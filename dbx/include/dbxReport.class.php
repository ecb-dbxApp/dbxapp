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
 * Neuer empfohlener Ablauf
 * ------------------------
 * 1. init(...)
 *    - lädt den Formular-/Reportzustand aus der Session
 * 2. create_selection_fields(...)
 *    - erzeugt Selektionsfelder aus FD/DD
 *    - legt FD-Defaults in _data ab
 * 3. Modul liest Reportwerte am Reportobjekt:
 *    - $oReport->get_fld_val('dbx_rsort', 'id', 'parameter')
 *    - $oReport->get_fld_val('dbx_rpos', 0, 'int')
 * 4. Report-Kontext setzen:
 *    - _tpl
 *    - _rflds
 *    - _rdata
 *    - _rcount
 *    - _rrows
 *    - _rpos
 * 5. optional:
 *    - Optionen / Actions / Select-Felder / Grid-Daten setzen
 * 6. run()
 *
 *
 * Wichtige Selektions-Regel
 * -------------------------
 * Report-Selektionen sind Formular-/Reportzustand.
 * Sichtbare Felder und interne Werte wie dbx_rpos werden über
 * dbxForm::get_fld_val() gelesen und in _sys gespeichert.
 *
 * Request-Werte aus POST/GET gewinnen, sonst wird der gespeicherte
 * Formularzustand verwendet. Dadurch bleiben Reportzustände nach F5 erhalten,
 * ohne dass dbxReport Werte nach dbx()->get_modul_var(...) spiegeln muss.
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
 * Callback-Modell
 * ---------------
 * `init()` übernimmt den direkten Aufrufer als Owner. Modulmethoden nach
 * `{fid}_{event}` werden dadurch ohne Registrierung gefunden; Bindestriche
 * der Report-ID werden zu Unterstrichen. Für `invoice-report` heißt der
 * Record-Callback beispielsweise `invoice_report_next_record()`.
 *
 * Berechnete Felder und Summen werden dort aufgebaut. Ein während des
 * Record-Laufs per `add_rep()` aktualisierter Wert steht beim Footerlauf zur
 * Verfügung. `{rpt:col_count}` liefert alle Tabellenspalten,
 * `{rpt:colspan}` alle Spalten außer der letzten Wertespalte. Explizite
 * Owner- und Callback-Setter bleiben nur für bewusst abweichende Namen nötig.
 *
 *
 * Wichtige Regel
 * --------------
 * Im Modus 'tabulurator' ist dbxReport kein klassischer HTML-Record-Renderer.
 * Dort liefert die Klasse primär Shell, URLs, Grid-Replaces und optional
 * JSON-kompatible Row-Daten.
 */
class dbxReport extends dbxForm {

    /** Interne Report-State-Werte ohne sichtbares Formularfeld */
    protected $_report_state_flds = array(
        'dbx_rpos' => array('default' => 0, 'rules' => 'int'),
    );

    /** vorbereitete einfache Tabellen-Templates */
    protected $_table_render_tpl_cache = array();

    /** AJAX-Linkklasse für klassische Report-Buttons */
    public $_ajax = 1;

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

    /** Gesamtanzahl ohne Report-Filter; -1 = nicht separat gesetzt */
    public $_count_all = -1;

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

    /** Registrierte Report-Mehrfachaktionen fuer Footer-Select */
    protected $_report_multi_actions = array();

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

    /** Confirm-Text für Tabellenleerung */
    public $_msg_confirm_delete_tab = 'Datenbank-Tabelle wirklich leeren ?';

    /** Confirm-Text für Copy */
    public $_msg_confirm_copy = 'Datensatz kopieren ?';

    /** DD der Tabelle, die über {db:delete_tab} geleert werden darf */
    public $_delete_tab_dd = '';

    /** Button-Label für {db:delete_tab} */
    public $_delete_tab_label = 'Tabelle leeren';

    /**
     * Verwendet für die Delete-Tab-Aktion die Meldungsschlüssel der aktiven FD.
     *
     * Der Schalter bleibt nur dann aus, wenn ein Modul beim Aktivieren bewusst
     * ein eigenes Label übergibt. Alte Aufrufe ohne zweiten Parameter werden
     * dadurch automatisch mehrsprachig, ohne ihre Signatur zu ändern.
     *
     * Wahr, solange das Label automatisch aus der aktiven FD gelesen wird.
     */
    protected $_delete_tab_label_from_fd = false;


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
     * Wichtig
     * -------
     * dbxReport erweitert dbxForm. Deshalb muss zuerst der Formularlauf über
     * _forward_clear() zurückgesetzt werden. Danach werden alle reportbezogenen
     * Laufzustände und Options-/Renderflags zurückgesetzt, damit bei wiederverwendeten
     * Systemobjekten keine Werte aus einem vorherigen Reportlauf durchrutschen.
     *
     * Der Workflow-Scope wird bewusst nicht zurückgesetzt. Er gehört zur
     * dbxForm-Workflow-Logik und darf sich in zusammenhängenden Abläufen fachlich
     * weitervererben.
     *
     * @return void
     */
    public function clear() {
        $this->_forward_clear();

        $this->_mode                = 'table';
        $this->_current_page        = 0;
        $this->_current_report_ln   = 0;
        $this->_current_page_ln     = 0;
        $this->_first_page_lines    = 9999;
        $this->_next_page_lines     = 9999;

        $this->_fld_haeder          = '';

        $this->_haeder_report       = '';
        $this->_footer_report       = '';
        $this->_haeder_page         = '';
        $this->_footer_page         = '';
        $this->_haeder_next_page    = '';
        $this->_footer_next_page    = '';

        $this->_haeder              = '';
        $this->_body                = '';
        $this->_footer              = '';

        $this->_record              = array();
        $this->_callback_owner      = null;
        $this->_callback_id         = '';
        $this->_callbacks           = array();
        $this->_rdata               = array();
        $this->_rdata_inline        = false;
        $this->_body_inline         = '';

        $this->_rcount              = 0;
        $this->_count_all           = -1;
        $this->_rpos                = 0;
        $this->_rrows               = 20;
        $this->_pages               = 0;
        $this->_pagelink            = '';

        $this->_auto_flds           = '';
        $this->_auto_mode           = '';
        $this->_rflds               = '';

        $this->_add_action          = '';

        $this->_create_sel_flds     = 0;
        $this->_create_row_select   = 0;
        $this->_create_row_edit     = 0;
        $this->_create_row_copy     = 0;
        $this->_create_row_delete   = 0;
        $this->_create_row_download = 0;
        $this->_create_row_show     = 0;
        $this->_create_row_export   = 0;
        $this->_create_row_import   = 0;
        $this->_create_row_undo     = 0;
        $this->_create_row_print    = 0;

        $this->_rpt_format          = array();


        $this->_table_col_count     = 0;
        $this->_multi_page_select   = 0;
        $this->_multi_select_work   = '';
        $this->_report_multi_actions = array();
        $this->_table_buttons       = 'left';
        $this->_table_render_tpl_cache = array();

        $this->_data_table          = 0;
        $this->_scroll_table        = 0;

        $this->_style_haeder        = array();
        $this->_class_haeder        = array();
        $this->_class_body          = array();

        $this->_count_selects       = -1;

        $this->_msg_confirm_delete  = 'Datensatz löschen ?';
        $this->_msg_confirm_delete_tab = 'Datenbank-Tabelle wirklich leeren ?';
        $this->_msg_confirm_copy    = 'Datensatz kopieren ?';

        $this->_delete_tab_dd       = '';
        $this->_delete_tab_label    = 'Tabelle leeren';
        $this->_delete_tab_label_from_fd = false;

        $this->_grid_read_url       = '';
        $this->_grid_save_url       = '';
        $this->_grid_delete_url     = '';
        $this->_grid_insert_url     = '';
        $this->_grid_sort_url       = '';
        $this->_grid_sync_url       = '';
        $this->_grid_print_url      = '';
        $this->_grid_export_url     = '';
        $this->_grid_schema         = '';
        $this->_grid_id             = '';
        $this->_grid_cols           = '';
        $this->_grid_layout         = 'fitColumns';
        $this->_grid_headerfilter   = 1;
        $this->_grid_headersort     = 1;
        $this->_grid_allow_delete   = 1;
        $this->_grid_allow_edit     = 1;
        $this->_grid_allow_insert   = 1;
        $this->_grid_synctime       = '2';

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
     * Aktiviert den Template-Platzhalter {db:delete_tab}.
     *
     * @param string $dd DD-Name der zu leerenden Tabelle
     * @param string|null $label Optionales festes Button-Label. Ohne Angabe
     *        verwendet dbxReport automatisch `delete_tab_label` aus der
     *        aktiven sprachabhängigen FD.
     *
     * @return void
     */
    public function enable_delete_tab($dd, $label = null) {
        $this->_delete_tab_dd    = (string) $dd;
        $this->_delete_tab_label_from_fd = $label === null || $label === '';
        if (!$this->_delete_tab_label_from_fd) {
            $this->_delete_tab_label = (string) $label;
        }

        if (!$this->_dd && $this->_delete_tab_dd) {
            $this->_dd = $this->_delete_tab_dd;
        }

        // Der Report besitzt alle Informationen fuer die Aktion bereits
        // zentral. Ein zuvor manuell gesetzter Delete-Tab-Button wird bewusst
        // ersetzt, damit alte Module ohne eigene Tokenlogik sicher weiterlaufen.
        $this->add_rep('bar_actions', $this->get_delete_tab_button());
    }

    /**
     * Rendert den Button fuer {db:delete_tab}.
     *
     * @return string
     */
    protected function get_delete_tab_button() {
        if (!$this->_delete_tab_dd) {
            return '';
        }

        $action = $this->get_report_action_url();

        if ($action) {
            $webApp = dbx()->get_system_obj('dbxWebApp');
            $action = $webApp->append_route_params($action, array(
                'dbx_do' => 'delete_tab',
            ));
            $action = dbx()->action_url($action);
        }

        $label = $this->_delete_tab_label_from_fd
            ? $this->get_fd_message(
                'delete_tab_label',
                'Tabelle leeren'
            )
            : $this->_delete_tab_label;
        $title = $this->get_fd_message(
            'delete_tab_title',
            $label
        );
        $confirm = $this->get_fd_message(
            'delete_tab_confirm',
            $this->_msg_confirm_delete_tab
        );
        $tooltip = $this->get_fd_message(
            'delete_tab_tooltip',
            $title
        );

        $data = array(
            'action'  => $action,
            'label'   => $label,
            'title'   => $title,
            'confirm' => $confirm,
            'class'   => 'btn-danger',
            'tooltip' => $tooltip,
        );

        $tpl = $this->get_tpl('dbx|action_button_delete_tab', $data);

        return $tpl;
    }

    /**
     * Ersetzt DB-spezifische Report-Platzhalter.
     *
     * @param string $content
     *
     * @return string
     */
    protected function merge_db_placeholders($content) {
        $content = str_replace('{db:delete_tab}', $this->get_delete_tab_button(), $content);

        return $content;
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

    public function get_record(): array {
        if (!is_array($this->_record)) {
            return array();
        }

        return $this->_record;
    }

    public function set_record(array $record): void {
        $this->_record = $record;
    }

    public function get_record_val(string $name, mixed $default = null): mixed {
        if (!is_array($this->_record)) {
            return $default;
        }

        if (array_key_exists($name, $this->_record)) {
            return $this->_record[$name];
        }

        return $default;
    }

    public function set_record_val(string $name, mixed $value): void {
        if (!is_array($this->_record)) {
            $this->_record = array();
        }

        $this->_record[$name] = $value;
    }

    public function set_callback_owner($owner): void {
        if (is_object($owner)) {
            $this->_callback_owner = $owner;
        }
    }

    public function set_report_header_callback(string $callback): void {
        $this->set_callback('report_header', $callback);
    }

    public function set_page_header_callback(string $callback): void {
        $this->set_callback('page_header', $callback);
    }

    public function set_header_callback(string $callback): void {
        $this->set_callback('header', $callback);
    }

    public function set_body_callback(string $callback): void {
        $this->set_callback('body', $callback);
    }

    public function set_footer_callback(string $callback): void {
        $this->set_callback('footer', $callback);
    }

    public function set_page_footer_callback(string $callback): void {
        $this->set_callback('page_footer', $callback);
    }

    public function set_report_footer_callback(string $callback): void {
        $this->set_callback('report_footer', $callback);
    }

    public function set_next_record_callback(string $callback): void {
        $this->set_callback('next_record', $callback);
    }

    /**
     * Liefert den reportbezogenen Select-Key eines Datensatzes.
     *
     * @param mixed $record
     *
     * @return string
     */
    protected function get_record_select_key($record) {
        if (is_array($record) && array_key_exists('id', $record)) {
            return $this->normalize_multi_select_key($record['id']);
        }

        $rid = $this->get_record_rid($record, '');

        return $this->normalize_multi_select_key($rid);
    }

    /**
     * Parst eine ID-Liste in ein sauberes eindeutiges Array.
     *
     * Unterstützt:
     * - Array
     * - JSON-Array
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

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $this->parse_multi_select_ids($decoded);
            }
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

        $selects         = $this->get_multi_selects();
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
        $quick = dbx()->get_modul_var('dbx_select_quick', 1, 'int');

        if ($quick) {
            http_response_code(204);
            if (function_exists('session_write_close')) {
                session_write_close();
            }
            exit;
        }

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
        if (function_exists('session_write_close')) {
            session_write_close();
        }
        exit;
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
        $modul      = $this->_dbx_modul ;
        $modul_key  = $modul . '-rpt-' . $this->_fid;
        if (is_array($selects)) {
            foreach ($selects as $rid => $value) {
                $key = $this->normalize_multi_select_key($rid);

                if ($key !== '') {
                    $normalized[$key] = 1;
                }
            }
        }
        dbx()->set_remember_var('multi_select', $normalized, $modul_key);
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
        $modul      = $this->_dbx_modul ;
        $modul_key  = $modul . '-rpt-' . $this->_fid;
        $selects = dbx()->get_remember_var('multi_select', array(), $modul_key);

        if (!is_array($selects)) {
            $selects = array();
        }

        $normalized = array();

        foreach ($selects as $rid => $value) {
            $xkey = $this->normalize_multi_select_key($rid);

            if ($xkey !== '') {
                $normalized[$xkey] = 1;
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
     * Entfernt Datensätze nur aus der gemerkten Auswahl.
     *
     * Wichtige Regel:
     * - hier wird NICHT fachlich gelöscht
     * - hier wird NICHT in der DB gelöscht
     * - die Methode bereinigt ausschließlich den Remember-basierten Select-Zustand
     *
     * Typische Verwendung:
     * - Modul löscht Datensatz selbst
     * - danach ruft Modul del_selected($rid) auf
     * - damit wird die Auswahl konsistent bereinigt
     *
     * @param mixed $rid
     *
     * @return int Anzahl entfernter Einträge
     */
    public function del_selected($rid = 0) {
        $count = 0;

        if ($rid == '*') {
            $count = count($this->get_multi_selects());
            $this->del_multi_select('*');
            return $count;
        }

        $key = $this->normalize_multi_select_key($rid);

        if ($key === '') {
            return 0;
        }

        $selects = $this->get_multi_selects();

        if (isset($selects[$key])) {
            unset($selects[$key]);
            $this->set_multi_selects($selects);
            $count = 1;
        }

        return $count;
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

        $felder      = explode(',', $feldListe);
        $sql        .= ' AND (';
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
     * Aktuell unterstützt:
     * - dbx_do=row_select
     * - dbx_do=rows_select
     * - dbx_do=clear_selects
     *
     * Alte Legacy-Pfade für add/rem/count_response wurden bewusst entfernt.
     *
     * @return mixed
     */
    public function set_form_selects() {
        $dbx_do = dbx()->get_modul_var('dbx_do', '', 'parameter');
        $ajax   = dbx()->get_system_var('dbx_ajax', 0, 'int');

        if ($dbx_do === 'selection_state') {
            $idsRaw     = dbx()->get_modul_var('dbx_select_ids', '', '*');
            $visibleRaw = dbx()->get_modul_var('dbx_select_visible_ids', '', '*');
            $selected   = $this->parse_multi_select_ids($idsRaw);
            $visibleIds = $this->parse_multi_select_ids($visibleRaw);

            if (!$visibleIds) {
                $visibleIds = $selected;
            }

            $selectedMap = array_flip($selected);

            foreach ($visibleIds as $rid) {
                if (isset($selectedMap[$rid])) {
                    $this->set_multi_select($rid);
                } else {
                    $this->del_multi_select($rid);
                }
            }

            if ($ajax) {
                $this->send_multi_select_json_response($visibleIds, $dbx_do);
            }

            return 'handled';
        }

        if ($dbx_do === 'row_select') {
            $state      = dbx()->get_modul_var('dbx_select_state', 0, 'int');
            $rid        = dbx()->get_modul_var('rid', '', 'parameter+.');
            $selectId   = dbx()->get_modul_var('dbx_select_id', '', 'parameter+.');
            $visibleIds = dbx()->get_modul_var('dbx_select_visible_ids', '', '*');

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
            $state      = dbx()->get_modul_var('dbx_select_state', 0, 'int');
            $idsRaw     = dbx()->get_modul_var('dbx_select_ids', '', '*');
            $visibleRaw = dbx()->get_modul_var('dbx_select_visible_ids', '', '*');
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
        $reform = $this->get_report_format($key);

        if ($reform == 'php-date-usr' || $reform == 'date') {
            $value = $this->php_date_usr($value);
        }

        if (
            $reform == 'php-datetime-usr' ||
            $reform == 'date_time' ||
            $reform == 'datetime' ||
            $reform == 'datetime_ms'
        ) {
            $value = $this->php_datetime_usr($value);
        }

        if ($reform == 'html-chars') {
            $value = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if ($value === null) {
            $value = '';
        }

        return $value;
    }

    /**
     * Ermittelt die explizite oder automatische Reportformatierung.
     *
     * @param string $key Feldname
     *
     * @return string
     */
    protected function get_report_format($key) {
        $format = $this->_rpt_format;
        $reform = '';

        if (is_array($format)) {
            if (isset($format[$key])) {
                $reform = $format[$key];
            }
        } else {
            $reform = $format;
        }

        if ($reform === '') {
            $reform = $this->get_auto_report_format($key);
        }

        return (string) $reform;
    }

    /**
     * Prueft, ob eine Reportzelle bewusst HTML ausgeben darf.
     *
     * @param string $key Feldname
     *
     * @return bool
     */
    protected function report_cell_allows_html($key) {
        $format = strtolower(trim($this->get_report_format($key)));

        return in_array($format, array('html', 'raw-html', 'raw', 'tpl', 'template'), true);
    }

    /**
     * Bereitet einen Tabellenwert fuer normale Reportzellen auf.
     *
     * Reportwerte duerfen grundsaetzlich HTML enthalten. Wer Escaping braucht,
     * markiert die Spalte explizit mit dem Reportformat `html-chars`; diese
     * Formatierung wurde bereits in rpt_format() angewendet.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return string
     */
    protected function render_report_cell_value($key, $value) {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Ermittelt automatische Reportformatierung aus DD/FD-Metadaten.
     *
     * Reihenfolge:
     * 1. DD/FD convert
     * 2. DD/FD type
     *
     * @param string $key Feldname
     *
     * @return string
     */
    protected function get_auto_report_format($key) {
        static $cache = array();

        if (!$this->_dd || !$key) {
            return '';
        }

        $dd       = (string) $this->_dd;
        $key      = (string) $key;
        $cacheKey = $dd . '|' . $key;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $format  = '';
        $convert = strtolower(trim((string) $this->get_dd($dd, $key, 'convert')));

        if (in_array($convert, array('date', 'php-date-usr'), true)) {
            $format = 'php-date-usr';
            $cache[$cacheKey] = $format;
            return $format;
        }

        if (in_array($convert, array('date_time', 'datetime', 'datetime_ms', 'php-datetime-usr'), true)) {
            $format = 'php-datetime-usr';
            $cache[$cacheKey] = $format;
            return $format;
        }

        $type = strtolower(trim((string) $this->get_dd($dd, $key, 'type')));

        if ($type == 'date') {
            $format = 'php-date-usr';
            $cache[$cacheKey] = $format;
            return $format;
        }

        if (in_array($type, array('datetime', 'date_time', 'datetime_ms', 'timestamp'), true)) {
            $format = 'php-datetime-usr';
            $cache[$cacheKey] = $format;
            return $format;
        }

        $cache[$cacheKey] = '';

        return '';
    }

    /**
     * Fügt reportweite Platzhalter, Objekte und dbxForm-Replacements ein.
     *
     * Neben `{rpt:col_count}` steht `{rpt:colspan}` für alle Spalten außer
     * der letzten Wertespalte bereit. Abschließend werden auch spät während
     * des Record-Laufs mit add_rep() gesetzte Werte über die von dbxForm
     * geerbte replaces()-Pipeline eingesetzt.
     *
     * @param string $content
     *
     * @return string
     */
    public function rpt_merge_obj($content) {
        $count_select = $this->get_count_selects();
        $count_cols   = $this->_table_col_count;
        $label_colspan = max(1, $count_cols - 1);
        $page         = $this->_current_page;
        $page_break   = $this->_page_break;

        $content = str_replace('{rpt:count_sel}', $count_select, $content);
        $content = str_replace('{rpt:col_count}', $count_cols, $content);
        $content = str_replace('{rpt:colspan}', $label_colspan, $content);
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

        return $this->replaces($content);
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
        $content = ($this->_fid != 'pagination') ? $this->callback('report_header', $content) : $content;
        return $this->forward_run_report_haeder($content);
    }

    public function run_page_haeder($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('page_header', $content) : $content;
        return $this->forward_run_page_haeder($content);
    }

    public function run_page_footer($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('page_footer', $content) : $content;
        return $this->forward_run_page_footer($content);
    }

    public function forward_run_report_footer($content) {
        return $this->rpt_merge_obj($content);
    }

    public function run_report_footer($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('report_footer', $content) : $content;
        return $this->forward_run_report_footer($content);
    }

    public function run_haeder($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('header', $content) : $content;
        return $this->forward_run_haeder($content);
    }

    public function run_body($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('body', $content) : $content;
        return $this->forward_run_body($content);
    }

    public function run_footer($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('footer', $content) : $content;
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

    protected function render_simple_table_tpl($file, array $data) {
        if (!isset($this->_table_render_tpl_cache[$file])) {
            $this->_table_render_tpl_cache[$file] = $this->get_tpl($file, array());
        }

        foreach (array('title', 'tooltip') as $attribute) {
            if (isset($data[$attribute]) && !is_array($data[$attribute])) {
                $data[$attribute] = htmlspecialchars(
                    (string)$data[$attribute],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
            }
        }

        return $this->oTPL->replaces($this->_table_render_tpl_cache[$file], $data);
    }

    /**
     * Liefert getrennte Fenstertitel und HTML-faehige Bedienhinweise fuer
     * automatisch erzeugte Report-Aktionen.
     */
    protected function get_table_action_ui($type): array {
        $language = in_array($this->_dbx_lng, array('de', 'en', 'es'), true)
            ? $this->_dbx_lng
            : 'de';
        $texts = array(
            'de' => array(
                'edit' => array('Datensatz bearbeiten', '<strong>Bearbeiten</strong><br><small>Datensatz im Formular oeffnen</small>'),
                'copy' => array('Datensatz kopieren', '<strong>Kopieren</strong><br><small>Neuen Datensatz als Kopie anlegen</small>'),
                'show' => array('Datensatz anzeigen', '<strong>Anzeigen</strong><br><small>Datensatz schreibgeschuetzt oeffnen</small>'),
                'export' => array('CSV Export', '<strong>Exportieren</strong><br><small>Datensatz als CSV ausgeben</small>'),
                'import' => array('CSV Import', '<strong>Importieren</strong><br><small>Daten aus einer CSV-Datei einlesen</small>'),
                'download' => array('Datei herunterladen', '<strong>Herunterladen</strong><br><small>Datei lokal speichern</small>'),
                'delete' => array('Datensatz loeschen', '<strong>Loeschen</strong><br><small>Datensatz nach Bestaetigung entfernen</small>'),
                'print' => array('Drucken', '<strong>Drucken</strong><br><small>Druckansicht oeffnen</small>'),
                'expander' => array('Details', '<strong>Details</strong><br><small>Zusaetzliche Zeilendaten einblenden</small>'),
            ),
            'en' => array(
                'edit' => array('Edit record', '<strong>Edit</strong><br><small>Open the record in the form</small>'),
                'copy' => array('Copy record', '<strong>Copy</strong><br><small>Create a new record as a copy</small>'),
                'show' => array('View record', '<strong>View</strong><br><small>Open the record read-only</small>'),
                'export' => array('CSV export', '<strong>Export</strong><br><small>Write the record to CSV</small>'),
                'import' => array('CSV import', '<strong>Import</strong><br><small>Read data from a CSV file</small>'),
                'download' => array('Download file', '<strong>Download</strong><br><small>Save the file locally</small>'),
                'delete' => array('Delete record', '<strong>Delete</strong><br><small>Remove the record after confirmation</small>'),
                'print' => array('Print', '<strong>Print</strong><br><small>Open the print view</small>'),
                'expander' => array('Details', '<strong>Details</strong><br><small>Show additional row data</small>'),
            ),
            'es' => array(
                'edit' => array('Editar registro', '<strong>Editar</strong><br><small>Abrir el registro en el formulario</small>'),
                'copy' => array('Copiar registro', '<strong>Copiar</strong><br><small>Crear un registro nuevo como copia</small>'),
                'show' => array('Mostrar registro', '<strong>Mostrar</strong><br><small>Abrir el registro en modo de solo lectura</small>'),
                'export' => array('Exportar CSV', '<strong>Exportar</strong><br><small>Guardar el registro como CSV</small>'),
                'import' => array('Importar CSV', '<strong>Importar</strong><br><small>Leer datos desde un archivo CSV</small>'),
                'download' => array('Descargar archivo', '<strong>Descargar</strong><br><small>Guardar el archivo localmente</small>'),
                'delete' => array('Eliminar registro', '<strong>Eliminar</strong><br><small>Quitar el registro tras confirmarlo</small>'),
                'print' => array('Imprimir', '<strong>Imprimir</strong><br><small>Abrir la vista de impresion</small>'),
                'expander' => array('Detalles', '<strong>Detalles</strong><br><small>Mostrar datos adicionales de la fila</small>'),
            ),
        );

        return $texts[$language][(string)$type] ?? array('', '');
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
            $tpl  = $this->render_simple_table_tpl($file, array(
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
        $actionUi = $this->get_table_action_ui($type);
        $dat = array(
            'rid'     => $rid,
            'value'   => $rid,
            'action'  => $this->get_report_action_url(),
            'class'   => 'no-sort',
            'title'   => $actionUi[0],
            'tooltip' => $actionUi[1],
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

        if ($this->_fid != 'pagination') {
            $data = array(
                'type'   => $type,
                'record' => $record,
                'data'   => $dat,
            );

            $data = $this->callback('row_action_data', $data);

            if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
                $dat = $data['data'];
            }
        }

        if ($type === 'delete') {
            $webApp = dbx()->get_system_obj('dbxWebApp');
            $deleteRid = (int)($dat['rid'] ?? $rid);
            $deleteParams = array(
                'dbx_do' => 'row_delete',
                'rid' => $deleteRid,
            );

            $action = trim((string)($dat['action'] ?? ''));
            if ($action !== '' && $action !== '#' && stripos($action, 'javascript:') !== 0) {
                $action = $webApp->append_route_params($action, $deleteParams);
                $dat['action'] = dbx()->action_url($action);
            }

            // Spezialisierte Row-Templates verwenden teilweise delete_url
            // statt action. Auch diese Variante wird zentral normalisiert.
            $deleteUrl = trim((string)($dat['delete_url'] ?? ''));
            if ($deleteUrl !== '' && $deleteUrl !== '#' && stripos($deleteUrl, 'javascript:') !== 0) {
                $deleteUrl = $webApp->append_route_params($deleteUrl, $deleteParams);
                $dat['delete_url'] = dbx()->action_url($deleteUrl);
            }
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
            $tpl  = $this->render_simple_table_tpl($file, $dat);

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

        return $this->render_simple_table_tpl($file, array(
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
        $rid     = $this->get_record_select_key($record);
        $checked = $this->check_is_multiselect($rid);

        if ($checked) {
            $this->_post[$name] = 1;
        }

        return $this->render_simple_table_tpl($file, array(
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

                $tpl = $this->render_simple_table_tpl($file, array(
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

                $class = trim($class . ' dbx-report-cell');
                $value = $this->render_report_cell_value($xkey, $value);

                $tpl = $this->render_simple_table_tpl($this->_tabel_tpls['tpl_row_col'], array(
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
                $record        = ($this->_fid != 'pagination') ? $this->callback('next_record', $record) : $record;
                $this->_record = $record;

                if (!is_array($record)) {
                    continue;
                }

                $line          = $this->run_body($line);
                $record        = $this->_record;

                if (!is_array($record)) {
                    continue;
                }

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

                if (is_array($record)) {
                    foreach ($record as $field => $value) {
                        $field_name = '{' . $field . '}';

                        if (strpos($line, $field_name) === false) {
                            continue;
                        }

                        $value = $this->rpt_format($field, $value);

                        if (!is_array($value) && !is_object($value)) {
                            if ($value === null) {
                                $value = '';
                            }

                            $line = str_replace($field_name, (string) $value, $line);
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
        $content = str_replace('{rpt:colspan}', max(1, $col_count - 1), $content);
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

    /**
     * Aktiviert die Report-JS-Lib automatisch fuer Reports mit Row-Checkboxen.
     *
     * Aeltere Report-Templates haben zwar class="dbxReport", aber noch kein
     * data-dbx="lib=report". Die Row-Selection muss trotzdem zentral und
     * flackerfrei laufen, ohne jedes Template einzeln nachzuziehen.
     *
     * @param string $content
     *
     * @return string
     */
    protected function ensure_report_select_feature($content) {
        if ((!$this->_create_row_select && !$this->_create_sel_flds) || !is_string($content) || stripos($content, 'dbxReport') === false) {
            return $content;
        }

        return preg_replace_callback(
            '/<div\b(?=[^>]*\bclass\s*=\s*(["\'])[^"\']*\bdbxReport\b[^"\']*\1)([^>]*)>/i',
            function ($match) {
                $tag = $match[0];

                if (preg_match('/\bdata-dbx\s*=\s*(["\'])(.*?)\1/i', $tag, $dataMatch)) {
                    $value = trim($dataMatch[2]);

                    if (stripos($value, 'lib=report') !== false) {
                        return $tag;
                    }

                    $value  = ($value === '') ? 'lib=report|form=0' : $value . '||lib=report|form=0';
                    $newAtt = 'data-dbx=' . $dataMatch[1] . $value . $dataMatch[1];

                    return str_replace($dataMatch[0], $newAtt, $tag);
                }

                return substr($tag, 0, -1) . ' data-dbx="lib=report|form=0">';
            },
            $content,
            1
        );
    }

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
        $oReport->_mode            = 'table';
        $oReport->_rflds           = array();
        $oReport->_body_inline     = false;
        $oReport->_create_sel_flds = 0;
        // Die Pagination rendert ueber eine eigene, isolierte dbxReport-Instanz
        // mit synthetischem Modul/Fid ('dbx'/'pagination'). Ohne diese
        // Uebernahme wuerden {pagination:count_all} und
        // {pagination:count_checked} den (leeren) Auswahl-/Zaehlkontext dieser
        // Hilfsinstanz statt des tatsaechlichen Reports lesen.
        $oReport->_count_all       = ($this->_count_all >= 0) ? $this->_count_all : $rcount;
        $oReport->_count_selects   = $this->get_count_selects();

        $content = $oReport->run();

        $content = str_replace('{href_first}', $href_first, $content);
        $content = str_replace('{href_last}',  $href_last,  $content);
        $content = str_replace('{href_prev}',  $href_prev,  $content);
        $content = str_replace('{href_next}',  $href_next,  $content);
        $selectState = $this->get_visible_multi_select_state();

        $countAll = ($this->_count_all >= 0) ? $this->_count_all : $rcount;

        $content = $this->applyReportCountReplaces($content);

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
            'grid_id'        => $gridId,
            'grid_cols'      => $cols,
            'grid_schema'    => $this->_grid_schema,
            'grid_layout'    => $this->_grid_layout,
            'grid_height'    => $this->_rrows,
            'grid_synctime'  => $this->_grid_synctime,
            'grid_page'      => $page,
            'grid_page_size' => ($rrows > 0) ? $rrows : 25,
        );
    }

    public function add_grid_stats(array $stats, $ariaLabel = '') {
        if (!$stats) {
            $this->add_rep('grid_stats', '');
            return;
        }

        $html = '<div class="dbx-grid-stats"';
        if ((string)$ariaLabel !== '') {
            $html .= ' aria-label="' . htmlspecialchars((string)$ariaLabel, ENT_QUOTES) . '"';
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

    protected function buildGridBarObj() {
        if ($this->_mode !== 'tabulurator') {
            return;
        }
    }

    protected function prepareReportFrameReplaces(int $i, array $options = array()): void {
        $withForm = (string)($this->_replaces['frame_use_form'] ?? '0') !== '0';
        if (array_key_exists('with_form', $options)) {
            $withForm = (bool)$options['with_form'];
        }
        $panelClass = trim('dbxReport ' . (string)($this->_replaces['report_shell_class'] ?? '') . ' ' . (string)($this->_replaces['shell_panel_class'] ?? ''));
        $frameId = trim((string)($this->_replaces['frame_id'] ?? ''));
        if ($frameId === '') {
            $frameId = 'dbx_target_' . $i;
        }

        $this->add_rep('frame_id', $frameId);
        if (trim((string)($this->_replaces['frame_panel_class'] ?? '')) === '') {
            $this->add_rep('frame_panel_class', trim($panelClass));
        }
        $panelAttrs = (string)($this->_replaces['report_shell_attrs'] ?? '');
        if ($panelAttrs === '') {
            $panelAttrs = (string)($this->_replaces['shell_panel_attrs'] ?? '');
        }
        $this->add_rep('frame_panel_attrs', $panelAttrs);
        $this->add_rep('frame_subbar', '');
        $this->add_rep('frame_body_class', (string)($this->_replaces['shell_body_class'] ?? ''));
        $this->add_rep('frame_body_head', (string)($this->_replaces['frame_body_head'] ?? ''));
        $this->add_rep('frame_body_tail', (string)($this->_replaces['frame_body_tail'] ?? ''));

        if ($withForm) {
            $reportFormClass = trim((string)($this->_replaces['report_form_class'] ?? ''));
            $reportFormAttrs = trim((string)($this->_replaces['report_form_attrs'] ?? ''));
            $this->add_rep('frame_form_open', '<form action="' . htmlspecialchars((string)$this->_action, ENT_QUOTES) . '" method="post" id="dbx_form_' . $i . '" class="dbxAjax' . ($reportFormClass !== '' ? ' ' . $reportFormClass : '') . '"' . ($reportFormAttrs !== '' ? ' ' . $reportFormAttrs : '') . '>');
            $this->add_rep('frame_form_close', '</form>');
        } else {
            $this->add_rep('frame_form_open', '');
            $this->add_rep('frame_form_close', '');
        }
    }

    /**
     * Entfernt unbenutzte Report-Bar-Feldslots ({obj:...} ohne Felddefinition).
     *
     * @param string $content
     *
     * @return string
     */
    protected function applyReportCountReplaces($content) {
        $rcount   = (int) $this->_rcount;
        $countAll = ($this->_count_all >= 0) ? (int) $this->_count_all : $rcount;

        $content = str_replace('{pagination:count_all}',      (string) $countAll, (string) $content);
        $content = str_replace('{pagination:count_selected}', (string) $rcount, (string) $content);
        $content = str_replace('{pagination:count_checked}',  (string) $this->get_count_selects(), (string) $content);
        $content = str_replace('{report_extra_stats}', (string)($this->_replaces['report_extra_stats'] ?? ''), (string) $content);
        $content = str_replace('{report_bar_actions}', (string)($this->_replaces['report_bar_actions'] ?? ''), (string) $content);

        return $content;
    }

    protected function cleanupReportBarSlots($content) {
        return preg_replace(
            '/<div class="dbx-report-bar-field"[^>]*>\s*\{obj:[a-z0-9_]+\}\s*<\/div>\s */i',
            '',
            (string) $content
        );
    }

    /**
     * Editor-Platzhalter (#form_msg_info#) im Live-Betrieb als leer behandeln.
     *
     * @param string $msg
     *
     * @return string
     */
    protected function resolveReportMsgText($msg) {
        $msg = trim((string) $msg);

        if ($msg === '') {
            return '';
        }

        $editor = dbx()->get_system_var('dbx_editor', 0, 'int');

        if (!$editor && preg_match('/^#form_msg_(info|success|error|warning)#$/', $msg)) {
            return '';
        }

        return $msg;
    }

    /**
     * Baut die sichtbare Report-Formularmeldung (leer wenn nicht gesetzt).
     *
     * @return string
     */
    protected function buildReportFormMsgHtml() {
        $error = $this->resolveReportMsgText($this->_msg_error);

        if ($error === '' && !empty($this->_msg_err)) {
            $error = $this->resolveReportMsgText($this->_msg_err);
        }

        if ($error !== '') {
            return $this->get_form_msg('error', $error);
        }

        $warning = $this->resolveReportMsgText($this->_msg_warning);

        if ($warning !== '') {
            return $this->get_form_msg('warning', $warning);
        }

        $success = $this->resolveReportMsgText($this->_msg_success);

        if ($success !== '') {
            return $this->get_form_msg('success', $success);
        }

        if ($this->submit() && $this->errors()) {
            return $this->get_form_msg('error', 'Pruefen Sie bitte Ihre Eingaben.');
        }

        $info = $this->resolveReportMsgText($this->_msg_info);

        if ($info !== '') {
            return $this->get_form_msg('info', $info);
        }

        return '';
    }

    /**
     * Registriert Report-Aktionen und baut Footer-Select-Metadaten auf.
     *
     * @param string $obj
     * @param string $tpl
     * @param string $action
     * @param mixed  $data
     *
     * @return void
     */
    public function add_action($obj, $tpl, $action = '', $data = '') {
        $dbx_do = $this->parse_report_action_code($action);
        $actionUrl = (string)$action;

        if ($actionUrl !== '' && $actionUrl[0] === '&') {
            $actionUrl = $this->get_report_action_url() . $actionUrl;
        }

        if ($actionUrl !== '') {
            $actionUrl = dbx()->action_url($actionUrl);
        }

        parent::add_action($obj, $tpl, $actionUrl, $data);

        if ($dbx_do === '') {
            return;
        }

        $confirm = '';

        if ($tpl === 'action_button_delete') {
            $confirm = (string) $this->_msg_confirm_delete;
        }

        $this->_report_multi_actions[$dbx_do] = array(
            'obj'     => (string) $obj,
            'label'   => $this->get_report_action_label($tpl),
            'tpl'     => (string) $tpl,
            'action'  => (string) $actionUrl,
            'confirm' => $confirm,
            'quick'   => in_array($dbx_do, array('multi_select', 'multi_deselect'), true),
        );
    }

    /**
     * @param string $action
     *
     * @return string
     */
    protected function parse_report_action_code($action) {
        $action = (string) $action;

        if ($action === '' || $action[0] !== '&') {
            return '';
        }

        if (preg_match('/(?:^|&)dbx_do=([^&]+)/', $action, $match)) {
            return (string) $match[1];
        }

        if (preg_match('/(?:^|&)dbx_run2=([^&]+)/', $action, $match)) {
            return (string) $match[1];
        }

        return '';
    }

    /**
     * @param string $tpl
     *
     * @return string
     */
    protected function get_report_action_label($tpl) {
        $labels = array(
            'action_button_delete' => array(
                'key' => 'action_delete_selected',
                'de' => 'Ausgewählte löschen',
                'en' => 'Delete selected',
                'es' => 'Eliminar seleccionados',
            ),
            'action_button_activate' => array(
                'key' => 'action_activate_selected',
                'de' => 'Ausgewählte aktivieren',
                'en' => 'Activate selected',
                'es' => 'Activar seleccionados',
            ),
            'action_button_deactivate' => array(
                'key' => 'action_deactivate_selected',
                'de' => 'Ausgewählte deaktivieren',
                'en' => 'Deactivate selected',
                'es' => 'Desactivar seleccionados',
            ),
            'action_button_select' => array(
                'key' => 'action_select_visible',
                'de' => 'Sichtbare auswählen',
                'en' => 'Select visible',
                'es' => 'Seleccionar visibles',
            ),
            'action_button_deselect' => array(
                'key' => 'action_deselect_visible',
                'de' => 'Sichtbare abwählen',
                'en' => 'Deselect visible',
                'es' => 'Deseleccionar visibles',
            ),
        );

        if (!isset($labels[$tpl])) {
            return (string) $tpl;
        }

        $language = in_array($this->_dbx_lng, array('en', 'es'), true)
            ? $this->_dbx_lng
            : 'de';
        $definition = $labels[$tpl];

        return $this->get_fd_message(
            $definition['key'],
            $definition[$language]
        );
    }

    /**
     * Loescht gemerkte Mehrfachauswahl Datensatz fuer Datensatz (Trace pro Zeile).
     *
     * @param string $dd
     * @param int    $verify_access
     * @param int    $trace
     *
     * @return array{deleted:int,failed:int,total:int}
     */
    public function delete_multi_selected_records($dd, $verify_access = 1, $trace = 1) {
        $db      = dbx()->get_system_obj('dbxDB');
        $selects = array_keys($this->get_multi_selects());
        $fld_id  = $this->_fld_id ? $this->_fld_id : 'id';
        $deleted = 0;
        $failed  = 0;

        if (!is_array($selects) || !count($selects)) {
            return array(
                'deleted' => 0,
                'failed'  => 0,
                'total'   => 0,
            );
        }

        foreach ($selects as $id) {
            $key = $this->normalize_multi_select_key($id);

            if ($key === '') {
                continue;
            }

            if (preg_match('/^-?\d+$/', $key)) {
                $where = $fld_id . '=' . (int) $key;
            } else {
                $where = $fld_id . "='" . addslashes($key) . "'";
            }

            $ok = $db->delete($dd, $where, $verify_access, $trace);
            $this->del_selected($key);

            if ((int) $ok > 0) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        $this->_count_selects = -1;

        return array(
            'deleted' => $deleted,
            'failed'  => $failed,
            'total'   => $deleted + $failed,
        );
    }

    /**
     * Setzt Erfolgs-/Fehlermeldung nach Mehrfach-Loeschung.
     *
     * @param array $result
     *
     * @return void
     */
    public function apply_multi_delete_result(array $result) {
        $deleted = (int) ($result['deleted'] ?? 0);
        $failed  = (int) ($result['failed'] ?? 0);
        $language = in_array($this->_dbx_lng, array('en', 'es'), true)
            ? $this->_dbx_lng
            : 'de';

        if ($deleted <= 0 && $failed <= 0) {
            return;
        }

        if ($deleted > 0 && $failed === 0) {
            if ($deleted === 1) {
                $fallback = array(
                    'de' => '1 Datensatz gelöscht.',
                    'en' => '1 record was deleted.',
                    'es' => 'Se eliminó 1 registro.',
                )[$language];
                $this->_msg_success = $this->get_fd_message(
                    'multi_delete_one',
                    $fallback
                );
            } else {
                $fallback = array(
                    'de' => $deleted . ' Datensätze gelöscht.',
                    'en' => $deleted . ' records were deleted.',
                    'es' => 'Se eliminaron ' . $deleted . ' registros.',
                )[$language];
                $this->_msg_success = $this->format_fd_message(
                    'multi_delete_many',
                    array('count' => $deleted),
                    $fallback
                );
            }
            return;
        }

        if ($deleted > 0) {
            $fallback = array(
                'de' => $deleted . ' Datensätze gelöscht, ' . $failed . ' fehlgeschlagen.',
                'en' => $deleted . ' records were deleted; ' . $failed . ' failed.',
                'es' => 'Se eliminaron ' . $deleted . ' registros; ' . $failed . ' fallaron.',
            )[$language];
            $this->_msg_error = $this->format_fd_message(
                'multi_delete_partial',
                array('deleted' => $deleted, 'failed' => $failed),
                $fallback
            );
            return;
        }

        $fallback = array(
            'de' => 'Löschen fehlgeschlagen.',
            'en' => 'Deletion failed.',
            'es' => 'La eliminación falló.',
        )[$language];
        $this->_msg_error = $this->get_fd_message(
            'multi_delete_failed',
            $fallback
        );
    }

    /**
     * Baut Footer mit Aktions-Select und Schnellaktionen.
     *
     * @return void
     */
    protected function buildReportFooterObj() {
        if (!$this->_create_row_select || !$this->_report_multi_actions) {
            return;
        }

        if (isset($this->_obj['report_footer'])) {
            return;
        }

        $selectOptions = '';
        $actionLinks   = '';
        $hasSelect     = 0;

        foreach ($this->_report_multi_actions as $dbx_do => $action) {
            if (!empty($action['quick'])) {
                continue;
            }

            $hasSelect     = 1;
            $label         = $this->get_report_action_label(
                (string) ($action['tpl'] ?? '')
            );
            $actionSuffix  = (string) ($action['action'] ?? '');
            $actionUrl     = $actionSuffix;

            if ($actionSuffix !== '' && $actionSuffix[0] === '&') {
                $actionUrl = $this->get_report_action_url() . $actionSuffix;
            }

            $selectOptions .= $this->get_tpl('dbx|report-footer-action-option', array(
                'value' => (string) $dbx_do,
                'label' => $label,
            ));

            $actionLink = trim($this->get_tpl((string) ($action['tpl'] ?? 'dbx|action_button'), array(
                'action'  => $actionUrl,
                'label'   => $label,
                'title'   => $this->get_fd_message(
                    'report_action_confirm_title',
                    array(
                        'de' => 'Aktion bestätigen',
                        'en' => 'Confirm action',
                        'es' => 'Confirmar acción',
                    )[in_array($this->_dbx_lng, array('en', 'es'), true)
                        ? $this->_dbx_lng
                        : 'de']
                ),
                'confirm' => (string) ($action['confirm'] ?? ''),
                'class'   => '',
                'tooltip' => '',
            )));

            if ($actionLink !== '') {
                $actionLinks .= $this->get_tpl('dbx|report-footer-action-link', array(
                    'value'       => (string) $dbx_do,
                    'action_link' => $actionLink,
                ));
            }
        }

        if (!$hasSelect) {
            return;
        }

        $i = (int) $this->_next_i;
        $actionMain = $this->get_tpl('dbx|report-footer-action-main', array(
            'action_id'             => 'dbx_report_action_' . $i,
            'report_action_options' => $selectOptions,
            'report_action_links'   => $actionLinks,
        ));

        $html = trim($this->get_tpl('dbx|report-footer', array(
            'report_action_main' => $actionMain,
        )));

        if ($html !== '') {
            $this->add_obj('report_footer', 'obj-value', $html);
        }
    }

    /**
     * Fuegt Report-Footer vor Tabellenende ein, falls noch kein Slot vorhanden.
     *
     * @param string $content
     *
     * @return string
     */
    protected function injectReportFooter($content) {
        if (!isset($this->_obj['report_footer'])) {
            return (string) $content;
        }

        if (strpos($content, '{obj:report_footer}') !== false) {
            return (string) $content;
        }

        if (stripos($content, '<tfoot') !== false) {
            return (string) $content;
        }

        return (string) preg_replace(
            '/<\/tbody>\s*<\/table>/i',
            '</tbody>{obj:report_footer}</table>',
            (string) $content,
            1
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

    public function create_selection_fields($fd = 'fd::') {
        dbx()->debug('create_selection_fields');
        $source = $fd;

        if ($source === 'fd::') {
            $source = $this->_fd;
        } elseif ($source) {
            $this->_fd = $source;
        }

        if (!$source) {
            return;
        }

        $before = array_keys($this->_flds);
        $this->add_flds($source);
        $added = array_diff(array_keys($this->_flds), $before);

        foreach ($this->_report_state_flds as $key => $state) {
            if (!array_key_exists($key, $this->_data)) {
                $this->_data[$key] = $state['default'] ?? '';
            }
        }

        foreach ($added as $key) {
            if (!array_key_exists($key, $this->_data)) {
                $this->_data[$key] = $this->get_dd($source, $key, 'default');
            }
        }
    }



    public function init($fid, $tpl = '', $first_page_lines = -1, $next_page_lines = -1, $current_line = -1) {
        if ($fid == 'pagination')  return;
        $this->_dbx_modul= dbx()->get_system_var('dbx_activ_modul', 'dbx', '*');
        $this->forward_init($fid, $tpl);
        $this->set_callback_id($fid);
        //$this->create_selection_fields();

        $modul      = $this->_dbx_modul ;
        $modul_key = $modul . '-rpt-' . $this->_fid;


        if ($first_page_lines <= 0) $first_page_lines = 999999;
        if ($next_page_lines  <= 0) $next_page_lines = 999999;

        if ($current_line <= 0)     $current_line = 0;

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

        dbx()->set_remember_var('last_report_i', $this->_next_i, $modul_key);

        $this->set_form_selects();
        $this->_multi_select_work = '';
    }

    /**
     * Rendert den Report mit bereits gesetzten Properties.
     *
     * Wichtiger neuer Ablauf:
     * - run() arbeitet nur noch mit dem internen Objektzustand
     * - keine Feldliste / kein Modus / keine Pages mehr als Parameter
     * - Module setzen _rflds, _mode, _pages, _rdata, _rcount, _rrows, _rpos vorher
     *
     * @return string
     */
    public function run() {
        $content   = '';
        $norep     = '';
        $i               = $this->_next_i;
        $fid             = $this->_fid;
        $tpl             = $this->_tpl;

        $pages = $this->_pages;
        $mode  = $this->_mode;
        $flds  = $this->_rflds;

        if (!is_array($flds))  $flds = array();

        $this->_dbx_modul=  dbx()->get_system_var('dbx_activ_modul', 'dbx', '*');
        $this->_auto_flds = $flds;
        $this->_auto_mode = $mode;
        //$count = $this->_rcount;

        if ($this->_dd) {
            $oDB = dbx()->get_system_obj('dbxDB');
            if (method_exists($oDB, 'get_dd_file')) {
                $this->add_editor_file('dd', $oDB->get_dd_file($this->_dd));
            }
        }

        $this->buildModuleBarObj();
        $this->buildGridBarObj();
        $this->buildReportFooterObj();
        $this->prepareReportFrameReplaces((int)$i);

        $replaces = $this->_replaces;

        if ($mode == 'tabulurator') {
            $replaces = array_merge($replaces, $this->get_grid_replaces());
        }

        foreach (array(
            'report_shell_class' => '',
            'report_shell_attrs' => '',
            'report_form_class'  => '',
            'report_form_attrs'  => '',
            'shell_panel_class'  => '',
            'shell_panel_attrs'  => '',
            'shell_body_class'   => '',
        ) as $shellKey => $shellDefault) {
            if (!isset($replaces[$shellKey]) || (string) $replaces[$shellKey] === '') {
                $replaces[$shellKey] = $shellDefault;
            }
        }

        if (strpos($tpl, '|') === false) {
            $tpl = $this->_dbx_modul . '|' . $tpl;
        }

        $report_tpl = $this->get_tpl($tpl, $replaces, 'htm', $i);
        $report_tpl = $this->merge_tpl_data($report_tpl, $i);
        $report_tpl = $this->injectReportFooter($report_tpl);
        $report_tpl = $this->merge_fld_data($report_tpl, $i);
        $report_tpl = $this->merge_obj($report_tpl, $i);
        $report_tpl = $this->ensureFormHelpBar($report_tpl);
        $report_tpl = $this->merge_db_placeholders($report_tpl);
        $report_tpl = $this->cleanupReportBarSlots($report_tpl);
        $report_tpl = $this->applyReportCountReplaces($report_tpl);

        if ($mode == 'tabulurator') {
            $content = $report_tpl;

            if ($fid != 'pagination') {
                $this->store_sysdata();
            }

            $content = str_replace('{i}', $i, $content);

            $msg_tpl = $this->buildReportFormMsgHtml();
            $content = str_replace('{obj:form_msg}', $msg_tpl, $content);

            if ($pages) {
                $ReportPages = $this->get_report_pages();
                $content     = str_replace('[dbx:pagination]', $ReportPages, $content);
            } else {
                $content = str_replace('[dbx:pagination]', '', $content);
            }
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
            $content = $this->ensure_report_select_feature($content);

            return $this->add_editor_markers($content);
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

            $msg_tpl = $this->buildReportFormMsgHtml();
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
        $content = $this->ensure_report_select_feature($content);

        return $this->add_editor_markers($content);
    }
}

?>
