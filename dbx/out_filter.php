<?php

  // Die zentrale Produktversion wird auch in spaet eingesetzten CMS-Inhalten
  // ersetzt. Codebeispiele, Styles und Skripte bleiben bytegenau erhalten;
  // normale HTML-Attribute wie versionierte Vertragslinks werden aufgeloest.
  $dbx_version = htmlspecialchars((string)dbx()->get_version(), ENT_QUOTES, 'UTF-8');

  // Die offizielle Produktschreibweise wird nur in sichtbaren Textknoten
  // normalisiert. Tags und Attribute bleiben davon unberuehrt.
  $content = preg_replace_callback(
    '~<(?:script|style|code|pre)\b[^>]*>[\s\S]*?</(?:script|style|code|pre)>|<[^>]+>|[^<]+~iu',
    static function(array $match) use ($dbx_version): string {
      $chunk = (string)($match[0] ?? '');
      if ($chunk === '' || preg_match('~^<(?:script|style|code|pre)\b~iu', $chunk) === 1) {
        return $chunk;
      }
      $chunk = str_replace('{dbx:version}', $dbx_version, $chunk);
      if ($chunk[0] === '<') {
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
