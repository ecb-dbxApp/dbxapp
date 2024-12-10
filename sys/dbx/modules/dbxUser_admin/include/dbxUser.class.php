<?php
namespace dbx\dbxUser_admin;



Class dbxUser {

   public function run() {
 
    $modul =dbx_get_SysVar('dbx_modul');
    $action=dbx_get_ModulVar('dbx_action');
    $work  =dbx_get_ModulVar('dbx_work'); 

    switch ($work) {

        case 'list_user':
            $obj=dbx_get_Modul_include_object('dbxUser_list');
            $content=$obj->run();
        break;


        case 'new_user';
        $obj=dbx_get_Modul_include_object('dbxUser_view');
        $content=$obj->run();
        break;

        case 'edit_user';
        $obj=dbx_get_Modul_include_object('dbxUser_view');
        $content=$obj->run();
        break;


        case 'edit_profil';
        $obj=dbx_get_Modul_include_object('dbxUser_profil');
        $content=$obj->run($action);
        break;

        case 'avatar_upload';
        case 'edit_avatar';
        $obj=dbx_get_Modul_include_object('dbxUser_avatar');
        $content=$obj->run();
        break;

        case 'list_groups';
        $obj=dbx_get_Modul_include_object('dbxUser_groups');
        $content=$obj->run('list_groups');
        break;

        case 'new_group';
        $obj=dbx_get_Modul_include_object('dbxUser_groups');
        $content=$obj->run('new_group');
        break;

        case 'edit_group';
        $obj=dbx_get_Modul_include_object('dbxUser_groups');
        $content=$obj->run($action);
        break;


        case 'import_user';
        $obj=dbx_get_Modul_include_object('dbxUser_import');
        $content=$obj->run();
        break;

        case 'verify':
          $content="Verify not ready";
        break;


        case 'sessions':
          $obj=dbx_get_Modul_include_object('dbxUser_sessions');
          $content=$obj->run();
          break;

        default:
          $oTPL=dbx_get_sys_object('dbxTPL');
          $msg['msg']="Modul=($modul) Action=($action) Work=($work) is undef.";
          $content=$oTPL->get_tpl('dbx','alert-warning',$msg);


    }

    return $content;
   }

}
?>