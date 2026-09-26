<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
if (!function_exists('imagecreatetruecolor')) {
    echo "SKIP: real image tests require GD; use module_test.php for fallback coverage\n";
    exit;
}
function pngFixture(int $width, int $height, bool $transparent = false): string {
    $im = imagecreatetruecolor($width, $height);
    imagealphablending($im, false); imagesavealpha($im, true);
    $color = imagecolorallocatealpha($im, 30, 100, 180, $transparent ? 127 : 0);
    imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $color);
    $stream = fopen('php://temp', 'w+b'); imagepng($im, $stream);
    rewind($stream); $bytes = stream_get_contents($stream); fclose($stream);
    return base64_encode($bytes);
}
function thumbnailBytes(array $state): string {
    return base64_decode(explode(',', $state['image1'], 2)[1], true);
}
resident(10);
$media[30] = ['MediaType'=>1, 'MediaFile'=>'portrait.png', 'content'=>pngFixture(1600,800,true)];
$original = $media[30]['content'];
$m = register(new TileVisuresidencystatustile()); $m->Create();
$m->properties['Residents'] = residents(['Variable' => 10, 'Image' => 30]);
$m->ApplyChanges();
$bytes = thumbnailBytes(latest($m)); $size = getimagesizefromstring($bytes);
check($size[0] === 512 && $size[1] === 256, 'Large portrait resized preserving aspect ratio');
check(strlen($bytes) <= 128*1024, 'Thumbnail stays below 128 KiB');
$decoded = imagecreatefromstring($bytes);
$rgba = imagecolorsforindex($decoded, imagecolorat($decoded,0,0));
check($rgba['alpha'] === 127, 'Transparency survives conversion');
check($media[30]['content'] === $original, 'Original media unchanged');
$cache = $m->buffers['Thumbnail30'];
$keys = static fn (IPSModuleStrict $m): array => array_map('strval', json_decode($m->buffers['ThumbnailKeys'], true));
check($keys($m) === ['30'], 'Thumbnails are cached per media ID');
check(max(array_map('strlen', $m->buffers)) < 256 * 1024, 'Every buffer stays below the 256 kB warning threshold of Symcon');
$m->ApplyChanges();
check($cache === $m->buffers['Thumbnail30'] && !isset(latest($m)['image1']), 'Cached thumbnail reused with delta updates');
$media[30]['content'] = pngFixture(60,120);
$m->MessageSink(0,30,MM_UPDATE,[]);
$size = getimagesizefromstring(thumbnailBytes(latest($m)));
check($size[0] === 60 && $size[1] === 120, 'Small image is not enlarged');
check($cache !== $m->buffers['Thumbnail30'], 'Media content update invalidates thumbnail cache');
$m->properties['BG_Off'] = false; $m->properties['bgImage'] = 30;
check(snapshot($m)['bgimage'] === 'data:image/png;base64,' . $media[30]['content'], 'Background is not resized');
$media[30]['content'] = pngFixture(1600,800);
$rows = [];
for ($i = 1; $i <= 5; $i++) { $rows[] = ['Variable' => 10, 'Image' => 30]; }
$m->properties['Residents'] = residents(...$rows);
$m->properties['bgImage']=0; $m->ApplyChanges();
check(strlen($m->GetVisualizationTile()) < 100000, 'Five resized photos fit comfortably in the tile');
check(!str_contains($m->GetConfigurationForm(), 'Tile image budget exceeded'), 'Resized photos no longer trigger budget replacement');
echo 'Five-photo HTML: ' . strlen($m->GetVisualizationTile()) . " bytes\n";

// Entfernte Bewohner duerfen den Miniaturen-Puffer nicht weiterwachsen lassen.
$media[31] = ['MediaType'=>1, 'MediaFile'=>'zweites.png', 'content'=>pngFixture(400,400)];
$m->properties['Residents'] = residents(['Variable' => 10, 'Image' => 30], ['Variable' => 10, 'Image' => 31]);
$m->ApplyChanges();
check($keys($m) === ['30', '31'], 'Every configured photo is cached');
check(max(array_map('strlen', $m->buffers)) < 256 * 1024, 'Two cached photos still keep every buffer below 256 kB');
$m->properties['Residents'] = residents(['Variable' => 10, 'Image' => 31]);
$m->ApplyChanges();
check($keys($m) === ['31'] && ($m->buffers['Thumbnail30'] ?? '') === '', 'Removing a resident prunes its thumbnail');
$m->buffers['Thumbnails'] = '{"30":{"hash":"alt"}}'; $m->ApplyChanges();
check($m->buffers['Thumbnails'] === '', 'Shared buffer of the previous version is cleared');
