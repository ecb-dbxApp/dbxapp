# dbxReport {#dbxapp_dbxreport}

`dbxReport` ist die Listenpipeline von dbxapp. Sobald eine Ansicht Suche,
Sortierung, Pagination, Zeilenaktionen oder Mehrfachauswahl benötigt, sollte sie
nicht als eigene Schleife mit eigener UI-Logik entstehen.

## Einordnung im Golden Path

@ref dbxapp_module_reference zeigt einen vollständigen Report mit
Selection-FD, sicherer Sortierung, Zeilenaktionen, Confirm und Ajax. Dieses
Kapitel vertieft Table-, TPL-, Grid-, Auswahl- und Callback-Fähigkeiten.

`dbxReport` erweitert bewusst `dbxForm`: Reportfilter sind Formfelder und
verwenden denselben validierten Zustand. Die gemeinsame Pipeline ist ein
Kompatibilitätsvorteil, kein Anlass für zwei konkurrierende Formularsysteme.
Das gilt auch für sprachabhängige Selection-FDs und deren `$messages`:
`dbxReport` verwendet dieselbe Dateiauflösung, denselben Cache sowie
`save_success` und `save_error` im geerbten Speicherpfad. Eine zweite
Report-spezifische Meldungslogik ist nicht erforderlich.

Für Reporttitel, Spaltenköpfe, Statuswerte, Bestätigungen und Footer gilt
derselbe Vertrag. `load_fd_messages()` lädt eine Selection-FD auch dann, wenn
der Report keine sichtbaren Filterfelder benötigt; `format_fd_message()`
ersetzt dynamische Platzhalter:

```php
$report->_fd = 'myInvoices|rpt-invoice-selection';
$report->load_fd_messages();
$report->_rflds = array(
    'invoice_no' => $report->get_fd_message('column_invoice_no'),
    'total' => $report->get_fd_message('column_total'),
);
$question = $report->format_fd_message(
    'delete_question',
    array('id' => $rid)
);
```

Auch bei einem Report ohne sichtbare Selection-Felder wird `_fd` vor
Spaltenaufbau und Meldungszuweisung gesetzt. Titel, Spaltenköpfe, Statuslabels,
leere Zustände, Summenbeschriftungen und fachliche Confirmtexte stammen aus
diesem FD-Vertrag:

```php
$report->_fd = 'myTasks|task-report';
$report->load_fd_messages();
$report->add_rep('bar_title', $report->get_fd_message('report_title'));
$report->_rflds = array(
    'title' => $report->get_fd_message('column_title'),
    'status' => $report->get_fd_message('column_status'),
    'total' => $report->get_fd_message('column_total'),
);
```

`dbxReport` benötigt dafür keinen zweiten Übersetzungsmechanismus, weil es die
komplette FD- und Meldungspipeline von `dbxForm` erbt. Datenwerte aus einer
einsprachigen Tabelle werden davon bewusst nicht übersetzt.

@image html dbxapp-report-flow.svg "dbxReport-Ablauf"

## Fähigkeiten

- Tabellen-, Template- und Grid-Ausgabe.
- DD-/FD-basierte Filterfelder.
- Suche, Sortierung, Seitenlänge und Offset.
- Einzelaktionen wie Anzeigen, Bearbeiten, Kopieren oder Löschen.
- seitenübergreifende Auswahl und Multi-Aktionen.
- Ausgabeformate und bewusst freigegebene HTML-Spalten.
- Callbacks für Report-, Seiten-, Tabellen- und Datensatzebene.
- AJAX-Reload in ein eindeutiges Report-Target.

## Lebenszyklus

```text
init(fid, template)
  -> DD, Action und Modus setzen
  -> Selection-FD/Felder anlegen
  -> Report-Aktion behandeln
  -> Filterwerte lesen und WHERE bauen
  -> count_all und rcount bestimmen
  -> rdata mit Limit/Offset laden
  -> run() rendert Filter, Pagination, Header, Rows, Footer
```

## Reales Tabellenreport-Muster

Das folgende Beispiel entspricht dem Workflow-Adminreport und verwendet
bewusst getrennte Gesamtzahl, Trefferzahl und Datensätze:

```php
public function report() {
    $dd = 'myTasks|myTask';
    $db = dbx()->get_system_obj('dbxDB');
    $report = dbx()->get_system_obj('dbxReport');

    $report->init('my-task-report');
    $report->_dd = $dd;
    $report->_action = '?dbx_modul=myTasks&dbx_run1=report';
    $report->_pages = true;
    $report->_but_pagination = 7;
    $report->_create_row_select = false;
    $report->_create_row_edit = false;
    $report->_create_row_delete = false;
    $report->_msg_info = 'Aufgaben filtern und bearbeiten.';

    $report->create_selection_fields('myTasks|rpt-myTask-selection');

    if ($report->submit()) {
        $report->_msg_info = $report->errors()
            ? 'Bitte Filtereingaben prüfen.'
            : 'Filter angewendet.';
    }

    $search = $report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64');
    $rows   = max(10, min(100, (int)$report->get_fld_val('dbx_rrows', 30, 'int')));
    $pos    = max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
    $sort   = $report->get_fld_val('dbx_rsort', 'title', 'parameter');
    $desc   = strtoupper((string)$report->get_fld_val('dbx_rdesc', 'ASC', 'parameter'));

    $allowedSort = array('id', 'title', 'status', 'update_date');
    if (!in_array($sort, $allowedSort, true)) {
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

    $columns = array('id', 'title', 'status', 'active', 'update_date');
    $data = $db->select($dd, $where, $columns, $sort, $desc, '', $rows, $pos);

    foreach ((array)$data as &$row) {
        $row['status_view'] = $this->statusBadge($row['status'] ?? '');
        $row['action'] = dbx()->get_system_obj('dbxTPL')->get_tpl(
            'myTasks|task-row-action',
            array('id' => (int)($row['id'] ?? 0))
        );
    }
    unset($row);

    $report->_rflds = array(
        'id' => 'ID',
        'title' => 'Titel',
        'status_view' => 'Status',
        'active' => 'Aktiv',
        'update_date' => 'Aktualisiert',
        'action' => 'Aktion',
    );
    $report->_rpt_format = array(
        'status_view' => 'html',
        'update_date' => 'php-datetime-usr',
        'action' => 'html',
    );
    $report->_rrows = $rows;
    $report->_rpos = $pos;
    $report->_count_all = $db->count($dd, array('trash' => 0));
    $report->_rcount = $db->count($dd, $where);
    $report->_rdata = $data;

    return $report->run();
}
```

### Warum die Werte getrennt werden

| Eigenschaft | Bedeutung |
| --- | --- |
| `_count_all` | alle grundsätzlich verfügbaren Datensätze |
| `_rcount` | Trefferzahl nach dem aktuellen Filter |
| `_rrows` | Zeilen pro Seite |
| `_rpos` | Offset der aktuellen Seite |
| `_rdata` | tatsächlich geladene Datensätze dieser Seite |
| `_rflds` | auszugebende Felder und Labels |
| `_rpt_format` | Format je Ausgabefeld |

Pagination funktioniert nur korrekt, wenn Zählung und Datenabfrage dieselbe
WHERE-Bedingung verwenden.

## Selection-FD für Filter und Sortierung

```php
<?php

$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';

$field = array();
$field['name'] = 'dbx_rrows';
$field['type'] = 'int';
$field['tpl'] = 'select-single-label';
$field['default'] = '30';
$field['label'] = 'Anz. Seite';
$field['rules'] = 'int';
$field['options'] = '10=10&20=20&30=30&50=50&100=100';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rsort';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'title';
$field['label'] = 'Sortierung';
$field['rules'] = 'parameter';
$field['options'] = 'id=ID&title=Titel&status=Status&update_date=Update';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rdesc';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'ASC';
$field['label'] = 'Richtung';
$field['rules'] = 'parameter';
$field['options'] = 'ASC=Aufsteigend&DESC=Absteigend';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rwhere';
$field['type'] = 'varchar';
$field['tpl'] = 'dbx|search';
$field['default'] = '';
$field['label'] = 'Suchen';
$field['rules'] = 'sqlsearch|max=64';
$fields[] = $field;

?>
```

Sortierfelder werden als feste Optionsliste vorgegeben und im Controller
zusätzlich gegen dieselbe Allowlist geprüft. Das ist wichtig, weil ein Request
manipuliert werden kann und `ORDER BY` keine freie Benutzereingabe erhalten
darf.

## Report-Template mit `dbx_split`

```html
<div class="dbx-panel dbxReport dbx-ajax-root" id="dbx_target_{i}"
     data-dbx="lib=report|form=0">
 <form action="{action}" method="post" id="dbx_form_{i}" class="dbxAjax">
  [dbx:form]
  [tpl=dbx|report-form-select]
  [dbx:pagination]
  <div class="table-responsive dbx-report-table-scroll">
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
  [dbx:js]
 </form>
</div>
```

`dbx_split` teilt ein Reporttemplate in Header, wiederholte Zeile und Footer.
`[rpt:row]` wird im Header durch Spaltenköpfe und im Body durch Zellen ersetzt.
Das reale Template steht unter
`dbx/modules/dbxWorkflow_admin/tpl/htm/workflow-definitions.htm`.

## Ausgabeformate und HTML

Reportwerte laufen durch die Reportformatierung. Normale DD-Werte werden nicht
im Modul pauschal escaped. Konvertierungen und bewusst erzeugte
Komponentenfragmente werden feldweise angegeben:

```php
$report->_rpt_format = array(
    'price' => 'number',
    'update_date' => 'php-datetime-usr',
    'status_view' => 'html',
    'action' => 'html',
);
```

`html` ist nur für serverseitig kontrollierte Fragmente geeignet. Ein
zusätzliches `html-chars` ist nur dann sinnvoll, wenn ein roher Fremdwert
ausdrücklich als literaler HTML-Text erscheinen soll. Es ist nicht der
Standardwrapper für jeden Datenbankwert.

## Zeilenaktionen

### Eigene Aktionsspalte

```html
<div class="btn-group btn-group-sm">
 <a class="btn btn-outline-primary dbxOpenWin"
    href="?dbx_modul=myTasks&amp;dbx_run1=edit&amp;rid={id}"
    data-title="Aufgabe bearbeiten">
  <i class="bi bi-pencil"></i>
 </a>
 <a class="btn btn-outline-danger dbxAjax dbxConfirm"
    href="{delete_url}"
    data-confirm="Aufgabe wirklich löschen?"
    data-confirm-buttons="yesno"
    data-ajax-target="dbx_target_{i}" data-ajax-replace="target">
  <i class="bi bi-trash"></i>
 </a>
</div>
```

### Automatische Aktionen

dbxReport kann Standardaktionen erzeugen:

```php
$report->_create_row_select = true;
$report->_create_row_edit   = true;
$report->_create_row_show   = true;
$report->_create_row_copy   = false;
$report->_create_row_delete = true;
$report->_msg_confirm_delete = 'Diesen Datensatz wirklich löschen?';
```

Der Controller muss die resultierenden `dbx_do`-Aktionen behandeln und danach
wieder denselben Report rendern. UI-Erzeugung ersetzt keine Berechtigungsprüfung
im Servercode.

Die mutierenden Standardaktionen `row_delete`, `delete_tab`, `multi_delete`
sowie Aktivieren/Deaktivieren werden vom Report automatisch über
`dbxApi::action_url()` signiert. `dbxWebApp` prüft die zugehörige Policy vor
dem Modulstart. Filter, Report-Sortierung, Pagination, Show und Edit bleiben
unverändert und tokenlos. Fachmodule bauen für diese Standardfälle weder
eigene Scopes noch eigene Tokenprüfungen.

Auch individuelle Standard-Links brauchen keine Modulkonfiguration:
`delete` oder `save` in `dbx_run1`, `dbx_run2`, `dbx_run3` beziehungsweise
`dbx_do` werden zusammen mit `rid` von `dbx()->action_url($url)` automatisch
erkannt. dbxWebApp bindet die RID an den Scope und prüft den Request vor dem
Modulstart. Modul- und DD-Rechte bleiben zusätzlich verbindlich.

## Einzel- und Multi-Delete

```php
$do  = dbx()->get_modul_var('dbx_do', '', 'parameter');
$rid = (int)dbx()->get_modul_var('rid', 0, 'int');

if ($do === 'row_delete' && $rid > 0) {
    $ok = $db->delete($dd, $rid);
    $report->del_selected($rid);
    $report->_msg_success = $ok ? 'Datensatz gelöscht.' : '';
    $report->_msg_error = $ok ? '' : 'Datensatz konnte nicht gelöscht werden.';
}

if ($do === 'multi_delete') {
    $result = $report->delete_multi_selected_records($dd);
    $report->apply_multi_delete_result($result);
}
```

Konfiguration für eine seitenübergreifende Auswahl:

```php
$report->_multi_page_select = 1;
$report->_create_sel_flds = true;
$report->_create_row_select = true;

$report->add_action('rows_select', 'action_button_select', '&dbx_do=rows_select');
$report->add_action('rows_deselect', 'action_button_deselect', '&dbx_do=clear_selects');
$report->add_action('rows_delete', 'action_button_delete', '&dbx_do=multi_delete');
```

Auswahlzustand wird über die Report-/Remember-Pipeline geführt. Keine parallele
Session-Liste im Fachmodul anlegen.

## Callbacks richtig einsetzen

`dbxReport` nutzt die von `dbxForm` geerbten Callback-Defaults. `init()` merkt
sich den direkten Modul-/Service-Aufrufer als Owner. Die normalisierte
Formular-ID ist der Methodenpräfix:

```php
$report->init('task-report', 'myModule|task-report');

public function task_report_next_record($report, $record) {
    if (is_array($record)) {
        $record['status_view'] = $this->statusBadge($record['status'] ?? '');
    }
    return $record;
}
```

`task-report` sucht damit automatisch `task_report_next_record()`,
`task_report_body()`, `task_report_footer()` und die weiteren Events.
`set_callback_owner()` und `set_*_callback()` sind nur nötig, wenn bewusst ein
anderer Owner oder Methodenname verwendet wird.

Explizite Abweichung:

```php
$report->set_next_record_callback('legacyPrepareRecord');
```

Weitere Ebenen sind Report-Header, Page-Header, Tabellen-Header,
Tabellen-Footer, Page-Footer und Report-Footer. Sie verändern bereits
gerenderten Content. Für die Aufbereitung einzelner Zeilen ist der
`{fid}_next_record`-Default die passendere Stelle.

### Berechnete Summenspalte und Endsumme im Table-Footer

Eine virtuelle Spalte gehört nicht in eine zweite Datenbankschleife. Im
`table`-Modus setzt der automatische Datensatz-Callback den Wert unmittelbar
vor der Zeilenausgabe und aktualisiert den Footerwert spät per `add_rep()`:

```php
private int $itemTotalCents = 0;

public function invoice_items_report_next_record($report, $record)
{
    if (!is_array($record)) {
        return $record;
    }

    $quantity = (float)($record['quantity'] ?? 0);
    $unitPrice = (float)($record['unit_price'] ?? 0);
    $sumCents = (int)round($quantity * $unitPrice * 100);

    $this->itemTotalCents += $sumCents;
    $report->add_rep(
        'report_total',
        number_format(
            $this->itemTotalCents / 100,
            2,
            ',',
            '.'
        ) . ' EUR'
    );
    $record['sum'] = number_format(
        $sumCents / 100,
        2,
        ',',
        '.'
    ) . ' EUR';

    return $record;
}
```

Die Report-ID aktiviert den Callback bereits über die Namenskonvention:

```php
$report->init(
    'invoice-items-report',
    'myInvoices|invoice-items-report'
);
$report->_mode = 'table';
$report->_rflds = array(
    'position_no' => 'Pos.',
    'article_no' => 'Artikelnummer',
    'description' => 'Artikel',
    'quantity' => 'Menge',
    'unit_price' => 'Einzelpreis',
    'sum' => 'Summe',
);

$this->itemTotalCents = 0;
```

Der Bereich nach dem zweiten `dbx_split` ist der Tabellen-Footer und wird erst
nach allen angezeigten Datensätzen verarbeitet:

```html
<tfoot>
 <tr>
  <th colspan="{rpt:colspan}" class="text-end">
   Endsumme der Positionen
  </th>
  <th class="text-end">{report_total}</th>
 </tr>
</tfoot>
```

Die von `dbxForm` geerbte `replaces()`-Pipeline wendet das zuletzt per
`add_rep()` gesetzte Ergebnis beim Footerlauf an. `{rpt:colspan}` spannt
automatisch über alle Spalten außer der letzten Summenspalte;
`{rpt:col_count}` bleibt für die vollständige Spaltenzahl verfügbar. Im
Rechnungsbeispiel ist der Positionsreport nicht paginiert, daher umfasst die
Endsumme alle Positionen. Bei Pagination wäre es bewusst die aktuelle Seite.

## Die drei Ausgabemodi

### Table

```php
$report->_mode = 'table';
```

Standard für Adminlisten, Bestellungen, Benutzer und Workflow-Instanzen. Er
passt zu Sortierung, Pagination, Aktionen und responsiver Tabellenhülle.

### TPL

```php
$report->_mode = 'tpl';
```

Jeder Datensatz wird über ein Row-Template ausgegeben. Das eignet sich für
Produktkarten, Dashboards, Medienkacheln und Activity-Feeds. Suche und
Pagination bleiben trotzdem Teil von dbxReport.

### Tabulator/Grid

```php
$report->_mode = 'tabulurator';
```

Die Schreibweise `tabulurator` ist derzeit der intern verwendete
Kompatibilitätswert. Der Modus ist für interaktive Datengitter und größere
Admin-Datenmengen gedacht. Vor einer neuen Grid-Ansicht ein bestehendes Beispiel
wie `dbxSchema` oder `dbxContent_sections` übernehmen, weil Spalten- und
JavaScript-Konfiguration dazugehören.

Schreibende Grid-URLs müssen der vorhandenen dbxReport-Konvention folgen:

```text
records_grid_save
records_grid_insert
records_grid_delete
records_grid_sort
records_grid_sync
```

Die historischen `dbxSchema`-Varianten `data_<aktion>` und
`fields_<aktion>` bleiben kompatibel. `dbxReport` signiert Save, Insert,
Delete, Sort und Sync automatisch; Read bleibt tokenlos. `dbxWebApp` erkennt
die eigentliche Zielroute auch bei einer direkt konstruierten Anfrage.
Modulcode ergänzt keinen Marker und prüft keinen zweiten Token. Eine
unbekannte schreibende Grid-Konvention wird fail-closed nicht als URL
ausgegeben.

## AJAX-Verhalten

Der Report besitzt ein eigenes Target:

```html
<div id="dbx_target_{i}" class="dbxReport dbx-ajax-root">
 <form class="dbxAjax" action="{action}" method="post">
```

Filter, Pagination und Aktionen sollen nach dem Request genau dieses Target
neu rendern. Für mehrfach eingebettete Reports ist `{i}` zwingend; eine feste
globale ID führt zu falschen Ersetzungen.

## Verbindliche Regeln

1. Listen mit Filter, Sortierung oder Pagination verwenden dbxReport.
2. Auswahl- und Pagingzustand nicht neben der Reportpipeline duplizieren.
3. Filterwerte validieren und WHEREs über dbxDB strukturiert bauen.
4. `_count_all`, `_rcount` und `_rdata` konsistent berechnen.
5. HTML nur für kontrollierte Spalten mit `_rpt_format = 'html'` freigeben.
6. Zeilenaktionen im Template oder über Standardaktionen erzeugen, serverseitig
   aber immer erneut Rechte und Datensatz prüfen.
7. Nach einer Aktion denselben Report für dasselbe Target rendern.
8. Table, TPL und Grid nach Darstellungsziel wählen, nicht eigene Pipelines
   erfinden.
9. Standardmutationen automatisch signieren lassen; individuelle mutierende
   Links deklarieren und über `action_url()` führen.
10. `{fid}_{event}`-Callback-Defaults verwenden und Footerwerte spät per
    `add_rep()` setzen; reine `str_replace()`-Footer-Callbacks vermeiden.

## Reale Referenzen

- `dbx/modules/dbxWorkflow_admin/include/dbxWorkflowAdmin.class.php`:
  filterbarer Tabellenreport und Workflow-Instanzen.
- `dbx/modules/dbxAdmin/include/dbxWizard.class.php`: vollständiges generiertes
  Muster mit Multi-Select, Multi-Delete und Callbacks.
- `dbx/modules/dbxShop/include/dbxShopService.class.php`: TPL-Report für
  Produktkarten.
- `dbx/modules/dbxShop_admin/include/dbxShopAdmin.class.php`: umfangreiche
  Tabellenreports und Massenaktionen.
- `dbx/modules/dbxContent_admin/include/dbxContent_sections.class.php`:
  Table-, TPL- und Grid-Varianten.
- @ref dbxapp_module_reference — verbindliches Gesamtbeispiel.
- @ref dbxapp_javascript_libs — Browserablauf für Report, Confirm und Ajax.
