<?php

declare(strict_types=1);

namespace dbx\dbxAdmin;

dbx()->get_system_obj('dbxReport', 'use');

class dbxReport_Server extends \dbxReport {


    public function run_body( $content ) {
        $o_db =dbx()->get_system_obj('dbxDB');
        $o_tpl=dbx()->get_system_obj('dbxTPL');

        $tables=0; $count_tables=0;

        $record = $this->_record;
        $server = $record['name'];
        $is_active = $o_db->db_server_config_is_active((string)$server, is_array($record) ? $record : array());
        $connect = $is_active ? $o_db->connect_db_server($server) : 0;
        $action = $this->get_action();

        //dbx_debug("record server=($server)",$record);


        if (!$is_active) {
            $record['sync'] = '<span class="badge bg-secondary">'
                . dbx()->esc($this->get_fd_message('status_inactive')) . '</span>';
        } elseif ($connect) {
            $record['sync'] = '<span class="green">' . dbx()->esc($this->get_fd_message('yes')) . '</span>';
            $tables=$o_db->get_db_tables($server,'sqlite_sequence');
            $count_tables=count($tables);

        } else {
            $but['href']  =$action.'&dbx_run3=create_db&rid='.$server;
            $but['label'] ='DB';
            $but['class'] ='btn-inline'; 
            $but['title'] = 'DB';
            $but['tooltip'] = $this->get_fd_message('connection_check');

            $but_connect=$o_tpl->get_tpl('dbx|button_dbcreate',$but);
   
            $record['sync'] = "<span class='red'>" . dbx()->esc($this->get_fd_message('no')) . "</span> $but_connect";
        }

        $record['tables']=$count_tables;
        
        $this->_record = $record;
        return $content;
    }

}


