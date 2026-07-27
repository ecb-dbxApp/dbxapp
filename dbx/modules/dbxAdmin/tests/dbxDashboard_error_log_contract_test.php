<?php
/**
 * Architekturvertrag für die Fehlerprotokoll-Anzeige im Admin-Dashboard.
 */

$moduleDir = dirname(__DIR__);
$dashboard = (string)file_get_contents(
    $moduleDir . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxDashboard.class.php'
);
$sysMsg = (string)file_get_contents(
    $moduleDir . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxSysMsg.class.php'
);
$template = (string)file_get_contents(
    $moduleDir . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm'
    . DIRECTORY_SEPARATOR . 'admin-dashboard-error-log.htm'
);
$css = (string)file_get_contents(
    $moduleDir . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'css'
    . DIRECTORY_SEPARATOR . 'admin-dashboard.css'
);

$requiredDashboardParts = array(
    "healthPercent = 0",
    "'health_error_log'",
    "dbx()->action_url(",
    "dbx_do=delete_error_log&rid=error_log",
    "htmlspecialchars(",
    "ENT_QUOTES | ENT_SUBSTITUTE",
    "admin-dashboard-status",
);
foreach ($requiredDashboardParts as $part) {
    if (strpos($dashboard, $part) === false) {
        fwrite(STDERR, "Dashboard-Fehlerlog-Vertrag fehlt: {$part}\n");
        exit(1);
    }
}

$requiredServiceParts = array(
    "public function get_error_log_file(): string",
    "dbx()->get_file_dir() . 'dbxError.log'",
    "public function error_log_exists(): bool",
    "public function delete_error_log(): string",
    "@unlink(\$file)",
    "clearstatcache(true, \$file)",
);
foreach ($requiredServiceParts as $part) {
    if (strpos($sysMsg, $part) === false) {
        fwrite(STDERR, "Zentraler Fehlerlog-Servicevertrag fehlt: {$part}\n");
        exit(1);
    }
}

if (strpos($sysMsg, "get_modul_var('file'") !== false) {
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

$loadMessages = static function (string $file): array {
    $messages = array();
    $fields = array();
    include $file;
    return $messages;
};

$fdDir = $moduleDir . DIRECTORY_SEPARATOR . 'fd' . DIRECTORY_SEPARATOR;
$messageSets = array(
    'de' => $loadMessages($fdDir . 'admin-dashboard-status.fd.php'),
    'en' => $loadMessages($fdDir . 'admin-dashboard-status_en.fd.php'),
    'es' => $loadMessages($fdDir . 'admin-dashboard-status_es.fd.php'),
);
$referenceKeys = array_keys($messageSets['de']);
sort($referenceKeys);

foreach ($messageSets as $language => $messages) {
    $keys = array_keys($messages);
    sort($keys);
    if ($keys !== $referenceKeys) {
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
