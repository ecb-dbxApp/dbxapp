# Sicherheit, Integrität und Performance {#dbxapp_security_integrity_performance}

Stand: 25. Juli 2026

Dieses Kapitel dokumentiert die Härtung der öffentlichen Laufzeit, von
`dbxWorkflow`, `dbxShop` und `dbxContent_admin`. Es ergänzt die verbindlichen
Architekturregeln und die Fachleitfäden. Bestehende GET-Navigation bleibt
erhalten. Nur GETs, die Zustand verändern, benötigen einen Nachweis der
konkreten Browseraktion.

## Architekturentscheidung

Die Änderungen bleiben in der vorhandenen dbxapp-Architektur:

- Datenzugriff erfolgt auch in Wartungs- und Migrationswerkzeugen ausschließlich
  über `dbxDB` und DD.
- Eingaben und Listen verwenden `dbxForm` bzw. `dbxReport`.
- Fachlogik bleibt in Workflow- und Shop-Service/Repository.
- Vollständige Ausgabeflächen liegen in `dbxTPL`; Templates bleiben von
  Datenzugriff und Fachmutation getrennt.
- Es gibt keine zweite Token-Klasse und keinen parallelen Security-Stack.

Kerneländerungen wurden nur für systemweite Verträge vorgenommen:

| Kernelbereich | Notwendigkeit |
| --- | --- |
| `dbxApi` | HMAC-Action-Tokens, automatische Action-URLs, sichere Zufallswerte |
| `dbxWebApp` | automatische Action-Policies, Prüfung vor Modulstart, kein API-Autologin |
| `dbxReport` | automatische Tokenisierung der Standard- und Grid-Mutationen |
| `dbxForm` | eigener rotierender POST-CSRF-Schutz; Action-URL nur bei automatisch erkannter RID-Mutation |
| `dbxSession` | zentrale Gast-Session-Schreiblast vermeiden und Action-Secret an Login/Logout verwerfen |
| `dbxRuntime` | gemeinsame Sicherheitsheader |

Für mutierende Link-Aktionen wird die vorhandene Kernel-Infrastruktur
`action_token()` / `check_action_token()` verwendet. `dbx_token` ist nur deren
Transportparameter; es gibt weiterhin keine zweite Token-Klasse und keinen
parallelen Security-Stack.

Seit der zentralen Action-Policy werden konkrete Tokens stateless aus genau
einem Session-Secret per HMAC abgeleitet. Auch RID-spezifische Scopes erzeugen
dadurch keine wachsende Token-Liste in der PHP-Session. Bereits vor der
Umstellung gerenderte Scope-Tokens werden für die Dauer ihrer bestehenden
Session weiterhin akzeptiert.

Beim Login und Logout verwirft `dbxSession` sowohl das HMAC-Secret als auch
eventuelle Legacy-Tokens. Ein vor dem Benutzerwechsel gerenderter Link ist
danach ungültig. `delete` und `save` werden zusammen mit `rid` direkt aus den
dbx-Aktionsparametern erkannt; dafür ist kein Konfigurationszugriff nötig.
Ungewöhnliche ältere Aktionsnamen können zur Kompatibilität weiterhin eine
explizite Policy verwenden.

Eine Tokenprüfung erzeugt selbst keinen Sessionzustand. Ist noch kein
Action-Secret vorhanden, wird auch ein formal korrekt aussehender fremder Token
direkt verworfen. Erst das Rendern eines echten Aktionslinks erzeugt bei Bedarf
das eine Session-Secret. Weder Action- noch Formular-Tokens oder Teile davon
werden in Debugmeldungen geschrieben.

## Warum Rechte allein bei mutierenden GETs nicht genügen

Modul- und DD-Rechte beantworten, **wer** eine Operation ausführen darf. Sie
belegen nicht, dass dieser Benutzer genau diese Mutation auslösen wollte.
Ohne Action-Token kann eine fremde Website einen eingeloggten Browser zum
Aufruf eines Start-, Pause-, Resume-, Cancel- oder Finish-GETs veranlassen
(Cross-Site Request Forgery).

Das gilt gerade dann, wenn der Benutzer berechtigt ist: Der Browser sendet
seine Sitzung mit, anschließend lassen Modul- und DD-Rechte die Mutation
korrekt zu. OWASP weist ausdrücklich darauf hin, dass `SameSite=Lax` bei einer
zustandsändernden Top-Level-GET-Navigation nicht genügt:
[OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html).

Der verbindliche Vertrag lautet deshalb:

- reine Navigation, Anzeige, Filter und Detail-GETs bleiben tokenlos;
- Standardaktionen von `dbxReport` (`row_delete`, `multi_delete`,
  `delete_tab`, Aktivieren/Deaktivieren) werden automatisch tokenisiert;
- schreibende Grid-Endpunkte mit der dbxReport-Konvention
  `*_grid_<save|insert|delete|sort|sync>`, `data_<...>` oder `fields_<...>`
  werden aus der eigentlichen Route erkannt und automatisch tokenisiert;
- `delete` und `save` werden in den dbx-Aktionsparametern zusammen mit `rid`
  automatisch erkannt und an diese RID gebunden;
- `dbxWebApp` prüft die erkannte Policy vor dem Start des Modulcodes;
- alte mutierende Direktlinks bleiben als Route erreichbar, mutieren ohne
  gültigen Token aber nicht;
- normale POST-Formulare verwenden nur den automatisch verwalteten
  `dbxForm`-Token und erhalten keinen zusätzlichen `dbx_token`;
- ein Report-Link im Formular kann beide Schutzschichten enthalten;
- das Token ersetzt niemals Modul-, Owner- oder DD-Rechte.

Die URL wird ohne Policy-Konfiguration, Scope-Bau oder manuelle Tokenprüfung
erzeugt:

```php
$url = dbx()->action_url(
    '?dbx_modul=myInvoices&dbx_run1=delete&rid=' . $rid
);
```

`action_url()` verändert die URL nur, wenn `dbxWebApp` eine passende Policy
findet. Das Token bindet Modul, Run-Kontext, Aktionsname und automatisch die
RID. Transportwerte wie `dbx_ajax`, `dbx_window` und `dbx_token` selbst sind
nicht Teil des Scopes. Derselbe Aktionslink funktioniert deshalb als normaler
GET und als Ajax-POST eines Reports. `dbx_token` wird außerdem aus Self-,
Filter- und Pagination-URLs entfernt.

Beim Grid ist der Schutz nicht von einem optionalen Marker abhängig.
`dbxWebApp` erkennt die eigentliche Save-/Insert-/Delete-/Sort-/Sync-Route.
Darum wird auch eine direkt konstruierte Anfrage ohne `dbx_token` abgewiesen.
Eine konfigurierte schreibende Grid-URL, die keiner unterstützten Konvention
folgt, wird von `dbxReport` nicht unsigniert ausgegeben. Lesende Grid-URLs
bleiben unverändert.

Bewusste fachliche Sonder-Scopes bestehen nur für Abläufe, die keine
dbxReport- oder Delete-/Save-RID-Standardaktion sind: CMS/SEO-Medienaktionen,
Workflow-Start und Instanzkommandos, Shop-Sammel- und Statusaktionen,
dbxKi-Plan/Execute, erneuter Registrierungsversand sowie die zusätzlichen
Benutzerverwaltungsaktionen Verify, Lock, Unlock und Passwort-Reset. Die
verbindliche Allowlist und ihre Begründungen werden durch
`dbxActionTokenUsageAudit_test.php` geprüft. Neue manuelle Tokenlogik in einem
anderen Modul lässt diesen Test fehlschlagen.

Fehlende, falsche oder für eine andere RID erzeugte Tokens liefern HTTP 403,
erzeugen eine Security-Systemmeldung und erreichen den Modulcode nicht.

Der Nachweis für die Notwendigkeit ist unabhängig von den Modulrechten:
Browser senden Session-Cookies bei Same-Site-Navigation automatisch; Rechte
identifizieren damit nur den eingeloggten Benutzer. Erst das
sessiongebundene, nicht von einer fremden Seite bekannte Action-Token belegt
die konkrete Aktion. Aus diesem Grund ist `dbx_token` für schreibende GETs
notwendig, für normale GET-Navigation aber nicht.

Laufzeitnachweis vom 24. Juli 2026: Ein direkter Workflow-Start ohne Token
lieferte die Übersicht mit tokenisiertem Startlink. Die Zahl der
`workflow_instance`-Datensätze blieb dabei bei 30.

## Konfiguration und Secrets

`dbx/modules/dbx/cfg/config.php` enthält keine produktiven Zugangsdaten mehr.
Lokale Werte liegen in:

```text
dbx/modules/dbx/cfg/config.local.php
```

Die Datei ist über `**/cfg/config.local.php` ignoriert. Eine leere,
versionierbare Vorlage liegt unter:

```text
dbx/modules/dbx/cfg/config.local.example.php
```

`dbxApi::get_config()` lädt zuerst die Basis und überlagert anschließend die
lokale Datei rekursiv. `set_config()` entfernt lokale Override-Pfade vor dem
Schreiben der Basisdatei. Dadurch kann ein Admin-Speichervorgang lokale Secrets
nicht versehentlich zurück in die versionierte Konfiguration kopieren.

Wichtig: Das Entfernen aus dem aktuellen Quellstand löscht keine Werte aus
bereits vorhandener Git-Historie, Backups oder Logs. Alle ehemals im Repository
gespeicherten Zugangsdaten müssen nach der Umstellung rotiert werden.

Die frühere Sonderbehandlung `dbx_api -> login(2)` wurde entfernt. Ein
Requestparameter darf keine Identität und keine Berechtigung erzeugen.

## Workflow-Vertrag

### Zugriff

- Authentifizierte Benutzer laden Instanzen mit DD-Prüfung; Owner und Admin
  bleiben maßgeblich.
- Gastinstanzen sind zusätzlich an die aktive PHP-Session gebunden.
- `owner = 0` allein ist keine öffentliche Freigabe.
- Pro Session werden höchstens 100 Gast-Instanz-IDs gehalten.

### Atomarität und Wiederholung

- Schritt und Instanzfortschritt werden in einer Transaktion gespeichert.
- Identische wiederholte Schritt-POSTs erzeugen keinen doppelten
  `workflow_step`.
- Automationsergebnisse werden zuerst gesammelt und anschließend gemeinsam
  mit dem Instanzzustand gespeichert.
- Finish beansprucht den Zustand atomar als `finishing`.
- Nur der Request mit genau einem erfolgreichen Statuswechsel darf die
  Fachoperation oder den externen Abschluss ausführen.
- Ein fachlicher Fehler setzt einen nachvollziehbaren Status; Erfolg setzt
  erst danach `finished`.

Die neue DD-Option `finishing` ist Teil des Statusvertrags und wird in Admin-
und Benutzeroberfläche angezeigt.

## Shop-Vertrag

### Lesen und Wartung

Öffentliche Katalog- und normale Admin-GETs führen weder Schemaänderungen noch
Defaultpflege oder Datenbereinigungen aus. `dbxShopRepository::install()` ist
ohne Wartungsparameter garantiert schreibfrei. DD-Synchronisation,
Channel-Defaults, Primärgruppen und Gruppenbilder laufen ausschließlich über
den expliziten, berechtigten und tokenisierten Adminpunkt
**Installation / Wartung**. Demodaten werden nur von diesem Wartungslauf
angelegt. Dasselbe gilt für die Provisionierung/Aktualisierung der
Shop-Hilfeseiten, das Anlegen des CMS-Shop-Medienordners und den vollständigen
Neuaufbau der Shop-Medienverwendungen. Normale Produkt-, Formular- und
Medienansichten lesen diese Daten nur; konkrete Bildzuordnungen aktualisieren
ihre Medienverwendung direkt nach der erfolgreichen Mutation.

Der Katalog lädt Filterkandidaten gebündelt. Gruppen, Attribute und der
angeforderte Channel werden für alle Kandidaten in wenigen Abfragen geladen.
Nur die sichtbare Reportseite erhält anschließend die vollständige Dekoration
mit Bildern, Versand- und Channelgruppen. Direkte inaktive Channel-Zuordnungen
unterdrücken weiterhin eine geerbte aktive Freigabe.

Vollständige Produktlisten verwenden ebenfalls eine einheitliche gebündelte
Datensicht im Shop-Repository. Produktgruppen, Versandgruppen, Channelgruppen,
Channels, Bilder, Attributdefinitionen und Werte werden je Operation höchstens
einmal über `dbxDB` geladen und danach per ID zugeordnet. Die Datensicht endet
mit dem Methodenaufruf; sie kann nach einem späteren Schreibzugriff nicht
veraltet weiterleben. `products()` und `productsByIds()` nutzen denselben Pfad,
die bisherigen Einzelmethoden bleiben kompatibel.

Ein globaler Ergebnis-Cache in `dbxDB` wurde bewusst nicht ergänzt. dbxDB
besitzt neben DD-Selects auch rohe Queries, Schreibpfade und Transaktionen;
außerdem können getrennte Prozesse schreiben. Eine lückenlose, rechte- und
transaktionssichere Invalidierung wäre unverhältnismäßig komplex.

Stattdessen merkt das Shop-Repository innerhalb genau eines Requests kleine,
häufig wiederverwendete Referenzlisten: Gruppen, Channels,
Attributdefinitionen und Filterdefinitionen. Jede zugehörige
Repository-Mutation leert diesen Cache sofort. Er enthält keine Benutzer-,
Token- oder Warenkorbdaten und endet mit dem Request. DD-, Rechte- und
Datenzugriff bleiben zentral in `dbxDB`; nur Wiederverwendung und fachliche
Zuordnung liegen im Repository.

Messung am 24. Juli 2026 mit jeweils fünf ungecachten HTTPS-Katalogaufrufen und
30 vorhandenen Produkten:

| Messpunkt | Vorher | Nachher | Änderung |
| --- | ---: | ---: | ---: |
| dbxShop | 179,0 ms | 77,8 ms | -56,5 % |
| gesamte DB-Zeit | 73,8 ms | 23,0 ms | -68,8 % |
| serverseitige Gesamtzeit | 223,8 ms | 121,6 ms | -45,7 % |

Alle zehn Vergleichsaufrufe lieferten HTTP 200. Zusätzlich wurden die
vollständig dekorierten Ergebnisse aller 30 Produkte gegen den bisherigen
Einzelpfad verglichen; Feldwerte, Typen und Reihenfolgen waren identisch.

Die zweite Stufe vermeidet unsichtbare Renderarbeit: Karten- und
Detailtemplates melden über ihre lokalen `{replacement_namen}`, welche Werte
sie tatsächlich benötigen. Der Service erzeugt Galerie, Attributtabelle,
Versand-/Lagerblöcke und Kauf-`dbxForm` nur bei einem entsprechenden
Platzhalter. Eigene Templates behalten damit den vollständigen Vertrag. Der
requestlokale Cache enthält ausschließlich Template-Feldnamen und keine
Produkt-, Benutzer- oder Tokendaten.

Kontrollierter A/B-Lauf mit je 15 ungecachten HTTPS-Katalogaufrufen:

| Messpunkt | vollständige Erzeugung | selektive Erzeugung | Änderung |
| --- | ---: | ---: | ---: |
| Median serverseitig gesamt | 203 ms | 164 ms | -19,2 % |
| Median dbxShop | 127 ms | 99 ms | -22,0 % |
| isolierte Kartenerzeugung, 9 Produkte × 20 | 579,5 ms | 94,0 ms | -83,8 % |

Alle 30 A/B-Aufrufe lieferten HTTP 200. Die ausgegebene Referenzkarte behielt
exakt 1.127 Bytes und denselben SHA-256-Hash; die Referenzdetailansicht war
ebenfalls bytegenau identisch. Die HTTP-Medianwerte sind aussagekräftiger als
der Mittelwert, weil die lokale Apache-/SQLite-Messung einzelne DB-Ausreißer
enthielt.

Die Admin-Bildliste beseitigt denselben N+1-Typ für Bezeichnungen. Bei 52
Bilddatensätzen sank ein Aufruf von `allImages()` im Mittel von 21,1 ms auf
2,9 ms (-86,3 %). Zehn Vorher-/Nachher-Aufrufe lieferten denselben
serialisierten SHA-256-Ergebnishash. Produkt- und Gruppentitel werden nun in
höchstens zwei DD-Mengenabfragen geladen.

## Schema-, DB- und Ausgabegrenzen

DD-Dateien sind die einzige Quelle für Tabellen, Spalten und Indizes.
Fachrequests dürfen weder `PRAGMA`, `ALTER TABLE`, `CREATE TABLE` noch
`CREATE INDEX` ausführen. Die ehemaligen CMS- und Sprach-Schemahelfer bleiben
als schreibfreie Kompatibilitätsmethoden erhalten; die tatsächliche
Synchronisation erfolgt über `dbxDD` im administrativen DD-Ablauf.

Auch einmalige Werkzeuge halten diese Grenze ein:

- `migrate_flat_permalinks.php` arbeitet über Content-DD und `dbxDB`;
- `rebuild_tutorial_callouts.php` verwendet Content-, Media- und
  Media-Usage-DDs sowie getrennte dbxDB-Transaktionen;
- die beiden MySQL-Prüfwerkzeuge verwenden den konfigurierten dbxDB-Server.

Direkte PDO- oder mysqli-Nutzung ist ausschließlich innerhalb der zentralen
Klasse `dbxDB` erlaubt. Ein automatisierter Boundary-Test durchsucht alle
Projekt-PHP-Dateien und verhindert einen Rückfall.

Vollständige Shop-Rechnung, Payment-Test und Workflow-Designer liegen in
Modultemplates und werden über `dbxTPL` befüllt. Dynamische wiederholte
Workflow-Feldkomponenten dürfen serverseitig erzeugt werden; Datenzugriff und
Fachmutation bleiben dabei außerhalb des Templates. Inline-Eventattribute
wurden entfernt. Delegierte Handler verwenden die gemeinsamen Bibliotheken
`ajax.js`, `confirm.js`, `openWin` und `core.js`; der Druckablauf verwendet
die vorhandene Print-Library.

### Bestellung und Bestand

Bestellung, Positionen, Bestandsreservierung und Historie bilden eine
Transaktion. Physischer Bestand wird mit einer bedingten SQL-Aktualisierung
reserviert:

```text
stock = stock - menge
WHERE stock >= menge
```

Genau eine geänderte Zeile ist erforderlich. Damit kann paralleler Checkout
keinen negativen Bestand erzeugen. Eine Rückbuchung wird ebenfalls atomar über
`stock_reserved` und `stock_released` beansprucht und ist wiederholbar.

Channel-Importe prüfen die externe Referenz vor und innerhalb der
Schreibtransaktion. Bei SQLite serialisiert `BEGIN IMMEDIATE` parallele
Schreiber.

### Checkout-Idempotenz

Jedes Checkout-Formular enthält eine zufällige `checkout_request_id`. Die
Session ordnet diese ID einer bereits erzeugten Bestellung zu. Wiederholte
POSTs derselben Browser-Session setzen denselben Ablauf fort, statt eine zweite
Bestellung anzulegen. Die Session hält höchstens 25 Zuordnungen.

Provider-Create/Capture/Complete erhalten zusätzlich deterministische
Idempotency-Keys.

### Provider-Rückkehr

Eine Browser-Query ist kein Zahlungsnachweis. Die Zuordnung erfolgt
ausschließlich über die serverseitig gespeicherte Kombination aus:

```text
payment_provider + payment_reference
```

`order_no` ist nur ein zusätzlicher Konsistenzcheck. Danach gelten:

- atomarer Claim `open|created|failed -> processing`;
- nur der Claim-Gewinner führt Capture/Complete aus;
- ein abgebrochener `processing`-Claim kann erst nach der konfigurierten Lease
  `payment_processing_retry_seconds` (Standard 300 Sekunden) erneut übernommen
  werden; der Provider erhält dabei weiterhin denselben deterministischen
  Idempotency-Key;
- PayPal: Provider-ID, Rootstatus, Capturestatus, Bestellreferenz, Betrag und
  Währung müssen passen;
- Amazon Pay: Session-ID, Merchant-Referenz, Betrag, Währung und dokumentierter
  Providerstatus müssen passen;
- terminal bezahlte Zustände werden nicht durch spätere schwächere Rückläufe
  herabgestuft;
- wiederholte erfolgreiche Returns lösen weder Providercall noch Mail erneut
  aus;
- Browser-Cancel-GETs sind rein informativ und verändern keinen
  Zahlungs-/Bestandsstatus.

Live-Calls gegen PayPal oder Amazon Pay waren nicht Teil der lokalen Prüfung.
Vor Produktivbetrieb sind Sandbox-End-to-End-Tests mit den echten
Providerkonten Pflicht.

### Öffentlicher Channel-Webhook

Der Channel-Webhook ist als Provider-Endpunkt öffentlich erreichbar. Deshalb
kann ihn eine Modulgruppe nicht wie eine Adminseite schützen. Der Import ist
fail-closed:

- `order_import_enabled` und aktiver Channel sind erforderlich;
- ein nicht leeres `webhook_secret` ist erforderlich;
- das Secret wird aus `X-DBX-Shop-Secret`, `X-Channel-Secret` oder dem
  Request-Body gelesen;
- GET-Query-Secrets werden nicht akzeptiert, weil sie in Logs,
  Browser-Historien und Referrer-Headern erscheinen können;
- fehlende Konfiguration liefert HTTP 503, falsche Authentifizierung HTTP 403.

Vor der Prüfung einer externen Channel-Referenz sperrt der Import zusätzlich
die zugehörige `shop_channel`-Zeile innerhalb derselben Transaktion. Damit
werden parallele Imports derselben Referenz auch zwischen getrennten
Anwendungsprozessen serialisiert; der Unique-/Duplikatcheck bleibt danach die
fachliche Schranke.

Laufzeitnachweis: Ein nicht authentifizierter Probeimport lieferte HTTP 503;
die Zahl der `shop_order`-Datensätze blieb bei 14.

## Gast-Sessions, Cache und Header

`session_db_guest=0` ist der neue Standard. PHP-Session, Sprache, Warenkorb und
Gast-Workflow funktionieren unverändert; frische Gäste erzeugen jedoch keinen
zentralen `dbx_session`-Datensatz pro Request. Authentifizierte Benutzer werden
weiter gespeichert. `session_db_guest=1` stellt das frühere Betriebsverhalten
wieder her.

### Flüchtige PHP-Sessions ohne Cookie

Die zentrale `dbx_session`-Tabelle und die PHP-Sessiondatei sind getrennte
Speicher. `session_db_guest=0` verhindert nur anonyme Datenbankzeilen. Ein
unbedingtes `session_start()` kann trotzdem für jeden Request ohne
`PHPSESSID` eine neue Datei im konfigurierten `session.save_path` anlegen.

Eine IP-Adresse ersetzt die Session-ID ausdrücklich nicht:

- viele Benutzer können über dieselbe NAT-/Proxy-IP kommen;
- Mobilfunk-, IPv6-Privacy- und Providerwechsel ändern die IP;
- Warenkorb, Anmeldung und CSRF-Zustand dürften nie zwischen IP-Nutzern geteilt
  werden;
- eine IP ist kein hinreichend geheimes Authentisierungsmerkmal.

Normale Gastsessions bleiben deshalb an die zufällige PHP-SID im sicheren
Cookie gebunden. Flüchtig und am Requestende mit `session_destroy()` entfernt
werden ausschließlich:

- UID 0, `GET` oder `HEAD`, kein eingehender PHP-Session-Cookie und durch
  `dbxBrowser` erkannter Robot; oder
- UID 0, `GET` oder `HEAD`, kein eingehender PHP-Session-Cookie und ein bereits
  als unpersonalisiert/tokenfrei validierter Full-Page-Cache-Hit.

POSTs, authentifizierte Benutzer, gültige Cookie-Sessions und normale
nicht-cachebare Browseraufrufe bleiben unverändert persistent. Ein Cookie gilt
nur dann als vorhandene Session, wenn seine SID mit der von PHP unter
`session.use_strict_mode` akzeptierten aktiven SID übereinstimmt. Zufällige
oder veraltete Cookiewerte umgehen die Robot-/Cache-Hit-Behandlung daher nicht.
Beim Verwerfen wird auch der von `session_start()` vorgemerkte
`Set-Cookie`-Header entfernt. Der Abschluss liegt zentral in `dbxSession`;
`dbxApi` ruft ihn sowohl beim normalen Requestende als auch vor dem frühen
Full-Page-Cache-Exit auf.

Laufzeitnachweis vom 24. Juli 2026:

```text
Robot ohne Cookie:          HTTP 200, kein Set-Cookie, Sessiondatei-Delta 0
Gast auf Page-Cache-Hit:    HTTP 200, public/ETag, Sessiondatei-Delta 0
Normaler Gast, Cache-Miss:  Set-Cookie, Sessiondatei-Delta 1
Folgerequest mit Cookie:    gleiche SID, Sessiondatei-Delta 0
```

Bereits vor dem Deployment angesammelte `sess_*`-Dateien werden nicht
pauschal gelöscht. Sie müssen über PHP-Garbage-Collection oder eine
altersgeprüfte Betriebswartung auslaufen. Ein ungefiltertes Löschen würde
aktive Benutzer abmelden und Warenkörbe verlieren.

Der vollständige Gastseiten-Cache:

- verwendet Shared Locks für normale Treffer und Exclusive Locks nur für
  Initialisierung/Rotation;
- entfernt auf sicheren, unpersonalisierten Hits Session-Cookies und private
  Cache-Header;
- liefert `Cache-Control: public`, ETag und `304 Not Modified`;
- verwendet `full_page_browser_ttl`, Standard 60 Sekunden.

Gemeinsame Responses senden:

```text
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
X-Frame-Options: SAMEORIGIN
```

`SAMEORIGIN` erhält die dbxapp-eigenen Fenster-/Frame-Abläufe. Eine strikte CSP
wurde wegen bestehender älterer Inline-Skriptblöcke bewusst noch nicht
aktiviert. Inline-Eventattribute sind dagegen nicht mehr zulässig und werden
automatisiert geprüft.

Unbekannte Permalinks zeigen aus Kompatibilitätsgründen weiterhin die
konfigurierte Home-Darstellung, liefern aber HTTP 404, behalten den
angeforderten Pfad und werden nicht als erfolgreiche Seite gecacht.

## Telemetrie und Content

Der Performance-Timer:

- prüft das DD-Schema über einen versionierten Tagesmarker statt pro Request;
- führt Retention-Cleanup über einen nicht blockierenden Tages-Lock aus;
- schreibt Request und Detailtimer in einer Transaktion.

Der Content-Renderer:

- cached Folderzeilen requestlokal für Rechte und vererbte Einstellungen;
- lädt verwendete Medien gebündelt;
- löst Sprachgeschwister direkt über `lng_uid` statt über einen vollständigen
  Permalink-Index-Scan.

## Verifikation

Am 24. Juli 2026 wurden ausgeführt:

```text
PHP-Syntax: 373 von 373 Laufzeitdateien erfolgreich
Regression: 34 von 34 PHP-Testdateien erfolgreich
JavaScript: 2 von 2 Testdateien; 32 von 32 Dateien per node --check
Browser: Startseite, Shop-Katalog und Workflow-Übersicht
Browser: Warenkorb Einzel-Löschen und Leeren per AJAX/Confirm, Badge korrekt
Shop-GET: 10 Katalogaufrufe HTTP 200, DB-Hash und Änderungszeit unverändert
Shop: Mengen-/Einzeldekoration für 30 Produkte vollständig identisch
Performance: dbxShop 179,0 -> 77,8 ms; DB 73,8 -> 23,0 ms
Performance: selektives Kartenrendering 579,5 -> 94,0 ms
Performance: Admin-Bildliste 21,1 -> 2,9 ms pro Aufruf
HTTP: Cache 200/ETag, bedingter Request 304, kein Set-Cookie
HTTP: unbekannter Permalink 404 ohne Redirect
Session: 20 Robot-Shop-GETs, HTTP 200, kein Set-Cookie, Sessiondatei-Delta 0
Webhook: nicht authentifizierter POST 503, shop_order Delta 0
Workflow: alter Start-GET ohne Token, workflow_instance Delta 0
Doxygen 1.17.0: HTML vollständig erzeugt, 0 Warnungen
```

Besonders relevante Tests:

```text
php dbx/include/tests/db_access_boundary_test.php
php dbx/include/tests/template_hygiene_test.php
php dbx/include/tests/dbxApi_security_test.php
php dbx/include/tests/dbxAjax_submitter_test.php
php dbx/include/tests/dbxSession_ephemeral_guest_test.php
php dbx/modules/dbxContent/tests/dbxContentPageCache_test.php
php dbx/modules/dbxContent_admin/tests/cms_action_security_test.php
php dbx/modules/dbxWorkflow/tests/workflow_security_test.php
php dbx/modules/dbxWorkflow_admin/tests/workflow_roundtrip_test.php
php dbx/modules/dbxShop/tests/shop_integrity_test.php
php dbx/modules/dbxShop/tests/shop_admin_action_security_test.php
php dbx/modules/dbxShop/tests/shop_repository_request_cache_test.php
php dbx/modules/dbxShop/tests/payment_validation_test.php
```

## Einführungs- und Rückfallplan

1. Lokale Zugangsdaten aus `config.local.example.php` ableiten.
2. Ehemals versionierte Zugangsdaten rotieren.
3. Shop-DD-Sync administrativ einmal kontrollieren.
4. Für jeden aktiven Order-Import ein starkes Webhook-Secret setzen und den
   Provider auf Header-/Body-Übertragung umstellen.
5. PayPal und Amazon Pay im Sandboxkonto vollständig testen.
6. Cache-Header und Gastseiten nach dem Deployment prüfen.
7. Erst dann Provider und Channel produktiv aktivieren.

Kompatibilitäts-Schalter:

- `session_db_guest=1`: zentrale Gast-Session-Persistenz wie früher.
- `full_page_browser_ttl=0`: Browser-TTL deaktivieren, Servercache behalten.

Nicht zurückgenommen werden dürfen die unsicheren Verträge API-Autologin,
unverifizierter Payment-Return, Webhook ohne Authentifizierung oder
zustandsändernder Workflow-GET ohne Action-Token.

## Verwandte Dokumentation

- @ref dbxapp_ai_rules
- @ref dbxapp_shop_guide
- @ref dbxapp_shop_ai_reference
- @ref dbxapp_workflow_guide
- @ref dbxapp_current_operations
