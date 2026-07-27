<?php
namespace dbx\dbxUser;

Class dbxUser {

   Public $oTPL;
  
   public function __construct() {
     $this->oTPL = dbx()->get_system_obj('dbxTPL');
   }

   public function run($action='') {
      $action=dbx()->get_modul_var('dbx_run1');
      $work=dbx()->get_modul_var('dbx_run2');
      $content="dbXuser ($action) not found";
      switch ($action) {

        case 'user':
            switch ($work) {
              case 'edit_profil':
              case 'profil':
              case '':
                  $obj=dbx()->get_include_obj('dbxUser_profil');
                  $content=$obj->run();
                  break;

              case 'edit_avatar':
              case 'avatar_upload':
              case 'avatar':
                  $obj=dbx()->get_include_obj('dbxUser_avatar');
                  $content=$obj->run();
                  break;

              default:
                  $content="dbXuser ($action/$work) not found";
            }
            break;


        case 'profil':
            $obj=dbx()->get_include_obj('dbxUser_profil');
            $content=$obj->run();
            break;


        case 'avatar_upload';
             //dbx()->set_modul_var($mid,'dbx_action_user','avatar_upload');
             $obj=dbx()->get_include_obj('dbxUser_avatar');
             $content=$obj->run();
             break;


         case 'profil_view';
              $content=$this->oTPL->get_tpl('dbxUser|view-profil');
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
