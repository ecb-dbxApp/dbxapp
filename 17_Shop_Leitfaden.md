# Shop-Leitfaden {#dbxapp_shop_guide}

Stand: 2026-08-01

`dbxShop` ist die Shop-Fachanwendung von dbxapp. Sie umfasst inzwischen den
öffentlichen Katalog, Produktsuche und Filter, Warenkorb, Checkout,
Zahlungsarten, Bestellungen, Rechnungen, Widerruf, Medien, Artikelgruppen,
Attribute, Versandgruppen und Verkaufskanäle. Die Administration liegt in
einem getrennten Modul `dbxShop_admin`.

Der Shop nutzt die normalen dbxapp-Bausteine: Routing über `dbx_run1`,
Templates über `dbxTPL`, Datenzugriff über `dbxDB`, Struktur über DD,
Formulare über `dbxForm`, Listen über `dbxReport`, CMS-Seiten für
Rechtstexte und `openWin` für Admin-Dialoge.

## Fachliches Gesamtbild

dbxShop deckt den Weg von einem gepflegten Produkt bis zur nachvollziehbaren
Bestellung ab:

```text
Produktstamm
  -> Gruppen, Attribute, Medien, Preis, Steuer und Versand
  -> Freigabe für interne oder externe Verkaufskanäle
  -> Katalog, Suche, Filter und Produktdetail
  -> Warenkorb und serverseitige Neuberechnung
  -> Checkout, Rechtstexte und Zahlungsart
  -> Bestellung mit unveränderlichen Positions-Snapshots
  -> Zahlung, Rechnung, Versand, Tracking und Historie
  -> optional Widerruf und Channel-Rückmeldungen
```

Der Shop ist dabei kein isoliertes Fremdsystem. CMS, Benutzer, Medien,
Workflow, Mail, PDF, Konfiguration und Designs werden über die vorhandenen
dbxapp-Bausteine verbunden.

## Rollen und typische Aufgaben

| Rolle | Typische Aufgaben |
| --- | --- |
| Besucher/Kunde | suchen, filtern, Produkt ansehen, Warenkorb, Checkout, Bestellung/Rechnung ansehen, Widerruf senden |
| Produktpflege | Produkt, Gruppe, Attribute, Bilder, Preis, Steuer, Bestand und Versand pflegen |
| Shop-Admin | Bestellungen, Zahlungen, Rechnung, Versand, Tracking, Rechtstexte und Einstellungen verwalten |
| Channel-Manager | Kanäle konfigurieren, Mapping prüfen, Produkte exportieren, Webhooks überwachen |
| Entwickler | Repository/Service/Adapter erweitern, ohne Preis- oder Rechteprüfungen ins Template zu verlagern |

## Produktlebenszyklus

1. Produkt mit eindeutiger SKU und verständlichem Slug anlegen.
2. Titel, Beschreibung, Produkttyp und Aktivstatus pflegen.
3. Brutto-/Nettopreis, Steuerklasse und gegebenenfalls Bestand festlegen.
4. Eine Primärgruppe und optionale weitere Gruppen zuordnen.
5. Attribute der Gruppen ausfüllen; filterbare Attribute bewusst markieren.
6. Produkt- oder Gruppenbilder aus der zentralen Medienverwaltung zuordnen.
7. Versandgruppen und Lieferinformationen prüfen.
8. interne bzw. externe Channels aktivieren und Overrides pflegen.
9. Produktvorschau in Katalog und Detailansicht prüfen.
10. Produkt freigeben bzw. über den passenden Workflow veröffentlichen.

Gruppen sind mehr als Navigation. Sie können Darstellung, Attribute, Steuer-
und Versanddefaults sowie Channel-Gruppen bündeln. Ein konkreter Produktwert
gewinnt, ein leerer/erbender Wert verwendet den fachlich vorgesehenen
Gruppendefault.

## Kundenerlebnis

### Katalog

Der öffentliche Katalog kombiniert Suche, Gruppenbaum und Attributfilter.
Angezeigt werden nur aktive, für den internen `shop`-Channel freigegebene
Produkte. Produktkarten kommen aus einem TPL-Report; dadurch bleiben Filter,
Trefferzahl und Pagination Teil der Reportpipeline, obwohl keine klassische
Tabelle sichtbar ist.

### Produktdetail

Die Detailansicht zeigt:

- Mediengalerie und Primärbild,
- Titel, Kurz- und Langbeschreibung,
- Preis-/Steuerinformation,
- Lieferzeit, Versand und Bestand,
- sichtbare Produktattribute,
- Warenkorbaktion,
- gruppenabhängiges Detail- und Gallerytemplate.

Eine SKU oder ID aus der URL wird immer gegen aktiven Channel, Aktivstatus und
Zugriffsregeln geprüft.

### Warenkorb

Der Session-Warenkorb ist nur eine Benutzerabsicht. Vor Anzeige und Checkout
werden Produkte, Mengen, Preis, Steuer, Bestand und Lieferbarkeit erneut aus
dem Repository bestimmt. Vom Browser übergebene Summen oder Preise sind nie
die Berechnungsgrundlage.

### Checkout

Der Checkout verwendet dbxForm und führt den Kunden durch Kontakt-/Adressdaten,
Zahlungsart, Hinweise und Zustimmungen. Nur konfigurierte Zahlungsarten werden
angeboten. Rechtstexte können als Snapshot in der Bestellung gespeichert
werden, damit der zum Kaufzeitpunkt akzeptierte Stand erhalten bleibt.

### Sprachabhängige Oberfläche

Katalogfilter, Kauf- und Warenkorbformular, Checkout sowie die öffentliche
Bestellliste besitzen eigene deutsche, englische und spanische FDs:

```text
shop-catalog-filter-form[_en|_es].fd.php
shop-cart[_en|_es].fd.php
checkout[_en|_es].fd.php
shop-orders[_en|_es].fd.php
```

Die jeweilige FD liefert neben Feldlabels auch Seiten-/Bar-Titel,
Reportspalten, Statusbeschriftungen, leere Zustände, Zahlungs- und
Validierungshinweise sowie Confirmtexte. `dbxForm` und `dbxReport` laden diese
Meldungen über ihren gemeinsamen FD-Mechanismus. Allgemeine Browserknöpfe wie
Ja/Nein kommen aus der zentralen JavaScript-Übersetzung.

Produktnamen, Gruppennamen, Attribute, Liefertexte oder Zahlungsanweisungen aus
der Datenbank sind gespeicherte Fachdaten. In einer einsprachigen Shoptabelle
werden sie nicht durch eine FD scheinübersetzt. Dafür wären echte
Sprachtabellen und passende Sprach-DDs erforderlich.

## Bestelllebenszyklus

Eine Bestellung besitzt zwei fachlich getrennte Zustände:

- Bestell-/Abwicklungsstatus, z. B. Zahlung ausstehend, in Bearbeitung,
  versendet oder abgeschlossen.
- Zahlungsstatus und Providerreferenz, z. B. offen, bestätigt, fehlgeschlagen
  oder erstattet.

```text
Checkout validiert
  -> Order und Items einmalig anlegen
  -> Zahlungsreferenz/Provider starten
  -> Provider-Rückkehr oder Offline-Zahlung verbuchen
  -> History-Ereignis schreiben
  -> Rechnung erzeugen/freigeben
  -> Versand und Tracking pflegen
  -> Abschluss oder Widerruf
```

`shop_order_item` speichert den kaufzeitbezogenen Snapshot. Änderungen an SKU,
Titel, Preis oder Steuer eines Produkts dürfen bestehende Bestellpositionen
nicht nachträglich verändern. Statusänderungen werden in
`shop_order_history` nachvollziehbar festgehalten.

## Preis, Steuer, Versand und Bestand

- Währung und Brutto-/Nettoanzeige kommen aus der Shopkonfiguration.
- Steuerklassen werden zentral konfiguriert und einem Produkt bzw. Default
  zugeordnet.
- Channelpreise können den internen Preis überschreiben; der definierte
  Vererbungswert verwendet wieder den Produkt-/Gruppenpreis.
- Versandgruppen bestimmen Lieferzeit, Kosten und Freigrenzen.
- Bei aktivem Bestand werden Verfügbarkeit und Mengen serverseitig geprüft.
- Digitale Produkte können einen eigenen Liefer-/Versandweg verwenden.

Rundung und Summenbildung gehören in die Fachlogik. Templates formatieren nur
bereits berechnete Werte.

## Modulaufteilung

```text
dbx/modules/dbxShop/
  dbxShop.class.php                  Frontend-Router
  cfg/config.php                     Shop-Konfiguration
  cfg/payment.php                    PayPal-Fallbackkonfiguration
  dd/*.dd.php                        Shop-Datenmodell
  fd/*.fd.php                        Produkt-, Checkout- und Fachformulare
  include/dbxShopRepository.class.php Datenzugriff und Fachdaten
  include/dbxShopService.class.php   Frontend-Anwendungslogik
  include/dbxShopPayPal.class.php    PayPal-Adapter
  include/dbxShopAmazonPay.class.php Amazon-Pay-Adapter
  include/dbxShopChannelConnector.class.php Channel-Adapter
  tpl/htm/*.htm                      Frontend-Templates
  design/css/shop.css                Shop-Komponentenstil
  design/js/shop.js                  Shop-Komponentenverhalten
  tools/                             Testdaten- und Mockup-Werkzeuge

dbx/modules/dbxShop_admin/
  dbxShop_admin.class.php            Admin-Router-Wrapper
  include/dbxShopAdmin.class.php     Admin-Anwendungslogik
  cfg/config.php                     Zugriff: Gruppe admin
  fd/*.fd.php                        Settings- und Reportfelder
  tpl/htm/*.htm                      Admin-Templates
  design/css/shop-admin.css          Admin-Komponentenstil
```

`dbxShop` ist bewusst Frontend und verwendet das aktive Frontend-Design.
`dbxShop_admin` besitzt das Suffix `_admin` und wird für Administratoren vom
Design-Router auf `default_design_admin` aufgelöst. Aktuell ist das
Admin-Design `dbxapp`.

## Frontend-Routen

Aufrufmuster:

```text
?dbx_modul=dbxShop&dbx_run1={route}
```

| `dbx_run1` | Aufgabe |
| --- | --- |
| `start`, `catalog` | Katalog mit Suche, Gruppen- und Attributfiltern |
| `product`, `detail` | Produktdetail anhand SKU/Parameter |
| `cart` | Warenkorb anzeigen und Mengen bearbeiten |
| `checkout` | Kundendaten, Zahlungsart und Zustimmung erfassen |
| `paypal_start` | PayPal-Ablauf aus dem Checkout starten |
| `paypal_return` | PayPal-Rückkehr erfassen und Zahlung verbuchen |
| `paypal_cancel` | abgebrochene PayPal-Zahlung behandeln |
| `amazon_pay_return` | Amazon-Pay-Rückkehr verarbeiten |
| `amazon_pay_cancel` | abgebrochene Amazon-Pay-Zahlung behandeln |
| `order`, `orders` | eigene bzw. zuletzt erzeugte Bestellungen anzeigen |
| `invoice_pdf` | zugreifbare Rechnung als PDF ausgeben |
| `channel_webhook` | externe Bestell-/Channel-Rückmeldung annehmen |
| `legal`, `terms` | CMS-Rechtstexte des Shops ausgeben |
| `return`, `returns`, `withdrawal` | Widerrufsseite und Formular ausgeben |

Beispiel für eine CMS- oder Template-Inclusion:

```html
[modul=dbxShop]dbx_run1=catalog[/modul]
```

## Admin-Routen

Aufrufmuster:

```text
?dbx_modul=dbxShop_admin&dbx_run1={route}
```

| `dbx_run1` | Aufgabe |
| --- | --- |
| `dashboard`, `start` | Kennzahlen und Schnellzugriffe |
| `install` | DD-Schema, Defaults und Testgrundlage sicherstellen |
| `products` | Artikelliste, Auswahl- und Massenaktionen |
| `product_edit` | Artikel bearbeiten oder neu anlegen |
| `product_tree_move` | Artikelgruppe im Baum verschieben |
| `product_channel_mapping` | Channel-spezifische Artikeldaten pflegen |
| `products_help` | kontextbezogene Produkthilfe |
| `groups` | hierarchische Artikelgruppen verwalten |
| `attributes` | Attributdefinitionen verwalten |
| `product_attributes` | Attributwerte eines Artikels pflegen |
| `shipping_groups` | Versandarten und Kosten verwalten |
| `channel_groups` | Vertriebsszenarien aus Channels bilden |
| `channels` | interne und externe Verkaufskanäle konfigurieren |
| `media` | Shop-Medien anzeigen und hochladen |
| `assign_media` | Medien Artikeln oder Gruppen zuordnen |
| `orders` | Bestellreport mit Filtern und Aktionen |
| `order_detail` | Status, Zahlung, Versand, Tracking und Notiz pflegen |
| `order_invoice` | Rechnung als HTML anzeigen |
| `order_invoice_pdf` | Rechnungs-PDF erzeugen/oeffnen |
| `legal` | Rechtstexte im CMS pflegen |
| `returns` | Widerrufe administrieren |
| `settings` | Shop-, Steuer-, Zahlungs-, Mail- und Versandeinstellungen |
| `payment_test` | konfigurierte Zahlungsanbieter prüfen |

Der Modulzugriff ist über `dbxShop_admin/cfg/config.php` auf die Gruppe
`admin` beschraenkt. Fachaktionen dürfen diese Modulberechtigung nicht durch
direkte, ungeschuetzte Hilfsrouten umgehen.

## Datenmodell

Alle Shop-DDs verwenden derzeit den Server `dbxShop|dbxShop.db3`. Jede Tabelle
besitzt die Primaer-ID `id`. Die physischen Tabellen werden aus den DD-Dateien
synchronisiert und sollen nicht parallel als handgeschriebenes SQL-Schema
gepflegt werden.

### Artikel und Darstellung

| DD | Tabelle | Zweck |
| --- | --- | --- |
| `shopProduct` | `shop_product` | SKU, Slug, Titel, Typ, Preis, Steuer, Versand, Bestand, Aktivstatus |
| `shopProductGroup` | `shop_product_group` | hierarchische Gruppen, Steuer-/Versanddefaults, Karten-, Detail- und Gallery-Templates |
| `shopProductGroupMap` | `shop_product_group_map` | Artikel-zu-Gruppe, einschliesslich Primaergruppe |
| `shopProductImage` | `shop_product_image` | CMS-Medium oder Bildpfad für Artikel/Gruppe |
| `shopAttributeDefinition` | `shop_attribute_definition` | gruppenbezogene Text-, Auswahl- oder Zahlenattribute |
| `shopProductAttributeValue` | `shop_product_attribute_value` | konkrete Attributwerte eines Artikels |

Die Artikelgruppe kann Darstellungsvorgaben erben lassen, zum Beispiel
`card_template`, `detail_template`, `gallery_template`, Bildanzahl, Bildmodus,
Overflow und Klickverhalten. Fachliche Artikelwerte bleiben im Artikel; die
Gruppe stellt Defaults und Kategorisierung bereit.

### Versand und Channels

| DD | Tabelle | Zweck |
| --- | --- | --- |
| `shopShippingGroup` | `shop_shipping_group` | Versandweg, Lieferzeit, Kosten und Freigrenze |
| `shopProductShippingGroupMap` | `shop_product_shipping_group_map` | Artikel-zu-Versandgruppe |
| `shopChannel` | `shop_channel` | Plattform, Verbindung, Zugang, Export/Import und Teststatus |
| `shopProductChannel` | `shop_product_channel` | Aktivierung, Channel-SKU, Preis-/Versandoverride und Exportstatus |
| `shopChannelGroup` | `shop_channel_group` | wiederverwendbare Gruppe von Verkaufskanälen |
| `shopChannelGroupChannel` | `shop_channel_group_channel` | Channels innerhalb einer Channel-Gruppe |
| `shopProductChannelGroupMap` | `shop_product_channel_group_map` | Artikel-zu-Channel-Gruppe |

Direkte Channel-Zuordnungen und geerbte Zuordnungen über Channel-Gruppen
werden gemeinsam aufgelöst. Ein Channel-spezifischer Preis von `-1` bedeutet
sinngemäß, dass der Artikel- bzw. Gruppenwert verwendet wird.

### Bestellung und Widerruf

| DD | Tabelle | Zweck |
| --- | --- | --- |
| `shopOrder` | `shop_order` | Kunde, Summe, Channel, Zahlung, Rechnung, Bestand, Versand und Rechtstext-Snapshots |
| `shopOrderItem` | `shop_order_item` | unveränderlicher Bestellpositions-Snapshot |
| `shopOrderHistory` | `shop_order_history` | Status- und Fachereignisse einer Bestellung |
| `shopWithdrawal` | `shop_withdrawal` | Widerruf mit Bezug zu Bestellung/Kunde und Adminstatus |

Bestellpositionen speichern SKU, Titel, Menge, Preis, Steuer und Versand zum
Bestellzeitpunkt. Nachtraegliche Artikelaenderungen dürfen eine vorhandene
Bestellung deshalb nicht rückwirkend umschreiben.

## Installation, DD-Sync und Testdaten

`dbxShopRepository::install()` synchronisiert die 17 Shop-DDs, legt
Standard-Channels an und bereinigt bestimmte Zuordnungen. Die Synchronisation
wird mit `schema_sync_version` begrenzt. Eine Änderung an einem DD erfordert
deshalb auch eine neue Schema-Versionskennung, wenn die automatische
Synchronisation erneut laufen soll.

Wenn Artikel, Versandgruppen, Channel-Gruppen oder Bilder fehlen, ruft der
aktuelle Shop `seedDemoProducts()` auf. Das Entwicklungsprojekt darf
Testdaten enthalten. Die mitgelieferten Testdaten umfassen unter anderem
Software- und Servicegruppen, Versandgruppen, Channel-Gruppen und Demoartikel.

Werkzeuge für gezielte Testdaten und Mockups liegen unter:

```text
dbx/modules/dbxShop/tools/seed_dbxapp_merch.php
dbx/modules/dbxShop/tools/generate_dbxapp_merch_mockups.php
```

## Katalog und Produktdarstellung

Der Katalog zeigt nur aktive Artikel, die dem internen Channel `shop`
zugeordnet sind. Er unterstützt:

- Volltextsuche mit gewichteten Artikel-, Gruppen- und Attributwerten,
- hierarchische Artikelgruppen und Breadcrumbs,
- filterbare Attribute,
- gruppenspezifische Karten- und Detailtemplates,
- Artikel- und Gruppenbilder aus dem CMS-Medienbestand,
- Steuer-, Liefer- und Bestandsinformationen.

Vorhandene Templates sind unter anderem:

```text
product-card-default.htm
product-card-compact.htm
product-detail-default.htm
product-detail-technical.htm
shop-catalog-report.htm
shop-catalog-filter-form.htm
```

Neue Varianten werden als Shop-Templates ergänzt und über die Gruppe
ausgewählt. Der Service validiert den Template-Namen; ungeprüfte Dateipfade
aus Datenbankwerten sind nicht erlaubt.

### Einheitliches Laden von Produktlisten

Produktlisten verwenden im Repository immer denselben einfachen Ablauf:

1. Produktzeilen bzw. leichte Filterkandidaten laden.
2. Beziehungen für die benoetigte Produktmenge gebuendelt über `dbxDB` und
   die vorhandenen DDs laden.
3. Gruppen, Versand, Channels, Bilder und Attribute im Speicher per ID
   zuordnen.
4. Nur die sichtbare Katalogseite vollständig darstellen.

`products()` für die Administration und `productsByIds()` für den
öffentlichen Report verwenden beide `decorateProducts()`. Die Methode bildet
eine kurzlebige Datensicht nur für den aktuellen Aufruf. Sie ist kein
prozess- oder requestuebergreifender Cache und benötigt deshalb keine
Invalidierung. Einzelabrufe wie `productById()` und `productBySku()` bleiben
unverändert kompatibel.

`dbxDB::select1()` besitzt einen universellen requestlokalen Einzelsatz-Cache.
Er wiederholt identische DD-/WHERE-/Spaltenzugriffe nicht und wird bei jedem
Schreibzugriff auf dieselbe DD verworfen. Transaktionen umgehen diesen Cache.
Die Repository-Mengenabfragen bleiben davon unberührt: Sie sind weiterhin
der richtige Weg für vollständige Produktlisten mit vielen unterschiedlichen
IDs, während der zentrale Cache wiederholte Zugriffe auf denselben Einzelsatz
vermeidet.

Auch die Darstellung folgt dem Mengenprinzip. Der Service liest einmal pro
Request die in einem Karten- oder Detailtemplate vorhandenen
`{replacement_namen}` und erzeugt nur deren Werte. Eine Katalogkarte baut
dadurch keine unsichtbare Detailgalerie, Attributtabelle, Versand-/Lageransicht
oder dbxForm-Instanz mehr. Eigene Shop-Templates bleiben kompatibel: Sobald sie
einen bekannten Platzhalter verwenden, wird dessen Wert weiterhin vollständig
erzeugt. Der Cache enthält nur die Platzhalternamen der lokalen Template-Datei,
keine Produkt-, Benutzer- oder Formulardaten.

Die Admin-Bildliste folgt demselben Vertrag. `allImages()` lädt alle benötigten
Produkt- und Gruppentitel in höchstens zwei Mengenabfragen und ordnet sie per
ID zu. Neue Adminlisten dürfen nicht innerhalb einer Ergebnis-Schleife
`select1()` für reine Bezeichnungen aufrufen. Der zentrale Cache ist kein
Ersatz für eine fachlich zusammengehörige Listendatensicht.

## Warenkorb und Checkout

Der Warenkorb wird in der PHP-Session unter `dbxShop_cart` geführt. Der
Checkout erfasst Name, E-Mail, Telefon, Lieferadresse, Notiz und Zahlungsart.
Rechtstexte und Widerrufsbelehrung müssen bestaetigt werden.

Ändernde Warenkorbaktionen bleiben POST-Aktionen im vorhandenen
`dbxReport`-/`dbxForm`-Vertrag. `remove` und `clear` sind benannte
Submit-Buttons; `ajax.js` übernimmt deren `name/value` zusätzlich zu den
Formulardaten. Nach einem Bestätigungsdialog hält ein kurzlebiges Hidden-Feld
den bestätigten `name/value`-Wert fest und der reguläre Formular-Submit
entscheidet weiter zwischen AJAX und Browsernavigation. Dadurch funktionieren
Entfernen und Leeren sowohl mit AJAX als auch beim nativen Formular-Fallback.
Die vorhandene dbxForm-CSRF-Prüfung bleibt unverändert; ein separater
`dbx_token` wurde dafür nicht eingeführt.

Der aktuelle Gesamtzähler steht am zurückgegebenen Warenkorb-Root in
`data-dbx-shop-cart-count`. Das modulbezogene `design/js/shop.js` synchronisiert
damit nach `ajax:after` alle Menü-Badges. Die dbxapp-Asset-Version aus `VERSION` stellt
sicher, dass Browser die korrigierten Kernel-Bibliotheken neu laden.

Der Ablauf ist:

1. Warenkorbpositionen gegen aktuelle Produkte prüfen.
2. Bestand prüfen, wenn `stock_enabled` aktiv ist.
3. Kundendaten und Zahlungsart validieren.
4. Rechtstexte und Widerruf als Snapshot speichern, wenn aktiviert.
5. Bestellung und Positionen erzeugen.
6. Bestand gegebenenfalls reservieren.
7. Bei Offline-Zahlung Bestaetigungsseite und Mail ausgeben.
8. Bei PayPal/Amazon Pay zum Provider wechseln und Rückkehr verarbeiten.
9. Bestell- und Zahlungsereignisse in der History protokollieren.

Gastbestellungen werden mit `checkout_guest_allowed` gesteuert. Die
öffentliche Anzeige einer Bestellung oder Rechnung muss weiterhin die im
Service implementierte Zugriffsprüfung benutzen; eine frei uebergebene ID
allein ist keine Berechtigung.

## Zahlungsarten

Aktuell unterstützt die Konfiguration:

| Zahlungsart | Aktivierung |
| --- | --- |
| Vorkasse/Überweisung | Schalter plus optionale Bankdaten und Anweisung |
| Rechnung | Schalter plus Rechnungshinweis |
| PayPal | Schalter, Modus, Client-ID und Client-Secret |
| Amazon Pay | Schalter, Modus, Region, Merchant-/Store-/Key-Daten und Private Key |

PayPal und Amazon Pay werden im Checkout erst angeboten, wenn die benötigten
Zugangsdaten vorhanden sind. Neue Provider gehören in eine eigene
Adapterklasse; Secrets dürfen nicht in Templates, Logs oder Testausgaben
geschrieben werden.

## Rechtstexte, CMS und Widerruf

Der Shop stellt die benötigten CMS-Seiten unter einem Shop-Ordner sicher.
Rechtstexte und Widerrufsinhalt werden im CMS gepflegt, nicht dauerhaft als
HTML im Shop-Template dupliziert. Beim Checkout können Snapshots in der
Bestellung gespeichert werden, damit die zum Kaufzeitpunkt akzeptierte Fassung
nachvollziehbar bleibt.

Widerrufe werden über das Frontendformular in `shop_withdrawal` gespeichert
und im Admin-Modul bearbeitet. Kunden- und Admin-Mail können separat
aktiviert werden.

## Medien

Produktbilder referenzieren vorzugsweise zentrale CMS-Medien über `media_id`.
Ein `image_path` bleibt als Fallback für vorhandene oder erzeugte Dateien
möglich. Ein Bild kann einem Artikel oder einer Artikelgruppe zugeordnet sein.
Gruppenbilder dienen als Fallback und als visuelle Navigation im Katalog.

Der konfigurierbare CMS-Slot lautet standardmäßig `shop`. Medien dürfen
nicht allein durch Dateinamen als vertrauenswürdig behandelt werden;
Uploadziel, Dateiname und Dateityp werden serverseitig begrenzt.

## Channels und externe Plattformen

Standard-Channels sind:

```text
shop, amazon, ebay, kleinanzeigen, mobile
```

`dbxShopChannelConnector` kapselt Verbindungstest, Payload-Normalisierung und
Artikelexport. Plattformbezogene Implementierungen existieren für eBay,
Amazon, mobile.de und Kleinanzeigen; zusätzlich gibt es einen generischen
Middleware-Weg. Der interne Channel `shop` benötigt keinen externen Export.

Eine sichtbare Channel-Konfiguration bedeutet nicht automatisch, dass ein
Produktivvertrag oder alle API-Berechtigungen vorhanden sind. Vor Livebetrieb
müssen Zugangsdaten, API-Scopes, Marketplace-/Seller-Kontext, Policies,
Webhook-Authentifizierung und Fehlerwiederholung geprüft werden.

Der Webhook-Endpunkt ist:

```text
?dbx_modul=dbxShop&dbx_run1=channel_webhook&channel={channel_key}
```

Webhook-Secrets und Provider-Signaturen sind Sicherheitsgrenzen. Ein neuer
Importweg darf Bestellungen nicht ungeprüft aus beliebigem JSON erzeugen.

## Einstellungen

Die Admin-Seite `settings` verwaltet unter anderem:

- Aktivstatus, Standard-Channel und Währung,
- Brutto-/Nettoanzeige und drei Steuerklassen,
- B2B-, Lager- und Channel-Schalter,
- Gastbestellung und Rechtstext-Snapshots,
- Kunden-/Admin-Mail und Absender,
- Vorkasse, Rechnung, PayPal und Amazon Pay,
- digitale Lieferung und pauschalen Versand,
- den CMS-Medienslot.

Die Konfiguration wird mit `dbx()->get_cfg('dbxShop')` gelesen und über
die zentrale Konfigurationsschnittstelle gespeichert. Keine zweite JSON- oder
ENV-Konfiguration für dieselben Werte einführen.

## Reale Codewege

### Repository statt DB-Zugriff aus dem Template

```php
$repo = dbx()->get_include_obj('dbxShopRepository', 'dbxShop');
$product = $repo->productBySku($sku, true);

if (!is_array($product)) {
    return dbx()->get_system_obj('dbxTPL')->get_tpl(
        'dbx|alert-warning',
        array('msg' => 'Produkt nicht gefunden.')
    );
}

$images = $repo->imagesForProduct(
    (int)$product['id'],
    $repo->groupsForProduct((int)$product['id'])
);
```

Der zweite Parameter von `productBySku()` verlangt hier ein aktives Produkt.
Die Serviceklasse prüft zusätzlich Channel und Darstellungskontext. Ein
Template erhält nur die fertigen Werte und generiert keine Shopabfrage.

### Produkt an einen Channel exportieren

```php
$result = $repo->exportProductToChannel($productId, 'ebay');

if (empty($result['ok'])) {
    $message = (string)($result['message'] ?? 'Export fehlgeschlagen.');
} else {
    $message = (string)($result['message'] ?? 'Export ausgeführt.');
}
```

`exportProductToChannel()` lädt Channel, Produkt, Mapping und Connectorstatus
über das Repository. Ein Admincontroller soll nicht selbst ein Providerpayload
aus Request-Feldern zusammensetzen.

### Bestellreport

```php
$filters = array(
    'search' => $search,
    'status' => $status,
    'payment_status' => $paymentStatus,
    'channel_key' => $channel,
);

$count = $repo->orderCount($filters);
$orders = $repo->orders($filters, $rows, $offset, 'create_date', 'DESC');
```

Die Adminansicht übergibt diese Daten an dbxReport. Repository und Report haben
unterschiedliche Aufgaben: das Repository kennt die Shopabfrage, dbxReport
kennt Filterzustand, Pagination, Formatierung und Aktionen.

### Zahlungsadapter

```php
$paypal = dbx()->get_include_obj('dbxShopPayPal', 'dbxShop');

if (!$paypal->isConfigured()) {
    return array('ok' => 0, 'message' => $paypal->configHint());
}

$providerOrder = $paypal->createOrder($order, $returnUrl, $cancelUrl);
$approvalUrl = $paypal->approvalUrl($providerOrder);
```

Provideradapter kapseln Authentifizierung, Request/Response und Testmodus. Der
Service bleibt für lokalen Bestellzustand, Idempotenz, Rückkehr und Historie
zuständig.

## Erweiterungsszenarien

### Neues Produktattribut

Wenn Redakteure ein zusätzliches Merkmal wie Material oder Vertragslaufzeit
benötigen, wird meist keine neue Spalte in `shop_product` gebraucht. Eine
Attributdefinition kann einer Gruppe zugeordnet, als filterbar markiert und pro
Produkt befüllt werden.

### Neue Zahlungsart

1. Konfigurationsfelder und sichere Secret-Behandlung ergänzen.
2. eigenen Adapter mit `isConfigured`, Start/Erstellung, Rückkehr/Bestätigung
   und Verbindungstest implementieren.
3. Zahlungsart nur bei vollständiger Konfiguration im Checkout anbieten.
4. lokale Order vor bzw. eindeutig zum Providerrequest referenzieren.
5. wiederholte Rückkehr/Webhooks idempotent verarbeiten.
6. History und Fehlermeldung ohne Secrets schreiben.

### Neuer Verkaufskanal

1. Channeltyp und Konfiguration definieren.
2. `dbxShopChannelConnector` bzw. einen plattformspezifischen Adapter ergänzen.
3. Produktmapping und erforderliche Policies/IDs pflegbar machen.
4. Testverbindung ohne produktive Mutation bereitstellen.
5. Exportstatus, externe IDs und Providerantwort speichern.
6. Webhook authentifizieren und normalisieren.
7. Fehlerwiederholung und Teilfehler fachlich festlegen.

### Neue Darstellungsvariante

Eine neue Karten- oder Detailansicht entsteht als Shoptemplate und optionales
CSS innerhalb des Shopdesigns. Die Gruppe wählt den erlaubten Template-Namen.
Produktdaten, Preise und Rechte bleiben unverändert im Service/Repository.

## Betrieb und Fehlersuche

- Dashboardzahlen zuerst gegen Repositoryabfragen und aktive Filter prüfen.
- Fehlende Produkte: Aktivstatus, internen Channel, Gruppe und Rechte prüfen.
- Falscher Preis: Produktwert, Gruppen-/Channeloverride, Steueranzeige und
  Währung getrennt prüfen.
- Checkoutfehler: Formularvalidierung, Bestand, Zustimmung und Provider-
  Konfiguration prüfen.
- Doppelbestellung: Providerreferenz, Rückkehr-Idempotenz und Webhook-Historie
  prüfen.
- Fehlendes Bild: `shopProductImage`, `media_id`, aktive dbxMedia-Datei und
  Gruppenfallback prüfen.
- Exportfehler: Channeltest, Mapping, externe Policies/Scopes und gespeicherte
  Connectorantwort prüfen; niemals Secrets in die UI kopieren.

## Erweiterungsregeln

- Neue Tabellen zuerst als DD in `dbxShop/dd` modellieren.
- Jede Shop-Tabelle benötigt `id` als Primaerschluessel mit Autoincrement-
  Semantik der jeweiligen Datenbank.
- Datenzugriff gehört in `dbxShopRepository`, nicht in Templates.
- Frontendablauf gehört in `dbxShopService`.
- Adminablauf gehört in `dbxShopAdmin` und bleibt admin-geschützt.
- Neue Eingaben verwenden `dbxForm` und FD, neue Listen `dbxReport`.
- Preis-, Steuer-, Versand- und Bestellwerte werden serverseitig berechnet.
- IDs aus Request oder Session werden immer gegen Rechte und Datensatzbezug
  geprüft.
- Secrets werden nicht ausgegeben oder in Systemmeldungen protokolliert.
- Externe Calls erhalten Timeout, Fehlerbehandlung und nachvollziehbaren
  Status; sie dürfen lokale Bestellungen nicht inkonsistent hinterlassen.

## Prüfliste

- DD-Sync läuft auf einer leeren Entwicklungsdatenbank durch.
- Jede Tabelle besitzt `id` und eine funktionierende Autoincrement-ID.
- Demoartikel erscheinen im Katalog und lassen sich filtern.
- Warenkorb addiert, ändert und entfernt Positionen korrekt.
- Bestätigtes Einzel-Löschen und Leeren funktionieren per AJAX; der Menü-Badge
  zeigt danach unmittelbar die aktuelle Gesamtmenge.
- Checkout weist unvollständige Daten und fehlende Zustimmung ab.
- Offline-Zahlung erzeugt Bestellung und Positionen genau einmal.
- Provider-Rückkehr ist wiederholbar bzw. erzeugt keine Doppelbestellung.
- Eigene Bestellungen und Rechnungen sind zugreifbar, fremde nicht.
- Adminreports, Formulare und Massenaktionen funktionieren.
- Rechtstexte, Widerruf, Medien und Mail-Schalter sind geprüft.
- Channel-Tests und Exporte zeigen Fehler, ohne Secrets auszugeben.
- `dbxapp` und `flowers` stellen den Shop lesbar und responsive dar.

## Verwandte Dokumentation

- @ref dbxapp_security_integrity_performance
- @ref dbxapp_shop_ai_reference
- @ref dbxapp_dbxdb_dd_fd
- @ref dbxapp_dbxform
- @ref dbxapp_dbxreport
- @ref dbxapp_design_themes_skins
- @ref dbxapp_ai_rules
