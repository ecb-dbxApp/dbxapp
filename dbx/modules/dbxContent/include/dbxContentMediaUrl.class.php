<?php

declare(strict_types=1);

namespace dbx\dbxContent;

final class dbxContentMediaUrl
{
    public static function external_video_thumbnail(array $row): string
    {
        $provider = strtolower(trim((string)($row['provider'] ?? '')));
        $provider_id = trim((string)($row['provider_id'] ?? ''));
        if ($provider === 'youtube' && preg_match('~^[A-Za-z0-9_-]{11}$~', $provider_id)) {
            return 'https://img.youtube.com/vi/' . $provider_id . '/hqdefault.jpg';
        }
        return '';
    }
}
