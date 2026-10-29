<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../DreoHeater/module.php';

use SymconDreo\Client;
use SymconDreo\Protocol;
use SymconDreo\WebSocket;
use SymconDreo\ApiException;

$passed = 0;
function check(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $name); }
    ++$passed;
}
function fails(Closure $fn, string $name): void
{
    try { $fn(); } catch (Throwable $e) { check(true, $name); return; }
    check(false, $name);
}
function reply(array $data): array { return [200, json_encode(['code' => 0, 'data' => $data], JSON_THROW_ON_ERROR)]; }
function device(string $serial = 'fixture-720S'): array { return ['model' => Protocol::MODEL, 'sn' => $serial, 'deviceName' => 'Test Heater']; }

check(Protocol::celsius(70) === 21.1, '70F is 21.1C');
check(Protocol::celsius(32) === 0.0, 'freezing point');
for ($c = 5; $c <= 35; ++$c) {
    $f = Protocol::command('TargetTemperature', $c)['ecolevel'];
    check(Protocol::targetCelsius($f) === (float) $c, 'whole-C setpoint roundtrip ' . $c);
}
foreach ([4, 36, 20.5, NAN, INF, '21', true] as $invalid) {
    fails(fn() => Protocol::command('TargetTemperature', $invalid), 'invalid setpoint blocked');
}
foreach ([['Power', 1], ['Mode', -1], ['Mode', 3], ['Mode', '2'], ['HeatLevel', 0], ['HeatLevel', 4], ['ptcon', true]] as [$key, $value]) {
    fails(fn() => Protocol::command($key, $value), 'invalid/unsupported control blocked');
}
check(Protocol::command('Mode', 2) === ['mode' => 'eco'], 'mode does not silently power heater on');
check(Protocol::command('HeatLevel', 3) === ['htalevel' => 3], 'heat level does not silently power heater on');
check(Protocol::command('Power', false) === ['poweron' => false], 'off uses poweron, not mode=off');
$devices = Protocol::devices(['list' => [device(), ['model' => 'DR-UNKNOWN', 'sn' => 'unknown']],
    'familyRooms' => [['devices' => [device(), device('another')]]]]);
check(count($devices) === 2, 'nested family discovery deduplicates and filters models');
check(Protocol::unwrap(['mixed' => ['poweron' => ['state' => false], 'temperature' => ['state' => 70]]]) === ['poweron' => false, 'temperature' => 70], 'false and numeric state unwrapped');
fails(fn() => Protocol::unwrap(['mixed' => 'wrong']), 'unexpected state shape blocked');
check(!Protocol::matches(['poweron' => false], ['poweron' => true]), 'wrong power is not confirmation');
check(!Protocol::matches([], ['poweron' => false]), 'missing power is not false confirmation');

$requests = [];
$http = static function ($url, $method, $headers, $body) use (&$requests): array {
    $requests[] = compact('url', 'method', 'headers', 'body');
    if (str_contains($url, '/login')) {
        return reply(['region' => 'EU', 'access_token' => 'fixture-token']);
    }
    return reply(['list' => [device()]]);
};
$client = new Client('NA', '', $http);
$client->login('fixture@example.invalid', 'fixture-password');
check(count($requests) === 2, 'region redirect bounded and follows EU');
check(str_contains($requests[0]['url'], 'app-api-us.') && str_contains($requests[1]['url'], 'app-api-eu.'), 'correct region hosts');
check($requests[0]['body']['password'] === md5('fixture-password'), 'password hash matches app API');
check(!in_array('Authorization: Bearer fixture-token', $requests[1]['headers'], true), 'login does not send bearer token');
check(count($client->discover()) === 1, 'discovery works with authenticated client');
check(in_array('Authorization: Bearer fixture-token', $requests[2]['headers'], true), 'authenticated request has bearer header');
check(str_contains($requests[2]['url'], '/family/room/devices'), 'EU workaround is first route');

$pages = [];
$client = new Client('EU', 'fixture-token', static function ($url) use (&$pages): array {
    parse_str(parse_url($url, PHP_URL_QUERY), $query); $page = (int) $query['pageNo']; $pages[] = $page;
    return reply(['totalPage' => 2, 'list' => [device('page-' . $page)]]);
});
check(count($client->discover()) === 2 && $pages === [1, 2], 'multi-page discovery');
$routes = [];
$client = new Client('EU', 'fixture-token', static function ($url) use (&$routes): array {
    $routes[] = $url;
    return str_contains($url, '/family/') ? [404, ''] : reply(['list' => [device()]]);
});
check(count($client->discover()) === 1 && count($routes) === 2, 'unsupported family route falls back');
foreach ([[401, '{}'], [200, 'not json'], [200, '{"code":100022,"msg":"fixture-secret"}'], [200, '{"code":0,"data":"bad"}']] as $response) {
    $client = new Client('EU', 'fixture-token', static fn() => $response);
    try { $client->state('fixture'); check(false, 'bad response rejected'); }
    catch (ApiException $e) { check(!str_contains($e->getMessage(), 'fixture-secret'), 'error does not leak raw response'); }
}
$client = new Client('EU', '', static fn() => reply(['region' => 'CN', 'access_token' => 'fixture-token']));
fails(fn() => $client->login('x', 'y'), 'unrecognized login region rejected');
$client = new Client('EU', '', static fn() => reply(['region' => 'EU', 'access_token' => "bad\r\ntoken"]));
fails(fn() => $client->login('x', 'y'), 'bearer header injection blocked');

// RFC6455 codec exercises actual streams and masked client frames, no real cloud.
foreach (['', 'hello', str_repeat('x', 126), str_repeat('y', 66000)] as $payload) {
    $frame = WebSocket::frame($payload); $b = ord($frame[1]); $len = $b & 127; $offset = 2;
    if ($len === 126) { $len = unpack('n', substr($frame, 2, 2))[1]; $offset = 4; }
    elseif ($len === 127) { $len = unpack('N', substr($frame, 6, 4))[1]; $offset = 10; }
    $mask = substr($frame, $offset, 4); $encoded = substr($frame, $offset + 4); $decoded = '';
    for ($i = 0; $i < $len; ++$i) { $decoded .= $encoded[$i] ^ $mask[$i % 4]; }
    check(($b & 128) !== 0 && $decoded === $payload, 'outbound frame masking/length ' . strlen($payload));
}
$stream = fopen('php://temp', 'r+'); fwrite($stream, "\x81\x05hello"); rewind($stream);
check(WebSocket::readFrame($stream, microtime(true) + 1) === [1, 'hello', true], 'read server frame'); fclose($stream);
$stream = fopen('php://temp', 'r+'); fwrite($stream, "\x89\x01x"); rewind($stream);
check(WebSocket::readFrame($stream, microtime(true) + 1) === [9, 'x', true], 'read server ping'); fclose($stream);
$stream = fopen('php://temp', 'r+'); fwrite($stream, "\x81\x85"); rewind($stream);
fails(fn() => WebSocket::readFrame($stream, microtime(true) + 1), 'masked server frame rejected'); fclose($stream);
$stream = fopen('php://temp', 'r+'); fwrite($stream, "\x81\x05he"); rewind($stream);
fails(fn() => WebSocket::readFrame($stream, microtime(true) + 1), 'truncated frame rejected'); fclose($stream);

class FakeClient extends Client
{
    public array $sent = [], $devices, $stateData;
    public ?Throwable $failure = null;
    public ?Throwable $sendFailure = null;
    public bool $applyCommand = false;
    public int $logins = 0, $reads = 0;
    public function __construct() {
        parent::__construct('EU', 'fixture-token'); $this->devices = Protocol::devices([device()]);
        $this->stateData = ['poweron' => false, 'mode' => 'eco', 'temperature' => 70, 'ecolevel' => 70,
            'htalevel' => 1, 'ptcon' => false, 'connected' => true];
    }
    public function login(string $email, string $password): void { ++$this->logins; }
    public function session(): array { return ['region' => 'EU', 'token' => 'fixture-token']; }
    public function discover(): array { return $this->devices; }
    public function state(string $serial): array { ++$this->reads; if ($this->failure !== null) { $e = $this->failure; $this->failure = null; throw $e; } return $this->stateData; }
    public function send(string $serial, array $params): string { $this->sent[] = $params; if ($this->sendFailure !== null) { $e = $this->sendFailure; $this->sendFailure = null; throw $e; } if ($this->applyCommand) { $this->stateData = array_merge($this->stateData, $params); } return 'control-reply'; }
}
class TestHeater extends DreoHeater
{
    public FakeClient $fake;
    public function __construct() { $this->fake = new FakeClient(); $this->Create(); }
    protected function newClient(string $region, string $token): Client { return $this->fake; }
}
function heater(): TestHeater {
    $h = new TestHeater();
    $h->properties = array_merge($h->properties, ['Open' => true, 'Email' => 'fixture@example.invalid',
        'Password' => 'fixture-password', 'Serial' => 'fixture-720S']);
    $h->ApplyChanges(); return $h;
}
$h = new TestHeater(); $h->ApplyChanges();
check($h->status === 104 && $h->timers['Refresh'] === 0, 'disabled instance does not poll');
$h = heater();
check(!$h->values['StateValid'] && !$h->actions['Power'], 'controls disabled before first valid state');
check($h->Refresh() && $h->values['CurrentTemperature'] === 21.1, 'module displays Celsius from real protocol values');
check($h->values['TargetTemperature'] === 21.0, 'module setpoint matches app whole Celsius');
check($h->timers['Refresh'] === 30000 && $h->status === 102, 'poll interval/status after successful refresh');
$h->SetPower(true);
check($h->values['Power'] === false, 'server acceptance never overwrites actual power');
check(str_contains($h->values['LastCommand'], 'ausstehend'), 'unconfirmed command explicitly pending');
$h->fake->stateData['poweron'] = true; $h->Refresh();
check($h->values['LastCommand'] === 'Durch Gerätestatus bestätigt.', 'actual state confirms command');
$uncertain = heater(); $uncertain->Refresh(); $uncertain->fake->sendFailure = new RuntimeException('socket timeout');
fails(fn() => $uncertain->SetPower(true), 'lost acknowledgement is an explicit command error');
check(!$uncertain->values['Power'] && str_contains($uncertain->values['LastCommand'], 'unklar'), 'lost acknowledgement does not invent success');
$uncertain->fake->stateData['poweron'] = true; $uncertain->Refresh();
check(str_contains($uncertain->values['LastCommand'], 'Versand war unklar'), 'desired state after uncertain send does not invent acceptance');
check(count($uncertain->fake->sent) === 1, 'uncertain command never automatically resent');
$h->fake->failure = new ApiException('expired', 401); $before = $h->fake->logins;
check($h->Refresh() && $h->fake->logins === $before + 1, '401 refreshes session exactly once');
$h->fake->failure = new RuntimeException('network failed');
check(!$h->Refresh() && !$h->values['StateValid'] && !$h->actions['Power'], 'network error invalidates state and controls');
fails(fn() => $h->SetPower(true), 'cannot power heater with invalid state');
$h->Refresh(); $h->fake->stateData['connected'] = false; $h->Refresh();
check($h->status === 204 && !$h->actions['Power'] && !$h->values['Online'], 'offline disables controls');
$before = count($h->fake->sent); fails(fn() => $h->SetPower(true), 'offline command blocked');
check(count($h->fake->sent) === $before, 'offline emits no command');
unset($h->fake->stateData['connected']); $h->Refresh();
check(!$h->values['Online'] && str_contains($h->values['Availability'], 'Unbekannt'), 'missing online field is not invented as online');
$h->fake->failure = new ApiException('account rejected', 100022); $h->Refresh();
check($h->timers['Refresh'] === 0, 'bad credentials stop automatic login retries');
$h = heater(); $h->fake->devices = [];
check(!$h->Refresh() && !$h->actions['Power'], 'wrong account/device fails closed');
check(count($h->fake->sent) === 0, 'unknown device emits no command');
$h = heater(); unset($h->fake->stateData['temperature']);
check(!$h->Refresh() && !$h->values['StateValid'], 'partial state does not leave old temperature falsely fresh');
$h = heater(); $h->Refresh(); $GLOBALS['busy'] = true;
check(!$h->Refresh() && $h->values['StateValid'], 'busy poll does not invalidate concurrent state');
fails(fn() => $h->SetPower(true), 'concurrent command blocked'); $GLOBALS['busy'] = false;
$h->fake->failure = new RuntimeException('fixture@example.invalid fixture-password fixture-token fixture-720S'); $h->Refresh();
$diagnostics = $h->GetDiagnostics();
foreach (['fixture@example.invalid', 'fixture-password', 'fixture-token', 'fixture-720S'] as $secret) {
    check(!str_contains($diagnostics, $secret), 'diagnostics redacted: ' . $secret);
}
$form = json_decode($h->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
check(in_array('PasswordTextBox', array_column($form['elements'], 'type'), true), 'password uses masked form input');
$root = dirname(__DIR__); $library = json_decode(file_get_contents($root . '/library.json'), true, 64, JSON_THROW_ON_ERROR);
$module = json_decode(file_get_contents($root . '/DreoHeater/module.json'), true, 64, JSON_THROW_ON_ERROR);
check($module['name'] === 'DreoHeater' && $module['prefix'] === 'DREO', 'module metadata matches class/API');
foreach ([$library['id'], $module['id']] as $guid) {
    check((bool) preg_match('/^\{[A-F0-9]{8}(-[A-F0-9]{4}){3}-[A-F0-9]{12}\}$/', $guid), 'GUID format');
}
check($library['compatibility']['version'] === '9.0', 'minimum kernel declared');
echo "PASS: $passed checks (synthetic fixtures and SDK substitute; no live cloud/device/kernel test).\n";
