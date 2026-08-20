<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = array();
$feature_marker = 'lib=icons_editor|module=dbxDesign_admin|mode={dbx:edit}';

foreach (array('dbxapp', 'flowers', 'steal') as $design) {
   $path = $root . '/design/' . $design . '/htm/default.htm';
   $source = is_file($path) ? (string)file_get_contents($path) : '';
   if (!str_contains($source, $feature_marker)) {
      $errors[] = "Design $design routet icons_editor nicht mit Modul und aktivem Modus.";
   }

   $editor_css_path = $root . '/design/' . $design . '/css/c-icons_editor.css';
   $editor_css = is_file($editor_css_path) ? (string)file_get_contents($editor_css_path) : '';
   if (!str_contains($editor_css, '.dbx-editor-files-title')
       || !str_contains($editor_css, 'background: #0a2744;')
       || !str_contains($editor_css, 'color: #f5fbff !important;')) {
      $errors[] = "Design $design besitzt keine kontrastreiche Level-9-Zwischenueberschrift.";
   }
}

$design_service = (string)file_get_contents(
   $root . '/modules/dbxDesign_admin/include/dbxDesignService.class.php'
);
if (!str_contains($design_service, $feature_marker)) {
   $errors[] = 'Neu erzeugte Designs erhalten nicht den vollstaendigen icons_editor-Marker.';
}

$editor_js = (string)file_get_contents($root . '/modules/dbxDesign_admin/js/icons_editor.js');
if (!str_contains($editor_js, 'value === null ? cfg.mode : value')) {
   $errors[] = 'Der gemerkte Editor-Modus wird ohne URL-Parameter nicht an JavaScript uebergeben.';
}

$web_app = (string)file_get_contents($root . '/include/dbxWebApp.class.php');
$security = (string)file_get_contents($root . '/include/dbxApiSecurity.trait.php');
if (!str_contains($web_app, 'max(0, min(9, (int)dbx()->get_request_var(\'dbx_edit\', $edit, \'int\')))')) {
   $errors[] = 'dbx_edit wird serverseitig nicht auf 0 bis 9 normalisiert.';
}
if (!str_contains($security, '&& $this->has_group(\'admin\')')) {
   $errors[] = 'Der Editor-Modus ist nicht auf Administratoren begrenzt.';
}

if ($errors) {
   fwrite(STDERR, "FAIL dbx_edit feature routing\n- " . implode("\n- ", $errors) . "\n");
   exit(1);
}

echo "OK dbx_edit 1-9 feature routing, state fallback and admin boundary\n";
