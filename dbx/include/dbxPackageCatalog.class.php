<?php

declare(strict_types=1);

require_once __DIR__ . '/dbxJsonFile.class.php';

/** Signierter, rollback-geschuetzter Marktplatzkatalog. */
final class dbxPackageCatalog
{
    private string $root;
    private string $catalog_url;
    private int $cache_ttl;
    private dbxPackageContract $contract;

    public function __construct(string $root = '', string $catalog_url = '', int $cache_ttl = 21600)
    {
        $resolved = realpath($root !== '' ? $root : dirname(__DIR__, 2));
        if ($resolved === false) {
            throw new RuntimeException('dbxApp-Projektwurzel wurde nicht gefunden.');
        }
        require_once __DIR__ . '/dbxPackageContract.class.php';
        $this->root = rtrim($resolved, '\\/');
        $this->catalog_url = trim($catalog_url);
        $this->cache_ttl = max(300, $cache_ttl);
        $this->contract = new dbxPackageContract();
    }

    /** @return array<string,mixed> */
    public function load(bool $force = false): array
    {
        $cache = $this->root . '/files/sys/marketplace/catalog.json';
        if (!$force && is_file($cache) && filemtime($cache) >= time() - $this->cache_ttl) {
            return $this->validate(dbxJsonFile::read_array($cache), true);
        }
        if ($this->catalog_url !== '') {
            try {
                $catalog = $this->validate($this->fetch($this->catalog_url), true);
                $this->guard_sequence($catalog);
                $this->write_json($cache, $catalog);
                return $catalog;
            } catch (Throwable $exception) {
                if ($force) {
                    throw $exception;
                }
                // Der eingebettete Katalog ist read-only und erlaubt auch bei
                // einem Marktplatzausfall eine stabile Bestandsanzeige.
            }
        }
        return $this->validate(dbxJsonFile::read_array($this->root . '/dbx/marketplace/catalog.json'), false);
    }

    /** Liefert ausschliesslich lokalen Cache oder eingebetteten Katalog. */
    public function local(): array
    {
        $cache = $this->root . '/files/sys/marketplace/catalog.json';
        if (is_file($cache)) {
            try {
                return $this->validate(dbxJsonFile::read_array($cache), true);
            } catch (Throwable) {
                // Ein ungueltiger Cache wird nie verwendet; der im Kernel
                // enthaltene Katalog bleibt als sichere Offline-Anzeige.
            }
        }
        return $this->validate(dbxJsonFile::read_array($this->root . '/dbx/marketplace/catalog.json'), false);
    }

    /** @return array<string,mixed> */
    public function validate(array $catalog, bool $require_signature = true): array
    {
        if ((int)($catalog['schema'] ?? 0) !== 1 || (string)($catalog['channel'] ?? '') !== 'stable') {
            throw new RuntimeException('Der Marktplatzkatalog ist ungueltig.');
        }
        if ((int)($catalog['sequence'] ?? 0) < 1) {
            throw new RuntimeException('Dem Marktplatzkatalog fehlt die Sequenznummer.');
        }
        $expires = strtotime((string)($catalog['expires_at'] ?? '')) ?: 0;
        if ($expires <= time()) {
            throw new RuntimeException('Der Marktplatzkatalog ist abgelaufen.');
        }
        if ($require_signature) {
            $this->contract->verify_signed_document($catalog, $this->trusted_keys());
        }
        $packages = is_array($catalog['packages'] ?? null) ? $catalog['packages'] : array();
        $normalized = array();
        foreach ($packages as $package) {
            if (!is_array($package)) {
                throw new RuntimeException('Ungueltiger Katalogeintrag.');
            }
            $manifest = $this->contract->validate_manifest($package, false);
            $artifact = is_array($package['artifact'] ?? null) ? $package['artifact'] : array();
            if ($artifact !== array()) {
                $hash = strtolower(trim((string)($artifact['sha256'] ?? '')));
                $url = trim((string)($artifact['url'] ?? ''));
                if (!preg_match('/^[a-f0-9]{64}$/', $hash) || !$this->contract->trusted_artifact_source($url)) {
                    throw new RuntimeException('Ungueltiges oder nicht vertrauenswuerdiges Paketartefakt.');
                }
                $manifest['artifact'] = array(
                    'url' => $url,
                    'sha256' => $hash,
                    'size' => max(1, (int)($artifact['size'] ?? 0)),
                );
            }
            $security = is_array($package['security'] ?? null) ? $package['security'] : array();
            if ($artifact !== array() && ($security['status'] ?? '') !== 'approved') {
                throw new RuntimeException('Paketartefakt besitzt keine Sicherheitsfreigabe.');
            }
            $manifest['security'] = array(
                'status' => (string)($security['status'] ?? ''),
                'reviewed_at' => (string)($security['reviewed_at'] ?? ''),
                'publisher' => (string)($security['publisher'] ?? ''),
            );
            $entitlement = is_array($package['entitlement'] ?? null) ? $package['entitlement'] : array();
            $manifest['entitlement'] = array('status' => (string)($entitlement['status'] ?? ''));
            if ($manifest['license'] === 'paid' && isset($package['purchase_url'])) {
                $purchase_url = trim((string)$package['purchase_url']);
                if (!$this->trusted_market_url($purchase_url)) {
                    throw new RuntimeException('Nicht vertrauenswuerdige Kaufadresse im Marktplatzkatalog.');
                }
                $manifest['purchase_url'] = $purchase_url;
            }
            if (isset($normalized[$manifest['id']])) {
                throw new RuntimeException('Doppelte Paket-ID im Marktplatzkatalog.');
            }
            $normalized[$manifest['id']] = $manifest;
        }
        $catalog['packages'] = $normalized;
        return $catalog;
    }

    /** @return array<string,string> */
    public function trusted_keys(): array
    {
        $trust = dbxJsonFile::read_array($this->root . '/dbx/marketplace/trust.json');
        $keys = array();
        foreach ((array)($trust['keys'] ?? array()) as $id => $entry) {
            $file = is_array($entry) ? (string)($entry['file'] ?? '') : '';
            if (!preg_match('/^[A-Za-z0-9._-]+\.pem$/', $file)) {
                continue;
            }
            $path = $this->root . '/dbx/marketplace/keys/' . $file;
            if (is_file($path)) {
                $keys[(string)$id] = (string)file_get_contents($path);
            }
        }
        return $keys;
    }

    private function guard_sequence(array $catalog): void
    {
        $file = $this->root . '/files/sys/marketplace/state.json';
        $state = dbxJsonFile::read_array($file);
        $previous = (int)($state['sequence'] ?? 0);
        $current = (int)$catalog['sequence'];
        if ($current < $previous) {
            throw new RuntimeException('Ein aelterer Marktplatzkatalog wurde abgewiesen.');
        }
        $this->write_json($file, array('sequence' => $current, 'accepted_at' => gmdate('c')));
    }

    /** @return array<string,mixed> */
    private function fetch(string $url): array
    {
        if (!$this->contract->trusted_catalog_source($url) || !extension_loaded('curl')) {
            throw new RuntimeException('Der Marktplatzkatalog besitzt keine sichere HTTPS-Quelle.');
        }
        $github_source = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: '')) === 'github.com';
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'dbxApp-Marketplace/' . trim((string)@file_get_contents($this->root . '/VERSION')),
        ));
        $content = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $effective_url = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($curl);
        curl_close($curl);
        if (!$this->contract->trusted_effective_source($effective_url, $github_source)
            || !is_string($content) || strlen($content) > 4 * 1024 * 1024
            || $status < 200 || $status >= 300) {
            throw new RuntimeException('Marktplatzkatalog konnte nicht geladen werden' . ($error !== '' ? ': ' . $error : '.'));
        }
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Marktplatzkatalog ist kein gueltiges JSON.');
        }
        return $decoded;
    }

    private function trusted_market_url(string $url): bool
    {
        $parts = parse_url($url);
        return strtolower((string)($parts['scheme'] ?? '')) === 'https'
            && strtolower((string)($parts['host'] ?? '')) === 'market.dbxapp.de';
    }

    private function write_json(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Marktplatzstatus konnte nicht angelegt werden.');
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (!is_string($json) || file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Marktplatzstatus konnte nicht sicher gespeichert werden.');
        }
    }
}
