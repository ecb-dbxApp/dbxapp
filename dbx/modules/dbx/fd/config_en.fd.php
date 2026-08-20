<?php
$messages = array();
$messages['module_subtitle'] = 'Module: dbx';
$messages['config_info'] = 'Edit the dbx system configuration.';
$messages['config_save'] = 'Save configuration';
$messages['config_saved'] = 'The configuration was saved successfully.';
$messages['config_save_error'] = 'The configuration could not be saved.';
$messages['no_entries'] = 'No entries available.';
$messages['no_sql_servers'] = 'No SQL servers are configured. dbxDB automatically resolves module SQLite files (*.db3) below dbx/modules/*/db/.';
$messages['edit_section'] = 'Edit {section}.';
$messages['save_section'] = 'Save {section}';
$messages['create_section'] = 'Create {section}';
$messages['delete_section'] = 'Delete {section}';
$messages['check_new_name'] = 'Please check the new name.';
$messages['enter_new_name'] = 'Please enter the new name.';
$messages['entry_exists'] = 'Entry “{entry}” already exists.';
$messages['entry_create_error'] = 'The entry could not be created.';
$messages['entry_created'] = 'Entry “{entry}” was created.';
$messages['entry_delete_error'] = 'Entry “{entry}” could not be deleted.';
$messages['entry_deleted'] = 'Entry “{entry}” was deleted.';
$messages['check_input'] = 'Please check your input.';
$messages['module_sqlite_forbidden'] = 'Module SQLite files (*.db3) do not belong in the system configuration.';
$messages['entry_save_error'] = 'The entry could not be saved.';
$messages['entry_saved'] = 'The entry was saved.';
$messages['new_entry_info'] = 'Create a new {section} entry.';
$messages['label_new_entry'] = 'New entry';
$messages['tooltip_new_entry'] = 'Name of the new entry';
$messages['edit_entry_info'] = 'Edit {section} “{entry}”.';
$messages['confirm_delete_entry'] = 'Really delete {section} “{entry}”?';

// Alias: Formular-FD zeigt auf cfg/config.dd.php (eine Quelle).
require dirname(__DIR__) . '/cfg/config.dd.php';

// Die technische Feldstruktur bleibt in config.dd.php zentral; nur UI-Texte
// werden in der FD-Sprachversion ersetzt.
$fd_text = array(
    'Versionsnummer' => 'Version number',
    'Interne Config-Versionsnummer des dbx-Moduls.' => 'Internal configuration version number of the dbx module.',
    'Version muss eine Zahl sein.' => 'The version must be a number.',
    'Modul aktiv' => 'Module active',
    'Schaltet das Kernmodul dbx ein oder aus.' => 'Enables or disables the dbx core module.',
    'Zugriff' => 'Access',
    'Benutzergruppen mit Zugriff auf das Systemmodul.' => 'User groups with access to the system module.',
    'Standard-Sprache' => 'Default language',
    'Fallback-Sprache des Systems.' => 'Fallback language of the system.',
    'Verfuegbare Sprachen' => 'Available languages',
    'Sprachen, die Benutzer waehlen duerfen.' => 'Languages users may select.',
    'Standard-Skin' => 'Default skin',
    'Farbschema (Skin) fuer neue Besucher.' => 'Color scheme (skin) for new visitors.',
    'Design (Benutzer)' => 'Design (users)',
    'Layout-Paket fuer oeffentliche Seiten.' => 'Layout package for public pages.',
    'Design (Admin)' => 'Design (admin)',
    'Layout-Paket fuer den Admin-Bereich.' => 'Layout package for the admin area.',
    'Session-DB speichern' => 'Save session database',
    'Normale HTTP-Requests und HTML-AJAX-Requests am Request-Ende in der Session-Datenbank speichern.' => 'Save normal HTTP requests and HTML AJAX requests in the session database at the end of the request.',
    'Permalinks' => 'Permalinks',
    'Schoene URLs / Permalink-Aufloesung aktivieren.' => 'Enable readable URLs and permalink resolution.',
    'System-Cache' => 'System cache',
    'Templates und Modul-Configs in der Session cachen.' => 'Cache templates and module configurations in the session.',
    'Content-Cache' => 'Content cache',
    'Gerenderte Content-Seiten und Permalink-Index als HTML cachen.' => 'Cache rendered content pages and the permalink index as HTML.',
    'SysMsg-Level' => 'SysMsg level',
    'Steuert, welche Systemmeldungen in dbxSysMsg gespeichert werden.' => 'Controls which system messages are saved in dbxSysMsg.',
    'Performance-Level' => 'Performance level',
    'Steuert, ob keine Performance-Daten, nur Hauptkennzahlen oder alle Detail-Timer gespeichert werden.' => 'Controls whether no performance data, only main metrics, or all detailed timers are saved.',
    'Performance: Sample-Rate' => 'Performance: sample rate',
    'Nur jeden N-ten Request messen (1 = jeder Request).' => 'Measure only every Nth request (1 = every request).',
    'Performance: Aufbewahrung (Tage)' => 'Performance: retention (days)',
    'Alte Performance-Daten nach X Tagen loeschen.' => 'Delete old performance data after X days.',
    'Performance: Langsam ab (ms)' => 'Performance: slow from (ms)',
    'Schwellwert fuer langsame Requests im Dashboard.' => 'Threshold for slow requests in the dashboard.',
    'Intro-Seite' => 'Intro page',
    'Intro beim ersten Besuch anzeigen.' => 'Show the intro on the first visit.',
    'Wartungsmodus' => 'Maintenance mode',
    'Anwendung als in Ueberarbeitung markieren.' => 'Mark the application as under maintenance.',
    'Installationsmodus' => 'Installation mode',
    'Setup-/Installationsmodus aktivieren.' => 'Enable setup and installation mode.',
    'Config verschluesseln' => 'Encrypt configuration',
    'Modul-Config-Dateien verschluesselt speichern.' => 'Store module configuration files encrypted.',
    'Schluessel (secure)' => 'Key (secure)',
    'Schluessel fuer verschluesselte Config-Dateien.' => 'Key for encrypted configuration files.',
    'Standard-DB-Server' => 'Default database server',
    'Standard-SQL-Server aus config.php. Modul-SQLite (*.db3) wird von dbxDB automatisch aus dbx/modules/*/db/ aufgeloest.' => 'Default SQL server from config.php. dbxDB automatically resolves module SQLite files (*.db3) from dbx/modules/*/db/.',
    'Standard-Mail-Profil' => 'Default mail profile',
    'Name des Mail-Eintrags aus dem Tab Mail.' => 'Name of the mail entry from the Mail tab.',
    'Modul-Bilder (Dashboard)' => 'Module images (dashboard)',
    'Kommagetrennte Dateinamen unter files/mod/ fuer das Admin-Dashboard.' => 'Comma-separated file names below files/mod/ for the admin dashboard.',
    '0=Nein&1=Ja' => '0=No&1=Yes',
    'de=Deutsch&en=English&es=Espanol' => 'de=German&en=English&es=Spanish',
    'hell=Hell&gelb=Gelb&rot=Rot&gruen=Gruen&blau=Blau&dunkel=Dunkel' => 'hell=Light&gelb=Yellow&rot=Red&gruen=Green&blau=Blue&dunkel=Dark',
    'error=Nur Error&warning=Error und Warning&all=Alles' => 'error=Errors only&warning=Errors and warnings&all=All',
    'off=Aus&main=Nur Hauptkennzahlen&detail=Hauptkennzahlen und Details' => 'off=Off&main=Main metrics only&detail=Main metrics and details',
);

foreach ($fields as &$fd_field) {
    foreach (array('label', 'tooltip', 'errormsg', 'options') as $fd_ui_key) {
        if (
            isset($fd_field[$fd_ui_key]) &&
            is_string($fd_field[$fd_ui_key]) &&
            isset($fd_text[$fd_field[$fd_ui_key]])
        ) {
            $fd_field[$fd_ui_key] = $fd_text[$fd_field[$fd_ui_key]];
        }
    }
}
unset($fd_field, $fd_text, $fd_ui_key);
