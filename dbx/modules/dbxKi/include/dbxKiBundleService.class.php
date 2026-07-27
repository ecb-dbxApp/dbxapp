<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

class dbxKiBundleService {

   private const BUNDLE_VERSION = '1.0';
   private const SESSION_KEY = 'bundle_jobs';
   private const AREA = 'cms';

   private function cms(): dbxKiCmsService {
      return dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');
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
      return max(1, (int)dbx()->get_config('dbxKi', $key, $default));
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
            'manifest.json' => 'Metadaten (title, recipe, lng, intent, auto_execute)',
            'job.json' => 'Geordnete steps mit action und params',
            'context.json' => 'Optionaler Arbeitskontext',
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
            'auto_execute' => 'Wenn manifest.auto_execute=true gesetzt ist, startet dbxKi nach erfolgreicher Validierung automatisch den Bundle-Prozess.',
            'ki_contract' => 'Die KI liefert nur manifest.json, job.json und optionale assets/. Keine externen Runner oder eigenen Tools.',
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
         tooltip: 'ZIP-Datei mit manifest.json und job.json auswaehlen.',
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
      try {
         $isBrowserUpload = $this->firstUploadFile() !== array() || isset($_POST['return_run1']);
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
         $manifest = is_array($payload['manifest'] ?? null) ? $payload['manifest'] : array();
         $context = is_array($payload['context'] ?? null) ? $payload['context'] : array();
         $job = is_array($payload['job'] ?? null) ? $payload['job'] : array();
         $assetsDir = (string)($payload['assets_dir'] ?? '');
         $readme = (string)($payload['readme'] ?? '');

         $validation = $this->validateJob($job, $assetsDir);
         $preview = $this->buildPreview($job, $assetsDir, $manifest);

         $state = array(
            'area' => self::AREA,
            'status' => 'preview_ready',
            'percent' => 0,
            'step_percent' => 0,
            'message' => 'Bundle geprueft. Ausfuehrung starten.',
            'manifest' => $manifest,
            'context' => $context,
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

         if ($this->manifestAutoExecute($manifest)) {
            try {
               $this->authorizeAutoExecuteManifest();
               $state['status'] = 'running';
               $state['message'] = 'Bundle geprueft. Automatische Ausfuehrung gestartet.';
               $this->setJob($token, $state);
               $next = $this->moduleUrl('bundle_process', array('proc_key' => $token));
               return $this->renderProcess($state, $next);
            } catch (\Throwable $e) {
               $state['status'] = 'error';
               $state['message'] = $e->getMessage();
               $this->setJob($token, $state);
               $next = $this->moduleUrl('bundle_process', array('proc_key' => $token));
               return $this->renderProcess($state, $next);
            }
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

      $rawJob = trim((string)($_POST['job_json'] ?? ''));
      if ($rawJob !== '') {
         $job = json_decode($rawJob, true);
         if (!is_array($job)) {
            throw new \InvalidArgumentException('job_json ist kein gueltiges JSON.');
         }
         $manifest = array('title' => 'JSON-Job', 'lng' => dbxContentLng::current(), 'bundle_version' => self::BUNDLE_VERSION);
         $rawManifest = trim((string)($_POST['manifest_json'] ?? ''));
         if ($rawManifest !== '') {
            $decoded = json_decode($rawManifest, true);
            if (is_array($decoded)) {
               $manifest = array_merge($manifest, $decoded);
            }
         }
         return array(
            'manifest' => $manifest,
            'context' => array(),
            'job' => $job,
            'assets_dir' => '',
            'readme' => '',
         );
      }

      throw new \InvalidArgumentException('Bitte ZIP-Datei oder job.json senden.');
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
      $manifest = $this->readJsonFile($root . '/manifest.json', false);
      $context = $this->readJsonFile($root . '/context.json', false);
      $job = $this->readJsonFile($root . '/job.json', true);
      $readme = is_file($root . '/README.md') ? (string)file_get_contents($root . '/README.md') : '';
      $assetsDir = is_dir($root . '/assets') ? $root . '/assets' : '';

      return array(
         'manifest' => is_array($manifest) ? $manifest : array(),
         'context' => is_array($context) ? $context : array(),
         'job' => $job,
         'assets_dir' => $assetsDir,
         'readme' => $readme,
      );
   }

   private function findBundleRoot(string $dest): string {
      if (is_file($dest . '/job.json')) {
         return $dest;
      }
      foreach (glob($dest . '/*', GLOB_ONLYDIR) ?: array() as $dir) {
         if (is_file($dir . '/job.json')) {
            return $dir;
         }
      }
      throw new \InvalidArgumentException('job.json im Bundle nicht gefunden.');
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

   private function validateJob(array $job, string $assetsDir): array {
      $steps = $job['steps'] ?? null;
      if (!is_array($steps) || !$steps) {
         throw new \InvalidArgumentException('job.json muss ein nicht-leeres steps-Array enthalten.');
      }

      $warnings = array();
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
            $warnings[] = 'Step ' . $id . ' (' . $action . '): ' . $e->getMessage();
         }
      }

      if ($errors) {
         throw new \InvalidArgumentException(implode(' ', $errors));
      }

      return array(
         'ok' => 1,
         'step_count' => count($steps),
         'warnings' => $warnings,
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
      $footerActions = $this->buildImportFooterActions($state, true, $startUrl, $backUrl);
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

   private function buildImportFooterActions(array $state, bool $showExecute, string $executeUrl, string $backUrl): string {
      $html = '';
      if ($showExecute && $executeUrl !== '') {
         $html .= '<a class="btn btn-primary btn-sm" href="' . $this->esc($executeUrl)
            . '"><i class="bi bi-play-fill"></i> Ausfuehren</a>';
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
         $this->tickJob($state);
      }

      $this->setJob($token, $state);
      $next = $this->moduleUrl('bundle_process', array('proc_key' => $token));
      return $this->renderProcess($state, $next);
   }

   private function authorizeBundleExecute(): void {
      if ((int)dbx()->get_config('dbxKi', 'allow_execute', 1) !== 1) {
         throw new \RuntimeException('Automatische Ausfuehrung ist deaktiviert (allow_execute).');
      }
      $token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
      if (!$this->cms()->bundleCheckExecuteToken($token)) {
         throw new \RuntimeException('Ungueltiges oder abgelaufenes Ausfuehrungs-Token.');
      }
   }

   private function manifestAutoExecute(array $manifest): bool {
      $value = $manifest['auto_execute'] ?? false;
      if (is_bool($value)) {
         return $value;
      }
      $value = strtolower(trim((string)$value));
      return in_array($value, array('1', 'true', 'yes', 'ja', 'on'), true);
   }

   private function authorizeAutoExecuteManifest(): void {
      if ((int)dbx()->get_config('dbxKi', 'allow_execute', 1) !== 1) {
         throw new \RuntimeException('Automatische Ausfuehrung ist deaktiviert (allow_execute).');
      }
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
      if (!class_exists('ZipArchive')) {
         dbx()->json_response(array('ok' => 0, 'error' => 'ZipArchive nicht verfuegbar.'), true);
      }

      $cms = $this->cms();
      $lng = dbxContentLng::current();
      $describe = $this->describeBundle();
      $snapshot = $cms->bundleSnapshot(array('lng' => $lng, 'limit' => 50));
      $instructionsPath = dirname(__DIR__) . '/KI-INSTRUCTIONS.md';
      $instructions = is_file($instructionsPath) ? file_get_contents($instructionsPath) : '';

      $manifest = array(
         'bundle_version' => self::BUNDLE_VERSION,
         'api_version' => '0.1',
         'title' => 'dbxKi CMS Briefing',
         'recipe' => 'page.create.v1',
         'lng' => $lng,
         'intent' => 'create',
         'area' => self::AREA,
         'auto_execute' => true,
      );

      $exampleJob = array(
         'steps' => array(
            array(
               'id' => 'hero',
               'action' => 'media.create_base64',
               'params' => array(
                  'file_name' => 'hero.jpg',
                  'asset_ref' => 'hero.jpg',
                  'title' => 'Hero',
                  'alt' => 'Hero-Bild',
               ),
            ),
            array(
               'id' => 'page',
               'action' => 'page.create',
               'params' => array(
                  'lng' => $lng,
                  'folder_id' => 1,
                  'title' => 'Neue KI-Seite',
                  'template' => 'c-title-hero_header-body1-footer',
                  'content' => '<p>Inhalt der Seite</p>',
               ),
            ),
            array(
               'id' => 'hero_assign',
               'action' => 'media.assign',
               'params' => array(
                  'media_id' => '$ref:hero.media_id',
                  'content_id' => '$ref:page.page_id',
                  'slot' => 'hero',
                  'lng' => $lng,
               ),
            ),
         ),
      );

      $context = array(
         'lng' => $lng,
         'snapshot' => $snapshot,
         'note' => 'Ordner- und Seiten-IDs aus snapshot fuer folder_id / page.update verwenden.',
      );

      $readme = "# dbxKi CMS Bundle\n\n"
         . "1. job.json und optionale assets/ bearbeiten\n"
         . "2. ZIP mit manifest.json, job.json, README.md erstellen\n"
         . "3. In dbxKi unter Bundle importieren\n";

      $tmp = tempnam(sys_get_temp_dir(), 'dbxki');
      $zip = new \ZipArchive();
      if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
         @unlink($tmp);
         throw new \RuntimeException('Export-ZIP konnte nicht erstellt werden.');
      }

      $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      $zip->addFromString('job.json', json_encode($exampleJob, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      $zip->addFromString('context.json', json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      $zip->addFromString('bundle.describe.json', json_encode($describe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      $zip->addFromString('README.md', $readme);
      if ($instructions !== '') {
         $zip->addFromString('KI-INSTRUCTIONS.md', $instructions);
      }
      $zip->close();

      $name = 'dbxki-cms-briefing-' . $lng . '.zip';
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="' . $name . '"');
      header('Content-Length: ' . filesize($tmp));
      readfile($tmp);
      @unlink($tmp);
      exit;
   }

   public function handleDescribeJson(): void {
      dbx()->json_response($this->describeBundle(), true);
   }
}

?>
