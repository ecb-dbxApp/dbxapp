<?php

declare(strict_types=1);

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Projektwurzel fehlt.\n");
    exit(1);
}
$version = trim((string)file_get_contents($root . '/VERSION'));
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    fwrite(STDERR, "VERSION ist ungueltig.\n");
    exit(1);
}
$version_parts = array_map('intval', explode('.', $version));
$release_line = $version_parts[0] . '.' . $version_parts[1] . '.0';
$next_major = (string)($version_parts[0] + 1) . '.0.0';
$kernel_constraint = '>=' . $release_line . ' <' . $next_major;
$package_constraint = '^' . $release_line;

$write = static function (string $file, array $data): void {
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Verzeichnis konnte nicht angelegt werden: ' . $dir);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || file_put_contents($file, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Manifest konnte nicht geschrieben werden: ' . $file);
    }
};

$base = static function (string $id, string $type, string $name, string $title, bool $managed, string $license = 'free') use ($version, $kernel_constraint): array {
    return array(
        'schema' => 1,
        'id' => $id,
        'type' => $type,
        'name' => $name,
        'title' => $title,
        'description' => '',
        'descriptions' => array('de' => '', 'en' => '', 'es' => ''),
        'icon' => $type === 'kernel' ? 'bi-cpu' : ($type === 'design' ? 'bi-palette' : 'bi-box-seam'),
        'image' => '',
        'package_excludes' => array(),
        'version' => $version,
        'vendor' => array(
            'id' => $managed ? 'dbxapp' : 'local',
            'name' => $managed ? 'dbxApp' : 'Lokaler Hersteller',
        ),
        'license' => $license,
        'managed' => $managed,
        'requires' => array(
            'kernel' => $type === 'kernel' ? '*' : $kernel_constraint,
            'php' => '>=8.2.0',
            'extensions' => array('json'),
            'packages' => array(),
        ),
        'permissions' => array(),
        'migrations' => array(),
        'files' => array(),
    );
};

$kernel = $base('dbxapp/kernel/dbxapp', 'kernel', 'dbxapp', 'dbxApp Kernel', true);
$kernel['description'] = 'Gemeinsame Laufzeit, globale Assets, Add-ons und Paket-Engine.';
$kernel['descriptions'] = array(
    'de' => 'Der sichere Kern von dbxApp: Laufzeit, globale Assets, Add-ons, Abstraktionen und die Paket-Engine.',
    'en' => 'The secure dbxApp core: runtime, global assets, add-ons, abstractions and the package engine.',
    'es' => 'El núcleo seguro de dbxApp: entorno de ejecución, recursos globales, complementos, abstracciones y motor de paquetes.',
);
$kernel['requires']['kernel'] = '*';
$kernel['requires']['extensions'] = array('curl', 'json', 'openssl', 'zip');
$kernel['permissions'] = array('filesystem', 'network', 'package-management');
$write($root . '/dbx.package.json', $kernel);

$dependencies = array(
    'dbxContent_admin' => array('dbxapp/module/dbxContent' => $package_constraint),
    'dbxContact_admin' => array('dbxapp/module/dbxContact' => $package_constraint),
    'dbxShop' => array('dbxapp/module/dbxContent' => $package_constraint),
    'dbxShop_admin' => array(
        'dbxapp/module/dbxShop' => $package_constraint,
        'dbxapp/module/dbxContent_admin' => $package_constraint,
    ),
    'dbxUser_admin' => array('dbxapp/module/dbxUser' => $package_constraint),
    'dbxWorkflow_admin' => array('dbxapp/module/dbxWorkflow' => $package_constraint),
);
$permission_map = array(
    'dbxAdmin' => array('administration', 'database', 'filesystem', 'package-management'),
    'dbxContent' => array('database', 'media'),
    'dbxContent_admin' => array('administration', 'database', 'media'),
    'dbxContact' => array('database', 'mail'),
    'dbxContact_admin' => array('administration', 'database', 'mail'),
    'dbxDownLoad' => array('filesystem'),
    'dbxKi' => array('database', 'filesystem', 'network'),
    'dbxLogin' => array('authentication', 'database', 'mail'),
    'dbxMenu' => array('database'),
    'dbxSelfTest' => array('administration', 'filesystem', 'network'),
    'dbxSetup' => array('administration', 'database', 'filesystem'),
    'dbxShop' => array('database', 'media', 'network', 'payment'),
    'dbxShop_admin' => array('administration', 'database', 'media', 'network'),
    'dbxUser' => array('authentication', 'database'),
    'dbxUser_admin' => array('administration', 'authentication', 'database'),
    'dbxWorkflow' => array('database'),
    'dbxWorkflow_admin' => array('administration', 'database'),
    'myLKW' => array('database', 'filesystem'),
);

$module_catalog = array(
    'dbx' => array('bi-database-gear', 'Datenmodell, Basistemplates und gemeinsame Modulressourcen.', 'Data model, base templates and shared module resources.', 'Modelo de datos, plantillas base y recursos compartidos de módulos.'),
    'dbxAdmin' => array('bi-speedometer2', 'Administration für Systemstatus, Datenbanken, Module, Konfiguration, Benutzer und Updates.', 'Administration for system health, databases, modules, configuration, users and updates.', 'Administración del estado del sistema, bases de datos, módulos, configuración, usuarios y actualizaciones.'),
    'dbxConstruct' => array('bi-hammer', 'Werkzeuge zum strukturierten Erstellen und Erweitern dbxApp-konformer Module.', 'Tools for creating and extending well-structured dbxApp modules.', 'Herramientas para crear y ampliar módulos dbxApp bien estructurados.'),
    'dbxContact' => array('bi-chat-left-text', 'Kontaktformulare und nachvollziehbare Anfragen für Besucher und angemeldete Benutzer.', 'Contact forms and traceable requests for visitors and signed-in users.', 'Formularios de contacto y solicitudes trazables para visitantes y usuarios.'),
    'dbxContact_admin' => array('bi-inbox', 'Zentrale Bearbeitung, Beantwortung und Statusverwaltung eingehender Kontaktanfragen.', 'Central processing, replies and status management for incoming contact requests.', 'Gestión central, respuestas y estados de las solicitudes de contacto.'),
    'dbxContent' => array('bi-file-earmark-richtext', 'CMS-Laufzeit für Seiten, Inhalte, Medien, Mehrsprachigkeit und öffentliche Ausgabe.', 'CMS runtime for pages, content, media, multilingual content and public rendering.', 'Motor CMS para páginas, contenidos, medios, multilingüismo y publicación.'),
    'dbxContent_admin' => array('bi-pencil-square', 'Visuelle CMS-Administration für Seitenbaum, Inhalte, Medien und Veröffentlichung.', 'Visual CMS administration for page trees, content, media and publishing.', 'Administración visual del CMS para páginas, contenidos, medios y publicación.'),
    'dbxDesign_admin' => array('bi-palette2', 'Designs und Farbvarianten erstellen, bearbeiten, prüfen und als Standard festlegen.', 'Create, edit, review and select designs and colour variants as defaults.', 'Crear, editar, revisar y establecer diseños y variantes de color.'),
    'dbxDownLoad' => array('bi-cloud-arrow-down', 'Kontrollierte und abgesicherte Bereitstellung von Dateien zum Download.', 'Controlled and secured delivery of downloadable files.', 'Entrega controlada y segura de archivos descargables.'),
    'dbxEditor' => array('bi-code-square', 'Integrierter Editor für Templates, Quelltexte und strukturierte Systemdateien.', 'Integrated editor for templates, source code and structured system files.', 'Editor integrado para plantillas, código fuente y archivos estructurados.'),
    'dbxHelp' => array('bi-question-circle', 'Kontextsensitive, modulautonome Hilfe direkt an Formularen und Arbeitsbereichen.', 'Context-sensitive, module-owned help directly in forms and work areas.', 'Ayuda contextual y autónoma de cada módulo en formularios y áreas de trabajo.'),
    'dbxHome' => array('bi-house-door', 'Startbereich und zentrale Einstiegspunkte für Benutzer und Anwendungen.', 'Home area and central entry points for users and applications.', 'Área de inicio y puntos de entrada centrales para usuarios y aplicaciones.'),
    'dbxKi' => array('bi-stars', 'KI-gestützte Analyse, Inhaltserstellung und regelkonforme Assistenz in dbxApp.', 'AI-assisted analysis, content creation and policy-compliant support in dbxApp.', 'Análisis, creación de contenido y asistencia conforme mediante IA.'),
    'dbxLogin' => array('bi-shield-lock', 'Sichere Anmeldung, Registrierung, Bestätigung und Passwort-Wiederherstellung.', 'Secure sign-in, registration, confirmation and password recovery.', 'Inicio de sesión, registro, confirmación y recuperación de contraseña seguros.'),
    'dbxMenu' => array('bi-menu-button-wide', 'Mehrsprachige Haupt-, Unter- und Kontextnavigation für dbxApp-Oberflächen.', 'Multilingual main, sub and contextual navigation for dbxApp interfaces.', 'Navegación principal, secundaria y contextual multilingüe.'),
    'dbxPage_admin' => array('bi-window-stack', 'Administrative Seiten- und API-Einstellungen für dbxApp-Anwendungen.', 'Administrative page and API settings for dbxApp applications.', 'Configuración administrativa de páginas y API para aplicaciones dbxApp.'),
    'dbxSelfTest' => array('bi-clipboard2-check', 'Automatische System-, Sicherheits-, Qualitäts- und Browserprüfungen mit Protokoll.', 'Automated system, security, quality and browser checks with reporting.', 'Pruebas automáticas de sistema, seguridad, calidad y navegador con informes.'),
    'dbxSetup' => array('bi-wrench-adjustable-circle', 'Geführte Erstinstallation und sichere Einrichtung einer neuen dbxApp-Instanz.', 'Guided first installation and secure setup of a new dbxApp instance.', 'Instalación guiada y configuración segura de una nueva instancia dbxApp.'),
    'dbxShop' => array('bi-cart3', 'Shop-Laufzeit für Katalog, Warenkorb, Bestellungen und Zahlungsabläufe.', 'Shop runtime for catalogue, cart, orders and payment flows.', 'Motor de tienda para catálogo, carrito, pedidos y pagos.'),
    'dbxShop_admin' => array('bi-shop-window', 'Administration von Artikeln, Bestellungen, Verkaufskanälen und Shop-Inhalten.', 'Administration of products, orders, sales channels and shop content.', 'Administración de productos, pedidos, canales de venta y contenidos.'),
    'dbxUser' => array('bi-person-circle', 'Benutzerprofil, persönliche Daten, Avatar und sichere Passwortpflege.', 'User profile, personal data, avatar and secure password management.', 'Perfil, datos personales, avatar y gestión segura de contraseñas.'),
    'dbxUser_admin' => array('bi-people', 'Verwaltung von Benutzerkonten, Gruppen, Rollen und Zugriffsrechten.', 'Management of user accounts, groups, roles and access rights.', 'Gestión de cuentas, grupos, roles y permisos de acceso.'),
    'dbxWorkflow' => array('bi-diagram-3', 'Ausführung nachvollziehbarer, modularer Geschäftsprozesse und Arbeitsschritte.', 'Execution of traceable, modular business processes and work steps.', 'Ejecución de procesos de negocio y pasos de trabajo modulares y trazables.'),
    'dbxWorkflow_admin' => array('bi-bezier2', 'Workflows visuell definieren, an Module binden und laufende Instanzen überwachen.', 'Visually define workflows, bind modules and monitor running instances.', 'Definir flujos visualmente, vincular módulos y supervisar instancias.'),
    'myLKW' => array('bi-truck', 'Fuhrpark- und Dispositionslösung für Fahrzeuge, Fahrer und mehrtägige Tourplanung – mit täglichem CSV-Import, direkter Tabellenpflege sowie Druck und Export.', 'Fleet and dispatch solution for vehicles, drivers and multi-day route planning, including daily CSV import, direct table editing, print and export.', 'Solución de flota y planificación para vehículos, conductores y rutas de varios días, con importación CSV diaria, edición directa, impresión y exportación.'),
);

$catalog_packages = array($kernel);
foreach (glob($root . '/dbx/modules/*', GLOB_ONLYDIR) ?: array() as $directory) {
    $name = basename($directory);
    $managed = !str_starts_with(strtolower($name), 'my') || $name === 'myLKW';
    $vendor = $managed ? 'dbxapp' : 'local';
    $license = $name === 'myLKW' ? 'paid' : ($managed ? 'free' : 'private');
    $manifest = $base($vendor . '/module/' . $name, 'module', $name, $name, $managed, $license);
    $catalog_info = $module_catalog[$name] ?? array('bi-box-seam', 'Modulares Funktionspaket für dbxApp.', 'Modular feature package for dbxApp.', 'Paquete funcional modular para dbxApp.');
    $manifest['icon'] = $catalog_info[0];
    $manifest['descriptions'] = array('de' => $catalog_info[1], 'en' => $catalog_info[2], 'es' => $catalog_info[3]);
    $manifest['description'] = $catalog_info[1];
    $image = 'dbx/modules/' . $name . '/tpl/img/' . $name . '.png';
    if (is_file($root . '/' . $image)) {
        $manifest['image'] = $image;
    }
    if ($name === 'myLKW') {
        $manifest['title'] = 'myLKW Fuhrpark & Disposition';
        $manifest['purchase_url'] = 'https://market.dbxapp.de/packages/myLKW';
        $manifest['package_excludes'] = array(
            'dbx/modules/myLKW/tpl/img/CCF_000031.pdf',
            'dbx/modules/myLKW/tpl/img/CCF_000031.png',
            'dbx/modules/myLKW/tpl/img/CCF_000032.pdf',
            'dbx/modules/myLKW/tpl/img/CCF_000033.pdf',
            'dbx/modules/myLKW/tpl/img/CCF_000034.pdf',
            'dbx/modules/myLKW/tpl/img/CCF_000035.pdf',
            'dbx/modules/myLKW/tpl/img/Muster10.png',
            'dbx/modules/myLKW/tpl/img/schein_a.png',
        );
    }
    $manifest['requires']['packages'] = $dependencies[$name] ?? array();
    $manifest['permissions'] = $permission_map[$name] ?? array();
    $write($directory . '/dbx.package.json', $manifest);
    if ($managed) {
        $catalog_packages[] = $manifest;
    }
}

foreach (glob($root . '/dbx/design/*', GLOB_ONLYDIR) ?: array() as $directory) {
    $name = basename($directory);
    $managed = in_array(strtolower($name), array('dbxapp', 'flowers'), true);
    $vendor = $managed ? 'dbxapp' : 'local';
    $manifest = $base($vendor . '/design/' . $name, 'design', $name, ucfirst($name), $managed, $managed ? 'free' : 'private');
    $manifest['description'] = $managed ? 'Offizielles dbxApp-Design.' : 'Lokales, nicht durch den Marktplatz verwaltetes Design.';
    $manifest['permissions'] = array('presentation');
    $write($directory . '/dbx.package.json', $manifest);
    if ($managed) {
        $catalog_packages[] = $manifest;
    }
}

usort($catalog_packages, static fn(array $a, array $b): int => strnatcasecmp($a['id'], $b['id']));
$catalog = array(
    'schema' => 1,
    'channel' => 'stable',
    'sequence' => 1,
    'generated_at' => gmdate('c'),
    'expires_at' => '2030-01-01T00:00:00+00:00',
    'packages' => $catalog_packages,
);
$write($root . '/dbx/marketplace/catalog.json', $catalog);
$trust_file = $root . '/dbx/marketplace/trust.json';
if (!is_file($trust_file)) {
    $write($trust_file, array(
        'schema' => 1,
        'algorithm' => 'rsa-sha256',
        'keys' => array(),
        'note' => 'Oeffentliche Produktionsschluessel werden vor Aktivierung des Remote-Katalogs eingetragen.',
    ));
}

echo 'Paketmanifeste erzeugt: ' . count($catalog_packages) . " verwaltete Pakete.\n";
