<?php
namespace dbx\dbxMenu;

class dbxContent_menu {

  Public $oTPL;

  public function __construct() {
     $this->oTPL=dbx_get_sys_object('dbxTPL');
  }


  private function user_has_access($groups) {
     return 1;
  }


  private function has_entrys($root,$tab_folder,$tab_content,$count,$deepr=0) {
    $deepr=($deepr + 1);
    $deep=dbx_get_ModulVar('deep',9,'int');


    //$count=($count + $this->has_files($root,$tab_content));

    if ($deepr <= $deep) {
      $count=($count + $this->has_files($root,$tab_content));
      $where = "parent_id = $root";
      $db    = dbx_get_sys_object('dbxDB');
      $folder=$db->select($tab_folder,$where,'id,name,group_read');
      //dbx_debug("##Check-Entrys Folder=($root) Count=($count) Deep=($deep) Deepr=($deepr)");
      if (is_array($folder)) {
        foreach ($folder as $no => $record) {
          $access=$this->user_has_access($record['group_read']);
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
      $db    = dbx_get_sys_object('dbxDB');
      $files = $db->select($tab_content,$where,'id,title,group_read');
      if (is_array($files)) {
        foreach ($files as $no => $record) {
          $title=$record['title'];
          $access=$this->user_has_access($record['group_read']);
          if ($access) $count ++;
        }
      }
    }
    //dbx_debug("has_files($root)=($count)");
    return $count;
  }

  private function content_files($root,$tab_content,$content) {
      $obj_files='';
      $where = "folder = $root";
      $db    = dbx_get_sys_object('dbxDB');
      $files = $db->select($tab_content,$where,'id,title,permalink,group_read','sorter');
      if (is_array($files)) {
        foreach ($files as $no => $record) {
          $id   =$record['id'];
          $title=$record['title'];
          $access=$this->user_has_access($record['group_read']);
          if ($access) {
             $tpl=$this->oTPL->get_tpl('modul','menu-content-file');
             if ($record['permalink']) {
                $href=$record['permalink'];
             } else {
                $href='?dbx_Modul=dbxContent&dbx_action=show&cid='.$id;
             }
             $tpl = (str_replace('{href}' ,$href ,$tpl));
             $tpl = (str_replace('{title}',$title,$tpl));
             $obj_files.=$tpl;
          }
        }
      }
      $content = (str_replace('{obj:files}',$obj_files,$content));
      return $content;
  }


  private function content_folder($root,$tab_folder,$tab_content,$content,$deepr=0) {
      $obj_folder='';
      $where = "parent_id = $root";
      $db    = dbx_get_sys_object('dbxDB');
      $folder=$db->select($tab_folder,$where,'id,name,group_read');
      if (is_array($folder)) {
        $deep  = dbx_get_ModulVar('deep',9,'int');
        $deepr = ($deepr + 1);
        //dbx_set_ModulVar('deep_run',$deepr);
        if ($deepr <= $deep) {
          foreach ($folder as $no => $record) {
            $access=$this->user_has_access($record['group_read']);
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
    $root  = dbx_get_ModulVar('root',0,'int');
    $deep  = dbx_get_ModulVar('deep',9,'int');
    $label = dbx_get_ModulVar('label');
    $mode  = dbx_get_ModulVar('mode');
    $lng   = dbx_get_ModulVar('lng'); 
    $tpl   = 'menu-content';
    if ($mode) $tpl=$tpl.'-'.$mode;

    $tab_content= 'dbx_'.$lng.'_content';
    $tab_folder = $tab_content.'_folder';
    dbx_set_ModulVar('deep_run',0);
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
     $root=dbx_get_ModulVar('root',0);
     if (!dbx_is_integer($root)) {
        #todo $root = folder name select get root 
     }
     dbx_set_ModulVar('lng',dbx_get_SysVar('dbx_lng')); 
     return $this->content_menu_load();
  } // run()

} // class

?>