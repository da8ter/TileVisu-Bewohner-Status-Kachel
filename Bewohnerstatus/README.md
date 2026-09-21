# Bewohnerstatus
![Bewohnerstatus-Kachel](https://github.com/da8ter/images/blob/main/bewohner_status.jpg)

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Kachelvisualisierung](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Bildet den Anwesenheitsstatus von bis zu 5 Bewohnern ab. Der Status eines Bewohners wird über eine Bool-Variable gesteuert. Ein Umschalten des Status kann zusätzlich über das Bild erfolgen (kann in der Konfiguration deaktiviert werden). Anwesende Bewohner werden in Farbe, abwesende in Graustufen dargestellt. Es kann je Bewohner ein eigenes Bild verwendet werden.
* Für jeden Bewohner kann eine zusätzliche Info-Variable angezeigt werden (z. B. der aktuelle Standort wie "Arbeit" oder "Entfernung" etc.). Die Inhalte werden automatisch und dynamisch aktualisiert, sobald sich der Wert der gewählten Variable ändert. Es sind alle Variablentypen erlaubt.
* Die Schriftgröße für den Bewohnernamen und die Zusatzinfo ist individuell einstellbar (Pixel).
* Der Eckenradius der Bilder ist einstellbar (z.B. 50% für runde Bilder).
* Bewohnernamen können global ein- oder ausgeblendet werden.
* Die Bedienung (Statusumschaltung per Klick auf das Bild) kann global gesperrt werden.
* Ein benutzerdefiniertes Hintergrundbild mit einstellbarer Transparenz und Kachelhintergrundfarbe kann verwendet werden. Alternativ kann ein Standard-Hintergrundbild genutzt oder ganz deaktiviert werden.

### 2. Voraussetzungen

- Deklarierte Mindestversion: IP-Symcon 7.1. Die verwendete Basisklasse `IPSModuleStrict` wurde dort als Preview angeboten; reguläre Unterstützung besteht ab 8.1. Ein Laufzeittest auf 7.1 steht aus.

### 3. Software-Installation

* Über das Module Control folgende URL hinzufügen:
  `https://github.com/da8ter/TileVisu-Bewohner-Status-Kachel.git`

### 4. Einrichten der Instanzen in IP-Symcon

Unter 'Instanz hinzufügen' kann die "Bewohner-Status Kachel"-Kachel mithilfe des Schnellfilters gefunden werden. (Suchbegriff: `Bewohner-Status`)
- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

**Allgemeine Einstellungen:**

Name                       | Beschreibung
-------------------------- | ---------------------------------------------------------------------------------------------------------------------------------
Standard-Hintergrundbild   | Schaltet das Standard-Hintergrundbild ein (`true`) oder aus (`false`). Wenn aus und kein benutzerdefiniertes Bild gewählt ist, wird kein Hintergrundbild angezeigt.
Hintergrundbild            | Auswahl eines eigenen Medienobjekts als Hintergrundbild. Wird ignoriert, wenn "Standard-Hintergrundbild" aktiv ist.
Transparenz Bild           | Transparenz des Hintergrundbildes (Wert von 0.0 bis 1.0). Ermöglicht zusammen mit "Kachelhintergrundfarbe" das Bild abzudunkeln oder einzufärben.
Kachelhintergrundfarbe     | Hintergrundfarbe der Kachel. Nur sichtbar, wenn ein Hintergrundbild mit Transparenz < 1.0 verwendet wird.
Name anzeigen              | Schaltet die Anzeige der Bewohnernamen global ein (`true`) oder aus (`false`).
Bedienung sperren          | Sperrt (`true`) oder erlaubt (`false`) die manuelle Statusumschaltung durch Klick auf das Bewohnerbild.
Schriftgröße Name          | Schriftgröße des Bewohnernamens (Standard: `10`). Angabe in px.
Schriftgröße Zusatzinfo    | Schriftgröße der Zusatzinformationen (Standard: `8`). Angabe in px.
Eckenradius Bilder         | Eckenradius für die Bewohnerbilder (z.B. `0` für eckig, `50` für rund). Angabe in Prozent.

**Bewohnerbezogene Einstellungen (für jeden Bewohner 1-5):**

Name                       | Beschreibung
-------------------------- | ---------------------------------------------------------------------------------------------------
Status (Bewohner X)        | Bool-Variable, die den Anwesenheitsstatus des Bewohners steuert (`true` = anwesend, `false` = abwesend).
Foto (Bewohner X Image)    | Auswahl eines eigenen Bildes (Medienobjekt) für den Bewohner.
Alternativer Name (Bewohner X AltName) | Optionaler alternativer Name, der anstelle des Variablennamens für den Bewohner angezeigt wird.
Zusatzinfo (AdditionalInfo X) | Variable, deren formatierter Inhalt unter dem Bewohnernamen angezeigt wird (z.B. Standort, Statusmeldung).

### 5. Statusvariablen und Profile

Je Bewohner wird eine existierende Boolean-Variable benötigt. Ungültige Zuordnungen werden ausgeblendet und beim Öffnen der Konfiguration gemeldet. Zusatzinformationen verwenden den formatierten Variablenwert. Ungültige UTF-8-Zeichen werden durch Ersatzzeichen ersetzt.

Ein Klick führt eine vorhandene Variablenaktion aus. Nur beschreibbare Variablen ohne Aktion werden direkt umgeschaltet. Schreibgeschützte Sensoren ohne Aktion, deaktivierte Objekte, gesperrte Variablen und ausdrücklich deaktivierte oder nicht verfügbare Aktionen sind nicht bedienbar. Die globale Bedienungssperre wird zusätzlich serverseitig geprüft.

Bilder unterstützen PNG, JPEG, GIF, BMP, ICO und WebP, bis maximal 5 MiB pro Bild. Größere, leere oder nicht unterstützte Bilder werden durch den Platzhalter ersetzt; beim eigenen Hintergrund entfällt das Bild. Die Konfiguration zeigt entsprechende Hinweise. Für kurze Ladezeiten kleine, auf die Anzeigegröße zugeschnittene Bilder verwenden. Medienänderungen aktualisieren geöffnete Kacheln. Unveränderte Bilder werden bei Konfigurationsupdates nicht erneut übertragen; neue Ansichten erhalten immer den vollständigen Zustand.

Numerische Darstellungswerte werden serverseitig auf die Formulargrenzen begrenzt. Die gespeicherten Einstellungen werden dabei nicht verändert.

### 6. Kachelvisualisierung

Die Instanz als Kachel in die Kachelvisualisierung aufnehmen. Bedienbare Bewohnerbilder sind mit Tab erreichbar und lassen sich mit Enter oder Leertaste betätigen. Ein sichtbarer Fokusrahmen und der gedrückt-Zustand machen die Bedienung zugänglich. Die Namen bleiben für assistive Technologien erhalten, auch wenn die sichtbaren Namen ausgeschaltet sind.

Die maximale Bildbreite ist von 10 bis 100 Prozent einstellbar (Standard: 80 Prozent).

### 7. PHP-Befehlsreferenz

`IPS_RequestAction($InstanzID, 'Bewohner1', 1)` schaltet den konfigurierten Bewohnerstatus um. Entsprechend stehen `Bewohner2` bis `Bewohner5` zur Verfügung. Der Wertparameter bleibt aus Kompatibilitätsgründen ohne Bedeutung: Der aktuelle Status wird invertiert. Die Bedienungssperre gilt auch für diesen Aufruf.

Entwicklung und Prüfschritte: [Regressionstests](../tests/README.md).
