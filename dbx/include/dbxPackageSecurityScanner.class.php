<?php

declare(strict_types=1);

/** Statischer Sicherheits- und Konformitaetscheck fuer Marktplatzpakete. */
final class dbxPackageSecurityScanner
{
    private dbxPackageContract $contract;

    public function __construct()
    {
        require_once __DIR__ . '/dbxPackageContract.class.php';
        $this->contract = new dbxPackageContract();
    }

    /** @return array{approved:bool,errors:array<int,string>,warnings:array<int,string>,scanned_files:int} */
    public function scan(array $manifest, string $root): array
    {
        $manifest = $this->contract->validate_manifest($manifest, true);
        $errors = array();
        $warnings = array();
        $scanned = 0;
        $first_party = $manifest['vendor']['id'] === 'dbxapp';
        $forbidden_extensions = array('exe', 'dll', 'com', 'bat', 'cmd', 'ps1', 'phar', 'pfx', 'p12', 'key', 'pem');
        foreach ($manifest['files'] as $relative => $expected_hash) {
            if (!$this->contract->path_allowed($manifest['type'], $manifest['name'], $relative)) {
                $errors[] = 'Datei verlaesst den Paketbereich: ' . $relative;
                continue;
            }
            $file = rtrim($root, '\\/') . '/' . $relative;
            if (!is_file($file) || is_link($file)) {
                $errors[] = 'Datei fehlt oder ist ein Symlink: ' . $relative;
                continue;
            }
            $actual_hash = strtolower((string)hash_file('sha256', $file));
            if (!hash_equals($expected_hash, $actual_hash)) {
                $errors[] = 'SHA-256 stimmt nicht: ' . $relative;
                continue;
            }
            ++$scanned;
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            $public_trust_key = $manifest['type'] === 'kernel'
                && $extension === 'pem'
                && preg_match('#^dbx/marketplace/keys/[A-Za-z0-9._-]+\.pem$#', $relative);
            if (in_array($extension, $forbidden_extensions, true) && !$public_trust_key) {
                $errors[] = 'Nicht erlaubter Dateityp: ' . $relative;
                continue;
            }
            if (!in_array($extension, array('php', 'js', 'htm', 'html'), true) || filesize($file) > 4 * 1024 * 1024) {
                continue;
            }
            $source = (string)file_get_contents($file);
            if ($manifest['type'] === 'design' && $extension === 'php') {
                $errors[] = 'Designpakete duerfen keinen PHP-Code enthalten: ' . $relative;
            }
            if ($extension === 'php') {
                foreach (array(
                    '~\b(?:eval|assert|shell_exec|passthru|proc_open|popen)\s*\(~i' => 'riskanter PHP-Aufruf',
                    '~\b(?:include|require)(?:_once)?\s*\(\s*\$~i' => 'dynamischer Include-Pfad',
                    '~(?:base64_decode|gzinflate)\s*\([^;]+\)~i' => 'verschleierter Code',
                ) as $pattern => $label) {
                    if (preg_match($pattern, $source)) {
                        if ($first_party) {
                            $warnings[] = $label . ': ' . $relative;
                        } else {
                            $errors[] = $label . ': ' . $relative;
                        }
                    }
                }
                if ($manifest['vendor']['id'] !== 'dbxapp' && preg_match('~\b(?:PDO|mysqli)\b~', $source)) {
                    $errors[] = 'Direkter Datenbankzugriff statt dbxDB: ' . $relative;
                }
            }
            if ($extension === 'js' && !in_array('network', $manifest['permissions'], true)
                && preg_match('~\b(?:fetch|WebSocket|XMLHttpRequest)\b~', $source)) {
                $errors[] = 'Netzwerkzugriff ohne Berechtigung: ' . $relative;
            }
            if (in_array($extension, array('htm', 'html'), true) && preg_match('~\son[a-z]+\s*=~i', $source)) {
                $warnings[] = 'Inline-Eventhandler pruefen: ' . $relative;
            }
        }
        return array(
            'approved' => $errors === array(),
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'scanned_files' => $scanned,
        );
    }
}
