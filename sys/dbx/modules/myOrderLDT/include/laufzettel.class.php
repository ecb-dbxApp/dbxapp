<?php
namespace dbx\myOrderLDT;
//include_once dbx_get_base_dir().'dbx/add_ons/sftp/vendor/autoload.php';
//use phpseclib3\Crypt\AES;

dbx_use_sys_class('dbxReport');


class dbxReport_Laufzettel extends \dbxReport {

  private function get_name($record) {
    $retval=$record['vorname'].' '.$record['nachname'];
    return $retval;
  }

  private function get_count_anf($anf) {
    $count=0;
    if ($anf=='a:0:{}') $anf='';
    if ($anf) {
      $anfx = explode(",", $anf);
      $count= count($anfx);
    } 
    return $count;

  }


  private function insertLineBreaksAfterComma($string, $maxLength) { 
    $string = str_replace(',', ' , ', $string);
    $output = '';
    $currentLineLength = 0;
    $parts = explode(',', $string);  // Zerlege den String in Teile, die durch Kommas getrennt sind
    
    foreach ($parts as $index => $part) {
        // Füge das aktuelle Teilstück und ein Komma hinzu (außer beim letzten Teil)
        if ($index < count($parts) - 1) {
            $part .= ',';
        }
        
        // Überprüfe die Länge der aktuellen Zeile mit dem neuen Teilstück
        if ($currentLineLength + strlen($part) > $maxLength) {
            // Wenn die maximale Länge überschritten wird, füge einen <br> hinzu und starte eine neue Zeile
            $output .= '<br>';
            $currentLineLength = 0;
        }
        
        // Füge das Teilstück der Ausgabe hinzu und aktualisiere die Länge der aktuellen Zeile
        $output .= $part;
        $currentLineLength += strlen($part);
    }

    return $output;
  } 

  private function get_auftrag($anforderungen) {
    $oDB=dbx_get_sys_object('dbxDB');
    $auftrag='';
    
    $anfs = explode(",", $anforderungen); 
    foreach ($anfs as $anf) {
       $data=$oDB->select1('lda_methoden',"abk = '$anf'",verify_access: 0);
       if (is_array($data)) {
          if ($data['pos10a'] > 0) {
            $auftrag.=$data['pos10a']."\t";
          } else {
            $auftrag.=$anf.',';
          }
       }
        
    }
    dbx_debug("Auftrag=($auftrag)");
    return $auftrag;
  }

  private function bar_datum($date) {
    $date=str_replace("-", "", $date);
    return $date;
  }

  private function get_pdf417_data($id,$arzt,$pat) {
     $xdata='';
     $oDB=dbx_get_sys_object('dbxDB');
     $record=$oDB->select1('my_order',$id);

     $lastDayOfYear  = new \DateTime('last day of December');
     $verende=$lastDayOfYear->format('Ymd'); // Ausgabe: z.B. 20241231
     $firstDayOfYear = new \DateTime('first day of January');
     $firstDate=$firstDayOfYear->format('Ymd'); // Ausgabe: z.B. 20240101

     $form_code="10";
     $form_erg ='A';
     $version  = '08';
     $record['verende']           = $verende;
     $record['kv_bereich']        = '49'; // #todo ??
     $record['versichertenart']   = substr($record['status'], 0, 1);
     $record['personengruppe']    = substr($record['status'], 3, 2); 
     $record['dmp_kennzeichnung'] = substr($record['status'], 3, 2); 
     $record['ausstell_datum']    = $firstDate;  
     $record['ssw']               = ''; // ?? 
     $record['titel']             = ''; // ?? 
     $record['namzusatz']         = '';
     $record['vorsatzwort']       = ''; 
     $record['hausnummer']        = '';
     $record['postfach_plz']      = '';
     $record['postfach_ort']      = '';
     $record['postfach']          = '';
     $record['postfach_land']     = '';
     $record['knappschaft']       = '';
     $record['pruefnr']           = 'Y/9/2307/36/112'; // ? #todo 
     $record['auftrag']           = $this->get_auftrag($record['anforderungen']) ;
     
     if ($record['praeventiv']) $record['kurativ']=2;
     if ($record['belegarzt'])  $record['kurativ']=4;
  
     $record['gebdat']           =$this->bar_datum($record['gebdat']);
     $record['ausstell_datum']   =$this->bar_datum($record['ausstell_datum']);
     $record['abdatum']          =$this->bar_datum($record['abdatum']);

     $record['diagnosen']='C24.9 G';

     //dbx_debug ("PDF Arzt=($arzt) pat=($pat)",$record);

     $out['01']=$form_code;
     $out['02']=$form_erg;
     $out['03']=$version;
     $out['04']=$arzt.$pat;
     $out['05']=$record['nachname'];
     $out['06']=$record['vorname'];
     $out['07']=$record['gebdat']; // LDT format #todo
     $out['08']=$record['verende'];
     $out['09']=$record['kostentraeger'];
     $out['10']=$record['krankenkasse'];
     $out['11']=$record['kv_bereich'];
     $out['12']=$record['versicherungsnr'];
     $out['13']=$record['versichertenart'];
     $out['14']=$record['personengruppe'];
     $out['15']=$record['dmp_kennzeichnung'];
     $out['16']=$record['bsnr'];
     $out['17']=$record['lanr'];
     $out['18']=$record['ausstell_datum'];
     $out['19']=$record['geschlecht'];
     $out['20']=$record['ssw'];
     $out['21']=$record['titel'];
     $out['22']=$record['namzusatz'];
     $out['22']=$record['namzusatz'];
     $out['23']=$record['vorsatzwort'];
     $out['24']=$record['plz'];
     $out['25']=$record['ort'];
     $out['26']=$record['strasse'];
     $out['27']=$record['hausnummer'];
     $out['28']=$record['land'];

     $out['29']=$record['postfach_plz'];
     $out['30']=$record['postfach_ort'];
     $out['31']=$record['postfach'];
     $out['32']=$record['postfach_land'];

     $out['33']=$record['kurativ'];
     $out['34']=$record['unfall'];
     $out['35']=$record['knappschaft'];
     $out['36']=$record['abdatum'];
     $out['37']=$record['abzeit'];
     $out['38']=$record['diagnosen'];
     //$out['39']=$record['bemerkung1'];
     $out['40']=$record['pruefnr'];
     $out['41']=$record['auftrag'];

     $valuesOnly = array_values($out);
     $xdata = implode("\t", $valuesOnly);  
     $xdata = iconv("UTF-8", "Windows-1252//TRANSLIT", $xdata);


     return $xdata;
  }


  public function run_body($content) {
    $today=date('Y-m-d'); 
    $record   =$this->_record;
    $id=$record['id'];
    
    $count=$this->get_count_anf($record['anforderungen']);

    $class='anf-today';
    $datum=$record['datum'];
    if ($datum  > $today) $class='anf-future';
    if ($datum  < $today) $class='anf-past';
   

    $record['name']     = $this->get_name($record);
    $record['count']    = $count;
    $record['pat']      = sprintf('%03d',$record['pat']);
    $record['gebat']    = $this->php_date_usr($record['gebdat']);
    $record['anforderungenx']=$this->insertLineBreaksAfterComma($record['anforderungen'],100);
    $record['bemerkung1'].='<br><br><hr>';
    $record['dbx_td_class']= $class; 
    $this->_record=$record;


    $data['name']  =$this->get_name($record);
    $data['pra']   =dbx_get_cfg('myOrderLDT','praxis');
  
    $barcode=dbx_get_sys_object('dbxBarcode');
    $pdf417 =dbx_get_sys_object('dbxPDF417');
    $options=array();

    $Arzt=sprintf("%04d", $data['pra']);
    $Pat =sprintf("%03d", $record['pat']);
    $ArztPat=$Arzt.$Pat;

    $symbology='ean-128'; 
    $xdata    = $ArztPat;
    $options['w'] = 160;
    $options['h'] = 60;
    $svg1=$barcode->render_svg($symbology, $xdata, $options);

    //$symbology='gs1-dmtx-r'; 
    //$xdata    ='10;A;10;;Ismail;Aischa;19900423;;105313145;AOK Hessen;46;Z282596124;1;00;00;391740500;998268726;20241030;W;;;;;64347;Griesheim;Kirschberg;14 D;D;;;;;1;;;20;;;;Y/9/2307/36/112;03;13;17;23;27;29;30;39;43;49;';
    //$options['w'] = 260;
    //$options['h'] = 60;
    //$svg2=$barcode->render_svg($symbology, $xdata, $options);

    $xdata=$this->get_pdf417_data($id, $Arzt,$Pat);
    //$xdata    ='10;A;10;;Ismail;Aischa;19900423;;105313145;AOK Hessen;46;Z282596124;1;00;00;391740500;998268726;20241030;W;;;;;64347;Griesheim;Kirschberg;14 D;D;;;;;1;;;20;;;;Y/9/2307/36/112;03;13;17;23;27;29;30;39;43;49;';
    $svg2=$pdf417->get_pdf417($xdata);


    $barcode_arzt_pat=$svg1;
    $barcode_order   =$svg2;


    $pat_info=$this->get_tpl('modul|pat_laufzettel',$data);
    $this->add_obj('pat_info','obj-value',$pat_info);
    $this->add_obj('bar_arzt_pat','obj-value',$barcode_arzt_pat);
    $this->add_obj('bar_order'   ,'obj-value',$barcode_order);

    $content=$this->forward_run_body($content);
    return $content;
  }
}



// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -



Class laufzettel {

  private function get_count_anf($anf) {
    $count=0;
    $anf=trim($anf);
    if ($anf) {
      $anfx = explode(",", $anf);
      $count= count($anfx); 
      if (trim($anfx[0]) == 'a:0:{}') $count --;
      //dbx_debug("COUNT-ANT=($count)",$anfx); 
    } 

    //if ($count) $count--;
    return $count;
  }


  private function get_check_anf($anf,$abk) {
    $chk=0;  $abk.=','; $anf.=',';
    $pos=(strpos('~'.$anf,$abk));
    if ($pos) $chk=1; 
    //dbx_debug("chek-anf ($abk) Anf=($anf) Pos=($pos) Check=$chk");
    return $chk;
  }

  private function get_profil_parameter($profil) {
     $retval=array();
     $oDB=dbx_get_sys_object('dbxDB');
     $data=$oDB->select1('my_profile',"profil == '$profil'");
     if (is_array($data)) {
        $retval=$data['parameter'];  
        if (!is_array($retval)) $retval=explode(',',$retval);
     }
     return $retval;
  }
 
  // - - - - - - - - - - - - - - - - - - - -

  // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -







  public function list_laufzettel() {
    
    $today  = date('Y-m-d', time()); 

    $oReport = new dbxReport_Laufzettel;
    $oDB     = dbx_get_sys_object('dbxDB');
    $oTPL    = dbx_get_sys_object('dbxTPL');
    $lng     = dbx_get_ModulVar('lng','de');
    $dd      = 'my_order';
    $form_id = 'report-laufzettel';
    $work    =dbx_get_ModulVar('dbx_work','','parameter');
    $do      =dbx_get_ModulVar('dbx_do'  ,'','parameter'); 
    $rid     =dbx_get_ModulVar('rid'     ,0 ,'int');
    $oReport->init($form_id);
    $modal_content=''; 
    $date  =$oReport->get_sel('sel_date'   ,$today ,'date');



    $flds['id']              ='';
    $flds['datum']           ='Datum';  
    $flds['pat']             ='Probe-Nr.';
    $flds['pk']              ='P/K'; 
    $flds['formular']        ='Formular';
    $flds['vorname']         ='Name';
    $flds['nachname']        ='';      
    $flds['gebdat']          ='Geburtstag'; 
    $flds['anforderungen']   ='';
    $flds['count']           ='Anf.';
    $flds['gesendet']        ='Gesendet';
    $flds['bemerkung1']      ='Bemerkung';
 

    $options_rsort['id']        = 'ID';
    $options_rsort['datum']     = 'Datum';
    $options_rsort['gesendet']  = 'Gesendet';

    $today  = date('Y-m-d', time()); 
    $sel_date=$today;
    $class_haeder['ldt']='no-sort'; 

    $data['dbx_rrows']= 100;
    $data['dbx_rsort']='id';
    $data['sel_date'] = $sel_date;

    
    $oReport->_data=$data;
    $oReport->_action='?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=laufzettel'; 
    $oReport->_options_rsort = $options_rsort;
    $oReport->_but_pagination   =0;
    $oReport->_create_row_select=0;
    $oReport->_create_row_edit  =0;
    $oReport->_create_row_delete=0;
    $oReport->_create_sel_flds  =0;    
 
    $oReport->_msg_info=''; //'Liste der Patienten für Laboranforderungen.  Wählen Sie bitte das Datum aus.';
    $oReport->_msg_success='';

    $oReport->_class_haeder                 = $class_haeder; 
    $oReport->_activ_id                     = 0;

    $today_user=$oReport->php_date_usr($today);
    $datum_user=$oReport->php_date_usr($date);


    $sel1['action']= $oReport->_action.'&sdate=today';
    $sel1['msg']   ='Nur Heute ('. $today_user .')';

    $sel2['action']=$oReport->_action.'&sdate=clear';
    $sel2['msg']   ='Dieses Datum';

    $oReport->add_fld('sel_today' ,'modul|button_report',$sel1);
    $oReport->add_fld('sel_clear' ,'modul|button_report',$sel2);
    

    $oReport->add_action('button_prn',  'modul|button_prn', '#');
    // print 'obj:print'


    $rpt_format['datum']   ='php-date-usr';
    $rpt_format['gebdat']  ='php-date-usr';
    $rpt_format['gesendet']='php-datetime-usr';
    $oReport->_rpt_format=$rpt_format;
       


     $todo=$oDB->count('my_order',"datum == '$today' and pat > 0 and nachname > ' ' and gebdat > '1900-01-01' and anforderungen > ' ' and gesendet <= '1900-01-01' ");
     $alle=$oDB->count('my_order',"datum == '$today' ");
     $send=$oDB->count('my_order',"datum == '$today' and gesendet > '1900-01-01' ");
     $rest=($alle - $send);
    

     $dateset=dbx_get_PostVar('dbx_get',0,'*');
     parse_str($dateset,$params);
     if (isset($params['sdate'])) $set_date=$params['sdate'];
    

     if($oReport->submit()) {
       if($oReport->errors()) {      // submit && no errors
          $oReport->_msg_error = 'Prüfen sie bitte ihre Eingaben';
       }
     }  else { // no submit
       
 
     }

     // get all selections and order
     
    $rgroup=''; $set_date=0;
    $select=$oReport->get_sel('dbx_rselect',0         ,'int');
    $date  =$oReport->get_sel('sel_date'   ,$sel_date ,'date');

    $rgroup=''; $rwhere='';
    $select   =$oReport->get_sel('dbx_rselect'  ,0          ,'int');
    $rfind    =$oReport->get_sel('dbx_rwhere'   ,''         ,'parameter');
    $rrows    =$oReport->get_sel('dbx_rrows'    ,100        ,'int');
    $rpos     =$oReport->get_sel('dbx_rpos'     ,0          ,'int');
    $rsort    ='datum';
    $rdesc    ='DESC';



     $rwhere="datum = '$date' ";


     parse_str($dateset,$params);
     if (isset($params['sdate'])) { 
        $set_date=$params['sdate'];
     } else {
        $set_date=dbx_get_Remember('set_date',0,'parameter'); 
     } 

     if ($set_date) {
 
        if ($set_date=='today') {
          $date = $today;
          //$_POST['sel_date'] = $date;  // Datum auf Heute setzen 
          $rwhere="datum = '$date' ";
          dbx_set_Remember('set_date','today');
        }     
        if ($set_date=='clear') {
          $rwhere="datum = '$date' ";
          dbx_set_Remember('set_date',0);
        }    
     } 

     $sel_date=$oReport->php_date_usr($date);
     $oReport->add_obj('datum','obj-value',$sel_date);

     if ($select) $rwhere=$oReport->add_rwhere_select($rwhere); 

     
     $oReport->_rcount=$oDB->count($dd,$rwhere);
     $oReport->_rdata =$oDB->select($dd,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);
     
     $oReport->add_fld('sel_date','date-label-group-prompt','','date', 'Datum','Von diesem Datum');



     $content=$oReport->run(1,$flds,'table');
  

     return $content;

  }


// ----------------------------------
  private function get_file_info($file,$what) {
    // // 064-001_2024-02-07.ldt
    $retval='';
    if ($what == 'praxis')  $retval=substr($file, 0, 3); 
    if ($what == 'pat')     $retval=substr($file, 4, 3); 
    if ($what == 'dat')     $retval=substr($file, 8, 10); 
    if ($what == 'ext') {
      $last_dot_position = strrpos($file, '.');
      if ($last_dot_position > 0) $retval = substr($file, $last_dot_position);
    }     
    
    //$retval=substr($file, -4);   
    return $retval;
  }  









   public function run() {
    $modul=dbx_get_SysVar('dbx_activ_modul');
    $work =dbx_get_ModulVar('dbx_work');
    $content="myOrder.class Work=($work)";
    switch ($work) {
 

        case 'print':
          $content=$this->list_laufzettel();     
        break; 

        case 'laufzettel':
        case 'list':
          $content=$this->list_laufzettel();     
        break; 


       default:
        dbx_set_Remember('dbx_load_pat',1);
        $oTPL=dbx_get_sys_object('dbxTPL');
        $msg['msg']="x Modul=($modul) Work=($work) is undef.";
        $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // switch()


     return $content;
   } // run

} // class


