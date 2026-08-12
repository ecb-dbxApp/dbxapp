<?php
/**
 * Regressionstest für den dbxTPL-Rohcache.
 *
 * Der Test stellt sicher, dass DBX-Systemvariablen nicht in den Requestcache
 * eingebrannt werden. Ein bereits gecachtes Template muss deshalb bei einer
 * Änderung des aktiven Designs im selben Request den neuen Wert ausgeben.
 */

class dbxObj
{
}

class dbxTPLRawCacheTestApi
{
    public string $baseDir = '';

    public array $system = array(
        'dbx_lng' => '',
        'dbx_design' => 'first',
        'dbx_activ_design' => 'first',
        'dbx_edit' => 0,
    );

    public function get_base_dir(): string
    {
        if ($this->baseDir !== '') {
            return $this->baseDir;
        }
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

    public function lng_resolve_file(string $dir, string $name, string $ext, string $lng = '', bool $fallback = true): string
    {
        $dir = str_replace('\\', '/', $dir);
        if ($dir !== '' && substr($dir, -1) !== '/') $dir .= '/';
        $path = $dir . strtolower(trim($name)) . '.' . ltrim(strtolower(trim($ext)), '.');
        return is_file($path) ? $this->os_path($path) : '';
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

$_SESSION = array();

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dbxTPL.class.php';

$tpl = new dbxTPL();
$first = $tpl->get_tpl('dbx|test-dbx-runtime-cache', array());

if (trim($first) !== 'first') {
    fwrite(STDERR, "FAIL: Erster DBX-Systemwert wurde nicht eingesetzt.\n");
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

$tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dbx-tpl-cache-' . bin2hex(random_bytes(6));
$tempTplDir = $tempRoot . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'modules'
    . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm';
if (!mkdir($tempTplDir, 0777, true) && !is_dir($tempTplDir)) {
    fwrite(STDERR, "FAIL: Temporaeres Template-Verzeichnis konnte nicht angelegt werden.\n");
    exit(1);
}
$tempTpl = $tempTplDir . DIRECTORY_SEPARATOR . 'test-source-change.htm';
file_put_contents($tempTpl, 'erste Version');
dbx()->baseDir = $tempRoot . DIRECTORY_SEPARATOR;
$sourceFirst = $tpl->get_tpl('dbx|test-source-change', array());
file_put_contents($tempTpl, 'zweite, laengere Version');
clearstatcache(true, $tempTpl);
$tpl->clear_raw_cache();
$sourceSecond = $tpl->get_tpl('dbx|test-source-change', array());

@unlink($tempTpl);
@rmdir($tempTplDir);
@rmdir(dirname($tempTplDir));
@rmdir(dirname(dirname($tempTplDir)));
@rmdir(dirname(dirname(dirname($tempTplDir))));
@rmdir(dirname(dirname(dirname(dirname($tempTplDir)))));
@rmdir($tempRoot);

if ($sourceFirst !== 'erste Version' || $sourceSecond !== 'zweite, laengere Version') {
    fwrite(STDERR, "FAIL: Geaenderte Template-Datei blieb im Requestcache veraltet.\n");
    exit(1);
}

echo "OK: dbxTPL cached rohe Templates requestlokal, aktualisiert geaenderte Quellen und setzt DBX-Systemwerte bei jeder Ausgabe neu ein.\n";
