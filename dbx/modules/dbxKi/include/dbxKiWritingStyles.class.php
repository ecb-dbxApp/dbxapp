<?php
namespace dbx\dbxKi;

class dbxKiWritingStyles {

   private const CONFIG_KEY = 'writing_styles';

   public static function defaults(): array {
      $path = dirname(__DIR__) . '/cfg/writing_styles.php';
      $writing_styles = array();
      if (is_file($path)) {
         include $path;
      }
      return is_array($writing_styles) ? $writing_styles : array();
   }

   public static function all(): array {
      $config = dbx()->get_config('dbxKi');
      $stored = array();
      if (is_array($config) && !empty($config[self::CONFIG_KEY])) {
         $raw = $config[self::CONFIG_KEY];
         if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $stored = is_array($decoded) ? $decoded : array();
         } elseif (is_array($raw)) {
            $stored = $raw;
         }
      }
      if (!$stored) {
         return self::defaults();
      }
      return self::normalize($stored);
   }

   public static function save(array $styles): void {
      $normalized = self::normalize($styles);
      if (!$normalized) {
         throw new \InvalidArgumentException('Mindestens ein gueltiger Schreibstil erforderlich.');
      }
      $config = dbx()->get_config('dbxKi');
      if (!is_array($config)) {
         $config = array();
      }
      $config[self::CONFIG_KEY] = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      dbx()->set_config('dbxKi', $config);
   }

   public static function resetToDefaults(): void {
      $config = dbx()->get_config('dbxKi');
      if (!is_array($config)) {
         $config = array();
      }
      unset($config[self::CONFIG_KEY]);
      dbx()->set_config('dbxKi', $config);
   }

   public static function prompt(string $key): string {
      $all = self::all();
      return (string) ($all[$key]['prompt'] ?? ($all['sachlich']['prompt'] ?? ''));
   }

   public static function label(string $key): string {
      $all = self::all();
      return (string) ($all[$key]['label'] ?? $key);
   }

   private static function normalize(array $styles): array {
      $out = array();
      foreach ($styles as $key => $meta) {
         if (!is_array($meta)) {
            continue;
         }
         $key = self::slugKey(is_string($key) ? $key : (string) ($meta['key'] ?? ''));
         if ($key === '') {
            continue;
         }
         $label = trim((string) ($meta['label'] ?? ''));
         $prompt = trim((string) ($meta['prompt'] ?? ''));
         if ($label === '' || $prompt === '') {
            continue;
         }
         $out[$key] = array('label' => $label, 'prompt' => $prompt);
      }
      return $out;
   }

   public static function slugKey(string $key): string {
      $key = strtolower(trim($key));
      $key = preg_replace('/[^a-z0-9_-]+/', '_', $key);
      return trim((string) $key, '_');
   }

   public static function parseFormRows(array $keys, array $labels, array $prompts): array {
      $styles = array();
      $count = max(count($keys), count($labels), count($prompts));
      for ($i = 0; $i < $count; $i++) {
         $styles[] = array(
            'key' => (string) ($keys[$i] ?? ''),
            'label' => (string) ($labels[$i] ?? ''),
            'prompt' => (string) ($prompts[$i] ?? ''),
         );
      }
      $map = array();
      foreach ($styles as $row) {
         $key = self::slugKey((string) ($row['key'] ?? ''));
         if ($key === '') {
            continue;
         }
         $map[$key] = array(
            'label' => trim((string) ($row['label'] ?? '')),
            'prompt' => trim((string) ($row['prompt'] ?? '')),
         );
      }
      return self::normalize($map);
   }
}
