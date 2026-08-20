<?php
declare(strict_types=1);

namespace dbx\dbxUser;

/** Gemeinsame Prüfung von Benutzerdatei-Uploads. */
final class dbxUserUpload
{
    public static function has_file(string $key): bool
    {
        return isset($_FILES[$key])
            && is_array($_FILES[$key])
            && (int)($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            && trim((string)($_FILES[$key]['name'] ?? '')) !== '';
    }
}

