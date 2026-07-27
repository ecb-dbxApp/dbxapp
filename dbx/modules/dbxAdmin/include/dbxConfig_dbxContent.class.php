<?php
namespace dbx\dbxAdmin;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContentTranslate.class.php';

use dbx\dbxContent\dbxContentTranslate;

class dbxConfig_dbxContent {

   private const LNG_FD = 'fd:dbxContent|config-lng';

   private const TRANSLATE_KEYS = array(
      'lng_translate_provider',
      'lng_translate_api_key',
      'lng_translate_api_url',
      'lng_translate_model',
   );

   private function addLngFld($oForm, string $name): void {
      $oForm->add_fld($name, 'fd::', 'fd::', 'fd::', 'fd::', 'fd::', 'fd::', 'fd::', 'fd::', 'fd::', self::LNG_FD);
   }

   /**
    * Aktive Werte aus dbx, dbxHome und dbxContent fuer $data zusammenfuehren.
    */
   private function buildFormData(): array {
      $contentCfg = dbx()->get_config('dbxContent');
      if (!is_array($contentCfg)) {
         $contentCfg = array();
      }

      $data = array(
         'lng_translate_provider' => strtolower(trim((string) ($contentCfg['lng_translate_provider'] ?? 'copy'))),
         'lng_translate_api_key' => (string) ($contentCfg['lng_translate_api_key'] ?? ''),
         'lng_translate_api_url' => (string) ($contentCfg['lng_translate_api_url'] ?? ''),
         'lng_translate_model' => (string) ($contentCfg['lng_translate_model'] ?? 'gpt-4o-mini'),
      );

      if ($data['lng_translate_provider'] === '' || $data['lng_translate_provider'] === 'undef') {
         $data['lng_translate_provider'] = 'copy';
      }
      if ($data['lng_translate_model'] === '' || $data['lng_translate_model'] === 'undef') {
         $data['lng_translate_model'] = 'gpt-4o-mini';
      }

      $dbxCfg = dbx()->get_config('dbx');
      if (!is_array($dbxCfg)) {
         $dbxCfg = array();
      }

      $data['default_lng'] = strtolower(trim((string) ($dbxCfg['default_lng'] ?? 'de')));
      if ($data['default_lng'] === '' || $data['default_lng'] === 'undef') {
         $data['default_lng'] = 'de';
      }

      $accessible = $dbxCfg['accessible_lng'] ?? 'de';
      if (is_array($accessible)) {
         $data['accessible_lng'] = implode(',', $accessible);
      } else {
         $data['accessible_lng'] = trim((string) $accessible);
      }
      if ($data['accessible_lng'] === '' || $data['accessible_lng'] === 'undef') {
         $data['accessible_lng'] = $data['default_lng'];
      }

      $homeCfg = dbx()->get_config('dbxHome');
      $data['home_cid'] = is_array($homeCfg) ? (string) ($homeCfg['cid'] ?? '1') : '1';
      if ($data['home_cid'] === '' || $data['home_cid'] === 'undef') {
         $data['home_cid'] = '1';
      }

      return $data;
   }

   /**
    * @return string[]
    */
   private function normalizeAccessibleLng($raw, string $defaultLng): array {
      $out = array();
      if (is_array($raw)) {
         foreach ($raw as $val) {
            $val = strtolower(trim((string) $val));
            if ($val !== '' && preg_match('/^[a-z]{2,3}$/', $val)) {
               $out[] = $val;
            }
         }
      } else {
         $parts = preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
         if (is_array($parts)) {
            foreach ($parts as $val) {
               $val = strtolower(trim((string) $val));
               if ($val !== '' && preg_match('/^[a-z]{2,3}$/', $val)) {
                  $out[] = $val;
               }
            }
         }
      }

      $out = array_values(array_unique($out));
      if ($defaultLng !== '' && !in_array($defaultLng, $out, true)) {
         array_unshift($out, $defaultLng);
      }
      if (!count($out)) {
         $out = array($defaultLng !== '' ? $defaultLng : 'de');
      }

      return $out;
   }

   private function saveLngConfig(array $config): void {
      $defaultLng = strtolower(trim((string) ($config['default_lng'] ?? 'de')));
      if ($defaultLng === '' || !preg_match('/^[a-z]{2,3}$/', $defaultLng)) {
         $defaultLng = 'de';
      }

      $accessibleList = $this->normalizeAccessibleLng($config['accessible_lng'] ?? $defaultLng, $defaultLng);

      $dbxCfg = dbx()->get_config('dbx');
      if (!is_array($dbxCfg)) {
         $dbxCfg = array();
      }
      $dbxCfg['default_lng'] = $defaultLng;
      $dbxCfg['accessible_lng'] = implode(',', $accessibleList);
      dbx()->set_config('dbx', $dbxCfg);

      $homeCid = trim((string) ($config['home_cid'] ?? '1'));
      if ($homeCid === '' || !ctype_digit($homeCid)) {
         $homeCid = '1';
      }

      $homeCfg = dbx()->get_config('dbxHome');
      if (!is_array($homeCfg)) {
         $homeCfg = array();
      }
      $homeCfg['cid'] = $homeCid;
      dbx()->set_config('dbxHome', $homeCfg);
   }

   private function saveTranslateConfig(array $config): void {
      $contentCfg = dbx()->get_config('dbxContent');
      if (!is_array($contentCfg)) {
         $contentCfg = array();
      }

      foreach (self::TRANSLATE_KEYS as $key) {
         if (!array_key_exists($key, $config)) {
            continue;
         }
         $contentCfg[$key] = $config[$key];
      }

      if (!isset($contentCfg['dbxConfig_modul']) || $contentCfg['dbxConfig_modul'] === '') {
         $contentCfg['dbxConfig_modul'] = 'secure';
      }

      dbx()->set_config('dbxContent', $contentCfg);
   }

   public function run($action = '') {
      $data = $this->buildFormData();

      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('form-dbxContent-config', 'config-dbxContent-shell');
      $oForm->_action = $action !== ''
         ? $action
         : '?dbx_modul=dbxAdmin&dbx_run1=config&dbx_run2=edit&xmodul=dbxContent';
      $oForm->_dd = self::LNG_FD;
      $oForm->_data = $data;
      $oForm->_fld_change_state = '*';

      $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
      $help->attachForm($oForm, 'content_lng');

      $oForm->add_rep('section_languages', 'Sprachen');
      $oForm->add_rep('languages_hint', 'Master- und Zielsprachen steuern CMS-Tabellen, Tree-Badges und Startseiten-Aufloesung.');
      $oForm->add_rep('section_translate', 'Uebersetzung (API)');
      $oForm->add_rep('translate_hint', 'Provider und API-Zugang fuer automatische Uebersetzung bei Sync und Provision.');

      $this->addLngFld($oForm, 'default_lng');
      $this->addLngFld($oForm, 'accessible_lng');
      $this->addLngFld($oForm, 'home_cid');

      $this->addLngFld($oForm, 'lng_translate_provider');
      $this->addLngFld($oForm, 'lng_translate_api_key');
      $this->addLngFld($oForm, 'lng_translate_api_url');
      $this->addLngFld($oForm, 'lng_translate_model');

      $saveBtn = dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|button-submit', array(
         'label' => 'Sprach-Config speichern',
      ));
      $oForm->add_obj('button', 'obj-value', $saveBtn);
      $oForm->add_obj('bar_actions', 'obj-value', $saveBtn);

      if ($oForm->submit() && !$oForm->errors()) {
         $config = is_array($oForm->_post) ? $oForm->_post : array();
         foreach (array('button', 'bar_actions') as $skip) {
            unset($config[$skip]);
         }

         $this->saveLngConfig($config);
         $this->saveTranslateConfig($config);

         dbx()->sys_msg(
            'info',
            'dbxContent',
            'config',
            'Sprach-Config gespeichert',
            'master=' . (string) ($config['default_lng'] ?? '') . '; provider=' . (string) ($config['lng_translate_provider'] ?? '')
         );

         $oForm->_data = $this->buildFormData();
      }

      $this->applyBarReps($oForm);

      return $oForm->run();
   }

   private function applyBarReps($oForm): void {
      $provider = dbxContentTranslate::provider();
      $oForm->add_rep('bar_title', 'CMS — Sprachen & Uebersetzung');
      $oForm->add_rep(
         'bar_subtitle',
         'Aktiver Uebersetzungs-Provider: ' . $provider . ' (nach Speichern neu geladen)'
      );
      $oForm->add_rep('bar_icon', 'bi-translate');
      $oForm->add_rep('bar_class', 'dbx-module-bar');
      $oForm->add_rep('bar_title_class', 'dbx-module-bar-titleblock');
      $oForm->add_rep('bar_actions_class', 'dbx-module-bar-actions');
      $oForm->add_rep('current_provider', $provider);
   }
}
