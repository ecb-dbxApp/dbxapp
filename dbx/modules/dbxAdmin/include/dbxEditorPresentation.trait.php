<?php
declare(strict_types=1);

namespace dbx\dbxAdmin;

/** Gemeinsame interne Darstellung und Formularwertbehandlung der DD-/FD-Editoren. */
trait dbxEditorPresentationTrait
{
    private function merge_record($old, $post, $keys)
    {
        $record = is_array($old) ? $old : array();
        foreach ((array)$keys as $key) {
            if (array_key_exists($key, (array)$post)) {
                $record[$key] = $this->normalize_value($post[$key]);
            }
        }

        return $record;
    }

    private function normalize_value($value): string
    {
        if (is_array($value)) {
            return implode(',', array_map('trim', $value));
        }

        return trim((string)$value);
    }

    private function safe_id($value): string
    {
        $value = (string)preg_replace('/[^A-Za-z0-9_]+/', '_', (string)$value);
        $value = trim($value, '_');

        return $value ?: 'x';
    }

    private function alert($type, $message): string
    {
        $type = (string)preg_replace('/[^a-z]/', '', (string)$type);
        if ($type === '') {
            $type = 'info';
        }

        return '<div class="alert alert-' . $type . '">' . $message . '</div>';
    }
}

