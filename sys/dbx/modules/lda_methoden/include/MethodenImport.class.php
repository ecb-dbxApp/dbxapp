<?php
namespace dbx\myOrderLDT;

Class MethodenImport extends \dbxObj {

  Public $oTPL;

  public function __construct() {
   $this->oTPL = dbx_get_sys_object('dbxTPL');
  }

  public function import() {
     $content=''; $stepper=2; $status=0; $run=1; $timer=200; // fast submit
     $data['file']='methoden.csv';
     $uid=dbx_get_CurrentUser();
     if ($stepper >=2) {

       $oForm=dbx_get_sys_object('dbxForm');
       $oForm->init('form-csv-reader');
       $oForm->_action='?dbx_modul=myOrderLDT&dbx_action=import_methoden';
       $oForm->_data=$data;
       $oForm->_fld_change_state='all';
       $oForm->_msg_info='';

       $oForm->_try_max=99999999;
       $pdata['msg']   = 'Warte auf neue methoden.csv Datei';
       $pdata['width'] = 100;

       $bdata['id']   ='button_{i}';
       $bdata['label']='LDA Methoden einlesen';
       $bdata['sec']  =($timer / 1000);


       $progress=$this->oTPL->get_tpl('dbx','progressbar-1');
       $button  =$this->oTPL->get_tpl('dbx','button-submit',$bdata);
       $date_time=date('d-m-Y H:i:s');

       if($oForm->submit() || $uid== -3) {
          if(!$oForm->errors()) {
             $file =dbx_get_file_dir().'/myBefund/ldt-in/methoden.csv';

             if (file_exists($file)) {
               $pdata['msg'] = 'Die CSV Datei ('.$file.') wird eingelesen.';
               $oImporter =dbx_get_sys_object('dbxCSVreader');
               $oImporter->set_property('filename',$file);
               $oImporter->set_property('dbtab','lda_methoden');
               $oImporter->set_property('where','poskarte = {poskarte}');
               $oImporter->set_property('pass' , 1); // convert
               $oImporter->set_property('owner',-1); // admin
               $oImporter->set_property('utf8' , 1); // convert 2 utf8
               $oImporter->set_property('run_bytes',9600); // max Line length

               $status=$oImporter->run();

               if ($status <= 0)  { 
                  $pdata['msg'] ='Ein Fehler ist aufgetreten';
                  $run=0;
               }   

               $filesize=$oImporter->get_property('filesize');
               $done    =$oImporter->get_property('done');
               $percent =$oImporter->get_property('percent');
               $querys  =$oImporter->get_property('querys');
               $errors  =$oImporter->get_property('errors');
               $lines   =$oImporter->get_property('lines');


               $msg="Querys=($querys) ($percent %)";

               if ($status == 1 ) {
                 $pdata['msg']=$msg; 
               }
               if ($status == 2) { // finisch import and process data
                  $run=0;
                  $button='';
                  $progress=$this->oTPL->get_tpl('dbx','alert-info',"msg=Es wurden ($querys) Methoden eingelesen ($date_time).");
                  if (file_exists($file)) unlink($file);

                  if ($errors) {
                    $run=0; 
                    $pdata['msg']= "Es wurden ($querys) Methoden eingelesen. Es sind ($errors) Fehler aufgetreten";
                  }
               }
            } else {
               $run=0;
               $button  =''; 
               $progress=$this->oTPL->get_tpl('dbx','alert-info',"msg=Die CSV Datei (methoden.csv) ist nicht vorhanden ($date_time).");
            }
          } else {
            $pdata['msg'] ='Ein Fehler ist aufgetreten';
            $run=0;     
          }
      } // submit
      
      $bdata['sec']  =($timer);
      $oForm->add_obj('progress','obj-value',$progress,$pdata);
      $oForm->add_obj('button'  ,'obj-value',$button  ,$bdata);
      if ($run) $oForm->add_js_autosubmit('#dbx_form_{i}',$timer);
   
      $content=$oForm->run();    

    }
    return $content;
  }  // import()




   public function run() {
      $content=$this->import();
      return $content;
   }


}

?>
