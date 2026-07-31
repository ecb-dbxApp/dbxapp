<?php
namespace dbx\dbxSelfTest;

/** Web-Controller fuer Dashboard, AJAX-Orchestrierung und Protokolldownload. */
class dbxSelfTestController
{
    private const TOKEN_SCOPE = 'dbxSelfTest.actions';
    private ?dbxSelfTestRunner $runner = null;

    private function runner(): dbxSelfTestRunner
    {
        if (!$this->runner) {
            $this->runner = dbx()->get_include_obj('dbxSelfTestRunner', 'dbxSelfTest');
        }
        return $this->runner;
    }

    private function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    private function url(string $action, bool $tokenized = false, array $params = array()): string
    {
        $url = '?dbx_modul=dbxSelfTest&dbx_run1=' . rawurlencode($action);
        foreach ($params as $key => $value) {
            $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }
        if ($tokenized) {
            $url .= '&dbx_token=' . rawurlencode(dbx()->action_token(self::TOKEN_SCOPE));
        }
        return $url;
    }

    private function payload(): array
    {
        $raw = file_get_contents('php://input');
        $data = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : array();
        return is_array($data) ? $data : array();
    }

    private function requireToken(string $action): void
    {
        $token = (string)dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
        if (dbx()->check_action_token(self::TOKEN_SCOPE, $token)) {
            return;
        }
        dbx()->sys_msg('security', 'dbxSelfTest Token abgewiesen: ' . $action);
        http_response_code(403);
        dbx()->json_response(array('ok' => 0, 'error' => 'Sicherheitstoken ungueltig. Bitte Seite neu laden.'), true);
    }

    private function json(array $data): void
    {
        dbx()->json_response($data, true);
    }

    private function publicCatalog(): array
    {
        return array_map(static function (array $test): array {
            unset($test['handler']);
            return $test;
        }, $this->runner()->catalog('full'));
    }

    private function dashboard(): string
    {
        $catalog = $this->runner()->catalog('full');
        $quick = $this->runner()->catalog('quick');
        $categories = array_unique(array_column($catalog, 'category'));
        sort($categories);
        dbx()->set_system_var('dbx_title', 'System-Selbsttest');

        return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxSelfTest|selftest-dashboard', array(
            'test_count' => count($catalog),
            'quick_count' => count($quick),
            'category_count' => count($categories),
            'catalog_url' => $this->h($this->url('api_catalog')),
            'start_url' => $this->h($this->url('api_start', true)),
            'execute_url' => $this->h($this->url('api_execute', true)),
            'finish_url' => $this->h($this->url('api_finish', true)),
            'browser_result_url' => $this->h($this->url('api_browser_result', true)),
            'run_url' => $this->h($this->url('api_run')),
            'download_url' => $this->h($this->url('download')),
            'bar_title' => 'dbxSelfTest',
            'bar_subtitle' => 'Komplett-, Schnell- und Einzeltests mit dauerhaftem Protokoll.',
            'bar_icon' => 'bi-clipboard2-pulse',
            'bar_actions' => '<a class="btn btn-outline-secondary btn-sm" href="' . $this->h($this->url('dashboard')) . '"><i class="bi bi-arrow-clockwise"></i> Aktualisieren</a>',
            'bar_class' => 'dbx-module-bar',
            'bar_title_class' => 'dbx-module-bar-titleblock',
            'bar_title_pre' => '',
            'bar_title_heading_attrs' => '',
            'bar_middle' => '',
            'bar_extra' => '',
            'bar_actions_class' => 'dbx-module-bar-actions',
        ));
    }

    private function apiCatalog(): void
    {
        $this->json(array(
            'ok' => 1,
            'tests' => $this->publicCatalog(),
            'history' => $this->runner()->history(20),
        ));
    }

    private function apiStart(): void
    {
        $this->requireToken('api_start');
        $data = $this->payload();
        $profile = ($data['profile'] ?? '') === 'quick' ? 'quick' : 'full';
        $ids = is_array($data['test_ids'] ?? null) ? array_slice($data['test_ids'], 0, 500) : array();
        $mode = count($ids) === 1 ? 'single' : ($ids !== array() ? 'selection' : 'complete');
        try {
            $run = $this->runner()->startRun($profile, $ids, $mode);
            $this->json(array('ok' => 1, 'run' => $run));
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(array('ok' => 0, 'error' => $e->getMessage()));
        }
    }

    private function apiExecute(): void
    {
        $this->requireToken('api_execute');
        // Umfangreiche Systempruefungen (insbesondere php -l ueber das ganze
        // Projekt) duerfen nicht am normalen Web-Request-Limit abbrechen.
        @set_time_limit(360);
        $data = $this->payload();
        try {
            $result = $this->runner()->executeRunTest(
                (string)($data['run_id'] ?? ''),
                (string)($data['test_id'] ?? '')
            );
            $run = $this->runner()->loadRun((string)($data['run_id'] ?? ''));
            $this->json(array('ok' => 1, 'result' => $result, 'run' => $run));
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(array('ok' => 0, 'error' => $e->getMessage()));
        }
    }

    private function apiFinish(): void
    {
        $this->requireToken('api_finish');
        $data = $this->payload();
        try {
            $run = $this->runner()->finishRun(
                (string)($data['run_id'] ?? ''),
                !empty($data['aborted'])
            );
            $this->json(array('ok' => 1, 'run' => $run, 'history' => $this->runner()->history(20)));
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(array('ok' => 0, 'error' => $e->getMessage()));
        }
    }

    private function apiBrowserResult(): void
    {
        $this->requireToken('api_browser_result');
        $data = $this->payload();
        try {
            $result = $this->runner()->recordBrowserTestResult(
                (string)($data['run_id'] ?? ''),
                (string)($data['test_id'] ?? ''),
                is_array($data['result'] ?? null) ? $data['result'] : array()
            );
            $run = $this->runner()->loadRun((string)($data['run_id'] ?? ''));
            $this->json(array('ok' => 1, 'result' => $result, 'run' => $run));
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(array('ok' => 0, 'error' => $e->getMessage()));
        }
    }

    private function apiRun(): void
    {
        $id = (string)dbx()->get_modul_var('run_id', '', 'parameter|max=40');
        $run = $this->runner()->loadRun($id);
        if (!$run) {
            http_response_code(404);
            $this->json(array('ok' => 0, 'error' => 'Testlauf nicht gefunden.'));
        }
        $this->json(array('ok' => 1, 'run' => $run));
    }

    private function download(): void
    {
        $id = (string)dbx()->get_modul_var('run_id', '', 'parameter|max=40');
        $path = $this->runner()->runLogPath($id);
        if ($path === null) {
            http_response_code(404);
            echo 'Testprotokoll nicht gefunden.';
            exit;
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="dbx-selftest-' . $id . '.json"');
        header('Content-Length: ' . (string)filesize($path));
        readfile($path);
        exit;
    }

    public function run(): string
    {
        $action = (string)dbx()->get_modul_var('dbx_run1', 'dashboard', 'parameter');
        switch ($action) {
            case 'api_catalog':
                $this->apiCatalog();
                break;
            case 'api_start':
                $this->apiStart();
                break;
            case 'api_execute':
                $this->apiExecute();
                break;
            case 'api_finish':
                $this->apiFinish();
                break;
            case 'api_browser_result':
                $this->apiBrowserResult();
                break;
            case 'api_run':
                $this->apiRun();
                break;
            case 'download':
                $this->download();
                break;
        }
        return $this->dashboard();
    }
}
