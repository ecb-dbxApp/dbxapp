<?php
/**
 * Regressionstest für den dbxTPL-Rohcache.
 *
 * Der Test stellt sicher, dass DBX-Systemvariablen nicht in den Session-Cache
 * eingebrannt werden. Ein bereits gecachtes Template muss deshalb bei einer
 * Änderung des aktiven Designs im selben Request den neuen Wert ausgeben.
 */

class dbxObj
{
}

class dbxTPLRawCacheTestApi
{
    public array $system = array(
        'dbx_lng' => '',
        'dbx_design' => 'first',
        'dbx_activ_design' => 'first',
        'dbx_edit' => 0,
    );

    public function get_base_dir(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'dbxTPL' . DIRECTORY_SEPARATOR;
    }

    public function get_system_var(string $name, $default = '')
    {
        return $this->system[$name] ?? $default;
    }

    public function get_request_var(string $name, $default = '')
    {
        return $default;
    }

    public function set_system_var(string $name, $value): void
    {
        $this->system[$name] = $value;
    }

    public function get_base_url(): string
    {
        return 'https://localhost/dbxapp/';
    }

    public function get_skin(): string
    {
        return 'default';
    }

    public function get_skin_css(): string
    {
        return 'default.css';
    }

    public function get_skin_class(): string
    {
        return 'skin-default';
    }

    public function editor_file_path(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    public function os_path(string $path): string
    {
        return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    }

    public function register_editor_file(string $kind, string $path): void
    {
    }
}

function dbx(): dbxTPLRawCacheTestApi
{
    static $api;
    if (!$api) {
        $api = new dbxTPLRawCacheTestApi();
    }
    return $api;
}

$_SESSION = array('dbx' => array('cache' => array('tpl' => array())));

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dbxTPL.class.php';

$tpl = new dbxTPL();
$first = $tpl->get_tpl('dbx|test-dbx-runtime-cache', array());

if (trim($first) !== 'first') {
    fwrite(STDERR, "FAIL: Erster DBX-Systemwert wurde nicht eingesetzt.\n");
    exit(1);
}

$cached = $_SESSION['dbx']['cache']['tpl']['dbx']['test-dbx-runtime-cache']['htm']['']['tpl'] ?? '';
if (strpos($cached, '{dbx:design}') === false) {
    fwrite(STDERR, "FAIL: Template-Cache enthält keinen rohen DBX-Platzhalter.\n");
    exit(1);
}

dbx()->set_system_var('dbx_activ_design', 'second');
$second = $tpl->get_tpl('dbx|test-dbx-runtime-cache', array());

if (trim($second) !== 'second') {
    fwrite(STDERR, "FAIL: Cache-Treffer verwendet einen veralteten DBX-Systemwert.\n");
    exit(1);
}

$design = $tpl->get_design_tpl('test', '1', 'de', 'htm', 0);
if (trim($design) !== 'visible') {
    fwrite(STDERR, "FAIL: Design-Template-Bedingung sieht den aktuellen DBX-Systemwert nicht.\n");
    exit(1);
}

echo "OK: dbxTPL cached rohe Templates und setzt DBX-Systemwerte vor Bedingungen bei jeder Ausgabe neu ein.\n";
