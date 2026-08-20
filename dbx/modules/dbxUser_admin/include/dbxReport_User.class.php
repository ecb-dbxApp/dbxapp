<?php

declare(strict_types=1);

namespace dbx\dbxUser_admin;

dbx()->get_system_obj('dbxReport', 'use');

class dbxReport_User extends \dbxReport
{
    public function run_body($content)
    {
        $this->_record = is_array($this->_record) ? $this->_record : array();
        return $content;
    }
}
