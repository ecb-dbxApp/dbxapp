## Ziel

<!-- Was wird geändert und warum? -->

## dbxApp-Vertrag

- [ ] Datenbankzugriff erfolgt ausschließlich über dbxDB.
- [ ] DD-/FD-, Sprach- und Rechteverhalten bleibt kompatibel.
- [ ] Zustandsändernde Aktionen sind zentral abgesichert.
- [ ] Keine Datenbanken, Backups, Secrets oder Benutzerdateien enthalten.

## Prüfung

- [ ] `composer --working-dir=dbx ci` ist erfolgreich.
- [ ] Betroffener Hauptablauf wurde praktisch getestet.
- [ ] Dokumentation, Changelog oder Upgrade-Hinweise sind bei Bedarf aktualisiert.
