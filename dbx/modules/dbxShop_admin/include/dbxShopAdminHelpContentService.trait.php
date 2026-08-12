<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Hilfeseiten und Rechtstexte als CMS-Inhalte mit zentraler Cache-/Permalinkpflege.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminHelpContentServiceTrait {


   private function shopMediaUsageContentId(): int {
      $configured = (int)dbx()->get_cfg('dbxShop', 'media_usage_content_id');
      if ($configured > 0 && $this->contentPageExists($configured, $this->shopMediaUsageContentDd())) {
         return $configured;
      }

      return $this->ensureShopMediaUsagePage();
   }



   private function contentDd(): string {
      return dbx()->lng_name('content');
   }



   private function shopMediaUsageLng(): string {
      return dbxContentMediaUsageScope::language(dbxContentLngSync::masterLng());
   }



   private function shopMediaUsageContentDd(): string {
      return dbxContentLng::ddContent($this->shopMediaUsageLng());
   }



   private function shopMediaUsageFolderDd(): string {
      return dbxContentLng::ddFolder($this->shopMediaUsageLng());
   }



   private function folderDd(): string {
      return dbx()->lng_name('content_folder');
   }



   private function contentPageExists(int $contentId, string $dd = ''): bool {
      if ($contentId <= 0) {
         return false;
      }
      try {
         $row = $this->db()->select1($dd !== '' ? $dd : $this->contentDd(), $contentId, 'id', 0);
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
         . '<dt>Shop-Absender</dt><dd>Eigene From-Adresse fuer Bestellungen, Statusmeldungen und Widerrufe. Verwenden Sie eine echte Domain-Adresse, z.B. <code>shop@example.org</code>. Eine stabile Shop-Adresse erleichtert automatische Mailprozesse beim Empfaenger.</dd>'
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



   private function loadContentCacheSupport(): void {
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
      $this->loadContentCacheSupport();
      if (class_exists('\\dbx\\dbxContent\\dbxContentPageCache')) {
         \dbx\dbxContent\dbxContentPageCache::invalidateContent($cid);
      }
   }



   private function syncShopHelpPermalinkIndex(int $cid, string $permalink, string $rights = 'admin'): void {
      if ($cid <= 0 || $permalink === '') {
         return;
      }
      $this->loadContentCacheSupport();
      if (class_exists('\\dbx\\dbxContent\\dbxContentPermalinkIndex')) {
         \dbx\dbxContent\dbxContentPermalinkIndex::upsertPage($cid, $permalink, $rights, 1);
      }
   }



   private function removeShopHelpPermalinkIndex(string $permalink): void {
      $permalink = trim($permalink);
      if ($permalink === '') {
         return;
      }
      $this->loadContentCacheSupport();
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
}
