<?php
namespace dbx\dbxContent;




class dbxContent_content {

   Public $oTPL;

   public function __construct() {
     $this->oTPL = dbx_get_sys_object('dbxTPL');
   }





  private function get_content_tpl_from_folder($folder=0) {
    // #ToDO Recursive get TPL from Folder
    $tpl='C-default';
    $db=$db=dbx_get_sys_object('dbxDB');
    $rec=$db->select1('dbx_de_content_folder',$folder);
    if (is_array($rec)) {
       if ($rec['template']) {
         $tpl=$rec['template'];
       } else {
         $rec=$db->select1('dbx_de_content_folder',$rec['parent_id']);
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


  public function run() {
     $tpl=''; $content=''; $imgs=array();
     $uid=dbx_get_CurrentUser();
     $cid=dbx_get_ModulVar('dbx_cid',0,'int');
     $lng=dbx_get_SysVar('dbx_lng','de');
     $content_tab='dbx_'.$lng.'_content';
     $img_url=dbx_get_SysVar('dbx_base_url').'dbx/modules/dbxContent/img/';
     $design= dbx_get_SysVar('dbx_design','default');

     dbx_set_SysVar('dbx_page','content');
     if (!$cid) return 'Keine dbx_cid gesetzt!';

     $db=dbx_get_sys_object('dbxDB');
     $rec=$db->select1($content_tab,$cid);
     if (is_array($rec)) {
        $hits=$rec['hits'];
        if ($hits >= 0) { // Update Hits
          $id= $rec['id'];
          $upd['id']   = $id; 
          $upd['hits'] =($hits +1);
          $ok=$db->update($content_tab,$upd,$id,0,1,1,0); // no access-check and no trace  
        }


        $img=0; $cols=0;
        if ($rec['upload1']) $img++;
        if ($rec['upload2']) $img++;
        if ($rec['upload3']) $img++;
        $tpl   =$rec['template'];
        $folder=$rec['folder'];
        if (!$tpl) $tpl=$this->get_content_tpl_from_folder($folder);

        $merge['i']      = dbx_get_next_i();
        $title=$rec['title'];
        dbx_set_SysVar('dbx_title',$title);

        $merge['title']  =$rec['title'];
        $merge['content']=$rec['content'];
        $merge['thesar'] =$rec['thesar'];

        $merge['src_1']  =$img_url.$rec['upload1'];
        $merge['src_2']  =$img_url.$rec['upload2'];
        $merge['src_3']  =$img_url.$rec['upload3'];

        $merge['alt_1']  =$rec['img_alt_1'];
        $merge['alt_2']  =$rec['img_alt_2'];
        $merge['alt_3']  =$rec['img_alt_3'];

        $merge['des_1']  =$rec['img_des_1'];
        $merge['des_2']  =$rec['img_des_2'];
        $merge['des_3']  =$rec['img_des_3'];

        $img1['src']     =$img_url.$rec['upload1'];
        $img1['alt']     =$rec['img_alt_1'];

        $img2['src']     =$img_url.$rec['upload2'];
        $img2['alt']     =$rec['img_alt_2'];

        $img3['src']     =$img_url.$rec['upload3'];
        $img3['alt']     =$rec['img_alt_3'];


        $merge['img1']   =$this->oTPL->get_tpl('dbxContent','images',$img1);
        $merge['img2']   =$this->oTPL->get_tpl('dbxContent','images',$img2);
        $merge['img3']   =$this->oTPL->get_tpl('dbxContent','images',$img3);

        $content=$this->oTPL->get_tpl('dbxContent',$tpl);

        if (strpos($content,"{col_1}")) $cols=1;
        if (strpos($content,"{col_2}")) $cols=2;
        if (strpos($content,"{col_3}")) $cols=3;

        if ($cols) {
          $cols_content=$this->get_col_content($cols,$rec['content']);
          if (isset($cols_content[0])) $merge['col_1'] = $cols_content[0];
          if (isset($cols_content[1])) $merge['col_2'] = $cols_content[1];
          if (isset($cols_content[2])) $merge['col_3'] = $cols_content[2];
        }
        $merge['cols'] = $cols;

        $content=$this->oTPL->replaces($content,$merge);

     } else {
       $content = $this->oTPL->get_tpl('dbxContent','no-page');
     }

     return $content;
  } // run()


} // class

?>
