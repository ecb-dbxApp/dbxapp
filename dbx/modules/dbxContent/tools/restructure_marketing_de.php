<?php
declare(strict_types=1);

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentSitemap;

if (PHP_SAPI !== 'cli') {
   fwrite(STDERR, "Dieses Werkzeug darf nur auf der Kommandozeile laufen.\n");
   exit(1);
}

$apply = in_array('--apply', $argv, true);
$base = dirname(__DIR__, 4);
chdir($base);

$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';
require_once $base . '/dbx/modules/dbxContent/include/dbxContent_bootstrap_sync.php';
require_once $base . '/dbx/modules/dbxContent/include/dbxContentPageCache.class.php';
require_once $base . '/dbx/modules/dbxContent/include/dbxContentSitemap.class.php';

$contentDd = dbxContentLng::ddContent('de');
$folderDd = dbxContentLng::ddFolder('de');
$db = dbx()->get_system_obj('dbxDB');
if (!is_object($db) || !$db->connect_db_server('dbx|dbxContent.db3')) {
   fwrite(STDERR, "Die Content-Datenbank konnte nicht über dbxDB verbunden werden.\n");
   exit(1);
}

$trashId = 19;
$archiveIds = array(
   2, 4, 5, 6, 8, 45, 47, 48, 51, 53, 56, 57, 58, 59, 60, 62,
   63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76,
   115, 116, 117, 118, 119, 120, 121, 122,
);

$pages = array(
   array(
      'folder' => 2,
      'sorter' => '0010',
      'title' => 'Lösungen für Websites, Verkauf und Geschäftsprozesse',
      'permalink' => 'loesungen',
      'seo_title' => 'dbxapp Lösungen: CMS, Shop, Anwendungen und Portale',
      'description' => 'Vier klare Lösungsbereiche verbinden Website und CMS, Shop und Multichannel, individuelle Anwendungen sowie Intranet und Portale.',
      'keywords' => 'dbxapp Lösungen, CMS, Shop, Fachanwendung, Intranet, Portal',
      'content' => <<<'HTML'
<p class="lead">dbxapp verbindet Inhalte, Verkauf und betriebliche Abläufe auf einer gemeinsamen Plattform. Sie starten mit der Lösung, die heute gebraucht wird, und ergänzen weitere Funktionen, wenn das Unternehmen wächst.</p>

<div class="row g-4 my-4">
  <div class="col-md-6"><div class="card h-100"><div class="card-body">
    <h2>Website und CMS</h2>
    <p>Seiten, Medien und mehrsprachige Inhalte komfortabel pflegen und mit individuellen Funktionen verbinden.</p>
    <a class="btn btn-outline-primary" href="cms-website">CMS und Website entdecken</a>
  </div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body">
    <h2>Shop und Multichannel</h2>
    <p>Produkte, Leistungen, Bestellungen und Vertriebskanäle in einem nachvollziehbaren Ablauf verwalten.</p>
    <a class="btn btn-outline-primary" href="shop-multichannel">Shop-Lösung entdecken</a>
  </div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body">
    <h2>Individuelle Anwendungen</h2>
    <p>Formulare, Daten, Reports, Dashboards und Workflows passend zu den tatsächlichen Aufgaben entwickeln.</p>
    <a class="btn btn-outline-primary" href="individuelle-anwendungen">Anwendungen entdecken</a>
  </div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body">
    <h2>Intranet und Portale</h2>
    <p>Geschützte Informationen und Funktionen für Mitarbeitende, Kunden oder Partner bereitstellen.</p>
    <a class="btn btn-outline-primary" href="intranet-portale">Portale entdecken</a>
  </div></div></div>
</div>

<h2>Keine neue technische Insel</h2>
<p>Alle Bereiche verwenden denselben Kern für Benutzer, Rechte, Daten, Formulare, Reports, Templates und Medien. Dadurch können Inhalte und Fachfunktionen zusammenarbeiten, ohne für jeden neuen Schritt ein getrenntes System einzuführen.</p>

<div class="alert alert-primary">
  <strong>Welche Lösung passt?</strong> Beschreiben Sie kurz Ihre Ausgangslage. Gemeinsam lässt sich klären, welcher Einstieg sinnvoll ist.
  <a class="btn btn-primary ms-md-3 mt-2 mt-md-0" href="kontakt">Projekt besprechen</a>
</div>
HTML,
   ),
   array(
      'folder' => 2,
      'sorter' => '0020',
      'title' => 'Website und CMS: Inhalte professionell verwalten',
      'permalink' => 'cms-website',
      'seo_title' => 'CMS und Website mit dbxapp',
      'description' => 'Mit dbxapp verwalten Sie Seiten, Medien, Designs und mehrsprachige Inhalte und verbinden das CMS bei Bedarf mit individuellen Modulen.',
      'keywords' => 'CMS, Website, Medienverwaltung, mehrsprachig, Webdesign, dbxapp',
      'content' => <<<'HTML'
<p class="lead">Ein professioneller Webauftritt braucht aktuelle Inhalte, eine klare Struktur und ein Design, das zur Marke passt. Das dbxapp CMS verbindet diese redaktionellen Aufgaben mit Medien, Rechten und funktionalen Modulen.</p>

<h2>Inhalte und Seiten übersichtlich pflegen</h2>
<p>Redakteure verwalten Texte, Bilder, Downloads, Seitenstruktur und SEO-Daten an einem Ort. Inhalte können mehrsprachig aufgebaut, gezielt veröffentlicht und von berechtigten Personen bearbeitet werden.</p>

<div class="row g-3 my-4">
  <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h3>Seiten und Medien</h3><p>Inhalte strukturieren, Medien zentral verwenden und Änderungen direkt im passenden Kontext prüfen.</p></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h3>Designfreiheit</h3><p>Inhalt, Template und Design bleiben getrennt. Dadurch sind individuelle Auftritte möglich, ohne die redaktionellen Daten neu aufzubauen.</p></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h3>Mehrsprachigkeit</h3><p>Sprachversionen werden nachvollziehbar miteinander verbunden und können getrennt geprüft und veröffentlicht werden.</p></div></div></div>
</div>

<h2>Mehr als eine klassische Website</h2>
<p>Formulare, Kontaktstrecken, Downloads, Shop-Funktionen, geschützte Bereiche oder individuelle Anwendungen lassen sich in Content-Seiten einbinden. So bleibt die Website der verständliche Einstieg, während dahinter echte Geschäftsprozesse ablaufen können.</p>

<h2>KI-Unterstützung mit Freigabe</h2>
<p>dbxKi kann Briefings, Textentwürfe, SEO-Daten und Übersetzungen vorbereiten. Das CMS bleibt das führende System: Vorschläge werden geprüft und erst danach übernommen.</p>

<p><a class="btn btn-primary" href="kontakt">Website-Projekt anfragen</a> <a class="btn btn-outline-secondary" href="dbxki">dbxKi kennenlernen</a></p>
HTML,
   ),
   array(
      'folder' => 2,
      'sorter' => '0030',
      'title' => 'Shop und Multichannel: überzeugend verkaufen',
      'permalink' => 'shop-multichannel',
      'seo_title' => 'Shop und Multichannel mit dbxapp',
      'description' => 'Produkte, Leistungen, Bestellungen, Zahlungen und Vertriebskanäle werden in dbxapp gemeinsam und nachvollziehbar verwaltet.',
      'keywords' => 'Onlineshop, Multichannel, Produkte, Bestellungen, Marktplätze, dbxapp Shop',
      'content' => <<<'HTML'
<p class="lead">Der dbxapp Shop verbindet ein verständliches Einkaufserlebnis mit der Verwaltung von Produkten, Bestellungen und Vertriebskanälen. Inhalte, Kundenkommunikation und Verkauf bleiben Teil derselben Plattform.</p>

<h2>Vom Angebot bis zur Bestellung</h2>
<p>Produkte und Leistungen lassen sich strukturiert präsentieren. Artikelgruppen, Attribute, Bilder, Versandinformationen und rechtliche Inhalte schaffen eine belastbare Grundlage für den Verkauf.</p>

<div class="row g-3 my-4">
  <div class="col-md-6"><div class="card h-100"><div class="card-body"><h3>Katalog und Produktdaten</h3><p>Produkte, Varianten, Medien und Zuordnungen übersichtlich verwalten und passend ausgeben.</p></div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body"><h3>Bestellungen und Zahlungen</h3><p>Warenkorb, Checkout und Bestellstatus in klaren Abläufen zusammenführen; Zahlungsanbieter können passend angebunden werden.</p></div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body"><h3>Vertriebskanäle</h3><p>Eigener Shop und weitere Kanäle können auf derselben Produktbasis arbeiten. Zuordnungen bleiben je Artikel kontrollierbar.</p></div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body"><h3>Individuelle Prozesse</h3><p>B2B-Angebote, besondere Freigaben oder interne Auftragswege lassen sich als Module und Workflows ergänzen.</p></div></div></div>
</div>

<h2>Kein Shop neben dem CMS</h2>
<p>Produktinformationen, Landingpages, Medien und Kontaktwege werden nicht in getrennten Systemen gepflegt. Das reduziert doppelte Arbeit und ermöglicht durchgängige Abläufe vom Inhalt bis zur Bearbeitung einer Bestellung.</p>

<p><a class="btn btn-primary" href="kontakt">Verkaufsprozess besprechen</a> <a class="btn btn-outline-secondary" href="?dbx_modul=dbxShop&amp;dbx_run1=catalog">Demo-Shop ansehen</a></p>
HTML,
   ),
   array(
      'folder' => 2,
      'sorter' => '0040',
      'title' => 'Individuelle Anwendungen und Workflows',
      'permalink' => 'individuelle-anwendungen',
      'seo_title' => 'Individuelle Anwendungen und Workflows mit dbxapp',
      'description' => 'dbxapp verbindet Datenmodelle, Formulare, Reports, Rechte und Workflows zu passgenauen Fachanwendungen und Portalen.',
      'keywords' => 'Fachanwendung, Workflow, Formular, Report, Dashboard, Systemintegration',
      'content' => <<<'HTML'
<p class="lead">Standardsoftware passt selten exakt zu gewachsenen Abläufen. Mit dbxapp entstehen Anwendungen, die Aufgaben, Daten und Rollen eines Unternehmens abbilden, ohne als neue Insellösung neben Website, CMS oder Shop zu stehen.</p>

<h2>Typische Einsatzbereiche</h2>
<ul>
  <li>CRM, Kunden- und Serviceportale</li>
  <li>Auftragsbearbeitung, Lager und Inventur</li>
  <li>Tickets, Anfragen und Freigaben</li>
  <li>Projektsteuerung und interne Verwaltungswerkzeuge</li>
  <li>Reports, Kennzahlen und Dashboards</li>
  <li>Anbindung bestehender Daten und Systeme</li>
</ul>

<h2>Vom Formular zum nachvollziehbaren Prozess</h2>
<p>Formulare erfassen strukturierte Daten. Reports machen sie auffindbar und auswertbar. Rechte bestimmen, wer lesen oder handeln darf. Workflows verbinden Status, Prüfungen und Aktionen zu einem klaren Ablauf. Jeder Schritt bleibt fachlich im zuständigen Modul.</p>

<div class="row g-3 my-4">
  <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h3>Gemeinsame Datenbasis</h3><p>DD und dbxDB sorgen für einheitliche Datenzugriffe, Rechte und Serverbindungen.</p></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h3>Einheitliche Bedienung</h3><p>dbxForm, dbxReport und dbxTPL geben neuen Modulen vertraute und erprobte Oberflächen.</p></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h3>Kontrolliert erweiterbar</h3><p>Neue Funktionen ergänzen bestehende Module, Benutzer und Rechte, statt einen parallelen Technikstapel einzuführen.</p></div></div></div>
</div>

<h2>Transparenz im Betrieb</h2>
<p>Das Admin-Dashboard bündelt Systemstatus, Laufzeiten, Datenbankwerte und Meldungen. Dadurch werden technische Auffälligkeiten sichtbar, bevor sie unbemerkt zu einem dauerhaften Problem werden.</p>

<p><a class="btn btn-primary" href="kontakt">Anwendungsfall beschreiben</a> <a class="btn btn-outline-secondary" href="plattform">Plattform verstehen</a></p>
HTML,
   ),
   array(
      'folder' => 2,
      'sorter' => '0050',
      'title' => 'Intranet und Portale für geschützte Zusammenarbeit',
      'permalink' => 'intranet-portale',
      'seo_title' => 'Intranet und Portale mit dbxapp',
      'description' => 'Geschützte Inhalte, Benutzer, Formulare, Reports und Workflows werden in dbxapp zu einem passenden Intranet oder Portal verbunden.',
      'keywords' => 'Intranet, Kundenportal, Mitarbeiterportal, Rechte, geschützte Inhalte',
      'content' => <<<'HTML'
<p class="lead">Ein Intranet oder Portal soll Informationen nicht nur anzeigen, sondern Menschen bei ihrer Arbeit unterstützen. dbxapp verbindet geschützte Inhalte mit Benutzern, Formularen, Reports und klaren Prozessen.</p>

<h2>Für Mitarbeitende, Kunden und Partner</h2>
<p>Bereiche können nach Benutzergruppen und Aufgaben getrennt werden. Öffentliche Informationen, interne Seiten und persönliche Vorgänge bleiben trotzdem Teil einer gemeinsamen Plattform.</p>

<div class="row g-3 my-4">
  <div class="col-md-6"><div class="card h-100"><div class="card-body"><h3>Geschützte Informationen</h3><p>Wissen, Dokumente und Hinweise gezielt für berechtigte Gruppen bereitstellen.</p></div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body"><h3>Formulare und Anfragen</h3><p>Interne Meldungen, Freigaben oder Serviceanfragen strukturiert erfassen und bearbeiten.</p></div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body"><h3>Persönliche Bereiche</h3><p>Benutzer sehen ihre eigenen Vorgänge, Bestellungen oder Aufgaben in einem klaren Kontext.</p></div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body"><h3>Flexible Bereitstellung</h3><p>Je nach Schutzbedarf im eigenen Intranet, auf Hosting, in der Cloud oder als betreute Installation.</p></div></div></div>
</div>

<h2>Mit den Anforderungen wachsen</h2>
<p>Ein Portal kann mit wenigen Inhalten und Formularen beginnen. Später lassen sich Reports, Workflows oder individuelle Module ergänzen, ohne Benutzerverwaltung und Bedienkonzept neu aufzubauen.</p>

<p><a class="btn btn-primary" href="kontakt">Portal planen</a> <a class="btn btn-outline-secondary" href="pakete">Betriebsmodelle vergleichen</a></p>
HTML,
   ),
   array(
      'folder' => 2,
      'sorter' => '0060',
      'title' => 'dbxKi: KI-Unterstützung mit Kontrolle',
      'permalink' => 'dbxki',
      'seo_title' => 'dbxKi: KI für CMS, SEO und Module',
      'description' => 'dbxKi bereitet Inhalte, SEO-Daten, Übersetzungen und Entwicklungsaufträge vor; Prüfung und Freigabe bleiben im dbxapp-Ablauf.',
      'keywords' => 'dbxKi, künstliche Intelligenz, CMS, SEO, Übersetzung, Modulentwicklung',
      'content' => <<<'HTML'
<p class="lead">dbxKi verbindet KI-Anbieter mit klar beschriebenen Aufgaben in dbxapp. Die KI liefert einen prüfbaren Vorschlag; Rechte, Daten, Freigabe und Veröffentlichung bleiben unter Kontrolle des Systems und seiner Benutzer.</p>

<h2>Unterstützung für Inhalte</h2>
<p>Redaktionen können Briefings für neue Seiten, Überarbeitungen, SEO-Daten, Zusammenfassungen und Übersetzungen erstellen. Quellen, Ziel und gewünschte Struktur werden im Auftrag festgehalten.</p>

<h2>Unterstützung für Module und Designs</h2>
<p>Auch technische Aufgaben lassen sich als vollständiges Paket vorbereiten: mit Regeln, vorhandenen Dateien, gewünschtem Ergebnis und einem importierbaren Antwortpaket. Änderungen werden vor der Übernahme geprüft.</p>

<ol class="my-4">
  <li><strong>Briefing:</strong> Ziel, Zielgruppe, Quellen und Grenzen verständlich beschreiben.</li>
  <li><strong>Auftragspaket:</strong> dbxKi stellt die notwendigen Regeln und Dateien zusammen.</li>
  <li><strong>Ergebnis prüfen:</strong> Inhalt, Dateiliste und Änderungen kontrollieren.</li>
  <li><strong>Freigeben:</strong> Erst die bewusste Übernahme verändert das System.</li>
</ol>

<h2>Datenschutz und Betriebsmodell</h2>
<p>Welche Daten an einen KI-Anbieter übermittelt werden dürfen, hängt vom gewählten Anbieter, Vertrag und Einsatzzweck ab. Sensible Daten gehören nur in einen dafür ausdrücklich freigegebenen Prozess. dbxKi macht den Auftrag nachvollziehbar, ersetzt aber keine organisatorische Datenschutzentscheidung.</p>

<p><a class="btn btn-primary" href="kontakt">KI-Einsatz besprechen</a> <a class="btn btn-outline-secondary" href="dokumentation">Technische Anleitung öffnen</a></p>
HTML,
   ),
   array(
      'folder' => 11,
      'sorter' => '0010',
      'title' => 'Die dbxapp Plattform: ein Kern, klare Bausteine',
      'permalink' => 'plattform',
      'seo_title' => 'dbxapp Plattform und Technik',
      'description' => 'Ein gemeinsamer Kern verbindet Benutzer, Rechte, Daten, Formulare, Reports, Templates, CMS, Shop und individuelle Module.',
      'keywords' => 'dbxapp Plattform, Architektur, Module, dbxDB, dbxForm, dbxReport, dbxTPL',
      'content' => <<<'HTML'
<p class="lead">dbxapp ist eine modulare Plattform für Websites, Shops und Geschäftsanwendungen. Wiederkehrende Aufgaben werden im Kern einheitlich gelöst, während die Fachlogik in klar abgegrenzten Modulen bleibt.</p>

<h2>Gemeinsame Grundlagen</h2>
<div class="row g-3 my-4">
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><h3>Daten</h3><p>dbxDB und DD verbinden Datenzugriff, Tabellenbeschreibung, Rechte und unterschiedliche Datenbankserver.</p></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><h3>Formulare</h3><p>dbxForm nutzt FD, Validierung, Meldungen, Freigaben und Ajax nach einem gemeinsamen Vertrag.</p></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><h3>Listen</h3><p>dbxReport stellt Filter, Tabellen, Summen, Aktionen und Exporte einheitlich bereit.</p></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><h3>Darstellung</h3><p>dbxTPL trennt Daten und Fachlogik von wiederverwendbaren Templates und Designs.</p></div></div></div>
</div>

<h2>Benutzer, Rechte und Sicherheit</h2>
<p>Module, Inhalte und Daten verwenden denselben Benutzer- und Gruppenkontext. DD-Rechte schützen Datenoperationen. Formulare und schreibende Aktionen erhalten zusätzlich die jeweils notwendige Absicherung gegen unbeabsichtigte Browseraktionen.</p>

<h2>Desktop, Mobile, Intranet und Cloud</h2>
<p>Dies sind keine getrennten Produkte, sondern Zugriffs- und Betriebsformen derselben Plattform. Die fachliche Codebasis bleibt gemeinsam; Design, Bereitstellung und Zugriffskontext können angepasst werden.</p>

<h2>Schnittstellen und Erweiterungen</h2>
<p>Neue Anforderungen werden als Module ergänzt. Sie können vorhandene Daten, Formulare, Reports, Workflows und CMS-Seiten nutzen. Bestehende Systeme lassen sich über klar definierte Schnittstellen einbinden.</p>

<p><a class="btn btn-primary" href="entwickler">Entwicklerüberblick</a> <a class="btn btn-outline-secondary" href="pakete">Betrieb und Pakete</a></p>
HTML,
   ),
   array(
      'folder' => 11,
      'sorter' => '0020',
      'title' => 'Pakete und Betrieb passend zum Projekt',
      'permalink' => 'pakete',
      'seo_title' => 'dbxapp Pakete, Full Service und Self-Hosting',
      'description' => 'Website, Business, Intranet und individuelle Projekte lassen sich mit Full Service, Self-Hosting oder einer passenden Vereinbarung betreiben.',
      'keywords' => 'dbxapp Pakete, Full Service, Self-Hosting, Intranet, Enterprise, Website',
      'content' => <<<'HTML'
<p class="lead">Nicht jedes Projekt braucht denselben Umfang. Die Pakete beschreiben sinnvolle Einstiege; Funktionen und Betrieb werden anschließend passend zum tatsächlichen Bedarf vereinbart.</p>

<div class="table-responsive my-4">
<table class="table table-striped align-middle">
  <thead><tr><th>Angebot</th><th>Geeignet für</th><th>Typischer Schwerpunkt</th></tr></thead>
  <tbody>
    <tr><th>Website</th><td>Unternehmen und Organisationen mit einem professionellen Internetauftritt</td><td>CMS, Medien, Design, Formulare und Mehrsprachigkeit</td></tr>
    <tr><th>Business</th><td>Projekte, die Inhalte und Verkauf oder kleinere Anwendungen verbinden</td><td>Website, CMS, Shop, Benutzer und erste Prozessmodule</td></tr>
    <tr><th>Intranet</th><td>Interne und geschützte Zusammenarbeit</td><td>Benutzer, Rechte, Inhalte, Formulare, Reports und Workflows</td></tr>
    <tr><th>Individuell</th><td>Fachanwendungen und Systemintegration</td><td>Eigene Datenmodelle, Module, Schnittstellen und komplexere Abläufe</td></tr>
  </tbody>
</table>
</div>

<h2>Betriebsmodelle</h2>
<div class="row g-3 my-4">
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><h3>Full Service</h3><p>Einrichtung, Hosting, Wartung und Weiterentwicklung werden passend zum vereinbarten Leistungsumfang übernommen.</p></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><h3>Self-Hosting</h3><p>Die eigene IT verantwortet Installation, Updates, Backups, Mail-Konfiguration und sicheren Betrieb.</p></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><h3>Individuelle Vereinbarung</h3><p>Aufgaben können geteilt werden, etwa eigener Betrieb mit Unterstützung bei Einrichtung oder Updates.</p></div></div></div>
</div>

<h2>Non-Profit</h2>
<p>Für gemeinnützige Organisationen kann ein Sondertarif oder ein reduzierter Einstieg vereinbart werden. Technisch bleibt es dieselbe belastbare Plattform; entscheidend sind Ziel, Umfang und verfügbare Mittel.</p>

<p><a class="btn btn-primary" href="kontakt">Passendes Angebot klären</a> <a class="btn btn-outline-secondary" href="demo">Demo testen</a></p>
HTML,
   ),
   array(
      'folder' => 11,
      'sorter' => '0030',
      'title' => 'Referenzen und realistische Beispielprojekte',
      'permalink' => 'referenzen',
      'seo_title' => 'dbxapp Referenzen und Beispielprojekte',
      'description' => 'Realistisch beschriebene Beispielprojekte zeigen, wie CMS, Shop, Portale, Formulare, Reports und Workflows zusammenspielen.',
      'keywords' => 'dbxapp Referenzen, Beispielprojekt, CMS, Shop, Portal, Workflow',
      'content' => <<<'HTML'
<p class="lead">Eine Plattform überzeugt nicht durch allgemeine Versprechen, sondern durch nachvollziehbare Abläufe. Solange ein Projekt nicht als veröffentlichte Kundenreferenz freigegeben ist, kennzeichnen wir es ausdrücklich als Beispielprojekt.</p>

<div class="row g-4 my-4">
  <div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <span class="badge text-bg-secondary mb-2">Beispielprojekt</span>
    <h2>Service- und Kundenportal</h2>
    <p><strong>Ausgangslage:</strong> Anfragen kommen über verschiedene Kanäle und sind schwer nachzuverfolgen.</p>
    <p><strong>Umsetzung:</strong> Kontaktformular, geschützter Kundenbereich, Statusreport und Benachrichtigungen auf einer gemeinsamen Datenbasis.</p>
    <p><strong>Ergebnis:</strong> Ein klarer Vorgang vom Eingang bis zur beantworteten Anfrage.</p>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <span class="badge text-bg-secondary mb-2">Beispielprojekt</span>
    <h2>Intranet mit Freigaben</h2>
    <p><strong>Ausgangslage:</strong> Informationen, Formulare und Zuständigkeiten liegen in getrennten Werkzeugen.</p>
    <p><strong>Umsetzung:</strong> Geschützte Inhalte, Benutzergruppen, Formulare, Reports und ein mehrstufiger Workflow.</p>
    <p><strong>Ergebnis:</strong> Mitarbeitende finden Informationen und Aufgaben in derselben Oberfläche.</p>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <span class="badge text-bg-secondary mb-2">Beispielprojekt</span>
    <h2>Shop mit individueller Bearbeitung</h2>
    <p><strong>Ausgangslage:</strong> Standardbestellungen und besondere B2B-Anfragen sollen gemeinsam bearbeitet werden.</p>
    <p><strong>Umsetzung:</strong> Produktkatalog, Warenkorb, Bestellungen, kundenspezifische Formulare und interne Statusschritte.</p>
    <p><strong>Ergebnis:</strong> Verkauf und anschließende Bearbeitung bleiben durchgängig verbunden.</p>
  </div></div></div>
</div>

<h2>Was eine veröffentlichte Referenz zeigen soll</h2>
<p>Ausgangssituation, umgesetzte Funktionen, aussagekräftige Screenshots, messbares oder konkret beobachtbares Ergebnis und die eingesetzten dbxapp-Module. Kundennamen und Projektdaten werden nur mit ausdrücklicher Freigabe veröffentlicht.</p>

<p><a class="btn btn-primary" href="kontakt">Eigenen Anwendungsfall besprechen</a></p>
HTML,
   ),
   array(
      'folder' => 11,
      'sorter' => '0040',
      'title' => 'dbxapp Demo kostenlos testen',
      'permalink' => 'demo',
      'seo_title' => 'dbxapp Demo für Windows anfordern',
      'description' => 'Fordern Sie die portable dbxapp Windows-Demo an und testen Sie CMS, Medien, Module und typische Abläufe lokal.',
      'keywords' => 'dbxapp Demo, Windows Demo, portable Anwendung, SQLite, Download',
      'content' => <<<'HTML'
<p class="lead">Mit der kostenlosen Demo gewinnen Sie einen ersten Eindruck davon, wie CMS-Inhalte, Medien, Benutzer und Module in dbxapp zusammenspielen.</p>

<h2>Was Sie testen können</h2>
<ul>
  <li>Seiten und Inhalte im CMS ansehen und bearbeiten</li>
  <li>Medien und Designs kennenlernen</li>
  <li>Shop- und Modulfunktionen ausprobieren</li>
  <li>Administration, Reports und typische Abläufe erkunden</li>
</ul>

<h2>Portable Windows-Version</h2>
<p>Die Demo wird als ZIP-Paket bereitgestellt und kann lokal unter Windows gestartet werden. Sie verwendet eine vorbereitete Umgebung und SQLite-Datenbanken; ein externer MySQL-Server ist für diesen Test nicht erforderlich. Vor dem Start sollten die Installationshinweise gelesen werden.</p>

<div class="alert alert-info"><strong>Wichtig:</strong> Die Demo ist eine Testumgebung und kein produktiver Betrieb. Verwenden Sie darin keine echten vertraulichen oder personenbezogenen Geschäftsdaten.</div>

<h2>Download-Link anfordern</h2>
[modul=dbxDownLoad]dbx_run1=form[/modul]

<h2>Noch unsicher, was Sie testen sollten?</h2>
<p><a class="btn btn-primary" href="kontakt">Frage zur Demo stellen</a> <a class="btn btn-outline-secondary" href="dokumentation">Installationshinweise öffnen</a></p>
HTML,
   ),
   array(
      'folder' => 3,
      'sorter' => '0010',
      'title' => 'Für Entwickler: dbxapp sauber erweitern',
      'permalink' => 'entwickler',
      'seo_title' => 'dbxapp für Entwickler: Architektur und Module',
      'description' => 'Ein Überblick über Module, dbxDB, DD, FD, dbxForm, dbxReport, dbxTPL, Self-Hosting und die Erweiterung von dbxapp.',
      'keywords' => 'dbxapp Entwickler, Modul, dbxDB, dbxForm, dbxReport, dbxTPL, DD, FD',
      'content' => <<<'HTML'
<p class="lead">dbxapp stellt wiederkehrende technische Aufgaben zentral bereit. Entwickler konzentrieren sich auf die Fachlogik eines Moduls und nutzen für Daten, Formulare, Reports und Templates ein gemeinsames Vorgehen.</p>

<h2>Verbindliche Bausteine</h2>
<dl class="row">
  <dt class="col-sm-3">dbxDB und DD</dt><dd class="col-sm-9">Datenzugriff, Serverbindung, Tabellenbeschreibung, Rechte und automatische Systemfelder.</dd>
  <dt class="col-sm-3">dbxForm und FD</dt><dd class="col-sm-9">Felder, Validierung, sprachabhängige Meldungen, Speichern und Ajax-Verhalten.</dd>
  <dt class="col-sm-3">dbxReport</dt><dd class="col-sm-9">Filter, Listen, Summen, Aktionen, Pagination und Exporte auf Basis derselben Definitionen.</dd>
  <dt class="col-sm-3">dbxTPL</dt><dd class="col-sm-9">Wiederverwendbare Darstellung ohne Datenbankzugriff und Fachmutation im Template.</dd>
</dl>

<h2>Module statt Sonderwege</h2>
<p>Ein Modul kapselt seine Fachlogik, DDs, FDs und Templates. Es verwendet die vorhandenen Kernel-Fähigkeiten direkt und führt keinen parallelen Datenbank-, Formular-, Template- oder Sicherheitsstapel ein.</p>

<h2>Gemischte Datenbankserver</h2>
<p>Jedes DD kann an einen eigenen Server gebunden sein. Eine Installation darf SQLite- und MySQL-Tabellen kombinieren. Modulcode greift deshalb nie direkt auf eine konkrete Datenbankdatei oder PDO-Verbindung zu, sondern ausschließlich über dbxDB und das zuständige DD.</p>

<h2>Self-Hosting und Updates</h2>
<p>Lokale Konfiguration und Daten bleiben von Release-Dateien getrennt. Datenbankänderungen werden versionsbezogen über DD-Migrationen ausgeführt und müssen bestehende Serverbindungen respektieren.</p>

<p><a class="btn btn-primary" href="dokumentation">Technische Dokumentation</a> <a class="btn btn-outline-secondary" href="kontakt">Entwicklungsprojekt anfragen</a></p>
HTML,
   ),
   array(
      'folder' => 3,
      'sorter' => '0020',
      'title' => 'Dokumentation und Tutorials',
      'permalink' => 'dokumentation',
      'seo_title' => 'dbxapp Dokumentation und Tutorials',
      'description' => 'Einstieg in Benutzerhandbuch, Entwicklerdokumentation und Tutorials für dbxapp.',
      'keywords' => 'dbxapp Dokumentation, Benutzerhandbuch, Entwicklerdokumentation, Tutorial',
      'content' => <<<'HTML'
<p class="lead">Marketingseiten erklären Nutzen und Angebot. Konkrete Bedienung, Klassen, Modulaufrufe und technische Abläufe gehören in die eigenständige Dokumentation.</p>

<div class="row g-4 my-4">
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><h2>Benutzerhandbuch</h2><p>Schritt-für-Schritt-Anleitungen für CMS, Medien, Shop, Benutzer und tägliche Aufgaben.</p></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><h2>Entwicklerdokumentation</h2><p>Architektur, Kernel-Klassen, DD, FD, Module, Templates, Sicherheit und verbindliche Vorgehensweisen.</p></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><h2>Tutorials</h2><p>Zusammenhängende Beispiele für typische Verwaltungs-, Content-, Shop-, Workflow- und Designaufgaben.</p></div></div></div>
</div>

<p><a class="btn btn-primary" href="https://doku.dbxapp.de/">Dokumentation öffnen</a></p>

<div class="alert alert-secondary">Die bisherigen lokalen Hilfe- und Tutorialseiten bleiben erhalten, werden aber nicht mehr als öffentliche Marketingseiten indexiert.</div>
HTML,
   ),
   array(
      'folder' => 11,
      'sorter' => '0050',
      'title' => 'Über uns und Kontakt',
      'permalink' => 'kontakt',
      'seo_title' => 'dbxapp kennenlernen und Projekt besprechen',
      'description' => 'Beschreiben Sie Ihre Ziele, vorhandenen Systeme und gewünschten Abläufe. Wir klären gemeinsam, ob und wie dbxapp dazu passt.',
      'keywords' => 'dbxapp Kontakt, Beratung, Projektanfrage, CMS, Shop, Fachanwendung',
      'content' => <<<'HTML'
<p class="lead">Eine gute Lösung beginnt nicht mit möglichst vielen Funktionen, sondern mit einem klaren Verständnis der Aufgabe. Beschreiben Sie, was heute schwierig ist und welches Ergebnis erreicht werden soll.</p>

<h2>So gehen wir vor</h2>
<ol>
  <li><strong>Ausgangslage verstehen:</strong> Ziele, Benutzer, Daten, vorhandene Systeme und Rahmenbedingungen klären.</li>
  <li><strong>Sinnvollen Einstieg bestimmen:</strong> Website, Shop, Portal oder Fachanwendung fachlich abgrenzen.</li>
  <li><strong>Vorgehen transparent planen:</strong> Funktionen, Betrieb, Verantwortlichkeiten und nächste Schritte nachvollziehbar festhalten.</li>
  <li><strong>Schrittweise umsetzen:</strong> Mit einem prüfbaren Kern beginnen und Erweiterungen kontrolliert ergänzen.</li>
</ol>

<h2>Was in eine erste Anfrage gehört</h2>
<p>Eine kurze Beschreibung des Problems, die wichtigsten Benutzergruppen, vorhandene Systeme, gewünschte Funktionen und die bevorzugte Betriebsform. Vertrauliche Detaildaten sind für den ersten Kontakt nicht erforderlich.</p>

[modul=dbxContact]dbx_run1=form[/modul]
HTML,
   ),
);

$home = array(
   'title' => 'dbxapp – CMS, Shop und individuelle Anwendungen',
   'seo_title' => 'dbxapp: CMS, Shop und individuelle Anwendungen',
   'description' => 'dbxapp verbindet Website und CMS, Shop, Portale und individuelle Geschäftsanwendungen auf einer gemeinsamen modularen Plattform.',
   'keywords' => 'dbxapp, CMS, Shop, Fachanwendung, Portal, Workflow, Self-Hosting',
   'content' => <<<'HTML'
<p class="lead">dbxapp verbindet Website und CMS, Shop, Portale und individuelle Geschäftsanwendungen auf einer gemeinsamen Plattform. Inhalte, Benutzer, Daten und Abläufe greifen ineinander, statt in getrennten Systemen gepflegt zu werden.</p>

<div class="row g-4 my-4">
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2>Website und CMS</h2><p>Seiten, Medien, Designs und mehrsprachige Inhalte professionell verwalten.</p><a href="cms-website">Mehr erfahren</a></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2>Shop und Multichannel</h2><p>Produkte, Bestellungen und Vertriebskanäle nachvollziehbar verbinden.</p><a href="shop-multichannel">Mehr erfahren</a></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2>Individuelle Anwendungen</h2><p>Formulare, Reports und Workflows passend zu den tatsächlichen Aufgaben.</p><a href="individuelle-anwendungen">Mehr erfahren</a></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body"><h2>Intranet und Portale</h2><p>Geschützte Inhalte und Funktionen für Mitarbeitende, Kunden und Partner.</p><a href="intranet-portale">Mehr erfahren</a></div></div></div>
</div>

<h2>Weniger Insellösungen, klarere Abläufe</h2>
<p>Viele Unternehmen pflegen Inhalte, Kundendaten, Bestellungen, Formulare und interne Vorgänge in getrennten Werkzeugen. Das erzeugt doppelte Arbeit, Medienbrüche und unklare Zuständigkeiten. dbxapp stellt dafür einen gemeinsamen Kern bereit: Benutzer und Rechte, Datenzugriff, Formulare, Reports, Templates, Medien und Module folgen denselben Regeln.</p>

<h2>Für wen eignet sich dbxapp?</h2>
<p>Für Unternehmen und Organisationen, die mit einer professionellen Website starten oder bestehende digitale Abläufe zusammenführen möchten. Besonders sinnvoll ist die Plattform, wenn Standardfunktionen mit individuellen Anforderungen verbunden werden sollen.</p>

<h2>Flexibel betreiben</h2>
<p>dbxapp kann als betreuter Full Service, auf eigener Infrastruktur, im Intranet oder auf geeignetem Hosting betrieben werden. Die fachliche Anwendung bleibt dieselbe; Betrieb und Verantwortung werden passend zum Projekt gewählt.</p>

<h2>Warum eine gemeinsame Plattform?</h2>
<ul>
  <li>Inhalte und Funktionen werden nicht mehrfach gepflegt.</li>
  <li>Benutzer und Rechte gelten über Modulgrenzen hinweg.</li>
  <li>Neue Anforderungen ergänzen die bestehende Lösung.</li>
  <li>Systemstatus und Laufzeiten bleiben nachvollziehbar.</li>
  <li>KI-Unterstützung arbeitet mit Briefing, Prüfung und Freigabe.</li>
</ul>

<div class="alert alert-primary">
  <strong>dbxapp kennenlernen:</strong>
  <a class="btn btn-primary ms-md-3 mt-2 mt-md-0" href="demo">Demo anfordern</a>
  <a class="btn btn-outline-primary ms-2 mt-2 mt-md-0" href="kontakt">Projekt besprechen</a>
</div>
HTML,
);

$basePage = array(
   'activ' => 1,
   'template' => 'c-marketing-body1-footer',
   'addmenu' => 1,
   'class' => '',
   'target' => '',
   'group_read' => '*',
   'meta_robots' => 'index,follow',
   'seo_image_id' => 0,
   'data' => '',
   'modules' => '',
   'thesar' => '',
   'hero_template' => 'none',
   'hero_image_id' => 'none',
   'hero_margin_top' => '0',
   'hero_height' => 'parent',
   'hero_variant' => 'parent',
   'gallery_template' => '',
   'gallery_visible_count' => '',
   'gallery_image_size' => '',
   'gallery_overflow' => '',
   'gallery_click_behavior' => '',
   'hero_sticky' => '0',
   'hero_scroll_layer' => 'under',
   'gallery_lightbox_width' => 'parent',
   'lng_sync' => 'auto',
   'lng_rev' => 1,
   'lng_synced_rev' => 0,
);

$folderChanges = array(
   2 => array('name' => 'Lösungen', 'parent_id' => 1, 'group_read' => '*', 'sorter' => '0010'),
   3 => array('name' => 'Entwickler', 'parent_id' => 1, 'group_read' => '*', 'sorter' => '0030'),
   11 => array('name' => 'Plattform', 'parent_id' => 1, 'group_read' => '*', 'sorter' => '0020'),
   12 => array('name' => 'Archiv: Beispiele', 'parent_id' => $trashId, 'group_read' => 'admin'),
   15 => array('name' => 'Dokumentation und Tutorials', 'parent_id' => 9, 'group_read' => '*'),
   18 => array('name' => 'Archiv: Pakete', 'parent_id' => $trashId, 'group_read' => 'admin'),
   19 => array('name' => 'trash', 'parent_id' => 0, 'group_read' => 'admin'),
);

$result = array(
   'mode' => $apply ? 'apply' : 'dry-run',
   'language' => 'de',
   'backup' => '',
   'folders_planned' => count($folderChanges),
   'archive_pages_planned' => count($archiveIds),
   'target_pages_planned' => count($pages) + 1,
   'created' => 0,
   'updated' => 0,
   'archived' => 0,
   'documentation_noindex' => 0,
);

if (!$apply) {
   echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
   exit(0);
}

$dbFile = $base . '/dbx/modules/dbx/db/dbxContent.db3';
if (!is_file($dbFile) || (int)filesize($dbFile) <= 0) {
   fwrite(STDERR, "Content-Datenbank nicht gefunden: {$dbFile}\n");
   exit(1);
}
$backupDir = dirname($dbFile) . '/backup';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
   fwrite(STDERR, "Backup-Verzeichnis konnte nicht angelegt werden.\n");
   exit(1);
}
$backupFile = $backupDir . '/dbxContent-before-marketing-de-' . date('Ymd-His') . '.db3';
if (!copy($dbFile, $backupFile)) {
   fwrite(STDERR, "Datenbank-Backup konnte nicht erstellt werden.\n");
   exit(1);
}
$result['backup'] = $backupFile;

if ($db->begin($contentDd) !== 1) {
   fwrite(STDERR, "Die dbxDB-Transaktion konnte nicht gestartet werden.\nBackup: {$backupFile}\n");
   exit(1);
}

try {
   $trash = $db->select1($folderDd, $trashId, 'id,name', 0);
   if (!is_array($trash) || (int)($trash['id'] ?? 0) !== $trashId) {
      throw new RuntimeException('Der deutsche Ordner /trash mit ID 19 fehlt.');
   }

   foreach ($folderChanges as $id => $changes) {
      if ($db->update($folderDd, $changes, (int)$id, 0) !== 1) {
         throw new RuntimeException("Ordner {$id} konnte nicht aktualisiert werden.");
      }
      $result['updated']++;
   }

   foreach ($archiveIds as $id) {
      $source = $db->select1($contentDd, $id, 'id,title,permalink', 0);
      if (!is_array($source) || (int)($source['id'] ?? 0) !== $id) {
         throw new RuntimeException("Archivquelle content_de#{$id} fehlt.");
      }
      if ($db->update($contentDd, array(
         'folder' => $trashId,
         'activ' => 0,
         'addmenu' => 0,
         'group_read' => 'admin',
         'meta_robots' => 'noindex,nofollow',
      ), $id, 0) !== 1) {
         throw new RuntimeException("Seite content_de#{$id} konnte nicht nach /trash verschoben werden.");
      }
      $result['archived']++;
   }

   $homeRow = $db->select1($contentDd, 1, 'id,lng_rev', 0);
   if (!is_array($homeRow) || (int)($homeRow['id'] ?? 0) !== 1) {
      throw new RuntimeException('Die konfigurierte deutsche Startseite content_de#1 fehlt.');
   }
   $homeUpdate = array_merge($basePage, $home, array(
      'folder' => 11,
      'permalink' => 'home',
      'sorter' => '0000',
      'addmenu' => 0,
      'lng_rev' => max(1, (int)($homeRow['lng_rev'] ?? 1)) + 1,
   ));
   unset($homeUpdate['lng_synced_rev']);
   if ($db->update($contentDd, $homeUpdate, 1, 0) !== 1) {
      throw new RuntimeException('Die deutsche Startseite konnte nicht aktualisiert werden.');
   }
   $result['updated']++;

   foreach ($pages as $page) {
      $permalink = (string)$page['permalink'];
      $existing = $db->select1($contentDd, array('permalink' => $permalink), 'id,lng_uid,lng_rev', 0);
      $record = array_merge($basePage, $page);
      if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
         $id = (int)$existing['id'];
         $record['lng_rev'] = max(1, (int)($existing['lng_rev'] ?? 1)) + 1;
         unset($record['lng_synced_rev']);
         if ($db->update($contentDd, $record, $id, 0) !== 1) {
            throw new RuntimeException("Zielseite {$permalink} konnte nicht aktualisiert werden.");
         }
         $result['updated']++;
         continue;
      }

      $record['lng_uid'] = dbxContentLngSync::newUid('p');
      if ($db->insert($contentDd, $record, 0, 1, 0, 1) !== 1 || (int)$db->get_insert_id() <= 0) {
         throw new RuntimeException("Zielseite {$permalink} konnte nicht angelegt werden.");
      }
      $result['created']++;
   }

   $documentationRows = $db->select(
      $contentDd,
      'folder IN (10,15)',
      'id',
      'id',
      'ASC',
      '',
      0,
      0,
      0
   );
   foreach (is_array($documentationRows) ? $documentationRows : array() as $row) {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) {
         continue;
      }
      if ($db->update($contentDd, array(
         'meta_robots' => 'noindex,follow',
         'addmenu' => 0,
      ), $id, 0) !== 1) {
         throw new RuntimeException("Dokumentationsseite content_de#{$id} konnte nicht auf noindex gesetzt werden.");
      }
      $result['documentation_noindex']++;
   }

   foreach (array(
      33 => array('title' => 'DataDic Übersicht'),
      41 => array('title' => 'Sprachen & Übersetzung — Benutzerhandbuch'),
      55 => array('addmenu' => 0),
   ) as $id => $changes) {
      if ($db->update($contentDd, $changes, $id, 0) !== 1) {
         throw new RuntimeException("Deutsche Seite content_de#{$id} konnte nicht bereinigt werden.");
      }
      $result['updated']++;
   }

   if ($db->commit($contentDd) !== 1) {
      throw new RuntimeException('Die dbxDB-Transaktion konnte nicht abgeschlossen werden.');
   }
} catch (Throwable $e) {
   $db->rollback($contentDd);
   fwrite(STDERR, $e->getMessage() . "\nBackup: {$backupFile}\n");
   exit(1);
}

$cacheStats = dbxContentPageCache::invalidateAll();
dbxContentSitemap::invalidate();
$result['cache'] = $cacheStats;

$activeTargets = array('home');
foreach ($pages as $page) {
   $activeTargets[] = (string)$page['permalink'];
}
$targetRows = array();
foreach ($activeTargets as $permalink) {
   $row = $db->select1(
      $contentDd,
      array('permalink' => $permalink),
      'id,permalink,title,folder,activ,meta_robots',
      0
   );
   if (is_array($row)
      && (int)($row['id'] ?? 0) > 0
      && (int)($row['activ'] ?? 0) === 1
      && (string)($row['meta_robots'] ?? '') === 'index,follow') {
      $targetRows[] = $row;
   }
}
$result['verified_targets'] = count($targetRows);
$result['verified_total_de'] = (int)$db->count($contentDd, '1=1');

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
