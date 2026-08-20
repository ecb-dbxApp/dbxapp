<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = array();
$services = array(
    'dbxAssetRegistry' => array('add_css', 'add_js', 'get_assets'),
    'dbxConfigStore' => array('cached', 'remember', 'forget', 'export_php_assignments'),
    'dbxMail' => array('send_message'),
    'dbxPresentation' => array(
        'get_design_skin_ids', 'normalize_skin', 'get_skin', 'get_skin_css',
        'get_skin_class', 'get_design_catalog', 'is_design',
    ),
    'dbxRequestPipeline' => array('run'),
    'dbxRuntime' => array('error_log_file', 'error_type', 'write_php_error_log'),
    'dbxSearchDefaults' => array('build'),
);

foreach ($services as $class => $methods) {
    $file = $root . '/include/' . $class . '.class.php';
    if (!is_file($file)) {
        $errors[] = "Kernel-Service fehlt: {$class}";
        continue;
    }
    $source = (string)file_get_contents($file);
    foreach ($methods as $method) {
        if (!preg_match('/public\s+function\s+' . preg_quote($method, '/') . '\s*\(/', $source)) {
            $errors[] = "{$class}::{$method} fehlt";
        }
    }
}

$api_files = array_merge(
    array($root . '/include/dbxApi.php'),
    glob($root . '/include/dbxApi*.trait.php') ?: array()
);
$api_source = '';
foreach ($api_files as $file) {
    $api_source .= (string)file_get_contents($file) . "\n";
}
$api_services = $services;
unset($api_services['dbxRequestPipeline']);
$moved_methods = array_merge(...array_values($api_services));
foreach ($moved_methods as $method) {
    if (preg_match('/public\s+function\s+' . preg_quote($method, '/') . '\s*\(/', $api_source)) {
        $errors[] = "Ausgelagerte Methode wieder in dbxApi: {$method}";
    }
}

$password_policy = (string)file_get_contents($root . '/include/dbxPasswordPolicy.class.php');
if (!preg_match('/public\s+static\s+function\s+generate\s*\(/', $password_policy)) {
    $errors[] = 'dbxPasswordPolicy::generate fehlt';
}
if (preg_match('/public\s+function\s+new_password\s*\(/', $api_source)) {
    $errors[] = 'Passworterzeugung wieder in dbxApi';
}

foreach (array('dbxAssetRegistry', 'dbxConfigStore', 'dbxPresentation', 'dbxRequestPipeline', 'dbxSearchDefaults') as $class) {
    if (!str_contains($api_source, "'{$class}'")) {
        $errors[] = "Interner Service nicht gegen myX-Override geschützt: {$class}";
    }
}

foreach (array('dbxAssetRegistry', 'dbxPresentation', 'dbxSearchDefaults') as $class) {
    $override = $root . '/modules/myX/sysclass/my' . substr($class, 3) . '.class.php';
    if (is_file($override)) {
        $errors[] = 'Interner Service besitzt unerwarteten myX-Override: ' . basename($override);
    }
}

// Permalink-Orchestrierung ist bewusst Kernel-Verantwortung und darf durch
// die Servicebereinigung nicht versehentlich in dbxContent verschoben werden.
foreach (array('get_content_permalink_mode', 'load_content_cache_classes', 'apply_content_permalink_redirect') as $method) {
    $found = str_contains($api_source, $method)
        || str_contains((string)file_get_contents($root . '/include/dbxWebAppRouting.trait.php'), $method)
        || str_contains((string)file_get_contents($root . '/include/dbxWebAppRedirect.trait.php'), $method)
        || str_contains((string)file_get_contents($root . '/include/dbxRequestPipeline.class.php'), $method);
    if (!$found) {
        $errors[] = "Kernel-Permalinkfunktion fehlt: {$method}";
    }
}

if ($errors !== array()) {
    fwrite(STDERR, "Kernel-Service-Vertrag verletzt:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "OK klare Kernel-Services; dbxApi bleibt schlank und Permalinks bleiben im Kernel.\n";
