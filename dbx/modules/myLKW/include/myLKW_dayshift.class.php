<?php
namespace dbx\myLKW;

use function dbx\myLKW\dbx_get_datum;

class myLKW_dayshift {
    
    private function is_workday(string $date): bool {

        $d = new \DateTime($date);
        $w = (int)$d->format('N'); // 1..7

        $max_workday = (int) dbx()->get_cfg('myLKW','workdays');

        if (!$max_workday) {
            $max_workday = 5;
        }

        if ($w > $max_workday) {
            return false;
        }

        return true;
    }


    private function shift_day(): int {

        $dd  = 'lkw';
        $o_db = dbx()->get_system_obj('dbxDB');

        $table = $o_db->get_dd_table($dd);
        if (!$table) return 0;

        $today = date('Y-m-d');

        /* -----------------------------------------
        Workday prüfen
        ----------------------------------------- */

        if (!$this->is_workday($today)) {
            dbx()->debug("kein Workday → kein Shift");
            return 0;
        }

        $uid = dbx()->user();
        $now = date('Y-m-d H:i:s');

        try {

            if (!$o_db->begin($dd)) return 0;

            $sql = "UPDATE $table SET

            /* ===== d0 ← d1 ===== */
            d0_origen_region = d1_origen_region,
            d0_origen_lugar  = d1_origen_lugar,
            d0_carga_region  = d1_carga_region,
            d0_carga_lugar   = d1_carga_lugar,
            d0_observaciones = d1_observaciones,

            /* ===== d1 ← d2 ===== */
            d1_origen_region = d2_origen_region,
            d1_origen_lugar  = d2_origen_lugar,
            d1_carga_region  = d2_carga_region,
            d1_carga_lugar   = d2_carga_lugar,
            d1_observaciones = d2_observaciones,

            /* ===== d2 ← d3 ===== */
            d2_origen_region = d3_origen_region,
            d2_origen_lugar  = d3_origen_lugar,
            d2_carga_region  = d3_carga_region,
            d2_carga_lugar   = d3_carga_lugar,
            d2_observaciones = d3_observaciones,

            /* ===== d3 ← d4 ===== */
            d3_origen_region = d4_origen_region,
            d3_origen_lugar  = d4_origen_lugar,
            d3_carga_region  = d4_carga_region,
            d3_carga_lugar   = d4_carga_lugar,
            d3_observaciones = d4_observaciones,

            /* ===== d4 ← d5 ===== */
            d4_origen_region = d5_origen_region,
            d4_origen_lugar  = d5_origen_lugar,
            d4_carga_region  = d5_carga_region,
            d4_carga_lugar   = d5_carga_lugar,
            d4_observaciones = d5_observaciones,

            /* ===== d5 leer ===== */
            d5_origen_region = '',
            d5_origen_lugar  = '',
            d5_carga_region  = '',
            d5_carga_lugar   = '',
            d5_observaciones = '',

            update_date      = '$now',
            update_uid       = '$uid'";

            if (!$o_db->raw_query($o_db->get_dd_server($dd),$sql)) {

                dbx()->debug("rawQuery rollback");
                $o_db->rollback($dd);
                return 0;
            }

            if (!$o_db->commit($dd)) {

                dbx()->debug("commit rollback");
                $o_db->rollback($dd);
                return 0;
            }

            return 1;

        }
        catch (\Throwable $e) {

            dbx()->debug("rollback");
            $o_db->rollback($dd);
            return 0;
        }
    }



    public function run() {

        dbx()->debug("start myLKW_dayshift");

        $cfg_date = dbx()->get_cfg('myLKW','shiftdate');
        $today   = date('Y-m-d');

        if ($cfg_date && $cfg_date !== 'undef' && $cfg_date !== '0000-00-00') {

            if ($cfg_date >= $today) {

                $content='
                <div class="alert alert-warning">
                    <strong>Aviso:</strong> No se ha realizado el cambio de día.
                    La fecha configurada ('.$cfg_date.') ya es hoy o posterior.
                </div>';

                $content.="[modul=myLKW]dbx_run1=list_lkw[/modul]";
                return $content;
            }
        }

        $ok = $this->shift_day();

        dbx()->debug("myLKW_dayshift=($ok)");

        if ($ok) {

            $cfg_date = dbx()->get_cfg('myLKW','shiftdate');

            if (
                !$cfg_date ||
                $cfg_date === 'undef' ||
                $cfg_date === '0000-00-00'
            ) {
                $date = new \DateTime('today');
            }
            else {

                $date = new \DateTime($cfg_date);

                // ✅ NEU: nächster Workday statt +1 Tag
                $next = dbx_get_datum('yyyy-mm-dd', 1, $date);
                $date = new \DateTime($next);
            }

            $config = dbx()->get_cfg('myLKW');
            $config['shiftdate'] = $date->format('Y-m-d');

            dbx()->set_cfg('myLKW',$config);
        }

        $content='
        <div class="alert alert-info">
            <strong>Información:</strong> El cambio de día se ha realizado correctamente.
        </div>';

        $content.="[modul=myLKW]dbx_run1=list_lkw[/modul]";

        return $content;
    }

}