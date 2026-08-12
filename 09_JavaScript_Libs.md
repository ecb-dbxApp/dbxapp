# JavaScript-Systemlibs {#dbxapp_javascript_libs}

dbxapp-JavaScript ist eine deklarative Erweiterung der serverseitigen
Pipelines. `core.js` erkennt benötigte Features, lädt sie und initialisiert sie
auch nach einem HTML-Ajax-Replace erneut.

Das vollständige Zusammenspiel mit einem Modul steht unter
@ref dbxapp_module_reference.

## Grundvertrag

| Aufgabe | Systemlib |
| --- | --- |
| Feature-Erkennung und UI-State | `core.js` |
| HTML-/JSON-Transport | `ajax.js` |
| Bestätigung | `confirm.js` |
| Fenster | `openWin.js` |
| Formularinteraktion | `form.js` |
| Report, Pagination und Auswahl | `report.js` |
| Grid/Tabulator | `grid.js` |
| längere Prozesse | `process.js` |

Eine vorhandene Systemfähigkeit wird nicht mit `fetch()`, `window.confirm()`,
einem zweiten Modal oder direktem Browser-Storage nachgebaut.

## Sprache im Browser

Allgemeine Frameworktexte wie „Ja/Nein/Abbrechen“, Sortierzustände oder
technische Laufzeitfehler werden zentral mit `dbx.translate(key, fallback)`
aus der aktiven Sprache aufgelöst. Fachliche Texte bleiben dagegen Eigentum
der sprachabhängigen FD und werden serverseitig in HTML oder Datenattribute
eingesetzt:

```php
<a class="dbxConfirm"
   href="{delete_url}"
   data-confirm-title="{delete_title}"
   data-confirm="{delete_question}"
   data-confirm-hint="{delete_hint}"
   data-confirm-buttons="yesno">
```

Damit gibt es genau zwei klare Ebenen:

- zentrale JavaScript-Übersetzung für generische Bedienelemente der Libs;
- aktive FD für Formular-, Report- und Fachmeldungen eines Moduls.

Modul-JavaScript führt keine eigene dritte Übersetzungstabelle für dieselben
Formulartexte.

## Feature-Scope und automatische Initialisierung

Features werden auf einem Root registriert:

```html
<div id="dbx_target_7"
     data-dbx="lib=ajax|class=dbxAjax|mode=html|target=dbx_target_7">
 ...
</div>
```

Der Scope ist das Root-Element und seine Kinder. Ein verschachteltes Feature
verarbeitet nicht versehentlich Elemente eines benachbarten Moduls. Nach Ajax
initialisiert `core.js` nur den neu eingesetzten Bereich erneut.

Form- und Reporttemplates verwenden `{i}` in IDs:

```html
<div id="dbxForm_{i}">...</div>
<div id="dbx_target_{i}">...</div>
```

So bleiben mehrere Instanzen derselben Route voneinander getrennt.

## Ajax: HTML ist der Standard

Ein Formular:

```html
<form action="{action}" method="post" class="dbxAjax"
      data-ajax-target="dbxForm_{i}"
      data-ajax-mode="html"
      data-ajax-replace="target">
 [dbx:form]
</form>
```

Ein Link:

```html
<a class="dbxAjax"
   href="?dbx_modul=myTasks&amp;dbx_run1=report&amp;dbx_rpos=30"
   data-ajax-target="dbx_target_7"
   data-ajax-replace="target">
 Weiter
</a>
```

Kanonische Attribute:

| Attribut | Bedeutung |
| --- | --- |
| `data-ajax-target` | DOM-ID des Ziels |
| `data-ajax-mode="html"` | Server liefert HTML |
| `data-ajax-replace="target"` | Zielknoten vollständig ersetzen |
| `data-ajax-replace="content"` | nur den Zielinhalt ersetzen |
| `data-ajax-url` | URL-Abweichung vom geerbten Formular-/Linkziel |
| `data-ajax-method` | explizite HTTP-Methode, falls nicht ableitbar |
| `data-ajax-params` | zusätzliche, kontrollierte Parameter |

Vorhandene Altattribute wie `data-target` oder `data-replace` bleiben in
Bestandsmodulen kompatibel. Neue Templates SOLLEN die `data-ajax-*`-Namen
verwenden, weil Zweck und Zugehörigkeit eindeutig sind.

`dbx_ajax=1` wird nicht in normale Links geschrieben. Nur `ajax.js` setzt den
Ajax-Kontext des von ihm ausgeführten Requests.

## Normaler Request bleibt funktionsfähig

Ajax ist progressive Verbesserung:

1. Der Link besitzt ein echtes `href`.
2. Das Formular besitzt `action`, `method` und einen echten Submitbutton.
3. Der Server kann dieselbe Route vollständig rendern.
4. Ajax ersetzt nur die passende Teiloberfläche.

Eine Modulaktion darf nicht ausschließlich funktionieren, wenn ein
JavaScript-Eventhandler ausgeführt wurde.

## Confirm: deklarativer Standard

```html
<a class="dbxAjax dbxConfirm"
   href="{delete_url}"
   data-confirm-title="Aufgabe löschen"
   data-confirm="Aufgabe wirklich löschen?"
   data-confirm-hint="Dieser Vorgang wird protokolliert."
   data-confirm-buttons="yesno">
 Löschen
</a>
```

Wichtige Attribute:

| Attribut | Bedeutung |
| --- | --- |
| `data-confirm` | Kurzform der Frage |
| `data-confirm-question` | explizite Frage |
| `data-confirm-title` | Dialogtitel |
| `data-confirm-hint` | ergänzender Hinweis |
| `data-confirm-buttons` | `yesno`, `yesnocancel` oder `cancel` |
| `data-confirm-labelyes` | Beschriftung für Ja |
| `data-confirm-labelno` | Beschriftung für Nein |
| `data-confirm-labelcancel` | Beschriftung für Abbruch |
| `data-confirm-closable` | Schließen über X erlauben |
| `data-confirm-backdropclose` | Schließen über Hintergrund erlauben |
| `data-confirm-escclose` | Schließen über Escape erlauben |

Bei „Ja“ setzt `confirm.js` die ursprüngliche Link-, Button- oder
Formularaktion automatisch fort. Ist dieselbe Quelle als `dbxAjax`
gekennzeichnet, wird zuerst der Ajax-Weg verwendet; andernfalls folgt der
normale Browserweg.

Confirm führt keine Mutation aus und ist keine Sicherheitsprüfung. Rechte,
Validierung und bei mutierenden GETs der Action-Token werden serverseitig
geprüft.

## Confirm und Ajax: festgelegte Reihenfolge

```text
Klick
  -> confirm.js öffnet Dialog
     -> Nein: Ende
     -> Ja: ursprüngliche Aktion fortsetzen
        -> ajax.js transportiert Request
           -> Server prüft Route, Rechte, Werte und Token
              -> Server rendert HTML
                 -> ajax.js ersetzt Target
                    -> core.js initialisiert neuen Inhalt
```

Das Modul soll diese Reihenfolge nicht mit eigenen Clickhandlern nachbauen.
Insbesondere dürfen Confirm und openWin nicht unabhängig denselben Klick
verarbeiten.

## Programmatisches Confirm

Nur wenn die deklarative Fortsetzung nicht genügt:

```js
dbx.confirm.open({
    id: "myTasks-bulk-delete",
    root: element,
    title: "Aufgaben löschen",
    question: "Ausgewählte Aufgaben wirklich löschen?",
    hint: "Die Aktion kann nicht rückgängig gemacht werden.",
    buttons: "yesnocancel"
}).then(function (result) {
    if (result.action === "yes") {
        // Danach eine vorhandene dbx-Aktion auslösen.
    }
});
```

Die Promise liefert `yes`, `no`, `cancel` oder `close`. Auch hier gehört die
Fachmutation auf den Server. Eigene Dialog-DOM-Strukturen sind nicht
erforderlich.

## JSON-Ajax

HTML ist passend für `dbxForm` und `dbxReport`, weil beide vollständige
Teiloberflächen zurückgeben. JSON ist für begrenzte Status- oder API-Aktionen
geeignet:

```html
<div id="my_task_status"
     data-dbx="lib=ajax|class=dbxUiAjax|mode=json|target=my_task_status">
 <button class="dbxUiAjax"
         data-ajax-url="?dbx_modul=myTasks&amp;dbx_run1=status_api&amp;rid=17">
  Status prüfen
 </button>
</div>
```

JSON- und HTML-Routen haben unterschiedliche Antwortverträge. Eine JSON-Route
rendert nicht nebenbei ein Formtemplate. Ein normaler Form-/Report-Ajax liefert
keine willkürliche Mischantwort.

## `core.js` und UI-State

```js
const state = dbx.uiGet(
    "collapse",
    "myTasks-filter",
    "state",
    "open"
);
dbx.uiSet(
    "collapse",
    "myTasks-filter",
    "state",
    "collapsed"
);
```

Direkter Zugriff auf `localStorage` oder `sessionStorage` außerhalb von
`core.js` ist verboten. Nur der zentrale State kann Namensräume,
Kompatibilität und Bereinigung systemweit steuern.

## `report.js`, `form.js` und `grid.js`

`report.js` übernimmt:

- Pagination-Ajax;
- Einzel- und Mehrfachauswahl;
- Reportaktionen;
- Auswahlzustand und Reload.

`form.js` übernimmt:

- Formularzustand;
- Submitmechanik und Feldinteraktion;
- Reinitialisierung nach Ajax.

`grid.js` übernimmt:

- Tabulator-/Grid-Ausgabe;
- Spaltenbreiten und Sichtbarkeit;
- Inline-Edit und Grid-Reload;
- UI-State über `core.js`.

Fachmodule ergänzen Konfiguration und Fachendpunkte, nicht die Basispipeline.

## Weitere Systemlibs

| Lib | Aufgabe |
| --- | --- |
| `adminDashboard.js` | Dashboardbereiche und Diagramme |
| `menu.js` | Navigation und Chronik |
| `utilities.js` | allgemeine UI-Hilfen, Collapse, Theme/Skin |
| `ace.js` | Source- und Templateeditor |
| `cms.js` | stabiler CMS-/Jodit-Kern, Cursor und Komponentenbearbeitung |
| `cms-page.js` | Seitenformular und Seitenaktionen; im CMS vorab geladen |
| `cms-tree.js` | Content-Baum; erst beim Öffnen geladen |
| `cms-media.js` | Medienbrowser, Upload und Medienwartung; erst bei Nutzung geladen |
| `cms-language.js` | Sprachabgleich und Sprachdialoge; erst bei Nutzung geladen |
| `cms-jodit-image.js` | erweiterter Jodit-Bilddialog; erst beim Bilddialog geladen |
| `kiBriefing.js` | KI-Briefing-Oberfläche |
| `seoAdmin.js` | SEO-Verwaltung |

Die CMS-Module werden über `dbx.cmsRuntime` registriert. Nur der für jede
Bearbeitung notwendige Kern und die Seitensteuerung werden initial geladen.
Ein Featuremodul darf keine zweite Ajax-, Fenster-, Layer- oder
Editor-Infrastruktur aufbauen; Requests laufen über `ajax.js`, Fenster über
`openWin.js` und gemeinsame Helfer werden aus dem Runtime-Kontext bezogen.
Der Browsercache reduziert wiederholte Übertragung, Lazy Loading spart aber
zusätzlich Parse-, Initialisierungs- und Eventkosten für unbenutzte Funktionen.

## Verbindliche Regeln

1. Vorhandene Lib verwenden, keine zweite Implementierung.
2. Feature auf den kleinsten sinnvollen Root begrenzen.
3. `{i}` und eindeutige Targets bei wiederholbaren Komponenten nutzen.
4. Echte `href`-/`action`-Fallbacks erhalten.
5. HTML als Standardantwort für Form und Report verwenden.
6. Confirm nur als Benutzerinteraktion behandeln.
7. Berechtigung, Validierung und Mutation ausschließlich serverseitig
   entscheiden.
8. `dbx_ajax` nicht manuell in normale URLs einbauen.
9. JavaScript muss nach einem Ajax-Replace wiederholbar initialisierbar sein.
10. Direkten Browser-Storage vermeiden.

## Mindesttests

- Link/Formular ohne JavaScript;
- derselbe Ablauf mit Ajax;
- korrektes und fehlendes Target;
- zwei Instanzen derselben Komponente;
- Confirm „Ja“, „Nein“, Escape und Schließen;
- kein Request bei „Nein“;
- genau ein Request bei „Ja“;
- ungültige Rechte oder Token werden serverseitig abgewiesen;
- erneute Featurefunktion nach einem Target-Replace.

## Weiterführende Kapitel

- @ref dbxapp_module_reference
- @ref dbxapp_dbxform
- @ref dbxapp_dbxreport
- @ref dbxapp_security_integrity_performance
