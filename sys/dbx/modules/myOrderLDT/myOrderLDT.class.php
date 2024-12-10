<?php
namespace dbx\myOrderLDT;
 
class myOrderLDT {

  

  public function run() {
 
     $modul =dbx_get_SysVar('dbx_activ_modul');
     $action=dbx_get_ModulVar('dbx_action');

     switch ($action) {

        case 'import_pat':
          //return "<hr>";
          $oTPL=dbx_get_sys_object('dbxTPL');
          $data['width']=98;
          $content=$oTPL->get_tpl('dbx','progressbar-2',$data);
          

          //läuft jetzt über den Client !
          //dbx_debug("Session:",$sesrec);
          //return "Praxis=($praxis) Session=($sessid) Self=($self)";
          //$obj=dbx_get_Modul_include_object('importPat');
          //$content=$obj->run(); 
        break; 

 
        case 'summary':
          dbx_set_Remember('dbx_load_pat',1);
          $obj=dbx_get_Modul_include_object('mySummary');
          $content=$obj->run();     
        break; 

        case 'order':
          dbx_set_Remember('dbx_load_pat',0);
          $obj=dbx_get_Modul_include_object('myOrder');
          $content=$obj->run();    
          dbx_set_SysVar('dbx_page','order');
        break; 

        case 'profil':
          dbx_set_SysVar('dbx_title','Profile' );
          dbx_set_Remember('dbx_load_pat',0);
          $obj=dbx_get_Modul_include_object('myProfil');
          $content=$obj->run();    
          dbx_set_SysVar('dbx_page','default');
        break; 



       default:
        dbx_set_Remember('dbx_load_pat',1);
        $oTPL=dbx_get_sys_object('dbxTPL');
        $msg['msg']="Modul=($modul) Action=($action) is undef.";
        $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // switch()
     
     return $content;
   } 
   
   
} // class

?>