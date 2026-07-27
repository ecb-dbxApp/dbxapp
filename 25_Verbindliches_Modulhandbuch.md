# Verbindliches Modulhandbuch {#dbxapp_module_reference}

**Referenzstand:** 25. Juli 2026  
**Beispielmodul:** `myInvoices`

Dieses Kapitel ist der verbindliche Golden Path für ein datenbasiertes
dbXapp-Modul. Das vollständige Beispiel verwaltet Rechnungen und deren
Artikelpositionen. Es verbindet `dbxTPL`, `dbxDB`, DD, FD, `dbxForm`,
`dbxReport`, Modul-Inclusion, `ajax.js` und `confirm.js` in einem
zusammenhängenden Ablauf.

Das Beispiel ist nicht nur Pseudocode. Die installierbare Referenz liegt unter
`dbx/modules/myInvoices/`. Die Codeblöcke dieses Kapitels erklären die
entscheidenden Verträge; die Moduldateien, Fixtures und ausführbaren Tests
sind die maßgebliche, gemeinsam gepflegte Fassung.

Die Wörter **MUSS**, **DARF NICHT**, **SOLL** und **DARF** sind normativ:

- **MUSS** und **DARF NICHT** beschreiben den dbXapp-Vertrag.
- **SOLL** ist die Standardentscheidung; eine Abweichung braucht einen
  dokumentierten fachlichen Grund.
- **DARF** bezeichnet eine unterstützte Variante.

## Das Zielbild

Die erste Listenstufe zeigt Rechnungen. Direkt unter jeder Rechnungszeile ruft
ein serverseitiger Modulmarker die zweite Listenstufe auf:

```html
[modul=myInvoices]dbx_run1=positions&invoice_id=17[/modul]
```

Die zweite Stufe zeigt die Artikelpositionen genau dieser Rechnung. Ihr
Record-Callback berechnet für jede Position `Menge * Einzelpreis`, setzt die
virtuelle Spalte `sum` und sammelt Centbeträge. Ihr Footer-Callback setzt die
Endsumme und berechnet den benötigten `colspan` aus der tatsächlich von
`dbxReport` aufgebauten Spaltenzahl.

```text
Request oder [modul=myInvoices]
        |
        v
kleiner Router myInvoices.class.php
        |
        v
myInvoicesService
   |          |           |
   |          |           +--> dbxTPL --> HTML-Templates
   |          +--------------> dbxForm / dbxReport --> FD
   +-------------------------> dbxDB --> DD --> SQLite/MySQL
                                      |
Rechnungsreport                      v
   +--> [modul=myInvoices] --> Positionsreport je Rechnung

Browser:
core.js --> confirm.js --> ajax.js --> gleicher Modulrequest
```

Der `[modul=...]`-Aufruf ist kein zweiter Browserrequest. Der
`dbxInterpreter` setzt die angegebenen Parameter als geschützte Modulvariablen,
ruft den Modulrouter im aktuellen Owner-Kontext auf und ersetzt den Marker
serverseitig durch das Ergebnis. Modulzugriff und DD-Rechte bleiben aktiv.

`confirm.js` entscheidet nur, ob eine Benutzeraktion fortgesetzt wird.
`ajax.js` transportiert den Request und ersetzt HTML. Berechtigung,
Validierung, Transaktion und Mutation bleiben vollständig auf dem Server.

## Welche Schicht entscheidet was?

| Frage | Zuständige Schicht | Nicht dort ablegen |
| --- | --- | --- |
| Welche Route läuft? | Modulrouter | SQL, Formularaufbau, Markup |
| Welche fachliche Operation läuft? | Service | Layoutdetails |
| Woher kommen Daten? | `dbxDB` über DD | PDO oder SQL im Modul |
| Wie sehen Tabellen und Rechte aus? | DD | Controller-Arrays |
| Welche Felder zeigt ein Formular? | FD | zweite Tabellenbeschreibung |
| Wie wird eingegeben und validiert? | `dbxForm` | direkte `$_POST`-Speicherung |
| Wie werden beide Stufen gelistet? | je ein `dbxReport` | eigene Tabellenloops |
| Wie wird eine Unterliste eingebettet? | `[modul=...]...[/modul]` | interner HTTP-Aufruf |
| Wie wird HTML strukturiert? | `dbxTPL`-Template | große PHP-HTML-Strings |
| Wie werden Zeilenwerte berechnet? | Record-Callback | Zusatzschleife nach dem Report |
| Wie wird die Endsumme gesetzt? | Footer-Callback | JavaScript-Nachberechnung |
| Wer darf mutieren? | Modulzugriff, DD-Rechte, ggf. Action-Token | Confirm-Dialog |

## Warum die großen Kernelklassen nicht nach Größe geteilt werden

`dbxDB`, `dbxDD`, `dbxForm` und `dbxReport` sind stabile Systemfassaden und
Pipelines. Ihre Größe folgt ihrer Fähigkeitstiefe:

- `dbxDB` vereinheitlicht Server, DD-Auflösung, Rechte, Owner-Filter,
  Transaktionen, Trace, Fehler und Performance.
- `dbxDD` erweitert den Datenpfad um Schema-, Backup-, Restore- und
  Transferprozesse.
- `dbxForm` hält einen vollständigen Formularzustand von FD/DD bis Validierung,
  Meldung, Submit-Schutz und Rendern.
- `dbxReport` erweitert `dbxForm` um Selection-Felder, Pagination,
  Zeilenaktionen, Auswahlzustand, Callbacks und mehrere Ausgabemodi.

Eine Kernelaufteilung ist nur mit stabiler Fassade, Regressionstests und einer
bewiesenen Verantwortungsgrenze sinnvoll. Fachmodule dürfen dagegen echte
Fachlogik in Services oder Repositories gliedern. Sie dürfen Kernel-Funktionen nicht durch eigene Methoden nachbauen, die nur `dbx()` weiterreichen.

## Verzeichnisstruktur des Referenzmoduls

```text
dbx/modules/myInvoices/
  myInvoices.class.php
  cfg/config.php
  include/myInvoicesService.class.php
  include/myInvoicesFixtures.class.php
  dd/invoice.dd.php
  dd/invoiceItem.dd.php
  fd/invoice-form.fd.php
  fd/rpt-invoice-selection.fd.php
  tpl/htm/invoice-form.htm
  tpl/htm/invoice-report.htm
  tpl/htm/invoice-items-report.htm
  tpl/htm/invoice-row-action.htm
  tpl/htm/invoice-install.htm
  tpl/htm/install-required.htm
  tools/install_demo.php
  tests/myInvoices_contract_test.php
  tests/myInvoices_integration_test.php
  tests/README.md
  README.md
```

Die zwei Tabellen liegen auf demselben DD-Server. Dadurch kann eine
Mehrtabellenmutation über eine gemeinsame `dbxDB`-Transaktion laufen.

## Routen- und Sicherheitsvertrag

| Route | Zweck | Zustand ändert sich? | Schutz |
| --- | --- | ---: | --- |
| `dbx_run1=report` | Rechnungen, Filter, Pagination | nein | Modul- und DD-Leserecht |
| `dbx_run1=positions&invoice_id=17` | eingebettete Positionen | nein | Modul- und beide DD-Leserechte |
| `dbx_run1=form&rid=17` | Rechnungskopf bearbeiten | POST ja | `dbxForm` plus DD-Schreibrecht |
| `dbx_run1=delete&rid=17` | Rechnung samt Positionen löschen | ja | Modulrecht, DD-Löschrecht, Action-Token |
| `dbx_run1=install` | DD-Sync und idempotente Demo-Fixtures | POST ja | Adminrecht und `dbxForm` |

Normale Navigation, Filter, Report und Positions-Inclusion bleiben tokenlos.
Ein Standardformular erhält keinen zusätzlichen `dbx_token`, weil `dbxForm`
seinen Submit schützt. Bei der schreibenden Delete-URL erkennt
`dbxApi::action_url()` die Kombination aus `delete` und `rid` automatisch,
bindet die RID und ergänzt den Token. `dbxWebApp` prüft ihn vor dem Aufruf des
Moduls. Eine `action_routes`-Konfiguration ist dafür nicht nötig.

DD- und Modulrechte beantworten, **wer** handeln darf. Der Action-Token
beweist zusätzlich, dass der mutierende GET aus dem aktuellen Browserkontext
stammt. Er ersetzt keine Rechteprüfung.

## 1. Modulkonfiguration

Datei `cfg/config.php`:

```php
<?php

$config['version'] = '1.0';
$config['activ'] = '1';
$config['groups'] = 'admin';
$config['page_size'] = '20';

?>
```

Die Konfiguration schützt nur den Moduleinstieg und enthält keine doppelte
Beschreibung einzelner Standardaktionen. Die automatische Action-Policy
bindet das Token an Route und Rechnungs-ID; ein Token für Rechnung 17 gilt
deshalb nicht für Rechnung 18. Fachwerte gehören nicht in eine parallele
JSON-Datei oder in JavaScript.

## 2. DD des Rechnungskopfs

Datei `dd/invoice.dd.php`. Doxygen bindet die tatsächlich ausgeführte DD
direkt ein; Dokumentation und Modul können dadurch nicht auseinanderlaufen:

@include dbx/modules/myInvoices/dd/invoice.dd.php

`total_gross` ist ein fachlicher Snapshot, kein dbx-Systemfeld. Er wird beim
Ändern von Positionen innerhalb derselben Fachtransaktion aktualisiert. Die
Reportcallbacks formatieren und summieren ihn, verändern ihn aber nicht.

## 3. DD der Rechnungspositionen

Datei `dd/invoiceItem.dd.php`. Auch hier ist die ausführbare DD selbst die
einzige Quelle der Dokumentation:

@include dbx/modules/myInvoices/dd/invoiceItem.dd.php

Beide DDs enthalten die Systemfelder `create_date`, `create_uid`, `owner`,
`update_date` und `update_uid`. Diese Werte werden automatisch von `dbxDB` gesetzt.
Das Modul setzt sie weder beim Insert noch beim Update selbst. Genau das ist
ein Vorteil der DD-Nutzung. Die DDs folgen dem dbxapp-Exportformat mit
expliziten `TABLE`-, `FIELDS`- und `INDEXES`-Abschnitten. Es gibt darin keine
Closure oder Hilfsmethode, welche Feldattribute verbirgt.

## 4. FD für den Rechnungskopf

Datei `fd/invoice-form.fd.php`:

Die Dokumentation bindet die tatsächlich ausgeführte FD ein. Dadurch können
Meldungsschlüssel, Labels und Sprachvertrag nicht von der Implementierung
abweichen:

@include dbx/modules/myInvoices/fd/invoice-form.fd.php

@cond INTERNAL
```php
<?php

$fields = array();

$addField = function (
    string $name,
    string $type,
    string $tpl,
    string $label,
    string $rules,
    array $extra = array()
) use (&$fields): void {
    $field = array();
    $field['name'] = $name;
    $field['type'] = $type;
    $field['tpl'] = $tpl;
    $field['label'] = $label;
    $field['rules'] = $rules;
    $field['default'] = $extra['default'] ?? '';
    $field['options'] = $extra['options'] ?? '';
    $fields[] = $field;
};

$addField(
    'invoice_no',
    'varchar',
    'text-label',
    'Rechnungsnummer',
    'parameter|min=2|max=40'
);
$addField(
    'invoice_date',
    'date',
    'date-label',
    'Rechnungsdatum',
    'date'
);
$addField('customer', 'varchar', 'text-label', 'Kunde', '*|min=2|max=180');
$addField(
    'status',
    'varchar',
    'select-single-label',
    'Status',
    'parameter|max=24',
    array(
        'default' => 'draft',
        'options' => 'draft=Entwurf&open=Offen&paid=Bezahlt',
    )
);

?>
```
@endcond

Die FD wählt und ordnet Formularfelder. Sie dupliziert weder DD-Rechte noch
automatische Systemfelder. `total_gross` ist ebenfalls kein Eingabefeld des
Kopfformulars, weil die Rechnungssumme aus den Positionen entsteht.

## 5. Selection-FD des Rechnungsreports

Datei `fd/rpt-invoice-selection.fd.php`:

@include dbx/modules/myInvoices/fd/rpt-invoice-selection.fd.php

@cond INTERNAL
```php
<?php

$fields = array();

$field = array();
$field['name'] = 'dbx_rrows';
$field['type'] = 'int';
$field['tpl'] = 'select-single-label';
$field['default'] = '20';
$field['label'] = 'Anz. Seite';
$field['rules'] = 'int';
$field['options'] = '10=10&20=20&30=30&50=50';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rsort';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'invoice_date';
$field['label'] = 'Sortierung';
$field['rules'] = 'parameter';
$field['options'] =
    'invoice_no=Rechnungsnummer&invoice_date=Datum'
    . '&customer=Kunde&status=Status&total_gross=Summe';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rdesc';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'DESC';
$field['label'] = 'Richtung';
$field['rules'] = 'parameter';
$field['options'] = 'ASC=Aufsteigend&DESC=Absteigend';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rstatus';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'all';
$field['label'] = 'Status';
$field['rules'] = 'parameter|max=24';
$field['options'] = 'all=Alle&draft=Entwurf&open=Offen&paid=Bezahlt';
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
@endcond

Die Optionsliste ist Komfort, keine Sicherheitsgrenze. Der Service prüft
Sortierfeld und Richtung zusätzlich gegen feste Allowlists.

## 6. Kleiner Router

Datei `myInvoices.class.php`:

```php
<?php
namespace dbx\myInvoices;

class myInvoices
{
    public function run()
    {
        $run = (string)dbx()->get_modul_var(
            'dbx_run1',
            'report',
            'parameter|max=32'
        );
        $service = dbx()->get_include_obj(
            'myInvoicesService',
            'myInvoices'
        );

        switch ($run) {
            case 'positions':
                return $service->positions();

            case 'form':
            case 'edit':
                return $service->form();

            case 'delete':
                return $service->delete();

            case 'report':
            case 'list':
            default:
                return $service->report();
        }
    }
}

?>
```

Der Router validiert nur die Route und delegiert. Besonders wichtig: Auch der
eingebettete `[modul=myInvoices]`-Aufruf geht durch dieselbe `run()`-Methode.
Es existiert kein Sonderzugriff auf die Positions-DD.

## 7. Service: zwei Reports, Callbacks und Transaktion

Datei `include/myInvoicesService.class.php`:

Der vollständige Quelltext wird direkt eingebunden. Er ist damit die
verbindliche Referenz für aktive FD-Meldungen, Spaltenlabels, Confirmtexte,
Callback-Defaults, Summen und Transaktionen:

@include dbx/modules/myInvoices/include/myInvoicesService.class.php

@cond INTERNAL
```php
<?php
namespace dbx\myInvoices;

class myInvoicesService
{
    private const INVOICE_DD = 'myInvoices|invoice';
    private const ITEM_DD = 'myInvoices|invoiceItem';
    private const FORM_FD = 'myInvoices|invoice-form';
    private const REPORT_FD = 'myInvoices|rpt-invoice-selection';

    private int $invoicePageTotalCents = 0;
    private int $itemTotalCents = 0;

    private function euro(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' EUR';
    }

    private function url(string $run, array $params = array()): string
    {
        $url = '?dbx_modul=myInvoices&dbx_run1=' . rawurlencode($run);
        foreach ($params as $key => $value) {
            $url .= '&' . rawurlencode((string)$key)
                . '=' . rawurlencode((string)$value);
        }
        return $url;
    }

    public function form(): string
    {
        $ridValue = dbx()->get_modul_var('rid', 'new', 'parameter|max=24');
        $rid = $ridValue === 'new' ? 0 : (int)$ridValue;
        $isNew = $rid <= 0;

        $data = $isNew
            ? array('status' => 'draft')
            : dbx()->get_system_obj('dbxDB')->select1(
                self::INVOICE_DD,
                array('id' => $rid)
            );

        if (!$isNew && (int)($data['id'] ?? 0) <= 0) {
            return dbx()->get_system_obj('dbxTPL')->get_tpl(
                'dbx|alert-warning',
                array('msg' => 'Rechnung nicht gefunden.')
            );
        }

        $form = dbx()->get_system_obj('dbxForm');
        $form->init('invoice-form', 'myInvoices|invoice-form');
        $form->_dd = self::INVOICE_DD;
        $form->_fd = self::FORM_FD;
        $form->_data = is_array($data) ? $data : array();
        $form->_rid = $rid;
        $form->_action = $this->url('form', array(
            'rid' => $isNew ? 'new' : $rid,
        ));
        $form->_msg_info =
            'Rechnungskopf über die FD prüfen und speichern.';
        $form->add_rep(
            'form_title',
            $isNew ? 'Neue Rechnung' : 'Rechnung bearbeiten'
        );
        $form->add_rep('list_url', $this->url('report'));
        $form->add_flds();

        if ($form->submit()) {
            if (!$form->errors()) {
                $ok = $form->save_post(
                    self::INVOICE_DD,
                    $isNew ? 'new' : $rid
                );
                $savedRid = (int)$form->_rid;
                if ($ok && $isNew && $savedRid > 0) {
                    $form->_action = $this->url(
                        'form',
                        array('rid' => $savedRid)
                    );
                    $form->add_rep(
                        'form_title',
                        'Rechnung bearbeiten'
                    );
                }
                $form->_msg_success = $ok
                    ? 'Rechnung gespeichert.'
                    : '';
                $form->_msg_error = $ok
                    ? ''
                    : 'Rechnung konnte nicht gespeichert werden.';
            } else {
                $form->_msg_error = 'Bitte Eingaben prüfen.';
            }
        }

        return $form->run();
    }

    public function invoice_report_next_record($report, $record)
    {
        if (!is_array($record)) {
            return $record;
        }

        $id = (int)($record['id'] ?? 0);
        $totalCents = (int)round(
            (float)($record['total_gross'] ?? 0) * 100
        );

        $this->invoicePageTotalCents += $totalCents;
        $report->add_rep(
            'invoice_report_total',
            $this->euro($this->invoicePageTotalCents)
        );
        $record['action'] = dbx()->get_system_obj('dbxTPL')->get_tpl(
            'myInvoices|invoice-row-action',
            array(
                'edit_url' => $this->url('form', array('rid' => $id)),
                'delete_url' => dbx()->action_url(
                    $this->url('delete', array('rid' => $id))
                ),
                'delete_title' => 'Rechnung löschen',
                'delete_question' =>
                    'Rechnung #' . $id . ' samt Positionen löschen?',
                'delete_hint' => 'Der Vorgang wird vollständig protokolliert.',
            )
        );
        $record['total_gross'] = $this->euro($totalCents);
        $record['positions_call'] =
            '[modul=myInvoices]dbx_run1=positions&invoice_id='
            . $id
            . '[/modul]';

        return $record;
    }

    public function report(
        string $success = '',
        string $error = ''
    ): string {
        $db = dbx()->get_system_obj('dbxDB');
        $report = dbx()->get_system_obj('dbxReport');
        $report->init(
            'invoice-report',
            'myInvoices|invoice-report'
        );
        $report->_dd = self::INVOICE_DD;
        $report->_mode = 'table';
        $report->_action = $this->url('report');
        $report->_pages = true;
        $report->_but_pagination = 7;
        $report->_create_row_select = false;
        $report->_create_row_edit = false;
        $report->_create_row_delete = false;
        $report->_msg_success = $success;
        $report->_msg_error = $error;
        $report->add_rep('install_url', $this->url('install'));
        $report->create_selection_fields(self::REPORT_FD);

        $this->invoicePageTotalCents = 0;

        if ($report->submit() && $report->errors()) {
            $report->_msg_error = 'Bitte Filtereingaben prüfen.';
        }

        $search = trim((string)$report->get_fld_val(
            'dbx_rwhere',
            '',
            'sqlsearch|max=64'
        ));
        $status = (string)$report->get_fld_val(
            'dbx_rstatus',
            'all',
            'parameter|max=24'
        );
        $pageSize = max(10, min(50, (int)$report->get_fld_val(
            'dbx_rrows',
            20,
            'int'
        )));
        $position = max(0, (int)$report->get_fld_val(
            'dbx_rpos',
            0,
            'int'
        ));
        $sort = (string)$report->get_fld_val(
            'dbx_rsort',
            'invoice_date',
            'parameter'
        );
        $direction = strtoupper((string)$report->get_fld_val(
            'dbx_rdesc',
            'DESC',
            'parameter'
        ));

        $allowedSort = array(
            'invoice_no',
            'invoice_date',
            'customer',
            'status',
            'total_gross',
        );
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'invoice_date';
        }
        if (!in_array($direction, array('ASC', 'DESC'), true)) {
            $direction = 'DESC';
        }

        $where = array();
        if (in_array($status, array('draft', 'open', 'paid'), true)) {
            $where['status'] = $status;
        }
        if ($search !== '') {
            $where['search'] = array(
                'value' => $search,
                'like' => array('invoice_no', 'customer'),
                'mode' => 'contains',
            );
        }

        $columns = array(
            'id',
            'invoice_no',
            'invoice_date',
            'customer',
            'status',
            'total_gross',
        );
        $rows = $db->select(
            self::INVOICE_DD,
            $where,
            $columns,
            $sort,
            $direction,
            '',
            $pageSize,
            $position
        );

        $report->_rflds = array(
            'invoice_no' => 'Rechnung',
            'invoice_date' => 'Datum',
            'customer' => 'Kunde',
            'status' => 'Status',
            'action' => 'Aktion',
            'total_gross' => 'Summe',
        );
        $report->_rpt_format = array(
            'invoice_date' => 'php-date-usr',
            'action' => 'html',
        );
        $report->_rrows = $pageSize;
        $report->_rpos = $position;
        $report->_count_all = $db->count(self::INVOICE_DD);
        $report->_rcount = $db->count(self::INVOICE_DD, $where);
        $report->_rdata = is_array($rows) ? $rows : array();

        return $report->run();
    }

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
            $this->euro($this->itemTotalCents)
        );
        $record['quantity'] = number_format($quantity, 2, ',', '.');
        $record['unit_price'] = $this->euro(
            (int)round($unitPrice * 100)
        );
        $record['sum'] = $this->euro($sumCents);

        return $record;
    }

    public function positions(): string
    {
        $invoiceId = (int)dbx()->get_modul_var(
            'invoice_id',
            0,
            'int'
        );
        if ($invoiceId <= 0) {
            return '';
        }

        $db = dbx()->get_system_obj('dbxDB');
        $invoice = $db->select1(
            self::INVOICE_DD,
            array('id' => $invoiceId),
            array('id', 'invoice_no')
        );
        if ((int)($invoice['id'] ?? 0) <= 0) {
            return '';
        }

        $rows = $db->select(
            self::ITEM_DD,
            array('invoice_id' => $invoiceId),
            array(
                'position_no',
                'article_no',
                'description',
                'quantity',
                'unit_price',
            ),
            'position_no',
            'ASC'
        );

        $report = dbx()->get_system_obj('dbxReport');
        $report->init(
            'invoice-items-report',
            'myInvoices|invoice-items-report'
        );
        $report->_dd = self::ITEM_DD;
        $report->_mode = 'table';
        $report->_pages = false;
        $report->_create_row_select = false;
        $report->_create_row_edit = false;
        $report->_create_row_delete = false;
        $report->add_rep('invoice_no', (string)$invoice['invoice_no']);

        $this->itemTotalCents = 0;

        $report->_rflds = array(
            'position_no' => 'Pos.',
            'article_no' => 'Artikelnummer',
            'description' => 'Artikel',
            'quantity' => 'Menge',
            'unit_price' => 'Einzelpreis',
            'sum' => 'Summe',
        );
        $report->_rrows = is_array($rows) ? count($rows) : 0;
        $report->_rpos = 0;
        $report->_count_all = $report->_rrows;
        $report->_rcount = $report->_rrows;
        $report->_rdata = is_array($rows) ? $rows : array();

        return $report->run();
    }

    public function delete(): string
    {
        $rid = (int)dbx()->get_modul_var('rid', 0, 'int');

        if ($rid <= 0) {
            return $this->report(
                '',
                'Die Rechnung konnte nicht bestimmt werden.'
            );
        }

        $db = dbx()->get_system_obj('dbxDB');
        if ($db->begin(self::INVOICE_DD) !== 1) {
            return $this->report('', 'Transaktion konnte nicht starten.');
        }

        $itemsDeleted = $db->delete(
            self::ITEM_DD,
            array('invoice_id' => $rid)
        );
        $invoiceDeleted = in_array($itemsDeleted, array(0, 1), true)
            ? $db->delete(self::INVOICE_DD, array('id' => $rid))
            : -2;

        if ($invoiceDeleted !== 1
            || $db->commit(self::INVOICE_DD) !== 1
        ) {
            $db->rollback(self::INVOICE_DD);
            return $this->report(
                '',
                'Rechnung konnte nicht vollständig gelöscht werden.'
            );
        }

        return $this->report('Rechnung und Positionen wurden gelöscht.');
    }
}

?>
```
@endcond

### Was die Servicefunktionen bewirken

| Funktion | Aufgabe und Wirkung |
| --- | --- |
| `euro()` | Formatiert die im Modul bewusst als Integer-Cent geführten Summen einheitlich für die Ausgabe. |
| `url()` | Baut eine Modulroute und kodiert ausschließlich Querybestandteile. Sie ist Fachlogik für konsistente Routen, kein Alias auf `dbx()`. |
| `form()` | Liest über die Kopf-DD, lässt `dbxForm` FD, Validierung und Submit ausführen und speichert über `save_post()`. |
| `invoice_report_next_record()` | Ist der automatische `{fid}_next_record`-Callback: formatiert den Snapshot, signiert die Delete-Route, sammelt die Seitensumme per spätem `add_rep()` und setzt den `[modul=...]`-Aufruf für Stufe zwei. |
| `report()` | Führt Selection-FD, Allowlist, Count, paginiertes Select und den äußeren `table`-Report zusammen. |
| `invoice_items_report_next_record()` | Ist der automatische Positions-Callback: berechnet `quantity * unit_price`, setzt die virtuelle Spalte `sum` und aktualisiert die Endsumme per `add_rep()`. |
| `positions()` | Prüft die geschützte `invoice_id`, liest Kopf und Positionen über DDs und rendert eine eigenständige, nicht paginierte Reportinstanz. |
| `delete()` | Wird erst nach zentraler Policy-Prüfung aufgerufen, validiert die RID und löscht Positionen plus Kopf unter DD-Rechten atomar über `begin()`, `commit()` und `rollback()`. |

Die Callbackmethoden sind echte Aufbereitungslogik. Sie sind ausdrücklich
keine Modulmethoden, die nur `dbx()`-Funktionen weiterreichen.

Nach einem erfolgreichen Insert übernimmt `form()` die von `dbxForm`
gesetzte `_rid` in Action und Titel. Ein weiterer Submit aktualisiert deshalb
denselben Datensatz, statt unbeabsichtigt einen zweiten Insert zu versuchen.

### Callback-Lebenszyklus

1. `dbxForm::init()` übernimmt den direkten Modul-/Service-Aufrufer als
   Callback-Owner. `set_callback_owner($this)` ist im Normalfall unnötig.
2. Die Formular-ID wird zur Callback-ID: `invoice-report` sucht automatisch
   `invoice_report_next_record()`, `invoice-items-report` entsprechend
   `invoice_items_report_next_record()`.
3. Ein expliziter Callback-Setter ist nur nötig, wenn bewusst von dieser
   Namenskonvention abgewichen wird.
4. `add_rep()` darf auch während des Record-Laufs Werte setzen. Die von
   `dbxForm` bereitgestellte `replaces()`-Pipeline wendet sie im Footer mit
   dem zuletzt akkumulierten Wert an.
5. `{rpt:col_count}` umfasst alle Reportspalten. `{rpt:colspan}` umfasst
   automatisch alle Spalten außer der letzten Wertespalte.

## 8. Formtemplate

Datei `tpl/htm/invoice-form.htm`:

```html
<div id="dbxForm_{i}" class="dbx-panel dbxForm_wrapper dbx-ajax-root">
 <div class="dbx-panel-head d-flex justify-content-between align-items-center">
  <h2 class="h5 mb-0">{form_title}</h2>
  <a class="btn btn-outline-secondary btn-sm" href="{list_url}">
   Zur Liste
  </a>
 </div>
 <form action="{action}" method="post" class="dbxAjax"
       data-ajax-target="dbxForm_{i}" data-ajax-replace="target">
  <div class="dbx-panel-body">
   {obj:form_msg}
   <div class="row g-3">
    [dbx:form]
   </div>
  </div>
  <div class="dbx-panel-foot">
   <button class="btn btn-primary" type="submit">
    <i class="bi bi-save"></i> Speichern
   </button>
  </div>
  [dbx:js]
 </form>
</div>
```

Ohne JavaScript funktioniert derselbe Submit normal. Mit `dbxAjax` ersetzt
die Antwort nur `dbxForm_{i}` und initialisiert die enthaltenen Features neu.

## 9. Äußerer Rechnungsreport

Datei `tpl/htm/invoice-report.htm`:

```html
<div class="dbx-panel dbxReport dbx-ajax-root"
     id="dbx_target_{i}"
     data-dbx="lib=report|form=0">
 <div class="dbx-panel-head d-flex justify-content-between align-items-center">
  <h2 class="h5 mb-0">Rechnungen</h2>
  <a class="btn btn-primary btn-sm"
     href="?dbx_modul=myInvoices&amp;dbx_run1=form&amp;rid=new">
   <i class="bi bi-plus-lg"></i> Neu
  </a>
 </div>
 <form action="{action}" method="post" id="dbx_form_{i}" class="dbxAjax">
  [dbx:form]
  [tpl=dbx|report-form-select]
  [dbx:pagination]
  <div class="table-responsive dbx-report-table-scroll">
   <table class="table table-striped table-bordered table-hover align-middle">
    <thead>
     <tr class="{tr-class}">[rpt:row]</tr>
    </thead>
    <tbody>
     <hr class="dbx_split">
     <tr class="{tr-class}">[rpt:row]</tr>
     <tr class="dbx-invoice-items-row">
      <td colspan="{rpt:col_count}" class="p-3 bg-body-tertiary">
       {positions_call}
      </td>
     </tr>
     <hr class="dbx_split">
    </tbody>
    <tfoot>
     <tr>
      <th colspan="{rpt:colspan}" class="text-end">
       Endsumme der angezeigten Rechnungen
      </th>
      <th class="text-end">{invoice_report_total}</th>
     </tr>
    </tfoot>
   </table>
  </div>
  [dbx:js]
 </form>
</div>
```

`[rpt:row]` erzeugt im `table`-Modus Header und Datenzellen aus `_rflds`.
Die zweite `<tr>` gehört zur gleichen Rechnung. Ihr
`colspan="{rpt:col_count}"` deckt auch dann alle Spalten ab, wenn sich der
Report später ändert. `{positions_call}` wurde vorher vom Record-Callback
gesetzt und wird nach dem Reportlauf vom `dbxInterpreter` ausgeführt.

Der Footer verwendet `{rpt:colspan}`. dbxReport berechnet damit automatisch
„alle Spalten außer der letzten Wertespalte“. Eine eigene Footer-Methode ist
nicht erforderlich; der Record-Callback aktualisiert die Endsumme spät über
`add_rep()`.

## 10. Eingebetteter Positionsreport

Datei `tpl/htm/invoice-items-report.htm`:

```html
<div class="dbxReport" id="dbx_invoice_items_{i}">
 <h3 class="h6">Positionen zu Rechnung {invoice_no}</h3>
 <div class="table-responsive">
  <table class="table table-sm table-bordered mb-0">
   <thead>
    <tr class="{tr-class}">[rpt:row]</tr>
   </thead>
   <tbody>
    <hr class="dbx_split">
    <tr class="{tr-class}">[rpt:row]</tr>
    <hr class="dbx_split">
   </tbody>
   <tfoot>
    <tr>
     <th colspan="{rpt:colspan}" class="text-end">
      Endsumme der Positionen
     </th>
     <th class="text-end">{report_total}</th>
    </tr>
   </tfoot>
  </table>
 </div>
</div>
```

Der Positionsreport enthält bewusst kein eigenes `<form>`: Er liegt innerhalb
des Formulars des äußeren Reports, und verschachtelte HTML-Formulare wären
ungültig. Er besitzt trotzdem eine eindeutige `{i}`-ID und eine vollständige
`dbxReport`-Pipeline.

Weil `positions()` keine Pagination aktiviert, sieht der automatische
`invoice_items_report_next_record()`-Callback jede Position der Rechnung.
Der per `add_rep()` gesetzte Footerwert ist damit die vollständige
Rechnungssumme der geladenen Positionen, nicht nur eine Seitensumme.

## 11. Zeilenaktionen mit Confirm und Ajax

Datei `tpl/htm/invoice-row-action.htm`:

```html
<div class="btn-group btn-group-sm" role="group">
 <a class="btn btn-outline-primary" href="{edit_url}">
  <i class="bi bi-pencil"></i>
  <span class="visually-hidden">Bearbeiten</span>
 </a>
 <a class="btn btn-outline-danger dbxAjax dbxConfirm"
    href="{delete_url}"
    data-confirm-title="{delete_title}"
    data-confirm="{delete_question}"
    data-confirm-hint="{delete_hint}"
    data-confirm-buttons="yesno">
  <i class="bi bi-trash"></i>
  <span class="visually-hidden">Löschen</span>
 </a>
</div>
```

Der Ablauf bleibt systemweit einheitlich:

1. `confirm.js` fragt nur bei der markierten Mutation nach.
2. Bei „Nein“ entsteht kein Request.
3. Bei „Ja“ übernimmt `ajax.js` den vorhandenen Link.
4. `dbxWebApp` prüft vor dem Modulstart Route, RID-Bindung und Token.
5. `dbxDB` startet eine Transaktion auf dem gemeinsamen DD-Server.
6. Positionen und Rechnung werden über ihre DDs gelöscht und getraced.
7. Bei einem Fehler rollt `rollback()` beide Schritte zurück.
8. Der neu gerenderte Report ersetzt denselben Ajax-Root.

## 12. Was die zweistufige Inclusion technisch bewirkt

Für jede sichtbare Rechnung erzeugt `invoice_report_next_record()` genau einen
Marker:

```html
[modul=myInvoices]dbx_run1=positions&invoice_id=17[/modul]
```

Der Interpreter:

1. prüft den Modulzugriff;
2. setzt `dbx_run1` und `invoice_id` als geschützte Modulvariablen;
3. ruft erneut den kleinen Router auf;
4. lässt `positions()` Kopf und Positionen über `dbxDB` und DD prüfen;
5. ersetzt den Marker durch den fertigen Positionsreport.

Das Muster eignet sich für eine begrenzte äußere Seitengröße. Bei 20
Rechnungen entstehen 20 gezielte Positionsabfragen mit verschiedenen
`invoice_id`-Werten; das sind keine identischen Resultsets, die ein einfacher
Ergebniscache zusammenlegen könnte. `dbxDB` kann weiterhin DD-Metadaten,
Verbindungen und dafür vorgesehene DD-Caches zentral nutzen.

Bei sehr großen Seiten SOLL die Unterliste erst beim Öffnen eingebettet oder
in einem fachlichen Batch über `dbxDB` vorgeladen werden. Direkte PDO-Abfragen
oder ein zweiter Browser-API-Weg sind keine Optimierung.

## 13. DD-Sync, Fixtures und Installation

Beide DDs werden über den vorhandenen Admin-DD-Sync oder einen explizit
geschützten Installationspfad synchronisiert:

```php
$dd = dbx()->get_system_obj('dbxDD');

foreach (array('invoice', 'invoiceItem') as $name) {
    $dd->sync_dd_to_db('myInvoices', $name, 'reset');

    do {
        $state = $dd->sync_dd_to_db(
            'myInvoices',
            $name,
            'apply'
        );
    } while (($state['status'] ?? '') === 'running');

    if (($state['status'] ?? '') !== 'finished') {
        throw new \RuntimeException(
            (string)($state['message'] ?? 'DD-Sync fehlgeschlagen')
        );
    }
}
```

Schemaänderungen laufen nicht bei jedem normalen Request. Der ausführbare
Service zeigt unter `dbx_run1=install` zunächst nur ein `dbxForm`. DD-Sync und
Fixtures starten erst nach dessen gültigem POST. Deshalb braucht die
Installationsroute keinen zusätzlichen `dbx_token`: `dbxForm` schützt bereits
den Submit, und die Route verlangt zusätzlich Adminrecht.

`include/myInvoicesFixtures.class.php` berechnet die Demo-Snapshots aus den
zugehörigen Positionen und schreibt sämtliche Fachdaten über `dbxDB`. Sie
übergibt keine Auditfelder. Alle Demo-Rechnungen besitzen eine eindeutige
`DBX-DEMO-*`-Nummer; ein wiederholter Lauf überspringt vorhandene Fixtures und
überschreibt keine Benutzerdaten.

Für Automation steht derselbe Installer ohne zweiten Datenpfad per CLI bereit:

```text
php dbx/modules/myInvoices/tools/install_demo.php
php dbx/modules/myInvoices/tools/install_demo.php --schema-only
```

## 14. Direkter Aufruf und Inclusion

Direkt:

```text
?dbx_modul=myInvoices&dbx_run1=report
?dbx_modul=myInvoices&dbx_run1=form&rid=new
?dbx_modul=myInvoices&dbx_run1=form&rid=17
?dbx_modul=myInvoices&dbx_run1=positions&invoice_id=17
?dbx_modul=myInvoices&dbx_run1=install
```

Eingebettet:

```html
[modul=myInvoices]dbx_run1=report[/modul]
```

Der Positionsaufruf wird normalerweise vom Rechnungs-Record-Callback erzeugt,
kann für eine reine Detailansicht aber auch bewusst eingesetzt werden:

```html
[modul=myInvoices]dbx_run1=positions&invoice_id=17[/modul]
```

Templates verwenden `{i}` für eindeutige Instanzen. Modulparameter werden mit
`get_modul_var()` und passenden Regeln gelesen.

## 15. Verbindliche Arbeitsanweisung

Für jedes neue oder wesentlich erweiterte datenbasierte Modul gilt:

1. Fachzweck, Benutzergruppen, lesende und mutierende Routen aufschreiben.
2. Beziehungen und Transaktionsgrenzen der DDs festlegen.
3. DDs im direkt lesbaren dbxapp-Exportformat anlegen: `TABLE`, `FIELDS` und
   `INDEXES` mit expliziten `$table[...]`, `$field[...]`, `$fields[]=$field`,
   `$index[...]` und `$indexes[]=$index`; keine `$addField`-Closure.
4. Automatische dbxDB-Systemfelder nicht im Modul nachbauen.
5. Pro Formularsicht und Reportfilter eine passende FD anlegen.
   Jede FD besitzt strukturgleiche deutsche, englische und spanische
   `$messages`; Formulare und Reports beziehen sichtbare Fachtexte mit
   `load_fd_messages()`, `get_fd_message()` und `format_fd_message()` daraus.
6. Router auf validierte Routenauswahl und Delegation begrenzen.
7. Daten ausschließlich über `dbxDB` und DD lesen oder schreiben.
8. Standard-CRUD mit `dbxForm::save_post()` umsetzen.
9. Listen, Unterlisten, Pagination und Selection über `dbxReport` umsetzen.
10. Callback-Defaults `{fid}_{event}` verwenden; nur bei bewusster Abweichung
    einen Callback explizit registrieren.
11. Berechnete Ausgabefelder im `{fid}_next_record`-Callback setzen und
    akkumulierte Footerwerte dort spät per `add_rep()` aktualisieren.
12. `{rpt:col_count}` für alle Spalten oder `{rpt:colspan}` für alle Spalten
    außer der letzten Wertespalte verwenden.
13. `[modul=...]` für serverseitige Modul-Inclusion verwenden; keinen
    internen HTTP-Request bauen.
14. Requestsortierung gegen feste Feld- und Richtungslisten prüfen.
15. HTML in `dbxTPL`-Templates legen. Werte nicht pauschal escapen; nur eine
    tatsächlich rohe, außerhalb der dbx-Ausgabepipeline liegende Fremdeingabe
    wird am konkreten HTML-Kontext behandelt.
16. Ajax und Confirm deklarativ über vorhandene Klassen und Attribute nutzen.
17. Schreibende GETs mit dem Action-Token schützen; lesende GETs bleiben
    tokenlos.
18. Mehrtabellenmutationen über `dbxDB::begin()`, `commit()` und `rollback()`
    atomar halten.
19. Direkten Request, Inclusion, Ajax, Rechte, Summen und Mehrfachinstanzen
    testen.
20. Doxygen, Modul-README und reale Codeverweise gemeinsam aktualisieren.
21. In Deutsch, Englisch und Spanisch jeweils Formularlabels, Reportspalten,
    leere Zustände, Validierungs-, Erfolgs-, Fehler- und Confirmmeldungen
    prüfen. Gespeicherte Werte einer einsprachigen Tabelle gelten dabei als
    Daten und nicht als Oberflächenübersetzung.

## 16. Mindesttestmatrix

| Prüfung | Erwartung |
| --- | --- |
| Rechnungsreport direkt | korrekte Gesamt- und Trefferzahl |
| Rechnungsreport eingebettet | getrennte Modul- und DOM-Zustände |
| Positionsmarker | richtige `invoice_id`, keine fremden Requestwerte |
| Positions-DD ohne Recht | keine Daten trotz bekanntem Marker |
| Summenspalte | `quantity * unit_price`, kaufmännisch auf Cent gerundet |
| Positionsfooter | Summe aller Positionen, dynamischer `colspan` |
| Rechnungsfooter | Summe der sichtbaren Rechnungsseite |
| gespeicherter Snapshot | `total_gross` entspricht Positionsendsumme |
| Filter, Sortierung, Pagination | gleiche WHERE für Count und Select |
| Formular ohne JavaScript | normaler Submit funktioniert |
| Formular mit Ajax | nur eigenes `dbxForm_{i}` wird ersetzt |
| Insert und Update | DD-Rechte, Feldprüfung, automatische Systemfelder, Trace |
| Delete: „Nein“ | kein Request, keine Mutation |
| Delete: „Ja“ | Kopf und Positionen werden gemeinsam gelöscht |
| Fehler beim zweiten Delete | vollständiger Rollback |
| Delete ohne/falschen Token | keine Mutation |
| zwei Reportinstanzen | eindeutige `{i}`-IDs und getrennte Callback-Summen |
| SQLite/MySQL-Wechsel | kein Fachcode muss geändert werden |
| Doxygen-Build | keine fehlende Seite oder Referenz |

### Ausführbare Nachweise des Referenzmoduls

Die Mindestmatrix wird durch zwei versionierte Tests und einen dokumentierten
Browserablauf konkretisiert:

```text
php dbx/modules/myInvoices/tests/myInvoices_contract_test.php
php dbx/modules/myInvoices/tests/myInvoices_integration_test.php
```

Der Architekturtest verbietet direkten PDO-/SQL-Zugriff, triviale
`dbx()`-Wrapper, manuell gesetzte Auditfelder und abstrahierte DD-Feld-Closures.
Er verlangt die expliziten `TABLE`-, `FIELDS`- und `INDEXES`-Abschnitte. Der
Integrationstest prüft DD-Sync, idempotente Fixtures, automatische
Systemfelder, Snapshot- und Callback-Summen, falschen Action-Token,
erfolgreichen Mehrtabellen-Delete und einen erzwungenen Rollback nach dem
ersten Löschschritt.

Der am 25. Juli 2026 ausgeführte Browsernachweis umfasst:

| Ablauf | Nachweis |
| --- | --- |
| Installations-POST | `dbxForm`-Ajax, zweiter Lauf meldet 3 vorhandene Fixtures |
| direkter Report | 3 Rechnungen und 3 serverseitig eingebettete Positionsreports |
| Summen | `47,30`, `66,00`, `129,50` EUR; Seitenfooter `242,80 EUR` |
| Ajax-Insert | Erfolgsmeldung und anschließend gespeicherte RID |
| Submit ohne JavaScript | normaler HTTP-POST speichert und wechselt auf die RID |
| Confirm „Nein“ | Dialog schließt, Testrechnung bleibt vorhanden |
| falscher Token | Ablehnungsmeldung, Testrechnung bleibt vorhanden |
| Confirm „Ja“ | Kopf und Positionen verschwinden gemeinsam |
| Browserkonsole | keine Fehler oder Warnungen im finalen Reportlauf |

Der reproduzierbare Ablauf und seine stabilen Selektoren stehen in
`dbx/modules/myInvoices/tests/README.md`.

## 17. Häufige Fehlentscheidungen

| Falsch | Richtig |
| --- | --- |
| PDO oder SQL im Service | `dbxDB` mit DD-Referenz |
| `$addField`-Closure in der DD | explizite `$field[...]`- und `$index[...]`-Definitionen im dbxapp-Exportformat |
| private `db()`-/`tpl()`-Aliasfunktionen | `dbx()->get_system_obj(...)` direkt am Einsatzort |
| `create_date`, `create_uid`, `owner` manuell setzen | von `dbxDB` automatisch setzen lassen |
| jeden DD-/Reportwert vorsorglich escapen | vorhandene Ausgabe- und Formatpipeline nutzen |
| Positions-HTML im Callback zusammensetzen | Marker setzen, Struktur in `dbxTPL` |
| Unterliste per internem HTTP/fetch laden | `[modul=...]...[/modul]` |
| eigene Schleife für Tabellen und Pagination | `dbxReport` |
| Summe in JavaScript nachrechnen | `{fid}_next_record` plus spätes `add_rep()` |
| unnötige Owner-/Callback-Setter | dbxForm-Owner- und `{fid}_{event}`-Defaults |
| eigene Footer-Methode nur für `str_replace()` | `add_rep()` und geerbte `replaces()`-Pipeline |
| fester `colspan="6"` | `{rpt:col_count}` bzw. `{rpt:colspan}` |
| alle Positionen nur für den Report doppelt laden | im Record-Callback des vorhandenen Reports summieren |
| Kopf löschen, Positionen stehen lassen | gemeinsame dbxDB-Transaktion |
| `window.confirm()` | `dbxConfirm` und `confirm.js` |
| eigener `fetch()`-Handler für HTML | `dbxAjax` und `ajax.js` |
| Action-Token auf jedem GET | nur mutierende GET-Aktionen tokenisieren |
| ungefiltertes `ORDER BY` aus dem Request | Allowlist im Service |
| Kernelklasse wegen Zeilenzahl teilen | nur nach bewiesener Verantwortungsgrenze |

## Weiterführende Detailkapitel

- @ref dbxapp_module_patterns — Modulvarianten und Erweiterungsmuster
- @ref dbxapp_dbxtpl — Templates, Marker, Slots und Inclusion
- @ref dbxapp_dbxdb_dd_fd — Daten-, DD- und FD-Referenz
- @ref dbxapp_dbxform — vollständige Formularpipeline
- @ref dbxapp_dbxreport — vollständige Reportpipeline
- @ref dbxapp_javascript_libs — Browserlibs und deklarative Attribute
- @ref dbxapp_security_integrity_performance — Sicherheitsnachweise
- @ref dbxapp_ai_rules — verbindliche Regeln für KI-Agenten
