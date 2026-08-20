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

      $dd = dbxContentLng::dd_content('de');
      $existing = $db->select1($dd, array('permalink' => 'meine-anfragen'), 'id,content', 0);
      if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
         $id = (int) $existing['id'];
         $content = (string) ($existing['content'] ?? '');
         if (str_starts_with($content, '<h1>Meine Anfragen</h1>') && str_contains($content, '[modul=dbxContact]dbx_run1=tickets[/modul]')) {
            $db->update($dd, array('content' => self::page_content()), $id, 0, 1, 0, 1);
            dbxContentPageCache::invalidate_all();
         }
         return $id;
      }

      $folder_id = self::folder_id($db);
      $data = array(
         'activ' => 1,
         'folder' => $folder_id,
         'title' => 'Meine Anfragen',
         'permalink' => 'meine-anfragen',
         'description' => 'Eigene Kontaktanfragen, Antworten und aktuellen Bearbeitungsstatus anzeigen.',
         'keywords' => 'Kontakt, Anfragen, Tickets, Antworten',
         'group_read' => '*',
         'sorter' => self::next_sorter($db, $dd, $folder_id),
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
         'content' => self::page_content(),
      );

      if ($db->insert($dd, $data, 0, 1, 0, 1) <= 0) {
         return 0;
      }

      $id = (int) $db->get_insert_id();
      if ($id <= 0) {
         return 0;
      }

      dbxContentLngSync::after_page_save($db, $id, true);
      dbxContentPermalinkIndex::upsert_page($id, 'meine-anfragen', '*', 1, 'de');
      dbxContentPageCache::invalidate_all();
      return $id;
   }

   private static function page_content(): string {
      return '<p class="lead">Hier sehen Sie Ihre Kontaktanfragen, Antworten und den aktuellen Bearbeitungsstatus.</p>'
         . '[modul=dbxContact]dbx_run1=tickets[/modul]';
   }

   private static function folder_id($db): int {
      $dd = dbxContentLng::dd_folder('de');
      $rows = $db->select($dd, array('name' => 'Fuer Kunden'), 'id', 'id', 'ASC', '', 1, 0, 0);
      if (is_array($rows) && isset($rows[0]['id'])) {
         return (int) $rows[0]['id'];
      }
      return 0;
   }

   private static function next_sorter($db, string $dd, int $folder_id): string {
      $rows = $db->select($dd, array('folder' => $folder_id), 'sorter', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]['sorter'])) {
         $max = (int) $rows[0]['sorter'];
      }
      return sprintf('%04d', $max + 10);
   }
}
