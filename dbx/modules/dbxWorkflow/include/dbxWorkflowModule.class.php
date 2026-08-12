<?php
namespace dbx\dbxWorkflow;

class dbxWorkflowModule {

   private $ddBind = 'dbxWorkflow|workflowModuleBind';

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function h($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function read_json($value, $default = array()) {
      $value = trim((string)$value);
      if ($value === '') {
         return $default;
      }
      $data = json_decode($value, true);
      return is_array($data) ? $data : $default;
   }

   public function parseBindRef($bindRef) {
      $bindRef = trim((string)$bindRef);
      if ($bindRef === '' || strpos($bindRef, '|') === false) {
         return array('', '');
      }

      $parts = explode('|', $bindRef, 2);
      return array(trim((string)$parts[0]), trim((string)$parts[1]));
   }

   public function loadBindRecord($bindRef) {
      list($modul, $bindKey) = $this->parseBindRef($bindRef);
      if ($modul === '' || $bindKey === '') {
         return array();
      }

      $rows = $this->db()->select(
         $this->ddBind,
         array('modul' => $modul, 'bind_key' => $bindKey, 'active' => 1, 'trash' => 0),
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

   public function applyBindRef(array $definition) {
      $bindRef = trim((string)($definition['bind_ref'] ?? ''));
      if ($bindRef === '') {
         return $definition;
      }

      $row = $this->loadBindRecord($bindRef);
      if (!$row) {
         return $definition;
      }

      $bind = $this->read_json($row['bind_json'] ?? '', array());
      if ($bind) {
         $definition['bind'] = $bind;
      }

      return $definition;
   }

   private function bindConfig(array $definition) {
      return (array)($definition['bind'] ?? array());
   }

   private function recordConfig(array $definition) {
      return (array)($this->bindConfig($definition)['record'] ?? array());
   }

   public function recordIdFromValues(array $definition, array $values) {
      $record = $this->recordConfig($definition);
      $idNeed = trim((string)($record['id_need'] ?? ''));
      if ($idNeed === '' || !array_key_exists($idNeed, $values)) {
         return 0;
      }

      return (int)preg_replace('/\D+/', '', (string)$values[$idNeed]);
   }

   public function loadRecord(array $definition, $rid = 0) {
      $recordCfg = $this->recordConfig($definition);
      $dd = trim((string)($recordCfg['dd'] ?? ''));
      $rid = (int)$rid;
      if ($dd === '' || $rid <= 0) {
         return array();
      }

      $row = $this->db()->select1($dd, $rid);
      return (is_array($row) && $row) ? $row : array();
   }

   private function labelFromTemplate($template, array $row) {
      $label = (string)$template;
      foreach ($row as $key => $value) {
         if (is_scalar($value) || $value === null) {
            $label = str_replace('{' . $key . '}', (string)$value, $label);
         }
      }

      return trim($label);
   }

   private function optionsFromDdSelect(array $bindNeed, array $definition, array $values) {
      $record = $this->recordConfig($definition);
      $dd = trim((string)($record['dd'] ?? ''));
      if ($dd === '') {
         return array();
      }

      $source = array_merge(
         array('dd' => $dd, 'include_from' => (string)($record['id_need'] ?? '')),
         $bindNeed
      );

      return $this->optionsFromSource($source, $values, $this->recordIdFromValues($definition, $values));
   }

   public function optionsFromSource(array $source, array $values = array(), $includeId = 0) {
      $dd = trim((string)($source['dd'] ?? ''));
      if ($dd === '') {
         return array();
      }

      $where = (array)($source['where'] ?? array());
      $fields = (array)($source['fields'] ?? array('id'));
      $valueField = trim((string)($source['value'] ?? 'id'));
      $labelTpl = trim((string)($source['label'] ?? ('{' . $valueField . '}')));

      if ($valueField !== '' && !in_array($valueField, $fields, true)) {
         $fields[] = $valueField;
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
         $id = (string)($row[$valueField] ?? '');
         if ($id === '') {
            continue;
         }
         $seen[$id] = 1;
         $options[] = array(
            'value' => $id,
            'label' => $this->labelFromTemplate($labelTpl, $row),
         );
      }

      $includeId = (string)(int)$includeId;
      if ((int)$includeId > 0 && empty($seen[$includeId])) {
         $record = $this->db()->select1($dd, (int)$includeId, $fields);
         if (is_array($record) && $record) {
            $options[] = array(
               'value' => $includeId,
               'label' => $this->labelFromTemplate($labelTpl, $record),
            );
         }
      }

      return $options;
   }

   private function optionsFromFdField(array $definition, $fieldName) {
      $record = $this->recordConfig($definition);
      $dd = trim((string)($record['dd'] ?? ''));
      $fieldName = trim((string)$fieldName);
      if ($dd === '' || $fieldName === '') {
         return array();
      }

      $oDD = dbx()->get_system_obj('dbxDD');
      $model = $oDD->get_dd_model($dd);
      $optionsRaw = '';

      foreach ((array)($model['fields'] ?? array()) as $field) {
         if ((string)($field['name'] ?? '') === $fieldName) {
            $optionsRaw = (string)($field['options'] ?? '');
            break;
         }
      }

      if ($optionsRaw === '') {
         return array();
      }

      $options = array();
      foreach (explode('&', $optionsRaw) as $pair) {
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

   private function configHasPart($modul, $key, $part) {
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
         return \dbx\dbxContact\dbxContactConfig::modulMailEnabled((string) $modul, (string) $key);
      }

      $mode = strtolower(trim((string) dbx()->get_cfg($modul, $key)));
      return ($mode === 'both' || $mode === 'mail' || strpos($mode, 'mail') !== false);
   }

   private function needBind(array $definition, $needKey) {
      return (array)($this->bindConfig($definition)['needs'][$needKey] ?? array());
   }

   private function needVisible(array $definition, $needKey, array $bindNeed) {
      $showIf = (array)($bindNeed['show_if_config'] ?? array());
      if (!$showIf) {
         return true;
      }

      $modul = trim((string)($showIf['modul'] ?? ''));
      $key = trim((string)($showIf['key'] ?? ''));
      $has = trim((string)($showIf['has'] ?? ''));

      if ($modul === '' || $key === '' || $has === '') {
         return true;
      }

      return $this->configHasPart($modul, $key, $has);
   }

   public function enrichDefinition(array $definition, array $values = array()) {
      $definition = $this->applyBindRef($definition);
      $bindNeeds = (array)($this->bindConfig($definition)['needs'] ?? array());
      $needs = array();

      foreach ((array)($definition['needs'] ?? array()) as $need) {
         if (!is_array($need)) {
            continue;
         }

         $key = (string)($need['key'] ?? '');
         $bindNeed = (array)($bindNeeds[$key] ?? array());

         if ($bindNeed && !$this->needVisible($definition, $key, $bindNeed)) {
            continue;
         }

         if (!empty($need['source']) && is_array($need['source'])) {
            $need['options'] = $this->optionsFromSource(
               $need['source'],
               $values,
               $this->recordIdFromValues($definition, $values)
            );
         } elseif ($bindNeed) {
            $type = (string)($bindNeed['type'] ?? '');
            if ($type === 'dd_select') {
               $need['options'] = $this->optionsFromDdSelect($bindNeed, $definition, $values);
            } elseif ($type === 'dd_field_options') {
               $need['options'] = $this->optionsFromFdField($definition, (string)($bindNeed['field'] ?? ''));
            } elseif ($type === 'static_select') {
               $need['options'] = array_values((array)($bindNeed['options'] ?? array()));
            }
         }

         $needs[] = $need;
      }

      $definition['needs'] = $needs;
      $definition = $this->enrichShopEbayPublishDefinition($definition, $values);
      return $definition;
   }

   private function enrichShopEbayPublishDefinition(array $definition, array $values): array {
      if ((string)($definition['workflow_key'] ?? '') !== 'shop_ebay_publish') {
         return $definition;
      }

      $productId = (int)($values['product'] ?? 0);
      if ($productId <= 0) {
         return $definition;
      }

      $rows = $this->db()->select(
         'dbxShop|shopProductChannel',
         array('product_id' => $productId, 'channel_key' => 'ebay'),
         array('external_listing_id', 'external_offer_id', 'export_status', 'export_message'),
         'id',
         'DESC',
         '',
         1,
         0,
         0
      );
      $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
      $listingId = trim((string)($row['external_listing_id'] ?? ''));
      $offerId = trim((string)($row['external_offer_id'] ?? ''));
      $status = trim((string)($row['export_status'] ?? ''));
      $message = trim((string)($row['export_message'] ?? ''));

      foreach ($definition['needs'] as &$need) {
         if ((string)($need['key'] ?? '') !== 'ebay_view') {
            continue;
         }

         if ($listingId !== '') {
            $need['hint'] = 'Optionaler Kontrollschritt: Das eBay-Angebot wurde mit Listing-ID ' . $listingId . ' gespeichert. Oeffne das Angebot und pruefe Darstellung, Preis, Versand und Bilder.';
            $need['module_links'] = array(
               array(
                  'label' => 'Bei eBay ansehen',
                  'icon' => 'bi-box-arrow-up-right',
                  'url' => 'https://www.ebay.de/itm/' . rawurlencode($listingId),
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
            if ($offerId !== '') {
               $extra[] = 'Offer-ID: ' . $offerId;
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

   public function prefillStart(array $definition, $rid = 0) {
      $definition = $this->applyBindRef($definition);
      $recordCfg = $this->recordConfig($definition);
      $rid = (int)$rid;
      if (empty($recordCfg['prefill_rid']) || $rid <= 0) {
         return array();
      }

      $record = $this->loadRecord($definition, $rid);
      if (!$record) {
         return array();
      }

      $values = array();
      $idNeed = trim((string)($recordCfg['id_need'] ?? ''));
      if ($idNeed !== '') {
         $values[$idNeed] = (string)$rid;
      }

      foreach ((array)($this->bindConfig($definition)['needs'] ?? array()) as $needKey => $bindNeed) {
         $type = (string)($bindNeed['type'] ?? '');
         if ($type === 'dd_field_options' && isset($record[(string)($bindNeed['field'] ?? '')])) {
            $values[$needKey] = (string)$record[(string)$bindNeed['field']];
         }
         if ($type === 'dd_field_value' && isset($record[(string)($bindNeed['field'] ?? '')])) {
            $text = trim((string)$record[(string)$bindNeed['field']]);
            if ($text !== '') {
               $values[$needKey] = $text;
            }
         }
      }

      return $values;
   }

   private function shopEbayReadinessAutomation(array $values): ?array {
      $productId = (int)($values['product'] ?? 0);
      if ($productId <= 0) return null;

      $repo = dbx()->get_include_obj('dbxShopRepository', 'dbxShop');
      if (!is_object($repo) || !method_exists($repo, 'productChannelMapping')) {
         return null;
      }

      $data = $repo->productChannelMapping($productId, 'ebay');
      if (!is_array($data)) {
         return array('value' => 'needs_work', 'message' => 'Artikel oder eBay-Channel wurde nicht gefunden.');
      }

      $product = (array)($data['product'] ?? array());
      $channel = (array)($data['channel'] ?? array());
      $productChannel = (array)($data['product_channel'] ?? array());
      $mapping = (array)($data['mapping'] ?? array());
      $missing = array();

      if ((int)($channel['active'] ?? 0) !== 1) $missing[] = 'eBay-Channel aktiv';
      if ((int)($channel['export_enabled'] ?? 0) !== 1) $missing[] = 'Channel-Export freigeben';
      if ((int)($productChannel['active'] ?? 0) !== 1) $missing[] = 'Artikel dem eBay-Channel zuordnen';

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

      $sku = trim((string)($productChannel['channel_sku'] ?? $product['sku'] ?? ''));
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

   private function shopEbayStatusAutomation(array $values): ?array {
      $productId = (int)($values['product'] ?? 0);
      if ($productId <= 0) return null;

      $rows = $this->db()->select(
         'dbxShop|shopProductChannel',
         array('product_id' => $productId, 'channel_key' => 'ebay'),
         array('external_listing_id', 'export_status', 'export_message', 'last_export_date'),
         'id',
         'DESC',
         '',
         1,
         0,
         0
      );
      $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
      $lastExport = trim((string)($row['last_export_date'] ?? ''));
      if ($lastExport === '') return null;

      $status = strtolower(trim((string)($row['export_status'] ?? '')));
      $listingId = trim((string)($row['external_listing_id'] ?? ''));
      $connectorMessage = trim((string)($row['export_message'] ?? ''));
      $value = 'open';
      if (in_array($status, array('failed', 'error'), true)) {
         $value = 'error';
      } elseif ($listingId !== '' && in_array($status, array('published', 'exported', 'ready'), true)) {
         $value = 'ok';
      }

      $message = 'Connector-Status automatisch ausgewertet: ' . ($status !== '' ? $status : 'offen') . '.';
      if ($listingId !== '') $message .= ' Listing-ID: ' . $listingId . '.';
      if ($connectorMessage !== '') $message .= ' ' . $connectorMessage;
      return array('value' => $value, 'message' => $message);
   }

   public function automateNeed(array $definition, array $need, array $values): ?array {
      if ((string)($need['automation'] ?? 'manual') !== 'observe') return null;
      if ((string)($definition['workflow_key'] ?? '') !== 'shop_ebay_publish') return null;

      $key = (string)($need['key'] ?? '');
      if ($key === 'readiness_check') return $this->shopEbayReadinessAutomation($values);
      if ($key === 'status_check') return $this->shopEbayStatusAutomation($values);
      return null;
   }

   private function contextData(array $definition, array $record) {
      $context = (array)($this->bindConfig($definition)['context'] ?? array());
      $fields = (array)($context['fields'] ?? array());
      $data = array();

      foreach ($fields as $tplKey => $recordKey) {
         $value = $record[(string)$recordKey] ?? '';
         if ($tplKey === 'phone' && trim((string)$value) === '') {
            $value = '-';
         }
         $data[$tplKey] = $this->h($value);
      }

      return $data;
   }

   public function renderStepContext(array $definition, array $need, array $values) {
      $definition = $this->applyBindRef($definition);
      $context = (array)($this->bindConfig($definition)['context'] ?? array());
      if (!$context) {
         return '';
      }

      $hideOn = trim((string)($context['hide_on_need'] ?? ''));
      $rid = $this->recordIdFromValues($definition, $values);

      if ($rid > 0 && $hideOn !== '' && $need['key'] !== $hideOn) {
         $record = $this->loadRecord($definition, $rid);
         if ($record) {
            $tpl = trim((string)($context['tpl'] ?? ''));
            if ($tpl !== '') {
               return $this->tpl()->get_tpl($tpl, $this->contextData($definition, $record));
            }
         }
      }

      if ($hideOn !== '' && $need['key'] === $hideOn && $rid <= 0) {
         $recordCfg = $this->recordConfig($definition);
         $bindNeed = $this->needBind($definition, $hideOn);
         if (($bindNeed['type'] ?? '') === 'dd_select') {
            $options = $this->optionsFromDdSelect($bindNeed, $definition, $values);
            if (!$options) {
               return $this->tpl()->get_tpl('dbx|alert-info', array(
                  'msg' => 'Keine passenden Datensaetze gefunden. Bitte zuerst im Modul erfassen.',
               ));
            }
         }
      }

      return '';
   }

   public function renderFormValue(array $definition, array $need, array $values) {
      $definition = $this->applyBindRef($definition);
      $bindNeed = $this->needBind($definition, (string)($need['key'] ?? ''));
      if ((string)($bindNeed['type'] ?? '') !== 'dd_field_value') {
         return '';
      }

      if (array_key_exists($need['key'], $values)) {
         return $this->h($values[$need['key']]);
      }

      $rid = $this->recordIdFromValues($definition, $values);
      if ($rid > 0) {
         $record = $this->loadRecord($definition, $rid);
         $field = (string)($bindNeed['field'] ?? '');
         if ($record && $field !== '') {
            return $this->h(trim((string)($record[$field] ?? '')));
         }
      }

      return '';
   }

   public function formatValueLabel(array $definition, array $need, $value) {
      $definition = $this->applyBindRef($definition);
      $needKey = (string)($need['key'] ?? '');
      $bindNeed = $this->needBind($definition, $needKey);

      if (is_array($value) && !empty($value['skipped'])) {
         return '<em>Uebersprungen</em>';
      }

      if ((string)($bindNeed['type'] ?? '') === 'dd_select') {
         $record = $this->loadRecord($definition, (int)$value);
         if ($record) {
            return $this->h($this->labelFromTemplate((string)($bindNeed['label'] ?? '#{id}'), $record));
         }
      }

      if ((string)($bindNeed['type'] ?? '') === 'dd_field_options') {
         foreach ($this->optionsFromFdField($definition, (string)($bindNeed['field'] ?? '')) as $opt) {
            if ((string)($opt['value'] ?? '') === (string)$value) {
               return $this->h($opt['label']);
            }
         }
      }

      if ((string)($bindNeed['type'] ?? '') === 'static_select') {
         foreach ((array)($bindNeed['options'] ?? array()) as $opt) {
            if (is_array($opt) && (string)($opt['value'] ?? '') === (string)$value) {
               return $this->h($opt['label']);
            }
         }
      }

      return null;
   }

   private function finalStatusBox(string $type, string $title, string $body, string $extra = ''): string {
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

   private function genericFinalStatus(array $definition, string $instanceStatus, int $completed, int $total, array $missingLabels): string {
      if ($missingLabels !== array()) {
         return $this->finalStatusBox(
            'warning',
            'Workflow noch nicht vollstaendig',
            'Es fehlen noch Pflichtschritte: ' . implode(', ', $missingLabels) . '.'
         );
      }

      if ($instanceStatus === 'finished') {
         return $this->finalStatusBox(
            'success',
            'Workflow komplett abgeschlossen',
            'Alle notwendigen Schritte sind erledigt. Ergebnis: ' . (string)($definition['result'] ?? $definition['title'] ?? 'Workflow') . '.'
         );
      }

      return $this->finalStatusBox(
         'info',
         'Workflow bereit zum Abschluss',
         'Alle notwendigen Schritte sind erledigt (' . $completed . ' von ' . $total . '). Pruefe die Zusammenfassung und schliesse den Workflow ab.'
      );
   }

   private function ebayFinalStatus(array $values, string $instanceStatus, int $completed, int $total, array $missingLabels): string {
      if ($missingLabels !== array()) {
         return $this->finalStatusBox(
            'warning',
            'eBay-Workflow noch nicht vollstaendig',
            'Es fehlen noch Pflichtschritte: ' . implode(', ', $missingLabels) . '.'
         );
      }

      $productId = (int)($values['product'] ?? 0);
      if ($productId <= 0) {
         return $this->finalStatusBox('warning', 'eBay-Status unklar', 'Es ist kein Artikel ausgewaehlt.');
      }

      $rows = $this->db()->select(
         'dbxShop|shopProductChannel',
         array('product_id' => $productId, 'channel_key' => 'ebay'),
         array('external_listing_id', 'external_offer_id', 'export_status', 'export_message', 'last_export_date'),
         'id',
         'DESC',
         '',
         1,
         0,
         0
      );
      $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : array();
      $listingId = trim((string)($row['external_listing_id'] ?? ''));
      $offerId = trim((string)($row['external_offer_id'] ?? ''));
      $status = strtolower(trim((string)($row['export_status'] ?? '')));
      $message = trim((string)($row['export_message'] ?? ''));
      $lastExport = trim((string)($row['last_export_date'] ?? ''));
      $manualCheck = (string)($values['status_check'] ?? '');

      $meta = array();
      if ($status !== '') $meta[] = 'Status: ' . $status;
      if ($lastExport !== '') $meta[] = 'Letzter Export: ' . $lastExport;
      if ($offerId !== '') $meta[] = 'Offer-ID: ' . $offerId;
      if ($listingId !== '') $meta[] = 'Listing-ID: ' . $listingId;
      $extra = $meta ? '<div class="small mt-2 text-muted">' . $this->h(implode(' | ', $meta)) . '</div>' : '';
      if ($message !== '') {
         $extra .= '<div class="small mt-1">' . $this->h($message) . '</div>';
      }
      if ($listingId !== '') {
         $url = 'https://www.ebay.de/itm/' . rawurlencode($listingId);
         $extra .= '<div class="mt-2"><a class="btn btn-outline-primary btn-sm dbx-win" href="' . $this->h($url) . '" data-url="' . $this->h($url) . '" data-title="eBay-Angebot ansehen" data-width="92%" data-height="90%"><i class="bi bi-box-arrow-up-right"></i> Bei eBay ansehen</a></div>';
      }

      if (in_array($status, array('failed', 'error'), true)) {
         return $this->finalStatusBox('danger', 'eBay-Veroeffentlichung fehlgeschlagen', 'Der Connector hat einen Fehler gemeldet. Bitte Mapping, Zugangsdaten und eBay-Rueckmeldung pruefen.', $extra);
      }

      if ($listingId !== '' && in_array($status, array('published', 'exported', 'ready'), true)) {
         return $this->finalStatusBox('success', 'Artikel ist auf eBay veroeffentlicht', 'Der Export hat eine eBay Listing-ID geliefert. Der Workflow ist fachlich erfolgreich.', $extra);
      }

      if ($lastExport === '') {
         return $this->finalStatusBox('warning', 'eBay-Export noch nicht ausgefuehrt', 'Der Workflow ist inhaltlich vorbereitet, aber es gibt noch keinen gespeicherten Exportlauf.', $extra);
      }

      if ($listingId === '') {
         $text = 'Der Export wurde ausgefuehrt, aber es ist noch keine eBay Listing-ID gespeichert. Das kann bedeuten, dass die Plattform asynchron prueft oder die Rueckmeldung fehlt.';
         if ($manualCheck === 'error') {
            $text = 'Der Workflow wurde mit Fehlerstatus geprueft. Bitte die Connector-Meldung und eBay-Daten korrigieren.';
         }
         return $this->finalStatusBox('warning', 'eBay-Rueckmeldung fehlt oder ist offen', $text, $extra);
      }

      return $this->finalStatusBox('info', 'eBay-Status pruefen', 'Alle Workflow-Schritte sind erledigt, der technische Status ist aber nicht eindeutig als veroeffentlicht markiert.', $extra);
   }

   public function renderFinalStatus(array $definition, array $values, string $instanceStatus, int $completed, int $total, array $missingLabels): string {
      $definition = $this->applyBindRef($definition);
      if ((string)($definition['workflow_key'] ?? '') === 'shop_ebay_publish') {
         return $this->ebayFinalStatus($values, $instanceStatus, $completed, $total, $missingLabels);
      }
      return $this->genericFinalStatus($definition, $instanceStatus, $completed, $total, $missingLabels);
   }

   private function resolveToken($token, array $definition, array $values, array $record) {
      $token = trim((string)$token);
      if ($token === '@now') {
         return date('Y-m-d H:i:s');
      }
      if ($token === '@uid') {
         return (int)dbx()->user();
      }
      if (strpos($token, '@need:') === 0) {
         $needKey = substr($token, 6);
         return $values[$needKey] ?? '';
      }
      if (array_key_exists($token, $values)) {
         return $values[$token];
      }
      if (array_key_exists($token, $record)) {
         return $record[$token];
      }

      return $token;
   }

   private function resolveMap(array $map, array $definition, array $values, array $record) {
      $out = array();
      foreach ($map as $dbField => $source) {
         $out[$dbField] = $this->resolveToken($source, $definition, $values, $record);
      }
      return $out;
   }

   private function shopWorkflowFinish(array $definition, array $values) {
      $workflowKey = (string)($definition['workflow_key'] ?? '');
      if ($workflowKey !== 'shop_article_publish' && $workflowKey !== 'shop_ebay_publish') {
         return null;
      }

      $productId = (int)($values['product'] ?? 0);
      if ($productId <= 0) {
         return array('ok' => 0, 'message' => 'Kein Artikel ausgewaehlt.');
      }

      if ($workflowKey === 'shop_article_publish') {
         $release = (string)($values['final_check'] ?? '');
         if ($release === 'draft') {
            return array('ok' => 1, 'message' => 'Artikel bleibt als Entwurf vorbereitet.');
         }

         $ok = $this->db()->update(
            'dbxShop|shopProduct',
            array('active' => 1, 'update_date' => date('Y-m-d H:i:s'), 'update_uid' => (int)dbx()->user()),
            array('id' => $productId, 'trash' => 0),
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
      $channelRows = $db->select(
         'dbxShop|shopProductChannel',
         array('product_id' => $productId, 'channel_key' => 'ebay'),
         '*',
         'id',
         'DESC',
         '',
         1,
         0,
         1
      );
      if (!is_array($channelRows) || $db->get_error_status() !== '') {
         return array('ok' => 0, 'message' => 'Channel-Daten konnten nicht gelesen werden.');
      }
      $channelRow = (is_array($channelRows) && isset($channelRows[0])) ? $channelRows[0] : array();
      if (trim((string)($channelRow['last_export_date'] ?? '')) !== '') {
         $status = strtolower(trim((string)($channelRow['export_status'] ?? '')));
         $message = trim((string)($channelRow['export_message'] ?? ''));
         $listingId = trim((string)($channelRow['external_listing_id'] ?? ''));
         if (in_array($status, array('failed', 'error'), true)) {
            return array('ok' => 0, 'message' => 'Der eBay-Export ist fehlgeschlagen' . ($message !== '' ? ': ' . $message : '.') );
         }
         if ($listingId === '' || !in_array($status, array('published', 'exported', 'ready'), true)) {
            return array('ok' => 0, 'message' => 'Die eBay-Rückmeldung ist noch nicht eindeutig erfolgreich. Bitte Status und Listing-ID prüfen' . ($message !== '' ? ': ' . $message : '.') );
         }
         return array(
            'ok' => 1,
            'message' => 'eBay-Veröffentlichung bestätigt: ' . $status . ', Listing-ID ' . $listingId . ($message !== '' ? ' - ' . $message : '') . '.'
         );
      }

      return array(
         'ok' => 0,
         'message' => 'Der eBay-Export wurde noch nicht ausgeführt. Bitte im Schritt „Export durchführen“ bewusst veröffentlichen und anschließend die Connector-Rückmeldung prüfen.'
      );
   }

   public function applyFinish(array $definition, array $values) {
      $definition = $this->applyBindRef($definition);
      $shopResult = $this->shopWorkflowFinish($definition, $values);
      if (is_array($shopResult)) {
         return $shopResult;
      }

      $finish = (array)($this->bindConfig($definition)['finish'] ?? array());
      if (!$finish || (string)($finish['type'] ?? '') !== 'dd_update') {
         return null;
      }

      $recordCfg = $this->recordConfig($definition);
      $dd = trim((string)($recordCfg['dd'] ?? ''));
      $rid = $this->recordIdFromValues($definition, $values);
      if ($dd === '' || $rid <= 0) {
         return array('ok' => 0, 'message' => 'Kein Datensatz fuer den Abschluss ausgewaehlt.');
      }

      $record = $this->loadRecord($definition, $rid);
      if (!$record) {
         return array('ok' => 0, 'message' => 'Datensatz #' . $rid . ' wurde nicht gefunden.');
      }

      $update = $this->resolveMap((array)($finish['map'] ?? array()), $definition, $values, $record);

      if (array_key_exists('reply_text', (array)($finish['map'] ?? array()))) {
         $replyText = trim((string)($update['reply_text'] ?? ''));
         if (strlen($replyText) < 2) {
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
         $whenNeed = trim((string)($mail['when_need'] ?? ''));
         $whenValue = (string)($mail['when_value'] ?? '1');
         $send = ($whenNeed === '') || ((string)($values[$whenNeed] ?? '') === $whenValue);

         $configModul = trim((string)($mail['config_modul'] ?? ($definition['bind']['modul'] ?? '')));
         $modeKey = trim((string)($mail['mode_key'] ?? 'reply_mode'));
         if ($configModul !== '' && $modeKey !== '' && !$this->configHasPart($configModul, $modeKey, 'mail')) {
            $send = false;
         }

         if ($send) {
            $toField = trim((string)($mail['to_field'] ?? 'email'));
            $to = trim((string)($record[$toField] ?? ''));
            if ($to === '') {
               $message .= ' E-Mail-Versand nicht moeglich (keine Adresse).';
            } else {
               $subjectTpl = (string)($mail['subject_tpl'] ?? 'Antwort');
               $subject = $this->labelFromTemplate($subjectTpl, array_merge($record, $update));
               $bodyTpl = trim((string)($mail['body_tpl'] ?? ''));
               $bodyVars = array();
               foreach ((array)($mail['body_vars'] ?? array()) as $tplKey => $source) {
                  $bodyVars[$tplKey] = $this->h($this->resolveToken($source, $definition, $values, $record));
               }
               $html = $bodyTpl !== '' ? $this->tpl()->get_tpl($bodyTpl, $bodyVars) : nl2br($this->h((string)($update['reply_text'] ?? '')));

               $from = trim((string)dbx()->get_cfg($configModul, 'mail_from'));
               $fromName = trim((string)dbx()->get_cfg($configModul, 'mail_from_name'));
               $fromParam = ($from !== '') ? array('email' => $from, 'name' => $fromName) : '';
               $options = array('text' => strip_tags(str_replace('<br />', "\n", $html)));
               $profile = trim((string)dbx()->get_cfg($configModul, 'mail_profile'));
               if ($profile !== '') {
                  $options['mail_profile'] = $profile;
               }

               $mailOk = dbx()->send_mail($fromParam, $to, $subject, $html, 'html', array(), $options);
               if ($mailOk) {
                  $track = $this->resolveMap((array)($mail['track_fields'] ?? array()), $definition, $values, $record);
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
