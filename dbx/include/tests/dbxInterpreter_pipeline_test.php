<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/dbxRequestContext.class.php';

final class dbxInterpreterTestModule
{
    public function __construct(private string $name) {}

    public function run(string $action): string
    {
        if ($this->name === 'Nested') {
            return '[modul=Probe]dbx_run1=inner[/modul]';
        }
        $tpl = dbx()->get_modul_var('tpl', '');
        return '<b>' . $this->name . ':' . $action . ($tpl !== '' ? ':' . $tpl : '') . '</b>';
    }
}

final class dbxInterpreterTestApi
{
    private dbxRequestContext $context;
    private int $moduleId = 0;

    public function __construct() { $this->context = new dbxRequestContext(); }
    public function request_context(): dbxRequestContext { return $this->context; }
    public function get_system_var(string $name, mixed $default = null): mixed { return $this->context->system($name, $default); }
    public function set_system_var(string $name, mixed $value): void { $this->context->setSystem($name, $value); }
    public function get_modul_obj(string $module): object {
        $this->set_system_var('dbx_activ_modul_id', ++$this->moduleId);
        $this->set_system_var('dbx_activ_modul', $module);
        return new dbxInterpreterTestModule($module);
    }
    public function set_modul_var(string $name, mixed $value, bool $checkProtected = true): void {
        $module = (string)$this->get_system_var('dbx_activ_modul', '');
        $id = (int)$this->get_system_var('dbx_activ_modul_id', 0);
        $protected = $this->context->module($id, $module, 'dbx_protected_modulvars', array());
        if ($checkProtected && is_array($protected) && array_key_exists($name, $protected)) return;
        $this->context->setModule($id, $module, $name, $value);
    }
    public function get_modul_var(string $name, mixed $default = null): mixed {
        $module = (string)$this->get_system_var('dbx_activ_modul', '');
        $id = (int)$this->get_system_var('dbx_activ_modul_id', 0);
        return $this->context->module($id, $module, $name, $default);
    }
    public function get_request_var(string $name, mixed $default = null): mixed { return $default; }
    public function can_modul(string $module): bool { return true; }
    public function user(string $key = ''): int { return 2; }
    public function run_owner(object $owner, string $method, mixed ...$args): mixed { return $owner->{$method}(...$args); }
    public function timer(string $name, string $info = ''): void {}
    public function get_remember_var(string $name, mixed $default = null): mixed { return $default; }
}

function dbx(): dbxInterpreterTestApi
{
    static $api;
    return $api ??= new dbxInterpreterTestApi();
}

require_once dirname(__DIR__) . '/dbxInterpreter.class.php';

dbx()->set_system_var('dbx_modul', 'Original');
dbx()->set_system_var('dbx_run1', 'original');
$interpreter = new dbxInterpreter();
$input = '[modul=Probe]dbx_run1=one&tpl=first[/modul]|[modul=Probe]dbx_run1=two&tpl=second[/modul]|[modul=Nested]dbx_run1=start[/modul]';
$output = $interpreter->run($input);

if ($output !== '<b>Probe:one:first</b>|<b>Probe:two:second</b>|<b>Probe:inner</b>') {
    fwrite(STDERR, "FAIL Interpreter-Pipeline: {$output}\n");
    exit(1);
}
if (dbx()->get_system_var('dbx_modul') !== 'Original' || dbx()->get_system_var('dbx_run1') !== 'original') {
    fwrite(STDERR, "FAIL Interpreter hat den äußeren RequestContext verändert.\n");
    exit(1);
}

echo "OK Interpreter ersetzt parallele/rekursive Marker und restauriert den Kontext.\n";
