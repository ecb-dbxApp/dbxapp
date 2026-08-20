<?php
namespace dbx\myLKW;

use function dbx\myLKW\dbx_get_datum;

class myLKW_list {

    private string $dd = 'lkw';
    private string $fd = 'lkw-grid';
    private $o_db;

    public function __construct(){
        $this->o_db = dbx()->get_system_obj('dbxDB');
    }

    /* --- Fenster erzeugen --- */
    private function dbx_shift_window(int $block): string {

        // Basisdatum über zentrale Logik bestimmen:
        // 0 Offset = aktueller Dispo-Tag (automatisch Workday korrekt)
        $base_str = dbx_get_datum('yyyy-mm-dd', 0);
        $base    = new \DateTime($base_str);

        // Offset-Logik identisch:
        // d0=-2, d1=-1, d2=0, d3=+1, d4=+2, d5=+3
        $offset = $block - 2;

        return dbx_get_datum('dy dd.mm~~', $offset, $base);
    }

    /** HTML Modul-Template */
    private function create_tab() {
        $o_tpl = dbx()->get_system_obj('dbxTPL');
   
        $data['dat0'] = $this->dbx_shift_window(0);
        $data['dat1'] = $this->dbx_shift_window(1);
        $data['dat2'] = $this->dbx_shift_window(2);
        $data['dat3'] = $this->dbx_shift_window(3);
        $data['dat4'] = $this->dbx_shift_window(4);
        $data['dat5'] = $this->dbx_shift_window(5);

        $cols = $this->get_fd_grid_cols();
        $data['cols'] = $o_tpl->replaces($cols, $data);
        $data['sync_url'] = dbx()->action_url('?dbx_modul=myLKW&dbx_run1=sync_grid');
        $data['dbx_search'] = $o_tpl->get_tpl('dbx|search', dbx()->get_system_obj('dbxSearchDefaults')->build(array(
            'title'       => 'Search',
            'extra_attrs' => 'data-dbx="grid-search"',
            'i'           => dbx()->next_id(),
        )));

        dbx()->debug("grid_cols=($cols)");

        $content=$o_tpl->get_tpl('modul|report-lkw',$data);
        return $content;
    }

    private function get_fd_fields(): array {
        $dir = dbx()->get_base_dir() . 'dbx/modules/myLKW/fd/';
        $file = dbx()->lng_resolve_file($dir, $this->fd, 'fd.php', '', true);

        if (!$file || !is_file($file)) {
            return array();
        }

        $fields = array();
        $field = array();
        include $file;

        return is_array($fields) ? array_values($fields) : array();
    }

    private function get_fd_cols(): string {
        $cols = array();

        foreach ($this->get_fd_fields() as $field) {
            if (!empty($field['name']) && is_string($field['name'])) {
                $cols[] = $field['name'];
            }
        }

        return implode(',', $cols);
    }

    private function get_fd_grid_cols(): string {
        $cols = array();

        foreach ($this->get_fd_fields() as $field) {
            if (empty($field['name'])) {
                continue;
            }

            $name = (string) $field['name'];
            if (str_starts_with($name, '_')) {
                continue;
            }

            $type = $field['type'] ?? 'text';
            $grid_type = method_exists($this->o_db, 'map_dd_type_to_grid_type')
                ? $this->o_db->map_dd_type_to_grid_type((string) $type)
                : 'text';

            $label = trim((string)($field['label'] ?? ''));
            $label = str_replace(array(':', '[', ']'), '-', $label);
            if ($label === '') {
                $label = $name;
            }

            $group = '';
            if (!empty($field['group']) && is_string($field['group'])) {
                $group = '@' . trim($field['group']);
            }

            $protect = isset($field['protect']) ? (string) $field['protect'] : '0';
            $suffix = '';
            if ($protect === '2') {
                $suffix = ':!v';
            } elseif ($protect === '1') {
                $suffix = ':p';
            }

            $cols[] = $name . '[' . $label . ']:' . $grid_type . $suffix . $group;
        }

        return implode(',', $cols);
    }

    /** Daten sortieren für Tabulator (AJAX Header Sort) */
    private function sort_lkw(){

        $o_db = $this->o_db;
        $dd  = $this->dd;

        $server_time = (new \DateTime())->format('Y-m-d H:i:s.v');

        $rwhere = 'TRACTOR > " "';
        $flds   = $this->get_fd_cols();

        $rrows = 300;
        $rpos  = 0;

        $field = $_GET['field'] ?? '';
        $dir   = $_GET['dir'] ?? 'asc';
        if ($dir=='none') $dir ='desc';

        if(!preg_match('/^[a-zA-Z0-9_]+$/',$field)){
            $field = 'TIPO';
        }

        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        if ($field === 'TIPO') {
            $dir = ($dir === 'ASC') ? 'DESC' : 'ASC';
            $rsort = "TIPO {$dir}";
        } else {
            $rsort = "TIPO DESC, {$field} {$dir}";
        }

        $rdesc = '';

        $rows = $o_db->select(
            $dd,
            $rwhere,
            $flds,
            $rsort,
            $rdesc,
            '',
            $rrows,
            $rpos
        );

        if(!is_array($rows)){
            $rows = [];
        }

        $count = count($rows);
        dbx()->debug("count=($count) resort=($rsort)");

        dbx()->json_response(array(
            'ok'          => 1,
            'count'       => $count,
            'rows'        => array_values($rows),
            'server_time' => $server_time,
        ), true);
    }

    /* ========================================================= */

    private function read_lkw(){

        $o_db = $this->o_db;
        $dd  = $this->dd;

        $server_time = (new \DateTime())->format('Y-m-d H:i:s.v');

        $rwhere = 'TRACTOR > " "';
        $flds   = $this->get_fd_cols();

        $rrows = 300;
        $rpos  = 0;
        $rsort = 'TIPO DESC, d2_carga_region ASC, TRACTOR ASC';
        $rdesc = '';

        $rows = $o_db->select(
            $dd,
            $rwhere,
            $flds,
            $rsort,
            $rdesc,
            '',
            $rrows,
            $rpos
        );

        if(!is_array($rows)){
            $rows = [];
        }

        $count = count($rows);

        dbx()->json_response(array(
            'ok'          => 1,
            'count'       => $count,
            'rows'        => array_values($rows),
            'server_time' => $server_time,
        ), true);
    }

    /* ========================================================= */

    private function save_lkw(){
        $ok=0;
        $dd=$this->dd;
        if (isset($_POST['rows'])) {
           $post = $_POST['rows']; 
        } else { 
          $this->json(false,"Keine rows übergeben");  
        }
        //dbx_debug("##SAVE###",$post);
        foreach ($post as $no => $record) {
            dbx()->debug("record=",$record);
            if(!empty($record['id'])){
               $new_rec="ERROR"; 
               $id = intval($record['id']); 
               $ok = $this->o_db->update($this->dd,$record,$id);
               if ($ok) $new_rec=$this->o_db->select1($dd,$id);
               dbx()->debug("WRITE-LKW ok=($ok)= id=($id) New rec=",$new_rec); 
            }      
        }
        $this->json($ok);

        /*
        if(empty($post['id'])){
            $this->json(false,"Keine ID übergeben");
        }

        $id = intval($post['id']);
        unset($post['id']);

        foreach($post as $k=>$v){
            if(is_string($v)) $post[$k] = trim($v);
        }
        dbx()->debug("Save LKW Id=($id) Post=",$post);
        $ok = $this->o_db->update($this->dd, $post,$id,0,1,0,1);
        dbx()->debug("WRITE-LKW ok($ok)= id=($id) data=",$post); 

        $this->json($ok);
        */
    }

    /* ========================================================= */

    private function delete_lkw(){

        $id = intval($_POST['id'] ?? 0);

        if(!$id){
            $this->json(false,"Keine ID übergeben");
        }

        $ok = $this->o_db->delete($this->dd,$id);

        $this->json($ok);
    }

    /* ========================================================= */

    private function json(bool $success, string $msg=""){
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'=>$success,
            'msg'=>$msg
        ],JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ========================================================= */

    public function run() {

        $run = dbx()->get_modul_var('dbx_run2','create_tab','parameter');
        dbx()->debug("myLKW_list run work=($run)");

        switch($run){

            case 'create_tab':
                return $this->create_tab();
                break;

            case 'sort_lkw':
                $this->sort_lkw();
                break;

            case 'read_lkw':
                $this->read_lkw();
                break;

            case 'save_lkw':
                $this->save_lkw();
                break;

            case 'delete_lkw':
                $this->delete_lkw();
                break;

            default:
                return "Unbekannte Aktion ($run)";
        }
    }

}
