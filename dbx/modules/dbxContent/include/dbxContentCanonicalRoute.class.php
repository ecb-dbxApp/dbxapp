<?php

declare(strict_types=1);

namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContentLng.class.php';
require_once __DIR__ . '/dbxContentHome.class.php';
require_once __DIR__ . '/dbxContent_permalink.class.php';

/** Ermittelt kanonische öffentliche URLs und sprachliche Schwesterseiten. */
final class dbxContentCanonicalRoute
{
    /** Liefert die kanonische URL einer Content-Seite in der gewünschten Sprache. */
    public function page_url(int $cid, string $source_lng, string $target_lng): string
    {
        $source_lng = $this->safe_language($source_lng);
        $target_lng = $this->safe_language($target_lng);
        if ($cid <= 0 || $source_lng === '' || $target_lng === '') {
            return '';
        }

        $db = dbx()->get_system_obj('dbxDB');
        if (!is_object($db)) {
            return '';
        }

        $source = $db->select1(
            dbxContentLng::dd_content($source_lng),
            $cid,
            'id,permalink,lng_uid,activ',
            0
        );
        if (!is_array($source) || (int)($source['activ'] ?? 0) !== 1) {
            return '';
        }

        $target = $source;
        if ($target_lng !== $source_lng) {
            $lng_uid = trim((string)($source['lng_uid'] ?? ''));
            if ($lng_uid === '') {
                return '';
            }
            $target = $db->select1(
                dbxContentLng::dd_content($target_lng),
                array('lng_uid' => $lng_uid, 'activ' => 1),
                'id,permalink,lng_uid,activ',
                0
            );
            if (!is_array($target) || (int)($target['activ'] ?? 0) !== 1) {
                return '';
            }
        }

        return $this->record_url($target, $target_lng);
    }

    /** Liefert die kanonische URL der aktuell aufgelösten Content-Route. */
    public function current_page_url(): string
    {
        $cid = (int)dbx()->get_system_var('dbx_content_route_cid', 0, 'int');
        $language = (string)dbx()->get_system_var('dbx_lng', 'de');
        return $this->page_url($cid, $language, $language);
    }

    /** Ergänzt die konfigurierte Sprachwurzel exakt einmal. */
    private function record_url(array $record, string $language): string
    {
        $cid = (int)($record['id'] ?? 0);
        $base_url = rtrim((string)dbx()->get_base_url(), '/') . '/';
        $prefix = $this->language_prefix($language);
        if ($cid > 0 && dbxContentHome::resolve_cid($language) === $cid) {
            return $base_url . $prefix;
        }

        $permalink = dbxContent_permalink::public_path((string)($record['permalink'] ?? ''));
        if ($permalink === '') {
            return '';
        }
        return $base_url . $prefix . ltrim($permalink, '/');
    }

    private function language_prefix(string $language): string
    {
        $default_lng = $this->safe_language((string)dbx()->get_cfg('dbx', 'default_lng', 'de'));
        $use_prefix = (int)dbx()->get_cfg('dbx', 'language_path_prefix', 0) === 1
            && $language !== ''
            && $language !== $default_lng;
        return $use_prefix ? rawurlencode($language) . '/' : '';
    }

    private function safe_language(string $language): string
    {
        $language = strtolower(trim($language));
        return preg_match('/^[a-z]{2,3}$/', $language) === 1 ? $language : '';
    }
}
