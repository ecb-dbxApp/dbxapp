<?php

require_once 'dbxForm.class.php';

/**
 * =========================================================
 * DBX REPORT SYSTEM (dbxReport)
 * =========================================================
 *
 * Überblick
 * ---------
 * dbxReport ist die zentrale Listen- und Reportklasse von DBX.
 *
 * Die Klasse erweitert dbxForm um Mehrdatensatz-Ausgaben und deckt
 * die typischen Report-Fälle des Systems ab:
 *
 * - klassische Tabellenreports
 * - freie Template-/Record-Reports
 * - Sortierung, Suche, Filter und Pagination
 * - Row-Aktionen wie edit, copy, delete, show, print, export, import
 * - Remember-basierten Multi-Select
 * - Grid-/Tabulurator-Shells
 *
 *
 * Grundprinzip
 * ------------
 * dbxReport arbeitet zustandsbehaftet.
 *
 * Das Modul setzt den Report-Kontext:
 * - DD
 * - Template
 * - Feldliste
 * - Datenmenge
 * - Count
 * - optionale Row-Aktionen / Selektionsfelder / Grid-URLs
 *
 * Danach rendert dbxReport den gewünschten Ausgabemodus.
 *
 *
 * Typischer Ablauf
 * ----------------
 * 1. init(...)
 * 2. _dd / _tpl / _rflds / _rdata / _rcount setzen
 * 3. optional Aktionen / Select / Grid konfigurieren
 * 4. run(...)
 *
 *
 * Modi
 * ----
 * table
 *   Klassischer HTML-Tabellenreport mit automatischem Header/Body/Footer.
 *
 * tpl
 *   Freier Mehrdatensatz-Report auf Template-Basis.
 *
 * tabulurator
 *   Shell-/Container-Modus für Grid/Tabulator-artige Oberflächen.
 *   Hier ist dbxReport primär Kontext- und URL-Lieferant.
 *
 *
 * Multi-Select
 * ------------
 * Multi-Select wird reportbezogen über Remember gespeichert.
 *
 * Dadurch bleibt die Auswahl erhalten:
 * - über Reloads
 * - über Pagination
 * - über klassische Report-Workflows
 *
 * Wichtige Regel:
 * - Die Header-Checkbox bezieht sich immer auf die aktuell sichtbaren Zeilen.
 * - Die Gesamtauswahl bleibt trotzdem reportweit gespeichert.
 *
 *
 * Select-ID / RID
 * ---------------
 * dbxReport verwendet als Record-ID grundsätzlich:
 * - zuerst $_fld_id
 * - falls dort nichts gefunden wird, fallback auf typische Felder wie
 *   rid / id / recnum
 *
 * Damit bleiben sowohl klassische DBX-Tabellen als auch Reports mit
 * abweichendem Primärschlüssel verwendbar.
 *
 *
 * Template-Hinweis
 * ----------------
 * Header-Select-Template:
 * - erwartet typischerweise {checked}
 *
 * Row-Select-Template:
 * - bekommt sowohl {value} als auch {rid}
 * - beide enthalten absichtlich dieselbe ermittelte Record-ID
 *
 * Row-Actions:
 * - bekommen konsistent Daten über get_tpl($file, $dat)
 * - insbesondere {rid} und {action}
 *
 *
 * Hook-Modell
 * -----------
 * Module können den Ablauf gezielt erweitern:
 * - run_haeder()
 * - run_body()
 * - run_footer()
 * - run_report_haeder()
 * - run_page_haeder()
 * - run_page_footer()
 * - run_report_footer()
 *
 *
 * Wichtige Regel
 * --------------
 * Im Modus 'tabulurator' ist dbxReport kein klassischer HTML-Record-Renderer.
 * Dort liefert die Klasse primär Shell, URLs, Grid-Replaces und optional
 * JSON-kompatible Row-Daten.
 */
class dbxReport extends dbxForm {

    /** AJAX-Linkklasse für klassische Report-Buttons */
    public $_ajax = 0;

    /** Report-Modus: table|tpl|tabulurator */
    public $_mode = 'table';

    /** Aktuelle logische Report-Seite */
    public $_current_page = 0;

    /** Aktuelle Report-Zeile insgesamt */
    public $_current_report_ln = 0;

    /** Aktuelle Zeile auf aktueller Seite */
    public $_current_page_ln = 0;

    /** Maximale Zeilen auf erster Seite */
    public $_first_page_lines = 9999;

    /** Maximale Zeilen auf Folgeseiten */
    public $_next_page_lines = 9999;

    /** Historischer Header-Name */
    public $_fld_haeder = '';

    /** Report-Header außerhalb der eigentlichen Seiten */
    public $_haeder_report = '';

    /** Report-Footer außerhalb der eigentlichen Seiten */
    public $_footer_report = '';

    /** Seiten-Header */
    public $_haeder_page = '';

    /** Seiten-Footer */
    public $_footer_page = '';

    /** Header für Folgeseiten */
    public $_haeder_next_page = '';

    /** Footer für Folgeseiten */
    public $_footer_next_page = '';

    /** Seitenumbruch-Markup */
    public $_page_break = '<div class="page-break printMe"> </div><br>';

    /** Interner Bereich: Header */
    public $_haeder = '';

    /** Interner Bereich: Body */
    public $_body = '';

    /** Interner Bereich: Footer */
    public $_footer = '';

    /** Aktueller Datensatz im Body-Lauf */
    public $_record = array();

    /** Report-Daten */
    public $_rdata = array();

    /** Inline-Modus: Daten werden nur einmal in Body eingefügt */
    public $_rdata_inline = false;

    /** Inline-Body */
    public $_body_inline = '';

    /** Gesamtanzahl Datensätze */
    public $_rcount = 0;

    /** Offset */
    public $_rpos = 0;

    /** Datensätze pro Seite */
    public $_rrows = 20;

    /** Platzhaltertext für Suchfeld */
    public $_rwhere_placeholder = '#search_for#';

    /** Pagination aktiv */
    public $_pages = 0;

    /** Pagination-Link */
    public $_pagelink = '';

    /** Automatische Feldliste */
    public $_auto_flds = '';

    /** Automatischer Ausgabemodus */
    public $_auto_mode = '';

    /** Report-Feldliste */
    public $_rflds = '';

    /** Pagination-Template */
    public $_tpl_pagination = 'dbx|pagination';

    /** Anzahl sichtbarer Pagination-Buttons */
    public $_but_pagination = 3;

    /** Zusatzparameter an Row-Aktionen */
    public $_add_action = '';

    /** Selektionsfelder erstellen */
    public $_create_sel_flds = 0;

    /** Zeilen-Select aktiv */
    public $_create_row_select = 0;

    /** Zeilen-Edit aktiv */
    public $_create_row_edit = 0;

    /** Zeilen-Copy aktiv */
    public $_create_row_copy = 0;

    /** Zeilen-Delete aktiv */
    public $_create_row_delete = 0;

    /** Zeilen-Download aktiv */
    public $_create_row_download = 0;

    /** Zeilen-Show aktiv */
    public $_create_row_show = 0;

    /** Zeilen-Export aktiv */
    public $_create_row_export = 0;

    /** Zeilen-Import aktiv */
    public $_create_row_import = 0;

    /** Zeilen-Undo aktiv */
    public $_create_row_undo = 0;

    /** Zeilen-Print aktiv */
    public $_create_row_print = 0;

    /** Feldformatierungen */
    public $_rpt_format = array();

    /** Sortieroptionen */
    public $_options_rsort = array();

    /** Seitenlängenoptionen */
    public $_options_rrows = array();

    /** ASC/DESC Optionen */
    public $_options_rdesc = array();

    /** Auswahloptionen */
    public $_options_rselect = array();

    /** Tabellen-Templates */
    public $_tabel_tpls = array();

    /** Anzahl sichtbarer Tabellen-Spalten */
    public $_table_col_count = 0;

    /** Multi-Select seitenübergreifend */
    public $_multi_page_select = 0;

    /** Interner Multi-Select-Arbeitsmodus */
    public $_multi_select_work = '';

    /** Buttons links oder rechts */
    public $_table_buttons = 'left';

    /**
     * Daten-Tabellen-/Expander-Schalter.
     *
     * Historisch:
     * - 0 / 1
     * - auto
     * - 88
     *
     * Typ: $type. */
    public $_data_table = 0;

    /** Scrollbare Tabelle */
    public $_scroll_table = 0;

    /** Header-Klassen */
    public $_class_haeder = array();

    /** Header-Styles */
    public $_style_haeder = array();

    /** Body-Klassen */
    public $_class_body = array();

    /** Caching für Anzahl ausgewählter Datensätze */
    public $_count_selects = -1;

    /** Confirm-Text für Delete */
    public $_msg_confirm_delete = 'Datensatz löschen ?';

    /** Confirm-Text für Copy */
    public $_msg_confirm_copy = 'Datensatz kopieren ?';

    /* =====================================================
     * GRID / TABULURATOR SUPPORT
     * ===================================================== */

    /** Read-URL für Grid */
    public $_grid_read_url = '';

    /** Save-URL für Grid */
    public $_grid_save_url = '';

    /** Delete-URL für Grid */
    public $_grid_delete_url = '';

    /** Insert-URL für Grid */
    public $_grid_insert_url = '';

    /** Sort-URL für Grid */
    public $_grid_sort_url = '';

    /** Sync-URL für Grid */
    public $_grid_sync_url = '';

    /** Print-URL für Grid/Shell */
    public $_grid_print_url = '';

    /** Export-URL */
    public $_grid_export_url = '';

    /** Schema-Name für Grid */
    public $_grid_schema = '';

    /** Grid-ID */
    public $_grid_id = '';

    /** Grid-Spalten-Definition */
    public $_grid_cols = '';

    /** Grid-Layout */
    public $_grid_layout = 'fitColumns';

    /** Headerfilter für Grid */
    public $_grid_headerfilter = 1;

    /** Headersort für Grid */
    public $_grid_headersort = 1;

    /** Delete im Grid erlaubt */
    public $_grid_allow_delete = 1;

    /** Edit im Grid erlaubt */
    public $_grid_allow_edit = 1;

    /** Insert im Grid erlaubt */
    public $_grid_allow_insert = 1;

    /** Grid-Synctime */
    public $_grid_synctime = '2';

    /**
     * Löscht den kompletten Report-Zustand und setzt saubere Defaultwerte.
     *
     * @return void
     */
    public function clear() {
        $this->_forward_clear();

        $this->_rpt_format        = array();
        $this->_options_rsort     = array();
        $this->_options_rrows     = array();
        $this->_options_rdesc     = array();
        $this->_options_rselect   = array();

        $this->_style_haeder      = array();
        $this->_class_haeder      = array();
        $this->_class_body        = array();

        $this->_record            = array();
        $this->_rdata             = array();

        $this->_haeder            = '';
        $this->_body              = '';
        $this->_footer            = '';

        $this->_haeder_report     = '';
        $this->_footer_report     = '';
        $this->_haeder_page       = '';
        $this->_footer_page       = '';
        $this->_haeder_next_page  = '';
        $this->_footer_next_page  = '';

        $this->_table_col_count   = 0;
        $this->_count_selects     = -1;
        $this->_multi_select_work = '';

        $this->_grid_read_url      = '';
        $this->_grid_save_url      = '';
        $this->_grid_delete_url    = '';
        $this->_grid_insert_url    = '';
        $this->_grid_sort_url      = '';
        $this->_grid_sync_url      = '';
        $this->_grid_print_url     = '';
        $this->_grid_export_url    = '';
        $this->_grid_schema        = '';
        $this->_grid_id            = '';
        $this->_grid_cols          = '';
        $this->_grid_layout        = 'fitColumns';
        $this->_grid_headerfilter  = 1;
        $this->_grid_headersort    = 1;
        $this->_grid_allow_delete  = 1;
        $this->_grid_allow_edit    = 1;
        $this->_grid_allow_insert  = 1;
        $this->_grid_synctime      = '2';

        $table = array();

        $table['tpl_haeder_col']      = 'table_haeder_col';
        $table['tpl_haeder_select']   = 'table_haeder_select';
        $table['tpl_haeder_delte']    = 'table_haeder_delete';
        $table['tpl_haeder_expand']   = 'table_haeder_expand';
        $table['tpl_haeder_expander'] = 'table_haeder_expander';
        $table['tpl_haeder_edit']     = 'table_haeder_edit';
        $table['tpl_haeder_copy']     = 'table_haeder_copy';
        $table['tpl_haeder_undo']     = 'table_haeder_undo';
        $table['tpl_haeder_show']     = 'table_haeder_show';
        $table['tpl_haeder_import']   = 'table_haeder_import';
        $table['tpl_haeder_export']   = 'table_haeder_export';
        $table['tpl_haeder_download'] = 'table_haeder_download';
        $table['tpl_haeder_print']    = 'table_haeder_print';

        $table['tpl_row_col']         = 'table_row_col';
        $table['tpl_row_select']      = 'table_row_select';
        $table['tpl_row_expand']      = 'table_row_expand';
        $table['tpl_row_expander']    = 'table_row_expander';
        $table['tpl_row_edit']        = 'table_row_edit';
        $table['tpl_row_copy']        = 'table_row_copy';
        $table['tpl_row_delete']      = 'table_row_delete';
        $table['tpl_row_save']        = 'table_row_save';
        $table['tpl_row_undo']        = 'table_row_undo';
        $table['tpl_row_show']        = 'table_row_show';
        $table['tpl_row_export']      = 'table_row_export';
        $table['tpl_row_import']      = 'table_row_import';
        $table['tpl_row_download']    = 'table_row_download';
        $table['tpl_row_print']       = 'table_row_print';

        if ($this->_multi_page_select) {
            $table['tpl_haeder_select'] = 'table_haeder_select-multi';
            $table['tpl_row_select']    = 'table_row_select-multi';
        }

        $this->_tabel_tpls = $table;
    }

    /**
     * Überschreibt ein Tabellen-Template gezielt.
     *
     * @param string $tid Interner Template-Key
     * @param string $tpl Template-Datei
     *
     * @return void
     */
    public function set_tabel_tpl($tid, $tpl) {
        $this->_tabel_tpls[$tid] = $tpl;
    }

    /**
     * Liefert die Basis-Action-URL des aktuellen Reports.
     *
     * Primär wird $_action verwendet.
     * Falls diese leer ist, wird eine Basis-URL aus dem aktuellen
     * Modul-/Run-Kontext aufgebaut.
     *
     * @return string
     */
    protected function get_report_action_url() {
        if ($this->_action) {
            return $this->_action;
        }

        $modul = $this->_dbx_modul;
        $run1  = $this->_dbx_action;
        $run2  = $this->_dbx_work;

        if (!$run1) {
            $run1 = dbx()->get_modul_var('dbx_run1', 0);
        }

        if (!$run2) {
            $run2 = dbx()->get_modul_var('dbx_run2', 0);
        }

        $url = '?dbx_modul=' . $modul;

        if ($run1) {
            $url .= '&dbx_run1=' . $run1;
        }

        if ($run2) {
            $url .= '&dbx_run2=' . $run2;
        }

        return $url;
    }

    /**
     * Normalisiert einen Multi-Select-Key.
     *
     * @param mixed $rid
     *
     * @return string
     */
    protected function normalize_multi_select_key($rid) {
        if ($rid === null) {
            return '';
        }

        $rid = trim((string) $rid);

        if ($rid === '') {
            return '';
        }

        return $rid;
    }

    /**
     * Liefert die sauber ermittelte Record-ID eines Datensatzes.
     *
     * Reihenfolge:
     * 1. $_fld_id
     * 2. rid
     * 3. id
     * 4. recnum
     * 5. Default
     *
     * @param mixed $record
     * @param mixed $default
     *
     * @return mixed
     */
    protected function get_record_rid($record, $default = -1) {
        if (!is_array($record)) {
            return $default;
        }

        if ($this->_fld_id && array_key_exists($this->_fld_id, $record)) {
            return $record[$this->_fld_id];
        }

        if (array_key_exists('rid', $record)) {
            return $record['rid'];
        }

        if (array_key_exists('id', $record)) {
            return $record['id'];
        }

        if (array_key_exists('recnum', $record)) {
            return $record['recnum'];
        }

        return $default;
    }

    /**
     * Liefert den reportbezogenen Select-Key eines Datensatzes.
     *
     * @param mixed $record
     *
     * @return string
     */
    protected function get_record_select_key($record) {
        $rid = $this->get_record_rid($record, '');

        return $this->normalize_multi_select_key($rid);
    }

    /**
     * Parst eine ID-Liste in ein sauberes eindeutiges Array.
     *
     * Unterstützt:
     * - Array
     * - Pipe-Liste
     * - CSV
     * - einfache gemischte Trenner
     *
     * @param mixed $raw
     *
     * @return array
     */
    protected function parse_multi_select_ids($raw) {
        $ids = array();

        if (is_array($raw)) {
            foreach ($raw as $value) {
                $key = $this->normalize_multi_select_key($value);

                if ($key !== '') {
                    $ids[$key] = 1;
                }
            }

            return array_keys($ids);
        }

        $raw = trim((string) $raw);

        if ($raw === '') {
            return array();
        }

        $parts = preg_split('/[|,\s;]+/', $raw);

        if (!is_array($parts)) {
            return array();
        }

        foreach ($parts as $value) {
            $key = $this->normalize_multi_select_key($value);

            if ($key !== '') {
                $ids[$key] = 1;
            }
        }

        return array_keys($ids);
    }

    /**
     * Fügt mehrere IDs zur Auswahl hinzu.
     *
     * @param array $ids
     *
     * @return void
     */
    public function set_multi_select_ids(array $ids) {
        $selects = $this->get_multi_selects();

        foreach ($ids as $rid) {
            $key = $this->normalize_multi_select_key($rid);

            if ($key !== '') {
                $selects[$key] = 1;
            }
        }

        $this->set_multi_selects($selects);
    }

    /**
     * Entfernt mehrere IDs aus der Auswahl.
     *
     * @param array $ids
     *
     * @return void
     */
    public function del_multi_select_ids(array $ids) {
        $selects = $this->get_multi_selects();

        foreach ($ids as $rid) {
            $key = $this->normalize_multi_select_key($rid);

            if ($key !== '' && isset($selects[$key])) {
                unset($selects[$key]);
            }
        }

        $this->set_multi_selects($selects);
    }

    /**
     * Liefert die aktuell im Report sichtbaren Select-IDs.
     *
     * @return array
     */
    public function get_visible_multi_select_ids() {
        $ids = array();

        if (!is_array($this->_rdata)) {
            return array();
        }

        foreach ($this->_rdata as $record) {
            $key = $this->get_record_select_key($record);

            if ($key !== '') {
                $ids[$key] = 1;
            }
        }

        return array_keys($ids);
    }

    /**
     * Liefert den Multi-Select-Zustand relativ zu den aktuell sichtbaren IDs.
     *
     * @param array $visibleIds
     *
     * @return array
     */
    public function get_visible_multi_select_state(array $visibleIds = array()) {
        if (!$visibleIds) {
            $visibleIds = $this->get_visible_multi_select_ids();
        }

        $selects = $this->get_multi_selects();
        $selectedVisible = array();

        foreach ($visibleIds as $rid) {
            $key = $this->normalize_multi_select_key($rid);

            if ($key !== '' && isset($selects[$key])) {
                $selectedVisible[] = $key;
            }
        }

        $visibleCount  = count($visibleIds);
        $selectedCount = count($selectedVisible);
        $headerState   = 'none';

        if ($visibleCount > 0 && $selectedCount === $visibleCount) {
            $headerState = 'all';
        } elseif ($selectedCount > 0) {
            $headerState = 'partial';
        }

        return array(
            'visible_ids'          => array_values($visibleIds),
            'selected_ids_visible' => array_values($selectedVisible),
            'visible_count'        => $visibleCount,
            'selected_count'       => $selectedCount,
            'header_state'         => $headerState,
            'header_checked'       => ($headerState === 'all') ? 1 : 0,
        );
    }

    /**
     * Sendet einen JSON-Response für Multi-Select-AJAX.
     *
     * @param array  $visibleIds
     * @param string $dbx_do
     *
     * @return void
     */
    protected function send_multi_select_json_response(array $visibleIds = array(), $dbx_do = '') {
        $state = $this->get_visible_multi_select_state($visibleIds);

        $response = array(
            'ok'                     => 1,
            'dbx_do'                 => $dbx_do,
            'count_selects'          => $this->get_count_selects(),
            'visible_ids'            => $state['visible_ids'],
            'selected_ids_visible'   => $state['selected_ids_visible'],
            'visible_count'          => $state['visible_count'],
            'visible_selected_count' => $state['selected_count'],
            'header_state'           => $state['header_state'],
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Liefert einen stabilen Remember-Key für reportbezogene Zustände.
     *
     * @param string $suffix
     *
     * @return string
     */
    protected function get_report_remember_key(string $suffix): string {
        $modul = $this->_dbx_modul ?: 'dbx';
        $fid   = $this->_fid ?: 'report';

        return 'dbx.report.' . $modul . '.' . $fid . '.' . $suffix;
    }

    /**
     * Liest einen Systemwert aus Data/Sys/PostGet.
     *
     * @param string $name
     * @param mixed  $default
     * @param string $validate
     *
     * @return mixed
     */
    public function get_sys($name, $default = '', $validate = 'parameter') {
        if (isset($this->_data[$name])) {
            $default = $this->_data[$name];
        }

        if (isset($this->_sys[$name])) {
            $default = $this->_sys[$name];
        }

        $value = dbx()->get_request_var($name, $default, $validate);

        return $value;
    }

    /**
     * Prüft, ob eine Zeile aktuell im Multi-Select aktiv ist.
     *
     * @param mixed $rid
     *
     * @return string
     */
    public function check_is_multiselect($rid) {
        $checked = '';
        $key     = $this->normalize_multi_select_key($rid);
        $selects = $this->get_multi_selects();

        if ($key !== '' && isset($selects[$key])) {
            $checked = 'checked="checked"';
        }

        return $checked;
    }

    /**
     * Fügt eine ID oder alle aktuell geladenen Reportzeilen zur Mehrfachauswahl hinzu.
     *
     * @param mixed $rid
     *
     * @return void
     */
    public function set_multi_select($rid) {
        $selects = $this->get_multi_selects();

        if ($rid == '*') {
            foreach ($this->_rdata as $record) {
                $key = $this->get_record_select_key($record);

                if ($key !== '') {
                    $selects[$key] = 1;
                }
            }
        } else {
            $key = $this->normalize_multi_select_key($rid);

            if ($key !== '') {
                $selects[$key] = 1;
            }
        }

        $this->set_multi_selects($selects);
    }

    /**
     * Entfernt eine ID oder alle IDs aus der Mehrfachauswahl.
     *
     * @param mixed $rid
     *
     * @return void
     */
    public function del_multi_select($rid) {
        $selects = $this->get_multi_selects();

        if ($rid != '*') {
            $key = $this->normalize_multi_select_key($rid);

            if ($key !== '' && isset($selects[$key])) {
                unset($selects[$key]);
            }
        } else {
            $selects = array();
        }

        $this->set_multi_selects($selects);
    }

    /**
     * Speichert die komplette Mehrfachauswahl per Remember.
     *
     * @param array $selects
     *
     * @return void
     */
    public function set_multi_selects($selects) {
        $normalized = array();

        if (is_array($selects)) {
            foreach ($selects as $rid => $value) {
                $key = $this->normalize_multi_select_key($rid);

                if ($key !== '') {
                    $normalized[$key] = 1;
                }
            }
        }

        $key = $this->get_report_remember_key('multi_select');
        dbx()->set_remember_var($key, $normalized, 'dbx');
        $this->_count_selects = -1;
    }

    /**
     * Entfernt IDs aus dem Remember-basierten Multi-Select.
     *
     * @param mixed $id
     *
     * @return void
     */
    public function del_multi_selects($id) {
        if ($id == '*') {
            $this->set_multi_selects(array());
            return;
        }

        $ids = $this->get_multi_selects();
        $key = $this->normalize_multi_select_key($id);

        if ($key !== '' && isset($ids[$key])) {
            unset($ids[$key]);
        }

        $this->set_multi_selects($ids);
    }

    /**
     * Liest die aktuelle Mehrfachauswahl.
     *
     * @return array
     */
    public function get_multi_selects(): array {
        $key     = $this->get_report_remember_key('multi_select');
        $selects = dbx()->get_remember_var($key, array(), 'dbx');

        if (!is_array($selects)) {
            $selects = array();
        }

        $normalized = array();

        foreach ($selects as $rid => $value) {
            $key = $this->normalize_multi_select_key($rid);

            if ($key !== '') {
                $normalized[$key] = 1;
            }
        }

        return $normalized;
    }

    /**
     * Liefert die Anzahl aktuell selektierter Zeilen.
     *
     * @return int
     */
    public function get_count_selects() {
        $count = $this->_count_selects;

        if ($count == -1) {
            $selects = $this->get_multi_selects();
            $count   = is_array($selects) ? count($selects) : 0;
            $this->_count_selects = $count;
        }

        return $count;
    }

    /**
     * Löscht ausgewählte Datensätze.
     *
     * @param string $dd
     * @param mixed  $rid
     *
     * @return int
     */
    public function del_selected($dd, $rid = 0) {
        $ok  = 0;
        $err = 0;
        dbx()->set_modul_var('dbx_no_reset',  1);
        $db = dbx()->get_system_obj('dbxDB');

        if ($rid == '*') {
            $selected = $this->get_multi_selects();

            foreach ($selected as $id => $sel) {
                $ok = $db->delete($dd, $id);

                if (!$ok) {
                    $err++;
                }

                $this->del_multi_select($id);
            }

            if ($err) {
                $ok = ($err * -1);
            }
        } else {
            if ($rid) {
                $ok = $db->delete($dd, $rid);
                $this->del_multi_select($rid);
            }
        }

        return $ok;
    }

    /**
     * Ergänzt eine WHERE-Bedingung um aktuell selektierte IDs.
     *
     * @param string $rwhere
     *
     * @return string
     */
    public function add_rwhere_select($rwhere) {
        $selects = $this->get_multi_selects();
        $fld_id  = $this->_fld_id ? $this->_fld_id : 'id';

        if (!is_array($selects) || !count($selects)) {
            if ($rwhere > '') {
                $rwhere .= ' and (1=0)';
            }

            if ($rwhere == '') {
                $rwhere .= '1=0';
            }

            return $rwhere;
        }

        $values = array();

        foreach ($selects as $id => $sel) {
            $key = $this->normalize_multi_select_key($id);

            if ($key === '') {
                continue;
            }

            if (preg_match('/^-?\d+$/', $key)) {
                $values[] = (string) ((int) $key);
            } else {
                $values[] = "'" . addslashes($key) . "'";
            }
        }

        if (!count($values)) {
            if ($rwhere > '') {
                $rwhere .= ' and (1=0)';
            }

            if ($rwhere == '') {
                $rwhere .= '1=0';
            }

            return $rwhere;
        }

        $selectWhere = $fld_id . ' IN (' . implode(',', $values) . ')';

        if ($rwhere > '') {
            $rwhere .= ' and (' . $selectWhere . ')';
        } else {
            $rwhere = $selectWhere;
        }

        return $rwhere;
    }

    /**
     * Ergänzt eine Such-WHERE über eine Feldliste.
     *
     * @param string $sql
     * @param string $suchWert
     * @param string $feldListe
     *
     * @return string
     */
    public function add_rwhere_search($sql, $suchWert, $feldListe) {
        if ($suchWert === null) {
            $suchWert = '';
        }

        $suchWert = str_replace(array('\'', '"', '\\', '%'), '', $suchWert);
        $suchWert = filter_var($suchWert, FILTER_SANITIZE_SPECIAL_CHARS);

        $datum = DateTime::createFromFormat('d.m.Y', $suchWert);

        if ($datum && $datum->format('d.m.Y') === $suchWert) {
            $suchWert = $datum->format('Y-m-d');
        }

        $felder = explode(',', $feldListe);
        $sql   .= ' AND (';
        $bedingungen = array();

        foreach ($felder as $feld) {
            $feld = trim($feld);

            if ($feld === '') {
                continue;
            }

            $bedingungen[] = "$feld LIKE '$suchWert%'";
        }

        $sql .= implode(' OR ', $bedingungen);
        $sql .= ')';

        return $sql;
    }

    /**
     * Verarbeitet AJAX-/Select-Aktionen für Report-Mehrfachauswahl.
     *
     * Unterstützt:
     * - neue Wege über dbx_do
     * - Legacy-Wege über dbx_mode
     *
     * Rückgabewerte:
     * - 0
     * - handled
     * - count_response
     * - add
     * - rem
     *
     * @return mixed
     */
    public function set_form_selects() {
        $dbx_do   = dbx()->get_request_var('dbx_do', '', 'parameter');
        $dbx_mode = dbx()->get_request_var('dbx_mode', '', 'parameter');
        $checked  = dbx()->get_request_var('dbx_checked', '', 'parameter');
        $value    = dbx()->get_request_var('dbx_value', 0, 'parameter+.');
        $ajax     = dbx()->get_request_var('dbx_ajax', 0, 'int');
        $nor      = dbx()->get_modul_var('dbx_no_reset', 0, 'int');

        if ($dbx_do === '' && $dbx_mode !== '') {
            $dbx_do = $dbx_mode;
        }

        if ($dbx_do === 'row_select') {
            $state      = dbx()->get_request_var('dbx_select_state', 0, 'int');
            $rid        = dbx()->get_request_var('rid', '', 'parameter+.');
            $selectId   = dbx()->get_request_var('dbx_select_id', '', 'parameter+.');
            $visibleIds = dbx()->get_request_var('dbx_select_visible_ids', '', '*');

            if ($selectId !== '') {
                $rid = $selectId;
            }

            $rid = $this->normalize_multi_select_key($rid);

            if ($rid !== '') {
                if ($state) {
                    $this->set_multi_select($rid);
                } else {
                    $this->del_multi_select($rid);
                }
            }

            if ($ajax) {
                $this->send_multi_select_json_response(
                    $this->parse_multi_select_ids($visibleIds),
                    $dbx_do
                );
            }

            return 'handled';
        }

        if ($dbx_do === 'rows_select') {
            $state      = dbx()->get_request_var('dbx_select_state', 0, 'int');
            $idsRaw     = dbx()->get_request_var('dbx_select_ids', '', '*');
            $visibleRaw = dbx()->get_request_var('dbx_select_visible_ids', '', '*');
            $ids        = $this->parse_multi_select_ids($idsRaw);
            $visibleIds = $this->parse_multi_select_ids($visibleRaw);

            if (!$visibleIds) {
                $visibleIds = $ids;
            }

            if ($state) {
                $this->set_multi_select_ids($ids);
            } else {
                $this->del_multi_select_ids($ids);
            }

            if ($ajax) {
                $this->send_multi_select_json_response($visibleIds, $dbx_do);
            }

            return 'handled';
        }

        if ($dbx_do === 'clear_selects') {
            $this->set_multi_selects(array());

            if ($ajax) {
                $this->send_multi_select_json_response(array(), $dbx_do);
            }

            return 'handled';
        }

        if (!$nor) {
            if ($dbx_mode == 'reset_form_select') {
                $this->set_multi_selects(array());
                return 'count_response';
            }

            if ($dbx_mode == 'save_form_select' && $value && $ajax) {
                $selects = $this->get_multi_selects();
                $value   = $this->normalize_multi_select_key($value);

                if ($value !== '') {
                    if (!$checked || $checked == 'false') {
                        if (isset($selects[$value])) {
                            unset($selects[$value]);
                        }
                    } else {
                        $selects[$value] = 1;
                    }

                    $this->set_multi_selects($selects);
                }

                return 'count_response';
            }
        } else {
            if ($checked) {
                return 'add';
            }

            if (!$checked || $checked == 'false') {
                return 'rem';
            }
        }

        return 0;
    }

    /**
     * Formatiert einen Reportwert anhand der Felddefinition.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function rpt_format($key, $value) {
        $format = $this->_rpt_format;

        if (is_array($format)) {
            if (isset($format[$key])) {
                $reform = $format[$key];

                if ($reform == 'php-date-usr') {
                    $value = $this->php_date_usr($value);
                }

                if ($reform == 'php-datetime-usr') {
                    $value = $this->php_datetime_usr($value);
                }

                if ($reform == 'html-chars') {
                    $value = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                }
            }
        } else {
            if ($value === null) {
                $value = '';
            }

            if ($format == 'html-chars' && $value) {
                $value = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }
        }

        if ($value === null) {
            $value = '';
        }

        return $value;
    }

    /**
     * Fügt reportweite Platzhalter und Report-Objekte in einen String ein.
     *
     * @param string $content
     *
     * @return string
     */
    public function rpt_merge_obj($content) {
        $count_select = $this->get_count_selects();
        $count_cols   = $this->_table_col_count;
        $page         = $this->_current_page;
        $page_break   = $this->_page_break;

        $content = str_replace('{rpt:count_sel}', $count_select, $content);
        $content = str_replace('{rpt:col_count}', $count_cols, $content);
        $content = str_replace('{rpt:page}', $page, $content);
        $content = str_replace('{rpt:pagebrak}', $page_break, $content);

        if (is_array($this->_obj)) {
            foreach ($this->_obj as $key => $value) {
                $xkey = '{obj:' . $key . '}';

                if ($value === null) {
                    $value = '';
                }

                $content = str_replace($xkey, $value, $content);
            }
        }

        return $content;
    }

    public function forward_run_report_haeder($content) {
        return $this->rpt_merge_obj($content);
    }

    public function forward_run_page_haeder($content) {
        return $this->rpt_merge_obj($content);
    }

    public function forward_run_page_footer($content) {
        return $this->rpt_merge_obj($content);
    }

    public function run_report_haeder($content) {
        return $this->forward_run_report_haeder($content);
    }

    public function run_page_haeder($content) {
        return $this->forward_run_page_haeder($content);
    }

    public function run_page_footer($content) {
        return $this->forward_run_page_footer($content);
    }

    public function forward_run_report_footer($content) {
        return $this->rpt_merge_obj($content);
    }

    public function run_report_footer($content) {
        return $this->forward_run_report_footer($content);
    }

    public function run_haeder($content) {
        return $this->forward_run_haeder($content);
    }

    public function run_body($content) {
        return $this->forward_run_body($content);
    }

    public function run_footer($content) {
        return $this->forward_run_footer($content);
    }

    public function forward_run_haeder($content) {
        return $this->rpt_merge_obj($content);
    }

    public function forward_get_footer_page() {
        $footer  = $this->_footer_next_page;
        $footer .= $this->_footer_page;
        $footer  = $this->rpt_merge_obj($footer);

        return $footer;
    }

    public function forward_get_haeder_page() {
        $haeder  = $this->_haeder_page;
        $haeder .= $this->_haeder_next_page;
        $haeder  = $this->get_report_haeder($haeder);
        $haeder  = $this->rpt_merge_obj($haeder);

        return $haeder;
    }

    public function get_footer_page() {
        return $this->forward_get_footer_page();
    }

    public function get_haeder_page() {
        return $this->forward_get_haeder_page();
    }

    /**
     * Liefert eine Body-Klasse.
     *
     * @param string $xkey
     *
     * @return string
     */
    private function get_class_body($xkey) {
        $class = $xkey;

        if (isset($this->_class_body[$xkey])) {
            $class = $this->_class_body[$xkey];
        }

        return $class;
    }

    public function forward_run_body($content) {
        $page_footer = '';
        $page_haeder = '';
        $rpt         = $this->_fid;

        if ($rpt != 'pagination') {
            $this->_current_report_ln++;
            $this->_current_page_ln++;

            $pn = $this->_current_page;
            $ln = $this->_current_page_ln;

            if ($pn == 1) {
                $max = $this->_first_page_lines;
            } else {
                $max = $this->_next_page_lines;
            }

            if ($ln > $max) {
                $page_footer            = $this->get_footer_page();
                $this->_current_page_ln = 1;
                $page_haeder            = $this->get_haeder_page();
            }

            $content = $this->rpt_merge_obj($content);
        }

        return $page_footer . $page_haeder . $content;
    }

    public function forward_run_footer($content) {
        return $this->rpt_merge_obj($content);
    }

    public function set_class_haeder($key, $class = '') {
        if (!$class) {
            $class = 'th-' . $key;
        }

        $this->_class_haeder[$key] = $class;
    }

    private function get_class_haeder($key) {
        $class = '';

        if (is_array($this->_class_haeder)) {
            if (isset($this->_class_haeder[$key])) {
                $class = $this->_class_haeder[$key];
            }
        }

        if (!$class) {
            $class = 'th-' . $key;
        }

        return $class;
    }

    public function set_style_haeder($key, $style) {
        $this->_style_haeder[$key] = $style;
    }

    private function get_style_haeder($key) {
        $style = '';

        if (is_array($this->_style_haeder)) {
            if (isset($this->_style_haeder[$key])) {
                $style = $this->_style_haeder[$key];
            }
        }

        return $style;
    }

    /**
     * Liefert die aktivierten Tabellen-Aktionsdefinitionen.
     *
     * Die Definition existiert zentral nur einmal und wird anschließend
     * sowohl für Header als auch für Row-Buttons verwendet.
     *
     * @return array
     */
    protected function get_table_action_definitions() {
        return array(
            array(
                'type'       => 'expander',
                'enabled'    => (bool) $this->_data_table,
                'header_tpl' => 'tpl_haeder_expander',
                'row_tpl'    => 'tpl_row_expander',
            ),
            array(
                'type'       => 'edit',
                'enabled'    => (bool) $this->_create_row_edit,
                'header_tpl' => 'tpl_haeder_edit',
                'row_tpl'    => 'tpl_row_edit',
            ),
            array(
                'type'       => 'copy',
                'enabled'    => (bool) $this->_create_row_copy,
                'header_tpl' => 'tpl_haeder_copy',
                'row_tpl'    => 'tpl_row_copy',
            ),
            array(
                'type'       => 'show',
                'enabled'    => (bool) $this->_create_row_show,
                'header_tpl' => 'tpl_haeder_show',
                'row_tpl'    => 'tpl_row_show',
            ),
            array(
                'type'       => 'export',
                'enabled'    => (bool) $this->_create_row_export,
                'header_tpl' => 'tpl_haeder_export',
                'row_tpl'    => 'tpl_row_export',
            ),
            array(
                'type'       => 'import',
                'enabled'    => (bool) $this->_create_row_import,
                'header_tpl' => 'tpl_haeder_import',
                'row_tpl'    => 'tpl_row_import',
            ),
            array(
                'type'       => 'download',
                'enabled'    => (bool) $this->_create_row_download,
                'header_tpl' => 'tpl_haeder_download',
                'row_tpl'    => 'tpl_row_download',
            ),
            array(
                'type'       => 'delete',
                'enabled'    => (bool) $this->_create_row_delete,
                'header_tpl' => 'tpl_haeder_delte',
                'row_tpl'    => 'tpl_row_delete',
            ),
            array(
                'type'       => 'print',
                'enabled'    => (bool) $this->_create_row_print,
                'header_tpl' => 'tpl_haeder_print',
                'row_tpl'    => 'tpl_row_print',
            ),
        );
    }

    /**
     * Rendert den zentralen Header-Buttonblock.
     *
     * @return array
     */
    protected function render_table_header_action_block() {
        $html  = '';
        $count = 0;

        foreach ($this->get_table_action_definitions() as $def) {
            if (!$def['enabled']) {
                continue;
            }

            $file = $this->_tabel_tpls[$def['header_tpl']];
            $tpl  = $this->get_tpl($file, array(
                'name'  => 'ID',
                'class' => 'no-sort',
            ));

            $html .= $tpl . "\n";
            $count++;
        }

        return array(
            'html'  => $html,
            'count' => $count,
        );
    }

    /**
     * Baut das Standard-Datenarray für Row-Aktions-Templates.
     *
     * @param string $type
     * @param array  $record
     *
     * @return array
     */
    protected function get_table_row_action_data($type, array $record) {
        $rid = $this->get_record_rid($record, -1);
        $dat = array(
            'rid'     => $rid,
            'value'   => $rid,
            'action'  => $this->get_report_action_url(),
            'class'   => 'no-sort',
            'tooltip' => '',
        );

        if ($type === 'download') {
            $dat['href_dir_file'] = dbx()->get_modul_var('href_dir_file', '', '*');
        }

        if ($type === 'copy') {
            $dat['confirm'] = $this->_msg_confirm_copy;
        }

        if ($type === 'delete') {
            $dat['confirm'] = $this->_msg_confirm_delete;
        }

        return $dat;
    }

    /**
     * Rendert den zentralen Row-Buttonblock.
     *
     * @param array $record
     *
     * @return string
     */
    protected function render_table_row_action_block(array $record) {
        $html = '';

        foreach ($this->get_table_action_definitions() as $def) {
            if (!$def['enabled']) {
                continue;
            }

            $file = $this->_tabel_tpls[$def['row_tpl']];
            $dat  = $this->get_table_row_action_data($def['type'], $record);
            $tpl  = $this->get_tpl($file, $dat);

            $html .= $tpl . "\n";
        }

        return $html;
    }

    /**
     * Rendert die Header-Checkbox für die sichtbaren Rows.
     *
     * @param array  $auto_flds
     * @param string $fld_id
     * @param string $class
     * @param array  $select_state
     *
     * @return string
     */
    protected function render_table_header_select(array $auto_flds, $fld_id, $class, array $select_state) {
        $file    = $this->_tabel_tpls['tpl_haeder_select'];
        $name    = isset($auto_flds[$fld_id]) ? $auto_flds[$fld_id] : 'xID';
        $checked = '';

        if (!empty($select_state['header_checked'])) {
            $checked = 'checked="checked"';
        }

        return $this->get_tpl($file, array(
            'name'         => $name,
            'checked'      => $checked,
            'class'        => $class,
            'header_state' => isset($select_state['header_state']) ? $select_state['header_state'] : 'none',
        ));
    }

    /**
     * Rendert die Row-Checkbox.
     *
     * @param array  $record
     * @param string $class
     *
     * @return string
     */
    protected function render_table_row_select(array $record, $class) {
        $file    = $this->_tabel_tpls['tpl_row_select'];
        $name    = $this->_fid . '_select';
        $rid     = $this->get_record_rid($record, -1);
        $checked = $this->check_is_multiselect($rid);

        if ($checked) {
            $this->_post[$name] = 1;
        }

        return $this->get_tpl($file, array(
            'name'    => $name,
            'value'   => $rid,
            'rid'     => $rid,
            'checked' => $checked,
            'class'   => $class,
            'tooltip' => '',
        ));
    }

    /**
     * Rendert die automatischen Header-Datenspalten.
     *
     * @param array  $auto_flds
     * @param string $fld_id
     *
     * @return array
     */
    protected function render_table_header_data_columns(array $auto_flds, $fld_id) {
        $html  = '';
        $count = 0;

        foreach ($auto_flds as $key => $value) {
            $skip = 0;

            if ($this->_create_row_select && $key == $fld_id) {
                $skip = 1;
            }

            if (!$skip && $value > '') {
                $file  = $this->_tabel_tpls['tpl_haeder_col'];
                $class = $this->get_class_haeder($key);
                $style = $this->get_style_haeder($key);

                $tpl = $this->get_tpl($file, array(
                    'value' => $value,
                    'name'  => $key,
                    'class' => $class,
                    'style' => $style,
                ));

                $html .= $tpl . "\n";
                $count++;
            }
        }

        return array(
            'html'  => $html,
            'count' => $count,
        );
    }

    /**
     * Rendert die automatischen Body-Datenspalten.
     *
     * @param array  $record
     * @param array  $auto_flds
     * @param string $fld_id
     * @param string $defaultClass
     *
     * @return string
     */
    protected function render_table_row_data_columns(array $record, array $auto_flds, $fld_id, $defaultClass = 'auto-fld') {
        $html = '';

        foreach ($auto_flds as $no => $key) {
            $xkey  = '';
            $value = '-?-';
            $label = $auto_flds[$no];
            $skip  = 0;

            if (isset($record[$key])) {
                $xkey = $key;
            } elseif (isset($record[$no])) {
                $xkey = $no;
            }

            if ($this->_create_row_select && $xkey == $fld_id) {
                $skip = 1;
            }

            if (!$skip && $label > '') {
                if ($xkey) {
                    $value = $record[$xkey];
                    $value = $this->rpt_format($xkey, $value);
                }

                $class = $defaultClass;

                if ($defaultClass !== 'auto-fld') {
                    $class = $this->get_class_body($xkey);
                }

                $tpl = $this->get_tpl($this->_tabel_tpls['tpl_row_col'], array(
                    'value'   => $value,
                    'class'   => $class,
                    'tooltip' => '',
                ));

                $html .= $tpl . "\n";
            }
        }

        return $html;
    }

    /**
     * Erzeugt den Report-Header.
     *
     * @param string $content
     *
     * @return string
     */
    public function get_report_haeder($content = '') {
        if (!$content) {
            $content = $this->_haeder;
        }

        $this->_current_page++;
        $col_count    = 0;
        $auto_flds    = $this->_auto_flds;
        $auto_mode    = $this->_auto_mode;
        $select_state = $this->get_visible_multi_select_state();

        if (!is_array($auto_flds)) {
            if (is_string($auto_flds) && $auto_flds !== '') {
                $auto_flds = explode(',', $auto_flds);
            } else {
                $auto_flds = array();
            }
        }

        $pos = strpos($content, '[rpt:row]');

        if ($pos !== false) {
            $row    = '';
            $fld_id = $this->_fld_id;

            if ($auto_mode == 'table' && is_array($auto_flds)) {
                $buttonBlock = $this->render_table_header_action_block();
                $columnBlock = $this->render_table_header_data_columns($auto_flds, $fld_id);

                if ($this->_table_buttons != 'left') {
                    if ($this->_create_row_select) {
                        $row .= $this->render_table_header_select(
                            $auto_flds,
                            $fld_id,
                            $this->get_class_haeder(isset($auto_flds[$fld_id]) ? $auto_flds[$fld_id] : 'xID'),
                            $select_state
                        ) . "\n";
                        $col_count++;
                    }

                    $row      .= $columnBlock['html'];
                    $col_count += $columnBlock['count'];

                    $row      .= $buttonBlock['html'];
                    $col_count += $buttonBlock['count'];
                } else {
                    $row      .= $buttonBlock['html'];
                    $col_count += $buttonBlock['count'];

                    if ($this->_create_row_select) {
                        $row .= $this->render_table_header_select(
                            $auto_flds,
                            $fld_id,
                            'no-sort',
                            $select_state
                        ) . "\n";
                        $col_count++;
                    }

                    $row      .= $columnBlock['html'];
                    $col_count += $columnBlock['count'];
                }

                $this->_table_col_count = $col_count;
            }

            $content = str_replace('[rpt:row]', $row, $content);
        }

        $content = $this->run_haeder($content);

        return $content;
    }

    /**
     * Erzeugt den HTML-Body für klassische Reportmodi.
     *
     * @return string
     */
    public function get_report_body(): string {
        $content   = '';
        $line      = '';
        $loop      = 0;

        $auto_flds = $this->_auto_flds;
        $auto_mode = $this->_auto_mode;

        if (!is_array($auto_flds)) {
            if (is_string($auto_flds) && $auto_flds !== '') {
                $auto_flds = explode(',', $auto_flds);
            } else {
                $auto_flds = array();
            }
        }

        if (is_array($this->_rdata)) {
            foreach ($this->_rdata as $recnum => $record) {
                $loop++;
                $line = $this->_body;

                $this->_record = $record;
                $line          = $this->run_body($line);
                $record        = $this->_record;

                $fld_id = $this->_fld_id;
                $pos    = strpos($line, '[rpt:row]');

                if ($pos !== false && $this->_rdata_inline) {
                    $inline = $this->_body_inline;
                    return str_replace('[rpt:row]', $inline, $line);
                }

                if ($pos !== false) {
                    $row = '';

                    if ($auto_mode == 'table' && is_array($auto_flds)) {
                        $buttonBlock = $this->render_table_row_action_block($record);

                        if ($this->_table_buttons != 'left') {
                            if ($this->_create_row_select) {
                                $row .= $this->render_table_row_select($record, 'no-sort') . "\n";
                            }

                            $row .= $this->render_table_row_data_columns($record, $auto_flds, $fld_id, 'auto-fld');
                            $row .= $buttonBlock;
                        } else {
                            $row .= $buttonBlock;

                            if ($this->_create_row_select) {
                                $row .= $this->render_table_row_select($record, 'no-sort') . "\n";
                            }

                            $row .= $this->render_table_row_data_columns($record, $auto_flds, $fld_id, 'body');
                        }
                    }

                    $line = str_replace('[rpt:row]', $row, $line);
                }

                $tr_class  = $this->get_class_tr($record);
                $tr_class .= ($loop % 2 != 0) ? ' odd' : ' even';

                $line = str_replace('{tr-class}', $tr_class, $line);
                $pos  = strpos($line, '{');

                if ($pos !== false && is_array($record)) {
                    foreach ($record as $field => $value) {
                        $field_name = '{' . $field . '}';
                        $value      = $this->rpt_format($field, $value);

                        if (!is_array($value)) {
                            if ($value === null) {
                                $value = '';
                            }

                            $line = str_replace($field_name, $value, $line);
                        }
                    }
                }

                $col_count = $this->_table_col_count;
                $line      = str_replace('{rpt:col_count}', $col_count, $line);

                if (strpos($line, '{r}') !== false) {
                    $r    = dbx()->next_id(1);
                    $line = str_replace('{r}', $r, $line);
                }

                $content .= $line;

                if ($this->_rdata_inline) {
                    break;
                }
            }
        }

        return $content;
    }

    public function get_class_tr($record) {
        $class    = '';
        $activ_id = $this->_activ_id;

        if (!$activ_id) {
            $activ_id = $this->get_activ_id();
        }

        if ($activ_id) {
            $key = $this->_fld_id;

            if ($key && isset($record[$key])) {
                if ($activ_id == $record[$key]) {
                    $class = 'table-active';
                }
            }
        }

        return $class;
    }

    public function get_report_footer() {
        $content   = $this->_footer;
        $col_count = $this->_table_col_count;

        $content = str_replace('{rpt:col_count}', $col_count, $content);
        $content = $this->run_footer($content);

        return $content;
    }

    public function split_tpl($report) {
        $report_part      = explode('<hr class="dbx_split">', $report);
        $report_header    = '';
        $report_body      = '';
        $report_footer    = '';
        $next_haeder_page = '';
        $next_footer_page = '';
        $count = count($report_part);

        if ($count > 0) {
            $report_body = $report_part[0];
        }

        if ($count > 1) {
            $report_header = $report_part[0];
            $report_body   = $report_part[1];
        }

        if ($count > 2) {
            $report_header    = $report_part[0];
            $report_body      = $report_part[1];
            $report_footer    = $report_part[2];
            $next_haeder_page = $report_part[0];
            $next_footer_page = $report_part[2];
        }

        if ($count > 5) {
            $next_haeder_page = $report_part[3];
            $next_footer_page = $report_part[5];
        }

        $this->_haeder           = $report_header;
        $this->_body             = $report_body;
        $this->_footer           = $report_footer;
        $this->_footer_next_page = $next_footer_page;
        $this->_haeder_next_page = $next_haeder_page;
    }

    public function get_report_pages() {
        $content = '';
        $modul   = $this->_dbx_modul;
        $action  = $this->_dbx_action;
        $rcount  = $this->_rcount;
        $link    = $this->_pagelink;
        $tpl     = $this->_tpl_pagination;

        $rpos  = $this->get_sel('dbx_rpos', 0, 'int');
        $rrows = $this->get_sel('dbx_rrows', 10, 'int');

        if (!$link) {
            $link = '?dbx_modul=' . $modul . '&dbx_run1=' . $action;
        }

        $link = $this->_action ?: $link;
        $content = $this->pagination($tpl, $link, $rpos, $rrows, $rcount);

        return $content;
    }

    private function lnk_page($p, $akt_page, $link, $rpos, $rrows, $rcount, $target) {
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
        $rec['href_page'] = $link . '&dbx_rrows=' . $rrows . '&dbx_rpos=' . (($p - 1) * $rrows) . '&dbx_target=' . $target;
        $rec['p_active']  = $p_active;
        $rec['active']    = $active;
        $rec['current']   = $current;
        $rec['class']     = $class . ' dbxAjax';

        return $rec;
    }

    private function pagination($tpl, $link, $rpos, $rrows, $rcount) {
        if ($rcount == 0 || $rrows == 0 || $rcount <= $rrows) {
            return '';
        }

        $pages = intval($rcount / $rrows);

        if ($rcount % $rrows) {
            $pages++;
        }

        if ($pages == 0 && $rcount > 0) {
            $pages = 1;
        }

        $pmax     = $this->_but_pagination;
        $akt_page = intval($rpos / $rrows) + 1;

        if ($akt_page < 1) {
            $akt_page = 1;
        }

        if ($akt_page > $pages) {
            $akt_page = $pages;
        }

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

        if ($prev < 0) {
            $prev = 0;
        }

        if ($next > $last_pos) {
            $next = $last_pos;
        }

        $i      = $this->_next_i;
        $target = 'dbx_target_' . $i;

        $href_first = $link . '&dbx_rpos=0&dbx_rrows=' . $rrows . '&dbx_target=' . $target;
        $href_last  = $link . '&dbx_rpos=' . $last_pos . '&dbx_rrows=' . $rrows . '&dbx_target=' . $target;
        $href_prev  = $link . '&dbx_rpos=' . $prev . '&dbx_rrows=' . $rrows . '&dbx_target=' . $target;
        $href_next  = $link . '&dbx_rpos=' . $next . '&dbx_rrows=' . $rrows . '&dbx_target=' . $target;

        $this->_sys['dbx_rpos']  = $rpos;
        $this->_sys['dbx_rrows'] = $rrows;

        $dv = array();
        $dv['dbx_rpos']  = $rpos;
        $dv['dbx_rrows'] = $rrows;
        $rdata = array();

        for ($p = $p_s; $p <= $p_e; $p++) {
            $rdata[] = $this->lnk_page($p, $akt_page, $link, $rpos, $rrows, $rcount, $target);

            if ($p >= $pages) {
                break;
            }
        }

        $oReport = dbx()->get_system_obj('dbxReport');

        $oReport->init('pagination');
        $oReport->_data            = $dv;
        $oReport->_dbx_modul       = 'dbx';
        $oReport->_dbx_action      = 'pagination';
        $oReport->_dbx_modul_id    = 888;
        $oReport->_rdata           = $rdata;
        $oReport->_rcount          = $rcount;
        $oReport->_rrows           = $rrows;
        $oReport->_rpos            = $rpos;
        $oReport->_action          = $link;
        $oReport->_tpl             = $tpl;
        $oReport->_pages           = 0;
        $oReport->_body_inline     = false;
        $oReport->_create_sel_flds = 0;

        $content = $oReport->run(0, '', 'table');

        $content = str_replace('{href_first}', $href_first, $content);
        $content = str_replace('{href_last}',  $href_last,  $content);
        $content = str_replace('{href_prev}',  $href_prev,  $content);
        $content = str_replace('{href_next}',  $href_next,  $content);

        return $content;
    }

    public function data_rows($data, $rpos, $rrows) {
        $rdata = array();

        for ($i = $rpos; $i < ($rpos + $rrows); $i++) {
            if (isset($data[$i])) {
                $rdata[] = $data[$i];
            } else {
                break;
            }
        }

        return $rdata;
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

    public function submit() {
        $submit = 0;
        $nor    = dbx()->get_modul_var('dbx_no_reset', 0, 'int');
        if (!$nor) {
            $submit = $this->forward_submit();
        }

        return $submit;
    }

    public function set_sel($name, $value) {
        $_POST[$name]       = $value;
        $this->_data[$name] = $value;
        $this->_sys[$name]  = $value;
    }

    public function get_sel($name, $default = '', $rules = 'parameter') {
        $submit     = $this->submit();
        $page_reset = $this->_page_reset ? 1 : 0;
        $xdefault   = $default;
        $nor        = dbx()->get_modul_var('dbx_nor', 0, 'int');

        if ($submit && $page_reset && !$nor) {
            $this->_sys['dbx_rpos'] = 0;
        }

        if (isset($this->_data[$name])) {
            $xdefault = $this->_data[$name];
        }

        if (isset($this->_sys[$name])) {
            $xdefault = $this->_sys[$name];
        }

        if ($page_reset) {
            $danger_value = $this->get_post($name, $xdefault, '*');
            $ok           = $this->oValidator->validate($danger_value, $rules, $name);

            if (!$ok) {
                $danger_value = $default;
            }

            $value = $danger_value;
        } else {
            $value = $xdefault;
        }

        $this->_sys[$name] = $value;

        return $value;
    }

    protected function get_grid_action_url($url, $action) {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        $securedUrl = dbx()->action_url($url);
        if ($securedUrl === $url) {
            dbx()->debug(
                'dbxReport grid action blocked: convention not recognized'
                . ' action=(' . (string)$action . ') url=(' . $url . ')'
            );
            return '';
        }

        return $securedUrl;
    }

    protected function get_grid_replaces(): array {
        $gridId = $this->_grid_id;

        if (!$gridId) {
            $gridId = $this->_fid . '_grid';
        }

        $cols = $this->_grid_cols;

        if (!$cols && $this->_dd) {
            $oDB  = dbx()->get_system_obj('dbxDB');
            $cols = $oDB->get_dd_grid_cols($this->_dd);
        }

        return array(
            'read_url'      => $this->_grid_read_url,
            'save_url'      => $this->get_grid_action_url($this->_grid_save_url, 'save'),
            'delete_url'    => $this->get_grid_action_url($this->_grid_delete_url, 'delete'),
            'insert_url'    => $this->get_grid_action_url($this->_grid_insert_url, 'insert'),
            'sort_url'      => $this->get_grid_action_url($this->_grid_sort_url, 'sort'),
            'sync_url'      => $this->get_grid_action_url($this->_grid_sync_url, 'sync'),
            'print_url'     => $this->_grid_print_url,
            'export_url'    => $this->_grid_export_url,
            'grid_id'       => $gridId,
            'grid_cols'     => $cols,
            'grid_schema'   => $this->_grid_schema,
            'grid_layout'   => $this->_grid_layout,
            'grid_height'   => $this->_rrows,
            'grid_synctime' => $this->_grid_synctime,
        );
    }

    public function apply_tabulurator_request() {
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

    public function init($fid, $tpl = '', $first_page_lines = -1, $next_page_lines = -1, $current_line = -1) {
        $this->forward_init($fid, $tpl);

        if ($fid == 'pagination') {
            return;
        }

        if ($first_page_lines < 0) {
            $first_page_lines = 999999;
        }

        if ($next_page_lines < 0) {
            $next_page_lines = 999999;
        }

        if ($current_line < 0) {
            $current_line = 0;
        }

        $current_page_line = 0;
        $current_page      = 0;

        if ($current_line >= 0) {
            if ($current_line < $first_page_lines) {
                $current_page      = 1;
                $current_page_line = $current_line;
            } else {
                $remaining_lines   = $current_line - $first_page_lines;
                $current_page      = 2 + intval($remaining_lines / $next_page_lines);
                $current_page_line = ($remaining_lines % $next_page_lines);
            }

            $current_page--;
        }

        $this->_current_page      = $current_page;
        $this->_current_report_ln = $current_line;
        $this->_current_page_ln   = $current_page_line;
        $this->_first_page_lines  = $first_page_lines;
        $this->_next_page_lines   = $next_page_lines;

        $this->_fld_haeder       = '';
        $this->_haeder_report    = '';
        $this->_footer_report    = '';
        $this->_haeder_page      = '';
        $this->_footer_page      = '';
        $this->_haeder_next_page = '';
        $this->_footer_next_page = '';

        dbx()->set_remember_var('last_report_i', $this->_next_i, 'dbx');

        $retval = $this->set_form_selects();

        if ($retval === 'count_response') {
            $response = $this->get_count_selects();
            $this->fast_response($response);
        }

        if ($retval === 'add' || $retval === 'rem') {
            $this->_multi_select_work = $retval;

            $submitField = $fid . '_select';
            $selects     = $this->get_post($submitField, '', 'array|parameter');

            if (is_array($selects)) {
                foreach ($selects as $id => $sel) {
                    if ($retval == 'add') {
                        $this->set_multi_select($sel);
                    }
                }
            } else {
                if ($retval == 'rem') {
                    $selects   = $this->get_post('dbx_add', '', 'parameter');
                    $deselects = explode('|', $selects);

                    foreach ($deselects as $id => $sel) {
                        if ($sel) {
                            $this->del_multi_select($sel);
                        }
                    }
                }
            }
        } else {
            $this->_multi_select_work = '';
        }
    }

  public function run($pages = 0, $flds = '', $mode = '') {
      $content   = '';
      $msg       = '';
      $msg_class = 'info';
      $norep     = '';

      if (!$pages) {
          $pages = $this->_pages;
      }

      if (!$mode) {
          $mode = $this->_mode;
      }

      $this->_pages = $pages;
      $this->_mode  = $mode;

      if (!is_array($flds)) {
          $flds = $this->_rflds;
      }

      $submit = $this->submit();

      $nor = dbx()->get_modul_var('dbx_no_reset', 0,'int');


      $this->_auto_flds = $flds;
      $this->_auto_mode = $mode;

      $rrows = $this->_rrows;
      $count = $this->_rcount;

      if (isset($this->_sys['dbx_rrows'])) {
          $rrows = $this->_sys['dbx_rrows'];
      }

      if ($this->_data_table == 'auto') {
          if ($count > $rrows) {
              $this->_data_table = 0;
          } else {
              $this->_data_table = 1;
          }
      }

      $i               = $this->_next_i;
      $fid             = $this->_fid;
      $tpl             = $this->_tpl;
      $msg             = $this->_msg_info;
      $create_sel_flds = $this->_create_sel_flds;

      if ($fid != 'pagination') {
          if (!is_array($this->_options_rsort) || empty($this->_options_rsort)) {
              $this->_options_rsort['id'] = 'ID';
          }

          $this->_options_rrows['1']    = 1;
          $this->_options_rrows['5']    = 5;
          $this->_options_rrows['10']   = 10;
          $this->_options_rrows['15']   = 15;
          $this->_options_rrows['20']   = 20;
          $this->_options_rrows['25']   = 25;
          $this->_options_rrows['50']   = 50;
          $this->_options_rrows['100']  = 100;
          $this->_options_rrows['1000'] = 1000;

          $this->_options_rdesc['ASC']  = 'Aufsteigend';
          $this->_options_rdesc['DESC'] = 'Absteigend';

          $this->_options_rselect['0'] = '*Alle*';
          $this->_options_rselect['1'] = 'Ausgewählte';

          $count = $this->_rcount;

          if ($count != -88) {
              if ($submit && !$nor) {
                  $this->_sys['dbx_rpos'] = 0;
              }

              if (isset($this->_sys['dbx_rrows'])) {
                  $this->_rrows = $this->_sys['dbx_rrows'];
              } else {
                  $this->_rrows = $count;
              }

              if (isset($this->_sys['dbx_rpos'])) {
                  $this->_rpos = $this->_sys['dbx_rpos'];
              } else {
                  $this->_rpos = 0;
              }
          }
      }

      if ($fid != 'pagination' && $create_sel_flds == 1) {
          if ($this->_data_table == 88) {
              $next_val = 0;

              foreach ($this->_options_rrows as $key => $nam) {
                  if ($this->_rcount <= $key) {
                      $next_val = $key;
                  }

                  if ($next_val) {
                      break;
                  }
              }

              if ($next_val) {
                  $this->_sys['dbx_rrows'] = $next_val;
              }
          }

          $this->add_fld('dbx_rrows',   'select-single-label', options: $this->_options_rrows,   rules: 'int',       label: 'Anz.Seite');
          $this->add_fld('dbx_rsort',   'select-single-label', options: $this->_options_rsort,   rules: 'parameter', label: 'Sortierung');
          $this->add_fld('dbx_rdesc',   'select-single-label', options: $this->_options_rdesc,   rules: 'parameter', label: 'Auf/Ab');
          $this->add_fld('dbx_rwhere',  'dbx|search',          options: '',                      rules: '*',         label: 'Suchen', class: 'input-reset');
          $this->add_fld('dbx_rselect', 'select-single-label', options: $this->_options_rselect, rules: 'parameter', label: 'Ausgewählte');
      }

      if ($this->submit()) {
          $now = microtime(true);

          if (!isset($this->_sys['try_first'])) {
              $this->_sys['try_first'] = $now;
          }

          if (!isset($this->_sys['try_count'])) {
              $this->_sys['try_count'] = 0;
          }

          if (!isset($this->_sys['try_error'])) {
              $this->_sys['try_error'] = 0;
          }

          $this->_sys['try_last']  = $now;
          $this->_sys['try_count'] = ($this->_sys['try_count'] + 1);

          if ($this->errors()) {
              $this->_sys['status']    = -1;
              $this->_sys['try_error'] = ($this->_sys['try_error'] + 1);
              $msg_class               = 'error';
              $msg                     = $this->_msg_error;
          } else {
              $this->_sys['status']    = 1;
              $this->_sys['try_error'] = 0;
              $msg_class               = 'success';
              $msg                     = $this->_msg_success;
          }
      } else {
          $msg_class = 'init';
          $msg       = $this->_msg_info;
      }

      $replaces = $this->_replaces;
      $replaces['dbx:select']     = $this->_create_sel_flds;
      $replaces['dbx:data_table'] = $this->_data_table;

      if ($mode == 'tabulurator') {
          $replaces = array_merge($replaces, $this->get_grid_replaces());
      }

      if (strpos($tpl, '|') === false) {
          $tpl = $this->_dbx_modul . '|' . $tpl;
      }

      $report_tpl = $this->get_tpl($tpl, $replaces, 'htm', $i);
      $report_tpl = $this->merge_tpl_data($report_tpl, $i);
      $report_tpl = $this->merge_fld_data($report_tpl, $i);
      $report_tpl = $this->merge_obj($report_tpl, $i);

      if ($mode == 'tabulurator') {
          $content = $report_tpl;

          if ($fid != 'pagination') {
              $this->store_sysdata();
          }

          $content = str_replace('{i}', $i, $content);

          $msg_tpl = $this->get_form_msg($msg_class, $msg);
          $content = str_replace('{obj:form_msg}', $msg_tpl, $content);

          $content = str_replace('[dbx:pagination]', '', $content);
          $content = str_replace('{rpt:pages}', (string) $this->_current_page, $content);

          $norep_ids = '';
          $js        = $this->_js;

          if (is_array($js)) {
              foreach ($js as $javascript) {
                  $javascript = str_replace('{i}', $i, $javascript);
                  $norep_ids .= dbx()->norep("\n" . '<script type="text/javascript">' . $javascript . '</script>' . "\n", $i);
              }
          }

          if ($norep_ids) {
              $norep = '<div class="norep">' . $norep_ids . '</div>';
          }

          $content = str_replace('[dbx:js]', $norep, $content);

          return $content;
      }

      $this->split_tpl($report_tpl);

      $haeder = $this->get_report_haeder();
      $body   = $this->get_report_body();
      $footer = $this->get_report_footer();

      $content = $haeder . $body . $footer;

      if ($fid != 'pagination') {
          $this->store_sysdata();
      }

      if ($fid != 'pagination') {
          if ($pages) {
              $ReportPages = $this->get_report_pages();
              $content     = str_replace('[dbx:pagination]', $ReportPages, $content);
          }

          $haeder_report = $this->_haeder_report;
          $haeder_page   = $this->_haeder_page;
          $footer_page   = $this->_footer_page;
          $footer_report = $this->_footer_report;

          $content = str_replace('{i}', $i, $content);

          $msg_tpl = $this->get_form_msg($msg_class, $msg);
          $content = str_replace('{obj:form_msg}', $msg_tpl, $content);

          $haeder_report = $this->run_report_haeder($haeder_report);
          $haeder_page   = $this->run_page_haeder($haeder_page);
          $footer_page   = $this->run_page_footer($footer_page);
          $footer_report = $this->run_report_footer($footer_report);

          $content = str_replace('[rpt:haeder_report]', $haeder_report, $content);
          $content = str_replace('[rpt:haeder_page]',   $haeder_page,   $content);
          $content = str_replace('[rpt:footer_page]',   $footer_page,   $content);
          $content = str_replace('[rpt:footer_report]', $footer_report, $content);
      }

      $norep_ids = '';
      $js        = $this->_js;

      if (is_array($js)) {
          $count = count($js);

          if ($count) {
              foreach ($js as $javascript) {
                  $javascript = str_replace('{i}', $i, $javascript);
                  $norep_ids .= dbx()->norep("\n" . '<script type="text/javascript">' . $javascript . '</script>' . "\n", $i);
              }
          }
      }

      $content = str_replace('{i}', $i, $content);

      if ($norep_ids) {
          $norep = '<div class="norep">' . $norep_ids . '</div>';
      }

      $content = str_replace('[dbx:js]', $norep, $content);
      $content = str_replace('[dbx:pagination]', '', $content);
      $content = str_replace('{rpt:pages}', $this->_current_page, $content);

      return $content;
  }
}
