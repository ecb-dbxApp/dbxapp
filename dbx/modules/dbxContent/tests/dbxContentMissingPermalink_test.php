<?php

declare(strict_types=1);

$module_root = dirname(__DIR__);
$renderer = (string)file_get_contents($module_root . '/include/dbxContentRenderer.class.php');
foreach (glob($module_root . '/include/dbxContentRenderer*Trait.trait.php') ?: array() as $trait_file) {
   $renderer .= "\n" . (string)file_get_contents($trait_file);
}
$content_module = (string)file_get_contents($module_root . '/include/dbxContent_content.class.php');

if (!str_contains($renderer, "get_system_var('dbx_permalink', '')")
   || !str_contains($renderer, "'permalink' => dbx()->esc(")
   || !str_contains($renderer, 'public function render_not_found(): string')) {
   fwrite(STDERR, "FAIL: Der Renderer übergibt den angeforderten Permalink nicht sicher an das Missing-Template.\n");
   exit(1);
}

if (!str_contains($content_module, 'return $renderer->render_not_found();')
   || str_contains($content_module, 'show cid=($cid) nicht gefunden')) {
   fwrite(STDERR, "FAIL: Der cid=0-Pfad verwendet nicht die einheitliche Missing-Ausgabe.\n");
   exit(2);
}

foreach (array('', '_en', '_es') as $suffix) {
   $template = (string)file_get_contents(
      $module_root . '/tpl/htm/no-page' . $suffix . '.htm'
   );
   if (!str_contains($template, '{permalink}') || str_contains($template, '{cid}')) {
      fwrite(STDERR, "FAIL: Missing-Template verwendet nicht den Permalink: " . ($suffix ?: '_de') . "\n");
      exit(3);
   }
}

echo "OK dbxContent missing permalink\n";
