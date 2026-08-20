<?php
declare(strict_types=1);

final class dbxContentRuntimeTestApi
{
    public bool $access = true;

    public function has_group(string $groups): bool
    {
        return $this->access;
    }
}

function dbx(): dbxContentRuntimeTestApi
{
    static $api;
    return $api ??= new dbxContentRuntimeTestApi();
}

require_once dirname(__DIR__) . '/include/dbxContentRuntime.class.php';

use dbx\dbxContent\dbxContentRuntime;

if (dbxContentRuntime::user_can_access('*') !== true) {
    fwrite(STDERR, "FAIL: Gruppenfreigabe wird nicht als true weitergegeben.\n");
    exit(1);
}
dbx()->access = false;
if (dbxContentRuntime::user_can_access('*') !== false) {
    fwrite(STDERR, "FAIL: Abgelehnte Gruppenfreigabe wird nicht als false weitergegeben.\n");
    exit(1);
}
echo "OK dbxContent runtime verwendet die eindeutige Gruppenpruefung.\n";
