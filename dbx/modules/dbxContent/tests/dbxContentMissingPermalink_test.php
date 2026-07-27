<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$renderer = (string)file_get_contents($moduleRoot . '/include/dbxContentRenderer.class.php');
$contentModule = (string)file_get_contents($moduleRoot . '/include/dbxContent_content.class.php');

if (!str_contains($renderer, "get_system_var('dbx_permalink', '')")
   || !str_contains($renderer, "'permalink' => dbx()->esc(")
   || !str_contains($renderer, 'public function renderNotFound(): string')) {
   fwrite(STDERR, "FAIL: Der Renderer übergibt den angeforderten Permalink nicht sicher an das Missing-Template.\n");
   exit(1);
}

if (!str_contains($contentModule, 'return $renderer->renderNotFound();')
   || str_contains($contentModule, 'show cid=($cid) nicht gefunden')) {
   fwrite(STDERR, "FAIL: Der cid=0-Pfad verwendet nicht die einheitliche Missing-Ausgabe.\n");
   exit(2);
}

foreach (array('', '_en', '_es') as $suffix) {
   $template = (string)file_get_contents(
      $moduleRoot . '/tpl/htm/no-page' . $suffix . '.htm'
   );
   if (!str_contains($template, '{permalink}') || str_contains($template, '{cid}')) {
      fwrite(STDERR, "FAIL: Missing-Template verwendet nicht den Permalink: " . ($suffix ?: '_de') . "\n");
      exit(3);
   }
}

echo "OK dbxContent missing permalink\n";
