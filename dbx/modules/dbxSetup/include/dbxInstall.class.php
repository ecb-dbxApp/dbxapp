<?php

declare(strict_types=1);

namespace dbx\dbxSetup;

require_once dirname(__DIR__, 3) . '/include/dbxPasswordPolicy.class.php';

use RuntimeException;
use Throwable;

/**
 * Geführte, wiederanlaufbare Erstinstallation von dbxapp.
 *
 * Der Assistent speichert Installationszustand nur in der PHP-Session und
 * installationsspezifische Werte ausschließlich in config.local.php.
 * Strukturen entstehen aus den DDs; ausgelieferte SQL-Dumps werden nicht
 * vorausgesetzt.
 */
class dbxInstall
{
    private const TOTAL_STEPS = 7;
    private const SESSION_SECTION = 'installer';
    private const SESSION_MODULE = 'dbxSetup';
    private const SQL_SERVER = 'dbxApp';

    /** Geordnete Metadaten der sieben Installationsschritte. */
    private array $steps = array(
        1 => array('title' => 'Systemprüfung', 'short' => 'System', 'icon' => 'bi-cpu'),
        2 => array('title' => 'Website & Design', 'short' => 'Basis', 'icon' => 'bi-palette'),
        3 => array('title' => 'Datenspeicher', 'short' => 'Datenbank', 'icon' => 'bi-database'),
        4 => array('title' => 'Strukturen & Daten', 'short' => 'Synchronisierung', 'icon' => 'bi-arrow-repeat'),
        5 => array('title' => 'Administration', 'short' => 'Admin', 'icon' => 'bi-shield-lock'),
        6 => array('title' => 'E-Mail', 'short' => 'E-Mail', 'icon' => 'bi-envelope-gear'),
        7 => array('title' => 'Prüfen & starten', 'short' => 'Abschluss', 'icon' => 'bi-rocket-takeoff'),
    );

    /** Blockierende Validierungsfehler des aktuellen Schritts. */
    private array $errors = array();

    /** Nicht blockierende Statushinweise des aktuellen Schritts. */
    private array $notices = array();

    private bool $finished = false;

    private bool $stay_on_step = false;

    /** Ergebnisdaten des erfolgreich abgeschlossenen Assistenten. */
    private array $finish_result = array();

    public function __construct()
    {
        dbx()->set_system_var('dbx_title', 'dbxapp installieren');
        dbx()->set_system_var('dbx_meta_robots', 'noindex,nofollow,noarchive');
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }
    }

    /**
     * Rendert und verarbeitet den aktuellen Installationsschritt.
     */
    public function run(): string
    {
        $config = dbx()->get_cfg('dbx');
        $config = is_array($config) ? $config : array();
        if ((int)($config['install'] ?? 1) !== 1
            && (int)dbx()->get_system_var('dbx_install', 0, 'int') !== 1
        ) {
            return $this->render_already_installed();
        }
        $help = is_scalar($_GET['install_help'] ?? null)
            ? strtolower(trim((string)$_GET['install_help']))
            : '';
        if (in_array($help, array('design', 'db3', 'pdo', 'email'), true)) {
            return $this->render_installer_help($help);
        }

        $completed = max(0, min(
            self::TOTAL_STEPS,
            (int)$this->state('completed', 0)
        ));
        $step = $this->requested_step();
        $step = min($step, min(self::TOTAL_STEPS, $completed + 1));

        if ($this->is_post()) {
            if (!$this->valid_token($step)) {
                $this->errors[] = 'Die Installationssitzung ist abgelaufen. Bitte laden Sie die Seite neu.';
            } elseif ($this->process_step($step)) {
                if (!$this->stay_on_step) {
                    $this->set_state('completed', max($completed, $step));
                    if (!$this->finished) {
                        $step = min(self::TOTAL_STEPS, $step + 1);
                    }
                }
            }
        }

        if ($this->finished) {
            return $this->render_finished();
        }

        return $this->render_shell($step, $this->render_step($step));
    }

    /**
     * Liefert die Systemprüfungen auch für CLI-Tests und Statusanzeigen.
     *
     * @return array<int,array{id:string,label:string,value:string,status:string,required:bool,hint:string}>
     */
    public function system_checks(): array
    {
        $checks = array();
        $add = static function (
            string $id,
            string $label,
            string $value,
            bool $ok,
            bool $required,
            string $hint = ''
        ) use (&$checks): void {
            $checks[] = array(
                'id' => $id,
                'label' => $label,
                'value' => $value,
                'status' => $ok ? 'ok' : ($required ? 'error' : 'warning'),
                'required' => $required,
                'hint' => $hint,
            );
        };

        $add(
            'php',
            'PHP-Version',
            PHP_VERSION,
            version_compare(PHP_VERSION, '8.2.0', '>='),
            true,
            'dbxapp benötigt PHP 8.2 oder neuer.'
        );
        foreach (array('session', 'PDO', 'json', 'filter', 'hash', 'openssl') as $extension) {
            $add(
                'ext-' . strtolower($extension),
                'PHP-Erweiterung ' . $extension,
                extension_loaded($extension) ? 'verfügbar' : 'fehlt',
                extension_loaded($extension),
                true,
                'Die Erweiterung muss in php.ini aktiviert sein.'
            );
        }

        $config_dir = dbx()->os_path(
            dbx()->get_base_dir() . 'dbx/modules/dbx/cfg'
        );
        $local_config_file = dbx()->os_path(
            rtrim($config_dir, '/\\') . DIRECTORY_SEPARATOR . 'config.local.php'
        );
        $local_config_writable = $this->directory_writable($config_dir)
            && (!is_file($local_config_file) || is_writable($local_config_file));
        $add(
            'write-config',
            'Lokale Konfiguration',
            $this->compact_path($local_config_file),
            $local_config_writable,
            true,
            'Der Webserver benötigt Schreibrecht für config.local.php.'
        );
        foreach (array(
            'files' => dbx()->get_file_dir(),
            'tmp' => dbx()->get_base_dir() . 'tmp/',
        ) as $name => $directory) {
            $directory = dbx()->os_path($directory);
            $add(
                'write-' . $name,
                'Schreibverzeichnis ' . $name . '/',
                $this->compact_path($directory),
                $this->directory_writable($directory),
                true,
                'Das Verzeichnis wird für Laufzeitdaten benötigt.'
            );
        }

        $autoload = dbx()->os_path(dbx()->get_base_dir() . 'dbx/vendor/autoload.php');
        $add(
            'vendor',
            'Composer-Abhängigkeiten',
            is_file($autoload) ? 'vollständig' : 'autoload.php fehlt',
            is_file($autoload) && class_exists('\PHPMailer\PHPMailer\PHPMailer'),
            true,
            'Vor der Installation muss composer install ausgeführt worden sein.'
        );

        foreach (array(
            'pdo_sqlite' => 'SQLite/DB3',
            'pdo_mysql' => 'MySQL/MariaDB',
            'pdo_pgsql' => 'PostgreSQL',
            'pdo_sqlsrv' => 'Microsoft SQL Server',
            'curl' => 'Updates und externe APIs',
            'gd' => 'Bildverarbeitung',
            'fileinfo' => 'Dateityperkennung',
            'zip' => 'Updates und Archive',
            'mbstring' => 'erweiterte Unicode-Verarbeitung',
            'intl' => 'internationale Formate',
        ) as $extension => $purpose) {
            $add(
                'optional-' . $extension,
                'Optional: ' . $extension,
                extension_loaded($extension) ? 'verfügbar' : 'nicht aktiv',
                extension_loaded($extension),
                false,
                'Empfohlen für ' . $purpose . '.'
            );
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
            || in_array(
                strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')),
                array('https', 'wss'),
                true
            );
        $local_host = in_array(
            strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')),
            array('localhost', '127.0.0.1', '[::1]'),
            true
        );
        $add(
            'https',
            'HTTPS',
            $https ? 'aktiv' : ($local_host ? 'lokale Entwicklung' : 'nicht erkannt'),
            $https || $local_host,
            false,
            'Produktive Installationen sollten ausschließlich HTTPS verwenden.'
        );

        $memory = $this->ini_bytes((string)ini_get('memory_limit'));
        $add(
            'memory',
            'PHP memory_limit',
            (string)ini_get('memory_limit'),
            $memory === -1 || $memory >= 128 * 1024 * 1024,
            false,
            'Für Installation und Updates werden mindestens 128 MB empfohlen.'
        );

        return $checks;
    }

    private function process_step(int $step): bool
    {
        $action = $this->post_key('install_action', 40);
        $this->stay_on_step = ($step === 3 && $action === 'check_database')
            || ($step === 6 && $action === 'check_mail');

        $processed = match ($step) {
            1 => $this->process_system(),
            2 => $this->process_basics(),
            3 => $this->process_database(),
            4 => $this->process_schema(),
            5 => $this->process_admin(),
            6 => $this->process_mail(),
            7 => $this->process_finish(),
            default => false,
        };

        if ($processed && $this->stay_on_step) {
            $this->notices[] = $step === 3
                ? 'Die Datenbankwerte wurden erneut geprüft. Sie bleiben in Schritt 3 und können die Installation anschließend fortsetzen.'
                : 'Die E-Mail-Werte wurden erneut geprüft. Sie bleiben in Schritt 6 und können die Installation anschließend fortsetzen.';
        }

        return $processed;
    }

    private function process_system(): bool
    {
        $failed = array_values(array_filter(
            $this->system_checks(),
            static fn(array $check): bool =>
                $check['required'] && $check['status'] !== 'ok'
        ));
        if ($failed !== array()) {
            $this->errors[] = 'Mindestens eine notwendige Systemvoraussetzung ist noch nicht erfüllt.';
            return false;
        }

        $this->notices[] = 'Alle notwendigen Systemvoraussetzungen sind erfüllt.';
        return true;
    }

    private function process_basics(): bool
    {
        $site_title = $this->post_text('site_title', 120);
        $brand_name = $this->post_text('brand_name', 120);
        $tagline = $this->post_text('brand_tagline', 180);
        $user_design = $this->post_key('default_design_user', 63);
        $admin_design = $this->post_key('default_design_admin', 63);
        $language = strtolower($this->post_key('default_lng', 3));
        $timezone = $this->post_text('timezone', 80);
        $designs = $this->design_options();

        if ($site_title === '') {
            $this->errors[] = 'Bitte geben Sie einen Seitentitel ein.';
        }
        if ($brand_name === '') {
            $this->errors[] = 'Bitte geben Sie einen Namen für das Branding ein.';
        }
        if (!isset($designs[$user_design])) {
            $this->errors[] = 'Das gewählte Standarddesign ist nicht verfügbar.';
        }
        if (!isset($designs[$admin_design])) {
            $this->errors[] = 'Das gewählte Admin-Design ist nicht verfügbar.';
        }
        if (!in_array($language, array('de', 'en', 'es'), true)) {
            $this->errors[] = 'Die Standardsprache ist ungültig.';
        }
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $this->errors[] = 'Die Zeitzone ist ungültig.';
        }
        if ($this->errors !== array()) {
            return false;
        }

        $this->set_state('basics', array(
            'site_title' => $site_title,
            'brand_name' => $brand_name,
            'brand_tagline' => $tagline,
            'default_design_user' => $user_design,
            'default_design_admin' => $admin_design,
            'default_lng' => $language,
            'timezone' => $timezone,
        ));
        $this->notices[] = 'Grunddaten und Designauswahl wurden übernommen.';
        return true;
    }

    private function process_database(): bool
    {
        $mode = $this->post_key('storage_mode', 20);
        if (!in_array($mode, array('sqlite', 'mysql', 'configured'), true)) {
            $this->errors[] = 'Bitte wählen Sie einen gültigen Datenspeicher.';
            return false;
        }
        if ($mode !== 'sqlite' && !$this->post_bool('storage_advanced_confirm')) {
            $this->errors[] = 'Bitte bestätigen Sie die erweiterte Datenbankauswahl. Die mitgelieferten DB3-Datenbanken sind der einfache Standard und können auch später noch auf einen PDO-Server umgestellt werden.';
            return false;
        }

        if ($mode === 'sqlite') {
            if (!extension_loaded('pdo_sqlite')) {
                $this->errors[] = 'Für SQLite/DB3 muss die Erweiterung pdo_sqlite aktiviert sein.';
                return false;
            }
            $bindings = dbx()->get_cfg('dbx', 'dd_server_bindings', array());
            if (is_array($bindings) && $bindings !== array()) {
                if (!dbx()->set_local_config_section('dbx', 'dd_server_bindings', array())) {
                    $this->errors[] = 'Die lokalen DD-Serverbindungen konnten nicht zurückgesetzt werden.';
                    return false;
                }
            }
            $this->set_state('database', array(
                'mode' => 'sqlite',
                'server' => '',
                'migrate_data' => 0,
                'checked' => 1,
                'checked_at' => time(),
            ));
            $this->notices[] = 'Tabellenstruktur und Fachdaten der ausgelieferten DB3-Dateien bleiben unverändert. Den persönlichen Administratorzugang legen Sie in Schritt 5 fest.';
            return true;
        }

        if ($mode === 'configured') {
            $this->set_state('database', array(
                'mode' => 'configured',
                'server' => '',
                'migrate_data' => 0,
                'checked' => 1,
                'checked_at' => time(),
            ));
            $this->notices[] = 'Die bereits eingerichteten Datenbankziele der Module werden verwendet.';
            return true;
        }

        $db_type = strtolower($this->post_key('db_type', 20));
        $pdo_types = array(
            'mysql' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            'sqlsrv' => 'pdo_sqlsrv',
        );
        if (!isset($pdo_types[$db_type])) {
            $this->errors[] = 'Bitte wählen Sie einen unterstützten PDO-Datenbanktyp.';
            return false;
        }
        if (!extension_loaded($pdo_types[$db_type])) {
            $this->errors[] = 'Für den gewählten PDO-Server muss die Erweiterung '
                . $pdo_types[$db_type] . ' aktiviert sein.';
            return false;
        }

        $host = $this->post_text('db_host', 255);
        $database = $this->post_database_name('db_name');
        $user = $this->post_text('db_user', 255);
        $password = $this->post_secret('db_password', 1024);
        if ($password === '') {
            $current = dbx()->get_cfg('dbx', 'db', array());
            $password = is_array($current)
                ? (string)($current[self::SQL_SERVER]['pass'] ?? '')
                : '';
        }
        $port = (int)$this->post_int('db_port', 3306);
        $create = $this->post_bool('db_create');
        $migrate = $this->post_bool('migrate_data');
        if ($host === '' || $database === '' || $user === '') {
            $this->errors[] = 'PDO-Server, Datenbank und Benutzer sind erforderlich.';
        }
        if ($port < 1 || $port > 65535) {
            $this->errors[] = 'Der Datenbankport muss zwischen 1 und 65535 liegen.';
        }
        if ($this->errors !== array()) {
            return false;
        }

        $db_config = array(
            'activ' => '1',
            'type' => $db_type,
            'host' => $host,
            'dbname' => $database,
            'user' => $user,
            'pass' => $password,
            'port' => (string)$port,
        );
        $db = dbx()->get_system_obj('dbxDB');
        if (!is_object($db)) {
            $this->errors[] = 'Der Datenbankdienst steht nicht zur Verfügung.';
            return false;
        }
        if (!$db->can_connect_database_config($db_config, true)) {
            if (!$create
                || !$db->ensure_database_exists(self::SQL_SERVER, $db_config)
                || !$db->can_connect_database_config($db_config, true)
            ) {
                $this->errors[] = 'Die PDO-Verbindung zur Zieldatenbank ist fehlgeschlagen. Die Datenbank muss vorhanden oder erfolgreich angelegt worden sein.';
                return false;
            }
        }

        if (!dbx()->patch_local_config('dbx', array(
            'db' => array(self::SQL_SERVER => $db_config),
        ))) {
            $this->errors[] = 'Die lokale SQL-Konfiguration konnte nicht gespeichert werden.';
            return false;
        }

        try {
            $this->installer()->bind_all_to_server(self::SQL_SERVER);
        } catch (Throwable $exception) {
            $this->errors[] = $exception->getMessage();
            return false;
        }

        $this->set_state('database', array(
            'mode' => 'mysql',
            'server' => self::SQL_SERVER,
            'type' => $db_type,
            'host' => $host,
            'database' => $database,
            'user' => $user,
            'port' => $port,
            'migrate_data' => $migrate,
            'checked' => 1,
            'checked_at' => time(),
        ));
        $this->notices[] = 'PDO-Verbindung, Zieldatenbank und DD-Serverbindungen wurden erfolgreich vorbereitet.';
        return true;
    }

    private function process_schema(): bool
    {
        try {
            $installer = $this->installer();
            $database = $this->state('database', array());
            if (($database['mode'] ?? '') === 'sqlite') {
                $verification = $installer->verify_bundled_schema();
                if (empty($verification['ok'])) {
                    throw new RuntimeException(implode(
                        '; ',
                        (array)($verification['errors'] ?? array())
                    ));
                }
                $this->set_state('schema', array(
                    'total' => (int)($verification['total'] ?? 0),
                    'finished' => (int)($verification['verified'] ?? 0),
                    'transferred' => 0,
                    'skipped' => 0,
                    'groups_created' => 0,
                    'read_only' => 1,
                ));
                $this->notices[] = (int)($verification['verified'] ?? 0)
                    . ' ausgelieferte DB3-Tabellen wurden lesend geprüft; es wurde nichts verändert.';
                return true;
            }

            $schema = $installer->provision_schema();
            if (empty($schema['ok'])) {
                throw new RuntimeException(implode('; ', (array)($schema['errors'] ?? array())));
            }

            $transfer = array(
                'ok' => true,
                'total' => 0,
                'transferred' => 0,
                'skipped' => 0,
                'errors' => array(),
            );
            if (($database['mode'] ?? '') === 'mysql'
                && !empty($database['migrate_data'])
            ) {
                $transfer = $installer->transfer_declared_data_to_server(
                    (string)($database['server'] ?? self::SQL_SERVER)
                );
                if (empty($transfer['ok'])) {
                    throw new RuntimeException(implode('; ', (array)($transfer['errors'] ?? array())));
                }
            }

            $groups = $installer->seed_core_groups();
            $this->set_state('schema', array(
                'total' => (int)($schema['total'] ?? 0),
                'finished' => (int)($schema['finished'] ?? 0),
                'transferred' => (int)($transfer['transferred'] ?? 0),
                'skipped' => (int)($transfer['skipped'] ?? 0),
                'groups_created' => count((array)($groups['created'] ?? array())),
            ));
            $this->notices[] = (int)($schema['finished'] ?? 0)
                . ' DD-Strukturen wurden erfolgreich synchronisiert.';
            return true;
        } catch (Throwable $exception) {
            $this->errors[] = 'Synchronisierung fehlgeschlagen: ' . $exception->getMessage();
            return false;
        }
    }

    private function process_admin(): bool
    {
        $password_min_length = $this->post_int('password_min_length', 6);
        if ($password_min_length < 6 || $password_min_length > 128) {
            $this->errors[] = 'Die Passwort-Mindestlänge muss zwischen 6 und 128 Zeichen liegen. Empfohlen sind mindestens 12 Zeichen.';
            return false;
        }
        $password = $this->post_secret('admin_password', 128);
        $password_repeat = $this->post_secret('admin_password_repeat', 128);
        if (!hash_equals($password, $password_repeat)) {
            $this->errors[] = 'Die beiden Admin-Passwörter stimmen nicht überein.';
        }
        $missing_criteria = $this->password_criteria_missing(
            $password,
            $password_min_length
        );
        if ($missing_criteria !== array()) {
            $this->errors[] = 'Das Admin-Passwort erfüllt noch nicht: '
                . implode(', ', $missing_criteria) . '.';
        }
        if ($this->errors !== array()) {
            return false;
        }

        try {
            $basics = $this->state('basics', array());
            $admin = $this->installer()->ensure_initial_admin(
                true,
                (string)($basics['default_lng'] ?? 'de'),
                $password,
                false
            );
            if (empty($admin['exists'])) {
                $this->errors[] = 'Der persönliche Administratorzugang konnte nicht bereitgestellt werden.';
                return false;
            }
            $created = !empty($admin['created']);
            $reset = !empty($admin['reset']);
            $this->set_state('admin', array(
                'email' => (string)($admin['email'] ?? ''),
                'created' => $created ? 1 : 0,
                'existing' => $created ? 0 : 1,
                'reset' => $reset ? 1 : 0,
                'default_password' => !empty($admin['default_password']) ? 1 : 0,
                'password_reset_required' => !empty($admin['password_reset_required']) ? 1 : 0,
                'password_min_length' => $password_min_length,
                'password_configured' => 1,
            ));
            if ($created) {
                $this->notices[] = 'Der Benutzer admin wurde mit dem persönlichen Installationspasswort angelegt.';
            } else {
                $this->notices[] = 'Das Passwort des vorhandenen Benutzers admin wurde durch das persönliche Installationspasswort ersetzt.';
            }
            return true;
        } catch (Throwable $exception) {
            $this->errors[] = $exception->getMessage();
            return false;
        }
    }

    private function process_mail(): bool
    {
        $mode = $this->post_key('mail_delivery_mode', 20);
        if (!in_array($mode, array('internal', 'disabled', 'external'), true)) {
            $this->errors[] = 'Die globale E-Mail-Betriebsart ist ungültig.';
            return false;
        }

        $transport = $this->post_key('mail_transport', 20);
        $host = $this->post_text('mail_host', 255);
        $port = (int)$this->post_int('mail_port', 587);
        $secure = $this->post_key('mail_secure', 10);
        $auth = $this->post_bool('mail_auth');
        $user = $this->post_text('mail_user', 255);
        $password = $this->post_secret('mail_password', 2048);
        if ($password === '') {
            $current = dbx()->get_cfg('dbx', 'mail', array());
            $password = is_array($current)
                ? (string)($current[self::SQL_SERVER]['pass'] ?? '')
                : '';
        }
        $from_email = strtolower($this->post_text('mail_from_email', 254));
        $from_name = $this->post_text('mail_from_name', 160);
        $sender = strtolower($this->post_text('mail_sender', 254));
        $domains = strtolower($this->post_text('mail_from_domains', 1000));
        $force_from = $this->post_bool('mail_force_from');
        $send_test = $this->post_bool('mail_send_test');
        $test_recipient = strtolower($this->post_text('mail_test_recipient', 254));
        $contact_from = strtolower($this->post_text('contact_mail_from', 254));
        $shop_from = strtolower($this->post_text('shop_mail_from', 254));
        if ($contact_from === '') {
            $contact_from = $this->module_sender_address('kontakt', $from_email);
        }
        if ($shop_from === '') {
            $shop_from = $this->module_sender_address('shop', $from_email);
        }

        if ($mode === 'external') {
            if (!in_array($transport, array('mail', 'smtp', 'sendmail'), true)) {
                $this->errors[] = 'Der Mailtransport ist ungültig.';
            }
            if (!in_array($secure, array('', 'tls', 'ssl'), true)) {
                $this->errors[] = 'Die SMTP-Verschlüsselung ist ungültig.';
            }
            if ($port < 1 || $port > 65535) {
                $this->errors[] = 'Der SMTP-Port muss zwischen 1 und 65535 liegen.';
            }
            if (filter_var($from_email, FILTER_VALIDATE_EMAIL) === false) {
                $this->errors[] = 'Für externen Versand ist eine gültige Absenderadresse erforderlich.';
            }
            if ($from_name === '') {
                $this->errors[] = 'Für externen Versand ist ein Absendername erforderlich.';
            }
            if ($sender !== '' && filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
                $this->errors[] = 'Der technische Envelope-Absender ist ungültig.';
            }
            if ($transport === 'smtp' && $host === '') {
                $this->errors[] = 'Für SMTP ist ein Servername erforderlich.';
            }
            if ($transport === 'smtp' && $auth && ($user === '' || $password === '')) {
                if ($user === '' || $password === '') {
                    $this->errors[] = 'Für SMTP-Authentifizierung werden Benutzer und Passwort benötigt.';
                }
            }
            if ($send_test
                && filter_var($test_recipient, FILTER_VALIDATE_EMAIL) === false
            ) {
                $this->errors[] = 'Für die Test-E-Mail ist eine gültige Empfängeradresse erforderlich.';
            }
        } else {
            // Deaktivierte HTML-Felder werden vom Browser nicht übertragen.
            // Für den späteren Wechsel in den externen Modus bleiben deshalb
            // technisch gültige, aber inaktive Profildefaults erhalten.
            $transport = in_array($transport, array('mail', 'smtp', 'sendmail'), true)
                ? $transport
                : 'smtp';
            $secure = in_array($secure, array('', 'tls', 'ssl'), true)
                ? $secure
                : 'tls';
            $port = ($port >= 1 && $port <= 65535) ? $port : 587;
        }
        if (filter_var($contact_from, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[] = 'Der Absender für Kontaktanfragen ist ungültig.';
        }
        if (filter_var($shop_from, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[] = 'Der Absender für Shop-Nachrichten ist ungültig.';
        }
        if ($this->errors !== array()) {
            return false;
        }

        $profile = array(
            'transport' => $transport,
            'host' => $host,
            'port' => (string)$port,
            'secure' => $secure,
            'auth' => $auth ? '1' : '0',
            'user' => $user,
            'pass' => $password,
            'from_email' => $from_email,
            'from_name' => $from_name,
            'from_domains' => $domains,
            'sender' => $sender,
            'force_from' => $force_from ? '1' : '0',
        );
        if (!dbx()->patch_local_config('dbx', array(
            'mail_delivery_mode' => $mode,
            'default_mail' => self::SQL_SERVER,
            'mail' => array(self::SQL_SERVER => $profile),
        ))) {
            $this->errors[] = 'Die lokale E-Mail-Konfiguration konnte nicht gespeichert werden.';
            return false;
        }
        if (!dbx()->patch_local_config('dbxContact', array(
            'mail_profile' => self::SQL_SERVER,
            'mail_from' => $contact_from,
        ))) {
            $this->errors[] = 'Der Absender für Kontaktanfragen konnte nicht lokal gespeichert werden.';
            return false;
        }
        if (!dbx()->patch_local_config('dbxShop', array(
            'mail_profile' => self::SQL_SERVER,
            'mail_from' => $shop_from,
        ))) {
            $this->errors[] = 'Der Absender für Shop-Nachrichten konnte nicht lokal gespeichert werden.';
            return false;
        }

        if ($mode === 'external' && $send_test) {
            $ok = dbx()->get_system_obj('dbxMail')->send_message(
                array('email' => $from_email, 'name' => $from_name),
                $test_recipient,
                'dbxapp Installation: E-Mail-Test',
                '<p>Der E-Mail-Versand dieser dbxapp-Installation funktioniert.</p>',
                'html',
                array(),
                array(
                    'text' => 'Der E-Mail-Versand dieser dbxapp-Installation funktioniert.',
                    'mail_profile' => self::SQL_SERVER,
                )
            );
            if (!$ok) {
                $mail = dbx()->get_system_obj('dbxMail');
                $reason = is_object($mail) ? trim((string)$mail->get_error()) : '';
                $this->errors[] = 'Die Test-E-Mail konnte nicht gesendet werden'
                    . ($reason !== '' ? ': ' . $reason : '.');
                return false;
            }
            $this->notices[] = 'Die Test-E-Mail wurde erfolgreich versendet.';
        }

        $this->set_state('mail', array(
            'mode' => $mode,
            'transport' => $transport,
            'host' => $host,
            'port' => $port,
            'secure' => $secure,
            'auth' => $auth ? 1 : 0,
            'user' => $user,
            'from_email' => $from_email,
            'from_name' => $from_name,
            'sender' => $sender,
            'from_domains' => $domains,
            'force_from' => $force_from ? 1 : 0,
            'contact_from' => $contact_from,
            'shop_from' => $shop_from,
            'test_recipient' => $test_recipient,
            'tested' => ($mode === 'external' && $send_test) ? 1 : 0,
            'checked' => 1,
            'checked_at' => time(),
        ));
        if ($mode === 'internal') {
            $this->notices[] = 'E-Mail-Ereignisse bleiben intern; es wird nichts nach außen versendet.';
        } elseif ($mode === 'disabled') {
            $this->notices[] = 'E-Mail-Ereignisse werden global abgelehnt.';
        } else {
            $this->notices[] = 'Der externe E-Mail-Versand ist konfiguriert.';
        }
        return true;
    }

    private function process_finish(): bool
    {
        if (!$this->post_bool('confirm_install')) {
            $this->errors[] = 'Bitte bestätigen Sie die Zusammenfassung vor dem Abschluss.';
            return false;
        }
        if ((int)$this->state('completed', 0) < 6) {
            $this->errors[] = 'Die notwendigen Installationsschritte sind noch nicht vollständig.';
            return false;
        }

        $basics = $this->state('basics', array());
        $admin = (array)$this->state('admin', array());
        $config = dbx()->get_cfg('dbx');
        $config = is_array($config) ? $config : array();
        $secret = trim((string)($config['secure'] ?? ''));
        if (strlen($secret) < 32) {
            $secret = bin2hex(random_bytes(32));
        }

        $patch = array(
            'install' => 0,
            'ok' => 1,
            'construct' => 0,
            'secure' => $secret,
            'site_title' => (string)($basics['site_title'] ?? 'dbxapp'),
            'page' => (string)($basics['site_title'] ?? 'dbxapp'),
            'brand_name' => (string)($basics['brand_name'] ?? 'dbxapp'),
            'brand_tagline' => (string)($basics['brand_tagline'] ?? ''),
            'default_design_user' => (string)($basics['default_design_user'] ?? 'dbxapp'),
            'default_design_admin' => (string)($basics['default_design_admin'] ?? 'dbxapp'),
            'default_lng' => (string)($basics['default_lng'] ?? 'de'),
            'timezone' => (string)($basics['timezone'] ?? 'Europe/Berlin'),
            'password_min_length' => max(
                6,
                min(128, (int)($admin['password_min_length'] ?? 6))
            ),
        );
        if (!dbx()->patch_local_config('dbx', $patch)) {
            $this->errors[] = 'Der Installationsabschluss konnte nicht in config.local.php gespeichert werden.';
            return false;
        }

        dbx()->set_system_var('dbx_install', 0);
        $this->finish_result = array(
            'site_title' => $patch['site_title'],
            'admin_email' => (string)($this->state('admin', array())['email'] ?? ''),
            'mail_mode' => (string)($this->state('mail', array())['mode'] ?? 'internal'),
            'database' => (array)$this->state('database', array()),
        );
        $this->finished = true;
        $this->set_state('completed', self::TOTAL_STEPS);
        dbx()->invalidate_action_tokens();
        return true;
    }

    private function render_step(int $step): string
    {
        return match ($step) {
            1 => $this->render_system(),
            2 => $this->render_basics(),
            3 => $this->render_database(),
            4 => $this->render_schema(),
            5 => $this->render_admin(),
            6 => $this->render_mail(),
            7 => $this->render_finish(),
            default => '',
        };
    }

    private function render_system(): string
    {
        $checks = $this->system_checks();
        $required_ok = true;
        $rows = '';
        foreach ($checks as $check) {
            if ($check['required'] && $check['status'] !== 'ok') {
                $required_ok = false;
            }
            $icon = match ($check['status']) {
                'ok' => 'bi-check-circle-fill',
                'error' => 'bi-x-circle-fill',
                default => 'bi-exclamation-triangle-fill',
            };
            $rows .= '<div class="dbx-install-check dbx-install-check--' . $this->h($check['status']) . '">'
                . '<span class="dbx-install-check__icon"><i class="bi ' . $icon . '"></i></span>'
                . '<span class="dbx-install-check__body"><strong>' . $this->h($check['label']) . '</strong>'
                . '<small>' . $this->h($check['hint']) . '</small></span>'
                . '<span class="dbx-install-check__value">' . $this->h($check['value']) . '</span>'
                . '</div>';
        }

        $button = $required_ok
            ? '<button class="btn btn-primary btn-lg" type="submit"><span>Weiter zur Grundkonfiguration</span><i class="bi bi-arrow-right"></i></button>'
            : '<button class="btn btn-secondary btn-lg" type="button" disabled><i class="bi bi-lock"></i><span>Notwendige Prüfungen beheben</span></button>';

        return '<div class="dbx-install-section-head"><span class="dbx-install-kicker">Schritt 1</span>'
            . '<h2>Ist der Server bereit?</h2>'
            . '<p>dbxapp prüft zuerst die wirklich notwendigen Werte. Optionale Erweiterungen können später ergänzt werden.</p></div>'
            . '<div class="dbx-install-metrics">'
            . $this->metric('PHP', PHP_VERSION, 'bi-filetype-php')
            . $this->metric('Server', $this->server_label(), 'bi-hdd-rack')
            . $this->metric('Speicher', (string)ini_get('memory_limit'), 'bi-memory')
            . '</div>'
            . '<div class="dbx-install-checks">' . $rows . '</div>'
            . $this->form_open(1)
            . '<div class="dbx-install-actions">'
            . '<button class="btn btn-outline-secondary btn-lg" type="button" data-dbx-leave-allow onclick="window.location.reload()"><i class="bi bi-arrow-clockwise"></i><span>Erneut prüfen</span></button>'
            . $button
            . '</div></form>';
    }

    private function render_basics(): string
    {
        $config = dbx()->get_cfg('dbx');
        $config = is_array($config) ? $config : array();
        $values = array_replace(
            array(
                'site_title' => (string)($config['site_title'] ?? ($config['page'] ?? 'dbxapp')),
                'brand_name' => (string)($config['brand_name'] ?? 'dbxapp'),
                'brand_tagline' => (string)($config['brand_tagline'] ?? ''),
                'default_design_user' => (string)($config['default_design_user'] ?? 'dbxapp'),
                'default_design_admin' => (string)($config['default_design_admin'] ?? 'dbxapp'),
                'default_lng' => (string)($config['default_lng'] ?? 'de'),
                'timezone' => (string)($config['timezone'] ?? 'Europe/Berlin'),
            ),
            (array)$this->state('basics', array())
        );
        $design_options = $this->select_options(
            $this->design_options(),
            (string)$values['default_design_user']
        );
        $admin_design_options = $this->select_options(
            $this->design_options(),
            (string)$values['default_design_admin']
        );

        return '<div class="dbx-install-section-head"><span class="dbx-install-kicker">Schritt 2</span>'
            . '<h2>Identität und Standarddesign</h2>'
            . '<p>Diese Angaben bilden den globalen Rahmen. Inhalte und Design können später im Adminbereich weiterentwickelt werden.</p></div>'
            . $this->form_open(2)
            . '<div class="row g-4">'
            . '<div class="col-lg-7"><div class="dbx-install-card"><h3><i class="bi bi-window"></i> Website</h3>'
            . $this->input('site_title', 'Seitentitel / App-Name', (string)$values['site_title'], 'text', true, 'Zum Beispiel: Kundenportal Muster GmbH')
            . $this->input('brand_name', 'Markenname', (string)$values['brand_name'], 'text', true, 'Wird in Branding, Dokumenttitel und E-Mails verwendet.')
            . $this->input('brand_tagline', 'Claim / Kurzbeschreibung', (string)$values['brand_tagline'], 'text', false, 'Optional, maximal 180 Zeichen')
            . '<div class="row g-3"><div class="col-md-6">' . $this->field_label('default_lng', 'Standardsprache')
            . '<select class="form-select" id="default_lng" name="default_lng">'
            . $this->select_options(array('de' => 'Deutsch', 'en' => 'English', 'es' => 'Español'), (string)$values['default_lng'])
            . '</select></div><div class="col-md-6">'
            . $this->input('timezone', 'Zeitzone', (string)$values['timezone'], 'text', true, 'Zum Beispiel: Europe/Berlin')
            . '</div></div></div></div>'
            . '<div class="col-lg-5"><div class="dbx-install-card"><h3><i class="bi bi-palette2"></i> Design</h3>'
            . $this->field_label('default_design_user', 'Standarddesign')
            . '<select class="form-select mb-3" id="default_design_user" name="default_design_user">' . $design_options . '</select>'
            . $this->field_label('default_design_admin', 'Admin-Design')
            . '<select class="form-select" id="default_design_admin" name="default_design_admin">' . $admin_design_options . '</select>'
            . '<p class="dbx-install-help"><i class="bi bi-stars"></i> Das gewählte Design ist der Startpunkt. Es kann später im Design-Studio auch KI-gestützt nach Ihren eigenen Vorgaben angepasst oder als neues Design aufgebaut werden.</p>'
            . '<div class="dbx-install-help-actions dbx-install-help-actions--card">'
            . $this->installer_help_button('design', 'KI-gestützte Designanpassung erklärt', 'bi-magic')
            . '</div>'
            . '</div></div></div>'
            . $this->actions(2, 'Weiter zum Datenspeicher')
            . '</form>';
    }

    private function render_database(): string
    {
        $config = dbx()->get_cfg('dbx');
        $config = is_array($config) ? $config : array();
        $profile = is_array($config['db'][self::SQL_SERVER] ?? null)
            ? $config['db'][self::SQL_SERVER]
            : array();
        $saved = (array)$this->state('database', array());
        $mode = (string)($saved['mode'] ?? 'sqlite');
        $db_type = (string)($saved['type'] ?? ($profile['type'] ?? 'mysql'));
        return '<div class="dbx-install-section-head"><span class="dbx-install-kicker">Schritt 3</span>'
            . '<h2>Wo sollen die Daten liegen?</h2>'
            . '<p>Die mitgelieferten DB3-Dateien sind sofort einsatzbereit. Tabellenstruktur und Fachdaten bleiben erhalten; das persönliche Passwort für den Benutzer <strong>admin</strong> legen Sie in Schritt 5 fest. Ein externer PDO-Server ist eine optionale bewusste Umstellung.</p></div>'
            . $this->form_open(3)
            . '<div class="dbx-install-choice-grid">'
            . $this->choice('storage_mode', 'sqlite', $mode, 'bi-device-ssd', 'Mitgelieferte DB3', 'Ohne Datenbankserver starten; den persönlichen Admin-Zugang legen Sie später fest.', 'Standard')
            . $this->choice('storage_mode', 'mysql', $mode, 'bi-database-check', 'PDO-Datenbankserver', 'MySQL/MariaDB, PostgreSQL oder SQL Server als gemeinsames DD-Ziel.', 'Optional')
            . $this->choice('storage_mode', 'configured', $mode, 'bi-diagram-3', 'Bereits eingerichtete Datenbanken', 'Vorhandene Datenbankziele der Module unverändert weiterverwenden.', 'Fortgeschritten')
            . '</div>'
            . '<div class="dbx-install-help-actions" aria-label="Hilfe zum Datenspeicher">'
            . $this->installer_help_button('db3', 'Mitgelieferte DB3 genau erklärt', 'bi-info-circle')
            . $this->installer_help_button('pdo', 'PDO-Migration Schritt für Schritt', 'bi-arrow-left-right')
            . '</div>'
            . '<div class="alert alert-warning dbx-install-inline-note dbx-install-storage-confirm" data-install-storage-confirm'
            . ($mode === 'sqlite' ? ' hidden' : '')
            . '><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Erweiterte Datenbankauswahl bestätigen</strong>'
            . '<span>Die mitgelieferten DB3-Datenbanken sind der einfache, sofort einsatzbereite Standard: Es werden keine Server-Zugangsdaten und keine Migration benötigt. Einen PDO-Server oder bereits eingerichtete Datenbanken können Sie auch später in der Administration auswählen.</span>'
            . $this->switch_input(
                'storage_advanced_confirm',
                'Ich möchte die ausgewählte erweiterte Datenbanklösung jetzt verwenden.',
                $this->post_bool('storage_advanced_confirm'),
                'Die Auswahl wird erst nach dieser ausdrücklichen Bestätigung geprüft und übernommen.'
            )
            . '</div></div>'
            . '<div class="dbx-install-card dbx-install-db-fields" data-install-db-fields>'
            . '<h3><i class="bi bi-server"></i> PDO-Verbindung</h3>'
            . '<div class="row g-3">'
            . '<div class="col-12">' . $this->field_label('db_type', 'Datenbanktyp')
            . '<select class="form-select" id="db_type" name="db_type">'
            . $this->select_options(
                array(
                    'mysql' => 'MySQL / MariaDB',
                    'pgsql' => 'PostgreSQL',
                    'sqlsrv' => 'Microsoft SQL Server',
                ),
                $db_type
            )
            . '</select></div>'
            . '<div class="col-md-8">' . $this->input('db_host', 'Server / Host', (string)($saved['host'] ?? ($profile['host'] ?? '127.0.0.1')), 'text', false, '127.0.0.1 oder Hostname') . '</div>'
            . '<div class="col-md-4">' . $this->input('db_port', 'Port', (string)($saved['port'] ?? ($profile['port'] ?? 3306)), 'number', false, '3306') . '</div>'
            . '<div class="col-md-6">' . $this->input('db_name', 'Datenbank', (string)($saved['database'] ?? ($profile['dbname'] ?? 'dbxapp')), 'text', false, 'dbxapp') . '</div>'
            . '<div class="col-md-6">' . $this->input('db_user', 'Benutzer', (string)($saved['user'] ?? ($profile['user'] ?? '')), 'text', false, '') . '</div>'
            . '<div class="col-12">' . $this->input('db_password', 'Passwort', '', 'password', false, !empty($profile['pass']) ? 'Leer lassen: vorhandenes Passwort beibehalten' : 'Wird nur in config.local.php gespeichert', 'new-password') . '</div>'
            . '</div>'
            . $this->switch_input('db_create', 'Datenbank bei Bedarf automatisch erstellen', true, 'Benötigt CREATE DATABASE-Recht.')
            . $this->switch_input('migrate_data', 'Vorhandene lokale DD-Daten auf SQL übertragen', !empty($saved['migrate_data']), 'Explizite Migration: vorhandene Zieltabellen werden mit den lokalen Quelldaten ersetzt.')
            . '</div>'
            . '<div class="alert alert-info dbx-install-inline-note" data-install-storage-note="sqlite"'
            . ($mode === 'sqlite' ? '' : ' hidden')
            . '><i class="bi bi-shield-check"></i><div><strong>DB3-Standard</strong><span>Für die mitgelieferten DB3-Datenbanken sind keine Server-Zugangsdaten erforderlich. Tabellen und Fachdaten werden nicht neu angelegt oder kopiert. Im Administrationsschritt legen Sie direkt Ihr persönliches Admin-Passwort fest.</span></div></div>'
            . '<div class="alert alert-info dbx-install-inline-note" data-install-storage-note="mysql"'
            . ($mode === 'mysql' ? '' : ' hidden')
            . '><i class="bi bi-database-check"></i><div><strong>PDO-Server</strong><span>Der Installer prüft zuerst die Verbindung zur angegebenen Zieldatenbank. Nur nach erfolgreicher Verbindung wird der Server vorbereitet. Tabellen und Daten werden erst im nächsten Schritt verarbeitet.</span></div></div>'
            . '<div class="alert alert-info dbx-install-inline-note" data-install-storage-note="configured"'
            . ($mode === 'configured' ? '' : ' hidden')
            . '><i class="bi bi-diagram-3"></i><div><strong>Bereits eingerichtete Datenbanken</strong><span>dbxapp verwendet die schon konfigurierten Datenbankziele der einzelnen Module unverändert weiter. Diese Auswahl ist für bestehende oder besondere Installationen gedacht.</span></div></div>'
            . $this->checked_status(
                $saved,
                'Datenspeicher geprüft',
                match ($mode) {
                    'mysql' => 'Die PDO-Verbindung zur Zieldatenbank war erfolgreich. Konfiguration und DD-Serverbindungen wurden übernommen.',
                    'configured' => 'Die bereits eingerichteten Datenbankziele der Module wurden übernommen.',
                    default => 'PDO SQLite ist verfügbar und der mitgelieferte DB3-Standard wurde ausgewählt. Die Tabellen werden im nächsten Schritt lesend geprüft.',
                }
            )
            . $this->actions_with_check(
                3,
                'Weiter zu Strukturen & Daten',
                'check_database',
                !empty($saved['checked'])
                    ? 'Datenbank erneut prüfen'
                    : 'Datenbank prüfen'
            )
            . '</form>';
    }

    private function render_schema(): string
    {
        $database = (array)$this->state('database', array());
        $catalog = $this->installer()->discover_dds();
        $mode_labels = array(
            'sqlite' => 'SQLite / DB3 nach DD-Standard',
            'mysql' => 'Externer PDO-Datenbankserver',
            'configured' => 'Bereits eingerichtete Datenbanken',
        );
        $mode = (string)($database['mode'] ?? 'sqlite');
        $migrate = !empty($database['migrate_data']);
        $bundled = $mode === 'sqlite';

        return '<div class="dbx-install-section-head"><span class="dbx-install-kicker">Schritt 4</span>'
            . ($bundled
                ? '<h2>Mitgelieferte DB3-Strukturen bestätigen</h2><p>Beim Weitergehen werden die vorhandenen Datenbanken und Tabellen einmal lesend auf Vollständigkeit geprüft. Danach folgt Schritt 5: Dort legen Sie das persönliche Passwort für den Benutzer <strong>admin</strong> fest.</p>'
                : '<h2>DD-Strukturen synchronisieren</h2><p>dbxapp erzeugt die Datenbanken und Tabellen direkt aus den aktuellen Data Definitions. Nach erfolgreicher Synchronisierung folgt die Einrichtung des Adminzugangs.</p>')
            . '</div>'
            . '<div class="dbx-install-plan">'
            . $this->plan_row('bi-database', 'Zielspeicher', (string)($mode_labels[$mode] ?? $mode))
            . $this->plan_row(
                'bi-table',
                'Data Definitions',
                count($catalog) . ($bundled ? ' ausgelieferte Tabellen werden geprüft' : ' Strukturen gefunden')
            )
            . $this->plan_row(
                'bi-people',
                'Grunddaten',
                $bundled ? 'Mitgelieferte Datensätze bleiben unverändert' : 'Gast-, Mitglieder- und Admin-Gruppe'
            )
            . $this->plan_row(
                'bi-arrow-left-right',
                'Datenübertragung',
                $migrate
                    ? 'Vorhandene lokale Tabellen werden ausdrücklich übertragen'
                    : ($bundled ? 'Nicht erforderlich' : 'Keine Übertragung vorhandener Daten')
            )
            . '</div>'
            . ($migrate
                ? '<div class="alert alert-warning dbx-install-inline-note"><i class="bi bi-exclamation-triangle"></i><div><strong>Bestätigte Migration</strong><span>Vorhandene SQL-Zieltabellen werden vor der Übertragung geleert. Die lokalen Quelldaten bleiben erhalten.</span></div></div>'
                : '')
            . $this->form_open(4)
            . $this->actions(4, 'Weiter zur Administration')
            . '</form>';
    }

    private function installer_help_button(
        string $topic,
        string $label,
        string $icon
    ): string {
        $titles = array(
            'design' => 'Design später KI-gestützt weiterentwickeln',
            'db3' => 'Mitgelieferte DB3 verwenden',
            'pdo' => 'Auf einen PDO-Server migrieren',
            'email' => 'E-Mail-Konfiguration verstehen',
        );
        $title = (string)($titles[$topic] ?? 'Hilfe zur Installation');
        $width = $topic === 'email' ? '900' : '780';
        $url = '?dbx_modul=dbxSetup&dbx_run1=install&install_help=' . $topic;
        $config = 'lib=openWin|id=install-help-' . $topic
            . '|url=' . $url
            . '|title=' . $title
            . '|mode=modal|width=' . $width . '|height=82%|position=center'
            . '|closable=1|minimizable=0|maximizable=1|scroll=1|ajax=1';
        return '<button type="button" class="btn btn-outline-primary" data-dbx="'
            . $this->h($config) . '"><i class="bi ' . $this->h($icon)
            . '"></i><span>' . $this->h($label) . '</span></button>';
    }

    private function render_installer_help(string $topic): string
    {
        if ($topic === 'design') {
            return '<article class="dbx-install-help-window">'
                . '<span class="dbx-install-kicker">Flexibler Designstart</span>'
                . '<h2>Das Design später mit KI weiterentwickeln</h2>'
                . '<p>Die Auswahl während der Installation legt nur das anfängliche Standard- und Admin-Design fest. Sie ist keine endgültige Entscheidung: Das Erscheinungsbild kann später im Design-Studio an Ihre Marke, Zielgruppe und Arbeitsabläufe angepasst oder als eigenes Design neu aufgebaut werden.</p>'
                . '<div class="dbx-install-help-window__facts">'
                . '<section><i class="bi bi-palette2"></i><h3>Bestehendes Design anpassen</h3><p>Farben, Typografie, Abstände, Komponenten, Navigation und Branding lassen sich auf Basis des gewählten Designs gezielt verändern.</p></section>'
                . '<section><i class="bi bi-stars"></i><h3>KI als Gestaltungspartner</h3><p>Beschreiben Sie Stil, Markenwerte, Zielgruppe und gewünschte Funktionen in natürlicher Sprache. Daraus können passende Designvarianten und konkrete Anpassungen entwickelt werden.</p></section>'
                . '</div>'
                . '<ol class="dbx-install-help-steps">'
                . '<li><strong>Jetzt eine solide Grundlage wählen.</strong><span>Für den Einstieg ist „dbxapp – Standard“ eine sichere und vollständig unterstützte Ausgangsbasis.</span></li>'
                . '<li><strong>Eigene Vorgaben sammeln.</strong><span>Logo, Hausfarben, Schriftwünsche, Beispiele und Anforderungen helfen der KI, ein konsistentes Ergebnis zu erstellen.</span></li>'
                . '<li><strong>Design anpassen oder neu erstellen.</strong><span>Ein vorhandenes Design kann schrittweise verfeinert oder als getrenntes eigenes Design aufgebaut werden.</span></li>'
                . '<li><strong>Vor dem Einsatz prüfen.</strong><span>Desktop, Mobilansicht, Kontraste, Bedienbarkeit und wichtige Formulare sollten anschließend getestet werden.</span></li>'
                . '</ol>'
                . '<div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Standard- und Admin-Design können unabhängig gewählt und später jederzeit geändert werden. Ihre Inhalte und Daten bleiben davon unberührt.</div>'
                . '</article>';
        }

        if ($topic === 'db3') {
            return '<article class="dbx-install-help-window">'
                . '<span class="dbx-install-kicker">Empfohlener Standard</span>'
                . '<h2>Mitgelieferte DB3 direkt verwenden</h2>'
                . '<p>Die Auslieferung enthält die benötigten <code>*.db3</code>-Dateien bereits mit Tabellen und Grunddaten. Es sind weder Serverdaten noch eine Migration erforderlich.</p>'
                . '<div class="dbx-install-help-window__facts">'
                . '<section><i class="bi bi-database-check"></i><h3>Was der Installer prüft</h3><p>Jede aktuelle Data Definition muss ihre ausgelieferte Tabelle erreichen. Diese Prüfung ist ausschließlich lesend.</p></section>'
                . '<section><i class="bi bi-shield-lock"></i><h3>Was unverändert bleibt</h3><p>Tabellenstruktur und Fachdaten werden weder erzeugt, synchronisiert, geleert noch kopiert. Nur der Benutzer admin wird im Administrationsschritt auf den verbindlichen Installationszugang zurückgesetzt.</p></section>'
                . '</div>'
                . '<ol class="dbx-install-help-steps">'
                . '<li><strong>DB3-Standard auswählen.</strong><span>Die Auswahl ist bereits voreingestellt.</span></li>'
                . '<li><strong>Zur Administration weitergehen.</strong><span>Beim Wechsel prüft der Installer die DB3-Dateien einmal lesend. Fehlende Dateien oder Tabellen stoppen den Ablauf mit einer eindeutigen Meldung.</span></li>'
                . '<li><strong>Administratorzugang bereitstellen.</strong><span>Im Administrationsschritt wird der Benutzer admin angelegt oder sein bestehendes Passwort durch Ihr persönliches Passwort ersetzt.</span></li>'
                . '<li><strong>Direkt sicher anmelden.</strong><span>Nach der Installation verwenden Sie admin und das bereits während der Installation festgelegte persönliche Passwort.</span></li>'
                . '</ol>'
                . '<div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> DB3 eignet sich für den direkten Start, lokale Installationen und portable Sicherungen.</div>'
                . '</article>';
        }

        if ($topic === 'pdo') {
            return '<article class="dbx-install-help-window">'
                . '<span class="dbx-install-kicker">Optionale Migration</span>'
                . '<h2>DB3-Daten auf einen PDO-Server übertragen</h2>'
                . '<p>Die PDO-Option ist für MySQL/MariaDB, PostgreSQL oder Microsoft SQL Server vorgesehen. Verbindung, Datenbank und Migration werden bewusst getrennt geprüft.</p>'
                . '<ol class="dbx-install-help-steps">'
                . '<li><strong>Serverdaten erfassen.</strong><span>Datenbanktyp, Host, Port, Datenbankname und Benutzer sind erforderlich. Vorhandene Werte werden vorbelegt; ein leeres Passwortfeld behält das gespeicherte Passwort.</span></li>'
                . '<li><strong>Zieldatenbank verbinden.</strong><span>Ist sie vorhanden, muss eine direkte PDO-Verbindung gelingen. Optional darf dbxapp eine fehlende Datenbank anlegen.</span></li>'
                . '<li><strong>Verbindung erneut prüfen.</strong><span>Erst eine erfolgreiche Verbindung zur vorhandenen oder neu angelegten Zieldatenbank schaltet den nächsten Schritt frei.</span></li>'
                . '<li><strong>DD-Strukturen erzeugen.</strong><span>Die Tabellen werden aus den aktuellen Data Definitions auf dem verbundenen Ziel aufgebaut.</span></li>'
                . '<li><strong>Daten nur auf Wunsch übertragen.</strong><span>Die zusätzliche Option kopiert die vorhandenen DB3-Daten. Zieltabellen werden dafür vor dem Kopieren geleert; die DB3-Quellen bleiben erhalten.</span></li>'
                . '</ol>'
                . '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i> Ohne erfolgreiche Zielverbindung startet weder eine Schemaänderung noch eine Datenübertragung.</div>'
                . '</article>';
        }

        return '<article class="dbx-install-help-window">'
            . '<span class="dbx-install-kicker">Globaler Maildienst</span>'
            . '<h2>E-Mail-Versand sicher konfigurieren</h2>'
            . '<p>Der globale Maildienst entscheidet, ob dbxapp Nachrichten nur intern protokolliert, vollständig ablehnt oder über einen echten Mailserver versendet. Module können unterschiedliche sichtbare Absender verwenden, solange der ausgewählte Mailserver diese Adressen akzeptiert.</p>'
            . '<div class="dbx-install-help-window__facts">'
            . '<section><i class="bi bi-toggle2-on"></i><h3>Betriebsart</h3><p><strong>Nur intern</strong> sendet nichts ins Netzwerk. <strong>Extern senden</strong> aktiviert den Transport. <strong>Vollständig aus</strong> lehnt Mailereignisse ab.</p></section>'
            . '<section><i class="bi bi-hdd-network"></i><h3>Transport</h3><p>SMTP ist für produktive Systeme meist die beste Wahl. PHP mail() und Sendmail setzen eine korrekt eingerichtete Serverumgebung voraus.</p></section>'
            . '<section><i class="bi bi-person-badge"></i><h3>Sichtbarer Absender</h3><p>Absenderadresse und Absendername erscheinen im From-Header. Ohne Moduladresse dienen sie als globaler Standard.</p></section>'
            . '<section><i class="bi bi-arrow-return-left"></i><h3>Envelope-Absender</h3><p>Der technische Envelope-Absender verarbeitet Rückläufer und wird häufig als Return-Path verwendet. Er ist nicht die normale Antwortadresse.</p></section>'
            . '</div>'
            . '<ol class="dbx-install-help-steps">'
            . '<li><strong>Betriebsart festlegen.</strong><span>Für die sichere Erstinstallation ist „Nur intern“ empfohlen. Externer Versand kann später aktiviert werden.</span></li>'
            . '<li><strong>Mailserver eintragen.</strong><span>Bei SMTP werden Host, Port, Verschlüsselung und gegebenenfalls Benutzer und Passwort benötigt. Ein leeres Passwortfeld behält ein bereits gespeichertes Passwort.</span></li>'
            . '<li><strong>Globalen Standardabsender angeben.</strong><span>Verwenden Sie eine reale Adresse Ihrer eigenen Domain, die beim Mailanbieter als Absender zugelassen ist.</span></li>'
            . '<li><strong>Geschäftsbereiche trennen.</strong><span>Kontaktanfragen und Shop-Nachrichten erhalten eigene frei wählbare From-Adressen. Diese Werte werden lokal als <code>dbxContact.mail_from</code> und <code>dbxShop.mail_from</code> gespeichert und können von nachgelagerten E-Mail-Prozessen eindeutig gefiltert werden.</span></li>'
            . '<li><strong>Absenderdomains zuordnen.</strong><span>Die Domainliste hilft dbxMail bei mehreren Mailprofilen, den passenden Transport zu finden. Sie ersetzt keine Freigabe beim Provider und keine SPF-, DKIM- oder DMARC-Konfiguration.</span></li>'
            . '<li><strong>Test-E-Mail senden.</strong><span>Der Test ist nur bei externem Versand möglich und geht an die beim Administrator hinterlegte Adresse.</span></li>'
            . '</ol>'
            . '<h3 class="dbx-install-help-subtitle">Empfohlene Absenderrollen</h3>'
            . '<div class="dbx-install-help-sender-grid">'
            . '<section><i class="bi bi-shield-lock"></i><h3>System &amp; Administration</h3><p>Zum Beispiel <code>system@ihre-domain.de</code> für Registrierung, Sicherheit und technische Benachrichtigungen.</p></section>'
            . '<section><i class="bi bi-chat-left-text"></i><h3>Kontakt</h3><p>Standardmäßig <code>kontakt@ihre-domain.de</code>. Benachrichtigung, Eingangsbestätigung und Supportantwort verwenden diesen Absender; die Adresse des Anfragenden bleibt Reply-To.</p></section>'
            . '<section><i class="bi bi-bag-check"></i><h3>Shop</h3><p>Standardmäßig <code>shop@ihre-domain.de</code> für Bestellungen, Statusmeldungen und Widerrufe.</p></section>'
            . '</div>'
            . '<div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Ist „Globalen Absender für alle Module erzwingen“ aktiviert, ersetzt der globale From-Absender jede Moduladresse. Das ist hilfreich, wenn der Mailanbieter nur eine einzige Absenderadresse erlaubt.</div>'
            . '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i> Zugangsdaten werden ausschließlich lokal gespeichert. Veröffentlichen Sie SMTP-Passwörter niemals im Repository.</div>'
            . '</article>';
    }

    private function render_admin(): string
    {
        $config = dbx()->get_cfg('dbx');
        $config = is_array($config) ? $config : array();
        try {
            $admin = $this->installer()->inspect_initial_admin();
        } catch (Throwable $exception) {
            $admin = array('exists' => false, 'default_password' => false);
        }
        $admin_exists = !empty($admin['exists']);
        $saved_admin = (array)$this->state('admin', array());
        $password_min_length = (int)($saved_admin['password_min_length']
            ?? ($config['password_min_length'] ?? 6));
        if ($this->is_post() && isset($_POST['password_min_length'])) {
            $password_min_length = $this->post_int('password_min_length', 6);
        }
        return '<div class="dbx-install-section-head"><span class="dbx-install-kicker">Schritt 5</span>'
            . '<h2>Administrator absichern</h2>'
            . '<p>Der Benutzername lautet immer <strong>admin</strong>. Legen Sie jetzt direkt das persönliche Passwort fest, mit dem Sie sich nach der Installation anmelden.</p></div>'
            . $this->form_open(5, 'off')
            . '<div class="row g-4"><div class="col-lg-7"><div class="dbx-install-card">'
            . '<h3><i class="bi bi-person-lock"></i> Persönlicher Administratorzugang</h3>'
            . $this->input('initial_admin_user', 'Benutzername', 'admin', 'text', false, '', 'username', true)
            . '<div class="row g-3"><div class="col-md-6">'
            . $this->input('admin_password', 'Admin-Passwort', '', 'password', true, 'Persönliches Passwort', 'new-password')
            . '</div><div class="col-md-6">'
            . $this->input('admin_password_repeat', 'Passwort wiederholen', '', 'password', true, 'Noch einmal eingeben', 'new-password')
            . '</div></div>'
            . '<div class="mb-3">' . $this->field_label('password_min_length', 'Mindestlänge für neue Passwörter', true)
            . '<input class="form-control" id="password_min_length" name="password_min_length" type="number" min="6" max="128" step="1" required value="' . $this->h((string)$password_min_length) . '">'
            . '<p class="dbx-install-field-note"><i class="bi bi-info-circle"></i> Standard: 6 Zeichen. Für höhere Sicherheit werden 12 oder mehr Zeichen empfohlen. Dieselbe Vorgabe gilt später bei erzwungenen Passwortänderungen.</p></div>'
            . ($admin_exists
                ? '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Das bisherige Passwort des vorhandenen Benutzers <strong>admin</strong> wird durch das hier eingegebene persönliche Passwort ersetzt.</div>'
                : '<div class="alert alert-info mb-0"><i class="bi bi-info-circle-fill"></i> Das persönliche Passwort wird direkt sicher gehasht gespeichert und nicht in die Installationszusammenfassung übernommen.</div>')
            . '</div></div><div class="col-lg-5"><div class="dbx-install-card dbx-install-security-card">'
            . '<i class="bi bi-shield-check"></i><h3>Passwortanforderungen</h3>'
            . '<ul class="dbx-install-password-rules" data-install-password-rules data-min-length="' . $this->h((string)$password_min_length) . '">'
            . '<li data-password-rule="length"><i class="bi bi-circle"></i><span>mindestens <strong data-password-min-label>' . $this->h((string)$password_min_length) . '</strong> Zeichen; 12 oder mehr empfohlen</span></li>'
            . '<li data-password-rule="letters"><i class="bi bi-circle"></i><span>Groß- und Kleinbuchstaben</span></li>'
            . '<li data-password-rule="number"><i class="bi bi-circle"></i><span>mindestens eine Zahl</span></li>'
            . '<li data-password-rule="special"><i class="bi bi-circle"></i><span>mindestens ein Sonderzeichen</span></li>'
            . '<li data-password-rule="match"><i class="bi bi-circle"></i><span>beide Eingaben stimmen überein</span></li>'
            . '</ul><p>Die Kriterien werden beim Tippen sofort geprüft.</p>'
            . '</div></div></div>'
            . $this->actions(5, 'Administratorzugang speichern')
            . '</form>';
    }

    private function render_mail(): string
    {
        $config = dbx()->get_cfg('dbx');
        $config = is_array($config) ? $config : array();
        $profile = is_array($config['mail'][self::SQL_SERVER] ?? null)
            ? $config['mail'][self::SQL_SERVER]
            : array();
        $admin = (array)$this->state('admin', array());
        $basics = (array)$this->state('basics', array());
        $saved = (array)$this->state('mail', array());
        $mode = (string)($saved['mode'] ?? ($config['mail_delivery_mode'] ?? 'internal'));
        $transport = (string)($saved['transport'] ?? ($profile['transport'] ?? 'smtp'));
        $global_from = (string)($saved['from_email']
            ?? ($profile['from_email'] ?? ($admin['email'] ?? '')));
        $contact_config = dbx()->get_cfg('dbxContact');
        $contact_config = is_array($contact_config) ? $contact_config : array();
        $shop_config = dbx()->get_cfg('dbxShop');
        $shop_config = is_array($shop_config) ? $shop_config : array();
        $contact_derived = $this->module_sender_address('kontakt', $global_from);
        $contact_from = (string)($saved['contact_from']
            ?? ($contact_config['mail_from'] ?? ''));
        if (filter_var($contact_from, FILTER_VALIDATE_EMAIL) === false
            || (!array_key_exists('contact_from', $saved)
                && strtolower($contact_from) === 'kontakt@dbxapp.de'
                && $contact_derived !== 'kontakt@dbxapp.de')
        ) {
            $contact_from = $contact_derived;
        }
        $shop_derived = $this->module_sender_address('shop', $global_from);
        $shop_from = (string)($saved['shop_from']
            ?? ($shop_config['mail_from'] ?? ''));
        if (filter_var($shop_from, FILTER_VALIDATE_EMAIL) === false
            || (!array_key_exists('shop_from', $saved)
                && strtolower($shop_from) === 'shop@dbxapp.de'
                && $shop_derived !== 'shop@dbxapp.de')
        ) {
            $shop_from = $shop_derived;
        }
        $test_recipient = (string)($saved['test_recipient']
            ?? ($admin['email'] ?? ''));
        if (filter_var($test_recipient, FILTER_VALIDATE_EMAIL) === false
            && filter_var($global_from, FILTER_VALIDATE_EMAIL) !== false
        ) {
            $test_recipient = $global_from;
        }

        return '<div class="dbx-install-section-head"><span class="dbx-install-kicker">Schritt 6</span>'
            . '<h2>Globaler E-Mail-Betrieb</h2>'
            . '<p>Der Schalter gilt zentral für alle Module. Standardmäßig bleiben E-Mail-Ereignisse intern und verlassen den Server nicht.</p></div>'
            . '<div class="dbx-install-help-actions" aria-label="Hilfe zur E-Mail-Konfiguration">'
            . $this->installer_help_button('email', 'E-Mail-Konfiguration ausführlich erklärt', 'bi-question-circle')
            . '</div>'
            . $this->form_open(6, 'off')
            . '<div class="dbx-install-choice-grid dbx-install-choice-grid--compact">'
            . $this->choice('mail_delivery_mode', 'internal', $mode, 'bi-inbox', 'Nur intern', 'Kein externer Versand; Ereignisse bleiben im System.', 'Sicherer Standard')
            . $this->choice('mail_delivery_mode', 'external', $mode, 'bi-send-check', 'Extern senden', 'SMTP, PHP-Mail oder Sendmail aktivieren.', 'Aktiv')
            . $this->choice('mail_delivery_mode', 'disabled', $mode, 'bi-envelope-slash', 'Vollständig aus', 'Alle Mailereignisse global ablehnen.', 'Gesperrt')
            . '</div>'
            . '<div class="dbx-install-card dbx-install-mail-card" data-install-mail-fields'
            . ($mode === 'external' ? '' : ' hidden')
            . '><div class="dbx-install-mail-sections">'
            . '<section class="dbx-install-mail-section"><h3><i class="bi bi-hdd-network"></i> Versandserver</h3>'
            . '<div class="row g-2"><div class="col-md-4">' . $this->field_label('mail_transport', 'Transport')
            . '<select class="form-select" id="mail_transport" name="mail_transport">'
            . $this->select_options(array('smtp' => 'SMTP', 'mail' => 'PHP mail()', 'sendmail' => 'Sendmail'), $transport)
            . '</select></div><div class="col-md-5">'
            . $this->input('mail_host', 'SMTP-Server', (string)($saved['host'] ?? ($profile['host'] ?? '')), 'text', false, 'smtp.example.org')
            . '</div><div class="col-md-3">'
            . $this->input('mail_port', 'Port', (string)($saved['port'] ?? ($profile['port'] ?? 587)), 'number', false, '587')
            . '</div><div class="col-md-4">' . $this->field_label('mail_secure', 'Verschlüsselung')
            . '<select class="form-select" id="mail_secure" name="mail_secure">'
            . $this->select_options(array('tls' => 'TLS / STARTTLS', 'ssl' => 'SSL / SMTPS', '' => 'Keine'), (string)($saved['secure'] ?? ($profile['secure'] ?? 'tls')))
            . '</select></div><div class="col-md-8 d-flex align-items-end">'
            . $this->switch_input('mail_auth', 'SMTP-Authentifizierung', !empty($saved['auth'] ?? $profile['auth']), '')
            . '</div><div class="col-md-6">'
            . $this->input('mail_user', 'SMTP-Benutzer', (string)($saved['user'] ?? ($profile['user'] ?? '')), 'text', false, 'meist die E-Mail-Adresse', 'username')
            . '</div><div class="col-md-6">'
            . $this->input(
                'mail_password',
                'SMTP-Passwort',
                '',
                'password',
                false,
                !empty($profile['pass'])
                    ? 'Leer lassen: vorhandenes Passwort beibehalten'
                    : 'Wird lokal und nicht im Release gespeichert',
                'new-password'
            )
            . '</div></div></section>'
            . '<section class="dbx-install-mail-section"><h3><i class="bi bi-person-vcard"></i> Globaler Absender</h3>'
            . '<div class="row g-2"><div class="col-md-6">'
            . $this->input('mail_from_email', 'Absenderadresse', $global_from, 'email', false, 'noreply@example.org')
            . '</div><div class="col-md-6">'
            . $this->input('mail_from_name', 'Absendername', (string)($saved['from_name'] ?? ($profile['from_name'] ?? ($basics['brand_name'] ?? 'dbxapp'))), 'text', false, 'Name der Website')
            . '</div><div class="col-md-6">'
            . $this->input('mail_sender', 'Envelope-Absender', (string)($saved['sender'] ?? ($profile['sender'] ?? '')), 'email', false, 'Optional, für Bounces')
            . '</div><div class="col-md-6">'
            . $this->input('mail_from_domains', 'Erlaubte Domains', (string)($saved['from_domains'] ?? ($profile['from_domains'] ?? '')), 'text', false, 'example.org,*.example.org')
            . '</div></div></section>'
            . '<section class="dbx-install-mail-section"><h3><i class="bi bi-signpost-split"></i> Modul-Absender</h3>'
            . '<p class="dbx-install-mail-intro">Getrennte Adressen erleichtern die automatische Zuordnung beim Empfänger.</p>'
            . '<div class="row g-2"><div class="col-md-6">'
            . $this->input('contact_mail_from', 'Kontaktanfragen', $contact_from, 'email', true, 'kontakt@example.org')
            . '<p class="dbx-install-field-note">Anfragende bleiben als Reply-To erhalten.</p>'
            . '</div><div class="col-md-6">'
            . $this->input('shop_mail_from', 'Shop-Nachrichten', $shop_from, 'email', true, 'shop@example.org')
            . '<p class="dbx-install-field-note">Für Bestellungen, Status und Widerrufe.</p>'
            . '</div></div>'
            . $this->switch_input('mail_force_from', 'Globalen Absender erzwingen', !empty($saved['force_from'] ?? $profile['force_from']), 'Nur wenn der Mailanbieter keine unterschiedlichen Absender erlaubt.')
            . '</section>'
            . '<section class="dbx-install-mail-section"><h3><i class="bi bi-envelope-check"></i> Versandtest</h3>'
            . $this->input('mail_test_recipient', 'Empfänger der Test-E-Mail', $test_recipient, 'email', false, 'admin@example.org')
            . $this->switch_input('mail_send_test', 'Beim Prüfen eine Test-E-Mail senden', false, 'Nur bei „Extern senden“. Ohne diese ausdrückliche Auswahl wird keine E-Mail versendet.')
            . '</section></div></div>'
            . $this->checked_status(
                $saved,
                'E-Mail-Konfiguration geprüft',
                !empty($saved['tested'])
                    ? 'Die Konfiguration ist gültig und die Test-E-Mail wurde erfolgreich versendet.'
                    : 'Die Angaben sind gültig und lokal gespeichert. Es wurde keine Test-E-Mail versendet.'
            )
            . $this->actions_with_check(
                6,
                'Weiter zur Abschlussprüfung',
                'check_mail',
                !empty($saved['checked'])
                    ? 'E-Mail erneut prüfen'
                    : 'E-Mail prüfen'
            )
            . '</form>'
            . '<script>document.addEventListener("DOMContentLoaded",function(){const box=document.querySelector("[data-install-mail-fields]");const radios=document.querySelectorAll("input[name=mail_delivery_mode]");function sync(){const active=document.querySelector("input[name=mail_delivery_mode]:checked");if(box)box.hidden=!active||active.value!=="external";}radios.forEach(function(r){r.addEventListener("change",sync)});sync();const globalFrom=document.getElementById("mail_from_email");const roles=[["contact_mail_from","kontakt"],["shop_mail_from","shop"]];function domain(){const value=(globalFrom&&globalFrom.value||"").trim().toLowerCase();const at=value.lastIndexOf("@");return at>0&&at<value.length-1?value.slice(at+1):"";}roles.forEach(function(entry){const input=document.getElementById(entry[0]);if(!input||!globalFrom)return;const initialDomain=domain();const initialAuto=entry[1]+"@"+initialDomain;let automatic=input.value.trim().toLowerCase()===initialAuto||input.value.trim().toLowerCase()===entry[1]+"@dbxapp.de";input.addEventListener("input",function(){automatic=false;});globalFrom.addEventListener("input",function(){const nextDomain=domain();if(automatic&&nextDomain)input.value=entry[1]+"@"+nextDomain;});});});</script>';
    }

    private function render_finish(): string
    {
        $basics = (array)$this->state('basics', array());
        $database = (array)$this->state('database', array());
        $schema = (array)$this->state('schema', array());
        $admin = (array)$this->state('admin', array());
        $mail = (array)$this->state('mail', array());
        $database_label = match ((string)($database['mode'] ?? 'sqlite')) {
            'mysql' => strtoupper((string)($database['type'] ?? 'mysql'))
                . ' · ' . (string)($database['host'] ?? '')
                . ' / ' . (string)($database['database'] ?? ''),
            'configured' => 'Bereits eingerichtete Datenbanken',
            default => 'SQLite / DB3 nach DD-Standard',
        };
        $mail_label = match ((string)($mail['mode'] ?? 'internal')) {
            'external' => 'Externer Versand über ' . strtoupper((string)($mail['transport'] ?? 'smtp')),
            'disabled' => 'Vollständig deaktiviert',
            default => 'Nur interne Annahme, kein Netzwerkversand',
        };
        $contact_from = (string)($mail['contact_from']
            ?? $this->module_sender_address('kontakt', (string)($mail['from_email'] ?? '')));
        $shop_from = (string)($mail['shop_from']
            ?? $this->module_sender_address('shop', (string)($mail['from_email'] ?? '')));

        return '<div class="dbx-install-section-head"><span class="dbx-install-kicker">Schritt 7</span>'
            . '<h2>Bereit für den Start</h2>'
            . '<p>Geheimnisse werden in dieser Übersicht bewusst nicht angezeigt.</p></div>'
            . '<div class="dbx-install-summary">'
            . $this->summary_row('bi-window', 'Website', (string)($basics['site_title'] ?? ''), (string)($basics['brand_name'] ?? ''))
            . $this->summary_row('bi-palette', 'Design', (string)($basics['default_design_user'] ?? 'dbxapp'), 'Admin: ' . (string)($basics['default_design_admin'] ?? 'dbxapp'))
            . $this->summary_row('bi-database-check', 'Daten', $database_label, (int)($schema['finished'] ?? 0) . ' DD-Strukturen synchronisiert')
            . $this->summary_row('bi-person-check', 'Administrator', 'admin', 'Persönliches Passwort eingerichtet · Mindestlänge ' . (string)($admin['password_min_length'] ?? 6) . ' Zeichen')
            . $this->summary_row('bi-envelope-check', 'E-Mail', $mail_label, (string)($mail['from_email'] ?? ''))
            . $this->summary_row('bi-chat-left-text', 'Kontakt-Absender', $contact_from, 'dbxContact.mail_from')
            . $this->summary_row('bi-bag-check', 'Shop-Absender', $shop_from, 'dbxShop.mail_from')
            . '</div>'
            . $this->form_open(7)
            . '<label class="dbx-install-confirm"><input class="form-check-input" type="checkbox" name="confirm_install" value="1" required>'
            . '<span><strong>Installation verbindlich abschließen' . $this->tooltip_icon($this->field_tooltip('confirm_install')) . '</strong><small>dbxapp setzt den lokalen Installationsschalter auf 0 und startet danach im normalen Betriebsmodus.</small></span></label>'
            . '<div class="dbx-install-actions">' . $this->back_link(7)
            . '<button class="btn btn-success btn-lg" type="submit"><i class="bi bi-check2-circle"></i><span>dbxapp jetzt aktivieren</span></button>'
            . '</div></form>';
    }

    private function render_shell(int $step, string $content): string
    {
        $alerts = '';
        foreach ($this->errors as $message) {
            $alerts .= '<div class="alert alert-danger dbx-install-alert"><i class="bi bi-x-octagon-fill"></i><span>' . $this->h($message) . '</span></div>';
        }
        foreach ($this->notices as $message) {
            $alerts .= '<div class="alert alert-success dbx-install-alert"><i class="bi bi-check-circle-fill"></i><span>' . $this->h($message) . '</span></div>';
        }

        $completed = (int)$this->state('completed', 0);
        $nav = '';
        foreach ($this->steps as $number => $definition) {
            $class = $number === $step
                ? 'is-active'
                : ($number <= $completed ? 'is-complete' : 'is-pending');
            $inner = '<span class="dbx-install-step__number">'
                . ($number <= $completed && $number !== $step
                    ? '<i class="bi bi-check-lg"></i>'
                    : (string)$number)
                . '</span><span class="dbx-install-step__text"><small>'
                . $this->h($definition['short'])
                . '</small><strong>' . $this->h($definition['title']) . '</strong></span>';
            if ($number <= $completed + 1 && $number !== $step) {
                $nav .= '<a class="dbx-install-step ' . $class . '" href="' . $this->h($this->step_url($number)) . '">' . $inner . '</a>';
            } else {
                $nav .= '<span class="dbx-install-step ' . $class . '">' . $inner . '</span>';
            }
        }
        $progress = (int)round((($step - 1) / self::TOTAL_STEPS) * 100);

        return '<div class="dbx-install-layout">'
            . '<aside class="dbx-install-sidebar">'
            . '<a class="dbx-install-brand" href="' . $this->h($this->step_url(1)) . '"><span class="dbx-install-brand__mark"><i class="bi bi-boxes"></i></span>'
            . '<span><strong>dbxapp</strong><small>Installationsassistent</small></span></a>'
            . '<div class="dbx-install-progress"><span style="width:' . $progress . '%"></span></div>'
            . '<nav aria-label="Installationsschritte">' . $nav . '</nav>'
            . '<div class="dbx-install-sidebar__foot"><i class="bi bi-shield-lock"></i><span>Lokale Konfiguration<br><strong>Release-geschützt</strong></span></div>'
            . '</aside>'
            . '<main class="dbx-install-main"><header class="dbx-install-topbar">'
            . '<div><span>Erstinstallation</span><strong>Schritt ' . $step . ' von ' . self::TOTAL_STEPS . '</strong></div>'
            . '<span class="dbx-install-version">Entwicklungsstand ' . $this->h($this->version()) . '</span>'
            . '</header><section class="dbx-install-content">'
            . $alerts
            . '<div class="dbx-install-surface">' . $content . '</div>'
            . '</section></main></div>';
    }

    private function render_finished(): string
    {
        $database = (array)($this->finish_result['database'] ?? array());
        $database_label = ($database['mode'] ?? '') === 'mysql'
            ? 'PDO / ' . strtoupper((string)($database['type'] ?? 'mysql'))
            : (($database['mode'] ?? '') === 'configured' ? 'Bereits eingerichtete Datenbanken' : 'SQLite / DB3');
        return '<div class="dbx-install-finished"><div class="dbx-install-finished__glow"></div>'
            . '<div class="dbx-install-finished__card"><span class="dbx-install-finished__icon"><i class="bi bi-check2"></i></span>'
            . '<span class="dbx-install-kicker">Installation abgeschlossen</span>'
            . '<h1>' . $this->h((string)($this->finish_result['site_title'] ?? 'dbxapp')) . ' ist startklar.</h1>'
            . '<p>Systemprüfung, DD-Strukturen, Administrator und globale E-Mail-Regeln sind eingerichtet. Melden Sie sich jetzt als <strong>admin</strong> mit dem während der Installation festgelegten persönlichen Passwort an.</p>'
            . '<div class="dbx-install-finished__facts">'
            . '<span><i class="bi bi-database-check"></i><strong>' . $this->h($database_label) . '</strong><small>Datenspeicher</small></span>'
            . '<span><i class="bi bi-person-check"></i><strong>admin</strong><small>Persönliches Passwort eingerichtet</small></span>'
            . '<span><i class="bi bi-envelope-check"></i><strong>' . $this->h($this->mail_mode_label((string)($this->finish_result['mail_mode'] ?? 'internal'))) . '</strong><small>E-Mail-Betrieb</small></span>'
            . '</div><a class="btn btn-light btn-lg" href="?dbx_modul=dbxLogin&dbx_run1=login"><span>Zur Anmeldung</span><i class="bi bi-arrow-right"></i></a>'
            . '<small class="dbx-install-finished__hint">Der lokale Installationsschalter wurde sicher auf 0 gesetzt.</small>'
            . '</div></div>';
    }

    private function render_already_installed(): string
    {
        return '<div class="dbx-install-finished"><div class="dbx-install-finished__card">'
            . '<span class="dbx-install-finished__icon"><i class="bi bi-shield-check"></i></span>'
            . '<span class="dbx-install-kicker">Systemstatus</span><h1>dbxapp ist bereits installiert.</h1>'
            . '<p>Der Installationsassistent ist im laufenden Betrieb gesperrt.</p>'
            . '<a class="btn btn-light btn-lg" href="' . $this->h(dbx()->get_base_url()) . '"><span>Zur Anwendung</span><i class="bi bi-arrow-right"></i></a>'
            . '</div></div>';
    }

    private function form_open(int $step, string $autocomplete = 'on'): string
    {
        return '<form class="dbx-install-form" method="post" action="' . $this->h($this->step_url($step)) . '" autocomplete="' . $this->h($autocomplete) . '">'
            . '<input type="hidden" name="install_token" value="' . $this->h(dbx()->action_token($this->token_scope($step))) . '">';
    }

    private function actions(int $step, string $next_label): string
    {
        return '<div class="dbx-install-actions">' . $this->back_link($step)
            . '<button class="btn btn-primary btn-lg" type="submit"><span>' . $this->h($next_label) . '</span><i class="bi bi-arrow-right"></i></button></div>';
    }

    private function actions_with_check(
        int $step,
        string $next_label,
        string $check_action,
        string $check_label
    ): string {
        return '<div class="dbx-install-actions">' . $this->back_link($step)
            . '<div class="dbx-install-action-group">'
            . '<button class="btn btn-outline-primary btn-lg" type="submit" name="install_action" value="' . $this->h($check_action) . '"><i class="bi bi-arrow-clockwise"></i><span>' . $this->h($check_label) . '</span></button>'
            . '<button class="btn btn-primary btn-lg" type="submit" name="install_action" value="continue"><span>' . $this->h($next_label) . '</span><i class="bi bi-arrow-right"></i></button>'
            . '</div></div>';
    }

    /**
     * @param array<string,mixed> $state
     */
    private function checked_status(
        array $state,
        string $title,
        string $detail
    ): string {
        if (empty($state['checked'])) {
            return '<div class="alert alert-secondary dbx-install-inline-note dbx-install-check-status" data-install-check-status data-install-checked="0">'
                . '<i class="bi bi-clock-history"></i><div><strong>In dieser Installationssitzung noch nicht geprüft</strong>'
                . '<span>Die vorhandenen Vorgaben sind geladen. Mit „Prüfen“ können Sie den aktuellen Stand kontrollieren, ohne zum nächsten Schritt zu wechseln.</span></div></div>';
        }

        $checked_at = (int)($state['checked_at'] ?? 0);
        $when = $checked_at > 0
            ? date('d.m.Y, H:i', $checked_at) . ' Uhr'
            : 'in dieser Installationssitzung';

        return '<div class="alert alert-success dbx-install-inline-note dbx-install-check-status" data-install-check-status data-install-checked="1">'
            . '<i class="bi bi-check-circle-fill"></i><div><strong>' . $this->h($title) . '</strong>'
            . '<span>' . $this->h($detail) . ' Zuletzt erfolgreich: ' . $this->h($when) . '.</span></div></div>';
    }

    private function back_link(int $step): string
    {
        if ($step <= 1) {
            return '<span></span>';
        }
        return '<a class="btn btn-outline-secondary btn-lg" href="' . $this->h($this->step_url($step - 1)) . '"><i class="bi bi-arrow-left"></i><span>Zurück</span></a>';
    }

    private function field_label(
        string $name,
        string $label,
        bool $required = false
    ): string {
        return '<label class="form-label" for="' . $this->h($name) . '">'
            . $this->h($label)
            . ($required ? ' <span aria-hidden="true">*</span>' : '')
            . $this->tooltip_icon($this->field_tooltip($name))
            . '</label>';
    }

    private function tooltip_icon(string $tooltip): string
    {
        if ($tooltip === '') {
            return '';
        }
        return ' <span class="dbx-install-tooltip" tabindex="0" role="button"'
            . ' data-dbx-tooltip="' . $this->h($tooltip) . '"'
            . ' aria-label="Hilfe: ' . $this->h($tooltip) . '">'
            . '<i class="bi bi-question-circle-fill" aria-hidden="true"></i></span>';
    }

    private function field_tooltip(string $name): string
    {
        $tooltips = array(
            'site_title' => 'Erscheint im Browser-Titel, in Seitentiteln und an zentralen Stellen der Anwendung.',
            'brand_name' => 'Sichtbarer Name der Website oder Organisation, unter anderem für Branding und E-Mails.',
            'brand_tagline' => 'Kurzer Zusatz zum Markennamen, zum Beispiel Leistungsversprechen oder Themen der Website.',
            'default_lng' => 'Sprache, die dbxapp für neue oder noch nicht angemeldete Besucher zuerst verwendet.',
            'timezone' => 'Steuert Datums- und Zeitangaben. Für Deutschland ist Europe/Berlin passend.',
            'default_design_user' => 'Anfängliches Design für öffentliche Seiten und angemeldete Anwender.',
            'default_design_admin' => 'Anfängliches Erscheinungsbild des Administrationsbereichs.',
            'storage_advanced_confirm' => 'Bestätigt bewusst eine erweiterte Datenbanklösung anstelle des einfachen DB3-Standards.',
            'db_type' => 'Typ des externen PDO-Servers. Er muss zur installierten PHP-PDO-Erweiterung passen.',
            'db_host' => 'Hostname oder IP-Adresse des Datenbankservers, zum Beispiel 127.0.0.1 oder db.example.org.',
            'db_port' => 'Netzwerkport des Datenbankservers, zum Beispiel 3306 für MySQL oder MariaDB.',
            'db_name' => 'Name der vorhandenen oder anzulegenden Zieldatenbank.',
            'db_user' => 'Datenbankbenutzer mit den benötigten Rechten für Verbindung, Tabellen und optional die Datenbankanlage.',
            'db_password' => 'Passwort des Datenbankbenutzers. Leer lassen behält ein bereits lokal gespeichertes Passwort bei.',
            'db_create' => 'Legt die Datenbank nur dann an, wenn sie fehlt und der Benutzer das CREATE-DATABASE-Recht besitzt.',
            'migrate_data' => 'Überträgt lokale DB3-Daten ausdrücklich auf den PDO-Server und ersetzt dort vorhandene Zieldaten.',
            'initial_admin_user' => 'Der verbindliche Benutzername des ersten Administrators lautet admin.',
            'admin_password' => 'Persönliches Passwort des Administrators. Es wird ausschließlich als sicherer Passwort-Hash gespeichert.',
            'admin_password_repeat' => 'Wiederholen Sie das persönliche Admin-Passwort, um Eingabefehler auszuschließen.',
            'password_min_length' => 'Kleinster erlaubter Wert für neue Passwörter. Standard sind 6 Zeichen; empfohlen werden mindestens 12.',
            'mail_transport' => 'SMTP ist meist die beste Wahl. PHP mail() und Sendmail benötigen eine passende Serverkonfiguration.',
            'mail_host' => 'Hostname des SMTP-Servers, zum Beispiel smtp.example.org.',
            'mail_port' => 'SMTP-Port des Anbieters, meist 587 für STARTTLS oder 465 für SMTPS.',
            'mail_secure' => 'Verschlüsselung zum SMTP-Server gemäß Vorgabe des Mailanbieters.',
            'mail_auth' => 'Aktiviert die Anmeldung am SMTP-Server mit Benutzername und Passwort.',
            'mail_user' => 'Anmeldename des SMTP-Kontos; häufig die vollständige E-Mail-Adresse.',
            'mail_password' => 'Passwort oder App-Passwort des SMTP-Kontos. Leer lassen behält den vorhandenen lokalen Wert.',
            'mail_from_email' => 'Globale From-Adresse für allgemeine Systemnachrichten.',
            'mail_from_name' => 'Anzeigename, den Empfänger neben der Absenderadresse sehen.',
            'mail_sender' => 'Optionaler technischer Envelope-Absender für Rückläufer und Bounce-Verarbeitung.',
            'mail_from_domains' => 'Kommagetrennte Liste erlaubter Absenderdomains für Modul-Absender.',
            'contact_mail_from' => 'Eigene From-Adresse für Kontaktanfragen; Anfragende bleiben als Reply-To erhalten.',
            'shop_mail_from' => 'Eigene From-Adresse für Bestellungen, Statusmeldungen und Widerrufe.',
            'mail_force_from' => 'Ersetzt Modul-Absender durch die globale Adresse, falls der Anbieter nur einen Absender zulässt.',
            'mail_test_recipient' => 'Adresse, an die der ausdrücklich aktivierte Versandtest geschickt wird.',
            'mail_send_test' => 'Sendet nur bei externem Versand eine echte Test-E-Mail an den angegebenen Empfänger.',
            'confirm_install' => 'Schließt die Installation ab und deaktiviert den Installationsmodus lokal.',
        );
        return (string)($tooltips[$name] ?? '');
    }

    private function input(
        string $name,
        string $label,
        string $value,
        string $type = 'text',
        bool $required = false,
        string $placeholder = '',
        string $autocomplete = '',
        bool $readonly = false
    ): string {
        $input = '<input class="form-control" id="' . $this->h($name) . '" name="' . $this->h($name) . '" type="' . $this->h($type) . '" value="' . $this->h($value) . '"'
            . ($placeholder !== '' ? ' placeholder="' . $this->h($placeholder) . '"' : '')
            . ($autocomplete !== '' ? ' autocomplete="' . $this->h($autocomplete) . '"' : '')
            . ($readonly ? ' readonly' : '')
            . ($required ? ' required' : '') . '>';
        if ($type === 'password') {
            $input = '<div class="dbx-install-password-control">'
                . $input
                . '<button type="button" class="dbx-install-password-toggle" data-dbx-password-toggle="' . $this->h($name) . '" aria-label="Passwort anzeigen" title="Passwort anzeigen" aria-pressed="false">'
                . '<i class="bi bi-eye" aria-hidden="true"></i></button></div>';
        }

        return '<div class="mb-3">' . $this->field_label($name, $label, $required)
            . $input . '</div>';
    }

    private function switch_input(
        string $name,
        string $label,
        bool $checked,
        string $hint
    ): string {
        $tooltip = $this->field_tooltip($name);
        return '<label class="dbx-install-switch"><input class="form-check-input" type="checkbox" name="' . $this->h($name) . '" value="1"' . ($checked ? ' checked' : '') . '>'
            . '<span><strong>' . $this->h($label) . $this->tooltip_icon($tooltip !== '' ? $tooltip : $hint) . '</strong>'
            . ($hint !== '' ? '<small>' . $this->h($hint) . '</small>' : '')
            . '</span></label>';
    }

    private function choice(
        string $name,
        string $value,
        string $selected,
        string $icon,
        string $title,
        string $description,
        string $badge
    ): string {
        return '<label class="dbx-install-choice" data-dbx-tooltip="' . $this->h($description) . '"><input type="radio" name="' . $this->h($name) . '" value="' . $this->h($value) . '"' . ($value === $selected ? ' checked' : '') . '>'
            . '<span class="dbx-install-choice__body"><span class="dbx-install-choice__top"><i class="bi ' . $this->h($icon) . '"></i><em>' . $this->h($badge) . '</em></span>'
            . '<strong>' . $this->h($title) . '</strong><small>' . $this->h($description) . '</small><span class="dbx-install-choice__check"><i class="bi bi-check-lg"></i></span></span></label>';
    }

    private function metric(string $label, string $value, string $icon): string
    {
        return '<div class="dbx-install-metric"><i class="bi ' . $this->h($icon) . '"></i><span><small>' . $this->h($label) . '</small><strong>' . $this->h($value) . '</strong></span></div>';
    }

    private function plan_row(string $icon, string $label, string $value): string
    {
        return '<div class="dbx-install-plan__row"><i class="bi ' . $this->h($icon) . '"></i><span><small>' . $this->h($label) . '</small><strong>' . $this->h($value) . '</strong></span><i class="bi bi-check-circle-fill"></i></div>';
    }

    private function summary_row(
        string $icon,
        string $label,
        string $value,
        string $detail
    ): string {
        return '<div class="dbx-install-summary__row"><i class="bi ' . $this->h($icon) . '"></i><span><small>' . $this->h($label) . '</small><strong>' . $this->h($value) . '</strong>'
            . ($detail !== '' ? '<em>' . $this->h($detail) . '</em>' : '')
            . '</span><i class="bi bi-check2"></i></div>';
    }

    /**
     * @return array<string,string>
     */
    private function design_options(): array
    {
        $root = dbx()->os_path(dbx()->get_base_dir() . 'dbx/design');
        $options = array();
        foreach (glob(rtrim($root, '/\\') . '/*', GLOB_ONLYDIR) ?: array() as $directory) {
            $name = basename($directory);
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,62}$/', $name) !== 1
                || !is_file($directory . '/htm/default.htm')
            ) {
                continue;
            }
            $title = $name === 'dbxapp'
                ? 'dbxapp – Standard'
                : ucfirst(str_replace(array('-', '_'), ' ', $name));
            $metadata_file = $directory . '/design.json';
            if (is_file($metadata_file)) {
                $metadata = json_decode((string)file_get_contents($metadata_file), true);
                if (is_array($metadata) && trim((string)($metadata['title'] ?? '')) !== '') {
                    $title = trim((string)$metadata['title']);
                }
            }
            $options[$name] = $title;
        }
        if (!isset($options['dbxapp'])) {
            $options['dbxapp'] = 'dbxapp – Standard';
        }
        ksort($options, SORT_NATURAL | SORT_FLAG_CASE);
        return $options;
    }

    private function select_options(array $options, string $selected): string
    {
        $html = '';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . $this->h((string)$value) . '"'
                . ((string)$value === $selected ? ' selected' : '')
                . '>' . $this->h((string)$label) . '</option>';
        }
        return $html;
    }

    private function installer(): dbxInstallationService
    {
        dbx()->get_include_obj('dbxInstallationService', 'dbxSetup');
        return new dbxInstallationService();
    }

    private function requested_step(): int
    {
        $value = $_GET['step'] ?? $_GET['stepp'] ?? $_POST['step'] ?? 1;
        $step = filter_var($value, FILTER_VALIDATE_INT);
        return max(1, min(self::TOTAL_STEPS, $step === false ? 1 : (int)$step));
    }

    private function step_url(int $step): string
    {
        return '?dbx_modul=dbxSetup&dbx_run1=install&step='
            . max(1, min(self::TOTAL_STEPS, $step));
    }

    private function is_post(): bool
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    private function token_scope(int $step): string
    {
        return 'dbxSetup.install.step.' . $step;
    }

    private function valid_token(int $step): bool
    {
        $token = is_scalar($_POST['install_token'] ?? null)
            ? (string)$_POST['install_token']
            : '';
        return dbx()->check_action_token($this->token_scope($step), $token);
    }

    private function state(string $key, $default = null)
    {
        return dbx()->get_session_var(
            $key,
            $default,
            self::SESSION_SECTION,
            self::SESSION_MODULE
        );
    }

    private function set_state(string $key, $value): void
    {
        dbx()->set_session_var(
            $key,
            $value,
            self::SESSION_SECTION,
            self::SESSION_MODULE
        );
    }

    private function post_text(string $name, int $max_length): string
    {
        $value = is_scalar($_POST[$name] ?? null) ? trim((string)$_POST[$name]) : '';
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $max_length, 'UTF-8')
            : substr($value, 0, $max_length);
    }

    private function post_key(string $name, int $max_length): string
    {
        $value = $this->post_text($name, $max_length);
        return preg_match('/^[A-Za-z0-9_.\/:+-]*$/', $value) === 1 ? $value : '';
    }

    private function post_database_name(string $name): string
    {
        $value = $this->post_text($name, 64);
        return preg_match('/^[A-Za-z0-9_$-]+$/', $value) === 1 ? $value : '';
    }

    private function post_secret(string $name, int $max_length): string
    {
        $value = is_scalar($_POST[$name] ?? null) ? (string)$_POST[$name] : '';
        return substr(str_replace("\0", '', $value), 0, $max_length);
    }

    private function post_int(string $name, int $default): int
    {
        $value = filter_var($_POST[$name] ?? null, FILTER_VALIDATE_INT);
        return $value === false ? $default : (int)$value;
    }

    private function post_bool(string $name): bool
    {
        return isset($_POST[$name])
            && in_array(strtolower((string)$_POST[$name]), array('1', 'true', 'on', 'yes'), true);
    }

    private function directory_writable(string $directory): bool
    {
        if (!is_dir($directory)
            && !@mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            return false;
        }
        if (!is_writable($directory)) {
            return false;
        }
        $file = @tempnam($directory, 'dbx-inst-');
        if ($file === false) {
            return false;
        }
        $ok = @file_put_contents($file, 'ok', LOCK_EX) !== false
            && @file_get_contents($file) === 'ok';
        @unlink($file);
        return $ok;
    }

    private function compact_path(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', dbx()->get_base_dir()), '/') . '/';
        $normalized = str_replace('\\', '/', $path);
        return str_starts_with(strtolower($normalized), strtolower($base))
            ? './' . ltrim(substr($normalized, strlen($base)), '/')
            : basename($normalized);
    }

    private function ini_bytes(string $value): int
    {
        $value = strtolower(trim($value));
        if ($value === '-1') {
            return -1;
        }
        if ($value === '') {
            return 0;
        }
        $number = (float)$value;
        return match (substr($value, -1)) {
            'g' => (int)($number * 1024 * 1024 * 1024),
            'm' => (int)($number * 1024 * 1024),
            'k' => (int)($number * 1024),
            default => (int)$number,
        };
    }

    private function server_label(): string
    {
        $software = trim((string)($_SERVER['SERVER_SOFTWARE'] ?? 'PHP'));
        if ($software === '') {
            return PHP_SAPI;
        }
        $parts = preg_split('/\s+/', $software);
        $label = (string)($parts[0] ?? $software);
        return function_exists('mb_substr')
            ? mb_substr($label, 0, 32, 'UTF-8')
            : substr($label, 0, 32);
    }

    private function version(): string
    {
        $file = dbx()->os_path(dbx()->get_base_dir() . 'VERSION');
        return is_file($file) ? trim((string)file_get_contents($file)) : 'aktuell';
    }

    private function mail_mode_label(string $mode): string
    {
        return match ($mode) {
            'external' => 'Extern',
            'disabled' => 'Aus',
            default => 'Nur intern',
        };
    }

    private function module_sender_address(string $local_part, string $global_from): string
    {
        $local_part = strtolower(trim($local_part));
        $domain = 'dbxapp.de';
        if (filter_var($global_from, FILTER_VALIDATE_EMAIL) !== false) {
            $candidate = strtolower((string)substr(
                strrchr($global_from, '@') ?: '',
                1
            ));
            if ($candidate !== '') {
                $domain = $candidate;
            }
        }
        return $local_part . '@' . $domain;
    }

    /**
     * @return string[]
     */
    private function password_criteria_missing(
        string $password,
        int $minimum_length
    ): array {
        return \dbxPasswordPolicy::missing_criteria(
            $password,
            $minimum_length
        );
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
