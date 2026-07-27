# Einmalige GitHub-Einrichtung

Diese Einstellungen werden nach dem ersten Push im öffentlichen Repository
`ecb-dbxApp/dbxapp` vorgenommen.

## Repository

- Sichtbarkeit erst nach abgeschlossener `PUBLICATION_CHECKLIST.md` auf
  **Public** setzen.
- Default Branch: `main`
- Issues aktivieren.
- Private vulnerability reporting aktivieren.
- Dependency graph, Dependabot alerts und Dependabot security updates
  aktivieren.
- Secret scanning und Push Protection aktivieren, soweit im Tarif verfügbar.
- GitHub Actions Standardberechtigung auf **Read repository contents**
  begrenzen.

## Ruleset `main`

Ziel: Branch `main`

- Require a pull request before merging
- Require status checks to pass
- Erforderlicher Statuscheck: `dbx-ci`
- Block force pushes
- Restrict deletions
- Require linear history

Solange nur ein Maintainer vorhanden ist, wird keine fremde Freigabe
erzwungen. Mit einem zweiten Maintainer erhalten Kernel-, Security- und
DB-Migrationsänderungen mindestens eine Freigabe.

## Ruleset `release-tags`

Ziel: Tags `v*`

- Restrict updates
- Restrict deletions
- Block force updates
- Tag-Erstellung nur für Maintainer

## Labels

Mindestens:

- `bug`
- `enhancement`
- `feature`
- `fix`
- `documentation`
- `security`
- `dependencies`

## Release

Ein Release-Tag erzeugt einen **Entwurf**. Vor dem Veröffentlichen werden ZIP,
SHA-256-Prüfsumme, Release Notes und Upgrade-Hinweise geprüft. Bereits
veröffentlichte Artefakte werden nicht ersetzt; jede Korrektur erhält eine
neue Patchversion.
