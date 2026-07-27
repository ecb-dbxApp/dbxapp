<?php
namespace dbx\dbxUser_admin;



Class dbxUser {

   public function run() {
 
    $modul =dbx()->get_system_var('dbx_modul');
    $action=dbx()->get_modul_var('dbx_run1');
    $work  =dbx()->get_modul_var('dbx_run2'); 

    switch ($work) {

        case 'list_user':
            $obj=dbx()->get_include_obj('user_list');
            $content=$obj->run();
        break;

        case 'user_grid_read':
        case 'user_grid_save':
        case 'user_grid_insert':
        case 'user_grid_delete':
            $obj=dbx()->get_include_obj('user_list');
            $content=$obj->run();
        break;


        case 'new_user';
        $obj=dbx()->get_include_obj('dbxUser_view');
        $content=$obj->run();
        break;

        case 'edit_user';
        $obj=dbx()->get_include_obj('dbxUser_view');
        $content=$obj->run();
        break;


        case 'edit_profil';
        $obj=dbx()->get_include_obj('dbxUser_profil');
        $content=$obj->run($action);
        break;

        case 'avatar_upload';
        case 'edit_avatar';
        $obj=dbx()->get_include_obj('dbxUser_avatar');
        $content=$obj->run();
        break;

        case 'list_groups';
        $obj=dbx()->get_include_obj('dbxUser_groups');
        $content=$obj->run('list_groups');
        break;

        case 'group_grid_read':
        case 'group_grid_save':
        case 'group_grid_insert':
        case 'group_grid_delete':
        $obj=dbx()->get_include_obj('dbxUser_groups');
        $content=$obj->run($work);
        break;

        case 'new_group';
        $obj=dbx()->get_include_obj('dbxUser_groups');
        $content=$obj->run('new_group');
        break;

        case 'edit_group';
        $obj=dbx()->get_include_obj('dbxUser_groups');
        $content=$obj->run('edit_group');
        break;

        case 'delete_group';
        $obj=dbx()->get_include_obj('dbxUser_groups');
        $content=$obj->run('delete_group');
        break;


        case 'import_user';
        $obj=dbx()->get_include_obj('dbxUser_import');
        $content=$obj->run();
        break;

        case 'verify':
          $content="Verify not ready";
        break;


        case 'sessions':
          $obj=dbx()->get_include_obj('dbxUser_sessions');
          $content=$obj->run();
          break;

        default:
          $oTPL=dbx()->get_system_obj('dbxTPL');
          $msg['msg']="Modul=($modul) Action=($action) Work=($work) is undef.";
          $content=$oTPL->get_tpl('dbx','alert-warning',$msg);


    }

    return $content;
   }

}
?>
