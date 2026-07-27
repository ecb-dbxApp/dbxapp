# dbxKi Auftrag: Bestehende CMS-Seite aendern

> **Einstieg:** Zuerst `00-START.md` lesen — dann `briefing.json` + `job.vorlage.json`.

## Rolle

Du lieferst ein fertiges dbxKi-Antwort-ZIP zur **Aktualisierung** einer bestehenden Seite.
Kein PHP, kein SQL, keine eigenen Tools. dbxKi importiert, prueft und fuehrt `job.json` automatisch aus.

## Aufgabe

Aendere die Seite ID `{page_id}` (`{page_title}`) gemaess `briefing.json`.
Der bisherige Inhalt steht in `context.json` unter `current_page`.

## ZIP-Struktur (Antwort)

```
{zip_structure}
```

## Assets — verbindliche Entscheidung (nicht abweichen)

{assets_rules}

## Regeln

1. `job.json` = `job.vorlage.json` kopieren, alle `___KI_FUELLEN___` ersetzen.
2. `manifest.json` beibehalten und `auto_execute: true` nicht entfernen.
3. Nur Felder aendern, die in `briefing.change_fields` stehen.
4. Neuer HTML-Inhalt ersetzt `content` vollstaendig, sofern `content` in change_fields.
5. Steps **nicht** hinzufuegen oder entfernen — exakt wie in der Vorlage.
6. Kein `page.delete`. **Nie** `data_base64` — nur `asset_ref` falls Hero-Step vorgesehen.
7. Bestehende Medien- und Modul-Einbettungen standardmaessig unveraendert lassen.
8. Hero-Auftraege strikt unterscheiden: bestehendes Hero-Bild **aendern/ersetzen** = `page.hero_replace_image` und nur vorhandene Datei ersetzen. **Neues** Hero-Bild = `page.hero_create_image`, Ablage unter `files/media/img/hero`, Medienverknuepfung aktualisieren.
9. Bootstrap-Komponenten im Content nur verwenden, wenn sie im Abschnitt „Bootstrap-Komponenten im Content“ freigegeben sind.

## Eingebettete Medien und Modul-Aufrufe

{embedded_policy}

Aktuell erkannt:

```
{embedded_summary}
```

Der vollstaendige Rohinhalt steht in `context.json` unter `current_page.content`.
Eine gerenderte Textreferenz steht unter `current_page_context.rendered_text_excerpt`.
Die Seite kann in dbx auch ueber diese Einbettung gerendert werden:

```
[modul=dbxContent]dbx_run1=show&cid={page_id}[/modul]
```

## Schreibstil

{writing_style_prompt}

## Gewuenschte Aenderung

{change_brief}

Zusaetzliche Hinweise:
{custom_notes}

## Bisheriger Seiteninhalt (Referenz)

```
{current_content_excerpt}
```

{content_markers_guide}

## Bootstrap-Komponenten im Content

{bootstrap_components_guide}

## Feste Werte

- Seiten-ID: `{page_id}`
- Sprache: `{lng}`
- Permalink (beibehalten): `{permalink}`
