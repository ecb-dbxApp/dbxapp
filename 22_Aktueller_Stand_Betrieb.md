# Aktueller Stand, Betrieb und Qualität {#dbxapp_current_operations}

[Offizielle dbxapp Website](https://dbxapp.de)

Dieses Kapitel bündelt die Laufzeit- und Qualitätsverträge von dbxapp 5.0 mit
Stand 24. Juli 2026. Es ergänzt die Fachleitfäden um die Punkte, die beim
Betrieb, bei Fehlersuche und bei der Modernisierung älterer Module besonders
wichtig sind.

## Systemstand

| Bereich | Aktueller Vertrag |
| --- | --- |
| Produkt | dbxapp 5.0 |
| Website | [https://dbxapp.de](https://dbxapp.de) |
| PHP | 8.x |
| Doxygen | Projektversion 5.0, Generator 1.17.0 |
| Frontcontroller | `index.php` |
| Laufzeit-API | `dbx/include/dbxApi.php`, Zugriff über `dbx()` |
| Öffentlicher Cache | vollständiger Gastseiten-Cache v3 |
| Workflow | Definition in `dbxWorkflow_admin`, Ausführung in `dbxWorkflow` |
| Designs | `dbxapp` und `flowers`; alter Alias `fleurop` wird aufgelöst |

Die aktuellen Sicherheits-, Integritäts- und Performanceverträge einschließlich
Secret-Overrides, Action-Token-Entscheidung, Shop-Transaktionen,
Gast-Session-Verhalten und HTTP-Nachweisen stehen unter
@ref dbxapp_security_integrity_performance.

## Bootstrap und zentrale API

In `index.php` bleiben nur Bootstrap-Aufgaben. Die beiden globalen Ausnahmen
sind notwendig, bevor `dbxApi` geladen werden kann:

- `dbx_get_base_dir()` bestimmt den portablen Installations-/Include-Pfad.
- `dbx_get_file_dir()` bestimmt den portablen `files`-Ordner.

Allgemeine Laufzeitfunktionen gehören als Methoden nach `dbxApi.php` und werden
über `dbx()` aufgerufen. Modulbezogene Funktionen bleiben im jeweiligen Modul.
Neue globale `dbx_*`-Hilfsfunktionen sind nur zulässig, wenn sie aus zwingenden
Bootstrap- oder Kompatibilitätsgründen vor der API existieren müssen.

Für Datenlisten gilt entsprechend: Datenzugriffe bleiben zentral in `dbxDB`,
fachliche Mengenbildung und Zuordnung gehören in das jeweilige Repository.
Wiederholte Beziehungen einer Liste werden weiterhin mit gebündelten
DD-Abfragen geladen. Zusätzlich cached `dbxDB::select1()` identische
Einzelsatzzugriffe requestlokal pro DD und verwirft sie zentral bei
`insert()`, `update()`, `save()` und `delete()`. Transaktionen umgehen den
Cache. Kleine unveränderliche Referenzlisten dürfen daneben innerhalb eines
Requests im Fach-Repository gemerkt werden, wenn jede zugehörige Mutation den
Cache leert.

Direkte PDO- oder mysqli-Aufrufe außerhalb von `dbxDB` sind auch in
Kommandozeilen-, Migrations- und Wartungswerkzeugen nicht zulässig.
Tabellenänderungen stammen ausschließlich aus DD und werden über `dbxDD`
administrativ synchronisiert. Ein normaler Modulrequest darf keine
Schemaänderung als Nebenwirkung auslösen. Die Shop-DD-Synchronisation und
Defaultpflege liegt deshalb hinter dem berechtigten und action-tokenisierten
Adminpunkt **Installation / Wartung**.

Beim Rendering gilt dasselbe: Services erzeugen nur die Werte, deren
Platzhalter das konkrete Template verwendet. Requestlokal dürfen dafür
unveränderliche Template-Metadaten gemerkt werden; Fach-, Benutzer- und
Formulardaten werden nicht in diesen Metadaten-Cache aufgenommen.

## Aktueller Request- und Cacheablauf

```text
index.php
  -> dbx()->run_web_app_request()
  -> Request, Remember und Sprache erkennen
  -> fehlende statische Ressource prüfen
  -> Gast-Permalink für Full-Page-Cache vorbereiten
     -> HIT: Datei validieren, Header senden, Bytes unverändert ausgeben
     -> MISS: Permalink/CID, Design, Modul und Content normal auflösen
  -> Design laden
  -> dbxInterpreter ausführen
  -> Editor-Metadaten und Ausgabefilter anwenden
  -> finale 200-HTML-Antwort atomar cachen, wenn sie cachefähig ist
  -> Session speichern
  -> GET-Body ausgeben; bei HEAD nur Header senden
```

Ein Hit erfolgt vor der Content-/CID-Datenbankauflösung. Ein Miss rendert die
gesamte Seite bis nach Interpreter und Ausgabefiltern. Genau diese fertigen
Bytes werden gespeichert.

### Lesen

Cachelesen ist für öffentliche Gastaufrufe mit `GET` oder `HEAD` erlaubt.
Ausgeschlossen sind insbesondere angemeldete Benutzer, Admin-Bypass,
personalisierter Warenkorb, Editor, Ajax, openWin und nicht eindeutige
Queryparameter.

Vor der Ausgabe werden geprüft:

1. vorbereitete Cache-Generation stimmt noch;
2. Datei ist lesbar und vollständiges HTML;
3. `base href` entspricht Schema, Host, Port und Installationspfad;
4. Generation ist auch nach dem Lesen noch aktuell.

Fehlt eine Bedingung, wird die Datei nicht benutzt. Beschädigte Dateien oder
Dateien mit falschem `base href` werden gelöscht und am Requestende nach einem
normalen Renderlauf neu erstellt.

Ein sicherer Cache-Hit benötigt für UID 0 ohne eingehende `PHPSESSID` keine
persistente PHP-Session. Die während des Bootstraps kurz gestartete Session
wird deshalb vor dem frühen Cache-Exit zerstört. Dasselbe gilt außerhalb des
Page-Caches nur für erkannte Robots auf anonymen GET-/HEAD-Requests. Eine
Gastsession wird niemals über die IP rekonstruiert; normale Browserzustände
bleiben an den zufälligen Session-Cookie gebunden.

### Keine Behandlung der Cachebytes

Beim Cache-Hit gibt es bewusst keine Behandlung des HTML-Inhalts:

- kein Escaping;
- kein `htmlspecialchars()`;
- kein erneuter `dbxInterpreter`;
- kein Template-Replacement;
- keine Session- oder Benutzeranreicherung.

`file_get_contents()` liefert die gespeicherten Bytes unverändert zurück.
Sicherheit entsteht vor dem Schreiben: nur eine fertige öffentliche
200-Antwort ohne geschützte Tokenfelder wird akzeptiert.

### Schreiben und Invalidieren

`cache_content=1` erlaubt neue Schreibvorgänge. `cache_content=0` verhindert
nur neue Dateien; vorhandene Treffer bleiben lesbar und der Schalter löscht
nichts. Inhaltliche Änderungen invalidieren den Cache separat.

Die Datei wird zunächst unter einem zufälligen temporären Namen mit `LOCK_EX`
geschrieben und anschließend atomar umbenannt. Der Dateiname enthält:

- lesbaren Permalinkanteil plus SHA-256-Hash des vollständigen Permalinks;
- Sprache, Design und Skin;
- Hash von Schema, Host, Port und Installationspfad;
- Cache-Generation und Formatversion v3.

Eine Invalidierung wechselt zuerst die Generation und löscht danach alte
Dateien. Dadurch kann ein parallel laufender alter Request weder einen alten
Hit ausliefern noch seinen veralteten Renderstand in die neue Generation
schreiben.

## HEAD

`HEAD` folgt derselben Auflösung wie `GET`, sendet aber niemals den Body:

- Hit: fertige Cachedatei wird geprüft, nur Header werden gesendet.
- Miss: Seite darf vollständig gerendert und gespeichert werden, die endgültige
  Ausgabe unterdrückt den Body zentral in `dbxApi::emit_http_response_body()`.

Damit unterscheiden sich Hit und Miss nicht mehr im HTTP-Verhalten.

## Fehlende Ressourcen und dbxMissing

`dbxMissing` protokolliert ausschließlich fehlende statische Ressourcen wie
JavaScript, CSS, Bilder, Fonts oder vergleichbare Dateien. Ein unbekannter
normaler Permalink wird nicht als fehlende Ressource protokolliert.

Der Ablauf liegt vor dem Content-Cache:

```text
Requestpfad
  -> dynamische Dateirouten ausschließen
  -> Endung gegen erlaubte Ressourcenarten prüfen
  -> vorhandene Datei sicher innerhalb des Projektpfads ausliefern
  -> sonst 404 + no-store + Zähler in dbxMissing erhöhen
```

Die Administration ist erreichbar über:

```text
?dbx_modul=dbxAdmin&dbx_run1=missing&dbx_run2=list_missing
```

Löschen einzelner oder mehrerer Einträge sowie das Leeren der Tabelle sind mit
Action-Token und Confirm geschützt.

## Validatorregeln

### email

Die Regel `email` prüft eine vollständige Internetadresse:

- Gesamtlänge höchstens 254 Zeichen, Local-Part höchstens 64 Zeichen;
- genau ein `@`;
- kein führender, abschließender oder doppelter Punkt im Local-Part;
- mindestens ein Punkt in der Domain;
- Domainlabels höchstens 63 Zeichen, keine führenden/abschließenden Bindestriche;
- alphabetische oder Punycode-TLD;
- IDN-Umwandlung, wenn `intl` verfügbar ist;
- abschließende Prüfung mit `FILTER_VALIDATE_EMAIL`.

Für ein Pflichtfeld wird `email|min=1|max=254` verwendet.

### permalink

Die Regel `permalink` erlaubt exakt:

```regex
^[a-z0-9]+(?:-[a-z0-9]+)*$
```

Damit sind Leerzeichen, Großbuchstaben, Umlaute, Sonderzeichen, Schrägstriche,
führende/abschließende und doppelte Bindestriche ausgeschlossen. Die automatische
Erzeugung kanonisiert Titel zu einem flachen `-`-Slug.

## Formulare, Meldungen und Hilfe

Jedes dbxForm benötigt:

- Feldregeln einschließlich Pflichtkennzeichnung;
- einen Formularmeldungsplatzhalter an der richtigen Stelle;
- Hilfe in der Modulbar ganz rechts;
- eindeutige `{i}`-IDs und Ajax-Targets;
- normalen Request als Fallback.

`dbxAdminHelp` ordnet feste Themen robusten Permalinks wie
`help-dashboard-admin` zu. Diese Links enthalten keine Ordnerpfade und ändern
sich deshalb beim Verschieben der CMS-Hilfeseite nicht. Nicht registrierte
Formulare erhalten über `formButton()` eine Fallback-Hilfe.

Der Fehlversuchszähler von dbxForm wird nach 600 Sekunden ohne weiteren
Fehlversuch vollständig auf null gesetzt. Kurzzeitsperren und Sperrstufen
bleiben dadurch nicht über einen neuen Versuchzeitraum erhalten.

## Benutzerpasswörter

Beim Bearbeiten eines Benutzers stehen im rechten Block zwei Felder bereit:
**Neues Passwort** und **Passwort wiederholen**. Beide bleiben leer, wenn das
Passwort unverändert bleiben soll. Sobald eines ausgefüllt ist, müssen beide
Werte vorhanden und gleich sein; gespeichert wird ausschließlich der
`password_hash()`.

Die Listenaktion „Neues Passwort erzeugen“ generiert ein zwölfstelliges
Passwort und speichert nur dessen Hash. Der aktuelle Adminablauf zeigt das neue
Passwort einmal als Erfolgsmeldung an; ein automatischer Mailversand ist dort
nicht Bestandteil der Implementierung.

## CMS-Medien und Shop-Zuordnung

Die CMS-Medienliste zeigt zusätzlich Shopbilder, wenn ihre `dbxMediaUsage`
genau dem aktuell bearbeiteten Dokument und dem Slot `shop` zugeordnet ist.
Diese Bilder stehen für die Gallery dieses Dokuments zur Verfügung. Eine
Shop-Zuordnung eines anderen Dokuments darf nicht in der aktuellen Seite
erscheinen.

Editor-Medien behalten beim Speichern Layoutattribute wie Position und Größe.
Die gerenderte Ausgabe muss die Struktur des Editors übernehmen; ein links
stehendes Bild mit Text rechts darf nicht nach dem Speichern in eine vertikale
Anordnung zurückfallen.

Schreibende CMS-, Medien-, SEO- und Shop-Admin-GET-/JSON-Aktionen verwenden
zusätzlich zu Modul- und DD-Rechten das vorhandene
`action_token()`/`check_action_token()`-System. Reine Anzeige und Navigation
bleiben kompatibel und tokenlos. Das Token belegt die konkrete Browseraktion
und schützt damit vor fremd ausgelösten Requests; es ersetzt keine
Berechtigungsprüfung.

## Content-Template-Editor

Das Stift-Symbol neben einem `c-*`-Content-Template verwendet eine sequenzielle
Aktion:

1. `confirm.js` zeigt bei jedem Klick genau einen Warnhinweis;
2. vor der Zustimmung existiert noch kein Editorfenster;
3. „Abbrechen“ beendet die Aktion;
4. „Ja“ lädt über `ajax.js`/`openWin` den vorhandenen dbxEditor;
5. ACE öffnet die ausgewählte Datei unter
   `dbx/modules/dbxContent/tpl/htm/`.

## Modul-Wizard

`dbxWizard` erzeugt aktuelle Modulgerüste mit kleinem Router, Service,
DD/FD, dbxForm, dbxReport und Templates. Sicherheitsregeln:

- Modulnamen und Dateinamen werden streng geprüft;
- keine Pfade außerhalb `dbx/modules/{modul}/`;
- vorhandene Module können gezielt ergänzt werden;
- Überschreiben ist explizit und kann vorher unter `files/module-backup/`
  sichern;
- generierte Templates enthalten Formularmeldungen, Modulbar und eindeutige
  Targets;
- Fachlogik nutzt `dbx()`, dbxDB, dbxForm, dbxReport, ajax.js, confirm.js und
  openWin statt Parallelimplementierungen.

## Ajax-Vertrag

`dbx_ajax=1` darf grundsätzlich nur `ajax.js` für einen tatsächlichen
Ajaxrequest setzen. Manuell erzeugte Links auf vollständige Seiten enthalten
den Parameter nicht. Nach einem Ajaxrequest werden neue Inhalte über die
dbx-Scan-/Feature-Pipeline initialisiert.

Bei Formular-Submits muss `SubmitEvent.submitter` erhalten bleiben.
`FormData(form)` enthält den geklickten Submit-Button nicht automatisch;
`ajax.js` ergänzt deshalb dessen `name/value`. `confirm.js` löst bestätigte
Formularaktionen über den normalen Submit-Pfad aus und hält `name/value`
währenddessen in einem kurzlebigen Hidden-Feld. Damit bleiben
Formularkonfiguration, dbxForm-Security, AJAX und native Fallbacks kompatibel.
Die dbxapp-Asset-Version ist nach dieser Kerneländerung auf 69 erhöht.

## DD-Trace

Aktiviertes DD-Trace ist eine bewusste Ausnahme. Im aktuellen Quellstand gilt:

- `dbxUser.dd.php`: `trace=1`;
- alle übrigen aktiven DDs: `trace=0`.

Backup-DDs sind keine aktive Laufzeitdefinition und werden von dieser Regel
nicht als aktuelle Tabelle gewertet.

## Mindestprüfung vor Veröffentlichung

1. PHP-Syntax aller geänderten PHP-Dateien prüfen.
2. JavaScript mit `node --check` prüfen.
3. Validator-Tests für E-Mail und Permalink ausführen.
4. Cachetest einschließlich Hashkollision, `base href`, Generation und
   bytegenauer Ausgabe ausführen.
5. `HEAD` bei Hit und Miss prüfen.
6. Missing-Ressourcentest ausführen; normale Permalinks dürfen nicht geloggt
   werden.
7. dbxForm-Hilfeaudit und Try-Count-Test ausführen.
8. Wizard-Generationstest ausführen.
9. dbxDB-Boundary-, Action-Token- und Template-Hygiene-Tests ausführen.
10. CMS-Editor, Confirm und ACE-Ablauf im Browser prüfen.
11. Doxygen neu in einen leeren Ausgabeordner erzeugen und nach alten Domains,
    toten Links und Doxygen-Warnungen suchen.

## Reale Referenzen

- `dbx/include/dbxApi.php`
- `dbx/include/dbxWebApp.class.php`
- `dbx/include/dbxValidator.class.php`
- `dbx/include/dbxForm.class.php`
- `dbx/modules/dbxContent/include/dbxContentPageCache.class.php`
- `dbx/modules/dbxContent/include/dbxContent_permalink.class.php`
- `dbx/modules/dbxAdmin/include/dbxMissing.class.php`
- `dbx/modules/dbxAdmin/include/dbxAdminHelp.class.php`
- `dbx/modules/dbxAdmin/include/dbxWizard.class.php`
- `dbx/js/lib/cms.js`

Weiterlesen: @ref dbxapp_cms_dbxki, @ref dbxapp_dbxform,
@ref dbxapp_module_patterns, @ref dbxapp_workflow_guide und
@ref dbxapp_core_classes.
