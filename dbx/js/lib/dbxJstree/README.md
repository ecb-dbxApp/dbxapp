# dbxJstree

dbxJstree ist die vollständige Baum-Bibliothek von dbxapp. Sie wird direkt
aus `dbx/js/lib/dbxJstree.js` geladen, benötigt jQuery 4.x und besitzt keine
Laufzeitabhängigkeit zum früheren `add_ons/jstree`.

## Deklarative Verwendung

```html
<div data-dbx="lib=dbxJstree" data-jstree-options='{"core":{"data":[]}}'></div>
```

Der dbxapp-Kern lädt JavaScript und Standard-Theme dynamisch. Für direkte
Aufrufe steht zusätzlich `window.dbxJstree` mit `create`, `instance` und
`destroy` bereit. Die kompatible interne Plug-in-Schnittstelle
`jQuery(...).jstree(...)` bleibt für bestehende Baumimplementierungen erhalten.

## Herkunft und Lizenz

Version 1.0.0 basiert auf jsTree 3.3.17 von Ivan Bozhanov. Beide stehen
unter der MIT-Lizenz. Die dbxapp-Fassung prüft jQuery 4 zur Laufzeit und wird als
dbxapp-Systembibliothek gepflegt.
