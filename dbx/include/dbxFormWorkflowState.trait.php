<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxFormWorkflowStateTrait
{
    /** Liefert die aktuelle Zeit; in fokussierten Tests ueberschreibbar. */
    protected function current_time(): float {
        return microtime(true);
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
    public function get_draft_id(bool $create_if_missing = false): string {
        $draft_id = (string) $this->get_state_value('draft_id', '');

        if ($draft_id === '' && $create_if_missing) {
            $draft_id = $this->create_draft_id();
            $this->set_state_value('draft_id', $draft_id);
        }

        return $draft_id;
    }

/**
     * Setzt die Draft-ID explizit.
     *
     * @param string $draftId Draft-ID
     *
     * @return void
     */
    public function set_draft_id(string $draft_id) {
        $this->set_state_value('draft_id', $draft_id);
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
    public function set_dependencies(array $form_ids) {
        $deps = array();

        foreach ($form_ids as $form_id) {
            $deps[] = $this->normalize_state_token((string) $form_id);
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
    public function remember_current_dependencies(?array $form_ids = null) {
        if ($form_ids === null) {
            $form_ids = $this->get_state_value('depends_on', array());
        }

        $versions = array();

        foreach ($form_ids as $form_id) {
            $state = $this->get_external_form_state((string) $form_id);
            $versions[$form_id] = (int) ($state['version'] ?? 0);
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
        $depends_on      = $this->get_state_value('depends_on', array());
        $depends_version = $this->get_state_value('depends_version', array());

        if (!is_array($depends_on) || !$depends_on) {
            return true;
        }

        foreach ($depends_on as $form_id) {
            $state = $this->get_external_form_state((string) $form_id);
            $current_version = (int) ($state['version'] ?? 0);
            $saved_version   = (int) ($depends_version[$form_id] ?? -1);

            if ($current_version !== $saved_version) {
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
