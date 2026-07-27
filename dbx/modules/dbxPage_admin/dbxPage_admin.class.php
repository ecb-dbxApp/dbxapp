<?php
namespace dbx\dbxPage_admin;

class dbxPage_admin {


  public function run() {
     $uid   =dbx()->user();
     $mid   =dbx()->get_system_var('dbx_modul_id');
     $modul =dbx()->get_system_var('dbx_modul');

     $action=dbx()->get_modul_var('dbx_run1');

     switch ($action) {
       case 'list':
           $obj=dbx()->get_include_obj('dbxPage_list');
           $content=$obj->run();
           break;

       case 'edit':
           $obj=dbx()->get_include_obj('dbxPage_edit');
           $content=$obj->run();
           break;

       default:
         $oTPL=dbx()->get_system_obj('dbxTPL');
         $msg['msg']="Modul=($modul) Action=($action) is undef.";
         $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // sqitch()

     return $content;
   }


} // class

?>
