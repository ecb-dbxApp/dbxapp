<?php
namespace dbx\dbxKi;

class dbxKiModuleBriefingService {

   private const MODULE_BRIEFING_VERSION = '0.6';

   private function esc($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function modulesRoot(): string {
      return dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/');
   }

   private function validName($name): bool {
      return is_string($name) && preg_match('/^[A-Za-z][A-Za-z0-9_]{1,63}$/', $name);
   }

   private function moduleDir(string $modul): string {
      return dbx()->os_path($this->modulesRoot() . $modul . DIRECTORY_SEPARATOR);
   }

   private function modulePath(string $modul, string $rel): string {
      $rel = str_replace(array('\\', "\0"), array('/', ''), $rel);
      $rel = ltrim($rel, '/');
      if ($rel === '' || strpos($rel, '../') !== false) {
         return '';
      }
      $base = $this->moduleDir($modul);
      $path = dbx()->os_path($base . $rel);
      $baseNorm = str_replace('\\', '/', rtrim($base, '/\\') . '/');
      $pathNorm = str_replace('\\', '/', $path);
      return strpos($pathNorm, $baseNorm) === 0 ? $path : '';
   }

   private function moduleUrl(string $run1, array $params = array()): string {
      $url = '?dbx_modul=dbxKi&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }
      return $url;
   }

   /**
    * Baut ein spezialisiertes Modul-Briefingformular ueber dbxForm auf.
    *
    * Die komplexe Anordnung liegt im HTML-Template. dbxForm verantwortet den
    * stabilen Formularnamen, den CSRF-Schutz, Submit-Erkennung und Meldungen.
    * Ein `[dbx:form]`-Slot im Template nimmt das Security-Feld gezielt auf.
    *
    * @param string $fid Stabile Formular-ID
    * @param string $template dbxKi-Template ohne Modul-Praefix
    * @param string $action Ziel-URL
    * @param array $replacements Fertige Templatewerte fuer den jeweiligen Slot
    *
    * @return \dbxForm
    */
   private function managedForm(string $fid, string $template, string $action, array $replacements = array()) {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init($fid, $template);
      $form->_action = $action;
      $form->_msg_info = '';
      foreach ($replacements as $key => $value) {
         $form->add_rep((string)$key, $value);
      }
      return $form;
   }

   private function selectOptions(array $options, string $selected): string {
      $html = '';
      foreach ($options as $value => $label) {
         $sel = (string)$value === $selected ? ' selected' : '';
         $html .= '<option value="' . $this->esc($value) . '"' . $sel . '>' . $this->esc($label) . '</option>';
      }
      return $html;
   }

   private function moduleOptions(string $selected = ''): string {
      $html = '<option value="">Bitte Modul waehlen</option>';
      $dirs = glob($this->modulesRoot() . '*', GLOB_ONLYDIR) ?: array();
      sort($dirs);
      foreach ($dirs as $dir) {
         $name = basename($dir);
         if (!$this->validName($name)) {
            continue;
         }
         $sel = $name === $selected ? ' selected' : '';
         $html .= '<option value="' . $this->esc($name) . '"' . $sel . '>' . $this->esc($name) . '</option>';
      }
      return $html;
   }

   private function fileTree(string $modul, bool $withContent = false): array {
      $dir = $this->moduleDir($modul);
      $rows = array();
      if (!is_dir($dir)) {
         return $rows;
      }
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::SELF_FIRST
      );
      foreach ($it as $item) {
         $full = $item->getPathname();
         $rel = str_replace('\\', '/', substr($full, strlen(rtrim($dir, '/\\')) + 1));
         $row = array(
            'path' => 'dbx/modules/' . $modul . '/' . $rel,
            'type' => $item->isDir() ? 'dir' : 'file',
            'size' => $item->isFile() ? (int)$item->getSize() : 0,
         );
         if ($withContent && $item->isFile() && $this->isTextFile($full) && $item->getSize() <= 262144) {
            $row['content'] = (string)file_get_contents($full);
         }
         $rows[] = $row;
      }
      return $rows;
   }

   private function isTextFile(string $file): bool {
      return (bool)preg_match('/\.(php|phtml|pht|htm|html|css|js|json|md|txt|sql|xml|svg)$/i', $file);
   }

   private function moduleDescribe(string $modul = ''): array {
      return array(
         'ok' => 1,
         'area' => 'module',
         'module' => $modul,
         'briefing_version' => self::MODULE_BRIEFING_VERSION,
         'endpoint' => $this->moduleUrl('module_api'),
         'hard_rules' => $this->hardRules($modul ?: '{module}'),
         'dbxapp_way' => $this->dbxappWay(),
         'reference_standard' => $this->referenceStandard(),
         'module_pipeline' => $this->modulePipeline($modul ?: '{module}'),
         'ui_contract' => $this->uiContract(),
         'dbx_api_contract' => $this->dbxApiContract($modul ?: '{module}'),
         'api_actions' => $this->apiCatalog(),
         'answer_zip_contract' => $this->answerZipContract($modul ?: '{module}'),
      );
   }

   private function hardRules(string $modul): array {
      return array(
         'Nur Dateien unter dbx/modules/' . $modul . '/ bearbeiten.',
         'Keine Aenderungen an dbx/include, globaler config.php, anderen Modulen oder files/ ausser explizit erlaubten Modul-Assets.',
         'Vor destruktiven Aenderungen muss ein vollstaendiges Modul-ZIP als Backup existieren.',
         'Fachliche Datenbankzugriffe ausschliesslich ueber dbxDB und DD-Namen; kein PDO, mysqli, rohes SQL oder direkter Zugriff auf db3-Dateien.',
         'create_date, create_uid, update_date, update_uid und owner werden von dbxDB gesetzt und duerfen im Modulcode nicht manuell geschrieben werden.',
         'DD-Dateien muessen im dbxapp-Exportformat vollstaendig und direkt lesbar sein: TABLE, FIELDS und INDEXES explizit mit $table[...], $field[...] und $index[...] definieren. Keine $addField-Closure, keine DD-Includes und keine versteckende Hilfsabstraktion.',
         'Produktive Tabellen-DDs verwenden trace=0, sofern keine ausdruecklich dokumentierte Systemausnahme besteht.',
         'DD->DB Sync nur fuer ' . $modul . '|{dd}. Keine Migrationen, keine Altlasten.',
         'Templates ueber dbxTPL lesen/rendern: dbx()->get_system_obj("dbxTPL")->get_tpl("' . $modul . '|template", $data).',
         'Formulare ueber dbxForm bauen. Nach einem Insert die von dbxDB gelieferte RID in Formular und Action uebernehmen, damit weiteres Speichern ein Update ausfuehrt. Modulcode fuegt normalen Form-Actions keinen dbx_token hinzu; dbxForm signiert nur automatisch erkannte delete/save-RID-Actions.',
         'Reports ueber dbxReport bauen; berechnete Spalten und Summen ueber den {fid}_next_record-Default umsetzen, Footerwerte spaet per add_rep() setzen und {rpt:colspan} nutzen. Keine reine str_replace()-Footermethode und keine unnoetigen Callback-Setter. Mutierende Standardaktionen werden automatisch signiert. Schreibende Grid-Routen verwenden *_grid_save, *_grid_insert, *_grid_delete, *_grid_sort oder *_grid_sync; Read bleibt tokenlos.',
         'Keine Modulmethoden anlegen, die lediglich einen dbx()- oder get_system_obj()-Aufruf weiterreichen.',
         'Nicht pauschal escapen. Nur an einer echten Ausgabe- oder Syntaxgrenze passend zum Zielkontext behandeln.',
         'GET bleibt fuer Navigation und reine Anzeige erhalten. delete und save werden in dbx_run1/dbx_run2/dbx_run3/dbx_do zusammen mit rid automatisch erkannt; den Link nur mit dbx()->action_url($url) signieren. Keine action_routes-Konfiguration fuer diese Standardfaelle, keine manuellen Scopes und keine check_action_token()-Pruefung im Modulservice.',
         'Ajax und normaler POST muessen denselben dbxForm-Ablauf verwenden und beide getestet werden.',
         'Bestehende Tests erweitern oder einen fokussierten Vertrags-/Integrationstest im Modul ergaenzen.',
         'Modul-README und Doxygen-Kommentare aktualisieren, wenn Verhalten, DD, Formular, Report oder API geaendert werden.',
         'Das mitgelieferte myInvoices-Modul ist die ausfuehrbare Architektur-Referenz; Zielmodul und seine bestehenden Konventionen bleiben fuer Fachverhalten massgeblich.',
      );
   }

   private function dbxappWay(): array {
      return array(
         'module_entry' => 'dbx/modules/{module}/{module}.class.php routet dbx_run1 und delegiert an include Services.',
         'service_code' => 'Fachlogik liegt unter include/*.class.php im Modul.',
         'dd' => 'Data Dictionary liegt unter dd/*.dd.php und beschreibt TABLE, FIELDS und INDEXES explizit im dbxapp-Exportformat. Jedes Feld wird direkt ueber $field[...] und $fields[]=$field definiert.',
         'fd' => 'Form-/Report-Felder liegen unter fd/*.fd.php.',
         'templates' => 'Templates liegen unter tpl/htm und werden per dbxTPL get_tpl gerendert.',
         'sqlite' => '*.db3 fuer ein Modul bleibt im Modulkontext; DD->DB Sync ueber dbxAdmin Schema.',
         'database' => 'Fachliche Abfragen und Aenderungen laufen ausschliesslich ueber dbxDB mit DD; Auditfelder setzt dbxDB automatisch.',
         'create_update' => 'dbxForm speichert neue Datensaetze als Insert und arbeitet danach mit der gelieferten RID weiter, sodass derselbe Dialog Updates ausfuehrt.',
         'form_security' => 'Normale POST-Form-Routen verwenden den rotierenden dbxForm-Submit-Schutz. Modulcode fuegt keinen dbx_token hinzu; nur eine als delete/save plus rid erkannte Action wird zentral signiert.',
         'navigation' => 'GET fuer Navigation/Anzeige beibehalten; delete/save plus rid werden von action_url automatisch als mutierende Link-Aktion erkannt.',
         'action_security' => 'dbxWebApp bindet die RID und prueft automatisch erkannte Routen vor dem Modulstart; Fachservices bauen oder pruefen keine eigenen Link-Token-Scopes.',
         'grid_security' => 'dbxReport signiert Grid-Save, -Insert, -Delete, -Sort und -Sync anhand der eigentlichen *_grid_<aktion>-Route. Unbekannte Schreibkonventionen werden nicht unsigniert ausgegeben.',
         'callbacks' => 'dbxForm uebernimmt den direkten Aufrufer als Owner; dbxReport nutzt {fid}_{event}-Defaults. Summen im next_record-Callback akkumulieren, spaet per add_rep() setzen und {rpt:colspan} verwenden.',
         'system_objects' => 'dbx-Systemobjekte am Verwendungsort holen; keine privaten Methoden, die nur dbx()-Aufrufe kapseln.',
         'reference' => 'reference/myInvoices und reference/25_Verbindliches_Modulhandbuch.md zeigen den verbindlichen Standard ausfuehrbar.',
      );
   }

   private function referenceStandard(): array {
      return array(
         'manual_source' => '25_Verbindliches_Modulhandbuch.md',
         'reference_module_source' => 'dbx/modules/myInvoices',
         'export_manual' => 'reference/25_Verbindliches_Modulhandbuch.md',
         'export_module' => 'reference/myInvoices',
         'purpose' => 'Ausfuehrbare Referenz fuer dbxTPL, dbxDB, dbxForm, DD, FD, dbxReport, Callbacks, Ajax, Confirm, zentrale Action-Policies und Tests.',
         'priority' => 'Die Referenz bestimmt Architektur und Vorgehen; vorhandenes Fachverhalten des Zielmoduls darf nicht unbeabsichtigt veraendert werden.',
      );
   }

   private function apiCatalog(): array {
      return array(
         'system.describe' => array('method' => 'GET/POST', 'params' => array(), 'result' => 'Regeln, Aktionen, Antwort-ZIP-Vertrag'),
         'module.describe' => array('params' => array('xmodul'), 'result' => 'Regeln und Kontext fuer ein Modul'),
         'module.snapshot' => array('params' => array('xmodul', 'with_content=0|1'), 'result' => 'Dateibaum, optional kleine Textdateien mit Inhalt'),
         'module.file.read' => array('params' => array('xmodul', 'path'), 'result' => 'Einzelne Textdatei innerhalb des Moduls'),
         'module.pipeline_guide' => array('params' => array('xmodul', 'task_type'), 'result' => 'Verbindliches Job-Schema fuer Modul-Aenderungen'),
         'module.job.execute' => array('method' => 'POST JSON', 'params' => array('manifest', 'job'), 'result' => 'Fuehrt ein Modul-job.json direkt ueber dieselbe Pipeline aus'),
      );
   }

   private function answerZipContract(string $modul): array {
      return array(
         'filename' => 'antwort.zip',
         'required_files' => array('manifest.json', 'job.json', 'README.md'),
         'allowed_payload_paths' => array('dbx/modules/' . $modul . '/**'),
         'manifest' => array(
            'area' => 'module',
            'module' => $modul,
            'recipe' => 'module.update.v1',
            'auto_execute' => true,
         ),
         'job_actions' => array(
            'module.backup',
            'module.file.write',
            'module.file.delete',
            'module.dd.write',
            'module.dd.sync',
            'module.template.set',
            'module.asset.write',
         ),
      );
   }

   private function modulePipeline(string $modul): array {
      return array(
         'principle' => 'Die KI liefert nur manifest.json, job.json und optionale assets. dbxKi validiert und fuehrt alle Aenderungen ueber eigene Modul-API-Funktionen aus.',
         'transport' => array(
            'external_ki' => 'Antwort-ZIP unter ?dbx_modul=dbxKi&dbx_run1=module_bundle importieren.',
            'codex_direct' => 'Gleiches manifest/job/assets-Schema direkt an dbxKi uebergeben; kein eigener Schreibweg.',
         ),
         'sequence' => array(
            'module.describe oder module.pipeline_guide lesen.',
            'reference/25_Verbindliches_Modulhandbuch.md und reference/myInvoices als verbindlichen Architekturstandard lesen.',
            'Bestehenden Modulkontext aus module.snapshot/module.file.read nutzen.',
            'job.json mit festen module.* Actions fuellen.',
            'dbxKi legt automatisch ein Modul-Backup an, wenn Schreibaktionen enthalten sind.',
            'dbxKi schreibt Source/DD/Templates/CSS/Assets ausschliesslich innerhalb dbx/modules/' . $modul . '/.',
            'DD->DB Sync nur ueber module.dd.sync und nur fuer ' . $modul . '|{dd}.',
            'Zu geaendertem Verhalten passende Modul-, Vertrags- und Integrationstests mitliefern oder aktualisieren.',
            'Nach dem Import PHP-Syntax, Modultests, normale POST-Antwort und Ajax-Ablauf pruefen.',
         ),
      );
   }

   private function uiContract(): array {
      return array(
         'openWin' => array(
            'rule' => 'Fenster nur ueber dbxapp openWin/data-dbx konfigurieren, kein eigenes JS.',
            'html' => '<a class="dbx-win" data-dbx="lib=openWin|url=...|title=...|width=900|height=80%|position=center-top|reload=1|minimizable=1|maximizable=1">...</a>',
         ),
         'ajax' => array(
            'rule' => 'AJAX-Formulare/Reports ueber vorhandene dbxAjax/dbxReport/dbxForm Muster; JSON-Aktionen als dbx()->json_response.',
            'state' => 'UI-State ueber dbx()->get_remember_var / dbx()->set_remember_var, nicht ueber eigene Session-Strukturen.',
         ),
         'confirm' => array(
            'rule' => 'Loesch-/kritische Aktionen mit dbxConfirm oder bestehendem Confirm-Template, keine Browser-confirm()-Eigenlogik.',
         ),
         'process' => array(
            'rule' => 'Laengere Jobs als dbx process/openWin Prozess, nicht als blockierende UI mit eigenem Polling.',
         ),
      );
   }

   private function dbxApiContract(string $modul): array {
      return array(
         'templates' => 'Lesen/rendern ueber dbx()->get_system_obj("dbxTPL")->get_tpl("' . $modul . '|name", $data); Schreiben nur ueber module.template.set.',
         'forms' => 'dbxForm verwenden: init(), add_flds(), save_post() und Callbacks. Nach Insert die neue RID fuer Action/Formzustand setzen, damit der naechste Submit aktualisiert. Kein manuell gesetzter dbx_token; dbxForm signiert nur automatisch erkannte delete/save-RID-Actions.',
         'reports' => 'dbxReport verwenden: init(), {fid}_{event}-Callback-Defaults, spaete add_rep()-Footerwerte, {rpt:colspan}, Remember-Multi-Select, Multi-Delete, Edit, Detail und Row-Delete. Explizite Owner-/Callback-Setter und reine str_replace()-Footer vermeiden. Mutierende Standardaktionen automatisch signieren lassen; Grid-Schreibendpunkte nach *_grid_save, *_grid_insert, *_grid_delete, *_grid_sort oder *_grid_sync benennen.',
         'dd' => 'DD-Dateien vollstaendig und direkt lesbar im dbxapp-Exportformat schreiben: $table[...], $field[...], $fields[]=$field, $index[...] und $indexes[]=$index. Keine $addField-Closure. Sync ueber module.dd.sync.',
         'db' => 'DB-Zugriff ausschliesslich ueber dbx()->get_system_obj("dbxDB") und DD-Namen; kein PDO, mysqli, SQL oder direkter db3-Zugriff.',
         'audit' => 'create_date, create_uid, update_date, update_uid und owner nicht uebergeben; dbxDB setzt sie aus DD und Sitzung.',
         'objects' => 'dbxTPL, dbxDB, dbxForm und dbxReport am Verwendungsort abrufen; keine reinen dbx()-Wrapper.',
         'escaping' => 'Kein pauschales Escaping interner Werte; nur an der konkreten HTML-, URL-, JSON- oder Dateiausgabegrenze passend behandeln.',
         'get' => 'GET fuer Navigation/Anzeige beibehalten; delete/save plus rid werden ohne action_routes-Konfiguration erkannt. Links beim Rendern ueber dbx()->action_url($url) fuehren; dbxWebApp prueft vor dem Modulstart.',
         'action_links' => 'Keine manuellen action_token()-Scopes und keine check_action_token()-Pruefung im Modulservice. Modul- und DD-Berechtigung bleiben zusaetzlich verbindlich.',
         'state' => 'Persistenter UI-State ueber remember vars.',
      );
   }

   public function renderBriefing(): string {
      $selected = dbx()->get_modul_var('xmodul', '', 'parameter');
      $dd = dbx()->get_modul_var('dd_name', '', 'parameter');
      $taskType = dbx()->get_modul_var('task_type', 'update', 'parameter');
      $includeContext = dbx()->get_modul_var('include_context', 'full', 'parameter');
      $export = $this->moduleUrl('briefing_module_export');
      $api = $this->moduleUrl('module_api', array('action' => 'system.describe'));
      $bundle = $this->moduleUrl('module_bundle');
      $taskOptions = $this->selectOptions(array(
         'update' => 'Bestehendes Modul bearbeiten / aktualisieren',
         'extend' => 'Bestehendes Modul erweitern',
         'repair' => 'Fehler im Modul reparieren',
         'form_report' => 'Formular/Report erweitern',
         'api' => 'Modul-API erweitern',
         'refactor' => 'Intern aufraeumen',
      ), $taskType);
      $contextOptions = $this->selectOptions(array(
         'full' => 'Komplettes Modul ins ZIP',
         'tree' => 'Nur Dateibaum + Regeln',
      ), $includeContext);

      $actions = '<a class="btn btn-success btn-sm" href="' . $this->esc($bundle) . '"><i class="bi bi-upload"></i> Antwort importieren</a>'
         . '<a class="btn btn-outline-secondary btn-sm" target="_blank" href="' . $this->esc($api) . '"><i class="bi bi-braces"></i> API</a>';
      $help = dbx()->get_include_obj('dbxKiHelp', 'dbxKi');
      $data = array_merge(array(
         'export_url' => $this->esc($export),
         'bundle_url' => $this->esc($bundle),
         'api_url' => $this->esc($api),
         'module_options' => $this->moduleOptions($selected),
         'dd_name' => $this->esc($dd),
         'task_options' => $taskOptions,
         'context_options' => $contextOptions,
      ), $help->moduleBarTemplateData('briefing_module', $actions));

      return $this->managedForm(
         'ki-module-briefing',
         'ki-module-briefing',
         $export,
         $data
      )->run();
   }

   public function renderBundleStart(): string {
      $back = $this->moduleUrl('briefing_module');
      $action = $this->moduleUrl('module_bundle_import');
      $barActions = '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($back) . '"><i class="bi bi-arrow-left"></i> Modul-KI</a>';
      $help = dbx()->get_include_obj('dbxKiHelp', 'dbxKi');
      $data = array_merge(array(
         'import_url' => $this->esc($action),
         'back_url' => $this->esc($back),
      ), $help->moduleBarTemplateData('module_bundle', $barActions));

      return $this->managedForm(
         'ki-module-bundle-import',
         'ki-module-bundle-import',
         $action,
         $data
      )->run();
   }

   public function handleExport(): void {
      $form = $this->managedForm(
         'ki-module-briefing',
         'ki-module-briefing',
         $this->moduleUrl('briefing_module_export')
      );
      if (!$form->submit()) {
         dbx()->json_response(array('ok' => 0, 'error' => 'Ungueltiger oder abgelaufener Formular-Token.'), true);
      }

      $modul = dbx()->get_request_var('xmodul', '', 'parameter');
      if (!$this->validName($modul) || !is_dir($this->moduleDir($modul))) {
         dbx()->json_response(array('ok' => 0, 'error' => 'Ungueltiges oder fehlendes Modul'), true);
      }
      $dd = dbx()->get_request_var('dd_name', '', 'parameter');
      $brief = dbx()->get_request_var('brief', '', '*');
      $taskType = dbx()->get_request_var('task_type', 'update', 'parameter');
      $includeContext = dbx()->get_request_var('include_context', 'full', 'parameter');

      $manifest = $this->moduleDescribe($modul);
      $briefing = array(
         'briefing_version' => self::MODULE_BRIEFING_VERSION,
         'area' => 'module',
         'module' => $modul,
         'dd' => $dd,
         'task_type' => $taskType,
         'brief' => $brief,
         'rules' => $this->hardRules($modul),
         'pipeline' => $this->modulePipeline($modul),
         'ui_contract' => $this->uiContract(),
         'dbx_api_contract' => $this->dbxApiContract($modul),
         'reference_standard' => $this->referenceStandard(),
      );
      $job = array(
         'job_version' => self::MODULE_BRIEFING_VERSION,
         'area' => 'module',
         'module' => $modul,
         'steps' => array(
            array('id' => 'backup', 'action' => 'module.backup', 'params' => array('module' => $modul)),
            array('id' => 'change_1', 'action' => 'module.file.write', 'params' => array('module' => $modul, 'path' => 'dbx/modules/' . $modul . '/___PFAD___', 'content' => '___KI_FUELLEN___')),
         ),
      );
      $answerManifest = array(
         'bundle_version' => self::MODULE_BRIEFING_VERSION,
         'area' => 'module',
         'module' => $modul,
         'recipe' => 'module.update.v1',
         'task_type' => $taskType,
         'auto_execute' => true,
      );

      $files = array(
         '00-START.md' => $this->startText($modul),
         'KI-AUFTRAG.md' => $this->auftragText($modul, $dd, $brief),
         'briefing.json' => json_encode($briefing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
         'job.vorlage.json' => json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
         'manifest.vorlage.json' => json_encode($answerManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
         'module.describe.json' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
         'module.snapshot.json' => json_encode(array('module' => $modul, 'files' => $this->fileTree($modul, false)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
         'README.md' => 'dbxKi Modul-Auftrag fuer ' . $modul . "\n",
         'reference/README.md' => $this->referenceText(),
      );

      $tmp = tempnam(sys_get_temp_dir(), 'dbxkimod');
      $zip = new \ZipArchive();
      if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
         dbx()->json_response(array('ok' => 0, 'error' => 'ZIP konnte nicht erstellt werden'), true);
      }
      foreach ($files as $path => $content) {
         $zip->addFromString($path, (string)$content);
      }
      if ($includeContext === 'full') {
         $this->addModuleToZip($zip, $modul);
      }
      $this->addReferenceToZip($zip);
      $zip->close();
      $name = 'dbxki-modul-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $modul) . '.zip';
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="' . $name . '"');
      header('Content-Length: ' . filesize($tmp));
      readfile($tmp);
      @unlink($tmp);
      exit;
   }

   private function addModuleToZip(\ZipArchive $zip, string $modul): void {
      $dir = $this->moduleDir($modul);
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::SELF_FIRST
      );
      foreach ($it as $item) {
         $full = $item->getPathname();
         $rel = str_replace('\\', '/', substr($full, strlen(rtrim($dir, '/\\')) + 1));
         $zipRel = 'module_context/dbx/modules/' . $modul . '/' . $rel;
         if ($item->isDir()) {
            $zip->addEmptyDir($zipRel);
         } else {
            $zip->addFile($full, $zipRel);
         }
      }
   }

   /**
    * Legt den verbindlichen Modulstandard lesend in jedes Auftrags-ZIP.
    *
    * Die Referenz ist kein zweites Zielmodul. Sie zeigt nur die erwartete
    * Architektur und wird deshalb ohne Laufzeitdatenbanken exportiert.
    */
   private function addReferenceToZip(\ZipArchive $zip): void {
      $manual = dbx()->os_path(dbx()->get_base_dir() . '25_Verbindliches_Modulhandbuch.md');
      if (is_file($manual)) {
         $zip->addFile($manual, 'reference/25_Verbindliches_Modulhandbuch.md');
      }

      $dir = $this->moduleDir('myInvoices');
      if (!is_dir($dir)) {
         return;
      }
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::SELF_FIRST
      );
      foreach ($it as $item) {
         if (!$item->isFile()) {
            continue;
         }
         $full = $item->getPathname();
         $rel = str_replace('\\', '/', substr($full, strlen(rtrim($dir, '/\\')) + 1));
         if (str_starts_with($rel, 'db/') || !$this->isTextFile($full) || $item->getSize() > 262144) {
            continue;
         }
         $zip->addFile($full, 'reference/myInvoices/' . $rel);
      }
   }

   private function referenceText(): string {
      return "# Verbindlicher dbXapp-Modulstandard\n\n"
         . "- `25_Verbindliches_Modulhandbuch.md` beschreibt Architektur und Vorgehen.\n"
         . "- `myInvoices/` ist die ausfuehrbare Referenz fuer DD/FD, dbxDB, dbxTPL, dbxForm, dbxReport, Callbacks, Ajax, Confirm, zentrale Action-Policies und Tests.\n"
         . "- Die Referenz ist nur lesbar. Aenderungen duerfen ausschliesslich das im Manifest genannte Zielmodul betreffen.\n"
         . "- Bestehendes Fachverhalten und vorhandene Schnittstellen des Zielmoduls haben Vorrang vor einer blinden Kopie des Beispiels.\n";
   }

   private function startText(string $modul): string {
      return "# START\n\n"
         . "1. Lies `KI-AUFTRAG.md`.\n"
         . "2. Lies `briefing.json`, `module.describe.json`, `module.snapshot.json`.\n"
         . "3. Lies `reference/25_Verbindliches_Modulhandbuch.md` und `reference/myInvoices/` als Architekturstandard.\n"
         . "4. Nutze `module_context/dbx/modules/$modul/` als Wahrheit fuer bestehendes Fachverhalten und Schnittstellen.\n"
         . "5. Liefere `antwort.zip` mit `manifest.json`, `job.json`, optional `assets/` und README.\n"
         . "6. Keine eigenen Tools, kein SQL, keine freie Dateiliste. Nur die module.* Actions aus `module.describe.json` verwenden.\n";
   }

   private function auftragText(string $modul, string $dd, string $brief): string {
      return "# KI-Auftrag Modulprogrammierung\n\n"
         . "Modul: `$modul`\nDD: `$dd`\n\n"
         . "## Aufgabe\n\n" . trim($brief) . "\n\n"
         . "## Verbindliche Regeln\n\n- " . implode("\n- ", $this->hardRules($modul)) . "\n\n"
         . "## UI/API Regeln\n\n"
         . "- UI-State ueber remember vars, openWin ueber data-dbx, AJAX ueber dbxAjax/json_response, Confirm ueber dbxConfirm.\n"
          . "- Templates mit dbxTPL lesen/rendern; Antwort schreibt Templates nur via `module.template.set`.\n"
          . "- DDs im direkt lesbaren dbxapp-Exportformat mit expliziten TABLE-, FIELDS- und INDEXES-Abschnitten schreiben; keine `\$addField`-Closure.\n"
          . "- Nach einem Insert die neue RID in dbxForm und Action uebernehmen; erneutes Speichern muss ein Update sein.\n"
          . "- dbxForm-Actions nie manuell mit dbx_token versehen; nur automatisch erkannte delete/save-RID-Actions werden vom System signiert.\n"
          . "- dbxReport-Summen im {fid}_next_record-Default bilden, Footerwerte spaet per add_rep() setzen und {rpt:colspan} nutzen; keine unnoetigen Callback-Setter.\n"
          . "- Schreibende dbxReport-Grid-Routen als `*_grid_save`, `*_grid_insert`, `*_grid_delete`, `*_grid_sort` oder `*_grid_sync` benennen; keinen eigenen Tokenmarker anfuegen.\n"
          . "- delete/save plus rid ohne action_routes-Konfiguration ueber action_url() automatisch signieren und keine Token-Pruefung im Fachservice duplizieren.\n"
         . "- DD->DB Sync nur via `module.dd.sync` und nur fuer `$modul|$dd`.\n\n"
         . "## Referenz\n\n"
         . "- `reference/25_Verbindliches_Modulhandbuch.md` ist die verbindliche Anleitung.\n"
         . "- `reference/myInvoices/` ist das ausfuehrbare Beispiel. Nicht in das Zielmodul kopieren, sondern die passenden Muster anwenden.\n\n"
         . "## Erlaubte job.json Actions\n\n"
         . "- `module.backup`\n- `module.file.write`\n- `module.file.delete`\n- `module.dd.write`\n- `module.dd.sync`\n- `module.template.set`\n- `module.asset.write`\n\n"
         . "## Antwort\n\nLiefere `antwort.zip` mit `manifest.json`, `job.json`, optional `assets/` und README. `manifest.auto_execute` bleibt true. Keine Erklaerung statt ZIP.\n";
   }

   public function handleBundleImport(): string {
      $root = '';
      try {
         $form = $this->managedForm(
            'ki-module-bundle-import',
            'ki-module-bundle-import',
            $this->moduleUrl('module_bundle_import')
         );
         if (!$form->submit()) {
            throw new \RuntimeException('Ungueltiger oder abgelaufener Formular-Token.');
         }
         $payload = $this->readModuleBundlePayload();
         $manifest = is_array($payload['manifest'] ?? null) ? $payload['manifest'] : array();
         $job = is_array($payload['job'] ?? null) ? $payload['job'] : array();
         $assetsDir = (string)($payload['assets_dir'] ?? '');
         $root = (string)($payload['root'] ?? '');
         $this->validateModulePayload($manifest, $job);
         $result = $this->executeModuleJob($manifest, $job, $assetsDir);
         if ($root !== '') $this->removeDir($root);
         return $this->renderModuleBundleResult($result);
      } catch (\Throwable $e) {
         if ($root !== '') $this->removeDir($root);
         dbx()->sys_msg('error', 'dbxKi', 'module_bundle_import', 'Import fehlgeschlagen', $e->getMessage());
         return '<div class="container py-4"><div class="alert alert-danger">' . $this->esc($e->getMessage()) . '</div>'
            . '<a class="btn btn-secondary" href="' . $this->esc($this->moduleUrl('module_bundle')) . '">Zurueck</a></div>';
      }
   }

   private function readModuleBundlePayload(): array {
      $file = null;
      foreach ($_FILES as $upload) {
         if (is_array($upload) && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string)($upload['tmp_name'] ?? ''))) {
            $file = (string)$upload['tmp_name'];
            break;
         }
      }
      if (!$file) {
         throw new \InvalidArgumentException('Keine Antwort-ZIP hochgeladen.');
      }
      if (!class_exists('\\ZipArchive')) {
         throw new \RuntimeException('ZipArchive nicht verfuegbar.');
      }
      $root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'dbxki-module-' . bin2hex(random_bytes(8));
      if (!mkdir($root, 0777, true) && !is_dir($root)) {
         throw new \RuntimeException('Temp-Verzeichnis konnte nicht erstellt werden.');
      }
      $zip = new \ZipArchive();
      if ($zip->open($file) !== true) {
         $this->removeDir($root);
         throw new \InvalidArgumentException('ZIP konnte nicht geoeffnet werden.');
      }
      for ($i = 0; $i < $zip->numFiles; $i++) {
         $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
         if ($name === '' || strpos($name, '../') !== false || str_starts_with($name, '/')) {
            $zip->close();
            $this->removeDir($root);
            throw new \InvalidArgumentException('Ungueltiger ZIP-Pfad: ' . $name);
         }
      }
      $zip->extractTo($root);
      $zip->close();
      $manifest = $this->readJson($root . '/manifest.json', true);
      $job = $this->readJson($root . '/job.json', true);
      return array(
         'root' => $root,
         'assets_dir' => $root . '/assets',
         'manifest' => $manifest,
         'job' => $job,
      );
   }

   private function readJson(string $file, bool $required): array {
      if (!is_file($file)) {
         if ($required) throw new \InvalidArgumentException('Pflichtdatei fehlt: ' . basename($file));
         return array();
      }
      $data = json_decode((string)file_get_contents($file), true);
      if (!is_array($data)) {
         throw new \InvalidArgumentException('Ungueltiges JSON: ' . basename($file));
      }
      return $data;
   }

   private function removeDir(string $dir): void {
      if (!is_dir($dir)) return;
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ($it as $item) {
         $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
      }
      @rmdir($dir);
   }

   private function validateModulePayload(array $manifest, array $job): void {
      $modul = trim((string)($manifest['module'] ?? $job['module'] ?? ''));
      if (($manifest['area'] ?? '') !== 'module' || !$this->validName($modul) || !is_dir($this->moduleDir($modul))) {
         throw new \InvalidArgumentException('manifest.module ist ungueltig oder area ist nicht module.');
      }
      if (!is_array($job['steps'] ?? null) || !count($job['steps'])) {
         throw new \InvalidArgumentException('job.json benoetigt steps[].');
      }
      foreach ($job['steps'] as $pos => $step) {
         if (!is_array($step)) throw new \InvalidArgumentException('Step #' . ($pos + 1) . ' ist ungueltig.');
         $action = trim((string)($step['action'] ?? ''));
         if (!$this->moduleActionAllowed($action)) {
            throw new \InvalidArgumentException('Aktion nicht erlaubt: ' . $action);
         }
         $stepModule = trim((string)($step['params']['module'] ?? $modul));
         if ($stepModule !== $modul) {
            throw new \InvalidArgumentException('Step-Modul passt nicht zum Manifest: ' . $action);
         }
      }
   }

   private function moduleActionAllowed(string $action): bool {
      return in_array($action, array(
         'module.backup',
         'module.file.write',
         'module.file.delete',
         'module.dd.write',
         'module.dd.sync',
         'module.template.set',
         'module.asset.write',
      ), true);
   }

   private function executeModuleJob(array $manifest, array $job, string $assetsDir): array {
      $modul = trim((string)($manifest['module'] ?? $job['module'] ?? ''));
      $auto = $this->truthy($manifest['auto_execute'] ?? false);
      if (!$auto) {
         throw new \RuntimeException('manifest.auto_execute ist nicht true.');
      }

      $results = array();
      $needsBackup = false;
      foreach ($job['steps'] as $step) {
         if (($step['action'] ?? '') !== 'module.backup') {
            $needsBackup = true;
            break;
         }
      }
      if ($needsBackup) {
         $results['_auto_backup'] = $this->createModuleBackup($modul);
      }

      foreach ($job['steps'] as $pos => $step) {
         $id = trim((string)($step['id'] ?? ('step_' . ($pos + 1))));
         $params = $this->resolveModuleParams(is_array($step['params'] ?? null) ? $step['params'] : array(), $results);
         $params['module'] = $modul;
         $results[$id] = $this->executeModuleStep((string)$step['action'], $params, $assetsDir);
      }
      return array('ok' => 1, 'module' => $modul, 'results' => $results);
   }

   private function truthy($value): bool {
      if (is_bool($value)) return $value;
      return in_array(strtolower(trim((string)$value)), array('1', 'true', 'yes', 'ja', 'on'), true);
   }

   private function resolveModuleParams($value, array $results) {
      if (is_array($value)) {
         $out = array();
         foreach ($value as $key => $item) $out[$key] = $this->resolveModuleParams($item, $results);
         return $out;
      }
      if (!is_string($value) || strpos($value, '$ref:') === false) return $value;
      return preg_replace_callback('/\$ref:([A-Za-z0-9_.-]+)/', function($m) use ($results) {
         $parts = explode('.', $m[1]);
         $step = array_shift($parts);
         $v = $results[$step] ?? null;
         foreach ($parts as $part) {
            $v = is_array($v) && array_key_exists($part, $v) ? $v[$part] : '';
         }
         return (string)$v;
      }, $value);
   }

   private function executeModuleStep(string $action, array $params, string $assetsDir): array {
      switch ($action) {
         case 'module.backup':
            return $this->createModuleBackup((string)$params['module']);
         case 'module.file.write':
            return $this->moduleFileWrite($params, $assetsDir);
         case 'module.file.delete':
            return $this->moduleFileDelete($params);
         case 'module.dd.write':
            return $this->moduleDdWrite($params);
         case 'module.dd.sync':
            return $this->moduleDdSync($params);
         case 'module.template.set':
            return $this->moduleTemplateSet($params);
         case 'module.asset.write':
            return $this->moduleAssetWrite($params, $assetsDir);
      }
      throw new \InvalidArgumentException('Aktion nicht implementiert: ' . $action);
   }

   private function createModuleBackup(string $modul): array {
      $dir = dbx()->os_path(dbx()->get_file_dir() . 'temp/dbxKi/module-backups/');
      if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
         throw new \RuntimeException('Backup-Verzeichnis konnte nicht erstellt werden.');
      }
      $file = $dir . $modul . '-' . date('Ymd-His') . '.zip';
      $zip = new \ZipArchive();
      if ($zip->open($file, \ZipArchive::OVERWRITE) !== true) {
         throw new \RuntimeException('Modul-Backup konnte nicht erstellt werden.');
      }
      $this->addModuleToZip($zip, $modul);
      $zip->close();
      return array('backup' => str_replace('\\', '/', $file), 'size' => (int)@filesize($file));
   }

   private function relFromPath(string $modul, string $path): string {
      $path = str_replace(array('\\', "\0"), array('/', ''), trim($path));
      $prefix = 'dbx/modules/' . $modul . '/';
      if (strpos($path, $prefix) === 0) $path = substr($path, strlen($prefix));
      $path = ltrim($path, '/');
      if ($path === '' || strpos($path, '../') !== false || str_starts_with($path, '/')) {
         throw new \InvalidArgumentException('Ungueltiger Modulpfad.');
      }
      return $path;
   }

   private function targetFile(string $modul, string $path): string {
      $rel = $this->relFromPath($modul, $path);
      $file = $this->modulePath($modul, $rel);
      if ($file === '') throw new \InvalidArgumentException('Pfad ausserhalb des Moduls.');
      return $file;
   }

   private function bytesFromParams(array $params, string $assetsDir): string {
      if (array_key_exists('content', $params)) return (string)$params['content'];
      if (array_key_exists('content_base64', $params)) {
         $bytes = base64_decode((string)$params['content_base64'], true);
         if ($bytes === false) throw new \InvalidArgumentException('content_base64 ist ungueltig.');
         return $bytes;
      }
      $assetRef = trim(str_replace('\\', '/', (string)($params['asset_ref'] ?? '')));
      if ($assetRef !== '') {
         if (strpos($assetRef, '../') !== false || str_starts_with($assetRef, '/')) {
            throw new \InvalidArgumentException('asset_ref ist ungueltig.');
         }
         $file = rtrim($assetsDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetRef);
         if (!is_file($file)) throw new \InvalidArgumentException('Asset fehlt: ' . $assetRef);
         return (string)file_get_contents($file);
      }
      throw new \InvalidArgumentException('content, content_base64 oder asset_ref erforderlich.');
   }

   private function writeTargetFile(string $file, string $bytes): array {
      $dir = dirname($file);
      if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
         throw new \RuntimeException('Zielverzeichnis konnte nicht erstellt werden.');
      }
      if (file_put_contents($file, $bytes) === false) {
         throw new \RuntimeException('Datei konnte nicht geschrieben werden.');
      }
      return array('path' => str_replace('\\', '/', $file), 'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes));
   }

   private function moduleFileWrite(array $params, string $assetsDir): array {
      $modul = (string)$params['module'];
      $rel = $this->relFromPath($modul, (string)($params['path'] ?? ''));
      if (preg_match('#^(dd/|tpl/|img/|css/|js/)#i', $rel)) {
         throw new \InvalidArgumentException('Fuer DD/Templates/Assets die spezialisierten module.* Actions verwenden.');
      }
      if (preg_match('/\.(db3|sqlite|sqlite3)$/i', $rel)) {
         throw new \InvalidArgumentException('Datenbanken werden nicht direkt geschrieben. DD->DB Sync ueber module.dd.sync verwenden.');
      }
      $file = $this->targetFile($modul, $rel);
      return $this->writeTargetFile($file, $this->bytesFromParams($params, $assetsDir));
   }

   private function moduleFileDelete(array $params): array {
      if (!$this->truthy($params['confirm'] ?? false)) {
         throw new \InvalidArgumentException('module.file.delete benoetigt confirm=true.');
      }
      $modul = (string)$params['module'];
      $file = $this->targetFile($modul, (string)($params['path'] ?? ''));
      if (!is_file($file)) return array('deleted' => false, 'path' => str_replace('\\', '/', $file));
      if (!unlink($file)) throw new \RuntimeException('Datei konnte nicht geloescht werden.');
      return array('deleted' => true, 'path' => str_replace('\\', '/', $file));
   }

   private function moduleDdWrite(array $params): array {
      $modul = (string)$params['module'];
      $dd = trim((string)($params['dd'] ?? ''));
      if (!$this->validName($dd)) throw new \InvalidArgumentException('Ungueltiger DD-Name.');
      $content = (string)($params['content'] ?? '');
      if ($content === '' || preg_match('/\b(require|include)(_once)?\b/i', $content)) {
         throw new \InvalidArgumentException('DD muss vollstaendig sein und darf keine include/require verwenden.');
      }
      $file = $this->targetFile($modul, 'dd/' . $dd . '.dd.php');
      return $this->writeTargetFile($file, $content);
   }

   private function moduleTemplateSet(array $params): array {
      $modul = (string)$params['module'];
      $template = trim((string)($params['template'] ?? ''));
      $path = trim((string)($params['path'] ?? ''));
      if ($path === '') {
         $template = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $template);
         if ($template === '') throw new \InvalidArgumentException('template ist erforderlich.');
         if (!preg_match('/\.htm[l]?$/i', $template)) $template .= '.htm';
         $path = 'tpl/htm/' . $template;
      }
      if (strpos(str_replace('\\', '/', $path), 'tpl/') !== 0) {
         throw new \InvalidArgumentException('module.template.set darf nur unter tpl/ schreiben.');
      }
      $file = $this->targetFile($modul, $path);
      return $this->writeTargetFile($file, (string)($params['content'] ?? ''));
   }

   private function moduleAssetWrite(array $params, string $assetsDir): array {
      $modul = (string)$params['module'];
      $path = $this->relFromPath($modul, (string)($params['path'] ?? ''));
      if (!preg_match('#^(tpl/(img|mod)/|img/|css/|js/)#i', $path)) {
         throw new \InvalidArgumentException('module.asset.write darf nur tpl/img, tpl/mod, img, css oder js schreiben.');
      }
      return $this->writeTargetFile($this->targetFile($modul, $path), $this->bytesFromParams($params, $assetsDir));
   }

   private function moduleDdSync(array $params): array {
      $modul = (string)$params['module'];
      $dd = trim((string)($params['dd'] ?? ''));
      if (!$this->validName($dd)) throw new \InvalidArgumentException('Ungueltiger DD-Name.');
      if (!is_file($this->targetFile($modul, 'dd/' . $dd . '.dd.php'))) {
         throw new \InvalidArgumentException('DD-Datei fehlt: ' . $modul . '|' . $dd);
      }
      $oDD = dbx()->get_system_obj('dbxDD');
      if (!is_object($oDD) || !method_exists($oDD, 'sync_dd_to_db')) {
         throw new \RuntimeException('dbxDD Sync-API nicht verfuegbar.');
      }
      $oDD->sync_dd_to_db($modul, $dd, 'reset');
      $state = array();
      for ($i = 0; $i < 80; $i++) {
         $state = $oDD->sync_dd_to_db($modul, $dd, 'apply');
         $status = strtolower((string)($state['status'] ?? ''));
         if (in_array($status, array('finished', 'error', 'canceled'), true)) break;
         if ((int)($state['percent'] ?? 0) >= 100) break;
      }
      return array('dd' => $modul . '|' . $dd, 'state' => $state);
   }

   private function renderModuleBundleResult(array $result): string {
      $items = '';
      foreach (is_array($result['results'] ?? null) ? $result['results'] : array() as $id => $row) {
         $items .= '<li class="list-group-item"><strong>' . $this->esc($id) . '</strong><pre class="small mb-0">'
            . $this->esc(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            . '</pre></li>';
      }
      return '<div class="container py-4" style="max-width:1100px"><div class="alert alert-success">Modul-Bundle ausgefuehrt: '
         . $this->esc((string)($result['module'] ?? '')) . '</div><ul class="list-group mb-3">' . $items . '</ul>'
         . '<a class="btn btn-primary" href="' . $this->esc($this->moduleUrl('briefing_module', array('xmodul' => (string)($result['module'] ?? '')))) . '">Zur Modul-KI</a></div>';
   }

   private function modulePipelineGuide(string $modul, string $taskType = 'update'): array {
      return array(
         'workflow' => $this->modulePipeline($modul),
         'rules' => $this->hardRules($modul),
         'ui_contract' => $this->uiContract(),
         'dbx_api_contract' => $this->dbxApiContract($modul),
         'reference_standard' => $this->referenceStandard(),
         'manifest' => array(
            'bundle_version' => self::MODULE_BRIEFING_VERSION,
            'area' => 'module',
            'module' => $modul,
            'recipe' => 'module.update.v1',
            'task_type' => $taskType,
            'auto_execute' => true,
         ),
         'job' => array(
            'job_version' => self::MODULE_BRIEFING_VERSION,
            'area' => 'module',
            'module' => $modul,
            'steps' => array(
               array('id' => 'backup', 'action' => 'module.backup', 'params' => array('module' => $modul)),
               array('id' => 'write_source', 'action' => 'module.file.write', 'params' => array('module' => $modul, 'path' => 'dbx/modules/' . $modul . '/include/Service.class.php', 'content' => '___PHP_ODER_TEXT_CONTENT___')),
               array('id' => 'write_template', 'action' => 'module.template.set', 'params' => array('module' => $modul, 'template' => 'template-name', 'content' => '___HTML_TEMPLATE___')),
               array('id' => 'write_dd', 'action' => 'module.dd.write', 'params' => array('module' => $modul, 'dd' => 'ddName', 'content' => '___VOLLSTAENDIGE_DD_DATEI___')),
               array('id' => 'sync_dd', 'action' => 'module.dd.sync', 'params' => array('module' => $modul, 'dd' => 'ddName')),
            ),
         ),
      );
   }

   public function handleApi(): void {
      $raw = file_get_contents('php://input');
      $body = array();
      if (is_string($raw) && trim($raw) !== '') {
         $decoded = json_decode($raw, true);
         if (is_array($decoded)) $body = $decoded;
      }
      $params = is_array($body['params'] ?? null) ? $body['params'] : array();
      $action = (string)($body['action'] ?? dbx()->get_request_var('action', 'system.describe', 'parameter+.'));
      $modul = (string)($params['xmodul'] ?? $params['module'] ?? dbx()->get_request_var('xmodul', '', 'parameter'));
      try {
         switch ($action) {
            case 'system.describe':
               dbx()->json_response($this->moduleDescribe(''), true);
               return;
            case 'module.describe':
               if (!$this->validName($modul)) throw new \InvalidArgumentException('Ungueltiges Modul');
               dbx()->json_response($this->moduleDescribe($modul), true);
               return;
            case 'module.snapshot':
               if (!$this->validName($modul)) throw new \InvalidArgumentException('Ungueltiges Modul');
               $withContent = (int)($params['with_content'] ?? dbx()->get_request_var('with_content', 0, 'int')) === 1;
               dbx()->json_response(array('ok' => 1, 'module' => $modul, 'files' => $this->fileTree($modul, $withContent)), true);
               return;
            case 'module.pipeline_guide':
               if (!$this->validName($modul)) throw new \InvalidArgumentException('Ungueltiges Modul');
               $taskType = (string)($params['task_type'] ?? dbx()->get_request_var('task_type', 'update', 'parameter'));
               dbx()->json_response(array('ok' => 1, 'result' => $this->modulePipelineGuide($modul, $taskType)), true);
               return;
            case 'module.job.execute':
               $manifest = is_array($params['manifest'] ?? null) ? $params['manifest'] : array();
               $job = is_array($params['job'] ?? null) ? $params['job'] : array();
               if (!$manifest && is_array($body['manifest'] ?? null)) $manifest = $body['manifest'];
               if (!$job && is_array($body['job'] ?? null)) $job = $body['job'];
               $this->validateModulePayload($manifest, $job);
               dbx()->json_response($this->executeModuleJob($manifest, $job, ''), true);
               return;
            case 'module.file.read':
               if (!$this->validName($modul)) throw new \InvalidArgumentException('Ungueltiges Modul');
               $path = (string)($params['path'] ?? dbx()->get_request_var('path', '', '*'));
               $prefix = 'dbx/modules/' . $modul . '/';
               if (strpos($path, $prefix) === 0) {
                  $path = substr($path, strlen($prefix));
               }
               $file = $this->modulePath($modul, $path);
               if ($file === '' || !is_file($file) || !$this->isTextFile($file)) {
                  throw new \InvalidArgumentException('Datei nicht lesbar');
               }
               dbx()->json_response(array('ok' => 1, 'module' => $modul, 'path' => 'dbx/modules/' . $modul . '/' . str_replace('\\', '/', $path), 'content' => (string)file_get_contents($file)), true);
               return;
         }
         throw new \InvalidArgumentException('Unbekannte action');
      } catch (\Throwable $e) {
         dbx()->json_response(array('ok' => 0, 'error' => $e->getMessage()), true);
      }
   }
}
