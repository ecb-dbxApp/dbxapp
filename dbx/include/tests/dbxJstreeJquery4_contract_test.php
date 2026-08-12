<?php

declare(strict_types=1);

$dbx = dirname(__DIR__, 2);
$composer = json_decode((string)file_get_contents($dbx . '/composer.json'), true);
$manifest = json_decode((string)file_get_contents($dbx . '/js/lib/dbxJstree/manifest.json'), true);
$lib = (string)file_get_contents($dbx . '/js/lib/dbxJstree.js');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$assert(
    ($composer['require']['components/jquery'] ?? '') === '^4.0',
    'Composer muss jQuery 4 als Runtime festlegen.'
);
$assert(
    ($manifest['version'] ?? '') === '1.0.0'
        && ($manifest['jquery'] ?? '') === '4.x'
        && ($manifest['based_on'] ?? '') === 'jsTree 3.3.17',
    'Das Lib-Manifest beschreibt die dbxJstree/jQuery-4-Grenze nicht.'
);
$assert(
    str_contains($lib, "dbx.feature.register('dbxJstree'")
        && str_contains($lib, 'dbx_jquery_major !== 4')
        && str_contains($lib, "version : '1.0.0'")
        && str_contains($lib, "'/dbxJstree'")
        && str_contains($lib, 'this._data.state.saveTimer')
        && strlen($lib) > 250000,
    'dbxJstree ist keine vollständige eigenständige Lib oder ihre Runtime-Prüfung fehlt.'
);

foreach (array('$.isArray(', '$.isFunction(', '$.isNumeric(', '$.parseJSON(', '$.trim(', '$.type(', '$.unique(', '.andSelf(', '.delegate(', '.undelegate(') as $removedApi) {
    $assert(!str_contains($lib, $removedApi), 'dbxJstree nutzt eine in jQuery 4 entfernte API: ' . $removedApi);
}
foreach (array('changed', 'checkbox', 'conditionalselect', 'contextmenu', 'dnd', 'massload', 'search', 'sort', 'state', 'types', 'unique', 'wholerow') as $plugin) {
    $assert(
        str_contains($lib, '$.jstree.plugins.' . $plugin),
        'Die vollständige dbxJstree-Lib enthält das Plug-in nicht: ' . $plugin
    );
}
$assert(!is_dir($dbx . '/add_ons/jstree'), 'Das alte jsTree-Add-on muss vollständig entfallen.');

echo "OK dbxJstree 1.0.0 is wired for jQuery 4 without removed APIs.\n";
