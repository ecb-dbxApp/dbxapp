<?php
namespace dbx\dbxAdmin;

/**
 * Editor fuer FD-Dateien.
 *
 * FD-Dateien enthalten `$fields[]` und sprachabhängige `$messages` und liegen
 * unter:
 * dbx/modules/{modul}/fd/{fd}.fd.php
 */
class dbxEdit_fd
{
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
        $this->_texts->_fd = $this->_fd_field;
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

        $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
        $oTPL = dbx()->get_system_obj('dbxTPL');
        $reloadAction = $oTPL->get_tpl('dbx|button-bar-reload-ajax', array(
            'bar_reload_href'    => '?dbx_modul=dbxAdmin&dbx_run1=edit_fd&modul=' . rawurlencode($modul) . '&fd=' . rawurlencode($fd),
            'bar_reload_target'  => 'dbx_fdedit_' . $instance_id,
            'bar_reload_replace' => 'target',
        ));
        $barData = $help->moduleBarTemplateData('edit_fd', $reloadAction);
        $barData['bar_title'] = $texts->format_fd_message(
            'fd_frame_title',
            array('fd' => dbx()->esc($modul . '|' . $fd))
        );
        $barData['bar_class'] = 'dbx-bar--module dbx-ddedit-head';
        $data = array_merge($data, $barData);

        return $oTPL->get_tpl($this->_admin_modul . '|fdedit-frame', $data);
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
            $data = $this->default_field_record();
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

        $oForm = dbx()->get_system_obj('dbxForm');
        $oForm->init('fdedit_field_' . $this->safe_id($modul . '_' . $fd . '_' . (string)$field_pos), 'ddedit-field-form');
        $oForm->_fd = $this->_fd_field;
        $oForm->load_fd_messages();
        $oForm->_data = $data;
        $oForm->_action = $this->build_url('create_form_fd', $modul, $fd, array('field_pos' => (string)$field_pos));
        $oForm->_msg_info = $is_new
            ? $oForm->format_fd_message(
                'fd_edit_new',
                array('fd' => dbx()->esc($fd))
            )
            : $oForm->format_fd_message(
                'fd_edit_existing',
                array(
                    'field' => dbx()->esc(
                        (string)($data['name'] ?? $field_pos)
                    ),
                )
            );
        $oForm->add_flds();

        if ($oForm->submit()) {
            if (!$oForm->errors()) {
                $field = $this->merge_record($data, $oForm->_post, $this->field_keys());
                $field = $this->strip_editor_keys($field);

                $message = '';
                if (!$this->validate_field_record($field, $fields, $is_new ? -1 : (int)$field_pos, $message, $oForm)) {
                    $oForm->_msg_error = $message;
                    return $oForm->run();
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

                    $oForm->_data = $field;
                    $oForm->_action = $this->build_url('create_form_fd', $modul, $fd, array('field_pos' => (string)$field_pos));
                    $oForm->_msg_success = $oForm->format_fd_message(
                        'fd_field_saved',
                        array(
                            'field' => dbx()->esc(
                                (string)($field['name'] ?? $field_pos)
                            ),
                            'fd' => dbx()->esc($fd),
                        )
                    );
                } else {
                    $oForm->_msg_error = $oForm->format_fd_message(
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
                $oForm->_msg_error = $oForm->format_fd_message(
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
            $oForm->run()
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

        $oReport = dbx()->get_system_obj('dbxReport');
        $oReport->init('fdedit_fields_order_' . $this->safe_id($modul . '_' . $fd), 'fdedit-fields-order-report');
        $oReport->_mode = 'tpl';
        $oReport->_data = $data;
        $oReport->_replaces = $data;
        $oReport->_rdata = $rows;
        $oReport->_rcount = count($rows);
        $oReport->_rrows = 'auto';
        $oReport->_pages = false;

        return $oReport->run();
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

        $new = $this->reorder_records(array_values((array)($model['fields'] ?? array())), $order);
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

        $oForm = dbx()->get_system_obj('dbxForm');
        $oForm->init(
            'fd-create-from-dd-' . $this->safe_id($target_modul . '-' . $target_fd),
            'fdedit-create-from-dd'
        );
        $oForm->_fd = $this->_fd_field;
        $oForm->load_fd_messages();
        $oForm->_action = '?dbx_modul=' . $this->_admin_modul .
            '&dbx_run1=edit_fd&dbx_run2=create_from_dd&modul=' . rawurlencode($target_modul) .
            '&fd=' . rawurlencode($target_fd);
        // init() legt den Security-Wert in _data ab; deshalb Vorgaben ergänzen
        // und den von dbxForm erzeugten Wert nicht durch ein neues Array ersetzen.
        $oForm->_data = array_merge($oForm->_data, array(
            'source_modul' => $source_modul,
            'source_dd'    => $source_dd,
            'target_modul' => $target_modul,
            'target_fd'    => $target_fd,
            'overwrite'    => $overwrite ? 1 : 0,
        ));
        $oForm->_msg_info = $oForm->get_fd_message('create_info');

        $oForm->add_fld(
            'source_modul',
            'text-label',
            label: $oForm->get_fd_message('label_source_module'),
            rules: 'parameter|min=1|max=64',
            errormsg: $oForm->get_fd_message('error_source_module'),
            dd: ''
        );
        $oForm->add_fld(
            'source_dd',
            'text-label',
            label: $oForm->get_fd_message('label_source_dd'),
            rules: 'parameter|min=1|max=64',
            errormsg: $oForm->get_fd_message('error_source_dd'),
            dd: ''
        );
        $oForm->add_fld(
            'target_modul',
            'text-label',
            label: $oForm->get_fd_message('label_target_module'),
            rules: 'parameter|min=1|max=64',
            errormsg: $oForm->get_fd_message('error_target_module'),
            dd: ''
        );
        $oForm->add_fld(
            'target_fd',
            'text-label',
            label: $oForm->get_fd_message('label_target_fd'),
            rules: 'parameter|min=1|max=64',
            errormsg: $oForm->get_fd_message('error_target_fd'),
            dd: ''
        );
        $oForm->add_fld(
            'overwrite',
            'checkbox-label',
            label: $oForm->get_fd_message('label_overwrite'),
            rules: 'int',
            dd: ''
        );

        if ($oForm->submit()) {
            if ($oForm->errors()) {
                $oForm->_msg_error = $oForm->get_fd_message('required_fields');
            } else {
                $source_modul = $this->sanitize_name($oForm->get_post('source_modul', '', 'parameter|min=1|max=64'));
                $source_dd = $this->sanitize_name($oForm->get_post('source_dd', '', 'parameter|min=1|max=64'));
                $target_modul = $this->sanitize_name($oForm->get_post('target_modul', '', 'parameter|min=1|max=64'));
                $target_fd = $this->sanitize_name($oForm->get_post('target_fd', '', 'parameter|min=1|max=64'));
                $overwrite = (int)$oForm->get_post('overwrite', 0, 'int') === 1;

                if ($this->fd_file_exists($target_modul, $target_fd) && !$overwrite) {
                    $oForm->add_fld_error(
                        'overwrite',
                        $oForm->get_fd_message('target_exists_field')
                    );
                    $oForm->_msg_error = $oForm->get_fd_message('target_exists');
                } else {
                    $oDD = dbx()->get_system_obj('dbxDD');
                    $dd_model = $oDD->get_dd_model($source_modul . '|' . $source_dd);
                    $fields = is_array($dd_model['fields'] ?? null) ? array_values($dd_model['fields']) : array();

                    if (!count($fields)) {
                        $oForm->add_fld_error(
                            'source_dd',
                            $oForm->get_fd_message('source_no_fields_field')
                        );
                        $oForm->_msg_error = $oForm->get_fd_message('source_invalid');
                    } elseif ($this->save_model($target_modul, $target_fd, array('fields' => $fields))) {
                        $url = '?dbx_modul=' . $this->_admin_modul .
                            '&dbx_run1=edit_fd&modul=' . rawurlencode($target_modul) .
                            '&fd=' . rawurlencode($target_fd);
                        $oForm->_msg_success =
                            $oForm->format_fd_message(
                                'fd_created',
                                array(
                                    'fd' => dbx()->esc($target_modul . '|' . $target_fd),
                                    'dd' => dbx()->esc($source_modul . '|' . $source_dd),
                                )
                            ) . ' ' .
                            '<a class="btn btn-sm btn-primary ms-2" href="' . dbx()->esc($url) .
                            '">' . $oForm->get_fd_message('edit_fd_action') . '</a>';
                    } else {
                        $oForm->_msg_error = $oForm->get_fd_message('fd_write_error');
                        $oForm->add_fld_error(
                            'target_fd',
                            $oForm->get_fd_message('fd_write_field_error')
                        );
                    }
                }
            }
        }

        return $oForm->run();
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
                is_array($messages) ? $messages : array(),
                $fd
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
            is_array($model['messages'] ?? null) ? $model['messages'] : array(),
            $fd
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
        $content .= "\$messages['save_success'] = "
            . var_export($messages['save_success'], true) . ";\n";
        $content .= "\$messages['save_succeass'] = \$messages['save_success'];\n";
        $content .= "\$messages['save_error'] = "
            . var_export($messages['save_error'], true) . ";\n\n";
        foreach ($messages as $messageKey => $messageValue) {
            if (
                in_array(
                    $messageKey,
                    array('save_success', 'save_succeass', 'save_error'),
                    true
                ) ||
                !is_scalar($messageValue)
            ) {
                continue;
            }
            $content .= "\$messages[" . var_export((string)$messageKey, true) . '] = '
                . var_export((string)$messageValue, true) . ";\n";
        }
        if (count($messages) > 3) {
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

    /**
     * Normalisiert die Standard-Speichermeldungen einer FD.
     *
     * `save_success` ist der verbindliche Schlüssel. `save_succeass` bleibt
     * als kompatibler Alias erhalten. Bei neuen FDs richtet sich die
     * Standardsprache nach dem Dateisuffix `_en` bzw. `_es`.
     */
    private function normalize_fd_messages(array $messages, string $fd): array
    {
        $language = 'de';
        if (preg_match('/_en$/', $fd)) {
            $language = 'en';
        } elseif (preg_match('/_es$/', $fd)) {
            $language = 'es';
        }

        $defaults = array(
            'de' => array(
                'save_success' => 'Daten wurden gespeichert',
                'save_error' => 'Daten konnten nicht gespeichert werden',
            ),
            'en' => array(
                'save_success' => 'Data was saved',
                'save_error' => 'Data could not be saved',
            ),
            'es' => array(
                'save_success' => 'Los datos se guardaron',
                'save_error' => 'Los datos no se pudieron guardar',
            ),
        );

        if (
            !isset($messages['save_success']) &&
            isset($messages['save_succeass'])
        ) {
            $messages['save_success'] = $messages['save_succeass'];
        }

        foreach ($defaults[$language] as $key => $value) {
            if (!isset($messages[$key]) || !is_scalar($messages[$key])) {
                $messages[$key] = $value;
            } else {
                $messages[$key] = (string)$messages[$key];
            }
        }
        $messages['save_succeass'] = $messages['save_success'];

        return $messages;
    }

    private function normalize_field_for_write($field)
    {
        $field = $this->strip_editor_keys($field);
        $oDD = dbx()->get_system_obj('dbxDD');
        if (is_object($oDD) && method_exists($oDD, 'normalize_dd_field')) {
            return $oDD->normalize_dd_field($field);
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
            $modul = $this->sanitize_name($this->get_system_var('dbx_activ_modul', 'dbx'));
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

    private function get_system_var($name, $default = '')
    {
        if (function_exists('dbx')) {
            $obj = dbx();
            if (is_object($obj) && method_exists($obj, 'get_system_var')) {
                $value = $obj->get_system_var($name);
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }
        return $default;
    }

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

    private function strip_editor_keys($record)
    {
        unset($record['modul'], $record['dd'], $record['fd'], $record['field_pos'], $record['old_name']);
        return $record;
    }

    private function normalize_value($value)
    {
        if (is_array($value)) {
            return implode(',', array_map('trim', $value));
        }
        return trim((string)$value);
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

    private function reorder_records($records, $order)
    {
        $records = array_values((array)$records);
        $count = count($records);
        if (!$count) {
            return array();
        }
        if (count($order) !== $count) {
            return false;
        }

        $seen = array();
        $new = array();
        foreach ($order as $pos) {
            $pos = (int)$pos;
            if ($pos < 0 || $pos >= $count || isset($seen[$pos])) {
                return false;
            }
            $seen[$pos] = 1;
            $new[] = $records[$pos];
        }
        return $new;
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

    private function safe_id($value)
    {
        $value = preg_replace('/[^A-Za-z0-9_]+/', '_', (string)$value);
        $value = trim($value, '_');
        return $value ?: 'x';
    }

    private function instance_id($seed)
    {
        return $this->safe_id($seed . '_' . substr(md5((string)$seed), 0, 6));
    }

    private function alert($type, $msg)
    {
        $type = preg_replace('/[^a-z]/', '', (string)$type);
        if (!$type) {
            $type = 'info';
        }
        return '<div class="alert alert-' . $type . '">' . $msg . '</div>';
    }

    private function field_keys()
    {
        $oDD = dbx()->get_system_obj('dbxDD');
        if (is_object($oDD) && method_exists($oDD, 'dd_field_schema_keys')) {
            return array_merge(array('modul', 'dd', 'fd', 'field_pos', 'old_name'), $oDD->dd_field_schema_keys());
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

    private function default_field_record()
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
