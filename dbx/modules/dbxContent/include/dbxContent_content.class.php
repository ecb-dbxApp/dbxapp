<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap.php';

class dbxContent_content {

   Public $oTPL;

   public function __construct() {
     $this->oTPL = dbx()->get_system_obj('dbxTPL');
   }





  private function get_content_tpl_from_folder($folder=0) {
    // #ToDO Recursive get TPL from Folder
    $tpl='c-content';
    $db=$db=dbx()->get_system_obj('dbxDB');
    $rec=$db->select1(dbxContentLng::ddFolder(),$folder);
    if (is_array($rec)) {
       if ($rec['template']) {
         $tpl=$rec['template'];
       } else {
         $rec=$db->select1(dbxContentLng::ddFolder(),$rec['parent_id']);
         if (is_array($rec)) {
            if ($rec['template']) {
              $tpl=$rec['template'];
            }
         }
       }
    }
    return $tpl;
  }


  private function get_cols_content($cols,$content,$split) {

    $content_cols=array();
    for ($c = 0; $c < $cols; $c++) { $content_cols[$c]=''; }
    //dbx_debug("get_cols=($split)");



    $content_tmp = explode($split, $content);
    if (is_array($content_tmp)) {
      $count_tmp   = count($content_tmp);
      //dbx_debug("br count=($count_tmp)");
      if ($count_tmp >= $cols) {
         $a=0; // Start
         $per_col= (int) ($count_tmp / $cols +1);
         //dbx_debug("br per col=($per_col) ");
         for ($c = 0; $c < $cols; $c++) {
            //dbx_debug("use cols=($cols) col=($c) A=($a)");
            for ($p = $a; $p < ($per_col * ($c+1)) ; $p++) {
               //dbx_debug("br add col=($c) part=($p)");
               if (isset($content_tmp[$p])) {
                 $content_cols[$c].=$content_tmp[$p].$split;
                 // check for multiline elements ul img ....
                 if (strpos($content_tmp[$p],'</ul>')) $p=($p +3);
                 if (strpos($content_tmp[$p],'<img'))  $p=($p +3);
               }
               $a++;
            }
         }
      }
    }
    return $content_cols;
  }

  private function check_cols($content_cols,$cols) {
    $ok=0;
    if (is_array($content_cols)) {
       if (isset($content_cols[$cols-1])) {
          if (trim($content_cols[$cols-1]) > '') $ok=1;
       }
    }
    return $ok;
  }


  private function get_col_content($cols,$content) {

      $content = preg_replace('/<hr\b[^>]*class="[^"]*\bdbx_split\b[^"]*"[^>]*>/i', '<hr class="dbx_split">', (string)$content);
      $content_cols = explode('<hr class="dbx_split">', $content);
      if ($cols == count($content_cols)) return $content_cols;

      $content = trim($content);
      $content = (str_replace('<hr class="dbx_split">','<br>',$content));
      $content = (str_replace('<br />','<br>',$content));
      $content = (str_replace('<br/>' ,'<br>',$content));
      $length  = strlen($content);

      if (!$length) return ''; // Content is empty
      $content_cols=array();
      if (!$this->check_cols($content_cols,$cols)) $content_cols=$this->get_cols_content($cols,$content,'</p>');
      if (!$this->check_cols($content_cols,$cols)) $content_cols=$this->get_cols_content($cols,$content,'<br>');
      if (!$this->check_cols($content_cols,$cols)) $content_cols=$this->get_cols_content($cols,$content,'.');
      if (!$this->check_cols($content_cols,$cols)) $content_cols=$this->get_cols_content($cols,$content,' ');

      return $content_cols;
  }

  private function split_content_sections($content) {
      $content = (string)$content;
      $sections = array(
        'hero' => '',
        'main' => $content,
        'thesar' => '',
        'footer' => '',
      );

      $tokens = array('hero', 'main', 'thesar', 'footer');
      $pattern = '/(?:<!--\s*dbx:(hero|main|thesar|footer)\s*-->|<span[^>]*class="[^"]*dbx-cms-marker[^"]*"[^>]*>\s*dbx:(hero|main|thesar|footer)\s*<\/span>)/i';

      if (!preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
        return $sections;
      }

      $parts = array();
      foreach ($matches[0] as $idx => $match) {
        $name = strtolower($matches[1][$idx][0] ?: $matches[2][$idx][0]);
        if (!in_array($name, $tokens, true)) continue;
        $parts[] = array('name' => $name, 'pos' => $match[1], 'len' => strlen($match[0]));
      }

      if (!$parts) return $sections;

      for ($i = 0; $i < count($parts); $i++) {
        $start = $parts[$i]['pos'] + $parts[$i]['len'];
        $end = isset($parts[$i + 1]) ? $parts[$i + 1]['pos'] : strlen($content);
        $sections[$parts[$i]['name']] = trim(substr($content, $start, max(0, $end - $start)));
      }

      if (trim($sections['main']) === $content) {
        $first = $parts[0]['pos'];
        $sections['main'] = trim(substr($content, 0, $first));
      }

      return $sections;
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

  private function get_media_merge($cid) {
      $merge = array('media_hero' => '', 'media_gallery' => '', 'media_teaser' => '', 'media_footer' => '');
      $cid = (int)$cid;
      if ($cid <= 0) return $merge;

      $db = dbx()->get_system_obj('dbxDB');
      $usage_rows = $db->select('dbxMediaUsage', 'content_id = ' . $cid . ' AND active = 1', '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      $rows = array();
      if (is_array($usage_rows) && !empty($usage_rows)) {
        foreach ($usage_rows as $usage) {
          if (!is_array($usage)) continue;
          $row = $db->select1('dbxMedia', (int)($usage['media_id'] ?? 0));
          if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) continue;
          $row['slot'] = (string)($usage['slot'] ?? 'gallery');
          $rows[] = $row;
        }
      } else {
        $rows = $db->select('dbxMedia', 'content_id = ' . $cid . ' AND active = 1', '*', 'slot,title', 'ASC', '', 0, 0, 0);
      }
      if (!is_array($rows)) return $merge;

      $gallery = '';

      foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $slot = trim((string)($row['slot'] ?? 'gallery')) ?: 'gallery';
         $url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . (int)($row['id'] ?? 0);
        $alt = htmlspecialchars((string)($row['alt'] ?? $row['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $img = '<img class="dbx-content-media dbx-content-media-' . htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') . '" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '">';

        if ($slot === 'hero' && !$merge['media_hero']) $merge['media_hero'] = $img;
        if ($slot === 'teaser' && !$merge['media_teaser']) $merge['media_teaser'] = $img;
        if ($slot === 'footer' && !$merge['media_footer']) $merge['media_footer'] = $img;
        if ($slot === 'gallery') $gallery .= $img;
      }

      $merge['media_gallery'] = $gallery ? '<div class="dbx-content-gallery">' . $gallery . '</div>' : '';
      return $merge;
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
      return "show cid=($cid) nicht gefunden! ";
    }

    return $this->renderPage($cid);
  } // run()


} // class

?>
