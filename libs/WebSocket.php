<?php
declare(strict_types=1);

namespace SymconDreo;

/** Short-lived TLS WebSocket. No daemon, external package or disabled certificate verification. */
class WebSocket
{
    public function send(string $region, string $token, string $serial, array $params, float $timeout): string
    {
        if (!in_array($region, ['eu', 'us'], true) || $token === '') { throw new \RuntimeException('WebSocket-Anmeldung fehlt.'); }
        $host = 'wsb-' . $region . '.dreo-tech.com';
        $deadline = microtime(true) + $timeout;
        $socket = $this->connect($host, min(4, $timeout));
        try {
            $key = base64_encode(random_bytes(16));
            $path = '/websocket?' . http_build_query(['accessToken' => $token, 'timestamp' => (string) (int) floor(microtime(true) * 1000)], '', '&', PHP_QUERY_RFC3986);
            self::writeAll($socket, "GET $path HTTP/1.1\r\nHost: $host\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n", $deadline);
            $headers = '';
            while (!str_ends_with($headers, "\r\n\r\n")) {
                if (strlen($headers) > 16384) { throw new \RuntimeException('Ungültige WebSocket-Kopfzeilen.'); }
                $headers .= self::readExact($socket, 1, $deadline);
            }
            if (preg_match('/^HTTP\/1\.[01] (401|403)\b/', $headers)) { throw new ApiException('WebSocket-Anmeldung abgelaufen.', 401); }
            $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            if (!preg_match('/^HTTP\/1\.[01] 101\b/', $headers)
                || !preg_match('/^Sec-WebSocket-Accept:\s*' . preg_quote($accept, '/') . '\s*$/mi', $headers)) {
                throw new \RuntimeException('Dreo-WebSocket-Handshake abgelehnt.');
            }
            self::writeAll($socket, self::frame('2'), $deadline);
            self::writeAll($socket, self::frame(json_encode(['devicesn' => $serial, 'method' => 'control',
                'params' => $params, 'timestamp' => (string) (int) floor(microtime(true) * 1000)], JSON_THROW_ON_ERROR)), $deadline);
            $fragment = '';
            while (microtime(true) < $deadline) {
                [$opcode, $payload, $final] = self::readFrame($socket, $deadline);
                if ($opcode === 9) { self::writeAll($socket, self::frame($payload, 10), $deadline); continue; }
                if ($opcode === 10) { continue; }
                if ($opcode === 8) { throw new \RuntimeException('Dreo hat die Verbindung vor der Bestätigung geschlossen.'); }
                if ($opcode === 1) { $fragment = $payload; }
                elseif ($opcode === 0) { $fragment .= $payload; }
                else { continue; }
                if (strlen($fragment) > 1048576) { throw new \RuntimeException('WebSocket-Nachricht zu groß.'); }
                if (!$final) { continue; }
                $message = json_decode($fragment, true);
                $fragment = '';
                if (!is_array($message) || ($message['devicesn'] ?? '') !== $serial
                    || !in_array($message['method'] ?? '', ['control-reply', 'control-report'], true)) { continue; }
                $reported = $message['reported'] ?? [];
                if (is_array($reported) && isset($reported['error_code'])) {
                    throw new \RuntimeException('Dreo lehnt den Schaltbefehl ab (Code ' . (int) $reported['error_code'] . ').');
                }
                // This is an acceptance, not a guarantee the heater executed the command.
                return (string) $message['method'];
            }
            throw new \RuntimeException('Keine Dreo-Bestätigung; Ausführung unklar. Status aktualisieren.');
        } finally { fclose($socket); }
    }

    protected function connect(string $host, float $timeout): mixed
    {
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true,
            'peer_name' => $host, 'SNI_enabled' => true]]);
        $socket = @stream_socket_client('tls://' . $host . ':443', $errno, $error, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) { throw new \RuntimeException('Dreo-WebSocket nicht erreichbar.'); }
        return $socket;
    }

    public static function frame(string $payload, int $opcode = 1): string
    {
        $length = strlen($payload);
        $header = chr(0x80 | $opcode);
        if ($length < 126) { $header .= chr(0x80 | $length); }
        elseif ($length <= 65535) { $header .= chr(0x80 | 126) . pack('n', $length); }
        else { $header .= chr(0x80 | 127) . pack('NN', 0, $length); }
        $mask = random_bytes(4);
        $masked = '';
        for ($i = 0; $i < $length; ++$i) { $masked .= $payload[$i] ^ $mask[$i % 4]; }
        return $header . $mask . $masked;
    }

    public static function readFrame($socket, float $deadline): array
    {
        $header = self::readExact($socket, 2, $deadline);
        $a = ord($header[0]); $b = ord($header[1]);
        if (($a & 0x70) !== 0 || ($b & 0x80) !== 0) { throw new \RuntimeException('Ungültiger WebSocket-Rahmen.'); }
        $length = $b & 127;
        if ($length === 126) { $length = unpack('n', self::readExact($socket, 2, $deadline))[1]; }
        elseif ($length === 127) {
            $parts = unpack('Nhi/Nlo', self::readExact($socket, 8, $deadline));
            if ($parts['hi'] !== 0) { throw new \RuntimeException('WebSocket-Nachricht zu groß.'); }
            $length = $parts['lo'];
        }
        if ($length > 1048576) { throw new \RuntimeException('WebSocket-Nachricht zu groß.'); }
        return [$a & 15, self::readExact($socket, $length, $deadline), ($a & 128) !== 0];
    }

    private static function readExact($socket, int $length, float $deadline): string
    {
        $result = '';
        while (strlen($result) < $length) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) { throw new \RuntimeException('Dreo-Zeitlimit erreicht; Ausführung gegebenenfalls unklar.'); }
            stream_set_timeout($socket, (int) $remaining, max(1, (int) (($remaining - floor($remaining)) * 1000000)));
            $part = fread($socket, $length - strlen($result));
            if ($part === false || $part === '') { throw new \RuntimeException('Dreo-Verbindung unterbrochen oder Zeitlimit erreicht.'); }
            $result .= $part;
        }
        return $result;
    }

    private static function writeAll($socket, string $data, float $deadline): void
    {
        while ($data !== '') {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) { throw new \RuntimeException('Dreo-Zeitlimit beim Senden erreicht.'); }
            stream_set_timeout($socket, (int) $remaining, max(1, (int) (($remaining - floor($remaining)) * 1000000)));
            $written = fwrite($socket, $data);
            if ($written === false || $written === 0) { throw new \RuntimeException('Dreo-Verbindung beim Senden unterbrochen.'); }
            $data = substr($data, $written);
        }
    }
}
