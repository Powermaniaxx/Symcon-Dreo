<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../DreoHeater/module.php';

use SymconDreo\EndpointCache;
$checks = 0;
function verifyCache(bool $ok, string $name): void { global $checks; if (!$ok) { throw new RuntimeException($name); } ++$checks; }
$entries = []; $lookups = 0;
$cache = new EndpointCache(static function () use (&$entries): array { return $entries; },
    static function ($host, $entry) use (&$entries): void { if ($entry === null) { unset($entries[$host]); } else { $entries[$host] = $entry; } },
    static function ($host) use (&$lookups): array { ++$lookups; return ['bad-address', '203.0.113.4']; });
$host = 'wsb-eu.dreo-tech.com';
verifyCache($cache->get($host) === null, 'cold lookup remains normal DNS fallback');
$cache->warm($host);
verifyCache($lookups === 1 && $cache->get($host) === '203.0.113.4', 'background resolver remembers a valid address');
$cache->warm($host);
verifyCache($lookups === 1, 'fresh cached address avoids repeated DNS');
$entries[$host]['expires'] = microtime(true) + 10; $cache->warm($host);
verifyCache($lookups === 2, 'address nearing expiry is refreshed in background');
$entries[$host]['expires'] = microtime(true) - 1;
verifyCache($cache->get($host) === null, 'expired cache entry cannot pin an old address');
$cache->warm('unrelated.invalid'); $cache->remember('unrelated.invalid', '203.0.113.7');
verifyCache($lookups === 2 && !isset($entries['unrelated.invalid']), 'cache is restricted to fixed Dreo endpoint names');
$cache->remember($host, 'bad-address'); verifyCache($cache->get($host) === null, 'malformed address is not cached');
$cache->remember($host, '999.0.0.1'); verifyCache($cache->get($host) === null, 'out-of-range IPv4 address is not cached');
$cache->warm($host); $cache->forget($host);
verifyCache($cache->get($host) === null && !isset($entries[$host]), 'failed cached connection can invalidate address');

class WarmHeater extends DreoHeater
{
    public int $lookups = 0;
    protected function resolveEndpoint(string $host): array { ++$this->lookups; return ['203.0.113.4']; }
}
$h = new WarmHeater(); $h->Create();
$h->properties = array_merge($h->properties, ['Open' => true, 'Email' => 'fixture@example.invalid', 'Password' => 'fixture-password', 'Serial' => 'fixture-sn']);
$h->ApplyChanges();
verifyCache($h->lookups === 0 && $h->timers['EndpointWarmup'] === 1000, 'apply schedules DNS work without doing it in the UI call');
$GLOBALS['heldLocks']['SymconDreo.' . $h->InstanceID] = true;
$h->RequestAction('WarmEndpoints', true);
unset($GLOBALS['heldLocks']['SymconDreo.' . $h->InstanceID]);
verifyCache($h->lookups === 2 && $h->timers['EndpointWarmup'] === 30000, 'both hosts warm independently of the device operation lock');
$h->RequestAction('WarmEndpoints', true);
verifyCache($h->lookups === 2, 'module warm-up reuses valid addresses');
verifyCache(!str_contains($h->GetDiagnostics(), '203.0.113.4'), 'private endpoint cache is not exposed in diagnostics');
$h->properties['Open'] = false; $h->ApplyChanges(); $h->RequestAction('WarmEndpoints', true);
verifyCache($h->lookups === 2 && $h->timers['EndpointWarmup'] === 0, 'disabled instance does no DNS warm-up');
echo "PASS endpoint cache: $checks checks (no public DNS used).\n";
