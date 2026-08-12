# dbxapp Runtime- und Architekturmodernisierung

## Geltungsbereich

Dieser Entwicklungsstand wird als neues System betrieben. Es gibt keine
Kompatibilitäts- oder Datenbankmigrationen für ältere Installationen. Aktuelle
Inhaltsdaten, Benutzerkonten und transaktionale Update-Rollbacks bleiben davon
unberührt.

## Requestzustand und Lebenszyklen

`dbxRequestContext` hält Routing-, Darstellungs- und Modulwerte ausschließlich
für den aktuellen Request. Solche Werte werden nicht in `$_SESSION` geschrieben.
Dauerhafte Benutzereinstellungen verwenden weiterhin die Remember-/Session-API.

`dbxApi::get_system_obj()` unterscheidet zwei Lebenszyklen:

- requestweite Services: `dbxDB`, `dbxDD`, `dbxTPL`, `dbxValidator`,
  `dbxActionManifest` und weitere zustandsarme Infrastruktur;
- neue Instanz pro Verwendung: `dbxForm`, `dbxReport`, `dbxView`, `dbxProcess`.

Formulare und Reports dürfen deshalb niemals Zustand eines vorherigen Builders
übernehmen. DD verwendet per Komposition exakt die zentrale dbxDB-Instanz und
damit denselben Verbindungspool.

## Deklarative Aktionen

Modulrouten liegen in validierten Manifesten unter `cfg/*.php`. Jede Definition
enthält mindestens:

- `handler`
- `methods`
- `groups`
- `mutation`
- `response`

Aktuell sind die CMS-, Shop-Admin- und Schema-Dispatcher mit insgesamt 78
Routen ausgelagert. Neue Routen werden zuerst im Manifest definiert; parallele
Switch-, Token- und Response-Listen sind nicht zulässig.

## Templates, Interpreter und CSS

Der dbxTPL-Rohcache ist requestlokal. Ein Cachetreffer liest und hasht die Datei
nicht erneut. Ein Editor, der im selben Request eine Templatequelle schreibt,
ruft danach `clear_raw_cache()` auf.

Der Interpreter ersetzt alle auflösbaren Modulmarker eines Durchlaufs gemeinsam,
begrenzt rekursive Ausgaben auf 32 Durchläufe und restauriert anschließend den
äußeren RequestContext.

Jedes Design enthält seine vollständige CSS-Systembasis. Es gibt keine
Laufzeitimporte aus einem anderen Design oder aus `dbx/design/shared`.
Gemeinsame Klassennamen bilden den stabilen Komponentenvertrag; Darstellung
und Weiterentwicklung bleiben pro Design eigenständig.

## Form und Report

Die öffentliche API bleibt kompatibel. Intern sind erste Verantwortlichkeiten
abgetrennt:

- `dbxFormValueResolver`: Herkunft, Priorität, Validierung und opt-in
  Normalisierung eines Feldwerts;
- `dbxReportDataWindow`: seiteneffektfreie Auswahl des sichtbaren Datenfensters.

Neue Form-/Reportlogik soll als kleine Komponente ergänzt werden, wenn sie weder
öffentlichen Builderzustand noch Rendering und Datenzugriff gleichzeitig
benötigt.

## SelfTest

Der vollständige Lauf startet mit:

```text
C:\xampp\php\php.exe tools\ci.php
```

Die Testmetadaten stehen in
`dbx/modules/dbxSelfTest/cfg/test-metadata.php`. Tier, Timeout, Isolation und
Ressourcen werden dort explizit beschrieben. Der vertikale Kernfunktionstest
prüft dbx(), RequestContext, Lebenszyklen, Aktionsmanifest, dbxTPL, dbxForm,
dbxReport, DD, FD, dbxDD-Komposition und dbxDB gegen einen stabilen Ergebnis-Hash.

Wichtige gezielte Verträge:

- `dbxRequestContext_test.php`
- `dbxActionManifest_test.php`
- `dbxInterpreter_pipeline_test.php`
- `dbxSharedDesignCss_test.php`
- `dbxFormReportComponents_test.php`
- `dbxDD_composition_test.php`
- `dbxModuleDecomposition_contract_test.php`
- `dbxCoreFunctional_test.php`

UI-relevante Änderungen werden zusätzlich in allen Designs und den vorgesehenen
Desktop-/Tablet-/Mobilbreiten im Browser geprüft.

## Abnahme des Entwicklungsstands

Am 3. August 2026 bestanden der Kommandozeilen-Gesamtlauf mit 126/126 Tests
und der in der Oberfläche gestartete Schnelllauf mit 120/120 Tests. Die
Browserprüfung umfasste dbxapp, dbxdocs, flowers und steal bei 1440, 1024 und
390 Pixel Breite. Zusätzlich wurden der Content-Baum, der Medienbrowser, ein
Ordner mit vielen Bildern, das Anlegen und Löschen eines leeren Medienordners,
der Löschschutz eines belegten Ordners sowie die persistente mobile
Untermenünavigation geprüft.

Die sechs CMS-Kopffelder bilden bei den Breakpoints 6, 3, 2 und 1 Spalte. Eine
spezifischere Tablet-Mindestbreite darf die einspaltige Mobilregel nicht mehr
überstimmen; breite Editor-Tabellen scrollen mobil innerhalb des Editors.

Fünf lokale HTTP-Messungen pro Endpunkt ergaben als Median 577 ms für die
Startseite, 813 ms für den CMS-Einstieg, 1.521 ms für das SelfTest-Dashboard
und 422 ms für den vollständigen Content-Baum als JSON. Diese Werte dienen als
Entwicklungs-Baseline, nicht als Garantie für andere Hardware.
