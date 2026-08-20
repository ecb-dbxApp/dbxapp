<?php
declare(strict_types=1);

namespace dbx\dbxShop;

/** Gemeinsame Normalisierung von Shop-Konfiguration und Produktwerten. */
final class dbxShopValue
{
    public static function setting_bool(array $config, string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), array('1', 'true', 'yes', 'on'), true);
    }

    public static function attribute_text(array $product): string
    {
        $parts = array();
        foreach (($product['attributes'] ?? array()) as $attribute) {
            $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
            $parts[] = (string)($attribute['title'] ?? '');
            $parts[] = (string)($attribute['attr_key'] ?? '');
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode(' ', $parts);
    }
}

