<?php
namespace dbx\dbxContent;

class dbxContentLng {

   public static function current(): string {
      return dbx()->lng_current();
   }

   public static function dd_content(string $lng = ''): string {
      return dbx()->lng_name('content', $lng);
   }

   public static function dd_folder(string $lng = ''): string {
      return dbx()->lng_name('content_folder', $lng);
   }

   public static function permalink_mode(): string {
      $mode = strtolower(trim((string) dbx()->get_cfg('dbxContent', 'permalink_mode')));
      if ($mode === 'undef' || $mode === '') {
         $mode = strtolower(trim((string) dbx()->get_cfg('dbxContent', 'mode')));
      }

      return $mode === 'cms' ? 'cms' : 'content';
   }

   public static function is_cms_permalink_mode(): bool {
      return self::permalink_mode() === 'cms';
   }
}
