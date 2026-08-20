<?php
namespace dbx\myLKW;

class myLKW_dayset {


    public function run() {
       dbx()->debug("myLKWD_dayset");

        
        $today = (new \DateTime())->format('Y-m-d');
        $date = dbx()->get_cfg('myLKW','shiftdate'); // ALTEN shiftdate holen
        $data['setdate']=$date; 

        $o_form=dbx()->get_system_obj('dbxForm');
        $o_form->init('form-dayset');
        $o_form->set_action('?dbx_modul=myLKW&dbx_run1=dayset');
        $o_form->set_data($data);
        $o_form->add_module_bar('Establecer la fecha', 'bi-calendar-date');

        $o_form->add_fld('setdate','date-label'     ,rules: 'date',label: 'Datum',errormsg: 'Ingrese una fecha válida'); //#+
  
        $o_form->_msg_info    = "Establecer la fecha actual para el bloque de hoy";
        $o_form->_msg_error   = "Introduzca una fecha válida";
        $o_form->_msg_success = "Fecha establecida correctamente";
 
        if($o_form->submit()) {
            //dbx_debug("Login-Submit",$oForm->_errors,$oForm->_warnings);
        	if(!$o_form->errors()) {      // submit && no errors
                $config = dbx()->get_cfg('myLKW');
                $date_string = $o_form->get_post_data('setdate',$today,'date');
                $date_obj = new \DateTime($date_string);
                $config['shiftdate'] = $date_obj->format('Y-m-d');
                dbx()->set_cfg('myLKW',$config);
           }

        } // submit()
        $content= $o_form->run();
        return $content;
    }

}
