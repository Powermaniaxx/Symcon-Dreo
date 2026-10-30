<?php
declare(strict_types=1);
require __DIR__ . '/../libs/Client.php';
if (!function_exists('pcntl_fork') || !function_exists('openssl_pkey_new')) { throw new RuntimeException('pcntl and OpenSSL required.'); }
$dir = sys_get_temp_dir() . '/dreo-tls-' . bin2hex(random_bytes(8)); mkdir($dir, 0700);
$key = openssl_pkey_new(['private_key_bits' => 2048]);
$csr = openssl_csr_new(['commonName' => 'wsb-eu.dreo-tech.com'], $key, ['digest_alg' => 'sha256']);
$certificate = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
openssl_x509_export($certificate, $pem); openssl_pkey_export($key, $private);
file_put_contents($dir . '/cert.pem', $pem); file_put_contents($dir . '/server.pem', $pem . $private);
$context = stream_context_create(['ssl' => ['local_cert' => $dir . '/server.pem', 'verify_peer' => false]]);
$server = stream_socket_server('tls://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) { throw new RuntimeException('TLS fixture failed to listen.'); }
$port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
$pid = pcntl_fork();
if ($pid === 0) {
    // One matching connection, then two attempts each for wrong hostname and untrusted certificate.
    for ($i = 0; $i < 5; ++$i) { $socket = @stream_socket_accept($server, 5); if (is_resource($socket)) { fclose($socket); } }
    fclose($server); exit(0);
}
if ($pid === -1) { throw new RuntimeException('TLS fixture fork failed.'); }
fclose($server);
class TLSFixture extends \SymconDreo\WebSocket
{
    public array $calls = [];
    public function __construct(\SymconDreo\EndpointCache $cache, private int $port, private ?string $ca) { parent::__construct($cache); }
    protected function dial(string $target, float $timeout, array $ssl): mixed {
        $this->calls[] = ['target' => $target, 'ssl' => $ssl];
        if ($this->ca !== null) { $ssl['cafile'] = $this->ca; }
        return parent::dial('tls://127.0.0.1:' . $this->port, $timeout, $ssl);
    }
    public function open(string $host): mixed { return $this->connect($host, 2); }
}
$entries = [];
$cache = new \SymconDreo\EndpointCache(static function () use (&$entries) { return $entries; },
    static function ($host, $entry) use (&$entries) { if ($entry === null) { unset($entries[$host]); } else { $entries[$host] = $entry; } });
try {
    $cache->remember('wsb-eu.dreo-tech.com', '127.0.0.1');
    $valid = new TLSFixture($cache, $port, $dir . '/cert.pem');
    $socket = $valid->open('wsb-eu.dreo-tech.com'); fclose($socket);
    $call = $valid->calls[0];
    if ($call['target'] !== 'tls://127.0.0.1:443' || !$call['ssl']['verify_peer'] || !$call['ssl']['verify_peer_name']
        || $call['ssl']['peer_name'] !== 'wsb-eu.dreo-tech.com' || !$call['ssl']['SNI_enabled']) { throw new RuntimeException('Cached TLS lost hostname verification.'); }
    foreach ([['wsb-us.dreo-tech.com', $dir . '/cert.pem'], ['wsb-eu.dreo-tech.com', null]] as [$host, $ca]) {
        $cache->remember($host, '127.0.0.1'); $client = new TLSFixture($cache, $port, $ca); $failed = false;
        try { $socket = $client->open($host); fclose($socket); } catch (RuntimeException $e) { $failed = true; }
        if (!$failed || count($client->calls) !== 2 || $cache->get($host) !== null) { throw new RuntimeException('Bad TLS identity or trust accepted, or invalid address was not cleared.'); }
    }
    echo "PASS native cached TLS: matching host accepted; wrong host and untrusted certificate rejected.\n";
} finally {
    pcntl_waitpid($pid, $status);
    unlink($dir . '/cert.pem'); unlink($dir . '/server.pem'); rmdir($dir);
}
