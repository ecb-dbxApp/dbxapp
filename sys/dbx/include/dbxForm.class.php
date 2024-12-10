<?php



class dbxForm extends \dbxObj  {

  Public $oValidator;
  Public $oTPL;
  Public $_dbx_view='';
  Public $_view_sync = '';
  Public $_view_mode = '';

  Public $_mode='mix'; // tpl,php,mix
  Public $_ajax=true;
  Public $_store_mode='session';

  Public $_dbx_modul_id=0;
  Public $_dbx_modul ='modul';
  Public $_dbx_lng   ='';
  Public $_dbx_design='';
  Public $_dbx_action='';
  Public $_dbx_work  ='';
  Public $_dbx_page  ='';

  Public $_rid   = 0;
  Public $_action='';
  Public $_tpl=   '';
  Public $_fid=   '';
  Public $_next_i= 0;
  //Public $_try=0;


  Public $_dd='';
  Public $_data=array();
  Public $_post=array();
  Public $_sys =array();

  //Public $_form = '';
  Public $_obj      = array();
  Public $_js       = array();
  Public $_css      = array();
  //Public $_xflds    = array();
  Public $_flds     = array();
  Public $_infos    = array();
  Public $_errors   = array();
  Public $_warnings = array();
  Public $_replaces = array();


  public $_general_error     =''; 

  Public $_msg_info          = '#form_msg_info#';
  Public $_msg_success       = '#form_msg_success#';
  Public $_msg_error         = '#form_msg_error#';
  Public $_msg_warning       = '#form_msg_warning#';

  Public $_tpl_form_info     = 'form-alert-info';
  Public $_tpl_form_success  = 'form-alert-success';
  Public $_tpl_form_error    = 'form-alert-danger';
  Public $_tpl_form_warning  = 'form-alert-warning';

  Public $_tpl_fld_info      = 'fld-alert-info';
  Public $_tpl_fld_success   = 'fld-alert-success';
  Public $_tpl_fld_error     = 'fld-alert-danger';
  Public $_tpl_fld_warning   = 'fld-alert-warning';

  Public $_tpl_max_try       = 'form-alert-maxtry';
  Public $_try_reset         = 120; // sec nach dem der try_count auf 0 gesetzt wird;
  Public $_try_max           = 20;
  Public $_try_msg           = 'Max {try_count} try. Suspend for {sec} seconds'; 

  Public $_create_flds      =1;
  Public $_reload_record    =1;
  Public $_reload_run       =0;
  Public $_reload_transform =0;
  Public $_reload_suffix    ='_rlo';

  Public $_fld_change_state ='fld';

  Public $_fld_changes      =-1;
  Public $_form_submit      =-1;
  Public $_form_validate    =-1;

  Public $_editor_fld       ='';

  Public $_page_reset       =1;

  public $_confirm_delete   ='';
  public $_confirm_copy     ='';

  public function __construct($id='',$tpl='') {
     $this->oValidator=dbx_get_sys_object('dbxValidator');
     $this->oTPL      =dbx_get_sys_object('dbxTPL');
     if (!$tpl) $tpl=$id;
     if ($id) $this->init($id,$tpl);
  }

  public function __destruct() {
     //$this->clear();
  }

  public function clear_sys() {
     //dbx_debug('##CLEAR _SYS##');
     $this->_sys=array();
  }

  public function clear() {
     $this->_forward_clear();
  }

  public function _forward_clear() {
     $this->_reload_record=0;
     $this->_obj     =array();
     $this->_js      =array();
     $this->_css     =array();
     $this->_flds    =array();
     $this->_errors  =array();
     $this->_warnings=array();
     $this->_post    =array();
     $this->_sys     =array();
     $this->_data    =array();
     $this->_replaces=array();
  }
  
  public function forward_init($fid,$tpl='') { // called by __construct()
    $this->clear();
    if ($tpl=='') $tpl=$fid;
    //$i=dbx_get_SessionVal($fid,0,'form_id');
    //if (!$i) 
    $i=$this->next_i();
    
    $this->_fid =$fid;
    $this->_dbx_modul   =dbx_get_SysVar('dbx_activ_modul'   ,'modul');
    $this->_dbx_action  =dbx_get_SysVar('dbx_activ_action'  ,'run');
    $this->_dbx_page    =dbx_get_SysVar('dbx_page'          ,'default');
    $this->_dbx_design  =dbx_get_SysVar('dbx_design'        ,'default');
    $this->_dbx_lng     =dbx_get_SysVar('dbx_lng'           ,'de');
    $this->_dbx_modul_id=dbx_get_sysVar('dbx_activ_modul_id',1);
    $this->_tpl         =$tpl;
    $this->_data        =array();
    $this->_next_i      =$i;
    $this->_reload_run  =0;
    $this->_sys =$this->load_sysdata();
    

    $secure=md5($fid);
    $this->_sys[$fid] =$secure; // secure value
    $this->_data[$fid]=$secure; // secure value
    $this->add_fld($fid,'dbx|hidden',$fid,'*','parameter');  //#+
    //$this->add_fld($fid,'obv-value',$fid,'*','parameter');  //#+
    
    $modul=dbx_get_SysVar('dbx_activ_modul','dbx'); 
    
    $process =dbx_get_ModulVar('dbx_process',dbx_get_SessionVal('dbx_process',0),'parameter');
    if ($process) { 
      dbx_set_SessionVal('dbx_process',$process);
      dbx_debug("#SET-PROCESS=($process) Modul=($modul)");
   }
  }

  

  public function set_msg_info($msg) {
     $this->_msg_info=$msg;
  }
  public function set_msg_ok($msg) {
     $this->_msg_success=$msg;
  }

  public function set_msg_error($msg) {
     $this->_msg_error=$msg;
  }
  public function set_msg_warning($msg) {
     $this->_msg_warning=$msg;
  }


  public function get_fld_id($fld) {
     $fld_id=dbx_part_select(' name="','"',$fld);
     return $fld_id;
  }


  public function add_norep($content) {
   if (isset($_SESSION['dbx']['norep'])) {
     $xnorep=$_SESSION['dbx']['norep'];
     if (is_array($xnorep)) {
       for($i=0; $i < 2; $i++) { // noreps can include noraps
         foreach ($xnorep as $id => $norep) {
           $xid= '['.$id.']';
           $content = str_replace($xid,$norep,$content);
         }
       }
     }
   }
   return $content;
}




  public function fast_response($response,$interpreter=0) {
   $oSession=dbx_get_sys_object('dbxSession');
   if ($interpreter) {
      $oInterpreter=dbx_get_sys_object("dbxInterpreter");
      $response=$oInterpreter->run($response);
      $response=$this->add_norep($response);
   }
   $oSession->save_session(0);
   echo $response;
   exit;
 }



  public function get_tpl($tpl,$data='',$type='htm',$i=0) {
   //$out="In TPL=($tpl) Type=($type) -> "; 
   $modul='dbx'; $count=0;
   $pos  =strpos($tpl,'|'); 
   if ($pos){
      $parts = explode('|',$tpl);
      $count = count($parts);
      if ($count == 2 ) {
         $modul=$parts[0];
         $tpl  =$parts[1];
      }
   }
   if ($modul=='modul') $modul=dbx_get_SysVar('dbx_activ_modul','dbx');

   //$out=(" modul=($modul) TPL=($tpl) Count=($count) Pos=($pos)<br>"); 
   //dbx_debug("##GET-TPL##".$out);
   if (!str_contains($tpl,' ')) $tpl=$this->oTPL->get_tpl($modul,$tpl,$data,$type,$i);
   return $tpl;
  }


  public function obv_value($content,$id,$value) {
      $rep='{obv:'.$id.'}'; $val='';
      if (is_string($value)) {
        $val=htmlspecialchars($value, ENT_QUOTES);
        $content=str_replace($rep,$val,$content);
      }
      return $content;
  }


  public function merge_tpl_data($tpl,$i=0) {
     $editor=dbx_get_SysVar('dbx_editor',0,'int');
     if (!$i && !$editor) $i=$this->_next_i;
     $replaces=array();
     $replaces['dbx_modul']  =$this->_dbx_modul;
     $replaces['dbx_action'] =$this->_dbx_action;
     $replaces['dbx_page']   =$this->_dbx_page;
     $replaces['dbx_design'] =$this->_dbx_design;
     $replaces['dbx_lng']    =$this->_dbx_lng;
     $replaces['action']     =$this->_action;
     $replaces['fid']        =$this->_fid;
     $replaces['rid']        =$this->_rid;
     $replaces['self']       =dbx_get_self_url();
     if ($i) $replaces['i']  =$i;
     $tpl=$this->oTPL->replaces($tpl,$replaces);
     return $tpl;
  }



  public function merge_obj($content,$i) {
     //$editor=dbx_get_SysVar('dbx_editor',0,'int');
     $objs=$this->_obj;
     if (is_array($objs)) {
        foreach ($objs as $id => $obj) {
           $fid='{obj:'.$id.'}';
           //if (!$editor) $obj=str_replace('{i}',$i,$obj);
           $obj=$this->oTPL->replaces($obj,$this->_replaces);
           $content=str_replace($fid,$obj,$content);
        }
     }
     // Interpreter ? norep ? #ToDo
     return $content;
  }



 public function store_sysdata() {
    $section=$this->_fid;
    $modul  =$this->_dbx_modul;
    //$action =$this->_dbx_action;
    $mode   =$this->_store_mode;
    $value  =$this->_sys;
    $key='sysdata';
    if ($section) {
       $key='sysdata';
       if ($mode=='session') dbx_set_SessionVal($key,$value,$section,$modul);
    }
 }



 public function load_sysdata() {
    $sysdata=array();
    $section=$this->_fid;
    $modul  =$this->_dbx_modul;
    $key    ='sysdata';
    //$action =$this->_dbx_action;
    $empty=array(); // the default Value;
    if ($section) {
       $sysdata=dbx_get_SessionVal($key,$empty,$section,$modul);
    }
    return $sysdata;
 }








  public function delete_forward_set_form_id($fid,$data,$tpl,$mid,$modul) { // kann weck
    $this->clear(); // make by __construct
    if ($modul=='modul') $modul='';
    if (!is_array($data))  $data=array();

    if (!$tpl)    $tpl   =$fid;
    if (!$mid)    $mid   =dbx_get_sysVar('dbx_modul_id');
    if (!$modul)  $modul =dbx_get_sysVar('dbx_activ_modul');
    $action = dbx_get_sysVar('dbx_activ_action');
    $page   = dbx_get_sysVar('dbx_page','default');
   
    $this->_dbx_modul_id=$mid;
    $this->_dbx_modul   =$modul;
    $this->_dbx_action  =$action;
    $this->_dbx_page    =$page;
    $this->_tpl         =$tpl;
    $this->_fid         =$fid;
    $this->_data        =$data;

    $this->_reload_run=0;

    //$this->_old=$this->load_post();
    $this->_sys =$this->load_sysdata();
    //$this->_post=$this->load_post();

    $secure=md5($modul.$fid);
    $this->_sys[$fid] =$secure;  // secure value
    $this->_data[$fid]=$secure;  // secure value
    $this->add_fld($fid,'dbx|hidden',$fid,'','parameter','','','','');  //#+
  }

  public function add_js_countdown($id,$timer) {
    $ajax=dbx_get_sysVar('dbx_ajax',0);
    if (!$ajax) {
      $id='#'.$id;
      $js="dbxCountDown('$id');";
      $js=$this->get_js_repeat($js,1000);
      $this->_js[]=$js;
    }
  }






  public function add_js_call($id,$call) {
   $pos=strpos($id,'_{i}');
   if (!$pos) $id.='_{i}';
   $i=$this->_next_i;
   $id=str_replace('{i}',$i,$id);   

   $path='dbx/design/_all/';
   $sel = strtolower($call);

   switch ($sel) {

      case 'datatable':
         $this->add_js_lib('dbx/add_ons/datatables/datatables.min.css');      
         $this->add_js_lib('dbx/add_ons/datatables/datatables.min.js'); 
         $this->add_js_lib($path.'js/dbx-datatables.js');
         $this->add_js_ready("dbxDataTable('$id');");   
      break;

      case 'datatable1':
         $this->add_js_lib('dbx/add_ons/datatables/datatables.min.css');      
         $this->add_js_lib('dbx/add_ons/datatables/datatables.min.js'); 
         $this->add_js_lib($path.'js/dbx-datatables.js');
         $this->add_js_ready("dbxDataTable1('$id');");   
      break;

      case 'multiselect':
          // neue funktion in dbx-all 

         //dbx_debug("add js/css for multiselect id=($id)");
         //$this->add_js_lib($path.'js/dbx-multiselect.js');
         //$this->add_js_lib($path.'css/dbx-multiselect.css');
         //$this->add_js_ready("multiSelectWithoutCtrl('$id');");
      break;

      case 'multiselect2':
         //dbx_debug("add js/css for multiselect2 id=($id)");
         $this->add_js_lib($path.'js/dbx-quicksearch.js');
         $this->add_js_lib($path.'js/dbx-multiselect2.js');
         $this->add_js_lib($path.'css/dbx-multiselect2.css');
         $this->add_js_ready('$("#'.$id.'").multiSelect2();');

      break;

      case 'tree':
         $this->add_js_lib($path.'css/dbx-tree.css');
         $this->add_js_lib($path.'js/dbx-tree.js');
      break;

      case 'upload':
         $this->add_js_lib(url:  'dbx/add_ons/jquery/jquery.uploadfile.min.js');
         $this->add_js_ready('dbxUploadImg("#'.$id.'");');
      break;




      case 'editor-ace':
         $this->add_js_lib('dbx/add_ons/ace/ace.js');
         $this->add_js_lib('dbx/add_ons/ace/theme-github.js');
         $this->add_js_lib('dbx/add_ons/ace/mode-html.js');       
         $this->add_js_lib('dbx/add_ons/jquery/jquery-ace.min.js');
         $this->add_js_ready('$("#'.$id.'").ace({ theme: "github", lang: "html" });');
      break;  


      case 'editor-tiny':
         $this->add_js_lib('dbx/add_ons/tinymce/tinymce.min.js');
         $this->add_js_lib('dbx/add_ons/tinymce/jquery.tinymce.min.js');
         $this->add_js_lib('dbx/add_ons/tinymce/dbx.tinymce.js');       
         $this->add_js_ready('dbxInitHtml1("#'.$id.'");');
      break;  



      default:
         $modul=dbx_get_SysVar('dbx_activ_modul');
         $msg="add_js_call($call) for ($id) called by ($modul) not defined";
         $this->add_js('alert("'.$msg.'");'); 
   
   } // switch
  
      
   


} 




  public function add_js_lib($url) {
   $url=dbx_get_base_url().$url;
   $js='dbxAddLib("'.$url.'");';
   $this->add_js($js);
  }


  public function add_js($js,$time=0,$ready=1) {
    //if ($time)  $js=$this->get_js_repeat($js,$time);
    if ($time)  $js=$this->get_js_time($js,$time); 
    if ($ready) $js=$this->get_js_ready($js); 
    $this->_js[]=$js;
  }







  public function add_js_content($id,$content) {
    $content=addslashes($content);
    $content=str_replace("\n",' ',$content);
    $content=str_replace("\r",' ',$content);
    $content=str_replace('  ',' ',$content);
    $content=preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $content);
    $js="$('$id').html('$content');";
    $this->_js[]=$js;
    //$this->_init_ajax=true;
    return $content;
  }


  public function add_js_select($element) {
     if ($element=="tr") {
       $js="
       $('.tr-select').on( 'click', 'tr', function () {
           $(this).toggleClass('active');
       } );
       ";
    }
    if ($element=="td") {
      $js="
      $('.td-select').on( 'click', 'td', function () {
          $(this).toggleClass('active');
      } );
      ";
   }
   $this->_js[]=$js;
  }


  
  
  public function get_js_ready($jsf) {  
   $design=dbx_get_SysVar('dbx_design');
   $jsf=str_replace('{design}',$design,$jsf);
   $js ='$(document).ready(function() { ';
   $js.= $jsf;
   $js.=' } );';
   $js.="\n";
   return $js;    
}

  
  
  public function add_js_ready($js) {
     $js=$this->get_js_ready($js);   
     $this->_js[]=$js;   
  }





  public function get_js_togel_modal($modal='#dbxmodal1',$time=0) {
     $js="$('$modal').modal('toggle');";
     if ($time) $js=$this->get_js_time($js,$time);
     $js='<script>'.$js.'</script>'."\n";
     return $js;
  }

  public function get_js_close_modal($modal='#dbxmodal1',$time=0,$redirect='',$noscr=0) {
     $js="dbxCloseModal('$modal');";
     if ($redirect) $js.="dbxRedirect('$redirect');";
     if ($time)     $js =$this->get_js_time($js,$time);
     if (!$noscr)   $js='<script>'.$js.'</script>'."\n";
     return $js;
  }



  public function get_js_autosubmit($form_name,$time=0) {
     //$i=dbx_get_SessionVal($form_name,0,'form_id');
     $i=$this->_next_i;
     $js="dbxAjaxAutoSubmit('#dbx_form_{i}');";
     $js=str_replace('{i}',$i,$js);
     $this->add_js($js,$time);
     if ($time) $js=$this->get_js_time($js,$time);
     $js='<script>'.$js.'</script>';
     return $js;
  }


   public function add_js_close($time=0) {
      $js="dbxCloseWindow();";
      $this->add_js($js,$time);
   }


   public function add_js_autosubmit($fid,$time=0) {
      $i=$this->_next_i;
      $js="dbxAjaxAutoSubmit('$fid');";
      $js=str_replace('{i}',$i,$js);
      $this->add_js($js,$time);
   }
   
   public function add_js_redirect($url,$time=0) {
      $i=$this->_next_i;
      $js="dbx_reload('$url');";
      if ($time) $js=$this->get_js_time($js,$time);
      $js=str_replace('{i}',$i,$js);
      $this->_js[]=$js;
   } 


  public function add_js_close_modal($modal='#dbxmodal1',$time=0,$redirect='') {
     $i=$this->_next_i; 
     $js=$this->get_js_close_modal($modal,$time,$redirect,1);
     $js=str_replace('{i}',$i,$js);
     $this->_js[]=$js;
  }


  public function add_js_observe($fld,$time=1000) {
     $fld.='_{i}';
     $js="dbxObserve('$fld');";
     if ($time) $js=$this->get_js_repeat($js,$time);
     $this->_js[]=$js;
  }


  public function get_js_repeat($js,$time) {
     $a=''; $e=''; $clear='';
     if ($time) {
       $a='setInterval(function() { ';
       $e=" }, $time);";
       //$clear="clearInterval('dbxAjaxAutoSubmit');";
     }
     return $clear.$a.$js.$e;
  }

  public function get_js_time($js,$time) {
   $a=''; $e='';
   if ($time) {
     $a='setTimeout(function() { ';
     $e=" }, $time);";
   }
   return $a.$js.$e;
}

  public function add_js_time($js,$time) {
     $js=$this->get_js_time($js,$time);
     $this->_js[]=$js;
  }

  public function add_js_activate_tab($id_view,$id_tab,$time=10,$ready=1) {
    $js="$('$id_view a[href=\"$id_tab\"]').tab('show');";
    if ($time)  $js=$this->get_js_time($js,$time);
    if ($ready) $js=$this->get_js_ready($js);
    $this->_js[]=$js;
  }

  public function add_rep($key,$val) {
    $this->_replaces[$key]=$val;
  }

  public function add_fld_error($name,$msg='') {
      $this->_errors[$name]=$msg;
  }
  public function add_fld_warning($name,$msg='') {
      $this->_warnings[$name]=$msg;
  }


  public function get_fld_value($name,$default='',$rules='',$submit=-1) {
      $danger_value =$default;
      if ($submit < 0) $submit=$this->submit();
      if ($submit) {
         if (isset($this->_sys[$name])) $danger_value=$this->_sys[$name];
         $danger_value=$this->_get_post($name,$danger_value);
      } else {
        if (isset($this->_data[$name])) $danger_value=$this->_data[$name];
        if (isset($this->_sys[$name]))  $danger_value=$this->_sys[$name];
        // No Submit but URL
        //if (isset($_GET[$name])) {
        //   $danger_value=$_GET[$name];
        //}
      }
      if ($rules) {
        $ok=$this->oValidator->validate($danger_value,$rules,$name);
        if (!$ok) $this->add_fld_error($name,'f:'.$rules);
        if (!$ok) $danger_value=$default;
      }
      //dbx_debug("### FLD-VAL=($name) rules=($rules) submit=($submit)  Val=",$danger_value);
      $this->_sys[$name]=$danger_value;
      return $danger_value;
  }


  public function get_post_data($name,$default='',$rules='parameter') {   // default war null
   $danger_value=$this->_get_post_data($name,$default);
   if ($rules) {
      $ok=$this->oValidator->validate($danger_value,$rules,$name);
      if (!$ok) $this->add_fld_error($name,'p:'.$rules);
      if (!$ok) $danger_value=$default;
   }
   $value=$danger_value;
   //dbx_debug("POST v=($name) r=($rules) d=($danger_value)");
   return $value;
}

  public function get_post($name,$default='',$rules='alphanum') {
     $danger_value=$this->_get_post($name,$default);
     if ($rules) {
        $ok=$this->oValidator->validate($danger_value,$rules,$name);
        if (!$ok) $this->add_fld_error($name,'p:'.$rules);
        if (!$ok) $danger_value=$default;
     }
     $value=$danger_value;
     //dbx_debug("POST v=($name) r=($rules) c=($clean) v=($value) d=($danger_value)");
     return $value;
  }


  Private function _get_post_data($name,$default='')  {
   Global $_POST; Global $_GET;
   $set=0; $value=$default;

   if (isset($_POST[$name])) {
       $value = $_POST[$name];
       $set=1;
   }  else {
      if (isset($_GET[$name])) { 
         $value= $_GET[$name];
         $set=1;
      }   
   }
   if (!$set) {
      if (isset($this->_data[$name])) $value=$this->_data[$name];
   }
   return $value;
}



   Private function _get_post($name,$default='')  {
      Global $_POST; Global $_GET;
      $value=$default;

      if (isset($_POST[$name])) {
          $value = $_POST[$name];
      }  else {
         if (isset($_GET[$name])) $value= $_GET[$name];
      }
      return $value;
  }



  public function add_obj($obj,$tpl,$data='',$data2='') {
      //dbx_debug("##add-obj=($obj) Tpl=($tpl)");
      if ($tpl != 'obj-value' && $tpl != 'obv-value') {
          $tpl=$this->get_tpl($tpl,$data);
      } else {
        if ($tpl=='obv-value') $tpl=htmlspecialchars($data, ENT_QUOTES);
        if ($tpl=='obj-value') $tpl=$data;
        if ($data2) $tpl=$this->oTPL->replaces($tpl,$data2); 
      }       
      $this->_obj[$obj]=$tpl; 
  }

  public function add_action($obj,$tpl,$action,$data='') {
    //$dbx_modul  = $this->_dbx_modul;
    //$dbx_action = $this->_dbx_action;
    //$dbx_page   = $this->_dbx_page;
    $xaction = $this->_action;
    if ($action[0] == '&') {
      $x_action = $xaction.$action;
    } else {
      $x_action=$action;
    }
    $tpl = $this->get_tpl($tpl,$data);
    $tpl= str_replace('{action}',$x_action,$tpl);
    $this->_obj[$obj]=$tpl;
  }

private function include_dd($dd_file) {
  $fields=array();
  $dd_file=dbx_os_path_file($dd_file);
  //dbx_debug("READ DD file=($dd_file)");

  if (file_exists($dd_file)) {
     include $dd_file;
  }
  return $fields;
}


private function get_dd_fld($fields,$fld) {
   $field='';
   foreach ($fields as $no => $record) {
      if (isset($record['name'])) {
        if ($record['name'] == $fld) $field=$record;
      }
   }
   return $field;
}

public function get_dd($dd,$fld,$var) {
    $dd_file='';
    $value='dd:';

    //dbx_debug("#GET_dd($dd) fld=($fld) var=($var) ");

    $mod=''; if ($dd) $mod= 'dd';
    if (strpos($dd,'cfg:') === 0)  { 
      $mod= 'cfg'; 
      $dd = substr($dd,4);
    }  
    if (strpos($dd,'def:') === 0)  {
      $mod= 'def'; 
      $dd = substr($dd,4);
    }   
    if (strpos($dd,'mod:') === 0)  {
      $mod= 'mod'; 
      $dd = substr($dd,4);
    }   
    

    //dbx_debug("DD=($dd) MOD=($mod)");

    if ($mod) {
      if ($mod=='dd')   $dd_file=dbx_os_path_file(dbx_get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.php');

      if ($mod=='cfg')  $dd_file = dbx_get_base_dir()."dbx/modules/$dd/cfg/config.dd.php";
   
      if ($mod=='def')  $dd_file = dbx_get_base_dir()."dbx/modules/$dd/dd/$dd".'.dd.php';
      
      if ($mod=='mod')  { 
         $modul   = dbx_get_SysVar('dbx_activ_modul','dbx');
         $dd_file = dbx_get_base_dir()."dbx/modules/$modul/dd/$dd".'.dd.php';
      }   
      //dbx_debug("dd file ($dd) mod=($mod) file=($dd_file) field=($fld) Var=($var)");


      if ($dd_file) {
         $fields=$this->include_dd($dd_file);
         $field =$this->get_dd_fld($fields,$fld);
         if (is_array($field)) {
            if (isset($field[$var])) $value=$field[$var];
         }
      }
      //if ($value=='undeff:' && $var == 'label') $value=$fld; 

      //dbx_debug("get_dd dd=($dd) fld=($fld) var=($var) val=($value)",$fields,$field);
    }
    return $value;
}

private function sql_to_array($data) {
   $dd=''; $flds='*'; $where=''; $order=''; $limit=888; $asc_desc='ASC';
   $xdata=array();
   $data=str_replace('sql:','',$data);
   $work=explode('|', $data);
   if (isset($work[0])) $dd   =$work[0];
   if (isset($work[1])) $xkey =$work[1];
   if (isset($work[2])) $flds =$work[2];
   if (isset($work[3])) $where=$work[3];
   if (isset($work[4])) $order=$work[4];
   if (isset($work[5])) $limit=$work[5];

   $xdata[0]='Bitte auswählen';

   if ($order && strpos($order,' DESC')) {
      $asc_desc='DESC';
      $order=str_replace(' DESC','',$order);
      
   } 

   if ($order && strpos($order,' ASC')) {
      $asc_desc='ASC';
      $order=str_replace(' ASC','',$order);
   } 


   if ($dd) {
      $xflds=$flds;
      if (!$xkey) $xkey='id';
      if (!strpos($xflds,$xkey)) $xflds.=','.$xkey;

      //dbx_debug("#SELECT DD=($dd) where=($where) FLDS=($flds) Order=($order)");


      $db=dbx_get_sys_object('dbxDB');
      $data=$db->select($dd,$where,$xflds,$order,$asc_desc,'',$limit);      // select($tab,$where='',$columns='*',$orderby='',$asc_desc='ASC',$groupby='',$max=0,$offset=0,$verify_access=1) {
      if (is_array($data)) {
         foreach ($data as $no => $record) {
            $value='';
            foreach ($record as $fld => $val) {
               if (strpos('~'.$flds,$fld)) { 
                  if ($value > '') $value.=' | ';
                  $value.=$val;
               } 
            }       
            $xdata[$record[$xkey]]=$value;
         }   
      }
          
   }
   

   //dbx_debug("SEL-WORK=",$work);


   return $xdata;
}

 
public function add_fld($name,$tpl='dd:',$data='dd:',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='',$remap='') { //#
      //dbx_debug("add-fld=($name)");
      $dd=$this->_dd;


      if ($dd) {
        if ($tpl        =='dd:') $tpl        =$this->get_dd($dd,$name,'tpl'); 
        if ($data       =='dd:') $data       =$this->get_dd($dd,$name,'data');
        if ($rules      =='dd:') $rules      =$this->get_dd($dd,$name,'rules');
        if ($label      =='dd:') $label      =$this->get_dd($dd,$name,'label');
        if ($tooltip    =='dd:') $tooltip    =$this->get_dd($dd,$name,'tooltip');
        if ($msg        =='dd:') $msg        =$this->get_dd($dd,$name,'errormsg');
        if ($placeholder=='dd:') $placeholder=$this->get_dd($dd,$name,'placeholder');
      }

      if ($label =='dd:')  $label = $name;
      if ($tpl   =='dd:')  $tpl   ='text-label';  

      if (!is_array($data)) {
         $data_first=substr($data,0,4); 
         if ($data_first != 'sql:' )  $data =dbx_url_to_array($data);
         if ($data_first == 'sql:' )  $data =$this->sql_to_array($data);
      }
      //if (is_array($data)) dbx_debug("DATA=",$data);

 

      $fld=array();
      $fld['tpl']        =$tpl;
      $fld['name']       =$name;
      $fld['label']      =$label;
      $fld['rules']      =$rules;
      $fld['msg']        =$msg;
      $fld['tooltip']    =$tooltip;
      $fld['placeholder']=$placeholder;
      $fld['options']    =$data;
      $fld['class']      =$class;
      $fld['value']      ='';
      $fld['origin']     ='';
      $fld['error']      = 0;
      $fld['changed']    = 0;
      $fld['verify']     = 0;
      $fld['remap']      =$remap;
      $this->_flds[$name]=$fld;
  }

  public function check_fld_data($submit,$fld) {
    //dbx_debug("#check_fld submit=($submit)",$fld);
    if (!$fld['verify']) {
      $value=''; $old_value='';
      $name =$fld['name'];
      if (!$submit) {
         //dbx_debug("FLD-DATA ($name)",$this->_data);
         if (isset($this->_data[$name])) $value=$this->_data[$name];
         if (isset($this->_sys[$name]))  $value=$this->_sys[$name];

         $fld['value']  = $value;
         $fld['origin'] ='data';
         $fld['changed']=0;
         $fld['error']  =0;

      }
      if ($submit) {
         if (isset($this->_data[$name])) $old_value=$this->_data[$name];
         if (isset($this->_sys[$name]))  $old_value=$this->_sys[$name];
         $value=$this->_get_post($name,default: '');
         if ($value===null) $value=''; // empty field or unchecked checkbox
         $rul  =$fld['rules'];
         $ok   =$this->oValidator->validate($value,$rul,$name);
         //dbx_debug("##FORM-validate Fld=($name) ok=($ok) rul=($rul) Val=" , $value);
         
         $error=1; if ($ok) $error=0;
         $fld['origin']='post';
         $fld['error'] =$error;
         $fld['value'] =$value;
   

         if ($fld['error']) $this->add_fld_error($name,$fld['msg']);

         if (is_array($value)) {
            $values='';
            foreach ($value as $no => $keyval) {
               if ($values > '') $values.=',';
               $values.=$keyval;
            }
            $value=$values;
         }
         $change=$this->_fld_change_state;
         if ($value != $old_value || $change=='*') {
            //dbx_debug("#FLD-CHANGE ($name) Val=($value) != Old($old_value)");
            $fld['changed']=1;
         }


         if (!$fld['error'] && $fld['changed']) {
            $this->_post[$name]=$value;
         }
      }
      $fld['verify'] =1;
    }
    return $fld;
  }

  public function check_flds_data($submit) {
     $form_validate=$this->_form_validate;
     //dbx_debug("Form-validate=($form_validate) submit=($submit)");
     
      if ($form_validate == -1) {
      foreach ($this->_flds as $no => $fld) {
         //dbx_debug("Check fld ($no)");
         $fld=$this->check_fld_data($submit,$fld);
         $this->_flds[$no]=$fld;
      }
     }
     $this->_form_validate=1;
  }




  public function get_form_msg($mode,$msg='') {
     if (!$msg) return '';
     $file=$this->_tpl_form_info; $tpl='';
     //$msg=htmlspecialchars($msg);
     if ($mode=='success') $file=$this->_tpl_form_success;
     if ($mode=='error')   $file=$this->_tpl_form_error;
     if ($mode=='info')    $file=$this->_tpl_form_info;
     if ($mode=='warning') $file=$this->_tpl_form_warning;
     if ($file) {
       $tpl=$this->get_tpl($file);
       $tpl=(str_replace('{msg}',$msg,$tpl));
     }
     $tpl = (str_replace('{class}',$mode,$tpl));
     //return $msg;
     return $tpl;
  }

  public function get_fld_msg($mode,$msg) {
     $file=''; $tpl='';
     $msg=htmlspecialchars($msg);
     if ($mode=='success') $file=$this->_tpl_fld_success;
     if ($mode=='error')   $file=$this->_tpl_fld_error;
     if ($mode=='info')    $file=$this->_tpl_fld_info;
     if ($mode=='warning') $file=$this->_tpl_fld_warning;
     if ($file) {
       $tpl=$this->get_tpl($file);
       $tpl=(str_replace('{msg}',$msg,$tpl));
     }
     return $tpl;
  }



  public function php_date_usr($value) {
     if ($value) {
      $timestamp  = strtotime($value);
      $value = date('d.m.Y', $timestamp);
      $value = substr($value, 0, 10);
     }
     return $value;
  }

  public function php_datetime_usr($value) {
   //return $value;
   if (trim($value) !== '') {
       // Wandelt den Wert in einen UNIX-Zeitstempel um
       $timestamp = strtotime($value);
       
       if ($timestamp !== false) {
           // Dynamische Formatwahl je nach vorhandenem Inhalt
           if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', trim($value))) {
               // Nur Uhrzeit (ohne Datum)
               $value = date('H:i' . (substr_count($value, ':') == 2 ? ':s' : ''), $timestamp);
           } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$|^\d{2}\.\d{2}\.\d{4}$/', trim($value))) {
               // Nur Datum (ohne Zeit)
               $value = date('d.m.Y', $timestamp);
           } else {
               // Datum und Uhrzeit (mit oder ohne Sekunden)
               $value = date('d.m.Y H:i' . (substr_count($value, ':') == 2 ? ':s' : ''), $timestamp);
           }
       }
   }
   return $value;
}



  public function php_date($value) {
   if ($value) {
      if ($value != 'today') $timestamp  = strtotime($value);
      if ($value == 'today') $timestamp  = time(); 
      $value = date('Y-m-d', $timestamp);   
   }
   return $value;
  } 



  public function add_css($css='') {
     if ($css) { $this->_css[]=$css; }
  }

  public function forward_submit() {
      $submit=$this->_form_submit; 
      if ($submit != -1) return $submit;

      $secure= '~';    $submit= 0;
      
      $modul = $this->_dbx_modul;
      $fid   = $this->_fid;
      //$no_sub= $this->get_no_submit($fid,0);
   
      if (isset($_POST[$fid])) { // && $no_sub==0) {
         $secure=md5($fid);
         $fsec  =$_POST[$fid];
         if ($fsec == $secure) { 
            //unset($_POST[$fid]); 
            $submit=1;
         }   
      }

      $this->check_flds_data($submit);

      $this->_form_submit=$submit;
      return $submit;
  }


  public function submit() {
      $submit=$this->forward_submit();
      return $submit;
  }

  public function errors() {
      $submit =$this->submit();
      $this->check_flds_data($submit);
      if ($this->_general_error > '') $this->_errors['general']=1;
      $errors = (count($this->_errors));
      //dbx_debug("Form.class errors=($errors)");
      return $errors;
  }

  public function warnings() {
      $submit =$this->submit();
      $warnings = (count($this->_warnings));
      return $warnings;
  }


  public function changed($changed=0) {
     $submit =$this->submit();
     if ($submit) {
      if ($this->_fld_changes != -1)  return $this->_fld_changes; 
      foreach ($this->_flds as $no => $fld) {
        $changed=($changed + $fld['changed']);
      }
     }
     $this->_fld_changes=$changed;  
     return $changed;
  }


  public function get_rid() {
     $empty=array();
     $rid  =dbx_get_ModulVar('rid',-1,'int');
     $dbx_view =$this->_dbx_view;
     $dbx_modul=$this->_dbx_modul;

     if ($dbx_view) {
       $viewsys=dbx_get_SessionVal($dbx_view,$empty,'view-sys',$dbx_modul);
       if (isset($viewsys['value'])) $rid=$viewsys['value']; // sync
     } else {
       $rid=dbx_get_ModulVar('rid',-1,'int');
     }

     //dbx_debug("##get_rid=($rid) View=($dbx_view) Modul=($dbx_modul)");

     $this->_rid=$rid; // need for action
     return $rid;
  }

  public function view_sync($rid) {
    $empty=array();
    $dbx_view =$this->_dbx_view;
    $dbx_modul=$this->_dbx_modul;
    if ($dbx_view) {
      $viewsys=dbx_get_SessionVal($dbx_view,$empty,'view-sys',$dbx_modul);
      $viewsys['value']=$rid;
      dbx_set_SessionVal($dbx_view,$viewsys,'view-sys',$dbx_modul);
    }
  }

  public function wait() {
     $wait=0;
     $tpl=$this->_tpl;
     if (strpos($tpl,'_wait')) $wait=1;
     return $wait;
  }

  public function set_post($key,$val) {
     $this->_form_validate=-1;
     //$this->_form_submit  =-1;
     $this->_post[$key]=$val;
     //dbx_debug("#SET-POST ($key)=($val)");
     //change state 
     


  }

  public function save_post($dd,$rid,$pv='',$reread=1) {
    $new=array(); $ok=0; $reload=0;
    if ($rid=='new') $rid=0;
    //$post=$this->_post;
    if (is_array($pv)) {
       foreach ($pv as $key => $value) {
          $this->_post[$key]=$value;
          $this->_data[$key]=$value;
       }
    }

    if ($rid) $this->_rid=$rid;
    $rid =$this->_rid;
    $post=$this->_post;

    $oDB=dbx_get_sys_object('dbxDB');

    $ok=$oDB->save($dd,$post,$rid);


    //dbx_debug("#FORM-save# ok=($ok) Tab=($dd) rid=($rid)",$post);

    if (!$ok) {
      $error=$oDB->_error;
      $query=$oDB->_query;
      // db response
      //$_validation_error=0;
      //$_validation_warning=0;
      //$_validation_error_flds=array();
      //$_validation_warning_flds=array();
      //dbx_debug("DB-Error=($error) \n Query=($query)");
    }
    if ($ok) {
      if (!$rid) {
        $rid=$oDB->_insert_id;
        $this->_rid=$rid;
        //$this->view_sync($rid);
        //dbx_debug("save new rid=($rid)");
      }
      if ($rid && $ok) {
        $new =$oDB->select1($dd,$rid);
        $data=$this->_data;

        //dbx_debug("##reread Tab=($tab) RID=($rid)");
        if (is_array($new)) {
          foreach ($new as $key => $value) {
             if (isset($data[$key])) $data[$key]=$value;
          }
          $this->_data=$data;

          //if ($this->_reload_transform && $this->_reload_record) {
             $this->_reload_run=1;
          //}


          foreach ($this->_flds as $no => $fld) {
            $key=$fld['name'];
            if (isset($data[$key]) ) {
              $this->_flds[$no]['value']=$data[$key];
              $this->_flds[$no]['origin']='reload';
              $this->_flds[$no]['verify']=1;
            }
          }
        }
      }
      //$rid=$this->view_sync($rid);
    }

    //dbx_debug("SAVE-POST ok=($ok) Tab=($tab) ($rid) reload=($this->_reload_run)",$this->_flds);

    return $ok;
  }

  public function next_i($add=1) {
      $i=$this->_next_i;
      if (!$i) {
         $i=dbx_get_next_i($add);
         $this->_next_i=$i;
      }
      $this->add_rep('i',$i);
      return $i;
  }


  public function set_fld_val($fld_name,$fld_val) {
    if (isset($this->_flds[$fld_name])) {
       $this->_flds[$fld_name]['value']  =$fld_val;
       $this->_flds[$fld_name]['origin'] ='set';
       $this->_flds[$fld_name]['verify'] =1; // important
    }
    //dbx_debug("##Filds##",$this->_flds);
  }
/**
 * Erstellt das HTML für ein Formularfeld basierend auf den übergebenen Parametern.
 * 
 * Diese Funktion erzeugt das HTML für ein Formularfeld, einschließlich der Werte, Klassen,
 * Fehlernachrichten, Optionen (bei Auswahlfeldern) und anderer Attribute. Es werden auch
 * Tooltipps und Validierungsinformationen berücksichtigt.
 *
 * @param array $fld Die Feldkonfiguration mit folgenden möglichen Schlüsseln:
 *   - 'name': Der Name des Feldes.
 *   - 'label': Das Label des Feldes.
 *   - 'value': Der Wert des Feldes.
 *   - 'class': Die CSS-Klassen des Feldes.
 *   - 'tooltip': Das Tooltip des Feldes.
 *   - 'rules': Die Validierungsregeln des Feldes.
 *   - 'options': (Optional) Die Optionen für Auswahlfelder (nur bei Auswahlfeldern relevant).
 *   - 'error': (Optional) Flag, das angibt, ob ein Fehler vorliegt.
 *   - 'msg': (Optional) Fehlermeldung oder Erfolgsmeldung.
 *
 * @param int $i Der Index des Feldes (optional, Standard: 0).
 * 
 * @return string Das generierte HTML für das Feld.
 */
public function create_fld($fld, $i = 0) {
   if (!$i) $i = $this->_next_i;

   // Konfiguration abrufen
   $editor = dbx_get_SysVar('dbx_editor', 0, 'int');
   $edit = dbx_get_SysVar('dbx_edit', 0, 'int');
   $design = dbx_get_SysVar('dbx_design', 'default');
   $lng = dbx_get_SysVar('dbx_lng', 'de');
   
   // Formularstatus und Optionen
   $submit = $this->submit();
   $reload = $this->_reload_run;
   $suffix = $reload ? $this->_reload_suffix : '';

   // Optionen initialisieren
   $options = '';
   if (!$fld['verify']) $fld = $this->check_fld_data($submit, $fld);
   if (isset($fld['options'])) { 
       $options = $fld['options'];
       unset($fld['options']);
   }

   // ID, Message-Templates und Vorlagen-Initialisierung
   $id = $fld['name'];
   $fld_msg = '';
   $mid = '{msg:' . $id . '}';
   $oid = '{' . $id . '_options}';
   $fid = '{obj:' . $id . '}';
   $tpl = $this->get_tpl($fld['tpl'], $options);
   
   // Standardwerte in Vorlagen ersetzen
   $tpl = str_replace('{label}', $fld['label'], $tpl);
   $tpl = str_replace('{name}', $fld['name'], $tpl);
   $tpl = str_replace('{placeholder}', $fld['placeholder'], $tpl);

   // Wert des Feldes sicherstellen
   $fld_value = $fld['value'];
   $classes = $fld['class'];
   $name = $fld['name'];

   if ($fld_value === null) $fld_value = '';
   // htmlspecialchars für Strings, aber Array-Werte berücksichtigen
   if (!is_array($fld_value)) {
       $fld_value = htmlspecialchars($fld_value, ENT_QUOTES, 'UTF-8');
   }

   // Tooltip verarbeiten
   $tooltip = htmlspecialchars($fld['tooltip']);
   $tt = 'data-tooltip="' . $tooltip . '"';
   $tpl = str_replace('{tooltip}', $tt, $tpl);

   // Required-Flag basierend auf den Regeln setzen
   $required = '';
   if (strpos($fld['rules'], 'min=')) $required = 'required';
   if (strpos($fld['rules'], 'min=0')) $required = '';
   $tpl = str_replace('{required}', $required, $tpl);

   // Checkbox-Status setzen
   if (strpos($tpl, '{checked}')) {
       $checked = '';
       if ($fld['value']) $checked = 'checked';
       $tpl = str_replace('{checked}', $checked, $tpl);
   }

   // Optionen für Auswahlfelder (falls vorhanden)
   if (!strpos($tpl, '_options}')) {
       $tpl = $this->oTPL->replaces($tpl, $options);
       $options = '';
   }

   if (is_array($options)) {
       // Optionen erstellen und überprüfen, ob sie ausgewählt sind
       $xoptions = '';
       $oid = $id . '_options';
       $options_vals = $fld_value;

       if (!is_array($options_vals)) {
           if ($options_vals == null) $options_vals = '';
           $options_vals = explode(",", $options_vals);
       }

       if (is_array($options_vals)) {
           foreach ($options as $key => $description) {
               $selected = '';
               foreach ($options_vals as $keyval) {
                   if ($keyval == $key) $selected = 'selected';
               }
               $xoptions .= "<option class=\"{class}\" value=\"$key\" $selected > $description </option>\n";
               $xoptions = str_replace('{class}', $classes, $xoptions);
           }
       }
       $tpl = str_replace('{' . $oid . '}', $xoptions, $tpl);
   }

   // Fehlermeldungen verarbeiten
   if (!$fld['error']) {
       if (isset($this->_errors[$id])) {
           $msg = $this->_errors[$id];
           if ($msg) $fld['msg'] = $msg;
           $fld['error'] = 1;
       }
   }

   // Fehler- oder Erfolgsmeldung setzen
   if ($fld['error']) {
       $msg = $this->get_fld_msg('error', $fld['msg']);
   } else {
       if (!$submit) $msg = $this->get_fld_msg('info', $fld['msg']);
       if ($submit) $msg = $this->get_fld_msg('success', $fld['msg']);
   }
   $tpl = str_replace($mid, $msg, $tpl);

   // Feld-Klassen (Fehler, Info, Erfolg)
   if ($fld['error']) {
       $classes .= ' fld-error';
   } else {
       if (!$submit) $classes .= ' fld-info';
       if ($submit) $classes .= ' fld-success';
   }

   // Klasse in Vorlagen einsetzen
   $tpl = str_replace('{class}', $classes, $tpl);

   // Alle anderen Platzhalter ersetzen
   $tpl = $this->oTPL->replaces($tpl, $this->_replaces);
   $tpl = str_replace('{style}', '', $tpl);

   // Feldwert zuletzt (insbesondere bei Bildfeldern)
   if (!is_array($fld_value)) {
       if ($fld_value != '0' && $fld_value == null)  $fld_value = '';
       $tpl = str_replace('{src}', $fld_value, $tpl); // Img
       $tpl = str_replace('{value}', $fld_value, $tpl);
   }

   // Vorlagen abschließend modifizieren
   $tpl = dbx_add_norep($tpl, $i);

   return $tpl;
}

/**
 * Fügt Formularfelder in den angegebenen Inhalt ein.
 *
 * Diese Methode durchsucht einen HTML-Inhalt nach Platzhaltern für Formularfelder 
 * und ersetzt diese durch dynamisch generiertes HTML für die entsprechenden Felder. 
 * Felder, die keinen Platzhalter im Inhalt haben, werden an das Formular angehängt. 
 * Die Anfügung erfolgt entweder an der Stelle des Platzhalters `[dbx:form]` 
 * oder vor dem schließenden `</form>`-Tag.
 *
 * @param string $content Der HTML-Inhalt mit Platzhaltern für Formularfelder.
 *                        Platzhalter werden in der Form `{obj:<feld_id>}` erwartet.
 * @param int $i Optional. Index für die Feldgenerierung. Standard ist `0`. 
 *               Wenn `$i` gleich `0` ist und der Editor-Modus deaktiviert ist, 
 *               wird `$this->_next_i` verwendet.
 * @return string Der bearbeitete HTML-Inhalt mit den eingefügten Formularfeldern.
 *
 * @example
 * ```php
 * $content = '<form>{obj:name}{obj:email}</form>';
 * echo $obj->merge_fld_data($content);
 * ```
 */


 /**
 * Ersetzt Platzhalter für Formularfelder im Inhalt und fügt nicht genutzte Felder hinzu.
 *
 * Diese Methode verarbeitet einen HTML-Inhalt, ersetzt definierte Platzhalter wie `{obj:<feld_id>}` 
 * durch generierte HTML-Formularfelder und fügt alle verbleibenden Felder entweder an 
 * der Stelle des Platzhalters `[dbx:form]` oder vor dem schließenden `</form>`-Tag hinzu.
 *
 * @param string $content Der HTML-Inhalt mit Platzhaltern für Formularfelder.
 *                        Platzhalter haben das Format `{obj:<feld_id>}`.
 * @param int $i Optional. Der Index für die Generierung von Feldern. Standard ist `0`.
 *               Wenn `$i` gleich `0` und der Editor-Modus deaktiviert ist, 
 *               wird `$this->_next_i` verwendet.
 * @return string Der bearbeitete HTML-Inhalt mit eingefügten Formularfeldern.
 *
 * @example
 * ```php
 * $obj = new YourClass();
 * $obj->_flds = [
 *     'username' => ['remap' => null, 'type' => 'text', 'label' => 'Benutzername'],
 *     'password' => ['remap' => null, 'type' => 'password', 'label' => 'Passwort']
 * ];
 * $content = '<form>{obj:username}</form>';
 * echo $obj->merge_fld_data($content);
 * // Ausgabe: <form><input type="text" name="username" placeholder="Benutzername"></form>
 * ```
 */
public function merge_fld_data($content, $i = 0)
{
    $form = ''; // String für nicht eingebundene Formularfelder
    $editor = dbx_get_SysVar('dbx_editor', 0, 'int'); // Prüfen, ob Editor-Modus aktiv ist
    if (!$i && !$editor) {
        $i = $this->_next_i; // Standardindex verwenden, falls keiner angegeben ist
    }

    // Verarbeitung der definierten Felder
    foreach ($this->_flds as $id => $fld) {
        // Falls ein Feld remapped wird, neue ID verwenden
        if ($fld['remap']) {
            $id = $fld['remap'];
        }

        // Platzhalter-String für das aktuelle Feld
        $fid = '{obj:' . $id . '}';
        if (strpos($content, $fid) !== false) {
            // Ersetze Platzhalter durch generiertes Feld-HTML
            $fld_content = $this->create_fld($fld, $i);
            $content = str_replace($fid, $fld_content, $content);
            unset($this->_flds[$id]); // Feld aus der Liste entfernen
        } else {
            // Generiere Feld-HTML und füge es zum Formular-String hinzu
            $fld_content = $this->create_fld($fld, $i);
            $form .= $fld_content . "\n";
        }
    }

    // Platzhalter [dbx:form] oder </form> durch restliche Felder ersetzen
    if (strpos($content, '[dbx:form]') !== false) {
        $content = str_replace('[dbx:form]', $form, $content);
    } else {
        $content = str_replace('</form>', $form . '</form>', $content);
    }

    return $content; // Bearbeiteter Inhalt wird zurückgegeben
}


/**
 * Überprüft die Anzahl der Versuche und sperrt gegebenenfalls weitere Eingaben.
 *
 * Diese Methode verfolgt, wie oft ein Benutzer versucht hat, eine Aktion auszuführen,
 * und setzt eine Sperre, wenn die maximale Anzahl der Versuche überschritten wird. 
 * Bei Sperrung wird eine Wartezeit aktiviert, bevor neue Versuche erlaubt sind.
 *
 * @param bool $submit Gibt an, ob ein Formular abgeschickt wurde.
 * @param bool $errors Gibt an, ob bei der Verarbeitung Fehler aufgetreten sind.
 * @param bool $allways Optional. Gibt an, ob die Überprüfung immer ausgeführt werden soll. Standard ist `1`.
 * @return string Eine Nachricht oder leeren Inhalt, je nach Status der Sperre.
 *
 * @example
 * ```php
 * $obj = new YourClass();
 * $obj->_try_max = 3; // Maximale Anzahl der Versuche
 * $obj->_try_reset = 60; // Reset-Zeit in Sekunden
 * $obj->_try_msg = 'Zu viele Versuche. Bitte warten Sie {sec} Sekunden.';
 * $obj->_tpl_max_try = 'try_template'; // Template für Sperrnachricht
 *
 * $submit = true; // Formular wurde abgeschickt
 * $errors = true; // Es sind Fehler aufgetreten
 *
 * echo $obj->check_try_count($submit, $errors);
 * ```
 */
public function check_try_count($submit, $errors, $allways = 1)
{
    $content = '';
    $clear = 0;
    $reset = $this->_try_reset;
    $max = $this->_try_max;
    $msg = $this->_try_msg;
    $now = dbx_timestamp();
    $self = dbx_get_self_url();
    $sys = isset($this->_sys['_try_sys']) ? $this->_sys['_try_sys'] : [];

    // Initialisierung der Zähler, falls nicht vorhanden
    $sys['dbx_try_count'] = $sys['dbx_try_count'] ?? 0;
    $sys['dbx_run_count'] = $sys['dbx_run_count'] ?? 0;
    $sys['dbx_try_lock'] = $sys['dbx_try_lock'] ?? 0;

    $sys['dbx_run_count']++;

    if ($submit) {
        if ($errors) {
            $sys['dbx_try_first'] = $sys['dbx_try_first'] ?? $now;
            $sys['dbx_try_last'] = $now;
            $sys['dbx_try_count']++;

            if ($sys['dbx_try_count'] >= $max) {
                if (!isset($sys['dbx_try_stop'])) {
                    $sys['dbx_try_lock']++;
                    for ($i = 1; $i < $sys['dbx_try_lock']; $i++) {
                        $reset = (int)($reset * 2);
                    }
                    $sys['dbx_try_stop'] = $now;
                    $sys['dbx_try_run'] = dbx_timestamp($reset);
                }
            }

            if (isset($sys['dbx_try_run']) && $now > $sys['dbx_try_run']) {
                $clear = 1;
            }
        } else {
            $clear = 1; // Keine Fehler, zurücksetzen
        }
    }

    if (($submit || $allways) && !$clear) {
        if ($sys['dbx_try_count'] >= $max && $sys['dbx_try_run'] > 0) {
            $diff = dbx_time_diff($now, $sys['dbx_try_run']);
            if ($diff > 0) {
                $data = [
                    'msg' => $msg,
                    'sec' => (int)$diff,
                    'self' => $self,
                    'try_count' => $sys['dbx_try_count'],
                    'run_count' => $sys['dbx_run_count'],
                ];
                $content = $this->get_tpl($this->_tpl_max_try, $data);
            } else {
                $clear = 1;
            }
        }
    }

    if ($clear) {
        unset($sys['dbx_try_stop'], $sys['dbx_try_run']);
        $sys['dbx_try_count'] = 0;
    }

    $this->_sys['_try_sys'] = $sys;
    return $content;
}



/**
 * Führt den Hauptprozess eines Moduls aus, verarbeitet Vorlagen und Formularaktionen.
 *
 * Diese Methode führt alle erforderlichen Schritte durch, um ein Modul auszuführen, 
 * einschließlich der Verarbeitung von Vorlagen, Validierung von Benutzereingaben, 
 * und der Erzeugung von JavaScript-Code.
 *
 * @return string Der generierte HTML-Inhalt des Moduls mit allen eingebetteten Daten und JavaScript.
 *
 * @example
 * ```php
 * $obj = new dbxForm;
 * $obj->_tpl = 'example_template';
 * $obj->_msg_info = 'Bitte füllen Sie das Formular aus.';
 * $obj->_msg_error = 'Fehler bei der Eingabe.';
 * $obj->_msg_success = 'Formular erfolgreich übermittelt.';
 * $content = $obj->run();  // run ruft forward_run() auf
 * echo $content;
 * ```
 */
public function forward_run()
{
    $content = '';
    $msg = '';
    $vars = '';
    $msg_class = '';
    $norep = '';

    $mid = $this->_dbx_modul_id;
    $modul = $this->_dbx_modul;
    $tpl = $this->_tpl;
    $msg = $this->_msg_info;
    $editor = dbx_get_SysVar('dbx_editor', 0, 'int');

    $allways = 1;
    $submit = $this->submit();
    $errors = $this->errors();

    // Überprüfung auf maximale Versuchsanzahl
    $content = $this->check_try_count($submit, $errors, $allways);

    if (!$content) {
        $i = $this->next_i();
        $this->_next_i = $i;

        $replaces = $this->_replaces;
        $action = $this->_action;
        $rid = $this->_rid;
        $fid = $this->_fid;

        // Verarbeiten von Vorlagen und Platzhaltern
        $content = $this->get_tpl('modul|' . $tpl, $replaces, 'htm', $i);
        $content = $this->merge_tpl_data($content, $i);
        $content = $this->merge_fld_data($content, $i);
        $content = $this->merge_obj($content, $i);

        // Verarbeitung der Formularstatus-Meldungen
        if ($submit) {
            if ($this->errors()) {
                $form_msg = $this->get_form_msg('error', $this->_msg_error);
            }
            if ($this->warnings()) {
                $form_msg = $this->get_form_msg('warning', $this->_msg_warning);
            }
            if (!$this->errors() && !$this->warnings()) {
                $changed = $this->changed();
                $form_msg = $this->get_form_msg('success', $this->_msg_success);
            }
        } else {
            $form_msg = $this->get_form_msg('info', $this->_msg_info);
        }

        $content = str_replace('{obj:form_msg}', $form_msg, $content);

        // Einfügen von JavaScript
        $norep_ids = '';
        $js = $this->_js;
        if (is_array($js)) {
            foreach ($js as $javascript) {
                $javascript = str_replace('{i}', $this->_next_i, $javascript);
                $norep = '<script>' . $javascript . '</script> ';
                $norep_ids .= dbx_add_norep($norep, $i);
            }
        }
        $content = str_replace('[dbx:js]', $norep_ids, $content);
    }

    // Ersetzen des Index-Platzhalters
    if (!$editor && $content && $i) {
        $content = str_replace('{i}', $i, $content);
    }

    // Speichern der Systemdaten
    $this->store_sysdata();

    return $content;
}



  // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
  public function init($fid,$tpl='') { // called not by __construct()
    $this->forward_init($fid,$tpl);
  }
  public function run() {
    return $this->forward_run();
  }

} // - - - - - - - - - - class
