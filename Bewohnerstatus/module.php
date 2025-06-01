<?php

class TileVisuresidencystatustile extends IPSModule
{

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // Eigenschaften für die dargestellten Zähler und Bilder in Schleifen registrieren
        for ($i = 1; $i <= 5; $i++) {
            $this->RegisterPropertyInteger('Bewohner' . $i, 0);
            $this->RegisterPropertyInteger('AdditionalInfo' . $i, 0);
            $this->RegisterPropertyInteger('Bewohner' . $i . 'Image', 0);
            $this->RegisterPropertyString('Bewohner' . $i . 'AltName', '');
        }
        $this->RegisterPropertyFloat('Schriftgroesse', 10);
        $this->RegisterPropertyFloat('InfoSchriftgroesse', 8);
        $this->RegisterPropertyFloat('Eckenradius', 50);
        $this->RegisterPropertyBoolean('BG_Off', 1);
        $this->RegisterPropertyInteger("bgImage", 0);
        $this->RegisterPropertyFloat('Bildtransparenz', 0.7);
        $this->RegisterPropertyInteger('Kachelhintergrundfarbe', -1);
        $this->RegisterPropertyBoolean('NameSwitch', 1);
        $this->RegisterPropertyBoolean('BedienungSwitch', 0);
        $this->RegisterPropertyInteger('ImageMaxWidth', 80); // Maximale Bildbreite in vh
        // Visualisierungstyp auf 1 setzen, da wir HTML anbieten möchten
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        //Referenzen Registrieren
        $ids = [$this->ReadPropertyInteger('bgImage')];
        for ($i = 1; $i <= 5; $i++) {
            $ids[] = $this->ReadPropertyInteger('Bewohner' . $i);
            $ids[] = $this->ReadPropertyInteger('Bewohner' . $i . 'Image');
            $ids[] = $this->ReadPropertyInteger('AdditionalInfo' . $i);
        }
        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        foreach ($ids as $id) {
            if ($id !== '') {
                $this->RegisterReference($id);
            }
        }

        // Aktualisiere registrierte Nachrichten
        foreach ($this->GetMessageList() as $senderID => $messageIDs) {
            foreach ($messageIDs as $messageID) {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }

        foreach (['Bewohner1', 'Bewohner2', 'Bewohner3', 'Bewohner4', 'Bewohner5'] as $BewohnerProperty) {
            $this->RegisterMessage($this->ReadPropertyInteger($BewohnerProperty), OM_CHANGENAME);
            $this->RegisterMessage($this->ReadPropertyInteger($BewohnerProperty), VM_UPDATE);
        }
        // AdditionalInfo-Variablen ebenfalls für Updates registrieren
        foreach (['AdditionalInfo1', 'AdditionalInfo2', 'AdditionalInfo3', 'AdditionalInfo4', 'AdditionalInfo5'] as $InfoProperty) {
            $this->RegisterMessage($this->ReadPropertyInteger($InfoProperty), VM_UPDATE);
        }

        // Schicke eine komplette Update-Nachricht an die Darstellung, da sich ja Parameter geändert haben können
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        foreach (['Bewohner1', 'Bewohner2', 'Bewohner3', 'Bewohner4', 'Bewohner5'] as $index => $BewohnerProperty) {
            if ($SenderID === $this->ReadPropertyInteger($BewohnerProperty)) {
                switch ($Message) {
                    case OM_CHANGENAME:
                        // Teile der HTML-Darstellung den neuen Namen mit
                        $this->UpdateVisualizationValue(json_encode([
                            'name' . ($index + 1) => $Data[0]
                        ]));
                        break;

                    case VM_UPDATE:
                        // Teile der HTML-Darstellung den neuen Wert mit. Damit dieser korrekt formatiert ist, holen wir uns den von der Variablen via GetValueFormatted
                        $this->UpdateVisualizationValue(json_encode(['value' . ($index + 1) => GetValue($this->ReadPropertyInteger($BewohnerProperty))]));
                        break;
                }
            }
        }
        // AdditionalInfo-Variablen prüfen
        foreach (['AdditionalInfo1', 'AdditionalInfo2', 'AdditionalInfo3', 'AdditionalInfo4', 'AdditionalInfo5'] as $index => $InfoProperty) {
            if ($SenderID === $this->ReadPropertyInteger($InfoProperty)) {
                if ($Message == VM_UPDATE) {
                    $this->UpdateVisualizationValue(json_encode([
                        'info' . ($index + 1) => GetValueFormatted($SenderID)
                    ]));
                }
            }
        }
    }

    public function RequestAction($Ident, $value)
    {
        // Nachrichten von der HTML-Darstellung schicken immer den Ident passend zur Eigenschaft und im Wert die Differenz, welche auf die Variable gerechnet werden soll
        $variableID = $this->ReadPropertyInteger($Ident);
        $sperre = $this->ReadPropertyBoolean('BedienungSwitch');
        if (!IPS_VariableExists($variableID)) {
            $this->SendDebug('Error in RequestAction', 'Variable to be updated does not exist', 0);
            return;
        }
        // Umschalten des Werts der Variable
        $currentValue = GetValue($variableID);
        if ($sperre == false) {
            SetValue($variableID, !$currentValue);
        }

    }

    public function GetVisualizationTile()
    {
        // Füge ein Skript hinzu, um beim laden, analog zu Änderungen bei Laufzeit, die Werte zu setzen
        // Obwohl die Rückgabe von GetFullUpdateMessage ja schon JSON-codiert ist wird dennoch ein weiteres mal json_encode ausgeführt
        // Damit wird dem String Anführungszeichen hinzugefügt und eventuelle Anführungszeichen innerhalb werden korrekt escaped
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';

        // Füge statisches HTML aus Datei hinzu
        $module = file_get_contents(__DIR__ . '/module.html');

        // Gebe alles zurück. 
        // Wichtig: $initialHandling nach hinten, da die Funktion handleMessage ja erst im HTML definiert wird
        return $module . $initialHandling;
    }

    // Generiere eine Nachricht, die alle Elemente in der HTML-Darstellung aktualisiert
    private function _getBase64ImageData(int $imageID, string $defaultImagePath = ''): string
    {
        if (IPS_MediaExists($imageID)) {
            $image = IPS_GetMedia($imageID);
            if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                $imageFile = explode('.', $image['MediaFile']);
                $imageExtension = strtolower(end($imageFile));
                $imageContentPrefix = '';
                switch ($imageExtension) {
                    case 'bmp':
                        $imageContentPrefix = 'data:image/bmp;base64,';
                        break;
                    case 'jpg':
                    case 'jpeg':
                        $imageContentPrefix = 'data:image/jpeg;base64,';
                        break;
                    case 'gif':
                        $imageContentPrefix = 'data:image/gif;base64,';
                        break;
                    case 'png':
                        $imageContentPrefix = 'data:image/png;base64,';
                        break;
                    case 'ico':
                        $imageContentPrefix = 'data:image/x-icon;base64,';
                        break;
                }

                if ($imageContentPrefix) {
                    return $imageContentPrefix . IPS_GetMediaContent($imageID);
                }
            }
        }
        if ($defaultImagePath !== '' && file_exists($defaultImagePath)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($defaultImagePath));
        }
        return ''; // Return empty string if no image found and no default
    }

    private function GetFullUpdateMessage()
    {
        $result = [];
        for ($i = 1; $i <= 5; $i++) {
            $bewohnerID = $this->ReadPropertyInteger('Bewohner' . $i);
            $result['Bewohner' . $i] = IPS_VariableExists($bewohnerID);
            $result['bewohner' . $i . 'altname'] = $this->ReadPropertyString('Bewohner' . $i . 'AltName');
        }
        $result['nameswitch'] = $this->ReadPropertyBoolean('NameSwitch');
        $result['fontsize'] = $this->ReadPropertyFloat('Schriftgroesse');
        $result['infontsize'] = $this->ReadPropertyFloat('InfoSchriftgroesse');

        // Additional Info Variables
        $AdditionalInfoIDs = [
            $this->ReadPropertyInteger('AdditionalInfo1'),
            $this->ReadPropertyInteger('AdditionalInfo2'),
            $this->ReadPropertyInteger('AdditionalInfo3'),
            $this->ReadPropertyInteger('AdditionalInfo4'),
            $this->ReadPropertyInteger('AdditionalInfo5')
        ];
        for ($i = 0; $i < 5; $i++) {
            $infoKey = 'info' . ($i + 1);
            if (IPS_VariableExists($AdditionalInfoIDs[$i])) {
                $result[$infoKey] = GetValueFormatted($AdditionalInfoIDs[$i]);
            } else {
                $result[$infoKey] = '';
            }
        }
        $result['eckenradius'] = $this->ReadPropertyFloat('Eckenradius');
        $result['bildtransparenz'] = $this->ReadPropertyFloat('Bildtransparenz');
        $result['kachelhintergrundfarbe'] = '#' . sprintf('%06X', $this->ReadPropertyInteger('Kachelhintergrundfarbe'));
        $result['imageMaxWidth'] = $this->ReadPropertyInteger('ImageMaxWidth');

        // Hintergrundbild
        $bgImageID = $this->ReadPropertyInteger('bgImage');
        $defaultBgImagePath = __DIR__ . '/../imgs/kachelhintergrund1.png';

        if ($this->ReadPropertyBoolean('BG_Off')) {
            // BG_Off is true, force the default background image
            $result['bgimage'] = $this->_getBase64ImageData(0, $defaultBgImagePath); // Pass 0 to helper to explicitly request default
        } else {
            // BG_Off is false. Try to load custom image ONLY. No fallback to default image file.
            $imageData = $this->_getBase64ImageData($bgImageID); // Call helper without defaultImagePath
            if ($imageData !== '') { // Only set if a custom image was found and is valid
                $result['bgimage'] = $imageData;
            }
            // If no valid custom image is found (imageData is ''), $result['bgimage'] remains unset, meaning no background.
        }

        // Bewohner Daten und Bilder
        $defaultBewohnerImagePath = __DIR__ . '/assets/placeholder.png';
        for ($i = 1; $i <= 5; $i++) {
            $bewohnerID = $this->ReadPropertyInteger('Bewohner' . $i);
            if (IPS_VariableExists($bewohnerID)) {
                $altNameKey = 'bewohner' . $i . 'altname';
                if (!empty($result[$altNameKey])) {
                    $result['name' . $i] = $result[$altNameKey];
                } else {
                    $result['name' . $i] = IPS_GetName($bewohnerID);
                }
                $result['value' . $i] = GetValueBoolean($bewohnerID);

                $imageID = $this->ReadPropertyInteger('Bewohner' . $i . 'Image');
                $result['image' . $i] = $this->_getBase64ImageData($imageID, $defaultBewohnerImagePath);
            }
        }
        return json_encode($result);

    }


}

?>