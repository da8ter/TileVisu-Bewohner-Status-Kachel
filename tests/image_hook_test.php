<?php

declare(strict_types=1);

// Bilder ueber den eigenen Hook statt als Data-URI im Kacheldokument (Stufe 2 „Kachel-Visu schneller“).
require __DIR__ . '/bootstrap.php';
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Kopfzeilen und Status abfangen, Hook ohne Webserver aufrufen.
class HookProbeTile extends TileVisuresidencystatustile
{
    public array $sent = [];
    public int $status = 200;
    protected function SendHeader(string $header): void { $this->sent[] = $header; }
    protected function SendStatus(int $code): void { $this->status = $code; }
    public function hook(array $get, array $server = []): string
    {
        $_GET = $get;
        $_SERVER['HTTP_IF_NONE_MATCH'] = $server['HTTP_IF_NONE_MATCH'] ?? '';
        $this->sent = [];
        $this->status = 200;
        ob_start();
        $this->ProcessHookData();
        return (string) ob_get_clean();
    }
}
function query(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
    return $q;
}

$hookAvailable = true;
resident(10); resident(11);
$media[30] = ['MediaType' => MEDIATYPE_IMAGE, 'MediaFile' => 'portrait.webp', 'content' => base64_encode('portrait-bytes')];
$m = register(new HookProbeTile()); $m->Create();
$m->properties['Residents'] = residents(['Variable' => 10, 'Image' => 30], ['Variable' => 11]);
$m->ApplyChanges();
check($m->hooks === ['residentimages/12345'], 'Hook is registered natively in Create');
$token = $m->attributes['ImageHookToken'];
check(strlen($token) === 32 && ctype_xdigit($token), 'Hook token is created');

$state = snapshot($m);
$prefix = '/hook/residentimages/12345?k=';
check(str_starts_with($state['image1'], $prefix . 'image1&v=') && str_ends_with($state['image1'], '&t=' . $token), 'Resident photo is sent as hook address');
check(str_starts_with($state['image2'], $prefix . 'image2&v='), 'Placeholder is sent as hook address');
check(str_starts_with($state['bgimage'], $prefix . 'bgimage&v='), 'Default background is sent as hook address');
check(!str_contains($m->GetVisualizationTile(), 'base64,'), 'Tile document carries no Base64 image');

$q = query($state['image1']);
check($m->hook($q) === 'portrait-bytes' && $m->status === 200, 'Hook delivers the photo bytes');
check(in_array('Content-Type: image/webp', $m->sent, true), 'Hook sends the image type');
check(in_array('Cache-Control: private, max-age=31536000, immutable', $m->sent, true), 'Current version may be cached long (private)');
check(in_array('ETag: "' . $q['v'] . '"', $m->sent, true), 'ETag is the version from the address');
$body = $m->hook($q, ['HTTP_IF_NONE_MATCH' => '"' . $q['v'] . '"']);
check($m->status === 304 && $body === '', 'Matching If-None-Match answers 304 without body');
$bg = query($state['bgimage']);
$png = $m->hook($bg);
check(str_starts_with($png, "\x89PNG") && in_array('Content-Type: image/png', $m->sent, true), 'Background is delivered as PNG');

$m->hook(['k' => 'image1', 'v' => $q['v'], 't' => str_repeat('0', 32)]);
check($m->status === 403, 'Wrong token is rejected');
$m->hook(['k' => 'image1', 'v' => $q['v']]);
check($m->status === 403, 'Missing token is rejected');
$m->hook(['k' => 'image3', 't' => $token]);
check($m->status === 404, 'Image key outside the resident list is rejected');
$m->hook(['k' => 'Bewohner1', 't' => $token]);
check($m->status === 404, 'Non-image key is rejected');
$m->hook(['k' => 'image1', 'v' => 'alt', 't' => $token]);
check($m->status === 200 && in_array('Cache-Control: no-cache', $m->sent, true), 'Outdated address gets current content uncached');

$media[30]['content'] = base64_encode('new-portrait');
$m->MessageSink(0, 30, MM_UPDATE, []);
$changed = latest($m)['image1'];
check($changed !== $state['image1'] && str_starts_with($changed, $prefix . 'image1&v='), 'Changed photo gets a new address');
check($m->hook(query($changed)) === 'new-portrait', 'New address delivers the new photo');
$m->properties['Schriftgroesse'] = 12; $m->ApplyChanges();
check(!isset(latest($m)['image1']) && !isset(latest($m)['bgimage']), 'Style update omits unchanged image addresses');

$options['ScriptOutputBufferLimit'] = 4096;
$media[30]['content'] = base64_encode(str_repeat('x', 8000));
$m->MessageSink(0, 30, MM_UPDATE, []);
check(str_starts_with(latest($m)['image1'], 'data:image/webp;base64,'), 'Image above the hook output limit stays embedded');
unset($options['ScriptOutputBufferLimit']);

$hookAvailable = false;
$n = new HookProbeTile(); $n->InstanceID = 12346; register($n); $n->Create();
$n->properties['Residents'] = residents(['Variable' => 10, 'Image' => 30]);
$n->ApplyChanges();
check(str_starts_with(snapshot($n)['image1'], 'data:image/webp;base64,'), 'Without registered hook images stay embedded as before');
check(($n->attributes['ImageHookToken'] ?? '') === '', 'Without hook no token is created');
$n->hook(['k' => 'image1', 't' => '']);
check($n->status === 403, 'Without hook the endpoint answers nothing');
