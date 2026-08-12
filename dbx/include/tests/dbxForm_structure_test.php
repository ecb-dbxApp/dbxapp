<?php

/**
 * Statischer Regressionstest für Formular-Templates und kritische Shop-Aktionen.
 *
 * Der Test benötigt weder Datenbank noch Webserver. Er stellt sicher, dass
 * aktive Templates ausgeglichene, nicht verschachtelte Form-Tags besitzen und
 * dass die zuletzt migrierten Formulare ihre zentralen dbxForm-Platzhalter
 * nicht versehentlich verlieren.
 */

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$moduleRoot = $root . DIRECTORY_SEPARATOR . 'modules';
$errors = array();
$checked = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'htm') {
        continue;
    }

    $path = str_replace('\\', '/', $file->getPathname());
    $relativePath = '/' . ltrim(str_replace(
        '\\',
        '/',
        substr($file->getPathname(), strlen($moduleRoot))
    ), '/');
    if (strpos($relativePath, '/vendor/') !== false
        || strpos($relativePath, '/add_ons/') !== false
        || strpos($relativePath, '/work/') !== false) {
        continue;
    }
    if (strpos($path, '/modules/dbx/tpl/htm/frame-report-head.htm') !== false
        || strpos($path, '/modules/dbx/tpl/htm/frame-report-foot.htm') !== false) {
        // dbxReport setzt diese beiden Rahmenfragmente gemeinsam zusammen.
        continue;
    }

    $html = (string)file_get_contents($file->getPathname());
    $html = (string)preg_replace('/<!--.*?-->/s', '', $html);
    if (!preg_match('/<\/?form\b/i', $html)) {
        continue;
    }

    $checked++;
    preg_match_all('/<\/?form\b[^>]*>/i', $html, $matches);
    $depth = 0;
    foreach ($matches[0] as $tag) {
        if (preg_match('/^<\s*\/form\b/i', $tag)) {
            $depth--;
            if ($depth < 0) {
                $errors[] = $path . ': schließendes </form> ohne Öffnung';
                $depth = 0;
            }
            continue;
        }

        $depth++;
        if ($depth > 1) {
            $errors[] = $path . ': verschachteltes <form>';
        }
    }
    if ($depth !== 0) {
        $errors[] = $path . ': Form-Tags sind nicht ausgeglichen';
    }
}

$requiredTemplates = array(
    'dbxAdmin/tpl/htm/fdedit-create-from-dd.htm',
    'dbxContent_admin/tpl/htm/cms-media-upload-form.htm',
    'dbxContent_admin/tpl/htm/cms-external-video-form.htm',
    'dbxKi/tpl/htm/ki-briefing-page-create.htm',
    'dbxKi/tpl/htm/ki-briefing-page-update.htm',
    'dbxKi/tpl/htm/ki-briefing-page-translation.htm',
    'dbxKi/tpl/htm/ki-bundle-start.htm',
    'dbxKi/tpl/htm/ki-module-briefing.htm',
    'dbxKi/tpl/htm/ki-module-bundle-import.htm',
    'dbxKi/tpl/htm/ki-translation-sync-all.htm',
    'dbxShop_admin/tpl/htm/shop-product-channel-mapping.htm',
    'dbxShop/tpl/htm/shop-cart-report.htm',
    'dbxShop/tpl/htm/shop-checkout-form.htm',
    'dbxShop/tpl/htm/shop-withdrawal-form.htm',
    'dbxWorkflow/tpl/htm/workflow-review.htm',
    'dbxWorkflow/tpl/htm/workflow-step-choice.htm',
    'dbxWorkflow_admin/tpl/htm/workflow-bind-generator.htm',
);

foreach ($requiredTemplates as $relative) {
    $path = $moduleRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $html = is_file($path) ? (string)file_get_contents($path) : '';
    if ($html === '') {
        $errors[] = $relative . ': Template fehlt';
        continue;
    }
    if (strpos($html, '[dbx:form]') === false) {
        $errors[] = $relative . ': [dbx:form] für Security-/Restfelder fehlt';
    }
}

foreach (array(
    'dbxAdmin/tpl/htm/fdedit-create-from-dd.htm',
    'dbxShop_admin/tpl/htm/shop-product-channel-mapping.htm',
    'dbxShop/tpl/htm/shop-checkout-form.htm',
    'dbxShop/tpl/htm/shop-withdrawal-form.htm',
) as $relative) {
    $path = $moduleRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $html = is_file($path) ? (string)file_get_contents($path) : '';
    if (strpos($html, '{obj:form_msg}') === false) {
        $errors[] = $relative . ': zentraler dbxForm-Meldungsplatz fehlt';
    }
}

$shopServicePath = $moduleRoot . DIRECTORY_SEPARATOR . 'dbxShop' . DIRECTORY_SEPARATOR . 'include'
    . DIRECTORY_SEPARATOR . 'dbxShopService.class.php';
$shopService = dbx_test_module_source_bundle($shopServicePath);
foreach (array(
    '$cartReport->submit()' => 'Warenkorb-Report',
    '$buyForm->submit()' => 'Add-to-cart-Formular',
    '$row = $this->repo()->saveWithdrawal($values)' => 'Widerrufsformular',
    'if ($form->submit())' => 'dbxForm-Submit',
) as $needle => $label) {
    if (strpos($shopService, $needle) === false) {
        $errors[] = 'dbxShopService: Token-Guard fehlt für ' . $label;
    }
}

$shopAdminPath = $moduleRoot . DIRECTORY_SEPARATOR . 'dbxShop_admin' . DIRECTORY_SEPARATOR . 'include'
    . DIRECTORY_SEPARATOR . 'dbxShopAdmin.class.php';
$shopAdmin = dbx_test_module_source_bundle($shopAdminPath);
foreach (array(
    '$form->submit()' => 'zentraler Karten-Token-Guard',
    '$this->postedFormError' => 'sichtbare Karten-Fehlermeldung',
    '$content .= $this->shopMediaFormTemplates($this->shopMediaConfig())' => 'Medienformulare außerhalb der Kartenform',
) as $needle => $label) {
    if (strpos($shopAdmin, $needle) === false) {
        $errors[] = 'dbxShopAdmin: ' . $label . ' fehlt';
    }
}
foreach (array('productImagesPanel', 'productGroupImagePanel') as $method) {
    if (preg_match(
        '/private function ' . preg_quote($method, '/') . '\b.*?(?=\n\s*private function )/s',
        $shopAdmin,
        $methodMatch
    ) && strpos($methodMatch[0], 'shopMediaFormTemplates(') !== false) {
        $errors[] = 'dbxShopAdmin: Medienformular ist noch in ' . $method . ' verschachtelt';
    }
}

$cmsJs = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'lib'
    . DIRECTORY_SEPARATOR . 'cms.js');
$cmsJsWithoutComments = (string)preg_replace('~/\*.*?\*/|//[^\r\n]*~s', '', $cmsJs);
if (preg_match('/[\'"`][^\'"`]*<form\b/i', $cmsJsWithoutComments)) {
    $errors[] = 'cms.js: Formular-Markup wird wieder in JavaScript erzeugt';
}

$editorClass = (string)file_get_contents(
    $moduleRoot . DIRECTORY_SEPARATOR . 'dbxEditor' . DIRECTORY_SEPARATOR . 'dbxEditor.class.php'
);
$editorTemplate = (string)file_get_contents(
    $moduleRoot . DIRECTORY_SEPARATOR . 'dbxEditor' . DIRECTORY_SEPARATOR . 'tpl'
    . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'editor.htm'
);
$aceJs = (string)file_get_contents(
    $root . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'ace.js'
);
foreach (array(
    array($editorClass, "array('save', 'delete', 'rename', 'copy')", 'dbxEditor: gemeinsamer Mutations-Guard fehlt'),
    array($editorClass, '$form->submit()', 'dbxEditor: dbxForm-Submit-Prüfung fehlt'),
    array($editorTemplate, 'dbx-editor-security', 'dbxEditor: Sicherheitstoken fehlt im Editor-Template'),
    array($aceJs, 'function requestMutation(data)', 'ace.js: zentrale Mutationsfunktion fehlt'),
    array($aceJs, "method: 'POST'", 'ace.js: Mutationen werden nicht per POST gesendet'),
) as $check) {
    if (strpos($check[0], $check[1]) === false) {
        $errors[] = $check[2];
    }
}
if (strpos($aceJs, 'dbx_run1=delete&file=') !== false
    || strpos($aceJs, "requestJson('?dbx_modul=dbxEditor&dbx_run1=rename") !== false
    || strpos($aceJs, "requestJson('?dbx_modul=dbxEditor&dbx_run1=copy") !== false) {
    $errors[] = 'ace.js: schreibende Editor-Aktion verwendet wieder einen GET-Aufruf';
}

$formClass = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxForm.class.php');
if (!preg_match('/function get_security_data\(\).*?\$this->store_sysdata\(\)/s', $formClass)) {
    $errors[] = 'dbxForm: AJAX-Folgetoken wird nicht unmittelbar in der Session gespeichert';
}

if ($checked === 0) {
    $errors[] = 'Keine aktiven Formular-Templates gefunden';
}

if ($errors) {
    fwrite(STDERR, "Formular-Strukturprüfung fehlgeschlagen:\n - " . implode("\n - ", array_unique($errors)) . "\n");
    exit(1);
}

echo "OK: {$checked} Formular-Templates sind ausgeglichen, nicht verschachtelt und die migrierten Formulare sind abgesichert.\n";
