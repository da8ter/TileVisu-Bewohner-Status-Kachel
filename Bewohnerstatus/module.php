<?php

declare(strict_types=1);

class TileVisuresidencystatustile extends IPSModuleStrict
{
    private const RESIDENT_COUNT = 5;

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

        // Komplette Update-Nachricht an die Darstellung, da sich Parameter geändert haben können
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
            return;
        }

        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            if ($SenderID === $this->ReadPropertyInteger('Bewohner' . $i)) {
                switch ($Message) {
                    case OM_CHANGENAME:
                        // Ein konfigurierter Alternativname hat Vorrang vor dem Objektnamen
                        if ($this->ReadPropertyString('Bewohner' . $i . 'AltName') === '') {
                            $this->UpdateVisualizationValue(json_encode([
                                'name' . $i => (string)($Data[0] ?? '')
                            ]));
                        }
                        break;

                    case VM_UPDATE:
                        $this->UpdateVisualizationValue(json_encode([
                            'value' . $i => (bool)GetValue($SenderID)
                        ]));
                        break;
                }
            }

            if ($Message === VM_UPDATE && $SenderID === $this->ReadPropertyInteger('AdditionalInfo' . $i)) {
                $this->UpdateVisualizationValue(json_encode([
                    'info' . $i => GetValueFormatted($SenderID)
                ]));
            }
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        // Die Darstellung bedient ausschließlich die Bewohner-Status-Variablen —
        // alle anderen Idents werden an der Systemgrenze abgewiesen.
        if (preg_match('/^Bewohner[1-5]$/', $Ident) !== 1) {
            throw new Exception('Invalid ident: ' . $Ident);
        }

        if ($this->ReadPropertyBoolean('BedienungSwitch')) {
            return; // Bedienung ist gesperrt
        }

        $variableID = $this->ReadPropertyInteger($Ident);
        if (!IPS_VariableExists($variableID)) {
            $this->SendDebug('RequestAction', 'Variable to be updated does not exist', 0);
            return;
        }

        SetValue($variableID, !GetValueBoolean($variableID));
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
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';

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
            if ($id <= 0) {
                return;
            }
            $this->RegisterReference($id);
            foreach ($messages as $message) {
                $this->RegisterMessage($id, $message);
            }
        };

        $register($this->ReadPropertyInteger('bgImage'), []);
        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            $register($this->ReadPropertyInteger('Bewohner' . $i), [OM_CHANGENAME, VM_UPDATE]);
            $register($this->ReadPropertyInteger('AdditionalInfo' . $i), [VM_UPDATE]);
            $register($this->ReadPropertyInteger('Bewohner' . $i . 'Image'), []);
        }
    }

    private function GetBase64ImageData(int $imageID, string $defaultImagePath = ''): string
    {
        if (IPS_MediaExists($imageID)) {
            $image = IPS_GetMedia($imageID);
            if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                $imageFile = explode('.', $image['MediaFile']);
                $imageExtension = strtolower(end($imageFile));
                $imageContentPrefix = match ($imageExtension) {
                    'bmp'          => 'data:image/bmp;base64,',
                    'jpg', 'jpeg'  => 'data:image/jpeg;base64,',
                    'gif'          => 'data:image/gif;base64,',
                    'png'          => 'data:image/png;base64,',
                    'ico'          => 'data:image/x-icon;base64,',
                    default        => ''
                };

                if ($imageContentPrefix !== '') {
                    return $imageContentPrefix . IPS_GetMediaContent($imageID);
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

    // Nachricht, die alle Elemente der HTML-Darstellung aktualisiert.
    // Die Schlüssel-Reihenfolge ist Kontrakt: das Frontend verarbeitet sie in
    // Einfügereihenfolge (z.B. muss nameswitch vor fontsize kommen).
    private function GetFullUpdateMessage(): string
    {
        $result = [];

        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            $result['Bewohner' . $i] = IPS_VariableExists($this->ReadPropertyInteger('Bewohner' . $i));
            $result['bewohner' . $i . 'altname'] = $this->ReadPropertyString('Bewohner' . $i . 'AltName');
        }

        $result['nameswitch'] = $this->ReadPropertyBoolean('NameSwitch');
        $result['DebugOutline'] = $this->ReadPropertyBoolean('DebugOutline');
        $result['fontsize'] = $this->ReadPropertyFloat('Schriftgroesse');
        $result['infontsize'] = $this->ReadPropertyFloat('InfoSchriftgroesse');

        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            $infoID = $this->ReadPropertyInteger('AdditionalInfo' . $i);
            $result['info' . $i] = IPS_VariableExists($infoID) ? GetValueFormatted($infoID) : '';
        }

        $result['eckenradius'] = $this->ReadPropertyFloat('Eckenradius');
        $result['bildtransparenz'] = $this->ReadPropertyFloat('Bildtransparenz');
        // -1 (transparent) ergibt '#FFFFFFFFFFFFFFFF' — das Frontend erkennt genau diesen
        // Marker und setzt dann rgba(0,0,0,0).
        $result['kachelhintergrundfarbe'] = '#' . sprintf('%06X', $this->ReadPropertyInteger('Kachelhintergrundfarbe'));
        $result['imageMaxWidth'] = $this->ReadPropertyInteger('ImageMaxWidth');

        if ($this->ReadPropertyBoolean('BG_Off')) {
            // Standard-Hintergrundbild erzwingen
            $result['bgimage'] = $this->GetBase64ImageData(0, __DIR__ . '/../imgs/kachelhintergrund1.png');
        } else {
            // Nur ein konfiguriertes Bild verwenden — ohne gültiges Bild bleibt der
            // Schlüssel weg und die Kachel hat keinen Bildhintergrund.
            $imageData = $this->GetBase64ImageData($this->ReadPropertyInteger('bgImage'));
            if ($imageData !== '') {
                $result['bgimage'] = $imageData;
            }
        }

        $defaultBewohnerImagePath = __DIR__ . '/assets/placeholder.png';
        for ($i = 1; $i <= self::RESIDENT_COUNT; $i++) {
            $bewohnerID = $this->ReadPropertyInteger('Bewohner' . $i);
            if (!IPS_VariableExists($bewohnerID)) {
                continue;
            }

            $altName = $result['bewohner' . $i . 'altname'];
            $result['name' . $i] = $altName !== '' ? $altName : IPS_GetName($bewohnerID);
            $result['value' . $i] = GetValueBoolean($bewohnerID);
            $result['image' . $i] = $this->GetBase64ImageData(
                $this->ReadPropertyInteger('Bewohner' . $i . 'Image'),
                $defaultBewohnerImagePath
            );
        }

        return (string)json_encode($result);
    }
}
