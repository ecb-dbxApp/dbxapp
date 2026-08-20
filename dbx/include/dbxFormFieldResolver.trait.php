<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxFormFieldResolverTrait
{
    /** Extrahiert den Feldnamen aus einem bereits gerenderten HTML-Feld. */
    public function get_fld_id($field): string {
        $start = strpos((string)$field, ' name="');
        if ($start === false) return '';
        $start += strlen(' name="');
        $end = strpos((string)$field, '"', $start);
        return $end === false ? '' : substr((string)$field, $start, $end - $start);
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
            $fields = $this->o_db->get_dd_fields($name);
            if (method_exists($this->o_db, 'get_dd_file')) {
                $this->add_editor_file('dd', $this->o_db->get_dd_file($name));
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
            $sql_source = array_map('trim', explode(',', $xkey));
            $dd        = $this->normalize_sql_dd_name($dd, $sql_source[0] ?? '');
            $xkey      = $sql_source[1] ?? 'id';
            $flds      = $sql_source[2] ?? $xkey;
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

            $data = $this->o_db->select($dd, $where, $xflds, $order, $asc_desc, '', $limit, 0, 0);

            if (is_array($data)) {
                $display_fields = ($flds === '*') ? array() : array_map('trim', explode(',', $flds));

                foreach ($data as $record) {
                    $value = '';

                    foreach ($record as $fld => $val) {
                        $use = ($flds === '*') || in_array($fld, $display_fields, true);

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
        $data = dbx()->get_system_obj('dbxSearchDefaults')->build($data);
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
        $defaults = dbx()->get_system_obj('dbxSearchDefaults')->build();

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
     * Fuehrt die semantischen Standard-Feldtypen auf wenige einheitliche
     * Controls zurueck. DD und FD benennen weiterhin die Bedeutung
     * (text-label, integer-label, ...), waehrend Markup und Verhalten nur noch
     * an einer Stelle gepflegt werden.
     */
    private function normalize_standard_field_tpl($tpl, $name, array &$data, string $tooltip_token, int $i): string {
        $raw = trim((string)$tpl);
        $parts = explode('|', $raw, 2);
        if (count($parts) === 2 && strtolower($parts[0]) !== 'dbx') {
            return $raw;
        }

        $semantic = strtolower(count($parts) === 2 ? $parts[1] : $parts[0]);
        $input_types = array(
            'text-label'     => 'text',
            'text'           => 'text',
            'integer-label'  => 'number',
            'date-label'     => 'date',
            'datetime-label' => 'datetime-local',
            'password-label' => 'password',
        );
        $select_types = array(
            'select-single-label'    => false,
            'select-single'          => false,
            'select-multiple-label'  => true,
            'multi-select-label'     => true,
            'multi-select'           => true,
            'multiselect2'           => true,
        );
        $textarea_types = array('textarea-label', 'textarea', 'textarea-tpl');
        $labelled = str_ends_with($semantic, '-label') || $semantic === 'multiselect2';

        if (!isset($input_types[$semantic])
            && !array_key_exists($semantic, $select_types)
            && !in_array($semantic, $textarea_types, true)
            && $semantic !== 'checkbox-label'
        ) {
            return $raw;
        }

        $label = trim((string)($data['label'] ?? ''));
        $data['field_label'] = '';
        $data['field_hint'] = $labelled ? '' : $tooltip_token;
        if ($labelled && $label !== '') {
            $label_class = $semantic === 'checkbox-label'
                ? 'form-check-label control-label'
                : 'form-label control-label';
            $data['field_label'] = '<label for="' . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8')
                . '_' . $i . '" class="' . $label_class . '" data-dbx-errormsg="'
                . (string)($data['errormsg'] ?? '') . '">'
                . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . $tooltip_token . '</label>';
        }

        $data['field_wrap_class'] = trim((string)($data['field_wrap_class'] ?? ''));
        $data['field_input_name'] = (string)$name;
        $data['field_control_attrs'] = trim((string)($data['field_control_attrs'] ?? ''));

        if (isset($input_types[$semantic])) {
            $data['input_type'] = $input_types[$semantic];
            $data['input_min'] = $semantic === 'integer-label' ? ' min="0" step="1"' : '';
            $data['input_autocomplete'] = $semantic === 'password-label' ? ' autocomplete="current-password"' : '';
            return 'dbx|field-input-default';
        }

        if (array_key_exists($semantic, $select_types)) {
            $multiple = $select_types[$semantic];
            $data['field_input_name'] = (string)$name . ($multiple ? '[]' : '');
            $data['select_multiple'] = $multiple ? ' multiple="multiple"' : '';
            $size = max(0, (int)($data['size'] ?? 0));
            $data['select_size'] = $multiple && $size > 0 ? ' size="' . $size . '"' : '';
            if ($semantic === 'multiselect2') {
                $data['class'] = trim('dbxMultiSelect2 ' . (string)($data['class'] ?? ''));
            }
            return 'dbx|field-select-default';
        }

        if (in_array($semantic, $textarea_types, true)) {
            $rows = max(1, (int)($data['rows'] ?? ($semantic === 'textarea' ? 22 : 5)));
            $data['textarea_rows'] = (string)$rows;
            if ($semantic === 'textarea') {
                $data['class'] = trim('tinymce ' . (string)($data['class'] ?? ''));
            } elseif ($semantic === 'textarea-tpl') {
                $data['class'] = trim('dbx-template-editor ' . (string)($data['class'] ?? ''));
            }
            return 'dbx|field-textarea-default';
        }

        $data['field_input_name'] = (string)$name;
        return 'dbx|field-checkbox-default';
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
            $form_key = $this->_fid !== '' ? $this->_fid : 'form';
            $data['ui_persist_attr'] = ' data-dbx="lib=formUiPersist|form='
                . htmlspecialchars($form_key, ENT_QUOTES, 'UTF-8')
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

        foreach (array('placeholder') as $html_key) {
            if (isset($data[$html_key]) && !is_array($data[$html_key])) {
                $data[$html_key] = htmlspecialchars((string)$data[$html_key], ENT_QUOTES, 'UTF-8');
            }
        }

        foreach (array('errormsg') as $html_attribute_key) {
            if (isset($data[$html_attribute_key]) && !is_array($data[$html_attribute_key])) {
                $data[$html_attribute_key] = htmlspecialchars((string)$data[$html_attribute_key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        // Das Feld-Template erhaelt nur einen neutralen Platzhalter. So kann
        // das Fragezeichen zentral erzeugt werden, ohne dass dbxTPL das
        // vertrauenswuerdige Marker-HTML erneut escaped.
        $tooltip_marker = $this->render_field_tooltip_marker($data['tooltip']);
        $tooltip_token = '__DBX_FORM_FIELD_TOOLTIP_' . (int)$i . '__';
        $data['tooltip'] = $tooltip_token;

        $tpl = $this->normalize_standard_field_tpl($tpl, $name, $data, $tooltip_token, (int)$i);

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
        $tpl = str_replace($tooltip_token, $tooltip_marker, $tpl);

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

        $tpl = $this->o_tpl->replaces($tpl, $this->_replaces);

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
}
