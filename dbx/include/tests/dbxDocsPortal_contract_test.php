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
$template = docs_portal_read($designRoot . '/htm/default.htm');
$layout = docs_portal_read($designRoot . '/css/docs-layout.css');
$docsContent = docs_portal_read($designRoot . '/css/docs-content.css');
$contentCss = docs_portal_read($designRoot . '/css/c-content.css');
$darkSkin = docs_portal_read($designRoot . '/css/skin-dunkel.css');
$javascript = docs_portal_read($designRoot . '/js/dbxdocs.js');
$cinematicJavascript = docs_portal_read($designRoot . '/js/dbxdocs-cinematic.js');
$cinematicCss = docs_portal_read($designRoot . '/css/docs-cinematic.css');
$cinematicContent = docs_portal_read($root . '/dbx/modules/dbxDocs/content/dbxapp_home_cinematic.html');
$menuJavascript = docs_portal_read($root . '/dbx/js/lib/menu.js');
$menuModule = docs_portal_read($root . '/dbx/modules/dbxMenu/dbxMenu.class.php');
$menuDe = docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-main.htm');
$menuEn = docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-main_en.htm');
$menuEs = docs_portal_read($root . '/dbx/modules/dbxMenu/tpl/htm/dbx-docs-main_es.htm');
$doxyfile = docs_portal_read($root . '/Doxyfile');
$index = docs_portal_read($root . '/index.php');
$contentDd = docs_portal_read($root . '/dbx/modules/dbx/dd/dbxContent.dd.inc.php');
$docsModule = docs_portal_read($root . '/dbx/modules/dbxDocs/dbxDocs.class.php');
$docsTemplate = docs_portal_read($root . '/dbx/modules/dbxDocs/tpl/htm/reference.htm');
$docsProvision = docs_portal_read($root . '/dbx/modules/dbxDocs/include/dbxDocsContentProvision.class.php');
$docsMediaProvision = docs_portal_read($root . '/dbx/modules/dbxDocs/tools/update_docs_portal_media_20260728.php');
$docsHomeCinematic = docs_portal_read($root . '/dbx/modules/dbxDocs/tools/update_docs_home_cinematic_20260728.php');
$docsAccess = docs_portal_read($root . '/dbx/modules/dbxDocs/tools/configure_docs_access_20260728.php');
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
    'docs-cinematic.css',
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
    str_contains($template, 'tpl=modul|dbx-docs-main'),
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
        && str_contains($template, 'docs-layout.css?v=8')
        && preg_match('/core\.js\?design=\{dbx:design\}&log=off&v=\d+/', $template) === 1
        && str_contains($menuJavascript, "data-dbx-menu-active-open")
        && str_contains($menuJavascript, 'restoreActivePath($el)')
        && str_contains($menuJavascript, 'function closeBranch($item)')
        && str_contains($menuJavascript, 'closeAllMenus(true)')
        && str_contains($menuJavascript, "\$parent.addClass('is-active-path')")
        && !str_contains($menuJavascript, "\$parent.addClass('is-active is-active-path')")
        && str_contains($layout, '.is-active-path:not(.is-active)')
        && str_contains($menuJavascript, '.dbxLngOpt, .dbx-design-opt, .dbx-design-skin-opt, .dbx-skin-opt')
        && !str_contains(
            $layout,
            '.dbx-menu-item.is-active-path > .dbx-menu-list'
        ),
    'Aktive Dokumentationsordner lassen sich nicht zuverlässig schließen.'
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
    str_contains($template, 'docs-content.css?v=4')
        && str_contains($docsContent, '.dbx-user-nav-icon svg')
        && str_contains($docsContent, '.dbx-ki-area-grid')
        && str_contains($docsContent, '.dbx-update-state-grid')
        && str_contains($docsContent, 'body.dbx-docs .dbx-content-body')
        && str_contains($docsContent, 'padding: clamp(.85rem, 1.4vw, 1.2rem)')
        && str_contains($docsContent, '.dbx-doc-figure')
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
        && str_contains($javascript, 'is-mobile-nav-open'),
    'Persistente Desktop- und mobile Navigation fehlen.'
);
docs_portal_assert(
    str_contains($template, 'docs-cinematic.css?v=9')
        && str_contains($template, 'dbxdocs-cinematic.js?v=4')
        && str_contains($cinematicContent, 'data-duration="84"')
        && str_contains($cinematicContent, 'Aus Anforderungen werden Anwendungen.')
        && str_contains($cinematicContent, 'dbx-cinema-infrastructure')
        && str_contains($cinematicContent, 'dbx-cinema-glints')
        && str_contains($cinematicContent, 'dbx-cinema-shooting-star')
        && !str_contains($cinematicContent, 'dbx-cinema-crawl')
        && !str_contains($cinematicContent, 'dbx-cinema-intro')
        && !str_contains($cinematicContent, 'Wenn starre Einzellösungen')
        && str_contains($cinematicContent, 'Vom Gerät bis ins Rechenzentrum')
        && str_contains($cinematicContent, 'Ton starten')
        && str_contains($cinematicCss, '@keyframes dbxCinemaGlint')
        && str_contains($cinematicCss, '@keyframes dbxCinemaShootingStar')
        && str_contains($cinematicCss, '@keyframes dbxCinemaContentRise')
        && str_contains($cinematicCss, '@keyframes dbxCinemaDeviceIn')
        && str_contains($cinematicCss, '@keyframes dbxCinemaLogoImpact')
        && str_contains($cinematicCss, 'aspect-ratio: 16 / 7.3')
        && str_contains($cinematicCss, 'prefers-reduced-motion')
        && str_contains($cinematicJavascript, 'IntersectionObserver')
        && str_contains($cinematicJavascript, 'createSoundscape')
        && str_contains($cinematicJavascript, 'createDynamicsCompressor')
        && str_contains($cinematicJavascript, 'root.dataset.soundState')
        && str_contains($cinematicJavascript, 'data-cinema-toggle'),
    'Die kompakte, zugängliche 84-Sekunden-Startseitenanimation mit Infrastruktur-Szene, Schlusslogo und Ton ist nicht vollständig eingebunden.'
);
docs_portal_assert(
    str_contains($docsHomeCinematic, "\$contentDd = 'dbx|content_de';")
        && str_contains($docsHomeCinematic, "\$usageDd = 'dbx|dbxMediaUsage';")
        && str_contains($docsHomeCinematic, "array('active' => 0)")
        && str_contains($docsHomeCinematic, 'dbxContentPageCache::invalidateAll()'),
    'Die Animation muss reproduzierbar über dbxDB installiert und die alte Galerie reversibel deaktiviert werden.'
);
docs_portal_assert(
    str_contains($docsAccess, "array('dbxUser', 'dbxUser_groups')")
        && str_contains($docsAccess, 'new dbxInstallationService')
        && str_contains($docsAccess, "patch_local_config('dbxLogin', array('register' => '0'))")
        && str_contains($docsAccess, 'DBX_DOCS_ADMIN_PASSWORD')
        && !str_contains($docsAccess, 'bentox64'),
    'Benutzer-DD, Admin-Seed und ausgeschaltete Registrierung sind nicht reproduzierbar konfiguriert.'
);

docs_portal_assert(str_contains($menuDe, '[cms:root=Dokumentation&flat=1]'), 'Deutscher Dokumentationsbaum fehlt.');
docs_portal_assert(str_contains($menuEn, '[cms:root=Documentation&flat=1]'), 'Englischer Dokumentationsbaum fehlt.');
docs_portal_assert(str_contains($menuEs, '[cms:root=Documentación&flat=1]'), 'Spanischer Dokumentationsbaum fehlt.');
foreach (array($menuDe, $menuEn, $menuEs) as $menu) {
    docs_portal_assert(
        str_contains($menu, 'dbx_modul=dbxDocs')
            && str_contains($menu, 'dbx_run2=classes')
            && str_contains($menu, 'dbx_run2=namespaces')
            && str_contains($menu, 'dbx_run2=files')
            && str_contains($menu, 'dbx_run2=examples'),
        'Generierte Doxygen-Bereiche fehlen in einem Sprachmenü.'
    );
}
docs_portal_assert(
    str_contains($contentMenu, 'resolve_folder_reference')
        && str_contains($contentMenu, 'menu_title')
        && str_contains($contentMenu, 'dbx-cms-menu-folder'),
    'Benannte CMS-Wurzeln, kurze Menütitel oder Untermenüs fehlen.'
);
docs_portal_assert(
    str_contains($docsModule, 'REFERENCE_PAGES')
        && str_contains($docsModule, 'realpath')
        && str_contains($docsTemplate, '<iframe')
        && str_contains($docsTemplate, 'sandbox='),
    'Die sichere, eingebettete Doxygen-Referenz ist unvollständig.'
);
foreach (array(
    "'name' => 'Einstieg'",
    "'name' => 'Content & KI'",
    "'name' => 'Entwicklung'",
    "'name' => 'Betrieb & Sicherheit'",
    "'name' => 'Service'",
    'dbxContact]dbx_run1=form',
    'repairDocumentationLinks()',
    'referenceDocumentPermalink',
    'dbxcontent_tutorial_(.+)',
    '2026-07-28-ki-design-2',
) as $needle) {
    docs_portal_assert(
        str_contains($docsProvision, $needle),
        'CMS-Dokumentationsstruktur ist unvollständig: ' . $needle
    );
}
docs_portal_assert(
    is_file($root . '/dbx/modules/dbxDocs/content/dbxapp_user_ki_design.html')
        && is_file($root . '/dbx/modules/dbxDocs/assets/cms-content-tree-open.png'),
    'Die ausführliche Design-KI-Seite oder ihr CMS-Screenshot fehlt.'
);

docs_portal_assert(
    str_contains($doxyfile, 'OUTPUT_DIRECTORY       = C:/xampp/htdocs/dbxapp-docs/reference')
        && str_contains($doxyfile, 'HTML_OUTPUT            = current')
        && str_contains($doxyfile, 'docs/doxygen-generated-main.dox')
        && !str_contains($doxyfile, 'docs/doxygen-navigation.dox')
        && !str_contains($doxyfile, 'docs/generated/tutorials'),
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

echo "OK dbxdocs CMS navigation, access, cinematic homepage and embedded reference\n";
