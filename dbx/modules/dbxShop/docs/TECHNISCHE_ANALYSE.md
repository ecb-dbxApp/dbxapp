# dbxShop – technische Bestandsaufnahme

Stand: 2026-07-14

Diese modulnahe Datei beschreibt den implementierten Ist-Zustand von
`dbxShop` und `dbxShop_admin`. Sie ersetzt die fruehere Skeleton-Analyse vom
08.07.2026. Die ausfuehrlichen, projektweiten Dokumente sind:

- [Shop-Leitfaden](../../../../17_Shop_Leitfaden.md)
- [Shop-KI-Referenz](../../../../18_Shop_KI_Referenz.md)
- [Design-Leitfaden](../../../../15_Design_Themes_Skins.md)
- [Design-KI-Referenz](../../../../16_Design_KI_Referenz.md)

## Reifegrad

Der Shop ist kein leerer Modulrahmen mehr. Implementiert sind:

- Katalog, Volltextsuche, Gruppen- und Attributfilter,
- Produktkarten, Detailvarianten und Galerien,
- Session-Warenkorb und Checkout,
- Vorkasse, Rechnung, PayPal und Amazon Pay,
- Bestellungen, Positionen, History, Lagerreservierung und Rechnungs-PDF,
- Rechtstext- und Widerrufsseiten aus dem CMS,
- Widerrufsformular und Adminbearbeitung,
- Artikel-, Gruppen-, Attribut-, Versand- und Medienverwaltung,
- Bestellreport und Bestelldetail,
- Channel-, Channel-Gruppen- und Artikelmapping,
- Verbindungstest, Produktexport und Webhook-/Order-Import-Grundlage fuer
  externe Kanaele,
- DD-Synchronisation und Entwicklungs-Testdaten.

Die Konfigurationsversion lautet weiterhin `0.1.0`. Diese Versionsnummer ist
nicht mit dem funktionalen Umfang oder der DD-Schema-Version zu verwechseln.

## Einstiegspunkte

### Frontend

`dbxShop.class.php` ist ein kleiner Router. Er liest `dbx_run1` und delegiert
an `dbxShopService`.

```text
catalog/start          -> catalog()
product/detail         -> product()
cart                   -> cart()
checkout               -> checkout()
paypal_*               -> PayPal-Ablauf
amazon_pay_*           -> Amazon-Pay-Ablauf
order/orders           -> orders()
invoice_pdf            -> invoicePdf()
channel_webhook        -> channelWebhook()
legal/terms            -> legal()
return/returns/...     -> withdrawal()
```

### Administration

`dbxShop_admin.class.php` laedt `dbxShopAdmin`. Die Modulkonfiguration erlaubt
Zugriff nur fuer `admin`. `dbxShopAdmin::run()` routet unter anderem zu
Dashboard, Produkten, Gruppen, Attributen, Versandgruppen, Channels, Medien,
Bestellungen, Rechtstexten, Widerrufen und Einstellungen.

## Klassen und Verantwortungen

| Klasse | Verantwortung |
| --- | --- |
| `dbxShop` | Frontend-Routing, keine Fachdatenlogik |
| `dbxShopService` | Frontend-Use-Cases, Formular-/Templateaufbau, Checkout und Provider-Rueckkehr |
| `dbxShopRepository` | DD-Sync, Datenzugriff, Zuordnungen, Orders, Bestand, History, PDF und Widerruf |
| `dbxShopPayPal` | PayPal-Kommunikation |
| `dbxShopAmazonPay` | Amazon-Pay-Kommunikation |
| `dbxShopChannelConnector` | Plattformtests, Export und Webhook-Payload-Normalisierung |
| `dbxShop_admin` | Admin-Wrapper und Fehlerfallback |
| `dbxShopAdmin` | Admin-Use-Cases mit `dbxForm`, `dbxReport`, CMS-Hilfe und OpenWin |

Der Repository-Name ist historisch breit gefasst: Neben reinen CRUD-Methoden
enthaelt es auch Shop-Fachoperationen wie Bestellerzeugung, Bestandsfreigabe,
History und Rechnungs-PDF. Neue Logik soll diese bestehende Grenze beachten
und nicht zusaetzlich in Router oder Templates verteilt werden.

## DD-Schema

Die Daten liegen gemaess DD auf `dbxShop|dbxShop.db3`. Implementiert sind 17
DDs:

```text
shopProduct
shopProductGroup
shopProductGroupMap
shopProductImage
shopAttributeDefinition
shopProductAttributeValue
shopShippingGroup
shopProductShippingGroupMap
shopChannel
shopProductChannel
shopChannelGroup
shopChannelGroupChannel
shopProductChannelGroupMap
shopOrder
shopOrderItem
shopOrderHistory
shopWithdrawal
```

Alle Tabellen verwenden `id` als Primaerschluessel. Die ID muss durch den
jeweiligen Datenbanktreiber als Autoincrement-/Identity-Wert erzeugt werden.
Beziehungen speichern diese IDs, waehrend fachliche stabile Schluessel wie
`sku`, `group_key`, `channel_key` und `order_no` eigene Aufgaben haben.

`dbxShopRepository::install()` synchronisiert das Schema anhand einer
Versionskennung. Aktueller Wert:

```text
shop-dd-20260713-2
```

Die Kennung steht sowohl im Repository als auch in `cfg/config.php`. Bei einer
DD-Aenderung, die erneut automatisch angewendet werden soll, muessen beide
Stellen konsistent aktualisiert werden.

## Initialisierung und Testdaten

Beim Einstieg ruft der Service `ensureSeed()` auf. Dieses Verfahren:

1. synchronisiert das Shop-DD-Schema,
2. stellt die Standard-Channels sicher,
3. repariert Primaergruppen-/Bildgrundlagen,
4. legt Testdaten an, wenn wesentliche Grunddaten fehlen.

Testdaten sind in diesem Entwicklungsprojekt ausdruecklich erlaubt. Ein leeres
Shop-System bleibt deshalb aktuell nicht dauerhaft leer, wenn der Katalog oder
die Administration aufgerufen wird. Das ist beabsichtigt und muss bei Tests
beruecksichtigt werden.

## Artikelauflösung

Ein Artikel kann Informationen aus mehreren Ebenen beziehen:

```text
shopProduct
  -> Primaer-/weitere Artikelgruppen
     -> Darstellungs-, Steuer- und Gallery-Defaults
  -> Attribute und Attributwerte
  -> Artikel-/Gruppenbilder
  -> Versandgruppen
  -> direkte Channels
  -> Channel-Gruppen -> geerbte Channels
```

Channel-spezifische Werte in `shopProductChannel` koennen SKU, Preis, Versand,
Listing-IDs und Exportstatus ueberschreiben. Negative Preis-/Versandwerte
dienen als Kennzeichen fuer Vererbung.

## Checkout und Order-Lebenszyklus

Der Warenkorb liegt in `$_SESSION['dbxShop_cart']`. Beim Checkout werden
Produkte und Mengen erneut aus dem Repository aufgeloest. Der Browser ist
nicht die Quelle fuer Preis, Steuer, Versand oder Bestand.

Bei erfolgreicher Validierung erzeugt das Repository:

- einen Order-Header,
- Order-Item-Snapshots,
- einen initialen History-Eintrag,
- gegebenenfalls eine Bestandsreservierung,
- gespeicherte Rechtstext-/Widerrufssnapshots.

Offline-Zahlungen fuehren direkt zur Bestaetigung. Online-Zahlungen speichern
eine Providerreferenz und werden nach der Provider-Rueckkehr aktualisiert. Die
Rueckkehr darf nicht als zweite Bestellung implementiert werden.

Adminaktionen koennen Bestell-, Zahlungs- und Versandstatus, Tracking,
Rechnungsdaten und interne Notizen pflegen. Aenderungen werden soweit im
Repository vorgesehen in `shop_order_history` festgehalten.

## Recht und CMS

Shop-Rechtstexte und Widerruf werden als CMS-Seiten unter einem Shop-Ordner
erzeugt bzw. gefunden. Dadurch bleiben redaktionelle Inhalte im Content-CMS.
Die Bestellung kann den zum Kaufzeitpunkt gueltigen Inhalt als Snapshot
speichern.

Die Admin-Hilfe verwendet ebenfalls CMS-Seiten und einen admin-lesbaren
Shop-Ordner. Diese Hilfeinhalte sind nicht mit den oeffentlichen Rechtstexten
zu verwechseln.

## Medien

`shop_product_image` kann entweder `media_id` oder `image_path` verwenden.
Bevorzugt wird die Referenz auf ein zentrales CMS-Medium. Gruppenbilder dienen
als Katalognavigation und Fallback fuer Produkte. Die aktuelle
Initialisierungslogik reduziert reine Gruppenbilder pro Gruppe auf ein aktives
Primaerbild.

## Channels

Die Initialisierung stellt diese Kanaele bereit:

| Key | Modus | Zweck |
| --- | --- | --- |
| `shop` | intern | eigener Katalog und Checkout |
| `amazon` | API | Listings/Orders/Notifications vorbereitet |
| `ebay` | API | Sell APIs und Notifications |
| `kleinanzeigen` | manuell/middleware | nur freigegebene Schnittstellen verwenden |
| `mobile` | API | Seller-/Lead-Kontext |

Der Connector enthaelt plattformspezifische Test-, Export- und
Normalisierungswege. Die Anwesenheit des Codes bedeutet nicht, dass Zugang,
Vertrag, Scope und jede Plattformfunktion fuer Produktion vollstaendig
freigeschaltet sind.

Der oeffentliche `channel_webhook` ist sicherheitskritisch. Vor Import sind
Channel-Aktivstatus, Import-Schalter, Secret/Signatur, externe Referenz und
Dubletten zu pruefen. Fehlermeldungen duerfen keine Zugangsdaten enthalten.

## Einstellungen

`dbxShop_admin/fd/shop-settings.fd.php` beschreibt die Adminmaske. Sie umfasst:

- Grundbetrieb, Waehrung, Preis-/Steueranzeige,
- Steuerklassen,
- B2B, Bestand und Channels,
- Gastcheckout, Rechtssnapshot und Widerruf,
- Kunden-/Admin-Mail,
- Vorkasse, Rechnung, PayPal und Amazon Pay,
- digitale Lieferung und Pauschalversand,
- CMS-Medienslot.

Gespeichert wird ueber die zentrale dbXapp-Konfiguration. Die Datei
`dbxShop/cfg/payment.php` ist lediglich eine vorhandene PayPal-
Fallbackkonfiguration und kein zweiter vollwertiger Settings-Speicher.

## Darstellung und Designs

Shop-Komponenten werden durch `dbxShop/design/css/shop.css` gestaltet. Die
Seitenschale kommt aus dem globalen Design:

- `dbxapp`: technisches Standarddesign,
- `flowers`: vertikales, organisches Frontenddesign mit Light/Dark.

Die Shop-Administration nutzt `shop-admin.css` innerhalb des konfigurierten
Admin-Designs. Globales Design-CSS soll keine Shop-Fachstruktur duplizieren.

## Bekannte Grenzen und Vorsichtspunkte

- Der Repository-Code ist sehr umfangreich; Aenderungen muessen gezielt und
  mit Regressionstests erfolgen.
- Externe Plattformen benoetigen reale Providerpruefung vor Livebetrieb.
- Amazon Pay wird nur angeboten, wenn alle Schluesselwerte gesetzt sind; auch
  dann muss der echte Sandbox-/Liveablauf geprueft werden.
- Automatische Testdaten koennen einen Test auf „vollstaendig leer“ sofort
  wieder befuellen.
- Eine eigene `_adminWin`-Designpage oder iframe-Isolation ist nicht Teil des
  aktuellen Shopcodes.

## Technische Mindestpruefung

1. PHP-Syntax aller geaenderten Klassen und DD/FD-Dateien pruefen.
2. DD-Sync mit leerer und bestehender `dbxShop`-Datenbank pruefen.
3. `id`/Autoincrement fuer jede betroffene Tabelle pruefen.
4. Katalog, Detail, Warenkorb und Checkout testen.
5. Bestellzugriff mit Gast, Benutzer und fremdem Benutzer testen.
6. Adminzugriff ohne und mit Adminrecht testen.
7. Zahlungs-Erfolg, Abbruch, Fehler und wiederholte Rueckkehr testen.
8. Medien-, Rechtstext- und Widerrufsablauf testen.
9. Channel-Fehler ohne Secret-Ausgabe testen.
10. Ausgabe in `dbxapp`, `flowers` Light und `flowers` Dark testen.
