<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDBCrudTrait
{
/**
     * Führt eine SELECT-Abfrage auf der angegebenen DD aus.
     *
     * Ablauf
     * ------
     * - DD laden
     * - Tabelle/Server/Typ ermitteln
     * - Zugriff prüfen
     * - WHERE normalisieren
     * - Feldliste gegen DD validieren
     * - SQL generieren
     * - Ergebnis lesen
     *
     * @param string       $dd Datenbank-Definition
     * @param string       $where WHERE-Bedingung
     * @param string|array $columns Spaltenliste oder `*`
     * @param string       $orderby ORDER BY-Feld/Clause
     * @param string       $asc_desc Sortierreihenfolge
     * @param string       $groupby GROUP BY-Clause
     * @param int          $max Maximale Anzahl Datensätze
     * @param int          $offset Offset
     * @param int          $verify_access Zugriff prüfen
     *
     * Beispiel:
     * ```php
     * $rows = $db->select('dbxUser', "active=1", ['id', 'name'], 'name');
     * if (!$rows && $db->get_error_status() !== '') {
     *     // Zugriff verweigert oder DB-Fehler statt "keine Treffer" -
     *     // Grund steht in get_error_status()/get_error_text().
     * }
     * ```
     *
     * @return array Immer ein Array - leer bei "keine Treffer" ebenso wie bei
     *     Zugriffsfehler oder DB-Fehler. Der Grund fuer ein leeres Ergebnis
     *     steht danach in get_error_status() ('access'|'sql'|'db'|'').
     */
    public function select(string $dd = '', $where = '', $columns = '*', $orderby = '', $asc_desc = 'ASC', $groupby = '', $max = 0, $offset = 0, $verify_access = 1): array {
        $this->clear_db_error();

        $owner      = 0;
        $access     = 1;
        $fields     = '';

        $dbtab  = $this->get_dd_table($dd);
        $server = $this->get_dd_server($dd);
        $db_type = $this->get_db_type($server);

        if ($verify_access) {
            $access = $this->check_access('select', $dd);

            if ($access == 0) {
                $this->report_db_error('access', (string)$dd, 'Zugriff verweigert', 'select', 'access-select|' . $dd);
                return array();
            }

            if ($access === 2) {
                $owner = 1;
            }
        }

        $where = $this->normalize_where($dd, $where, $owner);


        if (!is_array($columns)) {
            $columns = strpos($columns, ',') !== false ? explode(',', $columns) : [$columns];
        }

        foreach ($columns as $no => $field) {
            $xfield = is_int($no) ? trim($field) : trim($no);

            if ($xfield === '*') {
                $fields = '*';
                break;
            }

            if ($this->is_fld_name($dd, $xfield)) {
                $fields .= $xfield . ',';
            }
        }

        $fields = $fields === '*' ? '*' : (rtrim($fields, ',') ?: '*');

        $query = "SELECT $fields FROM $dbtab ";

        if ($where) {
            $query .= "WHERE $where ";
        }

        if ($groupby) {
            $query .= "GROUP BY $groupby ";
        }

        if ($orderby) {
            $orderby = trim($orderby);
            $orderby = rtrim($orderby, ';');

            if (stripos($orderby, ' ASC') !== false || stripos($orderby, ' DESC') !== false) {
                $query .= "ORDER BY $orderby ";
            } else {
                $query .= "ORDER BY $orderby $asc_desc ";
            }
        }

        if ($max > 0) {
            if ($db_type === 'mysql') {
                $query .= "LIMIT $offset, $max ";
            } else {
                $query .= "LIMIT $max OFFSET $offset ";
            }
        }

        $result = $this->select_query($server, $query, $dd);

        return is_array($result) ? $result : array();
    }

/**
     * Führt eine SELECT-Abfrage aus und gibt genau einen Datensatz zurück.
     *
     * Gibt bei leerem oder ungültigem Ergebnis einen leeren Standarddatensatz
     * der DD zurück.
     *
     * @param string       $dd Datenbank-Definition
     * @param string       $where WHERE-Bedingung
     * @param string|array $columns Spaltenliste
     * @param int          $verify_access Zugriff prüfen
     *
     * @return array Einzelner Datensatz oder Leerstruktur
     */
    public function select1(string $dd, $where = '', $columns = '*', $verify_access = 1) {
        $verify_access = (int)$verify_access;
        $cache_allowed = $this->select1_cache_allowed($dd);
        $dd_key = '';
        $cache_key = '';

        if ($cache_allowed) {
            $dd_key = $this->select1_cache_dd_key($dd);
            $cache_key = $this->select1_cache_key($dd_key, $where, $columns, $verify_access);
            if (array_key_exists($cache_key, $this->_select1_cache[$dd_key] ?? array())) {
                $this->_select1_cache_stats['hits']++;
                return $this->_select1_cache[$dd_key][$cache_key];
            }
            $this->_select1_cache_stats['misses']++;
        } else {
            $this->_select1_cache_stats['transaction_bypass']++;
        }

        $db_records = $this->select($dd, $where, $columns, '', 'ASC', '', 1, 0, $verify_access);

        if (!is_array($db_records)) {
            return $this->empty_record($dd)[0];
        }

        $record = null;
        if (!isset($db_records[0]) || !is_array($db_records[0])) {
            $record = $this->empty_record($dd)[0];
        } else {
            $record = $db_records[0];
        }

        if ($cache_allowed) {
            if ($this->_select1_cache_entries < self::SELECT1_CACHE_MAX_ENTRIES) {
                $this->_select1_cache[$dd_key][$cache_key] = $record;
                $this->_select1_cache_servers[$dd_key] = $this->get_dd_server($dd);
                $this->_select1_cache_entries++;
                $this->_select1_cache_stats['stores']++;
            } else {
                $this->_select1_cache_stats['capacity_bypass']++;
            }
        }

        return $record;
    }

/**
     * Liefert generische Tree-Daten aus einer Parent-Tabelle und optionalen Items.
     *
     * Die Methode ist bewusst klein konfigurierbar, damit Module nur DD-Namen
     * und abweichende Feldnamen uebergeben muessen. Typischer CMS-Fall:
     * Ordner aus `dbxContentFolder`, Seiten aus `dbxContent`.
     *
     * @param string $folder_dd DD fuer die Baum-/Ordner-Tabelle
     * @param string $item_dd Optional DD fuer Kind-Items/Seiten
     * @param array $opt Feld- und Filteroptionen
     *
     * @return array{nodes:array,flat:array,folders:array,items:array}
     */
    public function select_tree(string $folder_dd, string $item_dd = '', array $opt = []): array {
        $tree_def = $this->get_dd_table_def($folder_dd);
        if (!is_array($tree_def)) $tree_def = [];

        if ($item_dd === '' && !empty($tree_def['tree_items_dd'])) {
            $item_dd = (string)$tree_def['tree_items_dd'];
        }

        $folder_id     = (string)($opt['folder_id'] ?? $tree_def['tree_id'] ?? 'id');
        $folder_parent = (string)($opt['folder_parent'] ?? $tree_def['tree_parent'] ?? 'parent_id');
        $folder_title  = (string)($opt['folder_title'] ?? $tree_def['tree_label'] ?? 'name');
        $folder_rights = (string)($opt['folder_rights'] ?? $tree_def['tree_rights'] ?? 'group_read');
        $folder_order  = (string)($opt['folder_order'] ?? $tree_def['tree_order'] ?? $folder_title);
        $folder_where  = (string)($opt['folder_where'] ?? '');

        $item_id       = (string)($opt['item_id'] ?? $tree_def['tree_items_id'] ?? 'id');
        $item_parent   = (string)($opt['item_parent'] ?? $tree_def['tree_items_parent'] ?? 'folder');
        $item_title    = (string)($opt['item_title'] ?? $tree_def['tree_items_label'] ?? 'title');
        $item_rights   = (string)($opt['item_rights'] ?? $tree_def['tree_items_rights'] ?? 'group_read');
        $item_order    = (string)($opt['item_order'] ?? $tree_def['tree_items_order'] ?? $item_title);
        $item_where    = (string)($opt['item_where'] ?? '');

        $root          = (int)($opt['root'] ?? 0);
        $verify_access = (int)($opt['verify_access'] ?? 1);

        $folder_cols = $this->tree_columns($folder_dd, [
            $folder_id,
            $folder_parent,
            $folder_title,
            $folder_rights,
            'template',
            'module',
            'sorter',
            'active',
            'activ'
        ]);

        $folders = $this->select($folder_dd, $folder_where, $folder_cols, $folder_order, 'ASC', '', 0, 0, $verify_access);
        if (!is_array($folders)) {
            $folders = [];
        }

        $item_cols = [];
        $items = [];

        if ($item_dd !== '') {
            $item_cols = $this->tree_columns($item_dd, [
                $item_id,
                $item_parent,
                $item_title,
                $item_rights,
                'permalink',
                'template',
                'description',
                'sorter',
                'active',
                'activ',
                'hits',
                'update_date'
            ]);

            $items = $this->select($item_dd, $item_where, $item_cols, $item_order, 'ASC', '', 0, 0, $verify_access);
            if (!is_array($items)) {
                $items = [];
            }
        }

        $nodes = [];
        $by_parent = [];
        $flat = [];

        foreach ($folders as $row) {
            if (!is_array($row)) continue;

            $id = (int)($row[$folder_id] ?? 0);
            if ($id <= 0) continue;

            $parent = (int)($row[$folder_parent] ?? 0);
            if ($parent === $id) {
                $parent = 0;
            }

            $node = $row;
            $node['_node_id'] = 'folder-' . $id;
            $node['_type'] = 'folder';
            $node['_id'] = $id;
            $node['_parent'] = $parent;
            $node['_title'] = (string)($row[$folder_title] ?? ('Ordner ' . $id));
            $node['_rights'] = (string)($row[$folder_rights] ?? '');
            $node['_children'] = [];

            $by_parent[$parent][] = $node;
        }

        foreach ($items as $row) {
            if (!is_array($row)) continue;

            $id = (int)($row[$item_id] ?? 0);
            if ($id <= 0) continue;

            $parent = (int)($row[$item_parent] ?? 0);

            $node = $row;
            $node['_node_id'] = 'page-' . $id;
            $node['_type'] = 'page';
            $node['_id'] = $id;
            $node['_parent'] = $parent;
            $node['_title'] = (string)($row[$item_title] ?? ('Seite ' . $id));
            $node['_rights'] = (string)($row[$item_rights] ?? '');
            $node['_children'] = [];

            $by_parent[$parent][] = $node;
        }

        $build = function ($parent, $level) use (&$build, &$by_parent, &$flat) {
            $children = $by_parent[$parent] ?? [];
            $out = [];

            foreach ($children as $node) {
                $node['_level'] = $level;

                if (($node['_type'] ?? '') === 'folder') {
                    $node['_children'] = $build((int)$node['_id'], $level + 1);
                }

                $flat[] = $node;
                $out[] = $node;
            }

            return $out;
        };

        $nodes = $build($root, 0);

        return [
            'nodes' => $nodes,
            'flat' => $flat,
            'folders' => array_values($folders),
            'items' => array_values($items),
        ];
    }

private function tree_columns(string $dd, array $columns): array {
        $out = [];

        foreach ($columns as $field) {
            $field = trim((string)$field);
            if ($field === '' || isset($out[$field])) continue;
            if ($this->is_fld_name($dd, $field)) {
                $out[$field] = $field;
            }
        }

        return array_values($out);
    }

/**
     * Führt eine UPDATE-Abfrage aus und gibt die Anzahl
     * der betroffenen Zeilen zurück.
     *
     * @param string $server Datenbankserver
     * @param string $sql SQL-Update-Statement
     *
     * @return int Anzahl der betroffenen Zeilen oder -2
     */
    public function update_query($server, $sql) {
        $this->_update_count = 0;
        $retval = -2;

        $timers = $this->db_timers_start('save');

        try {
            $stmt = $this->query($server, $sql);
        } finally {
            $this->db_timers_stop($timers);
        }

        if ($stmt) {
            $retval = $stmt->rowCount();
        }

        $this->_update_count = $retval;

        return $retval;
    }

/**
     * Zählt Datensätze einer DD oder Tabelle.
     *
     * - Bei DD-Aufruf werden Tabelle und Server aus der DD ermittelt.
     * - Bei explizitem `$server` wird `$dd` als Tabellenname behandelt.
     * - `where = 'new'` liefert direkt `0`.
     *
     * Der DB-Zugriff läuft über den zentralen Query-Pfad,
     * damit Fehlerbehandlung und Retry-Verhalten konsistent bleiben.
     *
     * @param string $dd DD-Name oder Tabellenname
     * @param string $where WHERE-Bedingung
     * @param string $server Optional expliziter Server
     *
     * @return int Anzahl, -1 oder -2
     */
    public function count($dd, $where = '', $server = '') {
        $count = -2;
        $explicit_server = $server !== '';

        if ($where == 'new') {
            return 0;
        }

        if (!$server) {
            $dbtab = $this->get_dd_table($dd);
        }

        if ($server) {
            $dbtab = $dd;
        }

        if (!$server) {
            $server = $this->get_dd_server($dd);
        }

        if (!$dbtab || !$server) {
            return $count;
        }

        if (!$explicit_server) {
            $access = $this->check_access('select', (string)$dd);
            if ($access == 0) {
                $this->report_db_error('access', (string)$dd, 'Zugriff verweigert', 'count', 'access-count|' . $dd);
                return -1;
            }

            $where = $this->normalize_where($dd, $where, $access === 2 ? 1 : 0);
        } else {
            $where = is_array($where) ? '' : $where;
        }

        $query = "SELECT COUNT(*) AS cnt FROM $dbtab";

        if (!empty($where)) {
            $query .= " WHERE $where";
        }

        $timers = $this->db_timers_start('select', $explicit_server ? '' : (string) $dd);

        try {
            $stmt = $this->query($server, $query);

            if (!is_object($stmt)) {
                if ($this->get_error_status() === '') {
                    $this->report_db_error('sql', (string)$server, 'SQL-Fehler', 'COUNT fehlgeschlagen', 'sql-count|' . $server);
                }
                return -2;
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row) && isset($row['cnt'])) {
                $count = (int) $row['cnt'];
            } else {
                $count = 0;
            }
        } catch (PDOException | Exception $e) {
            $this->report_db_error('sql', (string)$server, 'SQL-Fehler', $e->getMessage(), 'sql-count|' . $server);
            $count = -2;
        } finally {
            $this->db_timers_stop($timers);
        }

        return $count;
    }

/**
     * Leert eine Datenbank-Tabelle anhand ihrer DD und setzt die ID zurueck.
     *
     * Verwendung:
     * Admin-/Wartungsfunktionen koennen damit gezielt Tabellen leeren, wenn die
     * Datenquelle wirklich dbxDB ist.
     *
     * @param string $dd Datenbeschreibung
     *
     * @return int 1 bei Erfolg, 0 bei Fehler
     */
    public function delete_tab($dd) {
        $ok = $this->empty($dd);

        if (!$ok) {
            return 0;
        }

        return $this->optimize_tab($dd);
    }

/**
     * Optimiert eine Tabelle anhand ihrer DD nach Wartungsaktionen.
     *
     * MySQL nutzt OPTIMIZE TABLE. SQLite nutzt VACUUM fuer die ganze
     * Datenbankdatei, weil SQLite keine einzelne Tabellen-Vacuum kennt.
     *
     * @param string $dd Datenbeschreibung
     *
     * @return int 1 bei Erfolg oder wenn keine Optimierung noetig/definiert ist, 0 bei Fehler
     */
    public function optimize_tab($dd) {
        $this->load_dd($dd);
        $server = $this->get_dd_server($dd);
        $dbtab  = $this->get_dd_table($dd);
        $db_type = strtolower((string)$this->get_db_type($server));

        if (!$server || !$dbtab || !$db_type) {
            return 0;
        }

        switch ($db_type) {
            case 'mysql':
                $table = $this->quote_db_identifier_for_type($db_type, $dbtab);
                return (int)$this->exec($server, 'OPTIMIZE TABLE ' . $table);

            case 'sqlite':
                return (int)$this->exec($server, 'VACUUM');

            default:
                dbx()->debug("#optimize tab skipped dd=($dd) Server=($server) Tab=($dbtab) Type=($db_type)");
                return 1;
        }
    }
}
