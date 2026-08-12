# Release-Checkliste

## Automatisch

- [ ] `composer --working-dir=dbx ci` ist erfolgreich.
- [ ] Composer-Lockdatei ist aktuell.
- [ ] `composer audit` meldet keine bekannte Sicherheitslücke.
- [ ] Kein direkter PDO-/mysqli-Zugriff außerhalb von `dbxDB`.
- [ ] Keine DB3, Dumps, Backups, Logs, Secrets oder Benutzerdateien enthalten.
- [ ] Tag und `VERSION` stimmen überein.
- [ ] Source-to-GitHub-Dry-Run wurde geprüft und anschließend angewendet.
- [ ] Der GitHub-Spiegel enthält keine ungeplanten Handänderungen.

## Datenbanken

- [ ] Saubere DB3-Installation erfolgreich.
- [ ] Bei 4.2.0: Neuinstallation geprüft und Vorversionen ausdrücklich abgelehnt.
- [ ] Bei Folgereleases: DB3-Update ab der Manifest-Mindestbasis erfolgreich.
- [ ] Saubere MySQL-Installation erfolgreich.
- [ ] Bei Folgereleases: MySQL-Update ab der Manifest-Mindestbasis erfolgreich.
- [ ] Tabellen, Datensatzanzahlen und Prüfdaten stimmen.
- [ ] Migrationen verwenden dbxDB/DD und sind wiederholbar abgesichert.

## Funktionsabläufe

- [ ] Login, Logout und Session-ID-Wechsel funktionieren.
- [ ] `save` und `delete` funktionieren mit automatischem Action-Token.
- [ ] DD-Gruppen-, Benutzer- und Owner-Rechte greifen.
- [ ] dbxForm speichert und zeigt sprachabhängige Meldungen.
- [ ] dbxReport zeigt Labels, Callback-Summen und Footer korrekt.
- [ ] Deutsch, Englisch und Spanisch geprüft.
- [ ] Warenkorbeintrag entfernen und Warenkorb leeren funktionieren.
- [ ] Zahlungsvalidierung und Claim-Policy geprüft.
- [ ] dbxContent-Templateauswahl ist in allen Sprachen verfügbar.
- [ ] Full-Page-Cache und Invalidierung funktionieren.
- [ ] `files/dbxError.log` ist nach den Tests nicht vorhanden.
- [ ] Updateprüfung, Mindestbasis, Download, SHA-256, Staging, Installation
      und Rollback funktionieren ab der im Manifest verlangten Version.

## Veröffentlichung

- [ ] `CHANGELOG.md`, `UPGRADE.md` und `SECURITY.md` sind aktuell.
- [ ] Release-ZIP stammt aus dem geschützten Tag.
- [ ] SHA-256-Prüfsumme wurde kontrolliert.
- [ ] `UPDATE_BASELINE`, `update.json` und `.dbx-release-files.json` sind
      konsistent und verwenden Schema 2.
- [ ] GitHub-Release-Entwurf wurde visuell geprüft.
- [ ] Release und gegebenenfalls Security Advisory gleichzeitig publiziert.
