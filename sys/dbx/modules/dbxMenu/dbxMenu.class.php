<?php
namespace dbx\dbxMenu;

Class dbxMenu {
  Public $oTPL;

  public function __construct() {
     $this->oTPL=dbx_get_sys_object('dbxTPL');
  }


   private function get_menu_tpl($menu='undeff') {
     $access=1; $content='';
     if (strpos($menu, '_admin')) $access=dbx_check_access('admin');
     if (strpos($menu, '-admin')) $access=dbx_check_access('admin');
         
     if ($access) {
       $data=array();
       $self=dbx_get_SysVar('dbx_self_url','?','*');
       $self.='&';
       $self=str_replace('?&','?',$self);
       $data['self']=$self;
       $i = dbx_get_next_i();
       $content=$this->oTPL->get_tpl('dbxMenu',$menu,$data,'htm',$i);
     }

     return $content;
   }

   public function run() {
      $action=dbx_get_ModulVar('dbx_action' ,'undeff');
      $menu  =dbx_get_ModulVar('dbx_menu_id','undeff');
      $uid   =dbx_get_CurrentUser();
      $mid   =dbx_get_SysVar('dbx_activ_modul_id',-1);
      $content="Menu ($menu) not found"; // default output !

      //dbx_debug("#Modul Menu# action=($action) Menu=($menu) Modul-id=($mid)",$_SESSION['dbx']['tmp']);

      switch ($action) {
        case 'load':
          $content=$this->get_menu_tpl($menu);
        break;

        case 'content':
          $obj=dbx_get_Modul_include_object('dbxContent_menu');
          $content=$obj->run();
        break;
      }


      if ($uid) {
         $LoginOut ='LogOut'; $login_out = 'logout';
      } else {
         $LoginOut ='LogIn' ; $login_out = 'login';
      }

      $AdminMenu='';
      $design=dbx_get_SysVar('dbx_activ_design');
      $page  =dbx_get_SysVar('dbx_activ_page');
      $lng   =dbx_get_SysVar('dbx_activ_lng');

      if (dbx_check_access('admin'))         $AdminMenu="Admin";
      $content=str_replace('{dbx:Admin}'    ,$AdminMenu,$content);
      $content=str_replace('{dbx:LogInOut}' ,$LoginOut ,$content);
      $content=str_replace('{dbx:login_out}',$login_out,$content);
      $content=str_replace('{dbx:design}'   ,$design   ,$content);
      $content=str_replace('{dbx:page}'     ,$page     ,$content);
      $content=str_replace('{dbx:lng}'      ,$lng      ,$content);


      return $content;
   }

}

?>
