# Release-Checkliste

## Automatisch

- [ ] `composer --working-dir=dbx ci` ist erfolgreich.
- [ ] Composer-Lockdatei ist aktuell.
- [ ] `composer audit` meldet keine bekannte Sicherheitslücke.
- [ ] Kein direkter PDO-/mysqli-Zugriff außerhalb von `dbxDB`.
- [ ] Keine DB3, Dumps, Backups, Logs, Secrets oder Benutzerdateien enthalten.
- [ ] Tag und `VERSION` stimmen überein.

## Datenbanken

- [ ] Saubere DB3-Installation erfolgreich.
- [ ] Upgrade der vorherigen DB3-Version erfolgreich.
- [ ] Saubere MySQL-Installation erfolgreich.
- [ ] Upgrade der vorherigen MySQL-Version erfolgreich.
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

## Veröffentlichung

- [ ] `CHANGELOG.md`, `UPGRADE.md` und `SECURITY.md` sind aktuell.
- [ ] Release-ZIP stammt aus dem geschützten Tag.
- [ ] SHA-256-Prüfsumme wurde kontrolliert.
- [ ] GitHub-Release-Entwurf wurde visuell geprüft.
- [ ] Release und gegebenenfalls Security Advisory gleichzeitig publiziert.
