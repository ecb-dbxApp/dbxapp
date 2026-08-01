# Changelog

Alle wesentlichen Änderungen an dbxApp werden in dieser Datei dokumentiert.
Das Format orientiert sich an „Keep a Changelog“, die Versionierung an
Semantic Versioning.

## [4.1.1] - 2026-08-01

### Fixed

- Die Mediennutzungs-Migration lädt ihre neue Sprachkontext-Klasse aus dem
  bereitgestellten Releasebaum. Dadurch funktioniert das atomare Update von
  4.0.x, obwohl die bisherigen Programmdateien bis zum Abschluss aktiv
  bleiben.
- Ein Regressionstest schützt diesen Migrations-Bootstrap künftig vor
  erneuten Abhängigkeiten auf noch nicht aktivierte Dateien.

## [4.1.0] - 2026-08-01

### Added

- Sprachsichere Medienverwendung über `dbxMediaUsage.content_lng` und die
  Migration `core-4.1.0-media-usage-language`.
- Installations- und Selbsttest-Tutorials im aktualisierten
  Dokumentationsportal.
- Systemweiter Regressionstest für Sprache, Seitenkopie, Shop-Snapshot und
  rekursive Thumbnail-Wartung.

### Changed

- Medienbrowser und Content-Baum laden bedarfsgerecht; der Medienbrowser
  priorisiert die zuerst sichtbaren Datensätze.
- Seiten- und Sprachkopien verwenden eine ausdrückliche Slot-Freigabeliste.
  Shop-Medien werden niemals als normaler Seiteninhalt kopiert.
- Die Shop-Mediensynchronisierung ersetzt ihren Snapshot idempotent.
- Die Medienwartung analysiert Dubletten in allen Slots, rekonstruiert Hero-,
  Inline- und Shop-Nutzungen, durchsucht `_thumbs` rekursiv und optimiert die
  Datenbank.
- `dbxSelfTest` prüft jetzt 104 PHP-, JavaScript-, Modul-, Sicherheits- und
  Laufzeitverträge im vollständigen Referenzlauf.

### Fixed

- Gleichlautende Seiten- und Ordner-IDs in Deutsch, Englisch und Spanisch
  können Medien nicht mehr gegenseitig zuordnen oder entfernen.
- Die rechte Medienanzeige des CMS entspricht der aktiven Sprache und dem
  tatsächlich gespeicherten Content.
- Fehlende Vorschaubilder werden erstellt und verwaiste Vorschaubilder im
  tatsächlichen Medienverzeichnis entfernt.

### Migration

- `core-4.1.0-media-usage-language` synchronisiert das Media-Usage-DD, weist
  Altzeilen einer Content-Sprache zu und entfernt inaktive sowie exakt
  doppelte Datensätze. Anschließend gleicht die CMS-Medienwartung die
  verbleibenden Daten mit Content und Shop ab.

## [4.0.6] - 2026-07-31

### Fixed

- Bestehende Installationen aus dbxApp 4.0.2, deren lokale Kernkonfiguration
  noch keinen eigenen `install`-Schalter enthält, bleiben nach einem Update
  zuverlässig im normalen Anwendungsmodus. Neuinstallationen ohne lokale
  Konfiguration starten weiterhin im Installer; explizite lokale Werte haben
  unverändert Vorrang.
- Der Legacy-Installationsstatus ist mit isolierten Regressionstests für
  bestehende, neue, explizit konfigurierte und fremde Module abgesichert.

## [4.0.5] - 2026-07-31

### Fixed

- Die beiden von der Benutzeridentitätsmigration betroffenen Core-DDs
  deklarieren ihren SQLite-Server bereits im Release eindeutig als
  `dbx|dbxUser.db3`. Damit kann auch der noch installierte Updater aus 4.0.3
  die Datenbanksicherung vor dem Einspielen der neuen Dateien korrekt
  vorbereiten.
- Der Updatepfad von 4.0.2/4.0.3 zu einem Release mit der Migration
  `core-4.0.3-user-identity` ist als Bootstrap-Szenario abgesichert.

## [4.0.4] - 2026-07-31

### Fixed

- Der System-Updater erkennt den relativen SQLite-DD-Server
  `dbxUser.db3` und die vom laufenden DD-Loader qualifizierte Form
  `dbx|dbxUser.db3` als dasselbe physische Ziel. Dadurch kann die
  Benutzer-/Gruppenmigration auch in Installationen mit vorhandener lokaler
  DB3-Datei sicher vorbereitet werden.
- Die Migrationsprüfung deckt jetzt sowohl äquivalente SQLite-Schreibweisen
  als auch weiterhin gesperrte echte DD-Serverwechsel ab.

## [4.0.3] - 2026-07-31

### Added

- `dbxSelfTest` mit Komplett-, Schnell- und Einzeltests, dauerhaftem Protokoll
  sowie PHP- und JavaScript-Prüfungen ohne Node.js-Pflicht im Zielsystem.
- DD-basierte, protokollierte Datenmigrationen mit Tabellenbackup und
  gemeinsamem Datei-/Datenbank-Rollback im System-Updater.
- Dokumentationsportal, mehrsprachige Systemmenüs und ein eigenständiger
  Viewport-Test für die visuelle Prüfung responsiver Seiten.

### Changed

- Der dbxAdmin-Updater prüft, lädt und validiert ein neueres Release mit einem
  einzigen Startschritt.
- Vor dem eigentlichen Dateiaustausch kann ein Administrator das vorbereitete
  Update sicher stoppen; ZIP, Staging und Status werden vollständig entfernt.
- Update-, Installations-, Stop- und Rollbackoperationen verwenden eine
  gemeinsame Dateisperre und können nicht gegeneinander laufen.
- `Status & Health`, Dashboard-Menü und Schnellzugriff zeigen den zentralen
  Update-Zustand in Deutsch, Englisch und Spanisch an.
- Die Dashboard-Anzeige liest ausschließlich den lokalen Update-Cache; eine
  Netzwerkprüfung startet weiterhin nur bewusst auf der Update-Seite.
- Der CMS-Editor lädt Content-Baum und Medien bedarfsgerecht, zeigt sichtbare
  Medien priorisiert und gleicht Inline-Nutzung, Vorschaubilder und Wartung
  mit dem tatsächlich gespeicherten Inhalt ab.
- Videos verwenden konsistente Größen- und Ausrichtungsregeln in Editor und
  `dbxContent`; horizontale Zentrierung ist verfügbar.
- Der universelle Inhaltsmarker heißt einheitlich `hero` und kann Text,
  Bilder oder Videos enthalten.

### Fixed

- Editor-Cursor, Reload-Warnung und Medienzuordnung wurden korrigiert; eine
  Verlassenswarnung bleibt beim Schließen eines Tabs oder Browsers erhalten.
- Full-Page-Cache, Sitemap und Fehlressourcen-Erkennung behandeln Sprachen,
  Permalinks und ungültige Aufrufe konsistent.
- Lange Selbsttests liefern auch bei PHP-Laufzeitgrenzen eine gültige Antwort
  und können zuverlässig fortgesetzt oder gestoppt werden.

### Migration

- `core-4.0.3-user-identity` synchronisiert Benutzer- und Gruppen-DDs und
  stellt die verbindlichen Core-Gruppen wiederholbar sicher. Vorhandene
  Tabellen werden vor der Migration gesichert.

## [4.0.2] - 2026-07-27

### Added

- Kontrollierter Source-to-GitHub-Abgleich mit Dry-Run, fester
  Veröffentlichungsgrenze und Schutz lokaler Laufzeitdaten.
- Release-Datei-Inventar und maschinenlesbares `update.json` für installierte
  dbxApp-Systeme.
- Admin-Updateablauf mit HTTPS-Vertrauensgrenze, SHA-256, sicherer
  ZIP-Prüfung, Staging, Dateisicherung und Rollback.

### Changed

- dbxApp 4 wird ausdrücklich als vollständige Neuentwicklung und nicht als
  automatischer Upgradepfad von dbxWebApp geführt.
- Version 4.0.2 bildet die erste updaterfähige Referenz der dbxApp-4-Linie.
- Öffentliche Modulkonfigurationen enthalten keine installationsbezogenen
  Mail-, SFTP- oder Token-Werte; diese liegen nur in `config.local.php`.

## [4.0.1] - 2026-07-27

### Added

- Öffentlicher Release-, Security- und Contribution-Prozess.
- Einheitlicher CI-Einstieg über `composer --working-dir=dbx ci`.
- Automatische Composer-, Syntax-, Hygiene- und dbxApp-Testprüfung.
- Reproduzierbarer Release-ZIP-Bau mit SHA-256-Prüfsumme.

### Changed

- Öffentliche Release-Version auf `4.0.1` festgelegt.
- Composer-Abhängigkeiten verwenden kompatible Patch-/Minor-Bereiche.
- phpseclib auf den sicheren 3.0-Zweig ab Version 3.0.55 aktualisiert.

### Security

- Datenbanken, Backups, Laufzeitdaten und lokale Konfigurationen aus dem
  öffentlichen Quellstand ausgeschlossen.

## Frühere Versionen

Die historischen Hinweise befinden sich in:

- `RELEASE_NOTES_v2.0.md`
- `RELEASE_NOTES_v1.0.md`
- `RELEASE_NOTES_v0.9.md`
