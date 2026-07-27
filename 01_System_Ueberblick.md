# Systemueberblick {#dbxapp_system_overview}

[Offizielle dbXapp Website](https://dbxapp.de)

dbXapp ist ein PHP-basiertes Anwendungs-, CMS-, Modul- und Datenbanksystem.
Es verbindet klassische serverseitige Webentwicklung mit einem Runtime-Editor,
einer DD/FD-basierten Datenbeschreibung, wiederverwendbaren Formular- und
Report-Pipelines sowie einem Content-getriebenen Frontend.

## Was dbXapp ist

dbXapp ist keine lose Sammlung von Hilfsfunktionen. Es ist eine Plattform mit
einem klaren Kern:

- `index.php` ist der zentrale Einstieg.
- `dbx()` stellt die System-API bereit.
- Module liefern Fachlogik und Routen.
- Templates definieren Ausgabe und Layout.
- DD-Dateien beschreiben Tabellen und Felder.
- FD-Dateien beschreiben Formularsichten.
- `dbxForm` rendert und verarbeitet Formulare.
- `dbxReport` rendert Listen mit Suche, Sortierung, Pagination und Aktionen.
- `dbxDB` kapselt Datenzugriff, DD-Laden, Rechte, Trace, Performance und DB-Sync.
- JavaScript-Libs liefern standardisierte Browserfunktionen wie AJAX, Fenster,
  Confirm, CMS-Editor, Grid, Reports und UI-State.

@image html dbxapp-request-flow.svg "Request-, Modul-, Template- und Interpreter-Ablauf"

## Was dbXapp kann

dbXapp kann aus einer gemeinsamen Infrastruktur mehrere Arbeitsweisen bedienen:

- Content-Webseiten mit Permalinks, CMS-Seiten, Mehrsprachigkeit und Cache.
- Admin-Anwendungen mit URL-Parametern, Reports, Formularen und Fenstern.
- Modul-Inclusions innerhalb von Content oder Templates.
- Laufzeitbearbeitung von Templates, Content, DD/FD und Moduloberflaechen.
- DD-Synchronisation zwischen Quellstruktur und Datenbank.
- Backup, Restore und Transfer fuer Datenbankbereiche.
- KI-gestuetzte CMS- und Strukturarbeit ueber `dbxKi`.
- Umschaltbare, eigenstaendige Frontend-Designs mit getrenntem Admin-Design
  und designspezifischen Skins.
- Eine integrierte Shop-Fachanwendung mit Katalog, Checkout, Bestellungen,
  Zahlungen, Rechtstexten, Medien, Versand und Verkaufskanaelen.
- Definierbare und ausführbare Fachabläufe über `dbxWorkflow_admin` und
  `dbxWorkflow`.
- Einen vollständigen Gastseiten-Cache mit generationsbasierter Invalidierung,
  Host-/Installationsbindung und Prüfung des HTML-`base href`.
- Kontextbezogene Formularhilfen mit stabilen, ordnerunabhängigen Permalinks.

## Grundprinzipien

1. **Template first**: HTML-Struktur gehoert in `/tpl`, nicht in lange PHP-Strings.
2. **Pipeline first**: Formulare nutzen `dbxForm`, Listen nutzen `dbxReport`,
   Datenzugriff nutzt `dbxDB`.
3. **DD/FD first**: Datenstruktur und Formularstruktur werden beschrieben, nicht
   ad hoc in Controllern erfunden.
4. **Content first im Frontend**: Oeffentliche Seiten werden in der Regel ueber
   Permalinks und CMS-Seiten aufgerufen.
5. **Parameter first in der Administration**: Admin-Werkzeuge werden meistens
   ueber `?dbx_modul=...&dbx_run1=...` adressiert.
6. **Keine Parallelwege**: AJAX, Fenster, Confirm und UI-State laufen ueber die
   vorhandenen Libs.

## Typische Aufrufarten

Frontend:

```text
/kontakt
/produkte/lkw-planung
/de/service
```

Administration:

```text
?dbx_modul=dbxAdmin
?dbx_modul=dbxContact_admin&dbx_run1=list
?dbx_modul=dbxAdmin&dbx_run1=sysmsg&dbx_run2=list_sysmsg
```

Modul-Inclusion:

```html
[modul=dbxContact]dbx_run1=tickets[/modul]
[modul=dbxAdmin]dbx_run1=session&dbx_run2=list_session[/modul]
```

## Fuer wen diese Doku ist

Diese Doku richtet sich an:

- Anwender, die verstehen wollen, warum dbXapp eine runtime-faehige
  Entwicklungsumgebung ist.
- Entwickler, die Module, Reports, Formulare, CMS-Strukturen und Admin-Panels
  bauen.
- KI-Agenten, die dbXapp-Code erweitern sollen, ohne Kernel- oder Lib-Struktur
  zu zerstoeren.

Der wichtigste Satz fuer Menschen und KI lautet:

> dbXapp stellt mit `dbx()`, den Systemklassen und `core.js` die Infrastruktur
> bereit. Neue Fachfunktionen sollen diese Infrastruktur nutzen, nicht umgehen.
