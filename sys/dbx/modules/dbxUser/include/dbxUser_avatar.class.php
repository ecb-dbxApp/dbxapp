<?php
namespace dbx\dbxUser;

Class dbxUser_avatar {

  public function check_data($data) {
    $img_key='avatar'; $img_src='';
    $obs_key='obs_'.$img_key;
    $path  = dbx_get_base_dir().'dbx/modules/dbxUser/img/avatar/';
    $url   = dbx_get_base_url().'dbx/modules/dbxUser/img/avatar/';

    if (isset($data[$img_key])) $img_src=$data[$img_key];
    if (!$img_src) $img_src='avatar-0.png';
    $path_img_ext=$path.$img_src;
    $url_img_ext = $url.$img_src;
    if (!file_exists($path_img_ext)) $img_src='avatar-0.png';
    $data[$img_key]=$img_src;

    return $data;
  }


  public function run() {
     $content=''; $ok=false; $uid=0;

     $rid=dbx_get_ModulVar('rid',0);
     $obs=dbx_get_ModulVar('dbx_obs_fld');
     $obv=dbx_get_ModulVar('dbx_obs_val');
     if ($obs && $obv) $rid=$obv;

     //dbx_debug("AVATAR rid=($rid) OBS=($obs) OBV=($obv)");


     $oForm = dbx_get_sys_object('dbxForm');
     $db    = dbx_get_sys_object('dbxDB');
     $path  = dbx_get_base_dir().'dbx/modules/dbxUser/img/avatar/';
     $url   = dbx_get_base_url().'dbx/modules/dbxUser/img/avatar/';
     $target= dbx_get_GetVar('dbx_target');

     $tab    = 'dbx_user';
     $img_key= 'avatar';


     $data=$db->select1($tab,$rid,"id,userid,$img_key");
     if (is_array($data)) $uid=$data['userid'];

     $oForm->init('dbxUploadAvatar','form-avatar');
     $oForm->_action="?dbx_modul=dbxUser_admin&dbx_action=avatar_upload&rid=$rid"; // set_action() rid 'new' or record.id
     $oForm->_tpl='form-avatar_wait';
     $submit=$oForm->submit();

     if ($uid) {
       $oForm->_tpl='form-avatar';
       if (!empty($_FILES) ) {
         $oUpload=dbx_get_sys_object('dbxUpload');  // https://github.com/verot/class.upload.php
         $oUpload->upload($_FILES['upload_file']);
         $oUpload->allowed            = array('image/*');
         $oUpload->file_overwrite     = true;
         $oUpload->file_new_name_body = 'avatar-'.$uid;
         $oUpload->image_resize       = true;
         $oUpload->image_ratio_crop   = true;
         $oUpload->image_x            = 640;
         $oUpload->image_y            = 640;
         //$oUpload->image_ratio_y      = true;
         $oUpload->process($path);
         if ($oUpload->processed) {
           $img=$oUpload->file_dst_pathname;
           $field_values['id']=$rid;
           $field_values[$img_key]=$oUpload->file_dst_name;
           $ok=$db->save($tab,$field_values,$rid);
           if ($ok) {
             $data[$img_key]=$oUpload->file_dst_name;
             $url_img_ext=$url.$oUpload->file_dst_name;
             $img_src    =$oUpload->file_dst_name;
             $oForm->set_msg_ok("Bild Datei erfolgreich hochgeladen.");
           }
         } else {
           $content= 'error : ' . $oUpload->error;
           $oForm->set_msg_error("Bild Datei nicht hochgeladen.");
         }
         $oUpload->clean();
       } else {
         // Update Data if Filebrauser select Img
         if ($submit == 99998908) {
           $img_file=dbx_get_PostGetVar($img_key,0,'filename-img');
           if ($img_file) {
              $data[$img_key]=$img_file;
              $ok=$db->save($tab,$data,$rid);
              if ($ok) $oForm->_msg_success="Bild: ($img_file)";
           } else {
              
              $oForm->add_fld_error('x','x'); 
              //$img_file=dbx_get_PostGetVar($obs_key,0,'*');
              $oForm->_msg_error="Kein Bild: ($img_file)";
           }
         }
       }
       
       $oForm->add_js("dbxUploadImg('#uploader_{i}');");
     } // uid


     $oForm->_msg_info= 'Profil Bild (Avatar)';
     $oForm->_data=$this->check_data($data); // Image exist ?


     $img_src=$oForm->_data[$img_key];
     $url_img_ext=$url.$img_src;

     $observer='obs_rid';
     $observ['name']   =  $observer;
     $observ['form']   =  'dbx_form_{i}';
     $observ['observ'] =  'usr_rid'; // field must be dev inside one form of the view
     $observ['value']  =  $rid;
     $observ['old']    =  $rid;

     $img_data['dbx_get']    = '?dbx_modul=dbxUser_admin&dbx_action=set_observer';
     $img_data['dbx_target'] = $img_key.'_{i}';
     $img_data['id']         = 'selectimg_{i}';
     $img_data['name']       = $img_key;
     $img_data['src']        = $url_img_ext;
     $img_data['class']      = 'dbxImg';

     //$oForm->add_rep('rid',$rid);

     $oForm->add_obj($observer,'observer'    ,$observ);
     $oForm->add_obj($img_key ,'image-upload',$img_data);

     $oForm->add_obj('msg','alert-warning','msg=Ein Profilbild kann erst nach dem Speichern der Profildaten gewählt werden.');

     $oForm->add_obj('button-submit','dbx|button-submit','label=Save');

     $oForm->add_js_observe($observer,11500);


     $content=$oForm->run();


     return $content;
  }


}



?>
