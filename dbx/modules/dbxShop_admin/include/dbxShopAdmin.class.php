<?php
namespace dbx\dbxShop_admin;

class dbxShopAdmin {

   private const ACTION_TOKEN_SCOPE = 'dbxShop_admin.actions';

   /**
    * Erlaubt Provisionierung und Reparatur nur waehrend des expliziten,
    * tokenisierten Wartungslaufs.
    */
   private bool $maintenanceMode = false;

   /**
    * Sichtbare Rückmeldung einer vor dem Seitenaufbau abgewiesenen Kartenaktion.
    *
    * Die Kartenformulare werden erst nach der Aktionsverarbeitung aufgebaut.
    * Deshalb wird die Meldung kurz zwischengespeichert und von frame() genau
    * einmal oberhalb des aktuellen Verwaltungsbereichs ausgegeben.
    */
   private string $postedFormError = '';
   private $catalogTexts = null;

   /**
    * Modulweite, sprachabhängige Texte für die Katalog-Nebenformulare.
    *
    * Das eigene dbxForm-Objekt hält den FD-Kontext stabil, während auf einer
    * Seite mehrere Kartenformulare mit unterschiedlichen Datendictionaries
    * gerendert werden.
    */
   private function catalogTexts() {
      if ($this->catalogTexts) {
         return $this->catalogTexts;
      }
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('shop-catalog-texts');
      $texts->_fd = 'dbxShop_admin|shop-catalog';
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->catalogTexts = $texts;
      return $this->catalogTexts;
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function h($value): string {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   /**
    * Ergaenzt den vorhandenen sessiongebundenen dbx-Aktionstoken.
    *
    * GET-Links bleiben kompatibel. Nur Links und Endpunkte, die Daten
    * veraendern, werden tokenisiert.
    */
   private function actionUrl(string $url): string {
      $securedUrl = dbx()->action_url($url);
      if ($securedUrl !== $url) {
         return $securedUrl;
      }

      $separator = strpos($url, '?') === false ? '?' : '&';
      return $url . $separator . 'dbx_token=' . rawurlencode(dbx()->action_token(self::ACTION_TOKEN_SCOPE));
   }

   /**
    * Prueft den gemeinsamen Token fuer schreibende Shop-Admin-Aktionen.
    */
   private function checkActionToken(string $action): bool {
      $token = (string)dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
      if (dbx()->check_action_token(self::ACTION_TOKEN_SCOPE, $token)) {
         return true;
      }

      $this->postedFormError = $this->catalogTexts()->get_fd_message('security_token_error');
      dbx()->sys_msg(
         'security',
         'dbxShop_admin',
         $action,
         'Shop-Admin-Aktion ohne gueltigen Token abgewiesen',
         'ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
      );
      return false;
   }

   private function openWinButton(string $url, string $title, string $content, string $class = 'btn btn-outline-primary', string $width = '88%', string $height = '88%'): string {
      $escUrl = $this->h($url);
      $escTitle = $this->h($title);
      $class = trim($class);
      if (strpos(' ' . $class . ' ', ' openWin ') === false) {
         $class .= ' openWin';
      }
      if (strpos(' ' . $class . ' ', ' dbx-win ') === false) {
         $class .= ' dbx-win';
      }
      if (strpos($content, 'bi-question-circle') !== false && strpos(' ' . $class . ' ', ' dbx-help-action ') === false) {
         $class .= ' dbx-help-action';
      }
      return '<a class="' . $this->h($class) . '" href="' . $escUrl . '" data-url="' . $escUrl . '" data-title="' . $escTitle . '" data-width="' . $this->h($width) . '" data-height="' . $this->h($height) . '" title="' . $escTitle . '" role="button">' . $content . '</a>';
   }

   private function helpButton(int $helpId, string $title, string $class = 'btn btn-outline-secondary btn-sm me-1', string $width = '72%', string $height = '82%'): string {
      if ($helpId <= 0) {
         return '';
      }
      return $this->openWinButton(
         '?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId,
         $title,
         '<i class="bi bi-question-circle"></i><span class="visually-hidden"> Hilfe</span>',
         $class,
         $width,
         $height
      );
   }

   private function money($value): string {
      return number_format((float) $value, 2, ',', '.') . ' EUR';
   }

   private function mediaUrl(string $path): string {
      $path = trim(str_replace('\\', '/', $path));
      if ($path === '') return '';
      if (preg_match('~^https?://~i', $path) || substr($path, 0, 1) === '/') return $path;
      return dbx()->get_base_url() . ltrim($path, '/');
   }

   private function mediaItemUrl(array $image, bool $thumb = true): string {
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

   private function repo() {
      return dbx()->get_include_obj('dbxShopRepository', 'dbxShop');
   }

   private function ensureSeed(): void {
      // Normale Admin-Aufrufe duerfen weder Schema noch Demo-Daten aendern.
      // Installation und Wartung erfolgen ausschliesslich ueber run1=install.
      $this->repo()->install();
   }

   private function shopMediaUsageContentId(): int {
      $configured = (int)dbx()->get_config('dbxShop', 'media_usage_content_id');
      if ($configured > 0 && $this->contentPageExists($configured)) {
         return $configured;
      }

      return $this->ensureShopMediaUsagePage();
   }

   private function contentDd(): string {
      return function_exists('dbx_lng_name') ? dbx_lng_name('content') : 'content_de';
   }

   private function folderDd(): string {
      return function_exists('dbx_lng_name') ? dbx_lng_name('content_folder') : 'content_folder_de';
   }

   private function contentPageExists(int $contentId): bool {
      if ($contentId <= 0) {
         return false;
      }
      try {
         $row = $this->db()->select1($this->contentDd(), $contentId, 'id', 0);
         return is_array($row) && (int)($row['id'] ?? 0) === $contentId;
      } catch (\Throwable $e) {
         return false;
      }
   }

   private function ensureShopHelpFolder(): int {
      $db = $this->db();
      $folderDd = $this->folderDd();
      $row = $db->select1($folderDd, array('name' => 'shop', 'parent_id' => 0), 'id', 0);
      if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
         return (int)$row['id'];
      }

      $folderId = 0;
      $folderOk = (int)$db->insert($folderDd, array(
         'name' => 'shop',
         'parent_id' => 0,
         'sorter' => '9000',
         'group_read' => 'admin',
         'template' => 'c-content',
         'hero_template' => 'image-hero',
         'hero_image_id' => '',
         'hero_margin_top' => '',
         'hero_height' => '',
         'hero_variant' => '',
         'hero_sticky' => '',
         'hero_scroll_layer' => '',
      ));
      if ($folderOk === 1) {
         $folderId = (int)$db->get_insert_id();
      }
      if ($folderId > 0) {
         return $folderId;
      }

      return 0;
   }

   private function channelGroupsHelpHtml(): string {
      return '<section class="dbx-shop-help">'
         . '<h2>Channel-Gruppen</h2>'
         . '<p>Channel-Gruppen buendeln die Verkaufskanaele, auf denen ein Shop-Artikel angeboten werden soll. Sie werden spaeter Artikeln zugeordnet und steuern damit, ob ein Artikel nur im Shop, auf Marktplatzkanaelen oder in mehreren Kanaelen gefuehrt wird.</p>'
         . '<h3>Eingabefelder</h3>'
         . '<dl>'
         . '<dt>Key</dt><dd>Eindeutiger technischer Schluessel der Channel-Gruppe. Verwenden Sie kurze, stabile Werte wie <code>software-shop</code> oder <code>merch-marketplaces</code>. Der Key wird beim Neuanlegen gespeichert und danach nicht mehr direkt in der Liste geaendert.</dd>'
         . '<dt>Channel-Gruppe</dt><dd>Sichtbarer Name fuer die Verwaltung. Dieser Name sollte knapp beschreiben, fuer welche Artikel oder Verkaufssituation die Gruppe gedacht ist.</dd>'
         . '<dt>Beschreibung</dt><dd>Interne Erklaerung der Gruppe. Tragen Sie ein, welche Artikel in diese Gruppe gehoeren und warum die ausgewaehlten Channels passen.</dd>'
         . '<dt>Channels</dt><dd>Auswahl der aktiven Verkaufskanaele innerhalb dieser Gruppe. Aktivierte Checkboxen bedeuten: Artikel mit dieser Gruppe duerfen auf diesen Kanaelen gefuehrt oder exportiert werden.</dd>'
         . '<dt>Sort</dt><dd>Reihenfolge in Auswahllisten und Tabellen. Kleine Zahlen stehen weiter oben. Nutzen Sie Abstaende wie 10, 20, 30, damit spaeter Gruppen dazwischen einsortiert werden koennen.</dd>'
         . '<dt>Aktiv</dt><dd>Schaltet die Channel-Gruppe fuer die Nutzung ein oder aus. Inaktive Gruppen bleiben erhalten, werden aber nicht als aktive Option behandelt.</dd>'
         . '</dl>'
         . '<h3>Aktionen</h3>'
         . '<ul>'
         . '<li><strong>Plus in der Bar:</strong> fuegt oben eine leere Eingabezeile fuer eine neue Channel-Gruppe ein.</li>'
         . '<li><strong>Speichern:</strong> uebernimmt Name, Beschreibung, Channel-Auswahl, Sortierung und Aktiv-Status.</li>'
         . '<li><strong>Loeschen:</strong> verschiebt die Channel-Gruppe in den Papierkorb und deaktiviert ihre Channel-Zuordnungen. Bestehende Produktdaten bleiben dadurch nachvollziehbar, die Gruppe erscheint aber nicht mehr in der aktiven Liste.</li>'
         . '</ul>'
         . '<p><strong>Praxis:</strong> Legen Sie Gruppen nach Vertriebsszenarien an, nicht nach einzelnen Artikeln. Beispiele sind <code>software-shop</code>, <code>digital-marketplaces</code> oder <code>service-local</code>.</p>'
         . '</section>';
   }

   private function channelsHelpHtml(): string {
      return '<section class="dbx-shop-help">'
         . '<h2>Channels</h2>'
         . '<p>Channels beschreiben einzelne Verkaufskanaele wie Shop, eBay, Amazon, Kleinanzeigen oder eigene Marktplatz-Anbindungen. Ein aktiver Channel kann fuer Artikel-Export, Listing-Pflege und spaeter fuer Bestellrueckmeldungen genutzt werden.</p>'
         . '<h3>Eingabefelder</h3>'
         . '<dl>'
         . '<dt>Key</dt><dd>Eindeutiger technischer Schluessel des Channels, zum Beispiel <code>ebay</code> oder <code>amazon</code>. Der Key wird beim Neuanlegen vergeben und von Artikelzuordnungen und Bestellungen referenziert.</dd>'
         . '<dt>Channel</dt><dd>Sichtbarer Name in Verwaltung, Artikelliste und Channel-Gruppen.</dd>'
         . '<dt>Beschreibung</dt><dd>Interne Notiz, wofuer der Channel genutzt wird und welche Besonderheiten bei Angeboten gelten.</dd>'
         . '<dt>Plattform</dt><dd>Typ der Zielplattform. Vordefinierte Werte helfen spaeter beim Anschluss spezifischer API-Adapter; <code>custom</code> ist fuer eigene Integrationen gedacht.</dd>'
         . '<dt>Verbindung</dt><dd>Legt fest, ob der Channel intern, manuell, per API oder per Feed/Webhook betrieben wird.</dd>'
         . '<dt>Export</dt><dd>Erlaubt, Artikel aus dem Shop fuer diesen Channel vorzubereiten oder spaeter automatisiert als Listing zu uebertragen.</dd>'
         . '<dt>Order-Import</dt><dd>Erlaubt Bestellrueckmeldungen vom Channel in den Shop. Externe Plattformen benoetigen dafuer API-Zugriff oder einen Webhook.</dd>'
         . '<dt>API-Basis-URL</dt><dd>Basisadresse der Channel-API. Der Verbindungstest nutzt je nach Plattform den passenden Connector, z.B. eBay OAuth/Sell API, Amazon LWA/SP-API oder mobile.de Basic Auth.</dd>'
         . '<dt>Client-ID / Benutzer</dt><dd>Oeffentliche Kennung oder Login-Name der Integration.</dd>'
         . '<dt>Secret / Token / Passwort</dt><dd>Zugangsdaten fuer API-Aufrufe. Gespeicherte Werte werden im Feld angezeigt und koennen direkt ueberschrieben werden.</dd>'
         . '<dt>Marketplace-ID</dt><dd>Marktplatzkennung der Plattform, zum Beispiel <code>EBAY_DE</code> fuer eBay Deutschland oder <code>A1PA6795UKMFR9</code> fuer Amazon.de.</dd>'
         . '<dt>Seller-ID / Account-ID</dt><dd>Verkaeufer- oder Konto-ID der Plattform. Bei mobile.de ist hier insbesondere die <code>mobileSellerId</code> relevant.</dd>'
         . '<dt>Location-Key</dt><dd>Lager-, Standort- oder Versandortkennung. eBay benoetigt einen Merchant Location Key, bevor Angebote veroeffentlicht werden koennen.</dd>'
         . '<dt>Kategorie-ID</dt><dd>Standard-Kategorie fuer neue Listings, wenn der Adapter keine automatische Kategorieermittlung nutzt.</dd>'
         . '<dt>Payment-, Fulfillment- und Return-Policy</dt><dd>Policy-IDs der Plattform. Bei eBay sind das die Business Policies des Verkaeuferkontos: <strong>Payment Policy</strong> beschreibt Zahlungsarten und Zahlungsbedingungen, <strong>Fulfillment Policy</strong> beschreibt Versandservice, Bearbeitungszeit, Versandkosten und Versandregionen, <strong>Return Policy</strong> beschreibt Ruecknahmefrist, Kostentraeger und Rueckgabebedingungen. eBay-Angebote koennen ohne passende Policy-IDs oft nicht veroeffentlicht werden.</dd>'
         . '<dt>Notification-Ziel / Topic</dt><dd>Ziel fuer Rueckmeldungen, zum Beispiel eBay Notification Destination oder Amazon SQS ARN, plus Ereignis wie <code>ORDER_CHANGE</code>.</dd>'
         . '<dt>API-Scopes / Rollen</dt><dd>Dokumentation der erforderlichen Berechtigungen, etwa eBay OAuth Scopes oder Amazon SP-API Rollen. In der Maske am besten einen Wert pro Zeile eintragen. Wenn ein Anbieter technisch Leerzeichen erwartet, verbindet der spaetere Adapter diese Zeilen beim API-Aufruf passend.</dd>'
         . '<dt>Webhook-Secret</dt><dd>Geheimer Wert zur Pruefung eingehender Rueckmeldungen, zum Beispiel wenn ein Kauf auf eBay oder Amazon im Shop als Bestellung angelegt werden soll.</dd>'
         . '<dt>Sort / Aktiv</dt><dd>Sortierung und grundsaetzliche Freigabe des Channels.</dd>'
         . '</dl>'
         . '<h3>Aktionen</h3>'
         . '<ul>'
         . '<li><strong>Plus in der Bar:</strong> oeffnet ein Formular fuer einen neuen Channel.</li>'
         . '<li><strong>Speichern:</strong> uebernimmt Stammdaten, Integrationsmodus und Zugangsdaten.</li>'
         . '<li><strong>Test:</strong> prueft provider-spezifisch die Pflichtfelder und, soweit Zugangsdaten vorhanden sind, einen echten API-Zugriff ohne Daten zu veraendern.</li>'
         . '<li><strong>Loeschen:</strong> deaktiviert und verschiebt den Channel in den Papierkorb. Artikelzuordnungen werden deaktiviert.</li>'
         . '</ul>'
         . '<p><strong>Praxis:</strong> Erst Channel konfigurieren, dann in Channel-Gruppen auswaehlen und anschliessend Artikel diesen Gruppen oder einzelnen Channels zuordnen.</p>'
         . '</section>';
   }

   private function channelProviderHelpHtml(string $platform): string {
      $common = '<h3>Gemeinsame Eingabefelder</h3>'
         . '<dl>'
         . '<dt>Key</dt><dd>Technischer Schluessel des Channels. Er wird in Artikelzuordnungen, Channel-Gruppen, Webhook-URLs und Bestellungen gespeichert. Nach dem Anlegen nicht mehr aendern.</dd>'
         . '<dt>Channel</dt><dd>Sichtbarer Name in der Verwaltung.</dd>'
         . '<dt>Plattform</dt><dd>Legt fest, welcher Adapter oder welche Arbeitsweise spaeter verwendet wird.</dd>'
         . '<dt>Verbindung</dt><dd><code>Intern</code> fuer den Shop selbst, <code>Manuell</code> fuer Pflege ohne API, <code>API</code> fuer direkte Schnittstellen, <code>Feed</code> fuer Dateiuebergaben und <code>Webhook</code> fuer Rueckmeldungen.</dd>'
         . '<dt>Aktiv</dt><dd>Nur aktive Channels koennen Artikeln zugeordnet und fuer Importe verwendet werden.</dd>'
         . '<dt>Export</dt><dd>Erlaubt, Artikel fuer diesen Channel vorzubereiten oder spaeter automatisiert zur Plattform zu uebertragen.</dd>'
         . '<dt>Order-Import</dt><dd>Erlaubt, Bestellungen oder Kauf-/Lead-Rueckmeldungen vom Channel in dbxShop zu uebernehmen.</dd>'
         . '<dt>API-Basis-URL</dt><dd>Basisadresse der Anbieter-API. Der Verbindungstest nutzt den passenden Connector und macht nur lesende Pruefungen.</dd>'
         . '<dt>Client-ID</dt><dd>Oeffentliche App- oder Client-Kennung der Plattform.</dd>'
         . '<dt>Client-Secret</dt><dd>Geheimer App-Schluessel. Der gespeicherte Wert wird angezeigt und kann direkt ersetzt werden.</dd>'
         . '<dt>Access-Token</dt><dd>Kurzlebiger Zugriffstoken. Wird oft automatisch aus dem Refresh-Token erneuert.</dd>'
         . '<dt>Refresh-Token</dt><dd>Langfristiger Token, mit dem neue Access-Tokens erzeugt werden.</dd>'
         . '<dt>Benutzer / Passwort</dt><dd>Nur fuer Plattformen mit Basic Auth oder klassischem API-Login verwenden.</dd>'
         . '<dt>Marketplace-ID</dt><dd>Marktplatz- oder Laenderkennung, zum Beispiel <code>EBAY_DE</code> oder <code>A1PA6795UKMFR9</code>.</dd>'
         . '<dt>Seller-ID</dt><dd>Verkaeuferkennung der Plattform.</dd>'
         . '<dt>Account-ID / mobileSellerId</dt><dd>Konto-, Haendler- oder mobile.de Seller-ID.</dd>'
         . '<dt>Location-Key</dt><dd>Standort- oder Lagerkennung, relevant fuer eBay-Angebote.</dd>'
         . '<dt>Kategorie-ID</dt><dd>Plattformkategorie fuer Listings, falls keine automatische Zuordnung genutzt wird.</dd>'
         . '<dt>Payment-, Fulfillment-, Return-Policy</dt><dd>Policy-IDs fuer Zahlungs-, Versand- und Rueckgaberegeln. Fuer eBay sind diese Felder besonders wichtig; Details stehen im eBay-Hilfetext.</dd>'
         . '<dt>Notification-Ziel / SQS ARN</dt><dd>Zieladresse fuer Ereignisse: HTTPS-Destination bei eBay oder Amazon SQS ARN bei Amazon.</dd>'
         . '<dt>Notification-Topic</dt><dd>Ereignistyp, der abonniert wird, zum Beispiel <code>ORDER_CHANGE</code>.</dd>'
         . '<dt>API-Scopes / Rollen</dt><dd>Dokumentation der genehmigten Berechtigungen. Empfehlung fuer die Eingabe: ein Scope oder eine Rolle pro Zeile. eBay verlangt die Scopes im OAuth-Request technisch als leerzeichengetrennte Liste; beim Senden wird das dann URL-encodiert. Amazon verwendet hier eher Rollen/Berechtigungen, keine eBay-artige Scope-Liste.</dd>'
         . '<dt>Webhook-Secret</dt><dd>Geheimer Wert, mit dem dbxShop eingehende Channel-Webhooks pruefen kann.</dd>'
         . '<dt>dbxShop Webhook-URL</dt><dd>URL, die eine Plattform oder Middleware fuer Rueckmeldungen aufrufen kann.</dd>'
         . '</dl>';

      $linksTitle = '<h3>Beantragen / Bearbeiten</h3>';
      switch ($platform) {
         case 'ebay':
            return '<section class="dbx-shop-help">'
               . '<h2>Channel-Hilfe: eBay</h2>'
               . '<p>eBay ist ein API-faehiger Marktplatz-Channel. Fuer automatische Listings braucht dbxShop OAuth-Zugangsdaten, Marketplace, Location und Business-Policies. Fuer Bestellungen werden Fulfillment API oder Notification API genutzt.</p>'
               . $common
               . '<h3>eBay-spezifisch</h3><ol>'
               . '<li>Developer-Konto anlegen und Application Keyset erstellen. Daraus kommen Client-ID und Client-Secret.</li>'
               . '<li>OAuth User Consent fuer den eBay-Verkaeufer durchlaufen. Daraus kommt der Refresh-Token.</li>'
               . '<li>Scopes fuer Inventory, Fulfillment und Notifications eintragen.</li>'
               . '<li>Marketplace-ID setzen, fuer Deutschland meist <code>EBAY_DE</code>.</li>'
               . '<li>Merchant Location Key anlegen oder aus der Inventory API uebernehmen.</li>'
               . '<li>Payment-, Fulfillment- und Return-Policy in eBay Business Policies pflegen. Die IDs kommen aus dem eBay Seller-Konto oder aus der Account API. Ohne diese IDs kann ein Offer zwar vorbereitet sein, aber haeufig nicht erfolgreich published werden.</li>'
               . '<li><strong>Payment Policy ID:</strong> ID der Zahlungsregel, z.B. welche Zahlungsarten erlaubt sind und wann bezahlt werden muss.</li>'
               . '<li><strong>Fulfillment Policy ID:</strong> ID der Versandregel, z.B. Versanddienst, Bearbeitungszeit, Kosten, kostenlose Lieferung, internationale Versandzonen.</li>'
               . '<li><strong>Return Policy ID:</strong> ID der Rueckgaberegel, z.B. ob Rueckgabe akzeptiert wird, Frist und wer Ruecksendekosten traegt.</li>'
               . '<li>Notification Destination auf die dbxShop Webhook-URL setzen und Topic/Subscription fuer Order-Ereignisse konfigurieren.</li>'
               . '</ol>'
               . '<h3>eBay Scopes richtig eintragen</h3>'
               . '<p>eBay OAuth erwartet Scopes technisch im Parameter <code>scope</code> als durch Leerzeichen getrennte Liste. In diesem Formular ist es uebersichtlicher, jeden Scope in eine eigene Zeile zu schreiben. Ein spaeterer eBay-Adapter kann daraus automatisch die leerzeichengetrennte und URL-encodierte OAuth-Liste bauen.</p>'
               . '<p>Beispiel:</p>'
               . '<pre><code>https://api.ebay.com/oauth/api_scope/sell.inventory&#10;https://api.ebay.com/oauth/api_scope/sell.fulfillment&#10;https://api.ebay.com/oauth/api_scope/commerce.notification.subscription</code></pre>'
               . $linksTitle
               . '<ul>'
               . '<li><a href="https://developer.ebay.com/develop/guides-v2/get-started-with-ebay-apis" target="_blank" rel="noopener">eBay APIs starten und Application Keys</a></li>'
               . '<li><a href="https://developer.ebay.com/develop/guides-v2/authorization" target="_blank" rel="noopener">eBay OAuth und Scopes</a></li>'
               . '<li><a href="https://developer.ebay.com/api-docs/sell/inventory/overview.html" target="_blank" rel="noopener">eBay Inventory API</a></li>'
               . '<li><a href="https://developer.ebay.com/api-docs/sell/fulfillment/overview.html" target="_blank" rel="noopener">eBay Fulfillment API / Orders</a></li>'
               . '<li><a href="https://developer.ebay.com/api-docs/commerce/notification/overview.html" target="_blank" rel="noopener">eBay Notification API</a></li>'
               . '<li><a href="https://www.ebay.com/help/selling/business-policies/business-policies?id=4212" target="_blank" rel="noopener">eBay Business Policies</a></li>'
               . '</ul></section>';

         case 'amazon':
            return '<section class="dbx-shop-help">'
               . '<h2>Channel-Hilfe: Amazon</h2>'
               . '<p>Amazon nutzt die Selling Partner API. Fuer Listings und Orders braucht dbxShop eine registrierte SP-API App, LWA-Zugangsdaten, Seller-Autorisierung, Seller-ID, Marketplace-ID und passende Rollen.</p>'
               . $common
               . '<h3>Amazon-spezifisch</h3><ol>'
               . '<li>Professionelles Seller-Central-Konto und Entwicklerzugang vorbereiten.</li>'
               . '<li>SP-API Anwendung im Solution Provider Portal registrieren.</li>'
               . '<li>LWA Client-ID und Client-Secret aus der App-Registrierung uebernehmen.</li>'
               . '<li>Seller-Autorisierung durchlaufen und Refresh-Token speichern.</li>'
               . '<li>Marketplace-ID setzen, fuer Amazon.de <code>A1PA6795UKMFR9</code>.</li>'
               . '<li>Seller-ID aus Seller Central oder aus der Autorisierung uebernehmen.</li>'
               . '<li>Rollen fuer Product Listing, Orders und Notifications beantragen und im Feld API-Scopes/Rollen dokumentieren.</li>'
               . '<li>Fuer Rueckmeldungen Amazon Notifications API mit SQS Destination einrichten; die SQS ARN im Notification-Ziel speichern.</li>'
               . '</ol>'
               . '<h3>Amazon-Felder verstaendlich erklaert</h3><dl>'
               . '<dt>Client-ID und Client-Secret</dt><dd>Das sind die LWA-Zugangsdaten der registrierten SP-API Anwendung. Sie identifizieren die App, nicht den einzelnen Artikel.</dd>'
               . '<dt>Refresh-Token</dt><dd>Dieser Token entsteht, wenn der Seller die App autorisiert. Er ist der wichtigste gespeicherte Wert, weil daraus neue Access-Tokens erzeugt werden.</dd>'
               . '<dt>Marketplace-ID</dt><dd>Legt fest, auf welchem Amazon-Marktplatz gearbeitet wird. Fuer Deutschland ist das typischerweise <code>A1PA6795UKMFR9</code>.</dd>'
               . '<dt>Seller-ID</dt><dd>Identifiziert das Seller-Konto, fuer das Listings und Orders verarbeitet werden.</dd>'
               . '<dt>Kategorie-ID / Product Type</dt><dd>Amazon arbeitet nicht nur mit Kategorien, sondern stark mit Product Types und Pflichtattributen. Fuer Listings muss der Adapter wissen, welches Produktschema verwendet wird.</dd>'
               . '<dt>Notification-Ziel / SQS ARN</dt><dd>Amazon sendet Rueckmeldungen nicht direkt an irgendeine URL, sondern typischerweise an eine AWS SQS Queue. Die ARN dieser Queue gehoert in dieses Feld.</dd>'
               . '<dt>Notification-Topic</dt><dd>Fuer Bestellungen ist <code>ORDER_CHANGE</code> relevant. Damit koennen Statusaenderungen oder neue Bestellungen in dbxShop ankommen.</dd>'
               . '<dt>API-Scopes / Rollen</dt><dd>Hier dokumentieren, welche SP-API Rollen genehmigt wurden, zum Beispiel Listings, Orders und Notifications. Das sind keine eBay-OAuth-Scopes. Tragen Sie am besten eine Rolle pro Zeile ein, damit klar bleibt, welche Berechtigung vorhanden ist.</dd>'
               . '<dt>Payment/Fulfillment/Return Policies</dt><dd>Diese eBay-Felder sind fuer Amazon normalerweise nicht relevant. Amazon verwaltet Zahlungs- und Versandlogik anders ueber Seller Central, Fulfillment by Merchant oder FBA.</dd>'
               . '</dl>'
               . $linksTitle
               . '<ul>'
               . '<li><a href="https://developer-docs.amazon.com/sp-api/docs/registering-your-application" target="_blank" rel="noopener">Amazon SP-API App registrieren</a></li>'
               . '<li><a href="https://developer-docs.amazon.com/sp-api/docs/authorization-overview" target="_blank" rel="noopener">Amazon SP-API Autorisierung</a></li>'
               . '<li><a href="https://developer-docs.amazon.com/sp-api/docs/marketplace-ids" target="_blank" rel="noopener">Amazon Marketplace-IDs</a></li>'
               . '<li><a href="https://developer-docs.amazon.com/sp-api/docs/listings-items-api" target="_blank" rel="noopener">Amazon Listings Items API</a></li>'
               . '<li><a href="https://developer-docs.amazon.com/sp-api/docs/orders-api-v0-reference" target="_blank" rel="noopener">Amazon Orders API</a></li>'
               . '<li><a href="https://developer-docs.amazon.com/sp-api/docs/notifications-api" target="_blank" rel="noopener">Amazon Notifications API</a></li>'
               . '</ul></section>';

         case 'mobile':
            return '<section class="dbx-shop-help">'
               . '<h2>Channel-Hilfe: mobile.de</h2>'
               . '<p>mobile.de ist kein klassischer Warenkorb-Marktplatz, sondern vor allem ein Fahrzeuganzeigen- und Lead-Channel. Relevant sind Seller API fuer Anzeigen und Lead API fuer Rueckmeldungen.</p>'
               . $common
               . '<h3>mobile.de-spezifisch</h3><ol>'
               . '<li>Dealer- oder API-Account bei mobile.de verwenden.</li>'
               . '<li>API-Zugang im Dealer-Bereich oder ueber mobile.de Support aktivieren lassen.</li>'
               . '<li>Benutzer und Passwort fuer Basic Auth eintragen.</li>'
               . '<li>API-Basis-URL setzen, produktiv normalerweise <code>https://services.mobile.de</code> bzw. Seller-API-Pfad.</li>'
               . '<li>Account-ID/mobileSellerId oder Customer Number aus Dealer Area/API-Daten uebernehmen.</li>'
               . '<li>Fuer Rueckmeldungen Lead API nutzen; passende Lead-Events im Notification-Topic dokumentieren.</li>'
               . '</ol>'
               . '<h3>mobile.de-Felder verstaendlich erklaert</h3><dl>'
               . '<dt>API-Basis-URL</dt><dd>Adresse der mobile.de Seller API, zum Beispiel <code>https://services.mobile.de/seller-api</code>. Sandbox und Produktion koennen unterschiedliche URLs haben.</dd>'
               . '<dt>Benutzer / Passwort</dt><dd>mobile.de nutzt fuer viele API-Zugriffe Basic Auth. Deshalb sind API-Benutzer und API-Passwort wichtiger als Client-ID oder OAuth-Tokens.</dd>'
               . '<dt>Account-ID / mobileSellerId</dt><dd>Die mobileSellerId identifiziert den Haendlerbestand. Ohne diese Zuordnung kann ein System oft nicht wissen, zu welchem Haendler ein Inserat gehoert.</dd>'
               . '<dt>Seller-ID</dt><dd>Kann als Kundennummer oder interne Haendlerkennung dokumentiert werden.</dd>'
               . '<dt>Kategorie-ID</dt><dd>Beschreibt die Art des Inserats, zum Beispiel Auto, Motorrad oder Nutzfahrzeug. mobile.de ist stark fahrzeugorientiert, nicht allgemeiner Warenkorb-Shop.</dd>'
               . '<dt>Location-Key</dt><dd>Optionaler Standort oder Filialbezug, wenn ein Haendler mehrere Standorte hat.</dd>'
               . '<dt>Notification-Ziel / Topic</dt><dd>Rueckmeldungen sind eher Leads, Anfragen oder Direct Offers. Dokumentieren Sie hier, welche Middleware oder Lead-API angebunden ist.</dd>'
               . '<dt>Payment/Fulfillment/Return Policies</dt><dd>Diese eBay-Felder sind fuer mobile.de normalerweise nicht relevant, weil mobile.de keine klassischen Shop-Checkout-Regeln fuer diese Produkte verwaltet.</dd>'
               . '<dt>API-Scopes / Rollen</dt><dd>mobile.de nutzt in der Regel Basic Auth und API-Freischaltungen statt OAuth-Scopes. Das Feld kann zur Dokumentation genutzt werden, zum Beispiel <code>seller-api</code> und <code>lead-api</code> jeweils in einer eigenen Zeile.</dd>'
               . '</dl>'
               . $linksTitle
               . '<ul>'
               . '<li><a href="https://services.mobile.de/" target="_blank" rel="noopener">mobile.de API Uebersicht und Accounts</a></li>'
               . '<li><a href="https://services.mobile.de/manual/seller-api.html" target="_blank" rel="noopener">mobile.de Seller API</a></li>'
               . '<li><a href="https://services.sandbox.mobile.de/manual/lead-api.html" target="_blank" rel="noopener">mobile.de Lead API Sandbox</a></li>'
               . '<li><a href="https://services.mobile.de/manual/search-api.html" target="_blank" rel="noopener">mobile.de Search API / Customer IDs</a></li>'
               . '</ul></section>';

         case 'kleinanzeigen':
            return '<section class="dbx-shop-help">'
               . '<h2>Channel-Hilfe: Kleinanzeigen</h2>'
               . '<p>Kleinanzeigen ist hier als manueller oder vertraglicher Channel vorbereitet. Eine allgemein frei nutzbare offizielle Anzeigen-/Order-API fuer normale Shop-Artikel ist nicht hinterlegt. Keine Scraper-Zugangsdaten als offizielle API behandeln.</p>'
               . $common
               . '<h3>Kleinanzeigen-spezifisch</h3><ol>'
               . '<li>Wenn keine vertragliche Schnittstelle vorhanden ist, Verbindung auf <code>Manuell</code> lassen.</li>'
               . '<li>Export nur aktivieren, wenn eine rechtlich freigegebene Middleware oder Schnittstelle vorhanden ist.</li>'
               . '<li>API-Basis-URL, Benutzer, Token und Kategorie-ID nur aus dieser freigegebenen Schnittstelle uebernehmen.</li>'
               . '<li>Order-Import nur aktivieren, wenn Kauf-/Kontakt-Rueckmeldungen offiziell oder ueber eine eigene Middleware geliefert werden.</li>'
               . '<li>Fuer Immobilien gibt es offizielle Importschnittstellen; diese sind nicht automatisch eine allgemeine Artikel-API.</li>'
               . '</ol>'
               . '<h3>Kleinanzeigen-Felder verstaendlich erklaert</h3><dl>'
               . '<dt>Verbindung</dt><dd>Ohne freigegebene Schnittstelle sollte dieser Channel auf <code>Manuell</code> stehen. Das bedeutet: dbxShop dokumentiert den Channel, uebertraegt aber nicht automatisch.</dd>'
               . '<dt>API-Basis-URL / Client-ID / Secret / Token</dt><dd>Nur ausfuellen, wenn ein Vertrag, eine offizielle Importschnittstelle oder eine eigene Middleware diese Werte bereitstellt. Keine Login-Daten fuer Scraping eintragen.</dd>'
               . '<dt>Kategorie-ID</dt><dd>Falls eine freigegebene Schnittstelle Kategorien verwendet, kann hier die Zielkategorie dokumentiert werden.</dd>'
               . '<dt>Location-Key</dt><dd>Kann Ort, PLZ oder Standort-ID enthalten, wenn die Schnittstelle das erwartet.</dd>'
               . '<dt>Notification-Ziel / Topic</dt><dd>Bei Kleinanzeigen sind Rueckmeldungen eher Nachrichten, Leads oder Kontaktanfragen. Ein echter Kauf-Webhook ist nur sinnvoll, wenn eine Middleware solche Ereignisse liefert.</dd>'
               . '<dt>Order-Import</dt><dd>Nur aktivieren, wenn wirklich strukturierte Kauf-/Kontakt-Rueckmeldungen an dbxShop uebergeben werden koennen. Sonst bleibt die Pflege manuell.</dd>'
               . '<dt>Payment/Fulfillment/Return Policies</dt><dd>Diese eBay-Felder sind fuer Kleinanzeigen normalerweise nicht relevant.</dd>'
               . '<dt>API-Scopes / Rollen</dt><dd>Nur ausfuellen, wenn eine vertragliche Schnittstelle oder Middleware konkrete Berechtigungen nennt. Auch hier ist ein Wert pro Zeile am verstaendlichsten.</dd>'
               . '</dl>'
               . $linksTitle
               . '<ul>'
               . '<li><a href="https://hilfe-gewerblich.kleinanzeigen.de/artikel/schnittstellen" target="_blank" rel="noopener">Kleinanzeigen gewerbliche Schnittstellen</a></li>'
               . '<li><a href="https://business.kleinanzeigen.de/" target="_blank" rel="noopener">Kleinanzeigen fuer Gewerbliche</a></li>'
               . '</ul></section>';

         case 'shop':
            return '<section class="dbx-shop-help">'
               . '<h2>Channel-Hilfe: Shop</h2>'
               . '<p>Der Shop-Channel ist der interne Verkaufskanal. Er braucht keine externen API-Zugangsdaten.</p>'
               . $common
               . '<h3>Shop-spezifisch</h3><ol>'
               . '<li>Verbindung auf <code>Intern</code> lassen.</li>'
               . '<li>Export aktiv lassen, wenn Artikel im eigenen Shop sichtbar sein duerfen.</li>'
               . '<li>Order-Import bleibt aus, weil Bestellungen direkt im Shop entstehen.</li>'
               . '<li>API-Felder, Token, Marketplace-ID und Policies sind fuer den internen Shop nicht benoetigt.</li>'
               . '</ol></section>';

         default:
            return '<section class="dbx-shop-help">'
               . '<h2>Channel-Hilfe: Eigener Channel</h2>'
               . '<p>Eigene Channels koennen an individuelle APIs, Feed-Exporte oder Middleware angebunden werden. Die konkreten Pflichtwerte haengen vom Adapter ab.</p>'
               . $common
               . '<h3>Vorgehen</h3><ol>'
               . '<li>Beim Anbieter API-Zugang, Dokumentation und Testumgebung beantragen.</li>'
               . '<li>API-Basis-URL, Authentifizierung und erforderliche IDs in die Felder uebernehmen.</li>'
               . '<li>Webhook-Secret setzen und den dbxShop Webhook in der Middleware hinterlegen.</li>'
               . '<li>Verbindung testen und erst danach Export oder Order-Import produktiv nutzen.</li>'
               . '</ol></section>';
      }
   }

   private function productGroupsHelpHtml(): string {
      return '<section class="dbx-shop-help">'
         . '<h2>Artikelgruppen</h2>'
         . '<p>Artikelgruppen beschreiben fachliche Produktfamilien. Sie steuern Standardwerte wie Mehrwertsteuer, Darstellung, Detailansicht, Galerieverhalten und Hinweise zu Artikelattributen.</p>'
         . '<h3>Eingabefelder</h3>'
         . '<dl>'
         . '<dt>Key</dt><dd>Eindeutiger technischer Schluessel der Artikelgruppe. Nutzen Sie kurze stabile Werte wie <code>software</code>, <code>merch</code> oder <code>service</code>. Der Key wird beim Neuanlegen vergeben und bleibt danach stabil.</dd>'
         . '<dt>Obergruppe</dt><dd>Optionaler Platz in der Gruppenstruktur. Ohne Obergruppe erscheint die Gruppe als oberste Kachel im Katalog. Mit Obergruppe wird sie erst angezeigt, wenn der Kunde die uebergeordnete Gruppe anklickt, zum Beispiel <code>Kleidung / T-Shirts / male</code>.</dd>'
         . '<dt>Gruppe</dt><dd>Sichtbarer Name der Artikelgruppe in Verwaltung und Auswahllisten.</dd>'
         . '<dt>Beschreibung</dt><dd>Interne Beschreibung, welche Artikel in diese Gruppe gehoeren und wie sie im Shop verwendet wird.</dd>'
         . '<dt>Gruppenbild</dt><dd>Bild fuer die Gruppenkachel im Katalog. Pro Artikelgruppe gibt es genau ein aktives Gruppenbild. Eine neue Auswahl ersetzt das bisherige Gruppenbild; die Datei im CMS-Medienbrowser bleibt erhalten.</dd>'
         . '<dt>MwSt.</dt><dd>Standard-Mehrwertsteuersatz fuer Artikel dieser Gruppe. Einzelne Artikel koennen abweichen, wenn sie eigene Steuerwerte verwenden.</dd>'
         . '<dt>Karte</dt><dd>Template fuer Produktkarten in Listen, Katalogen oder Teasern.</dd>'
         . '<dt>Detail</dt><dd>Template fuer die Detailseite eines Artikels aus dieser Gruppe.</dd>'
         . '<dt>Gallery</dt><dd>Template fuer die Bild- oder Mediengalerie der Gruppe.</dd>'
         . '<dt>Bilder</dt><dd>Anzahl der initial sichtbaren Galeriebilder. Weitere Bilder koennen je nach Overflow-Einstellung sichtbar, scrollbar oder per Galerie erreichbar sein.</dd>'
         . '<dt>Bildgroesse</dt><dd>Steuert, wie Bilder in der Galerie eingepasst werden: Original, Cover oder Contain.</dd>'
         . '<dt>Lightbox</dt><dd>Breite der Lightbox-Ansicht, zum Beispiel <code>100vw</code> fuer volle Viewport-Breite.</dd>'
         . '<dt>Overflow</dt><dd>Verhalten bei vielen Bildern, zum Beispiel Grid, Slider, Scroll, Laufband oder Tutorial.</dd>'
         . '<dt>Klick</dt><dd>Definiert, was beim Klick auf ein Bild passiert: Lightbox, kein Klick, neuer Tab, ViewerJS oder PhotoSwipe.</dd>'
         . '<dt>Artikelattribute</dt><dd>Interne Hinweise fuer Attribute dieser Gruppe, zum Beispiel welche technischen Daten, Varianten oder Filter erwartet werden.</dd>'
         . '<dt>Channel-Vorgaben</dt><dd>Optionale Standardwerte fuer externe Verkaufskanaele. eBay Kategorie, Amazon Product Type, Kleinanzeigen Kategorie und mobile.de Kategorie werden bei Artikeln dieser Gruppe als Vorschlag im Channel-Mapping verwendet. Pro Artikel kann der Wert dort ueberschrieben werden. Wenn Channels in der Shop-Konfiguration deaktiviert sind, werden diese Eingaben ausgeblendet.</dd>'
         . '<dt>Sort</dt><dd>Reihenfolge in Listen und Auswahlfeldern. Kleine Zahlen stehen weiter oben; Schritte von 10 lassen Platz fuer spaetere Gruppen.</dd>'
         . '<dt>Aktiv</dt><dd>Schaltet die Artikelgruppe fuer die Verwendung ein oder aus. Inaktive Gruppen bleiben erhalten, werden aber nicht als aktive Option behandelt.</dd>'
         . '</dl>'
         . '<h3>Aktionen</h3>'
         . '<ul>'
         . '<li><strong>Plus in der Bar:</strong> legt oben eine neue Eingabezeile fuer eine Artikelgruppe an.</li>'
         . '<li><strong>Speichern:</strong> uebernimmt alle sichtbaren Einstellungen der Zeile.</li>'
         . '<li><strong>Loeschen:</strong> verschiebt die Artikelgruppe in den Papierkorb und deaktiviert sie. Bestehende Produktzuordnungen bleiben technisch nachvollziehbar, die Gruppe erscheint aber nicht mehr in der aktiven Liste.</li>'
         . '</ul>'
         . '<p><strong>Praxis:</strong> Verwenden Sie Artikelgruppen fuer wiederkehrende Produktlogik, nicht fuer einzelne Produkte. Beispiele sind Software, Merchandise, Service oder Zubehoer.</p>'
         . '<p><strong>Katalog:</strong> Kunden sehen zunaechst die obersten Gruppenbilder. Ein Klick oeffnet die Untergruppen und die direkten Artikel dieser Gruppe. Ohne Gruppenauswahl zeigt der Katalog alle aktiven Artikel und kann weiterhin per Suche und Attributen gefiltert werden.</p>'
         . '</section>';
   }

   private function shippingGroupsHelpHtml(): string {
      return '<section class="dbx-shop-help">'
         . '<h2>Versandgruppen</h2>'
         . '<p>Versandgruppen definieren Versandweg, Lieferzeit, Standardkosten und Freigrenzen. Artikel koennen diese Werte aus der Gruppe uebernehmen oder eigene Versandwerte verwenden.</p>'
         . '<h3>Eingabefelder</h3>'
         . '<dl>'
         . '<dt>Key</dt><dd>Eindeutiger technischer Schluessel der Versandgruppe. Beispiele sind <code>digital-free</code>, <code>service-remote</code> oder <code>merch-package</code>. Der Key wird beim Neuanlegen vergeben und bleibt danach stabil.</dd>'
         . '<dt>Versandgruppe</dt><dd>Sichtbarer Name fuer Verwaltung und Auswahlfelder.</dd>'
         . '<dt>Beschreibung</dt><dd>Interne Erklaerung, fuer welche Artikel und Liefersituationen diese Versandgruppe gedacht ist.</dd>'
         . '<dt>Versandweg</dt><dd>Bezeichnung des Versand- oder Bereitstellungswegs, zum Beispiel Download, Remote-Termin, DHL Paket oder Spedition.</dd>'
         . '<dt>Lieferzeit</dt><dd>Text fuer die erwartete Bereitstellung oder Lieferzeit, zum Beispiel <code>Sofort nach Freischaltung</code> oder <code>2-4 Werktage</code>.</dd>'
         . '<dt>Kosten</dt><dd>Standard-Versandkosten brutto. Verwenden Sie <code>0</code> fuer kostenfreie Lieferung oder digitale Bereitstellung.</dd>'
         . '<dt>Frei ab</dt><dd>Brutto-Warenwert, ab dem die Versandkosten entfallen. Der Wert <code>-1</code> bedeutet: keine Freigrenze.</dd>'
         . '<dt>Sort</dt><dd>Reihenfolge in Listen und Auswahlfeldern. Kleine Zahlen stehen weiter oben.</dd>'
         . '<dt>Aktiv</dt><dd>Schaltet die Versandgruppe fuer die Nutzung ein oder aus. Inaktive Gruppen bleiben erhalten, werden aber nicht als aktive Option behandelt.</dd>'
         . '</dl>'
         . '<h3>Aktionen</h3>'
         . '<ul>'
         . '<li><strong>Plus in der Bar:</strong> legt oben eine neue Eingabezeile fuer eine Versandgruppe an.</li>'
         . '<li><strong>Speichern:</strong> uebernimmt Versandweg, Lieferzeit, Kosten, Freigrenze, Sortierung und Aktiv-Status.</li>'
         . '<li><strong>Loeschen:</strong> verschiebt die Versandgruppe in den Papierkorb und deaktiviert sie. Bestehende Produktdaten bleiben nachvollziehbar, die Gruppe erscheint aber nicht mehr in der aktiven Liste.</li>'
         . '</ul>'
         . '<p><strong>Praxis:</strong> Trennen Sie digitale Bereitstellung, Remote-Leistungen und physische Paketlieferungen in eigene Versandgruppen.</p>'
         . '</section>';
   }

   private function settingsHelpHtml(): string {
      return '<section class="dbx-shop-help">'
         . '<h2>Shop-Einstellungen</h2>'
         . '<p>Diese Seite verwaltet globale dbxShop-Einstellungen. Artikel, Artikelgruppen, Versandgruppen und Channels nutzen diese Werte als gemeinsame Grundlage. Besonders wichtig ist die MwSt.-Logik: Artikel speichern keine eigenen Prozentwerte mehr, sondern Artikelgruppen waehlen eine MwSt.-Klasse wie <code>mwst1</code> oder <code>mwst2</code>. Der tatsaechliche Prozentwert wird hier gepflegt.</p>'
         . '<h3>Shop</h3><dl>'
         . '<dt>Shop aktiv</dt><dd>Schaltet den Shop grundsaetzlich ein oder aus. Wenn der Shop spaeter gesperrt werden soll, kann diese Einstellung fuer Frontend-Pruefungen genutzt werden.</dd>'
         . '<dt>Standard-Channel</dt><dd>Technischer Standard-Verkaufskanal fuer normale Shop-Bestellungen. Fuer den eigenen Shop ist typischerweise <code>web</code> oder <code>shop</code> sinnvoll. Externe Plattformen wie eBay oder Amazon werden als eigene Channels gepflegt.</dd>'
         . '<dt>Standard-Waehrung</dt><dd>ISO-Waehrungscode fuer Preise und Bestellungen, z.B. <code>EUR</code>. Der Wert sollte aus drei Buchstaben bestehen.</dd>'
         . '<dt>Preisanzeige</dt><dd>Legt fest, ob Preise als Brutto- oder Netto-Anzeige interpretiert werden sollen. Fuer einen deutschen Verbraucher-Shop ist Brutto in der Regel die passende Anzeige. B2B-Szenarien koennen Netto verwenden, benoetigen aber klare Steuer- und Rechtstexte.</dd>'
         . '<dt>B2B-Modus</dt><dd>Vorbereitung fuer Geschaeftskundenlogik. Diese Einstellung dokumentiert die Absicht, z.B. spaeter Netto-Preise, USt-ID-Pruefung oder andere Checkout-Regeln zu aktivieren.</dd>'
         . '<dt>Lagerbestand nutzen</dt><dd>Aktiviert die Beruecksichtigung des Lagerbestands. Wenn aus, koennen Artikel unabhaengig vom Feld Lagerbestand angezeigt werden.</dd>'
         . '<dt>Channels nutzen</dt><dd>Globaler Schalter fuer externe Verkaufskanaele wie eBay, Amazon, Kleinanzeigen oder mobile.de. Wenn deaktiviert, werden Channel-Funktionen im Shop-Admin ausgeblendet bzw. nicht verwendet. Welche externen Channels konkret aktiv sind, wird in der Channel-Verwaltung gepflegt und in den Einstellungen als Statusuebersicht angezeigt.</dd>'
         . '</dl>'
         . '<h3>MwSt.</h3><dl>'
         . '<dt>Standard-MwSt.-Klasse</dt><dd>Standardklasse fuer neue Artikelgruppen oder Faelle ohne eindeutige Zuordnung. Ueblich ist <code>mwst1</code> fuer den regulaeren Satz.</dd>'
         . '<dt>MwSt.-Anzeige</dt><dd>Legt fest, ob bei Preisen im Shop der Hinweis mit dem MwSt.-Satz angezeigt wird. Wenn deaktiviert, bleibt die steuerliche Berechnung intern erhalten, der sichtbare Preistext zeigt aber nur noch die Versandinformation. Fuer Sonderfaelle wie Kleinunternehmerregelung muessen passende Rechtstexte gepflegt werden.</dd>'
         . '<dt>mwst1 - Name</dt><dd>Bezeichnung der ersten Steuerklasse, z.B. <code>MwSt. normal</code>. Diese Klasse wird normalerweise fuer den regulaeren deutschen Umsatzsteuersatz verwendet.</dd>'
         . '<dt>mwst1 - Prozent</dt><dd>Prozentwert der ersten Steuerklasse, aktuell typischerweise <code>19</code>. Wenn sich der gesetzliche Satz aendert, wird dieser Wert hier zentral angepasst.</dd>'
         . '<dt>mwst2 - Name</dt><dd>Bezeichnung der zweiten Steuerklasse, z.B. <code>MwSt. ermaessigt</code>. Diese Klasse ist fuer Artikel mit ermaessigtem Steuersatz gedacht.</dd>'
         . '<dt>mwst2 - Prozent</dt><dd>Prozentwert der zweiten Steuerklasse, aktuell typischerweise <code>7</code>.</dd>'
         . '<dt>mwst3 - Name</dt><dd>Dritte vorbereitete Steuerklasse. Sie kann fuer kuenftige Steuersaetze, Sonderfaelle oder Uebergangsregeln genutzt werden.</dd>'
         . '<dt>mwst3 - Prozent</dt><dd>Prozentwert der dritten Steuerklasse, z.B. vorbereitet mit <code>22</code>. Solange Artikelgruppen diese Klasse nicht verwenden, wirkt sie sich nicht auf Artikelpreise aus.</dd>'
         . '<dd><strong>Workflow:</strong> Prozentwerte hier pflegen, danach in <strong>Artikelgruppen</strong> nur die passende Klasse auswaehlen. Artikel erben den Steuersatz aus ihrer primaeren Artikelgruppe.</dd>'
         . '</dl>'
         . '<h3>Checkout und Rechtstexte</h3><dl>'
         . '<dt>Gastbestellung erlauben</dt><dd>Erlaubt Bestellungen ohne vorheriges Kundenkonto. Wenn deaktiviert, verlangt der Checkout ein angemeldetes Benutzerkonto.</dd>'
         . '<dt>Rechtstext-Snapshot speichern</dt><dd>Speichert bei Bestellungen den Stand von Rechtstexten und Widerrufsbelehrung als Snapshot. So bleibt nachvollziehbar, welche Texte zum Kaufzeitpunkt galten.</dd>'
         . '<dt>Widerruf anzeigen</dt><dd>Steuert, ob der Widerrufsbereich im Shop sichtbar bzw. verwendbar sein soll. Der eigentliche Widerrufstext kommt aus der CMS-Seite <code>/shop-widerruf</code>.</dd>'
         . '<dt>Kunden-Mail senden</dt><dd>Sendet nach Bestellung und Widerruf eine kurze Bestaetigung an die beim Checkout bzw. Widerruf angegebene E-Mail-Adresse.</dd>'
         . '<dt>Admin-Mail senden</dt><dd>Sendet bei neuen Bestellungen und Widerrufen eine interne Benachrichtigung an die Admin-E-Mail-Adresse.</dd>'
         . '<dt>Mail-Absender</dt><dd>Absenderadresse fuer Shop-Mails. Verwenden Sie eine echte Domain-Adresse, die in der Mail-Konfiguration erlaubt ist, z.B. <code>shop@example.org</code>.</dd>'
         . '<dt>Admin-E-Mail</dt><dd>Empfaengeradresse fuer interne Shop-Benachrichtigungen, z.B. <code>admin@example.org</code>.</dd>'
         . '</dl>'
         . '<h3>Zahlungsarten</h3><dl>'
         . '<dt>Vorkasse aktiv</dt><dd>Aktiviert Vorkasse per Bankueberweisung. Die Bestellung wird gespeichert, die Zahlung bleibt offen, bis der Zahlungseingang im Admin bestaetigt wird.</dd>'
         . '<dt>Kontoinhaber</dt><dd>Name des Zahlungsempfaengers fuer Vorkasse, z.B. Firmenname oder Shop-Betreiber.</dd>'
         . '<dt>IBAN</dt><dd>Bankverbindung fuer Vorkasse. Dieser Wert wird Kunden nach der Bestellung und in der Bestellmail als Zahlungsinformation angezeigt.</dd>'
         . '<dt>BIC</dt><dd>BIC/SWIFT der Bank. Innerhalb Deutschlands oft nicht zwingend, fuer internationale Kunden aber hilfreich.</dd>'
         . '<dt>Bank</dt><dd>Name der Bank fuer die Vorkasse-Zahlungsinformation.</dd>'
         . '<dt>Vorkasse-Hinweis</dt><dd>Text, der Kunden erklaert, wann und wie sie ueberweisen sollen. Die Bestellnummer wird zusaetzlich als Verwendungszweck angezeigt.</dd>'
         . '<dt>Rechnung aktiv</dt><dd>Aktiviert Rechnung als Zahlungsart. Das ist meist nur fuer bestimmte Kunden oder B2B sinnvoll und sollte bei Bedarf mit Freigabelogik verbunden werden.</dd>'
         . '<dt>Rechnungs-Hinweis</dt><dd>Text fuer Zahlungsziel oder interne Hinweise, z.B. <code>Bitte zahlen Sie innerhalb von 14 Tagen nach Rechnungserhalt.</code></dd>'
         . '<dt>PayPal aktiv</dt><dd>Schaltet PayPal im Checkout frei. PayPal funktioniert erst, wenn Modus, Client-ID und Client-Secret korrekt eingetragen sind.</dd>'
         . '<dt>PayPal Modus</dt><dd><code>Sandbox</code> nutzt die PayPal-Testumgebung. <code>Live</code> nutzt echte Zahlungen. Vor Livebetrieb immer zuerst Sandbox testen.</dd>'
         . '<dt>PayPal Brand Name</dt><dd>Name, der bei PayPal im Zahlungsfenster erscheinen soll, z.B. der Shop- oder Firmenname.</dd>'
         . '<dt>PayPal Client-ID</dt><dd>Oeffentliche App-Kennung aus dem PayPal Developer Dashboard. Fuer Sandbox und Live gibt es getrennte Zugangsdaten.</dd>'
         . '<dt>PayPal Client-Secret</dt><dd>Geheimer App-Schluessel aus dem PayPal Developer Dashboard. Dieser Wert darf nicht oeffentlich angezeigt oder weitergegeben werden.</dd>'
         . '<dd>PayPal-Zugangsdaten werden im PayPal Developer Dashboard unter <a href="https://developer.paypal.com/dashboard/applications/" target="_blank" rel="noopener">developer.paypal.com/dashboard/applications</a> erstellt und verwaltet. Sandbox-Testkonten und Testkaeufer finden Sie unter <a href="https://developer.paypal.com/tools/sandbox/" target="_blank" rel="noopener">developer.paypal.com/tools/sandbox</a>.</dd>'
         . '<dt>Payment testen</dt><dd>Der Stecker-Button in der Bar prueft PayPal per OAuth-Token gegen Sandbox oder Live. Amazon Pay prueft vollstaendige Zugangsdaten und die lokale RSA-PSS-Signatur. Eine echte Amazon-Pay-Autorisierung wird beim Checkout mit einer echten Checkout Session ausgefuehrt.</dd>'
         . '<dt>Amazon Pay aktiv</dt><dd>Schaltet Amazon Pay als auswaehlbare Zahlungsart frei. Beim Checkout wird eine Amazon-Pay-Checkout-Session erzeugt, der Kunde zu Amazon Pay weitergeleitet und die Zahlung nach Rueckkehr ueber Complete Checkout Session abgeschlossen.</dd>'
         . '<dt>Amazon Pay Modus</dt><dd><code>Sandbox</code> fuer Tests, <code>Live</code> fuer echte Zahlungen. Die Zugangsdaten muessen zur jeweiligen Umgebung passen.</dd>'
         . '<dt>Amazon Pay Region</dt><dd>Region des Amazon-Pay-Kontos. Fuer Deutschland typischerweise <code>EU</code>.</dd>'
         . '<dt>Amazon Merchant-ID</dt><dd>Haendlerkennung aus der Amazon-Pay-/Seller-Central-Konfiguration.</dd>'
         . '<dt>Amazon Store-ID</dt><dd>Store-/Checkout-Konfiguration aus Amazon Pay. Sie identifiziert, welche Store-Konfiguration fuer den Checkout genutzt werden soll.</dd>'
         . '<dt>Public-Key-ID</dt><dd>Kennung des oeffentlichen Schluessels, der bei Amazon Pay hinterlegt wurde.</dd>'
         . '<dt>Private Key</dt><dd>Privater Signaturschluessel fuer Amazon Pay. Dieser Wert ist geheim und darf nicht oeffentlich angezeigt werden.</dd>'
         . '<dt>Sandbox Simulation</dt><dd>Optionaler Amazon-Pay-Simulationscode fuer Sandbox-Tests, z.B. <code>AmazonCanceled</code>. Leer lassen fuer normale Sandbox-Tests ohne erzwungene Fehlersimulation.</dd>'
         . '<dd>Amazon-Pay-Zugangsdaten werden in Seller Central bzw. Amazon Pay Integration Central verwaltet: <a href="https://sellercentral.amazon.de/" target="_blank" rel="noopener">sellercentral.amazon.de</a>. Die technische API-v2-Dokumentation steht unter <a href="https://developer.amazon.com/docs/amazon-pay-api-v2/introduction.html" target="_blank" rel="noopener">developer.amazon.com/docs/amazon-pay-api-v2</a>.</dd>'
         . '</dl>'
         . '<h3>Channels</h3><dl>'
         . '<dt>Aktive externe Channels</dt><dd>Zeigt alle aktiven Channels ausser dem internen <code>shop</code>-Channel. Aktiv, Export, Order-Import und Teststatus kommen aus der Channel-Verwaltung.</dd>'
         . '<dt>Channels bearbeiten</dt><dd>Oeffnet die Channel-Verwaltung. Dort werden API-Zugangsdaten, Marketplace-, Seller-, Location- und Policy-Werte gepflegt.</dd>'
         . '</dl>'
         . '<h3>Versand</h3><dl>'
         . '<dt>Digitale Downloads aktiv</dt><dd>Erlaubt digitale Bereitstellung fuer passende Produkte. Digitale Produkte koennen andere Widerrufsregeln haben; die Rechtstexte muessen dazu passen.</dd>'
         . '<dt>Pauschalversand aktiv</dt><dd>Aktiviert einen einfachen globalen Versandkostenwert als Fallback. Detailliertere Versandlogik wird weiterhin ueber Versandgruppen gepflegt.</dd>'
         . '<dt>Pauschalversand brutto</dt><dd>Globaler Bruttobetrag fuer Versandkosten, wenn keine spezifischere Versandgruppe greift. Beispiel: <code>5.90</code>.</dd>'
         . '<dt>CMS-Media Slot</dt><dd>Technischer Slotname fuer Shop-Medien im CMS-Medienbrowser. Normalerweise <code>shop</code>. Nur aendern, wenn Shop-Bilder bewusst in einem anderen CMS-Slot verwaltet werden sollen.</dd>'
         . '</dl>'
         . '<h3>Speichern</h3>'
         . '<p>Nach dem Speichern werden die Werte in der Modul-Konfiguration <code>dbxShop</code> abgelegt. Aenderungen an MwSt.-Prozentwerten wirken sofort auf Artikel, die ihre MwSt. aus Artikelgruppen erben.</p>'
         . '</section>';
   }

   private function ordersHelpHtml(): string {
      return '<section class="dbx-shop-help">'
         . '<h2>Bestellungen</h2>'
         . '<p>Die Bestellverwaltung zeigt Shop-, PayPal- und Channel-Bestellungen als Snapshots. Eine Bestellung soll nicht aus aktuellen Artikeldaten rekonstruiert werden, sondern behält Artikel, Preise, MwSt., Versand und Zahlungsdaten aus dem Zeitpunkt der Bestellung.</p>'
         . '<h3>Liste</h3><dl>'
         . '<dt>Bestellung</dt><dd>Bestellnummer, Datum und Herkunft. Die Bestellnummer bleibt stabil und dient fuer Kundensuche, Zahlungsabgleich und Channel-Zuordnung.</dd>'
         . '<dt>Kunde</dt><dd>Name und E-Mail-Adresse, soweit beim Checkout oder beim Channel-Import vorhanden. Bei importierten Marktplatzbestellungen kann die Plattform je nach Datenschutz nur eingeschraenkte Kundendaten liefern.</dd>'
         . '<dt>Status</dt><dd>Interner Bearbeitungsstatus: neu, Zahlung offen, bezahlt, in Bearbeitung, versendet, abgeschlossen oder storniert. Dieser Status steuert die Arbeit im Shop-Admin.</dd>'
         . '<dt>Zahlung</dt><dd>Zahlungsanbieter, Zahlungsstatus und Referenz, z.B. PayPal-ID oder externe Channel-Bestellnummer.</dd>'
         . '<dt>Positionen</dt><dd>Artikelpositionen als Snapshot mit SKU, Titel und Menge.</dd>'
         . '<dt>Summe</dt><dd>Bruttosumme der Bestellung in der gespeicherten Waehrung.</dd>'
         . '</dl>'
         . '<h3>Filter</h3><dl>'
         . '<dt>Suche</dt><dd>Sucht nach Bestellnummer, Kunde, E-Mail, Channel und Zahlungsreferenz.</dd>'
         . '<dt>Status</dt><dd>Filtert nach internem Bearbeitungsstatus.</dd>'
         . '<dt>Zahlung</dt><dd>Filtert nach Zahlungsstatus.</dd>'
         . '<dt>Versand</dt><dd>Filtert nach operativem Versandstatus, zum Beispiel offen, versendet oder zugestellt.</dd>'
         . '<dt>Channel</dt><dd>Filtert nach Herkunft, z.B. Shop, eBay, Amazon oder mobile.de.</dd>'
         . '</dl>'
         . '<h3>Detail bearbeiten</h3><dl>'
         . '<dt>Schnellaktionen</dt><dd>Setzen haeufige Arbeitsschritte mit einem Klick: bezahlt, in Bearbeitung, versandbereit, versendet, zugestellt/abgeschlossen, storniert oder erstattet. Jede Aktion schreibt die passenden Statusfelder und einen Historieneintrag.</dd>'
         . '<dt>Kundenmail senden</dt><dd>Der Umschlag-Button in der Bar sendet den aktuell gespeicherten Status an die Kunden-E-Mail-Adresse. Speichern Sie geaenderte Felder zuerst. Voraussetzung ist ein Mail-Absender in den Shop-Einstellungen.</dd>'
         . '<dt>Status</dt><dd>Setzt den internen Bearbeitungsstand. Typischer Ablauf: neu -> bezahlt -> in Bearbeitung -> versendet -> abgeschlossen.</dd>'
         . '<dt>Zahlungsstatus</dt><dd>Dokumentiert den Stand der Zahlung. PayPal und Channel-Import koennen diesen Wert automatisch setzen; manuelle Korrektur ist fuer Abgleich und Sonderfaelle moeglich.</dd>'
         . '<dt>Zahlungsreferenz</dt><dd>Externe Referenz wie PayPal Order ID, eBay Order ID, Amazon Order ID oder interne Buchungsnummer.</dd>'
         . '<dt>Rechnungsnummer / Rechnungsdatum</dt><dd>Werden manuell gepflegt oder beim Wechsel in einen bezahlten Bearbeitungsstatus automatisch vorgeschlagen. Fuer echten Livebetrieb muss ein rechtssicherer Nummernkreis definiert werden.</dd>'
         . '<dt>Versandstatus</dt><dd>Operativer Lieferstatus: offen, bereit, versendet, zugestellt oder Retoure.</dd>'
         . '<dt>Versanddienstleister</dt><dd>Name des Dienstleisters oder der Versandart, z.B. DHL, UPS, Deutsche Post oder Download/Freischaltung.</dd>'
         . '<dt>Trackingnummer / Tracking-URL</dt><dd>Trackingdaten fuer physische Sendungen. Wenn DHL, UPS, DPD oder Hermes als Versanddienstleister eingetragen ist und die Tracking-URL leer bleibt, wird beim Speichern eine passende Tracking-URL erzeugt.</dd>'
         . '<dt>Versendet am</dt><dd>Zeitpunkt des Versands. Wird beim Wechsel auf <code>Versendet</code> automatisch gesetzt, wenn noch leer.</dd>'
         . '<dt>Admin-Notiz</dt><dd>Interne Notizen fuer Rueckfragen, Versandhinweise, Sonderabsprachen oder Channel-Probleme.</dd>'
         . '<dt>Rechnung anzeigen / PDF</dt><dd>Der Beleg-Button zeigt eine Rechnungsvorschau. Der PDF-Button erzeugt bei Bedarf Rechnungsnummer, Datum und PDF-Datei und archiviert den Dateipfad an der Bestellung.</dd>'
         . '<dt>Positionen</dt><dd>Nicht direkt editierbare Snapshot-Positionen. Aenderungen an Artikeln sollen alte Bestellungen nicht veraendern.</dd>'
         . '<dt>Payload</dt><dd>Rohdaten aus Zahlung oder Channel-Import. Diese helfen bei technischer Pruefung und Supportfaellen.</dd>'
         . '<dt>Historie</dt><dd>Protokolliert wichtige Status-, Zahlungs-, Versand-, Rechnungs- und Widerrufsereignisse.</dd>'
         . '<dt>Widerrufe</dt><dd>Zeigt eingegangene Widerrufe, die anhand der Bestellnummer zugeordnet wurden.</dd>'
         . '</dl>'
         . '<h3>Loeschen</h3>'
         . '<p>Loeschen verschiebt Bestellungen in den Papierkorb. Es loescht keine Artikel und keine Channel-Daten. Fuer echte kaufmaennische Loesch-/Aufbewahrungsregeln muss vor Livebetrieb entschieden werden, was archiviert werden muss.</p>'
         . '</section>';
   }

   private function productsHelpHtml(): string {
      return '<section class="dbx-shop-help">'
         . '<h2>Produkte und Artikel-Formular</h2>'
         . '<p>Das Produktformular pflegt die Stammdaten eines Artikels. Gruppen, Attribute, Bilder und Channel-Zuordnungen sind getrennte Bereiche, damit ein Artikel sauber im Shop angezeigt, verkauft und optional zu externen Plattformen exportiert werden kann.</p>'
         . '<h3>Produktliste</h3><dl>'
         . '<dt>Suche / Filter</dt><dd>Sucht nach Artikelnummer, Titel, Beschreibung, Gruppe, Channel und weiteren Produktwerten. Die Anzahl pro Seite steuert die Tabellenlaenge; <code>Alle</code> zeigt alle passenden Artikel.</dd>'
         . '<dt>Bearbeiten</dt><dd>Oeffnet das Artikel-Formular im Fenster.</dd>'
         . '<dt>Ansehen</dt><dd>Oeffnet die Shop-Ansicht des Artikels.</dd>'
         . '<dt>Loeschen</dt><dd>Verschiebt den Artikel nach Confirm in den Papierkorb.</dd>'
         . '<dt>Mehrfachaktion</dt><dd>Fuer markierte Artikel koennen Channel hinzugefuegt, entfernt, exportiert oder Produktgruppen gesetzt werden.</dd>'
         . '</dl>'
         . '<h3>Grunddaten</h3><dl>'
         . '<dt>Artikelnummer</dt><dd>Interne und externe Artikelnummer. Sie entspricht praktisch der SKU und sollte eindeutig, stabil und kanalgeeignet sein.</dd>'
         . '<dt>URL-Name</dt><dd>Technischer URL-Teil fuer die Shop-Detailseite. Verwenden Sie kurze, sprechende Kleinbuchstaben ohne Sonderzeichen.</dd>'
         . '<dt>Titel</dt><dd>Sichtbarer Produktname im Shop, in Listen und beim Export.</dd>'
         . '<dt>Kategorie</dt><dd>Freie Kategoriebezeichnung fuer Darstellung und Verwaltung.</dd>'
         . '<dt>Produkttyp</dt><dd>Unterscheidet digitale Produkte, physische Produkte und Dienstleistungen. Das wirkt auf Versand, Checkout und spaetere Channel-Logik.</dd>'
         . '<dt>Aktiv</dt><dd>Nur aktive Produkte werden regulaer angeboten.</dd>'
         . '<dt>Sortierung</dt><dd>Reihenfolge in Listen und Katalogen. Kleine Werte stehen weiter vorne.</dd>'
         . '</dl>'
         . '<h3>Beschreibung</h3><dl>'
         . '<dt>Kurzbeschreibung</dt><dd>Kompakter Text fuer Karten, Listen und Suchergebnisse.</dd>'
         . '<dt>Beschreibung</dt><dd>Ausfuehrlicher Produkttext fuer Detailseite und Channel-Export.</dd>'
         . '</dl>'
         . '<h3>Preis, Versand und Darstellung</h3><dl>'
         . '<dt>Bruttopreis</dt><dd>Artikelpreis inklusive MwSt. Die MwSt.-Prozentwerte kommen aus den Shop-Einstellungen und der Artikelgruppe.</dd>'
         . '<dt>Versand-Quelle</dt><dd><code>Aus Gruppe</code> nutzt die Versandgruppe. Individuelle Werte ueberschreiben die Gruppe fuer diesen Artikel.</dd>'
         . '<dt>Versand brutto individuell</dt><dd>Artikelbezogener Versandwert. <code>-1</code> bedeutet: aus Gruppe oder globalem Fallback berechnen.</dd>'
         . '<dt>Lagerbestand</dt><dd>Interner Bestand. Fuer digitale Produkte kann 0 trotzdem verkaufbar sein, wenn der Ablauf das erlaubt.</dd>'
         . '<dt>Badge</dt><dd>Kurzer Hervorhebungs-Text auf Karten, z.B. <code>Neu</code> oder <code>Business</code>.</dd>'
         . '<dt>Icon</dt><dd>Bootstrap-Icon-Klasse fuer fallbackartige Darstellung ohne Bild.</dd>'
         . '<dt>Logo-Variante</dt><dd>Optionaler Darstellungswert fuer Templates.</dd>'
         . '<dt>MwSt.-Quelle</dt><dd>Normalfall ist <code>Aus Gruppe</code>. Die Gruppe referenziert <code>mwst1</code>, <code>mwst2</code> oder <code>mwst3</code>; die echten Prozentwerte stehen in den Shop-Einstellungen.</dd>'
         . '</dl>'
         . '<h3>Bilder</h3><dl>'
         . '<dt>Auswahl</dt><dd>Oeffnet den CMS-Medienbrowser fuer Shop-Bilder. Dateien bleiben im Medienbrowser; im Shop wird nur die Zuordnung gespeichert.</dd>'
         . '<dt>Primaerbild</dt><dd>Markiert das neu zugeordnete Bild als Hauptbild.</dd>'
         . '<dt>Papierkorb am Bild</dt><dd>Hebt nur die Shop-Zuordnung auf. Die Mediendatei wird nicht geloescht.</dd>'
         . '</dl>'
         . '<h3>Channels</h3><dl>'
         . '<dt>Checkbox</dt><dd>Setzt eine direkte Artikel-Channel-Zuordnung. Diese hat Vorrang vor Channel-Gruppen.</dd>'
         . '<dt>Status</dt><dd>Zeigt, ob der Channel direkt aktiv, direkt deaktiviert, aus einer Gruppe geerbt oder nicht gesetzt ist.</dd>'
         . '<dt>Mapping</dt><dd>Oeffnet das produktbezogene Channel-Mapping fuer Kategorie, Pflichtattribute, externe IDs und provider-spezifische Werte.</dd>'
         . '<dt>Export</dt><dd>Startet den Export fuer diesen Artikel und Channel, wenn der Channel Export erlaubt und ausreichend konfiguriert ist.</dd>'
         . '</dl>'
         . '<h3>Bar-Aktionen</h3><dl>'
         . '<dt>Speichern</dt><dd>Speichert Stammdaten und direkte Channel-Auswahl.</dd>'
         . '<dt>Artikel ansehen</dt><dd>Oeffnet die Shop-Vorschau.</dd>'
         . '<dt>Artikelattribute</dt><dd>Oeffnet die Werte der gruppenspezifischen Attribute fuer diesen Artikel.</dd>'
         . '<dt>Loeschen</dt><dd>Loescht nach Confirm den Artikel.</dd>'
         . '<dt>Hilfe</dt><dd>Oeffnet diese CMS-Hilfeseite.</dd>'
         . '<dt>Produktliste</dt><dd>Zurueck zur Produktuebersicht.</dd>'
         . '</dl>'
         . '</section>';
   }

   private function productChannelMappingHelpHtml(): string {
      return '<section class="dbx-shop-help">'
         . '<h2>Channel-Mapping je Artikel</h2>'
         . '<p>Das Channel-Mapping speichert produktbezogene Werte fuer genau einen Artikel und genau einen Channel. Globale Zugangsdaten bleiben im Channel-Formular; hier stehen die Werte, die sich pro Artikel unterscheiden.</p>'
         . '<h3>Channel-Werte</h3><dl>'
         . '<dt>Aktiv</dt><dd>Schaltet die direkte Artikelzuordnung fuer diesen Channel ein. Diese Einstellung hat Vorrang vor Channel-Gruppen.</dd>'
         . '<dt>Channel-SKU</dt><dd>Artikelnummer, die fuer diesen Channel verwendet wird. Meist identisch mit der Shop-Artikelnummer; bei Plattformen kann sie aber abweichen.</dd>'
         . '<dt>Channel-Preis</dt><dd>Brutto-Preis, der fuer diesen Channel verwendet wird. Wenn kein eigener Channel-Preis gespeichert ist, zeigt das Feld den aktuellen Shop-Artikelpreis an. Wird dieser Wert unveraendert gespeichert, bleibt intern die Vererbung aktiv. Aendern Sie den Wert nur, wenn dieser Channel bewusst einen abweichenden Preis bekommen soll.</dd>'
         . '<dt>Channel-Versand</dt><dd>Versandwert, der fuer diesen Channel verwendet wird. Ohne eigenen Channel-Wert wird der wirksame Artikel-/Gruppenversand angezeigt und intern weiter geerbt. Ein abweichender Wert wird nur gespeichert, wenn Sie ihn hier bewusst aendern.</dd>'
         . '<dt>Listing-ID</dt><dd>Externe ID des sichtbaren Angebots auf der Plattform. Bei eBay entsteht sie normalerweise erst nach dem Publish des Offers. Dieses Feld wird im Normalfall vom Connector nach erfolgreichem Export/Rueckmeldung gefuellt. Manuell eintragen sollten Sie sie nur, wenn ein bereits extern vorhandenes Angebot mit diesem Shop-Artikel verknuepft oder eine Zuordnung repariert werden muss.</dd>'
         . '<dt>Offer-ID</dt><dd>Externe Angebots-/Offer-ID der Plattform. Bei eBay ist das Offer der Zwischenschritt zwischen Inventory Item/SKU und dem veroeffentlichten Listing. Die Offer-ID braucht der Connector spaeter zum Aktualisieren, Publishen, Beenden oder Pruefen genau dieses Offers. Bei neuen Artikeln bleibt das Feld leer; nach erfolgreicher Erstellung speichert der Connector die ID automatisch.</dd>'
         . '</dl>'
         . '<h3>Provider-Mapping</h3><dl>'
         . '<dt>Shop / Custom Aktion</dt><dd>Interner oder Middleware-Endpunkt, an den der Export uebergeben werden soll.</dd>'
         . '<dt>eBay Kategorie-ID</dt><dd>eBay Kategorie fuer das Angebot. Sie muss zum Produkt passen, sonst lehnt eBay Pflichtattribute oder das Listing ab.</dd>'
         . '<dt>eBay Location-Key</dt><dd>Merchant Location Key des eBay-Verkaeuferkontos. Dieser Wert kommt aus der eBay-Channel-Konfiguration und wird nicht pro Artikel gepflegt. Wenn sich Lager- oder Versandort aendert, wird der Wert in der Channel-Verwaltung geaendert und gilt dann fuer alle eBay-Mappings ohne eigene Sonderlogik.</dd>'
         . '<dt>eBay Zustand</dt><dd>Condition-Code oder Zustand des Artikels, z.B. neu oder gebraucht.</dd>'
         . '<dt>eBay Policies</dt><dd>Payment-, Fulfillment- und Return-Policy-IDs. Sie bestimmen Zahlung, Versand und Rueckgabe fuer genau dieses Listing.</dd>'
         . '<dt>eBay Aspekte</dt><dd>Pflicht- und Zusatzattribute als <code>key=value</code>, z.B. <code>Marke=dbxApp</code>. Ein Wert pro Zeile.</dd>'
         . '<dt>Amazon Product Type</dt><dd>Amazon Produkttyp fuer die Listings Items API, z.B. <code>PRODUCT</code> oder eine spezifische Kategorie.</dd>'
         . '<dt>Amazon Requirements</dt><dd>Steuert, welche Anforderungen Amazon beim Listing erwartet, z.B. <code>LISTING</code> oder <code>LISTING_PRODUCT_ONLY</code>.</dd>'
         . '<dt>Amazon Brand / Attribute</dt><dd>Marke und weitere Attribute als <code>key=value</code>. Welche Werte Pflicht sind, haengt vom Product Type ab.</dd>'
         . '<dt>mobile.de Fahrzeugdaten</dt><dd>mobile.de ist fuer Fahrzeuge gedacht. Marke, Modell, Erstzulassung, Kilometer, Kraftstoff und Leistung muessen zum Fahrzeug passen.</dd>'
         . '<dt>Kleinanzeigen</dt><dd>Offiziell nur ueber Partner-/Middleware-Schnittstelle sinnvoll. Kategorie, Ort, Kontakt und Attribute werden an die Middleware gegeben.</dd>'
         . '</dl>'
         . '<h3>Exportstatus</h3><dl>'
         . '<dt>Status</dt><dd>Letzter technischer Exportzustand, z.B. veroeffentlicht, bereit, manuell bereit oder Fehler.</dd>'
         . '<dt>Meldung</dt><dd>Antwort oder Fehlermeldung des Connectors.</dd>'
         . '<dt>Payload</dt><dd>Gespeicherte technische Antwortdaten zur Fehlersuche.</dd>'
         . '</dl>'
         . '<h3>Bar-Aktionen</h3><dl>'
         . '<dt>Speichern</dt><dd>Speichert Mapping und Channel-Werte.</dd>'
         . '<dt>Exportieren</dt><dd>Startet den Export dieses Artikels fuer diesen Channel.</dd>'
         . '<dt>Hilfe</dt><dd>Oeffnet diese CMS-Hilfeseite.</dd>'
         . '</dl>'
         . '</section>';
   }

   private function productAttributesHelpHtml(): string {
      return '<section class="dbx-shop-help">'
         . '<h2>Artikelattribute</h2>'
         . '<p>Artikelattribute beschreiben gruppenspezifische Merkmale wie Groesse, Farbe, Material oder technische Daten. Die Definition entsteht bei der Artikelgruppe; im Artikel werden nur die Werte gepflegt.</p>'
         . '<h3>Attribut-Definition</h3><dl>'
         . '<dt>Gruppe</dt><dd>Artikelgruppe, fuer die das Attribut gilt.</dd>'
         . '<dt>Key</dt><dd>Technischer Schluessel, z.B. <code>farbe</code>. Er sollte stabil bleiben.</dd>'
         . '<dt>Titel</dt><dd>Sichtbarer Name des Attributs.</dd>'
         . '<dt>Typ</dt><dd>Text, Zahl oder Auswahlliste.</dd>'
         . '<dt>Einheit</dt><dd>Optionale Einheit, z.B. cm, kg, Watt.</dd>'
         . '<dt>Optionen</dt><dd>Werte fuer Auswahllisten, getrennt durch <code>|</code>.</dd>'
         . '<dt>Pflicht</dt><dd>Markiert Werte, die fuer vollstaendige Produktdaten erwartet werden.</dd>'
         . '<dt>Filter</dt><dd>Kann spaeter fuer Katalogfilter verwendet werden.</dd>'
         . '<dt>Vergleich</dt><dd>Kann spaeter fuer Produktvergleiche verwendet werden.</dd>'
         . '<dt>Sort / Aktiv</dt><dd>Reihenfolge und Freigabe des Attributs.</dd>'
         . '</dl>'
         . '<h3>Artikelwert</h3><dl>'
         . '<dt>Wert</dt><dd>Konkreter Wert dieses Artikels fuer das Attribut.</dd>'
         . '<dt>Einheit</dt><dd>Wird aus der Definition angezeigt.</dd>'
         . '<dt>Eigenschaft</dt><dd>Zeigt Pflicht-/Filter-Hinweise aus der Definition.</dd>'
         . '</dl>'
         . '<h3>Aktionen</h3><dl>'
         . '<dt>Speichern</dt><dd>Speichert die Attributdefinition oder die Artikelwerte.</dd>'
         . '<dt>Zurueck</dt><dd>Fuehrt zur Produktliste zurueck.</dd>'
         . '<dt>Hilfe</dt><dd>Oeffnet diese CMS-Hilfeseite.</dd>'
         . '</dl>'
         . '</section>';
   }

   private function shopMediaHelpHtml(): string {
      return '<section class="dbx-shop-help">'
         . '<h2>Shop-Medien</h2>'
         . '<p>Shop-Medien ordnen vorhandene CMS-Medien Artikeln oder Artikelgruppen zu. Die Datei bleibt im CMS-Medienbrowser; dbxShop speichert nur die Verwendung im Shop.</p>'
         . '<h3>Felder</h3><dl>'
         . '<dt>Artikel</dt><dd>Optionaler einzelner Artikel, dem neue Bildzuordnungen hinzugefuegt werden.</dd>'
         . '<dt>Artikelgruppe</dt><dd>Optionale Gruppe. Gruppenbilder koennen bei Artikeln dieser Gruppe als Fallback erscheinen.</dd>'
         . '<dt>Sortierung</dt><dd>Reihenfolge der Bilder.</dd>'
         . '<dt>Primaerbild</dt><dd>Markiert die neue Zuordnung als Hauptbild.</dd>'
         . '</dl>'
         . '<h3>Aktionen</h3><dl>'
         . '<dt>Auswahl laden</dt><dd>Laedt die aktuell gewaehlt Artikel-/Gruppenauswahl neu.</dd>'
         . '<dt>Auswahl</dt><dd>Oeffnet den CMS-Medienbrowser fuer Upload, Auswahl, Zuschneiden, Resize, Batch und Wartung.</dd>'
         . '<dt>Zugeordnete Shop-Bilder</dt><dd>Zeigt vorhandene Artikel- und Gruppenbild-Zuordnungen.</dd>'
         . '<dt>Hilfe</dt><dd>Oeffnet diese CMS-Hilfeseite.</dd>'
         . '</dl>'
         . '</section>';
   }

   private function loadShopHelpContentCacheSupport(): void {
      $bootstrap = dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap.php';
      if (is_file($bootstrap)) {
         require_once $bootstrap;
      }
      $cacheFile = dirname(__DIR__, 2) . '/dbxContent/include/dbxContentPageCache.class.php';
      if (is_file($cacheFile)) {
         require_once $cacheFile;
      }
      $indexFile = dirname(__DIR__, 2) . '/dbxContent/include/dbxContentPermalinkIndex.class.php';
      if (is_file($indexFile)) {
         require_once $indexFile;
      }
   }

   private function invalidateShopHelpCache(int $cid): void {
      if ($cid <= 0) {
         return;
      }
      $this->loadShopHelpContentCacheSupport();
      if (class_exists('\\dbx\\dbxContent\\dbxContentPageCache')) {
         \dbx\dbxContent\dbxContentPageCache::invalidateContent($cid);
      }
   }

   private function syncShopHelpPermalinkIndex(int $cid, string $permalink, string $rights = 'admin'): void {
      if ($cid <= 0 || $permalink === '') {
         return;
      }
      $this->loadShopHelpContentCacheSupport();
      if (class_exists('\\dbx\\dbxContent\\dbxContentPermalinkIndex')) {
         \dbx\dbxContent\dbxContentPermalinkIndex::upsertPage($cid, $permalink, $rights, 1);
      }
   }

   private function removeShopHelpPermalinkIndex(string $permalink): void {
      $permalink = trim($permalink);
      if ($permalink === '') {
         return;
      }
      $this->loadShopHelpContentCacheSupport();
      if (class_exists('\\dbx\\dbxContent\\dbxContentPermalinkIndex')) {
         \dbx\dbxContent\dbxContentPermalinkIndex::removeByPermalink($permalink);
      }
   }

   private function ensureShopChannelHelpPage(): int {
      return $this->ensureShopAdminHelpPage('help-shop-channel-gruppen', 'Hilfe: Channel-Gruppen', 'Hilfe zur Pflege von Shop Channel-Gruppen.', 'shop,channel,channel-gruppen,hilfe', $this->channelGroupsHelpHtml(), '9010', array('shop/help-channel-groups', 'shop/channel'));
   }

   private function ensureShopChannelsHelpPage(): int {
      return $this->ensureShopAdminHelpPage('help-shop-channels', 'Hilfe: Channels', 'Hilfe zur Pflege von Shop Channels und Marktplatz-Anbindungen.', 'shop,channels,api,marktplatz,hilfe', $this->channelsHelpHtml(), '9015', array('shop/help-channels', 'shop/channels'));
   }

   private function ensureShopChannelProviderHelpPage(string $platform): int {
      $allowed = array('shop', 'amazon', 'ebay', 'kleinanzeigen', 'mobile', 'custom');
      if (!in_array($platform, $allowed, true)) {
         $platform = 'custom';
      }
      $titles = array(
         'shop' => 'Hilfe: Channel Shop',
         'amazon' => 'Hilfe: Channel Amazon',
         'ebay' => 'Hilfe: Channel eBay',
         'kleinanzeigen' => 'Hilfe: Channel Kleinanzeigen',
         'mobile' => 'Hilfe: Channel mobile.de',
         'custom' => 'Hilfe: Eigener Channel',
      );
      $sort = array('shop' => '9016', 'amazon' => '9017', 'ebay' => '9018', 'kleinanzeigen' => '9019', 'mobile' => '9024', 'custom' => '9025');
      return $this->ensureShopAdminHelpPage(
         'help-shop-channel-' . $platform,
         $titles[$platform],
         'Hilfe zu API-Daten und Rueckmeldungen fuer ' . $titles[$platform] . '.',
         'shop,channels,' . $platform . ',api,hilfe',
         $this->channelProviderHelpHtml($platform),
         $sort[$platform],
         array('shop/help-channel-' . $platform, 'shop/channels/' . $platform)
      );
   }

   private function ensureShopProductGroupsHelpPage(): int {
      return $this->ensureShopAdminHelpPage('help-shop-artikelgruppen', 'Hilfe: Artikelgruppen', 'Hilfe zur Pflege von Shop Artikelgruppen.', 'shop,artikelgruppen,produktgruppen,hilfe', $this->productGroupsHelpHtml(), '9020', array('shop/help-groups', 'shop/groups'));
   }

   private function ensureShopShippingGroupsHelpPage(): int {
      return $this->ensureShopAdminHelpPage('help-shop-versandgruppen', 'Hilfe: Versandgruppen', 'Hilfe zur Pflege von Shop Versandgruppen.', 'shop,versandgruppen,shipping,hilfe', $this->shippingGroupsHelpHtml(), '9030', array('shop/help-shipping-groups', 'shop/shipping-groups'));
   }

   private function ensureShopSettingsHelpPage(): int {
      return $this->ensureShopAdminHelpPage('help-shop-einstellungen', 'Hilfe: Shop-Einstellungen', 'Hilfe zur Pflege der globalen Shop-Einstellungen.', 'shop,einstellungen,mwst,zahlung,versand,hilfe', $this->settingsHelpHtml(), '9040', array('shop/help-settings', 'shop/settings'));
   }

   private function ensureShopOrdersHelpPage(): int {
      return $this->ensureShopAdminHelpPage('help-shop-bestellungen', 'Hilfe: Bestellungen', 'Hilfe zur Bearbeitung von Shop- und Channel-Bestellungen.', 'shop,bestellungen,orders,zahlung,channel,hilfe', $this->ordersHelpHtml(), '9050', array('shop/help-orders', 'shop/orders'));
   }

   private function ensureShopProductsHelpPage(): int {
      return $this->ensureShopAdminHelpPage('help-shop-produkte', 'Hilfe: Produkte', 'Hilfe zur Produktliste und zum Artikel-Formular.', 'shop,produkte,artikel,formular,hilfe', $this->productsHelpHtml(), '9005', array('shop/help-products', 'shop/products'));
   }

   private function ensureShopProductChannelMappingHelpPage(): int {
      return $this->ensureShopAdminHelpPage('help-shop-produkt-channel-mapping', 'Hilfe: Channel-Mapping je Artikel', 'Hilfe zur produktbezogenen Channel-Mapping-Maske.', 'shop,produkte,channels,mapping,hilfe', $this->productChannelMappingHelpHtml(), '9014', array('shop/help-product-channel-mapping', 'shop/product-channel-mapping'));
   }

   private function ensureShopProductAttributesHelpPage(): int {
      return $this->ensureShopAdminHelpPage('help-shop-artikelattribute', 'Hilfe: Artikelattribute', 'Hilfe zu Attributdefinitionen und Artikelwerten.', 'shop,produkte,attribute,hilfe', $this->productAttributesHelpHtml(), '9028', array('shop/help-product-attributes', 'shop/product-attributes'));
   }

   private function ensureShopMediaHelpPage(): int {
      return $this->ensureShopAdminHelpPage('help-shop-medien', 'Hilfe: Shop-Medien', 'Hilfe zur Medienzuordnung fuer Shop-Artikel und Artikelgruppen.', 'shop,medien,bilder,hilfe', $this->shopMediaHelpHtml(), '9035', array('shop/help-media', 'shop/media-help'));
   }

   private function ensureShopAdminHelpPage(string $permalink, string $title, string $description, string $keywords, string $content, string $sorter, array $oldPermalinks = array()): int {
      $db = $this->db();
      $contentDd = $this->contentDd();
      try {
         $permalinks = array_values(array_unique(array_merge(array($permalink), $oldPermalinks)));
         foreach ($permalinks as $candidatePermalink) {
            $candidatePermalink = trim((string)$candidatePermalink);
            if ($candidatePermalink === '') {
               continue;
            }
            $row = $db->select1($contentDd, array('permalink' => $candidatePermalink), 'id,content,permalink,title,description,keywords,sorter', 0);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
               $id = (int)$row['id'];
               if (!$this->maintenanceMode) {
                  return $id;
               }
               $needsUpdate = trim((string)($row['content'] ?? '')) !== trim($content)
                  || trim((string)($row['permalink'] ?? '')) !== $permalink
                  || trim((string)($row['title'] ?? '')) !== $title
                  || trim((string)($row['description'] ?? '')) !== $description
                  || trim((string)($row['keywords'] ?? '')) !== $keywords
                  || trim((string)($row['sorter'] ?? '')) !== $sorter
                  || $candidatePermalink !== $permalink;
               if ($needsUpdate) {
                  $this->invalidateShopHelpCache($id);
                  $db->update($contentDd, array(
                     'permalink' => $permalink,
                     'title' => $title,
                     'description' => $description,
                     'keywords' => $keywords,
                     'sorter' => $sorter,
                     'content' => $content,
                     'group_read' => 'admin',
                     'activ' => 1,
                     'addmenu' => 0,
                     'meta_robots' => 'noindex,nofollow',
                  ), $id);
               }
               foreach ($oldPermalinks as $oldPermalink) {
                  $this->removeShopHelpPermalinkIndex((string)$oldPermalink);
               }
               $this->syncShopHelpPermalinkIndex($id, $permalink, 'admin');
               return $id;
            }
         }

         if (!$this->maintenanceMode) {
            return 0;
         }

         $folderId = $this->ensureShopHelpFolder();
         $insert = array(
            'activ' => 1,
            'addmenu' => 0,
            'folder' => $folderId,
            'group_read' => 'admin',
            'sorter' => $sorter,
            'title' => $title,
            'permalink' => $permalink,
            'description' => $description,
            'keywords' => $keywords,
            'template' => 'c-body1-footer',
            'content' => $content,
            'meta_robots' => 'noindex,nofollow',
         );
         $ok = (int)$db->insert($contentDd, $insert);
         if ($ok === 1) {
            $id = (int)$db->get_insert_id();
            $this->invalidateShopHelpCache($id);
            $this->syncShopHelpPermalinkIndex($id, $permalink, 'admin');
            return $id;
         }
      } catch (\Throwable $e) {
         if (function_exists('dbx')) {
            dbx()->debug('dbxShop help page failed', $e->getMessage());
         }
      }

      return 0;
   }

   private function ensureShopMediaUsagePage(): int {
      $db = $this->db();
      $contentDd = $this->contentDd();
      try {
         $row = $db->select1($contentDd, array('permalink' => 'shop-medienverwendung'), 'id', 0);
         if (!is_array($row)) {
            $row = $db->select1($contentDd, array('permalink' => 'outside/shop-media-usage'), 'id', 0);
            if ($this->maintenanceMode && is_array($row) && (int)($row['id'] ?? 0) > 0) {
               $db->update($contentDd, array('permalink' => 'shop-medienverwendung'), (int)$row['id']);
            }
         }
         if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
            return (int)$row['id'];
         }

         if (!$this->maintenanceMode) {
            return 0;
         }

         $folderId = 0;
         $folder = $db->select1($this->folderDd(), array('name' => 'outside'), 'id', 0);
         if (is_array($folder)) {
            $folderId = (int)($folder['id'] ?? 0);
         }

         $insert = array(
            'activ' => 0,
            'addmenu' => 0,
            'folder' => $folderId,
            'group_read' => 'admin',
            'sorter' => '9999',
            'title' => 'Shop Medienverwendung',
            'permalink' => 'shop-medienverwendung',
            'description' => 'Interne Seite fuer Shop-Medienverwendung.',
            'keywords' => '',
            'template' => 'c-body1-footer',
            'content' => '<p>Interne Seite fuer Shop-Medienverwendung.</p>',
            'meta_robots' => 'noindex,nofollow',
         );
         $ok = (int)$db->insert($contentDd, $insert);
         if ($ok === 1) {
            $id = (int)$db->get_insert_id();
            return $id;
         }
      } catch (\Throwable $e) {
         if (function_exists('dbx')) {
            dbx()->debug('dbxShop media_usage page failed', $e->getMessage());
         }
      }

      return 0;
   }

   private function shopMediaUsageSlot(): string {
      $slot = strtolower(trim((string)dbx()->get_config('dbxShop', 'media_usage_slot')));
      $allowed = array('shop','hero','gallery','inline','header','teaser','footer');
      return in_array($slot, $allowed, true) ? $slot : 'shop';
   }

   private function shopMediaUsageSorter($db, int $contentId, string $slot): string {
      $where = 'content_id = ' . $contentId . " AND slot = '" . str_replace("'", "''", $slot) . "' AND active = 1";
      $rows = $db->select('dbxMediaUsage', $where, 'sorter,id', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int)($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }

   private function shopMediaFolderPath(): string {
      $base = dbx()->get_file_dir();
      return rtrim($base, '/\\') . '/media/img/shop';
   }

   private function normalizeShopSourceImagePath(string $path): string {
      $path = trim(str_replace('\\', '/', $path));
      $path = preg_replace('~^https?://[^/]+/~i', '', $path) ?: $path;
      $path = preg_replace('~^/?dbxapp/~i', '', $path) ?: $path;
      return ltrim($path, '/');
   }

   private function filePathForShopImage(string $path): string {
      $path = $this->normalizeShopSourceImagePath($path);
      if ($path === '' || strpos($path, '..') !== false || preg_match('~(^|/)\.~', $path)) {
         return '';
      }
      if (preg_match('~^files/(.+)$~i', $path, $m)) {
         return rtrim((string)dbx()->get_file_dir(), '/\\') . '/' . $m[1];
      }
      if (preg_match('~^(media|shop)/~i', $path)) {
         return rtrim((string)dbx()->get_file_dir(), '/\\') . '/' . $path;
      }
      return '';
   }

   private function mediaMime(string $file): string {
      $mime = function_exists('mime_content_type') ? (string)@mime_content_type($file) : '';
      if ($mime !== '') {
         return $mime;
      }

      $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
      $map = array(
         'jpg' => 'image/jpeg',
         'jpeg' => 'image/jpeg',
         'png' => 'image/png',
         'gif' => 'image/gif',
         'webp' => 'image/webp',
         'svg' => 'image/svg+xml',
      );
      return $map[$ext] ?? 'application/octet-stream';
   }

   private function ensureMediaRecordForShopImage(array $image): int {
      $mediaId = (int)($image['media_id'] ?? 0);
      if ($mediaId > 0) {
         return $mediaId;
      }

      $sourcePath = $this->normalizeShopSourceImagePath((string)($image['image_path'] ?? ''));
      if ($sourcePath === '' || stripos($sourcePath, 'dbxmedia:') === 0) {
         return 0;
      }

      $sourceFile = $this->filePathForShopImage($sourcePath);
      if ($sourceFile === '' || !is_file($sourceFile) || !is_readable($sourceFile)) {
         return 0;
      }

      $name = basename($sourceFile);
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name) ?: ('shop-image-' . (int)($image['id'] ?? 0));
      $targetDir = $this->shopMediaFolderPath();
      if (!is_dir($targetDir)) {
         @mkdir($targetDir, 0775, true);
      }
      $targetFile = rtrim($targetDir, '/\\') . '/' . $name;
      if (!is_file($targetFile)) {
         @copy($sourceFile, $targetFile);
      }
      if (!is_file($targetFile)) {
         return 0;
      }

      $rel = 'media/img/shop/' . $name;
      $db = $this->db();
      $existing = $db->select1('dbxMedia', array('file_path' => $rel), 'id,active', 0);
      if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
         $mediaId = (int)$existing['id'];
         if ((int)($existing['active'] ?? 0) !== 1) {
            $db->update('dbxMedia', array('active' => 1), $mediaId);
         }
         $this->repo()->updateImageMediaReference((int)($image['id'] ?? 0), $mediaId, 'dbxmedia:' . $mediaId);
         return $mediaId;
      }

      $width = 0;
      $height = 0;
      $size = @getimagesize($targetFile);
      if (is_array($size)) {
         $width = (int)($size[0] ?? 0);
         $height = (int)($size[1] ?? 0);
      }
      $title = trim((string)($image['title'] ?? ''));
      if ($title === '') {
         $title = pathinfo($name, PATHINFO_FILENAME);
      }
      $mime = $this->mediaMime($targetFile);
      $insert = array(
         'active' => 1,
         'content_id' => 0,
         'folder_id' => 0,
         'slot' => 'shop',
         'usage' => 'shop',
         'sorter' => '',
         'template' => '',
         'title' => $title,
         'alt' => (string)($image['alt'] ?? $title),
         'caption' => '',
         'file_name' => $name,
         'file_path' => $rel,
         'mime' => $mime,
         'size' => (int)@filesize($targetFile),
         'width' => $width,
         'height' => $height,
         'tags' => 'shop',
         'media_type' => 'image',
         'storage_type' => 'local',
         'media_folder' => 'img/shop',
      );
      $ok = (int)$db->insert('dbxMedia', $insert);
      if ($ok !== 1) {
         return 0;
      }
      $mediaId = (int)$db->get_insert_id();
      if ($mediaId <= 0) {
         return 0;
      }
      if ($mediaId > 0) {
         $this->repo()->updateImageMediaReference((int)($image['id'] ?? 0), $mediaId, 'dbxmedia:' . $mediaId);
      }
      return $mediaId;
   }

   private function migrateExistingShopImagesToMedia(): void {
      foreach ($this->repo()->allImages() as $image) {
         if ((int)($image['active'] ?? 0) !== 1) {
            continue;
         }
         $this->ensureMediaRecordForShopImage($image);
      }
   }

   private function syncShopMediaUsage(): void {
      $db = $this->db();
      $contentId = $this->shopMediaUsageContentId();
      if ($contentId <= 0) {
         return;
      }

      $slot = $this->shopMediaUsageSlot();
      $sourceNeedle = '%"source":"dbxShop"%';
      try {
         $this->migrateExistingShopImagesToMedia();

         $db->update(
            'dbxMediaUsage',
            array('active' => 0),
            "content_id = " . $contentId . " AND settings LIKE '" . str_replace("'", "''", $sourceNeedle) . "' AND active = 1",
            0,
            1,
            1,
            0
         );

         $byMedia = array();
         foreach ($this->repo()->allImages() as $image) {
            if ((int)($image['active'] ?? 0) !== 1) {
               continue;
            }
            $mediaId = (int)($image['media_id'] ?? 0);
            if ($mediaId <= 0) {
               continue;
            }
            if (!isset($byMedia[$mediaId])) {
               $byMedia[$mediaId] = array(
                  'media_id' => $mediaId,
                  'title' => (string)($image['title'] ?? ''),
                  'product_ids' => array(),
                  'group_ids' => array(),
               );
            }
            $productId = (int)($image['product_id'] ?? 0);
            $groupId = (int)($image['group_id'] ?? 0);
            if ($productId > 0) {
               $byMedia[$mediaId]['product_ids'][$productId] = $productId;
            }
            if ($groupId > 0) {
               $byMedia[$mediaId]['group_ids'][$groupId] = $groupId;
            }
         }

         foreach ($byMedia as $mediaId => $info) {
            $settings = json_encode(array(
               'source' => 'dbxShop',
               'product_ids' => array_values($info['product_ids']),
               'group_ids' => array_values($info['group_ids']),
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $db->insert('dbxMediaUsage', array(
               'active' => 1,
               'media_id' => (int)$mediaId,
               'content_id' => $contentId,
               'folder_id' => 0,
               'slot' => $slot,
               'sorter' => $this->shopMediaUsageSorter($db, $contentId, $slot),
               'template' => 'image-gallery',
               'caption' => (string)($info['title'] ?? ''),
               'settings' => $settings ?: '{"source":"dbxShop"}',
            ));
         }
      } catch (\Throwable $e) {
         if (function_exists('dbx')) {
            dbx()->debug('dbxShop media_usage sync failed', $e->getMessage());
         }
      }
   }

   /**
    * Provisioniert Shop-Hilfen und Medienreferenzen bewusst nur im
    * administrativen Wartungslauf.
    */
   private function maintainShopAdminContent(): void {
      $this->ensureCmsShopMediaFolder();
      $this->ensureShopMediaUsagePage();
      $this->ensureShopChannelHelpPage();
      $this->ensureShopChannelsHelpPage();
      foreach (array('shop', 'amazon', 'ebay', 'kleinanzeigen', 'mobile', 'custom') as $platform) {
         $this->ensureShopChannelProviderHelpPage($platform);
      }
      $this->ensureShopProductGroupsHelpPage();
      $this->ensureShopShippingGroupsHelpPage();
      $this->ensureShopSettingsHelpPage();
      $this->ensureShopOrdersHelpPage();
      $this->ensureShopProductsHelpPage();
      $this->ensureShopProductChannelMappingHelpPage();
      $this->ensureShopProductAttributesHelpPage();
      $this->ensureShopMediaHelpPage();
      $this->syncShopMediaUsage();
   }

   private function chips(
      array $items,
      string $key = 'title',
      string $class = '',
      string $emptyLabel = 'keine Werte'
   ): string {
      $html = '';
      foreach ($items as $item) {
         $value = trim((string)($item[$key] ?? ''));
         if ($value === '') continue;
         $html .= '<span class="badge text-bg-light border">' . $this->h($value) . '</span>';
      }
      if ($html === '') {
         return '<span class="text-muted small">' . $this->h($emptyLabel) . '</span>';
      }
      $class = trim('dbx-shop-report-chip-grid ' . $class);
      return '<div class="' . $this->h($class) . '">' . $html . '</div>';
   }

   private function attributeBadges(
      array $product,
      string $emptyLabel = 'keine Werte'
   ): string {
      $html = '';
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
         if ($value === '') continue;
         $html .= '<span class="badge text-bg-info text-start text-wrap">' . $this->h($attribute['title'] ?? '') . ': ' . $this->h($value) . '</span>';
      }
      return $html !== ''
         ? '<div class="dbx-shop-report-chip-grid dbx-shop-report-chip-grid-attributes">' . $html . '</div>'
         : '<span class="text-muted small">' . $this->h($emptyLabel) . '</span>';
   }

   private function attributeOptions(string $options): array {
      $items = preg_split('~[|;\r\n]+~', $options) ?: array();
      return array_values(array_filter(array_map('trim', $items), fn($item) => $item !== ''));
   }

   private function optionsHtml(array $options, string $selected): string {
      $html = '';
      foreach ($options as $value => $label) {
         $value = (string)$value;
         $html .= '<option value="' . $this->h($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . $this->h($label) . '</option>';
      }
      return $html;
   }

   private function shopConfig(): array {
      $cfg = dbx()->get_config('dbxShop');
      return is_array($cfg) ? $cfg : array();
   }

   private function channelsEnabled(): bool {
      $value = strtolower(trim((string)dbx()->get_config('dbxShop', 'channels_enabled')));
      return !in_array($value, array('0', 'false', 'off', 'no', 'nein'), true);
   }

   private function taxRatesConfig(): array {
      $cfg = $this->shopConfig();
      $rates = $cfg['tax_rates'] ?? array();
      if (!is_array($rates) || !count($rates)) {
         $rates = array(
            'mwst1' => array('title' => 'MwSt. normal', 'rate' => '19'),
            'mwst2' => array('title' => 'MwSt. ermaessigt', 'rate' => '7'),
            'mwst3' => array('title' => 'MwSt. vorbereitet', 'rate' => '22'),
         );
      }
      foreach (array('mwst1' => 'MwSt. normal', 'mwst2' => 'MwSt. ermaessigt', 'mwst3' => 'MwSt. vorbereitet') as $key => $title) {
         if (!isset($rates[$key]) || !is_array($rates[$key])) {
            $rates[$key] = array('title' => $title, 'rate' => $key === 'mwst2' ? '7' : ($key === 'mwst3' ? '22' : '19'));
         }
      }
      return $rates;
   }

   private function taxClassOptions(string $selected): string {
      $options = array();
      foreach ($this->taxRatesConfig() as $key => $rate) {
         if (!is_array($rate)) continue;
         $label = trim((string)($rate['title'] ?? $key));
         $value = number_format((float)($rate['rate'] ?? 0), 2, ',', '.');
         $options[$key] = $label . ' (' . $value . '%)';
      }
      return $this->optionsHtml($options, $selected !== '' ? $selected : (string)($this->shopConfig()['default_tax_class'] ?? 'mwst1'));
   }

   private function normalizedText(string $value): string {
      $value = strtolower($value);
      $value = strtr($value, array('ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'));
      $value = preg_replace('~[^a-z0-9]+~', ' ', $value) ?: '';
      return preg_replace('~\\s+~', ' ', trim($value)) ?: '';
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

   private function productAttributeText(array $product): string {
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

   private function productGroupText(array $product): string {
      $parts = array();
      foreach (($product['groups'] ?? array()) as $group) {
         $parts[] = (string)($group['title'] ?? '');
         $parts[] = (string)($group['group_key'] ?? '');
         $parts[] = (string)($group['description'] ?? '');
         $parts[] = (string)($group['attribute_notes'] ?? '');
      }
      foreach (($product['shipping_groups'] ?? array()) as $group) {
         $parts[] = (string)($group['title'] ?? '');
         $parts[] = (string)($group['group_key'] ?? '');
      }
      foreach (($product['channel_groups'] ?? array()) as $group) {
         $parts[] = (string)($group['title'] ?? '');
         $parts[] = (string)($group['group_key'] ?? '');
      }
      foreach (($product['channels'] ?? array()) as $channel) {
         $parts[] = (string)($channel['title'] ?? '');
         $parts[] = (string)($channel['channel_key'] ?? '');
      }
      return implode(' ', $parts);
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
      $attributes = $this->normalizedText($this->productAttributeText($product));
      $groups = $this->normalizedText($this->productGroupText($product));

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

   private function productSortValue(array $product, string $sort) {
      switch ($sort) {
         case 'sku':
         case 'title':
            return $this->normalizedText((string)($product[$sort] ?? ''));
         case 'price_gross':
         case 'effective_tax_rate':
         case 'effective_shipping_gross':
            return (float)($product[$sort] ?? 0);
         case 'active':
            return (int)($product['active'] ?? 0);
         case 'sorter':
         default:
            return (int)($product['sorter'] ?? 100);
      }
   }

   private function sortProductsForReport(array $products, string $query, string $sort, string $direction): array {
      $hasQuery = $this->searchTerms($query) !== array();
      $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
      usort($products, function(array $a, array $b) use ($hasQuery, $sort, $direction): int {
         if ($hasQuery && (int)($a['_search_score'] ?? 0) !== (int)($b['_search_score'] ?? 0)) {
            return (int)($b['_search_score'] ?? 0) <=> (int)($a['_search_score'] ?? 0);
         }

         $av = $this->productSortValue($a, $sort);
         $bv = $this->productSortValue($b, $sort);
         if ($av == $bv) {
            return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
         }
         $cmp = is_numeric($av) && is_numeric($bv) ? ($av <=> $bv) : strcasecmp((string)$av, (string)$bv);
         return $direction === 'DESC' ? -$cmp : $cmp;
      });
      return $products;
   }

   public function product_report_next_record($report, $record) {
      if (!is_array($record)) {
         return $record;
      }

      $sku = (string)($record['sku'] ?? '');
      $productId = (int)($record['id'] ?? 0);
      $productTitle = trim((string)($record['title'] ?? $sku));
      $summary = trim((string)($record['summary'] ?? ''));
      $image = $record['images'][0] ?? array();
      $imgUrl = is_array($image) ? $this->mediaItemUrl($image, true) : '';
      $emptyLabel = $report->get_fd_message('no_values');

      $record['image_view'] = $imgUrl !== ''
         ? '<span class="dbx-shop-report-image"><img src="' . $this->h($imgUrl) . '" alt="" loading="lazy"></span>'
         : '<span class="text-muted small">' . $this->h($report->get_fd_message('no_image')) . '</span>';
      $record['article_view'] = '<div class="dbx-shop-report-article-scroll"><code class="dbx-shop-report-sku">' . $this->h($sku) . '</code>'
         . '<br><strong>' . $this->h($productTitle) . '</strong>'
         . ($summary !== '' ? '<br><small class="text-muted">' . $this->h($summary) . '</small>' : '')
         . '</div>';
      $record['groups_view'] = $this->chips($record['groups'] ?? array(), 'title', '', $emptyLabel);
      $record['attributes_view'] = $this->attributeBadges($record, $emptyLabel);
      $record['shipping_groups_view'] = $this->chips($record['shipping_groups'] ?? array(), 'title', '', $emptyLabel);
      $record['channel_groups_view'] = $this->chips($record['channel_groups'] ?? array(), 'title', '', $emptyLabel);
      $record['channels_view'] = $this->chips($record['channels'] ?? array(), 'title', 'dbx-shop-report-chip-grid-channels', $emptyLabel);
      $record['price_view'] = '<span class="text-nowrap">' . $this->money($record['price_gross'] ?? 0) . '</span>';
      $record['tax_view'] = number_format((float)($record['effective_tax_rate'] ?? 0), 2, ',', '.') . '%';
      $record['shipping_view'] = '<span class="text-nowrap">' . $this->money($record['effective_shipping_gross'] ?? 0) . '</span>';
      $record['status_view'] = ((int)($record['active'] ?? 0) === 1)
         ? '<span class="badge text-bg-success">' . $this->h($report->get_fd_message('status_active')) . '</span>'
         : '<span class="badge text-bg-secondary">' . $this->h($report->get_fd_message('status_inactive')) . '</span>';

      return $record;
   }

   public function product_report_row_action_data($report, $data) {
      if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
         return $data;
      }

      $type = (string)($data['type'] ?? '');
      $record = is_array($data['record'] ?? null) ? $data['record'] : array();
      $rid = (int)($data['data']['rid'] ?? $record['id'] ?? 0);
      $sku = (string)($record['sku'] ?? '');
      $title = trim((string)($record['title'] ?? $sku));

      if ($type === 'edit') {
         $url = '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $rid;
         $data['data']['action'] = $url;
         $data['data']['class'] = 'openWin dbx-win';
         $data['data']['tooltip'] = $report->format_fd_message(
            'action_edit',
            array('title' => $title)
         );
      } elseif ($type === 'show') {
         $url = '?dbx_modul=dbxShop&dbx_run1=product&sku=' . rawurlencode($sku);
         $data['data']['action'] = $url;
         $data['data']['class'] = 'openWin dbx-win';
         $data['data']['tooltip'] = $report->format_fd_message(
            'action_view',
            array('title' => $title)
         );
      } elseif ($type === 'delete') {
         $data['data']['action'] = '?dbx_modul=dbxShop_admin&dbx_run1=products';
         $data['data']['confirm'] = $report->format_fd_message(
            'action_delete_confirm',
            array('title' => $title)
         );
      }

      return $data;
   }

   private function productTreePanel(array $products, $texts): string {
      $groups = $this->repo()->groups();
      $groupsByParent = array();
      foreach ($groups as $group) {
         $parentId = (int)($group['parent_id'] ?? 0);
         $groupsByParent[$parentId][] = $group;
      }

      $productsByGroup = array();
      foreach ($products as $product) {
         $groupId = (int)($product['product_group_id'] ?? 0);
         if ($groupId <= 0 && isset($product['groups'][0])) {
            $groupId = (int)($product['groups'][0]['id'] ?? 0);
         }
         $productsByGroup[$groupId][] = $product;
      }

      $renderProducts = function(int $groupId, bool $asListItem = false) use (&$productsByGroup, $texts): string {
         $items = '';
         foreach (($productsByGroup[$groupId] ?? array()) as $product) {
            $id = (int)($product['id'] ?? 0);
            $title = trim((string)($product['title'] ?? $texts->get_fd_message('tree_product_fallback')));
            $sku = trim((string)($product['sku'] ?? ''));
            if ($id <= 0) continue;
            $searchText = trim($title . ' ' . $sku);
            $items .= '<li class="dbx-shop-tree-product" draggable="true" data-shop-tree-node="product" data-shop-tree-product="' . $id . '" data-shop-tree-search-text="' . $this->h($searchText) . '">';
            $items .= '<span class="dbx-shop-tree-product-main"><i class="bi bi-box-seam"></i><span><strong>' . $this->h($title) . '</strong>' . ($sku !== '' ? '<small>' . $this->h($sku) . '</small>' : '') . '</span></span>';
            $items .= '<a class="btn btn-outline-primary btn-sm openWin dbx-win" href="?dbx_modul=dbxShop_admin&amp;dbx_run1=product_edit&amp;id=' . $id . '" title="' . $this->h($texts->get_fd_message('tree_edit_product')) . '"><i class="bi bi-pencil"></i></a>';
            $items .= '</li>';
         }
         if ($items === '') {
            return '';
         }
         $html = '<ul class="dbx-shop-tree-products">' . $items . '</ul>';
         return $asListItem ? '<li class="dbx-shop-tree-product-list">' . $html . '</li>' : $html;
      };

      $renderGroup = function(array $group) use (&$renderGroup, &$groupsByParent, $renderProducts, $texts): string {
         $id = (int)($group['id'] ?? 0);
         if ($id <= 0) return '';
         $title = trim((string)($group['title'] ?? $texts->get_fd_message('tree_group_fallback')));
         $childHtml = '';
         foreach (($groupsByParent[$id] ?? array()) as $child) {
            $childHtml .= $renderGroup($child);
         }
         $productsHtml = $renderProducts($id, true);
         $countChildren = count($groupsByParent[$id] ?? array());
         $countProducts = substr_count($productsHtml, 'data-shop-tree-node="product"');
         $hasChildren = $childHtml !== '' || $productsHtml !== '';
         $html = '<li class="dbx-shop-tree-group" data-shop-tree-group-wrap data-shop-tree-search-text="' . $this->h($title) . '">';
         $html .= '<div class="dbx-shop-tree-group-head" draggable="true" data-shop-tree-node="group" data-shop-tree-group="' . $id . '" data-shop-tree-drop="' . $id . '">';
         $html .= '<span class="dbx-shop-tree-group-main">';
         if ($hasChildren) {
            $html .= '<button type="button" class="dbx-shop-tree-group-toggle" data-shop-tree-group-toggle title="' . $this->h($texts->get_fd_message('tree_toggle_group')) . '" aria-label="' . $this->h($texts->get_fd_message('tree_toggle_group')) . '" aria-expanded="true"><i class="bi bi-chevron-down"></i></button>';
         } else {
            $html .= '<span class="dbx-shop-tree-toggle-spacer"></span>';
         }
         $html .= '<i class="bi bi-folder2"></i><span><strong>' . $this->h($title) . '</strong><small>' . $this->h($texts->format_fd_message('tree_counts', array('groups' => $countChildren, 'products' => $countProducts))) . '</small></span></span>';
         $html .= '<a class="btn btn-outline-secondary btn-sm openWin dbx-win" href="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-url="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-title="' . $this->h($texts->get_fd_message('tree_edit_groups')) . '" data-width="54%" data-height="84%" title="' . $this->h($texts->get_fd_message('tree_edit_groups')) . '"><i class="bi bi-diagram-3"></i></a>';
         $html .= '</div>';
         if ($hasChildren) {
            $html .= '<ul class="dbx-shop-tree-children">' . $childHtml . $productsHtml . '</ul>';
         }
         $html .= '</li>';
         return $html;
      };

      $rootGroups = '';
      foreach (($groupsByParent[0] ?? array()) as $group) {
         $rootGroups .= $renderGroup($group);
      }
      $ungrouped = $renderProducts(0, false);
      $search = $this->tpl()->get_tpl('dbx|search', dbx()->search_defaults(array(
         'name' => 'shop_tree_search',
         'placeholder' => $texts->get_fd_message('tree_search_placeholder'),
         'title' => $texts->get_fd_message('tree_search_title'),
         'wrap_class' => 'dbx-shop-tree-search-wrap',
         'extra_attrs' => 'data-shop-tree-search',
         'i' => 1,
      )));
      $treeMoveUrl = str_replace('&', '&amp;', $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=product_tree_move'));
      $html = '<section class="dbx-shop-product-tree-panel" data-shop-tree-panel data-shop-tree-moveurl="' . $treeMoveUrl . '" aria-label="' . $this->h($texts->get_fd_message('tree_aria')) . '">';
      $html .= '<div class="dbx-shop-product-tree-head"><div><h3>' . $this->h($texts->get_fd_message('tree_title')) . '</h3><p>' . $this->h($texts->get_fd_message('tree_subtitle')) . '</p></div><div class="dbx-shop-product-tree-actions"><a class="btn btn-outline-primary btn-sm openWin dbx-win" href="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-url="?dbx_modul=dbxShop_admin&amp;dbx_run1=groups" data-title="' . $this->h($texts->get_fd_message('tree_edit_groups')) . '" data-width="54%" data-height="84%"><i class="bi bi-diagram-3"></i> ' . $this->h($texts->get_fd_message('tree_edit_groups_button')) . '</a><button type="button" class="btn btn-outline-secondary btn-sm" data-shop-tree-close title="' . $this->h($texts->get_fd_message('tree_close')) . '" aria-label="' . $this->h($texts->get_fd_message('tree_close')) . '"><i class="bi bi-x-lg"></i></button></div></div>';
      $html .= '<div class="dbx-shop-tree-tools">' . $search . '</div>';
      $html .= '<ul class="dbx-shop-tree-list">' . $rootGroups . '</ul>';
      if ($ungrouped !== '') {
         $html .= '<div class="dbx-shop-tree-ungrouped"><strong>' . $this->h($texts->get_fd_message('tree_ungrouped')) . '</strong>' . $ungrouped . '</div>';
      }
      $html .= '</section>';
      return $html;
   }

   private function productTreeToggleButton($texts): string {
      $label = $this->h($texts->get_fd_message('tree_open'));
      return '<button type="button" class="btn btn-outline-secondary btn-sm dbx-shop-product-tree-toggle" data-dbx="lib=shopAdmin" data-shop-tree-toggle title="' . $label . '" aria-label="' . $label . '" aria-expanded="false"><i class="bi bi-diagram-3"></i></button>';
   }

   private function selectedProductIds($report): array {
      $ids = array();
      foreach (array_keys($report->get_multi_selects()) as $id) {
         $id = (int)$id;
         if ($id > 0) {
            $ids[$id] = $id;
         }
      }
      return array_values($ids);
   }

   private function productReportActionControls(string $baseAction, $texts): string {
      $channels = '<option value="">' . $this->h($texts->get_fd_message('bulk_channel_placeholder')) . '</option>';
      foreach ($this->repo()->channels() as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key === '') {
            continue;
         }
         $channels .= '<option value="' . $this->h($key) . '">' . $this->h($channel['title'] ?? $key) . '</option>';
      }

      $groups = '<option value="0">' . $this->h($texts->get_fd_message('bulk_group_placeholder')) . '</option>';
      foreach ($this->repo()->groups() as $group) {
         $id = (int)($group['id'] ?? 0);
         if ($id <= 0) {
            continue;
         }
         $groups .= '<option value="' . $id . '">' . $this->h($group['title'] ?? '') . '</option>';
      }

      $url = function(string $do, array $params = array()) use ($baseAction): string {
         $query = $baseAction . '&dbx_do=' . rawurlencode($do);
         foreach ($params as $key => $value) {
            $query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
         }
         return $this->h($query);
      };

      $actions = '<option value="">' . $this->h($texts->get_fd_message('bulk_action_placeholder')) . '</option>'
         . '<option value="shop_products_delete">' . $this->h($texts->get_fd_message('bulk_delete')) . '</option>'
         . '<option value="shop_products_channel_add">' . $this->h($texts->get_fd_message('bulk_channel_add')) . '</option>'
         . '<option value="shop_products_channel_remove">' . $this->h($texts->get_fd_message('bulk_channel_remove')) . '</option>'
         . '<option value="shop_products_channel_export">' . $this->h($texts->get_fd_message('bulk_channel_export')) . '</option>'
         . '<option value="shop_products_group_set">' . $this->h($texts->get_fd_message('bulk_group_set')) . '</option>';

      return '<div class="dbx-shop-products-bulk-actions">'
         . '<select class="form-select form-select-sm" name="dbx_products_bulk_action" title="' . $this->h($texts->get_fd_message('bulk_title')) . '">' . $actions . '</select>'
         . '<select class="form-select form-select-sm" name="dbx_action_channel" title="' . $this->h($texts->get_fd_message('bulk_channel_title')) . '">' . $channels . '</select>'
         . '<select class="form-select form-select-sm" name="dbx_action_group" title="' . $this->h($texts->get_fd_message('bulk_group_title')) . '">' . $groups . '</select>'
         . '<a class="btn btn-primary btn-sm dbxAjaxFormAction dbxConfirm" href="' . $url('shop_products_apply') . '" data-confirm-title="<i class=\'bi bi-lightning-fill\'></i> ' . $this->h($texts->get_fd_message('bulk_confirm_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('bulk_confirm_question')) . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('bulk_confirm_hint')) . '</small>" data-confirm-buttons="yesno" role="button"><i class="bi bi-check2-circle"></i> ' . $this->h($texts->get_fd_message('bulk_execute')) . '</a>'
         . '</div>';
   }

   private function handleProductReportAction($report): void {
      $do = (string)dbx()->get_modul_var('dbx_do', '', 'parameter');
      $mutatingActions = array(
         'row_delete',
         'shop_products_apply',
         'shop_products_delete',
         'shop_products_channel_add',
         'shop_products_channel_remove',
         'shop_products_channel_export',
         'shop_products_group_set',
      );
      // row_delete ist bereits als dbxReport-Standardaktion zentral geprueft.
      // Die Shop-spezifischen Sammelaktionen behalten ihren fachlichen Scope.
      if ($do !== 'row_delete'
          && in_array($do, $mutatingActions, true)
          && !$this->checkActionToken('product_report_action')) {
         $report->_msg_error = $report->get_fd_message('token_error');
         return;
      }

      $rid = (int)dbx()->get_modul_var('rid', 0, 'int');
      if ($do === 'row_delete') {
         if ($rid <= 0) {
            $report->_msg_error = $report->get_fd_message('product_delete_error');
            return;
         }
         $count = $this->repo()->deleteProducts(array($rid));
         $report->del_multi_select($rid);
         $report->_msg_success = $count === 1
            ? $report->get_fd_message('product_delete_success')
            : '';
         $report->_msg_error = $count === 1
            ? ''
            : $report->get_fd_message('product_delete_error');
         return;
      }

      if ($do === 'shop_products_apply') {
         $do = (string)dbx()->get_modul_var('dbx_products_bulk_action', '', 'parameter');
      }

      if (!in_array($do, array('shop_products_delete', 'shop_products_channel_add', 'shop_products_channel_remove', 'shop_products_channel_export', 'shop_products_group_set'), true)) {
         if ((string)dbx()->get_modul_var('dbx_do', '', 'parameter') === 'shop_products_apply') {
            $report->_msg_error = $report->get_fd_message('choose_action');
         }
         return;
      }

      $ids = $this->selectedProductIds($report);
      if ($ids === array()) {
         $report->_msg_error = $report->get_fd_message('select_products');
         return;
      }

      if ($do === 'shop_products_delete') {
         $count = $this->repo()->deleteProducts($ids);
         foreach ($ids as $id) {
            $report->del_multi_select($id);
         }
         $report->_msg_success = $count === 1
            ? $report->get_fd_message('multi_deleted_one')
            : $report->format_fd_message(
               'multi_deleted_many',
               array('count' => $count)
            );
         return;
      }

      if ($do === 'shop_products_channel_add') {
         $channel = trim((string)dbx()->get_modul_var('dbx_action_channel', '', 'parameter'));
         if ($channel === '') {
            $report->_msg_error = $report->get_fd_message('choose_channel');
            return;
         }
         $count = $this->repo()->addChannelToProducts($ids, $channel);
         $report->_msg_success = $report->format_fd_message(
            'channel_added',
            array('count' => $count, 'channel' => $channel)
         );
         return;
      }

      if ($do === 'shop_products_channel_remove') {
         $channel = trim((string)dbx()->get_modul_var('dbx_action_channel', '', 'parameter'));
         if ($channel === '') {
            $report->_msg_error = $report->get_fd_message('choose_channel');
            return;
         }
         $count = $this->repo()->removeChannelFromProducts($ids, $channel);
         $report->_msg_success = $report->format_fd_message(
            'channel_removed',
            array('count' => $count, 'channel' => $channel)
         );
         return;
      }

      if ($do === 'shop_products_channel_export') {
         $channel = trim((string)dbx()->get_modul_var('dbx_action_channel', '', 'parameter'));
         if ($channel === '') {
            $report->_msg_error = $report->get_fd_message('choose_channel');
            return;
         }
         $summary = $this->repo()->exportProductsToChannel($ids, $channel);
         $report->_msg_success = $report->format_fd_message(
            'export_summary',
            array(
               'ok' => (int)($summary['ok'] ?? 0),
               'failed' => (int)($summary['failed'] ?? 0),
            )
         );
         if (!empty($summary['messages'])) {
            $report->_msg_info = implode('<br>', array_map(fn($msg) => $this->h($msg), array_slice((array)$summary['messages'], 0, 8)));
         }
         return;
      }

      if ($do === 'shop_products_group_set') {
         $groupId = (int)dbx()->get_modul_var('dbx_action_group', 0, 'int');
         if ($groupId <= 0) {
            $report->_msg_error = $report->get_fd_message('choose_group');
            return;
         }
         $count = $this->repo()->setProductGroupForProducts($ids, $groupId);
         $report->_msg_success = $report->format_fd_message(
            'group_set',
            array('count' => $count)
         );
      }
   }

   private function cardTemplateOptions(string $selected): string {
      return $this->optionsHtml(array(
         'product-card-default' => 'Standardkarte',
         'product-card-compact' => 'Kompaktkarte',
      ), $selected);
   }

   private function detailTemplateOptions(string $selected): string {
      return $this->optionsHtml(array(
         'product-detail-default' => 'Standarddetail',
         'product-detail-technical' => 'Technische Ansicht',
      ), $selected);
   }

   private function galleryTemplateOptions(string $selected): string {
      return $this->optionsHtml(array(
         'image-gallery' => 'Bild Gallery',
         'file-gallery' => 'Datei Gallery',
      ), $selected);
   }

   private function galleryOverflowOptions(string $selected): string {
      return $this->optionsHtml(array(
         'grid' => 'Grid',
         'slider' => 'Slider',
         'scroll' => 'Scroll',
         'laufband' => 'Laufband',
         'tutorial' => 'Tutorial',
      ), $selected);
   }

   private function galleryClickOptions(string $selected): string {
      return $this->optionsHtml(array(
         'lightbox' => 'Lightbox',
         'none' => 'Kein Klick',
         'newtab' => 'Neuer Tab',
         'viewerjs' => 'ViewerJS',
         'photoswipe' => 'PhotoSwipe',
      ), $selected);
   }

   private function shopAdminStyle(): string {
      $file = dirname(__DIR__) . '/design/css/shop-admin.css';
      if (!is_file($file)) {
         return '';
      }
      return '<style>' . file_get_contents($file) . '</style>';
   }

   private function frame(string $content, string $title = 'Shop Administration', string $barActions = ''): string {
      if ($this->postedFormError !== '') {
         $content = '<div class="alert alert-danger mx-3 mt-3 mb-0" role="alert">'
            . $this->h($this->postedFormError)
            . '</div>'
            . $content;
         $this->postedFormError = '';
      }

      return $this->tpl()->get_tpl('dbxShop_admin|admin-shell', array(
         'shop_admin_style' => $this->shopAdminStyle(),
         'bar_title' => $this->h($title),
         'bar_icon' => 'bi-bag-check',
         'bar_subtitle' => $this->h($this->catalogTexts()->get_fd_message('admin_subtitle')),
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_middle' => '',
         'bar_extra' => '',
         'bar_actions' => $barActions,
         'content' => $content,
      ));
   }

   private function productBarActions($texts = null): string {
      $title = $texts && method_exists($texts, 'get_fd_message')
         ? $texts->get_fd_message('new_product_title', 'Neuen Artikel anlegen')
         : 'Neuen Artikel anlegen';
      $label = $texts && method_exists($texts, 'get_fd_message')
         ? $texts->get_fd_message('new_product', 'Neuer Artikel')
         : 'Neuer Artikel';
      $help = $texts && method_exists($texts, 'get_fd_message')
         ? $texts->get_fd_message('products_help', 'Hilfe: Produkte')
         : 'Hilfe: Produkte';

      return '<a class="btn btn-outline-primary btn-sm me-1" href="?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=0" title="' . $this->h($title) . '"><i class="bi bi-plus-square"></i><span class="visually-hidden"> ' . $this->h($label) . '</span></a>'
         . $this->helpButton($this->ensureShopProductsHelpPage(), $help);
   }

   private function productShellActions($texts = null): string {
      return $this->productBarActions($texts);
   }

   private function productFormDefaults(int $id): array {
      $now = date('Y-m-d H:i:s');
      $uid = (int)dbx()->user();
      $defaults = array(
         'update_date' => $now,
         'update_uid' => $uid,
      );
      if ($id <= 0) {
         $defaults['create_date'] = $now;
         $defaults['create_uid'] = $uid;
         $defaults['owner'] = $uid;
         $defaults['trash'] = 0;
         $defaults['currency'] = 'EUR';
      }
      return $defaults;
   }

   private function newProductDefaults(): array {
      return array(
         'sku' => '',
         'slug' => '',
         'title' => '',
         'category' => 'Merchandise',
         'product_type' => 'physical',
         'summary' => '',
         'description' => '',
         'price_gross' => '0.00',
         'currency' => 'EUR',
         'tax_mode' => 'group',
         'tax_rate' => '-1',
         'shipping_mode' => 'group',
         'shipping_gross' => '-1',
         'stock' => '0',
         'active' => 1,
         'sorter' => 100,
         'badge' => '',
         'image_icon' => 'bi-box-seam',
         'logo_variant' => '',
      );
   }

   private function applyProductPreset(array $data): array {
      $preset = trim((string)dbx()->get_modul_var('workflow_preset', '', 'parameter'));
      if ($preset === 'shop_article_publish') {
         $data['category'] = $data['category'] ?: 'Merchandise';
         $data['product_type'] = $data['product_type'] ?: 'physical';
         $data['active'] = 1;
      }

      $map = array(
         'preset_sku' => 'sku',
         'preset_slug' => 'slug',
         'preset_title' => 'title',
         'preset_category' => 'category',
         'preset_product_type' => 'product_type',
         'preset_summary' => 'summary',
         'preset_price_gross' => 'price_gross',
         'preset_group_id' => 'product_group_id',
      );
      foreach ($map as $param => $field) {
         $value = trim((string)dbx()->get_modul_var($param, '', '*'));
         if ($value !== '') {
            $data[$field] = $value;
         }
      }

      return $data;
   }

   private function productFormActions(int $id, $texts): string {
      $html = $this->helpButton(
         $this->ensureShopProductsHelpPage(),
         $texts->get_fd_message('help_edit'),
         'btn btn-outline-secondary btn-sm ms-1'
      )
         . '<button class="btn btn-primary btn-sm" type="submit" title="' . $this->h($texts->get_fd_message('save_title')) . '"><i class="bi bi-save"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('save_label')) . '</span></button>';

      if ($id > 0) {
         $product = $this->repo()->productById($id);
         $previewUrl = '?dbx_modul=dbxShop&dbx_run1=product&sku=' . rawurlencode((string)($product['sku'] ?? ''));
         $deleteUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=products&dbx_do=row_delete&rid=' . $id);
         $html .= $this->openWinButton(
            $previewUrl,
            $texts->get_fd_message('view_product'),
            '<i class="bi bi-search"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('preview')) . '</span>',
            'btn btn-outline-primary btn-sm ms-1',
            '82%',
            '82%'
         );
         $html .= $this->openWinButton(
            '?dbx_modul=dbxShop_admin&dbx_run1=product_attributes&id=' . $id,
            $texts->get_fd_message('product_attributes'),
            '<i class="bi bi-sliders"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('attributes')) . '</span>',
            'btn btn-outline-primary btn-sm ms-1',
            '76%',
            '78%'
         );
         $html .= '<a class="btn btn-outline-danger btn-sm ms-1 dbxConfirm" href="' . $this->h($deleteUrl) . '" data-confirm-title="' . $this->h($texts->get_fd_message('delete_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('delete_confirm')) . '" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('delete_title')) . '"><i class="bi bi-trash"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('delete_label')) . '</span></a>';
      }

      $html .= '<a class="btn btn-outline-secondary btn-sm ms-1" href="?dbx_modul=dbxShop_admin&dbx_run1=products" title="' . $this->h($texts->get_fd_message('product_list_title')) . '"><i class="bi bi-table"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('product_list')) . '</span></a>';

      return $html;
   }

   private function productGroupOptions(int $excludeId = 0, bool $withNone = false, $texts = null): array {
      $groups = $this->repo()->groups();
      $byParent = array();
      foreach ($groups as $group) {
         $parentId = (int)($group['parent_id'] ?? 0);
         $byParent[$parentId][] = $group;
      }

      $texts = $texts ?: $this->catalogTexts();
      $options = $withNone ? array('0' => $texts->get_fd_message('groups_no_parent')) : array();
      $walk = function(int $parentId, string $prefix) use (&$walk, &$options, $byParent, $excludeId): void {
         foreach (($byParent[$parentId] ?? array()) as $group) {
            $id = (int)($group['id'] ?? 0);
            if ($id <= 0 || $id === $excludeId) {
               continue;
            }
            $title = trim((string)($group['title'] ?? ''));
            if ($title === '') {
               $title = (string)($group['group_key'] ?? $id);
            }
            $label = $prefix !== '' ? $prefix . ' / ' . $title : $title;
            $options[(string)$id] = $label;
            $walk($id, $label);
         }
      };
      $walk(0, '');
      return $options;
   }

   private function shopMediaConfig(): array {
      return array(
         'media' => $this->cmsEndpoint('cms_media', array('images' => 1, 'media_type' => 'image'), true),
         'uploadmediafolder' => 'img/shop',
         'upload' => $this->cmsEndpoint('cms_upload', array(), true),
         'externalvideo' => $this->cmsEndpoint('cms_external_video', array(), true),
         'mediafolders' => $this->cmsEndpoint('cms_media_folders'),
         'mediafoldercreate' => $this->cmsEndpoint('cms_media_folder_create', array(), true),
         'mediafolderdelete' => $this->cmsEndpoint('cms_media_folder_delete', array(), true),
         'mediafolderrename' => $this->cmsEndpoint('cms_media_folder_rename', array(), true),
         'mediamove' => $this->cmsEndpoint('cms_media_move', array(), true),
         'mediaunused' => $this->cmsEndpoint('cms_media_unused'),
         'mediaprocess' => $this->cmsEndpoint('cms_media_process', array(), true),
         'deletemedia' => $this->cmsEndpoint('cms_delete_media', array(), true),
         'editmedia' => $this->cmsEndpoint('cms_edit_media', array(), true),
         'assignurl' => $this->shopEndpoint('assign_media', array(), true),
      );
   }

   private function shopMediaAttrs(array $mediaCfg): string {
      $attrs = ' data-dbx="lib=shopAdmin"';
      foreach ($mediaCfg as $key => $value) {
         $attrs .= ' data-shop-' . $this->h($key) . '="' . $this->h($value) . '"';
      }
      return $attrs;
   }

   /**
    * Rendert die durch dbxForm geschuetzten Medienbrowser-Formulare.
    *
    * Shop und CMS verwenden dieselben Upload-Endpunkte und deshalb bewusst
    * dieselben stabilen Formular-IDs. Der Rueckgabewert muss ausserhalb einer
    * bereits offenen dbxForm eingefuegt werden: Die inerten DOM-Templates
    * enthalten eigene Formulare und duerfen die umgebende Kartenform nicht
    * verschachteln oder vorzeitig schliessen.
    */
   private function shopMediaFormTemplates(array $mediaCfg): string {
      return dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin')->renderTemplates(
         (string)($mediaCfg['upload'] ?? ''),
         'cms-media-upload',
         (string)($mediaCfg['externalvideo'] ?? ''),
         'cms-external-video'
      );
   }

   private function productImagesPanel(array $product, bool $isNew, $texts): string {
      if ($isNew) {
         return '<aside class="border rounded bg-light p-3"><h6 class="mb-3">'
            . $this->h($texts->get_fd_message('images_title'))
            . '</h6><div class="alert alert-info mb-0">'
            . $this->h($texts->get_fd_message('save_first_images'))
            . '</div></aside>';
      }

      $productId = (int)($product['id'] ?? 0);
      $mediaCfg = $this->shopMediaConfig();
      $html = '<aside class="border rounded bg-light p-3 dbx-shop-media-manager dbx-shop-product-image-panel"' . $this->shopMediaAttrs($mediaCfg) . '>';
      $html .= '<input type="hidden" value="' . $productId . '" data-shop-product-select>';
      $html .= '<input type="hidden" value="0" data-shop-group-select>';
      $html .= '<input type="hidden" value="100" data-shop-sorter>';
      $html .= '<div class="d-flex justify-content-between align-items-center gap-2 mb-3">';
      $html .= '<h6 class="mb-0">' . $this->h($texts->get_fd_message('images_title')) . '</h6>';
      $html .= '<button type="button" class="btn btn-outline-primary btn-sm dbx-shop-media-pick" data-shop-media-folder="img/shop" title="' . $this->h($texts->get_fd_message('select_images_title')) . '"><i class="bi bi-images"></i><i class="bi bi-camera-video"></i><i class="bi bi-upload"></i><span>' . $this->h($texts->get_fd_message('selection')) . '</span></button>';
      $html .= '</div>';
      $html .= '<label class="form-check mb-3"><input class="form-check-input" type="checkbox" value="1" data-shop-primary> <span class="form-check-label">' . $this->h($texts->get_fd_message('new_primary')) . '</span></label>';

      $images = (array)($product['images'] ?? array());
      if ($images === array()) {
         $html .= '<div class="text-muted small">' . $this->h($texts->get_fd_message('no_images')) . '</div>';
      } else {
         $html .= '<div class="dbx-shop-image-list">';
         foreach ($images as $image) {
            $imageId = (int)($image['id'] ?? 0);
            $source = (int)($image['product_id'] ?? 0) === $productId
               ? $texts->get_fd_message('image_source_product')
               : $texts->get_fd_message('image_source_group');
            $primary = (int)($image['is_primary'] ?? 0) === 1
               ? '<span class="badge text-bg-primary ms-1">' . $this->h($texts->get_fd_message('primary')) . '</span>'
               : '';
            $title = trim((string)($image['title'] ?? ''));
            if ($title === '') {
               $title = basename((string)($image['image_path'] ?? 'Bild'));
            }
            $removeUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $productId . '&remove_image=' . $imageId);
            $html .= '<figure class="dbx-shop-image-card">';
            if ($imageId > 0) {
               $html .= '<a class="btn btn-outline-danger btn-sm dbxAjax dbxConfirm dbx-shop-image-unassign" href="' . $this->h($removeUrl) . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($texts->get_fd_message('unlink_image_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('unlink_image_question')) . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('unlink_image_hint')) . '</small>" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('unlink_image_title')) . '"><i class="bi bi-trash"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('unlink_image_label')) . '</span></a>';
            }
            $html .= '<img src="' . $this->h($this->mediaItemUrl($image, true)) . '" alt="' . $this->h($image['alt'] ?? $title) . '">';
            $html .= '<figcaption><strong>' . $this->h($title) . '</strong><br><span class="text-muted">' . $this->h($source) . '</span>' . $primary . '</figcaption>';
            $html .= '</figure>';
         }
         $html .= '</div>';
      }

      $html .= '<div class="form-text mt-3">' . $this->h($texts->get_fd_message('media_hint')) . '</div>';
      $html .= '</aside>';
      return $html;
   }

   private function productGroupImagePanel(array $group, bool $isNew, $texts = null): string {
      $texts = $texts ?: $this->catalogTexts();
      if ($isNew) {
         return '<div class="dbx-shop-group-image-panel dbx-shop-group-image-empty"><div class="form-text">' . $this->h($texts->get_fd_message('group_image_save_first')) . '</div></div>';
      }

      $groupId = (int)($group['id'] ?? 0);
      if ($groupId <= 0) {
         return '';
      }

      $mediaCfg = $this->shopMediaConfig();
      $image = $this->repo()->primaryImageForGroup($groupId);
      $html = '<div class="dbx-shop-media-manager dbx-shop-group-image-panel"' . $this->shopMediaAttrs($mediaCfg) . '>';
      $html .= '<input type="hidden" value="0" data-shop-product-select>';
      $html .= '<input type="hidden" value="' . $groupId . '" data-shop-group-select>';
      $html .= '<input type="hidden" value="10" data-shop-sorter>';
      $html .= '<input type="hidden" value="1" data-shop-primary>';
      $html .= '<div class="dbx-shop-group-image-head">';
      $html .= '<span>' . $this->h($texts->get_fd_message('group_image_title')) . '</span>';
      $html .= '<button type="button" class="btn btn-outline-primary btn-sm dbx-shop-media-pick" data-shop-media-folder="img/shop" title="' . $this->h($texts->get_fd_message('group_image_select_title')) . '"><i class="bi bi-images"></i><span>' . $this->h($texts->get_fd_message('selection')) . '</span></button>';
      $html .= '</div>';
      if (is_array($image)) {
         $title = trim((string)($image['title'] ?? ''));
         if ($title === '') {
            $title = basename((string)($image['image_path'] ?? 'Gruppenbild'));
         }
         $html .= '<figure class="dbx-shop-group-image-preview"><img src="' . $this->h($this->mediaItemUrl($image, true)) . '" alt="' . $this->h($image['alt'] ?? $title) . '"><figcaption>' . $this->h($title) . '</figcaption></figure>';
      } else {
         $html .= '<div class="dbx-shop-group-image-placeholder"><i class="bi bi-image"></i><span>' . $this->h($texts->get_fd_message('group_image_none')) . '</span></div>';
      }
      $html .= '<div class="form-text">' . $this->h($texts->get_fd_message('group_image_hint')) . '</div>';
      $html .= '</div>';
      return $html;
   }

   private function productChannelsPanel(array $product, bool $isNew, $texts): string {
      if (!$this->channelsEnabled()) {
         return '';
      }
      if ($isNew) {
         return '<aside class="border rounded bg-light p-3 mt-3"><h6 class="mb-3">'
            . $this->h($texts->get_fd_message('channels_title'))
            . '</h6><div class="alert alert-info mb-0">'
            . $this->h($texts->get_fd_message('save_first_channels'))
            . '</div></aside>';
      }

      $productId = (int)($product['id'] ?? 0);
      $overrides = $this->repo()->productChannelOverrides($productId);
      $inherited = $this->repo()->inheritedChannelsForProduct($productId);
      $html = '<aside class="border rounded bg-light p-3 mt-3 dbx-shop-product-channel-panel">';
      $html .= '<h6 class="mb-2">' . $this->h($texts->get_fd_message('channels_title')) . '</h6>';
      $html .= '<p class="form-text mb-3">' . $this->h($texts->get_fd_message('channels_info')) . '</p>';
      $html .= '<input type="hidden" name="product_channel_editor" value="1">';

      $channels = $this->repo()->channels();
      if ($channels === array()) {
         $html .= '<div class="text-muted small">' . $this->h($texts->get_fd_message('no_channels')) . '</div>';
      } else {
         $html .= '<div class="table-responsive dbx-shop-product-channel-table-wrap">';
         $html .= '<table class="table table-sm align-middle mb-0 dbx-shop-product-channel-table">';
         $html .= '<thead><tr><th>' . $this->h($texts->get_fd_message('table_channel')) . '</th><th>' . $this->h($texts->get_fd_message('table_status')) . '</th><th>' . $this->h($texts->get_fd_message('table_export')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('table_action')) . '</th></tr></thead><tbody>';
         foreach ($channels as $channel) {
            $key = trim((string)($channel['channel_key'] ?? ''));
            if ($key === '') {
               continue;
            }

            $hasOverride = isset($overrides[$key]);
            $isInherited = isset($inherited[$key]);
            $checked = $hasOverride
               ? (int)($overrides[$key]['active'] ?? 0) === 1
               : $isInherited;
            $source = $hasOverride
               ? ((int)($overrides[$key]['active'] ?? 0) === 1
                  ? $texts->get_fd_message('channel_direct_active')
                  : $texts->get_fd_message('channel_direct_inactive'))
               : ($isInherited
                  ? $texts->format_fd_message(
                     'channel_from_group_title',
                     array('groups' => implode(', ', array_values($inherited[$key]['group_titles'] ?? array())))
                  )
                  : $texts->get_fd_message('channel_not_set'));
            $sourceText = (!$hasOverride && $isInherited)
               ? $texts->get_fd_message('channel_from_group')
               : $source;
            $statusClass = $checked ? 'text-bg-success' : 'text-bg-secondary';
            $export = $overrides[$key] ?? array();
            $exportStatus = trim((string)($export['export_status'] ?? ''));
            $exportMessage = trim((string)($export['export_message'] ?? ''));
            $listingId = trim((string)($export['external_listing_id'] ?? ''));
            $exportBadgeClass = match ($exportStatus) {
               'published', 'exported', 'ready', 'manual_ready' => 'text-bg-info',
               'failed' => 'text-bg-danger',
               default => 'text-bg-light text-dark',
            };
            $exportText = $exportStatus !== ''
               ? $exportStatus
               : $texts->get_fd_message('channel_not_exported');
            $exportUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $productId . '&export_channel=' . rawurlencode($key));
            $mappingUrl = '?dbx_modul=dbxShop_admin&dbx_run1=product_channel_mapping&id=' . $productId . '&channel=' . rawurlencode($key);

            $html .= '<tr class="dbx-shop-product-channel-row">';
            $html .= '<td class="dbx-shop-product-channel-name">';
            $html .= '<label class="d-flex align-items-start gap-2 mb-0">';
            $html .= '<input class="form-check-input" type="checkbox" name="product_channels[]" value="' . $this->h($key) . '"' . ($checked ? ' checked' : '') . '>';
            $html .= '<span class="dbx-shop-product-channel-copy"><strong>' . $this->h($channel['title'] ?? $key) . '</strong><code>' . $this->h($key) . '</code></span>';
            $html .= '</label>';
            $html .= '</td>';
            $html .= '<td><span class="badge ' . $statusClass . '" title="' . $this->h($source) . '">' . $this->h($sourceText) . '</span></td>';
            $html .= '<td><span class="badge ' . $exportBadgeClass . '" title="' . $this->h($exportMessage) . '">' . $this->h($exportText) . '</span>';
            if ($listingId !== '') {
               $html .= '<code class="small d-block mt-1">' . $this->h($listingId) . '</code>';
            }
            $html .= '</td>';
            $html .= '<td class="text-end"><span class="dbx-shop-product-channel-actions">';
            $html .= $this->openWinButton(
               $mappingUrl,
               $texts->format_fd_message(
                  'mapping_title',
                  array('channel' => (string)($channel['title'] ?? $key))
               ),
               '<i class="bi bi-sliders2"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('mapping_label')) . '</span>',
               'btn btn-outline-secondary btn-sm',
               '68%',
               '84%'
            );
            if ($checked && (int)($channel['export_enabled'] ?? 0) === 1) {
               $html .= '<a class="btn btn-outline-primary btn-sm dbxConfirm" href="' . $this->h($exportUrl) . '" data-confirm-title="<i class=\'bi bi-broadcast\'></i> ' . $this->h($texts->get_fd_message('export_title')) . '" data-confirm="' . $this->h($texts->format_fd_message('export_question', array('channel' => (string)($channel['title'] ?? $key)))) . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('export_hint')) . '</small>" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('export_button_title')) . '"><i class="bi bi-broadcast"></i></a>';
            }
            $html .= '</span></td>';
            $html .= '</tr>';
         }
         $html .= '</tbody></table></div>';
      }

      $html .= '</aside>';
      return $html;
   }

   private function mappingLinesToMap(string $lines): array {
      $map = array();
      foreach (preg_split('/\R/', $lines) ?: array() as $line) {
         $line = trim((string)$line);
         if ($line === '' || strpos($line, '=') === false) {
            continue;
         }
         [$key, $value] = array_map('trim', explode('=', $line, 2));
         if ($key !== '' && $value !== '') {
            $map[$key] = $value;
         }
      }
      return $map;
   }

   private function mappingMapToLines(array $map): string {
      $lines = array();
      foreach ($map as $key => $value) {
         if (is_array($value)) {
            $value = implode('|', array_map('strval', $value));
         }
         $lines[] = (string)$key . '=' . (string)$value;
      }
      return implode("\n", $lines);
   }

   private function ebayCategoryOptions(array $mapping, array $channel, array $product, $texts): array {
      $options = array();
      $add = function(string $value, string $label) use (&$options) {
         $value = trim($value);
         if ($value === '' || isset($options[$value])) {
            return;
         }
         $options[$value] = $label;
      };

      $current = (string)($mapping['category_id'] ?? '');
      $channelDefault = (string)($channel['category_id'] ?? '');
      $groupDefault = (string)($this->productGroupChannelMappingDefaults('ebay', $product)['category_id'] ?? '');
      $add($current, $texts->format_fd_message('mapping_current_selection', array('value' => $current)));
      $add($groupDefault, $texts->format_fd_message('mapping_group_default', array('value' => $groupDefault)));
      $add($channelDefault, $texts->format_fd_message('mapping_channel_default', array('value' => $channelDefault)));

      $configured = dbx()->get_config('dbxShop', 'ebay_category_options');
      if (is_array($configured)) {
         foreach ($configured as $value => $label) {
            $add((string)$value, (string)$label);
         }
      } else {
         foreach (preg_split('/\R/', (string)$configured) ?: array() as $line) {
            $line = trim($line);
            if ($line === '') {
               continue;
            }
            if (strpos($line, '=') !== false) {
               [$value, $label] = array_map('trim', explode('=', $line, 2));
               $add($value, $label !== '' ? $label : $value);
            } else {
               $add($line, $line);
            }
         }
      }

      $productCategory = strtolower((string)($product['category'] ?? '') . ' ' . (string)($product['product_type'] ?? ''));
      if (strpos($productCategory, 'software') !== false || strpos($productCategory, 'digital') !== false) {
         $add('58058', '58058 - ' . $texts->get_fd_message('mapping_category_software'));
      }

      $add('58058', '58058 - ' . $texts->get_fd_message('mapping_category_software'));
      $add('11450', '11450 - ' . $texts->get_fd_message('mapping_category_clothing'));
      $add('293', '293 - ' . $texts->get_fd_message('mapping_category_electronics'));
      $add('220', '220 - ' . $texts->get_fd_message('mapping_category_home'));
      $add('12576', '12576 - ' . $texts->get_fd_message('mapping_category_business'));

      return $options;
   }

   private function ebayCategoryInput(array $mapping, array $channel, array $product, $texts): string {
      $value = (string)($mapping['category_id'] ?? $channel['category_id'] ?? '');
      $html = '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('mapping_ebay_category')) . '</label>'
         . '<select class="form-select form-select-sm" name="mapping_category_id">';
      foreach ($this->ebayCategoryOptions($mapping, $channel, $product, $texts) as $optionValue => $label) {
         $html .= '<option value="' . $this->h($optionValue) . '"' . ((string)$optionValue === $value ? ' selected' : '') . '>' . $this->h($label) . '</option>';
      }
      $html .= '</select>'
         . '<div class="form-text">' . $this->h($texts->get_fd_message('mapping_ebay_category_hint')) . '</div>'
         . '</div>';
      return $html;
   }

   private function productGroupChannelMappingDefaults(string $platform, array $product): array {
      $groupId = (int)($product['product_group_id'] ?? 0);
      if ($groupId <= 0) {
         return array();
      }
      $group = $this->repo()->groupById($groupId);
      if (!is_array($group)) {
         return array();
      }

      if ($platform === 'ebay') {
         $category = trim((string)($group['ebay_category_id'] ?? ''));
         return $category !== '' ? array('category_id' => $category) : array();
      }
      if ($platform === 'amazon') {
         $productType = trim((string)($group['amazon_product_type'] ?? ''));
         return $productType !== '' ? array('productType' => $productType) : array();
      }
      if ($platform === 'kleinanzeigen') {
         $category = trim((string)($group['kleinanzeigen_category_id'] ?? ''));
         return $category !== '' ? array('category_id' => $category) : array();
      }
      if ($platform === 'mobile') {
         $category = trim((string)($group['mobile_category_id'] ?? ''));
         return $category !== '' ? array('mobile_vehicle' => array('category' => $category)) : array();
      }

      return array();
   }

   private function channelMappingInheritedDefaults(string $platform, array $channel, array $product): array {
      $defaults = array();
      if ($platform === 'ebay') {
         $defaults = array(
            'category_id' => trim((string)($channel['category_id'] ?? '')),
            'payment_policy_id' => trim((string)($channel['payment_policy_id'] ?? '')),
            'fulfillment_policy_id' => trim((string)($channel['fulfillment_policy_id'] ?? '')),
            'return_policy_id' => trim((string)($channel['return_policy_id'] ?? '')),
         );
      } elseif ($platform === 'amazon') {
         $category = trim((string)($channel['category_id'] ?? ''));
         if (stripos($category, 'productType:') === 0) {
            $category = trim(substr($category, strlen('productType:')));
         }
         if (strpos($category, '/') !== false) {
            $category = trim((string)explode('/', $category)[0]);
         }
         $defaults = array('productType' => strtoupper($category));
      } elseif ($platform === 'kleinanzeigen') {
         $defaults = array(
            'category_id' => trim((string)($channel['category_id'] ?? '')),
            'location' => trim((string)($channel['location_key'] ?? '')),
         );
      } elseif ($platform === 'mobile') {
         $category = trim((string)($channel['category_id'] ?? ''));
         $defaults = array('mobile_vehicle' => array('category' => $category));
      }

      $defaults = $this->mergeMappingDefaults($defaults, $this->productGroupChannelMappingDefaults($platform, $product));
      return $this->cleanEmptyMappingValues($defaults);
   }

   private function mergeMappingDefaults(array $defaults, array $mapping): array {
      foreach ($defaults as $key => $value) {
         if (is_array($value)) {
            $current = is_array($mapping[$key] ?? null) ? $mapping[$key] : array();
            $mapping[$key] = $this->mergeMappingDefaults($value, $current);
            continue;
         }
         if (!array_key_exists($key, $mapping) || trim((string)$mapping[$key]) === '') {
            $mapping[$key] = $value;
         }
      }
      return $mapping;
   }

   private function cleanEmptyMappingValues(array $mapping): array {
      foreach ($mapping as $key => $value) {
         if (is_array($value)) {
            $value = $this->cleanEmptyMappingValues($value);
            if ($value === array()) {
               unset($mapping[$key]);
            } else {
               $mapping[$key] = $value;
            }
            continue;
         }
         if (trim((string)$value) === '') {
            unset($mapping[$key]);
         }
      }
      return $mapping;
   }

   private function removeInheritedMappingDefaults(array $mapping, array $defaults): array {
      foreach ($defaults as $key => $defaultValue) {
         if (!array_key_exists($key, $mapping)) {
            continue;
         }
         if (is_array($defaultValue)) {
            $current = is_array($mapping[$key]) ? $mapping[$key] : array();
            $mapping[$key] = $this->removeInheritedMappingDefaults($current, $defaultValue);
            if ($mapping[$key] === array()) {
               unset($mapping[$key]);
            }
            continue;
         }
         if (trim((string)$mapping[$key]) === trim((string)$defaultValue)) {
            unset($mapping[$key]);
         }
      }
      return $this->cleanEmptyMappingValues($mapping);
   }

   private function providerMappingHtml(string $platform, array $mapping, array $channel, array $product, $texts): string {
      $input = function(string $name, string $label, string $value = '', string $placeholder = '', string $class = 'col-md-4'): string {
         return '<div class="' . $class . '"><label class="form-label">' . $this->h($label) . '</label><input class="form-control form-control-sm" name="mapping_' . $this->h($name) . '" value="' . $this->h($value) . '" placeholder="' . $this->h($placeholder) . '"></div>';
      };
      $textarea = function(string $name, string $label, string $value = '', string $placeholder = '', int $rows = 4, string $class = 'col-12'): string {
         return '<div class="' . $class . '"><label class="form-label">' . $this->h($label) . '</label><textarea class="form-control form-control-sm" rows="' . $rows . '" name="mapping_' . $this->h($name) . '" placeholder="' . $this->h($placeholder) . '">' . $this->h($value) . '</textarea></div>';
      };

      $html = '<div class="row g-3">';
      if ($platform === 'ebay') {
         $condition = (string)($mapping['condition'] ?? 'NEW');
         $aspects = is_array($mapping['aspects'] ?? null) ? $this->mappingMapToLines($mapping['aspects']) : '';
         $locationKey = trim((string)($channel['location_key'] ?? ''));
         $html .= $this->ebayCategoryInput($mapping, $channel, $product, $texts);
         $html .= '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('mapping_location_key')) . '</label>'
            . '<input class="form-control form-control-sm" value="' . $this->h($locationKey) . '" placeholder="' . $this->h($texts->get_fd_message('mapping_location_placeholder')) . '" readonly>'
            . '<div class="form-text">' . $this->h($texts->get_fd_message('mapping_location_hint')) . '</div></div>';
         $html .= '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('mapping_condition')) . '</label><select class="form-select form-select-sm" name="mapping_condition">'
            . '<option value="NEW"' . ($condition === 'NEW' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_condition_new')) . '</option>'
            . '<option value="USED_EXCELLENT"' . ($condition === 'USED_EXCELLENT' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_condition_used_excellent')) . '</option>'
            . '<option value="USED_GOOD"' . ($condition === 'USED_GOOD' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_condition_used_good')) . '</option>'
            . '</select></div>';
         $html .= $input('payment_policy_id', 'Payment-Policy-ID', (string)($mapping['payment_policy_id'] ?? $channel['payment_policy_id'] ?? ''), 'policy_payment_1234567890');
         $html .= $input('fulfillment_policy_id', 'Fulfillment-Policy-ID', (string)($mapping['fulfillment_policy_id'] ?? $channel['fulfillment_policy_id'] ?? ''), 'policy_fulfillment_1234567890');
         $html .= $input('return_policy_id', 'Return-Policy-ID', (string)($mapping['return_policy_id'] ?? $channel['return_policy_id'] ?? ''), 'policy_return_1234567890');
         $html .= $textarea('aspects', $texts->get_fd_message('mapping_ebay_aspects'), $aspects, "brand=dbxApp\ncolor=black\nsize=L", 5);
      } elseif ($platform === 'amazon') {
         $simple = is_array($mapping['simple_attributes'] ?? null) ? $this->mappingMapToLines($mapping['simple_attributes']) : '';
         $html .= $input('productType', 'Amazon Product Type', (string)($mapping['productType'] ?? $mapping['product_type'] ?? ''), 'SOFTWARE / PRODUCT / SHIRT');
         $html .= '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('mapping_requirements')) . '</label><select class="form-select form-select-sm" name="mapping_requirements">'
            . '<option value="LISTING"' . ((string)($mapping['requirements'] ?? 'LISTING') === 'LISTING' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_requirements_listing')) . '</option>'
            . '<option value="LISTING_PRODUCT_ONLY"' . ((string)($mapping['requirements'] ?? '') === 'LISTING_PRODUCT_ONLY' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_requirements_product')) . '</option>'
            . '<option value="LISTING_OFFER_ONLY"' . ((string)($mapping['requirements'] ?? '') === 'LISTING_OFFER_ONLY' ? ' selected' : '') . '>' . $this->h($texts->get_fd_message('mapping_requirements_offer')) . '</option>'
            . '</select></div>';
         $html .= $input('brand', $texts->get_fd_message('mapping_brand'), (string)($mapping['simple_attributes']['brand'] ?? 'dbxApp'), 'dbxApp');
         $html .= $textarea('simple_attributes', $texts->get_fd_message('mapping_amazon_attributes'), $simple, "manufacturer=dbxApp\nitem_type_keyword=software\nrecommended_browse_nodes=123456", 6);
         $html .= '<div class="col-12"><div class="form-text">' . $this->h($texts->get_fd_message('mapping_amazon_hint')) . '</div></div>';
      } elseif ($platform === 'mobile') {
         $vehicle = is_array($mapping['mobile_vehicle'] ?? null) ? $mapping['mobile_vehicle'] : array();
         $html .= $input('vehicle_make', $texts->get_fd_message('mapping_brand'), (string)($vehicle['make'] ?? ''), 'Volkswagen');
         $html .= $input('vehicle_model', $texts->get_fd_message('mapping_vehicle_model'), (string)($vehicle['model'] ?? ''), 'Golf');
         $html .= $input('vehicle_first_registration', $texts->get_fd_message('mapping_vehicle_registration'), (string)($vehicle['firstRegistration'] ?? ''), '2023-05');
         $html .= $input('vehicle_mileage', $texts->get_fd_message('mapping_vehicle_mileage'), (string)($vehicle['mileage'] ?? ''), '25000');
         $html .= $input('vehicle_fuel', $texts->get_fd_message('mapping_vehicle_fuel'), (string)($vehicle['fuel'] ?? ''), 'PETROL');
         $html .= $input('vehicle_power', $texts->get_fd_message('mapping_vehicle_power'), (string)($vehicle['power'] ?? ''), '110');
         $html .= $input('vehicle_category', $texts->get_fd_message('mapping_vehicle_category'), (string)($vehicle['category'] ?? $channel['category_id'] ?? ''), 'car');
         $html .= $textarea('vehicle_extra', $texts->get_fd_message('mapping_vehicle_extra'), is_array($mapping['vehicle_extra'] ?? null) ? $this->mappingMapToLines($mapping['vehicle_extra']) : '', "gearbox=MANUAL\nemissionClass=EURO6", 5);
         $html .= '<div class="col-12"><div class="form-text">' . $this->h($texts->get_fd_message('mapping_mobile_hint')) . '</div></div>';
      } elseif ($platform === 'kleinanzeigen') {
         $attrs = is_array($mapping['attributes'] ?? null) ? $this->mappingMapToLines($mapping['attributes']) : '';
         $html .= $input('category_id', $texts->get_fd_message('mapping_classified_category'), (string)($mapping['category_id'] ?? $channel['category_id'] ?? ''), 'category_12345');
         $html .= $input('location', $texts->get_fd_message('mapping_place'), (string)($mapping['location'] ?? ''), '10115 Berlin');
         $html .= $input('contact_name', $texts->get_fd_message('mapping_contact_name'), (string)($mapping['contact_name'] ?? ''), 'Muster GmbH');
         $html .= $input('phone', $texts->get_fd_message('mapping_phone'), (string)($mapping['phone'] ?? ''), '+49...');
         $html .= $textarea('attributes', $texts->get_fd_message('mapping_classified_attributes'), $attrs, "condition=new\nshipping=yes\ncolor=black", 5);
         $html .= '<div class="col-12"><div class="form-text">' . $this->h($texts->get_fd_message('mapping_classified_hint')) . '</div></div>';
      } else {
         $attrs = is_array($mapping['attributes'] ?? null) ? $this->mappingMapToLines($mapping['attributes']) : '';
         $html .= $input('endpoint_action', $texts->get_fd_message('mapping_endpoint'), (string)($mapping['endpoint_action'] ?? ''), 'products.upsert');
         $html .= $textarea('attributes', $texts->get_fd_message('mapping_middleware_attributes'), $attrs, "external_category=123\nbrand=dbxApp", 6);
      }
      $html .= '</div>';
      return $html;
   }

   private function collectProductChannelMapping(string $platform, array $defaults = array()): array {
      if ($platform === 'ebay') {
         return array(
            'category_id' => trim((string)($_POST['mapping_category_id'] ?? '')),
            'condition' => trim((string)($_POST['mapping_condition'] ?? 'NEW')),
            'payment_policy_id' => trim((string)($_POST['mapping_payment_policy_id'] ?? '')),
            'fulfillment_policy_id' => trim((string)($_POST['mapping_fulfillment_policy_id'] ?? '')),
            'return_policy_id' => trim((string)($_POST['mapping_return_policy_id'] ?? '')),
            'aspects' => $this->mappingLinesToMap((string)($_POST['mapping_aspects'] ?? '')),
         );
      }
      if ($platform === 'amazon') {
         $simple = $this->mappingLinesToMap((string)($_POST['mapping_simple_attributes'] ?? ''));
         $brand = trim((string)($_POST['mapping_brand'] ?? ''));
         if ($brand !== '') {
            $simple['brand'] = $brand;
         }
         return array(
            'productType' => trim((string)($_POST['mapping_productType'] ?? '')),
            'requirements' => trim((string)($_POST['mapping_requirements'] ?? 'LISTING')),
            'simple_attributes' => $simple,
         );
      }
      if ($platform === 'mobile') {
         $vehicle = array(
            'make' => trim((string)($_POST['mapping_vehicle_make'] ?? '')),
            'model' => trim((string)($_POST['mapping_vehicle_model'] ?? '')),
            'firstRegistration' => trim((string)($_POST['mapping_vehicle_first_registration'] ?? '')),
            'mileage' => trim((string)($_POST['mapping_vehicle_mileage'] ?? '')),
            'fuel' => trim((string)($_POST['mapping_vehicle_fuel'] ?? '')),
            'power' => trim((string)($_POST['mapping_vehicle_power'] ?? '')),
            'category' => trim((string)($_POST['mapping_vehicle_category'] ?? '')),
         );
         $vehicle = array_filter($vehicle, fn($value) => trim((string)$value) !== '');
         return array(
            'mobile_vehicle' => $vehicle,
            'vehicle_extra' => $this->mappingLinesToMap((string)($_POST['mapping_vehicle_extra'] ?? '')),
         );
      }
      if ($platform === 'kleinanzeigen') {
         return array(
            'category_id' => trim((string)($_POST['mapping_category_id'] ?? '')),
            'location' => trim((string)($_POST['mapping_location'] ?? '')),
            'contact_name' => trim((string)($_POST['mapping_contact_name'] ?? '')),
            'phone' => trim((string)($_POST['mapping_phone'] ?? '')),
            'attributes' => $this->mappingLinesToMap((string)($_POST['mapping_attributes'] ?? '')),
         );
      }
      return array(
         'endpoint_action' => trim((string)($_POST['mapping_endpoint_action'] ?? '')),
         'attributes' => $this->mappingLinesToMap((string)($_POST['mapping_attributes'] ?? '')),
      );
   }

   private function normalizeDecimalInput($value): string {
      $value = str_replace(',', '.', trim((string)$value));
      if ($value === '') {
         return '';
      }
      return number_format((float)$value, 2, '.', '');
   }

   private function channelInheritedDecimalValue(string $postedName): string {
      $value = $this->normalizeDecimalInput($_POST[$postedName] ?? '');
      $inherited = (string)($_POST[$postedName . '_inherited'] ?? '') === '1';
      $inheritedValue = $this->normalizeDecimalInput($_POST[$postedName . '_inherited_value'] ?? '');
      if ($value === '') {
         return '-1';
      }
      if ($inherited && $inheritedValue !== '' && $value === $inheritedValue) {
         return '-1';
      }
      return $value;
   }

   private function channelMappingDisplayValues(array $product, array $productChannel): array {
      $storedPrice = (float)($productChannel['price_gross'] ?? -1);
      $storedShipping = (float)($productChannel['shipping_gross'] ?? -1);
      $inheritedPrice = number_format((float)($product['price_gross'] ?? 0), 2, '.', '');
      $inheritedShipping = number_format((float)($product['effective_shipping_gross'] ?? $product['shipping_gross'] ?? 0), 2, '.', '');
      $priceInherited = $storedPrice < 0;
      $shippingInherited = $storedShipping < 0;

      return array(
         'price_gross' => $priceInherited ? $inheritedPrice : number_format($storedPrice, 2, '.', ''),
         'shipping_gross' => $shippingInherited ? $inheritedShipping : number_format($storedShipping, 2, '.', ''),
         'price_gross_inherited' => $priceInherited ? '1' : '0',
         'price_gross_inherited_value' => $inheritedPrice,
         'shipping_gross_inherited' => $shippingInherited ? '1' : '0',
         'shipping_gross_inherited_value' => $inheritedShipping,
      );
   }

   private function productChannelMapping(): string {
      $this->ensureSeed();
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('shop-product-channel-mapping-texts');
      $texts->_fd = 'dbxShop|shop-product-channel';
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $productId = (int)dbx()->get_modul_var('id', 0, 'int');
      $channelKey = trim((string)dbx()->get_modul_var('channel', '', 'parameter'));
      if ($productId <= 0 || $channelKey === '') {
         return $this->frame('<div class="alert alert-warning m-3">' . $this->h($texts->get_fd_message('mapping_missing')) . '</div>', $texts->get_fd_message('mapping_title'));
      }

      $state = $this->repo()->productChannelMapping($productId, $channelKey);
      if (!$state) {
         return $this->frame('<div class="alert alert-warning m-3">' . $this->h($texts->get_fd_message('mapping_not_found')) . '</div>', $texts->get_fd_message('mapping_title'));
      }

      $product = (array)$state['product'];
      $channel = (array)$state['channel'];
      $productChannel = (array)$state['product_channel'];
      $mapping = (array)$state['mapping'];
      $platform = (string)($channel['platform_type'] ?? 'custom');
      $mappingDefaults = $this->channelMappingInheritedDefaults($platform, $channel, $product);
      $mapping = $this->mergeMappingDefaults($mappingDefaults, $mapping);
      $message = '';

      if ($this->posted('save_channel_mapping')) {
         $saveProductId = (int)($_POST['product_id'] ?? $productId);
         $saveChannelKey = trim((string)($_POST['channel_key_ref'] ?? $channelKey));
         if ($saveProductId === $productId && $saveChannelKey === $channelKey) {
            $this->repo()->saveProductChannelMapping($productId, $channelKey, array(
               'active' => !empty($_POST['active']) ? 1 : 0,
               'channel_sku' => (string)($_POST['channel_sku'] ?? ''),
               'price_gross' => $this->channelInheritedDecimalValue('price_gross'),
               'shipping_gross' => $this->channelInheritedDecimalValue('shipping_gross'),
               'external_listing_id' => (string)($_POST['external_listing_id'] ?? ''),
               'external_offer_id' => (string)($_POST['external_offer_id'] ?? ''),
               'mapping' => $this->removeInheritedMappingDefaults($this->collectProductChannelMapping($platform, $mappingDefaults), $mappingDefaults),
            ));
            $message = $texts->get_fd_message('mapping_saved');
            $state = $this->repo()->productChannelMapping($productId, $channelKey) ?: $state;
            $productChannel = (array)$state['product_channel'];
            $mapping = (array)$state['mapping'];
            $mappingDefaults = $this->channelMappingInheritedDefaults($platform, $channel, $product);
            $mapping = $this->mergeMappingDefaults($mappingDefaults, $mapping);
         }
      }

      $displayValues = $this->channelMappingDisplayValues($product, $productChannel);
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-product-channel-mapping-' . $productId . '-' . $channelKey, 'shop-product-channel-mapping');
      $form->_dd = 'dbxShop|shopProductChannel';
      $form->_fd = 'dbxShop|shop-product-channel';
      $form->load_fd_messages();
      $form->_data = array_merge($productChannel + array(
         'product_id' => $productId,
         'channel_key' => $channelKey,
         'active' => 1,
         'channel_sku' => (string)($product['sku'] ?? ''),
         'price_gross' => -1,
         'shipping_gross' => -1,
      ), array(
         'price_gross' => $displayValues['price_gross'],
         'shipping_gross' => $displayValues['shipping_gross'],
      ));
      $form->_rid = (int)($productChannel['id'] ?? 0);
      $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=product_channel_mapping&id=' . $productId . '&channel=' . rawurlencode($channelKey);
      $form->set_activ_id((int)($productChannel['id'] ?? 0));
      $form->_msg_info = '';
      $form->add_rep('shop_admin_style', $this->shopAdminStyle());
      $form->add_rep('bar_class', 'dbx-module-bar');
      $form->add_rep('bar_title_class', 'dbx-module-bar-titleblock');
      $form->add_rep('bar_actions_class', 'dbx-module-bar-actions');
      $form->add_rep('bar_icon', 'bi-sliders2');
      $form->add_rep('bar_title', $this->h($texts->get_fd_message('mapping_title')));
      $form->add_rep('bar_subtitle', $this->h((string)($product['sku'] ?? '') . ' - ' . (string)($channel['title'] ?? $channelKey)));
      $form->add_rep('bar_actions', $this->helpButton($this->ensureShopProductChannelMappingHelpPage(), $texts->get_fd_message('mapping_help'), 'btn btn-outline-secondary btn-sm')
         . '<button class="btn btn-primary btn-sm" type="submit" title="' . $this->h($texts->get_fd_message('mapping_save')) . '"><i class="bi bi-save"></i> ' . $this->h($texts->get_fd_message('mapping_save')) . '</button>'
         . '<a class="btn btn-outline-primary btn-sm dbxConfirm" href="' . $this->h($this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $productId . '&export_channel=' . rawurlencode($channelKey))) . '" data-confirm-title="<i class=\'bi bi-broadcast\'></i> ' . $this->h($texts->get_fd_message('mapping_export_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('mapping_export_confirm')) . '" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('mapping_export_label')) . '"><i class="bi bi-broadcast"></i></a>');
      $form->add_rep('product_id', (string)$productId);
      $form->add_rep('channel_key', $this->h($channelKey));
      $form->add_rep('price_gross_inherited', $displayValues['price_gross_inherited']);
      $form->add_rep('price_gross_inherited_value', $displayValues['price_gross_inherited_value']);
      $form->add_rep('shipping_gross_inherited', $displayValues['shipping_gross_inherited']);
      $form->add_rep('shipping_gross_inherited_value', $displayValues['shipping_gross_inherited_value']);
      $form->add_rep('mapping_message', $message !== '' ? '<div class="alert alert-success mb-3">' . $this->h($message) . '</div>' : '');
      $form->add_rep('mapping_intro', $this->h($texts->get_fd_message('mapping_intro')));
      $form->add_rep('mapping_values_title', $this->h($texts->get_fd_message('mapping_values_title')));
      $form->add_rep('mapping_export_status_title', $this->h($texts->get_fd_message('mapping_export_status_title')));
      $form->add_rep('provider_title', $this->h(($channel['title'] ?? $channelKey) . ' Mapping'));
      $form->add_rep('export_status_view', $this->productChannelExportStatusHtml($productChannel, $texts));
      $form->add_fld('active');
      $form->add_fld('channel_sku', placeholder: (string)($product['sku'] ?? ''));
      $form->add_fld('price_gross', placeholder: $displayValues['price_gross']);
      $form->add_fld('shipping_gross', placeholder: $displayValues['shipping_gross']);
      $form->add_fld('external_listing_id');
      $form->add_fld('external_offer_id');
      $form->add_obj('provider_mapping', 'obj-value', $this->providerMappingHtml($platform, $mapping, $channel, $product, $texts));
      return $form->run();
   }

   private function productChannelExportStatusHtml(array $productChannel, $texts): string {
      $status = trim((string)($productChannel['export_status'] ?? ''));
      $message = trim((string)($productChannel['export_message'] ?? ''));
      $listing = trim((string)($productChannel['external_listing_id'] ?? ''));
      $offer = trim((string)($productChannel['external_offer_id'] ?? ''));
      $date = trim((string)($productChannel['last_export_date'] ?? ''));
      $html = '<div class="d-flex flex-wrap gap-2 align-items-center">';
      $html .= '<span class="badge text-bg-' . ($status === 'failed' ? 'danger' : ($status !== '' ? 'info' : 'secondary')) . '">' . $this->h($status !== '' ? $status : $texts->get_fd_message('mapping_not_exported')) . '</span>';
      if ($date !== '') $html .= '<span class="text-muted small">' . $this->h($date) . '</span>';
      if ($listing !== '') $html .= '<code>Listing: ' . $this->h($listing) . '</code>';
      if ($offer !== '') $html .= '<code>Offer: ' . $this->h($offer) . '</code>';
      $html .= '</div>';
      if ($message !== '') {
         $html .= '<div class="alert alert-secondary py-2 mt-2 mb-0">' . $this->h($message) . '</div>';
      }
      return $html;
   }

   /**
    * Prüft eine Shop-Admin-Kartenaktion über den zugehörigen dbxForm-Kontext.
    *
    * Mehrere Verwaltungsseiten rendern eine dbxForm-Karte je Datensatz. Die
    * Mutation wird am Seitenanfang verarbeitet; deshalb wird hier anhand von
    * Aktion und Datensatz dieselbe stabile Formular-ID rekonstruiert. Erst ein
    * gültiger, sessiongebundener Submit darf den Repository-Aufruf erreichen.
    * Für Speichern-Aktionen werden zusätzlich die im Formular sichtbaren
    * FD-Felder durch die normale dbxForm-/dbxValidator-Pipeline geprüft.
    */
   private function posted(string $action): bool {
      if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
         || (string)($_POST['shop_action'] ?? '') !== $action) {
         return false;
      }

      $id = max(0, (int)($_POST['id'] ?? 0));
      $suffix = $id > 0 ? (string)$id : 'new';
      $contract = array();

      switch ($action) {
         case 'save_channel_mapping':
            $productId = max(0, (int)($_POST['product_id'] ?? 0));
            $channelKey = trim((string)($_POST['channel_key_ref'] ?? ''));
            if ($productId <= 0 || !preg_match('/^[A-Za-z0-9_-]+$/', $channelKey)) {
               return false;
            }
            $contract = array(
               'fid' => 'shop-product-channel-mapping-' . $productId . '-' . $channelKey,
               'fd' => 'dbxShop|shop-product-channel',
               'fields' => array('active', 'channel_sku', 'price_gross', 'shipping_gross', 'external_listing_id', 'external_offer_id'),
            );
            break;

         case 'save_product_group':
            $contract = array(
               'fid' => 'shop-product-group-' . $suffix,
               'fd' => 'dbxShop|shop-product-group',
               'fields' => array('group_key', 'parent_id', 'title', 'description', 'tax_class', 'card_template', 'detail_template', 'gallery_template', 'gallery_visible_count', 'gallery_image_size', 'gallery_lightbox_width', 'gallery_overflow', 'gallery_click', 'attribute_notes', 'ebay_category_id', 'amazon_product_type', 'kleinanzeigen_category_id', 'mobile_category_id', 'sorter', 'active'),
            );
            break;

         case 'delete_product_group':
            $contract = array('fid' => 'shop-product-group-' . $suffix, 'fd' => '', 'fields' => array());
            break;

         case 'save_attribute_definition':
            $contract = array(
               'fid' => 'shop-attribute-definition-' . $suffix,
               'fd' => 'dbxShop|shop-attribute-definition',
               'fields' => array('group_id', 'attr_key', 'title', 'input_type', 'unit', 'options', 'required', 'filterable', 'comparable', 'sorter', 'active'),
            );
            break;

         case 'save_product_attributes':
            $productId = max(0, (int)($_POST['product_id'] ?? 0));
            if ($productId <= 0) return false;
            $contract = array('fid' => 'shop-product-attributes-' . $productId, 'fd' => '', 'fields' => array());
            break;

         case 'save_shipping_group':
            $contract = array(
               'fid' => 'shop-shipping-group-' . $suffix,
               'fd' => 'dbxShop|shop-shipping-group',
               'fields' => array('group_key', 'title', 'description', 'shipping_way', 'delivery_time', 'shipping_gross', 'free_from_gross', 'sorter', 'active'),
            );
            break;

         case 'delete_shipping_group':
            $contract = array('fid' => 'shop-shipping-group-' . $suffix, 'fd' => '', 'fields' => array());
            break;

         case 'save_channel_group':
            $contract = array(
               'fid' => 'shop-channel-group-' . $suffix,
               'fd' => 'dbxShop|shop-channel-group',
               'fields' => array('group_key', 'title', 'description', 'sorter', 'active'),
            );
            break;

         case 'delete_channel_group':
            $contract = array('fid' => 'shop-channel-group-' . $suffix, 'fd' => '', 'fields' => array());
            break;

         case 'save_channel':
         case 'test_channel':
            $contract = array(
               'fid' => 'shop-channel-form-' . $suffix,
               'fd' => 'dbxShop|shop-channel',
               'fields' => array(),
               'all_fields' => true,
            );
            break;

         case 'delete_channel':
            $contract = array('fid' => 'shop-channel-form-' . $suffix, 'fd' => '', 'fields' => array());
            break;

         default:
            // Nicht mehr gerenderte Legacy-Aktionen (z. B. alter Medienupload)
            // dürfen ohne expliziten dbxForm-Vertrag keine Mutation auslösen.
            return false;
      }

      // get_system_obj lädt die Klasse; eine eigene Instanz verhindert, dass
      // der Prüflauf den später gerenderten Karten-Formzustand überschreibt.
      dbx()->get_system_obj('dbxForm');
      $form = new \dbxForm();
      $form->init((string)$contract['fid']);
      if ((string)($contract['fd'] ?? '') !== '') {
         $form->_fd = (string)$contract['fd'];
      }
      if (!empty($contract['all_fields'])) {
         $form->add_flds();
      } else {
         foreach ((array)($contract['fields'] ?? array()) as $field) {
            // Nicht gerenderte optionale Felder werden nicht künstlich als
            // leer validiert. Vorhandene Werte durchlaufen ihre FD-Regeln.
            if (array_key_exists($field, $_POST)) {
               $form->add_fld($field);
            }
         }
      }

      if (!$form->submit()) {
         $this->postedFormError = 'Die Sicherheitsprüfung des Formulars ist fehlgeschlagen. Bitte laden Sie die Seite neu und versuchen Sie es erneut.';
         dbx()->sys_msg(
            'security',
            'dbxShop_admin',
            $action,
            'Shop-Admin-Formular abgewiesen',
            'fid=' . (string)$contract['fid'] . ' reason=token ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
         );
         return false;
      }

      if ($form->errors()) {
         $this->postedFormError = 'Bitte prüfen Sie die markierten beziehungsweise erforderlichen Eingaben.';
         dbx()->sys_msg(
            'security',
            'dbxShop_admin',
            $action,
            'Shop-Admin-Formular abgewiesen',
            'fid=' . (string)$contract['fid'] . ' reason=validation ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
         );
         return false;
      }

      if ($action === 'save_product_attributes') {
         foreach ((array)($_POST['attr_value'] ?? array()) as $value) {
            if (is_array($value) || mb_strlen((string)$value) > 255) {
               $this->postedFormError = 'Ein Attributwert ist ungültig oder länger als 255 Zeichen.';
               return false;
            }
         }
      }

      return true;
   }

   private function cmsEndpoint(string $run1, array $params = array(), bool $mutating = false): string {
      $url = '?dbx_modul=dbxContent_admin&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }
      if ($mutating) {
         $url .= '&dbx_token=' . rawurlencode(dbx()->action_token('dbxContent_admin.actions'));
      }
      return $url;
   }

   private function shopEndpoint(string $run1, array $params = array(), bool $mutating = false): string {
      $url = '?dbx_modul=dbxShop_admin&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }
      return $mutating ? $this->actionUrl($url) : $url;
   }

   private function readJsonPayload(): array {
      $raw = (string)file_get_contents('php://input');
      $data = $raw !== '' ? json_decode($raw, true) : null;
      if (is_array($data)) {
         return $data;
      }
      return $_POST;
   }

   private function ensureCmsShopMediaFolder(): void {
      $dir = rtrim((string)dbx()->get_file_dir(), '/\\') . '/media/img/shop';
      if (!is_dir($dir)) {
         @mkdir($dir, 0775, true);
      }
   }

   private function jsonExit(array $data): string {
      if (!headers_sent()) {
         header('Content-Type: application/json; charset=utf-8');
      }
      echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
   }

   private function assignMedia(): string {
      if (!$this->checkActionToken('assign_media')) {
         return $this->jsonExit(array('ok' => 0, 'msg' => $this->postedFormError));
      }
      $payload = $this->readJsonPayload();
      $productId = (int)($payload['product_id'] ?? 0);
      $groupId = (int)($payload['group_id'] ?? 0);
      $mediaId = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
      if ($mediaId <= 0 || ($productId <= 0 && $groupId <= 0)) {
         return $this->jsonExit(array('ok' => 0, 'msg' => 'Bitte Artikel oder Artikelgruppe und ein Medium auswaehlen.'));
      }

      $row = $this->repo()->saveMediaImage(
         $productId,
         $groupId,
         $mediaId,
         (string)($payload['title'] ?? ''),
         (string)($payload['alt'] ?? $payload['title'] ?? ''),
         !empty($payload['is_primary']) ? 1 : 0,
         (int)($payload['sorter'] ?? 100)
      );
      if (!$row) {
         return $this->jsonExit(array('ok' => 0, 'msg' => 'Bild konnte nicht zugeordnet werden.'));
      }
      $this->syncShopMediaUsage();
      return $this->jsonExit(array(
         'ok' => 1,
         'image' => $row,
         'url' => $this->mediaItemUrl($row, true),
      ));
   }

   private function productTreeMove(): string {
      if (!$this->checkActionToken('product_tree_move')) {
         return $this->jsonExit(array('ok' => 0, 'msg' => $this->postedFormError));
      }
      $this->ensureSeed();
      $payload = $this->readJsonPayload();
      $type = (string)($payload['type'] ?? '');
      $targetGroupId = (int)($payload['target_group_id'] ?? 0);

      if ($type === 'product') {
         $productId = (int)($payload['product_id'] ?? 0);
         if ($productId <= 0 || $targetGroupId <= 0) {
            return $this->jsonExit(array('ok' => 0, 'msg' => 'Artikel und Zielgruppe sind erforderlich.'));
         }
         $count = $this->repo()->setProductGroupForProducts(array($productId), $targetGroupId);
         if ($count <= 0) {
            return $this->jsonExit(array('ok' => 0, 'msg' => 'Artikel konnte nicht verschoben werden.'));
         }
         return $this->jsonExit(array('ok' => 1, 'msg' => 'Artikelgruppe wurde zugeordnet.'));
      }

      if ($type === 'group') {
         $groupId = (int)($payload['group_id'] ?? 0);
         if ($groupId <= 0) {
            return $this->jsonExit(array('ok' => 0, 'msg' => 'Artikelgruppe ist erforderlich.'));
         }
         if (!$this->repo()->moveProductGroupParent($groupId, $targetGroupId)) {
            return $this->jsonExit(array('ok' => 0, 'msg' => 'Artikelgruppe konnte nicht verschoben werden. Pruefen Sie, ob dadurch ein Kreis entstehen wuerde.'));
         }
         return $this->jsonExit(array('ok' => 1, 'msg' => 'Artikelgruppe wurde verschoben.'));
      }

      return $this->jsonExit(array('ok' => 0, 'msg' => 'Unbekannte Drag-Drop-Aktion.'));
   }

   private function dashboard(): string {
      $stats = $this->repo()->dashboardStats();
      return $this->frame($this->tpl()->get_tpl('dbxShop_admin|admin-dashboard', array(
         'orders_open' => (string)($stats['orders_open'] ?? 0),
         'payments_open' => (string)($stats['payments_open'] ?? 0),
         'shipping_open' => (string)($stats['shipping_open'] ?? 0),
         'withdrawals_open' => (string)($stats['withdrawals_open'] ?? 0),
         'stock_low' => (string)($stats['stock_low'] ?? 0),
         'products_active' => (string)($stats['products_active'] ?? 0),
         'product_url' => '?dbx_modul=dbxShop_admin&dbx_run1=products',
         'order_url' => '?dbx_modul=dbxShop_admin&dbx_run1=orders',
         'group_url' => '?dbx_modul=dbxShop_admin&dbx_run1=groups',
         'attribute_url' => '?dbx_modul=dbxShop_admin&dbx_run1=attributes',
         'shipping_group_url' => '?dbx_modul=dbxShop_admin&dbx_run1=shipping_groups',
         'channel_group_url' => '?dbx_modul=dbxShop_admin&dbx_run1=channel_groups',
         'channel_url' => '?dbx_modul=dbxShop_admin&dbx_run1=channels',
         'media_url' => '?dbx_modul=dbxShop_admin&dbx_run1=media',
         'legal_url' => '?dbx_modul=dbxShop_admin&dbx_run1=legal',
         'return_url' => '?dbx_modul=dbxShop_admin&dbx_run1=returns',
         'install_url' => $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=install'),
      )));
   }

   private function placeholder(string $title, string $text): string {
      return $this->frame(
         '<div class="alert alert-info m-3"><strong>' . $this->h($title) . '</strong><br>' . $this->h($text) . '</div>',
         $title
      );
   }

   private function shopLegalCmsPage(string $key, string $title, string $intro, string $shopRun): string {
      $service = dbx()->get_include_obj('dbxShopService', 'dbxShop');
      $pages = is_object($service) && method_exists($service, 'ensureShopLegalPages')
         ? $service->ensureShopLegalPages()
         : array();
      $cid = (int)($pages[$key] ?? 0);
      if ($cid <= 0) {
         return $this->placeholder($title, 'Die CMS-Seite konnte nicht angelegt oder gefunden werden.');
      }

      $row = $this->db()->select1($this->contentDd(), $cid, 'id,title,permalink,content,group_read,template,activ', 0);
      if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
         return $this->placeholder($title, 'Die CMS-Seite konnte nicht geladen werden.');
      }

      $editUrl = '?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=' . $cid;
      $cmsViewUrl = '?dbx_modul=dbxContent&dbx_run1=content&cid=' . $cid;
      $shopViewUrl = '?dbx_modul=dbxShop&dbx_run1=' . rawurlencode($shopRun);
      $permalink = (string)($row['permalink'] ?? '');

      $actions = $this->openWinButton($editUrl, $title . ' bearbeiten', '<i class="bi bi-pencil-square"></i><span> Bearbeiten</span>', 'btn btn-primary btn-sm me-1', '94%', '92%')
         . $this->openWinButton($cmsViewUrl, $title . ' CMS-Ansicht', '<i class="bi bi-file-richtext"></i><span class="visually-hidden"> CMS-Ansicht</span>', 'btn btn-outline-primary btn-sm me-1', '82%', '86%')
         . $this->openWinButton($shopViewUrl, $title . ' im Shop ansehen', '<i class="bi bi-box-arrow-up-right"></i><span class="visually-hidden"> Shop-Ansicht</span>', 'btn btn-outline-primary btn-sm me-1', '82%', '86%');

      $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
      $preview = is_object($renderer) && method_exists($renderer, 'renderStatic')
         ? (string)$renderer->renderStatic($cid, array('template' => 'c-body1-footer', 'skip_hits' => true))
         : trim((string)($row['content'] ?? ''));
      if (trim($preview) === '') {
         $preview = '<div class="alert alert-warning">Die CMS-Seite ist leer.</div>';
      }

      $meta = '<dl class="dbx-shop-admin-cms-meta">'
         . '<dt>CMS-ID</dt><dd>' . $cid . '</dd>'
         . '<dt>Permalink</dt><dd><code>' . $this->h($permalink) . '</code></dd>'
         . '<dt>Status</dt><dd>' . ((int)($row['activ'] ?? 0) === 1 ? 'Aktiv' : 'Inaktiv') . '</dd>'
         . '<dt>Leserechte</dt><dd><code>' . $this->h((string)($row['group_read'] ?? '')) . '</code></dd>'
         . '<dt>Template</dt><dd><code>' . $this->h((string)($row['template'] ?? '')) . '</code></dd>'
         . '</dl>';

      $html = '<section class="dbx-shop-admin-cms-page">'
         . '<div class="alert alert-info"><strong>' . $this->h($title) . '</strong><br>' . $this->h($intro) . '</div>'
         . $meta
         . '<div class="dbx-shop-admin-cms-preview">' . $preview . '</div>'
         . '</section>';

      return $this->frame($html, $title, $actions);
   }

   private function settingsBool(array $cfg, string $key, bool $default = false): bool {
      if (!array_key_exists($key, $cfg)) {
         return $default;
      }
      $value = $cfg[$key];
      if (is_bool($value)) return $value;
      return in_array(strtolower(trim((string)$value)), array('1', 'true', 'yes', 'on'), true);
   }

   private function saveSettings(): void {
      $cfg = $this->shopConfig();
      $cfg['enabled'] = !empty($_POST['enabled']);
      $cfg['activ'] = $cfg['enabled'] ? '1' : '0';
      $cfg['default_channel'] = trim((string)($_POST['default_channel'] ?? 'shop')) ?: 'shop';
      $cfg['default_currency'] = strtoupper(substr(preg_replace('~[^A-Z]~i', '', (string)($_POST['default_currency'] ?? 'EUR')) ?: 'EUR', 0, 3));
      $cfg['price_display'] = in_array((string)($_POST['price_display'] ?? 'gross'), array('gross', 'net'), true) ? (string)$_POST['price_display'] : 'gross';
      $cfg['default_tax_class'] = in_array((string)($_POST['default_tax_class'] ?? 'mwst1'), array('mwst1', 'mwst2', 'mwst3'), true) ? (string)$_POST['default_tax_class'] : 'mwst1';
      $cfg['tax_display_enabled'] = (string)($_POST['tax_display_enabled'] ?? '1') !== '0';

      $rates = array();
      foreach (array('mwst1', 'mwst2', 'mwst3') as $key) {
         $title = trim((string)($_POST['tax_title_' . $key] ?? $key));
         $rate = str_replace(',', '.', trim((string)($_POST['tax_rate_' . $key] ?? '0')));
         $rates[$key] = array(
            'title' => $title !== '' ? $title : $key,
            'rate' => number_format((float)$rate, 2, '.', ''),
         );
      }
      $cfg['tax_rates'] = $rates;

      foreach (array(
         'b2b_mode',
         'stock_enabled',
         'channels_enabled',
         'checkout_guest_allowed',
         'legal_snapshot_enabled',
         'withdrawal_button_enabled',
         'mail_customer_enabled',
         'mail_admin_enabled',
         'payment_bank_transfer_enabled',
         'payment_invoice_enabled',
         'payment_paypal_enabled',
         'payment_amazon_pay_enabled',
         'delivery_digital_download_enabled',
         'delivery_flat_shipping_enabled',
      ) as $key) {
         $cfg[$key] = !empty($_POST[$key]);
      }

      $cfg['payment_bank_transfer_account_owner'] = trim((string)($_POST['payment_bank_transfer_account_owner'] ?? ''));
      $cfg['payment_bank_transfer_iban'] = trim((string)($_POST['payment_bank_transfer_iban'] ?? ''));
      $cfg['payment_bank_transfer_bic'] = trim((string)($_POST['payment_bank_transfer_bic'] ?? ''));
      $cfg['payment_bank_transfer_bank_name'] = trim((string)($_POST['payment_bank_transfer_bank_name'] ?? ''));
      $cfg['payment_bank_transfer_instructions'] = trim((string)($_POST['payment_bank_transfer_instructions'] ?? ''));
      $cfg['payment_invoice_instructions'] = trim((string)($_POST['payment_invoice_instructions'] ?? ''));
      $cfg['payment_paypal_mode'] = (string)($_POST['payment_paypal_mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
      $cfg['payment_paypal_client_id'] = trim((string)($_POST['payment_paypal_client_id'] ?? ''));
      $cfg['payment_paypal_client_secret'] = trim((string)($_POST['payment_paypal_client_secret'] ?? ''));
      $cfg['payment_paypal_brand_name'] = trim((string)($_POST['payment_paypal_brand_name'] ?? 'dbXapp')) ?: 'dbXapp';
      $cfg['payment_paypal_currency'] = $cfg['default_currency'];
      $cfg['payment_amazon_pay_mode'] = (string)($_POST['payment_amazon_pay_mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
      $cfg['payment_amazon_pay_region'] = in_array((string)($_POST['payment_amazon_pay_region'] ?? 'EU'), array('EU', 'UK', 'US'), true) ? (string)$_POST['payment_amazon_pay_region'] : 'EU';
      $cfg['payment_amazon_pay_merchant_id'] = trim((string)($_POST['payment_amazon_pay_merchant_id'] ?? ''));
      $cfg['payment_amazon_pay_store_id'] = trim((string)($_POST['payment_amazon_pay_store_id'] ?? ''));
      $cfg['payment_amazon_pay_public_key_id'] = trim((string)($_POST['payment_amazon_pay_public_key_id'] ?? ''));
      $cfg['payment_amazon_pay_private_key'] = trim((string)($_POST['payment_amazon_pay_private_key'] ?? ''));
      $cfg['payment_amazon_pay_sandbox_simulation_code'] = trim((string)($_POST['payment_amazon_pay_sandbox_simulation_code'] ?? ''));
      $cfg['mail_from'] = trim((string)($_POST['mail_from'] ?? ''));
      $cfg['mail_admin_to'] = trim((string)($_POST['mail_admin_to'] ?? ''));

      $flatShipping = str_replace(',', '.', trim((string)($_POST['delivery_flat_shipping_gross_price'] ?? '0')));
      $cfg['delivery_flat_shipping_gross_price'] = number_format((float)$flatShipping, 2, '.', '');
      $cfg['media_usage_slot'] = preg_replace('~[^a-z0-9_-]+~i', '', (string)($_POST['media_usage_slot'] ?? 'shop')) ?: 'shop';

      dbx()->set_config('dbxShop', $cfg);
   }

   private function settingsFormData(array $cfg): array {
      $rates = $this->taxRatesConfig();
      $data = $cfg;
      foreach (array('mwst1', 'mwst2', 'mwst3') as $key) {
         $rate = is_array($rates[$key] ?? null) ? $rates[$key] : array();
         $data['tax_title_' . $key] = (string)($rate['title'] ?? $key);
         $data['tax_rate_' . $key] = (string)($rate['rate'] ?? '0');
      }

      foreach (array(
         'enabled',
         'b2b_mode',
         'stock_enabled',
         'channels_enabled',
         'checkout_guest_allowed',
         'legal_snapshot_enabled',
         'withdrawal_button_enabled',
         'mail_customer_enabled',
         'mail_admin_enabled',
         'payment_bank_transfer_enabled',
         'payment_invoice_enabled',
         'payment_paypal_enabled',
         'payment_amazon_pay_enabled',
         'delivery_digital_download_enabled',
         'delivery_flat_shipping_enabled',
      ) as $key) {
         $data[$key] = $this->settingsBool($cfg, $key, in_array($key, array(
            'enabled',
            'checkout_guest_allowed',
            'legal_snapshot_enabled',
            'withdrawal_button_enabled',
            'mail_customer_enabled',
            'mail_admin_enabled',
            'payment_bank_transfer_enabled',
            'delivery_digital_download_enabled',
            'delivery_flat_shipping_enabled',
         ), true)) ? 1 : 0;
      }

      $data['default_channel'] = (string)($cfg['default_channel'] ?? 'shop');
      $data['channels_enabled'] = array_key_exists('channels_enabled', $cfg) ? (int)((bool)$cfg['channels_enabled']) : 1;
      $data['default_currency'] = (string)($cfg['default_currency'] ?? 'EUR');
      $data['price_display'] = (string)($cfg['price_display'] ?? 'gross');
      $data['default_tax_class'] = (string)($cfg['default_tax_class'] ?? 'mwst1');
      $data['tax_display_enabled'] = $this->settingsBool($cfg, 'tax_display_enabled', true) ? 1 : 0;
      $data['payment_paypal_mode'] = (string)($cfg['payment_paypal_mode'] ?? 'sandbox');
      $data['payment_paypal_brand_name'] = (string)($cfg['payment_paypal_brand_name'] ?? 'dbXapp');
      $data['payment_paypal_client_id'] = (string)($cfg['payment_paypal_client_id'] ?? '');
      $data['payment_paypal_client_secret'] = (string)($cfg['payment_paypal_client_secret'] ?? '');
      $data['payment_bank_transfer_account_owner'] = (string)($cfg['payment_bank_transfer_account_owner'] ?? '');
      $data['payment_bank_transfer_iban'] = (string)($cfg['payment_bank_transfer_iban'] ?? '');
      $data['payment_bank_transfer_bic'] = (string)($cfg['payment_bank_transfer_bic'] ?? '');
      $data['payment_bank_transfer_bank_name'] = (string)($cfg['payment_bank_transfer_bank_name'] ?? '');
      $data['payment_bank_transfer_instructions'] = (string)($cfg['payment_bank_transfer_instructions'] ?? 'Bitte ueberweisen Sie den Rechnungsbetrag unter Angabe der Bestellnummer.');
      $data['payment_invoice_instructions'] = (string)($cfg['payment_invoice_instructions'] ?? 'Sie erhalten eine Rechnung. Bitte zahlen Sie innerhalb der angegebenen Frist.');
      $data['payment_amazon_pay_mode'] = (string)($cfg['payment_amazon_pay_mode'] ?? 'sandbox');
      $data['payment_amazon_pay_region'] = (string)($cfg['payment_amazon_pay_region'] ?? 'EU');
      $data['payment_amazon_pay_merchant_id'] = (string)($cfg['payment_amazon_pay_merchant_id'] ?? '');
      $data['payment_amazon_pay_store_id'] = (string)($cfg['payment_amazon_pay_store_id'] ?? '');
      $data['payment_amazon_pay_public_key_id'] = (string)($cfg['payment_amazon_pay_public_key_id'] ?? '');
      $data['payment_amazon_pay_private_key'] = (string)($cfg['payment_amazon_pay_private_key'] ?? '');
      $data['payment_amazon_pay_sandbox_simulation_code'] = (string)($cfg['payment_amazon_pay_sandbox_simulation_code'] ?? '');
      $data['mail_from'] = (string)($cfg['mail_from'] ?? '');
      $data['mail_admin_to'] = (string)($cfg['mail_admin_to'] ?? '');
      $data['delivery_flat_shipping_gross_price'] = (string)($cfg['delivery_flat_shipping_gross_price'] ?? '5.90');
      $data['media_usage_slot'] = (string)($cfg['media_usage_slot'] ?? 'shop');

      return $data;
   }

   private function settingsChannelsStatusHtml(array $cfg, $texts): string {
      $channelsEnabled = $this->settingsBool($cfg, 'channels_enabled', true);
      $channels = $this->repo()->channels();
      $external = array();
      foreach ($channels as $channel) {
         $key = strtolower(trim((string)($channel['channel_key'] ?? '')));
         if ($key === '' || $key === 'shop') {
            continue;
         }
         if ((int)($channel['active'] ?? 0) !== 1) {
            continue;
         }
         $external[] = $channel;
      }

      $html = '<div class="dbx-shop-settings-channel-status">';
      $html .= '<div class="dbx-shop-settings-channel-head">';
      $html .= '<div><strong>' . $this->h($texts->get_fd_message('channels_external')) . '</strong><span>' . $this->h($channelsEnabled ? $texts->get_fd_message('channels_global_active') : $texts->get_fd_message('channels_global_inactive')) . '</span></div>';
      $html .= $this->openWinButton('?dbx_modul=dbxShop_admin&dbx_run1=channels', $texts->get_fd_message('channels_edit'), '<i class="bi bi-broadcast"></i> ' . $this->h($texts->get_fd_message('column_channel')), 'btn btn-outline-primary btn-sm', '92%', '88%');
      $html .= '</div>';

      if (!$channelsEnabled) {
         $html .= '<div class="alert alert-warning py-2 mb-0">' . $this->h($texts->get_fd_message('channels_disabled')) . '</div>';
      }

      if ($external === array()) {
         $html .= '<div class="dbx-shop-settings-channel-empty">' . $this->h($texts->get_fd_message('channels_none')) . '</div>';
         return $html . '</div>';
      }

      $html .= '<div class="dbx-shop-settings-channel-grid">';
      $html .= '<div class="dbx-shop-settings-channel-grid-head"><span>' . $this->h($texts->get_fd_message('column_channel')) . '</span><span>' . $this->h($texts->get_fd_message('column_platform')) . '</span><span>' . $this->h($texts->get_fd_message('column_connection')) . '</span><span>' . $this->h($texts->get_fd_message('column_export')) . '</span><span>' . $this->h($texts->get_fd_message('column_import')) . '</span><span>' . $this->h($texts->get_fd_message('column_test')) . '</span></div>';
      foreach ($external as $channel) {
         $test = trim((string)($channel['test_status'] ?? ''));
         $testClass = $test === 'ok' ? 'success' : ($test !== '' ? 'warning' : 'secondary');
         $html .= '<div class="dbx-shop-settings-channel-grid-row">';
         $html .= '<span class="dbx-shop-settings-channel-name"><strong>' . $this->h((string)($channel['title'] ?? $channel['channel_key'] ?? '')) . '</strong><code>' . $this->h((string)($channel['channel_key'] ?? '')) . '</code></span>';
         $html .= '<span>' . $this->h((string)($channel['platform_type'] ?? '')) . '</span>';
         $html .= '<span>' . $this->h((string)($channel['connection_mode'] ?? '')) . '</span>';
         $html .= ((int)($channel['export_enabled'] ?? 0) === 1 ? '<span class="badge text-bg-success">' . $this->h($texts->get_fd_message('column_export')) . '</span>' : '<span class="badge text-bg-secondary">' . $this->h($texts->get_fd_message('export_off')) . '</span>');
         $html .= ((int)($channel['order_import_enabled'] ?? 0) === 1 ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('column_import')) . '</span>' : '<span class="badge text-bg-secondary">' . $this->h($texts->get_fd_message('import_off')) . '</span>');
         $html .= '<span class="badge text-bg-' . $testClass . '">' . $this->h($test !== '' ? $test : $texts->get_fd_message('not_tested')) . '</span>';
         $html .= '</div>';
      }
      $html .= '</div>';
      return $html . '</div>';
   }

   private function settings(): string {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-settings-form', 'shop-settings-form');
      $form->_fd = 'dbxShop_admin|shop-settings';
      $form->load_fd_messages();
      $helpId = $this->ensureShopSettingsHelpPage();
      $helpButton = $helpId > 0
         ? $this->openWinButton('?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId, $form->get_fd_message('settings_help'), '<i class="bi bi-question-circle"></i><span class="visually-hidden"> ' . $this->h($form->get_fd_message('settings_help')) . '</span>', 'btn btn-outline-secondary btn-sm me-1', '72%', '82%')
         : '';
      $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=settings';
      $form->_data = $this->settingsFormData($this->shopConfig());
      $form->add_rep('shop_admin_style', $this->shopAdminStyle());
      $form->add_rep('form_class', 'dbx-shop-settings-dbXForm');
      $form->add_rep('bar_title', $this->h($form->get_fd_message('settings_title')));
      $form->add_rep('bar_icon', 'bi-sliders');
      $form->add_rep('bar_subtitle', $this->h($form->get_fd_message('settings_subtitle')));
      $form->add_rep('bar_class', 'dbx-module-bar');
      $form->add_rep('bar_title_class', 'dbx-module-bar-titleblock');
      $form->add_rep('bar_title_pre', '');
      $form->add_rep('bar_title_heading_attrs', '');
      $form->add_rep('bar_middle', '');
      $form->add_rep('bar_actions_class', 'dbx-module-bar-actions');
      $form->add_rep('bar_extra', '');
      $form->add_obj('channels_status', 'obj-value', $this->settingsChannelsStatusHtml($this->shopConfig(), $form));
      $paymentTestButton = $this->openWinButton('?dbx_modul=dbxShop_admin&dbx_run1=payment_test', $form->get_fd_message('settings_payment_test'), '<i class="bi bi-plug"></i><span class="visually-hidden"> ' . $this->h($form->get_fd_message('settings_payment_test')) . '</span>', 'btn btn-outline-primary btn-sm me-1', '64%', '58%');
      $form->add_rep('bar_actions', $paymentTestButton . '<button class="btn btn-primary btn-sm" type="submit" name="shop_action" value="save_settings" title="' . $this->h($form->get_fd_message('settings_save')) . '"><i class="bi bi-save"></i><span class="visually-hidden"> ' . $this->h($form->get_fd_message('settings_save')) . '</span></button>' . $helpButton);
      $form->_msg_info = '';
      $form->add_flds();

      if ($form->submit()) {
         if (!$form->errors() && !$form->warnings()) {
            $this->saveSettings();
            $form->_data = $this->settingsFormData($this->shopConfig());
            $form->add_obj('channels_status', 'obj-value', $this->settingsChannelsStatusHtml($this->shopConfig(), $form));
         }
      }

      return $form->run();
   }

   private function paymentTest(): string {
      $paypal = dbx()->get_include_obj('dbxShopPayPal', 'dbxShop');
      $amazonPay = dbx()->get_include_obj('dbxShopAmazonPay', 'dbxShop');
      $paypalResult = is_object($paypal) && method_exists($paypal, 'testConnection')
         ? $paypal->testConnection()
         : array('ok' => false, 'mode' => '', 'message' => 'PayPal-Connector konnte nicht geladen werden.');
      $amazonResult = is_object($amazonPay) && method_exists($amazonPay, 'testConnection')
         ? $amazonPay->testConnection()
         : array('ok' => false, 'mode' => '', 'region' => '', 'message' => 'Amazon-Pay-Connector konnte nicht geladen werden.');

      $card = function(string $title, string $icon, array $result): string {
         $ok = !empty($result['ok']);
         $meta = array();
         if (trim((string)($result['mode'] ?? '')) !== '') {
            $meta[] = 'Modus: ' . $this->h((string)$result['mode']);
         }
         if (trim((string)($result['region'] ?? '')) !== '') {
            $meta[] = 'Region: ' . $this->h((string)$result['region']);
         }
         return $this->tpl()->get_tpl('dbxShop_admin|payment-test-card', array(
            'icon' => $this->h($icon),
            'title' => $this->h($title),
            'badge_class' => $ok ? 'text-bg-success' : 'text-bg-warning',
            'badge_text' => $ok ? 'OK' : 'Pruefen',
            'meta' => $meta !== array() ? '<p class="dbx-shop-payment-test-meta">' . implode(' · ', $meta) . '</p>' : '',
            'message' => $this->h((string)($result['message'] ?? 'Keine Rueckmeldung.')),
         ));
      };

      $body = $this->tpl()->get_tpl('dbxShop_admin|payment-test', array(
         'cards' => $card('PayPal', 'bi-paypal', $paypalResult)
            . $card('Amazon Pay', 'bi-amazon', $amazonResult),
      ));
      return $this->frame($body, 'Payment testen');
   }

   private function install(): string {
      if (!$this->checkActionToken('install')) {
         return $this->placeholder('Shop-Installation abgewiesen', $this->postedFormError);
      }
      $this->maintenanceMode = true;
      try {
         $this->repo()->seedDemoProducts();
         $this->maintainShopAdminContent();
      } finally {
         $this->maintenanceMode = false;
      }
      return $this->placeholder(
         'Shop-Installation ausgefuehrt',
         'dbxShop.db3 wurde angelegt bzw. aktualisiert. Deutsche Testartikel, Gruppen und Channel-Zuordnungen sind vorhanden.'
      );
   }

   private function products(): string {
      $this->ensureSeed();
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-products-report');
      $report->_fd = 'dbxShop_admin|rpt-products-selection';
      $report->load_fd_messages();
      // dbxReport haengt die konkreten Schreibaktionen an diese Basis-URL an.
      // Der zusaetzliche Token ist fuer reine Filter-/Navigationsaufrufe
      // unschaedlich und sichert gleichzeitig die kompatiblen GET-Aktionen.
      $report->_action = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=products');
      $report->_mode = 'table';
      $report->_pages = true;
      $report->_multi_page_select = 1;
      $report->_create_row_select = true;
      $report->_create_row_delete = true;
      $report->_create_row_edit = true;
      $report->_create_row_show = true;
      $report->_create_sel_flds = true;
      $report->_but_pagination = 7;
      $report->_fld_id = 'id';
      $report->_msg_confirm_delete = $report->get_fd_message('delete_confirm');
      $report->_msg_info = $report->get_fd_message('report_info');
      $report->add_rep('bar_title', $report->get_fd_message('bar_title'));
      $report->add_rep('bar_icon', 'bi-bag-check');
      $report->add_rep('bar_subtitle', $report->get_fd_message('bar_subtitle'));
      $report->add_rep('bar_class', 'dbx-module-bar');
      $report->add_rep('bar_title_class', 'dbx-module-bar-titleblock');
      $report->add_rep('bar_actions_class', 'dbx-module-bar-actions');
      $report->add_rep('bar_title_pre', $this->productTreeToggleButton($report));
      $report->add_rep('bar_title_heading_attrs', '');
      $report->add_rep('bar_middle', '');
      $report->add_rep('bar_extra', '');
      $report->add_rep('bar_actions', $this->productShellActions($report));
      $report->add_rep('shop_admin_style', $this->shopAdminStyle());
      $report->add_rep('report_form_class', 'dbx-shop-products-form is-shop-tree-collapsed');
      $report->add_rep('report_form_attrs', 'data-dbx="lib=shopAdmin" data-shop-tree-shell');
      $channelsEnabled = $this->channelsEnabled();
      $columnClasses = array(
         'image_view' => 'dbx-shop-col-image',
         'article_view' => 'dbx-shop-col-article',
         'groups_view' => 'dbx-shop-col-groups',
         'attributes_view' => 'dbx-shop-col-attributes',
         'shipping_groups_view' => 'dbx-shop-col-shipping-groups',
         'price_view' => 'dbx-shop-col-money',
         'tax_view' => 'dbx-shop-col-tax',
         'shipping_view' => 'dbx-shop-col-money',
         'status_view' => 'dbx-shop-col-status',
      );
      if ($channelsEnabled) {
         $columnClasses['channel_groups_view'] = 'dbx-shop-col-channel-groups';
         $columnClasses['channels_view'] = 'dbx-shop-col-channels';
      }
      foreach ($columnClasses as $field => $class) {
         $report->set_class_haeder($field, $class);
         $report->_class_body[$field] = $class;
      }
      $report->set_callback_owner($this);
      $report->set_callback('next_record', 'product_report_next_record');
      $report->set_callback('row_action_data', 'product_report_row_action_data');
      $report->add_rep('report_products_actions', $this->productReportActionControls($report->_action, $report));
      $report->create_selection_fields('dbxShop_admin|rpt-products-selection');
      $this->handleProductReportAction($report);

      $query = trim((string)$report->get_fld_val('dbx_rwhere', '', 'parameter|max=100'));
      $requestedRowsPerPage = (int)$report->get_fld_val('dbx_rrows', 30, 'int');
      $rowsPerPage = $requestedRowsPerPage === 0 ? 0 : max(10, min(100, $requestedRowsPerPage));
      $position = $rowsPerPage === 0 ? 0 : max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
      $sort = (string)$report->get_fld_val('dbx_rsort', 'sorter', 'parameter');
      $direction = strtoupper((string)$report->get_fld_val('dbx_rdesc', 'ASC', 'parameter')) === 'DESC' ? 'DESC' : 'ASC';
      $selectedOnly = (int)$report->get_fld_val('dbx_rselect', 0, 'int') === 1;
      if (!in_array($sort, array('sorter', 'sku', 'title', 'price_gross', 'effective_tax_rate', 'effective_shipping_gross', 'active'), true)) {
         $sort = 'sorter';
      }

      $allProducts = $this->repo()->products(false);
      $matchedProducts = array();
      $selectedIds = $selectedOnly ? $report->get_multi_selects() : array();
      foreach ($allProducts as $product) {
         if ($selectedOnly && !isset($selectedIds[(string)(int)($product['id'] ?? 0)])) {
            continue;
         }
         $score = $this->productSearchScore($product, $query);
         if ($score <= 0) {
            continue;
         }
         $product['_search_score'] = $score;
         $matchedProducts[] = $product;
      }
      $matchedProducts = $this->sortProductsForReport($matchedProducts, $query, $sort, $direction);
      $filteredCount = count($matchedProducts);
      if ($rowsPerPage > 0 && $position >= $filteredCount && $filteredCount > 0) {
         $position = max(0, (int)(floor(($filteredCount - 1) / $rowsPerPage) * $rowsPerPage));
      }
      $visibleProducts = $rowsPerPage === 0
         ? $matchedProducts
         : array_slice($matchedProducts, $position, $rowsPerPage);

      $report->_rflds = array(
         'image_view' => $report->get_fd_message('column_image'),
         'article_view' => $report->get_fd_message('column_product'),
         'groups_view' => $report->get_fd_message('column_product_groups'),
         'attributes_view' => $report->get_fd_message('column_attributes'),
         'shipping_groups_view' => $report->get_fd_message('column_shipping_groups'),
         'price_view' => $report->get_fd_message('column_price'),
         'tax_view' => $report->get_fd_message('column_tax'),
         'shipping_view' => $report->get_fd_message('column_shipping'),
         'status_view' => $report->get_fd_message('column_status'),
      );
      if ($channelsEnabled) {
         $report->_rflds = array_slice($report->_rflds, 0, 5, true)
            + array(
               'channel_groups_view' => $report->get_fd_message('column_channel_groups'),
               'channels_view' => $report->get_fd_message('column_channels'),
            )
            + array_slice($report->_rflds, 5, null, true);
      }
      $report->_rpt_format = array(
         'image_view' => 'html',
         'article_view' => 'html',
         'groups_view' => 'html',
         'attributes_view' => 'html',
         'shipping_groups_view' => 'html',
         'price_view' => 'html',
         'tax_view' => 'html',
         'shipping_view' => 'html',
         'status_view' => 'html',
      );
      if ($channelsEnabled) {
         $report->_rpt_format['channel_groups_view'] = 'html';
         $report->_rpt_format['channels_view'] = 'html';
      }
      $report->_rrows = $rowsPerPage;
      $report->_rpos = $position;
      $report->_count_all = count($allProducts);
      $report->_rcount = $filteredCount;
      $report->_rdata = $visibleProducts;

      $report->add_rep('product_tree_panel', $this->productTreePanel($allProducts, $report));
      $content = $report->run();
      if ($filteredCount === 0) {
         $content .= '<div class="alert alert-info mx-3">'
            . $this->h($report->get_fd_message('no_results'))
            . '</div>';
      }

      return $content;
   }

   private function productEdit(): string {
      $this->ensureSeed();
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $isNew = $id <= 0;
      $exportNotice = '';
      $exportOk = false;
      $removeImageId = (int)dbx()->get_modul_var('remove_image', 0, 'int');
      if (!$isNew && $removeImageId > 0) {
         if ($this->checkActionToken('remove_image')) {
            $this->repo()->removeProductImageAssociation($removeImageId, $id);
            $this->syncShopMediaUsage();
         }
      }
      $exportChannel = trim((string)dbx()->get_modul_var('export_channel', '', 'parameter'));
      if (!$isNew && $exportChannel !== '') {
         if ($this->checkActionToken('export_channel')) {
            $result = $this->repo()->exportProductToChannel($id, $exportChannel);
            $exportOk = !empty($result['ok']);
            $exportNotice = (string)($result['message'] ?? '');
         } else {
            $exportNotice = $this->postedFormError;
         }
      }
      $data = $isNew ? $this->applyProductPreset($this->newProductDefaults()) : $this->repo()->productById($id);

      if (!$isNew && !is_array($data)) {
         return $this->frame('<div class="alert alert-warning m-3">Artikel nicht gefunden.</div>', 'Artikel bearbeiten', $this->productBarActions());
      }

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-product-form', 'shop-product-form');
      $form->_dd = 'dbxShop|shopProduct';
      $form->_fd = 'dbxShop|shop-product';
      $form->load_fd_messages();
      $form->_data = $data;
      $form->_rid = $isNew ? 0 : $id;
      $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . ($isNew ? 0 : $id);
      $form->set_activ_id($isNew ? 0 : $id);
      $form->add_rep(
         'bar_title',
         $form->get_fd_message(
            $isNew ? 'form_new_title' : 'form_edit_title'
         )
      );
      $form->add_rep('bar_icon', 'bi-bag-check');
      $form->add_rep(
         'bar_subtitle',
         $isNew
            ? $form->get_fd_message('form_new_subtitle')
            : trim((string)($data['sku'] ?? '') . ' - ' . (string)($data['title'] ?? ''))
      );
      $form->add_rep('bar_class', 'dbx-module-bar');
      $form->add_rep('bar_title_class', 'dbx-module-bar-titleblock');
      $form->add_rep('bar_title_pre', '');
      $form->add_rep('bar_title_heading_attrs', '');
      $form->add_rep('bar_middle', '');
      $form->add_rep('bar_actions_class', 'dbx-module-bar-actions');
      $form->add_rep('bar_actions', $this->productFormActions($id, $form));
      $form->add_rep('bar_extra', '');
      $form->add_rep('shop_admin_style', $this->shopAdminStyle());
      $form->_msg_info = $form->get_fd_message('form_info');
      if ($exportNotice !== '') {
         if ($exportOk) {
            $form->_msg_success = $exportNotice;
         } else {
            $form->_msg_error = $exportNotice;
         }
      }
      $form->add_flds();
      $form->add_fld(
         'product_group_id',
         tpl: 'select-single-label',
         label: $form->get_fd_message('field_product_group'),
         options: $this->productGroupOptions(0, false),
         rules: 'int'
      );

      if ($form->submit()) {
         if (!$form->errors()) {
            $ok = $form->save_post('dbxShop|shopProduct', $isNew ? 'new' : $id, $this->productFormDefaults($id));
            if ($ok) {
               $savedId = (int)$form->_rid;
               $groupId = (int)($_POST['product_group_id'] ?? 0);
               if ($savedId > 0 && $groupId > 0) {
                  $this->repo()->setProductGroupForProducts(array($savedId), $groupId);
               }
               if ($savedId > 0 && isset($_POST['product_channel_editor'])) {
                  $this->repo()->saveProductChannelOverrides($savedId, (array)($_POST['product_channels'] ?? array()));
               }
               $form->_msg_success = $form->get_fd_message(
                  'product_save_success'
               );
               if ($savedId > 0) {
                  $id = $savedId;
                  $isNew = false;
                  $data = $this->repo()->productById($savedId) ?: $form->_data;
                  $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=product_edit&id=' . $savedId;
               }
            } else {
               $form->_msg_error = $form->get_fd_message(
                  'product_save_error'
               );
            }
         } else {
            $form->_msg_error = $form->get_fd_message(
               'validation_error'
            );
         }
      }

      $form->add_obj(
         'product_images',
         'obj-value',
         $this->productImagesPanel(
            is_array($data) ? $data : array(),
            $isNew,
            $form
         )
      );
      $form->add_obj(
         'product_channels',
         'obj-value',
         $this->productChannelsPanel(
            is_array($data) ? $data : array(),
            $isNew,
            $form
         )
      );

      $content = $form->run();
      if (!$isNew) {
         // Eigene Upload-/Video-Formulare erst nach dem Artikel-Formular
         // einbetten, damit der Browser keine verschachtelte Form erzeugt.
         $content .= $this->shopMediaFormTemplates($this->shopMediaConfig());
      }
      return $content;
   }

   private function productsHelp(): string {
      $helpId = $this->ensureShopProductsHelpPage();
      if ($helpId > 0) {
         return $this->frame('<div class="m-3">' . $this->productsHelpHtml() . '</div>', 'Produkte Hilfe', $this->productBarActions());
      }
      return $this->frame('<div class="alert alert-warning m-3">Hilfe konnte nicht angelegt werden.</div>', 'Produkte Hilfe', $this->productBarActions());
   }

   private function orderStatusOptions($texts = null): array {
      return array(
         'new' => $texts ? $texts->get_fd_message('order_status_new', 'Neu') : 'Neu',
         'payment_pending' => $texts ? $texts->get_fd_message('order_status_payment_pending', 'Zahlung offen') : 'Zahlung offen',
         'paid' => $texts ? $texts->get_fd_message('order_status_paid', 'Bezahlt') : 'Bezahlt',
         'processing' => $texts ? $texts->get_fd_message('order_status_processing', 'In Bearbeitung') : 'In Bearbeitung',
         'shipped' => $texts ? $texts->get_fd_message('order_status_shipped', 'Versendet') : 'Versendet',
         'done' => $texts ? $texts->get_fd_message('order_status_done', 'Abgeschlossen') : 'Abgeschlossen',
         'cancelled' => $texts ? $texts->get_fd_message('order_status_cancelled', 'Storniert') : 'Storniert',
      );
   }

   private function paymentStatusOptions($texts = null): array {
      return array(
         'open' => $texts ? $texts->get_fd_message('payment_status_open', 'Offen') : 'Offen',
         'created' => $texts ? $texts->get_fd_message('payment_status_created', 'Erstellt') : 'Erstellt',
         'pending' => $texts ? $texts->get_fd_message('payment_status_pending', 'In Prüfung') : 'In Prüfung',
         'completed' => $texts ? $texts->get_fd_message('payment_status_completed', 'Abgeschlossen') : 'Abgeschlossen',
         'paid' => $texts ? $texts->get_fd_message('payment_status_paid', 'Bezahlt') : 'Bezahlt',
         'failed' => $texts ? $texts->get_fd_message('payment_status_failed', 'Fehlgeschlagen') : 'Fehlgeschlagen',
         'cancelled' => $texts ? $texts->get_fd_message('payment_status_cancelled', 'Abgebrochen') : 'Abgebrochen',
         'refunded' => $texts ? $texts->get_fd_message('payment_status_refunded', 'Erstattet') : 'Erstattet',
      );
   }

   private function shippingStatusOptions($texts = null): array {
      return array(
         'open' => $texts ? $texts->get_fd_message('shipping_status_open', 'Offen') : 'Offen',
         'ready' => $texts ? $texts->get_fd_message('shipping_status_ready', 'Bereit') : 'Bereit',
         'shipped' => $texts ? $texts->get_fd_message('shipping_status_shipped', 'Versendet') : 'Versendet',
         'delivered' => $texts ? $texts->get_fd_message('shipping_status_delivered', 'Zugestellt') : 'Zugestellt',
         'returned' => $texts ? $texts->get_fd_message('shipping_status_returned', 'Retoure') : 'Retoure',
      );
   }

   private function orderStatusBadge(string $status, $texts = null): string {
      $labels = $this->orderStatusOptions($texts);
      $classes = array(
         'new' => 'text-bg-secondary',
         'payment_pending' => 'text-bg-warning',
         'paid' => 'text-bg-success',
         'processing' => 'text-bg-info',
         'shipped' => 'text-bg-primary',
         'done' => 'text-bg-success',
         'cancelled' => 'text-bg-danger',
      );
      return '<span class="badge ' . $this->h($classes[$status] ?? 'text-bg-secondary') . '">' . $this->h($labels[$status] ?? $status) . '</span>';
   }

   private function paymentStatusBadge(string $status, $texts = null): string {
      $labels = $this->paymentStatusOptions($texts);
      $classes = array(
         'open' => 'text-bg-secondary',
         'created' => 'text-bg-info',
         'pending' => 'text-bg-warning',
         'completed' => 'text-bg-success',
         'paid' => 'text-bg-success',
         'failed' => 'text-bg-danger',
         'cancelled' => 'text-bg-danger',
         'refunded' => 'text-bg-dark',
      );
      return '<span class="badge ' . $this->h($classes[$status] ?? 'text-bg-secondary') . '">' . $this->h($labels[$status] ?? $status) . '</span>';
   }

   private function shippingStatusBadge(string $status, $texts = null): string {
      $labels = $this->shippingStatusOptions($texts);
      $classes = array(
         'open' => 'text-bg-secondary',
         'ready' => 'text-bg-info',
         'shipped' => 'text-bg-primary',
         'delivered' => 'text-bg-success',
         'returned' => 'text-bg-warning',
      );
      return '<span class="badge ' . $this->h($classes[$status] ?? 'text-bg-secondary') . '">' . $this->h($labels[$status] ?? $status) . '</span>';
   }

   private function channelLabel(string $channel): string {
      $labels = array(
         'shop' => 'Shop',
         'web' => 'Web',
         'amazon' => 'Amazon',
         'ebay' => 'eBay',
         'kleinanzeigen' => 'Kleinanzeigen',
         'mobile' => 'mobile.de',
      );
      return $labels[$channel] ?? $channel;
   }

   private function paymentProviderLabel(string $provider, $texts = null): string {
      $labels = array(
         'bank_transfer' => $texts ? $texts->get_fd_message('payment_bank_transfer', 'Vorkasse / Überweisung') : 'Vorkasse / Überweisung',
         'invoice' => $texts ? $texts->get_fd_message('payment_invoice', 'Rechnung') : 'Rechnung',
         'paypal' => 'PayPal',
         'amazon_pay' => 'Amazon Pay',
      );
      return $labels[$provider] ?? $this->channelLabel($provider);
   }

   private function channelBadge(string $channel): string {
      $class = in_array($channel, array('shop', 'web', ''), true) ? 'text-bg-secondary' : 'text-bg-info';
      $text = $channel === '' ? 'Shop' : $this->channelLabel($channel);
      return '<span class="badge ' . $class . '">' . $this->h($text) . '</span>';
   }

   private function orderActions($texts = null): string {
      $helpId = $this->ensureShopOrdersHelpPage();
      $helpTitle = $texts
         ? $texts->get_fd_message('help_orders', 'Hilfe: Bestellungen')
         : 'Hilfe: Bestellungen';
      $helpLabel = $texts
         ? $texts->get_fd_message('help_label', 'Hilfe')
         : 'Hilfe';
      $helpButton = $helpId > 0
         ? $this->openWinButton('?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId, $helpTitle, '<i class="bi bi-question-circle"></i><span class="visually-hidden"> ' . $this->h($helpLabel) . '</span>', 'btn btn-outline-secondary btn-sm me-1', '72%', '82%')
         : '';
      return $helpButton;
   }

   private function handleOrderAction($report): void {
      $deleteId = (int)dbx()->get_modul_var('delete_order', 0, 'int');
      if ($deleteId <= 0) {
         return;
      }
      if (!$this->checkActionToken('delete_order')) {
         $report->_msg_error = $report->get_fd_message('token_error');
         return;
      }
      if ($this->repo()->deleteOrder($deleteId)) {
         $report->_msg_success = $report->get_fd_message('delete_success');
      } else {
         $report->_msg_error = $report->get_fd_message('delete_error');
      }
   }

   public function order_report_next_record($report, $record) {
      $id = (int)($record['id'] ?? 0);
      $orderNo = (string)($record['order_no'] ?? '');
      $created = (string)($record['create_date'] ?? '');
      $channel = (string)($record['channel_key'] ?? 'shop');
      $items = (array)($record['items'] ?? array());
      $itemLines = '';
      foreach ($items as $item) {
         $itemLines .= '<div>' . (int)($item['qty'] ?? 0) . 'x <strong>' . $this->h($item['title'] ?? '') . '</strong> <code>' . $this->h($item['sku'] ?? '') . '</code></div>';
      }
      if ($itemLines === '') {
         $itemLines = '<span class="text-muted">'
            . $this->h($report->get_fd_message('no_items'))
            . '</span>';
      }
      $detailUrl = '?dbx_modul=dbxShop_admin&dbx_run1=order_detail&id=' . $id;
      $deleteUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=orders&delete_order=' . $id);

      $externalId = trim((string)($record['payment_reference'] ?? ''));
      $sourceText = in_array($channel, array('', 'shop', 'web'), true)
         ? $report->get_fd_message('source_shop_order')
         : $report->get_fd_message('source_channel_order');
      $externalText = $externalId !== ''
         ? '<small>' . $this->h($report->format_fd_message('external_reference', array('reference' => $externalId))) . '</small>'
         : '';
      $record['order_view'] = '<div class="dbx-shop-order-main"><strong>' . $this->h($orderNo) . '</strong><small>' . $this->h($created) . '</small><span class="dbx-shop-order-source">' . $this->channelBadge($channel) . '<small>' . $this->h($sourceText) . '</small></span>' . $externalText . '</div>';
      $record['customer_view'] = '<div class="dbx-shop-order-customer"><strong>' . $this->h($record['customer_name'] ?? '') . '</strong><small>' . $this->h($record['customer_email'] ?? '') . '</small></div>';
      $record['items_view'] = '<div class="dbx-shop-order-items-small">' . $itemLines . '</div>';
      $record['status_view'] = '<div class="dbx-shop-order-status-stack">'
         . '<span><small>' . $this->h($report->get_fd_message('label_order')) . '</small>' . $this->orderStatusBadge((string)($record['status'] ?? 'new'), $report) . '</span>'
         . '<span><small>' . $this->h($report->get_fd_message('label_shipping')) . '</small>' . $this->shippingStatusBadge((string)($record['shipping_status'] ?? 'open'), $report) . '</span>'
         . '</div>';
      $record['payment_view'] = '<div class="dbx-shop-order-payment">' . $this->paymentStatusBadge((string)($record['payment_status'] ?? 'open'), $report) . '<small>' . $this->h($this->paymentProviderLabel((string)($record['payment_provider'] ?? ''), $report)) . '</small><small>' . $this->h($record['payment_reference'] ?? '') . '</small></div>';
      $record['total_view'] = $this->money($record['total_gross'] ?? 0);
      $record['actions_view'] = '<span class="dbx-shop-order-actions">'
         . $this->openWinButton($detailUrl, $report->format_fd_message('action_edit_title', array('number' => $orderNo)), '<i class="bi bi-pencil-square"></i><span class="visually-hidden"> ' . $this->h($report->get_fd_message('action_edit')) . '</span>', 'btn btn-outline-primary btn-sm', '86%', '88%')
         . '<a class="btn btn-outline-danger btn-sm dbxConfirm" href="' . $this->h($deleteUrl) . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($report->get_fd_message('delete_title')) . '" data-confirm="' . $this->h($report->get_fd_message('delete_question')) . '" data-confirm-hint="<small>' . $this->h($report->get_fd_message('delete_hint')) . '</small>" data-confirm-buttons="yesno" title="' . $this->h($report->get_fd_message('delete_title')) . '"><i class="bi bi-trash"></i><span class="visually-hidden"> ' . $this->h($report->get_fd_message('delete_label')) . '</span></a>'
         . '</span>';
      return $record;
   }

   private function orders(): string {
      $this->ensureSeed();
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-orders-report');
      $report->_fd = 'dbxShop_admin|rpt-orders-selection';
      $report->load_fd_messages();
      $report->_action = '?dbx_modul=dbxShop_admin&dbx_run1=orders';
      $report->_mode = 'table';
      $report->_pages = true;
      $report->_create_sel_flds = true;
      $report->_but_pagination = 7;
      $report->_fld_id = 'id';
      $report->_msg_info = $report->get_fd_message('report_info');
      $report->add_rep('bar_title', $report->get_fd_message('bar_title'));
      $report->add_rep('bar_icon', 'bi-receipt');
      $report->add_rep('bar_subtitle', $report->get_fd_message('bar_subtitle'));
      $report->add_rep('bar_class', 'dbx-module-bar');
      $report->add_rep('bar_title_class', 'dbx-module-bar-titleblock');
      $report->add_rep('bar_actions_class', 'dbx-module-bar-actions');
      $report->add_rep('bar_title_pre', '');
      $report->add_rep('bar_title_heading_attrs', '');
      $report->add_rep('bar_middle', '');
      $report->add_rep('bar_extra', '');
      $report->add_rep('bar_actions', $this->orderActions($report));
      $report->add_rep('shop_admin_style', $this->shopAdminStyle());
      foreach (array(
         'order_view' => 'dbx-shop-col-order',
         'customer_view' => 'dbx-shop-col-customer',
         'items_view' => 'dbx-shop-col-items',
         'status_view' => 'dbx-shop-col-status',
         'payment_view' => 'dbx-shop-col-payment',
         'total_view' => 'dbx-shop-col-total',
         'actions_view' => 'dbx-shop-col-actions',
      ) as $field => $class) {
         $report->set_class_haeder($field, $class);
         $report->_class_body[$field] = $class;
      }
      $report->set_callback_owner($this);
      $report->set_callback('next_record', 'order_report_next_record');
      $report->create_selection_fields('dbxShop_admin|rpt-orders-selection');
      $this->handleOrderAction($report);

      $query = trim((string)$report->get_fld_val('dbx_rwhere', '', 'parameter|max=120'));
      $requestedRowsPerPage = (int)$report->get_fld_val('dbx_rrows', 30, 'int');
      $rowsPerPage = $requestedRowsPerPage === 0 ? 0 : max(10, min(100, $requestedRowsPerPage));
      $position = $rowsPerPage === 0 ? 0 : max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
      $sort = (string)$report->get_fld_val('dbx_rsort', 'create_date', 'parameter');
      $direction = strtoupper((string)$report->get_fld_val('dbx_rdesc', 'DESC', 'parameter')) === 'ASC' ? 'ASC' : 'DESC';
      $filters = array(
         'query' => $query,
         'status' => trim((string)$report->get_fld_val('status', '', 'parameter')),
         'payment_status' => trim((string)$report->get_fld_val('payment_status', '', 'parameter')),
         'shipping_status' => trim((string)$report->get_fld_val('shipping_status', '', 'parameter')),
         'channel_key' => trim((string)$report->get_fld_val('channel_key', '', 'parameter')),
      );

      $filteredCount = $this->repo()->orderCount($filters);
      if ($rowsPerPage > 0 && $position >= $filteredCount && $filteredCount > 0) {
         $position = max(0, (int)(floor(($filteredCount - 1) / $rowsPerPage) * $rowsPerPage));
      }
      $orders = $this->repo()->orders($filters, $rowsPerPage, $position, $sort, $direction);

      $report->_rflds = array(
         'order_view' => $report->get_fd_message('column_order'),
         'customer_view' => $report->get_fd_message('column_customer'),
         'items_view' => $report->get_fd_message('column_items'),
         'status_view' => $report->get_fd_message('column_status'),
         'payment_view' => $report->get_fd_message('column_payment'),
         'total_view' => $report->get_fd_message('column_total'),
         'actions_view' => $report->get_fd_message('column_action'),
      );
      $report->_rpt_format = array(
         'order_view' => 'html',
         'customer_view' => 'html',
         'items_view' => 'html',
         'status_view' => 'html',
         'payment_view' => 'html',
         'total_view' => 'html',
         'actions_view' => 'html',
      );
      $report->_rrows = $rowsPerPage;
      $report->_rpos = $position;
      $report->_count_all = $this->repo()->orderCount(array());
      $report->_rcount = $filteredCount;
      $report->_rdata = $orders;

      $content = $report->run();
      if ($filteredCount === 0) {
         $content .= '<div class="alert alert-info mx-3">'
            . $this->h($report->get_fd_message('no_results'))
            . '</div>';
      }
      return $content;
   }

   public function withdrawal_report_next_record($report, $record) {
      if (!is_array($record)) {
         return $record;
      }
      $record['request_view'] = '<div><strong>' . $this->h($record['order_no'] ?? $report->get_fd_message('without_order_no')) . '</strong><br><small>' . $this->h($record['create_date'] ?? '') . '</small></div>';
      $record['customer_view'] = '<div><strong>' . $this->h($record['customer_name'] ?? '') . '</strong><br><small>' . $this->h($record['customer_email'] ?? '') . '</small></div>';
      $record['message_view'] = '<div class="small">' . nl2br($this->h($record['reason'] ?? '')) . '</div>';
      $status = (string)($record['status'] ?? '');
      $badge = in_array($status, array('accepted', 'refunded', 'closed'), true) ? 'text-bg-success' : (in_array($status, array('rejected'), true) ? 'text-bg-danger' : 'text-bg-warning');
      $statusLabel = $report->get_fd_message('status_' . $status, $status);
      $record['status_view'] = '<span class="badge ' . $badge . '">' . $this->h($statusLabel) . '</span>';
      $id = (int)($record['id'] ?? 0);
      $base = '?dbx_modul=dbxShop_admin&dbx_run1=returns&withdrawal_id=' . $id . '&withdrawal_status=';
      $record['actions_view'] =
         '<div class="btn-group btn-group-sm" role="group">'
         . '<a class="btn btn-outline-secondary" href="' . $this->h($this->actionUrl($base . 'processing')) . '" title="' . $this->h($report->get_fd_message('action_processing')) . '"><i class="bi bi-hourglass-split"></i></a>'
         . '<a class="btn btn-outline-success dbxConfirm" href="' . $this->h($this->actionUrl($base . 'accepted')) . '" data-confirm-title="' . $this->h($report->get_fd_message('action_accept_title')) . '" data-confirm="' . $this->h($report->get_fd_message('action_accept_question')) . '" data-confirm-buttons="yesno" title="' . $this->h($report->get_fd_message('action_accept')) . '"><i class="bi bi-check2"></i></a>'
         . '<a class="btn btn-outline-primary dbxConfirm" href="' . $this->h($this->actionUrl($base . 'refunded')) . '" data-confirm-title="' . $this->h($report->get_fd_message('action_refund_title')) . '" data-confirm="' . $this->h($report->get_fd_message('action_refund_question')) . '" data-confirm-buttons="yesno" title="' . $this->h($report->get_fd_message('action_refunded')) . '"><i class="bi bi-cash-coin"></i></a>'
         . '<a class="btn btn-outline-danger" href="' . $this->h($this->actionUrl($base . 'rejected')) . '" title="' . $this->h($report->get_fd_message('action_reject')) . '"><i class="bi bi-x-lg"></i></a>'
         . '<a class="btn btn-outline-secondary" href="' . $this->h($this->actionUrl($base . 'closed')) . '" title="' . $this->h($report->get_fd_message('action_close')) . '"><i class="bi bi-archive"></i></a>'
         . '</div>';
      return $record;
   }

   private function returns(): string {
      $this->ensureSeed();
      $withdrawalId = (int)dbx()->get_modul_var('withdrawal_id', 0, 'int');
      $withdrawalStatus = (string)dbx()->get_modul_var('withdrawal_status', '', 'parameter');
      if ($withdrawalId > 0 && $withdrawalStatus !== '') {
         if ($this->checkActionToken('withdrawal_status')) {
            $this->repo()->updateWithdrawalAdmin($withdrawalId, $withdrawalStatus);
         }
      }
      $report = dbx()->get_system_obj('dbxReport');
      $report->init('shop-orders-report');
      $report->_fd = 'dbxShop_admin|rpt-withdrawals-selection';
      $report->load_fd_messages();
      $report->_action = '?dbx_modul=dbxShop_admin&dbx_run1=returns';
      $report->_mode = 'table';
      $report->_pages = true;
      $report->_create_sel_flds = true;
      $report->_but_pagination = 7;
      $report->_fld_id = 'id';
      $report->_msg_info = $report->get_fd_message('report_info');
      if ($this->postedFormError !== '') {
         $report->_msg_error = $report->get_fd_message('token_error');
         $this->postedFormError = '';
      }
      $report->add_rep('bar_title', $report->get_fd_message('bar_title'));
      $report->add_rep('bar_icon', 'bi-arrow-counterclockwise');
      $report->add_rep('bar_subtitle', $report->get_fd_message('bar_subtitle'));
      $report->add_rep('bar_class', 'dbx-module-bar');
      $report->add_rep('bar_title_class', 'dbx-module-bar-titleblock');
      $report->add_rep('bar_actions_class', 'dbx-module-bar-actions');
      $report->add_rep('bar_title_pre', '');
      $report->add_rep('bar_title_heading_attrs', '');
      $report->add_rep('bar_middle', '');
      $report->add_rep('bar_extra', '');
      $shopService = dbx()->get_include_obj('dbxShopService', 'dbxShop');
      $pages = is_object($shopService) && method_exists($shopService, 'ensureShopLegalPages') ? $shopService->ensureShopLegalPages() : array();
      $withdrawalCid = (int)($pages['withdrawal'] ?? 0);
      $cmsButton = $withdrawalCid > 0
         ? $this->openWinButton('?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=' . $withdrawalCid, $report->get_fd_message('edit_legal_title'), '<i class="bi bi-pencil-square"></i><span class="visually-hidden"> ' . $this->h($report->get_fd_message('edit_cms')) . '</span>', 'btn btn-outline-primary btn-sm me-1', '94%', '92%')
         : '';
      $report->add_rep('bar_actions',
         $cmsButton
         . $this->openWinButton('?dbx_modul=dbxShop&dbx_run1=withdrawal', $report->get_fd_message('view_page_title'), '<i class="bi bi-box-arrow-up-right"></i><span class="visually-hidden"> ' . $this->h($report->get_fd_message('shop_view')) . '</span>', 'btn btn-outline-primary btn-sm me-1', '82%', '86%')
      );
      $report->add_rep('shop_admin_style', $this->shopAdminStyle());
      $report->set_callback_owner($this);
      $report->set_callback('next_record', 'withdrawal_report_next_record');
      $report->create_selection_fields('dbxShop_admin|rpt-withdrawals-selection');

      $query = trim((string)$report->get_fld_val('dbx_rwhere', '', 'parameter|max=120'));
      $rowsPerPage = (int)$report->get_fld_val('dbx_rrows', 30, 'int');
      $rowsPerPage = $rowsPerPage === 0 ? 0 : max(10, min(100, $rowsPerPage));
      $position = $rowsPerPage === 0 ? 0 : max(0, (int)$report->get_fld_val('dbx_rpos', 0, 'int'));
      $filters = array(
         'query' => $query,
         'status' => trim((string)$report->get_fld_val('status', '', 'parameter')),
      );
      $filteredCount = $this->repo()->withdrawalCount($filters);
      $rows = $this->repo()->withdrawals($filters, $rowsPerPage, $position);

      $report->_rflds = array(
         'request_view' => $report->get_fd_message('column_withdrawal'),
         'customer_view' => $report->get_fd_message('column_customer'),
         'message_view' => $report->get_fd_message('column_message'),
         'status_view' => $report->get_fd_message('column_status'),
         'actions_view' => $report->get_fd_message('column_action'),
      );
      $report->_rpt_format = array(
         'request_view' => 'html',
         'customer_view' => 'html',
         'message_view' => 'html',
         'status_view' => 'html',
         'actions_view' => 'html',
      );
      $report->_rrows = $rowsPerPage;
      $report->_rpos = $position;
      $report->_count_all = $this->repo()->withdrawalCount(array());
      $report->_rcount = $filteredCount;
      $report->_rdata = $rows;

      $content = $report->run();
      if ($filteredCount === 0) {
         $content .= '<div class="alert alert-info mx-3">'
            . $this->h($report->get_fd_message('no_results'))
            . '</div>';
      }
      return $content;
   }

   private function orderMetaHtml(array $order, $texts): string {
      $channel = (string)($order['channel_key'] ?? 'shop');
      $source = $this->channelLabel($channel) . ' '
         . (in_array($channel, array('shop', 'web', ''), true)
            ? $texts->get_fd_message('source_order_suffix')
            : $texts->get_fd_message('source_channel_order'));
      $paymentStatuses = $this->paymentStatusOptions($texts);
      $shippingStatuses = $this->shippingStatusOptions($texts);
      $rows = array(
         $texts->get_fd_message('meta_order_no') => $order['order_no'] ?? '',
         $texts->get_fd_message('meta_created') => $order['create_date'] ?? '',
         $texts->get_fd_message('meta_customer') => trim((string)($order['customer_name'] ?? '') . ' <' . (string)($order['customer_email'] ?? '') . '>'),
         $texts->get_fd_message('meta_phone') => $order['customer_phone'] ?? '',
         $texts->get_fd_message('meta_shipping_address') => $order['shipping_address'] ?? '',
         $texts->get_fd_message('meta_source') => $source,
         $texts->get_fd_message('meta_external_order') => $order['payment_reference'] ?? '',
         $texts->get_fd_message('meta_payment_method') => $this->paymentProviderLabel((string)($order['payment_provider'] ?? ''), $texts),
         $texts->get_fd_message('meta_payment_status') => $paymentStatuses[(string)($order['payment_status'] ?? '')] ?? ($order['payment_status'] ?? ''),
         $texts->get_fd_message('meta_invoice_no') => $order['invoice_no'] ?? '',
         $texts->get_fd_message('meta_invoice_date') => $order['invoice_date'] ?? '',
         $texts->get_fd_message('meta_invoice_pdf') => trim((string)($order['invoice_pdf_path'] ?? '')) !== '' ? (string)$order['invoice_pdf_path'] : $texts->get_fd_message('not_generated'),
         $texts->get_fd_message('meta_stock_reserved') => !empty($order['stock_reserved']) ? $texts->get_fd_message('yes') : $texts->get_fd_message('no'),
         $texts->get_fd_message('meta_stock_released') => !empty($order['stock_released']) ? $texts->get_fd_message('yes') . ', ' . (string)($order['stock_released_date'] ?? '') : $texts->get_fd_message('no'),
         $texts->get_fd_message('meta_shipping_status') => $shippingStatuses[(string)($order['shipping_status'] ?? '')] ?? ($order['shipping_status'] ?? ''),
         $texts->get_fd_message('meta_shipping_provider') => $order['shipping_provider'] ?? '',
         $texts->get_fd_message('meta_tracking_no') => $order['tracking_no'] ?? '',
         $texts->get_fd_message('meta_total') => $this->money($order['total_gross'] ?? 0),
      );
      $html = '<dl class="dbx-shop-order-meta">';
      foreach ($rows as $label => $value) {
         $html .= '<dt>' . $this->h($label) . '</dt><dd>' . $this->h($value) . '</dd>';
      }
      $html .= '</dl>';
      return $html;
   }

   private function orderHistoryHtml(array $order, $texts): string {
      $rows = (array)($order['history'] ?? array());
      if ($rows === array()) {
         return '<div class="text-muted small">' . $this->h($texts->get_fd_message('history_empty')) . '</div>';
      }
      $html = '<div class="dbx-shop-order-history">';
      foreach ($rows as $row) {
         $event = (string)($row['event_type'] ?? '');
         $old = (string)($row['old_value'] ?? '');
         $new = (string)($row['new_value'] ?? '');
         $msg = (string)($row['message'] ?? '');
         $html .= '<div class="dbx-shop-order-history-item">'
            . '<strong>' . $this->h($event) . '</strong>'
            . '<small>' . $this->h($row['create_date'] ?? '') . '</small>'
            . ($old !== '' || $new !== '' ? '<code>' . $this->h($old) . ' -> ' . $this->h($new) . '</code>' : '')
            . ($msg !== '' ? '<span>' . $this->h($msg) . '</span>' : '')
            . '</div>';
      }
      $html .= '</div>';
      return $html;
   }

   private function orderWithdrawalsHtml(array $order, $texts): string {
      $rows = (array)($order['withdrawals'] ?? array());
      if ($rows === array()) {
         return '<div class="text-muted small">' . $this->h($texts->get_fd_message('withdrawals_empty')) . '</div>';
      }
      $html = '<div class="dbx-shop-order-withdrawals">';
      foreach ($rows as $row) {
         $html .= '<div class="alert alert-warning py-2 mb-2">'
            . '<strong>' . $this->h($row['customer_name'] ?? '') . '</strong> '
            . '<span class="badge text-bg-warning">' . $this->h($row['status'] ?? '') . '</span>'
            . '<br><small>' . $this->h($row['create_date'] ?? '') . ' · ' . $this->h($row['customer_email'] ?? '') . '</small>'
            . '<div class="mt-1">' . nl2br($this->h($row['reason'] ?? '')) . '</div>'
            . '</div>';
      }
      $html .= '</div>';
      return $html;
   }

   private function orderItemsHtml(array $order, $texts): string {
      $items = (array)($order['items'] ?? array());
      if ($items === array()) {
         return '<div class="text-muted small">' . $this->h($texts->get_fd_message('items_empty')) . '</div>';
      }
      $html = '<div class="table-responsive"><table class="table table-sm table-bordered dbx-shop-order-items-table"><thead><tr>'
         . '<th>' . $this->h($texts->get_fd_message('items_product')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('items_quantity')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('items_unit_price')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('items_tax')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('items_shipping')) . '</th><th class="text-end">' . $this->h($texts->get_fd_message('items_total')) . '</th>'
         . '</tr></thead><tbody>';
      foreach ($items as $item) {
         $html .= '<tr>'
            . '<td><strong>' . $this->h($item['title'] ?? '') . '</strong><br><code>' . $this->h($item['sku'] ?? '') . '</code></td>'
            . '<td class="text-end">' . (int)($item['qty'] ?? 0) . '</td>'
            . '<td class="text-end">' . $this->money($item['price_gross'] ?? 0) . '</td>'
            . '<td class="text-end">' . $this->h(number_format((float)($item['tax_rate'] ?? 0), 2, ',', '.')) . ' %</td>'
            . '<td class="text-end">' . $this->money($item['shipping_gross'] ?? 0) . '</td>'
            . '<td class="text-end">' . $this->money($item['total_gross'] ?? 0) . '</td>'
            . '</tr>';
      }
      $html .= '</tbody></table></div>';
      return $html;
   }

   private function orderPayloadHtml(array $order, $texts): string {
      $blocks = array(
         $texts->get_fd_message('payload_payment') => trim((string)($order['payment_payload'] ?? '')),
         $texts->get_fd_message('payload_legal') => trim((string)($order['legal_snapshot'] ?? '')),
         $texts->get_fd_message('payload_withdrawal') => trim((string)($order['withdrawal_snapshot'] ?? '')),
      );
      $html = '';
      foreach ($blocks as $title => $payload) {
         if ($payload === '') {
            continue;
         }
         $decoded = json_decode($payload, true);
         if (is_array($decoded)) {
            $payload = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
         }
         $html .= '<details class="mb-2"><summary>' . $this->h($title) . '</summary><pre>' . $this->h($payload) . '</pre></details>';
      }
      if ($html === '') {
         return '<div class="text-muted small">' . $this->h($texts->get_fd_message('payload_empty')) . '</div>';
      }
      return $html;
   }

   private function statusMailChangesHtml(array $before, array $order): string {
      $rows = array(
         'Bestellstatus' => array($this->orderStatusOptions(), 'status'),
         'Zahlungsstatus' => array($this->paymentStatusOptions(), 'payment_status'),
         'Versandstatus' => array($this->shippingStatusOptions(), 'shipping_status'),
      );
      $html = '';
      foreach ($rows as $label => $cfg) {
         [$options, $field] = $cfg;
         $old = (string)($before[$field] ?? '');
         $new = (string)($order[$field] ?? '');
         if ($old === $new) {
            continue;
         }
         $html .= '<tr><th align="left">' . $this->h($label) . '</th><td>' . $this->h($options[$old] ?? $old) . '</td><td>' . $this->h($options[$new] ?? $new) . '</td></tr>';
      }
      if ($html === '') {
         return '<dl>'
            . '<dt>Bestellstatus</dt><dd>' . $this->h($this->orderStatusOptions()[(string)($order['status'] ?? '')] ?? (string)($order['status'] ?? '')) . '</dd>'
            . '<dt>Zahlungsstatus</dt><dd>' . $this->h($this->paymentStatusOptions()[(string)($order['payment_status'] ?? '')] ?? (string)($order['payment_status'] ?? '')) . '</dd>'
            . '<dt>Versandstatus</dt><dd>' . $this->h($this->shippingStatusOptions()[(string)($order['shipping_status'] ?? '')] ?? (string)($order['shipping_status'] ?? '')) . '</dd>'
            . '</dl>';
      }
      return '<table border="0" cellpadding="6" cellspacing="0">'
         . '<thead><tr><th align="left">Feld</th><th align="left">Vorher</th><th align="left">Jetzt</th></tr></thead>'
         . '<tbody>' . $html . '</tbody></table>';
   }

   private function sendOrderStatusMail(array $before, array $order): array {
      $cfg = $this->shopConfig();
      $from = trim((string)($cfg['mail_from'] ?? ''));
      $to = trim((string)($order['customer_email'] ?? ''));
      if ($from === '') {
         return array(false, 'Kundenmail wurde nicht gesendet: Mail-Absender fehlt in den Shop-Einstellungen.');
      }
      if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
         return array(false, 'Kundenmail wurde nicht gesendet: Die Bestellung hat keine gueltige Kunden-E-Mail.');
      }

      $orderNo = (string)($order['order_no'] ?? '');
      $subject = 'Aktualisierung Ihrer Bestellung ' . $orderNo;
      $trackingNo = trim((string)($order['tracking_no'] ?? ''));
      $trackingUrl = trim((string)($order['tracking_url'] ?? ''));
      $invoiceNo = trim((string)($order['invoice_no'] ?? ''));
      $extra = '';
      if ($trackingNo !== '') {
         $extra .= '<p><strong>Trackingnummer:</strong> ' . $this->h($trackingNo) . '</p>';
      }
      if ($trackingUrl !== '') {
         $extra .= '<p><a href="' . $this->h($trackingUrl) . '">Sendung verfolgen</a></p>';
      }
      if ($invoiceNo !== '') {
         $extra .= '<p><strong>Rechnung:</strong> ' . $this->h($invoiceNo) . '</p>';
      }
      $html = '<h2>Ihre Bestellung wurde aktualisiert</h2>'
         . '<p>Bestellnummer: <strong>' . $this->h($orderNo) . '</strong></p>'
         . $this->statusMailChangesHtml($before, $order)
         . $extra
         . '<p>Viele Gruesse<br>Ihr Shop-Team</p>';

      try {
         dbx()->send_mail($from, $to, $subject, $html, 'html');
         $this->repo()->addOrderHistory((int)($order['id'] ?? 0), 'customer_mail', '', $to, 'Statusbenachrichtigung wurde an den Kunden gesendet.');
         return array(true, 'Kundenmail wurde gesendet.');
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxShop_admin', (string)($order['id'] ?? ''), 'order status mail failed', $e->getMessage());
         return array(false, 'Kundenmail konnte nicht gesendet werden: ' . $e->getMessage());
      }
   }

   private function notifyCustomerHint(array $order, $texts): string {
      $email = trim((string)($order['customer_email'] ?? ''));
      return $email === ''
         ? $texts->get_fd_message('notify_no_email')
         : $texts->format_fd_message(
            'notify_hint',
            array('email' => $email)
         );
   }

   private function orderQuickActionsHtml(array $order, $texts): string {
      $id = (int)($order['id'] ?? 0);
      if ($id <= 0) {
         return '';
      }
      $base = '?dbx_modul=dbxShop_admin&dbx_run1=order_detail&dbx_run2=quick_action&id=' . $id . '&order_action=';
      $actions = array(
         'mark_paid' => array('bi-cash-coin', $texts->get_fd_message('quick_paid'), $texts->get_fd_message('quick_paid_confirm')),
         'processing' => array('bi-tools', $texts->get_fd_message('quick_processing'), $texts->get_fd_message('quick_processing_confirm')),
         'ready' => array('bi-box-seam', $texts->get_fd_message('quick_ready'), $texts->get_fd_message('quick_ready_confirm')),
         'shipped' => array('bi-truck', $texts->get_fd_message('quick_shipped'), $texts->get_fd_message('quick_shipped_confirm')),
         'delivered' => array('bi-check2-circle', $texts->get_fd_message('quick_delivered'), $texts->get_fd_message('quick_delivered_confirm')),
         'cancel' => array('bi-x-circle', $texts->get_fd_message('quick_cancel'), $texts->get_fd_message('quick_cancel_confirm')),
         'refund' => array('bi-arrow-counterclockwise', $texts->get_fd_message('quick_refund'), $texts->get_fd_message('quick_refund_confirm')),
      );
      $html = '<div class="dbx-shop-order-quick-actions" data-dbx="lib=confirm|class=dbxConfirm|bind=link">'
         . '<strong>' . $this->h($texts->get_fd_message('quick_actions')) . '</strong><div class="dbx-shop-order-quick-action-buttons">';
      foreach ($actions as $action => $cfg) {
         [$icon, $label, $confirm] = $cfg;
         $btnClass = in_array($action, array('cancel', 'refund'), true) ? 'btn-outline-danger' : 'btn-outline-primary';
         $html .= '<a class="btn btn-sm ' . $btnClass . ' dbxConfirm" href="' . $this->h($this->actionUrl($base . rawurlencode($action))) . '"'
            . ' data-confirm-title="<i class=\'bi ' . $this->h($icon) . '\'></i> ' . $this->h($label) . '"'
            . ' data-confirm="' . $this->h($confirm) . '"'
            . ' data-confirm-buttons="yesno"'
            . ' title="' . $this->h($label) . '">'
            . '<i class="bi ' . $this->h($icon) . '"></i> ' . $this->h($label) . '</a>';
      }
      $html .= '</div></div>';
      return $html;
   }

   private function orderDetailActions(int $id, $texts): string {
      $helpId = $this->ensureShopOrdersHelpPage();
      $helpButton = $helpId > 0
         ? $this->openWinButton('?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId, $texts->get_fd_message('help_orders'), '<i class="bi bi-question-circle"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('help_label')) . '</span>', 'btn btn-outline-secondary btn-sm ms-1', '72%', '82%')
         : '';
      $deleteUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=orders&delete_order=' . $id);
      $mailUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=order_detail&dbx_run2=send_status_mail&id=' . $id);
      $invoicePdfUrl = $this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=order_invoice_pdf&id=' . $id);
      return '<button class="btn btn-primary btn-sm" type="submit" title="' . $this->h($texts->get_fd_message('save_order_title')) . '"><i class="bi bi-save"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('save_label')) . '</span></button>'
         . $this->openWinButton('?dbx_modul=dbxShop_admin&dbx_run1=order_invoice&id=' . $id, $texts->get_fd_message('invoice_view'), '<i class="bi bi-file-earmark-text"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('invoice_label')) . '</span>', 'btn btn-outline-primary btn-sm ms-1', '82%', '86%')
         . '<a class="btn btn-outline-danger btn-sm ms-1" href="' . $this->h($invoicePdfUrl) . '" target="_blank" rel="noopener" title="' . $this->h($texts->get_fd_message('invoice_pdf_title')) . '"><i class="bi bi-file-earmark-pdf"></i><span class="visually-hidden"> PDF</span></a>'
         . '<a class="btn btn-outline-primary btn-sm ms-1 dbxConfirm" href="' . $this->h($mailUrl) . '" data-confirm-title="<i class=\'bi bi-envelope\'></i> ' . $this->h($texts->get_fd_message('customer_mail_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('customer_mail_question')) . '" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('customer_mail_title')) . '"><i class="bi bi-envelope"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('customer_mail_label')) . '</span></a>'
         . '<a class="btn btn-outline-danger btn-sm ms-1 dbxConfirm" href="' . $this->h($deleteUrl) . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($texts->get_fd_message('delete_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('delete_question')) . '" data-confirm-buttons="yesno" title="' . $this->h($texts->get_fd_message('delete_title')) . '"><i class="bi bi-trash"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('delete_label')) . '</span></a>'
         . $helpButton
         . $this->openWinButton('?dbx_modul=dbxShop_admin&dbx_run1=orders', $texts->get_fd_message('order_list_title'), '<i class="bi bi-table"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('order_list_label')) . '</span>', 'btn btn-outline-secondary btn-sm ms-1', '92%', '88%');
   }

   private function orderDetail(): string {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $order = $id > 0 ? $this->repo()->orderById($id) : null;
      if (!is_array($order)) {
         return $this->frame('<div class="alert alert-warning m-3">Bestellung nicht gefunden.</div>', 'Bestellung bearbeiten', $this->orderActions());
      }
      $quickMessage = '';
      $quickError = '';
      $quickAction = (string)dbx()->get_modul_var('order_action', '', 'parameter');
      if ((string)dbx()->get_modul_var('dbx_run2', '', 'parameter') === 'quick_action' && $quickAction !== '') {
         if (!$this->checkActionToken('order_quick_action')) {
            $quickOk = false;
            $quickMsg = $this->postedFormError;
         } else {
            [$quickOk, $quickMsg] = $this->repo()->updateOrderQuickAction($id, $quickAction);
         }
         if ($quickOk) {
            $quickMessage = $quickMsg;
            $order = $this->repo()->orderById($id) ?: $order;
         } else {
            $quickError = $quickMsg;
         }
      }
      $mailMessage = '';
      $mailError = '';
      $sendStatusMail = (string)dbx()->get_modul_var('dbx_run2', '', 'parameter') === 'send_status_mail'
         || (string)($_GET['dbx_run2'] ?? '') === 'send_status_mail';
      if ($sendStatusMail) {
         if (!$this->checkActionToken('send_status_mail')) {
            $mailOk = false;
            $mailMsg = $this->postedFormError;
         } else {
            [$mailOk, $mailMsg] = $this->sendOrderStatusMail($order, $order);
         }
         if ($mailOk) {
            $mailMessage = $mailMsg;
            $order = $this->repo()->orderById($id) ?: $order;
         } else {
            $mailError = $mailMsg;
         }
      }

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('shop-order-form', 'shop-order-form');
      $form->_dd = 'dbxShop|shopOrder';
      $form->_fd = 'dbxShop_admin|rpt-orders-selection';
      $form->load_fd_messages();
      $form->_data = $order;
      $form->_rid = $id;
      $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=order_detail&id=' . $id;
      $form->set_activ_id($id);
      $form->add_rep('bar_title', $form->get_fd_message('detail_title'));
      $form->add_rep('bar_icon', 'bi-receipt');
      $form->add_rep('bar_subtitle', (string)($order['order_no'] ?? ''));
      $form->add_rep('bar_class', 'dbx-module-bar');
      $form->add_rep('bar_title_class', 'dbx-module-bar-titleblock');
      $form->add_rep('bar_title_pre', '');
      $form->add_rep('bar_title_heading_attrs', '');
      $form->add_rep('bar_middle', '');
      $form->add_rep('bar_actions_class', 'dbx-module-bar-actions');
      $form->add_rep('bar_actions', $this->orderDetailActions($id, $form));
      $form->add_rep('bar_extra', '');
      $form->add_rep('shop_admin_style', $this->shopAdminStyle());
      $form->_msg_info = $form->get_fd_message('detail_info');
      $form->add_fld('status', tpl: 'select-single-label', label: $form->get_fd_message('field_order_status'), options: $this->orderStatusOptions($form));
      $form->add_fld('payment_status', tpl: 'select-single-label', label: $form->get_fd_message('field_payment_status'), options: $this->paymentStatusOptions($form));
      $form->add_fld('payment_reference', tpl: 'text-label', label: $form->get_fd_message('field_payment_reference'), rules: '*|max=180', placeholder: 'PAYID-xxxx / Channel-Order-ID');
      $form->add_fld('invoice_no', tpl: 'text-label', label: $form->get_fd_message('field_invoice_no'), rules: '*|max=60', placeholder: 'R2026-00001');
      $form->add_fld('invoice_date', tpl: 'text-label', label: $form->get_fd_message('field_invoice_date'), rules: '*|date', placeholder: date('Y-m-d'));
      $form->add_fld('shipping_status', tpl: 'select-single-label', label: $form->get_fd_message('field_shipping_status'), options: $this->shippingStatusOptions($form));
      $form->add_fld('shipping_provider', tpl: 'text-label', label: $form->get_fd_message('field_shipping_provider'), rules: '*|max=120', placeholder: 'DHL, UPS');
      $form->add_fld('tracking_no', tpl: 'text-label', label: $form->get_fd_message('field_tracking_no'), rules: '*|max=180', placeholder: '00340434123456789012');
      $form->add_fld('tracking_url', tpl: 'text-label', label: $form->get_fd_message('field_tracking_url'), rules: '*|max=255', placeholder: 'https://...');
      $form->add_fld('shipped_date', tpl: 'text-label', label: $form->get_fd_message('field_shipped_date'), rules: '*|datetime', placeholder: date('Y-m-d H:i:s'));
      $form->add_fld('note', tpl: 'textarea-label', label: $form->get_fd_message('field_note'), rules: '*|max=5000', data: 'rows=5', placeholder: $form->get_fd_message('note_placeholder'));
      $form->add_rep('order_notice', '');

      if ($form->submit()) {
         if (!$form->errors()) {
            if ($this->repo()->updateOrderAdmin($id, $_POST)) {
               $form->_msg_success = $form->get_fd_message(
                  'order_save_success'
               );
               $order = $this->repo()->orderById($id) ?: $order;
               $form->_data = $order;
            } else {
               $form->_msg_error = $form->get_fd_message(
                  'order_save_error'
               );
            }
         } else {
            $form->_msg_error = $form->get_fd_message(
               'validation_error'
            );
         }
      }

      $actionMessage = $quickMessage !== '' ? $quickMessage : $mailMessage;
      $actionError = $quickError !== '' ? $quickError : $mailError;
      if ($actionMessage !== '' || $actionError !== '') {
         $form->_form_submit = 1;
         $form->_msg_info = '';
         if ($actionError !== '') {
            $form->_msg_error = $actionError;
            $form->add_fld_error('general', $actionError);
         } else {
            $form->_msg_success = $actionMessage;
         }
      }

      $form->add_rep('notify_customer_hint', $this->h($this->notifyCustomerHint($order, $form)));
      $form->add_rep('order_quick_actions', $this->orderQuickActionsHtml($order, $form));
      $form->add_obj('order_meta', 'obj-value', $this->orderMetaHtml($order, $form));
      $form->add_obj('order_items', 'obj-value', $this->orderItemsHtml($order, $form));
      $form->add_obj('order_payload', 'obj-value', $this->orderPayloadHtml($order, $form));
      $form->add_obj('order_history', 'obj-value', $this->orderHistoryHtml($order, $form));
      $form->add_obj('order_withdrawals', 'obj-value', $this->orderWithdrawalsHtml($order, $form));
      return $form->run();
   }

   private function orderInvoice(): string {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $order = $id > 0 ? $this->repo()->orderById($id) : null;
      if (!is_array($order)) {
         return $this->frame('<div class="alert alert-warning m-3">Bestellung nicht gefunden.</div>', 'Rechnung');
      }
      $invoiceNo = trim((string)($order['invoice_no'] ?? ''));
      if ($invoiceNo === '') {
         $invoiceNo = 'Entwurf';
      }
      $rows = '';
      foreach ((array)($order['items'] ?? array()) as $item) {
         $rows .= $this->tpl()->get_tpl('dbxShop_admin|order-invoice-row', array(
            'title' => $this->h($item['title'] ?? ''),
            'sku' => $this->h($item['sku'] ?? ''),
            'qty' => (string)(int)($item['qty'] ?? 0),
            'price_gross' => $this->money($item['price_gross'] ?? 0),
            'tax_rate' => $this->h(number_format((float)($item['tax_rate'] ?? 0), 2, ',', '.')),
            'total_gross' => $this->money($item['total_gross'] ?? 0),
         ));
      }
      $html = $this->tpl()->get_tpl('dbxShop_admin|order-invoice', array(
         'invoice_no' => $this->h($invoiceNo),
         'invoice_date' => $this->h($order['invoice_date'] ?? date('Y-m-d')),
         'order_no' => $this->h($order['order_no'] ?? ''),
         'customer_name' => $this->h($order['customer_name'] ?? ''),
         'customer_email' => $this->h($order['customer_email'] ?? ''),
         'shipping_address' => nl2br($this->h($order['shipping_address'] ?? '')),
         'rows' => $rows,
         'total_gross' => $this->money($order['total_gross'] ?? 0),
      ));
      return $this->frame($html, 'Rechnung ' . $invoiceNo, '');
   }

   private function orderInvoicePdf(): string {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      if (!$this->checkActionToken('order_invoice_pdf')) {
         return $this->frame('<div class="alert alert-danger m-3">' . $this->h($this->postedFormError) . '</div>', 'Rechnung');
      }
      $order = $id > 0 ? $this->repo()->ensureOrderInvoicePdf($id) : null;
      if (!is_array($order)) {
         return $this->frame('<div class="alert alert-warning m-3">Rechnungs-PDF konnte nicht erzeugt werden.</div>', 'Rechnung');
      }
      $file = $this->repo()->invoicePdfAbsolutePath($order);
      if ($file === '') {
         return $this->frame('<div class="alert alert-warning m-3">Rechnungs-PDF ist nicht verfuegbar.</div>', 'Rechnung');
      }
      if (!headers_sent()) {
         header('Content-Type: application/pdf');
         header('Content-Disposition: inline; filename="' . basename($file) . '"');
         header('Content-Length: ' . filesize($file));
      }
      readfile($file);
      exit;
   }

   private function shopAdminCardForm(string $fid, string $dd, array $data, int $id, string $action, string $shopAction, string $saveAction, string $titleHtml, string $subtitle = '', string $cardClass = '') {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init($fid, 'shop-admin-card-form');
      $form->_dd = $dd;
      $form->_fd = array(
         'dbxShop|shopProductGroup' => 'dbxShop|shop-product-group',
         'dbxShop|shopAttributeDefinition' => 'dbxShop|shop-attribute-definition',
         'dbxShop|shopProductAttributeValue' => 'dbxShop|shop-product-attribute-value',
         'dbxShop|shopShippingGroup' => 'dbxShop|shop-shipping-group',
         'dbxShop|shopChannelGroup' => 'dbxShop|shop-channel-group',
      )[$dd] ?? '';
      if ($form->_fd !== '') {
         $form->load_fd_messages();
      }
      $form->set_form_help_enabled(false);
      $form->_data = $data + array('id' => $id);
      $form->_rid = $id;
      $form->_action = $action;
      $form->set_activ_id($id);
      $form->add_rep('form_class', 'dbx-shop-admin-card-dbXForm');
      $form->add_rep('form_attrs', 'data-target="dbxForm_{i}" data-dbx="lib=confirm|class=dbxConfirm|bind=button"');
      $form->add_rep('shop_action', $this->h($shopAction));
      $form->add_rep('save_action', $this->h($saveAction));
      $form->add_rep('record_id', (string)$id);
      $form->add_rep('extra_hidden', '');
      $form->add_rep('card_title', $titleHtml);
      $form->add_rep('card_subtitle', $this->h($subtitle));
      $form->add_rep('card_badges', '');
      $form->add_rep('card_class', $this->h($cardClass));
      $form->add_rep('form_body', '');
      $form->add_rep('delete_button', '');
      $form->_msg_info = '';
      return $form;
   }

   private function shopAdminCardDeleteButton(string $action, string $title, string $message): string {
      return '<button class="btn btn-outline-danger btn-sm dbxConfirm" name="shop_action" value="' . $this->h($action) . '" title="' . $this->h($title) . '" data-confirm-title="' . $this->h($title) . '" data-confirm="' . $this->h($message) . '" data-confirm-buttons="yesno"><i class="bi bi-trash"></i></button>';
   }

   private function activeBadge(array $row, $texts = null): string {
      $texts = $texts ?: $this->catalogTexts();
      return ((int)($row['active'] ?? 0) === 1)
         ? '<span class="badge text-bg-success">' . $this->h($texts->get_fd_message('active')) . '</span>'
         : '<span class="badge text-bg-secondary">' . $this->h($texts->get_fd_message('inactive')) . '</span>';
   }

   private function groups(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      if ($this->posted('delete_product_group')) {
         $this->repo()->deleteProductGroup((int)($_POST['id'] ?? 0));
      } elseif ($this->posted('save_product_group')) {
         $this->repo()->updateProductGroup((int)($_POST['id'] ?? 0), $_POST);
      }

      $cardHtml = function (array $group, bool $isNew = false) use ($texts): string {
         $id = (int)($group['id'] ?? 0);
         if ($isNew) {
            $title = '<span>' . $this->h($texts->get_fd_message('groups_new')) . '</span>';
            $subtitle = $texts->get_fd_message('groups_new_subtitle');
         } else {
            $title = '<code>' . $this->h($group['group_key'] ?? '') . '</code><span>' . $this->h($group['title'] ?? '') . '</span>';
            $subtitle = trim((string)($group['description'] ?? ''));
         }

         $form = $this->shopAdminCardForm(
            'shop-product-group-' . ($isNew ? 'new' : $id),
            'dbxShop|shopProductGroup',
            $group,
            $id,
            '?dbx_modul=dbxShop_admin&dbx_run1=groups' . ($isNew ? '&new=1' : ''),
            'save_product_group',
            'save_product_group',
            $title,
            $subtitle,
            'dbx-shop-product-group-card'
         );
         $form->add_rep('card_badges', $isNew ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('new')) . '</span>' : $this->activeBadge($group, $texts));
         $form->add_rep('extra_hidden', '<input type="hidden" name="display_variant" value="' . $this->h($group['display_variant'] ?? 'gallery_grid') . '">');
         if (!$isNew) {
            $form->add_rep('delete_button', $this->shopAdminCardDeleteButton('delete_product_group', $texts->get_fd_message('groups_delete_title'), $texts->get_fd_message('groups_delete_confirm')));
         }
         if ($isNew) {
            $form->add_fld('group_key', tpl: 'text-label', placeholder: 'artikelgruppe-key', rules: '*|parameter|max=80');
         }
         $form->add_fld('parent_id', tpl: 'select-single-label', options: $this->productGroupOptions($id, true, $texts), rules: 'int');
         $form->add_fld('title', tpl: 'text-label', placeholder: $texts->get_fd_message('groups_title_placeholder'), rules: '*|max=160');
         $form->add_fld('description', tpl: 'textarea-label', placeholder: $texts->get_fd_message('description_placeholder'), data: 'rows=2');
         $form->add_fld('tax_class', tpl: 'select-single-label', options: 'mwst1=mwst1&mwst2=mwst2&mwst3=mwst3');
         $form->add_fld('card_template', tpl: 'select-single-label', options: array(
            'product-card-default' => $texts->get_fd_message('card_default'),
            'product-card-compact' => $texts->get_fd_message('card_compact'),
         ));
         $form->add_fld('detail_template', tpl: 'select-single-label', options: array(
            'product-detail-default' => $texts->get_fd_message('detail_default'),
            'product-detail-technical' => $texts->get_fd_message('detail_technical'),
         ));
         $form->add_fld('gallery_template', tpl: 'select-single-label', options: array(
            'image-gallery' => $texts->get_fd_message('gallery_images'),
            'file-gallery' => $texts->get_fd_message('gallery_files'),
         ));
         $form->add_fld('gallery_visible_count', tpl: 'text-label', rules: 'int');
         $form->add_fld('gallery_image_size', tpl: 'select-single-label', options: 'original=Original&cover=Cover&contain=Contain');
         $form->add_fld('gallery_lightbox_width', tpl: 'text-label', placeholder: '100vw');
         $form->add_fld('gallery_overflow', tpl: 'select-single-label', options: array(
            'grid' => 'Grid',
            'slider' => 'Slider',
            'scroll' => 'Scroll',
            'laufband' => $texts->get_fd_message('gallery_marquee'),
            'tutorial' => 'Tutorial',
         ));
         $form->add_fld('gallery_click', tpl: 'select-single-label', options: array(
            'lightbox' => 'Lightbox',
            'none' => $texts->get_fd_message('gallery_no_click'),
            'newtab' => $texts->get_fd_message('gallery_new_tab'),
            'viewerjs' => 'ViewerJS',
            'photoswipe' => 'PhotoSwipe',
         ));
         $form->add_fld('attribute_notes', tpl: 'textarea-label', placeholder: $texts->get_fd_message('attribute_notes_placeholder'), data: 'rows=2');
         $channelDefaults = '';
         if ($this->channelsEnabled()) {
            $form->add_fld('ebay_category_id', tpl: 'text-label', placeholder: '58058');
            $form->add_fld('amazon_product_type', tpl: 'text-label', placeholder: 'SOFTWARE / PRODUCT / SHIRT');
            $form->add_fld('kleinanzeigen_category_id', tpl: 'text-label', placeholder: 'category_12345');
            $form->add_fld('mobile_category_id', tpl: 'text-label', placeholder: 'car');
            $channelDefaults = '<div class="wide dbx-shop-channel-defaults">'
               . '<h6>' . $this->h($texts->get_fd_message('channel_defaults_title')) . '</h6>'
               . '<p>' . $this->h($texts->get_fd_message('channel_defaults_info')) . '</p>'
               . '<div class="dbx-shop-admin-card-grid dbx-shop-channel-default-grid">'
               . '<div>{obj:ebay_category_id}</div>'
               . '<div>{obj:amazon_product_type}</div>'
               . '<div>{obj:kleinanzeigen_category_id}</div>'
               . '<div>{obj:mobile_category_id}</div>'
               . '</div>'
               . '</div>';
         }
         $form->add_fld('sorter', tpl: 'text-label', rules: 'int');
         $form->add_fld('active', tpl: 'checkbox-label', rules: 'int');
         $groupImagePanel = $this->productGroupImagePanel($group, $isNew, $texts);
         $form->add_rep('form_body',
            '<div class="dbx-shop-admin-card-grid">'
            . ($isNew ? '<div>{obj:group_key}</div>' : '')
            . '<div>{obj:parent_id}</div>'
            . '<div>{obj:title}</div>'
            . '<div>{obj:tax_class}</div>'
            . '<div>{obj:sorter}</div>'
            . '<div>{obj:active}</div>'
            . '<div class="wide">' . $groupImagePanel . '</div>'
            . '<div class="wide">{obj:description}</div>'
            . '<div>{obj:card_template}</div>'
            . '<div>{obj:detail_template}</div>'
            . '<div>{obj:gallery_template}</div>'
            . '<div>{obj:gallery_visible_count}</div>'
            . '<div>{obj:gallery_image_size}</div>'
            . '<div>{obj:gallery_lightbox_width}</div>'
            . '<div>{obj:gallery_overflow}</div>'
            . '<div>{obj:gallery_click}</div>'
            . '<div class="wide">{obj:attribute_notes}</div>'
            . $channelDefaults
            . '</div>'
         );
         return $form->run();
      };

      $groups = $this->repo()->groups();
      $cards = '';
      if ((int)($_GET['new'] ?? 0) === 1) {
         $sorter = 10;
         foreach ($groups as $group) {
            $sorter = max($sorter, (int)($group['sorter'] ?? 0) + 10);
         }
         $cards .= $cardHtml(array(
            'tax_class' => (string)($this->shopConfig()['default_tax_class'] ?? 'mwst1'),
            'default_tax_rate' => 19,
            'parent_id' => 0,
            'display_variant' => 'gallery_grid',
            'card_template' => 'product-card-default',
            'detail_template' => 'product-detail-default',
            'gallery_template' => 'image-gallery',
            'gallery_visible_count' => 3,
            'gallery_image_size' => 'original',
            'gallery_lightbox_width' => '100vw',
            'gallery_overflow' => 'grid',
            'gallery_click' => 'lightbox',
            'active' => 1,
            'sorter' => $sorter,
         ), true);
      }
      foreach ($groups as $group) {
         $cards .= $cardHtml($group);
      }
      $helpButton = $this->helpButton($this->ensureShopProductGroupsHelpPage(), $texts->get_fd_message('groups_help'));
      $barActions = '<a class="btn btn-outline-primary btn-sm me-1" href="?dbx_modul=dbxShop_admin&dbx_run1=groups&new=1" title="' . $this->h($texts->get_fd_message('groups_new_title')) . '"><i class="bi bi-plus-square"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('groups_new')) . '</span></a>' . $helpButton;
      $content = '<div class="alert alert-info mx-3 mt-3 mb-0">' . $this->h($texts->get_fd_message('groups_intro')) . '</div>'
         . '<div class="dbx-shop-admin-card-list">' . $cards . '</div>';
      if ($groups !== array()) {
         // Eine gemeinsame Medienbrowser-Vorlage genuegt fuer alle Karten.
         // Sie steht bewusst nach allen Kartenformularen.
         $content .= $this->shopMediaFormTemplates($this->shopMediaConfig());
      }
      return $this->frame($content, $texts->get_fd_message('groups_title'), $barActions);
   }

   private function attributes(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      if ($this->posted('save_attribute_definition')) {
         $this->repo()->saveAttributeDefinition($_POST);
      }

      $groupOptions = array();
      foreach ($this->repo()->groups() as $group) {
         $groupOptions[(string)(int)($group['id'] ?? 0)] = (string)($group['title'] ?? '');
      }

      $cardHelpButton = $this->helpButton($this->ensureShopProductAttributesHelpPage(), $texts->get_fd_message('attributes_help'), 'btn btn-outline-secondary btn-sm me-1');
      $cardHtml = function (array $attribute, bool $isNew = false) use ($groupOptions, $cardHelpButton, $texts): string {
         $id = (int)($attribute['id'] ?? 0);
         $type = (string)($attribute['input_type'] ?? 'text');
         $title = $isNew
            ? '<span>' . $this->h($texts->get_fd_message('attributes_new')) . '</span>'
            : '<code>' . $this->h($attribute['attr_key'] ?? '') . '</code><span>' . $this->h($attribute['title'] ?? '') . '</span>';
         $subtitle = $isNew ? $texts->get_fd_message('attributes_new_subtitle') : (string)($attribute['group_title'] ?? '');

         $form = $this->shopAdminCardForm(
            'shop-attribute-definition-' . ($isNew ? 'new' : $id),
            'dbxShop|shopAttributeDefinition',
            $attribute,
            $id,
            '?dbx_modul=dbxShop_admin&dbx_run1=attributes',
            'save_attribute_definition',
            'save_attribute_definition',
            $title,
            $subtitle,
            'dbx-shop-attribute-card'
         );
         $form->add_rep('card_badges', $cardHelpButton . ($isNew ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('new')) . '</span>' : $this->activeBadge($attribute, $texts)));
         $form->add_fld('group_id', tpl: 'select-single-label', options: $groupOptions, rules: 'int');
         $form->add_fld('attr_key', tpl: 'text-label', placeholder: $texts->get_fd_message('attributes_key_placeholder'), rules: '*|parameter|max=80');
         $form->add_fld('title', tpl: 'text-label', placeholder: $texts->get_fd_message('attributes_title_placeholder'), rules: '*|max=160');
         $form->add_fld('input_type', tpl: 'select-single-label', options: array(
            'text' => $texts->get_fd_message('attributes_type_text'),
            'select' => $texts->get_fd_message('attributes_type_select'),
            'number' => $texts->get_fd_message('attributes_type_number'),
         ));
         $form->add_fld('unit', tpl: 'text-label', placeholder: 'cm');
         $form->add_fld('options', tpl: 'textarea-label', placeholder: 'S|M|L|XL', data: 'rows=2');
         $form->add_fld('required', tpl: 'checkbox-label', rules: 'int');
         $form->add_fld('filterable', tpl: 'checkbox-label', rules: 'int');
         $form->add_fld('comparable', tpl: 'checkbox-label', rules: 'int');
         $form->add_fld('sorter', tpl: 'text-label', rules: 'int');
         $form->add_fld('active', tpl: 'checkbox-label', rules: 'int');
         $form->add_rep('form_body',
            '<div class="dbx-shop-admin-card-grid">'
            . '<div>{obj:group_id}</div>'
            . '<div>{obj:attr_key}</div>'
            . '<div>{obj:title}</div>'
            . '<div>{obj:input_type}</div>'
            . '<div>{obj:unit}</div>'
            . '<div>{obj:sorter}</div>'
            . '<div class="wide">{obj:options}</div>'
            . '<div class="wide dbx-shop-admin-check-grid">{obj:required}{obj:filterable}{obj:comparable}{obj:active}</div>'
            . '</div>'
         );
         return $form->run();
      };

      $cards = $cardHtml(array(
         'group_id' => (int)array_key_first($groupOptions),
         'input_type' => 'text',
         'required' => 0,
         'filterable' => 1,
         'comparable' => 0,
         'active' => 1,
         'sorter' => 100,
      ), true);
      foreach ($this->repo()->allAttributeDefinitions() as $attribute) {
         $cards .= $cardHtml($attribute);
      }

      $barActions = $this->helpButton($this->ensureShopProductAttributesHelpPage(), $texts->get_fd_message('attributes_help'));
      return $this->frame('<div class="alert alert-info mx-3 mt-3 mb-0">' . $this->h($texts->get_fd_message('attributes_intro')) . '</div><div class="dbx-shop-admin-card-list">' . $cards . '</div>', $texts->get_fd_message('attributes_title'), $barActions);
   }

   private function productAttributes(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      $productId = (int)dbx()->get_modul_var('id', '0', 'int');
      if ($this->posted('save_product_attributes')) {
         $productId = (int)($_POST['product_id'] ?? $productId);
         $this->repo()->saveProductAttributeValues($productId, $_POST['attr_value'] ?? array());
      }
      $product = $this->repo()->productById($productId);
      if (!$product) {
         return $this->placeholder($texts->get_fd_message('attributes_title'), $texts->get_fd_message('attributes_product_not_found'));
      }

      $valueMap = array();
      foreach (($product['attributes'] ?? array()) as $attribute) {
         $valueMap[(int)($attribute['id'] ?? 0)] = (string)($attribute['value_text'] ?? '');
      }

      $rows = '';
      foreach ($this->repo()->attributeDefinitionsForProduct($productId, true) as $definition) {
         $id = (int)($definition['id'] ?? 0);
         $value = $valueMap[$id] ?? '';
         $type = (string)($definition['input_type'] ?? 'text');
         $input = '';
         if ($type === 'select') {
            $input = '<select class="form-select form-select-sm" name="attr_value[' . $id . ']"><option value="">-</option>';
            foreach ($this->attributeOptions((string)($definition['options'] ?? '')) as $option) {
               $input .= '<option value="' . $this->h($option) . '"' . ($option === $value ? ' selected' : '') . '>' . $this->h($option) . '</option>';
            }
            if ($value !== '' && strpos((string)($definition['options'] ?? ''), $value) === false) {
               $input .= '<option value="' . $this->h($value) . '" selected>' . $this->h($value) . '</option>';
            }
            $input .= '</select>';
         } else {
            $inputType = $type === 'number' ? 'number' : 'text';
            $step = $type === 'number' ? ' step="0.01"' : '';
            $input = '<input class="form-control form-control-sm" type="' . $inputType . '"' . $step . ' name="attr_value[' . $id . ']" value="' . $this->h($value) . '">';
         }
         $rows .= '<tr>';
         $rows .= '<td><strong>' . $this->h($definition['title'] ?? '') . '</strong><br><small><code>' . $this->h($definition['attr_key'] ?? '') . '</code></small></td>';
         $rows .= '<td>' . $input . '</td>';
         $rows .= '<td>' . $this->h($definition['unit'] ?? '') . '</td>';
         $rows .= '<td>' . ((int)($definition['required'] ?? 0) === 1 ? '<span class="badge text-bg-warning">' . $this->h($texts->get_fd_message('required')) . '</span>' : '') . ' ' . ((int)($definition['filterable'] ?? 0) === 1 ? '<span class="badge text-bg-info">' . $this->h($texts->get_fd_message('filter')) . '</span>' : '') . '</td>';
         $rows .= '</tr>';
      }

      if ($rows === '') {
         $rows = '<tr><td colspan="4" class="text-muted">' . $this->h($texts->get_fd_message('attributes_none')) . '</td></tr>';
      }

      $form = $this->shopAdminCardForm(
         'shop-product-attributes-' . $productId,
         'dbxShop|shopProductAttributeValue',
         array('id' => $productId),
         $productId,
         '?dbx_modul=dbxShop_admin&dbx_run1=product_attributes&id=' . $productId,
         'save_product_attributes',
         'save_product_attributes',
         '<code>' . $this->h($product['sku'] ?? '') . '</code><span>' . $this->h($product['title'] ?? '') . '</span>',
         $texts->get_fd_message('attributes_value_subtitle'),
         'dbx-shop-product-attributes-card'
      );
      $form->add_rep('extra_hidden', '<input type="hidden" name="product_id" value="' . $productId . '">');
      $form->add_rep('card_badges', $this->helpButton($this->ensureShopProductAttributesHelpPage(), $texts->get_fd_message('attributes_help'), 'btn btn-outline-secondary btn-sm me-1') . '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxShop_admin&dbx_run1=products">' . $this->h($texts->get_fd_message('back')) . '</a>');
      $form->add_rep('form_body', '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>' . $this->h($texts->get_fd_message('column_attribute')) . '</th><th>' . $this->h($texts->get_fd_message('column_value')) . '</th><th>' . $this->h($texts->get_fd_message('column_unit')) . '</th><th>' . $this->h($texts->get_fd_message('column_property')) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>');
      return $this->frame('<div class="dbx-shop-admin-card-list">' . $form->run() . '</div>', $texts->get_fd_message('attributes_edit_title'));
   }

   private function shippingGroups(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      if ($this->posted('delete_shipping_group')) {
         $this->repo()->deleteShippingGroup((int)($_POST['id'] ?? 0));
      } elseif ($this->posted('save_shipping_group')) {
         $this->repo()->updateShippingGroup((int)($_POST['id'] ?? 0), $_POST);
      }

      $cardHelpButton = $this->helpButton($this->ensureShopShippingGroupsHelpPage(), $texts->get_fd_message('shipping_help'), 'btn btn-outline-secondary btn-sm me-1');
      $cardHtml = function (array $group, bool $isNew = false) use ($cardHelpButton, $texts): string {
         $id = (int)($group['id'] ?? 0);
         if ($isNew) {
            $title = '<span>' . $this->h($texts->get_fd_message('shipping_new')) . '</span>';
            $subtitle = $texts->get_fd_message('shipping_new_subtitle');
         } else {
            $title = '<code>' . $this->h($group['group_key'] ?? '') . '</code><span>' . $this->h($group['title'] ?? '') . '</span>';
            $subtitle = trim((string)($group['description'] ?? ''));
         }

         $form = $this->shopAdminCardForm(
            'shop-shipping-group-' . ($isNew ? 'new' : $id),
            'dbxShop|shopShippingGroup',
            $group,
            $id,
            '?dbx_modul=dbxShop_admin&dbx_run1=shipping_groups' . ($isNew ? '&new=1' : ''),
            'save_shipping_group',
            'save_shipping_group',
            $title,
            $subtitle,
            'dbx-shop-shipping-group-card'
         );
         $form->add_rep('card_badges', $cardHelpButton . ($isNew ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('new')) . '</span>' : $this->activeBadge($group, $texts)));
         if (!$isNew) {
            $form->add_rep('delete_button', $this->shopAdminCardDeleteButton('delete_shipping_group', $texts->get_fd_message('shipping_delete_title'), $texts->get_fd_message('shipping_delete_confirm')));
         }
         if ($isNew) {
            $form->add_fld('group_key', tpl: 'text-label', placeholder: 'versandgruppe-key', rules: '*|parameter|max=80');
         }
         $form->add_fld('title', tpl: 'text-label', placeholder: $texts->get_fd_message('shipping_new'), rules: '*|max=160');
         $form->add_fld('description', tpl: 'textarea-label', placeholder: $texts->get_fd_message('description_placeholder'), data: 'rows=2');
         $form->add_fld('shipping_way', tpl: 'text-label', placeholder: $texts->get_fd_message('shipping_way_placeholder'));
         $form->add_fld('delivery_time', tpl: 'text-label', placeholder: $texts->get_fd_message('shipping_time_placeholder'));
         $form->add_fld('shipping_gross', tpl: 'text-label', placeholder: '5.90', rules: 'decimal');
         $form->add_fld('free_from_gross', tpl: 'text-label', placeholder: '-1', rules: 'decimal');
         $form->add_fld('sorter', tpl: 'text-label', rules: 'int');
         $form->add_fld('active', tpl: 'checkbox-label', rules: 'int');
         $form->add_rep('form_body',
            '<div class="dbx-shop-admin-card-grid">'
            . ($isNew ? '<div>{obj:group_key}</div>' : '')
            . '<div>{obj:title}</div>'
            . '<div>{obj:shipping_way}</div>'
            . '<div>{obj:delivery_time}</div>'
            . '<div>{obj:shipping_gross}</div>'
            . '<div>{obj:free_from_gross}</div>'
            . '<div>{obj:sorter}</div>'
            . '<div>{obj:active}</div>'
            . '<div class="wide">{obj:description}</div>'
            . '</div>'
         );
         return $form->run();
      };

      $groups = $this->repo()->shippingGroups();
      $cards = '';
      if ((int)($_GET['new'] ?? 0) === 1) {
         $sorter = 10;
         foreach ($groups as $group) {
            $sorter = max($sorter, (int)($group['sorter'] ?? 0) + 10);
         }
         $cards .= $cardHtml(array(
            'shipping_gross' => 0,
            'free_from_gross' => -1,
            'active' => 1,
            'sorter' => $sorter,
         ), true);
      }
      foreach ($groups as $group) {
         $cards .= $cardHtml($group);
      }
      $helpButton = $this->helpButton($this->ensureShopShippingGroupsHelpPage(), $texts->get_fd_message('shipping_help'));
      $barActions = '<a class="btn btn-outline-primary btn-sm me-1" href="?dbx_modul=dbxShop_admin&dbx_run1=shipping_groups&new=1" title="' . $this->h($texts->get_fd_message('shipping_new_title')) . '"><i class="bi bi-plus-square"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('shipping_new')) . '</span></a>' . $helpButton;
      return $this->frame('<div class="alert alert-info mx-3 mt-3 mb-0">' . $this->h($texts->get_fd_message('shipping_intro')) . '</div><div class="dbx-shop-admin-card-list">' . $cards . '</div>', $texts->get_fd_message('shipping_title'), $barActions);
   }

   private function channelGroups(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      if ($this->posted('delete_channel_group')) {
         $this->repo()->deleteChannelGroup((int)($_POST['id'] ?? 0));
      } elseif ($this->posted('save_channel_group')) {
         $this->repo()->updateChannelGroup((int)($_POST['id'] ?? 0), $_POST, array_map('strval', $_POST['channels'] ?? array()));
      }
      $channels = $this->repo()->channels();
      $groups = $this->repo()->channelGroups();
      $cardHelpButton = $this->helpButton($this->ensureShopChannelHelpPage(), $texts->get_fd_message('channel_groups_help'), 'btn btn-outline-secondary btn-sm me-1');
      $cardHtml = function (array $group, bool $isNew = false) use ($channels, $cardHelpButton, $texts): string {
         $id = (int)($group['id'] ?? 0);
         $active = array();
         foreach (($group['channels'] ?? array()) as $channel) {
            if ((int)($channel['active'] ?? 0) === 1) {
               $active[] = (string)$channel['channel_key'];
            }
         }
         $checks = '<div class="dbx-shop-admin-check-grid">';
         foreach ($channels as $channel) {
            $key = (string)($channel['channel_key'] ?? '');
            if ($key === '') {
               continue;
            }
            $checks .= '<label><input type="checkbox" name="channels[]" value="' . $this->h($key) . '"' . (in_array($key, $active, true) ? ' checked' : '') . '> <span>' . $this->h($channel['title'] ?? $key) . '</span></label>';
         }
         $checks .= '</div>';

         if ($isNew) {
            $title = '<span>' . $this->h($texts->get_fd_message('channel_groups_new')) . '</span>';
            $subtitle = $texts->get_fd_message('channel_groups_new_subtitle');
         } else {
            $title = '<code>' . $this->h($group['group_key'] ?? '') . '</code><span>' . $this->h($group['title'] ?? '') . '</span>';
            $subtitle = trim((string)($group['description'] ?? ''));
         }

         $form = $this->shopAdminCardForm(
            'shop-channel-group-' . ($isNew ? 'new' : $id),
            'dbxShop|shopChannelGroup',
            $group,
            $id,
            '?dbx_modul=dbxShop_admin&dbx_run1=channel_groups' . ($isNew ? '&new=1' : ''),
            'save_channel_group',
            'save_channel_group',
            $title,
            $subtitle,
            'dbx-shop-channel-group-card'
         );
         $form->add_rep('card_badges', $cardHelpButton . ($isNew ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('new')) . '</span>' : $this->activeBadge($group, $texts)));
         if (!$isNew) {
            $form->add_rep('delete_button', $this->shopAdminCardDeleteButton('delete_channel_group', $texts->get_fd_message('channel_groups_delete_title'), $texts->get_fd_message('channel_groups_delete_confirm')));
         }
         if ($isNew) {
            $form->add_fld('group_key', tpl: 'text-label', placeholder: 'neue-channel-gruppe', rules: '*|parameter|max=80');
         }
         $form->add_fld('title', tpl: 'text-label', placeholder: $texts->get_fd_message('channel_groups_new'), rules: '*|max=160');
         $form->add_fld('description', tpl: 'textarea-label', placeholder: $texts->get_fd_message('description_placeholder'), data: 'rows=2');
         $form->add_fld('sorter', tpl: 'text-label', rules: 'int');
         $form->add_fld('active', tpl: 'checkbox-label', rules: 'int');
         $form->add_obj('channel_checks', 'obj-value', $checks);
         $form->add_rep('form_body',
            '<div class="dbx-shop-admin-card-grid">'
            . ($isNew ? '<div>{obj:group_key}</div>' : '')
            . '<div>{obj:title}</div>'
            . '<div>{obj:sorter}</div>'
            . '<div>{obj:active}</div>'
            . '<div class="wide">{obj:description}</div>'
            . '<div class="wide"><label class="form-label">' . $this->h($texts->get_fd_message('channels_label')) . '</label>{obj:channel_checks}</div>'
            . '</div>'
         );
         return $form->run();
      };

      $cards = '';
      if ((int)($_GET['new'] ?? 0) === 1) {
         $sorter = 10;
         foreach ($groups as $group) {
            $sorter = max($sorter, (int)($group['sorter'] ?? 0) + 10);
         }
         $cards .= $cardHtml(array(
            'title' => '',
            'description' => '',
            'active' => 1,
            'sorter' => $sorter,
            'channels' => array(),
         ), true);
      }
      foreach ($groups as $group) {
         $cards .= $cardHtml($group);
      }
      $helpButton = $this->helpButton($this->ensureShopChannelHelpPage(), $texts->get_fd_message('channel_groups_help'));
      $barActions = '<a class="btn btn-outline-primary btn-sm me-1" href="?dbx_modul=dbxShop_admin&dbx_run1=channel_groups&new=1" title="' . $this->h($texts->get_fd_message('channel_groups_new_title')) . '"><i class="bi bi-plus-square"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('channel_groups_new')) . '</span></a>' . $helpButton;
      return $this->frame('<div class="alert alert-info mx-3 mt-3 mb-0">' . $this->h($texts->get_fd_message('channel_groups_intro')) . '</div><div class="dbx-shop-admin-card-list">' . $cards . '</div>', $texts->get_fd_message('channel_groups_title'), $barActions);
   }

   private function shopMediaDir(bool $ensure = false): string {
      $dir = dirname(__DIR__, 4) . '/files/shop/img';
      if ($ensure && !is_dir($dir)) {
         mkdir($dir, 0775, true);
      }
      return $dir;
   }

   private function safeFileName(string $name): string {
      $name = strtolower(trim($name));
      $name = preg_replace('~[^a-z0-9._-]+~', '-', $name);
      $name = trim((string)$name, '-.');
      return $name !== '' ? $name : 'shop-image';
   }

   private function handleMediaUpload(): string {
      if (!$this->posted('upload_media')) {
         return '';
      }
      if (empty($_FILES['shop_image']['tmp_name']) || !is_uploaded_file($_FILES['shop_image']['tmp_name'])) {
         return '<div class="alert alert-warning m-3">Keine Datei ausgewaehlt.</div>';
      }
      $original = (string)($_FILES['shop_image']['name'] ?? 'shop-image');
      $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
      $allowed = array('jpg','jpeg','png','gif','webp','svg');
      if (!in_array($ext, $allowed, true)) {
         return '<div class="alert alert-danger m-3">Dieser Bildtyp ist fuer Shop-Medien nicht erlaubt.</div>';
      }

      $base = $this->safeFileName(pathinfo($original, PATHINFO_FILENAME));
      $name = $base . '.' . $ext;
      $target = $this->shopMediaDir(true) . '/' . $name;
      $i = 2;
      while (is_file($target)) {
         $name = $base . '-' . $i . '.' . $ext;
         $target = $this->shopMediaDir() . '/' . $name;
         $i++;
      }
      if (!move_uploaded_file($_FILES['shop_image']['tmp_name'], $target)) {
         return '<div class="alert alert-danger m-3">Upload konnte nicht gespeichert werden.</div>';
      }

      $rel = 'files/shop/img/' . $name;
      $productId = (int)($_POST['product_id'] ?? 0);
      $groupId = (int)($_POST['group_id'] ?? 0);
      if ($productId > 0 || $groupId > 0) {
         $this->repo()->saveImage($productId, $groupId, $rel, (string)($_POST['title'] ?? $base), (string)($_POST['alt'] ?? $base), !empty($_POST['is_primary']) ? 1 : 0, (int)($_POST['sorter'] ?? 100));
      }
      return '<div class="alert alert-success m-3">Bild wurde hochgeladen: ' . $this->h($name) . '</div>';
   }

   private function media(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      $selectedProduct = (int)dbx()->get_modul_var('product_id', '0', 'int');
      $selectedGroup = (int)dbx()->get_modul_var('group_id', '0', 'int');

      $productOptions = '<option value="0">' . $this->h($texts->get_fd_message('media_no_product')) . '</option>';
      foreach ($this->repo()->products(false) as $product) {
         $sel = (int)($product['id'] ?? 0) === $selectedProduct ? ' selected' : '';
         $productOptions .= '<option value="' . (int)($product['id'] ?? 0) . '"' . $sel . '>' . $this->h($product['title'] ?? '') . '</option>';
      }
      $groupOptions = '<option value="0">' . $this->h($texts->get_fd_message('media_no_group')) . '</option>';
      foreach ($this->repo()->groups() as $group) {
         $sel = (int)($group['id'] ?? 0) === $selectedGroup ? ' selected' : '';
         $groupOptions .= '<option value="' . (int)($group['id'] ?? 0) . '"' . $sel . '>' . $this->h($group['title'] ?? '') . '</option>';
      }

      $mediaCfg = $this->shopMediaConfig();
      $attrs = $this->shopMediaAttrs($mediaCfg);

      $html = '<div class="dbx-shop-media-manager m-3"' . $attrs . '>';
      $targetForm = dbx()->get_system_obj('dbxForm');
      $targetForm->init('shop-media-target-form', 'shop-media-target-form');
      $targetForm->_action = '?dbx_modul=dbxShop_admin&dbx_run1=media';
      $targetForm->set_form_help_enabled(false);
      $targetForm->add_rep('frame_skip_form_wrap', '1');
      $targetForm->add_rep('form_body',
         '<div class="row g-2 align-items-end">'
         . '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('media_product')) . '</label><select class="form-select form-select-sm" name="product_id" data-shop-product-select>' . $productOptions . '</select></div>'
         . '<div class="col-md-4"><label class="form-label">' . $this->h($texts->get_fd_message('media_group')) . '</label><select class="form-select form-select-sm" name="group_id" data-shop-group-select>' . $groupOptions . '</select></div>'
         . '<div class="col-md-2"><label class="form-label">' . $this->h($texts->get_fd_message('media_sort')) . '</label><input class="form-control form-control-sm" name="sorter" value="100" data-shop-sorter></div>'
         . '<div class="col-md-2 form-check pb-1"><input class="form-check-input" type="checkbox" name="is_primary" value="1" id="shop_img_primary" data-shop-primary><label class="form-check-label" for="shop_img_primary">' . $this->h($texts->get_fd_message('media_primary')) . '</label></div>'
         . '<div class="col-md-8"><div class="form-text">' . $this->h($texts->get_fd_message('media_hint')) . '</div></div>'
         . '<div class="col-md-2"><button class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-filter"></i> ' . $this->h($texts->get_fd_message('media_load_selection')) . '</button></div>'
         . '<div class="col-md-2"><button type="button" class="btn btn-outline-primary btn-sm w-100 dbx-shop-media-pick" data-shop-media-folder="img/shop" title="' . $this->h($texts->get_fd_message('media_select_title')) . '"><i class="bi bi-images"></i><i class="bi bi-camera-video"></i><i class="bi bi-upload"></i><span>' . $this->h($texts->get_fd_message('selection')) . '</span></button></div>'
         . '</div>'
      );
      $html .= $targetForm->run();

      $assigned = '';
      foreach ($this->repo()->allImages() as $image) {
         $assigned .= '<div class="col"><div class="card h-100"><img src="' . $this->h($this->mediaItemUrl($image, true)) . '" class="card-img-top" alt="" style="height:120px;object-fit:cover;"><div class="card-body p-2"><strong>' . $this->h($image['title'] ?? '') . '</strong><br><small>' . $this->h($image['product_title'] ?: $image['group_title'] ?: $texts->get_fd_message('not_assigned')) . '</small></div></div></div>';
      }
      $html .= '<div class="m-3"><h5>' . $this->h($texts->get_fd_message('media_assigned_title')) . '</h5><div class="row row-cols-2 row-cols-md-4 row-cols-xl-6 g-2">' . $assigned . '</div></div>';

      $files = glob($this->shopMediaDir() . '/*.{jpg,jpeg,png,gif,webp,svg}', GLOB_BRACE) ?: array();
      $fileCards = '';
      foreach ($files as $file) {
         $rel = 'files/shop/img/' . basename($file);
         $fileCards .= '<div class="col"><div class="card h-100"><img src="' . $this->h($this->mediaUrl($rel)) . '" class="card-img-top" alt="" style="height:120px;object-fit:cover;"><div class="card-body p-2"><small>' . $this->h(basename($file)) . '</small></div></div></div>';
      }
      $html .= '<div class="m-3"><h5>' . $this->h($texts->get_fd_message('media_legacy_title')) . '</h5><div class="row row-cols-2 row-cols-md-4 row-cols-xl-6 g-2">' . $fileCards . '</div></div>';
      $html .= $this->shopMediaFormTemplates($mediaCfg);
      $html .= '</div>';

      $barActions = $this->helpButton($this->ensureShopMediaHelpPage(), $texts->get_fd_message('media_help'));
      return $this->frame($html, $texts->get_fd_message('media_title'), $barActions);
   }

   private function channels(): string {
      $this->ensureSeed();
      $texts = $this->catalogTexts();
      $notice = '';
      if ($this->posted('delete_channel')) {
         $this->repo()->deleteChannel((int)($_POST['id'] ?? 0));
      } elseif ($this->posted('save_channel')) {
         $this->repo()->updateChannel((int)($_POST['id'] ?? 0), $_POST);
      } elseif ($this->posted('test_channel')) {
         $id = (int)($_POST['id'] ?? 0);
         if ($id > 0) {
            $this->repo()->updateChannel($id, $_POST);
            $result = $this->repo()->testChannelConnection($id);
            $notice = '<div class="alert ' . (!empty($result['ok']) ? 'alert-success' : 'alert-warning') . ' m-3">' . $this->h($result['message'] ?? '') . '</div>';
         } else {
            $notice = '<div class="alert alert-warning m-3">' . $this->h($texts->get_fd_message('channels_save_first')) . '</div>';
         }
      }

      $platformOptions = array(
         'shop' => 'Shop',
         'amazon' => 'Amazon',
         'ebay' => 'eBay',
         'kleinanzeigen' => 'Kleinanzeigen',
         'mobile' => 'mobile.de',
         'custom' => $texts->get_fd_message('channels_platform_custom'),
      );
      $modeOptions = array(
         'internal' => $texts->get_fd_message('channels_mode_internal'),
         'manual' => $texts->get_fd_message('channels_mode_manual'),
         'api' => 'API',
         'feed' => 'Feed',
         'webhook' => 'Webhook',
      );

      $platformHints = array(
         'shop' => array($texts->get_fd_message('channels_hint_shop_api'), $texts->get_fd_message('channels_hint_shop_listing'), $texts->get_fd_message('channels_hint_shop_feedback')),
         'amazon' => array($texts->get_fd_message('channels_hint_amazon_api'), $texts->get_fd_message('channels_hint_amazon_listing'), $texts->get_fd_message('channels_hint_amazon_feedback')),
         'ebay' => array($texts->get_fd_message('channels_hint_ebay_api'), $texts->get_fd_message('channels_hint_ebay_listing'), $texts->get_fd_message('channels_hint_ebay_feedback')),
         'kleinanzeigen' => array($texts->get_fd_message('channels_hint_classified_api'), $texts->get_fd_message('channels_hint_classified_listing'), $texts->get_fd_message('channels_hint_classified_feedback')),
         'mobile' => array($texts->get_fd_message('channels_hint_mobile_api'), $texts->get_fd_message('channels_hint_mobile_listing'), $texts->get_fd_message('channels_hint_mobile_feedback')),
         'custom' => array($texts->get_fd_message('channels_hint_custom_api'), $texts->get_fd_message('channels_hint_custom_listing'), $texts->get_fd_message('channels_hint_custom_feedback')),
      );

      $rowHtml = function (array $channel, bool $isNew = false) use ($platformOptions, $modeOptions, $platformHints, $texts): string {
         $id = (int)($channel['id'] ?? 0);
         $key = (string)($channel['channel_key'] ?? '');
         $platform = (string)($channel['platform_type'] ?? 'custom');
         $hint = $platformHints[$platform] ?? $platformHints['custom'];
         $placeholderMap = array(
            'shop' => array(
               'channel_key' => 'shop',
               'api_base_url' => 'nicht benoetigt',
               'api_client_id' => 'nicht benoetigt',
               'api_username' => 'nicht benoetigt',
               'api_client_secret' => 'nicht benoetigt',
               'api_access_token' => 'nicht benoetigt',
               'api_refresh_token' => 'nicht benoetigt',
               'api_password' => 'nicht benoetigt',
               'marketplace_id' => 'nicht benoetigt',
               'seller_id' => 'nicht benoetigt',
               'account_id' => 'nicht benoetigt',
               'location_key' => 'nicht benoetigt',
               'category_id' => 'nicht benoetigt',
               'payment_policy_id' => 'nicht benoetigt',
               'fulfillment_policy_id' => 'nicht benoetigt',
               'return_policy_id' => 'nicht benoetigt',
               'notification_destination' => 'nicht benoetigt',
               'notification_topic' => 'nicht benoetigt',
               'webhook_secret' => 'nicht benoetigt',
               'webhook_url' => 'nicht benoetigt',
               'api_scope' => 'nicht benoetigt',
            ),
            'amazon' => array(
               'channel_key' => 'amazon',
               'api_base_url' => 'https://sellingpartnerapi-eu.amazon.com',
               'api_client_id' => 'amzn1.application-oa2-client.keyxxxx',
               'api_username' => 'nicht benoetigt bei OAuth',
               'api_client_secret' => 'amzn1.oa2-cs.v1.secretxxxx',
               'api_access_token' => 'Atza|access_token_xxxx',
               'api_refresh_token' => 'Atzr|refresh_token_xxxx',
               'api_password' => 'nicht benoetigt bei SP-API',
               'marketplace_id' => 'A1PA6795UKMFR9',
               'seller_id' => 'A1SELLERIDXXXX',
               'account_id' => 'account_xxxx',
               'location_key' => 'nicht benoetigt',
               'category_id' => 'productType: SOFTWARE / PRODUCT',
               'payment_policy_id' => 'nicht benoetigt',
               'fulfillment_policy_id' => 'nicht benoetigt',
               'return_policy_id' => 'nicht benoetigt',
               'notification_destination' => 'arn:aws:sqs:eu-central-1:123456789012:amazon-orders',
               'notification_topic' => 'ORDER_CHANGE',
               'webhook_secret' => 'secret_64zeichen_xxxx',
               'webhook_url' => 'wird von dbxShop erzeugt',
               'api_scope' => "Listings Items\nOrders\nNotifications",
            ),
            'ebay' => array(
               'channel_key' => 'ebay',
               'api_base_url' => 'https://api.ebay.com',
               'api_client_id' => 'keyxxxx-appid',
               'api_username' => 'nicht benoetigt bei OAuth',
               'api_client_secret' => 'certid-secretxxxx',
               'api_access_token' => 'v^1.1#i^1#p^3#access_token_xxxx',
               'api_refresh_token' => 'v^1.1#r^1#p^3#refresh_token_xxxx',
               'api_password' => 'nicht benoetigt bei OAuth',
               'marketplace_id' => 'EBAY_DE',
               'seller_id' => 'sellername_xxxx',
               'account_id' => 'account_xxxx',
               'location_key' => 'default',
               'category_id' => '58058',
               'payment_policy_id' => 'policy_payment_1234567890',
               'fulfillment_policy_id' => 'policy_fulfillment_1234567890',
               'return_policy_id' => 'policy_return_1234567890',
               'notification_destination' => 'https://domain.de/?dbx_modul=dbxShop&dbx_run1=channel_webhook&channel=ebay',
               'notification_topic' => 'ORDER',
               'webhook_secret' => 'verification_token_xxxx',
               'webhook_url' => 'wird von dbxShop erzeugt',
               'api_scope' => "https://api.ebay.com/oauth/api_scope/sell.inventory\nhttps://api.ebay.com/oauth/api_scope/sell.fulfillment\nhttps://api.ebay.com/oauth/api_scope/commerce.notification.subscription",
            ),
            'kleinanzeigen' => array(
               'channel_key' => 'kleinanzeigen',
               'api_base_url' => 'nur bei freigegebener Schnittstelle',
               'api_client_id' => 'partner_key_xxxx',
               'api_username' => 'api-user@example.de',
               'api_client_secret' => 'partner_secret_xxxx',
               'api_access_token' => 'access_token_xxxx',
               'api_refresh_token' => 'refresh_token_xxxx',
               'api_password' => 'password_xxxx',
               'marketplace_id' => 'DE',
               'seller_id' => 'seller_xxxx',
               'account_id' => 'account_xxxx',
               'location_key' => 'standort_10115_berlin',
               'category_id' => 'category_12345',
               'payment_policy_id' => 'nicht benoetigt',
               'fulfillment_policy_id' => 'nicht benoetigt',
               'return_policy_id' => 'nicht benoetigt',
               'notification_destination' => 'https://middleware.example.de/webhook',
               'notification_topic' => 'lead / message / sale',
               'webhook_secret' => 'middleware_secret_xxxx',
               'webhook_url' => 'wird von dbxShop erzeugt',
               'api_scope' => "laut Vertrag\nMiddleware-Berechtigung",
            ),
            'mobile' => array(
               'channel_key' => 'mobile',
               'api_base_url' => 'https://services.mobile.de/seller-api',
               'api_client_id' => 'nicht benoetigt bei Basic Auth',
               'api_username' => 'dealer_api_user_xxxx',
               'api_client_secret' => 'nicht benoetigt bei Basic Auth',
               'api_access_token' => 'nicht benoetigt bei Basic Auth',
               'api_refresh_token' => 'nicht benoetigt bei Basic Auth',
               'api_password' => 'dealer_api_password_xxxx',
               'marketplace_id' => 'DE',
               'seller_id' => 'customer_123456',
               'account_id' => 'mobileSellerId_123456',
               'location_key' => 'location_123456',
               'category_id' => 'car / motorbike / commercial',
               'payment_policy_id' => 'nicht benoetigt',
               'fulfillment_policy_id' => 'nicht benoetigt',
               'return_policy_id' => 'nicht benoetigt',
               'notification_destination' => 'https://middleware.example.de/mobile-leads',
               'notification_topic' => 'lead-api',
               'webhook_secret' => 'lead_secret_xxxx',
               'webhook_url' => 'wird von dbxShop erzeugt',
               'api_scope' => "seller-api\nbasic-auth\nlead-api",
            ),
            'custom' => array(
               'channel_key' => 'mein-channel',
               'api_base_url' => 'https://api.anbieter.de/v1',
               'api_client_id' => 'client_123456',
               'api_username' => 'api-user@example.de',
               'api_client_secret' => 'client_secret_xxxxx',
               'api_access_token' => 'access_token_xxxxx',
               'api_refresh_token' => 'refresh_token_xxxxx',
               'api_password' => 'API-Passwort',
               'marketplace_id' => 'DE',
               'seller_id' => 'seller_123456',
               'account_id' => 'account_123456',
               'location_key' => 'lager-1',
               'category_id' => '12345',
               'payment_policy_id' => 'payment_123',
               'fulfillment_policy_id' => 'shipping_123',
               'return_policy_id' => 'return_123',
               'notification_destination' => 'https://domain.de/webhook',
               'notification_topic' => 'order.created',
               'webhook_secret' => 'zufaelliges-geheimes-secret',
               'webhook_url' => 'wird von dbxShop erzeugt',
               'api_scope' => "products:write\norders:read\nwebhooks:read",
            ),
         );
         $placeholders = $placeholderMap[$platform] ?? $placeholderMap['custom'];
         $placeholderTranslations = array(
            'nicht benoetigt' => $texts->get_fd_message('channels_not_required'),
            'nicht benoetigt bei OAuth' => $texts->get_fd_message('channels_not_required_oauth'),
            'nicht benoetigt bei SP-API' => $texts->get_fd_message('channels_not_required_spapi'),
            'nicht benoetigt bei Basic Auth' => $texts->get_fd_message('channels_not_required_basic'),
            'wird von dbxShop erzeugt' => $texts->get_fd_message('channels_generated'),
            'nur bei freigegebener Schnittstelle' => $texts->get_fd_message('channels_approved_only'),
         );
         foreach ($placeholders as $placeholderField => $placeholderValue) {
            if (isset($placeholderTranslations[$placeholderValue])) {
               $placeholders[$placeholderField] = $placeholderTranslations[$placeholderValue];
            }
         }
         $ph = function (string $field) use ($placeholders): string {
            return $this->h($placeholders[$field] ?? '');
         };
         $secretPlaceholder = function (string $field) use ($placeholders): string {
            return $this->h((string)($placeholders[$field] ?? ''));
         };
         $status = (string)($channel['test_status'] ?? '');
         $statusBadge = $status === 'ok'
            ? '<span class="badge text-bg-success">' . $this->h($texts->get_fd_message('channels_status_ok')) . '</span>'
            : ($status === 'error'
               ? '<span class="badge text-bg-warning">' . $this->h($texts->get_fd_message('channels_status_open')) . '</span>'
               : '<span class="badge text-bg-secondary">' . $this->h($texts->get_fd_message('channels_status_none')) . '</span>');
         $activeBadge = (int)($channel['active'] ?? 1) === 1
            ? '<span class="badge text-bg-success">' . $this->h($texts->get_fd_message('active')) . '</span>'
            : '<span class="badge text-bg-secondary">' . $this->h($texts->get_fd_message('inactive')) . '</span>';
         $exportBadge = (int)($channel['export_enabled'] ?? 0) === 1 ? '<span class="badge text-bg-info">' . $this->h($texts->get_fd_message('channels_export')) . '</span>' : '';
         $orderBadge = (int)($channel['order_import_enabled'] ?? 0) === 1 ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('channels_order_import')) . '</span>' : '';
         $webhookPath = ($key !== '' && $platform !== 'shop') ? '?dbx_modul=dbxShop&dbx_run1=channel_webhook&channel=' . rawurlencode($key) : '';
         $open = $isNew || (int)($_GET['edit'] ?? 0) === $id;
         $editUrl = '?dbx_modul=dbxShop_admin&dbx_run1=channels&edit=' . $id;
         $helpId = $this->ensureShopChannelProviderHelpPage($platform);
         $helpButton = '';
         if ($helpId > 0) {
            $helpTitle = 'Hilfe: Channel ' . ($platformOptions[$platform] ?? 'Channel');
            $helpButton = $this->openWinButton(
               '?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId,
               $helpTitle,
               '<i class="bi bi-question-circle"></i><span class="visually-hidden"> Hilfe</span>',
               'btn btn-outline-secondary btn-sm dbx-shop-channel-help',
               '72%',
               '82%'
            );
            $helpButton = str_replace('<a ', '<a data-dbx="lib=shopAdmin" data-shop-stop-propagation ', $helpButton);
         }

         $form = dbx()->get_system_obj('dbxForm');
         $form->init('shop-channel-form-' . ($isNew ? 'new' : $id), 'shop-channel-form');
         $form->_dd = 'dbxShop|shopChannel';
         $form->_fd = 'dbxShop|shop-channel';
         $form->_data = $channel + array('id' => $id);
         $form->_rid = $isNew ? 0 : $id;
         $form->_action = '?dbx_modul=dbxShop_admin&dbx_run1=channels' . ($isNew ? '&new=1' : '&edit=' . $id);
         $form->set_activ_id($isNew ? 0 : $id);
         $form->set_form_help_enabled(false);
         $form->add_rep('frame_skip_form_wrap', '0');
         $form->add_rep('form_class', 'dbx-shop-channel-dbXForm');
         $form->add_rep('form_attrs', 'data-target="dbxForm_{i}" data-dbx="lib=confirm|class=dbxConfirm|bind=button"');
         $form->add_rep('details_open', $open ? ' open' : '');
         $form->add_rep('channel_key_view', $this->h($key !== '' ? $key : strtolower($texts->get_fd_message('new'))));
         $form->add_rep('channel_title_view', $this->h($channel['title'] ?? $texts->get_fd_message('channels_new')));
         $form->add_rep('platform_view', $this->h($platformOptions[$platform] ?? $platform));
         $form->add_rep('connection_view', $this->h($modeOptions[(string)($channel['connection_mode'] ?? 'manual')] ?? ($channel['connection_mode'] ?? 'manual')));
         $form->add_rep('active_badge', $activeBadge);
         $form->add_rep('export_badge', $exportBadge);
         $form->add_rep('order_badge', $orderBadge);
         $form->add_rep('status_badge', $statusBadge);
         $form->add_rep('last_test_date', !empty($channel['last_test_date']) ? $this->h($channel['last_test_date']) : '');
         $form->add_rep('channel_help_button', $helpButton);
         $form->add_rep('channel_edit_button', !$isNew ? '<a class="btn btn-outline-primary btn-sm dbx-shop-channel-edit" data-dbx="lib=shopAdmin" data-shop-stop-propagation href="' . $this->h($editUrl) . '" title="' . $this->h($texts->get_fd_message('channels_edit_title')) . '"><i class="bi bi-pencil-square"></i> ' . $this->h($texts->get_fd_message('channels_edit')) . '</a>' : '');
         $form->add_rep('hint_api', $this->h($hint[0]));
         $form->add_rep('hint_listing', $this->h($hint[1]));
         $form->add_rep('hint_feedback', $this->h($hint[2]));
         $form->add_rep('test_message', !empty($channel['test_message']) ? '<div class="col-12"><div class="alert alert-secondary py-2 mb-0">' . $this->h($channel['test_message']) . '</div></div>' : '');
         $form->add_rep('test_button', !$isNew ? '<button class="btn btn-outline-secondary btn-sm ms-1" name="shop_action" value="test_channel" title="' . $this->h($texts->get_fd_message('channels_test_title')) . '"><i class="bi bi-plug"></i> ' . $this->h($texts->get_fd_message('channels_test_label')) . '</button>' : '');
         $form->add_rep('delete_button', !$isNew ? '<button type="submit" class="btn btn-outline-danger btn-sm ms-1 dbxConfirm" name="shop_action" value="delete_channel" title="' . $this->h($texts->get_fd_message('channels_delete_title')) . '" data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $this->h($texts->get_fd_message('channels_delete_title')) . '" data-confirm="' . $this->h($texts->get_fd_message('channels_delete_confirm')) . '" data-confirm-hint="<small>' . $this->h($texts->get_fd_message('channels_delete_hint')) . '</small>" data-confirm-buttons="yesno"><i class="bi bi-trash"></i></button>' : '');
         $form->_msg_info = '';

         $form->add_fld('id', tpl: 'dbx|hidden', rules: 'int', dd: 'dd::');
         $form->add_fld('channel_key', placeholder: $placeholders['channel_key'] ?? '', data: $isNew ? '' : 'readonly=readonly');
         $form->add_fld('title');
         $form->add_fld('platform_type', tpl: 'select-single-label', options: $platformOptions);
         $form->add_fld('connection_mode', tpl: 'select-single-label', options: $modeOptions);
         $form->add_fld('sorter');
         $form->add_fld('active');
         $form->add_fld('export_enabled');
         $form->add_fld('order_import_enabled');
         $form->add_fld('description', data: 'rows=2');
         $form->add_fld('api_base_url', placeholder: $placeholders['api_base_url'] ?? '');
         $form->add_fld('api_client_id', placeholder: $placeholders['api_client_id'] ?? '');
         $form->add_fld('api_username', placeholder: $placeholders['api_username'] ?? '');
         $form->add_fld('api_client_secret', placeholder: $secretPlaceholder('api_client_secret'));
         $form->add_fld('api_access_token', placeholder: $secretPlaceholder('api_access_token'));
         $form->add_fld('api_refresh_token', placeholder: $secretPlaceholder('api_refresh_token'));
         $form->add_fld('api_password', placeholder: $secretPlaceholder('api_password'));
         $form->add_fld('marketplace_id', placeholder: $placeholders['marketplace_id'] ?? '');
         $form->add_fld('seller_id', placeholder: $placeholders['seller_id'] ?? '');
         $form->add_fld('account_id', placeholder: $placeholders['account_id'] ?? '');
         $form->add_fld('location_key', placeholder: $placeholders['location_key'] ?? '');
         $form->add_fld('category_id', placeholder: $placeholders['category_id'] ?? '');
         $form->add_fld('payment_policy_id', placeholder: $placeholders['payment_policy_id'] ?? '');
         $form->add_fld('fulfillment_policy_id', placeholder: $placeholders['fulfillment_policy_id'] ?? '');
         $form->add_fld('return_policy_id', placeholder: $placeholders['return_policy_id'] ?? '');
         $form->add_fld('notification_destination', placeholder: $placeholders['notification_destination'] ?? '');
         $form->add_fld('notification_topic', placeholder: $placeholders['notification_topic'] ?? '');
         $form->add_fld('webhook_secret', placeholder: $secretPlaceholder('webhook_secret'));
         $form->add_fld('api_scope', placeholder: $placeholders['api_scope'] ?? '', data: 'rows=2');
         $form->add_obj('webhook_url', 'obj-value', '<label class="form-label">' . $this->h($texts->get_fd_message('channels_webhook_url')) . '</label><input class="form-control form-control-sm" value="' . $this->h($webhookPath) . '" placeholder="' . $ph('webhook_url') . '" readonly>');

         return $form->run();
      };

      $channels = $this->repo()->channels();
      $content = $notice . '<div class="m-3 dbx-shop-channel-list">';
      if ((int)($_GET['new'] ?? 0) === 1) {
         $sorter = 10;
         foreach ($channels as $channel) {
            $sorter = max($sorter, (int)($channel['sorter'] ?? 0) + 10);
         }
         $content .= $rowHtml(array(
            'title' => '',
            'platform_type' => 'custom',
            'connection_mode' => 'api',
            'export_enabled' => 1,
            'order_import_enabled' => 1,
            'active' => 1,
            'sorter' => $sorter,
         ), true);
      }
      foreach ($channels as $channel) {
         $content .= $rowHtml($channel);
      }
      $content .= '</div>';

      $helpId = $this->ensureShopChannelsHelpPage();
      $helpButton = $helpId > 0
         ? $this->openWinButton('?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId, $texts->get_fd_message('channels_help'), '<i class="bi bi-question-circle"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('help')) . '</span>', 'btn btn-outline-secondary btn-sm me-1', '72%', '82%')
         : '';
      $barActions = '<a class="btn btn-outline-primary btn-sm me-1" href="?dbx_modul=dbxShop_admin&dbx_run1=channels&new=1" title="' . $this->h($texts->get_fd_message('channels_new_title')) . '"><i class="bi bi-plus-square"></i><span class="visually-hidden"> ' . $this->h($texts->get_fd_message('channels_new')) . '</span></a>' . $helpButton;
      return $this->frame($content, $texts->get_fd_message('channels_title'), $barActions);
   }

   public function run(): string {
      $run = dbx()->get_modul_var('dbx_run1', 'dashboard', 'parameter');

      switch ($run) {
         case '':
         case 'dashboard':
         case 'start':
            return $this->dashboard();

         case 'install':
            return $this->install();

         case 'products':
            return $this->products();

         case 'product_edit':
            return $this->productEdit();

         case 'product_tree_move':
            return $this->productTreeMove();

         case 'product_channel_mapping':
            return $this->productChannelMapping();

         case 'products_help':
            return $this->productsHelp();

         case 'groups':
            return $this->groups();

         case 'attributes':
            return $this->attributes();

         case 'product_attributes':
            return $this->productAttributes();

         case 'shipping_groups':
            return $this->shippingGroups();

         case 'channel_groups':
            return $this->channelGroups();

         case 'channels':
            return $this->channels();

         case 'media':
            return $this->media();

         case 'assign_media':
            return $this->assignMedia();

         case 'orders':
            return $this->orders();

         case 'order_detail':
            return $this->orderDetail();

         case 'order_invoice':
            return $this->orderInvoice();

         case 'order_invoice_pdf':
            return $this->orderInvoicePdf();

         case 'legal':
            return $this->shopLegalCmsPage(
               'legal',
               'Rechtstexte',
               'Diese Seite kommt aus dem CMS über den stabilen Permalink /shop-rechtstexte. Inhalte wie Anbieterkennzeichnung, AGB, Zahlung, Versand und Datenschutz-Hinweise werden dort gepflegt.',
               'legal'
            );

         case 'returns':
            return $this->returns();

         case 'settings':
            return $this->settings();

         case 'payment_test':
            return $this->paymentTest();

         default:
            return $this->placeholder('Unbekannter Shop-Aufruf', 'dbx_run1=' . $run . ' ist im Shop-Admin-Skeleton noch nicht definiert.');
      }
   }
}
?>
