<?php

declare(strict_types=1);

namespace dbx\dbxShop;

final class dbxShopSearch
{
    public static function normalized_text(string $value): string
    {
        $value = strtolower($value);
        $value = strtr($value, array('ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'));
        $value = preg_replace('~[^a-z0-9]+~', ' ', $value) ?: '';
        return preg_replace('~\s+~', ' ', trim($value)) ?: '';
    }

    public static function terms(string $query): array
    {
        $terms = preg_split('~\s+~', self::normalized_text($query)) ?: array();
        $stop_words = array_flip(array(
            'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einer', 'einem',
            'und', 'oder', 'mit', 'ohne', 'fuer', 'fur', 'von', 'im', 'in', 'am', 'an', 'auf', 'zu',
        ));
        $result = array();
        foreach ($terms as $term) {
            $term = trim($term);
            if ($term === '' || isset($stop_words[$term])) continue;
            if (strlen($term) < 2 && !ctype_digit($term)) continue;
            $result[$term] = true;
        }
        return array_keys($result);
    }

    public static function field_score(string $text, string $term, int $weight): int
    {
        if ($text === '' || $term === '') return 0;
        if ($text === $term) return $weight * 8;
        $term_length = strlen($term);
        $compact_text = str_replace(' ', '', $text);
        $compact_term = str_replace(' ', '', $term);
        if (strpos($text, $term) !== false || strpos($compact_text, $compact_term) !== false) {
            return $weight * 5;
        }
        $best = 0;
        foreach (preg_split('~\s+~', $text) ?: array() as $token) {
            $token = trim($token);
            if ($token === '') continue;
            if ($token === $term) {
                $best = max($best, $weight * 6);
                continue;
            }
            if ($term_length < 3) continue;
            if (strlen($token) >= $term_length && strpos($token, $term) === 0) {
                $best = max($best, $weight * 4);
                continue;
            }
            if ($term_length >= 4
                && strlen($token) >= 4
                && substr($token, 0, 3) === substr($term, 0, 3)
                && abs(strlen($token) - $term_length) <= ($term_length >= 7 ? 2 : 1)
                && levenshtein($token, $term) <= ($term_length >= 7 ? 2 : 1)) {
                $best = max($best, $weight * 2);
            }
        }
        return $best;
    }
}
