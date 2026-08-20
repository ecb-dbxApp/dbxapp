<?php
declare(strict_types=1);

/**
 * Vertrag: Tooltips sind ein eigenes, HTML-faehiges UI-System und kein Alias
 * fuer das native title-Attribut.
 */

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$failures = array();

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$read = static function (string $relative) use ($root): string {
    $file = $root . '/' . ltrim($relative, '/');
    return is_file($file) ? (string)file_get_contents($file) : '';
};

$utilities = $read('js/lib/utilities.js');
$form = dbx_test_module_source_bundle($root . '/include/dbxForm.class.php');
$report = dbx_test_module_source_bundle($root . '/include/dbxReport.class.php');
$api = dbx_test_module_source_bundle($root . '/include/dbxApi.php');
$open_win = $read('js/lib/openWin.js');
$menu = $read('modules/dbxMenu/dbxMenu.class.php');
$selftest = $read('modules/dbxSelfTest/js/selftest.js');
$selftest_style = $read('modules/dbxSelfTest/tpl/htm/selftest-dashboard-style.htm');
$selftest_controller = $read('modules/dbxSelfTest/include/dbxSelfTestController.class.php');
$selftest_row_name = $read('modules/dbxSelfTest/tpl/htm/selftest-run-name.htm');

$assert(
    str_contains($utilities, 'const TOOLTIP_SELECTOR = "[data-dbx-tooltip],[data-dbx-errormsg]"')
        && !str_contains($utilities, '[data-dbx-errormsg],[title]'),
    'Der zentrale Tooltip darf native title-Attribute nicht automatisch uebernehmen.'
);
$assert(
    str_contains($utilities, 'function sanitizeTooltipHtml(')
        && str_contains($utilities, '"STRONG"')
        && str_contains($utilities, '"SCRIPT"')
        && str_contains($utilities, 'element.removeAttribute(attribute.name)'),
    'HTML-Tooltips brauchen eine zentrale Positivliste und aktive Inhalte muessen entfernt werden.'
);
$assert(
    str_contains($utilities, 'suppressNativeTitles(target)')
        && str_contains($utilities, 'restoreNativeTitles()'),
    'Ein expliziter dbx-Tooltip muss parallel vorhandene native Hinweise temporaer unterdruecken.'
);
$assert(
    str_contains($utilities, 'dbx.uiLayer.next({')
        && str_contains($utilities, 'tooltipEl.setAttribute("data-dbx-layer", "tooltip")'),
    'Tooltips muessen das zentrale UI-Layer-System verwenden.'
);
$assert(
    str_contains($utilities, 'new MutationObserver(mutations =>')
        && str_contains($utilities, 'attributeFilter: ["data-dbx-tooltip", "data-dbx-errormsg"]')
        && str_contains($utilities, 'showTooltip(tooltipTarget)'),
    'Sichtbare Tooltips muessen dynamisch geaenderte Inhalte sofort uebernehmen.'
);
$assert(
    str_contains($utilities, 'function trackTooltipPosition(duration)')
        && str_contains($utilities, 'tooltipStableFrames < 12')
        && str_contains($utilities, 'trackTooltipPosition(1200)')
        && str_contains($utilities, 'stopTooltipPositionTracking();'),
    'Tooltips muessen kurzen Layoutuebergaengen folgen, ohne dauerhaft pro Frame zu messen.'
);
$assert(
    str_contains($utilities, 'dbx.add_css("design", "c-tooltip.css")'),
    'Tooltip-CSS muss aus dem aktiven, eigenstaendigen Design geladen werden.'
);
$assert(
    !str_contains($api, '$data[\'tooltip\'] = (string)$data[\'title\']')
        && !str_contains($form, '$data[\'tooltip\'] = (string)($data[\'title\']')
        && !str_contains($form, '? (string) $data[\'tooltip\']'),
    'dbxApi und dbxForm duerfen title und tooltip nicht gegenseitig als Fallback verwenden.'
);
$assert(
    str_contains($form, 'escape_tooltip_template_data')
        && str_contains($form, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'dbxForm muss HTML-faehige Tooltip-Werte am Attribut-Rand sicher kodieren.'
);
$assert(
    str_contains($report, 'get_table_action_ui')
        && str_contains($report, "'title'   => \$action_ui[0]")
        && str_contains($report, "'tooltip' => \$action_ui[1]"),
    'dbxReport muss Fenstertitel und Bedienhinweis getrennt erzeugen.'
);
$assert(
    str_contains($selftest_row_name, 'data-dbx-tooltip="{tooltip_html}"')
        && str_contains($selftest_controller, "\$tooltip_html = '<strong>' . \$this->h(\$row['name']) . '</strong>';")
        && str_contains($selftest_controller, "\$tooltip_html .= '<br><small>' . \$this->h(\$row['description']) . '</small>';")
        && !str_contains($selftest, 'dataset.tooltip')
        && !str_contains($selftest_style, 'attr(data-tooltip)'),
    'Der SelfTest darf kein paralleles lokales Tooltip-System mehr enthalten.'
);

foreach (array('dbxapp', 'flowers', 'steal') as $design) {
    $css = $read('design/' . $design . '/css/c-tooltip.css');
    $assert($css !== '', $design . ': c-tooltip.css fehlt.');
    $assert(
        str_contains($css, '--dbx-tooltip-bg: #fff1a8')
            && str_contains($css, '::before')
            && str_contains($css, 'data-placement="bottom"')
            && str_contains($css, '--dbx-tooltip-arrow-left'),
        $design . ': gelbe Sprechblase, Pfeil oder Platzierungs-Fallback ist unvollstaendig.'
    );
}

$scan_root = $root;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($scan_root, FilesystemIterator::SKIP_DOTS)
);
$native_ui_title = '~<(?:a|button|span|div|label|i|input|select|th|td|summary|hr)\b[^>]*\s+title\s*=~is';
$duplicate_tooltip = '~<(?:a|button|span|div|label|i|input|select|th|td|summary|hr)\b[^>]*data-dbx-tooltip\s*=[^>]*data-dbx-tooltip\s*=~is';

foreach ($iterator as $file) {
    if (!$file->isFile() || !preg_match('~\.(?:php|js|html?|tpl)$~i', $file->getFilename())) {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if (
        str_contains($path, '/vendor/')
        || str_contains($path, '/add_ons/')
        || str_contains($path, '/tests/')
        || str_contains($path, '/modules/dbxMenu/')
    ) {
        continue;
    }

    $source = (string)file_get_contents($file->getPathname());
    if (preg_match($native_ui_title, $source)) {
        $failures[] = 'Nativer Bedienhinweis statt data-dbx-tooltip: '
            . ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');
    }
    if (preg_match($duplicate_tooltip, $source)) {
        $failures[] = 'Doppeltes data-dbx-tooltip am selben Element: '
            . ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');
    }
}

foreach (array(
    'js/lib/core.js',
    'js/lib/form.js',
    'modules/dbxContent_admin/js/cms-page.js',
    'js/lib/demoMode.js',
    'modules/dbxShop_admin/js/shopAdmin.js',
) as $relative) {
    $assert(
        !preg_match('~setAttribute\([\'"]title[\'"]|\.(?:title)\s*=~', $read($relative)),
        $relative . ': dynamischer Bedienhinweis verwendet noch title.'
    );
}
$assert(
    !str_contains($open_win, '.attr("title"'),
    'openWin muss dynamische Bedienhinweise als dbx-Tooltip setzen.'
);
$assert(
    str_contains($read('modules/dbxContent_admin/js/cms.js'), 'marker.removeAttribute("title");')
        && str_contains($read('modules/dbxContent_admin/js/cms.js'), 'marker.setAttribute("data-dbx-tooltip", "Marker auswählen')
        && str_contains($read('modules/dbxContent_admin/js/cms.js'), 'duplicateBtn.setAttribute("aria-label", duplicateTooltip)')
        && !str_contains($read('modules/dbxContent_admin/js/cms.js'), 'duplicateBtn.getAttribute("title")'),
    'CMS-Marker oder dynamische Aktionsbeschriftungen verwenden noch native title-Werte.'
);
$assert(
    !preg_match('~<a[^>]*\\btitle=~i', $menu)
        && str_contains($menu, 'data-dbx-tooltip="\' . $title . \'"')
        && str_contains($menu, 'data-dbx-tooltip="\' . $label . \'"'),
    'Die systemseitig erzeugten Menueeintraege duerfen keine nativen Bedienhinweise ausgeben.'
);
$assert(
    str_contains($open_win, 'setMaximizeControlState($win, fullscreen)')
        && str_contains($open_win, 'ui.minimizeWindow')
        && str_contains($open_win, 'ui.maximizeWindow')
        && str_contains($open_win, 'ui.restoreWindow')
        && str_contains($open_win, 'ui.closeWindow')
        && str_contains($open_win, '.attr("data-dbx-tooltip", label)')
        && str_contains($open_win, 'keydown.keyboard-activate'),
    'openWin muss alle Fenstersteuerungen zentral beschriften und per Tastatur bedienbar halten.'
);
$assert(
    str_contains($read('modules/dbx/tpl/htm/table_row_action.htm'), '{link_attributes}')
        && str_contains($report, "'data-dbx-tooltip=\"' . \$this->table_action_attr(\$data['tooltip'] ?? '')")
        && str_contains($report, "'data-title=\"' . \$this->table_action_attr(\$data['title'] ?? '')")
        && !str_contains($read('modules/dbx/tpl/htm/button_dbcreate.htm'), 'data-title="{tooltip}"'),
    'Fenstertitel und Tooltip sind in zentralen Templates noch gekoppelt.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", array_values(array_unique($failures))) . "\n");
    exit(1);
}

echo "OK explizite HTML-Tooltips, getrennte Titel, zentrale Layer und vier eigenstaendige Designs.\n";
