<?php
declare(strict_types=1);

namespace dbx\dbxUser_admin;

require_once __DIR__ . '/dbxUserGridActions.class.php';

/** Gemeinsamer HTTP-Rahmen der Benutzer- und Gruppengrids. */
trait dbxUserAdminGridTrait
{
    private function base_url($run2, $params = array())
    {
        return dbx()->append_url_params(
            '?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=' . rawurlencode((string)$run2),
            $params
        );
    }

    private function request_json()
    {
        return dbx()->get_json_request();
    }

    private function grid_delete()
    {
        $database = dbx()->get_system_obj('dbxDB');
        dbx()->json_response(dbxUserGridActions::delete_result(
            $database,
            $this->dd,
            $this->request_json(),
            $this->texts()->get_fd_message('id_missing')
        ));
    }
}

