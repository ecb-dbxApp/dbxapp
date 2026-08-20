<?php
namespace dbx\dbxUser_admin;

Class dbxUser_import extends \dbxObj {

  Public $o_tpl;

  public function __construct() {
   $this->o_tpl = dbx()->get_system_obj('dbxTPL');
  }

  public function import() {
     $content=''; $status='run'; $timer=1; $percent=100; 
     $data['file']='user.csv';
   

      $o_form=dbx()->get_system_obj('dbxForm');
      $o_form->init('form-csv-reader', 'dbx|form-csv-process');
      $o_form->set_action('?dbx_modul=dbxUser_admin&dbx_run1=import_user');
      $o_form->set_data($data);
      $o_form->_fld_change_state='all';
      $o_form->_msg_info='';

      $o_form->_try_max=99999999;
   
      $bdata['id']   ='button_{i}';
      $bdata['label']='User einlesen';
      $bdata['sec']  = $timer;

      $o_importer=dbx()->get_system_obj('dbxCSVreader');
      $progress =$this->o_tpl->get_tpl('dbx','progressbar-1');
      $button   =$this->o_tpl->get_tpl('dbx','button-submit',$bdata);
      $date_time=date('d-m-Y H:i:s');


      $msg='CSV Datei einlesen.';

      if($o_form->submit()) {
         if(!$o_form->errors()) {
            $file =dbx()->get_file_dir().'/myBefund/ldt-in/user.csv';

            if (file_exists($file)) {

               $status=$o_importer->init('import_user_csv');
               if ($status=='init') {               
                  $o_importer->set_property('filename',$file);
                  $o_importer->set_property('dd'   ,'dbx_user');
                  $o_importer->set_property('where','id = {id}');
                  $o_importer->set_property('pass' , 1); // convert
                  $o_importer->set_property('owner',-1); // admin
                  $o_importer->set_property('utf8' , 1); // convert 2 utf8
                  $o_importer->set_property('run_bytes',9600); // max Line length
                  $o_importer->set_property('separator',';');
               }
               $msg="Die CSV Datei ($file) wird eingelesen ($status)."; 

               $status=$o_importer->run();   
               $filesize=$o_importer->get_property('filesize');
               $percent =$o_importer->get_property('percent');
               $querys  =$o_importer->get_property('querys');
               $errors  =$o_importer->get_property('errors');
               $lines   =$o_importer->get_property('lines');


               $msg="Querys=($querys) ($percent %) status=($status)"; 
               if ($status == 'end' && !$errors)  $msg="Es wurden ($querys) Benutzer eingelesen ($date_time)  status=($status)";
               if ($status == 'end' &&  $errors)  $msg="Es wurden ($querys) Benutzer eingelesen ($date_time) Es sind ($errors) Fehler aufgetreten. status=($status)";
   
            } else {
               $status='end';
               $msg="Die CSV Datei (user.csv) ist nicht vorhanden ($date_time).";
            }
         } else {
            $msg='Ein Fehler ist aufgetreten';    
         }
   } // submit
   $pdata['msg']  =$msg;
   $pdata['value']=$percent;
   $pdata['width']=$percent;
   $bdata['sec']  =$timer;
   //if ($timer)
   $o_form->add_obj('progress','obj-value',$progress,$pdata);
   $o_form->add_obj('button'  ,'obj-value',$button  ,$bdata);
   if ($status=='run') $o_form->add_js_autosubmit('#dbx_form_{i}',$timer);

   $content=$o_form->run();      
   return $content;

}  // import()




   public function run() {
      $content=$this->import();
      return $content;
   }


}

?>
