<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxCssTestReader.php';
$menu = (string)file_get_contents($root . '/js/lib/menu.js');
$main_templates = array_map(
    static fn(string $file): string => (string)file_get_contents($root . '/modules/dbxMenu/tpl/htm/' . $file),
    array('dbx-top-main.htm', 'dbx-top-main_en.htm', 'dbx-top-main_es.htm')
);
$menu_styles = array_map(
    static fn(string $design): string => dbx_test_read_css($root . '/design/' . $design . '/css/m-menu.css'),
    array('dbxapp', 'flowers', 'steal')
);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach (array(
    'function isPersistentSideMenu($menu)',
    'function storeOpenBranches($menu)',
    'function restoreStoredOpenBranches($menu)',
    'function closeOtherSideBranches($menu, $item)',
    "closest('aside, [data-dbx-menu-position=\"left\"]",
    "dbx.uiSet(LIB, sideMenuStateId(\$menu), 'branches', paths)",
    "dbx.uiGet(LIB, sideMenuStateId(\$menu), 'branches', [])",
    "\$el.attr('data-dbx-menu-persist-open', '1')",
    "!list.classList.contains('dbx-menu-list')",
) as $needle) {
    $assert(str_contains($menu, $needle), 'Allgemeine Persistenz linker Untermenüs fehlt: ' . $needle);
}

$assert(
    str_contains($menu, 'if (!isPersistentSideMenu($menu)) return;')
        && substr_count($menu, 'restoreStoredOpenBranches(') >= 3
        && str_contains($menu, "'.dbx-menu-item:not(.has-children) > .dbx-menu-link[href]'")
        && str_contains($menu, 'storeOpenBranches($menu);'),
    'Öffnungszustände werden nicht bei Umschaltung, Seitenaufruf und Initialisierung erhalten.'
);

$assert(
    str_contains($menu, 'if (isPersistentSideMenu($menu))')
        && str_contains($menu, 'closeOtherSideBranches($menu, $item)')
        && str_contains($menu, 'if (!keep.has(this)) closeBranch($(this));'),
    'Linke Menüs schließen beim Öffnen eines anderen Ordners nicht alle fremden Zweige.'
);

foreach ($main_templates as $template) {
    foreach (array('dbx-shop-cart-menu-link', 'dbxModeToggle', 'dbx-skin-menu-root', 'dbx:login_out') as $anchor) {
        $position = strpos($template, $anchor);
        $assert(
            $position !== false && strpos(substr($template, $position, 500), 'dbx-menu-mobile-label') !== false,
            'Eine Icon-Aktion besitzt keine feste Beschriftung im Hauptmenü-Template: ' . $anchor
        );
    }
}

foreach ($menu_styles as $style) {
    $assert(
        str_contains($style, '.dbx-menu-mobile-label')
            && str_contains($style, 'display: none')
            && str_contains($style, '[data-dbx-menu-persist-open="1"] .dbx-menu-mobile-label')
            && str_contains($style, 'display: inline'),
        'Template-Beschriftungen werden nicht korrekt zwischen horizontal, links und mobil umgeschaltet.'
    );
}

$dbxapp_style = $menu_styles[0];
$desktop_compact = strpos($dbxapp_style, '@media (min-width: 1200px) and (max-width: 1399.98px)');
$mobile_breakpoint = strpos($dbxapp_style, '@media (max-width: 1199.98px)');
$assert(
    $desktop_compact !== false
        && $mobile_breakpoint !== false
        && $desktop_compact < $mobile_breakpoint
        && str_contains(substr($dbxapp_style, $desktop_compact, $mobile_breakpoint - $desktop_compact), '#dbx_main_menu')
        && str_contains(substr($dbxapp_style, $desktop_compact, $mobile_breakpoint - $desktop_compact), '#dbx_admin_menu'),
    'Das kompakte Desktop-Layout muss Haupt- und Admin-Menü vor horizontalem Überlauf schützen.'
);

echo "OK persistent side-menu branches across all designs\n";
