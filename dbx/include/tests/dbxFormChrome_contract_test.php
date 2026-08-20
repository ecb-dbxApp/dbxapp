<?php

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$modules = $root . DIRECTORY_SEPARATOR . 'modules';
$core_templates = $modules . DIRECTORY_SEPARATOR . 'dbx'
    . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm';
$errors = array();

$form_source = dbx_test_module_source_bundle($root . DIRECTORY_SEPARATOR . 'include'
    . DIRECTORY_SEPARATOR . 'dbxForm.class.php');
foreach (array(
    "public \$_tpl_form_bar = 'dbx|form-bar-default'",
    "public \$_tpl_form_footer = 'dbx|form-footer-default'",
    "add_rep('form:bar', \$bar)",
    "add_rep('form:message', '{obj:form_msg}')",
    "add_rep('form:footer', \$footer)",
) as $contract) {
    if (strpos($form_source, $contract) === false) {
        $errors[] = 'dbxForm-Vertrag fehlt: ' . $contract;
    }
}

foreach (array(
    'form-bar-default',
    'form-footer-default',
    'form-message-save-success',
    'form-message-save-error',
    'form-message-validation-error',
    'form-message-warning',
    'form-message-delete-success',
    'form-message-delete-error',
    'form-action-delete-title',
    'form-action-delete-hint',
) as $name) {
    $path = $core_templates . DIRECTORY_SEPARATOR . $name . '.htm';
    if (!is_file($path)) {
        $errors[] = 'Zentrales Form-Template fehlt: ' . basename($path);
    }
    foreach (array('_en', '_es') as $suffix) {
        $variant = $core_templates . DIRECTORY_SEPARATOR . $name . $suffix . '.htm';
        if (is_file($variant)) {
            $errors[] = 'Redundante Sprachkopie vorhanden: ' . basename($variant);
        }
    }
}

$migrated = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($modules, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'htm') {
        continue;
    }
    $html = (string)file_get_contents($file->getPathname());
    if (strpos($html, '[dbx:form]') === false) {
        continue;
    }
    if (strpos($html, '[tpl=dbx|module-bar]') !== false) {
        $errors[] = 'Fester module-bar-Include im Form-Template: '
            . str_replace('\\', '/', $file->getPathname());
    }
    if (strpos($html, '{form:bar}') !== false) {
        $migrated++;
        foreach (array('{form:message}', '{form:footer}') as $placeholder) {
            if (strpos($html, $placeholder) === false) {
                $errors[] = basename($file->getPathname()) . ': ' . $placeholder . ' fehlt';
            }
        }
    }
}

// Reporttemplates besitzen seit dem eigenen Report-Chrome-Vertrag bewusst
// keine Form-Platzhalter mehr und werden separat geprueft.
if ($migrated < 40) {
    $errors[] = 'Zu wenige Form-Templates verwenden den neuen Chrome-Vertrag.';
}

foreach (new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($modules, FilesystemIterator::SKIP_DOTS)
) as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.fd.php')) {
        continue;
    }
    $source = (string)file_get_contents($file->getPathname());
    foreach (array('save_success', 'save_error') as $key) {
        if (preg_match('/\\$messages\\s*\\[\\s*[\'\"]' . $key . '[\'\"]\\s*\\]/', $source)) {
            $errors[] = basename($file->getPathname()) . ': globale Meldung ' . $key;
        }
    }
}

if ($errors) {
    fwrite(STDERR, "Form-Chrome-Vertrag verletzt:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "OK: {$migrated} Form-Templates verwenden zentrale Bar, Meldung und Footer.\n";
