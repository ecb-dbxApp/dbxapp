<?php
declare(strict_types=1);

$base = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$cms_js = (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms.js');
$editor_js = (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms-editor.js');
$tree_js = (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms-tree.js');
$page_js = (string)file_get_contents($base . '/modules/dbxContent_admin/js/cms-page.js');
$cms_css = (string)file_get_contents($base . '/design/dbxapp/css/c-cms.css');
$cms_php = dbx_test_module_source_bundle($base . '/modules/dbxContent_admin/include/dbxContent_cms.class.php');

$init_start = strpos($cms_js, 'init(el, cfg)');
$init_end = strpos($cms_js, 'rescan(root)', $init_start);
$init_source = substr($cms_js, $init_start, $init_end - $init_start);
$assert(
    str_contains($init_source, 'loadInitialSelection(el, cfg || {})')
        && !str_contains($init_source, 'loadTree(el, cfg || {})'),
    'Der CMS-Start lädt den Content-Baum weiterhin sofort.'
);
$assert(
    str_contains($cms_js, 'if (!collapsed) ensureTreeLoaded(root, cfg || cmsConfig(root));')
        && str_contains($tree_js, 'if (s.treeLoaded) return Promise.resolve(s.tree);')
        && str_contains($tree_js, 'if (s.treeLoading && s.treePromise) return s.treePromise;')
        && str_contains($cms_js, 'if (!s.treeLoaded) return;'),
    'Lazy Loading oder Request-Zusammenführung des Content-Baums fehlt.'
);
$initial_start = strpos($page_js, 'function loadInitialSelection(root, cfg)');
$initial_end = strpos($page_js, 'function setSelectValues(', $initial_start);
$initial_source = substr($page_js, $initial_start, $initial_end - $initial_start);
$assert(
    substr_count($initial_source, 'ensureTreeLoaded(root, cfg)') === 1
        && !str_contains($initial_source, 'const firstPage ='),
    'Eine leere oder frische CMS-Sitzung lädt den Baum noch als Seiten-Fallback.'
);
$assert(
    str_contains($cms_js, 'tree: [["js", "module", "dbxContent_admin/cms-tree.js"]]')
        && !str_contains($init_source, 'ensureCmsModule("tree")')
        && !str_contains($init_source, 'ensureCmsModule("media")'),
    'Tree oder Medienbrowser werden beim CMS-Start weiterhin erzwungen.'
);

$caret_start = strpos($editor_js, 'function refreshEditorCaretHint(root');
$caret_end = $caret_start === false ? false : strpos($editor_js, 'function hideEditorCaretHint(root)', $caret_start);
$caret_source = $caret_start !== false && $caret_end !== false
    ? substr($editor_js, $caret_start, $caret_end - $caret_start)
    : '';
$assert(
    str_contains($caret_source, 'window.requestAnimationFrame')
        && str_contains($caret_source, 'range.getBoundingClientRect()')
        && str_contains($caret_source, 'surface.getBoundingClientRect()')
        && str_contains($caret_source, 'explicitRange || s.editorContextPasteRange || currentEditorCaretRange(surface)')
        && !str_contains($caret_source, 's.editorContextPasteRange || s.editorRange')
        && str_contains($cms_css, '.dbx-cms-editor-caret-hint'),
    'Die sichtbare Editor-Einfügemarke ist nicht positionsgenau oder nicht gegen versetzte Bereiche abgesichert.'
);

$render_source = dbx_test_module_method_source($cms_php, 'render_cms');
$assert(
    !str_contains($render_source, '$this->cms_tree()')
        && str_contains($render_source, '$db->count(dbxContentLng::dd_content(), \'\')')
        && str_contains($render_source, '$db->count(dbxContentLng::dd_folder(), \'\')'),
    'Der initiale CMS-Render baut den vollständigen Baum noch für die Zähler auf.'
);
$resolve_source = dbx_test_module_method_source($cms_php, 'resolve_cms_page_id');
$assert(
    str_contains($resolve_source, "'sorter,title,id'")
        && str_contains($resolve_source, "\n         1,\n         0,\n         0\n"),
    'Die initiale Seite wird ohne URL nicht per kleiner Einzelabfrage bestimmt.'
);
$assert(
    str_contains($cms_php, 'private function tree_lng_coverage_rows($db): array')
        && !str_contains($cms_php, '$node[\'_row_html\'] =')
        && !str_contains($cms_php, '$tree[\'tree_html\'] ='),
    'Der Tree-Endpunkt erzeugt weiterhin zeilenweise DB-/Template-Arbeit.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK CMS cursor and lazy content tree performance contract.\n";
