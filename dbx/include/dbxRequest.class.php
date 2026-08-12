<?php

/**
 * Liest und validiert HTTP-Requestparameter.
 */
class dbxRequest {

   private static bool $bodyRead = false;
   private static string $rawBody = '';
   private static ?array $jsonBody = null;

   /**
    * Liest den unveränderten Request-Body höchstens einmal pro Request.
    */
   public function rawBody(): string {
      if (!self::$bodyRead) {
         $raw = file_get_contents('php://input');
         self::$rawBody = is_string($raw) ? $raw : '';
         self::$bodyRead = true;
      }

      return self::$rawBody;
   }

   /**
    * Liefert einen JSON-Objektbody als Array. Optional dient POST als Fallback.
    */
   public function json(bool $postFallback = false): array {
      if (self::$jsonBody === null) {
         $raw = trim($this->rawBody());
         $decoded = $raw !== '' ? json_decode($raw, true) : null;
         self::$jsonBody = is_array($decoded) ? $decoded : array();
      }

      if (!self::$jsonBody && $postFallback && is_array($_POST)) {
         return $_POST;
      }

      return self::$jsonBody;
   }

   /**
    * Liest GET oder POST; POST hat Vorrang.
    */
   public function request(string $varname, $default = '', string $rules = 'parameter') {
      $value = $default;
      $dangerValue = '';

      if (isset($_GET[$varname])) {
         $dangerValue = $_GET[$varname];
      }
      if (isset($_POST[$varname])) {
         $dangerValue = $_POST[$varname];
      }

      if ($dangerValue !== '' && $dangerValue !== null
          && dbx()->validate_var($dangerValue, $rules, $varname)) {
         $value = $dangerValue;
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

      $dangerValue = $_GET[$varname];
      return dbx()->validate_var($dangerValue, $rules, $varname)
         ? $dangerValue
         : $default;
   }

   /**
    * Liest ausschließlich POST.
    */
   public function post(string $varname, $default = '', string $rules = 'parameter') {
      if (!isset($_POST[$varname])) {
         return $default;
      }

      $dangerValue = $_POST[$varname];
      return dbx()->validate_var($dangerValue, $rules, $varname)
         ? $dangerValue
         : $default;
   }
}
