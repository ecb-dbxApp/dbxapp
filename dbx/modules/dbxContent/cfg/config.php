<?php 
$config['dbxConfig_modul'] = 'secure';
$config['groups'][0] = '*';
$config['permalink_mode'] = 'content';
$config['root'] = '0';
$config['lng_translate_provider'] = 'copy';
$config['lng_translate_api_key'] = '';
$config['lng_translate_api_url'] = '';
$config['lng_translate_model'] = 'gpt-4o-mini';
$config['context_help_provision_version'] = 7;
$config['default_seo_image_id'] = 111;

/*
 * Deutsche Marketing-Weiterleitungen.
 *
 * Die Quelldatensätze bleiben als inaktive Archive im Ordner /trash
 * erhalten. Nur ihre bisherigen öffentlichen Permalinks werden dauerhaft auf
 * die konsolidierten deutschen Zielseiten geführt. Andere Sprachen bleiben
 * bis zu ihrer eigenen redaktionellen Überarbeitung unverändert.
 */
$config['permalink_redirects']['de'] = array(
   'kunden-nutzen' => '/',
   'workflows-module' => 'individuelle-anwendungen',
   'entwickler-plattform' => 'entwickler',
   'content-cms-medien' => 'cms-website',
   'home-fuer-entwickler-dbxapp-medien' => 'cms-website',
   'dbxapp-plattform' => 'plattform',
   'beispiele-grid' => 'referenzen',
   'technische-dokumentation' => 'dokumentation',
   'dbxki-ki-automatisierung-cms' => 'dbxki',
   'dbxapp-cms-ki' => 'dbxki',
   'beispiele-gallery-1' => 'referenzen',
   'beispiele-gallery-2' => 'referenzen',
   'beispiele-gallery-3' => 'referenzen',
   'dbxapp-fachanwendungen-integration' => 'individuelle-anwendungen',
   'dbxapp-desktop-mobile-intranet-cloud' => 'plattform',
   'dbxapp-demo-einstieg' => 'demo',
   'dbxapp-website-paket' => 'pakete',
   'dbxapp-cms-ki-paket' => 'pakete',
   'dbxapp-individuelle-anwendung' => 'individuelle-anwendungen',
   'dbxapp-download-selbsthosting' => 'pakete',
   'dbxapp-full-service-paket' => 'pakete',
   'dbxapp-paket-demo' => 'demo',
   'dbxapp-paket-non-profit' => 'pakete',
   'dbxapp-paket-business' => 'pakete',
   'dbxapp-paket-intranet' => 'pakete',
   'dbxapp-paket-enterprise' => 'pakete',
   'jedes-design-mit-dbxapp' => 'cms-website',
   'ki-auftrag-content-erstellen' => 'dbxki',
   'ki-auftrag-module-erstellen' => 'dbxki',
   'dashboard-kontrolle-geschwindigkeit-ueberblick' => 'individuelle-anwendungen',
   'dbxapp-sys' => '/',
   'info-cms' => 'cms-website',
   'info-shop' => 'shop-multichannel',
   'info-app' => 'individuelle-anwendungen',
   'info-mobile' => 'plattform',
   'info-desktop' => 'plattform',
   'info-ki' => 'dbxki',
   'info-erweiterbar' => 'plattform',
);
