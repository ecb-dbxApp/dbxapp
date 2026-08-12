# Module entwickeln {#dbxapp_module_patterns}

[Offizielle dbxapp Website](https://dbxapp.de)

Ein dbxapp-Modul kapselt eine fachliche Aufgabe. Es erhält Requests über den
Modulrouter, liest Daten über DD/dbxDB, verwendet dbxForm und dbxReport und
rendert über dbxTPL. Kernel, globale JavaScript-Libs und zentrale UI-Pipelines
werden dabei wiederverwendet.

## Verhältnis zum verbindlichen Modulhandbuch

@ref dbxapp_module_reference ist der normative Golden Path und enthält ein
vollständiges Referenzmodul. Dieses Kapitel ergänzt Varianten, größere
Fachaufteilungen und reale Projektmuster. Bei einem Widerspruch gilt das
verbindliche Modulhandbuch zusammen mit den aktuellen Sicherheitsinvarianten.

## Modularten

| Art | Beispiel | Aufgabe |
| --- | --- | --- |
| Frontendmodul | `dbxContact`, `dbxShop`, `dbxWorkflow` | öffentliche oder benutzerbezogene Funktionen |
| Adminmodul | `dbxContact_admin`, `dbxShop_admin` | geschützte Verwaltung und Reports |
| Infrastrukturmodul | `dbxContent`, `dbxKi` | CMS-, API- oder Systemdienste |
| Einbettbares Modul | grundsätzlich jedes geeignete Modul | Aufruf aus CMS/Templates über `[modul=...]` |

Frontend und Administration können getrennt werden. Das hält Rechte, Design,
Routen und fachliche Oberfläche übersichtlich.

## Vollständige Struktur

```text
dbx/modules/myTasks/
  myTasks.class.php
  cfg/config.php
  include/myTasksService.class.php
  dd/myTask.dd.php
  fd/myTask-form.fd.php
  fd/rpt-myTask-selection.fd.php
  tpl/htm/start.htm
  tpl/htm/my-task-form.htm
  tpl/htm/my-task-report.htm
  tpl/htm/my-task-row-action.htm
  design/css/myTasks.css
  design/js/myTasks.js
  tpl/img/myTasks.png
  README.md
```

Nicht jedes Modul braucht alle Ordner. Ein reines Ausgabemodul kann ohne DD
und FD auskommen; ein API-Modul benötigt möglicherweise keine HTML-Templates.
Die Trennung von Router, Service und Template bleibt trotzdem sinnvoll.

## Konfiguration

`cfg/config.php` legt Aktivierung und Gruppen fest:

```php
<?php

$config['version'] = '1.0';
$config['activ']   = '1';
$config['groups']  = '*';
$config['page_size'] = '30';

?>
```

Für ein Adminmodul:

```php
<?php

$config['version'] = '1.0';
$config['activ'] = '1';
$config['dbxConfig_modul'] = 'secure';
$config['groups'] = 'admin';

?>
```

Lesen erfolgt über die zentrale Konfiguration:

```php
$pageSize = (int)dbx()->get_cfg('myTasks', 'page_size');
```

Keine zweite JSON-/ENV-Konfiguration für dieselben Werte anlegen.

## Router: klein und eindeutig

Datei `myTasks.class.php`:

```php
<?php
namespace dbx\myTasks;

class myTasks {

    public function run() {
        $run = dbx()->get_modul_var('dbx_run1', 'report', 'parameter');
        $service = dbx()->get_include_obj('myTasksService', 'myTasks');

        switch ($run) {
            case 'form':
            case 'edit':
                return $service->form();

            case 'delete':
                return $service->delete();

            case 'detail':
                return $service->detail();

            case 'api':
                return $service->api();

            case 'report':
            case 'list':
            default:
                return $service->report();
        }
    }
}

?>
```

Der Router entscheidet nur, welche Fachmethode läuft. Datenbankabfragen,
Formaufbau und lange HTML-Fragmente gehören nicht in den Switch.

Reale Routermuster:

- `dbx/modules/dbxContact/dbxContact.class.php`: kleiner Frontendrouter.
- `dbx/modules/dbxWorkflow/dbxWorkflow.class.php`: Start, Run und Overview.
- `dbx/modules/dbxShop/dbxShop.class.php`: umfangreicher Fachrouter.
- `dbx/modules/dbxWorkflow_admin/dbxWorkflow_admin.class.php`: delegierender
  Adminwrapper.

## Service-Grundgerüst

Datei `include/myTasksService.class.php`:

```php
<?php
namespace dbx\myTasks;

class myTasksService {

    private $dd = 'myTasks|myTask';

    private function baseUrl($run = 'report', array $params = array()) {
        $url = '?dbx_modul=myTasks&dbx_run1=' . rawurlencode($run);
        foreach ($params as $key => $value) {
            $url .= '&' . rawurlencode((string)$key) . '=' .
                rawurlencode((string)$value);
        }
        return $url;
    }

    public function detail() {
        $rid = (int)dbx()->get_modul_var('rid', 0, 'int');
        $row = dbx()->get_system_obj('dbxDB')->select1($this->dd, $rid);

        if ((int)($row['id'] ?? 0) <= 0) {
            return dbx()->get_system_obj('dbxTPL')->get_tpl(
                'dbx|alert-warning',
                array('msg' => 'Aufgabe nicht gefunden.')
            );
        }

        return dbx()->get_system_obj('dbxTPL')->get_tpl('myTasks|detail', array(
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'description' => (string)($row['description'] ?? ''),
        ));
    }
}

?>
```

Die Serviceklasse darf bei großen Fachbereichen weiter aufgeteilt werden,
beispielsweise in Repository, Service, Provideradapter oder Renderer. Der Shop
verwendet genau diese Aufteilung.

### Große Ablaufklassen ohne Laufzeit-Overhead zerlegen

Wenn mehrere Verantwortlichkeiten bewusst denselben Requestzustand teilen,
kann eine direkte Trait-Komposition sinnvoller sein als eine Kette von
Weiterleitungsobjekten. Verbindlich bleiben dabei fachlich benannte Blöcke,
explizite `require_once`-Einbindungen und eine kleine sichtbare Hauptklasse.
Magic-Dispatch, dynamische Trait-Suche und gegenseitige Service-Abhängigkeiten
sind nicht zulässig.

Die Referenz sind `dbxContent_cms` und `dbxShopAdmin`: Die Hauptklassen halten
nur Zustand, Einstieg und Aktionsvertrag. Form, Report, Page-Aktionen, Tree,
Katalog, Bestellung und die einzelnen Medienaufgaben liegen in benannten
`*Service.trait.php`-Dateien. PHP komponiert diese Methoden direkt in die
Klasse; dadurch entstehen weder zusätzliche Serviceobjekte noch zusätzliche
Datenbankabfragen. Persistente Fachgrenzen wie
`dbxContentCmsPersistenceService` bleiben eigenständige Klassen.

Der Vertrag wird mit `dbxModuleDecomposition_contract_test.php` kontrolliert:
Die Ablaufklasse bleibt unter 250 Zeilen, ein Verantwortungsblock unter 1000
Zeilen. Quelltextverträge lesen die explizite Komposition über
`dbxModuleSourceBundle.php`, statt Implementierung zurück in einen Monolithen
zu zwingen. Datenzugriff, Ausgabe und Eingaben verwenden auch innerhalb der
Blöcke weiterhin `dbxDB`, `dbxTPL`, `dbxForm` und `dbxReport`.

## Datenmodell

Eine neue persistente Tabelle erhält eine vollständige DD:

```text
dd/myTask.dd.php -> myTasks|myTask -> Tabelle my_task
```

Die DD folgt dem direkt lesbaren dbxapp-Exportformat: `TABLE`, `FIELDS` und
`INDEXES` werden mit `$table[...]`, `$field[...]`, `$fields[]=$field`,
`$index[...]` und `$indexes[]=$index` explizit definiert. Eine lokale
`$addField`-Closure oder DD-Includes sind dafür nicht zulässig.

Formularsichten und Reportfilter liegen separat:

```text
fd/myTask-form.fd.php
fd/rpt-myTask-selection.fd.php
```

Das vollständige DD-/FD-Muster steht unter @ref dbxapp_dbxdb_dd_fd.

## Formularmethode

```php
public function form() {
    $rid = (int)dbx()->get_modul_var('rid', 0, 'int');
    $data = $rid > 0
        ? dbx()->get_system_obj('dbxDB')->select1($this->dd, $rid)
        : array('status' => 'open', 'active' => 1);

    $form = dbx()->get_system_obj('dbxForm');
    $form->init('my-task-form');
    $form->_dd = $this->dd;
    $form->_fd = 'myTasks|myTask-form';
    $form->_data = $data;
    $form->_rid = $rid;
    $form->_action = $this->baseUrl('form', array(
        'rid' => $rid > 0 ? $rid : 'new',
    ));
    $form->add_flds();

    if ($form->submit() && !$form->errors()) {
        $ok = $form->save_post($this->dd, $rid > 0 ? $rid : 'new');
        $form->_msg_success = $ok ? 'Aufgabe gespeichert.' : '';
        $form->_msg_error = $ok ? '' : 'Speichern fehlgeschlagen.';
    }

    return $form->run();
}
```

Weitere Möglichkeiten wie einzelne Felder, Callbacks, Shells und eingebettete
Reports stehen unter @ref dbxapp_dbxform.

## Reportmethode

```php
public function report() {
    $db = dbx()->get_system_obj('dbxDB');
    $report = dbx()->get_system_obj('dbxReport');
    $report->init('my-task-report');
    $report->_dd = $this->dd;
    $report->_action = $this->baseUrl('report');
    $report->_pages = true;
    $report->_create_row_edit = true;
    $report->_create_row_delete = true;
    $report->create_selection_fields('myTasks|rpt-myTask-selection');

    $search = $report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64');
    $rows = max(10, min(100, (int)$report->get_fld_val('dbx_rrows', 30, 'int')));
    $pos = max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
    $sort = $report->get_fld_val('dbx_rsort', 'title', 'parameter');
    $desc = strtoupper((string)$report->get_fld_val('dbx_rdesc', 'ASC', 'parameter'));

    if (!in_array($sort, array('id', 'title', 'status', 'update_date'), true)) {
        $sort = 'title';
    }
    if (!in_array($desc, array('ASC', 'DESC'), true)) {
        $desc = 'ASC';
    }

    $where = array('trash' => 0);
    if ($search !== '') {
        $where['search'] = array(
            'value' => $search,
            'like' => array('title', 'description'),
            'mode' => 'contains',
        );
    }

    $report->_rflds = array(
        'id' => 'ID',
        'title' => 'Titel',
        'status' => 'Status',
        'update_date' => 'Aktualisiert',
    );
    $report->_rpt_format = array('update_date' => 'php-datetime-usr');
    $report->_rrows = $rows;
    $report->_rpos = $pos;
    $report->_count_all = $db->count($this->dd, array('trash' => 0));
    $report->_rcount = $db->count($this->dd, $where);
    $report->_rdata = $db->select(
        $this->dd, $where,
        array('id', 'title', 'status', 'update_date'),
        $sort, $desc, '', $rows, $pos
    );

    return $report->run();
}
```

Multi-Auswahl, Aktionen, HTML-Felder und TPL/Grid-Modus sind unter
@ref dbxapp_dbxreport beschrieben.

## Templates statt PHP-HTML

```php
return dbx()->get_system_obj('dbxTPL')->get_tpl('myTasks|detail', array(
    'title' => $row['title'],
    'status' => $row['status'],
));
```

```html
<article class="card my-task-detail">
 <div class="card-body">
  <h2 class="h4">{title}</h2>
  <p class="text-muted">{status}</p>
  <div>{description}</div>
 </div>
</article>
```

Mögliche Templatearten:

- Seiten-/Paneltemplate für eine einzelne Ausgabe.
- Formtemplate mit `[dbx:form]` und `{obj:*}`.
- Reporttemplate mit `dbx_split` und `[rpt:row]`.
- Row-/Cardtemplate für `_mode = 'tpl'`.
- Mail-, PDF- oder Drucktemplate innerhalb der dafür vorgesehenen Pipeline.

Templates können sprachabhängig als `name_de.htm`, `name_en.htm` usw. vorliegen.
dbxTPL verwendet die aktive Sprachvariante und anschließend den neutralen
Fallback.

### Menüeinträge des aktiven Hauptmoduls

Ein Hauptmodul kann einen Eintrag für das vorhandene Benutzer- oder Adminmenü
registrieren, ohne ein eigenes Menüsystem aufzubauen:

```php
$menu = dbx()->get_include_obj('dbxMenuSlot', 'dbxMenu');
$menu->register('user', 'menu-user', array('count' => $count));

if (dbx()->can('admin')) {
    $menu->register('admin', 'menu-admin');
}
```

Die Templates `tpl/htm/menu-user.htm` und `tpl/htm/menu-admin.htm` liefern nur
die zum vorhandenen Menü passende Struktur, normalerweise ein oder mehrere
`<li>`-Elemente. Dynamische Werte werden als Template-Daten übergeben; das
Modul wird nicht erneut ausgeführt. Sprachvarianten löst `dbxTPL` wie gewohnt
auf.

Die Kunden-Menütemplates entscheiden über die Position. Sie enthalten dafür
optional `{dbx:modul_menu_user}` beziehungsweise
`{dbx:modul_menu_admin}`. Fehlt ein Slot oder eine Registrierung, bleibt die
Ausgabe leer. Nur das aktive Hauptmodul darf registrieren; eingebettete Module
innerhalb eines bereits gerenderten CMS-Inhalts dürfen das Menü nicht
nachträglich verändern. Gastabhängige Beiträge müssen für URL, Sprache und
Design deterministisch sein, damit sie mit dem Full-Page-Cache verträglich
bleiben.

## Aufrufmöglichkeiten

### Direkter Modulrequest

```text
?dbx_modul=myTasks&dbx_run1=report
?dbx_modul=myTasks&dbx_run1=form&rid=12
```

### CMS- oder Template-Inclusion

```html
[modul=myTasks]dbx_run1=report&status=open[/modul]
```

### Mehrere Instanzen auf derselben Seite

```html
[modul=myTasks]dbx_run1=report&status=open[/modul]
[modul=myTasks]dbx_run1=report&status=done[/modul]
```

Form- und Reporttemplates verwenden `{i}` in IDs und Targets. Dadurch bleiben
Parameter-, AJAX- und UI-Zustände der Instanzen getrennt.

## Request-Werte

```php
$rid    = dbx()->get_modul_var('rid', 0, 'int');
$status = dbx()->get_modul_var('status', 'open', 'parameter');
$search = dbx()->get_modul_var('q', '', 'sqlsearch|max=64');
```

Der dritte Parameter ist die Validatorregel. Werte nicht direkt aus `$_GET`
oder `$_POST` in SQL, Templates oder Dateipfade übernehmen. dbxForm besitzt für
seine Felder eine eigene Requestpipeline; der Router liest nur Routenparameter.

## AJAX, openWin und Confirm

Vorhandene JavaScript-Libs werden deklarativ über Klassen/Datenattribute
angebunden:

```html
<a class="btn btn-primary dbx-win"
   href="?dbx_modul=myTasks&amp;dbx_run1=form&amp;rid={id}"
   data-dbx="lib=openWin|title=Aufgabe bearbeiten|width=70%|height=80%">
 Bearbeiten
</a>

<a class="btn btn-danger dbxAjax dbxConfirm"
   href="?dbx_modul=myTasks&amp;dbx_run1=delete&amp;rid={id}"
   data-confirm="Wirklich löschen?" data-confirm-buttons="yesno"
   data-target="dbx_target_{i}" data-replace="target">
 Löschen
</a>
```

Kein zweites Modal-, AJAX- oder Confirm-System im Modul einbauen.

`dbx_ajax=1` wird nicht manuell an normale Links angehängt. Nur `ajax.js` setzt
den Ajax-Kontext für den von ihm ausgeführten Request. Ein Link, der eine
vollständige CMS- oder Adminseite öffnen soll, bleibt ohne `dbx_ajax`.

Wenn eine Aktion erst bestätigt und danach in einem Fenster geladen werden
soll, dürfen Confirm- und openWin-Handler nicht gleichzeitig denselben Klick
verarbeiten. Entweder übernimmt `confirm.js` die deklarative Fortsetzung oder
der Modulhandler wartet programmatisch auf `dbx.confirm.open()` und ruft erst
bei `action === "yes"` den Ajax-/openWin-Schritt auf.

## JSON-API als eigene Route

```php
public function api() {
    $action = dbx()->get_modul_var('action', 'list', 'parameter');

    if ($action === 'list') {
        $rows = dbx()->get_system_obj('dbxDB')->select(
            $this->dd,
            array('active' => 1, 'trash' => 0),
            array('id', 'title', 'status'),
            'title', 'ASC', '', 100, 0
        );
        dbx()->json_response(array('ok' => 1, 'items' => $rows));
    }

    dbx()->json_response(array(
        'ok' => 0,
        'message' => 'Unbekannte Aktion.',
    ));
}
```

API und HTML sind getrennte Antwortarten. Ein API-Endpunkt rendert kein
Formtemplate und eine normale AJAX-Formaktion liefert in der Regel HTML für ihr
Target. Rechte gelten für beide Wege identisch.

## Frontend- und Adminmodul trennen

Empfohlen bei größeren Anwendungen:

```text
myTasks/        öffentliche/benutzerbezogene Anzeige und Aktionen
myTasks_admin/  Installation, Pflege, Reports und Konfiguration
```

Gemeinsame Fachlogik kann über klar benannte Klassen des Fachmoduls genutzt
werden. Das Adminmodul darf aber nicht die Rechteprüfung des Fachmoduls umgehen.
Ein gutes reales Muster sind `dbxShop` und `dbxShop_admin`.

## Installation und Schema

Ein Installationspfad synchronisiert nur die DDs seines Moduls:

```php
public function install() {
    $dd = dbx()->get_system_obj('dbxDD');
    $dd->sync_dd_to_db('myTasks', 'myTask', 'reset');

    do {
        $state = $dd->sync_dd_to_db('myTasks', 'myTask', 'apply');
    } while (($state['status'] ?? '') === 'running');

    return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-info', array(
        'msg' => ($state['status'] ?? '') === 'finished'
            ? 'Modul installiert.'
            : (string)($state['message'] ?? 'Installation fehlgeschlagen.'),
    ));
}
```

Installation ist eine Adminaktion. Sie läuft nicht bei jedem normalen Request.

## Assets und Skin-Fähigkeit

Modulspezifisches CSS und JavaScript liegen unter `design/css` bzw.
`design/js`. CSS verwendet die vom aktiven Design bereitgestellten Variablen
und Komponenten. Farben, Abstände oder Hintergründe nicht so fest verdrahten,
dass `dbxapp`, `flowers` oder weitere Skins unlesbar werden.

JavaScript erweitert die vorhandenen Libs und initialisiert sich wiederholbar,
auch nachdem AJAX neuen HTML-Inhalt eingesetzt hat.

## Empfohlene Reihenfolge für ein neues Modul

1. Fachzweck, Benutzergruppen und Routen festlegen.
2. Ähnliches vorhandenes Modul lesen.
3. DD und gegebenenfalls FD modellieren.
4. Templates und eindeutige Targets anlegen.
5. Kleinen Router und Fachservice implementieren.
6. dbxForm für Eingaben und dbxReport für Listen verwenden.
7. Adminzugriff und Installation separat schützen.
8. Direkten Request und `[modul=...]`-Einbettung testen.
9. AJAX, openWin, Confirm, Mehrfachinstanzen und aktive Skins testen.
10. Doxygen und Modul-README aktualisieren.

Der aktuelle `dbxWizard` kann ein neues Modul oder Ergänzungen für ein
vorhandenes Modul erzeugen. Er validiert Modul- und Dateinamen, beschränkt alle
Ziele auf `dbx/modules/{modul}/`, kann DD/FD, Router, Service, Formular, Report
und Templates generieren und legt vor Überschreibungen Sicherungen unter
`files/module-backup/` an. Nach der Erzeugung werden PHP-Syntax und die Route
mit `dbx/modules/dbxAdmin/tests/dbxWizard_generation_test.php` geprüft.

## Verbindliche Regeln

- Modulcode bleibt unter `dbx/modules/{modul}/`.
- Router klein halten; Fachlogik in Include-/Serviceklassen.
- Datenzugriff über dbxDB und DD, Eingaben über dbxForm, Listen über dbxReport.
- Ausgabe über dbxTPL; keine großen HTML-Strings in PHP.
- Konfiguration über `cfg/config.php` und `dbx()->get_cfg()`.
- Request-Werte mit `get_modul_var()` oder dbxForm validieren.
- Bestehende AJAX-, openWin-, Confirm- und Core-Libs verwenden.
- Keine privaten `db()`-, `tpl()`- oder Escape-Aliase anlegen, die nur
  vorhandene `dbx()`-Methoden weiterreichen.
- Automatische DD-Systemfelder nicht im Modul nachbauen.
- Frontend, Admin, API und Installation haben jeweils klare Rechte und
  Antwortarten.
- Mehrfachinstanzen verwenden `{i}` und getrennte Targets.
- Moduloberflächen bleiben responsive und skin-fähig.
- Reine GET-Navigation bleibt tokenlos. Schreibende GET-Aktionen verwenden den
  vorhandenen Action-Token zusätzlich zu Modul- und DD-Rechten.

## Reale Referenzen

- `dbx/modules/dbxAdmin/include/dbxWizard.class.php`: aktueller Generator für
  Router, Service, DD, FD, Form und Report.
- `dbx/modules/dbxContact`: kompaktes Frontend-/Adminmuster.
- `dbx/modules/dbxWorkflow`: deklarativer Fachablauf mit eigener Engine.
- `dbx/modules/dbxShop` und `dbxShop_admin`: große Anwendung mit Repository,
  Service, Providern, Frontend und Administration.
- @ref dbxapp_module_reference — vollständiger und normativer Golden Path.
- @ref dbxapp_dbxdb_dd_fd, @ref dbxapp_dbxform und @ref dbxapp_dbxreport.
