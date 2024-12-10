<?php
namespace dbx\dbxAdmin;

Class dbxPageEdit {

   private function save_tpl($type,$design,$file,$lng,$content) {
      $config=dbx_get_cfg('dbx');
      $default_lng=$config['default_lng'];
      dbx_debug("SAVE-TPL T=($type) F=($file) D=($design) L=($lng)");
      $file_type=$file.'.'.$type;
      $dir_file=dbx_get_base_dir()."dbx/design/$design/$type/$file";
      if ($lng > '' && $lng != $default_lng) {
         $dir_file.='_'.$lng;
      }
      $dir_file.='.'.$type;
      //dbx_debug("#TPL-SAVE=($dir_file)");
      $dir_file=dbx_os_path_file($dir_file);
      file_put_contents($dir_file,$content);
      //dbx_debug($tpl);
      return $dir_file;
   }


   public function run($rid=0) {
      $content=''; $ok=false;

      $oTPL  =dbx_get_sys_object('dbxTPL');

      $uid   =dbx_get_CurrentUser();
      $design=dbx_get_ModulVar('design');
      $file  =dbx_get_ModulVar('file');
      $lng   =dbx_get_ModulVar('lng');
      $type  ='htm';

      dbx_set_SysVar('dbx_page' ,'_pageedit');
      dbx_set_SysVar('dbx_editor',1);
      dbx_set_SysVar('dbx_window',1);

      //dbx_debug("Uid=($uid)  Design=($design) File=($file) Design=($design) Lng=($lng)");

      //$tpl=$oTPL->read_tpl($file,$type,$modul,$design,$lng,0,0,0,0);
      $path_file=$oTPL->get_design_tpl_dir_file('htm',$design,$file);
      $tpl=$oTPL->get_design_tpl($design,$file,$lng,'htm',0);


      $data['tpl']    = $tpl;
      $data['file']   = $file;
      $data['design'] = $design;
      $data['lng']    = $lng;
      $data['type']   = $type;

      $oForm=dbx_get_sys_object('dbxForm');
      $oForm->init('form-page-edit');

      $oForm->_data      = $data;
      $oForm->_action    ='?dbx_modul=dbxAdmin&dbx_action=_editpage';   // &modul='.$modul.'&file='.$file.'&design='.$design.'&lng='.$lng;
      $oForm->_msg_info  = 'TPL: '.$path_file;
      //$oForm->_editor_fld='tpl';

      $oForm->add_fld('design','text-label'   ,''       ,'parameter' ,'TPL-Design');     //#+
      $oForm->add_fld('lng'   ,'text-label'   ,''       ,'parameter' ,'TPL-Language');   // |
      $oForm->add_fld('file'  ,'text-label'   ,''       ,'parameter' ,'TPL-File');
      $oForm->add_fld('tpl'   ,'textarea-tpl' ,'rows=24','*'         ,'TPL-Content');
      $oForm->add_obj('button_save','button-submit','label=Speichern');             //#+
      $oForm->add_js_call('tpl','editor-ace'); 

      if($oForm->submit()) {
        $oForm->_msg_success = "No change: ($file)"; 
        if($oForm->changed()) {      // submit && no errors // we ignore warnings
           $tpl   =$oForm->get_post('tpl','','*');
           $file  =$oForm->get_post('file');
           $design=$oForm->get_post('design');
           $lng   =$oForm->get_post('lng');
           if (!$oForm->errors()) {
             $path_file=$this->save_tpl($type,$design,$file,$lng,$tpl);
             if (!$path_file) $oForm->_msg_error = "Error: ($path_file)";
             if ( $path_file) {
               $oForm->_msg_success = "Save: ($path_file)";
             }
           } else {
             $oForm->_msg_success   = '#no_change#';
           }
        } else {
           $oForm->_msg_errr = '#check_input#';
        }
      }
      $content= $oForm->run();
      dbx_set_SysVar('dbx_editor',0);
      //$content=dbx_add_norep($content);
      return $content;
   } // run()


}

?>