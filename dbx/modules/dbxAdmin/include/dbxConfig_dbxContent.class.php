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

   private function add_lng_fld($o_form, string $name): void {
      $o_form->add_fld($name, 'fd::', 'fd::', 'fd::', 'fd::', 'fd::', 'fd::', 'fd::', 'fd::', 'fd::', self::LNG_FD);
   }

   /**
    * Aktive Werte aus dbx, dbxHome und dbxContent fuer $data zusammenfuehren.
    */
   private function build_form_data(): array {
      $content_cfg = dbx()->get_cfg('dbxContent', '', null, true);
      if (!is_array($content_cfg)) {
         $content_cfg = array();
      }

      $data = array(
         'lng_translate_provider' => strtolower(trim((string) ($content_cfg['lng_translate_provider'] ?? 'copy'))),
         'lng_translate_api_key' => (string) ($content_cfg['lng_translate_api_key'] ?? ''),
         'lng_translate_api_url' => (string) ($content_cfg['lng_translate_api_url'] ?? ''),
         'lng_translate_model' => (string) ($content_cfg['lng_translate_model'] ?? 'gpt-4o-mini'),
      );

      if ($data['lng_translate_provider'] === '' || $data['lng_translate_provider'] === 'undef') {
         $data['lng_translate_provider'] = 'copy';
      }
      if ($data['lng_translate_model'] === '' || $data['lng_translate_model'] === 'undef') {
         $data['lng_translate_model'] = 'gpt-4o-mini';
      }

      $dbx_cfg = dbx()->get_cfg('dbx');
      if (!is_array($dbx_cfg)) {
         $dbx_cfg = array();
      }

      $data['default_lng'] = strtolower(trim((string) ($dbx_cfg['default_lng'] ?? 'de')));
      if ($data['default_lng'] === '' || $data['default_lng'] === 'undef') {
         $data['default_lng'] = 'de';
      }

      $accessible = $dbx_cfg['accessible_lng'] ?? 'de';
      if (is_array($accessible)) {
         $data['accessible_lng'] = implode(',', $accessible);
      } else {
         $data['accessible_lng'] = trim((string) $accessible);
      }
      if ($data['accessible_lng'] === '' || $data['accessible_lng'] === 'undef') {
         $data['accessible_lng'] = $data['default_lng'];
      }

      $home_cfg = dbx()->get_cfg('dbxHome');
      $data['home_cid'] = is_array($home_cfg) ? (string) ($home_cfg['cid'] ?? '1') : '1';
      if ($data['home_cid'] === '' || $data['home_cid'] === 'undef') {
         $data['home_cid'] = '1';
      }

      return $data;
   }

   /**
    * @return string[]
    */
   private function normalize_accessible_lng($raw, string $default_lng): array {
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
      if ($default_lng !== '' && !in_array($default_lng, $out, true)) {
         array_unshift($out, $default_lng);
      }
      if (!count($out)) {
         $out = array($default_lng !== '' ? $default_lng : 'de');
      }

      return $out;
   }

   private function save_lng_config(array $config): void {
      $default_lng = strtolower(trim((string) ($config['default_lng'] ?? 'de')));
      if ($default_lng === '' || !preg_match('/^[a-z]{2,3}$/', $default_lng)) {
         $default_lng = 'de';
      }

      $accessible_list = $this->normalize_accessible_lng($config['accessible_lng'] ?? $default_lng, $default_lng);

      $dbx_cfg = dbx()->get_cfg('dbx');
      if (!is_array($dbx_cfg)) {
         $dbx_cfg = array();
      }
      $dbx_cfg['default_lng'] = $default_lng;
      $dbx_cfg['accessible_lng'] = implode(',', $accessible_list);
      dbx()->set_cfg('dbx', $dbx_cfg);

      $home_cid = trim((string) ($config['home_cid'] ?? '1'));
      if ($home_cid === '' || !ctype_digit($home_cid)) {
         $home_cid = '1';
      }

      $home_cfg = dbx()->get_cfg('dbxHome');
      if (!is_array($home_cfg)) {
         $home_cfg = array();
      }
      $home_cfg['cid'] = $home_cid;
      dbx()->set_cfg('dbxHome', $home_cfg);
   }

   private function save_translate_config(array $config): void {
      $content_cfg = dbx()->get_cfg('dbxContent');
      if (!is_array($content_cfg)) {
         $content_cfg = array();
      }

      foreach (self::TRANSLATE_KEYS as $key) {
         if (!array_key_exists($key, $config)) {
            continue;
         }
         $content_cfg[$key] = $config[$key];
      }

      if (!isset($content_cfg['dbxConfig_modul']) || $content_cfg['dbxConfig_modul'] === '') {
         $content_cfg['dbxConfig_modul'] = 'secure';
      }

      dbx()->set_cfg('dbxContent', $content_cfg);
   }

   public function run($action = '') {
      $data = $this->build_form_data();

      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('form-dbxContent-config', 'config-dbxContent-shell');
      $o_form->set_action($action !== ''
         ? $action
         : '?dbx_modul=dbxAdmin&dbx_run1=config&dbx_run2=edit&xmodul=dbxContent');
      $o_form->set_data_definition(self::LNG_FD);
      $o_form->set_data($data);
      $o_form->_fld_change_state = '*';

      $help = dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
      $help->attach_form($o_form, 'config', '', 'dbxContent_admin');

      $o_form->add_rep('section_languages', 'Sprachen');
      $o_form->add_rep('languages_hint', 'Master- und Zielsprachen steuern CMS-Tabellen, Tree-Badges und Startseiten-Aufloesung.');
      $o_form->add_rep('section_translate', 'Uebersetzung (API)');
      $o_form->add_rep('translate_hint', 'Provider und API-Zugang fuer automatische Uebersetzung bei Sync und Provision.');

      $this->add_lng_fld($o_form, 'default_lng');
      $this->add_lng_fld($o_form, 'accessible_lng');
      $this->add_lng_fld($o_form, 'home_cid');

      $this->add_lng_fld($o_form, 'lng_translate_provider');
      $this->add_lng_fld($o_form, 'lng_translate_api_key');
      $this->add_lng_fld($o_form, 'lng_translate_api_url');
      $this->add_lng_fld($o_form, 'lng_translate_model');

      $save_btn = dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|button-submit', array(
         'label' => 'Sprach-Config speichern',
      ));
      $o_form->add_obj('button', 'obj-value', $save_btn);
      $o_form->add_obj('bar_actions', 'obj-value', $save_btn);

      if ($o_form->submit() && !$o_form->errors()) {
         $config = $o_form->validated_post();
         foreach (array('button', 'bar_actions') as $skip) {
            unset($config[$skip]);
         }

         $this->save_lng_config($config);
         $this->save_translate_config($config);

         dbx()->sys_msg(
            'info',
            'dbxContent',
            'config',
            'Sprach-Config gespeichert',
            'master=' . (string) ($config['default_lng'] ?? '') . '; provider=' . (string) ($config['lng_translate_provider'] ?? '')
         );

         $o_form->set_data($this->build_form_data());
      }

      $this->apply_bar_reps($o_form);

      return $o_form->run();
   }

   private function apply_bar_reps($o_form): void {
      $provider = dbxContentTranslate::provider();
      $o_form->add_rep('bar_title', 'CMS — Sprachen & Uebersetzung');
      $o_form->add_rep(
         'bar_subtitle',
         'Aktiver Uebersetzungs-Provider: ' . $provider . ' (nach Speichern neu geladen)'
      );
      $o_form->add_rep('bar_icon', 'bi-translate');
      $o_form->add_rep('bar_class', 'dbx-bar--module');
      $o_form->add_rep('bar_title_class', 'dbx-bar-title');
      $o_form->add_rep('bar_actions_class', 'dbx-bar-actions');
      $o_form->add_rep('current_provider', $provider);
   }
}
