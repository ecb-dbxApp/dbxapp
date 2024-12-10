<?php
namespace dbx\dbxContent_admin;
dbx_get_sys_object('dbxReport','use');


class dbxReport_Content extends \dbxReport {
  public $_folders = array(); // speed up
  public $_groups  = array();


 
  private function get_folder_matrix() { // create matrix folder <-> parent 
    $folder_matrix=dbx_get_ModulVar('folder_matrix','');
    if (is_array($folder_matrix)) return $folder_matrix;
    $folder_matrix=array();
    $db=dbx_get_sys_object('dbxDB');
    $root = dbx_get_ModulVar('tree_root',0);
    $lng  = dbx_get_SysVar('dbx_lng','de');
    $tab  = 'dbx_'.$lng.'_content_folder';


    $folders=$db->select($tab,'','id,parent_id');
    if (is_array($folders)) {
       //dbx_debug("#Folders=",$folders);
       foreach ($folders as $no => $record) {
          $id='f_'.$record['id'];
          $pa=$record['parent_id'];
          //dbx_debug("Folder=($id) pa=($pa)");
          $folder_matrix[$id]=$pa;
       }
    }
    //dbx_debug("#Folders matrix=",$folder_matrix);
    dbx_set_ModulVar('folder_matrix',$folder_matrix);
    return $folder_matrix;
  }


 private function get_folder_level($folder,$root=0) {
   $level=0;
   $folder_matrix=$this->get_folder_matrix();

   while ($folder != $root) :
     $pa=$root;
     $folder='f_'.$folder;
     if (isset($folder_matrix[$folder])) $pa=$folder_matrix[$folder];
     if ($pa != $root && $pa > 0) {
       $level++;
       $folder=$pa;
     } else {
       $root=$folder; // break
     }
   endwhile;

   return $level;
 }




  private function get_folder_name($id) {
     $folders=$this->_folders;
     //dbx_debug("###Folders## id=($id) ",$folders);


     $l=0; $folder = "(0) /";
     if ($id) {
        $folder_name='?';
        if (isset($folders[$id])) {
          $folder_name=$folders[$id];
        }
        $l=($this->get_folder_level($id,0) +1);
        $folder = "($id) | $folder_name | ($l)";
     }
     return $folder;
  }

  public function run_body($content) {
    $folder=0; $level=0; $sorter=''; $permalink='';
    $record =$this->_record;

    if (isset($record['parent_id']))  $folder   =$record['id'];
    if (isset($record['folder']))     $folder   =$record['folder'];
    if (isset($record['sorter']))     $sorter   =$record['sorter'];

    if ($folder) $level=$this->get_folder_level($folder);

    if (!isset($record['parent_id'])) {
       if (isset($record['folder'])) $record['folder_name'] = $this->get_folder_name($record['folder']);
    }
    $record['sort']=$sorter;
    $record['l']    =($level +1); // root is 0
    $record['perma']=($permalink);
    $this->_record=$record;
    return $content;
  }
}




// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Class dbxContent_list {

  Public $oTPL;

  public function __construct() {
   $this->oTPL = dbx_get_sys_object('dbxTPL');
  }


  function make_select_data($data,$id,$name,$parent='') {
     $select_data=array();
     $select_data[0]='/ ';
     if (is_array($data)) {
      foreach ($data as $no => $record) {
         if (is_array($record)) {
            if ( isset($record[$id]) && isset($record[$name]) ) {
               $xid=$record[$id];
               $xna=$record[$name];
               if ($parent && isset($record[$parent])) {
                 $pid=$record[$parent];
                 if ($pid==$xid) $pid=0;
                 if ($pid) {
                   while ($pid > 0) {
                     foreach ($data as $no2 => $rec2) {
                       if ($rec2[$id]==$pid) {
                          $xna=$rec2[$name].' -> '.$xna;
                          $pid=$rec2[$parent];
                          break;
                       } // == $pid
                     } //foreach
                   } // while $pid > 0
                 } // $pid
               } // parent
               $xna='/ -> '.$xna;
               $select_data[$xid]=$xna;
            } // isset $id && $name
         } else {
            // ?
         }
      }
    }
    return $select_data;
  }




  Private function delete_data($id) {
    //dbx_debug("DELETE Record ID($id)");
    return 1;
  }


  private function report_content_tree() {
     //$content="Start<br>";
     //$content.=$this->get_content_tree();
     //$content.="<br>Ende";


     // TreeView einschalten
     $db  = dbx_get_sys_object('dbxDB');
     $lng = dbx_get_ModulVar('lng','de');

     $tab_content='dbx_'.$lng.'_content';
     $tab_folder = $tab_content.'_folder';

     $modal1['title']     ='Content bearbeiten';
     $modal1['dbx_target']='dbxmodal1_content';
     
     $modal=$this->oTPL->get_tpl('dbx','modal1',$modal1);

     $data_folder=$db->select($tab_folder);

     $data_folder=$this->make_select_data($data_folder,'id','name','parent_id');
     //$content.=$modal;

     $data=array();
     $data['root']=dbx_get_SessionVal('content_tree_root',0);

     $oForm=dbx_get_sys_object('dbxForm');
     $oForm->init('form-content-tree');

     $oForm->_action='?dbx_modul=dbxContent_admin&dbx_action=tree';
     $oForm->_data=$data;
     $oForm->_fld_change_state='*'; // move value allways to _post
     $oForm->add_fld('root','select-single-label',$data_folder,'array|int','Auswahl Content Root','Content Ordner' );
     $oForm->add_obj('submit','dbx|button-submit','label=Auswahl');


     if($oForm->submit()) {
       //dbx_debug("#FORM-SUBMIT#");
       if(!$oForm->errors()) {      // submit && no errors // we ignore warnings
          //dbx_debug("###_POST=",$oForm->_post,$_POST);
          $root=$oForm->_post['root'];
          dbx_set_SessionVal('content_tree_root',$root);
          //dbx_debug("Set-session root-val=($root)");
       }
     }

     $root=dbx_get_SessionVal('content_tree_root',0);
     //dbx_debug("get-session root-val=($root)");
     dbx_set_ModulVar('fid',$root);

     $folder=$this->report_content_folder(1);
     $tree  =$this->get_content_tree($root);
     $files =$this->report_content_files();

     $oForm->add_obj('folder','obj-value',$folder);

     //dbx_debug("FOLDER",$folder);


     $oForm->add_obj('tree' ,'obj-value',$tree);
     $oForm->add_obj('files','obj-value',$files);
     $oForm->add_obj('modal','obj-value',$modal);

     $oForm->add_js_call('','tree');
     $content=$oForm->run();

     return $content;
  }



   private function get_content_tree($root) {
      $i   = dbx_get_next_i();
      dbx_set_ModulVar('fid',$root);
      $tree=$this->oTPL->get_tpl('modul','report-content-tree',"fid=$root&l=0",'htm',$i);
      //dbx_debug("#Content-Tree Root=($root) i=($i)",$tree);

      return $tree;
   }



    private function report_content_flat() {
      $oDB = dbx_get_sys_object('dbxDB');
      $lng = dbx_get_ModulVar('lng','de');
      $rid = dbx_get_ModulVar('rid',0,'int'); 

      $tab_content='dbx_'.$lng.'_content';
      $tab_folder = $tab_content.'_folder';
      $tab_groups = 'dbx_user_groups';    


      $form_id ='Report-Content-flat';
      $oReport = new dbxReport_Content();
      $oReport->init($form_id);
 

      $do=dbx_get_ModulVar('dbx_do');
      if ($do == 'row_edit' && $rid) {
         $obj=dbx_get_Modul_include_object('dbxContent_view');
         $modal_content=$obj->run();
         return $modal_content;

      }
      if ($do == 'row_delete' && $rid) {
         $ok=$oDB->delete($tab_content,$rid);
         if ( $ok) $oReport->_msg_success = 'Zeile gelöscht';
         if (!$ok) $oReport->_msg_error   = 'Zeile konnte nicht gelöscht werden';
      }    


      $tab_content='dbx_'.$lng.'_content';
      $tab_folder = $tab_content.'_folder';
      $tab_groups = 'dbx_user_groups';

      $folders=array(); $groups=array();
      $data_folder=$oDB->select($tab_folder);
      foreach ($data_folder as $no => $record) {
         $id          =$record['id'];
         $folders[$id]=$record['name'];
      }
      $data_groups=$oDB->select($tab_groups);
      foreach ($data_groups as $no => $record) {
         $id          =$record['name'];
         $groups[$id] =$record['description'];
      }

      $oReport->_folders =$folders; // speed up
      $oReport->_groups = $groups;   // speed up
      //dbx_debug("##FOLDERS##",$folders);



      $flds['id']         ='ID';
      $flds['permalink']  ='Permalink';
      $flds['title']      ='Titel';
      $flds['folder_name']='ID | Ordner  | Level';
      $flds['group_read'] ='Zugriff';
      $flds['activ']      ='Aktiv';
      $flds['hits']       ='Hits';

      $options_rsort['id']     ='ID';
      $options_rsort['folder'] ='Ortner';
      $options_rsort['title']  ='Titel';

      $data['dbx_rrows']= 10;
      $data['dbx_rsort']='id';

      //$oReport->set_form_id($form_id,$data);
      $oReport->_data             = $data;
      $oReport->_action           ='?dbx_modul=dbxContent_admin&dbx_action=flat'; // set_action() cid 'new' or record.id
      $oReport->_options_rsort    = $options_rsort;
      $oReport->_but_pagination   = 9;
      $oReport->_create_row_select= 1;
      $oReport->_create_row_edit  = 1;
      $oReport->_create_row_delete= 1;

      $oReport->_msg_info ='';

      $oReport->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_work=multi_delete');
      $oReport->add_action('rows_activate'  ,'action_button_activate'  ,'&dbx_work=multi_activate');
      $oReport->add_action('rows_deactivate','action_button_deactivate','&dbx_work=multi_deactivate');


 


      if($oReport->submit()) {
        if(!$oReport->errors()) {      // submit && no errors
           $work=$oReport->get_post('dbx_work');
           if ($work == 'multi_delete') {
              $ids=$oReport->get_post('Report-content_select','','array|int');
              if (is_array($ids)) {
                 foreach ($ids as $no => $id) {
                    $ok=$this->delete_data($id);
                 }
              }
           }
           $oReport->_msg_success   = 'Daten ausgewählt und sortiert';
        } else {
           $oReport->_msg_error = 'Prüfen sie bitte ihre Eingaben';
        }
      }  

      // get all selections and order
      $rgroup='';
      $rwhere=$oReport->get_sel('dbx_rwhere','');
      $rrows =$oReport->get_sel('dbx_rrows',10);
      $rpos  =$oReport->get_sel('dbx_rpos',0);
      $rsort =$oReport->get_sel('dbx_rsort','id');
      $rdesc =$oReport->get_sel('dbx_rdesc','ASC');
      // Custom select
      if ($rwhere) $rwhere="title  LIKE '%$rwhere%' ";
      // get db-Data
      $flds2=$flds;  // folder ist nicht in flds, da folder_name (generic)
      $flds2['folder']='folder';
      $oReport->_rcount=$oDB->count($tab_content,$rwhere);
      $oReport->_rdata =$oDB->select($tab_content,$rwhere,$flds2,$rsort,$rdesc,$rgroup,$rrows,$rpos);
      // run Report
      $content=$oReport->run(1,$flds,'table');

      //$content= (str_replace('dbxAjax','',$content));

      return $content;
   } // report_content_flat()




   private function report_content_folder($root=0) {
     $content = '';

     $oReport = new dbxReport_Content;
     $db      = dbx_get_sys_object('dbxDB');

     $lng = dbx_get_sysVar('dbx_lng','de');

     $tab_content='dbx_'.$lng.'_content';
     $tab_folder = $tab_content.'_folder';

     $folder =dbx_get_ModulVar('fid',0,'int');

     $flds['id']           ='ID';
     $flds['parent_id']    ='Parent';
     $flds['name']         ='Ordner';
     $flds['group_read']   ='Lesen';
     $flds['group_write']  ='Schreiben';
     $flds['group_delete'] ='Löschen';

     $data['dbx_rrows']= 1000;
     $data['dbx_rsort']='name';

     if (!$root) $where_folder = "parent_id = $folder";
     if ( $root) $where_folder = "id        = $folder";


     if ( $folder) $rdata =$db->select($tab_folder,$where_folder,'*','name');
     if (!$folder) {
        $rdata[0]['id']  =0;
        $rdata[0]['name']='/ (root)';
     }
     //$rdata =$this->get_folder_level($rdata,$folder);
     //dbx_debug("Root=($root) Folder=($folder) where=($where_folder)",$rdata);

     $oReport->init('report-content-folder');
     $oReport->_data  = $data;
     $oReport->_rdata = $rdata;
     $oReport->_rcount= count($rdata);
     $oReport->_create_sel_flds=0;

     if ($oReport->_rcount) {
         $content=$oReport->run(0);
     }
     //$content= str_replace('{m}'  ,$m    ,$content);
     return $content;
   }


   private function report_content_files() {
     //$m   = dbx_get_sysVar('modal_m',0);
     $db  = dbx_get_sys_object('dbxDB');
     $lng = dbx_get_ModulVar('lng','de'); // sysvar ?

     $tab_content='dbx_'.$lng.'_content';
     $folder=dbx_get_ModulVar('fid',-1,'int');
     if ($folder < 0) return 'no access';

     $flds['id']        ='ID';
     $flds['sorter']    ='Seq';
     $flds['title']     ='Titel';
     $flds['folder']    ='Ordner';
     $flds['permalink'] ='Permalink';

     $data['dbx_rrows']= 25;
     $data['dbx_rsort']='sorter';

     $rwhere = "folder = $folder";
     $rcount=$db->count($tab_content,$rwhere);
     $rdata =$db->select($tab_content,$rwhere,$flds,'sorter');
     //$rdata =$this->get_folder_level($rdata,$folder);

     $oReport = new dbxReport_Content;   //  dbx_get_sys_object('dbxReport','new');
     $oReport->init('report-content-files');
     $oReport->_data=$data;
     $oReport->_create_sel_flds=0;
     $oReport->_rcount=$rcount;
     $oReport->_rdata =$rdata;


     if ($rcount) {
       $oReport->add_obj('folder_file','modul|folder_files');
       $content="<li>($rcount) Einträge Ordner ($folder)</li>";
       $content=$oReport->run(0,$flds);
     } else {
       $content="<ul><li>Keine Einträge Ordner ($folder)</li></ul>";
     }

     //$content= str_replace('{m}'  ,$m    ,$content);

     return $content;
   }



   private function report_content_folder_files() {
     $content='';
     $oReport = new dbxReport_Content;
     $db      = dbx_get_sys_object('dbxDB');

     $lng = dbx_get_SysVar('dbx_lng','de'); // sysvar ?

     $tab_content='dbx_'.$lng.'_content';
     $tab_folder = $tab_content.'_folder';

     $root  =dbx_get_ModulVar('fid',0,'int');
     $folder=dbx_get_SysVar('tree_folder',0);

     $folder=0;

     //dbx_set_ModulVar('tree_folder',0); //only once

     //dbx_set_SysVar('tree_root',$root);

     $flds['id']           ='ID';
     $flds['parent_id']    ='Parent';
     $flds['name']         ='Ordner';
     $flds['sorter']       ='Seq';
     $flds['group_read']   ='Lesen';

     $data['dbx_rrows']= 1000;
     $data['dbx_rsort']='name';

     if (!$folder) $where_folder = "parent_id = $root";
     if ( $folder) $where_folder = "id        = $root";

     $data_folder  = $db->select($tab_folder,$where_folder,'*','name');
     //dbx_debug("Folder=($folder) where=($where_folder)",$data_folder);

     //$level        = $this->get_folder_level($data_folder[0][],$root);

     $oReport->init('report-content-folder-files');
     $oReport->_data  = $data;
     $oReport->_rdata = $data_folder;
     $oReport->_rcount= count($data_folder);
     $oReport->_create_sel_flds=0;
     //$oReport->add_rep('fid',$root);

     if ($oReport->_rcount) {
       $content=$oReport->run(0,$flds);
       //dbx_debug("report_content_folder_files root=($root) ");


     }
     return $content;
   }
   //  Helper function for tree-view aktions add and delete_data

   public function add_content() {
     $rid=dbx_get_ModulVar('rid',0);
     if ($rid) { // folder id
       $db  = dbx_get_sys_object('dbxDB');
       $lng = dbx_get_ModulVar('lng','de');

       $tab_content='dbx_'.$lng.'_content';
       $tab_folder = $tab_content.'_folder';

       $data['id']=0;
       $data['folder']=$rid;
       $data['title'] ='Neuer Content';
       $data['group_read'] ='admin';
       $data['template'] ='c-default';
       $ok=$db->save($tab_content,$data,0);
     }
   }


   public function del_content() {
     $rid=dbx_get_ModulVar('rid',0);
     //dbx_debug("TREE-DEL-Content=($rid)");

     if ($rid) { // folder id
       $db  = dbx_get_sys_object('dbxDB');
       $lng = dbx_get_ModulVar('lng','de');
       $tab_content='dbx_'.$lng.'_content';

       $ok=$db->delete($tab_content,$rid,1);
       $error=$db->_error;
       $query=$db->_query;

       //dbx_debug("DB-Error=($error) \n Query=($query)");



       //dbx_debug("del=($ok)");
     }
   }



   // ----------------------------------------------------

   public function run($action='flat') {
      $content='Modul dbxContent_admin action('.dbx_html($action).') not defined';
      //dbx_Debug("dbxContent_list=($action)");
      switch ($action) {
        case 'flat':
            $content=$this->report_content_flat();
            break;
        case 'tree': // alias for 'folder_files'
            $content=$this->report_content_tree();
            break;
        case 'folder':
            $content=$this->report_content_folder();
            break;
        case 'files':
            $content=$this->report_content_files();
            break;
        case 'folder_files':
            $content=$this->report_content_folder_files();
            break;

      }
      return $content;
   } // run

} // class



?>
