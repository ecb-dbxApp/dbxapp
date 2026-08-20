<?php
namespace dbx\dbxKi;

require_once __DIR__ . '/dbxKiSessionState.class.php';

use dbx\dbxContent\dbxContentLng;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
require_once __DIR__ . '/dbxKiContractService.class.php';

class dbxKiBundleService {

   private const BUNDLE_VERSION = '2.0';
   private const SESSION_KEY = 'bundle_jobs';
   private const AREA = 'cms';

   private function cms(): dbxKiCmsService {
      return dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');
   }

   private function contracts(): dbxKiContractService {
      return dbx()->get_include_obj('dbxKiContractService', 'dbxKi');
   }

   private function help(): dbxKiHelp {
      return dbx()->get_include_obj('dbxKiHelp', 'dbxKi');
   }

   private function with_module_bar(array $data, string $screen, string $actions_html = '', string $bar_title = ''): array {
      return array_merge($data, $this->help()->module_bar_template_data($screen, $actions_html, $bar_title));
   }

   private function module_url(string $run1, array $params = array()): string {
      $url = '?dbx_modul=dbxKi&dbx_run1=' . rawurlencode($run1);
      $params = array_filter($params, static fn($value): bool => $value !== null && $value !== '');
      return $this->append_url_params($url, $params);
   }

   /** Zentraler Laufzeithelfer mit kleinem Fallback für isolierte Servicetests. */
   private function append_url_params(string $url, array $params): string {
      if (!$params) {
         return $url;
      }
      if (function_exists('dbx')) {
         return \dbx()->append_url_params($url, $params);
      }
      return $url . (str_contains($url, '?') ? '&' : '?')
         . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
   }

   private function esc($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function config_int(string $key, int $default): int {
      return max(1, (int)dbx()->get_cfg('dbxKi', $key, $default));
   }

   private function session_bucket(): array {
      return dbxKiSessionState::bucket(self::SESSION_KEY);
   }

   private function get_job(string $token): array {
      $token = $this->sanitize_token($token);
      if ($token === '') {
         return array();
      }
      $bucket = $this->session_bucket();
      return is_array($bucket[$token] ?? null) ? $bucket[$token] : array();
   }

   private function set_job(string $token, array $state): array {
      $token = $this->sanitize_token($token);
      if ($token === '') {
         return array();
      }
      $state['proc_key'] = $token;
      $state['updated_at'] = date('Y-m-d H:i:s');
      dbxKiSessionState::put(self::SESSION_KEY, $token, $state);
      return $state;
   }

   private function sanitize_token(string $token): string {
      return preg_replace('/[^A-Za-z0-9_-]+/', '', (string)$token);
   }

   private function new_token(): string {
      return substr(md5(session_id() . microtime(true) . mt_rand()), 0, 16);
   }

   private function temp_root(): string {
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/tmp/ki-bundle';
      $dir = dbx()->os_path($dir);
      if (!is_dir($dir)) {
         @mkdir($dir, 0777, true);
      }
      return $dir;
   }

   private function job_dir(string $token): string {
      return rtrim($this->temp_root(), '/\\') . DIRECTORY_SEPARATOR . $this->sanitize_token($token);
   }

   private function remove_job_dir(string $token): void {
      $dir = $this->job_dir($token);
      if (!is_dir($dir)) {
         return;
      }
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ($it as $file) {
         if ($file->isDir()) {
            @rmdir($file->getPathname());
         } else {
            @unlink($file->getPathname());
         }
      }
      @rmdir($dir);
   }

   public function describe_bundle(): array {
      $cms = $this->cms();
      return array(
         'ok' => 1,
         'bundle_version' => self::BUNDLE_VERSION,
         'api_version' => '0.1',
         'area' => self::AREA,
         'module' => 'dbxKi',
         'purpose' => 'Offline KI-Bundles fuer CMS-Aenderungen ohne direkten API-Zugriff.',
         'upload_url' => $this->module_url('bundle_import'),
         'export_url' => $this->module_url('bundle_export'),
         'allowed_actions' => $this->allowed_actions(),
         'files' => array(
            'auftrag.contract.json' => 'Unveraenderter, signierter dbxKi-Auftrag',
            'answer.json' => 'Ausschliesslich die im Auftrag deklarierten Inhaltsfelder',
            'assets/' => 'Optionale Bilder und Dateien',
            'README.md' => 'Kurzbeschreibung fuer Menschen',
         ),
         'refs' => array(
            'syntax' => '$ref:{step_id}.{field}',
            'examples' => array('$ref:hero.id', '$ref:page.id', '$ref:hero_assign.usage_id'),
         ),
         'asset_ref' => 'In media.create_base64: asset_ref = Dateiname relativ zu assets/ (z.B. hero.jpg). Kein data_base64.',
         'recipes' => array(
            'page.create.v1' => 'Medien hochladen, Seite anlegen, Hero/Gallery zuweisen',
            'page.update.v1' => 'Seite per id patchen',
            'translation.v1' => 'translation.apply',
         ),
         'automation' => array(
            'default' => 'dbxKi importiert ZIP/JSON, validiert alle Steps und zeigt die Vorschau.',
            'auto_execute' => 'Antwort-Bundles werden immer zuerst als Vorschau gezeigt und nur mit dbxKi-Ausfuehrungstoken gestartet.',
            'ki_contract' => 'Die KI liefert nur den unveraenderten Vertrag, answer.json und erlaubte assets/. Aktionen werden von dbxKi rekonstruiert.',
         ),
         'cms' => $cms->bundle_system_describe(),
      );
   }

   private function allowed_actions(): array {
      $out = array();
      foreach ($this->cms()->bundle_action_catalog() as $action => $meta) {
         if ($this->cms()->bundle_is_allowed_in_package($action)) {
            $out[$action] = $meta;
         }
      }
      return $out;
   }

   /**
    * Baut das gemeinsame, CSRF-geschuetzte ZIP-Importformular.
    *
    * Startseite und Briefing verwenden absichtlich dieselbe Formular-ID. Der
    * Import-Endpunkt kann den dbxForm-Sicherheitstoken dadurch unabhaengig von
    * der aufrufenden Seite pruefen. Die ZIP selbst wird weiterhin im spezialisierten
    * Bundle-Validator kontrolliert; dbxForm verantwortet Submit, Token und Meldungen.
    */
   private function import_form(string $template, string $return_run1 = '') {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('ki-bundle-import', $template);
      $form->set_action($this->module_url('bundle_import'));
      // Nicht das gesamte Array ersetzen: dbxForm hat dort bereits sein
      // Security-Token abgelegt.
      $form->set_data_value('return_run1', $this->sanitize_return_run1($return_run1));
      $form->set_data_value('bundle_zip', '');
      $form->_msg_info = '';
      $form->add_fld('return_run1', 'dbx|hidden', rules: 'parameter', dd: '');
      $form->add_fld(
         'bundle_zip',
         'dbxKi|ki-bundle-zip-upload',
         label: $template === 'ki-briefing-import-panel' ? '3  KI-Antwort-ZIP' : 'Antwort-ZIP',
         rules: '',
         tooltip: 'ZIP-Datei mit auftrag.contract.json und answer.json auswaehlen.',
         errormsg: 'Bitte eine gueltige ZIP-Datei auswaehlen.',
         dd: ''
      );
      return $form;
   }

   /**
    * Rendert den kompakten Importblock unter einem KI-Briefing.
    */
   public function render_import_panel(string $return_run1): string {
      return $this->import_form('ki-briefing-import-panel', $return_run1)->run();
   }

   /**
    * Rendert die Bundle-Startseite mit einem vollwertigen dbxForm-Upload.
    */
   public function render_start_page(): string {
      $form = $this->import_form('ki-bundle-start');
      foreach ($this->with_module_bar(array(
         'api_url' => $this->esc($this->module_url('api')),
         'describe_url' => $this->esc($this->module_url('bundle_describe')),
         'export_url' => $this->esc($this->module_url('bundle_export')),
         'bundle_version' => $this->esc(self::BUNDLE_VERSION),
         'execute_token' => $this->esc($this->cms()->bundle_execute_token()),
      ), 'bundle') as $key => $value) {
         $form->add_rep((string)$key, $value);
      }
      return $form->run();
   }

   /**
    * Liest und validiert einen Bundle-Import.
    *
    * Browser-Uploads muessen als dbxForm-Submit mit passendem Sicherheitstoken
    * eintreffen. Maschinenlesbare job_json-Aufrufe bleiben ein API-Pfad und
    * werden wie bisher durch die API-/Jobvalidierung behandelt.
    */
   public function handle_import(): string {
      // Ergebnis-Fenster: derselbe Endpunkt liefert bei einem reinen GET mit
      // bekanntem Token (kein neuer Upload) direkt die bereits gespeicherte
      // Vorschau erneut aus - dadurch ist die Vorschau per URL adressierbar
      // und laesst sich in ein openWin-Fenster laden (siehe handleImport()
      // Erfolgszweig unten und kiResultWindow.js).
      $existing_token = $this->sanitize_token((string)dbx()->get_request_var('token', '', '*'));
      if ($existing_token !== '' && $this->first_upload_file() === array() && !isset($_POST['return_run1'])) {
         $state = $this->get_job($existing_token);
         if ($state !== array()) {
            return $this->render_preview_page($existing_token, $state);
         }
      }

      $is_browser_upload = $this->first_upload_file() !== array() || isset($_POST['return_run1']);
      try {
         if ($is_browser_upload) {
            $import_form = $this->import_form(
               'ki-briefing-import-panel',
               (string)($_POST['return_run1'] ?? '')
            );
            if (!$import_form->submit()) {
               throw new \RuntimeException('Ungueltiger oder abgelaufener Formular-Token.');
            }
         }
         $token = $this->new_token();
         $payload = $this->read_import_payload($token);
         $contract = is_array($payload['contract'] ?? null) ? $payload['contract'] : array();
         $answer = is_array($payload['answer'] ?? null) ? $payload['answer'] : array();
         $assets_dir = (string)($payload['assets_dir'] ?? '');
         $readme = (string)($payload['readme'] ?? '');

         $bound = $this->contracts()->bind($contract, $answer, $assets_dir);
         if (($bound['contract']['area'] ?? '') !== self::AREA) {
            throw new \InvalidArgumentException('Der Auftrag ist kein CMS-Auftrag.');
         }
         $manifest = (array)$bound['manifest'];
         $job = (array)$bound['job'];
         $this->validate_snapshot((array)$bound['contract']);
         $this->validate_recipe((array)$bound['contract'], $job);

         $validation = $this->validate_job($job, $assets_dir);
         $preview = $this->build_preview($job, $assets_dir, $manifest);

         $state = array(
            'area' => self::AREA,
            'status' => 'preview_ready',
            'percent' => 0,
            'step_percent' => 0,
            'message' => 'Bundle geprueft. Ausfuehrung starten.',
            'manifest' => $manifest,
            'contract' => $bound['contract'],
            'answer' => $answer,
            'context' => array(),
            'job' => $job,
            'assets_dir' => $assets_dir,
            'readme' => $readme,
            'validation' => $validation,
            'preview' => $preview,
            'step_pos' => 0,
            'step_results' => array(),
            'total' => count($job['steps'] ?? array()),
            'title' => (string)($manifest['title'] ?? 'KI-Bundle'),
            'recipe' => (string)($manifest['recipe'] ?? ''),
            'lng' => (string)($manifest['lng'] ?? dbxContentLng::current()),
            'return_run1' => $this->sanitize_return_run1((string)($_POST['return_run1'] ?? '')),
         );
         $this->set_job($token, $state);

         if ($is_browser_upload) {
            return $this->render_import_success($token, $state);
         }
         return $this->render_preview_page($token, $state);
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxKi', 'bundle_import', 'Import fehlgeschlagen', $e->getMessage());
         $back_run1 = $this->sanitize_return_run1((string)($_POST['return_run1'] ?? ''));
         $back_url = $back_run1 !== '' ? $this->module_url($back_run1) : $this->module_url('briefing');
         $tpl = dbx()->get_system_obj('dbxTPL');
         return $tpl->get_tpl('dbxKi|ki-bundle-import-error', $this->with_module_bar(array(
            'message' => $this->esc($e->getMessage()),
            'back_url' => $this->esc($back_url),
            'briefing_url' => $this->esc($this->module_url('briefing')),
         ), 'bundle'));
      }
   }

   private function read_import_payload(string $token): array {
      $file = $this->first_upload_file();
      if ($file) {
         return $this->extract_zip_upload($file, $token);
      }

      $raw_contract = trim((string)($_POST['contract_json'] ?? ''));
      $raw_answer = trim((string)($_POST['answer_json'] ?? ''));
      if ($raw_contract !== '' || $raw_answer !== '') {
         $contract = json_decode($raw_contract, true);
         $answer = json_decode($raw_answer, true);
         if (!is_array($contract) || !is_array($answer)) {
            throw new \InvalidArgumentException('contract_json und answer_json muessen gueltiges JSON sein.');
         }
         return array(
            'contract' => $contract,
            'answer' => $answer,
            'assets_dir' => '',
            'readme' => '',
         );
      }

      throw new \InvalidArgumentException('Bitte Antwort-ZIP oder contract_json plus answer_json senden.');
   }

   private function first_upload_file(): array {
      if (empty($_FILES) || !is_array($_FILES)) {
         return array();
      }
      foreach ($_FILES as $file) {
         if (!is_array($file)) {
            continue;
         }
         if (isset($file['tmp_name']) && !is_array($file['tmp_name'])) {
            return $file;
         }
      }
      return array();
   }

   private function extract_zip_upload(array $file, string $token): array {
      if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
         throw new \InvalidArgumentException('ZIP-Upload fehlgeschlagen.');
      }
      $tmp = (string)($file['tmp_name'] ?? '');
      if ($tmp === '' || !is_uploaded_file($tmp)) {
         throw new \InvalidArgumentException('ZIP-Upload ungueltig.');
      }
      if (!class_exists('ZipArchive')) {
         throw new \RuntimeException('ZipArchive ist auf dem Server nicht verfuegbar.');
      }

      $max_bytes = $this->config_int('max_bundle_bytes', 52428800);
      if ((int)($file['size'] ?? 0) > $max_bytes) {
         throw new \InvalidArgumentException('ZIP ueberschreitet max_bundle_bytes.');
      }

      $token = $this->sanitize_token($token);
      if ($token === '') {
         throw new \InvalidArgumentException('Interner Bundle-Token fehlt.');
      }
      $dest = $this->job_dir($token);
      if (is_dir($dest)) {
         $this->remove_job_dir($token);
      }
      @mkdir($dest, 0777, true);

      $zip = new \ZipArchive();
      if ($zip->open($tmp) !== true) {
         throw new \InvalidArgumentException('ZIP konnte nicht geoeffnet werden.');
      }

      $max_files = $this->config_int('max_bundle_files', 50);
      $total_uncompressed = 0;
      for ($i = 0; $i < $zip->numFiles; $i++) {
         $name = (string)$zip->getNameIndex($i);
         if ($name === '' || strpos($name, '..') !== false || $name[0] === '/') {
            $zip->close();
            throw new \InvalidArgumentException('Unzulaessiger ZIP-Pfad: ' . $name);
         }
         $stat = $zip->statIndex($i);
         $total_uncompressed += (int)($stat['size'] ?? 0);
         if ($total_uncompressed > $max_bytes) {
            $zip->close();
            throw new \InvalidArgumentException('Entpacktes Bundle zu gross.');
         }
      }
      if ($zip->numFiles > $max_files) {
         $zip->close();
         throw new \InvalidArgumentException('Zu viele Dateien im Bundle.');
      }

      if (!$zip->extractTo($dest)) {
         $zip->close();
         throw new \RuntimeException('ZIP konnte nicht entpackt werden.');
      }
      $zip->close();

      $root = $this->find_bundle_root($dest);
      $this->validate_response_tree($root);
      $contract = $this->read_json_file($root . '/auftrag.contract.json', true);
      $answer = $this->read_json_file($root . '/answer.json', true);
      $readme = is_file($root . '/README.md') ? (string)file_get_contents($root . '/README.md') : '';
      $assets_dir = is_dir($root . '/assets') ? $root . '/assets' : '';

      return array(
         'contract' => $contract,
         'answer' => $answer,
         'assets_dir' => $assets_dir,
         'readme' => $readme,
      );
   }

   private function find_bundle_root(string $dest): string {
      if (is_file($dest . '/auftrag.contract.json')) {
         return $dest;
      }
      foreach (glob($dest . '/*', GLOB_ONLYDIR) ?: array() as $dir) {
         if (is_file($dir . '/auftrag.contract.json')) {
            return $dir;
         }
      }
      throw new \InvalidArgumentException('auftrag.contract.json im Bundle nicht gefunden.');
   }

   private function validate_response_tree(string $root): void {
      $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
      foreach ($it as $item) {
         if (!$item->isFile()) continue;
         $relative = str_replace('\\', '/', substr($item->getPathname(), strlen(rtrim($root, '/\\')) + 1));
         if (in_array($relative, array('auftrag.contract.json', 'answer.json', 'README.md'), true)) continue;
         if (str_starts_with($relative, 'assets/')) continue;
         throw new \InvalidArgumentException('Nicht erlaubte Datei in Antwort-ZIP: ' . $relative);
      }
   }

   private function read_json_file(string $path, bool $required): array {
      if (!is_file($path)) {
         if ($required) {
            throw new \InvalidArgumentException('Pflichtdatei fehlt: ' . basename($path));
         }
         return array();
      }
      $raw = file_get_contents($path);
      $data = json_decode((string)$raw, true);
      if (!is_array($data)) {
         throw new \InvalidArgumentException('Ungueltiges JSON: ' . basename($path));
      }
      return $data;
   }

   private function validate_snapshot(array $contract): void {
      $snapshot = is_array($contract['snapshot'] ?? null) ? $contract['snapshot'] : array();
      $type = strtolower(trim((string)($snapshot['type'] ?? '')));
      if ($type === '') return;
      $lng = strtolower(trim((string)($snapshot['lng'] ?? '')));
      $id = (int)($snapshot['id'] ?? 0);
      if ($id <= 0) {
         throw new \InvalidArgumentException('Der Auftrags-Snapshot enthaelt kein gueltiges Ziel.');
      }
      if ($type === 'folder') {
         $current = $this->cms()->bundle_read('folder.get', array('lng' => $lng, 'id' => $id));
         $row = is_array($current['row'] ?? null) ? $current['row'] : array();
         $values = array();
         foreach ((array)($snapshot['fields'] ?? array()) as $field) $values[(string)$field] = $row[(string)$field] ?? null;
         if (($snapshot['fingerprint'] ?? '') !== '' && !hash_equals((string)$snapshot['fingerprint'], $this->contracts()->fingerprint($values))) {
            throw new \RuntimeException('Der Zielordner wurde seit dem Export veraendert. Bitte Auftrag neu exportieren.');
         }
         return;
      }
      if ($type !== 'page') {
         throw new \InvalidArgumentException('Unbekannter Auftrags-Snapshot: ' . $type);
      }
      $current = $this->cms()->bundle_read('page.get', array('lng' => $lng, 'id' => $id));
      $row = is_array($current['row'] ?? null) ? $current['row'] : array();
      $values = array();
      foreach ((array)($snapshot['fields'] ?? array()) as $field) {
         $values[(string)$field] = $row[(string)$field] ?? null;
      }
      $actual = $this->contracts()->fingerprint($values);
      if (!hash_equals((string)($snapshot['fingerprint'] ?? ''), $actual)) {
         throw new \RuntimeException('Die CMS-Seite wurde seit dem Export veraendert. Bitte einen neuen KI-Auftrag exportieren.');
      }
   }

   private function validate_recipe(array $contract, array $job): void {
      $recipe = strtolower(trim((string)($contract['recipe'] ?? '')));
      $allowed = array('page.create.v1', 'page.update.v1', 'translation.v1');
      if (!in_array($recipe, $allowed, true)) {
         throw new \InvalidArgumentException('Nicht unterstuetztes CMS-Rezept: ' . $recipe);
      }
      $manifest_recipe = strtolower(trim((string)($contract['metadata']['recipe'] ?? '')));
      if ($manifest_recipe !== $recipe) {
         throw new \InvalidArgumentException('Rezept und signierte Metadaten widersprechen sich.');
      }
      if (!is_array($job['steps'] ?? null) || !$job['steps']) {
         throw new \InvalidArgumentException('Der signierte Auftrag enthaelt keine Ausfuehrungsschritte.');
      }
   }

   private function validate_job(array $job, string $assets_dir): array {
      $steps = $job['steps'] ?? null;
      if (!is_array($steps) || !$steps) {
         throw new \InvalidArgumentException('Der intern gebundene Plan muss mindestens einen Schritt enthalten.');
      }

      $errors = array();
      $seen_ids = array();
      $step_results = array();
      $cms = $this->cms();

      foreach ($steps as $index => $step) {
         if (!is_array($step)) {
            $errors[] = 'Step ' . $index . ' ist kein Objekt.';
            continue;
         }
         $id = trim((string)($step['id'] ?? ''));
         if ($id === '') {
            $errors[] = 'Step ' . $index . ' ohne id.';
            continue;
         }
         if (isset($seen_ids[$id])) {
            $errors[] = 'Doppelte step id: ' . $id;
         }
         $seen_ids[$id] = true;

         $action = trim((string)($step['action'] ?? ''));
         if ($action === '') {
            $errors[] = 'Step ' . $id . ' ohne action.';
            continue;
         }
         if (!$cms->bundle_is_allowed_in_package($action)) {
            $errors[] = 'Step ' . $id . ': Aktion nicht erlaubt (' . $action . ').';
            continue;
         }

         $params = is_array($step['params'] ?? null) ? $step['params'] : array();
         try {
            $this->validate_ref_targets($params, array_keys($step_results));
            $resolved = $this->resolve_params($params, $step_results, $assets_dir, false);
            $cms->bundle_build_plan($action, $resolved);
            $step_results[$id] = $this->synthetic_step_result($action);
         } catch (\Throwable $e) {
            if ($this->params_contain_ref($params) && $this->is_preview_ref_dependency_error($e)) {
               $step_results[$id] = $this->synthetic_step_result($action);
               continue;
            }
            $errors[] = 'Step ' . $id . ' (' . $action . '): ' . $e->getMessage();
         }
      }

      if ($errors) {
         throw new \InvalidArgumentException(implode(' ', $errors));
      }

      return array(
         'ok' => 1,
         'step_count' => count($steps),
         'warnings' => array(),
      );
   }

   private function build_preview(array $job, string $assets_dir, array $manifest): array {
      $lines = array();
      $title = trim((string)($manifest['title'] ?? ''));
      if ($title !== '') {
         $lines[] = 'Titel: ' . $title;
      }
      $recipe = trim((string)($manifest['recipe'] ?? ''));
      if ($recipe !== '') {
         $lines[] = 'Rezept: ' . $recipe;
      }
      $lng = trim((string)($manifest['lng'] ?? ''));
      if ($lng !== '') {
         $lines[] = 'Sprache: ' . $lng;
      }

      foreach ($job['steps'] ?? array() as $step) {
         if (!is_array($step)) {
            continue;
         }
         $lines[] = '- ' . ($step['id'] ?? '?') . ': ' . ($step['action'] ?? '?');
      }

      if ($assets_dir !== '' && is_dir($assets_dir)) {
         $count = count(glob(rtrim($assets_dir, '/\\') . '/*') ?: array());
         $lines[] = 'Assets: ' . $count . ' Datei(en)';
      }

      return array(
         'lines' => $lines,
         'step_count' => count($job['steps'] ?? array()),
      );
   }

   /**
    * Kompaktes Erfolgsfragment fuer den Upload-Request selbst: das eigentliche
    * Vorschaufenster wird per kiResultWindow.js automatisch in einem
    * openWin-Fenster geoeffnet (URL = derselbe Endpunkt mit ?token=..., siehe
    * handleImport()-Kopf), statt die Vorschau inline auf der Seite zu zeigen.
    */
   private function render_import_success(string $token, array $state): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $title = trim((string)($state['title'] ?? 'KI-Bundle'));
      return $tpl->get_tpl('dbxKi|ki-bundle-import-success', array(
         'message' => 'Bundle geprueft. Vorschau wird geoeffnet ...',
         'preview_url' => $this->esc($this->module_url('bundle_import', array('token' => $token))),
         'window_title' => $this->esc($title !== '' ? $title : 'KI-Bundle-Vorschau'),
      ));
   }

   /**
    * Verwirft einen geprueften, aber noch nicht ausgefuehrten Bundle-Job.
    * Von "Verwerfen" im Vorschaufenster per AJAX aufgerufen (kiResultWindow.js
    * schliesst das Fenster danach clientseitig).
    */
   public function handle_discard(): void {
      $token = $this->sanitize_token((string)dbx()->get_request_var('token', '', '*'));
      if ($token !== '') {
         $this->remove_job_dir($token);
         dbxKiSessionState::remove(self::SESSION_KEY, $token);
      }
      header('Content-Type: text/plain; charset=utf-8');
      echo 'ok';
      exit;
   }

   private function render_preview_page(string $token, array $state): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $warnings = is_array($state['validation']['warnings'] ?? null) ? $state['validation']['warnings'] : array();
      $lines = is_array($state['preview']['lines'] ?? null) ? $state['preview']['lines'] : array();

      $warning_html = '<li class="text-muted">Keine</li>';
      foreach ($warnings as $warning) {
         $warning_html = '';
         break;
      }
      if ($warning_html === '') {
         foreach ($warnings as $warning) {
            $warning_html .= '<li>' . $this->esc($warning) . '</li>';
         }
      }

      $line_html = '';
      foreach ($lines as $line) {
         $line_html .= '<li class="list-group-item py-1 px-2">' . $this->esc($line) . '</li>';
      }

      $warning_block = '';
      if ($warning_html !== '<li class="text-muted">Keine</li>') {
         $warning_block = '<div class="small text-warning mb-2"><strong>Hinweise</strong><ul class="mb-0 ps-3">'
            . $warning_html . '</ul></div>';
      }

      $readme = trim((string)($state['readme'] ?? ''));
      $readme_html = '';
      if ($readme !== '') {
         $readme_html = '<details class="small mt-2"><summary class="text-muted">README der KI-ZIP</summary>'
            . '<pre class="small bg-light p-2 rounded mt-1 mb-0" style="max-height:8rem;overflow:auto">'
            . $this->esc($readme) . '</pre></details>';
      }

      $back_url = $this->return_url_from_state($state);
      $start_url = $this->module_url('bundle_process', array(
         'proc_key' => $token,
         'reset' => 1,
         'proc_cmd' => 'start',
         'token' => $this->cms()->bundle_execute_token(),
      ));
      $footer_actions = $this->build_import_footer_actions($state, true, $start_url, $back_url, $token);
      $bar_title = trim((string)($state['title'] ?? ''));

      return $tpl->get_tpl('dbxKi|ki-bundle-preview', $this->with_module_bar(array(
         'subtitle' => $this->esc((string)($state['recipe'] ?? '')),
         'step_count' => (int)($state['total'] ?? 0),
         'preview_list' => $line_html,
         'warning_block' => $warning_block,
         'readme_block' => $readme_html,
         'footer_actions' => $footer_actions,
      ), 'bundle_preview', '', $bar_title));
   }

   private function cms_admin_page_url(int $cid, string $lng = ''): string {
      $url = '?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=' . (int)$cid;
      $lng = strtolower(trim($lng));
      if ($lng !== '') {
         $url .= '&dbx_lng=' . rawurlencode($lng);
      }
      return $url;
   }

   private function resolve_content_page_ref(array $state): array {
      $lng = strtolower(trim((string)($state['lng'] ?? '')));
      $step_results = is_array($state['step_results'] ?? null) ? $state['step_results'] : array();
      foreach ($step_results as $result) {
         if (!is_array($result)) {
            continue;
         }
         $cid = (int)($result['page_id'] ?? 0);
         if ($cid > 0) {
            $result_lng = strtolower(trim((string)($result['lng'] ?? '')));
            return array(
               'cid' => $cid,
               'lng' => $result_lng !== '' ? $result_lng : $lng,
            );
         }
      }

      $job = is_array($state['job'] ?? null) ? $state['job'] : array();
      foreach ($job['steps'] ?? array() as $step) {
         if (!is_array($step)) {
            continue;
         }
         $action = (string)($step['action'] ?? '');
         $params = is_array($step['params'] ?? null) ? $step['params'] : array();
         if ($action === 'page.update') {
            $cid = (int)($params['id'] ?? 0);
            if ($cid > 0) {
               $step_lng = strtolower(trim((string)($params['lng'] ?? '')));
               return array('cid' => $cid, 'lng' => $step_lng !== '' ? $step_lng : $lng);
            }
         }
      }
      return array('cid' => 0, 'lng' => $lng);
   }

   private function build_import_footer_actions(array $state, bool $show_execute, string $execute_url, string $back_url, string $token = ''): string {
      $html = '';
      if ($show_execute && $execute_url !== '') {
         $html .= '<button type="button" class="btn btn-primary btn-sm" data-dbx-ki-inline-action="' . $this->esc($execute_url)
            . '"><i class="bi bi-play-fill"></i> Ausfuehren</button>';
      }
      if ($token !== '') {
         $html .= '<button type="button" class="btn btn-outline-danger btn-sm" data-dbx-ki-discard="'
            . $this->esc($this->module_url('bundle_discard', array('token' => $token)))
            . '" data-confirm="Bundle wirklich verwerfen? Die Aenderungen werden nicht uebernommen.">'
            . '<i class="bi bi-x-lg"></i> Verwerfen</button>';
      }

      $page_ref = $this->resolve_content_page_ref($state);
      if ((int)($page_ref['cid'] ?? 0) > 0) {
         $html .= '<a class="btn btn-success btn-sm" href="' . $this->esc($this->cms_admin_page_url((int)$page_ref['cid'], (string)($page_ref['lng'] ?? '')))
            . '"><i class="bi bi-pencil-square"></i> Seite im CMS</a>';
      }

      $html .= '<a class="btn btn-outline-primary btn-sm" href="' . $this->esc($this->module_url('briefing'))
         . '"><i class="bi bi-plus-lg"></i> Neuer KI-Auftrag</a>';
      $html .= '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($back_url)
         . '"><i class="bi bi-arrow-left"></i> Zurueck</a>';
      return $html;
   }

   private function sanitize_return_run1(string $run1): string {
      $allowed = array(
         'briefing',
         'briefing_page_create',
         'briefing_page_update',
         'briefing_page_translate',
         'bundle',
      );
      $run1 = strtolower(trim($run1));
      return in_array($run1, $allowed, true) ? $run1 : 'bundle';
   }

   private function return_url_from_state(array $state): string {
      $run1 = trim((string)($state['return_run1'] ?? ''));
      if ($run1 === '') {
         return $this->module_url('bundle');
      }
      return $this->module_url($this->sanitize_return_run1($run1));
   }

   public function handle_process(): string {
      $token = $this->sanitize_token((string)($_GET['proc_key'] ?? ''));
      if ($token === '') {
         $token = $this->new_token();
      }

      $cmd = strtolower(preg_replace('/[^a-z_]+/', '', (string)($_GET['proc_cmd'] ?? '')));
      $reset = (int)($_GET['reset'] ?? 0);
      $state = $this->get_job($token);

      if (!$state) {
         return '<div class="container py-4"><div class="alert alert-warning">Bundle-Prozess nicht gefunden.</div>'
            . '<p><a class="btn btn-secondary" href="' . $this->esc($this->module_url('bundle')) . '">Zurueck</a></p></div>';
      }

      if ($reset || $cmd === 'restart') {
         try {
            if ($cmd === 'start' || $reset || $cmd === 'restart') {
               $this->authorize_bundle_execute();
            }
         } catch (\Throwable $e) {
            $state['status'] = 'error';
            $state['message'] = $e->getMessage();
            $this->set_job($token, $state);
            $next = $this->module_url('bundle_process', array('proc_key' => $token));
            return $this->render_process($state, $next);
         }
         $state['status'] = 'running';
         $state['step_pos'] = 0;
         $state['step_results'] = array();
         $state['percent'] = 0;
         $state['step_percent'] = 0;
         $state['message'] = 'Bundle-Ausfuehrung gestartet.';
      } elseif ($cmd === 'start' && ($state['status'] ?? '') === 'preview_ready') {
         try {
            $this->authorize_bundle_execute();
         } catch (\Throwable $e) {
            $state['status'] = 'error';
            $state['message'] = $e->getMessage();
            $this->set_job($token, $state);
            $next = $this->module_url('bundle_process', array('proc_key' => $token));
            return $this->render_process($state, $next);
         }
         $state['status'] = 'running';
         $state['step_pos'] = 0;
         $state['step_results'] = array();
         $state['percent'] = 0;
         $state['step_percent'] = 0;
         $state['message'] = 'Bundle-Ausfuehrung gestartet.';
      }

      if ($cmd === 'pause' && ($state['status'] ?? '') === 'running') {
         $state['status'] = 'paused';
         $state['message'] = 'Bundle angehalten.';
      } elseif ($cmd === 'resume' && ($state['status'] ?? '') === 'paused') {
         $state['status'] = 'running';
         $state['message'] = 'Bundle fortgesetzt.';
      } elseif ($cmd === 'cancel') {
         $state['status'] = 'canceled';
         $state['message'] = 'Bundle abgebrochen.';
      }

      if (($state['status'] ?? '') === 'running') {
         $this->execute_atomically($state);
      }

      $this->set_job($token, $state);
      $next = $this->module_url('bundle_process', array('proc_key' => $token));
      return $this->render_process($state, $next);
   }

   private function authorize_bundle_execute(): void {
      if ((int)dbx()->get_cfg('dbxKi', 'allow_execute', 1) !== 1) {
         throw new \RuntimeException('Automatische Ausfuehrung ist deaktiviert (allow_execute).');
      }
      $token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
      if (!$this->cms()->bundle_check_execute_token($token)) {
         throw new \RuntimeException('Ungueltiges oder abgelaufenes Ausfuehrungs-Token.');
      }
   }

   private function execute_atomically(array &$state): void {
      $db = dbx()->get_system_obj('dbxDB');
      $dds = $this->transaction_dds((array)($state['job'] ?? array()));
      $started = array();
      try {
         foreach ($dds as $dd) {
            if ($db->begin($dd) !== 1) {
               throw new \RuntimeException('Transaktion konnte fuer ' . $dd . ' nicht gestartet werden.');
            }
            $started[] = $dd;
         }
         $guard = 0;
         while (($state['status'] ?? '') === 'running') {
            $this->tick_job($state);
            if (++$guard > 1000) {
               $state['status'] = 'error';
               $state['message'] = 'Bundle-Abbruch: ungueltige Schrittfolge.';
            }
         }
         if (($state['status'] ?? '') !== 'finished') {
            throw new \RuntimeException((string)($state['message'] ?? 'Bundle-Ausfuehrung fehlgeschlagen.'));
         }
         $state['verified'] = count((array)($state['step_results'] ?? array())) === count((array)($state['job']['steps'] ?? array()));
         if (!$state['verified']) {
            throw new \RuntimeException('Nachpruefung der Bundle-Ergebnisse fehlgeschlagen.');
         }
         foreach ($started as $dd) {
            if ($db->commit($dd) !== 1) {
               throw new \RuntimeException('Transaktion konnte nicht abgeschlossen werden.');
            }
         }
         $this->contracts()->consume((array)($state['contract'] ?? array()));
         $this->discard_file_backups((array)($state['file_backups'] ?? array()));
         $state['file_backups'] = array();
      } catch (\Throwable $e) {
         foreach (array_reverse($started) as $dd) {
            $db->rollback($dd);
         }
         $this->remove_created_media_files((array)($state['step_results'] ?? array()));
         $this->restore_file_backups((array)($state['file_backups'] ?? array()));
         $state['file_backups'] = array();
         $state['status'] = 'error';
         $state['rolled_back'] = true;
         $state['message'] = 'Bundle vollstaendig zurueckgerollt: ' . $e->getMessage();
      }
   }

   private function transaction_dds(array $job): array {
      $dds = array('dbxMedia', 'dbxMediaUsage');
      foreach ((array)($job['steps'] ?? array()) as $step) {
         $params = is_array($step['params'] ?? null) ? $step['params'] : array();
         foreach (array('lng', 'source_lng', 'target_lng') as $key) {
            $lng = strtolower(trim((string)($params[$key] ?? '')));
            if ($lng !== '') {
               $dds[] = dbxContentLng::dd_content($lng);
               $dds[] = dbxContentLng::dd_folder($lng);
            }
         }
      }
      return array_values(array_unique($dds));
   }

   private function remove_created_media_files(array $results): void {
      $root = rtrim(str_replace('\\', '/', dbx()->get_file_dir()), '/') . '/';
      foreach ($results as $result) {
         $relative = str_replace('\\', '/', (string)($result['row']['file_path'] ?? $result['media']['file_path'] ?? ''));
         if ($relative === '' || str_contains($relative, '..')) continue;
         $file = dbx()->os_path($root . ltrim($relative, '/'));
         $normalized = str_replace('\\', '/', $file);
         if (str_starts_with($normalized, $root) && is_file($file)) {
            @unlink($file);
         }
      }
   }

   private function capture_file_backups(array $plan, array &$state): void {
      $target = (string)($plan['target_file'] ?? '');
      if ($target === '' || !is_file($target)) return;
      $key = str_replace('\\', '/', $target);
      if (isset($state['file_backups'][$key])) return;
      $backup = tempnam(sys_get_temp_dir(), 'dbxki-file-rollback-');
      if ($backup === false || !copy($target, $backup)) {
         throw new \RuntimeException('Dateisicherung fuer Rollback fehlgeschlagen.');
      }
      $state['file_backups'][$key] = $backup;
   }

   private function restore_file_backups(array $backups): void {
      foreach ($backups as $target => $backup) {
         if (is_file((string)$backup)) {
            @copy((string)$backup, dbx()->os_path((string)$target));
            @unlink((string)$backup);
         }
      }
   }

   private function discard_file_backups(array $backups): void {
      foreach ($backups as $backup) if (is_file((string)$backup)) @unlink((string)$backup);
   }

   private function tick_job(array &$state): void {
      $steps = is_array($state['job']['steps'] ?? null) ? $state['job']['steps'] : array();
      $total = count($steps);
      $pos = (int)($state['step_pos'] ?? 0);
      if ($total <= 0) {
         $state['status'] = 'error';
         $state['message'] = 'Keine Steps im Bundle.';
         return;
      }
      if ($pos >= $total) {
         $state['status'] = 'finished';
         $state['percent'] = 100;
         $state['step_percent'] = 100;
         $state['message'] = $this->finished_message($state);
         return;
      }

      $step = $steps[$pos];
      $step_id = (string)($step['id'] ?? ('step_' . $pos));
      $action = (string)($step['action'] ?? '');
      $assets_dir = (string)($state['assets_dir'] ?? '');
      $step_results = is_array($state['step_results'] ?? null) ? $state['step_results'] : array();

      try {
         $params = is_array($step['params'] ?? null) ? $step['params'] : array();
         $resolved = $this->resolve_params($params, $step_results, $assets_dir, false);
         $plan = $this->cms()->bundle_build_plan($action, $resolved);
         $this->capture_file_backups($plan, $state);
         $result = $this->cms()->bundle_execute_plan($action, $resolved, $plan);
         $step_results[$step_id] = $this->normalize_step_result($action, $result);
         $state['step_results'] = $step_results;
         $state['step_pos'] = $pos + 1;
         $state['message'] = 'Step ' . $step_id . ' (' . $action . ') ausgefuehrt.';
      } catch (\Throwable $e) {
         $state['status'] = 'error';
         $state['message'] = 'Fehler in Step ' . $step_id . ': ' . $e->getMessage();
         dbx()->sys_msg('error', 'dbxKi', 'bundle_process', $step_id, $e->getMessage());
         return;
      }

      $done = (int)$state['step_pos'];
      $state['percent'] = (int)floor(($done / $total) * 100);
      $state['step_percent'] = 100;
      if ($done >= $total) {
         $state['status'] = 'finished';
         $state['percent'] = 100;
         $state['message'] = $this->finished_message($state);
      }
   }

   private function finished_message(array $state): string {
      $parts = array('Bundle abgeschlossen.');
      foreach (is_array($state['step_results'] ?? null) ? $state['step_results'] : array() as $id => $result) {
         if (!empty($result['page_id'])) {
            $parts[] = 'Seite #' . (int)$result['page_id'];
         }
         if (!empty($result['id']) && empty($result['page_id']) && empty($result['media_id'])) {
            $parts[] = $id . ' id=' . (int)$result['id'];
         }
      }
      return implode(' ', $parts);
   }

   private function normalize_step_result(string $action, array $result): array {
      $out = $result;
      if (!empty($result['id'])) {
         $out['id'] = (int)$result['id'];
      }
      if (strpos($action, 'page.') === 0) {
         $out['page_id'] = (int)($result['id'] ?? 0);
      }
      if (strpos($action, 'media.create') === 0) {
         $out['media_id'] = (int)($result['id'] ?? 0);
      }
      if ($action === 'media.assign' && !empty($result['usage_id'])) {
         $out['usage_id'] = (int)$result['usage_id'];
      }
      if ($action === 'translation.apply') {
         $out['page_id'] = (int)($result['id'] ?? 0);
         if (!empty($result['lng'])) {
            $out['lng'] = (string)$result['lng'];
         }
      }
      if (strpos($action, 'folder.') === 0 && !empty($result['id'])) {
         $out['folder_id'] = (int)$result['id'];
      }
      return $out;
   }

   private function resolve_params($value, array $step_results, string $assets_dir, bool $dry_run) {
      if (is_array($value)) {
         $out = array();
         foreach ($value as $key => $item) {
            $out[$key] = $this->resolve_params($item, $step_results, $assets_dir, $dry_run);
         }
         if (isset($out['asset_ref']) && $assets_dir !== '') {
            $out = $this->apply_asset_ref($out, $assets_dir, $dry_run);
         }
         return $out;
      }

      if (!is_string($value)) {
         return $value;
      }

      if (strpos($value, '$ref:') === 0) {
         if ($dry_run) {
            return 0;
         }
         return $this->resolve_ref($value, $step_results);
      }

      if (strpos($value, '$ref:') !== false) {
         if ($dry_run) {
            return $value;
         }
         return preg_replace_callback('/\$ref:([A-Za-z0-9_.-]+)/', function($match) use ($step_results) {
            return (string)$this->resolve_ref('$ref:' . $match[1], $step_results);
         }, $value);
      }

      return $value;
   }

   private function params_contain_ref($value): bool {
      if (is_array($value)) {
         foreach ($value as $item) {
            if ($this->params_contain_ref($item)) {
               return true;
            }
         }
         return false;
      }
      return is_string($value) && strpos($value, '$ref:') !== false;
   }

   private function validate_ref_targets($value, array $completed_step_ids): void {
      if (is_array($value)) {
         foreach ($value as $item) {
            $this->validate_ref_targets($item, $completed_step_ids);
         }
         return;
      }
      if (!is_string($value) || strpos($value, '$ref:') !== 0) {
         return;
      }
      $raw = substr($value, 5);
      $parts = explode('.', $raw, 2);
      if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
         throw new \InvalidArgumentException('Ungueltige Referenz: ' . $value);
      }
      if (!in_array($parts[0], $completed_step_ids, true)) {
         throw new \InvalidArgumentException('Referenz auf noch nicht ausgefuehrten Step: ' . $value);
      }
   }

   private function is_preview_ref_dependency_error(\Throwable $e): bool {
      $msg = $e->getMessage();
      return strpos($msg, 'größer als 0') !== false
         || strpos($msg, 'groesser als 0') !== false
         || strpos($msg, 'nicht gefunden') !== false;
   }

   private function synthetic_step_result(string $action): array {
      if (strpos($action, 'media.create') === 0) {
         return array('id' => 900001, 'media_id' => 900001);
      }
      if ($action === 'page.create' || $action === 'page.update') {
         return array('id' => 900002, 'page_id' => 900002);
      }
      if ($action === 'media.assign') {
         return array('usage_id' => 900003);
      }
      if (strpos($action, 'folder.create') === 0) {
         return array('id' => 900004, 'folder_id' => 900004);
      }
      return array('id' => 900000);
   }

   private function resolve_ref(string $value, array $step_results) {
      $raw = substr($value, 5);
      $parts = explode('.', $raw, 2);
      if (count($parts) !== 2) {
         throw new \InvalidArgumentException('Ungueltige Referenz: ' . $value);
      }
      $step_id = $parts[0];
      $field = $parts[1];
      if (!isset($step_results[$step_id]) || !is_array($step_results[$step_id])) {
         throw new \RuntimeException('Referenz nicht aufloesbar: ' . $value);
      }
      if (!array_key_exists($field, $step_results[$step_id])) {
         throw new \RuntimeException('Feld in Referenz fehlt: ' . $value);
      }
      return $step_results[$step_id][$field];
   }

   private function apply_asset_ref(array $params, string $assets_dir, bool $dry_run): array {
      $ref = ltrim(str_replace('\\', '/', (string)($params['asset_ref'] ?? '')), '/');
      if (strpos($ref, 'assets/') === 0) {
         $ref = substr($ref, 7);
      }
      if ($ref === '' || strpos($ref, '..') !== false) {
         throw new \InvalidArgumentException('Ungueltiger asset_ref.');
      }
      $path = rtrim(str_replace('\\', '/', $assets_dir), '/') . '/' . $ref;
      $real_assets = realpath($assets_dir);
      $real_file = realpath($path);
      if (!$real_assets || !$real_file || strpos($real_file, $real_assets) !== 0 || !is_file($real_file)) {
         throw new \InvalidArgumentException('Asset nicht gefunden: ' . $ref);
      }
      if ($dry_run) {
         $params['data_base64'] = base64_encode('dry-run');
      } else {
         $bytes = file_get_contents($real_file);
         if ($bytes === false) {
            throw new \RuntimeException('Asset konnte nicht gelesen werden: ' . $ref);
         }
         $params['data_base64'] = base64_encode($bytes);
      }
      unset($params['asset_ref']);
      if (empty($params['file_name'])) {
         $params['file_name'] = basename($real_file);
      }
      return $params;
   }

   private function render_process(array $state, string $next_url): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $status = strtolower((string)($state['status'] ?? 'running'));
      $percent = max(0, min(100, (int)($state['percent'] ?? 0)));
      $step_percent = max(0, min(100, (int)($state['step_percent'] ?? 0)));
      $token = (string)($state['proc_key'] ?? '');

      $status_labels = array(
         'preview_ready' => 'Bereit',
         'running' => 'Laeuft',
         'paused' => 'Pausiert',
         'finished' => 'Fertig',
         'error' => 'Fehler',
         'canceled' => 'Abgebrochen',
      );
      $status_classes = array(
         'running' => 'text-bg-primary',
         'paused' => 'text-bg-warning',
         'finished' => 'text-bg-success',
         'error' => 'text-bg-danger',
         'canceled' => 'text-bg-secondary',
         'preview_ready' => 'text-bg-info',
      );
      $status_icons = array(
         'running' => 'bi bi-arrow-repeat',
         'paused' => 'bi bi-pause-fill',
         'finished' => 'bi bi-check-lg',
         'error' => 'bi bi-exclamation-triangle',
         'canceled' => 'bi bi-x-lg',
         'preview_ready' => 'bi bi-hourglass',
      );

      $append = function(string $url, array $params): string {
         $params = array_filter($params, static fn($value): bool => $value !== null && $value !== '');
         return $this->append_url_params($url, $params);
      };

      $target_id = 'dbx_ki_bundle_' . substr(md5($token), 0, 12);
      $autostart = ($status === 'running') ? 1 : 0;
      $token_param = $this->cms()->bundle_execute_token();
      $back_url = $this->return_url_from_state($state);
      $finished_actions = '';
      if ($status === 'finished') {
         $finished_actions = $this->build_import_footer_actions($state, false, '', $back_url);
      }

      $bar_title = trim((string)($state['title'] ?? ''));

      return $tpl->get_tpl('dbxKi|ki-bundle-process', $this->with_module_bar(array(
         'target_id' => $this->esc($target_id),
         'status_key' => $this->esc($status),
         'status_label' => $this->esc($status_labels[$status] ?? $status),
         'status_class' => $this->esc($status_classes[$status] ?? 'text-bg-secondary'),
         'status_icon' => $this->esc($status_icons[$status] ?? 'bi bi-circle'),
         'message' => $this->esc((string)($state['message'] ?? '')),
         'percent' => $percent,
         'step_percent' => $step_percent,
         'step_label' => $this->esc((int)($state['step_pos'] ?? 0) . ' / ' . (int)($state['total'] ?? 0)),
         'updated_at' => $this->esc((string)($state['updated_at'] ?? '')),
         'next_url' => $this->esc($next_url),
         'pause_url' => $this->esc($append($next_url, array('proc_cmd' => 'pause', 'token' => $token_param))),
         'resume_url' => $this->esc($append($next_url, array('proc_cmd' => 'resume', 'token' => $token_param))),
         'cancel_url' => $this->esc($append($next_url, array('proc_cmd' => 'cancel', 'token' => $token_param))),
         'restart_url' => $this->esc($append($next_url, array('reset' => 1, 'proc_cmd' => 'restart', 'token' => $token_param))),
         'back_url' => $this->esc($back_url),
         'autostart' => $autostart,
         'interval' => 900,
         'pause_visible' => 'running',
         'resume_visible' => 'paused',
         'restart_visible' => 'paused,canceled,error,finished',
         'cancel_visible' => 'running,paused',
         'result_html' => $this->render_result_html($state),
         'finished_actions' => $finished_actions,
      ), 'bundle_process', '', $bar_title));
   }

   private function render_result_html(array $state): string {
      if (($state['status'] ?? '') !== 'finished') {
         return '';
      }
      $items = '';
      foreach (is_array($state['step_results'] ?? null) ? $state['step_results'] : array() as $id => $result) {
         if (!is_array($result)) {
            continue;
         }
         $bits = array($this->esc($id));
         foreach (array('page_id', 'media_id', 'folder_id', 'usage_id', 'id') as $key) {
            if (!empty($result[$key])) {
               $bits[] = $key . '=' . (int)$result[$key];
            }
         }
         $items .= '<li class="list-group-item py-1 px-2 small">' . implode(' ', $bits) . '</li>';
      }
      if ($items === '') {
         return '';
      }
      return '<ul class="list-group list-group-flush small mb-0">' . $items . '</ul>';
   }

   public function handle_export(): void {
      // Freie Beispiel-Jobs sind in Vertragsversion 2 entfallen. Jeder
      // Auftrag wird mit konkretem Ziel und Snapshot im Briefing signiert.
      header('Location: ' . $this->module_url('briefing'));
      exit;
   }
   public function handle_describe_json(): void {
      dbx()->json_response($this->describe_bundle(), true);
   }
}

?>
