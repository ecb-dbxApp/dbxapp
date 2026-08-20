<?php

  // Die offizielle Produktschreibweise wird nur in sichtbaren Textknoten
  // normalisiert. Tags, Attribute, Codebeispiele, Styles und Skripte bleiben
  // bytegenau erhalten.
  $content = preg_replace_callback(
    '~<(?:script|style|code|pre)\b[^>]*>[\s\S]*?</(?:script|style|code|pre)>|<[^>]+>|[^<]+~iu',
    static function(array $match): string {
      $chunk = (string)($match[0] ?? '');
      if ($chunk === '' || $chunk[0] === '<') {
        return $chunk;
      }
      return preg_replace('/\bdbx\s*app\b/iu', 'dbxapp', $chunk) ?? $chunk;
    },
    (string)$content
  ) ?? (string)$content;

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
