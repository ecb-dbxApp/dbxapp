# dbxKi Auftrag: Seite uebersetzen oder korrigieren

> **Einstieg:** Zuerst `00-START.md` lesen — dann `briefing.json` + `job.vorlage.json` + `context.json` (Quelltext).

## Rolle

Du lieferst ein fertiges dbxKi-Bundle (ZIP) mit den Steps aus `job.vorlage.json`.

## Aufgabe

Bearbeite die Quellseite aus `briefing.json` / `context.json` von **{source_lng}** fuer folgende Zielsprachen:

{target_instructions}

## ZIP-Antwort

```
antwort.zip
├── manifest.json
├── job.json
└── README.md
```

## Assets

**Keine.** Kein `assets/` Ordner, keine Bilder, keine Medien-Steps.
Bei echten Zielsprachen kopiert dbXapp Medien beim Import automatisch, wenn `copy_media` im jeweiligen `translation.apply`-Step gesetzt ist.

## Regeln

1. `job.json` = `job.vorlage.json` kopieren — alle `___KI_FUELLEN___` ersetzen.
2. **HTML-Struktur beibehalten:** Tags, Klassen, `data-cms-media-id`, Links unveraendert.
3. **Bereichs-Marker beibehalten:** `<hr data-dbx-marker="dbx:hero|header|footer">` exakt lassen (nur Text in den Bereichen uebersetzen).
4. Nur sichtbaren Text uebersetzen — keine URLs, Modulnamen, Template-Namen.
5. `source_lng`, `target_lng`, `source_id`, `copy_media`, `lng` und `id` in den Steps unveraendert lassen.
6. Jeder ausgefuellte `title` darf nicht leer sein.
7. Wenn `target_lng` gleich `source_lng` ist: **nicht uebersetzen**. Nur Rechtschreibung, Grammatik, Zeichensetzung und klare Tippfehler korrigieren.

## Eingebettete Medien und Modul-Aufrufe

Der gerenderte Inhalt kann in dbXapp ueber `{render_reference}` angezeigt werden.

{embedded_summary}

Standard: Medien, `data-cms-media-id`, Links und `[modul=...]...[/modul]`-Aufrufe nicht entfernen, nicht ersetzen und nicht umschreiben.

## Schreibstil / Ton

{writing_style_prompt}

Zusaetzliche Vorgaben:
{translation_notes}

## Quellseite

- ID: `{source_id}`
- Titel: `{source_title}`
- Permalink: `{source_permalink}`
- Basissprache: `{source_lng}`
- Ziele: `{target_lngs}`

## Felder

| Feld | Pflicht |
|------|---------|
| title | ja |
| description | ja (kann leerer String sein) |
| keywords | ja |
| content | ja (HTML) |

## Qualitaetskontrolle

- [ ] Jeder Step in `job.vorlage.json` ist in `job.json` vorhanden
- [ ] Alle vier Felder pro Step ausgefuellt
- [ ] HTML-Struktur technisch identisch zur Quelle
- [ ] Kein `___KI_FUELLEN___` mehr vorhanden
- [ ] Kein assets/ Ordner in der ZIP
