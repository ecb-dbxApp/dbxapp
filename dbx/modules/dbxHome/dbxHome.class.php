<?php
namespace dbx\dbxHome;

Class dbxHome {



   public function run() {
      $content='';
      $page = (string) dbx()->get_system_var('dbx_page', 'home');

      if ($page === 'intro') {
         dbx()->load_content_cache_classes();
         \dbx\dbxContent\dbxContentRenderer::resetSeoMeta();
         dbx()->set_system_var('dbx_title', 'Willkommen');
         $oTPL = dbx()->get_system_obj('dbxTPL');
         return $oTPL->get_tpl('dbxHome', 'intro', array(
            'continue_url' => dbx()->get_base_url() . '?dbx_modul=dbxHome&dbx_page=home',
         ));
      }

      dbx()->load_content_cache_classes();
      $cid = \dbx\dbxContent\dbxContentHome::resolveCid();

      if ($cid <= 0) {
         \dbx\dbxContent\dbxContentRenderer::resetSeoMeta();
      }

      dbx()->set_system_var('dbx_title','dbxApp Home');

      if ($cid > 0) {
         if (\dbx\dbxContent\dbxContentLng::isCmsPermalinkMode()) {
            $root = dbx()->get_cfg('dbxContent', 'root');
            if ($root === 'undef' || $root === '') {
               $root = 0;
            }

            $content='[modul=dbxContent]dbx_run1=cms&dbx_run2=show&root=' . (int)$root . '&cid=' . $cid . '[/modul]';
         } else {
            $content='[modul=dbxContent]dbx_run1=show&cid=' . $cid . '[/modul]';
         }
      }

      //dbx()->debug("dbxHome #SESSION#",$_SESSION);
   
      return $content;
   }
}

?>
