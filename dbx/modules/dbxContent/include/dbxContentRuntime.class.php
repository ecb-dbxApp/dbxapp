<?php
declare(strict_types=1);

namespace dbx\dbxContent;

/** Gemeinsame request- und zugriffsbezogene Content-Helfer. */
final class dbxContentRuntime
{
    public static function app_url(): string
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === '') {
            return '';
        }

        $directory = str_replace('\\', '/', dirname($script));
        if ($directory === '.' || $directory === '/' || $directory === '\\') {
            return '/';
        }

        return rtrim($directory, '/') . '/';
    }

    public static function user_can_access(mixed $groups): bool
    {
        $groups = trim((string)$groups);
        return dbx()->has_group($groups === '' ? '*' : $groups);
    }

    public static function is_no_hero(mixed $value): bool
    {
        $value = strtolower(trim((string)$value));
        return in_array($value, array('none', 'no-hero', '0', 'off'), true);
    }

    public static function clean_text(mixed $value, int $max = 254): string
    {
        $value = trim((string)$value);
        if ($max > 0 && strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }

        return $value;
    }

    public static function apply_requested_language(): void
    {
        $language = strtolower(trim((string)dbx()->get_request_var('dbx_lng', '')));
        if ($language === '' || !in_array($language, dbxContentLngSync::accessible_lngs(), true)) {
            return;
        }

        dbx()->set_system_var('dbx_lng', $language);
        dbx()->set_remember_var('dbx_lng', $language, 'dbx');
    }
}
