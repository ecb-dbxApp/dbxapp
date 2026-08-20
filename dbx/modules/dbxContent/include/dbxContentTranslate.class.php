<?php
namespace dbx\dbxContent;

class dbxContentTranslate {

   private static array $warnings = array();
   private static array $logged = array();

   public static function provider(): string {
      $provider = strtolower(trim((string) dbx()->get_cfg('dbxContent', 'lng_translate_provider', 'copy')));
      if ($provider === '' || $provider === 'undef') {
         return 'copy';
      }
      return $provider;
   }

   public static function clear_warnings(): void {
      self::$warnings = array();
      self::$logged = array();
   }

   public static function warnings(): array {
      return self::$warnings;
   }

   public static function translate(string $text, string $from, string $to, string $context = 'content'): string {
      $text = trim($text);
      if ($text === '' || $from === $to) {
         return $text;
      }

      $provider = self::provider();
      switch ($provider) {
         case 'none':
            return '';
         case 'copy':
            return $text;
         case 'custom':
            return self::translate_custom($text, $from, $to, $context);
         case 'deepl':
            return self::translate_deep_l($text, $from, $to, $context);
         case 'openai':
            return self::translate_open_ai($text, $from, $to, $context);
         default:
            self::add_warning($provider, 'Unbekannter Provider, Text wird kopiert.', $context, $to);
            return $text;
      }
   }

   private static function add_warning(string $provider, string $message, string $context = '', string $lng = ''): void {
      $provider = strtolower(trim($provider));
      $message = trim($message);
      if ($message === '') {
         return;
      }

      $key = $provider . '|' . $message;
      if (isset(self::$logged[$key])) {
         return;
      }
      self::$logged[$key] = 1;
      self::$warnings[] = array(
         'provider' => $provider,
         'message' => $message,
         'context' => $context,
         'lng' => $lng,
      );

      if (function_exists('dbx') && is_object(dbx())) {
         dbx()->sys_msg(
            'warning',
            'dbxContentTranslate',
            $lng !== '' ? $lng : $context,
            $message,
            $provider
         );
      }
   }

   private static function translate_custom(string $text, string $from, string $to, string $context): string {
      $file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxContent/translate.php');
      if (!is_file($file)) {
         self::add_warning('custom', 'translate.php fehlt, Text wird kopiert.', $context, $to);
         return $text;
      }

      $result = $text;
      include $file;
      if (!is_string($result) || trim($result) === '') {
         self::add_warning('custom', 'Custom-Translate lieferte kein Ergebnis.', $context, $to);
         return $text;
      }
      return $result;
   }

   private static function translate_deep_l(string $text, string $from, string $to, string $context): string {
      $key = trim((string) dbx()->get_cfg('dbxContent', 'lng_translate_api_key', ''));
      if ($key === '') {
         self::add_warning('deepl', 'API-Key fehlt (lng_translate_api_key), Text wird kopiert.', $context, $to);
         return $text;
      }

      $url = trim((string) dbx()->get_cfg('dbxContent', 'lng_translate_api_url', ''));
      if ($url === '') {
         $url = 'https://api-free.deepl.com/v2/translate';
      }

      $payload = http_build_query(array(
         'auth_key' => $key,
         'text' => $text,
         'source_lang' => strtoupper($from),
         'target_lang' => strtoupper($to),
      ));

      $response = self::http_post($url, $payload, array('Content-Type: application/x-www-form-urlencoded'), false, 'deepl', $context, $to);
      if (!is_array($response)) {
         return $text;
      }

      if (isset($response['message']) && is_string($response['message'])) {
         self::add_warning('deepl', $response['message'], $context, $to);
         return $text;
      }

      $trans = $response['translations'][0]['text'] ?? '';
      if (!is_string($trans) || trim($trans) === '') {
         self::add_warning('deepl', 'DeepL-Antwort ohne Text, Original wird verwendet.', $context, $to);
         return $text;
      }
      return $trans;
   }

   private static function translate_open_ai(string $text, string $from, string $to, string $context): string {
      $key = trim((string) dbx()->get_cfg('dbxContent', 'lng_translate_api_key', ''));
      if ($key === '') {
         self::add_warning('openai', 'API-Key fehlt (lng_translate_api_key), Text wird kopiert.', $context, $to);
         return $text;
      }

      $url = trim((string) dbx()->get_cfg('dbxContent', 'lng_translate_api_url', ''));
      if ($url === '') {
         $url = 'https://api.openai.com/v1/chat/completions';
      }

      $model = trim((string) dbx()->get_cfg('dbxContent', 'lng_translate_model', 'gpt-4o-mini'));
      if ($model === '') {
         $model = 'gpt-4o-mini';
      }

      $prompt = 'Translate the following ' . $context . ' from ' . $from . ' to ' . $to
         . '. Preserve HTML tags and [modul=...][/modul] markers exactly. Return only the translation.';

      $body = json_encode(array(
         'model' => $model,
         'messages' => array(
            array('role' => 'system', 'content' => $prompt),
            array('role' => 'user', 'content' => $text),
         ),
         'temperature' => 0.2,
      ), JSON_UNESCAPED_UNICODE);

      $response = self::http_post($url, $body, array(
         'Content-Type: application/json',
         'Authorization: Bearer ' . $key,
      ), true, 'openai', $context, $to);

      if (!is_array($response)) {
         return $text;
      }

      if (isset($response['error']['message']) && is_string($response['error']['message'])) {
         self::add_warning('openai', $response['error']['message'], $context, $to);
         return $text;
      }

      $trans = $response['choices'][0]['message']['content'] ?? '';
      if (!is_string($trans) || trim($trans) === '') {
         self::add_warning('openai', 'OpenAI-Antwort ohne Text, Original wird verwendet.', $context, $to);
         return $text;
      }
      return trim($trans);
   }

   private static function http_post(string $url, string $body, array $headers, bool $json = false, string $provider = '', string $context = '', string $lng = '') {
      if (!function_exists('curl_init')) {
         self::add_warning($provider !== '' ? $provider : 'translate', 'curl nicht verfuegbar, Text wird kopiert.', $context, $lng);
         return null;
      }

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);

      $raw = curl_exec($ch);
      $errno = curl_errno($ch);
      $error = curl_error($ch);
      $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($errno !== 0) {
         self::add_warning($provider !== '' ? $provider : 'translate', 'HTTP-Fehler: ' . $error, $context, $lng);
         return null;
      }
      if ($code >= 400) {
         self::add_warning($provider !== '' ? $provider : 'translate', 'HTTP ' . $code . ' von API', $context, $lng);
      }
      if (!is_string($raw) || $raw === '') {
         self::add_warning($provider !== '' ? $provider : 'translate', 'Leere API-Antwort', $context, $lng);
         return null;
      }

      $decoded = json_decode($raw, true);
      if (!is_array($decoded)) {
         self::add_warning($provider !== '' ? $provider : 'translate', 'Ungueltige JSON-Antwort', $context, $lng);
         return null;
      }
      return $decoded;
   }
}
