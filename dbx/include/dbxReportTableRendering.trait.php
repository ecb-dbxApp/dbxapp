<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxReportTableRenderingTrait
{
/**
     * Formatiert einen Reportwert anhand der Felddefinition.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function rpt_format($key, $value) {
        $reform = $this->get_report_format($key);

        if ($reform == 'php-date-usr' || $reform == 'date') {
            $value = $this->php_date_usr($value);
        }

        if (
            $reform == 'php-datetime-usr' ||
            $reform == 'date_time' ||
            $reform == 'datetime' ||
            $reform == 'datetime_ms'
        ) {
            $value = $this->php_datetime_usr($value);
        }

        if ($reform == 'html-chars') {
            $value = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if ($value === null) {
            $value = '';
        }

        return $value;
    }

/**
     * Ermittelt die explizite oder automatische Reportformatierung.
     *
     * @param string $key Feldname
     *
     * @return string
     */
    protected function get_report_format($key) {
        $format = $this->_rpt_format;
        $reform = '';

        if (is_array($format)) {
            if (isset($format[$key])) {
                $reform = $format[$key];
            }
        } else {
            $reform = $format;
        }

        if ($reform === '') {
            $reform = $this->get_auto_report_format($key);
        }

        return (string) $reform;
    }

/**
     * Bereitet einen Tabellenwert fuer normale Reportzellen auf.
     *
     * Reportwerte duerfen grundsaetzlich HTML enthalten. Wer Escaping braucht,
     * markiert die Spalte explizit mit dem Reportformat `html-chars`; diese
     * Formatierung wurde bereits in rpt_format() angewendet.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return string
     */
    protected function render_report_cell_value($key, $value) {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

/**
     * Ermittelt automatische Reportformatierung aus DD/FD-Metadaten.
     *
     * Reihenfolge:
     * 1. DD/FD convert
     * 2. DD/FD type
     *
     * @param string $key Feldname
     *
     * @return string
     */
    protected function get_auto_report_format($key) {
        static $cache = array();

        if (!$this->_dd || !$key) {
            return '';
        }

        $dd       = (string) $this->_dd;
        $key      = (string) $key;
        $cache_key = $dd . '|' . $key;

        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        $format  = '';
        $convert = strtolower(trim((string) $this->get_dd($dd, $key, 'convert')));

        if (in_array($convert, array('date', 'php-date-usr'), true)) {
            $format = 'php-date-usr';
            $cache[$cache_key] = $format;
            return $format;
        }

        if (in_array($convert, array('date_time', 'datetime', 'datetime_ms', 'php-datetime-usr'), true)) {
            $format = 'php-datetime-usr';
            $cache[$cache_key] = $format;
            return $format;
        }

        $type = strtolower(trim((string) $this->get_dd($dd, $key, 'type')));

        if ($type == 'date') {
            $format = 'php-date-usr';
            $cache[$cache_key] = $format;
            return $format;
        }

        if (in_array($type, array('datetime', 'date_time', 'datetime_ms', 'timestamp'), true)) {
            $format = 'php-datetime-usr';
            $cache[$cache_key] = $format;
            return $format;
        }

        $cache[$cache_key] = '';

        return '';
    }

/**
     * Fügt reportweite Platzhalter, Objekte und dbxForm-Replacements ein.
     *
     * Neben `{rpt:col_count}` steht `{rpt:colspan}` für alle Spalten außer
     * der letzten Wertespalte bereit. Abschließend werden auch spät während
     * des Record-Laufs mit add_rep() gesetzte Werte über die von dbxForm
     * geerbte replaces()-Pipeline eingesetzt.
     *
     * @param string $content
     *
     * @return string
     */
    public function rpt_merge_obj($content) {
        $count_select = $this->get_count_selects();
        $count_cols   = $this->_table_col_count;
        $label_colspan = max(1, $count_cols - 1);
        $page         = $this->_current_page;
        $page_break   = $this->_page_break;

        $content = str_replace('{rpt:count_sel}', $count_select, $content);
        $content = str_replace('{rpt:col_count}', $count_cols, $content);
        $content = str_replace('{rpt:colspan}', $label_colspan, $content);
        $content = str_replace('{rpt:page}', $page, $content);
        $content = str_replace('{rpt:pagebrak}', $page_break, $content);

        if (is_array($this->_obj)) {
            foreach ($this->_obj as $key => $value) {
                $xkey = '{obj:' . $key . '}';

                if ($value === null) {
                    $value = '';
                }

                $content = str_replace($xkey, $value, $content);
            }
        }

        return $this->replaces($content);
    }

/**
     * Liefert die aktivierten Tabellen-Aktionsdefinitionen.
     *
     * Die Definition existiert zentral nur einmal und wird anschließend
     * sowohl für Header als auch für Row-Buttons verwendet.
     *
     * @return array
     */
    protected function get_table_action_definitions() {
        return array(
            array(
                'type'       => 'expander',
                'enabled'    => (bool) $this->_data_table,
                'header_tpl' => 'tpl_header_expander',
                'row_tpl'    => 'tpl_row_expander',
            ),
            array(
                'type'       => 'edit',
                'enabled'    => (bool) $this->_create_row_edit,
                'header_tpl' => 'tpl_header_edit',
                'row_tpl'    => 'tpl_row_edit',
            ),
            array(
                'type'       => 'copy',
                'enabled'    => (bool) $this->_create_row_copy,
                'header_tpl' => 'tpl_header_copy',
                'row_tpl'    => 'tpl_row_copy',
            ),
            array(
                'type'       => 'show',
                'enabled'    => (bool) $this->_create_row_show,
                'header_tpl' => 'tpl_header_show',
                'row_tpl'    => 'tpl_row_show',
            ),
            array(
                'type'       => 'export',
                'enabled'    => (bool) $this->_create_row_export,
                'header_tpl' => 'tpl_header_export',
                'row_tpl'    => 'tpl_row_export',
            ),
            array(
                'type'       => 'import',
                'enabled'    => (bool) $this->_create_row_import,
                'header_tpl' => 'tpl_header_import',
                'row_tpl'    => 'tpl_row_import',
            ),
            array(
                'type'       => 'download',
                'enabled'    => (bool) $this->_create_row_download,
                'header_tpl' => 'tpl_header_download',
                'row_tpl'    => 'tpl_row_download',
            ),
            array(
                'type'       => 'delete',
                'enabled'    => (bool) $this->_create_row_delete,
                'header_tpl' => 'tpl_header_delete',
                'row_tpl'    => 'tpl_row_delete',
            ),
            array(
                'type'       => 'print',
                'enabled'    => (bool) $this->_create_row_print,
                'header_tpl' => 'tpl_header_print',
                'row_tpl'    => 'tpl_row_print',
            ),
        );
    }

protected function render_simple_table_tpl($file, array $data) {
        if (!isset($this->_table_render_tpl_cache[$file])) {
            $this->_table_render_tpl_cache[$file] = $this->get_tpl($file, array());
        }

        foreach (array('title', 'tooltip', 'label') as $attribute) {
            if (isset($data[$attribute]) && !is_array($data[$attribute])) {
                $data[$attribute] = htmlspecialchars(
                    (string)$data[$attribute],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
            }
        }

        return $this->o_tpl->replaces($this->_table_render_tpl_cache[$file], $data);
    }

/**
     * Liefert getrennte Fenstertitel und HTML-faehige Bedienhinweise fuer
     * automatisch erzeugte Report-Aktionen.
     */
    protected function get_table_action_ui($type): array {
        $language = in_array($this->_dbx_lng, array('de', 'en', 'es'), true)
            ? $this->_dbx_lng
            : 'de';
        $texts = array(
            'de' => array(
                'edit' => array('Datensatz bearbeiten', '<strong>Bearbeiten</strong><br><small>Datensatz im Formular oeffnen</small>'),
                'copy' => array('Datensatz kopieren', '<strong>Kopieren</strong><br><small>Neuen Datensatz als Kopie anlegen</small>'),
                'show' => array('Datensatz anzeigen', '<strong>Anzeigen</strong><br><small>Datensatz schreibgeschuetzt oeffnen</small>'),
                'export' => array('CSV Export', '<strong>Exportieren</strong><br><small>Datensatz als CSV ausgeben</small>'),
                'import' => array('CSV Import', '<strong>Importieren</strong><br><small>Daten aus einer CSV-Datei einlesen</small>'),
                'download' => array('Datei herunterladen', '<strong>Herunterladen</strong><br><small>Datei lokal speichern</small>'),
                'delete' => array('Datensatz loeschen', '<strong>Loeschen</strong><br><small>Datensatz nach Bestaetigung entfernen</small>'),
                'print' => array('Drucken', '<strong>Drucken</strong><br><small>Druckansicht oeffnen</small>'),
                'expander' => array('Details', '<strong>Details</strong><br><small>Zusaetzliche Zeilendaten einblenden</small>'),
            ),
            'en' => array(
                'edit' => array('Edit record', '<strong>Edit</strong><br><small>Open the record in the form</small>'),
                'copy' => array('Copy record', '<strong>Copy</strong><br><small>Create a new record as a copy</small>'),
                'show' => array('View record', '<strong>View</strong><br><small>Open the record read-only</small>'),
                'export' => array('CSV export', '<strong>Export</strong><br><small>Write the record to CSV</small>'),
                'import' => array('CSV import', '<strong>Import</strong><br><small>Read data from a CSV file</small>'),
                'download' => array('Download file', '<strong>Download</strong><br><small>Save the file locally</small>'),
                'delete' => array('Delete record', '<strong>Delete</strong><br><small>Remove the record after confirmation</small>'),
                'print' => array('Print', '<strong>Print</strong><br><small>Open the print view</small>'),
                'expander' => array('Details', '<strong>Details</strong><br><small>Show additional row data</small>'),
            ),
            'es' => array(
                'edit' => array('Editar registro', '<strong>Editar</strong><br><small>Abrir el registro en el formulario</small>'),
                'copy' => array('Copiar registro', '<strong>Copiar</strong><br><small>Crear un registro nuevo como copia</small>'),
                'show' => array('Mostrar registro', '<strong>Mostrar</strong><br><small>Abrir el registro en modo de solo lectura</small>'),
                'export' => array('Exportar CSV', '<strong>Exportar</strong><br><small>Guardar el registro como CSV</small>'),
                'import' => array('Importar CSV', '<strong>Importar</strong><br><small>Leer datos desde un archivo CSV</small>'),
                'download' => array('Descargar archivo', '<strong>Descargar</strong><br><small>Guardar el archivo localmente</small>'),
                'delete' => array('Eliminar registro', '<strong>Eliminar</strong><br><small>Quitar el registro tras confirmarlo</small>'),
                'print' => array('Imprimir', '<strong>Imprimir</strong><br><small>Abrir la vista de impresion</small>'),
                'expander' => array('Detalles', '<strong>Detalles</strong><br><small>Mostrar datos adicionales de la fila</small>'),
            ),
        );

        return $texts[$language][(string)$type] ?? array('', '');
    }

/** Liefert Icon und Verhalten einer einheitlichen Tabellenaktion. */
    protected function get_table_action_presentation($type): array {
        $type = strtolower(trim((string)$type));
        $icons = array(
            'expander' => 'bi-arrows-angle-expand',
            'edit'     => 'bi-pencil',
            'copy'     => 'bi-copy',
            'show'     => 'bi-search',
            'export'   => 'bi-file-earmark-arrow-down',
            'import'   => 'bi-file-earmark-arrow-up',
            'download' => 'bi-download',
            'delete'   => 'bi-trash',
            'print'    => 'bi-printer',
        );
        $defaults = array(
            'icon'       => $icons[$type] ?? 'bi-circle',
            'link_class' => 'btn-inline no-sort',
            'cell_class' => 'dbx-report-action-cell text-center',
            'window'     => in_array($type, array('export', 'import', 'print'), true),
            'width'      => $type === 'print' ? '1024' : ($type === 'export' || $type === 'import' ? '700' : ''),
            'height'     => $type === 'export' || $type === 'import' ? '500' : '',
        );
        $custom = isset($this->_table_action_options[$type]) && is_array($this->_table_action_options[$type])
            ? $this->_table_action_options[$type]
            : array();

        return array_replace($defaults, $custom);
    }

/** Maskiert einen dynamischen Wert fuer ein HTML-Attribut. */
    private function table_action_attr($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

/** Ergänzt die Parameter des universellen Header-Templates. */
    protected function prepare_table_header_action_data($type, array $data): array {
        $ui = $this->get_table_action_ui($type);
        $presentation = $this->get_table_action_presentation($type);
        $data['action_type'] = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$type));
        $data['header_class'] = 'th-' . $data['action_type'] . ' text-center align-middle';
        $data['icon'] = $presentation['icon'];
        $data['title'] = $ui[0];
        $data['tooltip'] = $ui[1];

        return $data;
    }

/** Ergänzt Link, Klassen und optionale Fenster-/Bestätigungsattribute. */
    protected function prepare_table_row_action_data($type, array $data): array {
        $type = strtolower(trim((string)$type));
        $presentation = $this->get_table_action_presentation($type);
        $href = (string)($data['action'] ?? '#');

        if ($type === 'download') {
            $href = '?dbx_modul=dbxDownload&dbx_run1=download&download={file}';
        }

        $record_class = trim((string)($data['class'] ?? ''));
        $classes = trim((string)$presentation['link_class'] . ' ' . $record_class);
        $attributes = array(
            'data-dbx-tooltip="' . $this->table_action_attr($data['tooltip'] ?? '') . '"',
        );

        $uses_window = !empty($presentation['window'])
            || preg_match('/(?:^|\s)(?:dbx-win|openWin)(?:\s|$)/', $record_class) === 1;
        if ($uses_window) {
            $classes .= ' dbx-win';
            $attributes[] = 'data-url="' . $this->table_action_attr($href) . '"';
            $attributes[] = 'data-title="' . $this->table_action_attr($data['title'] ?? '') . '"';
            if ((string)$presentation['width'] !== '') {
                $attributes[] = 'data-width="' . $this->table_action_attr($presentation['width']) . '"';
            }
            if ((string)$presentation['height'] !== '') {
                $attributes[] = 'data-height="' . $this->table_action_attr($presentation['height']) . '"';
            }
        }

        if ($type === 'delete') {
            $language = in_array($this->_dbx_lng, array('de', 'en', 'es'), true) ? $this->_dbx_lng : 'de';
            $confirm_text = array(
                'de' => array('Datensatz löschen', 'Datensatz wirklich löschen?', 'Dieser Vorgang kann nicht rückgängig gemacht werden.'),
                'en' => array('Delete record', 'Really delete the record?', 'This action cannot be undone.'),
                'es' => array('Eliminar registro', '¿Eliminar realmente el registro?', 'Esta acción no se puede deshacer.'),
            )[$language];
            $question = (string)($data['confirm'] ?? '');
            if ($question === '' || $question === 'Datensatz löschen ?') {
                $question = $confirm_text[1];
            }
            $classes .= ' dbxAjax dbxConfirm';
            $attributes[] = 'data-confirm-title="' . $this->table_action_attr($confirm_text[0]) . '"';
            $attributes[] = 'data-confirm="' . $this->table_action_attr($question) . '"';
            $attributes[] = 'data-confirm-hint="' . $this->table_action_attr('<small>' . $confirm_text[2] . '</small>') . '"';
            $attributes[] = 'data-confirm-buttons="yesno"';
        }

        $data['href'] = $this->table_action_attr($href);
        $data['link_class'] = $this->table_action_attr(trim($classes));
        $data['cell_class'] = $this->table_action_attr($presentation['cell_class']);
        $data['icon'] = $this->table_action_attr($presentation['icon']);
        $data['link_attributes'] = implode(' ', $attributes);
        $data['accessible_title'] = htmlspecialchars(
            (string)($data['title'] ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return $data;
    }

/**
     * Rendert den zentralen Header-Buttonblock.
     *
     * @return array
     */
    protected function render_table_header_action_block() {
        $html  = '';
        $count = 0;

        foreach ($this->get_table_action_definitions() as $def) {
            if (!$def['enabled']) {
                continue;
            }

            $file = $this->_table_tpls[$def['header_tpl']];
            $data = array(
                'name'  => 'ID',
                'class' => 'no-sort',
            );
            if ($file === 'table_header_action' || $file === 'dbx|table_header_action') {
                $data = $this->prepare_table_header_action_data($def['type'], $data);
            }
            $tpl = $this->render_simple_table_tpl($file, $data);

            $html .= $tpl . "\n";
            $count++;
        }

        return array(
            'html'  => $html,
            'count' => $count,
        );
    }

/**
     * Baut das Standard-Datenarray für Row-Aktions-Templates.
     *
     * @param string $type
     * @param array  $record
     *
     * @return array
     */
    protected function get_table_row_action_data($type, array $record) {
        $rid = $this->get_record_rid($record, -1);
        $action_ui = $this->get_table_action_ui($type);
        $dat = array(
            'rid'     => $rid,
            'value'   => $rid,
            'action'  => $this->get_report_action_url(),
            'class'   => 'no-sort',
            'title'   => $action_ui[0],
            'tooltip' => $action_ui[1],
        );

        if ($type === 'download') {
            $dat['href_dir_file'] = dbx()->get_modul_var('href_dir_file', '', '*');
        }

        if ($type === 'copy') {
            $dat['confirm'] = $this->_msg_confirm_copy;
        }

        if ($type === 'delete') {
            $dat['confirm'] = $this->_msg_confirm_delete;
        }

        if ($this->_fid != 'pagination') {
            $data = array(
                'type'   => $type,
                'record' => $record,
                'data'   => $dat,
            );

            $data = $this->callback('row_action_data', $data);

            if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
                $dat = $data['data'];
            }
        }

        if ($type === 'delete') {
            $web_app = dbx()->get_system_obj('dbxWebApp');
            $delete_rid = (int)($dat['rid'] ?? $rid);
            $delete_params = array(
                'dbx_do' => 'row_delete',
                'rid' => $delete_rid,
            );

            $action = trim((string)($dat['action'] ?? ''));
            if ($action !== '' && $action !== '#' && stripos($action, 'javascript:') !== 0) {
                $action = $web_app->append_route_params($action, $delete_params);
                $dat['action'] = dbx()->action_url($action);
            }

            // Spezialisierte Row-Templates verwenden teilweise delete_url
            // statt action. Auch diese Variante wird zentral normalisiert.
            $delete_url = trim((string)($dat['delete_url'] ?? ''));
            if ($delete_url !== '' && $delete_url !== '#' && stripos($delete_url, 'javascript:') !== 0) {
                $delete_url = $web_app->append_route_params($delete_url, $delete_params);
                $dat['delete_url'] = dbx()->action_url($delete_url);
            }
        }

        return $dat;
    }

/**
     * Rendert den zentralen Row-Buttonblock.
     *
     * @param array $record
     *
     * @return string
     */
    protected function render_table_row_action_block(array $record) {
        $html = '';

        foreach ($this->get_table_action_definitions() as $def) {
            if (!$def['enabled']) {
                continue;
            }

            $file = $this->_table_tpls[$def['row_tpl']];
            $dat  = $this->get_table_row_action_data($def['type'], $record);
            if ($file === 'table_row_action' || $file === 'dbx|table_row_action') {
                $dat = $this->prepare_table_row_action_data($def['type'], $dat);
            }
            $tpl  = $this->render_simple_table_tpl($file, $dat);

            $html .= $tpl . "\n";
        }

        return $html;
    }

/**
     * Rendert die Header-Checkbox für die sichtbaren Rows.
     *
     * @param array  $auto_flds
     * @param string $fld_id
     * @param string $class
     * @param array  $select_state
     *
     * @return string
     */
    protected function render_table_header_select(array $auto_flds, $fld_id, $class, array $select_state) {
        $file    = $this->_table_tpls['tpl_header_select'];
        $name    = isset($auto_flds[$fld_id]) ? $auto_flds[$fld_id] : 'xID';
        $checked = '';

        if (!empty($select_state['header_checked'])) {
            $checked = 'checked="checked"';
        }

        return $this->render_simple_table_tpl($file, array(
            'name'         => $name,
            'checked'      => $checked,
            'class'        => $class,
            'header_state' => isset($select_state['header_state']) ? $select_state['header_state'] : 'none',
        ));
    }

/**
     * Rendert die Row-Checkbox.
     *
     * @param array  $record
     * @param string $class
     *
     * @return string
     */
    protected function render_table_row_select(array $record, $class) {
        $file    = $this->_table_tpls['tpl_row_select'];
        $name    = $this->_fid . '_select';
        $rid     = $this->get_record_select_key($record);
        $checked = $this->check_is_multiselect($rid);

        if ($checked) {
            $this->_post[$name] = 1;
        }

        return $this->render_simple_table_tpl($file, array(
            'name'    => $name,
            'value'   => $rid,
            'rid'     => $rid,
            'checked' => $checked,
            'class'   => $class,
            'tooltip' => '',
        ));
    }

/**
     * Rendert die automatischen Header-Datenspalten.
     *
     * @param array  $auto_flds
     * @param string $fld_id
     *
     * @return array
     */
    protected function render_table_header_data_columns(array $auto_flds, $fld_id) {
        $html  = '';
        $count = 0;

        foreach ($auto_flds as $key => $value) {
            $skip = 0;

            if ($this->_create_row_select && $key == $fld_id) {
                $skip = 1;
            }

            if (!$skip && $value > '') {
                $file  = $this->_table_tpls['tpl_header_col'];
                $class = $this->get_class_header($key);
                $style = $this->get_style_header($key);

                $tpl = $this->render_simple_table_tpl($file, array(
                    'value' => $value,
                    'name'  => $key,
                    'class' => $class,
                    'style' => $style,
                ));

                $html .= $tpl . "\n";
                $count++;
            }
        }

        return array(
            'html'  => $html,
            'count' => $count,
        );
    }

/**
     * Rendert die automatischen Body-Datenspalten.
     *
     * @param array  $record
     * @param array  $auto_flds
     * @param string $fld_id
     * @param string $defaultClass
     *
     * @return string
     */
    protected function render_table_row_data_columns(array $record, array $auto_flds, $fld_id, $default_class = 'auto-fld') {
        $html = '';

        foreach ($auto_flds as $no => $key) {
            $xkey  = '';
            $value = '-?-';
            $label = $auto_flds[$no];
            $skip  = 0;

            if (isset($record[$key])) {
                $xkey = $key;
            } elseif (isset($record[$no])) {
                $xkey = $no;
            }

            if ($this->_create_row_select && $xkey == $fld_id) {
                $skip = 1;
            }

            if (!$skip && $label > '') {
                if ($xkey) {
                    $value = $record[$xkey];
                    $value = $this->rpt_format($xkey, $value);
                }

                $class = $default_class;

                if ($default_class !== 'auto-fld') {
                    $class = $this->get_class_body($xkey);
                }

                $class = trim($class . ' dbx-report-cell');
                $value = $this->render_report_cell_value($xkey, $value);

                $tpl = $this->render_simple_table_tpl($this->_table_tpls['tpl_row_col'], array(
                    'value'   => $value,
                    'class'   => $class,
                    'label'   => $label,
                    'tooltip' => '',
                ));

                $html .= $tpl . "\n";
            }
        }

        return $html;
    }

/**
     * Erzeugt den Report-Header.
     *
     * @param string $content
     *
     * @return string
     */
    public function get_report_header($content = '') {
        if (!$content) {
            $content = $this->_header;
        }

        $this->_current_page++;
        $col_count    = 0;
        $auto_flds    = $this->_auto_flds;
        $auto_mode    = $this->_auto_mode;
        $select_state = $this->get_visible_multi_select_state();

        if (!is_array($auto_flds)) {
            if (is_string($auto_flds) && $auto_flds !== '') {
                $auto_flds = explode(',', $auto_flds);
            } else {
                $auto_flds = array();
            }
        }

        $pos = strpos($content, '[rpt:row]');

        if ($pos !== false) {
            $row    = '';
            $fld_id = $this->_fld_id;

            if ($auto_mode == 'table' && is_array($auto_flds)) {
                $button_block = $this->render_table_header_action_block();
                $column_block = $this->render_table_header_data_columns($auto_flds, $fld_id);

                if ($this->_table_buttons != 'left') {
                    if ($this->_create_row_select) {
                        $row .= $this->render_table_header_select(
                            $auto_flds,
                            $fld_id,
                            $this->get_class_header(isset($auto_flds[$fld_id]) ? $auto_flds[$fld_id] : 'xID'),
                            $select_state
                        ) . "\n";
                        $col_count++;
                    }

                    $row      .= $column_block['html'];
                    $col_count += $column_block['count'];

                    $row      .= $button_block['html'];
                    $col_count += $button_block['count'];
                } else {
                    $row      .= $button_block['html'];
                    $col_count += $button_block['count'];

                    if ($this->_create_row_select) {
                        $row .= $this->render_table_header_select(
                            $auto_flds,
                            $fld_id,
                            'no-sort',
                            $select_state
                        ) . "\n";
                        $col_count++;
                    }

                    $row      .= $column_block['html'];
                    $col_count += $column_block['count'];
                }

                $this->_table_col_count = $col_count;
            }

            $content = str_replace('[rpt:row]', $row, $content);
        }

        $content = $this->run_header($content);

        return $content;
    }

/**
     * Erzeugt den HTML-Body für klassische Reportmodi.
     *
     * @return string
     */
    public function get_report_body(): string {
        $content   = '';
        $line      = '';
        $loop      = 0;

        $auto_flds = $this->_auto_flds;
        $auto_mode = $this->_auto_mode;

        if (!is_array($auto_flds)) {
            if (is_string($auto_flds) && $auto_flds !== '') {
                $auto_flds = explode(',', $auto_flds);
            } else {
                $auto_flds = array();
            }
        }

        if (is_array($this->_rdata)) {
            foreach ($this->_rdata as $recnum => $record) {
                $loop++;
                $line = $this->_body;

                $this->_record = $record;
                $record        = ($this->_fid != 'pagination') ? $this->callback('next_record', $record) : $record;
                $this->_record = $record;

                if (!is_array($record)) {
                    continue;
                }

                $line          = $this->run_body($line);
                $record        = $this->_record;

                if (!is_array($record)) {
                    continue;
                }

                $fld_id = $this->_fld_id;
                $pos    = strpos($line, '[rpt:row]');

                if ($pos !== false && $this->_rdata_inline) {
                    $inline = $this->_body_inline;
                    return str_replace('[rpt:row]', $inline, $line);
                }

                if ($pos !== false) {
                    $row = '';

                    if ($auto_mode == 'table' && is_array($auto_flds)) {
                        $button_block = $this->render_table_row_action_block($record);

                        if ($this->_table_buttons != 'left') {
                            if ($this->_create_row_select) {
                                $row .= $this->render_table_row_select($record, 'no-sort') . "\n";
                            }

                            $row .= $this->render_table_row_data_columns($record, $auto_flds, $fld_id, 'auto-fld');
                            $row .= $button_block;
                        } else {
                            $row .= $button_block;

                            if ($this->_create_row_select) {
                                $row .= $this->render_table_row_select($record, 'no-sort') . "\n";
                            }

                            $row .= $this->render_table_row_data_columns($record, $auto_flds, $fld_id, 'body');
                        }
                    }

                    $line = str_replace('[rpt:row]', $row, $line);
                }

                $tr_class  = $this->get_class_tr($record);
                $tr_class .= ($loop % 2 != 0) ? ' odd' : ' even';

                $line = str_replace('{tr-class}', $tr_class, $line);

                if (is_array($record)) {
                    foreach ($record as $field => $value) {
                        $field_name = '{' . $field . '}';

                        if (strpos($line, $field_name) === false) {
                            continue;
                        }

                        $value = $this->rpt_format($field, $value);

                        if (!is_array($value) && !is_object($value)) {
                            if ($value === null) {
                                $value = '';
                            }

                            $line = str_replace($field_name, (string) $value, $line);
                        }
                    }
                }

                $col_count = $this->_table_col_count;
                $line      = str_replace('{rpt:col_count}', $col_count, $line);

                if (strpos($line, '{r}') !== false) {
                    $r    = dbx()->next_id(1);
                    $line = str_replace('{r}', $r, $line);
                }

                $content .= $line;

                if ($this->_rdata_inline) {
                    break;
                }
            }
        }

        return $content;
    }

public function get_class_tr($record) {
        $class    = '';
        $activ_id = $this->_activ_id;

        if (!$activ_id) {
            $activ_id = $this->get_activ_id();
        }

        if ($activ_id) {
            $key = $this->_fld_id;

            if ($key && isset($record[$key])) {
                if ($activ_id == $record[$key]) {
                    $class = 'table-active';
                }
            }
        }

        return $class;
    }

public function get_report_footer() {
        $content   = $this->_footer;
        $col_count = $this->_table_col_count;

        $content = str_replace('{rpt:col_count}', $col_count, $content);
        $content = str_replace('{rpt:colspan}', max(1, $col_count - 1), $content);
        $content = $this->run_footer($content);

        return $content;
    }

public function split_tpl($report) {
        $report_part      = explode('<hr class="dbx_split">', $report);
        $report_header    = '';
        $report_body      = '';
        $report_footer    = '';
        $next_header_page = '';
        $next_footer_page = '';
        $count = count($report_part);

        if ($count > 0) {
            $report_body = $report_part[0];
        }

        if ($count > 1) {
            $report_header = $report_part[0];
            $report_body   = $report_part[1];
        }

        if ($count > 2) {
            $report_header    = $report_part[0];
            $report_body      = $report_part[1];
            $report_footer    = $report_part[2];
            $next_header_page = $report_part[0];
            $next_footer_page = $report_part[2];
        }

        if ($count > 5) {
            $next_header_page = $report_part[3];
            $next_footer_page = $report_part[5];
        }

        $this->_header           = $report_header;
        $this->_body             = $report_body;
        $this->_footer           = $report_footer;
        $this->_footer_next_page = $next_footer_page;
        $this->_header_next_page = $next_header_page;
    }

/**
     * Aktiviert die Report-JS-Lib automatisch fuer Reports mit Row-Checkboxen.
     *
     * Aeltere Report-Templates haben zwar class="dbxReport", aber noch kein
     * data-dbx="lib=report". Die Row-Selection muss trotzdem zentral und
     * flackerfrei laufen, ohne jedes Template einzeln nachzuziehen.
     *
     * @param string $content
     *
     * @return string
     */
    protected function ensure_report_select_feature($content) {
        if ((!$this->_create_row_select && !$this->_create_sel_flds) || !is_string($content) || stripos($content, 'dbxReport') === false) {
            return $content;
        }

        return preg_replace_callback(
            '/<div\b(?=[^>]*\bclass\s*=\s*(["\'])[^"\']*\bdbxReport\b[^"\']*\1)([^>]*)>/i',
            function ($match) {
                $tag = $match[0];

                if (preg_match('/\bdata-dbx\s*=\s*(["\'])(.*?)\1/i', $tag, $data_match)) {
                    $value = trim($data_match[2]);

                    if (stripos($value, 'lib=report') !== false) {
                        return $tag;
                    }

                    $value  = ($value === '') ? 'lib=report|form=0' : $value . '||lib=report|form=0';
                    $new_att = 'data-dbx=' . $data_match[1] . $value . $data_match[1];

                    return str_replace($data_match[0], $new_att, $tag);
                }

                return substr($tag, 0, -1) . ' data-dbx="lib=report|form=0">';
            },
            $content,
            1
        );
    }
}
