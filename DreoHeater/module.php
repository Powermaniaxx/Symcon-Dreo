<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/Client.php';

use SymconDreo\Client;
use SymconDreo\Protocol;
use SymconDreo\ApiException;

class DreoHeater extends IPSModuleStrict
{
    private float $operationDeadline = 0.0;
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyBoolean('Open', false);
        $this->RegisterPropertyString('Email', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('Region', 'EU');
        $this->RegisterPropertyString('Serial', '');
        $this->RegisterPropertyInteger('Interval', 30);
        $this->RegisterAttributeString('Devices', '[]');
        $this->RegisterTimer('Refresh', 0, 'DREO_Refresh($_IPS["TARGET"]);');
        $this->RegisterVariableBoolean('Power', 'Ein/Aus', ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH], 10);
        $this->RegisterVariableInteger('Mode', 'Betriebsart', $this->enumPresentation(['Nur Lüften', 'Heizen', 'ECO']), 20);
        $this->RegisterVariableInteger('HeatLevel', 'Heizstufe', $this->enumPresentation([1 => 'H1', 2 => 'H2', 3 => 'H3']), 30);
        $this->RegisterVariableFloat('TargetTemperature', 'Solltemperatur', ['PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'MIN' => 5, 'MAX' => 35, 'STEP_SIZE' => 1, 'SUFFIX' => ' °C', 'DIGITS' => 0], 40);
        $this->RegisterVariableFloat('CurrentTemperature', 'Raumtemperatur', ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => ' °C', 'DIGITS' => 1], 50);
        $this->RegisterVariableBoolean('Heating', 'Heizelement aktiv', [], 60);
        $this->RegisterVariableBoolean('Online', 'Online ausdrücklich bestätigt', [], 70);
        $this->RegisterVariableString('Availability', 'Verbindungsstatus', [], 75);
        $this->RegisterVariableBoolean('StateValid', 'Status gültig', [], 80);
        $this->RegisterVariableString('LastUpdate', 'Letzte erfolgreiche Abfrage (UTC)', [], 90);
        $this->RegisterVariableInteger('LastUpdateUnix', 'Letzte erfolgreiche Abfrage (Unixzeit)', [], 95);
        $this->RegisterVariableString('LastCommand', 'Letzter Schaltauftrag', [], 100);
        $this->RegisterVariableString('LastError', 'Letzter Fehler', [], 110);
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        $this->actions(false);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('Refresh', 0);
        $this->SetBuffer('Session', '');
        $this->SetBuffer('Pending', '');
        $this->SetBuffer('Failures', '0');
        $this->SetBuffer('VerifiedSerial', '');
        $this->SetValue('StateValid', false);
        $this->SetValue('Online', false);
        $this->SetValue('Availability', 'Noch keine aktuelle Statusabfrage');
        $this->actions(false);
        if (!$this->ReadPropertyBoolean('Open')) { $this->SetStatus(104); return; }
        try { $this->configuration(false); }
        catch (Throwable $e) { $this->SetValue('LastError', $e->getMessage()); $this->feedback($e->getMessage()); $this->SetStatus(200); return; }
        $this->SetValue('LastError', '');
        if ($this->ReadPropertyString('Serial') === '') {
            $this->feedback('Zugangsdaten gespeichert. Jetzt Geräte suchen und danach ein Gerät auswählen.');
            $this->SetStatus(201);
            return;
        }
        $this->SetSummary('Dreo 720S · ' . $this->ReadPropertyString('Region'));
        if (IPS_GetKernelRunlevel() === KR_READY) { $this->SetTimerInterval('Refresh', 1000); }
        $this->SetStatus(101);
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === IPS_KERNELMESSAGE && ($Data[0] ?? 0) === KR_READY) { $this->ApplyChanges(); }
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true, 64, JSON_THROW_ON_ERROR);
        foreach ($form['elements'] as &$element) {
            if (($element['name'] ?? '') === 'Serial') { $element['options'] = $this->deviceOptions(); }
            if (($element['name'] ?? '') === 'Feedback' && $this->GetBuffer('SearchFeedback') !== '') {
                $element['caption'] = $this->GetBuffer('SearchFeedback');
            }
        }
        unset($element);
        return json_encode($form, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function DiscoverDevices(): bool
    {
        return $this->guard(function (): bool {
            $this->configuration(false);
            $this->feedback('Anmeldung und Gerätesuche laufen …');
            $devices = $this->discoverWithDiagnostics();
            $this->WriteAttributeString('Devices', json_encode($devices, JSON_THROW_ON_ERROR));
            $this->SetBuffer('VerifiedSerial', '');
            $this->UpdateFormField('Serial', 'options', json_encode($this->deviceOptions(), JSON_THROW_ON_ERROR));
            $this->SetValue('LastError', '');
            $this->feedback($devices === []
                ? 'Anmeldung erfolgreich, aber keine verwendbaren 720S-Geräte geliefert. Unter „Diagnose anzeigen“ stehen die Abfrageergebnisse ohne Zugangsdaten.'
                : count($devices) . ' Gerät(e) gefunden. Gerät auswählen und Änderungen übernehmen.');
            if ($this->ReadPropertyBoolean('Open') && $this->ReadPropertyString('Serial') === '') { $this->SetStatus(201); }
            return true;
        });
    }

    public function Refresh(): bool
    {
        if (!$this->ReadPropertyBoolean('Open')) { return false; }
        if ($this->ReadPropertyString('Serial') === '') {
            $this->SetTimerInterval('Refresh', 0);
            $this->SetStatus(201);
            return false;
        }
        return $this->guard(function (): bool {
            $this->configuration(true);
            $this->verifySelectedDevice();
            $this->refreshState();
            return true;
        });
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $params = Protocol::command($Ident, $Value);
        $ok = $this->guard(function () use ($params): bool {
            if (!$this->ReadPropertyBoolean('Open')) { throw new RuntimeException('Die Instanz ist deaktiviert.'); }
            $this->configuration(true);
            $this->verifySelectedDevice();
            // An old failed/unknown status must not accidentally enable a heater.
            if (!$this->GetValue('StateValid')) { throw new RuntimeException('Zuerst einen gültigen Gerätestatus abrufen.'); }
            $serial = $this->ReadPropertyString('Serial');
            $raw = json_decode($this->GetBuffer('State'), true) ?? [];
            if (isset($raw['connected']) && in_array($raw['connected'], [false, 0], true)) {
                throw new RuntimeException('Das Gerät wurde als offline gemeldet.');
            }
            $this->SetValue('LastCommand', 'Wird gesendet; tatsächlicher Gerätestatus bleibt maßgeblich.');
            $this->SetBuffer('Pending', json_encode(['params' => $params, 'expires' => time() + 60, 'acknowledged' => false], JSON_THROW_ON_ERROR));
            $this->authenticated(fn(Client $client): string => $client->send($serial, $params));
            $this->SetBuffer('Pending', json_encode(['params' => $params, 'expires' => time() + 60, 'acknowledged' => true], JSON_THROW_ON_ERROR));
            $this->SetValue('LastCommand', 'Von der Cloud angenommen; Gerätebestätigung ausstehend.');
            $this->refreshState();
            return true;
        }, true);
        if (!$ok) { throw new RuntimeException((string) $this->GetValue('LastError')); }
    }

    public function SetPower(bool $Value): void { $this->RequestAction('Power', $Value); }
    public function SetMode(int $Value): void { $this->RequestAction('Mode', $Value); }
    public function SetHeatLevel(int $Value): void { $this->RequestAction('HeatLevel', $Value); }
    public function SetTargetTemperature(float $Value): void { $this->RequestAction('TargetTemperature', $Value); }

    public function ResetSession(): void
    {
        $this->SetBuffer('Session', '');
        $this->SetBuffer('VerifiedSerial', '');
        $this->SetBuffer('Failures', '0');
        $this->SetValue('StateValid', false);
        $this->SetValue('Online', false);
        $this->SetValue('Availability', 'Sitzung zurückgesetzt; Status noch unbekannt');
        $this->actions(false);
        if ($this->ReadPropertyBoolean('Open')) { $this->SetTimerInterval('Refresh', 1000); }
    }

    public function GetDiagnostics(): string
    {
        // Deliberate allowlist: no account name, serial, password, bearer token or raw API data.
        return json_encode(['module' => 'SymconDreo', 'version' => '0.1.0-beta.2', 'model' => Protocol::MODEL,
            'region' => $this->ReadPropertyString('Region'), 'active' => $this->ReadPropertyBoolean('Open'),
            'stateValid' => $this->GetValue('StateValid'), 'lastUpdateUtc' => $this->GetValue('LastUpdate'),
            'lastError' => $this->GetValue('LastError'),
            'discovery' => json_decode($this->GetBuffer('DiscoveryDiagnostics'), true) ?? [],
            'lastRequests' => json_decode($this->GetBuffer('RequestDiagnostics'), true) ?? [],
            'serverRegion' => (json_decode($this->GetBuffer('Session'), true) ?? [])['region'] ?? null,
            'stateKeys' => array_keys(json_decode($this->GetBuffer('State'), true) ?? [])],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    protected function newClient(string $region, string $token): Client
    {
        return new Client($region, $token, null, max(0, $this->operationDeadline - microtime(true)));
    }

    private function authenticated(Closure $operation): mixed
    {
        $session = json_decode($this->GetBuffer('Session'), true) ?? [];
        $client = $this->newClient($session['region'] ?? $this->ReadPropertyString('Region'), $session['token'] ?? '');
        try {
            if (empty($session['token'])) { $client->login(trim($this->ReadPropertyString('Email')), $this->ReadPropertyString('Password')); }
            try { return $operation($client); }
            catch (ApiException $e) {
                if ($e->getCode() !== 401 && $e->getCode() !== 403) { throw $e; }
                $client->login(trim($this->ReadPropertyString('Email')), $this->ReadPropertyString('Password'));
                return $operation($client);
            }
        } finally {
            $this->SetBuffer('Session', json_encode($client->session(), JSON_THROW_ON_ERROR));
            $this->SetBuffer('RequestDiagnostics', json_encode($client->diagnostics(), JSON_THROW_ON_ERROR));
        }
    }

    private function discoverWithDiagnostics(): array
    {
        $this->SetBuffer('DiscoveryDiagnostics', '');
        return $this->authenticated(function (Client $client): array {
            try { return $client->discover(); }
            finally { $this->SetBuffer('DiscoveryDiagnostics', json_encode($client->diagnostics(), JSON_THROW_ON_ERROR)); }
        });
    }

    private function verifySelectedDevice(): void
    {
        $serial = $this->ReadPropertyString('Serial');
        if ($this->GetBuffer('VerifiedSerial') === $serial) { return; }
        $devices = $this->discoverWithDiagnostics();
        $this->WriteAttributeString('Devices', json_encode($devices, JSON_THROW_ON_ERROR));
        foreach ($devices as $device) {
            if ($device['serial'] === $serial && $device['model'] === Protocol::MODEL) {
                $this->SetBuffer('VerifiedSerial', $serial); return;
            }
        }
        throw new RuntimeException('Das gewählte Gerät wurde in diesem Konto nicht als unterstützter 720S gefunden.');
    }

    private function refreshState(): void
    {
        $state = $this->authenticated(fn(Client $client): array => $client->state($this->ReadPropertyString('Serial')));
        if (!array_key_exists('poweron', $state) || !in_array($state['poweron'], [true, false, 0, 1], true)) {
            throw new RuntimeException('Gerätestatus enthält keinen gültigen Ein/Aus-Wert.');
        }
        foreach (['temperature', 'ecolevel', 'htalevel'] as $key) {
            if (!isset($state[$key]) || !is_numeric($state[$key]) || !is_finite((float) $state[$key])) {
                throw new RuntimeException('Gerätestatus enthält keinen gültigen Wert für ' . $key . '.');
            }
        }
        if (!isset($state['ptcon']) || !in_array($state['ptcon'], [true, false, 0, 1], true)) {
            throw new RuntimeException('Gerätestatus enthält keinen gültigen Heizstatus.');
        }
        if ((bool) $state['poweron'] && !in_array($state['mode'] ?? null, Protocol::MODES, true)) {
            throw new RuntimeException('Gerätestatus enthält einen unbekannten Betriebsmodus.');
        }
        $this->SetBuffer('State', json_encode($state, JSON_THROW_ON_ERROR));
        $this->SetValue('Power', (bool) $state['poweron']);
        $mode = array_search($state['mode'] ?? null, Protocol::MODES, true);
        if ($mode !== false) { $this->SetValue('Mode', $mode); }
        if (isset($state['htalevel']) && is_numeric($state['htalevel'])) { $this->SetValue('HeatLevel', (int) $state['htalevel']); }
        if (isset($state['temperature']) && is_numeric($state['temperature'])) {
            $this->SetValue('CurrentTemperature', Protocol::celsius((float) $state['temperature']));
        }
        if (isset($state['ecolevel']) && is_numeric($state['ecolevel'])) {
            $this->SetValue('TargetTemperature', Protocol::targetCelsius((float) $state['ecolevel']));
        }
        if (isset($state['ptcon']) && in_array($state['ptcon'], [true, false, 0, 1], true)) {
            $this->SetValue('Heating', (bool) $state['ptcon']);
        }
        $online = isset($state['connected']) && in_array($state['connected'], [true, 1], true);
        $offline = isset($state['connected']) && in_array($state['connected'], [false, 0], true);
        $this->SetValue('Online', $online);
        $this->SetValue('Availability', $online ? 'Online gemeldet' : ($offline ? 'Offline gemeldet' : 'Unbekannt; Cloud liefert Status, aber kein Online-Merkmal'));
        $this->SetValue('StateValid', true);
        $this->SetValue('LastUpdate', gmdate('Y-m-d H:i:s') . ' UTC');
        $this->SetValue('LastUpdateUnix', time());
        $this->SetValue('LastError', '');
        $this->SetBuffer('Failures', '0');
        $pending = json_decode($this->GetBuffer('Pending'), true) ?? [];
        if (isset($pending['params'])) {
            if (Protocol::matches($state, $pending['params'])) {
                $this->SetValue('LastCommand', ($pending['acknowledged'] ?? false)
                    ? 'Durch Gerätestatus bestätigt.' : 'Gewünschter Gerätestatus vorhanden; vorheriger Versand war unklar.');
                $this->SetBuffer('Pending', '');
            } elseif (($pending['expires'] ?? 0) <= time()) {
                $this->SetValue('LastCommand', 'Nicht durch Gerätestatus bestätigt. App und Gerät prüfen.');
                $this->SetBuffer('Pending', '');
            }
        }
        $map = ['Power' => 'poweron', 'Mode' => 'mode', 'HeatLevel' => 'htalevel', 'TargetTemperature' => 'ecolevel'];
        foreach ($map as $ident => $key) { $this->MaintainAction($ident, !$offline && isset($state[$key])); }
        $this->SetStatus($offline ? 204 : 102);
        $this->feedback('Gerätestatus erfolgreich aktualisiert.');
        $this->SetTimerInterval('Refresh', $this->interval() * 1000);
    }

    private function configuration(bool $requireSerial): void
    {
        if (trim($this->ReadPropertyString('Email')) === '' || $this->ReadPropertyString('Password') === '') {
            throw new RuntimeException('Dreo-E-Mail und Passwort eintragen und Änderungen übernehmen.');
        }
        if (!in_array($this->ReadPropertyString('Region'), ['EU', 'NA'], true)) { throw new RuntimeException('Region EU oder NA wählen.'); }
        if ($requireSerial && $this->ReadPropertyString('Serial') === '') { throw new RuntimeException('Geräte suchen, einen 720S auswählen und Änderungen übernehmen.'); }
        if ($this->ReadPropertyInteger('Interval') < 30 || $this->ReadPropertyInteger('Interval') > 3600) {
            throw new RuntimeException('Abfrageintervall: 30 bis 3600 Sekunden.');
        }
    }

    private function interval(): int { return max(30, min(3600, $this->ReadPropertyInteger('Interval'))); }

    private function guard(Closure $operation, bool $command = false): bool
    {
        $lock = 'SymconDreo.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 1000)) {
            // Another request owns the session; do not invalidate its state or issue a second command.
            if ($command) { throw new RuntimeException('Dreo-Abfrage läuft bereits. Bitte kurz warten.'); }
            return false;
        }
        $this->operationDeadline = microtime(true) + 22.0;
        try { return $operation(); }
        catch (Throwable $e) {
            $this->SetValue('StateValid', false);
            $this->SetValue('Online', false);
            $this->SetValue('Availability', 'Unbekannt; letzte Abfrage fehlgeschlagen');
            $this->actions(false);
            $message = $e->getMessage();
            // Only our own fixed messages reach this point; replace credentials as a second guard.
            $session = json_decode($this->GetBuffer('Session'), true) ?? [];
            foreach ([$this->ReadPropertyString('Password'), md5($this->ReadPropertyString('Password')),
                trim($this->ReadPropertyString('Email')), $session['token'] ?? '', $this->ReadPropertyString('Serial')] as $secret) {
                if ($secret !== '') { $message = str_replace($secret, '[entfernt]', $message); }
            }
            $this->SetValue('LastError', $message);
            $this->feedback('Fehler: ' . $message);
            $pending = json_decode($this->GetBuffer('Pending'), true) ?? [];
            if (isset($pending['params'])) {
                $this->SetValue('LastCommand', ($pending['acknowledged'] ?? false)
                    ? 'Cloud-Annahme vorhanden; Gerätestatus derzeit unbestätigt.'
                    : 'Versand oder Bestätigung fehlgeschlagen; Ausführung unklar. Status prüfen.');
            }
            $this->SendDebug('Dreo', $message, 0);
            $this->SetStatus(202);
            $failures = min(5, (int) $this->GetBuffer('Failures') + 1);
            $this->SetBuffer('Failures', (string) $failures);
            $delay = min(3600, max($this->interval(), min(300, 30 * (2 ** ($failures - 1)))));
            $this->SetTimerInterval('Refresh', $this->ReadPropertyBoolean('Open') && $this->ReadPropertyString('Serial') !== ''
                && $e->getCode() !== 100022 ? $delay * 1000 : 0);
            return false;
        } finally { IPS_SemaphoreLeave($lock); }
    }

    private function actions(bool $enabled): void
    {
        foreach (['Power', 'Mode', 'HeatLevel', 'TargetTemperature'] as $ident) { $this->MaintainAction($ident, $enabled); }
    }

    private function feedback(string $message): void
    {
        $this->SetBuffer('SearchFeedback', $message);
        $this->UpdateFormField('Feedback', 'caption', $message);
    }

    private function deviceOptions(): array
    {
        $options = [['caption' => 'Bitte ein Gerät auswählen', 'value' => '']];
        foreach (json_decode($this->ReadAttributeString('Devices'), true) ?? [] as $device) {
            $options[] = ['caption' => $device['name'] . ' (' . $device['model'] . ', …' . substr($device['serial'], -6) . ')', 'value' => $device['serial']];
        }
        return $options;
    }

    private function enumPresentation(array $labels): array
    {
        $options = [];
        foreach ($labels as $value => $caption) {
            $options[] = ['Value' => $value, 'Caption' => $caption, 'IconActive' => false, 'IconValue' => '', 'Color' => -1];
        }
        return ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'OPTIONS' => json_encode($options, JSON_THROW_ON_ERROR), 'DISPLAY' => 0];
    }
}
