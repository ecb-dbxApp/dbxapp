<?php
namespace dbx\dbxContent_admin;
dbx()->get_system_obj('dbxReport','use');
require_once __DIR__ . '/dbxReport_Folder.class.php';

require_once __DIR__ . '/dbxFolder_edit.class.php';
Class dbxContent_folder {

  public $o_tpl;

  public function __construct() {
     $this->o_tpl = dbx()->get_system_obj('dbxTPL');
  }

  function get_select_tpl($modul,$pfx='') {
     $select_data=array();
     $folder=$this->o_tpl->get_tpl_dir($modul);
     $folder_files=array_diff(scandir($folder), array('..', '.'));
     foreach ($folder_files as $no => $filename) {
        $id=substr($filename, 0 , (strrpos($filename, ".")));
        $na=$id;
        if ($pfx) {
          if (substr($na, 0, strlen($pfx)) != $pfx) $id=0;
        }
        if ($id) $select_data[$id]=$na;
     }
     //dbx_debug("Directory=($folder)",$folder_files);

     return $select_data;
   }




   private function report_folder_flat() {

      //return 'folder-flat';

      $o_report = new dbxReport_Folder;
      $o_db     = dbx()->get_system_obj('dbxDB');
      $lng     = dbx()->get_modul_var('lng','de');

      $dd_content = dbx()->lng_name('content', $lng);
      $dd_folder = dbx()->lng_name('content_folder', $lng);
      $dd_groups = 'dbxUser_groups';

      $rid=dbx()->get_modul_var('rid',0,'int');
      $do =dbx()->get_modul_var('dbx_run3');

      if ($do == 'row_delete' && $rid) $o_db->delete($dd_folder,$rid);
   
      if ($do == 'row_edit' ) return $this->folder_edit($rid);
      if ($do == 'multi_delete') {
         $ids=$o_report->get_post('Report-content_select','','array|int');
         if (is_array($ids)) {
            foreach ($ids as $no => $rid) {
               $ok=$o_db->delete($dd_folder,$rid);
            }
         }
      }



      $folders=array(); $groups=array();
      $data_folder=$o_db->select($dd_folder);
      foreach ($data_folder as $no => $record) {
         $id          =$record['id'];
         $folders[$id]=$record['name'];
      }
      $data_groups=$o_db->select($dd_groups);
      foreach ($data_groups as $no => $record) {
         $id          =$record['id'];
         $groups[$id] =$record['name'];
      }

      $o_report->_folders =$folders; // speed up
      $o_report->_groups = $groups;   // speed up



      $flds['id']          ='ID';
      $flds['name']        ='Ordner';
      $flds['parent_id']   ='Parent';
      $flds['group_read']  ='Zugriff Lesen';

      $options_rsort['id']     ='ID';
      $options_rsort['name']   ='Ortner';


      $data['dbx_rrows']= 100;
      $data['dbx_rsort']='id';

      $o_report->init('report-folder-flat', 'report-folder-flat');
      $o_report->set_data($data);

      $o_report->set_action('?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=list_folder');
      $o_report->_options_rsort = $options_rsort;
      $o_report->_but_pagination   =9;
      $o_report->set_table_actions(array(
         'select',
         'edit' => array('window' => true),
         'delete',
      ));
      $o_report->set_table_tpl('tpl_row_delete', 'modul|confirm_row_delete_folder');
      $o_report->_confirm_delete='modul|confirm-delete-methode';
      $o_report->_msg_info ='';


      $add['dbx_get']='?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=new_folder';
      $add['label']='Neuer Content Ordner';
      $add['title']='Neuer Content Ordner';
      $o_report->add_obj('add_folder','button-modal1',$add);

      $o_report->add_action('rows_delete'    ,'action_button_delete'    ,'&dbx_run2=multi_delete');
      $o_report->add_action('rows_activate'  ,'action_button_activate'  ,'&dbx_run2=multi_activate');
      $o_report->add_action('rows_deactivate','action_button_deactivate','&dbx_run2=multi_deactivate');

      $work=$o_report->get_fld_val('dbx_run2','','parameter');
      $rid =$o_report->get_fld_val('rid',0,'int|min=0');


      if($o_report->submit()) {
        if(!$o_report->errors()) {      // submit && no errors
           $work=$o_report->get_post('dbx_run2');
           $o_report->_msg_success   = '';
        } else {
           $o_report->_msg_err = 'Prüfen sie bitte ihre Eingaben';
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

      if ($rwhere) {
         $server = $o_db->get_dd_server($dd_folder);
         $needle = $o_db->escape_like($rwhere, $server);
         $rwhere = "name LIKE '$needle%'";
      }
      $o_report->set_report_counts(
         $o_db->count($dd_folder, $rwhere),
         $o_db->count($dd_folder)
      );
      $o_report->_rdata =$o_db->select($dd_folder,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);

      $content=$o_report->run(1,$flds,'table');

   


      return $content;
   } // report_content_flat()


   private function folder_edit($fid=0) {
      $obj= new dbxFolder_edit;
      $content=$obj->run();
      return $content;
   }




   // ----------------------------------------------------

   public function run($work='') {
      if (!$work) $work=dbx()->get_request_var('dbx_run2','flat');
      $content="Modul dbxContent_admin work($work) not defined";

      switch ($work) {
        case 'list_folder'; 
            $content=$this->report_folder_flat();
            break;
        case 'row_edit':
            $content=$this->report_folder_flat();
            break;
        case 'row_delete':
            $content=$this->report_folder_flat();
            break;
        case 'edit_folder':
            $content=$this->folder_edit();
            break;
         case 'new_folder':   
            $content=$this->folder_edit();
            break;
       
      }

      return $content;
   } // run

} // class



?>
