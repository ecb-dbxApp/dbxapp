<?php
declare(strict_types=1);

namespace dbx\dbxContent_admin;

/** Einziger Besitzer des requestübergreifenden CMS-Admin-Prozesszustands. */
final class dbxContentAdminSessionState
{
    public static function media_process(string $token): array
    {
        $state = dbx()->get_session_var($token, array(), 'media_process', 'dbxContent_admin');
        return is_array($state) ? $state : array();
    }

    public static function set_media_process(string $token, array $state): void
    {
        dbx()->set_session_var($token, $state, 'media_process', 'dbxContent_admin');
    }
}
