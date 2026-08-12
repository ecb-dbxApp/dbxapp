<?php

declare(strict_types=1);

use dbx\dbxContent\dbxContentLngSync;

require_once dirname(__DIR__) . '/include/dbxContentLngSync.class.php';

final class dbxContentReadOnlyUidDb
{
    public int $selects = 0;
    public int $updates = 0;
    public array $row = array('lng_uid' => ' p_existing ');

    public function select1(string $dd, int $id, string $fields, int $access): array
    {
        $this->selects++;
        return $this->row;
    }

    public function update(...$args): int
    {
        $this->updates++;
        return 1;
    }
}

$db = new dbxContentReadOnlyUidDb();
$uid = dbxContentLngSync::recordUid($db, 'content', 7);
if ($uid !== 'p_existing' || $db->selects !== 1 || $db->updates !== 0) {
    fwrite(STDERR, "Das reine Lesen einer Sprach-UID hat einen Seiteneffekt oder liefert einen falschen Wert.\n");
    exit(1);
}

$db->row = array('lng_uid' => '');
$uid = dbxContentLngSync::recordUid($db, 'content', 7);
if ($uid !== '' || $db->updates !== 0) {
    fwrite(STDERR, "Eine fehlende Sprach-UID wurde in einem Leseablauf geschrieben.\n");
    exit(1);
}

$cmsSource = file_get_contents(dirname(__DIR__, 2) . '/dbxContent_admin/include/dbxContent_cms.class.php');
$homeSource = file_get_contents(dirname(__DIR__) . '/include/dbxContentHome.class.php');
if (!is_string($cmsSource) || !is_string($homeSource)) {
    fwrite(STDERR, "Content-Quellen konnten nicht geprüft werden.\n");
    exit(1);
}
foreach (array('attach_lng_coverage', 'lng_coverage_json') as $method) {
    if (preg_match('/function\s+' . preg_quote($method, '/') . '\b(?:(?!\n\s*(?:public|private|protected)\s+function\b).)*ensureRecordUid\s*\(/s', $cmsSource) === 1) {
        fwrite(STDERR, "CMS-Lesemethode $method darf keine Sprach-UID erzeugen.\n");
        exit(1);
    }
}
if (preg_match('/function\s+resolveForLng\b(?:(?!\n\s*(?:public|private|protected)\s+function\b).)*ensureRecordUid\s*\(/s', $homeSource) === 1) {
    fwrite(STDERR, "Startseitenauflösung darf keine Sprach-UID erzeugen.\n");
    exit(1);
}

echo "OK: Content-Tree, Coverage und Startseitenauflösung lesen Sprach-UIDs ohne Datenbankänderung.\n";
