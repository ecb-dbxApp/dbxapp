# dbxKi – Arbeitsanweisung für KI-Systeme

## Ziel

Verwende ausschließlich das Modul `dbxKi`, um CMS-Daten zu lesen oder zu
ändern. Erzeuge keine eigenen SQL-Abfragen und bearbeite keine SQLite-Dateien
direkt.

## Verbindlicher Standardweg

Die KI arbeitet nicht direkt an der dbxapp-Installation. Die KI erzeugt nur
JSON-Dateien und optionale Assets. dbxKi importiert, prueft und fuehrt den
Auftrag vollstaendig aus.

1. Mensch exportiert Briefing-ZIP: `?dbx_modul=dbxKi&dbx_run1=briefing`
2. KI liest `00-START.md`, `briefing.json`, `job.vorlage.json`, `context.json`
3. KI liefert `antwort.zip` mit `manifest.json`, `job.json`, optional `assets/`
4. Mensch importiert `antwort.zip` in dbxKi
5. dbxKi validiert alle Steps und fuehrt den Prozess automatisch aus, wenn
   `manifest.auto_execute=true` gesetzt ist

### Antwort-ZIP

| Datei | Pflicht | Inhalt |
|-------|---------|--------|
| `job.json` | ja | `steps[]` mit `id`, `action`, `params` |
| `manifest.json` | ja | `title`, `recipe`, `lng`, `intent`, `auto_execute` |
| `context.json` | nein | Snapshot-Hinweise, Ziel-IDs |
| `assets/` | nein | Bilder für `asset_ref` |
| `README.md` | nein | Kurzinfo für Menschen |

Minimaler Manifest-Standard:

```json
{
  "title": "Auftragstitel",
  "recipe": "page.create.v1",
  "lng": "de",
  "intent": "create",
  "area": "cms",
  "auto_execute": true
}
```

Schema: `?dbx_modul=dbxKi&dbx_run1=bundle_describe`

## Module bearbeiten und aktualisieren

Der Modulbereich wird über
`?dbx_modul=dbxKi&dbx_run1=briefing_module` geöffnet. Die Aufgabe
`Bestehendes Modul bearbeiten / aktualisieren` ist der Standard.

Jedes erzeugte Modul-Auftrags-ZIP enthält zusätzlich:

- `reference/25_Verbindliches_Modulhandbuch.md`
- `reference/myInvoices/` als ausführbare Referenz

Für Moduländerungen gelten verbindlich:

1. Fachliche Datenbankzugriffe ausschließlich über `dbxDB` und DD.
2. `create_date`, `create_uid`, `update_date`, `update_uid` und `owner`
   werden nicht manuell gesetzt.
3. `dbxTPL`, `dbxForm` und `dbxReport` werden direkt und entsprechend ihrer
   vorgesehenen Funktionalität verwendet.
4. DD-Dateien werden vollständig und direkt lesbar im dbxapp-Exportformat
   geschrieben: Abschnitte `TABLE`, `FIELDS` und `INDEXES`, darin explizite
   `$table[...]`, `$field[...]`, `$fields[]=$field`, `$index[...]` und
   `$indexes[]=$index`. Keine `$addField`-Closure, keine DD-Includes und keine
   versteckende Hilfsabstraktion.
5. Nach einem Insert übernimmt das Formular die neue RID. Weitere Submits
   müssen denselben Datensatz aktualisieren.
6. Summen und virtuelle Reportspalten werden über den
   `{fid}_next_record`-Default gebildet. Footerwerte spät per `add_rep()`
   setzen und `{rpt:colspan}` nutzen; keine reine `str_replace()`-
   Footermethode und keine unnötigen Callback-Setter erzeugen.
7. GET bleibt für Navigation erhalten. `delete` und `save` werden in den
   dbx-Aktionsparametern zusammen mit `rid` automatisch erkannt; der Link wird
   nur durch `dbx()->action_url($url)` geführt. Keine `action_routes`-
   Konfiguration für diese Standardfälle, keine manuellen Scopes und keine
   `check_action_token()`-Prüfung im Modulservice.
8. Keine Hilfsmethoden, die lediglich einen `dbx()`-Aufruf weiterreichen, und
   kein pauschales Escaping interner Werte.
9. Ajax und normaler POST müssen denselben `dbxForm`-Ablauf verwenden und
   getestet werden. Modulcode setzt keinen zusätzlichen `dbx_token`;
   `dbxForm` verwaltet den rotierenden Submit-Schutz und signiert nur eine
   automatisch erkannte `delete`-/`save`-RID-Action.
10. Mutierende `dbxReport`-Standardaktionen werden automatisch signiert.
    Im Grid die vorhandenen Routenmuster `*_grid_save`, `*_grid_insert`,
    `*_grid_delete`, `*_grid_sort` und `*_grid_sync` verwenden. Read bleibt
    tokenlos; Modulcode ergänzt und prüft keinen eigenen Token. Modul- und
    DD-Rechte bleiben in jedem Fall zusätzlich verbindlich.

Die Referenz bestimmt Architektur und Vorgehen. Das vorhandene Fachverhalten
und die bestehenden Schnittstellen des gewählten Zielmoduls bleiben
maßgeblich.

#### Referenzen zwischen Steps

```
$ref:{step_id}.{field}
```

Beispiele: `$ref:hero.media_id`, `$ref:page.page_id`

#### Medien aus assets/

Hero-Bild: JPG, **Standard 1280×300 px**. Nur bei ausdruecklicher Vorgabe abweichen; dann maximal 1280×400 px.
Wenn nichts anderes angegeben ist, wird die CMS-Hero-Hoehe mit `hero_height: "300px"` eingetragen.

#### Content-Template (Seiten mit Hero)

Standard-Template: `c-title-hero_header-gallery-body1-footer` (Hero + Gallery + Body + Footer; Gallery optional leer).

#### Bereichs-Marker im HTML (`content`)

Marker sind **`<hr>`-Elemente** (wie im CMS-Editor), in dieser Reihenfolge:

| Marker | Slot |
|--------|------|
| `data-dbx-marker="dbx:hero"` | Text **davor** → `{cms:hero_text}` |
| `data-dbx-marker="dbx:header"` | Text **dazwischen** (nach Hero) → `{cms:header}` (eigener Block vor Body) |
| `data-dbx-marker="dbx:footer"` | Text **danach** → `{cms:footer}`; dazwischen → Body |

Fehlende Marker: Bereich entfaellt, Text geht in den Body. **Keine Spalten-Marker** von der KI setzen.
Hero-Text hat ohne andere Vorgabe maximal 3 Zeilen.

#### Content ist Fliesstext, Bootstrap-Komponenten sind erlaubt

KI-Content darf semantisch strukturiert sein, aber nicht optisch layouten:

- Erlaubt: `h2`, `h3`, `p`, `ul`, `ol`, `strong`, `em`, Links, CMS-Marker und sinnvolle Bootstrap-5-Content-Komponenten.
- Sinnvolle Bootstrap-Komponenten: `alert`, `card`, `row row-cols-* g-*`, `list-group`, `badge`, `btn`, `table table-*`, `accordion`, `nav nav-tabs`, `tab-content`.
- Bootstrap-Komponenten immer mit Bootstrap-Klassen schreiben. Kein eigenes CSS, kein eigenes JavaScript, keine Inline-Styles.
- Interaktive Bootstrap-Komponenten duerfen Bootstrap-Attribute wie `data-bs-toggle`, `data-bs-target`, `aria-*`, `role` nutzen.
- Keine dbx-eigenen Markierungsattribute fuer Bootstrap-Komponenten setzen. Im Content stehen nur normale Bootstrap-Klassen; dbxapp gestaltet sie ueber den umgebenden Content-Frame.
- Nicht erlaubt: freie Layout-Wrapper ohne fachlichen Sinn, Inline-Breiten, eigene Grid-Systeme oder projektfremde CSS-Klassen.
- Mehrspaltigkeit, Header-Anordnung, Gallery-Position, Hero-Aufbau und responsive Darstellung uebernimmt das CMS-Template bzw. der Content-Renderer.
- Text bleibt normaler Fliesstext zwischen den Markern; Bootstrap-Komponenten sind fuer kurze Teaser, Nutzen-Kacheln, Paket-Kacheln, Hinweise, Tabellen, Akkordeons oder Tabs gedacht.

#### openWin-Links im Content

Wenn ein Link in einem dbxApp-Fenster geoeffnet werden soll, nutze immer `openWin` ueber `data-dbx`. Kein eigenes JavaScript:

```html
<a class="btn btn-outline-primary dbx-win"
   href="kontakt"
   data-dbx="lib=openWin|url=kontakt|title=Kontakt|width=900|height=80%|position=center-top|reload=1|minimizable=1|maximizable=1">
   Kontakt im Fenster oeffnen
</a>
```

```json
{
  "action": "media.create_base64",
  "params": {
    "file_name": "hero.jpg",
    "asset_ref": "hero.jpg",
    "title": "Hero"
  }
}
```

#### Erlaubte Schreib-Aktionen im Bundle

Alle `write`-Aktionen aus `system.describe`, **ohne** `*.delete`.

## Einstieg Live-API

Die Live-API dient dbxKi, lokalen Integrationen und Diagnose. Auch hier gilt:
keine eigenen SQL- oder Dateiaenderungen. Rufe zuerst folgende Aktion auf:

```json
{
  "action": "system.describe"
}
```

Endpunkt:

```text
?dbx_modul=dbxKi&dbx_run1=api
```

Die Antwort enthält:

- alle verfügbaren Aktionen,
- `page_workflows` mit verbindlichen Ablaeufen fuer `page.create` und `page.update`,
- Parameter und Pflichtfelder,
- zugängliche Sprachen,
- das sessiongebundene Ausführungs-Token,
- vollständige Request-Beispiele.

### Guide-Aktionen fuer Seiten

Wenn eine KI nicht sicher ist, wie eine Seite angelegt oder geaendert werden
soll, nutzt sie zuerst eine Guide-Aktion. Diese Aktionen schreiben nichts:

```json
{ "action": "page.create_guide", "params": { "lng": "de", "folder_id": 1, "title": "Neue Seite", "with_hero": true } }
```

```json
{ "action": "page.update_guide", "params": { "lng": "de", "id": 12, "change_fields": ["content", "description"], "hero_mode": "none" } }
```

Die Antwort enthaelt ein verwendbares `manifest`- und `job`-Skelett sowie die
verbindlichen Regeln fuer Medien, Templates, openWin und HTML.

## Sicherer Live-API-Ablauf

1. Arbeitskontext mit `cms.snapshot`, `folder.list`, `page.list` oder
   `page.get` lesen.
2. Schreibaktion mit `mode: "preview"` planen.
3. Plan, IDs, Sprache, Zielordner und Änderungen prüfen.
4. Die von Preview gelieferte `execute_request` senden.
5. Ergebnis anhand von `ok`, `executed`, `result` und `plan_id` prüfen.

## Vollautomatischer Ablauf

Ein Preview-Aufruf ist nicht verpflichtend. Eine KI darf direkt ausführen,
wenn der Auftrag eindeutig ist:

```json
{
  "action": "page.update",
  "mode": "execute",
  "token": "TOKEN_AUS_SYSTEM.DESCRIBE",
  "params": {
    "lng": "de",
    "id": 12,
    "patch": {
      "title": "Neuer Titel"
    }
  }
}
```

Für `page.delete`, `folder.delete` und `media.delete` muss zusätzlich
`"confirm": true` gesendet werden.

## Übersetzungen

1. `translation.preview` aufrufen.
2. Nur `title`, `description`, `keywords` und `content` übersetzen.
3. HTML-Struktur, Links, IDs, Platzhalter, Shortcodes, CSS-Klassen,
   `data-cms-media-id`, URLs und Dateipfade unverändert lassen.
4. Bereichs-Marker (`<hr data-dbx-marker="dbx:header">` / `dbx:footer`) exakt beibehalten.
5. Übersetzung über `translation.apply` speichern.

Die KI übersetzt selbst. dbxKi benötigt dafür keinen externen
Übersetzungsdienst.

## Medien

- Vorhandene Medien mit `media.list` suchen.
- Vorhandene Modulbilder mit `module.assets` suchen. Fuer Modul- oder Paket-Visualisierungen zuerst diese vorhandenen `/tpl/mod`- bzw. `/files/mod`-Bilder verwenden.
- Neue Dateien mit `media.create_base64` bereitstellen.
- Lokale Bildquellen zuschneiden, skalieren, in WebP/JPEG/PNG umwandeln oder farblich variieren immer mit `media.create_image_variant`.
  Keine eigenen Shell-/Python-/Node-Skripte fuer diese Bildaufbereitung schreiben.
- Medien mit `media.assign` einer Seite oder einem Ordner zuordnen.

### Inline-Bilder im Content

- `files/media/...` ist **kein** öffentlicher URL-Pfad. Apache liefert diese Pfade mit **403** aus.
- Nach `media.create_base64` oder `media.create_image_variant` immer `inline_src` oder `inline_img` aus dem Ergebnis verwenden.
- Korrektes Muster:

```html
<img src="index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=123" data-cms-media-id="123" alt="Beschreibung">
```

- Optional danach `media.assign` mit `slot: "inline"` und `content_id`, damit das Medium in der CMS-Liste „Im Text“ erscheint.
- `page.create` und `page.update` normalisieren vorhandene `files/media/...`-Pfade in `content` automatisch auf `dbx_mid`-URLs, wenn das Medium in `dbxMedia` registriert ist.
- Modulbilder aus `module.assets` (`dbx/modules/*/tpl/mod`, `files/mod`) dürfen direkt per `{src}` eingebunden werden — das sind **keine** CMS-Medien unter `files/media/`.

### Paket-Seiten (dbxapp-paket-*)

Bestehende Paket-Detailseiten (z. B. `dbxapp-paket-demo`) sollen das gleiche Produktbild wie die Startseiten-Card nutzen (`home-package-*.webp`).

1. `page.get` liefert bei Paket-Permalinks `package_hint` mit `media_id` und `update_patch`.
2. Produktbild in der rechten Card setzen:

```json
{
  "action": "page.update",
  "mode": "execute",
  "params": {
    "lng": "de",
    "id": 68,
    "patch": {
      "package_product_image": true
    }
  }
}
```

Optional: `package_media_id` und `package_image_alt` im `patch`. Das Modul ersetzt Modul-SVGs in der Paket-Card durch `card-img-top` mit `dbx_mid` und legt bei Bedarf `media.assign` (`slot: inline`) an.

### Hero-Bilder

- Wenn der Auftrag lautet, ein bestehendes Hero-Bild einer Seite zu aendern/anzupassen/zu ersetzen:
  `page.hero_replace_image` verwenden. dbxKi ermittelt die aktuelle Hero-Medienverknuepfung der Seite und ersetzt nur die bestehende Bilddatei. Keine neue Medienverknuepfung und kein `page.update`, ausser der Auftrag fordert zusaetzliche Seitenfelder ausdruecklich.
- Wenn der Auftrag lautet, ein neues Hero-Bild fuer eine Seite zu erstellen/zu setzen:
  `page.hero_create_image` verwenden. dbxKi legt das neue Bild verbindlich unter `files/media/img/hero` ab, registriert es als Medium und setzt es als Hero der Seite.
- Hero-Bilder werden nie in `img/ki` abgelegt. Fuer Hero ist immer `img/hero` zu verwenden; der Ordner wird bei Bedarf angelegt.
- Ein Seitenkopf mit Bild und ueberlagertem Text ist immer ein CMS-Hero. Das Bild wird ueber `page.hero_create_image`, `page.hero_replace_image` oder `media.assign` mit `slot: "hero"` gesetzt; der Hero-Text steht vor dem Marker `data-dbx-marker="dbx:hero"`.
- Ein Hero darf niemals als Inline-Bild mit `position-relative`/`position-absolute` im Feld `content` nachgebaut werden. dbxKi lehnt einen solchen Auftrag technisch ab.

### Gallery-Bilder

- Bilder, die fuer eine CMS-Gallery erstellt oder als Gallery-Medien benutzt werden, liegen verbindlich unter `files/media/img/gallery`.
- Fuer neue Gallery-Bilder `media.create_base64` oder `media.create_image_variant` mit `media_folder: "img/gallery"` verwenden und danach mit `media.assign` als `slot: "gallery"` verknuepfen.
- Wenn ein Bild gleichzeitig Hero und Gallery sein soll, nicht dasselbe Medium fuer beide Zwecke wiederverwenden: Hero-Medium bleibt in `img/hero`, Gallery bekommt ein eigenes Medium in `img/gallery`.

### Allgemeine Bilder

- Alle sonstigen Bilder, die weder Hero noch Gallery sind, liegen verbindlich unter `files/media/img/images`.
- Fuer allgemeine Content-, Paket-, Teaser- oder Illustrationsbilder `media.create_image_variant` mit `media_folder: "img/images"` verwenden.
- **Paket-Card-Bilder:** Format **360x480 px** (Seitenverhaeltnis 9:12). Einheitliche Serie:
  - Grundfarbe **Blau** (Gradient), gleiches Layout fuer alle Pakete
  - Oben links Text **dbXapp**, **X rot**
  - **Glas/Glassmorphism**-Panels
  - **Desktop + Smartphone** mit CMS-Oberflaeche
  - **KI**-Symbol sichtbar (z. B. KI/AI-Icon)
  - Kleines **Paket-Symbol** (Demo/Non Profit/Business/Intranet/Enterprise) unterscheidet die Variante
  - Nicht ueberladen, aber CMS + KI + Paketart muessen erkennbar sein
- Für Hero-Bilder den Slot `hero` verwenden.
- Vor dem Löschen alle Verwendungen mit `media.unassign` entfernen.

### Videos

- Alle lokalen Videos liegen verbindlich unter `files/media/img/video`.
- Fuer neue lokale Videos `media.create_base64` mit `media_folder: "img/video"` verwenden.
- Externe Videos, z. B. YouTube, bleiben externe Medien und werden nicht in `img/video` kopiert.

### YouTube und externe Videos

- YouTube-Aufrufe werden als externe Medien behandelt und liegen als JSON-Metadaten verbindlich unter `files/media/youtube`.
- In der Medien-DB ist der kanonische Wert `media_folder: "youtube"` und `file_path: "media/youtube/{video_id}.json"`.
- Andere YouTube-Ablagepfade sind nicht vorgesehen.

## Fehlerbehandlung

Bei `ok: 0` darf die KI die Änderung nicht als erfolgreich melden.
Sie muss `error.code`, `error.message` und `error.details` auswerten,
den Arbeitskontext erneut lesen und nur mit korrigierten Parametern
wiederholen.
