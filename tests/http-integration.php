<?php
declare(strict_types=1);

namespace SymconDreo {
    // Only this test redirects the fixed Dreo host to a local HTTP fixture.
    function curl_init(?string $url = null): \CurlHandle|false
    {
        if (!str_starts_with($url ?? '', 'https://app-api-eu.dreo-tech.com/')) {
            throw new \RuntimeException('Unexpected production endpoint in fixture.');
        }
        return \curl_init($GLOBALS['fixture_http_url']);
    }
}

namespace {
    require __DIR__ . '/../libs/Client.php';
    if (!function_exists('curl_init') || !function_exists('pcntl_fork')) {
        fwrite(STDERR, "cURL and pcntl are required for HTTP integration tests.\n"); exit(1);
    }
    function fixture(string $scenario, \Closure $test): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($server === false) { throw new \RuntimeException('Could not start local HTTP fixture.'); }
        $GLOBALS['fixture_http_url'] = 'http://' . stream_socket_get_name($server, false) . '/probe';
        $pid = pcntl_fork();
        if ($pid === -1) { throw new \RuntimeException('Could not fork local HTTP fixture.'); }
        if ($pid === 0) {
            $socket = stream_socket_accept($server, 3);
            if ($socket === false) { exit(2); }
            stream_set_timeout($socket, 2); $request = '';
            while (!str_contains($request, "\r\n\r\n")) {
                $part = fread($socket, 4096); if ($part === false || $part === '') { exit(3); } $request .= $part;
            }
            if ($scenario === 'timeout') { usleep(1800000); }
            else {
                if (!str_starts_with($request, 'GET ') || stripos($request, 'Authorization:') !== false) { exit(4); }
                fwrite($socket, "HTTP/1.1 405 Method Not Allowed\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
            }
            fclose($socket); fclose($server); exit(0);
        }
        fclose($server);
        try { $test(); }
        finally { pcntl_waitpid($pid, $status); }
        if (pcntl_wexitstatus($status) !== 0) { throw new \RuntimeException('HTTP fixture failed.'); }
        echo 'PASS native cURL: ' . $scenario . "\n";
    }
    fixture('timeout', static function (): void {
        $client = new \SymconDreo\Client('EU', '', null, 1.0);
        try { $client->login('fixture@example.invalid', 'fixture-password'); throw new \LogicException('Expected timeout.'); }
        catch (\SymconDreo\ApiException $e) {
            $request = $client->diagnostics()['requests'][0];
            if ($request['curlCode'] !== 28 || $request['httpStatus'] !== 0 || $request['totalMs'] < 500
                || $request['totalMs'] > 1800 || !str_contains($e->getMessage(), 'cURL 28')) {
                throw new \RuntimeException('Native cURL timeout/timings were not preserved.');
            }
            if (str_contains(json_encode($client->diagnostics()), 'fixture-password')) { throw new \RuntimeException('Credential leak.'); }
        }
    });
    fixture('anonymous IPv4 probe', static function (): void {
        $result = (new \SymconDreo\Client('EU', '', null, 2.0, 'ipv4'))->probe();
        $request = $result['requests'][0];
        if ($request['curlCode'] !== 0 || $request['httpStatus'] !== 405 || $request['addressFamily'] !== 'ipv4'
            || $request['tlsMs'] !== 0.0) {
            throw new \RuntimeException('Native anonymous IPv4 probe failed.');
        }
    });
}
