# RAD und Runtime-IDE {#dbxapp_rad_runtime_ide}

dbXapp ist auch ein RAD-System. RAD bedeutet hier: Funktionen werden schnell aus
DD/FD, Templates, Modulen, Formularen und Reports zusammengesetzt, ohne fuer
jede Liste oder jedes Formular die komplette Infrastruktur neu zu schreiben.

## Warum dbXapp eine Runtime-IDE ist

dbXapp kann viele Entwicklungs- und Pflegeaufgaben in der laufenden Anwendung
ausfuehren:

- Templates im Browser bearbeiten.
- Content-Seiten im CMS pflegen.
- DD- und FD-Strukturen bearbeiten.
- Tabellen synchronisieren.
- Reports und Formulare direkt im Zielkontext pruefen.
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
| `dbx_edit=2` | erweiterter Bearbeitungsmodus fuer strukturierte Bearbeitung |
| `dbx_edit=9` | Runtime-IDE/Entwicklermodus fuer tiefe Bearbeitung und Diagnose |

Wichtig:

- Bei der normalen Webseitenausgabe muss der Interpreter auch mit
  `dbx_edit > 0` laufen.
- Nur im Template-Editor selbst duerfen `[modul=...]` und Marker nicht
  ausgewertet werden.
- Der Template-Editor bearbeitet die Datei aus `/tpl`, nicht das gerenderte DOM.

## Was dbx_edit=9 bedeutet

`dbx_edit=9` ist der tiefste Runtime-IDE-Modus. Er dient Entwicklern und Admins,
die direkt an Struktur und Ausgabe arbeiten.

Typische Aufgaben:

- Templatequelle pruefen.
- Modulbereiche lokalisieren.
- DD/FD-Strukturen kontrollieren.
- Layoutprobleme auf die richtige Template-Datei zurueckfuehren.
- Runtime-Zusammenspiel von Content, Modulen und Reports verstehen.

`dbx_edit=9` ist nicht fuer normale Endanwender gedacht.

## RAD-Workflow

Ein typischer RAD-Ablauf:

```text
DD definieren
  -> FD fuer Formularsicht ergaenzen
  -> dbxForm oder dbxReport verwenden
  -> Template erstellen
  -> Modulroute einbauen
  -> im Content oder Admin einbinden
  -> im dbx_edit-Modus pruefen
```

## Vorteil

Der Entwickler baut nicht jedes Mal eine neue Mini-Anwendung. Er kombiniert
vorhandene Bausteine:

- DD/FD fuer Struktur.
- `dbxDB` fuer Daten.
- `dbxForm` fuer Bearbeitung.
- `dbxReport` fuer Listen.
- `dbxTPL` fuer Ausgabe.
- `ajax.js`, `confirm.js`, `openWin.js` fuer Browserverhalten.

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
