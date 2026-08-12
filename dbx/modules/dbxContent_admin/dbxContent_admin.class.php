<?php
namespace dbx\dbxContent_admin;


class dbxContent_admin {

   private function unavailable(): string {
      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-warning', array(
         'msg' => 'Der Content-Admin-Bereich konnte nicht geladen werden.',
      ));
   }

   private function handle_seo(): string {
      $obj=dbx()->get_include_obj('dbxContent_seo');
      return is_object($obj) ? $obj->run('seo') : $this->unavailable();
   }

   private function handle_seo_page(): string {
      $obj=dbx()->get_include_obj('dbxContent_seo');
      return is_object($obj) ? $obj->run('seo_page') : $this->unavailable();
   }

   private function handle_seo_save(): string {
      $obj=dbx()->get_include_obj('dbxContent_seo');
      return is_object($obj) ? $obj->run('seo_save') : $this->unavailable();
   }

   private function handle_media_view(): string {
      $obj=dbx()->get_include_obj('dbxContent_sections');
      return is_object($obj) ? $obj->run('media_view') : $this->unavailable();
   }

   private function handle_edit_content(): string {
      $obj=dbx()->get_include_obj('dbxContent_cms');
      return is_object($obj) ? $obj->run('cms') : $this->unavailable();
   }

   private function handle_sysdata(): string {
      $obj=dbx()->get_include_obj('dbxContent_sysdata');
      return is_object($obj) ? $obj->run() : $this->unavailable();
   }

   private function handle_images(): string {
      $obj=dbx()->get_include_obj('dbxContent_images');
      return is_object($obj) ? $obj->run() : $this->unavailable();
   }

   private function handle_ibrowser(): string {
      $obj=dbx()->get_include_obj('dbxContent_ibrowser');
      return is_object($obj) ? $obj->run() : $this->unavailable();
   }

   private function handle_iupload(): string {
      $obj=dbx()->get_include_obj('dbxContent_images');
      return is_object($obj) ? $obj->run() : $this->unavailable();
   }

   private function handle_flat(): string {
      $obj=dbx()->get_include_obj('dbxContent_list');
      return is_object($obj) ? $obj->run('flat') : $this->unavailable();
   }

   private function handle_tree(): string {
      $obj=dbx()->get_include_obj('dbxContent_list');
      return is_object($obj) ? $obj->run('tree') : $this->unavailable();
   }

   private function handle_list_files(): string {
      $obj=dbx()->get_include_obj('dbxContent_list');
      return is_object($obj) ? $obj->run('files') : $this->unavailable();
   }

   private function handle_list_folder(): string {
      $obj=dbx()->get_include_obj('dbxContent_folder');
      return is_object($obj) ? $obj->run('list_folder') : $this->unavailable();
   }

   private function handle_list_folder_files(): string {
      $obj=dbx()->get_include_obj('dbxContent_list');
      return is_object($obj) ? $obj->run('folder_files') : $this->unavailable();
   }

   private function handle_folder_edit(): string {
      $obj=dbx()->get_include_obj('dbxContent_folder');
      return is_object($obj) ? $obj->run('edit') : $this->unavailable();
   }

  public function run($action='') {
     $uid   =dbx()->user();
     $mid   =dbx()->get_system_var('dbx_modul_id');
     $modul =dbx()->get_system_var('dbx_modul');
     //dbx()->set_system_var('dbx_page'  ,'content');
     $content="undef";
     if (!$action) $action=dbx()->get_modul_var('dbx_run1','content');
     dbx()->set_modul_var('dbx_run1',$action);

     // Alle modernen CMS-Endpunkte besitzen genau einen kanonischen
     // Aktionsvertrag in dbxContent_cms. Der Modulrouter muss diese Liste
     // deshalb nicht ein zweites Mal pflegen.
     if ($action === 'cms' || str_starts_with((string)$action, 'cms_')) {
        $obj=dbx()->get_include_obj('dbxContent_cms');
        if (is_object($obj) && method_exists($obj, 'supports_action') && $obj->supports_action((string)$action)) {
           return $obj->run((string)$action);
        }
     }

     // Einfache 1:1-Aktionen laufen ueber denselben Aktionsvertrag wie
     // dbxContent_cms/dbxSchema. Nur die verbliebenen Mehrschritt-Faelle
     // (content, tree_add/del_content, content_show) bleiben unten explizit.
     $definition = dbx()->get_system_obj('dbxActionManifest')
        ->action('dbxContent_admin', (string)$action, 'content-admin-actions');
     if (is_array($definition)) {
        $handler = (string)$definition['handler'];
        if (!method_exists($this, $handler)) {
           throw new \LogicException('Content-Admin-Handler fehlt: ' . $action);
        }
        return $this->{$handler}();
     }

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
            $obj=dbx()->get_include_obj('dbxConfig_dbxContent', 'dbxAdmin');
            $content=$obj->run('?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=config');
          break;

          default:
            $oTPL=dbx()->get_system_obj('dbxTPL');
            $msg['msg']="Modul=($modul) Action=($action) Work=($work) is undef.";
            $content=$oTPL->get_tpl('dbx','alert-warning',$msg);
        }
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
