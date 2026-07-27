# dbxApp aktualisieren

## Grundregeln

1. Release Notes und dieses Dokument vollständig lesen.
2. Anwendung während der Datenmigration in den Wartungsmodus versetzen.
3. Dateien, lokale Konfiguration und alle Datenbanken sichern.
4. Release-ZIP und SHA-256-Prüfsumme von GitHub verwenden.
5. Lokale `.env`, `config.local.php`, Medien und Datenbanken nicht
   überschreiben.
6. dbxSetup beziehungsweise die dokumentierten Migrationen ausführen.
7. Login, Formular, Report, Token-Aktionen und zentrale Module testen.
8. Prüfen, dass `files/dbxError.log` nicht vorhanden ist.

## DB3

Vor jeder Schemaänderung wird die betreffende DB3-Datei vollständig gesichert.
Nach der Migration werden mindestens Tabellen, Datensatzanzahlen und zentrale
Feldwerte mit der Sicherung verglichen.

## MySQL

Vor dem Update wird ein konsistenter Datenbankdump erstellt. Zeichensatz,
Collation und Transaktionsfähigkeit müssen den Release-Hinweisen entsprechen.
Alle Anwendungszugriffe laufen weiterhin ausschließlich über `dbxDB`.

## Rückkehr zur vorherigen Version

Ein Rollback besteht aus zwei zusammengehörenden Teilen:

- vorherige Anwendungsdateien wiederherstellen,
- dazu passende Datenbanksicherung wiederherstellen.

Eine neue Anwendungsversion darf nicht mit einer bereits migrierten, aber
inkompatiblen alten Datenbank vermischt werden.
