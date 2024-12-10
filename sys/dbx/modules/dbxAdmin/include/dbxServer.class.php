<?php
namespace dbx\dbxAdmin;
dbx_get_sys_object( 'dbxReport', 'use' );


class dbxReport_Tables extends \dbxReport {
    private function check_create_dd( $dd ) {
        $retval='error';
        $oDD = dbx_get_sys_object( 'dbxDD' );
        $dd_file=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.php');
        if ( !file_exists( $dd_file ) ) {
            $oDD->create_dd( $dd );
            $retval = 'create';
        } else {
            $retval = '<span class="red">not exist</span>';
            $exist  = $dd->get_table_exist($dd);
            if ($exist) $retval = 'exist';
            if ($exist) {
                $change = $dd->update_dd( $dd );
                if ($change) {
                    $retval.=' change';
                } else {
                    $retval.=' ok'; 
                }
            }
        }
        return $retval;
    }

    private function get_dd_exist($server,$table) {
        return "aber Hallo";
    }

    public function run_body( $content ) {
        $record = $this->_record;
        $server = $record['server'];
        $table  = $record['name'];
        $count  = $record['count'];
        
        $record['dd']=$this->get_dd_exist($server,$table);

        $this->_record = $record;
        return $content;        
    }    
}



class dbxReport_Server extends \dbxReport {



    

    public function run_body( $content ) {
        $oDD=dbx_get_sys_object('dbxDD');

        $path='/files/sys/SQLite/';
        $tables=0; $count_tables=0;

        $record = $this->_record;
        $server = $record['name'];
        $connect= $oDD->connect_db_server($server); 

        //dbx_debug("record server=($server)",$record);


        if ($connect) {
            $record['sync'] = '<span class="green">Ja</span>';
            $tables=$oDD->get_db_tables($server,'sqlite_sequence');
            $count_tables=count($tables);

        } else {
            $record['sync'] ='<span class="red">Nein</span>'; 
        }
        if ($record['host'] == '') $record['host']=$path; 

        $record['tables']=$count_tables;
        
        $this->_record = $record;
        return $content;
    }

}



Class dbxServer extends \dbxObj {

    private function run_maction($act,$sel) {
        return 1;
    }

    private function browser_server() {
        $oReport = new dbxReport_Tables;
        $oReport->_action = '?dbx_modul=dbxAdmin&dbx_action=server&dbx_work=list_tables';


        $uid     = dbx_get_CurrentUser();
        $oDD     = dbx_get_sys_object('dbxDD');
        $do      = dbx_get_ModulVar('dbx_do');
        $server  = dbx_get_ModulVar('rid');

        $flds['server']      = 'Server';
        $flds['name']        = 'Tablelle';
        $flds['count']       = 'Count';
        $flds['dd']          = 'DataDictonary';
   
 

        $oReport->init( 'report-tables'); 
        $oReport->_create_row_select  = 1;
        $oReport->_create_row_edit    = 1;
        $oReport->_create_row_delete  = 1;
        $oReport->_create_row_show    = 1;
        $oReport->_create_sel_flds    = 0;
        $oReport->_fld_id     = 'server';

        $oReport->_tabel_tpls['tpl_row_delete'] = 'modul|confirm_row_delete_table';
        $oReport->_tabel_tpls['tpl_row_edit']   = 'table_row_modal-edit';
        $oReport->_tabel_tpls['tpl_row_show']   = 'table_row_modal-show';
        
        $data=array(); 
        $rdata=$oDD->get_db_tables($server);

        dbx_debug("DATA Tablels Server=($server)",$rdata);

  
        $oReport->_data        = $data;
        $oReport->_msg_info    = "db-Tabllen von ($server)";
        $oReport->_msg_success = '';

        $rwhere = $oReport->get_sel( 'dbx_rwhere', '' );
        $rrows  = $oReport->get_sel( 'dbx_rrows', 25 );
        $rpos   = $oReport->get_sel( 'dbx_rpos', 0 );

        $oReport->_rdata = $oReport->data_rows( $rdata, $rpos, $rrows );
        $oReport->_rcount = count( $rdata );

        $content = $oReport->run( 0,$flds,'table');




        //$content = "dbxAdmin->Server ($server)<br>";

        return $content;
    }

    private function edit_server() {
 
        $content=''; 
        $oForm =dbx_get_sys_object('dbxForm');
        $work  =dbx_get_ModulVar('dbx_work',0,'parameter');
        $server=dbx_get_ModulVar('rid' ,'new','parameter');
        $dd    ='mod:server';

        $oForm->init('form-server');
        $xdata=dbx_get_cfg('dbx','db');
        if ($server != 'new') $data=$xdata[$server];
        if ($server == 'new') $data=array();  

        $oForm->_data      = $data;
        $oForm->_msg_info  = 'Sie können ein Daten bearbeiten';
        $oForm->_dd        = $dd; // Main db-Table
        $oForm->_action    = '?dbx_modul=dbxAdmin&dbx_action=server&dbx_work=edit_server&rid='.$server;
        $oForm->_fld_change_state='*'; // get allways all, unchaged too
  
        $oForm->add_fld('name'  ,'text-label',rules: 'parameter|min=1');
    
        $oForm->add_fld('type'  ,'select-single-label');
        $oForm->add_fld('host'  ,'text-label' );        
    
        $oForm->add_fld('user'  ,'text-label' );        
        $oForm->add_fld('pass'  ,'text-label' );        
        $oForm->add_fld('port'  ,'text-label' );        
    
    
        //public function add_fld($name,$tpl,$data='',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='') { //#
    
    
    
        if($oForm->submit()) {
            if(!$oForm->errors()) {      // submit && no errors && no warnings
             $change=$oForm->changed();
             if ($change) {
                $xdata  = dbx_get_cfg('dbx');
                $server = dbx_get_PostGetVar('name','new','parameter'); // new server need name
                $fields = ['name', 'type', 'host', 'user', 'pass', 'port']; // Array mit den gewünschten Feldnamen
                
                foreach ($fields as $field) {
                    if (isset($oForm->_post[$field])) $xdata['db'][$server][$field] = $oForm->_post[$field];
                }
 
                $config=$xdata; 
                dbx_debug('save_server',$config); 


                $ok=dbx_set_cfg('dbx',$config);

                if ( $ok) $oForm->_msg_success   = 'Config gespeichert';
                if (!$ok) $oForm->_msg_success   = 'Config konnten nicht gespeichert werden';

             } else {
               $oForm->_msg_success   = 'Keine Änderung';
             }
            } else {
             $err_flds='';
             $errors=$oForm->_errors;
             foreach ($errors as $key => $value) {
               $err_flds.=$key.' ';
             }
             $oForm->_msg_error = 'Prüfen sie bitte ihre Eingaben ('.$err_flds.')';
          }
        } else {
            $oForm->add_obj('form_msg','obj-value','');
        }
     
        $content=$oForm->run();
    
        return $content;
    
    }


    private function delete_server($server) {
        dbx_debug("delete server=($server)");
        $ok=0;
        if ($server) {
            $config=dbx_get_cfg('dbx');
            if (isset($config['db'][$server])) unset($config['db'][$server]);
            $ok=dbx_set_cfg('dbx',$config);
        }
        return $ok;
    }



    Private function report_server() {

        $oReport = new dbxReport_Server;
        $oReport->_action = '?dbx_modul=dbxAdmin&dbx_action=server&dbx_work=list_server';

        $content = "dbxAdmin->DataDic<br>";
        $uid     = dbx_get_CurrentUser();
        $oDD     = dbx_get_sys_object('dbxDD');
        $do      = dbx_get_ModulVar('dbx_do');
        $server  = dbx_get_ModulVar('rid');

        

        $act=dbx_get_ModulVar('maction_select',0,'parameter');
        $sel=dbx_get_ModulVar('report-server_select',0,'array|parameter');

        if ($act) $ok=$this->run_maction($act,$sel);
        //dbx_debug("#DD-MULTI-ACTION ($act)",$sel);

        if ($do == 'row_show') {
            if ($server) return $this->browser_server();
        }

        if ($do == 'row_edit') {
           $modal_content=$this->edit_server();
           return $modal_content;
        }
        if ($do == 'row_delete' && $server) {
           $ok=$this->delete_server($server);
           if ( $ok) $oReport->_msg_info = "Server ($server) gelöscht";
           if (!$ok) $oReport->_msg_info = "Server ($server) konnte nicht gelöscht werden";
        }

        $xdata=dbx_get_cfg('dbx','db');
        foreach ($xdata as $key => $value) {
            $rdata[]=$value; // make records
        }

        //dbx_debug("##rdata=",$rdata);

       
        $data['dbx_rrows'] = 999;
        $flds['name']      = 'Server';
        $flds['host']      = 'Host';
        $flds['type']      = 'Type';
        $flds['tables']    = 'Tabellen';
        $flds['sync']      = 'Verbunden';



        $oReport->init( 'report-server'); 
        $oReport->_create_row_select  = 1;
        $oReport->_create_row_edit    = 1;
        $oReport->_create_row_delete  = 1;
        $oReport->_create_row_show    = 1;
        $oReport->_create_sel_flds    = 0;
        $oReport->_fld_id     = 'name';

        $oReport->_tabel_tpls['tpl_row_delete'] = 'modul|confirm_row_delete_server';
        $oReport->_tabel_tpls['tpl_row_edit']   = 'table_row_modal-edit';
        $oReport->_tabel_tpls['tpl_row_show']   = 'table_row_modal-show';
        
  
        // if ( $nosel ) $oReport->init( 'report-datadic', 'report-datadic-nosel' );
        $oReport->_data        = $data;
        $oReport->_msg_info    = '';
        $oReport->_msg_success = '';

        $oReport->_rdata = $rdata;

        $i=dbx_get_Remember('last_report_i',0,'*','dbx');
        $bdata['dbx_get'] =  $oReport->_action.'&dbx_work=new_server';
        $bdata['label']   = 'Neuen Server erstellen.';
        $bdata['title']   = 'Neuen Server erstellen.';
        $bdata['on_close']=  "dbxReSendForm('#dbx_form_{i}')"; //"dbx_reload('?');";
        $bdata['class']   = 'modal-xxl';

        

        $oReport->add_obj('modal1','modal1',$bdata);
        $oReport->add_obj('new_server','button-modal1',$bdata); 
        
        
        $mactions['0']='[Aktion auswählen]';
        $mactions['export_csv']   ='Export CSV';
        $mactions['import_csv']   ='Import CSV';
        $mactions['empty_table']  ='db-Tabelle leeren';
        $mactions['delete_table'] ='db-Tabelle und DataDic löschen';
        $mactions['create_table'] ='db-Tabelle von DataDic erstellen';
        $mactions['sync_db_to_dd']='Datadictonary --> db-Tabelle syncronisieren';
        $mactions['sync_dd_to_db']='DataDictonary <-- db-Tabelle syncronisieren';

        $modal1['title']     ='Server';     
        $modal1['on_close']= "dbxReSendForm('#dbx_form_{i}')"; //"dbx_reload('?');";
        $modal1['class']     ='modal-xxl';

        $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);
        $oReport->add_obj('modal1','obj-value',$modal_content);



        $oReport->add_fld('maction_select','select-single',$mactions);
        $oReport->add_obj('maction_submit','dbx|button-submit','label=Aktion starten');

        if ( $oReport->submit() ) {
            if ( $oReport->errors() ) {
                $errors = $oReport->_errors;
                $oReport->_msg_error = 'Prüfen sie bitte ihre Eingaben';
            }
        }

        $oReport->add_obj('modal1','obj-value',$modal_content); 

        $rwhere = $oReport->get_sel( 'dbx_rwhere', '' );
        $rrows  = $oReport->get_sel( 'dbx_rrows', 25 );
        $rpos   = $oReport->get_sel( 'dbx_rpos', 0 );

        $oReport->_rdata = $oReport->data_rows( $rdata, $rpos, $rrows );
        $oReport->_rcount = count( $rdata );

        $content = $oReport->run( 1, $flds, 'table' );

        return $content;
    }

    Private function delete_table($dd) {
        $oTPL=dbx_get_sys_object('dbxTPL');
        $oDD =dbx_get_sys_object('dbxDD'); 
        $tab   =$oDD->get_dd_table($dd);
        $server=$oDD->get_dd_server($dd);
        $data['table']=$tab;
        $sql=$oTPL->get_tpl('modul','del_dd',$data,'sql');
        $dd_file=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.php');
        if (file_exists($dd_file)) unlink($dd_file); 
        $ok =$oDD->rawQuery($server,$sql);
        return $ok;
    }
 
    Private function create_table($table) {
        dbx_set_ModulVar('rid',$table); // need for report fields
        $oTPL=dbx_get_sys_object('dbxTPL');
        $oDB =dbx_get_sys_object('dbxDB'); 
        $data['table']=$table;
        $sql=$oTPL->get_tpl('modul','new_dd',$data,'sql');
        $ok =$oDB->query($sql,'status');
        return $ok;
    }
 


 






    public function run() {
        $work = dbx_get_ModulVar( 'dbx_work', 'list', 'parameter' );
        $content = "Unbekannter Aufruf dbx_work=($work)";
        $server  = dbx_get_ModulVar('rid','','parameter');
        switch ( $work ) {
        
            case 'list_server': 
                $content = $this->report_server();
            break;

            case 'edit_server':
                $content =$this->edit_server();
            break;    

            case 'new_server':
                $content =$this->edit_server();
            break;    

        }
        return $content;
    }

}
// class