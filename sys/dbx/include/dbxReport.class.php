<?php

require_once 'dbxForm.class.php';

class dbxReport extends dbxForm {

  Public $_ajax=0;


  Public $_haeder = '';
  Public $_body   = '';
  Public $_footer = '';
  //Public $_action = ''; 

  Public $_record  = array();
  Public $_rdata   = array();
  Public $_rcount  = 0;
  Public $_rpos    = 0;
  Public $_rrows   =10;

  Public $_rwhere_placeholder='#search_for#';
  Public $_fld_id     ='id'; // for multi_select

  Public $_pages      = 1;   // Create Pagination
  Public $_pagelink   ='';   // Pagination link empty = _action

  Public $_auto_flds ='';
  Public $_auto_mode ='';

  Public $_tpl_pagination   ='dbx|pagination';
  Public $_but_pagination   =3;

  Public $_add_action ='';

  Public $_create_sel_flds    =1;
  Public $_create_row_select  =0;
  Public $_create_row_edit    =0;
  Public $_create_row_copy    =0;
  Public $_create_row_delete  =0;
  Public $_create_row_download=0;
  Public $_create_row_show    =0;
  Public $_create_row_undo    =0;
  Public $_create_row_print   =0;

  Public $_rpt_format     =array();
  Public $_options_rsort  =array();
  Public $_options_rrows  =array();
  Public $_options_rdesc  =array();
  Public $_options_rselect=array();

  Public $_tabel_tpls=array();
  Public $_table_col_count=0;

  Public $_multi_page_select =1;
  Public $_multi_select_work ='';
  Public $_table_buttons     ='left'; // or 'right'
  
  Public $_data_table=0;

  Public $_scroll_table=0;
  Public $_class_haeder=array();
  Public $_class_body  =array();

  Public $_activ_id=0; 

  Public $_msg_confirm_delete='Datensatz löschen ?';
  Public $_msg_confirm_copy  ='Datensatz kopieren ?';

  public function clear() {
     $this->_forward_clear();
     $this->_rpt_format   = array();
     $this->_options_rsort= array();
     $this->_options_rrows= array();
     $this->_options_rdesc= array();
     $table['tpl_haeder_col']       ='table_haeder_col';
     $table['tpl_haeder_select']    ='table_haeder_select';
     $table['tpl_haeder_delte']     ='table_haeder_delete';
     $table['tpl_haeder_expand']    ='table_haeder_expand';
     $table['tpl_haeder_expander']  ='table_haeder_expander';
     $table['tpl_haeder_edit']      ='table_haeder_edit';
     $table['tpl_haeder_copy']      ='table_haeder_copy';
     $table['tpl_haeder_undo']      ='table_haeder_undo';
     $table['tpl_haeder_show']      ='table_haeder_show';
     $table['tpl_haeder_download']  ='table_haeder_download';
     $table['tpl_haeder_print']     ='table_haeder_print';
     $table['tpl_row_col']          ='table_row_col';

     $table['tpl_row_select']       ='table_row_select';
     $table['tpl_row_expand']       ='table_row_expand';
     $table['tpl_row_expander']     ='table_row_expander';
     $table['tpl_row_edit']         ='table_row_edit';
     $table['tpl_row_copy']         ='table_row_copy';
     $table['tpl_row_delete']       ='table_row_delete';
     $table['tpl_row_save']         ='table_row_save';
     $table['tpl_row_undo']         ='table_row_undo';
     $table['tpl_row_show']         ='table_row_show';
     $table['tpl_row_download']     ='table_row_download';
     $table['tpl_row_print']        ='table_row_print';

     if ($this->_multi_page_select) {
        $table['tpl_haeder_select']='table_haeder_select-multi';
        $table['tpl_row_select']   ='table_row_select-multi';
     }

     $this->_tabel_tpls=$table;
     $this->_table_col_count=0;
  }

  public function set_tabel_tpl($tid,$tpl) {
    $this->_tabel_tpls[$tid]=$tpl;
  }

 
  public function get_sys($name,$default='',$validate='parameter') {
    if (isset($this->_data[$name])) $default=$this->_data[$name];
    if (isset($this->_sys[$name]))  $default=$this->_sys[$name];
    $value=dbx_get_PostGetVar($name,$default,$validate);
    return $value;
  }



  public function check_is_multiselect($rid) {
     $checked='';
     $selects=$this->get_multi_selects();
     if (isset($selects[$rid])) $checked='checked="checked"';
     return $checked;
  }

  public function set_multi_select($rid) {

    $selects=$this->get_multi_selects();
    //dbx_debug("add-select=($rid) to=",$selects);
    if ($rid == '*') {
      foreach ($this->_rdata as $no => $record) {
         if (isset($record['id'])) {
           $id=$record['id'];
           $selects[$id]=1;
         }
      }
    } else {
      if ($rid) $selects[$rid]=1;
    }

    //dbx_debug("#A-set-mult-select ($rid)",$selects);

    $this->set_multi_selects($selects);
  }

  public function del_multi_select($rid) {
     //dbx_debug("del-select=($rid)");
     $empty=array();
     $selects=$this->get_multi_selects();
     if ($rid != '*') {
       if (isset($selects[$rid])) unset($selects[$rid]);
     } else {
       $selects=$empty;
     }
     //dbx_debug("#del-mult-select ($rid)",$selects);

     $this->set_multi_selects($selects);
  }

  public function set_multi_selects($selects) {
    $empty=array();
    $form_id=$this->_fid;
    $modul  =$this->_dbx_modul;
    $section=$this->_fid;
    $name   ='multi_select';
    dbx_set_SessionVal($name,$selects,$section,$modul);
    //dbx_debug("#set-session-multi-selects Modul=($modul) Name=($name) Section=($section)",$selects);
  }


  public function del_multi_selects($id) {
    $empty  =array();
    $selects_submit='';
    $form_id=$this->_fid;
    $modul  =$this->_dbx_modul;
    $section=$this->_fid;
    $name   ='multi_select';
 
    if ($id == '*') {
      dbx_set_SessionVal($name,$empty,$section,$modul);
    } else {
      if ($id) {
         $ids=$this->get_multi_selects();
         if (isset($ids[$id])) unset($ids[$id]);
         dbx_set_SessionVal($name,$ids,$section,$modul);
      }
    } 
  }  
  


  public function get_multi_selects() {
    $empty  =array();
    $selects=array();
    $selects_submit='';
    $form_id=$this->_fid;
    $modul  =$this->_dbx_modul;
    $section=$this->_fid;
    $name   ='multi_select';
  
    $selects_submit  =$this->get_post($form_id.'_select','','*');
    $selects_session =dbx_get_SessionVal($name,'',$section,$modul);
    if (is_array($selects_submit)) {     
       foreach ($selects_submit as $no => $key) {
           $selects[$key]=1;      
       }
       $selects_submit=$selects;
    }
    
    $selects = $selects_submit;
    if ( is_array($selects_session)) $selects=$selects_session;
    if (!is_array($selects))         $selects=$empty;

    //dbx_debug("#get-multi-selects",$selects);

    return $selects;
  }

  public function get_count_selects() {
    $count  =0; $empty=array();
    $modul  =$this->_dbx_modul;
    $section=$this->_fid;
    $name='multi_select';
    $selects=dbx_get_SessionVal($name,$empty,$section,$modul);
    if (is_array($selects)) $count=count($selects);
    //dbx_debug("Count selects=($count)",$selects);
    return $count;
  }

  public function del_selected($dd,$rid=0) {
    $ok=0; $err=0;
    $db=dbx_get_sys_object('dbxDB');
    if ($rid == '*') {
      $selected=$this->get_multi_selects();
      foreach ($selected as $rid => $sel) {
         $ok=$db->delete($dd,$rid);
         if (!$ok) $err++;
         $this->del_multi_select($rid);
      }
      if ($err) $ok=($err * -1);
    } else {
      if ($rid) {
         $ok=$db->delete($dd,$rid);
         $this->del_multi_select($rid);
      }
    }
    return $ok;
  }

  public function add_rwhere_select($rwhere) {
     $count=0;
     $selects=$this->get_multi_selects();
     //dbx_debug("selects=",$selects);
     if (is_array($selects) && count($selects)  ) {
       if ($rwhere  > '') $rwhere.= ' and (';
       if ($rwhere == '') $rwhere.= ' (';
       foreach ($selects as $id => $sel) {
         if (!$count) $rwhere.= '    id = '.$id.' ';
         if ( $count) $rwhere.= ' or id = '.$id.' ';
         $count++;
       }
       $rwhere.=')';
     } else {  // nothing selected
       if ($rwhere  > '') $rwhere.= ' and (id=-1)'; // Nothing 
       if ($rwhere == '') $rwhere.= 'id=-888'; // Nothing
     }
     //dbx_debug("SQL-rwhere=($rwhere)");
     return $rwhere;
  }


  function add_rwhere_search($sql,$suchWert, $feldListe) {
    if ($suchWert === null)  $suchWert = '';
    //dbx_debug("SUCHWERT-A=($suchWert)");
  
    // Entfernt potenziell gefährliche Zeichen: ', ", \, und andere

    $suchWert = str_replace(['\'', '"', '\\', '%'], '', $suchWert);
    // Optional: Entfernt HTML-Sonderzeichen
    $suchWert = filter_var($suchWert, FILTER_SANITIZE_SPECIAL_CHARS);
    
    //dbx_debug("SUCHWERT-B=($suchWert)");

     // Überprüfen, ob der Suchwert ein deutsches Datum ist (Format: dd.mm.yyyy)
     $datum = DateTime::createFromFormat('d.m.Y', $suchWert);
     if ($datum && $datum->format('d.m.Y') === $suchWert) {
         // Wenn es ein gültiges Datum ist, ins SQL-Format (Y-m-d) umwandeln
         $suchWert = $datum->format('Y-m-d');
     }
    // Feldliste in ein Array umwandeln
    $felder = explode(',', $feldListe);

    // Bedingungen zu SQL hinzufügen
    $sql .= " AND (";
    $bedingungen = [];
    
    foreach ($felder as $feld) {
      $bedingungen[] = "$feld LIKE '$suchWert%'";
    }
    
    $sql .= implode(' OR ', $bedingungen);
    $sql .= ")";

    return $sql;
}




  public function set_form_selects() {
    $retval=0; $empty=array();
    $modul  =$this->_dbx_modul;
    $section=$this->_fid;
    $name='multi_select';

    $mode    =dbx_get_PostGetVar('dbx_mode'   ,'','parameter');
    $checked =dbx_get_PostGetVar('dbx_checked','','parameter');
    $value   =dbx_get_PostGetVar('dbx_value'  ,0, 'parameter+.'); // record-ids or other value(s)
    $ajax    =dbx_get_PostGetVar('dbx_ajax'   ,0, 'int');
    $nor     =dbx_get_PostGetVar('dbx_nor'    ,0, 'int');

    //dbx_debug("#SET-FORM-SELECTS# Mode($mode) Value=($value) Check=($checked) Ajax=($ajax) Name=($name) Nor=($nor)");
    if (!$nor) {
      if ($mode == 'reset_form_select') {
         dbx_del_SessionVal($name,$section,$modul);
         //dbx_debug("RESET SELECTS# ($mode) ajax($ajax)");
      }
      if ($mode == 'save_form_select' && $value && $ajax) {
         $selects=dbx_get_SessionVal($name,$empty,$section,$modul);
         if (!$checked || $checked == 'false') {
           if (isset($selects[$value])) unset($selects[$value]);
         } else {
           $selects[$value]=1;
         }
         dbx_set_SessionVal($name,$selects,$section,$modul);
         $retval=1;
      }
    } else {
      if ($checked ) $retval= 'add';
      if (!$checked || $checked == 'false') $retval= 'rem';
    }

    $selects=dbx_get_SessionVal($name,$empty,$section,$modul);
    //dbx_debug("Selects=",$selects);

    return $retval;
  }





  public function rpt_format($key,$value) {
     
     $format=$this->_rpt_format;
     if (is_array($format)) {
       if (isset($format[$key])) {
           $reform=$format[$key];
           if ($reform == 'php-date-usr') {
             $value=$this->php_date_usr($value);
           }
           if ($reform == 'php-datetime-usr') {
             $value=$this->php_datetime_usr($value);
           }           
           if ($reform == 'html-chars') {
            $value=htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
           }           
       }
     } else {
       if ($format == 'html-chars') $value=htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
     }
     if ($value === null) $value='';
     return $value;
  }

  // override and notification
  public function rpt_merge_obj($content) {
     $objs=$this->_obj;
     if (is_array($objs)) {
        foreach ($objs as $key => $value) {
           $xkey='{obj:'.$key.'}';
           if ($value==null) $value='';
           $content=str_replace($xkey,$value,$content);
        }
     }
     return $content;
  }

  public function run_haeder($content) {
    $content=$this->forward_run_haeder($content);
    return $content;
  }

  public function run_body($content) {
    $content=$this->forward_run_body($content);
    return $content;
  }

  public function run_footer($content) {
    $content=$this->forward_run_footer($content);
    return $content;
  }


  public function forward_run_haeder($content) {

    $content=$this->rpt_merge_obj($content);
    $count_select=$this->get_count_selects();
    $content=str_replace('{count_sel}',$count_select,$content);
    return $content;
  }

  public function forward_run_body($content) {
    $content=$this->rpt_merge_obj($content);
    return $content;
  }

  public function forward_run_footer($content) {
    $content=$this->rpt_merge_obj($content);
    return $content;
  }

  // -
  private function get_class_haeder($key) {
     $class='';
     if (is_array($this->_class_haeder)) {
        if (isset($this->_class_haeder[$key])) $class=$this->_class_haeder[$key];
     }
     //dbx_debug("Key=($key) Class=($class)");
     return $class;
  }

  public function get_report_haeder() {
    $content=$this->_haeder;
    //$this->run_haeder_php();

    $auto_flds=$this->_auto_flds;
    $auto_mode=$this->_auto_mode;

    if (!is_array($auto_flds))  $auto_flds=explode(',',$auto_flds);

    $pos = strpos($content,'[dbx:row]');
    if ($pos) {
      $row=''; $col_count=0;
      $fld_id=$this->_fld_id;
      
      if ($auto_mode=='table' && is_array($auto_flds)) {
      
 
         //$file=$this->_tabel_tpls['tpl_haeder_col'];
         
         if ($this->_table_buttons != 'left') {
           
          if ($this->_create_row_select) {
             $checked=''; 
             $nor=dbx_get_PostGetVar('dbx_nor',0, 'int');
             if ($this->_multi_select_work == 'add' && $nor) $checked='checked';
             $file=$this->_tabel_tpls['tpl_haeder_select'];
             if (isset($this->_auto_flds[$fld_id])) {
               $name=$this->_auto_flds[$fld_id];
             } else {
               $name='xID';
             }
             $class=$this->get_class_haeder($name); 


             $th =$this->get_tpl($file);
             $tpl=$th;
             $tpl=(str_replace('{name}'   ,$name,$tpl));
             $tpl=(str_replace('{checked}',$checked,$tpl));
             $tpl=(str_replace('{th-class}'  ,$class,$tpl));
             $row.=$tpl."\n";
             $col_count++;
           }       
         
           foreach ($auto_flds as $key => $value) {
              $skip=0;
              $file=$this->_tabel_tpls['tpl_haeder_col'];
              if ($this->_create_row_select && $key == $fld_id) $skip=1;
              
              if (!$skip && $value > '') {
                $th =$this->get_tpl($file);
                $class=$this->get_class_haeder($key);
                $tpl=$th;
                $tpl=(str_replace('{value}',$value,$tpl));
                $tpl=(str_replace('{name}' ,$key  ,$tpl));
                $tpl=(str_replace('{th-class}',$class,$tpl));
                $row.=$tpl."\n";
                $col_count++;
             }
           }
         }

         if ($this->_data_table) {
          $file=$this->_tabel_tpls['tpl_haeder_expander'];
          $tpl =$this->get_tpl($file); // th
          $tpl=(str_replace('{name}','ID',$tpl)); // $value=get_row_id()
          $tpl=(str_replace('{th-class}','no-sort',$tpl));
          $row.=$tpl."\n";
          $col_count++;
        }


         if ($this->_create_row_edit) {
           $file=$this->_tabel_tpls['tpl_haeder_edit'];
           $tpl =$this->get_tpl($file); // th
           $tpl=(str_replace('{name}','ID',$tpl)); // $value=get_row_id()
           $tpl=(str_replace('{th-class}','no-sort',$tpl));
           $row.=$tpl."\n";
           $col_count++;
         }

         if ($this->_create_row_copy) {
          $file=$this->_tabel_tpls['tpl_haeder_copy'];
          $tpl =$this->get_tpl($file); // th
          $tpl=(str_replace('{name}','ID',$tpl)); // $value=get_row_id()
          $tpl=(str_replace('{th-class}','no-sort',$tpl));
          $row.=$tpl."\n";
          $col_count++;
        }

 
         if ($this->_create_row_show) {
           $file=$this->_tabel_tpls['tpl_haeder_show'];
           $tpl =$this->get_tpl($file); // th
           $tpl=(str_replace('{name}','ID',$tpl)); // $value=get_row_id()
           $tpl=(str_replace('{th-class}','no-sort',$tpl));
           $row.=$tpl."\n";
           $col_count++;
         }
         if ($this->_create_row_download) {
           $file=$this->_tabel_tpls['tpl_haeder_download'];
           $tpl =$this->get_tpl($file); // th
           $tpl=(str_replace('{name}','ID',$tpl)); // $value=get_row_id()
           $tpl=(str_replace('{th-class}','no-sort',$tpl));           
           $row.=$tpl."\n";
           $col_count++;
         }

         if ($this->_create_row_delete) {
          $class='no-sort';
          $file=$this->_tabel_tpls['tpl_haeder_delte'];
          $tpl =$this->get_tpl($file); // th
          $tpl=(str_replace('{name}','ID',$tpl)); // $value=get_row_id()
          $tpl=(str_replace('{th-class}' ,$class ,$tpl));
          $row.=$tpl."\n";
          $col_count++;
        }         

        if ($this->_create_row_print) {
          $class='no-sort';
          $file=$this->_tabel_tpls['tpl_haeder_print'];
          $tpl =$this->get_tpl($file); // th
          $tpl=(str_replace('{name}','ID',$tpl)); // $value=get_row_id()
          $tpl=(str_replace('{th-class}' ,$class ,$tpl));
          $row.=$tpl."\n";
          $col_count++;
        }         


         if ($this->_table_buttons == 'left') {
         
          if ($this->_create_row_select) {
             $checked=''; $class='no-sort';
             $nor=dbx_get_PostGetVar('dbx_nor',0, 'int');
             if ($this->_multi_select_work == 'add' && $nor) $checked='checked';
             $file=$this->_tabel_tpls['tpl_haeder_select'];
             if (isset($this->_auto_flds[$fld_id])) {
               $name=$this->_auto_flds[$fld_id];
             } else {
               $name='xID';
             }
             $th =$this->get_tpl($file);
             $tpl=$th;
             $tpl=(str_replace('{name}',$name,$tpl));
             $tpl=(str_replace('{checked}',$checked,$tpl));
             $tpl=(str_replace('{th-class}',$class,$tpl));
             
             $row.=$tpl."\n";
             $col_count++;
           }
           
         
         
           foreach ($auto_flds as $key => $value) {
              $skip=0; $class='';
              //dbx_debug("### Key=($key)",$value);

              $file=$this->_tabel_tpls['tpl_haeder_col'];
              if ($this->_create_row_select && $key == $fld_id) $skip=1;
              if (is_array($value)) $skip=1; 
              if (!$skip && $value > '') {
                $th =$this->get_tpl($file);
                $class=$this->get_class_haeder($key);
                $tpl=$th;
                $tpl=(str_replace('{value}',$value,$tpl));
                $tpl=(str_replace('{name}' ,$key  ,$tpl));
                $tpl=(str_replace('{th-class}',$class,$tpl));
                $row.=$tpl."\n";
                $col_count++;
             }
           }
           
         }


      }
      $content=(str_replace('[dbx:row]',$row,$content));

      $this->_table_col_count=$col_count;
    }
    $content=$this->run_haeder($content);
    return $content;
  }



  public function get_report_body() {
    $content=''; $line='';  $loop=0;  $even_odd='';
    $auto_flds =$this->_auto_flds;
    $auto_mode =$this->_auto_mode;
    $dbx_modul =$this->_dbx_modul;
    $dbx_action=$this->_dbx_action;
    $dbx_work  =$this->_dbx_work;
    $activ_id  =$this->_activ_id;

    $fid       =$this->_fid;
    $design= 'default';  $lng   = 'default'; // ??

    if (!$dbx_action) $dbx_action=dbx_get_ModulVar('dbx_action',0);
    if (!$dbx_work)   $dbx_work  =dbx_get_ModulVar('dbx_work',0);

    //dbx_debug("#REPORT Work =($dbx_work) activ-id=($activ_id)");

    $ajax=$this->_ajax;
    if ($ajax) { 
       $ajax='dbxAjax';
    } else {
       $ajax='';
    }


    if (!is_array($auto_flds)) $auto_flds=explode(',',$auto_flds);
    if (is_array($this->_rdata)) {

      //dbx_debug("rdata-body",$this->_rdata);


      foreach ($this->_rdata as $recnum => $record) {
         $loop=($loop + 1);
         $line=$this->_body;

         $this->_record=$record;
         $line=$this->run_body($line);
         $record=$this->_record;
         $fld_id=$this->_fld_id;
         $add   =$this->_add_action;
         //dbx_debug("BODY Loop=($loop) Form=($fid)");
         
         $pos = strpos($line,'[dbx:row]');
         if ($pos) {
           $row=''; $aid=0;
           $class="no-sortx"; $td_class='nix';
           if (isset($record['dbx_td_class'])) $td_class=$record['dbx_td_class']; 


           if ($auto_mode=='table' && is_array($auto_flds)) { 
           
             if ($this->_table_buttons != 'left') {
             
                if ($this->_create_row_select) {
                   $select=''; $selects=array();
                   $file=$this->_tabel_tpls['tpl_row_select'];
                   $tpl =$this->get_tpl($file);
                   $name=$this->_fid.'_select';
                   if (isset($record[$fld_id])) {
                     $value=$record[$fld_id];
                   } else {
                     $value=-1;
                   }
                   if (isset($record['id']) && $activ_id) {
                     $aid=$record['id'];
                     if ($activ_id == $aid) $class.=' activ_row';
                   }
                   
                   //dbx_debug("## report_body  activ id=($activ_id)=($aid)");

                   $checked=$this->check_is_multiselect($value);
                   $tpl=(str_replace('{name}'    ,$name   ,$tpl));    
                   $tpl=(str_replace('{value}'   ,$value  ,$tpl));    
                   $tpl=(str_replace('{checked}' ,$checked,$tpl)); 
                   $tpl=(str_replace('{class}'   ,$class  ,$tpl));   
                   $tpl=(str_replace('{td-class}',$td_class,$tpl)); 
                   
                   if ($checked) $this->_post[$name]=1;
                   $row.=$tpl."\n";
                }
              
             
             
                foreach ($auto_flds as $no => $key) {
                     $xkey=''; $value='-?-';  $label=''; $skip=0;
                     $label=$auto_flds[$no];
                     if (isset($record[$key])) {
                       $xkey=$key;
                     } else {
                       if (isset($record[$no])) $xkey=$no;
                     }
                     if ($this->_create_row_select && $xkey == $fld_id) $skip=1;
                     if (!$skip && $label > '') {
                       if ($xkey) {
                          $value=$record[$xkey];
                          $value=$this->rpt_format($xkey,$value); // format datum
                       }

                       $file=$this->_tabel_tpls['tpl_row_col'];
                       $tpl =$this->get_tpl($file); // td
                       $tpl =(str_replace('{value}'   ,$value,$tpl));
                       $tpl =(str_replace('{class}'   ,$class,$tpl));
                       $tpl =(str_replace('{td-class}',$td_class,$tpl));
                       $row.=$tpl."\n";
                    }
                 }
               } 

               if ($this->_data_table) {
                $file=$this->_tabel_tpls['tpl_row_expander'];
                $tpl =$this->get_tpl($file); // th
                //$tpl=(str_replace('{name}','ID',$tpl)); // $value=get_row_id()
                $tpl=(str_replace('{class}'   ,$class,$tpl));
                $tpl=(str_replace('{td-class}',$td_class,$tpl));
                $row.=$tpl."\n";
              }



               if ($this->_create_row_edit) {
                 $file=$this->_tabel_tpls['tpl_row_edit'];
                 $tpl =$this->get_tpl($file); // th
                 if (isset($record[$fld_id])) {
                   $rid =$record[$fld_id]; // isset ??
                 } else {
                   $rid = -1;
                 }
 
                 $action='?dbx_modul='.$dbx_modul.'&dbx_action='.$dbx_action.'&dbx_work='.$dbx_work.'&dbx_do=row_edit&rid='.$rid.$add;
                 $tpl=(str_replace('{action}',$action ,$tpl));
                 $tpl=(str_replace('{class}',$ajax.' '.$class,$tpl));
                 $tpl=(str_replace('{td-class}',$td_class,$tpl));
                 
                 $row.=$tpl."\n";
               }

      
       
               if ($this->_create_row_copy) {
                $file=$this->_tabel_tpls['tpl_row_copy'];
                $class='dbxAjax'; $toggle='togel'; $target='target';
                if ($this->_confirm_copy > '')     $class.=' dbxConfirm';
                $confirm_data['confirm']=$this->_msg_confirm_copy;
                $tpl =$this->get_tpl($file,$confirm_data); 
                 if (isset($record[$fld_id])) {
                   $rid =$record[$fld_id]; // isset ??
                 } else {
                   $rid = -1;
                 }
 
                 $action='?dbx_modul='.$dbx_modul.'&dbx_action='.$dbx_action.'&dbx_work='.$dbx_work.'&dbx_do=row_copy&rid='.$rid.$add;
                 $tpl=(str_replace('{action}',$action ,$tpl));
                 $tpl=(str_replace('{class}',$ajax.' '.$class,$tpl));
                 $tpl=(str_replace('{td-class}',$td_class,$tpl));
                 $row.=$tpl."\n";
              }


               if ($this->_create_row_download) {
                 $file=$this->_tabel_tpls['tpl_row_download'];
                 $tpl =$this->get_tpl($file); // th
                 if (isset($record[$fld_id])) {
                   $rid =$record[$fld_id]; // isset ??
                 } else {
                   $rid = -1;
                 }
                 $href_dir_file=dbx_get_ModulVar('href_dir_file','','*');
                 $tpl=(str_replace('{href_dir_file}',$href_dir_file ,$tpl)); // $value=get_row_id()
                 $tpl=(str_replace('{class}',$ajax.' '.$class,$tpl));
                 $tpl=(str_replace('{td-class}',$td_class,$tpl));
                 $row.=$tpl."\n";
               }



               if ($this->_create_row_show) {
                 $file=$this->_tabel_tpls['tpl_row_show'];
                 $tpl =$this->get_tpl($file); // th
                 if (isset($record[$fld_id])) {
                   $rid =$record[$fld_id]; // isset ??
                 } else {
                   $rid = -1;
                 }
                 //$action='?dbx_modul='.$dbx_modul.'&dbx_action='.$dbx_action.'&dbx_work=row_show'.$dbx_work.'&rid='.$rid.$add;
                 $action='?dbx_modul='.$dbx_modul.'&dbx_action='.$dbx_action.'&dbx_work='.$dbx_work.'&dbx_do=row_show&rid='.$rid.$add;
                 $tpl=(str_replace('{action}',$action ,$tpl));
                 $tpl=(str_replace('{class}',$ajax.' '.$class,$tpl));
                 $tpl=(str_replace('{td-class}',$td_class,$tpl));
                 $row.=$tpl."\n";
               }
                     

               if ($this->_create_row_delete) {
                $file=$this->_tabel_tpls['tpl_row_delete'];

                $class='dbxAjax'; $toggle='togel'; $target='target';
                if ($this->_confirm_delete > '')   $class.=' dbxConfirm';
                $confirm_data['confirm']=$this->_msg_confirm_delete;

                $tpl =$this->get_tpl($file,$confirm_data); // th
                if (isset($record[$fld_id])) {
                  $rid =$record[$fld_id]; // isset ??
                } else {
                  $rid = -1;
                }
                //$action='?dbx_modul='.$dbx_modul.'&dbx_action='.$dbx_action.'&dbx_work=row_delete'.$dbx_work.'&rid='.$rid.$add;
                $action='?dbx_modul='.$dbx_modul.'&dbx_action='.$dbx_action.'&dbx_work='.$dbx_work.'&dbx_do=row_delete&rid='.$rid.$add;
                $tpl=(str_replace('{action}',$action ,$tpl)); // $value=get_row_id()
                $tpl=(str_replace('{class}' ,$ajax.' '.$class,$tpl));
                $tpl=(str_replace('{td-class}',$td_class,$tpl));
                $row.=$tpl."\n";
              }


 



               
              if ($this->_create_row_print) {
                $file=$this->_tabel_tpls['tpl_row_print'];
                $tpl =$this->get_tpl($file); // th
                if (isset($record[$fld_id])) {
                  $rid =$record[$fld_id]; // isset ??
                } else {
                  $rid = -1;
                }

                $action='?dbx_modul='.$dbx_modul.'&dbx_action='.$dbx_action.'&dbx_work='.$dbx_work.'&dbx_do=row_print&rid='.$rid.$add;
                $tpl=(str_replace('{action}',$action ,$tpl));
                $tpl=(str_replace('{class}',$ajax.' '.$class,$tpl));
                $tpl=(str_replace('{td-class}',$td_class,$tpl));
                
                $row.=$tpl."\n";
              }

             if ($this->_table_buttons == 'left') {
             

                if ($this->_create_row_select) {
                  $class='no-sort';
                  $select=''; $selects=array();
                  $file=$this->_tabel_tpls['tpl_row_select'];
                  $tpl =$this->get_tpl($file);
                  $name=$this->_fid.'_select';
                  if (isset($record[$fld_id])) {
                    $value=$record[$fld_id];
                  } else {
                    $value=-1;
                  }
                  if (isset($record['id']) && $activ_id) {
                    $aid=$record['id'];
                    if ($activ_id == $aid) $class.=' activ_row';
                  }
                  
                

                  $checked=$this->check_is_multiselect($value);
                  $tpl=(str_replace('{name}'    ,$name   ,$tpl));    
                  $tpl=(str_replace('{value}'   ,$value  ,$tpl));    
                  $tpl=(str_replace('{checked}' ,$checked,$tpl)); 
                  $tpl=(str_replace('{class}'   ,$class  ,$tpl));   
                  $tpl=(str_replace('{td-class}',$td_class,$tpl)); 
                  
                  if ($checked) $this->_post[$name]=1;
                  $row.=$tpl."\n";
               }

             
                foreach ($auto_flds as $no => $key) {
                     $xkey=''; $value='-?-';  $label=''; $skip=0; 
                     
                     $label=$auto_flds[$no];
                     if (isset($record[$key])) {
                       $xkey=$key;
                     } else {
                       if (isset($record[$no])) $xkey=$no;
                     }
                     if ($this->_create_row_select && $xkey == $fld_id) $skip=1;
                     if (!$skip && $label > '') {
                       if ($xkey) {
                          $value=$record[$xkey];
                          $value=$this->rpt_format($xkey,$value); // formatierung datum
                       }
                       $file=$this->_tabel_tpls['tpl_row_col'];
                       $tpl =$this->get_tpl($file); // td
                       $tpl =(str_replace('{value}',$value,$tpl));
                       $tpl =(str_replace('{class}',$class,$tpl));
                       $tpl =(str_replace('{td-class}',$td_class,$tpl));
                       $row.=$tpl."\n";
                    }
                 }
               } 
               

           }
           $line=(str_replace('[dbx:row]',$row,$line));
         }

         $tr_class='tr-row'; 
         $class   =''; // #todo
         //dbx_debug("activ row id=($activ_id) <br> $line"); 
         if (isset($record['id']) && $activ_id) {
          if ($activ_id == $record['id']) $class.=" activ_row";
        }



         if (isset($record['dbx_tr_class'])) $tr_class=$record['dbx_tr_class'];
         $even_odd="even"; if ($loop % 2 != 0) $even_odd="odd";
         $line=(str_replace('{even-odd}',$even_odd,$line));
         $line=(str_replace('{class}'   ,$class   ,$line));
         $line=(str_replace('{tr-class}',$tr_class,$line));

         //$line=$this->merge_obj($line); // ??? Report Line Obj nicht Formular Obj

         $pos = strpos($line,'{');
          if ($pos) {
           if (is_array($record)) {
             foreach ($record as $field => $value) {
                $field_name="{".$field."}";
                $value=$this->rpt_format($field,$value);
                if (!is_array($value)) { 
                  if ($value==null) $value='';
                  $line=(str_replace($field_name,$value,$line));
                }  
             }
           }
        }
        $col_count=$this->_table_col_count;
        $line=(str_replace('{col_count}',$col_count,$line));
        if (strpos($line,'{r}')) {
           $r=dbx_get_next_i(1);
           $line=(str_replace('{r}',$r,$line));
        }
        $content.=$line;
      } // record
    } // records
    return $content;
  }


  public function get_report_footer() {
    $content=$this->_footer;
    $col_count=$this->_table_col_count;
    $content=(str_replace('{col_count}',$col_count,$content));
    $content=$this->run_footer($content);
    return $content;
  }



  public function split_tpl($report) {
      $report_part = explode('<hr class="dbx_split">', $report);
      $report_header="";
      $report_body  ="";
      $report_footer="";
      $count=count($report_part);
      if ($count>0) {
        $report_body   = $report_part[0];
      }
      if ($count>1) {
        $report_header = $report_part[0];
        $report_body   = $report_part[1];
      }
      if ($count>2) {
        $report_header = $report_part[0];
        $report_body   = $report_part[1];
        $report_footer = $report_part[2];
      }
      $this->_haeder = $report_header;
      $this->_body   = $report_body;
      $this->_footer = $report_footer;
  }



  public function get_report_pages() {

     $modul  = $this->_dbx_modul;
     $action = $this->_dbx_action;
     $rpos   = $this->_rpos;
     $rrows  = $this->_rrows;
     $rcount = $this->_rcount;
     $link   = $this->_pagelink;
     $tpl    = $this->_tpl_pagination;

     if (!$link) $link='?dbx_modul='.$modul.'&dbx_action='.$action;

     $link=$this->_action;

     $content=$this->pagination($tpl, $link, $rpos, $rrows, $rcount);
     return $content;
  }


  private function lnk_page($p,$akt_page,$link,$rpos, $rrows, $rcount,$target) {
     $active=''; $class=''; $active=''; $current=''; $p_active=''; $s_active='';
     if ($p==$akt_page) {
       //$s_active=' <span class="sr-only">(current)</span>';
       $p_active=' aria-current="page"';
       $active  =' active';
       $current =' aria-current="page" ';
     }
     //$target='dbx_target_{i}';

     $rec['p']=$p.$s_active;
     $rec['href_page']=$link."&dbx_rrows=$rrows&dbx_rpos=".(($p-1)*$rrows).'&dbx_target='.$target;
     $rec['p_active'] =$p_active;
     $rec['active']   =$active.' a';
     $rec['current']  =$current;
     $rec['class']    =$class.' dbxAjax';
     return $rec;
  }

  private function pagination($tpl, $link, $rpos, $rrows, $rcount) {
      $content=''; $first=0; $last=0; $pages =0;
      if ($rcount==0) return '';
      if ($rrows ==0) return '';
      if ($rcount <= $rrows) return '';


      $pmax=$this->_but_pagination;
      //$pmax= 2; // Max Pagination Buttons
      $p_s=1; $p_e=$pmax;
      $end = ($rpos + $rrows);

      if ($rcount > $rrows) {
        $pages = intval($rcount / $rrows);
        if ($rcount % $rrows) $pages++;

        $akt_page = intval($end / $rrows);
        if ($end % $rrows) $akt_page++;

      }
      if ($pages == 0 && $rcount >0) $pages = 1;
      if ($akt_page==1)      $first=1;
      if ($akt_page==$pages) $last =1;

      if ($akt_page > 0) {
          $p_s=($akt_page - ($pmax /2));
          $p_s=intval($p_s +0.9999);
          if ($p_s < 1) $p_s=1;
          $p_e = ($p_s + $pmax -1);
      }

      $last =($rcount - $rrows);
      $prev=((($akt_page -2) * $rrows));
      $next=((($akt_page   ) * $rrows));

      if ($prev <      0) $prev=0;
      if ($next > $rcount) $next=(($pages-1) * $rrows);
      $i=$this->_next_i;
      $target='dbx_target_'.$i;
      $href_first=$link.'&dbx_rpos=0&dbx_rrows='.$rrows.'&dbx_target='.$target;
      $href_last =$link.'&dbx_rpos='.$last.'&dbx_rrows='.$rrows.'&dbx_target='.$target;
      $href_prev =$link.'&dbx_rpos='.$prev.'&dbx_rrows='.$rrows.'&dbx_target='.$target;
      $href_next =$link.'&dbx_rpos='.$next.'&dbx_rrows='.$rrows.'&dbx_target='.$target;

      $this->_sys['dbx_rpos'] =$rpos;
      $this->_sys['dbx_rrows']=$rrows;
      $dv['dbx_rpos'] =$rpos;
      $dv['dbx_rrows']=$rrows;

      //$content="Anzahl=($rcount) Seiten=($pages) Seite=($akt_page) first=($first) last=($last) Start=($p_s) End=($p_e) M=($pmax)<br>";

      for ($p = $p_s; $p <= $p_e; $p++) {
          $rdata[]=$this->lnk_page($p,$akt_page,$link,$rpos, $rrows, $rcount, $target);
          if ($p >= $pages) break;
      }



      $oReport=dbx_get_sys_object('dbxReport');
      $oReport->init('pagination');
      $oReport->_data        = $dv;
      $oReport->_dbx_modul   = 'dbx';
      $oReport->_dbx_action  = 'pagination';
      $oReport->_dbx_modul_id= 888;
      $oReport->_rdata   = $rdata;
      $oReport->_rcount  = $rcount;
      $oReport->_rrows   = $rrows;
      $oReport->_rpos    = $rpos;
      $oReport->_action  = $link;
      $oReport->_tpl     = $tpl;

      $content.=$oReport->run();

      $content=(str_replace('{href_first}',$href_first,$content));
      $content=(str_replace('{href_last}', $href_last, $content));
      $content=(str_replace('{href_prev}', $href_prev, $content));
      $content=(str_replace('{href_next}', $href_next, $content));

      return $content;
  }


  public function data_rows($data,$rpos,$rrows) {
     $rdata=array();
     for($i=$rpos; $i < ($rpos +$rrows); $i++) {
        if (isset($data[$i])) {
           $rdata[]=$data[$i];
        } else {
          break;
        }
     }
     return $rdata;
  }



  public function add_where($mode,$select,$where='') {
      if ($select) {
        if ($where) {
           $where.= " $mode (";
           $where.= $select;
           $where.= ') ';
        } else {
          $where= $select;
        }
      }
      return $where;
  }


  public function no_page_reset() {
     $this->_page_reset=0;
  }


  public function submit() {
      $submit=0;
      $nor=dbx_get_PostGetVar('dbx_nor',0, 'int');
      if (!$nor) $submit=$this->forward_submit();
      //dbx_debug("#submit=($submit) nor=($nor)",$_POST);
      return $submit;
  }


  public function set_sel($name,$value) {
    $_POST[$name]      =$value;
    $this->_data[$name]=$value;
    $this->_sys[$name] =$value;
  }

  public function get_sel($name,$default='',$rules='parameter') {
      $submit    =$this->submit();
      $page_reset=1; //  $this->_page_reset;

      $xdefault  =$default;
      if ($submit && $page_reset)      $this->_sys['dbx_rpos']=0;
      if (isset($this->_data[$name]))  $xdefault=$this->_data[$name];
      if (isset($this->_sys[$name]))   $xdefault=$this->_sys[$name];
      if ($page_reset) {
        $danger_value=$this->get_post($name,$xdefault,'*');
        $ok=$this->oValidator->validate($danger_value,$rules,$name);
        if (!$ok) $danger_value=$default;
        $value=$danger_value;
      } else {
        $value=$xdefault;
      }
      $this->_sys[$name]=$value;
      //dbx_debug("##GET-SEL## Submit=($submit) Name=($name) Value=($value)");
      return $value;
  }

  

  public function init($fid,$tpl='') {
     $this->forward_init($fid,$tpl);
     if ($fid == 'pagination') return;
     dbx_set_Remember('last_report_i',$this->_next_i,'dbx');

     $fast_response=0;
     $retval=$this->set_form_selects();
     if ($retval && $retval != 'add' && $retval != 'rem') $fast_response=1;
     if ($fast_response) {
       $response=$this->get_count_selects();
       $this->fast_response($response); // fast retval;
     } else {
       $this->_multi_select_work=$retval;
       //dbx_debug("Multi-select=($retval)",$_POST,$_GET);
       $fid=$fid.'_select';
       $selects=$this->get_post($fid,'',$rules='array|parameter'); // parameter+. 
       //dbx_debug ("SELECTS=($fid) work($retval)",$selects);
       if (is_array($selects)) {
          foreach ($selects as $id => $sel) {
            //dbx_debug("add-sel ($retval) ($id) ($sel) ");
            if ($retval=='add') $this->set_multi_select($sel);
          }
       } else {
         if ($retval=='rem') {
            $selects=$this->get_post('dbx_add','','parameter');  // parameter+.
            $deselect=explode('|',$selects);
            //dbx_debug("rem_selects=($selects)",$deselect);
            foreach ($deselect as $id => $sel) {
              //dbx_debug("rem-sel ($retval) ($id) ($sel) ");
              if ($sel) $this->del_multi_select($sel);
            }

         }
       }
     }
  }


  public function run($pages=0,$flds='',$mode='') {
    $content=''; $msg=''; $vars=''; $msg_class=''; $norep='';

    $submit =$this->submit();

    $this->_auto_flds=$flds;
    $this->_auto_mode=$mode;

    $rrows = $this->_rrows;
    $count = $this->_rcount;

    if (isset($this->_sys['dbx_rrows'])) {
       $rrows =$this->_sys['dbx_rrows'];
    }

     if ($this->_data_table=='auto') {
       if ($count > $rrows) {
          $this->_data_table=0;
       } else {
          $this->_data_table=1; 
       }
     }
    //dbx_debug("Count=($count) Show-Rows=($rrows) Data-table=($this->_data_table)");


    $i    =$this->_next_i;
    $fid  =$this->_fid;
    $mid  =$this->_dbx_modul_id;
    $modul=$this->_dbx_modul;
    $tpl  =$this->_tpl;
    $flds =$this->_flds;
    $msg  =$this->_msg_info;
    $create_sel_flds=$this->_create_sel_flds;

    



    //dbx_debug("FELDER",$flds);
    




  //----------------------------------------------------
    if ($fid != 'pagination') {

      //$work=$this->get_post('dbx_work');
      //if ($work == 'multi_select')   $this->set_multi_select('*'); // _rdata must be set to add select all (from _rdata)
      //if ($work == 'multi_deselect') $this->del_multi_select('*'); // deselect all or deselect all of Page _selector='*' or 'rdata';

      if (!is_array($this->_options_rsort)) {
        $this->_options_rsort['id']='ID';
      }

      $this->_options_rrows['1']=1;
      $this->_options_rrows['5']=5;
      $this->_options_rrows['10']=10;
      $this->_options_rrows['15']=15;
      $this->_options_rrows['20']=20;
      $this->_options_rrows['25']=25;
      $this->_options_rrows['50']=50;
      $this->_options_rrows['100']=100;
      $this->_options_rrows['1000']=1000;
  

      $this->_options_rdesc['ASC'] ='Aufsteigend';
      $this->_options_rdesc['DESC']='Absteigend';

      $this->_options_rselect['0']='*Alle*';
      $this->_options_rselect['1']='Ausgewählte';

      $count = $this->_rcount;
      if ($count != -88) {

        $max=1000; $pos=-1;
        if (isset($this->_data['rrows'])) $max=$this->_data['rrows'];

        if ($this->submit()) $this->_sys['dbx_rpos']=0; // hier nicht wegen multi_select
  
        if (isset($this->_sys['dbx_rrows'])) {
          $this->_rrows=$this->_sys['dbx_rrows'];
          //dbx_debug("SYS-rrows=($this->_rrows)");
        } else {
          $this->_rrows=$count;
        }
        if (isset($this->_sys['dbx_rpos'])) {
          $this->_rpos=$this->_sys['dbx_rpos'];
        } else {
          $this->_rpos=0;
        }

        //dbx_debug("#B-#RPOS=($this->_rpos) rows=($this->_rrows) Submit=($submit) Rpt=($fid)",$this->_sys);
      } // $cout -88

    }
    //=====================================================

    if ($fid != 'pagination' && $create_sel_flds==1) {
      if ($this->_data_table==88) {
         $next_val=0;
         foreach ($this->_options_rrows as $key => $nam) {
            if ($this->_rcount <= $key) $next_val=$key;
            if ($next_val) break; 
         } 
         if ($next_val) $this->_sys['dbx_rrows']=$next_val;
      }   
    
      $this->add_fld('dbx_rrows'  ,'select-single-label',$this->_options_rrows  ,'int'      ,'Anz.Seite'     , '','','');  // #+
      $this->add_fld('dbx_rsort'  ,'select-single-label',$this->_options_rsort  ,'parameter','Sortierung'    , '','','');  // #+
      $this->add_fld('dbx_rdesc'  ,'select-single-label',$this->_options_rdesc  ,'parameter','Auf/Ab'        , '','','');  // #+
      $this->add_fld('dbx_rwhere' ,'text-label'         ,''                     ,'parameter','Suchen'        , '','','');  // #+
      $this->add_fld('dbx_rselect','select-single-label',$this->_options_rselect,'parameter','Ausgewählte'   , '','','');  // #+
    }


    $msg_class='info';

    if($this->submit()) {
        $now=microtime(true);
        if (!isset($this->_sys['try_first'])) $this->_sys['try_first']=$now;
        if (!isset($this->_sys['try_count'])) $this->_sys['try_count']=0;
        if (!isset($this->_sys['try_error'])) $this->_sys['try_error']=0;
        $this->_sys['try_last'] = $now;
        $this->_sys['try_count']=($this->_sys['try_count'] +1);

        if($this->errors()) {
           $this->_sys['status']=-1;
           $this->_sys['try_error']=($this->_sys['try_error'] +1);
           $msg_class='warning';
           $errors=$this->_errors;
           if (is_array($errors)) {
              foreach ($errors as $fld => $txt) {
                 $rep='{msg:'.$fld.'}';
                 $fld_msg = "<div class=\"fld_msg $msg_class\">".$txt.'</div>';
                 //$report_tpl = (str_replace($rep,$fld_msg,$report_tpl));
              }
            }    
            $msg_class='error';
            $msg=$this->_msg_error;          
        } else {
          $this->_sys['status']=1;
          $this->_sys['try_error']=0;
          $msg_class='success';
          $msg=$this->_msg_success;
        }
        //$this->_sys=array_merge($this->_sys,$this->_post); ???
     } else {
       $msg_class='init';
       $msg=$this->_msg_info;
     }
    //$this->create_flds();

    $replaces=$this->_replaces;
    $replaces['dbx:select']    =$this->_create_sel_flds;
    $replaces['dbx:data_table']=$this->_data_table;
    
    //dbx_debug("###crete-sel=($create_sel_flds)");
    
    $report_tpl=$this->get_tpl('modul|'.$tpl,$replaces,'htm',$i);



    //if (!$this->_data_table) $report_tpl=str_replace('data-toggle="table"','',$report_tpl);  
    
    
    
    //dbx_debug("#Replaces Modul=($modul) TPL=($tpl)",$replaces);


    $report_tpl=$this->merge_tpl_data($report_tpl,$i);
    $report_tpl=$this->merge_fld_data($report_tpl,$i);
    $report_tpl=$this->merge_obj($report_tpl,$i);




    $this->split_tpl($report_tpl);

    $haeder=$this->get_report_haeder();
    $body  =$this->get_report_body();
    $footer=$this->get_report_footer();


    $content=$haeder.$body.$footer;

    if ($fid != 'pagination') $this->store_sysdata(); // every time
    if ($pages) {
      $ReportPages=$this->get_report_pages();
      $content=(str_replace('[dbx:pagination]',$ReportPages,$content));
    }
    //$content = dbx_interpreter($content);

    if ($fid !='pagination') {
      //$i=$this->next_i();
      $content=(str_replace('{i}',$i,$content));
      $msg_tpl=$this->get_form_msg($msg_class,$msg);
      $content = (str_replace('{obj:form_msg}',$msg_tpl,$content));
    }

    //$this->store_sysdata();
 

    $norep_ids=''; $js=$this->_js;
    if (is_array($js)) {
       $count=count($js);
       if ($count) {
         foreach ($js as $no => $javascript) {
           $javascript=(str_replace('{i}',$i,$javascript));
           $norep="\n".'<script type="text/javascript">'.$javascript.'</script>'."\n";
           $norep_ids.=dbx_add_norep($norep,$i);
         }
       }
    }
    $content=(str_replace('{i}',$i,$content));
    if ($norep_ids) $norep='<div class="norep">'.$norep_ids.'</div>';
    $content = (str_replace('[dbx:js]',$norep,$content));
    $content = (str_replace('[dbx:pagination]','',$content));


    return $content;
  }

}

