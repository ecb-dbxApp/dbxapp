<?php
namespace dbx\myLKW;

class myLKW_import {


/* =========================================================
   PUBLIC IMPORT
   ========================================================= */

public function import_data(): array {
    $o_db  = dbx()->get_system_obj('dbxDB');

    $path_file = dbx()->get_file_dir().'sys/csv/lkw.csv';

    if (!file_exists($path_file)) {
        return [
            'ok' => 0,
            'inserted' => 0,
            'skipped' => 0,
            'message' => "CSV-Datei nicht gefunden: $path_file",
        ];
    }

    $rows = $this->read_csv($path_file);

    if (!$rows) {
        return [
            'ok' => 0,
            'inserted' => 0,
            'skipped' => 0,
            'message' => 'CSV-Datei ist leer oder nicht lesbar.',
        ];
    }

    $dd_fields = $o_db->get_dd_fields('lkw');

    $dd_names=[];
    foreach ($dd_fields as $f) {
        if (!empty($f['name'])) {
            $dd_names[$f['name']] = 1;
        }
    }

    $count_insert=0;
    $count_skip=0;
    $transaction_started = (int)$o_db->begin('lkw') === 1;

    if (!$transaction_started) {
        return [
            'ok' => 0,
            'inserted' => 0,
            'skipped' => 0,
            'message' => 'CSV-Import konnte nicht gestartet werden.',
        ];
    }

    try {
        if ((int)$o_db->empty('lkw') !== 1) {
            throw new \RuntimeException('Die vorhandenen Demodaten konnten nicht ersetzt werden.');
        }

        foreach ($rows as $row) {

            $record = $this->map_csv_row_to_lkw($row);

            /* LEERZEILEN ERKENNEN */
            if ($this->is_empty_lkw_row($record)) {
                $count_skip++;
                continue;
            }

            /* nur gültige DD Felder */
            $record = array_intersect_key($record,$dd_names);

            if (!$record) {
                $count_skip++;
                continue;
            }

            if ((int)$o_db->insert('lkw',$record,0,1,1,1) !== 1) {
                throw new \RuntimeException('Ein CSV-Datensatz konnte nicht gespeichert werden.');
            }
            $count_insert++;
        }

        if ($count_insert < 1) {
            throw new \RuntimeException('Die CSV-Datei enthält keine importierbaren Datensätze.');
        }

        if ((int)$o_db->commit('lkw') !== 1) {
            throw new \RuntimeException('Der CSV-Import konnte nicht abgeschlossen werden.');
        }
    } catch (\Throwable $e) {
        $o_db->rollback('lkw');
        return [
            'ok' => 0,
            'inserted' => 0,
            'skipped' => $count_skip,
            'message' => $e->getMessage(),
        ];
    }

    return [
        'ok' => 1,
        'inserted' => $count_insert,
        'skipped' => $count_skip,
        'message' => 'CSV-Import abgeschlossen.',
    ];
}

public function import(){

    $o_tpl = dbx()->get_system_obj('dbxTPL');
    $result = $this->import_data();
    $tpl = !empty($result['ok']) ? 'alert-info' : 'alert-danger';
    $msg['msg'] = htmlspecialchars((string)$result['message'], ENT_QUOTES, 'UTF-8');

    if (!empty($result['ok'])) {
        $msg['msg'] .= '<br>Neu angelegt: ' . (int)$result['inserted']
            . '<br>Übersprungen: ' . (int)$result['skipped'];
    }

    return $o_tpl->get_tpl('dbx|' . $tpl,$msg);
}


/* =========================================================
   CSV LESEN
   ========================================================= */

protected function read_csv(string $file): array {

    $data=[];
    $sep=',';

    $fh=fopen($file,'rb');
    if(!$fh) return [];

    $bom=fread($fh,3);
    if($bom!=="\xEF\xBB\xBF"){
        rewind($fh);
    }

    $header=fgetcsv($fh,0,$sep);

    if(!$header){
        fclose($fh);
        return [];
    }

    foreach ($header as &$h) {
        $h = $this->normalize_str($h);
    }

    $fixed=[
        'DOMICILIO'=>'DOMICILIO',
        'TRACTOR'=>'TRACTOR',
        'I.T.V.TRACT'=>'ITV_TRACT',
        'TIPO'=>'TIPO',
        'REMOLQUE'=>'REMOLQUE',
        'I.T.V.REMOL.'=>'ITV_REMOL',
        'CONDUCTOR'=>'CONDUCTOR',
        'TELF.'=>'TELF',
        'EMPRESA'=>'EMPRESA',
        'EXT.'=>'EXT',
        'MANT.'=>'MANT',
        'EVENTOS'=>'EVENTOS',
        'BUJES'=>'BUJES',
        'VENCIMIENTO'=>'VENCIMIENTO',
        'ANOTACIONES'=>'ANOTACIONES',
        'ODOMETRO'=>'ODOMETRO',
    ];

    $fixed_cols=[];

    foreach($header as $i=>$h){
        if(isset($fixed[$h])){
            $fixed_cols[$fixed[$h]]=$i;
        }
    }

    if(!isset($fixed_cols['TRACTOR'])){
        fclose($fh);
        return [];
    }

    $map=[
       'd0'=>['X','Y','Z','AA','AB'],
       'd1'=>['AD','AE','AF','AG','AH'],
       'd2'=>['AJ','AK','AL','AM','AN'],
       'd3'=>['AP','AQ','AR','AS','AT'],
       'd4'=>['AV','AW','AX','AY','AZ']
    ];

    $col_index=[];

    foreach($map as $d=>$cols){
        foreach($cols as $c){
            $col_index[$d][]=$this->excel_col_to_index($c);
        }
    }

    while(($row=fgetcsv($fh,0,$sep))!==false){

        if(!is_array($row)) continue;

        /* komplett leere CSV Zeilen überspringen */
        if(!array_filter($row)){
            continue;
        }

        $row=array_pad($row,140,'');

        foreach($row as &$v){
            $v=$this->normalize_str($v);
        }

        $rec=[];

        foreach($fixed_cols as $f=>$idx){
            $rec[$f]=$row[$idx] ?? '';
        }

        foreach($col_index as $d=>$idxs){

            $rec["__{$d}__"]=[
                'origen_region'=>$row[$idxs[0]] ?? '',
                'origen_lugar'=>$row[$idxs[1]] ?? '',
                'carga_region'=>$row[$idxs[2]] ?? '',
                'carga_lugar'=>$row[$idxs[3]] ?? '',
                'obs'=>$row[$idxs[4]] ?? '',
            ];
        }

        $data[]=$rec;
    }

    fclose($fh);

    return $data;
}


/* =========================================================
   UTF8 CLEAN
   ========================================================= */

protected function dbx_clean_utf8(string $s): string {

    if ($s === '') return '';

    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);

    for ($i = 0; $i < 5; $i++) {

        $prev = $s;

        $s = @iconv('UTF-8', 'Windows-1252//IGNORE', $s);
        $s = @iconv('Windows-1252', 'UTF-8//IGNORE', $s);

        if ($s === $prev) break;
    }

    $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');

    $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s);

    $s = str_replace("\xC2\xA0", ' ', $s);

    $s = preg_replace('/\s+/u', ' ', $s);

    return trim($s);
}


/* =========================================================
   NORMALIZER
   ========================================================= */

protected function normalize_str(?string $v,bool $upper=false): string {

    if($v===null) return '';

    $v=$this->dbx_clean_utf8($v);

    if($upper){
        $v=mb_strtoupper($v,'UTF-8');
    }

    return $v;
}


/* =========================================================
   LEERZEILE ERKENNEN
   ========================================================= */

protected function is_empty_lkw_row(array $record): bool {

    $fields=['TRACTOR','REMOLQUE','TIPO'];

    foreach ($fields as $f) {

        $v = (string)($record[$f] ?? '');

        /* alle whitespace entfernen */
        $v = preg_replace('/\s+/u','',$v);

        if ($v === '' || $v === '-' || $v === '.' || strtolower($v)==='null') {
            continue;
        }

        return false;
    }

    return true;
}

/* =========================================================
   EXCEL COL → INDEX
   ========================================================= */

protected function excel_col_to_index(string $col): int {

    $col=strtoupper(trim($col));

    $len=strlen($col);
    $num=0;

    for($i=0;$i<$len;$i++){
        $num=$num*26+(ord($col[$i])-64);
    }

    return $num-1;
}


/* =========================================================
   CSV → DB
   ========================================================= */

protected function map_csv_row_to_lkw(array $r): array {

    $out=[
        'DOMICILIO'=>$this->normalize_str($r['DOMICILIO'] ?? ''),
        'TRACTOR'=>$this->normalize_str($r['TRACTOR'] ?? '',true),
        'ITV_TRACT'=>$this->normalize_str($r['ITV_TRACT'] ?? ''),
        'TIPO'=>$this->normalize_str($r['TIPO'] ?? '',true),
        'REMOLQUE'=>$this->normalize_str($r['REMOLQUE'] ?? '',true),
        'ITV_REMOL'=>$this->normalize_str($r['ITV_REMOL'] ?? ''),
        'CONDUCTOR'=>$this->normalize_str($r['CONDUCTOR'] ?? ''),
        'TELF'=>$this->normalize_str($r['TELF'] ?? ''),
        'EMPRESA'=>$this->normalize_str($r['EMPRESA'] ?? ''),
        'EXT'=>$this->normalize_str($r['EXT'] ?? ''),
        'MANT'=>$this->normalize_str($r['MANT'] ?? ''),
        'EVENTOS'=>$this->normalize_str($r['EVENTOS'] ?? ''),
        'BUJES'=>$this->normalize_str($r['BUJES'] ?? ''),
        'VENCIMIENTO'=>$this->normalize_str($r['VENCIMIENTO'] ?? ''),
        'ANOTACIONES'=>$this->normalize_str($r['ANOTACIONES'] ?? ''),
        'ODOMETRO'=>$this->normalize_str($r['ODOMETRO'] ?? ''),
    ];

    foreach(['d0','d1','d2','d3','d4'] as $d){

        if(empty($r["__{$d}__"])) continue;

        $out["{$d}_origen_region"]=$this->normalize_str($r["__{$d}__"]['origen_region'] ?? '',true);
        $out["{$d}_origen_lugar"]=$this->normalize_str($r["__{$d}__"]['origen_lugar'] ?? '');
        $out["{$d}_carga_region"]=$this->normalize_str($r["__{$d}__"]['carga_region'] ?? '',true);
        $out["{$d}_carga_lugar"]=$this->normalize_str($r["__{$d}__"]['carga_lugar'] ?? '');
        $out["{$d}_observaciones"]=$this->normalize_str($r["__{$d}__"]['obs'] ?? '');
    }

    return $out;
}


/* =========================================================
   RUN
   ========================================================= */

public function run(){

    $modul=dbx()->get_system_var('dbx_activ_modul');
    $work=dbx()->get_modul_var('dbx_run2','import');
    $content="";

    switch($work){

        case 'import':
            $content=$this->import();
        break;

        default:

            $o_tpl=dbx()->get_system_obj('dbxTPL');

            $content=$o_tpl->get_tpl('dbx|alert-warning',[
                'msg'=>"Modul=($modul) Work=($work) is undef."
            ]);
    }

    return $content;
}

}
