<?php
/**
 * Zerlegt einfache SQL-Anweisungen in ihre strukturellen Bestandteile.
 *
 * Der Analyzer erkennt SELECT, INSERT, UPDATE und DELETE und stellt Typ,
 * Tabelle, Felder, Bedingungen, Sortierung sowie Begrenzung getrennt bereit.
 */
class dbxAnalyzerSQL {
    public $sql = '';
    public $type = 'UNKNOWN';
    public $fields = '';
    public $values = '';
    public $table = '';
    public $where = '';
    public $order_by = '';
    public $group_by = '';
    public $limit = 100;
    public $offset = 0;
    public $is_valid = true; // Neue Eigenschaft für die Gültigkeit des SQL-Statements

    public function analyze($sql) {
        $this->sql = trim($sql);

        if (empty($this->sql)) {
            $this->mark_invalid("SQL-Statement darf nicht leer sein.");
            return;
        }

        if (preg_match('/^\s*(SELECT|DELETE|INSERT|UPDATE)\b/i', $this->sql, $matches)) {
            $this->type = strtoupper($matches[1]);
        } else {
            $this->mark_invalid("Unbekannter oder nicht unterstützter SQL-Typ.");
            return;
        }

        try {
            switch ($this->type) {
                case 'INSERT':
                    $this->parse_insert();
                    break;
                case 'DELETE':
                    $this->parse_delete();
                    break;
                case 'SELECT':
                    $this->parse_select();
                    break;
            }

            $this->where = $this->extract_clause('WHERE');
            $this->order_by = $this->extract_clause('ORDER BY');
            $this->group_by = $this->extract_clause('GROUP BY');
            $this->extract_limit_and_offset();
        } catch (Exception $e) {
            $this->mark_invalid($e->getMessage());
        }
    }

    private function mark_invalid($message) {
        $this->is_valid = false;
        $this->type = 'UNKNOWN';
        $this->table = '';
        $this->fields = '';
        $this->values = '';
        $this->where = '';
        $this->order_by = '';
        $this->group_by = '';
        $this->limit = 100;
        $this->offset = 0;
        error_log("SQL Analyzer Fehler: $message");
    }

    private function parse_insert() {
        if (preg_match('/INSERT\s+INTO\s+([^\s(]+)(?:\s*\((.*?)\))?\s*VALUES\s*\((.*?)\)/i', $this->sql, $matches)) {
            $this->table = $matches[1];
            $this->fields = $matches[2] ?? '';
            $this->values = $matches[3];
        } else {
            throw new InvalidArgumentException("Ungültige INSERT-Syntax.");
        }
    }

    private function parse_delete() {
        if (preg_match('/DELETE\s+FROM\s+([^\s]+)/i', $this->sql, $matches)) {
            $this->table = $matches[1];
        } else {
            throw new InvalidArgumentException("Ungültige DELETE-Syntax.");
        }
    }

    private function parse_select() {
        if (preg_match('/SELECT\s+(.*?)\s+FROM\s+([^\s]+)/i', $this->sql, $matches)) {
            $this->fields = $matches[1];
            $this->table = $matches[2];
        } else {
            throw new InvalidArgumentException("Ungültige SELECT-Syntax.");
        }
    }

    private function extract_clause($clause) {
        if (preg_match("/\b$clause\b\s+(.*?)(?=\b(ORDER BY|GROUP BY|LIMIT|OFFSET|$))/i", $this->sql, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private function extract_limit_and_offset() {
        if (preg_match('/\bLIMIT\b\s+(\d+)(?:\s+\bOFFSET\b\s+(\d+))?/i', $this->sql, $matches)) {
            $this->limit = (int) ($matches[1] ?? 100);
            $this->offset = (int) ($matches[2] ?? 0);
        }
    }

    private function get_value($property, $default) {
        return $property !== '' ? $property : $default;
    }

    public function get_type($default = 'UNKNOWN') {
        return $this->get_value($this->type, $default);
    }

    public function get_fields($default = '*') {
        return $this->get_value($this->fields, $default);
    }

    public function get_table($default = '') {
        return $this->get_value($this->table, $default);
    }

    public function get_where($default = '') {
        return $this->get_value($this->where, $default);
    }

    public function get_order_by($default = '') {
        return $this->get_value($this->order_by, $default);
    }

    public function get_group_by($default = '') {
        return $this->get_value($this->group_by, $default);
    }

    public function get_limit($default = 100) {
        return (int) $this->get_value($this->limit, $default);
    }

    public function get_offset($default = 0) {
        return (int) $this->get_value($this->offset, $default);
    }
}
