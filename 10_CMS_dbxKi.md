# CMS und dbxKi {#dbxapp_cms_dbxki}

[Offizielle dbxapp Website](https://dbxapp.de)

Das Frontend von dbxapp ist normalerweise CMS-getrieben. Seiten werden über
Permalinks gefunden, aus Content-Datensaetzen gerendert und können Module
einbetten.

## CMS-Funktionalitaet

Das CMS bietet:

- Seitenbaum
- Content-Seiten
- Mehrsprachigkeit
- Medienverwaltung
- SEO-Daten
- Permalink-Index
- Content-Cache
- Modul-Inclusions
- Template-Auswahl je Inhalt
- Admin-Bearbeitung im Laufzeitkontext

## Für wen das CMS gedacht ist

Redakteure bearbeiten Seiten, Texte, Medien, SEO und Übersetzungen in einer
gemeinsamen Oberfläche. Designer stellen wiederverwendbare Content-, Hero- und
Medientemplates bereit. Entwickler ergänzen dynamische Funktionen als Module,
ohne redaktionelle Inhalte in PHP-Dateien zu verlagern.

Damit bleiben drei Ebenen getrennt:

| Ebene | Inhalt |
| --- | --- |
| Redaktion | Seitenbaum, Texte, Medien, Metadaten, Aktivstatus |
| Darstellung | Content-, Hero-, Gallery- und Medientemplates, aktiver Skin |
| Anwendung | eingebettete Module wie Kontakt, Shop, Report oder Workflow |

Eine CMS-Seite kann deshalb eine einfache Informationsseite, eine Landingpage
mit Hero und Galerie oder die redaktionelle Hülle um eine komplette Anwendung
sein.

## Module und Verantwortlichkeiten

```text
dbxContent
  Frontend-Routing, Permalink-Ausgabe und Medienauslieferung

dbxContentRenderer
  Seite, geerbte Einstellungen, Slots, Hero und Galerie rendern

dbxContentLng / dbxContentLngSync
  aktive Sprach-DD bestimmen und Sprachgeschwister synchronisieren

dbxContentPermalinkIndex
  sprachabhängige Permalink- und Home-Indizes verwalten

dbxContentPageCache
  komplette öffentliche Gastseiten lesen, schreiben und invalidieren

dbxContent_admin / dbxContent_cms
  Seitenbaum, Editor, Medien, Sprache und Einstellungen administrieren

dbxContent_seo
  SEO-Felder, Vorschau und Open-Graph-Daten pflegen
```

Die zentrale Arbeitsoberfläche ist:

```text
?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=edit
```

Sie gliedert sich in Seitenbaum, Editor und Medien-/Einstellungspanel. Die
älteren Listen- und Einzelmasken bleiben für spezielle Verwaltungsaufgaben
vorhanden; neue redaktionelle Abläufe sollen die zusammenhängende CMS-
Oberfläche verwenden.

## Datenmodell einer Seite

Content wird sprachbezogen in Tabellen wie `content_de`, `content_en` usw.
gespeichert. Die Resolverklasse `dbxContentLng` liefert die richtige DD; Code
soll keine Sprachtabelle aus einem unvalidierten String zusammensetzen.

Wichtige Seitendaten:

| Feld | Bedeutung |
| --- | --- |
| `id` | ID innerhalb der aktiven Sprache |
| `folder` | Ordner im sprachbezogenen Seitenbaum |
| `title` | sichtbarer Seitentitel |
| `permalink` | URL-Pfad innerhalb der Sprache |
| `description` | Kurzbeschreibung und Meta-Fallback |
| `keywords` | redaktionelle Suchbegriffe/Meta-Keywords |
| `seo_title` | optionaler eigener Browser-/Open-Graph-Titel |
| `meta_robots` | z. B. `index,follow` oder `noindex,follow` |
| `seo_image_id` | Open-Graph-Bild; `0` verwendet den Hero-Fallback |
| `content` | editorfähiges HTML mit CMS-Markern und Modul-Inclusions |
| `template` | Content-Layout der Seite |
| `activ` | öffentliche Freigabe |
| `group_read` | Leserechte |
| `lng_uid` | sprachübergreifende Identität |
| `lng_sync` | `auto`, `manual` oder `orphan` |
| `lng_rev` | Revision der Masterseite |
| `lng_synced_rev` | zuletzt übernommene Masterrevision |

Ordner besitzen unter anderem `parent_id`, Rechte sowie geerbte Defaults für
Contenttemplate, Hero, Galerie und Darstellung. Der Wert `parent` bedeutet,
dass die Einstellung aus dem übergeordneten Ordner übernommen wird. Dadurch
müssen Redakteure gemeinsame Layoutvorgaben nicht auf jeder Seite wiederholen.

## Redaktionsablauf

1. Zielordner im Seitenbaum wählen.
2. Seite neu anlegen oder vorhandene Seite öffnen.
3. Titel, Permalink, Beschreibung, Keywords, Template und Status prüfen.
4. Inhalt im Editor strukturieren und formatieren.
5. Hero-, Galerie- und Inline-Medien aus der Medienverwaltung zuordnen.
6. SEO-Titel, Robots und Social-Bild prüfen.
7. Sprachstatus und gegebenenfalls Sprachsynchronisation prüfen.
8. Speichern und Frontend-Vorschau kontrollieren.
9. Responsive Darstellung in Desktop-, Tablet- und Mobile-Viewport prüfen.

Das Speichern führt außerdem die CMS-Nacharbeiten aus: Content wird
normalisiert, Mediennutzung aktualisiert, Sprachrevision fortgeschrieben und
betroffene Indizes/Caches werden invalidiert. Direkte DB-Updates umgehen diese
Schritte und sind deshalb kein redaktioneller Speicherweg.

## Seitenbaum, Ordner und Vererbung

Der Seitenbaum verbindet Navigation und redaktionelle Ordnung. Ordner können
Unterordner und Seiten enthalten. Ein Verschieben ändert die Hierarchie, darf
aber nicht die sprachübergreifende Identität oder Medienzuordnungen verlieren.

Typische Vererbung:

- Leserechte und Sichtbarkeit.
- Contenttemplate.
- Hero-Template, Bild, Höhe, Variante, Sticky- und Scroll-Verhalten.
- Galerie-Template, sichtbare Anzahl, Bildgröße, Overflow und Klickverhalten.

Seiten dürfen einzelne Werte überschreiben. Ein konkreter Seitenwert gewinnt,
`parent` nutzt den nächsthöheren definierten Wert, und am Ende greift ein
sicherer Systemfallback.

## Contenttemplates und Slots

Contenttemplates liegen unter:

```text
dbx/modules/dbxContent/tpl/htm/c-*.htm
```

Beispiele:

```text
c-body1-footer.htm
c-title-hero_header-body1-footer.htm
c-title-hero_header-gallery-body1-footer.htm
c-title-hero_header-gallery-body3-footer.htm
```

Der Name beschreibt die vorhandenen Bereiche. Ein Template kann Seitentitel,
Hero, Header, Galerie, ein- bis dreispaltigen Body und Footer kombinieren. Der
Renderer füllt Marker wie `{cms:hero}`, `{cms:header}`, `{cms:col1}` und
`{cms:footer}`.

### Verbindlicher Sprachvertrag für `c-*`

`c-*`-Dateien beschreiben ausschließlich die Seitenaufteilung. Sie sind deshalb
sprachneutral und werden genau einmal im oben genannten Basisverzeichnis
geführt. Es gibt keine Dateien wie `c-body1-footer_en.htm` oder
`c-body1-footer_es.htm`.

Die aktive Sprache bestimmt den redaktionellen Datensatz, die FD-Texte und die
CMS-Bedienoberfläche. Sie darf die Liste der verfügbaren `c-*`-Layouts nicht
filtern oder verändern. Seiten- und Ordnerformular lesen daher in Deutsch,
Englisch und Spanisch dieselbe physische `c-*`-Liste. Die Liste wird pro Request
einmal ermittelt und anschließend wiederverwendet; nur die Beschriftung des
Feldes und der Eintrag `parent` werden übersetzt.

```text
Sprache de/en/es
  -> sprachabhängige Contentdaten und FD-Meldungen
  -> gemeinsame Auswahl dbx/modules/dbxContent/tpl/htm/c-*.htm
  -> ausgewähltes Layout wird sprachneutral gerendert
```

Neue Layouts werden als wiederverwendbares Template ergänzt. Redaktionelle
Seiten sollten keine eigene, seitenexklusive CSS-Anwendung nachbauen, wenn ein
Contenttemplate dieselbe Struktur abbilden kann.

## Editor und Inhaltsstruktur

Der Editor speichert HTML, aber die CMS-Marker markieren semantische Grenzen.
Beispiele sind Hero-Text, Header, Footer, Spaltentrenner und Druckseitenumbruch.
Die Marker werden im Editor sichtbar dargestellt und vom Renderer in Slots
übersetzt.

Beim Speichern gilt:

- Leere Absätze wie `<p><br></p>` werden nicht als wachsende Abstandsfolge
  vervielfacht.
- Überschriften, Absätze, Listen, Links und Ausrichtung bleiben semantisches
  HTML.
- Content ist kein Ort für Skripte, Styles oder unkontrollierte Inline-Handler.
- Modulmarker müssen syntaktisch vollständig bleiben.
- Kopierte Office-Formatierungen werden bereinigt, statt die Seite mit
  herstellerspezifischem Markup zu belasten.

## Medienverwaltung

Medien sind zentrale Datensätze in `dbxMedia`; ihre Verwendung wird über
`dbxMediaUsage` einer Seite, einem Ordner und einem Slot zugeordnet.

### Hero und Inline-Medien verbindlich trennen

Ein Bild am Seitenanfang mit darüber positioniertem Titel, Text oder
Schaltflächen ist kein frei gebauter Inhaltsblock, sondern ein CMS-Hero:

```text
Seitenfelder
  template       = Hero-fähiges c-*-Template
  hero_template  = image-hero
  hero_image_id  = ID des Hero-Mediums

Content
  Hero-Text
  <hr data-dbx-marker="dbx:hero">
  normaler Seiteninhalt

dbxMediaUsage
  Hero-Bild       slot=hero
  Bilder/Videos   slot=inline, wenn sie tatsächlich im Editor-HTML stehen
```

Der Hero wird dadurch über die vorhandene Hero-Vorschau, Höhe, Variante,
Sticky- und Scroll-Einstellungen bearbeitet. Ein Inline-Bild mit
`position-relative`/`position-absolute` als Ersatz-Hero ist nicht zulässig.

Der Bereich **Im Text** wird aus dem aktuell sichtbaren Editorinhalt gebildet.
Er zeigt deshalb nur tatsächlich eingebettete Medien. Auswahl, Bildbearbeitung
und Entfernen wirken direkt auf denselben Editorinhalt; die persistente
`dbxMediaUsage`-Zuordnung wird beim Speichern über die CMS-/dbxDB-Pipeline
synchronisiert.

`menu_title` gehört zu den Seitenfeldern neben Titel, Permalink, Template und
Status. Es wird als kurzer Navigationstitel geladen und gespeichert und ist
kein Bestandteil des freien Content-HTML.

Unterstützte Rollen:

- Hero-Medium.
- Galerie-Medium mit Sortierung.
- Inline-Bild im Content.
- Teaser- oder Footerbild.
- Video, externes Video oder Datei/Download.
- SEO-/Open-Graph-Bild.

Dateipfade werden serverseitig normalisiert. Medien werden über die
Content-Medienroute ausgeliefert, die MIME-Typ, Range-Requests, ETag,
Last-Modified und sichere Pfadgrenzen behandelt. Redakteure sollen keine freien
`files/...`-Pfade in HTML raten.

### Schnelle und responsive Bilder

Der Renderer erzeugt für Contentbilder die passenden Bildattribute. Für
Galerien und Bilder unterhalb des sichtbaren Starts wird Lazy Loading genutzt;
Hero bzw. wichtigstes Above-the-fold-Bild darf priorisiert werden. Breite und
Höhe bzw. ein stabiles Seitenverhältnis verhindern Layoutsprünge.

Grundregeln:

- moderne Webformate und vorhandene Varianten/Thumbnails nutzen,
- keine Originaldatei mit mehreren Tausend Pixeln als kleine Karte laden,
- sinnvolles `alt` aus Medien- oder Seitenkontext pflegen,
- Galerie auf Mobilgeräten einspaltig, im Querformat höchstens zweispaltig,
- externe Videos erst bei Bedarf aktiv laden.

Unbenutzte Medien werden nicht nur anhand einer einzelnen Tabelle beurteilt.
Die Medienwartung gleicht Usage, Content-Referenzen und Dateien ab und entfernt
nur wirklich verwaiste Einträge.

## SEO und Metadaten

Eine veröffentlichte Seite liefert mindestens:

- `<title>` aus `seo_title` oder `title`,
- Meta-Description aus `description`,
- Keywords, wenn redaktionell gepflegt,
- `meta_robots`,
- Canonical-/Permalink-Kontext,
- Open-Graph-Titel, Beschreibung und Bild,
- sprachbezogene Alternate-/Language-Informationen, soweit vorhanden.

Metadaten gehören zur fertigen Seite und damit auch zur vollständigen
Gast-Cachedatei. Eine Cacheausgabe darf nicht nur den Body speichern und bei
einem Hit nachträglich versuchen, Titel oder Social-Metadaten aus der DB zu
ergänzen.

## Mehrsprachige Seitenfamilien

`lng_uid` verbindet Seiten über Sprachen hinweg. Die Masterrevision steigt,
wenn relevante Inhalte geändert werden. Sprachgeschwister können automatisch
synchronisiert, bewusst manuell getrennt oder als verwaist markiert sein.

```text
Master speichern
  -> lng_rev erhöhen
  -> Sprachgeschwister und Ordnerbezug prüfen
  -> Synchronisationsdialog/Status anzeigen
  -> automatische Ziele aktualisieren
  -> manuelle Ziele als offen kennzeichnen
  -> sprachbezogene Cachedateien invalidieren
```

Eine Übersetzung erhält einen eigenen Permalink, Inhalt und Metadaten, behält
aber dieselbe `lng_uid`. Modul-Inclusions und CMS-Marker werden bei einer
Übersetzung exakt erhalten, sofern die Aufgabe nicht ausdrücklich ihre Änderung
fordert.

## Öffentliche Ausgabe und vollständiger Gast-Cache

Der Cache arbeitet für reine `GET`-/`HEAD`-Permalinkaufrufe von Gästen. Nur
eine positive Integer-ID kennzeichnet einen angemeldeten Benutzer. Edit-, AJAX-,
Fenster- und Sync-Sonderrequests werden nicht als öffentliche Seite gecacht.

```text
URL/Permalink und Sprache erkennen
  -> Cachepfad aus Permalink + Sprache + Design + Skin + Origin + Generation bilden
  -> HIT: fertige HTML-Datei bytegenau ausgeben und exit
  -> MISS: Permalink zu CID auflösen und normal rendern
  -> Design, Module, Interpreter, Metadaten und Ausgabefilter anwenden
  -> vollständige finale HTML-Antwort atomar schreiben
  -> Antwort ausgeben
```

Der Root-Aufruf ohne Parameter ist fachlich `/home` und verwendet dieselbe
Home-Cachelogik. Ein Hit benötigt keine Content-/Permalink-DB-Abfrage. Die
Datei enthält bereits `head`, Metadaten, Menüs, aufgelöste Module, Skin und
Body. Sie wird mit `file_get_contents()` unverändert gelesen – ohne Escaping
oder erneute Interpretation.

Dateinamen des aktuellen Full-Page-Cache v3 folgen sinngemäß:

```text
{lesbarer-permalink}-{sha256}_{sprache}_{design}_{skin}_{origin}_{generation}_v3.htm
```

Der Hash wird aus dem vollständigen normalisierten Permalink gebildet. Dadurch
kollidieren beispielsweise `abc/def` und `abc-def` nicht. Der Origin-Schlüssel
bindet die Datei an Schema, Host, Port und Installationspfad.

Vor jedem Lesen und Schreiben muss das Dokument vollständig sein und ein
`<base href="...">` besitzen, das exakt zur aktuellen Basis-URL passt. Eine
abweichende oder beschädigte Datei wird beim Lesen gelöscht und als Cache-Miss
neu gerendert. Cachebytes werden mit `file_get_contents()` unverändert
zurückgegeben: kein Escaping, kein HTML-Parsing, keine Interpreter-Runde und
keine Session-Nachbearbeitung.

Der Konfigurationsschalter `cache_content` steuert ausschließlich das Schreiben
neuer Dateien. Vorhandene Cache-Treffer bleiben auch bei ausgeschaltetem
Schreiben lesbar. Inhalte werden beim normalen Speichern über eine neue
Cache-Generation invalidiert; alte Requests können danach weder alte Dateien
ausliefern noch in die neue Generation zurückschreiben.

Nicht gespeichert werden fehlerhafte Antworten, unvollständiges HTML,
Antworten mit unpassendem `base href`, geschützte Eingabeformulare oder nicht
eindeutig bestimmte Requests. `HEAD` verwendet dieselben Header wie `GET`,
sendet bei Hit und Miss aber keinen Body. Ein `HEAD`-Miss darf vollständig
rendern und den Cache für einen folgenden `GET` vorbereiten.

## Rechte und Veröffentlichung

- `activ` steuert die Freigabe einer Seite.
- `group_read` begrenzt die Lesbarkeit.
- Ordnerrechte können vererbt werden.
- Adminaktionen bleiben dem Content-Adminmodul vorbehalten.
- Eine bekannte CID oder Medien-ID allein ist keine Berechtigung.
- Cache wird nur für tatsächlich öffentliche Gastantworten geschrieben.

## Redaktions-Prüfliste

- Titel, Permalink, Beschreibung, Keywords und Robots sind sinnvoll.
- Seitentemplate passt zu den tatsächlich verwendeten Slots.
- Hero und Galerie besitzen passende Medien, Alt-Texte und Größen.
- Links und Modul-Inclusions funktionieren.
- Sprachgeschwister und Sync-Status sind korrekt.
- Desktop, Tablet, Mobil-Hochformat und Mobil-Querformat sind geprüft.
- Frontend zeigt keine Editor-Leerzeilen oder leeren Absätze.
- Nach dem Speichern wird eine alte Gast-Cachedatei nicht weiter ausgeliefert.
- Social-/SEO-Metadaten stimmen auch bei einem Cache-Hit.

## Content-Ablauf

```text
URL-Kontext (Root wird home)
  -> Sprache und Skin bestimmen
  -> bei Gast: vollstaendigen Seiten-Cache pruefen
     -> HIT: fertiges HTML ausgeben und Request beenden
     -> MISS: Content ueber Permalink aufloesen
  -> Content-Template und Medien laden
  -> Modul-Inclusions interpretieren
  -> Metadaten, Menues, Skin und Ausgabefilter anwenden
  -> bei cachefaehigem Gast-MISS: finale HTML-Antwort speichern
  -> HTML ausgeben
```

@image html dbxapp-cms-ki-flow.svg "CMS- und dbxKi-Ablauf"

## Modul-Inclusion im CMS

Content kann Module enthalten:

```html
[modul=dbxContact]dbx_run1=tickets[/modul]
```

Mit Parametern:

```html
[modul=dbxContent]dbx_run1=show&cid=17[/modul]
```

Der Interpreter führt diese Inseln aus. Im Template-Editor bleiben sie Rohtext.

## Content-Bereiche und Spalten

Das CMS kann Content mit einfachen Markern in Bereiche aufteilen. Ein Template
entscheidet, welche Slots existieren:

```html
<section class="cms-hero {cms:hero_class}" style="{hero:style}">
   <div class="hero">{cms:hero}<div class="hero-text">{cms:hero_text}</div></div>
</section>

<section class="cms-header header">{cms:header}</section>

<section class="cols cols-{cms:cols}">
   <div class="col col-1">{cms:col1}</div>
   <div class="col col-2">{cms:col2}</div>
   <div class="col col-3">{cms:col3}</div>
</section>

<footer class="footer">{cms:footer}</footer>
```

Im Editor trennt der Inhalt Bereiche über einfache Marker, z.B.:

```html
<hr class="dbx-cms-marker dbx-cms-marker-header"
    data-dbx-marker="dbx:header" data-label="Header">

<hr class="dbx-cms-marker dbx-cms-marker-footer"
    data-dbx-marker="dbx:footer" data-label="Footer">
```

Text vor, zwischen und nach diesen Markern wird vom CMS-Renderer in die
passenden Slots gelegt. Spalten entstehen über Templates mit `{cms:col1}`,
`{cms:col2}` und `{cms:col3}`. Dadurch braucht der Content keine eigene
PHP-Logik, um mehrspaltig oder mit Header/Footer dargestellt zu werden.

## Mehrsprachigkeit

Content kann pro Sprache geführt werden. Je nach Modulstruktur werden
Sprachfelder, eigene Tabellen oder Verknüpfungen über `lng_uid` verwendet.

Wichtige Punkte:

- Sprache wird über `dbx_lng` und Remember-State geführt.
- Permalinks müssen sprachbezogen eindeutig sein.
- Cache muss bei Content-Änderungen invalidiert werden.

## Content-Cache

Der Seiten-Cache beschleunigt öffentliche Permalink-Seiten einschließlich des
parameterlosen Root-Aufrufs. Er speichert nicht einzelne Content-Fragmente,
sondern die vollständige, bereits interpretierte HTML-Antwort des Gasts. Dazu
gehören auch Menüs, Skin, Titel, SEO-/Social-Metadaten und aufgelöste Module.
Permalink- und Home-Indizes sind davon getrennte Auflösungsstrukturen.

Die verbindliche Reihenfolge, Cachegrenzen und Invalidierungsregeln stehen im
Abschnitt „Öffentliche Ausgabe und vollständiger Gast-Cache“ weiter oben.

## Content-Template direkt bearbeiten

Neben dem Feld **Content-Template** zeigt das CMS für ein ausgewähltes
`c-*`-Template ein Stift-Symbol. Der Ablauf ist bewusst fest:

```text
Stift anklicken
  -> confirm.js zeigt genau einen Warnhinweis
  -> Abbrechen: kein Fenster, keine Änderung
  -> Ja: ajax.js/openWin lädt dbxEditor
  -> ACE bearbeitet dbx/modules/dbxContent/tpl/htm/{template}.htm
```

Das Editorfenster darf niemals vor der Bestätigung geöffnet werden. Der Dialog
weist darauf hin, dass jede Seite betroffen ist, die dasselbe Content-Template
verwendet. `data-dbx`, `dbxConfirm` und `dbx-win` werden an diesem kombinierten
Ablauf nicht parallel auf demselben Link eingesetzt; der CMS-Handler sequenziert
Confirm und Editor eindeutig.

## Permalink-Vertrag

Neue CMS-Permalinks sind flach und ordnerunabhängig. Erlaubt sind nur
Kleinbuchstaben, Zahlen und einzelne Bindestriche:

```text
help-dashboard-admin     gueltig
cms-felder-editor        gueltig
ordner/seite             ungueltig
Seite mit Leerzeichen    ungueltig
```

Automatisch erzeugte Permalinks verwenden `-` und niemals `/`. Die
`dbxValidator`-Regel `permalink` sowie `dbxContent_permalink::isValid()` werden
beim Erkennen und Speichern verwendet. Weil der Ordner kein Bestandteil des
Permalinks ist, bleibt der Link beim Verschieben einer Hilfe- oder Inhaltsseite
stabil.

## dbxKi

`dbxKi` ist die KI-Schnittstelle für CMS- und Modulaufgaben. Sie soll
strukturierte Vorschläge liefern, aber nicht eigenmaechtig die dbxapp-Regeln
umgehen.

Mögliche Aufgaben:

- Content-Entwurf
- SEO-Texte
- Übersetzung
- Modul-Briefings
- Strukturvorschläge für Seiten
- Erklärung vorhandener Templates

## Visuelle CMS-Nutzung mit dbxKi

dbxKi ist nicht nur eine technische Schnittstelle. Es ist auch für die
menschenfreundliche Arbeit im CMS gedacht. Der Benutzer soll im CMS geführt
werden: Seite auswaehlen, Aufgabe beschreiben, Kontext bereitstellen,
Vorschlag prüfen und erst danach speichern.

Ein typischer Ablauf für einen Redakteur:

```text
CMS oeffnen
  -> Seite, Sprache und Template sehen
  -> Aufgabe für dbxKi formulieren
  -> Vorschlag/Preview anzeigen
  -> Änderungen visuell prüfen
  -> ausfuehren und Content speichern
  -> Cache/Permalink-Index aktualisieren
```

Der Vorteil ist, dass der Benutzer nicht mit Datenbanktabellen, IDs oder
internen Speicherformaten arbeiten muss. Die Oberfläche zeigt die Seite und
die relevanten CMS-Informationen. dbxKi liefert dazu einen strukturierten
Vorschlag, der über die vorhandenen dbxapp-Wege ausgeführt wird.

Wichtig ist die Trennung:

- Der Mensch entscheidet, welche Seite, Sprache und Aufgabe gemeint ist.
- dbxKi erzeugt Vorschläge, Jobs oder API-Aktionen.
- dbxapp speichert nur über die vorhandenen CMS-, Form- und DB-Pipelines.

Dadurch bleibt die Arbeit geführt und nachvollziehbar. Auch umfangreiche
Aufgaben wie neue Seite anlegen, Seite übersetzen, SEO-Texte verbessern oder
Hero-Bild zuordnen bestehen aus wenigen Schritten.

## Direkter KI-Zugriff für Codex und Cursor

KI-Werkzeuge wie Codex oder Cursor können direkt auf eine lokale dbxapp-
Installation zugreifen, wenn sie Zugriff auf den Projektordner und/oder den
lokalen HTTP-Endpunkt haben. Dann ist kein manuelles Hochladen und Herunterladen
einer ZIP-Datei noetig.

Der direkte Weg läuft über das Modul:

```text
?dbx_modul=dbxKi&dbx_run1=api
```

Die KI ruft zuerst `system.describe` auf. Danach kennt sie die erlaubten
Aktionen, Pflichtparameter, Sprachen, Tokens und Beispiel-Requests. Sie darf
CMS-Daten dann nicht direkt per SQL ändern, sondern nutzt die von dbxKi
bereitgestellten Aktionen.

Empfohlener Ablauf für Codex/Cursor:

```text
system.describe
  -> cms.snapshot, folder.list, page.list oder page.get
  -> Änderung als preview planen
  -> Preview-Ergebnis auswerten
  -> execute_request aus Preview senden
  -> Ergebnis pruefen
```

Beispiel für den ersten API-Aufruf:

```json
{
  "action": "system.describe"
}
```

Beispiel für eine kontrollierte Aktualisierung:

```json
{
  "action": "page.update",
  "mode": "preview",
  "params": {
    "lng": "de",
    "id": 12,
    "patch": {
      "title": "Neuer Seitentitel"
    }
  }
}
```

Die Preview liefert einen ausfuehrbaren Request. Dadurch muss die KI nicht
raten, welche Parameter beim Speichern notwendig sind. Der Ablauf ist geführt:
erst lesen, dann planen, dann ausführen.

## ZIP-Bundle für KI ohne direkten Zugriff

Der ZIP-Weg ist für Situationen gedacht, in denen die KI keinen direkten
Zugriff auf die Installation hat, zum Beispiel bei einem externen Chat-System.
Dann exportiert der Mensch ein Briefing, die KI erzeugt daraus ein Antwort-
Bundle und der Mensch importiert dieses Bundle wieder in dbxapp.

```text
Briefing-ZIP exportieren
  -> KI erstellt job.json und optionale assets/
  -> Antwort-ZIP importieren
  -> dbxKi prueft Job und zeigt Preview
  -> Mensch fuehrt aus
```

Bei Codex oder Cursor auf demselben Rechner ist dieser ZIP-Umweg normalerweise
nicht notwendig. Dort kann der direkte API- oder Projektzugriff benutzt werden.
Der ZIP-Weg bleibt trotzdem wichtig, weil er einen sicheren Austauschweg für
Installationen ohne gemeinsamen Dateisystem- oder HTTP-Zugriff bietet.

## dbxKi-Ablauf

```text
Briefing
  -> Kontext sammeln
  -> Preview erzeugen
  -> Mensch prueft
  -> Execute/Speichern
  -> Cache/Index aktualisieren
```

KI-Aktionen sollen preview-faehig sein. Direkte irreversible Aktionen ohne
Benutzerkontrolle sind zu vermeiden.

## Content mit dbxKi bearbeiten: menschliche Sicht

Der Mensch arbeitet immer aus der dbxapp-Oberfläche heraus. Er muss keine
Datenbanktabellen, Dateipfade oder internen Speicherformate kennen.

Typische Einstiege:

- Neue CMS-Seite: `?dbx_modul=dbxKi&dbx_run1=briefing_page_create`
- Bestehende CMS-Seite ändern: `?dbx_modul=dbxKi&dbx_run1=briefing_page_update`
- CMS-Seite übersetzen: `?dbx_modul=dbxKi&dbx_run1=briefing_page_translate`
- Import fertiger KI-Antwort: `?dbx_modul=dbxKi&dbx_run1=bundle`

Der menschliche Ablauf für eine Content-Änderung:

```text
dbxKi-Briefing oeffnen
  -> Aufgabe waehlen: neu, aendern oder uebersetzen
  -> Seite, Sprache, Zielordner und Template auswaehlen
  -> zu aendernde Felder festlegen
  -> Medien- und Hero-Regeln festlegen
  -> erlaubte Bootstrap-Komponenten auswaehlen
  -> Auftragstext schreiben
  -> KI-Auftrags-ZIP exportieren oder direkten API-Weg nutzen
  -> Antwort-ZIP importieren
  -> dbxKi prueft manifest.json und job.json
  -> Preview pruefen
  -> ausfuehren
  -> CMS-Seite im Editor und Frontend kontrollieren
```

Wichtig für den Redakteur:

- Der Mensch entscheidet, welche Seite, Sprache und Aufgabe gemeint ist.
- Die Auswahl der Bootstrap-Komponenten ist Teil des Auftrags. Nur die im
  Briefing ausgewählten Komponenten dürfen in den Content eingebaut werden.
- Medien werden über die Medienverwaltung erzeugt oder zugeordnet, nicht über
  manuelle `files/media/...`-Pfade im HTML.
- Hero-Bilder sind kein normaler HTML-Inhalt. Sie werden über die Hero- oder
  Medien-Aktionen von dbxKi ersetzt oder neu angelegt.
- Der Import führt nicht blind Fremdcode aus. dbxKi validiert den Job und
  benutzt die vorhandenen CMS-Pipelines.
- Nach dem Ausfuehren werden Cache, Permalink-Index und Medienzuordnungen über
  die dbxapp-Wege aktualisiert.

## Content mit dbxKi bearbeiten: KI-Sicht

Die KI denkt nicht über die komplette dbxapp-Implementierung nach. Sie liest
den Auftrag und fuellt das feste Antwortschema. Die Ausführung macht dbxKi.

Die KI liest im Auftrags-Bundle mindestens:

- `KI-AUFTRAG.md`
- `briefing.json`
- `context.json`
- `job.vorlage.json`
- `manifest.json`
- optionale Medien- oder Beispiel-Dateien unter `assets/`

Verbindlicher KI-Ablauf für CMS-Content:

```text
Auftrag lesen
  -> briefing.json und context.json auswerten
  -> erlaubte Felder, Sprache und Seiten-ID uebernehmen
  -> erlaubte Bootstrap-Komponenten beachten
  -> job.vorlage.json nach job.json kopieren
  -> alle ___KI_FUELLEN___ ersetzen
  -> nur erlaubte actions verwenden
  -> manifest.json beibehalten
  -> optionale assets/ passend referenzieren
  -> antwort.zip mit manifest.json, job.json, README.md und assets/ liefern
```

Erlaubte Content-Aktionen werden von dbxKi vorgegeben, zum Beispiel:

- `page.create`
- `page.update`
- `translation.apply`
- `page.hero_replace_image`
- `page.hero_create_image`
- `media.create_base64`
- `media.create_image_variant`
- `media.assign`

Regeln für die KI:

- Kein SQL, kein PHP, keine direkten Datei- oder Datenbank-Änderungen.
- Keine eigenen Tools oder Nebenprozesse für die Ausführung erfinden.
- `job.json` ist die einzige fachliche Antwortstruktur für dbxKi.
- IDs, Sprache, Template, Ordner und bestehende Content-Felder aus dem Kontext
  nicht frei raten.
- Felder, die nicht im Auftrag stehen, unverändert lassen.
- Content ist HTML, nicht Markdown.
- Modul-Inclusions wie `[modul=...]...[/modul]` exakt erhalten, ausser der
  Auftrag fordert die Änderung ausdrücklich.
- Bootstrap-Komponenten nur verwenden, wenn sie im Briefing erlaubt wurden und
  für Jodit/CMS sinnvoll nutzbar sind.
- Inline-Medien nach `media.create_*` über die von dbxKi gelieferten
  `inline_src`- oder `inline_img`-Werte einbauen.
- Neue Medien immer anschliessend mit `media.assign` zuordnen, wenn sie im CMS
  als Hero, Gallery oder Inline-Medium erscheinen sollen.
- Bei direktem API-Weg zuerst `system.describe` lesen, dann Guide/Preview
  nutzen und den gelieferten `execute_request` unverändert ausführen.

## Module mit dbxKi bearbeiten: menschliche Sicht

Neue Module werden zuerst über den Modul-Wizard angelegt. dbxKi für Module
ist für die kontrollierte Bearbeitung bestehender Module gedacht.

Typische Einstiege:

- Modul-Auftrag erstellen: `?dbx_modul=dbxKi&dbx_run1=briefing_module`
- Modul-Antwort importieren: `?dbx_modul=dbxKi&dbx_run1=module_bundle`
- Modul-API beschreiben: `?dbx_modul=dbxKi&dbx_run1=module_api&action=system.describe`

Der menschliche Ablauf für Modul-Änderungen:

```text
Modul-Wizard nutzen, falls ein neues Modul benoetigt wird
  -> dbxKi Modul-Briefing oeffnen
  -> bestehendes Modul auswaehlen
  -> optional DD auswaehlen
  -> Aufgabe und Kontextumfang festlegen
  -> Auftrag für die KI schreiben
  -> KI-Auftrags-ZIP exportieren
  -> Antwort-ZIP importieren
  -> dbxKi validiert Modulname, Pfade, Actions und Schema
  -> dbxKi erstellt vor Schreibzugriff ein Modul-Backup
  -> Preview/Import-Ergebnis pruefen
  -> Modul im Admin und Frontend testen
  -> bei schwerem Fehler Modul-Backup wiederherstellen
```

Wichtig für den Admin:

- dbxKi darf nur das ausgewaehlte Modul bearbeiten.
- Änderungen ausserhalb von `dbx/modules/{modul}/` sind nicht Bestandteil eines
  Modul-Jobs.
- DD-Dateien sind vollständig. Sie dürfen nicht von `inc`-Dateien abhaengen.
- Jede DD gehört zu ihrer eigenen DB-Tabelle.
- DD-zu-DB-Sync läuft über dbxKi und die dbxapp-Pipeline, nicht über
  manuelle SQL-Migrationen.
- Templates werden über die dbxapp-Templatewege gelesen und geschrieben. Zum
  Lesen verwendet Modulcode `dbx()->get_system_obj('dbxTPL')->get_tpl(...)`;
  Schreibvorgänge laufen über die dafür vorgesehene Editor-/dbxKi-Pipeline.
- Formulare sollen dbxForm-Konventionen nutzen.
- Reports sollen dbxReport-Konventionen nutzen, inklusive Multi-Select,
  Multi-Delete, Edit, Detail, Callback-Funktionen und Remember-State, wenn der
  Auftrag das verlangt.

## Module mit dbxKi bearbeiten: KI-Sicht

Die KI bearbeitet kein Projekt frei. Sie liefert einen Modul-Job im festen
Schema. dbxKi prüft und schreibt danach über eigene Funktionen.

Die KI liest im Modul-Auftrag mindestens:

- `KI-AUFTRAG.md`
- `briefing.json`
- `module.describe.json`
- `module.snapshot.json`
- `job.vorlage.json`
- `manifest.json`
- optionalen Modulkontext unter `module_context/`

Verbindlicher KI-Ablauf für Modul-Änderungen:

```text
Modul-Auftrag lesen
  -> ausgewaehltes Modul und optionales DD feststellen
  -> module.describe.json als API-Vertrag nutzen
  -> module.snapshot.json für Bestand und Grenzen nutzen
  -> nur erlaubte module.* actions planen
  -> job.vorlage.json nach job.json kopieren
  -> alle ___KI_FUELLEN___ ersetzen
  -> README.md mit kurzer menschlicher Zusammenfassung schreiben
  -> antwort.zip mit manifest.json, job.json, README.md und optional assets/ liefern
```

Erlaubte Modul-Aktionen werden von dbxKi vorgegeben, zum Beispiel:

- `module.backup`
- `module.file.write`
- `module.file.delete`
- `module.dd.write`
- `module.dd.sync`
- `module.template.set`
- `module.asset.write`

Regeln für die KI:

- Niemals ausserhalb des ausgewählten Moduls schreiben.
- Niemals Dateien durch direkte Shell-, SQL- oder Fremdtool-Anweisungen ändern.
- Keine Migrationen oder Altlasten erzeugen, wenn die aktuelle Struktur der
  Auftrag ist.
- DD-Dateien immer vollständig liefern.
- DD-zu-DB-Sync nur mit `module.dd.sync` anfordern.
- Templates über `module.template.set` liefern, nicht durch frei erfundene
  Speicherorte.
- PHP-Code an den bestehenden Modulstil anpassen und vorhandene dbx()-,
  dbxForm-, dbxReport-, openWin-, Ajax-, Confirm- und Remember-Konventionen
  verwenden.
- UI-State, Auswahlzustaende und schnelle Ajax-Aktionen nicht an Report-HTML-
  Reloads koppeln, wenn der Auftrag JSON-only oder no-response verlangt.
- Kein globales Refactoring und keine Änderungen an Core, anderen Modulen,
  globalen Templates, Konfiguration oder Datenbanken, ausser dbxKi erlaubt die
  konkrete Aktion im Job.
- Die Antwort ist kein Patch-Text für einen Menschen, sondern ein ausfuehrbares
  dbxKi-Antwort-Bundle.

## Ziel der festen dbxKi-Pipeline

Die feste Pipeline nimmt der KI die falschen Freiheitsgrade. Die KI muss nicht
erraten, wie dbxapp intern speichert. Sie muss nur ein valides, kleines Schema
fuellen. dbxKi übernimmt Validierung, Backup, Preview, Ausführung,
Cache-Aktualisierung und die Einhaltung der dbxapp-Regeln.

## Designs mit dbxKi bearbeiten

Designaufträge verwenden eine eigene, dateibasierte Pipeline. Sie werden nicht
als CMS-Job und nicht als Modul-Job ausgeführt:

```text
?dbx_modul=dbxKi&dbx_run1=briefing_design
  -> Briefing mit Aufteilung, Menü, Branding, Footer und Responsive-Zielen
  -> vollständiges Ausgangsdesign im Auftrags-ZIP
  -> Antwort mit manifest.json und result/design/
  -> sichere Dateivorschau
  -> dbxForm-Freigabe
  -> Backup, Staging, Designvertragsprüfung, Aktivierung
```

`dbxKiDesignService` führt keinen gelieferten PHP-Code und keine freien
Aktionen aus. Bestehende Designs werden vor dem Austausch als ZIP gesichert.
Neue Designs basieren auf einer vollständigen Kopie des Ausgangsdesigns und
bleiben zur Laufzeit eigenständig.

Der vollständige Vertrag steht unter @ref dbxapp_design_studio_ki.

## dbxContent-Tutorials in Doxygen veröffentlichen

Die deutschen, aktiven Seiten im dbxContent-Ordner
`Dokumentation und Tutorials` sind die maßgebliche Quelle der
Anwenderdokumentation. Der Export liest Inhalte, Zuordnungen und Medien
ausschließlich über DD und `dbxDB`:

```text
content_de (Ordner 15)
  -> dbxMediaUsage
  -> dbxMedia
  -> docs/generated/tutorials/
  -> Doxygen
  -> <dbxapp-dokumentationsverzeichnis>/
```

Es werden keine einzelnen Tutorials von Hand ausgewählt: Alle aktiven Seiten
des Tutorialordners werden exportiert. Ebenso werden alle aktiven
`dbxMediaUsage`-Zuordnungen dieser Seiten berücksichtigt. Im Bestand vom
28. Juli 2026 sind das 18 Seiten, 302 seitenbezogene Medienzuordnungen und
131 unterschiedliche Bilddateien.

Verbindlicher Ablauf nach einer Änderung an einem Tutorial:

```console
php dbx/modules/dbxContent/tools/export_doxygen_tutorials_de.php --write
php dbx/modules/dbxContent/tools/export_doxygen_tutorials_de.php --check
doxygen Doxyfile
```

`--write` erzeugt die Doxygen-Seiten und kopiert die zugeordneten Medien in
den generierten Dokumentationsbestand. `--check` verändert nichts und meldet
fehlende Medien oder einen nicht aktuellen Export mit einem Fehlercode.

Dateien unter `docs/generated/tutorials/` werden nicht von Hand geändert.
Eine fachliche Korrektur erfolgt immer an der deutschen dbxContent-Seite und
wird anschließend erneut exportiert. Die Hierarchie und Zuordnung zu
Anwender-, Entwickler- und KI-Bereichen steht in
`docs/doxygen-navigation.dox`.

Alte, doppelt UTF-8-codierte Zeichen in PHP-Kommentaren werden ausschließlich
für die Dokumentationsausgabe durch
`docs/tools/doxygen_php_utf8_filter.php` korrigiert. Der Filter verändert
weder Projektdateien noch PHP-Strings oder ausführbaren Code.

## Regeln

- KI darf Content vorschlagen, aber Systemwege nicht umgehen.
- Modul-Inclusions müssen exakt erhalten bleiben, wenn Content übersetzt wird.
- HTML-Struktur darf nicht unkontrolliert zerstört werden.
- Speichern läuft über vorhandene Module, Forms und DB-Pipelines.
