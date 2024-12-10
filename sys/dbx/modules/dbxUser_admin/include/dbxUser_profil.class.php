<?php
namespace dbx\dbxUser_admin;

Class dbxUser_profil extends \dbxObj {


   public function run($action) {


      $content=''; $options_groups=array();
      $oForm=dbx_get_sys_object('dbxForm');
      $oDB  =dbx_get_sys_object('dbxDB');
      $dd   ='dbx_user';
      $oForm->init('form-profil');
      $rid=$oForm->get_rid();

      //dbx_debug("### USER EDIT ID=($rid)  Action=($action) ####");
      $data=$oDB->select1('dbx_user',$rid);


      $user_groups=$oDB->select('dbx_user_groups','active = 1');
      foreach ($user_groups as $no => $record) {
        $id    =$record['name'];
        $group =$record['description'];
        $options_groups[$id]=$group;
      }
    
      $oForm->_data      = $data;
      $oForm->_msg_info  = 'Sie können ein Profildaten bearbeiten';
      $oForm->_dd        = $dd;
   
      $oForm->_action    = '?dbx_modul=dbxUser_admin&dbx_action=user&dbx_work=edit_profil&rid='.$rid;

      $options_land['']  ='Auswahl...';
      $options_land['de']='Deutschland';
      $options_land['us']="Unites States";


      $oForm->add_fld('uname'    ,'text-label' );
      $oForm->add_fld('id'       ,'text-label' );
      $oForm->add_fld('userid'   ,'text-label' );
      $oForm->add_fld('pass'     ,'text-label' );
      $oForm->add_fld('name'     ,'text-label' );
      $oForm->add_fld('name2'    ,'text-label' );
      $oForm->add_fld('telefon'  ,'text-label' );
      $oForm->add_fld('handy'    ,'text-label' );
      $oForm->add_fld('email'    ,'text-label' );
      $oForm->add_fld('strasse'  ,'text-label' );
      $oForm->add_fld('plz'      ,'text-label' );
      $oForm->add_fld('ort'      ,'text-label' );

      $oForm->add_fld('land'     ,'select-single-label',$options_land);
      $oForm->add_fld('roles'    ,'multi-select-label' ,$options_groups,'array|parameter',class: 'dbxMultiSelect2');

      //public function add_fld($name,$tpl,$data='',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='') { //#


      //$oForm->_select2=true; // Multiselect 


      if($oForm->submit()) {
      	if(!$oForm->errors()) {      // submit && no errors && no warnings
           $change=$oForm->changed();
           if ($change) {
             $rid=$oForm->get_Post('id'    ,0 ,'int');
             $uid=$oForm->get_Post('userid',0 ,'int');
             $pas=$oForm->get_Post('pass'  ,'','password');
             $len=strlen($pas);
             if ($len <= 32) $oForm->_post['pass']=md5($pas);
             if (!$uid)      $oForm->_post['userid']=$rid;


             $ok=$oForm->save_post('dbx_user',$rid);
             if ($ok) {
                $rid=$oForm->_rid;
                if (!$oForm->_data['userid']) {
                   $oForm->_post['userid']=$rid;
                   $ok=$oForm->save_post($dd,$rid);
                }
             }
             if ( $ok) $oForm->_msg_success   = 'Daten gespeichert';
             if (!$ok) $oForm->_msg_success   = 'Daten konnten nicht gespeichert werden';
           } else {
             $oForm->_msg_success   = 'Keine Änderung';
           }
      	} else {
           $err_flds='';
           $errors=$oForm->_errors;
           foreach ($errors as $key => $value) {
             $err_flds.=$key.' ';
           }
           $oForm->_msg_error = 'Prüfen sie bitte ihre Eingaben ('.$err_flds.')';
        }
      }
      //dbx_debug ("###USER-ADMIN DATA",$oForm->_data);
      $rid=$oForm->_data['id']; // nach dem speichern
      $oForm->add_obj('obs_rid','dbx|observe',"name=usr_rid&value=$rid"); // wird von avatar upload überwacht (observed)
      $oForm->add_js_call('roles','multiselect2');


      $content=$oForm->run();

      return $content;
   }



}




?>