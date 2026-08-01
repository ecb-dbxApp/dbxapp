# Verbindliche Regeln für KI-Agenten {#dbxapp_ai_rules}

Stand: 2026-07-24

Dieser Bereich ist verbindlich. Eine KI, die in dbxapp arbeitet, muss diese
Regeln einhalten.

Vor einer datenbasierten Modulentwicklung ist
@ref dbxapp_module_reference vollständig zu lesen und als Golden Path zu
verwenden.

Für einzelne Fachbereiche gelten zusaetzliche, strengere Referenzen:

- Design: @ref dbxapp_design_ai_reference
- Design-Wizard und Antwort-ZIP: @ref dbxapp_design_studio_ki
- Shop: @ref dbxapp_shop_ai_reference

Vor einer Änderung in einem dieser Bereiche muss die passende Referenz
vollständig gelesen werden. Eine Bereichsreferenz ergänzt diese Regeln; sie
hebt keine projektweite Sicherheits- oder Architekturregel auf.

## Nicht anfassen

Kernel-Klassen und JavaScript-Libs sind tabu, ausser der Benutzer fordert genau
diese Änderung ausdrücklich an.

Tabu ohne ausdrückliche Anforderung:

- `dbx/include/dbxApi.php`
- `dbx/include/dbxWebApp.class.php`
- `dbx/include/dbxTPL.class.php`
- `dbx/include/dbxDB.class.php`
- `dbx/include/dbxDD.class.php`
- `dbx/include/dbxForm.class.php`
- `dbx/include/dbxReport.class.php`
- `dbx/js/lib/core.js`
- `dbx/js/lib/ajax.js`
- `dbx/js/lib/openWin.js`
- `dbx/js/lib/confirm.js`
- andere Systemlibs

Diese Dateien werden genutzt, nicht ersetzt.

## Keine neuen Wege erfinden

Verboten:

- eigener AJAX-Mechanismus neben `ajax.js`
- eigene Fensterlogik neben `openWin.js`
- eigene Confirm-Dialoge neben `confirm.js`
- direkter Browser-Storage ausserhalb von `core.js`
- direkte PDO-/SQL-Sonderwege in Fachmodulen, wenn `dbxDB` reicht
- selbstgebaute Reports mit Suche/Pagination statt `dbxReport`
- Formular-HTML komplett in PHP statt `dbxForm`/Templates
- private Modulmethoden, die lediglich `dbx()` oder `get_system_obj()`
  weiterreichen
- manuelles Setzen der von `dbxDB` verwalteten Owner-, Benutzer- und
  Zeitfelder
- DD-Felder über lokale `$addField`-Closures oder andere versteckende
  Hilfsabstraktionen definieren; verbindlich ist das explizite
  dbxapp-Exportformat mit `TABLE`, `FIELDS` und `INDEXES`
- pauschale Escape-Wrapper um normale DD-, Form- oder Reportwerte

## Standardentscheidung

| Aufgabe | Zu verwenden |
| --- | --- |
| HTML/Layout | `dbxTPL` |
| Formular | `dbxForm` |
| Liste | `dbxReport` |
| Daten | `dbxDB` |
| Struktur | DD |
| Formularsicht | FD |
| AJAX | `ajax.js` |
| Fenster | `openWin.js` |
| Confirm | `confirm.js` |
| UI-State | `core.js` |
| Modul einbetten | `[modul=...]...[/modul]` |
| CMS/KI-Änderung | `dbxKi` |

## dbxKi-Regel

CMS-Inhalte, Seiten, Medien, Übersetzungen und SEO-Daten werden von einer KI
nicht direkt in der Datenbank geändert. Eine KI nutzt dafür `dbxKi`.

Wenn Codex, Cursor oder ein vergleichbarer Agent Zugriff auf die lokale
Installation haben, ist der direkte Weg zu verwenden:

```text
?dbx_modul=dbxKi&dbx_run1=api
```

Der erste Aufruf ist `system.describe`. Danach werden erlaubte Aktionen,
Parameter, Tokens und Beispiel-Requests aus der Antwort verwendet. Der
ZIP-Bundle-Weg ist nur für KI-Systeme ohne direkten Datei- oder HTTP-Zugriff
gedacht.

## Vorgehen bei neuen Funktionen

1. @ref dbxapp_module_reference lesen.
2. Vorhandene Module und Templates suchen.
3. Routen in lesend und mutierend klassifizieren.
4. DD/FD prüfen oder modellieren.
5. Template und eindeutige `{i}`-Targets anlegen.
6. `dbxForm` oder `dbxReport` verwenden.
7. Aktionen als Templates bauen.
8. JavaScript nur über vorhandene Libs anbinden.
9. Mutierende GETs mit vorhandenem Action-Token schützen.
10. Rechte, normalen Fallback, Ajax und Mehrfachinstanzen testen.
11. `dbx()` und Systemobjekte direkt verwenden; keine Aliasmethoden ohne
    eigene Fachverantwortung.
12. Automatische `dbxDB`-Systemfelder nicht im Modul duplizieren.
13. Keine unrelated Refactors.

## Kernel- und Größenregel

Eine große Kernelklasse wird nicht allein wegen ihrer Größe aufgeteilt.
`dbxDB`, `dbxDD`, `dbxForm` und `dbxReport` bleiben die öffentlichen Fassaden.
Eine interne Extraktion erfordert einen nachgewiesenen Nutzen, kompatible API
und Regressionstests. Eine KI darf keine neuen öffentlichen Parallelfassaden
erfinden.

## Dashboard-Regel

Dashboard-Templates definieren nur Aufteilung.

Gut:

```html
<div class="dbx-admin-dashboard-slot">
   [modul=dbxAdmin]dbx_run1=sysmsg&dbx_run2=list_sysmsg[/modul]
</div>
```

Schlecht:

- Dashboard baut eigene SysMsg-Liste.
- Dashboard kopiert Reportlogik.
- Dashboard hat Sonderlogik für Collapse, obwohl Report/Utility das kann.

## dbx_edit-Regel

Der Interpreter läuft für die Webseitenausgabe auch bei `dbx_edit > 0`.
Nur im Template-Editor wird Rohtext geschützt.

## Ziel

KI soll dbxapp-Code so erweitern, als wäre sie ein vorsichtiger Entwickler im
Projekt: vorhandene Wege verstehen, kleine Änderungen machen, Systemkonzepte
respektieren.
