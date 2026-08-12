# dbxApp 4.2.0

dbxApp 4.2.0 ist die erste unterstützte Produktversion und die verbindliche
Basis für alle zukünftigen Updates. Es gibt keinen Installations-, Daten- oder
Kompatibilitätsweg aus einer früheren dbxApp-Version.

## Update-Vertrag

- Neuinstallationen beginnen mit 4.2.0.
- Künftige Releases werden über den integrierten Updater installiert.
- Manifest, Mindestbasis, PHP-Anforderungen, SHA-256, ZIP-Pfade und jede
  Paketdatei werden vor der Installation geprüft.
- Produktdateien können kontrolliert ergänzt, ersetzt und entfernt werden.
- Künftige DD-/dbxDB-Migrationen beginnen frühestens bei 4.2.0 und verwenden
  ausschließlich die systemweiten Datenbankabstraktionen.
- Datei- und Datenbanksicherung sowie gemeinsamer Rollback bleiben Bestandteil
  jedes kritischen Updatevorgangs.
- Laufzeitdaten, Kundendateien und lokale Konfigurationen werden nicht aus dem
  Release-Paket überschrieben.

## Qualitätsstand

4.2.0 enthält den vereinheitlichten dbxTPL-/dbxForm-/dbxReport-/dbxDB-Vertrag,
die neue CMS-Editorstruktur, die konsolidierten Designs und den vollständigen
dbxSelfTest als ersten wartbaren Ausgangsstand der Produktlinie.
