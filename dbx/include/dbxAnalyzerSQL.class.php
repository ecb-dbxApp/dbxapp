<?php
class dbxAnalyzerSQL {
    public $sql = '';
    public $type = 'UNKNOWN';
    public $fields = '';
    public $values = '';
    public $table = '';
    public $where = '';
    public $orderBy = '';
    public $groupBy = '';
    public $limit = 100;
    public $offset = 0;
    public $isValid = true; // Neue Eigenschaft für die Gültigkeit des SQL-Statements

    public function analyze($sql) {
        $this->sql = trim($sql);

        if (empty($this->sql)) {
            $this->markInvalid("SQL-Statement darf nicht leer sein.");
            return;
        }

        if (preg_match('/^\s*(SELECT|DELETE|INSERT|UPDATE)\b/i', $this->sql, $matches)) {
            $this->type = strtoupper($matches[1]);
        } else {
            $this->markInvalid("Unbekannter oder nicht unterstützter SQL-Typ.");
            return;
        }

        try {
            switch ($this->type) {
                case 'INSERT':
                    $this->parseInsert();
                    break;
                case 'DELETE':
                    $this->parseDelete();
                    break;
                case 'SELECT':
                    $this->parseSelect();
                    break;
            }

            $this->where = $this->extractClause('WHERE');
            $this->orderBy = $this->extractClause('ORDER BY');
            $this->groupBy = $this->extractClause('GROUP BY');
            $this->extractLimitAndOffset();
        } catch (Exception $e) {
            $this->markInvalid($e->getMessage());
        }
    }

    private function markInvalid($message) {
        $this->isValid = false;
        $this->type = 'UNKNOWN';
        $this->table = '';
        $this->fields = '';
        $this->values = '';
        $this->where = '';
        $this->orderBy = '';
        $this->groupBy = '';
        $this->limit = 100;
        $this->offset = 0;
        error_log("SQL Analyzer Fehler: $message");
    }

    private function parseInsert() {
        if (preg_match('/INSERT\s+INTO\s+([^\s(]+)(?:\s*\((.*?)\))?\s*VALUES\s*\((.*?)\)/i', $this->sql, $matches)) {
            $this->table = $matches[1];
            $this->fields = $matches[2] ?? '';
            $this->values = $matches[3];
        } else {
            throw new InvalidArgumentException("Ungültige INSERT-Syntax.");
        }
    }

    private function parseDelete() {
        if (preg_match('/DELETE\s+FROM\s+([^\s]+)/i', $this->sql, $matches)) {
            $this->table = $matches[1];
        } else {
            throw new InvalidArgumentException("Ungültige DELETE-Syntax.");
        }
    }

    private function parseSelect() {
        if (preg_match('/SELECT\s+(.*?)\s+FROM\s+([^\s]+)/i', $this->sql, $matches)) {
            $this->fields = $matches[1];
            $this->table = $matches[2];
        } else {
            throw new InvalidArgumentException("Ungültige SELECT-Syntax.");
        }
    }

    private function extractClause($clause) {
        if (preg_match("/\b$clause\b\s+(.*?)(?=\b(ORDER BY|GROUP BY|LIMIT|OFFSET|$))/i", $this->sql, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private function extractLimitAndOffset() {
        if (preg_match('/\bLIMIT\b\s+(\d+)(?:\s+\bOFFSET\b\s+(\d+))?/i', $this->sql, $matches)) {
            $this->limit = (int) ($matches[1] ?? 100);
            $this->offset = (int) ($matches[2] ?? 0);
        }
    }

    private function getValue($property, $default) {
        return $property !== '' ? $property : $default;
    }

    public function getType($default = 'UNKNOWN') {
        return $this->getValue($this->type, $default);
    }

    public function getFields($default = '*') {
        return $this->getValue($this->fields, $default);
    }

    public function getTable($default = '') {
        return $this->getValue($this->table, $default);
    }

    public function getWhere($default = '') {
        return $this->getValue($this->where, $default);
    }

    public function getOrderBy($default = '') {
        return $this->getValue($this->orderBy, $default);
    }

    public function getGroupBy($default = '') {
        return $this->getValue($this->groupBy, $default);
    }

    public function getLimit($default = 100) {
        return (int) $this->getValue($this->limit, $default);
    }

    public function getOffset($default = 0) {
        return (int) $this->getValue($this->offset, $default);
    }
}
