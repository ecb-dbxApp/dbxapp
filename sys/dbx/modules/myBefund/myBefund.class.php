<?php
namespace dbx\myBefund;

class myBefund {

  public function run() {
     $content="";
     $modul =dbx_get_SysVar('dbx_activ_modul');
     $action=dbx_get_ModulVar('dbx_action');
     //if ($action=='list' && $work == 'row_show' && $rid) $action='analys'; // redirect ! 
     dbx_set_SysVar('dbx_page','befunde');


     switch ($action) { 

       case 'order':
          $content="Geister Aufruf";
          dbx_debug("GET=",$_GET);
          dbx_debug("POST=",$_POST);
          dbx_debug("Session=",$_SESSION);
       break;

       case 'import':
          dbx_set_Remember('set_date','last');
          $content='[modul=myBefund_admin]dbx_action=import&dbx_work=import_befund[/modul]';
          //$content=$obj->run();
       break; 

       case 'befund':
           $obj=dbx_get_Modul_include_object('myBefunde');
           $content=$obj->run();
        break;

       case 'ldt_analys':
           $obj=dbx_get_Modul_include_object('ExportLDT');
           $content=$obj->run();
       break;  

       case 'analys':
           $obj=dbx_get_Modul_include_object('myAnalysen');
           $content=$obj->run();
        break;

       case 'summary':
          $obj=dbx_get_Modul_include_object('mySummary');
          $content=$obj->run();     
        break;    

       default:
         $oTPL=dbx_get_sys_object('dbxTPL');
         $msg['msg']="Modul=($modul) Action=($action) is undef.";
         $content=$oTPL->get_tpl('dbx','alert-warning',$msg);




     } // switch
     $content=str_replace('#BR#','<br>',$content);
     return $content;
  } // run()

} // class