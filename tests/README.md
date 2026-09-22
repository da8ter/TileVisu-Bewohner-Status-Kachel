# Regressionstests

Ohne Zugriff auf einen Symcon-Server aus dem Repository-Verzeichnis ausführen:

```sh
php -l Bewohnerstatus/module.php
php tests/module_test.php
php tests/thumbnail_test.php
RESIDENT_TEST_NO_LOCK_MESSAGE=1 php tests/module_test.php
node tests/frontend_test.js
git diff --check
```

Der zweite PHP-Lauf lässt `VM_CHANGEDLOCKED` absichtlich undefiniert und prüft damit Instanzerstellung, Kernelstart und Nachrichtenverarbeitung auch ohne diese optionale SDK-Konstante.

Die PHP-Tests prüfen zusätzlich die Bewohnerliste (zwölf Zeilen, Kürzen, leere und kaputte Listendaten, Idents außerhalb der Liste), die Übernahme alter Installationen (Lücken schließen, Felder leeren, genau ein Durchlauf, Neuinstallation, bereits gepflegte Liste, absichtlich geleerte Liste) und die Darstellung abwesender Bewohner.

Die PHP-Tests verwenden isolierte SDK-Doubles. Sie prüfen Kernelstart, Hintergrundentfernung, vollständige Initialzustände trotz Bild-Deltas, WebP und Medienänderungen, Umschaltung mit und ohne Aktion, Schreibschutz, Bedienungssperre, ungültige Zuordnungen, UTF-8, Script-Escaping, Wertebegrenzung, Konfigurationshinweise und Bildgrößenlimit.

Die JavaScript-Tests führen den tatsächlichen Nachrichtencode mit einem minimalen DOM-Double aus. Die Kachel erzeugt ihre Bewohnerplätze selbst, das DOM-Double beherrscht dafür `createElement`/`appendChild`/`removeChild`. Geprüft werden das Wachsen und Schrumpfen der Liste ohne Neuladen, unsinnige Bewohnerzahlen, Reihenfolgeunabhängigkeit, vollständige und partielle Updates, Sichtbarkeit, Textausgabe, ARIA-Zustände, Graustufen und Deckkraft bei Abwesenheit und fehlerhafte Nachrichten. Native Tastaturereignisse und CSS-Layout benötigen zusätzlich einen Browser.

Vor einer Veröffentlichung in einer Symcon-Testinstanz prüfen:

- Kachel neu öffnen und mit 1, 5 und 15 Bewohnern in kleinen und großen Kacheln darstellen.
- Bewohner bei geöffneter Kachel hinzufügen, entfernen und umsortieren; die Kachel muss ohne Neuladen folgen.
- Eine Installation mit den alten fünf Feldern aktualisieren und die Übernahme im Meldungslog prüfen.
- Hintergrund aktivieren und ohne eigenes Bild wieder deaktivieren.
- Bewohnerfoto und Hintergrund innerhalb derselben Medien-ID ersetzen.
- Benutzerdefinierte Boolean-Variable, Variable mit Aktionsskript und schreibgeschützten Sensor testen.
- Bedienung sperren; Klicks und `IPS_RequestAction` dürfen den Status nicht ändern.
- Tab, Enter und Leertaste sowie sichtbaren Fokus und Screenreader-Zustand prüfen.
- Symcon neu starten und aktualisierte Werte nach Kernelstart prüfen.
- Deklarierte Mindestversion 7.1 separat prüfen; lokale CLI-Tests belegen keine SDK-Laufzeitkompatibilität.

## Prüfung dieser Änderung

Lokale Prüfung mit PHP 8.5.7 und Node.js erfolgreich. Die Testansicht im Codex-Browser wurde mit fünf Bewohnern bei Standardgröße und 320 × 180 Pixeln geprüft. Tab fokussiert den ersten Bewohner; Enter und Leertaste schalten den simulierten Status und aktualisieren den zugänglichen Zustand. Der Fokusrahmen ist sichtbar. Dabei wurden ausschließlich Testdaten und eine simulierte Aktion verwendet, keine echten Symcon-Variablen.

Das Standardhintergrundbild bleibt unverändert. Die Übertragungsoptimierung erfolgt durch Bild-Deltas; im Testszenario sinkt ein reines Gestaltungsupdate von 297.890 auf 545 Byte. Die vollständige Erstübertragung bleibt erforderlich.

## Gesamtes Bildbudget

Der Test mit fünf großen Fotos plus Hintergrund prüft die Summe aller Base64-Bilder, den vollständigen HTML-Aufruf inklusive zusätzlicher JSON-RPC-Kodierung sowie Update-Nachrichten gegen das gemeldete Limit von 5.048.576 Byte. Besonders viele `/`-Zeichen simulieren ungünstiges JSON-Escaping. Größte Bilder werden bei Budgetüberschreitung ersetzt; nach Verkleinerung der Quelldatei werden sie automatisch wieder angezeigt. Die Originalmedien werden nicht verändert.

## PHP-Verkleinerung

`thumbnail_test.php` erzeugt PNG-Testbilder im Speicher und prüft mit echtem GD: 512-Pixel-Grenze, Seitenverhältnis, Transparenz, 128-KiB-Grenze, unveränderte Originale, keine Hochskalierung, Cache-Erneuerung und fünf Bewohnerfotos zusammen. Mit `php -d disable_functions=imagecreatefromstring,imagepng tests/module_test.php` wird der Pfad ohne verfügbare GD-Funktionen geprüft. `php -d disable_functions=imagewebp tests/thumbnail_test.php` prüft den PNG-Ausgabepfad. Hintergründe werden nicht verkleinert.
