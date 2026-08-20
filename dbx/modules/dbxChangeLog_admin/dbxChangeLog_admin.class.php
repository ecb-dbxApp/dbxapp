<?php

declare(strict_types=1);

namespace dbx\dbxChangeLog_admin;

final class dbxChangeLog_admin
{
    public function run(): string
    {
        $route = (string)dbx()->get_modul_var('dbx_run1', 'report', 'parameter|max=32');
        $service = dbx()->get_include_obj('dbxChangeLogService', 'dbxChangeLog_admin');

        return match ($route) {
            'form', 'edit' => $service->form(),
            'resources' => $service->resources(),
            'report', 'list', '' => $service->report(),
            default => $service->report(),
        };
    }
}
