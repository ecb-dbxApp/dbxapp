<?php
declare(strict_types=1);

/**
 * Installiert die 84-Sekunden-Animation auf der Startseite des Doku-Portals.
 *
 * Die drei bisherigen Galerie-Zuordnungen werden nur deaktiviert. Medien und
 * Zuordnungen bleiben erhalten und können im CMS später wieder aktiviert
 * werden. Alle Datenbankzugriffe erfolgen ausschließlich über dbxDB und DDs.
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
$contentDd = 'dbx|content_de';
$usageDd = 'dbx|dbxMediaUsage';
$cinematicFile = $base . '/dbx/modules/dbxDocs/content/dbxapp_home_cinematic.html';
$cinematic = is_file($cinematicFile) ? trim((string)file_get_contents($cinematicFile)) : '';

if ($cinematic === '' || !str_contains($cinematic, 'data-dbx-cinema')) {
    throw new RuntimeException('Die gebündelte Startseitenanimation fehlt oder ist ungültig.');
}

$page = $db->select1(
    $contentDd,
    array('permalink' => 'tutorials-dbxapp'),
    array('id', 'permalink', 'content'),
    0
);
$pageId = (int)($page['id'] ?? 0);
if ($pageId <= 0) {
    throw new RuntimeException('Die Dokumentations-Startseite tutorials-dbxapp wurde nicht gefunden.');
}

$content = (string)($page['content'] ?? '');
$content = preg_replace(
    '#<!--\s*dbxdocs-cinematic:start:[^>]+-->.*?<!--\s*dbxdocs-cinematic:end\s*-->#is',
    '',
    $content
);
$content = $cinematic . "\n\n" . trim((string)$content);

if ($db->update(
    $contentDd,
    array('content' => $content),
    $pageId,
    0,
    1,
    0,
    1
) !== 1) {
    throw new RuntimeException('Die Dokumentations-Startseite konnte nicht aktualisiert werden.');
}

$disabled = 0;
$usages = $db->select(
    $usageDd,
    array('content_id' => $pageId, 'slot' => 'gallery'),
    'id,media_id,active',
    'id',
    'ASC',
    '',
    0,
    0,
    0
);

foreach (is_array($usages) ? $usages : array() as $usage) {
    $usageId = (int)($usage['id'] ?? 0);
    $mediaId = (int)($usage['media_id'] ?? 0);
    if ($usageId <= 0
        || !in_array($mediaId, array(119, 120, 121), true)
        || (int)($usage['active'] ?? 0) === 0
    ) {
        continue;
    }
    if ($db->update(
        $usageDd,
        array('active' => 0),
        $usageId,
        0,
        1,
        0,
        1
    ) !== 1) {
        throw new RuntimeException('Galerie-Zuordnung #' . $usageId . ' konnte nicht deaktiviert werden.');
    }
    $disabled++;
}

dbxContentPageCache::invalidateAll();

echo json_encode(array(
    'ok' => true,
    'content_id' => $pageId,
    'permalink' => (string)($page['permalink'] ?? ''),
    'cinematic_seconds' => 84,
    'gallery_usages_disabled' => $disabled,
    'cache_invalidated' => true,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
