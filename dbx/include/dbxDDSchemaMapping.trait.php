<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDDSchemaMappingTrait
{
/* =====================================================
     * SCHEMA MAPPING
     * ===================================================== */

    /**
     * Normalisiert einen Feldnamen fuer robuste Auto-Zuordnung.
     *
     * @param string $name Feldname
     *
     * @return string Normalisierter Vergleichsschluessel
     */
    protected function normalize_schema_field_key(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($name)));
    }

/**
     * Baut einen sicheren Dateinamenbestandteil.
     *
     * @param string $value Rohwert
     *
     * @return string Sicherer Dateinamenbestandteil
     */
    protected function schema_mapping_file_part(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim($value));
        $value = trim((string)$value, '._-');

        return $value !== '' ? $value : 'default';
    }

/**
     * Liefert das Verzeichnis fuer gespeicherte Schema-Mappings.
     *
     * @return string Absoluter Pfad
     */
    protected function schema_mapping_dir(): string
    {
        $dir = dbx()->os_path(dbx()->get_file_dir() . 'db/schema-mapping/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

/**
     * Liefert den Pfad zur Mapping-Datei.
     *
     * @param string $kind Mapping-Art
     * @param array  $context Kontextdaten
     *
     * @return string Absoluter Dateipfad
     */
    public function schema_mapping_path(string $kind, array $context): string
    {
        $kind = strtolower(trim($kind));

        $parts = [
            $kind ?: 'schema',
            $context['modul'] ?? '',
            $context['dd'] ?? '',
            $context['source_server'] ?? ($context['server'] ?? ''),
            $context['source_table'] ?? ($context['table'] ?? ''),
            $context['target_server'] ?? '',
            $context['target_table'] ?? '',
        ];

        $parts = array_map(fn($v) => $this->schema_mapping_file_part((string)$v), $parts);

        return $this->schema_mapping_dir() . implode('__', $parts) . '.json';
    }

/**
     * Baut einen Feldindex nach echtem Feldnamen.
     *
     * @param array $fields Feldliste
     *
     * @return array Feldindex
     */
    protected function schema_fields_by_name(array $fields): array
    {
        $index = [];

        foreach ($fields as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name !== '') {
                $index[$name] = $field;
            }
        }

        return $this->sort_schema_record($index, $this->dd_index_schema_keys());
    }

protected function sort_schema_record(array $record, array $keys): array
    {
        $sorted = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $record)) {
                $sorted[$key] = $record[$key];
                unset($record[$key]);
            }
        }

        foreach ($record as $key => $value) {
            $sorted[$key] = $value;
        }

        return $sorted;
    }

/**
     * Normalisiert eine technische Source->Target-Zuordnung.
     *
     * @param array $mapping      Source->Target Mapping
     * @param array $sourceFields Quellfelder
     * @param array $targetFields Zielfelder
     *
     * @return array Valides Source->Target Mapping
     */
    public function normalize_schema_mapping(array $mapping, array $source_fields, array $target_fields): array
    {
        $source_by_lower = [];
        foreach ($source_fields as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name !== '') {
                $source_by_lower[strtolower($name)] = $name;
            }
        }

        $target_by_lower = [];
        foreach ($target_fields as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name !== '') {
                $target_by_lower[strtolower($name)] = $name;
            }
        }

        $clean = [];
        foreach ($mapping as $source => $target) {
            $source_key = strtolower(trim((string)$source));
            $target_key = strtolower(trim((string)$target));

            if ($source_key === '' || $target_key === '') {
                continue;
            }

            if (!isset($source_by_lower[$source_key]) || !isset($target_by_lower[$target_key])) {
                continue;
            }

            $source_name = $source_by_lower[$source_key];
            $target_name = $target_by_lower[$target_key];

            foreach ($clean as $old_source => $old_target) {
                if (strcasecmp($old_target, $target_name) === 0) {
                    unset($clean[$old_source]);
                }
            }

            $clean[$source_name] = $target_name;
        }

        ksort($clean);
        return $clean;
    }

/**
     * Baut ein Auto-Mapping und uebersteuert es mit gespeicherter Zuordnung.
     *
     * @param array $sourceFields Quellfelder
     * @param array $targetFields Zielfelder
     * @param array $stored       Gespeichertes Mapping
     *
     * @return array Source->Target Mapping
     */
    protected function auto_schema_mapping(array $source_fields, array $target_fields, array $stored = []): array
    {
        $source_exact = [];
        $source_norm  = [];

        foreach ($source_fields as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $source_exact[strtolower($name)] = $name;
            $norm = $this->normalize_schema_field_key($name);
            if ($norm !== '' && !isset($source_norm[$norm])) {
                $source_norm[$norm] = $name;
            }
        }

        $mapping = [];
        $used_source = [];

        foreach ($target_fields as $field) {
            $target = (string)($field['name'] ?? '');
            if ($target === '') {
                continue;
            }

            $source = $source_exact[strtolower($target)] ?? '';
            if ($source !== '') {
                $mapping[$source] = $target;
                $used_source[strtolower($source)] = true;
            }
        }

        foreach ($target_fields as $field) {
            $target = (string)($field['name'] ?? '');
            if ($target === '') {
                continue;
            }

            $already_mapped = false;
            foreach ($mapping as $mapped_target) {
                if (strcasecmp($mapped_target, $target) === 0) {
                    $already_mapped = true;
                    break;
                }
            }

            if ($already_mapped) {
                continue;
            }

            $norm = $this->normalize_schema_field_key($target);
            $source = $source_norm[$norm] ?? '';
            if ($source !== '' && empty($used_source[strtolower($source)])) {
                $mapping[$source] = $target;
                $used_source[strtolower($source)] = true;
            }
        }

        $stored = $this->normalize_schema_mapping($stored, $source_fields, $target_fields);
        foreach ($stored as $source => $target) {
            foreach ($mapping as $old_source => $old_target) {
                if (strcasecmp($old_source, $source) === 0 || strcasecmp($old_target, $target) === 0) {
                    unset($mapping[$old_source]);
                }
            }

            $mapping[$source] = $target;
        }

        ksort($mapping);
        return $mapping;
    }

/**
     * Laedt ein gespeichertes Schema-Mapping.
     *
     * @param string $kind    Mapping-Art
     * @param array  $context Kontextdaten
     *
     * @return array Mapping-Dateiinhalt
     */
    public function load_schema_mapping(string $kind, array $context): array
    {
        $file = $this->schema_mapping_path($kind, $context);
        if (!is_file($file)) {
            return [
                'kind'       => strtolower(trim($kind)),
                'context'    => $context,
                'mapping'    => [],
                'file'       => $file,
                'updated_at' => '',
            ];
        }

        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            $data = [];
        }

        $data['kind']    = $data['kind'] ?? strtolower(trim($kind));
        $data['context'] = is_array($data['context'] ?? null) ? $data['context'] : $context;
        $data['mapping'] = is_array($data['mapping'] ?? null) ? $data['mapping'] : [];
        $data['file']    = $file;

        return $data;
    }

/**
     * Gibt nur die gespeicherten Mapping-Werte zurueck.
     *
     * @param string $kind    Mapping-Art
     * @param array  $context Kontextdaten
     *
     * @return array Source->Target Mapping
     */
    public function get_schema_mapping_values(string $kind, array $context): array
    {
        $data = $this->load_schema_mapping($kind, $context);

        return is_array($data['mapping'] ?? null) ? $data['mapping'] : [];
    }

/**
     * Baut die Mapping-Ansicht fuer Admin-UI und Prozesse.
     *
     * @param string $kind    dd_to_db|db_to_dd|transfer
     * @param array  $context Kontextdaten
     *
     * @return array Mapping-Modell
     */
    public function build_schema_mapping(string $kind, array $context): array
    {
        $kind = strtolower(trim($kind));
        if ($kind === '') {
            $kind = 'dd_to_db';
        }

        $modul = (string)($context['modul'] ?? '');
        $dd    = (string)($context['dd'] ?? '');
        $server = (string)($context['server'] ?? ($context['source_server'] ?? ''));
        $table  = (string)($context['table'] ?? ($context['source_table'] ?? ''));

        $source_fields = [];
        $target_fields = [];
        $source_label  = '';
        $target_label  = '';
        $dd_ref        = ($modul && $dd && strpos($dd, '|') === false) ? $modul . '|' . $dd : $dd;

        if ($kind === 'db_to_dd') {
            if ($server && $table && $this->get_table_exist($server, $table)) {
                $source_fields = $this->get_db_fields($server, $table);
            }

            $old_model = [];
            if ($dd_ref && ($this->get_dd_exist($dd_ref) || $this->get_dd_exist($dd))) {
                $old_model = $this->get_dd_model($dd_ref);
                if (!$old_model) {
                    $old_model = $this->get_dd_model($dd);
                }
            }

            $target_fields = $old_model['fields'] ?? $source_fields;
            $source_label  = $server . '|' . $table;
            $target_label  = ($modul ? $modul . '|' : '') . $dd;
        } elseif ($kind === 'transfer') {
            $source_server = (string)($context['source_server'] ?? $server);
            $source_table  = (string)($context['source_table'] ?? $table);
            $target_server = (string)($context['target_server'] ?? '');
            $target_table  = (string)($context['target_table'] ?? '');

            if ($source_server && $source_table && $this->get_table_exist($source_server, $source_table)) {
                $schema = $this->get_preferred_table_schema($source_server, $source_table);
                $source_fields = $schema['fields'] ?? [];
            }
            if ($target_server && $target_table && $this->get_table_exist($target_server, $target_table)) {
                $target_fields = $this->get_db_fields($target_server, $target_table);
            } else {
                $target_fields = $source_fields;
            }

            $source_label = $source_server . '|' . $source_table;
            $target_label = $target_server . '|' . $target_table;
        } else {
            $kind = 'dd_to_db';
            $model = $dd_ref ? $this->get_dd_model($dd_ref) : [];
            if (!$model && $dd) {
                $model = $this->get_dd_model($dd);
            }

            $server = $server ?: (string)($model['table']['server'] ?? '');
            $table  = $table  ?: (string)($model['table']['table']  ?? '');

            if ($server && $table && $this->get_table_exist($server, $table)) {
                $source_fields = $this->get_db_fields($server, $table);
            }

            $target_fields = $model['fields'] ?? [];
            $source_label  = $server . '|' . $table;
            $target_label  = ($modul ? $modul . '|' : '') . $dd;
        }

        $context['modul'] = $modul;
        $context['dd'] = $dd;
        $context['server'] = $server;
        $context['table'] = $table;

        $stored_data = $this->load_schema_mapping($kind, $context);
        $stored = is_array($stored_data['mapping'] ?? null) ? $stored_data['mapping'] : [];
        $mapping = $this->auto_schema_mapping($source_fields, $target_fields, $stored);

        $source_by_name = $this->schema_fields_by_name($source_fields);
        $used_sources  = [];
        $target_rows   = [];

        foreach ($target_fields as $target) {
            $target_name = (string)($target['name'] ?? '');
            if ($target_name === '') {
                continue;
            }

            $source_name = '';
            foreach ($mapping as $source => $mapped_target) {
                if (strcasecmp($mapped_target, $target_name) === 0) {
                    $source_name = $source;
                    $used_sources[strtolower($source)] = true;
                    break;
                }
            }

            $source = $source_name !== '' && isset($source_by_name[$source_name]) ? $source_by_name[$source_name] : [];
            $status = 'new';
            if ($source_name !== '') {
                $status = (strcasecmp($source_name, $target_name) === 0) ? 'exact' : 'mapped';

                if ($source && $target && !$this->is_dd_field_compatible_with_db($target, $source, $server ? $this->get_db_type($server) : '')) {
                    $status = 'type_conflict';
                }
            }

            $target_rows[] = [
                'target' => $target,
                'source' => $source,
                'source_name' => $source_name,
                'target_name' => $target_name,
                'status' => $status,
            ];
        }

        $unmapped_sources = [];
        foreach ($source_fields as $source) {
            $name = (string)($source['name'] ?? '');
            if ($name !== '' && empty($used_sources[strtolower($name)])) {
                $unmapped_sources[] = $source;
            }
        }

        return [
            'kind'             => $kind,
            'context'          => $context,
            'source_label'     => $source_label,
            'target_label'     => $target_label,
            'source_fields'    => $source_fields,
            'target_fields'    => $target_fields,
            'target_rows'      => $target_rows,
            'unmapped_sources' => $unmapped_sources,
            'mapping'          => $mapping,
            'stored_mapping'   => $stored,
            'file'             => $stored_data['file'] ?? $this->schema_mapping_path($kind, $context),
            'updated_at'       => $stored_data['updated_at'] ?? '',
        ];
    }

/**
     * Speichert ein Schema-Mapping als wiederverwendbare Mapping-Datei.
     *
     * @param string $kind    Mapping-Art
     * @param array  $context Kontextdaten
     * @param array  $mapping Source->Target Mapping
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function save_schema_mapping(string $kind, array $context, array $mapping): int
    {
        $model = $this->build_schema_mapping($kind, $context);
        $clean = $this->normalize_schema_mapping($mapping, $model['source_fields'] ?? [], $model['target_fields'] ?? []);

        $data = [
            'kind'       => $model['kind'] ?? strtolower(trim($kind)),
            'context'    => $model['context'] ?? $context,
            'source'     => $model['source_label'] ?? '',
            'target'     => $model['target_label'] ?? '',
            'mapping'    => $clean,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $file = $model['file'] ?? $this->schema_mapping_path($kind, $context);
        $ok = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $ok === false ? 0 : 1;
    }

/* =====================================================
     * CREATE DD
     * ===================================================== */

    /**
     * Erzeugt eine DD aus einer vorhandenen DB-Tabelle.
     *
     * Hinweis:
     * - dbxDD liefert hier nur die technische Infrastruktur
     * - spätere Admin-/Mapping-Logik kann dieses Ergebnis gezielt anpassen
     *
     * @param string $modul Zielmodul
     * @param string $dd DD-Name
     * @param string $server Servername
     * @param string $table Tabellenname
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function create_dd(string $modul, string $dd, string $server = 'dbXsystem', string $table = ''): int
    {
        if (!$table) {
            $table = $dd;
        }

        if (!$this->get_table_exist($server, $table)) {
            dbx()->sys_msg('error', 'dd', $dd, 'create_dd', 'table not found');
            return 0;
        }

        $table_meta = $this->normalize_table_record($dd, [
            'server' => $server,
            'table'  => $table,
        ]);

        $fields  = $this->get_db_fields($server, $table);
        $indexes = $this->get_db_indexes($server, $table);

        return $this->save_dd($modul, $dd, $table_meta, $fields, $indexes);
    }
}
