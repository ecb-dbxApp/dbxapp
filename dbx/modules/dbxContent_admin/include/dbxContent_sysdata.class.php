<?php
namespace dbx\dbxContent_admin;
//dbx()->get_system_obj('dbxForm','use');

use dbx\dbxContent\dbxContent_permalink;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
require_once __DIR__ . '/dbxContentSelectOptions.class.php';



class dbxContent_sysdata extends \dbxObj {


  Public $o_validator;
  Public $o_tpl;


  public function __construct() {
     $this->o_validator=dbx()->get_system_obj('dbxValidator');
     $this->o_tpl      =dbx()->get_system_obj('dbxTPL');
  }


  function get_select_tpl($modul,$pfx='') {
     $select_data=array();
     $folder=$this->o_tpl->get_tpl_dir($modul).'htm';
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







  public function run() {

    //return "ich bin die sysdaten";
     $content=''; $ok=false;
     $uid = dbx()->user();
     $rid = dbx()->get_modul_var('rid',0,'int');
     $view= dbx()->get_modul_var('dbx_view');
     //dbx_debug("EDIT-CID=($rid) uid=($uid)");

     if ($uid) {
       //dbx()->set_system_var('dbx_page','admin');
       $db  = dbx()->get_system_obj('dbxDB');
       $lng = dbx()->get_system_var('dbx_lng','de');

       $tab_content = dbx()->lng_name('content', $lng);
       $tab_folder = $tab_content.'_folder';


       $data         =$db->select1($tab_content,$rid);
       $data_folder  =$db->select($tab_folder);
       $data_folder  =dbxContentSelectOptions::hierarchy((array)$data_folder, 'id', 'name', 'parent_id');
       $data_template=$this->get_select_tpl('dbxContent','c-');

       $options_groups=array();
       $user_groups=$db->select('dbxUser_groups','active = 1','*','name');
       foreach ($user_groups as $no => $record) {
         $id    =$record['name'];
         $group =$record['description'];
         $options_groups[$id]=$group;
       }
       $options_groups['zzzz']='<hr>'; // no grops

       $o_form=dbx()->get_system_obj('dbxForm');
       $o_form->init('dbxContent_edit','form-sysdata');
       $o_form->set_data($data);
       $o_form->set_action("?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=edit_sysdata&dbx_view=$view&rid=$rid");
       $o_form->_msg_info   ='Content Sytemdaten bearbeiten';
       $o_form->_msg_success='Content gespeichert';
       //$options_select=$oForm->get_select_data('group_read');

       //add_fld($name,$tpl='dd:',$data='dd:',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='',$remap='') { //#

       $o_form->add_fld('id'        ,'text-label');                   //#+

       $o_form->add_fld('title'     ,'text-label');
       $o_form->add_fld('sorter'    ,'text-label');
       $o_form->add_fld('keywords'  ,'text-label');
       $o_form->add_fld('permalink' ,'text-label');
       $o_form->add_fld('activ'     ,'checkbox-label');
       $o_form->add_fld('hits'      ,'text-label');


       $o_form->add_fld('folder'    ,'select-single-label',options: $data_folder);
       $o_form->add_fld('template'  ,'select-single-label',options: $data_template);
       $o_form->add_fld('group_read','multi-select'       ,options: $options_groups, rules: '*'); //,$options_select);
   

       $observer='obs_content_rid';
       $observ['name']   =  'content_rid';
       $observ['observ'] =  'content_rid';
       $observ['value']  =  $rid;
       $observ['old']    =  $rid;
       $o_form->add_obj($observer,'dbx|observe',$observ);
       $o_form->add_js_call('group_read','multiselect2');

       //$oForm->add_js_observe($observer,'dbx_form_{i}',1500);

       if($o_form->submit()) {
          $submitted_permalink = trim((string)($_POST['permalink'] ?? ''));
          if ($submitted_permalink !== ''
             && dbxContent_permalink::is_valid($submitted_permalink)
             && dbxContent_permalink::exists($db, $tab_content, $submitted_permalink, (int)$rid)) {
             $o_form->add_fld_error('permalink', 'Dieser Permalink wird bereits von einer anderen Seite verwendet.');
          }
          //dbx_debug("#FORM-SUBMIT#");
         if(!$o_form->errors()) {      // submit && no errors // we ignore warnings
            //dbx_debug("#FORM-No-Errors");
            $change=$o_form->changed();
             $post_permalink = trim((string)($_POST['permalink'] ?? ($data['permalink'] ?? '')));
            $post_folder = (int)($_POST['folder'] ?? ($data['folder'] ?? 0));
            $post_title = $_POST['title'] ?? ($data['title'] ?? '');
            $post_values = array();
            if ($post_permalink === '') {
               $post_values['permalink'] = dbxContent_permalink::build($db, $tab_folder, $post_folder, $post_title, (int)$rid);
               $o_form->set_post('permalink', $post_values['permalink']);
               $change = 1;
            }
            if ($change) {
              //dbx_debug("#Form-Change");
              $ok=$o_form->save_post($tab_content,$rid,$post_values);
              if ( $ok) $o_form->_msg_success   = 'Daten gespeichert';
              if (!$ok) $o_form->_msg_success   = 'Daten konnten nicht gespeichert werden';
            } else {
              //dbx_debug("#Form-NO-Change");
              $o_form->_msg_success   = 'Keine Änderung';
            }
         }
         if ($o_form->errors()) {
            $flds='';
            $errors=$o_form->_errors;
            foreach ($errors as $fld => $msg) {
                $flds.=$fld.' ';
              // code...
            }
            $o_form->_msg_error = "Prüfen sie bitte ihre Eingaben ($flds)";

         }
       }

       $rid=$o_form->get_data('id'); // Value nach dem speichern
       $o_form->add_obj('obs_rid','dbx|observe',"name=content_rid&value=$rid"); // wird von avatar upload überwacht (observed)

       $content= $o_form->run();

     }

     return $content;
  } // run()


} // class

?>
