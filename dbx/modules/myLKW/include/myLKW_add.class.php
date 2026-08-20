<?php
namespace dbx\myLKW;

class myLKW_add {


    public function run() {
       dbx()->debug("myLKWD_add");
        $o_db=dbx()->get_system_obj('dbxDB');
        $dd = 'lkw';
        $rid= 0;
        
        $data=$o_db->empty_record($dd);
     
        $o_form=dbx()->get_system_obj('dbxForm');
        $o_form->init('form-add');
        $o_form->set_action('?dbx_modul=myLKW&dbx_run1=add_lkw');
        $o_form->set_data($data);
        $o_form->add_module_bar('Añadir vehículo', 'bi-truck');

        $o_form->add_fld('TIPO'  ,'text-label'   ,rules: 'alphanum|min=1',label: 'TIPO'   ,errormsg: 'Fahrzeug Art'); //#+
        $o_form->add_fld('TRACTOR','text-label'  ,rules: 'alphanum|min=1',label: 'TRACTOR',errormsg: 'Fahrzeug Traktor'); //#+
        $o_form->add_fld('REMOLQUE','text-label' ,rules: 'alphanum',label: 'REMOLQUE',errormsg: 'Fahrzeug REMOLQUE'); //#+

  
        $o_form->_msg_info    = "Crear nuevo vehículo";
        $o_form->_msg_error   = "Introduzca una fecha válida";
        $o_form->_msg_success = "Vehículo creado correctamente";
        
        if($o_form->submit()) {
            //dbx_debug("Login-Submit",$oForm->_errors,$oForm->_warnings);
        	if(!$o_form->errors()) {      // submit && no errors
               $o_form->save_post($dd, $rid);

           }

        } // submit()
        $content= $o_form->run();
        return $content;
    }

}
