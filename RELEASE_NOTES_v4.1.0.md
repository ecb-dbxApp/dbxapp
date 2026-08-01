# dbxApp 4.1.0

Version 4.1.0 erweitert die dbxApp-4-Linie um den vollständigen System-
Selbsttest, den überarbeiteten CMS- und Medienarbeitsplatz sowie eine
sprachsichere Medienverwendung.

## CMS und Medien

- Content-Baum und Medienbrowser laden ihre Daten bedarfsgerecht; sichtbare
  Medien werden priorisiert und weitere Seiten dynamisch nachgeladen.
- Editor, Vorschau und `dbxContent` verwenden dieselben Regeln für Video-
  größe und horizontale Ausrichtung.
- Inline-Medien, Hero, Galerie und Shop werden aus den tatsächlich
  gespeicherten Quellen ermittelt.
- `dbxMediaUsage.content_lng` trennt numerisch gleiche Seiten- und Ordner-IDs
  verschiedener Sprachen zuverlässig.
- Seiten- und Sprachkopien übernehmen ausschließlich erlaubte CMS-Slots und
  niemals Shop-Zuordnungen.
- Die Shop-Synchronisierung ersetzt ihren eigenen Snapshot idempotent.
- Die Medienwartung entfernt ungültige und doppelte Nutzungen aller Slots,
  rekonstruiert fehlende Soll-Nutzungen, löscht verwaiste Vorschaubilder,
  erstellt fehlende Vorschaubilder und optimiert die Datenbank.

## Systemtest und Betrieb

- `dbxSelfTest` führt Komplett-, Schnell-, Auswahl- und Einzeltests aus und
  protokolliert PHP-, JavaScript-, Sicherheits-, Modul- und Laufzeittests.
- Lange Testläufe liefern auch bei Laufzeitgrenzen gültige JSON-Antworten und
  können fortgesetzt oder gestoppt werden.
- System-Update, Migration, Full-Page-Cache, Sitemap und Fehlressourcen-
  Erkennung wurden erweitert und mit Regressionstests abgesichert.

## Datenmigration

Die Migration `core-4.1.0-media-usage-language` synchronisiert
`dbx|dbxMediaUsage`, weist vorhandene Nutzungen einer Content-Sprache zu und
entfernt inaktive sowie exakt doppelte Altzeilen. Der Updater sichert die
betroffene Datenbank vor der Migration und führt bei Fehlern Datei- und
Datenbank-Rollback gemeinsam aus.

Nach dem Update sollte ein Administrator im CMS einmal **Medienwartung**
ausführen. Dieser Lauf gleicht die Nutzungen mit allen Content-Sprachen und
den Shop-Artikelbildern ab, repariert Vorschaubilder und optimiert die
Datenbankdatei.
