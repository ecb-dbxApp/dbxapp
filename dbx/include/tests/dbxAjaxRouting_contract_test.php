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

$ajaxFile = str_replace('\\', '/', (string)realpath($base . '/js/lib/ajax.js'));
$openWinFile = str_replace('\\', '/', (string)realpath($base . '/js/lib/openWin.js'));
$ajax = $read($ajaxFile);
$grid = $read($base . '/js/lib/grid.js');
$jstree = $read($base . '/js/lib/dbxJstree.js');

$assert(
    str_contains($ajax, 'if (mode === "auto") return "auto";')
        && str_contains($ajax, 'const abortable = bool(options && options.abortable, false);')
        && str_contains($ajax, 'request.abort = function ()')
        && str_contains($ajax, 'return request;'),
    'ajax.js bietet keinen zentralen Auto-Response- und Abbruchvertrag fuer Systembibliotheken.'
);
$assert(
    str_contains($grid, 'dbx.ajax.request({')
        && str_contains($grid, "mode: 'text'")
        && !str_contains($grid, 'XMLHttpRequest')
        && !str_contains($grid, 'fetch('),
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
    if ($path !== $ajaxFile) {
        $assert(
            !preg_match('/(?:\bfetch\s*\(|new\s+XMLHttpRequest\b|\$\.ajax\s*\(|\$\.get\s*\(|\$\.post\s*\()/m', $source),
            'Direkter Netzwerktransport ausserhalb ajax.js: ' . str_replace('\\', '/', substr($path, strlen($base) + 1))
        );
    }
    if ($path !== $openWinFile) {
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
