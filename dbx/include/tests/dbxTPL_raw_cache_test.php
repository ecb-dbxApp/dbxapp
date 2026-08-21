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
    public string $base_dir = '';
    private ?dbxInertCode $inert_code = null;

    public array $system = array(
        'dbx_lng' => '',
        'dbx_design' => 'first',
        'dbx_activ_design' => 'first',
        'dbx_edit' => 0,
    );

    public function get_base_dir(): string
    {
        if ($this->base_dir !== '') {
            return $this->base_dir;
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

    public function get_system_obj(string $class): object
    {
        if ($class === 'dbxInertCode') {
            return $this->inert_code ??= new dbxInertCode();
        }
        return $this;
    }

    public function get_assets(string $type): array
    {
        return array();
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

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dbxInertCode.class.php';
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

$temp_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dbx-tpl-cache-' . bin2hex(random_bytes(6));
$temp_tpl_dir = $temp_root . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'modules'
    . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm';
if (!mkdir($temp_tpl_dir, 0777, true) && !is_dir($temp_tpl_dir)) {
    fwrite(STDERR, "FAIL: Temporaeres Template-Verzeichnis konnte nicht angelegt werden.\n");
    exit(1);
}
$temp_tpl = $temp_tpl_dir . DIRECTORY_SEPARATOR . 'test-source-change.htm';
file_put_contents($temp_tpl, 'erste Version');
dbx()->base_dir = $temp_root . DIRECTORY_SEPARATOR;
$source_first = $tpl->get_tpl('dbx|test-source-change', array());
file_put_contents($temp_tpl, 'zweite, laengere Version');
clearstatcache(true, $temp_tpl);
$tpl->clear_raw_cache();
$source_second = $tpl->get_tpl('dbx|test-source-change', array());

$partial_tpl = $temp_tpl_dir . DIRECTORY_SEPARATOR . 'test-inert-partial.htm';
$source_inert_tpl = $temp_tpl_dir . DIRECTORY_SEPARATOR . 'test-inert-source.htm';
$data_inert_tpl = $temp_tpl_dir . DIRECTORY_SEPARATOR . 'test-inert-data.htm';
$textarea_tpl = $temp_tpl_dir . DIRECTORY_SEPARATOR . 'test-inert-textarea.htm';
$literal_code = '<code>{dbx:version}[tpl=dbx|missing-code]</code>'
    . '<pre>[tpl=dbx|missing-pre]</pre>'
    . '<dbx-code>[tpl=dbx|missing-custom]</dbx-code>'
    . '<div class="dbx-code-inert">[tpl=dbx|missing-class]</div>'
    . '<div data-dbx-inert>[tpl=dbx|missing-attribute]</div>'
    . '<div data-dbx-inert><code>[tpl=dbx|missing-nested]</code></div>'
    . "\n```html\n[tpl=dbx|missing-markdown]\n```\n";
file_put_contents($partial_tpl, 'ACTIVE');
file_put_contents($source_inert_tpl, $literal_code . '|[tpl=dbx|test-inert-partial]');
file_put_contents($data_inert_tpl, '<section>{obj:content}</section>|[tpl=dbx|test-inert-partial]');
file_put_contents($textarea_tpl, '<textarea id="{name}_{i}">{value}</textarea>|[tpl=dbx|test-inert-partial]');
$tpl->clear_raw_cache();
$source_inert = $tpl->get_tpl('dbx|test-inert-source', array());
$data_inert = $tpl->get_tpl('dbx|test-inert-data', array('obj:content' => $literal_code));
$textarea_inert = $tpl->get_tpl('dbx|test-inert-textarea', array(
    'name' => 'content',
    'i' => 7,
    'value' => '[tpl=dbx|missing-textarea]',
));

@unlink($temp_tpl);
@unlink($partial_tpl);
@unlink($source_inert_tpl);
@unlink($data_inert_tpl);
@unlink($textarea_tpl);
@rmdir($temp_tpl_dir);
@rmdir(dirname($temp_tpl_dir));
@rmdir(dirname(dirname($temp_tpl_dir)));
@rmdir(dirname(dirname(dirname($temp_tpl_dir))));
@rmdir(dirname(dirname(dirname(dirname($temp_tpl_dir)))));
@rmdir($temp_root);

if ($source_first !== 'erste Version' || $source_second !== 'zweite, laengere Version') {
    fwrite(STDERR, "FAIL: Geaenderte Template-Datei blieb im Requestcache veraltet.\n");
    exit(1);
}
if ($source_inert !== $literal_code . '|ACTIVE') {
    fwrite(STDERR, "FAIL: dbxTPL hat Code aus dem Quelltemplate interpretiert: {$source_inert}\n");
    exit(1);
}
if ($data_inert !== '<section>' . $literal_code . '</section>|ACTIVE') {
    fwrite(STDERR, "FAIL: dbxTPL hat eingesetzten CMS-Code interpretiert: {$data_inert}\n");
    exit(1);
}
if ($textarea_inert !== '<textarea id="content_7">[tpl=dbx|missing-textarea]</textarea>|ACTIVE') {
    fwrite(STDERR, "FAIL: Textarea-Attribute wurden nicht ersetzt oder eingesetzter Inhalt interpretiert: {$textarea_inert}\n");
    exit(1);
}

echo "OK: dbxTPL cached rohe Templates requestlokal, aktualisiert geaenderte Quellen und setzt DBX-Systemwerte bei jeder Ausgabe neu ein.\n";
