<?php

declare(strict_types=1);

namespace dbx\dbxContent_admin;

dbx()->get_system_obj('dbxReport', 'use');

class dbxReport_Folder extends \dbxReport
{
    public array $_folders = array();
    public array $_groups = array();

    private function get_parent_folder_name($id): string
    {
        $folder = '(0) -root-';
        if ($id) {
            $folder_name = $this->_folders[$id] ?? '-?-';
            $folder = '(' . $id . ') ' . $folder_name;
        }
        return $folder;
    }

    public function run_body($content)
    {
        $record = $this->_record;
        if (isset($record['parent_id'])) {
            $record['parent_id'] = $this->get_parent_folder_name($record['parent_id']);
        }
        $this->_record = $record;
        return $content;
    }
}
