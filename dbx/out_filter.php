<?php

  // Erst nach Modul- und Template-Interpretation optimieren, damit auch
  // Bilder aus eingebetteten Content-Modulen erfasst werden. Der Marker
  // begrenzt die Aenderung strikt auf gerenderte Content-Seiten.
  if ((string)dbx()->get_system_var('dbx_master_modul', '') === 'dbxContent'
      && strpos((string)$content, '<!-- dbx-content-images:start -->') !== false) {
    dbx()->load_content_cache_classes();
    require_once dbx()->get_base_dir() . 'dbx/modules/dbxContent/include/dbxContentRenderer.class.php';
    $content = preg_replace_callback(
      '/<!--\s*dbx-content-images:start\s*-->([\s\S]*?)<!--\s*dbx-content-images:end\s*-->/i',
      static function(array $match): string {
        return \dbx\dbxContent\dbxContentRenderer::optimize_content_page_images((string)($match[1] ?? ''));
      },
      (string)$content
    );
  }
