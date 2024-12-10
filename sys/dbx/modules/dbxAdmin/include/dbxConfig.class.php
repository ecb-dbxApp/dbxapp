<?php
namespace dbx\dbxAdmin;

Class dbxConfig {

   Private function Get_ConfigModule() {
     $xmodul  = dbx_get_ModulVar('modul','undef');
     $config  = dbx_get_cfg($xmodul);
     $tpl     ='Form-dbxConfig';
     $oForm=dbx_get_sys_object('dbxForm');
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
      $xmodul=dbx_get_ModulVar('xmodul');
      $data  =dbx_get_cfg($xmodul);
      if (!is_array($data)) $data=$this->get_new_cfg();

      //dbx_debug("GET CFG ($xmodul)=",$data);

      $oForm=dbx_get_sys_object('dbxForm');
      $oForm->init('form-config-edit');
      $oForm->_action='?dbx_modul=dbxAdmin&dbx_action=config&dbx_work=edit&xmodul='.$xmodul;
      $oForm->_data=$data;
      $oForm->_dd  ='cfg:'.$xmodul;
      $oForm->_fld_change_state='*';

      
      
      foreach ($data as $fld => $value) {
         $oForm->add_fld($fld);
      }
      $oForm->add_obj('button','button-submit',"label=Modul ($xmodul) Config speichern");
      
      if ($oForm->submit()) {
         $config=$oForm->_post;
         if (isset($config['form-config-edit'])) unset($config['form-config-edit']);  
         $ok=dbx_set_cfg($xmodul,$config); 
      }   

      $content=$oForm->run();

      return $content;

     // 'form-config-edit'
   } 

   
   public function check_edit() {
      $xmodul=dbx_get_ModulVar('xmodul');
      $content='';

      switch ($xmodul) {

         case 'dbx':
             $oForm=dbx_get_Modul_include_object('dbxConfig_dbx');
             $content=$oForm->run();  
         break;
  
  
         default:
            $content=$this->edit_config();
  
      }
      return $content;

   }


   public function run() {
    $modul =dbx_get_ModulVar('dbx_modul');
    $action=dbx_get_ModulVar('dbx_action');
    $work  =dbx_get_ModulVar('dbx_work'); 
    $content="dbxAdmin->dbxConfig ($action) ($work) <br>";

    switch ($work) {

       case 'edit':
          $content=$this->check_edit();
       break;


       default:
          $oTPL=dbx_get_sys_object('dbxTPL');
          $msg['msg']="Modul=($modul) Action=($action) Work=($work) is undef!";
          $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

    }
    return $content;
 }

}