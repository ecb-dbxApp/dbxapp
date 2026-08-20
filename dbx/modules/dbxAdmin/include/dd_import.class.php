<?php
namespace dbx\dbxAdmin;

class dd_import extends \dbxObj {

public $o_tpl;


public function __construct() {
   $this->o_tpl = dbx()->get_system_obj('dbxTPL');
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
   $separator=$this->get_property('separator',';',$section);
   $where    =$this->get_property('dd_where' ,'' ,$section) ;
   $remap    =$this->get_property('dd_remap' ,0  ,$section);

   if (!$path_file)  $path_file  = dbx()->get_file_dir().'sys/csv/'.$dd.'.csv';



   $file          = basename($path_file);
   $data['file']  = dbx()->os_path($path_file);

   dbx()->debug("### IMPORT CSV dd=($dd) file=($path_file)");

   $o_form              = dbx()->get_system_obj('dbxForm');
   $o_form->init($section, 'dbx|form-csv-process');
   $o_form->set_action("?dbx_modul=dbxAdmin&dbx_run1=datadic&dbx_run2=import_csv&dd=$dd");
   $o_form->set_data($data);
   $o_form->_msg_info   = '';
   $o_form->_try_max    = 99999999;
   $o_form->_fld_change_state = 'all';

   $bdata['id']    = 'button_{i}';
   $bdata['label'] = "CSV einlesen ($file)";
   $bdata['sec']   = $timer;

   $o_importer = dbx()->get_system_obj('dbxCSVreader');
   $o_importer->_section=$section;



   $date_time  = date('d-m-Y H:i:s');

   $msg = "CSV Datei ($path_file) einlesen.";

   $submit=0;
   if ($o_form->submit()) $submit=1; 

   dbx()->debug("### IMPORT CSV submit=($submit) dd=($dd) file=($path_file)");
   $submit=1;

   if ($submit) {
 
       $status = $o_importer->init($dd.'_import_csv');
       dbx()->debug("#############  A-Status=($status)");

       if ($status == 'init') {
           $percent = 100;
           $msg     = "CSV Datei ($path_file) einlesen.";
           $o_importer->set_property('path_file' , $path_file);
           $o_importer->set_property('dd'        , $dd);
           $o_importer->set_property('where'     , '');
           $o_importer->set_property('pass'      , 0);
           $o_importer->set_property('owner'     , -1);
           $o_importer->set_property('utf8'      , 1);
           $o_importer->set_property('run_bytes' , 9600);
           $o_importer->set_property('separator' , $separator);
           $o_importer->set_property('remap'     , $remap);
           $o_importer->set_property('where'     , $where);
           dbx()->debug("##init remap Importer ($path_file) sep=($separator)");
       }

       if ($status == 'run')  { 
         $status   = $o_importer->run();
         $path_file= $o_importer->get_property('path_file');
         $filesize = $o_importer->get_property('filesize');
         $filepos  = $o_importer->get_property('filepos');
         $percent  = $o_importer->get_property('percent');
         $querys   = $o_importer->get_property('querys');
         $errors   = $o_importer->get_property('errors');
         $lines    = $o_importer->get_property('lines');
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
       $progress = $this->o_tpl->get_tpl('dbx', 'progressbar-1', $pdata);
       $o_form->add_obj('progress', 'obj-value', $progress);
   }


   $bdata['sec']   = $timer;
   $bdata['class'] = 'hidden';
   $button   = $this->o_tpl->get_tpl('dbx', 'button-submit', $bdata);

   $o_form->add_obj('button'  , 'obj-value', $button);

   if ($status != 'end')  $o_form->add_js_autosubmit('#dbx_form_{i}', $timer);

   return $o_form->run();
}

public function run() {
 
    dbx()->debug("#X# run dd_import");
    return $this->import();
}

}
