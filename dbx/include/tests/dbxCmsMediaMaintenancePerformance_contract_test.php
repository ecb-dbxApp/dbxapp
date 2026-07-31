<?php
declare(strict_types=1);

$base = dirname(__DIR__, 3);
$cms = file_get_contents($base . '/dbx/modules/dbxContent_admin/include/dbxContent_cms.class.php');
$process = file_get_contents($base . '/dbx/js/lib/process.js');

if (!is_string($cms) || !is_string($process)) {
    fwrite(STDERR, "Quellcode konnte nicht geladen werden.\n");
    exit(1);
}

$checks = [
    'Nutzungsabgleich startet eine DB-Transaktion' => strpos($cms, '$db->begin($this->dd_media_usage)') !== false,
    'Nutzungsabgleich committed die DB-Transaktion' => strpos($cms, '$db->commit($this->dd_media_usage)') !== false,
    'Nutzungsabgleich kann atomar zurueckrollen' => strpos($cms, '$db->rollback($this->dd_media_usage)') !== false,
    'Sorter werden ohne SELECT pro Insert fortgeschrieben' => strpos($cms, '$sorter_max[$sorter_key]') !== false,
    'Wartungs-Inserts laufen ohne Trace-Nebenlast' => strpos($cms, '$db->insert($this->dd_media_usage, $insert, 0, 1, 1, 0)') !== false,
    'SVG wird nicht faelschlich als Thumbnail-Fehler behandelt' => strpos($cms, '$this->media_thumbnail_supported($row)') !== false && strpos($cms, "return \$mime !== 'image/svg+xml'") !== false,
    'Fehlende und veraltete Thumbnails werden erkannt' => strpos($cms, 'private function media_thumbnail_is_current($row): bool') !== false
        && strpos($cms, '$source_time > $thumb_time') !== false
        && strpos($cms, 'strpos($thumb_stem, $source_stem) === false') !== false,
    'Ersetzte Thumbnails werden als verwaiste Dateien entfernt' => substr_count($cms, '$old_thumb !== \'\' && $old_thumb !== $new_thumb') >= 2,
    'Ein Datensatz plant nicht gleichzeitig Record- und Thumbnail-Arbeit' => strpos($cms, "if (\$id > 0\n             && !\$needs_record_check") !== false,
    'Prozess transport toleriert lange Wartungsschritte' => strpos($process, 'timeout: 45000') !== false,
    'Prozess fragt Status nach einem Timeout erneut ab' => strpos($process, 'loadIntoRoot(root, url, "retry")') !== false,
    'Prozess begrenzt automatische Wiederholungen' => strpos($process, 'retryCount <= 3') !== false,
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) $failed[] = $label;
}

if ($failed) {
    fwrite(STDERR, "Fehlgeschlagen:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "OK: Medienwartung schreibt atomar und die Prozessanzeige nimmt lange Schritte wieder auf.\n";
