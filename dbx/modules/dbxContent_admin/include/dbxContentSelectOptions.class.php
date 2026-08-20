<?php

declare(strict_types=1);

namespace dbx\dbxContent_admin;

final class dbxContentSelectOptions
{
    public static function hierarchy(array $records, string $id, string $name, string $parent = ''): array
    {
        $options = array(0 => '/ ');
        foreach ($records as $record) {
            if (!is_array($record) || !isset($record[$id], $record[$name])) continue;
            $record_id = $record[$id];
            $label = $record[$name];
            $parent_id = $parent !== '' ? ($record[$parent] ?? 0) : 0;
            if ($parent_id == $record_id) $parent_id = 0;
            $visited = array();
            while ($parent_id > 0 && !isset($visited[$parent_id])) {
                $visited[$parent_id] = true;
                $parent_record = self::find($records, $id, $parent_id);
                if ($parent_record === null) break;
                $label = $parent_record[$name] . ' -> ' . $label;
                $parent_id = $parent_record[$parent] ?? 0;
            }
            $options[$record_id] = '/ -> ' . $label;
        }
        return $options;
    }

    private static function find(array $records, string $id, mixed $value): ?array
    {
        foreach ($records as $record) {
            if (is_array($record) && ($record[$id] ?? null) == $value) return $record;
        }
        return null;
    }
}
