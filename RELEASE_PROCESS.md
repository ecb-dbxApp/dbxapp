# Verbindlicher Release-Prozess

Dieser Prozess veröffentlicht und aktualisiert ausschließlich die
dbxApp-4.2-Produktlinie. dbxApp 4.2.0 ist die erste unterstützte Version.
Es gibt keinen automatischen Updatepfad aus einer früheren Version.

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
4. enthält ausschließlich erforderliche Vorwärtsmigrationen ab der in
   `UPDATE_BASELINE` festgelegten unterstützten Version,
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
- Ab dem ersten Folgerelease: Update und Rollback von dbxApp 4.2.0 oder der im
  Manifest verlangten neueren Basis prüfen.
- Falls ein Release eine dokumentierte Datenmigration enthält: die
  Vorwärtsmigration ab der im Manifest verlangten 4.2-Basis mit DB3 und MySQL
  prüfen. Migrationen aus Versionen vor 4.2.0 existieren nicht.
- Hauptabläufe entsprechend `RELEASE_CHECKLIST.md` testen.
- Danach `main` auf die nächste `-dev`-Version setzen.

## 7. Was installierte Systeme wann erhalten

- Ein `*-dev`-Commit ist nur Entwicklungsstand und wird nie als Update
  angeboten.
- Ein geschützter Tag startet CI und erzeugt zunächst einen
  GitHub-Release-Entwurf. Auch dieser Entwurf wird noch nicht angeboten.
- Erst das manuelle Veröffentlichen des vollständig geprüften Entwurfs macht
  dessen `update.json` über
  `releases/latest/download/update.json` sichtbar.
- `dbxAdmin` vergleicht die dort angegebene stabile Version mit der lokal
  installierten Version und der im Manifest genannten `dbxapp`-Mindestbasis.
  Download und Installation bleiben getrennte, ausdrückliche Admin-Aktionen.
- Veröffentlichte Releases und ihre Assets sind auf GitHub unveränderlich.
  Eine Korrektur erfolgt deshalb immer als neue Patchversion, niemals durch
  Austausch eines bestehenden ZIPs.
