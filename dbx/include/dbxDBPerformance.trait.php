<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDBPerformanceTrait
{
/**
     * Aktiviert die Query-Messung nur, wenn die zentrale Performance-Erfassung
     * aktiv ist. Die Entscheidung wird pro Request einmal gecacht; das
     * Speichern der Messwerte selbst wird immer ausgeschlossen.
     */
    private function performance_query_capture_enabled(): bool {
        if ((int) dbx()->get_system_var('dbx_performance_timer_store', 0, 'int') === 1) {
            return false;
        }

        if ($this->_performance_query_capture !== null) {
            return $this->_performance_query_capture;
        }

        $level = strtolower(trim((string) dbx()->get_cfg('dbx', 'performance_timer_level')));
        if (in_array($level, array('main', 'detail', 'details'), true)) {
            return $this->_performance_query_capture = true;
        }

        $legacy = dbx()->get_cfg('dbx', 'performance_timer');
        return $this->_performance_query_capture = ((int) $legacy === 1);
    }

/**
     * Normalisiert SQL zu einer stabilen, datenschutzfreundlichen Struktur.
     * Literale und Kommentare werden entfernt; Tabellen- und Feldnamen bleiben
     * fuer die Diagnose erhalten. Parameterwerte werden nie gespeichert.
     */
    private function normalize_performance_sql(string $sql): string {
        $sql = preg_replace('~/\\*.*?\\*/~s', ' ', $sql);
        $sql = preg_replace('/--[^\\r\\n]*/', ' ', (string) $sql);
        $sql = preg_replace("/'(?:''|\\\\.|[^'])*'/s", '?', (string) $sql);
        $sql = preg_replace('/\\b(?:0x[0-9a-f]+|\\d+(?:\\.\\d+)?)\\b/i', '?', (string) $sql);
        $sql = preg_replace('/\\s+/', ' ', (string) $sql);
        return substr(trim((string) $sql), 0, 1200);
    }

private function performance_slow_query_ms(): int {
        if ($this->_performance_slow_query_ms !== null) {
            return $this->_performance_slow_query_ms;
        }

        $value = (int) dbx()->get_cfg('dbx', 'performance_timer_slow_query_ms');
        return $this->_performance_slow_query_ms = ($value > 0 ? $value : 100);
    }

/** Erfasst genau eine logisch ausgefuehrte DB-Anweisung. */
    private function record_performance_query(
        string $server,
        string $sql,
        float $started,
        int $affected_rows = 0,
        bool $success = true
    ): void {
        if (!$this->performance_query_capture_enabled()) {
            return;
        }

        $elapsed_ms = max(0.0, (microtime(true) - $started) * 1000);
        $normalized = $this->normalize_performance_sql($sql);
        if ($normalized === '') {
            return;
        }

        $fingerprint = substr(hash('sha256', strtolower($server) . '|' . strtolower($normalized)), 0, 16);
        $operation = strtoupper((string) strtok(ltrim($normalized), " \t\r\n"));
        if ($operation === '') {
            $operation = 'SQL';
        }

        $slow_ms = $this->performance_slow_query_ms();
        $slow = $elapsed_ms >= $slow_ms;

        if (!isset($this->_performance_queries[$fingerprint])) {
            $this->_performance_queries[$fingerprint] = array(
                'fingerprint'   => $fingerprint,
                'server'        => substr($server, 0, 80),
                'operation'     => substr($operation, 0, 16),
                'sql'           => $normalized,
                'query_count'   => 0,
                'time_ms'       => 0.0,
                'max_time_ms'   => 0.0,
                'affected_rows' => 0,
                'slow_count'    => 0,
                'failure_count' => 0,
            );
        }

        $entry =& $this->_performance_queries[$fingerprint];
        $entry['query_count']++;
        $entry['time_ms'] += $elapsed_ms;
        $entry['max_time_ms'] = max((float) $entry['max_time_ms'], $elapsed_ms);
        $entry['affected_rows'] += max(0, $affected_rows);
        if ($slow) $entry['slow_count']++;
        if (!$success) $entry['failure_count']++;
        unset($entry);

        $this->_performance_query_count++;
        $this->_performance_query_time_ms += $elapsed_ms;
        $this->_performance_query_affected_rows += max(0, $affected_rows);
        if ($slow) $this->_performance_query_slow_count++;
        if (!$success) $this->_performance_query_failed_count++;
    }

/**
     * Liefert die Request-Zusammenfassung fuer dbxPerformanceTimer.
     * Detailzeilen sind nach Gesamtkosten und danach nach Wiederholungen
     * sortiert, damit die relevantesten Optimierungskandidaten zuerst kommen.
     */
    public function performance_query_snapshot(): array {
        $queries = array_values($this->_performance_queries);
        usort($queries, static function (array $a, array $b): int {
            $time = ((float) ($b['time_ms'] ?? 0)) <=> ((float) ($a['time_ms'] ?? 0));
            if ($time !== 0) return $time;
            return ((int) ($b['query_count'] ?? 0)) <=> ((int) ($a['query_count'] ?? 0));
        });

        $duplicates = 0;
        foreach ($queries as $query) {
            $duplicates += max(0, (int) ($query['query_count'] ?? 0) - 1);
        }

        return array(
            'query_count'          => $this->_performance_query_count,
            'unique_query_count'   => count($queries),
            'duplicate_query_count'=> $duplicates,
            'slow_query_count'     => $this->_performance_query_slow_count,
            'failed_query_count'   => $this->_performance_query_failed_count,
            'affected_rows'        => $this->_performance_query_affected_rows,
            'query_time_ms'        => (int) round($this->_performance_query_time_ms),
            'queries'              => $queries,
        );
    }

/**
     * Liefert den kanonischen DD-Schluessel fuer Cache und Invalidierung.
     * Verschiedene gueltige Schreibweisen derselben DD werden dadurch nicht
     * als getrennte Datenquellen behandelt.
     */
    private function select1_cache_dd_key(string $dd): string {
        $definition = $this->load_dd($dd);
        if ((int)($definition['dd_status'] ?? 0) === 1) {
            return strtolower(
                trim((string)($definition['dd_modul'] ?? '')) . '|'
                . trim((string)($definition['dd_name'] ?? ''))
            );
        }

        return strtolower(trim($dd));
    }

/** Erstellt einen stabilen Schluessel fuer exakt einen select1()-Aufruf. */
    private function select1_cache_key(string $dd_key, $where, $columns, int $verify_access): string {
        return hash('sha256', serialize(array(
            $dd_key,
            $where,
            $columns,
            $verify_access,
            (int)dbx()->user(),
        )));
    }

/** Transaktionale Reads umgehen den Cache und sehen immer den PDO-Zustand. */
    private function select1_cache_allowed(string $dd): bool {
        $server = $this->get_dd_server($dd);
        return $server !== '' && empty($this->_tx[$server]);
    }

/** Verwirft alle select1()-Ergebnisse genau einer DD. */
    protected function invalidate_select1_cache(string $dd): void {
        $dd_key = $this->select1_cache_dd_key($dd);
        if (!isset($this->_select1_cache[$dd_key])) {
            return;
        }

        $this->_select1_cache_entries -= count($this->_select1_cache[$dd_key]);
        $this->_select1_cache_entries = max(0, $this->_select1_cache_entries);
        unset($this->_select1_cache[$dd_key]);
        unset($this->_select1_cache_servers[$dd_key]);
        $this->_select1_cache_stats['invalidations']++;
    }

/**
     * Nach Commit/Rollback werden alle DD-Caches des Transaktionsservers
     * verworfen. Das deckt auch absichtliche atomare update_query()-Pfade ab.
     */
    private function invalidate_select1_cache_server(string $server): void {
        foreach ($this->_select1_cache_servers as $dd_key => $cache_server) {
            if ($cache_server !== $server || !isset($this->_select1_cache[$dd_key])) {
                continue;
            }
            $this->_select1_cache_entries -= count($this->_select1_cache[$dd_key]);
            unset($this->_select1_cache[$dd_key], $this->_select1_cache_servers[$dd_key]);
            $this->_select1_cache_stats['invalidations']++;
        }
        $this->_select1_cache_entries = max(0, $this->_select1_cache_entries);
    }

/** Diagnosewerte fuer Tests und Performanceanzeige, ohne Cacheinhalte. */
    public function select1_cache_snapshot(): array {
        return $this->_select1_cache_stats + array(
            'entries' => $this->_select1_cache_entries,
            'dds' => count($this->_select1_cache),
            'capacity' => self::SELECT1_CACHE_MAX_ENTRIES,
        );
    }

/**
     * Erzeugt den Timer-Section-Namen fuer eine DD-spezifische DB-Messung.
     *
     * Beispiel:
     * - `db-select-dbx-dbXUser`
     * - `db-save-dbxContent-content`
     *
     * Lange DD-Namen werden gekuerzt und mit Hash stabil gemacht, damit die
     * Timer-Keys in der Performance-Auswertung lesbar bleiben.
     *
     * @param string $type select|save.
     * @param string $dd DD-Name, optional mit Modulprefix.
     * @return string Timer-Section oder leerer String.
     */
    private function db_timer_dd_section(string $type, string $dd): string {
        $dd = trim($dd);

        if ($dd === '') {
            return '';
        }

        $prefix = $type === 'save' ? 'db-save-' : 'db-select-';
        $safe = preg_replace('/[^A-Za-z0-9]+/', '-', $dd);
        $safe = trim((string) $safe, '-');

        if ($safe === '') {
            return '';
        }

        $max = 80 - strlen($prefix);
        if (strlen($safe) > $max) {
            $hash = substr(md5($dd), 0, 6);
            $safe = rtrim(substr($safe, 0, max(1, $max - 7)), '-') . '-' . $hash;
        }

        return $prefix . $safe;
    }

/**
     * Startet die DB-Performance-Timer fuer eine DB-Operation.
     *
     * Es werden bis zu drei Messpunkte geschrieben:
     * - `db-total`: Gesamtzeit aller DB-Aktionen im Request
     * - `db-select` oder `db-save`: Gesamtzeit je Operationstyp
     * - `db-select-*` oder `db-save-*`: Zeit je DD
     *
     * `save` umfasst insert und update. Verschachtelte Messungen werden
     * unterdrueckt, damit Trace- oder Hilfsqueries die Fachmessung nicht
     * doppelt verschlechtern.
     *
     * Beispiel:
     * ```php
     * $timers = $this->db_timers_start('select', 'dbx|dbxUser');
     * try {
     *    // SELECT ausfuehren
     * } finally {
     *    $this->db_timers_stop($timers);
     * }
     * ```
     *
     * @param string $type select|save.
     * @param string $dd Optionaler DD-Kontext.
     * @return array Gestartete Timer-Sections in Stop-Reihenfolge.
     */
    private function db_timers_start(string $type, string $dd = ''): array {
        if ((int) dbx()->get_system_var('dbx_performance_timer_store', 0, 'int') === 1) {
            return array();
        }

        if ($this->_db_timer_depth > 0) {
            return array();
        }

        $this->_db_timer_depth++;

        $type = $type === 'save' ? 'save' : 'select';
        $base = $type === 'save' ? 'db-save' : 'db-select';
        $sections = array(
            'db-total' => 'db total',
            $base      => $type,
        );

        $dd_section = $this->db_timer_dd_section($type, $dd);
        if ($dd_section !== '') {
            $sections[$dd_section] = $type . ' ' . $dd;
        }

        foreach ($sections as $section => $info) {
            dbx()->timer($section, $info);
        }

        return array_keys($sections);
    }

/**
     * Stoppt zuvor gestartete DB-Performance-Timer.
     *
     * Die Sections werden rueckwaerts beendet, damit DD-, Typ- und
     * Gesamtmessung sauber ineinander liegen.
     *
     * @param array $sections Rueckgabe von db_timers_start().
     * @return void
     */
    private function db_timers_stop(array $sections): void {
        if (!$sections) {
            return;
        }

        for ($i = count($sections) - 1; $i >= 0; $i--) {
            dbx()->timer($sections[$i]);
        }

        $this->_db_timer_depth = max(0, $this->_db_timer_depth - 1);
    }

/**
     * Erzeugt einen Zeitstempel mit Millisekunden.
     *
     * @return string Zeitstempel im Format `Y-m-d H:i:s.v`
     */
    private function now_ms(): string {
        return (new DateTime())->format('Y-m-d H:i:s.v');
    }
}
