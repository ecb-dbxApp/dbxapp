<?php
namespace dbx\myBefund_admin;


class myLDTx {

  Public $oTPL;
  Public $oDB;

  public function __construct() {
    $this->oTPL = dbx_get_sys_object('dbxTPL');
    $this->oDB  = dbx_get_sys_object('dbxDB');
  }

  public function send_ldx_file($path,$file) {
     $ok=0; $email='';


     $uid=(str_replace('LDA'  ,'',$file));
     $uid=(str_replace('URO'  ,'',$uid));
     $uid=(str_replace('.LDTx','',$uid));
     $uid=(str_replace('.run' ,'',$uid));

     $ok=dbx_validate_var($uid,'int');
     //dbx_debug ("UID=($uid) ok=($ok)");
     if ($ok) {
        $where="userid = $uid";
        $praxis=$this->oDB->select1('dbx_user',$where,'email');
        if (is_array($praxis)) {
          $email=$praxis['email'];
        }
     }
     if ($ok && $email) {
         $attach='';
         //dbx_debug ("#sende Befund#");
         $from   ='befund@l-da.de';
         $name   ='Laborgemeinschaft Darmstadt Befund';
         $to     =$email;
         $subject='(L-DA) Befund als LDTx Datei';
         $type  ='text';
         $text  ='Anbei der angeforderte Befund als LDTx Datei.';
         $attach=$path.$file;
         //$attach='/homepages/36/d22904123/htdocs/dbx.l-da.de/files/myBefund/test.LDTx';

         $ok=dbx_sendMail($from,$name,$to,$subject,$text,$type,$attach);
         //dbx_debug("##Mail##########sende uid=($uid) Ok=($ok) mail=($email) attach=($attach) sub=($subject)");
     }
     if (!$ok) {
         // protokoll error #alb todo
     }

     // ..
     return $ok;
  }


  public function send_ldtx() {
    $content=''; $data=array();
    $uid=dbx_get_CurrentUser();
    $oForm=dbx_get_sys_object('dbxForm');
    $oForm->init('form-import-ldt');
    $oForm->_data    =  $data;
    $oForm->_action  = '?dbx_modul=myBefund_admin&dbx_action=send_ldtx';
    $oForm->_msg_info= 'LDTx Daten werden gesendet';
    $timer=0;
    $pdata['msg']   = 'Warte auf LDTx-Dateien';
    $pdata['width'] = 100;

    $oForm->_msg_success = 'LDT Datei einlesen';
    $path   =dbx_get_file_dir().'myBefund/ldt-in/';
    $archiv =$path.'.done/';
    $ext    ='LDTx';
    $process='LDTx-send';

    $oWalker=dbx_get_sys_object('dbxFileWalker');
    $oWalker->set_property('process',$process);
    $oWalker->set_property('path'   ,$path);
    $oWalker->set_property('archiv' ,$archiv);
    $oWalker->set_property('date'   ,1);
    $oWalker->set_property('delete' ,0);
    $oWalker->set_property('ext'    ,$ext);
    $oWalker->set_property('run'   ,'');

    // only for test
    //$oWalker->set_property('run'    ,'');
    //$oWalker->set_property('done'   ,'');
    // ------------

    $status =$oWalker->run();
    $pos    =$oWalker->get_property('pos'    ,0);
    $count  =$oWalker->get_property('count'  ,0);
    $percent=$oWalker->get_property('percent',0);
    $file   =$oWalker->get_property('file'   ,'');
    $date_time=date('d-m-Y H:i:s');

    $info="##Walker## Status=($status) Pos=($pos) Count=($count) File=($file) Prozent=($percent)";
    $msg ="Sende=($file) ($percent %)";

    if ($status == 1) { // continue
      dbx_set_SessionVal('do_ldtx_send',1);
      // do import;
      $path_file=$path.$file;
      $ret=$this->send_ldx_file($path,$file);
      //dbx_debug("##RET##=",$ret);
      // - - - - - -

      $oWalker->done();
      $oWalker->archiv();

      $timer=0.1; // fast submit
      //$info =" Lese Datei ($file) ein.";
    }
    if ($status == 2) { // finisch import
      //$oWalker->archiv_all();
      $count=$oWalker->_count;
      $oWalker->clear($process);
      $timer= (1000 * 60); // slow submit (wait)
      $pdata['msg']   = 'Alle LDTx Dateien im Verzeichnis gesendet. Warte ...';
      $percent=100;
    }

   $label_button="LDTX-Dateien senden ";
   if ($file) $label_button.=" LDTx-Datei=($file)";
   $bdata['id']      = 'button_{i}';
   $bdata['sec']     = $timer;
   $bdata['label']   = $label_button;
   $bdata['submit']  = '#dbx_form_{i}';
   $bdata['redirect']= 0;

   $pdata['msg']   = $msg;
   $pdata['width'] = $percent;
   $pdata['value'] = $percent;

   $progress=$this->oTPL->get_tpl('dbx','progressbar-1');
   $button  =$this->oTPL->get_tpl('dbx','button-countdown');

   $info='';  $oForm->_msg_info=''; $oForm->_msg_success='';

   $oForm->add_obj('info'    ,'obj-value',$info);
   $oForm->add_obj('progress','obj-value',$progress,$pdata);
   $oForm->add_obj('button'  ,'obj-value',$button  ,$bdata);
   //$oForm->add_js_autosubmit('dbx_form_{i}',$timer);

   $content=$oForm->run();

   $do_ldtx_send=dbx_get_SessionVal('do_ldtx_send',0);

   if ($status==2 && $do_ldtx_send == 1) {
       dbx_set_SessionVal('do_ldtx_send',0);
       $count=$oWalker->_count;
       $content=$oForm->get_tpl('alert-success',"msg=Es wurden ($count) LDTX Dateien versendet ($date_time)");
   } 
   if ($status==2 && $do_ldtx_send == 0) {
       $content=$oForm->get_tpl('alert-info',"msg=Keine LDTx Datei(en) zum Senden vorhanden ($date_time).");
   }
  

   //$oWalker->clear($process); Dateien einzeln einlesen
   return $content;
 }




 


  public function run() {
      $content=$this->send_ldtx();
      return $content;
  }

} // Class

?>
