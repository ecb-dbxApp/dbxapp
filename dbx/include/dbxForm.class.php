<?php

/**
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
 * `$messages`-Array liefern. `save_post()` verwendet `save_success` nach
 * erfolgreichem Speichern und `save_error` bei einem dbxDB-Fehler.
 * `save_succeass` bleibt ausschließlich als kompatibler Alias erhalten.
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
 * $oDB   = dbx()->get_system_obj('dbxDB');
 *
 * $rid   = dbx()->get_modul_var('rid', 0, 'int');
 * $data  = $oDB->select1('dbx_user', $rid);
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

    /** Zentraler Validator */
    public $oValidator;

    /** Zentrale Template-Engine */
    public $oTPL;

    /** Zentraler DB-/DD-Service */
    public $oDB;

    /** Optionaler View-Schlüssel */
    public $_dbx_view = '';

    /** Optionaler View-Sync-Status */
    public $_view_sync = '';

    /** Optionaler View-Modus */
    public $_view_mode = '';

    /** tpl|php|mix */
    public $_mode = 'mix';

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
    public $_rid = 0;

    /** Formular-Action */
    public $_action = '';

    /** Template-Name */
    public $_tpl = '';

    /** Formular-ID */
    public $_fid = '';

    /** Laufende Formularinstanz-ID */
    public $_next_i = 0;

    /** Zugeordnetes DD. Bleibt Daten-/DB-Kontext und Fallback-Feldquelle. */
    public $_dd = '';

    /** Optionale FD-Felddefinitionsquelle. Wenn gesetzt, bevorzugt add_fld()/add_flds() diese Quelle vor _dd. */
    public $_fd = '';

    /** Dateien, die fuer den Frontend-Editor markiert werden */
    public $_editor_files = array();

    /** Modul-/Include-Class-Datei fuer den Frontend-Editor */
    public $_editor_class_file = '';

    /** Aktuelle Formulardaten / Record-Daten */
    public $_data = array();

    /** Validierte/geänderte POST-Werte */
    public $_post = array();

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
    public $_messages = array();

    /** Info-Meldungen */
    public $_infos = array();

    /** Feld-/Formfehler */
    public $_errors = array();

    /** Strukturierte Validator-Ergebnisse, indiziert nach Feldname */
    protected $_validation_results = array();

    /** Zuständig nur für Herkunft und Validierung einzelner Feldwerte. */
    private ?dbxFormValueResolver $valueResolver = null;

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
    public $_tpl_form_info = 'form-alert-info';

    /** Template für Erfolgsmeldungen */
    public $_tpl_form_success = 'form-alert-success';

    /** Template für Fehlermeldungen */
    public $_tpl_form_error = 'form-alert-danger';

    /** Template für Warnmeldungen */
    public $_tpl_form_warning = 'form-alert-warning';

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
     * @return void
     */
    public function __construct($id = '', $tpl = '') {
        $this->oValidator = dbx()->get_system_obj('dbxValidator');
        $this->oTPL       = dbx()->get_system_obj('dbxTPL');
        $this->oDB        = dbx()->get_system_obj('dbxDB');

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
     * @return void
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
        $this->_form_help_enabled = 1;

        $this->_fld_changes   = -1;
        $this->_form_submit   = -1;
        $this->_form_validate = -1;
        $this->_general_error = '';
        $this->_msg_info      = '#form_msg_info#';
        $this->_msg_success   = '#form_msg_success#';
        $this->_msg_error     = '#form_msg_error#';
        $this->_msg_warning   = '#form_msg_warning#';

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

    /**
     * Registriert eine genutzte FD-/DD-/Class-Datei fuer Editor-Marker.
     *
     * @param string $kind Marker-Typ: fd, dd oder class
     * @param string $file Absoluter oder relativer Dateipfad
     * @return void
     */
    protected function add_editor_file(string $kind, string $file) {
        $file = trim($file);
        $kind = strtolower(trim($kind));

        if ($file === '' || $kind === '') {
            return;
        }

        $path = dbx()->editor_file_path($file);
        $key  = $kind . '|' . $path;
        $this->_editor_files[$key] = array('kind' => $kind, 'file' => $path);
        dbx()->register_editor_file($kind, $path);
    }

    /**
     * Fuegt passende Editor-Marker um den gerenderten Formularinhalt.
     *
     * @param string $content Formularinhalt
     * @return string Inhalt mit Editor-Marker-Kommentaren
     */
    protected function add_editor_markers(string $content): string {
        $edit = (int) dbx()->get_system_var('dbx_edit', 0, 'int');

        if (($edit >= 4 && $edit <= 8) || $edit === 9) {
            return $content;
        }

        if ($this->_editor_class_file !== '') {
            $this->add_editor_file('class', $this->_editor_class_file);
        }

        foreach (dbx()->get_editor_files() as $file) {
            if (isset($file['kind'], $file['file'])) {
                $this->add_editor_file($file['kind'], $file['file']);
            }
        }

        if ($content === '' || !$this->_editor_files) {
            return $content;
        }

        $markers = '';

        foreach ($this->_editor_files as $file) {
            $kind = strtolower((string) ($file['kind'] ?? ''));

            // FD/DD-Marker werden pro Feld in create_fld() gesetzt.
            if ($kind === 'fd' || $kind === 'dd') {
                continue;
            }

            $marker = dbx()->editor_marker($file['kind'], $file['file']);
            $needle = trim($marker);

            if ($marker !== '' && strpos($content, $needle) === false && strpos($markers, $needle) === false) {
                $markers .= $marker;
            }
        }

        if ($markers === '') {
            return $content;
        }

        return $markers . $content . "\n<!-- DBX-EDITOR-END -->\n";
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

        if ($tpl == '') {
            $tpl = $fid;
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
        $this->attachAdminHelpButton();
        $this->add_default_search_rep();
    }

    /**
     * Setzt die einheitliche Modul-/Formularleiste.
     *
     * Standardmäßig werden bereits vorbereitete Metadaten beibehalten. Mit
     * `$replace = true` kann ein konkretes Formular den allgemeineren
     * Admin-Hilfetitel gezielt durch sprachabhängige FD-Texte ersetzen.
     *
     * @param string $title Titel der Leiste
     * @param string $icon Bootstrap-Iconklasse
     * @param string $subtitle Untertitel der Leiste
     * @param bool $replace Vorhandene Metadaten bewusst ersetzen
     *
     * @return void
     */
    public function add_module_bar(
        $title,
        $icon = 'bi-grid',
        $subtitle = '',
        $replace = false
    ) {
        if ($replace || !isset($this->_replaces['bar_title']) || (string)$this->_replaces['bar_title'] === '') {
            $this->add_rep('bar_title', (string)$title);
        }
        if ($replace || !isset($this->_replaces['bar_icon']) || (string)$this->_replaces['bar_icon'] === '') {
            $this->add_rep('bar_icon', (string)$icon);
        }
        if ($replace || !isset($this->_replaces['bar_subtitle']) || (string)$this->_replaces['bar_subtitle'] === '') {
            $this->add_rep('bar_subtitle', (string)$subtitle);
        }
    }

    /**
     * Schaltet die automatische Formular-Hilfeleiste gezielt ein oder aus.
     * Eingebettete Steuerformulare verwenden die Hilfe ihres Elternbereichs.
     */
    public function set_form_help_enabled(bool $enabled = true): self {
        $this->_form_help_enabled = $enabled ? 1 : 0;
        if (!$enabled) {
            $this->_form_help_button = '';
            unset($this->_obj['help_button']);
        }
        return $this;
    }

    public function add_module_bar_form_actions(array $options = array()) {
        $defaults = array(
            'save'         => true,
            'delete'       => false,
            'reload'       => true,
            'reload_url'   => '',
            'delete_url'   => '',
            'delete_title' => 'Datensatz loeschen',
            'delete_hint'  => 'Dieser Vorgang kann nicht rueckgaengig gemacht werden.',
        );
        $this->_module_bar_form_actions = array_merge($defaults, $options);
    }

    public function prepare_form_shell(array $options = array()) {
        if (!isset($this->_replaces['form_shell_class'])) {
            $this->add_rep('form_shell_class', (string)($options['class'] ?? ''));
        }
        if (!isset($this->_replaces['form_class'])) {
            $this->add_rep('form_class', (string)($options['form_class'] ?? ''));
        }
        if (!isset($this->_replaces['form_attrs'])) {
            $this->add_rep('form_attrs', (string)($options['form_attrs'] ?? ''));
        }
    }

    protected function defaultModuleBarReplaces(): array {
        return array(
            'bar_class'               => 'dbx-bar--module',
            'bar_title_class'         => 'dbx-bar-title',
            'bar_actions_class'       => 'dbx-bar-actions',
            'bar_title'               => '',
            'bar_icon'                => 'bi-grid',
            'bar_subtitle'            => '',
            'bar_title_pre'           => '',
            'bar_title_heading_attrs' => '',
            'bar_actions'             => '',
            'bar_extra'               => '',
            'bar_middle'              => '',
        );
    }

    protected function prepareFormFrameReplaces(int $i): void {
        $formClass = trim((string)($this->_replaces['form_class'] ?? ''));
        $formAttrs = trim((string)($this->_replaces['form_attrs'] ?? ''));
        $shellClass = trim((string)($this->_replaces['form_shell_class'] ?? ''));
        $panelClass = trim('dbxForm_wrapper ' . $shellClass);
        $frameId = trim((string)($this->_replaces['frame_id'] ?? ''));
        $formAction = dbx()->action_url((string)$this->_action);
        if ($frameId === '') {
            $frameId = 'dbx_target_' . $i;
        }

        $this->add_rep('frame_id', $frameId);
        if (trim((string)($this->_replaces['frame_panel_class'] ?? '')) === '') {
            $this->add_rep('frame_panel_class', $panelClass);
        }
        $this->add_rep('frame_panel_attrs', (string)($this->_replaces['frame_panel_attrs'] ?? ''));
        $this->add_rep('frame_subbar', '');
        $this->add_rep('frame_body_class', '');
        if ((string)($this->_replaces['frame_skip_form_wrap'] ?? '') !== '1') {
            $this->add_rep('frame_form_open', '<form action="' . htmlspecialchars($formAction, ENT_QUOTES) . '" method="post" id="dbx_form_' . $i . '" class="dbxAjax' . ($formClass !== '' ? ' ' . $formClass : '') . '"' . ($formAttrs !== '' ? ' ' . $formAttrs : '') . '>');
            $this->add_rep('frame_body_head', '<div class="row"><div class="col">{obj:form_msg}</div></div>');
            $this->add_rep('frame_form_close', '</form>');
        } else {
            if (!isset($this->_replaces['frame_form_open'])) {
                $this->add_rep('frame_form_open', '');
            }
            if (!isset($this->_replaces['frame_body_head'])) {
                $this->add_rep('frame_body_head', '');
            }
            if (!isset($this->_replaces['frame_form_close'])) {
                $this->add_rep('frame_form_close', '');
            }
        }
        $this->add_rep('frame_body_tail', '');
    }

    protected function applyModuleBarReplaces(array $values = array()): void {
        $defaults = array_merge($this->defaultModuleBarReplaces(), $values);

        foreach ($defaults as $key => $value) {
            $this->add_rep($key, (string) $value);
        }
    }

    protected function buildModuleBarFormActionsHtml() {
        if (!$this->_module_bar_form_actions) {
            return '';
        }

        $opts = $this->_module_bar_form_actions;
        $i = (int)$this->_next_i;
        if ($i <= 0) {
            $i = (int)$this->next_i();
        }

        $formId = 'dbx_form_' . $i;
        $html = '';

        if (!empty($opts['save'])) {
            $html .= $this->get_tpl('dbx|button-bar-save', array(
                'bar_form_id' => $formId,
            ));
        }

        if (!empty($opts['delete']) && trim((string)($opts['delete_url'] ?? '')) !== '') {
            $html .= $this->get_tpl('dbx|button-bar-delete', array(
                'bar_delete_url'   => htmlspecialchars((string)($opts['delete_url'] ?? ''), ENT_QUOTES),
                'bar_delete_title' => htmlspecialchars((string)($opts['delete_title'] ?? 'Datensatz loeschen'), ENT_QUOTES),
                'bar_delete_hint'  => htmlspecialchars((string)($opts['delete_hint'] ?? ''), ENT_QUOTES),
            ));
        }

        if (!empty($opts['reload'])) {
            $reloadUrl = trim((string)($opts['reload_url'] ?? ''));
            if ($reloadUrl === '') {
                $reloadUrl = (string)$this->_action;
            }

            $html .= $this->get_tpl('dbx|button-bar-reload', array(
                'bar_reload_url' => htmlspecialchars($reloadUrl, ENT_QUOTES),
            ));
        }

        return $html;
    }

    protected function buildModuleBarObj() {
        $modul = (string)dbx()->get_system_var('dbx_modul', '');

        try {
            $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
            $topic = $help->resolveTopic($modul);

            $actions = trim((string)($this->_replaces['bar_actions'] ?? ''));
            if ($actions === '' && isset($this->_obj['bar_actions'])) {
                $actions = (string)$this->_obj['bar_actions'];
            }

            $formActions = $this->buildModuleBarFormActionsHtml();
            if ($formActions !== '') {
                $actions = ($actions !== '') ? ($actions . ' ' . $formActions) : $formActions;
            }

            $title = (string)($this->_replaces['bar_title'] ?? '');
            $icon = (string)($this->_replaces['bar_icon'] ?? 'bi-grid');
            $subtitle = (string)($this->_replaces['bar_subtitle'] ?? '');

            $fdTitle = $this->get_fd_message('bar_title', '');
            $fdSubtitle = $this->get_fd_message('bar_subtitle', '');
            $fdIcon = $this->get_fd_message('bar_icon', '');
            $meta = array();

            if ($topic !== '') {
                $meta = $help->barMeta($topic);
                if (is_array($meta) && $meta) {
                    if ($title === '') {
                        $title = (string)($meta['title'] ?? $topic);
                    }
                    if ($icon === '' || $icon === 'bi-grid') {
                        $icon = (string)($meta['icon'] ?? 'bi-grid');
                    }
                    if ($subtitle === '') {
                        $subtitle = (string)($meta['subtitle'] ?? '');
                    }
                }
            }

            // Eine aktive FD ist für Formulare und Reports die verbindliche
            // sprachabhängige Quelle. Sie ersetzt automatisch leere oder von
            // der generischen Admin-Hilfe stammende Metadaten. Ein Modul darf
            // weiterhin bewusst einen spezielleren, ebenfalls aus der FD
            // gelesenen Formulartitel setzen.
            $metaTitle = (string)($meta['title'] ?? '');
            $metaSubtitle = (string)($meta['subtitle'] ?? '');
            if (
                $fdTitle !== '' &&
                ($title === '' || $title === $metaTitle || $title === $fdTitle)
            ) {
                $title = $fdTitle;
            }
            if (
                $fdSubtitle !== '' &&
                (
                    $subtitle === '' ||
                    $subtitle === $metaSubtitle ||
                    $subtitle === $fdSubtitle
                )
            ) {
                $subtitle = $fdSubtitle;
            }
            if ($fdIcon !== '') {
                $icon = $fdIcon;
            }

            $helpButton = '';
            if ($this->_form_help_enabled) {
                if ($topic !== '') {
                    $helpButton = $help->button($topic);
                } elseif (isset($this->_obj['help_button'])) {
                    $helpButton = (string)$this->_obj['help_button'];
                }
                if ($helpButton === '' && method_exists($help, 'formButton')) {
                    $helpButton = $help->formButton($modul, (string)$this->_fid, $title);
                }
            }
            $this->_form_help_button = $helpButton;

            $barExtra = trim((string)($this->_replaces['bar_extra'] ?? ''));
            $visibleBarActions = $actions . $barExtra;
            if ($helpButton !== '' && strpos($visibleBarActions, 'bi-question-circle') === false) {
                $barExtra .= $helpButton;
            }

            $barClass = trim((string)($this->_replaces['bar_class'] ?? ''));
            if ($barClass === '') {
                $barClass = 'dbx-bar--module';
            }

            $this->applyModuleBarReplaces(array(
                'bar_class'    => $barClass,
                'bar_title'    => $title,
                'bar_icon'     => $icon !== '' ? $icon : 'bi-grid',
                'bar_subtitle' => $subtitle,
                'bar_actions'  => $actions,
                'bar_extra'    => $barExtra,
            ));
        } catch (\Throwable $e) {
        }
    }

    /**
     * Ergaenzt bei aelteren Formular-Templates ohne eigene Leiste eine
     * kompakte Standardleiste. Dadurch bleibt der Help-Button nicht nur als
     * Replace vorbereitet, sondern ist in jedem tatsaechlich gerenderten
     * dbxForm-Formular erreichbar.
     */
    protected function ensureFormHelpBar(string $content): string {
        if (!$this->_form_help_enabled || $content === '' || $this->_form_help_button === '' || stripos($content, '<form') === false) {
            return $content;
        }
        if (strpos($content, 'bi-question-circle') !== false
            || strpos($content, 'dbx_run1=help') !== false) {
            return $content;
        }

        $title = trim((string)($this->_replaces['bar_title'] ?? ''));
        if ($title === '') {
            $title = ucwords(str_replace(array('-', '_', '.'), ' ', (string)$this->_fid));
        }
        $bar = $this->get_tpl('dbx|form-help-bar', array(
            'form_help_title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            'form_help_icon' => htmlspecialchars((string)($this->_replaces['bar_icon'] ?? 'bi-ui-checks'), ENT_QUOTES, 'UTF-8'),
            'form_help_button' => $this->_form_help_button,
        ));
        if (trim($bar) === '') {
            return $content;
        }

        return (string)preg_replace_callback('/<form\b[^>]*>/i', static function ($match) use ($bar) {
            return $match[0] . $bar;
        }, $content, 1);
    }

    protected function attachAdminHelpButton($topic = '') {
        $modul = (string)dbx()->get_system_var('dbx_modul', '');
        if ($modul !== 'dbxAdmin' && !str_ends_with($modul, '_admin')) {
            return;
        }

        try {
            $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
            if ($topic === '') {
                $topic = $help->resolveTopic($modul);
            }
            if ($topic === '') {
                return;
            }
            $this->add_obj('help_button', $help->button($topic));

            $barMeta = $help->barMeta($topic);
            if (is_array($barMeta) && $barMeta) {
                $this->add_module_bar(
                    (string)($barMeta['title'] ?? ''),
                    (string)($barMeta['icon'] ?? 'bi-grid'),
                    (string)($barMeta['subtitle'] ?? '')
                );
            }
        } catch (\Throwable $e) {
        }
    }

    private function get_token(): string {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return hash('sha256', uniqid('', true) . '|' . mt_rand());
        }
    }

    private function secure_fld_name(): string {
        return '_' . (string)$this->_fid;
    }

    private function secure_token(string $fld): string {
        if (!isset($this->_sys['_csrf']) || !is_array($this->_sys['_csrf'])) {
            $this->_sys['_csrf'] = array();
        }

        $token = (string)($this->_sys['_csrf'][$fld] ?? $this->_sys[$fld] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = $this->get_token();
        }

        $this->_sys['_csrf'][$fld] = $token;
        $this->_sys[$fld] = $token;
        $this->_data[$fld] = $token;

        return $token;
    }

    private function sync_secure_field(string $fld, string $token): void {
        $this->_sys['_csrf'][$fld] = $token;
        $this->_sys[$fld] = $token;
        $this->_data[$fld] = $token;

        if (isset($this->_flds[$fld])) {
            $this->_flds[$fld]['value'] = $token;
            $this->_flds[$fld]['origin'] = 'csrf';
            $this->_flds[$fld]['verify'] = 1;
            $this->_flds[$fld]['error'] = 0;
        }
    }

    private function rotate_secure_token(string $fld): void {
        $this->sync_secure_field($fld, $this->get_token());
    }

    /**
     * Liefert Feldname und aktuellen Wert des dbxForm-Security-Tokens.
     *
     * JavaScript-gesteuerte Formulare erhalten nach einem JSON-Submit keinen
     * neu gerenderten Formularblock. Sie koennen mit diesen Daten den von
     * evaluate_request() rotierten Token gezielt im bestehenden DOM ersetzen,
     * ohne die private Token-Verwaltung nachzubauen.
     *
     * @return array{name:string,value:string}
     */
    public function get_security_data(): array {
        $fld = $this->secure_fld_name();
        $token = $this->secure_token($fld);

        // AJAX-gesteuerte Formulare rufen nicht zwingend run() auf. Ohne
        // diese Speicherung würde ein hier erstmals erzeugter oder nach
        // submit() rotierter Token nur im aktuellen Objekt existieren und
        // der nächste Request wieder den alten Sessionstand laden.
        $this->store_sysdata();

        return array(
            'name' => $fld,
            'value' => $token,
        );
    }


    /**
     * Wandelt Query-/URL-Daten in ein Array um.
     *
     * Verwendung
     * ----------
     * Wird intern zur Normalisierung von `data` und `options` genutzt.
     *
     * Auswirkung
     * ----------
     * Strings wie `a=1&b=2` werden zu Arrays. Arrays bleiben unverändert.
     *
     * @param mixed $data String oder bereits Array
     *
     * @return array|string
     */
    public function url_to_array($data) {
        if (!is_array($data)) {
            $first = substr((string) $data, 0, 1);

            if ($data && $first != '=') {
                if (strpos((string) $data, '=') !== false) {
                    parse_str($data, $xdata);
                    $data = $xdata;
                }
            }
        }

        return $data;
    }

    /**
     * Setzt die Formular-Info-Meldung.
     *
     * @param string $msg Meldung
     *
     * @return void
     */
    public function set_msg_info($msg) {
        $this->_msg_info = $msg;
    }

    /**
     * Setzt die Formular-Erfolgsmeldung.
     *
     * @param string $msg Meldung
     *
     * @return void
     */
    public function set_msg_ok($msg) {
        $this->_msg_success = $msg;
    }

    /**
     * Setzt die Formular-Fehlermeldung.
     *
     * @param string $msg Meldung
     *
     * @return void
     */
    public function set_msg_error($msg) {
        $this->_msg_error = $msg;
    }

    /**
     * Setzt eine aktive allgemeine Formular-Fehlermeldung.
     *
     * Im Gegensatz zu set_msg_error(), das den Standardtext für einen später
     * erkannten Fehler festlegt, markiert diese Methode das Formular sofort
     * als fehlerhaft. Controller müssen dafür keine internen Statusfelder
     * von dbxForm kennen oder verändern.
     *
     * @param string $msg Anzuzeigende Fehlermeldung.
     *
     * @return void
     */
    public function set_error($msg) {
        $this->_msg_error = $msg;
        $this->_general_error = $msg;
    }

    /**
     * Setzt die Formular-Warnmeldung.
     *
     * @param string $msg Meldung
     *
     * @return void
     */
    public function set_msg_warning($msg) {
        $this->_msg_warning = $msg;
    }

    /**
     * Liefert eine Meldung aus der aktuell geladenen FD.
     *
     * Der korrekte Schlüssel für erfolgreiches Speichern ist
     * `save_success`. Der ältere Tippfehler `save_succeass` wird beim Laden
     * bidirektional als Alias ergänzt und bleibt damit kompatibel.
     *
     * @param string $key Meldungsschlüssel
     * @param string $default Rückgabewert, wenn der Schlüssel nicht existiert
     *
     * @return string
     */
    public function get_fd_message(string $key, string $default = ''): string {
        if (!array_key_exists($key, $this->_messages)) {
            return $default;
        }

        $message = $this->_messages[$key];

        return is_scalar($message) ? (string)$message : $default;
    }

    /**
     * Liefert eine FD-Meldung und ersetzt benannte Platzhalter.
     *
     * Dadurch bleiben auch dynamische Formular- und Reporttexte vollständig
     * in der sprachabhängigen FD. Ein Modul übergibt nur noch die Werte:
     * `format_fd_message('delete_question', array('id' => $rid))` ersetzt
     * beispielsweise `{id}`. dbxReport erbt diese Funktion von dbxForm.
     *
     * @param string $key Meldungsschlüssel aus `$messages`
     * @param array $values Platzhalterwerte ohne Klammern
     * @param string $default Rückgabe bei unbekanntem Meldungsschlüssel
     *
     * @return string Lokalisierte und formatierte Meldung
     */
    public function format_fd_message(
        string $key,
        array $values = array(),
        string $default = ''
    ): string {
        $message = $this->get_fd_message($key, $default);
        if ($message === '' || $values === array()) {
            return $message;
        }

        $replacements = array();
        foreach ($values as $placeholder => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $replacements['{' . trim((string)$placeholder, '{}') . '}'] =
                (string)$value;
        }

        return strtr($message, $replacements);
    }

    /**
     * Lädt ausschließlich den Meldungsvertrag einer sprachabhängigen FD.
     *
     * Verwendung
     * ----------
     * Formulare laden ihre FD normalerweise beim ersten `add_fld()` oder über
     * `add_flds()`. Für Modul-, Listen- und Bestätigungstexte werden Meldungen
     * jedoch teilweise bereits davor benötigt. Diese Methode nutzt denselben
     * sprachabhängigen Resolver und denselben Cache, ohne Felder zu rendern.
     *
     * dbxReport erbt die Methode. Damit können auch Tabellenköpfe, Footer und
     * Reportmeldungen aus der Selection-FD stammen, ohne die Filterfelder ein
     * zweites Mal anzulegen.
     *
     * @param string $fd FD in der Form `modul|name`; leer verwendet `_fd`
     *
     * @return array Geladene und normalisierte Meldungen
     */
    public function load_fd_messages(string $fd = ''): array {
        $source = trim($fd);
        if ($source === '') {
            $source = trim((string)$this->_fd);
        }
        if ($source === '') {
            return $this->_messages;
        }
        if (strpos($source, 'fd:') !== 0) {
            $source = 'fd:' . $source;
        }

        $this->get_dd_fields_source($source);

        return $this->_messages;
    }

    /**
     * Übernimmt und normalisiert die Meldungen einer geladenen FD.
     *
     * Später geladene FD-Werte überschreiben gleichnamige Werte. Dadurch gilt
     * bei zusammengesetzten Formularen dieselbe Priorität wie bei den
     * Felddefinitionen.
     *
     * @param array $messages Meldungen aus der FD-Datei
     *
     * @return void
     */
    private function apply_fd_messages(array $messages): void {
        if (
            !isset($messages['save_success']) &&
            isset($messages['save_succeass'])
        ) {
            $messages['save_success'] = $messages['save_succeass'];
        }

        if (
            !isset($messages['save_succeass']) &&
            isset($messages['save_success'])
        ) {
            $messages['save_succeass'] = $messages['save_success'];
        }

        $this->_messages = array_merge($this->_messages, $messages);
    }

    /**
     * Extrahiert den Feldnamen aus HTML.
     *
     * Verwendung
     * ----------
     * Legacy-Hilfe für Fälle, in denen ein bereits gerendertes HTML-Feld
     * untersucht werden muss.
     *
     * @param string $fld HTML-Feld
     *
     * @return string
     */
    public function get_fld_id($fld) {
        return dbx()->part_select(' name="', '"', $fld);
    }

    /**
     * Ersetzt norep-Platzhalter.
     *
     * Zweck
     * -----
     * Bestehende norep-Mechanik bleibt als Integrationsfunktion erhalten,
     * ist aber nicht mehr der Kern des Form-Designs.
     *
     * Auswirkung
     * ----------
     * Sucht `[id]`-Platzhalter im übergebenen Inhalt und ersetzt sie mit den
     * in `$_SESSION['dbx']['norep']` gespeicherten Inhalten.
     *
     * @param string $content Inhalt mit `[id]`-Platzhaltern
     *
     * @return string
     */
    public function add_norep($content) {
        $oTPL = dbx()->get_system_obj('dbxTPL');
        if (is_object($oTPL) && method_exists($oTPL, 'cleanup_optional_placeholders')) {
            $content = $oTPL->cleanup_optional_placeholders((string)$content);
        }

        if (isset($_SESSION['dbx']['norep']) && is_array($_SESSION['dbx']['norep'])) {
            $xnorep = $_SESSION['dbx']['norep'];

            for ($i = 0; $i < 2; $i++) {
                foreach ($xnorep as $id => $norep) {
                    $xid = '[' . $id . ']';
                    $content = str_replace($xid, $norep, $content);
                }
            }
        }

        return $content;
    }

    /**
     * Sendet eine direkte Fast-Response.
     *
     * Zweck
     * -----
     * Für Spezialfälle, in denen eine direkte Antwort ohne normalen Render-
     * Endlauf ausgegeben werden soll.
     *
     * Auswirkung
     * ----------
     * Speichert die Session, gibt den Response aus und beendet den Request.
     * Optional wird vorher der dbxInterpreter angewendet.
     *
     * @param string $response Antwortinhalt
     * @param int    $interpreter 1 = dbxInterpreter vorher anwenden
     *
     * @return void
     */
    public function fast_response($response, $interpreter = 0) {
        if ($interpreter) {
            $oInterpreter = dbx()->get_system_obj('dbxInterpreter');
            $response = $oInterpreter->run($response);
            $response = $this->add_norep($response);
        }

        echo $response;
        exit;
    }

    /**
     * Wrapper auf dbxTPL->get_tpl().
     *
     * Verwendung
     * ----------
     * Zentrale Kurzform, damit dbxForm und abgeleitete Klassen Templates über
     * die DBX-Template-Engine laden können.
     *
     * @param string $tpl Template
     * @param mixed  $data Replaces
     * @param string $type Typ
     * @param int    $i Laufindex
     *
     * @return string
     */
    public function get_tpl($tpl, $data = '', $type = 'htm', $i = 0) {
        return $this->oTPL->get_tpl($tpl, $data, $type, $i);
    }

    /**
     * Historische obv-Hilfe.
     *
     * Hinweis
     * -------
     * Diese Methode bleibt nur als Übergangshilfe erhalten.
     * Observe-/Client-Reaktionslogik gehört heute nicht mehr in den Kern von
     * dbxForm.
     *
     * @param string $content Inhalt
     * @param string $id Platzhalter-ID
     * @param mixed  $value Wert
     *
     * @return string
     */
    public function obv_value($content, $id, $value) {
        $rep = '{obv:' . $id . '}';
        $val = '';

        if (is_string($value)) {
            $val = htmlspecialchars($value, ENT_QUOTES);
            $content = str_replace($rep, $val, $content);
        }

        return $content;
    }

    /**
     * Führt grundlegende Form-Replaces in Templates aus.
     *
     * Verwendung
     * ----------
     * Wird in der Ausgabephase vor dem Einsetzen von Feldern und Objekten
     * aufgerufen.
     *
     * Auswirkung
     * ----------
     * Ersetzt Basiswerte wie `{dbx_modul}`, `{dbx_run1}`, `{action}`, `{fid}`,
     * `{rid}`, `{self}` und `{i}`.
     *
     * @param string $tpl Template-Inhalt
     * @param int    $i Laufindex
     *
     * @return string
     */
    public function merge_tpl_data($tpl, $i = 0) {
        $editor = dbx()->get_system_var('dbx_editor', 0, 'int');

        if (!$i && !$editor) {
            $i = $this->_next_i;
        }

        $replaces = array();
        $replaces['dbx_modul']  = $this->_dbx_modul;
        $replaces['dbx_run1']   = $this->_dbx_action;
        $replaces['dbx_page']   = $this->_dbx_page;
        $replaces['dbx_design'] = $this->_dbx_design;
        $replaces['dbx_lng']    = $this->_dbx_lng;
        $replaces['action']     = dbx()->action_url((string)$this->_action);
        $replaces['fid']        = $this->_fid;
        $replaces['rid']        = $this->_rid;
        $replaces['self']       = dbx()->get_self_url();

        if ($i) {
            $replaces['i'] = $i;
        }

        return $this->oTPL->replaces($tpl, $replaces);
    }

    /**
     * Ersetzt `{obj:*}`-Platzhalter.
     *
     * Verwendung
     * ----------
     * Wird nach `merge_fld_data()` aufgerufen, um frei hinzugefügte Objekte
     * wie Buttons, Action-Blöcke oder Modul-Ausgaben einzusetzen.
     *
     * Auswirkung
     * ----------
     * Alle Einträge in `_obj` werden in passende `{obj:name}`-Slots eingesetzt.
     *
     * @param string $content Inhalt
     * @param int    $i Laufindex
     *
     * @return string
     */
    public function merge_obj($content, $i = 0) {
        if (is_array($this->_obj)) {
            foreach ($this->_obj as $id => $obj) {
                $fid = '{obj:' . $id . '}';
                $obj = $this->oTPL->replaces($obj, $this->_replaces);
                $content = str_replace($fid, $obj, $content);
            }
        }

        return $content;
    }

    /**
     * Speichert Sysdata des Formulars.
     *
     * Zweck
     * -----
     * Sysdata ist der laufende Formzustand innerhalb des Session-Kontexts,
     * nicht der übergeordnete Workflow-State. Workflow-/Draft-/Version-Status
     * wird separat über Remember gehalten.
     *
     * Auswirkung
     * ----------
     * Speichert `_sys` unter Formular-ID und Modul in der Session.
     *
     * @return void
     */
    public function store_sysdata() {
        $section = $this->_fid;
        $modul   = $this->_dbx_modul;
        $mode    = $this->_store_mode;
        $value   = $this->_sys;
        $key     = 'sysdata';

        if ($section && $mode == 'session') {
            dbx()->set_session_var($key, $value, $section, $modul);
        }
    }

    /**
     * Lädt Sysdata des Formulars.
     *
     * Verwendung
     * ----------
     * Wird während `forward_init()` genutzt, damit Formularzustände zwischen
     * Requests erhalten bleiben können.
     *
     * Auswirkung
     * ----------
     * Gibt gespeicherte Sysdata als Array zurück. Ungültige Werte werden zu
     * einem leeren Array normalisiert.
     *
     * @return array
     */
    public function load_sysdata() {
        $sysdata = array();
        $section = $this->_fid;
        $modul   = $this->_dbx_modul;
        $key     = 'sysdata';
        $empty   = array();

        if ($section) {
            $sysdata = dbx()->get_session_var($key, $empty, $section, $modul);
        }

        return is_array($sysdata) ? $sysdata : array();
    }

    /**
     * Liefert die aktive Remember-ID des aktuellen DD-Kontexts.
     *
     * Verwendung
     * ----------
     * Wird für aktive Zeilen-/Datensatz-Markierungen verwendet.
     *
     * Auswirkung
     * ----------
     * Liest die aktive ID aus Remember. Ohne expliziten Schlüssel wird `_dd`
     * als Kontext verwendet.
     *
     * @param string $key Optionaler Kontextschlüssel
     *
     * @return mixed
     */
    public function get_activ_id($key = '') {
        if (!$key) {
            $key = $this->_dd;
        }

        $xkey = '_activ-row_id_' . $key;
        return dbx()->get_remember_var($xkey, 0, 'dbx');
    }

    /**
     * Setzt die aktive Remember-ID des aktuellen DD-Kontexts.
     *
     * @param mixed  $value Wert
     * @param string $key Optionaler Kontextschlüssel
     *
     * @return void
     */
    public function set_activ_id($value, $key = '') {
        if (!$key) {
            $key = $this->_dd;
        }

        $xkey = '_activ-row_id_' . $key;
        dbx()->set_remember_var($xkey, $value, 'dbx');
    }

    /**
     * Fügt einen allgemeinen Replace-Wert hinzu.
     *
     * Verwendung
     * ----------
     * Replaces werden beim Laden/Rendern von Templates und Objekten eingesetzt.
     *
     * @param string $key Schlüssel
     * @param mixed  $val Wert
     *
     * @return void
     */
    public function add_rep($key, $val) {
        $this->_replaces[$key] = $val;
    }

    /**
     * Wendet dbxForm-Replacements auf einen beliebigen Inhalt an.
     *
     * Ohne explizites Array werden die zuvor mit add_rep() gesetzten Werte
     * verwendet. Weil dbxReport von dbxForm erbt, stehen dieselbe Methode und
     * dieselben spaet gesetzten Replacements auch in Report-Callbacks bereit.
     *
     * @param string $content Inhalt mit `{name}`-Platzhaltern.
     * @param array|null $replaces Explizite Werte oder null fuer `_replaces`.
     * @return string Inhalt mit ersetzten Platzhaltern.
     */
    public function replaces($content, $replaces = null): string {
        if ($replaces === null) {
            $replaces = $this->_replaces;
        }

        return $this->oTPL->replaces(
            (string)$content,
            is_array($replaces) ? $replaces : array()
        );
    }

    /**
     * Fügt JS-Code hinzu.
     *
     * Verwendung
     * ----------
     * Modulcode kann damit kleine JS-Blöcke an den Formularlauf anhängen.
     *
     * Auswirkung
     * ----------
     * Der Code wird später in `forward_run()` über `[dbx:js]` und norep
     * in den Output eingebunden.
     *
     * @param string $javascript JS-Code
     *
     * @return void
     */
    public function add_js_code($javascript) {
        if ($javascript !== '') {
            $this->_js[] = $javascript;
        }
    }

    /**
     * Fügt einen einfachen JS-Aufruf hinzu.
     *
     * Zweck
     * -----
     * Einfache Übergangshilfe für modulare JS-Aufrufe. Komplexe UI-/Observe-
     * Logik sollte heute eher in core.js / Libs liegen.
     *
     * Beispiel
     * --------
     * ```php
     * $oForm->add_js_call('roles', 'multiselect2');
     * ```
     *
     * @param string $target Zielkennung
     * @param string $function Funktionsname
     * @param string $args Optionaler JS-Argumentstring
     *
     * @return void
     */
    public function add_js_call($target, $function, $args = '') {
        $js = $function . "('" . addslashes((string) $target) . "'";

        if ($args !== '') {
            $js .= ',' . $args;
        }

        $js .= ');';

        $this->_js[] = $js;
    }

    /**
     * Markiert ein Feld mit Fehlerstatus.
     *
     * Verwendung
     * ----------
     * Wird von `add_fld_error()` genutzt, damit Fehler nicht nur in `_errors`,
     * sondern auch direkt am Feldzustand sichtbar werden.
     *
     * Auswirkung
     * ----------
     * Setzt `error`, `verify`, ergänzt CSS-Klasse `fld-error` und setzt die
     * Fehlermeldung in `data.errormsg`.
     *
     * @param string $name Feldname
     * @param string $msg Fehlermeldung
     *
     * @return void
     */
    public function update_error_fld($name, $msg) {
        foreach ($this->_flds as $no => $fld) {
            if (($fld['name'] ?? '') == $name) {
                $fld['error']  = 1;
                $fld['verify'] = 1;
                $fld['data']['class']    = trim(($fld['data']['class'] ?? '') . ' fld-error');
                $fld['data']['errormsg'] = $msg;
                $this->_flds[$no] = $fld;
                break;
            }
        }
    }

    /**
     * Fügt einen Feldfehler hinzu.
     *
     * Auswirkung
     * ----------
     * Der Fehler wird in `_errors` gespeichert und der Feldzustand wird über
     * `update_error_fld()` angepasst.
     *
     * @param string $name Feldname
     * @param string $msg Meldung
     *
     * @return void
     */
    public function add_fld_error($name, $msg = '') {
        dbx()->debug("add fld-error fld=($name) Msg=($msg)");
        $this->_errors[$name] = $msg;
        $this->update_error_fld($name, $msg);
    }

    /**
     * Liefert das strukturierte Validator-Ergebnis eines Feldes.
     *
     * Ohne Feldname werden alle Ergebnisse des aktuellen Formularlaufs
     * zurückgegeben. Die Rückgabe enthält insbesondere `valid`, `code`,
     * `message`, `normalized` und `details`.
     *
     * @param string $name Optionaler Feldname
     * @return array
     */
    public function get_validation_result(string $name = ''): array {
        if ($name === '') {
            return $this->_validation_results;
        }

        $result = $this->_validation_results[$name] ?? array();
        return is_array($result) ? $result : array();
    }

    /**
     * Merkt sich ein Validator-Ergebnis im aktuellen Formularlauf.
     */
    protected function remember_validation_result(string $name, array $result): void {
        if ($name !== '') {
            $this->_validation_results[$name] = $result;
        }
    }

    /**
     * Fügt eine Feldwarnung hinzu.
     *
     * @param string $name Feldname
     * @param string $msg Meldung
     *
     * @return void
     */
    public function add_fld_warning($name, $msg = '') {
        $this->_warnings[$name] = $msg;
    }

    /**
     * Liefert einen Feldwert abhängig vom aktuellen Request-Status.
     *
     * Wirkung
     * -------
     * - bei Submit: POST/GET hat Vorrang
     * - ohne Submit: _data / _sys
     * - optional direkte Validator-Prüfung
     * - Wert wird in `_sys` gespiegelt
     *
     * @param string $name Feldname
     * @param mixed  $default Default
     * @param string $rules Validator-Regeln
     * @param int    $submit Optional Submit-Status
     *
     * @return mixed
     */
    private function resolve_fld_val($name, $default = '', $rules = '', $useRequest = true, $fallbackToState = true) {
        if ($this->valueResolver === null) {
            require_once __DIR__ . '/dbxFormValueResolver.class.php';
            $this->valueResolver = new dbxFormValueResolver($this->oValidator);
        }
        $resolved = $this->valueResolver->resolve(
            (string)$name,
            $default,
            (string)$rules,
            is_array($this->_data) ? $this->_data : array(),
            is_array($this->_sys) ? $this->_sys : array(),
            is_array($_POST ?? null) ? $_POST : array(),
            is_array($_GET ?? null) ? $_GET : array(),
            (bool)$useRequest,
            (bool)$fallbackToState
        );
        if ((string)$name !== '' && $resolved['validation'] !== array()) {
            $this->remember_validation_result((string)$name, $resolved['validation']);
        }
        return $resolved;
    }

    public function get_fld_val($name, $default = '', $rules = '', $submit = -1) {
        $resolved = $this->resolve_fld_val($name, $default, $rules, true, true);
        $value    = $resolved['value'];

        if (!$resolved['ok']) {
            $this->add_fld_error($name, 'f:' . $rules);
            $value = $default;
        }

        $this->_data[$name] = $value;
        $this->_sys[$name]  = $value;

        if (isset($this->_flds[$name])) {
            $this->_flds[$name]['value']  = $value;
            $this->_flds[$name]['origin'] = $resolved['origin'];
        }

        return $value;
    }
    public function get_fld_value($name, $default = '', $rules = '', $submit = -1) {
        return $this->get_fld_val($name, $default, $rules, $submit);
    }

    /**
     * Liest POST/GET-Daten mit Default-Rules `parameter`.
     *
     * @param string $name Feldname
     * @param mixed  $default Default
     * @param string $rules Regeln
     *
     * @return mixed
     */
    public function get_post_data($name, $default = '', $rules = 'parameter') {
        $danger_value = $this->_get_post_data($name, $default);

        if ($rules) {
            $validation = $this->oValidator->validateResult($danger_value, $rules, $name);
            $ok = (bool)($validation['valid'] ?? false);
            $this->remember_validation_result((string)$name, $validation);

            if (!$ok) {
                $this->add_fld_error($name, 'p:' . $rules);
                $danger_value = $default;
            } elseif (preg_match('/(?:^|\|)trim(?:\||$)/', (string)$rules)) {
                $danger_value = $validation['normalized'] ?? $danger_value;
            }
        }

        return $danger_value;
    }

    /**
     * Liest POST/GET-Daten mit Default-Rules `alphanum`.
     *
     * @param string $name Feldname
     * @param mixed  $default Default
     * @param string $rules Regeln
     *
     * @return mixed
     */
    public function get_post($name, $default = '', $rules = 'alphanum') {
        $danger_value = $this->_get_post($name, $default);

        if ($rules) {
            $validation = $this->oValidator->validateResult($danger_value, $rules, $name);
            $ok = (bool)($validation['valid'] ?? false);
            $this->remember_validation_result((string)$name, $validation);

            if (!$ok) {
                $this->add_fld_error($name, 'p:' . $rules);
                $danger_value = $default;
            } elseif (preg_match('/(?:^|\|)trim(?:\||$)/', (string)$rules)) {
                $danger_value = $validation['normalized'] ?? $danger_value;
            }
        }

        return $danger_value;
    }

    /**
     * Liest POST/GET/DATA in dieser Reihenfolge.
     *
     * Verwendung
     * ----------
     * Interner Rohzugriff für Funktionen, die neben Request-Werten auch `_data`
     * als Fallback einbeziehen sollen.
     *
     * @param string $name Feldname
     * @param mixed  $default Default
     *
     * @return mixed
     */
    private function _get_post_data($name, $default = '') {
        $set   = 0;
        $value = $default;

        if (isset($_POST[$name])) {
            $value = $_POST[$name];
            $set = 1;
        } elseif (isset($_GET[$name])) {
            $value = $_GET[$name];
            $set = 1;
        }

        if (!$set && isset($this->_data[$name])) {
            $value = $this->_data[$name];
        }

        return $value;
    }

    /**
     * Liest POST/GET in dieser Reihenfolge.
     *
     * Verwendung
     * ----------
     * Interner Rohzugriff für Request-Werte ohne `_data`-Fallback.
     *
     * @param string $name Feldname
     * @param mixed  $default Default
     *
     * @return mixed
     */
    private function _get_post($name, $default = '') {
        $value = $default;

        if (isset($_POST[$name])) {
            $value = $_POST[$name];
        } elseif (isset($_GET[$name])) {
            $value = $_GET[$name];
        }

        return $value;
    }

    /**
     * Fügt ein `{obj:*}`-Objekt hinzu.
     *
     * Verwendung
     * ----------
     * Für Inhalte, die nicht über die Feldpipeline laufen, aber als
     * `{obj:name}` im Template erscheinen sollen.
     *
     * Auswirkung
     * ----------
     * Rendert bei Template-Angaben sofort das Objekt und speichert es in `_obj`.
     *
     * @param string $obj Platzhalter-ID
     * @param string $tpl Template oder obj-value/obv-value
     * @param mixed  $data Daten
     * @param mixed  $data2 Zusätzliche Replaces
     *
     * @return void
     */
    protected function escape_tooltip_template_data($data) {
        if (is_array($data) && isset($data['tooltip']) && !is_array($data['tooltip'])) {
            $data['tooltip'] = htmlspecialchars(
                (string)$data['tooltip'],
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        return $data;
    }

    /**
     * Erzeugt den fokussierbaren Hilfe-Marker eines Formularfeldes.
     *
     * Feld-Templates kennen nur `{tooltip}`. Der eigentliche Tooltip-Inhalt
     * bleibt dadurch zentral in dbxForm und wird sicher in das Attribut des
     * Fragezeichens geschrieben.
     *
     * @param mixed $tooltip Tooltip-Inhalt aus FD oder DD
     * @return string Leerer String oder fertiges Marker-HTML
     */
    protected function render_field_tooltip_marker($tooltip): string {
        if (is_array($tooltip) || is_object($tooltip)) {
            return '';
        }

        $tooltip = trim((string)$tooltip);
        if ($tooltip === '') {
            return '';
        }

        $tooltipAttribute = htmlspecialchars(
            $tooltip,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $accessibleLabel = trim((string)preg_replace(
            '/\s+/u',
            ' ',
            strip_tags(html_entity_decode($tooltip, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ));
        if ($accessibleLabel === '') {
            $accessibleLabel = 'Tooltip';
        }
        $accessibleLabel = htmlspecialchars(
            $accessibleLabel,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return '<span class="dbx-form-tooltip-marker" role="img" tabindex="0"' .
            ' data-dbx-tooltip="' . $tooltipAttribute . '"' .
            ' aria-label="' . $accessibleLabel . '">?</span>';
    }

    public function add_obj($obj, $tpl, $data = '', $data2 = '') {
        if ($tpl != 'obj-value' && $tpl != 'obv-value') {
            $data = $this->escape_tooltip_template_data($data);
            $tpl = $this->get_tpl($tpl, $data);
        } else {
            if ($tpl == 'obv-value') {
                $tpl = htmlspecialchars((string) $data, ENT_QUOTES);
            }

            if ($tpl == 'obj-value') {
                $tpl = $data;
            }

            $tpl = $this->oTPL->replaces(
                (string)$tpl,
                $this->escape_tooltip_template_data($data2)
            );
        }

        $tpl = str_replace('{class}', '', $tpl);
        $tpl = str_replace('{tooltip}', '', $tpl);

        $this->_obj[$obj] = $tpl;
    }

    /**
     * Fügt ein Objekt hinzu, dessen Action aus `_action` abgeleitet wird.
     *
     * Verwendung
     * ----------
     * Praktisch für Buttons/Links, die an die aktuelle Formular-Action
     * andocken sollen.
     *
     * @param string $obj Platzhalter-ID
     * @param string $tpl Template
     * @param string $action Action oder `&...`-Suffix
     * @param mixed  $data Zusätzliche Daten
     *
     * @return void
     */
    public function add_action($obj, $tpl, $action = '', $data = '') {
        $xaction = $this->_action;

        if ($action !== '' && $action[0] == '&') {
            $x_action = $xaction . $action;
        } else {
            $x_action = $action;
        }

        $xdata = array();
        $xdata['action'] = dbx()->action_url((string)$x_action);

        if (is_array($data)) {
            $xdata = array_merge($xdata, $data);
        }

        $xdata = $this->escape_tooltip_template_data($xdata);
        $tpl = $this->get_tpl($tpl, $xdata);
        $tpl = str_replace('{class}', '', $tpl);
        $tpl = str_replace('{tooltip}', '', $tpl);

        $this->_obj[$obj] = $tpl;
    }

    /**
     * Liefert die aktive Felddefinitionsquelle.
     *
     * Verwendung
     * ----------
     * Zentraler Hybrid-Punkt für DD/FD. Wird intern von `add_fld()` und
     * `add_flds()` genutzt.
     *
     * Marker
     * ------
     * - `fd::` bedeutet: aktive Feldquelle verwenden, also `_fd` wenn gesetzt,
     *   sonst `_dd`
     * - `dd::` bedeutet: gezielt `_dd` verwenden
     * - jeder andere Wert gilt als explizite Quelle
     *
     * Auswirkung
     * ----------
     * Die Funktion erzeugt keinen Feldzustand und lädt keine Datei. Sie entscheidet
     * nur, welche Quelle für den nächsten Lookup verwendet werden soll.
     *
     * @param mixed $source Explizite Quelle, `fd::` oder `dd::`
     *
     * @return string
     */
    private function get_active_field_source($source = 'fd::'): string {
        if ($source === 'fd::') {
            if ($this->_fd) {
                return (string) $this->_fd;
            }

            return (string) $this->_dd;
        }

        if ($source === 'dd::') {
            return (string) $this->_dd;
        }

        return (string) $source;
    }

    /**
     * Löst einen einzelnen Feld-Marker gegen FD oder DD auf.
     *
     * Verwendung
     * ----------
     * Diese Funktion wird ausschließlich von `add_fld()` benutzt, um einzelne
     * Feldattribute wie `tpl`, `label`, `rules`, `class`, `tooltip`,
     * `placeholder`, `errormsg` und `remap` aufzulösen.
     *
     * Marker
     * ------
     * - `fd::` liest den Wert aus der aktiven Feldquelle
     * - `dd::` liest den Wert gezielt aus `_dd`
     * - jeder andere Wert wird unverändert zurückgegeben
     *
     * Auswirkung
     * ----------
     * Dadurch kann ein Feld überwiegend aus FD kommen, während einzelne Attribute
     * bewusst aus DD übernommen werden können:
     *
     * ```php
     * $this->add_fld('email', label: 'dd::');
     * ```
     *
     * @param mixed  $value Feldwert, Marker oder expliziter Wert
     * @param string $var Feldattribut
     * @param array  $field_record Aktiver FD/DD-Feldrecord
     * @param array  $dd_record Reiner DD-Feldrecord
     *
     * @return mixed
     */
    private function get_fld_marker_value_from_records($value, string $var, $field_record = array(), $dd_record = array()) {
        if (!is_array($field_record)) {
            $field_record = array();
        }

        if (!is_array($dd_record)) {
            $dd_record = array();
        }

        if ($value === 'fd::') {
            return $field_record[$var] ?? '';
        }

        if ($value === 'dd::') {
            return $dd_record[$var] ?? '';
        }

        return $value;
    }



    /**
     * Liefert Felddefinitionen aus einer DD-/FD-Quelle.
     *
     * Verwendung
     * ----------
     * Diese Funktion ist der zentrale, bewusst einfache Source-Loader für
     * Felddefinitionen. Der historische Name bleibt erhalten, weil bestehende
     * dbxForm-Logik bereits darüber arbeitet.
     *
     * Unterstützte Quellen
     * --------------------
     * - `name`              : Standard-DD über dbxDB
     * - `dd:name`           : Standard-DD über dbxDB
     * - `cfg:modul`         : `dbx/modules/{modul}/cfg/config.dd.php`
     * - `def:modul`         : `dbx/modules/{modul}/dd/{modul}.dd.php`
     * - `mod:name`          : `dbx/modules/{aktives_modul}/dd/{name}.dd.php`
     * - `fd:name`           : `dbx/modules/{aktives_modul}/fd/{name}.fd.php`
     * - `fd:modul|name`     : `dbx/modules/{modul}/fd/{name}.fd.php`
     * - `modul|name`        : Kurzform für `fd:modul|name`
     *
     * Auswirkung
     * ----------
     * Gibt immer ein Array zurück. Fehlende Dateien oder ungültige Quellen
     * führen zu einem leeren Array und brechen den Formularlauf nicht ab.
     *
     * @param string $dd DD-/FD-Quellenangabe
     *
     * @return array
     */
    private function get_dd_fields_source(string $dd): array {
        $dd = trim($dd);

        if ($dd === '' || $dd === 'dd') {
            return array();
        }

        $mod  = 'dd';
        $name = $dd;

        foreach (array('cfg:', 'def:', 'mod:', 'fd:', 'dd:') as $prefix) {
            if (strpos($dd, $prefix) === 0) {
                $mod  = substr($prefix, 0, -1);
                $name = substr($dd, strlen($prefix));
                break;
            }
        }

        if ($mod === 'dd' && strpos($dd, '|') !== false) {
            $mod  = 'fd';
            $name = $dd;
        }

        if ($mod === 'dd') {
            $fields = $this->oDB->get_dd_fields($name);
            if (method_exists($this->oDB, 'get_dd_file')) {
                $this->add_editor_file('dd', $this->oDB->get_dd_file($name));
            }
            return is_array($fields) ? $fields : array();
        }

        $dd_file = '';

        switch ($mod) {
            case 'cfg':
                $dd_file = dbx()->get_base_dir() . "dbx/modules/$name/cfg/config.dd.php";
                break;

            case 'def':
                $dd_file = dbx()->get_base_dir() . "dbx/modules/$name/dd/$name.dd.php";
                break;

            case 'mod':
                $modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
                $dd_file = dbx()->get_base_dir() . "dbx/modules/$modul/dd/$name.dd.php";
                break;

            case 'fd':
                $fd_modul = $this->_dbx_modul ? $this->_dbx_modul : dbx()->get_system_var('dbx_activ_modul', 'dbx');
                $fd_name  = $name;

                if (strpos($name, '|') !== false) {
                    $parts = explode('|', $name, 2);
                    $fd_modul = trim($parts[0]);
                    $fd_name  = trim($parts[1]);
                }

                if ($fd_modul === '') {
                    $fd_modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
                }

                $dd_file = dbx()->lng_resolve_file(
                    dbx()->get_base_dir() . "dbx/modules/$fd_modul/fd/",
                    $fd_name,
                    'fd.php',
                    $this->_dbx_lng,
                    true
                );
                if ($dd_file === '' || !is_file($dd_file)) {
                    $dd_file = dbx()->get_base_dir() . "dbx/modules/$fd_modul/fd/$fd_name.fd.php";
                }
                break;
        }

        return $this->read_dd_fields_direct($dd_file);
    }

    /**
     * Liest Felddefinitionen direkt aus einer DD-/FD-Datei.
     *
     * Verwendung
     * ----------
     * Gemeinsamer Datei-Leser für direkte DD- und FD-Quellen. Die Datei soll
     * ein `$fields`-Array bereitstellen.
     *
     * Auswirkung
     * ----------
     * Existiert die Datei nicht oder liefert sie kein Array, wird ein leeres
     * Array zurückgegeben. `$table` und `$indexes` werden bewusst vorbereitet,
     * damit DD-Dateien wie bisher funktionieren.
     *
     * Performance
     * -----------
     * Direkte DD-/FD-Dateiquellen werden wie DD-Quellen gecacht. Das ist wichtig,
     * weil get_dd() bei add_fld()/add_flds() mehrfach dieselbe Quelle abfragt.
     *
     * Cache
     * -----
     * - Runtime-Cache: innerhalb eines PHP-Requests
     * - Session-Cache: zwischen Requests
     * - Invalidierung über Datei-Pfad, filemtime und filesize
     *
     * @param string $dd_file DD-/FD-Datei
     *
     * @return array
     */
    private function read_dd_fields_direct(string $dd_file): array {
        $fields  = array();
        $table   = array();
        $indexes = array();
        $messages = array();

        $dd_file = dbx()->os_path($dd_file);

        if (!file_exists($dd_file)) {
            return array();
        }

        $real_file = realpath($dd_file);
        if (!$real_file) {
            $real_file = $dd_file;
        }

        $normalized = str_replace('\\', '/', $real_file);
        $kind       = (substr($normalized, -7) === '.fd.php') ? 'fd' : 'dd';
        $mtime      = @filemtime($real_file);
        $size       = @filesize($real_file);
        $cache_key  = md5($normalized);

        $this->add_editor_file($kind, $real_file);
        $runtime_cache =& $this->session_cache_section('form', 'field_source_runtime');
        $field_source_cache =& $this->session_cache_section('form', 'field_source');

        if (isset($runtime_cache[$cache_key])) {
            $entry = $runtime_cache[$cache_key];

            if (
                is_array($entry) &&
                ($entry['mtime'] ?? 0) === $mtime &&
                ($entry['size'] ?? 0) === $size &&
                isset($entry['fields']) &&
                is_array($entry['fields']) &&
                isset($entry['messages']) &&
                is_array($entry['messages'])
            ) {
                if ($kind === 'fd') {
                    $this->apply_fd_messages($entry['messages']);
                }
                return $entry['fields'];
            }
        }

        if (
            !isset($field_source_cache[$cache_key]) &&
            isset($_SESSION['dbx']['cache']['field_source'][$cache_key]) &&
            is_array($_SESSION['dbx']['cache']['field_source'][$cache_key])
        ) {
            $field_source_cache[$cache_key] = $_SESSION['dbx']['cache']['field_source'][$cache_key];
            unset($_SESSION['dbx']['cache']['field_source'][$cache_key]);
        }

        if (isset($field_source_cache[$cache_key])) {
            $entry = $field_source_cache[$cache_key];

            if (
                is_array($entry) &&
                ($entry['mtime'] ?? 0) === $mtime &&
                ($entry['size'] ?? 0) === $size &&
                isset($entry['fields']) &&
                is_array($entry['fields']) &&
                isset($entry['messages']) &&
                is_array($entry['messages'])
            ) {
                $runtime_cache[$cache_key] = $entry;
                if ($kind === 'fd') {
                    $this->apply_fd_messages($entry['messages']);
                }
                return $entry['fields'];
            }
        }

        include $real_file;

        if (!is_array($fields)) {
            $fields = array();
        }
        if (!is_array($messages)) {
            $messages = array();
        }

        $entry = array(
            'path'     => $normalized,
            'kind'     => $kind,
            'mtime'    => $mtime,
            'size'     => $size,
            'fields'   => $fields,
            'messages' => $messages,
        );

        $runtime_cache[$cache_key] = $entry;
        $field_source_cache[$cache_key] = $entry;

        if ($kind === 'fd') {
            $this->apply_fd_messages($messages);
        }

        return $fields;
    }

    /**
     * Sucht ein Feld nach Name in einer geladenen Feldliste.
     *
     * Verwendung
     * ----------
     * Wird von `get_dd()` genutzt. Der historische Name bleibt erhalten, die
     * Funktion arbeitet aber neutral für DD- und FD-Feldlisten.
     *
     * @param array  $fields Feldliste
     * @param string $fld Feldname
     *
     * @return array
     */
    private function get_dd_fld(array $fields, string $fld): array {
        foreach ($fields as $record) {
            if (isset($record['name']) && $record['name'] === $fld) {
                return $record;
            }
        }

        return array();
    }

    private function &session_cache_section(string $bereich, string $section) {
        if (!isset($_SESSION['dbx']) || !is_array($_SESSION['dbx'])) {
            $_SESSION['dbx'] = array();
        }

        if (!isset($_SESSION['dbx']['cache']) || !is_array($_SESSION['dbx']['cache'])) {
            $_SESSION['dbx']['cache'] = array();
        }

        if (!isset($_SESSION['dbx']['cache'][$bereich]) || !is_array($_SESSION['dbx']['cache'][$bereich])) {
            $_SESSION['dbx']['cache'][$bereich] = array();
        }

        if (!isset($_SESSION['dbx']['cache'][$bereich][$section]) || !is_array($_SESSION['dbx']['cache'][$bereich][$section])) {
            $_SESSION['dbx']['cache'][$bereich][$section] = array();
        }

        return $_SESSION['dbx']['cache'][$bereich][$section];
    }

    /**
     * Liest einen Feldwert oder das ganze Feld aus einer DD-/FD-Quelle.
     *
     * Wirkung
     * -------
     * - Standard-DDs laufen über dbxDB
     * - direkte DD-/FD-Dateiquellen bleiben möglich
     * - leeres Ergebnis liefert `''`
     * - `*` liefert den kompletten Feldsatz
     *
     * Performance
     * -----------
     * Die komplette Feldquelle wird über get_dd_fields_source() geladen.
     * Zusätzlich wird der einzelne Feld-Record pro Quelle/Feldname im aktuellen
     * PHP-Request gecacht. Dadurch muss add_fld() nicht für jedes Attribut
     * erneut linear durch alle Felddefinitionen laufen.
     *
     * @param string $dd DD-/FD-Angabe
     * @param string $fld Feldname
     * @param string $var Variablenname oder `*`
     *
     * @return mixed
     */
    public function get_dd(string $dd, string $fld, string $var) {
        if ($dd === '' || $dd === 'dd') {
            return '';
        }

        if ($fld === '') {
            return '';
        }

        $cache_key = md5($dd . "\0" . $fld);
        $field_cache =& $this->session_cache_section('form', 'runtime_field');

        if (isset($field_cache[$cache_key]) && is_array($field_cache[$cache_key])) {
            $field = $field_cache[$cache_key];
        } else {
            $fields = $this->get_dd_fields_source($dd);

            if (!is_array($fields)) {
                $field_cache[$cache_key] = array();
                return '';
            }

            $field = $this->get_dd_fld($fields, $fld);

            if (!is_array($field)) {
                $field = array();
            }

            $field_cache[$cache_key] = $field;
        }

        if (!$field) {
            return '';
        }

        if ($var === '*') {
            return $field;
        }

        return $field[$var] ?? '';
    }

    /**
     * Konvertiert eine SQL-Definition in ein Options-Array.
     *
     * Syntax
     * ------
     * `sql:<dd>|<key>|<fields>|<where>|<order>|<limit>`
     *
     * Beispiel
     * --------
     * ```php
     * $options = $this->sql_to_array('sql:dbx_user|id|name,email|active=1|name ASC|100');
     * ```
     *
     * Auswirkung
     * ----------
     * Liest Datensätze über dbxDB und erzeugt ein Array für Select-Optionen.
     *
     * @param string $data SQL-Definition
     *
     * @return array
     */
    private function sql_dd_file_exists($modul, $dd) {
        $modul = trim((string) $modul);
        $dd    = trim((string) $dd);

        if ($dd === '') {
            return false;
        }

        if ($modul === '') {
            $modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
        }

        $file1 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/dd/' . $dd . '.dd.php');
        $file2 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/dd/' . $dd . '.dd.php');

        return file_exists($file1) || file_exists($file2);
    }

    private function normalize_sql_dd_name($modul, $source) {
        $source = trim((string) $source);

        if ($source === '') {
            return '';
        }

        $candidates = array(
            $source,
            str_replace('dbx_', 'dbx', $source),
            str_replace(' ', '', ucwords(str_replace('_', ' ', $source))),
            lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $source))))
        );

        foreach ($candidates as $candidate) {
            if ($this->sql_dd_file_exists($modul, $candidate)) {
                return $candidate;
            }
        }

        return $source;
    }

    private function sql_to_array($data) {
        $dd       = '';
        $xkey     = '';
        $flds     = '*';
        $where    = '';
        $order    = '';
        $limit    = 888;
        $asc_desc = 'ASC';
        $xdata    = array();
        $compact_sql_syntax = false;

        $data = str_replace('sql:', '', (string) $data);
        $work = explode('|', $data);

        if (isset($work[0])) {
            $dd = $work[0];
        }

        if (isset($work[1])) {
            $xkey = $work[1];
        }

        if ($dd && $xkey && strpos($xkey, ',') !== false) {
            $sqlSource = array_map('trim', explode(',', $xkey));
            $dd        = $this->normalize_sql_dd_name($dd, $sqlSource[0] ?? '');
            $xkey      = $sqlSource[1] ?? 'id';
            $flds      = $sqlSource[2] ?? $xkey;
            $compact_sql_syntax = true;
        }

        if (isset($work[2])) {
            if ($compact_sql_syntax) {
                $where = $work[2];
            } else {
                $flds = $work[2];
            }
        }

        if (isset($work[3])) {
            if ($compact_sql_syntax) {
                $order = $work[3];
            } else {
                $where = $work[3];
            }
        }

        if (isset($work[4])) {
            if ($compact_sql_syntax) {
                if ((int) $work[4] > 0) {
                    $limit = (int) $work[4];
                }
            } else {
                $order = $work[4];
            }
        }

        if (!$compact_sql_syntax && isset($work[5]) && (int) $work[5] > 0) {
            $limit = (int) $work[5];
        }

        $xdata[0] = 'Bitte auswählen';

        if ($order && strpos($order, ' DESC') !== false) {
            $asc_desc = 'DESC';
            $order    = str_replace(' DESC', '', $order);
        }

        if ($order && strpos($order, ' ASC') !== false) {
            $asc_desc = 'ASC';
            $order    = str_replace(' ASC', '', $order);
        }

        if ($dd) {
            $xflds = $flds;

            if (!$xkey) {
                $xkey = 'id';
            }

            if (strpos(',' . $xflds . ',', ',' . $xkey . ',') === false && $xflds !== '*') {
                $xflds .= ',' . $xkey;
            }

            $data = $this->oDB->select($dd, $where, $xflds, $order, $asc_desc, '', $limit, 0, 0);

            if (is_array($data)) {
                $displayFields = ($flds === '*') ? array() : array_map('trim', explode(',', $flds));

                foreach ($data as $record) {
                    $value = '';

                    foreach ($record as $fld => $val) {
                        $use = ($flds === '*') || in_array($fld, $displayFields, true);

                        if ($use) {
                            if ($value !== '') {
                                $value .= ' | ';
                            }

                            $value .= $val;
                        }
                    }

                    if (isset($record[$xkey])) {
                        $xdata[$record[$xkey]] = $value;
                    }
                }
            }
        }

        return $xdata;
    }


    /**
     * Fügt alle Felder der aktiven DD-/FD-Felddefinitionsquelle hinzu.
     *
     * Verwendung
     * ----------
     * Diese Methode ist nur ein Auto-Wrapper über `add_fld()`. Sie erzeugt
     * keine eigene Feld-Engine, validiert nicht selbst und rendert nicht selbst.
     *
     * Hybrid-Regel
     * ------------
     * - explizite Quelle gewinnt
     * - `fd::` nutzt `_fd`, falls gesetzt, sonst `_dd`
     * - `dd::` nutzt gezielt `_dd`
     * - wenn keine Quelle vorhanden ist, passiert nichts
     *
     * Beispiel
     * --------
     * ```php
     * $oForm->_fd = 'dbx|login';
     * $oForm->add_flds();
     * $oForm->add_fld('remember_me', 'checkbox-label', label: 'Angemeldet bleiben', rules: 'int');
     * ```
     *
     * Auswirkung
     * ----------
     * Für jeden Eintrag mit `name` in der Feldquelle wird `add_fld($name, dd: $source)`
     * aufgerufen. Manuell nachträglich hinzugefügte Felder bleiben möglich und
     * können bestehende Namen bewusst überschreiben.
     *
     * @param mixed $dd Explizite DD-/FD-Quelle, `fd::` oder `dd::`
     *
     * @return int Anzahl angelegter Felder
     */
    public function add_flds($dd = 'fd::') {
        $source = $this->get_active_field_source($dd);

        if ($source === '' || $source === 'dd') {
            return 0;
        }

        $fields = $this->get_dd_fields_source($source);

        if (!is_array($fields) || !$fields) {
            return 0;
        }

        $count = 0;

        foreach ($fields as $record) {
            if (!is_array($record)) {
                continue;
            }

            if (!isset($record['name']) || trim((string) $record['name']) === '') {
                continue;
            }

            $name = trim((string) $record['name']);
            $this->add_fld($name, dd: $source);
            $count++;
        }

        return $count;
    }


    /**
     * Fügt ein Feld zur Formular-Definition hinzu.
     *
     * Zweck
     * -----
     * Diese Methode ist der zentrale Feld-Builder von dbxForm. Sie kombiniert:
     * - direkte Feldparameter
     * - FD-/DD-basierte Defaults
     * - aufbereitete Daten/Optionen
     *
     * Marker-Regel
     * ------------
     * - `fd::` bedeutet: aktive Feldquelle verwenden, also `_fd` wenn gesetzt,
     *   sonst `_dd`
     * - `dd::` bedeutet: gezielt `_dd` verwenden
     * - jeder andere Wert ist ein expliziter Wert
     *
     * Beispiel
     * --------
     * ```php
     * $oForm->_dd = 'dbx_user';
     * $oForm->_fd = 'dbx_user_login';
     *
     * // alles aus FD, sofern vorhanden
     * $oForm->add_fld('email');
     *
     * // Feld überwiegend aus FD, aber Label bewusst aus DD
     * $oForm->add_fld('email', label: 'dd::');
     *
     * // Template manuell, Label aus DD, Rest aus FD
     * $oForm->add_fld('email', tpl: 'text-label', label: 'dd::');
     * ```
     *
     * Auswirkung
     * ----------
     * Es wird genau ein Eintrag in `_flds[$name]` erzeugt oder überschrieben.
     * Die spätere Request-Auswertung, Validierung und Ausgabe laufen unverändert
     * über die bestehende dbxForm-Pipeline.
     *
     * @param string $name Feldname
     * @param string $tpl Template, `fd::` oder `dd::`
     * @param mixed  $label Label, `fd::` oder `dd::`
     * @param mixed  $rules Regeln, `fd::` oder `dd::`
     * @param mixed  $class CSS-Klasse, `fd::` oder `dd::`
     * @param mixed  $data Daten, `fd::` oder `dd::`
     * @param mixed  $options Optionen, `fd::` oder `dd::`
     * @param mixed  $tooltip Tooltip, `fd::` oder `dd::`
     * @param mixed  $placeholder Placeholder, `fd::` oder `dd::`
     * @param mixed  $errormsg Feldfehlertext, `fd::` oder `dd::`
     * @param mixed  $dd DD-/FD-Quelle, `fd::` oder `dd::`
     * @param mixed  $remap Optionaler Feld-Remap, `fd::` oder `dd::`
     *
     * @return void
     */
    public function add_fld($name, $tpl = 'fd::', $label = 'fd::', $rules = 'fd::', $class = 'fd::', $data = 'fd::', $options = 'fd::', $tooltip = 'fd::', $placeholder = 'fd::', $errormsg = 'fd::', $dd = 'fd::', $remap = 'fd::') {
        $field_source   = $this->get_active_field_source($dd);
        $dd_source      = $this->get_active_field_source('dd::');
        $field_record   = $field_source ? $this->get_dd($field_source, $name, '*') : array();
        $dd_record      = $dd_source ? $this->get_dd($dd_source, $name, '*') : array();
        $source_data    = array();
        $source_options = array();
        $dd_data        = array();
        $dd_options     = array();

        if (is_array($field_record) && $field_record) {
            $source_data    = $this->process_array($field_record['data'] ?? '');
            $source_options = $this->process_array($field_record['options'] ?? '');
        }

        if (is_array($dd_record) && $dd_record) {
            $dd_data    = $this->process_array($dd_record['data'] ?? '');
            $dd_options = $this->process_array($dd_record['options'] ?? '');
        }

        if ($data === 'fd::' || $data === '' || $data === null) {
            $data = $source_data;
        } elseif ($data === 'dd::') {
            $data = $dd_data;
        } else {
            $data = $this->merge_arrays($this->process_array($data), $source_data);
        }

        if ($options === 'fd::' || $options === '' || $options === null) {
            $options = $source_options;
        } elseif ($options === 'dd::') {
            $options = $dd_options;
        } else {
            $options = $this->merge_arrays($this->process_array($options), $source_options);
        }

        if (isset($data['dd'])) {
            $field_source = $this->get_active_field_source($data['dd']);
            $field_record = $field_source ? $this->get_dd($field_source, $name, '*') : array();
        }

        if ($label === 'fd::' && isset($data['label'])) {
            $label = $data['label'];
        }

        if ($rules === 'fd::' && isset($data['rules'])) {
            $rules = $data['rules'];
        }

        if ($class === 'fd::' && isset($data['class'])) {
            $class = $data['class'];
        }

        if ($tooltip === 'fd::' && isset($data['tooltip'])) {
            $tooltip = $data['tooltip'];
        }

        if ($placeholder === 'fd::' && isset($data['placeholder'])) {
            $placeholder = $data['placeholder'];
        }

        if ($errormsg === 'fd::' && isset($data['errormsg'])) {
            $errormsg = $data['errormsg'];
        }

        if ($remap === 'fd::' && isset($data['remap'])) {
            $remap = $data['remap'];
        }

        $data['name']        = $name;
        $data['tpl']         = $this->get_fld_marker_value_from_records($tpl,         'tpl',         $field_record, $dd_record);
        $data['label']       = $this->get_fld_marker_value_from_records($label,       'label',       $field_record, $dd_record);
        $data['rules']       = $this->get_fld_marker_value_from_records($rules,       'rules',       $field_record, $dd_record);
        $data['class']       = $this->get_fld_marker_value_from_records($class,       'class',       $field_record, $dd_record);
        $data['tooltip']     = $this->get_fld_marker_value_from_records($tooltip,     'tooltip',     $field_record, $dd_record);
        $data['placeholder'] = $this->get_fld_marker_value_from_records($placeholder, 'placeholder', $field_record, $dd_record);
        $data['errormsg']    = $this->get_fld_marker_value_from_records($errormsg,    'errormsg',    $field_record, $dd_record);
        $data['remap']       = $this->get_fld_marker_value_from_records($remap,       'remap',       $field_record, $dd_record);

        $fld = array(
            'name'     => $name,
            'data'     => $data,
            'options'  => $options,
            'label'    => $data['label'],
            'tpl'      => $data['tpl'],
            'rules'    => $data['rules'],
            'remap'    => $data['remap'],
            'value'    => '',
            'origin'   => '',
            'errormsg' => $data['errormsg'],
            'error'    => 0,
            'changed'  => 0,
            'verify'   => 0,
            'dd'       => $field_source,
        );

        $this->_flds[$name] = $fld;
        $this->touch_request_state();
    }
    
    /**
     * Verarbeitet Eingaben zu Arrays.
     *
     * Unterstützt
     * -----------
     * - bereitses Array
     * - Query-String
     * - `sql:`-Definition
     *
     * @param mixed $input Eingabe
     *
     * @return array
     */
    private function process_array($input) {
        if (!is_array($input)) {
            if ($input) {
                $data_first = substr((string) $input, 0, 4);

                if ($data_first === 'sql:') {
                    $input = $this->sql_to_array($input);
                } else {
                    $input = $this->url_to_array($input);
                }
            } else {
                $input = array();
            }
        }

        return is_array($input) ? $input : array();
    }

    /**
     * Ergänzt fehlende Keys aus `$secondary` in `$primary`.
     *
     * Verwendung
     * ----------
     * Wird beim Mergen von direkten Feldparametern und DD-/FD-Defaults genutzt.
     * Direkte Werte in `$primary` bleiben erhalten.
     *
     * @param array $primary Primärdaten
     * @param array $secondary Ergänzungsdaten
     *
     * @return array
     */
    private function merge_arrays($primary, $secondary) {
        if (!is_array($primary)) {
            $primary = array();
        }

        if (!is_array($secondary)) {
            $secondary = array();
        }

        foreach ($secondary as $key => $value) {
            if (!array_key_exists($key, $primary)) {
                $primary[$key] = $value;
            }
        }

        return $primary;
    }

    /**
     * Markiert den internen Request-Status als neu zu berechnen.
     *
     * Zweck
     * -----
     * Immer wenn der Modulcode Felder, `_post` oder andere request-relevante
     * Daten ändert, muss die interne Submit-/Validate-/Changed-Sicht ggf. neu
     * bewertet werden.
     *
     * @return void
     */
    private function touch_request_state() {
        $this->_form_validate = -1;
        $this->_fld_changes   = -1;
        $this->_form_submit   = -1;
    }


    
    /**
     * Prüft ein einzelnes Feld und baut seinen Laufzustand auf.
     *
     * Auswirkungen
     * ------------
     * - setzt `value`, `origin`, `error`, `changed`, `verify`
     * - schreibt geänderte und valide Werte nach `_post`
     *
     * @param int   $submit 0|1
     * @param array $fld Felddefinition
     *
     * @return array
     */
    public function check_fld_data($submit, $fld) {
        if (($fld['verify'] ?? 0)) {
            return $fld;
        }

        $errormsg  = 'Bitte Eingabe pruefen';
        $name      = $fld['name'] ?? '';
        $fld_rules = $fld['rules'] ?? '';

        if (!$submit) {
            $resolved = $this->resolve_fld_val($name, '', '', false, true);

            $fld['value']   = $resolved['value'];
            $fld['origin']  = $resolved['origin'];
            $fld['changed'] = 0;
            $fld['error']   = 0;
            $fld['verify']  = 1;

            return $fld;
        }

        $old       = $this->resolve_fld_val($name, '', '', false, true);
        $old_value = $old['value'];

        $resolved = $this->resolve_fld_val($name, '', $fld_rules, true, false);
        $value    = $resolved['value'];
        $ok       = (bool) $resolved['ok'];
        $validation = is_array($resolved['validation'] ?? null)
            ? $resolved['validation']
            : array();

        $fld['origin'] = $resolved['origin'];
        $fld['error']  = $ok ? 0 : 1;
        $fld['value']  = $value;
        $fld['validation'] = $validation;

        if (!empty($fld['data']['errormsg'])) {
            $errormsg = $fld['data']['errormsg'];
        } elseif (!$ok && !empty($validation['message'])) {
            $errormsg = (string)$validation['message'];
        }

        if ($fld['error']) {
            $this->add_fld_error($name, $errormsg);
            $fld['data']['errormsg'] = $errormsg;
        }

        $value_compare = $value;

        if (is_array($value_compare)) {
            $values = '';

            foreach ($value_compare as $keyval) {
                if ($values !== '') {
                    $values .= ',';
                }

                $values .= $keyval;
            }

            $value_compare = $values;
        }

        $change = $this->_fld_change_state;

        if ($value_compare != $old_value || $change == '*') {
            $fld['changed'] = 1;
        } else {
            $fld['changed'] = 0;
        }

        if (!$fld['error'] && $fld['changed']) {
            $this->_post[$name] = $value_compare;
        }

        $fld['verify'] = 1;

        return $fld;
    }


    /**
     * Prüft alle Felder des Formulars genau einmal pro Request-Zyklus.
     *
     * Auswirkung
     * ----------
     * Setzt `_post`, `_errors`, `_warnings` neu und aktualisiert alle Feld-
     * Zustände in `_flds`.
     *
     * @param int $submit 0|1
     *
     * @return void
     */
    public function check_flds_data($submit) {
        if ($this->_form_validate == 1) {
            return;
        }

        $this->_post     = array();
        $this->_errors   = array();
        $this->_warnings = array();

        foreach ($this->_flds as $no => $fld) {
            $fld = $this->check_fld_data($submit, $fld);
            $this->_flds[$no] = $fld;
        }

        $this->_form_validate = 1;
        $this->_fld_changes   = -1;
    }

    /**
     * Führt die Request-Auswertung aus.
     *
     * Sinn/Zweck
     * ----------
     * Das ist der zentrale Einstieg für die Phase "Request auswerten". Damit
     * werden Submit, Feldprüfung und Changed-Basis sauber vorbereitet.
     *
     * Auswirkung
     * ----------
     * Erkennt einen Submit über das Sicherheitsfeld des Formulars und ruft
     * `check_flds_data()` genau einmal pro Request-Zyklus auf.
     *
     * @return void
     */
    private function evaluate_request() {
        if ($this->_form_validate == 1 && $this->_form_submit > -1) {
            dbx()->debug("dbxForm submit cached: fid=({$this->_fid}) submit=({$this->_form_submit})");
            return;
        }

        $submit = 0;
        $fld = $this->secure_fld_name();
        $secure = $this->secure_token($fld);
        $posted = '';
        $hasPost = 0;
        $match = 0;

        if ($fld !== '' && isset($_POST[$fld])) {
            $posted = (string)$_POST[$fld];
            $hasPost = 1;

            if ($secure !== '' && hash_equals($secure, $posted)) {
                $submit = 1;
                $match = 1;
            }
        }

        dbx()->debug(
            "dbxForm submit evaluate: fid=({$this->_fid}) field=($fld) post=($hasPost) match=($match) submit=($submit)"
        );

        $this->_form_submit = $submit;
        $this->check_flds_data($submit);

        if ($submit) {
            $this->rotate_secure_token($fld);
        } else {
            $this->sync_secure_field($fld, $secure);
        }
    }

    /**
     * Gibt die Formular-Infobox zurück.
     *
     * @param string $mode info|success|error|warning
     * @param string $msg Meldung
     *
     * @return string
     */
    public function get_form_msg($mode, $msg = '') {
        if (!$msg) {
            return '';
        }

        $file = $this->_tpl_form_info;
        $tpl  = '';

        if ($mode == 'success') {
            $file = $this->_tpl_form_success;
        }

        if ($mode == 'error') {
            $file = $this->_tpl_form_error;
        }

        if ($mode == 'info') {
            $file = $this->_tpl_form_info;
        }

        if ($mode == 'warning') {
            $file = $this->_tpl_form_warning;
        }

        if ($file) {
            $tpl = $this->get_tpl($file);
            $tpl = str_replace('{msg}', $msg, $tpl);
        }

        return str_replace('{class}', $mode, $tpl);
    }

    /**
     * Gibt eine Feldmeldung zurück.
     *
     * @param string $mode info|success|error|warning
     * @param string $msg Meldung
     *
     * @return string
     */
    public function get_fld_msg($mode, $msg = '') {
        $file = '';
        $tpl  = '';

        if ($mode == 'success') {
            $file = $this->_tpl_fld_success;
        }

        if ($mode == 'error') {
            $file = $this->_tpl_fld_error;
        }

        if ($mode == 'info') {
            $file = $this->_tpl_fld_info;
        }

        if ($mode == 'warning') {
            $file = $this->_tpl_fld_warning;
        }

        if ($file) {
            $tpl = $this->get_tpl($file);
            $tpl = str_replace('{msg}', $msg, $tpl);
        }

        return $tpl;
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
     * Fügt CSS hinzu.
     *
     * @param string $css CSS-Referenz
     *
     * @return void
     */
    public function add_css($css = '') {
        if ($css) {
            $this->_css[] = $css;
        }
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
     * Öffentliche Submit-Abfrage.
     *
     * @return int 0|1
     */
    public function submit() {
        return $this->forward_submit();
    }

    /**
     * Gibt die Anzahl der Fehler zurück.
     *
     * @return int
     */
    public function errors() {
        $this->evaluate_request();

        if ($this->_general_error > '') {
            $this->_errors['general'] = 1;
        }

        return count($this->_errors);
    }

    /**
     * Gibt die Anzahl der Warnungen zurück.
     *
     * @return int
     */
    public function warnings() {
        $this->evaluate_request();
        return count($this->_warnings);
    }

    /**
     * Gibt die Anzahl geänderter Felder zurück.
     *
     * @param int $changed Initialwert
     *
     * @return int
     */
    public function changed($changed = 0) {
        $this->evaluate_request();

        if ($this->_form_submit != 1) {
            $this->_fld_changes = 0;
            return 0;
        }

        if ($this->_fld_changes != -1) {
            return $this->_fld_changes;
        }

        foreach ($this->_flds as $fld) {
            $changed += (int) ($fld['changed'] ?? 0);
        }

        $this->_fld_changes = $changed;

        return $changed;
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

        $this->oDB->_fld_id = $this->_fld_id;
        $ok = $this->oDB->save($dd, $post, $rid);

        if (!$ok) {
            dbx()->debug("DB-Error=(" . $this->oDB->_error . ")\nQuery=(" . $this->oDB->_query . ")");
            $saveError = $this->get_fd_message('save_error');
            if ($saveError !== '') {
                $this->_msg_error = $saveError;
            }
            $this->_general_error = $this->_msg_error;
            $this->set_form_saved(false);
            return $ok;
        }

        $saveSuccess = $this->get_fd_message('save_success');
        if ($saveSuccess !== '') {
            $this->_msg_success = $saveSuccess;
        }

        if (!$rid) {
            $rid = (int) $this->oDB->_insert_id;
            $this->_rid = $rid;
        }

        if ($rid > 0) {
            $this->set_state_value('rid', $rid);
        }

        if ($rid && $ok && $reread) {
            $new = $this->oDB->select1($dd, $rid);

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
     * Standard-Suchfeld fuer Grid-Toolbars ({dbx_search}).
     *
     * @param array $overrides Template-Daten fuer dbx|search
     * @return void
     */
    protected function add_default_search_rep(array $overrides = array()) {
        $data = $this->prepare_search_tpl_data(array_merge(array(
            'extra_attrs' => 'data-dbx="grid-search"',
        ), $overrides));
        $template = trim((string)($data['label'] ?? '')) === ''
            ? 'dbx|search'
            : 'dbx|search-label';
        $this->add_rep('dbx_search', $this->get_tpl($template, $data, 'htm', (int)$data['i']));
    }

    private function is_search_field_tpl($tpl): bool {
        $tpl = strtolower(trim((string) $tpl));

        return $tpl === 'dbx|search' || $tpl === 'search' || substr($tpl, -7) === '|search';
    }

    /**
     * Bereitet ausschliesslich die kontrollierten Template-Daten des
     * Suchfelds vor. Ein Feldwert wird in create_fld() bereits als
     * Benutzereingabe HTML-sicher gemacht und hier nicht erneut escaped.
     */
    private function prepare_search_tpl_data(
        array $data,
        ?string $name = null,
        ?string $value = null,
        ?int $i = null
    ): array {
        $data = dbx()->search_defaults($data);
        $data['name'] = $name !== null ? $name : (string)($data['name'] ?? '');
        $data['value'] = $value !== null ? $value : (string)($data['value'] ?? '');
        $data['i'] = $i !== null ? $i : (int)($data['i'] ?? $this->_next_i);
        $data['label'] = (string)($data['label'] ?? '');
        $data['class'] = (string)($data['class'] ?? '');
        $data['style'] = (string)($data['style'] ?? '');
        return $data;
    }

    /**
     * Report-Suchfeld dbx_rwhere immer als dbx|search rendern.
     *
     * @param string $name
     * @param string $tpl
     * @param array  $data
     * @return void
     */
    private function prepare_report_search_fld($name, &$tpl, array &$data): void {
        if ($name !== 'dbx_rwhere') {
            return;
        }

        $tpl = 'dbx|search';
        $defaults = dbx()->search_defaults();

        $data['label'] = '';
        $data['placeholder'] = $defaults['placeholder'];
        $data['title'] = trim((string)($data['title'] ?? '')) !== ''
            ? (string)$data['title']
            : $defaults['title'];
        $data['input_class'] = $defaults['input_class'];
        $data['wrap_class'] = $defaults['wrap_class'];
        $data['data_role'] = $defaults['data_role'];
        $data['extra_attrs'] = $defaults['extra_attrs'];
    }

    /**
     * Erzeugt HTML für ein Feld.
     *
     * Wichtig
     * -------
     * Diese Methode ist primär Render-Logik. Sie arbeitet idealerweise mit
     * bereits vorbereitetem Feldzustand. Falls ein Feld noch nicht geprüft
     * wurde, wird es defensiv lokal vorbereitet, ohne den globalen Feldbestand
     * zu zerstören.
     *
     * @param array $fld Felddefinition
     * @param int   $i Laufindex
     *
     * @return string
     */
    public function create_fld($fld, $i = 0) {
        if (!$i) {
            $i = $this->_next_i;
        }

        if (!($fld['verify'] ?? 0)) {
            $fld = $this->check_fld_data($this->submit(), $fld);
        }

        $tpl       = $fld['tpl'] ?? '';
        $data      = is_array($fld['data'] ?? null) ? $fld['data'] : array();
        $options   = is_array($fld['options'] ?? null) ? $fld['options'] : array();
        $fld_value = $fld['value'] ?? '';
        $error     = (int) ($fld['error'] ?? 0);
        $name      = $fld['name'] ?? '';

        if ($error) {
            $data['class'] = trim(($data['class'] ?? '') . ' fld-error');

            if (!isset($data['errormsg']) || $data['errormsg'] === '') {
                $data['errormsg'] = 'Eingabe bitte prüfen !';
            }
        } else {
            $data['errormsg'] = '';
        }

        if (!is_array($fld_value)) {
            $fld_value = htmlspecialchars((string) $fld_value, ENT_QUOTES, 'UTF-8');

            if (!isset($data['checked']) && $fld_value !== '' && $fld_value !== '0') {
                $data['checked'] = 'checked';
            }
        }
        if (!isset($data['checked'])) {
            $data['checked'] = '';
        }

        // UI-State-Persistenz: nur wenn das Formular es global erlaubt UND das
        // Feld selbst per FD-Flag `data=ui_persist=1` zugestimmt hat. Das
        // Attribut wird von formUiPersist.js (core.js ui state) ausgewertet,
        // um den Feldwert dauerhaft im Browser zu merken und wiederherzustellen.
        $data['ui_persist_attr'] = '';
        if ((int) $this->_ui_state_persist === 1 && (int) ($data['ui_persist'] ?? 0) === 1 && $name !== '') {
            $formKey = $this->_fid !== '' ? $this->_fid : 'form';
            $data['ui_persist_attr'] = ' data-dbx="lib=formUiPersist|form='
                . htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8')
                . '|key=' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"';
        }

        // dbxForm validates submitted data itself; native HTML5 required would
        // mark empty fields invalid before submit and can block the request.
        $required = '';

        $data['required'] = $required;
        $data['class']    = $data['class']    ?? '';
        $data['style']    = $data['style']    ?? '';
        $data['tooltip']  = $data['tooltip']  ?? '';
        $data['errormsg'] = $data['errormsg'] ?? '';

        foreach (array('placeholder') as $htmlKey) {
            if (isset($data[$htmlKey]) && !is_array($data[$htmlKey])) {
                $data[$htmlKey] = htmlspecialchars((string)$data[$htmlKey], ENT_QUOTES, 'UTF-8');
            }
        }

        foreach (array('errormsg') as $htmlAttributeKey) {
            if (isset($data[$htmlAttributeKey]) && !is_array($data[$htmlAttributeKey])) {
                $data[$htmlAttributeKey] = htmlspecialchars((string)$data[$htmlAttributeKey], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        // Das Feld-Template erhaelt nur einen neutralen Platzhalter. So kann
        // das Fragezeichen zentral erzeugt werden, ohne dass dbxTPL das
        // vertrauenswuerdige Marker-HTML erneut escaped.
        $tooltipMarker = $this->render_field_tooltip_marker($data['tooltip']);
        $tooltipToken = '__DBX_FORM_FIELD_TOOLTIP_' . (int)$i . '__';
        $data['tooltip'] = $tooltipToken;

        $this->prepare_report_search_fld($name, $tpl, $data);

        if ($this->is_search_field_tpl($tpl)) {
            $data = $this->prepare_search_tpl_data(
                $data,
                (string)$name,
                is_array($fld_value) ? '' : (string)$fld_value,
                (int)$i
            );
            $tpl = trim((string)($data['label'] ?? '')) === ''
                ? 'dbx|search'
                : 'dbx|search-label';
        }
        $tpl = $this->get_tpl($tpl, $data, 'htm', $i);
        $tpl = str_replace($tooltipToken, $tooltipMarker, $tpl);

        if (is_array($options)) {
            $xoptions = '';
            $oid = $name . '_options';
            $options_vals = $fld_value;

            if (!is_array($options_vals)) {
                $options_vals = explode(',', (string) $options_vals);
            }

            $selected_lookup = array();
            foreach ($options_vals as $keyval) {
                $selected_lookup[(string) $keyval] = true;
            }

            foreach ($options as $key => $description) {
                $selected = isset($selected_lookup[(string) $key]) ? 'selected' : '';

                $xoptions .= '<option value="' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' .
                             htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') .
                             "</option>\n";
            }

            $tpl = str_replace('{' . $oid . '}', $xoptions, $tpl);
        }

        $tpl = $this->oTPL->replaces($tpl, $this->_replaces);

        if (!is_array($fld_value)) {
            $tpl = str_replace('{src}', $fld_value, $tpl);
            $tpl = str_replace('{value}', $fld_value, $tpl);
        }

        $tpl = dbx()->norep($tpl, $i);

        $field_markers = $this->get_field_editor_markers();

        if ($field_markers !== '') {
            $tpl = $field_markers . $tpl;
        }

        return $tpl;
    }

    /**
     * Fügt Felder in den Template-Inhalt ein.
     *
     * Wichtige Regel
     * --------------
     * Diese Methode arbeitet mit einer lokalen Sicht auf `_flds`. Der globale
     * Feldbestand wird beim Rendern NICHT zerstört.
     *
     * Auswirkung
     * ----------
     * Felder mit explizitem `{obj:feld}`-Slot werden dort eingesetzt. Felder
     * ohne expliziten Slot werden gesammelt und in `[dbx:form]` oder vor
     * `</form>` eingefügt.
     *
     * @param string $content Template-Inhalt
     * @param int    $i Laufindex
     *
     * @return string
     */
    public function merge_fld_data($content, $i = 0) {
        $form   = '';
        $editor = dbx()->get_system_var('dbx_editor', 0, 'int');

        if (!$i && !$editor) {
            $i = $this->_next_i;
        }

        foreach ($this->_flds as $fld_name => $fld) {
            $slot = $fld_name;

            if (!empty($fld['remap'])) {
                $slot = $fld['remap'];
            }

            $fid = '{obj:' . $slot . '}';
            $fld_content = $this->create_fld($fld, $i);

            if (strpos($content, $fid) !== false) {
                $content = str_replace($fid, $fld_content, $content);
            } else {
                $form .= $fld_content . "\n";
            }
        }

        if (strpos($content, '[dbx:form]') !== false) {
            $content = str_replace('[dbx:form]', $form, $content);
        } else {
            $content = str_replace('</form>', $form . '</form>', $content);
        }

        return $content;
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
        $now     = dbx()->timestamp();
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

        $countReset = max(0, (int) $this->_try_count_reset);
        $lastTry    = (float) ($sys['dbx_try_last'] ?? 0);

        if ($countReset > 0 && $lastTry > 0 && ($now - $lastTry) > $countReset) {
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
                        $sys['dbx_try_run']  = dbx()->timestamp($reset);
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
                $diff = dbx()->time_diff($now, $sys['dbx_try_run']);

                if ($diff > 0) {
                    $data = array(
                        'sec'       => (int) $diff,
                        'self'      => $self,
                        'try_count' => $sys['dbx_try_count'],
                        'run_count' => $sys['dbx_run_count'],
                    );
                    $data['msg'] = $this->oTPL->replaces((string) $msg, $data);

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

    /**
     * Führt die Ausgabephase aus.
     *
     * Ablauf
     * ------
     * - Request-Zustand sicherstellen
     * - Try-Sperre prüfen
     * - Template laden
     * - TPL-Replaces ausführen
     * - Felder einfügen
     * - Objekte einfügen
     * - Form-Meldung setzen
     * - JS-Platzhalter füllen
     * - Sysdata und Workflow-State speichern
     *
     * @return string
     */
    public function forward_run() {
        $content = '';
        $editor  = dbx()->get_system_var('dbx_editor', 0, 'int');

        $this->evaluate_request();

        $submit = $this->submit();
        $errors = $this->errors();

        $tryContent = $this->check_try_count($submit, $errors, 1);

        $i = $this->next_i();
        $this->_next_i = $i;

        $this->buildModuleBarObj();
        $this->prepareFormFrameReplaces($i);

        $replaces = $this->_replaces;
        $replaces['form-id'] = $this->_dbx_modul . '-' . $this->_fid;
        foreach (array('form_shell_class' => '', 'form_class' => '', 'form_attrs' => '') as $shellKey => $shellDefault) {
            if (!isset($replaces[$shellKey]) || (string)$replaces[$shellKey] === '') {
                $replaces[$shellKey] = $shellDefault;
            }
        }
        foreach (array('shell_panel_class' => '', 'shell_panel_attrs' => '', 'shell_body_class' => '') as $shellKey => $shellDefault) {
            if (!isset($replaces[$shellKey]) || (string)$replaces[$shellKey] === '') {
                $replaces[$shellKey] = $shellDefault;
            }
        }

        // Ein explizites "modul|template" bleibt erhalten. Das erlaubt
        // gemeinsam genutzte dbxForm-Templates auch dann, wenn das aufrufende
        // Modul ein anderes ist.
        $templateRef = strpos((string)$this->_tpl, '|') !== false
            ? (string)$this->_tpl
            : 'modul|' . $this->_tpl;
        $content = $this->get_tpl($templateRef, $replaces, 'htm', $i);
        $content = $this->merge_tpl_data($content, $i);
        $content = $this->merge_fld_data($content, $i);
        $content = $this->merge_obj($content, $i);
        $content = $this->ensureFormHelpBar($content);

        $form_msg = '';

        if ($tryContent !== '') {
            $form_msg = $tryContent;
        } else {
            if ($submit) {
                if ($this->errors()) {
                    $form_msg = $this->get_form_msg('error', $this->_msg_error);
                } elseif ($this->warnings()) {
                    $form_msg = $this->get_form_msg('warning', $this->_msg_warning);
                } else {
                    $form_msg = $this->get_form_msg('success', $this->_msg_success);
                }
            } else {
                $form_msg = $this->get_form_msg('info', $this->_msg_info);
            }
        }

        $content = str_replace('{obj:form_msg}', $form_msg, $content);

        $norep_ids = '';

        if (is_array($this->_js)) {
            foreach ($this->_js as $javascript) {
                $javascript = str_replace('{i}', $this->_next_i, $javascript);
                $norep = '<script>' . $javascript . '</script> ';
                $norep_ids .= dbx()->norep($norep, $i);
            }
        }

        $content = str_replace('[dbx:js]', $norep_ids, $content);

        if (!$editor && $content && $i) {
            $content = str_replace('{i}', $i, $content);
        }

        $content = $this->callback('run', $content);

        $this->store_sysdata();
        $this->store_workflow_state();

        return $this->add_editor_markers($content);
    }

    /**
     * Liefert den absoluten Pfad einer FD-/DD-Quelle fuer Editor-Marker.
     *
     * @param string $kind fd oder dd
     * @param string $source Aktive Formular-Quelle
     *
     * @return string
     */
    protected function resolve_fd_dd_editor_file(string $source): string {
        $source = trim($source);

        if ($source === '') {
            return '';
        }

        $mod  = 'dd';
        $name = $source;

        foreach (array('cfg:', 'def:', 'mod:', 'fd:', 'dd:') as $prefix) {
            if (strpos($source, $prefix) === 0) {
                $mod  = substr($prefix, 0, -1);
                $name = substr($source, strlen($prefix));
                break;
            }
        }

        if ($mod === 'dd' && strpos($source, '|') !== false) {
            $mod  = 'fd';
            $name = $source;
        }

        if ($mod === 'dd') {
            if (method_exists($this->oDB, 'get_dd_file')) {
                return (string) $this->oDB->get_dd_file($name);
            }

            $modul = $this->_dbx_modul ? $this->_dbx_modul : dbx()->get_system_var('dbx_activ_modul', 'dbx');

            return dbx()->get_base_dir() . "dbx/modules/$modul/dd/$name.dd.php";
        }

        $dd_file = '';

        switch ($mod) {
            case 'cfg':
                $dd_file = dbx()->get_base_dir() . "dbx/modules/$name/cfg/config.dd.php";
                break;

            case 'def':
                $dd_file = dbx()->get_base_dir() . "dbx/modules/$name/dd/$name.dd.php";
                break;

            case 'mod':
                $modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
                $dd_file = dbx()->get_base_dir() . "dbx/modules/$modul/dd/$name.dd.php";
                break;

            case 'fd':
                $fd_modul = $this->_dbx_modul ? $this->_dbx_modul : dbx()->get_system_var('dbx_activ_modul', 'dbx');
                $fd_name  = $name;

                if (strpos($name, '|') !== false) {
                    $parts = explode('|', $name, 2);
                    $fd_modul = trim($parts[0]);
                    $fd_name  = trim($parts[1]);
                }

                if ($fd_modul === '') {
                    $fd_modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
                }

                $dd_file = dbx()->lng_resolve_file(
                    dbx()->get_base_dir() . "dbx/modules/$fd_modul/fd/",
                    $fd_name,
                    'fd.php',
                    $this->_dbx_lng,
                    true
                );
                if ($dd_file === '' || !is_file($dd_file)) {
                    $dd_file = dbx()->get_base_dir() . "dbx/modules/$fd_modul/fd/$fd_name.fd.php";
                }
                break;
        }

        if ($dd_file === '') {
            return '';
        }

        $real_file = realpath(dbx()->os_path($dd_file));

        return $real_file ? $real_file : $dd_file;
    }

    /**
     * Erzeugt FD-/DD-Editor-Marker direkt am Feld-Slot.
     *
     * @return string
     */
    protected function get_field_editor_markers(): string {
        $markers = '';

        foreach (array('fd' => $this->_fd, 'dd' => $this->_dd) as $kind => $source) {
            $file = $this->resolve_fd_dd_editor_file($source);

            if ($file === '') {
                continue;
            }

            $marker = dbx()->editor_marker($kind, $file);

            if ($marker !== '' && strpos($markers, trim($marker)) === false) {
                $markers .= $marker;
            }
        }

        return $markers;
    }

    /**
     * Öffentliche Init-Methode.
     *
     * @param string $fid Formular-ID
     * @param string $tpl Optionales Template
     *
     * @return void
     */
    public function init($fid, $tpl = '') {
        $this->forward_init($fid, $tpl);
    }

    /**
     * Öffentliche Run-Methode.
     *
     * @return string
     */
    public function run() {
        return $this->forward_run();
    }

    /* =====================================================
     * WORKFLOW / REMEMBER STATE
     * ===================================================== */

    /**
     * Setzt einen Workflow-Scope.
     *
     * Zweck
     * -----
     * Ein Scope gruppiert mehrere Formulare logisch innerhalb desselben
     * Workflows.
     *
     * Beispiel
     * --------
     * ```php
     * $oForm->set_workflow_scope('order-setup');
     * ```
     *
     * @param string $scope Scope
     *
     * @return void
     */
    public function set_workflow_scope(string $scope) {
        $this->_workflow_scope = trim($scope);
        $this->load_workflow_state();
    }

    /**
     * Gibt den Workflow-Scope zurück.
     *
     * @return string
     */
    public function get_workflow_scope(): string {
        return $this->_workflow_scope;
    }

    /**
     * Normalisiert Tokens für Remember-Schlüssel.
     *
     * @param string $token Token
     *
     * @return string
     */
    private function normalize_state_token(string $token): string {
        $token = trim($token);

        if ($token === '') {
            return 'undef';
        }

        return preg_replace('/[^a-zA-Z0-9._-]+/', '_', $token);
    }

    /**
     * Baut den Remember-Schlüssel für den Form-Workflow-State.
     *
     * Schlüsselaufbau
     * ---------------
     * - Modul
     * - optionaler Workflow-Scope
     * - Formular-ID
     *
     * Auswirkungen
     * ------------
     * Mehrere Formulare oder Workflows bleiben sauber getrennt.
     *
     * @return string
     */
    public function get_workflow_state_key(): string {
        $modul = $this->normalize_state_token((string) $this->_dbx_modul);
        $scope = $this->normalize_state_token((string) $this->_workflow_scope);
        $fid   = $this->normalize_state_token((string) $this->_fid);

        return 'dbx.form.state.' . $modul . '.' . $scope . '.' . $fid;
    }

    /**
     * Gibt den Standard-Workflow-State zurück.
     *
     * @return array
     */
    private function get_default_workflow_state(): array {
        return array(
            'rid'             => 0,
            'draft_id'        => '',
            'is_complete'     => 0,
            'is_valid'        => 0,
            'is_locked'       => 0,
            'is_saved'        => 0,
            'version'         => 0,
            'depends_on'      => array(),
            'depends_version' => array(),
        );
    }

    /**
     * Lädt den Remember-basierten Workflow-State.
     *
     * @return array
     */
    public function load_workflow_state(): array {
        $key   = $this->get_workflow_state_key();
        $state = dbx()->get_remember_var($key, $this->get_default_workflow_state(), 'dbx');

        if (!is_array($state)) {
            $state = $this->get_default_workflow_state();
        }

        $this->_workflow_state = array_merge($this->get_default_workflow_state(), $state);

        if ((int) ($this->_workflow_state['rid'] ?? 0) > 0 && !$this->_rid) {
            $this->_rid = (int) $this->_workflow_state['rid'];
        }

        return $this->_workflow_state;
    }

    /**
     * Speichert den Workflow-State.
     *
     * @return void
     */
    public function store_workflow_state() {
        $key = $this->get_workflow_state_key();
        dbx()->set_remember_var($key, $this->_workflow_state, 'dbx');
    }

    /**
     * Löscht den Workflow-State.
     *
     * @return void
     */
    public function clear_workflow_state() {
        $this->_workflow_state = $this->get_default_workflow_state();
        $this->store_workflow_state();
    }

    /**
     * Setzt einen Wert im Workflow-State.
     *
     * @param string $key Schlüssel
     * @param mixed  $value Wert
     *
     * @return void
     */
    public function set_state_value(string $key, $value) {
        $this->_workflow_state[$key] = $value;
        $this->store_workflow_state();
    }

    /**
     * Liest einen Wert aus dem Workflow-State.
     *
     * @param string $key Schlüssel
     * @param mixed  $default Default
     *
     * @return mixed
     */
    public function get_state_value(string $key, $default = null) {
        if (!array_key_exists($key, $this->_workflow_state)) {
            $this->load_workflow_state();
        }

        return $this->_workflow_state[$key] ?? $default;
    }

    /**
     * Gibt die Draft-ID zurück, optional mit automatischer Erzeugung.
     *
     * Beispiel
     * --------
     * ```php
     * $draftId = $oForm->get_draft_id(true);
     * ```
     *
     * @param bool $createIfMissing 1 = erzeugen falls leer
     *
     * @return string
     */
    public function get_draft_id(bool $createIfMissing = false): string {
        $draftId = (string) $this->get_state_value('draft_id', '');

        if ($draftId === '' && $createIfMissing) {
            $draftId = $this->create_draft_id();
            $this->set_state_value('draft_id', $draftId);
        }

        return $draftId;
    }

    /**
     * Setzt die Draft-ID explizit.
     *
     * @param string $draftId Draft-ID
     *
     * @return void
     */
    public function set_draft_id(string $draftId) {
        $this->set_state_value('draft_id', $draftId);
    }

    /**
     * Erzeugt eine neue Draft-ID.
     *
     * @return string
     */
    private function create_draft_id(): string {
        return 'dft_' . md5($this->_dbx_modul . '|' . $this->_fid . '|' . microtime(true) . '|' . mt_rand());
    }

    /**
     * Setzt den Validitätsstatus des Formulars.
     *
     * @param bool $state true/false
     *
     * @return void
     */
    public function set_form_valid(bool $state) {
        $this->set_state_value('is_valid', $state ? 1 : 0);
    }

    /**
     * Gibt zurück, ob das Formular als gültig markiert ist.
     *
     * @return bool
     */
    public function is_form_valid(): bool {
        return ((int) $this->get_state_value('is_valid', 0) === 1);
    }

    /**
     * Setzt den Vollständigkeitsstatus des Formulars.
     *
     * @param bool $state true/false
     *
     * @return void
     */
    public function set_form_complete(bool $state) {
        $this->set_state_value('is_complete', $state ? 1 : 0);
    }

    /**
     * Gibt zurück, ob das Formular als vollständig markiert ist.
     *
     * @return bool
     */
    public function is_form_complete(): bool {
        return ((int) $this->get_state_value('is_complete', 0) === 1);
    }

    /**
     * Setzt den Lock-Status des Formulars.
     *
     * @param bool $state true = gesperrt
     *
     * @return void
     */
    public function set_form_locked(bool $state) {
        $this->set_state_value('is_locked', $state ? 1 : 0);
    }

    /**
     * Gibt zurück, ob das Formular gesperrt ist.
     *
     * @return bool
     */
    public function is_form_locked(): bool {
        return ((int) $this->get_state_value('is_locked', 0) === 1);
    }

    /**
     * Setzt den Save-Status des Formulars.
     *
     * @param bool $state true/false
     *
     * @return void
     */
    public function set_form_saved(bool $state) {
        $this->set_state_value('is_saved', $state ? 1 : 0);
    }

    /**
     * Gibt zurück, ob das Formular bereits gespeichert wurde.
     *
     * @return bool
     */
    public function is_form_saved(): bool {
        return ((int) $this->get_state_value('is_saved', 0) === 1);
    }

    /**
     * Gibt die aktuelle Formularversion zurück.
     *
     * @return int
     */
    public function get_form_version(): int {
        return (int) $this->get_state_value('version', 0);
    }

    /**
     * Erhöht die Formularversion um 1.
     *
     * Zweck
     * -----
     * Nützlich für abhängige Formulare: Änderungen an Form A können dadurch
     * Form B/C als veraltet erkennen lassen.
     *
     * @return int Neue Version
     */
    public function bump_form_version(): int {
        $version = $this->get_form_version() + 1;
        $this->set_state_value('version', $version);
        return $version;
    }

    /**
     * Setzt die Abhängigkeiten dieses Formulars.
     *
     * Beispiel
     * --------
     * ```php
     * $oForm->set_dependencies(['form-step-1', 'form-step-2']);
     * ```
     *
     * @param array $formIds Abhängige Form-IDs
     *
     * @return void
     */
    public function set_dependencies(array $formIds) {
        $deps = array();

        foreach ($formIds as $formId) {
            $deps[] = $this->normalize_state_token((string) $formId);
        }

        $this->set_state_value('depends_on', $deps);
    }

    /**
     * Speichert die aktuell gültigen Versionsstände abhängiger Formulare.
     *
     * Zweck
     * -----
     * Nach erfolgreicher Prüfung/Speicherung eines abhängigen Formulars kann
     * damit festgehalten werden, auf welcher Version seiner Vorgänger es basiert.
     *
     * @param array|null $formIds Optional explizite Form-IDs
     *
     * @return void
     */
    public function remember_current_dependencies(?array $formIds = null) {
        if ($formIds === null) {
            $formIds = $this->get_state_value('depends_on', array());
        }

        $versions = array();

        foreach ($formIds as $formId) {
            $state = $this->get_external_form_state((string) $formId);
            $versions[$formId] = (int) ($state['version'] ?? 0);
        }

        $this->set_state_value('depends_version', $versions);
    }

    /**
     * Prüft, ob die gespeicherten Dependency-Versionen noch aktuell sind.
     *
     * Wirkung
     * -------
     * Wenn ein abhängiges Vorformular inzwischen eine andere Version hat, wird
     * `false` geliefert. Das ist der saubere Ersatz für starre Prozessstep-Logik.
     *
     * @return bool
     */
    public function dependencies_are_current(): bool {
        $dependsOn      = $this->get_state_value('depends_on', array());
        $dependsVersion = $this->get_state_value('depends_version', array());

        if (!is_array($dependsOn) || !$dependsOn) {
            return true;
        }

        foreach ($dependsOn as $formId) {
            $state = $this->get_external_form_state((string) $formId);
            $currentVersion = (int) ($state['version'] ?? 0);
            $savedVersion   = (int) ($dependsVersion[$formId] ?? -1);

            if ($currentVersion !== $savedVersion) {
                return false;
            }

            if ((int) ($state['is_valid'] ?? 0) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Liest den Workflow-State eines anderen Formulars im selben Scope.
     *
     * @param string $fid Formular-ID
     *
     * @return array
     */
    public function get_external_form_state(string $fid): array {
        $modul = $this->normalize_state_token((string) $this->_dbx_modul);
        $scope = $this->normalize_state_token((string) $this->_workflow_scope);
        $fid   = $this->normalize_state_token($fid);

        $key = 'dbx.form.state.' . $modul . '.' . $scope . '.' . $fid;
        $state = dbx()->get_remember_var($key, $this->get_default_workflow_state(), 'dbx');

        return is_array($state) ? $state : $this->get_default_workflow_state();
    }

    /**
     * Markiert das Formular aufgrund veralteter Abhängigkeiten als gesperrt.
     *
     * @return bool true = gesperrt, false = frei
     */
    public function refresh_dependency_lock(): bool {
        $locked = !$this->dependencies_are_current();
        $this->set_form_locked($locked);
        return $locked;
    }
}

?>
