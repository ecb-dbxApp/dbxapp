<?php


class dbxView extends \dbxObj {


  Public $oTPL;
  Public $oValidator;

   public function __construct() {
     $this->oValidator=dbx()->get_system_obj('dbxValidator');
     $this->oTPL      =dbx()->get_system_obj('dbxTPL');
   }


   public function dbxView_init($id,$tpl='') {
      if (!$tpl) $tpl=$id;
      $this->set_property('prozess_id',$id);
      $this->set_property('tpl'       ,$tpl);
   }

   public function dbxView_run() {
       $i      = dbx()->next_id();
       $target = 'dbx_target_'.$i;

       $id     = $this->get_property('prozess_id');
       $tpl    = $this->get_property('tpl');
       $sync   = $this->get_property('sync'  ,'rid');
       $modul  = $this->get_property('modul' ,'modul');
       $mode   = $this->get_property('mode'  ,'sync');
       $target = $this->get_property('target',$target);
       $val    = dbx()->get_request_var($sync,0,'int');
       if ($modul == 'modul' || !$modul) $modul=dbx()->get_system_var('dbx_modul','dbx');

       $dbx_run1=dbx()->get_system_var('dbx_run1','run');

       $action = '?dbx_modul='.$modul.'&dbx_run1='.$dbx_run1.'&'.$sync.'='.$val;
       $action = $this->get_property('action',$action);

       $vievsys['view']  =$id;
       $vievsys['value'] =$val;
       $vievsys['mode']  =$mode;
       $vievsys['modul'] =$modul;
       $vievsys['target']=$target;
       $vievsys['action']=$action;

       dbx()->set_session_var($id,$vievsys,'view-sys',$modul);


       $reps['dbx_view']  = $id;
       $reps['dbx_modul'] = $modul;
       $reps['dbx_tpl']   = $tpl;
       $reps['rid']       = $val;
       $reps['i']         = $i;

       $content=$this->oTPL->get_tpl($modul . '|' . $tpl, $reps, 'htm', $i);
       return $content;
   }

   // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 
} // class

?>
