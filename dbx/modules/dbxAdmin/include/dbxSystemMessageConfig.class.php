<?php
declare(strict_types=1);

namespace dbx\dbxAdmin;

/** Einheitlicher Zugriff auf die systemweite Meldungsstufe. */
final class dbxSystemMessageConfig
{
    public static function normalize(mixed $level): string
    {
        $level = strtolower(trim((string)$level));
        if ($level === 'warn') {
            $level = 'warning';
        }

        return in_array($level, array('error', 'warning', 'all'), true) ? $level : 'all';
    }

    public static function current(): string
    {
        return self::normalize(dbx()->get_cfg('dbx', 'sys_msg_level', 'all'));
    }

    public static function save(mixed $level): bool
    {
        $config = dbx()->get_cfg('dbx');
        if (!is_array($config)) {
            $config = array();
        }
        $config['sys_msg_level'] = self::normalize($level);

        return (int)dbx()->set_cfg('dbx', $config) > 0;
    }
}

