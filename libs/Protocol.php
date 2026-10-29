<?php
declare(strict_types=1);

namespace SymconDreo;

final class Protocol
{
    public const MODEL = 'DR-HSH040S';
    public const MODES = [0 => 'coolair', 1 => 'hotair', 2 => 'eco'];

    public static function loginBody(string $email, string $password): array
    {
        return [
            'acceptLanguage' => 'en', 'client_id' => '7de37c362ee54dcf9c4561812309347a',
            'client_secret' => '32dfa0764f25451d99f94e1693498791', 'email' => $email,
            'encrypt' => 'ciphertext', 'grant_type' => 'email-password',
            'himei' => 'faede31549d649f58864093158787ec9', 'password' => md5($password), 'scope' => 'all'
        ];
    }

    public static function devices(array $data): array
    {
        $found = [];
        $walk = static function (array $node) use (&$walk, &$found): void {
            $sn = $node['sn'] ?? $node['deviceSn'] ?? null;
            if (($node['model'] ?? '') === self::MODEL && is_string($sn) && $sn !== '') {
                $found[$sn] = ['serial' => $sn, 'model' => self::MODEL,
                    'name' => (string) ($node['deviceName'] ?? $node['name'] ?? 'Dreo 720S')];
            }
            foreach ($node as $child) {
                if (is_array($child)) { $walk($child); }
            }
        };
        $walk($data);
        return array_values($found);
    }

    public static function discoveryStats(array $data): array
    {
        $stats = ['recordsWithModel' => 0, 'supportedRecords' => 0, 'supportedMissingSerial' => 0,
            'supportedUsable' => 0, 'topLevelListCount' => isset($data['list']) && is_array($data['list']) ? count($data['list']) : null];
        $walk = static function (array $node) use (&$walk, &$stats): void {
            if (isset($node['model']) && is_string($node['model'])) {
                ++$stats['recordsWithModel'];
                if ($node['model'] === self::MODEL) {
                    ++$stats['supportedRecords'];
                    $sn = $node['sn'] ?? $node['deviceSn'] ?? null;
                    ++$stats[is_string($sn) && $sn !== '' ? 'supportedUsable' : 'supportedMissingSerial'];
                }
            }
            foreach ($node as $child) { if (is_array($child)) { $walk($child); } }
        };
        $walk($data);
        return $stats;
    }

    public static function unwrap(array $data): array
    {
        if (!isset($data['mixed']) || !is_array($data['mixed'])) {
            throw new \RuntimeException('Gerätestatus enthält kein mixed-Objekt.');
        }
        $state = [];
        foreach ($data['mixed'] as $key => $value) {
            $state[$key] = is_array($value) && array_key_exists('state', $value) ? $value['state'] : $value;
        }
        return $state;
    }

    public static function celsius(float $fahrenheit): float
    {
        return round(($fahrenheit - 32.0) * 5.0 / 9.0, 1);
    }

    public static function targetCelsius(float $fahrenheit): float
    {
        // Inverse of the app's ceil(C * 9/5 + 32), preferring whole degrees.
        $low = ($fahrenheit - 33) * 5 / 9;
        $high = ($fahrenheit - 32) * 5 / 9;
        $c = floor($high + 1e-9);
        return $c > $low + 1e-9 ? $c : floor($high * 2 + 1e-9) / 2;
    }

    public static function command(string $ident, mixed $value): array
    {
        switch ($ident) {
            case 'Power':
                if (!is_bool($value)) { throw new \InvalidArgumentException('Ein/Aus benötigt einen booleschen Wert.'); }
                return ['poweron' => $value];
            case 'Mode':
                if (!is_int($value) || !array_key_exists($value, self::MODES)) {
                    throw new \InvalidArgumentException('Ungültiger Heizmodus.');
                }
                return ['mode' => self::MODES[$value]];
            case 'HeatLevel':
                if (!is_int($value) || $value < 1 || $value > 3) {
                    throw new \InvalidArgumentException('Die Heizstufe muss zwischen 1 und 3 liegen.');
                }
                return ['htalevel' => $value];
            case 'TargetTemperature':
                if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)
                    || $value < 5 || $value > 35 || floor((float) $value) != $value) {
                    throw new \InvalidArgumentException('Solltemperatur: ganze Grad zwischen 5 und 35 °C.');
                }
                return ['ecolevel' => (int) ceil(round($value * 9 / 5 + 32, 6))];
            default:
                throw new \InvalidArgumentException('Diese Variable ist nicht schaltbar.');
        }
    }

    public static function matches(array $actual, array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual)) { return false; }
            if (is_bool($value)) {
                if (!in_array($actual[$key], [$value, $value ? 1 : 0], true)) { return false; }
            } elseif ($actual[$key] != $value) { return false; }
        }
        return true;
    }

    public static function transferStats(array $info, int $curlCode): array
    {
        // A whitelist deliberately excludes the effective URL, IP addresses and request headers.
        $result = ['httpStatus' => (int) ($info['http_code'] ?? 0), 'curlCode' => $curlCode];
        foreach (['dnsMs' => 'namelookup_time', 'connectMs' => 'connect_time', 'tlsMs' => 'appconnect_time',
            'firstByteMs' => 'starttransfer_time', 'totalMs' => 'total_time'] as $name => $key) {
            $result[$name] = isset($info[$key]) && is_numeric($info[$key]) ? round((float) $info[$key] * 1000, 1) : null;
        }
        return $result;
    }
}
