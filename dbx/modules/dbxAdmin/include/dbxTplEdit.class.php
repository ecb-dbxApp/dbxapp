<?php
namespace dbx\dbxAdmin;

Class dbxTplEdit {

   private function save_tpl($type,$modul,$file,$design,$lng,$tpl) {
      //dbx_debug("SAVE-TPL T=($type) M=($modul) F=($file) D=($design) L=($lng)");
      $file_type=$file.'.'.$type;
      //if ($modul == 'dbx') $dir_file=dbx()->get_base_dir()."dbx/tpl/$type/$file_type";
      //if ($modul != 'dbx') 
      $dir_file=dbx()->get_base_dir()."dbx/modules/$modul/tpl/$type/$file_type";
      $dir_file=dbx()->os_path($dir_file);
      file_put_contents($dir_file,$tpl);
      return $dir_file;
   }


   public function run($rid=0) {
      $content=''; $ok=false;
      $uid   =dbx()->user();
      $modul =dbx()->get_request_var('modul' ,''   ,'parameter');
      $type  =dbx()->get_request_var('type'  ,'htm','parameter');
      $file  =dbx()->get_request_var('tpl'   ,''   ,'parameter');
      $o_tpl  =dbx()->get_system_obj('dbxTPL');

      dbx()->set_system_var('dbx_page' ,'_tpledit');
      dbx()->set_system_var('dbx_editor',1);

      //dbx_debug("#### EDIT#### Uid=($uid) Type=($type) Modul=($modul) File=($file) ");
      //return "edit=$file";

      $tpl=$o_tpl->read_tpl($modul,$file,$type);

      //dbx_debug("#tpl  ($modul,$file,$type)",$tpl);

      $data['tpl']    = $tpl;
      $data['file']   = $file;
      $data['modul']  = $modul;
     

      $o_form=dbx()->get_system_obj('dbxForm');
      $o_form->init('form-tpl-edit', 'form-tpl-edit');

      $o_form->set_data($data);
      $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=_edittpl');
      $o_form->_msg_info  = 'TPL: '.$modul.'/'.$file.'.'.$type;
      //$oForm->_editor_fld='tpl';

      $o_form->add_fld('modul' ,'text-label'   ,rules: 'parameter' ,label: 'TPL-Modul');
      $o_form->add_fld('file'  ,'text-label'   ,rules: 'parameter' ,label: 'TPL-File');
      $o_form->add_fld('tpl'   ,'textarea-tpl' ,rules: '*'         ,label: 'TPL-Content',data: 'rows=22');
      $o_form->add_obj('button_save','dbx|button-submit','label=Speichern');             //#+


      $o_form->add_js_call('tpl','editor-ace');  // -ace

      if($o_form->submit()) {
        if($o_form->changed()) {      // submit && no errors // we ignore warnings
           $tpl   =$o_form->get_post('tpl','','*');
           $file  =$o_form->get_post('file');
           $design=$o_form->get_post('design');
           $lng   =$o_form->get_post('lng');
           if (!$o_form->errors()) {
             $path_file=$this->save_tpl($type,$modul,$file,$design,$lng,$tpl);
             if (!$path_file) $o_form->_msg_error = '#error_save_data#';
             if ( $path_file) {
               $o_form->_msg_success = "Save: ($path_file)";
             }
           } else {
             $o_form->_msg_success   = '#no_change#';
           }
        } else {
           $o_form->_msg_errr = '#check_input#';
        }
      }
      $content= $o_form->run();
      $content=dbx()->norep($content);
      dbx()->set_system_var('dbx_editor',0);

      //dbx_debug("#RETURN-CONTENT=",$content);


      return $content;
   } // run()


}

?>
