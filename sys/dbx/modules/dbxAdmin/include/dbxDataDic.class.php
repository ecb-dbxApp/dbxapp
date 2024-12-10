<?php
namespace dbx\dbxAdmin;
dbx_get_sys_object( 'dbxReport', 'use' );


class dbxReport_DataDic extends \dbxReport {

    private function check_create_dd($dd) {
        $retval = 'error';
    
        $oDD = dbx_get_sys_object('dbxDD');
        $dd_file=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.php');

    
        if (!file_exists($dd_file)) {
            $oDD->create_dd($dd);
            $retval = 'create';
        } else {
            $retval = '<span class="red">not exist</span>';
            $exist = $oDD->get_table_exist($dd);
    
            if ($exist) {
                $retval = 'exist';
                $change = $oDD->update_dd($dd);
                $retval .= $change ? ' change' : ' ok';
            }
        }
    
        return $retval;
    }
    

    

    public function run_body( $content ) {
        $oDD=dbx_get_sys_object('dbxDD');

        $record = $this->_record;
        $connect=$oDD->connect_db_server($record['server']); 
        $record['type'] = $oDD->get_db_type($record['server']);

        if ($connect) {
            $syncMessages = [
                -1 => 'db Table not exist',
                -2 => 'no dd fields',
                -3 => 'no db fields',
                -4 => 'dd <> db fields',
                -5 => 'dd field name <> db',
                -6 => 'dd field length <> db',
                -7 => 'dd field type <> db',
                -8 => 'dd field sequenz <> db',
                 0 => 'unsync',
                 1 => 'sync ok'
            ];
        
            $syncCode = $oDD->get_dd_sync($record['datadic']);
            $record['sync']  = $syncMessages[$syncCode] ?? 'Unknown sync status';
        } else {
            $record['sync']  = 'not connected';
            $record['count'] = '-';
        }
 


        $this->_record = $record;
        return $content;
    }

}





Class dd_import extends \dbxObj {

    Public $oTPL;
  
    public function __construct() {
     $this->oTPL = dbx_get_sys_object('dbxTPL');
    }
  
    public function import($dd,$remap=0) {
       $content=''; $status='run'; $timer=15; $percent=100; 

       $path=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.csv');
       $file=$dd.'.csv';
       $path_file=$path.$file;
       $data['file']=$path_file;     
  
       $oForm=dbx_get_sys_object('dbxForm');
       $oForm->init($dd.'_import','form-csv-reader');
       $oForm->_action="?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=import_csv&dd=$dd";
       $oForm->_data=$data;
       $oForm->_fld_change_state='all';
       $oForm->_msg_info='';
  
       $oForm->_try_max=99999999;
     
       $bdata['id']   ='button_{i}';
       $bdata['label']="CSV einlesen ($file)";
       $bdata['sec']  = $timer;
  
       $oImporter=dbx_get_sys_object('dbxCSVreader');
       $progress =$this->oTPL->get_tpl('dbx','progressbar-1');
       $button   =$this->oTPL->get_tpl('dbx','button-submit',$bdata);
       $date_time=date('d-m-Y H:i:s');
  
  
       $msg="CSV Datei ($file) einlesen.";
  
        if($oForm->submit()) {
           if(!$oForm->errors()) {
              if (file_exists($path_file)) {
                
              $status=$oImporter->init($dd.'_import_csv');
              dbx_debug("A-Status=($status)");

              if ($status=='init') {    
                 //$remap=dbx_get_ModulVar('dd_remap',0);

                 dbx_debug("#init #remap=",$remap);

                 $oImporter->set_property('filename',$path_file);
                 $oImporter->set_property('dd'   ,$dd);
                 $oImporter->set_property('where',''); // allways insert it´s faster for empty dbtable   
                 $oImporter->set_property('pass' , 0); // convert
                 $oImporter->set_property('owner',-1); // admin
                 $oImporter->set_property('utf8' , 1); // convert 2 utf8
                 $oImporter->set_property('run_bytes',9600); // max Line length
                 $oImporter->set_property('seperator',';');
                 $oImporter->set_property('remap',$remap); // field name change
                
                 dbx_debug("##remap Importer=",$remap);  
  
              }
              $msg="Die CSV Datei ($file) wird eingelesen ($status)."; 
  
              $status=$oImporter->run();   
            

              $filesize=$oImporter->get_property('filesize');
              $filepos =$oImporter->get_property('filepos');
              $percent =$oImporter->get_property('percent');
              $querys  =$oImporter->get_property('querys');
              $errors  =$oImporter->get_property('errors');
              $lines   =$oImporter->get_property('lines');
  
              dbx_debug("filesize=($filesize) FilePos=($filepos)  Querys=($querys) errors=($errors) lines=($lines)");

  
              $msg="Querys=($querys) ($percent %) status=($status)"; 
              if ($status == 'end' && !$errors)  $msg="Es wurden ($querys) Datensätze eingelesen ($date_time)";
              if ($status == 'end' &&  $errors)  $msg="Es wurden ($querys) Datensätze eingelesen ($date_time) Es sind ($errors) Fehler aufgetreten.";
     
           } else {
              $status='end';
              $msg="Die CSV Datei ($dd) ist nicht vorhanden ($date_time).";
           }
           } else {
           $msg='Ein Fehler ist aufgetreten';    
           }
     } // submit
     $pdata['msg']  =$msg;
     $pdata['value']=$percent;
     $pdata['width']=$percent;
     $bdata['sec']  =$timer;
     //if ($timer)
     $oForm->add_obj('progress','obj-value',$progress,$pdata);
     $oForm->add_obj('button'  ,'obj-value',$button  ,$bdata);
     if ($status != 'end') $oForm->add_js_autosubmit('#dbx_form_{i}',$timer);
     //if ($status == 'end') $oForm->add_js_close_modal('#dbxmodal1',1800);  
  
     $content=$oForm->run();      
     return $content;
  
  }  // import()
  
  
  
  
     public function run($dd,$remap=0) {
        dbx_debug("#run dd_import remap=",$remap);
        $content=$this->import($dd,$remap);
        return $content;
     }
  
  
  }















Class dbxDataDic extends \dbxObj {

    private function create_table_from_dd($dd) {    
        $oDD=dbx_get_sys_object('dbxDD');
        $ok=$oDD->create_db_tab($dd); 
        return $ok;
    }


    private function sync_dd_to_db($dd) {    
        $oDD=dbx_get_sys_object('dbxDD');
        $ok=$oDD->sync_dd_to_db($dd); 
        return $ok;
    }


    

    Private function run_maction($act,$sel) {
        $ok=1;
        $oDD = dbx_get_sys_object('dbxDD');

        dbx_debug("#multi-action=($act) sel=",$sel);

        if (is_array($sel)) {
            foreach ($sel as $no => $dd) {
                $server=$oDD->get_dd_server($dd);
                $tab   =$oDD->get_dd_table($dd);
                dbx_debug ("###  MACTION dd=($dd) Server=($server) Tab=($tab) act=($act)");
                if ($act=='create_table')  $ok=$this->create_table_from_dd($dd);
                if ($act=='sync_dd_to_db') $ok=$this->sync_dd_to_db($dd);
                if ($act=='empty_table')   $ok=$oDD->empty($dd);
            }
        }
        return $ok;
    }

    Private function report_datadic() {

        $oReport = new dbxReport_DataDic;
        $oReport->_action = '?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=list_dd';

        $content = "dbxAdmin->DataDic<br>";
        $uid     = dbx_get_CurrentUser();
        $oDD     = dbx_get_sys_object('dbxDD');
        $do      = dbx_get_ModulVar('dbx_do');
        $dd      = dbx_get_ModulVar('rid');
        $modal_content='DataDic';
        

        $act=dbx_get_ModulVar('maction_select',0,'parameter');
        $sel=dbx_get_ModulVar('report-datadic_select',0,'array|parameter');

        if ($act) $ok=$this->run_maction($act,$sel);
        //dbx_debug("#DD-MULTI-ACTION ($act)",$sel);

        if ($do == 'row_show') {
            if ($dd) return $this->browser_dd($dd);
        }

        if ($do == 'row_edit') {
           $modal_content=$this->edit_datadic_tab();
           return $modal_content;
        }
        if ($do == 'row_delete' && $dd) {
           $ok=$this->delete_table($dd);
           if ( $ok) $oReport->_msg_info = "DataDic ($dd) gelöscht";
           if (!$ok) $oReport->_msg_info = "DataDic ($dd) konnte nicht gelöscht werden";
        }



        $rdata = $oDD->get_dd_tables();        
        $data['dbx_rrows'] = 999;
        $flds['datadic']   = 'DataDictonary';
        $flds['table']     = 'db-Table';
        $flds['server']    = 'db-Server';
        $flds['type']      = 'db-Type';
        $flds['count']     = 'Datensätze';
        $flds['sync']      = 'Sync';



        $oReport->init( 'report-datadic'); //, 'report-datadic-nosel' );
        $oReport->_create_row_select  = 1;
        $oReport->_create_row_edit    = 1;
        $oReport->_create_row_delete  = 1;
        $oReport->_create_row_show    = 1;
        $oReport->_create_sel_flds    = 0;
        $oReport->_fld_id     = 'datadic';

        $oReport->_tabel_tpls['tpl_row_delete'] = 'modul|confirm_row_delete_dd';
        
  
        // if ( $nosel ) $oReport->init( 'report-datadic', 'report-datadic-nosel' );
        $oReport->_data        = $data;
        $oReport->_msg_info    = '';
        $oReport->_msg_success = '';

        $oReport->_rdata = $rdata;

        $i=dbx_get_Remember('last_report_i',0,'*','dbx');
        $bdata['dbx_get'] =  $oReport->_action.'&dbx_work=new_dd';
        $bdata['label']   = 'Neues Datadictonary erstellen.';
        $bdata['title']   = 'Neues DataDictonary erstellen.';
        $bdata['on_close']=  "dbxReSendForm('#dbx_form_{i}')"; //"dbx_reload('?');";
        $bdata['class']   = 'modal-xxl';

        

        $oReport->add_obj('modal1','modal1',$bdata);
        $oReport->add_obj('new_dd','button-modal1',$bdata); 
        
        
        $mactions['0']='[Aktion auswählen]';
        $mactions['export_csv']   ='Export CSV';
        $mactions['import_csv']   ='Import CSV';
        $mactions['empty_table']  ='db-Tabelle leeren';
        $mactions['delete_table'] ='db-Tabelle und DataDic löschen';
        $mactions['create_table'] ='db-Tabelle von DataDic erstellen';
        $mactions['sync_db_to_dd']='Datadictonary --> db-Tabelle syncronisieren';
        $mactions['sync_dd_to_db']='DataDictonary <-- db-Tabelle syncronisieren';

        $modal1['title']     = 'DataDic';     
        $modal1['on_close']  = "dbxReSendForm('#dbx_form_{i}')"; //"dbx_reload('?');";
        $modal1['class']     = 'modal-xxl';

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
 


 

    Private Function save_table( $dd, $post ) {
        //dbx_debug ( "Save-DD ($dd)", $post );
        $oDD = dbx_get_sys_Object( 'dbxDD' );
        $table  = $oDD->get_dd_table($dd,1);
        $fields = $oDD->get_dd_fields($dd );
        foreach ( $post as $fld => $value ) {
            $table[$fld] = $value;
        }
        $ok = $oDD->save_dd( $dd, $table, $fields );
        return $ok;
    }

    Private function isNumericType($type) {
        // Define an array of recognized numeric MySQL data types
        $numericTypes = [
            'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT',
            'FLOAT', 'DOUBLE', 'DECIMAL', 'NUMERIC'
        ];
        $typeUpper = strtoupper($type);
        $found=in_array($typeUpper, $numericTypes);
        if ($found == true) { 
            $found=1;
        } else {
            $found=0;
        }    
        //dbx_debug("ckeck-type ($type) Num=(found)"); 

        return $found;
    } 


    Private Function save_field( $dd, $rid, $post,$mode,$old_fld) {
        
        dbx_debug ( "Save-DD ($dd) Field($rid) mode=($mode) old=($old_fld)", $post );
        $oDD     = dbx_get_sys_Object( 'dbxDD' );
        $tab     = $oDD->get_dd_table($dd);
        $autosync= $oDD->get_dd_autosync($dd);
        $table   = $oDD->get_dd_table($dd,1);
        $fields  = $oDD->get_dd_fields($dd);
        $server  = $oDD->get_dd_server($dd);
        $type    = $oDD->get_db_type($server);
        $connect = $oDD->connect_db_server($server);
        $exist   = $oDD->get_table_exist($dd); 
        $count   = $oDD->count($dd);

        $oTPL=dbx_get_sys_object('dbxTPL');
        $oDD =dbx_get_sys_object('dbxDD'); 
        $default ="''";
        $data['table']  =$tab;
        $data['old_fld']=$old_fld;
        $data['name']   =$post['name']; // #alb #todo 
        $data['type']   =$post['type'];
        $data['length'] = '';
        $isNumeric=$this->isNumericType($data['type']);
 
        $yfld=$data['name'];
        $ydef=$post['default'];
       


        //dbx_debug("TAB=($tab) FLD=($yfld) Default=($ydef) Num=($isNumeric) ");

        if ($post['default']  > '') {
            if (dbx_is_integer($post['default']) && $isNumeric) {
               //dbx_debug("FLD=NUM"); 
               $default=$post['default'];
            } else {
               $default="'".$post['default']."'"; 
               if ($post['default'] == 'NOT NULL') $default='NOT NULL';
               if ($post['default'] == 'NULL')     $default='NULL';
               //dbx_debug("FLD=STR default=($default)");  
            }
            //$post['default']=$default;   
        }
        $data['default']=$default;

        if ($post['length'] > 0) {
            $data['length'] = '('.$post['length'].')';
        }


        //dbx_debug( "fields=", $fields );
        foreach ($fields as $no => $field) {
            if ( $field['name'] == $rid ) {
               foreach ( $post as $fld => $value ) {
                   $fields[$no][$fld] = $value;
               }
            }
        }

        if ($mode=='create' || $mode=='update') {

            //add field to fields with post
            $field['name']       =$post['name'];
            $field['type']       =$post['type'];
            $field['length']     =$post['length'];  

            $field['label']      =$post['label'];
            $field['default']    =$post['default'];
            $field['index']      =$post['index'];  

            $field['rules']      =$post['rules'];
            $field['tooltip']    =$post['tooltip'];
            $field['errormsg']   =$post['errormsg'];

            $field['placeholder']=$post['placeholder'];
            $field['mask']       =$post['mask'];
            $field['data']       =$post['data'];

            $field['tpl']        =$post['tpl'];
            $field['js']         =$post['js'];

            if ($exist && $count) {
                if (!isset($filed['old_fld'])) $field['old_fld']=$old_fld;  // unset it after db-update
            }
            $fields[]=$field;
    
        }


 

        $ok    = $oDD->save_dd( $dd, $table, $fields );  

        $old=$data['old_fld'];
        $new=$data['name'];


        dbx_debug("##########SAVE=($dd) OK=($ok) Fld=($new) War($old) Table-Exist=($exist) Count=($count) mode=($mode)");
        if ($ok) { 

            if ($connect && $count <=0 && $autosync) {
                $ok=$this->create_table_from_dd($dd);
                dbx_debug("#CREATE-DB=($dd) OK=($ok)");
            }


            if ($connect && $exist && $count > 0 && $type != 'sqlite' && $autosync) {

                if ($mode=='insert') {
                    #todo next version
                }    

                if ($mode=='delete') {
                    $sql=$oTPL->get_tpl('modul','del_fld',$data,'sql');
                    dbx_debug("create-Tab=($tab) fld=($rid) Sql=($sql)",$data);
                    $ok =$oDD->rawQuery($server,$sql);
                }    


                if ($mode=='update') {
                    #todo   //sql template je nach dbtype   
                    $sql=$oTPL->get_tpl('modul','update_fld',$data,'sql');
                    dbx_debug("update-DD=($dd) Tab=($tab) fld=($rid) Sql=($sql)",$data);
                    $ok =$oDD->rawQuery($server,$sql); 
                    dbx_debug("MOD-FLD ok=($ok)");      
                }

                if ($mode=='create') {
                    $sql=$oTPL->get_tpl('modul','create_fld',$data,'sql');
                    dbx_debug("create-Tab=($tab) fld=($rid) Sql=($sql)",$data);
                    $ok =$oDD->rawQuery($server,$sql);

                    dbx_debug("ADD-FLD ok=($ok)");

                }    
                        
            }
            if ($connect && $exist && $count > 0 && $type == 'sqlite' && $autosync) {
                // 1. store all records in array max=max_quick
                // 2. recreate db Table
                // 3. import all records from array
                dbx_debug("#RESTRUCT ($dd)");
                $map=array();
                $max_quick=dbx_get_cfg('dbxAdmin','max_quick'); 
 
                if ($old != $new) $map[$old]=$new; // map renamed fld   
               

                dbx_debug("CREATE new db-table OK=($ok) Count=($count) max=($max_quick)"); 

                
                if ($count <= $max_quick)  {
                    $recs=$oDD->select($dd, verify_access: 0);
                    $ok=$this->create_table_from_dd($dd);
                    if ($ok && $count) {
                        foreach ($recs as $no => $record) {
                            if ($old != $new) {
                                $record=$oDD->map_record($record,$map);
                                $record=$oDD->check_fields($dd,$record); // $verify_fields=1
                            }
                            $x=$oDD->insert($dd,$record,0,0,0,0); // quick execute
                            dbx_debug("#INSERT=($x) dd=($dd) rec=",$record);
                        } 
                    } 
                }  else { // big Data
                    $sysmsg="Datenbank wird neu strukturiert ($dd)";
                    $oForm=dbx_get_sys_object('dbxForm');
                    $content =$oForm->oTPL->get_tpl('dbx','alert-info',"msg=$sysmsg"); #todo 

                    $oProcess=dbx_get_sys_object('dbxProcess');
                    $oProcess->init('restruct_dd');       
                    $oProcess->set_property('dd_remap',$map);                                         
                    $oProcess->add("[modul=dbxAdmin]dbx_action=datadic&dbx_work=export_csv&dd=$dd&dbx_process=restruct_dd[/modul]");     // 1. write csv
                    $oProcess->add("[modul=dbxAdmin]dbx_action=datadic&dbx_work=restruct_tab&dd=$dd&dbx_process=restruct_dd[/modul]");   // 2. update db
                    $oProcess->add("[modul=dbxAdmin]dbx_action=datadic&dbx_work=import_csv&dd=$dd&dbx_process=restruct_dd[/modul]");     // 3. read csv
                    //$oProcess->add("[modul=dbxAdmin]dbx_action=datadic&dbx_work=list[/modul]");
                    //$content.=$oForm->get_js_close_modal('#dbxmodal1',1200);
                    //$content.=$oForm->get_js_autosubmit('report-dd-fields',1500); 
                    $content=$oProcess->run(); // first stepp of process
                    $oForm->fast_response($content,1); // with interpreter;  
                }
            
                        
            }




        }
        return $ok;
    }

    Private Function report_fields() {
        $content = ''; $modal_content='';
        $work= dbx_get_ModulVar( 'dbx_work' );
        $do  = dbx_get_ModulVar( 'dbx_do' );
        $rid = dbx_get_ModulVar( 'rid');
        $dd  = dbx_get_ModulVar( 'dd');


        if (!$dd) $dd=$rid; if (!$rid) $rid=$dd;   
        
        if ($work == 'list_fields') {
            if ( $do == 'row_edit' && $dd && $rid ) {             
               $modal_content=$this->edit_datadic_fld();
               return $modal_content;
            }
        }    


        
    



        //$DataDic = dbx_get_sys_object( 'dbxDataDictonary' );
        $DataDic = dbx_get_sys_object( 'dbxDD' );
  

        $oReport = dbx_get_sys_object( 'dbxReport' );
        $oReport->init( 'report-dd-fields' );

        if ( $do == 'row_delete' && $dd && $rid ) {       
            $ok = $this->delete_datadic_fld( $dd, $rid );
            if (  $ok ) $oReport->_msg_success  = 'Zeile gelöscht';
            if ( !$ok ) $oReport->_msg_error = 'Zeile konnte nicht gelöscht werden';
        }

        if ( $do == 'multi_delete' ) {
            //$ok = $oReport->del_selected( $tab, '*' );
        }



        $rdata = $DataDic->get_dd_fields( $dd );

        $oReport->_action = '?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=list_fields&dd='.$dd;
        $oReport->_ajax              = 0;
        $oReport->_pages             = 0;
        $oReport->_but_pagination    = 0;
        $oReport->_create_sel_flds   = 0;
        $oReport->_create_row_select = 0;
        $oReport->_create_row_edit   = 1;
        $oReport->_create_row_delete = 1;
        $oReport->_create_row_show    =0;
        $oReport->_table_buttons     ='left';
        //$oReport->_tabel_tpls['tpl_row_show']  = 'table_row_modal';

        $oReport->_tabel_tpls['tpl_row_edit']   = 'table_row_modal-edit';
        $oReport->_tabel_tpls['tpl_row_delete'] = 'modul|confirm_row_delete_fld';
   

        $oReport->_fld_id   = 'name';
        $oReport->_dbx_work = 'list_fields&dd='.$dd;
        
        $button['label']  = 'Neues Feld hinzufügen' ; 
        $button['dbx_get']= '?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=add_fld&dd='.$dd;
        $oReport->add_obj('add_fld', 'button-modal1', $button );


        if ( $oReport->submit() ) {
            if ( !$oReport->errors() ) {
                // submit && no errors
    
                $oReport->_msg_success   = 'Daten ausgewählt und sortiert';
            } else {
                $oReport->_msg_error = 'Prüfen sie bitte ihre Eingaben';
            }
        } else {
            // no submit

        }

        $flds['name']        = 'Name';
        $flds['type']        = 'Type';
        $flds['length']      = 'Länge';
        $flds['default']     = 'Default';
        $flds['index']       = 'Index';        
        $flds['label']       = 'Label';
        $flds['rules']       = 'Regeln';
        $flds['tooltip']     = 'Tooltip';
        $flds['errormsg']    = 'Fehlermeldung';
        $flds['placeholder'] = 'Platzhalter';
 
        //$flds['protect']     = 'Schützen';
        $flds['mask']        = 'Maske';
        $flds['data']        = 'Data';
        $flds['tpl']         = 'TPL';
        $flds['convert']     = 'Konvert';

        $flds['old_fld']     = 'Alter Name'; 

        $i=dbx_get_Remember('last_report_i',0,'*','dbx');
        $modal1['title']     ='DataDictonary Feld bearbeiten';
        $modal1['class']     ='modal-xl';   
        $modal1['on_close']  ="dbxReSendForm('#dbx_form_{i}')";     //'dbx_reload(2);'; // JS Event close modal reload and go to #end 


        $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);  
        $oReport->add_obj('modal1','obj-value',$modal_content);
        //$oReport->add_obj('modal1','obj-value','TEST-1b');

        $oReport->_rdata = $rdata;

        $content = $oReport->run( 0, $flds, 'table' );

        return $content;

    }


    Private function delete_datadic_fld($dd,$fld) {
        $ok=0;
        $new_fields=array();
        dbx_debug("### delete FLD   dd=($dd) Fld=($fld)");
        $oDD    = dbx_get_sys_Object( 'dbxDD' );
        $oTPL   = dbx_get_sys_Object( 'dbxTPL');
        $tab    = $oDD->get_dd_table($dd);
        $table  = $oDD->get_dd_table($dd,1);
        $server = $oDD->get_dd_server($dd);
        $fields = $oDD->get_dd_fields($dd);
        foreach ($fields as $no => $field) {
            if ( $field['name'] != $fld ) {
               $new_fields[]=$field; 
            }
        }
        dbx_debug("### TAB",$tab);
        dbx_debug("### TABLE",$table);
        dbx_debug("### FIELDS",$new_fields);


        $ok = $oDD->save_dd( $dd, $table, $new_fields );
        dbx_debug("SAVE-DD=($ok)") ;


        if ($ok) {
            $data['table'] = $tab;
            $data['fld']   = $fld;
            $sql=$oTPL->get_tpl('modul','del_fld',$data,'sql');
            dbx_debug("## Delete FLD Tab=($tab) fld=($fld) Sql=($sql)");
            $ok =$oDD->rawQuery($server,$sql);
        }

        return $ok;
    }

    Private Function edit_datadic_fld($new='') {
        $dd  = dbx_get_ModulVar( 'dd', '');
        $rid = dbx_get_ModulVar( 'rid', $new);
        $old_fld = $rid;
        $content = '';

        $oTPL= dbx_get_sys_object( 'dbxTPL' );
        $oDD = dbx_get_sys_object( 'dbxDD' );
        $xdata   = $oDD->get_dd_fields( $dd );
        $data    = array();
        foreach ($xdata as $no => $field) {
            if ($field['name'] == $rid ) {
               $data=$field;
               break;
            }
        }

        $oForm = dbx_get_sys_object( 'dbxForm' );   

        $form_id ='form-dd-field';
        $oForm->init( $form_id );
        $oForm->_data      = $data;
        $oForm->_msg_info  = "Sie können das Datenbank Feld ($dd) ($rid) bearbeiten";
        $oForm->_dd        = 'dd';
        $oForm->_action    = '?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=row_edit_fld&dd='.$dd.'&rid='.$rid;
        $oForm->_fld_change_state = '*'; // Important to get all flds 

        $types=$oDD->get_fld_types();  // all standart types from all db 
        $tpls =$oTPL->get_tpls('dbx'); 
        $index='0=ohne&PRI=Primär&MU=Mehrfach';

 
        $oForm->add_fld( 'name'       ,'text-label'         , ''        , 'parameter', 'Name' );
        $oForm->add_fld( 'type'       ,'select-single-label', $types    , 'parameter', 'Type' );
        $oForm->add_fld( 'length'     ,'text-label'         , ''        , 'number'   , 'Länge' );

        $oForm->add_fld( 'index'      ,'select-single-label', $index    , 'parameter', 'Index' );
        $oForm->add_fld( 'label'      ,'text-label'         , ''        , '*', 'Label' );
        $oForm->add_fld( 'default'    ,'text-label'         , ''        , '*', 'Default' );

        $oForm->add_fld( 'rules'      ,'text-label'         , ''        , '*', 'Validation' );        
        $oForm->add_fld( 'tooltip'    ,'text-label'         , ''        , '*', 'ToolTip' );
        $oForm->add_fld( 'errormsg'   ,'text-label'         , ''        , '*', 'Error MSG' );

        $oForm->add_fld( 'placeholder','text-label'         , ''        , '*', 'Placeholder' );
        $oForm->add_fld( 'mask'       ,'text-label'         , ''        , '*', 'Maske' );
        $oForm->add_fld( 'data'       ,'text-label'         , ''        , '*', 'Data' );

        $oForm->add_fld( 'tpl'        ,'select-single-label', $tpls     , '*', 'TPL' );
        $oForm->add_fld( 'prompt'     ,'text-label'         , ''        , '*', 'Prompt' );
        $oForm->add_fld( 'js'         ,'text-label'         , ''        , '*', 'JS' );


        $submit=$oForm->submit();
        $error =$oForm->errors();

        //return "Submit=($submit) error=($error)";

        if ( $oForm->submit() ) {
            if ( !$oForm->errors() ) {
                // submit && no errors && no warnings
                $change = $oForm->changed();
                if ( $change ) {
                    if ($rid == 'new') $mode='create';
                    if ($rid != 'new') $mode='update';
                    $ok = $this->save_field( $dd, $rid, $oForm->_post,$mode,$old_fld);
                    if ( $ok )  $oForm->_msg_success   = 'Daten gespeichert';
                    if ( !$ok ) $oForm->_msg_success   = 'Daten konnten nicht gespeichert werden';

                    if ($ok) { 
                        $fld=$oForm->_post['name'];
                        $sysmsg="Feld ($fld) gespeichert.";
                        $content =$oForm->oTPL->get_tpl('dbx','alert-success',"msg=$sysmsg");
                        $content.=$oForm->get_js_close_modal('#dbxmodal1',1200);
                        $content.=$oForm->get_js_autosubmit('report-dd-fields',1500);
                    } 

                } else {
                    $oForm->_msg_success = 'Keine Änderung';
                }
            } else {
                $err_flds = '';
                $errors = $oForm->_errors;
                foreach ( $errors as $key => $value ) {
                    $err_flds .= $key.' val=('.$value.')';
                }
                $oForm->_msg_error = 'Prüfen sie bitte ihre Eingaben ('.$err_flds.')';
            }
        }

        if (!$content) $content = $oForm->run();
        return $content;
    }

    private function get_def_new_dd($return='table') {
        $dev_path=dbx_get_base_dir().'dbx/modules/dbxAdmin/tpl/php/';
        $dev_path=dbx_os_path_file($dev_path);
        $dev_file=$dev_path.'new_dd.php';
        include $dev_file;
        if ($return == 'table')   return $table;
        if ($return == 'fields')  return $fields;
        return 0;
    } 


    Private Function edit_datadic_tab($new='') {
        $data  = array();
        $show_fields=0;
        $work  = dbx_get_ModulVar('dbx_work');
        $rid   = dbx_get_ModulVar( 'rid', $new, 'parameter' );
        $oDD   = dbx_get_sys_object( 'dbxDD' );
        $dd=$rid; 

        //return "RID=($rid) new=($new)";
        if ($rid != 'new') $data = $oDD->get_dd_table($dd,1);
        if ($rid != 'new') $show_fields=1;
        if ($rid == 'new') { 
            $table = $this->get_def_new_dd('table');
            $fields= $this->get_def_new_dd('fields');   
            $data= $table;
        }

        //dbx_debug("EDIT DATADIC FLDS=",$data);

        $content = '';
        $options_groups = array();
        $oForm = dbx_get_sys_object( 'dbxForm' );
        $oDD   = dbx_get_sys_object( 'dbxDD' );

        $user_groups = $oDD->select( 'dbx_user_groups', 'active = 1','*','description' );
        foreach ( $user_groups as $no => $record ) {
            $id    = $record['name'];
            $group = $record['description'];
            $options_groups[$id] = $group;
        }
        $yesNo[0] = 'Nein';
        $yesNo[1] = 'Ja';

        $config=dbx_get_cfg();
        $server['default']='default=('.$config['default_server'].')';
        foreach ($config['db'] as $serv => $serv_dev) {
            $server[$serv]=$serv.' | '.$serv_dev['type'];
        }


        $oForm->init( 'form-dd-table' );
        $oForm->_dd    = 'dd';
        $oForm->_data  = $data;
        $oForm->_fld_change_state='*';
        if ($rid != 'new') $oForm->_msg_info  = "Sie können das DataDictonary ($rid) bearbeiten";
        if ($rid == 'new') $oForm->_msg_info  = "Sie können eine neues DataDictonary erstellen";
        $oForm->_action    = '?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=row_edit_tab&rid='.$rid;

        // add_fld( $name, $tpl, $data = '', $rules = 'dd:', $label = 'dd:', $tooltip = 'dd:', $msg = 'dd:', $placeholder = 'dd:', $class = '' ) {
        //#
    
        $ddlist['label']='Liste der DataDictonarys';
        $ddlist['href'] ='?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=list_dd'; 
        $oForm->add_obj('report_dd','button',$ddlist);

        $oForm->add_fld( 'datadic'     , 'text-label'         , "value=$dd"   , 'parameter'      , 'DataDictonary' );
        $oForm->add_fld( 'autosync'    , 'select-single-label', $yesNo        , 'parameter'      , 'AutoSync' );

        $oForm->add_fld( 'server'      , 'select-single-label', $server       , 'parameter'     , 'Server' );
        $oForm->add_fld( 'table'       , 'text-label'         , ''            , 'parameter'     , 'Tabelle' );
        $oForm->add_fld( 'version'     , 'text-label'         , ''            , 'int'           , 'Version' );

        $oForm->add_fld( 'cache'       , 'select-single-label', $yesNo        , 'parameter'     , 'Cache' );
        $oForm->add_fld( 'trace'       , 'select-single-label', $yesNo        , 'parameter'     , 'Trace' );
        $oForm->add_fld( 'trash'       , 'select-single-label', $yesNo        , 'parameter'     , 'Trash' );

        $oForm->add_fld( 'read'        , 'multi-select-label' , $options_groups, 'array|parameter', 'Read:' );
        $oForm->add_fld( 'create'      , 'multi-select-label' , $options_groups, 'array|parameter', 'Create:' );
        $oForm->add_fld( 'update'      , 'multi-select-label' , $options_groups, 'array|parameter', 'Update:' );
        $oForm->add_fld( 'delete'      , 'multi-select-label' , $options_groups, 'array|parameter', 'Delete:' );

        $oForm->add_fld( 'read_owner'  , 'multi-select-label' , $options_groups, 'array|parameter', 'Read own:' );
        $oForm->add_fld( 'create_owner', 'multi-select-label' , $options_groups, 'array|parameter', 'Create own:' );
        $oForm->add_fld( 'update_owner', 'multi-select-label' , $options_groups, 'array|parameter', 'Update own:' );
        $oForm->add_fld( 'delete_owner', 'multi-select-label' , $options_groups, 'array|parameter', 'Delete own:' );

        if  ( $show_fields) $oForm->add_obj( 'dd_fields', 'obj-value',$this->report_fields());
        if  (!$show_fields) $oForm->add_obj( 'dd_fields', 'obj-value','');

        if ( $oForm->submit() ) {
            if ( !$oForm->errors() ) {
                // submit && no errors && no warnings
                
                $change = $oForm->changed();
                $dd     = $oForm->_post['datadic']; 
                $table  = $oForm->_post['table'];                
                 
                //return "change=($change) RID=($rid) DD=($dd) Tab=($table)";  

                if ( $change && $rid == 'new') {
 

                   if ($dd && $table) {
                      //return "SAVE change=($change) RID=($rid) DD=($dd) Tab=($table)"; 

                      $ok=$oDD->save_dd($dd,$oForm->_post,$fields);
                      if ( $ok) $oForm->_msg_success  = "DataDic ($dd) neu angelegt";
                      if (!$ok) $oForm->_msg_success  = "DataDic ($dd) konnte nicht angelegt werden.";
                      //$ok=$oDD->create_table_from_dd($dd);
                   }  
                }
                if ( $change && $rid != 'new') {
                    //return "change=($change) RID=($rid) DD=($dd) Tab=($table)";  

                    $ok = $this->save_table( $rid, $oForm->_post );
                    if (  $ok ) $oForm->_msg_success  = 'DataDic gespeichert';
                    if ( !$ok ) $oForm->_msg_success  = 'DataDic konnten nicht gespeichert werden';
                } else {
                    if (!$change) $oForm->_msg_success   = 'Keine Änderung';
                }
            } else {
                $err_flds = '';
                $errors = $oForm->_errors;
                foreach ( $errors as $key => $value ) {
                    $err_flds .= $key.' ';
                }
                $oForm->_msg_error = 'Prüfen sie bitte ihre Eingaben ('.$err_flds.')';
            }
        }

        $content = $oForm->run();

        return $content;

    }



    private function import_csv($dd,$remap=0) {
        $section=$dd.'_import_csv';

        dbx_debug("#import_csv dd=($dd) remap=",$remap);
        //exit; 

        $oImporter = new dd_import;
        $content=$oImporter->run($dd,$remap);
        return $content;
    }


    private function export_csv($dd=0) { 
        if (!$dd) $dd=dbx_get_ModulVar('dd',0,'parameter');
        $content="nix da";                
        $section=$dd.'_export_csv';
        $date_time=date('d-m-Y H:i:s');

      
        $oExporter=dbx_get_sys_object('dbxDBexport');

        $status=$oExporter->init($section);
        $timer =15; // fast submit
 
        dbx_debug("EXPORT-CSV DD=($dd) Process=$section  status=($status)");
 
        if ($status=='init') {
            dbx_debug("INIT=($status) dd=($dd)");

            $max_quick=dbx_get_cfg('dbxAdmin','max_quick');
            $path=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/');
            $file=$dd.'.csv';
          
            $path=dbx_get_ModulVar('path',$path);
            $file=dbx_get_ModulVar('file',$file);
            $max =dbx_get_ModulVar('max',$max_quick);

            $oExporter->set_property('path',$path,);
            $oExporter->set_property('file',$file,);
            $oExporter->set_property('dd'  ,$dd);
            $oExporter->set_property('charset','Windows-1252');

            $oExporter->set_property('seperator',dbx_get_ModulVar('seperator',';'));

            $oExporter->set_property('where'   ,dbx_get_ModulVar('where',''));
            $oExporter->set_property('columns' ,dbx_get_ModulVar('columns','*'));
            $oExporter->set_property('orderby' ,dbx_get_ModulVar('orderby',''));
            $oExporter->set_property('asc_desc',dbx_get_ModulVar('asc_desc','ASC'));
            $oExporter->set_property('groupby' ,dbx_get_ModulVar('groupby',''));
            $oExporter->set_property('max'     ,$max);
            $oExporter->set_property('offset'  ,0);
            $oExporter->set_property('count'   ,-1); 
         
        }

        $status =$oExporter->run($section);
        
        
        $path   =$oExporter->get_property('path'   ,0); 
        $file   =$oExporter->get_property('file'   ,0);
        $offset =$oExporter->get_property('offset' ,0);
        $count  =$oExporter->get_property('count'  ,0);
        $percent=$oExporter->get_property('percent',0);
  
        dbx_debug("Status of Exporter=($status) dd=($dd) offset=($offset) count=($count) Prozent=($percent)");


        $data=array();
        $oForm=dbx_get_sys_object('dbxForm');
        $oForm->init('form-'.$section,'form-export-csv');
        $oForm->_data    =  $data;
        $oForm->_action  = "?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=export_csv&dd=$dd";                        
        $oForm->_msg_info= "CSV Daten werden exportiert dd=($dd)";
        $oForm->_msg_success='';
 
        //$info='';  $oForm->_msg_info=''; $oForm->_msg_success='';

        $oForm->_msg_success = 'CSV Datei estellen';
    


        if ($status=='init') {
            //$records=$oExporter->get_records();
            //$this->write_csv($records,$section);
            $oExporter->write_csv_haeder();
            $progress=$oForm->get_tpl('dbx|alert-info');
            $msg ="Erstelle csv Datei ($file) Zeilen=($count)";
        }  

        if ($status=='run') {
            //$records=$oExporter->get_records();
            //$this->write_csv($records,$section);
            $oExporter->write_csv_line();
            $msg ="($percent %) Export ($dd) Datensätze=($count)";
            $progress=$oForm->get_tpl('dbx|progressbar-1');
        }  


        if ($status=='error') {
            $path_file=$path.$file;
            $msg ="Export ($dd) Fehler: Kann csv Datei ($path_file) nicht schreiben";
            $progress=$oForm->get_tpl('dbx|alert-danger');
            $status='end';
        }

        if ($status=='end') {
            //$oExporter->write_csv_line();
            $percent=100;
            //$progress=$oForm->get_tpl('dbx|alert-success');
            //$msg ="Export ($dd) Datensätze ($count)fertig";

            $progress=$oForm->get_tpl('dbx|progressbar-1');
            $msg ="($percent %) Export ($dd) Datensätze=($count)";

        }




        $pdata['msg']   = $msg;
        $pdata['width'] = $percent;
        $pdata['value'] = $percent;
    
        $label_button="dd=($dd) Daten exportieren status=($status) Prozent=($percent) count=($count)";        
        $bdata['id']   ='button_{i}';
        $bdata['sec']  =$timer;
        $bdata['label']=$label_button;
        
        $button  =$oForm->get_tpl('dbx|button-submit');

        //dbx_debug("Progress TPL=",$progress);        
    

        
        $oForm->add_obj('info'    ,'obj-value',$status);
        $oForm->add_obj('progress','obj-value',$progress,$pdata);
        $oForm->add_obj('button'  ,'obj-value',$button  ,$bdata);
       
        if ($status != 'end') $oForm->add_js_autosubmit('dbx_form_{i}',$timer);

        $content=$oForm->run();

        if ($status=='end') $content=$this->dbx_next_process($content);


        //dbx_debug("Session",$_SESSION['dbx']['session']['dbxAdmin']);
    

        return $content;
    }

    function browser_dd($dd) {
        $oReport=dbx_get_Modul_include_object('dbxDDbrouser');
        $oReport->_action="?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=brouse_dd&dd=$dd"; 
        $content=$oReport->browser_dd($dd);
        return $content;
    }

    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    public function run() {
        $work = dbx_get_ModulVar( 'dbx_work', 'list', 'parameter' );
        $content = "Unbekannter Aufruf dbx_work=($work)";
         
        switch ( $work ) {
           
           

            case 'brouse_dd':
                $dd =dbx_get_ModulVar('dd');
                $rid=dbx_get_ModulVar('rid');
                if (!$dd) $dd=$rid;
                if ($dd) $content=$this->browser_dd($dd);
            break;    

            case 'list_dd': 
                $content = $this->report_datadic();
            break;

            case 'list_fields': 
                $content = $this->report_fields();
            break;


            case 'row_edit_tab':
                $content = $this->edit_datadic_tab();
            break;

            case 'new_dd':
                $content= $this->edit_datadic_tab('new');
            break;    
 
            case 'add_fld':
                $content= $this->edit_datadic_fld('new');
            break;    

            case 'row_show_fld':
            case 'row_edit_fld':
                $content = $this->edit_datadic_fld();
            break;

            case 'row_delete_tab':
                $dd=dbx_get_ModulVar('rid');
                if ($dd)   $this->delete_table($dd);
                $content = $this->report_datadic();
            break;

            case 'row_delete_fld':
                $fld=dbx_get_PostGetVar('rid',0,'parameter');
                $dd =dbx_get_PostGetVar('tab',0,'parameter'); 
                //$content = "row_delete_fld $fld of dbxDataDic ($dd)";
                if ($fld && $dd) {
                   $oDD=dbx_get_sys_object( 'dbxDD' ); 
                   $fields=$oDD->delete_dd_fld($dd,$fld,1); 
                   //$content.= "DO row_delete_fld $fld of dbxDataDic ($dd)";
                }
                $content.= $this->report_fields();
            break;

            case 'export_csv':
                $dd=dbx_get_ModulVar('dd',0,'parameter');
                dbx_debug("export_csv dd=($dd)");
                if ($dd) {
                    $content=$this->export_csv($dd);
                } else {
                    $content = "Export CSV kein DataDictonary (dd)";
                }   
            break;
                 
            case 'restruct_tab':  
                $dd=dbx_get_ModulVar('dd',0,'parameter');
                $sync=1; // #todo from dd 'autosync' 
                $oTpl=dbx_get_sys_object('dbxTPL');
                if ($dd && $sync) $ok=$this->create_table_from_dd($dd);

                dbx_debug("#####RESTRUCT#### DD=($dd) OK=($ok)"); 
                if ($sync) {
                    if ($ok) {
                        $msg['msg']="OK DataDic ($dd) db-Table restrukt.";
                        $content=$oTpl->get_tpl('dbx','alert-info',$msg);
                        $content=$this->dbx_next_process($content,'restruct_dd');
                    } else {
                        $msg['msg']="ERROR DataDic ($dd) db-Table restrukt!";
                        $content=$oTpl->get_tpl('dbx','alert-danger',$msg);
                    }
                }
            break;

            case 'import_csv':
                $dd =dbx_get_ModulVar('dd' ,0,'parameter');
                $remap=$this->get_property('dd_remap',0,'restruct_dd'); 
                if ($dd) {                    
                   //return "#Import dd=($dd) csv=($csv)"; 
                   $content=$this->import_csv($dd,$remap); 
                } else {
                   $content = "Import CSV kein DataDictonary (dd)";
 
                }
            break;

            case 'edit_data':
                $obj=dbx_get_Modul_include_object('dbxDDbrouser');
                $content=$obj->edit_data();
            break;

                //case 'row_delete':
  
            //case 'row_show_tab':
            //    $dd=dbx_get_ModulVar('rid',0);
            //    if ($dd) $content=$this->browser_dd($dd); 
            //break;    


        }
        return $content;
    }

}
// class