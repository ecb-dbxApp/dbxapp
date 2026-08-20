<?php

declare(strict_types=1);

namespace dbx\dbxContent_admin;

require_once __DIR__ . '/dbxContentSelectOptions.class.php';

class dbxFolder_edit extends \dbxObj
{
    public $o_validator;
    public $o_tpl;

    public function __construct()
    {
        $this->o_validator = dbx()->get_system_obj('dbxValidator');
        $this->o_tpl = dbx()->get_system_obj('dbxTPL');
    }


    public function get_select_tpl($modul)
    {
        $prefix = 'c-';
        $select_data = array();
        $folder = $this->o_tpl->get_tpl_dir('dbxContent') . 'htm/';
        dbx()->debug("folder=($folder)");
        foreach (array_diff(scandir($folder), array('..', '.')) as $filename) {
            $id = substr($filename, 0, strrpos($filename, '.'));
            dbx()->debug("folder-files=($filename) id=($id)");
            $name = $id;
            if ($prefix && substr($name, 0, strlen($prefix)) != $prefix) $id = 0;
            if ($id) $select_data[$id] = $name;
        }
        return $select_data;
    }

    public function run()
    {
        $rid = dbx()->get_modul_var('rid', 0, 'int');
        if (!dbx()->user()) return '';
        $database = dbx()->get_system_obj('dbxDB');
        $language = dbx()->get_system_var('dbx_lng', 'de');
        $folder_dd = dbx()->lng_name('content_folder', $language);
        $data = $database->select1($folder_dd, $rid);
        $folder_options = dbxContentSelectOptions::hierarchy(
            (array)$database->select($folder_dd),
            'id',
            'name',
            'parent_id'
        );
        $template_options = $this->get_select_tpl('dbxContent');
        $group_options = array();
        foreach ((array)$database->select('dbxUser_groups', 'active = 1', '*', 'name') as $record) {
            $group_options[$record['name']] = $record['description'];
        }
        $form = dbx()->get_system_obj('dbxForm');
        $form->init('dbxContent_folder_edit', 'form-folder');
        $form->set_data($data);
        $form->set_action('?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=edit_folder&dbx_target=dbx_target_{i}&rid=' . $rid);
        $form->add_fld('id', 'text-label', rules: 'int', label: 'ID');
        $form->add_fld('name', 'text-label', rules: 'parameter|min=1', label: 'Ordner', errormsg: 'Bezeichnung vom Ordner. Keine Sonderzeichen erlaubt.');
        $form->add_fld('parent_id', 'select-single-label', rules: 'array|int', label: 'Unterordener von', options: $folder_options);
        $form->add_fld('template', 'select-single-label', rules: 'parameter', label: 'Template', options: $template_options);
        $form->add_fld('group_read', 'multi-select-label', rules: 'array|parameter', label: 'Zugriff Gruppen', options: $group_options);
        if ($form->submit()) {
            if (!$form->errors()) {
                $change = $form->changed();
                if ($change) {
                    $form->_msg_success = $form->save_post($folder_dd, $rid)
                        ? 'Daten gespeichert'
                        : 'Daten konnten nicht gespeichert werden';
                } else {
                    $form->_msg_success = 'Keine Änderung';
                }
            } else {
                $form->_msg_error = 'Prüfen sie bitte ihre Eingaben ('
                    . implode(' ', array_keys($form->_errors)) . ')';
            }
        }
        return $form->run();
    }
}
