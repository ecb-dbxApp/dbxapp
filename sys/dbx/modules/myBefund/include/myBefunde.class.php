<?php
namespace dbx\myBefund;
dbx_use_sys_class('dbxReport');


class dbxReport_Befunde extends \dbxReport {

  private function get_sex($sex) {
     $retval=$sex;
     if ($sex == 1)  $retval='Mann';
     if ($sex == 2)  $retval='Frau';
     if ($sex == 3)  $retval='Kind';
     if ($sex =='W') $retval='Frau';
     if ($sex =='F') $retval='Frau';
     if ($sex =='K') $retval='Kind';
     if ($sex =='M') $retval='Mann';
     return $retval;
  }

  private function get_typ($typ) {
     $retval='Befund';
     if ($typ == 8201) $retval='Labor-Bericht';
     if ($typ == 8202) $retval='LG-Bericht';
     if ($typ == 8203) $retval='Mikrobiologie';
     return $retval;
  }

  private function get_art($art) {
     $retval='Befund';
     if ($art == 'E') $retval='Endbefund';
     if ($art == 'T') $retval='Teilbefund';
     if ($art == 'N') $retval='Nachforderung';
     return $retval;
  }


    private function get_fax($fax) {
       $retval='?';
       if ($fax == 0) $retval='<i class="bi bi-square"></i>';
       if ($fax == 1) $retval='<i class="bi bi-check-square"></i>';
       return $retval;
    }

    private function get_ldt($ldt) {
      $retval='?';
      if ($ldt == 0) $retval='<i class="bi bi-square"></i>';
      if ($ldt == 1) $retval='<i class="bi bi-check-square"></i>';
      return $retval;
    }

    private function get_prn_cb($prn) {
      $retval='?';
      if ($prn <= 0) $retval='<center><i class="bi bi-square"></i></center>';
      if ($prn >= 1) $retval='<center><i class="bi bi-check-square"></i></center>';
      return $retval;
    }

    private function get_ldt_cb($ldt) {
      $retval='?';
      if ($ldt <= 0) $retval='<center><i class="bi bi-square"></i></center>';
      if ($ldt >= 1) $retval='<center><i class="bi bi-check-square"></i></center>';
      return $retval;
    }


  public function run_body($content) {
    $activ_id = $this->_activ_id;
    $record   = $this->_record;
    $class    = '';
    $record['sex']       = $this->get_sex($record['sex']);
    $record['befundtyp'] = $this->get_typ($record['befundtyp']);
    $record['befundart'] = $this->get_art($record['befundart']);
    //$record['fax']       = $this->get_fax($record['fax']);
    //if (isset($record['ldt'])) $record['ldt'] = $this->get_ldt($record['ldt']);
    if (isset($record['prn'])) $record['prn'] = $this->get_prn_cb($record['prn']);
    if (isset($record['ldt'])) $record['ldt'] = $this->get_ldt_cb($record['ldt']);

    if (strpos($content,'{obj:analysen}')) {
       $this->add_obj('analysen','obv-value','Analysen vom Befund werden geladen ...');
    }
    if (isset($record['id']) && $activ_id) {
      if ($activ_id == $record['id']) $class.=" activ_td";
    }
    $record['dbx_td_class']= $class; 
    $this->_record=$record;
    $content=$this->forward_run_body($content);
    return $content;
  }
}

// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -






// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -


Class myBefunde {

 

  
   private function report_befunde() {
      $content = '';   $sysmsg  = ''; $modal_content='';

      $do  =dbx_get_ModulVar('dbx_do','','parameter');
      $rid =dbx_get_ModulVar('rid',0,'int');
      $praxis=dbx_get_cfg('myOrderLDT','praxis');

      if ($do == 'row_show' && $rid) {
         return '[modul=myBefund]dbx_action=analys&dbx_work=list_analys&rid='. $rid .'[/modul]';
      }

      if ($do == 'row_print' && $rid) {
         return '[modul=myBefund]dbx_action=befund&dbx_work=print_analys&rid='. $rid .'[/modul]';
      }

  



      $form_id ='report-befunde-modal';
      $oReport = new dbxReport_Befunde;
      $oReport->init($form_id);   //if ($oReport->set_form_selects()) return $oReport->get_count_selects(); // fast retval;
      $uid     = dbx_get_CurrentUser();
      $oDB     = dbx_get_sys_object('dbxDB');
      $lng     = dbx_get_ModulVar('lng','de');
      $dd      = 'dbx_my_befund';
      $praxis_label=''; $ids=array();


      if ($do == 'multi_ldt_selected') {
         $ids=$oReport->get_multi_selects();
         dbx_set_Remember('multi_ldt_ids',$ids);
         return '[modul=myBefund]dbx_action=ldt_analys[/modul]';
      }
      if ($do == 'multi_ldt_unsend') {
         $where= 'ldt <> 1';
         $recs =$oDB->select($dd,$where);
         if (is_array($recs)) {
            foreach ($recs as $no => $rec) {
               $bid=$rec['id'];
               $ids[$bid]=1;
           }
         }
         dbx_set_Remember('multi_ldt_ids',$ids);
         return '[modul=myBefund]dbx_action=ldt_analys[/modul]';
      }





      $flds['id']         = '';
      $flds['arzt']       = $praxis_label;
      $flds['pat']        = 'AnfNr';
      $flds['datum']      = 'Datum';
      $flds['patvorname'] = 'Vorname';
      $flds['patname']    = 'Name';
      $flds['gebdat']     = 'GebDat';
      $flds['sex']        = 'Geschl';
      $flds['befundtyp']  = 'BefundTyp';
      $flds['befundart']  = 'BefundArt';
      $flds['prn']        = 'Druck';
      $flds['ldt']        = 'LDT';
      //$flds['ldt']        ='LDT';




      $class_haeder['ldt']='no-sort'; 
      


      $options_rsort['pat']         = 'AnfNr';
      $options_rsort['datum']       = 'Datum';
      $options_rsort['patvorname']  = 'Vorname';

      $rpt_format['datum'] ='php-date-usr';
      $rpt_format['gebdat']='php-date-usr';

      $today=date('Y-m-d');
      $last_date =date("Y-m-d");
      $first_date=date("Y-m-d");
      
      $where = "id > 0";  //  = $praxis";
   
      // select1($dd,$where='',$columns='*',$orderby='',$asc_desc='ASC',$groupby='',$max=1,$offset=0,$verify_access=1) {

      $last_rec=$oDB->select1($dd,$where,'id,datum','datum','DESC');
      //dbx_debug("Last Befund Rec ($dd)($where) =",$last_rec);



      if (is_array($last_rec)) {
        $last_date =$last_rec['datum'];
        $first_date=$last_date; // Aktueller Tag
      } 
      if (!is_array($last_rec)) {
         $last_date =$today;  
         $first_date=$last_date; // Aktueller Tag 
      }     
      $date_from = $oReport->get_sel('sel_date_from',$first_date,'date');
      $sdate = dbx_get_ModulVar('sdate',dbx_get_Remember('set_date'),'parameter');
    
 
      if ($sdate ==  'last') {
         $date_from = $first_date;
         dbx_set_Remember('set_date','clear');
      } 
      $oReport->set_sel('sel_date_from',$date_from);        
      //dbx_debug("sdate=($sdate) date=($date_from) ",$last_rec);


      

      //dbx_debug("#Befunde Von-Datum=($first_date)  Sel-Datum=($date_from)");

      $data['sel_date_from']= $first_date ; // ;$oReport->php_date_usr($last_date);
      $data['dbx_rrows']    =   100;
      $data['dbx_rsort']    = 'datum';
      $oReport->_data       = $data;
      $oReport->_activ_id   = dbx_get_Remember('my_befund-activ_id',0,'int');

      //dbx_debug("befund activ id=($oReport->_activ_id)");

      $oReport->_action='?dbx_modul=myBefund&dbx_action=befund&dbx_work=list_befund';
      $oReport->_options_rsort    = $options_rsort;
      $oReport->_but_pagination   =5;
      $oReport->_create_row_select=1;
      $oReport->_create_row_edit  =0;
      $oReport->_create_row_delete=0;
      $oReport->_create_row_show  =1;
      $oReport->_create_row_print =0;  // #todo aktuell noch ausgeschaltet
      $oReport->_create_sel_flds  =1;     
      $oReport->_data_table       =0; //'auto';
      $oReport->_table_buttons    ='left';
      
      $oReport->_class_haeder                  = $class_haeder; 
      $oReport->_tabel_tpls['tpl_row_show']    = 'table_row_modal-show';
      $oReport->_tabel_tpls['tpl_row_select']  = 'table_row_select-multi-noval';

      $oReport->_msg_info ='Liste der Befunde';
      $oReport->_msg_success=''; 
      $oReport->_rpt_format=$rpt_format;

 
 
 


      if($oReport->submit()) {
        if(!$oReport->errors()) {      // submit && no errors
           $oReport->_msg_success   = ''; // 'Daten ausgewählt und sortiert';
        } else {
           $oReport->_msg_error     = 'Prüfen sie bitte ihre Eingaben';
        }
      }  else { // no submit
        if ($do == 'row_delete' && $rid) {
           $ok=$oDB->delete($dd,$rid);
           if ( $ok) $oReport->_msg_info = 'Zeile gelöscht';
           if (!$ok) $oReport->_msg_info = 'Zeile konnte nicht gelöscht werden';
        }
      }
      if ($sysmsg) $oReport->set_msg_ok($sysmsg);
      if ($sysmsg) $oReport->set_msg_info($sysmsg);

      // get all selections and order
      $rgroup=''; $rwhere='';
      $select   =$oReport->get_sel('dbx_rselect'  ,0          ,'int');
      $rfind    =$oReport->get_sel('dbx_rwhere'   ,''         ,'parameter');
      $rrows    =$oReport->get_sel('dbx_rrows'    ,100        ,'int');
      $rpos     =$oReport->get_sel('dbx_rpos'     ,0          ,'int');
      $rsort    ='datum';
      $rdesc    ='DESC';


      $rwhere="arzt = '$praxis' and datum <= '$date_from' ";
   


   

    
   
     
      if ($rfind > ' ') {
         $rwhere=$oReport->add_rwhere_search($rwhere,$rfind,'pat,datum,patname,patvorname,gebdat,versicherungsnr');
      }
      if ($select) $rwhere=$oReport->add_rwhere_select($rwhere);
      


      $count=$oDB->count($dd,$rwhere);
      $rdata=$oDB->select($dd,$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);


      //dbx_debug("Befunde Count=($count) dd=($dd) where=($rwhere)"); 
      //return "count=($count) ($dd) ($rwhere)";

      $oReport->_rcount=$count;
      $oReport->_rdata =$rdata; 

      if ($oReport->_create_sel_flds) {
        $oReport->add_fld('sel_date_from','date-label-group-prompt','','date','Ab Datum','Von Datum');
        //$oReport->add_fld('sel_date_to'  ,'date-label-group-prompt','','date','Bis','Bis Datum');
      }
      
      $prax_ldt_selected['label']   ='<b>Ausgewählte Befunde</b> zum Einlesen für die <b>Arzt-Sortware</b> bereit stellen';
      $prax_ldt_selected['dbx_get'] ='?dbx_modul=myBefund&dbx_action=befund&dbx_work=list_befund&dbx_do=multi_ldt_selected';
      $oReport->add_obj('rows_send_selected','button-modal1',$prax_ldt_selected);

      $prax_ldt_unsend['label']   ='<b>Alle neue Befunde</b> zum Einlesen für die <b>Arzt-Sortware</b> bereit stellen';
      $prax_ldt_unsend['dbx_get'] ='?dbx_modul=myBefund&dbx_action=befund&dbx_work=list_befund&dbx_do=multi_ldt_unsend';
      $oReport->add_obj('rows_send_unsend','button-modal1',$prax_ldt_unsend);
  
      $getbefund['dbx_get']= '?dbx_modul=myBefund&dbx_action=import&dbx_work=import_befund';
      $getbefund['label']  = 'Neue Befunde vom Labor einlesen';
      $oReport->add_obj('getbefund','button-modal1',$getbefund);



  
      //$oReport->add_obj('rows_send','obj-value','<hr>');
      //$oReport->set_no_submit($form_id,0); // clear no_submit from modal recall !
      
      $oReport->add_js_call('dbx_table','datatable1');
      $oReport->add_js("datatable_fix('#dbx_table_{i}')",100); // work arround hack
      // run Report

      $modal1['title']     ='Labor Analysen';     
      $modal1['on_close']  ="dbxReSendForm('dbx_form_{i}')"; // JS Event close modal
      $modal1['class']     ='modal-xxl';
      $modal_content=$oReport->oTPL->get_tpl('dbx','modal1',$modal1);
      $oReport->add_obj('modal1','obj-value',$modal_content);


      $content=$oReport->run(1,$flds,'table');

      return $content;
   } // report_befunde()





   public function run($work='list') {
      
      $work=dbx_get_ModulVar('dbx_work');
      $content='Modul myBefunde action('.dbx_html($work).') not defined';


      switch ($work) {
        case 'list':
        case 'list_befund':
            $content=$this->report_befunde();
            break;
           
         case 'print_analys':
            $oAnalys=dbx_get_Modul_include_object('myAnalysen');
            $content=$oAnalys->run();
            break;

         case 'ldt_analys':
            $ids=dbx_get_Remember('multi_ldt_ids');
            $content="Analys LDT";
         break;   

         case 'ldt_save':
            $id=dbx_get_ModulVar('id', 0,'integer');
            if ($id) {
               $field_values=array();
               $oDD=dbx_get_sys_object('dbxDB');
               $field_values['id']= $id;
               $field_values['ldt']=1;
               $where = "id = $id";
               $ok=$oDD->update('dbx_my_befund',$field_values,$where,0,0,0,0);
               //dbx_debug("## save prn=($ok)");
            }
         break;
   

         case 'prn_save':
            $id=dbx_get_ModulVar('id', 0,'integer');
            if ($id) {
               $field_values=array();
               $oDD=dbx_get_sys_object('dbxDB');
               $field_values['id']= $id;
               $field_values['prn']=1;
               $where = "id = $id";
               $ok=$oDD->update('dbx_my_befund',$field_values,$where,0,0,0,0);
               //dbx_debug("## save prn=($ok)");
            }
         break;
   


      }
      return $content;
   } // run

} // class


