<?php
namespace dbx\dbxContent_admin;

dbx()->get_system_obj('dbxReport', 'use');
require_once __DIR__ . '/dbxReport_images.class.php';
Class dbxContent_ibrowser {
   
  private function delete_img() {
      $img =dbx()->get_modul_var('img','','filename-img'); 
      if ($img) {
         $path=dbx()->get_base_dir().'dbx/modules/dbxContent/img/';
         $path_file=$path.$img;
         if (file_exists($path_file)) unlink($path_file);
      }  
  }

  public function run() {
    $caller=dbx()->get_modul_var('dbx_caller',0);

    $o_report= new dbxReport_images;
    $o_report->init('dbxContent_ibrowser','report-images');
    $o_report->_create_sel_flds=0;
    $o_report->set_action(dbx()->get_self_url());

    $data['dbx_rrows']=2;
    $data['dbx_rpos'] =0;

    $caller=dbx()->get_modul_var('dbx_caller');

    $rrows =$o_report->get_fld_val('dbx_rrows',12,'int|min=1|max=120');
    $rpos  =$o_report->get_fld_val('dbx_rpos',0,'int|min=0');

    $work=dbx()->get_modul_var('dbx_run2');
    if ($work == 'delete_img') $this->delete_img();


    $o_report->set_data($data);
    $o_report->_rdata = $o_report->get_img_data($rpos,$rrows);
    $o_report->_rrows = $rrows;
    $o_report->_rpos  = $rpos;

    $selected['dbx_target']= $caller;
    $selected['dbx_value'] = '{name}';
   

    $delete['action']=$o_report->get_action().'&dbx_run2=delete_img&img={name}';



    $o_report->add_obj('img','image-upload');
    $o_report->add_obj('sel','button-get-selected',$selected);
    $o_report->add_obj('del','image-delete',$delete);


    $content=$o_report->run();
    return $content;
  }


} 

?>
