# Routing, Templates und Modul-Inclusion {#dbxapp_routing_templates}

dbXapp kennt zwei Hauptwege, eine Funktion aufzurufen:

- Content-getrieben ueber Permalinks und CMS-Seiten.
- Parameter-getrieben ueber `dbx_modul`, `dbx_run1`, `dbx_run2`, `dbx_run3`.

Beide Wege koennen gemischt werden.

## Frontend: Content-getrieben

Im Frontend ist dbXapp normalerweise CMS- und Permalink-getrieben.

Beispiel:

```text
/kontakt
/de/service
/lkw/angebot
```

Der Request wird auf eine CMS-Seite aufgeloest. Diese Seite kann normalen
Content, Templates und Modul-Inclusions enthalten.

Beispiel im Content:

```html
<h1>Meine Anfragen</h1>
[modul=dbxContact]dbx_run1=tickets[/modul]
```

Das Frontend bleibt damit redaktionell steuerbar. Fachfunktionen werden als
Modulinseln eingebettet.

## Administration: Parameter-getrieben

In der Administration sind URL-Parameter der Standard.

Beispiel:

```text
?dbx_modul=dbxAdmin
?dbx_modul=dbxContact_admin&dbx_run1=list
?dbx_modul=dbxAdmin&dbx_run1=config&dbx_run2=edit&xmodul=dbx
```

Die Hauptklasse des Moduls wertet `dbx_run1` aus und delegiert an
Include-Klassen.

Minimaler Router:

```php
class myModule {
   public function run() {
      $run1 = dbx()->get_modul_var('dbx_run1', 'list');

      switch ($run1) {
         case 'list':
            return dbx()->get_include_obj('myModuleList')->run();
         case 'edit':
            return dbx()->get_include_obj('myModuleForm')->run();
      }

      return dbx()->get_system_obj('dbxTPL')->get_tpl(
         'dbx|alert-warning',
         array('msg' => 'Unbekannte Aktion.')
      );
   }
}
```

## Mischung beider Welten

Eine CMS-Seite kann ein Admin-aehnliches Modul enthalten:

```html
[modul=dbxContact]dbx_run1=tickets&status=open[/modul]
```

Ein Admin-Dashboard kann wiederum nur Layout definieren:

```html
<div class="dbx-admin-dashboard-slot dbx-admin-dashboard-slot-sysmsg">
   [modul=dbxAdmin]dbx_run1=sysmsg&dbx_run2=list_sysmsg[/modul]
</div>
```

Das Eltern-Template entscheidet nur ueber Position. Das eingebundene Modul
bleibt fuer Inhalt, Report, Filter und Aktionen verantwortlich.

## Modulvariablen

`get_modul_var()` liest modulbezogene Parameter. Es ist der Standard innerhalb
eines Moduls.

```php
$run1 = dbx()->get_modul_var('dbx_run1', 'list', 'parameter');
$id   = (int) dbx()->get_modul_var('id', 0, 'int');
```

`get_request_var()` liest direkte Request-Parameter ohne Modulschutz. Es wird
genutzt, wenn ein Wert wirklich global fuer den Request gemeint ist.

```php
$ajax = (int) dbx()->get_request_var('dbx_ajax', 0, 'int');
```

## Remember-Variablen

Remember-Variablen speichern UI- oder Systemzustand ueber Requests hinweg.

```php
dbx()->set_remember_var('dbx_lng', 'de', 'dbx');
$lng = dbx()->get_remember_var('dbx_lng', 'de', 'dbx');
```

Typische Werte:

- Sprache: `dbx_lng`
- Design: `dbx_design`
- Editmodus: `dbx_edit`
- Farbe/Skin: `dbx_color`
- Report-Auswahlzustand

## Session-Variablen

Session-Variablen gehoeren zu kurzfristigem Benutzerzustand.

```php
dbx()->set_session_var('selected_ids', array(4, 7), 'report', 'dbxAdmin');
$ids = dbx()->get_session_var('selected_ids', array(), 'report', 'dbxAdmin');
```

## Geschuetzte Modulvariablen

dbXapp muss erlauben, dass dasselbe Modul mehrfach auf einer Seite vorkommt.
Deshalb duerfen Modulzustand, Reportzustand und Targets nicht global kollidieren.

Beispiel:

```html
[modul=dbxContact]dbx_run1=tickets&status=open[/modul]
[modul=dbxContact]dbx_run1=tickets&status=closed[/modul]
```

Beide Instanzen brauchen getrennte Filter, Pagination und AJAX-Targets.

## target_{i} und dbx_target_{i}

`{i}` wird von `dbxForm`/`dbxReport` durch eine laufende Instanznummer ersetzt.

Template:

```html
<div id="dbx_target_{i}" class="dbx-panel dbxReport">
   <form action="{action}" method="post" id="dbx_form_{i}" class="dbxAjax"
         data-ajax-target="dbx_target_{i}" data-ajax-replace="target">
      ...
   </form>
</div>
```

Gerenderte Ausgabe:

```html
<div id="dbx_target_17" class="dbx-panel dbxReport">
   <form id="dbx_form_17" class="dbxAjax" data-ajax-target="dbx_target_17">
```

Warum das wichtig ist:

- AJAX ersetzt genau den richtigen Bereich.
- Mehrere gleiche Reports koennen auf einer Seite existieren.
- Fenster, Confirm und Reloads haben ein klares Ziel.
- Keine ID-Kollisionen im DOM.
