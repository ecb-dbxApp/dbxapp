<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

function docs_portal_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function docs_portal_read(string $path): string
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('Datei kann nicht gelesen werden: ' . $path);
    }
    return $content;
}

$designRoot = $root . '/dbx/design/dbxdocs';
$pageTemplate = docs_portal_read($designRoot . '/htm/default.htm');
$pageShellHead = docs_portal_read($root . '/dbx/modules/dbxDocs/tpl/htm/page-shell-head.htm');
$pageShellStage = docs_portal_read($root . '/dbx/modules/dbxDocs/tpl/htm/page-shell-stage-head.htm');
$pageShellContent = docs_portal_read($root . '/dbx/modules/dbxDocs/tpl/htm/page-shell-content.htm');
$template = $pageShellHead . $pageShellStage . $pageShellContent;
$layout = docs_portal_read($designRoot . '/css/docs-layout.css');
$docsContent = docs_portal_read($designRoot . '/css/docs-content.css');
$contentCss = docs_portal_read($designRoot . '/css/c-content.css');
$darkSkin = docs_portal_read($designRoot . '/css/skin-dunkel.css');
$javascript = docs_portal_read($designRoot . '/js/dbxdocs.js');
$homeContent = docs_portal_read($root . '/dbx/modules/dbxDocs/content/dbxapp_home.html');
$installationContent = docs_portal_read($root . '/dbx/modules/dbxDocs/content/dbxapp_user_installation.html');
$installationTutorial = docs_portal_read($root . '/dbx/modules/dbxDocs/content/tutorial_installation.html');
$selfTestContent = docs_portal_read($root . '/dbx/modules/dbxDocs/content/dbxapp_user_selftest.html');
$menuJavascript = docs_portal_read($root . '/dbx/js/lib/menu.js');
$menuModule = docs_portal_read($root . '/dbx/modules/dbxMenu/dbxMenu.class.php');
$menuDe = docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-main-primary.htm');
$menuEn = docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-main-primary_en.htm');
$menuEs = docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-main-primary_es.htm');
$sectionMenus = array(
    docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-section-start.htm'),
    docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-section-apply.htm'),
    docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-section-develop.htm'),
    docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-section-operate.htm'),
);
$referenceMenus = array(
    docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-section-reference.htm'),
    docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-section-reference_en.htm'),
    docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-section-reference_es.htm'),
);
$pageResolver = docs_portal_read($root . '/dbx/modules/dbxDocs/include/dbxDocsPageResolver.class.php');
$doxyfile = is_file($root . '/Doxyfile') ? docs_portal_read($root . '/Doxyfile') : '';
$index = docs_portal_read($root . '/index.php');
$contentDd = docs_portal_read($root . '/dbx/modules/dbx/dd/dbxContent.dd.inc.php');
$docsModule = docs_portal_read($root . '/dbx/modules/dbxDocs/dbxDocs.class.php');
$docsTemplate = docs_portal_read($root . '/dbx/modules/dbxDocs/tpl/htm/reference.htm');
$docsWindowTemplate = docs_portal_read($root . '/dbx/modules/dbxDocs/tpl/htm/reference-window.htm');
$docsSearchTemplate = docs_portal_read($root . '/dbx/modules/dbxDocs/tpl/htm/search.htm');
$docsNotFoundTemplate = docs_portal_read($root . '/dbx/modules/dbxDocs/tpl/htm/not-found.htm');
$docsProvision = docs_portal_read($root . '/dbx/modules/dbxDocs/include/dbxDocsContentProvision.class.php');
$docsMediaProvision = docs_portal_read($root . '/dbx/modules/dbxDocs/tools/update_docs_portal_media_20260728.php');
$docsAccess = docs_portal_read($root . '/dbx/modules/dbxDocs/tools/configure_docs_access_20260728.php');
$htaccess = docs_portal_read($root . '/.htaccess');
$contentMenu = docs_portal_read($root . '/dbx/modules/dbxMenu/include/dbxContent_menu.class.php');
docs_portal_assert(
    is_file($root . '/dbx/modules/dbxContent/tpl/htm/c-content.htm'),
    'Das sprachunabhängige Standard-Content-Template c-content.htm fehlt.'
);

foreach (array(
    'base.css',
    'c-form.css',
    'c-report.css',
    'c-menu.css',
    'skin-blau.css',
    'theme.css',
    'docs-layout.css',
    'docs-content.css',
) as $css) {
    docs_portal_assert(
        is_file($designRoot . '/css/' . $css),
        'Erforderliche Design-CSS fehlt: ' . $css
    );
}

$asidePosition = strpos($template, '<aside id="dbxHeader"');
$mainPosition = strpos($template, '<main id="dbxMain"');
docs_portal_assert(
    $asidePosition !== false && $mainPosition !== false && $asidePosition < $mainPosition,
    'Die Dokumentationsnavigation muss links vor dem Hauptinhalt stehen.'
);
docs_portal_assert(
    str_contains($pageTemplate, 'tpl=modul|dbx-docs-main-primary'),
    'Das sprachabhängige Dokumentationsmenü ist nicht eingebunden.'
);
docs_portal_assert(
    !str_contains($template, 'fleurop-') && !str_contains($layout, 'fleurop-'),
    'Das dbxdocs-Design darf keine flowers-spezifischen Klassen übernehmen.'
);
docs_portal_assert(
    str_contains($layout, '--dbxdocs-blue-900')
        && str_contains($layout, 'grid-template-columns')
        && str_contains($layout, '.dbx-cms-menu-page'),
    'Blauer Layout-, Seitenleisten- oder Einklappvertrag fehlt.'
);
docs_portal_assert(
    str_contains($template, 'data-dbx-menu-active-open="1"')
        && str_contains($template, 'docs-layout.css?v=11')
        && str_contains($template, 'dbxdocs.js?v=6')
        && preg_match('/core\.js\?design=\{dbx:design\}&log=off&v=\d+/', $template) === 1
        && str_contains($menuJavascript, "data-dbx-menu-active-open")
        && str_contains($menuJavascript, 'restoreActivePath($el)')
        && str_contains($menuJavascript, 'function closeBranch($item)')
        && str_contains($menuJavascript, 'closeAllMenus(true)')
        && str_contains($menuJavascript, "\$parent.addClass('is-active-path')")
        && !str_contains($menuJavascript, "\$parent.addClass('is-active is-active-path')")
        && str_contains($layout, '.is-active-path:not(.is-active)')
        && str_contains($menuJavascript, '.dbxLngOpt, .dbx-design-opt, .dbx-design-skin-opt, .dbx-skin-opt'),
    'Aktive Dokumentationsordner lassen sich nicht zuverlässig schließen.'
);
docs_portal_assert(
    str_contains($layout, '.dbxdocs-admin-strip .dbx-menu > .dbx-menu-list')
        && str_contains($layout, 'flex-wrap: wrap;')
        && str_contains($layout, '.dbxdocs-stage #dbxMain')
        && str_contains($layout, '.dbxdocs-sectionbar')
        && str_contains($layout, '.dbx-menu-section')
        && substr_count($layout, 'min-width: 0;') >= 8,
    'Das Dokumentationsportal darf mit eingeblendeter Admin-Navigation nicht horizontal überlaufen.'
);
docs_portal_assert(
    str_contains($menuModule, "\$docsDisplay = stripos((string)\$menu, 'dbx-docs-main') !== false")
        && str_contains($menuModule, "array('dbxdocs' => 'dbxapp (Blau)')")
        && str_contains($menuModule, "array('blau' => true, 'dunkel' => true)")
        && str_contains($menuModule, "\$options['blau']['label'] = 'Light'")
        && str_contains($menuModule, "\$options['dunkel']['label'] = 'Dark'"),
    'Die Dokumentationsnavigation muss genau dbxapp (Blau) mit Light und Dark anbieten.'
);
docs_portal_assert(
    str_contains($darkSkin, 'text-shadow: 0 0 1px rgba(95, 255, 95, 0.18)')
        && !str_contains($darkSkin, '0 0 9px rgba(63, 255, 63, 0.30)')
        && substr_count($darkSkin, 'text-shadow: none;') >= 2,
    'Die grüne Dark-Skin-Schrift muss ohne unscharfen Leuchteffekt bleiben.'
);
docs_portal_assert(
    str_contains($template, 'docs-content.css?v=9')
        && str_contains($docsContent, '.dbx-user-nav-icon svg')
        && str_contains($docsContent, '.dbx-ki-area-grid')
        && str_contains($docsContent, '.dbx-update-state-grid')
        && str_contains($docsContent, 'body.dbx-docs .dbx-content-body')
        && str_contains($docsContent, 'body.dbx-docs .dbxContent_wrapper.dbx-content-page')
        && str_contains($docsContent, 'padding: clamp(.85rem, 1.4vw, 1.2rem)')
        && str_contains($docsContent, '.dbx-doc-figure')
        && str_contains($docsContent, '.dbxdocs-home-search')
        && str_contains($docsContent, 'grid-template-columns: auto minmax(0, 1fr) auto')
        && str_contains($docsContent, '.dbxdocs-path-grid')
        && str_contains($docsContent, '--dbx-doc-table-hover: #fff0a8')
        && str_contains($docsContent, 'table tbody tr:hover')
        && str_contains($docsContent, 'body.dbx-app.skin-dunkel .dbxdocs-cms-article')
        && str_contains($docsContent, '--dbx-doc-text: #7cff7c')
        && str_contains($docsContent, 'text-shadow: 0 0 1px rgba(95, 255, 95, .18)'),
    'Versionierte CMS-Sonderformatierung für Navigation, KI und Updates fehlt.'
);
docs_portal_assert(
    substr_count($contentCss, 'aspect-ratio: 16 / 9;') >= 2,
    'Die Portal-Galerie muss Bilder im Querformat darstellen.'
);
docs_portal_assert(
    str_contains($docsMediaProvision, "\$mediaDd = 'dbx|dbxMedia';")
        && str_contains($docsMediaProvision, "\$usageDd = 'dbx|dbxMediaUsage';")
        && str_contains($docsMediaProvision, 'thumb_file_path')
        && str_contains($docsMediaProvision, 'dbxContentPageCache::invalidateAll()'),
    'Reproduzierbare Medienaktualisierung über dbxDB und Cache-Invalidierung fehlt.'
);
docs_portal_assert(
    str_contains($javascript, 'sidebarCollapsed')
        && str_contains($javascript, 'is-mobile-nav-open')
        && str_contains($javascript, 'initSectionNavigation')
        && str_contains($javascript, 'resetInitialContentScroll')
        && str_contains($javascript, 'scrollRestoration'),
    'Persistente Desktop- und mobile Navigation fehlen.'
);
docs_portal_assert(
    str_contains($javascript, 'function buildPageToc()')
        && str_contains($javascript, 'h2, h3')
        && str_contains($javascript, 'scrollIntoView')
        && str_contains($docsContent, '.dbxdocs-page-toc'),
    'Das automatisch erzeugte Inhaltsverzeichnis für lange Seiten fehlt.'
);
docs_portal_assert(
    str_contains($homeContent, 'data-dbx-doc-revision="2026-08-01-portal-v5"')
        && str_contains($homeContent, 'Wissen aus einer Quelle')
        && !str_contains($homeContent, 'Version 4.')
        && str_contains($homeContent, '<h1>Was möchten Sie erreichen?</h1>')
        && substr_count($homeContent, 'dbxdocs-home-card') >= 10
        && str_contains($homeContent, 'dokumentation-selbsttest')
        && str_contains($homeContent, 'dbx_run1=" value="search') === false
        && str_contains($homeContent, 'name="dbx_run1" value="search"')
        && !str_contains($pageTemplate . $template, 'docs-cinematic')
        && !str_contains($pageTemplate . $template, 'dbxdocs-cinematic')
        && !is_file($designRoot . '/css/docs-cinematic.css')
        && !is_file($designRoot . '/js/dbxdocs-cinematic.js')
        && !is_file($root . '/dbx/modules/dbxDocs/content/dbxapp_home_cinematic.html'),
    'Die kurze Startseite oder die vollständige Entfernung der Animation ist nicht abgesichert.'
);
docs_portal_assert(
    str_contains($docsAccess, "array('dbxUser', 'dbxUser_groups')")
        && str_contains($docsAccess, 'new dbxInstallationService')
        && str_contains($docsAccess, "patch_local_config('dbxLogin', array('register' => '0'))")
        && str_contains($docsAccess, 'DBX_DOCS_ADMIN_PASSWORD')
        && !str_contains($docsAccess, 'bentox64'),
    'Benutzer-DD, Admin-Seed und ausgeschaltete Registrierung sind nicht reproduzierbar konfiguriert.'
);

docs_portal_assert(str_contains($sectionMenus[0], 'Dokumentation/Einstieg'), 'Deutscher Einstiegsbereich fehlt.');
docs_portal_assert(str_contains($sectionMenus[1], 'Dokumentation/Anwenden'), 'Deutscher Anwendungsbereich fehlt.');
docs_portal_assert(str_contains($sectionMenus[2], 'Dokumentation/Entwickeln'), 'Deutscher Entwicklungsbereich fehlt.');
docs_portal_assert(str_contains($sectionMenus[3], 'Dokumentation/Betrieb'), 'Deutscher Betriebsbereich fehlt.');
foreach (array($menuDe, $menuEn, $menuEs) as $menu) {
    docs_portal_assert(
        str_contains($menu, 'dbx_modul=dbxDocs')
            && str_contains($menu, 'dbx_run1=search')
            && !str_contains($menu, '{dbx:profile_link}')
            && !str_contains($menu, 'dbx_modul=dbxLogin')
            && !str_contains($menu, 'dbx_modul=dbxShop'),
        'Das fokussierte Sprachmenü enthält nicht nur Dokumentationsfunktionen.'
    );
}
foreach ($referenceMenus as $menu) {
    docs_portal_assert(
        str_contains($menu, 'dbx_run2=classes')
            && str_contains($menu, 'dbx_run2=namespaces')
            && str_contains($menu, 'dbx_run2=files')
            && str_contains($menu, 'dbx_run2=examples'),
        'Das horizontale Referenzmenü ist unvollständig.'
    );
}
docs_portal_assert(
    str_contains($pageResolver, "'anwenden' => 'docs-apply'")
        && str_contains($pageResolver, "'entwickeln' => 'docs-develop'")
        && str_contains($pageResolver, "'betrieb' => 'docs-operate'")
        && is_file($designRoot . '/htm/docs-start.htm')
        && is_file($designRoot . '/htm/docs-apply.htm')
        && is_file($designRoot . '/htm/docs-develop.htm')
        && is_file($designRoot . '/htm/docs-operate.htm')
        && is_file($designRoot . '/htm/docs-reference.htm'),
    'Eigene dbx_page-Templates oder die serverseitige Bereichsauflösung fehlen.'
);
docs_portal_assert(
    str_contains($contentMenu, 'resolve_folder_reference')
        && str_contains($contentMenu, 'menu_title')
        && str_contains($contentMenu, 'dbx-cms-menu-folder'),
    'Benannte CMS-Wurzeln, kurze Menütitel oder Untermenüs fehlen.'
);
docs_portal_assert(
    str_contains($docsModule, 'REFERENCE_PAGES')
        && str_contains($docsModule, 'realpath')
        && str_contains($docsModule, 'dbx_run2=window')
        && str_contains($docsModule, '&dbx_window=1')
        && str_contains($docsTemplate, '<iframe')
        && str_contains($docsTemplate, 'class="btn btn-outline-primary dbx-win"')
        && str_contains($docsTemplate, 'href="{reference_tab_url}"')
        && str_contains($docsWindowTemplate, '<iframe')
        && str_contains($docsWindowTemplate, 'href="{reference_portal_url}"')
        && str_contains($docsTemplate, 'sandbox=')
        && str_contains($docsWindowTemplate, 'sandbox='),
    'Die sichere Doxygen-Einbettung im Portal, dbx-Fenster und neuen Tab ist unvollständig.'
);
docs_portal_assert(
    str_contains($docsModule, 'private function search(): string')
        && str_contains($docsModule, 'private function searchPages(string $query): array')
        && str_contains($docsModule, 'private function notFound(): string')
        && str_contains($docsSearchTemplate, 'class="dbxdocs-home-search"')
        && str_contains($docsSearchTemplate, '{search_results}')
        && str_contains($docsNotFoundTemplate, 'Fehler 404')
        && str_contains($htaccess, 'dbx_run1=not_found')
        && str_contains($htaccess, '[R=301,L,NE]'),
    'Volltextsuche, hilfreiche 404-Seite oder Weiterleitung alter Doxygen-URLs fehlen.'
);
foreach (array(
    "'name' => 'Einstieg'",
    "'name' => 'CMS & KI'",
    "'name' => 'Entwickeln'",
    "'name' => 'Betrieb'",
    "'name' => 'Service'",
    'dbxContact]dbx_run1=form',
    'repairDocumentationLinks()',
    'referenceDocumentPermalink',
    'dbxcontent_tutorial_(.+)',
    '2026-08-01-installation-3',
    '2026-08-01-tutorial-installation-3',
    '2026-08-01-selftest-3',
    "'permalink' => 'dokumentation-installation'",
    "'permalink' => 'tutorial-installation'",
    "'permalink' => 'dokumentation-selbsttest'",
    '2026-08-01-ki-design-4',
    '2026-08-01-portal-v5',
    'entwickler|entwickeln|architektur',
    '2026-08-01-dbxform-quickstart-1',
    '2026-08-01-dbxreport-quickstart-1',
    '2026-08-01-area-pages-v1',
    'synchronizeDocumentationMetadata()',
    'Kanonische Seite',
    'dbxForm · Gesamtdokument',
    'dbxReport · Gesamtdokument',
    'ARRAY_FILTER_USE_BOTH',
    '$navigation !== array()',
    "'id,folder,menu_title,sorter'",
) as $needle) {
    docs_portal_assert(
        str_contains($docsProvision, $needle),
        'CMS-Dokumentationsstruktur ist unvollständig: ' . $needle
    );
}
docs_portal_assert(
    is_file($root . '/dbx/modules/dbxDocs/content/dbxapp_user_ki_design.html')
        && is_file($root . '/dbx/modules/dbxDocs/content/dbxapp_home.html')
        && is_file($root . '/dbx/modules/dbxDocs/content/dbxapp_dbxform_quickstart.html')
        && is_file($root . '/dbx/modules/dbxDocs/content/dbxapp_dbxreport_quickstart.html')
        && is_file($root . '/dbx/modules/dbxDocs/content/dbxapp_user_installation.html')
        && is_file($root . '/dbx/modules/dbxDocs/content/tutorial_installation.html')
        && is_file($root . '/dbx/modules/dbxDocs/content/dbxapp_user_selftest.html')
        && is_file($root . '/dbx/modules/dbxDocs/assets/cms-content-tree-open.png'),
    'Eine kuratierte Dokumentationsseite oder ihr CMS-Screenshot fehlt.'
);
docs_portal_assert(
    str_contains($installationContent, 'Die sieben Installationsschritte')
        && str_contains($installationContent, 'PHP 8.2 oder neuer')
        && str_contains($installationContent, 'persönliches Passwort')
        && str_contains($installationContent, 'dokumentation-selbsttest'),
    'Die kuratierte Installationsanleitung ist unvollständig.'
);
docs_portal_assert(
    str_contains($installationTutorial, '<h1>dbxapp Schritt für Schritt installieren</h1>')
        && substr_count($installationTutorial, '<h2>') >= 5
        && substr_count($installationTutorial, '<h3>') >= 5
        && str_contains($installationTutorial, 'class="markdownTable"'),
    'Das Installations-Tutorial besitzt keine belastbare H1/H2/H3-Struktur oder Kontrolltabelle.'
);
docs_portal_assert(
    str_contains($selfTestContent, 'Schnelltest')
        && str_contains($selfTestContent, 'Kompletttest')
        && str_contains($selfTestContent, 'JavaScript ohne Node.js')
        && str_contains($selfTestContent, 'files/sys/selftest')
        && str_contains($selfTestContent, '--profile=full'),
    'Die kuratierte dbxSelfTest-Anleitung ist unvollständig.'
);

docs_portal_assert(
    $doxyfile === '' || (
        str_contains($doxyfile, 'OUTPUT_DIRECTORY       = C:/xampp/htdocs/dbxapp-docs/reference')
        && str_contains($doxyfile, 'HTML_OUTPUT            = current')
        && str_contains($doxyfile, 'docs/doxygen-generated-main.dox')
        && !str_contains($doxyfile, 'docs/doxygen-navigation.dox')
        && !str_contains($doxyfile, 'docs/generated/tutorials')
        && str_contains($doxyfile, 'AUTOLINK_SUPPORT       = NO')
    ),
    'Doxygen muss ausschließlich die generierte Referenz veröffentlichen.'
);
docs_portal_assert(
    str_contains($index, 'session_name(')
        && str_contains($index, '$sessionHost . \'|\' . $sessionPath'),
    'Installationsbezogene Sitzungsisolation fehlt.'
);
docs_portal_assert(
    str_contains($contentDd, "'idx_content_' . \$__dbx_lng_dd . '_folder'"),
    'Sprachabhängige SQLite-Indexnamen sind nicht eindeutig.'
);
docs_portal_assert(
    str_contains($contentDd, "\$field['name']='menu_title';"),
    'Das sprachneutrale DD-Feld für kurze Menütitel fehlt.'
);

echo "OK dbxdocs focused navigation, concise homepage, search, metadata, redirects and embedded reference\n";
