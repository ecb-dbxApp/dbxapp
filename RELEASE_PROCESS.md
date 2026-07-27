# Verbindlicher Release-Prozess

## 0. Verbindliche Verzeichnisrollen

- `C:\xampp\htdocs\dbxapp` ist die einzige fachliche Entwicklungsquelle.
- `C:\xampp\htdocs\dbxapp-github` ist ein kontrollierter Git-/Release-Spiegel
  und kein zweiter Entwicklungsstand.
- Vor jedem Release-Commit wird zunächst ein Dry-Run ausgeführt:

  `php tools/sync-authoritative-source.php --source=C:\xampp\htdocs\dbxapp --target=C:\xampp\htdocs\dbxapp-github`

- Danach wird derselbe geprüfte Plan mit `--apply` angewendet. Der Spiegel muss
  vorher einen sauberen Git-Status haben. Release-Workflows, Security Policy
  und weitere GitHub-eigene Dateien bleiben vom Abgleich unberührt.

## 1. Entwicklung

- `main` ist jederzeit prüfbar und grundsätzlich veröffentlichungsfähig.
- Änderungen kommen über kurze `feature/...`- oder `fix/...`-Branches.
- Es gibt keinen dauerhaften `develop`- oder Release-Branch.
- Pull Requests müssen den erforderlichen Statuscheck `dbx-ci` bestehen.

## 2. Version

dbxApp verwendet `MAJOR.MINOR.PATCH`:

- `MAJOR`: inkompatible Kernel-, Modul-, DD- oder Datenmodelländerung,
- `MINOR`: kompatible neue Funktion,
- `PATCH`: Fehler-, Security- oder Dependency-Korrektur.

`VERSION`, Git-Tag und GitHub-Release müssen übereinstimmen. Entwicklungsstände
tragen `-dev` und können nicht als Release gebaut werden.

## 3. Release Pull Request

Der Release Pull Request:

1. entfernt `-dev` aus `VERSION`,
2. verschiebt `CHANGELOG.md` von `Unreleased` in die neue Version,
3. aktualisiert `UPGRADE.md` und `SECURITY.md`,
4. enthält alle erforderlichen Migrationen,
5. enthält den aktuellen Source-to-GitHub-Abgleich,
6. besteht CI und die manuelle Release-Checkliste.

## 4. Tag und Artefakt

Nach dem Merge wird der geschützte Tag `vMAJOR.MINOR.PATCH` erstellt. Der
GitHub-Workflow:

1. prüft Tag und `VERSION`,
2. installiert Composer-Abhängigkeiten reproduzierbar aus `composer.lock`,
3. führt `composer ci` aus,
4. baut das Release-ZIP aus dem frischen Checkout,
5. erzeugt ein Datei-Inventar im ZIP,
6. erzeugt SHA-256 und das stabile `update.json`,
7. erstellt einen GitHub-Release-Entwurf.

Der Entwurf wird erst nach Sichtprüfung manuell veröffentlicht.

## 5. Security Release

Security-Arbeit erfolgt in einem privaten GitHub Security Advisory. Der Fix
durchläuft dieselben Tests und wird als Patchversion veröffentlicht. Advisory
und Release werden gleichzeitig publiziert.

## 6. Nach dem Release

- ZIP herunterladen und Prüfsumme kontrollieren.
- `update.json` über die feste Latest-Release-URL abrufen und prüfen.
- Neuinstallation in einer leeren Umgebung prüfen.
- Update und Rollback von der vorherigen stabilen Version prüfen.
- Upgrade der vorherigen stabilen DB3- und MySQL-Version prüfen.
- Hauptabläufe entsprechend `RELEASE_CHECKLIST.md` testen.
- Danach `main` auf die nächste `-dev`-Version setzen.
