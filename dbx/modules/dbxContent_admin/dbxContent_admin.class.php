<?php
namespace dbx\dbxContent_admin;


class dbxContent_admin {


  public function run($action='') {
     $uid   =dbx()->user();
     $mid   =dbx()->get_system_var('dbx_modul_id');
     $modul =dbx()->get_system_var('dbx_modul');
     //dbx()->set_system_var('dbx_page'  ,'content');
     $content="undef";
     if (!$action) $action=dbx()->get_modul_var('dbx_run1','content');
     dbx()->set_modul_var('dbx_run1',$action);

//dbx_debug("dbxContent_admin=($action)");

     switch ($action) {

      case 'content':
        $work=dbx()->get_modul_var('dbx_run2','edit');
        switch ($work) {
          case '':
          case 'edit':
            $obj=dbx()->get_include_obj('dbxContent_cms');
            $content=$obj->run('cms');
          break;

          case 'list_content':
          case 'list_folder':
          case 'list_media':
          case 'templates':
          case 'template_new':
          case 'content_grid_read':
          case 'content_grid_save':
          case 'content_grid_delete':
          case 'content_grid_sync':
            $obj=dbx()->get_include_obj('dbxContent_sections');
            $content=$obj->run($work);
          break;

          case 'config':
            require_once dirname(__DIR__) . '/dbxAdmin/include/dbxConfig_dbxContent.class.php';
            $obj=new \dbx\dbxAdmin\dbxConfig_dbxContent();
            $content=$obj->run('?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=config');
          break;

          default:
            $oTPL=dbx()->get_system_obj('dbxTPL');
            $msg['msg']="Modul=($modul) Action=($action) Work=($work) is undef.";
            $content=$oTPL->get_tpl('dbx','alert-warning',$msg);
        }
      break;

      case 'cms':
        $obj=dbx()->get_include_obj('dbxContent_cms');
        $content=$obj->run('cms');
      break;

      case 'seo':
      case 'seo_page':
      case 'seo_save':
        $obj=dbx()->get_include_obj('dbxContent_seo');
        $content=is_object($obj) ? $obj->run($action) : '';
      break;

      case 'media_view':
        $obj=dbx()->get_include_obj('dbxContent_sections');
        $content=$obj->run('media_view');
      break;

      case 'cms_tree':
      case 'cms_page':
      case 'cms_save':
      case 'cms_new_page':
      case 'cms_duplicate_page':
      case 'cms_new_folder':
      case 'cms_save_folder':
      case 'cms_delete_folder':
      case 'cms_delete_page':
      case 'cms_move_node':
      case 'cms_media':
      case 'cms_upload':
      case 'cms_external_video':
      case 'cms_media_folders':
      case 'cms_media_folder_create':
      case 'cms_media_folder_delete':
      case 'cms_media_folder_rename':
      case 'cms_media_move':
      case 'cms_media_unused':
      case 'cms_remove_media':
      case 'cms_delete_media':
      case 'cms_edit_media':
      case 'cms_set_media_slot':
      case 'cms_assign_media':
      case 'cms_sort_media':
      case 'cms_media_process':
      case 'cms_mod_catalog':
      case 'cms_mod_modules':
      case 'cms_lng_coverage':
      case 'cms_lng_preview':
      case 'cms_lng_provision':
      case 'cms_lng_provision_tree':
      case 'cms_lng_reset_sync':
      case 'cms_lng_delete_preview':
        $obj=dbx()->get_include_obj('dbxContent_cms');
        $content=$obj->run($action);
      break;

       case 'edit_content':
         $obj=dbx()->get_include_obj('dbxContent_cms');
         $content=$obj->run('cms');
       break;

       case 'sysdata':
         $obj=dbx()->get_include_obj('dbxContent_sysdata');
         $content=$obj->run();
       break;

       case 'images':
         $obj=dbx()->get_include_obj('dbxContent_images');
         $content=$obj->run();
       break;

 


       case 'ibrowser':
         $obj=dbx()->get_include_obj('dbxContent_ibrowser');
         $content=$obj->run();
       break;

       case 'iupload':
           $obj=dbx()->get_include_obj('dbxContent_images');
           $content=$obj->run();
       break;



       case 'flat':
           $obj=dbx()->get_include_obj('dbxContent_list');
           $content=$obj->run('flat');
       break;



       case 'tree_add_content':
           $obj=dbx()->get_include_obj('dbxContent_list');
           $obj->add_content();
           $content=$obj->run('tree');
       break;

       case 'tree_del_content':
           $obj=dbx()->get_include_obj('dbxContent_list');
           $obj->del_content();
           $content=$obj->run('tree');
       break;


       case 'tree':
           $obj=dbx()->get_include_obj('dbxContent_list');
           $content=$obj->run('tree');
       break;

       case 'list_files':
           $obj=dbx()->get_include_obj('dbxContent_list');
           $content=$obj->run('files');
       break;

       case 'list_folder':
           $obj=dbx()->get_include_obj('dbxContent_folder');
           $content=$obj->run('flat');
       break;

       case 'list_folder_files':
           $obj=dbx()->get_include_obj('dbxContent_list');
           $content=$obj->run('folder_files');
       break;


       case 'folder_edit':
           $obj=dbx()->get_include_obj('dbxContent_folder');
           $content=$obj->run('edit');
       break;

       case 'content_show':
           $cid=dbx()->get_modul_var('rid',0,'int');
           $content='[modul=dbxContent]dbx_run1=show&cid='.$cid.'[/modul]';
       break;

       default:
         $content.="<div class='warning action_msg'>Modul=($modul) Action=($action) is undef.</div>";
     } // switch
     return $content;
  } // run()

} // class

?>
