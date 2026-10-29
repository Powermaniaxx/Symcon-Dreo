<?php
declare(strict_types=1);

namespace SymconDreo;

require_once __DIR__ . '/Protocol.php';
require_once __DIR__ . '/WebSocket.php';

class ApiException extends \RuntimeException {}

class Client
{
    private string $region;
    private string $token;
    private float $deadline;
    private ?\Closure $http;
    private array $diagnostics = [];
    private string $addressFamily;

    public function __construct(string $region, string $token = '', ?\Closure $http = null, float $budget = 22.0, string $addressFamily = 'auto')
    {
        if (!in_array($region, ['EU', 'NA'], true)) { throw new \InvalidArgumentException('Ungültige Region.'); }
        $this->region = $region;
        $this->token = $token;
        $this->http = $http;
        if (!in_array($addressFamily, ['auto', 'ipv4'], true)) { throw new \InvalidArgumentException('Ungültiger Verbindungsmodus.'); }
        $this->addressFamily = $addressFamily;
        $this->deadline = microtime(true) + max(0, min(22.0, $budget));
    }

    public function session(): array { return ['region' => $this->region, 'token' => $this->token]; }
    public function diagnostics(): array { return $this->diagnostics; }

    public function probe(): array
    {
        if ($this->token !== '') { throw new \LogicException('Verbindungstest darf kein Zugriffstoken verwenden.'); }
        try { $this->api('/api/oauth/login', 'GET', []); }
        catch (ApiException $e) { $this->diagnostics['probeResult'] = $e->getMessage(); }
        // HTTP 4xx/5xx can still prove connectivity. This is not a credential/login test.
        return $this->diagnostics;
    }

    public function login(string $email, string $password): void
    {
        $this->token = '';
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
        $found = [];
        // Both routes are required: some EU accounts only return devices via the family route.
        foreach (['/api/app/index/family/room/devices', '/api/v2/user-device/device/list'] as $path) {
            for ($page = 1; $page <= 10; ++$page) {
                try {
                    $data = $this->api($path, 'GET', ['acceptLanguage' => 'en', 'method' => 'devices',
                        'pageNo' => (string) $page, 'pageSize' => '100']);
                } catch (ApiException $e) {
                    if (in_array($e->getCode(), [404, 405], true)) { break; }
                    throw $e;
                }
                foreach (Protocol::devices($data) as $device) { $found[$device['serial']] = $device; }
                $this->diagnostics['discovery'][] = ['path' => $path, 'page' => $page, 'counts' => Protocol::discoveryStats($data)];
                $pages = (int) ($data['totalPage'] ?? 1);
                if ($pages > 10) { throw new ApiException('Geräteliste hat mehr als zehn Seiten; nicht vollständig eingelesen.'); }
                if ($page >= $pages) { break; }
            }
            if ($found !== []) { return array_values($found); }
        }
        return [];
    }

    public function state(string $serial): array
    {
        return Protocol::unwrap($this->api('/api/user-device/device/state', 'GET', ['deviceSn' => $serial]));
    }

    public function send(string $serial, array $params): string
    {
        $remaining = $this->deadline - microtime(true);
        if ($remaining < 2) { throw new ApiException('Zeitbudget erschöpft; kein Schaltbefehl gesendet.'); }
        return (new WebSocket())->send($this->region === 'EU' ? 'eu' : 'us', $this->token, $serial, $params, min(8.0, $remaining));
    }

    private function api(string $path, string $method, array $body): array
    {
        $requestIndex = count($this->diagnostics['requests'] ?? []);
        $this->diagnostics['requests'][] = ['path' => $path, 'httpStatus' => null, 'apiCode' => null];
        $remaining = $this->deadline - microtime(true);
        if ($remaining < 0.5) { throw new ApiException('Zeitlimit der Dreo-Abfrage erreicht.'); }
        $connectTimeout = min(8000, (int) ($remaining * 1000));
        $timeout = min(15000, (int) ($remaining * 1000));
        $this->diagnostics['requests'][$requestIndex] += ['connectTimeoutMs' => $connectTimeout,
            'timeoutMs' => $timeout, 'addressFamily' => $this->addressFamily];
        $host = 'https://app-api-' . ($this->region === 'EU' ? 'eu' : 'us') . '.dreo-tech.com';
        $query = ['timestamp' => (string) (int) floor(microtime(true) * 1000)];
        if ($method === 'GET') { $query = array_merge($body, $query); }
        $url = $host . $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $headers = ['ua: dreo/2.8.2', 'lang: en', 'Content-Type: application/json; charset=UTF-8',
            'User-Agent: okhttp/4.9.1'];
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
                if ($this->addressFamily === 'ipv4') { curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); }
                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
                }
                $raw = curl_exec($ch);
                $curlCode = curl_errno($ch);
                $info = curl_getinfo($ch);
                $info['appconnect_time'] = curl_getinfo($ch, CURLINFO_APPCONNECT_TIME);
                $stats = Protocol::transferStats($info, $curlCode);
                $this->diagnostics['requests'][$requestIndex] = array_merge($this->diagnostics['requests'][$requestIndex], $stats);
                $status = $stats['httpStatus'];
                if ($raw === false) {
                    throw new ApiException('Dreo-Verbindung fehlgeschlagen (cURL ' . $curlCode .
                        ($stats['totalMs'] !== null ? ', nach ' . round($stats['totalMs'] / 1000, 1) . ' s' : '') . ').');
                }
            } finally { curl_close($ch); }
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
        if (!isset($response['data']) || !is_array($response['data'])) { throw new ApiException('Dreo-Antwort enthält keine auswertbaren Daten.'); }
        return $response['data'];
    }
}
