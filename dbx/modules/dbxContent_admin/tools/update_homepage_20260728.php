<?php
declare(strict_types=1);

/**
 * Applies the concise German homepage and registers its new media.
 *
 * All database reads and writes intentionally use dbxDB with DD references.
 * Existing media IDs 166, 371 and 432 are retained so older CMS content keeps
 * working while receiving the new visual assets.
 */

$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';

$db = dbx()->get_system_obj('dbxDB');
$dd = dbx()->get_system_obj('dbxDD');
$mediaDd = 'dbx|dbxMedia';
$mediaUsageDd = 'dbx|dbxMediaUsage';
$contentDd = 'dbx|content_de';

$dd->sync_dd_to_db('dbx', 'content_de', 'reset');
for ($step = 0; $step < 1000; $step++) {
    $schema = $dd->sync_dd_to_db('dbx', 'content_de', 'apply');
    if (in_array((string)($schema['status'] ?? ''), array('finished', 'error', 'cancelled'), true)) {
        break;
    }
}
if (($schema['status'] ?? '') !== 'finished') {
    throw new RuntimeException(
        'Die Content-DD konnte nicht synchronisiert werden: '
        . (string)($schema['message'] ?? $schema['status'] ?? 'unbekannt')
    );
}

$media = array(
    166 => array(
        'title' => 'dbxapp – eine Plattform für Inhalte, Shop und Anwendungen',
        'alt' => 'Vernetzte dbxapp Plattform für CMS, Shop und Geschäftsanwendungen',
        'file_name' => 'dbxapp-platform-hero-20260728.webp',
        'file_path' => 'media/img/hero/dbxapp-platform-hero-20260728.webp',
        'mime' => 'image/webp',
        'width' => 2560,
        'height' => 640,
        'media_type' => 'image',
        'media_folder' => 'hero',
        'slot' => 'hero',
        'usage' => 'hero',
    ),
    371 => array(
        'title' => 'Bestehende Systeme neu denken und optimieren',
        'alt' => 'Getrennte Altsysteme werden als klare modulare dbxapp Lösung neu aufgebaut',
        'file_name' => 'dbxapp-systeme-neu-denken-20260728.webp',
        'file_path' => 'media/img/images/dbxapp-systeme-neu-denken-20260728.webp',
        'mime' => 'image/webp',
        'width' => 1600,
        'height' => 900,
        'media_type' => 'image',
        'media_folder' => 'images',
        'slot' => 'inline',
        'usage' => 'inline',
    ),
    432 => array(
        'title' => 'Mit dbxapp modular und sicher wachsen',
        'alt' => 'Modulare dbxapp Plattform mit CMS, Shop, Apps, Workflows und Sicherheit',
        'file_name' => 'dbxapp-modular-wachsen-20260728.webp',
        'file_path' => 'media/img/images/dbxapp-modular-wachsen-20260728.webp',
        'mime' => 'image/webp',
        'width' => 1600,
        'height' => 900,
        'media_type' => 'image',
        'media_folder' => 'images',
        'slot' => 'inline',
        'usage' => 'inline',
    ),
);

foreach ($media as $id => $record) {
    $file = $base . '/files/' . $record['file_path'];
    if (!is_file($file)) {
        throw new RuntimeException('Homepage-Medium fehlt: ' . $file);
    }
    $record['active'] = 1;
    $record['storage_type'] = 'local';
    $record['size'] = filesize($file);
    if ($db->update($mediaDd, $record, $id, 0, 1, 0, 1) !== 1) {
        throw new RuntimeException('Medium #' . $id . ' konnte nicht aktualisiert werden.');
    }
}

$upsertMedia = static function (array $record) use ($db, $mediaDd, $base): int {
    $file = $base . '/files/' . $record['file_path'];
    if (!is_file($file)) {
        throw new RuntimeException('Homepage-Medium fehlt: ' . $file);
    }
    $record['active'] = 1;
    $record['content_id'] = 0;
    $record['folder_id'] = 0;
    $record['storage_type'] = 'local';
    $record['size'] = filesize($file);
    $rows = $db->select(
        $mediaDd,
        array('file_path' => $record['file_path']),
        'id',
        'id',
        'ASC',
        '',
        1,
        0,
        0
    );
    $id = is_array($rows) && isset($rows[0]['id']) ? (int)$rows[0]['id'] : 0;
    if ($id > 0) {
        if ($db->update($mediaDd, $record, $id, 0, 1, 0, 1) !== 1) {
            throw new RuntimeException('Medium #' . $id . ' konnte nicht aktualisiert werden.');
        }
        return $id;
    }
    if ($db->insert($mediaDd, $record, 0, 1, 0, 1) !== 1) {
        throw new RuntimeException('Homepage-Medium konnte nicht angelegt werden.');
    }
    return (int)$db->get_insert_id();
};

$posterId = $upsertMedia(array(
    'slot' => 'inline',
    'usage' => 'inline',
    'sorter' => '0000',
    'template' => '',
    'title' => 'dbxapp TV-Spot – bewegtere Fassung',
    'alt' => 'Formatfüllendes dbxapp Original-Logo als Schlussbild des 30-Sekunden-Spots',
    'file_name' => 'dbxapp-tvspot-poster-20260730-v3.webp',
    'file_path' => 'media/img/images/dbxapp-tvspot-poster-20260730-v3.webp',
    'mime' => 'image/webp',
    'width' => 1024,
    'height' => 576,
    'media_type' => 'image',
    'media_folder' => 'images',
));

$videoId = $upsertMedia(array(
    'slot' => 'inline',
    'usage' => 'inline',
    'sorter' => '0000',
    'template' => '',
    'title' => 'dbxapp TV-Spot – bewegtere Fassung',
    'alt' => 'Dynamischer TV-Spot über Handy, Desktop, KI, CMS, Shop und Datenbanken mit dbxapp',
    'file_name' => 'dbxapp-tvspot-20260730-v3.mp4',
    'file_path' => 'media/video/dbxapp-tvspot-20260730-v3.mp4',
    'thumb_file_path' => 'media/img/images/dbxapp-tvspot-poster-20260730-v3.webp',
    'mime' => 'video/mp4',
    'width' => 1024,
    'height' => 576,
    'media_type' => 'video',
    'media_folder' => 'video',
));

$pages = $db->select(
    $contentDd,
    array('permalink' => 'home'),
    '*',
    'id',
    'ASC',
    '',
    1,
    0,
    0
);
$pageId = is_array($pages) && isset($pages[0]['id']) ? (int)$pages[0]['id'] : 0;
if ($pageId <= 0) {
    throw new RuntimeException('Die deutsche Startseite mit Permalink "home" wurde nicht gefunden.');
}

$content = <<<'HTML'
<div class="dbx-home-hero-copy text-white">
  <p class="text-uppercase fw-semibold mb-2">Eine Plattform. Klare Abläufe.</p>
  <h2 class="display-5 fw-bold text-white mb-3">Website, Shop und Anwendungen aus einem Kern.</h2>
  <p class="lead mb-4">dbxapp verbindet Inhalte, Verkauf, Daten und individuelle Prozesse – modular, sicher und passend zu Ihrem Unternehmen.</p>
  <a class="btn btn-primary btn-lg me-2 mb-2" href="demo">Demo ansehen</a>
  <a class="btn btn-outline-light btn-lg mb-2" href="kontakt">Projekt besprechen</a>
</div>
<hr class="dbx-cms-marker dbx-cms-marker-hero" contenteditable="false" data-dbx-marker="dbx:hero" data-label="Hero">

<div class="row g-4 mb-5">
  <div class="col-12">
    <p class="text-uppercase fw-semibold text-primary mb-2">30 Sekunden dbxapp</p>
    <h2>Eine Plattform in Bewegung.</h2>
    <p>Handy, Desktop, KI, CMS, aktiver Shop und Datenbanken in einem noch schnelleren Schnitt – die Lösung: dbxapp. Ton kann direkt im Player aktiviert werden.</p>
    <div class="d-flex flex-wrap gap-2">
      <span class="badge text-bg-primary">Handy</span>
      <span class="badge text-bg-primary">Desktop</span>
      <span class="badge text-bg-primary">KI</span>
      <span class="badge text-bg-primary">CMS</span>
      <span class="badge text-bg-primary">Shop</span>
      <span class="badge text-bg-primary">Datenbank</span>
    </div>
  </div>
  <div class="col-12">
    <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow">
      <figure class="dbx-cms-inline-media dbx-cms-inline-video-block" data-cms-media-id="{VIDEO_ID}" data-cms-media-slot="inline" data-cms-video-url="index.php?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid={VIDEO_ID}" data-cms-video-type="video" data-cms-video-mime="video/mp4" data-cms-video-autoplay="0" data-cms-video-loop="0" data-cms-video-muted="0">
        <img class="dbx-cms-inline-video-thumb" src="index.php?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid={POSTER_ID}" alt="dbxapp TV-Spot" title="dbxapp TV-Spot" data-cms-media-id="{VIDEO_ID}" data-cms-media-slot="inline">
        <span class="dbx-cms-inline-video-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>
      </figure>
    </div>
  </div>
</div>

<div class="row g-3 mb-5">
  <div class="col-sm-6 col-xl-3"><a class="card h-100 text-decoration-none shadow-sm" href="cms-website"><div class="card-body"><i class="bi bi-file-earmark-richtext fs-2 text-primary"></i><h2 class="h5 mt-3">Website &amp; CMS</h2><p class="text-body-secondary mb-0">Seiten, Medien, Designs und Sprachen einfach pflegen.</p></div></a></div>
  <div class="col-sm-6 col-xl-3"><a class="card h-100 text-decoration-none shadow-sm" href="shop-multichannel"><div class="card-body"><i class="bi bi-bag-check fs-2 text-primary"></i><h2 class="h5 mt-3">Shop &amp; Verkauf</h2><p class="text-body-secondary mb-0">Produkte, Bestellungen und Kanäle klar organisieren.</p></div></a></div>
  <div class="col-sm-6 col-xl-3"><a class="card h-100 text-decoration-none shadow-sm" href="individuelle-anwendungen"><div class="card-body"><i class="bi bi-grid-1x2 fs-2 text-primary"></i><h2 class="h5 mt-3">Individuelle Apps</h2><p class="text-body-secondary mb-0">Formulare, Reports und Workflows passend zur Aufgabe.</p></div></a></div>
  <div class="col-sm-6 col-xl-3"><a class="card h-100 text-decoration-none shadow-sm" href="intranet-portale"><div class="card-body"><i class="bi bi-people fs-2 text-primary"></i><h2 class="h5 mt-3">Portale &amp; Intranet</h2><p class="text-body-secondary mb-0">Geschützte Lösungen für Teams, Kunden und Partner.</p></div></a></div>
</div>

<div class="row g-4 mb-5">
  <div class="col-lg-6">
    <article class="card h-100 overflow-hidden shadow-sm">
      <img class="card-img-top" src="index.php?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid=371" alt="Bestehende Systeme werden neu gedacht" loading="lazy">
      <div class="card-body">
        <h2 class="h4">Bestehende Systeme besser aufbauen.</h2>
        <p class="mb-0"><strong>Bestehende Systeme lassen sich leicht nachbilden und optimieren.</strong> Bewährte Funktionen bleiben erhalten, während Datenmodelle, Bedienung und Abläufe klarer und einheitlicher werden.</p>
      </div>
    </article>
  </div>
  <div class="col-lg-6">
    <article class="card h-100 overflow-hidden shadow-sm">
      <img class="card-img-top" src="index.php?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid=432" alt="dbxapp wächst modular mit den Anforderungen" loading="lazy">
      <div class="card-body">
        <h2 class="h4">Klein starten. Sicher wachsen.</h2>
        <p class="mb-0">Ein gemeinsamer Kern für Benutzer, Rechte, Daten, Formulare, Reports und Templates. Neue Module ergänzen die Lösung, ohne ein neues Inselsystem zu schaffen.</p>
      </div>
    </article>
  </div>
</div>

<div class="alert alert-primary shadow-sm d-lg-flex align-items-center justify-content-between gap-4 mb-0">
  <div><h2 class="h4 mb-1">Passt dbxapp zu Ihrem Vorhaben?</h2><p class="mb-lg-0">Als Full Service, selbst betrieben, im Intranet oder auf eigener Infrastruktur.</p></div>
  <div class="text-nowrap mt-3 mt-lg-0"><a class="btn btn-primary me-2" href="demo">Demo anfordern</a><a class="btn btn-outline-primary" href="kontakt">Beratung</a></div>
</div>
HTML;

$content = str_replace(
    array('{POSTER_ID}', '{VIDEO_ID}'),
    array((string)$posterId, (string)$videoId),
    $content
);

$updated = $db->update($contentDd, array(
    'title' => 'dbxapp – CMS, Shop und Anwendungen',
    'menu_title' => 'Home',
    'seo_title' => 'dbxapp: CMS, Shop und individuelle Anwendungen',
    'description' => 'dbxapp verbindet Website und CMS, Shop, Portale und individuelle Geschäftsanwendungen in einer modularen Plattform.',
    'keywords' => 'dbxapp, CMS, Shop, Geschäftsanwendung, Portal, Workflow, Self-Hosting',
    'template' => 'c-title-hero_header-body1-footer',
    'content' => $content,
    'hero_template' => 'image-hero',
    'hero_image_id' => '166',
    'hero_margin_top' => '0',
    'hero_height' => '420px',
    'hero_variant' => 'original',
    'hero_sticky' => '0',
    'hero_scroll_layer' => 'under',
    'seo_image_id' => 166,
    'lng_rev' => (int)($pages[0]['lng_rev'] ?? 0) + 1,
), $pageId, 0, 1, 0, 1);

if ($updated !== 1) {
    throw new RuntimeException('Die deutsche Startseite konnte nicht aktualisiert werden (Rückgabe '
        . var_export($updated, true) . ').');
}

// Die Medienzuordnung spiegelt verbindlich den gerenderten Seitenaufbau:
// Hero getrennt vom HTML, Inline-Medien ausschließlich aus dem Content.
$wantedUsage = array(
    'hero' => array(166),
    'inline' => array($videoId, 371, 432),
);
$activeUsage = $db->select(
    $mediaUsageDd,
    'content_id = ' . $pageId . ' AND active = 1',
    '*',
    'sorter,id',
    'ASC',
    '',
    0,
    0,
    0
);
$kept = array('hero' => array(), 'inline' => array());
foreach (is_array($activeUsage) ? $activeUsage : array() as $usage) {
    $slot = (string)($usage['slot'] ?? '');
    if (!array_key_exists($slot, $wantedUsage)) {
        continue;
    }
    $mediaId = (int)($usage['media_id'] ?? 0);
    if (in_array($mediaId, $wantedUsage[$slot], true) && empty($kept[$slot][$mediaId])) {
        $kept[$slot][$mediaId] = true;
        continue;
    }
    $usageId = (int)($usage['id'] ?? 0);
    if ($usageId > 0 && $db->update($mediaUsageDd, array('active' => 0), $usageId, 0, 1, 0, 1) !== 1) {
        throw new RuntimeException('Veraltete Medienzuordnung #' . $usageId . ' konnte nicht deaktiviert werden.');
    }
}
foreach ($wantedUsage as $slot => $mediaIds) {
    foreach (array_values(array_unique(array_map('intval', $mediaIds))) as $position => $mediaId) {
        if ($mediaId <= 0 || !empty($kept[$slot][$mediaId])) {
            continue;
        }
        $usage = array(
            'active' => 1,
            'media_id' => $mediaId,
            'content_id' => $pageId,
            'folder_id' => 0,
            'slot' => $slot,
            'sorter' => str_pad((string)$position, 4, '0', STR_PAD_LEFT),
            'template' => '',
            'caption' => '',
            'settings' => '',
        );
        if ($db->insert($mediaUsageDd, $usage, 0, 1, 0, 1) !== 1) {
            throw new RuntimeException('Medienzuordnung für #' . $mediaId . ' (' . $slot . ') konnte nicht angelegt werden.');
        }
    }
}

dbx()->load_content_cache_classes();
\dbx\dbxContent\dbxContentPageCache::invalidateContent($pageId);
\dbx\dbxContent\dbxContentPageCache::invalidateAllMenus();
if (class_exists(\dbx\dbxContent\dbxContentSitemap::class)) {
    \dbx\dbxContent\dbxContentSitemap::invalidate();
}

echo json_encode(array(
    'ok' => true,
    'page_id' => $pageId,
    'poster_id' => $posterId,
    'video_id' => $videoId,
    'media_replaced' => array_keys($media),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
