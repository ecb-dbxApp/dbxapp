<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

dbx()->get_system_obj('dbxDD');

class dbxDDRestoreTemporalProbe extends dbxDD
{
    public function mysqlType(string $type, string $length = ''): string
    {
        return $this->map_dd_type_to_sql_type('mysql', $type, $length);
    }

    public function restoreValue(
        string $dbType,
        string $fieldType,
        mixed $value,
        string $sourceFieldType = ''
    ): mixed
    {
        return $this->normalize_restore_value($dbType, $fieldType, $value, $sourceFieldType);
    }
}

$probe = new dbxDDRestoreTemporalProbe();

if ($probe->mysqlType('datetime', '-1') !== 'DATETIME(6)'
    || $probe->mysqlType('timestamp', '-1') !== 'TIMESTAMP(6)'
    || $probe->mysqlType('time', '-1') !== 'TIME(6)'
) {
    fwrite(STDERR, "FAIL: MySQL-Zeittypen bewahren keine Mikrosekunden.\n");
    exit(1);
}

foreach (['date', 'time', 'datetime', 'timestamp', 'year'] as $type) {
    if ($probe->restoreValue('mysql', $type, '') !== null) {
        fwrite(STDERR, "FAIL: Leerer MySQL-Zeitwert wurde fuer {$type} nicht zu NULL normalisiert.\n");
        exit(2);
    }
}

$datetime = '2026-07-24 20:15:16.123456';
if ($probe->restoreValue('mysql', 'datetime', $datetime) !== $datetime
    || $probe->restoreValue('sqlite', 'datetime', '') !== ''
    || $probe->restoreValue('mysql', 'varchar', '') !== ''
    || $probe->restoreValue('sqlite', 'text', '2026-07-24 20:15:16.123000', 'datetime')
        !== '2026-07-24 20:15:16.123'
    || $probe->restoreValue('sqlite', 'text', '2026-07-24 20:15:16.390000', 'datetime')
        !== '2026-07-24 20:15:16.390'
    || $probe->restoreValue('sqlite', 'text', '2026-07-24 20:15:16.000000', 'datetime')
        !== '2026-07-24 20:15:16'
) {
    fwrite(STDERR, "FAIL: Unbetroffene Restore-Werte wurden veraendert.\n");
    exit(3);
}

echo "OK dbxDD temporal restore\n";
