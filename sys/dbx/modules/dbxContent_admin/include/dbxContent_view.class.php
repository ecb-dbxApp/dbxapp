<?php
namespace dbx\dbxContent_admin;

dbx_use_sys_class('dbxView');

class dbxContent_view extends \dbxView {

  public function run($rid=0) {
    $oTPL=dbx_get_sys_object('dbxTPL');
    $rid=dbx_get_ModulVar("rid",0,'int');
    $modul="dbxContent_admin";
    $tpl  ='view-content';
    $reps ="i=1&rid=$rid";




    $this->dbxView_init('view-content');
    $this->set_property('sync','rid');
    $this->set_property('rid',$rid);
    $content=$this->dbxView_run();

    //dbx_debug("###VIEW####");
    //return "View-Content";
    return $content;

  } // run()


} // class

?>