<?php
/**
 * Architekturvertrag für die Fehlerprotokoll-Anzeige im Admin-Dashboard.
 */

$module_dir = dirname(__DIR__);
require_once dirname(__DIR__, 3) . '/include/tests/dbxModuleSourceBundle.php';
$dashboard = dbx_test_module_source_bundle(
    $module_dir . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxDashboard.class.php'
);
$sys_msg = (string)file_get_contents(
    $module_dir . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxSysMsg.class.php'
);
$template = (string)file_get_contents(
    $module_dir . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm'
    . DIRECTORY_SEPARATOR . 'admin-dashboard-error-log.htm'
);
$css = (string)file_get_contents(
    $module_dir . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'css'
    . DIRECTORY_SEPARATOR . 'admin-dashboard.css'
);

$required_dashboard_parts = array(
    "health_percent = 0",
    "'health_error_log'",
    "dbx()->action_url(",
    "dbx_do=delete_error_log&rid=error_log",
    "htmlspecialchars(",
    "ENT_QUOTES | ENT_SUBSTITUTE",
    "admin-dashboard-status",
);
foreach ($required_dashboard_parts as $part) {
    if (strpos($dashboard, $part) === false) {
        fwrite(STDERR, "Dashboard-Fehlerlog-Vertrag fehlt: {$part}\n");
        exit(1);
    }
}

$required_service_parts = array(
    "public function get_error_log_file(): string",
    "dbx()->get_file_dir() . 'dbxError.log'",
    "public function error_log_exists(): bool",
    "public function delete_error_log(): string",
    "@unlink(\$file)",
    "clearstatcache(true, \$file)",
);
foreach ($required_service_parts as $part) {
    if (strpos($sys_msg, $part) === false) {
        fwrite(STDERR, "Zentraler Fehlerlog-Servicevertrag fehlt: {$part}\n");
        exit(1);
    }
}

if (strpos($sys_msg, "get_modul_var('file'") !== false) {
    fwrite(STDERR, "Der Fehlerlog-Pfad darf nicht aus Request-Daten stammen.\n");
    exit(1);
}

foreach (array(
    'dbx-admin-dashboard-error-log-content',
    'dbxConfirm',
    'data-confirm-buttons="yesno"',
    '{delete_action}',
    '{content}',
) as $part) {
    if (strpos($template, $part) === false) {
        fwrite(STDERR, "Fehlerlog-Templatevertrag fehlt: {$part}\n");
        exit(1);
    }
}

foreach (array(
    '.dbx-admin-dashboard-health.is-error',
    '.dbx-admin-dashboard-error-log-content',
    'max-height: 320px',
    'overflow: auto',
    'resize: vertical',
) as $part) {
    if (strpos($css, $part) === false) {
        fwrite(STDERR, "Fehlerlog-CSS-Vertrag fehlt: {$part}\n");
        exit(1);
    }
}

$load_messages = static function (string $file): array {
    $messages = array();
    $fields = array();
    include $file;
    return $messages;
};

$fd_dir = $module_dir . DIRECTORY_SEPARATOR . 'fd' . DIRECTORY_SEPARATOR;
$message_sets = array(
    'de' => $load_messages($fd_dir . 'admin-dashboard-status.fd.php'),
    'en' => $load_messages($fd_dir . 'admin-dashboard-status_en.fd.php'),
    'es' => $load_messages($fd_dir . 'admin-dashboard-status_es.fd.php'),
);
$reference_keys = array_keys($message_sets['de']);
sort($reference_keys);

foreach ($message_sets as $language => $messages) {
    $keys = array_keys($messages);
    sort($keys);
    if ($keys !== $reference_keys) {
        fwrite(STDERR, "FD-Schlüssel weichen für {$language} ab.\n");
        exit(1);
    }

    foreach (array(
        'health_error',
        'error_log_title',
        'error_log_delete_confirm',
        'error_log_deleted',
        'error_log_delete_error',
    ) as $key) {
        if (trim((string)($messages[$key] ?? '')) === '') {
            fwrite(STDERR, "FD-Meldung {$key} fehlt für {$language}.\n");
            exit(1);
        }
    }
}

echo "OK: Dashboard-Fehlerstatus, sicherer Log-Scroller, zentrale Löschung und Sprach-FDs\n";
