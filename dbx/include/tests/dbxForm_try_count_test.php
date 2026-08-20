<?php

class dbxObj {
}

class dbxFormTryCountTpl {
    public function replaces($content, $data) {
        foreach ((array) $data as $key => $value) {
            $content = str_replace('{' . $key . '}', (string) $value, (string) $content);
        }

        return $content;
    }

    public function get_tpl($tpl, $data = '', $type = 'htm', $i = 0) {
        $data = is_array($data) ? $data : array();
        return '<div class="alert alert-danger">' . ($data['msg'] ?? '') . '</div>';
    }
}

class dbxFormTryCountApi {
    public $now = 1000.0;
    public $tpl;

    public function __construct() {
        $this->tpl = new dbxFormTryCountTpl();
    }

    public function get_system_obj($name) {
        return $name === 'dbxTPL' ? $this->tpl : new stdClass();
    }

    public function get_self_url() {
        return '/try-count-test';
    }
}

$dbx_form_try_count_api = new dbxFormTryCountApi();

function dbx() {
    global $dbx_form_try_count_api;
    return $dbx_form_try_count_api;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dbxForm.class.php';

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

class dbxFormTryCountForm extends dbxForm {
    protected function current_time(): float {
        global $dbx_form_try_count_api;
        return $dbx_form_try_count_api->now;
    }
}

$form = new dbxFormTryCountForm();
$form->_try_max = 5;
$form->_try_reset = 6;
$form->_try_count_reset = 600;
$form->_try_msg = 'Noch {sec} Sekunden gesperrt.';
$form->_sys['_try_sys'] = array(
    'dbx_try_ip' => '127.0.0.1',
    'dbx_try_count' => 5,
    'dbx_try_lock' => 3,
    'dbx_try_first' => 100,
    'dbx_try_last' => 399,
    'dbx_try_stop' => 399,
    'dbx_try_run' => 2000,
);

$content = $form->check_try_count(false, false, 1);
$sys = $form->_sys['_try_sys'];

if ($content !== ''
    || (int) ($sys['dbx_try_count'] ?? -1) !== 0
    || (int) ($sys['dbx_try_lock'] ?? -1) !== 0
    || isset($sys['dbx_try_last'], $sys['dbx_try_stop'], $sys['dbx_try_run'])) {
    fwrite(STDERR, "Try-Zaehler wurde nach mehr als 600 Sekunden ohne Fehlversuch nicht zurueckgesetzt.\n");
    exit(1);
}

$form->_sys['_try_sys'] = array(
    'dbx_try_ip' => '127.0.0.1',
    'dbx_try_count' => 5,
    'dbx_try_lock' => 1,
    'dbx_try_first' => 500,
    'dbx_try_last' => 500,
    'dbx_try_stop' => 994,
    'dbx_try_run' => 1006,
);

$content = $form->check_try_count(false, false, 1);

if (strpos($content, 'Noch 6 Sekunden gesperrt.') === false
    || (int) ($form->_sys['_try_sys']['dbx_try_count'] ?? 0) !== 5) {
    fwrite(STDERR, "Eine aktive Try-Sperre wurde vor Ablauf des 600-Sekunden-Fensters aufgehoben.\n");
    exit(1);
}

echo "OK: dbxForm Try-Zaehler wird nach 600 Sekunden Inaktivitaet neu gestartet.\n";
