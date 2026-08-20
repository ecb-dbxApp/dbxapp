<?php

/**
 * @brief Zentrale, strikt prüfende Eingabevalidierung.
 *
 * `validate()` bleibt der rueckwaertskompatible boolesche Einstieg.
 * `validateResult()` liefert zusaetzlich einen stabilen Fehlercode, eine
 * lesbare Meldung, den normalisierten Wert und regelabhaengige Details.
 *
 * Wichtige Regeln
 * ----------------
 * - Leere Werte bleiben aus Kompatibilitaetsgruenden optional.
 * - `required` macht einen Wert ausdruecklich zum Pflichtwert.
 * - `trim` normalisiert Zeichenketten vor Laengen- und Formatpruefung.
 * - `min` und `max` bezeichnen bei Zeichenketten die Unicode-Zeichenanzahl.
 * - `array` prueft jedes Element mit der angegebenen Basisregel.
 * - `*` deaktiviert die inhaltliche Pruefung bewusst vollstaendig.
 *
 * Validierung ist keine HTML-Behandlung. Kontextgerechtes Escaping erfolgt
 * erst bei der Ausgabe durch dbxForm/dbxTPL beziehungsweise deren Renderer.
 */
class dbxValidator {

    private array $error_messages = [];

    /** Strukturierte Fehler des letzten Validierungslaufs. */
    private array $errors = [];

    /** Vollständiges strukturiertes Ergebnis des letzten Validierungslaufs. */
    private array $last_result = [];

    /** Optional fest vorgegebene Sprache; leer verwendet die aktive UI-Sprache. */
    private string $language = '';

    private static array $rule_cache = [];

    /** Request-lokaler Cache der kleinen sprachabhängigen Include-Dateien. */
    private static array $message_cache = [];

    /** Deutscher Notfall-Fallback, falls eine Sprachdatei nicht lesbar ist. */
    private const DEFAULT_MESSAGES = [
        'ok'                 => '',
        'required'           => 'Bitte füllen Sie dieses Feld aus.',
        'min_length'         => 'Die Eingabe ist zu kurz. Bitte ergänzen Sie den Wert.',
        'max_length'         => 'Die Eingabe ist zu lang. Bitte kürzen Sie den Wert.',
        'invalid_type'       => 'Bitte prüfen Sie Ihre Eingabe.',
        'invalid_format'     => 'Bitte geben Sie einen gültigen Wert ein.',
        'invalid_range'      => 'Der eingegebene Wert ist nicht zulässig.',
        'invalid_rule'       => 'Das Formular konnte nicht geprüft werden. Bitte versuchen Sie es erneut.',
        'invalid_array'      => 'Bitte prüfen Sie Ihre Auswahl.',
        'invalid_array_item' => 'Bitte prüfen Sie die ausgewählten Einträge.',
    ];


    /* =====================================================
       DB TYPE LIMITS
       ===================================================== */

    private const INT_RANGE = [

        'tinyint'   => [-128,127],
        'smallint'  => [-32768,32767],
        'mediumint' => [-8388608,8388607],
        'int'       => [-2147483648,2147483647]

        // 🔥 bigint entfernt → wird separat geprüft

    ];

    private const TEXT_LIMIT = [

        'tinytext'   => 255,
        'text'       => 65535,
        'mediumtext' => 16777215,
        'longtext'   => 4294967295

    ];

    private const BLOB_PASS = [

        'blob'=>true,
        'tinyblob'=>true,
        'mediumblob'=>true,
        'longblob'=>true

    ];


    /* =====================================================
       TYPE ALIAS
       ===================================================== */

    private const TYPE_ALIAS = [

        'integer'=>'int',
        'numeric'=>'decimal',
        'double'=>'float',
        'real'=>'float',

        'varchar2'=>'varchar',
        'bool'=>'boolean'

    ];


    /* =====================================================
       REGEX
       ===================================================== */

    private const REGEX = [

        'parameter'  => '/^[a-zA-Z0-9._\-|]+$/',
        'parameters' => '/^[a-zA-Z0-9._\/&=\-|]+$/',
        // CMS-Permalinks sind absichtlich flach und portabel. Ordnerpfade,
        // Leerzeichen, Sonderzeichen sowie fuehrende/doppelte Bindestriche
        // sind nicht erlaubt.
        'permalink'  => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        'datetime'   => '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/',

        'word'       => '/^[\p{L}\p{M}\p{N}@._,:;-]+$/u',
        'words'      => '/^[\p{L}\p{M}\p{N}\s@._,:;-]+$/u',
        'sqlsearch'  => '/^[\p{L}\p{M}\p{N}\s@._,:;\/\-]+$/u',

        'alphanum'   => '/^[\p{L}\p{M}0-9@_ .,:-]+$/u',

        'phone'      => '/^\+?[0-9\/\- ]+$/'

    ];


    /* =====================================================
       RULE PARSER
       ===================================================== */

    private function parse_rule(string $rules): array {

        if (isset(self::$rule_cache[$rules])) {
            return self::$rule_cache[$rules];
        }

        $r=[
            'base'     => '',
            'extra'    => '',
            'min'      => null,
            'max'      => null,
            'array'    => false,
            'required' => false,
            'trim'     => false,
            'invalid'  => [],
        ];

        foreach(explode('|',$rules) as $p){
            $p = trim($p);

            if ($p === '') {
                continue;
            }

            if ($p === 'array') {
                $r['array'] = true;
                continue;
            }

            if ($p === 'required') {
                $r['required'] = true;
                continue;
            }

            if ($p === 'trim') {
                $r['trim'] = true;
                continue;
            }

            if (strpos($p,'=')!==false){

                [$k,$v]=explode('=',$p,2);

                if (($k === 'min' || $k === 'max') && preg_match('/^\d+$/', $v)) {
                    $r[$k] = (int)$v;
                } else {
                    $r['invalid'][] = $p;
                }

                continue;
            }

            $base = $p;
            $extra = '';

            if (($pos=strpos($p,'+'))!==false){
                $base = substr($p, 0, $pos);
                $extra = substr($p, $pos + 1);
            }

            if ($r['base'] !== '') {
                // Historische Regeln verwenden `*|date` beziehungsweise
                // `*|parameter`. Das fuehrende `*` bedeutet dabei nur, dass
                // kein engerer Default vorgegeben ist; die konkrete Regel
                // muss weiterhin ausgewertet werden.
                if ($r['base'] === '*' && $base !== '*') {
                    $r['base'] = $base;
                    $r['extra'] = $extra;
                    continue;
                }
                if ($base === '*') {
                    continue;
                }

                $r['invalid'][] = $p;
                continue;
            }

            $r['base'] = $base;
            $r['extra'] = $extra;
        }

        $r['base']=strtolower($r['base']);

        if (isset(self::TYPE_ALIAS[$r['base']])) {
            $r['base']=self::TYPE_ALIAS[$r['base']];
        }

        if ($r['base'] === '' || !$this->is_known_base($r['base'])) {
            $r['invalid'][] = $r['base'] === '' ? '(empty)' : $r['base'];
        }

        if ($r['min'] !== null && $r['max'] !== null && $r['min'] > $r['max']) {
            $r['invalid'][] = 'min>max';
        }

        self::$rule_cache[$rules]=$r;

        return $r;
    }

    private function is_known_base(string $base): bool {
        return $base === '*'
            || in_array($base, [
                'int', 'tinyint', 'smallint', 'mediumint', 'bigint',
                'decimal', 'float', 'date', 'time', 'datetime', 'timestamp',
                'varchar', 'char', 'password', 'boolean', 'email', 'json', 'year'
            ], true)
            || isset(self::TEXT_LIMIT[$base])
            || isset(self::BLOB_PASS[$base])
            || isset(self::REGEX[$base]);
    }


    /* =====================================================
       LENGTH
       ===================================================== */

    private function string_length(string $value): int {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        $count = preg_match_all('/./us', $value, $matches);
        return $count === false ? strlen($value) : $count;
    }


    /* =====================================================
       EXTRA STRIP
       ===================================================== */

    private function strip_extra(string $v,string $extra): string {

        if ($extra==='') return $v;

        return str_replace(str_split($extra),'',$v);
    }


    /* =====================================================
       DATE / TIME
       ===================================================== */

    private function validate_date(string $v): bool {

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)) return false;

        [$y,$m,$d]=explode('-',$v);

        return checkdate((int)$m,(int)$d,(int)$y);
    }

    private function validate_time(string $v): bool {

        return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/',$v);
    }

    private function validate_timestamp(string $v): bool {

        if (!preg_match(
            '/^(\d{4})-(\d{2})-(\d{2}) ((?:[01]\d|2[0-3])):([0-5]\d):([0-5]\d)(?:\.\d{1,6})?$/',
            $v,
            $m
        )) {
            return false;
        }

        return checkdate((int)$m[2],(int)$m[3],(int)$m[1]);
    }


    /* =====================================================
       E-MAIL
       ===================================================== */

    /**
     * Prüft eine vollstaendige Internet-E-Mail-Adresse.
     *
     * Neben PHPs Syntaxpruefung werden die RFC-Laengenbegrenzungen sowie
     * Domainlabels und eine vollstaendige Top-Level-Domain kontrolliert.
     */
    private function validate_email(string $v): bool {

        if ($v === '' || strlen($v) > 254 || substr_count($v, '@') !== 1) {
            return false;
        }

        [$local, $domain] = explode('@', $v, 2);

        if ($local === '' || strlen($local) > 64 || $domain === '' || strlen($domain) > 253) {
            return false;
        }

        if ($local[0] === '.' || substr($local, -1) === '.' || strpos($local, '..') !== false) {
            return false;
        }

        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7E]/', $domain)) {
            $idn_flags = defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0;
            $idn_variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
            $ascii_domain = idn_to_ascii($domain, $idn_flags, $idn_variant);
            if ($ascii_domain === false) {
                return false;
            }
            $domain = $ascii_domain;
        }

        if (strpos($domain, '.') === false || strpos($domain, '..') !== false) {
            return false;
        }

        $labels = explode('.', $domain);
        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63
                || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i', $label)) {
                return false;
            }
        }

        $tld = (string)end($labels);
        if (!preg_match('/^(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})$/i', $tld)) {
            return false;
        }

        return filter_var($local . '@' . $domain, FILTER_VALIDATE_EMAIL) !== false;
    }


    /* =====================================================
       INT VALIDATION
       ===================================================== */

    private function validate_int(string $v,string $type): bool {

        if (!preg_match('/^-?\d+$/',$v)) return false;

        if ($type === 'bigint') {
            $negative = str_starts_with($v, '-');
            $digits = ltrim($v, '-0');
            if ($digits === '') {
                return true;
            }

            $limit = $negative ? '9223372036854775808' : '9223372036854775807';
            return strlen($digits) < strlen($limit)
                || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) <= 0);
        }

        if (!isset(self::INT_RANGE[$type])) return true;

        [$min,$max]=self::INT_RANGE[$type];

        $i=(int)$v;

        return ($i>=$min && $i<=$max);
    }


    /* =====================================================
       SCALAR VALIDATION
       ===================================================== */

    private function result(
        bool $valid,
        string $name,
        string $rules,
        string $base,
        $normalized,
        string $code = 'ok',
        array $details = []
    ): array {
        return [
            'valid'      => $valid,
            'name'       => $name,
            'rules'      => $rules,
            'rule'       => $base,
            'normalized' => $normalized,
            'code'       => $code,
            'message'    => $this->message($code),
            'details'    => $details,
        ];
    }

    /**
     * Setzt die Sprache für folgende Validierungen explizit.
     *
     * Ein leerer Wert aktiviert wieder die automatische UI-Sprache.
     */
    public function set_language(string $language = ''): void {
        $this->language = $this->normalize_language($language, true);
    }

    /**
     * Liefert die aktuell wirksame Sprache.
     */
    public function get_language(): string {
        return $this->resolve_language();
    }

    private function normalize_language(string $language, bool $allow_empty = false): string {
        $language = strtolower(trim($language));
        $language = preg_split('/[-_]/', $language, 2)[0] ?? '';

        if ($language === '' && $allow_empty) {
            return '';
        }

        return preg_match('/^[a-z]{2,3}$/', $language) ? $language : 'de';
    }

    private function resolve_language(): string {
        if ($this->language !== '') {
            return $this->language;
        }

        try {
            return $this->normalize_language((string)dbx()->lng_current());
        } catch (\Throwable $e) {
        }

        return 'de';
    }

    private function messages(string $language): array {
        $language = $this->normalize_language($language);
        if (isset(self::$message_cache[$language])) {
            return self::$message_cache[$language];
        }

        $messages = self::DEFAULT_MESSAGES;
        $default_file = __DIR__ . '/lang/dbxValidator_de.php';
        if (is_file($default_file)) {
            $loaded = include $default_file;
            if (is_array($loaded)) {
                $messages = array_merge($messages, $loaded);
            }
        }

        if ($language !== 'de') {
            $language_file = __DIR__ . '/lang/dbxValidator_' . $language . '.php';
            if (is_file($language_file)) {
                $loaded = include $language_file;
                if (is_array($loaded)) {
                    $messages = array_merge($messages, $loaded);
                }
            }
        }

        self::$message_cache[$language] = $messages;
        return $messages;
    }

    private function message(string $code): string {
        $messages = $this->messages($this->resolve_language());
        return (string)($messages[$code] ?? $messages['invalid_format'] ?? '');
    }

    private function validate_scalar_result($value, array $rule, string $rules, string $name): array {
        $base     = $rule['base'];
        $extra    = $rule['extra'];
        $min      = $rule['min'];
        $max      = $rule['max'];
        $required = (bool)$rule['required'];

        if (is_bool($value)) {
            if ($base !== 'boolean' && $base !== '*') {
                return $this->result(false, $name, $rules, $base, $value, 'invalid_type', [
                    'actual_type' => 'boolean',
                ]);
            }
            $value = $value ? '1' : '0';
        } elseif ($value === null) {
            $value = '';
        } else {
            $value = (string)$value;
        }

        if ($rule['trim']) {
            $value = trim($value);
        }

        if ($required && $value === '') {
            return $this->result(false, $name, $rules, $base, $value, 'required');
        }

        $length = $this->string_length($value);
        if ($min !== null && $length < $min) {
            return $this->result(false, $name, $rules, $base, $value, 'min_length', [
                'min' => $min,
                'actual' => $length,
            ]);
        }
        if ($max !== null && $length > $max) {
            return $this->result(false, $name, $rules, $base, $value, 'max_length', [
                'max' => $max,
                'actual' => $length,
            ]);
        }

        if ($value === '') {
            return $this->result(true, $name, $rules, $base, $value);
        }

        $check_value = $this->strip_extra($value, $extra);
        if ($check_value === '') {
            return $this->result(true, $name, $rules, $base, $value);
        }

        $code = 'invalid_format';

        switch($base){

            case '*':
                $ok=true;
                break;

            case 'int':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
                $ok=$this->validate_int($check_value,$base);
                $code = preg_match('/^-?\d+$/', $check_value) ? 'invalid_range' : 'invalid_format';
                break;

            case 'decimal':
            case 'float':
                $ok=is_numeric($check_value);
                break;

            case 'date':
                $ok=$this->validate_date($check_value);
                break;

            case 'time':
                $ok=$this->validate_time($check_value);
                break;

            case 'datetime':
            case 'timestamp':
                $ok=$this->validate_timestamp($check_value);
                break;

            case 'varchar':
            case 'char':
            case 'password':
                $ok=true;
                break;

            case 'boolean':
                $ok=($check_value==='0'||$check_value==='1'||$check_value==='true'||$check_value==='false');
                break;

            case 'email':
                $ok=$this->validate_email($check_value);
                break;

            case 'json':
                json_decode($check_value);
                $ok=(json_last_error()===JSON_ERROR_NONE);
                break;

            case 'year':
                $ok=preg_match('/^\d{4}$/',$check_value);
                break;

            default:

                if (isset(self::TEXT_LIMIT[$base])){
                    $ok=$this->string_length($check_value)<=self::TEXT_LIMIT[$base];
                    $code = 'max_length';
                    break;
                }

                if (isset(self::BLOB_PASS[$base])){
                    $ok=true;
                    break;
                }

                if (isset(self::REGEX[$base])){
                    $ok=preg_match(self::REGEX[$base],$check_value);
                    break;
                }

                return $this->result(false, $name, $rules, $base, $value, 'invalid_rule');
        }

        if (!$ok) {
            return $this->result(false, $name, $rules, $base, $value, $code);
        }

        return $this->result(true, $name, $rules, $base, $value);
    }


    /* =====================================================
       PUBLIC VALIDATE (STRICT)
       ===================================================== */

    /**
     * Validiert einen Wert und liefert ein maschinenlesbares Ergebnis.
     *
     * Die Schluessel `code` und `details` sind fuer Programmlogik bestimmt;
     * `message` ist eine allgemeine Fallback-Meldung. Formulare duerfen diese
     * weiterhin durch eine fachlich genauere Feldmeldung ersetzen.
     *
     * @param mixed  $value Zu pruefender Wert
     * @param string $rules Regelkette, zum Beispiel `required|email|max=180`
     * @param string $name  Logischer Feldname
     * @return array{
     *   valid:bool,
     *   name:string,
     *   rules:string,
     *   rule:string,
     *   normalized:mixed,
     *   code:string,
     *   message:string,
     *   details:array
     * }
     */
    public function validate_result($value, $rules = 'parameter', $name = '-undef-'): array {
        $this->clear_errors();

        $rules = trim((string)$rules);
        $name = (string)$name;

        // Historischer Vollpass: `*` akzeptiert bewusst auch Arrays und
        // andere Werte. Engere Arraypruefung wird mit `array|...` aktiviert.
        if ($rules === '*') {
            return $this->record_result(
                $this->result(true, $name, $rules, '*', $value)
            );
        }

        $rule = $this->parse_rule($rules);

        if ($rule['invalid']) {
            return $this->record_result($this->result(
                false,
                $name,
                $rules,
                (string)$rule['base'],
                $value,
                'invalid_rule',
                ['invalid' => array_values(array_unique($rule['invalid']))]
            ));
        }

        if ($rule['array']) {
            if ($value === null || $value === '') {
                $result = $rule['required']
                    ? $this->result(false, $name, $rules, $rule['base'], [], 'required')
                    : $this->result(true, $name, $rules, $rule['base'], []);
                return $this->record_result($result);
            }

            if (!is_array($value)) {
                return $this->record_result(
                    $this->result(false, $name, $rules, $rule['base'], $value, 'invalid_array')
                );
            }

            if ($rule['required'] && count($value) === 0) {
                return $this->record_result(
                    $this->result(false, $name, $rules, $rule['base'], [], 'required')
                );
            }

            $normalized = [];
            foreach ($value as $index => $item) {
                if (is_array($item) || is_object($item) || is_resource($item)
                    || (!is_scalar($item) && $item !== null)) {
                    return $this->record_result($this->result(
                        false,
                        $name,
                        $rules,
                        $rule['base'],
                        $normalized,
                        'invalid_array_item',
                        ['index' => $index, 'item_code' => 'invalid_type']
                    ));
                }

                $item_result = $this->validate_scalar_result($item, $rule, $rules, $name . '[' . $index . ']');
                $normalized[$index] = $item_result['normalized'];
                if (!$item_result['valid']) {
                    return $this->record_result($this->result(
                        false,
                        $name,
                        $rules,
                        $rule['base'],
                        $normalized,
                        'invalid_array_item',
                        ['index' => $index, 'item' => $item_result]
                    ));
                }
            }

            return $this->record_result(
                $this->result(true, $name, $rules, $rule['base'], $normalized)
            );
        }

        if (is_array($value) || is_object($value) || is_resource($value)
            || (!is_scalar($value) && $value !== null)) {
            return $this->record_result($this->result(
                false,
                $name,
                $rules,
                $rule['base'],
                $value,
                'invalid_type',
                ['actual_type' => gettype($value)]
            ));
        }

        return $this->record_result(
            $this->validate_scalar_result($value, $rule, $rules, $name)
        );
    }

    /**
     * Rueckwaertskompatibler boolescher Einstieg.
     *
     * Fuer Fehlercode, normalisierten Wert und Details validateResult() nutzen.
     */
    public function validate($value,$rules='parameter',$name='-undef-'): bool {
        $result = $this->validate_result($value, $rules, $name);
        return (bool)$result['valid'];
    }


    /* =====================================================
       CLEAN
       ===================================================== */

    /**
     * Historische Fehlerbehandlung fuer den DB-Clean-Modus.
     *
     * Die Methode escaped und filtert absichtlich nicht. Sie begrenzt nur die
     * Laenge, nachdem die eigentliche Validierung fehlgeschlagen ist. Fuer neue
     * Aufrufer sind validateResult() und dessen `normalized`-Wert vorzuziehen.
     *
     * @param mixed  $value  Eingabewert
     * @param string $rules  Nur aus Kompatibilitaetsgruenden vorhanden
     * @param int    $length Maximale Byte-Laenge; -1 deaktiviert das Kuerzen
     * @param string $name   Feldname fuer kompatible Signatur
     * @return string
     */
    public function clean($value,$rules,$length=-1,$name='-undef-'){

        if (!is_scalar($value) && $value !== null) return '';

        if ($value===null) $value='';
        $value = (string)$value;
        $length = (int)$length;

        if ($length>0 && strlen($value)>$length)
            $value=substr($value,0,$length);

        return $value;
    }


    /* =====================================================
       UTIL
       ===================================================== */

    public function get_error_messages(): array {
        return $this->error_messages;
    }

    /**
     * Liefert die strukturierten Fehler des letzten Validierungslaufs.
     */
    public function get_errors(): array {
        return $this->errors;
    }

    /**
     * Liefert das vollstaendige Ergebnis des letzten Validierungslaufs.
     */
    public function get_last_result(): array {
        return $this->last_result;
    }

    public function clear_errors(): void {
        $this->error_messages=[];
        $this->errors=[];
        $this->last_result=[];
    }

    private function record_result(array $result): array {
        $this->last_result = $result;
        if (!($result['valid'] ?? false)) {
            $this->errors[] = $result;
            $this->error_messages[] = (string)($result['message'] ?? '');
        }
        return $result;
    }

    public function get_rule_val($rules,$rule=''){

        if (is_string($rules)) $rules=explode('|',$rules);

        if (!is_array($rules)) return '';

        foreach ($rules as $r){
            $r = trim((string)$r);

            if ($rule === 'rule'
                && strpos($r, '=') === false
                && !in_array($r, array('array', 'required', 'trim'), true))
                return $r;

            if (strpos($r,'=')!==false){

                [$k,$v]=explode('=',$r,2);

                if ($k===$rule)
                    return $v;
            }
        }

        return '';
    }

}
