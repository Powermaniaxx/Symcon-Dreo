<?php
declare(strict_types=1);

namespace SymconDreo {
    function curl_init(?string $url = null): \CurlHandle|false
    {
        $GLOBALS['original_urls'][] = $url;
        $host = parse_url($url, PHP_URL_HOST);
        if (!in_array($host, ['open-api-us.dreo-tech.com', 'open-api-eu.dreo-tech.com'], true)) {
            throw new \RuntimeException('Unexpected production host.');
        }
        // Local HTTP fixture verifies real cURL requests. It does not test public DNS or TLS.
        return \curl_init($GLOBALS['fixture_base'] . parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY));
    }
}
namespace {
    require __DIR__ . '/../libs/Client.php';
    if (!function_exists('curl_init') || !function_exists('pcntl_fork')) { throw new \RuntimeException('cURL and pcntl required.'); }
    $contract = json_decode(file_get_contents(__DIR__ . '/fixtures/openapi-sdk.json'), true, 64, JSON_THROW_ON_ERROR)['requests'];
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($server === false) { throw new \RuntimeException('Fixture listen failed.'); }
    $GLOBALS['fixture_base'] = 'http://' . stream_socket_get_name($server, false);
    $pid = pcntl_fork();
    if ($pid === -1) { throw new \RuntimeException('Fixture fork failed.'); }
    if ($pid === 0) {
        try {
            foreach ($contract as $expected) {
                $socket = stream_socket_accept($server, 4);
                if ($socket === false) { throw new \RuntimeException('Expected connection not received.'); }
                stream_set_timeout($socket, 3); $raw = '';
                while (!str_contains($raw, "\r\n\r\n")) {
                    $chunk = fread($socket, 4096);
                    if ($chunk === '' || $chunk === false) { throw new \RuntimeException('Incomplete headers.'); }
                    $raw .= $chunk;
                }
                [$head, $body] = explode("\r\n\r\n", $raw, 2);
                $lines = explode("\r\n", $head); $requestLine = array_shift($lines); $headers = [];
                foreach ($lines as $line) { [$key, $value] = explode(':', $line, 2); $headers[strtolower(trim($key))] = trim($value); }
                $length = (int) ($headers['content-length'] ?? 0);
                while (strlen($body) < $length) {
                    $chunk = fread($socket, $length - strlen($body));
                    if ($chunk === '' || $chunk === false) { throw new \RuntimeException('Incomplete body.'); }
                    $body .= $chunk;
                }
                [$method, $target] = explode(' ', $requestLine, 3);
                parse_str(parse_url($target, PHP_URL_QUERY), $query);
                if (!isset($query['timestamp']) || !preg_match('/^[0-9]+$/', (string) $query['timestamp'])) { throw new \RuntimeException('Missing timestamp.'); }
                unset($query['timestamp']);
                ksort($query); $expectedQuery = $expected['params']; ksort($expectedQuery);
                if ($method !== $expected['method'] || parse_url($target, PHP_URL_PATH) !== parse_url($expected['url'], PHP_URL_PATH)
                    || $query !== $expectedQuery) { throw new \RuntimeException('Wire request does not match official SDK method/path/query.'); }
                foreach ($expected['headers'] as $key => $value) {
                    if (($headers[strtolower($key)] ?? null) !== $value) { throw new \RuntimeException('Header differs from official SDK.'); }
                }
                if (!isset($expected['headers']['Authorization']) && isset($headers['authorization'])) { throw new \RuntimeException('Unexpected login bearer.'); }
                $json = $body === '' ? null : json_decode($body, true, 64, JSON_THROW_ON_ERROR);
                if ($json !== $expected['json']) { throw new \RuntimeException('Body differs from official SDK.'); }
                $path = parse_url($target, PHP_URL_PATH);
                $data = match ($path) {
                    '/api/oauth/login' => ['access_token' => 'fixture-token:EU'],
                    '/api/v2/device/list' => [['model' => 'DR-HSH040S', 'deviceSn' => 'fixture-sn']],
                    '/api/v2/device/state' => ['power_switch' => false, 'temperature' => 70, 'ecolevel' => 70, 'htalevel' => 1, 'mode' => 'eco'],
                    default => ['accepted' => true],
                };
                $reply = json_encode(['code' => 0, 'data' => $data], JSON_THROW_ON_ERROR);
                fwrite($socket, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($reply) . "\r\nConnection: close\r\n\r\n" . $reply);
                fclose($socket);
            }
            fclose($server); exit(0);
        } catch (\Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(2); }
    }
    fclose($server); $GLOBALS['original_urls'] = [];
    try {
        $client = new \SymconDreo\Client('EU', '', null, 22, 'auto', 'openapi', ['temperature_unit' => 'fahrenheit']);
        $client->login('fixture@example.invalid', 'fixture-password');
        if (count($client->discover()) !== 1 || $client->state('fixture-sn')['poweron'] !== false) { throw new \RuntimeException('Fixture device/state mismatch.'); }
        $client->send('fixture-sn', ['poweron' => false]);
        foreach ($contract as $i => $request) {
            if (explode('?', $GLOBALS['original_urls'][$i])[0] !== $request['url']) { throw new \RuntimeException('Production URL differs from SDK.'); }
        }
    } finally { pcntl_waitpid($pid, $status); }
    if (pcntl_wexitstatus($status) !== 0) { throw new \RuntimeException('Official wire fixture failed.'); }
    echo "PASS native OpenAPI: four cURL requests match recorded official Python SDK contract.\n";
}
