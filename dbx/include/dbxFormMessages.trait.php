<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxFormMessagesTrait
{
/**
     * Setzt die Formular-Info-Meldung.
     *
     * @param string $msg Meldung
     *
     * @return void
     */
    public function set_msg_info($msg) {
        $this->_msg_info = $msg;
    }

/**
     * Setzt die Formular-Erfolgsmeldung.
     *
     * @param string $msg Meldung
     *
     * @return void
     */
    public function set_msg_ok($msg) {
        $this->_msg_success = $msg;
    }

/**
     * Setzt die Formular-Fehlermeldung.
     *
     * @param string $msg Meldung
     *
     * @return void
     */
    public function set_msg_error($msg) {
        $this->_msg_error = $msg;
    }

/**
     * Setzt eine aktive allgemeine Formular-Fehlermeldung.
     *
     * Im Gegensatz zu set_msg_error(), das den Standardtext für einen später
     * erkannten Fehler festlegt, markiert diese Methode das Formular sofort
     * als fehlerhaft. Controller müssen dafür keine internen Statusfelder
     * von dbxForm kennen oder verändern.
     *
     * @param string $msg Anzuzeigende Fehlermeldung.
     *
     * @return void
     */
    public function set_error($msg) {
        $this->_msg_error = $msg;
        $this->_general_error = $msg;
    }

/**
     * Setzt die Formular-Warnmeldung.
     *
     * @param string $msg Meldung
     *
     * @return void
     */
    public function set_msg_warning($msg) {
        $this->_msg_warning = $msg;
    }

/**
     * Liefert eine Meldung aus der aktuell geladenen FD.
     *
     * @param string $key Meldungsschlüssel
     * @param string $default Rückgabewert, wenn der Schlüssel nicht existiert
     *
     * @return string
     */
    public function get_fd_message(string $key, string $default = ''): string {
        if (!array_key_exists($key, $this->_messages)) {
            return $default;
        }

        $message = $this->_messages[$key];

        return is_scalar($message) ? (string)$message : $default;
    }

/**
     * Liefert eine FD-Meldung und ersetzt benannte Platzhalter.
     *
     * Dadurch bleiben auch dynamische Formular- und Reporttexte vollständig
     * in der sprachabhängigen FD. Ein Modul übergibt nur noch die Werte:
     * `format_fd_message('delete_question', array('id' => $rid))` ersetzt
     * beispielsweise `{id}`. dbxReport erbt diese Funktion von dbxForm.
     *
     * @param string $key Meldungsschlüssel aus `$messages`
     * @param array $values Platzhalterwerte ohne Klammern
     * @param string $default Rückgabe bei unbekanntem Meldungsschlüssel
     *
     * @return string Lokalisierte und formatierte Meldung
     */
    public function format_fd_message(
        string $key,
        array $values = array(),
        string $default = ''
    ): string {
        $message = $this->get_fd_message($key, $default);
        if ($message === '' || $values === array()) {
            return $message;
        }

        $replacements = array();
        foreach ($values as $placeholder => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $replacements['{' . trim((string)$placeholder, '{}') . '}'] =
                (string)$value;
        }

        return strtr($message, $replacements);
    }

/**
     * Lädt ausschließlich den Meldungsvertrag einer sprachabhängigen FD.
     *
     * Verwendung
     * ----------
     * Formulare laden ihre FD normalerweise beim ersten `add_fld()` oder über
     * `add_flds()`. Für Modul-, Listen- und Bestätigungstexte werden Meldungen
     * jedoch teilweise bereits davor benötigt. Diese Methode nutzt denselben
     * sprachabhängigen Resolver und denselben Cache, ohne Felder zu rendern.
     *
     * dbxReport erbt die Methode. Damit können auch Tabellenköpfe, Footer und
     * Reportmeldungen aus der Selection-FD stammen, ohne die Filterfelder ein
     * zweites Mal anzulegen.
     *
     * @param string $fd FD in der Form `modul|name`; leer verwendet `_fd`
     *
     * @return array Geladene und normalisierte Meldungen
     */
    public function load_fd_messages(string $fd = ''): array {
        $source = trim($fd);
        if ($source === '') {
            $source = trim((string)$this->_fd);
        }
        if ($source === '') {
            return $this->_messages;
        }
        if (strpos($source, 'fd:') !== 0) {
            $source = 'fd:' . $source;
        }

        $this->get_dd_fields_source($source);

        return $this->_messages;
    }

/**
     * Übernimmt und normalisiert die Meldungen einer geladenen FD.
     *
     * Später geladene FD-Werte überschreiben gleichnamige Werte. Dadurch gilt
     * bei zusammengesetzten Formularen dieselbe Priorität wie bei den
     * Felddefinitionen.
     *
     * @param array $messages Meldungen aus der FD-Datei
     *
     * @return void
     */
    private function apply_fd_messages(array $messages): void {
        $this->_messages = array_merge($this->_messages, $messages);
    }
}
