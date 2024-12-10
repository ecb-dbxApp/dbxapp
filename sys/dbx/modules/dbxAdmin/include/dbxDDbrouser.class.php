<?php
namespace dbx\dbxAdmin;
dbx_get_sys_object( 'dbxReport', 'use' );

class dbxDDBrouser extends \dbxReport {

    public function edit_data() {
  
        $add_data=array();
        $content=''; $options_groups=array();
        
        $oForm = dbx_get_sys_object('dbxForm');
        $db    = dbx_get_sys_object('dbxDB');
    
        $do    = dbx_get_ModulVar('dbx_do');
        $rid   = dbx_get_ModulVar('rid',0,'int');
        $dd    = dbx_get_ModulVar('dd');
     
    
        $oForm->init('form-dd-data');
    
        //dbx_debug("### USER EDIT ID=($rid)  Action=($action) ####");
        $data=$db->select1($dd,$rid);
    
          
        $oForm->_data      = $data;
        $oForm->_msg_info  = 'Sie können die Daten bearbeiten';
        $oForm->_dd        = $dd;  // Main db-Table
        $oForm->_action    = "?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=edit_data&dd=$dd&rid=$rid";


        $fields=$db->get_dd_fields($dd);
      
        foreach ($fields as $no => $field) { 
            $tpl   ='text-label';
            $xname =$field['name'];
            $xlabel=$field['label'];
            $oForm->add_fld($xname,$tpl);
        } 

    
        if($oForm->submit()) {
            if (!$oForm->errors()) {      // submit && no errors && no warnings
              $change=$oForm->changed();
              if ($change) {
              
                 $ok=$oForm->save_post($dd,$rid);
                 //dbx_debug("SAVE ($dd)=($ok)");
     
                 if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
                 if (!$ok) $oForm->_msg_success   = 'Daten konnten nicht gespeichert werden';
               
    
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
            // $oForm->add_obj('form_msg','obj-value','Daten bearbeiten');
        }
        $content=$oForm->run();
    
        return $content;
      }
    
        



    
    function row_edit() {
        return $this->edit_data();
    }
  





    function browser_dd($dd) {
        
        $oDB=dbx_get_sys_object('dbxDB'); 
        $form_tpl= 'report-dd'; 
        $form_id = $dd.'_'.$form_tpl;
        $flds=$oDB->get_dd_fields($dd,2); // with label=name
        $dd   = dbx_get_ModulVar('dd'); 
        $do   = dbx_get_ModulVar('dbx_do');
        $rid  = dbx_get_ModulVar('rid');
        if (!$dd) $dd=$rid;
        
        if ($do == 'add' || $do == 'row_edit') {
            $modal_content=$this->row_edit();
            return $modal_content;
        }
        if ($do == 'row_delete' && $rid) {
            $ok=$oDB->delete($dd,$rid);
        }
        
        $this->init($form_id,$form_tpl);       
        $this->_action="?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=brouse_dd&dd=$dd"; 


        //$this->_add_action="&dd=$dd"; // for action buttons edit delete .....
 
        $this->_but_pagination   =7;
        $this->_create_row_select=1;
        $this->_create_row_edit  =1;
        $this->_create_row_delete=1;
        $this->_data_table       =0; //'auto';
        $this->_scroll_table     =1;

        $this->_tabel_tpls['tpl_row_edit']   = 'table_row_modal-edit';
        //$this->_tabel_tpls['tpl_row_delete'] = 'modul|confirm_row_delete_rec';
        //$this->_confirm_delete='modul|confirm-delete-rec';
            
        $this->_options_rsort=$flds;
    

        $this->_dbx_work="brouse_dd&dd=$dd";
  

        $rgroup=''; $type='?';

        $rwhere=$this->get_sel('dbx_rwhere' ,'');
        $rrows =$this->get_sel('dbx_rrows'  ,10);
        $rpos  =$this->get_sel('dbx_rpos'   ,0);
        $rsort =$this->get_sel('dbx_rsort'  ,'id');
        $rdesc =$this->get_sel('dbx_rdesc'  ,'ASC');
        $select=$this->get_sel('dbx_rselect',0);

        $add_rec['dbx_get']="?dbx_modul=dbxAdmin&dbx_action=datadic&dbx_work=brouse_dd&dd=$dd&dbx_do=add&rid=new";
        $add_rec['label']='Neuer Datensatz';
        $this->add_obj('add_rec','button-modal1',$add_rec);
        $this->add_fld('sql','textarea-label',"label=SQL ($dd) use {tab} for Table",'*');

        $sql_select=0; 

 

        if($this->submit()) {
            if(!$this->errors()) {      // submit && no errors
               $this->_msg_success = 'Daten ausgewählt und sortiert'; 
               $sql=dbx_get_PostGetVar('sql','','*');
               if ($sql > '') {
                $dd_tab=$oDB->get_dd_table($dd);
                $sql=str_replace('{tab}', $dd_tab, $sql);
                $oAnayzer=dbx_get_sys_object('dbxAnalyzerSQL');
                $oAnayzer->analyze($sql);

                //$dd,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos


                $type   =$oAnayzer->getType('SELECT');
                 
                //dbx_debug("SQL-Type=($type) SQL=($sql)");                    


                if ($type == 'SELECT') {
                    $flds   =$oAnayzer->getFields('*');
                    $tab    =$oAnayzer->getTable($dd);
                    $rwhere =$oAnayzer->getWhere($rwhere);
                    $rsort  =$oAnayzer->getOrderBy($rsort);
                    $rgroup =$oAnayzer->getGroupBy($rgroup);
                    $rrows  =$oAnayzer->getLimit($rrows);
                    $rpos   =$oAnayzer->getOffset($rpos);

                    $flds   =$oDB->get_rpt_fields($dd,$flds,2); // Label = name 
                }

    

                if ($type != 'SELECT') {
                    $server= $oDB->get_dd_server($dd);
                    $query = $sql;
                    $ok=$oDB->rawQuery($server,$query);

                    dbx_debug("#RAW-QUERY type=($type)  Server=($server)  Query=($query) ok=",$ok);  

                    if ($ok == -2) {
                        $this->add_fld_error('sql','error');
                        $this->_msg_error = 'SQL Fehler ('.$sql.') = '.$oDB->_dbMessage;
                    } else {
                        $this->_msg_success ="SQL ok ($ok) Datensätze wurden bearbeitet";  
                    }
                }
               }
               
            } else {
               $this->_msg_error = 'Prüfen sie bitte ihre Eingaben';
            }
        } else { // no submit
            $rid=dbx_get_PostGetVar('rid',0,'int');
            if ($do == 'row_edit' && $rid) {
                $modal_content=$this->row_edit();
            }
            if ($do == 'row_delete' && $rid) {
               $ok=$oDB->delete($dd,$rid);
               if ( $ok) $this->_msg_info   = 'Zeile gelöscht';
               if (!$ok) $this->_msg_info   = 'Zeile konnte nicht gelöscht werden';
            }
        }


        $i=dbx_get_Remember('last_report_i',0,'*','dbx');
        $modal1['title']     ='DataDic Data Brouser';     
        $modal1['on_close']  ="dbxReSendForm('#dbx_form_{i}')";     // "dbx_reload('?');"; // JS Event close modal '?' = current self url
        $modal1['class']     ='modal-xxl';
        $modal_content=$this->oTPL->get_tpl('dbx','modal1',$modal1);


        $this->add_obj('add_rec','button-modal1',$add_rec);
        $this->add_obj('modal1' ,'obj-value'    ,$modal_content);
       

        $all=$oDB->count($dd);
        $this->_rcount=$oDB->count($dd,$rwhere);
        $this->_rdata =$oDB->select($dd,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);  
        if (!is_array($this->_rdata)) {
           $this->add_fld_error('sql','error');
           $this->_msg_error = 'SQL Fehler = '.$oDB->_dbMessage;
        }      

        //dbx_debug("dd=($dd) where=($rwhere) rsort=($rsort) rdesc=($rdesc) rgroup=($rgroup) rrows=($rrows) rpos=($rpos)",$flds); 


        $this->add_js_call('dbx_table','datatable1');
        $this->_rpt_format='html-chars';

        $content=$this->run(1,$flds,'table');
        return $content;
    }    

} 
