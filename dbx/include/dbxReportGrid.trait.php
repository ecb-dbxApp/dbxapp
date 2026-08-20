<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxReportGridTrait
{
/**
     * Markiert und signiert einen schreibenden Grid-Endpunkt.
     *
     * Grid-Aktionen senden JSON und durchlaufen deshalb nicht den normalen
     * dbxForm-Submit. dbxWebApp erkennt die Grid-Konvention direkt in der
     * Zielroute und prueft sie wie jede andere dbxReport-Standardaktion.
     */
    protected function get_grid_action_url($url, $action) {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        $secured_url = dbx()->action_url($url);
        if ($secured_url === $url) {
            dbx()->debug(
                'dbxReport grid action blocked: convention not recognized'
                . ' action=(' . (string)$action . ') url=(' . $url . ')'
            );
            return '';
        }

        return $secured_url;
    }

protected function get_grid_replaces(): array {
        $grid_id = $this->_grid_id;

        if (!$grid_id) {
            $grid_id = $this->_fid . '_grid';
        }

        $cols = $this->_grid_cols;

        if (!$cols && $this->_dd) {
            $o_db  = dbx()->get_system_obj('dbxDB');
            $cols = $o_db->get_dd_grid_cols($this->_dd);
        }

        $rrows = (int) $this->_rrows;
        $rpos  = (int) $this->_rpos;
        $page  = ($rrows > 0) ? ((int) floor($rpos / $rrows) + 1) : 1;

        return array(
            'read_url'       => $this->_grid_read_url,
            'save_url'       => $this->get_grid_action_url($this->_grid_save_url, 'save'),
            'delete_url'     => $this->get_grid_action_url($this->_grid_delete_url, 'delete'),
            'insert_url'     => $this->get_grid_action_url($this->_grid_insert_url, 'insert'),
            'sort_url'       => $this->get_grid_action_url($this->_grid_sort_url, 'sort'),
            'sync_url'       => $this->get_grid_action_url($this->_grid_sync_url, 'sync'),
            'print_url'      => $this->_grid_print_url,
            'export_url'     => $this->_grid_export_url,
            'grid_id'        => $grid_id,
            'grid_cols'      => $cols,
            'grid_schema'    => $this->_grid_schema,
            'grid_layout'    => $this->_grid_layout,
            'grid_height'    => $this->_rrows,
            'grid_synctime'  => $this->_grid_synctime,
            'grid_page'      => $page,
            'grid_page_size' => ($rrows > 0) ? $rrows : 25,
        );
    }

public function add_grid_stats(array $stats, $aria_label = '') {
        if (!$stats) {
            $this->add_rep('grid_stats', '');
            return;
        }

        $html = '<div class="dbx-grid-stats"';
        if ((string)$aria_label !== '') {
            $html .= ' aria-label="' . htmlspecialchars((string)$aria_label, ENT_QUOTES) . '"';
        }
        $html .= '>';

        foreach ($stats as $stat) {
            if (!is_array($stat)) {
                continue;
            }

            $label = htmlspecialchars((string)($stat['label'] ?? ''), ENT_QUOTES);
            $value = htmlspecialchars((string)($stat['value'] ?? ''), ENT_QUOTES);
            $tone  = (string)($stat['tone'] ?? '');
            $class = 'dbx-grid-stat';

            if ($tone === 'ok') {
                $class .= ' dbx-grid-stat-ok';
            } elseif ($tone === 'lock') {
                $class .= ' dbx-grid-stat-lock';
            } elseif ($tone !== '') {
                $class .= ' dbx-grid-stat-' . preg_replace('/[^a-z0-9_-]/i', '', $tone);
            }

            $html .= '<div class="' . $class . '"><span>' . $label . '</span><strong>' . $value . '</strong></div>';
        }

        $html .= '</div>';
        $this->add_rep('grid_stats', $html);
    }

public function apply_tabulator_request() {
        $page   = dbx()->get_request_var('page', 0, 'int');
        $size   = dbx()->get_request_var('size', 0, 'int');
        $limit  = dbx()->get_request_var('limit', 0, 'int');
        $offset = dbx()->get_request_var('offset', -1, 'int');

        if ($size > 0) {
            $this->_rrows = $size;
        } elseif ($limit > 0) {
            $this->_rrows = $limit;
        }

        if ($offset >= 0) {
            $this->_rpos = $offset;
        } elseif ($page > 0 && $this->_rrows > 0) {
            $this->_rpos = (($page - 1) * $this->_rrows);
        }
    }

public function get_report_rows_array(): array {
        $rows = array();

        if (!is_array($this->_rdata)) {
            return $rows;
        }

        foreach ($this->_rdata as $record) {
            $this->_record = $record;
            $dummy = '';
            $this->run_body($dummy);
            $record = $this->_record;

            if (!is_array($record)) {
                $record = array();
            }

            $row = array();

            foreach ($record as $field => $value) {
                if (is_array($value)) {
                    $row[$field] = $value;
                } else {
                    $row[$field] = $this->rpt_format($field, $value);
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

public function fast_response_rows_json() {
        $rows  = $this->get_report_rows_array();
        $count = (int) $this->_rcount;
        $rrows = (int) $this->_rrows;
        $rpos  = (int) $this->_rpos;
        $pages = 0;
        $page  = 1;

        if ($rrows > 0) {
            $pages = (int) ceil($count / $rrows);
            $page  = (int) floor($rpos / $rrows) + 1;
        }

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(array(
            'ok'    => 1,
            'count' => $count,
            'rows'  => array_values($rows),
            'rpos'  => $rpos,
            'rrows' => $rrows,
            'page'  => $page,
            'pages' => $pages,
        ), JSON_UNESCAPED_UNICODE);

        exit;
    }
}
