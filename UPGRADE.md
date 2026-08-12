# dbxApp 4.2 aktualisieren

dbxApp 4.2.0 ist die erste unterstützte Produktversion. Es gibt keinen
Update-, Import- oder Migrationspfad aus einer früheren dbxApp-Version. Der
Einstieg in die 4.2-Produktlinie erfolgt ausschließlich als Neuinstallation.

## Updates ab 4.2.0

Ab dem ersten Folgerelease steht unter **dbxAdmin → Status & Health →
System-Update** der verbindliche Ablauf bereit:

1. Der Updater prüft Manifest-Schema, Produkt, Kanal, die erforderliche
   dbxApp-Mindestbasis, PHP und Erweiterungen.
2. Das Paket wird per HTTPS geladen und über SHA-256, ZIP-Pfade, Symlinks,
   Dateiinventar und Einzeldatei-Hashes validiert.
3. Das geprüfte Paket wird isoliert bereitgestellt. Bis zur Installation kann
   es vollständig verworfen werden.
4. Vor der Installation werden alle betroffenen Produktdateien gesichert.
5. Neue Produktdateien werden installiert und nicht mehr enthaltene
   Produktdateien kontrolliert entfernt.
6. Enthält das Release eine Vorwärtsmigration, wird sie ausschließlich über
   dbxDB/dbxDD ausgeführt und gemeinsam mit den Dateien zurückgerollt, falls
   ein Fehler auftritt.
7. Erst die bestätigte neue `VERSION` schließt das Update ab.

`UPDATE_BASELINE` ist die maschinenlesbare Untergrenze. Ein Release darf diese
Grenze für spätere Updates anheben; Installationen unter der im Manifest
genannten Mindestversion werden nicht automatisch übersprungen.

## Nicht überschreibbare lokale Daten

Release-Pakete enthalten keine lokalen Konfigurationen, DB3-/SQL-Daten,
Uploads, Medien, Sessions, Caches, Logs, Schlüssel oder Kundendateien.
Insbesondere bleiben `.env`, `config.local.php`, `files/`, Datenbankpfade und
installationsbezogene Menü-Templates außerhalb des Produktupdates.

## Rollback

Der integrierte Rollback stellt die zum letzten Update gehörenden Dateien und
gegebenenfalls Datenbanksicherungen als zusammengehörenden Stand wieder her.
Eine Anwendungsversion darf nie mit einem unpassenden Datenbankstand gemischt
werden.
