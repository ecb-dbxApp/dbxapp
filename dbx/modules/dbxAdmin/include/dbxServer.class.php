<?php
namespace dbx\dbxAdmin;
dbx()->get_system_obj( 'dbxReport', 'use' );


class dbxReport_Tables extends \dbxReport {
    private function check_create_dd( $dd ) {
        $retval='error';
        $oDD = dbx()->get_system_obj( 'dbxDD' );
        $dd_file=dbx()->os_path(dbx()->get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.php');
        if ( !file_exists( $dd_file ) ) {
            $oDD->create_dd( $dd );
            $retval = 'create';
        } else {
            $retval = '<span class="red">not exist</span>';
            $exist  = $dd->get_table_exist($dd);
            if ($exist) $retval = 'exist';
            if ($exist) {
                $change = $dd->update_dd( $dd );
                if ($change) {
                    $retval.=' change';
                } else {
                    $retval.=' ok'; 
                }
            }
        }
        return $retval;
    }


    /**
     * Scannt ein Verzeichnis nach allen *.dd.php Dateien und gibt ein Array mit den Dateinamen zurück,
     * bei denen die Bedingungen $table['table'] == $db_table und $table['server'] == $db_server erfüllt sind.
     *
     * @param string $db_server Der Name des Datenbank-Servers, der in der $table['server'] Variable gesucht wird.
     * @param string $db_table Der Name der Datenbank-Tabelle, der in der $table['table'] Variable gesucht wird.
     * @param string $path Der Pfad, in dem nach den *.dd.php Dateien gesucht werden soll. Standardmäßig das aktuelle Verzeichnis.
     * @return array Ein Array mit den Dateinamen der passenden DataDictionary-Dateien.
     */
    private function get_dd_exist($db_server, $db_table, $path = '.') {
        $datadics = array();
        
        // Sicherstellen, dass der Pfad mit einem Slash endet
        $path = rtrim($path, '/') . '/';
        
        // Verzeichnis nach *.dd.php Dateien durchsuchen
        $files = glob($path . '*.dd.php');
        
        foreach ($files as $file) {
            // Temporäre Variablen initialisieren, um Konflikte zu vermeiden
            $table = array();
            $fields = array();
            
            // Datei einbinden
            include $file;
            
            // Überprüfen, ob die Bedingungen erfüllt sind
            if (isset($table['table']) && $table['table'] == $db_table && 
                isset($table['server']) && $table['server'] == $db_server) {
                $datadics[] = $file;
            }
        }
        
        return $datadics;
    }
    
   

    /**
     * Verarbeitet den übergebenen Inhalt und aktualisiert den Datensatz mit einer Liste der gefundenen DataDictionary-Dateien.
     * Nach jedem DataDictionary-Eintrag wird der $but_edit-Button angezeigt, und am Ende wird der $but_add-Button angehängt.
     * Die Buttons haben die gleiche Größe, und die Einträge werden nebeneinander angezeigt.
     *
     * @param string $content Der zu verarbeitende Inhalt (wird unverändert zurückgegeben).
     * @return string Der unveränderte Inhalt.
     */
    public function run_body($content) {
        // Aktuellen Datensatz aus der Instanzvariable holen
        $record = $this->_record;
        
        // Informationen aus dem Datensatz extrahieren
        $server = $record['server']; // Name des Datenbank-Servers
        $table  = $record['name'];   // Name der Datenbank-Tabelle
        $path   = dbx()->get_base_dir() . 'dbx/modules/dbx/dd/'; // Pfad zu den DataDictionary-Dateien
        $rid    = $server.'|'.$table;

        // DataDictionary-Dateien suchen
        $dds = $this->get_dd_exist($server, $table, $path);
        
        // Dateinamen verarbeiten:
        // 1. '.dd.php' entfernen
        // 2. Nach jedem Dateinamen $but_edit einfügen
        $dd_list = implode('', array_map(function($file) {
            // Den Dateinamen ohne '.dd.php' extrahieren
            $dd = basename($file, '.dd.php');
            
            // Button für das Bearbeiten eines DataDictionary

            $but_edit = '<a class="nav-link openWin" href="?dbx_modul=dbxAdmin&dbx_run1=datadic&dbx_run2=list_dd&dbx_run3=row_edit&rid='. $dd .'" data-dbx_win_width="1400" data-dbx_win_height="800"><i class="bi bi-pencil-square"></i></a>';


            // Den DataDictionary-Eintrag und den Button kombinieren
            return '<span class="dd-item">' . $dd . '</span>' . $but_edit;
        }, $dds));
        
        // Button für das Hinzufügen eines neuen DataDictionary
        $base_url=dbx()->get_base_url();
        $bt['title']      = $this->get_fd_message('create_dd_title');
        $bt['buttonText'] = "<i class='bi bi-plus-lg'></i>"; // Text des Buttons
        $bt['class']      = "btn btn-primary btn-sm p-1 d-flex align-items-center justify-content-center";
        $bt['style']      = "width: 36px; height: 24px; cursor: pointer; margin-left: 5px;"; 
        $bt['url']        = $base_url."?dbx_modul=dbxAdmin&dbx_run1=datadic&dbx_run2=add_dd&rid=".$rid;  
        $bt['modalClass'] = "modal-xl";
        $bt['returnJs']   = "dbxReSendForm(\'#dbx_form_{i}\')"; //"alert(\'JS run\');";
        $bt['isPrompt']   = 'false' ; // true, wenn es sich um ein Prompt-Modal handelt
        $bt['selectValueClass'] = ""; // Nur relevant, wenn $isPrompt true ist
        $bt['selectTarget']     = ""; // Nur relevant, wenn $isPrompt true ist
        $but_add =$this->get_tpl('button_modal',$bt);

  
                 


        // Kombiniere die DataDictionary-Einträge und die Buttons in einer Flexbox-Struktur
        $dd_list = '<div class="d-flex justify-content-between align-items-center w-100">
                        <span class="dd-list">' . $dd_list . '</span>
                        ' . $but_add . '
                    </div>';
        
        // Aktualisiere den Datensatz mit der Liste der gefundenen Dateien
        $record['dd'] = $dd_list;
        
        // Aktualisiere die Instanzvariable mit dem modifizierten Datensatz
        $this->_record = $record;
        
        // Gib den unveränderten Inhalt zurück
        return $content;
    }
    

}    
    
class dbxReport_Server extends \dbxReport {


    public function run_body( $content ) {
        $oDB =dbx()->get_system_obj('dbxDB');
        $oTPL=dbx()->get_system_obj('dbxTPL');

        $tables=0; $count_tables=0;

        $record = $this->_record;
        $server = $record['name'];
        $isActive = $oDB->db_server_config_is_active((string)$server, is_array($record) ? $record : array());
        $connect = $isActive ? $oDB->connect_db_server($server) : 0;
        $action = $this->_action;

        //dbx_debug("record server=($server)",$record);


        if (!$isActive) {
            $record['sync'] = '<span class="badge bg-secondary">'
                . dbx()->esc($this->get_fd_message('status_inactive')) . '</span>';
        } elseif ($connect) {
            $record['sync'] = '<span class="green">' . dbx()->esc($this->get_fd_message('yes')) . '</span>';
            $tables=$oDB->get_db_tables($server,'sqlite_sequence');
            $count_tables=count($tables);

        } else {
            $but['href']  =$action.'&dbx_run3=create_db&rid='.$server;
            $but['label'] ='DB';
            $but['class'] ='btn-inline'; 
            $but['tooltip'] = $this->get_fd_message('connection_check');

            $but_connect=$oTPL->get_tpl('dbx|button_dbcreate',$but);
   
            $record['sync'] = "<span class='red'>" . dbx()->esc($this->get_fd_message('no')) . "</span> $but_connect";
        }

        $record['tables']=$count_tables;
        
        $this->_record = $record;
        return $content;
    }

}



Class dbxServer extends \dbxObj {

    /** @var \dbxForm|null Stabiler sprachabhängiger Textkontext der Server-FD. */
    private $serverTexts;

    /**
     * Liefert Server-Texte aus der aktiven Sprachversion der FD.
     *
     * @return \dbxForm
     */
    private function server_texts() {
        if ($this->serverTexts) {
            return $this->serverTexts;
        }

        dbx()->get_system_obj('dbxForm', 'use');
        $texts = new \dbxForm();
        $texts->set_form_help_enabled(false);
        $texts->_fd = 'dbxAdmin|server';
        $texts->load_fd_messages();
        $this->serverTexts = $texts;

        return $this->serverTexts;
    }

    private function run_maction($act,$sel) {
        return 1;
    }

    private function browser_server() {
        $uid     = dbx()->user();
        $do      = dbx()->get_modul_var('dbx_run3');
        $server  = dbx()->get_modul_var('rid');
        $texts   = $this->server_texts();

          


        if ($do == 'create_db') return 'create_db';

        $oDD     = dbx()->get_system_obj('dbxDD');
        $oReport = new dbxReport_Tables;
        $oReport->_action = '?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=list_tables&rid='.$server;
 
        $flds['server']      = $texts->get_fd_message('column_server');
        $flds['name']        = $texts->get_fd_message('column_table');
        $flds['count']       = $texts->get_fd_message('column_count');
        $flds['dd']          = $texts->get_fd_message('column_datadic');
   
 

        $oReport->init( 'report-tables');
        $oReport->_fd = 'dbxAdmin|server';
        $oReport->load_fd_messages();
        $oReport->set_form_help_enabled(false);
        $oReport->add_rep('bar_title', $texts->get_fd_message('column_tables'));
        $oReport->add_rep('bar_subtitle', $texts->format_fd_message('tables_info', array('server' => $server)));
        $oReport->_create_row_select  = 1;
        $oReport->_create_row_edit    = 1;
        $oReport->_create_row_delete  = 1;
        $oReport->_create_row_show    = 1;
        $oReport->_create_sel_flds    = 0;
        $oReport->_fld_id     = 'server';

        $oReport->_tabel_tpls['tpl_row_delete'] = 'modul|confirm_row_delete_table';
        $oReport->_tabel_tpls['tpl_row_edit']   = 'table_row_modal-edit';
        $oReport->_tabel_tpls['tpl_row_show']   = 'table_row_modal-show';

        //$bt['label']="Neue Datenbank Tabelle erstellen.";
        //$bt['href'] ="?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=add_server_table&server=$server";

        //$oReport->add_obj('new_table','button',$bt);

        // Button für das Hinzufügen einer neuen Datenbank-Tabelle
        $base_url=dbx()->get_base_url();
        $bt['title']      = $texts->get_fd_message('new_table_title');
        $bt['buttonText'] = $texts->get_fd_message('new_table') . " <i class='bi bi-plus-lg'></i>"; // Text des Buttons
        $bt['class']      = "btn btn-primary align-items-center justify-content-center w100";
        $bt['style']      = ""; 
        $bt['url']        = $base_url."?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=add_dbtable&server=$server";  
        $bt['modalClass'] = "modal-xl";
        $bt['returnJs']   = "dbxReSendForm(\'#dbx_form_{i}\')"; //"alert(\'JS run\');";
        $bt['isPrompt']   = 'false' ; // true, wenn es sich um ein Prompt-Modal handelt
        $bt['selectValueClass'] = ""; // Nur relevant, wenn $isPrompt true ist
        $bt['selectTarget']     = ""; // Nur relevant, wenn $isPrompt true ist
        //$but_add =$this->get_tpl('button_modal',$bt);
        $oReport->add_obj('new_table','button_modal',$bt);
        $tableBtn = dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|button_modal', $bt);
        $oReport->add_obj('bar_actions', 'obj-value', $tableBtn);




        
        $data=array(); 
        $rdata=$oDD->get_db_tables($server);

        //dbx_debug("DATA Tablels Server=($server)",$rdata);

  
        $oReport->_data        = $data;
        $oReport->_msg_info    = $texts->format_fd_message('tables_info', array('server' => $server));
        $oReport->_msg_success = '';

        $rwhere = dbx()->get_request_var('dbx_rwhere', '', 'sqlsearch');
        $rrows  = dbx()->get_request_var('dbx_rrows', 9999, 'int');
        $rpos   = dbx()->get_request_var('dbx_rpos', 0, 'int');
        $rpos=0; $rrows=9999;

        $oReport->_rflds = $flds;
        $oReport->_mode = 'table';
        $oReport->_pages = 0;
        $oReport->_rrows = $rrows;
        $oReport->_rpos = $rpos;
        $oReport->_rdata = $oReport->data_rows( $rdata, $rpos, $rrows );
        $oReport->_rcount = count( $rdata );

        $content = $oReport->run();




        // $content = "dbxAdmin->Server ($server)<br>";

        return $content;
    }

    private function get_db_config(): array {
        $file = $this->get_db_config_file();
        $config = array();

        if (file_exists($file)) {
            include $file;
        }

        if (!is_array($config)) {
            $config = array();
        }

        if (!isset($config['db']) || !is_array($config['db'])) {
            $config['db'] = array();
        }

        return $config;
    }

    private function get_db_config_file(): string {
        return dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/cfg/config.php');
    }

    private function write_db_config(array $config): int {
        $file = $this->get_db_config_file();
        $dir = dirname($file);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $config = dbx()->get_system_obj('dbxConfigStore')->normalize_for_store($config);
        $content = "<?php \n" . dbx_convertArrayToPHPCode($config, '$config');
        $ok = file_put_contents($file, $content);

        if ($ok) {
            $_SESSION['dbx']['config']['dbx'] = $config;
            $_SESSION['dbx']['config_file']['dbx'] = $file;
        }

        return $ok ?: 0;
    }

    private function get_default_server(array $config): string {
        return isset($config['default_server']) ? trim((string) $config['default_server']) : '';
    }

    private function server_form_data(string $server, array $config): array {
        $data = array(
            'old_name'   => '',
            'name'       => '',
            'activ'      => '1',
            'type'       => 'mysql',
            'host'       => '127.0.0.1',
            'dbname'     => '',
            'user'       => '',
            'pass'       => '',
            'port'       => '',
            'is_default' => 0,
        );

        if ($server !== 'new' && isset($config['db'][$server]) && is_array($config['db'][$server])) {
            $data = array_merge($data, $config['db'][$server]);
            $data['old_name'] = $server;
            $data['name'] = $server;
        }

        if ($server === 'new') {
            $data['old_name'] = '';
            $data['name'] = '';
        }

        if ($this->get_default_server($config) === $data['name']) {
            $data['is_default'] = 1;
        }

        return $data;
    }

    private function normalize_server_record(array $post): array {
        $type = strtolower(trim((string) ($post['type'] ?? 'mysql')));
        $host = trim((string) ($post['host'] ?? ''));
        $activ = (string) ((int) ($post['activ'] ?? 1) ? 1 : 0);

        if ($type === 'sqlite' && $host !== '') {
            $host = dbx()->config_path_store($host, true);
        }

        return array(
            'activ' => $activ,
            'type'   => $type,
            'host'   => $host,
            'dbname' => trim((string) ($post['dbname'] ?? '')),
            'user'   => trim((string) ($post['user'] ?? '')),
            'pass'   => (string) ($post['pass'] ?? ''),
            'port'   => trim((string) ($post['port'] ?? '')),
        );
    }

    private function test_server_connection(string $server, array $config): array {
        $oDB = dbx()->get_system_obj('dbxDB');
        $oldSessionConfig = $_SESSION['dbx']['config']['dbx'] ?? null;

        $_SESSION['dbx']['config']['dbx'] = $config;

        if (isset($oDB->db[$server])) {
            unset($oDB->db[$server]);
        }

        $oDB->_dbMessage = '';
        $oDB->_error = '';
        $ok = $oDB->connect_db_server($server) ? 1 : 0;
        $message = $oDB->_dbMessage ?: $oDB->_error;

        if (isset($oDB->db[$server])) {
            unset($oDB->db[$server]);
        }

        if ($oldSessionConfig === null) {
            unset($_SESSION['dbx']['config']['dbx']);
        } else {
            $_SESSION['dbx']['config']['dbx'] = $oldSessionConfig;
        }

        return array('ok' => $ok, 'message' => $message);
    }

    private function ensure_server_connection(string $server, array $config): array {
        return $this->test_server_connection($server, $config);
    }

    private function save_server_from_form(array $post): array {
        $texts = $this->server_texts();
        $config = $this->get_db_config();
        $oldName = trim((string) ($post['old_name'] ?? ''));
        $newName = trim((string) ($post['name'] ?? ''));

        if ($newName === '') {
            return array('ok' => 0, 'message' => $texts->get_fd_message('server_name_missing'));
        }

        if ($oldName !== '' && $oldName !== $newName && isset($config['db'][$newName])) {
            return array('ok' => 0, 'message' => $texts->format_fd_message('server_exists', array('server' => $newName)));
        }

        if ($oldName === '' && isset($config['db'][$newName])) {
            return array('ok' => 0, 'message' => $texts->format_fd_message('server_exists', array('server' => $newName)));
        }

        $record = $this->normalize_server_record($post);

        if (strtolower(trim((string) ($record['type'] ?? ''))) === 'sqlite') {
            return array('ok' => 0, 'message' => $texts->get_fd_message('sqlite_config_error'));
        }

        if ($oldName !== '' && $oldName !== $newName && isset($config['db'][$oldName])) {
            unset($config['db'][$oldName]);
        }

        $config['db'][$newName] = $record;

        if ((int) ($post['is_default'] ?? 0) === 1 || $this->get_default_server($config) === '') {
            $config['default_server'] = $newName;
        }

        $test = ((string)($record['activ'] ?? '1') === '0')
            ? array('ok' => 1, 'message' => $texts->get_fd_message('server_disabled_test_skipped'))
            : $this->ensure_server_connection($newName, $config);

        if (!$test['ok']) {
            $msg = $test['message'] ? $test['message'] : $texts->get_fd_message('connection_failed');
            return array('ok' => 0, 'message' => $msg);
        }

        $ok = $this->write_db_config($config);

        if (!$ok) {
            return array('ok' => 0, 'message' => $texts->get_fd_message('config_save_error'));
        }

        $oDB = dbx()->get_system_obj('dbxDB');
        if ($oldName && isset($oDB->db[$oldName])) {
            unset($oDB->db[$oldName]);
        }
        if (isset($oDB->db[$newName])) {
            unset($oDB->db[$newName]);
        }

        return array(
            'ok' => 1,
            'message' => $texts->format_fd_message('config_saved_connected', array('server' => $newName)),
            'server' => $newName,
        );
    }

    private function edit_server() {
 
        $content=''; 
        $oForm =dbx()->get_system_obj('dbxForm');
        $texts = $this->server_texts();
        $server=dbx()->get_modul_var('rid' ,'new','parameter');
        if ($server === '') {
            $server = 'new';
        }
        $fd    ='dbxAdmin|server';

        $oForm->init('form-server');
        $config=$this->get_db_config();
        $data=$this->server_form_data($server, $config);

        $oForm->_data      = $data;
        $oForm->_msg_info  = $texts->get_fd_message('edit_server');
        $oForm->_fd        = $fd;
        $oForm->load_fd_messages();
        $oForm->_action    = '?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=edit_server&rid='.$server;
        $oForm->_fld_change_state='*'; // get allways all, unchaged too

        $oForm->add_flds();
    
        if($oForm->submit()) {
            if(!$oForm->errors()) {      // submit && no errors && no warnings
                $change=$oForm->changed();
                if ($change) {
                    $save = $this->save_server_from_form($oForm->_post);

                    if ($save['ok']) {
                        $serverName = (string)($save['server'] ?? '');
                        dbx()->set_modul_var('dbx_run2', 'list_server');
                        dbx()->set_modul_var('dbx_run3', '');
                        dbx()->set_modul_var('rid', $serverName);
                        return $this->report_server();
                    } else {
                        $oForm->_msg_error = $save['message'];
                    }
                } else {
                    $oForm->_msg_success = $texts->get_fd_message('no_change');
                }
            } else {
             $err_flds='';
             $errors=$oForm->_errors;
             foreach ($errors as $key => $value) {
               $err_flds.=$key.' ';
             }
             $oForm->_msg_error = $texts->format_fd_message('check_input', array('fields' => trim($err_flds)));
          }
        } else {
            $oForm->add_obj('form_msg','obj-value','');
        }
     
        $content=$oForm->run();
    
        return $content;
    
    }


    private function delete_server($server) {
        dbx()->debug("delete server=($server)");
        $ok=0;
        if ($server) {
            $config=$this->get_db_config();
            if (isset($config['db'][$server])) unset($config['db'][$server]);
            if ($this->get_default_server($config) === $server) {
                $keys = array_keys($config['db']);
                $config['default_server'] = $keys[0] ?? '';
            }
            $ok=$this->write_db_config($config);
        }
        return $ok;
    }

    private function create_server($server) {
        $content='';
        $oDB =dbx()->get_system_obj('dbxDB');
        $oTPL=dbx()->get_system_obj('dbxTPL');
        $texts=$this->server_texts();

        if (isset($oDB->db[$server])) {
            unset($oDB->db[$server]);
        }

        $config = dbx()->get_config('dbx', 'db');
        $dbConfig = is_array($config) && isset($config[$server]) && is_array($config[$server]) ? $config[$server] : array();
        if (!$oDB->db_server_config_is_active((string)$server, $dbConfig)) {
            $msg['msg'] = $texts->format_fd_message('server_disabled', array('server' => $server));
            return $oTPL->get_tpl('dbx|alert-info', $msg);
        }

        $ok=$oDB->connect_db_server($server);
        if ($ok) {
            $msg['msg'] = $texts->format_fd_message('server_connected', array('server' => $server));
            $content=$oTPL->get_tpl('dbx|alert-success',$msg);
        } else {
            $msg['msg'] = $texts->format_fd_message('server_connect_error', array('server' => $server))
                . '<br>' . dbx()->esc((string)$oDB->_dbMessage);
            $content=$oTPL->get_tpl('dbx|alert-danger',$msg);
        }
        return $content;
    }

    Private function report_server() {

        $oReport = new dbxReport_Server;
        $oReport->_action = '?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=list_server';
        $texts = $this->server_texts();
        $listMessage = '';

        $content = "dbxAdmin->DataDic<br>";
        $uid     = dbx()->user();
        $oDD     = dbx()->get_system_obj('dbxDD');
        $do      = dbx()->get_modul_var('dbx_run3');
        $server  = dbx()->get_modul_var('rid');


        if ($do =='create_db') {
            if ($server) return $this->create_server($server); 
        }

        if ($do == 'row_show') {
            if ($server) return $this->browser_server();
        }

        if ($do == 'row_edit') {
           $modal_content=$this->edit_server();
           return $modal_content;
        }
        if ($do == 'row_delete' && $server) {
           $ok=$this->delete_server($server);
           $listMessage = $ok
               ? $texts->format_fd_message('server_deleted', array('server' => $server))
               : $texts->format_fd_message('server_delete_error', array('server' => $server));
        }

        $config=$this->get_db_config();
        $xdata=$config['db'];
        $defaultServer=$this->get_default_server($config);
        $rdata=array();
        foreach ($xdata as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            if (dbx()->get_system_obj('dbxConfigStore')->is_module_db_entry((string) $key, $value)) {
                continue;
            }
            $record=$value;
            $record['name']=$key;
            $record['activ'] = array_key_exists('activ', $record) ? (string)$record['activ'] : '1';
            $record['activ_view'] = ((string)$record['activ'] === '0')
                ? '<span class="badge bg-secondary">' . dbx()->esc($texts->get_fd_message('status_inactive')) . '</span>'
                : '<span class="badge bg-success">' . dbx()->esc($texts->get_fd_message('status_active')) . '</span>';
            $record['is_default'] = ($key === $defaultServer)
                ? '<span class="green">' . dbx()->esc($texts->get_fd_message('yes')) . '</span>'
                : dbx()->esc($texts->get_fd_message('no'));
            $rdata[]=$record; // make records
        }

        //dbx_debug("##rdata=",$rdata);

       
        $data['dbx_rrows'] =  999;
        $flds['name']    = $texts->get_fd_message('column_server');
        $flds['dbname']  = $texts->get_fd_message('column_database');
        $flds['host']    = $texts->get_fd_message('column_host');
        $flds['type']    = $texts->get_fd_message('column_type');
        $flds['activ_view'] = $texts->get_fd_message('column_status');
        $flds['is_default'] = $texts->get_fd_message('column_default');
        $flds['tables']  = $texts->get_fd_message('column_tables');
        $flds['sync']    = $texts->get_fd_message('column_connected');



        $oReport->init( 'report-server'); 
        $oReport->_fd = 'dbxAdmin|server';
        $oReport->load_fd_messages();
        $oReport->set_form_help_enabled(false);
        $oReport->set_callback_owner($this);
        $oReport->set_callback('row_action_data', 'server_row_action_data');
        $oReport->_create_row_select  = 0;
        $oReport->_create_row_edit    = 1;
        $oReport->_create_row_delete  = 1;
        $oReport->_create_row_show    = 1;
        $oReport->_create_sel_flds    = 0;
        $oReport->_fld_id     = 'name';
        $oReport->_tabel_tpls['tpl_row_edit'] = 'modul|server_row_edit';

        // if ( $nosel ) $oReport->init( 'report-datadic', 'report-datadic-nosel' );
        $oReport->_data        = $data;
        $oReport->_msg_info    = $listMessage;
        $oReport->_msg_success = '';

        $oReport->_rflds = $flds;
        $oReport->_mode = 'table';
        $oReport->_pages = 1;
        $oReport->_rdata = $rdata;

        $newUrl = '?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=new_server&rid=new';
        $bdata['url']    = htmlspecialchars($newUrl, ENT_QUOTES, 'UTF-8');
        $bdata['label']  = $texts->get_fd_message('new_server');
        $bdata['title']  = $texts->get_fd_message('new_server_title');
        $bdata['class']  = 'btn btn-primary btn-sm';
        $bdata['icon']   = 'bi bi-plus-lg';
        $bdata['width']  = '30%';
        $bdata['height'] = '560';
        $newServerBtn = dbx()->get_system_obj('dbxTPL')->get_tpl('modul|server_button_openwin', $bdata);
        $oReport->add_obj('new_server', 'obj-value', $newServerBtn);
        $oReport->add_obj('bar_actions', 'obj-value', $newServerBtn);
        $oReport->add_rep('bar_title', $texts->get_fd_message('bar_title'));
        $oReport->add_rep('bar_subtitle', $texts->get_fd_message('bar_subtitle'));

        $rwhere = dbx()->get_request_var('dbx_rwhere', '', 'sqlsearch');
        $rrows  = dbx()->get_request_var('dbx_rrows', 25, 'int');
        $rpos   = dbx()->get_request_var('dbx_rpos', 0, 'int');

        if ($rrows <= 0) {
            $rrows = 999999;
        }

        $oReport->_rdata = $oReport->data_rows( $rdata, $rpos, $rrows );
        $oReport->_rcount = count( $rdata );
        $content = $oReport->run();

        return $content;
    }

    public function server_row_action_data($report, $data) {
        if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
            return $data;
        }

        $type = (string)($data['type'] ?? '');
        if (!in_array($type, array('edit', 'show', 'delete'), true)) {
            return $data;
        }

        $rid = (string)($data['data']['rid'] ?? '');
        $base = '?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=list_server';

        if ($type === 'edit') {
            $editUrl = '?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=edit_server&rid=' . rawurlencode($rid);
            $data['data']['action'] = $editUrl;
            $data['data']['edit_url'] = htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8');
            $data['data']['edit_title'] = htmlspecialchars(
                $this->server_texts()->format_fd_message('edit_server_title', array('server' => $rid)),
                ENT_QUOTES,
                'UTF-8'
            );
        } elseif ($type === 'show') {
            $data['data']['action'] = $base . '&dbx_run3=row_show&rid=' . rawurlencode($rid);
        } elseif ($type === 'delete') {
            $data['data']['action'] = $base . '&dbx_run3=row_delete&rid=' . rawurlencode($rid);
        }

        return $data;
    }

    Private function delete_table($dd) {
        $oTPL=dbx()->get_system_obj('dbxTPL');
        $oDD =dbx()->get_system_obj('dbxDD'); 
        $tab   =$oDD->get_dd_table($dd);
        $server=$oDD->get_dd_server($dd);
        $data['table']=$tab;
        $sql=$oTPL->get_tpl('modul|del_dd',$data,'sql');
        $dd_file=dbx()->os_path(dbx()->get_base_dir().'dbx/modules/dbx/dd/'.$dd.'.dd.php');
        if (file_exists($dd_file)) unlink($dd_file); 
        $ok =$oDD->rawQuery($server,$sql);
        return $ok;
    }
 
    Private function create_table($table) {
        dbx()->set_modul_var('rid',$table); // need for report fields
        $oTPL=dbx()->get_system_obj('dbxTPL');
        $oDB =dbx()->get_system_obj('dbxDB'); 
        $server = dbx()->get_modul_var('server', dbx()->get_modul_var('rid', '', 'parameter'), 'parameter');
        $data['table']=$table;
        $sql=$oTPL->get_tpl('modul|new_dd',$data,'sql');
        $ok = $server ? $oDB->exec($server, $sql) : 0;
        return $ok;
    }
 

    function add_dbtable() {
        $server=dbx()->get_modul_var('server');
        $content="add db-table server=($server)";



        $content=''; 
        $oDD   = dbx()->get_system_obj('dbxDD');
        $oForm = dbx()->get_system_obj('dbxForm');
        $texts = $this->server_texts();
        $dd    = 'mod:dbtable';

        $oForm->init('form-table');
        $data=array();  

        $oForm->_data      = $data;
        $oForm->_fd        = 'dbxAdmin|server';
        $oForm->load_fd_messages();
        $oForm->_msg_info  = $texts->get_fd_message('create_table_info');
        $oForm->_dd        = $dd; // Main db-Table
        $oForm->_action    = "?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=add_dbtable&server=$server";
        $oForm->_fld_change_state='*'; // get allways all, unchaged too
  
        $oForm->add_fld(
            'table',
            'text-label',
            rules: 'parameter|min=1',
            label: $texts->get_fd_message('label_table')
        );
    
  
    
    
        //public function add_fld($name,$tpl,$data='',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='') { //#
    
    
    
        if($oForm->submit()) {
            if(!$oForm->errors()) {      // submit && no errors && no warnings
             $change=$oForm->changed();
             if ($change) {
                $ok=$oDD->connect_db_server($server);
                if ($ok) {
                    //$database     
                }

                $database=$oDD->get_database($server);
                $table   =dbx()->get_request_var('table');

                $ok=1;

                $messageValues = array(
                    'server' => $server,
                    'database' => $database,
                    'table' => $table,
                );
                if ($ok) {
                    $oForm->_msg_success = $texts->format_fd_message('table_created', $messageValues);
                } else {
                    $oForm->_msg_error = $texts->format_fd_message('table_create_error', $messageValues);
                }

             } else {
               $oForm->_msg_success = $texts->get_fd_message('no_change');
             }
            } else {
   
             $oForm->_msg_error = $texts->get_fd_message('check_input_plain');
          }
        } else {
            $oForm->add_obj('form_msg','obj-value','');
        }
     
        $content=$oForm->run();
    
        return $content;
    



    }






    public function run() {
        $work = dbx()->get_modul_var( 'dbx_run2', 'list', 'parameter' );
        $content = "Unbekannter Aufruf dbx_run2=($work)";
        $server  = dbx()->get_modul_var('rid','','parameter');
        switch ( $work ) {
        
            case 'list_server': 
                $content = $this->report_server();
            break;


            case 'list_tables':
                dbx()->set_modul_var('rid',$server);
                $content = $this->browser_server();
            break;

            case 'edit_server':
                $content =$this->edit_server();
            break;    

            case 'new_server':
                $content =$this->edit_server();
            break;    

            case 'add_dbtable':
                $content =$this->add_dbtable();
            break;

        }
        return $content;
    }

}
// class
