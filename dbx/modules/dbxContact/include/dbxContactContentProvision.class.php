<?php
namespace dbx\dbxContact;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;

class dbxContactContentProvision {

   public static function run($db): int {
      if (!is_object($db)) {
         return 0;
      }

      $dd = dbxContentLng::ddContent('de');
      $existing = $db->select1($dd, array('permalink' => 'meine-anfragen'), 'id,content', 0);
      if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
         $id = (int) $existing['id'];
         $content = (string) ($existing['content'] ?? '');
         if (str_starts_with($content, '<h1>Meine Anfragen</h1>') && str_contains($content, '[modul=dbxContact]dbx_run1=tickets[/modul]')) {
            $db->update($dd, array('content' => self::pageContent()), $id, 0, 1, 0, 1);
            dbxContentPageCache::invalidateAll();
         }
         return $id;
      }

      $folderId = self::folderId($db);
      $data = array(
         'activ' => 1,
         'folder' => $folderId,
         'title' => 'Meine Anfragen',
         'permalink' => 'meine-anfragen',
         'description' => 'Eigene Kontaktanfragen, Antworten und aktuellen Bearbeitungsstatus anzeigen.',
         'keywords' => 'Kontakt, Anfragen, Tickets, Antworten',
         'group_read' => '*',
         'sorter' => self::nextSorter($db, $dd, $folderId),
         'template' => 'parent',
         'hero_template' => 'parent',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'grid',
         'gallery_click_behavior' => 'lightbox',
         'content' => self::pageContent(),
      );

      if ($db->insert($dd, $data, 0, 1, 0, 1) <= 0) {
         return 0;
      }

      $id = (int) $db->get_insert_id();
      if ($id <= 0) {
         return 0;
      }

      dbxContentLngSync::afterPageSave($db, $id, true);
      dbxContentPermalinkIndex::upsertPage($id, 'meine-anfragen', '*', 1, 'de');
      dbxContentPageCache::invalidateAll();
      return $id;
   }

   private static function pageContent(): string {
      return '<p class="lead">Hier sehen Sie Ihre Kontaktanfragen, Antworten und den aktuellen Bearbeitungsstatus.</p>'
         . '[modul=dbxContact]dbx_run1=tickets[/modul]';
   }

   private static function folderId($db): int {
      $dd = dbxContentLng::ddFolder('de');
      $rows = $db->select($dd, array('name' => 'Fuer Kunden'), 'id', 'id', 'ASC', '', 1, 0, 0);
      if (is_array($rows) && isset($rows[0]['id'])) {
         return (int) $rows[0]['id'];
      }
      return 0;
   }

   private static function nextSorter($db, string $dd, int $folderId): string {
      $rows = $db->select($dd, array('folder' => $folderId), 'sorter', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]['sorter'])) {
         $max = (int) $rows[0]['sorter'];
      }
      return sprintf('%04d', $max + 10);
   }
}
