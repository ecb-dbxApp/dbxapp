<?php
declare(strict_types=1);

$base = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$cmsJs = (string)file_get_contents($base . '/js/lib/cms.js');
$treeJs = (string)file_get_contents($base . '/js/lib/cms-tree.js');
$pageJs = (string)file_get_contents($base . '/js/lib/cms-page.js');
$cmsCss = (string)file_get_contents($base . '/design/dbxapp/css/c-cms.css');
$cmsPhp = dbx_test_module_source_bundle($base . '/modules/dbxContent_admin/include/dbxContent_cms.class.php');

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
        && str_contains($treeJs, 'if (s.treeLoaded) return Promise.resolve(s.tree);')
        && str_contains($treeJs, 'if (s.treeLoading && s.treePromise) return s.treePromise;')
        && str_contains($cmsJs, 'if (!s.treeLoaded) return;'),
    'Lazy Loading oder Request-Zusammenführung des Content-Baums fehlt.'
);
$initialStart = strpos($pageJs, 'function loadInitialSelection(root, cfg)');
$initialEnd = strpos($pageJs, 'function setSelectValues(', $initialStart);
$initialSource = substr($pageJs, $initialStart, $initialEnd - $initialStart);
$assert(
    substr_count($initialSource, 'ensureTreeLoaded(root, cfg)') === 1
        && !str_contains($initialSource, 'const firstPage ='),
    'Eine leere oder frische CMS-Sitzung lädt den Baum noch als Seiten-Fallback.'
);
$assert(
    str_contains($cmsJs, 'tree: [["js", "lib", "cms-tree.js"]]')
        && !str_contains($initSource, 'ensureCmsModule("tree")')
        && !str_contains($initSource, 'ensureCmsModule("media")'),
    'Tree oder Medienbrowser werden beim CMS-Start weiterhin erzwungen.'
);

$caretStart = strpos($cmsJs, 'function refreshEditorCaretHint(root');
$caretEnd = $caretStart === false ? false : strpos($cmsJs, 'function hideEditorCaretHint(root)', $caretStart);
$caretSource = $caretStart !== false && $caretEnd !== false
    ? substr($cmsJs, $caretStart, $caretEnd - $caretStart)
    : '';
$assert(
    str_contains($caretSource, 'window.requestAnimationFrame')
        && str_contains($caretSource, 'range.getBoundingClientRect()')
        && str_contains($caretSource, 'surface.getBoundingClientRect()')
        && str_contains($caretSource, 'explicitRange || s.editorContextPasteRange || currentEditorCaretRange(surface)')
        && !str_contains($caretSource, 's.editorContextPasteRange || s.editorRange')
        && str_contains($cmsCss, '.dbx-cms-editor-caret-hint'),
    'Die sichtbare Editor-Einfügemarke ist nicht positionsgenau oder nicht gegen versetzte Bereiche abgesichert.'
);

$renderSource = dbx_test_module_method_source($cmsPhp, 'render_cms');
$assert(
    !str_contains($renderSource, '$this->cms_tree()')
        && str_contains($renderSource, '$db->count(dbxContentLng::ddContent(), \'\')')
        && str_contains($renderSource, '$db->count(dbxContentLng::ddFolder(), \'\')'),
    'Der initiale CMS-Render baut den vollständigen Baum noch für die Zähler auf.'
);
$resolveSource = dbx_test_module_method_source($cmsPhp, 'resolve_cms_page_id');
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
