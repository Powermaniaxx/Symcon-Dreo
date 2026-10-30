<?php
declare(strict_types=1);

namespace SymconDreo;

/** Explicit App-API profiles, based on the pinned sources in docs/PROVENANCE.md. */
final class ModelCatalog
{
    public const CONTROLS = ['Power', 'Mode', 'HeatLevel', 'TargetTemperature', 'FanSpeed', 'Oscillation', 'TargetHumidity', 'MistLevel'];
    public const OPTIONAL = ['Mode', 'HeatLevel', 'TargetTemperature', 'CurrentTemperature', 'Heating', 'HeatingKnown',
        'FanSpeed', 'Oscillation', 'CurrentHumidity', 'TargetHumidity', 'MistLevel'];

    public static function all(): array
    {
        $models = [];
        foreach (['DR-HSH003S', 'DR-HSH004S', 'DR-HSH006S', 'DR-HSH009S', 'DR-HSH009AS',
            'DR-HSH010S', 'DR-HSH011', 'DR-HSH011S', 'DR-HSH017S', 'DR-HSH017BS', 'DR-HSH034S', 'DR-HSH040S', 'DR-HSH041S'] as $model) {
            $models[$model] = ['type' => 'heater', 'maxTemperature' => $model === 'DR-HSH017BS' ? 29 : 35];
        }
        foreach (['DR-HTF007S' => 4, 'DR-HTF011S' => 9, 'DR-HTF018S' => 9, 'DR-HTF017S' => 4,
            'DR-HTF024S' => 9, 'DR-HTF021S' => 12, 'DR-HTF021AS' => 12, 'DR-HTF518S' => 9,
            'DR-HAF001S' => 4, 'DR-HAF008S' => 9, 'DR-HPF004S' => 9, 'DR-HPF005S' => 10,
            'DR-HPF007S' => 10, 'DR-HPF008S' => 9, 'DR-HPF020S' => 9, 'DR-HPF022S' => 9] as $model => $max) {
            $modes = [1 => 'Normal', 2 => 'Natur', 3 => 'Schlafen', 4 => 'Auto'];
            if (str_starts_with($model, 'DR-HPF') || $model === 'DR-HAF008S') { $modes[5] = 'Turbo'; }
            if (in_array($model, ['DR-HPF004S', 'DR-HPF005S', 'DR-HPF007S', 'DR-HPF020S'], true)) { $modes[6] = 'Benutzerdefiniert'; }
            $models[$model] = ['type' => 'fan', 'maxSpeed' => $max, 'modes' => $modes];
        }
        foreach (['DR-HAP006S', 'DR-HAP007S', 'DR-HAP008S', 'DR-HAP009S', 'DR-HAP010S'] as $model) {
            $models[$model] = ['type' => 'purifier', 'maxSpeed' => 4];
        }
        foreach (['DR-HHM003S', 'DR-HHM005S', 'DR-HHM006S', 'DR-HHM014S', 'DR-HHM015S'] as $model) {
            $models[$model] = ['type' => 'humidifier', 'modes' => in_array($model, ['DR-HHM005S', 'DR-HHM006S', 'DR-HHM014S'], true)
                ? [0 => 'Manuell', 1 => 'Auto', 2 => 'Schlafen'] : []];
        }
        return $models;
    }

    public static function profile(string $model): ?array { return self::all()[$model] ?? null; }

    public static function boolean(mixed $value): bool { return in_array($value, [true, false, 0, 1], true); }
    private static function number(mixed $value): bool
    {
        return (is_int($value) || is_float($value) || is_string($value) && is_numeric($value)) && is_finite((float) $value);
    }

    /** Each visible/writable field must also have a valid reported value. No desired-state inference. */
    public static function fields(string $model, array $state, array $metadata = []): array
    {
        $profile = self::profile($model);
        if ($profile === null) { throw new \RuntimeException('Dieses Dreo-Modell ist noch nicht freigegeben.'); }
        $type = $profile['type'];
        $fields = [];
        $bool = static function (string $ident, string $key) use (&$fields, $state): void {
            if (array_key_exists($key, $state) && self::boolean($state[$key])) {
                $fields[$ident] = ['key' => $key, 'kind' => 'bool', 'value' => (bool) $state[$key]];
            }
        };
        $numeric = static function (string $ident, string $key, int $min, int $max) use (&$fields, $state): void {
            $value = $state[$key] ?? null;
            if (self::number($value) && (float) $value >= $min && (float) $value <= $max && floor((float) $value) == $value) {
                $fields[$ident] = ['key' => $key, 'kind' => 'int', 'min' => $min, 'max' => $max, 'value' => (int) $value];
            }
        };
        $enum = static function (string $key, array $values, array $labels) use (&$fields, $state): void {
            $index = array_search($state[$key] ?? null, $values, true);
            if ($index !== false) { $fields['Mode'] = ['key' => $key, 'kind' => 'enum', 'values' => $values, 'labels' => $labels, 'value' => $index]; }
        };
        $powerKey = 'poweron';
        if (in_array($type, ['fan', 'purifier'], true) && !array_key_exists($powerKey, $state)) { $powerKey = 'fanon'; }
        $bool('Power', $powerKey);
        if (!isset($fields['Power'])) { throw new \RuntimeException('Gerätestatus enthält keinen gültigen Ein/Aus-Wert.'); }

        if ($type === 'heater') {
            $enum('mode', ['coolair', 'hotair', 'eco'], ['Nur Lüften', 'Heizen', 'ECO']);
            $numeric('HeatLevel', 'htalevel', 1, 3);
            if (self::number($state['temperature'] ?? null)) {
                $temperature = (float) $state['temperature'];
                if (!in_array($model, ['DR-HSH006S', 'DR-HSH040S'], true) && self::number($state['tempoffset'] ?? null)) {
                    $temperature += (float) $state['tempoffset'];
                }
                $fields['CurrentTemperature'] = ['key' => 'temperature', 'kind' => 'read', 'value' => Protocol::celsius($temperature)];
            }
            if (self::number($state['ecolevel'] ?? null)) {
                $fields['TargetTemperature'] = ['key' => 'ecolevel', 'kind' => 'temperature', 'min' => 5, 'max' => $profile['maxTemperature'],
                    'value' => Protocol::targetCelsius((float) $state['ecolevel'])];
            }
            $bool('Heating', 'ptcon');
            // Preserve the 720S's established strict status validation.
            if ($model === Protocol::MODEL) {
                foreach (['CurrentTemperature', 'TargetTemperature', 'HeatLevel'] as $ident) {
                    if (!isset($fields[$ident])) { throw new \RuntimeException('720S liefert keinen gültigen Wert für ' . $ident . '.'); }
                }
                if ($fields['Power']['value'] && !isset($fields['Mode'])) { throw new \RuntimeException('720S meldet einen unbekannten Betriebsmodus.'); }
                if (($metadata['apiMode'] ?? 'app') === 'app' && !isset($fields['Heating'])) {
                    throw new \RuntimeException('720S liefert keinen gültigen Heizstatus.');
                }
            }
        } elseif ($type === 'fan') {
            $max = $profile['maxSpeed'];
            // HTF007S has 4- and 8-speed hardware. Unknown revisions retain the safe shared range.
            if ($model === 'DR-HTF007S') {
                $control = $metadata['controlsConf']['control'] ?? [];
                foreach (is_array($control) ? $control : [] as $node) {
                    if (($node['type'] ?? null) === 'Speed' && ($node['items'][0]['value'] ?? null) === 1 && ($node['items'][1]['value'] ?? null) === 8) { $max = 8; }
                }
                if (in_array($state['mcu_hardware_model'] ?? '', ['CMS89F7518', 'CMS89F7518/EUR', 'CMS89F7518/USA'], true)) { $max = 4; }
            }
            $numeric('FanSpeed', 'windlevel', 1, $max);
            $modes = $profile['modes'];
            $enum(array_key_exists('windtype', $state) ? 'windtype' : 'mode', array_values(array_keys($modes)), array_values($modes));
            foreach (['hoscon', 'shakehorizon', 'oscon'] as $key) {
                if (self::boolean($state[$key] ?? null)) { $bool('Oscillation', $key); break; }
            }
        } elseif ($type === 'purifier') {
            $numeric('FanSpeed', 'windlevel', 1, $profile['maxSpeed']);
            $enum('mode', ['auto-regular', 'manual', 'sleep', 'turbo'], ['Auto', 'Manuell', 'Schlafen', 'Turbo']);
            if (($state['mode'] ?? null) === 'auto') {
                $fields['Mode'] = ['key' => 'mode', 'kind' => 'enum', 'values' => ['auto-regular', 'manual', 'sleep', 'turbo'],
                    'labels' => ['Auto', 'Manuell', 'Schlafen', 'Turbo'], 'value' => 0];
            }
            // HAP010S speed has no physical effect with purifyon=false; never enable it implicitly.
            if ($model === 'DR-HAP010S' && !in_array($state['purifyon'] ?? null, [true, 1], true)) { unset($fields['FanSpeed']); }
        } elseif ($type === 'humidifier') {
            if (self::number($state['rh'] ?? null) && (float) $state['rh'] >= 0 && (float) $state['rh'] <= 100) {
                $fields['CurrentHumidity'] = ['key' => 'rh', 'kind' => 'read', 'value' => (float) $state['rh']];
            }
            $numeric('MistLevel', 'foglevel', 0, 6);
            $modes = $profile['modes'];
            if ($modes !== []) { $enum('mode', array_values(array_keys($modes)), array_values($modes)); }
            // Sleep and automatic targets are different directives. Do not write the other mode's target.
            if (in_array($state['mode'] ?? null, [0, 1, 2], true)) {
                $key = $state['mode'] === 2 ? 'rhsleeplevel' : 'rhautolevel';
                $range = $metadata['controlsConf']['humRange'] ?? [];
                $min = $range['controlMin'] ?? 30; $max = $range['controlMax'] ?? 90;
                if (is_int($min) && is_int($max) && $min >= 30 && $max <= 90 && $min < $max) { $numeric('TargetHumidity', $key, $min, $max); }
            }
        }
        if (isset($fields['Heating'])) { $fields['Heating']['kind'] = 'read'; }
        if (($metadata['apiMode'] ?? '') === 'openapi' && !($state['_modeWritable'] ?? false)) { unset($fields['Mode']); }
        return $fields;
    }

    public static function command(string $model, array $state, array $metadata, string $ident, mixed $value): array
    {
        $field = self::fields($model, $state, $metadata)[$ident] ?? null;
        if ($field === null || !in_array($ident, self::CONTROLS, true) || $field['kind'] === 'read') {
            throw new \InvalidArgumentException('Diese Funktion wird vom gewählten Gerät derzeit nicht angeboten.');
        }
        switch ($field['kind']) {
            case 'bool':
                if (!is_bool($value)) { throw new \InvalidArgumentException('Ein/Aus benötigt einen booleschen Wert.'); }
                return [$field['key'] => $value];
            case 'enum':
                if (!is_int($value) || !array_key_exists($value, $field['values'])) { throw new \InvalidArgumentException('Ungültige Betriebsart für dieses Modell.'); }
                return [$field['key'] => $field['values'][$value]];
            case 'int':
            case 'temperature':
                if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || floor((float) $value) != $value
                    || $value < $field['min'] || $value > $field['max']) { throw new \InvalidArgumentException('Wert liegt außerhalb des Bereichs dieses Modells.'); }
                if ($field['kind'] === 'int' && !is_int($value)) { throw new \InvalidArgumentException('Diese Einstellung benötigt eine ganze Zahl.'); }
                return [$field['key'] => $field['kind'] === 'temperature' ? (int) ceil(round($value * 9 / 5 + 32, 6)) : $value];
        }
        throw new \InvalidArgumentException('Unbekannte Gerätefunktion.');
    }
}
