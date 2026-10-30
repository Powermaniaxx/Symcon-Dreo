<?php
declare(strict_types=1);
namespace SymconDreo;

/** Short-lived DNS results; TLS always verifies the original Dreo hostname. */
final class EndpointCache
{
    public function __construct(private \Closure $read, private \Closure $write, private ?\Closure $resolver = null) {}

    private static function allowed(string $host): bool
    {
        return (bool) preg_match('/^(?:app-api|open-api|wsb)-(?:eu|us)\.dreo-tech\.com$/D', $host);
    }

    private static function ipv4(string $address): bool
    {
        $octet = '(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])';
        return (bool) preg_match('/^(?:' . $octet . '\.){3}' . $octet . '$/D', $address);
    }

    public function get(string $host, float $minimumRemaining = 0): ?string
    {
        if (!self::allowed($host)) { return null; }
        $entry = ($this->read)()[$host] ?? [];
        if (($entry['expires'] ?? 0) <= microtime(true) + $minimumRemaining
            || !is_string($entry['address'] ?? null)
            || !self::ipv4($entry['address'])) { return null; }
        return $entry['address'];
    }

    public function remember(string $host, string $address): void
    {
        if (!self::allowed($host) || !self::ipv4($address)) { return; }
        ($this->write)($host, ['address' => $address, 'expires' => microtime(true) + 120]);
    }

    public function forget(string $host): void { if (self::allowed($host)) { ($this->write)($host, null); } }

    public function warm(string $host): void
    {
        if (!self::allowed($host) || $this->get($host, 45) !== null) { return; }
        $addresses = $this->resolver !== null ? ($this->resolver)($host)
            : (function_exists('gethostbynamel') ? @gethostbynamel($host) : []);
        foreach (is_array($addresses) ? $addresses : [] as $address) {
            if (is_string($address) && self::ipv4($address)) {
                $this->remember($host, $address); return;
            }
        }
    }
}
