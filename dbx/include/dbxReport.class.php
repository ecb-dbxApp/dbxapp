<?php

require_once __DIR__ . '/dbxForm.class.php';
require_once __DIR__ . '/dbxReportSelection.trait.php';
require_once __DIR__ . '/dbxReportTableRendering.trait.php';
require_once __DIR__ . '/dbxReportPagination.trait.php';
require_once __DIR__ . '/dbxReportGrid.trait.php';
require_once __DIR__ . '/dbxReportChrome.trait.php';

/**
 * @brief Zentrale Listen-, Such-, Auswahl- und Reportpipeline von dbxapp.
 *
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
 * - Grid-/Tabulator-Shells
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
 * tabulator
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
 * Im Modus 'tabulator' ist dbxReport kein klassischer HTML-Record-Renderer.
 * Dort liefert die Klasse primär Shell, URLs, Grid-Replaces und optional
 * JSON-kompatible Row-Daten.
 */
class dbxReport extends dbxForm {

    use dbxReportSelectionTrait;
    use dbxReportTableRenderingTrait;
    use dbxReportPaginationTrait;
    use dbxReportGridTrait;
    use dbxReportChromeTrait;

    /** Standardtemplate fuer normale Tabellenreports. */
    public $_tpl_report = 'dbx|report-default';

    /** Einheitliche Filter-/Aktionsleiste eines Reports. */
    public $_tpl_report_bar = 'dbx|report-bar-default';

    /** Einheitlicher Footer fuer Mehrfachaktionen. */
    public $_tpl_report_footer = 'dbx|report-footer';

    /**
     * Eine Report-ID beschreibt Zustand und Callback-Namespace, nicht das Layout.
     * Spezialreports koennen ihr Modul-Template weiterhin ueber init() oder
     * set_report_tpl() setzen.
     */
    protected function default_tpl(string $fid): string {
        return $this->_tpl_report;
    }

    /** Interne Report-State-Werte ohne sichtbares Formularfeld */
    protected $_report_state_flds = array(
        'dbx_rpos' => array('default' => 0, 'rules' => 'int'),
    );

    /** vorbereitete einfache Tabellen-Templates */
    protected $_table_render_tpl_cache = array();
    protected $_table_action_options = array();

    /** AJAX-Linkklasse für klassische Report-Buttons */
    public $_ajax = 1;

    /** Report-Modus: table|tpl|tabulator */
    protected $_mode = 'table';

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
    public $_fld_header = '';

    /** Report-Header außerhalb der eigentlichen Seiten */
    public $_header_report = '';

    /** Report-Footer außerhalb der eigentlichen Seiten */
    public $_footer_report = '';

    /** Seiten-Header */
    public $_header_page = '';

    /** Seiten-Footer */
    public $_footer_page = '';

    /** Header für Folgeseiten */
    public $_header_next_page = '';

    /** Footer für Folgeseiten */
    public $_footer_next_page = '';

    /** Seitenumbruch-Markup */
    public $_page_break = '<div class="page-break printMe"> </div><br>';

    /** Interner Bereich: Header */
    public $_header = '';

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
    public $_table_tpls = array();

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
    public $_class_header = array();

    /** Header-Styles */
    public $_style_header = array();

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
     * GRID / TABULATOR SUPPORT
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

        $this->_tpl_report        = 'dbx|report-default';
        $this->_tpl_report_bar    = 'dbx|report-bar-default';
        $this->_tpl_report_footer = 'dbx|report-footer';
        $this->_report_bar_flds   = array();

        $this->_mode                = 'table';
        $this->_current_page        = 0;
        $this->_current_report_ln   = 0;
        $this->_current_page_ln     = 0;
        $this->_first_page_lines    = 9999;
        $this->_next_page_lines     = 9999;

        $this->_fld_header          = '';

        $this->_header_report       = '';
        $this->_footer_report       = '';
        $this->_header_page         = '';
        $this->_footer_page         = '';
        $this->_header_next_page    = '';
        $this->_footer_next_page    = '';

        $this->_header              = '';
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
        $this->_table_action_options = array();

        $this->_data_table          = 0;
        $this->_scroll_table        = 0;

        $this->_style_header        = array();
        $this->_class_header        = array();
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

        $table['tpl_header_col']      = 'table_header_col';
        $table['tpl_header_select']   = 'table_header_select';
        $table['tpl_header_delete']   = 'table_header_action';
        $table['tpl_header_expander'] = 'table_header_action';
        $table['tpl_header_edit']     = 'table_header_action';
        $table['tpl_header_copy']     = 'table_header_action';
        $table['tpl_header_show']     = 'table_header_action';
        $table['tpl_header_import']   = 'table_header_action';
        $table['tpl_header_export']   = 'table_header_action';
        $table['tpl_header_download'] = 'table_header_action';
        $table['tpl_header_print']    = 'table_header_action';

        $table['tpl_row_col']         = 'table_row_col';
        $table['tpl_row_select']      = 'table_row_select';
        $table['tpl_row_expander']    = 'table_row_expander';
        $table['tpl_row_edit']        = 'table_row_action';
        $table['tpl_row_copy']        = 'table_row_action';
        $table['tpl_row_delete']      = 'table_row_action';
        $table['tpl_row_show']        = 'table_row_action';
        $table['tpl_row_export']      = 'table_row_action';
        $table['tpl_row_import']      = 'table_row_action';
        $table['tpl_row_download']    = 'table_row_action';
        $table['tpl_row_print']       = 'table_row_action';

        if ($this->_multi_page_select) {
            $table['tpl_header_select'] = 'table_header_select-multi';
            $table['tpl_row_select']    = 'table_row_select-multi';
        }

        $this->_table_tpls = $table;
    }

    /**
     * Überschreibt ein Tabellen-Template gezielt.
     *
     * @param string $tid Interner Template-Key
     * @param string $tpl Template-Datei
     *
     * @return void
     */
    public function set_table_tpl($tid, $tpl) {
        $this->_table_tpls[$tid] = $tpl;
    }

    /**
     * Passt eine Standardaktion an, ohne dafuer ein fast identisches Template
     * im Fachmodul anlegen zu muessen.
     */
    public function set_table_action_options($type, array $options) {
        $type = strtolower(trim((string)$type));

        if ($type === '') {
            return;
        }

        $current = isset($this->_table_action_options[$type]) && is_array($this->_table_action_options[$type])
            ? $this->_table_action_options[$type]
            : array();
        $this->_table_action_options[$type] = array_replace($current, $options);
    }

    /**
     * Konfiguriert die Standardaktionen eines Tabellenreports deklarativ.
     *
     * Numerische Einträge aktivieren eine Aktion. Assoziative Einträge können
     * mit false deaktiviert oder mit einem Optionsarray konfiguriert werden.
     */
    public function set_table_actions(array $actions, bool $reset = true) {
        $properties = array(
            'select' => '_create_row_select', 'edit' => '_create_row_edit',
            'copy' => '_create_row_copy', 'delete' => '_create_row_delete',
            'show' => '_create_row_show', 'export' => '_create_row_export',
            'import' => '_create_row_import', 'download' => '_create_row_download',
            'print' => '_create_row_print',
        );

        if ($reset) {
            foreach ($properties as $property) $this->{$property} = 0;
        }

        foreach ($actions as $key => $value) {
            $type = is_int($key) ? strtolower(trim((string)$value)) : strtolower(trim((string)$key));
            if (!isset($properties[$type])) continue;

            $enabled = is_int($key) || $value === true || is_array($value);
            $this->{$properties[$type]} = $enabled ? 1 : 0;
            if (is_array($value)) $this->set_table_action_options($type, $value);
        }

        return $this;
    }

    /** Schaltet die Pagination ein oder aus und setzt die Anzahl der Buttons. */
    public function set_pagination(bool $enabled = true, int $buttons = 3): static {
        $this->_pages = $enabled ? 1 : 0;
        $this->_but_pagination = max(1, $buttons);
        return $this;
    }

    /** Setzt die maximale Zeilenzahl einer Reportseite. */
    public function set_page_size(int $rows): static {
        $this->_rrows = max(1, $rows);
        return $this;
    }

    /** Setzt die sichtbaren Spalten eines Tabellenreports. */
    public function set_report_fields(array $fields): static {
        $this->_rflds = $fields;
        return $this;
    }

    /**
     * Übergibt das Ergebnisfenster eines Reports samt Position und Gesamtzahl.
     */
    public function set_report_result(
        array $rows,
        int $position = 0,
        ?int $total = null
    ): static {
        $this->_rdata = $rows;
        $this->_rcount = count($rows);
        $this->_rpos = max(0, $position);
        $this->_count_all = $total === null ? $this->_rcount : max(0, $total);
        return $this;
    }

    /** Setzt bewusst ein individuelles Haupttemplate fuer diesen Report. */
    public function set_report_tpl($tpl): static {
        $tpl = trim((string)$tpl);
        $this->_tpl = $tpl !== '' ? $tpl : $this->_tpl_report;
        return $this;
    }

    /** Setzt die Reportleiste; ein leerer Wert deaktiviert sie. */
    public function set_report_bar_tpl($tpl): static {
        $this->_tpl_report_bar = trim((string)$tpl);
        return $this;
    }

    /** Setzt den Reportfooter; ein leerer Wert deaktiviert ihn. */
    public function set_report_footer_tpl($tpl): static {
        $this->_tpl_report_footer = trim((string)$tpl);
        return $this;
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
            $web_app = dbx()->get_system_obj('dbxWebApp');
            $action = $web_app->append_route_params($action, array(
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






    public function forward_run_report_header($content) {
        return $this->rpt_merge_obj($content);
    }

    public function forward_run_page_header($content) {
        return $this->rpt_merge_obj($content);
    }

    public function forward_run_page_footer($content) {
        return $this->rpt_merge_obj($content);
    }

    public function run_report_header($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('report_header', $content) : $content;
        return $this->forward_run_report_header($content);
    }

    public function run_page_header($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('page_header', $content) : $content;
        return $this->forward_run_page_header($content);
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

    public function run_header($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('header', $content) : $content;
        return $this->forward_run_header($content);
    }

    public function run_body($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('body', $content) : $content;
        return $this->forward_run_body($content);
    }

    public function run_footer($content) {
        $content = ($this->_fid != 'pagination') ? $this->callback('footer', $content) : $content;
        return $this->forward_run_footer($content);
    }

    public function forward_run_header($content) {
        return $this->rpt_merge_obj($content);
    }

    public function forward_get_footer_page() {
        $footer  = $this->_footer_next_page;
        $footer .= $this->_footer_page;
        $footer  = $this->rpt_merge_obj($footer);

        return $footer;
    }

    public function forward_get_header_page() {
        $header  = $this->_header_page;
        $header .= $this->_header_next_page;
        $header  = $this->get_report_header($header);
        $header  = $this->rpt_merge_obj($header);

        return $header;
    }

    public function get_footer_page() {
        return $this->forward_get_footer_page();
    }

    public function get_header_page() {
        return $this->forward_get_header_page();
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
        $page_header = '';
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
                $page_header            = $this->get_header_page();
            }

            $content = $this->rpt_merge_obj($content);
        }

        return $page_footer . $page_header . $content;
    }

    public function forward_run_footer($content) {
        return $this->rpt_merge_obj($content);
    }

    public function set_class_header($key, $class = '') {
        if (!$class) {
            $class = 'th-' . $key;
        }

        $this->_class_header[$key] = $class;
    }

    private function get_class_header($key) {
        $class = '';

        if (is_array($this->_class_header)) {
            if (isset($this->_class_header[$key])) {
                $class = $this->_class_header[$key];
            }
        }

        if (!$class) {
            $class = 'th-' . $key;
        }

        return $class;
    }

    public function set_style_header($key, $style) {
        $this->_style_header[$key] = $style;
    }

    private function get_style_header($key) {
        $style = '';

        if (is_array($this->_style_header)) {
            if (isset($this->_style_header[$key])) {
                $style = $this->_style_header[$key];
            }
        }

        return $style;
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

        $this->_fld_header       = '';
        $this->_header_report    = '';
        $this->_footer_report    = '';
        $this->_header_page      = '';
        $this->_footer_page      = '';
        $this->_header_next_page = '';
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
            $o_db = dbx()->get_system_obj('dbxDB');
            if (method_exists($o_db, 'get_dd_file')) {
                $this->add_editor_file('dd', $o_db->get_dd_file($this->_dd));
            }
        }

        $this->build_module_bar_obj();
        $this->build_grid_bar_obj();
        $this->build_report_footer_obj();
        $this->prepare_report_frame_replaces((int)$i);
        $this->prepare_report_chrome_replaces();
        // Uebergangsvertrag fuer individuelle Form-/Report-Hybridtemplates.
        $this->prepare_form_chrome_replaces();

        $replaces = $this->_replaces;

        if ($mode == 'tabulator') {
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
        ) as $shell_key => $shell_default) {
            if (!isset($replaces[$shell_key]) || (string) $replaces[$shell_key] === '') {
                $replaces[$shell_key] = $shell_default;
            }
        }

        if (strpos($tpl, '|') === false) {
            $tpl = $this->_dbx_modul . '|' . $tpl;
        }

        $report_tpl = $this->get_tpl($tpl, $replaces, 'htm', $i);
        $report_tpl = $this->merge_tpl_data($report_tpl, $i);
        $report_tpl = $this->inject_report_footer($report_tpl);
        $report_tpl = $this->merge_fld_data($report_tpl, $i);
        $report_tpl = $this->merge_obj($report_tpl, $i);
        $report_tpl = $this->merge_db_placeholders($report_tpl);
        $report_tpl = $this->cleanup_report_bar_slots($report_tpl);
        $report_tpl = $this->apply_report_count_replaces($report_tpl);

        if ($mode == 'tabulator') {
            $content = $report_tpl;

            if ($fid != 'pagination') {
                $this->store_sysdata();
            }

            $content = str_replace('{i}', $i, $content);

            $msg_tpl = $this->build_report_form_msg_html();
            $content = str_replace('{obj:form_msg}', $msg_tpl, $content);

            if ($pages) {
                $report_pages = $this->get_report_pages();
                $content     = str_replace('[dbx:pagination]', $report_pages, $content);
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

        $header = $this->get_report_header();
        $body   = $this->get_report_body();
        $footer = $this->get_report_footer();

        $content = $header . $body . $footer;

        if ($fid != 'pagination') {
            $this->store_sysdata();
        }

        if ($fid != 'pagination') {
            if ($pages) {
                $report_pages = $this->get_report_pages();
                $content     = str_replace('[dbx:pagination]', $report_pages, $content);
            }

            $header_report = $this->_header_report;
            $header_page   = $this->_header_page;
            $footer_page   = $this->_footer_page;
            $footer_report = $this->_footer_report;

            $content = str_replace('{i}', $i, $content);

            $msg_tpl = $this->build_report_form_msg_html();
            $content = str_replace('{obj:form_msg}', $msg_tpl, $content);

            $header_report = $this->run_report_header($header_report);
            $header_page   = $this->run_page_header($header_page);
            $footer_page   = $this->run_page_footer($footer_page);
            $footer_report = $this->run_report_footer($footer_report);

            $content = str_replace('[rpt:header_report]', $header_report, $content);
            $content = str_replace('[rpt:header_page]',   $header_page,   $content);
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
