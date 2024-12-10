<?php
namespace dbx\dbxContent_admin;


class dbxContent_admin {


  public function run($action='') {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');
     //dbx_set_SysVar('dbx_design','admin');
     dbx_set_SysVar('dbx_page'  ,'content');
     $content="UnDeff";
     if (!$action) $action=dbx_get_ModulVar('dbx_action','content');
     dbx_set_ModulVar('dbx_action',$action);

//dbx_debug("dbxContent_admin=($action)");

     switch ($action) {

      case 'content':
        $obj=dbx_get_Modul_include_object('dbxContent_content');
        $content=$obj->run();
      break;




       case 'edit_content':
         $obj=dbx_get_Modul_include_object('dbxContent_view');
         $content=$obj->run();
       break;

       case 'content':
        $obj=dbx_get_Modul_include_object('dbxContent_content');
        $content=$obj->run();
      break;       

       case 'sysdata':
         $obj=dbx_get_Modul_include_object('dbxContent_sysdata');
         $content=$obj->run();
       break;

       case 'images':
         $obj=dbx_get_Modul_include_object('dbxContent_images');
         $content=$obj->run();
       break;

 


       case 'ibrowser':
         $obj=dbx_get_Modul_include_object('dbxContent_ibrowser');
         $content=$obj->run();
       break;

       case 'iupload':
           $obj=dbx_get_Modul_include_object('dbxContent_images');
           $content=$obj->run();
       break;



       case 'flat':
           $obj=dbx_get_Modul_include_object('dbxContent_list');
           $content=$obj->run('flat');
       break;



       case 'tree_add_content':
           $obj=dbx_get_Modul_include_object('dbxContent_list');
           $obj->add_content();
           $content=$obj->run('tree');
       break;

       case 'tree_del_content':
           $obj=dbx_get_Modul_include_object('dbxContent_list');
           $obj->del_content();
           $content=$obj->run('tree');
       break;


       case 'tree':
           $obj=dbx_get_Modul_include_object('dbxContent_list');
           $content=$obj->run('tree');
       break;

       case 'list_files':
           $obj=dbx_get_Modul_include_object('dbxContent_list');
           $content=$obj->run('files');
       break;

       case 'list_folder':
           $obj=dbx_get_Modul_include_object('dbxContent_folder');
           $content=$obj->run('flat');
       break;

       case 'list_folder_files':
           $obj=dbx_get_Modul_include_object('dbxContent_list');
           $content=$obj->run('folder_files');
       break;


       case 'folder_edit':
           $obj=dbx_get_Modul_include_object('dbxContent_folder');
           $content=$obj->run('edit');
       break;

       case 'content_show':
           $cid=dbx_get_ModulVar('rid',0,'int');
           $content='[modul=dbxContent]dbx_action=show&cid='.$cid.'[/modul]';
       break;

       default:
         $content.="<div class='warning action_msg'>Modul=($modul) Action=($action) is undef.</div>";
     } // switch
     return $content;
  } // run()

} // class

?>