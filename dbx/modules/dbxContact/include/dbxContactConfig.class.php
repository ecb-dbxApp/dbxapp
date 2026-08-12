<?php
namespace dbx\dbxContact;

class dbxContactConfig {

   public static function isFlagEnabled($value, bool $default = false): bool {
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

   public static function mailAdminOnRequest(): bool {
      $val = dbx()->get_cfg('dbxContact', 'mail_admin_on_request');
      return self::isFlagEnabled($val, true);
   }

   public static function mailConfirmRequester(): bool {
      $val = dbx()->get_cfg('dbxContact', 'mail_confirm_requester');
      if (trim((string) $val) === '') {
         return true;
      }
      return self::isFlagEnabled($val, true);
   }

   public static function mailOnReply(): bool {
      $val = dbx()->get_cfg('dbxContact', 'mail_on_reply');
      return self::isFlagEnabled($val, true);
   }

   public static function modulMailEnabled(string $modul, string $key): bool {
      if ($modul === 'dbxContact') {
         if ($key === 'mail_on_reply') {
            return self::mailOnReply();
         }
         if ($key === 'mail_admin_on_request') {
            return self::mailAdminOnRequest();
         }
         if ($key === 'mail_confirm_requester') {
            return self::mailConfirmRequester();
         }
      }

      $val = dbx()->get_cfg($modul, $key);
      if (self::isFlagEnabled($val, false)) {
         return true;
      }

      $mode = strtolower(trim((string) $val));
      return ($mode === 'both' || $mode === 'mail' || strpos($mode, 'mail') !== false);
   }
}
