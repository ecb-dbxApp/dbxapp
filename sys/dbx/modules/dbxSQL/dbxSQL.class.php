<?php
namespace dbx\dbxSQL;
 
class dbxSQL {

  private function test() {
    return "test1 call";
  }

  public function run() {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');

     $action=dbx_get_ModulVar('dbx_action');

     switch ($action) {
       case 'test1':
           $content=$this->test();
           break;

       case 'test2':
           $obj=dbx_get_Modul_include_object('dbxSQL_test');
           $content=$obj->run('test');
           break;

      case 'create_by_dd':
        $obj=dbx_get_Modul_include_object('create_by_dd');
        $content=$obj->run();
        break;
       


       default:
         $oTPL=dbx_get_sys_object('dbxTPL');
         $msg['msg']="Modul=($modul) Action=($action) is undef.";
         $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // switch()
     
     return $content;
   } 
   
   
} // class

?>