# dbxTPL Leitfaden {#dbxapp_dbxtpl}

`dbxTPL` ist die Template-Schicht von dbXapp. Sie trennt Ausgabe von Fachlogik.
PHP bereitet Daten vor, Templates bestimmen Struktur, Positionierung und
Einbindung.

## Einordnung im Golden Path

Das durchgängige Modulbeispiel steht unter @ref dbxapp_module_reference. Dieses
Kapitel vertieft ausschließlich die Template-Fähigkeiten.

```text
Service/View-Model -> kontrollierte Werte -> dbxTPL -> HTML
dbxForm/dbxReport  -> Komponentenobjekte  -> dbxTPL -> HTML
```

`dbxTPL` lädt keine Fachdaten und mutiert keine Datensätze. Werte aus den
dbx-Pipelines werden ohne lokale Escape-Hilfsfunktionen eingesetzt. Ein
zusätzliches Escaping ist nur notwendig, wenn eine tatsächlich rohe
Fremdeingabe außerhalb der vorhandenen Feld-, Report- oder
Komponentenpipeline als literaler HTML-Text ausgegeben werden soll.

## Speicherorte

Modul-Templates liegen hier:

```text
dbx/modules/{modul}/tpl/htm/{template}.htm
dbx/modules/{modul}/tpl/css/{modul}.css
dbx/modules/{modul}/tpl/js/{modul}.js
```

Templates koennen sprachabhaengig sein. Wenn die aktive Sprache z.B. `de` ist,
versucht dbxTPL zuerst die Sprachdatei und faellt dann auf die neutrale Datei
zurueck:

```text
dbx/modules/{modul}/tpl/htm/{template}_de.htm
dbx/modules/{modul}/tpl/htm/{template}.htm
```

Damit koennen Labels, feste Ueberschriften und kleine Hilfetexte im Template
sprachspezifisch gepflegt werden, ohne den Modulcode zu verzweigen.

System-Templates liegen meist im Modul `dbx`:

```text
dbx/modules/dbx/tpl/htm/
```

## Template laden

```php
$tpl = dbx()->get_system_obj('dbxTPL');
$html = $tpl->get_tpl('dbxContact|ticket-row', array(
   'id'      => 17,
   'subject' => 'Rueckfrage',
));
```

`dbxContact|ticket-row` bedeutet:

```text
dbx/modules/dbxContact/tpl/htm/ticket-row.htm
```

## Marker ersetzen

Template:

```html
<article class="ticket-row">
   <strong>{subject}</strong>
   <span>#{id}</span>
</article>
```

PHP:

```php
return $tpl->get_tpl('dbxContact|ticket-row', array(
   'subject' => $row['subject'],
   'id'      => (int) $row['id'],
));
```

Ergebnis:

```html
<article class="ticket-row">
   <strong>Rueckfrage</strong>
   <span>#17</span>
</article>
```

## Objekte einsetzen

`dbxForm` und `dbxReport` koennen Objekte in Templates einsetzen:

```html
<div class="dbx-panel">
   {obj:bar}
   <div class="dbx-panel-body">
      {obj:report}
   </div>
</div>
```

PHP:

```php
$form = new \dbxForm();
$form->init('ticket-panel', 'ticket-panel');
$form->add_obj('bar', 'dbx|component-bar', $barData);
$form->add_obj('report', 'obj-value', $this->ticket_report());
return $form->run();
```

## Template-Aufteilung mit dbx_split

Einige dbXapp-Komponenten lesen nicht nur ein Template als Ganzes, sondern
teilen es in Bereiche auf. `dbxReport` verwendet dafuer den Marker:

```html
<hr class="dbx_split">
```

Ein typisches Report-Template aus `dbxAdmin|report-sysmsg`:

```html
[tpl=dbx|report-shell-head]
   [dbx:pagination]
   <div class="table-responsive">
    <table class="table table-striped table-bordered table-light table-hover align-middle">
     <thead>
      <tr class="{tr-class}">[rpt:row]</tr>
     </thead>
     <tbody>
      <hr class="dbx_split">
      <tr class="{tr-class}">[rpt:row]</tr>
      <hr class="dbx_split">
     </tbody>
    </table>
   </div>
[tpl=dbx|report-shell-foot]
```

Die Bereiche bedeuten:

- Vor dem ersten `dbx_split`: Header, z.B. Tabellenkopf.
- Zwischen erstem und zweitem `dbx_split`: Body/Row-Template, wird pro
  Datensatz wiederholt.
- Nach dem zweiten `dbx_split`: Footer oder nachgelagerte Reportstruktur.

Bei Templates mit mehr Split-Markern kann dbxReport zusaetzlich Header/Footer
fuer Folgeseiten unterscheiden. Wichtig ist: Die Aufteilung gehoert ins
Template. Der Report wertet sie aus.

## Modul-Inclusion

Templates duerfen Module einbinden:

```html
[modul=dbxAdmin]dbx_run1=sysmsg&dbx_run2=list_sysmsg[/modul]
```

Der `dbxInterpreter` fuehrt diesen Modulaufruf bei der Seitenausgabe aus.

## DesignPage, Page und Content-Templates

Ein typischer Aufbau:

```text
DesignPage
  -> Page-Template
     -> Content-Template
        -> Modul-Inclusions
           -> Modul-Templates
```

DesignPage definiert den aeusseren Rahmen: Navigation, Hauptbereich, Footer,
CSS/JS-Positionen.

Page-Templates definieren Seitentypen: Standardseite, Landingpage, Adminseite,
Dashboard.

Content-Templates definieren konkrete Inhaltsbloecke: Hero, Body, Galerie,
Footer.

Modul-Templates definieren Fachbereiche: Report, Formular, Toolbar, Panel.

CMS-Templates koennen Content ueber einfache Marker in Bereiche und Spalten
aufteilen:

```html
<section class="cms-header header">{cms:header}</section>
<section class="cols cols-{cms:cols}">
   <div class="col col-1">{cms:col1}</div>
   <div class="col col-2">{cms:col2}</div>
   <div class="col col-3">{cms:col3}</div>
</section>
<footer class="footer">{cms:footer}</footer>
```

Der CMS-Renderer verteilt den redaktionellen Inhalt auf diese Slots. Dadurch
kann eine CMS-Seite ohne eigenen PHP-Code einspaltig, zweispaltig,
dreispaltig, mit Header, Hero, Galerie und Footer ausgegeben werden.

## Positionierung

Positionierung gehoert in Templates und CSS, nicht in Controllerlogik.

Gut:

```html
<div class="dbx-admin-dashboard-slot dbx-admin-dashboard-slot-sessions">
   [modul=dbxAdmin]dbx_run1=session&dbx_run2=list_session[/modul]
</div>
```

Nicht gut:

```php
return '<div style="float:left;width:50%">' . $this->session_report() . '</div>';
```

## dbx_edit=1 und dbx_edit=2

`dbx_edit=1` und `dbx_edit=2` markieren editierbare Bereiche in der gerenderten
Seite. Die Webseite wird weiterhin normal ausgegeben. Der Interpreter bleibt
aktiv.

Wichtig:

- In der Webseite werden `[modul=...]` ausgefuehrt.
- Im Template-Editor selbst wird Rohtext angezeigt.
- Marker wie `{title}` bleiben im Template-Editor sichtbar.
- Der Editor darf nicht die gerenderte Ausgabe speichern.

## Regeln

- Keine langen HTML-Strings in PHP.
- Templates beschreiben Struktur.
- PHP liefert Daten, Status und Aktionen.
- Keine pauschalen Escape-Wrapper um normale DD-, Form- oder Reportwerte.
- Alles, was wiederverwendbar ist, bekommt ein eigenes Template.
- Dashboard-Templates sollen nur Aufteilung enthalten und Fachbereiche per
  `[modul=...]` einbinden.

## Verwandte Kapitel

- @ref dbxapp_module_reference
- @ref dbxapp_dbxform
- @ref dbxapp_dbxreport
- @ref dbxapp_dbxinterpreter
- @ref dbxapp_routing_templates
