# dbXapp v0.9

Stand: 22. Juni 2026

## Schwerpunkte

- Content-CMS und mehrsprachige Content-Tabellen überarbeitet
- Modulverwaltung, Modulbilder und kontextsensitive Modulhilfe erweitert
- Formulare, Templates und JavaScript-Ausgabe bereinigt
- Admin-, Benutzer-, Trace- und Konfigurationsbereiche überarbeitet
- lokale Zugangsdaten und personenbezogene Laufzeitdaten aus dem Release ausgeschlossen
- Projektdokumentation konsolidiert

## Lokale Konfiguration

Lokale Zugangsdaten werden nicht im Repository gespeichert. Als Vorlage dient
`.env.example`. Insbesondere die SFTP-Werte müssen über `DBX_SFTP_HOST`,
`DBX_SFTP_PORT`, `DBX_SFTP_USER` und `DBX_SFTP_PASSWORD` gesetzt werden.

## Lokale Daten

SQLite-Datenbanken sowie Dateien unter `files/media`, `files/sys/cfg` und
`files/sys/csv` bleiben ausschließlich lokal. Dadurch gelangen weder Sessions
und Protokolle noch Benutzer-, Kontakt-, Medien- oder Anwendungsdaten in das
Repository. Die leeren Datenbankverzeichnisse bleiben erhalten; Tabellen
werden über Setup und DD-Strukturen angelegt.
