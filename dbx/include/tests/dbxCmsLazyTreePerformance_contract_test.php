<?php
declare(strict_types=1);

$base = dirname(__DIR__, 2);
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$cmsJs = (string)file_get_contents($base . '/js/lib/cms.js');
$cmsCss = (string)file_get_contents($base . '/design/dbxapp/css/c-cms.css');
$cmsPhp = (string)file_get_contents($base . '/modules/dbxContent_admin/include/dbxContent_cms.class.php');

$initStart = strpos($cmsJs, 'init(el, cfg)');
$initEnd = strpos($cmsJs, 'rescan(root)', $initStart);
$initSource = substr($cmsJs, $initStart, $initEnd - $initStart);
$assert(
    str_contains($initSource, 'loadInitialSelection(el, cfg || {})')
        && !str_contains($initSource, 'loadTree(el, cfg || {})'),
    'Der CMS-Start lädt den Content-Baum weiterhin sofort.'
);
$assert(
    str_contains($cmsJs, 'if (!collapsed) ensureTreeLoaded(root, cfg || cmsConfig(root));')
        && str_contains($cmsJs, 'if (s.treeLoaded) return Promise.resolve(s.tree);')
        && str_contains($cmsJs, 'if (s.treeLoading && s.treePromise) return s.treePromise;'),
    'Lazy Loading oder Request-Zusammenführung des Content-Baums fehlt.'
);
$initialStart = strpos($cmsJs, 'function loadInitialSelection(root, cfg)');
$initialEnd = strpos($cmsJs, 'function setSelectValues(', $initialStart);
$initialSource = substr($cmsJs, $initialStart, $initialEnd - $initialStart);
$assert(
    substr_count($initialSource, 'ensureTreeLoaded(root, cfg)') === 1
        && !str_contains($initialSource, 'const firstPage ='),
    'Eine leere oder frische CMS-Sitzung lädt den Baum noch als Seiten-Fallback.'
);

$caretStart = strpos($cmsJs, 'function refreshEditorCaretHint(root)');
$caretEnd = strpos($cmsJs, 'function hideEditorCaretHint(root)', $caretStart);
$caretSource = substr($cmsJs, $caretStart, $caretEnd - $caretStart);
$assert(
    str_contains($caretSource, 'hideEditorCaretHint(root);')
        && !str_contains($caretSource, 'showEditorCaretHint(root')
        && !str_contains($cmsCss, '.dbx-cms-editor-caret-hint'),
    'Der versetzbare künstliche Editor-Cursor ist noch aktiv.'
);

$renderStart = strpos($cmsPhp, 'private function render_cms()');
$renderEnd = strpos($cmsPhp, 'private function mod_class_files(', $renderStart);
$renderSource = substr($cmsPhp, $renderStart, $renderEnd - $renderStart);
$assert(
    !str_contains($renderSource, '$this->cms_tree()')
        && str_contains($renderSource, '$db->count(dbxContentLng::ddContent(), \'\')')
        && str_contains($renderSource, '$db->count(dbxContentLng::ddFolder(), \'\')'),
    'Der initiale CMS-Render baut den vollständigen Baum noch für die Zähler auf.'
);
$resolveStart = strpos($cmsPhp, 'private function resolve_cms_page_id(): int');
$resolveEnd = strpos($cmsPhp, 'private function attach_unreachable_tree_nodes(', $resolveStart);
$resolveSource = substr($cmsPhp, $resolveStart, $resolveEnd - $resolveStart);
$assert(
    str_contains($resolveSource, "'sorter,title,id'")
        && str_contains($resolveSource, "\n         1,\n         0,\n         0\n"),
    'Die initiale Seite wird ohne URL nicht per kleiner Einzelabfrage bestimmt.'
);
$assert(
    str_contains($cmsPhp, 'private function tree_lng_coverage_rows($db): array')
        && !str_contains($cmsPhp, '$node[\'_row_html\'] =')
        && !str_contains($cmsPhp, '$tree[\'tree_html\'] ='),
    'Der Tree-Endpunkt erzeugt weiterhin zeilenweise DB-/Template-Arbeit.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK CMS cursor and lazy content tree performance contract.\n";
