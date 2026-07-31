# dbxapp-Dokumentationsportal

@page dbxapp_documentation_portal Dokumentationsportal betreiben und veröffentlichen

## Ziel

Das Dokumentationssystem unter `doku.dbxapp.de` verbindet zwei Arten von
Dokumentation, ohne sie technisch zu vermischen:

1. **Redaktionelle Dokumentation in dbxContent**
   Handbücher, Tutorials, Architekturtexte, Betriebsanweisungen, Screenshots
   und Medien werden mit den normalen dbxapp-Werkzeugen gepflegt. Die
   maßgebliche deutsche Fassung bleibt die Quelle für Übersetzungen.
2. **Generierte Quellcode-Referenz in Doxygen**
   Klassen, Namespaces, Dateien, Methoden und Codebeispiele werden ohne
   redaktionelle Doppelpflege aus dem versionierten Quellbestand erzeugt.

Das CMS ist das Portal. Doxygen wird als statischer, versionierter Bereich
unter `reference/current/` eingebunden. Dadurch bleiben Suche, Klassenlinks und
Quellreferenzen von Doxygen erhalten. Die Referenz wird über das Modul
`dbxDocs` im Portal eingebettet und kann zusätzlich direkt geöffnet werden.

## Verzeichnisstruktur

```text
C:\xampp\htdocs\dbxapp\                 lokale Entwicklungsquelle
C:\xampp\htdocs\dbxapp-docs\            eigenständige Dokumentationsinstallation
├── index.php                            dbxapp-/dbxContent-Portal
├── dbx\design\dbxdocs\                  blaues Dokumentationsdesign
├── files\media\                         Tutorialmedien
└── reference\current\                   erzeugte Doxygen-Ausgabe
```

Die vorherige statische Ausgabe wurde bei der Umstellung nicht gelöscht,
sondern als datierter Ordner
`C:\xampp\htdocs\dbxapp-docs-doxygen-backup-<Zeitstempel>` gesichert.

## Design- und Navigationsvertrag

Das Design `dbxdocs` verwendet:

- das linke, einklappbare Grundlayout des Designs `flowers`;
- Farben, Komponenten, Formulare, Reports und Fensterdarstellung des blauen
  dbxapp-Designs;
- eine eigene, sprachabhängige Dokumentationsnavigation;
- getrennte Einstiege für Anwender, Entwickler und die drei KI-Bereiche
  Content, Design und Module.

Die linke Navigation wird durch diese Modul-Templates bereitgestellt:

- `dbxMenu|dbx-docs-main` für Deutsch;
- `dbxMenu|dbx-docs-main_en` für Englisch;
- `dbxMenu|dbx-docs-main_es` für Spanisch.

Die CMS-Ordner sind sprachabhängig und bilden echte Untermenüs. Deutsch
verwendet die Bereiche Einstieg, Tutorials, Content & KI, Design, Shop,
Workflows, Entwicklung, Betrieb & Sicherheit und Service. Seiten besitzen
neben dem vollständigen Titel einen kurzen `menu_title`.

Doxygen ist statisch und sprachneutral versioniert. Im Menü erscheinen nur
die generierten Bereiche Übersicht, Klassen, Namespaces, Dateien und
Beispiele.

## Lokale Konfiguration

Die Dokumentationsinstallation verwendet ausschließlich eigene lokale
Konfigurationen und eigene Datenbanken. Produktive Geheimnisse,
`config.local.php`-Dateien und Benutzer-Datenbanken der Entwicklungsinstanz
werden nicht kopiert.

Verbindliche Werte der Dokumentationsinstallation:

```php
$config['default_design_user']  = 'dbxdocs';
$config['default_design_admin'] = 'dbxdocs';
$config['default_color']        = 'blau';
```

`dbxHome` startet mit der deutschen Tutorialübersicht `cid=79`.
dbxContent löst die zugehörigen Sprachseiten über `lng_uid` auf.

## Datenbereitstellung

Die Installation wird aus DDs aufgebaut:

1. Anwendungscode ohne lokale Geheimnisse und ohne produktive DB3 kopieren.
2. Nur die freigegebenen Content- und Medienbestände übernehmen.
3. Alle übrigen Tabellen über `dbxInstallationService::provisionSchema()`
   und damit über `dbxDD`/`dbxDB` erzeugen.
4. Systemgruppen idempotent mit `seedCoreGroups()` anlegen.
5. Den lokalen Administrator einmalig mit `createAdmin()` erzeugen.

Direkte SQL-Dumps oder direkter PDO-/SQLite-Zugriff gehören nicht zu diesem
Prozess. Jede DD kann weiterhin einen eigenen DB3- oder MySQL-Server verwenden.

## Doxygen erzeugen

Aus der lokalen Wahrheit `C:\xampp\htdocs\dbxapp`:

```powershell
Set-Location C:\xampp\htdocs\dbxapp
doxygen Doxyfile
```

`Doxyfile` schreibt nach:

```text
C:\xampp\htdocs\dbxapp-docs\reference\current\
```

Die Doxygen-Kopfleiste besitzt für den direkten Aufruf den Link **Portal**
zurück zur dbxContent-Startseite. Innerhalb des Portals blendet `dbxDocs`
doppelte Navigation und Branding aus. Interne Doxygen-Querverweise bleiben im
eingebetteten Referenzfenster.

## Veröffentlichung

Vor einer Veröffentlichung:

1. Quelltests und Doxygen-Tests ausführen.
2. `doxygen Doxyfile` ohne Fehler beenden.
3. Portal, mindestens ein Tutorialbild und `reference/current/` per HTTP
   auf Status 200 prüfen.
4. CMS-Untermenüs und die eingebetteten Bereiche Klassen, Namespaces,
   Dateien und Beispiele prüfen.
5. `files/dbxError.log` prüfen; eine vorhandene, nicht leere Datei bedeutet
   Systemstatus **Fehler**.
6. Erst danach die Dokumentationsinstallation nach `doku.dbxapp.de`
   übertragen.

Die Doxygen-Ausgabe ist reproduzierbar und wird nicht manuell bearbeitet.
Redaktionelle Änderungen erfolgen ausschließlich in dbxContent.
Quellcode-Kommentare werden lokal geändert und beim nächsten Doxygen-Lauf
automatisch in die Referenz übernommen.

## Sitzungsisolation

dbxapp leitet Cookie-Pfad und Sessionname automatisch aus Host und
Installationspfad ab. Dadurch teilen sich parallele Installationen wie
`/dbxapp/` und `/dbxapp-docs/` keine Sitzung, keine Anmeldung und kein
gewähltes Design. Auf Subdomains funktioniert dieselbe Regel ohne
Sonderkonfiguration.

## Abnahmekriterien

- linke Navigation sichtbar und per Tastatur bedienbar;
- Navigation kann ein- und ausgeklappt werden;
- blaues dbxapp-Erscheinungsbild ohne flowers-spezifische Gestaltung;
- deutsche, englische und spanische CMS-Navigation lädt;
- alle Tutorialmedien werden über dbxContent ausgeliefert;
- Doxygen unter `reference/current/` erreichbar und im Portal eingebettet;
- Portal-Rücklink in Doxygen funktioniert;
- keine unbearbeiteten Template-Platzhalter;
- keine PHP-Syntaxfehler und kein neuer Eintrag in `dbxError.log`.
