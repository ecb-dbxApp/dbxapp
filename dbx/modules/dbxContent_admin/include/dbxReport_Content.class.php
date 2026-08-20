<?php

declare(strict_types=1);

namespace dbx\dbxContent_admin;

dbx()->get_system_obj('dbxReport', 'use');

class dbxReport_Content extends \dbxReport
{
    public array $_folders = array();
    public array $_groups = array();

    private function get_folder_matrix(): array
    {
        $matrix = dbx()->get_modul_var('folder_matrix', '');
        if (is_array($matrix)) return $matrix;
        $matrix = array();
        $database = dbx()->get_system_obj('dbxDB');
        $language = dbx()->get_system_var('dbx_lng', 'de');
        $table = dbx()->lng_name('content_folder', $language);
        $folders = $database->select($table, '', 'id,parent_id');
        foreach ((array)$folders as $record) {
            $matrix['f_' . $record['id']] = $record['parent_id'];
        }
        dbx()->set_modul_var('folder_matrix', $matrix);
        return $matrix;
    }

    private function get_folder_level($folder, $root = 0): int
    {
        $level = 0;
        $matrix = $this->get_folder_matrix();
        while ($folder != $root) {
            $parent = $root;
            $folder = 'f_' . $folder;
            if (isset($matrix[$folder])) $parent = $matrix[$folder];
            if ($parent != $root && $parent > 0) {
                $level++;
                $folder = $parent;
            } else {
                $root = $folder;
            }
        }
        return $level;
    }

    private function get_folder_name($id): string
    {
        $folder = '(0) /';
        if ($id) {
            $name = $this->_folders[$id] ?? '?';
            $level = $this->get_folder_level($id) + 1;
            $folder = '(' . $id . ') | ' . $name . ' | (' . $level . ')';
        }
        return $folder;
    }

    public function run_body($content)
    {
        $folder = 0;
        $level = 0;
        $sorter = '';
        $record = $this->_record;
        if (isset($record['parent_id'])) $folder = $record['id'];
        if (isset($record['folder'])) $folder = $record['folder'];
        if (isset($record['sorter'])) $sorter = $record['sorter'];
        if ($folder) $level = $this->get_folder_level($folder);
        if (!isset($record['parent_id']) && isset($record['folder'])) {
            $record['folder_name'] = $this->get_folder_name($record['folder']);
        }
        $record['sort'] = $sorter;
        $record['l'] = $level + 1;
        $record['perma'] = '';
        $this->_record = $record;
        return $content;
    }
}
