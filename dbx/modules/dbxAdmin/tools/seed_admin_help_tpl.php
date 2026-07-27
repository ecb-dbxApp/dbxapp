<?php
$dir = dirname(__DIR__) . '/tpl/htm/';
$topics = array(
'admin-help-dashboard' => <<<'HTML'
<h4>Zweck</h4>
<p>Das Admin-Dashboard bündelt Systemstatus, Datenbanken, Module und letzte Aktivitaeten.</p>
<h4>Vorgehensweise</h4>
<ol>
<li>Oben Kennzahlen prüfen (DB-Verbindungen, Tabellen, Module).</li>
<li>Über die Aktionskarten direkt in häufige Admin-Bereiche springen.</li>
<li>Performance- und Aktivitätsbereiche für Engpässe nutzen.</li>
</ol>
<div class="help-flow">Dashboard → Bereich wählen → Detail-Admin → Aktion ausführen → Dashboard prüfen</div>
<div class="help-example"><strong>Beispiel:</strong> Hohe Trace-Zahl → Trace öffnen → betroffene Tabelle filtern → Datensatz-Historie ansehen.</div>
HTML,
'admin-help-cache' => <<<'HTML'
<h4>Zweck</h4>
<p>Der Gast-Full-Page-Cache speichert bei gueltigen Permalink-Aufrufen die vollstaendige Endausgabe. Titel, Description, Keywords, weitere Head-Metadaten, Design, Menues, Interpreter-Platzhalter und enthaltene Module sind darin bereits aufgeloest. Der Cache gilt ausschliesslich fuer Gaeste mit Benutzer-ID 0; angemeldete Benutzer bleiben dynamisch.</p>
<p>Ein Treffer wird vor der Content-Permalink-/CID-Aufloesung direkt aus der Permalink-Datei geliefert. Dabei ist kein Content-Datenbankzugriff erforderlich; nur die normale Session-Aktualisierung darf bei aktivem Session-DB-Modus die Datenbank verwenden. Sessiongebundene Formular-Tokens werden ohne Modul- oder Interpreterlauf frisch in die Gast-Session eingesetzt.</p>
<p>Die Cachedatei selbst folgt dem lesbaren Muster <code>permalink_sprache_skin.htm</code>, zum Beispiel <code>kunden-nutzen_de_blau.htm</code>. Ein <code>user_0</code>-Zusatz ist nicht erforderlich, weil nur Benutzer-ID 0 den Cache verwenden darf. Design-, Host-, Query- und Gastzustandsvarianten liegen kollisionsfrei in getrennten Kontext-Unterordnern.</p>
<h4>Möglichkeiten</h4>
<ul>
<li>Anzahl der gecachten Gast-Full-Pages je Sprache lesen.</li>
<li>Full-Page-Cache bei Template-, Menue- oder Content-Aenderungen manuell leeren.</li>
</ul>
<div class="help-flow">Content speichern → Cache invalidiert automatisch (CMS)
Manuell: Admin → Page-Cache → Leeren → Seite neu laden</div>
<div class="help-example"><strong>Beispiel:</strong> Content-Aenderung fehlt auf der Live-Seite → Cache leeren → gueltigen Permalink erneut aufrufen.</div>
HTML,
'admin-help-dd-list' => <<<'HTML'
<h4>Zweck</h4>
<p>DD Sync vergleicht DataDictionary-Dateien (<code>*.dd.php</code>) mit der physischen Datenbankstruktur.</p>
<h4>Vorgehensweise</h4>
<ol>
<li>Modul/DD in der Liste wählen.</li>
<li>Sync-Plan prüfen (fehlende Felder, Indizes).</li>
<li><em>Apply</em> schrittweise ausführen, nicht blind resetten.</li>
</ol>
<div class="help-flow">DD-Datei ändern → DD Sync → Diff prüfen → Apply → DB Sync testen</div>
<div class="help-example"><strong>Beispiel:</strong> Neues Feld in <code>contactRequest.dd.php</code> → DD Sync → Feld erscheint als pending → Apply.</div>
HTML,
'admin-help-dd-fields' => <<<'HTML'
<h4>Zweck</h4>
<p>Feldvergleich zwischen DD-Definition und Datenbank-Spalten einer Tabelle.</p>
<h4>Möglichkeiten</h4>
<ul>
<li>Abweichungen pro Feld (Typ, Länge, Index) erkennen.</li>
<li>Mapping und Batch-Aktionen für Korrekturen.</li>
</ul>
<div class="help-flow">Tabelle wählen → Fields Grid → Abweichung markieren → Sync Apply</div>
HTML,
'admin-help-dd-backup' => <<<'HTML'
<h4>Zweck</h4>
<p>Backup und Restore der SQLite-Datenbanken über den Admin.</p>
<h4>Vorgehensweise</h4>
<ol>
<li>Vor strukturellen Änderungen Backup erstellen.</li>
<li>Restore nur auf Test-/Dev-Umgebung oder mit Vorsicht auf Produktion.</li>
</ol>
<div class="help-example"><strong>Beispiel:</strong> Vor DD-Reset Backup → Apply → bei Fehler Restore.</div>
HTML,
'admin-help-db-list' => <<<'HTML'
<h4>Zweck</h4>
<p>DB Sync verwaltet Datensätze direkt in Tabellen (Lesen, Einfügen, Bearbeiten, Löschen).</p>
<h4>Möglichkeiten</h4>
<ul>
<li>Tabellendaten paginiert anzeigen und filtern.</li>
<li>CSV Import/Export pro Tabelle.</li>
<li>Einzelne Tabellen backup/restore.</li>
</ul>
<div class="help-flow">DB Sync → Tabelle → Daten prüfen → Zeile bearbeiten → Speichern</div>
HTML,
'admin-help-edit-dd' => <<<'HTML'
<h4>Zweck</h4>
<p>DD-Editor pflegt Tabellen-Metadaten und Felddefinitionen in <code>dd/{name}.dd.php</code>.</p>
<h4>Vorgehensweise</h4>
<ol>
<li>Tabelle + Felder im Editor öffnen.</li>
<li>Feldtyp, Regeln, Index anpassen.</li>
<li>Reihenfolge per Drag speichern.</li>
<li>Danach DD Sync ausführen.</li>
</ol>
<div class="help-flow">DD Editor → Feld hinzufügen → Speichern → DD Sync Apply</div>
HTML,
'admin-help-edit-fd' => <<<'HTML'
<h4>Zweck</h4>
<p>FD-Editor definiert Formularfelder (<code>fd/{name}.fd.php</code>) für dbxForm/dbxReport.</p>
<h4>Möglichkeiten</h4>
<ul>
<li>Labels, TPL-Typ, Validierung, Optionen pflegen.</li>
<li>Aus DD-Feldern FD automatisch erzeugen.</li>
</ul>
<div class="help-example"><strong>Beispiel:</strong> Neues Report-Filterfeld → FD anlegen → Report <code>create_selection_fields()</code> nutzen.</div>
HTML,
'admin-help-server' => <<<'HTML'
<h4>Zweck</h4>
<p>Server-Verwaltung listet registrierte SQLite-Server und deren Tabellen.</p>
<h4>Vorgehensweise</h4>
<ol>
<li>Server-Eintrag prüfen (Pfad, Name).</li>
<li>Tabellen zuordnen und DD-Verknüpfung kontrollieren.</li>
</ol>
HTML,
'admin-help-server-tables' => <<<'HTML'
<h4>Zweck</h4>
<p>Tabellen eines Datenbank-Servers verwalten und DD-Zuordnung prüfen.</p>
HTML,
'admin-help-modules-list' => <<<'HTML'
<h4>Zweck</h4>
<p>Übersicht aller installierten Module mit Version, Aktiv-Status und Zugriff.</p>
<h4>Möglichkeiten</h4>
<ul>
<li>Modul aktivieren/deaktivieren.</li>
<li>Zugriffsrechte (Gruppen) pro Modul setzen.</li>
<li>Avatar/Icon des Moduls anpassen.</li>
</ul>
HTML,
'admin-help-modules-new' => <<<'HTML'
<h4>Zweck</h4>
<p>Assistent zum Anlegen eines neuen Modul-Grundgerüsts.</p>
<div class="help-flow">Name wählen → Verzeichnisstruktur → Klasse + Config → Modul aktivieren</div>
HTML,
'admin-help-modules-access' => <<<'HTML'
<h4>Zweck</h4>
<p>Legt fest, welche Benutzergruppen ein Modul sehen und nutzen dürfen.</p>
<div class="help-example"><strong>Beispiel:</strong> dbxWorkflow nur für <code>admin</code> → Zugriff speichern → Menü prüfen.</div>
HTML,
'admin-help-config' => <<<'HTML'
<h4>Zweck</h4>
<p>System- und Modul-Konfiguration (<code>cfg/config.php</code>) zentral bearbeiten.</p>
<h4>Vorgehensweise</h4>
<ol>
<li>Modul in der Auswahl wählen (z. B. dbx, dbxContact).</li>
<li>Werte ändern und speichern.</li>
<li>Bei Mail/Cache-Einstellungen Auswirkung im Zielmodul testen.</li>
</ol>
HTML,
'admin-help-session' => <<<'HTML'
<h4>Zweck</h4>
<p>Zeigt aktive Benutzer-Sessions und Login-Metadaten.</p>
<h4>Möglichkeiten</h4>
<ul>
<li>Verdächtige Sessions identifizieren.</li>
<li>Bei Support-Fällen letzte Aktivität prüfen.</li>
</ul>
HTML,
'admin-help-trace' => <<<'HTML'
<h4>Zweck</h4>
<p>Trace protokolliert Änderungen an Datensätzen (wer, wann, was).</p>
<h4>Vorgehensweise</h4>
<ol>
<li>Nach Tabelle/Datensatz filtern.</li>
<li>Historie per Lupe in openWin öffnen.</li>
<li>Optional Wiederherstellen / Undelete.</li>
</ol>
<div class="help-flow">Änderung → Trace-Liste → Detail → Restore (optional)</div>
HTML,
'admin-help-sysmsg' => <<<'HTML'
<h4>Zweck</h4>
<p>System-Meldungen und Fehlerprotokoll des Kernels.</p>
<h4>Möglichkeiten</h4>
<ul>
<li>Fehler nach Modul/Aktion filtern.</li>
<li>Error-Log-Box für PHP/Kernel-Hinweise.</li>
</ul>
HTML,
'admin-help-missing' => <<<'HTML'
<h4>Zweck</h4>
<p>Listet fehlende Dateien, DD-Einträge oder Referenzen im System.</p>
<p>Nutzen Sie die Liste nach Deployments oder Modul-Updates zur Integritätsprüfung.</p>
HTML,
'admin-help-contact' => <<<'HTML'
<h4>Zweck</h4>
<p>Verwaltung eingehender Kontaktanfragen aus dbxContact.</p>
<h4>Vorgehensweise</h4>
<ol>
<li>Offene Anfragen in der Liste prüfen.</li>
<li><em>Antwort</em> öffnet Formular (Status, reply_text, optional Mail).</li>
<li>Alternativ Workflow <code>ticket_demo</code> über Modul-Binding.</li>
</ol>
<div class="help-flow">Anfrage → Antwort/Workflow → Status + Text → Speichern → optional Mail</div>
HTML,
'admin-help-user' => <<<'HTML'
<h4>Zweck</h4>
<p>Benutzer- und Gruppenverwaltung im dbxAdmin-Bereich (klassische Reports).</p>
<p>Für Grid-basierte Verwaltung siehe dbxUser_admin.</p>
HTML,
'admin-help-export-sql' => <<<'HTML'
<h4>Zweck</h4>
<p>SQL-Dump/Export von Datenbankinhalten für Backup oder Migration.</p>
HTML,
'admin-help-datadic' => <<<'HTML'
<h4>Zweck</h4>
<p>Gesamtübersicht aller DataDictionaries im System.</p>
HTML,
'admin-help-workflow-list' => <<<'HTML'
<h4>Workflow-Definitionen</h4>
<p>dbxWorkflow_admin ist die Quelle der Abläufe, die dbxWorkflow ausführt.</p>
<div class="help-flow">Definition → aktivieren → Start → Instanz mit Snapshot → Schritte → Abschluss</div>
<p>Nur aktive Definitionen können neu gestartet werden. Beim Start wird der Definitionsstand für die Instanz gespeichert.</p>
HTML,
'admin-help-workflow-edit' => <<<'HTML'
<h4>Workflow im Designer erstellen</h4>
<ol>
<li>Stabilen Workflow Key, Name, Ziel und Beschreibung festlegen.</li>
<li>Eingaben, Aktionen, Prüfungen und Entscheidungen aus der Palette in den Ablauf ziehen.</li>
<li>Pflichtangaben, Bearbeitungsarten und Verzweigungen konfigurieren.</li>
<li>Optional ein aktives Binding als <code>modul|bind_key</code> zuordnen.</li>
<li>Speichern, aktivieren und über dbxWorkflow vollständig testen.</li>
</ol>
<p>Die JSON-Definition wird automatisch erzeugt. Änderungen gelten für neu gestartete Instanzen.</p>
HTML,
'admin-help-workflow-binds' => <<<'HTML'
<h4>Modul-Bindings</h4>
<p>Bindings verbinden Workflows deklarativ mit DD, FD, Templates und Finish-Logik eines Fachmoduls.</p>
<ul>
<li>Modul + DD mit dem Generator als Grundgerüst scannen.</li>
<li>record, needs und finish fachlich prüfen.</li>
<li>Aktivieren und im Workflow als <code>bind_ref = modul|bind_key</code> referenzieren.</li>
</ul>
HTML,
'admin-help-workflow-bind-edit' => <<<'HTML'
<h4>Binding JSON</h4>
<p><code>record</code> beschreibt DD und Datensatz-ID, <code>needs</code> Auswahl/Vorbelegung/Kontext und <code>finish</code> die erlaubte Fachänderung.</p>
<p>Generatorergebnis immer auf Feldnamen, Rechte, Filter und Folgeaktionen prüfen.</p>
HTML,
'admin-help-workflow-instances' => <<<'HTML'
<h4>Instanzen</h4>
<p>Laufende und abgeschlossene Workflow-Durchläufe mit Status, Schritt und Fortschritt.</p>
<p>Neue Instanzen verwenden den beim Start gespeicherten Definitions-Snapshot. Letzte Meldung und Fachstatus vor einem Neustart prüfen.</p>
HTML,
'admin-help-workflow-install' => <<<'HTML'
<h4>Install</h4>
<p>Synchronisiert die Workflow-DDs und ergänzt fehlende Demo-Definitionen und Standard-Bindings.</p>
<p>Bestehende, im Admin bearbeitete Definitionen und Bindings werden nicht durch PHP-Vorgaben überschrieben.</p>
HTML,
'admin-help-workflow-use' => <<<'HTML'
<h4>Workflow starten</h4>
<p>Ziel und Beschreibung prüfen. Jeder Start erzeugt eine neue Instanz mit eigenem Definitions-Snapshot.</p>
<p>Für einen vorhandenen Durchlauf den Fortsetzen-Link nutzen. Unbekannte oder deaktivierte Keys werden nicht ersetzt.</p>
HTML,
'admin-help-workflow-run' => <<<'HTML'
<h4>Workflow ausführen</h4>
<p>Aktuellen Schritt bearbeiten, Fachmodul-Aktionen dort speichern und anschließend im Workflow bestätigen.</p>
<p>Prüfung und Schrittnavigation zeigen vollständige, offene und gesperrte Angaben. Erst nach erfolgreichem Review und Finish ist der Durchlauf fertig.</p>
HTML,
'admin-help-content' => <<<'HTML'
<h4>Content CMS</h4>
<p>Seitenbaum, Editor, Medien und Permalinks für dbxContent.</p>
<div class="help-flow">Ordner → Seite wählen → Inhalt bearbeiten → Speichern → Vorschau
Cache wird bei Save invalidiert.</div>
<h4>Bereiche</h4>
<ul>
<li>Links: Baum (Ordner/Seiten)</li>
<li>Mitte: Editor + Module auf Seite</li>
<li>Rechts: Meta, Medien, Einstellungen</li>
</ul>
HTML,
'admin-help-user-admin' => <<<'HTML'
<h4>Benutzer-Grid</h4>
<p>Tabulator-Grid für schnelle Bearbeitung von Benutzern und Gruppen.</p>
<ul>
<li>Autosave optional aktivieren.</li>
<li>Neuer Benutzer öffnet Formular im Fenster (openWin).</li>
<li>Gruppen-Rechte steuern Modulzugriff.</li>
</ul>
HTML,
);

$written = 0;
$preserved = 0;
foreach ($topics as $file => $html) {
    // Die ausfuehrlichen Workflow-Hilfen werden direkt als versionierte
    // Templates gepflegt. Der allgemeine Seed darf sie nicht verkuerzen.
    if (str_starts_with($file, 'admin-help-workflow-') && is_file($dir . $file . '.htm')) {
        $preserved++;
        continue;
    }
    file_put_contents($dir . $file . '.htm', trim($html) . "\n");
    $written++;
}
echo 'Created ' . $written . ' help templates; preserved ' . $preserved . " workflow templates.\n";
