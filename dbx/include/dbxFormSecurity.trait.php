<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxFormSecurityTrait
{
private function get_token(): string {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return hash('sha256', uniqid('', true) . '|' . mt_rand());
        }
    }

private function secure_fld_name(): string {
        return '_' . (string)$this->_fid;
    }

private function secure_token(string $fld): string {
        if (!isset($this->_sys['_csrf']) || !is_array($this->_sys['_csrf'])) {
            $this->_sys['_csrf'] = array();
        }

        $token = (string)($this->_sys['_csrf'][$fld] ?? $this->_sys[$fld] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = $this->get_token();
        }

        $this->_sys['_csrf'][$fld] = $token;
        $this->_sys[$fld] = $token;
        $this->_data[$fld] = $token;

        return $token;
    }

private function sync_secure_field(string $fld, string $token): void {
        $this->_sys['_csrf'][$fld] = $token;
        $this->_sys[$fld] = $token;
        $this->_data[$fld] = $token;

        if (isset($this->_flds[$fld])) {
            $this->_flds[$fld]['value'] = $token;
            $this->_flds[$fld]['origin'] = 'csrf';
            $this->_flds[$fld]['verify'] = 1;
            $this->_flds[$fld]['error'] = 0;
        }
    }

private function rotate_secure_token(string $fld): void {
        $this->sync_secure_field($fld, $this->get_token());
    }

/**
     * Liefert Feldname und aktuellen Wert des dbxForm-Security-Tokens.
     *
     * JavaScript-gesteuerte Formulare erhalten nach einem JSON-Submit keinen
     * neu gerenderten Formularblock. Sie koennen mit diesen Daten den von
     * evaluate_request() rotierten Token gezielt im bestehenden DOM ersetzen,
     * ohne die private Token-Verwaltung nachzubauen.
     *
     * @return array{name:string,value:string}
     */
    public function get_security_data(): array {
        $fld = $this->secure_fld_name();
        $token = $this->secure_token($fld);

        // AJAX-gesteuerte Formulare rufen nicht zwingend run() auf. Ohne
        // diese Speicherung würde ein hier erstmals erzeugter oder nach
        // submit() rotierter Token nur im aktuellen Objekt existieren und
        // der nächste Request wieder den alten Sessionstand laden.
        $this->store_sysdata();

        return array(
            'name' => $fld,
            'value' => $token,
        );
    }
}
