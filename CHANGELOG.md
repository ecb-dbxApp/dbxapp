# Changelog

Alle wesentlichen Änderungen an dbxApp werden in dieser Datei dokumentiert.
Das Format orientiert sich an „Keep a Changelog“, die Versionierung an
Semantic Versioning.

## [Unreleased]

### Added

- Öffentlicher Release-, Security- und Contribution-Prozess.
- Einheitlicher CI-Einstieg über `composer --working-dir=dbx ci`.
- Automatische Composer-, Syntax-, Hygiene- und dbxApp-Testprüfung.
- Reproduzierbarer Release-ZIP-Bau mit SHA-256-Prüfsumme.

### Changed

- Öffentliche Entwicklungslinie auf `3.1.0-dev` vereinheitlicht.
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
