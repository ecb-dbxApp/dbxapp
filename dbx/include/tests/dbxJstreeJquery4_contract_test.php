<?php

declare(strict_types=1);

$dbx = dirname(__DIR__, 2);
$composer = json_decode((string)file_get_contents($dbx . '/composer.json'), true);
$manifest = json_decode((string)file_get_contents($dbx . '/add_ons/dbxJstree/assets/manifest.json'), true);
$lib = (string)file_get_contents($dbx . '/add_ons/dbxJstree/dbxJstree.js');

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
        && str_contains($lib, "'dbx/add_ons/dbxJstree/assets'")
        && str_contains($lib, 'this._data.state.saveTimer')
        && strlen($lib) > 250000,
    'dbxJstree ist keine vollständige eigenständige Lib oder ihre Runtime-Prüfung fehlt.'
);

foreach (array('$.isArray(', '$.isFunction(', '$.isNumeric(', '$.parseJSON(', '$.trim(', '$.type(', '$.unique(', '.andSelf(', '.delegate(', '.undelegate(') as $removed_api) {
    $assert(!str_contains($lib, $removed_api), 'dbxJstree nutzt eine in jQuery 4 entfernte API: ' . $removed_api);
}
foreach (array('changed', 'checkbox', 'conditionalselect', 'contextmenu', 'dnd', 'massload', 'search', 'sort', 'state', 'types', 'unique', 'wholerow') as $plugin) {
    $assert(
        str_contains($lib, '$.jstree.plugins.' . $plugin),
        'Die vollständige dbxJstree-Lib enthält das Plug-in nicht: ' . $plugin
    );
}
$assert(!is_file($dbx . '/js/lib/dbxJstree.js'), 'Der Fremdcode-Fork darf nicht im eigenen Bibliotheksordner liegen.');

echo "OK dbxJstree 1.0.0 is wired for jQuery 4 without removed APIs.\n";
