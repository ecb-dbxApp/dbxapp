# Core-Klassen {#dbxapp_core_classes}

[Offizielle dbxapp Website](https://dbxapp.de)

Dieses Kapitel erklaert Sinn und typische Verwendung der wichtigsten
Systemklassen. Es ist keine vollständige API-Referenz; die Detailreferenz
erzeugt Doxygen direkt aus dem Source.

## Architekturentscheidung für große Kernklassen

`dbxDB`, `dbxDD`, `dbxForm` und `dbxReport` besitzen viele Fähigkeiten, weil
sie systemweite Fassaden und zustandsbehaftete Pipelines sind. Ihre öffentliche
API hält Module einfach und einheitlich.

| Beziehung | Architekturzweck |
| --- | --- |
| `dbxTPL extends dbxObj` | Templatezugriff nutzt gemeinsamen Objekt- und Systemkontext |
| `dbxDD extends dbxDB` | Schema-, Backup- und Transferprozesse verwenden denselben DB-Pfad |
| `dbxReport extends dbxForm` | Reportfilter verwenden dieselbe Feld-, FD- und Validierungspipeline |

Eine Teilung ist nicht allein wegen Zeilenzahl oder Methodenzahl sinnvoll.
Interne Verantwortungen dürfen erst extrahiert werden, wenn eine echte
Lebenszyklusgrenze, ein messbarer Nutzen, vollständige Regressionstests und
eine kompatible Fassade vorhanden sind. Fachmodule dürfen die internen
Teilklassen anschließend nicht direkt verwenden.

Die verbindliche Entscheidungsmatrix und das vollständige Zusammenspiel stehen
unter @ref dbxapp_module_reference.

## dbxApi / dbx()

`dbx()` ist der zentrale Zugriffspunkt.

Typische Aufgaben:

- Request- und Modulvariablen lesen.
- Remember- und Session-Werte setzen.
- Systemobjekte laden.
- Include-Objekte laden.
- Konfiguration lesen/schreiben.
- mutierende `delete`-/`save`-Links mit `rid` über `action_url()` automatisch
  erkennen und signieren.
- zentrale Hilfsfunktionen direkt nutzen; Escaping nur im tatsächlich
  notwendigen Ausgabekontext.

Beispiel:

```php
$run1 = dbx()->get_modul_var('dbx_run1', 'list');
$db   = dbx()->get_system_obj('dbxDB');
$cfg  = dbx()->get_cfg('dbx');
```

Für Konfiguration existiert genau eine API: `get_cfg()` liest Basis- und
lokale Werte, `set_cfg()` schreibt sie. Alte Parallelbezeichnungen sind nicht
Teil der Schnittstelle. Im Demo-Modus kann `get_cfg(..., true)` eine für die
Anzeige maskierte Kopie liefern; `set_cfg()` ist dann immer gesperrt.

## dbxWebApp

`dbxWebApp` führt den Request durch:

- Basis-URL bestimmen.
- Permalink oder Modulroute auflösen.
- automatisch erkannte und dbxReport-eigene Action-Policies vor dem
  Modulstart prüfen.
- schreibende dbxReport-Grid-Routen anhand ihrer Route erkennen, nicht anhand
  eines optionalen Transportmarkers.
- Design, Sprache, Editmodus bestimmen.
- Content oder Modul ausgeben.
- CSS/JS sammeln.
- Ausgabe finalisieren.

## dbxInterpreter

Der Interpreter verarbeitet Marker wie:

```html
[modul=dbxAdmin]dbx_run1=session&dbx_run2=list_session[/modul]
```

Er läuft für die Webseitenausgabe. Im Template-Editor darf er den
bearbeiteten Rohtext nicht ausführen.

Siehe auch: @ref dbxapp_dbxinterpreter.

## dbxTPL

Template-Engine für HTML-Templates, Marker und Template-Slots.

## dbxDB

Datenzugriff, DD-Laden, Rechte, Trace, Performance, Backup/Restore/Transfer.
Fachliche Änderungen laufen ausschließlich über `insert()`, `update()`,
`save()` und `delete()`. Im Demo-Modus verweigert die zentrale Rechteprüfung
diese Methoden. Explizite Systemaufrufe mit `verify_access=0`, insbesondere
der Session-Lebenszyklus, bleiben davon getrennt.

## dbxDD

DD-Synchronisation, DD-Modelle, Feld-/Indexstruktur, DB-zu-DD und DD-zu-DB.

## dbxForm

Formulare, Panels, Feldaufbau, Validierung, Meldungen, Save-Pipeline und
eigener rotierender POST-Schutz. `init()` übernimmt den direkten Aufrufer als
Callback-Owner; Methoden nach `{fid}_{event}` werden deshalb ohne Registrierung
gefunden. `add_rep()` und `replaces()` bilden die gemeinsame
Template-Replacement-Pipeline. Normale Formular-Actions benötigen keinen
zusätzlichen `dbx_token`.

## dbxReport

Listen, Suche, Sortierung, Pagination, Aktionen, Multi-Select, Grid-Modus und
automatische Signierung mutierender Standardaktionen. Im Grid werden Read-URLs
unverändert gelassen; Save, Insert, Delete, Sort und Sync werden nach der
verbindlichen Grid-Routenkonvention signiert und unbekannte Schreib-URLs
fail-closed verworfen. Berechnete Felder und
Summen gehören standardmäßig in `{fid}_next_record`; spät gesetzte
`add_rep()`-Werte stehen dem Footer zur Verfügung. `{rpt:col_count}` liefert
alle Spalten, `{rpt:colspan}` alle Spalten außer der letzten Wertespalte.
Explizite Owner- oder Callback-Setter sind nur für bewusst abweichende
Methodennamen nötig.

## dbxSession

Session-Verwaltung und optionale Session-DB. Normale HTTP-Requests und
HTML-AJAX-Requests können Sessionzustand am Request-Ende schreiben. Reine
JSON-AJAX-Aktionen müssen nicht zwingend Session/Performance schreiben.
Login und Logout verwerfen das Action-Token-Secret des vorherigen
Sicherheitskontexts.

## dbxMail

Mail-Versand über die konfigurierte Mail-Infrastruktur.

## dbxValidator

Validierung von Request-, Formular- und Feldwerten. Die aktuellen Fachregeln
enthalten unter anderem `email` für vollständige Internet-E-Mail-Adressen und
`permalink` für flache CMS-Permalinks aus Kleinbuchstaben, Zahlen und einzelnen
Bindestrichen.

## dbxUpload / dbxDownload

Upload-/Download-Funktionen, Dateipruefung und Bildbearbeitung.

## dbxProcess

Status- und Prozessverwaltung für laengere Operationen wie Sync, Import,
Transfer oder Batch-Aktionen.

## dbxUpdateService

Der dateibasierte System-Updater liegt zentral in `dbxAdmin`. Module bauen
keine eigenen Download-, Installations- oder Updatefunktionen.

Der normale Administrator-Ablauf besitzt zwei sichere Phasen:

1. **Update automatisch vorbereiten:** `prepare()` prüft das feste
   GitHub-Manifest, vergleicht die Version, lädt ein neueres Paket und prüft
   HTTPS-Ziel, PHP-Anforderungen, SHA-256, ZIP-Pfade, Symlinks,
   Dateiinventar und jede einzelne Datei. Installierte Programmdateien werden
   dabei noch nicht verändert.
2. **Entscheidung:** `install()` sichert und installiert das vorbereitete
   Paket. `cancel()` stoppt den Vorgang vorher und entfernt ZIP, Staging und
   Status vollständig.

Mutierende Updateoperationen werden durch eine zentrale Dateisperre
serialisiert. Der kurze Dateiaustausch darf nicht manuell unterbrochen werden:
Bei einem Fehler stellt `install()` automatisch die zuvor erzeugte
Dateisicherung wieder her. Nach einer erfolgreichen Installation steht
`rollback()` für die letzte Version zur Verfügung.

Der Updater greift niemals direkt auf DB3 oder MySQL zu. Datenänderungen
bleiben DD-gesteuerte `dbxDB`-Migrationen und müssen in den Release-Hinweisen
ausdrücklich beschrieben werden. `files/`, lokale `config.local.php`,
Uploads, Sessions, Caches und Logs werden weder ausgeliefert noch ersetzt.

### Anzeige in Admin-Dashboard und Menü

`Status & Health`, die Dashboard-Navigation und der Schnellzugriff zeigen den
Update-Zustand zentral an:

- **prüfen:** Es liegt noch kein lokaler Prüfstand vor.
- **aktuell:** Der zuletzt geprüfte stabile Stand ist nicht neuer.
- **neu:** Ein neuerer stabiler Release ist verfügbar.
- **bereit:** ZIP, Prüfsumme, Inventar und Staging sind vollständig geprüft;
  der Administrator kann die Installation fortsetzen oder das Update stoppen.

Diese Anzeigen rufen ausschließlich `dbxUpdateService::status()` auf. Die
Methode liest den lokalen Zustand aus `files/update` und führt ausdrücklich
keinen Netzwerkaufruf aus. Nur die Update-Seite startet mit `prepare()` eine
neue GitHub-Prüfung. Dadurch bleibt das Dashboard schnell und ein Ausfall von
GitHub beeinflusst weder Menü noch Systemstatus.

Alle Oberflächen beziehen die Service-Konfiguration über
`dbxUpdateService::configured()`. URL, Cache-Zeit, Sicherheitsprüfung,
Installation, Stop und Rollback bleiben damit an einer Stelle definiert.

## dbxView

Hilfen für Zielbereiche, View-State und AJAX-Targets.

## Grundregel

Kernel-Klassen sind Infrastruktur. Fachmodule sollen sie nutzen, nicht kopieren.
Kernel-Änderungen nur auf ausdrückliche Anforderung.

Weiterlesen: @ref dbxapp_module_reference, @ref dbxapp_dbxdb_dd_fd,
@ref dbxapp_dbxform und @ref dbxapp_dbxreport.
