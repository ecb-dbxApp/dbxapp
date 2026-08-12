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

trait dbxKiCmsCoreServiceTrait {

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
      $request = dbx()->get_json_request();

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
      if ((int)dbx()->get_cfg('dbxKi', 'allow_execute', 1) !== 1) {
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
}
