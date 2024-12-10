<?php
namespace dbx\dbxContent_admin;
dbx_get_sys_object('dbxReport','use');


class dbxReport_Folder extends \dbxReport {
    public $_folders = array(); // speed up
    public $_groups  = array(); // speed up

    private function get_parent_folder_name($id) {
       $folders=$this->_folders;
       $folder = "(0) -root-";
       if ($id) {
          $folder_name='-?-';
          if (isset($folders[$id])) $folder_name=$folders[$id];
          $folder = "($id) $folder_name";
       }
       return $folder;
    }

    private function get_group_name($dat_groups) {
       $groups=$this->_groups;
       $groups_name=''; $gid=0;
       $dat_groups = explode(',',$dat_groups);
       //dbx_debug("DAT-Groups=",$dat_groups);
       foreach ($dat_groups as $no => $gid) {
          //dbx_debug("Group ID=($gid)");
          if (isset($groups[$gid])) {
            $groups_name.='[('.$gid.') '.$groups[$gid].'] ';
          } else {
            if ($gid) $groups_name.='('.$gid.') -?- ';
          }
       }
       return $groups_name;
    }



    public function run_body($content) {
        $record =$this->_record;
        if (isset($record['parent_id']))  $record['parent_id']  =$this->get_parent_folder_name($record['parent_id']);
        //if (isset($record['group_read'])) $record['group_read'] =$this->get_group_name($record['group_read']);
        $this->_record=$record;
        return $content;
    }
}




class dbxFolder_edit extends \dbxObj {


  Public $oValidator;
  Public $oTPL;


  public function __construct() {
     $this->oValidator=dbx_get_sys_object('dbxValidator');
     $this->oTPL      =dbx_get_sys_object('dbxTPL');
  }

  function make_select_data($data,$id,$name,$parent='') {
     $select_data=array();
     $select_data[0]='/ ';
     if (is_array($data)) {
      foreach ($data as $no => $record) {
         if (is_array($record)) {
            if ( isset($record[$id]) && isset($record[$name]) ) {
               $xid=$record[$id];
               $xna=$record[$name];
               if ($parent && isset($record[$parent])) {
                 $pid=$record[$parent];
                 if ($pid==$xid) $pid=0;
                 if ($pid) {
                   while ($pid > 0) {
                     foreach ($data as $no2 => $rec2) {
                       if ($rec2[$id]==$pid) {
                          $xna=$rec2[$name].' -> '.$xna;
                          $pid=$rec2[$parent];
                          break;
                       } // == $pid
                     } //foreach
                   } // while $pid > 0
                 } // $pid
               } // parent
               $xna='/ -> '.$xna;
               $select_data[$xid]=$xna;
            } // isset $id && $name
         } else {
            // ?
         }
      }
    }
    return $select_data;
  }

  function get_select_tpl($modul) {
     $pfx='c-';
     $select_data=array();
     $folder=$this->oTPL->get_tpl_dir('dbxContent').'htm/';

     dbx_debug("folder=($folder)");

     $folder_files=array_diff(scandir($folder), array('..', '.'));
     foreach ($folder_files as $no => $filename) {
        $id=substr($filename, 0 , (strrpos($filename, ".")));
        dbx_debug("folder-files=($filename) id=($id)");

        $na=$id;
        if ($pfx) {
          if (substr($na, 0, strlen($pfx)) != $pfx) $id=0;
        }
        if ($id) $select_data[$id]=$na;
     }
     //dbx_debug("Directory=($folder)",$folder_files);

     return $select_data;
   }







  public function run() {
     $content=''; $ok=false;
     $uid = dbx_get_CurrentUser();
     $rid = dbx_get_ModulVar('rid',0,'int');;
     //dbx_debug("#EDIT-Folder=($rid) uid=($uid)");

     if ($uid) {
       //dbx_set_sysVar('dbx_page','admin');
       $db  = dbx_get_sys_object('dbxDB');
       $lng = dbx_get_SysVar('dbx_lng','de');

       $dd_content='dbx_'.$lng.'_content';
       $dd_folder = $dd_content.'_folder';


       $data         =$db->select1($dd_folder,$rid);
       $data_folder  =$db->select($dd_folder);
       $data_folder  =$this->make_select_data($data_folder,'id','name','parent_id');
       $data_template=$this->get_select_tpl('dbxContent','c-');

       $options_groups=array();
       $user_groups=$db->select('dbx_user_groups','active = 1','*','name');
       foreach ($user_groups as $no => $record) {
         $id    =$record['name'];
         $group =$record['description'];
         $options_groups[$id]=$group;
       }



       $oForm=dbx_get_sys_object('dbxForm');
       $oForm->init('dbxContent_folder_edit','form-folder');
       $oForm->_data  = $data;
       $oForm->_action='?dbx_modul=dbxContent_admin&dbx_action=content&dbx_work=edit_folder&dbx_target=dbx_target_{i}&rid='.$rid; // fid 'new' or record.id

       $oForm->add_fld('id'        ,'text-label'           ,''            ,'int'              ,'ID');
       $oForm->add_fld('name'      ,'text-label'           ,''            ,'parameter|min=1'  ,'Ordner'            ,'Bezeichnung vom Ordner. Keine Sonderzeichen erlaubt.'); // #+
       $oForm->add_fld('parent_id' ,'select-single-label' ,$data_folder   ,'array|int'        ,'Unterordener von'  ,'Content Ordner');
       $oForm->add_fld('template'  ,'select-single-label' ,$data_template ,'parameter'        ,'Template'          ,'Content Template');
       $oForm->add_fld('group_read','multi-select-label'  ,$options_groups,'array|parameter'  ,'Zugriff Gruppen'   ,'Auswahl Gruppen');                                      // #+

       //dbx_debug("#EDIT-Folder=(data)",$data);


       if ($oForm->submit()) {
         //dbx_debug("#FORM-SUBMIT#");
         if (!$oForm->errors()) {      // submit && no errors // we ignore warnings
            //dbx_debug("#FORM-No-Errors");
            $change=$oForm->changed();
            if ($change) {
              //dbx_debug("#Form-Change");
              $ok=$oForm->save_post($dd_folder,$rid);
              if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
              if (!$ok) $oForm->_msg_success   = 'Daten konnten nicht gespeichert werden';
            } else {
              //dbx_debug("#Form-NO-Change");
              $oForm->_msg_success   = 'Keine Änderung';
            }
         } else {
            $flds='';
            $errors=$oForm->_errors;
            foreach ($errors as $fld => $msg) {
                $flds.=$fld.' ';
              // code...
            }
            $oForm->_msg_error = "Prüfen sie bitte ihre Eingaben ($flds)";

         }
       }


       $content= $oForm->run();
     }

     return $content;
  } // run()


} // class












// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Class dbxContent_folder {

  public $oTPL=dbx_get_sys_object('dbxTPL');

  function get_select_tpl($modul,$pfx='') {
     $select_data=array();
     $folder=$this->oTPL->get_tpl_dir($modul);
     $folder_files=array_diff(scandir($folder), array('..', '.'));
     foreach ($folder_files as $no => $filename) {
        $id=substr($filename, 0 , (strrpos($filename, ".")));
        $na=$id;
        if ($pfx) {
          if (substr($na, 0, strlen($pfx)) != $pfx) $id=0;
        }
        if ($id) $select_data[$id]=$na;
     }
     //dbx_debug("Directory=($folder)",$folder_files);

     return $select_data;
   }




   private function report_folder_flat() {

      //return 'folder-flat';

      $oReport = new dbxReport_Folder;
      $oDB     = dbx_get_sys_object('dbxDB');
      $lng     = dbx_get_ModulVar('lng','de');

      $dd_content='dbx_'.$lng.'_content';
      $dd_folder = $dd_content.'_folder';
      $dd_groups = 'dbx_user_groups';

      $rid=dbx_get_ModulVar('rid',0,'int');
      $do =dbx_get_ModulVar('dbx_do');

      if ($do == 'row_delete' && $rid) $oDB->delete($dd_folder,$rid);
   
      if ($do == 'row_edit' ) return $this->folder_edit($rid);
      if ($do == 'multi_delete') {
         $ids=$oReport->get_post('Report-content_select','','array|int');
         if (is_array($ids)) {
            foreach ($ids as $no => $rid) {
               $ok=$oDB->delete($dd_folder,$rid);
            }
         }
      }



      $folders=array(); $groups=array();
      $data_folder=$oDB->select($dd_folder);
      foreach ($data_folder as $no => $record) {
         $id          =$record['id'];
         $folders[$id]=$record['name'];
      }
      $data_groups=$oDB->select($dd_groups);
      foreach ($data_groups as $no => $record) {
         $id          =$record['id'];
         $groups[$id] =$record['name'];
      }

      $oReport->_folders =$folders; // speed up
      $oReport->_groups = $groups;   // speed up



      $flds['id']          ='ID';
      $flds['name']        ='Ordner';
      $flds['parent_id']   ='Parent';
      $flds['group_read']  ='Zugriff Lesen';

      $options_rsort['id']     ='ID';
      $options_rsort['name']   ='Ortner';


      $data['dbx_rrows']= 100;
      $data['dbx_rsort']='id';

      $oReport->init('report-folder-flat');
      $oReport->_data=$data;

      $oReport->_action='?dbx_modul=dbxContent_admin&dbx_action=content&dbx_work=list_folder'; // set_action() cid 'new' or record.id
      $oReport->_options_rsort = $options_rsort;
      $oReport->_but_pagination   =9;
      $oReport->_create_row_select=1;
      $oReport->_create_row_edit  =1;
      $oReport->_create_row_delete=1;
      $oReport->_tabel_tpls['tpl_row_edit']   = 'table_row_modal-edit';
      $oReport->_tabel_tpls['tpl_row_delete'] = 'modul|confirm_row_delete_folder';
      $oReport->_confirm_delete='modul|confirm-delete-methode';
      $oReport->_msg_info ='';


      $modal1['title']     ='Content Ordner bearbeiten';     
      $modal1['on_close']  ="dbx_reload('?');"; // JS Event close modal 
      $modal1['class']     ='modal-xxl';
      $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);

      $add['dbx_get']='?dbx_modul=dbxContent_admin&dbx_action=content&dbx_work=new_folder';
      $add['label']='Neuer Content Ordner';
      $oReport->add_obj('add_folder','button-modal1',$add);

      $oReport->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_work=multi_delete');
      $oReport->add_action('rows_activate'  ,'action_button_activate'  ,'&dbx_work=multi_activate');
      $oReport->add_action('rows_deactivate','action_button_deactivate','&dbx_work=multi_deactivate');

      $work=$oReport->get_sel('dbx_work');
      $rid =$oReport->get_sel('rid',0,'int');


      if($oReport->submit()) {
        if(!$oReport->errors()) {      // submit && no errors
           $work=$oReport->get_post('dbx_work');
           $oReport->_msg_success   = 'Daten ausgewählt und sortiert';
        } else {
           $oReport->_msg_err = 'Prüfen sie bitte ihre Eingaben';
        }
      } 

      // get all selections and order
      $rgroup='';
      $rwhere=$oReport->get_sel('dbx_rwhere','');
      $rrows =$oReport->get_sel('dbx_rrows',10);
      $rpos  =$oReport->get_sel('dbx_rpos',0);
      $rsort =$oReport->get_sel('dbx_rsort','id');
      $rdesc =$oReport->get_sel('dbx_rdesc','ASC');

      if ($rwhere) $rwhere="title  LIKE '$rwhere%' ";
      $oReport->_rcount=$oDB->count($dd_folder,$rwhere);
      $oReport->_rdata =$oDB->select($dd_folder,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);

      $oReport->add_obj('modal1','obj-value',$modal_content);
      $content=$oReport->run(1,$flds,'table');

   


      return $content;
   } // report_content_flat()


   private function folder_edit($fid=0) {
      $obj= new dbxFolder_edit;
      $content=$obj->run();
      return $content;
   }


   private function folder_delete() {
      $fid=dbx_get_PostGetVar('id',0,'int');
      return "Delete Folder ($fid)";

   }


   // ----------------------------------------------------

   public function run($work='') {
      if (!$work) $work=dbx_get_PostGetVar('dbx_work','flat');
      $content="Modul dbxContent_admin work($work) not defined";

      switch ($work) {
        case 'list_folder'; 
            $content=$this->report_folder_flat();
            break;
        case 'row_edit':
            $content=$this->report_folder_flat();
            break;
        case 'row_delete':
            $content=$this->report_folder_flat();
            break;
        case 'edit_folder':
            $content=$this->folder_edit();
            break;
         case 'new_folder':   
            $content=$this->folder_edit();
            break;
       
      }

      return $content;
   } // run

} // class



?>
