# Doxygen Awesome für dbXapp

Die HTML-Dokumentation verwendet
[Doxygen Awesome CSS](https://github.com/jothepro/doxygen-awesome-css) in
Version 2.4.2. Die Theme-Dateien liegen zusammen mit der MIT-Lizenz in diesem
Verzeichnis.

Aktiviert sind:

- Sidebar-Only-Layout mit Doxygen-Treeview;
- responsive Darstellung und Volltextsuche;
- automatischer sowie manuell umschaltbarer Hell-/Dunkelmodus;
- Kopierschaltflächen für Codeblöcke;
- permanente Abschnittslinks;
- interaktives Inhaltsverzeichnis.

`header.html` enthält eine kleine native Initialisierung für Doxygen 1.17.
Sie ersetzt bei den optionalen Erweiterungen die frühere jQuery-Abhängigkeit.
Die dbXapp-Farben und Abstände werden in `dbxapp-doxygen.css` angepasst.
Der globale Header enthält auf jeder erzeugten Seite den offiziellen Link
[dbxapp.de](https://dbxapp.de).

Neu erzeugen:

```powershell
Set-Location C:\xampp\htdocs\dbxapp
doxygen Doxyfile
```

Die Ausgabe wird gemäß `Doxyfile` direkt nach
`C:\xampp\htdocs\dbxapp-docs` geschrieben.

Bei einer vollständigen Veröffentlichung wird der bisherige erzeugte Inhalt
des Ausgabeordners zuerst entfernt. Dadurch bleiben keine Seiten früherer
Namespaces, Dateien oder Kapitel in der neuen Dokumentation zurück.
