<?php
namespace dbx\dbxShop;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

class dbxShopService {

   private array $textForms = array();

   /**
    * Lädt den sprachabhängigen Meldungsvertrag einer UI-FD ohne Formular-Init.
    */
   private function texts(string $fd): \dbxForm {
      if (isset($this->textForms[$fd])) {
         return $this->textForms[$fd];
      }

      dbx()->get_system_obj('dbxForm', 'use');
      $form = new \dbxForm();
      $form->set_form_help_enabled(false);
      $form->_fd = $fd;
      $form->load_fd_messages();
      $this->textForms[$fd] = $form;
      return $form;
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function repo(): dbxShopRepository {
      return dbx()->get_include_obj('dbxShopRepository', 'dbxShop');
   }

   private function paypal(): dbxShopPayPal {
      return dbx()->get_include_obj('dbxShopPayPal', 'dbxShop');
   }

   private function amazonPay(): dbxShopAmazonPay {
      return dbx()->get_include_obj('dbxShopAmazonPay', 'dbxShop');
   }

   private function h($value): string {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   private function readJsonArray($value): array {
      $data = json_decode((string)$value, true);
      return is_array($data) ? $data : array();
   }

   private function money($value): string {
      $language = strtolower(
         substr((string)dbx()->get_system_var('dbx_lng', 'de'), 0, 2)
      );
      return ($language === 'en'
         ? number_format((float)$value, 2, '.', ',')
         : number_format((float)$value, 2, ',', '.')) . ' EUR';
   }

   private function shopConfig(): array {
      $cfg = dbx()->get_config('dbxShop');
      return is_array($cfg) ? $cfg : array();
   }

   private function settingsBool(array $cfg, string $key, bool $default = false): bool {
      if (!array_key_exists($key, $cfg)) {
         return $default;
      }
      $value = $cfg[$key];
      if (is_bool($value)) return $value;
      return in_array(strtolower(trim((string)$value)), array('1', 'true', 'yes', 'on'), true);
   }

   private function shopStyle(): string {
      $file = dirname(__DIR__) . '/design/css/shop.css';
      if (!is_file($file)) {
         return '';
      }
      return '<style>' . file_get_contents($file) . '</style>';
   }

   private function demoShopNoticeHtml(string $id = '', string $extraClass = '', $texts = null): string {
      if (!$this->settingsBool($this->shopConfig(), 'demo_notice_enabled', true)) {
         return '';
      }
      $texts = $texts ?: $this->texts('dbxShop|shop-catalog-filter-form');
      $idAttribute = $id !== '' ? ' id="' . $this->h($id) . '"' : '';
      $classAttribute = $extraClass !== '' ? ' ' . $this->h($extraClass) : '';
      return '<div' . $idAttribute . ' class="alert alert-danger dbx-shop-demo-alert' . $classAttribute . '" role="alert">'
         . '<strong><i class="bi bi-exclamation-octagon-fill"></i> '
         . $this->h($texts->get_fd_message('demo_title'))
         . '</strong><br>'
         . $this->h($texts->get_fd_message('demo_message'))
         . '</div>';
   }

   private function page(string $title, string $subtitle, string $body, string $active = 'catalog'): string {
      return $this->tpl()->get_tpl('dbxShop|start', array(
         'shop_style' => $this->shopStyle(),
         'title' => $this->h($title),
         'subtitle' => $this->h($subtitle),
         'body' => $body,
         'active_catalog' => $active === 'catalog' ? 'active' : '',
         'active_cart' => $active === 'cart' ? 'active' : '',
         'active_checkout' => $active === 'checkout' ? 'active' : '',
         'active_orders' => $active === 'orders' ? 'active' : '',
         'active_legal' => $active === 'legal' ? 'active' : '',
         'active_withdrawal' => $active === 'withdrawal' ? 'active' : '',
      ));
   }

   private function contentDb() {
      $db = dbx()->get_system_obj('dbxDB');
      if (!is_object($db) || !$db->connect_db_server('dbx|dbxContent.db3')) {
         return null;
      }
      return $db;
   }

   private function findContentFolder($db, string $name, int $parentId): int {
      $name = trim($name);
      $parentId = (int) $parentId;
      if ($name === '') {
         return 0;
      }
      $where = "name = '" . str_replace("'", "''", $name) . "' AND parent_id = " . $parentId;
      $rows = $db->select(\dbx\dbxContent\dbxContentLng::ddFolder(), $where, 'id', 'id', 'ASC', '', 1, 0, 0);
      if (!is_array($rows) || !isset($rows[0]['id'])) {
         return 0;
      }
      return (int) $rows[0]['id'];
   }

   private function nextFolderSorter($db, int $parentId): string {
      $parentId = (int) $parentId;
      $rows = $db->select(\dbx\dbxContent\dbxContentLng::ddFolder(), 'parent_id = ' . $parentId, 'sorter', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int) ($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }

   private function nextContentSorter($db, int $folderId): string {
      $folderId = (int) $folderId;
      $rows = $db->select(\dbx\dbxContent\dbxContentLng::ddContent(), 'folder = ' . $folderId, 'sorter', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int) ($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }

   private function ensureShopContentFolder($db): int {
      $folderId = $this->findContentFolder($db, 'shop', 0);
      if ($folderId > 0) {
         return $folderId;
      }

      $data = array(
         'name' => 'shop',
         'parent_id' => 0,
         'sorter' => $this->nextFolderSorter($db, 0),
         'group_read' => '*',
         'template' => 'c-body1-footer',
         'hero_template' => 'image-hero',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
      );
      $ok = (int) $db->insert(\dbx\dbxContent\dbxContentLng::ddFolder(), $data, 0, 1, 0, 0);
      if ($ok !== 1) {
         return 0;
      }
      $folderId = (int) $db->get_insert_id();
      if ($folderId <= 0) {
         return 0;
      }

      if ($folderId > 0) {
         \dbx\dbxContent\dbxContentLngSync::afterFolderSave($db, $folderId, true);
      }
      return $folderId;
   }

   private function shopLegalPageData($db, int $folderId, string $title, string $permalink, string $content): array {
      return array(
         'activ' => 1,
         'folder' => $folderId,
         'title' => substr($title, 0, 254),
         'permalink' => substr($permalink, 0, 254),
         'description' => '',
         'keywords' => '',
         'group_read' => '*',
         'sorter' => $this->nextContentSorter($db, $folderId),
         'template' => 'c-body1-footer',
         'hero_template' => 'parent',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'grid',
         'gallery_click_behavior' => 'lightbox',
         'content' => $content,
      );
   }

   private function syncContentPermalink(int $cid, string $permalink): void {
      if ($cid <= 0 || trim($permalink) === '') {
         return;
      }
      \dbx\dbxContent\dbxContentPermalinkIndex::upsertPage($cid, $permalink, '*', 1);
   }

   private function ensureShopLegalPage($db, int $folderId, string $title, string $permalink, string $content, array $legacyPermalinks = array()): int {
      $dd = \dbx\dbxContent\dbxContentLng::ddContent();
      $existing = null;
      foreach (array_values(array_unique(array_merge(array($permalink), $legacyPermalinks))) as $candidate) {
         $existing = $db->select1($dd, array('permalink' => $candidate), 'id,content,permalink', 0);
         if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
            break;
         }
      }
      if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
         $id = (int) $existing['id'];
         $storedContent = trim((string) ($existing['content'] ?? ''));
         if ($storedContent === '') {
            $data = $this->shopLegalPageData($db, $folderId, $title, $permalink, $content);
            unset($data['sorter'], $data['folder']);
            $db->update($dd, $data, $id, 0, 1, 1, 0);
            \dbx\dbxContent\dbxContentLngSync::afterPageSave($db, $id, false);
         } else {
            $db->update($dd, array('permalink' => $permalink, 'template' => 'c-body1-footer', 'group_read' => '*', 'activ' => 1), $id, 0, 1, 1, 0);
         }
         $this->syncContentPermalink($id, $permalink);
         return $id;
      }

      $data = $this->shopLegalPageData($db, $folderId, $title, $permalink, $content);
      $ok = (int) $db->insert($dd, $data, 0, 1, 0, 0);
      if ($ok !== 1) {
         return 0;
      }
      $id = (int) $db->get_insert_id();
      if ($id <= 0) {
         return 0;
      }
      if ($id > 0) {
         \dbx\dbxContent\dbxContentLngSync::afterPageSave($db, $id, true);
         $this->syncContentPermalink($id, $permalink);
      }
      return $id;
   }

   public function ensureShopLegalPages(): array {
      $db = $this->contentDb();
      if (!is_object($db)) {
         return array();
      }
      $folderId = $this->ensureShopContentFolder($db);
      if ($folderId <= 0) {
         return array();
      }

      return array(
         'legal' => $this->ensureShopLegalPage($db, $folderId, 'Rechtstexte', 'shop-rechtstexte', $this->defaultLegalContent(), array('shop/rechtstexte')),
         'withdrawal' => $this->ensureShopLegalPage($db, $folderId, 'Widerruf', 'shop-widerruf', $this->defaultWithdrawalContent(), array('shop/widerruf')),
      );
   }

   private function renderCmsShopPage(string $key, string $title, string $subtitle, string $active): string {
      $pages = $this->ensureShopLegalPages();
      $cid = (int) ($pages[$key] ?? 0);
      if ($cid <= 0) {
         return $this->page($title, $subtitle, $this->placeholder($title, 'Die CMS-Seite konnte nicht angelegt oder geladen werden.'), $active);
      }

      $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
      $body = is_object($renderer) ? (string) $renderer->renderStatic($cid, array('template' => 'c-body1-footer')) : '';
      if (trim($body) === '') {
         $body = $this->placeholder($title, 'Die CMS-Seite ist leer.');
      }
      return $this->page($title, $subtitle, '<div class="dbx-shop-cms-page">' . $body . '</div>', $active);
   }

   /**
    * Rendert und verarbeitet den Widerruf ausschließlich über dbxForm.
    *
    * Repository- und Mail-Aktion laufen erst nach erfolgreicher Token- und
    * Feldvalidierung. Ein fremder POST kann damit keinen Widerruf anlegen.
    */
   private function withdrawalFormHtml($form): string {
      $form->_action = '?dbx_modul=dbxShop&dbx_run1=withdrawal';
      $form->_data = array_merge($form->_data, array(
         'order_no' => (string)($_POST['order_no'] ?? ''),
         'customer_name' => (string)($_POST['customer_name'] ?? ''),
         'customer_email' => (string)($_POST['customer_email'] ?? ''),
         'customer_address' => (string)($_POST['customer_address'] ?? ''),
         'reason' => (string)($_POST['reason'] ?? ''),
      ));
      $form->add_module_bar(
         $form->get_fd_message('bar_title'),
         'bi-arrow-counterclockwise',
         $form->get_fd_message('bar_subtitle')
      );
      $form->_msg_info = $form->get_fd_message('form_info');
      $form->_msg_error = $form->get_fd_message('validation_error');
      $form->add_rep('bar_title', $form->get_fd_message('bar_title'));
      $form->add_flds();
      if ($form->submit()) {
         if ($form->errors()) {
            $form->_msg_error = $form->get_fd_message(
               'validation_error'
            );
         } else {
            $values = array(
               'order_no' => $form->get_post('order_no', '', 'parameter|max=40'),
               'customer_name' => $form->get_post_data('customer_name', '', '*|min=2|max=180'),
               'customer_email' => $form->get_post('customer_email', '', 'email|max=180'),
               'customer_address' => $form->get_post_data('customer_address', '', '*|min=8|max=2000'),
               'reason' => $form->get_post_data('reason', '', '*|max=3000'),
            );

            $row = $this->repo()->saveWithdrawal($values);
            if (is_array($row)) {
               $this->sendWithdrawalMails($row);
               foreach (array_keys($values) as $fieldName) {
                  $form->set_fld_val($fieldName, '');
               }
               $form->_msg_success = $form->get_fd_message(
                  'withdrawal_success'
               );
            } else {
               $form->_msg_error = $form->get_fd_message(
                  'withdrawal_error'
               );
            }
         }
      }

      return $form->run();
   }

   private function defaultLegalContent(): string {
      return <<<'HTML'
<div class="dbx-shop-legal-text">
   <h1>Rechtstexte</h1>
   <p class="dbx-shop-legal-note"><strong>Hinweis:</strong> Dies ist ein Mustertext fuer einen deutschen Online-Shop. Alle Platzhalter in eckigen Klammern muessen vor der Veroeffentlichung durch echte Betreiber-, Register-, Steuer-, Zahlungs- und Versanddaten ersetzt und rechtlich geprueft werden.</p>

   <h2>Anbieterkennzeichnung</h2>
   <p><strong>[Name/Firma des Shop-Betreibers]</strong><br>
   [Strasse und Hausnummer]<br>
   [PLZ Ort]<br>
   Deutschland</p>
   <p>Vertreten durch: [vertretungsberechtigte Person]<br>
   E-Mail: <a href="mailto:[E-Mail-Adresse]">[E-Mail-Adresse]</a><br>
   Telefon: [Telefonnummer]</p>
   <p>Registereintrag: [Registergericht und Registernummer, falls vorhanden]<br>
   Umsatzsteuer-ID: [USt-IdNr., falls vorhanden]<br>
   Wirtschafts-ID: [W-IdNr., falls vorhanden]</p>
   <p>Zustaendige Aufsichtsbehoerde: [nur eintragen, wenn fuer die Taetigkeit erforderlich]</p>

   <h2>Geltungsbereich</h2>
   <p>Diese Rechtstexte gelten fuer Bestellungen ueber diesen Shop. Abweichende Bedingungen von Kunden gelten nur, wenn der Shop-Betreiber ihnen ausdruecklich zustimmt.</p>

   <h2>Vertragspartner und Vertragsschluss</h2>
   <p>Der Kaufvertrag kommt zustande mit <strong>[Name/Firma des Shop-Betreibers]</strong>. Die Darstellung der Produkte im Shop ist kein rechtlich bindendes Angebot, sondern eine Aufforderung zur Bestellung. Der Kunde gibt ein verbindliches Angebot ab, wenn er den Bestellprozess abschliesst. Die Annahme erfolgt durch Bestellbestaetigung, Zahlungsaufforderung, Versandbestaetigung oder Lieferung der Ware.</p>

   <h2>Preise, Zahlung und Rechnung</h2>
   <p>Alle Preise verstehen sich in Euro und enthalten die gesetzliche Umsatzsteuer, sofern diese im Shop ausgewiesen wird. Zusaetzliche Versandkosten werden vor Abgabe der Bestellung angezeigt. Verfuegbare Zahlungsarten sind: [Zahlungsarten eintragen, z. B. PayPal, Ueberweisung, Rechnung]. Die Rechnung wird elektronisch oder in Textform bereitgestellt.</p>

   <h2>Lieferung und Versand</h2>
   <p>Die Lieferung erfolgt an die vom Kunden angegebene Lieferadresse. Liefergebiete, Versandarten, Versandkosten und Lieferzeiten ergeben sich aus den Angaben im Bestellprozess. Bei digitalen Produkten erfolgt die Bereitstellung per Download, E-Mail, Kundenkonto oder Freischaltung.</p>

   <h2>Eigentumsvorbehalt</h2>
   <p>Gelieferte Waren bleiben bis zur vollstaendigen Bezahlung Eigentum des Shop-Betreibers.</p>

   <h2>Maengelhaftung</h2>
   <p>Es gilt das gesetzliche Maengelhaftungsrecht. Kunden werden gebeten, offensichtliche Transportschaeden moeglichst schnell beim Zusteller und beim Shop-Betreiber zu melden. Die gesetzlichen Rechte des Kunden bleiben davon unberuehrt.</p>

   <h2>Digitale Inhalte und Dienstleistungen</h2>
   <p>Bei digitalen Inhalten, Downloads, Software, Online-Zugaengen oder Dienstleistungen koennen besondere Hinweise zur Vertragsausfuehrung, Kompatibilitaet, Laufzeit, Kuendigung und zum Widerrufsrecht erforderlich sein. Diese Angaben muessen beim jeweiligen Produkt und im Bestellprozess klar dargestellt werden.</p>

   <h2>Streitbeilegung</h2>
   <p>Die Europaeische Kommission stellt eine Plattform zur Online-Streitbeilegung bereit: <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr/</a>. Der Shop-Betreiber ist [bereit/nicht bereit] und [verpflichtet/nicht verpflichtet], an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>

   <h2>Datenschutz</h2>
   <p>Informationen zur Verarbeitung personenbezogener Daten, zu Kontaktformularen, Zahlungsdiensten, Versanddienstleistern, Cookies, Logfiles und Rechten betroffener Personen stehen in der Datenschutzerklaerung: <a href="?dbx_modul=dbxContent&amp;dbx_permalink=datenschutz">Datenschutzerklaerung</a>.</p>
</div>
HTML;
   }

   private function defaultWithdrawalContent(): string {
      return <<<'HTML'
<div class="dbx-shop-legal-text">
   <h1>Widerruf</h1>
   <p class="dbx-shop-legal-note"><strong>Hinweis:</strong> Dies ist ein Muster fuer Verbraucherbestellungen nach deutschem Recht. Platzhalter muessen ersetzt und die Ausnahmen fuer konkrete Produkte, digitale Inhalte und Dienstleistungen geprueft werden.</p>

   <h2>Widerrufsrecht</h2>
   <p>Verbraucher haben grundsaetzlich das Recht, einen Vertrag binnen vierzehn Tagen ohne Angabe von Gruenden zu widerrufen. Die Widerrufsfrist betraegt vierzehn Tage ab dem Tag, an dem der Kunde oder ein benannter Dritter die Ware erhalten hat. Bei digitalen Inhalten oder Dienstleistungen kann der Fristbeginn und das Erloeschen des Widerrufsrechts abweichen.</p>

   <h2>Ausuebung des Widerrufs</h2>
   <p>Um das Widerrufsrecht auszuueben, muss der Kunde den Shop-Betreiber mit einer eindeutigen Erklaerung ueber den Entschluss informieren, den Vertrag zu widerrufen. Die Erklaerung kann per Brief, E-Mail oder ueber ein im Shop bereitgestelltes Formular erfolgen.</p>
   <p><strong>Widerruf an:</strong><br>
   [Name/Firma des Shop-Betreibers]<br>
   [Strasse und Hausnummer]<br>
   [PLZ Ort]<br>
   E-Mail: <a href="mailto:[E-Mail-Adresse]">[E-Mail-Adresse]</a></p>

   <h2>Folgen des Widerrufs</h2>
   <p>Wenn der Kunde den Vertrag widerruft, werden alle Zahlungen einschliesslich der Standard-Lieferkosten unverzueglich und spaetestens binnen vierzehn Tagen ab Eingang der Widerrufserklaerung zurueckgezahlt. Fuer die Rueckzahlung wird dasselbe Zahlungsmittel verwendet, das bei der urspruenglichen Transaktion eingesetzt wurde, sofern nichts anderes vereinbart wurde.</p>
   <p>Bei Waren kann die Rueckzahlung verweigert werden, bis die Ware wieder eingegangen ist oder der Kunde den Nachweis erbracht hat, dass die Ware zurueckgesendet wurde. Der Kunde hat die Ware unverzueglich und spaetestens binnen vierzehn Tagen ab Widerruf zurueckzusenden.</p>

   <h2>Ruecksendekosten und Wertersatz</h2>
   <p>Die unmittelbaren Kosten der Ruecksendung traegt [Kunde/Shop-Betreiber - bitte passend eintragen]. Fuer einen Wertverlust der Ware muss der Kunde nur aufkommen, wenn dieser Wertverlust auf einen nicht notwendigen Umgang mit der Ware zurueckzufuehren ist.</p>

   <h2>Ausschluss oder Erloeschen des Widerrufsrechts</h2>
   <p>Das Widerrufsrecht kann insbesondere ausgeschlossen oder vorzeitig erloschen sein bei individuell angefertigten Waren, versiegelten Waren aus Hygiene- oder Gesundheitsschutzgruenden nach Entfernung der Versiegelung, schnell verderblichen Waren, bestimmten Dienstleistungen sowie digitalen Inhalten, wenn der Kunde ausdruecklich zugestimmt hat, dass die Ausfuehrung vor Ablauf der Widerrufsfrist beginnt, und die gesetzlich erforderlichen Bestaetigungen erteilt wurden.</p>

   <h2>Muster-Widerrufsformular</h2>
   <p>Wenn Sie den Vertrag widerrufen wollen, koennen Sie diesen Text verwenden und an den Shop-Betreiber senden:</p>
   <div class="dbx-shop-withdrawal-form">
      <p>An [Name/Firma, Anschrift und E-Mail-Adresse des Shop-Betreibers]</p>
      <p>Hiermit widerrufe ich den von mir abgeschlossenen Vertrag ueber den Kauf der folgenden Waren oder die Erbringung der folgenden Dienstleistung:</p>
      <p>[Artikel/Dienstleistung eintragen]</p>
      <p>Bestellt am: [Datum]<br>Erhalten am: [Datum]</p>
      <p>Name des Kunden: [Name]<br>Anschrift des Kunden: [Anschrift]</p>
      <p>Datum: [Datum]</p>
      <p>Unterschrift des Kunden: [nur bei Mitteilung auf Papier]</p>
   </div>
</div>
HTML;
   }

   private function mediaUrl(string $path): string {
      $path = trim(str_replace('\\', '/', $path));
      if ($path === '') {
         return '';
      }
      if (preg_match('~^https?://~i', $path) || substr($path, 0, 1) === '/') {
         return $path;
      }
      return dbx()->get_base_url() . ltrim($path, '/');
   }

   private function mediaItemUrl(array $image, bool $thumb = false): string {
      $mediaId = (int)($image['media_id'] ?? 0);
      if ($mediaId > 0) {
         $url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $mediaId;
         if ($thumb) {
            $url .= '&dbx_thumb=1';
         }
         return $url;
      }
      return $this->mediaUrl((string)($image['image_path'] ?? ''));
   }

   private function productImage(array $product): array {
      $images = $product['images'] ?? array();
      if (is_array($images) && isset($images[0]) && is_array($images[0])) {
         return $images[0];
      }
      return array(
         'image_path' => 'files/shop/img/software-dashboard.svg',
         'title' => $product['title'] ?? 'Artikel',
         'alt' => $product['title'] ?? 'Artikel',
      );
   }

   private function primaryGroup(array $product): array {
      return is_array($product['groups'] ?? null) ? (($product['groups'] ?? array())[0] ?? array()) : array();
   }

   private function templateName(string $value, string $fallback, string $prefix = ''): string {
      $value = preg_replace('~[^a-z0-9_-]+~i', '', trim($value));
      if ($value === '') {
         return $fallback;
      }
      if ($prefix !== '' && strpos($value, $prefix) !== 0) {
         return $fallback;
      }
      return $value;
   }

   private function shopTemplateExists(string $template): bool {
      $template = preg_replace('~[^a-z0-9_-]+~i', '', $template);
      if ($template === '') return false;
      return is_file(dirname(__DIR__) . '/tpl/htm/' . $template . '.htm');
   }

   /**
    * Liefert die im Shop-Template tatsaechlich verwendeten Replacement-Namen.
    *
    * Der Cache gilt nur fuer den aktuellen Request. Eigene Templates bleiben
    * kompatibel, weil jeder vorhandene bekannte Platzhalter erkannt wird.
    */
   private function shopTemplateFields(string $template): array {
      static $cache = array();
      $template = preg_replace('~[^a-z0-9_-]+~i', '', $template);
      if ($template === '') return array();
      if (isset($cache[$template])) return $cache[$template];

      $file = dirname(__DIR__) . '/tpl/htm/' . $template . '.htm';
      $source = is_file($file) ? file_get_contents($file) : '';
      $fields = array();
      if (is_string($source) && preg_match_all('~\\{([a-z][a-z0-9_]*)\\}~i', $source, $matches)) {
         foreach ($matches[1] as $field) {
            $fields[strtolower((string)$field)] = true;
         }
      }
      $cache[$template] = $fields;
      return $fields;
   }

   private function mediaTemplateExists(string $template): bool {
      $template = preg_replace('~[^a-z0-9_-]+~i', '', strtolower($template));
      if ($template === '') return false;
      return is_file(dirname(dirname(__DIR__)) . '/dbxContent/tpl/htm/media-' . $template . '.htm');
   }

   private function groupSetting(array $product, string $key, $fallback) {
      $group = $this->primaryGroup($product);
      $value = $group[$key] ?? $fallback;
      return $value === '' || $value === null ? $fallback : $value;
   }

   private function productVisual(array $product, string $class = ''): string {
      $image = $this->productImage($product);
      $src = $this->mediaItemUrl($image, true);
      $alt = (string)($image['alt'] ?? $image['title'] ?? $product['title'] ?? '');
      $count = count($product['images'] ?? array());
      $html = '<div class="dbx-shop-product-visual ' . $this->h($class) . '">';
      $html .= '<img class="dbx-shop-product-img" src="' . $this->h($src) . '" alt="' . $this->h($alt) . '" loading="lazy">';
      $html .= '<span class="dbx-shop-badge">' . $this->h($product['badge'] ?? 'Artikel') . '</span>';
      if ($count > 1) {
         $html .= '<span class="dbx-shop-image-count"><i class="bi bi-images"></i> ' . (int)$count . '</span>';
      }
      $html .= '</div>';
      return $html;
   }

   private function productGallery(array $product): string {
      $images = $product['images'] ?? array();
      if (!is_array($images) || $images === array()) {
         return $this->productVisual($product, 'dbx-shop-product-visual-large');
      }
      $overflow = preg_replace('~[^a-z0-9_-]~i', '', (string)$this->groupSetting($product, 'gallery_overflow', 'grid')) ?: 'grid';
      $click = preg_replace('~[^a-z0-9_-]~i', '', (string)$this->groupSetting($product, 'gallery_click', 'lightbox')) ?: 'lightbox';
      $visible = max(1, (int)$this->groupSetting($product, 'gallery_visible_count', 3));
      $imgSize = preg_replace('~[^a-z0-9_-]~i', '', (string)$this->groupSetting($product, 'gallery_image_size', 'original')) ?: 'original';
      $lightboxWidth = preg_replace('~[^a-z0-9%._-]+~i', '', (string)$this->groupSetting($product, 'gallery_lightbox_width', '100vw')) ?: '100vw';
      $template = preg_replace('~[^a-z0-9_-]+~i', '', strtolower((string)$this->groupSetting($product, 'gallery_template', 'image-gallery'))) ?: 'image-gallery';
      if (!$this->mediaTemplateExists($template)) {
         $template = 'image-gallery';
      }
      $html = '<div class="dbx-shop-product-gallery dbx-content-media-gallery gallery-list gallery-template-' . $this->h($template) . '" data-dbx="lib=gallery|overflow=' . $this->h($overflow) . '|click=' . $this->h($click) . '|img-count=' . $visible . '|img-size=' . $this->h($imgSize) . '|lightbox-width=' . $this->h($lightboxWidth) . '">';
      foreach ($images as $image) {
         $url = $this->mediaItemUrl($image, false);
         $thumbUrl = $this->mediaItemUrl($image, true);
         if ($url === '') {
            continue;
         }
         $title = (string)($image['title'] ?? $product['title'] ?? '');
         $alt = (string)($image['alt'] ?? $title);
         $caption = $title;
         $html .= $this->tpl()->get_tpl('dbxContent|media-' . $template, array(
            'id' => (string)($image['media_id'] ?? ''),
            'url' => $this->h($url),
            'thumb_url' => $this->h($thumbUrl),
            'poster_url' => $this->h($thumbUrl),
            'media_type' => 'image',
            'title' => $this->h($title),
            'alt' => $this->h($alt),
            'caption' => $this->h($caption),
            'slot' => 'gallery',
            'mime' => '',
         ));
      }
      $html .= '</div>';
      return $html;
   }

   private function placeholder(string $headline, string $text, array $items = array()): string {
      $list = '';
      foreach ($items as $item) {
         $list .= '<li>' . $this->h($item) . '</li>';
      }

      return $this->tpl()->get_tpl('dbxShop|placeholder', array(
         'headline' => $this->h($headline),
         'text' => $this->h($text),
         'items' => $list !== '' ? '<ul>' . $list . '</ul>' : '',
      ));
   }

   private function ensureSeed(): void {
      // Der oeffentliche GET-Pfad darf keine Demo- oder Wartungsdaten
      // anlegen. Seed und Migration werden im Admin explizit ausgefuehrt.
      $this->repo()->install();
   }

   private function activeChannel(): string {
      return 'shop';
   }

   private function channelNav(string $active): string {
      $channels = $this->repo()->channels();
      $html = '<div class="dbx-shop-channel-nav">';
      foreach ($channels as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key === '') {
            continue;
         }
         $cls = $key === $active ? ' active' : '';
         $html .= '<a class="btn btn-outline-secondary btn-sm' . $cls . '" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog&amp;channel=' . rawurlencode($key) . '">';
         $html .= $this->h($channel['title'] ?? $key);
         $html .= '</a>';
      }
      $html .= '</div>';
      return $html;
   }

   private function productHasChannel(array $product, string $channel): bool {
      foreach (($product['channels'] ?? array()) as $ch) {
         if ((string)($ch['channel_key'] ?? '') === $channel && (int)($ch['active'] ?? 0) === 1) {
            return true;
         }
      }
      return false;
   }

   private function groupsHtml(array $product): string {
      $html = '';
      foreach (($product['groups'] ?? array()) as $group) {
         $groupId = (int)($group['id'] ?? 0);
         $href = $groupId > 0 ? '?dbx_modul=dbxShop&amp;dbx_run1=catalog&amp;group=' . $groupId : '';
         $label = $this->h($group['title'] ?? '');
         $html .= $href !== ''
            ? '<a class="dbx-shop-chip" href="' . $href . '">' . $label . '</a>'
            : '<span class="dbx-shop-chip">' . $label . '</span>';
      }
      return $html;
   }

   private function catalogGroupId(): int {
      return max(0, (int)dbx()->get_modul_var('group', 0, 'int'));
   }

   private function groupImageUrl(array $group): string {
      $image = $this->repo()->primaryImageForGroup((int)($group['id'] ?? 0));
      if (is_array($image)) {
         $url = $this->mediaItemUrl($image, true);
         if ($url !== '') {
            return $url;
         }
      }
      return $this->mediaUrl('files/shop/img/software-dashboard.svg');
   }

   private function catalogGroupBreadcrumb(int $groupId): string {
      if ($groupId <= 0) {
         return '';
      }
      $path = $this->repo()->groupPath($groupId);
      if ($path === array()) {
         return '';
      }
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $html = '<nav class="dbx-shop-group-breadcrumb" aria-label="'
         . $this->h($texts->get_fd_message('groups_aria')) . '">';
      $html .= '<a href="?dbx_modul=dbxShop&amp;dbx_run1=catalog">'
         . $this->h($texts->get_fd_message('all_products')) . '</a>';
      foreach ($path as $group) {
         $id = (int)($group['id'] ?? 0);
         $title = $this->h($group['title'] ?? '');
         if ($id === $groupId) {
            $html .= '<span>' . $title . '</span>';
         } else {
            $html .= '<a href="?dbx_modul=dbxShop&amp;dbx_run1=catalog&amp;group=' . $id . '">' . $title . '</a>';
         }
      }
      $html .= '</nav>';
      return $html;
   }

   private function catalogGroupNavigation(int $parentId): string {
      $groups = $this->repo()->groupsByParent($parentId, true);
      if ($groups === array()) {
         return '';
      }
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $html = '<section class="dbx-shop-group-grid" aria-label="'
         . $this->h($texts->get_fd_message('groups_aria')) . '">';
      foreach ($groups as $group) {
         $id = (int)($group['id'] ?? 0);
         if ($id <= 0) continue;
         $title = trim((string)(
            $group['title'] ?? $texts->get_fd_message('group_fallback')
         ));
         $description = trim((string)($group['description'] ?? ''));
         $html .= '<a class="dbx-shop-group-card" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog&amp;group=' . $id . '">';
         $html .= '<span class="dbx-shop-group-card-image"><img src="' . $this->h($this->groupImageUrl($group)) . '" alt="' . $this->h($title) . '" loading="lazy"></span>';
         $html .= '<span class="dbx-shop-group-card-body"><strong>' . $this->h($title) . '</strong>';
         if ($description !== '') {
            $html .= '<small>' . $this->h($description) . '</small>';
         }
         $html .= '</span></a>';
      }
      $html .= '</section>';
      return $html;
   }

   private function productInCatalogGroup(array $product, int $groupId): bool {
      if ($groupId <= 0) {
         return true;
      }
      if ((int)($product['product_group_id'] ?? 0) === $groupId) {
         return true;
      }
      foreach (($product['groups'] ?? array()) as $group) {
         if ((int)($group['id'] ?? 0) === $groupId) {
            return true;
         }
      }
      return false;
   }

   private function channelsHtml(array $product): string {
      $html = '';
      foreach (($product['channels'] ?? array()) as $channel) {
         if ((int)($channel['active'] ?? 0) !== 1) {
            continue;
         }
         $html .= '<span class="dbx-shop-chip dbx-shop-chip-channel">' . $this->h($channel['title'] ?? $channel['channel_key'] ?? '') . '</span>';
      }
      return $html;
   }

   private function normalizedText(string $value): string {
      $value = strtolower($value);
      $value = strtr($value, array('ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'));
      $value = preg_replace('~[^a-z0-9]+~', ' ', $value) ?: '';
      return preg_replace('~\\s+~', ' ', trim($value)) ?: '';
   }

   private function attributeText(array $product): string {
      $parts = array();
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
         $parts[] = (string)($attribute['title'] ?? '');
         $parts[] = (string)($attribute['attr_key'] ?? '');
         if ($value !== '') {
            $parts[] = $value;
         }
      }
      return implode(' ', $parts);
   }

   private function groupText(array $product): string {
      $parts = array();
      foreach (($product['groups'] ?? array()) as $group) {
         $parts[] = (string)($group['title'] ?? '');
         $parts[] = (string)($group['group_key'] ?? '');
         $parts[] = (string)($group['description'] ?? '');
         $parts[] = (string)($group['attribute_notes'] ?? '');
      }
      return implode(' ', $parts);
   }

   private function searchTerms(string $query): array {
      $terms = preg_split('~\\s+~', $this->normalizedText($query)) ?: array();
      $stopWords = array_flip(array('der','die','das','den','dem','des','ein','eine','einer','einem','und','oder','mit','ohne','fuer','fur','von','im','in','am','an','auf','zu'));
      $out = array();
      foreach ($terms as $term) {
         $term = trim($term);
         if ($term === '' || isset($stopWords[$term])) {
            continue;
         }
         if (strlen($term) < 2 && !ctype_digit($term)) {
            continue;
         }
         $out[$term] = true;
      }
      return array_keys($out);
   }

   private function textMatchesSearchTerm(string $text, string $term): bool {
      return $this->searchFieldScore($text, $term, 1) > 0;
   }

   private function searchFieldScore(string $text, string $term, int $weight): int {
      if ($text === '' || $term === '') {
         return 0;
      }
      if ($text === $term) {
         return $weight * 8;
      }
      $termLength = strlen($term);
      $compactText = str_replace(' ', '', $text);
      $compactTerm = str_replace(' ', '', $term);
      if (strpos($text, $term) !== false || strpos($compactText, $compactTerm) !== false) {
         return $weight * 5;
      }
      $best = 0;
      foreach (preg_split('~\\s+~', $text) ?: array() as $token) {
         $token = trim($token);
         if ($token === '') {
            continue;
         }
         if ($token === $term) {
            $best = max($best, $weight * 6);
            continue;
         }
         if ($termLength < 3) {
            continue;
         }
         if (strlen($token) >= $termLength && strpos($token, $term) === 0) {
            $best = max($best, $weight * 4);
            continue;
         }
         if (
            $termLength >= 4
            && strlen($token) >= 4
            && substr($token, 0, 3) === substr($term, 0, 3)
            && abs(strlen($token) - $termLength) <= ($termLength >= 7 ? 2 : 1)
            && levenshtein($token, $term) <= ($termLength >= 7 ? 2 : 1)
         ) {
            $best = max($best, $weight * 2);
         }
      }
      return $best;
   }

   private function productSearchScore(array $product, string $query): int {
      $terms = $this->searchTerms($query);
      if ($terms === array()) {
         return 1;
      }

      $primary = $this->normalizedText(implode(' ', array(
         (string)($product['sku'] ?? ''),
         (string)($product['title'] ?? ''),
         (string)($product['category'] ?? ''),
         (string)($product['badge'] ?? ''),
         (string)($product['product_type'] ?? ''),
      )));
      $secondary = $this->normalizedText(implode(' ', array(
         (string)($product['summary'] ?? ''),
         (string)($product['description'] ?? ''),
      )));
      $attributes = $this->normalizedText($this->attributeText($product));
      $groups = $this->normalizedText($this->groupText($product));

      $score = 0;
      $matched = 0;
      $firstTermPrimaryScore = 0;
      $termCount = count($terms);

      foreach ($terms as $idx => $term) {
         $primaryScore = $this->searchFieldScore($primary, $term, 10);
         $termScore = max(
            $primaryScore,
            $this->searchFieldScore($attributes, $term, 7),
            $this->searchFieldScore($secondary, $term, 4),
            $this->searchFieldScore($groups, $term, 3)
         );

         if ($idx === 0) {
            $firstTermPrimaryScore = $primaryScore;
         }
         if ($termScore > 0) {
            $matched++;
            $score += $termScore;
         }
      }

      if ($matched === 0) {
         return 0;
      }
      if ($termCount === 1) {
         return $score;
      }

      if ($matched === $termCount || $firstTermPrimaryScore > 0 || $score >= 20) {
         return $score + ($matched * 3);
      }

      return 0;
   }

   private function attributesInlineHtml(array $product, int $max = 4): string {
      $html = '';
      $count = 0;
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
         if ($value === '') continue;
         $html .= '<span class="dbx-shop-attribute-chip"><span>' . $this->h($attribute['title'] ?? '') . '</span><strong>' . $this->h($value) . '</strong></span>';
         $count++;
         if ($count >= $max) break;
      }
      return $html !== '' ? '<div class="dbx-shop-attribute-row">' . $html . '</div>' : '';
   }

   private function attributesTableHtml(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $rows = '';
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
         if ($value === '') continue;
         $rows .= '<tr><th>' . $this->h($attribute['title'] ?? '') . '</th><td>' . $this->h($value) . '</td></tr>';
      }
      if ($rows === '') {
         return '';
      }
      return '<div class="dbx-shop-attributes"><h4>'
         . $this->h($texts->get_fd_message('attributes_heading'))
         . '</h4><table><tbody>' . $rows . '</tbody></table></div>';
   }

   private function selectedAttributeFilters(): array {
      $raw = $_GET['attr'] ?? array();
      if (!is_array($raw)) {
         return array();
      }
      $out = array();
      foreach ($raw as $id => $value) {
         $id = (int)$id;
         $value = trim((string)$value);
         if ($id > 0 && $value !== '') {
            $out[$id] = $value;
         }
      }
      return $out;
   }

   private function productMatchesQuery(array $product, string $query): bool {
      return $this->productSearchScore($product, $query) > 0;
   }

   private function productMatchesAttributeFilters(array $product, array $filters): bool {
      if ($filters === array()) {
         return true;
      }
      $values = array();
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $id = (int)($attribute['id'] ?? 0);
         if ($id <= 0) continue;
         $values[$id] = $this->normalizedText((string)($attribute['value_text'] ?? ''));
      }
      foreach ($filters as $id => $value) {
         if (!isset($values[$id]) || $values[$id] !== $this->normalizedText((string)$value)) {
            return false;
         }
      }
      return true;
   }

   private function catalogFiltersHtml(string $channel, string $query, array $selected, int $groupId = 0): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $filterFields = '';
      foreach ($this->repo()->attributeFilterDefinitions() as $definition) {
         $id = (int)($definition['id'] ?? 0);
         $values = $definition['values'] ?? array();
         if ($id <= 0 || !is_array($values) || $values === array()) continue;
         $label = trim((string)($definition['title'] ?? ''));
         $group = trim((string)($definition['group_title'] ?? ''));
         $filterFields .= '<label><span>' . $this->h($group !== '' ? $group . ': ' . $label : $label) . '</span><select class="form-select form-select-sm" name="attr[' . $id . ']">';
         $filterFields .= '<option value="">'
            . $this->h($texts->get_fd_message('all_option'))
            . '</option>';
         foreach ($values as $value) {
            $sel = isset($selected[$id]) && $this->normalizedText((string)$selected[$id]) === $this->normalizedText((string)$value) ? ' selected' : '';
            $filterFields .= '<option value="' . $this->h($value) . '"' . $sel . '>' . $this->h($value) . '</option>';
         }
         $filterFields .= '</select></label>';
      }
      $advancedFilters = '';
      if ($filterFields !== '') {
         $open = $selected !== array() ? ' open' : '';
         $advancedFilters .= '<details class="dbx-shop-filter-advanced"' . $open . '>';
         $advancedFilters .= '<summary><i class="bi bi-sliders"></i> '
            . $this->h($texts->get_fd_message('refine_filters'))
            . '</summary>';
         $advancedFilters .= '<div class="dbx-shop-filter-row">' . $filterFields . '</div>';
         $advancedFilters .= '</details>';
      }

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-catalog-filter-form', 'shop-catalog-filter-form');
      $form->set_editor_class_file(__FILE__);
      $form->_fd = 'dbxShop|shop-catalog-filter-form';
      $form->load_fd_messages();
      $form->_action = '?dbx_modul=dbxShop&dbx_run1=catalog';
      $form->_data = array('q' => $query);
      $form->_msg_info = '';
      $form->_msg_success = '';
      $form->_msg_error = '';
      $form->_msg_warning = '';
      $form->add_rep('bar_title', $texts->get_fd_message('bar_title'));
      $form->add_rep('frame_skip_form_wrap', '1');
      $form->add_fld('q');
      $form->add_rep('advanced_filters', $advancedFilters);
      $form->add_rep('group_hidden', $groupId > 0 ? '<input type="hidden" name="group" value="' . $groupId . '">' : '');
      return $form->run();
   }

   /**
    * Baut nur Werte, die das konkrete Karten-/Detailtemplate verwendet.
    *
    * Teure Teilrenderer wie Galerie und dbxForm werden dadurch nicht fuer
    * unsichtbare Platzhalter einer Produktkarte ausgefuehrt.
    */
   private function productTemplateData(
      array $product,
      string $channel,
      bool $detail = false,
      ?array $templateFields = null
   ): array {
      $sku = (string)($product['sku'] ?? '');
      $uses = static fn(string $field): bool => $templateFields === null || isset($templateFields[$field]);
      $data = array();
      if ($uses('sku')) $data['sku'] = $this->h($sku);
      if ($uses('title')) $data['title'] = $this->h($product['title'] ?? '');
      if ($uses('summary')) $data['summary'] = $this->h($product['summary'] ?? '');
      if ($uses('description')) {
         $data['description'] = $this->h($product['description'] ?? $product['summary'] ?? '');
      }
      if ($uses('groups')) $data['groups'] = $this->groupsHtml($product);
      if ($uses('channels')) $data['channels'] = '';
      if ($uses('attributes')) {
         $data['attributes'] = $detail
            ? $this->attributesTableHtml($product)
            : $this->attributesInlineHtml($product, 4);
      }
      if ($uses('attributes_table')) {
         $data['attributes_table'] = $this->attributesTableHtml($product);
      }
      if ($uses('gallery')) $data['gallery'] = $this->productGallery($product);
      if ($uses('visual')) $data['visual'] = $this->productVisual($product);
      if ($uses('price')) $data['price'] = $this->money($product['price_gross'] ?? 0);
      if ($uses('tax_shipping')) $data['tax_shipping'] = $this->taxShippingHtml($product);
      if ($uses('shipping_info')) $data['shipping_info'] = $this->shippingInfoHtml($product);
      if ($uses('stock_info')) $data['stock_info'] = $this->stockInfoHtml($product);
      if ($uses('buy_form')) $data['buy_form'] = $this->buyFormHtml($product);
      if ($uses('detail_url')) {
         $data['detail_url'] = '?dbx_modul=dbxShop&amp;dbx_run1=product&amp;sku=' . rawurlencode($sku);
      }
      if ($uses('catalog_url')) $data['catalog_url'] = '?dbx_modul=dbxShop&amp;dbx_run1=catalog';
      if ($uses('cart_url')) {
         $data['cart_url'] = '?dbx_modul=dbxShop&amp;dbx_run1=cart&amp;sku=' . rawurlencode($sku);
      }
      if ($uses('card_class')) {
         $data['card_class'] = $this->h($this->cssTemplateClass(
            (string)$this->groupSetting($product, 'card_template', 'product-card-default')
         ));
      }
      if ($uses('detail_class')) {
         $data['detail_class'] = $this->h($this->cssTemplateClass(
            (string)$this->groupSetting($product, 'detail_template', 'product-detail-default')
         ));
      }
      return $data;
   }

   private function cssTemplateClass(string $template): string {
      $template = preg_replace('~[^a-z0-9_-]+~i', '-', trim($template));
      return $template !== '' ? 'is-template-' . strtolower($template) : '';
   }

   private function renderProductCard(array $product, string $channel): string {
      $template = $this->templateName((string)$this->groupSetting($product, 'card_template', 'product-card-default'), 'product-card-default', 'product-card-');
      if (!$this->shopTemplateExists($template)) {
         $template = 'product-card-default';
      }
      return $this->tpl()->get_tpl(
         'dbxShop|' . $template,
         $this->productTemplateData($product, $channel, false, $this->shopTemplateFields($template))
      );
   }

   private function catalogReportHtml(array $products, string $channel, string $query, array $attributeFilters, int $groupId): string {
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-catalog-report', 'dbxShop|shop-catalog-report');
      $report->_fd = 'dbxShop|shop-catalog-filter-form';
      $report->load_fd_messages();
      $report->set_editor_class_file(__FILE__);
      $report->_mode = 'tpl';
      $report->_pages = true;
      $report->_create_row_select = false;
      $report->_create_row_edit = false;
      $report->_create_row_delete = false;
      $report->_but_pagination = 7;
      $rowsPerPage = max(6, min(48, (int)$report->get_fld_val('dbx_rrows', 12, 'int')));
      $position = max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
      $filteredCount = count($products);
      if ($position >= $filteredCount && $filteredCount > 0) {
         $position = max(0, (int)(floor(($filteredCount - 1) / $rowsPerPage) * $rowsPerPage));
      }
      $visibleCandidates = array_slice($products, $position, $rowsPerPage);
      $visible = $this->repo()->productsByIds(array_map(
         static fn($product) => (int)($product['id'] ?? 0),
         $visibleCandidates
      ));
      $rows = array();
      foreach ($visible as $product) {
         $rows[] = array(
            'id' => (int)($product['id'] ?? 0),
            'card' => $this->renderProductCard($product, $channel),
         );
      }

      $queryParts = array(
         'dbx_modul' => 'dbxShop',
         'dbx_run1' => 'catalog',
      );
      if ($query !== '') {
         $queryParts['q'] = $query;
      }
      if ($groupId > 0) {
         $queryParts['group'] = $groupId;
      }
      foreach ($attributeFilters as $id => $value) {
         $queryParts['attr[' . (int)$id . ']'] = (string)$value;
      }
      $report->_action = '?' . http_build_query($queryParts, '', '&');
      $report->_rflds = array(
         'card' => $report->get_fd_message('column_products'),
      );
      $report->_rpt_format = array('card' => 'html');
      $report->_rrows = $rowsPerPage;
      $report->_rpos = $position;
      $report->_count_all = $filteredCount;
      $report->_rcount = $filteredCount;
      $report->_rdata = $rows;
      return $report->run();
   }

   private function renderProductDetail(array $product, string $channel): string {
      $template = $this->templateName((string)$this->groupSetting($product, 'detail_template', 'product-detail-default'), 'product-detail-default', 'product-detail-');
      if (!$this->shopTemplateExists($template)) {
         $template = 'product-detail-default';
      }
      $data = $this->productTemplateData(
         $product,
         $channel,
         true,
         $this->shopTemplateFields($template)
      );
      return $this->tpl()->get_tpl('dbxShop|' . $template, $data);
   }

   private function taxShippingHtml(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $tax = $this->h(number_format((float)($product['effective_tax_rate'] ?? 0), 2, ',', '.'));
      $shipping = (float)($product['effective_shipping_gross'] ?? 0);
      $shippingText = $shipping > 0
         ? $this->money($shipping) . ' ' . $texts->get_fd_message('shipping_suffix')
         : $texts->get_fd_message('free_shipping');
      $showTax = $this->settingsBool($this->shopConfig(), 'tax_display_enabled', true);
      $parts = array();
      if ($showTax) {
         $parts[] = $tax . '% ' . $texts->get_fd_message('tax_label');
      }
      $parts[] = $this->h($shippingText);
      return '<small>' . implode(', ', $parts) . '</small>';
   }

   private function shippingInfoHtml(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $deliveryTime = trim((string)($product['effective_delivery_time'] ?? ''));
      $shippingWay = trim((string)($product['effective_shipping_way'] ?? ''));
      $shipping = (float)($product['effective_shipping_gross'] ?? 0);
      $shippingText = $shipping > 0
         ? $this->money($shipping)
         : $texts->get_fd_message('free_shipping');
      $rows = '';

      if ($deliveryTime !== '') {
         $rows .= '<div class="dbx-shop-shipping-info-row"><i class="bi bi-clock"></i><span>'
            . $this->h($texts->get_fd_message('delivery_time'))
            . ': ' . $this->h($deliveryTime) . '</span></div>';
      }
      if ($shippingWay !== '') {
         $rows .= '<div class="dbx-shop-shipping-info-row"><i class="bi bi-truck"></i><span>'
            . $this->h($texts->get_fd_message('shipping_method'))
            . ': ' . $this->h($shippingWay) . '</span></div>';
      }
      $rows .= '<div class="dbx-shop-shipping-info-row"><i class="bi bi-box-seam"></i><span>'
         . $this->h($texts->get_fd_message('shipping_costs'))
         . ': ' . $this->h($shippingText) . '</span></div>';

      return '<div class="dbx-shop-shipping-info">' . $rows . '</div>';
   }

   private function stockInfoHtml(array $product): string {
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $cfg = $this->shopConfig();
      if (!$this->settingsBool($cfg, 'stock_enabled', false) || (string)($product['product_type'] ?? '') !== 'physical') {
         return '';
      }
      $stock = (int)($product['stock'] ?? 0);
      if ($stock <= 0) {
         return '<div class="alert alert-warning py-2 mb-2"><i class="bi bi-exclamation-triangle"></i> '
            . $this->h($texts->get_fd_message('stock_out')) . '</div>';
      }
      if ($stock <= 3) {
         return '<div class="alert alert-info py-2 mb-2"><i class="bi bi-box-seam"></i> '
            . $this->h($texts->format_fd_message(
               'stock_low',
               array('count' => $stock)
            )) . '</div>';
      }
      return '<div class="dbx-shop-shipping-info-row"><i class="bi bi-box-seam"></i><span>'
         . $this->h($texts->get_fd_message('stock_available'))
         . '</span></div>';
   }

   /**
    * Erstellt das Add-to-cart-Formular für ein Produkt.
    *
    * Dieselbe Factory wird beim Rendern und beim Ziel-POST benutzt. So ist
    * die Warenkorbmutation an die dbxForm-Tokenprüfung gebunden.
    */
   private function buyForm(array $product): ?\dbxForm {
      $sku = (string)($product['sku'] ?? '');
      if ($sku === '') {
         return null;
      }
      $cfg = $this->shopConfig();
      if ($this->settingsBool($cfg, 'stock_enabled', false) && (string)($product['product_type'] ?? '') === 'physical' && (int)($product['stock'] ?? 0) <= 0) {
         return null;
      }
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-buy-' . preg_replace('~[^a-z0-9_-]+~i', '-', strtolower($sku)), 'shop-buy-form');
      $form->_fd = 'dbxShop|shop-cart';
      $form->load_fd_messages();
      $form->set_editor_class_file(__FILE__);
      $form->_action = '?dbx_modul=dbxShop&dbx_run1=cart&sku=' . rawurlencode($sku);
      $form->_data = array_merge($form->_data, array('qty' => 1));
      $form->_msg_info = '';
      $form->_msg_success = '';
      $form->_msg_error = '';
      $form->_msg_warning = '';
      $form->add_rep('bar_title', $form->get_fd_message('buy_form_title'));
      $form->add_rep('frame_skip_form_wrap', '1');
      $form->add_rep('buy_form_id', preg_replace('~[^a-z0-9_-]+~i', '-', $sku));
      $form->add_rep('catalog_url', '?dbx_modul=dbxShop&amp;dbx_run1=catalog');
      $form->add_fld(
         'qty',
         'dbxShop|shop-field-qty',
         label: $form->get_fd_message('label_quantity'),
         rules: 'int|min=1'
      );
      return $form;
   }

   private function buyFormHtml(array $product): string {
      $form = $this->buyForm($product);
      if (!$form) {
         $texts = $this->texts('dbxShop|shop-cart');
         return '<a class="btn btn-outline-secondary" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog"><i class="bi bi-arrow-left"></i> '
            . $this->h($texts->get_fd_message('back_to_catalog'))
            . '</a>';
      }
      return $form->run();
   }

   private function startSession(): void {
      if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
         session_start();
      }
      if (!isset($_SESSION['dbxShop_cart']) || !is_array($_SESSION['dbxShop_cart'])) {
         $_SESSION['dbxShop_cart'] = array();
      }
   }

   private function checkoutRequestId(): string {
      $posted = strtolower(trim((string)($_POST['checkout_request_id'] ?? '')));
      if (preg_match('/^[a-f0-9]{32,64}$/', $posted) === 1) {
         return $posted;
      }
      return bin2hex(random_bytes(24));
   }

   private function checkoutRequestOrder(string $requestId): ?array {
      $this->startSession();
      $requests = $_SESSION['dbxShop_checkout_requests'] ?? array();
      $orderNo = is_array($requests) ? (string)($requests[$requestId] ?? '') : '';
      return $orderNo !== '' ? $this->repo()->orderByNo($orderNo) : null;
   }

   private function rememberCheckoutRequest(string $requestId, array $order): void {
      $this->startSession();
      $requests = $_SESSION['dbxShop_checkout_requests'] ?? array();
      $requests = is_array($requests) ? $requests : array();
      $requests[$requestId] = (string)($order['order_no'] ?? '');
      $_SESSION['dbxShop_checkout_requests'] = array_slice($requests, -25, null, true);
   }

   private function cartItems(): array {
      $this->startSession();
      return $_SESSION['dbxShop_cart'];
   }

   private function cartQuantityTotal(): int {
      $count = 0;
      foreach ($this->cartItems() as $qty) {
         $count += max(0, (int)$qty);
      }
      return $count;
   }

   private function requestedQuantity($value, int $fallback = 1): int {
      $qty = (int)$value;
      return max(1, min(999, $qty > 0 ? $qty : $fallback));
   }

   private function addToCart(string $sku, int $qty = 1): void {
      if ($sku === '') {
         return;
      }
      $product = $this->repo()->productBySku($sku);
      if (!$product) {
         return;
      }
      $this->startSession();
      $_SESSION['dbxShop_cart'][$sku] = max(0, (int)($_SESSION['dbxShop_cart'][$sku] ?? 0)) + $this->requestedQuantity($qty);
   }

   private function updateCartQuantities(array $quantities): void {
      $this->startSession();
      foreach ($quantities as $sku => $qty) {
         $sku = (string)$sku;
         if (!isset($_SESSION['dbxShop_cart'][$sku])) {
            continue;
         }
         $_SESSION['dbxShop_cart'][$sku] = $this->requestedQuantity($qty);
      }
   }

   private function removeFromCart(string $sku): void {
      $sku = trim($sku);
      if ($sku === '') {
         return;
      }
      $this->startSession();
      unset($_SESSION['dbxShop_cart'][$sku]);
   }

   private function addedToCartDialog(array $product): string {
      $texts = $this->texts('dbxShop|shop-cart');
      $title = trim((string)($product['title'] ?? $texts->get_fd_message('product')));
      $qty = $this->requestedQuantity(dbx()->get_modul_var('qty', '1', 'parameter'));
      $body = '<div class="dbx-shop-added-dialog" role="dialog" aria-modal="true" aria-labelledby="dbx-shop-added-title">';
      $body .= '<div class="dbx-shop-added-dialog-backdrop"></div>';
      $body .= '<div class="dbx-shop-added-dialog-box">';
      $body .= '<div class="dbx-shop-added-dialog-icon"><i class="bi bi-check2"></i></div>';
      $body .= '<h3 id="dbx-shop-added-title">'
         . $this->h($texts->get_fd_message('added_title'))
         . '</h3>';
      $body .= '<p>' . $this->h($title) . ' <span class="dbx-shop-added-qty">x ' . (int)$qty . '</span></p>';
      $body .= '<div class="dbx-shop-added-dialog-actions">';
      $body .= '<a class="btn btn-outline-primary" href="?dbx_modul=dbxShop&amp;dbx_run1=cart"><i class="bi bi-cart"></i> '
         . $this->h($texts->get_fd_message('cart_title')) . '</a>';
      $body .= '<a class="btn btn-primary" href="?dbx_modul=dbxShop&amp;dbx_run1=checkout"><i class="bi bi-credit-card"></i> '
         . $this->h($texts->get_fd_message('checkout')) . '</a>';
      $body .= '<a class="btn btn-outline-secondary" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog"><i class="bi bi-grid"></i> '
         . $this->h($texts->get_fd_message('continue_shopping')) . '</a>';
      $body .= '</div>';
      $body .= '</div>';
      $body .= '</div>';
      return $body;
   }

   private function cartRowsAndSum(bool $editable = false): array {
      $rows = '';
      $sum = 0.0;
      foreach ($this->cartItems() as $sku => $qty) {
         $product = $this->repo()->productBySku((string)$sku);
         if (!$product) {
            continue;
         }
         $qty = max(1, (int)$qty);
         $price = (float)($product['price_gross'] ?? 0);
         $shipping = (float)($product['effective_shipping_gross'] ?? 0);
         $line = ($price + $shipping) * $qty;
         $sum += $line;
         $qtyHtml = $editable
            ? '<input class="form-control form-control-sm dbx-shop-cart-qty" type="number" min="1" max="999" step="1" name="qty[' . $this->h($sku) . ']" value="' . (int)$qty . '">'
            : (string)(int)$qty;
         $rows .= '<tr>';
         $rows .= '<td><strong>' . $this->h($product['title'] ?? '') . '</strong><br><small>' . $this->h($sku) . '</small></td>';
         $rows .= '<td class="text-end">' . $qtyHtml . '</td>';
         $rows .= '<td class="text-end">' . $this->money($price) . '</td>';
         $rows .= '<td class="text-end">' . $this->money($shipping) . '</td>';
         $rows .= '<td class="text-end">' . $this->money($line) . '</td>';
         $rows .= '</tr>';
      }
      return array($rows, $sum);
   }

   private function cartReportDataAndSum($texts = null): array {
      $texts = $texts ?: $this->texts('dbxShop|shop-cart');
      $rows = array();
      $sum = 0.0;
      foreach ($this->cartItems() as $sku => $qty) {
         $product = $this->repo()->productBySku((string)$sku);
         if (!$product) {
            continue;
         }
         $qty = max(1, (int)$qty);
         $price = (float)($product['price_gross'] ?? 0);
         $shipping = (float)($product['effective_shipping_gross'] ?? 0);
         $line = ($price + $shipping) * $qty;
         $sum += $line;
         $skuText = (string)$sku;
         $rows[] = array(
            'id' => $skuText,
            'remove' => '<button class="btn btn-sm btn-outline-danger dbxConfirm dbx-shop-cart-remove" type="submit" name="remove" value="' . $this->h($skuText)
               . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($texts->get_fd_message('remove_title'))
               . '" data-confirm="' . $this->h($texts->get_fd_message('remove_question'))
               . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('remove_hint'))
               . '</small>" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('remove_title'))
               . '"><i class="bi bi-trash"></i></button>',
            'article' => '<strong>' . $this->h($product['title'] ?? '') . '</strong><br><small>' . $this->h($skuText) . '</small>',
            'qty' => '<input class="form-control form-control-sm dbx-shop-cart-qty" type="number" min="1" max="999" step="1" name="qty[' . $this->h($skuText) . ']" value="' . (int)$qty . '">',
            'price' => '<span class="dbx-shop-money">' . $this->money($price) . '</span>',
            'shipping' => '<span class="dbx-shop-money">' . $this->money($shipping) . '</span>',
            'line' => '<span class="dbx-shop-money"><strong>' . $this->money($line) . '</strong></span>',
         );
      }
      return array($rows, $sum);
   }

   /**
    * Baut den Warenkorb als zustandsbehaftetes dbxReport-Formular.
    *
    * Bei einem POST wird dieses Objekt vor der Mutation erzeugt. Dadurch
    * prüft dbxReport genau den Security-Wert, den der Warenkorb gerendert hat.
    */
   private function cartReport(array $rows, float $sum): \dbxReport {
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-cart-report', 'dbxShop|shop-cart-report');
      $report->_fd = 'dbxShop|shop-cart';
      $report->load_fd_messages();
      $report->set_editor_class_file(__FILE__);
      $report->_action = '?dbx_modul=dbxShop&dbx_run1=cart';
      $report->_mode = 'table';
      $report->_pages = false;
      $report->_rdata = $rows;
      $report->_rcount = count($rows);
      $report->_count_all = count($rows);
      $report->_rrows = max(1, count($rows));
      $report->_rpos = 0;
      $report->_create_row_select = false;
      $report->_create_row_edit = false;
      $report->_create_row_delete = false;
      $report->_rflds = array(
         'remove' => $report->get_fd_message('column_action'),
         'article' => $report->get_fd_message('column_article'),
         'qty' => $report->get_fd_message('column_quantity'),
         'price' => $report->get_fd_message('column_price'),
         'shipping' => $report->get_fd_message('column_shipping'),
         'line' => $report->get_fd_message('column_total'),
      );
      $report->_rpt_format = array(
         'remove' => 'html',
         'article' => 'html',
         'qty' => 'html',
         'price' => 'html',
         'shipping' => 'html',
         'line' => 'html',
      );
      $report->add_rep('bar_title', $report->get_fd_message('cart_title'));
      $report->add_rep('cart_sum', $this->money($sum));
      $report->add_rep('cart_count', (string)$this->cartQuantityTotal());
      return $report;
   }

   private function cartReportHtml(array $rows, float $sum, ?\dbxReport $report = null): string {
      if (!$report) {
         $report = $this->cartReport($rows, $sum);
      } else {
         // Nach einer gültigen Aktion nur Ergebnisdaten erneuern. Ein zweites
         // init() würde den geprüften Submit-Zustand und den rotierten Token
         // verwerfen.
         $report->_rdata = $rows;
         $report->_rcount = count($rows);
         $report->_count_all = count($rows);
         $report->_rrows = max(1, count($rows));
         $report->add_rep('cart_sum', $this->money($sum));
         $report->add_rep('cart_count', (string)$this->cartQuantityTotal());
      }
      return $report->run();
   }

   private function cartBodyHtml(?\dbxReport $report = null, $texts = null): string {
      $texts = $texts ?: $this->texts('dbxShop|shop-cart');
      [$reportRows, $sum] = $this->cartReportDataAndSum($texts);

      if ($reportRows === array()) {
         return '<div class="dbx-shop-cart-empty" data-dbx-shop-cart-count="0">'
            . $this->placeholder(
               $texts->get_fd_message('empty_title'),
               $texts->get_fd_message('empty_message')
            )
            . '</div>';
      }

      return $this->cartReportHtml($reportRows, $sum, $report);
   }

   private function absoluteShopUrl(string $run, array $params = array()): string {
      $query = array_merge(array(
         'dbx_modul' => 'dbxShop',
         'dbx_run1' => $run,
      ), $params);
      return dbx()->get_base_url() . '?' . http_build_query($query, '', '&');
   }

   private function checkoutPaymentOptions(): array {
      $cfg = $this->shopConfig();
      $texts = $this->texts('dbxShop|checkout');
      $options = array();
      if ($this->settingsBool($cfg, 'payment_bank_transfer_enabled', true)) {
         $options['bank_transfer'] = $texts->get_fd_message('payment_bank_transfer');
      }
      if ($this->settingsBool($cfg, 'payment_invoice_enabled', false)) {
         $options['invoice'] = $texts->get_fd_message('payment_invoice');
      }
      if ($this->settingsBool($cfg, 'payment_paypal_enabled', false) && $this->paypal()->isConfigured()) {
         $options['paypal'] = 'PayPal';
      }
      if ($this->settingsBool($cfg, 'payment_amazon_pay_enabled', false) && $this->amazonPay()->isConfigured()) {
         $options['amazon_pay'] = 'Amazon Pay';
      }
      return $options;
   }

   private function paymentMethodLabels(): array {
      $texts = $this->texts('dbxShop|checkout');
      return array(
         'bank_transfer' => $texts->get_fd_message('payment_bank_transfer'),
         'invoice' => $texts->get_fd_message('payment_invoice'),
         'paypal' => 'PayPal',
         'amazon_pay' => 'Amazon Pay',
      );
   }

   private function paymentProviderLabel(string $provider): string {
      $labels = $this->paymentMethodLabels();
      $texts = $this->texts('dbxShop|shop-orders');
      $channelLabels = array(
         'shop' => $texts->get_fd_message('provider_shop'),
         'amazon' => $texts->get_fd_message('provider_amazon'),
         'ebay' => $texts->get_fd_message('provider_ebay'),
         'kleinanzeigen' => $texts->get_fd_message('provider_kleinanzeigen'),
         'mobile' => $texts->get_fd_message('provider_mobile'),
      );
      return $labels[$provider] ?? $channelLabels[$provider] ?? $provider;
   }

   private function paymentInstructions(string $method, array $order = array()): string {
      $cfg = $this->shopConfig();
      $texts = $this->texts('dbxShop|checkout');
      if ($method === 'bank_transfer') {
         $lines = array();
         $intro = trim((string)($cfg['payment_bank_transfer_instructions'] ?? ''));
         if ($intro === '') {
            $intro = $texts->get_fd_message('bank_transfer_default');
         }
         $lines[] = $intro;
         foreach (array(
            $texts->get_fd_message('account_owner') => 'payment_bank_transfer_account_owner',
            'IBAN' => 'payment_bank_transfer_iban',
            'BIC' => 'payment_bank_transfer_bic',
            'Bank' => 'payment_bank_transfer_bank_name',
         ) as $label => $key) {
            $value = trim((string)($cfg[$key] ?? ''));
            if ($value !== '') {
               $lines[] = $label . ': ' . $value;
            }
         }
         if (trim((string)($order['order_no'] ?? '')) !== '') {
            $lines[] = $texts->get_fd_message('purpose') . ': ' . (string)$order['order_no'];
         }
         return implode("\n", $lines);
      }
      if ($method === 'invoice') {
         $text = trim((string)($cfg['payment_invoice_instructions'] ?? ''));
         return $text !== '' ? $text : $texts->get_fd_message('invoice_default');
      }
      if ($method === 'amazon_pay') {
         return $texts->get_fd_message('amazon_default');
      }
      if ($method === 'paypal') {
         return $texts->get_fd_message('paypal_default');
      }
      return '';
   }

   private function checkoutPaymentHelp(array $options): string {
      $texts = $this->texts('dbxShop|checkout');
      if ($options === array()) {
         return '<div class="alert alert-warning mb-0">' . $this->h($texts->get_fd_message('payment_none_help')) . '</div>';
      }
      $parts = array();
      if (isset($options['bank_transfer'])) {
         $parts[] = '<div><strong>' . $this->h($options['bank_transfer']) . '</strong><span>'
            . $this->h($texts->get_fd_message('payment_bank_transfer_help')) . '</span></div>';
      }
      if (isset($options['invoice'])) {
         $parts[] = '<div><strong>' . $this->h($options['invoice']) . '</strong><span>'
            . $this->h($texts->get_fd_message('payment_invoice_help')) . '</span></div>';
      }
      if (isset($options['paypal'])) {
         $parts[] = '<div><strong>PayPal</strong><span>'
            . $this->h($texts->get_fd_message('payment_paypal_help')) . '</span></div>';
      }
      if (isset($options['amazon_pay'])) {
         $parts[] = '<div><strong>Amazon Pay</strong><span>'
            . $this->h($texts->get_fd_message('payment_amazon_help')) . '</span></div>';
      }
      return '<div class="dbx-shop-payment-method-help">' . implode('', $parts) . '</div>';
   }

   private function checkoutTableHtml(string $rows, float $sum): string {
      $texts = $this->texts('dbxShop|checkout');
      return '<div class="dbx-shop-cart table-responsive">'
         . '<table class="table table-sm align-middle">'
         . '<thead><tr><th>' . $this->h($texts->get_fd_message('column_product')) . '</th>'
         . '<th class="text-end">' . $this->h($texts->get_fd_message('column_quantity')) . '</th>'
         . '<th class="text-end">' . $this->h($texts->get_fd_message('column_price')) . '</th>'
         . '<th class="text-end">' . $this->h($texts->get_fd_message('column_shipping')) . '</th>'
         . '<th class="text-end">' . $this->h($texts->get_fd_message('column_total')) . '</th></tr></thead>'
         . '<tbody>' . $rows . '</tbody>'
         . '<tfoot><tr><th colspan="4" class="text-end">' . $this->h($texts->get_fd_message('amount_due'))
         . '</th><th class="text-end">' . $this->money($sum) . '</th></tr></tfoot>'
         . '</table></div>';
   }

   private function legalSnapshotsForOrder(): array {
      $cfg = $this->shopConfig();
      if (!$this->settingsBool($cfg, 'legal_snapshot_enabled', true)) {
         return array('', '');
      }
      $db = $this->contentDb();
      if (!is_object($db)) {
         return array('', '');
      }
      $pages = $this->ensureShopLegalPages();
      $dd = \dbx\dbxContent\dbxContentLng::ddContent();
      $snapshot = function(string $key) use ($db, $pages, $dd): string {
         $cid = (int)($pages[$key] ?? 0);
         if ($cid <= 0) {
            return '';
         }
         $row = $db->select1($dd, $cid, 'title,permalink,content,update_date', 0);
         if (!is_array($row)) {
            return '';
         }
         return json_encode(array(
            'captured_at' => date('Y-m-d H:i:s'),
            'title' => (string)($row['title'] ?? ''),
            'permalink' => (string)($row['permalink'] ?? ''),
            'update_date' => (string)($row['update_date'] ?? ''),
            'content' => (string)($row['content'] ?? ''),
         ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
      };
      return array($snapshot('legal'), $snapshot('withdrawal'));
   }

   private function orderSuccessPage(array $order, string $paymentMethod): string {
      $this->startSession();
      $_SESSION['dbxShop_last_order_no'] = (string)($order['order_no'] ?? '');
      $texts = $this->texts('dbxShop|checkout');
      $methodLabels = $this->paymentMethodLabels();
      $instructions = trim($this->paymentInstructions($paymentMethod, $order));
      $body = '<section class="dbx-shop-order-success">'
         . '<div class="dbx-shop-order-success-icon"><i class="bi bi-check2-circle"></i></div>'
         . '<h2>' . $this->h($texts->get_fd_message('order_saved_title')) . '</h2>'
         . '<p>' . $this->h($texts->get_fd_message('order_number_text')) . ' <strong>' . $this->h($order['order_no'] ?? '') . '</strong>.</p>'
         . '<dl>'
         . '<dt>' . $this->h($texts->get_fd_message('payment_method_label')) . '</dt><dd>' . $this->h($methodLabels[$paymentMethod] ?? $paymentMethod) . '</dd>'
         . '<dt>' . $this->h($texts->get_fd_message('status_label')) . '</dt><dd>' . $this->h($texts->get_fd_message('order_waiting')) . '</dd>'
         . '<dt>' . $this->h($texts->get_fd_message('total_label')) . '</dt><dd>' . $this->money($order['total_gross'] ?? 0) . '</dd>'
         . '</dl>'
         . ($instructions !== '' ? '<div class="alert alert-info text-start"><strong>' . $this->h($texts->get_fd_message('payment_note')) . '</strong><br>' . nl2br($this->h($instructions)) . '</div>' : '')
         . '<div class="dbx-shop-order-success-actions">'
         . '<a class="btn btn-primary" href="?dbx_modul=dbxShop&amp;dbx_run1=orders"><i class="bi bi-receipt"></i> ' . $this->h($texts->get_fd_message('view_orders')) . '</a>'
         . '<a class="btn btn-outline-secondary" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog"><i class="bi bi-grid"></i> ' . $this->h($texts->get_fd_message('continue_shopping')) . '</a>'
         . '</div>'
         . '</section>';
      return $this->page(
         $texts->get_fd_message('thanks_title'),
         $texts->get_fd_message('saved_snapshot_subtitle'),
         $body,
         'orders'
      );
   }

   private function shopMailFrom(array $cfg): array {
      $from = trim((string)($cfg['mail_from'] ?? ''));
      $fromName = trim((string)($cfg['mail_from_name'] ?? 'dbxShop'));
      return array('email' => $from, 'name' => $fromName);
   }

   private function shopMailOptions(array $cfg, array $extra = array()): array {
      $profile = trim((string)($cfg['mail_profile'] ?? ''));
      if ($profile !== '') {
         $extra['mail_profile'] = $profile;
      }
      return $extra;
   }

   private function orderMailHtml(array $order, string $title): string {
      $items = '';
      foreach ((array)($order['items'] ?? array()) as $item) {
         $items .= '<li>' . (int)($item['qty'] ?? 0) . 'x ' . $this->h($item['title'] ?? '') . ' - ' . $this->money($item['total_gross'] ?? 0) . '</li>';
      }
      $provider = (string)($order['payment_provider'] ?? '');
      $instructions = trim($this->paymentInstructions($provider, $order));
      return '<h1>' . $this->h($title) . '</h1>'
         . '<p>Bestellnummer: <strong>' . $this->h($order['order_no'] ?? '') . '</strong></p>'
         . '<ul>' . $items . '</ul>'
         . '<p>Summe: <strong>' . $this->money($order['total_gross'] ?? 0) . '</strong></p>'
         . '<p>Status: ' . $this->h($order['status'] ?? '') . ', Zahlung: ' . $this->h($this->paymentProviderLabel($provider)) . ' / ' . $this->h($order['payment_status'] ?? '') . '</p>'
         . ($instructions !== '' ? '<p><strong>Zahlungshinweis</strong><br>' . nl2br($this->h($instructions)) . '</p>' : '');
   }

   private function sendOrderMails(array $order): bool {
      $orderId = (int)($order['id'] ?? 0);
      if ($orderId <= 0 || $this->repo()->hasOrderHistoryEvent($orderId, 'notification', 'order_mail')) {
         return false;
      }

      $cfg = $this->shopConfig();
      $from = $this->shopMailFrom($cfg);
      if (filter_var((string)($from['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
         dbx()->sys_msg(
            'error',
            'dbxShop',
            (string)$orderId,
            'order mail configuration invalid',
            'Der konfigurierte Shop-Absender ist keine gültige E-Mail-Adresse.'
         );
         return false;
      }
      $mailOptions = $this->shopMailOptions($cfg);
      $subject = 'Bestellung ' . (string)($order['order_no'] ?? '');
      $sent = 0;
      try {
         if ($this->settingsBool($cfg, 'mail_customer_enabled', false)) {
            $to = trim((string)($order['customer_email'] ?? ''));
            if ($to !== '') {
               if (dbx()->send_mail($from, $to, $subject, $this->orderMailHtml($order, 'Ihre Bestellung'), 'html', array(), $mailOptions)) {
                  $sent++;
               }
            }
         }
         if ($this->settingsBool($cfg, 'mail_admin_enabled', false)) {
            $to = trim((string)($cfg['mail_admin_to'] ?? ''));
            if ($to !== '') {
               if (dbx()->send_mail($from, $to, '[Shop] ' . $subject, $this->orderMailHtml($order, 'Neue Shop-Bestellung'), 'html', array(), $mailOptions)) {
                  $sent++;
               }
            }
         }
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxShop', (string)($order['id'] ?? ''), 'order mail failed', $e->getMessage());
      }

      if ($sent > 0) {
         $this->repo()->addOrderHistory($orderId, 'notification', '', 'order_mail', $sent . ' Bestellmail(s) wurden versendet.');
         return true;
      }
      return false;
   }

   private function sendWithdrawalMails(array $withdrawal): void {
      $cfg = $this->shopConfig();
      $from = $this->shopMailFrom($cfg);
      if (filter_var((string)($from['email'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
         dbx()->sys_msg(
            'error',
            'dbxShop',
            (string)($withdrawal['id'] ?? ''),
            'withdrawal mail configuration invalid',
            'Der konfigurierte Shop-Absender ist keine gültige E-Mail-Adresse.'
         );
         return;
      }
      $mailOptions = $this->shopMailOptions($cfg);
      $subject = 'Widerruf ' . (string)($withdrawal['order_no'] ?? '');
      $html = '<h1>Widerruf</h1>'
         . '<p>Bestellnummer: <strong>' . $this->h($withdrawal['order_no'] ?? '') . '</strong></p>'
         . '<p>Name: ' . $this->h($withdrawal['customer_name'] ?? '') . '<br>E-Mail: ' . $this->h($withdrawal['customer_email'] ?? '') . '</p>'
         . '<p>' . nl2br($this->h($withdrawal['reason'] ?? '')) . '</p>';
      try {
         if ($this->settingsBool($cfg, 'mail_customer_enabled', false)) {
            $to = trim((string)($withdrawal['customer_email'] ?? ''));
            if ($to !== '') {
               dbx()->send_mail($from, $to, $subject, $html, 'html', array(), $mailOptions);
            }
         }
         if ($this->settingsBool($cfg, 'mail_admin_enabled', false)) {
            $to = trim((string)($cfg['mail_admin_to'] ?? ''));
            if ($to !== '') {
               dbx()->send_mail($from, $to, '[Shop] ' . $subject, $html, 'html', array(), $mailOptions);
            }
         }
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxShop', (string)($withdrawal['id'] ?? ''), 'withdrawal mail failed', $e->getMessage());
      }
   }

   private function publicOrderCard(array $order): string {
      $texts = $this->texts('dbxShop|shop-orders');
      $items = '';
      foreach ((array)($order['items'] ?? array()) as $item) {
         $items .= '<li><span>' . (int)($item['qty'] ?? 0) . 'x ' . $this->h($item['title'] ?? '') . '</span><strong>' . $this->money($item['total_gross'] ?? 0) . '</strong></li>';
      }
      if ($items === '') {
         $items = '<li><span>' . $this->h($texts->get_fd_message('no_items')) . '</span><strong></strong></li>';
      }
      $statusLabels = array(
         'new' => $texts->get_fd_message('status_new'),
         'payment_pending' => $texts->get_fd_message('status_payment_pending'),
         'paid' => $texts->get_fd_message('status_paid'),
         'processing' => $texts->get_fd_message('status_processing'),
         'shipped' => $texts->get_fd_message('status_shipped'),
         'done' => $texts->get_fd_message('status_done'),
         'cancelled' => $texts->get_fd_message('status_cancelled'),
      );
      $shippingLabels = array(
         'open' => $texts->get_fd_message('shipping_open'),
         'ready' => $texts->get_fd_message('shipping_ready'),
         'shipped' => $texts->get_fd_message('shipping_shipped'),
         'delivered' => $texts->get_fd_message('shipping_delivered'),
         'returned' => $texts->get_fd_message('shipping_returned'),
      );
      $withdrawalLabels = array(
         'new' => $texts->get_fd_message('withdrawal_new'),
         'processing' => $texts->get_fd_message('withdrawal_processing'),
         'accepted' => $texts->get_fd_message('withdrawal_accepted'),
         'rejected' => $texts->get_fd_message('withdrawal_rejected'),
         'refunded' => $texts->get_fd_message('withdrawal_refunded'),
         'closed' => $texts->get_fd_message('withdrawal_closed'),
      );
      $historyLabels = array(
         'created' => $texts->get_fd_message('history_created'),
         'status' => $texts->get_fd_message('history_status'),
         'payment_status' => $texts->get_fd_message('history_payment_status'),
         'shipping_status' => $texts->get_fd_message('history_shipping_status'),
         'invoice_no' => $texts->get_fd_message('history_invoice_no'),
         'tracking_no' => $texts->get_fd_message('history_tracking_no'),
         'payment' => $texts->get_fd_message('history_payment'),
         'customer_mail' => $texts->get_fd_message('history_customer_mail'),
         'withdrawal' => $texts->get_fd_message('history_withdrawal'),
         'withdrawal_status' => $texts->get_fd_message('history_withdrawal_status'),
         'stock_release' => $texts->get_fd_message('history_stock_release'),
         'invoice_pdf' => $texts->get_fd_message('history_invoice_pdf'),
      );
      $paymentStatus = (string)($order['payment_status'] ?? '');
      $paymentStatusLabel = $texts->get_fd_message('payment_status_' . $paymentStatus, $paymentStatus);
      $invoice = trim((string)($order['invoice_no'] ?? ''));
      $trackingNo = trim((string)($order['tracking_no'] ?? ''));
      $trackingUrl = trim((string)($order['tracking_url'] ?? ''));
      $channel = (string)($order['channel_key'] ?? 'shop');
      $extra = '';
      $extra .= '<span>' . $this->h($texts->get_fd_message('origin')) . ': ' . $this->h($this->paymentProviderLabel($channel)) . '</span>';
      if ($invoice !== '') {
         $extra .= '<span>' . $this->h($texts->get_fd_message('invoice')) . ': ' . $this->h($invoice) . '</span>';
      }
      $extra .= '<span>' . $this->h($texts->get_fd_message('shipping')) . ': '
         . $this->h($shippingLabels[(string)($order['shipping_status'] ?? 'open')] ?? (string)($order['shipping_status'] ?? 'open')) . '</span>';
      if ($trackingNo !== '') {
         $trackingText = $this->h($texts->get_fd_message('tracking')) . ': ' . $this->h($trackingNo);
         $extra .= $trackingUrl !== ''
            ? '<span><a href="' . $this->h($trackingUrl) . '" target="_blank" rel="noopener">' . $trackingText . '</a></span>'
            : '<span>' . $trackingText . '</span>';
      }
      $withdrawalsHtml = '';
      foreach ((array)($order['withdrawals'] ?? array()) as $withdrawal) {
         $status = (string)($withdrawal['status'] ?? 'new');
         $created = trim((string)($withdrawal['create_date'] ?? ''));
         $withdrawalsHtml .= '<li><span><strong>' . $this->h($withdrawalLabels[$status] ?? $status) . '</strong>' . ($created !== '' ? '<small>' . $this->h($created) . '</small>' : '') . '</span></li>';
      }
      if ($withdrawalsHtml !== '') {
         $withdrawalsHtml = '<section class="dbx-shop-public-order-withdrawals"><h4><i class="bi bi-arrow-counterclockwise"></i> '
            . $this->h($texts->get_fd_message('withdrawals')) . '</h4><ul>' . $withdrawalsHtml . '</ul></section>';
      }
      $historyHtml = '';
      $historyCount = 0;
      foreach ((array)($order['history'] ?? array()) as $history) {
         if ($historyCount >= 6) {
            break;
         }
         $type = (string)($history['event_type'] ?? '');
         $created = trim((string)($history['create_date'] ?? ''));
         $message = trim((string)($history['message'] ?? ''));
         $old = trim((string)($history['old_value'] ?? ''));
         $new = trim((string)($history['new_value'] ?? ''));
         $detail = $message !== '' ? $message : trim($old . ($old !== '' && $new !== '' ? ' -> ' : '') . $new);
         $historyHtml .= '<li><span><strong>' . $this->h($historyLabels[$type] ?? $type) . '</strong>' . ($detail !== '' ? '<small>' . $this->h($detail) . '</small>' : '') . '</span>' . ($created !== '' ? '<time>' . $this->h($created) . '</time>' : '') . '</li>';
         $historyCount++;
      }
      if ($historyHtml !== '') {
         $historyHtml = '<section class="dbx-shop-public-order-history"><h4><i class="bi bi-clock-history"></i> '
            . $this->h($texts->get_fd_message('history')) . '</h4><ol>' . $historyHtml . '</ol></section>';
      }
      $instructions = trim($this->paymentInstructions((string)($order['payment_provider'] ?? ''), $order));
      $invoiceLink = '';
      $canInvoice = $invoice !== '' || in_array((string)($order['status'] ?? ''), array('paid', 'processing', 'shipped', 'done'), true);
      if ($canInvoice) {
         $invoiceLink = '<a class="btn btn-outline-primary btn-sm" href="?dbx_modul=dbxShop&amp;dbx_run1=invoice_pdf&amp;order_no=' . rawurlencode((string)($order['order_no'] ?? '')) . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> '
            . $this->h($texts->get_fd_message('invoice')) . '</a>';
      }
      return '<article class="dbx-shop-public-order">'
         . '<header><div><strong>' . $this->h($order['order_no'] ?? '') . '</strong><small>' . $this->h($order['create_date'] ?? '') . '</small></div><span class="badge text-bg-primary">' . $this->h($statusLabels[(string)($order['status'] ?? '')] ?? ($order['status'] ?? '')) . '</span></header>'
         . '<ul>' . $items . '</ul>'
         . '<footer><span>' . $this->h($texts->get_fd_message('payment')) . ': '
         . $this->h($this->paymentProviderLabel((string)($order['payment_provider'] ?? ''))) . ' / '
         . $this->h($paymentStatusLabel) . '</span><strong>' . $this->money($order['total_gross'] ?? 0) . '</strong></footer>'
         . ($extra !== '' ? '<div class="dbx-shop-public-order-extra">' . $extra . '</div>' : '')
         . ($instructions !== '' && in_array((string)($order['payment_status'] ?? ''), array('open', 'created', 'pending'), true)
            ? '<div class="alert alert-info py-2 my-2"><strong>' . $this->h($texts->get_fd_message('payment_note')) . '</strong><br>' . nl2br($this->h($instructions)) . '</div>'
            : '')
         . $withdrawalsHtml
         . $historyHtml
         . '<div class="dbx-shop-public-order-actions">' . $invoiceLink
         . '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxShop&amp;dbx_run1=withdrawal"><i class="bi bi-arrow-counterclockwise"></i> '
         . $this->h($texts->get_fd_message('withdrawal')) . '</a></div>'
         . '</article>';
   }

   private function startPayPalForOrder(array $order): string {
      $returnUrl = $this->absoluteShopUrl('paypal_return', array('order_no' => (string)$order['order_no']));
      $cancelUrl = $this->absoluteShopUrl('paypal_cancel', array('order_no' => (string)$order['order_no']));
      $paypalOrder = $this->readJsonArray($order['payment_payload'] ?? '');
      $paypalId = (string)($order['payment_reference'] ?? '');
      $approvalUrl = $paypalId !== '' ? $this->paypal()->approvalUrl($paypalOrder) : '';
      if ($paypalId === '' || $approvalUrl === '') {
         $paypalOrder = $this->paypal()->createOrder($order, $returnUrl, $cancelUrl);
      }
      $paypalId = (string)($paypalOrder['id'] ?? '');
      if ($paypalId === ''
         || !$this->repo()->updateOrderPayment((int)$order['id'], 'paypal', 'created', $paypalId, $paypalOrder)) {
         throw new \RuntimeException('PayPal-Zahlungsreferenz konnte nicht sicher gespeichert werden.');
      }
      $approvalUrl = $this->paypal()->approvalUrl($paypalOrder);
      if ($approvalUrl === '') {
         throw new \RuntimeException('PayPal hat keinen Freigabe-Link geliefert.');
      }
      if (!headers_sent()) {
         header('Location: ' . $approvalUrl, true, 302);
         exit;
      }
      return '<a class="btn btn-primary" href="' . $this->h($approvalUrl) . '">Weiter zu PayPal</a>';
   }

   private function startAmazonPayForOrder(array $order): string {
      $returnUrl = $this->absoluteShopUrl('amazon_pay_return', array('order_no' => (string)$order['order_no']));
      $cancelUrl = $this->absoluteShopUrl('amazon_pay_cancel', array('order_no' => (string)$order['order_no']));
      $checkoutSession = $this->readJsonArray($order['payment_payload'] ?? '');
      $checkoutSessionId = (string)($order['payment_reference'] ?? '');
      $redirectUrl = $checkoutSessionId !== '' ? $this->amazonPay()->redirectUrl($checkoutSession) : '';
      if ($checkoutSessionId === '' || $redirectUrl === '') {
         $checkoutSession = $this->amazonPay()->createCheckoutSession($order, $returnUrl, $cancelUrl);
      }
      $checkoutSessionId = (string)($checkoutSession['checkoutSessionId'] ?? $checkoutSession['id'] ?? '');
      if ($checkoutSessionId === ''
         || !$this->repo()->updateOrderPayment((int)$order['id'], 'amazon_pay', 'created', $checkoutSessionId, $checkoutSession)) {
         throw new \RuntimeException('Amazon-Pay-Referenz konnte nicht sicher gespeichert werden.');
      }
      $redirectUrl = $this->amazonPay()->redirectUrl($checkoutSession);
      if ($redirectUrl === '') {
         throw new \RuntimeException('Amazon Pay hat keinen Redirect-Link geliefert.');
      }
      if (!headers_sent()) {
         header('Location: ' . $redirectUrl, true, 302);
         exit;
      }
      return '<a class="btn btn-primary" href="' . $this->h($redirectUrl) . '">Weiter zu Amazon Pay</a>';
   }

   private function continueCheckoutOrder(array $order, string $paymentMethod): string {
      $storedMethod = trim((string)($order['payment_provider'] ?? ''));
      if ($storedMethod !== '') $paymentMethod = $storedMethod;
      if ($paymentMethod === 'paypal') {
         return $this->startPayPalForOrder($order);
      }
      if ($paymentMethod === 'amazon_pay') {
         return $this->startAmazonPayForOrder($order);
      }

      $this->sendOrderMails($order);
      $this->startSession();
      $_SESSION['dbxShop_cart'] = array();
      return $this->orderSuccessPage($order, $paymentMethod);
   }

   public function catalog(): string {
      $this->ensureSeed();
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $channel = $this->activeChannel();
      $query = trim((string)($_GET['q'] ?? ''));
      $groupId = $this->catalogGroupId();
      $attributeFilters = $this->selectedAttributeFilters();
      $matches = array();
      $hasQuery = $this->searchTerms($query) !== array();
      $currentGroup = $groupId > 0 ? $this->repo()->groupById($groupId) : null;
      if ($groupId > 0 && !is_array($currentGroup)) {
         $groupId = 0;
      }

      foreach ($this->repo()->catalogCandidates($channel) as $product) {
         if (!$this->productHasChannel($product, $channel)) {
            continue;
         }
         if (!$this->productInCatalogGroup($product, $groupId)) {
            continue;
         }
         $score = $this->productSearchScore($product, $query);
         if ($score <= 0 || !$this->productMatchesAttributeFilters($product, $attributeFilters)) {
            continue;
         }

         $sku = (string)($product['sku'] ?? '');
         if ($sku === '') {
            continue;
         }
         $matches[] = array(
            'product' => $product,
            'score' => $score,
            'sorter' => (int)($product['sorter'] ?? 100),
            'title' => (string)($product['title'] ?? ''),
         );
      }

      if ($hasQuery && count($matches) > 1) {
         usort($matches, static function(array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
               return $b['score'] <=> $a['score'];
            }
            if ($a['sorter'] !== $b['sorter']) {
               return $a['sorter'] <=> $b['sorter'];
            }
            return strcasecmp($a['title'], $b['title']);
         });
      }

      $products = array_map(static fn($match) => $match['product'], $matches);
      $reportHtml = $products === array()
         ? $this->placeholder(
            $texts->get_fd_message('no_products_title'),
            $texts->get_fd_message('no_products_message')
         )
         : $this->catalogReportHtml($products, $channel, $query, $attributeFilters, $groupId);

      $isPaginationAjax = (int)dbx()->get_system_var('dbx_ajax', 0, 'int') === 1
         && (array_key_exists('dbx_rpos', $_GET) || array_key_exists('dbx_rrows', $_GET));
      if ($isPaginationAjax) {
         return $reportHtml;
      }

      $navigation = $this->catalogGroupBreadcrumb($groupId) . $this->catalogGroupNavigation($groupId);
      if ($navigation === '') {
         $navigation = $this->catalogGroupNavigation(0);
      }
      $title = is_array($currentGroup) ? (string)($currentGroup['title'] ?? 'Shop') : 'Shop';
      $subtitle = $texts->get_fd_message(
         is_array($currentGroup)
            ? 'catalog_group_subtitle'
            : 'catalog_subtitle'
      );

      return $this->page(
         $title,
         $subtitle,
         $this->demoShopNoticeHtml(
            'dbx-shop-demo-catalog-notice',
            'dbx-shop-demo-alert-catalog',
            $texts
         )
            . $navigation
            . $this->catalogFiltersHtml($channel, $query, $attributeFilters, $groupId)
            . $reportHtml,
         'catalog'
      );
   }

   public function product(): string {
      $this->ensureSeed();
      $texts = $this->texts('dbxShop|shop-catalog-filter-form');
      $channel = $this->activeChannel();
      $sku = dbx()->get_modul_var('sku', '', 'parameter');
      $product = $this->repo()->productBySku((string) $sku);

      if (!$product || !$this->productHasChannel($product, $channel)) {
         return $this->page(
            $texts->get_fd_message('product_page_title'),
            $texts->get_fd_message('product_not_found_subtitle'),
            $this->placeholder(
               $texts->get_fd_message('product_not_found_title'),
               $texts->get_fd_message('product_not_found_message')
            ),
            'catalog'
         );
      }

      $body = $this->renderProductDetail($product, $channel);

      return $this->page(
         $product['title'] ?? $texts->get_fd_message('product_page_title'),
         $product['summary'] ?? $texts->get_fd_message('product_fallback'),
         $body,
         'catalog'
      );
   }

   public function cart(): string {
      $this->ensureSeed();
      $texts = $this->texts('dbxShop|shop-cart');
      $channel = $this->activeChannel();
      $ajax = (int)dbx()->get_system_var('dbx_ajax', 0, 'int') === 1;
      $addSku = (string)dbx()->get_modul_var('sku', '', 'parameter');
      $cartReport = null;
      $hasCartPost = isset($_POST['shop_cart_update']) || isset($_POST['remove']) || isset($_POST['clear']);

      if ($hasCartPost) {
         [$currentRows, $currentSum] = $this->cartReportDataAndSum($texts);
         if ($currentRows !== array()) {
            $cartReport = $this->cartReport($currentRows, $currentSum);
            if ($cartReport->submit() && !$cartReport->errors()) {
               $removeSku = (string)$cartReport->get_post('remove', '', 'parameter');
               $clear = (int)$cartReport->get_post('clear', 0, 'int') === 1;
               if ($clear) {
                  $this->startSession();
                  $_SESSION['dbxShop_cart'] = array();
               } elseif ($removeSku !== '') {
                  $this->removeFromCart($removeSku);
               } elseif (isset($_POST['shop_cart_update']) && is_array($_POST['qty'] ?? null)) {
                  // qty ist ein dynamisches Report-Feld. Der rohe Arraywert
                  // wird erst nach erfolgreicher Report-Tokenprüfung gelesen;
                  // updateCartQuantities begrenzt jeden Wert auf 1..999.
                  $this->updateCartQuantities($_POST['qty']);
               }
            }
         }
      } elseif ($addSku !== '') {
         $product = $this->repo()->productBySku($addSku);
         $buyForm = is_array($product) ? $this->buyForm($product) : null;
         if ($buyForm && $buyForm->submit() && !$buyForm->errors()) {
            $qty = $this->requestedQuantity($buyForm->get_post('qty', 1, 'int|min=1'));
            $this->addToCart($addSku, $qty);
            return $this->page(
               $texts->get_fd_message('cart_title'),
               $texts->get_fd_message('added_subtitle'),
               $this->addedToCartDialog($product),
               'cart'
            );
         }
      }

      $body = $this->cartBodyHtml($cartReport, $texts);
      if ($ajax) {
         return $body;
      }

      return $this->page(
         $texts->get_fd_message('cart_title'),
         $texts->get_fd_message('cart_subtitle'),
         $body,
         'cart'
      );
   }

   public function checkout(): string {
      $this->ensureSeed();
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-checkout-form', 'shop-checkout-form');
      $form->_fd = 'dbxShop|checkout';
      $form->load_fd_messages();
      [$rows, $sum] = $this->cartRowsAndSum();
      if ($rows === '') {
         return $this->page(
            $form->get_fd_message('page_title'),
            $form->get_fd_message('empty_subtitle'),
            $this->placeholder(
               $form->get_fd_message('empty_title'),
               $form->get_fd_message('empty_message')
            ),
            'checkout'
         );
      }
      $cfg = $this->shopConfig();
      if (!$this->settingsBool($cfg, 'checkout_guest_allowed', true) && (int)dbx()->user() <= 0) {
         return $this->page(
            $form->get_fd_message('page_title'),
            $form->get_fd_message('login_subtitle'),
            '<div class="alert alert-warning m-3">'
               . $form->get_fd_message('login_message')
               . '</div>',
            'checkout'
         );
      }

      $paymentOptions = $this->checkoutPaymentOptions();
      $checkoutRequestId = $this->checkoutRequestId();
      $form->_action = '?dbx_modul=dbxShop&dbx_run1=checkout';
      $form->_data = array_merge($form->_data, array(
         'customer_name' => (string)($_POST['customer_name'] ?? ''),
         'customer_email' => (string)($_POST['customer_email'] ?? ''),
         'customer_phone' => (string)($_POST['customer_phone'] ?? ''),
         'shipping_address' => (string)($_POST['shipping_address'] ?? ''),
         'note' => (string)($_POST['note'] ?? ''),
         'checkout_request_id' => $checkoutRequestId,
         'payment_method' => (string)($_POST['payment_method'] ?? array_key_first($paymentOptions)),
         'accept_legal' => !empty($_POST['accept_legal']) ? 1 : 0,
         'accept_withdrawal' => !empty($_POST['accept_withdrawal']) ? 1 : 0,
      ));
      $form->_msg_info = $form->get_fd_message('form_info');
      $form->_msg_error = $form->get_fd_message('validation_error');
      $form->add_flds();
      $form->add_obj('checkout_cart', 'obj-value', $this->checkoutTableHtml($rows, $sum));
      $form->add_rep('payment_help', $this->checkoutPaymentHelp($paymentOptions));
      $form->add_rep(
         'demo_shop_notice',
         $this->demoShopNoticeHtml('dbx-shop-demo-notice', '', $form)
      );

      if ($form->submit()) {
         if ($form->errors()) {
            $form->_msg_error = $form->get_fd_message(
               'validation_error'
            );
         } else {
            $paymentMethod = (string)$form->get_post('payment_method', '', 'parameter');
            $customerName = trim((string)$form->get_post_data('customer_name', '', '*|min=2|max=180'));
            $customerEmail = trim((string)$form->get_post('customer_email', '', 'email|max=180'));
            $shippingAddress = trim((string)$form->get_post_data('shipping_address', '', '*|min=8|max=2000'));
            $customerPhone = trim((string)$form->get_post_data('customer_phone', '', '*|max=80'));
            $note = trim((string)$form->get_post_data('note', '', '*|max=2000'));
            $acceptLegal = (int)$form->get_post('accept_legal', 0, 'int') === 1;
            $acceptWithdrawal = (int)$form->get_post('accept_withdrawal', 0, 'int') === 1;

         $checkoutError = '';
         if ($paymentOptions === array()) {
            $checkoutError = $form->get_fd_message('no_payment');
            $form->add_fld_error('payment_method', $checkoutError);
         } elseif (!isset($paymentOptions[$paymentMethod])) {
            $checkoutError = $form->get_fd_message('select_payment');
            $form->add_fld_error('payment_method', $checkoutError);
         } elseif (!$acceptLegal || !$acceptWithdrawal) {
            $checkoutError = $form->get_fd_message('confirm_legal');
            if (!$acceptLegal) {
               $form->add_fld_error(
                  'accept_legal',
                  $form->get_fd_message('legal_field_error')
               );
            }
            if (!$acceptWithdrawal) {
               $form->add_fld_error(
                  'accept_withdrawal',
                  $form->get_fd_message('withdrawal_field_error')
               );
            }
         }

         if ($checkoutError !== '') {
            $form->_msg_error = $checkoutError;
         } else {
            try {
               $requestId = $checkoutRequestId;
               $existingOrder = $this->checkoutRequestOrder($requestId);
               if (is_array($existingOrder)) {
                  return $this->continueCheckoutOrder($existingOrder, $paymentMethod);
               }

               [$legalSnapshot, $withdrawalSnapshot] = $this->legalSnapshotsForOrder();
               $order = $this->repo()->createOrderFromItems(
                  $this->cartItems(),
                  $this->activeChannel(),
                  $customerName,
                  $customerEmail,
                  $note,
                  $paymentMethod,
                  in_array($paymentMethod, array('paypal', 'amazon_pay'), true) ? 'created' : 'open',
                  'payment_pending',
                  $customerPhone,
                  $shippingAddress,
                  $legalSnapshot,
                  $withdrawalSnapshot
               );
               if (!$order) {
                  $form->_msg_error = $form->get_fd_message(
                     'order_error'
                  );
               } else {
                  $this->rememberCheckoutRequest($requestId, $order);
                  return $this->continueCheckoutOrder($order, $paymentMethod);
               }
            } catch (\Throwable $e) {
               dbx()->sys_msg('error', 'dbxShop', 'checkout', 'checkout failed', $e->getMessage());
               $form->_msg_error = $form->get_fd_message(
                  'technical_error'
               );
            }
         }
         }
      }

      return $this->page(
         $form->get_fd_message('page_title'),
         $form->get_fd_message('page_subtitle'),
         $form->run(),
         'checkout'
      );
   }

   public function paypalStart(): string {
      $this->ensureSeed();
      return $this->checkout();
   }

   /**
    * Ordnet Provider-Ruecklaeufe ausschliesslich ueber die zuvor serverseitig
    * gespeicherte Zahlungsreferenz zu. order_no ist nur ein zusaetzlicher
    * Konsistenzcheck und niemals der Zahlungsnachweis.
    */
   private function providerReturnOrder(string $provider, string $reference, string $orderNo): ?array {
      if ($reference === '') return null;
      $order = $this->repo()->orderByPaymentReference($provider, $reference);
      if (!is_array($order)) return null;
      if ($orderNo !== '' && !hash_equals((string)($order['order_no'] ?? ''), $orderNo)) {
         return null;
      }
      return $order;
   }

   private function rememberProviderOrder(array $order, bool $clearCart = true): void {
      $this->startSession();
      if ($clearCart) $_SESSION['dbxShop_cart'] = array();
      $_SESSION['dbxShop_last_order_no'] = (string)($order['order_no'] ?? '');
   }

   public function paypalReturn(): string {
      $this->ensureSeed();
      $paypalOrderId = (string)dbx()->get_modul_var('token', '', 'parameter');
      $orderNo = (string)dbx()->get_modul_var('order_no', '', 'parameter');
      $order = $this->providerReturnOrder('paypal', $paypalOrderId, $orderNo);
      if (!$order || $paypalOrderId === '') {
         return $this->page('PayPal', 'Rueckkehr konnte nicht zugeordnet werden.', '<div class="alert alert-danger m-3">Die PayPal-Rueckkehr konnte keiner Bestellung zugeordnet werden.</div>', 'checkout');
      }

      if (in_array((string)($order['payment_status'] ?? ''), array('completed', 'paid'), true)) {
         $this->rememberProviderOrder($order);
         $body = '<div class="alert alert-success m-3"><strong>Zahlung bereits abgeschlossen.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' ist bezahlt.</div>';
         return $this->page('Danke', 'PayPal-Zahlung wurde bestaetigt.', $body, 'orders');
      }
      if (!$this->repo()->claimOrderPayment((int)$order['id'], 'paypal', $paypalOrderId)) {
         $fresh = $this->repo()->orderByPaymentReference('paypal', $paypalOrderId) ?: $order;
         $body = '<div class="alert alert-info m-3"><strong>Zahlung wird bereits verarbeitet.</strong><br>Bestellung ' . $this->h($fresh['order_no'] ?? '') . ' wird nicht doppelt belastet.</div>';
         return $this->page('PayPal', 'Zahlungsstatus wird verarbeitet.', $body, 'orders');
      }

      try {
         $capture = $this->paypal()->capture($paypalOrderId);
         $this->paypal()->validateCapture($capture, $order, $paypalOrderId);
         if (!$this->repo()->updateOrderPayment((int)$order['id'], 'paypal', 'completed', $paypalOrderId, $capture)) {
            throw new \RuntimeException('PayPal-Zahlungsstatus konnte nicht atomar gespeichert werden.');
         }
         $freshOrder = $this->repo()->orderById((int)$order['id']) ?: $order;
         $this->sendOrderMails($freshOrder);
         $this->rememberProviderOrder($freshOrder);
         $body = '<div class="alert alert-success m-3"><strong>Zahlung abgeschlossen.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' wurde bezahlt.</div>';
         return $this->page('Danke', 'PayPal-Zahlung wurde bestaetigt.', $body, 'orders');
      } catch (\Throwable $e) {
         $this->repo()->updateOrderPayment((int)$order['id'], 'paypal', 'failed', $paypalOrderId, array('error' => $e->getMessage()));
         return $this->page('PayPal', 'Zahlung konnte nicht bestaetigt werden.', '<div class="alert alert-danger m-3">' . $this->h($e->getMessage()) . '</div>', 'checkout');
      }
   }

   public function paypalCancel(): string {
      return $this->page(
         'PayPal abgebrochen',
         'Die Zahlung wurde nicht ausgefuehrt.',
         '<div class="alert alert-info m-3">Die PayPal-Seite wurde verlassen. Der Warenkorb bleibt erhalten; der serverseitige Zahlungsstatus wird nicht allein aufgrund dieses Browseraufrufs veraendert.</div>',
         'checkout'
      );
   }

   public function amazonPayReturn(): string {
      $this->ensureSeed();
      $checkoutSessionId = (string)dbx()->get_modul_var('checkoutSessionId', '', 'parameter');
      if ($checkoutSessionId === '') {
         $checkoutSessionId = (string)dbx()->get_modul_var('amazonCheckoutSessionId', '', 'parameter');
      }
      $orderNo = (string)dbx()->get_modul_var('order_no', '', 'parameter');
      $order = $this->providerReturnOrder('amazon_pay', $checkoutSessionId, $orderNo);
      if (!$order || $checkoutSessionId === '') {
         return $this->page('Amazon Pay', 'Rueckkehr konnte nicht zugeordnet werden.', '<div class="alert alert-danger m-3">Die Amazon-Pay-Rueckkehr konnte keiner Bestellung zugeordnet werden.</div>', 'checkout');
      }

      if (in_array((string)($order['payment_status'] ?? ''), array('completed', 'paid', 'pending'), true)) {
         $this->rememberProviderOrder($order);
         $body = '<div class="alert alert-success m-3"><strong>Amazon-Pay-Zahlung bereits verarbeitet.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' besitzt bereits einen gueltigen Providerstatus.</div>';
         return $this->page('Danke', 'Amazon-Pay-Zahlung wurde verarbeitet.', $body, 'orders');
      }
      if (!$this->repo()->claimOrderPayment((int)$order['id'], 'amazon_pay', $checkoutSessionId)) {
         $fresh = $this->repo()->orderByPaymentReference('amazon_pay', $checkoutSessionId) ?: $order;
         $body = '<div class="alert alert-info m-3"><strong>Zahlung wird bereits verarbeitet.</strong><br>Bestellung ' . $this->h($fresh['order_no'] ?? '') . ' wird nicht doppelt abgeschlossen.</div>';
         return $this->page('Amazon Pay', 'Zahlungsstatus wird verarbeitet.', $body, 'orders');
      }

      try {
         $result = $this->amazonPay()->completeCheckoutSession($checkoutSessionId, $order);
         $paymentStatus = $this->amazonPay()->validateCompletion($result, $order, $checkoutSessionId);
         if (!$this->repo()->updateOrderPayment((int)$order['id'], 'amazon_pay', $paymentStatus, $checkoutSessionId, $result)) {
            throw new \RuntimeException('Amazon-Pay-Status konnte nicht atomar gespeichert werden.');
         }
         $freshOrder = $this->repo()->orderById((int)$order['id']) ?: $order;
         if ($paymentStatus === 'completed') {
            $this->sendOrderMails($freshOrder);
         }
         $this->rememberProviderOrder($freshOrder);
         $body = '<div class="alert alert-success m-3"><strong>Amazon-Pay-Zahlung verarbeitet.</strong><br>Bestellung ' . $this->h($order['order_no'] ?? '') . ' wurde aktualisiert.</div>';
         return $this->page('Danke', 'Amazon-Pay-Zahlung wurde bestaetigt.', $body, 'orders');
      } catch (\Throwable $e) {
         $this->repo()->updateOrderPayment((int)$order['id'], 'amazon_pay', 'failed', $checkoutSessionId, array('error' => $e->getMessage()));
         return $this->page('Amazon Pay', 'Zahlung konnte nicht bestaetigt werden.', '<div class="alert alert-danger m-3">' . $this->h($e->getMessage()) . '</div>', 'checkout');
      }
   }

   public function amazonPayCancel(): string {
      return $this->page(
         'Amazon Pay abgebrochen',
         'Die Zahlung wurde nicht ausgefuehrt.',
         '<div class="alert alert-info m-3">Die Amazon-Pay-Seite wurde verlassen. Der Warenkorb bleibt erhalten; der serverseitige Zahlungsstatus wird nicht allein aufgrund dieses Browseraufrufs veraendert.</div>',
         'checkout'
      );
   }

   public function orders(): string {
      $this->ensureSeed();
      $this->startSession();
      $texts = $this->texts('dbxShop|shop-orders');
      $cards = '';
      $seen = array();
      $lastOrderNo = (string)($_SESSION['dbxShop_last_order_no'] ?? '');
      if ($lastOrderNo !== '') {
         $order = $this->repo()->orderByNo($lastOrderNo);
         if (is_array($order)) {
            $cards .= $this->publicOrderCard($order);
            $seen[(string)($order['order_no'] ?? '')] = true;
         }
      }

      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      foreach ($this->repo()->ordersByUid($uid, 25) as $order) {
         $orderNo = (string)($order['order_no'] ?? '');
         if ($orderNo !== '' && isset($seen[$orderNo])) {
            continue;
         }
         $cards .= $this->publicOrderCard($order);
      }

      if ($cards === '') {
         $cards = $this->placeholder(
            $texts->get_fd_message('empty_title'),
            $texts->get_fd_message('empty_message')
         );
      } else {
         $cards = '<section class="dbx-shop-public-orders">' . $cards . '</section>';
      }

      return $this->page(
         $texts->get_fd_message('page_title'),
         $texts->get_fd_message('page_subtitle'),
         $cards,
         'orders'
      );
   }

   private function orderIsPublicAccessible(array $order): bool {
      $this->startSession();
      $orderNo = (string)($order['order_no'] ?? '');
      if ($orderNo !== '' && $orderNo === (string)($_SESSION['dbxShop_last_order_no'] ?? '')) {
         return true;
      }
      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      return $uid > 0 && (int)($order['uid'] ?? 0) === $uid;
   }

   public function invoicePdf(): string {
      $this->ensureSeed();
      $this->startSession();
      $orderNo = (string)dbx()->get_modul_var('order_no', '', 'parameter');
      $order = $orderNo !== '' ? $this->repo()->orderByNo($orderNo) : null;
      if (!is_array($order) || !$this->orderIsPublicAccessible($order)) {
         return $this->page('Rechnung', 'Zugriff nicht moeglich.', '<div class="alert alert-warning m-3">Die Rechnung wurde nicht gefunden oder ist fuer diesen Benutzer nicht freigegeben.</div>', 'orders');
      }
      $order = $this->repo()->ensureOrderInvoicePdf((int)$order['id']);
      if (!is_array($order)) {
         return $this->page('Rechnung', 'PDF konnte nicht erzeugt werden.', '<div class="alert alert-danger m-3">Die Rechnungsdatei konnte nicht erzeugt werden.</div>', 'orders');
      }
      $file = $this->repo()->invoicePdfAbsolutePath($order);
      if ($file === '') {
         return $this->page('Rechnung', 'PDF konnte nicht geladen werden.', '<div class="alert alert-danger m-3">Die Rechnungsdatei ist nicht verfuegbar.</div>', 'orders');
      }
      if (!headers_sent()) {
         header('Content-Type: application/pdf');
         header('Content-Disposition: inline; filename="' . basename($file) . '"');
         header('Content-Length: ' . filesize($file));
      }
      readfile($file);
      exit;
   }

   private function jsonResponse(array $data, int $status = 200): string {
      if (!headers_sent()) {
         http_response_code($status);
         header('Content-Type: application/json; charset=utf-8');
      }
      return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
   }

   public function channelWebhook(): string {
      $channelKey = (string)dbx()->get_modul_var('channel', '', 'parameter');
      $raw = (string)file_get_contents('php://input');
      $payload = json_decode($raw, true);
      if (!is_array($payload)) {
         $payload = $_POST;
      }

      try {
         $channel = $this->repo()->channelByKey($channelKey);
         if (!$channel) {
            return $this->jsonResponse(array('ok' => false, 'message' => 'Channel nicht gefunden.'), 404);
         }
         if ((int)($channel['active'] ?? 0) !== 1 || (int)($channel['order_import_enabled'] ?? 0) !== 1) {
            return $this->jsonResponse(array('ok' => false, 'message' => 'Order-Import fuer diesen Channel ist nicht aktiv.'), 403);
         }

         $secret = trim((string)($channel['webhook_secret'] ?? ''));
         if ($secret === '') {
            // Der Endpunkt ist wegen externer Provider bewusst oeffentlich.
            // Modul-/DD-Rechte authentifizieren deshalb keinen Absender.
            return $this->jsonResponse(array(
               'ok' => false,
               'message' => 'Webhook-Authentifizierung ist nicht konfiguriert.',
            ), 503);
         }
         // Keine Secrets in GET-URLs: Query-Strings landen regelmaessig in
         // Access-Logs, Browser-Historien und Referrer-Headern.
         $given = trim((string)(
            $_SERVER['HTTP_X_DBX_SHOP_SECRET']
            ?? $_SERVER['HTTP_X_CHANNEL_SECRET']
            ?? $_POST['secret']
            ?? $payload['secret']
            ?? ''
         ));
         if ($given === '' || !hash_equals($secret, $given)) {
            return $this->jsonResponse(array('ok' => false, 'message' => 'Webhook-Secret ungueltig.'), 403);
         }

         $connector = dbx()->get_include_obj('dbxShopChannelConnector', 'dbxShop');
         if (is_object($connector) && method_exists($connector, 'normalizeWebhookPayload')) {
            $payload = (array)$connector->normalizeWebhookPayload($channel, $payload);
         }

         $order = $this->repo()->importChannelOrder($channelKey, $payload);
         return $this->jsonResponse(array(
            'ok' => true,
            'order_no' => (string)($order['order_no'] ?? ''),
            'channel' => $channelKey,
         ));
      } catch (\Throwable $e) {
         return $this->jsonResponse(array('ok' => false, 'message' => $e->getMessage()), 400);
      }
   }

   public function legal(): string {
      return $this->renderCmsShopPage(
         'legal',
         'Rechtstexte',
         'AGB, Anbieterkennzeichnung, Zahlung, Versand und Datenschutz-Hinweise.',
         'legal'
      );
   }

   public function withdrawal(): string {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-withdrawal-form', 'shop-withdrawal-form');
      $form->_fd = 'dbxShop|withdrawal';
      $form->load_fd_messages();
      $pages = $this->ensureShopLegalPages();
      $cid = (int)($pages['withdrawal'] ?? 0);
      $body = '';
      if ($cid > 0) {
         $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
         $body = is_object($renderer) ? (string)$renderer->renderStatic($cid, array('template' => 'c-body1-footer')) : '';
      }
      if (trim($body) === '') {
         $body = $this->placeholder(
            $form->get_fd_message('page_title'),
            $form->get_fd_message('empty_content')
         );
      }
      return $this->page(
         $form->get_fd_message('page_title'),
         $form->get_fd_message('page_subtitle'),
         '<div class="dbx-shop-cms-page">' . $body . '</div>' . $this->withdrawalFormHtml($form),
         'withdrawal'
      );
   }
}
?>
