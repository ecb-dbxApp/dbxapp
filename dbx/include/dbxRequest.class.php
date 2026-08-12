<?php

/**
 * Liest und validiert HTTP-Requestparameter.
 */
class dbxRequest {

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

