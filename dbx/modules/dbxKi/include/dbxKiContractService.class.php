<?php
namespace dbx\dbxKi;

/**
 * Verbindlicher Auftrag-/Antwortvertrag fuer alle dbxKi-Pipelines.
 *
 * Die KI darf nur die explizit deklarierten Outputs und Assets liefern.
 * Ausfuehrbare Aktionen und feste Zielparameter bleiben Bestandteil des
 * signierten Auftrags und werden beim Import von dbxKi rekonstruiert.
 */
class dbxKiContractService {
   public const VERSION = '2.0';
   private const OUTPUT_PREFIX = '{{output:';

   public function create(
      string $area,
      string $recipe,
      array $metadata,
      array $jobTemplate,
      array $outputs,
      array $assets = array(),
      array $snapshot = array()
   ): array {
      $contract = array(
         'contract_version' => self::VERSION,
         'contract_id' => bin2hex(random_bytes(16)),
         'issued_at' => gmdate('c'),
         'area' => strtolower(trim($area)),
         'recipe' => strtolower(trim($recipe)),
         'metadata' => $metadata,
         'job_template' => $jobTemplate,
         'outputs' => $this->normalizeOutputDefinitions($outputs),
         'assets' => $this->normalizeAssetDefinitions($assets),
         'snapshot' => $snapshot,
      );
      $contract['contract_hash'] = $this->hash($contract);
      $contract['signature'] = $this->signature($contract);
      return $contract;
   }

   public function verify(array $contract): array {
      foreach (array('contract_version', 'contract_id', 'area', 'recipe', 'job_template', 'outputs', 'contract_hash', 'signature') as $key) {
         if (!array_key_exists($key, $contract)) {
            throw new \InvalidArgumentException('Auftragsvertrag unvollstaendig: ' . $key . ' fehlt.');
         }
      }
      if (!hash_equals(self::VERSION, (string)$contract['contract_version'])) {
         throw new \InvalidArgumentException('Nicht unterstuetzte Auftragsversion.');
      }
      if (!preg_match('/^[a-f0-9]{32}$/', (string)$contract['contract_id'])) {
         throw new \InvalidArgumentException('Ungueltige Auftrags-ID.');
      }
      $expectedHash = $this->hash($contract);
      if (!hash_equals($expectedHash, (string)$contract['contract_hash'])) {
         throw new \InvalidArgumentException('Der Auftragsinhalt wurde veraendert.');
      }
      $expectedSignature = $this->signature($contract);
      if (!hash_equals($expectedSignature, (string)$contract['signature'])) {
         throw new \InvalidArgumentException('Die Auftragssignatur ist ungueltig.');
      }
      return $contract;
   }

   public function bind(array $contract, array $answer, string $assetsDir = ''): array {
      $contract = $this->verify($contract);
      $this->assertNotConsumed($contract);
      if (!hash_equals((string)$contract['contract_id'], trim((string)($answer['contract_id'] ?? '')))) {
         throw new \InvalidArgumentException('answer.json gehoert nicht zu diesem Auftrag.');
      }
      if (!hash_equals((string)$contract['contract_hash'], trim((string)($answer['contract_hash'] ?? '')))) {
         throw new \InvalidArgumentException('answer.json verwendet einen anderen Vertragsstand.');
      }
      $unknownTopLevel = array_diff(array_keys($answer), array('contract_id', 'contract_hash', 'outputs'));
      if ($unknownTopLevel) {
         throw new \InvalidArgumentException('Nicht erlaubte Felder in answer.json: ' . implode(', ', $unknownTopLevel));
      }

      $definitions = $this->normalizeOutputDefinitions((array)$contract['outputs']);
      $values = is_array($answer['outputs'] ?? null) ? $answer['outputs'] : array();
      $unknown = array_diff(array_keys($values), array_keys($definitions));
      if ($unknown) {
         throw new \InvalidArgumentException('Nicht erlaubte Antwortfelder: ' . implode(', ', $unknown));
      }
      foreach ($definitions as $name => $definition) {
         $required = !array_key_exists('required', $definition) || (bool)$definition['required'];
         if ($required && !array_key_exists($name, $values)) {
            throw new \InvalidArgumentException('Pflichtausgabe fehlt: ' . $name);
         }
         if (!array_key_exists($name, $values)) {
            continue;
         }
         $values[$name] = $this->validateOutputValue($name, $values[$name], $definition);
      }

      $this->validateAssets((array)$contract['assets'], $assetsDir);
      $job = $this->bindValue($contract['job_template'], $values);
      if ($this->containsOutputMarker($job)) {
         throw new \InvalidArgumentException('Der Auftrag enthaelt nicht aufgeloeste Antwortfelder.');
      }
      if ($this->containsForbiddenPlaceholder($job)) {
         throw new \InvalidArgumentException('Die Antwort enthaelt noch einen KI-Platzhalter.');
      }

      return array(
         'contract' => $contract,
         'answer' => $answer,
         'manifest' => is_array($contract['metadata'] ?? null) ? $contract['metadata'] : array(),
         'job' => is_array($job) ? $job : array(),
      );
   }

   public function assertNotConsumed(array $contract): void {
      $id = (string)($contract['contract_id'] ?? '');
      if ($id !== '' && !empty($_SESSION['dbx']['dbxKi']['consumed_contracts'][$id])) {
         throw new \RuntimeException('Dieser KI-Auftrag wurde bereits erfolgreich ausgefuehrt.');
      }
   }

   public function consume(array $contract): void {
      $contract = $this->verify($contract);
      if (!isset($_SESSION['dbx']['dbxKi']['consumed_contracts']) || !is_array($_SESSION['dbx']['dbxKi']['consumed_contracts'])) {
         $_SESSION['dbx']['dbxKi']['consumed_contracts'] = array();
      }
      $_SESSION['dbx']['dbxKi']['consumed_contracts'][(string)$contract['contract_id']] = time();
      if (count($_SESSION['dbx']['dbxKi']['consumed_contracts']) > 500) {
         asort($_SESSION['dbx']['dbxKi']['consumed_contracts'], SORT_NUMERIC);
         $_SESSION['dbx']['dbxKi']['consumed_contracts'] = array_slice($_SESSION['dbx']['dbxKi']['consumed_contracts'], -500, null, true);
      }
   }

   public function answerTemplate(array $contract): array {
      $contract = $this->verify($contract);
      $outputs = array();
      foreach ((array)$contract['outputs'] as $name => $definition) {
         $type = strtolower((string)($definition['type'] ?? 'string'));
         $outputs[$name] = $type === 'array' ? array() : ($type === 'boolean' ? false : '___KI_FUELLEN___');
      }
      return array(
         'contract_id' => $contract['contract_id'],
         'contract_hash' => $contract['contract_hash'],
         'outputs' => $outputs,
      );
   }

   public function fingerprint(array $value): string {
      return hash('sha256', $this->canonicalJson($value));
   }

   public function hash(array $contract): string {
      unset($contract['signature'], $contract['contract_hash']);
      return hash('sha256', $this->canonicalJson($contract));
   }

   private function signature(array $contract): string {
      $hash = (string)($contract['contract_hash'] ?? $this->hash($contract));
      return hash_hmac('sha256', 'dbxKi-contract|' . $hash, $this->secret());
   }

   private function secret(): string {
      $config = dbx()->get_cfg('dbx');
      $secret = is_array($config) ? trim((string)($config['secure'] ?? '')) : '';
      if (strlen($secret) < 32) {
         throw new \RuntimeException('Der dbx-Systemschluessel fuer dbxKi-Auftraege fehlt.');
      }
      return $secret;
   }

   private function canonicalJson($value): string {
      $value = $this->canonicalize($value);
      $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
      if (!is_string($json)) {
         throw new \RuntimeException('Auftragsvertrag konnte nicht kanonisiert werden.');
      }
      return $json;
   }

   private function canonicalize($value) {
      if (!is_array($value)) {
         return $value;
      }
      if (!array_is_list($value)) {
         ksort($value, SORT_STRING);
      }
      foreach ($value as $key => $item) {
         $value[$key] = $this->canonicalize($item);
      }
      return $value;
   }

   private function normalizeOutputDefinitions(array $outputs): array {
      $normalized = array();
      foreach ($outputs as $name => $definition) {
         $name = trim((string)$name);
         if ($name === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
            throw new \InvalidArgumentException('Ungueltiger Output-Name: ' . $name);
         }
         if (is_string($definition)) {
            $definition = array('type' => $definition);
         }
         $definition = is_array($definition) ? $definition : array();
         $definition['type'] = strtolower(trim((string)($definition['type'] ?? 'string')));
         if (!in_array($definition['type'], array('string', 'html', 'integer', 'boolean', 'array'), true)) {
            throw new \InvalidArgumentException('Ungueltiger Output-Typ fuer ' . $name . '.');
         }
         $normalized[$name] = $definition;
      }
      ksort($normalized, SORT_STRING);
      return $normalized;
   }

   private function normalizeAssetDefinitions(array $assets): array {
      $normalized = array();
      foreach ($assets as $name => $definition) {
         $name = ltrim(str_replace('\\', '/', trim((string)$name)), '/');
         if ($name === '' || str_contains($name, '..')) {
            throw new \InvalidArgumentException('Ungueltiger Asset-Name.');
         }
         $normalized[$name] = is_array($definition) ? $definition : array('required' => (bool)$definition);
      }
      ksort($normalized, SORT_STRING);
      return $normalized;
   }

   private function validateOutputValue(string $name, $value, array $definition) {
      $type = (string)($definition['type'] ?? 'string');
      if ($type === 'integer') {
         if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException($name . ' muss eine Ganzzahl sein.');
         }
         return (int)$value;
      }
      if ($type === 'boolean') {
         if (!is_bool($value) && !in_array($value, array(0, 1, '0', '1'), true)) {
            throw new \InvalidArgumentException($name . ' muss boolesch sein.');
         }
         return (bool)$value;
      }
      if ($type === 'array') {
         if (!is_array($value)) {
            throw new \InvalidArgumentException($name . ' muss ein Array sein.');
         }
         return $value;
      }
      if (is_array($value) || is_object($value)) {
         throw new \InvalidArgumentException($name . ' muss Text sein.');
      }
      $value = (string)$value;
      if (($definition['allow_empty'] ?? false) !== true && trim($value) === '') {
         throw new \InvalidArgumentException($name . ' darf nicht leer sein.');
      }
      if ($this->containsForbiddenPlaceholder($value)) {
         throw new \InvalidArgumentException($name . ' enthaelt noch einen KI-Platzhalter.');
      }
      $max = max(0, (int)($definition['max_length'] ?? 0));
      if ($max > 0 && mb_strlen($value, 'UTF-8') > $max) {
         throw new \InvalidArgumentException($name . ' ist laenger als ' . $max . ' Zeichen.');
      }
      if ($type === 'html') {
         $forbidden = '/<\s*(script|style|iframe|object|embed|form|input|meta|link)\b|\son[a-z]+\s*=|javascript\s*:|\sstyle\s*=/i';
         if (preg_match($forbidden, $value)) {
            throw new \InvalidArgumentException($name . ' enthaelt nicht erlaubtes aktives HTML oder Inline-CSS.');
         }
      }
      return $value;
   }

   private function bindValue($value, array $outputs) {
      if (is_array($value)) {
         $out = array();
         foreach ($value as $key => $item) {
            $out[$key] = $this->bindValue($item, $outputs);
         }
         return $out;
      }
      if (!is_string($value)) {
         return $value;
      }
      if (preg_match('/^\{\{output:([A-Za-z0-9_.-]+)\}\}$/', $value, $match)) {
         $name = $match[1];
         if (!array_key_exists($name, $outputs)) {
            throw new \InvalidArgumentException('Antwortwert fehlt: ' . $name);
         }
         return $outputs[$name];
      }
      if (str_contains($value, self::OUTPUT_PREFIX)) {
         throw new \InvalidArgumentException('Output-Platzhalter muessen den gesamten Wert bilden.');
      }
      return $value;
   }

   private function containsOutputMarker($value): bool {
      if (is_array($value)) {
         foreach ($value as $item) {
            if ($this->containsOutputMarker($item)) return true;
         }
         return false;
      }
      return is_string($value) && str_contains($value, self::OUTPUT_PREFIX);
   }

   private function containsForbiddenPlaceholder($value): bool {
      if (is_array($value)) {
         foreach ($value as $item) {
            if ($this->containsForbiddenPlaceholder($item)) return true;
         }
         return false;
      }
      return is_string($value) && (str_contains($value, '___KI_FUELLEN___') || str_contains($value, '___PFAD___'));
   }

   private function validateAssets(array $definitions, string $assetsDir): void {
      $definitions = $this->normalizeAssetDefinitions($definitions);
      $found = array();
      if ($assetsDir !== '' && is_dir($assetsDir)) {
         $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($assetsDir, \FilesystemIterator::SKIP_DOTS)
         );
         foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($assetsDir, '/\\')) + 1));
            $found[$relative] = $file->getPathname();
         }
      }
      $unknown = array_diff(array_keys($found), array_keys($definitions));
      if ($unknown) {
         throw new \InvalidArgumentException('Nicht erlaubte Assets: ' . implode(', ', $unknown));
      }
      foreach ($definitions as $name => $definition) {
         if (($definition['required'] ?? false) && !isset($found[$name])) {
            throw new \InvalidArgumentException('Pflicht-Asset fehlt: ' . $name);
         }
         if (!isset($found[$name])) continue;
         $max = max(0, (int)($definition['max_bytes'] ?? 0));
         if ($max > 0 && (int)filesize($found[$name]) > $max) {
            throw new \InvalidArgumentException('Asset ist zu gross: ' . $name);
         }
         $extensions = array_map('strtolower', (array)($definition['extensions'] ?? array()));
         if ($extensions && !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $extensions, true)) {
            throw new \InvalidArgumentException('Asset-Dateityp nicht erlaubt: ' . $name);
         }
         $expectedWidth = max(0, (int)($definition['width'] ?? 0));
         $expectedHeight = max(0, (int)($definition['height'] ?? 0));
         if ($expectedWidth > 0 || $expectedHeight > 0) {
            $image = @getimagesize($found[$name]);
            if (!is_array($image)) {
               throw new \InvalidArgumentException('Asset ist kein lesbares Bild: ' . $name);
            }
            if ($expectedWidth > 0 && (int)$image[0] !== $expectedWidth) {
               throw new \InvalidArgumentException('Asset-Breite muss ' . $expectedWidth . ' px sein: ' . $name);
            }
            if ($expectedHeight > 0 && (int)$image[1] !== $expectedHeight) {
               throw new \InvalidArgumentException('Asset-Hoehe muss ' . $expectedHeight . ' px sein: ' . $name);
            }
         }
      }
   }
}
