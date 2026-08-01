# dbxapp Dokumentation {#mainpage}

- **Version:** 4.1.3
- **Dokumentationsstand:** 1. August 2026
- **Website:** [dbxapp.de](https://dbxapp.de)

@htmlonly
<div class="dbx-doc-hero">
  <span class="dbx-doc-kicker">Eine Plattform · zwei klare Dokumentationswege</span>
  <h2>Was möchten Sie mit dbxapp tun?</h2>
  <p>Wählen Sie zuerst Ihre Rolle. Bedienung und tägliche Arbeit bleiben
  deutlich von Architektur, Klassen und Modulentwicklung getrennt.</p>
</div>
@endhtmlonly

@htmlonly
<div class="dbx-audience-grid">
  <div class="dbx-audience-card dbx-audience-user">
    <div class="dbx-audience-card-head">
      <span class="dbx-audience-card-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0-8a3 3 0 1 1 0 6 3 3 0 0 1 0-6Zm0 10c-5 0-9 2.5-9 5.5V22h18v-2.5C21 16.5 17 14 12 14Zm-6.9 6c.4-2 3.4-4 6.9-4s6.5 2 6.9 4H5.1Z"/></svg>
      </span>
      <span class="dbx-audience-label">Für Anwender</span>
    </div>
    <h2>dbxapp benutzen</h2>
    <p>Login, Navigation, Administration, CMS, Medien, Shop, Workflows,
    Designs und KI mit nachvollziehbaren Schritten und Screenshots.</p>
    <p><a href="dbxapp_user_docs.html">Anwenderdokumentation öffnen</a></p>
  </div>
  <div class="dbx-audience-card dbx-audience-developer">
    <div class="dbx-audience-card-head">
      <span class="dbx-audience-card-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="m8.7 16.6-4.6-4.6 4.6-4.6-1.4-1.4L1.3 12l6 6 1.4-1.4Zm6.6 0 1.4 1.4 6-6-6-6-1.4 1.4 4.6 4.6-4.6 4.6ZM14.2 3 8 21h2.1l6.2-18h-2.1Z"/></svg>
      </span>
      <span class="dbx-audience-label">Für Entwickler</span>
    </div>
    <h2>dbxapp entwickeln</h2>
    <p>Architektur, Module, dbxDB, DD, FD, dbxForm, dbxReport, dbxTPL,
    JavaScript, Sicherheit, Tests, Installation und Updates.</p>
    <p><a href="dbxapp_developer_docs.html">Entwicklerdokumentation öffnen</a></p>
  </div>
</div>
@endhtmlonly

@subpage dbxapp_user_docs "Anwenderdokumentation"

@subpage dbxapp_developer_docs "Entwicklerdokumentation"

## Die drei KI-Bereiche

dbxKi ist nicht ein einzelner allgemeiner KI-Weg. Die Aufgaben sind nach dem
Gegenstand klar getrennt:

@htmlonly
<div class="dbx-ki-summary-grid">
  <div class="dbx-ki-summary-content"><strong>1 · Content</strong><span>Seiten, Texte, SEO und Übersetzungen</span></div>
  <div class="dbx-ki-summary-design"><strong>2 · Design</strong><span>Layout, Branding, Skins und Designpakete</span></div>
  <div class="dbx-ki-summary-modules"><strong>3 · Module</strong><span>Fachlogik, DD, FD, Templates und Tests</span></div>
</div>
@endhtmlonly

@ref dbxapp_ki_areas "Die drei KI-Bereiche und ihre Grenzen im Überblick"

## Schnelleinstieg für Anwender

- @ref dbxcontent_tutorial_tutorial_login_profil_passwort "Anmelden, Profil und Passwort"
- @ref dbxcontent_tutorial_tutorial_menue_benutzen "Hauptmenü und Untermenüs"
- @ref dbxcontent_tutorial_tutorial_admin_dashboard "Admin-Dashboard"
- @ref dbxapp_user_system_update "System-Update sicher durchführen"
- @ref dbxcontent_tutorial_tutorial_cms_editor "CMS-Editor"
- @ref dbxcontent_tutorial_tutorial_shop_frontend "Shop im Frontend"
- @ref dbxcontent_tutorial_tutorial_workflow_nutzen "Workflow benutzen"

## Schnelleinstieg für Entwickler

1. @ref dbxapp_module_reference "Verbindliches Modulhandbuch"
2. @ref dbxapp_module_patterns "Modulaufbau und Patterns"
3. @ref dbxapp_dbxdb_dd_fd "dbxDB, DD und FD"
4. @ref dbxapp_dbxform "dbxForm"
5. @ref dbxapp_dbxreport "dbxReport"
6. @ref dbxapp_dbxtpl "dbxTPL"
7. @ref dbxapp_security_integrity_performance "Sicherheit, Integrität und Performance"

## Verbindliche technische Grundlage

| Aufgabe | dbxapp-Schicht |
| --- | --- |
| Request und Systemzugriff | `dbx()` / `dbxApi` |
| Fachroute | kleiner Modulrouter |
| Fachoperation | Modulservice, bei Bedarf Repository oder Provider |
| Darstellung | `dbxTPL` und Templates |
| Datenzugriff | `dbxDB` |
| Datenstruktur, Serverbindung und Rechte | DD |
| Formularsicht und sprachabhängige Meldungen | FD |
| Eingabe und Validierung | `dbxForm` |
| Listen, Filter, Summen und Pagination | `dbxReport` |
| Teilreload und Bestätigung | `ajax.js` und `confirm.js` |
| Content-Einbindung | `dbxInterpreter` |

@htmlonly
<div class="dbx-note">
Anwender-Tutorials werden automatisch aus den maßgeblichen deutschen
dbxContent-Seiten und ihren Medienzuordnungen erzeugt. Entwicklerdokumentation
bleibt dateibasiert, versionierbar und direkt mit Klassen und Tests verknüpft.
</div>
@endhtmlonly
