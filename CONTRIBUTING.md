# Zu dbxApp beitragen

## Arbeitsablauf

1. Eine kurze Issue beschreibt Fehler, Ziel oder Erweiterung.
2. Änderungen erfolgen in `feature/...` oder `fix/...`.
3. Ein Pull Request bleibt klein und fachlich zusammenhängend.
4. `composer --working-dir=dbx ci` muss erfolgreich sein.
5. Erst nach erfolgreicher Prüfung wird nach `main` zusammengeführt.

Sicherheitslücken werden entsprechend [SECURITY.md](SECURITY.md) nicht
öffentlich bearbeitet.

## Architekturvertrag

### Datenbank

- Datenbankzugriff ausschließlich über `dbxDB`.
- Keine direkten PDO-, mysqli- oder datenbankspezifischen Modulzugriffe.
- Rechte, Owner-Regeln, Felder und Serverzuordnung gehören in die DD.
- Systemfelder wie `create_date`, `create_uid` und `owner` setzt `dbxDB`.
- Änderungen müssen mit DB3 und allen als unterstützt erklärten
  Server-Datenbanken kompatibel sein.

### Formular und Report

- Eingaben werden mit `dbxForm` und FD-Dateien aufgebaut.
- Listen und Tabellen verwenden `dbxReport`.
- Sprachabhängige Labels und Meldungen stehen in den FD-Sprachvarianten.
- DD-Dateien erhalten nur dann Sprachvarianten, wenn auch getrennte
  sprachabhängige Tabellen existieren.
- Modulcode dupliziert keine Fähigkeiten von `dbxForm` oder `dbxReport`.

### Templates und Module

- Ausgabe und Layout verwenden `dbxTPL`.
- Module rufen vorhandene Kernel-Funktionen direkt auf.
- Keine Modulmethoden, die lediglich eine gleichnamige `dbx()`-Methode
  weiterreichen.
- Bestehende GET-Navigation bleibt kompatibel.
- Zustandsändernde `save`-/`delete`-Aktionen mit `rid` verwenden die zentrale
  Action-URL- und Token-Automatik.
- Token ersetzen niemals DD-, Gruppen-, Benutzer- oder Owner-Rechte.

## Sprachen

Deutsch ist die maßgebliche Inhaltsquelle. Bei sichtbaren Texten werden die
englische und spanische Variante strukturgleich gepflegt:

- `datei.htm`
- `datei_en.htm`
- `datei_es.htm`

Für sprachabhängige FD-Dateien gilt dasselbe. Tests müssen sicherstellen, dass
Labels und Meldungen tatsächlich aus der aktiven FD-Sprachversion stammen.

## Tests

Jede Fehlerkorrektur erhält möglichst zuerst einen reproduzierenden Test.
Neue Funktionen benötigen mindestens:

- einen Contract-/Unit-Test für die zentrale Logik,
- einen Rechte- oder Token-Test bei zustandsändernden Aktionen,
- einen Integrations- oder Browser-Smoke-Test für den Hauptablauf.

Tests müssen eigenständig, wiederholbar und ohne produktive Daten ausführbar
sein. Temporäre Daten gehören in das Betriebssystem-Tempverzeichnis.

## Nicht committen

- `.env` und `config.local.php`,
- DB3-, SQLite- oder MySQL-Dumps,
- Passwörter, Tokens, Schlüssel oder Zertifikate,
- Logs, Cache, Backups und KI-Arbeitsverzeichnisse,
- Benutzeruploads oder personenbezogene Daten,
- `vendor` und erzeugte Release-ZIPs.
