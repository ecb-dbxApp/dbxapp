<?php
/**
 * Class dbxAnalyzerSQL
 * 
 * Analyzes basic SQL statements (SELECT, INSERT, DELETE) to extract components such as
 * type, table, fields, values, and clauses like WHERE, ORDER BY, GROUP BY, LIMIT, and OFFSET.
 * 
 * Supports basic SQL parsing for simpler database operations and debugging purposes.
 */
class dbxAnalyzerSQL {
    /** @var string Original SQL statement. */
    public $sql = '';

    /** @var string The type of SQL statement (e.g., SELECT, INSERT, DELETE). */
    public $type = '';

    /** @var string Fields in the SELECT or INSERT statement. */
    public $fields = '';

    /** @var string Values in the INSERT statement. */
    public $values = '';

    /** @var string Name of the table being targeted. */
    public $table = '';

    /** @var string WHERE clause of the SQL statement. */
    public $where = '';

    /** @var string ORDER BY clause of the SQL statement. */
    public $orderBy = '';

    /** @var string GROUP BY clause of the SQL statement. */
    public $groupBy = '';

    /** @var int LIMIT clause value of the SQL statement. */
    public $limit = 100;

    /** @var int OFFSET clause value of the SQL statement. */
    public $offset = 0;

    /**
     * Analyzes the given SQL statement and extracts its components.
     * 
     * @param string $sql The SQL statement to analyze.
     * @throws InvalidArgumentException If the SQL statement is empty or unrecognized.
     */
    public function analyze($sql) {
        $this->sql = trim($sql);
        if (empty($this->sql)) {
            throw new InvalidArgumentException("SQL-Statement darf nicht leer sein.");
        }

        dbx_debug("ANALYZER=($this->sql)");

        if (preg_match('/^\s*(SELECT|DELETE|INSERT|UPDATE)\b/i', $this->sql, $matches)) {
            $this->type = strtoupper($matches[1]);
        } else {
            throw new InvalidArgumentException("Unbekannter oder nicht unterstützter SQL-Typ.");
        }

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
    }

    /**
     * Parses an INSERT statement and extracts the table, fields, and values.
     * 
     * @throws InvalidArgumentException If the INSERT statement has invalid syntax.
     */
    private function parseInsert() {
        if (preg_match('/INSERT\s+INTO\s+([^\s(]+)(?:\s*\((.*?)\))?\s*VALUES\s*\((.*?)\)/i', $this->sql, $matches)) {
            $this->table = $matches[1];
            $this->fields = $matches[2] ?? '';
            $this->values = $matches[3];
        } else {
            throw new InvalidArgumentException("Ungültige INSERT-Syntax.");
        }
    }

    /**
     * Parses a DELETE statement and extracts the table name.
     * 
     * @throws InvalidArgumentException If the DELETE statement has invalid syntax.
     */
    private function parseDelete() {
        if (preg_match('/DELETE\s+FROM\s+([^\s]+)/i', $this->sql, $matches)) {
            $this->table = $matches[1];
        } else {
            throw new InvalidArgumentException("Ungültige DELETE-Syntax.");
        }
    }

    /**
     * Parses a SELECT statement and extracts the fields and table name.
     * 
     * @throws InvalidArgumentException If the SELECT statement has invalid syntax.
     */
    private function parseSelect() {
        if (preg_match('/SELECT\s+(.*?)\s+FROM\s+([^\s]+)/i', $this->sql, $matches)) {
            $this->fields = $matches[1];
            $this->table = $matches[2];
        } else {
            throw new InvalidArgumentException("Ungültige SELECT-Syntax.");
        }
    }

    /**
     * Extracts a specific SQL clause (e.g., WHERE, ORDER BY) from the statement.
     * 
     * @param string $clause The clause to extract (e.g., 'WHERE', 'ORDER BY').
     * @return string The extracted clause content, or an empty string if not present.
     */
    private function extractClause($clause) {
        if (preg_match("/\b$clause\b\s+(.*?)(?=\b(ORDER BY|GROUP BY|LIMIT|OFFSET|$))/i", $this->sql, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * Extracts the LIMIT and OFFSET values from the SQL statement.
     */
    private function extractLimitAndOffset() {
        if (preg_match('/\bLIMIT\b\s+(\d+)(?:\s+\bOFFSET\b\s+(\d+))?/i', $this->sql, $matches)) {
            $this->limit = (int) ($matches[1] ?? 100);
            $this->offset = (int) ($matches[2] ?? 0);
        }
    }

    /**
     * Retrieves a property value with a fallback default value.
     * 
     * @param mixed $property The property value.
     * @param mixed $default The default value if the property is empty.
     * @return mixed The trimmed property value or the default.
     */
    private function getValue($property, $default) {
        return $property !== '' ? $property : $default;
    }

    /**
     * Retrieves the type of the SQL statement.
     * 
     * @param string $default The default type if none is found.
     * @return string The SQL type (e.g., 'SELECT').
     */
    public function getType($default = 'SELECT') {
        return $this->getValue($this->type, $default);
    }

    /**
     * Retrieves the fields in the SQL statement.
     * 
     * @param string $default The default fields if none are found.
     * @return string The fields in the statement (e.g., '*').
     */
    public function getFields($default = '*') {
        return $this->getValue($this->fields, $default);
    }

    /**
     * Retrieves the table name in the SQL statement.
     * 
     * @param string $default The default table name if none is found.
     * @return string The table name.
     */
    public function getTable($default = '') {
        return $this->getValue($this->table, $default);
    }

    /**
     * Retrieves the WHERE clause.
     * 
     * @param string $default The default WHERE clause if none is found.
     * @return string The WHERE clause content.
     */
    public function getWhere($default = '') {
        return $this->getValue($this->where, $default);
    }

    /**
     * Retrieves the ORDER BY clause.
     * 
     * @param string $default The default ORDER BY clause if none is found.
     * @return string The ORDER BY clause content.
     */
    public function getOrderBy($default = '') {
        return $this->getValue($this->orderBy, $default);
    }

    /**
     * Retrieves the GROUP BY clause.
     * 
     * @param string $default The default GROUP BY clause if none is found.
     * @return string The GROUP BY clause content.
     */
    public function getGroupBy($default = '') {
        return $this->getValue($this->groupBy, $default);
    }

    /**
     * Retrieves the LIMIT value.
     * 
     * @param int $default The default LIMIT if none is found.
     * @return int The LIMIT value.
     */
    public function getLimit($default = 100) {
        return (int) $this->getValue($this->limit, $default);
    }

    /**
     * Retrieves the OFFSET value.
     * 
     * @param int $default The default OFFSET if none is found.
     * @return int The OFFSET value.
     */
    public function getOffset($default = 0) {
        return (int) $this->getValue($this->offset, $default);
    }
}

