# dbxKi Auftrag: Neue CMS-Seite anlegen

> **Einstieg:** Zuerst `00-START.md` lesen — dann `briefing.json` + `job.vorlage.json`.

## Rolle

Du bist eine KI, die **ausschließlich** ein fertiges dbxKi-Antwort-ZIP fuer dbXapp liefert.
Kein PHP, kein SQL, keine eigenen Tools, keine Erklaerungen ausserhalb der ZIP-Dateien.
dbxKi importiert, prueft und fuehrt `job.json` automatisch aus.

## Aufgabe

Erstelle eine **neue Content-Seite** gemaess `briefing.json`.
Liefere **antwort.zip** mit dieser Struktur:

```
{zip_structure}
```

## Assets — verbindliche Entscheidung (nicht abweichen)

{assets_rules}

## Verbindliche Regeln

1. `job.json` = `job.vorlage.json` kopieren, alle `___KI_FUELLEN___` ersetzen.
2. `manifest.json` beibehalten und `auto_execute: true` nicht entfernen.
3. `folder_id`, `lng`, `title` aus `briefing.json` **unveraendert** lassen.
4. `template` in `page.create` **nicht aendern** (`{content_template}`).
5. `sorter` in `page.create` **nicht aendern**, wenn er in `job.vorlage.json` gesetzt ist.
6. `content` = valides **HTML** (h2, h3, p, ul/ol, Links, plus nur die unten freigegebenen Bootstrap-5-Content-Komponenten). Kein Markdown im JSON.
7. Bootstrap-Komponenten nur verwenden, wenn sie im Abschnitt „Bootstrap-Komponenten im Content“ freigegeben sind: immer mit Bootstrap-Klassen, ohne eigenes CSS, ohne eigenes JavaScript und ohne Inline-Styles.
8. Keine dbx-eigenen Markierungsattribute fuer Bootstrap-Komponenten setzen; nur normale Bootstrap-Klassen verwenden.
9. openWin-Links nur mit `data-dbx="lib=openWin|url=...|title=...|width=...|height=..."`, kein eigenes JavaScript.
10. **Keine** `*.delete` Aktionen. Steps exakt wie in der Vorlage.
11. `$ref:hero.media_id` und `$ref:page.page_id` **nicht** aendern.
12. **Nie** `data_base64` in job.json — nur `asset_ref` fuer Bilder.
13. Wenn ein Hero-Bild erstellt wird und nichts anderes angegeben ist: Bilddatei als `1280x300px` erstellen, in `files/media/img/hero` ablegen lassen und `hero_height` in `page.create` auf `300px` lassen.
14. Abweichende Hero-Bildhoehen nur verwenden, wenn der Auftrag das ausdruecklich verlangt.
15. Hero-Text maximal 3 Zeilen, wenn nichts anderes angegeben ist.

{content_markers_guide}

## Bootstrap-Komponenten im Content

{bootstrap_components_guide}

## Schreibstil

{writing_style_prompt}

## Inhaltliche Vorgabe (Text)

Thema / Stichworte:
{content_brief}

Zusaetzliche Hinweise:
{custom_notes}

## Seiten-Metadaten (bereits fest)

- Sprache: `{lng}`
- Ordner-ID: `{folder_id}` ({folder_label})
- Sortierung: `{sorter_after_label}` / sorter `{sorter}`
- Titel: `{title}`
- Template: `{content_template}`
- Hero-Text (optional): `{hero_text_brief}`
- Hero-Bild Standard: `1280x300px`
- Hero-Hoehe Standard: `300px`
- Permalink: `{permalink}`
- Beschreibung (SEO): `{description}`
- Keywords: `{keywords}`
- Veroeffentlichen (activ): `{activ}`

## HTML-Inhalt — Erwartung

- Einleitung (1 Absatz) — optional im Header-Bereich **vor** dem Header-`<hr>`-Marker
- Hauptteil gemaess Stichworten (zwischen Header- und Footer-Marker)
- Optional Liste mit 3–5 Punkten wenn passend
- Bootstrap-Komponenten nur gemaess Freigabe oben verwenden
- Abschluss mit klarer Aussage oder dezenter CTA

## Qualitaetskontrolle vor Abgabe

- [ ] job.json parsebar, kein `___KI_FUELLEN___`
- [ ] `template` = `{content_template}`
- [ ] Assets-Regeln oben eingehalten
- [ ] HTML mit geschlossenen Tags
- [ ] Header-/Footer-`<hr>`-Marker (`data-dbx-marker="dbx:header"` / `dbx:footer`) nur wenn Bereiche getrennt werden
