# Design-KI-Referenz {#dbxapp_design_ai_reference}

Stand: 2026-07-14

Diese Datei ist der verbindliche Arbeitskontext fuer KI-Agenten bei Aufgaben
an Designs, Themes, Skins, Menues und Design-Fenstern. Die erklaerende
Menschen-Dokumentation steht unter @ref dbxapp_design_themes_skins.

## Geltungsbereich

```yaml
domain: dbxapp-design
frontend_design_root: dbx/design
design_selector_module: dbx/modules/dbxMenu
runtime_owner: dbx/include/dbxWebApp.class.php
template_owner: dbx/include/dbxTPL.class.php
skin_client_owner: dbx/js/lib/utilities.js
core_loader: dbx/js/lib/core.js
current_frontend_designs:
  - dbxapp
  - flowers
admin_default_design: dbxapp
```

## Unveraenderliche Architekturregeln

1. Ein Design ist ein eigenstaendiges Paket unter `dbx/design/{name}`.
2. Designpakete erben keine privaten CSS-, JS- oder Bilddateien voneinander.
3. Fachlogik bleibt in Modulen; Designs enthalten Layout und Darstellung.
4. Globale Infrastruktur wird weiterverwendet: Bootstrap, Bootstrap Icons,
   jQuery, `core.js`, `dbxTPL`, `openWin`, Ajax und UI-State.
5. Ein oeffentlich waehlbares Design braucht `htm/default.htm`.
6. `[dbx:content]` ist der verbindliche Marker fuer den Modul-/Seiteninhalt.
7. Skins werden ueber `dbx_color`, Designs ueber `dbx_design` gefuehrt.
8. Admin-Module mit `_admin` im Modulnamen werden fuer Administratoren gegen
   `default_design_admin` aufgeloest.
9. Das horizontale Admin-Menue bleibt auch im Flowers-Frontend horizontal.
10. Keine neue iframe-, Ajax-, Fenster- oder Persistenzarchitektur ohne einen
    ausdruecklichen Auftrag und eine Aktualisierung dieser Dokumentation.
11. Geführte Design-Erstellung und Design-KI laufen über `dbxDesign_admin`
    beziehungsweise `dbxKiDesignService`; freie Dateischreibwege sind kein
    Ersatz.

## Kanonische Laufzeitwerte

| Wert | Bedeutung | Quelle/Persistenz |
| --- | --- | --- |
| `dbx_design` | gewaehltes Design oder Alias `user`/`admin` | Request und Remember-State |
| `dbx_page` | Design-Seitenvariante | Systemvariable |
| `dbx_lng` | aktive Sprache | Request und Remember-State |
| `dbx_color` | kanonische Skin-ID | Request und Remember-State |
| `dbx_activ_design` | optional bereits aufgeloestes Design | Systemvariable |
| `dbx_activ_page` | optional bereits aufgeloeste Page | Systemvariable |
| `dbx_window` | Fenstermodus; waehlt aktuell `_window` | Request/Systemvariable |
| `dbx_ajax` | nur Modulinhalt, keine Designschale | Request/Systemvariable |

Die Aliaswerte werden in `dbxWebApp::check_design()` aufgeloest:

```text
user  -> config.default_design_user
admin -> config.default_design_admin
```

Aktuelle Konfiguration:

```yaml
default_design_user: dbxapp
default_design_admin: dbxapp
default_color: blau
```

## Vor jeder Designaenderung lesen

Mindestens diese Dateien pruefen:

```text
15_Design_Themes_Skins.md
dbx/include/dbxWebApp.class.php
dbx/include/dbxTPL.class.php
dbx/include/dbxApi.php
dbx/modules/dbx/cfg/config.php
dbx/modules/dbxMenu/dbxMenu.class.php
dbx/modules/dbxMenu/tpl/htm/dbx-top-main.htm
dbx/js/lib/core.js
dbx/js/lib/utilities.js
dbx/design/{betroffenes-design}/htm/default.htm
dbx/design/{betroffenes-design}/css/base.css
dbx/design/{betroffenes-design}/css/theme.css
dbx/design/{betroffenes-design}/css/glass-3d.css (nur wenn vorhanden)
```

Bei Flowers zusaetzlich:

```text
dbx/design/flowers/js/flowers.js
dbx/design/flowers/css/colors.css
dbx/design/flowers/css/skin-hell.css
dbx/design/flowers/css/skin-dunkel.css
```

## Aktuelle Designvertraege

### dbxapp

```yaml
id: dbxapp
purpose: technisches Standard- und Admin-Design
navigation: horizontal
skins:
  blau: Blau
  gruen: Gruen
  gelb: Gelb
  rot: Rot
  hell: Hell
  dunkel: Dunkel
default_skin: blau
```

### flowers

```yaml
id: flowers
purpose: organisches Frontend fuer Blumen- und Pflanzenhandel
navigation:
  frontend: links-vertikal
  admin: oben-horizontal
visual_language:
  - holz
  - pflanzen
  - kupfer
  - floral
skins:
  hell: Light
  dunkel: Dark
default_skin: hell
design_script: dbx/design/flowers/js/flowers.js
```

Andere vorhandene Flowers-Dateien mit Namen wie `skin-blau.css` sind keine
Freigabe, diese Varianten im Menue anzuzeigen. Die sichtbaren Optionen werden
in `dbxMenu::skin_options()` festgelegt.

## Design-Erkennung und Auswahl

`dbxMenu::frontend_design_options()` erkennt Verzeichnisse nach diesem
Vertrag:

```text
include, wenn: dbx/design/{name}/htm/default.htm existiert
exclude, wenn: {name} mit _ oder - beginnt
```

Eine KI darf fuer ein neues Design keine zweite statische Designliste
einfuehren. Beschriftungen werden derzeit aus dem Verzeichnisnamen abgeleitet;
`dbxapp` erhaelt die Sonderbeschriftung `dbXapp`.

## Template-Vertrag

Ein `default.htm` muss die folgenden Aufgaben erfuellen:

```yaml
required:
  - valid_html_document
  - title_marker: "{dbx:title}"
  - content_marker: "[dbx:content]"
  - design_attribute: "data-dbx-design={dbx:design}"
  - skin_attribute: "data-dbx-skin={dbx:color}"
  - skin_stylesheet: "{dbx:skin_css}"
  - skin_class: "{dbx:skin_class}"
  - core_script: "dbx/js/lib/core.js?design={dbx:design}"
recommended:
  - dbxHeader
  - dbxMain
  - dbxContent
  - dbxFooter
  - windrop
  - dbxWindowCloseAll
  - dbxBackToTop
optional_slots:
  - "[dbx:logo] -> htm/logo.htm"
  - "[dbx:branding] -> htm/branding.htm"
  - "[dbx:footer] -> htm/footer.htm"
```

Beim Kopieren eines Design-Templates sind harte Pfade auf das Ursprungsdesign
vollstaendig zu ersetzen. Nur Vendor- und zentrale dbXapp-Ressourcen bleiben
gemeinsam.

## CSS-Vertrag

```yaml
colors.css: semantische Tokens und designweite Variablen
skin-*.css: Skinwerte und Kontrastmodus
base.css: Seitenschale, Navigation, responsive Layout
theme.css: visuelle Komponentenoberflaeche
glass-3d.css: optionale designweite Effekt- und Tiefenschicht
c-*.css: einzelne Systemkomponenten
m-menu.css: Menuevarianten
```

Entscheidungsregel:

```text
Farbe/Kontrast?       -> skin-*.css oder colors.css
App-Shell/Navigation? -> base.css
Panel/Form/Report?    -> theme.css oder passendes c-*.css
Fachkomponente?       -> dbx/modules/{modul}/design/
Verhalten?            -> vorhandene Core-Lib oder Design-JS
```

Keine unnoetigen `!important`-Ketten erzeugen. Vor einem `z-index`-Fix zuerst
Stacking Contexts (`position`, `transform`, `filter`, `overflow`, `isolation`)
der beteiligten Eltern pruefen.

## Fenster und Admin-Inhalte

Aktueller Ist-Zustand:

```yaml
iframe_isolation: false
window_page: _window
admin_window_page: not_implemented
ajax_response_has_design_shell: false
open_window_shell_owner: calling_frontend
admin_component_style_owner: admin_module
```

Die Bezeichnung `_adminWin` ist eine moegliche spaetere Zielrichtung, aber
keine aktuelle Datei oder Runtime-Regel. Eine KI darf sie nicht dokumentieren
oder verwenden, bevor Implementierung, Fallback und Tests existieren.

## Erlaubte Aenderungen

- Ein bestehendes Design visuell verbessern.
- Eigene Design-Assets ergaenzen.
- Einen weiteren Skin ergaenzen, wenn auch Menue und Client-Normalisierung
  angepasst werden.
- Ein neues vollstaendiges Designpaket anlegen.
- Design-spezifische responsive oder Accessibility-Fixes vornehmen.
- Das Flowers-Menue scroll- und dropdownfaehig halten.

## Nicht ohne ausdruecklichen Auftrag

- `default_design_admin` von `dbxapp` wegschalten.
- Admin- und Frontend-Rechte anhand des Designs entscheiden.
- Fachlogik in Design-JavaScript verschieben.
- `dbxWebApp`, `dbxTPL` oder `core.js` nur fuer eine optische Einzelkorrektur
  umbauen.
- ein Design von einem anderen privaten Designpaket abhaengig machen.
- neue Skin-IDs ohne server- und clientseitige Normalisierung einfuehren.
- eine `_adminWin`- oder iframe-Architektur nur teilweise implementieren.

## Arbeitsablauf fuer KI-Agenten

1. Auftrag einer Ebene zuordnen: globales Design, Skin, Systemkomponente oder
   Modulkomponente.
2. Aktive Designstruktur und alle Selektoren der betroffenen Komponente lesen.
3. Pruefen, ob der Fehler im Layout, Stacking Context, Overflow, UI-State oder
   Modul-CSS entsteht.
4. Kleinste passende Ebene aendern.
5. Cache-Buster in `default.htm` nur erhoehen, wenn Browsercache die geaenderte
   Design-Datei sonst verdeckt.
6. Beide Designs auf Regression pruefen, wenn gemeinsame Menue- oder Core-Dateien
   geaendert wurden.
7. Gast-, Benutzer- und Adminzustand testen.
8. Light/Dark sowie responsive Breiten testen.
9. Diese Referenz aktualisieren, wenn sich ein Architekturvertrag aendert.

Für ein vom Benutzer gestartetes KI-Design gilt stattdessen der kontrollierte
Paketweg:

```text
?dbx_modul=dbxKi&dbx_run1=briefing_design
  -> dbx.design.briefing.v1
  -> Antwort dbx.design.result.v1
  -> ZIP-Pfad- und Dateitypprüfung
  -> Dateivorschau
  -> dbxForm-Freigabe
  -> Backup + Staging + vollständige Vertragsprüfung
```

Die KI liefert nur Dateien unter `result/design/`. PHP, Module, DD, FD,
Datenbankaktionen und freie Shell-Anweisungen sind in diesem Vertrag verboten.

## Mindesttests

```yaml
pages:
  - frontend_home
  - login
  - cms_content
  - shop_catalog
  - admin_module
  - openWin_or_ajax_content
states:
  - guest
  - authenticated_user
  - administrator
  - long_submenu
  - narrow_viewport
  - light_skin
  - dark_skin
assertions:
  - no_horizontal_page_overflow
  - menus_reachable
  - dropdown_above_content
  - readable_contrast
  - content_marker_rendered_once
  - core_libraries_loaded
  - design_choice_persisted
  - admin_module_uses_admin_design
```

## Abschlussbericht einer KI

Der Bericht nennt:

1. geaenderte Design-, Modul- und Core-Dateien getrennt,
2. sichtbare Auswirkung fuer `dbxapp` und `flowers`,
3. gepruefte Rollen, Skins und Viewports,
4. verbleibende Grenzen, insbesondere Fenster-/iframe-Isolation,
5. ob ein Cache-Buster geaendert wurde.

Details zum Benutzer-Wizard und ZIP-Vertrag: @ref dbxapp_design_studio_ki.
