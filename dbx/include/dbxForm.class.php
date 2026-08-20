<?php

require_once __DIR__ . '/dbxFormSecurity.trait.php';
require_once __DIR__ . '/dbxFormWorkflowState.trait.php';
require_once __DIR__ . '/dbxFormValidation.trait.php';
require_once __DIR__ . '/dbxFormFieldResolver.trait.php';
require_once __DIR__ . '/dbxFormMessages.trait.php';
require_once __DIR__ . '/dbxFormRendering.trait.php';

/**
 * @brief Zentrale zustandsbehaftete Formular-, Validierungs- und Persistenzpipeline von dbxapp.
 *
 * =========================================================
 * DBX FORM SYSTEM (dbxForm)
 * =========================================================
 *
 * Zweck
 * -----
 * Die Klasse dbxForm ist die zentrale serverseitige Formular-Basis
 * für DBX-Module.
 *
 * Sie ist bewusst als offenes, zustandsbehaftetes Workflow-Objekt
 * gedacht und NICHT als starres Blackbox-Form-Framework.
 *
 * Typische Aufgaben
 * -----------------
 * - Formular-Kontext initialisieren
 * - DD-/FD-basierte Felddefinitionen ergänzen
 * - Request/Submit erkennen
 * - Feldwerte lesen und validieren
 * - geänderte Werte in `_post` sammeln
 * - Formularstatus/Meldungen verwalten
 * - optional über dbxDB speichern
 * - HTML-Ausgabe über dbxTPL erzeugen
 *
 * Architektur
 * -----------
 * Die Klasse orientiert sich an 5 klaren Phasen:
 *
 * 1. Setup
 *    - init()
 *    - _dd / _fd / _data / _action / Meldungen setzen
 *    - add_fld(), add_flds(), add_obj(), JS vorbereiten
 *
 * 2. Request auswerten
 *    - submit()
 *    - errors()
 *    - warnings()
 *    - changed()
 *
 * 3. Modul-Fachlogik
 *    - Modul kann nach Standardprüfung `_post` ergänzen/ändern
 *    - Speziallogik wie Hashing, Mapping, Folgeaktionen
 *
 * 4. Persistenz
 *    - save_post()
 *
 * 5. Ausgabe
 *    - run()
 *    - Template, Felder, Meldungen, Objekte, JS
 *
 * Wichtige Grundsätze
 * -------------------
 * - DD-Infrastruktur liegt zentral in dbxDB
 * - dbxForm nutzt dbxDB als DD-/Persistenzservice
 * - FD ist eine optionale, bevorzugte Felddefinitionsquelle
 * - Wenn _fd gesetzt ist, nutzt dbxForm _fd für Feld-Metadaten
 * - Wenn _fd leer ist und _dd gesetzt ist, nutzt dbxForm _dd wie bisher
 * - Wenn weder _fd noch _dd gesetzt ist, entstehen Felder nur aus manuellen add_fld()-Parametern
 * - Automatisch erzeugte Felder und manuelle add_fld()-Felder sind frei mischbar
 * - dbxForm bleibt offen für Modulcode
 * - Rendern darf den Kernzustand nicht zerstören
 * - Workflow-/Form-Zustände werden über DBX-State
 *   (`dbx_set_Remember` / `dbx_get_Remember`) gehalten
 *
 * FD-/DD-Felddefinitionen
 * ----------------------
 * DD und FD liefern in dbxForm dieselbe Art Feld-Metadaten:
 * - tpl
 * - label
 * - rules
 * - class
 * - data
 * - options
 * - tooltip
 * - placeholder
 * - errormsg
 * - remap
 *
 * _dd bleibt der Daten-/DB-Kontext und ist weiterhin der Fallback für
 * Felddefinitionen. _fd ist eine zusätzliche, generische und bevorzugte
 * Felddefinitionsquelle für beliebige Formular-/UI-Feldgruppen, z. B. Login,
 * Filter, Report-Selektionen, Umfragen oder Wizard-Schritte.
 *
 * Sprachabhängige FD und Meldungen
 * ---------------------------------
 * FD-Dateien werden zentral über `dbx()->lng_resolve_file()` aufgelöst:
 * `name_de.fd.php`, `name_en.fd.php`, `name_es.fd.php`, danach die neutrale
 * `name.fd.php` als Fallback. Neben `$fields` darf jede FD ein
 * `$messages`-Array liefern. Fachmeldungen bleiben in der FD; die globalen
 * Speicher-, Validierungs- und Löschmeldungen kommen aus sprachabhängigen
 * Templates des Moduls dbx.
 * dbxReport erbt Auflösung, Cache und Meldungsnutzung direkt von dbxForm.
 *
 * Sicherheitsvertrag: dbxForm und Action-Token
 * ---------------------------------------------
 * Ein normaler POST-Submit wird ausschliesslich durch das automatisch
 * erzeugte, formularspezifische Security-Feld von dbxForm geschuetzt.
 * `submit()` prueft dieses Feld mit `hash_equals()` und rotiert es nach einem
 * erfolgreichen Submit. Modulcode fuegt deshalb keinen zusaetzlichen
 * `dbx_token` an normale Formular-Actions an.
 *
 * `dbx_token` schuetzt dagegen automatisch erkannte zustandsaendernde
 * Link-Aktionen, die auch als GET funktionieren muessen. `delete`/`save`
 * plus `rid` werden zentral von dbxWebApp erkannt und von
 * dbxApi::action_url(), dbxForm beziehungsweise dbxReport tokenisiert.
 * Normale Form-Routen bleiben unveraendert; Modulcode fuegt keinen Token hinzu.
 * Ein Report-Footer-Link kann als Ajax-POST beide Schutzschichten enthalten:
 * Der Action-Token beweist die konkrete Link-Aktion, der dbxForm-Token den
 * gueltigen Submit des Reportformulars.
 *
 * Workflow-/Draft-Gedanke
 * -----------------------
 * dbxForm unterstützt neben `rid` auch einen Draft-/Form-Kontext.
 * Das ist wichtig für Fälle wie:
 *
 * - neuer Datensatz ohne echte DB-ID, aber mit Uploads
 * - mehrere abhängige Formulare innerhalb eines Workflows
 *
 * Dafür gibt es einen Remember-basierten Formularstatus mit Feldern wie:
 * - draft_id
 * - rid
 * - is_valid
 * - is_complete
 * - is_locked
 * - is_saved
 * - version
 * - depends_on
 * - depends_version
 *
 * Beispiel: einfache Nutzung mit DD
 * ---------------------------------
 * ```php
 * $oForm = dbx()->get_system_obj('dbxForm');
 * $o_db   = dbx()->get_system_obj('dbxDB');
 *
 * $rid   = dbx()->get_modul_var('rid', 0, 'int');
 * $data  = $o_db->select1('dbx_user', $rid);
 *
 * $oForm->init('form-user');
 * $oForm->_dd       = 'dbx_user';
 * $oForm->_data     = $data;
 * $oForm->_action   = '?dbx_modul=dbxUser&dbx_run1=edit&rid=' . $rid;
 * $oForm->_msg_info = 'Bitte prüfen Sie die Daten.';
 *
 * $oForm->add_fld('name',  'text-label');
 * $oForm->add_fld('email', 'text-label');
 *
 * if ($oForm->submit()) {
 *     if (!$oForm->errors() && !$oForm->warnings()) {
 *         if ($oForm->changed()) {
 *             $oForm->save_post('dbx_user', $rid);
 *             $oForm->_msg_success = 'Daten gespeichert';
 *         } else {
 *             $oForm->_msg_success = 'Keine Änderung';
 *         }
 *     } else {
 *         $oForm->_msg_error = 'Bitte Eingaben prüfen';
 *     }
 * }
 *
 * echo $oForm->run();
 * ```
 *
 * Beispiel: automatische Felder aus FD
 * ------------------------------------
 * ```php
 * $oForm->init('form-login');
 * $oForm->_fd = 'dbx|login';
 * $oForm->add_flds();
 * $oForm->add_fld('remember_me', 'checkbox-label', label: 'Angemeldet bleiben', rules: 'int');
 * echo $oForm->run();
 * ```
 *
 * Beispiel: neuer Datensatz mit Draft-Kontext
 * -------------------------------------------
 * ```php
 * $oForm->init('form-profile');
 * $draftId = $oForm->get_draft_id(true); // erzeugt Draft-ID, falls noch keine da ist
 *
 * // Uploads können jetzt auf $draftId referenzieren,
 * // auch wenn noch keine echte RID existiert.
 * ```
 *
 * Beispiel: abhängige Formulare
 * -----------------------------
 * ```php
 * $oForm1->set_workflow_scope('order-setup');
 * $oForm2->set_workflow_scope('order-setup');
 *
 * // Form 2 hängt von Form 1 ab
 * $oForm2->set_dependencies(['form-step-1']);
 *
 * // Nach gültigem Abschluss von Form 1:
 * $oForm1->set_form_valid(true);
 * $oForm1->set_form_complete(true);
 * $oForm1->bump_form_version();
 *
 * // Form 2 kann prüfen, ob seine Grundlage noch aktuell ist:
 * if (!$oForm2->dependencies_are_current()) {
 *     $oForm2->set_form_locked(true);
 * }
 * ```
 */
class dbxForm extends \dbxObj {

    use dbxFormSecurityTrait;
    use dbxFormWorkflowStateTrait;
    use dbxFormValidationTrait;
    use dbxFormFieldResolverTrait;
    use dbxFormMessagesTrait;
    use dbxFormRenderingTrait;

    /** Zentraler Validator */
    public $o_validator;

    /** Zentrale Template-Engine */
    public $o_tpl;

    /** Zentraler DB-/DD-Service */
    public $o_db;

    /** Optionaler View-Schlüssel */
    public $_dbx_view = '';

    /** Optionaler View-Sync-Status */
    public $_view_sync = '';

    /** Optionaler View-Modus */
    public $_view_mode = '';

    /** tpl|php|mix */
    protected $_mode = 'mix';

    /** AJAX-fähiges Formular */
    public $_ajax = true;

    /** Aktueller Sysdata-Speichermodus */
    public $_store_mode = 'session';

    /** Aktive Modul-ID */
    public $_dbx_modul_id = 0;

    /** Aktives Modul */
    public $_dbx_modul = 'modul';

    /** Aktive Sprache */
    public $_dbx_lng = '';

    /** Aktives Design */
    public $_dbx_design = '';

    /** Aktive DBX-Aktion */
    public $_dbx_action = '';

    /** Optionaler Work-Kontext */
    public $_dbx_work = '';

    /** Aktive Seite */
    public $_dbx_page = '';

    /** Aktuelle Record-ID */
    protected $_rid = 0;

    /** Formular-Action */
    protected $_action = '';

    /** Standardtemplate; die Form-ID bleibt davon unabhaengige State-Identitaet. */
    protected $_tpl = 'dbx|form-default';

    /** Formular-ID */
    public $_fid = '';

    /** Laufende Formularinstanz-ID */
    public $_next_i = 0;

    /** Zugeordnetes DD. Bleibt Daten-/DB-Kontext und Fallback-Feldquelle. */
    protected $_dd = '';

    /** Optionale FD-Felddefinitionsquelle. Wenn gesetzt, bevorzugt add_fld()/add_flds() diese Quelle vor _dd. */
    protected $_fd = '';

    /** Dateien, die fuer den Frontend-Editor markiert werden */
    public $_editor_files = array();

    /** Modul-/Include-Class-Datei fuer den Frontend-Editor */
    public $_editor_class_file = '';

    /** Aktuelle Formulardaten / Record-Daten */
    protected $_data = array();

    /** Validierte/geänderte POST-Werte */
    protected $_post = array();

    /** Form-interne Zustände / Sysdata */
    public $_sys = array();

    /** Platzhalter-Objekte */
    public $_obj = array();

    /** JS-Code-Blöcke */
    public $_js = array();

    /** CSS-Dateien oder Marker */
    public $_css = array();

    /** Felddefinitionen */
    public $_flds = array();

    /**
     * Meldungen der aktuell geladenen FD.
     *
     * Sprachvarianten werden zusammen mit der FD aufgelöst. dbxReport erbt
     * diesen Zustand und denselben Speicherpfad direkt von dbxForm.
     */
    protected $_messages = array();

    /** Info-Meldungen */
    public $_infos = array();

    /** Feld-/Formfehler */
    public $_errors = array();

    /** Strukturierte Validator-Ergebnisse, indiziert nach Feldname */
    protected $_validation_results = array();

    /** Zuständig nur für Herkunft und Validierung einzelner Feldwerte. */
    private ?dbxFormValueResolver $value_resolver = null;

    /** Warnungen */
    public $_warnings = array();

    /** Allgemeine Replace-Werte */
    public $_replaces = array();

    /** Allgemeiner Fehlertext */
    public $_general_error = '';

    /** Standard-Infotext */
    public $_msg_info = '#form_msg_info#';

    /** Standard-Erfolgstext */
    public $_msg_success = '#form_msg_success#';

    /** Standard-Fehlertext */
    public $_msg_error = '#form_msg_error#';

    /** Standard-Warntext */
    public $_msg_warning = '#form_msg_warning#';

    /** Template für Info-Meldungen */
    public $_tpl_form_info = 'alert-info';

    /** Template für Erfolgsmeldungen */
    public $_tpl_form_success = 'alert-success';

    /** Template für Fehlermeldungen */
    public $_tpl_form_error = 'alert-danger';

    /** Template für Warnmeldungen */
    public $_tpl_form_warning = 'alert-warning';

    /** Template für Feld-Info */
    public $_tpl_fld_info = 'fld-alert-info';

    /** Template für Feld-Erfolg */
    public $_tpl_fld_success = 'fld-alert-success';

    /** Template für Feld-Fehler */
    public $_tpl_fld_error = 'fld-alert-danger';

    /** Template für Feld-Warnung */
    public $_tpl_fld_warning = 'fld-alert-warning';

    /** Template für Max-Try-Sperre */
    public $_tpl_max_try = 'form-alert-maxtry';

    /** Gemeinsame Formularleiste; das Modul bestimmt nur Inhalt und Aktionen. */
    public $_tpl_form_bar = 'dbx|form-bar-default';

    /** Gemeinsamer Formularabschluss unterhalb des individuellen Layouts. */
    public $_tpl_form_footer = 'dbx|form-footer-default';

    /** Sekunden bis Try-Reset */
    public $_try_reset = 120;

    /** Sekunden ohne Fehlversuch, nach denen Try-Zaehler und Sperrstufe neu beginnen */
    public $_try_count_reset = 600;

    /** Maximale Fehlversuche */
    public $_try_max = 20;

    /** Sperr-Text */
    public $_try_msg = 'Max {try_count} try. Suspend for {sec} seconds';

    /** 1 = Felder sollen erzeugt werden */
    public $_create_flds = 1;

    /** 1 = Datensatz nach Save neu lesen */
    public $_reload_record = 1;

    /** 1 = Reload wurde ausgeführt */
    public $_reload_run = 0;

    /** Historischer Reload-Transform-Flag */
    public $_reload_transform = 0;

    /** Optionen fuer Standard-Aktionen in der Modul-Bar */
    protected $_module_bar_form_actions = array();

    /** Help-Button des aktuellen Formulars fuer Templates ohne eigenen Help-Slot. */
    protected $_form_help_button = '';

    /** Bereits gerenderte Standardaktionen fuer die gemeinsame Formularleiste. */
    protected $_form_bar_actions = '';

    /** 1 = automatische Formularhilfe rendern; 0 = Hilfe kommt aus der umgebenden Bereichsleiste. */
    public $_form_help_enabled = 1;

    /**
     * 1 = Felder mit FD-Flag `data=ui_persist=1` merken ihren Wert dauerhaft
     * im Browser (dbx.uiGet/uiSet, siehe core.js) und stellen ihn beim
     * naechsten Laden automatisch wieder her. Default 0: bestehende
     * Formulare aendern ihr Verhalten nicht, bis ein Formular dies bewusst
     * einschaltet. Das Feld-Flag entscheidet zusaetzlich pro Feld, ob es
     * mitmacht (siehe create_fld()).
     */
    public $_ui_state_persist = 0;

    /** Historischer Reload-Suffix */
    public $_reload_suffix = '_rlo';

    /** Feldmodus fuer die Changed-Logik: `fld` oder `*`. */
    public $_fld_change_state = 'fld';

    /** Gecachter Changed-Wert */
    public $_fld_changes = -1;

    /** -1 = unbekannt, 0 = nein, 1 = ja */
    public $_form_submit = -1;

    /** -1 = noch nicht ausgewertet, 1 = ausgewertet */
    public $_form_validate = -1;

    /** Optionales Editor-Feld */
    public $_editor_fld = '';

    /** Historischer Page-Reset-Flag */
    public $_page_reset = 1;

    /** Optionaler Lösch-Bestätigungstext */
    public $_confirm_delete = '';

    /** Optionaler Copy-Bestätigungstext */
    public $_confirm_copy = '';

    /** Optional aktive ID */
    public $_activ_id = 0;

    /** Primärschlüsselfeld */
    public $_fld_id = 'id';

    /** Workflow-Scope für Remember-State */
    public $_workflow_scope = '';

    /** Remember-basierter Form-Status */
    public $_workflow_state = array();

    /**
     * Initialisiert Validator, TPL und DB-Service.
     *
     * Verwendung
     * ----------
     * Normalerweise wird dbxForm über `dbx()->get_system_obj('dbxForm')`
     * bezogen. Optional kann direkt eine Formular-ID übergeben werden.
     *
     * Auswirkung
     * ----------
     * Die zentralen Services werden referenziert. Wird `$id` übergeben,
     * wird sofort `init($id, $tpl)` ausgeführt.
     *
     * @param string $id Optional Formular-ID für Sofort-Init
     * @param string $tpl Optional Template-Name
     *
     */
    public function __construct($id = '', $tpl = '') {
        $this->o_validator = dbx()->get_system_obj('dbxValidator');
        $this->o_tpl       = dbx()->get_system_obj('dbxTPL');
        $this->o_db        = dbx()->get_system_obj('dbxDB');

        if (!$tpl) {
            $tpl = $id;
        }

        if ($id) {
            $this->init($id, $tpl);
        }
    }

    /**
     * Destruktor.
     *
     * Verwendung
     * ----------
     * Aktuell gibt es keine explizite Aufräumlogik.
     *
     * Auswirkung
     * ----------
     * Keine.
     *
     */
    public function __destruct() {
    }

    /**
     * Leert nur den Sysdata-Bereich.
     *
     * Zweck
     * -----
     * Nützlich, wenn bewusst nur temporäre Form-Zustände zurückgesetzt
     * werden sollen, ohne das ganze Formularobjekt neu aufzubauen.
     *
     * Auswirkung
     * ----------
     * `_sys` wird geleert. `_data`, `_flds`, Workflow-State und andere
     * Laufzustände bleiben unverändert.
     *
     * @return void
     */
    public function clear_sys() {
        $this->_sys = array();
    }

    /**
     * Öffentlicher Reset-Einstieg.
     *
     * Verwendung
     * ----------
     * Wird von `forward_init()` genutzt und kann auch manuell aufgerufen werden,
     * wenn ein Formularlauf neu aufgebaut werden soll.
     *
     * Auswirkung
     * ----------
     * Ruft `_forward_clear()` auf und löscht den laufenden Formzustand
     * inklusive `_dd` und `_fd`, damit bei wiederverwendeten Systemobjekten
     * keine alte DD-/FD-Feldquelle in den nächsten Formularlauf durchrutscht.
     *
     * Workflow-Hinweis
     * ----------------
     * Der Workflow-Scope wird hier bewusst nicht zurückgesetzt, weil er sich
     * innerhalb zusammenhängender Formularabläufe fachlich weitervererben darf.
     *
     * @return void
     */
    public function clear() {
        $this->_forward_clear();
    }



    /**
     * Setzt den internen Formularzustand zurück.
     *
     * Sinn/Zweck
     * ----------
     * Dieser Reset betrifft den Formlauf selbst. Remember-basierte Workflow-
     * Zustände werden NICHT automatisch gelöscht, da sie fachlich über den
     * aktuellen Request hinaus gültig sein können.
     *
     * Auswirkung
     * ----------
     * Leert Objekte, JS, CSS, Felder, Meldungen, Fehler, Warnungen, `_post`,
     * `_sys`, `_data` und `_replaces`.
     *
     * Wichtig
     * -------
     * `_dd` und `_fd` werden ebenfalls geleert, weil dbxForm als zentrales
     * Systemobjekt wiederverwendet werden kann. Dadurch können keine alten
     * DD-/FD-Quellen in einen neuen Formularlauf durchrutschen.
     *
     * Zusätzlich werden laufbezogene Einzelwerte wie `_rid`, `_action`,
     * `_next_i` und `_fld_id` zurückgesetzt, damit keine alte Formularinstanz,
     * kein alter Datensatz und kein alter Primärschlüssel in den nächsten
     * Formularlauf übernommen werden.
     *
     * Konsequenz
     * ----------
     * Module setzen `_dd` und/oder `_fd` nach `init()` neu.
     *
     * @return void
     */
    public function _forward_clear() {
        $this->_reload_record = 0;
        $this->_reload_run    = 0;

        $this->_obj       = array();
        $this->_js        = array();
        $this->_css       = array();
        $this->_flds      = array();
        $this->_messages  = array();
        $this->_infos     = array();
        $this->_errors    = array();
        $this->_validation_results = array();
        $this->_warnings  = array();
        $this->_post      = array();
        $this->_sys       = array();
        $this->_data      = array();
        $this->_replaces  = array();
        $this->_module_bar_form_actions = array();
        $this->_form_help_button = '';
        $this->_form_bar_actions = '';
        $this->_form_help_enabled = 1;

        $this->_fld_changes   = -1;
        $this->_form_submit   = -1;
        $this->_form_validate = -1;
        $this->_general_error = '';
        $this->_msg_info      = '#form_msg_info#';
        $this->_msg_success   = '#form_msg_success#';
        $this->_msg_error     = '#form_msg_error#';
        $this->_msg_warning   = '#form_msg_warning#';
        $this->_tpl_form_bar = 'dbx|form-bar-default';
        $this->_tpl_form_footer = 'dbx|form-footer-default';

        $this->_rid     = 0;
        $this->_action  = '';
        $this->_next_i  = 0;
        $this->_fld_id  = 'id';
        $this->_callback_owner = null;
        $this->_callback_id    = '';
        $this->_callbacks      = array();

        $this->_dd = '';
        $this->_fd = '';
        $this->_editor_files = array();
    }

    /**
     * Merkt sich die geladene Modul-/Include-Class fuer den Frontend-Editor.
     *
     * @param string $file Absoluter oder relativer Dateipfad
     * @return void
     */
    public function set_editor_class_file(string $file) {
        $this->_editor_class_file = $file;
        $this->add_editor_file('class', $file);
    }



    public function set_form_callback_owner($owner): void {
        $this->set_callback_owner($owner);
    }

    public function set_init_callback(string $callback): void {
        $this->set_callback('init', $callback);
    }

    public function set_submit_callback(string $callback): void {
        $this->set_callback('submit', $callback);
    }

    public function set_run_callback(string $callback): void {
        $this->set_callback('run', $callback);
    }

    /**
     * Setzt DD und optionale FD gemeinsam als lesbare Datenquelle.
     */
    public function set_data_source(string $dd, string $fd = ''): static {
        $this->_dd = trim($dd);
        $this->_fd = trim($fd);
        return $this;
    }

    /** Setzt die DD-Datenquelle. */
    public function set_data_definition(string $dd): static {
        $this->_dd = trim($dd);
        return $this;
    }

    /** Setzt die optionale FD-Felddefinition. */
    public function set_field_definition(string $fd): static {
        $this->_fd = trim($fd);
        return $this;
    }

    /** Liefert die konfigurierte DD-Datenquelle. */
    public function get_data_definition(): string {
        return (string)$this->_dd;
    }

    /** Liefert die konfigurierte FD-Felddefinition. */
    public function get_field_definition(): string {
        return (string)$this->_fd;
    }

    /** Ersetzt den sprachabhängigen FD-Meldungskatalog. */
    public function set_fd_messages(array $messages): static {
        $this->_messages = $messages;
        return $this;
    }

    /** Liefert den sprachabhängigen FD-Meldungskatalog. */
    public function get_fd_messages(): array {
        return $this->_messages;
    }

    /** Setzt die Ziel-URL des Formulars oder Reports. */
    public function set_action(string $action): static {
        $this->_action = trim($action);
        return $this;
    }

    /** Liefert die konfigurierte Ziel-URL. */
    public function get_action(): string {
        return (string)$this->_action;
    }

    /** Setzt den Render-Modus. */
    public function set_mode(string $mode): static {
        $mode = trim($mode);
        $this->_mode = $mode !== '' ? $mode : 'mix';
        return $this;
    }

    /** Liefert den Render-Modus. */
    public function get_mode(): string {
        return (string)$this->_mode;
    }

    /** Setzt bewusst ein individuelles Haupttemplate. */
    public function set_template(string $template): static {
        $template = trim($template);
        $this->_tpl = $template !== '' ? $template : $this->default_tpl($this->_fid);
        return $this;
    }

    /** Liefert das aktuell konfigurierte Haupttemplate. */
    public function get_template(): string {
        return (string)$this->_tpl;
    }

    /** Ersetzt die aktuellen Form-/Reportdaten vollständig. */
    public function set_data(array $data): static {
        $this->_data = $data;
        return $this;
    }

    /** Ergänzt oder überschreibt einzelne Form-/Reportdaten. */
    public function merge_data(array $data): static {
        $this->_data = array_merge($this->_data, $data);
        return $this;
    }

    /** Setzt einen einzelnen Datenwert. */
    public function set_data_value(string $key, mixed $value): static {
        $this->_data[$key] = $value;
        return $this;
    }

    /** Entfernt einen einzelnen Datenwert. */
    public function unset_data_value(string $key): static {
        unset($this->_data[$key]);
        return $this;
    }

    /** Liefert alle Daten oder einen einzelnen Wert. */
    public function get_data(?string $key = null, mixed $default = null): mixed {
        if ($key === null) {
            return $this->_data;
        }
        return $this->_data[$key] ?? $default;
    }

    /** Setzt die aktuelle Record-ID ohne Request- oder View-Auflösung. */
    public function set_rid(int $rid): static {
        $this->_rid = $rid;
        $this->set_state_value('rid', $rid);
        return $this;
    }

    /** Liefert die aktuelle Record-ID ohne den Zustand aus dem Request zu verändern. */
    public function current_rid(): int {
        return (int)$this->_rid;
    }

    /** Setzt einen bereits validierten/geänderten POST-Wert. */
    public function set_post_value(string $key, mixed $value): static {
        $this->set_post($key, $value);
        return $this;
    }

    /** Entfernt einen bereits validierten/geänderten POST-Wert. */
    public function unset_post_value(string $key): static {
        unset($this->_post[$key]);
        $this->_fld_changes = -1;
        return $this;
    }

    /** Prüft, ob ein validierter/geänderter POST-Wert vorliegt. */
    public function has_post_value(string $key): bool {
        return array_key_exists($key, $this->_post);
    }

    /** Liefert einen validierten/geänderten POST-Wert. */
    public function post_value(string $key, mixed $default = null): mixed {
        return $this->_post[$key] ?? $default;
    }

    /** Liefert alle validierten/geänderten POST-Werte. */
    public function validated_post(): array {
        return $this->_post;
    }

    /**
     * Ermittelt den aufrufenden Modul-/Service-Owner fuer Callback-Defaults.
     *
     * Der direkte Aufrufer von init() ist stabiler als ein spaeter global
     * gesetzter Owner: DD-, TPL- oder Hilfe-Code kann den globalen Kontext
     * waehrend des Form-/Reportaufbaus wechseln. Ein explizit gesetzter
     * Callback-Owner hat weiterhin Vorrang.
     *
     * @return object|null
     */
    protected function resolve_init_callback_owner() {
        $trace = debug_backtrace(
            DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS,
            8
        );
        foreach ($trace as $frame) {
            $owner = $frame['object'] ?? null;
            if (is_object($owner)
                && $owner !== $this
                && !($owner instanceof dbxApi)) {
                return $owner;
            }
        }

        return dbx()->get_current_owner();
    }



    /**
     * Initialisiert den Form-Kontext.
     *
     * Ablauf
     * ------
     * - internen Formzustand resetten
     * - direkten Aufrufer als Callback-Owner uebernehmen
     * - DBX-Kontext übernehmen
     * - Sysdata laden
     * - Remember-Workflow-State laden
     * - Security-Feld für Submit-Schutz anlegen
     *
     * Verwendung
     * ----------
     * Nach `init()` kann der Modulcode `_dd`, `_fd`, `_data`, `_action`,
     * Meldungen und Felder setzen und das Formular weiter aufbauen.
     *
     * Auswirkung
     * ----------
     * Der Formularlauf ist vorbereitet. Das Security-Feld wird als echtes
     * dbxForm-Feld erzeugt und später durch die normale Feldpipeline gerendert.
     * Das Security-Feld nutzt bewusst keine DD-/FD-Quelle, damit es nie durch
     * externe Felddefinitionen beeinflusst wird.
     * Callbackmethoden folgen ohne weitere Registrierung `{fid}_{event}`;
     * Bindestriche der Formular-ID werden dabei zu Unterstrichen.
     *
     * @param string $fid Formular-ID
     * @param string $tpl Optionales Template
     *
     * @return void
     */
    public function forward_init($fid, $tpl = '') {
        $this->clear();

        // Der beim Aufbau aktive Modul-/Service-Owner ist der sichere Default
        // fuer alle spaeteren Form- und Report-Callbacks. Explizites
        // set_callback_owner() kann diesen Wert weiterhin ueberschreiben.
        $owner = $this->resolve_init_callback_owner();
        if (is_object($owner) && $owner !== $this) {
            $this->set_callback_owner($owner);
        }

        $tpl = trim((string)$tpl);
        if ($tpl === '') {
            $tpl = $this->default_tpl((string)$fid);
        }

        $i = $this->next_i();

        $this->_fid          = $fid;
        $this->set_callback_id($fid);
        $this->_dbx_modul    = dbx()->get_system_var('dbx_activ_modul', 'modul');
        $this->_dbx_action   = dbx()->get_system_var('dbx_activ_action', 'run');
        $this->_dbx_page     = dbx()->get_system_var('dbx_page', 'default');
        $this->_dbx_design   = dbx()->get_system_var('dbx_design', 'default');
        $this->_dbx_lng      = dbx()->get_system_var('dbx_lng', 'de');
        $this->_dbx_modul_id = dbx()->get_system_var('dbx_activ_modul_id', 1);
        $this->_tpl          = $tpl;
        $this->_data         = array();
        $this->_next_i       = $i;
        $this->_form_validate = -1;
        $this->_form_submit   = -1;


        $init = array(
            'fid' => $this->_fid,
            'tpl' => $this->_tpl,
            'i'   => $this->_next_i,
        );

        $init = $this->callback('init', $init);

        if (is_array($init)) {
            if (isset($init['fid'])) $this->_fid = $init['fid'];
            if (isset($init['tpl'])) $this->_tpl = $init['tpl'];
            if (isset($init['i']))   $this->_next_i = $init['i'];
        }

        $this->_sys = $this->load_sysdata();

        $this->load_workflow_state();

        $secure_fld = $this->secure_fld_name();
        $this->add_fld($secure_fld, 'dbx|hidden', $secure_fld, rules: 'parameter', dd: '');
        $this->sync_secure_field($secure_fld, $this->secure_token($secure_fld));
        $this->attach_admin_help_button();
        $this->add_default_search_rep();
    }





























































    /**
     * Platzhalter-Helfer.
     *
     * @param mixed $datum Eingabe
     *
     * @return string
     */
    public function get_quartal($datum) {
        return '3/25';
    }

    /**
     * Wandelt Datumswerte in deutsches Format um.
     *
     * @param mixed $value Eingabewert
     *
     * @return string
     */
    public function php_date_usr($value) {
        if (trim((string) $value) !== '') {
            $timestamp = strtotime((string) $value);

            if ($timestamp !== false) {
                $value = date('d.m.Y', $timestamp);
                $value = substr($value, 0, 10);
            }
        }

        return (string) $value;
    }

    /**
     * Wandelt Datums-/Zeitwerte in deutsches Format um.
     *
     * @param mixed $value Eingabewert
     *
     * @return string
     */
    public function php_datetime_usr($value) {
        $raw = trim((string) $value);

        if ($raw !== '') {
            $ms = '';

            if (preg_match('/\.(\d+)$/', $raw, $match)) {
                $ms = '.' . $match[1];
            }

            $timestamp = strtotime($raw);

            if ($timestamp !== false) {
                if (preg_match('/^\d{2}:\d{2}(:\d{2})?(\.\d+)?$/', $raw)) {
                    $value = date('H:i' . (substr_count($raw, ':') == 2 ? ':s' : ''), $timestamp) . $ms;
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$|^\d{2}\.\d{2}\.\d{4}$/', $raw)) {
                    $value = date('d.m.Y', $timestamp);
                } else {
                    $value = date('d.m.Y H:i' . (substr_count($raw, ':') >= 2 ? ':s' : ''), $timestamp) . $ms;
                }
            }
        }

        return (string) $value;
    }

    /**
     * Wandelt Datumswerte in DB-Format um.
     *
     * @param mixed $value Eingabe
     *
     * @return string
     */
    public function php_date($value) {
        if ($value) {
            if ($value != 'today') {
                $timestamp = strtotime((string) $value);
            } else {
                $timestamp = time();
            }

            $value = date('Y-m-d', $timestamp);
        }

        return (string) $value;
    }


    /**
     * Gibt zurück, ob das Formular abgesendet wurde.
     *
     * Wirkung
     * -------
     * Diese Methode triggert die zentrale Request-Auswertung, ist aber nach
     * außen weiterhin bequem als Statusabfrage nutzbar.
     *
     * @return int 0|1
     */
    public function forward_submit() {
        $this->evaluate_request();
        $submit = ($this->_form_submit == 1) ? 1 : 0;
        $submit = $this->callback('submit', $submit);
        $result = ($submit == 1) ? 1 : 0;

        dbx()->debug("dbxForm submit result: fid=({$this->_fid}) result=($result)");

        return $result;
    }





    /**
     * Liest die aktuelle RID, optional view-synchronisiert.
     *
     * @return int
     */
    public function get_rid() {
        $empty     = array();
        $rid       = dbx()->get_modul_var('rid', -1, 'int');
        $dbx_view  = $this->_dbx_view;
        $dbx_modul = $this->_dbx_modul;

        if ($dbx_view) {
            $viewsys = dbx()->get_session_var($dbx_view, $empty, 'view-sys', $dbx_modul);

            if (isset($viewsys['value'])) {
                $rid = $viewsys['value'];
            }
        }

        $this->_rid = (int) $rid;
        $this->set_state_value('rid', (int) $rid);

        return (int) $rid;
    }

    /**
     * Synchronisiert eine RID in den View-State.
     *
     * @param int $rid RID
     *
     * @return void
     */
    public function view_sync($rid) {
        $empty     = array();
        $dbx_view  = $this->_dbx_view;
        $dbx_modul = $this->_dbx_modul;

        if ($dbx_view) {
            $viewsys = dbx()->get_session_var($dbx_view, $empty, 'view-sys', $dbx_modul);
            $viewsys['value'] = $rid;
            dbx()->set_session_var($dbx_view, $viewsys, 'view-sys', $dbx_modul);
        }
    }

    /**
     * Historischer Wait-Check.
     *
     * Hinweis
     * -------
     * Kein zentraler Architekturanker mehr.
     *
     * @return int
     */
    public function wait() {
        $wait = 0;
        $tpl  = $this->_tpl;

        if (strpos((string) $tpl, '_wait') !== false) {
            $wait = 1;
        }

        return $wait;
    }

    /**
     * Setzt einen `_post`-Wert manuell.
     *
     * Zweck
     * -----
     * Ermöglicht Modul-Fachlogik nach Standardvalidierung, ohne die Offenheit
     * von dbxForm einzuschränken.
     *
     * @param string $key Feldname
     * @param mixed  $val Wert
     *
     * @return void
     */
    public function set_post($key, $val) {
        if ($key != 'form-dd-field') {
            $this->_post[$key] = $val;
        }

        $this->_fld_changes = -1;
    }

    /**
     * Speichert `_post` über dbxDB.
     *
     * Sinn/Zweck
     * ----------
     * Diese Methode ist die bequeme Persistenz-Brücke von dbxForm. Sie ersetzt
     * NICHT die freie Modul-Fachlogik, sondern bildet den Standard-Speicherpfad
     * für Formulare.
     *
     * Verhalten
     * ---------
     * - optional zusätzliche Werte aus `$pv` übernehmen
     * - dbxDB->save() aufrufen
     * - bei Erfolg Insert-ID übernehmen
     * - optional Datensatz erneut laden
     * - `_data` und Feldwerte aktualisieren
     * - Workflow-State aktualisieren
     *
     * @param string $dd DD/Zielstruktur
     * @param mixed  $rid RID oder `new`
     * @param mixed  $pv Zusätzliche Werte
     * @param int    $reread 1 = Datensatz nach Save neu lesen
     *
     * @return mixed Ergebnis von dbxDB->save()
     */
    public function save_post($dd, $rid, $pv = '', $reread = 1) {
        $ok = 0;

        if ($rid === 'new') {
            $rid = 0;
        }

        if (is_array($pv)) {
            foreach ($pv as $key => $value) {
                $this->_post[$key] = $value;
                $this->_data[$key] = $value;
            }
        }

        if ($rid) {
            $this->_rid = (int) $rid;
        }

        $rid = (int) $this->_rid;
        $post = $this->_post;

        $this->o_db->_fld_id = $this->_fld_id;
        $ok = $this->o_db->save($dd, $post, $rid);

        if (!$ok) {
            dbx()->debug("DB-Error=(" . $this->o_db->_error . ")\nQuery=(" . $this->o_db->_query . ")");
            if ($this->_msg_error === '#form_msg_error#') {
                $this->_msg_error = '#form_msg_save_error#';
            }
            $this->_general_error = $this->_msg_error;
            $this->set_form_saved(false);
            return $ok;
        }

        if ($this->_msg_success === '#form_msg_success#') {
            $this->_msg_success = '#form_msg_save_success#';
        }

        if (!$rid) {
            $rid = (int) $this->o_db->_insert_id;
            $this->_rid = $rid;
        }

        if ($rid > 0) {
            $this->set_state_value('rid', $rid);
        }

        if ($rid && $ok && $reread) {
            $new = $this->o_db->select1($dd, $rid);

            if (is_array($new)) {
                foreach ($new as $key => $value) {
                    $this->_data[$key] = $value;
                }

                $this->_reload_run = 1;

                foreach ($this->_flds as $no => $fld) {
                    $key = $fld['name'] ?? '';

                    if ($key !== '' && array_key_exists($key, $this->_data)) {
                        $this->_flds[$no]['value']  = $this->_data[$key];
                        $this->_flds[$no]['origin'] = 'reload';
                        $this->_flds[$no]['verify'] = 1;
                        $this->_flds[$no]['error']  = 0;
                    }
                }
            }
        }

        $this->set_form_saved(true);
        $this->set_form_complete(true);
        $this->set_form_valid(true);
        $this->bump_form_version();
        $this->store_workflow_state();

        dbx()->debug("#DD FORM-save#  Tab=($dd) rid=($rid)");

        return $ok;
    }

    /**
     * Liefert oder erzeugt die laufende Formularinstanz-ID.
     *
     * @param int $add Weitergabe an dbx()->next_id()
     *
     * @return int
     */
    public function next_i($add = 1) {
        $i = $this->_next_i;

        if (!$i) {
            $i = dbx()->next_id($add);
            $this->_next_i = $i;
        }

        $this->add_rep('i', $i);

        return $i;
    }

    /**
     * Setzt einen Feldwert direkt im Feldzustand.
     *
     * @param string $fld_name Feldname
     * @param mixed  $fld_val Wert
     *
     * @return void
     */
    public function set_fld_val($fld_name, $fld_val) {
        $this->_data[$fld_name] = $fld_val;
        $this->_sys[$fld_name]  = $fld_val;

        if (isset($this->_flds[$fld_name])) {
            $this->_flds[$fld_name]['value']  = $fld_val;
            $this->_flds[$fld_name]['origin'] = 'set';
            $this->_flds[$fld_name]['verify'] = 1;
        }
    }








    /**
     * Prüft Fehlversuche und baut ggf. Sperrinhalt.
     *
     * @param bool $submit Submit-Status
     * @param bool $errors Fehlerstatus
     * @param bool $allways Immer prüfen
     *
     * @return string
     */
    public function check_try_count($submit, $errors, $allways = 1) {
        $content = '';
        $clear   = 0;
        $reset   = $this->_try_reset;
        $max     = $this->_try_max;
        $msg     = $this->_try_msg;
        $now     = $this->current_time();
        $self    = dbx()->get_self_url();
        $ip      = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $sys     = isset($this->_sys['_try_sys']) ? $this->_sys['_try_sys'] : array();

        if (($sys['dbx_try_ip'] ?? '') !== $ip) {
            $sys = array(
                'dbx_try_ip' => $ip,
            );
        }

        $sys['dbx_try_count'] = $sys['dbx_try_count'] ?? 0;
        $sys['dbx_run_count'] = $sys['dbx_run_count'] ?? 0;
        $sys['dbx_try_lock']  = $sys['dbx_try_lock'] ?? 0;
        $sys['dbx_try_ip']    = $ip;

        $count_reset = max(0, (int) $this->_try_count_reset);
        $last_try    = (float) ($sys['dbx_try_last'] ?? 0);

        if ($count_reset > 0 && $last_try > 0 && ($now - $last_try) > $count_reset) {
            unset(
                $sys['dbx_try_first'],
                $sys['dbx_try_last'],
                $sys['dbx_try_stop'],
                $sys['dbx_try_run']
            );
            $sys['dbx_try_count'] = 0;
            $sys['dbx_try_lock']  = 0;
        }

        $sys['dbx_run_count']++;

        if ($submit) {
            if ($errors) {
                $sys['dbx_try_first'] = $sys['dbx_try_first'] ?? $now;
                $sys['dbx_try_last']  = $now;
                $sys['dbx_try_count']++;

                if ($sys['dbx_try_count'] >= $max) {
                    if (!isset($sys['dbx_try_stop'])) {
                        $sys['dbx_try_lock']++;

                        for ($i = 1; $i < $sys['dbx_try_lock']; $i++) {
                            $reset = (int) ($reset * 2);
                        }

                        $sys['dbx_try_stop'] = $now;
                        $sys['dbx_try_run']  = $this->current_time() + $reset;
                    }
                }

                if (isset($sys['dbx_try_run']) && $now > $sys['dbx_try_run']) {
                    $clear = 1;
                }
            } else {
                $clear = 1;
            }
        }

        if (($submit || $allways) && !$clear) {
            if (($sys['dbx_try_count'] ?? 0) >= $max && ($sys['dbx_try_run'] ?? 0) > 0) {
                $diff = (float)$sys['dbx_try_run'] - $now;

                if ($diff > 0) {
                    $data = array(
                        'sec'       => (int) $diff,
                        'self'      => $self,
                        'try_count' => $sys['dbx_try_count'],
                        'run_count' => $sys['dbx_run_count'],
                    );
                    $data['msg'] = $this->o_tpl->replaces((string) $msg, $data);

                    $content = $this->get_tpl($this->_tpl_max_try, $data);
                } else {
                    $clear = 1;
                }
            }
        }

        if ($clear) {
            unset($sys['dbx_try_stop'], $sys['dbx_try_run']);
            $sys['dbx_try_count'] = 0;
        }

        $this->_sys['_try_sys'] = $sys;

        return $content;
    }

































}

?>
