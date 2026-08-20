<?php

declare(strict_types=1);

namespace dbx\dbxContent_admin;

dbx()->get_system_obj('dbxReport', 'use');

class dbxReport_ContentSections extends \dbxReport
{
    public function run_body($content)
    {
        $record = $this->_record;
        $path = trim((string)($record['path'] ?? ''));
        if ($path !== '') {
            $url = '?dbx_modul=dbxEditor&dbx_run1=edit&file=' . rawurlencode($path);
            $title = htmlspecialchars($this->get_fd_message('edit_template'), ENT_QUOTES, 'UTF-8');
            $escaped_url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $record['edit'] = '<a class="btn btn-outline-primary btn-sm dbx-win" href="'
                . $escaped_url . '" data-url="' . $escaped_url . '" data-title="'
                . $title . '" data-dbx-tooltip="' . $title . '" aria-label="' . $title
                . '"><i class="bi bi-pencil"></i></a>';
        }
        $edit_url = trim((string)($record['edit_url'] ?? ''));
        if ($edit_url !== '') {
            $record['edit'] = '<a class="btn btn-outline-primary btn-sm" href="'
                . htmlspecialchars($edit_url, ENT_QUOTES, 'UTF-8') . '" data-dbx-tooltip="'
                . htmlspecialchars($this->get_fd_message('edit_in_cms'), ENT_QUOTES, 'UTF-8')
                . '" aria-label="' . htmlspecialchars($this->get_fd_message('edit_in_cms'), ENT_QUOTES, 'UTF-8')
                . '"><i class="bi bi-pencil"></i></a>';
        }
        $this->_record = $record;
        return $this->forward_run_body($content);
    }
}
