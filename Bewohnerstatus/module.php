<?php

declare(strict_types=1);

class TileVisuresidencystatustile extends IPSModuleStrict
{
    private const RESIDENT_COUNT = 5;
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    private const IMAGE_TYPES = [
        'bmp' => 'image/bmp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'png' => 'image/png', 'ico' => 'image/x-icon',
        'webp' => 'image/webp'
    ];

    public function Create(): void
    {
        parent::Create();

        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            $this->RegisterPropertyInteger('Bewohner' . $i, 0);
            $this->RegisterPropertyInteger('AdditionalInfo' . $i, 0);
            $this->RegisterPropertyInteger('Bewohner' . $i . 'Image', 0);
            $this->RegisterPropertyString('Bewohner' . $i . 'AltName', '');
        }
        $this->RegisterPropertyFloat('Schriftgroesse', 10);
        $this->RegisterPropertyFloat('InfoSchriftgroesse', 8);
        $this->RegisterPropertyFloat('Eckenradius', 50);
        $this->RegisterPropertyBoolean('BG_Off', true);
        $this->RegisterPropertyInteger('bgImage', 0);
        $this->RegisterPropertyFloat('Bildtransparenz', 0.7);
        $this->RegisterPropertyInteger('Kachelhintergrundfarbe', -1);
        $this->RegisterPropertyBoolean('NameSwitch', true);
        $this->RegisterPropertyBoolean('BedienungSwitch', false);
        $this->RegisterPropertyBoolean('DebugOutline', false);
        $this->RegisterPropertyInteger('ImageMaxWidth', 80); // Maximale Bildbreite in %

        // HTML-SDK-Darstellung
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Kein Heavy Work vor KR_READY: Referenzen, Messages und Fremdvariablen-Zugriffe
        // erst, wenn der Kernel bereit ist.
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
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

        if (in_array($Message, [MM_UPDATE, MM_CHANGEFILE, MM_AVAILABLE, OM_UNREGISTER,
            OM_CHANGETYPE, VM_CHANGEPROFILEACTION, VM_CHANGEDLOCKED, OM_CHANGEDISABLED, OM_CHANGEREADONLY], true)) {
            $this->SendState();
            return;
        }

        $update = [];
        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            if ($SenderID === $this->ReadPropertyInteger('Bewohner' . $i) && $this->IsResidentVariable($SenderID)) {
                if ($Message === OM_CHANGENAME && $this->ReadPropertyString('Bewohner' . $i . 'AltName') === '') {
                    $update['name' . $i] = IPS_GetName($SenderID);
                } elseif ($Message === VM_UPDATE) {
                    $update['value' . $i] = GetValueBoolean($SenderID);
                }
            }
            if ($Message === VM_UPDATE && $SenderID === $this->ReadPropertyInteger('AdditionalInfo' . $i)
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
        if (preg_match('/\ABewohner[1-5]\z/', $Ident) !== 1) {
            throw new Exception('Invalid ident: ' . $Ident);
        }

        if ($this->ReadPropertyBoolean('BedienungSwitch')) {
            return; // Bedienung ist gesperrt
        }

        $variableID = $this->ReadPropertyInteger($Ident);
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

    // Referenzen und Nachrichten-Abos passend zur aktuellen Konfiguration neu aufbauen
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

        $register = function (int $id, array $messages): void {
            if ($id <= 0 || !IPS_ObjectExists($id)) {
                return;
            }
            $this->RegisterReference($id);
            foreach ($messages as $message) {
                $this->RegisterMessage($id, $message);
            }
        };

        $mediaMessages = [MM_UPDATE, MM_CHANGEFILE, MM_AVAILABLE, OM_UNREGISTER];
        $register($this->ReadPropertyInteger('bgImage'), $mediaMessages);
        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            $register($this->ReadPropertyInteger('Bewohner' . $i), [OM_CHANGENAME, VM_UPDATE, OM_UNREGISTER, OM_CHANGETYPE, VM_CHANGEPROFILEACTION, VM_CHANGEDLOCKED, OM_CHANGEDISABLED, OM_CHANGEREADONLY]);
            $register($this->ReadPropertyInteger('AdditionalInfo' . $i), [VM_UPDATE, OM_UNREGISTER]);
            $register($this->ReadPropertyInteger('Bewohner' . $i . 'Image'), $mediaMessages);
        }
    }

    private function GetBase64ImageData(int $imageID, string $defaultImagePath = ''): string
    {
        if (IPS_MediaExists($imageID)) {
            $image = IPS_GetMedia($imageID);
            if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                $mime = self::IMAGE_TYPES[strtolower(pathinfo($image['MediaFile'], PATHINFO_EXTENSION))] ?? '';
                if ($mime !== '') {
                    $content = IPS_GetMediaContent($imageID);
                    // Bound transport size without requiring GD or changing the source media.
                    if ($content !== '' && strlen($content) <= 4 * (int)ceil(self::MAX_IMAGE_BYTES / 3)) {
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

    // Every initial render gets a complete snapshot. Broadcasts omit unchanged images.
    private function GetFullUpdateData(): array
    {
        $result = [];
        $result['nameswitch'] = $this->ReadPropertyBoolean('NameSwitch');
        $result['DebugOutline'] = $this->ReadPropertyBoolean('DebugOutline');
        $result['fontsize'] = $this->BoundedFloat('Schriftgroesse', 1, 50, 10);
        $result['infontsize'] = $this->BoundedFloat('InfoSchriftgroesse', 1, 50, 8);

        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            $infoID = $this->ReadPropertyInteger('AdditionalInfo' . $i);
            $result['info' . $i] = IPS_VariableExists($infoID) ? GetValueFormatted($infoID) : '';
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
        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            $bewohnerID = $this->ReadPropertyInteger('Bewohner' . $i);
            $valid = $this->IsResidentVariable($bewohnerID);
            $result['Bewohner' . $i] = $valid;
            $result['operable' . $i] = $this->CanOperate($bewohnerID);
            if (!$valid) {
                $result['name' . $i] = '';
                $result['value' . $i] = false;
                $result['image' . $i] = '';
                continue;
            }

            $altName = $this->ReadPropertyString('Bewohner' . $i . 'AltName');
            $result['name' . $i] = $altName !== '' ? $altName : IPS_GetName($bewohnerID);
            $result['value' . $i] = GetValueBoolean($bewohnerID);
            $result['image' . $i] = $this->GetBase64ImageData(
                $this->ReadPropertyInteger('Bewohner' . $i . 'Image'),
                $defaultBewohnerImagePath
            );
        }

        return $result;
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
            return json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_THROW_ON_ERROR);
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
            if ($key === 'bgimage' || preg_match('/\Aimage[1-5]\z/', $key) === 1) {
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
        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            $id = $this->ReadPropertyInteger('Bewohner' . $i);
            if ($id !== 0 && !$this->IsResidentVariable($id)) {
                $warnings[] = 'Bewohner' . $i . ': ' . $this->Translate('Select an existing Boolean variable.');
            }
            $info = $this->ReadPropertyInteger('AdditionalInfo' . $i);
            if ($info !== 0 && !IPS_VariableExists($info)) {
                $warnings[] = 'AdditionalInfo' . $i . ': ' . $this->Translate('Select an existing variable.');
            }
        }
        $imageProperties = ['bgImage'];
        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            $imageProperties[] = 'Bewohner' . $i . 'Image';
        }
        foreach ($imageProperties as $property) {
            $id = $this->ReadPropertyInteger($property);
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
        if ($warnings !== []) {
            array_unshift($form['elements'], ['type' => 'Label', 'caption' => implode("\n", $warnings)]);
        }
        return $this->EncodeJSON($form);
    }

}
