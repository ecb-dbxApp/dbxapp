# Shop-KI-Referenz {#dbxapp_shop_ai_reference}

Stand: 2026-07-24

Diese Referenz ist der verbindliche Kurzkontext fuer KI-Agenten, die den Shop
analysieren oder erweitern. Die fachliche Erklaerung fuer Menschen steht unter
@ref dbxapp_shop_guide.

## Systemprofil

```yaml
domain: dbxShop
runtime: PHP/dbXapp
frontend_module: dbxShop
admin_module: dbxShop_admin
database_server: dbxShop|dbxShop.db3
schema_source: dbx/modules/dbxShop/dd
frontend_service: dbx/modules/dbxShop/include/dbxShopService.class.php
repository: dbx/modules/dbxShop/include/dbxShopRepository.class.php
admin_service: dbx/modules/dbxShop_admin/include/dbxShopAdmin.class.php
admin_access_group: admin
schema_sync_version: shop-dd-20260713-2
development_data_allowed: true
```

## Verbindliche Schichtentrennung

```text
dbxShop.class.php
  -> wertet dbx_run1 aus
  -> delegiert an dbxShopService

dbxShopService
  -> steuert Frontend-Use-Cases
  -> verwendet dbxForm/dbxTPL
  -> ruft dbxShopRepository und Provideradapter

dbxShopRepository
  -> synchronisiert DD
  -> liest und schreibt Shop-Fachdaten ueber dbxDB
  -> berechnet/verwaltet Zuordnungen, Orders, Bestand und History

dbxShop_admin.class.php
  -> delegiert an dbxShopAdmin

dbxShopAdmin
  -> steuert Admin-Use-Cases
  -> verwendet dbxForm/dbxReport/dbxTPL
  -> ruft dasselbe Repository
```

Keine SQL-Abfragen in Templates. Keine Checkout- oder Preislogik in
JavaScript. Keine zweite Repository- oder ORM-Schicht neben `dbxDB`/DD.

## Vor jeder Shopaenderung lesen

Abhaengig vom Auftrag mindestens:

```text
17_Shop_Leitfaden.md
dbx/modules/dbxShop/dbxShop.class.php
dbx/modules/dbxShop/include/dbxShopService.class.php
dbx/modules/dbxShop/include/dbxShopRepository.class.php
dbx/modules/dbxShop/cfg/config.php
dbx/modules/dbxShop/dd/{betroffene-dd}.dd.php
dbx/modules/dbxShop/fd/{betroffene-fd}.fd.php
dbx/modules/dbxShop/tpl/htm/{betroffene-template}.htm
dbx/modules/dbxShop_admin/include/dbxShopAdmin.class.php
dbx/modules/dbxShop_admin/fd/{betroffene-fd}.fd.php
```

Bei Zahlungen:

```text
dbxShopPayPal.class.php
dbxShopAmazonPay.class.php
fd/checkout.fd.php
```

Bei Channels:

```text
dbxShopChannelConnector.class.php
dd/shopChannel.dd.php
dd/shopProductChannel.dd.php
```

## Routing-Vertrag

```yaml
frontend:
  default: catalog
  routes:
    catalog: catalog
    start: catalog
    product: product
    detail: product
    cart: cart
    checkout: checkout
    paypal_start: paypalStart
    paypal_return: paypalReturn
    paypal_cancel: paypalCancel
    amazon_pay_return: amazonPayReturn
    amazon_pay_cancel: amazonPayCancel
    order: orders
    orders: orders
    invoice_pdf: invoicePdf
    channel_webhook: channelWebhook
    legal: legal
    terms: legal
    return: withdrawal
    returns: withdrawal
    withdrawal: withdrawal
admin:
  default: dashboard
  routes:
    - dashboard
    - install
    - products
    - product_edit
    - product_tree_move
    - product_channel_mapping
    - products_help
    - groups
    - attributes
    - product_attributes
    - shipping_groups
    - channel_groups
    - channels
    - media
    - assign_media
    - orders
    - order_detail
    - order_invoice
    - order_invoice_pdf
    - legal
    - returns
    - settings
    - payment_test
```

Neue Routen werden im Modulrouter registriert und an eine benannte
Service-Methode delegiert. Keine versteckten Aktionen nur ueber ungepruefte
`$_GET`-/`$_POST`-Verzweigungen einfuehren.

## Datenmodellvertrag

Jede Tabelle braucht:

```yaml
primary_field: id
primary_semantics: integer-autoincrement
schema_owner: DD
database_access: dbxDB
```

Aktuelle DD-/Tabellenzuordnung:

```yaml
shopProduct: shop_product
shopProductGroup: shop_product_group
shopProductGroupMap: shop_product_group_map
shopProductImage: shop_product_image
shopAttributeDefinition: shop_attribute_definition
shopProductAttributeValue: shop_product_attribute_value
shopShippingGroup: shop_shipping_group
shopProductShippingGroupMap: shop_product_shipping_group_map
shopChannel: shop_channel
shopProductChannel: shop_product_channel
shopChannelGroup: shop_channel_group
shopChannelGroupChannel: shop_channel_group_channel
shopProductChannelGroupMap: shop_product_channel_group_map
shopOrder: shop_order
shopOrderItem: shop_order_item
shopOrderHistory: shop_order_history
shopWithdrawal: shop_withdrawal
```

Beziehungsregeln:

```yaml
product_primary_group:
  denormalized_field: shop_product.product_group_id
  normalized_map: shop_product_group_map
  primary_marker: is_primary
product_shipping_groups:
  map: shop_product_shipping_group_map
product_channels:
  direct: shop_product_channel
  inherited_via:
    - shop_product_channel_group_map
    - shop_channel_group_channel
product_images:
  product_reference: product_id
  group_reference: group_id
  cms_media_reference: media_id
orders:
  header: shop_order
  items: shop_order_item
  events: shop_order_history
  withdrawals: shop_withdrawal
```

Eine DD-Aenderung erfordert:

1. DD-Datei anpassen.
2. Datenbankabhaengige Defaults und Indizes pruefen.
3. `schema_sync_version` in Repository und Konfiguration konsistent erhoehen,
   wenn der automatische Sync erneut laufen muss.
4. Sync auf einer leeren und einer bereits initialisierten Testdatenbank
   pruefen.
5. Fuer jede neue Tabelle `id`/Autoincrement auf allen unterstuetzten
   DB-Treibern pruefen.

## Kritische Fachinvarianten

### Artikel

```yaml
sku: stable-and-unique-business-key
slug: url-safe
active_catalog_visibility: active=1 AND trash=0 AND channel=shop
price_authority: server
tax_authority: server
shipping_authority: server
stock_authority: server
```

### Bestellung

```yaml
order_no: stable-public-reference
items: snapshot-at-order-time
legal_text: optional-snapshot-at-order-time
withdrawal_text: optional-snapshot-at-order-time
payment_payload: provider-result-not-authorization
history: append-business-events
foreign_order_access: forbidden
duplicate_provider_return: must-not-create-duplicate-order
```

Artikelpreise oder Titel duerfen bestehende `shop_order_item`-Snapshots nicht
nachtraeglich veraendern.

### Warenkorb

```yaml
storage: PHP session
key: dbxShop_cart
trusted_price: false
trusted_tax: false
trusted_stock: false
```

Der Warenkorb speichert Auswahl und Menge. Vor Checkout sind Produkt,
Verfuegbarkeit und Preise erneut serverseitig aus dem Repository zu lesen.

## Konfigurationsvertrag

Quelle: `dbx()->get_config('dbxShop')`.

```yaml
general:
  - enabled
  - default_channel
  - default_currency
  - price_display
  - tax_display_enabled
  - default_tax_class
  - tax_rates
  - b2b_mode
  - stock_enabled
  - channels_enabled
checkout:
  - checkout_guest_allowed
  - legal_snapshot_enabled
  - withdrawal_button_enabled
mail:
  - mail_customer_enabled
  - mail_admin_enabled
  - mail_from
  - mail_admin_to
payment:
  - payment_bank_transfer_*
  - payment_invoice_*
  - payment_paypal_*
  - payment_amazon_pay_*
delivery:
  - delivery_digital_download_enabled
  - delivery_flat_shipping_enabled
  - delivery_flat_shipping_gross_price
media:
  - media_usage_content_id
  - media_usage_slot
```

Keine gleichbedeutenden Werte in einer zweiten Konfigurationsdatei einfuehren.
`cfg/payment.php` ist aktuell nur ein PayPal-Fallback; die Admin-Konfiguration
liegt in `dbxShop`-Config.

## Zahlungssicherheit

```yaml
providers:
  offline:
    - bank_transfer
    - invoice
  online:
    - paypal
    - amazon_pay
secrets:
  render_in_html: false
  log_plaintext: false
  commit_real_values: false
provider_result:
  verify_server_side: true
  bind_to_order: true
  persist_status: true
  append_history: true
```

Ein Redirect oder Queryparameter ist kein Zahlungsnachweis. Der Adapter muss
die Providerantwort serverseitig verarbeiten und die Bestellung anhand einer
gespeicherten Referenz zuordnen.

## Channel-Sicherheit

```yaml
default_channels:
  - shop
  - amazon
  - ebay
  - kleinanzeigen
  - mobile
webhook_route: "?dbx_modul=dbxShop&dbx_run1=channel_webhook&channel={key}"
required_controls:
  - active_channel
  - order_import_enabled
  - webhook_secret_or_provider_signature
  - normalized_payload
  - stable_external_reference
  - duplicate_protection
  - failure_logging_without_secrets
```

Ein vorbereiteter Connector ist keine Garantie fuer produktive API-Abdeckung.
Bei Aenderungen offizielle Provideranforderungen, vorhandene Credentials und
den konkreten Code pruefen. Keine Live-Calls als Teil eines normalen lokalen
Tests ohne ausdruecklichen Auftrag.

## Rechte und Eingabevertrauen

```yaml
admin_module_group: admin
request_ids_trusted: false
session_ids_trusted: false
posted_prices_trusted: false
posted_status_trusted: false
uploaded_filenames_trusted: false
webhook_json_trusted: false
```

Eine ID aus `$_SESSION` hilft beim Wiederfinden, ersetzt aber keine
Eigentums-/Rechtepruefung. Fuer Order, Invoice, Withdrawal und Media ist immer
Datensatzbezug plus Berechtigung zu validieren.

## Template- und Designvertrag

- Shop-Ausgabe verwendet `dbxShop|...`-Templates.
- Produktkarten und Details duerfen ueber Artikelgruppen ausgewaehlt werden.
- Template-Namen werden als erlaubte Modul-Templates validiert.
- Globale Seitenschale kommt aus dem aktiven Frontend-Design.
- Shop-CSS bleibt unter `dbxShop/design`, nicht als Kopie in jedem globalen
  Design.
- Shop-Admin-CSS bleibt unter `dbxShop_admin/design`.
- Gemeinsame Aenderungen muessen in `dbxapp` und `flowers` getestet werden.

## Aufgabe-zu-Datei-Matrix

| Aufgabe | Primaere Dateien |
| --- | --- |
| neue Frontendroute | `dbxShop.class.php`, `dbxShopService.class.php`, Template |
| neue Adminroute | `dbxShopAdmin.class.php`, Admin-Template/FD |
| neues Datenfeld | betreffende DD, Repository, FD/Form, Template |
| neue Tabelle | neue DD, Repository-Syncliste, Schema-Version |
| Katalogfilter | Repository/Service, Filter-FD, Katalogtemplates |
| Produktdarstellung | Produktgruppenfelder, Shop-Templates, Shop-CSS |
| neue Zahlungsart | eigener Adapter, Checkout-FD, Service, Settings-FD |
| neuer Channel | Channel-DD/Defaults, Connector, Adminformular, Mapping |
| neue Adminliste | `dbxReport`, Auswahl-FD, Reporttemplate |
| neue Eingabemaske | `dbxForm`, DD/FD, Template |

## Verbotene Abkuerzungen

- Keine Tabelle direkt nur mit `CREATE TABLE` anlegen.
- Keine manuelle ID-Vergabe als Ersatz fuer Autoincrement.
- Keine Preise oder Berechtigungen aus dem Browser uebernehmen.
- Keine externen Secrets in Quelltext/Testdaten hinterlassen.
- Keine Bestellungen allein anhand einer frei geratenen ID ausgeben.
- Keine Produktivzahlung durch simulierten Return als erfolgreich markieren.
- Keine Channel-Nachricht ohne Authentifizierung importieren.
- Kein leeres Webhook-Secret als implizite Freigabe behandeln und keine
  Webhook-Secrets aus GET-Query-Strings lesen.
- Kein Browser-Return, `order_no`-Parameter oder Cancel-Link als
  Zahlungsnachweis behandeln.
- Keine grosse HTML-Ausgabe neu als PHP-String bauen, wenn ein Template passt.
- Keine Kernel-Aenderung, solange das Problem innerhalb des Shop-Moduls loesbar
  ist.

## Arbeitsablauf fuer KI-Agenten

1. Ist-Zustand in Router, Service, Repository, DD, FD und Template lesen.
2. Betroffene Invarianten und Rechte nennen.
3. Datenmodellaenderung zuerst in DD entwerfen.
4. Fachlogik in Repository/Service implementieren.
5. Ausgabe und Eingabe ueber Template, `dbxForm` oder `dbxReport` anbinden.
6. Fehlerwege, Wiederholung und Transaktionsgrenzen beruecksichtigen.
7. Tests mit vorhandenen Testdaten ausfuehren; Testdaten duerfen bleiben.
8. Leere Datenbank, vorhandene Datenbank und Autoincrement pruefen, wenn das
   Schema betroffen ist.
9. Gast, Benutzer und Admin pruefen, wenn Zugriff betroffen ist.
10. Menschen- und KI-Dokumentation aktualisieren, wenn sich ein Vertrag
    aendert.

## Mindesttests nach Bereich

```yaml
schema:
  - empty_database_sync
  - existing_database_sync
  - id_autoincrement
  - foreign_key_like_references
catalog:
  - active_visibility
  - shop_channel_visibility
  - query_search
  - group_filter
  - attribute_filter
cart_checkout:
  - add_update_remove
  - server_price_recalculation
  - invalid_customer_data
  - missing_legal_acceptance
  - stock_conflict
order:
  - unique_order
  - item_snapshot
  - own_access
  - foreign_access_denied
  - invoice_access
payment:
  - disabled_provider_hidden
  - missing_credentials_hidden
  - success
  - cancel
  - provider_error
  - repeated_return
admin:
  - non_admin_denied
  - forms
  - reports
  - bulk_actions
  - secret_masking
channel:
  - inactive_rejected
  - invalid_signature_rejected
  - duplicate_payload
  - export_error_persisted
design:
  - dbxapp
  - flowers_light
  - flowers_dark
  - responsive
```

## Abschlussbericht einer KI

Der Bericht trennt:

1. Datenmodell/Schema,
2. Repository/Fachlogik,
3. Frontend,
4. Administration,
5. externe Provider/Channels,
6. Tests und verwendete Testdaten,
7. offene Risiken oder bewusst nicht produktive Integrationen.
