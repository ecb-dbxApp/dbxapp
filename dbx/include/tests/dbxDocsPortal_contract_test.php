<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once __DIR__ . '/dbxCssTestReader.php';

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
$contentCss = dbx_test_read_css($designRoot . '/css/c-content.css');
$dbxappTemplate = docs_portal_read($root . '/dbx/design/dbxapp/htm/default.htm');
$dbxappWindowTemplate = docs_portal_read($root . '/dbx/design/dbxapp/htm/_window.htm');
$dbxappDocsContent = docs_portal_read($root . '/dbx/design/dbxapp/css/docs-content.css');
$javascript = docs_portal_read($designRoot . '/js/dbxdocs.js');
$homeContent = docs_portal_read($root . '/dbx/modules/dbxDocs/content/dbxapp_home.html');
$installationContent = docs_portal_read($root . '/dbx/modules/dbxDocs/content/dbxapp_user_installation.html');
$installationTutorial = docs_portal_read($root . '/dbx/modules/dbxDocs/content/tutorial_installation.html');
$selfTestContent = docs_portal_read($root . '/dbx/modules/dbxDocs/content/dbxapp_user_selftest.html');
$menuJavascript = docs_portal_read($root . '/dbx/js/lib/menu.js');
$menuModule = docs_portal_read($root . '/dbx/modules/dbxMenu/dbxMenu.class.php');
$webApp = docs_portal_read($root . '/dbx/include/dbxWebApp.class.php');
$tplEngine = docs_portal_read($root . '/dbx/include/dbxTPL.class.php');
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
$contentRenderer = docs_portal_read($root . '/dbx/modules/dbxContent/include/dbxContentRenderer.class.php');
$documentationContentTemplate = docs_portal_read($root . '/dbx/modules/dbxContent/tpl/htm/c-doku.htm');
$docsAccess = docs_portal_read($root . '/dbx/modules/dbxDocs/tools/configure_docs_access_20260728.php');
$htaccess = docs_portal_read($root . '/.htaccess');
$contentMenu = docs_portal_read($root . '/dbx/modules/dbxMenu/include/dbxContent_menu.class.php');
$contentPermalink = docs_portal_read($root . '/dbx/modules/dbxContent/include/dbxContent_permalink.class.php');
$contentSitemap = docs_portal_read($root . '/dbx/modules/dbxContent/include/dbxContentSitemap.class.php');
docs_portal_assert(
    is_file($root . '/dbx/modules/dbxContent/tpl/htm/c-content.htm'),
    'Das sprachunabhängige Standard-Content-Template c-content.htm fehlt.'
);

foreach (array(
    'base.css',
    'c-form.css',
    'c-report.css',
    'c-menu.css',
    'skin-hell.css',
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
        && str_contains($template, 'docs-layout.css?v={dbx:asset_version}')
        && str_contains($template, 'dbxdocs.js?v={dbx:asset_version}')
        && str_contains($template, 'core.js?design={dbx:design}&log=off&v={dbx:asset_version}')
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
    str_contains($layout, '#dbxHeader.dbxdocs-sidebar .dbx-menu .dbx-menu-item > .dbx-menu-list')
        && str_contains($layout, 'background: #061d3d !important;')
        && str_contains($layout, '.dbx-menu-link.is-active')
        && str_contains($layout, 'background: linear-gradient(135deg, #0b6fc7, #0752aa);'),
    'Sprach-, Design- und Skin-Auswahl benötigen kontrastreiche Untermenüs und einen eindeutigen Aktivzustand.'
);
docs_portal_assert(
    str_contains($menuModule, "return 'dbxdocs';")
        && str_contains($menuModule, '$this->render_design_menu($baseSelf, $activeDesign)')
        && str_contains($menuModule, 'foreach ($this->frontend_design_options() as $design => $designLabel)')
        && !str_contains($menuModule, "array('dbxdocs' => 'dbxapp (Blau)')")
        && !str_contains($menuModule, 'array_intersect_key($options'),
    'Die Dokumentationsnavigation muss alle installierten Designs anbieten.'
);
docs_portal_assert(
    str_contains($template, 'docs-content.css?v={dbx:asset_version}')
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
    str_contains($dbxappTemplate, 'dbx/design/dbxapp/css/docs-content.css?v={dbx:asset_version}')
        && str_contains($dbxappWindowTemplate, 'dbx/design/dbxapp/css/docs-content.css?v={dbx:asset_version}')
        && str_contains($dbxappDocsContent, '.dbxdocs-home-grid')
        && str_contains($dbxappDocsContent, '.dbxdocs-search-result')
        && str_contains($dbxappDocsContent, '.dbx-documentation-page > .dbx-doc-meta')
        && str_contains($dbxappDocsContent, '.dbxdocs-reference-frame')
        && str_contains($dbxappDocsContent, 'var(--dbx-primary, #0d6efd)'),
    'Die Dokumentation muss im dbxapp-Design und in dbx-Fenstern dessen Designvariablen verwenden.'
);
docs_portal_assert(
    str_contains($contentCss, 'grid-template-columns: repeat(5, minmax(0, 1fr));')
        && str_contains($contentCss, 'grid-template-columns: repeat(3, minmax(0, 1fr));')
        && str_contains($contentCss, 'grid-template-columns: repeat(2, minmax(0, 1fr));')
        && str_contains($docsContent, 'box-shadow: inset 0 3px 0 #0b75d1;')
        && str_contains($docsContent, 'overflow-wrap: anywhere;'),
    'Der Dokumentstatus muss die volle Breite nutzen und responsiv lesbar bleiben.'
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
    str_contains($homeContent, 'data-dbx-doc-revision="2026-08-03-portal-v6"')
        && str_contains($homeContent, 'Wissen aus einer Quelle')
        && !str_contains($homeContent, 'Version 4.')
        && str_contains($homeContent, '<h1>Was möchten Sie erreichen?</h1>')
        && str_contains($homeContent, 'Vier klare Arbeitsbereiche')
        && str_contains($homeContent, 'dokumentation-einstieg')
        && str_contains($homeContent, 'dokumentation-betrieb')
        && str_contains($homeContent, 'dokumentation-entwickeln')
        && str_contains($homeContent, 'dokumentation-ki-agenten')
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
    str_contains($pageShellStage, 'href="{dbx:docs_return_url}"')
        && str_contains($pageShellStage, 'Zurück zu dbXapp')
        && str_contains($pageShellContent, 'href="{dbx:docs_return_url}"')
        && str_contains($webApp, "'dbx_docs_return_design'")
        && str_contains($webApp, "'dbx_docs_return_color'")
        && str_contains($webApp, 'documentation_return_url()')
        && str_contains($tplEngine, "'{dbx:docs_return_url}'"),
    'Der validierte Rückweg aus dbxdocs zum zuvor aktiven Design/Skin fehlt.'
);
docs_portal_assert(
    str_contains($pageShellHead, '<base href="{dbx:base_href}">')
        && str_contains($pageShellHead, 'href="{dbx:docs_home_url}"')
        && str_contains($contentPermalink, "return 'dokumentation/' . \$permalink;")
        && str_contains($contentPermalink, "str_starts_with(\$legacy, 'dokumentation/')")
        && str_contains($contentSitemap, 'dbxContent_permalink::publicPath')
        && str_contains($htaccess, 'doku\\.dbxapp\\.de')
        && str_contains($htaccess, 'https://dbxapp.de/dokumentation/$1'),
    'Der kanonische Dokumentationsbereich unter /dokumentation/ oder die seitenweise Subdomain-Weiterleitung fehlt.'
);
docs_portal_assert(
    str_contains($docsAccess, "array('dbxUser', 'dbxUser_groups')")
        && str_contains($docsAccess, 'new dbxInstallationService')
        && str_contains($docsAccess, "patch_local_config('dbxLogin', array('register' => '0'))")
        && str_contains($docsAccess, 'DBX_DOCS_ADMIN_PASSWORD')
        && !str_contains($docsAccess, 'bentox64'),
    'Benutzer-DD, Admin-Seed und ausgeschaltete Registrierung sind nicht reproduzierbar konfiguriert.'
);

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
        && str_contains($layout, '#dbxContent:has(> .dbxdocs-reference)')
        && str_contains($layout, 'max-width: none;')
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
    "'name' => 'Anwender'",
    "'name' => 'CMS & KI'",
    "'name' => 'Entwickler'",
    "'name' => 'Administratoren'",
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
    '2026-08-03-portal-v6',
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
    $haystack = in_array($needle, array('entwickler|entwickeln|architektur', 'Kanonische Seite'), true)
        ? $docsProvision . $contentRenderer . $documentationContentTemplate
        : $docsProvision;
    docs_portal_assert(
        str_contains($haystack, $needle),
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
        str_contains($doxyfile, 'OUTPUT_DIRECTORY       = reference')
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
