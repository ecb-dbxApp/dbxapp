<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 4);
$dbFile = $repoRoot . '/dbx/modules/dbx/db/dbxMedia.db3';
$videoFile = $repoRoot . '/files/media/video/dbxapp-tvspot-20260731-v4.mp4';
$posterFile = $repoRoot . '/files/media/img/images/dbxapp-tvspot-poster-20260731-v4.webp';
$backupDir = $repoRoot . '/files/tmp';
$mediaId = 442;

foreach (array($dbFile, $videoFile, $posterFile) as $requiredFile) {
    if (!is_file($requiredFile)) {
        fwrite(STDERR, 'Pflichtdatei fehlt: ' . $requiredFile . PHP_EOL);
        exit(1);
    }
}

$posterInfo = @getimagesize($posterFile);
if (!is_array($posterInfo) || (int)($posterInfo[0] ?? 0) !== 1024 || (int)($posterInfo[1] ?? 0) !== 576) {
    fwrite(STDERR, 'Das V4-Poster hat nicht die erwarteten 1024x576 Pixel.' . PHP_EOL);
    exit(1);
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, 'Backup-Verzeichnis konnte nicht erstellt werden: ' . $backupDir . PHP_EOL);
    exit(1);
}

$backupFile = $backupDir . '/dbxMedia-before-tvspot-v4-' . date('Ymd-His') . '.db3';
if (!copy($dbFile, $backupFile)) {
    fwrite(STDERR, 'Mediendatenbank konnte nicht gesichert werden.' . PHP_EOL);
    exit(1);
}

$base = $repoRoot;
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';

$db = dbx()->get_system_obj('dbxDB');
$mediaDd = 'dbx|dbxMedia';

try {
    if ($db->begin($mediaDd) !== 1) {
        throw new RuntimeException('Die Medientransaktion konnte nicht gestartet werden.');
    }

    $before = $db->select1($mediaDd, $mediaId, '*', 0);
    if (!is_array($before) || (int)($before['id'] ?? 0) !== $mediaId) {
        throw new RuntimeException('Der erwartete Medieneintrag #442 existiert nicht.');
    }
    if ((string)($before['media_type'] ?? '') !== 'video') {
        throw new RuntimeException('Der Medieneintrag #442 ist kein Video.');
    }

    $updated = $db->update($mediaDd, array(
        'update_date' => date('Y-m-d H:i:s.v'),
        'update_uid' => 1,
        'active' => 1,
        'title' => 'dbxapp TV-Spot – Event-Choreografie',
        'alt' => 'Dynamischer dbxapp TV-Spot mit eingeblendeten Statuskarten, UI-Ereignissen und klaren Zustandswechseln',
        'file_name' => basename($videoFile),
        'file_path' => 'media/video/' . basename($videoFile),
        'mime' => 'video/mp4',
        'size' => filesize($videoFile),
        'width' => 1024,
        'height' => 576,
        'thumb_file_path' => 'media/img/images/' . basename($posterFile),
        'thumb_width' => 1024,
        'thumb_height' => 576,
        'media_type' => 'video',
        'storage_type' => 'local',
        'media_folder' => 'video',
    ), $mediaId, 0, 1, 0, 1);
    if ($updated !== 1) {
        throw new RuntimeException('Der Medieneintrag #442 konnte nicht aktualisiert werden.');
    }

    $after = $db->select1($mediaDd, $mediaId, '*', 0);
    if (!is_array($after) || (string)($after['file_name'] ?? '') !== basename($videoFile)) {
        throw new RuntimeException('Der aktualisierte Medieneintrag konnte nicht verifiziert werden.');
    }

    if ($db->commit($mediaDd) !== 1) {
        throw new RuntimeException('Die Medientransaktion konnte nicht abgeschlossen werden.');
    }
} catch (Throwable $exception) {
    $db->rollback($mediaDd);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, 'Unveränderte Sicherung: ' . $backupFile . PHP_EOL);
    exit(1);
}

echo json_encode(array(
    'media_id' => $mediaId,
    'previous_file' => (string)($before['file_name'] ?? ''),
    'active_file' => (string)($after['file_name'] ?? ''),
    'active_size' => (int)($after['size'] ?? 0),
    'poster' => (string)($after['thumb_file_path'] ?? ''),
    'backup' => $backupFile,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
