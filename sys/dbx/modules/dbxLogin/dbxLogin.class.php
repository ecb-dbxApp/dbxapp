<?php
namespace dbx\dbxLogin;

Class dbxLogin {


   public function run() {
      $uid   =dbx_get_CurrentUser();
      $modul =dbx_get_SysVar('dbx_modul');
      $action=dbx_get_SysVar('dbx_action');
      $activ_modul = dbx_get_SysVar('dbx_activ_modul');
      $activ_action= dbx_get_SysVar('dbx_activ_action');
 
      dbx_set_SysVar('dbx_master_action',$action);  // override 'run'
      dbx_set_SysVar('dbx_page','login');  // 

    
      $content="LogOut ($action) UID=($uid)";


      //dbx_debug("#dbxLogIn A=($action) U=($uid)  Modul=($modul) Activ-Modul=($activ_modul) Activ-Action=($activ_action)"   );

      switch ($action) {

          case 'run':  
            $base=dbx_get_base_url();
            //dbx_debug("LOG-Content-redir-base=($base)");
            //dbx_redirect('?dbx_modul=myArzt&dbx_action=arzt&dbx_work=list');
            if ($uid != -1) dbx_logout($uid); 
             
            if ($uid != -1) dbx_redirect('?dbx_modul=dbxLogin&dbx_action=logout');
            if ($uid == -1) dbx_redirect('?dbx_modul=dbxLogin&dbx_action=login');
          
          break;          

          case 'login':
            if ($activ_modul =='dbxLogin') {
              
              dbx_set_SysVar('dbx_page','login');
              $obj=dbx_get_Modul_include_object('dbxUser_login');
              $content=$obj->run();
            }
          break;
  
          case 'logout':
            if ($activ_modul =='dbxLogin') {
              //$content=dbx_redirect('?dbx_modul=dbxHome&dbx_page=home&dbx_design=default',1);
              $obj=dbx_get_Modul_include_object('dbxUser_login');
              $content=$obj->run();
            }
          break;



          case 'verify':
            // #todo
          break;


          default:
            $content="<span class='red action_msg'>Modul $modul Action $action is undef.</span>";

      }

      //dbx_debug("LOGIN CONTENT=",$content);

      return $content;
   }
}

