<?php
declare(strict_types=1);

/**
 * Aktualisiert die drei Portalbilder der dbxapp-Dokumentation.
 *
 * Ausführung im Wurzelverzeichnis der Dokumentationsinstallation:
 * php dbx/modules/dbxDocs/tools/update_docs_portal_media_20260728.php
 *
 * Alle Datenbankzugriffe erfolgen über dbxDB und die vorhandenen DDs.
 * Die bestehenden Medien-IDs bleiben erhalten, damit CMS-Zuordnungen und
 * Permalinks unverändert weiterarbeiten.
 */

$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp-docs/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp-docs/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';
require_once $base . '/dbx/modules/dbxContent/include/dbxContent_bootstrap_sync.php';

use dbx\dbxContent\dbxContentPageCache;

$db = dbx()->get_system_obj('dbxDB');
$mediaDd = 'dbx|dbxMedia';
$usageDd = 'dbx|dbxMediaUsage';

$media = array(
    119 => array(
        'title' => 'dbxapp – eine Plattform für Inhalte, Shop und Anwendungen',
        'alt' => 'Vernetzte dbxapp-Plattform für CMS, Shop, Anwendungen, Workflows und KI',
        'caption' => 'Eine Plattform für Inhalte, Shop und Anwendungen',
        'file_name' => 'dbxapp-kurzfilm-poster-20260728.webp',
        'file_path' => 'media/img/images/dbxapp-kurzfilm-poster-20260728.webp',
        'tags' => 'dbxapp, plattform, cms, shop, anwendungen, ki',
    ),
    120 => array(
        'title' => 'Bestehende Systeme neu denken und optimieren',
        'alt' => 'Getrennte Altsysteme werden als klare modulare dbxapp-Lösung neu aufgebaut',
        'caption' => 'Bestehende Systeme lassen sich leicht nachbilden und optimieren',
        'file_name' => 'dbxapp-systeme-neu-denken-20260728.webp',
        'file_path' => 'media/img/images/dbxapp-systeme-neu-denken-20260728.webp',
        'tags' => 'dbxapp, systeme, modernisierung, optimierung',
    ),
    121 => array(
        'title' => 'Mit dbxapp modular und sicher wachsen',
        'alt' => 'Modulare dbxapp-Plattform mit CMS, Shop, Apps, Workflows und Sicherheit',
        'caption' => 'Modular starten und sicher wachsen',
        'file_name' => 'dbxapp-modular-wachsen-20260728.webp',
        'file_path' => 'media/img/images/dbxapp-modular-wachsen-20260728.webp',
        'tags' => 'dbxapp, module, wachstum, sicherheit',
    ),
);

$updatedMedia = 0;
$updatedUsages = 0;

foreach ($media as $id => $record) {
    $file = $base . '/files/' . $record['file_path'];
    if (!is_file($file)) {
        throw new RuntimeException('Dokumentationsmedium fehlt: ' . $file);
    }

    $image = getimagesize($file);
    if (!is_array($image) || (int)($image[0] ?? 0) <= 0 || (int)($image[1] ?? 0) <= 0) {
        throw new RuntimeException('Ungültiges Dokumentationsmedium: ' . $file);
    }

    $record += array(
        'active' => 1,
        'slot' => 'gallery',
        'usage' => 'gallery',
        'mime' => 'image/webp',
        'size' => filesize($file),
        'width' => (int)$image[0],
        'height' => (int)$image[1],
        'thumb_file_path' => '',
        'thumb_width' => 0,
        'thumb_height' => 0,
        'media_type' => 'image',
        'storage_type' => 'local',
        'media_folder' => 'img/images',
    );

    if ($db->update($mediaDd, $record, $id, 0, 1, 0, 1) !== 1) {
        throw new RuntimeException('Dokumentationsmedium #' . $id . ' konnte nicht aktualisiert werden.');
    }
    $updatedMedia++;

    $usages = $db->select(
        $usageDd,
        array('media_id' => $id),
        'id,caption',
        'id',
        'ASC',
        '',
        0,
        0,
        0
    );
    foreach (is_array($usages) ? $usages : array() as $usage) {
        $usageId = (int)($usage['id'] ?? 0);
        if ($usageId <= 0) {
            continue;
        }
        if ($db->update(
            $usageDd,
            array('caption' => $record['caption']),
            $usageId,
            0,
            1,
            0,
            1
        ) !== 1) {
            throw new RuntimeException('Medienzuordnung #' . $usageId . ' konnte nicht aktualisiert werden.');
        }
        $updatedUsages++;
    }
}

dbxContentPageCache::invalidateAll();

echo json_encode(array(
    'ok' => true,
    'media_updated' => $updatedMedia,
    'usages_updated' => $updatedUsages,
    'cache_invalidated' => true,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
