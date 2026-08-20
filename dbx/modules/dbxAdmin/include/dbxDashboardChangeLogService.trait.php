<?php

namespace dbx\dbxAdmin;

trait dbxDashboardChangeLogServiceTrait
{
    private function change_log_panel(): string
    {
        $dd_ref = 'dbxChangeLog_admin|dbxChangeLog';
        $tpl = dbx()->get_system_obj('dbxTPL');
        $items = '';
        $total = 0;

        try {
            if (dbx()->get_system_obj('dbxDD')->create_db_tab($dd_ref) !== 1) {
                throw new \RuntimeException('Datenbank nicht verfügbar');
            }
            $db = dbx()->get_system_obj('dbxDB');
            $total = (int)$db->count($dd_ref);
            $rows = $db->select($dd_ref, '', array('change_date', 'summary', 'actor', 'resources'), 'change_date', 'DESC', '', 8, 0);
            foreach (is_array($rows) ? $rows : array() as $row) {
                $resources = preg_split('/\R+/', trim((string)($row['resources'] ?? ''))) ?: array();
                $resources = array_slice(array_values(array_filter($resources)), 0, 3);
                $items .= $tpl->get_tpl('dbxAdmin|admin-dashboard-change-log-item', array(
                    'date' => dbx()->esc((string)($row['change_date'] ?? '')),
                    'summary' => dbx()->esc((string)($row['summary'] ?? '')),
                    'actor' => dbx()->esc((string)($row['actor'] ?? '')),
                    'resources' => dbx()->esc(implode(' · ', $resources)),
                ));
            }
        } catch (\Throwable $exception) {
            $items = '<div class="alert alert-warning mb-0">Change Log konnte nicht geladen werden.</div>';
        }

        if ($items === '') {
            $items = '<div class="dbx-admin-dashboard-change-log-empty"><i class="bi bi-journal-check"></i>'
                . '<strong>Noch keine Änderungen protokolliert</strong>'
                . '<span>Abgeschlossene Änderungen von Codex und dbxKi erscheinen hier automatisch.</span></div>';
        }

        return $tpl->get_tpl('dbxAdmin|admin-dashboard-change-log', array(
            'count' => $total,
            'items' => $items,
            'list_url' => '?dbx_modul=dbxChangeLog_admin&amp;dbx_run1=report',
            'new_url' => '?dbx_modul=dbxChangeLog_admin&amp;dbx_run1=form',
        ));
    }
}

