# Changelog

Alle wesentlichen Änderungen an dbxApp werden in dieser Datei dokumentiert.
Das Format orientiert sich an „Keep a Changelog“, die Versionierung an
Semantic Versioning.

## [Unreleased]

### Changed

- Die laufende Entwicklung nach dem stabilen Release 4.0.2 trägt die Version
  `4.0.3-dev` und wird nicht als Update angeboten.
- Der dbxAdmin-Updater prüft, lädt und validiert ein neueres Release mit einem
  einzigen Startschritt.
- Vor dem eigentlichen Dateiaustausch kann ein Administrator das vorbereitete
  Update sicher stoppen; ZIP, Staging und Status werden vollständig entfernt.
- Update-, Installations-, Stop- und Rollbackoperationen verwenden eine
  gemeinsame Dateisperre und können nicht gegeneinander laufen.

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
