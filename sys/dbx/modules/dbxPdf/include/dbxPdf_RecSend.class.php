<?php
namespace dbx\dbxPdf;

class dbxPdf_RecSend {

  Public $oTPL;
  Public $oDB;

  public function __construct() {
    $this->oTPL = dbx_get_sys_object('dbxTPL');
    $this->oDB  = dbx_get_sys_object('dbxDB');
  }


  public function generate_pdf($path,$file,$praxis) {
    $pdf=''; 
    $path_file=$path.$file;
    $path_file=dbx_os_path_file($path_file);
    

    $html=file_get_contents($path_file);
  
    $home_url=dbx_get_base_url();
    $home_url = str_replace('https://','http://',$home_url); // for xampp

    $home_img='dbx/modules/dbxPdf/tpl/img/';
    $html = str_replace('{base_href}dbx/modules/dbxPdf/design/default/img/',$home_img ,$html);
    $html = str_replace("<head>","<head>\n <base href=\"".$home_url."\"/>",$html);

    
    //echo $html; exit;
    file_put_contents('test.html',$html);
    
    // instantiate and use the dompdf class
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    
    
    $dompdf  = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    
    // (Optional) Setup the paper size and orientation
    $dompdf->setPaper('A4', 'portrait');
    
    // Render the HTML as PDF
    $dompdf->render();
    $pdf = $dompdf->output();

    // Output the generated PDF to Browser
    $path_file.='.pdf';
  
    //$dompdf->stream();  
    $path_file=dbx_os_path_file($path_file);  
    file_put_contents($path_file, $pdf);
     
     
    return $path_file;
  }


  public function send_pdf_file($ext,$path,$file) {
     $ok=0; $email=''; $arzt=0; $pdf='';

     if ($ext == 'rec') $uid=(str_replace('-medistar.rec'    ,'',$file));
     if ($ext == 'gkv') $uid=(str_replace('-inanspruch.gkv'  ,'',$file));
     if ($ext == 'pkv') $uid=(str_replace('-inanspruch.pkv'  ,'',$file));
     
 
     $uid=(str_replace('.run','',$uid));
     $ok=dbx_validate_var($uid,'int');
     
     $where="userid = $uid";
     $img_path_file =dbx_get_base_url();  
     $img_path_file.='dbx/modules/dbxPdf/tpl/img/lda-logo.jpg';
     $toInfo='labor@l-da.de';

     //dbx_debug ("PDF-SEND=($path - $file) UID=($uid) Where=($where) ok=($ok)");
     if ($ok) {
        $pdf=$this->generate_pdf($path,$file,$uid);
        $praxis=$this->oDB->select1('dbx_user',$where);
        if (is_array($praxis)) {
          $email=$praxis['emailbill'];
          $arzt =$praxis['userid'];
        }
     }
     if ($ok && $email && $arzt == $uid) {
         //dbx_debug ("#sende Befund#");
         $to    = $email; //'dagmar.thum@l-da.de' ; // or leo4u@gmx.de
         $toMail= $email;
         //$toMail = 'leo4u@gmx.de'; // dagmar.thum@l-da.de';  // Komment for real 
         
         if ($ext == 'rec') $from    ='rechnung@l-da.de';
         if ($ext != 'rec') $from    ='labor@l-da.de';
         
         if ($ext == 'rec') $name    ='Laborgenossenschaft Darmstadt Rechnung';
         if ($ext != 'rec') $name    ='Laborgemeinschaft Darmstadt Rechnung';
         
         
         if ($ext == 'rec')  $subject = '(L-DA) Rechung Medistar/DFU';
         if ($ext == 'gkv')  $subject = '(L-DA) Inanspruchnahme Kasse';
         if ($ext == 'pkv')  $subject = '(L-DA) Inanspruchnahme Privat und IGEL';

         if ($ext == 'rec') $text = '<br>Sehr geehrte Damen und Herren,<br>im Anhang finden Sie die monatliche Medistar Support <b>Rechnung</b>.<br><br>Mit freundlichen Grüßen<br>Laborgenossenschaft Darmstadt eG<br><hr><br><br>Laborgenossenschaft Darmstadt<br>';
         if ($ext == 'pkv') $text = '<br>Sehr geehrte Damen und Herren,<br>im Anhang finden Sie Ihre Inanspruchnahme <b>Privat und IGEL</b> zur <b>Kenntnisnahme</b>.<br><br>Mit freundlichen Grüßen<br>Laborgemeinschaft Darmstadt eG<br><br>';                 
         if ($ext == 'gkv') $text = '<br>Sehr geehrte Damen und Herren,<br>im Anhang finden Sie Ihre Inanspruchnahme <b>Kasse</b> zur <b>Kenntnisnahme</b>.<br><br>Mit freundlichen Grüßen<br>Laborgemeinschaft Darmstadt eG<br><br>';
         $text   .='Grüner Weg 18<br>64285 Darmstadt<br>Tel: 06151 - 494025<br>Fax: 06151 - 2796050<br><br><hr><br>' ;
         $text   .='<img src="'.$img_path_file.'"><br>'; 
        
         $ok=dbx_sendMail($from,$name,$toMail,$subject,$text,'html',$pdf);

         $subject.=' an->('.$to.')';
         $ok=dbx_sendMail($from,$name,$toInfo,'Gesendet: '.$subject,$text,'html');
         
         
         //dbx_debug("##Mail##########sende uid=($uid) Ok=($ok) mail=($email) attach=($attach) sub=($subject)");
     } else {
         $from    ='rechnung@l-da.de';
         $name    ='Laborgemeinschaft Darmstadt Rechnung';
         $to      = $toInfo ;    // $email;
         $subject = 'FEHLER ! (L-DA) PDF Versand ('. $email .')';
         $type    ='html';
         $text    ="Fehler beim senden an Praxis=($arzt = $uid) Mail=($email) Type=($ext).";
         $ok=dbx_sendMail($from,$name,$to,$subject,$text,$type,$pdf);
 
       
     }
     if ($pdf) {
        if (file_exists($pdf)) unlink($pdf);
     }

    return $ok;
  }




  public function send_rec($ext) {
  
     $content=''; $data=array();
     $uid=dbx_get_CurrentUser();
     $oForm=dbx_get_sys_object('dbxForm');
     $oForm->init('form-send-'.$ext);
     $oForm->_data    =  $data;
     $oForm->_action  = '?dbx_modul=dbxPdf&dbx_action=send-'.$ext;
     $oForm->_msg_info= 'Dateien werden als PDF gesendet';
     $timer=0;
     $pdata['width'] = 100;
     
     if ($ext == 'rec') {
       $pdata['msg']   = 'Warte auf Rechnungen';
       $oForm->_msg_info   ='Rechnungen senden';
       $oForm->_msg_success='Rechnungen gesendet';
     }
     if ($ext == 'gkv') {
       $pdata['msg']   = 'Warte auf Inanspruchnahme Kasse';
       $oForm->_msg_info   ='Inanspruchnahme senden';
       $oForm->_msg_success='Inanspruchnahme gesendet';
     }
     
     if ($ext == 'pkv') {
       $pdata['msg']   = 'Warte auf Inanspruchnahme Privat';
       $oForm->_msg_info   ='Inanspruchnahme senden';
       $oForm->_msg_success='Inanspruchnahme gesendet';
     }
     
     
     
     $path   =dbx_get_file_dir().'myBefund/ldt-in/';
     $archiv =$path.'.done/';
     //$ext    ='rec';
     $process='PdfSendRec'.$ext;

     $oWalker=dbx_get_sys_object('dbxFileWalker');
     $oWalker->set_property('process',$process);
     $oWalker->set_property('path'   ,$path);
     $oWalker->set_property('archiv' ,$archiv);
     $oWalker->set_property('date'   ,1);
     $oWalker->set_property('delete' ,0);
     $oWalker->set_property('ext'    ,$ext);
     $oWalker->set_property('run'   ,'');  // no run protection importent to send '.LDTx and not .LDTx.run'!


     // only for test
     //$oWalker->set_property('run'    ,'');
     //$oWalker->set_property('done'   ,'');
     // ------------

     $status =$oWalker->run();
     $pos    =$oWalker->get_property('pos'    ,0);
     $count  =$oWalker->get_property('count'  ,0);
     $percent=$oWalker->get_property('percent',0);
     $file   =$oWalker->get_property('file'   ,'');

     $info="##Walker## Status=($status) Pos=($pos) Count=($count) File=($file) Prozent=($percent)";
     $msg ="Import=($file) ($percent %)";

     if ($status == 1) { // continue
       // do import;
       $exist=0; if (file_exists($path.$file)) $exist=1;

       $ok=$this->send_pdf_file($ext,$path,$file);
       // - - - - - -
       //dbx_debug("##SEND## ($ok)= Path=($path) File=($file) exist=($exist)");


       $oWalker->done();
       $oWalker->archiv();

       $timer=100; // fast submit
       dbx_set_SessionVal('do_send_'.$ext,1);
       //$info =" Lese Datei ($file) ein.";
     }
     if ($status == 2) { // finisch import
       //$oWalker->archiv_all();
       $oWalker->clear($process);
       $percent=100;
     }

    if ($ext == 'rec') $label_button="Rechnungen senden ";
    if ($ext == 'gkv') $label_button="Inanspruchnahme Kasse senden ";
    if ($ext == 'pkv') $label_button="Inanspruchnahme Privat+IGEL senden ";    
    if ($file) $label_button.=" Datei=($file)";
    
    $bdata['id']   ='button_{i}';
    $bdata['sec']  =($timer / 1000);
    $bdata['label']=$label_button;
    $bdata['submit']  = '#dbx_form_{i}';
    $bdata['redirect']= 0;

    $pdata['msg']   = $msg;
    $pdata['width'] = $percent;
    $pdata['value'] = $percent;

    $progress=$this->oTPL->get_tpl('dbx','progressbar-1');
    $button  =$this->oTPL->get_tpl('dbx','button-countdown.js');

    $info='';  $oForm->_msg_info=''; $oForm->_msg_success='';

    $oForm->add_obj('info'    ,'obj-value',$info    ,''    );
    $oForm->add_obj('progress','obj-value',$progress,$pdata);
    $oForm->add_obj('button'  ,'obj-value',$button  ,$bdata);
    //$oForm->add_js_autosubmit('dbx_form_{i}',$timer); // check #todo

    $content=$oForm->run();

    $do_send_pdf=dbx_get_SessionVal('do_send_pdf_'.$ext,0);

    if ($status==2 && $uid == -3 ) { //&& $do_send_pdf == 1) {
        dbx_set_SessionVal('do_send_pdf_'.$ext,0);
        $count=$oWalker->_count;
        $date_time=date('d-m-Y H:i:s');
        if ($ext == 'rec' ) $msg="Es wurden ($count) Rechnungen versendet ($date_time)";
        if ($ext == 'gkv' ) $msg="Es wurden ($count) Inanspruch GKV versendet ($date_time)";
        if ($ext == 'pkv' ) $msg="Es wurden ($count) Inanspruch PKV versendet ($date_time)";
        $content=$this->oTPL->get_tpl('dbx','alert-info',"msg=$msg");
    }
    //get_tpl($modul,$file,$data='',$type='htm',$i=0)
    //$oWalker->clear($process); Dateien einzeln einlesen
    return $content;
  }


  public function run($ext) {
      $content =$this->send_rec($ext);
      return $content;
  }


} // class
