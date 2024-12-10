<?php
namespace dbx\dbxContent_admin;
//dbx_get_sys_object('dbxForm','use');




class dbxContent_sysdata extends \dbxObj {


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

  function get_select_tpl($modul,$pfx='') {
     $select_data=array();
     $folder=$this->oTPL->get_tpl_dir($modul).'htm';
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







  public function run() {

    //return "ich bin die sysdaten";
     $content=''; $ok=false;
     $uid = dbx_get_CurrentUser();
     $rid = dbx_get_ModulVar('rid',0,'int');
     $view= dbx_get_ModulVar('dbx_view');
     //dbx_debug("EDIT-CID=($rid) uid=($uid)");

     if ($uid) {
       //dbx_set_sysVar('dbx_page','admin');
       $db  = dbx_get_sys_object('dbxDB');
       $lng = dbx_get_SysVar('dbx_lng','de');

       $tab_content='dbx_'.$lng.'_content';
       $tab_folder = $tab_content.'_folder';


       $data         =$db->select1($tab_content,$rid);
       $data_folder  =$db->select($tab_folder);
       $data_folder  =$this->make_select_data($data_folder,'id','name','parent_id');
       $data_template=$this->get_select_tpl('dbxContent','c-');

       $options_groups=array();
       $user_groups=$db->select('dbx_user_groups','active = 1','*','name');
       foreach ($user_groups as $no => $record) {
         $id    =$record['name'];
         $group =$record['description'];
         $options_groups[$id]=$group;
       }
       $options_groups['zzzz']='<hr>'; // no grops

       $oForm=dbx_get_sys_object('dbxForm');
       $oForm->init('dbxContent_edit','form-sysdata');
       $oForm->_data=$data;
       //$oForm->_action='?dbx_modul=dbxContent_admin&dbx_action=sysdata&rid='.$rid; // set_action()
       $oForm->_action="?dbx_modul=dbxContent_admin&dbx_action=content&dbx_work=edit_sysdata&dbx_view=$view&rid=$rid";
       $oForm->_msg_info   ='Content Sytemdaten bearbeiten';
       $oForm->_msg_success='Content gespeichert';
       //$options_select=$oForm->get_select_data('group_read');

       //add_fld($name,$tpl='dd:',$data='dd:',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='',$remap='') { //#

       $oForm->add_fld('id'        ,'text-label');                   //#+

       $oForm->add_fld('title'     ,'text-label');
       $oForm->add_fld('sorter'    ,'text-label');
       $oForm->add_fld('keywords'  ,'text-label');
       $oForm->add_fld('permalink' ,'text-label');
       $oForm->add_fld('activ'     ,'checkbox-label');
       $oForm->add_fld('hits'      ,'text-label');


       $oForm->add_fld('folder'    ,'select-single-label',data: $data_folder);
       $oForm->add_fld('template'  ,'select-single-label',data: $data_template);
       $oForm->add_fld('group_read','multi-select'       ,data: $options_groups, rules: '*'); //,$options_select);
   

       $observer='obs_content_rid';
       $observ['name']   =  'content_rid';
       $observ['observ'] =  'content_rid';
       $observ['value']  =  $rid;
       $observ['old']    =  $rid;
       $oForm->add_obj($observer,'dbx|observe',$observ);
       $oForm->add_js_call('group_read','multiselect2');

       //$oForm->add_js_observe($observer,'dbx_form_{i}',1500);

       if($oForm->submit()) {
         //dbx_debug("#FORM-SUBMIT#");
         if(!$oForm->errors()) {      // submit && no errors // we ignore warnings
            //dbx_debug("#FORM-No-Errors");
            $change=$oForm->changed();
            if ($change) {
              //dbx_debug("#Form-Change");
              $ok=$oForm->save_post($tab_content,$rid);
              if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
              if (!$ok) $oForm->_msg_success   = 'Daten konnten nicht gespeichert werden';
            } else {
              //dbx_debug("#Form-NO-Change");
              $oForm->_msg_success   = 'Keine Änderung';
            }
         }
         if ($oForm->errors()) {
            $flds='';
            $errors=$oForm->_errors;
            foreach ($errors as $fld => $msg) {
                $flds.=$fld.' ';
              // code...
            }
            $oForm->_msg_error = "Prüfen sie bitte ihre Eingaben ($flds)";

         }
       }

       $rid=$oForm->_data['id']; // Value nach dem speichern
       $oForm->add_obj('obs_rid','dbx|observe',"name=content_rid&value=$rid"); // wird von avatar upload überwacht (observed)

       $content= $oForm->run();

     }

     return $content;
  } // run()


} // class

?>
