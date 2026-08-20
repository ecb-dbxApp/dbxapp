<?php

namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_permalink.class.php';
require_once __DIR__ . '/dbxContentLng.class.php';
require_once __DIR__ . '/dbxContentMediaUsageScope.class.php';
require_once __DIR__ . '/dbxContentMediaUrl.class.php';
require_once __DIR__ . '/dbxContentRendererPageTrait.trait.php';
require_once __DIR__ . '/dbxContentRendererSeoTrait.trait.php';
require_once __DIR__ . '/dbxContentRendererLayoutTrait.trait.php';
require_once __DIR__ . '/dbxContentRendererMediaTrait.trait.php';

/**
 * @brief Fassade zum Rendern veröffentlichter CMS-Inhalte.
 *
 * Seitenablauf, Layoutanalyse, Medien und SEO bleiben intern getrennt. Die
 * öffentliche Renderer-API bleibt für Content, Sitemap und Cache unverändert.
 */
class dbxContentRenderer {

   use dbxContentRendererPageTrait;
   use dbxContentRendererSeoTrait;
   use dbxContentRendererLayoutTrait;
   use dbxContentRendererMediaTrait;

   private $dd_media = 'dbxMedia';
   private $dd_media_usage = 'dbxMediaUsage';
   private array $folder_row_cache = array();
}

?>
