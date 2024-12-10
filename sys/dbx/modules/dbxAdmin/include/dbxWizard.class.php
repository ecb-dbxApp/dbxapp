<?php
namespace dbx\dbxAdmin;

Class dbxWizard {

  private function create_modul($modul_name,$modul_path) {
     $oTpl=dbx_get_sys_object('dbxTPL');
     $typ  ='php';
     $modul='dbxAdmin';

     $content=$oTpl->read_tpl('dbxAdmin','new_modul','php');



     $content=str_replace('{modul}',$modul_name,$content);
     $file_path_name=$modul_path.'/'.$modul_name.'.class.php';
     $file_path_name=dbx_os_path_file($file_path_name);
     file_put_contents($file_path_name,$content);

     @mkdir($modul_path.'/cfg',0777,TRUE);
     @mkdir($modul_path.'/img',0777,TRUE);
     @mkdir($modul_path.'/design/default/htm',0777,TRUE);
     @mkdir($modul_path.'/design/default/img',0777,TRUE);
     @mkdir($modul_path.'/design/mobile/htm',0777,TRUE);
     @mkdir($modul_path.'/design/mobile/img',0777,TRUE);

     @mkdir($modul_path.'/include',0777,TRUE);


     $content=$oTpl->read_tpl('dbxAdmin','new_modul_inc','php');

     $content=str_replace('{modul}',$modul_name,$content);
     $file_path_name=$modul_path.'/include/'.$modul_name.'_test.class.php';
     $file_path_name=dbx_os_path_file($file_path_name);
     file_put_contents($file_path_name,$content);

     $from_img=dbx_get_base_dir().'dbx/modules/dbxAdmin/img/new_modul.gif';
     $to_img  =dbx_get_base_dir().'dbx/modules/'.$modul_name.'/img/modul.gif';

     $from_cfg=dbx_get_base_dir().'dbx/modules/dbxAdmin/cfg/new_config.php';
     $to_cfg  =dbx_get_base_dir().'dbx/modules/'.$modul_name.'/cfg/config.php';


     @copy($from_img,$to_img );
     @copy($from_cfg,$to_cfg );

     //dbx_set_SessionVal('msg_new_modul',"Das Modul ($modul_name) wurde erfolgreich erstellt");
     //dbx_redirect('?dbx_modul=dbxAdmin&dbx_action=modules&dbx_page=admin');

  }


  public function new_modul() {
    $content="Ein Wizard für ein neues Modul";
    //$msg_init=dbx_get_SessionVal('msg_new_modul');

    $oForm=dbx_get_sys_object('dbxForm');
    $oForm->init('form-wizzard-new');
    $oForm->_msg_info ='Geben Sie bitte den Namen des neuen Moduls ein.';
    $oForm->_msg_error='Modul Name ungültig oder schon vorhanden';

    // create all Form Fields
    $oForm->add_fld('modul','text-label','','parameter|min=1','Modul Name','Bitte geben Sie den Namen des Modules an'); // #+

    // - - - - - - - -
    dbx_del_SessionVal('*','msg_new_modul');
    // ---

    if($oForm->submit()) {
      if(!$oForm->errors()) {      // submit && no errors
         //1. check is exist
         $modul_name=$oForm->_post['modul'];
         $modul_path=dbx_get_base_dir().'dbx/modules/'.$modul_name;
         $err=is_dir($modul_path);
         if ($err) {
            $oForm->add_fld_error('modul',"Ein Modul mit dem Namen ($modul_name) existiert schon.");
         }
         // 2. Module erstellen wenn kein err
         if (!$err) {
           mkdir($modul_path,0777,TRUE);
           $ok=is_dir($modul_path); // check if dir is createted
           if ($ok) {
             $this->create_modul($modul_name,$modul_path);
             $oForm->_msg_success="Neues Modul ($modul_name) erstellt.";
           } else {
             $oForm->add_fld_error('modul',"Das Modul ($modul_name) konnte nicht erstellt werden.");
           }
         }
      }
      if(!$oForm->errors()) {
         $oForm->_msg_error="<h4>Modul Name ungültig oder schon vorhanden</h4>";
      }
    } // submit()

    $content=$oForm->run();
    return $content;
  }



   public function run() {
      $content=$this->new_modul();
      return $content;
   }

}




?>