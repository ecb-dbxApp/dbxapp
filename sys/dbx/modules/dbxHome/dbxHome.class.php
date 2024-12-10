<?php
namespace dbx\dbxHome;

Class dbxHome {

   public function server() {
      $content='[-modul=LabServer]dbx_action=run[/modul]';
      return $content;
   }


   public function run() {
      $content='';
      dbx_set_Remember('dbx_load_pat',1,'myOrderLDT');
      $oTPL=dbx_get_sys_object('dbxTPL');
      dbx_set_SysVar('dbx_title','LabConn');
      dbx_set_SysVar('dbx_page' ,'default');
      $uid   =dbx_get_CurrentUser();
      $praxis=dbx_get_cfg('myOrderLDT','praxis');
      $login =dbx_get_cfg('myOrderLDT','autologin');
      $system=dbx_get_cfg('myOrderLDT','system');
      $reff  =dbx_get_ModulVar('dbx_reff');

      //$login=-2; // admin

      //dbx_debug("Session:",$_SESSION) ;

      if ($uid==0) {
         if ($uid != $login && $reff != 'logout' && $reff != 'login') {
            if ($login != 0) dbx_login($login);
         }
      } 

      if ($system == 'labserv')  {
         dbx_set_SysVar('dbx_page','server'); 
         $content=$this->server();
         return $content; 

      }



      $uid=dbx_get_CurrentUser();
      dbx_set_SysVar('dbx_page','home'); 
    
      //$content="User ($uid) Login=($login) Reff=($reff) <br>";
      $uid   =dbx_get_CurrentUser();
      if ($uid == 0) {
         $content='[modul=dbxLogin]dbx_action=login[/modul]';
      }  
      if ($uid != 0) { 
         $data['col:1']='[modul=myOrderLDT]dbx_action=summary&dbx_work=run[/modul]';
         $data['col:2']='[modul=myBefund]dbx_action=summary&dbx_work=run[/modul]'; 
         //$content.='[modul=myOrderLDT]dbx_action=import_pat&dbx_work=run&dbx_do=reload[/modul]';
         $content.=$oTPL->get_tpl('modul','2-cols',$data);

         //$content.=$this->test();
      }
   

      return $content;
   }
}

?>