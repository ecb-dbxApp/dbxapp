<?php
declare(strict_types=1);

namespace dbx\dbxAdmin;

/** Gemeinsame, zustandslose Operationen der DD- und FD-Editoren. */
final class dbxEditorRecords
{
    /** Liest eine Systemvariable und verwendet bei leerem Ergebnis den Default. */
    public static function system_value(string $name, mixed $default = ''): mixed
    {
        if (function_exists('dbx')) {
            $app = dbx();
            if (is_object($app) && method_exists($app, 'get_system_var')) {
                $value = $app->get_system_var($name);
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return $default;
    }

    /** Ordnet Records anhand einer vollständigen, eindeutigen Positionsliste. */
    public static function reorder(array $records, array $order): array|false
    {
        $records = array_values($records);
        $count = count($records);
        if ($count === 0) {
            return array();
        }
        if (count($order) !== $count) {
            return false;
        }

        $seen = array();
        $ordered = array();
        foreach ($order as $position) {
            $position = (int)$position;
            if ($position < 0 || $position >= $count || isset($seen[$position])) {
                return false;
            }
            $seen[$position] = true;
            $ordered[] = $records[$position];
        }

        return $ordered;
    }

    /** Gemeinsamer Ausgangsdatensatz für ein neues DD-/FD-Feld. */
    public static function default_field(): array
    {
        return array(
            'name'        => '',
            'type'        => 'varchar',
            'index'       => '',
            'length'      => '255',
            'default'     => '',
            'label'       => '',
            'rules'       => 'text',
            'tooltip'     => '',
            'errormsg'    => '',
            'placeholder' => '',
            'convert'     => '',
            'protect'     => '0',
            'group'       => '',
            'mask'        => '',
            'data'        => '',
            'options'     => '',
            'tpl'         => 'text-label',
            'js'          => '',
            'prompt'      => '',
        );
    }
}

