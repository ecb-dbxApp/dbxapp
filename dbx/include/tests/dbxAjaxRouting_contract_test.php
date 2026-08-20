<?php
declare(strict_types=1);

$base = dirname(__DIR__, 2);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static function (string $file): string {
    return is_file($file) ? (string)file_get_contents($file) : '';
};

$ajax_file = str_replace('\\', '/', (string)realpath($base . '/js/lib/ajax.js'));
$open_win_file = str_replace('\\', '/', (string)realpath($base . '/js/lib/openWin.js'));
$ajax = $read($ajax_file);
$grid = $read($base . '/js/lib/grid.js');
$grid_state = $read($base . '/js/lib/grid-state.js');
$grid_export = $read($base . '/js/lib/grid-export.js');
$grid_transport = $read($base . '/js/lib/grid-transport.js');
$grid_columns = $read($base . '/js/lib/grid-columns.js');
$grid_ui = $read($base . '/js/lib/grid-ui.js');
$jstree = $read($base . '/add_ons/dbxJstree/dbxJstree.js');

$assert(
    str_contains($ajax, 'if (mode === "auto") return "auto";')
        && str_contains($ajax, 'const abortable = bool(options && options.abortable, false);')
        && str_contains($ajax, 'request.abort = function ()')
        && str_contains($ajax, 'return request;'),
    'ajax.js bietet keinen zentralen Auto-Response- und Abbruchvertrag fuer Systembibliotheken.'
);
$assert(
    str_contains($grid, "['js','lib','grid-state.js']")
        && str_contains($grid, "['js','lib','grid-export.js']")
        && str_contains($grid_state, 'Object.assign(feature, {')
        && str_contains($grid_state, '_tableHasPendingEdits(table)')
        && str_contains($grid_export, '_ensureExcelExportDeps(table, done)')
        && str_contains($grid_export, '_ensurePdfExportDeps(table, done)')
        && str_contains($grid_transport, '_dbxRequest(url, options = {})')
        && str_contains($grid_columns, 'buildColumns(opt)')
        && str_contains($grid_ui, 'bindToolbar(el, table, opt, uiState, root)')
        && !str_contains($grid, '_loadRootScript(file, done)'),
    'Grid-State und Export-Abhaengigkeiten sind nicht eigenstaendig gekapselt.'
);
$assert(
    str_contains($grid_transport, 'dbx.ajax.request({')
        && str_contains($grid_export, 'dbx.ajax.request({')
        && str_contains($grid_export, "mode: 'text'")
        && !str_contains($grid . $grid_state . $grid_export . $grid_transport . $grid_columns . $grid_ui, 'XMLHttpRequest')
        && !str_contains($grid . $grid_state . $grid_export . $grid_transport . $grid_columns . $grid_ui, 'fetch('),
    'grid.js laedt Export-Abhaengigkeiten nicht ausschliesslich ueber ajax.js.'
);
$assert(
    str_contains($jstree, 'var dbxJstreeRequest = function (settings)')
        && str_contains($jstree, 'window.dbx.ajax.request({')
        && str_contains($jstree, "['js', 'lib', 'ajax.js']")
        && !str_contains($jstree, '$.ajax(')
        && !str_contains($jstree, 'XMLHttpRequest')
        && !str_contains($jstree, 'fetch('),
    'dbxJstree umgeht ajax.js oder deklariert die Transportabhaengigkeit nicht.'
);

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
    $base . '/js',
    FilesystemIterator::SKIP_DOTS
));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'js') continue;
    $path = str_replace('\\', '/', $file->getPathname());
    $source = $read($path);
    if ($path !== $ajax_file) {
        $assert(
            !preg_match('/(?:\bfetch\s*\(|new\s+XMLHttpRequest\b|\$\.ajax\s*\(|\$\.get\s*\(|\$\.post\s*\()/m', $source),
            'Direkter Netzwerktransport ausserhalb ajax.js: ' . str_replace('\\', '/', substr($path, strlen($base) + 1))
        );
    }
    if ($path !== $open_win_file) {
        $assert(
            !preg_match('/\bwindow\.open\s*\(/m', $source),
            'Direktes Browserfenster ausserhalb openWin.js: ' . str_replace('\\', '/', substr($path, strlen($base) + 1))
        );
    }
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK all DBX JavaScript network requests and browser windows use ajax.js and openWin.js.\n";
