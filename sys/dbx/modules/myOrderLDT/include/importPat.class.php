<?php
namespace dbx\myOrderLDT;

Class importPat {
    private function get_pat_data($sys,$patient,$record) {
        $reader='reader_'.$sys;
        $oReader=dbx_get_Modul_include_object($reader);

        $lines  =$oReader->get_lines($patient);
        
        foreach ($record as $fld => $valuer) {
            $default = $record[$fld];
            $record[$fld] = $oReader->get_data($fld,$lines,$default); 
        }

        return $record;
    }


  public function import_pat() {
    $today  = date('Y-m-d', time()); 
    $path=dbx_get_file_dir(); 
    $file=dbx_get_cfg('myOrderLDT','import_pat');
    $sys =dbx_get_cfg('myOrderLDT','medisoft');
    $do  =dbx_get_ModulVar('dbx_do');

    if (strpos($file,':')) $path='';
    $path_file=$path.$file;
    $path_file=dbx_os_path_file($path_file);

    
    $timer=1600;  $add=0;

    $oForm=dbx_get_sys_object('dbxForm');

    $oForm->init('form-import-pat');
    $oForm->_action='?dbx_modul=myOrderLDT&dbx_action=import_pat&dbx_work=run&dbx_do='.$do;

    $oForm->_fld_change_state='all';
    $oForm->_msg_info='';
    $oForm->_msg_success='';
  
    $oForm->_try_max=99999999;
     
    $bdata['id']   ='button_{i}';
    $bdata['label']="Patient Daten einlesen ($path_file)";
    $bdata['sec']  = $timer;
  
    $progress =$oForm->get_tpl('dbx|progressbar-2');
    $button   =$oForm->get_tpl('button-submit');

    $date_time=date('d-m-Y H:i:s');
  
    $loop=0; $pat_id=0;
    $msg="Patient Daten einlesen.";
  
    $loop=$oForm->get_post('loop',90,'int');
    $loop=($loop + 10); if ($loop > 100) $loop=10;
 
    $submit=$oForm->submit();

    $exist='nicht vorhanden!';
    if (file_exists($path_file)) $exist='vorhanden';

    dbx_debug("import Pat submit=($submit) Loop=($loop) file=($path_file) exist=($exist)");

    if (file_exists($path_file)) {
        $oDB    = dbx_get_sys_object('dbxDB');
        $patient= file_get_contents($path_file);       
        $ok     = unlink($path_file);

        $add=1; $datum=$today;

        $record = $oDB->select1('my_order','new');
        if (isset($record['id'])) { 
            unset($record['id']);
            unset($record['create_date']);  
            unset($record['create_uid']); 
            unset($record['update_date']);
            unset($record['update_uid']);
            unset($record['owner']); 

            unset($record['anforderungen']);
            unset($record['profile']); 
            unset($record['bemerkung']);

        }    
  

        
        $record= $this->get_pat_data($sys,$patient,$record);
        if (!isset($record['nachname'])) $record=0;
        if ( isset($record['nachname'])) {
            if ($record['nachname'] <= ' ') $record=0;
        }
        dbx_debug("#read pat=",$record); 
        

        if (is_array($record)) {            

            $record['datum'] = $datum ;
            $vorname =$record['vorname'];
            $nachname=$record['nachname'];
            $gebdat  =$record['gebdat'];
            $lanr    =$record['lanr'];
            $bsnr    =$record['bsnr'];

            $arzt=$oDB->select1('my_arzt',"lanr ='$lanr' and bsnr = '$bsnr'");
            if (is_array($arzt)) {
               $record['arzt']=$arzt['id']; 
            } else {
               $arzt=array(); 
               $arzt['name']='-unbekannt-';
               $arzt['bsnr']= $bsnr; 
               $arzt['lanr']= $lanr;
               $ok=$oDB->insert('my_arzt',$arzt); 
               $record['arzt']=$oDB->_insert_id;  
            }


            $record['pk']='k';    // check is privat 
            if ($record['status']           ==  '000000')   $record['pk']='p';
            if ($record['kostentraeger']    ==  '000000')   $record['pk']='p';
            if ($record['versicherungsnr']  =='')           $record['pk']='p';
            if ($record['kostentraeger']    =='')           $record['pk']='p'; 
            $record['praxis'] =dbx_get_cfg('myOrderLDT','praxis');

            $where = "datum == '$datum' and vorname == '$vorname' and nachname == '$nachname' and gebdat == '$gebdat' ";
            // oder
            $where = 'id = 0'; //  add allways a new record #todo cfg

            dbx_debug("#SAVE-PAT",$record);
            dbx_set_Remember('set_date','today');
            $ok=$oDB->save('my_order',$record,$where);
            $pat_id=$oDB->_insert_id;
        }
        //return $patient;
    }

    $data['value']=$loop;
   
    $pdata['msg']  =$msg;
    $pdata['value']=$loop;
    $pdata['width']=$loop;
    $bdata['sec']  =$timer;
 
    $oForm->add_fld('loop','text-label',$data);
    $oForm->add_obj('progress','obj-value',$progress,$pdata);
    $oForm->add_obj('button'  ,'obj-value',$button  ,$bdata);

    //if ($do != 'reload') $add=0;

    if (!$add) $oForm->add_js_autosubmit('#dbx_form_{i}',$timer);
    if ($add) { 
        $reload=dbx_get_ModulVar('dbx_do');
        $load  =dbx_get_Remember('dbx_load_pat',1,'*','myOrderLDT');
         
        //dbx_debug("ADD-PAT load=($load) reload=($reload)"); 


        if ($load) { 
            if ($reload=='reload') $oForm->add_js("dbx_reload('self');");
            if ($reload=='parent') $oForm->add_js("dbx_reload('parent');");
            if ($reload=='edit' && !$pat_id) $oForm->add_js("dbx_reload('self');");
            if ($reload=='edit' && $pat_id) {
               dbx_debug("edit-pat=($pat_id)");
               dbx_redirect('?dbx_modul=myOrderLDT&dbx_action=order&dbx_work=edit_order&dbx_do=new&rid='.$pat_id);              
            }
        }    
        if (!$load) $oForm->add_js_autosubmit('#dbx_form_{i}',$timer);

    }
 
    // dbx_debug("Submit=($add)");

    $content=$oForm->run();      
    
    return $content;
  }

  public function import_init() {
     $do =dbx_get_ModulVar('dbx_do');
     $url=dbx_get_base_url()."?dbx_modul=myOrderLDT&dbx_action=import_pat&dbx_work=run&dbx_do=$do&dbx_page=iframe";
     $oTPL=dbx_get_sys_object('dbxTPL');
     $data['src']=$url;
     $content=$oTPL->get_tpl('dbx','iframe',$data);
     //return "URL=$url";
     return $content;
  } 


   public function import($path_file) {
    $sys    = dbx_get_cfg('myOrderLDT','medisoft');
    $today  = date('Y-m-d', time()); 
    $pat_id = 0;
    if (file_exists($path_file)) {
        $oDB    = dbx_get_sys_object('dbxDB');
        $patient= file_get_contents($path_file);       
        $ok     = unlink($path_file);

        $datum=$today;

        $record = $oDB->select1('my_order','new');
        if (isset($record['id'])) { 
            unset($record['id']);
            unset($record['create_date']);  
            unset($record['create_uid']); 
            unset($record['update_date']);
            unset($record['update_uid']);
            unset($record['owner']); 

            unset($record['anforderungen']);
            unset($record['profile']); 
            unset($record['bemerkung']);

        }    
  

        
        $record= $this->get_pat_data($sys,$patient,$record);
        if (!isset($record['nachname'])) $record=0;
        if ( isset($record['nachname'])) {
            if ($record['nachname'] <= ' ') $record=0;
        }
        if (is_array($record)) {            

            $record['datum'] = $datum ;
            $vorname =$record['vorname'];
            $nachname=$record['nachname'];
            $gebdat  =$record['gebdat'];
            $lanr    =$record['lanr'];
            $bsnr    =$record['bsnr'];

            $arzt=$oDB->select1('my_arzt',"lanr ='$lanr' and bsnr = '$bsnr'");
            if (is_array($arzt)) {
               $record['arzt']=$arzt['id']; 
            } else {
               $arzt=array(); 
               $arzt['name']='-unbekannt-';
               $arzt['bsnr']= $bsnr; 
               $arzt['lanr']= $lanr;
               $ok=$oDB->insert('my_arzt',$arzt); 
               $record['arzt']=$oDB->_insert_id;  
            }


            $record['pk']='k';    // check is privat 
            if ($record['status']           ==  '000000')   $record['pk']='p';
            if ($record['kostentraeger']    ==  '000000')   $record['pk']='p';
            if ($record['versicherungsnr']  =='')           $record['pk']='p';
            if ($record['kostentraeger']    =='')           $record['pk']='p'; 
            if ($record['pk'] == 'k' ) $record['formular']='m10aIgel';
            if ($record['pk'] == 'p' ) $record['formular']='igel';


            $where = "datum == '$datum' and vorname == '$vorname' and nachname == '$nachname' and gebdat == '$gebdat' ";
            // oder
            $where = 'id = 0'; //  add allways a new record #todo cfg


            //dbx_debug("#save-pat-x=",$record);

            $ok=$oDB->save('my_order',$record,$where);
            $pat_id=$oDB->_insert_id;
        }
        
    }
    return $pat_id;

      
   }


   public function run() {
      
      $work=dbx_get_ModulVar('dbx_work');
      $content= "Unknow work ($work)";

      if ($work=='init') return $this->import_init();
      if ($work=='run')  return $this->import_pat();
      
      return $content;
   } // run

} // class

