<?php

declare(strict_types=1);

/**
 * Ermittelt und validiert einen Formularwert ohne Rendering-/DB-Zustand.
 *
 * Die Komponente bündelt die Priorität Request > Sysdata > Record > Default
 * und liefert neben dem Wert auch Herkunft und strukturiertes
 * Validator-Ergebnis. dbxForm bleibt für Speicherung und Fehlermarkierung
 * verantwortlich.
 */
class dbxFormValueResolver
{
    public function __construct(private object $validator) {}

    /**
     * @param string $name Feldname
     * @param mixed $default Standardwert
     * @param string $rules Validierungsregeln
     * @param array $data Werte des geladenen Datensatzes
     * @param array $system Formularwerte des Laufzeitkontexts
     * @param array $post POST-Werte
     * @param array $query GET-Werte
     * @param bool $useRequest Requestwerte berücksichtigen
     * @param bool $fallbackToState Datensatz- und Systemwerte berücksichtigen
     * @return array{value:mixed,origin:string,ok:int,validation:array}
     */
    public function resolve(
        string $name,
        mixed $default,
        string $rules,
        array $data,
        array $system,
        array $post,
        array $query,
        bool $useRequest,
        bool $fallbackToState
    ): array {
        $value = $default;
        $origin = 'default';
        if ($fallbackToState && array_key_exists($name, $data)) {
            $value = $data[$name];
            $origin = 'data';
        }
        if ($fallbackToState && array_key_exists($name, $system)) {
            $value = $system[$name];
            $origin = 'sys';
        }
        if ($useRequest && array_key_exists($name, $post)) {
            $value = $post[$name];
            $origin = 'post';
        } elseif ($useRequest && array_key_exists($name, $query)) {
            $value = $query[$name];
            $origin = 'get';
        }
        if ($value === null) $value = '';

        $validation = array();
        $valid = true;
        if ($rules !== '') {
            $validation = $this->validator->validateResult($value, $rules, $name);
            $valid = (bool)($validation['valid'] ?? false);
            if ($valid && preg_match('/(?:^|\|)trim(?:\||$)/', $rules)) {
                $value = $validation['normalized'] ?? $value;
            }
        }
        return array(
            'value' => $value,
            'origin' => $origin,
            'ok' => $valid ? 1 : 0,
            'validation' => $validation,
        );
    }
}
