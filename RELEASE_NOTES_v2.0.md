# dbXapp 2.0 Release Notes

Version 2.0 fasst den aktuellen lokalen Entwicklungsstand als neue
Referenzversion fuer GitHub zusammen.

## Schwerpunkt

- Content-CMS, Medienauswahl und Bilddialoge weiterentwickelt.
- Admin-Dashboard, Sessions, Performance- und Health-Anzeigen ueberarbeitet.
- Reports, Pagination, Ajax-Replaces und Footer-Laufzeiten konsolidiert.
- DD/FD- und Config-Oberflaechen erweitert, inklusive aktiv/deaktivierbarer
  externer SQL-Server.
- HTML-Ausgabe in Reports, DD/FD und Templates wieder als Standard ermoeglicht,
  wo die Werte aus kontrollierten dbXapp-Quellen kommen.
- Doxygen-Dokumentation fuer den lokalen Stand neu generiert.
- Lokaler Stand gilt als Wahrheit fuer Version 2.0.

## Hinweise

- `C:/xampp/htdocs/dbxapp-docs` ist die generierte Doxygen-Ausgabe.
- Die Dokumentationsquellen liegen im Repository als Markdown-Dateien und
  werden ueber `Doxyfile` generiert.
- Lokale Laufzeitdaten, Datenbanken, Medien, Caches und sensible
  Konfigurationen bleiben durch `.gitignore` vom Release ausgeschlossen.
