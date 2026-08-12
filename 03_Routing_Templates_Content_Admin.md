# Routing, Templates und Modul-Inclusion {#dbxapp_routing_templates}

dbxapp kennt zwei Hauptwege, eine Funktion aufzurufen:

- Content-getrieben über Permalinks und CMS-Seiten.
- Parameter-getrieben über `dbx_modul`, `dbx_run1`, `dbx_run2`, `dbx_run3`.

Beide Wege können gemischt werden.

## Frontend: Content-getrieben

Im Frontend ist dbxapp normalerweise CMS- und Permalink-getrieben.

Beispiel:

```text
/kontakt
/de/service
/lkw/angebot
```

Der Request wird auf eine CMS-Seite aufgelöst. Diese Seite kann normalen
Content, Templates und Modul-Inclusions enthalten.

Beispiel im Content:

```html
<h1>Meine Anfragen</h1>
[modul=dbxContact]dbx_run1=tickets[/modul]
```

Das Frontend bleibt damit redaktionell steuerbar. Fachfunktionen werden als
Modulinseln eingebettet.

## Verbindlicher SEO- und URL-Vertrag

Öffentliche Inhalte besitzen genau eine indexierbare URL:

- Die konfigurierte deutsche Startseite wird unter der Basis-URL
  `https://dbxapp.de/` ausgegeben. Der interne Permalink `home` bleibt für die
  Content-Auflösung kompatibel, `/home` antwortet aber dauerhaft mit HTTP 301
  auf `/`.
- Normale deutsche, englische und spanische Inhaltsseiten verwenden ihren
  jeweiligen sauberen Permalink und einen selbstreferenziellen Canonical.
- Erkennt die Permalink-Auflösung einen eindeutigen Permalink in einer anderen
  Sprachtabelle, wird diese Sprache für den Request automatisch aktiviert.
  Sprachabhängige Content-URLs benötigen deshalb weder Cookie noch
  `dbx_lng`-Query und sind direkt abrufbar.
- `http://dbxapp.de/*` und `https://www.dbxapp.de/*` werden in einem Schritt
  auf `https://dbxapp.de/*` vereinheitlicht.
- Interne Startseitenlinks verwenden `{dbx:base_href}` und erzeugen nicht
  erneut den Alias `home`.

`dbxContentRenderer` setzt Canonical, Robots, Open Graph, JSON-LD und
`hreflang` als Systemwerte. `dbxTPL` erzeugt daraus den zentralen
`{dbx:head_meta}`-Block. Jedes vollständige Design-HTML mit `<head>` muss
diesen Platzhalter genau einmal direkt im Head enthalten:

```html
<title>{dbx:title}</title>{dbx:head_meta}
```

Ein Design darf **kein eigenes festes** `<link rel="canonical">` definieren.
Dadurch gelten dieselben URLs und Robots-Regeln in `dbxapp`, `flowers`,
`steal`, Fenstern, Intro- und Editor-Seiten. Technische Routen mit
`dbx_modul`, `dbx_run*`, `dbx_action`, `dbx_edit`, `dbx_token`, Ajax oder
Fensterkontext erhalten zentral `noindex,follow`; ein zugleich geladener
Content-Datensatz darf diese Regel nicht mit `index,follow` überstimmen.

Die XML-Sitemap enthält ausschließlich aktive, öffentlich lesbare
Content-Permalinks. Sie enthält keine Login-, Warenkorb-, Konto-, Admin-,
Aktions- oder sonstigen `?dbx_*`-URLs und führt die deutsche Startseite nur als
Basis-URL. `robots.txt` lässt das Crawling zu und verweist auf die Sitemap,
damit Suchmaschinen die `noindex`-Metadaten technischer Seiten lesen können.

Der Regressionstest
`dbx/include/tests/dbxSeoCanonicalPolicy_test.php` sichert Redirect,
Canonical, Robots, Sitemap-Vertrag und die Head-Metablöcke aller Designs.
Nach einer produktiven Veröffentlichung werden `/`, `/home`, eine normale
Inhaltsseite, eine Sprachseite, eine technische Route, `sitemap.xml` und
`robots.txt` geprüft. Erst danach wird in der Google Search Console eine
erneute Validierung angestoßen.

## Deutsche Marketingstruktur und `/trash`

Die deutsche Website ist die maßgebliche öffentliche Fassung. Ihre Navigation
ist auf wenige klare Bereiche begrenzt:

- `Lösungen`: Übersicht, CMS und Website, Shop und Multichannel, individuelle
  Anwendungen, Intranet und Portale sowie dbxKi.
- `Plattform`: Technik, Pakete, Referenzen, Demo und Kontakt.
- `Entwickler`: Entwicklerüberblick und Dokumentation.

Öffentliche Zielseiten verwenden das Content-Template
`c-marketing-body1-footer`. Die Modul-Bar liefert die einzige Überschrift
erster Ordnung; der Seiteninhalt beginnt danach mit Einleitung und
Überschriften ab Ebene zwei. Titel, Beschreibung und Inhalt verwenden echte
deutsche Umlaute. Die Produktbezeichnung lautet immer `dbxapp`.

Redundante, unfertige oder nur zu Testzwecken angelegte Seiten werden nicht
gelöscht. Sie werden nach `/trash` verschoben und erhalten verbindlich:

- `activ = 0`
- `addmenu = 0`
- `group_read = admin`
- `meta_robots = noindex,nofollow`

Der Ordner `/trash` selbst ist nur für die Gruppe `admin` lesbar. Hilfe- und
Tutorialseiten, die intern weiter benötigt werden, bleiben aktiv, erscheinen
aber nicht im Menü und verwenden `noindex,follow`.

Alte deutsche Marketing-Permalinks bleiben als sprachabhängige HTTP-301-
Weiterleitungen in `dbx/modules/dbxContent/cfg/config.php` erhalten. Die
Erkennung erfolgt zentral in `dbxWebApp`; Modul- und Aktionsrouten werden davon
nicht verändert. Da die flachen öffentlichen URLs derzeit kein Sprachsegment
besitzen, enthält die Sitemap nur die maßgebliche deutsche Sprachfassung.
Weitere Sprachen dürfen erst mit jeweils eindeutigen kanonischen URLs in die
Sitemap aufgenommen werden.

## Administration: Parameter-getrieben

In der Administration sind URL-Parameter der Standard.

Beispiel:

```text
?dbx_modul=dbxAdmin
?dbx_modul=dbxContact_admin&dbx_run1=list
?dbx_modul=dbxAdmin&dbx_run1=config&dbx_run2=edit&xmodul=dbx
```

Die Hauptklasse des Moduls wertet `dbx_run1` aus und delegiert an
Include-Klassen.

Minimaler Router:

```php
class myModule {
   public function run() {
      $run1 = dbx()->get_modul_var('dbx_run1', 'list');

      switch ($run1) {
         case 'list':
            return dbx()->get_include_obj('myModuleList')->run();
         case 'edit':
            return dbx()->get_include_obj('myModuleForm')->run();
      }

      return dbx()->get_system_obj('dbxTPL')->get_tpl(
         'dbx|alert-warning',
         array('msg' => 'Unbekannte Aktion.')
      );
   }
}
```

## Mischung beider Welten

Eine CMS-Seite kann ein Admin-aehnliches Modul enthalten:

```html
[modul=dbxContact]dbx_run1=tickets&status=open[/modul]
```

Ein Admin-Dashboard kann wiederum nur Layout definieren:

```html
<div class="dbx-admin-dashboard-slot dbx-admin-dashboard-slot-sysmsg">
   [modul=dbxAdmin]dbx_run1=sysmsg&dbx_run2=list_sysmsg[/modul]
</div>
```

Das Eltern-Template entscheidet nur über Position. Das eingebundene Modul
bleibt für Inhalt, Report, Filter und Aktionen verantwortlich.

## Modulvariablen

`get_modul_var()` liest modulbezogene Parameter. Es ist der Standard innerhalb
eines Moduls.

```php
$run1 = dbx()->get_modul_var('dbx_run1', 'list', 'parameter');
$id   = (int) dbx()->get_modul_var('id', 0, 'int');
```

`get_request_var()` liest direkte Request-Parameter ohne Modulschutz. Es wird
genutzt, wenn ein Wert wirklich global für den Request gemeint ist.

```php
$ajax = (int) dbx()->get_request_var('dbx_ajax', 0, 'int');
```

## Remember-Variablen

Remember-Variablen speichern UI- oder Systemzustand über Requests hinweg.

```php
dbx()->set_remember_var('dbx_lng', 'de', 'dbx');
$lng = dbx()->get_remember_var('dbx_lng', 'de', 'dbx');
```

Typische Werte:

- Sprache: `dbx_lng`
- Design: `dbx_design`
- Editmodus: `dbx_edit`
- Farbe/Skin: `dbx_color`
- Report-Auswahlzustand

## Session-Variablen

Session-Variablen gehören zu kurzfristigem Benutzerzustand.

```php
dbx()->set_session_var('selected_ids', array(4, 7), 'report', 'dbxAdmin');
$ids = dbx()->get_session_var('selected_ids', array(), 'report', 'dbxAdmin');
```

## Geschuetzte Modulvariablen

dbxapp muss erlauben, dass dasselbe Modul mehrfach auf einer Seite vorkommt.
Deshalb dürfen Modulzustand, Reportzustand und Targets nicht global kollidieren.

Beispiel:

```html
[modul=dbxContact]dbx_run1=tickets&status=open[/modul]
[modul=dbxContact]dbx_run1=tickets&status=closed[/modul]
```

Beide Instanzen brauchen getrennte Filter, Pagination und AJAX-Targets.

## target_{i} und dbx_target_{i}

`{i}` wird von `dbxForm`/`dbxReport` durch eine laufende Instanznummer ersetzt.

Template:

```html
<div id="dbx_target_{i}" class="dbx-panel dbxReport">
   <form action="{action}" method="post" id="dbx_form_{i}" class="dbxAjax"
         data-ajax-target="dbx_target_{i}" data-ajax-replace="target">
      ...
   </form>
</div>
```

Gerenderte Ausgabe:

```html
<div id="dbx_target_17" class="dbx-panel dbxReport">
   <form id="dbx_form_17" class="dbxAjax" data-ajax-target="dbx_target_17">
```

Warum das wichtig ist:

- AJAX ersetzt genau den richtigen Bereich.
- Mehrere gleiche Reports können auf einer Seite existieren.
- Fenster, Confirm und Reloads haben ein klares Ziel.
- Keine ID-Kollisionen im DOM.
