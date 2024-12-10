<?php
namespace dbx\dbxAdmin;

Class dbxMenu {

   Public $oTPL;
   
   public function __construct() {
     $this->oTPL = dbx_get_sys_object('dbxTPL');
   }

   function run($action='') {
      $content='';
      $uid   = dbx_get_CurrentUser();

  
      $menu  = dbx_get_ModulVar('dbx_menu_id','Menu-Admin');
      if ($menu) {
        $content=$this->oTPL->get_tpl('dbxAdmin',$menu);

        if ($uid) {
           $LoginOut ='LogOut'; $login_out='logout';
        } else {
           $LoginOut ='LogIn'; $login_out='login';
        }

        //$content=dbx_replace('dbx:LogInOut' ,$LoginOut ,$content); 
        //$content=dbx_replace('dbx:login_out',$login_out,$content);

      }
      return $content;
   }
}

?>