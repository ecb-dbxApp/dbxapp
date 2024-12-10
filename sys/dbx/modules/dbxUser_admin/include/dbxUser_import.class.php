<?php
namespace dbx\dbxUser_admin;

Class dbxUser_import extends \dbxObj {

  Public $oTPL;

  public function __construct() {
   $this->oTPL = dbx_get_sys_object('dbxTPL');
  }

  public function import() {
     $content=''; $status='run'; $timer=1; $percent=100; 
     $data['file']='user.csv';
   

      $oForm=dbx_get_sys_object('dbxForm');
      $oForm->init('form-csv-reader');
      $oForm->_action='?dbx_modul=dbxUser_admin&dbx_action=import_user';
      $oForm->_data=$data;
      $oForm->_fld_change_state='all';
      $oForm->_msg_info='';

      $oForm->_try_max=99999999;
   
      $bdata['id']   ='button_{i}';
      $bdata['label']='User einlesen';
      $bdata['sec']  = $timer;

      $oImporter=dbx_get_sys_object('dbxCSVreader');
      $progress =$this->oTPL->get_tpl('dbx','progressbar-1');
      $button   =$this->oTPL->get_tpl('dbx','button-submit',$bdata);
      $date_time=date('d-m-Y H:i:s');


      $msg='CSV Datei einlesen.';

      if($oForm->submit()) {
         if(!$oForm->errors()) {
            $file =dbx_get_file_dir().'/myBefund/ldt-in/user.csv';

            if (file_exists($file)) {

               $status=$oImporter->init('import_user_csv');
               if ($status=='init') {               
                  $oImporter->set_property('filename',$file);
                  $oImporter->set_property('dd'   ,'dbx_user');
                  $oImporter->set_property('where','userid = {userid}');
                  $oImporter->set_property('pass' , 1); // convert
                  $oImporter->set_property('owner',-1); // admin
                  $oImporter->set_property('utf8' , 1); // convert 2 utf8
                  $oImporter->set_property('run_bytes',9600); // max Line length
                  $oImporter->set_property('seperator',';');
               }
               $msg="Die CSV Datei ($file) wird eingelesen ($status)."; 

               $status=$oImporter->run();   
               $filesize=$oImporter->get_property('filesize');
               $percent =$oImporter->get_property('percent');
               $querys  =$oImporter->get_property('querys');
               $errors  =$oImporter->get_property('errors');
               $lines   =$oImporter->get_property('lines');


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
   $oForm->add_obj('progress','obj-value',$progress,$pdata);
   $oForm->add_obj('button'  ,'obj-value',$button  ,$bdata);
   if ($status=='run') $oForm->add_js_autosubmit('#dbx_form_{i}',$timer);

   $content=$oForm->run();      
   return $content;

}  // import()




   public function run() {
      $content=$this->import();
      return $content;
   }


}

?>
