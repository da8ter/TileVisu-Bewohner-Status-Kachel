# Bewohnerstatus
![Bewohnerstatus-Kachel](https://raw.githubusercontent.com/da8ter/images/refs/heads/main/bewohnerstatus.png)

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Kachelvisualisierung](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Bildet den Anwesenheitsstatus beliebig vieler Bewohner ab. Sie werden in einer Liste eingerichtet, die sich jederzeit erweitern, kürzen und umsortieren lässt. Der Status eines Bewohners wird über eine Bool-Variable gesteuert. Ein Umschalten des Status kann zusätzlich über das Bild erfolgen (kann in der Konfiguration deaktiviert werden). Anwesende Bewohner werden in Farbe, abwesende in Graustufen dargestellt. Es kann je Bewohner ein eigenes Bild verwendet werden.
* Je Bewohner kann eine Entfernungs-Variable gewählt werden. Sie erscheint als kleines Kennzeichen in der Symcon-Akzentfarbe oben rechts am Foto: eingefahren nur das Route-Symbol, bei Mauszeiger darüber oder Antippen fährt es seitlich aus und zeigt den formatierten Wert. Ist der Bewohner anwesend, bleibt das Kennzeichen ganz weg. Die Schriftgröße folgt der Einstellung „Info Schriftgröße".
* Für jeden Bewohner kann eine zusätzliche Info-Variable angezeigt werden (z. B. der aktuelle Standort wie "Arbeit" oder "Entfernung" etc.). Die Inhalte werden automatisch und dynamisch aktualisiert, sobald sich der Wert der gewählten Variable ändert. Es sind alle Variablentypen erlaubt.
* Die Schriftgröße für den Bewohnernamen und die Zusatzinfo ist individuell einstellbar (Pixel).
* Der Eckenradius der Bilder ist einstellbar (z.B. 50% für runde Bilder).
* Bewohnernamen können global ein- oder ausgeblendet werden.
* Die Darstellung abwesender Bewohner ist einstellbar: Graustufen lassen sich abschalten, die Deckkraft der Fotos ist per Schieberegler von 0 bis 100 % wählbar.
* Die Bedienung (Statusumschaltung per Klick auf das Bild) kann global gesperrt werden.
* Ein benutzerdefiniertes Hintergrundbild mit einstellbarer Transparenz und Kachelhintergrundfarbe kann verwendet werden. Alternativ kann ein Standard-Hintergrundbild genutzt oder ganz deaktiviert werden.

### 2. Voraussetzungen

- Mindestversion: Symcon 8.1.

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
Graustufen bei Abwesenheit | Stellt abwesende Bewohner in Graustufen dar (`true`, Standard) oder belässt das Foto farbig (`false`).
Deckkraft der Fotos bei Abwesenheit | Deckkraft abwesender Bewohnerfotos in Prozent (Schieberegler 0 bis 100, Standard: `50`). `0` blendet das Foto ganz aus, `100` zeigt es unverändert.
Schriftgröße Name          | Schriftgröße des Bewohnernamens (Standard: `10`). Angabe in px.
Schriftgröße Zusatzinfo    | Schriftgröße der Zusatzinformationen (Standard: `8`). Angabe in px.
Eckenradius Bilder         | Eckenradius für die Bewohnerbilder (z.B. `0` für eckig, `50` für rund). Angabe in Prozent.

**Bewohnerliste:**

Die Bewohner stehen in der Liste „Bewohner". Über „Hinzufügen" und „Entfernen" lassen sich beliebig viele Zeilen anlegen; die Reihenfolge der Zeilen ist zugleich die Reihenfolge in der Kachel und kann per Drag & Drop geändert werden.

Spalte                     | Beschreibung
-------------------------- | ---------------------------------------------------------------------------------------------------
Status                     | Bool-Variable, die den Anwesenheitsstatus des Bewohners steuert (`true` = anwesend, `false` = abwesend). Pflichtangabe je Zeile.
Zusätzliche Info           | Variable, deren formatierter Inhalt unter dem Bewohnernamen angezeigt wird (z.B. Standort, Statusmeldung). Alle Variablentypen sind erlaubt.
Foto                       | Auswahl eines eigenen Bildes (Medienobjekt) für den Bewohner.
Entfernung                 | Variable, deren formatierter Inhalt im Kennzeichen oben rechts am Foto erscheint (z.B. `2,4 km`). Alle Variablentypen sind erlaubt. Ohne Variable, bei leerem Wert oder solange der Bewohner anwesend ist, bleibt das Kennzeichen unsichtbar.
Name überschreiben         | Optionaler alternativer Name, der anstelle des Variablennamens angezeigt wird.

### 5. Statusvariablen und Profile

Je Bewohner wird eine existierende Boolean-Variable benötigt. Ungültige Zuordnungen werden ausgeblendet und beim Öffnen der Konfiguration gemeldet. Zusatzinformation und Entfernung verwenden den formatierten Variablenwert. Ungültige UTF-8-Zeichen werden durch Ersatzzeichen ersetzt.

Ein Klick führt eine vorhandene Variablenaktion aus. Nur beschreibbare Variablen ohne Aktion werden direkt umgeschaltet. Schreibgeschützte Sensoren ohne Aktion, deaktivierte Objekte, gesperrte Variablen und ausdrücklich deaktivierte oder nicht verfügbare Aktionen sind nicht bedienbar. Die globale Bedienungssperre wird zusätzlich serverseitig geprüft.

Bilder unterstützen PNG, JPEG, GIF, BMP, ICO und WebP, mit einer Eingangsgrenze von 5 MiB pro Bild. Zusätzlich dürfen alle tatsächlich ausgegebenen Bilddaten der Kachel zusammen höchstens 2 MiB einschließlich Base64 umfassen (entspricht ungefähr 1,5 MiB Originaldaten). Wird dieses Gesamtbudget überschritten, werden die größten Bilder zuerst durch Platzhalter ersetzt bzw. der Hintergrund ausgeblendet. Das Formular nennt die betroffenen Bildfelder. Bewohnerfotos werden mit PHP-GD automatisch für die Anzeige verkleinert: längste Seite maximal 512 Pixel, maximal 128 KiB pro Vorschaubild, ohne Hochskalieren. Ausgabe als WebP (Qualität 82), andernfalls PNG; bei Bedarf wird die Auflösung weiter reduziert. Seitenverhältnis und Transparenz bleiben erhalten. Animationen werden als Standbild angezeigt. JPEG-EXIF-Ausrichtung wird berücksichtigt, wenn die PHP-EXIF-Erweiterung vorhanden ist. Die Originaldateien bleiben unverändert. Eigene Hintergründe werden nicht heruntergerechnet. Größere, leere oder nicht unterstützte Bilder werden durch den Platzhalter ersetzt; beim eigenen Hintergrund entfällt das Bild. Die Konfiguration zeigt entsprechende Hinweise. Die verkleinerten Bewohnerfotos werden je Bewohner zwischengespeichert und bei geändertem Medieninhalt erneuert. Ohne GD, bei einem Decoderfehler, mehr als 24 Megapixeln oder unzureichendem PHP-Speicher greift die bisherige Ausgabe mit Gesamtbudget. Die Eingangsgrenze von 5 MiB bleibt bestehen. Für Hintergründe weiterhin kleine, auf die Anzeigegröße zugeschnittene Bilder verwenden. Medienänderungen aktualisieren geöffnete Kacheln. Unveränderte Bilder werden bei Konfigurationsupdates nicht erneut übertragen; neue Ansichten erhalten immer den vollständigen Zustand.

Numerische Darstellungswerte werden serverseitig auf die Formulargrenzen begrenzt. Die gespeicherten Einstellungen werden dabei nicht verändert.

### 6. Kachelvisualisierung

Die Instanz als Kachel in die Kachelvisualisierung aufnehmen. Bedienbare Bewohnerbilder sind mit Tab erreichbar und lassen sich mit Enter oder Leertaste betätigen. Ein sichtbarer Fokusrahmen und der gedrückt-Zustand machen die Bedienung zugänglich. Die Namen bleiben für assistive Technologien erhalten, auch wenn die sichtbaren Namen ausgeschaltet sind.

Die maximale Bildbreite ist von 10 bis 100 Prozent einstellbar (Standard: 80 Prozent).

### 7. PHP-Befehlsreferenz

`IPS_RequestAction($InstanzID, 'Bewohner1', 1)` schaltet den Status des ersten Bewohners der Liste um; `Bewohner2`, `Bewohner3` usw. entsprechend, bis zur Länge der Liste. Die Nummer bezeichnet die **Position in der Liste**: Wird die Liste umsortiert, zeigt dieselbe Nummer auf einen anderen Bewohner. Der Wertparameter bleibt aus Kompatibilitätsgründen ohne Bedeutung: Der aktuelle Status wird invertiert. Die Bedienungssperre gilt auch für diesen Aufruf.

### 8. Hinweise

* Die Hinweise auf der Konfigurationsseite (ungültige Variable, nicht unterstütztes Bild, überschrittenes Bildbudget) beziehen sich auf den **übernommenen** Stand. Nach dem Ändern eines Feldes erscheinen sie erst nach "Änderungen übernehmen".
* Bewohnerfotos werden nur für die Kachel verkleinert (längste Kante 512 px, Ziel unter 128 KiB, WebP sofern verfügbar). Die Medienobjekte selbst bleiben unverändert. Ohne PHP-GD entfällt die Verkleinerung; die Konfigurationsseite weist darauf hin.
* Die Kachel baut ihre Bewohnerplätze aus der Nachricht auf. Eine geöffnete Kachel folgt einer geänderten Liste sofort, ohne neu geladen zu werden.
* Angetippt bleibt das Kennzeichen drei Sekunden offen und fährt dann von selbst wieder ein. Ein zweiter Tipp schließt es sofort. Der Tipp auf das Kennzeichen schaltet den Anwesenheitsstatus **nicht** um.
* Der Text bleibt auch eingefahren im Dokument stehen, damit Vorlesewerkzeuge ihn finden.
* **Übernahme alter Installationen:** Bis Version 1.1.0 gab es fünf feste Bewohner-Felder. Beim ersten Start nach dem Update wandert deren Inhalt automatisch in die Liste — Lücken werden geschlossen, Foto, Zusatzinfo und alternativer Name bleiben erhalten; die Entfernung bleibt leer, die gab es vorher nicht. Die alten Felder werden danach geleert, die Übernahme läuft genau einmal und wird im Meldungslog vermerkt. Wer die Liste anschließend leert, bekommt die alten Bewohner nicht zurück.
