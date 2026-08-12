# dbxapp-Dokumentationsportal

@page dbxapp_documentation_portal Dokumentationsportal betreiben und veröffentlichen

## Ziel

Das Dokumentationssystem der aktuellen dbxapp-Installation verbindet zwei
Arten von Dokumentation, ohne sie technisch zu vermischen:

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

Alle öffentlichen Dokumentationsseiten liegen kanonisch auf der Hauptdomain
unter `https://dbxapp.de/dokumentation/`. Eine eigene Dokumentations-Subdomain
ist nicht Bestandteil der Zielarchitektur.

## Verzeichnisstruktur

```text
<dbxapp-quellverzeichnis>\               Entwicklungs- und Laufzeitinstallation
├── index.php                            dbxapp-/dbxContent-Portal
├── dbx\design\dbxdocs\                  blaues Dokumentationsdesign
├── files\media\                         Tutorialmedien
└── reference\current\                   erzeugte Doxygen-Ausgabe
```

Die Referenz gehört damit zur selben Installation wie die redaktionellen
Seiten. Eine zweite dbxapp-Kopie und eine Synchronisierung zwischen zwei
Installationen sind nicht erforderlich.

## Design- und Navigationsvertrag

Das Design `dbxdocs` verwendet:

- das linke, einklappbare Grundlayout des Designs `flowers`;
- Farben, Komponenten, Formulare, Reports und Fensterdarstellung des blauen
  dbxapp-Designs;
- eine eigene, sprachabhängige Dokumentationsnavigation;
- vier getrennte Einstiege für Anwender, Administratoren, Entwickler und KI.

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

## Designaufruf

Der Menüpunkt **Dokumentation** öffnet die Portal-Startseite mit
`/dokumentation/?dbx_design=dbxdocs`. Das Design gilt für Portal, Suche und
eingebettete Referenz. Vor dem Wechsel merkt dbxapp das aktive Website-Design
und dessen Skin separat. **Zurück zu dbXapp** in der linken Navigation sowie
**dbXapp** im Footer stellen genau dieses Paar wieder her. Die Anwendung bleibt
dieselbe Installation; Benutzer, Rechte, Sitzung und Content werden nicht
dupliziert.

Frühere URLs von `doku.dbxapp.de` werden mit HTTP 301 seitenweise auf denselben
Pfad unter `dbxapp.de/dokumentation/` geführt. Ebenso zeigen alte flache
Dokumentations- und Tutorial-Permalinks dauerhaft auf ihren exakten neuen
Bereichspfad; eine pauschale Weiterleitung nur auf die Startseite ist nicht
zulässig.

## Doxygen erzeugen

Im dbxapp-Wurzelverzeichnis:

```powershell
Set-Location <dbxapp-wurzelverzeichnis>
doxygen Doxyfile
php dbx/modules/dbxDocs/tools/build_docs_search_index.php
```

`Doxyfile` schreibt nach:

```text
<dbxapp-wurzelverzeichnis>\reference\current\
```

Die Ausgabe wird vollständig aus dem aktuellen Quellbestand reproduziert.
Der anschließende Indexlauf integriert Klassen, Methoden, Dateien und
Namespaces zusätzlich in die Suche des Moduls `dbxDocs`.

Die Doxygen-Kopfleiste besitzt für den direkten Aufruf den Link **Portal**
zurück zur dbxContent-Startseite. Innerhalb des Portals blendet `dbxDocs`
doppelte Navigation und Branding aus. Interne Doxygen-Querverweise bleiben im
eingebetteten Referenzfenster.

## Veröffentlichung

Vor einer Veröffentlichung:

1. Quelltests und Doxygen-Tests ausführen. Anschließend im Modul
   `dbxSelfTest` mindestens den Schnelltest, vor einer Veröffentlichung den
   Kompletttest starten.
2. `doxygen Doxyfile` ohne Fehler beenden.
3. Portal, mindestens ein Tutorialbild und `reference/current/` per HTTP
   auf Status 200 prüfen.
4. CMS-Untermenüs und die eingebetteten Bereiche Klassen, Namespaces,
   Dateien und Beispiele prüfen.
5. `files/dbxError.log` prüfen; eine vorhandene, nicht leere Datei bedeutet
   Systemstatus **Fehler**.
6. Erst danach die geprüfte dbxapp-Installation veröffentlichen.

Die Doxygen-Ausgabe ist reproduzierbar und wird nicht manuell bearbeitet.
Redaktionelle Änderungen erfolgen ausschließlich in dbxContent.
Quellcode-Kommentare werden lokal geändert und beim nächsten Doxygen-Lauf
automatisch in die Referenz übernommen.

Gezielt versionierte redaktionelle Seiten werden aus
`dbx/modules/dbxDocs/content/` provisioniert. Der wiederholbare Abgleich lautet:

```powershell
php dbx/modules/dbxDocs/tools/provision_docs_content.php
```

Der Lauf aktualisiert nur ausdrücklich revisionierte Seiten, ergänzt fehlende
Seiten und invalidiert danach den Content-Cache. Bestehende, nicht markierte
CMS-Redaktion wird ohne `--force` nicht überschrieben.

## Abnahmekriterien

- linke Navigation sichtbar und per Tastatur bedienbar;
- Navigation kann ein- und ausgeklappt werden;
- blaues dbxapp-Erscheinungsbild ohne flowers-spezifische Gestaltung;
- deutsche, englische und spanische CMS-Navigation lädt;
- alle Tutorialmedien werden über dbxContent ausgeliefert;
- Doxygen unter `reference/current/` erreichbar und im Portal eingebettet;
- kanonische Portal-URL und Unterseiten liegen unter `/dokumentation/`;
- alte Subdomain- und flache Dokumentations-URLs liefern exakte 301-Ziele;
- Installations- und SelfTest-Anleitung im Bereich Betrieb & Sicherheit
  erreichbar;
- Portal-Rücklink in Doxygen funktioniert;
- keine unbearbeiteten Template-Platzhalter;
- keine PHP-Syntaxfehler und kein neuer Eintrag in `dbxError.log`.
