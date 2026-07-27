# DB3-MySQL-DB3-Roundtrip {#dbxapp_db_roundtrip}

**Durchgeführt:** 24. Juli 2026  
**Ausgangsdatenbank:** `dbx/modules/dbxContact/db/dbxContact.db3`  
**MySQL-Ziel:** `dbxRoundtrip` / Datenbank `dbx_roundtrip`

## Ergebnis

Die SQLite-Datenbank des Moduls `dbxContact` wurde gesichert, über den
dbxAdmin-Transfer nach MySQL übertragen, dort über die echten
`dbxContact_admin`-Abläufe gelesen und schreibend getestet und anschließend in
eine neu erzeugte DB3 zurückübertragen. Die beiden DDs zeigen abschließend
wieder auf:

```php
$table['server']='dbxContact|dbxContact.db3';
```

Die zurückübertragene DB3 ist integer, strukturell gleich und fachlich
datengleich zum Original. Die einzigen Rohwertabweichungen sind leere
Zeitwerte: Ein ursprünglicher leerer String wurde in MySQL korrekt als SQL
`NULL` gespeichert und kam deshalb als `NULL` zurück. Dies verhindert
Zero-Date-Werte und Fehler unter strikten MySQL-SQL-Modi.

## Verbindliche Architektur

Der gesamte fachliche Datenzugriff blieb innerhalb der dbXapp-Schichten:

- Verbindung, Abfragen und Transaktionen über `dbxDB`
- Schema, Backup, Restore und Transfer über `dbxDD`
- Admin-Oberfläche und Transferdialog über die vorhandenen
  dbxAdmin-Templates und -Formulare
- fachlicher Nutzungstest über das bestehende `dbxContact_admin`-Modul mit
  `dbxReport`, `dbxForm` und `dbxTPL`

Es wurde weder eine direkte PDO-Abfrage außerhalb von `dbxDB` ergänzt noch ein
paralleler Transferweg gebaut. Die GET-Aufrufe und Modulparameter blieben
unverändert. Ein zusätzlicher `dbx_token` war nicht erforderlich: Der
vorhandene Admin- und Modulzugriff prüft seine Berechtigungen bereits selbst.

## Gesicherter Ausgangsstand

Der unveränderte Ausgangsstand liegt unter:

```text
files/sys/db-roundtrip/20260724-dbxContact/
  dbxContact.original.db3
  contactRequest.original.dd.php
  contactMessage.original.dd.php
```

SHA-256 des Originals:

```text
9E6FA833F71A2CCB37D49C70E06CF66CF8C2617D184283ABC456BA7DFC75B5DA
```

Die fertig zurückübertragene Datei wurde zusätzlich als
`dbxContact.final.db3` archiviert. Ihr Hash ist mit der aktuell eingesetzten
Modul-DB3 identisch:

```text
57D81550F63ADED7B1EEA8DA9F1626C2EFFD9B3F5FF5E178816CF5556D099A54
```

Ein anderer Datei-Hash ist bei einer neu aufgebauten SQLite-Datei normal:
Seitengröße, freie Seiten, B-Tree-Anordnung und `VACUUM` sind keine fachlichen
Daten. Deshalb wurde zusätzlich ein struktur- und datenbezogener Vergleich
durchgeführt.

## Ablauf

1. Original-DB3 und beide DD-Dateien wurden vor der ersten Änderung gesichert.
2. Der MySQL-Dienst, PDO-Treiber und die Verbindung wurden geprüft.
3. Für den isolierten Test wurde der Server `dbxRoundtrip` verwendet. Der
   produktive Standardeintrag `dbxApp` bleibt deaktiviert, weil seine effektiv
   geladene lokale Zugangskonfiguration nicht authentifiziert. Damit erzeugt
   eine bekannte Fehlkonfiguration keine Laufzeitfehler.
4. Über
   `?dbx_modul=dbxAdmin&dbx_run1=db&dbx_run2=list_db&dbx_page=admin`
   wurden `contact_request` und `contact_message` nach MySQL transferiert.
5. `contactRequest.dd.php` und `contactMessage.dd.php` zeigten während des
   Nutzungstests auf `dbxRoundtrip`.
6. Der echte Kontakt-Report las 4 Anfragen aus MySQL. Zusätzlich bestand ein
   transaktionaler DD-Test die Folge `INSERT`, `SELECT`, `UPDATE`, `DELETE`,
   `ROLLBACK`; danach waren weiterhin genau 4 Datensätze vorhanden.
7. Eine neue leere `dbxContact.db3` wurde angelegt und beide Tabellen wurden
   über denselben dbxAdmin-Transfer zurückübertragen.
8. Beide DDs wurden auf `dbxContact|dbxContact.db3` zurückgestellt.
9. Report, DD-CRUD, Integrität und logischer Vergleich wurden erneut gegen die
   neue SQLite-Datei ausgeführt.
10. Der ausschließlich lokal verwendete Admin-Test-Bypass wurde entfernt.
    Nach Logout zeigt der Admin-Link wieder nur die Anmeldung.

## Vergleichsergebnis

| Prüfung | `contact_request` | `contact_message` |
| --- | ---: | ---: |
| Datensätze Original / Neu | 4 / 4 | 5 / 5 |
| Spalten gleich | ja | ja |
| Felddefinitionen gleich | ja | ja |
| Indizes gleich | ja | ja |
| semantische Nutzdaten gleich | ja | ja |

Beide Dateien liefern bei `PRAGMA integrity_check` den Wert `ok`. Die
semantischen Daten-Hashes sind:

```text
contact_request
24f3120aa31e2a8ecfbe1b70a8d555eb17dfb215b00ce2248ae2f65e74c6b068

contact_message
fa14fb9761a952dd2405b52b0e11ee234535cd4dd32fa1e5c81daee99baa5675
```

Rohwertabweichungen bestehen ausschließlich in `''` zu `NULL`:

- `contact_request.closed_date`: 3 Werte
- `contact_request.confirm_mail_sent_date`: 1 Wert
- `contact_request.mail_sent_date`: 3 Werte
- `contact_request.user_hidden_date`: 2 Werte
- `contact_message.mail_sent_date`: 5 Werte

Alle vorhandenen Datumswerte einschließlich Millisekunden sind erhalten. Der
Browser zeigt beispielsweise wieder `07.06.2026 10:46:48.854` und nicht eine
künstlich aufgefüllte Darstellung mit sechs Nachkommastellen.

## Notwendige zentrale Korrekturen

Die Korrekturen liegen bewusst an den gemeinsamen Stellen, damit alle Module
denselben einfachen und sicheren Ablauf nutzen:

- `dbxDB::connect_db_server()` erkennt eine bereits geöffnete dynamische
  Modul-SQLite-Verbindung wieder. Zuvor konnte der zweite Zugriff auf denselben
  dynamischen Server trotz gültiger PDO-Verbindung fehlschlagen.
- Die ursprüngliche DB-Fehlermeldung bleibt nach dem Schreiben einer
  Systemmeldung erhalten; ein erfolgreicher interner Insert überschreibt sie
  nicht mehr.
- `dbxDD` erzeugt MySQL-Temporaltypen mit sechsstelliger Genauigkeit, damit
  vorhandene Bruchteile nicht abgeschnitten werden.
- DD-Backups speichern zusätzlich die Quellfeldtypen. Alte Backups ohne diese
  Metadaten bleiben lesbar.
- Der Restore wandelt leere MySQL-Zeitwerte in `NULL` und normalisiert beim
  SQLite-Rückweg nur technisch angehängte Nullstellen der Sekundenbruchteile.
  Inhaltliche Werte bleiben unverändert.

## Reproduzierbare Prüfungen

```text
php dbx/include/tests/dbxDB_dynamic_reconnect_test.php
php dbx/include/tests/dbxDD_restore_temporal_test.php
php dbx/include/tests/db_access_boundary_test.php
php tools/check-mysql.php dbxRoundtrip
php tools/pdo-mysql-test.php dbxRoundtrip
php tools/db-roundtrip-compare.php <Original-Server> <Neu-Server> contact_request,contact_message
```

`tools/db-roundtrip-compare.php` verwendet ausschließlich `dbxDB` und
`dbxDD`. Es prüft Integrität, Tabellen, Spalten, Felddefinitionen, Indizes,
Rohwerte und semantisch normalisierte Nutzdaten.

Abschlussstand des automatisierten Testlaufs:

| Testgruppe | Ergebnis |
| --- | --- |
| geänderte PHP-Dateien | 10/10 ohne Syntaxfehler |
| PHP-Laufzeitdateien ohne `vendor` | 381/381 ohne Syntaxfehler |
| PHP-Regressionstests | 36/36 bestanden |
| JavaScript-Regressionstests | 2/2 bestanden |
| `dbxRoundtrip` über `dbxDB` | erreichbar, Datenbank `dbx_roundtrip`, 2 Tabellen |
| Original-vs.-Final-Vergleich | Exit-Code 0, `equal: true` |
| Browser: MySQL-Report | 4 Datensätze, keine DB-Fehler |
| Browser: neue DB3 | 4 Datensätze, keine DB-Fehler |
| Browser nach Bypass-Entfernung | Admin-Link zeigt Anmeldung |

Die Datei `dbx/modules/dbx/tpl/php/dd_file.php` ist eine Generatorvorlage mit
noch nicht ersetzten `{...}`-Markern und deshalb bewusst keine eigenständig
lintbare PHP-Laufzeitdatei.

## Wiederherstellung

Bei einem später erkannten Problem kann die aktuelle Modul-DB3 nach vorheriger
Sicherung durch `dbxContact.original.db3` aus dem Archiv ersetzt werden.
Anschließend sind die beiden archivierten DD-Dateien zurückzuspielen und
`PRAGMA integrity_check` sowie der Kontakt-Report zu prüfen. Das
`dbx_roundtrip`-Schema bleibt als isoliertes, funktionierendes MySQL-Testziel
erhalten; kein produktives DD verweist abschließend darauf.
