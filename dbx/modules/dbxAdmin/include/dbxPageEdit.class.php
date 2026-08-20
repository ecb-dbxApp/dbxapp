<?php
namespace dbx\dbxAdmin;

Class dbxPageEdit {

   private function save_tpl($type,$design,$file,$lng,$content) {
      $config=dbx()->get_cfg('dbx');
      $default_lng=$config['default_lng'];
      dbx()->debug("SAVE-TPL T=($type) F=($file) D=($design) L=($lng)");
      $file_type=$file.'.'.$type;
      $dir_file=dbx()->get_base_dir()."dbx/design/$design/$type/$file";
      if ($lng > '' && $lng != $default_lng) {
         $dir_file.='_'.$lng;
      }
      $dir_file.='.'.$type;
      //dbx_debug("#TPL-SAVE=($dir_file)");
      $dir_file=dbx()->os_path($dir_file);
      file_put_contents($dir_file,$content);
      //dbx_debug($tpl);
      return $dir_file;
   }


   public function run($rid=0) {
      $content=''; $ok=false;

      $o_tpl  =dbx()->get_system_obj('dbxTPL');

      $uid   =dbx()->user();
      $design=dbx()->get_modul_var('design');
      $file  =dbx()->get_modul_var('file');
      $lng   =dbx()->get_modul_var('lng');
      $type  ='htm';

      dbx()->set_system_var('dbx_page' ,'_pageedit');
      dbx()->set_system_var('dbx_editor',1);
      dbx()->set_system_var('dbx_window',1);

      //dbx_debug("Uid=($uid)  Design=($design) File=($file) Design=($design) Lng=($lng)");

      //$tpl=$o_tpl->read_tpl($file,$type,$modul,$design,$lng,0,0,0,0);
      $path_file=$o_tpl->get_design_tpl_dir_file('htm',$design,$file);
      $tpl=$o_tpl->get_design_tpl($design,$file,$lng,'htm',0);


      $data['tpl']    = $tpl;
      $data['file']   = $file;
      $data['design'] = $design;
      $data['lng']    = $lng;
      $data['type']   = $type;

      $o_form=dbx()->get_system_obj('dbxForm');
      $o_form->init('form-page-edit', 'form-page-edit');

      $o_form->set_data($data);
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=_editpage');
      $o_form->_msg_info  = 'TPL: '.$path_file;
      //$oForm->_editor_fld='tpl';

      $o_form->add_fld('design','text-label'   ,rules: 'parameter' ,label: 'TPL-Design');     //#+
      $o_form->add_fld('lng'   ,'text-label'   ,rules: 'parameter' ,label: 'TPL-Language');   // |
      $o_form->add_fld('file'  ,'text-label'   ,rules: 'parameter' ,label: 'TPL-File');
      $o_form->add_fld('tpl'   ,'textarea-tpl' ,rules: '*'         ,label: 'TPL-Content',data: 'rows=24');
      $o_form->add_obj('button_save','button-submit','label=Speichern');             //#+
      $o_form->add_js_call('tpl','editor-ace'); 

      if($o_form->submit()) {
        $o_form->_msg_success = "No change: ($file)"; 
        if($o_form->changed()) {      // submit && no errors // we ignore warnings
           $tpl   =$o_form->get_post('tpl','','*');
           $file  =$o_form->get_post('file');
           $design=$o_form->get_post('design');
           $lng   =$o_form->get_post('lng');
           if (!$o_form->errors()) {
             $path_file=$this->save_tpl($type,$design,$file,$lng,$tpl);
             if (!$path_file) $o_form->_msg_error = "Error: ($path_file)";
             if ( $path_file) {
               $o_form->_msg_success = "Save: ($path_file)";
             }
           } else {
             $o_form->_msg_success   = '#no_change#';
           }
        } else {
           $o_form->_msg_error = '#check_input#';
        }
      }
      $content= $o_form->run();
      dbx()->set_system_var('dbx_editor',0);
      //$content=dbx()->norep($content);
      return $content;
   } // run()


}

?>
