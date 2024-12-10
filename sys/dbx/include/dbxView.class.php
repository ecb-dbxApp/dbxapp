<?php


class dbxView extends \dbxObj {


  Public $oTPL;
  Public $oValidator;

   public function __construct() {
     $this->oValidator=dbx_get_sys_object('dbxValidator');
     $this->oTPL      =dbx_get_sys_object('dbxTPL');
   }


   public function dbxView_init($id,$tpl='') {
      if (!$tpl) $tpl=$id;
      $this->set_property('prozess_id',$id);
      $this->set_property('tpl'       ,$tpl);
   }

   public function dbxView_run() {
       $i      = dbx_get_next_i();
       $target = 'dbx_target_'.$i;

       $id     = $this->get_property('prozess_id');
       $tpl    = $this->get_property('tpl');
       $sync   = $this->get_property('sync'  ,'rid');
       $modul  = $this->get_property('modul' ,'modul');
       $mode   = $this->get_property('mode'  ,'sync');
       $target = $this->get_property('target',$target);
       $val    = dbx_get_PostGetVar($sync,0,'int');
       if ($modul == 'modul' || !$modul) $modul=dbx_get_sysVar('dbx_modul','dbx');

       $dbx_action=dbx_get_sysVar('dbx_action','run');

       $action = '?dbx_modul='.$modul.'&dbx_action='.$dbx_action.'&'.$sync.'='.$val;
       $action = $this->get_property('action',$action);

       $vievsys['view']  =$id;
       $vievsys['value'] =$val;
       $vievsys['mode']  =$mode;
       $vievsys['modul'] =$modul;
       $vievsys['target']=$target;
       $vievsys['action']=$action;

       dbx_set_SessionVal($id,$vievsys,'view-sys',$modul);


       $reps['dbx_view']  = $id;
       $reps['dbx_modul'] = $modul;
       $reps['dbx_tpl']   = $tpl;
       $reps['rid']       = $val;
       $reps['i']         = $i;

       $content=$this->oTPL->get_tpl($modul,$tpl,$reps,'htm',$i);
       return $content;
   }

   // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
 
} // class

?>