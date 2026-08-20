<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$model = (string)file_get_contents($root . '/include/dbxDDModel.trait.php');
$ki = (string)file_get_contents($root . '/modules/dbxKi/include/dbxKiModuleBriefingService.class.php');

$save_start = strpos($model, 'public function save_dd(');
$sync_call = strpos($model, '$this->synchronize_saved_dd($modul, $dd)', $save_start === false ? 0 : $save_start);
$private_write = strpos($model, 'private function write_dd(');
if ($save_start === false || $sync_call === false || $private_write === false || $sync_call < $save_start) {
    fwrite(STDERR, "DD-Speichern erzwingt die DD->DB-Synchronisierung nicht.\n");
    exit(1);
}

foreach (array(
    "Jede DD-Aenderung benoetigt danach module.dd.sync",
    "sync_dd_to_db(\$modul, \$dd, 'plan')",
    "!== 'finished'",
) as $required) {
    if (!str_contains($ki, $required)) {
        fwrite(STDERR, "KI-DD-Vertrag unvollstaendig: {$required}\n");
        exit(1);
    }
}

echo "OK DD-Aenderungen erzwingen Datei-, Plan- und DB-Synchronisierung.\n";
