<?php
namespace dbx\dbxContent_admin;

class dbxContent_sysdata {



  function make_select_data($data,$id,$name,$parent='') {
     $loop=0; // break if endless
     $select_data=array();
     if (is_array($data)) {
      foreach ($data as $no => $record) {

         $loop = ($loop + 1);
         if ($loop > 9) break;

         if (is_array($record)) {
            if ( isset($record[$id]) && isset($record[$name]) ) {
               $xid=$record[$id];
               $xna=$record[$name];
               if ($parent && isset($record[$parent])) {
                 $pid=$record[$parent];
                 if ($pid) {
                   while ($pid > 0) {
                     $loop = ($loop + 1);
                     $last_pid=$pid;
                     //dbx_debug ("Parent-PID=($pid) Last=($last_pid)");
                     foreach ($data as $no2 => $rec2) {
                       if ($rec2[$id]==$pid) {
                          $xna=$rec2[$name].' -> '.$xna;
                          $pid=$rec2[$parent];
                          break;
                       } // == $pid
                     } //foreach data -> rec2
                     if ($last_pid==$pid || $loop > 9) {
                        $pid=0;
                        break;
                     }
                   } // while $pid > 0
                 } // $pid
               } // parent
               $select_data[$xid]=$xna;
            } // isset $id && $name
         } else {
           // No record
         }
      } // foreach data -> record
    } // array data
    sort($select_data);
    return $select_data;
  }

  function get_select_tpl($modul,$pfx='') {
     $oTpl=dbx_get_sys_object('dbxTpl');
     $select_data=array();
     $folder=$oTpl->get_tpl_dir($modul);
     $folder_files=array_diff(scandir($folder), array('..', '.'));
     foreach ($folder_files as $no => $filename) {
        $id=substr($filename, 0 , (strrpos($filename, ".")));
        $na=$id;
        if ($pfx) {
          if (substr($na, 0, strlen($pfx)) != $pfx) $id=0;
        }
        if ($id) $select_data[$id]=$na;
     }
     dbx_debug("Directory=($folder)",$folder_files);

     return $select_data;
   }



  private function get_content_images($data) {
    $content_images='';
    for ($i=1; $i <= 6; $i++) {
       $key='upload'.$i;
       $img['name'] ="Bild-($i)";
       $img['value']=$data[$key];
    }

    //$oForm->get_tpl('dbxContent_admin','content_image',$img);


    return $content_images;
  }



  public function run() {
    
    dbx_debug('run->contnten-sysdata');
    
    $content='no uid or minus'; $ok=false;
    $uid = dbx_get_CurrentUser();
    $rid = dbx_get_ModulVar('rid',0,'int'); 


    dbx_debug("EDIT-CID=($rid) uid=($rid)");

  
    //dbx_set_sysVar('dbx_page','admin');
    $db  = dbx_get_sys_object('dbxDB');
    $lng = dbx_get_ModulVar('lng','de');

    $dd_content='dbx_'.$lng.'_content';
    $dd_folder = $dd_content.'_folder';

    


    $data=$db->select1($dd_content,$rid);

    dbx_debug("#content edit rid=($rid) dd_content=($dd_content) dd_folder=($dd_folder) Data=",$data ); 



    $data_folder=$db->select($dd_folder);
    $data_folder=$this->make_select_data($data_folder,'id','name','parent_id');

    $data_template=$this->get_select_tpl('dbxContent','C-');

    $options_groups=array();
    $options_select=array();
    $user_groups=$db->select('dbx_user_groups');
    foreach ($user_groups as $no => $record) {
      $id    =$record['id'];
      $group =$record['name'];
      $group.=' (' .$record['description'] .')';
      $options_groups[$id]=$group;
    }
    $options_select=$data['group_read'];
    //dbx_debug("options_select",$options_select);


    $modal1['title']     ='#image_select#';
    $modal1['label']     ='Filebrauser';
    $modal1['dbx_get']   ='?dbx_modul=dbxContent_admin&dbx_action=ibrowser&dbx_caller=selectimg_{i}&dbx_cols=2';
    $modal1['dbx_target']='dbxmodal1_content';


    //dbx_debug("SELECT DATA",$data);

    //return $content;
    $oForm=dbx_get_sys_object('dbxForm');
    $oForm->init('form-edit');
    $oForm->_data=$data;
    $oForm->_action='?dbx_modul=dbxContent_admin&dbx_action=edit&dbx_target=dbx_target_{i}&cid={dbx:cid}'; // set_action() cid 'new' or record.id


    $oForm->add_fld('title'     ,'text-label');
    $oForm->add_fld('sorter'    ,'text-label');
    $oForm->add_fld('keywords'  ,'text-label');
    $oForm->add_fld('permalink' ,'text-label');
    $oForm->add_fld('folder'    ,'select-single-label',data: $data_folder);
    $oForm->add_fld('template'  ,'select-single-label',data: $data_template);
    $oForm->add_fld('group_read','multi-select'       ,data: $options_groups); //,$options_select);




    if ($oForm->submit()) {
      if(!$oForm->errors()) {      // submit && no errors // we ignore warnings
        $change=$oForm->changed();
        if ($change) {
          $ok=$oForm->save_post($dd_content,$rid);
          if (!$ok) $oForm->_msg_success = '#error_save_data#';
          if ( $ok) {
            $oForm->_msg_success = '#success_save_data#';
          }
        } else {
          $oForm->_msg_success   = '#no_change#';
        }
      } else {
        $oForm->_msg_errr = '#check_input#';
      }
    }
    $content= $oForm->run();


     // switch between 'new' and record.id
     //$new=$oForm->_new_record_id; if ($new) $rid=$new;
     //$content=dbx_replace('dbx:rid',$rid,$content);
     // - - - -

     return $content;
  } // run()


} // class

?>
