<?php
namespace dbx\myBefund_admin;


class myImport {

  Public $oTPL;

  public function __construct() {
   $this->oTPL = dbx_get_sys_object('dbxTPL');
  }



  private function ImportLDT($path_file) {
     $oImporter=dbx_get_Modul_include_object('ImportLDT');
     $content=$oImporter->run($path_file);
     return $content;
  }

  private function download_befunde() {
     $oImporter=dbx_get_Modul_include_object('DownloadLDT');
     $content=$oImporter->run();
     return $content;    
  }

  public function import_befund() {
     $content=''; $data=array();
     $count  =0;
     $msg    ='Warten ...';
     $percent=100; 
     $timer  =100;
     
     $oForm=dbx_get_sys_object('dbxForm');
     $oForm->init('form-import-ldt');
     $oForm->_data    =  $data;
     $oForm->_action  = '?dbx_modul=myBefund_admin&dbx_action=import&dbx_work=import_befund';
     $oForm->_msg_info= '';
     $oForm->_msg_success='';

     $label_button =" LDA LDT-Befunde";
     $progress     =$this->oTPL->get_tpl('dbx','progressbar-1');

     $oWalker=dbx_get_sys_object('dbxFileWalker');
     $oWalker->init('import_ldt'); 


     $status =$oWalker->run();
     //dbx_debug("#Walker Status 1=($status)");


     if ($status == 'init') {
      $count=$this->download_befunde();
      $path =dbx_get_file_dir().'myBefund/ldt-in/';
      $path= dbx_os_path_file($path);
      $oWalker->set_property('path'   ,$path);
      $oWalker->set_property('archiv' ,$path.'.done/');
      $oWalker->set_property('date'   ,1);
      $oWalker->set_property('ext'    ,'.ldt');
      $oWalker->create_que();
      $status='run';
     }

     //$status =$oWalker->run();
     //dbx_debug("#Walker Status 2=($status)");
     $pos    =$oWalker->get_property('pos'    ,0);
     $count  =$oWalker->get_property('count'  ,0);
     $percent=$oWalker->get_property('percent',0);
     $path   =$oWalker->get_property('path');
     $file   =$oWalker->get_property('file');

     dbx_debug("LDT Importer Count=($count) Pos=($pos) count=($count) Perz($percent) Path=($path) file=($file)");


     if ($count <= 0) $status='end'; 




     if ($status == 'run' && $file) { // continue
       $ok=0;
       $label_button.=" LDT-Datei=($file)";
       $path_file=$path.$file;
       dbx_debug("##IMPORT## =($file) ($path_file) ");
       if ($path > ' ' && $file > ' ') $ok=$this->ImportLDT($path_file);
       dbx_debug("##IMPORT## = OK($ok)");
       $oWalker->archiv();
     }

     //$status =$oWalker->get_property('status' ,0);
     

     if ($status=='init') {
      $msg="Es sind ($count) Status=($status) LDT Befund Dateien zum Einlesen vorhanden. ($percent) %";
      if (!$percent) $percent=88;
     }

     if ($status=='run') {
      $msg= "Von ($count) Status=($status) LDT Befund Dateien ($pos) eingelesen. ($percent) %";
      if (!$percent) { 
         $percent=100;
         $msg="Es sind ($count) LDT Befund Dateien zum Einlesen vorhanden.";
      }   
     }

     if ($status == 'end') { // finisch import
       $progress='';
       $percent=90;
       $nowx=date('d.m.Y H:i:s');
       $bdata['msg']  = "<center>Es wurden ($count) LDT-Dateien am ($nowx) eingelesen.</center>";
       
     }

 
    $bdata['id']      = 'button_{i}';
    $bdata['sec']     = $timer;
    $bdata['label']   = $label_button;
    $bdata['submit']  = '#dbx_form_{i}';
    $bdata['redirect']= 0;

    $pdata['msg']   = $msg;
    $pdata['width'] = $percent;
    $pdata['value'] = $percent;

    

    if ($status != 'end')                $button  =$this->oTPL->get_tpl('dbx','button-submit');
    if ($status != 'end')                $button  =$this->oTPL->get_tpl('dbx','button-submit');
    if ($status == 'end' && $count == 0) $button  =$this->oTPL->get_tpl('dbx','form-alert-info');
    if ($status == 'end' && $count >= 1) $button  =$this->oTPL->get_tpl('dbx','form-alert-success');

    
    $oForm->add_obj('progress','obj-value',$progress,$pdata);
    $oForm->add_obj('button'  ,'obj-value',$button  ,$bdata);

    dbx_debug("#myImoort Status=($status)");
    if ($status != 'end' && $count > 0) $oForm->add_js_autosubmit('dbx_form_{i}');

    $content=$oForm->run();
    
    //if ($status == 'end') $content.=dbx_redirect('?home',1,4000); 
    
    return $content;
  }


  public function run() {
    $modul=dbx_get_SysVar('dbx_activ_modul');
    $work =dbx_get_ModulVar('dbx_work');

    switch ($work) {
       

      case 'import_befund':
        $content=$this->import_befund();
      break;

      default:
        $oTPL=dbx_get_sys_object('dbxTPL');
        $msg['msg']="Modul=($modul) Work=($work) is undef.";
        $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

    } // switch()


    return $content;


  }

} // class
