<?php
namespace dbx\myLKW;

dbx()->get_system_obj('dbxReport', 'use');
use function dbx\myLKW\dbx_get_datum;
require_once __DIR__ . '/dbxReport_Dispo.class.php';


class myLKW_report {

private function get_report_dispo($page=0,$col=0) {

    $dd      = 'lkw';
    $form_id = 'report-dispo-prn-'.$page.'-'.$col;

    $o_report = new dbxReport_Dispo;

    // WICHTIG: Keine interne Pagination
    $o_report->init($form_id, 'report-dispo-prn', 999999, 999999, 0);

    $o_report->set_data_definition($dd);
  

    $o_db = dbx()->get_system_obj('dbxDB');

    $flds = [
        'id'               => '',
        'TRACTOR'          => 'TRACTOR',
        'TIPO'             => 'TIPO',
        'd2_carga_region'  => 'Reg.',
        'd2_carga_lugar'   => 'Lugar',
        'd2_observaciones' => 'Obser.',
        'observacion'      => 'observación',
    ];
    

    
    // -----------------------------------------
    // NEU: TIPO BASIERTE SEITENLOGIK
    // -----------------------------------------

    $per_column = 54;

    // Seite 1 definierte TIPOs
    $tipos_page1 = [
        'PM',
        'TL-PM',
        'DK-PM',
        'TAL',
        'BAN',
        'LT-BAS',
        'AST'
    ];

    $rwhere = 'TRACTOR > " "';

    // -----------------------------------------
    // WHERE nach Seite
    // -----------------------------------------

    if ($page == 1) {

        $tipo_list = "'" . implode("','", $tipos_page1) . "'";
        $rwhere  .= " AND TIPO IN ($tipo_list)";

    } else {

        $tipo_list = "'" . implode("','", $tipos_page1) . "'";
        $rwhere  .= " AND (TIPO NOT IN ($tipo_list) OR TIPO IS NULL OR TIPO='')";

    }

    // -----------------------------------------
    // OFFSET NUR innerhalb gefilterter Daten
    // -----------------------------------------

    $rrows = $per_column;
    $rpos  = ($col - 1) * $per_column;

    // -----------------------------------------
    // SORT
    // -----------------------------------------

    $rsort = 'TIPO DESC, d2_carga_region ASC, TRACTOR ASC';
    $rdesc  = '';
    $rgroup = '';

    $count = $o_db->count($dd,$rwhere);

    $rdata = $o_db->select(
        $dd,
        $rwhere,
        $flds,
        $rsort,
        $rdesc,
        $rgroup,
        $rrows,
        $rpos
    );

    dbx()->debug("LKW Print page=($page) col=($col) rpos=($rpos) rows=($rrows)");

    // -----------------------------------------
    // Report Setup – DRUCKMODUS
    // -----------------------------------------

    $o_report->set_report_result($rdata, $rpos, $count);
    $o_report->set_pagination(false);
    $o_report->set_mode('table');
    $o_report->set_report_fields($flds);

    // WICHTIG: Alles deaktivieren
    $o_report->_create_sel_flds    = 0;
    $o_report->set_table_actions(array());
    $o_report->_data_table         = 0;

    $o_report->set_style_header('TIPO','width:42px');
    
    $o_report->set_style_header('TRACTOR','width:74px');
    $o_report->set_style_header('d2_carga_region','width:30px');
    
    $o_report->set_style_header('d2_carga_lugar','width:80px');
    
    $o_report->set_style_header('d2_observaciones','width:40px');
    
    //$oReport->set_style_header('observacion','width:100%');
   


    $content= $o_report->run(0,$flds);
    return $content;
}
     

  /* ========================================================= */

    /** Haupt-Dispatcher */
    public function run() {
        $o_tpl = dbx()->get_system_obj('dbxTPL');
        $page = dbx()->get_modul_var('page',0,'int');
        $col  = dbx()->get_modul_var('col' ,0,'int');
    

        $data['date'] = dbx_get_datum('day dd month yyyy', 1);
        dbx()->debug("myLKW_report page=($page) col=($col)");
        if (!$page && !$col) { 
            $page=dbx()->get_modul_var('dbx_page',0,'parameter');
            if ($page) dbx()->set_system_var('dbx_page',$page);  // window support 
            $data = array_merge($data, array(
                'frame_id'            => 'lkw_report',
                'frame_panel_class'   => 'dbxReport noPrint',
                'frame_panel_attrs'   => '',
                'frame_subbar'        => '',
                'frame_form_open'     => '',
                'frame_form_close'    => '',
                'frame_body_class'    => '',
                'frame_body_head'     => '',
                'frame_body_tail'     => '',
                'bar_class'           => 'dbx-bar--module noPrint',
                'bar_title_class'     => 'dbx-bar-title',
                'bar_actions_class'   => 'dbx-bar-actions',
                'bar_title'           => 'LKW Report',
                'bar_icon'            => 'bi-printer',
                'bar_subtitle'        => '',
                'bar_title_pre'       => '',
                'bar_title_heading_attrs' => '',
                'bar_middle'          => '',
                'bar_extra'           => '',
                'bar_actions'         => $o_tpl->get_tpl('myLKW|report-lkw-print-action'),
            ));
            $content=$o_tpl->get_tpl('myLKW|report-lkw-cols',$data);

        }    
        if ($page && $col) {
            $content=$this->get_report_dispo($page,$col);
            // hier kommt dann der Report Aufruf hin der den content einer seiter einer spalte zurück gibt

        }
        return $content;
    }

    /* ========================================================= */

}
