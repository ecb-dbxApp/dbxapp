<?php
namespace dbx\dbxContent_admin;

dbx()->use_system_class('dbxView');

class dbxContent_view extends \dbxView {

  private function viewFrameBarReps($tpl, $rid, $i) {
    $isSaveView = ($tpl === 'save-view-content');

    return array(
      'frame_id'            => 'dbx_target_' . $i,
      'frame_panel_class'   => 'dbxView_wrapper',
      'frame_panel_attrs'   => '',
      'frame_subbar'        => '',
      'frame_form_open'     => '',
      'frame_form_close'    => '',
      'frame_body_class'    => '',
      'frame_body_head'     => '',
      'frame_body_tail'     => '',
      'bar_class'           => 'dbx-module-bar',
      'bar_title_class'     => 'dbx-module-bar-titleblock',
      'bar_actions_class'   => 'dbx-module-bar-actions',
      'bar_title'           => $isSaveView ? ('Content View (' . (int)$rid . ')') : ('Content ID=(' . (int)$rid . ')'),
      'bar_icon'            => $isSaveView ? 'bi-eye' : 'bi-file-earmark-text',
      'bar_subtitle'        => '',
      'bar_title_pre'       => '',
      'bar_title_heading_attrs' => '',
      'bar_middle'          => '',
      'bar_extra'           => '',
      'bar_actions'         => '',
    );
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

    $reps = array(
      'dbx_view'  => $id,
      'dbx_modul' => $modul,
      'dbx_tpl'   => $tpl,
      'rid'       => $val,
      'i'         => $i,
    );
    $reps = array_merge($reps, $this->viewFrameBarReps($tpl, $val, $i));

    $content=$this->oTPL->get_tpl($modul . '|' . $tpl, $reps, 'htm', $i);
    return $content;
  }

  public function run($rid=0) {
    $oTPL=dbx()->get_system_obj('dbxTPL');
    $rid=dbx()->get_modul_var("rid",0,'int');
    $modul="dbxContent_admin";
    $tpl  ='view-content';
    $reps ="i=1&rid=$rid";

    $this->dbxView_init('view-content');
    $this->set_property('sync','rid');
    $this->set_property('rid',$rid);
    $content=$this->dbxView_run();

    return $content;

  } // run()


} // class

?>
