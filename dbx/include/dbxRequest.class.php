<?php

/**
 * @brief Liest und validiert HTTP-Requestparameter zentral und typisiert.
 */
class dbxRequest {

   private static bool $body_read = false;
   private static string $raw_body = '';
   private static ?array $json_body = null;

   /**
    * Liest den unveränderten Request-Body höchstens einmal pro Request.
    */
   public function raw_body(): string {
      if (!self::$body_read) {
         $raw = file_get_contents('php://input');
         self::$raw_body = is_string($raw) ? $raw : '';
         self::$body_read = true;
      }

      return self::$raw_body;
   }

   /**
    * Liefert einen JSON-Objektbody als Array. Optional dient POST als Fallback.
    */
   public function json(bool $post_fallback = false): array {
      if (self::$json_body === null) {
         $raw = trim($this->raw_body());
         $decoded = $raw !== '' ? json_decode($raw, true) : null;
         self::$json_body = is_array($decoded) ? $decoded : array();
      }

      if (!self::$json_body && $post_fallback && is_array($_POST)) {
         return $_POST;
      }

      return self::$json_body;
   }

   /**
    * Liest GET oder POST; POST hat Vorrang.
    */
   public function request(string $varname, $default = '', string $rules = 'parameter') {
      $value = $default;
      $danger_value = '';

      if (isset($_GET[$varname])) {
         $danger_value = $_GET[$varname];
      }
      if (isset($_POST[$varname])) {
         $danger_value = $_POST[$varname];
      }

      if ($danger_value !== '' && $danger_value !== null
          && dbx()->validate_var($danger_value, $rules, $varname)) {
         $value = $danger_value;
      }

      return $value;
   }

   /**
    * Liest ausschließlich GET.
    */
   public function get(string $varname, $default = '', string $rules = 'parameter') {
      if (!isset($_GET[$varname])) {
         return $default;
      }

      $danger_value = $_GET[$varname];
      return dbx()->validate_var($danger_value, $rules, $varname)
         ? $danger_value
         : $default;
   }

   /**
    * Liest ausschließlich POST.
    */
   public function post(string $varname, $default = '', string $rules = 'parameter') {
      if (!isset($_POST[$varname])) {
         return $default;
      }

      $danger_value = $_POST[$varname];
      return dbx()->validate_var($danger_value, $rules, $varname)
         ? $danger_value
         : $default;
   }
}
