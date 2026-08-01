# dbxApp 4.1.2

Version 4.1.2 ist das vollständig geprüfte Update-Kompatibilitätsrelease der
4.1-Linie. Es enthält alle Funktionen aus 4.1.0 und 4.1.1 und korrigiert die
physische DD-Synchronisierung während atomarer Updates.

## Update- und Migrationssicherheit

- `sync_dd` verwendet nun ausdrücklich die DD-Definition aus dem verifizierten
  Release-Staging und nicht die noch aktive Definition der Vorgängerversion.
- Die zusätzliche Migration `core-4.1.2-media-usage-schema-repair` legt
  `dbxMediaUsage.content_lng` updatefest an, prüft die physische Spalte und
  wiederholt anschließend Sprachzuordnung und Dublettenbereinigung.
- Die Reparatur funktioniert sowohl direkt von 4.0.x als auch für eine
  Installation, die 4.1.1 bereits ausgeführt hat.
- Vorhandene lokale DD-Serverbindungen bleiben maßgeblich; der temporäre
  Release-DD-Cache wird nach der Migration vollständig wiederhergestellt.
- Neue Regressionstests prüfen die Staging-DD-Auswahl, Cache-Wiederherstellung
  und die physische Schema-Verifikation.

## Enthaltener Funktionsumfang

4.1.2 enthält außerdem die sprachsichere Medienverwendung, idempotente
Shop-Medien-Synchronisierung, erweiterte Medien- und Thumbnailwartung, den
vollständigen `dbxSelfTest` sowie die Performance- und Bedienungsverbesserungen
im CMS.

Nach dem Update sollte ein Administrator im CMS einmal **Medienwartung**
ausführen. Ein zweiter Lauf muss ohne neu hinzugekommene Korrekturen enden.
