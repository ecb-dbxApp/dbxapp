# dbxApp Source-Refactoring 4.3

Stand: 17. August 2026

Ziel: Der dbxApp-Sourcecode wird einheitlicher, lesbarer, kleiner und klarer strukturiert, ohne Fachverhalten, Sicherheitsgrenzen oder Modulautonomie zu verlieren.

## Verbindliche Arbeitsregeln

- `C:\xampp\htdocs\dbxapp` ist die Entwicklungsquelle und einzige Wahrheit.
- Menüinhalte und installationsbezogene Menü-Templates sind Kundendateien und werden nicht verändert.
- `myX` bleibt ein nicht updatebares Custom-Modul.
- Kein Versionswechsel, kein Release und kein Abgleich von `dbxapp.de`, bevor alle Punkte abgeschlossen und vollständig getestet sind.
- Fremdcode wird isoliert und über Adapter angebunden, nicht intern umgeschrieben.
- Ein Punkt wird erst nach passenden fokussierten Tests und vollständigem SelfTest als erledigt markiert.
- Nach jedem Strukturblock werden mit normalem Admin-Login mindestens Start/Administration, Content-CMS-Formular, Content-Report und die Admin-Missing-Liste real im Browser geprüft; `files/dbxError.log` und Missing-Zähler/Zeitstempel dürfen dabei nicht neu entstehen oder wachsen.
- Verhaltensänderungen und reine Strukturänderungen werden in getrennten, nachvollziehbaren Blöcken umgesetzt.
- Jeder UI-Smoke-Test umfasst zusätzlich die Systemmeldungen (`dbxAdmin/sysmsg/list_sysmsg`); neue Fehlermeldungen sind wie `dbxError.log` und Missing-Treffer zu behandeln.

## Ausgangswerte

- [x] Analyse erstellt.
- [x] 756 PHP-Dateien und rund 168.000 Zeilen inventarisiert (inklusive DD/FD und Tests, ohne Vendor/Backups/Work).
- [x] 280 produktive Klassen-/Trait-Dateien geprüft.
- [x] 566 direkte Form-/Report-Zustandszugriffe in Modulen ermittelt.
- [x] 69 direkte Sessionzugriffe in Modulen ermittelt.
- [x] 19 exakte produktive Methodenduplikat-Gruppen ermittelt.
- [x] Große PHP- und JavaScript-Verantwortungsbereiche ermittelt.

## A. Qualitätsnetz und Konventionen

- [x] Verbindliche PHP-, JavaScript-, Klassen-, Datei- und Methodenkonventionen als Systemregel dokumentieren.
- [x] Architekturtest für eine primäre Klasse pro `*.class.php` ergänzen (sinkende Ausgangsgrenze: 9 Dateien/11 Zusatzklassen).
- [x] Architekturtest gegen neue direkte Form-/Report-Property-Zugriffe ergänzen (sinkende Ausgangsgrenze: 598 Vorkommen).
- [x] Architekturtest gegen neue direkte `$_SESSION`-Zugriffe in Fachmodulen ergänzen (sinkende Ausgangsgrenze: 82 Vorkommen).
- [x] Komplexitätsinventar als wiederholbaren SelfTest bereitstellen (347 eigene PHP-Dateien, 116.148 Zeilen, 4.095 Methoden; sinkende Grenzen für sechs Kernfassaden).
- [x] Fremdcode im Inventar eindeutig kennzeichnen und aus den dbxApp-Komplexitätsmetriken ausschließen.
- [x] Direkte CLI-Testläufe zentral als `DBX_SELFTEST` isolieren; absichtlich provozierte Fehler-/Security-Pfade schreiben nicht mehr in reale Systemmeldungsdatenbanken.

## B. dbxForm- und dbxReport-Konfigurations-API

- [x] Einheitliche, lesbare Setter/Fluent-API für Datenquelle, Action, Record, RID, Modus und Standardoptionen ergänzen.
- [x] Referenzmodul `myInvoices` vollständig auf die neue Konfigurations-API migrieren (17 direkte Zugriffe entfernt).
- [x] Report-Aktionskonfiguration vollständig über `set_table_actions()` und Optionsmethoden führen.
- [x] Direkte Zugriffe in dbxAdmin migrieren.
- [x] Direkte Zugriffe in dbxContent_admin migrieren.
- [x] Direkte Zugriffe in dbxShop und dbxShop_admin migrieren.
- [x] Direkte Zugriffe in dbxWorkflow und dbxWorkflow_admin migrieren.
- [x] Direkte Zugriffe in dbxUser, dbxUser_admin, dbxLogin, dbxContact und dbxContact_admin migrieren.
- [x] Direkte Zugriffe in dbxKi, dbxSelfTest und kleineren Produktmodulen migrieren.
- [x] Direkte Zugriffe in myLKW migrieren; myX nicht verändern.
- [x] Öffentliche interne Properties nach vollständiger Migration kapseln (`_tpl`, `_mode`, `_action`, `_rid`, `_dd`, `_fd`, `_data`, `_post`, `_messages` sind `protected`).

## C. Klassen- und Dateistruktur

- [x] Mehrfachklassen aus `dbxAdmin/include/dbxServer.class.php` trennen.
- [x] Mehrfachklassen aus `dbxAdmin/include/dbxUser.class.php` trennen.
- [x] `dbxAdmin/include/dbxWizard.class.php` tokenbasiert geprüft: `class __CLASS__` ist Generator-Heredoc, keine Laufzeitklasse.
- [x] Mehrfachklassen aus `dbxContent_admin` trennen.
- [x] Mehrfachklassen aus `dbxUser_admin` trennen.
- [x] Mehrfachklassen aus `myLKW` trennen.
- [x] Dateiname, primäre Klasse und Modul-Namespace vereinheitlichen und per Token-Architekturtest auf 0 Abweichungen halten (`myX` unverändert; global geladener Kernel-Timer bewusst ausgenommen).

## D. Echte Duplikate beseitigen

- [x] Shop-Suchnormalisierung und Suchbewertung gemeinsam in `dbxShopSearch` bereitstellen.
- [x] dbxContent-Auswahldaten in `dbxContentSelectOptions` zentralisieren.
- [x] Externe Video-/Medien-URL-Auflösung zentralisieren (`dbxContentMediaUrl` und `dbxShopMediaUrl`; fokussierte CMS-/Shop-Tests grün).
- [x] DD-/FD-Editor-Reihenfolge und Default-Feldaufbau in `dbxEditorRecords` zentralisieren und per Vertragstest absichern.
- [x] Kontaktstatus-Darstellung in `dbxContactPresentation` zentralisieren.
- [x] Identische User-Grid-Löschoperationen in `dbxUserGridActions` zentralisieren und mit User-/Login-Vertragstests absichern.
- [x] Übrige exakte Duplikatgruppen zusammenführen; verbleibende Kompatibilitätsadapter und triviale Konstruktoren im automatischen Duplikatvertrag explizit dokumentieren.

## E. Benennungen und Coding-Stil

- [x] `haeder` vollständig zu `header` migrieren (API, Properties und drei aktive Tabellen-Templates; Report-/Template-Tests grün).
- [x] `tabel` in technischen Bezeichnern vollständig zu `table` migrieren (`set_table_tpl`, `_table_tpls`; deutsche Wörter wie „Tabelle“ bleiben korrekt).
- [x] `tabulurator` vollständig zu `tabulator` migrieren (Modus, Request-API und Aufrufer; Report-/Admin-Tests grün).
- [x] `seperator` vollständig zu `separator` migrieren (CSV-Konfiguration und Aufrufer).
- [x] `validatior` vollständig zu `validator` migrieren.
- [x] Den ungenutzten Legacy-Alias `dync_db_to_dd` löschen; nur `sync_db_to_dd` bleibt.
- [x] `multible`, `succeass` und weitere bestätigte Schreibfehler in eigenen Quellen bereinigen; `reponsive` liegt ausschließlich im manifestierten Fremdcode-Fork und wird nicht intern umgeschrieben.
- [x] Die mehrdeutige Gruppenprüfung `dbx()->can()` vollständig durch die alleinige boolesche API `dbx()->has_group()` ersetzen; Alias und alle Produktaufrufe entfernt, Vertragstest ergänzt und zentrale UI-Wege geprüft.
- [x] Sämtliche öffentlichen `dbx()->...`-Methoden nach Nutzung, Verantwortung und semantischer Redundanz inventarisiert (`DBX-API-AUDIT.md`): 98 -> 85 Methoden, 13 Synonyme/native Wrapper/falsch platzierte Einzelfunktionen entfernt; Oberflächenvertrag und Load-only-Regressionstest ergänzt.
- [x] Methoden-/Property-Stil innerhalb eigener Klassen vereinheitlichen.
  - [x] dbxApi-interne Override-Helfer sowie 182 statische Methoden in Content-Cache, Sprachdiensten und 24 weiteren klar abgegrenzten State-/Value-/Config-Klassen auf `snake_case` migriert; CamelCase-Methodengrenze 1443 -> 1259 gesenkt.
  - [x] 477 private Methoden in 43 eigenen Klassen auf `snake_case` migriert; klassenübergreifende Trait-Aufrufe geprüft und korrigiert, CamelCase-Methodengrenze 1259 -> 782 gesenkt.
  - [x] 341 private Methoden in 44 internen Trait-/Kompositionsdateien auf `snake_case` migriert; dynamische Handlernamen und Trait-Aufrufe nachgezogen, CamelCase-Methodengrenze 782 -> 441 gesenkt.
  - [x] Verbleibende Altmethoden klassenweise nach öffentlichen Verträgen und internen Helfern migriert: 35 geschützte sowie 358 weitere eigene Methoden; 13 zuvor mitgezählte JavaScript-Funktionen in PHP-Heredocs tokenbasiert als Fehlklassifikation erkannt. Verbleibend: 0 CamelCase-Methoden.
  - [x] 83 eindeutige CamelCase-Properties (104 Deklarationen) und 15.080 lokale/Parameter-Variablenvorkommen tokenbasiert auf `snake_case` migriert; benannte Argumente, dynamische Aufrufe und Propertyzugriffe geprüft. Architekturvertrag hält eigene Methoden, Variablen und Properties bei 0; externe DOM-, ZipArchive- und PHPMailer-Properties bleiben unverändert.
- [x] Alle in diesem Refactoring neu eingeführten Klassen mit `strict_types=1`, Parameter- und Rückgabetypen versehen; Architekturgrenze verhindert neue untypisierte Klassendateien (180 Altdateien bei nun 222 Klassendateien).
- [x] Mechanische Umstellungen ausschließlich tokenbasiert und ohne manifestierten Fremdcode, `vendor`, `add_ons`, Backups, Work-Verzeichnisse oder `myX` durchgeführt; vollständige PHP-Syntax und 156 PHP-Verträge danach grün.

## F. Kernel-Fassaden intern zerlegen

- [x] dbxForm: Security, Workflow-State, Validierung, Feldauflösung, Meldungen und Rendering in sechs benannte interne Komponenten getrennt (Fassade 5.206 -> 1.506 Zeilen).
- [x] dbxReport: Tabellenrendering, Aktionen/Chrome, Auswahl, Pagination und Grid in fünf benannte interne Komponenten getrennt (Fassade 3.989 -> 1.414 Zeilen).
- [x] dbxDB: Verbindung, DD-Auflösung, Query-Ausführung, CRUD, Schema und Profiling in sechs benannte interne Komponenten getrennt (Fassade 4.921 -> 1.743 Zeilen).
- [x] dbxDD: Modell/Datei, Introspektion, SQL-Dialekt, Mapping, Prozesszustand, Sync und Backup/Restore in sieben Komponenten getrennt (Fassade 4.531 -> 214 Zeilen).
- [x] dbxApi: Request/Session, Config, Security, Assets/Design und Sprache hinter der stabilen `dbx()`-Fassade getrennt (Fassade 3.356 -> 1.827 Zeilen).
- [x] dbxWebApp: Routing, Action-Policy, Ressourcen, Permalinks/Redirects, Design und Output-Pipeline getrennt (Fassade 2.413 -> 1.161 Zeilen).
- [x] dbxReport-Vererbung von dbxForm neu bewertet und bewusst beibehalten: Auswahlfelder, Validierung, Remember-State und Template-Pipeline sind echte gemeinsame Basis; Komposition würde derzeit nur eine breite Proxy-API erzeugen.

## G. Modulservices statt Trait-Monolithen

- [x] dbxContent_admin: Modulcontroller delegiert an den expliziten Objektservice `dbxContent_cms`; CMS, Medien, Persistenz und Sprache sind darin fachlich getrennte Segmente/Services.
- [x] dbxKi: Modulcontroller delegiert an `dbxKiCmsService` und `dbxKiBriefingService`; Vertrag und Bundle sind eigenständige Services.
- [x] dbxShop: Modulcontroller delegiert an `dbxShopService`; Repository, Katalog, Checkout, Order und Darstellung sind separat komponiert.
- [x] dbxShop_admin: Modulcontroller delegiert an `dbxShopAdmin`; Dashboard, Produkt, Order, Medien und Kanäle sind separat komponiert.
- [x] dbxAdmin: Modulcontroller delegiert an die Objektservices `dbxDashboard` und `dbxSchema` statt Fachlogik selbst zu besitzen.
- [x] Verbleibende Traits als ausschließlich interne, kohärente Implementierungssegmente expliziter Service-Fassaden begrenzt; neuer Architekturvertrag verhindert Controller-Rückbau und Segmente über 1.000 Zeilen.

## H. JavaScript modularisieren

- [x] `cms.js` fachlich getrennt: Seite, Baum, Medien, Sprache und Jodit bleiben autonome Module; Marker-Erzeugung/-Serialisierung liegen in `cms-marker.js`, Kontextmenue/Zwischenablage in `cms-context.js`, Bootstrap-Komponenten/Badges in `cms-components.js` und Editor-DOM/Caret/HTML-Zustand in `cms-editor.js`. Explizite Runtime-Kontexte halten die Abhaengigkeiten sichtbar; `cms.js` koordiniert Feature-Laden, Editor-Initialisierung, Inline-Medien und Seitenzustand (6.547 -> 5.031 Zeilen).
- [x] `grid.js` in klar geladene Verantwortungen getrennt: `grid-state.js` (State, Pagination, Dirty/Layout), `grid-transport.js` (Requests/Server-Sort), `grid-columns.js` (Spalten, Editoren, Zeilenaktionen), `grid-ui.js` (Toolbar/Layout/Spaltenwahl) und `grid-export.js` (lazy Export-Abhaengigkeiten). `grid.js` koordiniert nur noch Lebenszyklus, Tabulator-Aufbau, Reload/Sync und Speichern (4.076 -> 1.837 Zeilen).
  - [x] Initialisierungsgrenze vereinheitlicht: Edit-State wird erst nach `tableBuilt` über Tabulator gelesen; Content-Grid öffnet ohne die bisherigen `getEditedCells`-Frühwarnungen.
- [x] `core.js` auf Kernel und Feature-Lader begrenzt (2.942 -> 2.281 Zeilen); Laufzeitdiagnose, Events und Geräteabstraktion nach `runtime.js` (460 Zeilen), Hintergrundplanung nach `scheduler.js` (227 Zeilen) getrennt. Alle zehn Produktdesigns und der Design-Wizard laden die feste Reihenfolge Core -> Runtime -> Scheduler; doppelte Initialisierung wird abgefangen.
- [x] Gemeinsame DOM-, Request- und State-Helfer eindeutig besitzen lassen; keine neue allgemeine Sammeldatei erzeugen. CMS-Marker besitzen ihre DOM-Normalisierung, CMS-Editor besitzt Caret/HTML-Zustand, CMS-Kontext besitzt Blockaktionen, Grid-Transport besitzt Requests und Grid-State besitzt UI-Persistenz.
- [x] Bedarfsgerechtes Laden und einmalige Feature-Registrierung für alle neuen Dateien abgesichert: zwingende Editor-/Grid-Bausteine werden als deklarierte Feature-Abhaengigkeiten vor `init()` geladen, optionale CMS-Bereiche bleiben lazy; die eigentlichen Features bleiben genau einmal registriert.

## I. Session-, Cache- und Laufzeitzustand

- [x] dbxKi-Vorschau-/Vertragszustand in `dbxKiSessionState` kapseln.
- [x] dbxShop-Warenkorb-/Checkoutzustand in `dbxShopSessionState` kapseln.
- [x] dbxContent_admin-Medienprozesszustand in `dbxContentAdminSessionState` kapseln.
- [x] dbxAdmin-Konfigurations- und Wartungszustand über den klar benannten Kernel-Service `dbxConfigStore` kapseln.
- [x] Direkte Modulzugriffe auf DD-/Kernel-Sessioncache entfernen.
- [x] Cache-Zuständigkeiten vereinheitlichen: strukturierter Laufzeitzustand wird ausschließlich über dbxApi sowie modulspezifische State-Fassaden gelesen und geschrieben; Architekturgrenze `direct_module_session` von 82 auf 0 gesenkt.

## J. Fremdcode isolieren

- [x] `dbxUpload` unverändert nach `dbx/add_ons/class-upload` verschoben und über den stabilen Systemadapter `dbx/include/dbxUpload.class.php` angebunden.
- [x] `dbxBarcode` unverändert nach `dbx/add_ons/barcode` verschoben und über den stabilen Systemadapter `dbx/include/dbxBarcode.class.php` angebunden.
- [x] `dbxJstree` als gepflegten Fremdcode-Fork vollständig nach `dbx/add_ons/dbxJstree` isoliert; Core-Feature-Lader und Theme-Pfade auf den Add-on-Besitz umgestellt.
- [x] Lizenz-, Herkunfts-, Versions-, Source- und Adapterinformationen in `dbx/third-party-sources.json` sowie Add-on-Manifesten maschinenprüfbar gemacht; eigener Isolationstest ergänzt.

## K. Abschlussprüfung

- [x] Alle fokussierten Verträge und Modultests grün.
- [x] Vollständiger SelfTest grün: 170/170 entdeckte System-, Include- und Modulvertraege.
- [x] PHP-Syntax des Gesamtsystems grün: 839/839 ausfuehrbare eigene PHP-Dateien; `dbx/modules/dbx/tpl/php/dd_file.php` ist ein Generator-Template mit `{..._block}`-Platzhaltern und deshalb bewusst keine direkt parsbare PHP-Quelldatei.
- [x] JavaScript-Syntax des Gesamtsystems grün: 55/55 eigene JavaScript-Dateien (ohne Vendor/Add-ons).
- [x] Template-Nutzungsaudit ohne ungenutzte Familien: 570 Familien, 447 statisch verwendet, 123 konventionsbedingt geschuetzt, 0 Kandidaten/0 Dateien.
- [x] Laufzeittests für zentrale Admin-, Formular-, Report-, CMS-, Shop-, Workflow- und KI-Abläufe grün.
- [x] Wiederholbarer UI-Smoke-Test mit normalem Admin-Login: Update/Admin, Content-Formular, Content-CMS samt real geoeffnetem 13-Punkte-Kontextmenue, Content-Report (40 Zeilen), Missing-Liste und SysMsg ohne Fehlerbanner, fehlgeschlagene Lazy-Bereiche oder Browserwarnungen/-fehler; keine neue Systemmeldung vom 17.08.2026, Missing unveraendert bei 54 fuer den bekannten Altpfad `dbx/js/lib/content.js`.
- [x] Admin-Bypass nach Tests deaktiviert bzw. nicht im produktiven `index.php` vorhanden.
- [x] Kein verbleibendes `dbxError.log`; die beim Service-Umbau entdeckten Ladefehler wurden behoben und das ausschließlich daraus entstandene Protokoll anschließend entfernt.
- [x] Metriken erneut erfasst: direkte Form-/Report-Zustandszugriffe 566 -> 0, direkte Modul-Sessionzugriffe 69 -> 0, Mehrklassen-/Dateinamen-/Namespace-Abweichungen jeweils 0; `dbxForm` 5.206 -> 1.506, `dbxReport` 3.989 -> 1.414, `dbxDB` 4.921 -> 1.743, `dbxDD` 4.531 -> 214, `dbxWebApp` 2.413 -> 1.161, `core.js` 2.942 -> 2.281, `grid.js` 4.076 -> 1.837 und `cms.js` 6.547 -> 5.031 Zeilen.
- [ ] Erst danach Version/Release/Update und Abgleich der Auslieferungsstände separat durchführen.

## L. Klare Kernel-Services

- [x] Konfigurationscache, Invalidierung und lesbarer PHP-Export in `dbxConfigStore` gebündelt; redundante `dbx()`-Methoden entfernt.
- [x] Gemeinsame Suchfeld-Defaults in `dbxSearchDefaults` gebündelt.
- [x] Designkatalog, Skin-Erkennung und Skin-Normalisierung in `dbxPresentation` gebündelt.
- [x] Modul-CSS und Modul-JavaScript über `dbxAssetRegistry` registriert; KI-Regeln und Moduldokumentation auf den Service-Aufruf aktualisiert.
- [x] Interne Kernel-Services erzeugen keine myX-Overrides; das Custom-Modul bleibt unangetastet.
- [x] `dbxApi` von 85 auf 70 öffentliche Methoden reduziert.
- [x] Architekturvertrag verhindert die Rückkehr ausgelagerter Methoden in `dbxApi` und hält die Permalink-Orchestrierung ausdrücklich im Kernel.
- [x] UI geprüft: Admin, Konfigurationsformular, Content-CMS, Reports, Missing, Hell/Dunkel und dynamisches dbxKi-Modul-CSS ohne Browserfehler.

## M. Request- und Fachabläufe

- [x] Vollständigen Frontcontroller-Ablauf in `dbxRequestPipeline` kapseln; `index.php` bleibt Bootstrap, Fehlerbehandlung und ein einzelner Pipeline-Aufruf.
- [x] Fachfremde Kleinfunktionen aus `dbxApi` entfernen: Passworterzeugung in `dbxPasswordPolicy`, Fehlerprotokollierung in `dbxRuntime`, vollständige Mailvorbereitung in `dbxMail`; öffentliche API 70 -> 64 Methoden.
- [x] DD-Synchronisierung in benannte Phasen für Vorbereitung, Tabelle, Felder, Indizes und Metadaten-Merge zerlegen; öffentlicher Sync-Vertrag und DB-Synchronisierung bleiben unverändert.
- [x] Wizard-Servicevorlage von der Eingabeaufbereitung trennen sowie Shop-Produktanreicherung und Channel-Seitenaufbau in Lade-, Anreicherungs- und Renderphasen zerlegen.
- [x] Nach jedem Block Admin, CMS/Formular, Reports, DD, Wizard und Shop per UI sowie Missing, Systemmeldungen und `files/dbxError.log` geprüft.
- [x] Architekturvertrag verhindert den Rückbau der benannten Request-, DD-, Wizard- und Shop-Phasen.

## N. Renderer, Workflow und aktueller Modul-Wizard

- [x] `dbxContentRenderer` nach Seitenaufbau, SEO, Layout und Medien in vier kohärente interne Segmente getrennt; öffentliche Fassade 2.226 -> 32 Zeilen, API und Verhalten unverändert.
- [x] `dbxWorkflowEngine` nach Definition, Laufzeit und Rendering in drei kohärente interne Segmente getrennt; öffentliche Fassade 2.012 -> 59 Zeilen, Sicherheits- und Roundtrip-Verträge unverändert.
- [x] Die 282-zeilige Wizard-Servicevorlage vollständig aus `dbxWizard.class.php` nach `include/templates/module-service.template.php` ausgelagert; Wizard 1.331 -> 1.130 Zeilen.
- [x] Erzeugte Module auf den Stand von dbxApp 4.3 gebracht: deklaratives `cfg/actions.php`, lokales `dbx.package.json`, individuelle Form-ID/Modulvorlage, gemeinsame Form-Bar/Meldungen/Footer, `dbx|report-default`, deklarative Tabellenaktionen und modulinterne Hilfe.
- [x] Formular- und Report-FDs werden vollständig in Deutsch, Englisch und Spanisch erzeugt; normale Tabellenreports kopieren kein eigenes Standardtemplate mehr.
- [x] Generator-Vertrag erzeugt ein temporäres Beispielmodul, lintet Router/Service/Manifest, lädt Aktionsmanifest und Serviceklasse und prüft die erwarteten öffentlichen Formular-/Reportmethoden.
- [x] Fokussierte Content-, Workflow-, Form-, Report-, Action-, Modulautonomie-, Dekompositions- und Komplexitätsverträge grün.
- [x] UI-Smoke-Test grün: Wizard, Content-Formular, Content-Report, Workflow, Missing und Systemmeldungen ohne DBX-Fehler; `files/dbxError.log` bleibt abwesend.
- [x] Vollständiger SelfTest nach dem gesamten Block grün: 170/170 Tests, 0 Fehler; Komplexitätsinventar 425 eigene PHP-Dateien, 116.428 Zeilen und 4.083 Methoden.
