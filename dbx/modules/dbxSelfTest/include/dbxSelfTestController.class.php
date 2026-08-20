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
        if ($tokenized) {
            $params['dbx_token'] = dbx()->action_token(self::TOKEN_SCOPE);
        }
        return dbx()->append_url_params($url, $params);
    }

    private function payload(): array
    {
        return dbx()->get_json_request();
    }

    private function require_token(string $action): void
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
    private function dashboard(int $cleared_count = -1): string
    {
        dbx()->set_system_var('dbx_title', 'System-Selbsttest');

        $runner = $this->runner();
        $catalog = $runner->catalog('full');
        $latest = $runner->history(1)[0] ?? null;
        $run = $latest ? $runner->load_run((string)($latest['id'] ?? '')) : null;

        $last_results = array();
        foreach ((array)($run['results'] ?? array()) as $result) {
            if (is_array($result) && (string)($result['test_id'] ?? '') !== '') {
                $last_results[(string)$result['test_id']] = $result;
            }
        }

        $report = dbx()->get_system_obj('dbxReport');
        $report->init('selftest-dashboard', 'dbxSelfTest|selftest-dashboard');
        $report
            ->set_data_source('', 'dbxSelfTest|rpt-selftest-summary')
            ->set_mode('table')
            ->set_action($this->url('dashboard'))
            ->set_pagination(true, 7)
            ->set_table_actions(array('select'));
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
        $page_size = max(25, min(500, (int)$report->get_fld_val('dbx_rrows', 200, 'int')));
        $position = max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));

        $allowed_sort = array('name', 'category', 'status', 'duration_ms');
        if (!in_array($sort, $allowed_sort, true)) {
            $sort = 'status';
        }
        if (!in_array($direction, array('ASC', 'DESC'), true)) {
            $direction = 'ASC';
        }
        $allowed_status = array('all', 'passed', 'failed', 'skipped');
        if (!in_array($status, $allowed_status, true)) {
            $status = 'all';
        }

        $all_rows = array();
        foreach ($catalog as $test) {
            $id = (string)($test['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $known = $last_results[$id] ?? null;
            $all_rows[] = array(
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

        $filtered_rows = $all_rows;
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $filtered_rows = array_values(array_filter(
                $filtered_rows,
                static function (array $row) use ($needle): bool {
                    return str_contains(mb_strtolower($row['name']), $needle)
                        || str_contains(mb_strtolower($row['category']), $needle)
                        || str_contains(mb_strtolower($row['relative_path']), $needle);
                }
            ));
        }
        if ($status !== 'all') {
            $filtered_rows = array_values(array_filter(
                $filtered_rows,
                static fn(array $row): bool => $row['status'] === $status
            ));
        }
        usort($filtered_rows, static function (array $a, array $b) use ($sort, $direction): int {
            $cmp = $a[$sort] <=> $b[$sort];
            return $direction === 'DESC' ? -$cmp : $cmp;
        });

        $totals = is_array($run['totals'] ?? null) ? $run['totals'] : array();
        $passed = (int)($totals['passed'] ?? 0);
        $failed = (int)($totals['failed'] ?? 0);
        $skipped = (int)($totals['skipped'] ?? 0);
        $finished_at = (string)($run['finished_at'] ?? ($run['started_at'] ?? ''));
        $tpl = dbx()->get_system_obj('dbxTPL');

        if (!$run) {
            $summary_line = $report->get_fd_message('no_run');
            $summary_class = 'alert-secondary';
            $summary_icon = 'bi-info-circle';
        } else {
            $summary_line = $report->format_fd_message(
                $skipped > 0 ? 'summary_line_with_skipped' : 'summary_line',
                array(
                    'date' => $report->php_datetime_usr($finished_at),
                    'passed' => (string)$passed,
                    'failed' => (string)$failed,
                    'skipped' => (string)$skipped,
                )
            );
            $summary_class = $failed > 0 ? 'alert-danger' : 'alert-success';
            $summary_icon = $failed > 0 ? 'bi-exclamation-triangle' : 'bi-check-circle';
        }
        if ($cleared_count >= 0) {
            $report->_msg_success = $report->format_fd_message(
                'clear_history_success',
                array('count' => (string)$cleared_count)
            );
        } elseif ($filtered_rows === array()) {
            $report->_msg_info = $report->get_fd_message('empty_result');
        }
        $report->add_rep('summary_line', $summary_line);
        $report->add_rep('summary_class', $summary_class);
        $report->add_rep('summary_icon', $summary_icon);
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

        $rows = array_slice($filtered_rows, $position, $page_size);
        foreach ($rows as &$row) {
            $tooltip_html = '<strong>' . $this->h($row['name']) . '</strong>';
            if ($row['description'] !== '') {
                $tooltip_html .= '<br><small>' . $this->h($row['description']) . '</small>';
            }
            $status_label = $report->get_fd_message('status_' . $row['status'], $row['status']);
            $status_class = $row['status'] === 'passed'
                ? 'text-bg-success'
                : ($row['status'] === 'failed' ? 'text-bg-danger' : 'text-bg-secondary');

            $row['name'] = $tpl->get_tpl('dbxSelfTest|selftest-run-name', array(
                'name' => $row['name'],
                'tooltip_html' => $tooltip_html,
                'relative_path' => $row['relative_path'],
                'execution' => $row['execution'],
                'test_path' => $row['relative_path'],
                'timeout' => (string)$row['timeout'],
            ));
            $badge_html = $tpl->get_tpl('dbxSelfTest|selftest-status-badge', array(
                'class' => $status_class,
                'label' => $status_label,
            ));
            $row['status'] = '<span data-selftest-status-for="' . $this->h($row['id']) . '">' . $badge_html . '</span>';
            $duration_text = $row['duration_ms'] > 0
                ? number_format($row['duration_ms'], 0, ',', '.') . ' ms'
                : '–';
            $row['duration_ms'] = '<span data-selftest-duration-for="' . $this->h($row['id']) . '">'
                . $this->h($duration_text) . '</span>';
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
        $report->_rrows = $page_size;
        $report->_rpos = $position;
        $report->_count_all = count($all_rows);
        $report->_rcount = count($filtered_rows);
        $report->_rdata = $rows;

        return $report->run();
    }

    private function api_start(): void
    {
        $this->require_token('api_start');
        $data = $this->payload();
        $profile = ($data['profile'] ?? '') === 'quick' ? 'quick' : 'full';
        $ids = is_array($data['test_ids'] ?? null) ? array_slice($data['test_ids'], 0, 500) : array();
        $mode = count($ids) === 1 ? 'single' : ($ids !== array() ? 'selection' : 'complete');
        try {
            $run = $this->runner()->start_run($profile, $ids, $mode);
            $this->json(array('ok' => 1, 'run' => $run));
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(array('ok' => 0, 'error' => $e->getMessage()));
        }
    }

    private function api_execute(): void
    {
        $this->require_token('api_execute');
        // Umfangreiche Systempruefungen (insbesondere php -l ueber das ganze
        // Projekt) duerfen nicht am normalen Web-Request-Limit abbrechen.
        @set_time_limit(360);
        $data = $this->payload();
        try {
            $result = $this->runner()->execute_run_test(
                (string)($data['run_id'] ?? ''),
                (string)($data['test_id'] ?? '')
            );
            $run = $this->runner()->load_run((string)($data['run_id'] ?? ''));
            $this->json(array('ok' => 1, 'result' => $result, 'run' => $run));
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(array('ok' => 0, 'error' => $e->getMessage()));
        }
    }

    private function api_finish(): void
    {
        $this->require_token('api_finish');
        $data = $this->payload();
        try {
            $run = $this->runner()->finish_run(
                (string)($data['run_id'] ?? ''),
                !empty($data['aborted'])
            );
            $this->json(array('ok' => 1, 'run' => $run));
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(array('ok' => 0, 'error' => $e->getMessage()));
        }
    }

    private function api_browser_result(): void
    {
        $this->require_token('api_browser_result');
        $data = $this->payload();
        try {
            $result = $this->runner()->record_browser_test_result(
                (string)($data['run_id'] ?? ''),
                (string)($data['test_id'] ?? ''),
                is_array($data['result'] ?? null) ? $data['result'] : array()
            );
            $run = $this->runner()->load_run((string)($data['run_id'] ?? ''));
            $this->json(array('ok' => 1, 'result' => $result, 'run' => $run));
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(array('ok' => 0, 'error' => $e->getMessage()));
        }
    }

    private function download(): void
    {
        $id = (string)dbx()->get_modul_var('run_id', '', 'parameter|max=40');
        $path = $this->runner()->run_log_path($id);
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
                $this->require_token('clear_history');
                return $this->dashboard($this->runner()->clear_history());
            case 'api_start':
                $this->api_start();
                break;
            case 'api_execute':
                $this->api_execute();
                break;
            case 'api_finish':
                $this->api_finish();
                break;
            case 'api_browser_result':
                $this->api_browser_result();
                break;
            case 'download':
                $this->download();
                break;
        }
        return $this->dashboard();
    }
}
