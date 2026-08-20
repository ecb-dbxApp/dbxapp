<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap.php';

class dbxContent_content {

   Public $o_tpl;

   public function __construct() {
     $this->o_tpl = dbx()->get_system_obj('dbxTPL');
   }












  private function admin_editor_bar_html($cid = 0) {
      if (!dbx()->has_group('admin')) {
         return '';
      }

      $url = dbxContentRuntime::app_url() . '?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=' . (int)$cid;
      return $this->o_tpl->get_tpl('dbxContent|content-view-bar-admin-win', array(
         'admin_url' => dbx()->esc($url),
      ));
  }

  private function wrap_content_page($page_content, $cid) {
      $i = dbx()->next_id();
      $title = trim((string)dbx()->get_system_var('dbx_title', ''));
      if ($title === '') {
         $db = dbx()->get_system_obj('dbxDB');
         $row = $db->select1(dbxContentLng::dd_content(), (int)$cid, 'title', 0);
         if (is_array($row)) {
            $title = trim((string)($row['title'] ?? ''));
         }
      }
      if ($title === '') {
         $title = 'Seite #' . (int)$cid;
      }

      return $this->o_tpl->get_tpl('dbxContent|content-page-frontend', array(
         'frame_id'      => 'dbx_content_page_' . $i,
         'cid'           => (string)(int)$cid,
         'frontend_head' => $this->o_tpl->get_tpl('dbxContent|content-frontend-head', array(
            'bar_title'               => $title,
            'bar_title_pre'           => $this->admin_editor_bar_html((int)$cid),
            'bar_title_heading_attrs' => ' data-cms-page-title',
         )),
         'page_content'  => $page_content,
      ));
  }



  public function render_page(int $cid, array $options = array()): string {
    $cid = (int) $cid;
    if ($cid <= 0) {
      return '';
    }

    require_once __DIR__ . '/dbxContent_bootstrap.php';
    $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
    $lng = dbxContentPageCache::current_lng();
    $render_options = array(
      'skip_hits' => !empty($options['skip_hits']),
      'admin_help' => !empty($options['admin_help']),
    );
    $forced_template = trim((string)($options['template'] ?? ''));
    if ($forced_template !== '') {
      $render_options['template'] = $forced_template;
    }
    $static = $renderer->render_static($cid, $render_options);
    if (array_key_exists('wrap', $options) && !$options['wrap']) {
      return $static;
    }
    return $this->wrap_content_page($static, $cid);
  }

  public function run() {
    $cid = (int) dbx()->get_modul_var('dbx_cid', 0, 'int');
    if ($cid <= 0) {
      $cid = (int) dbx()->get_modul_var('cid', 0, 'int');
    }
    if ($cid <= 0) {
      $cid = (int) dbx()->get_system_var('dbx_cid', 0, 'int');
    }
    if ($cid <= 0) {
      require_once __DIR__ . '/dbxContent_bootstrap.php';
      $cid = dbxContentHome::resolve_cid();
    }
    if (!$cid > 0) {
      $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
      return $renderer->render_not_found();
    }

    return $this->render_page($cid);
  } // run()


} // class

?>
