<?php
namespace dbx\myOrderLDT;
//include_once dbx_get_base_dir().'dbx/add_ons/sftp/vendor/autoload.php';
//use phpseclib3\Crypt\AES;

dbx_use_sys_class('dbxReport');

class editAnf {
  
  public function set_next_patid($rid) {
    $anfc = 0; $pat=0;
    dbx_debug("############## check set_next_patid=($rid)");
    $today=date('Y-m-d');
    $oDB=dbx_get_sys_object('dbxDB');
    $data=$oDB->select1('my_order',$rid);
    //dbx_debug("Next pat data id=($rid) data=",$data); 

    if (is_array($data)) {
      $date= $data['datum'];
      $pat = $data['pat']; 
      $anf = $data['anforderungen'];
      $anfc= $this->get_count_anf($anf);
    

      if ($anfc) {

        $pat_nr_ok = false;
        //dbx_debug("# create new pat-nr Date=($date) count=($anfc)",$anf);
        for ($iL = 0; $iL < 10; $iL++) {
        
          $probe=$oDB->select1('my_numkreis','id =1');
          $pat=$probe['next_probe'];
          $next=($pat + 1);
          if ($next > $probe['id_bis']) $next=$probe['id_von'];
          $probe['next_probe']=$next;
          $ok=$oDB->update('my_numkreis',$probe,'id = 1',0,0,0,0); //     update($dd,$field_values,$where,$verify_access=1,$verify_fields=1,$verify_values=1,$trace=1) {                                  
  
          $where="datum = '$today' and  pat = '$pat'  and id <> $rid";
          $count=$oDB->count('my_order',$where);
          if (!$count)  $pat_nr_ok = true;                   
          if ($pat_nr_ok === true) break;
 
        }
        if ($pat_nr_ok) {
          $rec=array();
          $rec['pat']=$pat;
          $ok=$oDB->update('my_order',$rec,$rid,0,0,0,0); 
          if ($ok) $_POST['pat']=$pat; // Update Form
          dbx_debug("NEW-NR=($pat) save=($ok)");
        }

      }
 
    } 
    return $pat; 
  }


  public  function get_count_anf($anf) {
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


  public function get_check_anf($anf,$abk) {
    $chk=0;  $abk.=','; $anf.=',';
    $pos=(strpos('~'.$anf,$abk));
    if ($pos) $chk=1; 
    //dbx_debug("chek-anf ($abk) Anf=($anf) Pos=($pos) Check=$chk");
    return $chk;
  }

  public function get_profil_parameter($profil) {
     $retval=array();
     $oDB=dbx_get_sys_object('dbxDB');
     $data=$oDB->select1('my_profile',"profil == '$profil'");
     if (is_array($data)) {
        $retval=$data['parameter'];  
        if (!is_array($retval)) $retval=explode(',',$retval);
     }
     return $retval;
  }



}

class dbxReport_Order extends \dbxReport {

  private function get_name($record) {
    $retval=$record['vorname'].' '.$record['nachname'];
    return $retval;
  }

  private function get_count_anf($anf) {
    $count=0;
    if ($anf=='a:0:{}') $anf='';
    if ($anf) {
      $anfx = explode( ",", $anf);
      $count= count($anfx);
    } 
    return $count;

  }


  public function run_body($content) {
    $today=date('Y-m-d'); 
    $activ_id =$this->_activ_id;
    $record   =$this->_record;
    $count=$this->get_count_anf($record['anforderungen']);
    $class='anf-today';
    $datum=$record['datum'];
    if ($datum  > $today) $class='anf-future';
    if ($datum  < $today) $class='anf-past';
    if (isset($record['id']) && $activ_id) {
      if ($activ_id == $record['id']) $class.=" activ_td";
    }

    $record['vorname']     = $this->get_name($record);
    $record['count']       = $count;
    if ($record['pat'] > 0)  $record['pat'] = sprintf('%03d',$record['pat']);
  
    $record['dbx_td_class']= $class; 
    $this->_record=$record;
    $content=$this->forward_run_body($content);
    return $content;
  }
}



// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -



Class myOrder {

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

   function add_order() {
      dbx_set_ModulVar('rid',0);
      return $this->edit_order();
   }

  public function edit_order() {
    dbx_set_Remember('dbx_load_pat',0);
    $add_data=array();
    $content=''; $options_groups=array(); $add_data=array();
    $oForm =dbx_get_sys_object('dbxForm');
    $oDB   =dbx_get_sys_object('dbxDB');
    $do    =dbx_get_ModulVar('dbx_do',0,'parameter');
    $rid   =dbx_get_ModulVar('rid',0,'int');
    $dd    ='my_order';
    $praxis=dbx_get_cfg('myOrderLDT','praxis');

    //dbx_debug("EDIT-ORDER GET= POST=",$_GET,$_POST);
    $oForm->init('form-patient');
    
    $oForm->_msg_error='Fehler';

    //dbx_debug("### USER EDIT ID=($rid)  Action=($action) ####");
    $data=$oDB->select1('my_order',$rid);
    
    $arzt=$oDB->select1('my_arzt',$data['arzt']);
    if (is_array($arzt)) {
      $data['lanr']=$arzt['lanr'];
      $data['bsnr']=$arzt['bsnr'];
    } 
  
    if (!$data['datum']) $data['datum']=$oForm->php_date('today'); // new rec 
    

    $oForm->_data      = $data;
    $oForm->_dd        = $dd; // Main db-Table for validation
    $oForm->_action    = "?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=edit_order&dbx_do=$do&rid=$rid";


    
    $oForm->add_fld('pat'            ,'integer-label' );
    $oForm->add_fld('arzt'           ,'select-single-label' );
    $oForm->add_fld('datum'          ,'date-label-prompt','','*');
    $oForm->add_fld('pk'             ,'select-single-label');
    $oForm->add_fld('formular'       ,'select-single-label');

    $oForm->add_fld('kurativ'        ,'checkbox-label', class: 'cb-size-2' );
    $oForm->add_fld('praeventiv'     ,'checkbox-label', class: 'cb-size-2' );
    $oForm->add_fld('belegarzt'      ,'checkbox-label', class: 'cb-size-2' );
    $oForm->add_fld('unfall'         ,'checkbox-label', class: 'cb-size-2' );
    $oForm->add_fld('geschlecht'     ,'select-single-label');

    // add_fld($name,$tpl,$data='dd:',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='',$remap='') { //#

    $oForm->add_fld('krankenkasse'   ,'text-label' );
    $oForm->add_fld('nachname'       ,'text-label' );
    $oForm->add_fld('vorname'        ,'text-label' );
    $oForm->add_fld('gebdat'         ,'date-label-prompt');
    $oForm->add_fld('strasse'        ,'text-label' );
    $oForm->add_fld('land'           ,'select-single-label');
    $oForm->add_fld('plz'            ,'text-label' );
    $oForm->add_fld('ort'            ,'text-label' );

    $oForm->add_fld('kostentraeger'  ,'text-label' );
    $oForm->add_fld('versicherungsnr','text-label' );
    $oForm->add_fld('status'         ,'select-single-label' );
    

    $oForm->add_fld('bsnr'           ,'text-label' );
    $oForm->add_fld('lanr'           ,'text-label' );
   
    $oForm->add_fld('gesendet'       ,'text-label-disabled' ); 
    $oForm->add_fld('abdatum'        ,'date-label-prompt' );
    $oForm->add_fld('abzeit'         ,'text-label' );

    $oForm->add_fld('diagnosen'      ,'textarea-small-label' );
    $oForm->add_fld('bemerkung1'     ,'textarea-small-label' );

    
 
    $can_edit =1;
    $anf  =$data['anforderungen'];
    $send =$data['gesendet'];
   

    if ($anf=='a:0:{}') $anf='';
    $count_anf=$this->get_count_anf($anf);
    if ($send > ' ') $can_edit = 0;
      
    $anfx=str_replace(",", ", ", $anf); 
    $oForm->add_obj('count_anf'    ,'obj-value',"<b>($count_anf)</b>");
    $oForm->add_obj('anforderungen','obj-value'," ($anfx)");


    //public function add_fld($name,$tpl,$data='',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='') { //#

    //$oForm->_fld_change_state='*';
    

    if($oForm->submit()) {
      //dbx_debug("form-submit");
      $oForm->_msg_success   = 'Keine Änderung';
      $change=$oForm->changed();
      if ($change) {
        //dbx_debug("form change");
        $today=$oForm->php_date('today'); 
      
        $arzt  = $oForm->get_post_data('arzt'           ,1  ,'int');
        $pat   = $oForm->get_post_data('pat'            ,0  ,'int');
        $pk    = $oForm->get_post_data('pk'             ,'k','parameter');
        //$kasse = $oForm->get_post_data('kostentraeger'  ,'' ,'parameter');
        //$versnr= $oForm->get_post_data('versicherungsnr','' ,'parameter');
        //$name  = $oForm->get_post_data('nachname'       ,'' ,'words');
        //$gebdat= $oForm->get_post_data('gebdat'         ,'' ,'date');
        $datum = $oForm->get_post_data('datum'          ,'' ,'date');
        $lanr   =$oForm->get_post_data('lanr'           ,'','parameter');
        $bsnr   =$oForm->get_post_data('bsnr'           ,'','parameter');

        if (!$lanr || !$bsnr) {
           $einsender=$oDB->select1('my_arzt',$arzt);
           if (is_array($einsender)) {
              $lanr=$einsender['lanr'];
              $bsnr=$einsender['bsnr'];
           }

        }

        if ($datum > $today) dbx_set_Remember('set_date','last');


        if ($pat > '') {
          $where="datum = '$datum' and  pat = '$pat'  and id <> $rid";
          $count=$oDB->count($dd,$where);
          if ($count) {
            $oForm->add_fld_error('pat',"Proben IDs können an einem Tag nicht mehrfach vergeben werden.");
            $oForm->_msg_error="Proben IDs können an einem Tag nicht mehrfach vergeben werden.";
          }
        } 

          
        //dbx_debug("EDIT-Order Datum=($datum) Pat=($pat) PK=($pk) Kasse=($kasse) GebDat=($gebdat) Praxis=($praxis) "); 
        if ($pk == 'a') $pk='k'; 
        $praxis=dbx_get_cfg('myOrderLDT','praxis');
        $oForm->set_post('praxis',$praxis);   
        $oForm->set_post('pk'    ,$pk);
        $oForm->set_post('datum' ,$datum);
        $oForm->set_post('praxis',$praxis);
        $oForm->set_post('bsnr'  ,$bsnr);
        $oForm->set_post('lanr'  ,$lanr);

      

        $ok=0;
        $errors=$oForm->_errors;      
        //dbx_debug("#Form Errors=",$errors); 


        if (!$oForm->errors()) {
          $ok=$oForm->save_post($dd,$rid);
          //dbx_debug("Save Order set set_date =last");
          dbx_set_Remember('set_date','last');          
        }
        if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
        if (!$ok) $oForm->_msg_error     = "Daten konnten nicht gespeichert werden";
        



        //if (!$ok) $oForm->_general_error = 'xFehlerx';
        //$oForm->add_fld_error('bsnr',"Tragen sie die BSNR und LANR des Einsenders ein.");
 
        
        if ($oForm->errors()) {
          $oForm->_msg_error = 'Prüfen sie bitte ihre Eingaben.';
        }

      }  
    } 
    //dbx_debug ("###PATIENT DATA",$oForm->_data);
    if (!$rid) $rid=$oForm->_data['id']; // nach dem speichern
    dbx_set_Remember('my_order-activ_id',$rid);
    
    if ($rid) { //$data['id']) {
      $oForm->add_action('edit_anf','modul|button_edit_anf', '&dbx_work=edit_anf&rid='.$rid);  
    } else {
      $oForm->add_obj('edit_anf','modul|button_edit_anf_wait'); 
    }

    $pat_datum=dbx_get_webDate($data['datum']);
    $pat_pat  =$data['pat']; 
    $pat_name =$data['vorname'].' '.$data['nachname'];
    $pat_geb  =dbx_get_webDate($data['gebdat']);
    $pat_ges  =dbx_get_webDateTime($data['gesendet']);
    $pat_info="$pat_datum $pat_pat $pat_name $pat_geb ";
 

    $oForm->_msg_info  = "Sie können die Laboranforderungen <b>bearbeiten</b>. ($pat_info)";
    if (!$can_edit) { 
      $pat_info.= " Gesendet=$pat_ges";
      $oForm->_msg_info  = "Anforderungen wurden schon an das Labor <b>gesendet</b>. Bearbeiten nicht möglich.  ($pat_info)";
      $oForm->add_js("disable_form('dbx_form_{i}');");
    }

    $content=$oForm->run();
    //$top_import_edit="<br>";

    if ($rid && $do=='new') {
      $content='<br><div class="container">'.$content.'</div>';
     }
    return $content;
  }




  function delete_selected($ids,$obj) {

    $oDB     = dbx_get_sys_object('dbxDB');
    $oTPL    = dbx_get_sys_object('dbxTPL');
    $dd      = 'my_order';
    $count   = 0;

    $data['msg']="Es können nur ungesendete Formulare gelöscht werden.";
    $msg=$oTPL->get_tpl('dbx','alert-primary',$data);
    $content=$msg; 

    
    if (is_array($ids)) {
      $count   = count($ids);
      foreach ($ids as $id => $x) {
         $pat_name='wurde schon gelöscht';
         $pat=$oDB->select1($dd,$id);
         if (is_array($pat)) {
          $pat_name='Anforderung=('. $pat['pat'].') '.$pat['vorname'].' '.$pat['nachname'].' '.dbx_get_webDate($pat['gebdat']);
          $gesendet=trim($pat['gesendet']); 
          if ($gesendet == '') {
            $ok=$ok=$oDB->delete($dd,$id);
            if ($ok) {
              $obj->del_multi_selects($id);
              $dat['msg']="Lösche Anforderung von ($pat_name) gesendet=($gesendet).";
              $msg=$oTPL->get_tpl('dbx','alert-success',$dat);
            } else {
              $dat['msg']="Lösche Anforderung von ($pat_name) nicht möglich.";
              $msg=$oTPL->get_tpl('dbx','alert-danger',$dat);
            }
          } else {
            $gesendet=dbx_get_webDateTime($gesendet);
            $dat['msg']="Lösche Anforderung von ($pat_name) nicht möglich, da schon gesendet ($gesendet).";
            $msg=$oTPL->get_tpl('dbx','alert-warning',$dat);
          }

         } else {
           $obj->del_multi_selects($id);
           $dat['msg']="Lösche Anforderung von ID ($id) nicht möglich, da schon gelöscht.";
           $msg=$oTPL->get_tpl('dbx','alert-warning',$dat);
         }
         $content.=$msg; 
      }
   } 
   if (!$count) {
    $dat['msg']="Es wurden keine Patienten ausgewählt.";
    $msg=$oTPL->get_tpl('dbx','alert-warning',$dat);
    $content.=$msg; 
   }
   return $content;
  }


  function reset_selected($ids) {

    $oDB     = dbx_get_sys_object('dbxDB');
    $oTPL    = dbx_get_sys_object('dbxTPL');
    $dd      = 'my_order';
    $today   = date('Y-m-d', time());  

    $data['msg']="Es können nur bei Formularen von <b>Heute</b> die gesendet Kennung entfernt werden.";
    $msg=$oTPL->get_tpl('dbx','alert-primary',$data);
    $content=$msg; 
    $count=0;
    
    if (is_array($ids)) {
      $count   = count($ids);
      foreach ($ids as $id => $x) {
         $pat_name='unbekannt';
         $pat=$oDB->select1($dd,$id);
         if (is_array($pat)) {
          $pat_name=$pat['pat'].' '.$pat['vorname'].' '.$pat['nachname'].' '.dbx_get_webDate($pat['gebdat']);
          $date    =$pat['datum'];
          $gesendet=trim($pat['gesendet']); 
          if ($gesendet > '') { // && $date == $today) {
            $field_values['id']=$id;
            $field_values['gesendet']='';
            $ok=$ok=$oDB->update($dd,$field_values,$id);
            if ($ok) {
              $dat['msg']="Entferne Status gesendet von ($pat_name) gesendet=($gesendet).";
              $msg=$oTPL->get_tpl('dbx','alert-success',$dat);
            } else {
              $dat['msg']="Entfernen vom Status gesendet von ($pat_name) nicht möglich.";
              $msg=$oTPL->get_tpl('dbx','alert-danger',$dat);
            }
          } else {
            $dat['msg']="Entfernen vom Status gesendet von ($pat_name) nicht möglich, da <b>unversendet</b>.";
            $msg=$oTPL->get_tpl('dbx','alert-info',$dat);           
          }

         } else {
           $dat['msg']="Anforderung von ID ($id) nicht gefunden.";
           $msg=$oTPL->get_tpl('dbx','alert-warning',$dat);
         }
         $content.=$msg; 
      }
   } 
   if (!$count) {
    $dat['msg']="Es wurden keine Patienten ausgewählt.";
    $msg=$oTPL->get_tpl('dbx','alert-warning',$dat);
    $content.=$msg; 
   }
   return $content;
  }


  public function send_selected($ids) {

    $oDB        = dbx_get_sys_object('dbxDB');
    $oTPL       = dbx_get_sys_object('dbxTPL');
    $dd         = 'my_order';
    $today      = date('Y-m-d');
    $today_time = date('Y-m-d H:i:s'); 
    $praxis     = dbx_get_cfg('myOrderLDT','praxis'); 

    $data['msg']="Praxis ($praxis). Es können nur <b>unversendete</b> Formulare von <b>Heute</b> an das Labor gesendet werden.";
    $msg=$oTPL->get_tpl('dbx','alert-primary',$data);
    $content=$msg; 

    $ok=0; $count=0; $msg='';

    if (is_array($ids)) $count = count($ids);
    
    if ($count > 0) {
      foreach ($ids as $id => $x) {

        $ok=0; $error='';
        $anf=''; $geb=''; $gesendet=''; $pra=0; $pat=0; $date=''; $msg=''; $pat_name='unbekannt';
         
        $record=$oDB->select1($dd,$id);
        if (is_array($record)) {
          $pat_name='Anforderung=('. $record['pat'].') '.$record['vorname'].' '.$record['nachname'].' '.dbx_get_webDate($record['gebdat']);
          $pra     =$record['praxis'];
          $pat     =$record['pat'];
          $date    =$record['datum'];
          $gesendet=$record['gesendet']; 
          $anf     =$record['anforderungen'];
          $geb     =$record['gebdat'];
          if ($anf == 'a:0:{}') $anf='';
          
          if ($pra == $praxis && $pat > '0' && $date == $today && $gesendet == '' && $anf > ' ') {
            //$content.="DO ID=($id) ($pat_name) Anf=($anf) Geb=($geb)";

            $ok=$this->create_order($id);
            if (!$ok) $error.='Fehler beim <b>Erstellen</b> der LDT-Datei';
            if ($ok)  {
                $ok=$this->send_order_id($record);
                if (!$ok) $error.='Fehler beim <b>Senden</b> der LDT-Datei';
            }  
            if ($ok) {
              $dat['msg']="Sende ($pat_name) an das Labor.";
              $msg=$oTPL->get_tpl('dbx','alert-success',$dat);
              $xbef['gesendet']= $today_time;
              $ok=$oDB->update($dd,$xbef,$id);
            } 
            if (!$ok) {
                $dat['msg']="Senden  von ($pat_name) nicht möglich. $error";
                $msg=$oTPL->get_tpl('dbx','alert-danger',$dat);
            }
            $content.=$msg;
            
            if ($pra != $praxis) {
              $error.="Fehler Praxis ($pra) ist nicht ($praxis)";
              $dat['msg']="Senden  von ($pat_name) nicht möglich. $error";
              $msg=$oTPL->get_tpl('dbx','alert-danger',$dat);
              $content.=$msg;
              $ok=1; // Hilfsweise, um die weiteren Fehler nicht auch noch anzuzeigen
            }
          
            if ($pat <= '0')     $error.="Anforderungs-Nr=($pat). ";
            if ($anf <= ' ')     $error.="Keine Anforderungen.($anf) ";
            if ($geb <= ' ')     $error.="Keine Geburtsdatum. ($geb) ";
            if ($date != $today) $error.="Anforderung ist nicht von Heute. ($date) ";
            if ($gesendet > ' ') $error.="Anforderung wurde schon gesendet ($gesendet). ";
  
            if ($error > '') {  
              $dat['msg']="Senden  von ($pat_name) nicht möglich. $error";
              $msg=$oTPL->get_tpl('dbx','alert-warning',$dat);
              $content.=$msg;
            } 
          }
        } // is record 

      } // foreach
   }
   if ($count <= 0) {
    $dat['msg']="Es wurden keine Patienten ausgewählt.";
    $msg=$oTPL->get_tpl('dbx','alert-warning',$dat);
    $content.=$msg; 
  }
   return $content;
}






  public function list_order() {
    $modal_content=''; $info=''; $msg=''; $msg_mode='info';
    dbx_set_Remember('dbx_load_pat',1);
     
    $today   = date('Y-m-d', time());  

    $oReport = new dbxReport_Order;
    $oDB     = dbx_get_sys_object('dbxDB');
    $lng     = dbx_get_ModulVar('lng','de');
    $dd      = 'my_order';
    $form_id = 'report-order';
    $oReport->init($form_id);
    

    $do      =dbx_get_ModulVar('dbx_do'  ,'','parameter'); 
    $rid     =dbx_get_ModulVar('rid'     ,0 ,'int');
  
    if ($do == 'row_copy' && $rid) {
      $ok=0; 
      $rec=$oDB->select1($dd,$rid);
      if (is_array($rec)) {
        $info= dbx_get_webDate($rec['datum']).' '.$rec['vorname'].' '.$rec['nachname'].' Geb.:'.dbx_get_webDate($rec['gebdat']);
        $rec['id']      = 0;
        $rec['datum']   =$today; 
        $rec['gesendet']='';
        $rec['pat']     ='';
        $ok=$oDB->insert($dd,$rec,0);
        if ($ok)   $msg = "Anforderung von ($info) wurden zum heutigen Datum als unversendet kopiert.";
      }
      if (!$ok)  $msg = "Anforderung von ($info) konnten nicht kopiert werden.";
      $oReport->del_multi_selects($rid);
      $do=0; // inline kein modal content
      if (!$ok) $msg_mode='warning';
  }



    if ($do == 'row_delete' && $rid) {
        $ok=0; 
        $rec=$oDB->select1($dd,$rid);
        if (is_array($rec)) {
          $info= dbx_get_webDate($rec['datum']).' '.$rec['vorname'].' '.$rec['nachname'].' Geb.:'.dbx_get_webDate($rec['gebdat']);
          if (!$rec['gesendet']) {
            $ok=$oDB->delete($dd,$rid);
            if (!$ok) $msg = "Anforderung von ($info) konnte nicht gelöscht werden";
            if ( $ok) $msg = "Anforderung von ($info) gelöscht.";
          } else {

            $msg = "Anforderung von ($info) kann nicht gelöscht werden, da schon versendet.";
          }
        } else {
          $msg= "Zeile mit id ($rid) nicht vorhanen.";
        }
        $oReport->del_multi_selects($rid);
        $do=0; // inline kein modal content
        if (!$ok) $msg_mode='warning';
    }


    if ($do) {
      $out=0; 
      
      switch ($do) {
                
        case 'row_edit':
          $modal_content=$this->edit_order();
          $out=1; 
        break;


        case 'laufzettel':
          $obj=dbx_get_Modul_include_object('laufzettel');
          $modal_content=$obj->run();
          $out=1; 
        break;

        case 'send_order':
          $ids=$oReport->get_multi_selects();
          $modal_content=$this->send_selected($ids);
          $out=1; 
        break;

        case 'reset_selected':
          $ids=$oReport->get_multi_selects();
          $modal_content=$this->reset_selected($ids);
          $out=1; 
        break;

        case 'delete_selected':
          $ids=$oReport->get_multi_selects();
          $modal_content=$this->delete_selected($ids,$oReport);
          $out=1; 
        break;


        
      } // switch

      if ($out) return $modal_content;

    } 

  

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
 

    $options_rsort['id']        = 'ID';
    $options_rsort['datum']     = 'Datum';
    $options_rsort['gesendet']  = 'Gesendet';

    $today   = date('Y-m-d', time()); 
    $sel_date=$today;
    $class_haeder['ldt']='no-sort'; 

    $data['dbx_rrows']= 100;
    $data['dbx_rsort']='id';
    $data['sel_date'] = $sel_date;

    
    $oReport->_data=$data;
    $oReport->_action='?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=list_order'; // set_action() cid 'new' or record.id
    $oReport->_options_rsort = $options_rsort;
    $oReport->_but_pagination   =7;
    $oReport->_create_row_select=1;
    $oReport->_create_row_copy  =0; 
    $oReport->_create_row_edit  =1;
    $oReport->_create_row_delete=1;
    $oReport->_create_sel_flds  =1;    
 
    $oReport->_msg_info=''; //'Liste der Patienten für Laboranforderungen.  Wählen Sie bitte das Datum aus.';
    $oReport->_msg_success='';

    $oReport->_tabel_tpls['tpl_row_edit']   = 'table_row_modal1-edit';
    $oReport->_tabel_tpls['tpl_row_copy']   = 'confirm_row_copy';
    $oReport->_tabel_tpls['tpl_row_delete'] = 'confirm_row_delete';
    $oReport->_tabel_tpls['tpl_row_select'] = 'table_row_select-multi-noval';
    $oReport->_class_haeder                 = $class_haeder; 
    $oReport->_activ_id                     = dbx_get_Remember('my_order-activ_id',0,'int');

    $today_user=$oReport->php_date_usr($today);
    $oReport->_msg_confirm_copy='Möchten Sie den Patient mit seinen Anforderungen kopieren ?<br>Die Anforderung wird als unversendet zum <b>heutigen Datum</b> kopiert.';
    



    $sel1['action']='?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=list_order&sdate=today';
    $sel1['msg']   ='Nur Heute ('. $today_user .')';

    $sel2['action']='?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=list_order&sdate=clear';
    $sel2['msg']   ='Ab Datum';

    $oReport->add_fld('sel_today' ,'modul|button_report',$sel1);
    $oReport->add_fld('sel_clear' ,'modul|button_report',$sel2);


    $rpt_format['datum']   ='php-date-usr';
    $rpt_format['gebdat']  ='php-date-usr';
    $rpt_format['gesendet']='php-datetime-usr';
    $oReport->_rpt_format=$rpt_format;
       


     $add_order['dbx_get']='?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=add_order';
     $add_order['label']='Neuer Patient / Anforderungen';
     $oReport->add_obj('add_order','button-modal1',$add_order);
     

     $todo=$oDB->count('my_order',"datum == '$today' and pat > 0 and nachname > ' ' and gebdat > '1900-01-01' and anforderungen > ' ' and gesendet <= '1900-01-01' ");
     $alle=$oDB->count('my_order',"datum == '$today' ");
     $send=$oDB->count('my_order',"datum == '$today' and gesendet > '1900-01-01' ");
     $rest=($alle - $send);
    

     $send_order['dbx_get'] = '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=send_order';
     $send_order['label']   = "Heute von ($alle) Formularen ($send) gesendet.";
     $oReport->add_obj('send_order','button-modal1',$send_order);

     // - -- -- 

     $laufzettel['dbx_get']  = '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=laufzettel';
     $laufzettel['label']    = "Laufzettel drucken Patienten";
     $oReport->add_obj('laufzettel','button-modal1',$laufzettel);

     $laufzettel['dbx_get']  = '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=list_order&dbx_do=send_order';
     $laufzettel['label']    = "Anforderungen ans Labor senden";
     $oReport->add_obj('senden_selected','button-modal1',$laufzettel);

    
     $senden_reset['dbx_get']= '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=list_order&dbx_do=reset_selected';
     $senden_reset['label']  = "Status auf ungesendet zurücksetzen";
     $oReport->add_obj('senden_reset','button-modal1',$senden_reset);    

     $delete['dbx_get']      = '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=list_order&dbx_do=delete_selected';
     $delete['label']        = "Patienten/Anforderungen löschen";
     $oReport->add_obj('delete','button-modal1',$delete);   
     


     //senden_reset

     //delete


     $modal1['title']     ='LDA Anforderungen';     
     $modal1['on_close']= " dbxReSendForm('#dbx_form_{i}')"; //"dbx_reload('?');";
     $modal1['class']     ='modal-xxl';
     
     $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);
     $oReport->add_obj('modal1','obj-value',$modal_content);


     $confirm['class']  ='dbxConfirm';
     $confirm['confirm']='Zeilen löschen';
     $confirm['title']  ='Anforderungen löschen ?';
     $oReport->add_action('rows_delete' ,'action_button_delete' ,'&dbx_do=multi_delete',$confirm);  // data-confirm_title="Eintrag löschen:" data-confirm="{confirm}"
 

     if($oReport->submit()) {
       if($oReport->errors()) {      // submit && no errors
          $oReport->_msg_error = 'Prüfen sie bitte ihre Eingaben';
       }
     }  else { // no submit
       // nothing to do;
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



    $rwhere="datum <= '$date' ";
    $sdate =dbx_get_ModulVar('sdate',dbx_get_Remember('set_date'),'parameter');

    //dbx_debug("get set_date=($sdate) datum=($date)");

    if ($sdate) {
        //dbx_debug("SETZE DATUM für sel ($sdate) datum=($date)");
        if ($sdate == 'today') {
          $date = $today;
          $rwhere="datum = '$date' ";
          dbx_set_Remember('set_date','today');
        }     
        if ($sdate ==  'last') {
          $last=$oDB->select1('my_order',"datum <= '2099-31-12'",'datum','datum','DESC');
          if (is_array($last)) $date=$last['datum'];
          $sdate='clear';
        }            
        if ($sdate ==  'clear') {
          $rwhere="datum <= '$date' ";
          dbx_set_Remember('set_date','');
        }    
        //dbx_set_Remember('set_date','');
    } 
  
    $oReport->set_sel('sel_date',$date);        

     if ($select) $rwhere=$oReport->add_rwhere_select($rwhere); 

     /*
     // Ein Test falls nichts gefunden wird
     $oReport->_rcount=$oDB->count($dd,$rwhere);
     if (!$oReport->_rcount) {
        dbx_set_Remember('set_date','');
        $oReport->del_multi_selects('*');
        $rwhere='id > 0'; // fallback, nichts gefunden, dann alles ! (keine leere Liste, wenn Daten vorhanden)
     }
     */


     $oReport->_rcount=$oDB->count($dd,$rwhere);
     $oReport->_rdata =$oDB->select($dd,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);
     
     $oReport->add_fld('sel_date','date-label-group-prompt','','date', 'Ab Datum','Von Datum');
     $oReport->add_js_call('dbx_table','datatable1');
     //$oReport->add_js("datatable_fix('#dbx_table_{i}')",100); // work arround hack

     if ($msg) {
       $msg=$oReport->get_tpl('alert-'.$msg_mode,"msg=$msg");
     }
     $oReport->add_obj('msg','obj-value',$msg);

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



  public function create_order_ldt($order) {
    $ok=1;
    $syspraxis=dbx_get_cfg('myOrderLDT','praxis');
    $oLDT  = dbx_get_Modul_include_object('LDT');
    $today = date('Y-m-d', time());
    $praxis= sprintf("%03d", $order['praxis']);
    $pat   = sprintf("%03d", $order['pat']); 
    $file  = $praxis.'_'.$pat.'-'.$today.'.ldt';
    $path_file = dbx_get_base_dir().'files/myOrder/send-order/'.$file;
    if ($praxis == $syspraxis) {
      $ldt=$oLDT->get_ldt_anforderung($order);      
      $ldt = iconv("UTF-8", "CP437//TRANSLIT", $ldt); // Zeichensatz DOS IBM 
      file_put_contents($path_file,$ldt);
      dbx_debug("#Write-LDT=($path_file)");
    } else {
      dbx_debug("#ERROR Praxis Sys=($syspraxis) Data=($praxis) Pat=($pat)");
    }
    return $ok;
  }



  public function create_order($id=0) {
     $ok=0;
     $today = date('Y-m-d', time());
     $praxis=dbx_get_cfg();
     
     $oDB=dbx_get_sys_object('dbxDB');     
     $tosend=$oDB->count('my_order',"datum = '$today' and pat > 0 and gebdat > '1900-01-01' and anforderungen > ' ' and anforderungen <> 'a:0:{}' and gesendet <= '1900-01-01' ");
 
     if($tosend > 0) { 
      if (!$id) { 
        $orders=$oDB->select('my_order',"datum = '$today' and pat > 0 and gebdat > '1900-01-01' and anforderungen > ' ' and anforderungen <> 'a:0:{}' and gesendet <= '1900-01-01' ");
        foreach ($orders as $order) { 
          $ok=$this->create_order_ldt($order);
          dbx_debug("Multi create_order ok=($ok)  id=($id) ");
        }
      } 
      if ($id) {
        $order=$oDB->select1('my_order',$id);
        if (is_array($order)) {
          $ok=$this->create_order_ldt($order);
          dbx_debug("Single create_order ok=($ok) id=($id)");
        }         
      }
     }
 
      

     return $ok;  
  }

  public function send_order_id($order) {

    $host=dbx_get_cfg('dbx','sftp_host');
    $user=dbx_get_cfg('dbx','sftp_user');
    $pass=dbx_get_cfg('dbx','sftp_pass');
    $port=dbx_get_cfg('dbx','sftp_port');


   // $sftp = new \phpseclib3\Net\SFTP($host);
   // $sftp->login($user, $pass);
   // $sftp->chdir('order');     



    dbx_debug("#SFTP# Login (myOrderLDT->myOrder->run)");

    $attempts = 0;
    $maxRetries =7;
    $connected = false;
         

    while ($attempts < $maxRetries && !$connected) {
        try {
            dbx_debug("#SFTP# Try connect ($attempts)"); 
            $sftp = null;
            $sftp = new \phpseclib3\Net\SFTP($host);
            if (!$sftp->login($user, $pass)) {
                throw new \RuntimeException('Login failed');
            }
            $sftp->chdir('/order');
            $connected = true; // Verbindung erfolgreich
        } catch (\Exception $e) {
            $attempts++;
            $sftp = null; // Verbindung zurücksetzen
            if ($attempts >= $maxRetries) {
                throw new \RuntimeException('Error reading from socket after ' . $maxRetries . ' attempts: ' . $e->getMessage());
            }
        }
    }


    $today = date('Y-m-d', time());
    $praxis= sprintf("%03d", $order['praxis']);
    $pat   = sprintf("%03d", $order['pat']); 
    $file  = $praxis.'_'.$pat.'-'.$today.'.ldt';

    $file_local       =dbx_get_base_dir().'files/myOrder/send-order/'.$file;
    $file_local_crypt =dbx_get_base_dir().'files/myOrder/send-order/'.$file.'.crypt';
    $file_local_done  =dbx_get_base_dir().'files/myOrder/send-order/.done/'.$file;
    $file_remote=$file.'.crypt';

    $file_local      =dbx_os_path_file($file_local);
    $file_local_crypt=dbx_os_path_file($file_local_crypt);
    $file_local_done =dbx_os_path_file($file_local_done);

    dbx_debug("File_local =$file_local"); 
    dbx_debug("File_crypt =$file_local_crypt");
    dbx_debug("File_remote=$file_remote");


    $ldtx=file_get_contents($file_local);
    $ldtx=dbx_crypt($ldtx,$file.'.crypt');  
    $ok=file_put_contents($file_local_crypt,$ldtx);


    $ok=$sftp->put($file_remote, $file_local_crypt, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE); // upload a file with the content of the file

    return $ok;
  }   




  public function send_order() { 
    $content=''; 
    $today     = date('Y-m-d', time()); 
    $today_time= date('Y-m-d H:i:s', time()); 
    $oTPL=dbx_get_sys_object('dbxTPL');

    $oDB=dbx_get_sys_object('dbxDB');
    

    $alle  =$oDB->count('my_order',"datum == '$today'");
    $tosend=$oDB->count('my_order',"datum == '$today' and pat > 0 and nachname > ' ' and gebdat > '1900-01-01' and anforderungen > ' ' and anforderungen <> 'a:0:{}'  and gesendet <= '1900-01-01' ");
    $issend=$oDB->count('my_order',"datum == '$today' and  gesendet > '1900-01-01' ");
     

    $nosend=($alle - $tosend - $issend);

    $ok=$this->create_order();

    //$content.="Alle=($alle) ToSend=($tosend) gesendet=($issend) nosend=($nosend) <br>";

    $msg['msg'] = "Es gibt Heute ($alle) Patienten.";
    $content.=$oTPL->get_tpl('dbx','alert-info',$msg); 
   
    if ($issend) {
      $msg['msg'] = "Es wurden Heute für ($issend) Patienten/Anforderungen an das Labor gesendet.";
      $content.=$oTPL->get_tpl('dbx','alert-info',$msg); 
    }
    if ($nosend) {
      $msg['msg'] = "Es gibt Heute ($nosend) Patienten mit fehlenden Daten/Anforderungen.";
      $content.=$oTPL->get_tpl('dbx','alert-warning',$msg); 
    }
    if ($tosend) {
      $msg['msg'] = "Es gibt Heute ($tosend) Patienten mit Anforderungen, die an das Labor übermittelt werden.";
      $content.=$oTPL->get_tpl('dbx','alert-success',$msg);
    }



    //$host='home22904123.1and1-data.host';
    //$user='acc241427790';
    //$pass='Bentox64!#Lda-sftp-2023';
    //$port= 22;

    $host=dbx_get_cfg('dbx','sftp_host');
    $user=dbx_get_cfg('dbx','sftp_user');
    $pass=dbx_get_cfg('dbx','sftp_pass');
    $port=dbx_get_cfg('dbx','sftp_port');




    $file_path  = dbx_get_base_dir().'files/myOrder/send-order/';
 
    $files = scandir($file_path);
    $files = array_diff($files, array('.', '..','.save'));   
    // Display the list of files

    $sftp = new \phpseclib3\Net\SFTP($host);
    $sftp->login($user, $pass);
    $sftp->chdir('order');

    $count_send=0;

    foreach ($files as $file) {

        $ldt=0; $ok=0;


        $ext=$this->get_file_info($file,'ext'); 

        //dbx_debug("File=($file) Ext=($ext)",$files );
        if ($ext=='.ldt') $ldt=1;



        if ($ldt) {

            $file_local       =dbx_get_base_dir().'files/myOrder/send-order/'.$file;
            $file_local_crypt =dbx_get_base_dir().'files/myOrder/send-order/'.$file.'.crypt';
            $file_local_done  =dbx_get_base_dir().'files/myOrder/send-order/.done/'.$file;
            $file_remote=$file.'.crypt';

            $file_local      =dbx_os_path_file($file_local);
            $file_local_crypt=dbx_os_path_file($file_local_crypt);
            $file_local_done =dbx_os_path_file($file_local_done);

            //dbx_debug("File_local =$file_local"); 
            //dbx_debug("File_crypt =$file_local_crypt");
            //dbx_debug("File_remote=$file_remote");


            $ldtx=file_get_contents($file_local);
            $ldtx=dbx_crypt($ldtx,$file.'.crypt');  
            $ok=file_put_contents($file_local_crypt,$ldtx);


            $ok=$sftp->put($file_remote, $file_local_crypt, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE); // upload a file with the content of the file


            if ($ok) {
                $pra=$this->get_file_info($file,'praxis');
                $pat=$this->get_file_info($file,'pat');
                $dat=$this->get_file_info($file,'dat'); 

                //$content.="<br>File=($file) Praxis=($pra) Pat=($pat) Dat=($dat)<br>";
       
                $field_values['gesendet']=$today_time; 
                $where="datum = '$today' and praxis = '$pra' and pat = '$pat' ";

                $ok=$oDB->update('my_order',$field_values,$where); 
                if ($ok) {
                  if (file_exists($file_local))  $ok=rename($file_local, $file_local_done); //  unlink($file_local); // #todo #do it 
                  $count_send++;
                }
                if (file_exists($file_local_crypt)) unlink($file_local_crypt); // #todo #do it 
                $pra=sprintf("%03d", $pra);
                $pat=sprintf("%03d", $pat);
                $msg['msg'] = "Anforderungen für ($pra-$pat) übermittelt.";
                $content.=$oTPL->get_tpl('dbx','alert-success',$msg);
          
                //$content.="Send Praxis=($pra) Pat=($pat) Dat=($dat) where=($where) time=($today_time) save=($ok)<br>"; 
                

            } else {
              $msg['msg'] = "Fehler bei Übertragung ($file).";
              $content.=$oTPL->get_tpl('dbx','alert-danger',$msg);   
            }
        }
    }   
    
    if ($count_send) {
      dbx_set_Remember('last_export_anforderung_date' ,$today_time);    
      dbx_set_Remember('last_export_anforderung_count',$count_send);    
    }

    if ($count_send) { 
        $msg['msg'] = "Es wurden Anforderungen für (<b>$count_send</b>) Patienten an das Labor übermittelt.";
        $content.=$oTPL->get_tpl('dbx','alert-info',$msg);
    }    
    if (!$count_send) {
      $msg['msg'] = "Es wurden <b>keine neuen</b> Anforderungen an das Labor übermittelt.";
      $content.=$oTPL->get_tpl('dbx','alert-warning',$msg);
    }

    return $content;
  }

  // Formulare - - - - - - - - - - - - - - - - - - 


  public function edit_anf_igel() {
    $oForm=dbx_get_Modul_include_object('form_igel');
    return $oForm->run(); 
  }

 
  public function edit_anf_lda() {
    $oForm=dbx_get_Modul_include_object('form_lda');
    return $oForm->run(); 
  }
 


  // - - - - - - - - - - - - - - - - - - 

  public function edit_anf_m10a() {
    $oForm=dbx_get_Modul_include_object('form_m10a');
    return $oForm->run(); 
  } 

  public function edit_anf_m10aIgel() {  
    $oForm=dbx_get_Modul_include_object('form_m10aIgel');
    return $oForm->run(); 
  } 

  public function edit_anf_f1() {
    $oForm=dbx_get_Modul_include_object('form_f1');
    return $oForm->run(); 
  } 

  // =============================================

   public function run() {
    $modul=dbx_get_SysVar('dbx_activ_modul');
    $work =dbx_get_ModulVar('dbx_work');
    $content="myOrder.class Work=($work)";
    switch ($work) {
 
      case 'laufzettel':
         $obj=dbx_get_Modul_include_object('laufzettel');
         $content=$obj->run();
      break;  

      case 'add_pat':
        $pat_id=0;
        $path=dbx_get_cfg('myOrderLDT','import_pat');
        $file=dbx_get_ModulVar('pat_file',0,'filename');
        $exist=false;
        $path_file=dbx_os_path_file($path.$file);
        if ($file) {
          if (file_exists($path_file)) $exist=true;
        }
        //return "<br>read: ($path_file)";
        
        
        if ($file && $exist) {
          $obj=dbx_get_Modul_include_object('importPat');
          $pat_id=$obj->import($path_file);
          if ( $pat_id) dbx_redirect('?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=edit_order&dbx_do=new&rid='.$pat_id);
          if (!$pat_id) {
            $oTPL=dbx_get_sys_object('dbxTPL');
            $msg['msg']="Patienten Daten von Praxis Software einlesen.<br>Keine Patient in Import Datei gefunden!";
            $content=$oTPL->get_tpl('dbx','alert-warning',$msg);              
          }
        } 
        if (!$file) { 
          $oTPL=dbx_get_sys_object('dbxTPL');
          $msg['msg']="Patienten Daten von Praxis Software einlesen.<br>Keine Import Datei ($file) angegeben!";
          $content=$oTPL->get_tpl('dbx','alert-warning',$msg);
        } 
        if ($file && !$exist) { 
          $oTPL=dbx_get_sys_object('dbxTPL');
          $msg['msg']="Patienten Daten von Praxis Software einlesen.<br>Import Datei ($path_file)  nicht vorhanden!";
          $content=$oTPL->get_tpl('dbx','alert-warning',$msg);
        } 
        break; 

        case 'send_order':
          dbx_set_Remember('dbx_load_pat',0);
          $content=$this->send_order();
        break; 

 
        case 'add_order':
          dbx_set_Remember('dbx_load_pat',0);
          $content=$this->add_order();     
        break; 

        case 'edit_order':
          dbx_set_Remember('dbx_load_pat',0);
          $content=$this->edit_order();     
        break; 

        case 'list_order':
          $content=$this->list_order();     
        break; 

        case 'load_pat':
          $content=$this->add_order();     
        break; 

        case 'edit_anf':
          //dbx_debug("EDIT_ANF GET POST=",$_GET,$_POST);
          $ok  =1; $count=0;
          $pk  =dbx_get_PostGetVar('pk'           ,0 ,'parameter');
          $id  =dbx_get_PostGetVar('rid'          ,0 ,'integer');
          $date=dbx_get_PostGetVar('datum'        ,0 ,'date');
          $anf =dbx_get_PostGetVar('anforderungen','','list');


          $oDB =dbx_get_sys_object('dbxDB'); 
          $today=date('Y-m-d');

          $where = "id = $id";  
          if ($pk && $id) {

            if (isset($_POST['pat'])) {
              $pat  =dbx_get_PostGetVar('pat'  ,''    ,'integer');
              $datum=dbx_get_PostGetVar('datum',$today,'date');

              if ($pat > '') {
                $xwhere="datum = '$datum' and  pat = '$pat' and id <> $id ";
                $count=$oDB->count('my_order',$xwhere);
                dbx_debug("Edit-Anf COUNT Datum=($datum) PAT=($pat) Count=($count)");
                if ($count) {
                  $content=$this->edit_order();  // keine doppelte pat Nummern erlauben
                  return $content;  
                } 
              } 


            }
            $where = "id = $id";
            $ok=$oDB->save('my_order',$_POST,$where); 

             //if (!$count) $ok=$oDB->save('my_order',$_POST,$where);   // save($dd,$field_values,$where,$verify_access=1,$verify_fields=1,$verify_values=1,$trace=1) {
             //if ( $count) $ok=0;//dbx_debug("SAVE ORDER ($id)=($ok)"); 
          }
          if (!$ok) {
            $oTPL=dbx_get_sys_object('dbxTPL');
            $msg['msg']="Patient ID=($id) konnte nicht gespeichert werden.";
            //$content=$oTPL->get_tpl('dbx','alert-warning',$msg);
            $content=$this->edit_order();     
            return $content;
          }
          if ($ok) { 
            $must_save=0;
            $now_time = date('H:i');
            $rec=$oDB->select1('my_order',$where);
            if ($rec['datum']==$today) {
               if (!$rec['abdatum']) {
                  $rec['abdatum']=$today;
                  $must_save=1;
               }
               if (!$rec['abzeit']) {
                $rec['abzeit']  = $now_time;
                $must_save=1;
               }
               if ($must_save) {
                $ok=$oDB->save('my_order',$rec,$where,0); 
               }
            }

            $content='Unbekannte Erfassungsart=('.$rec['formular'].')';
            if ($rec['formular'] == 'm10aIgel')   $content=$this->edit_anf_m10aIgel();
            if ($rec['formular'] == 'm10a')       $content=$this->edit_anf_m10a();
            if ($rec['formular'] == 'igel')       $content=$this->edit_anf_igel();
            if ($rec['formular'] == 'lda')        $content=$this->edit_anf_lda();
          }       
        break; 
        
        case 'edit_anf_m10aIgel':
          $content=$this->edit_anf_m10aIgel();
        break;

        case 'edit_anf_m10a':
          $content=$this->edit_anf_m10a();
        break;  
        
        case 'edit_anf_igel':
           $content=$this->edit_anf_igel();
        break;  

        case 'edit_anf_lda':
          $content=$this->edit_anf_lda();
          break;  
  

        case 'edit_anf_f1':
          $content=$this->edit_anf_f1();
       break;  


       default:
        dbx_set_Remember('dbx_load_pat',1);
        $oTPL=dbx_get_sys_object('dbxTPL');
        $msg['msg']="Modul=($modul) Work=($work) is undef.";
        $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // switch()


     return $content;
   } // run

} // class


