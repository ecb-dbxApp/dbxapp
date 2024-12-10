<?php
namespace dbx\myBefund;

Class mySummary {
  
  public function summary() {
    $today  = date('Y-m-d', time()); 
    $content='';
   
    $oTPL=dbx_get_sys_object('dbxTPL'); 
    $oDB =dbx_get_sys_object('dbxDB');
 
    $count   =$oDB->count('dbx_my_befund');
    $last_rec=$oDB->select1('dbx_my_befund','id > 0','datum','datum','DESC');

    //dbx_debug ("#mySummery Befunde coiunt=($count) LastRec=",$last_rec);

    if (is_array($last_rec)) {
       $last_date=$last_rec['datum'];
       $count=$oDB->count('dbx_my_befund',"datum == '$last_date'");

       if ($last_date==$today) {
        $msg['msg']="Es sind (<b>$count</b>) Befunde von <b>Heute</b> vorhanden.";
        $content=$oTPL->get_tpl('dbx','alert-success',$msg);
       } else {
        $msg['msg']="Es sind (<b>keine</b>) Befunde von <b>Heute</b> vorhanden.";
        $content.=$oTPL->get_tpl('dbx','alert-warning',$msg);
       }

        $last_date_user=dbx_get_webDate($last_date);
        $msg['msg']="Es sind (<b>$count</b>) Befunde vom <b>$last_date_user</b> vorhanden.";
        $content.=$oTPL->get_tpl('dbx','alert-primary',$msg);
       
        
 //       $date = dbx_get_Remember('last_import_befunde_date' ,'?');    
 //       $count= dbx_get_Remember('last_import_befunde_count','0');              
        //if ($date != '?') $date=dbx_get_webDate($date);

 //       if ($date == $today) {
 //        $msg['msg']="Es wurden (<b>$count</b>) Befunde <b>Heute</b> eingelesen.";
 //        $content.=$oTPL->get_tpl('dbx','alert-success',$msg);
 //       } else {
 //        $msg['msg']="Es wurden (<b>$count</b>) Befunde am <b>$date</b> eingelesen.";
 //        $content.=$oTPL->get_tpl('dbx','alert-info',$msg);
 //       }

    } else {
       $msg['msg']='Es sind keine Befunde vorhanden.';
       $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

    }    


    $bdata['dbx_get']= '?dbx_modul=myBefund&dbx_action=import&dbx_work=import_befund';
    $bdata['label']  = 'Neue Befunde vom Labor einlesen';
    $bdata['i']      = dbx_get_next_i();
    //$action_button=$oTPL->get_tpl('dbx','button',$bdata);
    $action_button=$oTPL->get_tpl('dbx','button-modal1',$bdata);
    
    $content.=$action_button;



    return $content;
  }




   public function run() {
      $content=$this->summary();
      return $content;
   } // run

} // class


