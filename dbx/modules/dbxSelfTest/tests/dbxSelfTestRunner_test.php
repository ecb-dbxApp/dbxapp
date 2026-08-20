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
$test_dir = $fixture . '/dbx/modules/demo/tests';
$log_dir = $fixture . '/files/sys/selftest';
mkdir($test_dir, 0777, true);
mkdir($log_dir, 0777, true);
file_put_contents(
    $test_dir . '/demo_test.php',
    "<?php\n/** Prüft den kontrollierten Testkontext des isolierten Demo-Tests. */\nif (getenv('DBX_SELFTEST') !== '1') { fwrite(STDERR, 'FAIL missing test context'); exit(9); } echo 'PASS demo';\n"
);
file_put_contents($test_dir . '/demo_test.js', "console.log('PASS browser demo');\n");

try {
    $runner = new dbxSelfTestRunner($fixture, $log_dir);
    $runner_source = (string)file_get_contents(dirname(__DIR__) . '/include/dbxSelfTestRunner.class.php');
    $controller_source = (string)file_get_contents(dirname(__DIR__) . '/include/dbxSelfTestController.class.php');
    $dashboard_source = (string)file_get_contents(dirname(__DIR__) . '/js/selftest.js');
    selftest_assert(
        str_contains($runner_source, 'token_get_all($source, TOKEN_PARSE)')
            && !str_contains($runner_source, "array(\$php, '-n', '-l', \$file)"),
        'PHP-Syntaxchecks muessen ohne wiederholte PHP-Prozessstarts laufen.'
    );
    selftest_assert(str_contains($controller_source, '@set_time_limit(360)'), 'Lange Web-Systemtests brauchen ein kontrolliertes Request-Zeitlimit.');
    selftest_assert(str_contains($dashboard_source, 'updateRowStatus(id, "running")') && str_contains($dashboard_source, 'response.text()'), 'Das Dashboard muss Aktivitaet und nicht-JSON Serverfehler aussagekraeftig behandeln.');
    $row_name_source = (string)file_get_contents(dirname(__DIR__) . '/tpl/htm/selftest-run-name.htm');
    selftest_assert(
        !str_contains($dashboard_source, '.title = description;')
            && !str_contains($dashboard_source, 'dataset.tooltip')
            && str_contains($row_name_source, 'data-dbx-tooltip="{tooltip_html}"')
            && str_contains($controller_source, "\$tooltip_html = '<strong>' . \$this->h(\$row['name']) . '</strong>';"),
        'SelfTest-Testname muss ausschliesslich den zentralen dbx-Tooltip nutzen.'
    );
    $start_function = strpos($dashboard_source, 'async function startRun(profile, ids)');
    $busy_guard = $start_function === false ? false : strpos($dashboard_source, 'if (state.busy) return;', $start_function);
    $busy_lock = $busy_guard === false ? false : strpos($dashboard_source, 'setBusy(true);', $busy_guard);
    $start_request = $busy_lock === false ? false : strpos($dashboard_source, 'request(urls.start', $busy_lock);
    selftest_assert(
        $start_function !== false && $busy_guard !== false && $busy_lock !== false && $start_request !== false && $busy_lock < $start_request,
        'Startschaltflaechen muessen vor dem ersten Request gegen Doppelklicks gesperrt werden.'
    );
    $php_resolver = new ReflectionMethod($runner, 'resolve_php_cli_binary');
    $php_cli = $php_resolver->invoke($runner);
    selftest_assert(is_string($php_cli) && is_file($php_cli), 'Ein echter PHP-CLI-Interpreter muss aufgeloest werden.');
    selftest_assert((bool)preg_match('/^php(?:\.exe)?$/i', basename($php_cli)), 'Apache/httpd darf nicht als PHP-CLI verwendet werden.');
    $catalog = $runner->catalog('full');
    $demo = array_values(array_filter($catalog, static fn(array $test): bool => ($test['relative_path'] ?? '') === 'dbx/modules/demo/tests/demo_test.php'));
    $browser_demo = array_values(array_filter($catalog, static fn(array $test): bool => ($test['relative_path'] ?? '') === 'dbx/modules/demo/tests/demo_test.js'));
    selftest_assert(count($catalog) >= 8, 'Systemtests und Fixture-Test muessen entdeckt werden.');
    selftest_assert(count($demo) === 1, 'PHP-Test muss genau einmal im Katalog stehen.');
    selftest_assert(count($browser_demo) === 1 && $browser_demo[0]['execution'] === 'browser', 'JavaScript-Test muss fuer den Browser katalogisiert werden.');
    selftest_assert(($demo[0]['isolation'] ?? '') === 'process', 'PHP-Test braucht explizite Prozessisolation.');
    selftest_assert(
        ($demo[0]['description'] ?? '') === 'Prüft den kontrollierten Testkontext des isolierten Demo-Tests.',
        'Der einleitende Testkommentar muss als konkrete UI-Erklärung erscheinen.'
    );
    selftest_assert(array_key_exists('resources', $demo[0]), 'Testressourcen muessen im Katalog dokumentiert sein.');
    selftest_assert(
        count(array_filter($catalog, static fn(array $test): bool => trim((string)($test['description'] ?? '')) === '')) === 0,
        'Jeder Test im Katalog braucht eine aussagekraeftige Beschreibung.'
    );

    $run = $runner->start_run('full', array($demo[0]['id']), 'single');
    $result = $runner->execute_run_test($run['id'], $demo[0]['id']);
    selftest_assert($result['status'] === 'passed', 'Einzeltest muss isoliert erfolgreich laufen.');
    $finished = $runner->finish_run($run['id']);
    selftest_assert($finished['status'] === 'passed', 'Abgeschlossener Lauf muss bestanden sein.');
    selftest_assert(($finished['totals']['passed'] ?? 0) === 1, 'Summen muessen das Ergebnis enthalten.');
    selftest_assert(is_file((string)$runner->run_log_path($run['id'])), 'JSON-Protokoll muss geschrieben werden.');
    selftest_assert($runner->load_run('../ungueltig') === null, 'Ungueltige Lauf-IDs duerfen nicht aufgeloest werden.');

    $browser_run = $runner->start_run('full', array($browser_demo[0]['id']), 'single');
    $browser_result = $runner->record_browser_test_result($browser_run['id'], $browser_demo[0]['id'], array(
        'status' => 'passed',
        'output' => 'PASS browser demo',
        'duration_ms' => 12,
    ));
    selftest_assert($browser_result['status'] === 'passed' && $browser_result['execution'] === 'browser', 'Browserergebnis muss validiert protokolliert werden.');
    selftest_assert($runner->finish_run($browser_run['id'])['status'] === 'passed', 'Browser-Testlauf muss bestanden sein.');

    $stale_run = $runner->start_run('full', array($demo[0]['id']), 'single');
    $stale_path = (string)$runner->run_log_path($stale_run['id']);
    touch($stale_path, time() - 601);
    clearstatcache(true, $stale_path);
    $stale_history = array_values(array_filter(
        $runner->history(20),
        static fn(array $item): bool => ($item['id'] ?? '') === $stale_run['id']
    ));
    selftest_assert(($stale_history[0]['display_status'] ?? '') === 'interrupted', 'Verwaiste Weblaeufe muessen als unterbrochen erscheinen.');
    selftest_assert(($runner->load_run($stale_run['id'])['status'] ?? '') === 'running', 'Unterbrochene Laeufe muessen fortsetzbar bleiben.');
} finally {
    selftest_remove_tree($fixture);
}

echo "PASS: dbxSelfTest entdeckt, isoliert, protokolliert und validiert Testlaeufe.\n";
