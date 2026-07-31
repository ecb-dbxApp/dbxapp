<?php

declare(strict_types=1);

namespace dbx\dbxSetup;

use RuntimeException;

/**
 * Reproduzierbare Erstinstallation aus DDs und idempotenten Seeds.
 *
 * DDs definieren die Struktur, lokale DD-Serverbindungen den physischen
 * Speicher und diese Klasse ausschliesslich die notwendigen Ausgangsdaten.
 * Es werden keine SQL-Dumps und keine ausgelieferten Produktiv-DB3 benutzt.
 */
class dbxInstallationService
{
    private object $db;
    private object $dd;

    public function __construct(?object $db = null, ?object $dd = null)
    {
        $this->db = $db ?? dbx()->get_system_obj('dbxDB');
        $this->dd = $dd ?? dbx()->get_system_obj('dbxDD');

        if (!is_object($this->db) || !is_object($this->dd)) {
            throw new RuntimeException('dbxDB/dbxDD stehen fuer die Installation nicht zur Verfuegung.');
        }
    }

    /**
     * Liefert alle installierbaren DDs, optional begrenzt auf Module.
     *
     * @return array<int,array{
     *   module:string,
     *   name:string,
     *   dd:string,
     *   file:string,
     *   declared_server:string,
     *   table:string
     * }>
     */
    public function discoverDDs(array $modules = array()): array
    {
        $moduleFilter = array();
        foreach ($modules as $module) {
            $module = trim((string)$module);
            if ($module !== '') {
                $moduleFilter[strtolower($module)] = true;
            }
        }

        $root = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/');
        $records = array();
        foreach (glob($root . '*/dd/*.dd.php') ?: array() as $file) {
            $normalized = str_replace('\\', '/', $file);
            if (str_contains($normalized, '/_backup/')
                || preg_match('#/dbx/modules/([^/]+)/dd/([^/]+)\.dd\.php$#', $normalized, $match) !== 1
            ) {
                continue;
            }

            $module = (string)$match[1];
            $name = (string)$match[2];
            if ($name === '' || str_starts_with($name, '.')) {
                continue;
            }
            if ($moduleFilter !== array()
                && !isset($moduleFilter[strtolower($module)])
            ) {
                continue;
            }

            $definition = $this->readDDTableDefinition($file);
            $records[$module . '|' . $name] = array(
                'module' => $module,
                'name' => $name,
                'dd' => $module . '|' . $name,
                'file' => $file,
                'declared_server' => trim((string)($definition['server'] ?? '')),
                'table' => trim((string)($definition['table'] ?? '')),
            );
        }

        uksort($records, static function (string $left, string $right): int {
            $leftCore = str_starts_with($left, 'dbx|') ? 0 : 1;
            $rightCore = str_starts_with($right, 'dbx|') ? 0 : 1;
            return $leftCore <=> $rightCore ?: strcasecmp($left, $right);
        });

        return array_values($records);
    }

    /**
     * Liest ausschließlich den Tabellenkopf einer DD in einem isolierten
     * Scope. Installationscode muss dadurch keine Servernamen oder
     * Tabellennamen duplizieren.
     */
    private function readDDTableDefinition(string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return array();
        }

        $definition = (static function (string $ddFile): array {
            $table = array();
            $fields = array();
            $indexes = array();
            $field = array();
            $index = array();
            include $ddFile;
            return is_array($table) ? $table : array();
        })($file);

        return is_array($definition) ? $definition : array();
    }

    /**
     * Bindet alle gefundenen DDs auf einen konfigurierten Server.
     *
     * Die Methode schreibt nur den lokalen, releasegeschuetzten Binding-Teil.
     * Einzelne Bindungen koennen danach weiter unabhaengig angepasst werden.
     */
    public function bindAllToServer(string $server, array $modules = array()): array
    {
        $server = trim($server);
        if ($server === '') {
            throw new RuntimeException('Der Zielserver fuer die DD-Bindungen fehlt.');
        }

        $bindings = array();
        foreach ($this->discoverDDs($modules) as $record) {
            $bindings[$record['dd']] = $server;
        }

        if (!dbx()->patch_local_config('dbx', array(
            'dd_server_bindings' => $bindings,
        ))) {
            throw new RuntimeException('Lokale DD-Serverbindungen konnten nicht gespeichert werden.');
        }

        return $bindings;
    }

    /**
     * Erstellt beziehungsweise ergaenzt die DD-Struktur ohne Force-Rebuild.
     *
     * @return array{ok:bool,total:int,finished:int,results:array,errors:array}
     */
    public function provisionSchema(array $modules = array()): array
    {
        $results = array();
        $errors = array();
        $records = $this->discoverDDs($modules);

        foreach ($records as $record) {
            $binding = $this->db->get_dd_server_binding_info($record['dd']);
            if (empty($binding['valid']) || ($binding['resolved_server'] ?? '') === '') {
                $errors[] = $record['dd'] . ': ungueltige DD-Serverbindung';
                continue;
            }

            $this->dd->sync_dd_to_db(
                $record['module'],
                $record['name'],
                'reset'
            );

            $state = array();
            for ($step = 0; $step < 1000; $step++) {
                $state = $this->dd->sync_dd_to_db(
                    $record['module'],
                    $record['name'],
                    'apply'
                );
                $status = (string)($state['status'] ?? '');
                if (in_array($status, array('finished', 'error', 'cancelled'), true)) {
                    break;
                }
            }

            $results[$record['dd']] = array(
                'server' => (string)$binding['resolved_server'],
                'status' => (string)($state['status'] ?? 'error'),
                'message' => (string)($state['message'] ?? ''),
            );
            if (($state['status'] ?? '') !== 'finished') {
                $errors[] = $record['dd'] . ': '
                    . (string)($state['message'] ?? 'Schema-Synchronisation fehlgeschlagen');
            }
        }

        return array(
            'ok' => $errors === array(),
            'total' => count($records),
            'finished' => count($results) - count($errors),
            'results' => $results,
            'errors' => $errors,
        );
    }

    /**
     * Prüft die mitgelieferten DB3-Tabellen ausschließlich lesend.
     *
     * Der Standard-Installer darf die ausgelieferten Datenbanken weder
     * synchronisieren noch neu anlegen. Fehlende Tabellen werden deshalb
     * gemeldet, statt den Lieferzustand stillschweigend zu verändern.
     *
     * @return array{ok:bool,total:int,verified:int,results:array,errors:array}
     */
    public function verifyBundledSchema(array $modules = array()): array
    {
        if (!method_exists($this->dd, 'get_table_exist')) {
            throw new RuntimeException('Die lesende DD-Strukturprüfung steht nicht zur Verfügung.');
        }

        $records = $this->discoverDDs($modules);
        $results = array();
        $errors = array();
        $verified = 0;
        foreach ($records as $record) {
            $binding = $this->db->get_dd_server_binding_info($record['dd']);
            $server = trim((string)($binding['resolved_server'] ?? ''));
            $table = method_exists($this->db, 'get_dd_table')
                ? trim((string)$this->db->get_dd_table($record['dd']))
                : '';
            if ($table === '') {
                $table = trim((string)($record['table'] ?? ''));
            }
            $valid = !empty($binding['valid'])
                && $server !== ''
                && $table !== ''
                && $this->dd->get_table_exist($server, $table);
            $results[$record['dd']] = array(
                'status' => $valid ? 'verified' : 'missing',
                'server' => $server,
                'table' => $table,
            );
            if ($valid) {
                $verified++;
            } else {
                $errors[] = $record['dd']
                    . ': ausgelieferte DB3-Tabelle fehlt oder ist nicht erreichbar';
            }
        }

        return array(
            'ok' => $errors === array(),
            'total' => count($records),
            'verified' => $verified,
            'results' => $results,
            'errors' => $errors,
        );
    }

    /**
     * Überträgt vorhandene Daten aus den in den DDs deklarierten
     * Quellspeichern auf einen neu konfigurierten SQL-Server.
     *
     * Der Aufruf ist bewusst separat von provisionSchema(): Eine frische
     * Installation erzeugt nur Strukturen und Seeds. Erst die ausdrückliche
     * Auswahl im Installer darf vorhandene lokale Tabellen in das neue Ziel
     * kopieren und dort ersetzen.
     *
     * @return array{ok:bool,total:int,transferred:int,skipped:int,results:array,errors:array}
     */
    public function transferDeclaredDataToServer(
        string $targetServer,
        array $modules = array()
    ): array {
        $targetServer = trim($targetServer);
        if ($targetServer === '') {
            throw new RuntimeException('Der Zielserver für die Datenübertragung fehlt.');
        }
        if (!method_exists($this->dd, 'get_table_exist')
            || !method_exists($this->dd, 'transfer_table')
        ) {
            throw new RuntimeException('Die DD-Datenübertragung steht nicht zur Verfügung.');
        }

        $results = array();
        $errors = array();
        $transferred = 0;
        $skipped = 0;
        $records = $this->discoverDDs($modules);

        foreach ($records as $record) {
            $ddRef = (string)$record['dd'];
            $sourceServer = trim((string)($record['declared_server'] ?? ''));
            $table = trim((string)($record['table'] ?? ''));
            if ($sourceServer === ''
                || $table === ''
                || strcasecmp($sourceServer, $targetServer) === 0
                || !$this->dd->get_table_exist($sourceServer, $table)
            ) {
                $results[$ddRef] = array(
                    'status' => 'skipped',
                    'source' => $sourceServer,
                    'target' => $targetServer,
                    'table' => $table,
                );
                $skipped++;
                continue;
            }

            $this->dd->transfer_table(
                $sourceServer,
                $table,
                $targetServer,
                $table,
                'reset',
                0,
                1
            );
            $state = array();
            for ($step = 0; $step < 100000; $step++) {
                $state = $this->dd->transfer_table(
                    $sourceServer,
                    $table,
                    $targetServer,
                    $table,
                    'step',
                    0,
                    1
                );
                if (in_array(
                    (string)($state['status'] ?? ''),
                    array('finished', 'error', 'cancelled'),
                    true
                )) {
                    break;
                }
            }

            $status = (string)($state['status'] ?? 'error');
            $results[$ddRef] = array(
                'status' => $status,
                'source' => $sourceServer,
                'target' => $targetServer,
                'table' => $table,
                'message' => (string)($state['message'] ?? ''),
            );
            if ($status === 'finished') {
                $transferred++;
            } else {
                $errors[] = $ddRef . ': '
                    . (string)($state['message'] ?? 'Datenübertragung fehlgeschlagen');
            }
        }

        return array(
            'ok' => $errors === array(),
            'total' => count($records),
            'transferred' => $transferred,
            'skipped' => $skipped,
            'results' => $results,
            'errors' => $errors,
        );
    }

    /**
     * Legt die verbindlichen Core-Gruppen an, ohne bestehende Werte zu
     * ueberschreiben.
     */
    public function seedCoreGroups(): array
    {
        $groups = array(
            'guest' => 'Nicht angemeldete Benutzer',
            'member' => 'Bestaetigte Benutzer',
            'admin' => 'Systemadministratoren',
        );
        $result = array('created' => array(), 'existing' => array());

        foreach ($groups as $name => $description) {
            $existing = $this->db->select1(
                'dbx|dbxUser_groups',
                array('name' => $name),
                array('id', 'name'),
                0
            );
            if ((int)($existing['id'] ?? 0) > 0) {
                $result['existing'][] = $name;
                continue;
            }

            $id = $this->db->insert(
                'dbx|dbxUser_groups',
                array(
                    'name' => $name,
                    'description' => $description,
                    'active' => 1,
                ),
                0,
                1,
                1,
                0
            );
            if ((int)$id <= 0) {
                throw new RuntimeException('Systemgruppe konnte nicht angelegt werden: ' . $name);
            }
            $result['created'][] = $name;
        }

        return $result;
    }

    /**
     * Liefert den Zustand des initialen Administrators ohne Datenänderung.
     *
     * @return array{
     *   exists:bool,id:int,email:string,default_password:bool,
     *   password_reset_required:bool,created:bool,reset:bool
     * }
     */
    public function inspectInitialAdmin(): array
    {
        $existing = $this->db->select1(
            'dbx|dbxUser',
            array('uname' => 'admin'),
            array('id', 'uname', 'pass', 'email', 'settings'),
            0
        );
        if ((int)($existing['id'] ?? 0) <= 0) {
            return array(
                'exists' => false,
                'id' => 0,
                'email' => '',
                'default_password' => false,
                'password_reset_required' => false,
                'created' => false,
                'reset' => false,
            );
        }

        $settings = json_decode((string)($existing['settings'] ?? ''), true);
        return array(
            'exists' => true,
            'id' => (int)$existing['id'],
            'email' => (string)($existing['email'] ?? ''),
            'default_password' => password_verify(
                '123456',
                (string)($existing['pass'] ?? '')
            ),
            'password_reset_required' => is_array($settings)
                && !empty($settings['password_reset_required']),
            'created' => false,
            'reset' => false,
        );
    }

    /**
     * Stellt bei der Installation den persönlichen Administratorzugang bereit.
     *
     * Ein vorhandener Administrator bleibt als Datensatz erhalten. Passwort,
     * Admin-Rolle und Aktivstatus werden anhand der Installationsangaben
     * aktualisiert. Der optionale Reset-Schalter bleibt für spätere
     * administrative Passwortwechsel verfügbar.
     */
    public function ensureInitialAdmin(
        bool $createIfMissing,
        string $language,
        string $password,
        bool $passwordResetRequired = false
    ): array {
        if (strlen($password) < 6 || strlen($password) > 128) {
            throw new RuntimeException('Das Admin-Passwort muss zwischen 6 und 128 Zeichen lang sein.');
        }

        $status = $this->inspectInitialAdmin();
        if (!$status['exists'] && !$createIfMissing) {
            return $status;
        }

        if (!$status['exists']) {
            $id = $this->db->insert(
                'dbx|dbxUser',
                array(
                    'uname' => 'admin',
                    'pass' => password_hash($password, PASSWORD_DEFAULT),
                    'email' => '',
                    'roles' => 'admin',
                    'language' => preg_match('/^[a-z]{2,3}$/', $language)
                        ? $language
                        : 'de',
                    'status' => 1,
                    'is_confirm' => 1,
                    'settings' => json_encode(
                        $passwordResetRequired
                            ? array('password_reset_required' => 1)
                            : array('password_changed_at' => date(DATE_ATOM)),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                ),
                0,
                1,
                1,
                0
            );
            if ((int)$id <= 0) {
                throw new RuntimeException('Der initiale Admin-Benutzer konnte nicht angelegt werden.');
            }

            $status = $this->inspectInitialAdmin();
            $status['created'] = true;
            $status['reset'] = true;
            return $status;
        }

        $existing = $this->db->select1(
            'dbx|dbxUser',
            array('uname' => 'admin'),
            array('id', 'settings'),
            0
        );
        $settings = json_decode((string)($existing['settings'] ?? ''), true);
        $settings = is_array($settings) ? $settings : array();
        if ($passwordResetRequired) {
            $settings['password_reset_required'] = 1;
            unset($settings['password_changed_at']);
        } else {
            unset($settings['password_reset_required']);
            $settings['password_changed_at'] = date(DATE_ATOM);
        }

        $updated = $this->db->update(
            'dbx|dbxUser',
            array(
                'pass' => password_hash($password, PASSWORD_DEFAULT),
                'roles' => 'admin',
                'status' => 1,
                'is_confirm' => 1,
                'settings' => json_encode(
                    $settings,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ),
            (int)$status['id'],
            0,
            1,
            1,
            0
        );
        if (!$updated) {
            throw new RuntimeException('Der vorhandene Admin-Benutzer konnte nicht auf den Installationsstandard zurückgesetzt werden.');
        }

        $status = $this->inspectInitialAdmin();
        $status['reset'] = true;
        return $status;
    }

    /**
     * Erstellt den Admin nur bei einer echten Erstinstallation.
     *
     * Ein vorhandener Admin wird weder veraendert noch mit einem neuen
     * Passwort versehen.
     */
    public function createAdmin(
        string $password,
        string $email,
        string $language = 'de'
    ): array {
        $existing = $this->db->select1(
            'dbx|dbxUser',
            array('uname' => 'admin'),
            array('id', 'uname'),
            0
        );
        if ((int)($existing['id'] ?? 0) > 0) {
            return array(
                'created' => false,
                'id' => (int)$existing['id'],
                'reason' => 'admin_exists',
            );
        }

        if (strlen($password) < 12) {
            throw new RuntimeException('Das Admin-Passwort muss mindestens 12 Zeichen lang sein.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Die Admin-E-Mail-Adresse ist ungueltig.');
        }

        $id = $this->db->insert(
            'dbx|dbxUser',
            array(
                'uname' => 'admin',
                'pass' => password_hash($password, PASSWORD_DEFAULT),
                'email' => $email,
                'roles' => 'admin',
                'language' => preg_match('/^[a-z]{2,3}$/', $language)
                    ? $language
                    : 'de',
                'status' => 1,
                'is_confirm' => 1,
                'settings' => '{}',
            ),
            0,
            1,
            1,
            0
        );
        if ((int)$id <= 0) {
            throw new RuntimeException('Der Admin-Benutzer konnte nicht angelegt werden.');
        }

        return array('created' => true, 'id' => (int)$id, 'reason' => '');
    }
}
