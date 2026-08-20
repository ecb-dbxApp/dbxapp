<?php
namespace dbx\dbxWorkflow;

require_once __DIR__ . '/dbxWorkflowValue.class.php';

/**
 * Bindet dbxApp-Module als ausführbare Bausteine in einen Workflow ein.
 */
class dbxWorkflowModule {

   private $dd_bind = 'dbxWorkflow|workflowModuleBind';

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function h($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   public function parse_bind_ref($bind_ref) {
      $bind_ref = trim((string)$bind_ref);
      if ($bind_ref === '' || strpos($bind_ref, '|') === false) {
         return array('', '');
      }

      $parts = explode('|', $bind_ref, 2);
      return array(trim((string)$parts[0]), trim((string)$parts[1]));
   }

   public function load_bind_record($bind_ref) {
      list($modul, $bind_key) = $this->parse_bind_ref($bind_ref);
      if ($modul === '' || $bind_key === '') {
         return array();
      }

      $rows = $this->db()->select(
         $this->dd_bind,
         array('modul' => $modul, 'bind_key' => $bind_key, 'active' => 1, 'trash' => 0),
         '*',
         'id',
         'DESC',
         '',
         1,
         0,
         0
      );

      return (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
   }

   public function apply_bind_ref(array $definition) {
      $bind_ref = trim((string)($definition['bind_ref'] ?? ''));
      if ($bind_ref === '') {
         return $definition;
      }

      $row = $this->load_bind_record($bind_ref);
      if (!$row) {
         return $definition;
      }

      $bind = dbxWorkflowValue::read_json($row['bind_json'] ?? '', array());
      if ($bind) {
         $definition['bind'] = $bind;
      }

      return $definition;
   }

   private function bind_config(array $definition) {
      return (array)($definition['bind'] ?? array());
   }

   private function record_config(array $definition) {
      return (array)($this->bind_config($definition)['record'] ?? array());
   }

   public function record_id_from_values(array $definition, array $values) {
      $record = $this->record_config($definition);
      $id_need = trim((string)($record['id_need'] ?? ''));
      if ($id_need === '' || !array_key_exists($id_need, $values)) {
         return 0;
      }

      return (int)preg_replace('/\D+/', '', (string)$values[$id_need]);
   }

   public function load_record(array $definition, $rid = 0) {
      $record_cfg = $this->record_config($definition);
      $dd = trim((string)($record_cfg['dd'] ?? ''));
      $rid = (int)$rid;
      if ($dd === '' || $rid <= 0) {
         return array();
      }

      $row = $this->db()->select1($dd, $rid);
      return (is_array($row) && $row) ? $row : array();
   }

   private function label_from_template($template, array $row) {
      $label = (string)$template;
      foreach ($row as $key => $value) {
         if (is_scalar($value) || $value === null) {
            $label = str_replace('{' . $key . '}', (string)$value, $label);
         }
      }

      return trim($label);
   }

   private function options_from_dd_select(array $bind_need, array $definition, array $values) {
      $record = $this->record_config($definition);
      $dd = trim((string)($record['dd'] ?? ''));
      if ($dd === '') {
         return array();
      }

      $source = array_merge(
         array('dd' => $dd, 'include_from' => (string)($record['id_need'] ?? '')),
         $bind_need
      );

      return $this->options_from_source($source, $values, $this->record_id_from_values($definition, $values));
   }

   public function options_from_source(array $source, array $values = array(), $include_id = 0) {
      $dd = trim((string)($source['dd'] ?? ''));
      if ($dd === '') {
         return array();
      }

      $where = (array)($source['where'] ?? array());
      $fields = (array)($source['fields'] ?? array('id'));
      $value_field = trim((string)($source['value'] ?? 'id'));
      $label_tpl = trim((string)($source['label'] ?? ('{' . $value_field . '}')));

      if ($value_field !== '' && !in_array($value_field, $fields, true)) {
         $fields[] = $value_field;
      }

      $rows = $this->db()->select(
         $dd,
         $where,
         $fields,
         (string)($source['order_field'] ?? 'id'),
         (string)($source['order_dir'] ?? 'DESC'),
         '',
         (int)($source['limit'] ?? 200),
         0,
         0
      );

      $options = array();
      $seen = array();

      foreach ((array)$rows as $row) {
         $id = (string)($row[$value_field] ?? '');
         if ($id === '') {
            continue;
         }
         $seen[$id] = 1;
         $options[] = array(
            'value' => $id,
            'label' => $this->label_from_template($label_tpl, $row),
         );
      }

      $include_id = (string)(int)$include_id;
      if ((int)$include_id > 0 && empty($seen[$include_id])) {
         $record = $this->db()->select1($dd, (int)$include_id, $fields);
         if (is_array($record) && $record) {
            $options[] = array(
               'value' => $include_id,
               'label' => $this->label_from_template($label_tpl, $record),
            );
         }
      }

      return $options;
   }

   private function options_from_fd_field(array $definition, $field_name) {
      $record = $this->record_config($definition);
      $dd = trim((string)($record['dd'] ?? ''));
      $field_name = trim((string)$field_name);
      if ($dd === '' || $field_name === '') {
         return array();
      }

      $o_dd = dbx()->get_system_obj('dbxDD');
      $model = $o_dd->get_dd_model($dd);
      $options_raw = '';

      foreach ((array)($model['fields'] ?? array()) as $field) {
         if ((string)($field['name'] ?? '') === $field_name) {
            $options_raw = (string)($field['options'] ?? '');
            break;
         }
      }

      if ($options_raw === '') {
         return array();
      }

      $options = array();
      foreach (explode('&', $options_raw) as $pair) {
         $pair = trim($pair);
         if ($pair === '' || strpos($pair, '=') === false) {
            continue;
         }
         list($value, $label) = explode('=', $pair, 2);
         $options[] = array(
            'value' => trim($value),
            'label' => trim($label),
         );
      }

      return $options;
   }

   private function config_has_part($modul, $key, $part) {
      if ($part !== 'mail') {
         return false;
      }

      if ($modul === 'dbxContact' && !class_exists('\\dbx\\dbxContact\\dbxContactConfig', false)) {
         $path = dbx()->get_base_dir() . 'dbx/modules/dbxContact/include/dbxContactConfig.class.php';
         if (is_file($path)) {
            require_once $path;
         }
      }

      if (class_exists('\\dbx\\dbxContact\\dbxContactConfig', false)) {
         return \dbx\dbxContact\dbxContactConfig::modul_mail_enabled((string) $modul, (string) $key);
      }

      $mode = strtolower(trim((string) dbx()->get_cfg($modul, $key)));
      return ($mode === 'both' || $mode === 'mail' || strpos($mode, 'mail') !== false);
   }

   private function need_bind(array $definition, $need_key) {
      return (array)($this->bind_config($definition)['needs'][$need_key] ?? array());
   }

   private function need_visible(array $definition, $need_key, array $bind_need) {
      $show_if = (array)($bind_need['show_if_config'] ?? array());
      if (!$show_if) {
         return true;
      }

      $modul = trim((string)($show_if['modul'] ?? ''));
      $key = trim((string)($show_if['key'] ?? ''));
      $has = trim((string)($show_if['has'] ?? ''));

      if ($modul === '' || $key === '' || $has === '') {
         return true;
      }

      return $this->config_has_part($modul, $key, $has);
   }

   public function enrich_definition(array $definition, array $values = array()) {
      $definition = $this->apply_bind_ref($definition);
      $bind_needs = (array)($this->bind_config($definition)['needs'] ?? array());
      $needs = array();

      foreach ((array)($definition['needs'] ?? array()) as $need) {
         if (!is_array($need)) {
            continue;
         }

         $key = (string)($need['key'] ?? '');
         $bind_need = (array)($bind_needs[$key] ?? array());

         if ($bind_need && !$this->need_visible($definition, $key, $bind_need)) {
            continue;
         }

         if (!empty($need['source']) && is_array($need['source'])) {
            $need['options'] = $this->options_from_source(
               $need['source'],
               $values,
               $this->record_id_from_values($definition, $values)
            );
         } elseif ($bind_need) {
            $type = (string)($bind_need['type'] ?? '');
            if ($type === 'dd_select') {
               $need['options'] = $this->options_from_dd_select($bind_need, $definition, $values);
            } elseif ($type === 'dd_field_options') {
               $need['options'] = $this->options_from_fd_field($definition, (string)($bind_need['field'] ?? ''));
            } elseif ($type === 'static_select') {
               $need['options'] = array_values((array)($bind_need['options'] ?? array()));
            }
         }

         $needs[] = $need;
      }

      $definition['needs'] = $needs;
      $definition = $this->enrich_shop_ebay_publish_definition($definition, $values);
      return $definition;
   }

   private function enrich_shop_ebay_publish_definition(array $definition, array $values): array {
      if ((string)($definition['workflow_key'] ?? '') !== 'shop_ebay_publish') {
         return $definition;
      }

      $product_id = (int)($values['product'] ?? 0);
      if ($product_id <= 0) {
         return $definition;
      }

      $rows = $this->db()->select(
         'dbxShop|shopProductChannel',
         array('product_id' => $product_id, 'channel_key' => 'ebay'),
         array('external_listing_id', 'external_offer_id', 'export_status', 'export_message'),
         'id',
         'DESC',
         '',
         1,
         0,
         0
      );
      $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
      $listing_id = trim((string)($row['external_listing_id'] ?? ''));
      $offer_id = trim((string)($row['external_offer_id'] ?? ''));
      $status = trim((string)($row['export_status'] ?? ''));
      $message = trim((string)($row['export_message'] ?? ''));

      foreach ($definition['needs'] as &$need) {
         if ((string)($need['key'] ?? '') !== 'ebay_view') {
            continue;
         }

         if ($listing_id !== '') {
            $need['hint'] = 'Optionaler Kontrollschritt: Das eBay-Angebot wurde mit Listing-ID ' . $listing_id . ' gespeichert. Oeffne das Angebot und pruefe Darstellung, Preis, Versand und Bilder.';
            $need['module_links'] = array(
               array(
                  'label' => 'Bei eBay ansehen',
                  'icon' => 'bi-box-arrow-up-right',
                  'url' => 'https://www.ebay.de/itm/' . rawurlencode($listing_id),
                  'title' => 'eBay-Angebot ansehen',
                  'width' => '92%',
                  'height' => '90%'
               ),
               array(
                  'label' => 'eBay-Mapping',
                  'icon' => 'bi-sliders2',
                  'url' => '?dbx_modul=dbxShop_admin&dbx_run1=product_channel_mapping&id={product}&channel=ebay',
                  'title' => 'Channel-Mapping: eBay',
                  'width' => '68%',
                  'height' => '84%'
               )
            );
         } else {
            $extra = array();
            if ($offer_id !== '') {
               $extra[] = 'Offer-ID: ' . $offer_id;
            }
            if ($status !== '') {
               $extra[] = 'Status: ' . $status;
            }
            if ($message !== '') {
               $extra[] = $message;
            }
            $need['hint'] = 'Optionaler Kontrollschritt: Es ist noch keine eBay Listing-ID gespeichert. Pruefe zuerst Exportstatus und Mapping.'
               . ($extra ? ' ' . implode(' | ', $extra) : '');
         }
      }
      unset($need);

      return $definition;
   }

   public function prefill_start(array $definition, $rid = 0) {
      $definition = $this->apply_bind_ref($definition);
      $record_cfg = $this->record_config($definition);
      $rid = (int)$rid;
      if (empty($record_cfg['prefill_rid']) || $rid <= 0) {
         return array();
      }

      $record = $this->load_record($definition, $rid);
      if (!$record) {
         return array();
      }

      $values = array();
      $id_need = trim((string)($record_cfg['id_need'] ?? ''));
      if ($id_need !== '') {
         $values[$id_need] = (string)$rid;
      }

      foreach ((array)($this->bind_config($definition)['needs'] ?? array()) as $need_key => $bind_need) {
         $type = (string)($bind_need['type'] ?? '');
         if ($type === 'dd_field_options' && isset($record[(string)($bind_need['field'] ?? '')])) {
            $values[$need_key] = (string)$record[(string)$bind_need['field']];
         }
         if ($type === 'dd_field_value' && isset($record[(string)($bind_need['field'] ?? '')])) {
            $text = trim((string)$record[(string)$bind_need['field']]);
            if ($text !== '') {
               $values[$need_key] = $text;
            }
         }
      }

      return $values;
   }

   private function shop_ebay_readiness_automation(array $values): ?array {
      $product_id = (int)($values['product'] ?? 0);
      if ($product_id <= 0) return null;

      $repo = dbx()->get_include_obj('dbxShopRepository', 'dbxShop');
      if (!is_object($repo) || !method_exists($repo, 'productChannelMapping')) {
         return null;
      }

      $data = $repo->product_channel_mapping($product_id, 'ebay');
      if (!is_array($data)) {
         return array('value' => 'needs_work', 'message' => 'Artikel oder eBay-Channel wurde nicht gefunden.');
      }

      $product = (array)($data['product'] ?? array());
      $channel = (array)($data['channel'] ?? array());
      $product_channel = (array)($data['product_channel'] ?? array());
      $mapping = (array)($data['mapping'] ?? array());
      $missing = array();

      if ((int)($channel['active'] ?? 0) !== 1) $missing[] = 'eBay-Channel aktiv';
      if ((int)($channel['export_enabled'] ?? 0) !== 1) $missing[] = 'Channel-Export freigeben';
      if ((int)($product_channel['active'] ?? 0) !== 1) $missing[] = 'Artikel dem eBay-Channel zuordnen';

      $required = array(
         'api_client_id' => 'Client-ID/App-ID',
         'api_client_secret' => 'Client-Secret/Cert-ID',
         'marketplace_id' => 'Marketplace-ID',
         'location_key' => 'Location-Key',
      );
      foreach ($required as $key => $label) {
         if (trim((string)($channel[$key] ?? '')) === '') $missing[] = $label;
      }
      if (trim((string)($channel['api_refresh_token'] ?? '')) === '' && trim((string)($channel['api_access_token'] ?? '')) === '') {
         $missing[] = 'Refresh-Token oder Access-Token';
      }

      $mapped = array(
         'category_id' => 'Kategorie-ID',
         'payment_policy_id' => 'Payment-Policy',
         'fulfillment_policy_id' => 'Fulfillment-Policy',
         'return_policy_id' => 'Return-Policy',
      );
      foreach ($mapped as $key => $label) {
         $resolved = trim((string)($mapping[$key] ?? $channel[$key] ?? ''));
         if ($resolved === '') $missing[] = $label;
      }

      $sku = trim((string)($product_channel['channel_sku'] ?? $product['sku'] ?? ''));
      if ($sku === '') $missing[] = 'Channel-SKU/Artikelnummer';
      if (trim((string)($product['title'] ?? '')) === '') $missing[] = 'Artikeltitel';

      $missing = array_values(array_unique($missing));
      if ($missing) {
         return array(
            'value' => 'needs_work',
            'message' => 'Automatische Bereitschaftsprüfung: Bitte ergänzen: ' . implode(', ', $missing) . '.',
         );
      }

      return array(
         'value' => 'ready',
         'message' => 'Automatische Bereitschaftsprüfung: eBay-Grunddaten, Zuordnung und Policies sind vollständig.',
      );
   }

   private function shop_ebay_status_automation(array $values): ?array {
      $product_id = (int)($values['product'] ?? 0);
      if ($product_id <= 0) return null;

      $rows = $this->db()->select(
         'dbxShop|shopProductChannel',
         array('product_id' => $product_id, 'channel_key' => 'ebay'),
         array('external_listing_id', 'export_status', 'export_message', 'last_export_date'),
         'id',
         'DESC',
         '',
         1,
         0,
         0
      );
      $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
      $last_export = trim((string)($row['last_export_date'] ?? ''));
      if ($last_export === '') return null;

      $status = strtolower(trim((string)($row['export_status'] ?? '')));
      $listing_id = trim((string)($row['external_listing_id'] ?? ''));
      $connector_message = trim((string)($row['export_message'] ?? ''));
      $value = 'open';
      if (in_array($status, array('failed', 'error'), true)) {
         $value = 'error';
      } elseif ($listing_id !== '' && in_array($status, array('published', 'exported', 'ready'), true)) {
         $value = 'ok';
      }

      $message = 'Connector-Status automatisch ausgewertet: ' . ($status !== '' ? $status : 'offen') . '.';
      if ($listing_id !== '') $message .= ' Listing-ID: ' . $listing_id . '.';
      if ($connector_message !== '') $message .= ' ' . $connector_message;
      return array('value' => $value, 'message' => $message);
   }

   public function automate_need(array $definition, array $need, array $values): ?array {
      if ((string)($need['automation'] ?? 'manual') !== 'observe') return null;
      if ((string)($definition['workflow_key'] ?? '') !== 'shop_ebay_publish') return null;

      $key = (string)($need['key'] ?? '');
      if ($key === 'readiness_check') return $this->shop_ebay_readiness_automation($values);
      if ($key === 'status_check') return $this->shop_ebay_status_automation($values);
      return null;
   }

   private function context_data(array $definition, array $record) {
      $context = (array)($this->bind_config($definition)['context'] ?? array());
      $fields = (array)($context['fields'] ?? array());
      $data = array();

      foreach ($fields as $tpl_key => $record_key) {
         $value = $record[(string)$record_key] ?? '';
         if ($tpl_key === 'phone' && trim((string)$value) === '') {
            $value = '-';
         }
         $data[$tpl_key] = $this->h($value);
      }

      return $data;
   }

   public function render_step_context(array $definition, array $need, array $values) {
      $definition = $this->apply_bind_ref($definition);
      $context = (array)($this->bind_config($definition)['context'] ?? array());
      if (!$context) {
         return '';
      }

      $hide_on = trim((string)($context['hide_on_need'] ?? ''));
      $rid = $this->record_id_from_values($definition, $values);

      if ($rid > 0 && $hide_on !== '' && $need['key'] !== $hide_on) {
         $record = $this->load_record($definition, $rid);
         if ($record) {
            $tpl = trim((string)($context['tpl'] ?? ''));
            if ($tpl !== '') {
               return $this->tpl()->get_tpl($tpl, $this->context_data($definition, $record));
            }
         }
      }

      if ($hide_on !== '' && $need['key'] === $hide_on && $rid <= 0) {
         $record_cfg = $this->record_config($definition);
         $bind_need = $this->need_bind($definition, $hide_on);
         if (($bind_need['type'] ?? '') === 'dd_select') {
            $options = $this->options_from_dd_select($bind_need, $definition, $values);
            if (!$options) {
               return $this->tpl()->get_tpl('dbx|alert-info', array(
                  'msg' => 'Keine passenden Datensaetze gefunden. Bitte zuerst im Modul erfassen.',
               ));
            }
         }
      }

      return '';
   }

   public function render_form_value(array $definition, array $need, array $values) {
      $definition = $this->apply_bind_ref($definition);
      $bind_need = $this->need_bind($definition, (string)($need['key'] ?? ''));
      if ((string)($bind_need['type'] ?? '') !== 'dd_field_value') {
         return '';
      }

      if (array_key_exists($need['key'], $values)) {
         return $this->h($values[$need['key']]);
      }

      $rid = $this->record_id_from_values($definition, $values);
      if ($rid > 0) {
         $record = $this->load_record($definition, $rid);
         $field = (string)($bind_need['field'] ?? '');
         if ($record && $field !== '') {
            return $this->h(trim((string)($record[$field] ?? '')));
         }
      }

      return '';
   }

   public function format_value_label(array $definition, array $need, $value) {
      $definition = $this->apply_bind_ref($definition);
      $need_key = (string)($need['key'] ?? '');
      $bind_need = $this->need_bind($definition, $need_key);

      if (is_array($value) && !empty($value['skipped'])) {
         return '<em>Uebersprungen</em>';
      }

      if ((string)($bind_need['type'] ?? '') === 'dd_select') {
         $record = $this->load_record($definition, (int)$value);
         if ($record) {
            return $this->h($this->label_from_template((string)($bind_need['label'] ?? '#{id}'), $record));
         }
      }

      if ((string)($bind_need['type'] ?? '') === 'dd_field_options') {
         foreach ($this->options_from_fd_field($definition, (string)($bind_need['field'] ?? '')) as $opt) {
            if ((string)($opt['value'] ?? '') === (string)$value) {
               return $this->h($opt['label']);
            }
         }
      }

      if ((string)($bind_need['type'] ?? '') === 'static_select') {
         foreach ((array)($bind_need['options'] ?? array()) as $opt) {
            if (is_array($opt) && (string)($opt['value'] ?? '') === (string)$value) {
               return $this->h($opt['label']);
            }
         }
      }

      return null;
   }

   private function final_status_box(string $type, string $title, string $body, string $extra = ''): string {
      $icon = 'bi-info-circle';
      if ($type === 'success') $icon = 'bi-check2-circle';
      if ($type === 'warning') $icon = 'bi-exclamation-triangle';
      if ($type === 'danger') $icon = 'bi-x-circle';
      return '<div class="alert alert-' . $this->h($type) . ' mb-3">'
         . '<div class="fw-semibold"><i class="bi ' . $icon . '"></i> ' . $this->h($title) . '</div>'
         . '<div>' . $this->h($body) . '</div>'
         . $extra
         . '</div>';
   }

   private function generic_final_status(array $definition, string $instance_status, int $completed, int $total, array $missing_labels): string {
      if ($missing_labels !== array()) {
         return $this->final_status_box(
            'warning',
            'Workflow noch nicht vollstaendig',
            'Es fehlen noch Pflichtschritte: ' . implode(', ', $missing_labels) . '.'
         );
      }

      if ($instance_status === 'finished') {
         return $this->final_status_box(
            'success',
            'Workflow komplett abgeschlossen',
            'Alle notwendigen Schritte sind erledigt. Ergebnis: ' . (string)($definition['result'] ?? $definition['title'] ?? 'Workflow') . '.'
         );
      }

      return $this->final_status_box(
         'info',
         'Workflow bereit zum Abschluss',
         'Alle notwendigen Schritte sind erledigt (' . $completed . ' von ' . $total . '). Pruefe die Zusammenfassung und schliesse den Workflow ab.'
      );
   }

   private function ebay_final_status(array $values, string $instance_status, int $completed, int $total, array $missing_labels): string {
      if ($missing_labels !== array()) {
         return $this->final_status_box(
            'warning',
            'eBay-Workflow noch nicht vollstaendig',
            'Es fehlen noch Pflichtschritte: ' . implode(', ', $missing_labels) . '.'
         );
      }

      $product_id = (int)($values['product'] ?? 0);
      if ($product_id <= 0) {
         return $this->final_status_box('warning', 'eBay-Status unklar', 'Es ist kein Artikel ausgewaehlt.');
      }

      $rows = $this->db()->select(
         'dbxShop|shopProductChannel',
         array('product_id' => $product_id, 'channel_key' => 'ebay'),
         array('external_listing_id', 'external_offer_id', 'export_status', 'export_message', 'last_export_date'),
         'id',
         'DESC',
         '',
         1,
         0,
         0
      );
      $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
      $listing_id = trim((string)($row['external_listing_id'] ?? ''));
      $offer_id = trim((string)($row['external_offer_id'] ?? ''));
      $status = strtolower(trim((string)($row['export_status'] ?? '')));
      $message = trim((string)($row['export_message'] ?? ''));
      $last_export = trim((string)($row['last_export_date'] ?? ''));
      $manual_check = (string)($values['status_check'] ?? '');

      $meta = array();
      if ($status !== '') $meta[] = 'Status: ' . $status;
      if ($last_export !== '') $meta[] = 'Letzter Export: ' . $last_export;
      if ($offer_id !== '') $meta[] = 'Offer-ID: ' . $offer_id;
      if ($listing_id !== '') $meta[] = 'Listing-ID: ' . $listing_id;
      $extra = $meta ? '<div class="small mt-2 text-muted">' . $this->h(implode(' | ', $meta)) . '</div>' : '';
      if ($message !== '') {
         $extra .= '<div class="small mt-1">' . $this->h($message) . '</div>';
      }
      if ($listing_id !== '') {
         $url = 'https://www.ebay.de/itm/' . rawurlencode($listing_id);
         $extra .= '<div class="mt-2"><a class="btn btn-outline-primary btn-sm dbx-win" href="' . $this->h($url) . '" data-url="' . $this->h($url) . '" data-title="eBay-Angebot ansehen" data-width="92%" data-height="90%"><i class="bi bi-box-arrow-up-right"></i> Bei eBay ansehen</a></div>';
      }

      if (in_array($status, array('failed', 'error'), true)) {
         return $this->final_status_box('danger', 'eBay-Veroeffentlichung fehlgeschlagen', 'Der Connector hat einen Fehler gemeldet. Bitte Mapping, Zugangsdaten und eBay-Rueckmeldung pruefen.', $extra);
      }

      if ($listing_id !== '' && in_array($status, array('published', 'exported', 'ready'), true)) {
         return $this->final_status_box('success', 'Artikel ist auf eBay veroeffentlicht', 'Der Export hat eine eBay Listing-ID geliefert. Der Workflow ist fachlich erfolgreich.', $extra);
      }

      if ($last_export === '') {
         return $this->final_status_box('warning', 'eBay-Export noch nicht ausgefuehrt', 'Der Workflow ist inhaltlich vorbereitet, aber es gibt noch keinen gespeicherten Exportlauf.', $extra);
      }

      if ($listing_id === '') {
         $text = 'Der Export wurde ausgefuehrt, aber es ist noch keine eBay Listing-ID gespeichert. Das kann bedeuten, dass die Plattform asynchron prueft oder die Rueckmeldung fehlt.';
         if ($manual_check === 'error') {
            $text = 'Der Workflow wurde mit Fehlerstatus geprueft. Bitte die Connector-Meldung und eBay-Daten korrigieren.';
         }
         return $this->final_status_box('warning', 'eBay-Rueckmeldung fehlt oder ist offen', $text, $extra);
      }

      return $this->final_status_box('info', 'eBay-Status pruefen', 'Alle Workflow-Schritte sind erledigt, der technische Status ist aber nicht eindeutig als veroeffentlicht markiert.', $extra);
   }

   public function render_final_status(array $definition, array $values, string $instance_status, int $completed, int $total, array $missing_labels): string {
      $definition = $this->apply_bind_ref($definition);
      if ((string)($definition['workflow_key'] ?? '') === 'shop_ebay_publish') {
         return $this->ebay_final_status($values, $instance_status, $completed, $total, $missing_labels);
      }
      return $this->generic_final_status($definition, $instance_status, $completed, $total, $missing_labels);
   }

   private function resolve_token($token, array $definition, array $values, array $record) {
      $token = trim((string)$token);
      if ($token === '@now') {
         return date('Y-m-d H:i:s');
      }
      if ($token === '@uid') {
         return (int)dbx()->user();
      }
      if (strpos($token, '@need:') === 0) {
         $need_key = substr($token, 6);
         return $values[$need_key] ?? '';
      }
      if (array_key_exists($token, $values)) {
         return $values[$token];
      }
      if (array_key_exists($token, $record)) {
         return $record[$token];
      }

      return $token;
   }

   private function resolve_map(array $map, array $definition, array $values, array $record) {
      $out = array();
      foreach ($map as $db_field => $source) {
         $out[$db_field] = $this->resolve_token($source, $definition, $values, $record);
      }
      return $out;
   }

   private function shop_workflow_finish(array $definition, array $values) {
      $workflow_key = (string)($definition['workflow_key'] ?? '');
      if ($workflow_key !== 'shop_article_publish' && $workflow_key !== 'shop_ebay_publish') {
         return null;
      }

      $product_id = (int)($values['product'] ?? 0);
      if ($product_id <= 0) {
         return array('ok' => 0, 'message' => 'Kein Artikel ausgewaehlt.');
      }

      if ($workflow_key === 'shop_article_publish') {
         $release = (string)($values['final_check'] ?? '');
         if ($release === 'draft') {
            return array('ok' => 1, 'message' => 'Artikel bleibt als Entwurf vorbereitet.');
         }

         $ok = $this->db()->update(
            'dbxShop|shopProduct',
            array('active' => 1, 'update_date' => date('Y-m-d H:i:s'), 'update_uid' => (int)dbx()->user()),
            array('id' => $product_id, 'trash' => 0),
            1,
            1,
            1,
            1
         );
         if ($ok !== 1) {
            return array('ok' => 0, 'message' => 'Artikel konnte nicht freigegeben werden.');
         }
         return array('ok' => 1, 'message' => 'Artikel wurde im Shop freigegeben.');
      }

      $db = $this->db();
      $channel_rows = $db->select(
         'dbxShop|shopProductChannel',
         array('product_id' => $product_id, 'channel_key' => 'ebay'),
         '*',
         'id',
         'DESC',
         '',
         1,
         0,
         1
      );
      if (!is_array($channel_rows) || $db->get_error_status() !== '') {
         return array('ok' => 0, 'message' => 'Channel-Daten konnten nicht gelesen werden.');
      }
      $channel_row = (is_array($channel_rows) && isset($channel_rows[0])) ? $channel_rows[0] : array();
      if (trim((string)($channel_row['last_export_date'] ?? '')) !== '') {
         $status = strtolower(trim((string)($channel_row['export_status'] ?? '')));
         $message = trim((string)($channel_row['export_message'] ?? ''));
         $listing_id = trim((string)($channel_row['external_listing_id'] ?? ''));
         if (in_array($status, array('failed', 'error'), true)) {
            return array('ok' => 0, 'message' => 'Der eBay-Export ist fehlgeschlagen' . ($message !== '' ? ': ' . $message : '.') );
         }
         if ($listing_id === '' || !in_array($status, array('published', 'exported', 'ready'), true)) {
            return array('ok' => 0, 'message' => 'Die eBay-Rückmeldung ist noch nicht eindeutig erfolgreich. Bitte Status und Listing-ID prüfen' . ($message !== '' ? ': ' . $message : '.') );
         }
         return array(
            'ok' => 1,
            'message' => 'eBay-Veröffentlichung bestätigt: ' . $status . ', Listing-ID ' . $listing_id . ($message !== '' ? ' - ' . $message : '') . '.'
         );
      }

      return array(
         'ok' => 0,
         'message' => 'Der eBay-Export wurde noch nicht ausgeführt. Bitte im Schritt „Export durchführen“ bewusst veröffentlichen und anschließend die Connector-Rückmeldung prüfen.'
      );
   }

   public function apply_finish(array $definition, array $values) {
      $definition = $this->apply_bind_ref($definition);
      $shop_result = $this->shop_workflow_finish($definition, $values);
      if (is_array($shop_result)) {
         return $shop_result;
      }

      $finish = (array)($this->bind_config($definition)['finish'] ?? array());
      if (!$finish || (string)($finish['type'] ?? '') !== 'dd_update') {
         return null;
      }

      $record_cfg = $this->record_config($definition);
      $dd = trim((string)($record_cfg['dd'] ?? ''));
      $rid = $this->record_id_from_values($definition, $values);
      if ($dd === '' || $rid <= 0) {
         return array('ok' => 0, 'message' => 'Kein Datensatz fuer den Abschluss ausgewaehlt.');
      }

      $record = $this->load_record($definition, $rid);
      if (!$record) {
         return array('ok' => 0, 'message' => 'Datensatz #' . $rid . ' wurde nicht gefunden.');
      }

      $update = $this->resolve_map((array)($finish['map'] ?? array()), $definition, $values, $record);

      if (array_key_exists('reply_text', (array)($finish['map'] ?? array()))) {
         $reply_text = trim((string)($update['reply_text'] ?? ''));
         if (strlen($reply_text) < 2) {
            return array('ok' => 0, 'message' => 'Bitte eine Rueckmeldung mit mindestens 2 Zeichen erfassen.');
         }
      }

      $ok = $this->db()->update($dd, $update, $rid);
      if ($ok !== 1) {
         return array('ok' => 0, 'message' => 'Datensatz konnte nicht gespeichert werden.');
      }

      $message = 'Datensatz #' . $rid . ' wurde gespeichert.';
      $mail = (array)($finish['mail'] ?? array());

      if ($mail) {
         $when_need = trim((string)($mail['when_need'] ?? ''));
         $when_value = (string)($mail['when_value'] ?? '1');
         $send = ($when_need === '') || ((string)($values[$when_need] ?? '') === $when_value);

         $config_modul = trim((string)($mail['config_modul'] ?? ($definition['bind']['modul'] ?? '')));
         $mode_key = trim((string)($mail['mode_key'] ?? 'reply_mode'));
         if ($config_modul !== '' && $mode_key !== '' && !$this->config_has_part($config_modul, $mode_key, 'mail')) {
            $send = false;
         }

         if ($send) {
            $to_field = trim((string)($mail['to_field'] ?? 'email'));
            $to = trim((string)($record[$to_field] ?? ''));
            if ($to === '') {
               $message .= ' E-Mail-Versand nicht moeglich (keine Adresse).';
            } else {
               $subject_tpl = (string)($mail['subject_tpl'] ?? 'Antwort');
               $subject = $this->label_from_template($subject_tpl, array_merge($record, $update));
               $body_tpl = trim((string)($mail['body_tpl'] ?? ''));
               $body_vars = array();
               foreach ((array)($mail['body_vars'] ?? array()) as $tpl_key => $source) {
                  $body_vars[$tpl_key] = $this->h($this->resolve_token($source, $definition, $values, $record));
               }
               $html = $body_tpl !== '' ? $this->tpl()->get_tpl($body_tpl, $body_vars) : nl2br($this->h((string)($update['reply_text'] ?? '')));

               $from = trim((string)dbx()->get_cfg($config_modul, 'mail_from'));
               $from_name = trim((string)dbx()->get_cfg($config_modul, 'mail_from_name'));
               $from_param = ($from !== '') ? array('email' => $from, 'name' => $from_name) : '';
               $options = array('text' => strip_tags(str_replace('<br />', "\n", $html)));
               $profile = trim((string)dbx()->get_cfg($config_modul, 'mail_profile'));
               if ($profile !== '') {
                  $options['mail_profile'] = $profile;
               }

               $mail_ok = dbx()->get_system_obj('dbxMail')->send_message($from_param, $to, $subject, $html, 'html', array(), $options);
               if ($mail_ok) {
                  $track = $this->resolve_map((array)($mail['track_fields'] ?? array()), $definition, $values, $record);
                  if ($track) {
                     $this->db()->update($dd, $track, $rid);
                  }
                  $message .= ' E-Mail wurde versendet.';
               } else {
                  $message .= ' E-Mail-Versand fehlgeschlagen.';
               }
            }
         }
      }

      return array('ok' => 1, 'message' => $message);
   }
}
?>
