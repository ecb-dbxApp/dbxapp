<?php
namespace dbx\dbxAdmin;

Class dbxConfig {

   private function get_new_cfg() {
      $config=array();
      $config['active']  =  0;
      $config['version'] = '1';
      $config['groups']  = 'admin';
      return $config;
   }


   public function edit_config() {
      $xmodul=dbx()->get_modul_var('xmodul');
      $data  =dbx()->get_cfg($xmodul, '', null, true);
      if (!is_array($data)) $data=$this->get_new_cfg();

      //dbx_debug("GET CFG ($xmodul)=",$data);

      $o_form=dbx()->get_system_obj('dbxForm');
      $o_form->init('form-config-edit', 'form-config-edit');
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=config&dbx_run2=edit&xmodul='.$xmodul);
      $o_form->set_data($data);
      $o_form->set_data_definition('cfg:'.$xmodul);
      $o_form->_fld_change_state='*';
      $o_form->add_rep('bar_title', 'Konfiguration: ' . $xmodul);

      foreach ($data as $fld => $value) {
         $o_form->add_fld($fld);
      }
      $save_btn = dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|button-submit', array('label' => 'Modul (' . $xmodul . ') Config speichern'));
      $o_form->add_obj('button','obj-value', $save_btn);
      $o_form->add_obj('bar_actions', 'obj-value', $save_btn);
      
      if ($o_form->submit()) {
         $config=$o_form->validated_post();
         $ok=dbx()->set_cfg($xmodul,$config); 
      }   

      $content=$o_form->run();

      return $content;

     // 'form-config-edit'
   } 

   
   public function check_edit() {
      $xmodul=dbx()->get_modul_var('xmodul');
      $content='';

      switch ($xmodul) {

         case 'dbx':
             $o_form=dbx()->get_include_obj('dbxConfig_dbx');
             $content=$o_form->run();  
         break;

         case 'dbxContent':
             $o_form=dbx()->get_include_obj('dbxConfig_dbxContent');
             $content=$o_form->run();
         break;
  
  
         default:
            $content=$this->edit_config();
  
      }
      return $content;

   }


   public function run() {
    $modul =dbx()->get_modul_var('dbx_modul');
    $action=dbx()->get_modul_var('dbx_run1');
    $work  =dbx()->get_modul_var('dbx_run2'); 
    $content="dbxAdmin->dbxConfig ($action) ($work) <br>";

    switch ($work) {

       case 'edit':
          $content=$this->check_edit();
       break;


       default:
          $o_tpl=dbx()->get_system_obj('dbxTPL');
          $msg['msg']="Modul=($modul) Action=($action) Work=($work) is undef!";
          $content=$o_tpl->get_tpl('dbx|alert-warning',$msg);

    }
    return $content;
 }

}
