<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../DreoHeater/module.php';

use SymconDreo\Client;
use SymconDreo\Protocol;
use SymconDreo\ApiException;

$checks = 0;
function verify(bool $ok, string $name): void { global $checks; if (!$ok) { throw new RuntimeException($name); } ++$checks; }
function reject(Closure $fn, string $name): void {
    try { $fn(); } catch (RuntimeException $e) { verify(true, $name); return; }
    verify(false, $name);
}
function response(array $data): array { return [200, json_encode(['code' => 0, 'data' => $data], JSON_THROW_ON_ERROR)]; }
function config(string $unit = 'fahrenheit'): array {
    // Synthetic heater configuration in the schema consumed by hass-dreoverse, not a captured 720S payload.
    return ['temperature_unit' => $unit, 'temperature_range' => $unit === 'celsius' ? [5, 35] : [41, 95],
        'hvac_mode_relate_map' => [
            'fan_only' => ['report' => ['hvac_mode_value' => 'fan_only'], 'controls' => [
                ['directive_name' => 'power_switch', 'directive_value' => true], ['directive_name' => 'hvacmode', 'directive_value' => 'fan_only']]],
            'heat' => ['report' => ['hvac_mode_value' => 'heat'], 'controls' => [['directive_name' => 'hvacmode', 'directive_value' => 'heat']]],
            'eco' => ['report' => ['hvac_mode_value' => 'heat', 'directive_value' => 'eco'], 'controls' => [['directive_name' => 'hvacmode', 'directive_value' => 'eco']]],
        ]];
}
function rawState(): array { return ['power_switch' => false, 'connected' => true, 'hvacmode' => 'eco', 'temperature' => 70, 'ecolevel' => 70, 'htalevel' => 2]; }
$requests = [];
$http = static function ($url, $method, $headers, $body) use (&$requests): array {
    $requests[] = compact('url', 'method', 'headers', 'body');
    return match (parse_url($url, PHP_URL_PATH)) {
        '/api/oauth/login' => response(['access_token' => 'secret-fixture-token:EU']),
        '/api/v2/device/list' => response([['deviceModel' => Protocol::MODEL, 'serialNumber' => 'fixture-sn', 'config' => ['heater_entity_config' => config()]]]),
        '/api/v2/device/state' => response(rawState()),
        '/api/v2/device/control' => response(['accepted' => true, 'desired' => $body['desired']]),
        default => throw new RuntimeException('Unexpected endpoint.'),
    };
};
$client = new Client('EU', '', $http, 22, 'auto', 'openapi', config());
$client->login('fixture@example.invalid', 'fixture-password');
verify(str_starts_with($requests[0]['url'], 'https://open-api-us.dreo-tech.com/api/oauth/login?'), 'official login starts on US endpoint even for EU');
verify($requests[0]['body'] === ['client_id' => '89ef537b2202481aaaf9077068bcb0c9', 'client_secret' => '41b20a1f60e9499e89c8646c31f93ea1',
    'grant_type' => 'openapi', 'scope' => 'all', 'email' => 'fixture@example.invalid', 'password' => md5('fixture-password')], 'login body agrees with official SDK');
verify($client->session() === ['region' => 'EU', 'token' => 'secret-fixture-token', 'apiMode' => 'openapi'], 'token suffix selects region and is removed from stored bearer');
$devices = $client->discover();
verify(count($devices) === 1 && $devices[0]['serial'] === 'fixture-sn' && $devices[0]['heaterConfig'] === config(), 'official list aliases and heater configuration are retained');
verify(count(Protocol::devices([['model' => '', 'deviceModel' => Protocol::MODEL, 'sn' => '', 'devicesn' => 'fallback-sn']])) === 1, 'empty optional aliases do not hide a valid device');
verify(Protocol::devices([['model' => Protocol::MODEL, 'sn' => 'x', 'deviceName' => []]])[0]['name'] === 'Dreo 720S', 'malformed device name does not emit PHP output');
verify(str_starts_with($requests[1]['url'], 'https://open-api-eu.dreo-tech.com/api/v2/device/list?'), 'list uses EU after official token login');
$state = $client->state('fixture-sn');
verify($state['poweron'] === false && $state['mode'] === 'eco' && $state['temperature'] === 70 && $state['_modeWritable'], 'official state is normalized without inventing ptcon');
verify(!isset($state['ptcon']), 'missing heating sensor is not fabricated');
verify($client->send('fixture-sn', Protocol::command('Power', true)) === 'http-accepted', 'official control uses HTTP');
verify($requests[3]['body'] === ['devicesn' => 'fixture-sn', 'desired' => ['power_switch' => true]], 'control envelope agrees with official SDK');
foreach ($requests as $i => $request) {
    parse_str(parse_url($request['url'], PHP_URL_QUERY), $query);
    verify(isset($query['timestamp']) && $query['dreover'] === '1.0.0', 'every official request carries API version and timestamp');
    verify(in_array('UA: openapi/1.0.0', $request['headers'], true), 'official UA header');
    verify(!str_contains(implode(' ', $request['headers']), ':EU'), 'bearer never includes region suffix');
    verify(($i === 0) === !in_array('Authorization: Bearer secret-fixture-token', $request['headers'], true), 'login omits bearer, authenticated requests use stripped token');
}
foreach (['secret-fixture-token', 'fixture@example.invalid', 'fixture-password', 'fixture-sn'] as $private) {
    verify(!str_contains(json_encode($client->diagnostics()), $private), 'diagnostics exclude private data');
}
foreach (['token-value' => 'NA', 'token-value:NA' => 'NA', 'token-value:eu' => 'EU'] as $token => $region) {
    $c = new Client('EU', '', static fn() => response(['token' => $token]), 22, 'auto', 'openapi'); $c->login('a', 'b');
    verify($c->session()['region'] === $region && $c->session()['token'] === 'token-value', 'token alias and official default region');
}
foreach (['', 'bad:OTHER', ':EU', "bad\r\n:EU"] as $token) {
    reject(fn() => (new Client('EU', '', static fn() => response(['access_token' => $token]), 22, 'auto', 'openapi'))->login('a', 'b'), 'invalid tokens rejected');
}
foreach ([401, 403, 429, 500] as $status) {
    reject(fn() => (new Client('EU', '', static fn() => [$status, ''], 22, 'auto', 'openapi'))->login('a', 'b'), 'HTTP auth/rate/server errors are failures');
}
reject(fn() => (new Client('EU', '', static fn() => [200, '{"code":100022}'], 22, 'auto', 'openapi'))->login('a', 'b'), 'API auth rejection is a failure');
reject(fn() => (new Client('EU', '', static fn() => [200, 'broken'], 22, 'auto', 'openapi'))->login('a', 'b'), 'invalid JSON rejected');
reject(fn() => Protocol::openState(['desired' => rawState()], config()), 'desired-only data cannot confirm device state');
verify(Protocol::openState(['desired' => ['power_switch' => true], 'reported' => rawState()], config())['poweron'] === false, 'reported state wins over desired request');
reject(fn() => Protocol::openState(rawState(), []), 'unknown temperature unit blocks state rather than guessing');
$celsius = rawState(); $celsius['temperature'] = 21; $celsius['ecolevel'] = 21;
$converted = Protocol::openState($celsius, config('celsius'));
verify(Protocol::celsius($converted['temperature']) === 21.0 && Protocol::targetCelsius($converted['ecolevel']) === 21.0, 'Celsius state is not interpreted as Fahrenheit');
verify(Protocol::matches($converted, Protocol::command('TargetTemperature', 21)), 'native Celsius target confirms pending canonical request despite Fahrenheit rounding');
foreach (range(5, 35) as $target) {
    $canonical = Protocol::command('TargetTemperature', $target);
    verify(Protocol::openCommand($canonical, config('celsius')) === ['ecolevel' => $target], 'native Celsius target');
    verify(Protocol::openCommand($canonical, config()) === $canonical, 'native Fahrenheit target');
}
$restricted = config(); $restricted['temperature_range'] = [41, 85];
reject(fn() => Protocol::openCommand(Protocol::command('TargetTemperature', 35), $restricted), 'model-specific temperature limit blocks command before HTTP');
foreach ([0 => 'fan_only', 1 => 'heat', 2 => 'eco'] as $mode => $native) {
    verify(Protocol::openCommand(Protocol::command('Mode', $mode), config()) === ['hvacmode' => $native], 'config-derived mode never includes implicit power-on');
}
$ambiguous = config(); $ambiguous['hvac_mode_relate_map']['another-heat'] = $ambiguous['hvac_mode_relate_map']['heat'];
reject(fn() => Protocol::openCommand(['mode' => 'hotair'], $ambiguous), 'ambiguous model mapping blocks writes');
$unreviewed = config(); $unreviewed['hvac_mode_relate_map']['heat']['controls'][] = ['directive_name' => 'unreviewed_setting', 'directive_value' => 'x'];
reject(fn() => Protocol::openCommand(['mode' => 'hotair'], $unreviewed), 'unreviewed secondary controls are not sent');
$rejectControl = new Client('EU', 'fixture-token', static fn() => response(['accepted' => false]), 22, 'auto', 'openapi', config());
reject(fn() => $rejectControl->send('fixture-sn', ['poweron' => true]), 'explicit command rejection stays a failure');
verify((new Client('EU', 'fixture-token', static fn() => [200, '{"code":0}'], 22, 'auto', 'openapi', config()))->send('fixture-sn', ['poweron' => false]) === 'http-accepted', 'empty successful control response means cloud acceptance only');
$instance = new DreoHeater(); $instance->Create(); verify($instance->properties['CloudAPI'] === 'openapi', 'module defaults to manufacturer API');
class OpenHeater extends DreoHeater
{
    public array $requests = [], $liveState;
    public bool $unauthorizedOnce = false;
    public function __construct() {
        $this->liveState = rawState(); $this->Create();
        $this->properties = array_merge($this->properties, ['Open' => true, 'Email' => 'fixture@example.invalid', 'Password' => 'fixture-password', 'Serial' => 'fixture-sn']);
        $this->ApplyChanges();
    }
    protected function httpTransport(): ?Closure {
        return function ($url, $method, $headers, $body): array {
            $path = parse_url($url, PHP_URL_PATH); $this->requests[] = compact('url', 'method', 'headers', 'body');
            if ($path === '/api/oauth/login') { return response(['access_token' => 'secret-fixture-token:EU']); }
            if ($path === '/api/v2/device/list') { return response([['model' => Protocol::MODEL, 'deviceSn' => 'fixture-sn', 'config' => ['heater_entity_config' => config()]]]); }
            if ($path === '/api/v2/device/state') {
                if ($this->unauthorizedOnce) { $this->unauthorizedOnce = false; return [401, '']; }
                return response($this->liveState);
            }
            if ($path === '/api/v2/device/control') { return response(['accepted' => true]); }
            throw new RuntimeException('Unexpected request.');
        };
    }
}
$h = new OpenHeater(); verify($h->requests === [], 'create and apply never send network commands');
verify($h->Refresh() && $h->values['StateValid'] && !$h->values['Power'], 'full module login, discovery and official state work together');
verify(!$h->values['HeatingKnown'] && $h->values['CurrentTemperature'] === 21.1, 'module separates absent heating sensor and converts explicit Fahrenheit');
$loginCount = count(array_filter($h->requests, fn($r) => str_contains($r['url'], '/oauth/login')));
$h->Refresh(); verify(count(array_filter($h->requests, fn($r) => str_contains($r['url'], '/oauth/login'))) === $loginCount, 'official session is reused across polls');
$h->SetPower(true);
verify(!$h->values['Power'] && str_contains($h->values['LastCommand'], 'ausstehend'), 'cloud acceptance does not optimistically set heater power');
$h->liveState['power_switch'] = true; $h->Refresh();
verify($h->values['Power'] && str_contains($h->values['LastCommand'], 'Gerätestatus bestätigt'), 'later reported power confirms actual requested state');
$h->SetMode(0);
$controls = array_values(array_filter($h->requests, fn($r) => str_contains($r['url'], '/device/control')));
verify(end($controls)['body']['desired'] === ['hvacmode' => 'fan_only'], 'module mode sends only model-configured mode without power change');
$h->unauthorizedOnce = true; verify($h->Refresh(), 'one 401 is recovered by one login and one state retry');
verify(count(array_filter($h->requests, fn($r) => str_contains($r['url'], '/oauth/login'))) === $loginCount + 1, '401 recovery performs exactly one extra login');
verify(!str_contains($h->GetDiagnostics(), 'secret-fixture-token') && !str_contains($h->GetDiagnostics(), 'fixture-sn'), 'full module diagnostics hide token and serial');
echo "PASS OpenAPI: $checks checks (official transport contract; synthetic heater data; no live device).\n";
