<?php
declare(strict_types=1);

namespace dbx\dbxLogin;

/** Einheitliche Auswertung der Login-Modulkonfiguration. */
final class dbxLoginConfig
{
    public static function mail_enabled(string $key): bool
    {
        $value = strtolower(trim((string)dbx()->get_cfg('dbxLogin', $key)));
        return !in_array($value, array('', '0', 'false', 'off', 'no'), true);
    }
}

