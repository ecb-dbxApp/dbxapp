<?php
declare(strict_types=1);

/**
 * Vertrag: Tooltips sind ein eigenes, HTML-faehiges UI-System und kein Alias
 * fuer das native title-Attribut.
 */

$root = dirname(__DIR__, 2);
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
$form = $read('include/dbxForm.class.php');
$report = $read('include/dbxReport.class.php');
$api = $read('include/dbxApi.php');
$openWin = $read('js/lib/openWin.js');
$menu = $read('modules/dbxMenu/dbxMenu.class.php');
$selftest = $read('modules/dbxSelfTest/tpl/js/selftest.js');
$selftestStyle = $read('modules/dbxSelfTest/tpl/htm/selftest-dashboard-style.htm');
$selftestController = $read('modules/dbxSelfTest/include/dbxSelfTestController.class.php');
$selftestRowName = $read('modules/dbxSelfTest/tpl/htm/selftest-run-name.htm');

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
        && str_contains($report, "'title'   => \$actionUi[0]")
        && str_contains($report, "'tooltip' => \$actionUi[1]"),
    'dbxReport muss Fenstertitel und Bedienhinweis getrennt erzeugen.'
);
$assert(
    str_contains($selftestRowName, 'data-dbx-tooltip="{tooltip_html}"')
        && str_contains($selftestController, "\$tooltipHtml = '<strong>' . \$this->h(\$row['name']) . '</strong>';")
        && str_contains($selftestController, "\$tooltipHtml .= '<br><small>' . \$this->h(\$row['description']) . '</small>';")
        && !str_contains($selftest, 'dataset.tooltip')
        && !str_contains($selftestStyle, 'attr(data-tooltip)'),
    'Der SelfTest darf kein paralleles lokales Tooltip-System mehr enthalten.'
);

foreach (array('dbxapp', 'dbxdocs', 'flowers', 'steal') as $design) {
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

$scanRoot = $root;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
);
$nativeUiTitle = '~<(?:a|button|span|div|label|i|input|select|th|td|summary|hr)\b[^>]*\s+title\s*=~is';
$duplicateTooltip = '~<(?:a|button|span|div|label|i|input|select|th|td|summary|hr)\b[^>]*data-dbx-tooltip\s*=[^>]*data-dbx-tooltip\s*=~is';

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
        || str_contains($path, '/modules/dbxDocs/content/generated/')
    ) {
        continue;
    }

    $source = (string)file_get_contents($file->getPathname());
    if (preg_match($nativeUiTitle, $source)) {
        $failures[] = 'Nativer Bedienhinweis statt data-dbx-tooltip: '
            . ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');
    }
    if (preg_match($duplicateTooltip, $source)) {
        $failures[] = 'Doppeltes data-dbx-tooltip am selben Element: '
            . ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');
    }
}

foreach (array(
    'js/lib/core.js',
    'js/lib/form.js',
    'js/lib/cms-page.js',
    'js/lib/demoMode.js',
    'js/lib/shopAdmin.js',
) as $relative) {
    $assert(
        !preg_match('~setAttribute\([\'"]title[\'"]|\.(?:title)\s*=~', $read($relative)),
        $relative . ': dynamischer Bedienhinweis verwendet noch title.'
    );
}
$assert(
    !str_contains($openWin, '.attr("title"'),
    'openWin muss dynamische Bedienhinweise als dbx-Tooltip setzen.'
);
$assert(
    str_contains($read('js/lib/cms.js'), 'marker.removeAttribute("title");')
        && str_contains($read('js/lib/cms.js'), 'marker.setAttribute("data-dbx-tooltip", "Marker auswählen')
        && str_contains($read('js/lib/cms.js'), 'duplicateBtn.setAttribute("aria-label", duplicateTooltip)')
        && !str_contains($read('js/lib/cms.js'), 'duplicateBtn.getAttribute("title")'),
    'CMS-Marker oder dynamische Aktionsbeschriftungen verwenden noch native title-Werte.'
);
$assert(
    !preg_match('~<a[^>]*\\btitle=~i', $menu)
        && str_contains($menu, 'data-dbx-tooltip="\' . $title . \'"')
        && str_contains($menu, 'data-dbx-tooltip="\' . $label . \'"'),
    'Die systemseitig erzeugten Menueeintraege duerfen keine nativen Bedienhinweise ausgeben.'
);
$assert(
    str_contains($openWin, 'setMaximizeControlState($win, fullscreen)')
        && str_contains($openWin, 'ui.minimizeWindow')
        && str_contains($openWin, 'ui.maximizeWindow')
        && str_contains($openWin, 'ui.restoreWindow')
        && str_contains($openWin, 'ui.closeWindow')
        && str_contains($openWin, '.attr("data-dbx-tooltip", label)')
        && str_contains($openWin, 'keydown.keyboard-activate'),
    'openWin muss alle Fenstersteuerungen zentral beschriften und per Tastatur bedienbar halten.'
);
$assert(
    !str_contains($read('modules/dbx/tpl/htm/table_row_edit.htm'), 'data-title="{tooltip}"')
        && !str_contains($read('modules/dbx/tpl/htm/table_row_show.htm'), 'data-title="{tooltip}"')
        && !str_contains($read('modules/dbx/tpl/htm/button_dbcreate.htm'), 'data-title="{tooltip}"'),
    'Fenstertitel und Tooltip sind in zentralen Templates noch gekoppelt.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", array_values(array_unique($failures))) . "\n");
    exit(1);
}

echo "OK explizite HTML-Tooltips, getrennte Titel, zentrale Layer und vier eigenstaendige Designs.\n";
