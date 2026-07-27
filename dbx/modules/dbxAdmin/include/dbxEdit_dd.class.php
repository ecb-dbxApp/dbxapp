<?php
namespace dbx\dbxAdmin;

/**
 * =========================================================
 * DBX ADMIN DD EDITOR (dbxEdit_dd)
 * =========================================================
 *
 * Zweck
 * -----
 * Bearbeitet DBX-DD-Dateien komfortabel über:
 *
 * - dbxForm für $table
 * - dbxReport im tpl-Modus für $fields[]
 * - dbxForm je Feld
 * - dbxReport im tpl-Modus für $indexes[]
 * - dbxForm je Index
 *
 * Erwartete Dateien:
 * ------------------
 * FD:
 * - dbx/modules/dbxAdmin/fd/ddedit-table.fd.php
 * - dbx/modules/dbxAdmin/fd/ddedit-field.fd.php
 * - dbx/modules/dbxAdmin/fd/ddedit-index.fd.php
 *
 * Templates:
 * - dbx/modules/dbxAdmin/tpl/htm/ddedit-frame.htm
 * - dbx/modules/dbxAdmin/tpl/htm/ddedit-table-form.htm
 * - dbx/modules/dbxAdmin/tpl/htm/ddedit-field-form.htm
 * - dbx/modules/dbxAdmin/tpl/htm/ddedit-index-form.htm
 * - dbx/modules/dbxAdmin/tpl/htm/ddedit-fields-report.htm
 * - dbx/modules/dbxAdmin/tpl/htm/ddedit-field-row.htm
 * - dbx/modules/dbxAdmin/tpl/htm/ddedit-indexes-report.htm
 * - dbx/modules/dbxAdmin/tpl/htm/ddedit-index-row.htm
 *
 * Aufruf aus dbxAdmin:
 * --------------------
 * case 'edit_dd':
 *   $obj = dbx()->get_include_obj('dbxEdit_dd');
 *   $content = $obj->run();
 * break;
 */
class dbxEdit_dd
{
    /**
     * Modulname dieses Include-Objekts.
     *
     * Typ: $type. */
    private $_admin_modul = 'dbxAdmin';

    /**
     * FD-Quelle für den $table-Bereich.
     *
     * Typ: $type. */
    private $_fd_table = 'dbxAdmin|ddedit-table';

    /**
     * FD-Quelle für einzelne DD-Felder.
     *
     * Typ: $type. */
    private $_fd_field = 'dbxAdmin|ddedit-field';

    /**
     * FD-Quelle für einzelne DD-Indexe.
     *
     * Typ: $type. */
    private $_fd_index = 'dbxAdmin|ddedit-index';

    /** Sprachstabile dbxForm-Textkontexte, indiziert mit der FD-Referenz. */
    private $_text_forms = array();

    /**
     * Liefert Meldungen aus der sprachabhängigen FD, ohne den aktiven Formularzustand zu verändern.
     *
     * @param string $fd FD-Referenz
     * @return \dbxForm
     */
    private function texts($fd)
    {
        if (isset($this->_text_forms[$fd])) {
            return $this->_text_forms[$fd];
        }

        dbx()->get_system_obj('dbxForm', 'use');
        $form = new \dbxForm();
        $form->init('ddedit-texts-' . $this->safe_id($fd));
        $form->_fd = $fd;
        $form->load_fd_messages();
        $form->set_form_help_enabled(false);
        $this->_text_forms[$fd] = $form;

        return $form;
    }

    /**
     * Hauptdispatcher.
     *
     * @return string
     */
    public function run()
    {
        $work = dbx()->get_modul_var('dbx_run2', '');

        switch ($work) {

            case 'create_form_table':
                return $this->create_form_table();

            case 'create_form_dd':
                return $this->create_form_dd();

            case 'create_form_index':
                return $this->create_form_index();

            case 'delete_field':
                return $this->delete_field();

            case 'delete_index':
                return $this->delete_index();

            case 'save_field_order':
                return $this->save_field_order();

            case 'save_index_order':
                return $this->save_index_order();

            case '':
            case 'editor':
            default:
                return $this->run_editor();
        }
    }


    /**
     * Rendert den kompletten DD-Editor.
     *
     * @return string
     */
    private function run_editor()
    {
        list($modul, $dd) = $this->dd_params_from_request();
        $texts = $this->texts($this->_fd_table);

        if (!$modul || !$dd) {
            return $this->alert('warning', $texts->get_fd_message('missing_dd_params'));
        }

        $model = $this->load_model($modul, $dd);
        if (!$model) {
            return $this->alert('danger', $texts->format_fd_message(
                'dd_unreadable',
                array('dd' => dbx()->esc($modul . '|' . $dd))
            ));
        }

        $instance_id         = $this->instance_id($modul . '_' . $dd);
        $work_target_id      = 'dbx_ddedit_work_' . $instance_id;
        $work_content        = $this->create_form_table($modul, $dd, $model);
        $fields_order_report = $this->create_fields_order_report($modul, $dd, $model, $work_target_id);
        $indexes_report      = $this->create_indexes_report($modul, $dd, $model, $work_target_id);

        $data = array(
            'i'              => $instance_id,
            'modul'          => $modul,
            'dd'             => $dd,
            'path'           => $this->dd_file_path($modul, $dd),
            'message'        => '',
            'work_target_id'      => $work_target_id,
            'table_url'           => $this->build_url('create_form_table', $modul, $dd),
            'new_field_url'       => $this->build_url('create_form_dd', $modul, $dd, array('field_pos' => 'new')),
            'new_index_url'       => $this->build_url('create_form_index', $modul, $dd, array('index_pos' => 'new')),
            'work_content'        => $work_content,
            'fields_order_report' => $fields_order_report,
            'indexes_report'      => $indexes_report,
        );

        $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');
        $oTPL = dbx()->get_system_obj('dbxTPL');
        $reloadAction = $oTPL->get_tpl('dbx|button-bar-reload-ajax', array(
            'bar_reload_href'    => '?dbx_modul=dbxAdmin&dbx_run1=edit_dd&modul=' . rawurlencode($modul) . '&dd=' . rawurlencode($dd),
            'bar_reload_target'  => 'dbx_ddedit_' . $instance_id,
            'bar_reload_replace' => 'target',
        ));
        $barData = $help->moduleBarTemplateData('edit_dd', $reloadAction);
        $barData['bar_title'] = $texts->format_fd_message(
            'bar_title',
            array('dd' => dbx()->esc($modul . '|' . $dd))
        );
        $barData['bar_subtitle'] = $texts->get_fd_message('bar_subtitle');
        $barData['bar_class'] = 'dbx-module-bar dbx-ddedit-head';
        $data = array_merge($data, $barData);

        return $oTPL->get_tpl($this->_admin_modul . '|ddedit-frame', $data);
    }

    /**
     * Rendert und verarbeitet das $table-Formular.
     *
     * @param string $modul Optional Modulname
     * @param string $dd Optional DD-Name
     * @param array  $model Optional bereits geladenes DD-Modell
     *
     * @return string
     */
    private function create_form_table($modul = '', $dd = '', $model = array())
    {
        $texts = $this->texts($this->_fd_table);
        if (!$modul || !$dd) {
            list($modul, $dd) = $this->dd_params_from_request();
        }

        if (!$model) {
            $model = $this->load_model($modul, $dd);
        }

        if (!$model) {
            return $this->alert('danger', $texts->format_fd_message(
                'dd_not_found',
                array('dd' => dbx()->esc($modul . '|' . $dd))
            ));
        }

        $data = is_array($model['table'] ?? null) ? $model['table'] : array();
        $data['modul'] = $modul;
        $data['dd']    = $dd;

        $oForm = dbx()->get_system_obj('dbxForm');
        $oForm->init('ddedit_table_' . $this->safe_id($modul . '_' . $dd), 'ddedit-table-form');
        $oForm->_fd     = $this->_fd_table;
        $oForm->load_fd_messages();
        $oForm->set_form_help_enabled(false);
        $oForm->_data   = $data;
        $oForm->_action = $this->build_url('create_form_table', $modul, $dd);
        $oForm->_msg_info = $texts->format_fd_message('edit_info', array('dd' => dbx()->esc($dd)));
        $oForm->add_flds();
        $this->apply_table_right_fields($oForm, $data);

        if ($oForm->submit()) {

            if (!$oForm->errors()) {

                $table = $this->merge_record($data, $oForm->_post, $this->table_keys());
                $table['datadic'] = $dd;

                $model['table'] = $table;

                $ok = $this->save_model($modul, $dd, $model);

                if ($ok) {
                    $oForm->_data = array_merge($table, array('modul' => $modul, 'dd' => $dd));
                    $oForm->_msg_success = $texts->format_fd_message('table_saved', array('dd' => dbx()->esc($dd)));
                } else {
                    $oForm->_msg_error = $texts->format_fd_message('table_save_error', array('dd' => dbx()->esc($dd)));
                }

            } else {
                $oForm->_msg_error = $texts->format_fd_message('table_check', array('dd' => dbx()->esc($dd)));
            }
        }

        return $oForm->run();
    }

    /**
     * Rendert und verarbeitet ein Formular für ein einzelnes DD-Feld.
     *
     * @param string $modul Optional Modulname
     * @param string $dd Optional DD-Name
     * @param mixed  $field_pos Optional Feldposition oder "new"
     * @param array  $model Optional bereits geladenes DD-Modell
     *
     * @return string
     */
    private function create_form_dd($modul = '', $dd = '', $field_pos = null, $model = array())
    {
        $texts = $this->texts($this->_fd_field);
        if (!$modul || !$dd) {
            list($modul, $dd) = $this->dd_params_from_request();
        }

        if ($field_pos === null) {
            $field_pos = dbx()->get_modul_var('field_pos', 'new');
        }

        if (!$model) {
            $model = $this->load_model($modul, $dd);
        }

        if (!$model) {
            return $this->alert('danger', $texts->format_fd_message(
                'dd_not_found',
                array('dd' => dbx()->esc($modul . '|' . $dd))
            ));
        }

        $fields = array_values((array)($model['fields'] ?? array()));
        $is_new = ((string)$field_pos === 'new');

        if ($is_new) {
            $data = $this->default_field_record();
        } else {
            $pos = (int)$field_pos;
            if (!isset($fields[$pos]) || !is_array($fields[$pos])) {
                return $this->alert('warning', $texts->format_fd_message(
                    'position_not_found',
                    array('position' => dbx()->esc((string)$field_pos))
                ));
            }

            $data = $fields[$pos];
        }

        $data['modul']     = $modul;
        $data['dd']        = $dd;
        $data['field_pos'] = (string)$field_pos;
        $data['old_name']  = (string)($data['name'] ?? '');

        $form_id = 'ddedit_field_' . $this->safe_id($modul . '_' . $dd . '_' . (string)$field_pos);

        $oForm = dbx()->get_system_obj('dbxForm');
        $oForm->init($form_id, 'ddedit-field-form');
        $oForm->_fd     = $this->_fd_field;
        $oForm->load_fd_messages();
        $oForm->set_form_help_enabled(false);
        $oForm->_data   = $data;
        $oForm->_action = $this->build_url('create_form_dd', $modul, $dd, array('field_pos' => (string)$field_pos));
        $oForm->_msg_info = $is_new
            ? $texts->format_fd_message('edit_new', array('dd' => dbx()->esc($dd)))
            : $texts->format_fd_message('edit_existing', array('field' => dbx()->esc((string)($data['name'] ?? $field_pos))));
        $oForm->add_flds();

        if ($oForm->submit()) {

            if (!$oForm->errors()) {

                $field = $this->merge_record($data, $oForm->_post, $this->field_keys());
                $field = $this->strip_editor_keys($field);

                $message = '';
                if (!$this->validate_field_record($field, $fields, $is_new ? -1 : (int)$field_pos, $message)) {
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

                $ok = $this->save_model($modul, $dd, $model);

                if ($ok) {
                    $field['modul']     = $modul;
                    $field['dd']        = $dd;
                    $field['field_pos'] = (string)$field_pos;
                    $field['old_name']  = (string)($field['name'] ?? '');

                    $oForm->_data = $field;
                    $oForm->_action = $this->build_url('create_form_dd', $modul, $dd, array('field_pos' => (string)$field_pos));
                    $oForm->_msg_success = $texts->format_fd_message(
                        'field_saved',
                        array('field' => dbx()->esc((string)($field['name'] ?? $field_pos)))
                    );
                } else {
                    $oForm->_msg_error = $texts->format_fd_message(
                        'field_save_error',
                        array('field' => dbx()->esc((string)($field['name'] ?? $field_pos)))
                    );
                }

            } else {
                $oForm->_msg_error = $texts->format_fd_message(
                    'field_check',
                    array('field' => dbx()->esc((string)($data['name'] ?? $field_pos)))
                );
            }
        }

        $delete_url = $this->build_url('delete_field', $modul, $dd, array('field_pos' => (string)$field_pos));

        return str_replace(
            '&dbx_run2=delete_field&modul={modul}&dd={dd}&field_pos={field_pos}',
            dbx()->esc($delete_url),
            $oForm->run()
        );
    }

    /**
     * Rendert und verarbeitet ein Formular für einen einzelnen DD-Index.
     *
     * @param string $modul Optional Modulname
     * @param string $dd Optional DD-Name
     * @param mixed  $index_pos Optional Indexposition oder "new"
     * @param array  $model Optional bereits geladenes DD-Modell
     *
     * @return string
     */
    private function create_form_index($modul = '', $dd = '', $index_pos = null, $model = array())
    {
        $texts = $this->texts($this->_fd_index);
        if (!$modul || !$dd) {
            list($modul, $dd) = $this->dd_params_from_request();
        }

        if ($index_pos === null) {
            $index_pos = dbx()->get_modul_var('index_pos', 'new');
        }

        if (!$model) {
            $model = $this->load_model($modul, $dd);
        }

        if (!$model) {
            return $this->alert('danger', $texts->format_fd_message(
                'dd_not_found',
                array('dd' => dbx()->esc($modul . '|' . $dd))
            ));
        }

        $indexes = array_values((array)($model['indexes'] ?? array()));
        $is_new = ((string)$index_pos === 'new');

        if ($is_new) {
            $data = $this->default_index_record();
        } else {
            $pos = (int)$index_pos;
            if (!isset($indexes[$pos]) || !is_array($indexes[$pos])) {
                return $this->alert('warning', $texts->format_fd_message(
                    'position_not_found',
                    array('position' => dbx()->esc((string)$index_pos))
                ));
            }

            $data = $indexes[$pos];
        }

        $data['modul']     = $modul;
        $data['dd']        = $dd;
        $data['index_pos'] = (string)$index_pos;
        $data['old_name']  = (string)($data['name'] ?? '');

        $form_id = 'ddedit_index_' . $this->safe_id($modul . '_' . $dd . '_' . (string)$index_pos);

        $oForm = dbx()->get_system_obj('dbxForm');
        $oForm->init($form_id, 'ddedit-index-form');
        $oForm->_fd     = $this->_fd_index;
        $oForm->load_fd_messages();
        $oForm->set_form_help_enabled(false);
        $oForm->_data   = $data;
        $oForm->_action = $this->build_url('create_form_index', $modul, $dd, array('index_pos' => (string)$index_pos));
        $oForm->_msg_info = $is_new
            ? $texts->format_fd_message('edit_new', array('dd' => dbx()->esc($dd)))
            : $texts->format_fd_message('edit_existing', array('index' => dbx()->esc((string)($data['name'] ?? $index_pos))));
        $oForm->add_flds();

        if ($oForm->submit()) {

            if (!$oForm->errors()) {

                $index = $this->merge_record($data, $oForm->_post, $this->index_keys());
                $index = $this->strip_editor_keys($index);

                $message = '';
                if (!$this->validate_index_record($index, $indexes, $is_new ? -1 : (int)$index_pos, $message)) {
                    $oForm->_msg_error = $message;
                    return $oForm->run();
                }

                if ($is_new) {
                    $indexes[] = $index;
                    $index_pos = count($indexes) - 1;
                    $is_new = false;
                } else {
                    $indexes[(int)$index_pos] = $index;
                }

                $model['indexes'] = array_values($indexes);

                $ok = $this->save_model($modul, $dd, $model);

                if ($ok) {
                    $index['modul']     = $modul;
                    $index['dd']        = $dd;
                    $index['index_pos'] = (string)$index_pos;
                    $index['old_name']  = (string)($index['name'] ?? '');

                    $oForm->_data = $index;
                    $oForm->_action = $this->build_url('create_form_index', $modul, $dd, array('index_pos' => (string)$index_pos));
                    $oForm->_msg_success = $texts->format_fd_message(
                        'index_saved',
                        array('index' => dbx()->esc((string)($index['name'] ?? $index_pos)))
                    );
                } else {
                    $oForm->_msg_error = $texts->format_fd_message(
                        'index_save_error',
                        array('index' => dbx()->esc((string)($index['name'] ?? $index_pos)))
                    );
                }

            } else {
                $oForm->_msg_error = $texts->format_fd_message(
                    'index_check',
                    array('index' => dbx()->esc((string)($data['name'] ?? $index_pos)))
                );
            }
        }

        $delete_url = $this->build_url('delete_index', $modul, $dd, array('index_pos' => (string)$index_pos));

        return str_replace(
            '&dbx_run2=delete_index&modul={modul}&dd={dd}&index_pos={index_pos}',
            dbx()->esc($delete_url),
            $oForm->run()
        );
    }

    /**
     * Rendert den Fields-Report im tpl-Modus.
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     * @param array  $model DD-Modell
     *
     * @return string
     */
    private function create_fields_report($modul, $dd, $model)
    {
        $fields = array_values((array)($model['fields'] ?? array()));
        $rows = array();

        foreach ($fields as $pos => $field) {
            if (!is_array($field)) {
                continue;
            }

            $row = $this->field_row_defaults();
            foreach ($field as $key => $value) {
                $row[$key] = is_array($value) ? implode(',', $value) : (string)$value;
            }

            $row['modul']     = $modul;
            $row['dd']        = $dd;
            $row['field_pos'] = (string)$pos;

            $rows[] = $row;
        }

        $data = array(
            'modul' => $modul,
            'dd'    => $dd,
            'count' => count($rows),
        );

        $oReport = dbx()->get_system_obj('dbxReport');
        $oReport->init('ddedit_fields_' . $this->safe_id($modul . '_' . $dd), 'ddedit-fields-report');
        $oReport->_mode  = 'tpl';
        $oReport->_data  = $data;
        $oReport->_replaces = $data;
        $oReport->_rdata = $rows;
        $oReport->_rcount = count($rows);
        $oReport->_rrows = 'auto';
        $oReport->_pages = false;

        $oReport->add_obj(
            'new_field_form',
            'obj-value',
            '[modul=dbxAdmin]dbx_run1=edit_dd&dbx_run2=create_form_dd&modul=' . $modul . '&dd=' . $dd . '&field_pos=new[/modul]'
        );

        $oReport->add_obj(
            'field_form',
            'obj-value',
            '[modul=dbxAdmin]dbx_run1=edit_dd&dbx_run2=create_form_dd&modul={modul}&dd={dd}&field_pos={field_pos}[/modul]'
        );

        return $oReport->run();
    }

    /**
     * Rendert die linke Feld-Reihenfolge als eigenen dbxReport.
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     * @param array  $model DD-Modell
     *
     * @return string
     */
    private function create_fields_order_report($modul, $dd, $model, $target_id = '')
    {
        $fields = array_values((array)($model['fields'] ?? array()));
        $rows = array();

        foreach ($fields as $pos => $field) {
            if (!is_array($field)) {
                continue;
            }

            $row = $this->field_row_defaults();
            foreach ($field as $key => $value) {
                $row[$key] = is_array($value) ? implode(',', $value) : (string)$value;
            }

            $row['modul']     = $modul;
            $row['dd']        = $dd;
            $row['field_pos'] = (string)$pos;
            $row['sort_no']   = (string)($pos + 1);
            $row['target_id'] = $target_id;
            $row['form_url']  = $this->build_url('create_form_dd', $modul, $dd, array('field_pos' => (string)$pos));

            $rows[] = $row;
        }

        $data = array(
            'modul'         => $modul,
            'dd'            => $dd,
            'count'         => count($rows),
            'target_id'     => $target_id,
            'new_field_url' => $this->build_url('create_form_dd', $modul, $dd, array('field_pos' => 'new')),
        );

        $oReport = dbx()->get_system_obj('dbxReport');
        $oReport->init('ddedit_fields_order_' . $this->safe_id($modul . '_' . $dd), 'ddedit-fields-order-report');
        $oReport->_mode  = 'tpl';
        $oReport->_data  = $data;
        $oReport->_replaces = $data;
        $oReport->_rdata = $rows;
        $oReport->_rcount = count($rows);
        $oReport->_rrows = 'auto';
        $oReport->_pages = false;

        return $oReport->run();
    }

    /**
     * Rendert den Index-Report im tpl-Modus.
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     * @param array  $model DD-Modell
     *
     * @return string
     */
    private function create_indexes_report($modul, $dd, $model, $target_id = '')
    {
        $indexes = array_values((array)($model['indexes'] ?? array()));
        $rows = array();

        foreach ($indexes as $pos => $index) {
            if (!is_array($index)) {
                continue;
            }

            $row = $this->index_row_defaults();
            foreach ($index as $key => $value) {
                $row[$key] = is_array($value) ? implode(',', $value) : (string)$value;
            }

            $row['modul']     = $modul;
            $row['dd']        = $dd;
            $row['index_pos'] = (string)$pos;
            $row['target_id'] = $target_id;
            $row['form_url']  = $this->build_url('create_form_index', $modul, $dd, array('index_pos' => (string)$pos));

            $rows[] = $row;
        }

        $data = array(
            'modul'         => $modul,
            'dd'            => $dd,
            'count'         => count($rows),
            'target_id'     => $target_id,
            'new_index_url' => $this->build_url('create_form_index', $modul, $dd, array('index_pos' => 'new')),
        );

        $oReport = dbx()->get_system_obj('dbxReport');
        $oReport->init('ddedit_indexes_' . $this->safe_id($modul . '_' . $dd), 'ddedit-indexes-report');
        $oReport->_mode  = 'tpl';
        $oReport->_data  = $data;
        $oReport->_replaces = $data;
        $oReport->_rdata = $rows;
        $oReport->_rcount = count($rows);
        $oReport->_rrows = 'auto';
        $oReport->_pages = false;

        return $oReport->run();
    }

    /**
     * Löscht ein DD-Feld anhand der Position.
     *
     * @return string
     */
    private function delete_field()
    {
        list($modul, $dd) = $this->dd_params_from_request();
        $field_pos = (int)dbx()->get_modul_var('field_pos', -1);
        $texts = $this->texts($this->_fd_field);

        $model = $this->load_model($modul, $dd);
        if (!$model) {
            return $this->alert('danger', $texts->format_fd_message(
                'dd_not_found',
                array('dd' => dbx()->esc($modul . '|' . $dd))
            ));
        }

        $fields = array_values((array)($model['fields'] ?? array()));
        if (!isset($fields[$field_pos])) {
            return $this->alert('warning', $texts->get_fd_message('field_not_found'));
        }

        $name = (string)($fields[$field_pos]['name'] ?? $field_pos);
        unset($fields[$field_pos]);

        $model['fields'] = array_values($fields);

        $ok = $this->save_model($modul, $dd, $model);

        if ($ok) {
            return $this->alert('success', $texts->format_fd_message(
                'field_deleted',
                array('field' => dbx()->esc($name))
            ));
        }

        return $this->alert('danger', $texts->format_fd_message(
            'field_delete_error',
            array('field' => dbx()->esc($name))
        ));
    }

    /**
     * Löscht einen DD-Index anhand der Position.
     *
     * @return string
     */
    private function delete_index()
    {
        list($modul, $dd) = $this->dd_params_from_request();
        $index_pos = (int)dbx()->get_modul_var('index_pos', -1);
        $texts = $this->texts($this->_fd_index);

        $model = $this->load_model($modul, $dd);
        if (!$model) {
            return $this->alert('danger', $texts->format_fd_message(
                'dd_not_found',
                array('dd' => dbx()->esc($modul . '|' . $dd))
            ));
        }

        $indexes = array_values((array)($model['indexes'] ?? array()));
        if (!isset($indexes[$index_pos])) {
            return $this->alert('warning', $texts->get_fd_message('index_not_found'));
        }

        $name = (string)($indexes[$index_pos]['name'] ?? $index_pos);
        unset($indexes[$index_pos]);

        $model['indexes'] = array_values($indexes);

        $ok = $this->save_model($modul, $dd, $model);

        if ($ok) {
            return $this->alert('success', $texts->format_fd_message(
                'index_deleted',
                array('index' => dbx()->esc($name))
            ));
        }

        return $this->alert('danger', $texts->format_fd_message(
            'index_delete_error',
            array('index' => dbx()->esc($name))
        ));
    }

    /**
     * Speichert die Reihenfolge der DD-Felder.
     *
     * @return string
     */
    private function save_field_order()
    {
        list($modul, $dd) = $this->dd_params_from_request();
        $order = $this->parse_order(dbx()->get_modul_var('order', array()));
        $texts = $this->texts($this->_fd_field);

        $model = $this->load_model($modul, $dd);
        if (!$model) {
            dbx()->json_response(array(
                'ok' => 0,
                'msg' => $texts->format_fd_message('dd_not_found', array('dd' => $modul . '|' . $dd)),
            ));
        }

        $fields = array_values((array)($model['fields'] ?? array()));
        $new = $this->reorder_records($fields, $order);

        if ($new === false) {
            dbx()->json_response(array('ok' => 0, 'msg' => $texts->get_fd_message('invalid_order')));
        }

        $model['fields'] = $new;
        $ok = $this->save_model($modul, $dd, $model);

        dbx()->json_response(array(
            'ok'    => $ok ? 1 : 0,
            'msg'   => $ok ? $texts->get_fd_message('order_saved') : $texts->get_fd_message('order_save_error'),
            'count' => count($new),
        ));

        return '';
    }

    /**
     * Speichert die Reihenfolge der DD-Indexe.
     *
     * @return string
     */
    private function save_index_order()
    {
        list($modul, $dd) = $this->dd_params_from_request();
        $order = $this->parse_order(dbx()->get_modul_var('order', array()));
        $texts = $this->texts($this->_fd_index);

        $model = $this->load_model($modul, $dd);
        if (!$model) {
            dbx()->json_response(array(
                'ok' => 0,
                'msg' => $texts->format_fd_message('dd_not_found', array('dd' => $modul . '|' . $dd)),
            ));
        }

        $indexes = array_values((array)($model['indexes'] ?? array()));
        $new = $this->reorder_records($indexes, $order);

        if ($new === false) {
            dbx()->json_response(array('ok' => 0, 'msg' => $texts->get_fd_message('invalid_order')));
        }

        $model['indexes'] = $new;
        $ok = $this->save_model($modul, $dd, $model);

        dbx()->json_response(array(
            'ok'    => $ok ? 1 : 0,
            'msg'   => $ok ? $texts->get_fd_message('order_saved') : $texts->get_fd_message('order_save_error'),
            'count' => count($new),
        ));

        return '';
    }

    /**
     * Lädt ein DD-Modell.
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     *
     * @return array
     */
    private function load_model($modul, $dd)
    {
        if (!$modul || !$dd) {
            return array();
        }

        $oDD = dbx()->get_system_obj('dbxDD');
        $model = $oDD->get_dd_model($this->dd_ref($modul, $dd));

        if (!is_array($model)) {
            return array();
        }

        if (!isset($model['table']) || !is_array($model['table'])) {
            $model['table'] = array();
        }

        if (!isset($model['fields']) || !is_array($model['fields'])) {
            $model['fields'] = array();
        }

        if (!isset($model['indexes']) || !is_array($model['indexes'])) {
            $model['indexes'] = array();
        }

        return $model;
    }

    /**
     * Speichert ein DD-Modell über dbxDD::save_dd().
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     * @param array  $model DD-Modell
     *
     * @return int
     */
    private function save_model($modul, $dd, $model)
    {
        if (!$modul || !$dd || !is_array($model)) {
            return 0;
        }

        $this->backup_dd_file($modul, $dd);

        $table   = is_array($model['table'] ?? null) ? $model['table'] : array();
        $fields  = is_array($model['fields'] ?? null) ? array_values($model['fields']) : array();
        $indexes = is_array($model['indexes'] ?? null) ? array_values($model['indexes']) : array();

        $oDD = dbx()->get_system_obj('dbxDD');
        return $oDD->save_dd($modul, $dd, $table, $fields, $indexes);
    }

    /**
     * Stellt die DD-Tabellenrechte als dbxSelect1-Multiselect dar.
     *
     * @param object $oForm dbxForm-Instanz
     * @param array  $data Aktuelle Table-Daten
     *
     * @return void
     */
    private function apply_table_right_fields($oForm, $data)
    {
        if (!is_object($oForm) || !method_exists($oForm, 'add_fld')) {
            return;
        }

        foreach (array('read', 'create', 'update', 'delete') as $name) {
            $oForm->add_fld(
                $name,
                'dbxAdmin|ddedit-rights-select1',
                rules: 'array|parameter+*',
                options: $this->table_right_options($data[$name] ?? '', false)
            );
        }

        foreach (array('read_owner', 'create_owner', 'update_owner', 'delete_owner') as $name) {
            $oForm->add_fld(
                $name,
                'dbxAdmin|ddedit-rights-select1',
                rules: 'array|parameter+*',
                options: $this->table_right_options($data[$name] ?? '', true)
            );
        }
    }

    /**
     * Liefert Rechte-Optionen fuer dbxForm inklusive vorhandener Spezialwerte.
     *
     * @param mixed $current Aktuell gespeicherte CSV-/Array-Werte
     * @param bool  $owner   Owner-Feld mit owner-Vorgabe
     *
     * @return array
     */
    private function table_right_options($current = '', $owner = false)
    {
        $options = array();

        if ($owner) {
            $options['owner'] = 'owner';
        } else {
            $options['*'] = $this->texts($this->_fd_table)->get_fd_message('rights_all');
        }

        $db = dbx()->get_system_obj('dbxDB');
        if (is_object($db) && method_exists($db, 'select')) {
            $rows = $db->select('dbxUser_groups', '', '*', 'name');
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $name = trim((string)($row['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $label = trim((string)($row['description'] ?? ''));
                    $options[$name] = $label !== '' ? $label : $name;
                }
            }
        }

        foreach ($this->csv_values($current) as $value) {
            if (!isset($options[$value])) {
                $options[$value] = $value;
            }
        }

        return $options;
    }

    /**
     * Normalisiert CSV-/Array-Werte fuer Select-Optionen.
     *
     * @param mixed $value Wert
     *
     * @return array
     */
    private function csv_values($value)
    {
        if (is_array($value)) {
            $values = $value;
        } else {
            $values = explode(',', (string)$value);
        }

        $out = array();
        foreach ($values as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Erstellt vor dem Speichern eine einfache Backup-Kopie der DD-Datei.
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     *
     * @return int
     */
    private function backup_dd_file($modul, $dd)
    {
        $file = $this->dd_file_path($modul, $dd);
        if (!$file || !file_exists($file)) {
            return 0;
        }

        $dir = dirname($file) . '/_backup';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        if (!is_dir($dir)) {
            return 0;
        }

        $backup = $dir . '/' . $dd . '.' . date('Ymd-His') . '.dd.php';
        return @copy($file, $backup) ? 1 : 0;
    }

    /**
     * Liest und normalisiert die Request-DD-Parameter.
     *
     * @return array
     */
    private function dd_params_from_request()
    {
        $modul = $this->sanitize_name(dbx()->get_modul_var('modul', ''));
        $dd    = $this->sanitize_name(dbx()->get_modul_var('dd', ''));

        if (!$modul) {
            $modul = $this->sanitize_name(dbx()->get_modul_var('xmodul', ''));
        }

        if (!$modul) {
            $modul = $this->sanitize_name($this->get_system_var('dbx_activ_modul', 'dbx'));
        }

        return array($modul, $dd);
    }

    /**
     * Erzeugt DD-Referenz modul|dd.
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     *
     * @return string
     */
    private function dd_ref($modul, $dd)
    {
        return $modul . '|' . $dd;
    }

    /**
     * Ermittelt den erwarteten DD-Dateipfad.
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     *
     * @return string
     */
    private function dd_file_path($modul, $dd)
    {
        $file = dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/dd/' . $dd . '.dd.php';

        return dbx()->os_path($file);
    }

    /**
     * Baut eine URL zum DD-Editor.
     *
     * @param string $run2 dbx_run2
     * @param string $modul Modulname
     * @param string $dd DD-Name
     * @param array  $extra Zusatzparameter
     *
     * @return string
     */
    private function build_url($run2, $modul, $dd, $extra = array())
    {
        $url = '?dbx_modul=' . $this->_admin_modul .
               '&dbx_run1=edit_dd' .
               '&dbx_run2=' . rawurlencode($run2) .
               '&modul=' . rawurlencode($modul) .
               '&dd=' . rawurlencode($dd);

        foreach ((array)$extra as $key => $value) {
            $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }

        return $url;
    }

    /**
     * Liest eine Systemvariable defensiv.
     *
     * @param string $name Name
     * @param mixed  $default Default
     *
     * @return mixed
     */
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

    /**
     * Merged gültige Form-Werte in einen bestehenden Record.
     *
     * @param array $old Altdaten
     * @param array $post Geänderte Formdaten
     * @param array $keys Erlaubte Keys
     *
     * @return array
     */
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

    /**
     * Entfernt Editor-Hilfsfelder aus DD-Records.
     *
     * @param array $record Record
     *
     * @return array
     */
    private function strip_editor_keys($record)
    {
        unset($record['modul']);
        unset($record['dd']);
        unset($record['field_pos']);
        unset($record['index_pos']);
        unset($record['old_name']);

        return $record;
    }

    /**
     * Normalisiert einen einzelnen Wert.
     *
     * @param mixed $value Wert
     *
     * @return string
     */
    private function normalize_value($value)
    {
        if (is_array($value)) {
            return implode(',', array_map('trim', $value));
        }

        return trim((string)$value);
    }

    /**
     * Parse Reihenfolge aus JSON, CSV oder Array.
     *
     * @param mixed $raw Rohwert
     *
     * @return array
     */
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
        if (!is_array($parts)) {
            return array();
        }

        return array_values(array_map('intval', $parts));
    }

    /**
     * Sortiert Records nach Positionsliste neu.
     *
     * @param array $records Records
     * @param array $order Positionsliste
     *
     * @return array|false
     */
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

    /**
     * Validiert ein einzelnes Feld und die Feldnamen insgesamt.
     *
     * @param array  $field Feld
     * @param array  $fields Alle Felder
     * @param int    $self_pos Eigene Position oder -1
     * @param string $message Rückgabe Fehlermeldung
     *
     * @return bool
     */
    private function validate_field_record($field, $fields, $self_pos, &$message)
    {
        $name = trim((string)($field['name'] ?? ''));
        $texts = $this->texts($this->_fd_field);

        if (!$this->is_identifier($name)) {
            $message = $texts->format_fd_message('invalid_field_name', array('field' => dbx()->esc($name)));
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
            $message = $texts->format_fd_message('duplicate_field_name', array('field' => dbx()->esc($name)));
            return false;
        }

        return true;
    }

    /**
     * Validiert einen einzelnen Index und die Indexnamen insgesamt.
     *
     * @param array  $index Index
     * @param array  $indexes Alle Indexe
     * @param int    $self_pos Eigene Position oder -1
     * @param string $message Rückgabe Fehlermeldung
     *
     * @return bool
     */
    private function validate_index_record($index, $indexes, $self_pos, &$message)
    {
        $name = trim((string)($index['name'] ?? ''));
        $type = strtoupper(trim((string)($index['type'] ?? 'INDEX')));
        $fields = trim((string)($index['fields'] ?? ''));
        $texts = $this->texts($this->_fd_index);

        if (!$this->is_identifier($name)) {
            $message = $texts->format_fd_message('invalid_index_name', array('index' => dbx()->esc($name)));
            return false;
        }

        if (!$fields) {
            $message = $texts->get_fd_message('missing_index_field');
            return false;
        }

        if (!in_array($type, array('PRIMARY', 'INDEX', 'UNIQUE', 'FULLTEXT'), true)) {
            $message = $texts->format_fd_message('invalid_index_type', array('type' => dbx()->esc($type)));
            return false;
        }

        $names = array();
        foreach (array_values((array)$indexes) as $pos => $old) {
            if ((int)$pos === (int)$self_pos) {
                continue;
            }

            $old_name = strtolower(trim((string)($old['name'] ?? '')));
            if ($old_name !== '') {
                $names[$old_name] = 1;
            }
        }

        if (isset($names[strtolower($name)])) {
            $message = $texts->format_fd_message('duplicate_index_name', array('index' => dbx()->esc($name)));
            return false;
        }

        return true;
    }

    /**
     * Prüft DBX-/DB-Identifier.
     *
     * @param string $name Name
     *
     * @return bool
     */
    private function is_identifier($name)
    {
        return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string)$name);
    }

    /**
     * Sanitized Modul-/DD-Dateiname.
     *
     * @param mixed $name Name
     *
     * @return string
     */
    private function sanitize_name($name)
    {
        $name = trim((string)$name);

        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            return '';
        }

        return $name;
    }

    /**
     * Sicherer HTML-ID-Teil.
     *
     * @param string $value Wert
     *
     * @return string
     */
    private function safe_id($value)
    {
        $value = preg_replace('/[^A-Za-z0-9_]+/', '_', (string)$value);
        $value = trim($value, '_');

        return $value ?: 'x';
    }

    /**
     * Einfache Instanz-ID.
     *
     * @param string $seed Seed
     *
     * @return string
     */
    private function instance_id($seed)
    {
        return $this->safe_id($seed . '_' . substr(md5((string)$seed), 0, 6));
    }

    /**
     * Bootstrap-Alert.
     *
     * @param string $type Typ
     * @param string $msg Meldung
     *
     * @return string
     */
    private function alert($type, $msg)
    {
        $type = preg_replace('/[^a-z]/', '', (string)$type);
        if (!$type) {
            $type = 'info';
        }

        return '<div class="alert alert-' . $type . '">' . $msg . '</div>';
    }

    /**
     * Keys des $table-Bereichs.
     *
     * @return array
     */
    private function table_keys()
    {
        $oDD = dbx()->get_system_obj('dbxDD');

        if (is_object($oDD) && method_exists($oDD, 'dd_table_schema_keys')) {
            return $oDD->dd_table_schema_keys();
        }

        return array(
            'server',
            'table',
            'datadic',
            'primary',
            'language',
            'version',
            'autosync',
            'cache',
            'trash',
            'trace',
            'update_sql',
            'default_sort',
            'form-dd-table',
            'read',
            'create',
            'update',
            'delete',
            'read_owner',
            'create_owner',
            'update_owner',
            'delete_owner',
        );
    }

    /**
     * Keys eines $fields[]-Eintrags.
     *
     * @return array
     */
    private function field_keys()
    {
        $oDD = dbx()->get_system_obj('dbxDD');

        if (is_object($oDD) && method_exists($oDD, 'dd_field_schema_keys')) {
            return array_merge(array('modul', 'dd', 'field_pos', 'old_name'), $oDD->dd_field_schema_keys());
        }

        return array(
            'modul',
            'dd',
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

    /**
     * Keys eines $indexes[]-Eintrags.
     *
     * @return array
     */
    private function index_keys()
    {
        $oDD = dbx()->get_system_obj('dbxDD');

        if (is_object($oDD) && method_exists($oDD, 'dd_index_schema_keys')) {
            return array_merge(array('modul', 'dd', 'index_pos', 'old_name'), $oDD->dd_index_schema_keys());
        }

        return array(
            'modul',
            'dd',
            'index_pos',
            'old_name',
            'name',
            'type',
            'fields',
            'unique',
            'comment',
        );
    }

    /**
     * Default für neues DD-Feld.
     *
     * @return array
     */
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

    /**
     * Default für neuen DD-Index.
     *
     * @return array
     */
    private function default_index_record()
    {
        return array(
            'name'    => '',
            'type'    => 'INDEX',
            'fields'  => '',
            'unique'  => '0',
            'comment' => '',
        );
    }

    /**
     * Defaultwerte für eine Field-Report-Zeile.
     *
     * @return array
     */
    private function field_row_defaults()
    {
        return array(
            'modul'     => '',
            'dd'        => '',
            'field_pos' => '',
            'name'      => '',
            'type'      => '',
            'index'     => '',
            'length'    => '',
            'label'     => '',
        );
    }

    /**
     * Defaultwerte für eine Index-Report-Zeile.
     *
     * @return array
     */
    private function index_row_defaults()
    {
        return array(
            'modul'     => '',
            'dd'        => '',
            'index_pos' => '',
            'name'      => '',
            'type'      => '',
            'fields'    => '',
            'unique'    => '',
            'comment'   => '',
        );
    }
}

?>
