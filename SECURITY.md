# Security Policy

## Unterstützte Versionen

Es wird ausschließlich die neueste stabile Minor-Version der aktuellen
Major-Linie mit Sicherheitsupdates versorgt.

| Version | Status |
|---|---|
| 4.0.x | unterstützt |
| 3.x und älter | nicht öffentlich unterstützt |

Der konkrete Status wird bei jedem Release aktualisiert.

## Sicherheitslücke vertraulich melden

Bitte keine öffentliche Issue für eine noch nicht behobene Sicherheitslücke
anlegen.

Bevorzugt wird GitHubs **Private vulnerability reporting** im Bereich
`Security` des Repositorys. Falls das nicht verfügbar ist, kann die Meldung
an `Armin.Braun@dbxapp.de` gesendet werden.

Eine gute Meldung enthält:

- betroffene Version und Komponente,
- nachvollziehbare Schritte oder einen minimalen Proof of Concept,
- mögliche Auswirkungen,
- bekannte Voraussetzungen für einen Angriff,
- Kontaktmöglichkeit für Rückfragen.

Keine realen Benutzerdaten, Passwörter oder produktiven Datenbanken mitsenden.

## Reaktionsziele

| Schweregrad | Erste Bewertung | Ziel für eine korrigierte Version |
|---|---:|---:|
| Kritisch | 24 Stunden | möglichst 72 Stunden |
| Hoch | 3 Arbeitstage | 7 Tage |
| Mittel | 7 Tage | spätestens 30 Tage |
| Niedrig | 14 Tage | reguläres Patch-Release |

Die Frist beginnt mit einer nachvollziehbaren Meldung. Bei komplexen Fehlern
werden Melder über den Stand informiert.

## Behebung und Veröffentlichung

Sicherheitskorrekturen werden in einem privaten GitHub Security Advisory und
dessen temporärem privaten Fork entwickelt. Nach erfolgreicher Prüfung werden
Patch-Release und Advisory gleichzeitig veröffentlicht.

Ein Security-Release dokumentiert:

- betroffene und korrigierte Versionen,
- Schweregrad und Auswirkungen,
- erforderliche Update-Schritte,
- gegebenenfalls Datenbank- oder Konfigurationsänderungen.
