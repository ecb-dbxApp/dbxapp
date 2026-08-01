<?php
namespace dbx\dbxContent_admin;

/**
 * Erstellt ohne Datenbankzugriffe einen sicheren Reparaturplan fuer
 * dbxMediaUsage. Die eigentlichen Schreibzugriffe bleiben im CMS-Service.
 */
final class dbxContentMediaUsageMaintenance {

   public static function usageKey(int $mediaId, int $contentId, int $folderId, string $slot, string $contentLng = 'de'): string {
      $contentLng = strtolower(trim($contentLng)) ?: 'de';
      if ($contentId > 0) {
         return 'content:' . $contentLng . ':' . $contentId . ':' . $slot . ':' . $mediaId;
      }
      return 'folder:' . $contentLng . ':' . $folderId . ':' . $slot . ':' . $mediaId;
   }

   /**
    * @return array{delete:array<int,string>,update:array<int,array>,insert:array,kept:int,analyzed:int,reasons:array<string,int>}
    */
   public static function plan(
      array $usageRows,
      array $validMediaIds,
      array $expected,
      array $contentFolders,
      array $folderIds,
      array $allowedSlots,
      array $controlledSlots = array('hero', 'inline', 'shop')
   ): array {
      $delete = array();
      $update = array();
      $seen = array();
      $reasons = array();
      $kept = 0;
      $analyzed = 0;
      $controlledSlots = array_fill_keys(array_map('strval', $controlledSlots), 1);
      $allowed = array_fill_keys(array_map('strval', $allowedSlots), 1);

      usort($usageRows, static function($a, $b): int {
         return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
      });

      foreach ($usageRows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) continue;
         $analyzed++;
         $mediaId = (int)($row['media_id'] ?? 0);
         $contentId = (int)($row['content_id'] ?? 0);
         $folderId = (int)($row['folder_id'] ?? 0);
         $contentLng = strtolower(trim((string)($row['content_lng'] ?? ''))) ?: 'de';
         $slot = strtolower(trim((string)($row['slot'] ?? '')));
         $reason = '';

         if ((int)($row['active'] ?? 0) !== 1) {
            $reason = 'inactive';
         } elseif (!isset($allowed[$slot])) {
            $reason = 'slot_invalid';
         } elseif ($mediaId <= 0 || !isset($validMediaIds[$mediaId])) {
            $reason = 'media_invalid';
         } elseif ($contentId > 0 && !array_key_exists($contentLng . ':' . $contentId, $contentFolders)
             && !array_key_exists($contentId, $contentFolders)) {
            $reason = 'content_missing';
         } elseif ($contentId <= 0 && ($folderId <= 0 || (!isset($folderIds[$contentLng . ':' . $folderId]) && !isset($folderIds[$folderId])))) {
            $reason = $folderId > 0 ? 'folder_missing' : 'target_missing';
         }

         $key = self::usageKey($mediaId, $contentId, $folderId, $slot, $contentLng);
         if ($reason === '' && isset($controlledSlots[$slot])) {
            if (($slot === 'inline' && $contentId <= 0) || !isset($expected[$key])) {
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
         if (isset($controlledSlots[$slot])) {
            $reference = $expected[$key];
            $validFolders = is_array($reference['valid_folders'] ?? null)
               ? $reference['valid_folders']
               : array();
            $wantedFolder = (int)($reference['folder_id'] ?? 0);
            if ($contentId > 0
                && $wantedFolder > 0
                && ($folderId <= 0 || !isset($validFolders[$folderId]))) {
               $update[$id] = array('folder_id' => $wantedFolder);
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
