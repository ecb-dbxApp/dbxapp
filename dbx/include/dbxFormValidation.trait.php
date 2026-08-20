<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxFormValidationTrait
{
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
    private function resolve_fld_val($name, $default = '', $rules = '', $use_request = true, $fallback_to_state = true) {
        if ($this->value_resolver === null) {
            require_once __DIR__ . '/dbxFormValueResolver.class.php';
            $this->value_resolver = new dbxFormValueResolver($this->o_validator);
        }
        $resolved = $this->value_resolver->resolve(
            (string)$name,
            $default,
            (string)$rules,
            is_array($this->_data) ? $this->_data : array(),
            is_array($this->_sys) ? $this->_sys : array(),
            is_array($_POST ?? null) ? $_POST : array(),
            is_array($_GET ?? null) ? $_GET : array(),
            (bool)$use_request,
            (bool)$fallback_to_state
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
            $validation = $this->o_validator->validate_result($danger_value, $rules, $name);
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
            $validation = $this->o_validator->validate_result($danger_value, $rules, $name);
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
        $has_post = 0;
        $match = 0;

        if ($fld !== '' && isset($_POST[$fld])) {
            $posted = (string)$_POST[$fld];
            $has_post = 1;

            if ($secure !== '' && hash_equals($secure, $posted)) {
                $submit = 1;
                $match = 1;
            }
        }

        dbx()->debug(
            "dbxForm submit evaluate: fid=({$this->_fid}) field=($fld) post=($has_post) match=($match) submit=($submit)"
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
        $standard_templates = array(
            '#form_msg_success#' => 'form-message-save-success',
            '#form_msg_save_success#' => 'form-message-save-success',
            '#form_msg_error#' => 'form-message-validation-error',
            '#form_msg_save_error#' => 'form-message-save-error',
            '#form_msg_warning#' => 'form-message-warning',
        );

        if (isset($standard_templates[$msg])) {
            return $this->get_tpl('dbx|' . $standard_templates[$msg]);
        }

        if (!$msg || $msg === '#form_msg_info#') {
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
     * Rendert eine sprachabhängige Standardmeldung aus dem Modul dbx.
     * Fachliche Meldungen bleiben bewusst im jeweiligen Modul.
     */
    public function get_standard_form_message(string $name, array $data = array()): string {
        $templates = array(
            'save-success' => 'dbx|form-message-save-success',
            'save-error' => 'dbx|form-message-save-error',
            'validation-error' => 'dbx|form-message-validation-error',
            'warning' => 'dbx|form-message-warning',
            'delete-success' => 'dbx|form-message-delete-success',
            'delete-error' => 'dbx|form-message-delete-error',
        );
        if (!isset($templates[$name])) {
            return '';
        }

        return $this->get_tpl($templates[$name], $data);
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
}
