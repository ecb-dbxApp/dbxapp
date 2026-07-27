# Design, Themes und Skins {#dbxapp_design_themes_skins}

Stand: 2026-07-26

dbXapp trennt Fachlogik, Content und Darstellung. Ein **Design** ist eine
vollstaendige Anwendungsschale mit eigenem HTML, CSS, JavaScript und Bildern.
Ein **Skin** ist eine Farb- und Kontrastvariante innerhalb eines Designs. Diese
Trennung erlaubt technisch identische Seiten mit sehr unterschiedlichen
Auftritten.

Aktuell sind drei eigenstaendige Frontend-Designs vorhanden:

| Design | Charakter | Navigation | angebotene Skins |
| --- | --- | --- | --- |
| `dbxapp` | technisch, kompakt, anwendungsorientiert | horizontal | Blau, Gruen, Gelb, Rot, Hell, Dunkel |
| `flowers` | verspielt, organisch, Holz, Pflanzen und Kupfer | links vertikal | Blau, Gruen, Gelb, Rot, Hell, Dunkel |
| `steal` | Edelstahl, Chrom, Gold und Riffelblech | horizontal | Blau, Gruen, Gelb, Rot, Hell, Dunkel |

Das konfigurierte Admin-Design ist `dbxapp`. Ein Frontend-Benutzer darf das
Frontend-Design wechseln. Wird im Frontend fuer Administratoren das
horizontale Admin-Menue eingeblendet, bleibt dieses bewusst horizontal und
uebernimmt nicht die vertikale Flowers-Navigation.

## Begriffe und Verantwortung

### Design

Ein Design verantwortet:

- die aeussere HTML-Struktur der Seite,
- Haupt- und Admin-Navigation,
- Header, Contentflaeche, Footer und Fenster-Dock,
- responsive Verhalten,
- Typografie, Abstaende, Oberflaechen und Bildsprache,
- designbezogene JavaScript-Ergaenzungen,
- die Einbindung der angebotenen Skins.

Ein Design verantwortet **nicht**:

- Shop-, CMS- oder Benutzerlogik,
- Datenzugriff und Rechtepruefung,
- das Speichern von Formularen,
- Report-Pagination oder Ajax-Transport,
- die fachliche Bedeutung eines Moduls.

### Skin

Ein Skin veraendert innerhalb eines Designs vor allem Farben, Kontraste,
Schatten, Rahmen und Hell-/Dunkelverhalten. Der Skin darf die Grundstruktur
eines Designs nicht neu bauen. Wenn Navigation, Spalten oder Seitenschale
anders werden sollen, ist ein eigenes Design die richtige Ebene.

### Modul-Design

Module duerfen eigenes Komponenten-CSS und eigene Templates mitbringen, zum
Beispiel:

```text
dbx/modules/dbxShop/design/css/shop.css
dbx/modules/dbxShop/design/js/shop.js
dbx/modules/dbxShop/tpl/htm/product-card-default.htm
```

Dieses Modul-Design gestaltet nur die fachliche Ausgabe. Die umgebende Seite
kommt weiterhin aus dem aktiven globalen Design unter `dbx/design/`.

## Verzeichnis eines eigenstaendigen Designs

Ein Design liegt unter:

```text
dbx/design/{design}/
  htm/
    default.htm
  css/
    colors.css
    skin-*.css
    base.css
    theme.css
    c-*.css
    m-menu.css
  js/
  img/
```

`htm/default.htm` ist die Voraussetzung dafuer, dass ein Verzeichnis als
oeffentlich waehlbares Frontend-Design erkannt wird. Verzeichnisse mit einem
Namen, der mit `_` oder `-` beginnt, werden in der Designauswahl nicht
angeboten.

Jedes Design soll alle designbezogenen Dateien selbst besitzen. `flowers`
soll daher nicht stillschweigend CSS, Bilder oder JavaScript aus `dbxapp`
erben. Gemeinsam genutzt werden nur zentrale System- und Vendor-Ressourcen,
etwa Bootstrap, Bootstrap Icons, jQuery und `dbx/js/lib/core.js`.

## Design-Templates und Seitenvarianten

Die Seitenschale wird mit `dbxTPL::get_design_tpl()` geladen. Ausgangspunkt
ist die aktive Kombination aus:

- `dbx_design`: Designpaket,
- `dbx_page`: Seitenvariante,
- `dbx_lng`: Sprache,
- `dbx_color`: Skin.

Typische Dateien im Design sind:

```text
htm/default.htm     normale Seite
htm/intro.htm       optionale Einfuehrungsseite
htm/_window.htm     reduzierte Fensterschale, wenn vorhanden
htm/_editor.htm     Editor-Spezialseite, wenn vorhanden
htm/_install.htm    Installationsseite, wenn vorhanden
```

Ist eine angeforderte Seitenvariante im aktiven Design nicht vorhanden, faellt
die Template-Aufloesung auf `default.htm` zurueck. Der derzeitige
Fenstermechanismus setzt bei `dbx_window=1` die Seite auf `_window`. Eine
separate `_adminWin`-Variante ist im aktuellen Stand nicht implementiert und
darf deshalb nicht vorausgesetzt werden.

Ein minimales `default.htm` enthaelt mindestens:

```html
<!doctype html>
<html lang="{dbx:lng}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{dbx:title}</title>{dbx:head_meta}
  <link rel="stylesheet" href="dbx/vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="dbx/vendor/twbs/bootstrap-icons/font/bootstrap-icons.css">
  <link rel="stylesheet" href="dbx/design/{dbx:design}/css/colors.css">
  <link rel="stylesheet" href="{dbx:skin_css}">
  <link rel="stylesheet" href="dbx/design/{dbx:design}/css/base.css">
  <link rel="stylesheet" href="dbx/design/{dbx:design}/css/theme.css">
</head>
<body class="{dbx:skin_class}"
      data-dbx-design="{dbx:design}"
      data-dbx-skin="{dbx:color}">
  <main id="dbxMain">[dbx:content]</main>
  <script src="dbx/vendor/components/jquery/jquery.min.js"></script>
  <script src="dbx/js/lib/core.js?design={dbx:design}"></script>
</body>
</html>
```

Der reale Contentmarker lautet `[dbx:content]`. Die Marker
`{dbx:skin_css}`, `{dbx:skin_class}`, `{dbx:design}`, `{dbx:color}` und
`{dbx:lng}` werden durch `dbxTPL` ersetzt.

Optional kann eine Designschale wiederverwendbare Fragmente enthalten:

```html
<div class="brand">[dbx:logo][dbx:branding]</div>
<main>[dbx:content]</main>
[dbx:footer]
```

Die Inhalte liegen im selben Design unter `htm/logo.htm`,
`htm/branding.htm` und `htm/footer.htm`. `dbxTPL` setzt nur tatsächlich
verwendete Slots ein. Bestehende Designs ohne Slots bleiben unverändert.

## Laufzeit: Wie ein Design bestimmt wird

Der Ablauf liegt zentral in `dbxWebApp`:

1. `check_remember()` liest `dbx_design`, `dbx_color`, `dbx_lng` und
   `dbx_edit` aus Remember-State und Request.
2. Requestwerte duerfen die gemerkten Werte ueberschreiben.
3. `check_design()` validiert das Design und loest die Aliaswerte `user` und
   `admin` gegen die Konfiguration auf.
4. Ein Admin-Modul mit `_admin` im Modulnamen schaltet fuer Administratoren
   auf `default_design_admin`.
5. `design_load()` waehlt Design und Page, setzt den Modulinhalt in
   `[dbx:content]` ein und liefert die fertige Seite aus.
6. Bei `dbx_ajax=1` wird keine neue Seitenschale geladen; nur der Modulinhalt
   wird zurueckgegeben.

Die Standardwerte stehen in `dbx/modules/dbx/cfg/config.php`:

```php
$config['default_color'] = 'blau';
$config['default_design_user'] = 'dbxapp';
$config['default_design_admin'] = 'dbxapp';
```

Ein Modul oder Admin-Werkzeug kann fuer seinen Aufruf `dbx_page`,
`dbx_design`, `dbx_lng` und `dbx_color` setzen. Solche Umschaltungen muessen
vor dem Laden der Designschale erfolgen und duerfen die zentrale Validierung
nicht umgehen.

## Frontend und Administration

Die aktuelle Trennung lautet:

- Normale Frontend-Seiten verwenden das vom Benutzer gewaehlte Design.
- `dbxShop` ist ein Frontend-Modul und laeuft deshalb in der aktiven
  Frontend-Schale.
- Module mit dem Suffix `_admin`, beispielsweise `dbxShop_admin`, wechseln
  fuer Administratoren zum konfigurierten Admin-Design.
- Das Admin-Menue kann fuer angemeldete Administratoren auch im Frontend
  erscheinen. Im Flowers-Design wird es als eigener horizontaler Streifen
  ueber dem eigentlichen Frontend-Header ausgegeben.
- Ein per Ajax oder `openWin` geladener Inhalt erhaelt nicht automatisch ein
  zweites komplettes Design. Die Fensterschale kommt vom aufrufenden Kontext;
  Komponenten des Admin-Inhalts werden durch ihre Modul-Styles gestaltet.

Damit bleiben Frontend und Backend visuell trennbar, ohne dass Modulcode
dupliziert oder ein iframe vorausgesetzt werden muss. Falls spaeter echte
Design-Isolation in Fenstern benoetigt wird, muss sie als eigener, dokumentierter
Fenstermodus umgesetzt werden.

## Design- und Skin-Auswahl im Menue

Die Auswahl befindet sich im Hauptmenue-Template:

```text
dbx/modules/dbxMenu/tpl/htm/dbx-top-main.htm
```

`dbxMenu::frontend_design_options()` durchsucht zur Laufzeit
`dbx/design/*/htm/default.htm`. Dadurch erscheint ein neues vollstaendiges
Design ohne fest verdrahtete Liste im Menue. Innerhalb der Auswahl bildet
`dbxMenu::render_design_skin_menu()` fuer jedes Design eine eigene Gruppe.
Darunter stehen ausschliesslich dessen eigene Farbvarianten.

Die verbindliche Skin-Erkennung liegt zentral in
`dbxApi::get_design_skin_ids()`. Sie liest je Design die vorhandenen Dateien:

```text
dbx/design/{design}/css/skin-*.css
```

Damit gibt es keine zweite Skin-Liste in `dbxMenu` oder `utilities.js`. Eine
neue Datei wie `skin-petrol.css` wird automatisch nur in der Gruppe des
betreffenden Designs angeboten. `dbxApi::normalize_skin()` prueft
`dbx_color` gegen genau diesen Katalog, damit kein nicht vorhandenes
Stylesheet ausgewaehlt wird.

Jede Auswahl ist ein normaler, kopierbarer GET-Aufruf mit `dbx_design`
**und** `dbx_color`. Damit fuehren Designwechsel und reine Farbwechsel
denselben serverseitig validierten Ablauf aus; JavaScript ist fuer die
Auswahl nicht erforderlich. `utilities.js` behaelt `applySkin()` fuer
programmatische Oberflaechen und speichert solche lokale Auswahlen pro
Design. Die Farbe eines Designs ueberschreibt damit nicht mehr versehentlich
die Auswahl eines anderen Designs.

## CSS-Schichten

Eine robuste Aufteilung ist:

| Datei | Verantwortung |
| --- | --- |
| `colors.css` | gemeinsame Design-Tokens und semantische Farbvariablen |
| `skin-*.css` | Werte fuer eine konkrete Farb-/Kontrastvariante |
| `base.css` | App-Shell, Header, Main, Footer, Navigation, responsive Grundstruktur |
| `theme.css` | visuelle Sprache fuer Panels, Formulare, Reports und Fenster |
| `glass-3d.css` | dbxapp-Effektschicht fuer Glas, Licht, metallische Kanten und gestaffelte Tiefe |
| `c-*.css` | Komponenten wie CMS, Form, Grid, Report, OpenWin und Process |
| `m-menu.css` | Menue- und responsive Sonderregeln |

Skins sollten vorzugsweise CSS-Variablen ueberschreiben. Komponenten greifen
auf diese Variablen zu. Dadurch muss eine Dark-Variante nicht die komplette
Komponentenbibliothek kopieren.

Im Design `dbxapp` wird `glass-3d.css` nach `base.css` und `theme.css`
eingebunden. Die Datei gestaltet die vorhandenen Komponenten ueber die
Skin-Tokens und darf deshalb keine eigene, vom Skin getrennte Farbwelt
einfuehren.

## Das Flowers-Design

`flowers` ist bewusst keine Farbkopie von `dbxapp`. Seine Merkmale sind:

- feste bzw. einblendbare linke Hauptnavigation,
- Holz-, Pflanzen- und Kupferanmutung,
- organische Ornamente und florale Icons,
- eine eigenstaendige Topbar und ein eigener Footer,
- ein horizontaler Admin-Streifen fuer Administratoren,
- eigenstaendiges Login-Styling,
- Light- und Dark-Skin,
- designbezogenes Verhalten in `dbx/design/flowers/js/flowers.js`.

Die linke Navigation muss bei langen oder aufgeklappten Untermenues scrollbar
bleiben. Dropdowns des horizontalen Admin-Menues muessen ueber dem Seiteninhalt
liegen; deshalb sind Stacking Context und `z-index` Teil der Designpruefung.

## Neues Design anlegen

Für Administratoren ist der bevorzugte Weg das Design Studio:

```text
?dbx_modul=dbxDesign_admin&dbx_run1=list
```

Der Wizard erzeugt eine unabhängige Kopie, fragt Aufteilung, Menüform, Footer,
Branding, Logo, Farben und Typografie ab und validiert den Vertrag vor der
Aktivierung. Der manuelle Weg bleibt für Entwickler möglich:

1. Einen eindeutigen, URL-tauglichen Namen waehlen.
2. Unter `dbx/design/{name}` mindestens `htm`, `css`, `js` und `img` anlegen.
3. Eine vollstaendige `htm/default.htm` mit `[dbx:content]`, Menues, Footer,
   Vendor-Abhaengigkeiten und `core.js` erstellen.
4. Eigene `colors.css`, `base.css`, `theme.css` und Skin-Dateien anlegen.
5. Benoetigte `c-*.css` und Menue-Regeln im Design selbst pflegen.
6. Das Design mit Gast, Benutzer und Administrator testen.
7. Lange Haupt- und Untermenues, kleine Displays und Tastaturbedienung testen.
8. Formulare, Reports, CMS, Login, Shop und OpenWin/Ajax pruefen.
9. Erst danach das Design ueber `dbx_design={name}` produktiv anbieten.

## Pruefliste

- `htm/default.htm` existiert und enthaelt `[dbx:content]`.
- `{dbx:skin_css}` und `{dbx:skin_class}` sind eingebunden.
- `body` traegt `data-dbx-design` und `data-dbx-skin`.
- `core.js?design={dbx:design}` wird geladen.
- Das Design importiert keine privaten Dateien eines anderen Designs.
- Haupt- und Admin-Menue sind erreichbar und ueberdecken den Content korrekt.
- Untermenues bleiben bei geringer Hoehe erreichbar.
- Light/Dark bzw. alle angebotenen Skins funktionieren ohne unlesbare
  Kontraste.
- Login, CMS, Shop, Formulare, Reports, Fenster und Ajax wurden geprueft.
- Admin-Module laufen im Admin-Design; Frontend-Seiten bleiben umschaltbar.

## Verwandte Dokumentation

- @ref dbxapp_design_ai_reference
- @ref dbxapp_design_studio_ki
- @ref dbxapp_routing_templates
- @ref dbxapp_dbxtpl
- @ref dbxapp_javascript_libs
- @ref dbxapp_ai_rules
