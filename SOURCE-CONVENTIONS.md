# dbxApp Source-Konventionen

Diese Regeln gelten für selbst entwickelte PHP-, JavaScript-, CSS- und Modul-Sourcen. Fremdcode wird nicht mechanisch umformatiert oder intern refaktoriert.

## Architektur

- Eine Datei besitzt genau eine primäre Klasse, ein Interface oder ein Trait.
- Der Dateiname entspricht dem Namen der primären Klasse beziehungsweise des Traits.
- Module besitzen ihre Fachlogik, Templates, Hilfe, JavaScript- und CSS-Dateien selbst.
- Gemeinsame Kernel-Funktionalität bleibt im Kernel beziehungsweise im Modul `dbx`.
- Gemeinsame Fachfunktionalität bleibt beim fachlich zuständigen Modul; keine bedeutungslose globale `Utils`-Sammlung.
- Große öffentliche Kernelklassen bleiben stabile Fassaden und delegieren intern an kleine, kohärente Services.
- Neue fachliche Komponenten werden als komponierte Services gebaut. Ein Trait darf nur ein eng kohärentes, größenbegrenztes Implementierungssegment einer expliziten Service-Fassade sein; Controller verwenden keine Trait-Sammlung als Ersatz für einen Service.

## PHP

- Klassen und Interfaces: `PascalCase`.
- Methoden, Properties und lokale Variablen: `snake_case`.
- Konstanten: `UPPER_SNAKE_CASE`.
- Neue eigenständige Klassen beginnen mit `declare(strict_types=1);` und verwenden sinnvolle Parameter- und Rückgabetypen.
- Bestehende dynamische dbxApp-Fassaden werden nur nach vollständiger Migration typisiert; keine Scheinsicherheit durch falsche Typen.
- Abhängigkeiten werden innerhalb größerer Services explizit über Konstruktoren oder klar benannte Factory-Methoden übergeben. `dbx()` bleibt die öffentliche dbxApp-Fassade und ist an Modul- und Request-Einstiegspunkten zulässig.
- Gruppenrechte werden ausschließlich mit der booleschen API `dbx()->has_group()` geprüft. Mehrdeutige Synonyme oder reine Weiterleitungs-Aliase gehören nicht in die öffentliche Fassade.
- Öffentliche `dbx()`-Methoden benötigen eine eindeutige Verantwortung. Semantisch gleiche Funktionen werden unabhängig von unterschiedlichem Quelltext zusammengeführt; neue Wrapper ohne zusätzliches Verhalten sind unzulässig.
- Modulcode verändert keine internen Form-/Report-Properties direkt, wenn eine öffentliche Konfigurationsmethode existiert.
- Fachmodule greifen nicht direkt auf `$_SESSION` zu. Zustand läuft über einen zuständigen Modul-State-Service oder die dbxApp Session-/Remember-API.
- Direkte Datenbanktreiber (`PDO`, `mysqli`, `SQLite3` und vergleichbare native APIs) sind in eigenen Produkt-, Modul-, KI-, Migrations- und Werkzeug-Sourcen verboten. Sämtliche Lese- und Schreibzugriffe erfolgen über `dbxDB` und eine vollständige DD; Schemaänderungen ausschließlich über die DD-Synchronisierung.
- DD/FD bleiben vollständig und direkt lesbar; sie werden nicht zugunsten abstrakter Builder versteckt.
- Eine DD-Erstellung oder DD-Änderung ist erst nach erfolgreicher DD→DB-Synchronisierung abgeschlossen. `save_dd()` schreibt die Datei, validiert den Sync-Plan und legt/ändert anschließend die Tabelle; reine Dateischreibpfade sind nicht öffentlich.
- Schreibweisen werden fachlich korrekt verwendet: `header`, `table`, `tabulator`, `separator`, `validator`, `multiple`, `success`, `responsive`, `input`.

## Formulare und Reports

- Die Form-ID ist State-Identität, nicht Templatename.
- Ein Formular besitzt normalerweise ein individuelles Haupttemplate im Modul und verwendet gemeinsame Form-Chrome-Slots.
- Ein normaler Tabellenreport verwendet das dbxReport-Standardtemplate.
- Datenquelle, Action, Record, RID, Modus und Aktionen werden über benannte APIs konfiguriert.
- Standardaktionen werden über `set_table_actions()` konfiguriert; Templates werden nicht für kleine Aktionsunterschiede kopiert.

## JavaScript

- `core.js` enthält nur Kernel und Feature-Lader.
- Globale Bibliotheken besitzen genau eine klar benannte Verantwortung.
- Reine Modulfeatures liegen unter `dbx/modules/{modul}/js/`.
- Dateien über etwa 1.500 Zeilen werden auf kohärente Verantwortungen geprüft; eine Teilung ist kein Selbstzweck.
- Features registrieren sich einmalig über den dbxApp-Featuremechanismus und unterstützen Lazy Loading.
- Keine neue allgemeine Sammeldatei für beliebige DOM-, Request- oder State-Helfer.

## Änderungen und Tests

- Jeder erfolgreich abgeschlossene logische Änderungsblock erhält genau einen verständlichen Eintrag in `dbxChangeLog` mit Datum/Uhrzeit, Akteur, Zusammenfassung, Begründung und betroffenen Ressourcen. Einzeldateien werden nicht als getrennte Änderungen protokolliert. Vorschauen, fehlgeschlagene und zurückgerollte Änderungen erhalten keinen Eintrag.
- Codex verwendet dafür `dbx/modules/dbxChangeLog_admin/tools/write-change-log.php`. dbxKi verwendet zentral `dbxKiChangeLogService::write_change_log()` und liefert die gespeicherte Change-Log-Information in seiner JSON-Antwort.
- Strukturänderung und Verhaltensänderung werden getrennt umgesetzt.
- Jede Migration erhält einen fokussierten Vertrags- oder Integrationstest.
- Nach einem abgeschlossenen Block müssen PHP-/JavaScript-Syntax, relevante Modultests und der vollständige SelfTest grün sein.
- Nach jedem Strukturblock werden Admin-Seite, Content-CMS-Formular, Content-Report und Admin-Missing-Liste mit normalem Admin-Login im Browser aufgerufen; Browser-Konsole, `files/dbxError.log` und Missing-Zähler/Zeitstempel dürfen keine neuen Fehler zeigen.
- Sinkende Architektur-Altlasten-Grenzen werden nach jedem Block im Architekturtest aktualisiert.
