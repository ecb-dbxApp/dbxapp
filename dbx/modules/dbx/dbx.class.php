<?php
namespace dbx\dbx;

class dbx {

  public function run() {
     $uid   =dbx()->user();
     $mid   =dbx()->get_system_var('dbx_modul_id');
     $modul =dbx()->get_system_var('dbx_modul');

     $run1=dbx()->get_modul_var('dbx_run1','','parameter');
     $run2=dbx()->get_modul_var('dbx_run2','','parameter');

     $oTPL=dbx()->get_system_obj('dbxTPL');
     $msg['msg']="Modul=($modul) ist ein dbx Service Modul ohne direkten Aufruf.";
     $content=$oTPL->get_tpl('dbx|alert-warning',$msg);


     return $content;
   }


} // class

?>
