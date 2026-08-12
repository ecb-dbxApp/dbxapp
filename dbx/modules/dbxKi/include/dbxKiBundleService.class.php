<?php
namespace dbx\dbxKi;

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

   private function withModuleBar(array $data, string $screen, string $actionsHtml = '', string $barTitle = ''): array {
      return array_merge($data, $this->help()->moduleBarTemplateData($screen, $actionsHtml, $barTitle));
   }

   private function moduleUrl(string $run1, array $params = array()): string {
      $url = '?dbx_modul=dbxKi&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         if ($value === null || $value === '') {
            continue;
         }
         $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }
      return $url;
   }

   private function esc($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function configInt(string $key, int $default): int {
      return max(1, (int)dbx()->get_cfg('dbxKi', $key, $default));
   }

   private function sessionBucket(): array {
      if (!isset($_SESSION['dbx']['dbxKi']) || !is_array($_SESSION['dbx']['dbxKi'])) {
         $_SESSION['dbx']['dbxKi'] = array();
      }
      if (!isset($_SESSION['dbx']['dbxKi'][self::SESSION_KEY]) || !is_array($_SESSION['dbx']['dbxKi'][self::SESSION_KEY])) {
         $_SESSION['dbx']['dbxKi'][self::SESSION_KEY] = array();
      }
      return $_SESSION['dbx']['dbxKi'][self::SESSION_KEY];
   }

   private function getJob(string $token): array {
      $token = $this->sanitizeToken($token);
      if ($token === '') {
         return array();
      }
      $bucket = $this->sessionBucket();
      return is_array($bucket[$token] ?? null) ? $bucket[$token] : array();
   }

   private function setJob(string $token, array $state): array {
      $token = $this->sanitizeToken($token);
      if ($token === '') {
         return array();
      }
      $state['proc_key'] = $token;
      $state['updated_at'] = date('Y-m-d H:i:s');
      $_SESSION['dbx']['dbxKi'][self::SESSION_KEY][$token] = $state;
      return $state;
   }

   private function sanitizeToken(string $token): string {
      return preg_replace('/[^A-Za-z0-9_-]+/', '', (string)$token);
   }

   private function newToken(): string {
      return substr(md5(session_id() . microtime(true) . mt_rand()), 0, 16);
   }

   private function tempRoot(): string {
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/tmp/ki-bundle';
      $dir = dbx()->os_path($dir);
      if (!is_dir($dir)) {
         @mkdir($dir, 0777, true);
      }
      return $dir;
   }

   private function jobDir(string $token): string {
      return rtrim($this->tempRoot(), '/\\') . DIRECTORY_SEPARATOR . $this->sanitizeToken($token);
   }

   private function removeJobDir(string $token): void {
      $dir = $this->jobDir($token);
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

   public function describeBundle(): array {
      $cms = $this->cms();
      return array(
         'ok' => 1,
         'bundle_version' => self::BUNDLE_VERSION,
         'api_version' => '0.1',
         'area' => self::AREA,
         'module' => 'dbxKi',
         'purpose' => 'Offline KI-Bundles fuer CMS-Aenderungen ohne direkten API-Zugriff.',
         'upload_url' => $this->moduleUrl('bundle_import'),
         'export_url' => $this->moduleUrl('bundle_export'),
         'allowed_actions' => $this->allowedActions(),
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
         'cms' => $cms->bundleSystemDescribe(),
      );
   }

   private function allowedActions(): array {
      $out = array();
      foreach ($this->cms()->bundleActionCatalog() as $action => $meta) {
         if ($this->cms()->bundleIsAllowedInPackage($action)) {
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
   private function importForm(string $template, string $returnRun1 = '') {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('ki-bundle-import', $template);
      $form->_action = $this->moduleUrl('bundle_import');
      // Nicht das gesamte Array ersetzen: dbxForm hat dort bereits sein
      // Security-Token abgelegt.
      $form->_data['return_run1'] = $this->sanitizeReturnRun1($returnRun1);
      $form->_data['bundle_zip'] = '';
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
   public function renderImportPanel(string $returnRun1): string {
      return $this->importForm('ki-briefing-import-panel', $returnRun1)->run();
   }

   /**
    * Rendert die Bundle-Startseite mit einem vollwertigen dbxForm-Upload.
    */
   public function renderStartPage(): string {
      $form = $this->importForm('ki-bundle-start');
      foreach ($this->withModuleBar(array(
         'api_url' => $this->esc($this->moduleUrl('api')),
         'describe_url' => $this->esc($this->moduleUrl('bundle_describe')),
         'export_url' => $this->esc($this->moduleUrl('bundle_export')),
         'bundle_version' => $this->esc(self::BUNDLE_VERSION),
         'execute_token' => $this->esc($this->cms()->bundleExecuteToken()),
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
   public function handleImport(): string {
      // Ergebnis-Fenster: derselbe Endpunkt liefert bei einem reinen GET mit
      // bekanntem Token (kein neuer Upload) direkt die bereits gespeicherte
      // Vorschau erneut aus - dadurch ist die Vorschau per URL adressierbar
      // und laesst sich in ein openWin-Fenster laden (siehe handleImport()
      // Erfolgszweig unten und kiResultWindow.js).
      $existingToken = $this->sanitizeToken((string)dbx()->get_request_var('token', '', '*'));
      if ($existingToken !== '' && $this->firstUploadFile() === array() && !isset($_POST['return_run1'])) {
         $state = $this->getJob($existingToken);
         if ($state !== array()) {
            return $this->renderPreviewPage($existingToken, $state);
         }
      }

      $isBrowserUpload = $this->firstUploadFile() !== array() || isset($_POST['return_run1']);
      try {
         if ($isBrowserUpload) {
            $importForm = $this->importForm(
               'ki-briefing-import-panel',
               (string)($_POST['return_run1'] ?? '')
            );
            if (!$importForm->submit()) {
               throw new \RuntimeException('Ungueltiger oder abgelaufener Formular-Token.');
            }
         }
         $token = $this->newToken();
         $payload = $this->readImportPayload($token);
         $contract = is_array($payload['contract'] ?? null) ? $payload['contract'] : array();
         $answer = is_array($payload['answer'] ?? null) ? $payload['answer'] : array();
         $assetsDir = (string)($payload['assets_dir'] ?? '');
         $readme = (string)($payload['readme'] ?? '');

         $bound = $this->contracts()->bind($contract, $answer, $assetsDir);
         if (($bound['contract']['area'] ?? '') !== self::AREA) {
            throw new \InvalidArgumentException('Der Auftrag ist kein CMS-Auftrag.');
         }
         $manifest = (array)$bound['manifest'];
         $job = (array)$bound['job'];
         $this->validateSnapshot((array)$bound['contract']);
         $this->validateRecipe((array)$bound['contract'], $job);

         $validation = $this->validateJob($job, $assetsDir);
         $preview = $this->buildPreview($job, $assetsDir, $manifest);

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
            'assets_dir' => $assetsDir,
            'readme' => $readme,
            'validation' => $validation,
            'preview' => $preview,
            'step_pos' => 0,
            'step_results' => array(),
            'total' => count($job['steps'] ?? array()),
            'title' => (string)($manifest['title'] ?? 'KI-Bundle'),
            'recipe' => (string)($manifest['recipe'] ?? ''),
            'lng' => (string)($manifest['lng'] ?? dbxContentLng::current()),
            'return_run1' => $this->sanitizeReturnRun1((string)($_POST['return_run1'] ?? '')),
         );
         $this->setJob($token, $state);

         if ($isBrowserUpload) {
            return $this->renderImportSuccess($token, $state);
         }
         return $this->renderPreviewPage($token, $state);
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxKi', 'bundle_import', 'Import fehlgeschlagen', $e->getMessage());
         $backRun1 = $this->sanitizeReturnRun1((string)($_POST['return_run1'] ?? ''));
         $backUrl = $backRun1 !== '' ? $this->moduleUrl($backRun1) : $this->moduleUrl('briefing');
         $tpl = dbx()->get_system_obj('dbxTPL');
         return $tpl->get_tpl('dbxKi|ki-bundle-import-error', $this->withModuleBar(array(
            'message' => $this->esc($e->getMessage()),
            'back_url' => $this->esc($backUrl),
            'briefing_url' => $this->esc($this->moduleUrl('briefing')),
         ), 'bundle'));
      }
   }

   private function readImportPayload(string $token): array {
      $file = $this->firstUploadFile();
      if ($file) {
         return $this->extractZipUpload($file, $token);
      }

      $rawContract = trim((string)($_POST['contract_json'] ?? ''));
      $rawAnswer = trim((string)($_POST['answer_json'] ?? ''));
      if ($rawContract !== '' || $rawAnswer !== '') {
         $contract = json_decode($rawContract, true);
         $answer = json_decode($rawAnswer, true);
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

   private function firstUploadFile(): array {
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

   private function extractZipUpload(array $file, string $token): array {
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

      $maxBytes = $this->configInt('max_bundle_bytes', 52428800);
      if ((int)($file['size'] ?? 0) > $maxBytes) {
         throw new \InvalidArgumentException('ZIP ueberschreitet max_bundle_bytes.');
      }

      $token = $this->sanitizeToken($token);
      if ($token === '') {
         throw new \InvalidArgumentException('Interner Bundle-Token fehlt.');
      }
      $dest = $this->jobDir($token);
      if (is_dir($dest)) {
         $this->removeJobDir($token);
      }
      @mkdir($dest, 0777, true);

      $zip = new \ZipArchive();
      if ($zip->open($tmp) !== true) {
         throw new \InvalidArgumentException('ZIP konnte nicht geoeffnet werden.');
      }

      $maxFiles = $this->configInt('max_bundle_files', 50);
      $totalUncompressed = 0;
      for ($i = 0; $i < $zip->numFiles; $i++) {
         $name = (string)$zip->getNameIndex($i);
         if ($name === '' || strpos($name, '..') !== false || $name[0] === '/') {
            $zip->close();
            throw new \InvalidArgumentException('Unzulaessiger ZIP-Pfad: ' . $name);
         }
         $stat = $zip->statIndex($i);
         $totalUncompressed += (int)($stat['size'] ?? 0);
         if ($totalUncompressed > $maxBytes) {
            $zip->close();
            throw new \InvalidArgumentException('Entpacktes Bundle zu gross.');
         }
      }
      if ($zip->numFiles > $maxFiles) {
         $zip->close();
         throw new \InvalidArgumentException('Zu viele Dateien im Bundle.');
      }

      if (!$zip->extractTo($dest)) {
         $zip->close();
         throw new \RuntimeException('ZIP konnte nicht entpackt werden.');
      }
      $zip->close();

      $root = $this->findBundleRoot($dest);
      $this->validateResponseTree($root);
      $contract = $this->readJsonFile($root . '/auftrag.contract.json', true);
      $answer = $this->readJsonFile($root . '/answer.json', true);
      $readme = is_file($root . '/README.md') ? (string)file_get_contents($root . '/README.md') : '';
      $assetsDir = is_dir($root . '/assets') ? $root . '/assets' : '';

      return array(
         'contract' => $contract,
         'answer' => $answer,
         'assets_dir' => $assetsDir,
         'readme' => $readme,
      );
   }

   private function findBundleRoot(string $dest): string {
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

   private function validateResponseTree(string $root): void {
      $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
      foreach ($it as $item) {
         if (!$item->isFile()) continue;
         $relative = str_replace('\\', '/', substr($item->getPathname(), strlen(rtrim($root, '/\\')) + 1));
         if (in_array($relative, array('auftrag.contract.json', 'answer.json', 'README.md'), true)) continue;
         if (str_starts_with($relative, 'assets/')) continue;
         throw new \InvalidArgumentException('Nicht erlaubte Datei in Antwort-ZIP: ' . $relative);
      }
   }

   private function readJsonFile(string $path, bool $required): array {
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

   private function validateSnapshot(array $contract): void {
      $snapshot = is_array($contract['snapshot'] ?? null) ? $contract['snapshot'] : array();
      $type = strtolower(trim((string)($snapshot['type'] ?? '')));
      if ($type === '') return;
      $lng = strtolower(trim((string)($snapshot['lng'] ?? '')));
      $id = (int)($snapshot['id'] ?? 0);
      if ($id <= 0) {
         throw new \InvalidArgumentException('Der Auftrags-Snapshot enthaelt kein gueltiges Ziel.');
      }
      if ($type === 'folder') {
         $current = $this->cms()->bundleRead('folder.get', array('lng' => $lng, 'id' => $id));
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
      $current = $this->cms()->bundleRead('page.get', array('lng' => $lng, 'id' => $id));
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

   private function validateRecipe(array $contract, array $job): void {
      $recipe = strtolower(trim((string)($contract['recipe'] ?? '')));
      $allowed = array('page.create.v1', 'page.update.v1', 'translation.v1');
      if (!in_array($recipe, $allowed, true)) {
         throw new \InvalidArgumentException('Nicht unterstuetztes CMS-Rezept: ' . $recipe);
      }
      $manifestRecipe = strtolower(trim((string)($contract['metadata']['recipe'] ?? '')));
      if ($manifestRecipe !== $recipe) {
         throw new \InvalidArgumentException('Rezept und signierte Metadaten widersprechen sich.');
      }
      if (!is_array($job['steps'] ?? null) || !$job['steps']) {
         throw new \InvalidArgumentException('Der signierte Auftrag enthaelt keine Ausfuehrungsschritte.');
      }
   }

   private function validateJob(array $job, string $assetsDir): array {
      $steps = $job['steps'] ?? null;
      if (!is_array($steps) || !$steps) {
         throw new \InvalidArgumentException('Der intern gebundene Plan muss mindestens einen Schritt enthalten.');
      }

      $errors = array();
      $seenIds = array();
      $stepResults = array();
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
         if (isset($seenIds[$id])) {
            $errors[] = 'Doppelte step id: ' . $id;
         }
         $seenIds[$id] = true;

         $action = trim((string)($step['action'] ?? ''));
         if ($action === '') {
            $errors[] = 'Step ' . $id . ' ohne action.';
            continue;
         }
         if (!$cms->bundleIsAllowedInPackage($action)) {
            $errors[] = 'Step ' . $id . ': Aktion nicht erlaubt (' . $action . ').';
            continue;
         }

         $params = is_array($step['params'] ?? null) ? $step['params'] : array();
         try {
            $this->validateRefTargets($params, array_keys($stepResults));
            $resolved = $this->resolveParams($params, $stepResults, $assetsDir, false);
            $cms->bundleBuildPlan($action, $resolved);
            $stepResults[$id] = $this->syntheticStepResult($action);
         } catch (\Throwable $e) {
            if ($this->paramsContainRef($params) && $this->isPreviewRefDependencyError($e)) {
               $stepResults[$id] = $this->syntheticStepResult($action);
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

   private function buildPreview(array $job, string $assetsDir, array $manifest): array {
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

      if ($assetsDir !== '' && is_dir($assetsDir)) {
         $count = count(glob(rtrim($assetsDir, '/\\') . '/*') ?: array());
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
   private function renderImportSuccess(string $token, array $state): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $title = trim((string)($state['title'] ?? 'KI-Bundle'));
      return $tpl->get_tpl('dbxKi|ki-bundle-import-success', array(
         'message' => 'Bundle geprueft. Vorschau wird geoeffnet ...',
         'preview_url' => $this->esc($this->moduleUrl('bundle_import', array('token' => $token))),
         'window_title' => $this->esc($title !== '' ? $title : 'KI-Bundle-Vorschau'),
      ));
   }

   /**
    * Verwirft einen geprueften, aber noch nicht ausgefuehrten Bundle-Job.
    * Von "Verwerfen" im Vorschaufenster per AJAX aufgerufen (kiResultWindow.js
    * schliesst das Fenster danach clientseitig).
    */
   public function handleDiscard(): void {
      $token = $this->sanitizeToken((string)dbx()->get_request_var('token', '', '*'));
      if ($token !== '') {
         $this->removeJobDir($token);
         unset($_SESSION['dbx']['dbxKi'][self::SESSION_KEY][$token]);
      }
      header('Content-Type: text/plain; charset=utf-8');
      echo 'ok';
      exit;
   }

   private function renderPreviewPage(string $token, array $state): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $warnings = is_array($state['validation']['warnings'] ?? null) ? $state['validation']['warnings'] : array();
      $lines = is_array($state['preview']['lines'] ?? null) ? $state['preview']['lines'] : array();

      $warningHtml = '<li class="text-muted">Keine</li>';
      foreach ($warnings as $warning) {
         $warningHtml = '';
         break;
      }
      if ($warningHtml === '') {
         foreach ($warnings as $warning) {
            $warningHtml .= '<li>' . $this->esc($warning) . '</li>';
         }
      }

      $lineHtml = '';
      foreach ($lines as $line) {
         $lineHtml .= '<li class="list-group-item py-1 px-2">' . $this->esc($line) . '</li>';
      }

      $warningBlock = '';
      if ($warningHtml !== '<li class="text-muted">Keine</li>') {
         $warningBlock = '<div class="small text-warning mb-2"><strong>Hinweise</strong><ul class="mb-0 ps-3">'
            . $warningHtml . '</ul></div>';
      }

      $readme = trim((string)($state['readme'] ?? ''));
      $readmeHtml = '';
      if ($readme !== '') {
         $readmeHtml = '<details class="small mt-2"><summary class="text-muted">README der KI-ZIP</summary>'
            . '<pre class="small bg-light p-2 rounded mt-1 mb-0" style="max-height:8rem;overflow:auto">'
            . $this->esc($readme) . '</pre></details>';
      }

      $backUrl = $this->returnUrlFromState($state);
      $startUrl = $this->moduleUrl('bundle_process', array(
         'proc_key' => $token,
         'reset' => 1,
         'proc_cmd' => 'start',
         'token' => $this->cms()->bundleExecuteToken(),
      ));
      $footerActions = $this->buildImportFooterActions($state, true, $startUrl, $backUrl, $token);
      $barTitle = trim((string)($state['title'] ?? ''));

      return $tpl->get_tpl('dbxKi|ki-bundle-preview', $this->withModuleBar(array(
         'subtitle' => $this->esc((string)($state['recipe'] ?? '')),
         'step_count' => (int)($state['total'] ?? 0),
         'preview_list' => $lineHtml,
         'warning_block' => $warningBlock,
         'readme_block' => $readmeHtml,
         'footer_actions' => $footerActions,
      ), 'bundle_preview', '', $barTitle));
   }

   private function cmsAdminPageUrl(int $cid, string $lng = ''): string {
      $url = '?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=' . (int)$cid;
      $lng = strtolower(trim($lng));
      if ($lng !== '') {
         $url .= '&dbx_lng=' . rawurlencode($lng);
      }
      return $url;
   }

   private function resolveContentPageRef(array $state): array {
      $lng = strtolower(trim((string)($state['lng'] ?? '')));
      $stepResults = is_array($state['step_results'] ?? null) ? $state['step_results'] : array();
      foreach ($stepResults as $result) {
         if (!is_array($result)) {
            continue;
         }
         $cid = (int)($result['page_id'] ?? 0);
         if ($cid > 0) {
            $resultLng = strtolower(trim((string)($result['lng'] ?? '')));
            return array(
               'cid' => $cid,
               'lng' => $resultLng !== '' ? $resultLng : $lng,
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
               $stepLng = strtolower(trim((string)($params['lng'] ?? '')));
               return array('cid' => $cid, 'lng' => $stepLng !== '' ? $stepLng : $lng);
            }
         }
      }
      return array('cid' => 0, 'lng' => $lng);
   }

   private function buildImportFooterActions(array $state, bool $showExecute, string $executeUrl, string $backUrl, string $token = ''): string {
      $html = '';
      if ($showExecute && $executeUrl !== '') {
         $html .= '<button type="button" class="btn btn-primary btn-sm" data-dbx-ki-inline-action="' . $this->esc($executeUrl)
            . '"><i class="bi bi-play-fill"></i> Ausfuehren</button>';
      }
      if ($token !== '') {
         $html .= '<button type="button" class="btn btn-outline-danger btn-sm" data-dbx-ki-discard="'
            . $this->esc($this->moduleUrl('bundle_discard', array('token' => $token)))
            . '" data-confirm="Bundle wirklich verwerfen? Die Aenderungen werden nicht uebernommen.">'
            . '<i class="bi bi-x-lg"></i> Verwerfen</button>';
      }

      $pageRef = $this->resolveContentPageRef($state);
      if ((int)($pageRef['cid'] ?? 0) > 0) {
         $html .= '<a class="btn btn-success btn-sm" href="' . $this->esc($this->cmsAdminPageUrl((int)$pageRef['cid'], (string)($pageRef['lng'] ?? '')))
            . '"><i class="bi bi-pencil-square"></i> Seite im CMS</a>';
      }

      $html .= '<a class="btn btn-outline-primary btn-sm" href="' . $this->esc($this->moduleUrl('briefing'))
         . '"><i class="bi bi-plus-lg"></i> Neuer KI-Auftrag</a>';
      $html .= '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($backUrl)
         . '"><i class="bi bi-arrow-left"></i> Zurueck</a>';
      return $html;
   }

   private function sanitizeReturnRun1(string $run1): string {
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

   private function returnUrlFromState(array $state): string {
      $run1 = trim((string)($state['return_run1'] ?? ''));
      if ($run1 === '') {
         return $this->moduleUrl('bundle');
      }
      return $this->moduleUrl($this->sanitizeReturnRun1($run1));
   }

   public function handleProcess(): string {
      $token = $this->sanitizeToken((string)($_GET['proc_key'] ?? ''));
      if ($token === '') {
         $token = $this->newToken();
      }

      $cmd = strtolower(preg_replace('/[^a-z_]+/', '', (string)($_GET['proc_cmd'] ?? '')));
      $reset = (int)($_GET['reset'] ?? 0);
      $state = $this->getJob($token);

      if (!$state) {
         return '<div class="container py-4"><div class="alert alert-warning">Bundle-Prozess nicht gefunden.</div>'
            . '<p><a class="btn btn-secondary" href="' . $this->esc($this->moduleUrl('bundle')) . '">Zurueck</a></p></div>';
      }

      if ($reset || $cmd === 'restart') {
         try {
            if ($cmd === 'start' || $reset || $cmd === 'restart') {
               $this->authorizeBundleExecute();
            }
         } catch (\Throwable $e) {
            $state['status'] = 'error';
            $state['message'] = $e->getMessage();
            $this->setJob($token, $state);
            $next = $this->moduleUrl('bundle_process', array('proc_key' => $token));
            return $this->renderProcess($state, $next);
         }
         $state['status'] = 'running';
         $state['step_pos'] = 0;
         $state['step_results'] = array();
         $state['percent'] = 0;
         $state['step_percent'] = 0;
         $state['message'] = 'Bundle-Ausfuehrung gestartet.';
      } elseif ($cmd === 'start' && ($state['status'] ?? '') === 'preview_ready') {
         try {
            $this->authorizeBundleExecute();
         } catch (\Throwable $e) {
            $state['status'] = 'error';
            $state['message'] = $e->getMessage();
            $this->setJob($token, $state);
            $next = $this->moduleUrl('bundle_process', array('proc_key' => $token));
            return $this->renderProcess($state, $next);
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
         $this->executeAtomically($state);
      }

      $this->setJob($token, $state);
      $next = $this->moduleUrl('bundle_process', array('proc_key' => $token));
      return $this->renderProcess($state, $next);
   }

   private function authorizeBundleExecute(): void {
      if ((int)dbx()->get_cfg('dbxKi', 'allow_execute', 1) !== 1) {
         throw new \RuntimeException('Automatische Ausfuehrung ist deaktiviert (allow_execute).');
      }
      $token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
      if (!$this->cms()->bundleCheckExecuteToken($token)) {
         throw new \RuntimeException('Ungueltiges oder abgelaufenes Ausfuehrungs-Token.');
      }
   }

   private function executeAtomically(array &$state): void {
      $db = dbx()->get_system_obj('dbxDB');
      $dds = $this->transactionDds((array)($state['job'] ?? array()));
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
            $this->tickJob($state);
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
         $this->discardFileBackups((array)($state['file_backups'] ?? array()));
         $state['file_backups'] = array();
      } catch (\Throwable $e) {
         foreach (array_reverse($started) as $dd) {
            $db->rollback($dd);
         }
         $this->removeCreatedMediaFiles((array)($state['step_results'] ?? array()));
         $this->restoreFileBackups((array)($state['file_backups'] ?? array()));
         $state['file_backups'] = array();
         $state['status'] = 'error';
         $state['rolled_back'] = true;
         $state['message'] = 'Bundle vollstaendig zurueckgerollt: ' . $e->getMessage();
      }
   }

   private function transactionDds(array $job): array {
      $dds = array('dbxMedia', 'dbxMediaUsage');
      foreach ((array)($job['steps'] ?? array()) as $step) {
         $params = is_array($step['params'] ?? null) ? $step['params'] : array();
         foreach (array('lng', 'source_lng', 'target_lng') as $key) {
            $lng = strtolower(trim((string)($params[$key] ?? '')));
            if ($lng !== '') {
               $dds[] = dbxContentLng::ddContent($lng);
               $dds[] = dbxContentLng::ddFolder($lng);
            }
         }
      }
      return array_values(array_unique($dds));
   }

   private function removeCreatedMediaFiles(array $results): void {
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

   private function captureFileBackups(array $plan, array &$state): void {
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

   private function restoreFileBackups(array $backups): void {
      foreach ($backups as $target => $backup) {
         if (is_file((string)$backup)) {
            @copy((string)$backup, dbx()->os_path((string)$target));
            @unlink((string)$backup);
         }
      }
   }

   private function discardFileBackups(array $backups): void {
      foreach ($backups as $backup) if (is_file((string)$backup)) @unlink((string)$backup);
   }

   private function tickJob(array &$state): void {
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
         $state['message'] = $this->finishedMessage($state);
         return;
      }

      $step = $steps[$pos];
      $stepId = (string)($step['id'] ?? ('step_' . $pos));
      $action = (string)($step['action'] ?? '');
      $assetsDir = (string)($state['assets_dir'] ?? '');
      $stepResults = is_array($state['step_results'] ?? null) ? $state['step_results'] : array();

      try {
         $params = is_array($step['params'] ?? null) ? $step['params'] : array();
         $resolved = $this->resolveParams($params, $stepResults, $assetsDir, false);
         $plan = $this->cms()->bundleBuildPlan($action, $resolved);
         $this->captureFileBackups($plan, $state);
         $result = $this->cms()->bundleExecutePlan($action, $resolved, $plan);
         $stepResults[$stepId] = $this->normalizeStepResult($action, $result);
         $state['step_results'] = $stepResults;
         $state['step_pos'] = $pos + 1;
         $state['message'] = 'Step ' . $stepId . ' (' . $action . ') ausgefuehrt.';
      } catch (\Throwable $e) {
         $state['status'] = 'error';
         $state['message'] = 'Fehler in Step ' . $stepId . ': ' . $e->getMessage();
         dbx()->sys_msg('error', 'dbxKi', 'bundle_process', $stepId, $e->getMessage());
         return;
      }

      $done = (int)$state['step_pos'];
      $state['percent'] = (int)floor(($done / $total) * 100);
      $state['step_percent'] = 100;
      if ($done >= $total) {
         $state['status'] = 'finished';
         $state['percent'] = 100;
         $state['message'] = $this->finishedMessage($state);
      }
   }

   private function finishedMessage(array $state): string {
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

   private function normalizeStepResult(string $action, array $result): array {
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

   private function resolveParams($value, array $stepResults, string $assetsDir, bool $dryRun) {
      if (is_array($value)) {
         $out = array();
         foreach ($value as $key => $item) {
            $out[$key] = $this->resolveParams($item, $stepResults, $assetsDir, $dryRun);
         }
         if (isset($out['asset_ref']) && $assetsDir !== '') {
            $out = $this->applyAssetRef($out, $assetsDir, $dryRun);
         }
         return $out;
      }

      if (!is_string($value)) {
         return $value;
      }

      if (strpos($value, '$ref:') === 0) {
         if ($dryRun) {
            return 0;
         }
         return $this->resolveRef($value, $stepResults);
      }

      if (strpos($value, '$ref:') !== false) {
         if ($dryRun) {
            return $value;
         }
         return preg_replace_callback('/\$ref:([A-Za-z0-9_.-]+)/', function($match) use ($stepResults) {
            return (string)$this->resolveRef('$ref:' . $match[1], $stepResults);
         }, $value);
      }

      return $value;
   }

   private function paramsContainRef($value): bool {
      if (is_array($value)) {
         foreach ($value as $item) {
            if ($this->paramsContainRef($item)) {
               return true;
            }
         }
         return false;
      }
      return is_string($value) && strpos($value, '$ref:') !== false;
   }

   private function validateRefTargets($value, array $completedStepIds): void {
      if (is_array($value)) {
         foreach ($value as $item) {
            $this->validateRefTargets($item, $completedStepIds);
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
      if (!in_array($parts[0], $completedStepIds, true)) {
         throw new \InvalidArgumentException('Referenz auf noch nicht ausgefuehrten Step: ' . $value);
      }
   }

   private function isPreviewRefDependencyError(\Throwable $e): bool {
      $msg = $e->getMessage();
      return strpos($msg, 'größer als 0') !== false
         || strpos($msg, 'groesser als 0') !== false
         || strpos($msg, 'nicht gefunden') !== false;
   }

   private function syntheticStepResult(string $action): array {
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

   private function resolveRef(string $value, array $stepResults) {
      $raw = substr($value, 5);
      $parts = explode('.', $raw, 2);
      if (count($parts) !== 2) {
         throw new \InvalidArgumentException('Ungueltige Referenz: ' . $value);
      }
      $stepId = $parts[0];
      $field = $parts[1];
      if (!isset($stepResults[$stepId]) || !is_array($stepResults[$stepId])) {
         throw new \RuntimeException('Referenz nicht aufloesbar: ' . $value);
      }
      if (!array_key_exists($field, $stepResults[$stepId])) {
         throw new \RuntimeException('Feld in Referenz fehlt: ' . $value);
      }
      return $stepResults[$stepId][$field];
   }

   private function applyAssetRef(array $params, string $assetsDir, bool $dryRun): array {
      $ref = ltrim(str_replace('\\', '/', (string)($params['asset_ref'] ?? '')), '/');
      if (strpos($ref, 'assets/') === 0) {
         $ref = substr($ref, 7);
      }
      if ($ref === '' || strpos($ref, '..') !== false) {
         throw new \InvalidArgumentException('Ungueltiger asset_ref.');
      }
      $path = rtrim(str_replace('\\', '/', $assetsDir), '/') . '/' . $ref;
      $realAssets = realpath($assetsDir);
      $realFile = realpath($path);
      if (!$realAssets || !$realFile || strpos($realFile, $realAssets) !== 0 || !is_file($realFile)) {
         throw new \InvalidArgumentException('Asset nicht gefunden: ' . $ref);
      }
      if ($dryRun) {
         $params['data_base64'] = base64_encode('dry-run');
      } else {
         $bytes = file_get_contents($realFile);
         if ($bytes === false) {
            throw new \RuntimeException('Asset konnte nicht gelesen werden: ' . $ref);
         }
         $params['data_base64'] = base64_encode($bytes);
      }
      unset($params['asset_ref']);
      if (empty($params['file_name'])) {
         $params['file_name'] = basename($realFile);
      }
      return $params;
   }

   private function renderProcess(array $state, string $nextUrl): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $status = strtolower((string)($state['status'] ?? 'running'));
      $percent = max(0, min(100, (int)($state['percent'] ?? 0)));
      $stepPercent = max(0, min(100, (int)($state['step_percent'] ?? 0)));
      $token = (string)($state['proc_key'] ?? '');

      $statusLabels = array(
         'preview_ready' => 'Bereit',
         'running' => 'Laeuft',
         'paused' => 'Pausiert',
         'finished' => 'Fertig',
         'error' => 'Fehler',
         'canceled' => 'Abgebrochen',
      );
      $statusClasses = array(
         'running' => 'text-bg-primary',
         'paused' => 'text-bg-warning',
         'finished' => 'text-bg-success',
         'error' => 'text-bg-danger',
         'canceled' => 'text-bg-secondary',
         'preview_ready' => 'text-bg-info',
      );
      $statusIcons = array(
         'running' => 'bi bi-arrow-repeat',
         'paused' => 'bi bi-pause-fill',
         'finished' => 'bi bi-check-lg',
         'error' => 'bi bi-exclamation-triangle',
         'canceled' => 'bi bi-x-lg',
         'preview_ready' => 'bi bi-hourglass',
      );

      $append = function(string $url, array $params): string {
         foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
               continue;
            }
            $url .= (strpos($url, '?') === false ? '?' : '&')
               . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
         }
         return $url;
      };

      $targetId = 'dbx_ki_bundle_' . substr(md5($token), 0, 12);
      $autostart = ($status === 'running') ? 1 : 0;
      $tokenParam = $this->cms()->bundleExecuteToken();
      $backUrl = $this->returnUrlFromState($state);
      $finishedActions = '';
      if ($status === 'finished') {
         $finishedActions = $this->buildImportFooterActions($state, false, '', $backUrl);
      }

      $barTitle = trim((string)($state['title'] ?? ''));

      return $tpl->get_tpl('dbxKi|ki-bundle-process', $this->withModuleBar(array(
         'target_id' => $this->esc($targetId),
         'status_key' => $this->esc($status),
         'status_label' => $this->esc($statusLabels[$status] ?? $status),
         'status_class' => $this->esc($statusClasses[$status] ?? 'text-bg-secondary'),
         'status_icon' => $this->esc($statusIcons[$status] ?? 'bi bi-circle'),
         'message' => $this->esc((string)($state['message'] ?? '')),
         'percent' => $percent,
         'step_percent' => $stepPercent,
         'step_label' => $this->esc((int)($state['step_pos'] ?? 0) . ' / ' . (int)($state['total'] ?? 0)),
         'updated_at' => $this->esc((string)($state['updated_at'] ?? '')),
         'next_url' => $this->esc($nextUrl),
         'pause_url' => $this->esc($append($nextUrl, array('proc_cmd' => 'pause', 'token' => $tokenParam))),
         'resume_url' => $this->esc($append($nextUrl, array('proc_cmd' => 'resume', 'token' => $tokenParam))),
         'cancel_url' => $this->esc($append($nextUrl, array('proc_cmd' => 'cancel', 'token' => $tokenParam))),
         'restart_url' => $this->esc($append($nextUrl, array('reset' => 1, 'proc_cmd' => 'restart', 'token' => $tokenParam))),
         'back_url' => $this->esc($backUrl),
         'autostart' => $autostart,
         'interval' => 900,
         'pause_visible' => 'running',
         'resume_visible' => 'paused',
         'restart_visible' => 'paused,canceled,error,finished',
         'cancel_visible' => 'running,paused',
         'result_html' => $this->renderResultHtml($state),
         'finished_actions' => $finishedActions,
      ), 'bundle_process', '', $barTitle));
   }

   private function renderResultHtml(array $state): string {
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

   public function handleExport(): void {
      // Freie Beispiel-Jobs sind in Vertragsversion 2 entfallen. Jeder
      // Auftrag wird mit konkretem Ziel und Snapshot im Briefing signiert.
      header('Location: ' . $this->moduleUrl('briefing'));
      exit;
   }
   public function handleDescribeJson(): void {
      dbx()->json_response($this->describeBundle(), true);
   }
}

?>
