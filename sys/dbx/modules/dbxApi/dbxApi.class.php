<?php
namespace dbx\dbxApi;

class dbxApi {

  private function json_response($json,$session_save=1) {
    if ($session_save) {
      $oSession=dbx_get_sys_object('dbxSession');
      $oSession->save_session(0);
    }
    echo $json;
    exit;
  }

  private function html_response($htlm,$session_save=1) {
    if ($session_save) {
      $oSession=dbx_get_sys_object('dbxSession');
      $oSession->save_session(0);
    }
    echo $htlm;
    exit;
  }

  private function load_user($uid) {
    $db=dbx_get_sys_object('dbxDB');
    $rec=$db->select1('dbx_user',"userid = '$uid'");
    if (is_array($rec)) {
      dbx_login($uid); // Session
    }
  }


  public function run() {
     $access=0;
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');

     $action=dbx_get_ModulVar('dbx_action','web','parameter');
     $api   =dbx_get_ModulVar('dbx_api',''      ,'parameter');
     $key   =dbx_get_ModulVar('dbx_apikey',''   ,'password');

     dbx_set_SysVar('dbx_activ_page'  ,'_api');
     dbx_set_SysVar('dbx_activ_design','_admin');

     if ($api && $key) {
       $db=dbx_get_sys_object('dbxDB');
       $rec=$db->select1('dbx_api',"api = '$api'");
       if (is_array($rec)) {
           $apikey=$rec['apikey'];
           $runas =$rec['runas'];
           if (!$runas) $runas=-3;

           if ($apikey == $key)  $access=1;

           dbx_set_ModulVar('mod'  ,$rec['modul']);
           dbx_set_ModulVar('act'  ,$rec['action']);
           dbx_set_ModulVar('work' ,$rec['work']);
           dbx_set_ModulVar('runas',$rec['runas']);
           if ($uid != $runas) dbx_login($runas); // Session
       }
     }

    $uid   =dbx_get_CurrentUser();
    $groups=dbx_get_CurrentUser('roles');

    //$content="Api=($api) Key=($apikey) User=($uid) Groups($groups) Access=($access)";
    //return $content;


     if ($access) {
       //dbx_set_SysVar('dbx_api_access',1);


       switch ($action) {

         case 'web':
             $obj=dbx_get_Modul_include_object('dbxApi_call');
             $content=$obj->run('web');
             break;



         case 'html':
             $obj=dbx_get_Modul_include_object('dbxApi_call');
             $html=$obj->run('html');
             $this->html_response($html);
             break;

         case 'json':
             $obj=dbx_get_Modul_include_object('dbxApi_call');
             $json=$obj->run('json');
             $this->json_response($json);
             break;

         default:
           $oTPL=dbx_get_sys_object('dbxTPL');
           $msg['msg']="Modul=($modul) Api=($api) Action=($action) is undef";
           $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

       } // sqitch()
     } else  {  // access
       $oTPL=dbx_get_sys_object('dbxTPL');
       $msg['msg']="Modul=($modul) mode=($action) Api=($api) Wrong Api Call or Key.";
       $content=$oTPL->get_tpl('dbx','alert-danger',$msg);
     }



     return $content;
   } // run()


} // class

?>
