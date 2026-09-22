<?php

declare(strict_types=1);

class TileVisuresidencystatustile extends IPSModuleStrict
{
    // Nur noch fuer die Uebernahme alter Installationen: so viele feste
    // Bewohner-Properties gab es vor der Liste.
    private const LEGACY_SLOTS = 5;
    private const RESIDENT_REFRESH_MESSAGES = [
        'OM_UNREGISTER', 'OM_CHANGETYPE', 'VM_CHANGEPROFILEACTION',
        'VM_CHANGEDLOCKED', 'OM_CHANGEDISABLED', 'OM_CHANGEREADONLY'
    ];
    private const MEDIA_REFRESH_MESSAGES = ['MM_UPDATE', 'MM_CHANGEFILE', 'MM_AVAILABLE', 'OM_UNREGISTER'];
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    private const THUMBNAIL_EDGE = 512;
    private const MAX_THUMBNAIL_BYTES = 128 * 1024;
    private const MAX_DECODE_PIXELS = 24000000;
    // Includes base64 expansion across all images, leaving transport headroom.
    private const MAX_TILE_IMAGE_BYTES = 2 * 1024 * 1024;
    private const IMAGE_TYPES = [
        'bmp' => 'image/bmp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'png' => 'image/png', 'ico' => 'image/x-icon',
        'webp' => 'image/webp'
    ];

    private ?string $placeholder = null;
    private ?array $residents = null;
    private ?array $thumbnails = null;
    private array $thumbnailsUsed = [];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Residents', '[]');
        // Die alten Einzel-Properties bleiben registriert, sonst laesst sich eine
        // bestehende Installation nicht mehr auslesen. Sie stehen in keinem
        // Formular mehr und werden bei der Uebernahme geleert.
        for ($i = 1; $i <= self::LEGACY_SLOTS; $i++) {
            $this->RegisterPropertyInteger('Bewohner' . $i, 0);
            $this->RegisterPropertyInteger('AdditionalInfo' . $i, 0);
            $this->RegisterPropertyInteger('Bewohner' . $i . 'Image', 0);
            $this->RegisterPropertyString('Bewohner' . $i . 'AltName', '');
        }
        $this->RegisterAttributeBoolean('LegacyImported', false);
        $this->RegisterPropertyFloat('Schriftgroesse', 10);
        $this->RegisterPropertyFloat('InfoSchriftgroesse', 8);
        $this->RegisterPropertyFloat('Eckenradius', 50);
        $this->RegisterPropertyBoolean('BG_Off', true);
        $this->RegisterPropertyInteger('bgImage', 0);
        $this->RegisterPropertyFloat('Bildtransparenz', 0.7);
        $this->RegisterPropertyInteger('Kachelhintergrundfarbe', -1);
        $this->RegisterPropertyBoolean('NameSwitch', true);
        $this->RegisterPropertyBoolean('GraustufenSwitch', true);
        $this->RegisterPropertyInteger('Abwesenheitstransparenz', 50);
        $this->RegisterPropertyBoolean('BedienungSwitch', false);
        $this->RegisterPropertyInteger('ImageMaxWidth', 80); // Maximale Bildbreite in %

        // HTML-SDK-Darstellung
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->residents = null;

        // Kein Heavy Work vor KR_READY: Referenzen, Messages und Fremdvariablen-Zugriffe
        // erst, wenn der Kernel bereit ist.
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        // Uebernahme alter Installationen. Schreibt sie Properties, ruft sie
        // IPS_ApplyChanges und dieser Durchlauf endet hier.
        if ($this->ImportLegacyResidents()) {
            return;
        }

        $this->RegisterWatchedObjects();

        // Einstellungen aktualisieren; unveränderte Bilddaten werden nicht erneut gesendet.
        $this->SendState();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
            return;
        }

        if (in_array($Message, $this->SupportedMessages(array_merge(self::MEDIA_REFRESH_MESSAGES, self::RESIDENT_REFRESH_MESSAGES)), true)) {
            $this->SendState();
            return;
        }

        $update = [];
        foreach ($this->Residents() as $index => $resident) {
            $i = $index + 1;
            if ($SenderID === $resident['Variable'] && $this->IsResidentVariable($SenderID)) {
                if ($Message === OM_CHANGENAME && $resident['AltName'] === '') {
                    $update['name' . $i] = IPS_GetName($SenderID);
                } elseif ($Message === VM_UPDATE) {
                    $update['value' . $i] = GetValueBoolean($SenderID);
                }
            }
            if ($Message === VM_UPDATE && $SenderID === $resident['AdditionalInfo']
                && IPS_VariableExists($SenderID)) {
                $update['info' . $i] = GetValueFormatted($SenderID);
            }
        }
        if ($update !== []) {
            $this->UpdateVisualizationValue($this->EncodeJSON($update));
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        // Die Darstellung bedient ausschließlich die Bewohner-Status-Variablen —
        // alle anderen Idents werden an der Systemgrenze abgewiesen.
        $slot = $this->ResidentSlot($Ident, 'Bewohner');
        if ($slot === 0) {
            throw new Exception('Invalid ident: ' . $Ident);
        }

        if ($this->ReadPropertyBoolean('BedienungSwitch')) {
            return; // Bedienung ist gesperrt
        }

        $variableID = $this->Residents()[$slot - 1]['Variable'];
        if (!$this->CanOperate($variableID)) {
            $this->SendDebug('RequestAction', 'Resident variable is invalid or not operable', 0);
            return;
        }

        $target = !GetValueBoolean($variableID);
        if (HasAction($variableID)) {
            if (!\RequestAction($variableID, $target)) {
                $this->LogMessage('Resident action failed for variable ' . $variableID, KL_WARNING);
            }
        } else {
            SetValue($variableID, $target);
        }
    }

    public function GetVisualizationTile(): string
    {
        $module = file_get_contents(__DIR__ . '/module.html');
        if ($module === false) {
            $this->LogMessage('module.html could not be loaded', KL_ERROR);
            return '';
        }

        // Initiale Werte analog zu Laufzeit-Updates setzen. Das doppelte json_encode ist
        // beabsichtigt: es liefert den JSON-String als korrekt escaptes JS-Stringliteral.
        // Wichtig: $initialHandling nach dem HTML, da handleMessage dort erst definiert wird.
        $initialHandling = '<script>handleMessage(' . $this->EncodeJSON($this->EncodeJSON($this->GetFullUpdateData())) . ');</script>';

        return $module . $initialHandling;
    }

    // Einzige Quelle fuer die Bewohner: die Liste aus der Konfiguration.
    private function Residents(): array
    {
        if ($this->residents !== null) {
            return $this->residents;
        }
        $rows = json_decode($this->ReadPropertyString('Residents'), true);
        $this->residents = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $this->residents[] = [
                'Variable'       => (int) ($row['Variable'] ?? 0),
                'AdditionalInfo' => (int) ($row['AdditionalInfo'] ?? 0),
                'Image'          => (int) ($row['Image'] ?? 0),
                'AltName'        => (string) ($row['AltName'] ?? ''),
            ];
        }
        return $this->residents;
    }

    // Uebernahme aus den fuenf festen Slots. Laeuft genau einmal je Instanz:
    // das Attribut wird vor dem Schreiben gesetzt, damit der durch
    // IPS_ApplyChanges ausgeloeste zweite Durchlauf nicht erneut uebernimmt.
    private function ImportLegacyResidents(): bool
    {
        if ($this->ReadAttributeBoolean('LegacyImported')) {
            return false;
        }
        $rows = [];
        for ($i = 1; $i <= self::LEGACY_SLOTS; $i++) {
            $row = [
                'Variable'       => $this->ReadPropertyInteger('Bewohner' . $i),
                'AdditionalInfo' => $this->ReadPropertyInteger('AdditionalInfo' . $i),
                'Image'          => $this->ReadPropertyInteger('Bewohner' . $i . 'Image'),
                'AltName'        => $this->ReadPropertyString('Bewohner' . $i . 'AltName'),
            ];
            if ($row['Variable'] !== 0 || $row['AdditionalInfo'] !== 0 || $row['Image'] !== 0 || $row['AltName'] !== '') {
                $rows[] = $row;
            }
        }
        $this->WriteAttributeBoolean('LegacyImported', true);
        if ($rows === [] || $this->Residents() !== []) {
            return false; // Neue Installation oder bereits gepflegte Liste
        }

        IPS_SetProperty($this->InstanceID, 'Residents', $this->EncodeJSON($rows));
        for ($i = 1; $i <= self::LEGACY_SLOTS; $i++) {
            IPS_SetProperty($this->InstanceID, 'Bewohner' . $i, 0);
            IPS_SetProperty($this->InstanceID, 'AdditionalInfo' . $i, 0);
            IPS_SetProperty($this->InstanceID, 'Bewohner' . $i . 'Image', 0);
            IPS_SetProperty($this->InstanceID, 'Bewohner' . $i . 'AltName', '');
        }
        $this->LogMessage(sprintf('Migrated %d residents from the fixed slots to the resident list', count($rows)), KL_NOTIFY);
        IPS_ApplyChanges($this->InstanceID);
        return true;
    }

    // Nummer aus Ident oder Datenschluessel gegen die Liste pruefen.
    private function ResidentSlot(string $key, string $prefix): int
    {
        if (!str_starts_with($key, $prefix)) {
            return 0;
        }
        $suffix = substr($key, strlen($prefix));
        if ($suffix === '' || $suffix !== (string) (int) $suffix) {
            return 0;
        }
        $slot = (int) $suffix;
        return $slot >= 1 && $slot <= count($this->Residents()) ? $slot : 0;
    }

    private function IsImageKey(string $key): bool
    {
        return $key === 'bgimage' || $this->ResidentSlot($key, 'image') > 0;
    }

    private function PlaceholderImage(): string
    {
        return $this->placeholder ??= $this->GetBase64ImageData(0, __DIR__ . '/assets/placeholder.png');
    }

    // Referenzen und Nachrichten-Abos passend zur aktuellen Konfiguration neu aufbauen
    private function SupportedMessages(array $names): array
    {
        // Optional SDK notifications differ between Symcon versions. Do not invent
        // numeric fallbacks: only subscribe to messages provided by this runtime.
        $messages = [];
        foreach ($names as $name) {
            if (defined($name)) {
                $messages[] = constant($name);
            }
        }
        return array_values(array_unique($messages));
    }

    private function RegisterWatchedObjects(): void
    {
        foreach ($this->GetReferenceList() as $ref) {
            $this->UnregisterReference($ref);
        }
        foreach ($this->GetMessageList() as $senderID => $messageIDs) {
            foreach ($messageIDs as $messageID) {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }

        // Dieselbe Variable darf in mehreren Zeilen der Liste stehen; Referenz
        // und Abo werden trotzdem nur einmal eingetragen.
        $seen = [];
        $register = function (int $id, array $messages) use (&$seen): void {
            if ($id <= 0 || !IPS_ObjectExists($id)) {
                return;
            }
            if (!isset($seen[$id])) {
                $this->RegisterReference($id);
                $seen[$id] = [];
            }
            foreach ($messages as $message) {
                if (!isset($seen[$id][$message])) {
                    $seen[$id][$message] = true;
                    $this->RegisterMessage($id, $message);
                }
            }
        };

        $mediaMessages = $this->SupportedMessages(self::MEDIA_REFRESH_MESSAGES);
        $residentMessages = array_merge([OM_CHANGENAME, VM_UPDATE], $this->SupportedMessages(self::RESIDENT_REFRESH_MESSAGES));
        $register($this->ReadPropertyInteger('bgImage'), $mediaMessages);
        foreach ($this->Residents() as $resident) {
            $register($resident['Variable'], $residentMessages);
            $register($resident['AdditionalInfo'], [VM_UPDATE, OM_UNREGISTER]);
            $register($resident['Image'], $mediaMessages);
        }
    }

    private function GetBase64ImageData(int $imageID, string $defaultImagePath = '', bool $resident = false): string
    {
        if (IPS_MediaExists($imageID)) {
            $image = IPS_GetMedia($imageID);
            if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                $mime = self::IMAGE_TYPES[strtolower(pathinfo($image['MediaFile'], PATHINFO_EXTENSION))] ?? '';
                if ($mime !== '') {
                    $content = IPS_GetMediaContent($imageID);
                    // Bound source size before optional resident thumbnail generation.
                    if ($content !== '' && strlen($content) <= 4 * (int)ceil(self::MAX_IMAGE_BYTES / 3)) {
                        if ($resident) {
                            $thumbnail = $this->ResidentThumbnail($content, $imageID);
                            if ($thumbnail !== '') {
                                return $thumbnail;
                            }
                        }
                        return 'data:' . $mime . ';base64,' . $content;
                    }
                    $this->SendDebug('Image', 'Empty or oversized image: ' . $imageID, 0);
                } else {
                    $this->SendDebug('Image', 'Unsupported image format: ' . $imageID, 0);
                }
            }
        }

        if ($defaultImagePath !== '' && file_exists($defaultImagePath)) {
            $content = file_get_contents($defaultImagePath);
            if ($content !== false) {
                return 'data:image/png;base64,' . base64_encode($content);
            }
        }

        return '';
    }

    // Nach Medien-ID zwischengespeichert, nicht nach Platznummer: beim
    // Umsortieren der Liste bleibt die Miniatur beim Bild. Ein geaenderter
    // Inhalt verwirft den Eintrag auch bei gleicher ID.
    private function ResidentThumbnail(string $base64, int $mediaID): string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            return ''; // GD is optional. The combined output budget remains enforced.
        }
        if ($this->thumbnails === null) {
            $cached = json_decode($this->GetBuffer('Thumbnails'), true);
            $this->thumbnails = is_array($cached) ? $cached : [];
        }
        $key = (string) $mediaID;
        $hash = hash('sha256', 'v1:' . $base64);
        $entry = $this->thumbnails[$key] ?? null;
        if (!is_array($entry) || ($entry['hash'] ?? '') !== $hash) {
            $entry = ['hash' => $hash, 'image' => $this->CreateThumbnail($base64)];
            $this->thumbnails[$key] = $entry;
        }
        $this->thumbnailsUsed[$key] = true;
        return $entry['image'];
    }

    // Nur die aktuell verwendeten Bilder behalten: bei beliebig vielen
    // Bewohnern darf der Puffer nicht mit jeder entfernten Zeile weiterwachsen.
    private function StoreThumbnails(): void
    {
        if ($this->thumbnails !== null) {
            $this->SetBuffer('Thumbnails', $this->EncodeJSON(array_intersect_key($this->thumbnails, $this->thumbnailsUsed)));
        }
    }

    private function CreateThumbnail(string $base64): string
    {
        $bytes = base64_decode($base64, true);
        if ($bytes === false) {
            return '';
        }
        // Convert GD warnings into a controlled fallback; never leak binary data
        // or decoder warnings into the visualization's output buffer.
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });
        try {
            $size = getimagesizefromstring($bytes);
            if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_DECODE_PIXELS) {
                return '';
            }
            // Reserve decoder/rotation memory before allocating a full raster.
            $estimated = $size[0] * $size[1] * 12 + strlen($bytes) * 2 + 16 * 1024 * 1024;
            $limit = trim((string)ini_get('memory_limit'));
            $unit = strtolower(substr($limit, -1));
            $limitBytes = (int)$limit * match ($unit) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
            if ($limitBytes > 0 && memory_get_usage(true) + $estimated > $limitBytes) {
                return '';
            }
            $source = imagecreatefromstring($bytes);
            if ($source === false) {
                return '';
            }
            // GD does not apply JPEG EXIF orientation automatically.
            if ($size[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $input = fopen('php://temp', 'w+b');
                if ($input !== false) {
                    try {
                        fwrite($input, $bytes);
                        rewind($input);
                        $exif = exif_read_data($input);
                        $orientation = (int)($exif['Orientation'] ?? 1);
                        if (in_array($orientation, [2, 5, 7], true)) imageflip($source, IMG_FLIP_HORIZONTAL);
                        if ($orientation === 4) imageflip($source, IMG_FLIP_VERTICAL);
                        $angle = match ($orientation) { 3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0 };
                        if ($angle !== 0) {
                            $rotated = imagerotate($source, $angle, 0);
                            if ($rotated !== false) $source = $rotated;
                        }
                    } catch (Throwable $e) {
                        // Missing or malformed EXIF must not prevent resizing.
                    } finally {
                        fclose($input);
                    }
                }
            }
            $width = imagesx($source);
            $height = imagesy($source);
            $useWebP = function_exists('imagewebp') && (imagetypes() & IMG_WEBP) !== 0;
            for ($edge = self::THUMBNAIL_EDGE; $edge >= 64; $edge = intdiv($edge, 2)) {
                $scale = min(1, $edge / max($width, $height));
                $w = max(1, (int)round($width * $scale));
                $h = max(1, (int)round($height * $scale));
                $target = imagecreatetruecolor($w, $h);
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagefilledrectangle($target, 0, 0, $w - 1, $h - 1, imagecolorallocatealpha($target, 0, 0, 0, 127));
                if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $w, $h, $width, $height)) return '';
                $output = fopen('php://temp', 'w+b');
                if ($output === false) return '';
                try {
                    $ok = $useWebP ? imagewebp($target, $output, 82) : imagepng($target, $output, 6);
                    rewind($output);
                    $encoded = stream_get_contents($output);
                } finally {
                    fclose($output);
                }
                if ($ok && $encoded !== false && $encoded !== '' && strlen($encoded) <= self::MAX_THUMBNAIL_BYTES) {
                    return 'data:image/' . ($useWebP ? 'webp' : 'png') . ';base64,' . base64_encode($encoded);
                }
            }
        } catch (Throwable $e) {
            $this->SendDebug('Thumbnail', $e->getMessage(), 0);
        } finally {
            restore_error_handler();
        }
        return '';
    }

    // Every initial render gets a complete snapshot. Broadcasts omit unchanged images.
    private function GetFullUpdateData(array &$limitedImages = []): array
    {
        // Jede Momentaufnahme bestimmt neu, welche Miniaturen noch gebraucht werden.
        $this->thumbnailsUsed = [];
        $result = [];
        $result['nameswitch'] = $this->ReadPropertyBoolean('NameSwitch');
        $result['fontsize'] = $this->BoundedFloat('Schriftgroesse', 1, 50, 10);
        // Darstellung abwesender Bewohner: Graustufen schaltbar, Deckkraft frei waehlbar.
        $result['graustufen'] = $this->ReadPropertyBoolean('GraustufenSwitch') ? 100 : 0;
        $result['abwesenheitstransparenz'] = max(0, min(100, $this->ReadPropertyInteger('Abwesenheitstransparenz'))) / 100;
        $result['infontsize'] = $this->BoundedFloat('InfoSchriftgroesse', 1, 50, 8);

        // Die Kachel baut ihre Bewohnerplaetze aus dieser Zahl auf.
        $result['residents'] = count($this->Residents());
        foreach ($this->Residents() as $index => $resident) {
            $infoID = $resident['AdditionalInfo'];
            $result['info' . ($index + 1)] = IPS_VariableExists($infoID) ? GetValueFormatted($infoID) : '';
        }

        $result['eckenradius'] = $this->BoundedFloat('Eckenradius', 0, 50, 50);
        $result['bildtransparenz'] = $this->BoundedFloat('Bildtransparenz', 0, 1, 0.7);
        $color = $this->ReadPropertyInteger('Kachelhintergrundfarbe');
        $result['kachelhintergrundfarbe'] = $color < 0 || $color > 0xFFFFFF ? 'transparent' : sprintf('#%06X', $color);
        $result['imageMaxWidth'] = max(10, min(100, $this->ReadPropertyInteger('ImageMaxWidth')));
        $result['bgimage'] = $this->ReadPropertyBoolean('BG_Off')
            ? $this->GetBase64ImageData(0, __DIR__ . '/../imgs/kachelhintergrund1.png')
            : $this->GetBase64ImageData($this->ReadPropertyInteger('bgImage'));

        $defaultBewohnerImagePath = __DIR__ . '/assets/placeholder.png';
        foreach ($this->Residents() as $index => $resident) {
            $i = $index + 1;
            $bewohnerID = $resident['Variable'];
            $valid = $this->IsResidentVariable($bewohnerID);
            $result['Bewohner' . $i] = $valid;
            $result['operable' . $i] = $this->CanOperate($bewohnerID);
            if (!$valid) {
                $result['name' . $i] = '';
                $result['value' . $i] = false;
                $result['image' . $i] = '';
                continue;
            }

            $result['name' . $i] = $resident['AltName'] !== '' ? $resident['AltName'] : IPS_GetName($bewohnerID);
            $result['value' . $i] = GetValueBoolean($bewohnerID);
            $result['image' . $i] = $this->GetBase64ImageData($resident['Image'], $defaultBewohnerImagePath, true);
        }

        $limitedImages = $this->LimitImagePayload($result);
        $this->StoreThumbnails();
        return $result;
    }

    private function LimitImagePayload(array &$data): array
    {
        $sizes = [];
        foreach ($data as $key => $value) {
            if ($this->IsImageKey($key)) {
                $sizes[$key] = strlen($value);
            }
        }
        $total = array_sum($sizes);
        arsort($sizes, SORT_NUMERIC);
        $limited = [];
        $placeholder = $this->PlaceholderImage();
        // Replace the largest images first, keeping as many small photos as possible.
        foreach ($sizes as $key => $size) {
            if ($total <= self::MAX_TILE_IMAGE_BYTES) {
                break;
            }
            $replacement = $key === 'bgimage' ? '' : $placeholder;
            if ($size <= strlen($replacement)) {
                continue;
            }
            $data[$key] = $replacement;
            $total -= $size - strlen($replacement);
            $limited[] = $key === 'bgimage' ? 'bgImage' : 'Bewohner' . $this->ResidentSlot($key, 'image') . 'Image';
        }
        return $limited;
    }

    private function IsResidentVariable(int $id): bool
    {
        return IPS_VariableExists($id) && IPS_GetVariable($id)['VariableType'] === 0;
    }

    private function CanOperate(int $id): bool
    {
        if ($this->ReadPropertyBoolean('BedienungSwitch') || !$this->IsResidentVariable($id)) {
            return false;
        }
        $variable = IPS_GetVariable($id);
        $object = IPS_GetObject($id);
        if ($variable['VariableIsLocked'] || $object['ObjectIsDisabled'] || $variable['VariableCustomAction'] === 1) {
            return false;
        }
        // Never bypass an explicitly configured but unavailable action.
        if (HasAction($id)) {
            return true;
        }
        return !$object['ObjectIsReadOnly'] && $variable['VariableAction'] === 0 && $variable['VariableCustomAction'] === 0;
    }

    private function BoundedFloat(string $name, float $min, float $max, float $fallback): float
    {
        $value = $this->ReadPropertyFloat($name);
        return is_finite($value) ? max($min, min($max, $value)) : $fallback;
    }

    private function EncodeJSON(mixed $data): string
    {
        try {
            return json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->LogMessage('Visualization JSON: ' . $e->getMessage(), KL_ERROR);
            return '{}';
        }
    }

    private function SendState(): void
    {
        $data = $this->GetFullUpdateData();
        $hashes = [];
        $previous = json_decode($this->GetBuffer('ImageHashes'), true) ?? [];
        foreach ($data as $key => $value) {
            if ($this->IsImageKey($key)) {
                $hashes[$key] = hash('sha256', $value);
                if (($previous[$key] ?? null) === $hashes[$key]) {
                    unset($data[$key]);
                }
            }
        }
        $this->UpdateVisualizationValue($this->EncodeJSON($data));
        $this->SetBuffer('ImageHashes', $this->EncodeJSON($hashes));
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        $warnings = [];
        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            $warnings[] = $this->Translate('PHP GD is unavailable. Resident photos cannot be resized automatically.');
        }
        $label = static fn (int $slot): string => sprintf('%s %d', 'Bewohner', $slot);
        $imageProperties = ['bgImage' => $this->ReadPropertyInteger('bgImage')];
        foreach ($this->Residents() as $index => $resident) {
            $slot = $index + 1;
            if ($resident['Variable'] !== 0 && !$this->IsResidentVariable($resident['Variable'])) {
                $warnings[] = $label($slot) . ': ' . $this->Translate('Select an existing Boolean variable.');
            }
            if ($resident['AdditionalInfo'] !== 0 && !IPS_VariableExists($resident['AdditionalInfo'])) {
                $warnings[] = $label($slot) . ': ' . $this->Translate('Select an existing variable.');
            }
            $imageProperties[$label($slot)] = $resident['Image'];
        }
        foreach ($imageProperties as $property => $id) {
            if ($id === 0 || ($property === 'bgImage' && $this->ReadPropertyBoolean('BG_Off'))) {
                continue;
            }
            $media = IPS_MediaExists($id) ? IPS_GetMedia($id) : null;
            if ($media === null || $media['MediaType'] !== MEDIATYPE_IMAGE
                || !isset(self::IMAGE_TYPES[strtolower(pathinfo($media['MediaFile'], PATHINFO_EXTENSION))])) {
                $warnings[] = $property . ': ' . $this->Translate('Select an image: PNG, JPEG, GIF, BMP, ICO or WebP.');
            } else {
                $content = IPS_GetMediaContent($id);
                if ($content === '' || strlen($content) > 4 * (int)ceil(self::MAX_IMAGE_BYTES / 3)) {
                    $warnings[] = $property . ': ' . $this->Translate('Image is empty or exceeds 5 MiB.');
                }
            }
        }
        $limitedImages = [];
        $this->GetFullUpdateData($limitedImages);
        if ($limitedImages !== []) {
            $warnings[] = implode(', ', $limitedImages) . ': ' . $this->Translate('Tile image budget exceeded (2 MiB including Base64). Reduce these images; placeholders are used and oversized backgrounds are hidden.');
        }
        if ($warnings !== []) {
            array_unshift($form['elements'], ['type' => 'Label', 'caption' => implode("\n", $warnings)]);
        }
        return $this->EncodeJSON($form);
    }

}
