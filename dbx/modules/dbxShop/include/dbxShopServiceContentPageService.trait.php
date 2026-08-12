<?php
namespace dbx\dbxShop;

trait dbxShopServiceContentPageServiceTrait {

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
}
