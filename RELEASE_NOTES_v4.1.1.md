# dbxApp 4.1.1

Version 4.1.1 ist das Update-Kompatibilitätsrelease für dbxApp 4.1.0.
Es enthält den vollständigen Funktionsumfang von 4.1.0 und korrigiert den
Updatepfad von bestehenden 4.0.x-Installationen.

## Korrektur des Updatepfads

- Die Migration `core-4.1.0-media-usage-language` lädt ihre neue
  Sprachkontext-Abhängigkeit ausdrücklich aus dem geprüften Release-Paket.
- Damit kann die Datenmigration bereits in der atomaren Vorbereitungsphase
  ausgeführt werden, obwohl die bisherigen Programmdateien noch aktiv sind.
- Der Updater behält sein gemeinsames Datei-/Datenbank-Rollback bei: Ein
  Fehler lässt die bestehende Installation unverändert.
- Ein Regressionstest sichert den Upgradefall künftig ausdrücklich ab.

## Enthaltener Funktionsumfang

4.1.1 enthält außerdem alle Neuerungen von 4.1.0: sprachsichere
Medienverwendung, idempotente Shop-Medien-Synchronisierung, erweiterte
Medien- und Thumbnailwartung, den vollständigen `dbxSelfTest` sowie die
Performance- und Bedienungsverbesserungen im CMS.

Nach dem Update sollte ein Administrator im CMS einmal **Medienwartung**
ausführen. Dieser Lauf gleicht die Nutzungen mit allen Content-Sprachen und
den Shop-Artikelbildern ab, repariert Vorschaubilder und optimiert die
Datenbankdatei.
