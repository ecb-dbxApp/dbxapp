<?php
declare(strict_types=1);

/**
 * Prüft Seitenfelder, responsive Formularraster, die aktive Tree-Markierung
 * sowie den weiterhin schreibenden Drag-and-drop-Pfad des Content-Trees.
 */

$base = dirname(__DIR__, 4);
require_once $base . '/dbx/include/tests/dbxModuleSourceBundle.php';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$cms_class = dbx_test_module_source_bundle($base . '/dbx/modules/dbxContent_admin/include/dbxContent_cms.class.php');
$persistence = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/include/dbxContentCmsPersistenceService.class.php');
$assert(
    str_contains($cms_class, "add_fld('folder', 'dbxContent_admin|cms-field-select'")
        && str_contains($cms_class, "get_fd_message('label_folder')")
        && str_contains($cms_class, 'options: $this->page_folder_values()')
        && str_contains($cms_class, 'private function page_folder_values(): array'),
    'Die Seiten-Ordnerzuordnung wird nicht als beschriftetes Auswahlfeld gerendert.'
);
$field_order = array('title', 'menu_title', 'permalink', 'folder', 'template', 'activ');
foreach (array('de' => '', 'en' => '_en', 'es' => '_es') as $language => $suffix) {
    $messages = array();
    require $base . '/dbx/modules/dbxContent_admin/fd/cms-page' . $suffix . '.fd.php';
    $assert(trim((string)($messages['label_folder'] ?? '')) !== '', 'Ordnerbeschriftung fehlt für ' . $language . '.');
}

$template = (string)file_get_contents(
    $base . '/dbx/modules/dbxContent_admin/tpl/htm/cms-admin-page-form.htm'
);
$last_position = -1;
foreach ($field_order as $field) {
    $needle = '{obj:' . $field . '}';
    $position = strpos($template, $needle);
    $assert($position !== false, 'Feld fehlt im gemeinsamen Seiten-Template: ' . $field);
    if ($position !== false) {
        $assert($position > $last_position, 'Feldreihenfolge ist falsch: ' . $field);
        $last_position = $position;
    }
}
$assert(
    str_contains($template, 'class="dbx-form-grid dbx-form-grid--6"')
        && substr_count($template, 'dbx-form-grid__item') >= 8
        && substr_count($template, 'dbx-form-grid__item--full') === 2
        && str_contains($template, '{obj:folder}'),
    'Das gemeinsame Seitenformular verwendet nicht den kanonischen dbxForm-Rastervertrag.'
);
$assert(
    !str_contains($template, 'dbx-cms-system-field')
        && !str_contains($template, 'dbx-cms-form-grid')
        && !str_contains($template, 'dbx-form-grid-6'),
    'Legacy-Klassen sind noch im gemeinsamen Seitenformular vorhanden.'
);
foreach (array('_en', '_es') as $suffix) {
    $assert(
        !is_file($base . '/dbx/modules/dbxContent_admin/tpl/htm/cms-admin-page-form' . $suffix . '.htm'),
        'Das sprachneutrale Seitenformular ist weiterhin als Strukturduplikat ' . $suffix . ' vorhanden.'
    );
}

$cms_js = (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms.js')
    . (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms-components.js')
    . (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms-page.js')
    . (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms-tree.js')
    . (string)file_get_contents($base . '/dbx/modules/dbxContent_admin/js/cms-language.js');
foreach (array(
    'function buildPageFolderOptions(root)',
    'cmsFieldSelector("folder", "page")',
    'buildPageFolderOptions(root);',
    'folder: Number(getField(root, "folder") || 0)',
) as $part) {
    $assert(str_contains($cms_js, $part), 'Hierarchische Ordnerauswahl unvollständig: ' . $part);
}
$assert(
    str_contains($cms_js, 'function handleLngAfterSave(root, cfg, data)')
        && str_contains($cms_js, 'if (Number(data.open_lng_provision) === 1)')
        && substr_count($cms_js, 'handleLngAfterSave(root, cfg, data);') >= 4
        && !str_contains($cms_js, 'Object.assign({}, data, { open_lng_provision: 0 })'),
    'Seiten und Ordner müssen nach jedem Speichern die angeforderte Sprachaktion ausführen.'
);
$assert(
    str_contains($cms_js, 'window.setTimeout(() => openLngProvisionDialog(root, cfg), 0)')
        && str_contains($cms_js, 'window.setTimeout(() => showLngSyncResultModal(root, data), 0)'),
    'Sprachdialog und Synchronisierungsergebnis werden nach dem Speichern nicht vollständig behandelt.'
);
$assert(
    str_contains($persistence, 'private function transaction(')
        && str_contains($persistence, '$this->db->begin($dd)')
        && str_contains($persistence, '$this->db->commit($dd)')
        && str_contains($persistence, '$this->db->rollback($dd)')
        && str_contains($persistence, 'public function flush_tree_move_cache(')
        && str_contains($persistence, '$this->flush_tree_move_cache($type, $id, $target')
        && str_contains($cms_class, 'private function lng_save_response('),
    'Transaktion, gebündelte Cache-Invalidierung oder gemeinsame Sprachantwort fehlt.'
);
$assert(
    str_contains($persistence, 'public function save_page(')
        && str_contains($persistence, 'public function save_folder(')
        && substr_count($persistence, '$this->transaction(') >= 7
        && str_contains($persistence, '$this->flush_lng_sync_cache((array)$result[\'sync_result\'], false)')
        && str_contains($persistence, 'public function flush_saved_folder_cache(')
        && str_contains($persistence, '$this->flush_saved_folder_cache($id, $parent_id)'),
    'Seiten-/Ordnerspeichern ist nicht atomar oder invalidiert Caches mehrfach.'
);
$assert(
    str_contains($cms_js, 'function applySaveSuccessStatus(root, data, message)')
        && substr_count($cms_js, 'applySaveSuccessStatus(root, data, cmsText') === 2,
    'Seiten- und Ordnerspeichern verwenden nicht denselben Statusablauf.'
);
$assert(
    str_contains($cms_js, 'function finishEntityDelete(root, cfg, type, id)')
        && substr_count($cms_js, 'return finishEntityDelete(root, cfg, type, id);') === 3
        && str_contains($cms_js, '["id", "title", "menu_title", "permalink", "description", "keywords"].forEach')
        && str_contains($cms_js, 'clearDirtyAfterSave(root);')
        && str_contains($cms_js, 'return loadTree(root, cfg).then(data => {')
        && substr_count($cms_js, 'showTreePanel(root);') >= 2,
    'Nach dem Löschen einer Seite oder eines Ordners wird der aktualisierte Content-Baum nicht zuverlässig angezeigt.'
);
$assert(
    str_contains($cms_js, 'function moveNode(root, cfg, type, id, targetFolder, position)')
        && str_contains($cms_js, 'const url = cfgUrl(cfg, "movenode")')
        && str_contains($cms_js, 'root.addEventListener("dragstart", e =>')
        && str_contains($cms_js, 'root.addEventListener("drop", e =>')
        && str_contains($cms_js, 'moveNode(root, cfg, data.type, Number(data.id), targetFolder, position);'),
    'Drag-and-drop im Content-Tree ist nicht mehr vollständig mit dem Schreibendpunkt verbunden.'
);
$assert(
    str_contains($cms_js, 'function normalizeFlexContentAlignment(surface)')
        && str_contains($cms_js, 'child.matches(".badge,.btn,a.btn,button.btn")')
        && str_contains($cms_js, 'center: "justify-content-center"')
        && str_contains($cms_js, 'flex.style.removeProperty("text-align")')
        && str_contains($cms_js, 'normalizeFlexContentAlignment(surface);')
        && str_contains($cms_js, 'topEditorChild(surface, range)')
        && str_contains($cms_js, 'function applyEditorAlignment(root, command)'),
    'Pill-/Button-Flexzeilen werden nicht zuverlaessig ueber justify-content ausgerichtet.'
);
$assert(
    str_contains($cms_js, 'const surfaceRect = surface.getBoundingClientRect')
        && str_contains($cms_js, 'contentBottom - surfaceRect.top + paddingBottom')
        && !str_contains($cms_js, 'root.__dbxCmsEditorHeightTimers = [180]'),
    'Die Editorhoehe wird weiterhin mit einer verzoegerten, sichtbaren Layout-Mutation berechnet.'
);
$assert(
    str_contains($cms_js, 'const row = treePress && treePress.row && root.contains(treePress.row) ? treePress.row : eventRow;')
        && str_contains($cms_js, 'const hasData = !!runtimeData || Array.from(e.dataTransfer.types || [])')
        && str_contains($cms_js, 'const row = treeDrag && treeDrag.row ? treeDrag.row : closestElement'),
    'Verschachtelte Tree-Zeilen oder Browser ohne MIME-Typ verlieren weiterhin die echte Drag-Quelle.'
);
$assert(
    str_contains($cms_class, "!empty(\$definition['mutation']) && dbx()->is_demo_mode()")
        && str_contains($cms_class, "'demo_readonly' => true")
        && substr_count($cms_js, 'err.demoReadonly = !!(data && data.demo_readonly);') === 2,
    'Der Demo-Modus trennt lesende CMS-Aufrufe und kontrolliert abgewiesene Änderungen nicht sauber.'
);
$assert(
    str_contains($cms_class, 'private function move_node_json()')
        && str_contains($cms_class, '$this->persistence($db)->move_node(')
        && str_contains($persistence, "\$data[\$parent_field] = \$target")
        && str_contains($persistence, 'private function reorder_siblings(')
        && str_contains($persistence, 'dbxContentTreeOrder::plan(')
        && str_contains($persistence, "'sorter_updates' => count(\$result['changed'])"),
    'Der Tree-Schreibendpunkt aktualisiert Zuordnung oder Sortierung nicht vollständig.'
);
$assert(
    str_contains($persistence, 'if ($data && $this->db->update')
        && str_contains($persistence, "foreach (\$plan['updates'] as \$row_id => \$sorter)"),
    'Unveränderte Tree-Positionen erzeugen weiterhin unnötige Datenbank-Updates.'
);

foreach (array('dbxapp', 'flowers', 'steal') as $design) {
    $css = (string)file_get_contents($base . '/dbx/design/' . $design . '/css/c-cms.css');
    $assert(
        str_contains($css, '.dbx-cms-page-panel > .dbx-form-grid')
            && !str_contains($css, '.dbx-cms-form-grid-page')
            && !str_contains($css, '.dbx-cms-system-field'),
        'CMS-spezifische Raster-Altlasten sind in ' . $design . ' noch vorhanden.'
    );
    $assert(
        str_contains($css, '.dbx-cms-tree-row.is-active')
            && str_contains($css, '#fff9d8')
            && str_contains($css, 'border-left: 4px solid'),
        'Die aktive Tree-Seite ist in ' . $design . ' nicht deutlich gelb/golden markiert.'
    );
    if ($design !== 'flowers') {
        $assert(
            str_contains($css, '.dbx-cms:not(.dbx-cms-view).is-tree-collapsed .dbx-cms-shell,')
                && str_contains($css, 'grid-template-columns: minmax(0, 1fr);'),
            'Die spezifische Tablet-Mindestbreite bleibt mobil aktiv in ' . $design . '.'
        );
    }

    $form_css = (string)file_get_contents($base . '/dbx/design/' . $design . '/css/c-form.css');
    foreach (array(
        '.dbx-form-grid',
        '.dbx-form-grid--12',
        '.dbx-form-grid--6',
        '.dbx-form-grid--4',
        '.dbx-form-grid--2',
        '.dbx-form-grid__item--full',
        '.dbx-form-field',
        '.dbx-form-field__label',
        '.dbx-form-field__control',
        'grid-template-columns: repeat(6, minmax(0, 1fr));',
        'grid-template-columns: repeat(4, minmax(0, 1fr));',
        'grid-template-columns: repeat(3, minmax(0, 1fr));',
        'grid-template-columns: repeat(2, minmax(0, 1fr));',
        'grid-template-columns: minmax(0, 1fr);',
    ) as $part) {
        $assert(
            str_contains($form_css, $part),
            'Allgemeine Formularraster-Regel fehlt in ' . $design . ': ' . $part
        );
    }
    $assert(
        str_contains($form_css, '@media (max-width: 1200px)')
            && str_contains($form_css, '@media (max-width: 900px)')
            && str_contains($form_css, '@media (max-width: 700px)')
            && !str_contains($form_css, '.dbx-form-grid-6')
            && !str_contains($form_css, '.dbx-form-grid-4'),
        'Responsive Breakpoints der Formularraster fehlen im Design ' . $design . '.'
    );
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK CMS page folder selector and canonical dbxForm layout\n";
