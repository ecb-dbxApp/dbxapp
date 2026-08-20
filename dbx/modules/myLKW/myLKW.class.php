<?php
namespace dbx\myLKW;
use DateTime;
use Throwable;


function dbx_get_datum(
    string $format = 'day,dd.mm.yyyy',
    int $offset = 0,
    ?DateTime $base = null
): string {

    // -----------------------------------------
    // BASISDATUM
    // -----------------------------------------

    if (!$base) {
        $base = new DateTime('today');
    } else {
        $base = clone $base;
    }

    // -----------------------------------------
    // WORKDAY CHECK (CFG gesteuert)
    // -----------------------------------------

    $is_workday = function(DateTime $d): bool {

        $w = (int)$d->format('N'); // 1=Mo ... 7=So

        $max_workday = (int) dbx()->get_cfg('myLKW','workdays');
        if (!$max_workday) {
            $max_workday = 5;
        }

        if ($w > $max_workday) {
            return false;
        }

        return true;
    };

    // -----------------------------------------
    // NEXT / PREV WORKDAY
    // -----------------------------------------

    $next_workday = function(DateTime $d) use ($is_workday): DateTime {
        $d = clone $d;
        do {
            $d->modify('+1 day');
        } while (!$is_workday($d));
        return $d;
    };

    $prev_workday = function(DateTime $d) use ($is_workday): DateTime {
        $d = clone $d;
        do {
            $d->modify('-1 day');
        } while (!$is_workday($d));
        return $d;
    };

    // -----------------------------------------
    // OFFSET (d0/d1/d2 ...)
    // -----------------------------------------

    if ($offset > 0) {
        for ($i = 0; $i < $offset; $i++) {
            $base = $next_workday($base);
        }
    }

    if ($offset < 0) {
        for ($i = 0; $i < abs($offset); $i++) {
            $base = $prev_workday($base);
        }
    }

    // -----------------------------------------
    // SPANISCH
    // -----------------------------------------

    $dias_full  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    $dias_short = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

    $meses_full = [
        1=>'enero','febrero','marzo','abril','mayo','junio',
        'julio','agosto','septiembre','octubre','noviembre','diciembre'
    ];

    // -----------------------------------------
    // RANGE (~~ FIX)
    // -----------------------------------------

    if (strpos($format, '~~') !== false) {

        $fmt = str_replace('~~','',$format);

        $format_date = function(DateTime $d) use ($fmt, $dias_full, $dias_short, $meses_full): string {

            $out = $fmt;

            $out = str_replace('day',   $dias_full[(int)$d->format('w')], $out);
            $out = str_replace('dy',    $dias_short[(int)$d->format('w')], $out);

            $out = str_replace('dd',    $d->format('d'), $out);
            $out = str_replace('mm',    $d->format('m'), $out);
            $out = str_replace('yyyy',  $d->format('Y'), $out);

            $out = str_replace('month', $meses_full[(int)$d->format('n')], $out);

            return trim($out);
        };

        $start = clone $base;
        $end   = $next_workday($start);

        return $format_date($start) . ' ~~ ' . $format_date($end);
    }

    // -----------------------------------------
    // NORMAL FORMAT
    // -----------------------------------------

    $format_date = function(DateTime $d) use ($format, $dias_full, $dias_short, $meses_full): string {

        $out = $format;

        $out = str_replace('day',   $dias_full[(int)$d->format('w')], $out);
        $out = str_replace('dy',    $dias_short[(int)$d->format('w')], $out);

        $out = str_replace('dd',    $d->format('d'), $out);
        $out = str_replace('mm',    $d->format('m'), $out);
        $out = str_replace('yyyy',  $d->format('Y'), $out);

        $out = str_replace('month', $meses_full[(int)$d->format('n')], $out);

        return $out;
    };

    return $format_date($base);
}

 
class myLKW {
private function is_dayshift_enabled(): bool {
    $configured = dbx()->get_cfg('myLKW','dayshift_enabled');
    return in_array(strtolower((string)$configured), ['1', 'true', 'on'], true);
}

private function run_daily_csv_import(): array {

    $configured = dbx()->get_cfg('myLKW','daily_csv_import');
    $enabled = !in_array((string)$configured, ['0', 'false', 'off'], true);
    $importer = dbx()->get_include_obj('myLKW_import');

    dbx()->get_include_obj('myLKWDailyImportService', 'myLKW', 'load');
    $state_file = dbx()->get_file_dir() . 'sys/state/myLKW-daily-import.json';
    $service = new myLKWDailyImportService($importer, $state_file);

    return $service->run_if_due($enabled);
}

private function shift_plus() {

    $dd  = 'lkw';
    $o_db = dbx()->get_system_obj('dbxDB');

    $table = $o_db->get_dd_table($dd);
    if (!$table) return 0;

    $uid = dbx()->user();
    $now = date('Y-m-d H:i:s');

    try {

        if (!$o_db->begin($dd)) return 0;

        $sql = "UPDATE $table SET

        /* ===== d5 ← d4 ===== */
        d5_origen_region = d4_origen_region,
        d5_origen_lugar  = d4_origen_lugar,
        d5_carga_region  = d4_carga_region,
        d5_carga_lugar   = d4_carga_lugar,
        d5_observaciones = d4_observaciones,

        /* ===== d4 ← d3 ===== */
        d4_origen_region = d3_origen_region,
        d4_origen_lugar  = d3_origen_lugar,
        d4_carga_region  = d3_carga_region,
        d4_carga_lugar   = d3_carga_lugar,
        d4_observaciones = d3_observaciones,

        /* ===== d3 ← d2 ===== */
        d3_origen_region = d2_origen_region,
        d3_origen_lugar  = d2_origen_lugar,
        d3_carga_region  = d2_carga_region,
        d3_carga_lugar   = d2_carga_lugar,
        d3_observaciones = d2_observaciones,

        /* ===== d2 ← d1 ===== */
        d2_origen_region = d1_origen_region,
        d2_origen_lugar  = d1_origen_lugar,
        d2_carga_region  = d1_carga_region,
        d2_carga_lugar   = d1_carga_lugar,
        d2_observaciones = d1_observaciones,

        /* ===== d1 ← d0 ===== */
        d1_origen_region = d0_origen_region,
        d1_origen_lugar  = d0_origen_lugar,
        d1_carga_region  = d0_carga_region,
        d1_carga_lugar   = d0_carga_lugar,
        d1_observaciones = d0_observaciones,

        /* ===== d0 leer ===== */
        d0_origen_region = '',
        d0_origen_lugar  = '',
        d0_carga_region  = '',
        d0_carga_lugar   = '',
        d0_observaciones = '',

        update_date      = '$now',
        update_uid       = '$uid'";

        if (!$o_db->raw_query($o_db->get_dd_server($dd),$sql)) {

            $o_db->rollback($dd);
            return 0;
        }

        if (!$o_db->commit($dd)) {

            $o_db->rollback($dd);
            return 0;
        }

        return 1;

    }
    catch (Throwable $e) {

        $o_db->rollback($dd);
        return 0;
    }
}

private function sync_grid() {
  
    $o_db = dbx()->get_system_obj('dbxDB');
    $dd  = 'lkw';

    $last_update = dbx()->get_modul_var('last_update','','datetime');

    //dbx_debug("Sync=$last_update");

    if (!$last_update) {
        $last_update = '1970-01-01 00:00:00';
    }

    /* ---------------------------------------------
    SERVER TIME
    --------------------------------------------- */

    $server_time = (new DateTime())->format('Y-m-d H:i:s.v');

    /* ---------------------------------------------
    WHERE
    --------------------------------------------- */

    $where = "update_date > '$last_update'";
    dbx()->debug("where=($where)");

    /* ---------------------------------------------
    SELECT
    --------------------------------------------- */

    $rows = $o_db->select(
        $dd,
        $where,
        '*',
        'update_date',
        'ASC'
    );

    if(!is_array($rows)){
        $rows = [];
    }

    /* ---------------------------------------------
    COUNT
    --------------------------------------------- */

    $count = count($rows);

    dbx()->debug("Update=($count)",$rows);

    /* ---------------------------------------------
    JSON OUTPUT
    --------------------------------------------- */

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok'          => 1,
        'count'       => $count,
        'rows'        => $rows,
        'server_time' => $server_time
    ]);

    exit;
}


    public function run() {
 
        $modul =dbx()->get_system_var('dbx_activ_modul');
        $action=dbx()->get_modul_var('dbx_run1');
        dbx()->set_system_var('dbx_page','default');

        if ($action !== 'import_lkw') {
              $daily_import = $this->run_daily_csv_import();
              dbx()->debug('myLKW daily CSV import', $daily_import);
        }
     
        switch ($action) {

           case 'sync_grid':
                $this->sync_grid();
            break; 

            case 'shift_plus':
                $this->shift_plus();
            break; 

        
            case 'summary':
                $obj=dbx()->get_include_obj('mySummary');
                $content=$obj->run();     
            break; 

            case 'list_lkw':
                $obj=dbx()->get_include_obj('myLKW_list');
                $content=$obj->run();    
            break; 

           case 'list_dispo':
                $obj=dbx()->get_include_obj('myLKW_dispo');
                $content=$obj->run();    
            break; 

            case 'dayshift':
               if ($this->is_dayshift_enabled()) {
                  $obj=dbx()->get_include_obj('myLKW_dayshift');
                  $content=$obj->run();
               } else {
                  $o_tpl=dbx()->get_system_obj('dbxTPL');
                  $content=$o_tpl->get_tpl('dbx|alert-info', array(
                     'msg' => 'Die Tagesverschiebung ist deaktiviert. Die Demodaten werden einmal täglich aus der CSV neu eingelesen.'
                  ));
               }
            break;

            case 'dayset':
               $obj=dbx()->get_include_obj('myLKW_dayset');
               $content=$obj->run();      
            break;

           case 'add_lkw':
               $obj=dbx()->get_include_obj('myLKW_add');
               $content=$obj->run();      
            break;



           case 'report_lkw':
                $obj=dbx()->get_include_obj('myLKW_report');
                $content=$obj->run();    
            break; 

            case 'import_lkw':
                $obj=dbx()->get_include_obj('myLKW_import');
                $content=$obj->run();    
            break;             

        default:
            $o_tpl=dbx()->get_system_obj('dbxTPL');
            $msg['msg']="Modul=($modul) Action=($action) is undef.";
            $content=$o_tpl->get_tpl('dbx','alert-warning',$msg);

     } // switch()
     
     return $content;
   } 
   
   
} // class
