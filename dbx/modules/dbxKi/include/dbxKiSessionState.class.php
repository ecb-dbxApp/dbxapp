<?php
declare(strict_types=1);

namespace dbx\dbxKi;

/** Einziger Besitzer des requestübergreifenden dbxKi-Sessionzustands. */
final class dbxKiSessionState
{
    private const MODULE = 'dbxKi';

    public static function bucket(string $name): array
    {
        $bucket = dbx()->get_session_var('*', array(), $name, self::MODULE);
        return is_array($bucket) ? $bucket : array();
    }

    public static function get(string $bucket, string $key, mixed $default = null): mixed
    {
        return dbx()->get_session_var($key, $default, $bucket, self::MODULE);
    }

    public static function put(string $bucket, string $key, mixed $value): void
    {
        dbx()->set_session_var($key, $value, $bucket, self::MODULE);
    }

    public static function remove(string $bucket, string $key): void
    {
        dbx()->delete_session_var($key, $bucket, self::MODULE);
    }

    public static function has(string $bucket, string $key): bool
    {
        return !empty(dbx()->get_session_var($key, null, $bucket, self::MODULE));
    }

    public static function remember_consumed(string $contract_id, int $timestamp, int $limit = 500): void
    {
        self::put('consumed_contracts', $contract_id, $timestamp);
        $consumed = self::bucket('consumed_contracts');
        if (count($consumed) > $limit) {
            asort($consumed, SORT_NUMERIC);
            dbx()->set_session_var('*', array_slice($consumed, -$limit, null, true), 'consumed_contracts', self::MODULE);
        }
    }
}
