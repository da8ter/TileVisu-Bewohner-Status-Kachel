<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
check(defined('VM_CHANGEDLOCKED') === (getenv('RESIDENT_TEST_NO_LOCK_MESSAGE') !== '1'), 'Requested SDK constant availability is active');
resident(10); resident(20, 'Arbeit');
$m = register(new TileVisuresidencystatustile()); $m->Create();
$m->properties['Residents'] = residents(['Variable' => 10, 'AdditionalInfo' => 20]);
$runlevel = 0; $m->ApplyChanges();
check($m->updates === [] && isset($m->messages[0]), 'Initialization waits for kernel');
$runlevel = KR_READY; $m->MessageSink(0, 0, IPS_KERNELSTARTED, []);
$initialSize = strlen(end($m->updates));
check(in_array(10606, $m->messages[10], true) === defined('VM_CHANGEDLOCKED'), 'Optional lock notification subscribed only when available');
check(latest($m)['Bewohner1'] && latest($m)['operable1'], 'Valid resident is visible and operable');
$m->properties['Schriftgroesse'] = 20; $m->ApplyChanges();
check(!isset(latest($m)['bgimage']) && !isset(latest($m)['image1']), 'Style update omits unchanged images');
check(isset(snapshot($m)['image1'], snapshot($m)['bgimage']), 'New clients receive complete images after deltas');
echo 'Payload initial/style-only: ' . $initialSize . '/' . strlen(end($m->updates)) . ' bytes' . PHP_EOL;
$m->properties['BG_Off'] = false; $m->ApplyChanges();
check(latest($m)['bgimage'] === '', 'Background removal is explicit');
$media[30] = ['MediaType' => MEDIATYPE_IMAGE, 'MediaFile' => 'portrait.WEBP', 'content' => base64_encode('first')];
setResident($m, 0, ['Image' => 30]);
check(str_starts_with(latest($m)['image1'], 'data:image/webp;base64,'), 'WebP supported case-insensitively');
check(in_array(MM_UPDATE, $m->messages[30], true), 'Media updates subscribed');
$media[30]['content'] = base64_encode('second'); $m->MessageSink(0,30,MM_UPDATE,[]);
check(latest($m)['image1'] === 'data:image/webp;base64,' . base64_encode('second'), 'Changed media content broadcast');
unset($media[30]); $m->MessageSink(0,30,OM_UNREGISTER,[]);
check(str_starts_with(latest($m)['image1'], 'data:image/png;base64,'), 'Deleted photo restores placeholder');
$m->RequestAction('Bewohner1', 1);
check($writes === [[10,false]], 'Plain variable toggles directly');
$variables[10]['ObjectIsReadOnly'] = true; $variables[10]['VariableAction'] = 123;
$m->RequestAction('Bewohner1', 1);
check($actions === [[10,true]] && count($writes) === 1, 'Read-only actionable variable uses action');
$variables[10]['VariableAction'] = 0; $m->RequestAction('Bewohner1', 1);
check(count($actions) === 1 && count($writes) === 1, 'Read-only sensor cannot be written');
$variables[10]['ObjectIsReadOnly'] = false; $variables[10]['VariableCustomAction'] = 1;
$m->RequestAction('Bewohner1', 1); check(count($writes) === 1, 'Explicitly disabled action is respected');
$variables[10]['VariableCustomAction'] = 0; $m->properties['BedienungSwitch'] = true;
$m->RequestAction('Bewohner1', 1); check(count($writes) === 1 && !snapshot($m)['operable1'], 'Global lock applies in backend and UI');
try { $m->RequestAction("Bewohner1\n", 1); throw new LogicException('Ident accepted'); }
catch (Exception $e) { check(str_starts_with($e->getMessage(),'Invalid ident:'), 'Trailing newline ident rejected'); }
$m->properties['BedienungSwitch'] = false; $variables[10]['value'] = true;
$m->MessageSink(0,10,VM_UPDATE,[]); check(latest($m) === ['value1'=>true], 'Status delta has no image payload');
$variables[20]['value'] = "\xB1"; $m->ApplyChanges();
check(latest($m)['info1'] === "\u{FFFD}", 'Invalid UTF-8 repaired in full state');
$m->MessageSink(0,20,VM_UPDATE,[]); check(latest($m)['info1'] === "\u{FFFD}", 'Invalid UTF-8 repaired in delta');
setResident($m, 0, ['AltName' => '</script><script>alert(1)</script>']);
$baseScripts = substr_count(file_get_contents(__DIR__ . '/../Bewohnerstatus/module.html'), '<script>');
check(substr_count($m->GetVisualizationTile(), '<script>') === $baseScripts + 1, 'Initial script cannot be escaped by a name');
$m->properties['Schriftgroesse'] = INF; $m->properties['ImageMaxWidth'] = 500;
setResident($m, 1, ['Variable' => 20]); check(!latest($m)['Bewohner2'] && latest($m)['Bewohner1'], 'Invalid resident does not break other residents');
check(latest($m)['fontsize'] === 10 && latest($m)['imageMaxWidth'] === 100, 'Numeric configuration is bounded');
check(str_contains($m->GetConfigurationForm(), 'Select an existing Boolean variable.'), 'Invalid configuration shown in form');
$media[30] = ['MediaType'=>1,'MediaFile'=>'photo.svg','content'=>base64_encode('unsupported')];
check(str_contains($m->GetConfigurationForm(), 'Select an image:'), 'Unsupported media explained in form');
$media[30]['MediaFile']='photo.png'; $media[30]['content']=str_repeat('A', 4 * (int)ceil(5*1024*1024/3)+4);
check(str_contains($m->GetConfigurationForm(), 'exceeds 5 MiB'), 'Oversized image explained in form');
check(strlen(snapshot($m)['image1']) < 10000, 'Oversized image replaced with placeholder');
setResident($m, 0, ['Image' => 0]);
check(!isset($m->messages[30], $m->references[30]), 'Obsolete media watches removed');
unset($variables[10]); $m->MessageSink(0,10,OM_UNREGISTER,[]);
check(!latest($m)['Bewohner1'] && latest($m)['image1'] === '', 'Deleted resident hidden and image cleared');

// Each source is below 5 MiB, but their combined base64 output exceeds the
// runtime buffer. Slash-heavy data also exercises nested JSON transport escaping.
$large = register(new TileVisuresidencystatustile()); $large->InstanceID = 12346; register($large);
$large->Create();
$large->properties['BG_Off'] = false;
$rows = [];
for ($i = 1; $i <= 5; $i++) {
    resident(100 + $i);
    $rows[] = ['Variable' => 100 + $i, 'Image' => 200 + $i];
    $media[200 + $i] = ['MediaType' => 1, 'MediaFile' => 'photo.jpg', 'content' => base64_encode(str_repeat("\xff", 700000))];
}
$large->properties['Residents'] = residents(...$rows);
$large->properties['bgImage'] = 206;
$media[206] = ['MediaType' => 1, 'MediaFile' => 'background.jpg', 'content' => base64_encode(str_repeat("\xff", 1500000))];
$large->ApplyChanges();
$state = snapshot($large);
$imageKeys = ['bgimage','image1','image2','image3','image4','image5'];
$sum = array_sum(array_map(static fn($key) => strlen($state[$key]), $imageKeys));
check($sum <= 2 * 1024 * 1024, 'Combined image budget respected');
$html = $large->GetVisualizationTile();
$transportSize = strlen(json_encode(['jsonrpc'=>'2.0','result'=>$html,'id'=>1], JSON_THROW_ON_ERROR));
check($transportSize < 5048576, 'HTML plus nested JSON transport below reported output limit');
check(strlen(json_encode(end($large->updates), JSON_THROW_ON_ERROR)) < 5048576, 'Full update plus transport below output limit');
check(str_contains($large->GetConfigurationForm(), 'Tile image budget exceeded'), 'Combined budget warning appears in configuration');
check($state['Bewohner1'] && $state['Bewohner5'], 'Large images do not hide resident status');
$large->ApplyChanges(); check(!isset(latest($large)['image1']), 'Budgeted images still use delta caching');
$media[201]['content'] = base64_encode('small replacement');
$large->MessageSink(0,201,MM_UPDATE,[]);
check(latest($large)['image1'] === 'data:image/jpeg;base64,' . base64_encode('small replacement'), 'Reducing an image restores it automatically');

// --- Bewohnerliste: beliebig viele Zeilen ---------------------------------
$list = register(new TileVisuresidencystatustile()); $list->InstanceID = 12347; register($list);
$list->Create();
$rows = [];
for ($i = 1; $i <= 12; $i++) { resident(300 + $i); $rows[] = ['Variable' => 300 + $i]; }
$list->properties['Residents'] = residents(...$rows);
$list->ApplyChanges();
$state = latest($list);
check($state['residents'] === 12, 'Payload carries the resident count');
check($state['Bewohner12'] && !array_key_exists('Bewohner13', $state), 'Twelve residents are served, no thirteenth');
check(!str_contains($list->GetVisualizationTile(), '<button'), 'Tile ships no fixed resident blocks');
$list->RequestAction('Bewohner12', 1);
check(end($writes) === [312, false], 'Twelfth resident is operable by ident');
foreach (['13', '0', '01', '', ' 1'] as $suffix) {
    try { $list->RequestAction('Bewohner' . $suffix, 1); throw new LogicException('Ident accepted: ' . $suffix); }
    catch (Exception $e) { check(str_starts_with($e->getMessage(), 'Invalid ident:'), 'Ident outside the list rejected: "Bewohner' . $suffix . '"'); }
}
$list->properties['Residents'] = residents(['Variable' => 301]);
$list->ApplyChanges();
check(latest($list)['residents'] === 1 && !isset($list->references[312]), 'Shrinking the list drops residents and their watches');
$list->properties['Residents'] = '[]'; $list->ApplyChanges();
check(latest($list)['residents'] === 0, 'An empty list is a valid configuration');
$list->properties['Residents'] = 'kein json'; $list->ApplyChanges();
check(latest($list)['residents'] === 0, 'Broken list data does not break the tile');

$form = json_decode($list->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$lists = [];
array_walk_recursive($form, static function ($value, $key) use (&$lists) { if ($key === 'type' && $value === 'List') $lists[] = $value; });
check($lists === ['List'], 'Configuration offers exactly one resident list');
check(!str_contains($list->GetConfigurationForm(), '"repeat"') && !str_contains($list->GetConfigurationForm(), '{i}'), 'No template leftovers in the form');

// --- Übernahme alter Installationen ---------------------------------------
$old = new TileVisuresidencystatustile(); $old->InstanceID = 12348; register($old); $old->Create();
resident(401); resident(402); resident(403, 'Arbeit');
$media[404] = ['MediaType' => MEDIATYPE_IMAGE, 'MediaFile' => 'foto.png', 'content' => base64_encode('bild')];
$old->properties['Bewohner1'] = 401;
$old->properties['Bewohner3'] = 402;
$old->properties['Bewohner3AltName'] = 'Papa';
$old->properties['Bewohner3Image'] = 404;
$old->properties['AdditionalInfo3'] = 403;
$old->ApplyChanges();
$imported = json_decode($old->properties['Residents'], true, 512, JSON_THROW_ON_ERROR);
check(count($imported) === 2, 'Only configured slots are imported');
check($imported[0]['Variable'] === 401 && $imported[0]['AltName'] === '', 'First slot keeps its variable');
check($imported[1] === ['Variable' => 402, 'AdditionalInfo' => 403, 'Image' => 404, 'AltName' => 'Papa'],
    'Gap is closed and every field carried over');
check($old->properties['Bewohner1'] === 0 && $old->properties['Bewohner3AltName'] === '', 'Legacy slots are cleared after the import');
check($old->attributes['LegacyImported'], 'Import is recorded');
check(latest($old)['residents'] === 2 && latest($old)['name2'] === 'Papa', 'Imported residents reach the tile at once');
$old->properties['Bewohner1'] = 401; $old->ApplyChanges();
check(json_decode($old->properties['Residents'], true) === $imported && $old->properties['Bewohner1'] === 401,
    'A second run never imports again');

$fresh = new TileVisuresidencystatustile(); $fresh->InstanceID = 12349; register($fresh); $fresh->Create();
$fresh->ApplyChanges();
check($fresh->properties['Residents'] === '[]' && $fresh->attributes['LegacyImported'], 'A new installation has nothing to import');
$fresh->properties['Residents'] = residents(['Variable' => 401]); $fresh->ApplyChanges();
$fresh->properties['Residents'] = '[]'; $fresh->ApplyChanges();
check($fresh->properties['Residents'] === '[]', 'Emptying the list is not undone by the import');

$both = new TileVisuresidencystatustile(); $both->InstanceID = 12350; register($both); $both->Create();
$both->properties['Bewohner1'] = 401;
$both->properties['Residents'] = residents(['Variable' => 402]);
$both->ApplyChanges();
check(json_decode($both->properties['Residents'], true)[0]['Variable'] === 402 && $both->properties['Bewohner1'] === 401,
    'An existing list wins and legacy data stays untouched');

check(!array_key_exists('DebugOutline', snapshot($m)), 'Debug outline is gone from the payload');
$m->properties['GraustufenSwitch'] = true; $m->properties['Abwesenheitstransparenz'] = 50; $m->ApplyChanges();
check(latest($m)['graustufen'] === 100 && (float) latest($m)['abwesenheitstransparenz'] === 0.5, 'Absent styling defaults to grayscale at half opacity');
$m->properties['GraustufenSwitch'] = false; $m->properties['Abwesenheitstransparenz'] = 20; $m->ApplyChanges();
check(latest($m)['graustufen'] === 0 && (float) latest($m)['abwesenheitstransparenz'] === 0.2, 'Absent styling follows the configuration');
$m->properties['Abwesenheitstransparenz'] = 500; $m->ApplyChanges();
// json_encode macht aus 1.0 ein int; hier zaehlt der Wert, nicht der PHP-Typ.
check((float) latest($m)['abwesenheitstransparenz'] === 1.0, 'Absent opacity is bounded');

echo 'Worst-case image fixture transport: ' . $transportSize . ' bytes' . PHP_EOL;
