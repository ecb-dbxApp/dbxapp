<?php
namespace dbx\myOrderLDT;

Class mySummary {
  
   public function summary() {
      $today  = date('Y-m-d', time()); 
      $content=''; $last_date=''; $gesendet=0; $count=0;

      //dbx_debug("#SUMMERY#");

      $oTPL=dbx_get_sys_object('dbxTPL'); 
      $oDB =dbx_get_sys_object('dbxDB');
      $dd  ='my_order'; 
      $count   =$oDB->count($dd);

      $today_recs    =$oDB->count($dd,"datum = '$today'");
      $future_recs   =$oDB->count($dd,"datum > '$today'");
      $past_recs     =$oDB->count($dd,"datum < '$today'");
      $today_to_send =$oDB->count($dd,"datum = '$today' and gesendet < '1900-01-01' ");
      $today_is_send =$oDB->count($dd,"datum = '$today' and gesendet > '1900-01-01' ");


      if ($today_recs) {
         $msg['msg']="Es sind (<b>$today_recs</b>) Anforderungen von <b>Heute</b> vorhanden.";
         $content=$oTPL->get_tpl('dbx','alert-success',$msg);
      } else {
         $msg['msg']="Es sind (<b>$today_recs</b>) Anforderungen von <b>Heute</b> vorhanden.";
         $content=$oTPL->get_tpl('dbx','alert-warning',$msg);
      }


      if ($today_to_send) {
         $msg['msg']="<b>Heute</b> wurden von (<b>$today_recs</b>) Anforderungen (<b>$today_to_send</b>) noch nicht gesendet.";
         $content.=$oTPL->get_tpl('dbx','alert-warning',$msg);
      }

      if ($today_is_send) {
         $msg['msg']="<b>Heute</b> wurden von (<b>$today_recs</b>) Anforderungen (<b>$today_is_send</b>) gesendet.";
         $content.=$oTPL->get_tpl('dbx','alert-info',$msg);
      }




      $todo=$oDB->count('my_order',"datum = '$today' and pat > 0 and gebdat > '1900-01-01' and anforderungen > ' ' and anforderungen <> 'a:0:{}' and gesendet <= '1900-01-01' ");
      $bdata['i']       = dbx_get_next_i();
      $bdata['dbx_get'] =  '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=send_order';
      $bdata['label']   = "Labor Anforderungen senden ($todo)";
      $action_button=$oTPL->get_tpl('dbx','button-modal1',$bdata);

      $content.=$action_button;
      return $content;
   }




   public function run($action='summary') {
      $content=$this->summary();
      return $content;
   } // run

} // class

