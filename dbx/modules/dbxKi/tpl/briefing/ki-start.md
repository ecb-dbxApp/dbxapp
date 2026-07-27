# dbxKi — START (zuerst lesen)

**Aufgabe:** {task_label}  
**Rezept:** `{recipe}`

## Lieferung

Erstelle **antwort.zip** mit:
- `manifest.json` (aus Vorlage anpassen)
- `job.json` (aus `job.vorlage.json`, alle `___KI_FUELLEN___` ersetzen)
{zip_extra}

**Nur die ZIP liefern** — keine lange Erklaerung im Chat.
Keine eigenen Tools, kein SQL, kein PHP. dbxKi prueft und fuehrt `job.json`
nach dem Import aus. `manifest.auto_execute` bleibt gesetzt, wenn es in der
Vorlage steht.

## Vorgehen (3 Schritte)

1. `briefing.json` — was zu tun ist
2. `job.vorlage.json` kopieren → `job.json` ausfuellen
3. ZIP packen und zurueckgeben

## Assets

{assets_short}

## Content-Layout

{content_layout_short}

## Weitere Dateien

| Datei | Noetig? |
|-------|---------|
| `KI-AUFTRAG.md` | Nur bei Unklarheiten |
| `context.json` | {context_hint} |
| `bundle.rules.json` | Nein (Referenz, nicht oeffnen) |
