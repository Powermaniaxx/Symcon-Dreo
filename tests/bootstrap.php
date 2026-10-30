<?php
declare(strict_types=1);

// Minimal in-memory SDK substitute. Does not claim to be a real Symcon kernel.
foreach (['IPS_KERNELMESSAGE' => 10001, 'KR_READY' => 10103,
    'VARIABLE_PRESENTATION_SWITCH' => 'switch', 'VARIABLE_PRESENTATION_ENUMERATION' => 'enum',
    'VARIABLE_PRESENTATION_SLIDER' => 'slider', 'VARIABLE_PRESENTATION_VALUE_PRESENTATION' => 'value'] as $key => $value) {
    if (!defined($key)) { define($key, $value); }
}
function IPS_GetKernelRunlevel(): int { return KR_READY; }
function IPS_SemaphoreEnter(string $name, int $timeout): bool
{
    if ((!str_starts_with($name, 'SymconDreo.Queue.') && ($GLOBALS['busy'] ?? false)) || isset($GLOBALS['heldLocks'][$name])) { return false; }
    $GLOBALS['heldLocks'][$name] = true;
    return true;
}
function IPS_SemaphoreLeave(string $name): void { unset($GLOBALS['heldLocks'][$name]); }
function IPS_RequestAction(int $id, string $ident, mixed $value): bool
{
    if (($GLOBALS['callbackFailure'] ?? false)) { throw new RuntimeException('Kernel-Testfehler'); }
    if (($GLOBALS['callbackReject'] ?? false)) { return false; }
    // Enforce silent internal actions. This is a regression constraint, not a kernel implementation.
    ob_start();
    try {
        $GLOBALS['callbackInstance']->RequestAction($ident, $value);
        if (ob_get_contents() !== '') { throw new RuntimeException('Unexpected output inside kernel action.'); }
    } finally { ob_end_clean(); }
    return true;
}
function DREO_GetFormFeedback(int $id): string { return $GLOBALS['callbackInstance']->GetFormFeedback(); }
function DREO_GetDiagnostics(int $id): string
{
    if (($GLOBALS['callbackFailure'] ?? false)) { throw new RuntimeException('Kernel-Testfehler'); }
    return $GLOBALS['callbackInstance']->GetDiagnostics();
}

class IPSModuleStrict
{
    public int $InstanceID = 101;
    public array $properties = [], $attributes = [], $buffers = [], $values = [], $actions = [], $timers = [], $debug = [];
    public int $status = 0;
    public function Create(): void {}
    public function ApplyChanges(): void {}
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void {}
    public function RequestAction(string $Ident, mixed $Value): void {}
    public function GetConfigurationForm(): string { return ''; }
    protected function RegisterPropertyBoolean(string $name, bool $default): void { $this->properties[$name] ??= $default; }
    protected function RegisterPropertyString(string $name, string $default): void { $this->properties[$name] ??= $default; }
    protected function RegisterPropertyInteger(string $name, int $default): void { $this->properties[$name] ??= $default; }
    protected function ReadPropertyBoolean(string $name): bool { return $this->properties[$name]; }
    protected function ReadPropertyString(string $name): string { return $this->properties[$name]; }
    protected function ReadPropertyInteger(string $name): int { return $this->properties[$name]; }
    protected function RegisterAttributeString(string $name, string $default): void { $this->attributes[$name] ??= $default; }
    protected function ReadAttributeString(string $name): string { return $this->attributes[$name]; }
    protected function WriteAttributeString(string $name, string $value): void { $this->attributes[$name] = $value; }
    protected function SetBuffer(string $name, string $value): void { $this->buffers[$name] = $value; }
    protected function GetBuffer(string $name): string { return $this->buffers[$name] ?? ''; }
    protected function RegisterTimer(string $name, int $interval, string $script): void { $this->timers[$name] = $interval; }
    protected function SetTimerInterval(string $name, int $interval): void { $this->timers[$name] = $interval; }
    protected function RegisterVariableBoolean(string $name, string $caption, array $presentation, int $position): bool { $new = !array_key_exists($name, $this->values); $this->values[$name] ??= false; return $new; }
    protected function RegisterVariableInteger(string $name, string $caption, array $presentation, int $position): bool { $new = !array_key_exists($name, $this->values); $this->values[$name] ??= 0; return $new; }
    protected function RegisterVariableFloat(string $name, string $caption, array $presentation, int $position): bool { $new = !array_key_exists($name, $this->values); $this->values[$name] ??= 0.0; return $new; }
    protected function RegisterVariableString(string $name, string $caption, array $presentation, int $position): bool { $new = !array_key_exists($name, $this->values); $this->values[$name] ??= ''; return $new; }
    protected function MaintainAction(string $name, bool $active): void { $this->actions[$name] = $active; }
    protected function SetValue(string $name, mixed $value): void { $this->values[$name] = $value; }
    protected function GetValue(string $name): mixed { return $this->values[$name]; }
    protected function SetStatus(int $status): void { $this->status = $status; }
    protected function SetSummary(string $summary): void {}
    protected function RegisterMessage(int $sender, int $message): void {}
    protected function UpdateFormField(string $name, string $parameter, string $value): void {}
    protected function SendDebug(string $name, string $message, int $format): void { $this->debug[] = $message; }
}
