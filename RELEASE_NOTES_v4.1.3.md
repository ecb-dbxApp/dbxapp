# dbxApp 4.1.3

Version 4.1.3 ist das abschließende Update-Kompatibilitätsrelease der
4.1-Linie. Es enthält alle Funktionen und Migrationskorrekturen aus 4.1.0 bis
4.1.2 und sichert zusätzlich den Übergang zwischen bereits aktiven und
bereitgestellten Klassen ab.

## Idempotenter Migrations-Bootstrap

- `dbxContentMediaUsageScope` kann aus dem verifizierten Stagingbaum geladen
  werden, auch wenn dieselbe Klasse in der aktiven 4.1-Installation bereits
  existiert.
- Die Klassendatei prüft vor der Deklaration ausdrücklich den laufenden
  Prozess. Unterschiedliche aktive und gestagte Dateipfade führen dadurch
  nicht mehr zu einer doppelten PHP-Klassendeklaration.
- Der Regressionstest prüft diesen Vertrag gemeinsam mit der physischen
  `content_lng`-Reparatur und der Staging-DD-Auswahl.

## Enthaltener Funktionsumfang

4.1.3 enthält die sprachsichere Medienverwendung, idempotente
Shop-Medien-Synchronisierung, erweiterte Medien- und Thumbnailwartung, den
vollständigen `dbxSelfTest` sowie die Performance- und Bedienungsverbesserungen
im CMS.

Nach dem Update sollte ein Administrator im CMS einmal **Medienwartung**
ausführen. Ein zweiter Lauf muss ohne neu hinzugekommene Korrekturen enden.
