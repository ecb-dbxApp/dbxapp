<?php
namespace dbx\dbxApi;

class dbxApi_call {

  public function run($mode) {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');
     $call  =dbx_get_ModulVar('call','','parameter');
     $mod   =dbx_get_ModulVar('mod' ,'','parameter');
     $key   =dbx_get_ModulVar('key' ,'','parameter');
     $act   =dbx_get_ModulVar('act' ,'','parameter');
     $work  =dbx_get_ModulVar('work','','parameter');

     $content=dbx_add_modul($mod,$act,$work);
     
     return $content;
  } // run()

} // class

?>
