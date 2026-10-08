# Bewohner-Status-Kachel

Symcon-HTML-Kachel, die den Anwesenheitsstatus beliebig vieler Bewohner mit Foto, Name und Zusatzinfo zeigt; ein Tipp aufs Foto schaltet den Status um. Öffentliches Repo `da8ter/TileVisu-Bewohner-Status-Kachel`.

Betriebsdaten dieses Rechners stehen in `CLAUDE.local.md` (nicht eingecheckt). Eine `docs/` gibt es nicht: Die Entscheidungen stehen in Code-Kommentaren und in `tests/README.md`.

## Aufbau

- **`Bewohnerstatus/`** (Präfix `BWO`, Klasse `TileVisuresidencystatustile`, `IPSModuleStrict`): `module.php`, `module.html`, `form.json`, `locale.json`, `assets/placeholder.png`.
- **Bewohnerliste** in der Property `Residents` (JSON-Liste). Die alten fünf Einzelplätze (`Bewohner<n>`, `AdditionalInfo<n>`, `Bewohner<n>Image`, `Bewohner<n>AltName`) bleiben registriert, sonst ließe sich eine Altinstallation nicht mehr auslesen. `ImportLegacyResidents()` übernimmt sie genau einmal; das Attribut `LegacyImported` wird **vor** dem Schreiben gesetzt, damit der zweite `ApplyChanges`-Durchlauf nicht erneut übernimmt.
- **Bilder** über den nativen Hook `/hook/residentimages/<ID>` (in `Create()`, mit Token und Version, langer privater Cache, 304). Ohne registrierten Hook oder über der Ausgabegrenze werden sie wie früher als Data-URI eingebettet.
- **Miniaturen** liegen in einem Puffer je Foto (`Thumbnail<Schlüssel>`), nicht in einem gemeinsamen: Symcon behält von einem Puffer nur die letzten 512 kB, der gemeinsame wurde zerschnitten.
- **Sparsame Updates** mit Prüfwert je Variable im Puffer `UpdateHashes`; Regeln in `tests/README.md`.

## Besonderheiten

- **Alternativname `"0"` wird angezeigt** (Vergleich `!== ''`, nicht `empty()`), passend zum Vorrang des Alternativnamens bei `OM_CHANGENAME`. Bewusste Entscheidung, nicht zurückbauen.
- `VM_CHANGEDLOCKED` ist eine optionale SDK-Konstante; der Code muss auch ohne sie laufen (eigener Testlauf).

## Prüfen

```bash
php -l Bewohnerstatus/module.php
php tests/module_test.php
RESIDENT_TEST_NO_LOCK_MESSAGE=1 php tests/module_test.php
php tests/thumbnail_test.php
php tests/image_hook_test.php
node tests/frontend_test.js
```

Eigene SDK-Attrappe in `tests/bootstrap.php`, kein laufendes Symcon nötig. Was die Tests abdecken und was nur in einer Testinstanz prüfbar ist: `tests/README.md`.

## Regeln

- **Commits:** nach jeder abgeschlossenen Änderung, ein Thema je Commit, deutsche Botschaft, **ohne** Co-Authored-By-Zeile. Tests vorher.
- **Nie** `git checkout`/`git restore` auf Dateien: die Arbeitskopie kann nicht committete Arbeit enthalten.
- **Push nur auf Zuruf.** Arbeitszweig ist `beta`; auf GitHub wird `symcon-beta` nachgezogen (`git push origin HEAD:symcon-beta`), `main` nur auf ausdrücklichen Zuruf. Vorher `git fetch` und `git log HEAD..origin/<Zweig>` prüfen, nie force-pushen.
- **Release:** `version`, `build` und `date` in `library.json` hochsetzen.
- **Öffentliches Repo:** keine IP-Adressen, Ports, Instanz-IDs, Token, Pfade unter `/Users/`, keine echten Namen oder Fotos von Bewohnern – auch nicht in Tests oder Doku.
- **Symcon-Hausregeln:** `declare(strict_types=1)`, typisierte Signaturen, kein Heavy Work vor `KR_READY`, Darstellungen statt Variablenprofilen für eigene Variablen, neue Nutzertexte sagen „Symcon“.

## Weiteres Wissen

- Symcon-Plattformwissen (Lebenszyklus, Hooks und Ausgabegrenze, Puffergrenzen, Property-Migration, Kacheln): https://github.com/da8ter/SymDo-Family-Organizer/tree/SymDo-Beta/.claude/docs/plattform, lokal `../List/.claude/docs/plattform/`.
- Muster für sparsame Kachel-Updates: https://github.com/da8ter/SymDo-Family-Organizer/blob/SymDo-Beta/.claude/docs/entscheidungen/kacheln-ressourcen.md
