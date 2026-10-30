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
$transport = Protocol::transferStats(['http_code' => 0, 'total_time' => 8.0, 'namelookup_time' => 0.02,
    'connect_time' => 0.05, 'appconnect_time' => 0.1, 'starttransfer_time' => 0.0,
    'url' => 'private-token-url', 'primary_ip' => 'private-address'], 28);
check($transport['curlCode'] === 28 && $transport['httpStatus'] === 0 && $transport['totalMs'] === 8000.0, 'failed cURL transfer retains error and timing instead of null status');
check($transport['dnsMs'] === 20.0 && $transport['tlsMs'] === 100.0, 'transport timings normalized to milliseconds');
check(!str_contains(json_encode($transport), 'private-token') && !str_contains(json_encode($transport), 'private-address'), 'transport diagnostics exclude URL and IP address');
fails(fn() => (new Client('EU', 'fixture-token'))->probe(), 'anonymous connectivity probe rejects bearer session');
$probeRequest = null;
$probe = new Client('EU', '', static function ($url, $method, $headers, $body) use (&$probeRequest) {
    $probeRequest = compact('url', 'method', 'headers', 'body'); return [405, ''];
}, 8, 'ipv4');
$probeResult = $probe->probe();
check($probeRequest['method'] === 'GET' && $probeRequest['body'] === [] && !str_contains(implode(' ', $probeRequest['headers']), 'Authorization'), 'connectivity probe never submits account or password');
check($probeResult['requests'][0]['httpStatus'] === 405 && $probeResult['requests'][0]['addressFamily'] === 'ipv4', 'HTTP method rejection still recorded as a received response');
check($probeResult['httpResponseReceived'] && !$probeResult['loginTested'], 'HTTP rejection confirms reachability without claiming login');
$anonymous = (new Client('EU', '', static fn() => [200, '{"code":100000}']))->probe();
check($anonymous['httpResponseReceived'] && !$anonymous['loginTested'] && $anonymous['requests'][0]['apiCode'] === 100000,
    'observed anonymous API 100000 preserves API code and confirms only reachability');
check(str_contains($anonymous['probeResult'], 'Server erreichbar') && str_contains($anonymous['probeResult'], 'Anmeldung nicht geprüft'), 'anonymous API error is not presented as a failed credential test');
$unreachable = (new Client('EU', '', static fn() => [0, '']))->probe();
check(!$unreachable['httpResponseReceived'] && !$unreachable['loginTested'], 'no HTTP response does not claim reachability or login');
$limit = $probeResult['requests'][0];
check($limit['timeoutMs'] <= 8000 && $limit['connectTimeoutMs'] <= $limit['timeoutMs'], 'request timeouts stay inside operation budget');
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
$stats = Protocol::discoveryStats(['list' => [device(), ['model' => Protocol::MODEL], ['model' => 'unknown', 'sn' => 'private-value']]]);
check($stats['supportedRecords'] === 2 && $stats['supportedUsable'] === 1 && $stats['supportedMissingSerial'] === 1, 'discovery diagnostics distinguish model detection from usable serial');
check(!str_contains(json_encode($stats), 'private-value') && !str_contains(json_encode($stats), 'fixture-720S'), 'discovery statistics do not expose serial numbers');
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
$diag = $client->diagnostics();
check($diag['requests'][2]['httpStatus'] === 200 && $diag['requests'][2]['apiCode'] === 0, 'HTTP and API status retained for diagnostics');
check($diag['discovery'][0]['counts']['supportedUsable'] === 1, 'discovery counts retained for diagnostics');
check(!str_contains(json_encode($diag), 'fixture-token') && !str_contains(json_encode($diag), 'fixture@example.invalid'), 'request diagnostics contain neither bearer token nor account');

$pages = [];
$client = new Client('EU', 'fixture-token', static function ($url) use (&$pages): array {
    parse_str(parse_url($url, PHP_URL_QUERY), $query); $page = (int) $query['pageNo']; $pages[] = $page;
    return reply(['totalPage' => 2, 'list' => [device('page-' . $page)]]);
});
check(count($client->discover()) === 2 && $pages === [1, 2, 1, 2], 'multi-page discovery combines both routes and deduplicates');
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
    public ?Throwable $discoverFailure = null;
    public bool $applyCommand = false;
    public int $logins = 0, $reads = 0;
    public ?Closure $duringSend = null;
    public function __construct() {
        parent::__construct('EU', 'fixture-token'); $this->devices = Protocol::devices([device()]);
        $this->stateData = ['poweron' => false, 'mode' => 'eco', 'temperature' => 70, 'ecolevel' => 70,
            'htalevel' => 1, 'ptcon' => false, 'connected' => true];
    }
    public function login(string $email, string $password): void { ++$this->logins; }
    public function session(): array { return ['region' => 'EU', 'token' => 'fixture-token', 'apiMode' => 'app']; }
    public function discover(): array { if ($this->discoverFailure !== null) { throw $this->discoverFailure; } return $this->devices; }
    public function state(string $serial): array { ++$this->reads; if ($this->failure !== null) { $e = $this->failure; $this->failure = null; throw $e; } return $this->stateData; }
    public function send(string $serial, array $params): string { $this->sent[] = $params; if ($this->duringSend !== null) { $hook = $this->duringSend; $this->duringSend = null; $hook(); } if ($this->sendFailure !== null) { $e = $this->sendFailure; $this->sendFailure = null; throw $e; } if ($this->applyCommand) { $this->stateData = array_merge($this->stateData, $params); } return 'control-reply'; }
}
class FixtureProbe extends Client
{
    public function __construct(private array $result) { parent::__construct('EU', ''); }
    public function probe(): array { return $this->result; }
}
class TestHeater extends DreoHeater
{
    public FakeClient $fake;
    public array $probeModes = [];
    public string $probeFailureMode = '';
    public function __construct() { $this->fake = new FakeClient(); $this->Create(); $this->properties['CloudAPI'] = 'app'; }
    protected function newClient(string $region, string $token): Client { return $this->fake; }
    protected function newProbeClient(string $region, string $mode): Client {
        $this->probeModes[] = $mode;
        if ($mode === $this->probeFailureMode) { throw new RuntimeException('private error detail'); }
        return new FixtureProbe(['requests' => [['httpStatus' => 405, 'curlCode' => 0, 'totalMs' => 12.0]]]);
    }
}
function heater(): TestHeater {
    $h = new TestHeater();
    $h->properties = array_merge($h->properties, ['Open' => true, 'Email' => 'fixture@example.invalid',
        'Password' => 'fixture-password', 'Serial' => 'fixture-720S']);
    $h->ApplyChanges(); return $h;
}
$h = new TestHeater(); $h->ApplyChanges();
check($h->status === 104 && $h->timers['Refresh'] === 0, 'disabled instance does not poll');
$setup = heater(); $setup->properties['Serial'] = ''; $setup->ApplyChanges();
check($setup->status === 201 && $setup->values['LastError'] === '', 'first setup does not report missing device as invalid credentials/configuration');
check($setup->timers['Refresh'] === 0 && !$setup->actions['Power'], 'first setup has no background error loop or active controls');
check($setup->DiscoverDevices(), 'search works with no selected device');
$setupForm = json_decode($setup->GetConfigurationForm(), true);
$select = array_values(array_filter($setupForm['elements'], fn($x) => ($x['name'] ?? '') === 'Serial'))[0];
$feedback = array_values(array_filter($setupForm['elements'], fn($x) => ($x['name'] ?? '') === 'Feedback'))[0];
check(count($select['options']) === 2, 'found device options survive form reopen');
check(str_contains($feedback['caption'], '1 Gerät(e) gefunden'), 'search result is visible near device selection after reopen');
check(!$setup->Refresh() && $setup->fake->reads === 0 && $setup->values['LastError'] === '', 'refresh before selection is harmless and does not query state');
$setup->fake->devices = []; $setup->DiscoverDevices();
check(str_contains($setup->buffers['SearchFeedback'], 'Anmeldung erfolgreich'), 'empty device list is distinguished from failed authentication');
$setup->fake->discoverFailure = new ApiException('Dreo antwortet mit HTTP 403.', 403); $setup->DiscoverDevices();
check(str_contains($setup->buffers['SearchFeedback'], 'Fehler: Dreo antwortet mit HTTP 403.'), 'discovery errors shown directly in form');
check($setup->timers['Refresh'] === 0, 'failed discovery before selection does not start automatic retries');
$setupForm = json_decode($setup->GetConfigurationForm(), true);
$feedback = array_values(array_filter($setupForm['elements'], fn($x) => ($x['name'] ?? '') === 'Feedback'))[0];
check(str_contains($feedback['caption'], 'HTTP 403'), 'discovery error survives form reopen');
$h = heater();
check(!$h->values['StateValid'] && !$h->actions['Power'], 'controls disabled before first valid state');
check($h->Refresh() && $h->values['CurrentTemperature'] === 21.1, 'module displays Celsius from real protocol values');
check($h->values['TargetTemperature'] === 21.0, 'module setpoint matches app whole Celsius');
check($h->timers['Refresh'] === 30000 && $h->status === 102, 'poll interval/status after successful refresh');
$readsBeforeSend = $h->fake->reads;
$h->SetPower(true);
check($h->fake->reads === $readsBeforeSend && $h->timers['Refresh'] === 1000, 'click sends without waiting for an HTTP confirmation poll');
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
$h->SetPower(true);
check($h->timers['CommandQueue'] === 100 && $h->fake->sent === [], 'concurrent command is deferred without exception or overlapping send');
$GLOBALS['busy'] = false;
$q = heater(); $q->Refresh(); $q->fake->applyCommand = true;
$q->fake->duringSend = static function () use ($q): void { $q->SetPower(false); $q->SetPower(false); };
$q->SetPower(true);
check($q->fake->sent === [['poweron' => true]] && $q->timers['CommandQueue'] === 1, 'rapid opposite clicks follow active send without an extra half-second delay');
check($q->values['StateValid'] && $q->values['LastError'] === '', 'double click does not invalidate state or create an error');
$q->RequestAction('RunCommandQueue', true);
check($q->fake->sent === [['poweron' => true], ['poweron' => false]], 'latest queued power intent is sent once after active command');
$q->Refresh();
check(!$q->values['Power'] && $q->timers['CommandQueue'] === 0 && $q->values['LastCommand'] === 'Durch Gerätestatus bestätigt.', 'queued power finishes with reported state and stopped timer');
$q->RequestAction('RunCommandQueue', true);
check(count($q->fake->sent) === 2, 'stale command timer never replays completed work');
$same = heater(); $same->Refresh(); $same->fake->applyCommand = true;
$same->fake->duringSend = static function () use ($same): void { $same->SetPower(false); };
$same->SetPower(false);
check($same->fake->sent === [['poweron' => false]] && $same->timers['CommandQueue'] === 0, 'same in-flight power click is coalesced');
$staleActive = heater(); $staleActive->Refresh();
$staleActive->buffers['ActiveCommand'] = json_encode(['id' => 'stale', 'generation' => $staleActive->buffers['CommandGeneration'], 'params' => ['poweron' => true], 'expires' => microtime(true) - 1]);
$staleActive->SetPower(true);
check($staleActive->fake->sent === [['poweron' => true]], 'expired active marker cannot swallow a new explicit request');
$reversed = heater(); $reversed->Refresh(); $reversed->fake->applyCommand = true;
$reversed->fake->duringSend = static function () use ($reversed): void { $reversed->SetPower(false); $reversed->SetPower(true); };
$reversed->SetPower(true); $reversed->RequestAction('RunCommandQueue', true); $reversed->Refresh();
check($reversed->fake->sent === [['poweron' => true]] && $reversed->values['Power'], 'latest intent matching active command cancels queued opposite intent');
$multiple = heater(); $multiple->Refresh(); $multiple->fake->applyCommand = true;
$GLOBALS['busy'] = true; $multiple->SetMode(1); $multiple->SetTargetTemperature(20); $multiple->SetTargetTemperature(21); $GLOBALS['busy'] = false;
check(count(json_decode($multiple->buffers['CommandQueue'], true)) === 2, 'queue keeps one latest value per distinct control');
$multiple->RequestAction('RunCommandQueue', true); $multiple->RequestAction('RunCommandQueue', true);
check($multiple->fake->sent === [['mode' => 'hotair'], ['ecolevel' => 70]], 'different controls retain order and latest setpoint without implicit power-on');
$expired = heater(); $expired->Refresh(); $GLOBALS['busy'] = true; $expired->SetPower(true); $GLOBALS['busy'] = false;
$waiting = json_decode($expired->buffers['CommandQueue'], true); $waiting['Power']['expires'] = microtime(true) - 1; $expired->buffers['CommandQueue'] = json_encode($waiting);
$expired->RequestAction('RunCommandQueue', true);
check($expired->fake->sent === [] && str_contains($expired->values['LastCommand'], 'abgelaufen'), 'expired queued heater command is discarded without execution');
$resumePoll = heater(); $resumePoll->Refresh();
$resumePoll->fake->duringSend = static function () use ($resumePoll): void { $resumePoll->SetPower(false); };
$resumePoll->SetPower(true);
$waiting = json_decode($resumePoll->buffers['CommandQueue'], true); $waiting['Power']['expires'] = microtime(true) - 1; $resumePoll->buffers['CommandQueue'] = json_encode($waiting);
$resumePoll->RequestAction('RunCommandQueue', true);
check(count($resumePoll->fake->sent) === 1 && $resumePoll->timers['Refresh'] === 1000, 'expired follow-up still resumes pending device confirmation polling');
foreach (['ApplyChanges', 'ResetSession'] as $cancelAction) {
    $cancelled = heater(); $cancelled->Refresh(); $GLOBALS['busy'] = true; $cancelled->SetPower(true); $GLOBALS['busy'] = false;
    $cancelled->$cancelAction(); $cancelled->RequestAction('RunCommandQueue', true);
    check($cancelled->fake->sent === [] && $cancelled->timers['CommandQueue'] === 0, $cancelAction . ' cancels unsent heater intent');
}
$lost = heater(); $lost->Refresh(); $lost->fake->sendFailure = new RuntimeException('socket timeout');
$lost->fake->duringSend = static function () use ($lost): void { $lost->SetPower(false); };
fails(fn() => $lost->SetPower(true), 'active uncertain send is still reported as an error');
$lost->RequestAction('RunCommandQueue', true);
check(count($lost->fake->sent) === 1 && !$lost->values['StateValid'] && $lost->timers['CommandQueue'] === 0, 'failed active send cancels queued work and never automatically resends');
$queuedFailure = heater(); $queuedFailure->Refresh(); $GLOBALS['busy'] = true; $queuedFailure->SetPower(true); $GLOBALS['busy'] = false;
$queuedFailure->fake->sendFailure = new RuntimeException('socket timeout');
$queuedFailure->RequestAction('RunCommandQueue', true);
check(str_contains($queuedFailure->values['LastError'], 'socket timeout') && $queuedFailure->timers['CommandQueue'] === 0, 'timer command failure is recorded without an uncaught exception');
$h->fake->failure = new RuntimeException('fixture@example.invalid fixture-password fixture-token fixture-720S'); $h->Refresh();
$diagnostics = $h->GetDiagnostics();
foreach (['fixture@example.invalid', 'fixture-password', 'fixture-token', 'fixture-720S'] as $secret) {
    check(!str_contains($diagnostics, $secret), 'diagnostics redacted: ' . $secret);
}
$form = json_decode($h->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
check(in_array('PasswordTextBox', array_column($form['elements'], 'type'), true), 'password uses masked form input');
$async = new TestHeater();
$async->RequestAction('TestConnection', true);
$pendingProbe = json_decode($async->GetDiagnostics(), true)['networkTest'];
check($pendingProbe['status'] === 'running' && $pendingProbe['next'] === 'auto', 'connection button immediately persists running status');
check($async->probeModes === [] && $async->timers['ConnectionTest'] === 1000, 'button schedules work without performing blocking network calls');
check(str_contains($async->values['ConnectionTestStatus'], 'gestartet'), 'button immediately displays visible acknowledgement');
check($async->fake->logins === 0 && $async->fake->sent === [], 'anonymous test starts without credentials or heater commands');
$probeId = $async->buffers['ProbeID']; $async->RequestAction('TestConnection', true);
check($async->buffers['ProbeID'] === $probeId, 'repeated click does not duplicate active test');
$async->RequestAction('RunConnectionTest', true);
$partialProbe = json_decode($async->GetDiagnostics(), true)['networkTest'];
check($async->probeModes === ['auto'] && isset($partialProbe['auto']) && !isset($partialProbe['ipv4']), 'first timer callback runs one probe and persists partial result');
check($partialProbe['status'] === 'running' && $partialProbe['next'] === 'ipv4' && $async->timers['ConnectionTest'] === 1000, 'second probe scheduled as a separate timer callback');
$async->RequestAction('RunConnectionTest', true);
$finishedProbe = json_decode($async->GetDiagnostics(), true)['networkTest'];
check($async->probeModes === ['auto', 'ipv4'] && $finishedProbe['status'] === 'completed', 'second callback completes both comparisons');
check($async->timers['ConnectionTest'] === 0 && str_contains($async->values['ConnectionTestStatus'], 'HTTP 405'), 'finished test stops its timer and displays connection result');
$async->RequestAction('RunConnectionTest', true);
check(count($async->probeModes) === 2, 'stale timer does not repeat completed probes');
$cancel = new TestHeater(); $cancel->TestConnection(); $cancel->ApplyChanges();
check(json_decode($cancel->GetDiagnostics(), true)['networkTest']['status'] === 'cancelled' && $cancel->timers['ConnectionTest'] === 0, 'configuration change cancels queued test');
$cancel->RequestAction('RunConnectionTest', true);
check($cancel->probeModes === [], 'cancelled timer performs no requests');
$failure = new TestHeater(); $failure->probeFailureMode = 'auto'; $failure->TestConnection();
$failure->RequestAction('RunConnectionTest', true); $failure->RequestAction('RunConnectionTest', true);
$failedProbe = json_decode($failure->GetDiagnostics(), true)['networkTest'];
check($failedProbe['status'] === 'completed' && isset($failedProbe['auto']['error']) && isset($failedProbe['ipv4']), 'unexpected error is recorded and does not prevent the comparison');
check(!str_contains($failure->GetDiagnostics(), 'private error detail'), 'unexpected probe exception does not expose raw details');
$asyncForm = json_decode($async->GetConfigurationForm(), true);
$connectionButton = array_values(array_filter($asyncForm['actions'], fn($x) => ($x['caption'] ?? '') === 'Verbindung prüfen'))[0];
check(str_contains($connectionButton['onClick'], 'IPS_RequestAction'), 'form uses native kernel action entry point');
// Execute the actual JSON callbacks, including their output and exception path.
function clickButton(TestHeater $instance, string $caption): string {
    $form = json_decode($instance->GetConfigurationForm(), true);
    $button = array_values(array_filter($form['actions'], fn($x) => $x['caption'] === $caption))[0];
    $GLOBALS['callbackInstance'] = $instance;
    $id = $instance->InstanceID;
    ob_start();
    try { eval($button['onClick']); return ob_get_contents(); }
    finally { ob_end_clean(); }
}
$ui = heater();
check(str_contains(clickButton($ui, 'Oberfläche testen'), '1.1.0: Klick empfangen. Instanz 101'), 'pure console callback immediately prints version and instance');
check(str_contains(clickButton($ui, 'Geräte suchen'), 'Klick empfangen'), 'actual search callback acknowledges before network work');
check($ui->timers['FormAction'] === 1000 && $ui->attributes['Devices'] === '[]', 'search waits for background timer');
check(str_contains(clickButton($ui, 'Status aktualisieren'), 'wartet bereits'), 'second form job does not replace queued search');
$ui->RequestAction('RunFormAction', true);
check(count(json_decode($ui->attributes['Devices'], true)) === 1 && $ui->timers['FormAction'] === 0, 'form timer performs search once');
$ui->RequestAction('RunFormAction', true);
check(str_contains(clickButton($ui, 'Status aktualisieren'), 'Klick empfangen'), 'actual refresh callback acknowledges immediately');
$ui->RequestAction('RunFormAction', true);
check($ui->values['StateValid'], 'form timer refreshes actual fixture state');
check(str_contains(clickButton($ui, 'Sitzung zurücksetzen'), 'Sitzung zurückgesetzt'), 'reset callback prints result');
check(!empty(json_decode(clickButton($ui, 'Diagnose anzeigen'), true)['lastFormAction']['receivedAtUtc']), 'diagnostics callback prints JSON with received timestamp');
check(str_contains(clickButton($ui, 'Verbindung prüfen'), 'Verbindungstest gestartet') && $ui->probeModes === [], 'connection callback prints before background probes');
$GLOBALS['callbackFailure'] = true;
check(str_contains(clickButton($ui, 'Diagnose anzeigen'), 'Symcon-Fehler: Kernel-Testfehler'), 'actual form callback exposes kernel errors as a dialog');
check(str_contains(clickButton($ui, 'Oberfläche testen'), 'Klick empfangen'), 'pure console test independent of kernel action failure');
$GLOBALS['callbackFailure'] = false;
$GLOBALS['callbackReject'] = true;
$rejectedUi = heater();
check(str_contains(clickButton($rejectedUi, 'Geräte suchen'), 'Symcon meldet einen Aufruffehler')
    && $rejectedUi->timers['FormAction'] === 0, 'rejected kernel call reports failure without claiming a queued search');
$GLOBALS['callbackReject'] = false;
check(json_decode(clickButton($rejectedUi, 'Diagnose anzeigen'), true)['version'] === '1.1.0', 'diagnostics returns JSON directly without action output');
$ui = heater(); clickButton($ui, 'Geräte suchen'); $ui->ApplyChanges(); $ui->RequestAction('RunFormAction', true);
check($ui->attributes['Devices'] === '[]' && $ui->timers['FormAction'] === 0, 'apply cancels waiting form work');
$ui = new TestHeater(); clickButton($ui, 'Status aktualisieren'); $ui->RequestAction('RunFormAction', true);
check(str_contains($ui->buffers['SearchFeedback'], 'zuerst ein Gerät auswählen'), 'refresh without selection produces visible explanation');
$ui = heater(); $ui->fake->discoverFailure = new ApiException('Dreo antwortet mit HTTP 403.', 403);
clickButton($ui, 'Geräte suchen'); $ui->RequestAction('RunFormAction', true);
check(str_contains($ui->buffers['SearchFeedback'], 'HTTP 403'), 'background discovery errors remain visible');
foreach ($asyncForm['actions'] as $button) { check($button['link'] === false, 'button output always opens dialog instead of detected link'); }
$root = dirname(__DIR__); $library = json_decode(file_get_contents($root . '/library.json'), true, 64, JSON_THROW_ON_ERROR);
$module = json_decode(file_get_contents($root . '/DreoHeater/module.json'), true, 64, JSON_THROW_ON_ERROR);
check($module['name'] === 'DreoHeater' && $module['prefix'] === 'DREO', 'module metadata matches class/API');
foreach ([$library['id'], $module['id']] as $guid) {
    check((bool) preg_match('/^\{[A-F0-9]{8}(-[A-F0-9]{4}){3}-[A-F0-9]{12}\}$/', $guid), 'GUID format');
}
check($library['compatibility']['version'] === '9.0', 'minimum kernel declared');
echo "PASS: $passed checks (synthetic fixtures and SDK substitute; no live cloud/device/kernel test).\n";
