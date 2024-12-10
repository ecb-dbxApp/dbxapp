<?php



class dbxValidator {
    private array $errorMessages = [];

    // Regelmäßige Ausdrücke zentral verwalten
    private const REGEX = [
    
        'number'     => '/^-?\d+([.,]\d+)?$/',
        'date'       => '/^\d{4}-\d{2}-\d{2}$|^\d{2}\.\d{2}\.\d{4}$/',
        'datetime'   => '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$|^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}(:\d{2})?$/',
        'parameter'  => '/^[a-zA-Z0-9._-]{1,32}$/',
        'parameters' => '/^[a-zA-Z0-9._\/&=-]{1,255}$/',

        'word'  => '/^[\p{L}\p{M}\x21-\x7E\xA0-\xFF\x{20AC}]+$/u',
        'words' => '/^[\p{L}\p{M}\s\x20-\x7E\xA0-\xFF\x{20AC}]+$/u',

        'alphanum'   => '/^[a-zA-Z0-9äöüÄÖÜß@_ -]+$/',
        'email'      => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        'phone'      => '/^\+?[0-9\/\- ]+$/',
        'url'        => '/^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([\/\w .-]*)*\/?$/',
        'filename'   => '/^[\w,\s-]+\.[A-Za-z]{2,4}$/',
        'path'       => '/^(\/[a-zA-Z0-9_-]+)+\/?$/',
        'pathfile'   => '/^(\/[a-zA-Z0-9_-]+)+\/[\w,\s-]+\.[A-Za-z]{2,4}$/',
        'time'       => '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/',
        'list'       => '/^([a-zA-Z0-9_]+,)*[a-zA-Z0-9_]+$/',
        

        'int' => '/^-?\d+$/',
        'integer' => '/^-?\d+$/',
        'tinyint' => '/^-?\d{1,3}$/', // 0-255 oder -128 bis 127 für signed
        'smallint' => '/^-?\d{1,5}$/', // -32768 bis 32767 oder 0-65535
        'mediumint' => '/^-?\d{1,8}$/', // -8388608 bis 8388607 oder 0-16777215
        'bigint' => '/^-?\d+$/', // Sehr große Ganzzahlen
        'decimal' => '/^-?\d+(\.\d+)?$/', // Zahlen mit Nachkommastellen
        'float' => '/^-?\d+(\.\d+)?(e[+-]?\d+)?$/i', // Float-Werte mit wissenschaftlicher Notation
        'double' => '/^-?\d+(\.\d+)?(e[+-]?\d+)?$/i',
        'char' => '/^[\s\S]{1,255}$/', // Bis 255 Zeichen
        'varchar' => '/^[\s\S]{1,65535}$/', // Bis zu 65k Zeichen
        'text' => '/^[\s\S]{1,65535}$/', // Großtext
        'tinytext' => '/^[\s\S]{1,255}$/',
        'mediumtext' => '/^[\s\S]{1,16777215}$/', // Mittelgroßer Text
        'longtext' => '/^[\s\S]{1,4294967295}$/', // Sehr großer Text
        'timestamp' => '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
        'year' => '/^\d{4}$/',
        'boolean' => '/^(0|1|true|false)$/i', // MySQL speichert boolean als tinyint(1)
        'json' => '/^\{.*\}$|^\[.*\]$/', // Einfache JSON-Struktur
        'enum' => '/^\w+$/', // Wird durch Werteprüfung ergänzt
        'set' => '/^(\w+(,\w+)*)$/', // Liste von Werten, getrennt durch Komma
        'password' => '/^[\s\S]{1,255}$/', // Bis 255 Zeichen
  

    ];

    /**
     * Validierung der Länge eines Werts mit min/max
     */
    private function validateLength(string $value, array $params): bool
    {
        $length = strlen($value);
        if (isset($params['min']) && $length < $params['min']) {
            return false;
        }
        if (isset($params['max']) && $length > $params['max']) {
            return false;
        }
        return true;
    }


    private function removeRule(string $rules, string $ruleToRemove): string {
        // Zerlege die Regeln in ein Array
        $rulesArray = explode('|', $rules);
    
        // Filtere die zu entfernende Regel heraus
        $filteredRules = array_filter($rulesArray, function ($rule) use ($ruleToRemove) {
            return trim($rule) !== $ruleToRemove;
        });
    
        // Füge die verbleibenden Regeln zusammen
        $filteredRulesString = implode('|', $filteredRules);
    
        // Entferne ein führendes '|' (falls vorhanden)
        return ltrim($filteredRulesString, '|');
    }
    



    /**
     * Extrahiert Bedingungsparameter wie min/max aus der Regel.
     */
    private function parseRules(string $rules): array {
        $params = [];
        $filteredRules = [];

        foreach (explode('|', $rules) as $rule) {
            if (str_contains($rule, '=')) {
                [$key, $value] = explode('=', $rule, 2);
                if (in_array($key, ['min', 'max'])) {
                    $params[$key] = is_numeric($value) ? (int)$value : $value;
                } else {
                    $filteredRules[] = $rule; // Keine min/max-Regel, behalten
                }
            } else {
                $filteredRules[] = $rule;
            }
        }

        return ['parameters' => $params, 'filteredRules' => implode('|', $filteredRules)];
    }

    /**
     * Validiert einen Wert anhand einer Regel.
     */
    public function validateRule($value, string $rule): bool
    {
       
        // Konvertiere leere Werte zu einem leeren String
        $value = ($value === null || $value === '') ? '' : (string)$value;

        // Parsen der Regel und Parameter
        $parsed = $this->parseRules($rule);
        $params = $parsed['parameters'];
        $filteredRule = $parsed['filteredRules'];

        // Längenbeschränkungen prüfen
        if (!$this->validateLength($value, $params)) {
            return false;
        }

        if ($value == '' || $value == '0' || $value == '1') return true; // speed up 

        // Überprüfe, ob zusätzliche Zeichen angegeben sind
        if (strpos($filteredRule, '+') !== false) {
            $baseRule = strstr($filteredRule, '+', true);
            $additionalChars = substr(strstr($filteredRule, '+'), 1); // Zeichen nach '+'
            return $this->validateWithRegex($value, $baseRule, $additionalChars);
        }

        // Falls keine weiteren Regeln vorhanden sind, wird direkt geprüft
        if (empty($filteredRule)) {
            return true;
        }

        // Überprüfe den Wert mit der Basisregel 
        return $this->validateWithRegex($value, $filteredRule);
    }

    /**
     * Validiert einen oder mehrere Werte basierend auf Regeln.
     */
    public function validate($value, string $rules = 'parameter', $name = '-undef-'): bool {
        if ($value===null) $value=''; 
        
        //dbx_debug("Validate ($name) Val=($value) rules=($rules)");
        
         // Check $rules format !
        // Ersetzt doppelte '||' durch '|'
        $rules = str_replace('||', '|', $rules);
        if (substr($rules, 0, 1) === '|') {
            $rules = substr($rules, 1);
        }
        
        //if ( is_array($value)) dbx_debug("validate=($name) Rul=($rules) is Array");
    

        if ($rules === '')  return true;  // erlaubt alles ohne Prüfung
        if ($rules === '*') return true;  // erlaubt alles ohne Prüfung
        if ($rules == 'dd:') {
            dbx_debug("#Validator dd: Fld=($name) Regel ($rules) Val=", $value);
            return true;
        }
        if (strpos($rules, 'array') === true) {
            if (!is_array($value)) {
                dbx_debug("a#Error Validator Fld=($name) Regel (array) Value ($value) ist keine array"); 
                return false; 
            }       
        }    

        // Wenn ein Array geprüft wird
        if (is_array($value)) {
            if (strpos($rules, 'array') === false) {
                dbx_debug("a#Error Validator Fld=($name) Regel ($rules) enthält keine 'array'-Bedingung", $value);
                return false;
            }
    
            // Entferne die Regel 'array' aus den Regeln
            $rules = $this->removeRule($rules, 'array');
    
            // Array: Prüfe jedes Element mit der restlichen Regel
            $rulesArray = explode('|', $rules);
            foreach ($value as $item) {
                foreach ($rulesArray as $rule) {
                    if ($item===null) $item='';
                    dbx_debug("validate item rule=($rule) val=($item)");
                    if (!$this->validateRule($item, $rule)) {
                        dbx_debug("b#Error Validator Array Fld=($name) Rul=($rule) value=($item)");
                        return false;
                    }
                }
            }
            return true; // Alle Array-Elemente gültig
        } else {
            // Kein Array, prüfe den Wert direk   
            $rulesArray = explode('|', $rules);
            foreach ($rulesArray as $rule) {
                if (!$this->validateRule($value, $rule)) {
                    dbx_debug("c#Error Validator Fld=($name) Rul=($rule) value=($value)", $this->errorMessages);
                    return false;
                }
            }
        }
        return true;
    }
    

    /**
     * Regelvalidierung mit regulären Ausdrücken
     */
    /**
     * Validiert einen Wert mit einem regulären Ausdruck und zusätzlichen erlaubten Zeichen
     */
    private function validateWithRegex(string $value, string $rule, string $additionalChars = ''): bool {
        // Hole den Regex für die Basisregel
        $regex = self::REGEX[$rule] ?? '';
    
        //dbx_debug("#validateWithRegex check val=($value) rule=($rule) add=($additionalChars)");
    
        if ($regex === '') {
            $this->errorMessages[] = "Unbekannte Regel: $rule";
            return false;
        }
    
        // Wenn zusätzliche Zeichen angegeben sind, erweitere den Regex
        if ($additionalChars !== '') {
            // Suche den schließenden `]` innerhalb des Regex und füge zusätzliche Zeichen hinzu
            $regex = preg_replace('/\]$/', preg_quote($additionalChars, '/') . ']', $regex);
        }
    
        // Prüfe den Wert mit dem modifizierten Regex
        return preg_match($regex, $value) === 1;
    }
    

    /**
     * Gibt Fehlermeldungen zurück.
     */
    public function getErrorMessages(): array {
        return $this->errorMessages;
    }

    /**
     * Löscht alle gespeicherten Fehlermeldungen.
     */
    public function clearErrors(): void    {
        $this->errorMessages = [];
    }

    public function clean($value, string $rules, $length = -1, $name = '-undef-') {
        // Entferne die Regeln 'array' und '*'
        $rules = $this->removeRule($rules, 'array');
        $rules = $this->removeRule($rules, '*');
        $rules = str_replace('||', '|', $rules);
        if (substr($rules, 0, 1) === '|') {
            $rules = substr($rules, 1);
        }
        
     

        // Debug-Ausgabe
        dbx_debug("clean var=($name) rules=($rules) Lang=($length) Value=($value)");

        if (is_array($value)) return $value; // #todo  clean array ?  
        if ($value===null) $value='';
        if (!$rules || !$value) return $value;        
        

        // Kürze den Wert, falls eine maximale Länge angegeben ist
        if ($length > 0) {
            $l = strlen($value);
            if ($l > $length) $value = substr($value, 0, $length);
        }
    

        // Entferne min und max Regeln
        $rules = preg_replace('/\b(min|max)=[^|]+/', '', $rules);
            

        // Basierend auf den Regeln $value filtern
        if (!empty($rules)) {
            $rulesArray = explode('|', $rules);
    
            foreach ($rulesArray as $rule) {
                // Regel aufteilen, um zusätzliche Zeichen zu berücksichtigen
                $additionalChars = '';
                if (strpos($rule, '+') !== false) {
                    $parts = explode('+', $rule);
                    $rule = $parts[0];
                    $additionalChars = isset($parts[1]) ? preg_quote($parts[1], '/') : '';
                }
    
                // Hole den Regex für die Regel
                $regex = self::REGEX[$rule] ?? null;
    
                if ($regex) {
                    // Erlaube zusätzliche Zeichen
                    if ($additionalChars) {
                        $regex = substr($regex, 0, -1) . preg_quote($additionalChars, '/') . ']$/';
                    }
    
                    // Filtere den Wert
                    if (preg_match_all($regex, $value, $matches)) {
                        $value = implode('', $matches[0]);
                    } else {
                        // Keine gültigen Zeichen gefunden
                        $value = '';
                    }
                }
            }
        }
    
        // Rückgabe des bereinigten Wertes
        return $value;
    }
    

    public function get_rule_val($rules,$rule='') {
      $val=''; $chk='=';
      if (is_array($rules)) {
        foreach ($rules as $no => $rulex) {
            if ($rule=='rule') {
               if (!strpos($rulex,'=')) return $rulex; 
            }

            if (strpos($rulex,$chk) > 0) {
               $parts=explode($chk, $rulex);
               if ($parts[0]==$rule) return $parts[1];
            } 
        } 
      }  
      return $val;
    }
}
