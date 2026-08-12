<?php
declare(strict_types=1);

$base = dirname(__DIR__, 4);
require_once $base . '/dbx/include/tests/dbxCssTestReader.php';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$menus = array(
    'dbx-top-admin.htm' => 'System-Selbsttest',
    'dbx-top-admin_de.htm' => 'System-Selbsttest',
    'dbx-top-admin_en.htm' => 'System self-test',
    'dbx-top-admin_es.htm' => 'Autoprueba del sistema',
    'top-admin-bar.htm' => 'System-Selbsttest',
    'top-admin-bar_en.htm' => 'System self-test',
    'top-admin-bar_es.htm' => 'Autoprueba del sistema',
);
foreach ($menus as $file => $label) {
    $html = (string)file_get_contents($base . '/dbx/modules/dbxMenu/tpl/htm/' . $file);
    $assert(
        str_contains($html, 'href="?dbx_modul=dbxSelfTest&dbx_run1=dashboard"') && str_contains($html, $label),
        'dbxSelfTest fehlt oder ist falsch lokalisiert in ' . $file . '.'
    );
}

$viewportJs = (string)file_get_contents($base . '/dbx/js/lib/viewport.js');
$assert(str_contains($viewportJs, 'scrolling="yes"'), 'Das Vorschau-Iframe fordert keinen sichtbaren Scrollbereich an.');
foreach (array('dbxapp', 'dbxdocs', 'steal') as $design) {
    $css = dbx_test_read_css($base . '/dbx/design/' . $design . '/css/c-viewport.css');
    $assert(
        str_contains($css, '.dbx-viewport-lab .dbx-viewport-toolbar .dbx-viewport-profile-select select')
            && str_contains($css, '-webkit-text-fill-color: #ffffff'),
        'Der Auflösungs-Selector ist in ' . $design . ' nicht kontrastfest.'
    );
    $assert(
        str_contains($css, 'html.dbx-viewport-preview-page body.dbx-app .dbx-content::-webkit-scrollbar')
            && str_contains($css, 'scrollbar-width: auto'),
        'Der Scrollbalken der Viewport-Vorschau ist in ' . $design . ' nicht sichtbar definiert.'
    );

    $menuCss = dbx_test_read_css($base . '/dbx/design/' . $design . '/css/m-menu.css');
    $assert(
        str_contains($menuCss, '@media (max-width: 1199.98px)')
            && str_contains($menuCss, '@media (min-width: 1200px) and (max-width: 1399.98px)')
            && str_contains($menuCss, '#dbx_admin_menu'),
        'Das Admin-Menue ist in ' . $design . ' zwischen Tablet- und schmaler Desktopbreite nicht ueberlaufsicher.'
    );
}

$flowersMenuCss = (string)file_get_contents($base . '/dbx/design/flowers/css/m-menu.css');
$assert(
    str_contains($flowersMenuCss, '.fleurop-admin-strip > .dbx-menu-mobile-bar { display: flex !important;')
        && str_contains($flowersMenuCss, '.fleurop-admin-strip .dbx-menu-admin.dbx-menu-root:not(.is-mobile-open) { display: none !important;')
        && str_contains($flowersMenuCss, 'flex-wrap: wrap;'),
    'Das Flowers-Admin-Menue ist mobil nicht einklappbar oder auf mittleren Breiten nicht ueberlaufsicher.'
);

$docsLayoutCss = (string)file_get_contents($base . '/dbx/design/dbxdocs/css/docs-layout.css');
$assert(
    str_contains($docsLayoutCss, 'body.dbx-app.dbx-docs #dbxHeader.dbxdocs-sidebar .dbx-menu-root')
        && str_contains($docsLayoutCss, 'background: transparent;'),
    'Die dbxdocs-Seitennavigation ist nicht gegen den hellen globalen Glaseffekt abgegrenzt.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK dbxSelfTest menu localization and viewport accessibility contract.\n";
