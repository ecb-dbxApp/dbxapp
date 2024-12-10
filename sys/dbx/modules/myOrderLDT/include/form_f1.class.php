<?php
namespace dbx\myOrderLDT;





Class form_f1 extends editAnf {

 
  // - - - - - - - - - - - - - - - - - - 

  public function edit() {
     $content='Keine Patienten Daten. Bitte erst einen Patienten erfassen.';
     $work=dbx_get_ModulVar('dbx_work',0,'parameter');
     $today=date('Y-m-d');

     $oForm= dbx_get_sys_object('dbxForm');
     $oDB  = dbx_get_sys_object('dbxDB');
     $rid  = dbx_get_ModulVar('rid',0,'int');
     $autoSubmit=1;

     if ($rid) {
      $content=''; $options_groups=array();
      $dd   = 'my_order';
      $data = $oDB->select1($dd,$rid);
      $pat  = $data['vorname'].' '.$data['nachname'].' '.$oForm->php_date_usr($data['gebdat']);

      $methoden=$oDB->select('lda_methoden','pos10a > 0 ');
      //$igel    =$oDB->select('lda_methoden',"posigel > '0' and pos10a <= '0' ");
      $igel=array();  

      $oForm->init('form-anforderungen-f1');
      $oForm->_fld_change_state='*';

      $oForm->_data       = $data;
      $oForm->_msg_info   = "Anforderungen von ($pat) bearbeiten";
      $oForm->_msg_success= "Anforderungen von ($pat) gespeichert";
      $oForm->_dd         = $dd; // Main db-Table
      $oForm->_action     = '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=edit_anf_f1&rid='.$rid;
      
      $edit_pat=array();
      $edit_pat['href'] = '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=edit_order&rid='.$rid;
      $edit_pat['label']= "Daten des Patienten bearbeiten";
      $oForm->add_obj('edit_pat','button',$edit_pat);

      $list_pat=array();
      $list_pat['href'] = '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=list_order';
      $list_pat['label']= "Liste der des Patienten";
      $oForm->add_obj('list_pat','button',$list_pat);

      //add_fld($name,$tpl='dd:',$data='dd:',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='',$remap='')
 


                    $fld['style'] ="";
                    $fld['obj-x'] ='330px';
                    $fld['obj-y'] ='360px';
                    $fld['obj-w'] ='300px';
                    $fld['obj-h'] ='86px';
                    $fld['drag']='draggable'; 
                    $fld['name'] ='test1';
                    $fld['placeholder']='platzhalter Aaaa';
                    $fld['value'] ='Mein Wert Aaa';
                    $fld['required']='';
                    $fld['tooltip']='';
                    $fld['class']='w100';
                    $fld['label']='Label Top';
  
                   $oForm->add_fld('f1','dbx|drag-text-label-top',$fld);                


    

  
                  $fld['style'] ='';
                  $fld['obj-x'] ='300px';
                  $fld['obj-y'] ='60px';
                  $fld['obj-w'] ='300px';
                  $fld['obj-h'] ='48px';
                  $fld['drag']='draggable'; 
                  $fld['name'] ='test2';
                  $fld['placeholder']='platzhalter A';
                  $fld['value'] ='Mein Wert A';
                  $fld['required']='';
                  $fld['tooltip']='Bitte Daten sorgfälltig eingeben.';
                  $fld['class']='';
                  $fld['label']='Label left';

      $oForm->add_fld('f2','dbx|drag-text-label-left',$fld);


                    $fld['style'] ="";
                    $fld['obj-x'] ='200px';
                    $fld['obj-y'] ='320px';
                    $fld['obj-w'] ='400px';
                    $fld['obj-h']='66px';
                    $fld['drag']='no-drag'; 
                    $fld['name'] ='test3';
                    $fld['placeholder']='platzhalter B';
                    $fld['value'] ='Kein Drag';
                    $fld['required']='';
                    $fld['tooltip']='';
                    $fld['class']='w100';
                    $fld['label']='Label right';


      $oForm->add_fld('f3','dbx|drag-text-label-right',$fld);




      if ($oForm->submit()) {
        if (!$oForm->errors()) {      // submit && no errors && no warnings

          $post=$_POST;
          //$post=$oForm->_post;

          //dbx_debug("##POST-ANF  ANF=",$post);  
          $anf=array();
          $anforderungen=''; $new_profile=''; $old_profile=''; $newprofile=''; $oldprofile=''; $plus=''; $minus='';

          foreach ($post as $no => $sel) {
              $nox=substr($no,1);
              if (dbx_is_integer($nox)) { 
                $anf[]=$sel;
              } 
          } 


          if (!isset($post['profile'])) $post['profile']='';
          $old_profile=$data['profile'];
          $new_profile=$post['profile'];
          $old_anford =$data['anforderungen'];
     

          if (!is_array($old_profile)) $old_profile=explode(',',$old_profile);
          if (!is_array($new_profile)) $new_profile=explode(',',$new_profile);
          if (!is_array($old_anford))  $old_anford =explode(',',$old_anford);

          $plus =array_diff($new_profile,$old_profile);
          $minus=array_diff($old_profile,$new_profile);
           

          foreach ($minus as $no => $profil) {
             if ($profil) {
               $parameters=$this->get_profil_parameter($profil);
               //dbx_debug("MINUS Parameter from profil ($profil)",$parameters,$anf);
               $anf=array_diff($anf,$parameters);
             }
          }

          foreach ($plus as $no => $profil) {
            if ($profil) {
              $parameters=$this->get_profil_parameter($profil);
              $anf=array_merge($anf,$parameters);
            }
          }

          $anf=array_unique($anf);
        
          $record['id']=$rid;
          $record['anforderungen']=$anf; // orderungen;
          $record['profile']      =$new_profile;
          
          $oForm->_post=$record;

          $ok=$oDB->save($dd,$record,$rid);
          $data = $oDB->select1($dd,$rid);
          $count_anforderungen=$this->get_count_anf($data['anforderungen']);
          if ($count_anforderungen && $data['datum']==$today && $data['pat']<='0' ) {
            $pat=$this->set_next_patid($rid);
          }


          //$count_anforderungen=$this->get_count_anf($data['anforderungen']);
          //$count_profile      =$this->get_count_anf($data['profile']);
          //if ( $ok) $oForm->_msg_success   = "Daten gespeichert von ($pat) Anforderungen=($count_anforderungen) Profile=($count_profile)";
          $oForm->_msg_success=$oForm->_msg_info;
          if (!$ok) $oForm->_msg_success   = "Daten konnten nicht gespeichert werden ($pat)";


          $oForm->_data = $data;
           
 
        } else {
          $oForm->_msg_error = 'Prüfen sie bitte ihre Eingaben';
        } 
      }
    } else {
        $oForm->add_obj('form_msg','obj-value','');
    }

    
    //dbx_debug("#METHODEN=",$methoden);

    
    $anforderungen = $data['anforderungen'];
    //dbx_debug("Anforderungen",$anforderungen);

    foreach ($methoden as $no => $methode) {
      $pos =$methode['pos10a'];
      $abk =$methode['abk'];
      $nam =$methode['name'];

      $val=$this->get_check_anf($anforderungen,$abk);
      //dbx_debug("Anforderung=($abk) Select=($val) ANFO=($anforderungen)");
      
      //$cdata['name'] =$abk;
      $cdata['label']  ="($pos) $nam";
      $cdata['value']  =$abk;
      $cdata[$abk]     =$val;
      $cdata['checked']='';
      $cdata['class']  ='changeSubmit cb-size-1';
      if ($val) $cdata['checked']='checked';
      // $data[$abk]=$val; // Value 
      // add_fld($name,$tpl,$data='dd:',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='') { //#
      //$oForm->add_fld('a'.$pos,'modul|checkbox-anforderung',$cdata,'*',$abk);

    }

 


    $profile=array();
    $profilex=$oDB->select('my_profile');
    foreach ($profilex as $no => $record) {
      $name       =$record['profil'];
      $bezeichnung=$record['bezeichnung']; 
      $profile[$name]=$bezeichnung;
    }  

    // Igel
    //$methoden=$oDB->select('lda_methoden',"posigel > 0");
    
    $pro['size']   = 32;
    $pro['class']  ='itemSubmit';
    $multible=$oForm->get_tpl('select-multible',$pro);

    //$oForm->add_fld('profile','select-multible-label',$profile);
    $oForm->add_fld('profile',$multible,$profile,class: 'changeSubmit');

    $js='nix';
    $oForm->add_js_call('profile_{i}','multiselect');
    if ($autoSubmit) {
      $js ="$('.changeSubmit').change(function() { dbx_sync_form=0; $('#dbx_form_{i}').submit(); });\n";
      $js.="$('.itemSubmit').mousedown(function() {dbx_sync_form=1; $('#dbx_form_{i}').submit(); });";
      $oForm->add_js($js);                //add_js($js,$time=0,$ready=1) {
    }
    $content=$oForm->run();
    return $content;  
  }

  // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -






   public function run() {
      return $this->edit();
   } // run

} // class


