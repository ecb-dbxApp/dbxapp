<?php
namespace dbx\dbxContact;

class dbxContactConfig {

   public static function is_flag_enabled($value, bool $default = false): bool {
      $value = strtolower(trim((string) $value));
      if ($value === '') {
         return $default;
      }
      if (in_array($value, array('0', 'no', 'off', 'false', 'nein'), true)) {
         return false;
      }
      if (in_array($value, array('1', 'yes', 'on', 'true', 'ja'), true)) {
         return true;
      }
      return $default;
   }

   public static function mail_admin_on_request(): bool {
      $val = dbx()->get_cfg('dbxContact', 'mail_admin_on_request');
      return self::is_flag_enabled($val, true);
   }

   public static function mail_confirm_requester(): bool {
      $val = dbx()->get_cfg('dbxContact', 'mail_confirm_requester');
      if (trim((string) $val) === '') {
         return true;
      }
      return self::is_flag_enabled($val, true);
   }

   public static function mail_on_reply(): bool {
      $val = dbx()->get_cfg('dbxContact', 'mail_on_reply');
      return self::is_flag_enabled($val, true);
   }

   public static function modul_mail_enabled(string $modul, string $key): bool {
      if ($modul === 'dbxContact') {
         if ($key === 'mail_on_reply') {
            return self::mail_on_reply();
         }
         if ($key === 'mail_admin_on_request') {
            return self::mail_admin_on_request();
         }
         if ($key === 'mail_confirm_requester') {
            return self::mail_confirm_requester();
         }
      }

      $val = dbx()->get_cfg($modul, $key);
      if (self::is_flag_enabled($val, false)) {
         return true;
      }

      $mode = strtolower(trim((string) $val));
      return ($mode === 'both' || $mode === 'mail' || strpos($mode, 'mail') !== false);
   }
}
