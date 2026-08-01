<?php

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

return array(
    'id' => 'core-4.1.0-media-usage-language',
    'version' => '4.1.0',
    'description' => 'Sprachsichere und idempotente Medienverwendung.',
    'affected_dd' => array('dbx|dbxMediaUsage'),
    'operations' => array(
        array('type' => 'sync_dd', 'dd' => 'dbx|dbxMediaUsage'),
    ),
    'up' => static function($db, $dd): void {
        require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
        // Migrations run before the staged program files become active. Load
        // this new dependency from the migration's own release tree instead
        // of relying on the previous installation's bootstrap/autoloader.
        require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContentMediaUsageScope.class.php';

        $languages = array_values(array_unique(array_map(
            static fn($lng): string => dbxContentMediaUsageScope::language((string)$lng),
            dbxContentLngSync::accessibleLngs()
        )));
        if (!$languages) $languages = array('de');
        $master = dbxContentMediaUsageScope::language(dbxContentLngSync::masterLng());

        $pageCache = array();
        $folderCache = array();
        foreach ($languages as $lng) {
            $pageCache[$lng] = array();
            $folderCache[$lng] = array();
            $pages = $db->select(dbxContentLng::ddContent($lng), '', 'id,folder,hero_image_id,content', 'id', 'ASC', '', 0, 0, 0);
            foreach (is_array($pages) ? $pages : array() as $page) {
                if (is_array($page) && (int)($page['id'] ?? 0) > 0) {
                    $pageCache[$lng][(int)$page['id']] = $page;
                }
            }
            $folders = $db->select(dbxContentLng::ddFolder($lng), '', 'id,hero_image_id', 'id', 'ASC', '', 0, 0, 0);
            foreach (is_array($folders) ? $folders : array() as $folder) {
                if (is_array($folder) && (int)($folder['id'] ?? 0) > 0) {
                    $folderCache[$lng][(int)$folder['id']] = $folder;
                }
            }
        }

        $containsMedia = static function(string $html, int $mediaId): bool {
            if ($mediaId <= 0 || $html === '') return false;
            return preg_match('/data-cms-media-id=["\']?' . $mediaId . '(?:["\'\s>]|$)/i', $html) === 1
                || preg_match('/(?:dbx_mid|media_id)=' . $mediaId . '(?:[^0-9]|$)/i', $html) === 1;
        };

        $rows = $db->select('dbxMediaUsage', '', '*', 'id', 'ASC', '', 0, 0, 0);
        $seen = array();
        foreach (is_array($rows) ? $rows : array() as $row) {
            if (!is_array($row)) continue;
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            if ((int)($row['active'] ?? 0) !== 1) {
                $db->delete('dbxMediaUsage', $id, 1, 0);
                continue;
            }

            $mediaId = (int)($row['media_id'] ?? 0);
            $contentId = (int)($row['content_id'] ?? 0);
            $folderId = (int)($row['folder_id'] ?? 0);
            $slot = strtolower(trim((string)($row['slot'] ?? '')));
            $candidates = array();

            if ($slot === 'shop') {
                $candidates = array($master);
            } elseif ($contentId > 0) {
                foreach ($languages as $lng) {
                    $page = $pageCache[$lng][$contentId] ?? null;
                    if (!is_array($page)) continue;
                    if ($folderId > 0 && (int)($page['folder'] ?? 0) !== $folderId) continue;
                    if ($slot === 'hero' && (int)($page['hero_image_id'] ?? 0) !== $mediaId) continue;
                    if ($slot === 'inline' && !$containsMedia((string)($page['content'] ?? ''), $mediaId)) continue;
                    $candidates[] = $lng;
                }
                if (!$candidates) {
                    foreach ($languages as $lng) {
                        if (isset($pageCache[$lng][$contentId])) $candidates[] = $lng;
                    }
                }
            } elseif ($folderId > 0) {
                foreach ($languages as $lng) {
                    $folder = $folderCache[$lng][$folderId] ?? null;
                    if (!is_array($folder)) continue;
                    if ($slot === 'hero' && (int)($folder['hero_image_id'] ?? 0) !== $mediaId) continue;
                    $candidates[] = $lng;
                }
                if (!$candidates) {
                    foreach ($languages as $lng) {
                        if (isset($folderCache[$lng][$folderId])) $candidates[] = $lng;
                    }
                }
            }

            $lng = count($candidates) === 1
                ? $candidates[0]
                : (in_array($master, $candidates, true) ? $master : ($candidates[0] ?? $master));
            $key = $lng . ':' . $contentId . ':' . $folderId . ':' . $slot . ':' . $mediaId;
            if (isset($seen[$key])) {
                $db->delete('dbxMediaUsage', $id, 1, 0);
                continue;
            }
            $seen[$key] = $id;
            if ((string)($row['content_lng'] ?? '') !== $lng) {
                $db->update('dbxMediaUsage', array('content_lng' => $lng), $id, 0, 1, 1, 0);
            }
        }
    },
);
