<?php
declare(strict_types=1);

namespace SymconDreo;

require_once __DIR__ . '/Protocol.php';
require_once __DIR__ . '/WebSocket.php';
require_once __DIR__ . '/EndpointCache.php';

class ApiException extends \RuntimeException {}

class Client
{
    private string $region;
    private string $token;
    private float $deadline;
    private ?\Closure $http;
    private array $diagnostics = [];
    private string $addressFamily;
    private string $apiMode;
    private array $heaterConfig;
    private ?EndpointCache $endpoints;

    public function __construct(string $region, string $token = '', ?\Closure $http = null, float $budget = 22.0, string $addressFamily = 'auto', string $apiMode = 'app', array $heaterConfig = [], ?EndpointCache $endpoints = null)
    {
        if (!in_array($region, ['EU', 'NA'], true)) { throw new \InvalidArgumentException('Ungültige Region.'); }
        $this->region = $region;
        $this->token = $token;
        $this->http = $http;
        if (!in_array($addressFamily, ['auto', 'ipv4'], true)) { throw new \InvalidArgumentException('Ungültiger Verbindungsmodus.'); }
        $this->addressFamily = $addressFamily;
        if (!in_array($apiMode, ['app', 'openapi'], true)) { throw new \InvalidArgumentException('Ungültige Cloud-API.'); }
        $this->apiMode = $apiMode;
        $this->heaterConfig = $heaterConfig;
        $this->endpoints = $endpoints;
        $this->deadline = microtime(true) + max(0, min(22.0, $budget));
    }

    public function session(): array { return ['region' => $this->region, 'token' => $this->token, 'apiMode' => $this->apiMode]; }
    public function diagnostics(): array { return $this->diagnostics; }

    public function probe(): array
    {
        if ($this->token !== '') { throw new \LogicException('Verbindungstest darf kein Zugriffstoken verwenden.'); }
        try { $this->api('/api/oauth/login', 'GET', [], $this->apiMode === 'openapi' ? 'NA' : null); }
        catch (ApiException $e) { $this->diagnostics['probeResult'] = $e->getMessage(); }
        // HTTP 4xx/5xx can still prove connectivity. This is not a credential/login test.
        $status = (int) ($this->diagnostics['requests'][0]['httpStatus'] ?? 0);
        $this->diagnostics['httpResponseReceived'] = $status >= 100 && $status <= 599;
        $this->diagnostics['loginTested'] = false;
        if ($this->diagnostics['httpResponseReceived']) {
            $this->diagnostics['probeResult'] = 'HTTP-Antwort empfangen; Server erreichbar. Anmeldung nicht geprüft.';
        }
        return $this->diagnostics;
    }

    public function login(string $email, string $password): void
    {
        $this->token = '';
        if ($this->apiMode === 'openapi') {
            // The official SDK logs in on US, then chooses the region from the token suffix.
            $data = $this->api('/api/oauth/login', 'POST', Protocol::openLoginBody($email, $password), 'NA');
            $token = $data['access_token'] ?? $data['token'] ?? '';
            if (!is_string($token) || $token === '' || preg_match('/[\x00-\x20\x7f]/', $token)) {
                throw new ApiException('Anmeldung: kein gültiges Zugriffstoken.');
            }
            [$clean, $suffix] = array_pad(explode(':', $token, 2), 2, 'NA');
            if ($clean === '' || !in_array(strtoupper($suffix), ['EU', 'NA'], true)) {
                throw new ApiException('Anmeldung: unbekannte Tokenregion.');
            }
            $this->region = strtoupper($suffix);
            $this->token = $clean;
            return;
        }
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $data = $this->api('/api/oauth/login', 'POST', Protocol::loginBody($email, $password));
            $region = strtoupper((string) ($data['region'] ?? ''));
            if (!in_array($region, ['EU', 'NA'], true)) { throw new ApiException('Anmeldung: unbekannte Serverregion.'); }
            if ($region !== $this->region) { $this->region = $region; continue; }
            $token = $data['access_token'] ?? '';
            if (!is_string($token) || $token === '' || preg_match('/[\r\n]/', $token)) {
                throw new ApiException('Anmeldung: kein gültiges Zugriffstoken.');
            }
            $this->token = $token;
            return;
        }
        throw new ApiException('Anmeldung: widersprüchliche Serverregionen.');
    }

    public function discover(): array
    {
        if ($this->apiMode === 'openapi') {
            $path = '/api/v2/device/list';
            $data = $this->api($path, 'GET', []);
            $this->diagnostics['discovery'][] = ['path' => $path, 'counts' => Protocol::discoveryStats($data)];
            // OpenAPI adapters are currently implemented only for the original 720S.
            return array_values(array_filter(Protocol::devices($data), static fn(array $device): bool => $device['model'] === Protocol::MODEL));
        }
        $found = [];
        // Both routes are required: some EU accounts only return devices via the family route.
        foreach (['/api/app/index/family/room/devices', '/api/v2/user-device/device/list'] as $path) {
            for ($page = 1; $page <= 10; ++$page) {
                try {
                    $data = $this->api($path, 'GET', ['acceptLanguage' => 'en', 'method' => 'devices',
                        'pageNo' => (string) $page, 'pageSize' => '100']);
                } catch (ApiException $e) {
                    if (in_array($e->getCode(), [404, 405], true)) { break; }
                    if ($found !== [] && !in_array($e->getCode(), [401, 403, 100022], true)) {
                        $this->diagnostics['incompleteDiscovery'] = true;
                        $this->diagnostics['discoveryWarnings'][] = ['path' => $path, 'code' => $e->getCode()];
                        break;
                    }
                    throw $e;
                }
                foreach (Protocol::devices($data) as $device) {
                    $previous = $found[$device['serial']] ?? null;
                    if ($previous !== null && $previous['model'] !== $device['model']) { throw new ApiException('Widersprüchliche Modellangaben zwischen den Gerätelisten.'); }
                    // A second route may omit metadata that the family route already supplied.
                    if ($previous !== null) {
                        $device['name'] = $previous['name'];
                        foreach (['controlsConf', 'heaterConfig'] as $key) {
                            if (($device[$key] ?? []) === []) { $device[$key] = $previous[$key] ?? []; }
                        }
                    }
                    $found[$device['serial']] = $device;
                }
                $this->diagnostics['discovery'][] = ['path' => $path, 'page' => $page, 'counts' => Protocol::discoveryStats($data)];
                $pages = (int) ($data['totalPage'] ?? 1);
                if ($pages > 10) { throw new ApiException('Geräteliste hat mehr als zehn Seiten; nicht vollständig eingelesen.'); }
                if ($page >= $pages) { break; }
            }
            // Some accounts distribute devices across the two routes. Merge instead of stopping at the first heater.
        }
        return array_values($found);
    }

    public function state(string $serial): array
    {
        if ($this->apiMode === 'openapi') {
            $data = $this->api('/api/v2/device/state', 'GET', ['deviceSn' => $serial]);
            $this->diagnostics['stateKeys'] = array_keys($data);
            $unit = $this->heaterConfig['temperature_unit'] ?? null;
            $this->diagnostics['modelConfiguration'] = [
                'temperatureUnit' => in_array($unit, ['celsius', 'fahrenheit'], true) ? $unit : null,
                'hasTemperatureRange' => is_array($this->heaterConfig['temperature_range'] ?? null),
                'modeMappingCount' => is_array($this->heaterConfig['hvac_mode_relate_map'] ?? null) ? count($this->heaterConfig['hvac_mode_relate_map']) : 0,
            ];
            return Protocol::openState($data, $this->heaterConfig);
        }
        return Protocol::unwrap($this->api('/api/user-device/device/state', 'GET', ['deviceSn' => $serial]));
    }

    public function send(string $serial, array $params): string
    {
        if ($this->apiMode === 'openapi') {
            $desired = Protocol::openCommand($params, $this->heaterConfig);
            $data = $this->api('/api/v2/device/control', 'POST', ['devicesn' => $serial, 'desired' => $desired]);
            if (($data['accepted'] ?? null) === false) { throw new ApiException('Dreo hat den Schaltauftrag abgelehnt.'); }
            return 'http-accepted';
        }
        $remaining = $this->deadline - microtime(true);
        if ($remaining < 2) { throw new ApiException('Zeitbudget erschöpft; kein Schaltbefehl gesendet.'); }
        $socket = new WebSocket($this->endpoints);
        try { return $socket->send($this->region === 'EU' ? 'eu' : 'us', $this->token, $serial, $params, min(8.0, $remaining)); }
        finally { $this->diagnostics['command'] = $socket->diagnostics(); }
    }

    private function api(string $path, string $method, array $body, ?string $regionOverride = null): array
    {
        $requestIndex = count($this->diagnostics['requests'] ?? []);
        $this->diagnostics['requests'][] = ['path' => $path, 'apiMode' => $this->apiMode, 'serverRegion' => $regionOverride ?? $this->region, 'httpStatus' => null, 'apiCode' => null];
        $remaining = $this->deadline - microtime(true);
        if ($remaining < 0.5) { throw new ApiException('Zeitlimit der Dreo-Abfrage erreicht.'); }
        $connectTimeout = min(8000, (int) ($remaining * 1000));
        $timeout = min(15000, (int) ($remaining * 1000));
        $this->diagnostics['requests'][$requestIndex] += ['connectTimeoutMs' => $connectTimeout,
            'timeoutMs' => $timeout, 'addressFamily' => $this->addressFamily];
        $host = 'https://' . ($this->apiMode === 'openapi' ? 'open-api-' : 'app-api-')
            . (($regionOverride ?? $this->region) === 'EU' ? 'eu' : 'us') . '.dreo-tech.com';
        $query = ['timestamp' => (string) (int) floor(microtime(true) * 1000)];
        if ($this->apiMode === 'openapi') { $query['dreover'] = '1.0.0'; }
        if ($method === 'GET') { $query = array_merge($body, $query); }
        $url = $host . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $hostname = (string) parse_url($host, PHP_URL_HOST);
        $headers = ['ua: dreo/2.8.2', 'lang: en', 'Content-Type: application/json; charset=UTF-8',
            'User-Agent: okhttp/4.9.1'];
        if ($this->apiMode === 'openapi') { $headers = ['UA: openapi/1.0.0', 'Content-Type: application/json']; }
        if ($this->token !== '') { $headers[] = 'Authorization: Bearer ' . $this->token; }
        if ($this->http !== null) {
            [$status, $raw] = ($this->http)($url, $method, $headers, $body);
        } else {
            if (!function_exists('curl_init')) { throw new ApiException('Die PHP-Erweiterung cURL fehlt.'); }
            $ch = curl_init($url);
            if ($ch === false) { throw new ApiException('HTTP-Verbindung konnte nicht vorbereitet werden.'); }
            try {
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_CONNECTTIMEOUT_MS => $connectTimeout,
                    CURLOPT_TIMEOUT_MS => $timeout,
                    CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_FOLLOWLOCATION => false, CURLOPT_ENCODING => '']);
                $cachedAddress = $this->endpoints?->get($hostname);
                if ($cachedAddress !== null) { curl_setopt($ch, CURLOPT_RESOLVE, [$hostname . ':443:' . $cachedAddress]); }
                if ($this->addressFamily === 'ipv4') { curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); }
                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
                }
                $raw = curl_exec($ch);
                $curlCode = curl_errno($ch);
                $info = curl_getinfo($ch);
                $info['appconnect_time'] = curl_getinfo($ch, CURLINFO_APPCONNECT_TIME);
                if ($curlCode === 0 && $cachedAddress === null && curl_getinfo($ch, CURLINFO_HTTP_CONNECTCODE) === 0 && is_string($info['primary_ip'] ?? null)) { $this->endpoints?->remember($hostname, $info['primary_ip']); }
                elseif ($curlCode !== 0 && $cachedAddress !== null) { $this->endpoints?->forget($hostname); }
                $stats = Protocol::transferStats($info, $curlCode);
                $stats['cachedAddressUsed'] = $cachedAddress !== null;
                $this->diagnostics['requests'][$requestIndex] = array_merge($this->diagnostics['requests'][$requestIndex], $stats);
                $status = $stats['httpStatus'];
                if ($raw === false) {
                    throw new ApiException('Dreo-Verbindung fehlgeschlagen (cURL ' . $curlCode .
                        ($stats['totalMs'] !== null ? ', nach ' . round($stats['totalMs'] / 1000, 1) . ' s' : '') . ').');
                }
            } finally { unset($ch); }
        }
        $this->diagnostics['requests'][$requestIndex]['httpStatus'] = (int) $status;
        if ($status !== 200) { throw new ApiException('Dreo antwortet mit HTTP ' . $status . '.', (int) $status); }
        try { $response = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new ApiException('Dreo liefert keine gültige JSON-Antwort.'); }
        if (!is_array($response) || !array_key_exists('code', $response)) { throw new ApiException('Unbekanntes Dreo-Antwortformat.'); }
        $this->diagnostics['requests'][$requestIndex]['apiCode'] = is_numeric($response['code']) ? (int) $response['code'] : null;
        if ((string) $response['code'] !== '0') {
            $code = is_numeric($response['code']) ? (int) $response['code'] : -1;
            throw new ApiException($code === 100022 ? 'Dreo-Anmeldung abgelehnt: Konto oder Passwort prüfen.' : 'Dreo-API meldet Fehlercode ' . $code . '.', $code);
        }
        if ($this->apiMode === 'openapi' && $path === '/api/v2/device/control' && !isset($response['data'])) { return []; }
        if (!isset($response['data']) || !is_array($response['data'])) { throw new ApiException('Dreo-Antwort enthält keine auswertbaren Daten.'); }
        return $response['data'];
    }
}
