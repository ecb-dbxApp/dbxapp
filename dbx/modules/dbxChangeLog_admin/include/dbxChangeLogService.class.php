<?php

declare(strict_types=1);

namespace dbx\dbxChangeLog_admin;

final class dbxChangeLogService
{
    public const DD = 'dbxChangeLog_admin|dbxChangeLog';
    private const FORM_FD = 'dbxChangeLog_admin|change-log-form';
    private const REPORT_FD = 'dbxChangeLog_admin|rpt-change-log-selection';

    private function db()
    {
        return dbx()->get_system_obj('dbxDB');
    }

    private function can_manage(): bool
    {
        return dbx()->has_group('admin');
    }

    private function url(string $route = 'report', array $params = array()): string
    {
        return dbx()->append_url_params(
            '?dbx_modul=dbxChangeLog_admin&dbx_run1=' . rawurlencode($route),
            $params
        );
    }

    private function ensure_schema(): void
    {
        if (dbx()->get_system_obj('dbxDD')->create_db_tab(self::DD) !== 1) {
            throw new \RuntimeException('Die Change-Log-Datenbank konnte nicht synchronisiert werden.');
        }
    }

    public function form(?int $rid = null): string
    {
        if (!$this->can_manage()) {
            return $this->report();
        }
        $this->ensure_schema();
        $rid ??= (int)dbx()->get_modul_var('rid', 0, 'int');
        $is_new = $rid <= 0;
        $data = $is_new ? array(
            'change_date' => date('Y-m-d H:i:s'),
            'actor' => 'Admin',
        ) : $this->db()->select1(self::DD, $rid);

        if (!is_array($data)) {
            $data = array();
        }

        $form = dbx()->get_system_obj('dbxForm');
        $form->init('dbx-change-log-form', 'dbxChangeLog_admin|change-log-form');
        $form->set_data_source(self::DD, self::FORM_FD);
        $form->load_fd_messages();
        $form->set_data($data)->set_rid($rid)->set_action(
            $this->url('form', $is_new ? array() : array('rid' => $rid))
        );
        $form->add_module_bar(
            $form->get_fd_message($is_new ? 'form_title_new' : 'form_title_edit'),
            'bi-journal-text',
            $form->get_fd_message('form_subtitle')
        );
        $form->add_rep(
            'bar_actions',
            '<a class="btn btn-outline-secondary btn-sm" href="'
                . htmlspecialchars($this->url('report'), ENT_QUOTES, 'UTF-8')
                . '"><i class="bi bi-table"></i> '
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
        $form->add_flds();

        $action = (string)dbx()->get_modul_var('dbx_do', '', 'parameter');
        if ($action === 'delete' && !$is_new) {
            $this->db()->delete(self::DD, $rid);
            return $this->report();
        }

        if ($form->submit() && !$form->errors()) {
            $form->save_post(self::DD, $is_new ? 'new' : $rid);
            if ($is_new && $form->current_rid() > 0) {
                $form->set_action($this->url('form', array('rid' => $form->current_rid())));
            }
        }

        return $form->run();
    }

    public function report(): string
    {
        $this->ensure_schema();
        $can_manage = $this->can_manage();
        dbx()->get_system_obj('dbxAssetRegistry')->add_css('dbxChangeLog_admin', 'change-log.css');
        $report = dbx()->get_system_obj('dbxReport');
        $report->init('change-log-report', 'dbxChangeLog_admin|change-log-report');
        $report->set_data_definition(self::DD)
            ->set_mode('table')
            ->set_action($this->url('report'))
            ->set_pagination(true, 7)
            ->set_table_actions($can_manage ? array('edit', 'delete') : array());
        $report->create_selection_fields(self::REPORT_FD);
        $report->add_rep('bar_title', $report->get_fd_message('report_title'));
        $report->add_rep('bar_subtitle', $report->get_fd_message('report_subtitle'));
        $report->add_rep('bar_icon', 'bi-journal-text');
        $report->add_rep('report_shell_class', 'dbx-change-log-report');
        $report->add_rep('bar_actions', $can_manage
            ? '<a class="btn btn-primary btn-sm" href="'
                . htmlspecialchars($this->url('form'), ENT_QUOTES, 'UTF-8')
                . '"><i class="bi bi-plus-lg"></i> '
                . htmlspecialchars($report->get_fd_message('action_new'), ENT_QUOTES, 'UTF-8')
                . '</a>'
            : '');

        $this->handle_action($report);
        $search = trim((string)$report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=128'));
        $actor = trim((string)$report->get_fld_val('dbx_ractor', '', 'sqlsearch|max=80'));
        $sort = (string)$report->get_fld_val('dbx_rsort', 'change_date', 'parameter');
        $direction = strtoupper((string)$report->get_fld_val('dbx_rdesc', 'DESC', 'parameter'));
        $rows_per_page = max(1, (int)$report->get_fld_val('dbx_rrows', 20, 'int'));
        $position = max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));

        $parts = array();
        if ($search !== '') {
            $parts['search'] = array(
                'value' => $search,
                'like' => array('summary', 'details', 'resources', 'actor'),
                'mode' => 'contains',
            );
        }
        if ($actor !== '') {
            $parts['actor'] = $actor;
        }
        $where = $parts ?: '';
        $fields = array(
            'change_date' => $report->get_fd_message('column_date'),
            'summary' => $report->get_fd_message('column_summary'),
            'details' => $report->get_fd_message('column_details'),
        );
        $select_fields = array('id' => 'ID', 'resources' => 'Ressourcen') + $fields;
        $safe_direction = in_array($direction, array('ASC', 'DESC'), true) ? $direction : 'DESC';
        $result = $this->db()->select(
            self::DD,
            $where,
            $select_fields,
            in_array($sort, array('change_date', 'summary', 'actor'), true) ? $sort : 'change_date',
            $safe_direction,
            '',
            $rows_per_page,
            $position
        );
        $filtered_count = (int)$this->db()->count(self::DD, $where);
        $total_count = (int)$this->db()->count(self::DD);
        $report->set_report_fields($fields)
            ->set_report_result(is_array($result) ? $result : array(), $position, $filtered_count)
            ->set_report_counts($filtered_count, $total_count);

        return $report->run();
    }

    /** Ergänzt die zweite Reportstufe für jede sichtbare Hauptzeile. */
    public function change_log_report_next_record($report, $record)
    {
        if (!is_array($record)) {
            return $record;
        }
        $id = (int)($record['id'] ?? 0);
        $timestamp = strtotime((string)($record['change_date'] ?? ''));
        $record['change_date'] = $timestamp === false
            ? htmlspecialchars((string)($record['change_date'] ?? ''), ENT_QUOTES, 'UTF-8')
            : date('d.m.Y H:i', $timestamp);
        $record['summary'] = htmlspecialchars((string)($record['summary'] ?? ''), ENT_QUOTES, 'UTF-8');
        $record['details'] = htmlspecialchars((string)($record['details'] ?? ''), ENT_QUOTES, 'UTF-8');
        $record['resources_call'] = '[modul=dbxChangeLog_admin]dbx_run1=resources&change_log_id='
            . $id . '[/modul]';
        return $record;
    }

    /** Rendert die Ressourcen eines Change-Log-Eintrags als eingebetteten Subreport. */
    public function resources(?int $change_log_id = null): string
    {
        $this->ensure_schema();
        $change_log_id ??= (int)dbx()->get_modul_var('change_log_id', 0, 'int');
        if ($change_log_id <= 0) {
            return '';
        }
        $record = $this->db()->select1(self::DD, $change_log_id);
        if (!is_array($record) || (int)($record['id'] ?? 0) <= 0) {
            return '';
        }
        $resource_names = preg_split('/\R+/', trim((string)($record['resources'] ?? ''))) ?: array();
        $rows = array();
        foreach (array_values(array_unique(array_filter(array_map('trim', $resource_names)))) as $resource) {
            $rows[] = array(
                'resource' => htmlspecialchars($resource, ENT_QUOTES, 'UTF-8'),
            );
        }

        $report = dbx()->get_system_obj('dbxReport');
        $report->init(
            'change-log-resources-report',
            'dbxChangeLog_admin|change-log-resources-report'
        );
        $report->set_field_definition('dbxChangeLog_admin|change-log-resources');
        $report->load_fd_messages();
        $report->set_mode('table')->set_pagination(false)->set_table_actions(array());
        $report->add_rep('resource_count', (string)count($rows));
        $report->add_rep('resource_panel_key', 'change-log-resources-' . $change_log_id);
        $report->set_report_fields(array(
            'resource' => $report->get_fd_message('column_resource'),
        ));
        $report->set_page_size(max(1, count($rows)))->set_report_result($rows, 0, count($rows));
        return $report->run();
    }

    private function handle_action($report): void
    {
        if (!$this->can_manage()) {
            return;
        }
        $action = (string)dbx()->get_modul_var('dbx_do', '', 'parameter');
        $rid = (int)dbx()->get_modul_var('rid', 0, 'int');
        if ($action === 'row_edit' && $rid > 0) {
            echo $this->form($rid);
            exit;
        }
        if ($action === 'row_delete' && $rid > 0) {
            $ok = (bool)$this->db()->delete(self::DD, $rid);
            $report->{$ok ? '_msg_success' : '_msg_error'} = $report->get_fd_message(
                $ok ? 'delete_success' : 'delete_error'
            );
        }
    }
}
