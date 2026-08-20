<?php

declare(strict_types=1);

namespace dbx\dbxShop;

final class dbxShopMediaUrl
{
    public static function path(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') return '';
        if (preg_match('~^https?://~i', $path) || str_starts_with($path, '/')) return $path;
        return dbx()->get_base_url() . ltrim($path, '/');
    }

    public static function item(array $image, bool $thumbnail = false): string
    {
        $media_id = (int)($image['media_id'] ?? 0);
        if ($media_id > 0) {
            $url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $media_id;
            return $thumbnail ? $url . '&dbx_thumb=1' : $url;
        }
        return self::path((string)($image['image_path'] ?? ''));
    }
}
