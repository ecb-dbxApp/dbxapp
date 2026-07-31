<?php
require_once dirname(__DIR__) . '/include/dbxSelfTestRunner.class.php';

use dbx\dbxSelfTest\dbxSelfTestRunner;

function selftest_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function selftest_remove_tree(string $path): void {
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) rmdir($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($path);
}

$fixture = sys_get_temp_dir() . '/dbx-selftest-runner-' . bin2hex(random_bytes(4));
$testDir = $fixture . '/dbx/modules/demo/tests';
$logDir = $fixture . '/files/sys/selftest';
mkdir($testDir, 0777, true);
mkdir($logDir, 0777, true);
file_put_contents(
    $testDir . '/demo_test.php',
    "<?php if (getenv('DBX_SELFTEST') !== '1') { fwrite(STDERR, 'FAIL missing test context'); exit(9); } echo 'PASS demo';\n"
);
file_put_contents($testDir . '/demo_test.js', "console.log('PASS browser demo');\n");

try {
    $runner = new dbxSelfTestRunner($fixture, $logDir);
    $runnerSource = (string)file_get_contents(dirname(__DIR__) . '/include/dbxSelfTestRunner.class.php');
    $controllerSource = (string)file_get_contents(dirname(__DIR__) . '/include/dbxSelfTestController.class.php');
    $dashboardSource = (string)file_get_contents(dirname(__DIR__) . '/tpl/js/selftest.js');
    selftest_assert(str_contains($runnerSource, "array(\$php, '-n', '-l', \$file)"), 'PHP-Syntaxchecks muessen ohne php.ini-Startkosten laufen.');
    selftest_assert(str_contains($controllerSource, '@set_time_limit(360)'), 'Lange Web-Systemtests brauchen ein kontrolliertes Request-Zeitlimit.');
    selftest_assert(str_contains($dashboardSource, 'runningTestId') && str_contains($dashboardSource, 'response.text()'), 'Das Dashboard muss Aktivitaet und nicht-JSON Serverfehler aussagekraeftig behandeln.');
    $startFunction = strpos($dashboardSource, 'async function startRun(profile, ids)');
    $busyGuard = $startFunction === false ? false : strpos($dashboardSource, 'if (state.busy) return;', $startFunction);
    $busyLock = $busyGuard === false ? false : strpos($dashboardSource, 'setBusy(true);', $busyGuard);
    $startRequest = $busyLock === false ? false : strpos($dashboardSource, 'request(urls.start', $busyLock);
    selftest_assert(
        $startFunction !== false && $busyGuard !== false && $busyLock !== false && $startRequest !== false && $busyLock < $startRequest,
        'Startschaltflaechen muessen vor dem ersten Request gegen Doppelklicks gesperrt werden.'
    );
    $phpResolver = new ReflectionMethod($runner, 'resolvePhpCliBinary');
    $phpCli = $phpResolver->invoke($runner);
    selftest_assert(is_string($phpCli) && is_file($phpCli), 'Ein echter PHP-CLI-Interpreter muss aufgeloest werden.');
    selftest_assert((bool)preg_match('/^php(?:\.exe)?$/i', basename($phpCli)), 'Apache/httpd darf nicht als PHP-CLI verwendet werden.');
    $catalog = $runner->catalog('full');
    $demo = array_values(array_filter($catalog, static fn(array $test): bool => ($test['relative_path'] ?? '') === 'dbx/modules/demo/tests/demo_test.php'));
    $browserDemo = array_values(array_filter($catalog, static fn(array $test): bool => ($test['relative_path'] ?? '') === 'dbx/modules/demo/tests/demo_test.js'));
    selftest_assert(count($catalog) >= 8, 'Systemtests und Fixture-Test muessen entdeckt werden.');
    selftest_assert(count($demo) === 1, 'PHP-Test muss genau einmal im Katalog stehen.');
    selftest_assert(count($browserDemo) === 1 && $browserDemo[0]['execution'] === 'browser', 'JavaScript-Test muss fuer den Browser katalogisiert werden.');

    $run = $runner->startRun('full', array($demo[0]['id']), 'single');
    $result = $runner->executeRunTest($run['id'], $demo[0]['id']);
    selftest_assert($result['status'] === 'passed', 'Einzeltest muss isoliert erfolgreich laufen.');
    $finished = $runner->finishRun($run['id']);
    selftest_assert($finished['status'] === 'passed', 'Abgeschlossener Lauf muss bestanden sein.');
    selftest_assert(($finished['totals']['passed'] ?? 0) === 1, 'Summen muessen das Ergebnis enthalten.');
    selftest_assert(is_file((string)$runner->runLogPath($run['id'])), 'JSON-Protokoll muss geschrieben werden.');
    selftest_assert($runner->loadRun('../ungueltig') === null, 'Ungueltige Lauf-IDs duerfen nicht aufgeloest werden.');

    $browserRun = $runner->startRun('full', array($browserDemo[0]['id']), 'single');
    $browserResult = $runner->recordBrowserTestResult($browserRun['id'], $browserDemo[0]['id'], array(
        'status' => 'passed',
        'output' => 'PASS browser demo',
        'duration_ms' => 12,
    ));
    selftest_assert($browserResult['status'] === 'passed' && $browserResult['execution'] === 'browser', 'Browserergebnis muss validiert protokolliert werden.');
    selftest_assert($runner->finishRun($browserRun['id'])['status'] === 'passed', 'Browser-Testlauf muss bestanden sein.');

    $staleRun = $runner->startRun('full', array($demo[0]['id']), 'single');
    $stalePath = (string)$runner->runLogPath($staleRun['id']);
    touch($stalePath, time() - 601);
    clearstatcache(true, $stalePath);
    $staleHistory = array_values(array_filter(
        $runner->history(20),
        static fn(array $item): bool => ($item['id'] ?? '') === $staleRun['id']
    ));
    selftest_assert(($staleHistory[0]['display_status'] ?? '') === 'interrupted', 'Verwaiste Weblaeufe muessen als unterbrochen erscheinen.');
    selftest_assert(($runner->loadRun($staleRun['id'])['status'] ?? '') === 'running', 'Unterbrochene Laeufe muessen fortsetzbar bleiben.');
} finally {
    selftest_remove_tree($fixture);
}

echo "PASS: dbxSelfTest entdeckt, isoliert, protokolliert und validiert Testlaeufe.\n";
