<?php
namespace dbx\myOrderLDT;





Class form_lda extends editAnf {


 
  // - - - - - - - - - - - - - - - - - - 

  public function edit() {
 
    $content='Keine Patienten Daten. Bitte erst einen Patienten erfassen.';
    $work=dbx_get_ModulVar('dbx_work',0,'parameter');
    $today=date('Y-m-d');
    
    $oForm= dbx_get_sys_object('dbxForm');
    $oDB  = dbx_get_sys_object('dbxDB');
    $dd   = 'my_order';
    $rid  = dbx_get_ModulVar('rid',0,'int');
    $praxis=dbx_get_cfg('myOrderLDT','praxis');
    $autoSubmit=1;
    $data = $oDB->select1($dd,$rid);


    $methoden=$oDB->select('lda_methoden','poskarte > 0 ','abk,name','name');
    foreach ($methoden as $no => $record) {
        $id    =$record['abk'];
        $bez  = $record['name'];
        if ($bez[0] != '-') {
            if (strpos($bez, 'Profil') === false)  $options_methoden[$id]=$bez;
        }
    }
    
    $pat = $data['vorname'].' '.$data['nachname'].' '.$oForm->php_date_usr($data['gebdat']);
    $can_edit = empty($data['gesendet']) ? 1 : 0;




    $oForm->init('form-anforderungen-lda');
    $oForm->_data = $data;
    $oForm->_dd   = $dd; // Main db-Table
    $oForm->_data = $data;
    $oForm->_msg_info   = "Anforderungen von ($pat) bearbeiten";
    $oForm->_msg_success= "Anforderungen von ($pat) gespeichert";
    $oForm->_action     = '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=edit_anf_lda&rid='.$rid;   
    $oForm->_fld_change_state='*'; 
    $oForm->_tpl_fld_info='';
 
    $edit_pat=array();
    $edit_pat['href'] = '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=edit_order&rid='.$rid;
    $edit_pat['label']= "Daten des Patienten bearbeiten";
    $oForm->add_obj('edit_pat','button',$edit_pat);

    $list_pat=array();
    $list_pat['href'] = '?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=list_order';
    $list_pat['label']= "Liste der des Patienten";
    $oForm->add_obj('list_pat','button',$list_pat);    

    //$oForm->add_obj('save','button-submit','label=speichern'); 

    if ($rid) {


     if ($oForm->submit()) {
       if (!$oForm->errors()) {      // submit && no errors && no warnings

        $post=$_POST;
         //$post=$oForm->_post;

        //dbx_debug("##POST-ANF  ANF=",$post);  

        $new_profile=''; $old_profile=''; $plus=''; $minus='';

      
        if (!isset($post['profile'])) $post['profile']='';
        $old_profile=$data['profile'];
        $new_profile=$post['profile'];
        $old_anford =$data['anforderungen'];
    

        if (!is_array($old_profile)) $old_profile=explode(',',$old_profile);
        if (!is_array($new_profile)) $new_profile=explode(',',$new_profile);
        if (!is_array($old_anford))  $old_anford =explode(',',$old_anford);

        $plus =array_diff($new_profile,$old_profile);
        $minus=array_diff($old_profile,$new_profile);
        $anf  =dbx_get_PostGetVar('anforderungen','','*');

        if (!is_array($anf)) $anf=array();
        
        //dbx_debug("OLD_profil=",$old_profile);
        //dbx_debug("NEW_profil=",$new_profile);
        //dbx_debug("ANF=",$anf); 


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
        dbx_debug("save-anf=",$anf);
        
        $record=array();
        $record['id']           =$rid;
        $record['praxis']       =dbx_get_cfg('myOrderLDT','praxis');
        $record['anforderungen']=$anf; // orderungen;
        $record['profile']      =$new_profile;
         
     
        dbx_debug("ANF speichern=",$record);


        $ok=$oDB->save($dd,$record,$rid);
        $data = $oDB->select1($dd,$rid);
        //$anf  = $data['anforderungen'];
        $oForm->_data=$data;
        $_POST['anforderungen']=$anf;
        $count_anforderungen=$this->get_count_anf($data['anforderungen']);
        if ($count_anforderungen && $data['datum']==$today && $data['pat']<='0' ) {
          $pat=$this->set_next_patid($rid);
        }
        if ( $ok) $oForm->_msg_success = "Daten gespeichert von ($pat)";
        if (!$ok) $oForm->_msg_error   = "Daten konnten nicht gespeichert werden ($pat)";
        if (!$ok) $oForm->_general_error=1;
       } else {
         $oForm->_msg_error = 'Prüfen sie bitte ihre Eingaben';
       } 
     }
   } else {
       $oForm->add_obj('form_msg','obj-value','Kein Patient');
   }

   
   //dbx_debug("#METHODEN=",$methoden);



   $profile=array();
   $profilex=$oDB->select('my_profile');
   foreach ($profilex as $no => $record) {
     $name       =$record['profil'];
     $bezeichnung=$record['bezeichnung']; 
     $profile[$name]=$bezeichnung;
   }  

   $oForm->add_fld('anforderungen' ,'multi-select-label' ,$options_methoden,'array|parameter',class: 'dbxMultiSelect2 searchable');
   
   $pro['size']   = 20;
   $pro['class']  ='itemSubmit';
   $multible=$oForm->get_tpl('select-multible-label',$pro);


   $oForm->add_fld('profile',$multible,$profile,class: 'changeSubmit');

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

   $oForm->add_js_call('profile_{i}'      ,'multiselect');
   $oForm->add_js_call('anforderungen_{i}','multiselect2');
   $oForm->add_js("dbx_MultiSelect('profile_{i}','dbx_form_{i}');\n");
   
   if ($autoSubmit) {
     $js ="$('.itemSubmit').mousedown(function() {dbx_sync_form=1; $('#dbx_form_{i}').submit(); });\n";
     $js.="autosave_multiselect('anforderungen_{i}','dbx_form_{i}');\n";
     $oForm->add_js($js);                //add_js($js,$time=0,$ready=1) {
   }



   //$js ="$('.changeSubmit').change(function()  { dbx_sync_form=0; $('#dbx_form_{i}').submit(); });\n";
 

   $js ="$('.MultiSelect2').change(function() { dbx_sync_form=0; $('#dbx_form_{i}').submit(); });\n";
   $oForm->add_js($js);

   
   $content=$oForm->run();
   return $content;  
 }

  // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -






   public function run() {
      return $this->edit();
   } // run

} // class


