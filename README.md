# dbxApp

dbxApp ist eine modulare PHP-Anwendungsplattform für CMS, Shop, Formulare,
Reports, Workflows, Administration und KI-gestützte Arbeitsabläufe.

dbxApp 4 ist eine vollständige Neuentwicklung auf Basis der Erfahrungen mit
dem früheren dbxWebApp. Es besteht kein Vertrag für ein direktes
Datei-, Datenbank- oder In-place-Upgrade von dbxWebApp auf dbxApp 4.
Eine spätere Übernahme alter Daten wäre ein gesonderter, ausdrücklich
dokumentierter Import und niemals Aufgabe des dbxApp-Updaters.

Die zentrale Architektur besteht aus:

- `dbxDB` für sämtliche Datenbankzugriffe, Rechte, DD-Validierung und Trace,
- DD- und FD-Definitionen für Daten- und Formularverträge,
- `dbxForm`, `dbxReport` und `dbxTPL` für einheitliche Oberflächen,
- Modulen mit klar definierten Aktionen und automatischer Action-Token-Absicherung.

## Status

Dieses Repository enthält den bereinigten Stand für das öffentliche Release
der Linie 4.0. Die aktuelle Version steht in
[`VERSION`](VERSION). Versionen mit dem Suffix `-dev` sind keine Releases.

## Voraussetzungen

- PHP 8.2 oder neuer
- Composer 2
- Apache mit `mod_rewrite`
- PDO SQLite oder PDO MySQL
- HTTPS für produktive Installationen

Unterstützt ist nur eine Kombination, die in CI und in der jeweiligen
Release-Dokumentation ausdrücklich als getestet ausgewiesen ist.

## Installation aus dem Quellcode

```bash
git clone https://github.com/ecb-dbxApp/dbxapp.git
cd dbxapp
composer --working-dir=dbx install
```

Installationsbezogene Zugangsdaten werden in nicht versionierten
`cfg/config.local.php`-Dateien hinterlegt. Dazu wird die jeweilige
`config.local.example.php` kopiert, beispielsweise:

```bash
cp dbx/modules/dbx/cfg/config.local.example.php \
   dbx/modules/dbx/cfg/config.local.php
```

Mail-Empfänger und Modul-Secrets bleiben in den öffentlichen Defaults leer.
Weitere Beispiele liegen bei `dbxLogin`, `dbxContact` und `dbxDownLoad`.
`.env.example` enthält ausschließlich Variablen für einen unbeaufsichtigten
Setup-Lauf; dbxApp lädt keine allgemeine `.env`-Datei. Zugangsdaten und lokale
Konfigurationen dürfen niemals committet werden.

Anschließend wird das Verzeichnis als Webroot oder als Unterverzeichnis des
Webroots bereitgestellt. Der Setup-Dialog erzeugt die lokalen Datenbanken aus
den DD- und Installationsdefinitionen. Produktive Datenbanken sind nicht Teil
dieses Repositorys.

## Qualität prüfen

Nach `composer install` genügt ein Kommando:

```bash
composer --working-dir=dbx ci
```

Es prüft Composer, bekannte Sicherheitslücken, öffentliche Repository-Hygiene,
PHP-Syntax und die eigenständigen dbxApp-Tests.

## Verbindliche Entwicklungsregeln

- Datenbankzugriff ausschließlich über `dbxDB`.
- Datenstruktur und Rechte werden in DD-Dateien definiert.
- Formulare und Reports verwenden sprachabhängige FD-Dateien.
- Ausgabe erfolgt über `dbxTPL`, `dbxForm` und `dbxReport`.
- Standardaktionen verwenden die zentrale Action-URL-/Token-Automatik.
- Keine Modul-Wrapper, die lediglich gleichnamige `dbx()`-Funktionen aufrufen.
- Keine Datenbanken, Backups, Zugangsdaten oder Benutzerdateien im Repository.

Ausführliche Regeln stehen in [CONTRIBUTING.md](CONTRIBUTING.md) und im
[verbindlichen Modulhandbuch](25_Verbindliches_Modulhandbuch.md).

## Releases und Updates

- [Release-Prozess](RELEASE_PROCESS.md)
- [Release-Checkliste](RELEASE_CHECKLIST.md)
- [Upgrade-Hinweise](UPGRADE.md)
- [Änderungshistorie](CHANGELOG.md)
- [Security Policy](SECURITY.md)

Release-ZIPs werden ausschließlich aus einem geschützten Git-Tag gebaut.
Lokale Arbeitsverzeichnisse sind keine Release-Quelle.

Version 4.0.2 ist die erste updaterfähige Referenzversion der neuen
dbxApp-4-Linie. Ab dieser Basis prüft und installiert `dbxAdmin` spätere
stabile dbxApp-4-Releases über einen kurzen, zentralen Ablauf mit SHA-256-,
ZIP- und Dateiinventar-Prüfung, automatischer Dateisicherung und Rollback.
Prüfung, Download und Staging erfolgen mit einem Klick. Vor dem eigentlichen
Dateiaustausch kann der Administrator das vorbereitete Update installieren
oder vollständig stoppen und entfernen.
Die klare Trennung von Dateiupdate und DB-Migration ist in den
[Upgrade-Hinweisen](UPGRADE.md) dokumentiert.

## Lizenz

dbxApp steht unter der GNU General Public License, Version 2 oder jeder
späteren Version. Siehe [LICENSE](LICENSE).
