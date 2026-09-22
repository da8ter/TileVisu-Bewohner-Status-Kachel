<?php

declare(strict_types=1);

// Isolated SDK double; never connect to a running Symcon installation.
const KR_READY = 10103, IPS_KERNELSTARTED = 10001, OM_CHANGENAME = 10404,
    OM_UNREGISTER = 10402, OM_CHANGETYPE = 10406, OM_CHANGEREADONLY = 10409,
    OM_CHANGEDISABLED = 10415, VM_UPDATE = 10603, VM_CHANGEPROFILEACTION = 10605,
    MM_CHANGEFILE = 10903, MM_AVAILABLE = 10904,
    MM_UPDATE = 10905, MEDIATYPE_IMAGE = 1, KL_ERROR = 10205, KL_WARNING = 10204,
    KL_NOTIFY = 10201;

// Some Symcon runtimes do not expose this documented optional notification.
if (getenv('RESIDENT_TEST_NO_LOCK_MESSAGE') !== '1') {
    define('VM_CHANGEDLOCKED', 10606);
}

class IPSModuleStrict
{
    public array $properties = [], $messages = [], $references = [], $updates = [], $logs = [], $buffers = [], $attributes = [];
    public int $InstanceID = 12345;
    public function Create(): void {}
    public function ApplyChanges(): void {}
    protected function RegisterPropertyInteger(string $k, int $v): void { $this->properties[$k] = $v; }
    protected function RegisterPropertyString(string $k, string $v): void { $this->properties[$k] = $v; }
    protected function RegisterPropertyBoolean(string $k, bool $v): void { $this->properties[$k] = $v; }
    protected function RegisterPropertyFloat(string $k, float $v): void { $this->properties[$k] = $v; }
    protected function ReadPropertyInteger(string $k): int { return $this->properties[$k]; }
    protected function ReadPropertyString(string $k): string { return $this->properties[$k]; }
    protected function ReadPropertyBoolean(string $k): bool { return $this->properties[$k]; }
    protected function ReadPropertyFloat(string $k): float { return $this->properties[$k]; }
    protected function RegisterAttributeBoolean(string $k, bool $v): void { $this->attributes[$k] = $v; }
    protected function ReadAttributeBoolean(string $k): bool { return $this->attributes[$k]; }
    protected function WriteAttributeBoolean(string $k, bool $v): void { $this->attributes[$k] = $v; }
    protected function SetVisualizationType(int $type): void {}
    protected function UpdateVisualizationValue(string $value): void { $this->updates[] = $value; }
    protected function GetReferenceList(): array { return array_keys($this->references); }
    protected function GetMessageList(): array { return $this->messages; }
    protected function RegisterReference(int $id): void { $this->references[$id] = true; }
    protected function UnregisterReference(int $id): void { unset($this->references[$id]); }
    protected function RegisterMessage(int $id, int $message): void {
        if (!in_array($message, $this->messages[$id] ?? [], true)) $this->messages[$id][] = $message;
    }
    protected function UnregisterMessage(int $id, int $message): void {
        if (!isset($this->messages[$id])) return;
        $this->messages[$id] = array_values(array_diff($this->messages[$id], [$message]));
        if ($this->messages[$id] === []) unset($this->messages[$id]);
    }
    protected function GetBuffer(string $key): string { return $this->buffers[$key] ?? ''; }
    protected function SetBuffer(string $key, string $value): void { $this->buffers[$key] = $value; }
    protected function LogMessage(string $message, int $severity): void { $this->logs[] = $message; }
    protected function SendDebug(string $name, string $data, int $format): void {}
    protected function Translate(string $text): string { return $text; }
}

$variables = $media = $actions = $writes = $instances = [];
$runlevel = KR_READY;
function resident(int $id, mixed $value = true, array $overrides = []): void {
    $GLOBALS['variables'][$id] = array_replace([
        'VariableType' => is_bool($value) ? 0 : 3, 'VariableIsLocked' => false,
        'VariableAction' => 0, 'VariableCustomAction' => 0, 'ObjectIsReadOnly' => false,
        'ObjectIsDisabled' => false, 'name' => 'Resident ' . $id, 'value' => $value,
    ], $overrides);
}
function IPS_GetKernelRunlevel(): int { return $GLOBALS['runlevel']; }
// Symcon merkt Properties vor und aktiviert sie mit ApplyChanges; die Attrappe
// setzt sie sofort — fuer den Ablauf der Uebernahme ist das gleichwertig.
function IPS_SetProperty(int $id, string $key, mixed $value): bool {
    $module = $GLOBALS['instances'][$id] ?? throw new RuntimeException('Unknown instance ' . $id);
    $changed = ($module->properties[$key] ?? null) !== $value;
    $module->properties[$key] = $value;
    return $changed;
}
function IPS_ApplyChanges(int $id): bool {
    ($GLOBALS['instances'][$id] ?? throw new RuntimeException('Unknown instance ' . $id))->ApplyChanges();
    return true;
}
function register(IPSModuleStrict $module): IPSModuleStrict {
    $GLOBALS['instances'][$module->InstanceID] = $module;
    return $module;
}
// Bewohnerliste wie das Formular sie speichert.
function residents(array ...$rows): string {
    return json_encode(array_map(static fn (array $r): array => $r + [
        'Variable' => 0, 'AdditionalInfo' => 0, 'Image' => 0, 'AltName' => '',
    ], $rows));
}
function IPS_VariableExists(int $id): bool { return isset($GLOBALS['variables'][$id]); }
function IPS_MediaExists(int $id): bool { return isset($GLOBALS['media'][$id]); }
function IPS_ObjectExists(int $id): bool { return IPS_VariableExists($id) || IPS_MediaExists($id); }
function IPS_GetVariable(int $id): array { return $GLOBALS['variables'][$id]; }
function IPS_GetObject(int $id): array { return $GLOBALS['variables'][$id]; }
function IPS_GetMedia(int $id): array { return $GLOBALS['media'][$id]; }
function IPS_GetMediaContent(int $id): string { return $GLOBALS['media'][$id]['content']; }
function IPS_GetName(int $id): string { return $GLOBALS['variables'][$id]['name']; }
function GetValueBoolean(int $id): bool {
    if (IPS_GetVariable($id)['VariableType'] !== 0) throw new RuntimeException('Not Boolean');
    return $GLOBALS['variables'][$id]['value'];
}
function GetValueFormatted(int $id): string { return (string)$GLOBALS['variables'][$id]['value']; }
function HasAction(int $id): bool {
    $v = IPS_GetVariable($id);
    return $v['VariableCustomAction'] !== 1 && ($v['VariableAction'] > 0 || $v['VariableCustomAction'] > 1);
}
function RequestAction(int $id, mixed $value): bool { $GLOBALS['actions'][] = [$id, $value]; return true; }
function SetValue(int $id, mixed $value): void {
    if (IPS_GetObject($id)['ObjectIsReadOnly']) throw new RuntimeException('Read only');
    $GLOBALS['writes'][] = [$id, $value]; $GLOBALS['variables'][$id]['value'] = $value;
}
// Eine Zeile der Bewohnerliste aendern, so wie es das Formular taete.
function setResident(IPSModuleStrict $module, int $index, array $changes): void {
    $rows = json_decode($module->properties['Residents'], true);
    $rows = is_array($rows) ? $rows : [];
    $rows[$index] = array_replace(
        $rows[$index] ?? ['Variable' => 0, 'AdditionalInfo' => 0, 'Image' => 0, 'AltName' => ''],
        $changes
    );
    $module->properties['Residents'] = json_encode(array_values($rows));
    $module->ApplyChanges();
}

function check(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . PHP_EOL;
}
function latest(IPSModuleStrict $module): array { return json_decode(end($module->updates), true, 512, JSON_THROW_ON_ERROR); }
function snapshot(TileVisuresidencystatustile $module): array {
    $html = $module->GetVisualizationTile();
    $prefix = '<script>handleMessage(';
    $start = strrpos($html, $prefix);
    if ($start === false) throw new RuntimeException('Missing initial state');
    $start += strlen($prefix);
    $end = strpos($html, ');</script>', $start);
    if ($end === false) throw new RuntimeException('Incomplete initial state');
    return json_decode(json_decode(substr($html, $start, $end - $start), true, 512, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
}
require __DIR__ . '/../Bewohnerstatus/module.php';
