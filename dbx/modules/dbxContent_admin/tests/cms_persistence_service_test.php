<?php
declare(strict_types=1);

/**
 * Prueft die zentrale CMS-Persistenzgrenze: Commit bei Erfolg, Rollback bei
 * Arbeits- oder Commitfehlern, dbxDB-Abstraktion und Controller-Delegation.
 */

final class CmsPersistenceTestLog {
    public array $messages = array();
    public function sys_msg(...$args): void { $this->messages[] = $args; }
}

$testLog = new CmsPersistenceTestLog();
if (!function_exists('dbx')) {
    function dbx(): CmsPersistenceTestLog {
        global $testLog;
        return $testLog;
    }
}

final class CmsPersistenceFakeDb {
    public array $calls = array();
    public int $beginResult = 1;
    public int $commitResult = 1;
    public function begin(string $dd): int { $this->calls[] = array('begin', $dd); return $this->beginResult; }
    public function commit(string $dd): int { $this->calls[] = array('commit', $dd); return $this->commitResult; }
    public function rollback(string $dd): int { $this->calls[] = array('rollback', $dd); return 1; }
}

$base = dirname(__DIR__, 4);
require_once $base . '/dbx/include/tests/dbxModuleSourceBundle.php';
$serviceFile = $base . '/dbx/modules/dbxContent_admin/include/dbxContentCmsPersistenceService.class.php';
$controllerFile = $base . '/dbx/modules/dbxContent_admin/include/dbxContent_cms.class.php';
require_once $serviceFile;

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$class = new ReflectionClass(\dbx\dbxContent_admin\dbxContentCmsPersistenceService::class);
$transaction = $class->getMethod('transaction');
$transaction->setAccessible(true);

$db = new CmsPersistenceFakeDb();
$service = $class->newInstance($db);
$value = $transaction->invoke($service, 'content', 'test_success', 'success', static fn(): string => 'stored');
$assert($value === 'stored', 'Der Transaktionswert wird bei Erfolg nicht weitergegeben.');
$assert($db->calls === array(array('begin', 'content'), array('commit', 'content')), 'Erfolg fuehrt nicht exakt zu Begin und Commit.');

$db = new CmsPersistenceFakeDb();
$service = $class->newInstance($db);
try {
    $transaction->invoke($service, 'content', 'test_work_error', 'work', static function(): void {
        throw new RuntimeException('controlled work failure');
    });
    $assert(false, 'Ein Arbeitsfehler wurde verschluckt.');
} catch (Throwable $e) {
    $assert(str_contains($e->getMessage(), 'controlled work failure'), 'Der urspruengliche Arbeitsfehler geht verloren.');
}
$assert($db->calls === array(array('begin', 'content'), array('rollback', 'content')), 'Arbeitsfehler loest keinen Rollback aus.');

$db = new CmsPersistenceFakeDb();
$db->commitResult = 0;
$service = $class->newInstance($db);
try {
    $transaction->invoke($service, 'folder', 'test_commit_error', 'commit', static fn(): int => 1);
    $assert(false, 'Ein fehlgeschlagener Commit wurde als Erfolg behandelt.');
} catch (Throwable $e) {
    $assert(str_contains($e->getMessage(), 'commit failed'), 'Der Commitfehler ist nicht lokalisierbar.');
}
$assert(
    $db->calls === array(array('begin', 'folder'), array('commit', 'folder'), array('rollback', 'folder')),
    'Ein Commitfehler loest keinen Rollback aus.'
);

$source = (string)file_get_contents($serviceFile);
$controller = dbx_test_module_source_bundle($controllerFile);
foreach (array('savePage', 'createPage', 'duplicatePage', 'saveFolder', 'createFolder', 'deletePage', 'deleteFolder', 'moveNode') as $method) {
    $assert(str_contains($source, 'public function ' . $method . '('), 'Persistenzmethode fehlt: ' . $method);
}
$assert(substr_count($source, '$this->transaction(') >= 7, 'Nicht alle schreibenden CMS-Ablaufe verwenden die gemeinsame Transaktion.');
$assert(
    !preg_match('/\bnew\s+(?:\\\\)?(?:PDO|mysqli|SQLite3)\b|\bPDO::|\bmysqli_/', $source),
    'Der Service umgeht dbxDB mit einem direkten Datenbankzugriff.'
);
$assert(
    str_contains($controller, '$this->persistence($db)->savePage(')
        && str_contains($controller, '$this->persistence($db)->saveFolder(')
        && str_contains($controller, '$this->persistence($db)->moveNode('),
    'Der CMS-Controller delegiert Kernmutationen nicht vollstaendig an den Persistenzservice.'
);
$assert(count($testLog->messages) === 2, 'Fehlerpfade werden nicht genau einmal im Systemlog lokalisiert.');

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK CMS persistence: commit, rollback, dbxDB boundary and delegation\n";
