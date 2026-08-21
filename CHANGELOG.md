# Changelog

Alle wesentlichen Änderungen an dbxApp werden in dieser Datei dokumentiert.
Das Format orientiert sich an „Keep a Changelog“, die Versionierung an
Semantic Versioning.

## [4.4.8] - 2026-08-21

### Fixed

- Erkennt den internen Page-Cache-Alias `/home` als kanonische Startseite.
  Dadurch erzeugt die Sprachumschaltung auch bei einem vorbereiteten
  Full-Page-Cache direkte DE-/EN-URLs ohne `dbx_lng`-Queryparameter.
- Ergänzt einen Regressionstest für die Kombination aus Home-Alias,
  Sprachpräfix und bestehendem Sprachparameter.

## [4.4.7] - 2026-08-21

### Fixed

- Ersetzt auf sprachpräfixierten Startseiten einen bereits vorhandenen reinen
  `dbx_lng`-Parameter durch die direkte kanonische Sprach-URL. Technische
  Routen mit weiteren Parametern bleiben unverändert.
- Ergänzt den Regressionstest für den vollständigen DE→EN→DE-Rundlauf der
  kanonischen Startseiten.

## [4.4.6] - 2026-08-21

### Fixed

- Behandelt den internen Frontcontroller `index.php` bei sprachpräfixierten
  Installationen wie die kanonische Startseite. Dadurch wechseln DE/EN direkt
  zwischen den kanonischen Schwesterseiten und erzeugen keinen
  `dbx_lng`-Queryparameter mehr.
- Erlaubt dem kontrollierten Source-to-GitHub-Abgleich auch saubere
  Git-Worktrees als Release-Spiegel.

## [4.4.5] - 2026-08-21

### Fixed

- Erzeugt auf sprachpräfixierten Startseiten direkte kanonische Sprachlinks
  ohne `dbx_lng`-Queryparameter. Technische Routen und Unterseiten behalten
  ihre bisherige Parameter- bzw. Content-Routenlogik.
- Ergänzt Regressionstests für Standard-, Sprach- und technische
  Startseitenrouten.

## [4.4.4] - 2026-08-21

### Fixed

- Verlegt den signierten Updatekatalog auf den unveränderlichen GitHub-Release,
  weil die bisher konfigurierten Updatehosts nicht per DNS erreichbar sind.
- Erlaubt ausschließlich den fest begrenzten HTTPS-Releasepfad des offiziellen
  dbxApp-Repositories und die zugehörigen GitHub-Asset-CDNs; fremde
  Repositories, unversionierte Pakete und unsichere Redirects bleiben gesperrt.
- Zentralisiert die Vertrauensprüfung für Katalog und Paketdownload im
  gemeinsamen Paketvertrag.

## [4.4.3] - 2026-08-21

### Release note

- Veröffentlicht den Funktionsstand von 4.4.2 als vollständig signierten
  Paketrelease. Der Quell-Tag 4.4.2 bleibt bestehen, besitzt wegen der
  GitHub-Release-Unveränderlichkeit jedoch keinen wiederverwendbaren
  Paketrelease.
- Kernel, allgemeine Module und Designs erhalten konsistent Version 4.4.3;
  unveränderte Fachmodule behalten ihre eigene Paketversion.

## [4.4.2] - 2026-08-21

### Fixed

- Schützt dargestellte Codebeispiele zentral vor der dbxTPL- und
  dbxInterpreter-Auswertung. HTML-Codeelemente, explizit inerte Bereiche und
  Markdown-Codeblöcke bleiben unverändert, während Syntax außerhalb dieser
  Bereiche weiterhin ausgeführt wird.
- Verhindert dadurch beschädigte Systemdokumentation wie
  `Menu (b-undef) not found` und `TPL (...) not found`.
- Ergänzt Regressionstests für Quelltemplates und nachträglich eingesetzte
  CMS-Inhalte.

### Changed

- Entfernt die inzwischen in das eigenständige Dokumentationsprojekt
  ausgelagerten Doxygen- und Tutorialquellen aus dem Produktrelease.

## [4.4.1] - 2026-08-20

### Fixed

- Korrigierte den plattformunabhängigen Quellinventar-Test, damit die
  Release-Prüfung unter PHP 8.2 bis 8.5 zuverlässig durchläuft.
- Ergänzte den im Vertrauensspeicher referenzierten öffentlichen
  Marktplatzschlüssel, damit signierte Kataloge tatsächlich verifiziert werden.
- Vereinheitlichte die sichtbare Produktschreibweise zentral als `dbxapp`,
  ohne Attribute, Quellcodebeispiele, Skripte oder Styles zu verändern.

### Release note

- 4.4.1 ersetzt das unveränderlich veröffentlichte 4.4.0-Paket. Es enthält
  denselben Funktionsstand mit korrigierter CI-Abnahme und ist der empfohlene
  Updatepfad für Installationen der 4.4-Linie.

## [4.2.0] - 2026-08-05

### Added

- Erste unterstützte dbxApp-Produktversion und verbindliche Update-Basis.
- Vollständiger Vorwärts-Updater mit vertrauensgebundenem Manifest,
  SHA-256- und Einzeldateiprüfung, isoliertem Staging, Sicherung und Rollback.
- Maschinenlesbarer `UPDATE_BASELINE`-Vertrag für künftige Releases.
- Koordinierung zukünftiger DD-/dbxDB-Migrationen mit Datei- und
  Datenbankrollback.

### Changed

- Vereinheitlichte dbxTPL-, dbxForm-, dbxReport- und dbxDB-Strukturen.
- Konsolidierte CMS-, Design-, Medien- und Spracharchitektur.
- Vollständiger dbxSelfTest als verbindliche Release-Prüfung.

### Removed

- Upgrade- und Kompatibilitätspfade für nicht veröffentlichte Vorversionen.
- Einmalige, datierte Produktmigrationswerkzeuge und frühere Release-Historie.
