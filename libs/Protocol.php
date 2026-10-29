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

    public static function openLoginBody(string $email, string $password): array
    {
        return ['client_id' => '89ef537b2202481aaaf9077068bcb0c9',
            'client_secret' => '41b20a1f60e9499e89c8646c31f93ea1', 'grant_type' => 'openapi',
            'scope' => 'all', 'email' => $email, 'password' => md5($password)];
    }

    private static function firstString(array $node, array $keys): ?string
    {
        foreach ($keys as $key) { if (is_string($node[$key] ?? null) && $node[$key] !== '') { return $node[$key]; } }
        return null;
    }
    private static function model(array $node): ?string { return self::firstString($node, ['model', 'deviceModel', 'productName']); }
    private static function serial(array $node): ?string { return self::firstString($node, ['sn', 'deviceSn', 'devicesn', 'serialNumber', 'serial_number']); }

    public static function devices(array $data): array
    {
        $found = [];
        $walk = static function (array $node) use (&$walk, &$found): void {
            $sn = self::serial($node);
            if (self::model($node) === self::MODEL && is_string($sn) && $sn !== '') {
                $found[$sn] = ['serial' => $sn, 'model' => self::MODEL,
                    'name' => self::firstString($node, ['deviceName', 'name', 'nickname', 'alias']) ?? 'Dreo 720S',
                    'heaterConfig' => is_array($node['config']['heater_entity_config'] ?? null) ? $node['config']['heater_entity_config'] : ($found[$sn]['heaterConfig'] ?? [])];
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
            if (is_string(self::model($node))) {
                ++$stats['recordsWithModel'];
                if (self::model($node) === self::MODEL) {
                    ++$stats['supportedRecords'];
                    $sn = self::serial($node);
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

    private static function openUnit(array $config): string
    {
        $unit = $config['temperature_unit'] ?? '';
        if (!in_array($unit, ['celsius', 'fahrenheit'], true)) {
            throw new \RuntimeException('OpenAPI: Temperatureinheit fehlt in der Gerätekonfiguration; keine Umrechnung oder Steuerung auf Verdacht.');
        }
        return $unit;
    }

    private static function openModes(array $config): array
    {
        $result = []; $ambiguous = [];
        $map = $config['hvac_mode_relate_map'] ?? [];
        if (!is_array($map)) { return []; }
        foreach ($map as $reported => $entry) {
            if (!is_array($entry)) { continue; }
            $report = $entry['report'] ?? [];
            if (!is_array($report)) { continue; }
            $labels = [$reported, $report['directive_value'] ?? ''];
            $canonical = null;
            if (in_array('eco', $labels, true)) { $canonical = 'eco'; }
            elseif (($report['hvac_mode_value'] ?? '') === 'fan_only' || in_array('coolair', $labels, true)) { $canonical = 'coolair'; }
            elseif (($report['hvac_mode_value'] ?? '') === 'heat' || in_array('hotair', $labels, true)) { $canonical = 'hotair'; }
            if ($canonical === null) { continue; }
            if (isset($result[$canonical])) { $ambiguous[$canonical] = true; }
            $result[$canonical] = ['reported' => (string) $reported, 'controls' => $entry['controls'] ?? []];
        }
        foreach ($ambiguous as $mode => $_) { unset($result[$mode]); }
        return $result;
    }

    public static function openState(array $data, array $config): array
    {
        $state = $data;
        // Desired values are requests, never proof of the actual device state.
        if (isset($data['desired']) && !isset($data['power_switch'])) {
            $reported = false;
            foreach (['reported', 'state', 'status', 'properties'] as $key) {
                if (is_array($data[$key] ?? null)) { $reported = true; break; }
            }
            if (!$reported) { throw new \RuntimeException('OpenAPI liefert nur Sollwerte, keinen bestätigten Gerätestatus.'); }
        }
        foreach (['reported', 'state', 'status', 'properties'] as $key) {
            if (is_array($data[$key] ?? null)) { $state = $data[$key]; break; }
        }
        if (!array_key_exists('power_switch', $state) || !in_array($state['power_switch'], [true, false, 0, 1], true)) {
            throw new \RuntimeException('OpenAPI liefert keinen gültigen power_switch-Status.');
        }
        $result = ['poweron' => (bool) $state['power_switch']];
        foreach (['connected', 'ptcon', 'htalevel'] as $key) { if (array_key_exists($key, $state)) { $result[$key] = $state[$key]; } }
        if (!isset($result['connected']) && isset($data['connected'])) { $result['connected'] = $data['connected']; }
        $unit = self::openUnit($config);
        foreach (['temperature', 'ecolevel'] as $key) {
            if (isset($state[$key]) && is_numeric($state[$key])) {
                $result[$key] = $unit === 'celsius' ? (float) $state[$key] * 9 / 5 + 32 : $state[$key];
                if ($unit === 'celsius' && $key === 'ecolevel') { $result[$key] = (int) ceil(round((float) $result[$key], 6)); }
            }
        }
        $modes = self::openModes($config);
        foreach ($modes as $canonical => $entry) {
            if (($state['hvacmode'] ?? null) === $entry['reported']) { $result['mode'] = $canonical; break; }
        }
        if (!isset($result['mode']) && in_array($state['mode'] ?? null, self::MODES, true)) { $result['mode'] = $state['mode']; }
        $result['_modeWritable'] = count($modes) === 3;
        return $result;
    }

    public static function openCommand(array $params, array $config): array
    {
        if (count($params) !== 1) { throw new \InvalidArgumentException('Genau einen geprüften Heizbefehl senden.'); }
        $key = array_key_first($params); $value = $params[$key];
        switch ($key) {
            case 'poweron':
                if (!is_bool($value)) { throw new \InvalidArgumentException('Ungültiger Ein/Aus-Befehl.'); }
                return ['power_switch' => $value];
            case 'htalevel':
                if (!is_int($value) || $value < 1 || $value > 3) { throw new \InvalidArgumentException('Ungültige Heizstufe.'); }
                return ['htalevel' => $value];
            case 'ecolevel':
                if (!is_numeric($value) || !is_finite((float) $value)) { throw new \InvalidArgumentException('Ungültige Solltemperatur.'); }
                $target = self::openUnit($config) === 'celsius' ? self::targetCelsius((float) $value) : (float) $value;
                $range = $config['temperature_range'] ?? null;
                if (!is_array($range) || count($range) !== 2 || !is_numeric($range[0]) || !is_numeric($range[1])
                    || $target < (float) $range[0] || $target > (float) $range[1]) {
                    throw new \RuntimeException('Solltemperatur liegt außerhalb des von OpenAPI bestätigten Bereichs oder der Bereich fehlt.');
                }
                return ['ecolevel' => (int) $target];
            case 'mode':
                if (!is_string($value) || !in_array($value, self::MODES, true)) { throw new \InvalidArgumentException('Ungültiger Betriebsmodus.'); }
                $modes = self::openModes($config);
                if (count($modes) !== 3 || !is_array($modes[$value]['controls'] ?? null)) {
                    throw new \RuntimeException('OpenAPI liefert keine eindeutige Zuordnung aller drei Heizmodi.');
                }
                $desired = [];
                foreach ($modes[$value]['controls'] as $control) {
                    if (!is_array($control)) { throw new \RuntimeException('Ungültige Moduskonfiguration.'); }
                    $name = $control['directive_name'] ?? '';
                    if ($name === 'power_switch') { continue; } // Mode selection never powers the heater on.
                    $setting = $control['directive_value'] ?? null;
                    if (!in_array($name, ['hvacmode', 'mode'], true) || !is_string($setting) || $setting === '' || strlen($setting) > 64) {
                        throw new \RuntimeException('OpenAPI-Modus enthält eine nicht geprüfte Zusatzfunktion.');
                    }
                    $desired[$name] = $setting;
                }
                if ($desired === []) { throw new \RuntimeException('OpenAPI-Modus enthält keinen verwendbaren Steuerbefehl.'); }
                return $desired;
            default: throw new \InvalidArgumentException('OpenAPI-Befehl nicht freigegeben.');
        }
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
