# dbxInterpreter Leitfaden {#dbxapp_dbxinterpreter}

`dbxInterpreter` ist die Bruecke zwischen Content, Templates und Modulen. Sein
Prinzip ist bewusst klein: Texte enthalten einfache Marker, und die
Ausgabepipeline ersetzt diese Marker durch das passende Ergebnis.

## Die drei Marker

Die dbxapp-Ausgabepipeline interpretiert im Kern nur drei Arten von Markern:

| Marker | Aufgabe | Technische Verarbeitung |
| --- | --- | --- |
| `[inc=...]...[/inc]` | Bedingte Bereiche anzeigen oder entfernen | `dbxTPL` |
| `[tpl=modul\|file]` | Template-Bausteine einbinden | `dbxTPL` |
| `[modul=modul]...[/modul]` | Modul ausführen und Ergebnis einsetzen | `dbxInterpreter` |

Das ist absichtlich wenig. Es gibt keine grosse Template-Sprache, keine Loops
im Template und kein `eval()`. Die Logik bleibt im PHP-Modul, die Struktur im
Template, und die Verbindung entsteht über diese Marker.

## Warum das schnell ist

Der Interpreter ist schnell, weil er keine komplexe Sprache auswertet.

- Es werden nur bekannte Marker gesucht.
- `[inc]` entscheidet nur, ob ein vorhandener Block bleibt.
- `[tpl]` lädt ein Template rekursiv über `dbxTPL`.
- `[modul]` setzt Modulparameter und ruft das Modul über `dbxApi` auf.
- Template-Inhalte werden gecacht; dynamische Werte werden danach ersetzt.
- Es gibt keine beliebigen PHP-Ausdruecke im Template.

Dadurch bleibt die Ausgabe deterministisch. Ein Template ist Text, kein
Programm. Module können trotzdem beliebig komplex sein, weil sie als normale
dbxapp-Module laufen.

## `[inc]`: einfache Bedingungen

`[inc]` ist für kleine Anzeigeentscheidungen gedacht.

```html
[inc=1]<span>sichtbar</span>[/inc]
[inc=0]<span>entfernt</span>[/inc]
```

Mit erlaubten Funktionen:

```html
[inc=has_group('admin')]
   <a href="?dbx_modul=dbxAdmin">Administration</a>
[/inc]
```

Regel: `[inc]` ist keine Programmiersprache. Komplexe Logik gehört in Modul-
oder Include-Klassen.

## `[tpl]`: Template-Bausteine

`[tpl]` bindet wiederverwendbare Template-Teile ein.

```html
[tpl=dbx|module-bar]
[tpl=dbx|report-form-select]
[tpl=dbxContact|contact-ticket-detail]
```

Das macht Templates klein und kombinierbar. Ein Report-Template kann dadurch
z.B. den Standard-Reportkopf, Filterbar und Footer nutzen, ohne diese Struktur
in jedem Modul zu kopieren.

## `[modul]`: Modul-Inseln

`[modul]` startet ein Modul und ersetzt den Marker durch dessen Ausgabe.

```html
[modul=dbxAdmin]dbx_run1=session&dbx_run2=list_session[/modul]
```

Ablauf:

1. `dbxInterpreter` findet den Marker.
2. Der Modulname wird gelesen.
3. Die Parameter im Marker werden als Modulvariablen gesetzt.
4. Diese Modulvariablen werden geschützt, damit Requestwerte sie nicht
   versehentlich überschreiben.
5. `dbxApi` lädt das Modulobjekt.
6. Das Modul wird über `run()` ausgeführt.
7. Die Modulausgabe ersetzt den Marker.

Beispiel für Content:

```html
<h1>Meine Anfragen</h1>
[modul=dbxContact]dbx_run1=tickets[/modul]
```

Beispiel für ein Admin-Dashboard-Template:

```html
<div class="dbx-admin-dashboard-slot dbx-admin-dashboard-slot-sysmsg">
   [modul=dbxAdmin]dbx_run1=sysmsg&dbx_run2=list_sysmsg[/modul]
</div>
```

Das Eltern-Template definiert damit nur die Position. Der eingebundene Bereich
bleibt ein eigenes Modul mit eigenem Report, eigener Suche, Sortierung,
Pagination, Aktionen und eigenem AJAX-Target.

## Warum damit extrem viel möglich ist

Mit diesen drei Markern entstehen verschachtelbare Bausteine:

- Eine CMS-Seite kann mehrere Module enthalten.
- Ein Dashboard kann nur aus Layout-Slots und Modulaufrufen bestehen.
- Ein Report kann Standard-Templates für Kopf, Filter, Body und Footer nutzen.
- Ein Formular kann innerhalb eines Moduls stehen und wiederum Standard-
  Formular-Templates verwenden.
- Ein Modul kann mehrfach auf derselben Seite laufen, wenn Targets und
  Modulvariablen sauber getrennt sind.
- Content bleibt redaktionell editierbar, obwohl Fachfunktionen eingebettet
  werden.

Beispiel:

```html
[tpl=dbx|frame-head]
   [inc=has_group('admin')]
      [modul=dbxAdmin]dbx_run1=sysmsg&dbx_run2=list_sysmsg[/modul]
   [/inc]
   [modul=dbxContact]dbx_run1=tickets&status=open[/modul]
[tpl=dbx|frame-foot]
```

Das ist kein grosses Framework im Template. Es sind nur drei einfache
Mechanismen, die sauber mit den Kernel-Klassen zusammenspielen.

## Template-Editor und dbx_edit

Bei der normalen Webseitenausgabe muss der Interpreter laufen, auch wenn
`dbx_edit` aktiv ist. Sonst würde die Seite nur rohe Marker anzeigen.

Im Template-Editor gilt das Gegenteil: Dort darf der Interpreter die Marker
nicht ausführen. Der Benutzer soll die Datei aus `/tpl` genau so sehen und
bearbeiten, wie sie gespeichert ist.

Wichtig:

- Webseite mit `dbx_edit > 0`: Interpreter läuft.
- Template-Editor: Marker bleiben Rohtext.
- `[modul=...]` im Editor darf keine Modulausgabe erzeugen.
- Editor-Marker wie `DBX-TPL-START` gehören nicht in den bearbeiteten
  Rohtext.

## Performance- und Sicherheitsgedanke

Der Interpreter bleibt klein, weil er nur verbindet:

- Er erzeugt keine Fachlogik.
- Er speichert keine Daten.
- Er rendert keine Reports selbst.
- Er baut keine Fenster, Ajax-Logik oder Confirm-Dialoge.

Diese Aufgaben gehören zu den jeweiligen Standardklassen und JavaScript-Libs.
Der Interpreter sorgt nur dafür, dass Content und Templates die richtigen
Bausteine ausloesen können.

## Regeln für Module

- Modulaufrufe im Content immer als `[modul=...]...[/modul]` schreiben.
- Parameter im Marker wie Querystring schreiben: `dbx_run1=list&id=4`.
- Keine eigenen Parser für Modulmarker bauen.
- Wiederholbare Bereiche mit eindeutigen Targets wie `dbx_target_{i}` bauen.
- Fachlogik im Modul halten, nicht im Template verstecken.
- Templates klein halten und Standardbausteine über `[tpl=...]` nutzen.
- Bedingungen nur für einfache Anzeigeentscheidungen mit `[inc]` nutzen.

## Zusammenhang mit dbxTPL

`dbxTPL` und `dbxInterpreter` sind getrennt, arbeiten aber zusammen:

1. `dbxTPL` lädt Templates und ersetzt `{marker}`.
2. `dbxTPL` verarbeitet `[inc]`.
3. `dbxTPL` verarbeitet `[tpl]`.
4. Die Webausgabe laesst danach Modulmarker durch `dbxInterpreter` auflösen.

Diese Trennung ist ein Kernvorteil von dbxapp: Template-Struktur und
Modul-Ausführung bleiben getrennt, können aber im Content kombiniert werden.
