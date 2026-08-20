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
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$module_root = $root . DIRECTORY_SEPARATOR . 'modules';
$errors = array();
$checked = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($module_root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'htm') {
        continue;
    }

    $path = str_replace('\\', '/', $file->getPathname());
    $relative_path = '/' . ltrim(str_replace(
        '\\',
        '/',
        substr($file->getPathname(), strlen($module_root))
    ), '/');
    if (strpos($relative_path, '/vendor/') !== false
        || strpos($relative_path, '/add_ons/') !== false
        || strpos($relative_path, '/work/') !== false) {
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

$required_templates = array(
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

foreach ($required_templates as $relative) {
    $path = $module_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
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
    $path = $module_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $html = is_file($path) ? (string)file_get_contents($path) : '';
    if (strpos($html, '{form:message}') === false) {
        $errors[] = $relative . ': zentraler dbxForm-Meldungsplatz fehlt';
    }
}

$shop_service_path = $module_root . DIRECTORY_SEPARATOR . 'dbxShop' . DIRECTORY_SEPARATOR . 'include'
    . DIRECTORY_SEPARATOR . 'dbxShopService.class.php';
$shop_service = dbx_test_module_source_bundle($shop_service_path);
foreach (array(
    '$cart_report->submit()' => 'Warenkorb-Report',
    '$buy_form->submit()' => 'Add-to-cart-Formular',
    '$row = $this->repo()->save_withdrawal($values)' => 'Widerrufsformular',
    'if ($form->submit())' => 'dbxForm-Submit',
) as $needle => $label) {
    if (strpos($shop_service, $needle) === false) {
        $errors[] = 'dbxShopService: Token-Guard fehlt für ' . $label;
    }
}

$shop_admin_path = $module_root . DIRECTORY_SEPARATOR . 'dbxShop_admin' . DIRECTORY_SEPARATOR . 'include'
    . DIRECTORY_SEPARATOR . 'dbxShopAdmin.class.php';
$shop_admin = dbx_test_module_source_bundle($shop_admin_path);
foreach (array(
    '$form->submit()' => 'zentraler Karten-Token-Guard',
    '$this->posted_form_error' => 'sichtbare Karten-Fehlermeldung',
    '$content .= $this->shop_media_form_templates($this->shop_media_config())' => 'Medienformulare außerhalb der Kartenform',
) as $needle => $label) {
    if (strpos($shop_admin, $needle) === false) {
        $errors[] = 'dbxShopAdmin: ' . $label . ' fehlt';
    }
}
foreach (array('product_images_panel', 'product_group_image_panel') as $method) {
    if (preg_match(
        '/private function ' . preg_quote($method, '/') . '\b.*?(?=\n\s*private function )/s',
        $shop_admin,
        $method_match
    ) && strpos($method_match[0], 'shop_media_form_templates(') !== false) {
        $errors[] = 'dbxShopAdmin: Medienformular ist noch in ' . $method . ' verschachtelt';
    }
}

$cms_js = (string)file_get_contents($module_root . DIRECTORY_SEPARATOR . 'dbxContent_admin'
    . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'cms.js');
$cms_js_without_comments = (string)preg_replace('~/\*.*?\*/|//[^\r\n]*~s', '', $cms_js);
if (preg_match('/[\'"`][^\'"`]*<form\b/i', $cms_js_without_comments)) {
    $errors[] = 'cms.js: Formular-Markup wird wieder in JavaScript erzeugt';
}

$editor_class = (string)file_get_contents(
    $module_root . DIRECTORY_SEPARATOR . 'dbxEditor' . DIRECTORY_SEPARATOR . 'dbxEditor.class.php'
);
$editor_template = (string)file_get_contents(
    $module_root . DIRECTORY_SEPARATOR . 'dbxEditor' . DIRECTORY_SEPARATOR . 'tpl'
    . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'editor.htm'
);
$ace_js = (string)file_get_contents(
    $module_root . DIRECTORY_SEPARATOR . 'dbxEditor' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'ace.js'
);
foreach (array(
    array($editor_class, "array('save', 'delete', 'rename', 'copy')", 'dbxEditor: gemeinsamer Mutations-Guard fehlt'),
    array($editor_class, '$form->submit()', 'dbxEditor: dbxForm-Submit-Prüfung fehlt'),
    array($editor_template, 'dbx-editor-security', 'dbxEditor: Sicherheitstoken fehlt im Editor-Template'),
    array($ace_js, 'function requestMutation(data)', 'ace.js: zentrale Mutationsfunktion fehlt'),
    array($ace_js, "method: 'POST'", 'ace.js: Mutationen werden nicht per POST gesendet'),
) as $check) {
    if (strpos($check[0], $check[1]) === false) {
        $errors[] = $check[2];
    }
}
if (strpos($ace_js, 'dbx_run1=delete&file=') !== false
    || strpos($ace_js, "requestJson('?dbx_modul=dbxEditor&dbx_run1=rename") !== false
    || strpos($ace_js, "requestJson('?dbx_modul=dbxEditor&dbx_run1=copy") !== false) {
    $errors[] = 'ace.js: schreibende Editor-Aktion verwendet wieder einen GET-Aufruf';
}

$form_class = dbx_test_module_source_bundle($root . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxForm.class.php');
if (!preg_match('/function get_security_data\(\).*?\$this->store_sysdata\(\)/s', $form_class)) {
    $errors[] = 'dbxForm: AJAX-Folgetoken wird nicht unmittelbar in der Session gespeichert';
}
if (!preg_match("/(?:public|protected)\\s+\\\$_tpl\\s*=\\s*'dbx\\|form-default';/", $form_class)
    || !str_contains($form_class, 'protected function default_tpl(string $fid): string')
    || preg_match('/if\s*\(\s*\$tpl\s*==?\s*[\'\"]{2}\s*\)\s*\{\s*\$tpl\s*=\s*\$fid/s', $form_class)) {
    $errors[] = 'dbxForm: fehlendes Template muss den Standard statt der Form-ID verwenden';
}
$standard_template = $module_root . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'tpl'
    . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'form-default.htm';
$standard_html = is_file($standard_template) ? (string)file_get_contents($standard_template) : '';
if (!str_contains($standard_html, '[dbx:form]')
    || !str_contains($standard_html, '{form:bar}')
    || !str_contains($standard_html, '{form:message}')
    || !str_contains($standard_html, '{form:footer}')) {
    $errors[] = 'dbxForm: universelles Standardtemplate fehlt oder ist unvollstaendig';
}

if ($checked === 0) {
    $errors[] = 'Keine aktiven Formular-Templates gefunden';
}

if ($errors) {
    fwrite(STDERR, "Formular-Strukturprüfung fehlgeschlagen:\n - " . implode("\n - ", array_unique($errors)) . "\n");
    exit(1);
}

echo "OK: {$checked} Formular-Templates sind ausgeglichen, nicht verschachtelt und die migrierten Formulare sind abgesichert.\n";
