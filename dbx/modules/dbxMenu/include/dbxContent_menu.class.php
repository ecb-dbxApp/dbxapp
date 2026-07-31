<?php
namespace dbx\dbxMenu;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentPageCache;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap.php';

class dbxContent_menu {

  Public $oTPL;

  public function __construct() {
     $this->oTPL=dbx()->get_system_obj('dbxTPL');
  }


  private function user_has_access($groups) {
     $groups = trim((string)$groups);
     if ($groups === '') $groups = '*';
     return dbx()->can($groups);
  }

  private function resolve_folder_rights($db, $folder_id, array $visited = array()) {
     $folder_id = (int)$folder_id;
     if ($folder_id <= 0) return '*';
     if (isset($visited[$folder_id])) return '*';
     $visited[$folder_id] = 1;

     $row = $db->select1(dbxContentLng::ddFolder(), $folder_id, '*', 0);
     if (!is_array($row)) return '*';

     $raw = trim((string)($row['group_read'] ?? ''));
     if ($raw === '') return '*';

     $parent_id = (int)($row['parent_id'] ?? 0);
     $parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
     $out = array();
     $uses_parent = false;

     foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part === '') continue;
        if (strtolower($part) === 'parent') {
           $uses_parent = true;
           continue;
        }
        $out[$part] = 1;
     }

     if ($uses_parent) {
        $parent_rights = $this->resolve_folder_rights($db, $parent_id, $visited);
        foreach (preg_split('/\s*,\s*/', $parent_rights, -1, PREG_SPLIT_NO_EMPTY) as $part) {
           $part = trim((string)$part);
           if ($part !== '' && strtolower($part) !== 'parent') $out[$part] = 1;
        }
     }

     return count($out) ? implode(',', array_keys($out)) : '*';
  }

  private function folder_allowed($db, $folder_id) {
     return $this->user_has_access($this->resolve_folder_rights($db, $folder_id));
  }

  private function content_href(array $page) {
     $permalink = trim((string)($page['permalink'] ?? ''));
     if ($permalink !== '') {
        if (preg_match('/^(https?:\/\/|\/|\?)/i', $permalink)) return $permalink;
        return dbx()->get_base_url() . ltrim($permalink, '/');
     }
     return '?dbx_modul=dbxContent&dbx_run1=show&dbx_cid=' . (int)($page['id'] ?? 0);
  }

  private function cms_pages($db, $folder_id) {
     $rows = $db->select(dbxContentLng::ddContent(), 'folder = ' . (int)$folder_id . ' AND activ = 1', 'id,title,menu_title,permalink,sorter,folder', 'sorter,title,id', 'ASC', '', 0, 0, 0);
     return is_array($rows) ? $rows : array();
  }

  private function cms_folders($db, $parent_id) {
     $rows = $db->select(dbxContentLng::ddFolder(), 'parent_id = ' . (int)$parent_id, 'id,name,parent_id,group_read,sorter', 'sorter,name,id', 'ASC', '', 0, 0, 0);
     return is_array($rows) ? $rows : array();
  }

  private function render_page_li(array $page) {
     $title = trim((string)($page['menu_title'] ?? ''));
     if ($title === '') $title = trim((string)($page['title'] ?? ''));
     if ($title === '') $title = 'Seite ' . (int)($page['id'] ?? 0);
     return '<li class="dbx-cms-menu-page"><a href="' . dbx()->esc($this->content_href($page)) . '"><span>' . dbx()->esc($title) . '</span></a></li>';
  }

  private function resolve_folder_reference($db, $root) {
     if (is_int($root) || (is_string($root) && ctype_digit(trim($root)))) {
        return (int)$root;
     }

     $path = trim(rawurldecode((string)$root), " \t\n\r\0\x0B/");
     if ($path === '') return 0;

     $parentId = 0;
     foreach (preg_split('#\s*/\s*#', $path, -1, PREG_SPLIT_NO_EMPTY) as $name) {
        $folder = $db->select1(
           dbxContentLng::ddFolder(),
           array('parent_id' => $parentId, 'name' => trim((string)$name)),
           array('id', 'name'),
           0
        );
        $parentId = (int)($folder['id'] ?? 0);
        if ($parentId <= 0) return 0;
     }

     return $parentId;
  }

  private function render_pages($db, $folder_id) {
     $html = '';
     foreach ($this->cms_pages($db, $folder_id) as $page) {
        $html .= $this->render_page_li($page);
     }
     return $html;
  }

  private function render_folder_li($db, array $folder) {
     $id = (int)($folder['id'] ?? 0);
     if ($id <= 0 || !$this->folder_allowed($db, $id)) return '';

     $inner = $this->render_children($db, $id);
     if (trim($inner) === '') return '';

     $name = trim((string)($folder['name'] ?? ''));
     if ($name === '') $name = 'Ordner ' . $id;

     return '<li class="dbx-cms-menu-folder">'
        . '<a role="button" tabindex="0" aria-haspopup="true">'
        . '<i class="bi bi-folder2-open" aria-hidden="true"></i>'
        . '<span>' . dbx()->esc($name) . '</span>'
        . '</a><ul>' . $inner . '</ul></li>';
  }

  private function render_children($db, $folder_id, $include_pages = true) {
     $folder_id = (int)$folder_id;
     if (!$this->folder_allowed($db, $folder_id)) return '';

     $html = '';
     foreach ($this->cms_folders($db, $folder_id) as $folder) {
        $html .= $this->render_folder_li($db, $folder);
     }
     if ($include_pages) {
        $html .= $this->render_pages($db, $folder_id);
     }
     return $html;
  }

  public function render_flat_marker($root = 0, $flat = 1) {
     $flat = (int)$flat;

     if ($flat !== 1) return '';

     $db = dbx()->get_system_obj('dbxDB');
     $root = $this->resolve_folder_reference($db, $root);
     if (!$this->folder_allowed($db, $root)) return '';

     return $this->render_pages($db, $root);
  }

  public function render_placeholder($root = 0, $flat = 1, $split_flat = false) {
     $flat = (int)$flat;
     $split_flat = $split_flat && $flat === 1;
     $db = dbx()->get_system_obj('dbxDB');
     $root = $this->resolve_folder_reference($db, $root);
     $lng = dbxContentPageCache::currentLng();
     $variant = dbxContentPageCache::menuVariantFlat($flat);
     if ($split_flat) {
        $variant .= '-split';
     }

      if (dbxContentPageCache::isReadEnabled()) {
        $cached = dbxContentPageCache::readMenu($root, $variant, $lng);
        if ($cached !== null) {
           return $cached;
        }
     }

     $html = '';

     if ($root <= 0) {
        $inner = $this->render_children($db, 0, !$split_flat);
        if ($flat) {
           $html = $inner;
        } elseif (trim($inner) !== '') {
           $html = '<li class="dbx-cms-menu-root"><a>Content</a><ul>' . $inner . '</ul></li>';
        }
     } else {
        if ($this->folder_allowed($db, $root)) {
           if ($flat) {
              $html = $this->render_children($db, $root, !$split_flat);
           } else {
              $folder = $db->select1(dbxContentLng::ddFolder(), $root, '*', 0);
              if (is_array($folder)) {
                 $html = $this->render_folder_li($db, $folder);
              }
           }
        }
     }

      if (dbxContentPageCache::isWriteEnabled()) {
        dbxContentPageCache::writeMenu($html, $root, $variant, $lng);
     }

     return $html;
  }


  private function has_entrys($root,$tab_folder,$tab_content,$count,$deepr=0) {
    $deepr=($deepr + 1);
    $deep=dbx()->get_modul_var('deep',9,'int');


    //$count=($count + $this->has_files($root,$tab_content));

    if ($deepr <= $deep) {
      $count=($count + $this->has_files($root,$tab_content));
      $where = "parent_id = $root";
      $db    = dbx()->get_system_obj('dbxDB');
      $folder=$db->select($tab_folder,$where,'id,name,group_read,sorter','sorter,name,id');
      //dbx_debug("##Check-Entrys Folder=($root) Count=($count) Deep=($deep) Deepr=($deepr)");
      if (is_array($folder)) {
        foreach ($folder as $no => $record) {
          $access=$this->folder_allowed($db, (int)$record['id']);
          if ($access) {
             $root  = $record['id'];
             $name  = $record['name'];
             $count = $this->has_entrys($root,$tab_folder,$tab_content,$count,$deepr) ;
          }
        }
      }
    }
    return $count;
  }



  private function has_files($root,$tab_content) {
    $count = 0;
    if ($root >= 0) {
      $where = "folder = $root";
      //dbx_debug("Content-menu ($tab_content) where=($where)");
      $db    = dbx()->get_system_obj('dbxDB');
      if (!$this->folder_allowed($db, (int)$root)) return 0;
      $files = $db->select($tab_content,$where,'id,title');
      if (is_array($files)) {
        $count = count($files);
      }
    }
    //dbx_debug("has_files($root)=($count)");
    return $count;
  }

  private function content_files($root,$tab_content,$content) {
      $obj_files='';
      $where = "folder = $root";
      $db    = dbx()->get_system_obj('dbxDB');
      if (!$this->folder_allowed($db, (int)$root)) {
         return str_replace('{obj:files}', '', $content);
      }
      $files = $db->select($tab_content,$where,'id,title,permalink,sorter','sorter,title,id');
      if (is_array($files)) {
        foreach ($files as $no => $record) {
          $id   =$record['id'];
          $title=$record['title'];
          $tpl=$this->oTPL->get_tpl('modul','menu-content-file');
          if ($record['permalink']) {
             $href=$record['permalink'];
          } else {
             $href='?dbx_Modul=dbxContent&dbx_run1=show&cid='.$id;
          }
          $tpl = (str_replace('{href}' ,$href ,$tpl));
          $tpl = (str_replace('{title}',$title,$tpl));
          $obj_files.=$tpl;
        }
      }
      $content = (str_replace('{obj:files}',$obj_files,$content));
      return $content;
  }


  private function content_folder($root,$tab_folder,$tab_content,$content,$deepr=0) {
      $obj_folder='';
      $where = "parent_id = $root";
      $db    = dbx()->get_system_obj('dbxDB');
      $folder=$db->select($tab_folder,$where,'id,name,group_read,sorter','sorter,name,id');
      if (is_array($folder)) {
        $deep  = dbx()->get_modul_var('deep',9,'int');
        $deepr = ($deepr + 1);
        //dbx()->set_modul_var('deep_run',$deepr);
        if ($deepr <= $deep) {
          foreach ($folder as $no => $record) {
            $access=$this->folder_allowed($db, (int)$record['id']);
            if ($access) {
               $root  = $record['id'];
               $name  = $record['name'];
               $count = $this->has_entrys($root,$tab_folder,$tab_content,0,$deepr);
               if ($count) {
                  $tpl = $this->oTPL->get_tpl('modul','menu-content-sub');
                  $tpl = (str_replace('{folder}',$name ,$tpl));
                  $tpl = (str_replace('{count}' ,$count,$tpl));
                  $tpl = (str_replace('{deep}'  ,$deepr,$tpl));

                  $tpl=$this->content_files($root,$tab_content,$tpl);
                  if ($deepr < $deep) {
                     $tpl=$this->content_folder($root,$tab_folder,$tab_content,$tpl,$deepr);
                  } else {
                    $tpl='';
                  }
                  $obj_folder.=$tpl;
               } // count
            }  // access
          } // foreach
        } // deepr
      } // folder
      $content = (str_replace('{obj:folders}',$obj_folder,$content));
      return $content;
  }



  private function content_menu_load() {
    $content='';
    $root  = dbx()->get_modul_var('root',0,'int');
    $deep  = dbx()->get_modul_var('deep',9,'int');
    $label = dbx()->get_modul_var('label');
    $mode  = dbx()->get_modul_var('mode');
    $lng   = dbx()->get_modul_var('lng'); 
    $tpl   = 'menu-content';
    if ($mode) $tpl=$tpl.'-'.$mode;

    $tab_content = dbxContentLng::ddContent($lng);
    $tab_folder  = dbxContentLng::ddFolder($lng);
    dbx()->set_modul_var('deep_run',0);
    $entrys= $this->has_entrys($root,$tab_folder,$tab_content,0);
    $files = $this->has_files($root,$tab_content);
    if ($entrys) {
      $content=$this->oTPL->get_tpl('modul',$tpl);
      $content=str_replace('{label}',$label,$content);

      $content=$this->content_files($root,$tab_content,$content);
      $content=$this->content_folder($root,$tab_folder,$tab_content,$content);
    }
    //dbx_debug("### Content_menu ROOT=($entrys) TPL=($tpl) Mode=($mode) #####");

    return $content;
  }





  public function run() {
     $root = dbx()->get_modul_var('root', 0);
     if (!dbx()->is_int_value($root)) {
        #todo $root = folder name select get root 
     }

     $lng = trim((string) dbx()->get_modul_var('lng', ''));
     if ($lng === '') {
        $lng = dbx()->get_system_var('dbx_lng', 'de');
     }
     dbx()->set_modul_var('lng', $lng);

     $deep = (int) dbx()->get_modul_var('deep', 9, 'int');
     $mode = trim((string) dbx()->get_modul_var('mode', ''));
     $label = trim((string) dbx()->get_modul_var('label', ''));
     $variant = dbxContentPageCache::menuVariantLoad($deep, $mode, $label);

      if (dbxContentPageCache::isReadEnabled()) {
        $cached = dbxContentPageCache::readMenu((int) $root, $variant, $lng);
        if ($cached !== null) {
           return $cached;
        }
     }

     $content = $this->content_menu_load();

      if (dbxContentPageCache::isWriteEnabled()) {
        dbxContentPageCache::writeMenu($content, (int) $root, $variant, $lng);
     }

     return $content;
  } // run()

} // class

?>
