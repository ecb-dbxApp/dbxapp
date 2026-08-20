# Verbindliche Arbeitsgrenze

- Menüinhalte und installationsbezogene Menü-Templates sind Kundendateien.
  Eine gewünschte Menüänderung wird ausschließlich in der betroffenen
  Installation ausgeführt. Sie wird nicht in die Produktquelle synchronisiert,
  nicht zum Anlass für eine Versionsänderung genommen und nicht über ein Update
  verteilt.
- Modulimplementierungen und ihre System-Sourcen sind Produktdateien. Änderungen
  an PHP, DD, FD, JavaScript, CSS oder anderen systemweiten Modulbestandteilen
  erfolgen in der dbxapp-Entwicklungsquelle, werden getestet und über den
  vorgesehenen Release- und Updateprozess ausgeliefert.
- Vor jeder Änderung ist die Zieldatei zuerst als Kundeninhalt oder Systemquelle
  zu klassifizieren. Bei einer reinen Kundenänderung bleibt der Produktstand
  unverändert.
- Für Systemquellen gelten zusätzlich die verbindlichen Konventionen in
  `SOURCE-CONVENTIONS.md`.
- Direkter Datenbankzugriff über PDO, mysqli, SQLite3 oder native Treiber ist
  in Produkt-, Modul-, KI-, Migrations- und Werkzeugcode grundsätzlich
  verboten. Jeder Datenzugriff läuft ausnahmslos über `dbxDB` und eine
  vollständige DD; Strukturänderungen zusätzlich über die DD-Synchronisierung.
  Diese Regel gilt verbindlich für Menschen, Codex und dbxKi.
- Nach jedem erfolgreich abgeschlossenen logischen Änderungsblock schreibt
  Codex genau einen verständlichen Eintrag in `dbxChangeLog` – nicht einen
  Eintrag je Datei. Dafür ist ausschließlich
  `php dbx/modules/dbxChangeLog_admin/tools/write-change-log.php` zu verwenden.
  Der Eintrag enthält Datum/Uhrzeit, Akteur, eine verständliche Zusammenfassung
  und Begründung sowie alle betroffenen Ressourcen. Fehlgeschlagene, verworfene oder lediglich
  vorgesehene Änderungen werden nicht protokolliert; das Schreiben des
  Protokolleintrags erzeugt keinen weiteren Protokolleintrag.
- Für dbxKi ist das Change Log ebenfalls verbindlich. Schreibende KI-Antworten
  enthalten das deklarierte JSON-Objekt `change_log`; dbxKi speichert es erst
  nach erfolgreicher Ausführung über `dbxKiChangeLogService::write_change_log()`
  und gibt die gespeicherte Information in der JSON-Antwort zurück.
