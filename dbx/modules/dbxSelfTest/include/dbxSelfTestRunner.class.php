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

    private string $base_dir;
    private string $log_dir;
    private ?array $catalog_cache = null;
    private ?string $php_cli_binary_cache = null;
    private int $max_output_bytes = 131072;

    public function __construct(?string $base_dir = null, ?string $log_dir = null)
    {
        $root = $base_dir ?: dirname(__DIR__, 4);
        $real = realpath($root);
        $this->base_dir = rtrim(str_replace('\\', '/', $real !== false ? $real : $root), '/');
        $this->log_dir = $log_dir ?: $this->base_dir . '/files/sys/selftest';
        $this->ensure_directory($this->log_dir);
    }

    public function base_dir(): string
    {
        return $this->base_dir;
    }

    public function log_dir(): string
    {
        return $this->log_dir;
    }

    /**
     * Liefert den vollstaendigen oder auf ein Profil reduzierten Testkatalog.
     */
    public function catalog(string $profile = 'full'): array
    {
        if ($this->catalog_cache === null) {
            $tests = $this->builtin_catalog();
            $tests = array_merge($tests, $this->discover_file_tests());
            usort($tests, static function (array $a, array $b): int {
                return [$a['category'], $a['name']] <=> [$b['category'], $b['name']];
            });
            $this->catalog_cache = $tests;
        }

        $profile = $this->normalize_profile($profile);
        if ($profile === 'full') {
            return $this->catalog_cache;
        }

        return array_values(array_filter(
            $this->catalog_cache,
            static fn(array $test): bool => ($test['tier'] ?? 'full') === 'quick'
        ));
    }

    public function catalog_by_id(): array
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
    public function start_run(string $profile = 'full', array $test_ids = array(), string $mode = 'complete'): array
    {
        $profile = $this->normalize_profile($profile);
        $catalog = $this->catalog_by_id();
        if ($test_ids === array()) {
            $test_ids = array_column($this->catalog($profile), 'id');
        }

        $selected = array();
        foreach ($test_ids as $id) {
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
        $this->write_run($run);
        return $run;
    }

    /**
     * Führt genau einen zum Lauf gehoerenden Test aus und protokolliert ihn.
     */
    public function execute_run_test(string $run_id, string $test_id): array
    {
        $run = $this->load_run($run_id);
        if (!$run) {
            throw new \RuntimeException('Testlauf wurde nicht gefunden.');
        }
        if (($run['status'] ?? '') !== 'running') {
            throw new \RuntimeException('Testlauf ist bereits abgeschlossen.');
        }
        if (!in_array($test_id, $run['test_ids'] ?? array(), true)) {
            throw new \RuntimeException('Test gehoert nicht zu diesem Lauf.');
        }

        foreach ($run['results'] ?? array() as $existing) {
            if (($existing['test_id'] ?? '') === $test_id) {
                return $existing;
            }
        }

        $catalog = $this->catalog_by_id();
        if (!isset($catalog[$test_id])) {
            throw new \RuntimeException('Test ist nicht mehr im Katalog vorhanden.');
        }

        $run['current_test_id'] = $test_id;
        $this->write_run($run);
        $result = $this->execute_test($catalog[$test_id]);

        $run = $this->load_run($run_id) ?: $run;
        $run['results'][] = $result;
        $run['current_test_id'] = null;
        $run['totals'] = $this->calculate_totals($run);
        $run['duration_ms'] = $this->duration_since((string)$run['started_at']);
        $this->write_run($run);
        return $result;
    }

    /**
     * Übernimmt das Ergebnis eines im Admin-Browser isoliert ausgefuehrten
     * JavaScript-Tests. Testmetadaten und Laufzuordnung bleiben serverseitig.
     */
    public function record_browser_test_result(string $run_id, string $test_id, array $payload): array
    {
        $run = $this->load_run($run_id);
        if (!$run || ($run['status'] ?? '') !== 'running') {
            throw new \RuntimeException('Aktiver Testlauf wurde nicht gefunden.');
        }
        if (!in_array($test_id, $run['test_ids'] ?? array(), true)) {
            throw new \RuntimeException('Test gehoert nicht zu diesem Lauf.');
        }
        foreach ($run['results'] ?? array() as $existing) {
            if (($existing['test_id'] ?? '') === $test_id) {
                return $existing;
            }
        }
        $catalog = $this->catalog_by_id();
        $test = $catalog[$test_id] ?? null;
        if (!is_array($test) || ($test['type'] ?? '') !== 'js') {
            throw new \RuntimeException('Nur JavaScript-Tests duerfen Browserergebnisse melden.');
        }

        $status = ($payload['status'] ?? '') === 'passed' ? 'passed' : 'failed';
        $output = $this->clean_output((string)($payload['output'] ?? ''));
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
            'summary' => $this->result_summary($status, $output, ''),
            'output' => $output,
            'relative_path' => (string)$test['relative_path'],
        );
        $run['results'][] = $result;
        $run['current_test_id'] = null;
        $run['totals'] = $this->calculate_totals($run);
        $run['duration_ms'] = $this->duration_since((string)$run['started_at']);
        $this->write_run($run);
        return $result;
    }

    public function finish_run(string $run_id, bool $aborted = false): array
    {
        $run = $this->load_run($run_id);
        if (!$run) {
            throw new \RuntimeException('Testlauf wurde nicht gefunden.');
        }
        $run['current_test_id'] = null;
        $run['totals'] = $this->calculate_totals($run);
        $run['finished_at'] = $this->now();
        $run['duration_ms'] = $this->duration_between((string)$run['started_at'], (string)$run['finished_at']);
        if ($aborted || (int)$run['totals']['completed'] < (int)$run['totals']['total']) {
            $run['status'] = 'aborted';
        } elseif ((int)$run['totals']['failed'] > 0) {
            $run['status'] = 'failed';
        } else {
            $run['status'] = 'passed';
        }
        $this->write_run($run);
        return $run;
    }

    /**
     * Synchroner Einstieg fuer CLI und CI.
     */
    public function run_profile(string $profile = 'full', array $test_ids = array(), ?callable $on_result = null): array
    {
        $run = $this->start_run($profile, $test_ids, 'cli');
        foreach ($run['test_ids'] as $id) {
            $result = $this->execute_run_test((string)$run['id'], (string)$id);
            if ($on_result) {
                $on_result($result, $this->load_run((string)$run['id']));
            }
        }
        return $this->finish_run((string)$run['id']);
    }

    public function load_run(string $run_id): ?array
    {
        if (!$this->valid_run_id($run_id)) {
            return null;
        }
        $path = $this->run_path($run_id);
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
        $files = glob($this->log_dir . '/*.json') ?: array();
        usort($files, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        $items = array();
        foreach (array_slice($files, 0, max(1, min(100, $limit))) as $file) {
            $run = $this->load_run(pathinfo($file, PATHINFO_FILENAME));
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

    /**
     * Loescht alle protokollierten Testlaeufe. Ein gerade aktiv laufender
     * Lauf (Status "running", noch innerhalb der Aktiv-Karenzzeit) bleibt
     * erhalten, damit eine parallel offene Live-Ansicht nicht verwaist.
     *
     * @return int Anzahl der tatsaechlich geloeschten Protokolldateien.
     */
    public function clear_history(): int
    {
        $files = glob($this->log_dir . '/*.json') ?: array();
        $deleted = 0;
        foreach ($files as $file) {
            $run = $this->load_run(pathinfo($file, PATHINFO_FILENAME));
            $status = (string)($run['status'] ?? '');
            $modified = (int)(filemtime($file) ?: 0);
            $active = $status === 'running' && $modified >= time() - self::ACTIVE_RUN_GRACE_SECONDS;
            if ($active) {
                continue;
            }
            if (@unlink($file)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    public function run_log_path(string $run_id): ?string
    {
        $path = $this->valid_run_id($run_id) ? $this->run_path($run_id) : '';
        return $path !== '' && is_file($path) ? $path : null;
    }

    private function builtin_catalog(): array
    {
        return array(
            $this->builtin('environment', 'Laufzeitumgebung', 'Prüft PHP-Version, Erweiterungen und Prozessausfuehrung.', 'quick', 15),
            $this->builtin('filesystem', 'Dateisystem und Schutzregeln', 'Prüft notwendige Verzeichnisse, Schreibrechte und private Dateischutzregeln.', 'quick', 15),
            $this->builtin('modules', 'Modul-Einstiegspunkte', 'Prüft Hauptklassen, Namespaces und Konfiguration aller Module.', 'quick', 30),
            $this->builtin('conflict_markers', 'Ungelöste Konfliktmarker', 'Sucht nach nicht aufgeloesten Git-Konflikten in Quell- und Konfigurationsdateien.', 'quick', 30),
            $this->builtin('page_cache', 'Gastseiten-Cache Integrität', 'Prüft Generationen, veraltete HTML-Dateien und liegengebliebene Schreibdateien.', 'quick', 30),
            $this->builtin('php_syntax', 'PHP-Syntax Gesamtsystem', 'Parst alle eigenen PHP-Dateien ohne sie auszufuehren.', 'full', 300),
            $this->builtin('js_syntax', 'JavaScript-Syntax Gesamtsystem', 'Prüft alle eigenen JavaScript-Dateien mit Node.js; ohne Node.js laufen Browser-Tests weiterhin im Web-Dashboard.', 'full', 300),
            $this->builtin('composer_validate', 'Composer-Konfiguration', 'Validiert dbx/composer.json im Strict-Modus.', 'full', 60),
            $this->builtin('composer_audit', 'Composer-Sicherheitsaudit', 'Prüft produktive Composer-Abhaengigkeiten auf bekannte Schwachstellen.', 'full', 120),
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

    private function discover_file_tests(): array
    {
        $tests = array();
        $root = $this->base_dir . '/dbx';
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
            $relative = ltrim(substr($path, strlen($this->base_dir)), '/');
            $module = $this->module_from_path($relative);
            $metadata = $this->test_metadata($relative, $type);
            $tier = (string)$metadata['tier'];
            $test_name = (string)preg_replace('/_test\.(?:php|js)$/i', '', $name);
            $tests[] = array(
                'id' => $type . '.' . substr(hash('sha256', $relative), 0, 20),
                'name' => $test_name,
                'description' => trim((string)($metadata['description'] ?? '')) !== ''
                    ? trim((string)$metadata['description'])
                    : $this->file_test_description($test_name, $path),
                'type' => $type,
                'execution' => $type === 'js' ? 'browser' : 'server',
                'category' => $module,
                'tier' => $tier,
                'timeout' => (int)$metadata['timeout'],
                'isolation' => (string)$metadata['isolation'],
                'resources' => (array)$metadata['resources'],
                'relative_path' => $relative,
                'handler' => '',
            );
        }
        return $tests;
    }

    /**
     * Erzeugt fuer automatisch entdeckte Tests eine verständliche
     * Mindestbeschreibung. Spezifische Tests können diese zentral über
     * test-metadata.php überschreiben, aber kein UI-Eintrag bleibt ohne
     * Aussage darüber, welche Art von Verhalten geprüft wird.
     */
    private function file_test_description(string $test_name, string $path = ''): string
    {
        // Ein einleitender Test-Kommentar ist die präziseste Dokumentation und
        // wird deshalb direkt im Dashboard als Tooltip verwendet. Annotationen
        // und lange Implementierungsdetails bleiben außen vor.
        if ($path !== '' && is_file($path)) {
            $source = (string)file_get_contents($path);
            if (preg_match('~/\*\*(.*?)\*/~s', $source, $match)) {
                $lines = preg_split('/\R/', (string)$match[1]) ?: array();
                $summary = array();
                foreach ($lines as $line) {
                    $line = trim((string)preg_replace('/^\s*\*\s?/', '', (string)$line));
                    if ($line === '' && $summary !== array()) break;
                    if ($line === '' || str_starts_with($line, '@')) continue;
                    $summary[] = $line;
                }
                $description = trim(implode(' ', $summary));
                if (mb_strlen($description) >= 12 && mb_strlen($description) <= 320) {
                    return $description;
                }
            }

            // Viele kompakte Vertragstests dokumentieren ihr kontrolliertes
            // Endergebnis in der abschließenden OK/PASS-Meldung. Diese Aussage
            // ist konkreter als ein aus dem Dateinamen erzeugter Standardsatz.
            if (preg_match('~(?:echo\s+|\.pass\(\s*)[\'\"](?:OK|PASS)(?::|\s)+([^\'\"\r\n]+)~iu', $source, $match)) {
                $outcome = preg_replace('/\\\\[rnt]+$/', '', (string)$match[1]);
                $outcome = trim((string)$outcome, " \t.:;-");
                if (mb_strlen($outcome) >= 8 && mb_strlen($outcome) <= 240) {
                    return 'Prüft als kontrolliertes Ergebnis: ' . $outcome . '.';
                }
            }
        }

        $label = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $test_name);
        $label = str_replace(array('_', '-'), ' ', (string)$label);
        $label = trim((string)preg_replace('/\s+/', ' ', (string)$label));
        $label = preg_replace('/\bcontract\b/i', '', $label);
        $label = trim((string)preg_replace('/\s+/', ' ', (string)$label));

        if (preg_match('/security|sicherheit/i', $test_name)) {
            return 'Prüft die Sicherheitsregeln und unzulässigen Zugriffe für ' . $label . '.';
        }
        if (preg_match('/performance/i', $test_name)) {
            return 'Prüft die Performance-Grenzen und vermeidet Regressionen bei ' . $label . '.';
        }
        if (preg_match('/integration|roundtrip/i', $test_name)) {
            return 'Prüft das Zusammenspiel der beteiligten Komponenten für ' . $label . '.';
        }
        if (preg_match('/contract/i', $test_name)) {
            return 'Prüft den verbindlichen technischen Vertrag für ' . $label . '.';
        }
        return 'Prüft die vorgesehene Funktionalität von ' . $label . '.';
    }

    /** @return array{tier:string,timeout:int,isolation:string,resources:array} */
    private function test_metadata(string $relative, string $type): array
    {
        static $config = null;
        if ($config === null) {
            $file = dirname(__DIR__) . '/cfg/test-metadata.php';
            $config = is_file($file) ? require $file : array();
            if (!is_array($config)) $config = array();
        }
        $metadata = array_replace(array(
            'tier' => 'quick', 'timeout' => 90,
            'isolation' => $type === 'js' ? 'browser' : 'process',
            'resources' => array(), 'description' => '',
        ), is_array($config['defaults'] ?? null) ? $config['defaults'] : array());
        foreach ((array)($config['rules'] ?? array()) as $rule) {
            if (!is_array($rule) || @preg_match((string)($rule['pattern'] ?? ''), $relative) !== 1) {
                continue;
            }
            $metadata = array_replace($metadata, $rule);
            break;
        }
        if ($type === 'js') $metadata['isolation'] = 'browser';
        $metadata['tier'] = in_array($metadata['tier'], array('quick', 'full'), true) ? $metadata['tier'] : 'quick';
        $metadata['timeout'] = max(5, (int)$metadata['timeout']);
        $metadata['resources'] = array_values(array_unique(array_map('strval', (array)$metadata['resources'])));
        return $metadata;
    }

    private function module_from_path(string $relative): string
    {
        if (preg_match('~^dbx/modules/([^/]+)/~', $relative, $match)) {
            return (string)$match[1];
        }
        return 'Core';
    }

    private function execute_test(array $test): array
    {
        $started = microtime(true);
        $started_at = $this->now();
        if (($test['type'] ?? '') === 'system') {
            $raw = $this->execute_builtin((string)$test['handler'], (int)$test['timeout']);
        } else {
            $raw = $this->execute_file_test($test);
        }
        $finished_at = $this->now();
        $output = $this->clean_output((string)($raw['output'] ?? ''));
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
            'started_at' => $started_at,
            'finished_at' => $finished_at,
            'summary' => $this->result_summary($status, $output, (string)($raw['summary'] ?? '')),
            'output' => $output,
            'relative_path' => (string)($test['relative_path'] ?? ''),
        );
    }

    private function execute_file_test(array $test): array
    {
        $relative = (string)($test['relative_path'] ?? '');
        $path = $this->safe_project_file($relative);
        if ($path === null || !is_file($path)) {
            return array('status' => 'failed', 'exit_code' => 2, 'output' => 'Testdatei fehlt: ' . $relative);
        }
        $type = (string)($test['type'] ?? '');
        if ($type === 'php') {
            $php = $this->resolve_php_cli_binary();
            if ($php === null) {
                return array(
                    'status' => 'failed',
                    'exit_code' => 127,
                    'output' => 'PHP-CLI wurde nicht gefunden. Bitte DBX_PHP_BINARY auf den PHP-CLI-Interpreter setzen.',
                );
            }
            $command = array($php, $path);
        } elseif ($type === 'js') {
            $node = $this->resolve_executable('node');
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
        return $this->run_process($command, $this->base_dir, (int)($test['timeout'] ?? 90));
    }

    private function execute_builtin(string $handler, int $timeout): array
    {
        return match ($handler) {
            'environment' => $this->check_environment(),
            'filesystem' => $this->check_filesystem(),
            'modules' => $this->check_modules(),
            'conflict_markers' => $this->check_conflict_markers(),
            'page_cache' => $this->check_page_cache(),
            'php_syntax' => $this->check_php_syntax($timeout),
            'js_syntax' => $this->check_java_script_syntax($timeout),
            'composer_validate' => $this->run_composer(array('validate', '--strict', '--no-check-publish'), $timeout),
            'composer_audit' => $this->run_composer(array('audit', '--no-dev'), $timeout),
            default => array('status' => 'failed', 'exit_code' => 2, 'output' => 'Unbekannter Systemtest: ' . $handler),
        };
    }

    private function check_environment(): array
    {
        $errors = array();
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            $errors[] = 'PHP 8.2 oder neuer ist erforderlich; aktiv: ' . PHP_VERSION;
        }
        foreach (array('json', 'session', 'pdo', 'pdo_sqlite', 'openssl', 'fileinfo') as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = 'PHP-Erweiterung fehlt: ' . $extension;
            }
        }
        if (!function_exists('proc_open')) {
            $errors[] = 'proc_open ist deaktiviert; isolierte Tests sind nicht moeglich.';
        }
        $php_cli = $this->resolve_php_cli_binary();
        if ($php_cli === null) {
            $errors[] = 'PHP-CLI wurde nicht gefunden; serverseitige Einzeltests sind nicht moeglich.';
        }
        $node = $this->resolve_executable('node');
        $lines = array(
            'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')',
            'PHP-CLI: ' . ($php_cli ?? 'nicht gefunden'),
            'Betriebssystem: ' . PHP_OS_FAMILY,
            'Speicherlimit: ' . (string)ini_get('memory_limit'),
            'Node.js: ' . ($node !== null ? 'verfuegbar (optional)' : 'nicht installiert; Browser-Adapter aktiv'),
        );
        return $this->check_result($errors, $lines);
    }

    private function check_filesystem(): array
    {
        $errors = array();
        $lines = array();
        foreach (array('dbx/modules', 'files/sys', 'files/temp') as $relative) {
            $path = $this->base_dir . '/' . $relative;
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
        $htaccess = @file_get_contents($this->base_dir . '/.htaccess');
        if (!is_string($htaccess) || !str_contains($htaccess, 'sqlite3') || !str_contains($htaccess, 'files/(media|sys|temp)')) {
            $errors[] = '.htaccess schuetzt private Laufzeit- oder Datenbankdateien nicht vollstaendig.';
        } else {
            $lines[] = '.htaccess: private Dateien werden gesperrt';
        }
        return $this->check_result($errors, $lines);
    }

    private function check_modules(): array
    {
        $errors = array();
        $count = 0;
        foreach (glob($this->base_dir . '/dbx/modules/*', GLOB_ONLYDIR) ?: array() as $dir) {
            $module = basename($dir);
            // Nur gueltige Modulkennungen sind lauffaehige Module. Temporär
            // deaktivierte Verzeichnisse wie "-myModule" gehoeren weder zur
            // Registry noch in die Einstiegspunktpruefung.
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{1,62}$/', $module)) {
                continue;
            }
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
        return $this->check_result($errors, array($count . ' Module geprueft.'));
    }

    private function check_conflict_markers(): array
    {
        $errors = array();
        $checked = 0;
        foreach ($this->project_files(array('php', 'js', 'css', 'htm', 'html', 'json', 'md', 'cfx', 'cfg')) as $file) {
            $checked++;
            $handle = @fopen($file, 'rb');
            if (!$handle) {
                continue;
            }
            $line_no = 0;
            while (($line = fgets($handle)) !== false) {
                $line_no++;
                if (preg_match('/^(<<<<<<< |=======\s*$|>>>>>>> )/', rtrim($line, "\r\n"))) {
                    $errors[] = $this->relative_path($file) . ':' . $line_no;
                    if (count($errors) >= 50) {
                        break 2;
                    }
                }
            }
            fclose($handle);
        }
        return $this->check_result($errors, array($checked . ' Dateien geprueft.'));
    }

    private function check_page_cache(): array
    {
        $dir = $this->base_dir . '/files/cache/content/full-page';
        if (!is_dir($dir)) {
            return $this->check_result(array(), array('Noch kein Gastseiten-Cache vorhanden.'));
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
        return $this->check_result($errors, array(count($files) . ' aktuelle Gastseiten in genau einer Generation.'));
    }

    private function check_php_syntax(int $timeout): array
    {
        $errors = array();
        $checked = 0;
        $deadline = microtime(true) + max(30, $timeout);
        foreach ($this->project_files(array('php')) as $file) {
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
            $source = @file_get_contents($file);
            if ($source === false) {
                $errors[] = $this->relative_path($file) . "\nDatei konnte nicht gelesen werden.";
                continue;
            }
            try {
                // TOKEN_PARSE verwendet den Parser der laufenden Installation,
                // fuehrt den Quellcode jedoch nicht aus. Damit entfallen unter
                // Windows hunderte kostspielige PHP-CLI-Prozessstarts.
                token_get_all($source, TOKEN_PARSE);
            } catch (\ParseError $exception) {
                $errors[] = $this->relative_path($file) . "\n" . $exception->getMessage();
                if (count($errors) >= 25) {
                    break;
                }
            }
        }
        return $this->check_result($errors, array($checked . ' PHP-Dateien ohne Syntaxfehler.'));
    }

    private function check_java_script_syntax(int $timeout): array
    {
        $node = $this->resolve_executable('node');
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
        foreach ($this->project_files(array('js')) as $file) {
            if (microtime(true) >= $deadline) {
                return array(
                    'status' => 'failed',
                    'exit_code' => 124,
                    'timed_out' => true,
                    'output' => 'JavaScript-Syntaxpruefung nach ' . $checked . ' Dateien abgebrochen: Gesamtzeit ueberschritten.',
                );
            }
            $checked++;
            $result = $this->run_process(array($node, '--check', $file), $this->base_dir, 20);
            if ((int)($result['exit_code'] ?? 1) !== 0) {
                $errors[] = $this->relative_path($file) . "\n" . trim((string)($result['output'] ?? ''));
                if (count($errors) >= 25) {
                    break;
                }
            }
        }
        return $this->check_result($errors, array($checked . ' JavaScript-Dateien ohne Syntaxfehler.'));
    }

    private function run_composer(array $arguments, int $timeout): array
    {
        $composer = $this->resolve_composer_command();
        if ($composer === null) {
            return array('status' => 'failed', 'exit_code' => 127, 'output' => 'Composer wurde nicht gefunden.');
        }
        return $this->run_process(array_merge($composer, $arguments), $this->base_dir . '/dbx', $timeout);
    }

    private function resolve_composer_command(): ?array
    {
        $candidate = $this->resolve_executable('composer');
        if ($candidate === null) {
            return null;
        }
        if (preg_match('/\.bat$/i', $candidate)) {
            $phar = dirname($candidate) . DIRECTORY_SEPARATOR . 'composer.phar';
            if (is_file($phar)) {
                $php = $this->resolve_php_cli_binary();
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
    private function resolve_php_cli_binary(): ?string
    {
        if ($this->php_cli_binary_cache !== null) {
            return $this->php_cli_binary_cache !== '' ? $this->php_cli_binary_cache : null;
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
        $directory = $this->base_dir;
        for ($level = 0; $level < 4; $level++) {
            $candidates[] = $directory . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $executable;
            $parent = dirname($directory);
            if ($parent === $directory) {
                break;
            }
            $directory = $parent;
        }
        $from_path = $this->resolve_executable('php');
        if ($from_path !== null) {
            $candidates[] = $from_path;
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate) && (PHP_OS_FAMILY === 'Windows' || is_executable($candidate))) {
                $this->php_cli_binary_cache = $candidate;
                return $candidate;
            }
        }
        $this->php_cli_binary_cache = '';
        return null;
    }

    private function run_process(array $command, string $cwd, int $timeout): array
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
        $timed_out = false;
        $last_exit = -1;
        while (true) {
            $this->append_output($output, (string)stream_get_contents($pipes[1]));
            $this->append_output($output, (string)stream_get_contents($pipes[2]));
            $status = proc_get_status($process);
            if (!$status['running']) {
                $last_exit = (int)$status['exitcode'];
                break;
            }
            if ((microtime(true) - $started) > max(1, $timeout)) {
                $timed_out = true;
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
        $this->append_output($output, (string)stream_get_contents($pipes[1]));
        $this->append_output($output, (string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closed_exit = proc_close($process);
        $exit_code = $timed_out ? 124 : ($last_exit >= 0 ? $last_exit : (int)$closed_exit);
        return array(
            'status' => $exit_code === 0 ? 'passed' : 'failed',
            'exit_code' => $exit_code,
            'timed_out' => $timed_out,
            'output' => $output,
        );
    }

    private function append_output(string &$output, string $chunk): void
    {
        if ($chunk === '') {
            return;
        }
        $output .= $chunk;
        if (strlen($output) > $this->max_output_bytes) {
            $half = intdiv($this->max_output_bytes, 2);
            $output = substr($output, 0, $half)
                . "\n... Ausgabe gekuerzt ...\n"
                . substr($output, -$half);
        }
    }

    private function project_files(array $extensions): array
    {
        $extensions = array_fill_keys(array_map('strtolower', $extensions), true);
        $files = array();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($this->base_dir, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $current): bool {
                    if (!$current->isDir()) {
                        return true;
                    }
                    $path = str_replace('\\', '/', $current->getPathname());
                    foreach (array('/.git', '/dbx/vendor', '/node_modules/', '/files/', '/tmp/', '/output/', '/reference/', '/.playwright-cli') as $excluded) {
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

    private function resolve_executable(string $name): ?string
    {
        $path_value = (string)getenv('PATH');
        $extensions = PHP_OS_FAMILY === 'Windows'
            ? array('.exe', '.bat', '.cmd', '')
            : array('');
        foreach (explode(PATH_SEPARATOR, $path_value) as $directory) {
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

    private function check_result(array $errors, array $success_lines): array
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
            'output' => "PASS\n" . implode("\n", $success_lines),
        );
    }

    private function calculate_totals(array $run): array
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

    private function result_summary(string $status, string $output, string $explicit): string
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

    private function clean_output(string $output): string
    {
        $output = preg_replace('/\x1B(?:[@-Z\\-_]|\[[0-?]*[ -\/]*[@-~])/', '', $output) ?? $output;
        $output = str_replace("\0", '', $output);
        if (strlen($output) > $this->max_output_bytes) {
            $output = substr($output, 0, $this->max_output_bytes) . "\n... Ausgabe gekuerzt ...";
        }
        return trim($output);
    }

    private function safe_project_file(string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '..')) {
            return null;
        }
        $candidate = realpath($this->base_dir . '/' . ltrim(str_replace('\\', '/', $relative), '/'));
        if ($candidate === false) {
            return null;
        }
        $normalized = str_replace('\\', '/', $candidate);
        return str_starts_with($normalized . '/', $this->base_dir . '/') ? $normalized : null;
    }

    private function relative_path(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        return str_starts_with($file, $this->base_dir . '/') ? substr($file, strlen($this->base_dir) + 1) : $file;
    }

    private function normalize_profile(string $profile): string
    {
        return $profile === 'quick' ? 'quick' : 'full';
    }

    private function valid_run_id(string $run_id): bool
    {
        return preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{10}$/', $run_id) === 1;
    }

    private function run_path(string $run_id): string
    {
        return rtrim($this->log_dir, '/\\') . DIRECTORY_SEPARATOR . $run_id . '.json';
    }

    private function write_run(array $run): void
    {
        $id = (string)($run['id'] ?? '');
        if (!$this->valid_run_id($id)) {
            throw new \RuntimeException('Ungueltige Testlauf-ID.');
        }
        $json = json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Testprotokoll konnte nicht serialisiert werden.');
        }
        $handle = @fopen($this->run_path($id), 'c+b');
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

    private function ensure_directory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Self-Test-Protokollverzeichnis konnte nicht angelegt werden.');
        }
    }

    private function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    private function duration_since(string $started): int
    {
        $time = strtotime($started);
        return $time === false ? 0 : max(0, (int)round((microtime(true) - $time) * 1000));
    }

    private function duration_between(string $started, string $finished): int
    {
        $a = strtotime($started);
        $b = strtotime($finished);
        return $a === false || $b === false ? 0 : max(0, ($b - $a) * 1000);
    }
}
