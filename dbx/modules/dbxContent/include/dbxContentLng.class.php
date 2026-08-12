<?php
namespace dbx\dbxContent;

class dbxContentLng {

   public static function current(): string {
      return dbx()->lng_current();
   }

   public static function ddContent(string $lng = ''): string {
      return dbx()->lng_name('content', $lng);
   }

   public static function ddFolder(string $lng = ''): string {
      return dbx()->lng_name('content_folder', $lng);
   }

   public static function permalinkMode(): string {
      $mode = strtolower(trim((string) dbx()->get_cfg('dbxContent', 'permalink_mode')));
      if ($mode === 'undef' || $mode === '') {
         $mode = strtolower(trim((string) dbx()->get_cfg('dbxContent', 'mode')));
      }

      return $mode === 'cms' ? 'cms' : 'content';
   }

   public static function isCmsPermalinkMode(): bool {
      return self::permalinkMode() === 'cms';
   }
}
