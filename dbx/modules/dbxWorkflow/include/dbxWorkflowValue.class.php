<?php
declare(strict_types=1);

namespace dbx\dbxWorkflow;

/** Zustandslose Normalisierung gemeinsamer Workflow-Werte. */
final class dbxWorkflowValue
{
    public static function read_json(mixed $value, mixed $default = array()): mixed
    {
        $value = trim((string)$value);
        if ($value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public static function key(mixed $value): string
    {
        $value = strtolower(trim((string)$value));
        $value = (string)preg_replace('/[^a-z0-9_]+/', '_', $value);
        return trim($value, '_');
    }
}

