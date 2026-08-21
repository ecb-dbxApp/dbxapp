<?php

declare(strict_types=1);

require_once __DIR__ . '/dbxJsonFile.class.php';

/**
 * Gemeinsame Installations- und Update-Engine fuer dbxApp-Komponenten.
 *
 * Sie verarbeitet mehrere ausgewaehlte Pakete als einen vorbereiteten Plan,
 * sichert alle betroffenen Dateien und rollt bei Fehlern den gesamten Plan
 * in umgekehrter Reihenfolge zurueck.
 */
final class dbxPackageManager
{
    private const MAX_PACKAGE_BYTES = 268435456;
    private const MAX_EXTRACTED_BYTES = 536870912;
    private const MAX_FILES = 20000;

    private string $root;
    private string $work;
    private dbxPackageContract $contract;
    private dbxPackageCatalog $catalog;

    public function __construct(string $root = '', ?dbxPackageCatalog $catalog = null)
    {
        $resolved = realpath($root !== '' ? $root : dirname(__DIR__, 2));
        if ($resolved === false) {
            throw new RuntimeException('dbxApp-Projektwurzel wurde nicht gefunden.');
        }
        require_once __DIR__ . '/dbxPackageContract.class.php';
        require_once __DIR__ . '/dbxPackageCatalog.class.php';
        $this->root = rtrim($resolved, '\\/');
        $this->work = $this->root . '/files/update/components';
        $this->contract = new dbxPackageContract();
        $this->catalog = $catalog ?? new dbxPackageCatalog($this->root);
    }

    public static function configured(): self
    {
        require_once __DIR__ . '/dbxPackageContract.class.php';
        require_once __DIR__ . '/dbxPackageCatalog.class.php';
        $cfg = function_exists('dbx') ? dbx()->get_cfg('dbxAdmin') : array();
        $url = is_array($cfg) ? (string)($cfg['marketplace_catalog_url'] ?? '') : '';
        $ttl = is_array($cfg) ? (int)($cfg['marketplace_cache_ttl'] ?? 21600) : 21600;
        $root = function_exists('dbx') ? (string)dbx()->get_base_dir() : '';
        return new self($root, new dbxPackageCatalog($root, $url, $ttl));
    }

    /** @return array<string,array<string,mixed>> */
    public function inventory(bool $with_drift = true, ?array $drift_types = null): array
    {
        $paths = array('dbx.package.json');
        foreach (array('module' => 'dbx/modules', 'design' => 'dbx/design') as $type => $base) {
            foreach (glob($this->root . '/' . $base . '/*/dbx.package.json') ?: array() as $file) {
                $paths[] = ltrim(str_replace('\\', '/', substr($file, strlen($this->root))), '/');
            }
        }
        $out = array();
        foreach ($paths as $relative) {
            $manifest = $this->read_json($this->root . '/' . $relative);
            if ($manifest === array()) {
                continue;
            }
            try {
                $manifest = $this->contract->validate_manifest($manifest, false);
            } catch (Throwable $exception) {
                $out['invalid:' . $relative] = array(
                    'id' => 'invalid:' . $relative,
                    'type' => 'invalid',
                    'name' => basename(dirname($relative)),
                    'version' => '—',
                    'valid' => false,
                    'error' => $exception->getMessage(),
                    'managed' => false,
                    'drift' => array(),
                );
                continue;
            }
            // Ein Manifest darf seinen Installationsort nicht selbst behaupten.
            // Nur der kanonische Pfad macht ein Paket zu einer installierten
            // Komponente; umbenannte/deaktivierte Ordner bleiben außen vor.
            if ($relative !== $this->contract->manifest_path((string)$manifest['type'], (string)$manifest['name'])) {
                continue;
            }
            $receipt = $this->receipt($manifest['id']);
            $audit_type = $drift_types === null || in_array((string)$manifest['type'], $drift_types, true);
            $drift = $with_drift && $audit_type && $receipt !== array() ? $this->detect_drift($receipt) : array();
            $manifest['valid'] = true;
            $manifest['receipt'] = $receipt;
            $manifest['installed_at'] = (string)($receipt['installed_at'] ?? '');
            $manifest['state'] = $receipt !== array() ? 'installed' : (!empty($manifest['managed']) ? 'bundled' : 'local');
            $manifest['drift'] = $drift;
            $out[$manifest['id']] = $manifest;
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    /** Registriert die mit der aktuellen Installation ausgelieferten Pakete. */
    public function adopt_bundled_packages(): array
    {
        $created = array();
        foreach ($this->inventory(false) as $id => $manifest) {
            $existing = $this->receipt($id);
            if (empty($manifest['valid']) || empty($manifest['managed'])
                || (($existing['source'] ?? '') === 'marketplace')) {
                continue;
            }
            // Ein Release-Beleg ist die unveraenderliche Vergleichsbasis fuer
            // die Drift-Pruefung. Ihn bei jedem Seitenaufruf neu zu hashen
            // waere teuer und wuerde lokale Aenderungen zugleich maskieren.
            if (($existing['source'] ?? '') === 'bundled-release'
                && (string)($existing['version'] ?? '') === (string)$manifest['version']) {
                continue;
            }
            $files = $this->current_files($manifest['type'], $manifest['name'], (array)($manifest['package_excludes'] ?? array()));
            $receipt = array(
                'schema' => 1,
                'id' => $id,
                'type' => $manifest['type'],
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'vendor' => $manifest['vendor'],
                'managed' => true,
                'source' => 'bundled-release',
                'installed_at' => gmdate('c'),
                'files' => $files,
                'migrations' => array(),
            );
            $this->write_json($this->receipt_file($id), $receipt);
            $created[] = $id;
        }
        return $created;
    }

    /** @return array<string,mixed> */
    public function status(bool $force_catalog = false, ?array $drift_types = null): array
    {
        $this->adopt_bundled_packages();
        $installed = $this->inventory(true, $drift_types);
        $catalog = $this->catalog->load($force_catalog);
        $available = array();
        foreach ((array)$catalog['packages'] as $id => $package) {
            $current = $installed[$id] ?? null;
            $package['installed'] = is_array($current);
            $package['installed_version'] = is_array($current) ? (string)$current['version'] : '';
            $package['update_available'] = is_array($current)
                && version_compare((string)$package['version'], (string)$current['version'], '>');
            $package['install_available'] = !is_array($current);
            $package['drift'] = is_array($current) ? (array)($current['drift'] ?? array()) : array();
            $package['compatible'] = $this->compatible($package, $installed);
            $entitled = ($package['license'] ?? 'free') !== 'paid'
                || ($package['entitlement']['status'] ?? '') === 'granted';
            $package['entitled'] = $entitled;
            $package['actionable'] = !empty($package['artifact'])
                && !empty($package['compatible'])
                && $entitled
                && empty($package['drift'])
                && (!empty($package['update_available']) || !empty($package['install_available']));
            $available[$id] = $package;
        }
        return array(
            'installed' => $installed,
            'available' => $available,
            'catalog' => $catalog,
            'staged' => $this->read_json($this->work . '/staged.json'),
            'rollback' => $this->read_json($this->work . '/installed.json'),
        );
    }

    /** Netzwerkfreier Kurzstatus fuer Dashboard und Menue. */
    public function local_status(): array
    {
        $installed = $this->inventory(false);
        $catalog = $this->catalog->local();
        $updates = array();
        foreach ((array)$catalog['packages'] as $id => $package) {
            if (isset($installed[$id])
                && version_compare((string)$package['version'], (string)$installed[$id]['version'], '>')) {
                $updates[$id] = $package;
            }
        }
        $staged = $this->read_json($this->work . '/staged.json');
        return array(
            'current_version' => (string)($installed['dbxapp/kernel/dbxapp']['version'] ?? trim((string)@file_get_contents($this->root . '/VERSION'))),
            'available_version' => (string)($catalog['packages']['dbxapp/kernel/dbxapp']['version'] ?? ''),
            'update_available' => $updates !== array(),
            'update_count' => count($updates),
            'checked_at' => (string)($catalog['generated_at'] ?? ''),
            'staged_version' => is_array($staged['packages'] ?? null) ? (string)count($staged['packages']) : '',
            'stop_available' => is_array($staged['packages'] ?? null) && $staged['packages'] !== array(),
            'status_available' => true,
        );
    }

    /**
     * @param array $ids Ausgewaehlte Paketkennungen
     * @param bool $force_catalog Marktplatzkatalog neu laden
     * @return array<string,mixed>
     */
    public function prepare(array $ids, bool $force_catalog = true): array
    {
        return $this->synchronized(function () use ($ids, $force_catalog): array {
            $catalog = $this->catalog->load($force_catalog);
            $installed = $this->inventory();
            $selected = $this->resolve_dependencies($ids, (array)$catalog['packages'], $installed);
            if ($selected === array()) {
                throw new RuntimeException('Es wurde kein installierbares Paket ausgewaehlt.');
            }
            $this->discard_staged(false);
            $transaction = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
            $stage_root = $this->work . '/staging/' . $transaction;
            $this->ensure_directory($stage_root);
            $plans = array();
            try {
                foreach ($selected as $id => $entry) {
                    if (empty($entry['artifact'])) {
                        throw new RuntimeException('Fuer ' . $id . ' steht kein Paketdownload bereit.');
                    }
                    $archive = $stage_root . '/' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $id) . '.zip';
                    $this->download((string)$entry['artifact']['url'], $archive, min(self::MAX_PACKAGE_BYTES, max(1, (int)$entry['artifact']['size'])));
                    $actual = hash_file('sha256', $archive);
                    if (!is_string($actual) || !hash_equals((string)$entry['artifact']['sha256'], strtolower($actual))) {
                        throw new RuntimeException('SHA-256 stimmt nicht: ' . $id);
                    }
                    $target = $stage_root . '/packages/' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $id);
                    $inspected = $this->inspect_archive($archive, $entry, $target);
                    $plans[$id] = array(
                        'catalog' => $entry,
                        'manifest' => $inspected['manifest'],
                        'archive' => $archive,
                        'staging' => $target,
                    );
                }
            } catch (Throwable $exception) {
                $this->remove_tree($stage_root, $this->work);
                throw $exception;
            }
            $state = array(
                'schema' => 1,
                'transaction' => $transaction,
                'prepared_at' => gmdate('c'),
                'stage_root' => $stage_root,
                'packages' => $plans,
            );
            $this->write_json($this->work . '/staged.json', $state);
            return $state;
        });
    }

    /** @return array<string,mixed> */
    public function install_prepared(): array
    {
        return $this->synchronized(function (): array {
            $state = $this->read_json($this->work . '/staged.json');
            if (!is_array($state['packages'] ?? null) || $state['packages'] === array()) {
                throw new RuntimeException('Es ist kein Komponentenplan bereitgestellt.');
            }
            $backup = $this->work . '/backups/' . (string)$state['transaction'];
            $backup_files = $backup . '/files';
            $this->ensure_directory($backup_files);
            $entries = array();
            $receipts_before = array();
            foreach ($state['packages'] as $id => $plan) {
                $manifest = $this->contract->validate_manifest((array)$plan['manifest'], true);
                $this->verify_staging((string)$plan['staging'], $manifest['files']);
                $old_receipt = $this->receipt($id);
                $receipts_before[$id] = $old_receipt;
                $old_files = $old_receipt !== array()
                    ? array_keys((array)($old_receipt['files'] ?? array()))
                    : array_keys($this->current_files($manifest['type'], $manifest['name'], (array)($manifest['package_excludes'] ?? array())));
                $manifest_path = $this->contract->manifest_path($manifest['type'], $manifest['name']);
                foreach (array_unique(array_merge($old_files, array_keys($manifest['files']), array($manifest_path))) as $relative) {
                    if (!$this->contract->path_allowed($manifest['type'], $manifest['name'], (string)$relative)) {
                        continue;
                    }
                    $destination = $this->root . '/' . $relative;
                    $existed = is_file($destination);
                    if ($existed) {
                        $copy = $backup_files . '/' . $relative;
                        $this->ensure_directory(dirname($copy));
                        if (!copy($destination, $copy)) {
                            throw new RuntimeException('Sicherung fehlgeschlagen: ' . $relative);
                        }
                    }
                    $entries[$relative] = array('path' => $relative, 'existed' => $existed);
                }
            }
            $backup_state = array(
                'schema' => 1,
                'transaction' => $state['transaction'],
                'created_at' => gmdate('c'),
                'backup_directory' => $backup,
                'entries' => array_values($entries),
                'receipts_before' => $receipts_before,
                'packages' => array_keys($state['packages']),
                'migrations' => array(),
            );
            $this->write_json($backup . '/backup.json', $backup_state);
            try {
                foreach ($state['packages'] as $id => $plan) {
                    $manifest = $this->contract->validate_manifest((array)$plan['manifest'], true);
                    $old_receipt = $receipts_before[$id];
                    $old_files = $old_receipt !== array()
                        ? array_keys((array)($old_receipt['files'] ?? array()))
                        : array_keys($this->current_files($manifest['type'], $manifest['name'], (array)($manifest['package_excludes'] ?? array())));
                    foreach (array_diff($old_files, array_keys($manifest['files'])) as $relative) {
                        if ($this->contract->path_allowed($manifest['type'], $manifest['name'], (string)$relative)) {
                            $destination = $this->root . '/' . $relative;
                            if (is_file($destination) && !unlink($destination)) {
                                throw new RuntimeException('Veraltete Paketdatei konnte nicht entfernt werden: ' . $relative);
                            }
                        }
                    }
                    foreach ($manifest['files'] as $relative => $hash) {
                        $source = (string)$plan['staging'] . '/' . $relative;
                        $destination = $this->root . '/' . $relative;
                        $this->ensure_directory(dirname($destination));
                        if (!copy($source, $destination)) {
                            throw new RuntimeException('Paketdatei konnte nicht installiert werden: ' . $relative);
                        }
                    }
                    $this->write_json(
                        $this->root . '/' . $this->contract->manifest_path($manifest['type'], $manifest['name']),
                        $manifest
                    );
                    $applied = $this->apply_migrations($manifest, 'up');
                    $backup_state['migrations'][$id] = $applied;
                    $this->write_json($backup . '/backup.json', $backup_state);
                    $receipt = array(
                        'schema' => 1,
                        'id' => $manifest['id'],
                        'type' => $manifest['type'],
                        'name' => $manifest['name'],
                        'version' => $manifest['version'],
                        'vendor' => $manifest['vendor'],
                        'managed' => true,
                        'source' => 'marketplace',
                        'installed_at' => gmdate('c'),
                        'files' => $manifest['files'],
                        'migrations' => $applied,
                    );
                    $this->write_json($this->receipt_file($id), $receipt);
                }
            } catch (Throwable $exception) {
                $this->restore_backup($backup_state);
                throw new RuntimeException('Komponenteninstallation fehlgeschlagen und wurde zurueckgerollt: ' . $exception->getMessage(), 0, $exception);
            }
            $installed = $backup_state + array('installed_at' => gmdate('c'));
            $this->write_json($this->work . '/installed.json', $installed);
            $this->discard_staged(false);
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $this->invalidate_page_cache();
            return $installed;
        });
    }

    /** @return array<string,mixed> */
    public function rollback(): array
    {
        return $this->synchronized(function (): array {
            $state = $this->read_json($this->work . '/installed.json');
            if (!is_dir((string)($state['backup_directory'] ?? '')) || !empty($state['rolled_back_at'])) {
                throw new RuntimeException('Kein Komponenten-Rollback verfuegbar.');
            }
            $this->restore_backup($state);
            $state['rolled_back_at'] = gmdate('c');
            $this->write_json($this->work . '/installed.json', $state);
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $this->invalidate_page_cache();
            return $state;
        });
    }

    /** Verhindert veraltete HTML- und Asset-Versionen nach Update/Rollback. */
    private function invalidate_page_cache(): void
    {
        $file = $this->root . '/dbx/modules/dbxContent/include/dbxContentPageCache.class.php';
        if (!is_file($file)) {
            return;
        }
        require_once $file;
        if (class_exists('\\dbx\\dbxContent\\dbxContentPageCache', false)) {
            \dbx\dbxContent\dbxContentPageCache::invalidate_all();
        }
    }

    /** @return array<string,mixed> */
    public function cancel(): array
    {
        return $this->synchronized(fn(): array => $this->discard_staged(true));
    }

    /**
     * @param string $archive Pfad zum heruntergeladenen Paketarchiv
     * @param array $expected Erwartete Paketmetadaten aus dem Katalog
     * @param string $extract_to Geprueftes Zielverzeichnis fuer die Entpackung
     * @return array{manifest:array<string,mixed>}
     */
    public function inspect_archive(string $archive, array $expected, string $extract_to): array
    {
        if (!class_exists(ZipArchive::class) || !is_file($archive)) {
            throw new RuntimeException('Paketarchiv oder ZIP-Erweiterung fehlt.');
        }
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true || $zip->numFiles < 2 || $zip->numFiles > self::MAX_FILES) {
            throw new RuntimeException('Paketarchiv ist ungueltig oder zu gross.');
        }
        try {
            $manifest_path = $this->contract->manifest_path((string)$expected['type'], (string)$expected['name']);
            $manifest_raw = $zip->getFromName($manifest_path);
            $manifest = is_string($manifest_raw) ? json_decode($manifest_raw, true) : null;
            if (!is_array($manifest)) {
                throw new RuntimeException('Paketmanifest fehlt im Archiv.');
            }
            $manifest = $this->contract->validate_manifest($manifest, true);
            foreach (array('id', 'type', 'name', 'version') as $field) {
                if ((string)$manifest[$field] !== (string)$expected[$field]) {
                    throw new RuntimeException('Katalog und Paket stimmen bei ' . $field . ' nicht ueberein.');
                }
            }
            $allowed = array_fill_keys(array_merge(array_keys($manifest['files']), array($manifest_path)), true);
            $seen = array();
            $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $raw = is_array($stat) ? (string)($stat['name'] ?? '') : '';
                if ($raw === '' || str_ends_with($raw, '/')) {
                    continue;
                }
                $relative = $this->contract->normalize_path($raw);
                $attributes = 0;
                $ops = 0;
                if ($zip->getExternalAttributesIndex($index, $ops, $attributes) && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('Symbolische Links sind in Paketen nicht erlaubt.');
                }
                if (isset($seen[strtolower($relative)]) || !isset($allowed[$relative])) {
                    throw new RuntimeException('Unerwartete oder kollidierende Datei im Paket: ' . $relative);
                }
                $seen[strtolower($relative)] = true;
                $total += max(0, (int)($stat['size'] ?? 0));
                if ($total > self::MAX_EXTRACTED_BYTES) {
                    throw new RuntimeException('Entpacktes Paket ist zu gross.');
                }
                if ($relative !== $manifest_path) {
                    $content = $zip->getFromIndex($index);
                    if (!is_string($content) || !hash_equals($manifest['files'][$relative], hash('sha256', $content))) {
                        throw new RuntimeException('Dateipruefsumme stimmt nicht: ' . $relative);
                    }
                }
            }
            if (count($seen) !== count($allowed)) {
                throw new RuntimeException('Paketarchiv und Datei-Inventar sind nicht identisch.');
            }
            $this->remove_tree($extract_to, $this->work);
            $this->ensure_directory($extract_to);
            foreach (array_keys($manifest['files']) as $relative) {
                $content = $zip->getFromName($relative);
                $target = $extract_to . '/' . $relative;
                $this->ensure_directory(dirname($target));
                if (!is_string($content) || file_put_contents($target, $content, LOCK_EX) === false) {
                    throw new RuntimeException('Paketdatei konnte nicht bereitgestellt werden: ' . $relative);
                }
            }
            return array('manifest' => $manifest);
        } finally {
            $zip->close();
        }
    }

    /** @param array<string,mixed> $package @param array<string,array<string,mixed>> $installed */
    private function compatible(array $package, array $installed): bool
    {
        $kernel = $installed['dbxapp/kernel/dbxapp']['version'] ?? trim((string)@file_get_contents($this->root . '/VERSION'));
        if (!$this->contract->compatible((string)$kernel, (string)($package['requires']['kernel'] ?? '*'))
            || !$this->contract->compatible(PHP_VERSION, (string)($package['requires']['php'] ?? '>=8.2'))) {
            return false;
        }
        foreach ((array)($package['requires']['extensions'] ?? array()) as $extension) {
            if (!extension_loaded((string)$extension)) {
                return false;
            }
        }
        foreach ((array)($package['requires']['packages'] ?? array()) as $id => $constraint) {
            if (!isset($installed[$id]) || !$this->contract->compatible((string)$installed[$id]['version'], (string)$constraint)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<int,string> $ids @param array<string,array<string,mixed>> $catalog @param array<string,array<string,mixed>> $installed @return array<string,array<string,mixed>> */
    private function resolve_dependencies(array $ids, array $catalog, array $installed): array
    {
        $resolved = array();
        $visiting = array();
        $visit = function (string $id) use (&$visit, &$resolved, &$visiting, $catalog, $installed): void {
            if (isset($resolved[$id])) {
                return;
            }
            if (isset($visiting[$id])) {
                throw new RuntimeException('Zyklische Paketabhaengigkeit: ' . $id);
            }
            if (!isset($catalog[$id])) {
                throw new RuntimeException('Paket ist nicht im geprueften Katalog: ' . $id);
            }
            $visiting[$id] = true;
            $entry = $catalog[$id];
            if (($entry['license'] ?? 'free') === 'paid'
                && ($entry['entitlement']['status'] ?? '') !== 'granted') {
                throw new RuntimeException('Fuer dieses Paket fehlt eine gueltige Kaufberechtigung: ' . $id);
            }
            foreach ((array)($entry['requires']['packages'] ?? array()) as $dependency => $constraint) {
                if (isset($installed[$dependency]) && $this->contract->compatible((string)$installed[$dependency]['version'], (string)$constraint)) {
                    continue;
                }
                $visit((string)$dependency);
                if (!$this->contract->compatible((string)$catalog[$dependency]['version'], (string)$constraint)) {
                    throw new RuntimeException('Abhaengigkeit ist nicht kompatibel: ' . $dependency);
                }
            }
            unset($visiting[$id]);
            $current = $installed[$id] ?? null;
            if (is_array($current) && !version_compare((string)$entry['version'], (string)$current['version'], '>')) {
                return;
            }
            if (!$this->compatible($entry, $installed + $resolved)) {
                throw new RuntimeException('Paket ist mit dieser Installation nicht kompatibel: ' . $id);
            }
            if (!empty($current['drift'])) {
                throw new RuntimeException('Lokale Aenderungen blockieren das Update: ' . $id);
            }
            $resolved[$id] = $entry;
        };
        foreach (array_values(array_unique(array_filter(array_map('strval', $ids)))) as $id) {
            $visit($id);
        }
        return $resolved;
    }

    /** @return array<int,string> */
    private function apply_migrations(array $manifest, string $direction): array
    {
        $migrations = is_array($manifest['migrations'] ?? null) ? $manifest['migrations'] : array();
        if ($direction === 'down') {
            $migrations = array_reverse($migrations);
        }
        $applied = array();
        foreach ($migrations as $migration) {
            if (!is_array($migration)) {
                throw new RuntimeException('Ungueltige Paketmigration.');
            }
            $id = trim((string)($migration['id'] ?? ''));
            $file = $this->contract->normalize_path((string)($migration['file'] ?? ''));
            $class = trim((string)($migration['class'] ?? ''));
            if (!preg_match('/^[A-Za-z_\\][A-Za-z0-9_\\]*$/', $class)
                || !$this->contract->path_allowed($manifest['type'], $manifest['name'], $file)
                || !is_file($this->root . '/' . $file)) {
                throw new RuntimeException('Ungueltige Paketmigration: ' . $id);
            }
            require_once __DIR__ . '/dbxPackageMigrationInterface.class.php';
            require_once $this->root . '/' . $file;
            $object = class_exists($class) ? new $class() : null;
            if (!$object instanceof dbxPackageMigrationInterface) {
                throw new RuntimeException('Migration implementiert den dbxApp-Vertrag nicht: ' . $id);
            }
            $context = array('root' => $this->root, 'package' => $manifest, 'migration' => $migration);
            $direction === 'down' ? $object->down($context) : $object->up($context);
            $applied[] = $id;
        }
        return $applied;
    }

    private function restore_backup(array $state): void
    {
        $backup = (string)($state['backup_directory'] ?? '');
        if (!$this->inside($backup, $this->work)) {
            throw new RuntimeException('Komponentenbackup ist ungueltig.');
        }
        foreach (array_reverse((array)($state['packages'] ?? array())) as $id) {
            $receipt = $this->receipt((string)$id);
            if ($receipt !== array() && !empty($state['migrations'][$id])) {
                $manifest_file = $this->root . '/' . $this->contract->manifest_path((string)$receipt['type'], (string)$receipt['name']);
                $manifest = $this->read_json($manifest_file);
                if ($manifest !== array()) {
                    $this->apply_migrations($manifest, 'down');
                }
            }
        }
        foreach (array_reverse((array)($state['entries'] ?? array())) as $entry) {
            $relative = (string)($entry['path'] ?? '');
            $destination = $this->root . '/' . $relative;
            if (!empty($entry['existed'])) {
                $source = $backup . '/files/' . $relative;
                $this->ensure_directory(dirname($destination));
                if (!is_file($source) || !copy($source, $destination)) {
                    throw new RuntimeException('Backup konnte nicht wiederhergestellt werden: ' . $relative);
                }
            } elseif (is_file($destination) && !unlink($destination)) {
                throw new RuntimeException('Neue Paketdatei konnte nicht entfernt werden: ' . $relative);
            }
        }
        foreach ((array)($state['receipts_before'] ?? array()) as $id => $receipt) {
            $file = $this->receipt_file((string)$id);
            if (is_array($receipt) && $receipt !== array()) {
                $this->write_json($file, $receipt);
            } else {
                @unlink($file);
            }
        }
    }

    /** @return array<string,string> */
    private function current_files(string $type, string $name, array $excludes = array()): array
    {
        $base = match ($type) {
            'module' => $this->root . '/dbx/modules/' . $name,
            'design' => $this->root . '/dbx/design/' . $name,
            'kernel' => '',
            default => '',
        };
        $files = array();
        if ($type === 'kernel') {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root . '/dbx', FilesystemIterator::SKIP_DOTS));
            foreach (array('VERSION', 'index.php', 'dbx.package.json') as $root_file) {
                if (is_file($this->root . '/' . $root_file)) {
                    $files[$root_file] = strtolower((string)hash_file('sha256', $this->root . '/' . $root_file));
                }
            }
        } elseif (is_dir($base)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        } else {
            return array();
        }
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($this->root))), '/');
            if ($relative === $this->contract->manifest_path($type, $name)
                || in_array($relative, $excludes, true)
                || !$this->contract->path_allowed($type, $name, $relative)) {
                continue;
            }
            $files[$relative] = strtolower((string)hash_file('sha256', $file->getPathname()));
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    /** @return array<int,array<string,string>> */
    private function detect_drift(array $receipt): array
    {
        $drift = array();
        foreach ((array)($receipt['files'] ?? array()) as $relative => $expected) {
            $file = $this->root . '/' . $relative;
            $actual = is_file($file) ? hash_file('sha256', $file) : false;
            if (!is_string($actual)) {
                $drift[] = array('path' => (string)$relative, 'state' => 'missing');
            } elseif (!hash_equals((string)$expected, strtolower($actual))) {
                $drift[] = array('path' => (string)$relative, 'state' => 'modified');
            }
        }
        return $drift;
    }

    private function verify_staging(string $stage, array $files): void
    {
        if (!$this->inside($stage, $this->work)) {
            throw new RuntimeException('Komponenten-Stagingbereich ist ungueltig.');
        }
        foreach ($files as $relative => $expected) {
            $actual = is_file($stage . '/' . $relative) ? hash_file('sha256', $stage . '/' . $relative) : false;
            if (!is_string($actual) || !hash_equals((string)$expected, strtolower($actual))) {
                throw new RuntimeException('Staging-Datei wurde veraendert: ' . $relative);
            }
        }
    }

    private function download(string $url, string $target, int $expected_maximum): void
    {
        if (!$this->contract->trusted_artifact_source($url) || !extension_loaded('curl')) {
            throw new RuntimeException('Paketdownload verlaesst die dbxApp-Vertrauensgrenze.');
        }
        $github_source = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: '')) === 'github.com';
        $this->ensure_directory(dirname($target));
        $stream = fopen($target, 'wb');
        if (!is_resource($stream)) {
            throw new RuntimeException('Paketdownload konnte nicht angelegt werden.');
        }
        $written = 0;
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FAILONERROR => false,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use ($stream, &$written, $expected_maximum): int {
                $length = strlen($chunk);
                if ($written + $length > $expected_maximum || $written + $length > self::MAX_PACKAGE_BYTES) {
                    return 0;
                }
                $result = fwrite($stream, $chunk);
                if ($result !== $length) {
                    return 0;
                }
                $written += $length;
                return $length;
            },
        ));
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $effective_url = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($stream);
        if (!$this->contract->trusted_effective_source($effective_url, $github_source)
            || $ok !== true || $status < 200 || $status >= 300 || $written < 1) {
            @unlink($target);
            throw new RuntimeException('Paketdownload fehlgeschlagen' . ($error !== '' ? ': ' . $error : '.'));
        }
    }

    private function receipt(string $id): array
    {
        return $this->read_json($this->receipt_file($id));
    }

    private function receipt_file(string $id): string
    {
        return $this->root . '/files/sys/packages/installed/' . hash('sha256', strtolower($id)) . '.json';
    }

    private function discard_staged(bool $required): array
    {
        $file = $this->work . '/staged.json';
        $state = $this->read_json($file);
        if ($state === array()) {
            if ($required) {
                throw new RuntimeException('Kein Komponentenplan zum Stoppen vorhanden.');
            }
            return array('packages' => array());
        }
        $stage = (string)($state['stage_root'] ?? '');
        if ($stage !== '') {
            $this->remove_tree($stage, $this->work);
        }
        @unlink($file);
        return $state;
    }

    private function synchronized(callable $operation): mixed
    {
        $this->ensure_directory($this->work);
        $handle = fopen($this->work . '/update.lock', 'c+');
        if (!is_resource($handle) || !flock($handle, LOCK_EX)) {
            throw new RuntimeException('Komponenten-Updatesperre konnte nicht gesetzt werden.');
        }
        try {
            return $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function inside(string $path, string $root): bool
    {
        $path = str_replace('\\', '/', rtrim($path, '\\/'));
        $root = str_replace('\\', '/', rtrim($root, '\\/'));
        return $path !== '' && $root !== '' && ($path === $root || str_starts_with($path . '/', $root . '/'));
    }

    private function ensure_directory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Verzeichnis konnte nicht angelegt werden: ' . $directory);
        }
    }

    private function remove_tree(string $directory, string $allowed_root): void
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }
        if (!$this->inside($directory, $allowed_root) || rtrim($directory, '\\/') === rtrim($allowed_root, '\\/')) {
            throw new RuntimeException('Unsicheres Loeschziel wurde abgewiesen.');
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                if (!unlink($item->getPathname())) {
                    throw new RuntimeException('Staging-Datei konnte nicht entfernt werden.');
                }
            } elseif (!rmdir($item->getPathname())) {
                throw new RuntimeException('Staging-Verzeichnis konnte nicht entfernt werden.');
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('Staging-Verzeichnis konnte nicht entfernt werden.');
        }
    }

    /** @return array<string,mixed> */
    private function read_json(string $file): array
    {
        return dbxJsonFile::read_array($file);
    }

    private function write_json(string $file, array $data): void
    {
        $this->ensure_directory(dirname($file));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (!is_string($json) || file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Paketstatus konnte nicht sicher gespeichert werden.');
        }
    }
}
