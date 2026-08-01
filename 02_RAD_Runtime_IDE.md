# RAD und Runtime-IDE {#dbxapp_rad_runtime_ide}

dbxapp ist auch ein RAD-System. RAD bedeutet hier: Funktionen werden schnell aus
DD/FD, Templates, Modulen, Formularen und Reports zusammengesetzt, ohne für
jede Liste oder jedes Formular die komplette Infrastruktur neu zu schreiben.

## Warum dbxapp eine Runtime-IDE ist

dbxapp kann viele Entwicklungs- und Pflegeaufgaben in der laufenden Anwendung
ausführen:

- Templates im Browser bearbeiten.
- Content-Seiten im CMS pflegen.
- DD- und FD-Strukturen bearbeiten.
- Tabellen synchronisieren.
- Reports und Formulare direkt im Zielkontext prüfen.
- Modul-Inclusions im Content setzen.
- Admin-Panels und Dashboard-Bereiche aus Modulaufrufen zusammensetzen.

Die Runtime-IDE arbeitet also nicht nur mit Sourcecode, sondern auch mit
Formularen, Reports und Strukturmetadaten.

@image html dbxapp-template-editor-flow.svg "Template-Editor und dbx_edit Ablauf"

## dbx_edit Modi

`dbx_edit` steuert, wie stark die Ausgabe editierbar oder technisch sichtbar ist.

| Modus | Bedeutung |
| --- | --- |
| `dbx_edit=0` | normale Ausgabe |
| `dbx_edit=1` | Webseite wird normal gerendert, editierbare Bereiche werden markiert |
| `dbx_edit=2` | erweiterter Bearbeitungsmodus für strukturierte Bearbeitung |
| `dbx_edit=9` | Runtime-IDE/Entwicklermodus für tiefe Bearbeitung und Diagnose |

Wichtig:

- Bei der normalen Webseitenausgabe muss der Interpreter auch mit
  `dbx_edit > 0` laufen.
- Nur im Template-Editor selbst dürfen `[modul=...]` und Marker nicht
  ausgewertet werden.
- Der Template-Editor bearbeitet die Datei aus `/tpl`, nicht das gerenderte DOM.

## Was dbx_edit=9 bedeutet

`dbx_edit=9` ist der tiefste Runtime-IDE-Modus. Er dient Entwicklern und Admins,
die direkt an Struktur und Ausgabe arbeiten.

Typische Aufgaben:

- Templatequelle prüfen.
- Modulbereiche lokalisieren.
- DD/FD-Strukturen kontrollieren.
- Layoutprobleme auf die richtige Template-Datei zurückführen.
- Runtime-Zusammenspiel von Content, Modulen und Reports verstehen.

`dbx_edit=9` ist nicht für normale Endanwender gedacht.

## RAD-Workflow

Ein typischer RAD-Ablauf:

```text
DD definieren
  -> FD für Formularsicht ergänzen
  -> dbxForm oder dbxReport verwenden
  -> Template erstellen
  -> Modulroute einbauen
  -> im Content oder Admin einbinden
  -> im dbx_edit-Modus pruefen
```

## Vorteil

Der Entwickler baut nicht jedes Mal eine neue Mini-Anwendung. Er kombiniert
vorhandene Bausteine:

- DD/FD für Struktur.
- `dbxDB` für Daten.
- `dbxForm` für Bearbeitung.
- `dbxReport` für Listen.
- `dbxTPL` für Ausgabe.
- `ajax.js`, `confirm.js`, `openWin.js` für Browserverhalten.

Dadurch bleibt Fachlogik klein und die Anwendung einheitlich.

## Zentrales Fehlerprotokoll im Admin-Dashboard

Das Admin-Dashboard behandelt `files/dbxError.log` als verbindliches
Fehlersignal:

- Sobald die Datei vorhanden ist, wird der Systemzustand auf `FEHLER` gesetzt.
- Das vollständige Protokoll erscheint HTML-maskiert in einem vertikal
  vergrößerbaren Scrollbereich.
- Der Löschbutton entfernt ausschließlich die von `dbx()->get_file_dir()`
  gelieferte Datei. Ein Dateipfad aus Request-Daten ist nicht zulässig.
- Die Delete-URL wird mit `dbx()->action_url()` erzeugt. Dadurch ergänzt und
  prüft dbxApp den Aktionstoken automatisch.
- Titel, Status-, Bestätigungs-, Erfolgs- und Fehlermeldungen kommen aus
  `dbxAdmin|admin-dashboard-status` und stehen in Deutsch, Englisch und
  Spanisch bereit.
- Nach erfolgreicher Löschung wird der Dashboardzustand neu berechnet. Andere
  vorhandene Warnungen, Systemmeldungen oder DD-Probleme bleiben dabei
  selbstverständlich wirksam.

Der gemeinsame Dateizugriff liegt in `dbxSysMsg`. Dashboard und
Systemmeldungs-Report verwenden deshalb dieselbe fest verdrahtete und
überprüfte Löschoperation.
