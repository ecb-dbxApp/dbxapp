# Changelog

Alle wesentlichen Änderungen an dbxApp werden in dieser Datei dokumentiert.
Das Format orientiert sich an „Keep a Changelog“, die Versionierung an
Semantic Versioning.

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
