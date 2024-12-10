<?php
namespace dbx\dbxTest;

class dbxTest_test {

  private function test() { 
    return "test inc call";
  }

  public function run($action='') {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');

     $content="";

     switch ($action) {
       case 'test':
           $content=$this->test();
           break;

       default:
         $content="<div class='alert alert-warning' role='alert'>Modul=($modul) Inc=(dbxTest_test) Action=($action) is undef.</div>";
     } // switch
     return $content;
  } // run()

} // class

?>