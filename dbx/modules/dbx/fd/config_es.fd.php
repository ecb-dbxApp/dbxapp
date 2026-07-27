<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';
$messages['module_subtitle'] = 'Módulo: dbx';
$messages['config_info'] = 'Editar la configuración del sistema dbx.';
$messages['config_save'] = 'Guardar configuración';
$messages['config_saved'] = 'La configuración se ha guardado correctamente.';
$messages['config_save_error'] = 'No se ha podido guardar la configuración.';
$messages['no_entries'] = 'No hay entradas.';
$messages['no_sql_servers'] = 'No hay servidores SQL configurados. dbxDB resuelve automáticamente los archivos SQLite de módulos (*.db3) bajo dbx/modules/*/db/.';
$messages['edit_section'] = 'Editar {section}.';
$messages['save_section'] = 'Guardar {section}';
$messages['create_section'] = 'Crear {section}';
$messages['delete_section'] = 'Eliminar {section}';
$messages['check_new_name'] = 'Revise el nombre nuevo.';
$messages['enter_new_name'] = 'Introduzca el nombre nuevo.';
$messages['entry_exists'] = 'La entrada «{entry}» ya existe.';
$messages['entry_create_error'] = 'No se ha podido crear la entrada.';
$messages['entry_created'] = 'Se ha creado la entrada «{entry}».';
$messages['entry_delete_error'] = 'No se ha podido eliminar la entrada «{entry}».';
$messages['entry_deleted'] = 'Se ha eliminado la entrada «{entry}».';
$messages['check_input'] = 'Revise los datos introducidos.';
$messages['module_sqlite_forbidden'] = 'Los archivos SQLite de módulos (*.db3) no deben guardarse en la configuración del sistema.';
$messages['entry_save_error'] = 'No se ha podido guardar la entrada.';
$messages['entry_saved'] = 'Se ha guardado la entrada.';
$messages['new_entry_info'] = 'Crear una entrada nueva de {section}.';
$messages['label_new_entry'] = 'Entrada nueva';
$messages['tooltip_new_entry'] = 'Nombre de la entrada nueva';
$messages['edit_entry_info'] = 'Editar {section} «{entry}».';
$messages['confirm_delete_entry'] = '¿Eliminar realmente {section} «{entry}»?';

// Alias: Formular-FD zeigt auf cfg/config.dd.php (eine Quelle).
require dirname(__DIR__) . '/cfg/config.dd.php';

// La estructura técnica de campos permanece centralizada en config.dd.php;
// esta variante FD sustituye únicamente los textos de la interfaz.
$fdText = array(
    'Versionsnummer' => 'Número de versión',
    'Interne Config-Versionsnummer des dbx-Moduls.' => 'Número interno de versión de configuración del módulo dbx.',
    'Version muss eine Zahl sein.' => 'La versión debe ser un número.',
    'Modul aktiv' => 'Módulo activo',
    'Schaltet das Kernmodul dbx ein oder aus.' => 'Activa o desactiva el módulo principal dbx.',
    'Zugriff' => 'Acceso',
    'Benutzergruppen mit Zugriff auf das Systemmodul.' => 'Grupos de usuarios con acceso al módulo del sistema.',
    'Standard-Sprache' => 'Idioma predeterminado',
    'Fallback-Sprache des Systems.' => 'Idioma alternativo del sistema.',
    'Verfuegbare Sprachen' => 'Idiomas disponibles',
    'Sprachen, die Benutzer waehlen duerfen.' => 'Idiomas que los usuarios pueden seleccionar.',
    'Standard-Skin' => 'Tema predeterminado',
    'Farbschema (Skin) fuer neue Besucher.' => 'Esquema de colores para nuevos visitantes.',
    'Design (Benutzer)' => 'Diseño (usuarios)',
    'Layout-Paket fuer oeffentliche Seiten.' => 'Paquete de diseño para páginas públicas.',
    'Design (Admin)' => 'Diseño (administración)',
    'Layout-Paket fuer den Admin-Bereich.' => 'Paquete de diseño para el área de administración.',
    'Session-DB speichern' => 'Guardar base de datos de sesiones',
    'Normale HTTP-Requests und HTML-AJAX-Requests am Request-Ende in der Session-Datenbank speichern.' => 'Guarda las solicitudes HTTP normales y las solicitudes AJAX HTML en la base de datos de sesiones al finalizar la solicitud.',
    'Permalinks' => 'Enlaces permanentes',
    'Schoene URLs / Permalink-Aufloesung aktivieren.' => 'Activa URL legibles y la resolución de enlaces permanentes.',
    'System-Cache' => 'Caché del sistema',
    'Templates und Modul-Configs in der Session cachen.' => 'Almacena plantillas y configuraciones de módulos en la sesión.',
    'Content-Cache' => 'Caché de contenido',
    'Gerenderte Content-Seiten und Permalink-Index als HTML cachen.' => 'Almacena como HTML las páginas de contenido renderizadas y el índice de enlaces permanentes.',
    'SysMsg-Level' => 'Nivel de SysMsg',
    'Steuert, welche Systemmeldungen in dbxSysMsg gespeichert werden.' => 'Controla qué mensajes del sistema se guardan en dbxSysMsg.',
    'Performance-Level' => 'Nivel de rendimiento',
    'Steuert, ob keine Performance-Daten, nur Hauptkennzahlen oder alle Detail-Timer gespeichert werden.' => 'Controla si no se guardan datos de rendimiento, solo métricas principales o todos los temporizadores detallados.',
    'Performance: Sample-Rate' => 'Rendimiento: frecuencia de muestreo',
    'Nur jeden N-ten Request messen (1 = jeder Request).' => 'Mide solo cada solicitud N (1 = cada solicitud).',
    'Performance: Aufbewahrung (Tage)' => 'Rendimiento: conservación (días)',
    'Alte Performance-Daten nach X Tagen loeschen.' => 'Elimina los datos de rendimiento antiguos después de X días.',
    'Performance: Langsam ab (ms)' => 'Rendimiento: lento desde (ms)',
    'Schwellwert fuer langsame Requests im Dashboard.' => 'Umbral de solicitudes lentas en el panel.',
    'Intro-Seite' => 'Página de introducción',
    'Intro beim ersten Besuch anzeigen.' => 'Muestra la introducción en la primera visita.',
    'Wartungsmodus' => 'Modo de mantenimiento',
    'Anwendung als in Ueberarbeitung markieren.' => 'Marca la aplicación como en mantenimiento.',
    'Installationsmodus' => 'Modo de instalación',
    'Setup-/Installationsmodus aktivieren.' => 'Activa el modo de configuración e instalación.',
    'Config verschluesseln' => 'Cifrar configuración',
    'Modul-Config-Dateien verschluesselt speichern.' => 'Guarda cifrados los archivos de configuración de los módulos.',
    'Schluessel (secure)' => 'Clave (segura)',
    'Schluessel fuer verschluesselte Config-Dateien.' => 'Clave para archivos de configuración cifrados.',
    'Standard-DB-Server' => 'Servidor de base de datos predeterminado',
    'Standard-SQL-Server aus config.php. Modul-SQLite (*.db3) wird von dbxDB automatisch aus dbx/modules/*/db/ aufgeloest.' => 'Servidor SQL predeterminado de config.php. dbxDB resuelve automáticamente los archivos SQLite de los módulos (*.db3) desde dbx/modules/*/db/.',
    'Standard-Mail-Profil' => 'Perfil de correo predeterminado',
    'Name des Mail-Eintrags aus dem Tab Mail.' => 'Nombre de la entrada de correo de la pestaña Correo.',
    'Modul-Bilder (Dashboard)' => 'Imágenes de módulos (panel)',
    'Kommagetrennte Dateinamen unter files/mod/ fuer das Admin-Dashboard.' => 'Nombres de archivo separados por comas bajo files/mod/ para el panel de administración.',
    '0=Nein&1=Ja' => '0=No&1=Sí',
    'de=Deutsch&en=English&es=Espanol' => 'de=Alemán&en=Inglés&es=Español',
    'hell=Hell&gelb=Gelb&rot=Rot&gruen=Gruen&blau=Blau&dunkel=Dunkel' => 'hell=Claro&gelb=Amarillo&rot=Rojo&gruen=Verde&blau=Azul&dunkel=Oscuro',
    'error=Nur Error&warning=Error und Warning&all=Alles' => 'error=Solo errores&warning=Errores y advertencias&all=Todos',
    'off=Aus&main=Nur Hauptkennzahlen&detail=Hauptkennzahlen und Details' => 'off=Desactivado&main=Solo métricas principales&detail=Métricas principales y detalles',
);

foreach ($fields as &$fdField) {
    foreach (array('label', 'tooltip', 'errormsg', 'options') as $fdUiKey) {
        if (
            isset($fdField[$fdUiKey]) &&
            is_string($fdField[$fdUiKey]) &&
            isset($fdText[$fdField[$fdUiKey]])
        ) {
            $fdField[$fdUiKey] = $fdText[$fdField[$fdUiKey]];
        }
    }
}
unset($fdField, $fdText, $fdUiKey);
