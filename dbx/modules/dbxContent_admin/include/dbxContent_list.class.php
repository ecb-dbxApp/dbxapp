<?php
namespace dbx\dbxContent_admin;
dbx()->get_system_obj('dbxReport','use');
require_once __DIR__ . '/dbxReport_Content.class.php';
require_once __DIR__ . '/dbxContentSelectOptions.class.php';

use dbx\dbxContent\dbxContent_permalink;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';


Class dbxContent_list {

  Public $o_tpl;

  public function __construct() {
   $this->o_tpl = dbx()->get_system_obj('dbxTPL');
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
     $db  = dbx()->get_system_obj('dbxDB');
     $lng = dbx()->get_modul_var('lng','de');

     $tab_content=dbx()->lng_name('content', $lng);
     $tab_folder = dbx()->lng_name('content_folder', $lng);

     $data_folder=$db->select($tab_folder);

     $data_folder=dbxContentSelectOptions::hierarchy((array)$data_folder, 'id', 'name', 'parent_id');
     //$content.=$modal;

     $data=array();
     $data['root']=dbx()->get_session_var('content_tree_root',0);

     $o_form=dbx()->get_system_obj('dbxForm');
     $o_form->init('form-content-tree', 'form-content-tree');

     $o_form->set_action('?dbx_modul=dbxContent_admin&dbx_run1=tree');
     $o_form->set_data($data);
     $o_form->_fld_change_state='*'; // move value allways to _post
     $o_form->add_fld('root','select-single-label',rules: 'array|int', label: 'Auswahl Content Root',options: $data_folder);
     $o_form->add_obj('submit','dbx|button-submit',label: 'Auswahl');


     if($o_form->submit()) {
       //dbx_debug("#FORM-SUBMIT#");
       if(!$o_form->errors()) {      // submit && no errors // we ignore warnings
          $root=$o_form->post_value('root');
          dbx()->set_session_var('content_tree_root',$root);
          //dbx_debug("Set-session root-val=($root)");
       }
     }

     $root=dbx()->get_session_var('content_tree_root',0);
     //dbx_debug("get-session root-val=($root)");
     dbx()->set_modul_var('fid',$root);

     $folder=$this->report_content_folder(1);
     $tree  =$this->get_content_tree($root);
     $files =$this->report_content_files();

     $o_form->add_obj('folder','obj-value',$folder);

     //dbx_debug("FOLDER",$folder);


     $o_form->add_obj('tree' ,'obj-value',$tree);
     $o_form->add_obj('files','obj-value',$files);
     $o_form->add_obj('modal','obj-value',$modal);

     $o_form->add_js_call('','tree');
     $content=$o_form->run();

     return $content;
  }



   private function get_content_tree($root) {
      $i   = dbx()->next_id();
      dbx()->set_modul_var('fid',$root);
      $tree=$this->o_tpl->get_tpl('modul','report-content-tree',"fid=$root&l=0",'htm',$i);
      //dbx_debug("#Content-Tree Root=($root) i=($i)",$tree);

      return $tree;
   }



    private function report_content_flat() {
      $o_db = dbx()->get_system_obj('dbxDB');
      $lng = dbx()->get_modul_var('lng','de');
      $rid = dbx()->get_modul_var('rid',0,'int'); 

      $tab_content=dbx()->lng_name('content', $lng);
      $tab_folder = dbx()->lng_name('content_folder', $lng);
      $tab_groups = 'dbxUser_groups';


      $form_id ='Report-Content-flat';
      $o_report = new dbxReport_Content();
      $o_report->init($form_id, 'report-content-flat');
 

      $do=dbx()->get_modul_var('dbx_run3');
      if ($do == 'row_edit' && $rid) {
         $obj=dbx()->get_include_obj('dbxContent_view');
         $modal_content=$obj->run();
         return $modal_content;

      }
      if ($do == 'row_delete' && $rid) {
         $ok=$o_db->delete($tab_content,$rid);
         if ( $ok) $o_report->_msg_success = 'Zeile gelöscht';
         if (!$ok) $o_report->_msg_error   = 'Zeile konnte nicht gelöscht werden';
      }    


      $tab_content=dbx()->lng_name('content', $lng);
      $tab_folder = dbx()->lng_name('content_folder', $lng);
      $tab_groups = 'dbxUser_groups';

      $folders=array(); $groups=array();
      $data_folder=$o_db->select($tab_folder);
      foreach ($data_folder as $no => $record) {
         $id          =$record['id'];
         $folders[$id]=$record['name'];
      }
      $data_groups=$o_db->select($tab_groups);
      foreach ($data_groups as $no => $record) {
         $id          =$record['name'];
         $groups[$id] =$record['description'];
      }

      $o_report->_folders =$folders; // speed up
      $o_report->_groups = $groups;   // speed up
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
      $o_report->set_data($data);
      $o_report->set_action('?dbx_modul=dbxContent_admin&dbx_run1=flat');
      $o_report->_options_rsort    = $options_rsort;
      $o_report->_but_pagination   = 9;
      $o_report->set_table_actions(array('select', 'edit', 'delete'));

      $o_report->_msg_info ='';

      $o_report->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_run2=multi_delete');
      $o_report->add_action('rows_activate'  ,'action_button_activate'  ,'&dbx_run2=multi_activate');
      $o_report->add_action('rows_deactivate','action_button_deactivate','&dbx_run2=multi_deactivate');


 


      if($o_report->submit()) {
        if(!$o_report->errors()) {      // submit && no errors
           $work=$o_report->get_post('dbx_run2');
           if ($work == 'multi_delete') {
              $ids=$o_report->get_post('Report-content_select','','array|int');
              if (is_array($ids)) {
                 foreach ($ids as $no => $id) {
                    $ok=$this->delete_data($id);
                 }
              }
           }
           $o_report->_msg_success   = '';
        } else {
           $o_report->_msg_error = 'Prüfen sie bitte ihre Eingaben';
        }
      }  

      // get all selections and order
      $rgroup='';
      $rwhere=$o_report->get_fld_val('dbx_rwhere','','varchar|trim');
      $rrows =$o_report->get_fld_val('dbx_rrows',10,'int|min=1|max=1000');
      $rpos  =$o_report->get_fld_val('dbx_rpos',0,'int|min=0');
      $rsort =$o_report->get_fld_val('dbx_rsort','id','parameter');
      $rdesc =strtoupper((string)$o_report->get_fld_val('dbx_rdesc','ASC','parameter'));
      if (!in_array($rdesc, array('ASC', 'DESC'), true)) $rdesc = 'ASC';
      // Custom select
      if ($rwhere) {
         $server = $o_db->get_dd_server($tab_content);
         $needle = $o_db->escape_like($rwhere, $server);
         $rwhere = "title LIKE '%$needle%'";
      }
      // get db-Data
      $flds2=$flds;  // folder ist nicht in flds, da folder_name (generic)
      $flds2['folder']='folder';
      $o_report->set_report_counts(
         $o_db->count($tab_content, $rwhere),
         $o_db->count($tab_content)
      );
      $o_report->_rdata =$o_db->select($tab_content,$rwhere,$flds2,$rsort,$rdesc,$rgroup,$rrows,$rpos);
      // run Report
      $content=$o_report->run(1,$flds,'table');

      //$content= (str_replace('dbxAjax','',$content));

      return $content;
   } // report_content_flat()




   private function report_content_folder($root=0) {
     $content = '';

     $o_report = new dbxReport_Content;
     $db      = dbx()->get_system_obj('dbxDB');

     $lng = dbx()->get_system_var('dbx_lng','de');

     $tab_content=dbx()->lng_name('content', $lng);
     $tab_folder = dbx()->lng_name('content_folder', $lng);

     $folder =dbx()->get_modul_var('fid',0,'int');

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

     $o_report->init('report-content-folder', 'report-content-folder');
     $o_report->set_data($data);
     $o_report->_rdata = $rdata;
     $o_report->_rcount= count($rdata);
     $o_report->_create_sel_flds=0;

     if ($o_report->_rcount) {
         $content=$o_report->run(0);
     }
     //$content= str_replace('{m}'  ,$m    ,$content);
     return $content;
   }


   private function report_content_files() {
     //$m   = dbx()->get_system_var('modal_m',0);
     $db  = dbx()->get_system_obj('dbxDB');
     $lng = dbx()->get_modul_var('lng','de'); // sysvar ?

     $tab_content=dbx()->lng_name('content', $lng);
     $folder=dbx()->get_modul_var('fid',-1,'int');
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

     $o_report = new dbxReport_Content;   //  dbx()->get_system_obj('dbxReport','new');
     $o_report->init('report-content-files', 'report-content-files');
     $o_report->set_data($data);
     $o_report->_create_sel_flds=0;
     $o_report->_rcount=$rcount;
     $o_report->_rdata =$rdata;


     if ($rcount) {
       $o_report->add_obj('folder_file','modul|folder_files');
       $content="<li>($rcount) Einträge Ordner ($folder)</li>";
       $content=$o_report->run(0,$flds);
     } else {
       $content="<ul><li>Keine Einträge Ordner ($folder)</li></ul>";
     }

     //$content= str_replace('{m}'  ,$m    ,$content);

     return $content;
   }



   private function report_content_folder_files() {
     $content='';
     $o_report = new dbxReport_Content;
     $db      = dbx()->get_system_obj('dbxDB');

     $lng = dbx()->get_system_var('dbx_lng','de'); // sysvar ?

     $tab_content=dbx()->lng_name('content', $lng);
     $tab_folder = dbx()->lng_name('content_folder', $lng);

     $root  =dbx()->get_modul_var('fid',0,'int');
     $folder=dbx()->get_system_var('tree_folder',0);

     $folder=0;

     //dbx()->set_modul_var('tree_folder',0); //only once

     //dbx()->set_system_var('tree_root',$root);

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

     $o_report->init('report-content-folder-files', 'report-content-folder-files');
     $o_report->set_data($data);
     $o_report->_rdata = $data_folder;
     $o_report->_rcount= count($data_folder);
     $o_report->_create_sel_flds=0;
     //$oReport->add_rep('fid',$root);

     if ($o_report->_rcount) {
       $content=$o_report->run(0,$flds);
       //dbx_debug("report_content_folder_files root=($root) ");


     }
     return $content;
   }
   //  Helper function for tree-view aktions add and delete_data

   public function add_content() {
     $rid=dbx()->get_modul_var('rid',0);
     if ($rid) { // folder id
       $db  = dbx()->get_system_obj('dbxDB');
       $lng = dbx()->get_modul_var('lng','de');

       $tab_content=dbx()->lng_name('content', $lng);
       $tab_folder = dbx()->lng_name('content_folder', $lng);

       $data['id']=0;
       $data['folder']=$rid;
       $data['title'] ='Neuer Content';
       $data['permalink'] = dbxContent_permalink::build($db, $tab_folder, $rid, $data['title']);
       $data['group_read'] ='admin';
       $data['template'] ='c-content';
       $ok=$db->save($tab_content,$data,0);
     }
   }


   public function del_content() {
     $rid=dbx()->get_modul_var('rid',0);
     //dbx_debug("TREE-DEL-Content=($rid)");

     if ($rid) { // folder id
       $db  = dbx()->get_system_obj('dbxDB');
       $lng = dbx()->get_modul_var('lng','de');
       $tab_content=dbx()->lng_name('content', $lng);

       $ok=$db->delete($tab_content,$rid,1);
       $error=$db->_error;
       $query=$db->_query;

       //dbx_debug("DB-Error=($error) \n Query=($query)");



       //dbx_debug("del=($ok)");
     }
   }



   // ----------------------------------------------------

   public function run($action='flat') {
      $content='Modul dbxContent_admin action('.dbx()->esc($action).') not defined';
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
