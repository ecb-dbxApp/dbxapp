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
      $oTPL  =dbx()->get_system_obj('dbxTPL');

      dbx()->set_system_var('dbx_page' ,'_tpledit');
      dbx()->set_system_var('dbx_editor',1);

      //dbx_debug("#### EDIT#### Uid=($uid) Type=($type) Modul=($modul) File=($file) ");
      //return "edit=$file";

      $tpl=$oTPL->read_tpl($modul,$file,$type);

      //dbx_debug("#tpl  ($modul,$file,$type)",$tpl);

      $data['tpl']    = $tpl;
      $data['file']   = $file;
      $data['modul']  = $modul;
     

      $oForm=dbx()->get_system_obj('dbxForm');
      $oForm->init('form-tpl-edit');

      $oForm->_data      = $data;
      $oForm->_action    ='?dbx_modul=dbxAdmin&dbx_run1=_edittpl';   // &modul='.$modul.'&file='.$file.'&design='.$design.'&lng='.$lng;
      $oForm->_msg_info  = 'TPL: '.$modul.'/'.$file.'.'.$type;
      //$oForm->_editor_fld='tpl';

      $oForm->add_fld('modul' ,'text-label'   ,rules: 'parameter' ,label: 'TPL-Modul');
      $oForm->add_fld('file'  ,'text-label'   ,rules: 'parameter' ,label: 'TPL-File');
      $oForm->add_fld('tpl'   ,'textarea-tpl' ,rules: '*'         ,label: 'TPL-Content',data: 'rows=22');
      $oForm->add_obj('button_save','dbx|button-submit','label=Speichern');             //#+


      $oForm->add_js_call('tpl','editor-ace');  // -ace

      if($oForm->submit()) {
        if($oForm->changed()) {      // submit && no errors // we ignore warnings
           $tpl   =$oForm->get_post('tpl','','*');
           $file  =$oForm->get_post('file');
           $design=$oForm->get_post('design');
           $lng   =$oForm->get_post('lng');
           if (!$oForm->errors()) {
             $path_file=$this->save_tpl($type,$modul,$file,$design,$lng,$tpl);
             if (!$path_file) $oForm->_msg_error = '#error_save_data#';
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
      $content=dbx()->norep($content);
      dbx()->set_system_var('dbx_editor',0);

      //dbx_debug("#RETURN-CONTENT=",$content);


      return $content;
   } // run()


}

?>