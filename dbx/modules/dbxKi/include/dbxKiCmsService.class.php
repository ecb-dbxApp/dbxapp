<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;
use dbx\dbxContent\dbxContentHome;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;
use dbx\dbxContent\dbxContentRenderer;
use dbx\dbxContent\dbxContentTranslate;
use dbx\dbxContent\dbxContent_permalink;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

class dbxKiCmsService {

   private const TOKEN_SCOPE = 'dbxKi.cms.execute';
   private const API_VERSION = '0.1';

   private $db;

   public function __construct() {
      $this->db = dbx()->get_system_obj('dbxDB');
   }

   public function handle(string $route = 'api'): void {
      try {
         $request = $this->request();
         if ($route === 'describe') {
            $request['action'] = 'system.describe';
         } elseif ($route === 'preview') {
            $request['mode'] = 'preview';
         } elseif ($route === 'execute') {
            $request['mode'] = 'execute';
         }

         $action = strtolower(trim((string)($request['action'] ?? 'system.describe')));
         $mode = strtolower(trim((string)($request['mode'] ?? 'preview')));
         $params = is_array($request['params'] ?? null) ? $request['params'] : array();

         if ($action === 'system.describe') {
            $this->respond($this->describe());
         }

         if ($action === 'bundle.describe') {
            $bundle = dbx()->get_include_obj('dbxKiBundleService', 'dbxKi');
            $this->respond($bundle->describeBundle());
         }

         if ($action === 'system.health') {
            $this->respond($this->health());
         }

         $catalog = $this->catalog();
         if (!isset($catalog[$action])) {
            $this->fail('unknown_action', 'Unbekannte Aktion.', array(
               'action' => $action,
               'available_actions' => array_keys($catalog),
            ));
         }

         $write = (bool)($catalog[$action]['write'] ?? false);
         if (!$write) {
            $result = $this->read_action($action, $params);
            $this->respond(array(
               'ok' => 1,
               'api_version' => self::API_VERSION,
               'action' => $action,
               'mode' => 'read',
               'result' => $result,
            ));
         }

         if (!in_array($mode, array('preview', 'execute'), true)) {
            $this->fail('invalid_mode', 'Erlaubte Modi sind preview und execute.');
         }

         $plan = $this->build_plan($action, $params);
         $planId = $this->plan_id($action, $plan);

         if ($mode === 'preview') {
            $this->respond(array(
               'ok' => 1,
               'api_version' => self::API_VERSION,
               'action' => $action,
               'mode' => 'preview',
               'will_execute' => false,
               'plan_id' => $planId,
               'plan' => $plan,
               'execute_request' => array(
                  'action' => $action,
                  'mode' => 'execute',
                  'token' => dbx()->action_token(self::TOKEN_SCOPE),
                  'expected_plan_id' => $planId,
                  'confirm' => (bool)($catalog[$action]['destructive'] ?? false),
                  'params' => $params,
               ),
            ));
         }

         $this->authorize_execute($request, (bool)($catalog[$action]['destructive'] ?? false));
         $expected = trim((string)($request['expected_plan_id'] ?? ''));
         if ($expected !== '' && !hash_equals($planId, $expected)) {
            $this->fail('plan_changed', 'Der aktuelle Plan stimmt nicht mehr mit expected_plan_id überein.', array(
               'expected_plan_id' => $expected,
               'current_plan_id' => $planId,
               'current_plan' => $plan,
            ));
         }

         $result = $this->execute_action($action, $params, $plan);
         dbx()->sys_msg(
            'info',
            'dbxKi',
            $action,
            'KI-CMS-Aktion ausgeführt',
            'uid=' . (int)dbx()->user() . ' plan=' . $planId
         );

         $this->respond(array(
            'ok' => 1,
            'api_version' => self::API_VERSION,
            'action' => $action,
            'mode' => 'execute',
            'executed' => true,
            'plan_id' => $planId,
            'result' => $result,
         ));
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxKi', 'api', 'Ausnahme', $e->getMessage());
         $this->fail('exception', $e->getMessage());
      }
   }

   private function request(): array {
      $request = array();
      $raw = file_get_contents('php://input');
      if (is_string($raw) && trim($raw) !== '') {
         $json = json_decode($raw, true);
         if (is_array($json)) {
            $request = $json;
         }
      }

      foreach (array('action', 'mode', 'token', 'expected_plan_id', 'confirm') as $key) {
         if (!array_key_exists($key, $request)) {
            $value = dbx()->get_request_var($key, null);
            if ($value !== null) {
               $request[$key] = $value;
            }
         }
      }

      if (!isset($request['params']) || !is_array($request['params'])) {
         $params = dbx()->get_request_var('params', array(), '*');
         if (is_string($params) && trim($params) !== '') {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : array();
         }
         $request['params'] = is_array($params) ? $params : array();
      }

      return $request;
   }

   private function respond(array $data): void {
      dbx()->json_response($data, true);
   }

   private function fail(string $code, string $message, array $details = array()): void {
      $this->respond(array(
         'ok' => 0,
         'api_version' => self::API_VERSION,
         'error' => array(
            'code' => $code,
            'message' => $message,
            'details' => $details,
         ),
      ));
   }

   private function authorize_execute(array $request, bool $destructive): void {
      if ((int)dbx()->get_config('dbxKi', 'allow_execute', 1) !== 1) {
         $this->fail('execute_disabled', 'Automatische Ausführung ist in der Modulkonfiguration deaktiviert.');
      }
      $token = trim((string)($request['token'] ?? ''));
      if (!dbx()->check_action_token(self::TOKEN_SCOPE, $token)) {
         $this->fail('invalid_token', 'Ungültiges oder abgelaufenes Aktions-Token.');
      }
      if ($destructive && !$this->bool_value($request['confirm'] ?? false)) {
         $this->fail('confirmation_required', 'Diese Aktion löscht Daten. Für automatische Ausführung confirm=true senden.');
      }
   }

   private function describe(): array {
      return array(
         'ok' => 1,
         'api_version' => self::API_VERSION,
         'module' => 'dbxKi',
         'purpose' => 'KI-optimierte Bedienung des dbXapp-CMS ohne direkten SQL-Zugriff.',
         'endpoint' => '?dbx_modul=dbxKi&dbx_run1=api',
         'authentication' => array(
            'read_and_preview' => 'Normale dbXapp-Modulberechtigung.',
            'execute' => 'Admin-Sitzung plus token.',
            'token' => dbx()->action_token(self::TOKEN_SCOPE),
            'token_scope' => self::TOKEN_SCOPE,
         ),
         'protocol' => array(
            'method' => 'GET oder POST; für komplexe Daten POST mit application/json verwenden.',
            'request' => array(
               'action' => 'Eine Aktion aus actions.',
               'mode' => 'preview oder execute; bei Leseaktionen wird mode ignoriert.',
               'params' => 'Aktionsparameter.',
               'token' => 'Nur für execute.',
               'expected_plan_id' => 'Optional. Verhindert Ausführung, falls sich der Plan seit preview geändert hat.',
               'confirm' => 'Bei Löschaktionen für execute zwingend true.',
            ),
            'automation' => array(
               'safe' => 'Erst preview aufrufen und execute_request aus der Antwort unverändert senden.',
               'direct' => 'Für vollautomatische Ausführung action, mode=execute, token und params direkt senden.',
               'rule' => 'Keine SQL-Befehle erzeugen. Ausschließlich diese Aktionen verwenden.',
            ),
         ),
         'page_workflows' => $this->page_workflows(),
         'languages' => dbxContentLngSync::accessibleLngs(),
         'actions' => $this->catalog(),
         'examples' => array(
            'preview_page_create' => array(
               'action' => 'page.create',
               'mode' => 'preview',
               'params' => array(
                  'lng' => 'de',
                  'folder_id' => 1,
                  'title' => 'Neue KI-Seite',
                  'content' => '<p>Inhalt</p>',
               ),
            ),
            'automatic_page_update' => array(
               'action' => 'page.update',
               'mode' => 'execute',
               'token' => dbx()->action_token(self::TOKEN_SCOPE),
               'params' => array(
                  'lng' => 'de',
                  'id' => 1,
                  'title' => 'Aktualisierter Titel',
               ),
            ),
            'translation' => array(
               'action' => 'translation.apply',
               'mode' => 'execute',
               'token' => dbx()->action_token(self::TOKEN_SCOPE),
               'params' => array(
                  'source_lng' => 'de',
                  'target_lng' => 'en',
                  'source_id' => 1,
                  'translation' => array(
                     'title' => 'Translated title',
                     'description' => 'Translated description',
                     'keywords' => 'translated, keywords',
                     'content' => '<p>Translated content</p>',
                     'seo_title' => 'Translated SEO title',
                  ),
               ),
            ),
         ),
      );
   }

   private function page_workflows(): array {
      return array(
         'contract' => array(
            'ki_role' => 'Die KI erzeugt nur JSON-Dateien und optionale Assets. dbxKi importiert, prueft und fuehrt alles aus.',
            'no_external_tools' => 'Keine eigenen PHP-, SQL-, Shell-, Python- oder Node-Tools fuer CMS-Aenderungen erzeugen.',
            'delivery' => 'antwort.zip mit manifest.json, job.json, optional assets/ und README.md; alternativ job_json im dbxKi-Importformular.',
            'auto_execute' => 'Wenn dbxKi nach erfolgreicher Pruefung automatisch ausfuehren soll: manifest.auto_execute = true setzen.',
         ),
         'page_create' => array(
            'guide_action' => 'page.create_guide',
            'sequence' => array(
               'Arbeitskontext mit cms.snapshot oder page.create_guide lesen.',
               'Neue Medien zuerst mit media.create_base64 oder media.create_image_variant als Step anlegen.',
               'page.create mit lng, folder_id, title, template, content, description, keywords, activ anlegen.',
               'Inline-Bilder im content immer mit $ref:{media_step}.inline_src und data-cms-media-id setzen.',
               'Verwendete Inline-/Gallery-/Hero-Medien mit media.assign zuordnen.',
               'dbxKi importiert job.json, validiert jeden Step und fuehrt den Prozess aus.',
            ),
             'fixed_rules' => array(
                'folder_id, lng und title aus dem Auftrag nicht eigenmaechtig aendern.',
                'template nur aus Auftrag/Guide verwenden; bei Root-Seiten nie parent verwenden.',
                'Kein SQL, keine direkten Dateipfade files/media/... in img src.',
                'HTML ist erlaubt; Bootstrap-5-Klassen sind erlaubt; kein eigenes JavaScript.',
                'Hero-Bilder unter img/hero, Gallery-Bilder unter img/gallery, normale Inline-Bilder unter img/images.',
                'Ein Seitenkopf mit Bild und ueberlagertem Text ist immer ein CMS-Hero: Bild per media.assign slot=hero und hero_image_id/hero_template setzen; Hero-Text vor den dbx:hero-Marker schreiben.',
                'Niemals einen Hero als Inline-Bild mit position-relative/position-absolute im Content nachbauen.',
             ),
         ),
         'page_update' => array(
            'guide_action' => 'page.update_guide',
            'sequence' => array(
               'Bestehende Seite mit page.get oder page.update_guide lesen.',
               'Nur Felder aendern, die im Auftrag/change_fields erlaubt sind.',
               'Bei content-Aenderung vorhandene data-cms-media-id, dbx_mid-URLs, Links und Modulaufrufe erhalten, ausser der Auftrag fordert Aenderung.',
               'Bestehendes Hero-Bild ersetzen: page.hero_replace_image. Neues Hero-Bild setzen: page.hero_create_image.',
               'page.update nur fuer Seitenfelder verwenden; Medien danach bei Bedarf mit media.assign verknuepfen.',
               'dbxKi importiert job.json, validiert jeden Step und fuehrt den Prozess aus.',
            ),
             'fixed_rules' => array(
                'id, lng und permalink der Zielseite nicht eigenmaechtig aendern.',
                'Kein page.delete in KI-Auftraegen.',
                'Keine vorhandenen Medienpfade manuell umschreiben.',
                'Wenn content nicht in change_fields steht, content unveraendert lassen.',
                'Hero-Aenderungen nur ueber page.hero_replace_image/page.hero_create_image oder Hero-Felder plus media.assign slot=hero ausfuehren.',
                'Keinen Inline-Schein-Hero mit absolut positioniertem Text am Seitenanfang erzeugen.',
             ),
         ),
      );
   }

   private function page_create_guide(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $folderId = max(0, (int)($params['folder_id'] ?? $params['folder'] ?? 0));
      $title = $this->clean($params['title'] ?? '___TITEL___', 254);
      $withHero = $this->bool_value($params['with_hero'] ?? false);
      $withGallery = $this->bool_value($params['with_gallery'] ?? false);
      $template = $this->clean($params['template'] ?? ($withHero ? 'c-title-hero_header-gallery-body1-footer' : 'c-body1-footer'), 254);
      if ($folderId === 0 && strtolower($template) === 'parent') {
         $template = 'c-body1-footer';
      }

      $steps = array();
      if ($withHero) {
         $steps[] = array(
            'id' => 'hero',
            'action' => 'media.create_base64',
            'params' => array(
               'asset_ref' => 'hero.jpg',
               'file_name' => 'hero.jpg',
               'media_folder' => 'img/hero',
               'title' => $title . ' Hero',
               'alt' => $title,
            ),
         );
      }
      if ($withGallery) {
         $steps[] = array(
            'id' => 'gallery_1',
            'action' => 'media.create_base64',
            'params' => array(
               'asset_ref' => 'gallery-1.jpg',
               'file_name' => 'gallery-1.jpg',
               'media_folder' => 'img/gallery',
               'title' => $title . ' Galerie',
               'alt' => $title,
            ),
         );
      }
      $steps[] = array(
         'id' => 'page',
         'action' => 'page.create',
         'params' => array(
            'lng' => $lng,
            'folder_id' => $folderId,
            'title' => $title,
            'template' => $template,
            'hero_height' => $withHero ? '300px' : 'parent',
            'description' => '___SEO_BESCHREIBUNG___',
            'keywords' => '___KEYWORDS___',
            'activ' => 1,
            'content' => '___HTML_CONTENT___',
         ),
      );
      if ($withHero) {
         $steps[] = array(
            'id' => 'hero_assign',
            'action' => 'media.assign',
            'params' => array(
               'lng' => $lng,
               'media_id' => '$ref:hero.media_id',
               'content_id' => '$ref:page.page_id',
               'slot' => 'hero',
            ),
         );
      }
      if ($withGallery) {
         $steps[] = array(
            'id' => 'gallery_assign_1',
            'action' => 'media.assign',
            'params' => array(
               'lng' => $lng,
               'media_id' => '$ref:gallery_1.media_id',
               'content_id' => '$ref:page.page_id',
               'slot' => 'gallery',
            ),
         );
      }

      return array(
         'workflow' => $this->page_workflows()['page_create'],
         'manifest' => array(
            'title' => $title,
            'recipe' => 'page.create.v1',
            'lng' => $lng,
            'auto_execute' => true,
         ),
         'job' => array('steps' => $steps),
         'content_contract' => $this->content_contract(),
      );
   }

   private function page_update_guide(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = max(0, (int)($params['id'] ?? 0));
      $heroMode = strtolower(trim((string)($params['hero_mode'] ?? 'none')));
      if (!in_array($heroMode, array('none', 'replace', 'create'), true)) {
         $heroMode = 'none';
      }
      $fields = $params['change_fields'] ?? array('content');
      if (is_string($fields)) {
         $fields = array_values(array_filter(array_map('trim', explode(',', $fields))));
      }
      if (!is_array($fields) || !$fields) {
         $fields = array('content');
      }

      $steps = array();
      if ($heroMode === 'replace') {
         $steps[] = array(
            'id' => 'hero_replace',
            'action' => 'page.hero_replace_image',
            'params' => array(
               'lng' => $lng,
               'id' => $id,
               'source_file' => 'assets/hero.jpg',
               'width' => 1280,
               'height' => 300,
               'fit' => 'cover',
            ),
         );
      } elseif ($heroMode === 'create') {
         $steps[] = array(
            'id' => 'hero_create',
            'action' => 'page.hero_create_image',
            'params' => array(
               'lng' => $lng,
               'id' => $id,
               'source_file' => 'assets/hero.jpg',
               'file_name' => 'hero.jpg',
               'width' => 1280,
               'height' => 300,
               'fit' => 'cover',
            ),
         );
      }

      $patch = array();
      foreach ($fields as $field) {
         $field = trim((string)$field);
         if ($field === '') continue;
         $patch[$field] = $field === 'content' ? '___HTML_CONTENT___' : '___' . strtoupper($field) . '___';
      }
      if ($patch) {
         $steps[] = array(
            'id' => 'page_update',
            'action' => 'page.update',
            'params' => array(
               'lng' => $lng,
               'id' => $id,
               'patch' => $patch,
            ),
         );
      }

      $current = array();
      if ($id > 0) {
         try {
            $current = $this->page_get(array('lng' => $lng, 'id' => $id));
         } catch (\Throwable $e) {
            $current = array('error' => $e->getMessage());
         }
      }

      return array(
         'workflow' => $this->page_workflows()['page_update'],
         'target' => array('lng' => $lng, 'id' => $id, 'change_fields' => array_values($fields), 'hero_mode' => $heroMode),
         'current_page' => $current,
         'manifest' => array(
            'title' => 'Seite ' . $id . ' aktualisieren',
            'recipe' => 'page.update.v1',
            'lng' => $lng,
            'auto_execute' => true,
         ),
         'job' => array('steps' => $steps),
         'content_contract' => $this->content_contract(),
      );
   }

   private function content_contract(): array {
      return array(
         'html_allowed' => true,
         'bootstrap_allowed' => true,
         'forbidden' => array('SQL', 'direkte SQLite-Aenderungen', 'eigene PHP-Tools', 'eigene JavaScript-Logik im Content', 'files/media/... als img src', 'Inline-Schein-Hero mit position-relative/position-absolute'),
         'inline_media' => 'Immer inline_src/index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid={id} plus data-cms-media-id verwenden.',
         'hero' => array(
            'image' => 'Das Hero-Bild gehoert in die CMS-Hero-Zuordnung (slot=hero und hero_image_id), niemals in content.',
            'text' => 'Hero-Text steht vor dem hr-Marker data-dbx-marker="dbx:hero".',
            'validation' => 'dbxKi lehnt einen Inline-Bildblock mit absolut ueberlagertem Hero-Text am Seitenanfang ab.',
         ),
         'openwin' => 'openWin nur ueber class dbx-win und data-dbx="lib=openWin|url=...|title=...|width=...|height=..." verwenden.',
         'markers' => array(
            'dbx:hero' => 'Text davor wird Hero-Text.',
            'dbx:header' => 'Text danach bis zum naechsten Marker wird Header.',
            'dbx:footer' => 'Text danach wird Footer.',
         ),
      );
   }

   private function catalog(): array {
      return array(
         'system.health' => array(
            'write' => false,
            'description' => 'Prüft Modul, Benutzer, Sprachen und CMS-Datenzugriff.',
            'params' => array(),
         ),
         'cms.snapshot' => array(
            'write' => false,
            'description' => 'Liefert Ordner, Seiten und Medien in einem begrenzten Arbeitskontext.',
            'params' => array('lng' => 'Sprachcode', 'folder_id' => 'Optionaler Ordner', 'limit' => '1..200'),
         ),
         'folder.list' => array(
            'write' => false,
            'description' => 'Listet CMS-Ordner.',
            'params' => array('lng' => 'Sprachcode', 'parent_id' => 'Optionaler Parent', 'limit' => '1..500'),
         ),
         'folder.get' => array(
            'write' => false,
            'description' => 'Liest einen Ordner.',
            'params' => array('lng' => 'Sprachcode', 'id' => 'Ordner-ID'),
         ),
         'folder.create' => array(
            'write' => true,
            'description' => 'Erstellt einen Ordner.',
            'required' => array('name'),
            'params' => array(
               'lng' => 'Sprachcode',
               'name' => 'Bezeichnung',
               'parent_id' => 'Parent-ID, Standard 0',
               'group_read' => 'parent oder kommaseparierte Gruppen',
               'template' => 'Content-Template',
               'hero_*' => 'Optionale Hero-Einstellungen',
            ),
         ),
         'folder.update' => array(
            'write' => true,
            'description' => 'Ändert oder verschiebt einen Ordner.',
            'required' => array('id'),
            'params' => array('lng' => 'Sprachcode', 'id' => 'Ordner-ID', 'patch' => 'Zu ändernde Felder oder Felder direkt in params'),
         ),
         'folder.delete' => array(
            'write' => true,
            'destructive' => true,
            'description' => 'Löscht einen leeren Ordner in einer Sprache.',
            'required' => array('id'),
            'params' => array('lng' => 'Sprachcode', 'id' => 'Ordner-ID'),
         ),
         'page.list' => array(
            'write' => false,
            'description' => 'Listet CMS-Seiten.',
            'params' => array('lng' => 'Sprachcode', 'folder_id' => 'Optionaler Ordner', 'limit' => '1..500'),
         ),
         'page.get' => array(
            'write' => false,
            'description' => 'Liest eine Seite einschließlich Medienzuordnungen.',
            'params' => array('lng' => 'Sprachcode', 'id' => 'Seiten-ID'),
         ),
         'page.create_guide' => array(
            'write' => false,
            'description' => 'Liefert den verbindlichen KI-Ablauf, Regeln und ein job.json-Skelett fuer das Anlegen einer CMS-Seite.',
            'params' => array('lng' => 'Sprachcode', 'folder_id' => 'Zielordner', 'title' => 'Seitentitel', 'with_hero' => '0/1', 'with_gallery' => '0/1'),
         ),
         'page.update_guide' => array(
            'write' => false,
            'description' => 'Liefert den verbindlichen KI-Ablauf, Regeln und ein job.json-Skelett fuer das Aendern einer CMS-Seite.',
            'params' => array('lng' => 'Sprachcode', 'id' => 'Seiten-ID', 'change_fields' => 'Liste erlaubter Felder', 'hero_mode' => 'none, replace oder create'),
         ),
         'page.create' => array(
            'write' => true,
            'description' => 'Erstellt eine CMS-Seite.',
            'required' => array('title'),
            'params' => array(
               'lng' => 'Sprachcode',
               'folder_id' => 'Ordner-ID',
                'title' => 'Titel',
                'seo_title' => 'Optionaler SEO-Titel; Standard ist der Seitentitel',
                'content' => 'HTML-Inhalt',
               'description' => 'Meta-Beschreibung',
               'keywords' => 'Meta-Keywords',
               'permalink' => 'Optional; wird sonst erzeugt',
               'activ' => '0 oder 1',
               'template' => 'Content-Template',
            ),
         ),
         'page.update' => array(
            'write' => true,
            'description' => 'Aktualisiert ausgewählte Seitenfelder. Inline-Bilder in content werden automatisch auf CMS-Medien-URLs (dbx_mid) normalisiert.',
            'required' => array('id'),
            'params' => array(
               'lng' => 'Sprachcode',
               'id' => 'Seiten-ID',
               'patch' => 'Zu ändernde Felder oder Felder direkt in params',
               'package_product_image' => 'Optional 1: Paket-Card auf vorhandenes Produktbild (home-package-*) umstellen',
               'package_media_id' => 'Optional: Medien-ID statt Permalink-Zuordnung',
               'package_image_alt' => 'Optional: alt-Text fuer das Produktbild',
            ),
         ),
         'page.hero_replace_image' => array(
            'write' => true,
            'description' => 'Ersetzt nur die bestehende Hero-Bilddatei einer Seite. Medienverknüpfung und Seitenfelder bleiben unverändert.',
            'required' => array('id', 'source_file'),
            'params' => array(
               'lng' => 'Sprachcode',
               'id' => 'Seiten-ID',
               'source_file' => 'Absolute oder dbxapp-relative neue Bildquelle',
               'width' => 'Optional; Standard Breite des bestehenden Hero-Mediums',
               'height' => 'Optional; Standard Höhe des bestehenden Hero-Mediums',
               'fit' => 'cover oder contain, Standard cover',
               'quality' => '1..100, Standard 82',
            ),
         ),
         'page.hero_create_image' => array(
            'write' => true,
            'description' => 'Erstellt ein neues Hero-Bild in files/media/img/hero und setzt es als Hero der Seite.',
            'required' => array('id', 'source_file'),
            'params' => array(
               'lng' => 'Sprachcode',
               'id' => 'Seiten-ID',
               'source_file' => 'Absolute oder dbxapp-relative Bildquelle',
               'file_name' => 'Optionaler Dateiname, Standard aus Permalink',
               'width' => 'Zielbreite, Standard 1280',
               'height' => 'Zielhöhe, Standard 300',
               'fit' => 'cover oder contain, Standard cover',
               'quality' => '1..100, Standard 82',
            ),
         ),
         'page.delete' => array(
            'write' => true,
            'destructive' => true,
            'description' => 'Löscht eine Seite in einer Sprache und deaktiviert ihre Medienzuordnungen.',
            'required' => array('id'),
            'params' => array('lng' => 'Sprachcode', 'id' => 'Seiten-ID'),
         ),
         'media.list' => array(
            'write' => false,
            'description' => 'Listet aktive Medien und optional deren Zuordnungen.',
            'params' => array('media_type' => 'image, video, file oder external_video', 'folder' => 'Medienordner', 'limit' => '1..500'),
         ),
         'media.get' => array(
            'write' => false,
            'description' => 'Liest ein Medium und seine aktiven Verwendungen.',
            'params' => array('id' => 'Medien-ID'),
         ),
         'module.assets' => array(
            'write' => false,
            'description' => 'Listet vorhandene Modulbilder aus dbx/modules/*/tpl/mod und files/mod fuer Content- und Modul-Visualisierungen.',
            'params' => array('module' => 'Optionaler Modulname', 'limit' => '1..500'),
         ),
         'media.create_base64' => array(
            'write' => true,
            'description' => 'Speichert eine Base64-Datei über dbXapp und registriert sie als Medium. Liefert inline_src/inline_img fuer die Content-Einbindung.',
            'required' => array('file_name', 'data_base64'),
            'params' => array(
               'file_name' => 'Dateiname mit Endung',
               'data_base64' => 'Reines Base64 oder Data-URL',
               'media_folder' => 'Standard img/images, img/video oder file/ki; Hero immer img/hero, Gallery immer img/gallery',
               'title' => 'Titel',
               'alt' => 'Alternativtext',
               'caption' => 'Bildunterschrift',
               'tags' => 'Tags',
            ),
            'returns' => array('id', 'row', 'inline_src', 'inline_img'),
            'usage' => 'Im Content immer inline_src oder inline_img verwenden. Niemals files/media/... direkt in img src setzen.',
         ),
         'media.create_image_variant' => array(
            'write' => true,
            'description' => 'Erzeugt aus einer lokalen Bildquelle eine zugeschnittene, skalierte und optional farblich getönte Bildvariante und registriert sie als Medium. Liefert inline_src/inline_img fuer die Content-Einbindung.',
            'required' => array('source_file', 'file_name'),
            'params' => array(
               'source_file' => 'Absolute oder dbxapp-relative Quelldatei',
               'file_name' => 'Zieldateiname mit .webp, .jpg, .jpeg oder .png',
               'width' => 'Zielbreite, Standard Originalbreite',
               'height' => 'Zielhöhe, Standard Originalhöhe',
               'fit' => 'cover oder contain, Standard cover',
               'crop_x/crop_y/crop_width/crop_height' => 'Optionaler Quell-Ausschnitt in Pixeln vor dem Skalieren',
               'tint' => 'Optionale Farbe als #RRGGBB',
               'tint_strength' => '0..1, Standard 0',
               'quality' => '1..100, Standard 82',
               'media_folder' => 'Standard img/images; Hero immer img/hero, Gallery immer img/gallery',
               'title' => 'Titel',
               'alt' => 'Alternativtext',
               'caption' => 'Bildunterschrift',
               'tags' => 'Tags',
            ),
            'returns' => array('id', 'row', 'inline_src', 'inline_img'),
            'usage' => 'Im Content immer inline_src oder inline_img verwenden. Niemals files/media/... direkt in img src setzen.',
         ),
         'media.update' => array(
            'write' => true,
            'description' => 'Ändert Metadaten eines Mediums.',
            'required' => array('id'),
            'params' => array('id' => 'Medien-ID', 'patch' => 'title, alt, caption, tags, template'),
         ),
         'media.assign' => array(
            'write' => true,
            'description' => 'Ordnet ein Medium einer Seite oder einem Ordner zu.',
            'required' => array('media_id'),
            'params' => array(
               'media_id' => 'Medien-ID',
               'content_id' => 'Seiten-ID',
               'folder_id' => 'Ordner-ID',
               'slot' => 'hero, gallery, inline, header, teaser oder footer',
               'template' => 'Darstellungs-Template',
               'caption' => 'Kontextspezifische Bildunterschrift',
               'settings' => 'Objekt oder JSON-Text',
            ),
         ),
         'media.unassign' => array(
            'write' => true,
            'description' => 'Deaktiviert eine Medienzuordnung.',
            'required' => array('usage_id'),
            'params' => array('usage_id' => 'ID aus dbxMediaUsage'),
         ),
         'media.delete' => array(
            'write' => true,
            'destructive' => true,
            'description' => 'Löscht ein unbenutztes Medium einschließlich lokaler Datei.',
            'required' => array('id'),
            'params' => array('id' => 'Medien-ID'),
         ),
         'translation.preview' => array(
            'write' => false,
            'description' => 'Liefert Quelltext, vorhandenes Ziel und genaue Übersetzungsanweisung.',
            'required' => array('source_lng', 'target_lng', 'source_id'),
            'params' => array('source_lng' => 'Quellsprache', 'target_lng' => 'Zielsprache', 'source_id' => 'Quellseiten-ID'),
         ),
         'translation.apply' => array(
            'write' => true,
            'description' => 'Speichert eine von der KI gelieferte Übersetzung; kein externer Übersetzungsdienst nötig.',
            'required' => array('source_lng', 'target_lng', 'source_id', 'translation'),
            'params' => array(
               'source_lng' => 'Quellsprache',
               'target_lng' => 'Zielsprache',
               'source_id' => 'Quellseiten-ID',
               'translation' => 'Objekt mit title, description, keywords und content; optional seo_title sowie img_alt_1..3 und img_des_1..3',
               'copy_media' => '1 kopiert aktive Medienzuordnungen, Standard 1',
            ),
         ),
         'translation.sync_all' => array(
            'write' => true,
            'description' => 'Übersetzt eine komplette CMS-Sprachstruktur aus einer Quellsprache in eine oder mehrere Zielsprachen.',
            'required' => array('source_lng'),
            'params' => array(
               'source_lng' => 'Quellsprache',
               'target_lngs' => 'Optional: Array oder kommaseparierte Zielsprachen; Standard alle aktiven Sprachen außer source_lng',
               'root_folder_id' => 'Optional: Ordner-Teilbaum; Standard 0 = alle Ordner und Seiten',
               'update_existing' => '1 aktualisiert vorhandene Zielseiten/-ordner, Standard 1',
               'skip_manual' => '1 überspringt Ziel-Datensätze mit lng_sync=manual, Standard 0',
               'copy_media' => '1 kopiert aktive Medienzuordnungen, Standard 1',
               'replace_media_usage' => '1 ersetzt Medienzuordnungen der Zielseite; Standard 0 = nur fehlende ergänzen',
            ),
         ),
      );
   }

   private function health(): array {
      $this->ensure_schema();
      return array(
         'ok' => 1,
         'api_version' => self::API_VERSION,
         'user_id' => (int)dbx()->user(),
         'admin' => 1,
         'execute_enabled' => (int)dbx()->get_config('dbxKi', 'allow_execute', 1),
         'languages' => dbxContentLngSync::accessibleLngs(),
         'master_language' => dbxContentLngSync::masterLng(),
         'content_count' => $this->db->count(dbxContentLng::ddContent($this->language(''))),
         'folder_count' => $this->db->count(dbxContentLng::ddFolder($this->language(''))),
         'media_count' => $this->db->count('dbxMedia', 'active = 1'),
      );
   }

   private function read_action(string $action, array $params) {
      $this->ensure_schema();
      switch ($action) {
         case 'cms.snapshot':
            return $this->snapshot($params);
         case 'folder.list':
            return $this->folder_list($params);
         case 'folder.get':
            return $this->folder_get($params);
         case 'page.list':
            return $this->page_list($params);
         case 'page.get':
            return $this->page_get($params);
         case 'page.create_guide':
            return $this->page_create_guide($params);
         case 'page.update_guide':
            return $this->page_update_guide($params);
         case 'media.list':
            return $this->media_list($params);
         case 'media.get':
            return $this->media_get($params);
         case 'module.assets':
            return $this->module_assets($params);
         case 'translation.preview':
            return $this->translation_preview($params);
      }
      throw new \RuntimeException('Leseaktion nicht implementiert: ' . $action);
   }

   private function build_plan(string $action, array $params): array {
      $this->ensure_schema();
      switch ($action) {
         case 'folder.create':
            return $this->plan_folder_create($params);
         case 'folder.update':
            return $this->plan_folder_update($params);
         case 'folder.delete':
            return $this->plan_folder_delete($params);
         case 'page.create':
            return $this->plan_page_create($params);
         case 'page.update':
            return $this->plan_page_update($params);
         case 'page.hero_replace_image':
            return $this->plan_page_hero_replace_image($params);
         case 'page.hero_create_image':
            return $this->plan_page_hero_create_image($params);
         case 'page.delete':
            return $this->plan_page_delete($params);
         case 'media.create_base64':
            return $this->plan_media_create($params);
         case 'media.create_image_variant':
            return $this->plan_media_create_image_variant($params);
         case 'media.update':
            return $this->plan_media_update($params);
         case 'media.assign':
            return $this->plan_media_assign($params);
         case 'media.unassign':
            return $this->plan_media_unassign($params);
         case 'media.delete':
            return $this->plan_media_delete($params);
         case 'translation.apply':
            return $this->plan_translation_apply($params);
         case 'translation.sync_all':
            return $this->plan_translation_sync_all($params);
      }
      throw new \RuntimeException('Planung nicht implementiert: ' . $action);
   }

   private function execute_action(string $action, array $params, array $plan) {
      switch ($action) {
         case 'folder.create':
            return $this->execute_folder_create($plan);
         case 'folder.update':
            return $this->execute_folder_update($plan);
         case 'folder.delete':
            return $this->execute_folder_delete($plan);
         case 'page.create':
            return $this->execute_page_create($plan);
         case 'page.update':
            return $this->execute_page_update($plan);
         case 'page.hero_replace_image':
            return $this->execute_page_hero_replace_image($plan);
         case 'page.hero_create_image':
            return $this->execute_page_hero_create_image($plan);
         case 'page.delete':
            return $this->execute_page_delete($plan);
         case 'media.create_base64':
            return $this->execute_media_create($params, $plan);
         case 'media.create_image_variant':
            return $this->execute_media_create_image_variant($plan);
         case 'media.update':
            return $this->execute_media_update($plan);
         case 'media.assign':
            return $this->execute_media_assign($plan);
         case 'media.unassign':
            return $this->execute_media_unassign($plan);
         case 'media.delete':
            return $this->execute_media_delete($plan);
         case 'translation.apply':
            return $this->execute_translation_apply($params, $plan);
         case 'translation.sync_all':
            return $this->execute_translation_sync_all($plan);
      }
      throw new \RuntimeException('Ausführung nicht implementiert: ' . $action);
   }

   private function ensure_schema(): void {
      if (!is_object($this->db)) {
         throw new \RuntimeException('dbxDB ist nicht verfügbar.');
      }
      dbxContentLngSync::ensureSchema($this->db);
   }

   private function language($value): string {
      $lng = strtolower(trim((string)$value));
      if ($lng === '') {
         $lng = dbxContentLng::current();
      }
      if (!in_array($lng, dbxContentLngSync::accessibleLngs(), true)) {
         throw new \InvalidArgumentException('Nicht unterstützte Sprache: ' . $lng);
      }
      return $lng;
   }

   private function id(array $params, string $key = 'id'): int {
      $id = (int)($params[$key] ?? 0);
      if ($id <= 0) {
         throw new \InvalidArgumentException($key . ' muss größer als 0 sein.');
      }
      return $id;
   }

   private function limit(array $params, int $default = 100, int $max = 500): int {
      return max(1, min($max, (int)($params['limit'] ?? $default)));
   }

   private function bool_value($value): bool {
      if (is_bool($value)) return $value;
      return in_array(strtolower(trim((string)$value)), array('1', 'true', 'yes', 'ja', 'on'), true);
   }

   private function clean($value, int $max = 0): string {
      $value = trim((string)$value);
      if ($max > 0) {
         $value = mb_substr($value, 0, $max);
      }
      return $value;
   }

   private function plan_id(string $action, array $plan): string {
      return hash('sha256', $action . '|' . json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
   }

   private function snapshot(array $params): array {
      return array(
         'language' => $this->language($params['lng'] ?? ''),
         'folders' => $this->folder_list($params),
         'pages' => $this->page_list($params),
         'media' => $this->media_list(array('limit' => $this->limit($params, 100, 200))),
         'module_assets' => $this->module_assets(array('limit' => 200)),
      );
   }

   private function folder_list(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $where = '';
      if (array_key_exists('parent_id', $params)) {
         $where = 'parent_id = ' . max(0, (int)$params['parent_id']);
      }
      $rows = $this->db->select(dbxContentLng::ddFolder($lng), $where, '*', 'sorter,id', 'ASC', '', $this->limit($params), 0, 1);
      return array('lng' => $lng, 'rows' => is_array($rows) ? $rows : array());
   }

   private function folder_get(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $row = $this->db->select1(dbxContentLng::ddFolder($lng), $id);
      if (!is_array($row)) throw new \RuntimeException('Ordner nicht gefunden.');
      return array('lng' => $lng, 'row' => $row);
   }

   private function page_list(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $where = '';
      if (array_key_exists('folder_id', $params)) {
         $where = 'folder = ' . max(0, (int)$params['folder_id']);
      }
      $rows = $this->db->select(dbxContentLng::ddContent($lng), $where, '*', 'folder,sorter,id', 'ASC', '', $this->limit($params), 0, 1);
      return array('lng' => $lng, 'rows' => is_array($rows) ? $rows : array());
   }

   private function page_get(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $row = $this->db->select1(dbxContentLng::ddContent($lng), $id);
      if (!is_array($row)) throw new \RuntimeException('Seite nicht gefunden.');
      $usage = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage('content_id = ' . $id . ' AND active = 1', $lng), '*', 'slot,sorter,id', 'ASC');
      $hint = $this->package_page_hint($row);
      return array(
         'lng' => $lng,
         'row' => $row,
         'media_usage' => is_array($usage) ? $usage : array(),
         'package_hint' => $hint,
      );
   }

   private function media_list(array $params): array {
      $rows = $this->db->select('dbxMedia', 'active = 1', '*', 'id', 'DESC', '', $this->limit($params), 0, 1);
      $type = strtolower(trim((string)($params['media_type'] ?? '')));
      $folder = trim((string)($params['folder'] ?? ''));
      $rows = array_values(array_filter(is_array($rows) ? $rows : array(), static function($row) use ($type, $folder) {
         if ($type !== '' && strtolower((string)($row['media_type'] ?? '')) !== $type) return false;
         if ($folder !== '' && (string)($row['media_folder'] ?? '') !== $folder) return false;
         return true;
      }));
      return array('rows' => $rows);
   }

   private function media_get(array $params): array {
      $id = $this->id($params);
      $row = $this->db->select1('dbxMedia', $id);
      if (!is_array($row)) throw new \RuntimeException('Medium nicht gefunden.');
      $usage = $this->db->select('dbxMediaUsage', 'media_id = ' . $id . ' AND active = 1', '*', 'id', 'ASC');
      return array('row' => $row, 'usage' => is_array($usage) ? $usage : array());
   }

   private function module_assets(array $params): array {
      $base = rtrim(str_replace('\\', '/', dbx()->get_base_dir()), '/') . '/';
      $moduleFilter = strtolower(trim((string)($params['module'] ?? '')));
      $limit = $this->limit($params, 200, 500);
      $rows = array();
      $seen = array();

      $add = static function(string $file, string $source) use (&$rows, &$seen, $base, $moduleFilter): void {
         $path = str_replace('\\', '/', $file);
         if (!is_file($file)) {
            return;
         }
         if (!preg_match('/\.(svg|png|jpe?g|webp|gif)$/i', $path)) {
            return;
         }

         $rel = str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
         $name = basename($path);
         $module = '';
         $action = '';
         if (preg_match('#dbx/modules/([^/]+)/tpl/mod/([^/]+)\.[^.]+$#', $rel, $m)) {
            $module = (string)$m[1];
            $stem = (string)$m[2];
         } else {
            $stem = preg_replace('/\.[^.]+$/', '', $name);
            if (preg_match('/^([A-Za-z0-9_]+)__(.+)$/', (string)$stem, $m)) {
               $module = (string)$m[1];
               $action = (string)$m[2];
            }
         }
         if ($action === '' && $module !== '') {
            $prefix = $module . '_';
            $stem = preg_replace('/\.[^.]+$/', '', $name);
            $action = str_starts_with((string)$stem, $prefix) ? substr((string)$stem, strlen($prefix)) : (string)$stem;
         }
         if ($moduleFilter !== '' && strtolower($module) !== $moduleFilter) {
            return;
         }
         $key = $rel;
         if (isset($seen[$key])) {
            return;
         }
         $seen[$key] = true;
         $rows[] = array(
            'module' => $module,
            'action' => $action,
            'name' => $name,
            'source' => $source,
            'path' => $rel,
            'src' => $rel,
            'bytes' => filesize($file),
         );
      };

      foreach (glob(dbx()->get_base_dir() . 'dbx/modules/*/tpl/mod/*') ?: array() as $file) {
         $add($file, 'module_tpl_mod');
         if (count($rows) >= $limit) {
            break;
         }
      }
      if (count($rows) < $limit) {
         foreach (glob(dbx()->get_base_dir() . 'files/mod/*') ?: array() as $file) {
            $add($file, 'files_mod');
            if (count($rows) >= $limit) {
               break;
            }
         }
      }

      return array(
         'rows' => $rows,
         'usage' => 'Im Content als <img src=\"{src}\" ...> verwenden. Vorhandene Modulbilder bevorzugen, wenn Module visuell dargestellt werden.',
      );
   }

   private function translation_preview(array $params): array {
      $sourceLng = $this->language($params['source_lng'] ?? '');
      $targetLng = $this->language($params['target_lng'] ?? '');
      if ($sourceLng === $targetLng) throw new \InvalidArgumentException('Quell- und Zielsprache müssen verschieden sein.');
      $sourceId = $this->id($params, 'source_id');
      $sourceDd = dbxContentLng::ddContent($sourceLng);
      $source = $this->db->select1($sourceDd, $sourceId);
      if (!is_array($source)) throw new \RuntimeException('Quellseite nicht gefunden.');
      $uid = trim((string)($source['lng_uid'] ?? ''));
      $targetId = $uid !== ''
         ? dbxContentLngSync::resolveIdByUid($this->db, dbxContentLng::ddContent($targetLng), $uid, $targetLng)
         : 0;
      $target = $targetId > 0 ? $this->db->select1(dbxContentLng::ddContent($targetLng), $targetId) : null;
      return array(
         'source_lng' => $sourceLng,
         'target_lng' => $targetLng,
         'source' => $source,
         'target' => $target,
         'source_uid_missing' => $uid === '',
         'instruction' => array(
            'translate_fields' => array(
               'title', 'description', 'keywords', 'content', 'seo_title',
               'img_alt_1', 'img_alt_2', 'img_alt_3',
               'img_des_1', 'img_des_2', 'img_des_3'
            ),
            'preserve' => 'HTML-Struktur, Links, data-cms-media-id, Platzhalter, IDs, CSS-Klassen und technische Attribute unverändert lassen.',
            'do_not_translate' => 'Dateipfade, URLs, Modulnamen, Template-Namen, Shortcodes und Code.',
            'quality' => 'Natürlich, fachlich korrekt und zur Zielsprache passend übersetzen. Keine zusätzlichen Aussagen erfinden.',
            'next_action' => 'translation.apply mit translation-Objekt aufrufen.',
         ),
      );
   }

   private function plan_folder_create(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $name = $this->clean($params['name'] ?? '', 120);
      if ($name === '') throw new \InvalidArgumentException('name ist erforderlich.');
      $parent = max(0, (int)($params['parent_id'] ?? 0));
      if ($parent > 0 && !is_array($this->db->select1(dbxContentLng::ddFolder($lng), $parent))) {
         throw new \RuntimeException('Parent-Ordner nicht gefunden.');
      }
      return array(
         'operation' => 'insert',
         'entity' => 'folder',
         'lng' => $lng,
         'data' => $this->folder_data($params, $parent, $name),
      );
   }

   private function plan_folder_update(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $dd = dbxContentLng::ddFolder($lng);
      $before = $this->db->select1($dd, $id);
      if (!is_array($before)) throw new \RuntimeException('Ordner nicht gefunden.');
      $patch = $this->patch($params);
      $parent = array_key_exists('parent_id', $patch) ? max(0, (int)$patch['parent_id']) : (int)($before['parent_id'] ?? 0);
      if ($parent === $id || $this->folder_descendant($dd, $parent, $id)) {
         throw new \InvalidArgumentException('Ungültiger Parent: Schleife im Ordnerbaum.');
      }
      if ($parent > 0 && !is_array($this->db->select1($dd, $parent))) throw new \RuntimeException('Parent-Ordner nicht gefunden.');
      $allowed = array('name', 'parent_id', 'group_read', 'template', 'hero_template', 'hero_image_id', 'hero_margin_top', 'hero_height', 'hero_variant', 'hero_sticky', 'hero_scroll_layer', 'sorter');
      $data = $this->whitelist($patch, $allowed);
      if (isset($data['name'])) $data['name'] = $this->clean($data['name'], 120);
      if (!$data) throw new \InvalidArgumentException('Keine änderbaren Felder übergeben.');
      return array('operation' => 'update', 'entity' => 'folder', 'lng' => $lng, 'id' => $id, 'before' => $before, 'changes' => $data);
   }

   private function plan_folder_delete(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $row = $this->db->select1(dbxContentLng::ddFolder($lng), $id);
      if (!is_array($row)) throw new \RuntimeException('Ordner nicht gefunden.');
      $check = dbxContentLngSync::folderDeletable($this->db, $lng, $id);
      if ((int)($check['deletable'] ?? 0) !== 1) {
         throw new \RuntimeException((string)($check['reason'] ?? 'Ordner ist nicht löschbar.'));
      }
      return array('operation' => 'delete', 'entity' => 'folder', 'lng' => $lng, 'id' => $id, 'before' => $row);
   }

   private function plan_page_create(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $title = $this->clean($params['title'] ?? '', 254);
      if ($title === '') throw new \InvalidArgumentException('title ist erforderlich.');
      $folder = max(0, (int)($params['folder_id'] ?? $params['folder'] ?? 0));
      if ($folder > 0 && !is_array($this->db->select1(dbxContentLng::ddFolder($lng), $folder))) {
         throw new \RuntimeException('Zielordner nicht gefunden.');
      }
      $data = $this->page_data($params, $lng, $folder, $title);
      $this->assert_no_fake_inline_hero((string)($data['content'] ?? ''));
      return array(
         'operation' => 'insert',
         'entity' => 'page',
         'lng' => $lng,
         'data' => $data,
      );
   }

   private function plan_page_update(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $dd = dbxContentLng::ddContent($lng);
      $before = $this->db->select1($dd, $id);
      if (!is_array($before)) throw new \RuntimeException('Seite nicht gefunden.');
      $patch = $this->patch($params);
      if (array_key_exists('folder_id', $patch) && !array_key_exists('folder', $patch)) {
         $patch['folder'] = $patch['folder_id'];
      }
      $packageProductImage = $this->bool_value($patch['package_product_image'] ?? false);
      $packageMediaId = max(0, (int)($patch['package_media_id'] ?? 0));
      $packageImageAlt = $this->clean($patch['package_image_alt'] ?? '', 254);
      unset($patch['package_product_image'], $patch['package_media_id'], $patch['package_image_alt']);
      $allowed = array(
          'activ', 'folder', 'title', 'menu_title', 'seo_title', 'permalink', 'description', 'keywords', 'group_read', 'template', 'content', 'sorter',
         'hero_template', 'hero_image_id', 'hero_margin_top', 'hero_height', 'hero_variant', 'hero_sticky',
         'hero_scroll_layer', 'gallery_template', 'gallery_visible_count', 'gallery_image_size',
         'gallery_lightbox_width', 'gallery_overflow', 'gallery_click_behavior'
      );
      $data = $this->whitelist($patch, $allowed);
      if (isset($data['title'])) $data['title'] = $this->clean($data['title'], 254);
      if (isset($data['menu_title'])) $data['menu_title'] = $this->clean($data['menu_title'], 96);
      if (isset($data['seo_title'])) $data['seo_title'] = $this->clean($data['seo_title'], 254);
      if (array_key_exists('permalink', $data)) {
         $data['permalink'] = trim($this->clean($data['permalink'], 254));
         if (!dbxContent_permalink::isValid($data['permalink'])) {
            throw new \InvalidArgumentException('permalink darf nur Kleinbuchstaben, Zahlen und einzelne Bindestriche enthalten.');
         }
         if (dbxContent_permalink::exists($this->db, $dd, $data['permalink'], $id)) {
            throw new \InvalidArgumentException('permalink wird bereits von einer anderen Seite verwendet.');
         }
      }
      if (isset($data['folder'])) {
         $data['folder'] = max(0, (int)$data['folder']);
         if ($data['folder'] > 0 && !is_array($this->db->select1(dbxContentLng::ddFolder($lng), $data['folder']))) {
            throw new \RuntimeException('Zielordner nicht gefunden.');
         }
      }
      if (!$data && !$packageProductImage && $packageMediaId <= 0) {
         throw new \InvalidArgumentException('Keine änderbaren Felder übergeben.');
      }
      if (array_key_exists('content', $data)) {
         $data['content'] = $this->normalize_content_inline_media_urls((string)$data['content']);
      }
      $packageMediaApplied = 0;
      if ($packageProductImage || $packageMediaId > 0) {
         $mediaId = $packageMediaId > 0
            ? $packageMediaId
            : $this->package_media_id_for_permalink((string)($before['permalink'] ?? ''));
         if ($mediaId <= 0) {
            throw new \RuntimeException('Kein Paket-Produktbild fuer diese Seite gefunden. package_media_id angeben oder home-package-* Medium anlegen.');
         }
         $content = array_key_exists('content', $data)
            ? (string)$data['content']
            : (string)($before['content'] ?? '');
         $data['content'] = $this->normalize_content_inline_media_urls(
            $this->apply_package_product_image($content, $mediaId, $packageImageAlt)
         );
         $packageMediaApplied = $mediaId;
      }
      if (array_key_exists('content', $data)) {
         $this->assert_no_fake_inline_hero((string)$data['content']);
      }
      $plan = array('operation' => 'update', 'entity' => 'page', 'lng' => $lng, 'id' => $id, 'before' => $before, 'changes' => $data);
      if ($packageMediaApplied > 0) {
         $plan['package_media_id_applied'] = $packageMediaApplied;
      }
      return $plan;
   }

   private function plan_page_hero_replace_image(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $hero = $this->hero_media_for_page($lng, $id);
      $media = $hero['media'];
      $source = $this->source_image_plan($params);
      $target = $this->media_local_file($media);
      if ($target === '') throw new \RuntimeException('Hero-Medium ist keine lokale Datei.');

      $width = max(1, (int)($params['width'] ?? $media['width'] ?? 0));
      $height = max(1, (int)($params['height'] ?? $media['height'] ?? 0));
      if ($width <= 1 || $height <= 1) {
         $width = 1280;
         $height = 300;
      }
      $mime = (string)($media['mime'] ?? '');
      if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true)) {
         $mime = $this->mime_from_file_name((string)($media['file_name'] ?? 'hero.webp'));
      }

      return array(
         'operation' => 'replace_page_hero_file',
         'entity' => 'page_hero',
         'lng' => $lng,
         'id' => $id,
         'page' => $hero['page'],
         'media' => $media,
         'usage' => $hero['usage'],
         'source' => $source,
         'target_file' => $target,
         'width' => $width,
         'height' => $height,
         'fit' => $this->image_fit($params['fit'] ?? 'cover'),
         'quality' => $this->image_quality($params['quality'] ?? 82),
         'mime' => $mime,
      );
   }

   private function plan_page_hero_create_image(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $dd = dbxContentLng::ddContent($lng);
      $page = $this->db->select1($dd, $id);
      if (!is_array($page)) throw new \RuntimeException('Seite nicht gefunden.');

      $permalink = trim((string)($page['permalink'] ?? ''));
      $baseName = $permalink !== '' ? $permalink : ('page-' . $id);
      $fileName = $this->safe_file_name($params['file_name'] ?? ($baseName . '-hero.webp'));
      if ($fileName === '') $fileName = 'page-' . $id . '-hero.webp';

      $variant = $this->plan_media_create_image_variant(array_merge($params, array(
         'file_name' => $fileName,
         'width' => max(1, (int)($params['width'] ?? 1280)),
         'height' => max(1, (int)($params['height'] ?? 300)),
         'fit' => $params['fit'] ?? 'cover',
         'quality' => $params['quality'] ?? 82,
         'media_folder' => 'img/hero',
         'title' => $params['title'] ?? ('Hero ' . ($page['title'] ?? $fileName)),
         'alt' => $params['alt'] ?? (string)($page['title'] ?? ''),
      )));

      return array(
         'operation' => 'create_page_hero_media',
         'entity' => 'page_hero',
         'lng' => $lng,
         'id' => $id,
         'page' => $page,
         'media_plan' => $variant,
      );
   }

   private function plan_page_delete(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $row = $this->db->select1(dbxContentLng::ddContent($lng), $id);
      if (!is_array($row)) throw new \RuntimeException('Seite nicht gefunden.');
      $usage = $this->db->count('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage('content_id = ' . $id . ' AND active = 1', $lng));
      return array('operation' => 'delete', 'entity' => 'page', 'lng' => $lng, 'id' => $id, 'before' => $row, 'media_usage_to_deactivate' => $usage);
   }

   private function plan_media_create(array $params): array {
      $name = $this->safe_file_name($params['file_name'] ?? '');
      $raw = (string)($params['data_base64'] ?? '');
      if ($name === '' || trim($raw) === '') throw new \InvalidArgumentException('file_name und data_base64 sind erforderlich.');
      $decoded = $this->decode_base64($raw);
      $max = max(1024, (int)dbx()->get_config('dbxKi', 'max_base64_bytes', 10485760));
      if (strlen($decoded) > $max) throw new \InvalidArgumentException('Datei überschreitet das konfigurierte Größenlimit.');
      $mime = $this->detect_mime($decoded, $name);
      $allowed = array('image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'video/quicktime', 'application/pdf', 'text/plain');
      if (!in_array($mime, $allowed, true)) throw new \InvalidArgumentException('Nicht unterstützter MIME-Typ: ' . $mime);
      $type = strpos($mime, 'image/') === 0 ? 'image' : (strpos($mime, 'video/') === 0 ? 'video' : 'file');
      $defaultFolder = $type === 'image' ? 'img/images' : ($type === 'video' ? 'img/video' : 'file/ki');
      $folder = $this->media_folder($params['media_folder'] ?? $defaultFolder, $type);
      return array(
         'operation' => 'create_file_and_insert',
         'entity' => 'media',
         'file_name' => $name,
         'bytes' => strlen($decoded),
         'sha256' => hash('sha256', $decoded),
         'mime' => $mime,
         'media_type' => $type,
         'media_folder' => $folder,
         'metadata' => array(
            'title' => $this->clean($params['title'] ?? pathinfo($name, PATHINFO_FILENAME), 160),
            'alt' => $this->clean($params['alt'] ?? '', 254),
            'caption' => $this->clean($params['caption'] ?? ''),
            'tags' => $this->clean($params['tags'] ?? '', 254),
         ),
      );
   }

   private function plan_media_create_image_variant(array $params): array {
      if (!extension_loaded('gd')) {
         throw new \RuntimeException('GD ist erforderlich, um Bildvarianten zu erzeugen.');
      }

      $source = $this->resolve_local_file((string)($params['source_file'] ?? ''));
      if ($source === '' || !is_file($source) || !is_readable($source)) {
         throw new \InvalidArgumentException('source_file ist nicht lesbar.');
      }

      $name = $this->safe_file_name($params['file_name'] ?? '');
      if ($name === '') throw new \InvalidArgumentException('file_name ist erforderlich.');
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $mimeMap = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp');
      if (!isset($mimeMap[$ext])) {
         throw new \InvalidArgumentException('file_name muss .webp, .jpg, .jpeg oder .png verwenden.');
      }

      $info = @getimagesize($source);
      if (!is_array($info) || empty($info[0]) || empty($info[1])) {
         throw new \InvalidArgumentException('source_file ist kein lesbares Bild.');
      }
      $sourceMime = (string)($info['mime'] ?? '');
      if (!in_array($sourceMime, array('image/jpeg', 'image/png', 'image/webp', 'image/gif'), true)) {
         throw new \InvalidArgumentException('Nicht unterstützter Quellbildtyp: ' . $sourceMime);
      }

      $sourceWidth = (int)$info[0];
      $sourceHeight = (int)$info[1];
      $crop = $this->image_crop_rect($params, $sourceWidth, $sourceHeight);
      $width = max(1, (int)($params['width'] ?? $sourceWidth));
      $height = max(1, (int)($params['height'] ?? $sourceHeight));
      $fit = strtolower(trim((string)($params['fit'] ?? 'cover')));
      if (!in_array($fit, array('cover', 'contain'), true)) $fit = 'cover';
      $quality = min(100, max(1, (int)($params['quality'] ?? 82)));
      $tint = $this->normalize_hex_color((string)($params['tint'] ?? ''));
      $tintStrength = max(0.0, min(1.0, (float)($params['tint_strength'] ?? 0)));
      $folder = $this->media_folder($params['media_folder'] ?? 'img/images', 'image');

      return array(
         'operation' => 'create_image_variant_and_insert',
         'entity' => 'media',
         'source_file' => $source,
         'source_sha256' => hash_file('sha256', $source),
         'source_mime' => $sourceMime,
         'source_width' => $sourceWidth,
         'source_height' => $sourceHeight,
         'crop' => $crop,
         'file_name' => $name,
         'mime' => $mimeMap[$ext],
         'media_type' => 'image',
         'media_folder' => $folder,
         'width' => $width,
         'height' => $height,
         'fit' => $fit,
         'quality' => $quality,
         'tint' => $tint,
         'tint_strength' => $tintStrength,
         'metadata' => array(
            'title' => $this->clean($params['title'] ?? pathinfo($name, PATHINFO_FILENAME), 160),
            'alt' => $this->clean($params['alt'] ?? '', 254),
            'caption' => $this->clean($params['caption'] ?? ''),
            'tags' => $this->clean($params['tags'] ?? '', 254),
         ),
      );
   }

   private function plan_media_update(array $params): array {
      $id = $this->id($params);
      $before = $this->db->select1('dbxMedia', $id);
      if (!is_array($before) || (int)($before['active'] ?? 0) !== 1) throw new \RuntimeException('Medium nicht gefunden.');
      $data = $this->whitelist($this->patch($params), array('title', 'alt', 'caption', 'tags', 'template'));
      if (!$data) throw new \InvalidArgumentException('Keine änderbaren Metadaten übergeben.');
      return array('operation' => 'update', 'entity' => 'media', 'id' => $id, 'before' => $before, 'changes' => $data);
   }

   private function plan_media_assign(array $params): array {
      $mediaId = $this->id($params, 'media_id');
      $media = $this->db->select1('dbxMedia', $mediaId);
      if (!is_array($media) || (int)($media['active'] ?? 0) !== 1) throw new \RuntimeException('Medium nicht gefunden.');
      $contentId = max(0, (int)($params['content_id'] ?? 0));
      $folderId = max(0, (int)($params['folder_id'] ?? 0));
      if (($contentId > 0) === ($folderId > 0)) throw new \InvalidArgumentException('Genau content_id oder folder_id muss gesetzt sein.');
      $lng = $this->language($params['lng'] ?? '');
      if ($contentId > 0 && !is_array($this->db->select1(dbxContentLng::ddContent($lng), $contentId))) throw new \RuntimeException('Seite nicht gefunden.');
      if ($folderId > 0 && !is_array($this->db->select1(dbxContentLng::ddFolder($lng), $folderId))) throw new \RuntimeException('Ordner nicht gefunden.');
      $slot = $this->slot($params['slot'] ?? 'gallery');
      return array(
         'operation' => 'insert',
         'entity' => 'media_usage',
         'lng' => $lng,
         'media' => $media,
         'data' => array(
            'active' => 1,
            'media_id' => $mediaId,
            'content_id' => $contentId,
            'folder_id' => $folderId,
            'slot' => $slot,
            'template' => $this->clean($params['template'] ?? $media['template'] ?? '', 80),
            'caption' => $this->clean($params['caption'] ?? ''),
            'settings' => is_array($params['settings'] ?? null)
               ? json_encode($params['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
               : $this->clean($params['settings'] ?? ''),
         ),
      );
   }

   private function plan_media_unassign(array $params): array {
      $id = $this->id($params, 'usage_id');
      $row = $this->db->select1('dbxMediaUsage', $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) throw new \RuntimeException('Aktive Medienzuordnung nicht gefunden.');
      return array('operation' => 'update', 'entity' => 'media_usage', 'id' => $id, 'before' => $row, 'changes' => array('active' => 0));
   }

   private function plan_media_delete(array $params): array {
      $id = $this->id($params);
      $data = $this->media_get(array('id' => $id));
      if (count($data['usage'])) throw new \RuntimeException('Medium wird noch verwendet. Zuerst media.unassign ausführen.');
      return array('operation' => 'delete', 'entity' => 'media', 'id' => $id, 'before' => $data['row']);
   }

   private function plan_translation_apply(array $params): array {
      $preview = $this->translation_preview($params);
      $translation = is_array($params['translation'] ?? null) ? $params['translation'] : array();
      foreach (array('title', 'description', 'keywords', 'content') as $field) {
         if (!array_key_exists($field, $translation)) throw new \InvalidArgumentException('translation.' . $field . ' fehlt.');
      }
      $translation = $this->whitelist($translation, array(
         'title', 'description', 'keywords', 'content', 'seo_title',
         'img_alt_1', 'img_alt_2', 'img_alt_3',
         'img_des_1', 'img_des_2', 'img_des_3'
      ));
      $translation['title'] = $this->clean($translation['title'], 254);
      $translation['description'] = $this->clean($translation['description'], 254);
      $translation['keywords'] = $this->clean($translation['keywords'], 254);
      foreach (array('seo_title', 'img_alt_1', 'img_alt_2', 'img_alt_3') as $field) {
         if (array_key_exists($field, $translation)) {
            $translation[$field] = $this->clean($translation[$field], 254);
         }
      }
      foreach (array('img_des_1', 'img_des_2', 'img_des_3') as $field) {
         if (array_key_exists($field, $translation)) {
            $translation[$field] = $this->clean($translation[$field]);
         }
      }
      if ($translation['title'] === '') throw new \InvalidArgumentException('Übersetzter Titel darf nicht leer sein.');
      $translation['content'] = $this->normalize_content_inline_media_urls((string)$translation['content']);
      return array(
         'operation' => is_array($preview['target']) ? 'update' : 'insert',
         'entity' => 'translation',
         'source_lng' => $preview['source_lng'],
         'target_lng' => $preview['target_lng'],
         'source' => $preview['source'],
         'target' => $preview['target'],
         'translation' => $translation,
         'copy_media' => !array_key_exists('copy_media', $params) || $this->bool_value($params['copy_media']),
      );
   }

   private function plan_translation_sync_all(array $params): array {
      $sourceLng = $this->language($params['source_lng'] ?? '');
      $targetLngs = $this->target_languages($params, $sourceLng);
      if (!count($targetLngs)) {
         throw new \InvalidArgumentException('Keine Zielsprachen gefunden.');
      }

      $rootFolderId = max(0, (int)($params['root_folder_id'] ?? $params['folder_id'] ?? 0));
      if ($rootFolderId > 0 && !is_array($this->db->select1(dbxContentLng::ddFolder($sourceLng), $rootFolderId))) {
         throw new \RuntimeException('Quellordner nicht gefunden.');
      }

      $folderIds = $this->collect_folder_ids_for_lng($sourceLng, $rootFolderId);
      $pageIds = $this->collect_page_ids_for_lng($sourceLng, $rootFolderId, $folderIds);

      return array(
         'operation' => 'translation_sync_all',
         'entity' => 'content_language',
         'source_lng' => $sourceLng,
         'target_lngs' => $targetLngs,
         'root_folder_id' => $rootFolderId,
         'update_existing' => !array_key_exists('update_existing', $params) || $this->bool_value($params['update_existing']),
         'skip_manual' => array_key_exists('skip_manual', $params) && $this->bool_value($params['skip_manual']),
         'copy_media' => !array_key_exists('copy_media', $params) || $this->bool_value($params['copy_media']),
         'replace_media_usage' => array_key_exists('replace_media_usage', $params) && $this->bool_value($params['replace_media_usage']),
         'provider' => dbxContentTranslate::provider(),
         'counts' => array(
            'folders' => count($folderIds),
            'pages' => count($pageIds),
            'target_languages' => count($targetLngs),
         ),
         'source_ids' => array(
            'folders' => $folderIds,
            'pages' => $pageIds,
         ),
      );
   }

   private function execute_folder_create(array $plan): array {
      $dd = dbxContentLng::ddFolder($plan['lng']);
      $data = $plan['data'];
      $data['sorter'] = $this->next_sorter($dd, 'parent_id', (int)$data['parent_id']);
      $data += $this->lng_fields('f', $plan['lng']);
      if ($this->db->insert($dd, $data) !== 1) throw new \RuntimeException('Ordner konnte nicht erstellt werden.');
      $id = $this->db->get_insert_id();
      $this->invalidate_folder($id);
      return array('id' => $id, 'row' => $this->db->select1($dd, $id));
   }

   private function execute_folder_update(array $plan): array {
      $dd = dbxContentLng::ddFolder($plan['lng']);
      $data = $plan['changes'];
      $data = $this->advance_revision($dd, $plan['id'], $data, $plan['lng']);
      if ($this->db->update($dd, $data, $plan['id']) !== 1) throw new \RuntimeException('Ordner konnte nicht aktualisiert werden.');
      $this->invalidate_folder($plan['id']);
      return array('id' => $plan['id'], 'row' => $this->db->select1($dd, $plan['id']));
   }

   private function execute_folder_delete(array $plan): array {
      $dd = dbxContentLng::ddFolder($plan['lng']);
      if ($this->db->delete($dd, $plan['id']) !== 1) throw new \RuntimeException('Ordner konnte nicht gelöscht werden.');
      $this->invalidate_folder($plan['id']);
      return array('deleted' => true, 'id' => $plan['id'], 'lng' => $plan['lng']);
   }

   private function execute_page_create(array $plan): array {
      $dd = dbxContentLng::ddContent($plan['lng']);
      $data = $plan['data'];
      if (trim((string)($data['sorter'] ?? '')) === '') {
         $data['sorter'] = $this->next_sorter($dd, 'folder', (int)$data['folder']);
      }
      $data += $this->lng_fields('p', $plan['lng']);
      if ($this->db->insert($dd, $data) !== 1) throw new \RuntimeException('Seite konnte nicht erstellt werden.');
      $id = $this->db->get_insert_id();
      $this->invalidate_page($id, $plan['lng'], $data);
      return array('id' => $id, 'row' => $this->db->select1($dd, $id));
   }

   private function execute_page_update(array $plan): array {
      $dd = dbxContentLng::ddContent($plan['lng']);
      $data = $this->advance_revision($dd, $plan['id'], $plan['changes'], $plan['lng']);
      if ($this->db->update($dd, $data, $plan['id']) !== 1) throw new \RuntimeException('Seite konnte nicht aktualisiert werden.');
      $mediaId = (int)($plan['package_media_id_applied'] ?? 0);
      if ($mediaId > 0) {
         $this->ensure_inline_media_usage((int)$plan['id'], $mediaId, (string)$plan['lng']);
      }
      $row = $this->db->select1($dd, $plan['id']);
      $this->invalidate_page($plan['id'], $plan['lng'], $row);
      $result = array('id' => $plan['id'], 'row' => $row);
      if ($mediaId > 0) {
         $result['package_media_id'] = $mediaId;
         $result = array_merge($result, $this->media_inline_payload($mediaId));
      }
      return $result;
   }

   private function execute_page_hero_replace_image(array $plan): array {
      $target = (string)$plan['target_file'];
      $this->render_image_variant_to_file($plan['source'], $target, (int)$plan['width'], (int)$plan['height'], (string)$plan['fit'], (string)$plan['mime'], (int)$plan['quality']);

      $mediaId = (int)($plan['media']['id'] ?? 0);
      $data = array(
         'size' => (int)@filesize($target),
         'width' => (int)$plan['width'],
         'height' => (int)$plan['height'],
         'mime' => (string)$plan['mime'],
      );
      if ($mediaId <= 0) throw new \RuntimeException('Hero-Medium konnte nicht aktualisiert werden.');
      $this->db->update('dbxMedia', $data, $mediaId);
      $this->invalidate_media_references($mediaId);
      $this->invalidate_page((int)$plan['id'], (string)$plan['lng'], $plan['page']);
      return array(
         'id' => (int)$plan['id'],
         'media_id' => $mediaId,
         'file' => str_replace('\\', '/', $target),
         'replaced' => true,
      );
   }

   private function execute_page_hero_create_image(array $plan): array {
      $media = $this->execute_media_create_image_variant($plan['media_plan']);
      $mediaId = (int)($media['id'] ?? 0);
      if ($mediaId <= 0) throw new \RuntimeException('Hero-Medium konnte nicht erstellt werden.');

      $data = array(
         'active' => 1,
         'media_id' => $mediaId,
         'content_id' => (int)$plan['id'],
         'folder_id' => 0,
         'content_lng' => dbxContentMediaUsageScope::language((string)$plan['lng']),
         'slot' => 'hero',
         'template' => '',
         'caption' => '',
         'settings' => '',
      );
      $where = dbxContentMediaUsageScope::withLanguage('content_id = ' . (int)$plan['id'] . " AND slot = 'hero' AND active = 1", (string)$plan['lng']);
      $this->db->update('dbxMediaUsage', array('active' => 0), $where, 0, 1, 1, 1);
      $data['sorter'] = $this->next_usage_sorter((int)$plan['id'], 0, 'hero', (string)$plan['lng']);
      if ($this->db->insert('dbxMediaUsage', $data) !== 1) {
         throw new \RuntimeException('Hero-Medienzuordnung konnte nicht erstellt werden.');
      }
      $usageId = $this->db->get_insert_id();
      $this->sync_hero_setting((string)$plan['lng'], $data);
      $this->invalidate_usage($data);
      $row = $this->db->select1(dbxContentLng::ddContent((string)$plan['lng']), (int)$plan['id']);
      return array(
         'id' => (int)$plan['id'],
         'media_id' => $mediaId,
         'usage_id' => $usageId,
         'row' => $row,
         'media' => $media['row'] ?? array(),
      );
   }

   private function execute_page_delete(array $plan): array {
      $dd = dbxContentLng::ddContent($plan['lng']);
      if ($this->db->delete($dd, $plan['id']) !== 1) throw new \RuntimeException('Seite konnte nicht gelöscht werden.');
      $this->db->update('dbxMediaUsage', array('active' => 0), dbxContentMediaUsageScope::withLanguage('content_id = ' . (int)$plan['id'] . ' AND active = 1', (string)$plan['lng']), 0, 1, 1, 1);
      dbxContentPageCache::invalidateContent($plan['id']);
      dbxContentPageCache::invalidateAllMenus();
      dbxContentPermalinkIndex::removeByCid($plan['id'], $plan['lng']);
      return array('deleted' => true, 'id' => $plan['id'], 'lng' => $plan['lng']);
   }

   private function execute_media_create(array $params, array $plan): array {
      $bytes = $this->decode_base64((string)$params['data_base64']);
      if (!hash_equals((string)$plan['sha256'], hash('sha256', $bytes))) {
         throw new \RuntimeException('Der Medieninhalt stimmt nicht mit dem geprüften Plan überein.');
      }
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/media/' . $plan['media_folder'];
      $dir = dbx()->os_path($dir);
      if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) throw new \RuntimeException('Medienordner konnte nicht erstellt werden.');
      $name = $this->unique_name($dir, $plan['file_name']);
      $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
      if (file_put_contents($file, $bytes) === false) throw new \RuntimeException('Mediendatei konnte nicht geschrieben werden.');
      $relative = 'media/' . trim(str_replace('\\', '/', $plan['media_folder']), '/') . '/' . $name;
      $width = 0;
      $height = 0;
      $size = @getimagesize($file);
      if (is_array($size)) {
         $width = (int)($size[0] ?? 0);
         $height = (int)($size[1] ?? 0);
      }
      $data = array_merge($plan['metadata'], array(
         'active' => 1,
         'file_name' => $name,
         'file_path' => $relative,
         'mime' => $plan['mime'],
         'size' => strlen($bytes),
         'width' => $width,
         'height' => $height,
         'media_type' => $plan['media_type'],
         'storage_type' => 'local',
         'media_folder' => $plan['media_folder'],
      ));
      if ($this->db->insert('dbxMedia', $data) !== 1) {
         @unlink($file);
         throw new \RuntimeException('Medium konnte nicht registriert werden.');
      }
      $id = $this->db->get_insert_id();
      return array_merge(array('id' => $id, 'row' => $this->db->select1('dbxMedia', $id)), $this->media_inline_payload($id));
   }

   private function execute_media_create_image_variant(array $plan): array {
      $source = (string)($plan['source_file'] ?? '');
      if (!is_file($source) || !is_readable($source)) {
         throw new \RuntimeException('Quellbild ist nicht lesbar.');
      }
      if (!hash_equals((string)($plan['source_sha256'] ?? ''), hash_file('sha256', $source))) {
         throw new \RuntimeException('Das Quellbild stimmt nicht mehr mit dem geprüften Plan überein.');
      }

      $src = $this->gd_load_image($source, (string)$plan['source_mime']);
      $width = max(1, (int)$plan['width']);
      $height = max(1, (int)$plan['height']);
      $dst = imagecreatetruecolor($width, $height);
      imagealphablending($dst, false);
      imagesavealpha($dst, true);
      $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
      imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);

      $sourceWidth = imagesx($src);
      $sourceHeight = imagesy($src);
      $sourceX = 0;
      $sourceY = 0;
      $crop = is_array($plan['crop'] ?? null) ? $plan['crop'] : array();
      if ($crop) {
         $sourceX = max(0, min((int)($crop['x'] ?? 0), $sourceWidth - 1));
         $sourceY = max(0, min((int)($crop['y'] ?? 0), $sourceHeight - 1));
         $sourceWidth = max(1, min((int)($crop['width'] ?? $sourceWidth), imagesx($src) - $sourceX));
         $sourceHeight = max(1, min((int)($crop['height'] ?? $sourceHeight), imagesy($src) - $sourceY));
      }
      $fit = (string)($plan['fit'] ?? 'cover');
      if ($fit === 'contain') {
         $scale = min($width / $sourceWidth, $height / $sourceHeight);
         $copyWidth = max(1, (int)round($sourceWidth * $scale));
         $copyHeight = max(1, (int)round($sourceHeight * $scale));
         $dstX = (int)floor(($width - $copyWidth) / 2);
         $dstY = (int)floor(($height - $copyHeight) / 2);
         imagecopyresampled($dst, $src, $dstX, $dstY, $sourceX, $sourceY, $copyWidth, $copyHeight, $sourceWidth, $sourceHeight);
      } else {
         $sourceRatio = $sourceWidth / $sourceHeight;
         $targetRatio = $width / $height;
         if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int)round($sourceHeight * $targetRatio);
            $srcX = $sourceX + (int)floor(($sourceWidth - $cropWidth) / 2);
            $srcY = $sourceY;
         } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int)round($sourceWidth / $targetRatio);
            $srcX = $sourceX;
            $srcY = $sourceY + (int)floor(($sourceHeight - $cropHeight) / 2);
         }
         imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $width, $height, $cropWidth, $cropHeight);
      }
      imagedestroy($src);

      $this->gd_apply_tint($dst, (string)($plan['tint'] ?? ''), (float)($plan['tint_strength'] ?? 0));

      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/media/' . $plan['media_folder'];
      $dir = dbx()->os_path($dir);
      if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) throw new \RuntimeException('Medienordner konnte nicht erstellt werden.');
      $name = $this->unique_name($dir, $plan['file_name']);
      $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
      $this->gd_save_image($dst, $file, (string)$plan['mime'], (int)$plan['quality']);
      imagedestroy($dst);

      $relative = 'media/' . trim(str_replace('\\', '/', $plan['media_folder']), '/') . '/' . $name;
      $data = array_merge($plan['metadata'], array(
         'active' => 1,
         'file_name' => $name,
         'file_path' => $relative,
         'mime' => $plan['mime'],
         'size' => (int)@filesize($file),
         'width' => $width,
         'height' => $height,
         'media_type' => 'image',
         'storage_type' => 'local',
         'media_folder' => $plan['media_folder'],
      ));
      if ($this->db->insert('dbxMedia', $data) !== 1) {
         @unlink($file);
         throw new \RuntimeException('Medium konnte nicht registriert werden.');
      }
      $id = $this->db->get_insert_id();
      return array_merge(array('id' => $id, 'row' => $this->db->select1('dbxMedia', $id)), $this->media_inline_payload($id));
   }

   private function execute_media_update(array $plan): array {
      if ($this->db->update('dbxMedia', $plan['changes'], $plan['id']) !== 1) throw new \RuntimeException('Medium konnte nicht aktualisiert werden.');
      $this->invalidate_media_references((int)$plan['id']);
      return array('id' => $plan['id'], 'row' => $this->db->select1('dbxMedia', $plan['id']));
   }

   private function execute_media_assign(array $plan): array {
      $data = $plan['data'];
      $data['content_lng'] = dbxContentMediaUsageScope::language((string)($plan['lng'] ?? ''));
      if ($data['slot'] === 'hero') {
         $where = $data['content_id'] > 0
            ? 'content_id = ' . (int)$data['content_id']
            : 'folder_id = ' . (int)$data['folder_id'];
         $this->db->update('dbxMediaUsage', array('active' => 0), dbxContentMediaUsageScope::withLanguage($where . " AND slot = 'hero' AND active = 1", $data['content_lng']), 0, 1, 1, 1);
      }
      $data['sorter'] = $this->next_usage_sorter($data['content_id'], $data['folder_id'], $data['slot'], $data['content_lng']);
      if ($this->db->insert('dbxMediaUsage', $data) !== 1) throw new \RuntimeException('Medienzuordnung konnte nicht erstellt werden.');
      $id = $this->db->get_insert_id();
      if ($data['slot'] === 'hero') {
         $this->sync_hero_setting((string)($plan['lng'] ?? ''), $data);
      }
      $this->invalidate_usage($data);
      return array('usage_id' => $id, 'row' => $this->db->select1('dbxMediaUsage', $id));
   }

   private function sync_hero_setting(string $lng, array $usage): void {
      $mediaId = (int)($usage['media_id'] ?? 0);
      $contentId = (int)($usage['content_id'] ?? 0);
      $folderId = (int)($usage['folder_id'] ?? 0);
      if ($mediaId <= 0) {
         return;
      }
      if ($lng === '') {
         $lng = dbxContentLng::current();
      }

      if ($contentId > 0) {
         $dd = dbxContentLng::ddContent($lng);
         $page = $this->db->select1($dd, $contentId);
         if (!is_array($page)) {
            return;
         }
         $patch = array('hero_image_id' => (string)$mediaId);
         $heroTemplate = trim((string)($page['hero_template'] ?? ''));
         if ($heroTemplate === '' || $heroTemplate === 'parent') {
            $patch['hero_template'] = 'image-hero';
         }
         if ($this->db->update($dd, $patch, $contentId) !== 1) {
            return;
         }
         $row = $this->db->select1($dd, $contentId);
         if (is_array($row)) {
            $this->invalidate_page($contentId, $lng, $row);
         }
         return;
      }

      if ($folderId > 0) {
         $dd = dbxContentLng::ddFolder($lng);
         $folder = $this->db->select1($dd, $folderId);
         if (!is_array($folder)) {
            return;
         }
         $patch = array('hero_image_id' => (string)$mediaId);
         $heroTemplate = trim((string)($folder['hero_template'] ?? ''));
         if ($heroTemplate === '' || $heroTemplate === 'parent') {
            $patch['hero_template'] = 'image-hero';
         }
         if ($this->db->update($dd, $patch, $folderId) === 1) {
            $this->invalidate_folder($folderId);
         }
      }
   }

   private function execute_media_unassign(array $plan): array {
      if ($this->db->update('dbxMediaUsage', array('active' => 0), $plan['id']) !== 1) throw new \RuntimeException('Medienzuordnung konnte nicht entfernt werden.');
      $this->invalidate_usage($plan['before']);
      return array('unassigned' => true, 'usage_id' => $plan['id']);
   }

   private function execute_media_delete(array $plan): array {
      require_once dirname(__DIR__, 2) . '/dbxContent_admin/include/dbxContent_cms.class.php';
      $cms = new \dbx\dbxContent_admin\dbxContent_cms();
      $result = $cms->delete_media_record((int)$plan['id']);
      if ((int)($result['ok'] ?? 0) !== 1) {
         throw new \RuntimeException(implode(' ', is_array($result['errors'] ?? null) ? $result['errors'] : array('Medium konnte nicht gelöscht werden.')));
      }
      return $result;
   }

   private function execute_translation_apply(array $params, array $plan): array {
      $source = $plan['source'];
      $targetLng = $plan['target_lng'];
      $targetDd = dbxContentLng::ddContent($targetLng);
      $sourceUid = trim((string)($source['lng_uid'] ?? ''));
      if ($sourceUid === '') {
         $sourceUid = dbxContentLngSync::ensureRecordUid(
            $this->db,
            dbxContentLng::ddContent($plan['source_lng']),
            (int)$source['id'],
            'p'
         );
      }
      $targetFolder = dbxContentLngSync::ensureFolderIdInLng($this->db, (int)($source['folder'] ?? 0), $targetLng);
      $data = $this->copy_page_structure($source);
      $data = array_merge($data, $plan['translation']);
      $data['folder'] = $targetFolder;
      $data['permalink'] = dbxContent_permalink::build($this->db, dbxContentLng::ddFolder($targetLng), $targetFolder, $data['title']);
      $data['lng_uid'] = $sourceUid;
      $data['lng_sync'] = 'manual';
      $data['lng_rev'] = max(1, (int)($plan['target']['lng_rev'] ?? 0) + 1);
      $data['lng_synced_rev'] = (int)($source['lng_rev'] ?? 1);

      $targetId = (int)($plan['target']['id'] ?? 0);
      if ($targetId > 0) {
         if ($this->db->update($targetDd, $data, $targetId) !== 1) throw new \RuntimeException('Übersetzung konnte nicht aktualisiert werden.');
      } else {
         if ($this->db->insert($targetDd, $data) !== 1) throw new \RuntimeException('Übersetzung konnte nicht erstellt werden.');
         $targetId = $this->db->get_insert_id();
      }

      $mediaCopied = 0;
      if ($plan['copy_media']) {
         $this->db->update(
            'dbxMediaUsage',
            array('active' => 0),
            dbxContentMediaUsageScope::withLanguage('content_id = ' . $targetId . ' AND active = 1', $targetLng),
            0,
            1,
            1,
            1
         );
         $mediaCopied = $this->copy_media_usage((int)$source['id'], $targetId, $targetFolder, (string)$plan['source_lng'], $targetLng);
      }
      $row = $this->db->select1($targetDd, $targetId);
      $this->invalidate_page($targetId, $targetLng, $row);
      return array('id' => $targetId, 'lng' => $targetLng, 'row' => $row, 'media_copied' => $mediaCopied);
   }

   private function execute_translation_sync_all(array $plan): array {
      $sourceLng = (string)($plan['source_lng'] ?? '');
      $targetLngs = is_array($plan['target_lngs'] ?? null) ? $plan['target_lngs'] : array();
      $updateExisting = (bool)($plan['update_existing'] ?? true);
      $skipManual = (bool)($plan['skip_manual'] ?? false);
      $copyMedia = (bool)($plan['copy_media'] ?? true);
      $replaceMediaUsage = (bool)($plan['replace_media_usage'] ?? false);
      $sourceIds = is_array($plan['source_ids'] ?? null) ? $plan['source_ids'] : array();
      $folderIds = is_array($sourceIds['folders'] ?? null) ? array_map('intval', $sourceIds['folders']) : array();
      $pageIds = is_array($sourceIds['pages'] ?? null) ? array_map('intval', $sourceIds['pages']) : array();

      dbxContentTranslate::clearWarnings();

      $result = array(
         'source_lng' => $sourceLng,
         'target_lngs' => $targetLngs,
         'provider' => dbxContentTranslate::provider(),
         'folders' => array('created' => array(), 'updated' => array(), 'skipped' => array()),
         'pages' => array('created' => array(), 'updated' => array(), 'skipped' => array()),
         'media_copied' => 0,
         'errors' => array(),
         'warnings' => array(),
      );

      foreach ($targetLngs as $targetLng) {
         $targetLng = $this->language($targetLng);
         foreach ($folderIds as $folderId) {
            try {
               $item = $this->sync_translate_folder($sourceLng, $targetLng, $folderId, $updateExisting, $skipManual);
               $bucket = (string)($item['status'] ?? 'skipped');
               $result['folders'][$bucket === 'created' ? 'created' : ($bucket === 'updated' ? 'updated' : 'skipped')][] = $item;
            } catch (\Throwable $e) {
               $result['errors'][] = 'Ordner #' . $folderId . ' nach ' . strtoupper($targetLng) . ': ' . $e->getMessage();
            }
         }

         foreach ($pageIds as $pageId) {
            try {
               $item = $this->sync_translate_page($sourceLng, $targetLng, $pageId, $updateExisting, $skipManual, $copyMedia, $replaceMediaUsage);
               $bucket = (string)($item['status'] ?? 'skipped');
               $result['pages'][$bucket === 'created' ? 'created' : ($bucket === 'updated' ? 'updated' : 'skipped')][] = $item;
               $result['media_copied'] += (int)($item['media_copied'] ?? 0);
            } catch (\Throwable $e) {
               $result['errors'][] = 'Seite #' . $pageId . ' nach ' . strtoupper($targetLng) . ': ' . $e->getMessage();
            }
         }
      }

      $result['warnings'] = dbxContentTranslate::warnings();
      return $result;
   }

   private function sync_translate_folder(string $sourceLng, string $targetLng, int $sourceId, bool $updateExisting, bool $skipManual): array {
      $sourceDd = dbxContentLng::ddFolder($sourceLng);
      $targetDd = dbxContentLng::ddFolder($targetLng);
      $source = $this->db->select1($sourceDd, $sourceId);
      if (!is_array($source)) {
         throw new \RuntimeException('Quellordner nicht gefunden.');
      }

      $uid = dbxContentLngSync::ensureRecordUid($this->db, $sourceDd, $sourceId, 'f');
      if ($uid === '') {
         throw new \RuntimeException('Sprach-ID konnte nicht erzeugt werden.');
      }

      $targetId = dbxContentLngSync::resolveIdByUid($this->db, $targetDd, $uid, $targetLng);
      $target = $targetId > 0 ? $this->db->select1($targetDd, $targetId) : null;
      if (is_array($target) && !$updateExisting) {
         return array('status' => 'skipped', 'reason' => 'exists', 'entity' => 'folder', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId);
      }
      if (is_array($target) && $skipManual && strtolower(trim((string)($target['lng_sync'] ?? ''))) === 'manual') {
         return array('status' => 'skipped', 'reason' => 'manual', 'entity' => 'folder', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId);
      }

      $name = dbxContentTranslate::translate((string)($source['name'] ?? ''), $sourceLng, $targetLng, 'folder_name');
      if ($name === '' && trim((string)($source['name'] ?? '')) !== '') {
         $name = (string)$source['name'];
      }
      if ($name === '') {
         $name = 'Ordner';
      }

      $data = $this->copy_folder_structure($source);
      $data['name'] = $this->clean($name, 120);
      $data['parent_id'] = $this->target_folder_id_from_source_parent($sourceLng, $targetLng, (int)($source['parent_id'] ?? 0));
      $data['lng_uid'] = $uid;
      $data['lng_sync'] = 'auto';
      $data['lng_rev'] = is_array($target) ? max(1, (int)($target['lng_rev'] ?? 0) + 1) : 0;
      $data['lng_synced_rev'] = max(1, (int)($source['lng_rev'] ?? 1));

      if ($targetId > 0) {
         if ($this->db->update($targetDd, $data, $targetId) !== 1) {
            throw new \RuntimeException('Zielordner konnte nicht aktualisiert werden.');
         }
         $status = 'updated';
      } else {
         if ($this->db->insert($targetDd, $data) !== 1) {
            throw new \RuntimeException('Zielordner konnte nicht erstellt werden.');
         }
         $targetId = (int)$this->db->get_insert_id();
         $status = 'created';
      }

      $this->invalidate_folder($targetId);
      return array('status' => $status, 'entity' => 'folder', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId, 'name' => $data['name']);
   }

   private function sync_translate_page(string $sourceLng, string $targetLng, int $sourceId, bool $updateExisting, bool $skipManual, bool $copyMedia, bool $replaceMediaUsage): array {
      $sourceDd = dbxContentLng::ddContent($sourceLng);
      $targetDd = dbxContentLng::ddContent($targetLng);
      $source = $this->db->select1($sourceDd, $sourceId);
      if (!is_array($source)) {
         throw new \RuntimeException('Quellseite nicht gefunden.');
      }

      $uid = dbxContentLngSync::ensureRecordUid($this->db, $sourceDd, $sourceId, 'p');
      if ($uid === '') {
         throw new \RuntimeException('Sprach-ID konnte nicht erzeugt werden.');
      }

      $targetId = dbxContentLngSync::resolveIdByUid($this->db, $targetDd, $uid, $targetLng);
      $target = $targetId > 0 ? $this->db->select1($targetDd, $targetId) : null;
      if (is_array($target) && !$updateExisting) {
         return array('status' => 'skipped', 'reason' => 'exists', 'entity' => 'page', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId);
      }
      if (is_array($target) && $skipManual && strtolower(trim((string)($target['lng_sync'] ?? ''))) === 'manual') {
         return array('status' => 'skipped', 'reason' => 'manual', 'entity' => 'page', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId);
      }

      $title = dbxContentTranslate::translate((string)($source['title'] ?? ''), $sourceLng, $targetLng, 'title');
      if ($title === '' && trim((string)($source['title'] ?? '')) !== '') {
         $title = (string)$source['title'];
      }
      if ($title === '') {
         throw new \RuntimeException('Übersetzter Titel ist leer.');
      }

      $targetFolder = $this->target_folder_id_from_source_parent($sourceLng, $targetLng, (int)($source['folder'] ?? 0));
      if ((int)($source['folder'] ?? 0) > 0 && $targetFolder <= 0) {
         throw new \RuntimeException('Zielordner konnte nicht aufgelöst werden.');
      }

      $data = $this->copy_page_structure($source);
      $data['folder'] = $targetFolder;
      $data['title'] = $this->clean($title, 254);
      $data['description'] = $this->clean(dbxContentTranslate::translate((string)($source['description'] ?? ''), $sourceLng, $targetLng, 'description'), 254);
      $data['keywords'] = $this->clean(dbxContentTranslate::translate((string)($source['keywords'] ?? ''), $sourceLng, $targetLng, 'keywords'), 254);
      $data['content'] = $this->normalize_content_inline_media_urls(dbxContentTranslate::translate((string)($source['content'] ?? ''), $sourceLng, $targetLng, 'content'));
      foreach (array('seo_title', 'img_alt_1', 'img_alt_2', 'img_alt_3', 'img_des_1', 'img_des_2', 'img_des_3') as $field) {
         if (array_key_exists($field, $source)) {
            $max = $field === 'seo_title' || strpos($field, 'img_alt_') === 0 ? 254 : 0;
            $data[$field] = $this->clean(dbxContentTranslate::translate((string)($source[$field] ?? ''), $sourceLng, $targetLng, $field), $max);
         }
      }
      $data['permalink'] = dbxContent_permalink::build($this->db, dbxContentLng::ddFolder($targetLng), $targetFolder, $data['title']);
      $data['lng_uid'] = $uid;
      $data['lng_sync'] = 'auto';
      $data['lng_rev'] = is_array($target) ? max(1, (int)($target['lng_rev'] ?? 0) + 1) : 0;
      $data['lng_synced_rev'] = max(1, (int)($source['lng_rev'] ?? 1));

      if ($targetId > 0) {
         if ($this->db->update($targetDd, $data, $targetId) !== 1) {
            throw new \RuntimeException('Zielseite konnte nicht aktualisiert werden.');
         }
         $status = 'updated';
      } else {
         if ($this->db->insert($targetDd, $data) !== 1) {
            throw new \RuntimeException('Zielseite konnte nicht erstellt werden.');
         }
         $targetId = (int)$this->db->get_insert_id();
         $status = 'created';
      }

      $mediaCopied = 0;
      if ($copyMedia) {
         if ($replaceMediaUsage) {
            $this->db->update('dbxMediaUsage', array('active' => 0), dbxContentMediaUsageScope::withLanguage('content_id = ' . $targetId . ' AND active = 1', $targetLng), 0, 1, 1, 1);
            $mediaCopied = $this->copy_media_usage($sourceId, $targetId, $targetFolder, $sourceLng, $targetLng);
         } else {
            $mediaCopied = $this->copy_missing_media_usage($sourceId, $targetId, $targetFolder, $sourceLng, $targetLng);
         }
      }

      $row = $this->db->select1($targetDd, $targetId);
      $this->invalidate_page($targetId, $targetLng, $row);
      return array('status' => $status, 'entity' => 'page', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId, 'title' => $data['title'], 'media_copied' => $mediaCopied);
   }

   private function target_languages(array $params, string $sourceLng): array {
      $raw = $params['target_lngs'] ?? $params['target_lng'] ?? array();
      if (is_string($raw)) {
         $raw = array_values(array_filter(array_map('trim', explode(',', $raw))));
      } elseif (!is_array($raw)) {
         $raw = array();
      }
      if (!count($raw)) {
         $raw = dbxContentLngSync::accessibleLngs();
      }

      $out = array();
      foreach ($raw as $lng) {
         $lng = $this->language($lng);
         if ($lng === $sourceLng || in_array($lng, $out, true)) {
            continue;
         }
         $out[] = $lng;
      }
      return $out;
   }

   private function collect_folder_ids_for_lng(string $lng, int $rootFolderId = 0): array {
      $dd = dbxContentLng::ddFolder($lng);
      if ($rootFolderId <= 0) {
         $rows = $this->db->select($dd, '', 'id', 'parent_id,sorter,id', 'ASC', '', 0, 0, 0);
         return $this->ids_from_rows($rows);
      }

      $out = array();
      $seen = array();
      $queue = array($rootFolderId);
      while (count($queue)) {
         $id = (int)array_shift($queue);
         if ($id <= 0 || isset($seen[$id])) {
            continue;
         }
         $seen[$id] = 1;
         $out[] = $id;
         $rows = $this->db->select($dd, 'parent_id = ' . $id, 'id', 'sorter,id', 'ASC', '', 0, 0, 0);
         foreach ($this->ids_from_rows($rows) as $childId) {
            if (!isset($seen[$childId])) {
               $queue[] = $childId;
            }
         }
      }
      return $out;
   }

   private function collect_page_ids_for_lng(string $lng, int $rootFolderId, array $folderIds): array {
      $dd = dbxContentLng::ddContent($lng);
      if ($rootFolderId <= 0) {
         $rows = $this->db->select($dd, '', 'id', 'folder,sorter,id', 'ASC', '', 0, 0, 0);
         return $this->ids_from_rows($rows);
      }

      $folderIds = array_values(array_filter(array_map('intval', $folderIds), static function($id) {
         return $id > 0;
      }));
      if (!count($folderIds)) {
         return array();
      }
      $rows = $this->db->select($dd, 'folder IN (' . implode(',', $folderIds) . ')', 'id', 'folder,sorter,id', 'ASC', '', 0, 0, 0);
      return $this->ids_from_rows($rows);
   }

   private function ids_from_rows($rows): array {
      $out = array();
      foreach (is_array($rows) ? $rows : array() as $row) {
         if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
            $out[] = (int)$row['id'];
         }
      }
      return $out;
   }

   private function target_folder_id_from_source_parent(string $sourceLng, string $targetLng, int $sourceFolderId): int {
      $sourceFolderId = (int)$sourceFolderId;
      if ($sourceFolderId <= 0) {
         return 0;
      }
      $sourceDd = dbxContentLng::ddFolder($sourceLng);
      $source = $this->db->select1($sourceDd, $sourceFolderId);
      if (!is_array($source)) {
         return 0;
      }

      $uid = dbxContentLngSync::ensureRecordUid($this->db, $sourceDd, $sourceFolderId, 'f');
      if ($uid === '') {
         return 0;
      }
      $targetDd = dbxContentLng::ddFolder($targetLng);
      $targetId = dbxContentLngSync::resolveIdByUid($this->db, $targetDd, $uid, $targetLng);
      if ($targetId > 0) {
         return $targetId;
      }

      try {
         $created = $this->sync_translate_folder($sourceLng, $targetLng, $sourceFolderId, true, false);
         return (int)($created['target_id'] ?? 0);
      } catch (\Throwable $e) {
         return 0;
      }
   }

   private function copy_page_structure(array $source): array {
      $skip = array('id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner', 'title', 'permalink', 'description', 'keywords', 'content', 'lng_uid', 'lng_sync', 'lng_rev', 'lng_synced_rev');
      $data = array();
      foreach ($source as $key => $value) {
         if (!in_array($key, $skip, true)) {
            $data[$key] = $value;
         }
      }
      return $data;
   }

   private function copy_folder_structure(array $source): array {
      $skip = array('id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner', 'name', 'parent_id', 'lng_uid', 'lng_sync', 'lng_rev', 'lng_synced_rev');
      $data = array();
      foreach ($source as $key => $value) {
         if (!in_array($key, $skip, true)) {
            $data[$key] = $value;
         }
      }
      return $data;
   }

   private function copy_missing_media_usage(int $sourceId, int $targetId, int $targetFolder, string $sourceLng, string $targetLng): int {
      $rows = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage("content_id = " . $sourceId . " AND active = 1 AND slot IN ('hero','gallery','inline','header','teaser','footer')", $sourceLng), '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      $count = 0;
      foreach (is_array($rows) ? $rows : array() as $row) {
         if (!is_array($row)) {
            continue;
         }
         $mediaId = (int)($row['media_id'] ?? 0);
         $slot = str_replace("'", "''", (string)($row['slot'] ?? ''));
         if ($mediaId <= 0 || $slot === '') {
            continue;
         }
         if ($slot === 'hero' && (int)$this->db->count('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage('content_id = ' . $targetId . " AND slot = 'hero' AND active = 1", $targetLng)) > 0) {
            continue;
         }
         $exists = (int)$this->db->count('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage('content_id = ' . $targetId . ' AND media_id = ' . $mediaId . " AND slot = '" . $slot . "' AND active = 1", $targetLng));
         if ($exists > 0) {
            continue;
         }
         $data = $this->whitelist($row, array('media_id', 'slot', 'sorter', 'template', 'caption', 'settings'));
         $data['active'] = 1;
         $data['content_id'] = $targetId;
         $data['folder_id'] = $targetFolder;
         $data['content_lng'] = dbxContentMediaUsageScope::language($targetLng);
         if ($this->db->insert('dbxMediaUsage', $data) === 1) {
            $count++;
         }
      }
      return $count;
   }

   private function folder_data(array $params, int $parent, string $name): array {
      return array(
         'name' => $name,
         'parent_id' => $parent,
         'group_read' => $this->clean($params['group_read'] ?? ($parent > 0 ? 'parent' : '*'), 512),
         'template' => $this->clean($params['template'] ?? ($parent > 0 ? 'parent' : 'c-content'), 254),
         'hero_template' => $this->clean($params['hero_template'] ?? ($parent > 0 ? 'parent' : 'image-hero'), 80),
         'hero_image_id' => $this->clean($params['hero_image_id'] ?? 'parent', 32),
         'hero_margin_top' => $this->clean($params['hero_margin_top'] ?? 'parent', 32),
         'hero_height' => $this->clean($params['hero_height'] ?? ($parent > 0 ? 'parent' : '300px'), 32),
         'hero_variant' => $this->clean($params['hero_variant'] ?? 'parent', 32),
         'hero_sticky' => $this->clean($params['hero_sticky'] ?? 'parent', 32),
         'hero_scroll_layer' => $this->clean($params['hero_scroll_layer'] ?? 'parent', 32),
      );
   }

   private function page_data(array $params, string $lng, int $folder, string $title): array {
      $permalink = trim($this->clean($params['permalink'] ?? '', 254));
      if ($permalink === '') {
         $permalink = dbxContent_permalink::build($this->db, dbxContentLng::ddFolder($lng), $folder, $title);
      } else {
         if (!dbxContent_permalink::isValid($permalink)) {
            throw new \InvalidArgumentException('permalink darf nur Kleinbuchstaben, Zahlen und einzelne Bindestriche enthalten.');
         }
         if (dbxContent_permalink::exists($this->db, dbxContentLng::ddContent($lng), $permalink)) {
            throw new \InvalidArgumentException('permalink wird bereits von einer anderen Seite verwendet.');
         }
      }
      return array(
         'activ' => $this->bool_value($params['activ'] ?? true) ? 1 : 0,
          'folder' => $folder,
          'title' => $title,
          'menu_title' => $this->clean($params['menu_title'] ?? '', 96),
          'seo_title' => $this->clean($params['seo_title'] ?? $title, 254),
          'permalink' => $permalink,
         'description' => $this->clean($params['description'] ?? '', 254),
         'keywords' => $this->clean($params['keywords'] ?? '', 254),
         'group_read' => $this->clean($params['group_read'] ?? 'parent', 512),
         'template' => $this->clean($params['template'] ?? 'parent', 254),
         'hero_template' => $this->clean($params['hero_template'] ?? 'parent', 80),
         'hero_image_id' => $this->clean($params['hero_image_id'] ?? 'parent', 32),
         'hero_margin_top' => $this->clean($params['hero_margin_top'] ?? 'parent', 32),
         'hero_height' => $this->clean($params['hero_height'] ?? '300px', 32),
         'hero_variant' => $this->clean($params['hero_variant'] ?? 'parent', 32),
         'hero_sticky' => $this->clean($params['hero_sticky'] ?? 'parent', 32),
         'hero_scroll_layer' => $this->clean($params['hero_scroll_layer'] ?? 'parent', 32),
         'gallery_template' => $this->clean($params['gallery_template'] ?? 'image-gallery', 80),
         'gallery_visible_count' => $this->clean($params['gallery_visible_count'] ?? '3', 32),
         'gallery_image_size' => $this->clean($params['gallery_image_size'] ?? 'original', 32),
         'gallery_lightbox_width' => $this->clean($params['gallery_lightbox_width'] ?? '100vw', 32),
         'gallery_overflow' => $this->clean($params['gallery_overflow'] ?? 'grid', 32),
         'gallery_click_behavior' => $this->clean($params['gallery_click_behavior'] ?? 'lightbox', 32),
         'sorter' => $this->clean($params['sorter'] ?? '', 32),
          'content' => $this->normalize_content_inline_media_urls((string)($params['content'] ?? '')),
       );
    }

   /**
    * Verhindert einen im Content nachgebauten Hero.
    *
    * Ein Bild mit umfangreicher absoluter Textebene im ersten Inhaltsblock
    * muss die vorhandene CMS-Hero-Logik verwenden. Kleine Badges auf Karten
    * bleiben erlaubt.
    */
   private function assert_no_fake_inline_hero(string $html): void {
      if (stripos($html, '<img') === false || stripos($html, 'position') === false) {
         return;
      }

      $doc = new \DOMDocument('1.0', 'UTF-8');
      $previous = libxml_use_internal_errors(true);
      try {
         $loaded = $doc->loadHTML(
            '<div data-dbx-ki-content-root="1">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
         );
      } finally {
         libxml_clear_errors();
         libxml_use_internal_errors($previous);
      }
      if (!$loaded) {
         return;
      }

      $xpath = new \DOMXPath($doc);
      $roots = $xpath->query('//*[@data-dbx-ki-content-root="1"]');
      $root = $roots instanceof \DOMNodeList ? $roots->item(0) : null;
      if (!$root instanceof \DOMElement) {
         return;
      }
      $first = null;
      foreach ($root->childNodes as $child) {
         if ($child instanceof \DOMElement) {
            $first = $child;
            break;
         }
      }
      if (!$first instanceof \DOMElement) {
         return;
      }

      $images = $first->getElementsByTagName('img');
      foreach ($images as $image) {
         $host = $image->parentNode;
         while ($host instanceof \DOMElement && $host !== $root) {
            $class = ' ' . strtolower($host->getAttribute('class')) . ' ';
            $style = strtolower($host->getAttribute('style'));
            $relative = str_contains($class, ' position-relative ')
               || preg_match('/position\s*:\s*relative/i', $style) === 1;
            if ($relative) {
               foreach ($host->getElementsByTagName('*') as $candidate) {
                  if ($candidate === $image || !$candidate instanceof \DOMElement) {
                     continue;
                  }
                  $candidateClass = ' ' . strtolower($candidate->getAttribute('class')) . ' ';
                  $candidateStyle = strtolower($candidate->getAttribute('style'));
                  $absolute = str_contains($candidateClass, ' position-absolute ')
                     || preg_match('/position\s*:\s*absolute/i', $candidateStyle) === 1;
                  $text = trim(preg_replace('/\s+/u', ' ', $candidate->textContent ?? '') ?? '');
                  $structuredText = $candidate->getElementsByTagName('h1')->length
                     + $candidate->getElementsByTagName('h2')->length
                     + $candidate->getElementsByTagName('p')->length
                     + $candidate->getElementsByTagName('a')->length;
                  if ($absolute && (mb_strlen($text) >= 80 || $structuredText >= 2)) {
                     throw new \InvalidArgumentException(
                        'dbxKi: Ein Bild mit ueberlagertem Text am Seitenanfang ist ein CMS-Hero. '
                        . 'Hero-Bild ueber hero_image_id/media.assign slot=hero setzen und den Hero-Text '
                        . 'vor den dbx:hero-Marker schreiben; kein Inline-Schein-Hero.'
                     );
                  }
               }
            }
            if ($host === $first) {
               break;
            }
            $host = $host->parentNode;
         }
      }
   }

   private function patch(array $params): array {
      $patch = is_array($params['patch'] ?? null) ? $params['patch'] : $params;
      foreach (array('id', 'lng', 'patch', 'folder_id') as $key) {
         if ($key !== 'folder_id') unset($patch[$key]);
      }
      return $patch;
   }

   private function whitelist(array $data, array $allowed): array {
      return array_intersect_key($data, array_flip($allowed));
   }

   private function lng_fields(string $prefix, string $lng): array {
      return array(
         'lng_uid' => dbxContentLngSync::newUid($prefix),
         'lng_sync' => $lng === dbxContentLngSync::masterLng() ? 'auto' : 'manual',
         'lng_rev' => 1,
         'lng_synced_rev' => 0,
      );
   }

   private function advance_revision(string $dd, int $id, array $data, string $lng): array {
      $row = $this->db->select1($dd, $id, 'lng_uid,lng_rev', 0);
      $uid = trim((string)($row['lng_uid'] ?? ''));
      if ($uid === '') $uid = dbxContentLngSync::newUid(strpos($dd, 'folder') !== false ? 'f' : 'p');
      $data['lng_uid'] = $uid;
      $data['lng_rev'] = max(1, (int)($row['lng_rev'] ?? 0)) + 1;
      if ($lng !== dbxContentLngSync::masterLng()) $data['lng_sync'] = 'manual';
      return $data;
   }

   private function next_sorter(string $dd, string $field, int $parent): string {
      $rows = $this->db->select($dd, $field . ' = ' . $parent, 'sorter,id', 'sorter DESC,id DESC', 'ASC', '', 1, 0, 0);
      $max = is_array($rows) && isset($rows[0]) ? (int)($rows[0]['sorter'] ?? 0) : 0;
      return sprintf('%04d', $max + 10);
   }

   private function next_usage_sorter(int $content, int $folder, string $slot, string $lng = ''): string {
      $where = "active = 1 AND slot = '" . str_replace("'", "''", $slot) . "'";
      if ($content > 0) $where .= ' AND content_id = ' . $content;
      if ($folder > 0) $where .= ' AND folder_id = ' . $folder;
      $where = dbxContentMediaUsageScope::withLanguage($where, $lng);
      $rows = $this->db->select('dbxMediaUsage', $where, 'sorter,id', 'sorter DESC,id DESC', 'ASC', '', 1, 0, 0);
      $max = is_array($rows) && isset($rows[0]) ? (int)($rows[0]['sorter'] ?? 0) : 0;
      return sprintf('%04d', $max + 10);
   }

   private function folder_descendant(string $dd, int $candidate, int $ancestor): bool {
      $seen = array();
      while ($candidate > 0 && !isset($seen[$candidate])) {
         if ($candidate === $ancestor) return true;
         $seen[$candidate] = 1;
         $row = $this->db->select1($dd, $candidate, 'parent_id', 0);
         if (!is_array($row)) break;
         $candidate = (int)($row['parent_id'] ?? 0);
      }
      return false;
   }

   private function slot($value): string {
      $slot = strtolower(trim((string)$value));
      $allowed = array('hero', 'gallery', 'inline', 'header', 'teaser', 'footer');
      if (!in_array($slot, $allowed, true)) throw new \InvalidArgumentException('Ungültiger Medienslot: ' . $slot);
      return $slot;
   }

   private function safe_file_name($value): string {
      $name = basename(str_replace('\\', '/', trim((string)$value)));
      $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
      return trim((string)$name, '.-');
   }

   private function decode_base64(string $raw): string {
      $raw = trim($raw);
      if (preg_match('~^data:[^;]+;base64,(.*)$~s', $raw, $match)) $raw = $match[1];
      $decoded = base64_decode(preg_replace('/\s+/', '', $raw), true);
      if ($decoded === false) throw new \InvalidArgumentException('data_base64 ist ungültig.');
      return $decoded;
   }

   private function detect_mime(string $bytes, string $name): string {
      if (class_exists('\finfo')) {
         $finfo = new \finfo(FILEINFO_MIME_TYPE);
         $mime = (string)$finfo->buffer($bytes);
         if ($mime !== '') return $mime;
      }
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $map = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf', 'txt' => 'text/plain');
      return $map[$ext] ?? 'application/octet-stream';
   }

   private function resolve_local_file(string $path): string {
      $path = trim(str_replace('\\', '/', $path));
      if ($path === '') return '';
      if (!preg_match('~^(?:[A-Za-z]:/|/)~', $path)) {
         $path = rtrim(str_replace('\\', '/', dbx()->get_base_dir()), '/') . '/' . ltrim($path, '/');
      }
      return dbx()->os_path($path);
   }

   private function media_local_file(array $media): string {
      if (($media['storage_type'] ?? 'local') !== 'local') return '';
      $filePath = trim((string)($media['file_path'] ?? ''));
      if ($filePath === '') return '';
      $filePath = preg_replace('~^files/~', '', str_replace('\\', '/', $filePath));
      if (strpos($filePath, 'media/') !== 0) return '';
      return dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/' . $filePath);
   }

   private function hero_media_for_page(string $lng, int $id): array {
      $page = $this->db->select1(dbxContentLng::ddContent($lng), $id);
      if (!is_array($page)) throw new \RuntimeException('Seite nicht gefunden.');

      $usage = array();
      $mediaId = (int)($page['hero_image_id'] ?? 0);
      if ($mediaId <= 0) {
         $rows = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage('content_id = ' . $id . " AND slot = 'hero' AND active = 1", $lng), '*', 'sorter,id', 'DESC', '', 1, 0, 0);
         if (is_array($rows) && is_array($rows[0] ?? null)) {
            $usage = $rows[0];
            $mediaId = (int)($usage['media_id'] ?? 0);
         }
      }
      if ($mediaId <= 0) throw new \RuntimeException('Die Seite hat kein bestehendes Hero-Bild.');

      $media = $this->db->select1('dbxMedia', $mediaId);
      if (!is_array($media) || (int)($media['active'] ?? 0) !== 1) throw new \RuntimeException('Hero-Medium nicht gefunden.');
      if (!$usage) {
         $rows = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage('content_id = ' . $id . ' AND media_id = ' . $mediaId . " AND slot = 'hero' AND active = 1", $lng), '*', 'sorter,id', 'DESC', '', 1, 0, 0);
         if (is_array($rows) && is_array($rows[0] ?? null)) $usage = $rows[0];
      }
      return array('page' => $page, 'media' => $media, 'usage' => $usage);
   }

   private function source_image_plan(array $params): array {
      $source = $this->resolve_local_file((string)($params['source_file'] ?? ''));
      if ($source === '' || !is_file($source) || !is_readable($source)) {
         throw new \InvalidArgumentException('source_file ist nicht lesbar.');
      }
      $info = @getimagesize($source);
      if (!is_array($info) || empty($info[0]) || empty($info[1])) {
         throw new \InvalidArgumentException('source_file ist kein lesbares Bild.');
      }
      $mime = (string)($info['mime'] ?? '');
      if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp', 'image/gif'), true)) {
         throw new \InvalidArgumentException('Nicht unterstützter Quellbildtyp: ' . $mime);
      }
      return array(
         'file' => $source,
         'sha256' => hash_file('sha256', $source),
         'mime' => $mime,
         'width' => (int)$info[0],
         'height' => (int)$info[1],
         'crop' => $this->image_crop_rect($params, (int)$info[0], (int)$info[1]),
         'tint' => $this->normalize_hex_color((string)($params['tint'] ?? '')),
         'tint_strength' => max(0.0, min(1.0, (float)($params['tint_strength'] ?? 0))),
      );
   }

   private function image_fit($value): string {
      $fit = strtolower(trim((string)$value));
      return in_array($fit, array('cover', 'contain'), true) ? $fit : 'cover';
   }

   private function image_quality($value): int {
      return min(100, max(1, (int)$value));
   }

   private function image_crop_rect(array $params, int $sourceWidth, int $sourceHeight): array {
      $sourceWidth = max(1, $sourceWidth);
      $sourceHeight = max(1, $sourceHeight);
      $x = (int)($params['crop_x'] ?? 0);
      $y = (int)($params['crop_y'] ?? 0);
      $width = (int)($params['crop_width'] ?? $sourceWidth);
      $height = (int)($params['crop_height'] ?? $sourceHeight);

      $x = max(0, min($x, $sourceWidth - 1));
      $y = max(0, min($y, $sourceHeight - 1));
      $width = max(1, min($width, $sourceWidth - $x));
      $height = max(1, min($height, $sourceHeight - $y));

      return array('x' => $x, 'y' => $y, 'width' => $width, 'height' => $height);
   }

   private function mime_from_file_name(string $name): string {
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $map = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp');
      return $map[$ext] ?? 'image/webp';
   }

   private function normalize_hex_color(string $value): string {
      $value = trim($value);
      if ($value === '') return '';
      if ($value[0] !== '#') $value = '#' . $value;
      return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : '';
   }

   private function gd_load_image(string $file, string $mime) {
      switch ($mime) {
         case 'image/jpeg':
            $image = @imagecreatefromjpeg($file);
            break;
         case 'image/png':
            $image = @imagecreatefrompng($file);
            break;
         case 'image/webp':
            $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
            break;
         case 'image/gif':
            $image = @imagecreatefromgif($file);
            break;
         default:
            $image = false;
      }
      if (!$image) throw new \RuntimeException('Bild konnte nicht geladen werden.');
      imagealphablending($image, true);
      imagesavealpha($image, true);
      return $image;
   }

   private function render_image_variant_to_file(array $source, string $file, int $width, int $height, string $fit, string $mime, int $quality): void {
      if (!hash_equals((string)($source['sha256'] ?? ''), hash_file('sha256', (string)$source['file']))) {
         throw new \RuntimeException('Das Quellbild stimmt nicht mehr mit dem geprüften Plan überein.');
      }
      $src = $this->gd_load_image((string)$source['file'], (string)$source['mime']);
      $dst = imagecreatetruecolor($width, $height);
      imagealphablending($dst, false);
      imagesavealpha($dst, true);
      $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
      imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);

      $sourceWidth = imagesx($src);
      $sourceHeight = imagesy($src);
      $sourceX = 0;
      $sourceY = 0;
      $crop = is_array($source['crop'] ?? null) ? $source['crop'] : array();
      if ($crop) {
         $sourceX = max(0, min((int)($crop['x'] ?? 0), $sourceWidth - 1));
         $sourceY = max(0, min((int)($crop['y'] ?? 0), $sourceHeight - 1));
         $sourceWidth = max(1, min((int)($crop['width'] ?? $sourceWidth), imagesx($src) - $sourceX));
         $sourceHeight = max(1, min((int)($crop['height'] ?? $sourceHeight), imagesy($src) - $sourceY));
      }
      if ($fit === 'contain') {
         $scale = min($width / $sourceWidth, $height / $sourceHeight);
         $copyWidth = max(1, (int)round($sourceWidth * $scale));
         $copyHeight = max(1, (int)round($sourceHeight * $scale));
         imagecopyresampled($dst, $src, (int)floor(($width - $copyWidth) / 2), (int)floor(($height - $copyHeight) / 2), $sourceX, $sourceY, $copyWidth, $copyHeight, $sourceWidth, $sourceHeight);
      } else {
         $sourceRatio = $sourceWidth / $sourceHeight;
         $targetRatio = $width / $height;
         if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int)round($sourceHeight * $targetRatio);
            $srcX = $sourceX + (int)floor(($sourceWidth - $cropWidth) / 2);
            $srcY = $sourceY;
         } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int)round($sourceWidth / $targetRatio);
            $srcX = $sourceX;
            $srcY = $sourceY + (int)floor(($sourceHeight - $cropHeight) / 2);
         }
         imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $width, $height, $cropWidth, $cropHeight);
      }
      imagedestroy($src);
      $this->gd_apply_tint($dst, (string)($source['tint'] ?? ''), (float)($source['tint_strength'] ?? 0));
      $this->gd_save_image($dst, $file, $mime, $quality);
      imagedestroy($dst);
   }

   private function gd_apply_tint($image, string $hex, float $strength): void {
      if ($hex === '' || $strength <= 0) return;
      $r = hexdec(substr($hex, 1, 2));
      $g = hexdec(substr($hex, 3, 2));
      $b = hexdec(substr($hex, 5, 2));
      $overlay = imagecreatetruecolor(imagesx($image), imagesy($image));
      imagealphablending($overlay, false);
      imagesavealpha($overlay, true);
      $color = imagecolorallocate($overlay, $r, $g, $b);
      imagefilledrectangle($overlay, 0, 0, imagesx($overlay), imagesy($overlay), $color);
      imagecopymerge($image, $overlay, 0, 0, 0, 0, imagesx($image), imagesy($image), (int)round($strength * 100));
      imagedestroy($overlay);
   }

   private function gd_save_image($image, string $file, string $mime, int $quality): void {
      $ok = false;
      if ($mime === 'image/webp' && function_exists('imagewebp')) {
         $ok = @imagewebp($image, $file, $quality);
      } elseif ($mime === 'image/png') {
         $compression = (int)round((100 - $quality) / 100 * 9);
         $ok = @imagepng($image, $file, max(0, min(9, $compression)));
      } elseif ($mime === 'image/jpeg') {
         $white = imagecreatetruecolor(imagesx($image), imagesy($image));
         $bg = imagecolorallocate($white, 255, 255, 255);
         imagefilledrectangle($white, 0, 0, imagesx($white), imagesy($white), $bg);
         imagecopy($white, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
         $ok = @imagejpeg($white, $file, $quality);
         imagedestroy($white);
      }
      if (!$ok) throw new \RuntimeException('Bildvariante konnte nicht gespeichert werden.');
   }

   private function media_folder($value, string $type): string {
      $folder = trim(str_replace('\\', '/', (string)$value), '/');
      $folder = preg_replace('~[^A-Za-z0-9/_-]+~', '-', $folder);
      if ($type === 'video') {
         return 'img/video';
      }
      $root = $type === 'image' ? 'img' : 'file';
      if ($folder === '' || ($folder !== $root && strpos($folder, $root . '/') !== 0)) $folder = $type === 'image' ? 'img/images' : 'file/ki';
      return $folder;
   }

   private function unique_name(string $dir, string $name): string {
      $base = pathinfo($name, PATHINFO_FILENAME);
      $ext = pathinfo($name, PATHINFO_EXTENSION);
      $candidate = $name;
      $i = 1;
      while (is_file(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $candidate)) {
         $candidate = $base . '-' . $i++ . ($ext !== '' ? '.' . $ext : '');
      }
      return $candidate;
   }

   private function invalidate_page(int $id, string $lng, array $row): void {
      dbxContentPageCache::invalidateContent($id);
      dbxContentPageCache::invalidateAllMenus();
      $previousLng = dbx()->get_system_var('dbx_lng', dbxContentLngSync::masterLng());
      dbx()->set_system_var('dbx_lng', $lng);
      $renderer = new dbxContentRenderer();
      $rights = $renderer->getPublicFolderRights((int)($row['folder'] ?? 0));
      if ((int)($row['activ'] ?? 1) === 1 && trim((string)($row['permalink'] ?? '')) !== '') {
         dbxContentPermalinkIndex::upsertPage($id, (string)$row['permalink'], $rights, 1, $lng);
      } else {
         dbxContentPermalinkIndex::removeByCid($id, $lng);
      }
      dbxContentHome::refreshHomeCache($this->db, $id, $lng);
      dbx()->set_system_var('dbx_lng', $previousLng);
   }

   private function invalidate_folder(int $id): void {
      dbxContentPageCache::invalidateFolderTree($this->db, $id);
      dbxContentPageCache::invalidateAllMenus();
   }

   private function invalidate_usage(array $usage): void {
      $content = (int)($usage['content_id'] ?? 0);
      $folder = (int)($usage['folder_id'] ?? 0);
      if ($content > 0) dbxContentPageCache::invalidateContent($content);
      if ($folder > 0) dbxContentPageCache::invalidateFolderTree($this->db, $folder);
      dbxContentPageCache::invalidateAllMenus();
   }

   private function invalidate_media_references(int $mediaId): void {
      $mediaId = (int)$mediaId;
      if ($mediaId <= 0) {
         return;
      }

      $rows = $this->db->select('dbxMediaUsage', 'media_id = ' . $mediaId . ' AND active = 1', '*', 'id', 'ASC', '', 0, 0, 0);
      foreach (is_array($rows) ? $rows : array() as $row) {
         if (is_array($row)) {
            $this->invalidate_usage($row);
         }
      }
   }

   private function copy_media_usage(int $sourceId, int $targetId, int $targetFolder, string $sourceLng, string $targetLng): int {
      $rows = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage("content_id = " . $sourceId . " AND active = 1 AND slot IN ('hero','gallery','inline','header','teaser','footer')", $sourceLng), '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      $count = 0;
      foreach (is_array($rows) ? $rows : array() as $row) {
         $data = $this->whitelist($row, array('media_id', 'slot', 'sorter', 'template', 'caption', 'settings'));
         $data['active'] = 1;
         $data['content_id'] = $targetId;
         $data['folder_id'] = $targetFolder;
         $data['content_lng'] = dbxContentMediaUsageScope::language($targetLng);
         if ($this->db->insert('dbxMediaUsage', $data) === 1) $count++;
      }
      return $count;
   }

   private function inline_media_src(int $id): string {
      return 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . max(0, (int)$id);
   }

   private function package_media_file_map(): array {
      return array(
         'dbxapp-paket-demo' => 'paket-demo-360x480.webp',
         'dbxapp-paket-non-profit' => 'paket-nonprofit-360x480.webp',
         'dbxapp-paket-business' => 'paket-business-360x480.webp',
         'dbxapp-paket-intranet' => 'paket-intranet-360x480.webp',
         'dbxapp-paket-enterprise' => 'paket-enterprise-360x480.webp',
      );
   }

   private function package_media_id_for_permalink(string $permalink): int {
      $permalink = trim(strtolower($permalink));
      $map = $this->package_media_file_map();
      $fileName = (string)($map[$permalink] ?? '');
      if ($fileName === '') {
         return 0;
      }
      $where = "active = 1 AND file_name = '" . str_replace("'", "''", $fileName) . "'";
      $row = $this->db->select1('dbxMedia', $where);
      return is_array($row) ? (int)($row['id'] ?? 0) : 0;
   }

   private function package_page_hint(array $page): ?array {
      $permalink = trim((string)($page['permalink'] ?? ''));
      $mediaId = $this->package_media_id_for_permalink($permalink);
      if ($mediaId <= 0) {
         return null;
      }
      return array(
         'permalink' => $permalink,
         'media_id' => $mediaId,
         'file_name' => (string)($this->package_media_file_map()[strtolower($permalink)] ?? ''),
         'inline_src' => $this->inline_media_src($mediaId),
         'update_patch' => array('package_product_image' => true),
      );
   }

   private function apply_package_product_image(string $content, int $mediaId, string $alt = ''): string {
      if ($mediaId <= 0 || stripos($content, 'col-md-4') === false || stripos($content, 'card') === false) {
         return $content;
      }
      $srcEsc = htmlspecialchars($this->inline_media_src($mediaId), ENT_QUOTES, 'UTF-8');
      $altEsc = htmlspecialchars($alt !== '' ? $alt : 'Paket', ENT_QUOTES, 'UTF-8');
      $img = '<img class="card-img-top" src="' . $srcEsc . '" data-cms-media-id="' . $mediaId . '" alt="' . $altEsc . '">';

      $updated = preg_replace_callback(
         '/<div class="col-md-4"><div class="card shadow-sm(?:\s+position-relative)?">(?:<img[^>]*card-img-top[^>]*>)?(?:<span class="position-absolute[^>]*>[\s\S]*?<\/span>)?<div class="card-body text-center">([\s\S]*?)<\/div><\/div><\/div>/i',
         function($m) use ($img) {
            $body = (string)($m[1] ?? '');
            $badge = '';
            if (preg_match('/<span class="badge[^>]*bg-success[^>]*>([\s\S]*?)<\/span>/i', $body, $badgeMatch)) {
               $label = trim(strip_tags((string)($badgeMatch[1] ?? 'Kostenlos')));
               if ($label !== '') {
                  $badge = '<span class="position-absolute top-0 end-0 badge rounded-pill bg-success m-2">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
               }
            }
            $body = preg_replace('/<span class="badge[^>]*>[\s\S]*?<\/span>\s*(?:<br\s*\/?>)?\s*/i', '', $body, 1);
            $body = preg_replace('/<img\b[^>]*>\s*/i', '', $body, 1);
            $body = preg_replace('/\bh5 mt-3\b/', 'h5', $body, 1);
            return '<div class="col-md-4"><div class="card shadow-sm position-relative">' . $img . $badge . '<div class="card-body text-center">' . $body . '</div></div></div>';
         },
         $content,
         1
      );

      return is_string($updated) && $updated !== '' ? $updated : $content;
   }

   private function ensure_inline_media_usage(int $contentId, int $mediaId, string $lng = ''): void {
      $contentId = (int)$contentId;
      $mediaId = (int)$mediaId;
      if ($contentId <= 0 || $mediaId <= 0) {
         return;
      }
      $lng = dbxContentMediaUsageScope::language($lng);
      $where = dbxContentMediaUsageScope::withLanguage('content_id = ' . $contentId . ' AND media_id = ' . $mediaId . " AND slot = 'inline' AND active = 1", $lng);
      if (is_array($this->db->select1('dbxMediaUsage', $where))) {
         return;
      }
      $data = array(
         'active' => 1,
         'media_id' => $mediaId,
         'content_id' => $contentId,
         'folder_id' => 0,
         'content_lng' => $lng,
         'slot' => 'inline',
         'template' => '',
         'caption' => '',
         'settings' => '',
         'sorter' => $this->next_usage_sorter($contentId, 0, 'inline', $lng),
      );
      $this->db->insert('dbxMediaUsage', $data);
   }

   private function media_inline_payload(int $id): array {
      $id = max(0, (int)$id);
      if ($id <= 0) {
         return array();
      }
      $src = $this->inline_media_src($id);
      return array(
         'inline_src' => $src,
         'inline_img' => '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" data-cms-media-id="' . $id . '" alt="">',
      );
   }

   private function normalize_content_inline_media_urls(string $html): string {
      $html = (string)$html;
      if ($html === '' || stripos($html, '<img') === false) {
         return $html;
      }

      return preg_replace_callback('/<img\b([^>]*?)>/i', function($m) {
         $tag = (string)($m[0] ?? '');
         $attrs = (string)($m[1] ?? '');
         $id = 0;
         if (preg_match('/\bdata-cms-media-id=["\']?([0-9]+)/i', $attrs, $id_match)) {
            $id = (int)$id_match[1];
         } elseif (preg_match('/\bdbx_mid=([0-9]+)/i', $attrs, $id_match)) {
            $id = (int)$id_match[1];
         } elseif (preg_match('/\bsrc=(["\'])([^"\']+)\1/i', $attrs, $src_match)) {
            $id = $this->media_id_by_inline_src((string)$src_match[2]);
         }
         if ($id <= 0) {
            return $tag;
         }
         return $this->patch_img_tag_for_inline_media($tag, $id);
      }, $html);
   }

   private function media_id_by_inline_src(string $src): int {
      $src = html_entity_decode(trim($src), ENT_QUOTES, 'UTF-8');
      if ($src === '' || preg_match('#^(?:https?:)?//#i', $src) || stripos($src, 'dbx_mid=') !== false) {
         return 0;
      }

      $path = preg_replace('/[?#].*$/', '', str_replace('\\', '/', $src));
      $rel = '';
      if (preg_match('#(?:^|/)(?:files/)?media/(.+)$#i', $path, $match)) {
         $rel = 'media/' . ltrim((string)$match[1], '/');
      } else {
         return 0;
      }

      static $cache = array();
      if (isset($cache[$rel])) {
         return (int)$cache[$rel];
      }

      $where = "active = 1 AND file_path = '" . str_replace("'", "''", $rel) . "'";
      $row = $this->db->select1('dbxMedia', $where);
      if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
         return $cache[$rel] = (int)$row['id'];
      }

      $base = basename($rel);
      if ($base === '' || $base === '.' || $base === '..') {
         return $cache[$rel] = 0;
      }
      $rows = $this->db->select(
         'dbxMedia',
         "active = 1 AND file_name = '" . str_replace("'", "''", $base) . "'",
         'id,file_path',
         'id',
         'DESC',
         '',
         5,
         0,
         0
      );
      if (is_array($rows)) {
         foreach ($rows as $candidate) {
            $candidatePath = ltrim(str_replace('\\', '/', (string)($candidate['file_path'] ?? '')), '/');
            if ($candidatePath === $rel || basename($candidatePath) === $base) {
               return $cache[$rel] = (int)($candidate['id'] ?? 0);
            }
         }
      }

      return $cache[$rel] = 0;
   }

   private function patch_img_tag_for_inline_media(string $tag, int $id): string {
      $id = max(0, (int)$id);
      if ($id <= 0) {
         return $tag;
      }
      $src = $this->inline_media_src($id);
      $src_attr = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
      $tag = preg_replace('/\s*data-cms-media-id\s*=\s*["\']?[^"\'>\s]*["\']*/i', '', $tag);
      if (preg_match('/\bsrc=(["\'])([^"\']*)\1/i', $tag)) {
         $tag = preg_replace('/\bsrc=(["\'])([^"\']*)\1/i', 'src="' . $src_attr . '"', $tag, 1);
      } else {
         $tag = preg_replace('/^<img\b/i', '<img src="' . $src_attr . '"', $tag);
      }
      $tag = preg_replace('/^<img\b/i', '<img data-cms-media-id="' . $id . '"', $tag);
      return $tag;
   }

   public function bundleActionCatalog(): array {
      return $this->catalog();
   }

   public function bundleIsAllowedInPackage(string $action): bool {
      $catalog = $this->catalog();
      if (!isset($catalog[$action]) || !($catalog[$action]['write'] ?? false)) {
         return false;
      }
      if (!empty($catalog[$action]['destructive'])) {
         return false;
      }
      return true;
   }

   public function bundleBuildPlan(string $action, array $params): array {
      if (!$this->bundleIsAllowedInPackage($action)) {
         throw new \InvalidArgumentException('Aktion im Bundle nicht erlaubt: ' . $action);
      }
      return $this->build_plan($action, $params);
   }

   public function bundleExecutePlan(string $action, array $params, array $plan): array {
      if (!$this->bundleIsAllowedInPackage($action)) {
         throw new \InvalidArgumentException('Aktion im Bundle nicht erlaubt: ' . $action);
      }
      return $this->execute_action($action, $params, $plan);
   }

   public function bundleExecuteToken(): string {
      return dbx()->action_token(self::TOKEN_SCOPE);
   }

   public function bundleCheckExecuteToken(string $token): bool {
      return dbx()->check_action_token(self::TOKEN_SCOPE, $token);
   }

   public function bundleSnapshot(array $params = array()): array {
      return $this->snapshot($params);
   }

   public function bundleSystemDescribe(): array {
      return $this->describe();
   }
}
