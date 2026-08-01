# Design Studio und KI-Wizard {#dbxapp_design_studio_ki}

Stand: 2026-07-25

`dbxDesign_admin` ist die verbindliche Oberfläche zum Personalisieren und
Erstellen vollständiger dbxapp-Designs. Das Modul verbindet einen einfachen
Wizard mit der kontrollierten Design-Pipeline von `dbxKi`.

## Benutzerwege

### Ohne KI

```text
Design Studio
  -> Ausgangsdesign wählen
  -> neuen technischen Namen vergeben
  -> Seitenaufteilung, Menüform, Breite und Footer wählen
  -> Branding, Logo, Farben und Typografie festlegen
  -> optional als Frontend-Standard setzen
  -> im Staging erzeugen und validieren
  -> Vorschau in neuem Tab
```

Einstiege:

```text
?dbx_modul=dbxDesign_admin&dbx_run1=list
?dbx_modul=dbxDesign_admin&dbx_run1=wizard
```

Der Wizard ändert das Ausgangsdesign niemals. Er kopiert es als eigenständige
technische Grundlage, ersetzt alle harten Pfade auf das Ausgangspaket und
erzeugt anschließend die neue Schale. Dadurch bleiben die vorhandenen Styles
für dbxForm, dbxReport, CMS, Shop, Menüs, Ajax und openWin verfügbar, ohne dass
das neue Design zur Laufzeit von privaten Dateien eines anderen Designs
abhängt.

### Mit KI

```text
Design Studio oder dbxKi-Hub
  -> Design-Briefing öffnen
  -> bestehendes Design ändern oder neues Design entwickeln
  -> Ziel, Aufteilung, Menü, Branding, Footer und Mobilverhalten beschreiben
  -> KI-Auftrags-ZIP herunterladen
  -> ZIP von der KI bearbeiten lassen
  -> Antwort-ZIP hochladen
  -> Dateiliste und Status neu/geändert/unverändert prüfen
  -> Anwendung per dbxForm bestätigen
  -> Backup, Staging, Vertragsprüfung und Aktivierung
```

Einstieg:

```text
?dbx_modul=dbxKi&dbx_run1=briefing_design
```

## Warum ein neues Design immer ein eigenes Paket ist

Eine direkte Änderung von `dbxapp` oder `flowers` wäre für einen ungeübten
Benutzer zwar kurzfristig einfach, würde aber Updates, Vergleiche und
Wiederherstellung erschweren. Deshalb ist die Standardaktion
**Personalisieren** technisch eine sichere Ableitung:

- Original bleibt erhalten.
- Zielname ist eindeutig.
- keine stillen Überschreibungen;
- vollständige Paketprüfung vor Aktivierung;
- als Frontend-Standard nur nach ausdrücklicher Auswahl;
- neue Designs erscheinen automatisch in der bestehenden Designauswahl.

## Dateivertrag

Ein vom Wizard verwaltetes Design besitzt zusätzlich `design.json`:

```json
{
  "contract": "dbx.design.v1",
  "name": "meine-firma",
  "source_design": "dbxapp",
  "layout": "sidebar",
  "menu_style": "pills",
  "content_width": "wide",
  "managed_by": "dbxDesign_admin",
  "slots": ["logo", "branding", "footer"]
}
```

`design.json` ist Metadaten- und Bearbeitungskontext. Die Laufzeit bleibt
weiterhin dateibasiert und benötigt dafür weder eine Datenbank noch eine neue
Konfigurationspipeline.

Die vom Wizard geschriebenen Kern-Dateien sind:

```text
htm/default.htm
htm/logo.htm
htm/branding.htm
htm/footer.htm
css/design-custom.css
design.json
```

Alle übrigen Komponenten-Dateien stammen als unabhängige Kopie aus dem
Ausgangsdesign.

## Design-Slots

Die optionale Strukturierung erfolgt über:

| Marker | Fragmentdatei | Verantwortung |
| --- | --- | --- |
| <code>[dbx:logo]</code> | `htm/logo.htm` | Logo oder Markenzeichen |
| <code>[dbx:branding]</code> | `htm/branding.htm` | Markenname und Claim |
| <code>[dbx:footer]</code> | `htm/footer.htm` | Footer und Fenster-Dock |

`dbxTPL::replace_design_slots()` setzt nur Marker ein, die in der geladenen
Designschale vorkommen. Fehlt ein Fragment, wird der betreffende optionale
Marker leer aufgelöst. Designs ohne diese Marker bleiben byte-logisch im
bisherigen Ablauf. Fragmente dürfen weder <code>[dbx:content]</code> noch weitere
Design-Slots enthalten; die Laufzeit entfernt solche verschachtelten Marker
vorsorglich und der Designvalidator lehnt das Paket ab.

Die zentrale Erweiterung ist notwendig, weil wiederverwendbare Bereiche der
Designschale eine Runtime-Verantwortung und keine Modulverantwortung sind. Es
gibt keine zweite Template-Engine und keine Spezialbehandlung in
`dbxDesign_admin`.

## dbxForm, dbxTPL, dbxDB und dbxReport

- Der Wizard und alle Freigaben laufen über `dbxForm`.
- Seiten, Karten, Vorschau und Ergebnisse kommen aus `dbxTPL`.
- `dbxDB` wird nicht verwendet, weil Designpakete bewusst dateibasiert sind.
- `dbxReport` wird nicht zweckentfremdet: Die Designübersicht besitzt keine
  DD- oder Tabellenquelle und benötigt weder DB-Pagination noch DB-Selektion.

Damit werden die Bibliotheken nach ihrer Fachverantwortung genutzt und nicht
nur formal aufgerufen.

## KI-Auftrags-ZIP

Ein Design-Briefing enthält:

```text
00-START.md
manifest.json
briefing.json
context.json
KI-AUFTRAG.md
RESULT-FORMAT.md
source-design/{design}/...
```

Der komplette Designkontext ist enthalten. Die KI muss keine Pfade, Marker
oder Abhängigkeiten erraten. `manifest.json` kennzeichnet dieses Paket mit
dem Vertrag `dbx.design.briefing.v1` und verlangt als Antwort
`dbx.design.result.v1`.

## KI-Antwort-ZIP

Die Antwort besitzt ausschließlich:

```text
manifest.json
result/design/{neue-oder-geänderte-dateien}
```

Beispiel:

```json
{
  "contract": "dbx.design.result.v1",
  "mode": "update",
  "source_design": "meine-firma",
  "target_design": "meine-firma",
  "summary": "Header, Branding und mobile Navigation überarbeitet"
}
```

Es wird kein PHP und kein freier KI-Befehl ausgeführt. dbxKi übernimmt nur
Dateien mit erlaubten Design-Endungen. Das Ergebnis wird auf einer Kopie im
Staging aufgebaut und gegen den vollständigen Designvertrag geprüft.

## Sicherheits- und Integritätsregeln

1. Modulzugriff ist auf die Gruppe `admin` beschränkt.
2. Wizard, Upload und Freigabe verwenden `dbxForm`.
3. ZIP-Pfade mit `..`, absoluten Pfaden, Laufwerksangaben oder Nullbytes werden
   abgelehnt.
4. PHP-Dateien und unbekannte Dateitypen sind in KI-Ergebnissen verboten.
5. Inline-Handler, Inline-Skripte, externe Script-/CSS-Ressourcen, aktives SVG
   und eigener Netzwerktransport im Design-JavaScript werden abgelehnt.
6. `[dbx:content]` muss exakt einmal vorkommen.
7. dbx-Systemmarker, Skin und `core.js` müssen erhalten bleiben.
8. Update erzeugt vor dem Austausch ein ZIP unter
   `files/sys/design-backups/`.
9. Schreibzugriffe erfolgen zuerst in `files/tmp/design-admin/`.
10. Ein neues Design überschreibt kein vorhandenes Paket.
11. Die KI ändert keine Module, DDs, FDs, Datenbanken oder Rechte.

## Kompatibilitätsprüfung

Ein Design gilt erst als aktivierbar, wenn mindestens Folgendes erfüllt ist:

- `htm/default.htm` vorhanden;
- `[dbx:content]` exakt einmal;
- `{dbx:title}`, `{dbx:design}`, `{dbx:skin_css}` und
  `{dbx:skin_class}` vorhanden;
- `dbx/js/lib/core.js` eingebunden;
- alle verwendeten Design-Slots besitzen Fragmente;
- keine symbolischen Links;
- ausschließlich erlaubte Design-Dateitypen.

Zusätzlich sind im Browser zu prüfen:

- Admin-Zugriff und dbxForm-Token;
- Wizard-Schritte und responsive Darstellung;
- Vorschau für Top-, Sidebar- und Hybrid-Aufteilung;
- Haupt- und Admin-Menü;
- dbxForm, dbxReport, CMS, Shop, Ajax und openWin;
- schmale und breite Viewports;
- alle vom Ausgangsdesign angebotenen Skins.

## Verantwortliche Klassen

| Klasse | Verantwortung |
| --- | --- |
| `dbxDesignAdmin` | Routing und Benutzeroberfläche |
| `dbxDesignService` | Pfadgrenze, Erzeugung, Validierung, Backup und Aktivierung |
| `dbxKiDesignService` | Briefing-ZIP, Antwort-Import, Vorschau und Freigabe |
| `dbxTPL` | optionale Design-Slots in der Seitenschale |

## Verwandte Dokumentation

- @ref dbxapp_design_themes_skins
- @ref dbxapp_design_ai_reference
- @ref dbxapp_cms_dbxki
- @ref dbxapp_dbxtpl
- @ref dbxapp_dbxform
- @ref dbxapp_security_integrity_performance
