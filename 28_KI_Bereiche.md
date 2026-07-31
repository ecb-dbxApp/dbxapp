# Die drei KI-Bereiche in dbxapp {#dbxapp_ki_areas}

dbxKi besitzt drei klar getrennte Arbeitsbereiche. Sie verwenden denselben
Grundgedanken – Briefing, vollständiger Kontext, prüfbares Ergebnis und
bewusste Freigabe –, bearbeiten aber unterschiedliche Gegenstände.

@htmlonly
<div class="dbx-ki-area-grid">
  <div class="dbx-ki-area dbx-ki-content">
    <span class="dbx-area-number">1</span>
    <h2>Content</h2>
    <p>Seiten, Texte, SEO-Daten, Medienhinweise und Übersetzungen innerhalb von
    dbxContent.</p>
    <p><a href="dbxapp_user_ki_content.html">Content-KI öffnen</a></p>
  </div>
  <div class="dbx-ki-area dbx-ki-design">
    <span class="dbx-area-number">2</span>
    <h2>Design</h2>
    <p>Designpakete, Seitenaufteilung, Menüform, Branding, Skins, Assets und
    responsive Darstellung.</p>
    <p><a href="dbxapp_user_ki_design.html">Design-KI öffnen</a></p>
  </div>
  <div class="dbx-ki-area dbx-ki-modules">
    <span class="dbx-area-number">3</span>
    <h2>Module</h2>
    <p>Fachmodule, DD, FD, Templates, Services, Tests und kontrollierte
    Erweiterungen der Anwendung.</p>
    <p><a href="dbxapp_user_ki_modules.html">Modul-KI öffnen</a></p>
  </div>
</div>
@endhtmlonly

@subpage dbxapp_user_ki_content "Content-KI"

@subpage dbxapp_user_ki_design "Design-KI"

@subpage dbxapp_user_ki_modules "Modul-KI"

## Was alle drei Bereiche gemeinsam haben

1. Der Benutzer beschreibt Ziel und erwartetes Ergebnis.
2. dbxKi ergänzt die verbindlichen Regeln und die benötigten Quelldateien.
3. Die KI liefert ein klar begrenztes Antwortpaket.
4. dbxapp prüft Vertrag, Pfade, Dateien und Zielbereich.
5. Der Benutzer sieht das Ergebnis vor der Übernahme.
6. Erst eine bewusste Freigabe verändert das System.

## Deutliche Grenzen

| Bereich | Darf bearbeiten | Darf nicht übernehmen |
| --- | --- | --- |
| Content-KI | dbxContent-Seiten, sprachbezogene Inhalte und SEO-Vorschläge | Module, DD, Rechte oder Designpakete |
| Design-KI | Dateien des angegebenen Ziel-Designs | Fachlogik, Datenbanktabellen oder Modulrechte |
| Modul-KI | ausdrücklich beauftragte Modul- und Testdateien | freie Änderungen außerhalb des Auftragspakets |

@htmlonly
<div class="dbx-note">
Die Wahl des richtigen KI-Bereichs ist Teil der Sicherheit. Eine
Content-Änderung wird nicht als Modulauftrag ausgeführt, und ein Designauftrag
darf keine Fachlogik verändern.
</div>
@endhtmlonly
