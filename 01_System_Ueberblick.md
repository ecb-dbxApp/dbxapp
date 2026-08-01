# Systemueberblick {#dbxapp_system_overview}

[Offizielle dbxapp Website](https://dbxapp.de)

dbxapp ist ein PHP-basiertes Anwendungs-, CMS-, Modul- und Datenbanksystem.
Es verbindet klassische serverseitige Webentwicklung mit einem Runtime-Editor,
einer DD/FD-basierten Datenbeschreibung, wiederverwendbaren Formular- und
Report-Pipelines sowie einem Content-getriebenen Frontend.

## Was dbxapp ist

dbxapp ist keine lose Sammlung von Hilfsfunktionen. Es ist eine Plattform mit
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

## Was dbxapp kann

dbxapp kann aus einer gemeinsamen Infrastruktur mehrere Arbeitsweisen bedienen:

- Content-Webseiten mit Permalinks, CMS-Seiten, Mehrsprachigkeit und Cache.
- Admin-Anwendungen mit URL-Parametern, Reports, Formularen und Fenstern.
- Modul-Inclusions innerhalb von Content oder Templates.
- Laufzeitbearbeitung von Templates, Content, DD/FD und Moduloberflaechen.
- DD-Synchronisation zwischen Quellstruktur und Datenbank.
- Backup, Restore und Transfer für Datenbankbereiche.
- KI-gestuetzte CMS- und Strukturarbeit über `dbxKi`.
- Umschaltbare, eigenständige Frontend-Designs mit getrenntem Admin-Design
  und designspezifischen Skins.
- Eine integrierte Shop-Fachanwendung mit Katalog, Checkout, Bestellungen,
  Zahlungen, Rechtstexten, Medien, Versand und Verkaufskanälen.
- Definierbare und ausführbare Fachabläufe über `dbxWorkflow_admin` und
  `dbxWorkflow`.
- Einen vollständigen Gastseiten-Cache mit generationsbasierter Invalidierung,
  Host-/Installationsbindung und Prüfung des HTML-`base href`.
- Kontextbezogene Formularhilfen mit stabilen, ordnerunabhängigen Permalinks.

## Grundprinzipien

1. **Template first**: HTML-Struktur gehört in `/tpl`, nicht in lange PHP-Strings.
2. **Pipeline first**: Formulare nutzen `dbxForm`, Listen nutzen `dbxReport`,
   Datenzugriff nutzt `dbxDB`.
3. **DD/FD first**: Datenstruktur und Formularstruktur werden beschrieben, nicht
   ad hoc in Controllern erfunden.
4. **Content first im Frontend**: Öffentliche Seiten werden in der Regel über
   Permalinks und CMS-Seiten aufgerufen.
5. **Parameter first in der Administration**: Admin-Werkzeuge werden meistens
   über `?dbx_modul=...&dbx_run1=...` adressiert.
6. **Keine Parallelwege**: AJAX, Fenster, Confirm und UI-State laufen über die
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

## Für wen diese Doku ist

Diese Doku richtet sich an:

- Anwender, die verstehen wollen, warum dbxapp eine runtime-faehige
  Entwicklungsumgebung ist.
- Entwickler, die Module, Reports, Formulare, CMS-Strukturen und Admin-Panels
  bauen.
- KI-Agenten, die dbxapp-Code erweitern sollen, ohne Kernel- oder Lib-Struktur
  zu zerstören.

Der wichtigste Satz für Menschen und KI lautet:

> dbxapp stellt mit `dbx()`, den Systemklassen und `core.js` die Infrastruktur
> bereit. Neue Fachfunktionen sollen diese Infrastruktur nutzen, nicht umgehen.
