# dbXapp 5.0 Dokumentation {#mainpage}

**Offizielle Website:** [dbxapp.de](https://dbxapp.de)

**Dokumentationsstand:** 24. Juli 2026

dbXapp ist ein modulares PHP-System für Webanwendungen, CMS, Administration,
Datenmodelle, Formulare, Reports, Workflows und eine Runtime-IDE. Die
Dokumentation ist zugleich:

- Einstieg für Entwickler und Betreiber;
- technische Referenz der vorhandenen Fähigkeiten;
- verbindlicher Architekturvertrag für neue Module;
- Arbeitsgrundlage für KI-Agenten.

## Der wichtigste Einstieg

Wer ein Modul baut oder erweitert, beginnt mit
@ref dbxapp_module_reference. Das Kapitel zeigt einen vollständigen,
zusammenhängenden Ablauf mit:

- `dbxTPL`;
- `dbxDB` und `dbxDD`;
- DD und FD;
- `dbxForm`;
- `dbxReport`;
- `confirm.js` und `ajax.js`;
- Routen, Rechte, Action-Token und Mindesttests.

Die Einzelkapitel vertiefen danach jeweils eine Pipeline. Sie sind keine
alternativen Implementierungswege.

@image html dbxapp-request-flow.svg "Request-, Modul-, Template- und Interpreter-Ablauf"

## Architektur in einer Tabelle

| Aufgabe | Verbindliche dbXapp-Schicht |
| --- | --- |
| Request und Systemzugriff | `dbx()` / `dbxApi` |
| Fachroute | kleiner Modulrouter |
| Fachoperation | Modulservice, bei Bedarf Repository/Provider |
| Ausgabe und Layout | `dbxTPL` und `/tpl` |
| Datenzugriff | `dbxDB` |
| Schema und Transfer | `dbxDD` |
| Datenstruktur und Rechte | DD |
| Formularsicht | FD |
| Eingabe und Validierung | `dbxForm` |
| Liste, Filter und Pagination | `dbxReport` |
| Teilreload | `ajax.js` |
| Bestätigung | `confirm.js` |
| Fenster | `openWin.js` |
| UI-State | `core.js` |
| Content-Inclusion | `dbxInterpreter` |

Das System bleibt einfach, weil jedes Modul dieselben Fassaden nutzt. Große
Kernelklassen werden nicht allein wegen ihrer Zeilenzahl geteilt. Sie bilden
stabile, fähigkeitsreiche Pipelines. Fachmodule bleiben dagegen klein und
werden bei klaren Fachgrenzen in Services, Repositories oder Provideradapter
zerlegt.

## Lesepfade

### Ich möchte ein Modul entwickeln

1. @ref dbxapp_module_reference
2. @ref dbxapp_module_patterns
3. das passende Detailkapitel zu Template, Daten, Form oder Report
4. @ref dbxapp_security_integrity_performance

### Ich möchte das System verstehen

1. @ref dbxapp_system_overview
2. @ref dbxapp_routing_templates
3. @ref dbxapp_dbxinterpreter
4. @ref dbxapp_rad_runtime_ide

### Ich möchte Daten, Formulare oder Reports bauen

1. @ref dbxapp_dbxdb_dd_fd
2. @ref dbxapp_dbxform
3. @ref dbxapp_dbxreport
4. @ref dbxapp_dbxtpl
5. @ref dbxapp_javascript_libs

### Ich betreibe oder prüfe die Anwendung

1. @ref dbxapp_current_operations
2. @ref dbxapp_security_integrity_performance
3. @ref dbxapp_db_roundtrip

### Ich arbeite als KI-Agent

1. @ref dbxapp_ai_rules
2. @ref dbxapp_module_reference
3. die zusätzliche Fachreferenz des betroffenen Bereichs

## Dokumentationslandkarte

### Fundament

- @ref dbxapp_system_overview
- @ref dbxapp_routing_templates
- @ref dbxapp_dbxinterpreter
- @ref dbxapp_rad_runtime_ide
- @ref dbxapp_core_classes

### Verbindliche Modulentwicklung

- @ref dbxapp_module_reference
- @ref dbxapp_module_patterns
- @ref dbxapp_ai_rules
- @ref dbxapp_security_integrity_performance

### Kernpipelines

- @ref dbxapp_dbxtpl
- @ref dbxapp_dbxdb_dd_fd
- @ref dbxapp_dbxform
- @ref dbxapp_dbxreport
- @ref dbxapp_javascript_libs

### Betrieb und Datenbanken

- @ref dbxapp_current_operations
- @ref dbxapp_db_roundtrip

### CMS, Design und Fachanwendungen

- @ref dbxapp_cms_dbxki
- @ref dbxapp_design_themes_skins
- @ref dbxapp_design_ai_reference
- @ref dbxapp_design_studio_ki
- @ref dbxapp_shop_guide
- @ref dbxapp_shop_ai_reference
- @ref dbxapp_workflow_guide
- @ref dbxapp_workflow_create
- @ref dbxapp_workflow_use

### Herkunft und Bibliotheken

- @ref dbxapp_credits

## Normative Reihenfolge

Bei Widersprüchen gilt:

1. aktuelle Sicherheits- und Integritätsinvarianten;
2. verbindliches Modulhandbuch;
3. bereichsspezifische KI-Referenz;
4. Kernpipeline-Leitfaden;
5. allgemeines Modulpattern;
6. ältere oder rein beschreibende Beispiele.

Ein Widerspruch ist kein Anlass, einen Parallelweg zu bauen. Zuerst werden
Code, Tests und aktuelle Dokumentation gemeinsam geprüft; anschließend wird
die Dokumentation berichtigt.

## Systemweiter Entwicklungsablauf

1. Fachzweck, Benutzergruppen und Routen festlegen.
2. Ein ähnliches vorhandenes Modul lesen.
3. DD und benötigte FDs modellieren.
4. Templates und eindeutige `{i}`-Targets festlegen.
5. Kleinen Router und klaren Fachservice implementieren.
6. Daten ausschließlich über `dbxDB` lesen und schreiben.
7. Eingaben über `dbxForm`, Listen über `dbxReport` bauen.
8. Ajax, Confirm, Fenster und UI-State deklarativ an die vorhandenen Libs
   anbinden.
9. Mutierende GET-Aktionen mit dem vorhandenen Action-Token schützen; reine
   Navigation bleibt tokenlos.
10. Direkten Request, Inclusion, normalen Fallback, Ajax, Rechte,
    Mehrfachinstanzen und aktive Designs testen.
11. Doxygen und Modul-README aktualisieren.

## Unveränderliche Grundregeln

1. Kein direkter PDO-Zugriff in Fachmodulen.
2. Kein SQL und keine Fachmutation in Templates.
3. Keine großen Formular-HTML-Strings statt `dbxForm`.
4. Keine eigene Listen-, Such- oder Pagination-Pipeline statt `dbxReport`.
5. Keine zweite AJAX-, Confirm-, Fenster- oder Storage-Lösung.
6. DD ist die versionierbare Datenstruktur; FD ist eine konkrete Formularsicht.
7. Requestwerte werden validiert und Sortierungen gegen Allowlists geprüft.
8. `dbxDB` setzt Owner-, Benutzer- und Zeitfelder automatisch; Module
   duplizieren diese Automatik nicht.
9. Module verwenden `dbx()` direkt und bauen keine Aliasmethoden ohne eigene
   Fachverantwortung.
10. Normale DD-, Form- und Reportwerte werden nicht pauschal escaped.
11. `verify_access=0`, `trace=0` und Kerneländerungen sind begründete
   Infrastruktur-Ausnahmen.
12. Mehrfach verwendbare Module besitzen getrennte Parameter- und DOM-Targets.
13. GET-Kompatibilität bleibt erhalten; nur mutierende GETs benötigen den
    dokumentierten Aktionsnachweis.

## Wann Arbeit abgeschlossen ist

Eine Änderung gilt erst als fertig, wenn:

- sie die vorgesehene dbXapp-Pipeline nutzt;
- normale und Ajax-Antwort denselben fachlichen Vertrag erfüllen;
- Rechte, Validierung, Fehler- und Erfolgsmeldungen sichtbar funktionieren;
- keine direkten Datenbank- oder JavaScript-Parallelwege entstanden sind;
- Syntax-, Regression- und Browsertests bestanden sind;
- Doxygen ohne fehlende Seiten oder Referenzen gebaut wird;
- Sicherung und Rückweg bei Daten- oder Schemaänderungen dokumentiert sind.

<div class="dbx-note">
dbXapp bleibt schnell und verständlich, wenn die leistungsfähigen gemeinsamen
Pipelines konsequent genutzt werden und jedes Fachmodul nur seine eigene
Fachentscheidung ergänzt.
</div>
