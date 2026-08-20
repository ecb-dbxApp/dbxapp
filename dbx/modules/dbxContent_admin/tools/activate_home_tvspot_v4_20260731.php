<?php

declare(strict_types=1);

$repo_root = dirname(__DIR__, 4);
$db_file = $repo_root . '/dbx/modules/dbx/db/dbxMedia.db3';
$video_file = $repo_root . '/files/media/video/dbxapp-tvspot-20260731-v4.mp4';
$poster_file = $repo_root . '/files/media/img/images/dbxapp-tvspot-poster-20260731-v4.webp';
$backup_dir = $repo_root . '/files/tmp';
$media_id = 442;

foreach (array($db_file, $video_file, $poster_file) as $required_file) {
    if (!is_file($required_file)) {
        fwrite(STDERR, 'Pflichtdatei fehlt: ' . $required_file . PHP_EOL);
        exit(1);
    }
}

$poster_info = @getimagesize($poster_file);
if (!is_array($poster_info) || (int)($poster_info[0] ?? 0) !== 1024 || (int)($poster_info[1] ?? 0) !== 576) {
    fwrite(STDERR, 'Das V4-Poster hat nicht die erwarteten 1024x576 Pixel.' . PHP_EOL);
    exit(1);
}

if (!is_dir($backup_dir) && !mkdir($backup_dir, 0775, true) && !is_dir($backup_dir)) {
    fwrite(STDERR, 'Backup-Verzeichnis konnte nicht erstellt werden: ' . $backup_dir . PHP_EOL);
    exit(1);
}

$backup_file = $backup_dir . '/dbxMedia-before-tvspot-v4-' . date('Ymd-His') . '.db3';
if (!copy($db_file, $backup_file)) {
    fwrite(STDERR, 'Mediendatenbank konnte nicht gesichert werden.' . PHP_EOL);
    exit(1);
}

$base = $repo_root;
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';

$db = dbx()->get_system_obj('dbxDB');
$media_dd = 'dbx|dbxMedia';

try {
    if ($db->begin($media_dd) !== 1) {
        throw new RuntimeException('Die Medientransaktion konnte nicht gestartet werden.');
    }

    $before = $db->select1($media_dd, $media_id, '*', 0);
    if (!is_array($before) || (int)($before['id'] ?? 0) !== $media_id) {
        throw new RuntimeException('Der erwartete Medieneintrag #442 existiert nicht.');
    }
    if ((string)($before['media_type'] ?? '') !== 'video') {
        throw new RuntimeException('Der Medieneintrag #442 ist kein Video.');
    }

    $updated = $db->update($media_dd, array(
        'update_date' => date('Y-m-d H:i:s.v'),
        'update_uid' => 1,
        'active' => 1,
        'title' => 'dbxapp TV-Spot – Event-Choreografie',
        'alt' => 'Dynamischer dbxapp TV-Spot mit eingeblendeten Statuskarten, UI-Ereignissen und klaren Zustandswechseln',
        'file_name' => basename($video_file),
        'file_path' => 'media/video/' . basename($video_file),
        'mime' => 'video/mp4',
        'size' => filesize($video_file),
        'width' => 1024,
        'height' => 576,
        'thumb_file_path' => 'media/img/images/' . basename($poster_file),
        'thumb_width' => 1024,
        'thumb_height' => 576,
        'media_type' => 'video',
        'storage_type' => 'local',
        'media_folder' => 'video',
    ), $media_id, 0, 1, 0, 1);
    if ($updated !== 1) {
        throw new RuntimeException('Der Medieneintrag #442 konnte nicht aktualisiert werden.');
    }

    $after = $db->select1($media_dd, $media_id, '*', 0);
    if (!is_array($after) || (string)($after['file_name'] ?? '') !== basename($video_file)) {
        throw new RuntimeException('Der aktualisierte Medieneintrag konnte nicht verifiziert werden.');
    }

    if ($db->commit($media_dd) !== 1) {
        throw new RuntimeException('Die Medientransaktion konnte nicht abgeschlossen werden.');
    }
} catch (Throwable $exception) {
    $db->rollback($media_dd);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, 'Unveränderte Sicherung: ' . $backup_file . PHP_EOL);
    exit(1);
}

echo json_encode(array(
    'media_id' => $media_id,
    'previous_file' => (string)($before['file_name'] ?? ''),
    'active_file' => (string)($after['file_name'] ?? ''),
    'active_size' => (int)($after['size'] ?? 0),
    'poster' => (string)($after['thumb_file_path'] ?? ''),
    'backup' => $backup_file,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
