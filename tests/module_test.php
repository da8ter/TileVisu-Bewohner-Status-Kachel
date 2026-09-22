<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
check(defined('VM_CHANGEDLOCKED') === (getenv('RESIDENT_TEST_NO_LOCK_MESSAGE') !== '1'), 'Requested SDK constant availability is active');
resident(10); resident(20, 'Arbeit');
$m = new TileVisuresidencystatustile(); $m->Create();
$m->properties['Bewohner1'] = 10; $m->properties['AdditionalInfo1'] = 20;
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
$m->properties['Bewohner1Image'] = 30; $m->ApplyChanges();
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
$m->properties['Bewohner1AltName'] = '</script><script>alert(1)</script>';
$baseScripts = substr_count(file_get_contents(__DIR__ . '/../Bewohnerstatus/module.html'), '<script>');
check(substr_count($m->GetVisualizationTile(), '<script>') === $baseScripts + 1, 'Initial script cannot be escaped by a name');
$m->properties['Bewohner2'] = 20; $m->properties['Schriftgroesse'] = INF; $m->properties['ImageMaxWidth'] = 500;
$m->ApplyChanges(); check(!latest($m)['Bewohner2'] && latest($m)['Bewohner1'], 'Invalid resident does not break other residents');
check(latest($m)['fontsize'] === 10 && latest($m)['imageMaxWidth'] === 100, 'Numeric configuration is bounded');
check(str_contains($m->GetConfigurationForm(), 'Select an existing Boolean variable.'), 'Invalid configuration shown in form');
$media[30] = ['MediaType'=>1,'MediaFile'=>'photo.svg','content'=>base64_encode('unsupported')];
check(str_contains($m->GetConfigurationForm(), 'Select an image:'), 'Unsupported media explained in form');
$media[30]['MediaFile']='photo.png'; $media[30]['content']=str_repeat('A', 4 * (int)ceil(5*1024*1024/3)+4);
check(str_contains($m->GetConfigurationForm(), 'exceeds 5 MiB'), 'Oversized image explained in form');
check(strlen(snapshot($m)['image1']) < 10000, 'Oversized image replaced with placeholder');
$m->properties['Bewohner1Image']=0; $m->ApplyChanges();
check(!isset($m->messages[30], $m->references[30]), 'Obsolete media watches removed');
unset($variables[10]); $m->MessageSink(0,10,OM_UNREGISTER,[]);
check(!latest($m)['Bewohner1'] && latest($m)['image1'] === '', 'Deleted resident hidden and image cleared');

// Each source is below 5 MiB, but their combined base64 output exceeds the
// runtime buffer. Slash-heavy data also exercises nested JSON transport escaping.
$large = new TileVisuresidencystatustile(); $large->Create();
$large->properties['BG_Off'] = false;
for ($i = 1; $i <= 5; $i++) {
    resident(100 + $i);
    $large->properties['Bewohner' . $i] = 100 + $i;
    $large->properties['Bewohner' . $i . 'Image'] = 200 + $i;
    $media[200 + $i] = ['MediaType' => 1, 'MediaFile' => 'photo.jpg', 'content' => base64_encode(str_repeat("\xff", 700000))];
}
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

// --- Bewohnerzahl, Vorlage und Darstellung bei Abwesenheit -----------------
$reflection = new ReflectionClass(TileVisuresidencystatustile::class);
$count = $reflection->getConstant('RESIDENT_COUNT');
$tile = $m->GetVisualizationTile();
check(substr_count($tile, '<button type="button"') === $count, 'Tile renders exactly RESIDENT_COUNT residents');
check(!str_contains($tile, '{i}') && !str_contains($tile, '<!--BEWOHNER-->'), 'Resident template markers are consumed');
check(str_contains($tile, 'id="Bewohner' . $count . '"') && !str_contains($tile, 'id="Bewohner' . ($count + 1) . '"'),
    'Slot numbering follows RESIDENT_COUNT');
foreach ([(string)($count + 1), '0', '01', '', ' 1'] as $suffix) {
    try { $m->RequestAction('Bewohner' . $suffix, 1); throw new LogicException('Ident accepted: ' . $suffix); }
    catch (Exception $e) { check(str_starts_with($e->getMessage(), 'Invalid ident:'), 'Ident out of range rejected: "Bewohner' . $suffix . '"'); }
}
$form = json_decode($m->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$names = [];
array_walk_recursive($form, static function ($value, $key) use (&$names) { if ($key === 'name') $names[] = $value; });
for ($i = 1; $i <= $count; $i++) {
    check(in_array('Bewohner' . $i, $names, true) && in_array('Bewohner' . $i . 'AltName', $names, true), 'Configuration row exists for resident ' . $i);
}
check(!in_array('Bewohner' . ($count + 1), $names, true), 'Configuration stops at RESIDENT_COUNT');
check(!str_contains($m->GetConfigurationForm(), '{i}') && !str_contains($m->GetConfigurationForm(), '"repeat"'), 'Form template markers are consumed');
check(!array_key_exists('DebugOutline', snapshot($m)), 'Debug outline is gone from the payload');
$m->properties['GraustufenSwitch'] = true; $m->properties['Abwesenheitstransparenz'] = 50; $m->ApplyChanges();
check(latest($m)['graustufen'] === 100 && (float) latest($m)['abwesenheitstransparenz'] === 0.5, 'Absent styling defaults to grayscale at half opacity');
$m->properties['GraustufenSwitch'] = false; $m->properties['Abwesenheitstransparenz'] = 20; $m->ApplyChanges();
check(latest($m)['graustufen'] === 0 && (float) latest($m)['abwesenheitstransparenz'] === 0.2, 'Absent styling follows the configuration');
$m->properties['Abwesenheitstransparenz'] = 500; $m->ApplyChanges();
// json_encode macht aus 1.0 ein int; hier zaehlt der Wert, nicht der PHP-Typ.
check((float) latest($m)['abwesenheitstransparenz'] === 1.0, 'Absent opacity is bounded');

echo 'Worst-case image fixture transport: ' . $transportSize . ' bytes' . PHP_EOL;
