<?php
declare(strict_types=1);

$base = dirname(__DIR__, 4);
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
    $css = (string)file_get_contents($base . '/dbx/design/' . $design . '/css/c-viewport.css');
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
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK dbxSelfTest menu localization and viewport accessibility contract.\n";
