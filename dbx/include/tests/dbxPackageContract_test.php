<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/dbxPackageContract.class.php';

function package_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$contract = new dbxPackageContract();
$root = dirname(__DIR__, 3);
$count = 0;
$files = array_merge(
    array($root . '/dbx.package.json'),
    glob($root . '/dbx/modules/*/dbx.package.json') ?: array(),
    glob($root . '/dbx/design/*/dbx.package.json') ?: array()
);
foreach ($files as $file) {
    $manifest = json_decode((string)file_get_contents($file), true);
    package_contract_assert(is_array($manifest), 'Manifest ist ungueltig: ' . $file);
    $contract->validate_manifest($manifest, false);
    ++$count;
}
package_contract_assert($count >= 3, 'Kernel-, Modul- und Designmanifeste fehlen.');
package_contract_assert($contract->path_allowed('module', 'Demo', 'dbx/modules/Demo/demo.php'), 'Moduldatei wird nicht akzeptiert.');
package_contract_assert(!$contract->path_allowed('module', 'Demo', 'dbx/modules/Other/demo.php'), 'Modul kann fremde Dateien schreiben.');
package_contract_assert(!$contract->path_allowed('module', 'dbxMenu', 'dbx/modules/dbxMenu/tpl/htm/dbx-top-main.htm'), 'Kundenspezifische Menue-Templates duerfen nicht paketiert werden.');
package_contract_assert(!$contract->path_allowed('design', 'Demo', 'dbx/design/Demo/backdoor.php'), 'Design kann PHP ausliefern.');
package_contract_assert(!$contract->path_allowed('kernel', 'dbxapp', 'dbx/modules/Demo/demo.php'), 'Kernel kann Module ueberschreiben.');

$thrown = false;
try {
    $contract->normalize_path('../escape.php');
} catch (RuntimeException) {
    $thrown = true;
}
package_contract_assert($thrown, 'Pfad-Traversal wird nicht abgewiesen.');

echo 'dbxPackageContract: ' . $count . " Manifeste und Paketgrenzen geprueft.\n";
