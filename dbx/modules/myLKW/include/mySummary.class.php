<?php
namespace dbx\myLKW;


Class mySummary {

  public function summary_lkw() {
    $data['count_all']=180;
    $o_tpl=dbx()->get_system_obj('dbxTPL');
    $content=$o_tpl->get_tpl('modul','summary_lkw',$data);
    return $content;


  }
  

  public function summary_order() {
    $data['count_all']=170;
    $o_tpl=dbx()->get_system_obj('dbxTPL');
    $content=$o_tpl->get_tpl('modul','summary_order',$data);
    return $content;


  }

 
  public function lkw() {
    $o_tpl=dbx()->get_system_obj('dbxTPL');
    $msg['msg']="<h4>LKW-Summery</h4>".$this->summary_lkw();
    $content=$o_tpl->get_tpl('dbx','alert-info',$msg);
    return $content;


  }
 
  
  public function order() {
    $o_tpl=dbx()->get_system_obj('dbxTPL');
    $msg['msg']="<h4>Order-Summery</h4>".$this->summary_order();
    $content=$o_tpl->get_tpl('dbx','alert-info',$msg);
    return $content;
  }
 

  // =============================================

   public function run() {
    $modul=dbx()->get_system_var('dbx_activ_modul');
    $work =dbx()->get_modul_var('dbx_run2');
    $content="";
    
    switch ($work) {
 

        case 'lkw':
          $content=$this->lkw();     
        break; 

        case 'order':
          $content=$this->order();     
        break; 


       default:
        $o_tpl=dbx()->get_system_obj('dbxTPL');
        $msg['msg']="Modul=($modul) Work=($work) is undef.";
        $content=$o_tpl->get_tpl('dbx','alert-warning',$msg);

     } // switch()


     return $content;
   } // run

} // class


