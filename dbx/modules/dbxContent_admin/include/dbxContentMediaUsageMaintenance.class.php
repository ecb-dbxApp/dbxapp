<?php
namespace dbx\dbxContent_admin;

/**
 * Erstellt ohne Datenbankzugriffe einen sicheren Reparaturplan fuer
 * dbxMediaUsage. Die eigentlichen Schreibzugriffe bleiben im CMS-Service.
 */
final class dbxContentMediaUsageMaintenance {

   public static function usage_key(int $media_id, int $content_id, int $folder_id, string $slot, string $content_lng = 'de'): string {
      $content_lng = strtolower(trim($content_lng)) ?: 'de';
      if ($content_id > 0) {
         return 'content:' . $content_lng . ':' . $content_id . ':' . $slot . ':' . $media_id;
      }
      return 'folder:' . $content_lng . ':' . $folder_id . ':' . $slot . ':' . $media_id;
   }

   /**
    * @return array{delete:array<int,string>,update:array<int,array>,insert:array,kept:int,analyzed:int,reasons:array<string,int>}
    */
   public static function plan(
      array $usage_rows,
      array $valid_media_ids,
      array $expected,
      array $content_folders,
      array $folder_ids,
      array $allowed_slots,
      array $controlled_slots = array('hero', 'inline', 'shop')
   ): array {
      $delete = array();
      $update = array();
      $seen = array();
      $reasons = array();
      $kept = 0;
      $analyzed = 0;
      $controlled_slots = array_fill_keys(array_map('strval', $controlled_slots), 1);
      $allowed = array_fill_keys(array_map('strval', $allowed_slots), 1);

      usort($usage_rows, static function($a, $b): int {
         return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
      });

      foreach ($usage_rows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) continue;
         $analyzed++;
         $media_id = (int)($row['media_id'] ?? 0);
         $content_id = (int)($row['content_id'] ?? 0);
         $folder_id = (int)($row['folder_id'] ?? 0);
         $content_lng = strtolower(trim((string)($row['content_lng'] ?? ''))) ?: 'de';
         $slot = strtolower(trim((string)($row['slot'] ?? '')));
         $reason = '';

         if ((int)($row['active'] ?? 0) !== 1) {
            $reason = 'inactive';
         } elseif (!isset($allowed[$slot])) {
            $reason = 'slot_invalid';
         } elseif ($media_id <= 0 || !isset($valid_media_ids[$media_id])) {
            $reason = 'media_invalid';
         } elseif ($content_id > 0 && !array_key_exists($content_lng . ':' . $content_id, $content_folders)
             && !array_key_exists($content_id, $content_folders)) {
            $reason = 'content_missing';
         } elseif ($content_id <= 0 && ($folder_id <= 0 || (!isset($folder_ids[$content_lng . ':' . $folder_id]) && !isset($folder_ids[$folder_id])))) {
            $reason = $folder_id > 0 ? 'folder_missing' : 'target_missing';
         }

         $key = self::usage_key($media_id, $content_id, $folder_id, $slot, $content_lng);
         if ($reason === '' && isset($controlled_slots[$slot])) {
            if (($slot === 'inline' && $content_id <= 0) || !isset($expected[$key])) {
               $reason = 'not_in_content';
            }
         }
         if ($reason === '' && isset($seen[$key])) $reason = 'duplicate';

         if ($reason !== '') {
            $delete[$id] = $reason;
            $reasons[$reason] = (int)($reasons[$reason] ?? 0) + 1;
            continue;
         }

         $seen[$key] = $id;
         if (isset($controlled_slots[$slot])) {
            $reference = $expected[$key];
            $valid_folders = is_array($reference['valid_folders'] ?? null)
               ? $reference['valid_folders']
               : array();
            $wanted_folder = (int)($reference['folder_id'] ?? 0);
            if ($content_id > 0
                && $wanted_folder > 0
                && ($folder_id <= 0 || !isset($valid_folders[$folder_id]))) {
               $update[$id] = array('folder_id' => $wanted_folder);
            }
         }
         $kept++;
      }

      $insert = array();
      foreach ($expected as $key => $reference) {
         if (!isset($seen[$key])) $insert[$key] = $reference;
      }

      return array(
         'delete' => $delete,
         'update' => $update,
         'insert' => $insert,
         'kept' => $kept,
         'analyzed' => $analyzed,
         'reasons' => $reasons,
      );
   }
}
