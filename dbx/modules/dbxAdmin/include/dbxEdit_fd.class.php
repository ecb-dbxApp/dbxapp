<?php
namespace dbx\dbxAdmin;

require_once __DIR__ . '/dbxEditorRecords.class.php';
require_once __DIR__ . '/dbxEditorPresentation.trait.php';

/**
 * Editor fuer FD-Dateien.
 *
 * FD-Dateien enthalten `$fields[]` und sprachabhängige `$messages` und liegen
 * unter:
 * dbx/modules/{modul}/fd/{fd}.fd.php
 */
class dbxEdit_fd
{
    use dbxEditorPresentationTrait;

    private $_admin_modul = 'dbxAdmin';
    private $_fd_field = 'dbxAdmin|ddedit-field';
    private $_texts = null;

    /**
     * Sprachabhängiger Meldungskontext der aktiven FD.
     *
     * Es werden nur die FD-Meldungen geladen; ein zweites Formular wird weder
     * initialisiert noch gerendert.
     *
     * @return \dbxForm
     */
    private function texts()
    {
        if ($this->_texts instanceof \dbxForm) {
            return $this->_texts;
        }

        dbx()->get_system_obj('dbxForm', 'use');
        $this->_texts = new \dbxForm();
        $this->_texts->set_form_help_enabled(false);
        $this->_texts->set_field_definition($this->_fd_field);
        $this->_texts->load_fd_messages();

        return $this->_texts;
    }

    public function run()
    {
        $work = dbx()->get_modul_var('dbx_run2', '');

        switch ($work) {
            case 'create_form_fd':
                return $this->create_form_fd();

            case 'delete_field':
                return $this->delete_field();

            case 'save_field_order':
                return $this->save_field_order();

            case 'create_from_dd':
                return $this->create_from_dd();

            case '':
            case 'editor':
            default:
                return $this->run_editor();
        }
    }

    private function run_editor()
    {
        $texts = $this->texts();
        list($modul, $fd) = $this->fd_params_from_request();

        if (!$modul) {
            return $this->alert('warning', $texts->get_fd_message('fd_module_missing'));
        }

        if (!$fd) {
            return $this->create_from_dd();
        }

        $model = $this->load_model($modul, $fd);
        if (!$model) {
            return $this->alert(
                'danger',
                $texts->format_fd_message(
                    'fd_not_readable',
                    array('fd' => dbx()->esc($modul . '|' . $fd))
                )
            );
        }

        $instance_id = $this->instance_id($modul . '_' . $fd);
        $work_target_id = 'dbx_fdedit_work_' . $instance_id;
        $fields = array_values((array)($model['fields'] ?? array()));
        $work_content = count($fields)
            ? $this->create_form_fd($modul, $fd, '0', $model)
            : $this->create_form_fd($modul, $fd, 'new', $model);

        $data = array(
            'i'                  => $instance_id,
            'modul'              => $modul,
            'fd'                 => $fd,
            'message'            => '',
            'work_target_id'     => $work_target_id,
            'create_from_dd_url' => $this->build_url('create_from_dd', $modul, $fd),
            'work_content'       => $work_content,
            'fields_order_report'=> $this->create_fields_order_report($modul, $fd, $model, $work_target_id),
        );

        $help = dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
        $o_tpl = dbx()->get_system_obj('dbxTPL');
        $reload_action = $o_tpl->get_tpl('dbx|button-bar-reload-ajax', array(
            'bar_reload_href'    => '?dbx_modul=dbxAdmin&dbx_run1=edit_fd&modul=' . rawurlencode($modul) . '&fd=' . rawurlencode($fd),
            'bar_reload_target'  => 'dbx_fdedit_' . $instance_id,
            'bar_reload_replace' => 'target',
        ));
        $bar_data = $help->module_bar_template_data('edit_fd', $reload_action);
        $bar_data['bar_title'] = $texts->format_fd_message(
            'fd_frame_title',
            array('fd' => dbx()->esc($modul . '|' . $fd))
        );
        $bar_data['bar_class'] = 'dbx-bar--module dbx-ddedit-head';
        $data = array_merge($data, $bar_data);

        return $o_tpl->get_tpl($this->_admin_modul . '|fdedit-frame', $data);
    }

    private function create_form_fd($modul = '', $fd = '', $field_pos = null, $model = array())
    {
        $texts = $this->texts();
        if (!$modul || !$fd) {
            list($modul, $fd) = $this->fd_params_from_request();
        }

        if ($field_pos === null) {
            $field_pos = dbx()->get_modul_var('field_pos', 'new');
        }

        if (!$model) {
            $model = $this->load_model($modul, $fd);
        }

        if (!$model) {
            return $this->alert(
                'danger',
                $texts->format_fd_message(
                    'fd_not_found',
                    array('fd' => dbx()->esc($modul . '|' . $fd))
                )
            );
        }

        $fields = array_values((array)($model['fields'] ?? array()));
        $is_new = ((string)$field_pos === 'new');

        if ($is_new) {
            $data = dbxEditorRecords::default_field();
        } else {
            $pos = (int)$field_pos;
            if (!isset($fields[$pos]) || !is_array($fields[$pos])) {
                return $this->alert(
                    'warning',
                    $texts->format_fd_message(
                        'fd_position_not_found',
                        array('position' => dbx()->esc((string)$field_pos))
                    )
                );
            }
            $data = $fields[$pos];
        }

        $data['modul'] = $modul;
        $data['dd'] = $fd;
        $data['field_pos'] = (string)$field_pos;
        $data['old_name'] = (string)($data['name'] ?? '');

        $o_form = dbx()->get_system_obj('dbxForm');
        $o_form->init('fdedit_field_' . $this->safe_id($modul . '_' . $fd . '_' . (string)$field_pos), 'ddedit-field-form');
        $o_form->set_field_definition($this->_fd_field);
        $o_form->load_fd_messages();
        $o_form->set_data($data);
        $o_form->set_action($this->build_url('create_form_fd', $modul, $fd, array('field_pos' => (string)$field_pos)));
        $o_form->_msg_info = $is_new
            ? $o_form->format_fd_message(
                'fd_edit_new',
                array('fd' => dbx()->esc($fd))
            )
            : $o_form->format_fd_message(
                'fd_edit_existing',
                array(
                    'field' => dbx()->esc(
                        (string)($data['name'] ?? $field_pos)
                    ),
                )
            );
        $o_form->add_flds();

        if ($o_form->submit()) {
            if (!$o_form->errors()) {
                $field = $this->merge_record($data, $o_form->validated_post(), $this->field_keys());
                $field = $this->strip_editor_keys($field);

                $message = '';
                if (!$this->validate_field_record($field, $fields, $is_new ? -1 : (int)$field_pos, $message, $o_form)) {
                    $o_form->_msg_error = $message;
                    return $o_form->run();
                }

                if ($is_new) {
                    $fields[] = $field;
                    $field_pos = count($fields) - 1;
                    $is_new = false;
                } else {
                    $fields[(int)$field_pos] = $field;
                }

                $model['fields'] = array_values($fields);
                $ok = $this->save_model($modul, $fd, $model);

                if ($ok) {
                    $field['modul'] = $modul;
                    $field['dd'] = $fd;
                    $field['field_pos'] = (string)$field_pos;
                    $field['old_name'] = (string)($field['name'] ?? '');

                    $o_form->set_data($field);
                    $o_form->set_action($this->build_url('create_form_fd', $modul, $fd, array('field_pos' => (string)$field_pos)));
                    $o_form->_msg_success = $o_form->format_fd_message(
                        'fd_field_saved',
                        array(
                            'field' => dbx()->esc(
                                (string)($field['name'] ?? $field_pos)
                            ),
                            'fd' => dbx()->esc($fd),
                        )
                    );
                } else {
                    $o_form->_msg_error = $o_form->format_fd_message(
                        'fd_field_save_error',
                        array(
                            'field' => dbx()->esc(
                                (string)($field['name'] ?? $field_pos)
                            ),
                            'fd' => dbx()->esc($fd),
                        )
                    );
                }
            } else {
                $o_form->_msg_error = $o_form->format_fd_message(
                    'fd_field_check',
                    array(
                        'field' => dbx()->esc(
                            (string)($data['name'] ?? $field_pos)
                        ),
                    )
                );
            }
        }

        $delete_url = $this->build_url('delete_field', $modul, $fd, array('field_pos' => (string)$field_pos));

        return str_replace(
            '&dbx_run2=delete_field&modul={modul}&dd={dd}&field_pos={field_pos}',
            dbx()->esc($delete_url),
            $o_form->run()
        );
    }

    private function create_fields_order_report($modul, $fd, $model, $target_id = '')
    {
        $rows = array();
        foreach (array_values((array)($model['fields'] ?? array())) as $pos => $field) {
            if (!is_array($field)) {
                continue;
            }

            $row = $this->field_row_defaults();
            foreach ($field as $key => $value) {
                $row[$key] = is_array($value) ? implode(',', $value) : (string)$value;
            }

            $row['modul'] = $modul;
            $row['dd'] = $fd;
            $row['fd'] = $fd;
            $row['field_pos'] = (string)$pos;
            $row['sort_no'] = (string)($pos + 1);
            $row['target_id'] = $target_id;
            $row['form_url'] = $this->build_url('create_form_fd', $modul, $fd, array('field_pos' => (string)$pos));
            $rows[] = $row;
        }

        $data = array(
            'modul'         => $modul,
            'fd'            => $fd,
            'dd'            => $fd,
            'count'         => count($rows),
            'target_id'     => $target_id,
            'new_field_url' => $this->build_url('create_form_fd', $modul, $fd, array('field_pos' => 'new')),
        );

        $o_report = dbx()->get_system_obj('dbxReport');
        $o_report->init('fdedit_fields_order_' . $this->safe_id($modul . '_' . $fd), 'fdedit-fields-order-report');
        $o_report->set_mode('tpl');
        $o_report->set_data($data);
        $o_report->_replaces = $data;
        $o_report->_rdata = $rows;
        $o_report->_rcount = count($rows);
        $o_report->_rrows = 'auto';
        $o_report->_pages = false;

        return $o_report->run();
    }

    private function delete_field()
    {
        $texts = $this->texts();
        list($modul, $fd) = $this->fd_params_from_request();
        $field_pos = (int)dbx()->get_modul_var('field_pos', -1);

        $model = $this->load_model($modul, $fd);
        if (!$model) {
            return $this->alert(
                'danger',
                $texts->format_fd_message(
                    'fd_not_found',
                    array('fd' => dbx()->esc($modul . '|' . $fd))
                )
            );
        }

        $fields = array_values((array)($model['fields'] ?? array()));
        if (!isset($fields[$field_pos])) {
            return $this->alert('warning', $texts->get_fd_message('fd_field_not_found'));
        }

        $name = (string)($fields[$field_pos]['name'] ?? $field_pos);
        unset($fields[$field_pos]);
        $model['fields'] = array_values($fields);

        if ($this->save_model($modul, $fd, $model)) {
            return $this->alert(
                'success',
                $texts->format_fd_message(
                    'fd_field_deleted',
                    array('field' => dbx()->esc($name))
                )
            );
        }

        return $this->alert(
            'danger',
            $texts->format_fd_message(
                'fd_field_delete_error',
                array('field' => dbx()->esc($name))
            )
        );
    }

    private function save_field_order()
    {
        $texts = $this->texts();
        list($modul, $fd) = $this->fd_params_from_request();
        $order = $this->parse_order(dbx()->get_modul_var('order', array()));

        $model = $this->load_model($modul, $fd);
        if (!$model) {
            dbx()->json_response(array(
                'ok' => 0,
                'msg' => $texts->format_fd_message(
                    'fd_not_found',
                    array('fd' => $modul . '|' . $fd)
                ),
            ));
        }

        $new = dbxEditorRecords::reorder(array_values((array)($model['fields'] ?? array())), $order);
        if ($new === false) {
            dbx()->json_response(array(
                'ok' => 0,
                'msg' => $texts->get_fd_message('fd_invalid_order'),
            ));
        }

        $model['fields'] = $new;
        $ok = $this->save_model($modul, $fd, $model);

        dbx()->json_response(array(
            'ok'    => $ok ? 1 : 0,
            'msg'   => $texts->get_fd_message(
                $ok ? 'fd_order_saved' : 'fd_order_save_error'
            ),
            'count' => count($new),
        ));

        return '';
    }

    /**
     * Erstellt eine FD aus einer vorhandenen DD.
     *
     * Die Eingaben, Pflichtfeldfehler und der CSRF-Schutz laufen vollständig
     * über dbxForm. Erst nach erfolgreicher Formularvalidierung werden Namen
     * normalisiert, die Quell-DD gelesen und die FD-Datei geschrieben.
     */
    private function create_from_dd()
    {
        $texts = $this->texts();
        list($modul, $fd) = $this->fd_params_from_request();

        $source_modul = $this->sanitize_name(dbx()->get_modul_var('source_modul', $modul ?: 'dbx'));
        $source_dd = $this->sanitize_name(dbx()->get_modul_var('source_dd', ''));
        $target_modul = $this->sanitize_name(dbx()->get_modul_var('target_modul', $modul ?: 'dbx'));
        $target_fd = $this->sanitize_name(dbx()->get_modul_var('target_fd', $fd));
        $overwrite = (string)dbx()->get_modul_var('overwrite', '') === '1';

        $o_form = dbx()->get_system_obj('dbxForm');
        $o_form->init(
            'fd-create-from-dd-' . $this->safe_id($target_modul . '-' . $target_fd),
            'fdedit-create-from-dd'
        );
        $o_form->set_field_definition($this->_fd_field);
        $o_form->load_fd_messages();
        $o_form->set_action('?dbx_modul=' . $this->_admin_modul .
            '&dbx_run1=edit_fd&dbx_run2=create_from_dd&modul=' . rawurlencode($target_modul) .
            '&fd=' . rawurlencode($target_fd));
        // init() legt den Security-Wert in _data ab; deshalb Vorgaben ergänzen
        // und den von dbxForm erzeugten Wert nicht durch ein neues Array ersetzen.
        $o_form->merge_data(array(
            'source_modul' => $source_modul,
            'source_dd'    => $source_dd,
            'target_modul' => $target_modul,
            'target_fd'    => $target_fd,
            'overwrite'    => $overwrite ? 1 : 0,
        ));
        $o_form->_msg_info = $o_form->get_fd_message('create_info');

        $o_form->add_fld(
            'source_modul',
            'text-label',
            label: $o_form->get_fd_message('label_source_module'),
            rules: 'parameter|min=1|max=64',
            errormsg: $o_form->get_fd_message('error_source_module'),
            dd: ''
        );
        $o_form->add_fld(
            'source_dd',
            'text-label',
            label: $o_form->get_fd_message('label_source_dd'),
            rules: 'parameter|min=1|max=64',
            errormsg: $o_form->get_fd_message('error_source_dd'),
            dd: ''
        );
        $o_form->add_fld(
            'target_modul',
            'text-label',
            label: $o_form->get_fd_message('label_target_module'),
            rules: 'parameter|min=1|max=64',
            errormsg: $o_form->get_fd_message('error_target_module'),
            dd: ''
        );
        $o_form->add_fld(
            'target_fd',
            'text-label',
            label: $o_form->get_fd_message('label_target_fd'),
            rules: 'parameter|min=1|max=64',
            errormsg: $o_form->get_fd_message('error_target_fd'),
            dd: ''
        );
        $o_form->add_fld(
            'overwrite',
            'checkbox-label',
            label: $o_form->get_fd_message('label_overwrite'),
            rules: 'int',
            dd: ''
        );

        if ($o_form->submit()) {
            if ($o_form->errors()) {
                $o_form->_msg_error = $o_form->get_fd_message('required_fields');
            } else {
                $source_modul = $this->sanitize_name($o_form->get_post('source_modul', '', 'parameter|min=1|max=64'));
                $source_dd = $this->sanitize_name($o_form->get_post('source_dd', '', 'parameter|min=1|max=64'));
                $target_modul = $this->sanitize_name($o_form->get_post('target_modul', '', 'parameter|min=1|max=64'));
                $target_fd = $this->sanitize_name($o_form->get_post('target_fd', '', 'parameter|min=1|max=64'));
                $overwrite = (int)$o_form->get_post('overwrite', 0, 'int') === 1;

                if ($this->fd_file_exists($target_modul, $target_fd) && !$overwrite) {
                    $o_form->add_fld_error(
                        'overwrite',
                        $o_form->get_fd_message('target_exists_field')
                    );
                    $o_form->_msg_error = $o_form->get_fd_message('target_exists');
                } else {
                    $o_dd = dbx()->get_system_obj('dbxDD');
                    $dd_model = $o_dd->get_dd_model($source_modul . '|' . $source_dd);
                    $fields = is_array($dd_model['fields'] ?? null) ? array_values($dd_model['fields']) : array();

                    if (!count($fields)) {
                        $o_form->add_fld_error(
                            'source_dd',
                            $o_form->get_fd_message('source_no_fields_field')
                        );
                        $o_form->_msg_error = $o_form->get_fd_message('source_invalid');
                    } elseif ($this->save_model($target_modul, $target_fd, array('fields' => $fields))) {
                        $url = '?dbx_modul=' . $this->_admin_modul .
                            '&dbx_run1=edit_fd&modul=' . rawurlencode($target_modul) .
                            '&fd=' . rawurlencode($target_fd);
                        $o_form->_msg_success =
                            $o_form->format_fd_message(
                                'fd_created',
                                array(
                                    'fd' => dbx()->esc($target_modul . '|' . $target_fd),
                                    'dd' => dbx()->esc($source_modul . '|' . $source_dd),
                                )
                            ) . ' ' .
                            '<a class="btn btn-sm btn-primary ms-2" href="' . dbx()->esc($url) .
                            '">' . $o_form->get_fd_message('edit_fd_action') . '</a>';
                    } else {
                        $o_form->_msg_error = $o_form->get_fd_message('fd_write_error');
                        $o_form->add_fld_error(
                            'target_fd',
                            $o_form->get_fd_message('fd_write_field_error')
                        );
                    }
                }
            }
        }

        return $o_form->run();
    }

    private function load_model($modul, $fd)
    {
        if (!$modul || !$fd) {
            return array();
        }

        $file = $this->fd_file_path($modul, $fd);
        if (!is_file($file) || !is_readable($file)) {
            return array();
        }

        $fields = array();
        $field = array();
        $messages = array();
        include $file;

        return array(
            'fields' => is_array($fields) ? array_values($fields) : array(),
            'messages' => $this->normalize_fd_messages(
                is_array($messages) ? $messages : array()
            ),
        );
    }

    private function save_model($modul, $fd, $model)
    {
        if (!$modul || !$fd || !is_array($model)) {
            return 0;
        }

        $fields = is_array($model['fields'] ?? null) ? array_values($model['fields']) : array();
        $messages = $this->normalize_fd_messages(
            is_array($model['messages'] ?? null) ? $model['messages'] : array()
        );
        $dir = dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/fd/';
        $dir = dbx()->os_path($dir);

        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return 0;
        }

        $file = $this->fd_file_path($modul, $fd);
        $this->backup_fd_file($modul, $fd);

        $content = "<?php\n\n";
        $content .= "\$messages = array();\n";
        foreach ($messages as $message_key => $message_value) {
            if (!is_scalar($message_value)) {
                continue;
            }
            $content .= "\$messages[" . var_export((string)$message_key, true) . '] = '
                . var_export((string)$message_value, true) . ";\n";
        }
        if (count($messages) > 0) {
            $content .= "\n";
        }
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $field = $this->normalize_field_for_write($field);
            $content .= "\$field = array();\n";
            foreach ($field as $key => $value) {
                $content .= "\$field[" . var_export((string)$key, true) . "]=" . var_export($value, true) . ";\n";
            }
            $content .= "\$fields[]=\$field;\n\n";
        }

        return file_put_contents($file, $content) !== false ? 1 : 0;
    }

    /** Entfernt globale Formularmeldungen aus dem modulspezifischen FD-Modell. */
    private function normalize_fd_messages(array $messages): array
    {
        unset(
            $messages['save_success'],
            $messages['save_error']
        );

        return $messages;
    }

    private function normalize_field_for_write($field)
    {
        $field = $this->strip_editor_keys($field);
        $o_dd = dbx()->get_system_obj('dbxDD');
        if (is_object($o_dd) && method_exists($o_dd, 'normalize_dd_field')) {
            return $o_dd->normalize_dd_field($field);
        }

        $out = array();
        foreach ($this->field_keys() as $key) {
            if (array_key_exists($key, $field)) {
                $out[$key] = $field[$key];
            }
        }
        return $out;
    }

    private function backup_fd_file($modul, $fd)
    {
        $file = $this->fd_file_path($modul, $fd);
        if (!is_file($file)) {
            return;
        }

        $dir = dirname($file) . DIRECTORY_SEPARATOR . '_backup';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (is_dir($dir)) {
            copy($file, $dir . DIRECTORY_SEPARATOR . $fd . '.' . date('Ymd-His') . '.fd.php');
        }
    }

    private function fd_params_from_request()
    {
        $modul = $this->sanitize_name(dbx()->get_modul_var('modul', ''));
        $fd = $this->sanitize_name(dbx()->get_modul_var('fd', ''));

        if (!$fd) {
            $fd = $this->sanitize_name(dbx()->get_modul_var('dd', ''));
        }

        if (!$modul) {
            $modul = $this->sanitize_name(dbx()->get_modul_var('xmodul', ''));
        }

        if (!$modul) {
            $modul = $this->sanitize_name(dbxEditorRecords::system_value('dbx_activ_modul', 'dbx'));
        }

        return array($modul, $fd);
    }

    private function fd_file_exists($modul, $fd)
    {
        return is_file($this->fd_file_path($modul, $fd));
    }

    private function fd_file_path($modul, $fd)
    {
        $file = dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/fd/' . $fd . '.fd.php';
        return dbx()->os_path($file);
    }

    private function build_url($run2, $modul, $fd, $extra = array())
    {
        $url = '?dbx_modul=' . $this->_admin_modul .
               '&dbx_run1=edit_fd' .
               '&dbx_run2=' . rawurlencode($run2) .
               '&modul=' . rawurlencode($modul) .
               '&fd=' . rawurlencode($fd);

        return dbx()->append_url_params($url, (array)$extra);
    }

    private function strip_editor_keys($record)
    {
        unset($record['modul'], $record['dd'], $record['fd'], $record['field_pos'], $record['old_name']);
        return $record;
    }

    private function parse_order($raw)
    {
        if (is_array($raw)) {
            return array_values(array_map('intval', $raw));
        }

        $raw = trim((string)$raw);
        if ($raw === '') {
            return array();
        }

        if (substr($raw, 0, 1) === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_map('intval', $decoded));
            }
        }

        $parts = preg_split('/[|,\s;]+/', $raw);
        return is_array($parts) ? array_values(array_map('intval', $parts)) : array();
    }

    private function validate_field_record($field, $fields, $self_pos, &$message, $texts)
    {
        $name = trim((string)($field['name'] ?? ''));
        if (!$this->is_identifier($name)) {
            $message = $texts->format_fd_message(
                'fd_invalid_field_name',
                array('field' => dbx()->esc($name))
            );
            return false;
        }

        $names = array();
        foreach (array_values((array)$fields) as $pos => $old) {
            if ((int)$pos === (int)$self_pos) {
                continue;
            }

            $old_name = strtolower(trim((string)($old['name'] ?? '')));
            if ($old_name !== '') {
                $names[$old_name] = 1;
            }
        }

        if (isset($names[strtolower($name)])) {
            $message = $texts->format_fd_message(
                'fd_duplicate_field_name',
                array('field' => dbx()->esc($name))
            );
            return false;
        }

        return true;
    }

    private function is_identifier($name)
    {
        return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string)$name);
    }

    private function sanitize_name($name)
    {
        $name = trim((string)$name);
        return preg_match('/^[A-Za-z0-9_-]+$/', $name) ? $name : '';
    }

    private function instance_id($seed)
    {
        return $this->safe_id($seed . '_' . substr(md5((string)$seed), 0, 6));
    }

    private function field_keys()
    {
        $o_dd = dbx()->get_system_obj('dbxDD');
        if (is_object($o_dd) && method_exists($o_dd, 'dd_field_schema_keys')) {
            return array_merge(array('modul', 'dd', 'fd', 'field_pos', 'old_name'), $o_dd->dd_field_schema_keys());
        }

        return array(
            'modul',
            'dd',
            'fd',
            'field_pos',
            'old_name',
            'name',
            'type',
            'index',
            'length',
            'default',
            'label',
            'rules',
            'tooltip',
            'errormsg',
            'placeholder',
            'convert',
            'protect',
            'group',
            'mask',
            'data',
            'options',
            'tpl',
            'js',
            'prompt',
        );
    }

    private function field_row_defaults()
    {
        return array(
            'modul'     => '',
            'dd'        => '',
            'fd'        => '',
            'field_pos' => '',
            'name'      => '',
            'type'      => '',
            'index'     => '',
            'length'    => '',
            'label'     => '',
        );
    }
}

?>
