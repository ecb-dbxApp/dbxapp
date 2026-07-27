# dbxApp aktualisieren

Dieses Verfahren gilt ausschließlich für Updates innerhalb der neuen
dbxApp-4-Produktlinie. dbxApp 4 ist eine vollständige Neuentwicklung und kein
In-place-Upgrade von dbxWebApp. Eine Übernahme von dbxWebApp-Daten muss, falls
sie künftig benötigt wird, als gesonderter Import mit eigener Prüfung,
Sicherung und Dokumentation entwickelt werden.

## Update über dbxAdmin

dbxApp 4.0.2 ist die erste updaterfähige Referenzversion. Von dieser Basis aus
steht unter **dbxAdmin → Status & Health → System-Update** für spätere stabile
dbxApp-4-Releases der verbindliche Standardablauf bereit:

1. **Update automatisch vorbereiten** prüft das feste Manifest aus
   `ecb-dbxApp/dbxapp`, lädt ein neueres ZIP in den isolierten Staging-Bereich
   und kontrolliert HTTPS-Ziel, Version, PHP-Anforderungen, Größe, SHA-256,
   ZIP-Pfade, Symlinks, Dateiinventar und jede einzelne Datei.
2. Danach gibt es genau zwei sichere Möglichkeiten:
   **Jetzt sicher installieren** oder **Update stoppen**.
3. **Update stoppen** entfernt das heruntergeladene ZIP, den entpackten
   Staging-Bereich und dessen Status. Installierte Programmdateien wurden bis
   dahin nicht verändert.
4. **Jetzt sicher installieren** sichert alle betroffenen Programmdateien und
   ersetzt danach den geprüften Release-Stand. Der kurze Dateiaustausch darf
   nicht manuell abgebrochen werden; ein Fehler löst automatisch das
   dateibasierte Rollback aus.
5. **Letztes Update zurückrollen** stellt bei Bedarf die letzte
   Dateisicherung wieder her.

Damit sind Release-Prüfung, Download und vollständige Paketprüfung in einem
einzigen Startschritt automatisiert. Die bewusste Entscheidung unmittelbar
vor der ersten Änderung installierter Dateien bleibt beim Administrator.

Dashboard-Menü, `Status & Health` und Schnellzugriff zeigen vier eindeutige
Zustände: **prüfen**, **aktuell**, **neu** und **bereit**. Sie lesen nur den
lokalen Zustand in `files/update`; deshalb bleiben Dashboard und Menü auch
ohne GitHub-Verbindung schnell und funktionsfähig. Bei **bereit** führt der
Link zurück zur Entscheidung **installieren** oder **Update stoppen**.

Lokale Konfigurationen, DB3-/MySQL-Datenbanken, Uploads, Sessions, Caches und
Logs sind ausdrücklich kein Bestandteil des Release-Pakets. Der Updater
greift nie direkt auf eine Datenbank zu.

### Datenbankänderungen

Der automatische Ablauf ist absichtlich ein Dateiupdate. Ersetzt ein Release
DDs oder benötigt eine Datenmigration, nennen die Release Notes die
zusätzlichen Schritte. Migrationen laufen ausschließlich über `dbxDB` und DD;
vorher werden die betroffenen DB3-Dateien beziehungsweise MySQL-Dumps
gesichert. Ohne ausdrücklich dokumentierte Migration wird keine Datenbank
verändert.

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

Ein direktes Update von dbxWebApp auf dbxApp 4 ist kein unterstützter Schritt
dieses Verfahrens.

## DB3

Vor jeder Schemaänderung wird die betreffende DB3-Datei vollständig gesichert.
Nach der Migration werden mindestens Tabellen, Datensatzanzahlen und zentrale
Feldwerte mit der Sicherung verglichen.

## MySQL

Vor dem Update wird ein konsistenter Datenbankdump erstellt. Zeichensatz,
Collation und Transaktionsfähigkeit müssen den Release-Hinweisen entsprechen.
Alle Anwendungszugriffe laufen weiterhin ausschließlich über `dbxDB`.

## Rückkehr zur vorherigen Version

Bei einem reinen Dateiupdate genügt die in dbxAdmin angebotene letzte
Dateisicherung. Sobald eine Datenmigration ausgeführt wurde, besteht ein
vollständiges Rollback aus zwei zusammengehörenden Teilen:

- vorherige Anwendungsdateien wiederherstellen,
- dazu passende Datenbanksicherung wiederherstellen.

Eine neue Anwendungsversion darf nicht mit einer bereits migrierten, aber
inkompatiblen alten Datenbank vermischt werden.
