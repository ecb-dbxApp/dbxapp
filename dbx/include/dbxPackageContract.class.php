<?php

declare(strict_types=1);

/**
 * Verbindlicher Vertrag fuer Kernel-, Modul- und Designpakete.
 *
 * Die Klasse liegt absichtlich im Kernel. Paketquellen koennen damit weder
 * ihre eigene Pruefung ersetzen noch die erlaubte Installationsgrenze
 * erweitern.
 */
final class dbxPackageContract
{
    public const SCHEMA = 1;
    public const TYPES = array('kernel', 'module', 'design');

    /** @return array<string,mixed> */
    public function validate_manifest(array $manifest, bool $require_files = false): array
    {
        if ((int)($manifest['schema'] ?? 0) !== self::SCHEMA) {
            throw new RuntimeException('Nicht unterstuetzter dbxApp-Paketvertrag.');
        }
        $type = strtolower(trim((string)($manifest['type'] ?? '')));
        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException('Ungueltiger Pakettyp.');
        }
        $name = trim((string)($manifest['name'] ?? ''));
        if (!$this->valid_name($name, $type)) {
            throw new RuntimeException('Ungueltiger technischer Paketname.');
        }
        $id = trim((string)($manifest['id'] ?? ''));
        if (!preg_match('#^[a-z0-9][a-z0-9._-]{1,62}/(?:kernel|module|design)/[A-Za-z0-9][A-Za-z0-9_-]{1,62}$#', $id)) {
            throw new RuntimeException('Ungueltige dauerhafte Paket-ID.');
        }
        if (!str_ends_with(strtolower($id), '/' . strtolower($type) . '/' . strtolower($name))) {
            throw new RuntimeException('Paket-ID, Pakettyp und technischer Name stimmen nicht ueberein.');
        }
        $version = trim((string)($manifest['version'] ?? ''));
        if (!preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
            throw new RuntimeException('Ungueltige Paketversion.');
        }
        $vendor = is_array($manifest['vendor'] ?? null) ? $manifest['vendor'] : array();
        $vendor_id = strtolower(trim((string)($vendor['id'] ?? '')));
        $vendor_name = trim((string)($vendor['name'] ?? ''));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,62}$/', $vendor_id) || $vendor_name === '') {
            throw new RuntimeException('Herstellerangaben im Paket sind ungueltig.');
        }
        if (!str_starts_with(strtolower($id), $vendor_id . '/')) {
            throw new RuntimeException('Paket-ID und Hersteller-ID stimmen nicht ueberein.');
        }

        $requires = is_array($manifest['requires'] ?? null) ? $manifest['requires'] : array();
        $kernel = trim((string)($requires['kernel'] ?? ''));
        if ($type !== 'kernel' && $kernel === '') {
            throw new RuntimeException('Die Kernel-Kompatibilitaet fehlt.');
        }
        if ($kernel !== '' && !$this->valid_constraint($kernel)) {
            throw new RuntimeException('Die Kernel-Kompatibilitaet ist ungueltig.');
        }
        $php = trim((string)($requires['php'] ?? '>=8.2'));
        if (!$this->valid_constraint($php)) {
            throw new RuntimeException('Die PHP-Kompatibilitaet ist ungueltig.');
        }

        $dependencies = is_array($requires['packages'] ?? null) ? $requires['packages'] : array();
        $normalized_dependencies = array();
        foreach ($dependencies as $dependency_id => $constraint) {
            if (is_int($dependency_id) && is_array($constraint)) {
                $dependency_id = (string)($constraint['id'] ?? '');
                $constraint = (string)($constraint['version'] ?? '');
            }
            $dependency_id = trim((string)$dependency_id);
            $constraint = trim((string)$constraint);
            if (!$this->valid_package_id($dependency_id) || !$this->valid_constraint($constraint)) {
                throw new RuntimeException('Eine Paketabhaengigkeit ist ungueltig.');
            }
            $normalized_dependencies[$dependency_id] = $constraint;
        }

        $permissions = is_array($manifest['permissions'] ?? null) ? $manifest['permissions'] : array();
        $permissions = array_values(array_unique(array_map(static function ($permission): string {
            $permission = strtolower(trim((string)$permission));
            if ($permission !== '' && !preg_match('/^[a-z][a-z0-9._-]{1,63}$/', $permission)) {
                throw new RuntimeException('Eine Paketberechtigung ist ungueltig.');
            }
            return $permission;
        }, $permissions)));
        $permissions = array_values(array_filter($permissions, static fn(string $permission): bool => $permission !== ''));

        $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : array();
        if ($require_files && $files === array()) {
            throw new RuntimeException('Das Paket besitzt kein Datei-Inventar.');
        }
        $normalized_files = array();
        foreach ($files as $relative => $hash) {
            $relative = $this->normalize_path((string)$relative);
            $hash = strtolower(trim((string)$hash));
            if (!$this->path_allowed($type, $name, $relative)) {
                throw new RuntimeException('Paketdatei verlaesst ihren Zielbereich: ' . $relative);
            }
            if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
                throw new RuntimeException('Ungueltige Dateipruefsumme: ' . $relative);
            }
            $normalized_files[$relative] = $hash;
        }
        ksort($normalized_files, SORT_STRING);

        $manifest['type'] = $type;
        $manifest['name'] = $name;
        $manifest['id'] = $id;
        $manifest['version'] = $version;
        $manifest['vendor'] = array('id' => $vendor_id, 'name' => $vendor_name);
        $manifest['requires'] = array(
            'kernel' => $kernel,
            'php' => $php,
            'extensions' => array_values(array_unique(array_map('strtolower', array_map('strval', (array)($requires['extensions'] ?? array()))))),
            'packages' => $normalized_dependencies,
        );
        $manifest['permissions'] = $permissions;
        $manifest['files'] = $normalized_files;
        $manifest['managed'] = !empty($manifest['managed']);
        $manifest['license'] = strtolower(trim((string)($manifest['license'] ?? 'free')));
        if (!in_array($manifest['license'], array('free', 'paid', 'private'), true)) {
            throw new RuntimeException('Ungueltiges Lizenzmodell.');
        }
        $icon = strtolower(trim((string)($manifest['icon'] ?? 'bi-box-seam')));
        if (!preg_match('/^bi-[a-z0-9-]+$/', $icon)) {
            throw new RuntimeException('Ungueltiges Paket-Icon.');
        }
        $manifest['icon'] = $icon;
        $image = trim((string)($manifest['image'] ?? ''));
        if ($image !== '') {
            $image = $this->normalize_path($image);
            $image_extension = strtolower(pathinfo($image, PATHINFO_EXTENSION));
            if (!$this->path_allowed($type, $name, $image)
                || !in_array($image_extension, array('svg', 'png', 'webp', 'jpg', 'jpeg', 'gif'), true)) {
                throw new RuntimeException('Ungueltiges Paketbild.');
            }
        }
        $manifest['image'] = $image;
        $excludes = array();
        foreach ((array)($manifest['package_excludes'] ?? array()) as $excluded) {
            $excluded = $this->normalize_path((string)$excluded);
            if (!$this->path_allowed($type, $name, $excluded)
                || $excluded === $this->manifest_path($type, $name)
                || $excluded === $image) {
                throw new RuntimeException('Ungueltiger Paketausschluss: ' . $excluded);
            }
            $excludes[] = $excluded;
        }
        $manifest['package_excludes'] = array_values(array_unique($excludes));
        $descriptions = is_array($manifest['descriptions'] ?? null) ? $manifest['descriptions'] : array();
        $manifest['descriptions'] = array();
        foreach (array('de', 'en', 'es') as $language) {
            $text = trim((string)($descriptions[$language] ?? ''));
            if ($text !== '' && mb_strlen($text) > 600) {
                throw new RuntimeException('Paketbeschreibung ist zu lang.');
            }
            $manifest['descriptions'][$language] = $text;
        }
        return $manifest;
    }

    public function manifest_path(string $type, string $name): string
    {
        return match ($type) {
            'kernel' => 'dbx.package.json',
            'module' => 'dbx/modules/' . $name . '/dbx.package.json',
            'design' => 'dbx/design/' . $name . '/dbx.package.json',
            default => throw new InvalidArgumentException('Ungueltiger Pakettyp.'),
        };
    }

    public function path_allowed(string $type, string $name, string $relative): bool
    {
        $relative = $this->normalize_path($relative);
        if ($type === 'module') {
            return str_starts_with($relative, 'dbx/modules/' . $name . '/')
                && !($name === 'dbxMenu' && str_starts_with($relative, 'dbx/modules/dbxMenu/tpl/htm/'))
                && !str_ends_with(strtolower($relative), '/config.local.php')
                && !preg_match('#/(?:tests?|tools?)/#i', '/' . $relative)
                && strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== 'md'
                && !$this->runtime_path($relative);
        }
        if ($type === 'design') {
            if (!str_starts_with($relative, 'dbx/design/' . $name . '/')) {
                return false;
            }
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            return in_array($extension, array(
                'htm', 'html', 'css', 'js', 'json', 'txt', 'md',
                'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico',
                'woff', 'woff2', 'ttf', 'otf',
            ), true);
        }
        if ($type !== 'kernel') {
            return false;
        }
        if (preg_match('#/(?:tests?|tools?)/#i', '/' . $relative)
            || strtolower(pathinfo($relative, PATHINFO_EXTENSION)) === 'md') {
            return false;
        }
        if ($relative === 'VERSION' || $relative === 'dbx.package.json' || $relative === 'index.php') {
            return true;
        }
        return str_starts_with($relative, 'dbx/include/')
            || str_starts_with($relative, 'dbx/js/')
            || str_starts_with($relative, 'dbx/css/')
            || str_starts_with($relative, 'dbx/img/')
            || str_starts_with($relative, 'dbx/add_ons/')
            || str_starts_with($relative, 'dbx/vendor/')
            || str_starts_with($relative, 'dbx/marketplace/');
    }

    public function normalize_path(string $path): string
    {
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new RuntimeException('Unsicherer Paketpfad.');
        }
        $path = trim($path, '/ ');
        if ($path === '' || preg_match('/^[A-Za-z]:/', $path)) {
            throw new RuntimeException('Leerer oder absoluter Paketpfad.');
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new RuntimeException('Pfad-Traversal im Paket.');
            }
        }
        return $path;
    }

    /** @param array<string,string> $keys */
    public function verify_signed_document(array $document, array $keys): void
    {
        $signature = is_array($document['signature'] ?? null) ? $document['signature'] : array();
        $key_id = trim((string)($signature['key_id'] ?? ''));
        $algorithm = strtolower(trim((string)($signature['algorithm'] ?? '')));
        $encoded = trim((string)($signature['value'] ?? ''));
        if ($key_id === '' || $algorithm !== 'rsa-sha256' || $encoded === '' || !isset($keys[$key_id])) {
            throw new RuntimeException('Katalogsignatur oder vertrauenswuerdiger Schluessel fehlt.');
        }
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('Fuer die Katalogsignatur fehlt OpenSSL.');
        }
        $binary = base64_decode($encoded, true);
        if (!is_string($binary)) {
            throw new RuntimeException('Katalogsignatur ist ungueltig kodiert.');
        }
        $payload = $document;
        unset($payload['signature']);
        $result = openssl_verify($this->canonical_json($payload), $binary, $keys[$key_id], OPENSSL_ALGO_SHA256);
        if ($result !== 1) {
            throw new RuntimeException('Katalogsignatur konnte nicht bestaetigt werden.');
        }
    }

    public function canonical_json(mixed $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) {
                return $item;
            }
            if (!array_is_list($item)) {
                ksort($item, SORT_STRING);
            }
            foreach ($item as $key => $nested) {
                $item[$key] = $normalize($nested);
            }
            return $item;
        };
        $json = json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if (!is_string($json)) {
            throw new RuntimeException('Dokument konnte nicht kanonisiert werden.');
        }
        return $json;
    }

    public function compatible(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }
        foreach (preg_split('/\s+/', $constraint) ?: array() as $rule) {
            if ($rule === '') {
                continue;
            }
            if (!preg_match('/^(>=|<=|>|<|=|\^|~)?(\d+\.\d+\.\d+)$/', $rule, $match)) {
                return false;
            }
            $operator = $match[1] !== '' ? $match[1] : '=';
            $required = $match[2];
            if ($operator === '^') {
                $major = (int)explode('.', $required)[0];
                if (!version_compare($version, $required, '>=') || !version_compare($version, ($major + 1) . '.0.0', '<')) {
                    return false;
                }
                continue;
            }
            if ($operator === '~') {
                [$major, $minor] = array_map('intval', array_slice(explode('.', $required), 0, 2));
                if (!version_compare($version, $required, '>=') || !version_compare($version, $major . '.' . ($minor + 1) . '.0', '<')) {
                    return false;
                }
                continue;
            }
            if (!version_compare($version, $required, $operator === '=' ? '==' : $operator)) {
                return false;
            }
        }
        return true;
    }

    private function valid_constraint(string $constraint): bool
    {
        return $constraint === '*' || preg_match('/^(?:(?:>=|<=|>|<|=|\^|~)?\d+\.\d+\.\d+)(?:\s+(?:(?:>=|<=|>|<|=|\^|~)?\d+\.\d+\.\d+))*$/', $constraint) === 1;
    }

    private function valid_package_id(string $id): bool
    {
        return preg_match('#^[a-z0-9][a-z0-9._-]{1,62}/(?:kernel|module|design)/[A-Za-z0-9][A-Za-z0-9_-]{1,62}$#', $id) === 1;
    }

    private function valid_name(string $name, string $type): bool
    {
        if ($type === 'kernel') {
            return $name === 'dbxapp';
        }
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,62}$/', $name) === 1;
    }

    private function runtime_path(string $relative): bool
    {
        return preg_match('#/(?:db|cache|tmp|temp|work|backup|backups|uploads)/#i', '/' . $relative) === 1;
    }
}
