# Verbindlicher Release-Prozess

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
5. besteht CI und die manuelle Release-Checkliste.

## 4. Tag und Artefakt

Nach dem Merge wird der geschützte Tag `vMAJOR.MINOR.PATCH` erstellt. Der
GitHub-Workflow:

1. prüft Tag und `VERSION`,
2. installiert Composer-Abhängigkeiten reproduzierbar aus `composer.lock`,
3. führt `composer ci` aus,
4. baut das Release-ZIP aus dem frischen Checkout,
5. erzeugt eine SHA-256-Prüfsumme,
6. erstellt einen GitHub-Release-Entwurf.

Der Entwurf wird erst nach Sichtprüfung manuell veröffentlicht.

## 5. Security Release

Security-Arbeit erfolgt in einem privaten GitHub Security Advisory. Der Fix
durchläuft dieselben Tests und wird als Patchversion veröffentlicht. Advisory
und Release werden gleichzeitig publiziert.

## 6. Nach dem Release

- ZIP herunterladen und Prüfsumme kontrollieren.
- Neuinstallation in einer leeren Umgebung prüfen.
- Upgrade der vorherigen stabilen DB3- und MySQL-Version prüfen.
- Hauptabläufe entsprechend `RELEASE_CHECKLIST.md` testen.
- Danach `main` auf die nächste `-dev`-Version setzen.
