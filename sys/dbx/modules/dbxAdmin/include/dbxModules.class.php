<?php
namespace dbx\dbxAdmin;

Class dbxModules {

   Private function get_modul_col($xmodul) {
      $col_obj='[modul=dbxAdmin]dbx_action=modules&dbx_work=modul_access&dbx_do=edit&xmodul='.$xmodul.'[/modul]';
      return $col_obj;
   }

   private function save_config_modul($modul,$data) {
      $ok=dbx_set_cfg($modul,$data);
      return $ok;
   }

   private function modul_access() {
      $db    = dbx_get_sys_object('dbxDB');
      $xmodul= dbx_get_ModulVar('xmodul');

      $content="Modul=($xmodul)"; // return $content;

      $data  = dbx_get_cfg($xmodul);

      $options_groups['admin'] ='Admin';
      $options_groups['guest'] ='Gast';
      $options_groups['member']="Mitglied";
      $user_groups=$db->select('dbx_user_groups','active = 1');
      foreach ($user_groups as $no => $record) {
        $id    =$record['name'];
        $group =$record['description'];
        //$group.=' (' .$record['description'] .')';
        $options_groups[$id]=$group;
      }
      //$groups=$data['groups'];

      //foreach ($groups as $key => $value) {
      //     $options_select[$value] =1;
      //}

      $oForm= dbx_get_sys_object('dbxForm');
      $oForm->init('form-modul-access');
      $oForm->_data=$data;
      $oForm->_msg_info= 'Zugriff auswählen';


      $oForm->add_obj('xmodul',$xmodul);
      $oForm->add_fld('groups','multi-select', $options_groups,'array|parameter','Gruppen','Die Eingabe darf keine Sonderzeichen beinhalten.'); // #+
      $oForm->add_js_call('groups_{i}','multiselect');


      //foreach ($data as $key => $value) {
      //  if ($key != 'groups') $oForm->add_fld($key,'text-label','','alphanum'); //#+
      //}
      if($oForm->submit()) {
        if(!$oForm->errors()) {      // submit && no errors
           $ok=$this->save_config_modul($xmodul,$oForm->_post);
           if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
           if (!$ok) $oForm->_msg_success   = 'Daten konnten nicht gespeichert werden';
        } else {
           $oForm->_msg_errr = 'Prüfen sie bitte ihre Eingaben';
        }
      }

      $content=$oForm->run();
      $content=(str_replace('{xmodul}',$xmodul,$content));  // #todo als rep-func in oForm

      return $content;
   }





   private function modul_avatar() {

      $modul =dbx_get_SysVar('dbx_modul');
      $uid   =dbx_get_CurrentUser();
      $xmodul=dbx_get_ModulVar('xmodul');


      if (!$xmodul) return "kein Modul ($xmodul)";

      if (!$uid) $content="No Access Uid=($uid)<br>";
      if ($uid) {

        $content=''; $ok=false;


        $path  =dbx_os_path_file(dbx_get_base_dir()."dbx/modules/$xmodul/tpl/img/");
        $url  = dbx_get_base_url()."dbx/modules/$xmodul/tpl/img/";
        $target=dbx_get_GetVar('dbx_target');
        $modul_img='modul.gif';


        $path_img_ext=$path.$modul_img;
        $url_img_ext=$url.$modul_img;
        if (!file_exists($path_img_ext)) {
           // copy Platzhater IMG zu Destination
        }



        $data=array();
        $data['xmodul']=$xmodul;
        $data['avatar_upload']=$url_img_ext;

        $oForm=dbx_get_sys_object('dbxForm');
        $oForm->_msg_info= 'Sie können ein Modulbild auswählen';
        $oForm->init('dbxModules_avatar','form-avatar');
        $oForm->_data=$data;
        $oForm->add_js_call('uploader_img','upload');
        $oForm->_msg_info='';
        $oForm->_msg_info='';
        $oForm->_msg_success='';
        //<script>dbxUploadImg('#uploader_img_{i}');</script>
        //$oForm->add_js("dbxUploadImg('#uploader_img_{i}');");

        if (!empty($_FILES) ) {
          $oUpload=dbx_get_sys_object('dbxUpload');  // https://github.com/verot/class.upload.php
          $oUpload->upload($_FILES['upload_file']);
          $oUpload->allowed            = array('image/*');
          $oUpload->file_new_name_body = 'modul';
          $oUpload->image_convert      = 'gif';
          $oUpload->file_overwrite     = true;
          $oUpload->image_resize       = true;
          $oUpload->image_x            = 200;
          $oUpload->image_y            = 200;
          //$oUpload->image_ratio_X      = true;
          $oUpload->process($path);
          if ($oUpload->processed) {
            $img=$oUpload->file_dst_pathname;
            //$this->add_js_ok($img,$target);
            $oUpload->clean();
            //$field_values['avatar']=$oUpload->file_dst_name;
            $oForm->_data['avatar_upload']=$url.$oUpload->file_dst_name;
            $oForm->_msg_success='Bild Datei erfolgreich hochgeladen.';
          } else {
            $content= 'error : ' . $oUpload->error;
            $oForm->msg_error("Bild Datei nicht hochgeladen.");
          }
    		}
        $oForm->add_fld('avatar_upload','avatar_upload','','alphanum','Modul-Bild','Sie können ein Bild hochladen.','','','dbx-avatar-modul'); // #+
        $content= $oForm->run();
        $content=(str_replace('{xmodul}',$xmodul,$content));
      }
      return $content;
   }


   Private function report_modules() {

      $path = dbx_os_path_file(dbx_get_base_dir().'dbx/modules/');
      $dh = opendir($path);

      $data=array(); $modules=array();
      while(($file = readdir($dh)) !== false) {
        $pos = strpos($file, '.');
        if ($pos === false) {
          $xmodul=$file;
          $record['xmodul'] =$xmodul;
          if ($xmodul != 'tpl') $modules[]=$record;
        }
      }

      //dbx_debug("MODULE=($path)",$modules);

      $oReport =dbx_get_sys_object('dbxReport');
      $oReport->init('dbxModules','report-modules');
      $oReport->_data=$data;
      $oReport->_rdata=$modules;

      $edit['label']  ='Config {xmodul} bearbeiten';
      $edit['dbx_get']='?dbx_modul=dbxAdmin&dbx_action=config&dbx_work=edit&xmodul={xmodul}'; 

      $edit_config=$oReport->get_tpl('button-modal1',$edit);

      



      $oReport->add_obj('edit'  ,'obj-value',$edit_config);
      $oReport->add_obj('ximg'  ,'obj-value','[modul=dbxAdmin]dbx_action=modules&dbx_work=modul_avatar&xmodul={xmodul}[/modul]');
      $oReport->add_obj('xmodul','obj-value',$this->get_modul_col('{xmodul}'));

      $modal1['title']     ='Modul Config';     
      $modal1['on_close']  ="dbx_reload('?');"; // JS Event close modal '?' = current self url
      $modal1['on_close']  ="dbxReSendForm('#dbx_form_{i}')"; // JS Event close modal '?' = current self url
      $modal1['class']     ='modal-xl';
      $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);
      $oReport->add_obj('modal1','obj-value',$modal_content);


      $content=$oReport->run();

      return $content;
   }


   public function modul_new() {
      $obj=dbx_get_Modul_include_object('dbxWizard');
      $run=dbx_get_ModulVar('run');
      $content=$obj->run($run);
      return $content;
   }

   public function modul_edit() {
      $obj=dbx_get_Modul_include_object('dbxWizard');
      $run=dbx_get_ModulVar('run');
      $content=$obj->run($run);
      return $content;
   }





   public function run() {
      $modul =dbx_get_ModulVar('dbx_modul');
      $action=dbx_get_ModulVar('dbx_action');
      $work  =dbx_get_ModulVar('dbx_work'); 
      $content="dbxAdmin->dbxModules ($work) X<br>";

      switch ($work) {

         case 'modul_list':
            $content=$this->report_modules();
         break;

  

         case 'modul_avatar';
            $content=$this->modul_avatar();
         break;

         case 'avatar_upload':
            $content=$this->modul_avatar();
         break;   


         case 'modul_new':
            $content=$this->modul_new();
         break;

         case 'modul_edit':
            $content=$this->modul_edit();
         break;


         case 'modul_access':
            $content=$this->modul_access(); // Inline report
         break;      


         default:
            $oTPL=dbx_get_sys_object('dbxTPL');
            $msg['msg']="Modul=($modul) Action=($action) Work=($work) is undef!";
            $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

      }
      return $content;
   }

}




?>