<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxReportSelectionTrait
{
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
    public function get_visible_multi_select_state(array $visible_ids = array()) {
        if (!$visible_ids) {
            $visible_ids = $this->get_visible_multi_select_ids();
        }

        $selects         = $this->get_multi_selects();
        $selected_visible = array();

        foreach ($visible_ids as $rid) {
            $key = $this->normalize_multi_select_key($rid);

            if ($key !== '' && isset($selects[$key])) {
                $selected_visible[] = $key;
            }
        }

        $visible_count  = count($visible_ids);
        $selected_count = count($selected_visible);
        $header_state   = 'none';

        if ($visible_count > 0 && $selected_count === $visible_count) {
            $header_state = 'all';
        } elseif ($selected_count > 0) {
            $header_state = 'partial';
        }

        return array(
            'visible_ids'          => array_values($visible_ids),
            'selected_ids_visible' => array_values($selected_visible),
            'visible_count'        => $visible_count,
            'selected_count'       => $selected_count,
            'header_state'         => $header_state,
            'header_checked'       => ($header_state === 'all') ? 1 : 0,
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
    protected function send_multi_select_json_response(array $visible_ids = array(), $dbx_do = '') {
        $quick = dbx()->get_modul_var('dbx_select_quick', 1, 'int');

        if ($quick) {
            http_response_code(204);
            if (function_exists('session_write_close')) {
                session_write_close();
            }
            exit;
        }

        $state = $this->get_visible_multi_select_state($visible_ids);

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

        $select_where = $fld_id . ' IN (' . implode(',', $values) . ')';

        if ($rwhere > '') {
            $rwhere .= ' and (' . $select_where . ')';
        } else {
            $rwhere = $select_where;
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
    public function add_rwhere_search($sql, $such_wert, $feld_liste) {
        if ($such_wert === null) {
            $such_wert = '';
        }

        $such_wert = str_replace(array('\'', '"', '\\', '%'), '', $such_wert);
        $such_wert = filter_var($such_wert, FILTER_SANITIZE_SPECIAL_CHARS);

        $datum = DateTime::createFromFormat('d.m.Y', $such_wert);

        if ($datum && $datum->format('d.m.Y') === $such_wert) {
            $such_wert = $datum->format('Y-m-d');
        }

        $felder      = explode(',', $feld_liste);
        $sql        .= ' AND (';
        $bedingungen = array();

        foreach ($felder as $feld) {
            $feld = trim($feld);

            if ($feld === '') {
                continue;
            }

            $bedingungen[] = "$feld LIKE '$such_wert%'";
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
            $ids_raw     = dbx()->get_modul_var('dbx_select_ids', '', '*');
            $visible_raw = dbx()->get_modul_var('dbx_select_visible_ids', '', '*');
            $selected   = $this->parse_multi_select_ids($ids_raw);
            $visible_ids = $this->parse_multi_select_ids($visible_raw);

            if (!$visible_ids) {
                $visible_ids = $selected;
            }

            $selected_map = array_flip($selected);

            foreach ($visible_ids as $rid) {
                if (isset($selected_map[$rid])) {
                    $this->set_multi_select($rid);
                } else {
                    $this->del_multi_select($rid);
                }
            }

            if ($ajax) {
                $this->send_multi_select_json_response($visible_ids, $dbx_do);
            }

            return 'handled';
        }

        if ($dbx_do === 'row_select') {
            $state      = dbx()->get_modul_var('dbx_select_state', 0, 'int');
            $rid        = dbx()->get_modul_var('rid', '', 'parameter+.');
            $select_id   = dbx()->get_modul_var('dbx_select_id', '', 'parameter+.');
            $visible_ids = dbx()->get_modul_var('dbx_select_visible_ids', '', '*');

            if ($select_id !== '') {
                $rid = $select_id;
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
                    $this->parse_multi_select_ids($visible_ids),
                    $dbx_do
                );
            }

            return 'handled';
        }

        if ($dbx_do === 'rows_select') {
            $state      = dbx()->get_modul_var('dbx_select_state', 0, 'int');
            $ids_raw     = dbx()->get_modul_var('dbx_select_ids', '', '*');
            $visible_raw = dbx()->get_modul_var('dbx_select_visible_ids', '', '*');
            $ids        = $this->parse_multi_select_ids($ids_raw);
            $visible_ids = $this->parse_multi_select_ids($visible_raw);

            if (!$visible_ids) {
                $visible_ids = $ids;
            }

            if ($state) {
                $this->set_multi_select_ids($ids);
            } else {
                $this->del_multi_select_ids($ids);
            }

            if ($ajax) {
                $this->send_multi_select_json_response($visible_ids, $dbx_do);
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
}
