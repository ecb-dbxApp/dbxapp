<?php
namespace dbx\dbxAdmin;

Class dbxConfig {

   Private function Get_ConfigModule() {
     $xmodul  = dbx()->get_modul_var('modul','undef');
     $config  = dbx()->get_cfg($xmodul, '', null, true);
     $tpl     ='Form-dbxConfig';
     $oForm=dbx()->get_system_obj('dbxForm');
     $oForm->_dbData  =$config;
     $content=$oForm->run('dbxConfig',$tpl);

     return $content;
   }

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

      $oForm=dbx()->get_system_obj('dbxForm');
      $oForm->init('form-config-edit');
      $oForm->_action='?dbx_modul=dbxAdmin&dbx_run1=config&dbx_run2=edit&xmodul='.$xmodul;
      $oForm->_data=$data;
      $oForm->_dd  ='cfg:'.$xmodul;
      $oForm->_fld_change_state='*';
      $oForm->add_rep('bar_title', 'Konfiguration: ' . $xmodul);

      foreach ($data as $fld => $value) {
         $oForm->add_fld($fld);
      }
      $saveBtn = dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|button-submit', array('label' => 'Modul (' . $xmodul . ') Config speichern'));
      $oForm->add_obj('button','obj-value', $saveBtn);
      $oForm->add_obj('bar_actions', 'obj-value', $saveBtn);
      
      if ($oForm->submit()) {
         $config=$oForm->_post;
         $ok=dbx()->set_cfg($xmodul,$config); 
      }   

      $content=$oForm->run();

      return $content;

     // 'form-config-edit'
   } 

   
   public function check_edit() {
      $xmodul=dbx()->get_modul_var('xmodul');
      $content='';

      switch ($xmodul) {

         case 'dbx':
             $oForm=dbx()->get_include_obj('dbxConfig_dbx');
             $content=$oForm->run();  
         break;

         case 'dbxContent':
             $oForm=dbx()->get_include_obj('dbxConfig_dbxContent');
             $content=$oForm->run();
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
          $oTPL=dbx()->get_system_obj('dbxTPL');
          $msg['msg']="Modul=($modul) Action=($action) Work=($work) is undef!";
          $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

    }
    return $content;
 }

}
