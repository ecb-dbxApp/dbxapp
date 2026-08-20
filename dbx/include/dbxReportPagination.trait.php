<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxReportPaginationTrait
{
/**
     * Erzeugt die Pagination des Reports.
     *
     * Verwendet die bereits gesetzten Report-Werte _rpos und _rrows
     * und liest diese nicht noch einmal implizit aus dem Formular.
     *
     * @return string
     */
    public function get_report_pages() {
        $content = '';
        $modul   = $this->_dbx_modul;
        $action  = $this->_dbx_action;
        $rcount  = $this->_rcount;
        $link    = $this->_pagelink;
        $tpl     = $this->_tpl_pagination;
        $rpos    = $this->_rpos;
        $rrows   = $this->_rrows;

        if (!$link) {
            $link = '?dbx_modul=' . $modul . '&dbx_run1=' . $action;
        }

        $link    = $this->_action ?: $link;
        $content = $this->pagination($tpl, $link, $rpos, $rrows, $rcount);

        return $content;
    }

private function lnk_page($p, $akt_page, $link, $rpos, $rrows, $rcount) {
        $active   = '';
        $class    = '';
        $current  = '';
        $p_active = '';
        $s_active = '';

        if ($p == $akt_page) {
            $p_active = ' aria-current="page"';
            $active   = ' active';
            $current  = ' aria-current="page" ';
        }

        $rec = array();
        $rec['p']         = $p . $s_active;
        $rec['href_page'] = $link . '&dbx_rrows=' . $rrows . '&dbx_rpos=' . (($p - 1) * $rrows);
        $rec['p_active']  = $p_active;
        $rec['active']    = $active;
        $rec['current']   = $current;
        $rec['class']     = $class . ' dbxAjax';

        return $rec;
    }

private function pagination($tpl, $link, $rpos, $rrows, $rcount) {
        if ($rrows == 0) {
            return '';
        }

        $pages = intval($rcount / $rrows);

        if ($rcount % $rrows) $pages++;
        if ($pages == 0)  $pages = 1;


        $pmax     = $this->_but_pagination;
        $akt_page = intval($rpos / $rrows) + 1;

        if ($akt_page < 1)  $akt_page = 1;
        if ($akt_page > $pages) $akt_page = $pages;

        $half = intval($pmax / 2);
        $p_s  = $akt_page - $half;
        $p_e  = $akt_page + $half;

        if ($p_s < 1) {
            $p_s = 1;
            $p_e = $pmax;
        }

        if ($p_e > $pages) {
            $p_e = $pages;
            $p_s = $pages - $pmax + 1;

            if ($p_s < 1) {
                $p_s = 1;
            }
        }

        $last_pos = ($pages - 1) * $rrows;
        $prev     = ($akt_page - 2) * $rrows;
        $next     = ($akt_page) * $rrows;

        if ($prev < 0) $prev = 0;
        if ($next > $last_pos)  $next = $last_pos;

        $href_first = $link . '&dbx_rpos=0&dbx_rrows=' . $rrows;
        $href_last  = $link . '&dbx_rpos=' . $last_pos . '&dbx_rrows=' . $rrows ;
        $href_prev  = $link . '&dbx_rpos=' . $prev . '&dbx_rrows=' . $rrows ;
        $href_next  = $link . '&dbx_rpos=' . $next . '&dbx_rrows=' . $rrows ;

        $this->_sys['dbx_rpos']  = $rpos;
        $this->_sys['dbx_rrows'] = $rrows;

        $dv = array();
        $dv['dbx_rpos']  = $rpos;
        $dv['dbx_rrows'] = $rrows;
        $rdata = array();

        for ($p = $p_s; $p <= $p_e; $p++) {
            $rdata[] = $this->lnk_page($p, $akt_page, $link, $rpos, $rrows, $rcount);

            if ($p >= $pages) {
                break;
            }
        }

        $o_report = dbx()->get_system_obj('dbxReport');

        $o_report->init('pagination');
        $o_report->_data            = $dv;
        $o_report->_dbx_modul       = 'dbx';
        $o_report->_dbx_action      = 'pagination';
        $o_report->_dbx_modul_id    = 888;
        $o_report->_rdata           = $rdata;
        $o_report->_rcount          = $rcount;
        $o_report->_rrows           = $rrows;
        $o_report->_rpos            = $rpos;
        $o_report->_action          = $link;
        $o_report->_tpl             = $tpl;
        $o_report->_pages           = 0;
        $o_report->_mode            = 'table';
        $o_report->_rflds           = array();
        $o_report->_body_inline     = false;
        $o_report->_create_sel_flds = 0;
        // Die Pagination rendert ueber eine eigene, isolierte dbxReport-Instanz
        // mit synthetischem Modul/Fid ('dbx'/'pagination'). Ohne diese
        // Uebernahme wuerden {pagination:count_all} und
        // {pagination:count_checked} den (leeren) Auswahl-/Zaehlkontext dieser
        // Hilfsinstanz statt des tatsaechlichen Reports lesen.
        $o_report->_count_all       = ($this->_count_all >= 0) ? $this->_count_all : $rcount;
        $o_report->_count_selects   = $this->get_count_selects();

        $content = $o_report->run();

        $content = str_replace('{href_first}', $href_first, $content);
        $content = str_replace('{href_last}',  $href_last,  $content);
        $content = str_replace('{href_prev}',  $href_prev,  $content);
        $content = str_replace('{href_next}',  $href_next,  $content);
        $select_state = $this->get_visible_multi_select_state();

        $count_all = ($this->_count_all >= 0) ? $this->_count_all : $rcount;

        $content = $this->apply_report_count_replaces($content);

        return $content;
    }

public function data_rows($data, $rpos, $rrows) {
        require_once __DIR__ . '/dbxReportDataWindow.class.php';
        return (new dbxReportDataWindow())->slice(
            is_array($data) ? $data : array(),
            (int)$rpos,
            (int)$rrows
        );
    }

public function add_where($mode, $select, $where = '') {
        if ($select) {
            if ($where) {
                $where .= " $mode (";
                $where .= $select;
                $where .= ') ';
            } else {
                $where = $select;
            }
        }

        return $where;
    }

public function no_page_reset() {
        $this->_page_reset = 0;
    }
}
