<?php
namespace dbx\dbxSelfTest;

/**
 * Zentraler, auch ohne Web-Kernel nutzbarer Test-Orchestrator.
 *
 * Der Runner entdeckt die vorhandenen Tests, fuehrt jeden Test in einem
 * separaten Prozess aus und protokolliert komplette sowie einzelne Laeufe als
 * JSON unter files/sys/selftest. Test-IDs werden ausschliesslich aus dem
 * serverseitig ermittelten Katalog aufgeloest; Request-Werte gelangen nie in
 * eine Shell-Kommandozeile.
 */
class dbxSelfTestRunner
{
    public const LOG_VERSION = 1;
    private const ACTIVE_RUN_GRACE_SECONDS = 600;

    private string $baseDir;
    private string $logDir;
    private ?array $catalogCache = null;
    private ?string $phpCliBinaryCache = null;
    private int $maxOutputBytes = 131072;

    public function __construct(?string $baseDir = null, ?string $logDir = null)
    {
        $root = $baseDir ?: dirname(__DIR__, 4);
        $real = realpath($root);
        $this->baseDir = rtrim(str_replace('\\', '/', $real !== false ? $real : $root), '/');
        $this->logDir = $logDir ?: $this->baseDir . '/files/sys/selftest';
        $this->ensureDirectory($this->logDir);
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    public function logDir(): string
    {
        return $this->logDir;
    }

    /**
     * Liefert den vollstaendigen oder auf ein Profil reduzierten Testkatalog.
     */
    public function catalog(string $profile = 'full'): array
    {
        if ($this->catalogCache === null) {
            $tests = $this->builtinCatalog();
            $tests = array_merge($tests, $this->discoverFileTests());
            usort($tests, static function (array $a, array $b): int {
                return [$a['category'], $a['name']] <=> [$b['category'], $b['name']];
            });
            $this->catalogCache = $tests;
        }

        $profile = $this->normalizeProfile($profile);
        if ($profile === 'full') {
            return $this->catalogCache;
        }

        return array_values(array_filter(
            $this->catalogCache,
            static fn(array $test): bool => ($test['tier'] ?? 'full') === 'quick'
        ));
    }

    public function catalogById(): array
    {
        $out = array();
        foreach ($this->catalog('full') as $test) {
            $out[(string)$test['id']] = $test;
        }
        return $out;
    }

    /**
     * Startet einen protokollierten Lauf. Ohne IDs wird das Profil verwendet.
     */
    public function startRun(string $profile = 'full', array $testIds = array(), string $mode = 'complete'): array
    {
        $profile = $this->normalizeProfile($profile);
        $catalog = $this->catalogById();
        if ($testIds === array()) {
            $testIds = array_column($this->catalog($profile), 'id');
        }

        $selected = array();
        foreach ($testIds as $id) {
            $id = (string)$id;
            if (isset($catalog[$id]) && !in_array($id, $selected, true)) {
                $selected[] = $id;
            }
        }
        if ($selected === array()) {
            throw new \RuntimeException('Keine gueltigen Tests ausgewaehlt.');
        }

        $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(5));
        $now = $this->now();
        $run = array(
            'version' => self::LOG_VERSION,
            'id' => $id,
            'mode' => in_array($mode, array('complete', 'selection', 'single', 'cli'), true) ? $mode : 'complete',
            'profile' => $profile,
            'status' => 'running',
            'started_at' => $now,
            'finished_at' => null,
            'duration_ms' => 0,
            'test_ids' => $selected,
            'current_test_id' => null,
            'results' => array(),
            'totals' => $this->totals(count($selected)),
            'environment' => array(
                'php' => PHP_VERSION,
                'os' => PHP_OS_FAMILY,
                'sapi' => PHP_SAPI,
                'host' => (string)($_SERVER['HTTP_HOST'] ?? gethostname() ?: ''),
            ),
        );
        $this->writeRun($run);
        return $run;
    }

    /**
     * Fuehrt genau einen zum Lauf gehoerenden Test aus und protokolliert ihn.
     */
    public function executeRunTest(string $runId, string $testId): array
    {
        $run = $this->loadRun($runId);
        if (!$run) {
            throw new \RuntimeException('Testlauf wurde nicht gefunden.');
        }
        if (($run['status'] ?? '') !== 'running') {
            throw new \RuntimeException('Testlauf ist bereits abgeschlossen.');
        }
        if (!in_array($testId, $run['test_ids'] ?? array(), true)) {
            throw new \RuntimeException('Test gehoert nicht zu diesem Lauf.');
        }

        foreach ($run['results'] ?? array() as $existing) {
            if (($existing['test_id'] ?? '') === $testId) {
                return $existing;
            }
        }

        $catalog = $this->catalogById();
        if (!isset($catalog[$testId])) {
            throw new \RuntimeException('Test ist nicht mehr im Katalog vorhanden.');
        }

        $run['current_test_id'] = $testId;
        $this->writeRun($run);
        $result = $this->executeTest($catalog[$testId]);

        $run = $this->loadRun($runId) ?: $run;
        $run['results'][] = $result;
        $run['current_test_id'] = null;
        $run['totals'] = $this->calculateTotals($run);
        $run['duration_ms'] = $this->durationSince((string)$run['started_at']);
        $this->writeRun($run);
        return $result;
    }

    /**
     * Uebernimmt das Ergebnis eines im Admin-Browser isoliert ausgefuehrten
     * JavaScript-Tests. Testmetadaten und Laufzuordnung bleiben serverseitig.
     */
    public function recordBrowserTestResult(string $runId, string $testId, array $payload): array
    {
        $run = $this->loadRun($runId);
        if (!$run || ($run['status'] ?? '') !== 'running') {
            throw new \RuntimeException('Aktiver Testlauf wurde nicht gefunden.');
        }
        if (!in_array($testId, $run['test_ids'] ?? array(), true)) {
            throw new \RuntimeException('Test gehoert nicht zu diesem Lauf.');
        }
        foreach ($run['results'] ?? array() as $existing) {
            if (($existing['test_id'] ?? '') === $testId) {
                return $existing;
            }
        }
        $catalog = $this->catalogById();
        $test = $catalog[$testId] ?? null;
        if (!is_array($test) || ($test['type'] ?? '') !== 'js') {
            throw new \RuntimeException('Nur JavaScript-Tests duerfen Browserergebnisse melden.');
        }

        $status = ($payload['status'] ?? '') === 'passed' ? 'passed' : 'failed';
        $output = $this->cleanOutput((string)($payload['output'] ?? ''));
        $duration = max(0, min(3600000, (int)($payload['duration_ms'] ?? 0)));
        $now = $this->now();
        $result = array(
            'test_id' => (string)$test['id'],
            'name' => (string)$test['name'],
            'category' => (string)$test['category'],
            'type' => 'js',
            'execution' => 'browser',
            'status' => $status,
            'exit_code' => $status === 'passed' ? 0 : 1,
            'timed_out' => !empty($payload['timed_out']),
            'duration_ms' => $duration,
            'started_at' => (string)($payload['started_at'] ?? $now),
            'finished_at' => $now,
            'summary' => $this->resultSummary($status, $output, ''),
            'output' => $output,
            'relative_path' => (string)$test['relative_path'],
        );
        $run['results'][] = $result;
        $run['current_test_id'] = null;
        $run['totals'] = $this->calculateTotals($run);
        $run['duration_ms'] = $this->durationSince((string)$run['started_at']);
        $this->writeRun($run);
        return $result;
    }

    public function finishRun(string $runId, bool $aborted = false): array
    {
        $run = $this->loadRun($runId);
        if (!$run) {
            throw new \RuntimeException('Testlauf wurde nicht gefunden.');
        }
        $run['current_test_id'] = null;
        $run['totals'] = $this->calculateTotals($run);
        $run['finished_at'] = $this->now();
        $run['duration_ms'] = $this->durationBetween((string)$run['started_at'], (string)$run['finished_at']);
        if ($aborted || (int)$run['totals']['completed'] < (int)$run['totals']['total']) {
            $run['status'] = 'aborted';
        } elseif ((int)$run['totals']['failed'] > 0) {
            $run['status'] = 'failed';
        } else {
            $run['status'] = 'passed';
        }
        $this->writeRun($run);
        return $run;
    }

    /**
     * Synchroner Einstieg fuer CLI und CI.
     */
    public function runProfile(string $profile = 'full', array $testIds = array(), ?callable $onResult = null): array
    {
        $run = $this->startRun($profile, $testIds, 'cli');
        foreach ($run['test_ids'] as $id) {
            $result = $this->executeRunTest((string)$run['id'], (string)$id);
            if ($onResult) {
                $onResult($result, $this->loadRun((string)$run['id']));
            }
        }
        return $this->finishRun((string)$run['id']);
    }

    public function loadRun(string $runId): ?array
    {
        if (!$this->validRunId($runId)) {
            return null;
        }
        $path = $this->runPath($runId);
        if (!is_file($path)) {
            return null;
        }
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return null;
        }
        flock($handle, LOCK_SH);
        $json = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        $data = json_decode((string)$json, true);
        return is_array($data) ? $data : null;
    }

    public function history(int $limit = 20): array
    {
        $files = glob($this->logDir . '/*.json') ?: array();
        usort($files, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        $items = array();
        foreach (array_slice($files, 0, max(1, min(100, $limit))) as $file) {
            $run = $this->loadRun(pathinfo($file, PATHINFO_FILENAME));
            if (!$run) {
                continue;
            }
            $status = (string)($run['status'] ?? '');
            $modified = (int)(filemtime($file) ?: 0);
            $run['display_status'] = $status === 'running' && $modified < time() - self::ACTIVE_RUN_GRACE_SECONDS
                ? 'interrupted'
                : $status;
            unset($run['results'], $run['test_ids'], $run['environment']);
            $items[] = $run;
        }
        return $items;
    }

    public function runLogPath(string $runId): ?string
    {
        $path = $this->validRunId($runId) ? $this->runPath($runId) : '';
        return $path !== '' && is_file($path) ? $path : null;
    }

    private function builtinCatalog(): array
    {
        return array(
            $this->builtin('environment', 'Laufzeitumgebung', 'Prueft PHP-Version, Erweiterungen und Prozessausfuehrung.', 'quick', 15),
            $this->builtin('filesystem', 'Dateisystem und Schutzregeln', 'Prueft notwendige Verzeichnisse, Schreibrechte und private Dateischutzregeln.', 'quick', 15),
            $this->builtin('modules', 'Modul-Einstiegspunkte', 'Prueft Hauptklassen, Namespaces und Konfiguration aller Module.', 'quick', 30),
            $this->builtin('conflict_markers', 'Ungelöste Konfliktmarker', 'Sucht nach nicht aufgeloesten Git-Konflikten in Quell- und Konfigurationsdateien.', 'quick', 30),
            $this->builtin('page_cache', 'Gastseiten-Cache Integrität', 'Prueft Generationen, veraltete HTML-Dateien und liegengebliebene Schreibdateien.', 'quick', 30),
            $this->builtin('php_syntax', 'PHP-Syntax Gesamtsystem', 'Fuehrt php -l fuer alle eigenen PHP-Dateien aus.', 'full', 300),
            $this->builtin('js_syntax', 'JavaScript-Syntax Gesamtsystem', 'Prueft alle eigenen JavaScript-Dateien mit Node.js.', 'full', 300),
            $this->builtin('composer_validate', 'Composer-Konfiguration', 'Validiert dbx/composer.json im Strict-Modus.', 'full', 60),
            $this->builtin('composer_audit', 'Composer-Sicherheitsaudit', 'Prueft produktive Composer-Abhaengigkeiten auf bekannte Schwachstellen.', 'full', 120),
        );
    }

    private function builtin(string $key, string $name, string $description, string $tier, int $timeout): array
    {
        return array(
            'id' => 'system.' . $key,
            'name' => $name,
            'description' => $description,
            'type' => 'system',
            'category' => 'System',
            'tier' => $tier,
            'timeout' => $timeout,
            'relative_path' => '',
            'handler' => $key,
        );
    }

    private function discoverFileTests(): array
    {
        $tests = array();
        $root = $this->baseDir . '/dbx';
        if (!is_dir($root)) {
            return $tests;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            if (!str_contains($path, '/tests/')) {
                continue;
            }
            $name = $file->getFilename();
            $type = str_ends_with($name, '_test.php') ? 'php' : (str_ends_with($name, '_test.js') ? 'js' : '');
            if ($type === '') {
                continue;
            }
            $relative = ltrim(substr($path, strlen($this->baseDir)), '/');
            $module = $this->moduleFromPath($relative);
            $tier = preg_match('/(?:integration|roundtrip|performance|migration|install|update|doxygen|maintenance)/i', $name)
                ? 'full'
                : 'quick';
            $tests[] = array(
                'id' => $type . '.' . substr(hash('sha256', $relative), 0, 20),
                'name' => preg_replace('/_test\.(?:php|js)$/i', '', $name),
                'description' => $relative,
                'type' => $type,
                'execution' => $type === 'js' ? 'browser' : 'server',
                'category' => $module,
                'tier' => $tier,
                'timeout' => $tier === 'full' ? 180 : 90,
                'relative_path' => $relative,
                'handler' => '',
            );
        }
        return $tests;
    }

    private function moduleFromPath(string $relative): string
    {
        if (preg_match('~^dbx/modules/([^/]+)/~', $relative, $match)) {
            return (string)$match[1];
        }
        return 'Core';
    }

    private function executeTest(array $test): array
    {
        $started = microtime(true);
        $startedAt = $this->now();
        if (($test['type'] ?? '') === 'system') {
            $raw = $this->executeBuiltin((string)$test['handler'], (int)$test['timeout']);
        } else {
            $raw = $this->executeFileTest($test);
        }
        $finishedAt = $this->now();
        $output = $this->cleanOutput((string)($raw['output'] ?? ''));
        $status = (string)($raw['status'] ?? ((int)($raw['exit_code'] ?? 1) === 0 ? 'passed' : 'failed'));
        if (!in_array($status, array('passed', 'failed', 'skipped'), true)) {
            $status = 'failed';
        }
        return array(
            'test_id' => (string)$test['id'],
            'name' => (string)$test['name'],
            'category' => (string)$test['category'],
            'type' => (string)$test['type'],
            'status' => $status,
            'exit_code' => isset($raw['exit_code']) ? (int)$raw['exit_code'] : null,
            'timed_out' => !empty($raw['timed_out']),
            'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'summary' => $this->resultSummary($status, $output, (string)($raw['summary'] ?? '')),
            'output' => $output,
            'relative_path' => (string)($test['relative_path'] ?? ''),
        );
    }

    private function executeFileTest(array $test): array
    {
        $relative = (string)($test['relative_path'] ?? '');
        $path = $this->safeProjectFile($relative);
        if ($path === null || !is_file($path)) {
            return array('status' => 'failed', 'exit_code' => 2, 'output' => 'Testdatei fehlt: ' . $relative);
        }
        $type = (string)($test['type'] ?? '');
        if ($type === 'php') {
            $php = $this->resolvePhpCliBinary();
            if ($php === null) {
                return array(
                    'status' => 'failed',
                    'exit_code' => 127,
                    'output' => 'PHP-CLI wurde nicht gefunden. Bitte DBX_PHP_BINARY auf den PHP-CLI-Interpreter setzen.',
                );
            }
            $command = array($php, $path);
        } elseif ($type === 'js') {
            $node = $this->resolveExecutable('node');
            if ($node === null) {
                return array(
                    'status' => 'skipped',
                    'exit_code' => 0,
                    'output' => 'Node.js ist nicht installiert. Dieser JavaScript-Test wird im Web-Dashboard vom Browser ausgefuehrt.',
                );
            }
            $command = array($node, $path);
        } else {
            return array('status' => 'failed', 'exit_code' => 2, 'output' => 'Unbekannter Testtyp.');
        }
        return $this->runProcess($command, $this->baseDir, (int)($test['timeout'] ?? 90));
    }

    private function executeBuiltin(string $handler, int $timeout): array
    {
        return match ($handler) {
            'environment' => $this->checkEnvironment(),
            'filesystem' => $this->checkFilesystem(),
            'modules' => $this->checkModules(),
            'conflict_markers' => $this->checkConflictMarkers(),
            'page_cache' => $this->checkPageCache(),
            'php_syntax' => $this->checkPhpSyntax($timeout),
            'js_syntax' => $this->checkJavaScriptSyntax($timeout),
            'composer_validate' => $this->runComposer(array('validate', '--strict', '--no-check-publish'), $timeout),
            'composer_audit' => $this->runComposer(array('audit', '--no-dev'), $timeout),
            default => array('status' => 'failed', 'exit_code' => 2, 'output' => 'Unbekannter Systemtest: ' . $handler),
        };
    }

    private function checkEnvironment(): array
    {
        $errors = array();
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $errors[] = 'PHP 8.0 oder neuer ist erforderlich; aktiv: ' . PHP_VERSION;
        }
        foreach (array('json', 'session', 'pdo', 'pdo_sqlite', 'openssl', 'fileinfo') as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = 'PHP-Erweiterung fehlt: ' . $extension;
            }
        }
        if (!function_exists('proc_open')) {
            $errors[] = 'proc_open ist deaktiviert; isolierte Tests sind nicht moeglich.';
        }
        $phpCli = $this->resolvePhpCliBinary();
        if ($phpCli === null) {
            $errors[] = 'PHP-CLI wurde nicht gefunden; serverseitige Einzeltests sind nicht moeglich.';
        }
        $node = $this->resolveExecutable('node');
        $lines = array(
            'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')',
            'PHP-CLI: ' . ($phpCli ?? 'nicht gefunden'),
            'Betriebssystem: ' . PHP_OS_FAMILY,
            'Speicherlimit: ' . (string)ini_get('memory_limit'),
            'Node.js: ' . ($node !== null ? 'verfuegbar (optional)' : 'nicht installiert; Browser-Adapter aktiv'),
        );
        return $this->checkResult($errors, $lines);
    }

    private function checkFilesystem(): array
    {
        $errors = array();
        $lines = array();
        foreach (array('dbx/modules', 'files/sys', 'files/temp') as $relative) {
            $path = $this->baseDir . '/' . $relative;
            if (!is_dir($path)) {
                $parent = dirname($path);
                if (str_starts_with($relative, 'files/')
                    && is_dir($parent)
                    && is_writable($parent)
                ) {
                    $lines[] = $relative . ': wird bei Bedarf in einem schreibbaren Laufzeitverzeichnis angelegt';
                    continue;
                }
                $errors[] = 'Verzeichnis fehlt: ' . $relative;
                continue;
            }
            $lines[] = $relative . ': vorhanden' . (is_writable($path) ? ', schreibbar' : ', nicht schreibbar');
            if (str_starts_with($relative, 'files/') && !is_writable($path)) {
                $errors[] = 'Laufzeitverzeichnis ist nicht schreibbar: ' . $relative;
            }
        }
        $htaccess = @file_get_contents($this->baseDir . '/.htaccess');
        if (!is_string($htaccess) || !str_contains($htaccess, 'sqlite3') || !str_contains($htaccess, 'files/(media|sys|temp)')) {
            $errors[] = '.htaccess schuetzt private Laufzeit- oder Datenbankdateien nicht vollstaendig.';
        } else {
            $lines[] = '.htaccess: private Dateien werden gesperrt';
        }
        return $this->checkResult($errors, $lines);
    }

    private function checkModules(): array
    {
        $errors = array();
        $count = 0;
        foreach (glob($this->baseDir . '/dbx/modules/*', GLOB_ONLYDIR) ?: array() as $dir) {
            $module = basename($dir);
            $entry = $dir . '/' . $module . '.class.php';
            $config = $dir . '/cfg/config.php';
            if (!is_file($entry)) {
                $errors[] = $module . ': Hauptklasse fehlt';
                continue;
            }
            if (!is_file($config)) {
                $errors[] = $module . ': cfg/config.php fehlt';
            }
            $source = (string)@file_get_contents($entry);
            if (!preg_match('/namespace\s+dbx\\\\' . preg_quote($module, '/') . '\s*;/', $source)) {
                $errors[] = $module . ': Namespace stimmt nicht';
            }
            if (!preg_match('/class\s+' . preg_quote($module, '/') . '\b/i', $source)) {
                $errors[] = $module . ': Klasse ' . $module . ' fehlt';
            }
            $count++;
        }
        return $this->checkResult($errors, array($count . ' Module geprueft.'));
    }

    private function checkConflictMarkers(): array
    {
        $errors = array();
        $checked = 0;
        foreach ($this->projectFiles(array('php', 'js', 'css', 'htm', 'html', 'json', 'md', 'cfx', 'cfg')) as $file) {
            $checked++;
            $handle = @fopen($file, 'rb');
            if (!$handle) {
                continue;
            }
            $lineNo = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNo++;
                if (preg_match('/^(<<<<<<< |=======\s*$|>>>>>>> )/', rtrim($line, "\r\n"))) {
                    $errors[] = $this->relativePath($file) . ':' . $lineNo;
                    if (count($errors) >= 50) {
                        break 2;
                    }
                }
            }
            fclose($handle);
        }
        return $this->checkResult($errors, array($checked . ' Dateien geprueft.'));
    }

    private function checkPageCache(): array
    {
        $dir = $this->baseDir . '/files/cache/content/full-page';
        if (!is_dir($dir)) {
            return $this->checkResult(array(), array('Noch kein Gastseiten-Cache vorhanden.'));
        }
        $generation = trim((string)@file_get_contents($dir . '/.generation'));
        $errors = array();
        if (!preg_match('/^[a-f0-9]{24}$/', $generation)) {
            $errors[] = 'Cache-Generation fehlt oder ist ungueltig.';
        }
        $files = glob($dir . '/*.htm') ?: array();
        foreach ($files as $file) {
            if ($generation !== '' && !str_ends_with(strtolower(basename($file)), '_' . $generation . '_v3.htm')) {
                $errors[] = 'Veraltete Cache-Generation: ' . basename($file);
                if (count($errors) >= 25) break;
            }
        }
        foreach (glob($dir . '/*.tmp-*') ?: array() as $temporary) {
            if ((int)@filemtime($temporary) < time() - 300) {
                $errors[] = 'Verwaiste temporaere Cache-Datei: ' . basename($temporary);
                if (count($errors) >= 25) break;
            }
        }
        return $this->checkResult($errors, array(count($files) . ' aktuelle Gastseiten in genau einer Generation.'));
    }

    private function checkPhpSyntax(int $timeout): array
    {
        $php = $this->resolvePhpCliBinary();
        if ($php === null) {
            return array(
                'status' => 'failed',
                'exit_code' => 127,
                'output' => 'PHP-CLI wurde nicht gefunden; die Syntaxpruefung kann nicht ausgefuehrt werden.',
            );
        }
        $errors = array();
        $checked = 0;
        $deadline = microtime(true) + max(30, $timeout);
        foreach ($this->projectFiles(array('php')) as $file) {
            // PHP-Generatorvorlagen enthalten absichtlich noch nicht ersetzte
            // Platzhalter oder Fragmente und sind keine direkt ladbaren Dateien.
            if (str_contains(str_replace('\\', '/', $file), '/tpl/php/')) {
                continue;
            }
            if (microtime(true) >= $deadline) {
                return array(
                    'status' => 'failed',
                    'exit_code' => 124,
                    'timed_out' => true,
                    'output' => 'PHP-Syntaxpruefung nach ' . $checked . ' Dateien abgebrochen: Gesamtzeit ueberschritten.',
                );
            }
            $checked++;
            // Syntaxpruefung benoetigt keine php.ini. -n spart unter XAMPP pro
            // Datei den teuren Modul- und Konfigurationsstart.
            $result = $this->runProcess(array($php, '-n', '-l', $file), $this->baseDir, 20);
            if ((int)($result['exit_code'] ?? 1) !== 0) {
                $errors[] = $this->relativePath($file) . "\n" . trim((string)($result['output'] ?? ''));
                if (count($errors) >= 25) {
                    break;
                }
            }
        }
        return $this->checkResult($errors, array($checked . ' PHP-Dateien ohne Syntaxfehler.'));
    }

    private function checkJavaScriptSyntax(int $timeout): array
    {
        $node = $this->resolveExecutable('node');
        if ($node === null) {
            return array(
                'status' => 'skipped',
                'exit_code' => 0,
                'output' => 'Node.js ist optional und auf diesem Server nicht installiert. Browserfaehige JavaScript-Tests laufen im Web-Dashboard.',
            );
        }
        $errors = array();
        $checked = 0;
        $deadline = microtime(true) + max(30, $timeout);
        foreach ($this->projectFiles(array('js')) as $file) {
            if (microtime(true) >= $deadline) {
                return array(
                    'status' => 'failed',
                    'exit_code' => 124,
                    'timed_out' => true,
                    'output' => 'JavaScript-Syntaxpruefung nach ' . $checked . ' Dateien abgebrochen: Gesamtzeit ueberschritten.',
                );
            }
            $checked++;
            $result = $this->runProcess(array($node, '--check', $file), $this->baseDir, 20);
            if ((int)($result['exit_code'] ?? 1) !== 0) {
                $errors[] = $this->relativePath($file) . "\n" . trim((string)($result['output'] ?? ''));
                if (count($errors) >= 25) {
                    break;
                }
            }
        }
        return $this->checkResult($errors, array($checked . ' JavaScript-Dateien ohne Syntaxfehler.'));
    }

    private function runComposer(array $arguments, int $timeout): array
    {
        $composer = $this->resolveComposerCommand();
        if ($composer === null) {
            return array('status' => 'failed', 'exit_code' => 127, 'output' => 'Composer wurde nicht gefunden.');
        }
        return $this->runProcess(array_merge($composer, $arguments), $this->baseDir . '/dbx', $timeout);
    }

    private function resolveComposerCommand(): ?array
    {
        $candidate = $this->resolveExecutable('composer');
        if ($candidate === null) {
            return null;
        }
        if (preg_match('/\.bat$/i', $candidate)) {
            $phar = dirname($candidate) . DIRECTORY_SEPARATOR . 'composer.phar';
            if (is_file($phar)) {
                $php = $this->resolvePhpCliBinary();
                return $php !== null ? array($php, $phar) : null;
            }
        }
        return array($candidate);
    }

    /**
     * PHP_BINARY points to httpd.exe when dbxSelfTest runs inside Apache on
     * Windows. Starting it as if it were PHP-CLI makes every web test fail.
     * Resolve a real CLI interpreter independently from the current SAPI.
     */
    private function resolvePhpCliBinary(): ?string
    {
        if ($this->phpCliBinaryCache !== null) {
            return $this->phpCliBinaryCache !== '' ? $this->phpCliBinaryCache : null;
        }

        $executable = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
        $candidates = array();
        $configured = trim((string)getenv('DBX_PHP_BINARY'), " \t\n\r\0\x0B\"");
        if ($configured !== '') {
            $candidates[] = $configured;
        }
        if (preg_match('/^php(?:\.exe)?$/i', basename(PHP_BINARY))) {
            $candidates[] = PHP_BINARY;
        }
        $ini = php_ini_loaded_file();
        if (is_string($ini) && $ini !== '') {
            $candidates[] = dirname($ini) . DIRECTORY_SEPARATOR . $executable;
        }
        if (defined('PHP_BINDIR')) {
            $candidates[] = rtrim((string)PHP_BINDIR, '/\\') . DIRECTORY_SEPARATOR . $executable;
        }

        // XAMPP and similar layouts: <root>/htdocs/project + <root>/php/php.exe.
        $directory = $this->baseDir;
        for ($level = 0; $level < 4; $level++) {
            $candidates[] = $directory . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $executable;
            $parent = dirname($directory);
            if ($parent === $directory) {
                break;
            }
            $directory = $parent;
        }
        $fromPath = $this->resolveExecutable('php');
        if ($fromPath !== null) {
            $candidates[] = $fromPath;
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate) && (PHP_OS_FAMILY === 'Windows' || is_executable($candidate))) {
                $this->phpCliBinaryCache = $candidate;
                return $candidate;
            }
        }
        $this->phpCliBinaryCache = '';
        return null;
    }

    private function runProcess(array $command, string $cwd, int $timeout): array
    {
        if (!function_exists('proc_open')) {
            return array('status' => 'failed', 'exit_code' => 127, 'output' => 'proc_open ist nicht verfuegbar.');
        }
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $pipes = array();
        $environment = getenv();
        if (!is_array($environment)) {
            $environment = array();
        }
        $environment['DBX_SELFTEST'] = '1';
        unset($environment['DBX_SELFTEST_ALLOW_SYSMSG']);
        $process = @proc_open($command, $descriptors, $pipes, $cwd, $environment, array('bypass_shell' => true));
        if (!is_resource($process)) {
            return array('status' => 'failed', 'exit_code' => 127, 'output' => 'Prozess konnte nicht gestartet werden.');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $started = microtime(true);
        $output = '';
        $timedOut = false;
        $lastExit = -1;
        while (true) {
            $this->appendOutput($output, (string)stream_get_contents($pipes[1]));
            $this->appendOutput($output, (string)stream_get_contents($pipes[2]));
            $status = proc_get_status($process);
            if (!$status['running']) {
                $lastExit = (int)$status['exitcode'];
                break;
            }
            if ((microtime(true) - $started) > max(1, $timeout)) {
                $timedOut = true;
                @proc_terminate($process);
                usleep(100000);
                $status = proc_get_status($process);
                if ($status['running']) {
                    @proc_terminate($process, 9);
                }
                break;
            }
            usleep(20000);
        }
        $this->appendOutput($output, (string)stream_get_contents($pipes[1]));
        $this->appendOutput($output, (string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closedExit = proc_close($process);
        $exitCode = $timedOut ? 124 : ($lastExit >= 0 ? $lastExit : (int)$closedExit);
        return array(
            'status' => $exitCode === 0 ? 'passed' : 'failed',
            'exit_code' => $exitCode,
            'timed_out' => $timedOut,
            'output' => $output,
        );
    }

    private function appendOutput(string &$output, string $chunk): void
    {
        if ($chunk === '') {
            return;
        }
        $output .= $chunk;
        if (strlen($output) > $this->maxOutputBytes) {
            $half = intdiv($this->maxOutputBytes, 2);
            $output = substr($output, 0, $half)
                . "\n... Ausgabe gekuerzt ...\n"
                . substr($output, -$half);
        }
    }

    private function projectFiles(array $extensions): array
    {
        $extensions = array_fill_keys(array_map('strtolower', $extensions), true);
        $files = array();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($this->baseDir, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $current): bool {
                    if (!$current->isDir()) {
                        return true;
                    }
                    $path = str_replace('\\', '/', $current->getPathname());
                    foreach (array('/.git', '/dbx/vendor', '/files/', '/output/', '/reference/', '/.playwright-cli') as $excluded) {
                        if (str_contains($path, $excluded)) {
                            return false;
                        }
                    }
                    return true;
                }
            )
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && isset($extensions[strtolower($file->getExtension())])) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($files);
        return $files;
    }

    private function resolveExecutable(string $name): ?string
    {
        $pathValue = (string)getenv('PATH');
        $extensions = PHP_OS_FAMILY === 'Windows'
            ? array('.exe', '.bat', '.cmd', '')
            : array('');
        foreach (explode(PATH_SEPARATOR, $pathValue) as $directory) {
            $directory = trim($directory, " \t\n\r\0\x0B\"");
            if ($directory === '') {
                continue;
            }
            foreach ($extensions as $extension) {
                $candidate = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name . $extension;
                if (is_file($candidate) && (PHP_OS_FAMILY === 'Windows' || is_executable($candidate))) {
                    return $candidate;
                }
            }
        }
        return null;
    }

    private function checkResult(array $errors, array $successLines): array
    {
        if ($errors !== array()) {
            return array(
                'status' => 'failed',
                'exit_code' => 1,
                'output' => "FAIL\n- " . implode("\n- ", $errors),
            );
        }
        return array(
            'status' => 'passed',
            'exit_code' => 0,
            'output' => "PASS\n" . implode("\n", $successLines),
        );
    }

    private function calculateTotals(array $run): array
    {
        $totals = $this->totals(count($run['test_ids'] ?? array()));
        foreach ($run['results'] ?? array() as $result) {
            $status = (string)($result['status'] ?? 'failed');
            $totals['completed']++;
            if (isset($totals[$status])) {
                $totals[$status]++;
            } else {
                $totals['failed']++;
            }
            $totals['duration_ms'] += (int)($result['duration_ms'] ?? 0);
        }
        $totals['pending'] = max(0, $totals['total'] - $totals['completed']);
        return $totals;
    }

    private function totals(int $total): array
    {
        return array(
            'total' => $total,
            'completed' => 0,
            'pending' => $total,
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'duration_ms' => 0,
        );
    }

    private function resultSummary(string $status, string $output, string $explicit): string
    {
        if ($explicit !== '') {
            return $explicit;
        }
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $output) ?: array())));
        if ($status === 'passed') {
            return $lines !== array() ? (string)end($lines) : 'Bestanden';
        }
        foreach ($lines as $line) {
            if (preg_match('/(?:FAIL|Fatal|Error|Assertion|Exception|fehlt|missing)/i', $line)) {
                return $this->shorten($line, 300);
            }
        }
        return $lines !== array() ? $this->shorten((string)end($lines), 300) : 'Fehlgeschlagen';
    }

    private function shorten(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? (string)mb_substr($value, 0, $length)
            : substr($value, 0, $length);
    }

    private function cleanOutput(string $output): string
    {
        $output = preg_replace('/\x1B(?:[@-Z\\-_]|\[[0-?]*[ -\/]*[@-~])/', '', $output) ?? $output;
        $output = str_replace("\0", '', $output);
        if (strlen($output) > $this->maxOutputBytes) {
            $output = substr($output, 0, $this->maxOutputBytes) . "\n... Ausgabe gekuerzt ...";
        }
        return trim($output);
    }

    private function safeProjectFile(string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '..')) {
            return null;
        }
        $candidate = realpath($this->baseDir . '/' . ltrim(str_replace('\\', '/', $relative), '/'));
        if ($candidate === false) {
            return null;
        }
        $normalized = str_replace('\\', '/', $candidate);
        return str_starts_with($normalized . '/', $this->baseDir . '/') ? $normalized : null;
    }

    private function relativePath(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        return str_starts_with($file, $this->baseDir . '/') ? substr($file, strlen($this->baseDir) + 1) : $file;
    }

    private function normalizeProfile(string $profile): string
    {
        return $profile === 'quick' ? 'quick' : 'full';
    }

    private function validRunId(string $runId): bool
    {
        return preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{10}$/', $runId) === 1;
    }

    private function runPath(string $runId): string
    {
        return rtrim($this->logDir, '/\\') . DIRECTORY_SEPARATOR . $runId . '.json';
    }

    private function writeRun(array $run): void
    {
        $id = (string)($run['id'] ?? '');
        if (!$this->validRunId($id)) {
            throw new \RuntimeException('Ungueltige Testlauf-ID.');
        }
        $json = json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Testprotokoll konnte nicht serialisiert werden.');
        }
        $handle = @fopen($this->runPath($id), 'c+b');
        if (!$handle) {
            throw new \RuntimeException('Testprotokoll konnte nicht geoeffnet werden.');
        }
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException('Testprotokoll konnte nicht gesperrt werden.');
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $json);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Self-Test-Protokollverzeichnis konnte nicht angelegt werden.');
        }
    }

    private function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    private function durationSince(string $started): int
    {
        $time = strtotime($started);
        return $time === false ? 0 : max(0, (int)round((microtime(true) - $time) * 1000));
    }

    private function durationBetween(string $started, string $finished): int
    {
        $a = strtotime($started);
        $b = strtotime($finished);
        return $a === false || $b === false ? 0 : max(0, ($b - $a) * 1000);
    }
}
