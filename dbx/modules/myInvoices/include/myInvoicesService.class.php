<?php
namespace dbx\myInvoices;

/**
 * Fachservice des ausführbaren Rechnungs-Referenzmoduls.
 *
 * Der Service zeigt den verbindlichen Zusammenschluss von dbxDB, DD, FD,
 * dbxForm, dbxReport, dbxTPL, Reportcallbacks, Modul-Inclusion, Ajax,
 * Confirm, zentrale Action-Policy und Mehrtabellentransaktion.
 */
class myInvoicesService
{
    private const INVOICE_DD = 'myInvoices|invoice';
    private const ITEM_DD = 'myInvoices|invoiceItem';
    private const FORM_FD = 'myInvoices|invoice-form';
    private const REPORT_FD = 'myInvoices|rpt-invoice-selection';

    private int $invoicePageTotalCents = 0;
    private int $itemTotalCents = 0;

    /**
     * Formatiert einen Centbetrag fuer die Rechnungsansicht.
     */
    private function euro(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' EUR';
    }

    /**
     * Baut eine konsistente Modulroute.
     *
     * @param string $run Modulaktion.
     * @param array $params Weitere Modulparameter.
     * @return string Relative dbXapp-URL.
     */
    private function url(string $run, array $params = array()): string
    {
        $url = '?dbx_modul=myInvoices&dbx_run1=' . rawurlencode($run);
        foreach ($params as $key => $value) {
            $url .= '&' . rawurlencode((string)$key)
                . '=' . rawurlencode((string)$value);
        }
        return $url;
    }

    /**
     * Rendert und verarbeitet das Rechnungskopfformular.
     *
     * dbxForm übernimmt CSRF, FD-Felder, Regeln, Meldungen, Ajax- und
     * Normal-Submit. save_post() persistiert ausschließlich über die DD.
     *
     * @return string Formular-HTML.
     */
    public function form(): string
    {
        $ridValue = dbx()->get_modul_var(
            'rid',
            'new',
            'parameter|max=24'
        );
        $rid = $ridValue === 'new' ? 0 : (int)$ridValue;
        $isNew = $rid <= 0;

        $form = dbx()->get_system_obj('dbxForm');
        $form->init('invoice-form', 'myInvoices|invoice-form');
        $form->_dd = self::INVOICE_DD;
        $form->_fd = self::FORM_FD;
        $form->load_fd_messages();

        $data = $isNew
            ? array('status' => 'draft')
            : dbx()->get_system_obj('dbxDB')->select1(
                self::INVOICE_DD,
                array('id' => $rid)
            );

        if (!$isNew && (int)($data['id'] ?? 0) <= 0) {
            return dbx()->get_system_obj('dbxTPL')->get_tpl(
                'dbx|alert-warning',
                array('msg' => $form->get_fd_message('not_found'))
            );
        }

        $form->_data = is_array($data) ? $data : array();
        $form->_rid = $rid;
        $form->_action = $this->url('form', array(
            'rid' => $isNew ? 'new' : $rid,
        ));
        $form->_msg_info = $form->get_fd_message('form_info');
        $form->add_rep(
            'form_title',
            $form->get_fd_message(
                $isNew ? 'form_title_new' : 'form_title_edit'
            )
        );
        $form->add_rep(
            'bar_title',
            $form->get_fd_message('help_title')
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
                        $form->get_fd_message('form_title_edit')
                    );
                }
            } else {
                $form->_msg_error = $form->get_fd_message(
                    'validation_error'
                );
            }
        }

        return $form->run();
    }

    /**
     * Bereitet eine Rechnungszeile unmittelbar vor dem Rendern auf.
     *
     * Wirkung:
     * - sammelt die Seitensumme in Cent,
     * - formatiert den Snapshot,
     * - rendert die Aktionen über dbxTPL,
     * - setzt den serverseitigen Modulaufruf für Stufe zwei.
     *
     * @param object $report Aktuelle dbxReport-Instanz.
     * @param mixed $record Aktueller Datensatz.
     * @return mixed Aufbereiteter Datensatz.
     */
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
                'delete_title' => $report->get_fd_message(
                    'delete_title'
                ),
                'delete_question' => $report->format_fd_message(
                    'delete_question',
                    array('id' => $id)
                ),
                'delete_hint' => $report->get_fd_message(
                    'delete_hint'
                ),
            )
        );
        $status = (string)($record['status'] ?? '');
        if ($status !== '') {
            $record['status'] = $report->get_fd_message(
                'status_' . $status,
                $status
            );
        }
        $record['total_gross'] = $this->euro($totalCents);
        $record['positions_call'] =
            '[modul=myInvoices]dbx_run1=positions&invoice_id='
            . $id
            . '[/modul]';

        return $record;
    }

    /**
     * Rendert den paginierten Rechnungsreport.
     *
     * Selection-FD und Allowlist kontrollieren Filter und Sortierung. Count
     * und Select erhalten dasselbe Where. Jede sichtbare Rechnung bindet den
     * Positionsreport serverseitig über `[modul=...]` ein.
     *
     * @param string $success Optionale Erfolgsmeldung.
     * @param string $error Optionale Fehlermeldung.
     * @return string Report-HTML.
     */
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
        $report->add_rep('install_url', $this->url('install'));
        $report->create_selection_fields(self::REPORT_FD);
        $report->add_rep(
            'bar_title',
            $report->get_fd_message('report_title')
        );
        $report->_msg_success = $success === ''
            ? ''
            : $report->get_fd_message($success, $success);
        $report->_msg_error = $error === ''
            ? ''
            : $report->get_fd_message($error, $error);

        $this->invoicePageTotalCents = 0;

        if ($report->submit() && $report->errors()) {
            $report->_msg_error = $report->get_fd_message('filter_error');
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

        if (!is_array($rows) || $db->get_error_status() !== '') {
            return dbx()->get_system_obj('dbxTPL')->get_tpl(
                'myInvoices|install-required',
                array('install_url' => $this->url('install'))
            );
        }

        $report->_rflds = array(
            'invoice_no' => $report->get_fd_message('column_invoice_no'),
            'invoice_date' => $report->get_fd_message(
                'column_invoice_date'
            ),
            'customer' => $report->get_fd_message('column_customer'),
            'status' => $report->get_fd_message('column_status'),
            'action' => $report->get_fd_message('column_action'),
            'total_gross' => $report->get_fd_message('column_total'),
        );
        $report->_rpt_format = array(
            'invoice_date' => 'php-date-usr',
            'action' => 'html',
        );
        $report->_rrows = $pageSize;
        $report->_rpos = $position;
        $report->_count_all = $db->count(self::INVOICE_DD);
        $report->_rcount = $db->count(self::INVOICE_DD, $where);
        $report->_rdata = $rows;

        return $report->run();
    }

    /**
     * Berechnet und formatiert die virtuelle Summenspalte einer Position.
     *
     * Die Akkumulation in Cent vermeidet Rundungsdrift im Footer.
     *
     * @param object $report Aktuelle dbxReport-Instanz.
     * @param mixed $record Aktuelle Position.
     * @return mixed Position mit virtueller Spalte `sum`.
     */
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

    /**
     * Rendert die zweite Listenstufe einer konkreten Rechnung.
     *
     * Die geschützte Modulvariable `invoice_id` wird validiert; Kopf und
     * Positionen werden anschließend über ihre DDs gelesen. Der eingebettete
     * Report enthält bewusst kein eigenes HTML-Formular.
     *
     * @return string Positionsreport oder leer bei ungültiger Rechnung.
     */
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
        $report->_fd = self::REPORT_FD;
        $report->load_fd_messages();
        $report->_mode = 'table';
        $report->_pages = false;
        $report->_create_row_select = false;
        $report->_create_row_edit = false;
        $report->_create_row_delete = false;
        $report->add_rep(
            'invoice_no',
            (string)$invoice['invoice_no']
        );

        $this->itemTotalCents = 0;

        $report->_rflds = array(
            'position_no' => $report->get_fd_message(
                'column_position_no'
            ),
            'article_no' => $report->get_fd_message(
                'column_article_no'
            ),
            'description' => $report->get_fd_message('column_article'),
            'quantity' => $report->get_fd_message('column_quantity'),
            'unit_price' => $report->get_fd_message(
                'column_unit_price'
            ),
            'sum' => $report->get_fd_message('column_total'),
        );
        $report->_rrows = is_array($rows) ? count($rows) : 0;
        $report->_rpos = 0;
        $report->_count_all = $report->_rrows;
        $report->_rcount = $report->_rrows;
        $report->_rdata = is_array($rows) ? $rows : array();

        return $report->run();
    }

    /**
     * Löscht Kopf und Positionen atomar.
     *
     * dbxWebApp hat `delete` zusammen mit `rid` automatisch erkannt und den
     * Action-Token bereits vor dem Modulstart geprüft. Der Service validiert
     * nur noch die fachliche RID; dbxDB erzwingt weiterhin die DD-Rechte.
     *
     * @return string Neu gerenderter Report mit Statusmeldung.
     */
    public function delete(): string
    {
        $rid = (int)dbx()->get_modul_var('rid', 0, 'int');

        if ($rid <= 0) {
            return $this->report(
                '',
                'delete_invalid'
            );
        }

        $db = dbx()->get_system_obj('dbxDB');
        if ($db->begin(self::INVOICE_DD) !== 1) {
            return $this->report(
                '',
                'transaction_error'
            );
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
                'delete_error'
            );
        }

        return $this->report('delete_success');
    }

    /**
     * Rendert den dbxForm-geschützten Installations- und Fixture-POST.
     *
     * Ein GET zeigt nur das Formular. Schema und Demo-Daten werden erst nach
     * gültigem dbxForm-Submit verändert.
     *
     * @return string Installationsformular.
     */
    public function installation(): string
    {
        $form = dbx()->get_system_obj('dbxForm');
        $form->init(
            'myInvoices-install',
            'myInvoices|invoice-install'
        );
        $form->_fd = 'myInvoices|invoice-install';
        $form->load_fd_messages();
        $form->add_rep(
            'bar_title',
            $form->get_fd_message('bar_title')
        );

        if (!dbx()->can('admin')) {
            return dbx()->get_system_obj('dbxTPL')->get_tpl(
                'dbx|alert-warning',
                array('msg' => $form->get_fd_message('admin_required'))
            );
        }

        $form->_action = $this->url('install');
        $form->_msg_info = $form->get_fd_message('install_info');
        $form->_data = array_merge(
            $form->_data,
            array('install_demo' => 1)
        );
        $form->add_rep('report_url', $this->url('report'));
        $form->add_fld(
            'install_demo',
            'dbx|hidden',
            rules: 'int',
            dd: ''
        );

        if ($form->submit()) {
            if ($form->errors()) {
                $form->_msg_error = $form->get_fd_message(
                    'install_invalid'
                );
            } else {
                $fixtures = dbx()->get_include_obj(
                    'myInvoicesFixtures',
                    'myInvoices'
                );
                $result = $fixtures->install(true);
                if ((int)($result['ok'] ?? 0) === 1) {
                    $form->_msg_success = $form->format_fd_message(
                        'install_success',
                        array(
                            'invoices' => (int)$result['created_invoices'],
                            'items' => (int)$result['created_items'],
                            'existing' => (int)$result['existing_invoices'],
                        )
                    );
                } else {
                    dbx()->sys_msg(
                        'error',
                        'myInvoices',
                        'install',
                        'Installation failed',
                        (string)($result['message'] ?? '')
                    );
                    $form->_msg_error = $form->get_fd_message(
                        'install_failed'
                    );
                }
            }
        }

        return $form->run();
    }
}

?>
