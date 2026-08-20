<?php

declare(strict_types=1);

return <<<'PHP'
<?php

declare(strict_types=1);

namespace dbx\__MODUL__;

/**
 * Fachservice des vom dbxAdmin-Wizard erzeugten Moduls.
 *
 * Das Formular behält sein individuelles Modul-Layout. Bedienleiste,
 * Meldungen und Footer kommen aus den gemeinsamen dbx-Komponenten. Der
 * Tabellenreport verwendet bewusst das zentrale Standardtemplate.
 */
final class __CLASS__
{
    private const DD = __DD_REF__;
    private const FORM_FD = __FORM_FD__;
    private const REPORT_FD = __REPORT_FD__;

    private function db()
    {
        return dbx()->get_system_obj('dbxDB');
    }

    private function tpl()
    {
        return dbx()->get_system_obj('dbxTPL');
    }

    private function url(string $run1 = 'report', array $params = array()): string
    {
        $url = '?dbx_modul=__MODUL__&dbx_run1=' . rawurlencode($run1);
        return dbx()->append_url_params($url, $params);
    }

    private function standard_message(string $name): string
    {
        return $this->tpl()->get_tpl('dbx|form-message-' . $name);
    }

    /** Rendert und verarbeitet das individuelle Modulformular. */
    public function form(?int $rid = null): string
    {
        if (self::DD === '') {
            return $this->tpl()->get_tpl('dbx|alert-warning', array(
                'msg' => 'Für dieses Modul ist kein Data Dictionary konfiguriert.',
            ));
        }

        $rid = $rid ?? (int)dbx()->get_modul_var('rid', 0, 'int');
        $action = (string)dbx()->get_modul_var('dbx_do', '', 'parameter');
        if ($action === 'delete' && $rid > 0) {
            return $this->delete_record($rid);
        }

        $is_new = $rid <= 0;
        $data = $is_new
            ? array('activ' => 1)
            : $this->db()->select1(self::DD, $rid);
        if (!is_array($data)) {
            $data = array('activ' => 1);
        }

        $form = dbx()->get_system_obj('dbxForm');
        $form->init('__MODUL__-form', '__MODUL__|__FORM_TEMPLATE__');
        $form->set_data_source(self::DD, self::FORM_FD);
        $form->load_fd_messages();
        $form
            ->set_data($data)
            ->set_rid($rid)
            ->set_action($this->url('form', $is_new ? array() : array('rid' => $rid)));

        $form->add_module_bar(
            $form->get_fd_message($is_new ? 'form_title_new' : 'form_title_edit'),
            'bi-ui-checks',
            $form->get_fd_message('form_subtitle')
        );
        $form->add_rep(
            'bar_actions',
            '<a class="btn btn-outline-secondary btn-sm" href="'
                . htmlspecialchars($this->url('report'), ENT_QUOTES, 'UTF-8')
                . '"><i class="bi bi-table" aria-hidden="true"></i> '
                . htmlspecialchars($form->get_fd_message('action_report'), ENT_QUOTES, 'UTF-8')
                . '</a>'
        );
        $form->add_module_bar_form_actions(array(
            'save' => true,
            'delete' => !$is_new,
            'delete_url' => $is_new ? '' : dbx()->action_url(
                $this->url('form', array('rid' => $rid, 'dbx_do' => 'delete'))
            ),
            'reload' => true,
        ));
        $form->_msg_info = $form->get_fd_message('form_info');
        $form->add_flds();

        if ($form->submit() && !$form->errors()) {
            $form->save_post(
                self::DD,
                $is_new ? 'new' : $rid,
                $this->save_defaults($rid)
            );
            if ($is_new && $form->current_rid() > 0) {
                $form->set_action($this->url('form', array(
                    'rid' => $form->current_rid(),
                )));
            }
        }

        return $form->run();
    }

    /** Liefert die technischen Standardwerte für Insert und Update. */
    private function save_defaults(int $rid): array
    {
        $uid = (int)dbx()->user();
        $now = date('Y-m-d H:i:s');
        $defaults = array('update_date' => $now, 'update_uid' => $uid);
        if ($rid <= 0) {
            $defaults += array(
                'create_date' => $now,
                'create_uid' => $uid,
                'owner' => $uid,
                'trash' => 0,
            );
        }
        return $defaults;
    }

    /** Löscht einen Datensatz; die Route wird zentral per Action-Token geprüft. */
    public function delete_record(int $rid): string
    {
        $ok = $rid > 0 && (bool)$this->db()->delete(self::DD, $rid);
        return $this->standard_message($ok ? 'delete-success' : 'delete-error')
            . $this->report();
    }

    /** Zeigt einen einzelnen Datensatz ohne eigenes Detailtemplate. */
    public function detail(?int $rid = null): string
    {
        $rid = $rid ?? (int)dbx()->get_modul_var('rid', 0, 'int');
        $record = $rid > 0 ? $this->db()->select1(self::DD, $rid) : array();
        if (!is_array($record) || !$record) {
            return $this->tpl()->get_tpl('dbx|alert-warning', array(
                'msg' => 'Der Datensatz wurde nicht gefunden.',
            ));
        }

        $rows = '';
        foreach ($record as $key => $value) {
            $rows .= '<tr><th>' . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8')
                . '</th><td>' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
                . '</td></tr>';
        }
        return '<div class="dbx-panel"><div class="dbx-panel-body">'
            . '<div class="table-responsive"><table class="table table-sm">'
            . $rows . '</table></div></div></div>';
    }

    /** Rendert einen normalen Tabellenreport über dbx|report-default. */
    public function report(): string
    {
        if (self::DD === '') {
            return $this->tpl()->get_tpl('dbx|alert-warning', array(
                'msg' => 'Für dieses Modul ist kein Data Dictionary konfiguriert.',
            ));
        }

        $report = dbx()->get_system_obj('dbxReport');
        $report->init('__MODUL__-report');
        $report
            ->set_data_definition(self::DD)
            ->set_mode('table')
            ->set_action($this->url('report'))
            ->set_pagination(true, 7)
            ->set_table_actions(array('select', 'edit', 'show', 'delete'));
        $report->_multi_page_select = 1;
        $report->create_selection_fields(self::REPORT_FD);
        $report->add_rep('bar_title', $report->get_fd_message('report_title'));
        $report->add_rep('bar_subtitle', $report->get_fd_message('report_subtitle'));
        $report->add_rep('bar_icon', 'bi-table');
        $report->add_rep(
            'bar_actions',
            '<a class="btn btn-primary btn-sm" href="'
                . htmlspecialchars($this->url('form'), ENT_QUOTES, 'UTF-8')
                . '"><i class="bi bi-plus-lg" aria-hidden="true"></i> '
                . htmlspecialchars($report->get_fd_message('action_new'), ENT_QUOTES, 'UTF-8')
                . '</a>'
        );
        $report->add_action('rows_select', 'action_button_select', '&dbx_do=rows_select');
        $report->add_action('rows_deselect', 'action_button_deselect', '&dbx_do=clear_selects');
        $report->add_action('rows_delete', 'action_button_delete', '&dbx_do=multi_delete');

        $action_content = $this->handle_report_action($report);
        if ($action_content !== '') {
            return $action_content;
        }

        $search = trim((string)$report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64'));
        $sort = (string)$report->get_fld_val('dbx_rsort', 'title', 'parameter');
        $direction = strtoupper((string)$report->get_fld_val('dbx_rdesc', 'ASC', 'parameter'));
        $rows_per_page = max(1, (int)$report->get_fld_val('dbx_rrows', 20, 'int'));
        $position = max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
        $selected_only = (int)$report->get_fld_val('dbx_rselect', 0, 'int') === 1;

        $where = $search === '' ? '' : array('search' => array(
            'value' => $search,
            'like' => array('title', 'description'),
            'mode' => 'contains',
        ));
        if ($selected_only) {
            $where = $report->add_rwhere_select('');
        }

        $fields = __FIELDS_EXPORT__;
        $result = $this->db()->select(
            self::DD,
            $where,
            $fields,
            $sort,
            in_array($direction, array('ASC', 'DESC'), true) ? $direction : 'ASC',
            '',
            $rows_per_page,
            $position
        );
        $report
            ->set_report_fields($fields)
            ->set_report_result(
                is_array($result) ? $result : array(),
                $position,
                (int)$this->db()->count(self::DD, $where)
            );
        $report->_count_all = (int)$this->db()->count(self::DD);

        return $report->run();
    }

    /** Führt die standardisierten Zeilen- und Mehrfachaktionen aus. */
    private function handle_report_action($report): string
    {
        $action = (string)dbx()->get_modul_var('dbx_do', '', 'parameter');
        $rid = (int)dbx()->get_modul_var('rid', 0, 'int');

        if ($action === 'row_edit' && $rid > 0) {
            return $this->form($rid);
        }
        if (in_array($action, array('row_show', 'detail'), true) && $rid > 0) {
            return $this->detail($rid);
        }
        if ($action === 'row_delete' && $rid > 0) {
            $ok = (bool)$this->db()->delete(self::DD, $rid);
            $report->del_selected($rid);
            if ($ok) {
                $report->_msg_success = $report->get_fd_message('delete_success');
            } else {
                $report->_msg_error = $report->get_fd_message('delete_error');
            }
        }
        if ($action === 'multi_delete') {
            $report->apply_multi_delete_result(
                $report->delete_multi_selected_records(self::DD)
            );
        }
        return '';
    }

    /** Minimaler API-Platzhalter für die fachliche Erweiterung im Modul. */
    public function api(): void
    {
        dbx()->json_response(array(
            'ok' => true,
            'module' => '__MODUL__',
            'dd' => self::DD,
        ));
    }
}
PHP;
