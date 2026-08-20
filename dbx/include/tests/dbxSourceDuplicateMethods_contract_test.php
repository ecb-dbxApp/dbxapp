<?php
declare(strict_types=1);

$project_root = dirname(__DIR__, 3);
$dbx_root = $project_root . '/dbx';
$manifest = json_decode((string)file_get_contents($dbx_root . '/third-party-sources.json'), true);
$foreign = array();
foreach ((array)($manifest['sources'] ?? array()) as $entry) {
    $foreign[str_replace('\\', '/', (string)($entry['path'] ?? ''))] = true;
}

$method_bodies = array();
foreach (array($dbx_root . '/include', $dbx_root . '/modules') as $root) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        $relative = ltrim(str_replace(str_replace('\\', '/', $project_root), '', $path), '/');
        if (!$file->isFile()
            || strtolower($file->getExtension()) !== 'php'
            || isset($foreign[$relative])
            || preg_match('~/(?:vendor|tests?|dd|fd|_backup|backups|work|temp|myX)/~i', $path)
        ) {
            continue;
        }

        $tokens = token_get_all((string)file_get_contents($path));
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) {
                continue;
            }
            $cursor = $index + 1;
            while ($cursor < $count && ((is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) || $tokens[$cursor] === '&')) {
                $cursor++;
            }
            if ($cursor >= $count || !is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_STRING) {
                continue;
            }
            $method = $tokens[$cursor][1];
            while ($cursor < $count && $tokens[$cursor] !== '{') {
                $cursor++;
            }
            if ($cursor >= $count) {
                continue;
            }

            $depth = 0;
            $body = '';
            for (; $cursor < $count; $cursor++) {
                $token = $tokens[$cursor];
                $text = is_array($token) ? $token[1] : $token;
                if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                    $text = '';
                }
                $body .= $text;
                if ($text === '{') {
                    $depth++;
                } elseif ($text === '}' && --$depth === 0) {
                    break;
                }
            }
            if (strlen($body) > 80) {
                $method_bodies[hash('sha256', $body)][] = $relative . '::' . $method;
            }
        }
    }
}

$allowed = array(
    array(
        'dbx/include/dbxProcess.class.php::add_norep',
        'dbx/include/dbxWebAppPresentation.trait.php::add_norep',
    ),
    array(
        'dbx/include/dbxView.class.php::__construct',
        'dbx/modules/dbxContent_admin/include/dbxContent_sysdata.class.php::__construct',
        'dbx/modules/dbxContent_admin/include/dbxFolder_edit.class.php::__construct',
    ),
    array(
        'dbx/modules/dbxDesign_admin/include/dbxDesignAdmin.class.php::__construct',
        'dbx/modules/dbxKi/include/dbxKiDesignService.class.php::__construct',
    ),
);
foreach ($allowed as &$group) {
    sort($group);
}
unset($group);

$unexpected = array();
foreach ($method_bodies as $group) {
    if (count($group) < 2) {
        continue;
    }
    sort($group);
    if (!in_array($group, $allowed, true)) {
        $unexpected[] = $group;
    }
}

if ($unexpected !== array()) {
    fwrite(STDERR, "Neue exakte Methodenduplikate gefunden:\n");
    foreach ($unexpected as $group) {
        fwrite(STDERR, '- ' . implode(', ', $group) . "\n");
    }
    exit(1);
}

echo "OK exakte Methodenduplikate: nur dokumentierte Kompatibilitaetsadapter und triviale Konstruktoren.\n";
