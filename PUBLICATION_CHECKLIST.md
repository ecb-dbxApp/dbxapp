# Checkliste vor dem ersten öffentlichen Push

- [ ] Das bisherige private Repository und seine Historie bleiben privat.
- [ ] Der öffentliche Stand stammt ausschließlich aus `dbxapp-github`.
- [ ] `composer --working-dir=dbx ci` ist vollständig erfolgreich.
- [ ] Secret-Scan des gesamten neuen Repositorys ist ohne offenen Befund.
- [ ] Keine DB3-, SQLite-, Dump-, Log-, Backup- oder Benutzerdateien enthalten.
- [ ] Keine lokalen `config.local.php`- oder `.env`-Dateien enthalten.
- [ ] Alle Bilder, Fonts, Logos und Fremdkomponenten dürfen veröffentlicht werden.
- [ ] `LICENSE` und Drittanbieter-Lizenzen sind vollständig.
- [ ] Personenbezogene Daten und interne Kundenmodule wurden entfernt.
- [ ] Alle Mail-Empfänger, SMTP-/SFTP-Profile und Modul-Secrets sind nur lokal konfiguriert.
- [ ] `VERSION`, README, Changelog und Security Policy sind schlüssig.
- [ ] Der initiale Commit enthält keine alte private Git-Historie.
- [ ] GitHub-Einstellungen aus `GITHUB_SETUP.md` sind aktiviert.

Das alte Repository darf nicht einfach auf „Public“ umgestellt werden. Es
enthält historische Backups und Datenartefakte, die im neuen Repository
bewusst nicht übernommen wurden.
