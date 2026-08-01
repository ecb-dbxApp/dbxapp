<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$failures = array();
$paths = array();

// Die Entwicklungsinstanz enthält bewusst lokale Daten und Arbeitsstände.
// Der kontrollierte GitHub-/Release-Spiegel ist an seinem verbindlichen
// Release-Prozess erkennbar und wird vollständig geprüft.
if (!is_file($root . '/RELEASE_PROCESS.md')) {
    echo "OK public release hygiene: authoritative development instance is not the public mirror.\n";
    exit(0);
}

if (is_dir($root . '/.git') && function_exists('proc_open')) {
    $process = proc_open(
        array('git', '-C', $root, 'ls-files', '--cached', '--others', '--exclude-standard', '-z'),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
        $pipes,
        $root,
        null,
        array('bypass_shell' => true)
    );
    if (is_resource($process)) {
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code === 0 && is_string($output)) {
            $paths = array_values(array_filter(explode("\0", $output), 'strlen'));
        } elseif (trim((string)$error) !== '') {
            $failures[] = 'Git-Dateiliste konnte nicht gelesen werden: ' . trim((string)$error);
        }
    }
}

if ($paths === array()) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $item): bool {
                if (!$item->isDir()) {
                    return true;
                }
                $path = str_replace('\\', '/', $item->getPathname());
                foreach (array('/.git', '/dbx/vendor', '/dbx/files', '/files', '/dist', '/output', '/tmp') as $excluded) {
                    if (str_contains($path, $excluded)) {
                        return false;
                    }
                }
                return true;
            }
        )
    );
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $paths[] = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        }
    }
}

$secretPatterns = array(
    '/-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----\s+[A-Za-z0-9+\/=\r\n]{80,}-----END (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/s',
    '/\bgithub_pat_[A-Za-z0-9_]{40,}\b/',
    '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/',
    '/\bAKIA[0-9A-Z]{16}\b/',
    '/\bsk-[A-Za-z0-9]{32,}\b/',
    '/[\'"](?:token_secret|client_secret|api_key|private_key|password|pass)[\'"]\s*\]\s*=\s*[\'"][^\'"\s]{12,}[\'"]/i',
);

foreach (array_unique($paths) as $relative) {
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $absolute = $root . '/' . $relative;
    if (!is_file($absolute)) {
        continue;
    }
    $base = basename($relative);
    $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    if (in_array($base, array('.env', '.env.local', 'config.local.php'), true)
        || in_array($extension, array('db3', 'sqlite', 'sqlite3', 'log', 'pem', 'key', 'p12', 'pfx'), true)
        || preg_match('#/(?:backup|_backup|\.backup|work)/#i', '/' . $relative)
    ) {
        $failures[] = 'Nicht veröffentlichbare Datei: ' . $relative;
        continue;
    }
    if (filesize($absolute) > 2 * 1024 * 1024
        || !preg_match('/\.(?:php|js|mjs|ts|json|xml|ya?ml|md|txt|dox|htm|html|css|scss|env|example)$/i', $relative)
    ) {
        continue;
    }
    $content = file_get_contents($absolute);
    if (!is_string($content)) {
        continue;
    }
    foreach ($secretPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $failures[] = 'Mögliches Secret in: ' . $relative;
            break;
        }
    }
}

$releaseBuilder = $root . '/tools/build-release.php';
if (is_file($releaseBuilder)) {
    $releaseBuilderContent = (string)file_get_contents($releaseBuilder);
    if (str_contains($releaseBuilderContent, "preg_match('#/tests/#'")) {
        $failures[] = 'Kunden-Releases müssen die dbxSelfTest-Vertragstests enthalten.';
    }
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo 'OK public release hygiene: ' . count(array_unique($paths)) . " Dateien geprüft.\n";
