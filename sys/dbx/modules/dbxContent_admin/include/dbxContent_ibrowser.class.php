<?php
namespace dbx\dbxContent_admin;

dbx_use_sys_class('dbxReport');

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
    $aext=array("jpg","jpeg","png","gif");
    $path=dbx_get_file_dir().'/dbxContent/img/';
    $url =dbx_get_base_url().'files/dbxContent/img/';


    $dh = opendir($path);
    while(($file = readdir($dh)) !== false) {
      $info = pathinfo($path.$file);
      $ext  = $info['extension'];
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
    $this->_rcount=$rcount;

    return $rdata;
  }


  Private function report_images() {
     $data =array(); $cols=2;
     $cols  =dbx_get_PostGetVar('dbx_cols',1);
     $rrows =dbx_get_PostGetVar('dbx_rrows',2);
     $rpos  =dbx_get_PostGetVar('dbx_rpos',0);
     $target="";
     $section='ibrowser';

     $rdata =$this->get_img_data($rpos,$rrows);
     $rcount=$this->_rcount;
     for ($i=1; $i <= $cols; $i++) {
        $fkey='obj_'.$i;
        $flds[$fkey]='Bild';
     }
     //dbx_debug("Felder",$flds);

     $caller=dbx_get_PostGetVar('dbx_caller',0);
     if (!$caller) $caller=dbx_get_SessionVal('dbx_caller',0,$section);
     if ( $caller) dbx_set_SessionVal('dbx_caller',$caller,$section);
     dbx_debug("#images caller=($caller)");


     $selected['label']     = "#get_selected# (2)";
     $selected['dbx_target']= $caller;
     $selected['dbx_jsys']  = "dbx_jmode=set_value&dbx_select_max=1&dbx_caller=$caller";

     $oReport =dbx_get_sys_object('dbxReport');
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
      $img =dbx_get_ModulVar('img','','filename-img'); 
      if ($img) {
         $path=dbx_get_base_dir().'dbx/modules/dbxContent/img/';
         $path_file=$path.$img;
         if (file_exists($path_file)) unlink($path_file);
      }  
  }

  public function run() {
    $caller=dbx_get_ModulVar('dbx_caller',0);

    $oReport= new dbxReport_images;
    $oReport->init('dbxContent_ibrowser','report-images');
    $oReport->_create_sel_flds=0;
    $oReport->_action=dbx_get_self_url();

    $data['dbx_rrows']=2;
    $data['dbx_rpos'] =0;

    $caller=dbx_get_ModulVar('dbx_caller');

    $rrows =$oReport->get_sel('dbx_rrows',12);
    $rpos  =$oReport->get_sel('dbx_rpos',0);

    $work=dbx_get_ModulVar('dbx_work');
    if ($work == 'delete_img') $this->delete_img();


    $oReport->_data  = $data;
    $oReport->_rdata = $oReport->get_img_data($rpos,$rrows);
    $oReport->_rrows = $rrows;
    $oReport->_rpos  = $rpos;

    $selected['dbx_target']= $caller;
    $selected['dbx_value'] = '{name}';
   

    $delete['action']=$oReport->_action.'&dbx_work=delete_img&img={name}';



    $oReport->add_obj('img','image-upload');
    $oReport->add_obj('sel','button-get-selected',$selected);
    $oReport->add_obj('del','image-delete',$delete);


    $content=$oReport->run();
    return $content;
  }


} 

?>