<?php
namespace dbx\dbxAdmin;
dbx()->get_system_obj( 'dbxReport', 'use' );


require_once __DIR__ . '/dbxReport_Tables.class.php';
require_once __DIR__ . '/dbxReport_Server.class.php';

Class dbxServer extends \dbxObj {

    /** @var \dbxForm|null Stabiler sprachabhängiger Textkontext der Server-FD. */
    private $server_texts;

    /**
     * Liefert Server-Texte aus der aktiven Sprachversion der FD.
     *
     * @return \dbxForm
     */
    private function server_texts() {
        if ($this->server_texts) {
            return $this->server_texts;
        }

        dbx()->get_system_obj('dbxForm', 'use');
        $texts = new \dbxForm();
        $texts->set_form_help_enabled(false);
        $texts->set_field_definition('dbxAdmin|server');
        $texts->load_fd_messages();
        $this->server_texts = $texts;

        return $this->server_texts;
    }


    private function browser_server() {
        $uid     = dbx()->user();
        $do      = dbx()->get_modul_var('dbx_run3');
        $server  = dbx()->get_modul_var('rid');
        $texts   = $this->server_texts();

          


        if ($do == 'create_db') return 'create_db';

        $o_dd     = dbx()->get_system_obj('dbxDD');
        $o_report = new dbxReport_Tables;
        $o_report->set_action('?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=list_tables&rid='.$server);
 
        $flds['server']      = $texts->get_fd_message('column_server');
        $flds['name']        = $texts->get_fd_message('column_table');
        $flds['count']       = $texts->get_fd_message('column_count');
        $flds['dd']          = $texts->get_fd_message('column_datadic');
   
 

        $o_report->init('report-tables', 'report-tables');
        $o_report->set_field_definition('dbxAdmin|server');
        $o_report->load_fd_messages();
        $o_report->set_form_help_enabled(false);
        $o_report->add_rep('bar_title', $texts->get_fd_message('column_tables'));
        $o_report->add_rep('bar_subtitle', $texts->format_fd_message('tables_info', array('server' => $server)));
        $o_report->set_table_actions(array(
            'select',
            'edit' => array('window' => true),
            'delete',
            'show' => array('window' => true),
        ));
        $o_report->_create_sel_flds    = 0;
        $o_report->_fld_id     = 'server';

        $o_report->set_table_tpl('tpl_row_delete', 'modul|confirm_row_delete_table');

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
        $o_report->add_obj('new_table','button_modal',$bt);
        $table_btn = dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|button_modal', $bt);
        $o_report->add_obj('bar_actions', 'obj-value', $table_btn);




        
        $data=array(); 
        $rdata=$o_dd->get_db_tables($server);

        //dbx_debug("DATA Tablels Server=($server)",$rdata);

  
        $o_report->set_data($data);
        $o_report->_msg_info    = $texts->format_fd_message('tables_info', array('server' => $server));
        $o_report->_msg_success = '';

        $rwhere = dbx()->get_request_var('dbx_rwhere', '', 'sqlsearch');
        $rrows  = dbx()->get_request_var('dbx_rrows', 9999, 'int');
        $rpos   = dbx()->get_request_var('dbx_rpos', 0, 'int');
        $rpos=0; $rrows=9999;

        $o_report->_rflds = $flds;
        $o_report->set_mode('table');
        $o_report->_pages = 0;
        $o_report->_rrows = $rrows;
        $o_report->_rpos = $rpos;
        $o_report->_rdata = $o_report->data_rows( $rdata, $rpos, $rrows );
        $o_report->_rcount = count( $rdata );

        $content = $o_report->run();




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
        $config_store = dbx()->get_system_obj('dbxConfigStore');
        $content = "<?php \n" . $config_store->export_php_assignments($config, '$config');
        $ok = file_put_contents($file, $content);

        if ($ok) {
            $config_store->remember('dbx', $config, $file);
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
        $o_db = dbx()->get_system_obj('dbxDB');
        $config_store = dbx()->get_system_obj('dbxConfigStore');
        $old_session_config = $config_store->cached('dbx');
        $config_store->remember('dbx', $config);

        if (isset($o_db->db[$server])) {
            unset($o_db->db[$server]);
        }

      $o_db->_db_message = '';
        $o_db->_error = '';
        $ok = $o_db->connect_db_server($server) ? 1 : 0;
      $message = $o_db->_db_message ?: $o_db->_error;

        if (isset($o_db->db[$server])) {
            unset($o_db->db[$server]);
        }

        if ($old_session_config === null) {
            $config_store->forget('dbx');
        } else {
            $config_store->remember('dbx', $old_session_config);
        }

        return array('ok' => $ok, 'message' => $message);
    }

    private function ensure_server_connection(string $server, array $config): array {
        return $this->test_server_connection($server, $config);
    }

    private function save_server_from_form(array $post): array {
        $texts = $this->server_texts();
        $config = $this->get_db_config();
        $old_name = trim((string) ($post['old_name'] ?? ''));
        $new_name = trim((string) ($post['name'] ?? ''));

        if ($new_name === '') {
            return array('ok' => 0, 'message' => $texts->get_fd_message('server_name_missing'));
        }

        if ($old_name !== '' && $old_name !== $new_name && isset($config['db'][$new_name])) {
            return array('ok' => 0, 'message' => $texts->format_fd_message('server_exists', array('server' => $new_name)));
        }

        if ($old_name === '' && isset($config['db'][$new_name])) {
            return array('ok' => 0, 'message' => $texts->format_fd_message('server_exists', array('server' => $new_name)));
        }

        $record = $this->normalize_server_record($post);

        if (strtolower(trim((string) ($record['type'] ?? ''))) === 'sqlite') {
            return array('ok' => 0, 'message' => $texts->get_fd_message('sqlite_config_error'));
        }

        if ($old_name !== '' && $old_name !== $new_name && isset($config['db'][$old_name])) {
            unset($config['db'][$old_name]);
        }

        $config['db'][$new_name] = $record;

        if ((int) ($post['is_default'] ?? 0) === 1 || $this->get_default_server($config) === '') {
            $config['default_server'] = $new_name;
        }

        $test = ((string)($record['activ'] ?? '1') === '0')
            ? array('ok' => 1, 'message' => $texts->get_fd_message('server_disabled_test_skipped'))
            : $this->ensure_server_connection($new_name, $config);

        if (!$test['ok']) {
            $msg = $test['message'] ? $test['message'] : $texts->get_fd_message('connection_failed');
            return array('ok' => 0, 'message' => $msg);
        }

        $ok = $this->write_db_config($config);

        if (!$ok) {
            return array('ok' => 0, 'message' => $texts->get_fd_message('config_save_error'));
        }

        $o_db = dbx()->get_system_obj('dbxDB');
        if ($old_name && isset($o_db->db[$old_name])) {
            unset($o_db->db[$old_name]);
        }
        if (isset($o_db->db[$new_name])) {
            unset($o_db->db[$new_name]);
        }

        return array(
            'ok' => 1,
            'message' => $texts->format_fd_message('config_saved_connected', array('server' => $new_name)),
            'server' => $new_name,
        );
    }

    private function edit_server() {
 
        $content=''; 
        $o_form =dbx()->get_system_obj('dbxForm');
        $texts = $this->server_texts();
        $server=dbx()->get_modul_var('rid' ,'new','parameter');
        if ($server === '') {
            $server = 'new';
        }
        $fd    ='dbxAdmin|server';

        $o_form->init('form-server', 'form-server');
        $config=$this->get_db_config();
        $data=$this->server_form_data($server, $config);

        $o_form->set_data($data);
        $o_form->_msg_info  = $texts->get_fd_message('edit_server');
        $o_form->set_field_definition($fd);
        $o_form->load_fd_messages();
        $o_form->set_action('?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=edit_server&rid='.$server);
        $o_form->_fld_change_state='*'; // get allways all, unchaged too

        $o_form->add_flds();
    
        if($o_form->submit()) {
            if(!$o_form->errors()) {      // submit && no errors && no warnings
                $change=$o_form->changed();
                if ($change) {
                    $save = $this->save_server_from_form($o_form->validated_post());

                    if ($save['ok']) {
                        $server_name = (string)($save['server'] ?? '');
                        dbx()->set_modul_var('dbx_run2', 'list_server');
                        dbx()->set_modul_var('dbx_run3', '');
                        dbx()->set_modul_var('rid', $server_name);
                        return $this->report_server();
                    } else {
                        $o_form->_msg_error = $save['message'];
                    }
                } else {
                    $o_form->_msg_success = $texts->get_fd_message('no_change');
                }
            } else {
             $err_flds='';
             $errors=$o_form->_errors;
             foreach ($errors as $key => $value) {
               $err_flds.=$key.' ';
             }
             $o_form->_msg_error = $texts->format_fd_message('check_input', array('fields' => trim($err_flds)));
          }
        } else {
            $o_form->add_obj('form_msg','obj-value','');
        }
     
        $content=$o_form->run();
    
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
        $o_db =dbx()->get_system_obj('dbxDB');
        $o_tpl=dbx()->get_system_obj('dbxTPL');
        $texts=$this->server_texts();

        if (isset($o_db->db[$server])) {
            unset($o_db->db[$server]);
        }

        $config = dbx()->get_cfg('dbx', 'db');
        $db_config = is_array($config) && isset($config[$server]) && is_array($config[$server]) ? $config[$server] : array();
        if (!$o_db->db_server_config_is_active((string)$server, $db_config)) {
            $msg['msg'] = $texts->format_fd_message('server_disabled', array('server' => $server));
            return $o_tpl->get_tpl('dbx|alert-info', $msg);
        }

        $ok=$o_db->connect_db_server($server);
        if ($ok) {
            $msg['msg'] = $texts->format_fd_message('server_connected', array('server' => $server));
            $content=$o_tpl->get_tpl('dbx|alert-success',$msg);
        } else {
            $msg['msg'] = $texts->format_fd_message('server_connect_error', array('server' => $server))
         . '<br>' . dbx()->esc((string)$o_db->_db_message);
            $content=$o_tpl->get_tpl('dbx|alert-danger',$msg);
        }
        return $content;
    }

    Private function report_server() {

        $o_report = new dbxReport_Server;
        $o_report->set_action('?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=list_server');
        $texts = $this->server_texts();
        $list_message = '';

        $content = "dbxAdmin->DataDic<br>";
        $uid     = dbx()->user();
        $o_dd     = dbx()->get_system_obj('dbxDD');
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
           $list_message = $ok
               ? $texts->format_fd_message('server_deleted', array('server' => $server))
               : $texts->format_fd_message('server_delete_error', array('server' => $server));
        }

        $config=$this->get_db_config();
        $xdata=$config['db'];
        $default_server=$this->get_default_server($config);
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
            $record['is_default'] = ($key === $default_server)
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



        $o_report->init('report-server');
        $o_report->set_field_definition('dbxAdmin|server');
        $o_report->load_fd_messages();
        $o_report->set_form_help_enabled(false);
        $o_report->set_callback_owner($this);
        $o_report->set_callback('row_action_data', 'server_row_action_data');
        $o_report->set_table_actions(array('edit', 'delete', 'show'));
        $o_report->_create_sel_flds    = 0;
        $o_report->_fld_id     = 'name';
        $o_report->set_table_tpl('tpl_row_edit', 'modul|server_row_edit');

        // if ( $nosel ) $oReport->init( 'report-datadic', 'report-datadic-nosel' );
        $o_report->set_data($data);
        $o_report->_msg_info    = $list_message;
        $o_report->_msg_success = '';

        $o_report->_rflds = $flds;
        $o_report->set_mode('table');
        $o_report->_pages = 1;
        $o_report->_rdata = $rdata;

        $new_url = '?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=new_server&rid=new';
        $bdata['url']    = htmlspecialchars($new_url, ENT_QUOTES, 'UTF-8');
        $bdata['label']  = $texts->get_fd_message('new_server');
        $bdata['title']  = $texts->get_fd_message('new_server_title');
        $bdata['class']  = 'btn btn-primary btn-sm';
        $bdata['icon']   = 'bi bi-plus-lg';
        $bdata['width']  = '30%';
        $bdata['height'] = '560';
        $new_server_btn = dbx()->get_system_obj('dbxTPL')->get_tpl('modul|server_button_openwin', $bdata);
        $o_report->add_obj('new_server', 'obj-value', $new_server_btn);
        $o_report->add_obj('bar_actions', 'obj-value', $new_server_btn);
        $o_report->add_rep('bar_title', $texts->get_fd_message('bar_title'));
        $o_report->add_rep('bar_subtitle', $texts->get_fd_message('bar_subtitle'));

        $rwhere = dbx()->get_request_var('dbx_rwhere', '', 'sqlsearch');
        $rrows  = dbx()->get_request_var('dbx_rrows', 25, 'int');
        $rpos   = dbx()->get_request_var('dbx_rpos', 0, 'int');

        if ($rrows <= 0) {
            $rrows = 999999;
        }

        $o_report->_rdata = $o_report->data_rows( $rdata, $rpos, $rrows );
        $o_report->_rcount = count( $rdata );
        $content = $o_report->run();

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
            $edit_url = '?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=edit_server&rid=' . rawurlencode($rid);
            $data['data']['action'] = $edit_url;
            $data['data']['edit_url'] = htmlspecialchars($edit_url, ENT_QUOTES, 'UTF-8');
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

    Private function create_table($table) {
        dbx()->set_modul_var('rid',$table); // need for report fields
        $o_tpl=dbx()->get_system_obj('dbxTPL');
        $o_db =dbx()->get_system_obj('dbxDB'); 
        $server = dbx()->get_modul_var('server', dbx()->get_modul_var('rid', '', 'parameter'), 'parameter');
        $data['table']=$table;
        $sql=$o_tpl->get_tpl('modul|new_dd',$data,'sql');
        $ok = $server ? $o_db->exec($server, $sql) : 0;
        return $ok;
    }
 

    function add_dbtable() {
        $server=dbx()->get_modul_var('server');
        $content="add db-table server=($server)";



        $content=''; 
        $o_dd   = dbx()->get_system_obj('dbxDD');
        $o_form = dbx()->get_system_obj('dbxForm');
        $texts = $this->server_texts();
        $dd    = 'mod:dbtable';

        $o_form->init('form-table', 'form-table');
        $data=array();  

        $o_form->set_data($data);
        $o_form->set_field_definition('dbxAdmin|server');
        $o_form->load_fd_messages();
        $o_form->_msg_info  = $texts->get_fd_message('create_table_info');
        $o_form->set_data_definition($dd); // Main db-Table
        $o_form->set_action("?dbx_modul=dbxAdmin&dbx_run1=server&dbx_run2=add_dbtable&server=$server");
        $o_form->_fld_change_state='*'; // get allways all, unchaged too
  
        $o_form->add_fld(
            'table',
            'text-label',
            rules: 'parameter|min=1',
            label: $texts->get_fd_message('label_table')
        );
    
  
    
    
        //public function add_fld($name,$tpl,$data='',$rules='dd:',$label='dd:',$tooltip='dd:',$msg='dd:',$placeholder='dd:',$class='') { //#
    
    
    
        if($o_form->submit()) {
            if(!$o_form->errors()) {      // submit && no errors && no warnings
             $change=$o_form->changed();
             if ($change) {
                $ok=$o_dd->connect_db_server($server);
                if ($ok) {
                    //$database     
                }

                $database=$o_dd->get_database($server);
                $table   =dbx()->get_request_var('table');

                $ok=1;

                $message_values = array(
                    'server' => $server,
                    'database' => $database,
                    'table' => $table,
                );
                if ($ok) {
                    $o_form->_msg_success = $texts->format_fd_message('table_created', $message_values);
                } else {
                    $o_form->_msg_error = $texts->format_fd_message('table_create_error', $message_values);
                }

             } else {
               $o_form->_msg_success = $texts->get_fd_message('no_change');
             }
            } else {
   
             $o_form->_msg_error = $texts->get_fd_message('check_input_plain');
          }
        } else {
            $o_form->add_obj('form_msg','obj-value','');
        }
     
        $content=$o_form->run();
    
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
