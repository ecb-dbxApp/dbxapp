<?php

/**
 * Vertrag für das Anlegen und sichere Löschen von Medienordnern.
 */
$root = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$cms = (string)file_get_contents($root . '/modules/dbxContent_admin/js/cms.js')
   . (string)file_get_contents($root . '/modules/dbxContent_admin/js/cms-media.js');
$backend = dbx_test_module_source_bundle($root . '/modules/dbxContent_admin/include/dbxContent_cms.class.php');
$css = (string)file_get_contents($root . '/design/dbxapp/css/c-cms.css');

$assert = static function (bool $condition, string $message): void {
   if (!$condition) {
      fwrite(STDERR, "FAIL: {$message}\n");
      exit(1);
   }
};

foreach (array(
   'data-cms-media-tree-folder-name',
   'data-cms-media-tree-folder-create',
   'data-cms-media-tree-folder-delete',
) as $selector) {
   $assert(str_contains($cms, $selector), 'Medienbrowser-Aktion fehlt: ' . $selector);
}

$assert(
   str_contains($cms, 'Medien, Dateien und Unterordner werden niemals mitgeloescht.'),
   'Der Löschdialog erklärt die Leerordner-Grenze nicht.'
);
$assert(
   str_contains($cms, 'return parentFolder + "/" + name;'),
   'Neue Unterordner werden nicht unter dem aktuell gewählten Ordner angelegt.'
);
$assert(
   str_contains($backend, "Ordner enthaelt noch Medien.")
      && str_contains($backend, "Ordner ist nicht leer.")
      && !str_contains($backend, "media_folder_delete_json() {\n      \$force"),
   'Das Backend schützt gefüllte Medienordner nicht verbindlich.'
);
$assert(
   str_contains($backend, 'private function custom_media_root_exists($folder)')
      && str_contains($backend, 'if ($this->custom_media_root_exists($folder)) return $folder;'),
   'Unterordner vorhandener freier Medienwurzeln werden fälschlich nach img/allgemein umgeleitet.'
);
$assert(
   str_contains($css, '.dbx-cms-media-explorer-folder-management'),
   'Die sichtbare Ordnerverwaltung ist nicht gestaltet.'
);
$assert(
   str_contains($css, '.dbx-cms-media-explorer-grid')
      && str_contains($css, 'grid-auto-rows: max-content')
      && str_contains($css, 'align-content: start'),
   'Die Medienkarten im Tree-Browser dürfen bei vielen Bildern nicht überlappen.'
);

echo "OK media browser creates folders and deletes empty folders only.\n";
