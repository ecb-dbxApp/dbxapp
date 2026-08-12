<?php
namespace dbx\dbxSelfTest;

/** Web-Controller fuer das Testdashboard, AJAX-Orchestrierung und Protokolldownload. */
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

    /**
     * Die eine Testansicht: Kennzahlenzeile mit Verlauf-loeschen-Button,
     * Start-/Stopp-Steuerung mit Live-Fortschritt und ein sortier-/
     * filterbarer dbxReport ueber den vollstaendigen Testkatalog. Jede Zeile
     * zeigt den zuletzt bekannten Status; die Ausfuehrung selbst laeuft
     * client-seitig ueber die JSON-Aktionen dieses Controllers.
     */
    private function dashboard(int $clearedCount = -1): string
    {
        dbx()->set_system_var('dbx_title', 'System-Selbsttest');

        $runner = $this->runner();
        $catalog = $runner->catalog('full');
        $latest = $runner->history(1)[0] ?? null;
        $run = $latest ? $runner->loadRun((string)($latest['id'] ?? '')) : null;

        $lastResults = array();
        foreach ((array)($run['results'] ?? array()) as $result) {
            if (is_array($result) && (string)($result['test_id'] ?? '') !== '') {
                $lastResults[(string)$result['test_id']] = $result;
            }
        }

        $report = dbx()->get_system_obj('dbxReport');
        $report->init('selftest-dashboard', 'dbxSelfTest|selftest-dashboard');
        $report->_fd = 'dbxSelfTest|rpt-selftest-summary';
        $report->_dd = '';
        $report->_mode = 'table';
        $report->_action = $this->url('dashboard');
        $report->_pages = true;
        $report->_but_pagination = 7;
        $report->_create_row_select = true;
        $report->_create_row_edit = false;
        $report->_create_row_delete = false;
        $report->load_fd_messages();
        $report->add_module_bar(
            'dbxSelfTest',
            'bi-clipboard2-pulse',
            'Komplett-, Schnell- und Einzeltests mit dauerhaftem Protokoll.'
        );
        $report->add_rep('start_url', $this->h($this->url('api_start', true)));
        $report->add_rep('execute_url', $this->h($this->url('api_execute', true)));
        $report->add_rep('finish_url', $this->h($this->url('api_finish', true)));
        $report->add_rep('browser_result_url', $this->h($this->url('api_browser_result', true)));
        $report->create_selection_fields('dbxSelfTest|rpt-selftest-summary');

        if ($report->submit() && $report->errors()) {
            $report->_msg_error = 'Bitte Filtereingaben pruefen.';
        }

        $search = trim((string)$report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=64'));
        $status = (string)$report->get_fld_val('dbx_rstatus', 'all', 'parameter|max=24');
        $sort = (string)$report->get_fld_val('dbx_rsort', 'status', 'parameter|max=24');
        $direction = strtoupper((string)$report->get_fld_val('dbx_rdesc', 'ASC', 'parameter'));
        $pageSize = max(25, min(500, (int)$report->get_fld_val('dbx_rrows', 200, 'int')));
        $position = max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));

        $allowedSort = array('name', 'category', 'status', 'duration_ms');
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'status';
        }
        if (!in_array($direction, array('ASC', 'DESC'), true)) {
            $direction = 'ASC';
        }
        $allowedStatus = array('all', 'passed', 'failed', 'skipped');
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'all';
        }

        $allRows = array();
        foreach ($catalog as $test) {
            $id = (string)($test['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $known = $lastResults[$id] ?? null;
            $allRows[] = array(
                'id' => $id,
                'name' => (string)($test['name'] ?? $id),
                'category' => (string)($test['category'] ?? ''),
                'description' => (string)($test['description'] ?? ''),
                'relative_path' => (string)($test['relative_path'] ?? ''),
                'execution' => (string)($test['execution'] ?? 'server'),
                'timeout' => (int)($test['timeout'] ?? 30),
                'status' => $known ? (string)($known['status'] ?? 'pending') : 'pending',
                'duration_ms' => $known ? (int)($known['duration_ms'] ?? 0) : 0,
            );
        }

        $filteredRows = $allRows;
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $filteredRows = array_values(array_filter(
                $filteredRows,
                static function (array $row) use ($needle): bool {
                    return str_contains(mb_strtolower($row['name']), $needle)
                        || str_contains(mb_strtolower($row['category']), $needle)
                        || str_contains(mb_strtolower($row['relative_path']), $needle);
                }
            ));
        }
        if ($status !== 'all') {
            $filteredRows = array_values(array_filter(
                $filteredRows,
                static fn(array $row): bool => $row['status'] === $status
            ));
        }
        usort($filteredRows, static function (array $a, array $b) use ($sort, $direction): int {
            $cmp = $a[$sort] <=> $b[$sort];
            return $direction === 'DESC' ? -$cmp : $cmp;
        });

        $totals = is_array($run['totals'] ?? null) ? $run['totals'] : array();
        $passed = (int)($totals['passed'] ?? 0);
        $failed = (int)($totals['failed'] ?? 0);
        $skipped = (int)($totals['skipped'] ?? 0);
        $finishedAt = (string)($run['finished_at'] ?? ($run['started_at'] ?? ''));
        $tpl = dbx()->get_system_obj('dbxTPL');

        if (!$run) {
            $summaryLine = $report->get_fd_message('no_run');
            $summaryClass = 'alert-secondary';
            $summaryIcon = 'bi-info-circle';
        } else {
            $summaryLine = $report->format_fd_message(
                $skipped > 0 ? 'summary_line_with_skipped' : 'summary_line',
                array(
                    'date' => $report->php_datetime_usr($finishedAt),
                    'passed' => (string)$passed,
                    'failed' => (string)$failed,
                    'skipped' => (string)$skipped,
                )
            );
            $summaryClass = $failed > 0 ? 'alert-danger' : 'alert-success';
            $summaryIcon = $failed > 0 ? 'bi-exclamation-triangle' : 'bi-check-circle';
        }
        if ($clearedCount >= 0) {
            $report->_msg_success = $report->format_fd_message(
                'clear_history_success',
                array('count' => (string)$clearedCount)
            );
        } elseif ($filteredRows === array()) {
            $report->_msg_info = $report->get_fd_message('empty_result');
        }
        $report->add_rep('summary_line', $summaryLine);
        $report->add_rep('summary_class', $summaryClass);
        $report->add_rep('summary_icon', $summaryIcon);
        $report->add_rep(
            'clear_history_button',
            $latest ? $tpl->get_tpl('dbxSelfTest|selftest-clear-history-button', array(
                'delete_url' => $this->h($this->url('clear_history', true)),
                'delete_title' => $report->get_fd_message('clear_history_title'),
                'delete_question' => $report->get_fd_message('clear_history_question'),
                'delete_hint' => $report->get_fd_message('clear_history_hint'),
                'delete_label' => $report->get_fd_message('clear_history_label'),
            )) : ''
        );

        $rows = array_slice($filteredRows, $position, $pageSize);
        foreach ($rows as &$row) {
            $tooltipHtml = '<strong>' . $this->h($row['name']) . '</strong>';
            if ($row['description'] !== '') {
                $tooltipHtml .= '<br><small>' . $this->h($row['description']) . '</small>';
            }
            $statusLabel = $report->get_fd_message('status_' . $row['status'], $row['status']);
            $statusClass = $row['status'] === 'passed'
                ? 'text-bg-success'
                : ($row['status'] === 'failed' ? 'text-bg-danger' : 'text-bg-secondary');

            $row['name'] = $tpl->get_tpl('dbxSelfTest|selftest-run-name', array(
                'name' => $row['name'],
                'tooltip_html' => $tooltipHtml,
                'relative_path' => $row['relative_path'],
                'execution' => $row['execution'],
                'test_path' => $row['relative_path'],
                'timeout' => (string)$row['timeout'],
            ));
            $badgeHtml = $tpl->get_tpl('dbxSelfTest|selftest-status-badge', array(
                'class' => $statusClass,
                'label' => $statusLabel,
            ));
            $row['status'] = '<span data-selftest-status-for="' . $this->h($row['id']) . '">' . $badgeHtml . '</span>';
            $durationText = $row['duration_ms'] > 0
                ? number_format($row['duration_ms'], 0, ',', '.') . ' ms'
                : '–';
            $row['duration_ms'] = '<span data-selftest-duration-for="' . $this->h($row['id']) . '">'
                . $this->h($durationText) . '</span>';
        }
        unset($row);

        $report->_rflds = array(
            'name' => $report->get_fd_message('column_name'),
            'category' => $report->get_fd_message('column_category'),
            'status' => $report->get_fd_message('column_status'),
            'duration_ms' => $report->get_fd_message('column_duration'),
        );
        $report->_rpt_format = array(
            'name' => 'html',
            'status' => 'html',
            'duration_ms' => 'html',
        );
        $report->_rrows = $pageSize;
        $report->_rpos = $position;
        $report->_count_all = count($allRows);
        $report->_rcount = count($filteredRows);
        $report->_rdata = $rows;

        return $report->run();
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
            $this->json(array('ok' => 1, 'run' => $run));
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
            case 'clear_history':
                $this->requireToken('clear_history');
                return $this->dashboard($this->runner()->clearHistory());
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
            case 'download':
                $this->download();
                break;
        }
        return $this->dashboard();
    }
}
