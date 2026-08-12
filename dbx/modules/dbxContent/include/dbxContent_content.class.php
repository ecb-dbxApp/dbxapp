<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap.php';

class dbxContent_content {

   Public $oTPL;

   public function __construct() {
     $this->oTPL = dbx()->get_system_obj('dbxTPL');
   }












  private function adminEditorBarHtml($cid = 0) {
      if (!dbx()->can('admin')) {
         return '';
      }

      $url = $this->appUrl() . '?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=' . (int)$cid;
      return $this->oTPL->get_tpl('dbxContent|content-view-bar-admin-win', array(
         'admin_url' => dbx()->esc($url),
      ));
  }

  private function appUrl() {
      $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
      if ($script === '') return '';
      $dir = str_replace('\\', '/', dirname($script));
      if ($dir === '.' || $dir === '/' || $dir === '\\') return '/';
      return rtrim($dir, '/') . '/';
  }

  private function wrapContentPage($pageContent, $cid) {
      $i = dbx()->next_id();
      $title = trim((string)dbx()->get_system_var('dbx_title', ''));
      if ($title === '') {
         $db = dbx()->get_system_obj('dbxDB');
         $row = $db->select1(dbxContentLng::ddContent(), (int)$cid, 'title', 0);
         if (is_array($row)) {
            $title = trim((string)($row['title'] ?? ''));
         }
      }
      if ($title === '') {
         $title = 'Seite #' . (int)$cid;
      }

      return $this->oTPL->get_tpl('dbxContent|content-page-frontend', array(
         'frame_id'      => 'dbx_content_page_' . $i,
         'cid'           => (string)(int)$cid,
         'frontend_head' => $this->oTPL->get_tpl('dbxContent|content-frontend-head', array(
            'bar_title'               => $title,
            'bar_title_pre'           => $this->adminEditorBarHtml((int)$cid),
            'bar_title_heading_attrs' => ' data-cms-page-title',
         )),
         'page_content'  => $pageContent,
      ));
  }



  public function renderPage(int $cid, array $options = array()): string {
    $cid = (int) $cid;
    if ($cid <= 0) {
      return '';
    }

    require_once __DIR__ . '/dbxContent_bootstrap.php';
    $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
    $lng = dbxContentPageCache::currentLng();
    $renderOptions = array(
      'skip_hits' => !empty($options['skip_hits']),
      'admin_help' => !empty($options['admin_help']),
    );
    $forcedTemplate = trim((string)($options['template'] ?? ''));
    if ($forcedTemplate !== '') {
      $renderOptions['template'] = $forcedTemplate;
    }
    $static = $renderer->renderStatic($cid, $renderOptions);
    if (array_key_exists('wrap', $options) && !$options['wrap']) {
      return $static;
    }
    return $this->wrapContentPage($static, $cid);
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
      $cid = dbxContentHome::resolveCid();
    }
    if (!$cid > 0) {
      $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
      return $renderer->renderNotFound();
    }

    return $this->renderPage($cid);
  } // run()


} // class

?>
