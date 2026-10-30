<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../DreoHeater/module.php';

use SymconDreo\Client;
use SymconDreo\ModelCatalog;
use SymconDreo\Protocol;

$checks = 0;
function test(bool $condition, string $name): void {
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL models: ' . $name); }
    ++$checks;
}
function rejects(Closure $fn, string $name): void {
    try { $fn(); } catch (Throwable $e) { test(true, $name); return; }
    test(false, $name);
}
function sample(string $model): array {
    return match (ModelCatalog::profile($model)['type']) {
        'heater' => ['poweron' => false, 'mode' => 'eco', 'htalevel' => 2, 'ecolevel' => 70, 'temperature' => 68, 'ptcon' => false],
        'fan' => ['poweron' => true, 'windtype' => 1, 'windlevel' => 2, 'shakehorizon' => false],
        'purifier' => ['poweron' => true, 'mode' => 'auto-regular', 'windlevel' => 2, 'purifyon' => true],
        'humidifier' => ['poweron' => false, 'mode' => 1, 'rh' => 45, 'rhautolevel' => 50, 'rhsleeplevel' => 40, 'foglevel' => 2]
    };
}

// Exercise a mixed account, nested rooms, duplicate devices and unsupported combinations.
$records = [];
foreach (ModelCatalog::all() as $model => $profile) { $records[] = ['model' => $model, 'sn' => 'fixture-' . $model]; }
$found = Protocol::devices(['list' => $records, 'familyRooms' => [['devices' => $records]],
    'unsupported' => [['model' => 'DR-HSH016S', 'sn' => 'combo'], ['model' => 'DR-HAC006S', 'sn' => 'ac'], ['model' => 'DR-NEW999S', 'sn' => 'future']]]);
test(count($found) === count($records) && count($found) === 39, 'mixed account discovers each allowlisted model exactly once');
test(count(Protocol::devices(['list' => [['model' => 'DR-HSH040S']]])) === 0, 'missing serial never yields a usable device');
rejects(fn() => Protocol::devices(['list' => [['model' => 'DR-HSH040S', 'sn' => 'same'], ['model' => 'DR-HHM005S', 'sn' => 'same']]]), 'conflicting model for a serial fails closed');
test(Protocol::discoveryStats(['list' => $records])['supportedUsable'] === 39, 'discovery statistics cover the whole catalog');
$split = new Client('EU', 'fixture', static function ($url): array {
    $family = str_contains($url, '/family/');
    $record = ['model' => $family ? 'DR-HSH040S' : 'DR-HTF011S', 'sn' => $family ? 'heater' : 'fan'];
    return [200, json_encode(['code' => 0, 'data' => ['list' => [$record]]])];
});
test(count($split->discover()) === 2, 'different devices on family and standard routes are merged');
$partial = new Client('EU', 'fixture', static fn($url) => str_contains($url, '/family/')
    ? [200, '{"code":0,"data":{"list":[{"model":"DR-HSH040S","sn":"fixture"}]}}'] : [503, '']);
test(count($partial->discover()) === 1 && ($partial->diagnostics()['incompleteDiscovery'] ?? false), 'second route outage preserves devices but explicitly reports incomplete discovery');
$unauthorized = new Client('EU', 'fixture', static fn($url) => str_contains($url, '/family/')
    ? [200, '{"code":0,"data":{"list":[{"model":"DR-HSH040S","sn":"fixture"}]}}'] : [401, '']);
rejects(fn() => $unauthorized->discover(), 'partial list never hides authentication errors');

foreach (ModelCatalog::all() as $model => $profile) {
    $state = sample($model); $fields = ModelCatalog::fields($model, $state);
    test(isset($fields['Power']) && $fields['Power']['value'] === $state['poweron'], $model . ' validates power');
    test(ModelCatalog::command($model, $state, [], 'Power', false) === ['poweron' => false], $model . ' exact power command');
    if ($profile['type'] === 'heater') {
        test(ModelCatalog::command($model, $state, [], 'Mode', 2) === ['mode' => 'eco'], $model . ' heater mode does not turn on');
        test(ModelCatalog::command($model, $state, [], 'TargetTemperature', 21) === ['ecolevel' => 70], $model . ' Celsius target');
    } else {
        test(!isset($fields['Heating'], $fields['HeatLevel'], $fields['TargetTemperature']), $model . ' has no heater controls');
        rejects(fn() => ModelCatalog::command($model, $state, [], 'HeatLevel', 1), $model . ' rejects heater directive');
    }
}
rejects(fn() => ModelCatalog::fields('DR-UNKNOWN', ['poweron' => true]), 'unknown model never inherits a profile');
rejects(fn() => ModelCatalog::fields('DR-HTF011S', ['desired' => ['poweron' => true]]), 'desired-only power never enables controls');
rejects(fn() => ModelCatalog::fields('DR-HTF011S', ['poweron' => 'false']), 'ambiguous boolean rejected');
$state = sample('DR-HTF011S');
test(ModelCatalog::command('DR-HTF011S', $state, [], 'FanSpeed', 9) === ['windlevel' => 9], 'fan speed uses native steps');
rejects(fn() => ModelCatalog::command('DR-HTF011S', $state, [], 'FanSpeed', 10), 'fan speed model limit');
rejects(fn() => ModelCatalog::command('DR-HTF011S', $state, [], 'FanSpeed', 2.0), 'fan speed requires integer');
test(ModelCatalog::command('DR-HPF007S', $state, [], 'Mode', 1) === ['windtype' => 2], 'corrected HPF007S natural=2 mapping');
test(ModelCatalog::command('DR-HPF007S', $state, [], 'Mode', 3) === ['windtype' => 4], 'corrected HPF007S auto=4 mapping');
test(ModelCatalog::command('DR-HTF011S', ['fanon' => true], [], 'Power', false) === ['fanon' => false], 'reported fanon is used without guessing poweron');
$state['oscon'] = true; unset($state['shakehorizon']);
test(ModelCatalog::command('DR-HTF011S', $state, [], 'Oscillation', false) === ['oscon' => false], 'legacy tower oscillation directive');
$state['hoscon'] = true;
test(ModelCatalog::command('DR-HPF008S', $state, [], 'Oscillation', false) === ['hoscon' => false], 'circulator horizontal directive does not change vertical axis');
test(!isset(ModelCatalog::fields('DR-HTF011S', ['poweron' => true, 'windtype' => 99])['Mode']), 'unknown mode is hidden instead of mislabeled');
test(!isset(ModelCatalog::fields('DR-HTF011S', ['poweron' => true, 'windlevel' => 99])['FanSpeed']), 'invalid optional speed disables just that field');
$state = sample('DR-HTF007S');
$meta = ['controlsConf' => ['control' => [['type' => 'Speed', 'items' => [['value' => 1], ['value' => 8]]]]]];
rejects(fn() => ModelCatalog::command('DR-HTF007S', $state, [], 'FanSpeed', 8), 'unknown 007S revision remains at shared four-speed limit');
test(ModelCatalog::command('DR-HTF007S', $state, $meta, 'FanSpeed', 8) === ['windlevel' => 8], 'reported eight-speed capability enables eight');
$state['mcu_hardware_model'] = 'CMS89F7518/EUR';
rejects(fn() => ModelCatalog::command('DR-HTF007S', $state, $meta, 'FanSpeed', 8), 'old MCU overrides broad metadata');
$state = sample('DR-HAP009S');
test(ModelCatalog::command('DR-HAP009S', $state, [], 'Mode', 0) === ['mode' => 'auto-regular'], 'purifier firmware-specific auto directive');
test(!isset(ModelCatalog::fields('DR-HAP010S', ['poweron' => true, 'windlevel' => 2, 'purifyon' => false])['FanSpeed']), '530S never silently starts purification for speed changes');
$state = sample('DR-HSH017BS');
rejects(fn() => ModelCatalog::command('DR-HSH017BS', $state, [], 'TargetTemperature', 30), '017BS has lower target limit');
$state['tempoffset'] = 2;
test(ModelCatalog::fields('DR-HSH004S', $state)['CurrentTemperature']['value'] === 21.1, 'older heater temperature includes explicit Fahrenheit offset once');
test(ModelCatalog::fields('DR-HSH040S', $state)['CurrentTemperature']['value'] === 20.0, '720S reported temperature already includes offset');
unset($state['ptcon']);
test(!isset(ModelCatalog::fields('DR-HSH004S', $state)['Heating']), 'other heaters can omit heat-element status');
rejects(fn() => ModelCatalog::fields('DR-HSH040S', $state), '720S strict heat-element regression');
$state = sample('DR-HHM005S');
test(ModelCatalog::command('DR-HHM005S', $state, [], 'TargetHumidity', 55) === ['rhautolevel' => 55], 'humidifier automatic humidity directive');
$state['mode'] = 2;
test(ModelCatalog::command('DR-HHM005S', $state, [], 'TargetHumidity', 55) === ['rhsleeplevel' => 55], 'sleep mode writes its separate humidity target');
test(ModelCatalog::command('DR-HHM005S', $state, [], 'MistLevel', 4) === ['foglevel' => 4], 'mist uses explicit raw device level');
test(ModelCatalog::fields('DR-HHM005S', array_replace($state, ['rh' => 47.3]))['CurrentHumidity']['value'] === 47.3, 'fractional measured humidity is retained');
rejects(fn() => ModelCatalog::command('DR-HHM005S', $state, ['controlsConf' => ['humRange' => ['controlMin' => 40, 'controlMax' => 70]]], 'TargetHumidity', 35), 'reported narrower humidity range enforced');
rejects(fn() => ModelCatalog::command('DR-HHM005S', $state, [], 'TargetHumidity', 91), 'humidity maximum enforced');
unset($state['rhsleeplevel']);
rejects(fn() => ModelCatalog::command('DR-HHM005S', $state, [], 'TargetHumidity', 50), 'missing sleep target never writes automatic target');

// Production module + production Client REST decoding, with synthetic HTTP and a send capture.
class ModelFixtureClient extends Client {
    public array $sent = [];
    public array $fixtureState;
    public function __construct(public string $fixtureModel) {
        $this->fixtureState = sample($fixtureModel) + ['connected' => true];
        parent::__construct('EU', '', function ($url, $method, $headers, $body): array {
            $path = parse_url($url, PHP_URL_PATH);
            $data = match ($path) {
                '/api/oauth/login' => ['region' => 'EU', 'access_token' => 'fixture-token'],
                '/api/app/index/family/room/devices', '/api/v2/user-device/device/list' => ['list' => [['model' => $this->fixtureModel, 'sn' => 'fixture-serial']]],
                '/api/user-device/device/state' => ['mixed' => array_map(static fn($v) => ['state' => $v], $this->fixtureState)],
                default => throw new RuntimeException('Unexpected path')
            };
            return [200, json_encode(['code' => 0, 'data' => $data], JSON_THROW_ON_ERROR)];
        });
    }
    public function send(string $serial, array $params): string { $this->sent[] = $params; return 'fixture-accepted'; }
}
class ModelFixtureModule extends DreoHeater {
    public ModelFixtureClient $fixture;
    public function __construct(string $model) {
        $this->fixture = new ModelFixtureClient($model); $this->Create();
        $this->properties = array_replace($this->properties, ['Open' => true, 'Email' => 'fixture@example.invalid',
            'Password' => 'fixture-password', 'Serial' => 'fixture-serial']);
        $this->ApplyChanges();
    }
    protected function newClient(string $region, string $token): Client { return $this->fixture; }
}
foreach (['DR-HSH040S', 'DR-HSH004S', 'DR-HTF011S', 'DR-HAP009S', 'DR-HHM005S'] as $model) {
    $module = new ModelFixtureModule($model);
    test($module->fixture->sent === [] && !$module->values['StateValid'], $model . ' setup sends no command');
    test($module->Refresh() && $module->values['StateValid'], $model . ' production discovery/state integration');
    test(json_decode($module->GetDiagnostics(), true)['model'] === $model, $model . ' diagnostics reflect selected model');
    $module->SetPower(false);
    test($module->fixture->sent === [['poweron' => false]], $model . ' production action reaches transport');
    test($module->timers['Refresh'] === 1000 && $module->values['LastCommand'] !== 'Durch Gerätestatus bestätigt.', $model . ' confirmation remains separate');
    $module->fixture->fixtureState['poweron'] = false; $module->Refresh();
    test($module->values['LastCommand'] === 'Durch Gerätestatus bestätigt.', $model . ' actual-state confirmation');
    if (ModelCatalog::profile($model)['type'] !== 'heater') {
        test($module->isHidden('HeatLevel') && $module->isHidden('TargetTemperature') && !$module->actions['HeatLevel'], $model . ' heater controls hidden and disabled');
        rejects(fn() => $module->SetHeatLevel(1), $model . ' helper cannot bypass model restrictions');
    }
}
$module = new ModelFixtureModule('DR-HTF011S'); $module->Refresh();
test($module->presentations['FanSpeed']['MAX'] === 9, 'UI slider matches selected model range');
$GLOBALS['busy'] = true; $module->SetFanSpeed(4); $module->SetFanSpeed(5); $GLOBALS['busy'] = false;
test($module->fixture->sent === [] && count(json_decode($module->buffers['CommandQueue'], true)) === 1, 'fan follow-up clicks coalesce');
$module->RequestAction('RunCommandQueue', true);
test($module->fixture->sent === [['windlevel' => 5]], 'last requested fan speed sent once');
$module->fixture->fixtureModel = 'DR-HHM005S'; $module->fixture->fixtureState = sample('DR-HHM005S');
$module->ApplyChanges(); $module->Refresh();
test($module->isHidden('FanSpeed') && !$module->isHidden('TargetHumidity') && !$module->actions['FanSpeed'], 'type change hides old controls and shows humidity');
$GLOBALS['busy'] = true; $module->SetTargetHumidity(55); $GLOBALS['busy'] = false;
$module->fixture->fixtureState['mode'] = 2; $module->Refresh();
$before = count($module->fixture->sent); $module->RequestAction('RunCommandQueue', true);
test(count($module->fixture->sent) === $before && str_contains($module->values['LastError'], 'geändert'), 'queued humidity target cannot cross automatic/sleep mode change');
$module = new ModelFixtureModule('DR-HSH040S'); $module->Refresh(); $powerBefore = $module->values['Power'];
// Simulate upgrading an existing instance whose new variables do not yet exist.
foreach (['FanSpeed', 'Oscillation', 'CurrentHumidity', 'TargetHumidity', 'MistLevel'] as $ident) { unset($module->values[$ident]); }
$module->ApplyChanges();
test(array_key_exists('FanSpeed', $module->values) && $module->values['Power'] === $powerBefore, 'upgrade registers extra variables while retaining existing values');
$module->Refresh();
test(!$module->isHidden('HeatLevel') && $module->isHidden('TargetHumidity'), '720S upgrade retains heater display');
$module->fixture->fixtureModel = 'DR-HTF011S'; $module->fixture->fixtureState = sample('DR-HTF011S');
$module->DiscoverDevices(); $before = count($module->fixture->sent);
rejects(fn() => $module->SetPower(true), 'rediscovered model change requires a fresh status before any control');
test(count($module->fixture->sent) === $before && !$module->values['StateValid'], 'old heater state cannot authorize commands for a changed model');
test($module->Refresh() && $module->values['StateValid'], 'fresh status restores changed model controls');
$module->properties['Open'] = false; $module->ApplyChanges();
test(!in_array(true, $module->actions, true), 'deactivation disables every new control');
$client = new Client('EU', 'fixture', static fn() => [200, json_encode(['code' => 0, 'data' => $records])], 22, 'auto', 'openapi');
test(count($client->discover()) === 1, 'experimental OpenAPI remains restricted to its implemented 720S adapter');

echo 'PASS models: ' . $checks . ' checks (39 explicit profiles; synthetic protocol/module integration; no new hardware claims).' . PHP_EOL;
