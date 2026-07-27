<?php
namespace dbx\dbxAdmin;

class dd_import extends \dbxObj {

public $oTPL;


public function __construct() {
   $this->oTPL = dbx()->get_system_obj('dbxTPL');
}

public function import() {
   $content  = ''; 
   $status   = 'run'; 
   $timer    = 100; 
   $percent  = 100;
   $section  = $this->_section;
   dbx()->debug("run import dd_import section=($section)");


   $dd       =$this->get_property('dd'       ,0  ,$section) ;  // #todo $section
   $path_file=$this->get_property('path_file','' ,$section) ;
   $seperator=$this->get_property('seperator',';',$section);
   $where    =$this->get_property('dd_where' ,'' ,$section) ;
   $remap    =$this->get_property('dd_remap' ,0  ,$section);

   if (!$path_file)  $path_file  = dbx()->get_file_dir().'sys/csv/'.$dd.'.csv';



   $file          = basename($path_file);
   $data['file']  = dbx()->os_path($path_file);

   dbx()->debug("### IMPORT CSV dd=($dd) file=($path_file)");

   $oForm              = dbx()->get_system_obj('dbxForm');
   $oForm->init($section, 'form-csv-reader');
   $oForm->_action     = "?dbx_modul=dbxAdmin&dbx_run1=datadic&dbx_run2=import_csv&dd=$dd";
   $oForm->_data       = $data;
   $oForm->_msg_info   = '';
   $oForm->_try_max    = 99999999;
   $oForm->_fld_change_state = 'all';

   $bdata['id']    = 'button_{i}';
   $bdata['label'] = "CSV einlesen ($file)";
   $bdata['sec']   = $timer;

   $oImporter = dbx()->get_system_obj('dbxCSVreader');
   $oImporter->_section=$section;



   $date_time  = date('d-m-Y H:i:s');

   $msg = "CSV Datei ($path_file) einlesen.";

   $submit=0;
   if ($oForm->submit()) $submit=1; 

   dbx()->debug("### IMPORT CSV submit=($submit) dd=($dd) file=($path_file)");
   $submit=1;

   if ($submit) {
 
       $status = $oImporter->init($dd.'_import_csv');
       dbx()->debug("#############  A-Status=($status)");

       if ($status == 'init') {
           $percent = 100;
           $msg     = "CSV Datei ($path_file) einlesen.";
           $oImporter->set_property('path_file' , $path_file);
           $oImporter->set_property('dd'        , $dd);
           $oImporter->set_property('where'     , '');
           $oImporter->set_property('pass'      , 0);
           $oImporter->set_property('owner'     , -1);
           $oImporter->set_property('utf8'      , 1);
           $oImporter->set_property('run_bytes' , 9600);
           $oImporter->set_property('seperator' , $seperator);
           $oImporter->set_property('remap'     , $remap);
           $oImporter->set_property('where'     , $where);
           dbx()->debug("##init remap Importer ($path_file) sep=($seperator)");
       }

       if ($status == 'run')  { 
         $status   = $oImporter->run();
         $path_file= $oImporter->get_property('path_file');
         $filesize = $oImporter->get_property('filesize');
         $filepos  = $oImporter->get_property('filepos');
         $percent  = $oImporter->get_property('percent');
         $querys   = $oImporter->get_property('querys');
         $errors   = $oImporter->get_property('errors');
         $lines    = $oImporter->get_property('lines');
         $msg = "Datensätze=($querys) ($percent %) status=($status)";
         dbx()->debug("File=($path_file) filesize=($filesize) FilePos=($filepos) Querys=($querys) errors=($errors) lines=($lines)");

       }
       if ($status == 'end') {
           dbx()->debug("END CSV ($dd)");
           $msg = "Es wurden ($querys) Datensätze eingelesen ($date_time)";
           if ($errors)  $msg .= " Es sind ($errors) Fehler aufgetreten.";
       }

       $pdata['msg']   = $msg;
       $pdata['value'] = $percent;
       $pdata['width'] = $percent;
       $progress = $this->oTPL->get_tpl('dbx', 'progressbar-1', $pdata);
       $oForm->add_obj('progress', 'obj-value', $progress);
   }


   $bdata['sec']   = $timer;
   $bdata['class'] = 'hidden';
   $button   = $this->oTPL->get_tpl('dbx', 'button-submit', $bdata);

   $oForm->add_obj('button'  , 'obj-value', $button);

   if ($status != 'end')  $oForm->add_js_autosubmit('#dbx_form_{i}', $timer);

   return $oForm->run();
}

public function run() {
 
    dbx()->debug("#X# run dd_import");
    return $this->import();
}

}
