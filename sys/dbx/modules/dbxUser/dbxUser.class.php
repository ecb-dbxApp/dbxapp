<?php
namespace dbx\dbxUser;

Class dbxUser {

   Public $oTPL;
  
   public function __construct() {
     $this->oTPL = dbx_get_sys_object('dbxTPL');
   }

   public function run($action='') {
      $action=dbx_get_ModulVar('dbx_action');
      $content="dbXuser ($action) not found";
      switch ($action) {


        case 'profil':
            $obj=dbx_get_Modul_include_object('dbxUser_profil');
            $content=$obj->run();
            break;


        case 'avatar_upload';
             //dbx_set_ModulVar($mid,'dbx_action_user','avatar_upload');
             $obj=dbx_get_Modul_include_object('dbxUser_avatar');
             $content=$obj->run();
             break;


         case 'profil_view';
              $content=$tis-oTPL->get_tpl('dbxUser','view-profil');
              break;


        case 'verify':
             break;


        default:
          //$content.="<span class='red action_msg'>Modul $modul Action $action is undef.</span>";

      }
      return $content;
   }
}

?>