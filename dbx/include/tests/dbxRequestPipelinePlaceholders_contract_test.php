<?php

declare(strict_types=1);

$source = (string)file_get_contents(dirname(__DIR__) . '/dbxRequestPipeline.class.php');
$interpreter = strpos($source, '$interpreter->run($page_content)');
$runtime_values = strpos($source, "get_system_obj('dbxTPL')->replaces_dbx(\$page_content)");
$restore_inert_code = strpos($source, '$web_app->add_norep($page_content)');

if ($interpreter === false
    || $runtime_values === false
    || $restore_inert_code === false
    || !($interpreter < $runtime_values
        && $runtime_values < $restore_inert_code)
) {
    fwrite(STDERR, "FAILED: Laufzeit-Platzhalter werden nicht vor inertem Code und Full-Page-Cache aufgelöst.\n");
    exit(1);
}

echo "OK dbxRequestPipeline runtime placeholders\n";
