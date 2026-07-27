<?php
namespace dbx\dbxContent;

class dbxContentLng {

   public static function current(): string {
      return function_exists('dbx_lng_current') ? dbx_lng_current() : 'de';
   }

   public static function ddContent(string $lng = ''): string {
      return function_exists('dbx_lng_name') ? dbx_lng_name('content', $lng) : 'content_de';
   }

   public static function ddFolder(string $lng = ''): string {
      return function_exists('dbx_lng_name') ? dbx_lng_name('content_folder', $lng) : 'content_folder_de';
   }

   public static function permalinkMode(): string {
      $mode = strtolower(trim((string) dbx()->get_config('dbxContent', 'permalink_mode')));
      if ($mode === 'undef' || $mode === '') {
         $mode = strtolower(trim((string) dbx()->get_config('dbxContent', 'mode')));
      }

      return $mode === 'cms' ? 'cms' : 'content';
   }

   public static function isCmsPermalinkMode(): bool {
      return self::permalinkMode() === 'cms';
   }
}
