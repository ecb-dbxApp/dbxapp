<?php
namespace dbx\dbxContent_admin;

dbx()->use_system_class('dbxReport');

Class dbxReport_images extends \dbxReport {

  public function run_body($content) {

     $img_data=$this->_record;
     //$this->add_obj('ximg','dbx|image-upload',$img_data);
     //dbx_debug("#BODY#",$content,$img_data);
     $content=$this->forward_run_body($content);
     return $content;
  }


  public function get_img_data($rpos=0,$rrows=1) {
    $rdata=array(); $rcount=0;
    $aext=array("jpg","jpeg","png","gif","webp");
    $path=dbx()->get_file_dir().'/dbxContent/img/';
    $url =dbx()->get_base_url().'files/dbxContent/img/';

    if (!is_dir($path)) @mkdir($path, 0777, true);
    if (!is_dir($path) || !is_readable($path)) {
      $this->_rcount = 0;
      return $rdata;
    }

    $dh = opendir($path);
    if (!$dh) {
      $this->_rcount = 0;
      return $rdata;
    }
    while(($file = readdir($dh)) !== false) {
      $info = pathinfo($path.$file);
      $ext  = strtolower((string)($info['extension'] ?? ''));
      if (in_array($ext,$aext)) {
        $rcount++;
        if ($rcount >= $rpos && $rcount <= ($rpos+$rrows) ) {
          $record=array();
          $xfile=$file;
          $record['id']     =$xfile;
          $record['name']   =$xfile;
          $record['src']    =$url.$xfile;
          $record['alt']    ="Bild ($xfile)";
          $record['tooltip']='tt';
          $record['high']   ='600px';
          $record['width']  ='1600px';
          $record['class']  ='dbxImg';
          $record['dbx_selectval']=$xfile;
          $rdata[]=$record;
        }
      } // ext in array(aext)
    }
    closedir($dh);
    $this->_rcount=$rcount;

    return $rdata;
  }


  Private function report_images() {
     $data =array(); $cols=2;
     $cols  =dbx()->get_request_var('dbx_cols',1);
     $rrows =dbx()->get_request_var('dbx_rrows',2);
     $rpos  =dbx()->get_request_var('dbx_rpos',0);
     $target="";
     $section='ibrowser';

     $rdata =$this->get_img_data($rpos,$rrows);
     $rcount=$this->_rcount;
     for ($i=1; $i <= $cols; $i++) {
        $fkey='obj_'.$i;
        $flds[$fkey]='Bild';
     }
     //dbx_debug("Felder",$flds);

     $caller=dbx()->get_request_var('dbx_caller',0);
     if (!$caller) $caller=dbx()->get_session_var('dbx_caller',0,$section);
     if ( $caller) dbx()->set_session_var('dbx_caller',$caller,$section);
     dbx()->debug("#images caller=($caller)");


     $selected['label']     = "#get_selected# (2)";
     $selected['dbx_target']= $caller;
     $selected['dbx_jsys']  = "dbx_jmode=set_value&dbx_select_max=1&dbx_caller=$caller";

     $oReport =dbx()->get_system_obj('dbxReport');
     $oReport->init('dbxContent_ibrowser','report-images');
     $oReport->_data=$data;
     $oReport->_create_sel_flds=0;
     $oReport->_rdata = $rdata;
     $oReport->_rcount= $rcount;
     $oReport->_rrows = $rrows;
     $oReport->_rpos  = $rpos;

     $content=$oReport->run(1,$flds,'table');

     return $content;
  }

}


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

    $oReport= new dbxReport_images;
    $oReport->init('dbxContent_ibrowser','report-images');
    $oReport->_create_sel_flds=0;
    $oReport->_action=dbx()->get_self_url();

    $data['dbx_rrows']=2;
    $data['dbx_rpos'] =0;

    $caller=dbx()->get_modul_var('dbx_caller');

    $rrows =$oReport->get_sel('dbx_rrows',12);
    $rpos  =$oReport->get_sel('dbx_rpos',0);

    $work=dbx()->get_modul_var('dbx_run2');
    if ($work == 'delete_img') $this->delete_img();


    $oReport->_data  = $data;
    $oReport->_rdata = $oReport->get_img_data($rpos,$rrows);
    $oReport->_rrows = $rrows;
    $oReport->_rpos  = $rpos;

    $selected['dbx_target']= $caller;
    $selected['dbx_value'] = '{name}';
   

    $delete['action']=$oReport->_action.'&dbx_run2=delete_img&img={name}';



    $oReport->add_obj('img','image-upload');
    $oReport->add_obj('sel','button-get-selected',$selected);
    $oReport->add_obj('del','image-delete',$delete);


    $content=$oReport->run();
    return $content;
  }


} 

?>
