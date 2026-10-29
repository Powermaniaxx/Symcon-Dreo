<?php
declare(strict_types=1);
require __DIR__ . '/../libs/Client.php';

if (!function_exists('pcntl_fork')) { fwrite(STDERR, "pcntl is required for the local WebSocket integration tests.\n"); exit(1); }

final class LoopbackSocket extends \SymconDreo\WebSocket
{
    public function __construct(private string $address) {}
    protected function connect(string $host, float $timeout): mixed
    {
        $socket = stream_socket_client('tcp://' . $this->address, $errno, $error, $timeout);
        if ($socket === false) { throw new RuntimeException('Loopback test connection failed.'); }
        return $socket;
    }
}
function exact($s, int $count): string {
    $r = ''; while (strlen($r) < $count) { $p = fread($s, $count - strlen($r)); if ($p === false || $p === '') { throw new RuntimeException('Test stream ended.'); } $r .= $p; } return $r;
}
function clientFrame($s): string {
    $h = exact($s, 2); $len = ord($h[1]) & 127;
    if ((ord($h[1]) & 128) === 0) { throw new RuntimeException('Client frame not masked.'); }
    if ($len === 126) { $len = unpack('n', exact($s, 2))[1]; }
    if ($len === 127) { $len = unpack('N', substr(exact($s, 8), 4))[1]; }
    $mask = exact($s, 4); $data = exact($s, $len); $raw = '';
    for ($i = 0; $i < $len; ++$i) { $raw .= $data[$i] ^ $mask[$i % 4]; } return $raw;
}
function serverFrame(string $payload, int $op = 1, bool $fin = true): string {
    $len = strlen($payload); return chr(($fin ? 128 : 0) | $op) . ($len < 126 ? chr($len) : chr(126) . pack('n', $len)) . $payload;
}
function scenario(string $scenario, bool $expectSuccess): void
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($server === false) { throw new RuntimeException('Could not start loopback server.'); }
    $address = stream_socket_get_name($server, false);
    $pid = pcntl_fork();
    if ($pid === -1) { throw new RuntimeException('Fork failed.'); }
    if ($pid === 0) {
        try {
            $s = stream_socket_accept($server, 2); if ($s === false) { exit(2); }
            stream_set_timeout($s, 2); $headers = '';
            while (!str_ends_with($headers, "\r\n\r\n")) { $headers .= exact($s, 1); }
            preg_match('/Sec-WebSocket-Key:\s*(\S+)/i', $headers, $m);
            $accept = base64_encode(sha1(($m[1] ?? '') . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            if ($scenario === 'bad-handshake') { $accept = 'wrong'; }
            fwrite($s, "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $accept\r\n\r\n");
            if ($scenario !== 'bad-handshake') {
                if (clientFrame($s) !== '2') { exit(3); }
                $command = json_decode(clientFrame($s), true);
                if (($command['method'] ?? '') !== 'control' || ($command['params'] ?? []) !== ['poweron' => true]) { exit(4); }
                $reply = ['devicesn' => $scenario === 'wrong-device' ? 'other-device' : 'fixture-sn', 'method' => 'control-reply', 'reported' => ['poweron' => true]];
                if ($scenario === 'reject') { $reply['reported'] = ['error_code' => 500003, 'error_msg' => 'do-not-leak']; }
                if ($scenario === 'close') { fwrite($s, serverFrame('', 8)); }
                elseif ($scenario === 'fragmented') {
                    $json = json_encode($reply); $n = (int) floor(strlen($json) / 2);
                    fwrite($s, serverFrame(substr($json, 0, $n), 1, false) . serverFrame('ping', 9) . serverFrame(substr($json, $n), 0));
                    clientFrame($s); // read the masked pong, which may arrive after the text continuation
                } else { fwrite($s, serverFrame(json_encode($reply))); }
                if ($scenario === 'wrong-device') { usleep(400000); }
            }
            fclose($s); fclose($server); exit(0);
        } catch (Throwable $e) { fwrite(STDERR, 'Fixture server: ' . $e->getMessage() . "\n"); exit(5); }
    }
    fclose($server); $success = false;
    try { $result = (new LoopbackSocket($address))->send('eu', 'fixture-token', 'fixture-sn', ['poweron' => true], 0.3); $success = $result === 'control-reply'; }
    catch (Throwable $e) { if (str_contains($e->getMessage(), 'do-not-leak')) { throw new RuntimeException('Raw server message leaked.'); } }
    finally { pcntl_waitpid($pid, $status); }
    if ($success !== $expectSuccess || pcntl_wexitstatus($status) !== 0) { throw new RuntimeException('FAIL loopback scenario: ' . $scenario); }
    echo 'PASS loopback: ' . $scenario . "\n";
}
foreach (['confirm' => true, 'fragmented' => true, 'reject' => false, 'wrong-device' => false, 'close' => false, 'bad-handshake' => false] as $scenario => $success) { scenario($scenario, $success); }
