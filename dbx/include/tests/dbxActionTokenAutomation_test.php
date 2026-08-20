<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$_SESSION = array();
$_GET = array();
$_POST = array();

dbx()->set_system_var('dbx_modul', 'testModule');
dbx()->set_system_var('dbx_run1', 'list');
dbx()->set_system_var('dbx_run2', '');
dbx()->set_system_var('dbx_run3', '');

$web = dbx()->get_system_obj('dbxWebApp');

$navigation = '?dbx_modul=testModule&dbx_run1=list&dbx_page=admin';
if (dbx()->action_url($navigation) !== $navigation) {
   $fail('Normale GET-Navigation wurde tokenisiert.', 1);
}

$automatic_delete_url = '?dbx_modul=testModule&dbx_run1=delete&rid=17';
$automatic_secured_delete_url = dbx()->action_url($automatic_delete_url);
$automatic_delete_route = $web->parse_route_url($automatic_secured_delete_url);
$automatic_delete_policy = $web->action_policy_for_url($automatic_secured_delete_url);
$automatic_delete_token = (string)($automatic_delete_route['params']['dbx_token'] ?? '');
if ((string)($automatic_delete_policy['source'] ?? '') !== 'automatic'
    || (string)($automatic_delete_policy['action'] ?? '') !== 'dbxAction.delete'
    || (string)($automatic_delete_policy['bindings']['rid'] ?? '') !== '17'
    || !dbx()->check_action_token(
       (string)($automatic_delete_policy['scope'] ?? ''),
       $automatic_delete_token
    )) {
   $fail('delete plus rid wurde nicht automatisch tokenisiert.', 18);
}

$automatic_save_url = '?dbx_modul=testModule&dbx_run1=edit&dbx_run2=save&rid=23';
$automatic_secured_save_url = dbx()->action_url($automatic_save_url);
$automatic_save_route = $web->parse_route_url($automatic_secured_save_url);
$automatic_save_policy = $web->action_policy_for_url($automatic_secured_save_url);
if ((string)($automatic_save_policy['source'] ?? '') !== 'automatic'
    || (string)($automatic_save_policy['action'] ?? '') !== 'dbxAction.save'
    || !dbx()->check_action_token(
       (string)($automatic_save_policy['scope'] ?? ''),
       (string)($automatic_save_route['params']['dbx_token'] ?? '')
    )) {
   $fail('save plus rid wurde nicht automatisch tokenisiert.', 19);
}

$named_delete_url = '?dbx_modul=testModule&dbx_run1=invoice_delete&rid=25';
$named_delete_policy = $web->action_policy_for_url($named_delete_url);
if ((string)($named_delete_policy['action'] ?? '') !== 'dbxAction.delete') {
   $fail('delete als Aktionsbestandteil wurde nicht erkannt.', 25);
}

$undelete_url = '?dbx_modul=testModule&dbx_run1=undelete&rid=25';
if (dbx()->action_url($undelete_url) !== $undelete_url) {
   $fail('undelete wurde faelschlich als delete erkannt.', 26);
}

$delete_without_rid = '?dbx_modul=testModule&dbx_run1=delete';
if (dbx()->action_url($delete_without_rid) !== $delete_without_rid) {
   $fail('delete ohne RID wurde unnoetig tokenisiert.', 20);
}

$navigation_with_rid = '?dbx_modul=testModule&dbx_run1=form&rid=17';
if (dbx()->action_url($navigation_with_rid) !== $navigation_with_rid) {
   $fail('Normale RID-Navigation wurde tokenisiert.', 21);
}

$changed_automatic_delete_policy = $web->action_policy_for_url(
   '?dbx_modul=testModule&dbx_run1=delete&rid=18'
);
if (dbx()->check_action_token(
   (string)($changed_automatic_delete_policy['scope'] ?? ''),
   $automatic_delete_token
)) {
   $fail('Automatisches Delete-Token ist nicht an die RID gebunden.', 22);
}

$_GET = $automatic_delete_route['params'];
$_POST = array();
dbx()->set_system_var('dbx_modul', 'testModule');
dbx()->set_system_var('dbx_run1', 'delete');
dbx()->set_system_var('dbx_run2', '');
dbx()->set_system_var('dbx_run3', '');
$automatic_current_policy = $web->current_action_policy();
if ((string)($automatic_current_policy['source'] ?? '') !== 'automatic'
    || !$web->current_action_request_is_valid($automatic_current_policy)) {
   $fail('Automatischer RID-Request wird vor dem Modulstart nicht akzeptiert.', 23);
}
unset($_GET['dbx_token']);
$automatic_current_policy = $web->current_action_policy();
if ($web->current_action_request_is_valid($automatic_current_policy)) {
   $fail('Automatischer RID-Request ohne Token wird zentral akzeptiert.', 24);
}

$_GET = array();
dbx()->set_system_var('dbx_run1', 'list');

$delete_url = '?dbx_modul=testModule&dbx_run1=list&dbx_do=row_delete&rid=17';
$secured_delete_url = dbx()->action_url($delete_url);
$delete_route = $web->parse_route_url($secured_delete_url);
$delete_token = (string)($delete_route['params']['dbx_token'] ?? '');
$delete_policy = $web->action_policy_for_url($secured_delete_url);

if (!preg_match('/^[a-f0-9]{64}$/', $delete_token)
    || (string)($delete_policy['action'] ?? '') !== 'dbxReport.row_delete'
    || !dbx()->check_action_token((string)($delete_policy['scope'] ?? ''), $delete_token)) {
   $fail('Standard-Row-Delete wurde nicht kanonisch tokenisiert.', 2);
}

$changed_rid_url = '?dbx_modul=testModule&dbx_run1=list&dbx_do=row_delete&rid=18';
$changed_rid_policy = $web->action_policy_for_url($changed_rid_url);
if (dbx()->check_action_token((string)($changed_rid_policy['scope'] ?? ''), $delete_token)) {
   $fail('Ein Row-Delete-Token ist nicht an die RID gebunden.', 3);
}

$security_store = $_SESSION['dbx']['session']['dbx']['security'] ?? array();
if (!isset($security_store['action_token_secret']) || isset($security_store['action_tokens'])) {
   $fail('Neue Tokens verwenden weiterhin einen Sessioneintrag je Scope.', 4);
}

for ($rid = 1; $rid <= 200; $rid++) {
   dbx()->action_token('test.dynamic.' . $rid);
}
$security_store = $_SESSION['dbx']['session']['dbx']['security'] ?? array();
if (array_key_exists('action_tokens', $security_store)) {
   $fail('Dynamische Scopes vergroessern weiterhin die Sessionablage.', 5);
}

$_SESSION = array();
if (dbx()->check_action_token('missing.scope', '')) {
   $fail('Ein leerer Action-Token wurde akzeptiert.', 6);
}
if (isset($_SESSION['dbx']['session']['dbx']['security']['action_token_secret'])) {
   $fail('Eine leere Token-Pruefung schreibt unnoetig eine Session.', 7);
}

$unknown_token = str_repeat('a', 64);
if (dbx()->check_action_token('missing.scope', $unknown_token)) {
   $fail('Ein unbekannter Action-Token wurde akzeptiert.', 30);
}
if (isset($_SESSION['dbx']['session']['dbx']['security']['action_token_secret'])) {
   $fail('Eine ungueltige Token-Pruefung erzeugt ein neues Session-Secret.', 31);
}

// Eine Modulroute wird einmal deklarativ registriert. Fuer den Test bleibt
// die Konfiguration ausschliesslich im aktuellen Session-Cache.
$dbx_config = dbx()->get_cfg('dbx');
$dbx_config['action_routes'] = array(
   'custom_delete' => array(
      'match' => array(
         'dbx_run1' => 'test_action',
         'dbx_do' => 'remove',
      ),
      'bind' => array('id'),
   ),
);
$_SESSION['dbx']['config']['dbx'] = $dbx_config;

$custom_url = '?dbx_modul=dbx&dbx_run1=test_action&dbx_do=remove&id=44';
$custom_secured_url = dbx()->action_url($custom_url);
$custom_route = $web->parse_route_url($custom_secured_url);
$custom_policy = $web->action_policy_for_url($custom_secured_url);
if ((string)($custom_policy['source'] ?? '') !== 'module'
    || (string)($custom_policy['action'] ?? '') !== 'dbx.custom_delete'
    || !dbx()->check_action_token(
       (string)($custom_policy['scope'] ?? ''),
       (string)($custom_route['params']['dbx_token'] ?? '')
    )) {
   $fail('Deklarierte Modulaktion wird nicht automatisch tokenisiert.', 9);
}

// Aktueller Request: dieselbe zentrale Policy muss vor dem Modulstart gelten.
$current_secured_url = dbx()->action_url($delete_url);
$current_route = $web->parse_route_url($current_secured_url);
$_GET = $current_route['params'];
$_POST = array();
dbx()->set_system_var('dbx_modul', 'testModule');
dbx()->set_system_var('dbx_run1', 'list');
dbx()->set_system_var('dbx_run2', '');
dbx()->set_system_var('dbx_run3', '');

$current_policy = $web->current_action_policy();
if (!$current_policy || !$web->current_action_request_is_valid($current_policy)) {
   $fail('Ein gueltiger aktueller Action-Request wird zentral abgewiesen.', 10);
}

$_GET['dbx_token'] = str_repeat('0', 64);
$current_policy = $web->current_action_policy();
if ($web->current_action_request_is_valid($current_policy)) {
   $fail('Ein falscher aktueller Action-Token wird zentral akzeptiert.', 11);
}

dbx()->get_system_obj('dbxReport', 'load');

class dbxActionTokenAutomationReport extends dbxReport {
   public function configure(string $action): void {
      $this->_action = $action;
      $this->_fid = 'action-token-report';
      $this->_dbx_modul = 'testModule';
   }

   public function rowDeleteData(array $record): array {
      return $this->get_table_row_action_data('delete', $record);
   }

   public function deleteTableButton(): string {
      return $this->get_delete_tab_button();
   }

   public function multiAction(string $code): array {
      return (array)($this->_report_multi_actions[$code] ?? array());
   }

   public function gridUrls(array $urls): array {
      $this->_grid_read_url = (string)($urls['read'] ?? '');
      $this->_grid_save_url = (string)($urls['save'] ?? '');
      $this->_grid_insert_url = (string)($urls['insert'] ?? '');
      $this->_grid_delete_url = (string)($urls['delete'] ?? '');
      $this->_grid_sort_url = (string)($urls['sort'] ?? '');
      $this->_grid_sync_url = (string)($urls['sync'] ?? '');
      return $this->get_grid_replaces();
   }
}

class dbxActionTokenAutomationForm extends dbxForm {
   public function configure(string $action): void {
      $this->init('action-token-form');
      $this->_action = $action;
   }

   public function mergedAction(): string {
      return $this->merge_tpl_data('{action}', 1);
   }
}

dbx()->set_system_var('dbx_modul', 'testModule');
dbx()->set_system_var('dbx_run1', 'list');
dbx()->set_system_var('dbx_run2', '');
dbx()->set_system_var('dbx_run3', '');

$form = new dbxActionTokenAutomationForm();
$form->configure(
   '?dbx_modul=testModule&dbx_run1=edit&dbx_run2=save_record&rid=31'
);
$form_action = $form->mergedAction();
$form_action_route = $web->parse_route_url($form_action);
$form_action_policy = $web->action_policy_for_url($form_action);
if ((string)($form_action_policy['action'] ?? '') !== 'dbxAction.save'
    || !dbx()->check_action_token(
       (string)($form_action_policy['scope'] ?? ''),
       (string)($form_action_route['params']['dbx_token'] ?? '')
    )) {
   $fail('dbxForm tokenisiert erkannte RID-Actions nicht automatisch.', 27);
}

$report = new dbxActionTokenAutomationReport();
$report->configure('?dbx_modul=testModule&dbx_run1=list');
$row_action = $report->rowDeleteData(array('id' => 23));
$row_route = $web->parse_route_url((string)($row_action['action'] ?? ''));
$row_policy = $web->action_policy_for_url((string)($row_action['action'] ?? ''));
if ((string)($row_route['params']['dbx_do'] ?? '') !== 'row_delete'
    || (string)($row_route['params']['rid'] ?? '') !== '23'
    || !dbx()->check_action_token(
       (string)($row_policy['scope'] ?? ''),
       (string)($row_route['params']['dbx_token'] ?? '')
    )) {
   $fail('dbxReport tokenisiert die Row-Delete-URL nicht automatisch.', 12);
}

$report->enable_delete_tab('dbxMissing');
$delete_table_html = html_entity_decode($report->deleteTableButton(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
if (strpos($delete_table_html, 'dbx_do=delete_tab') === false
    || strpos($delete_table_html, 'dbx_token=') === false) {
   $fail('dbxReport tokenisiert delete_tab nicht automatisch.', 13);
}

$admin_delete_url = '?dbx_modul=dbxAdmin&dbx_run1=trace&dbx_run2=list_trace&dbx_do=delete_tab';
$admin_secured_delete_url = dbx()->action_url($admin_delete_url);
$admin_delete_route = $web->parse_route_url($admin_secured_delete_url);
$admin_delete_policy = $web->action_policy_for_url($admin_secured_delete_url);
if ((string)($admin_delete_policy['action'] ?? '') !== 'dbxReport.delete_tab'
    || !dbx()->check_action_token(
       (string)($admin_delete_policy['scope'] ?? ''),
       (string)($admin_delete_route['params']['dbx_token'] ?? '')
    )) {
   $fail('dbxAdmin-Reports verwenden nicht den zentralen delete_tab-Scope.', 28);
}

foreach (array(
   dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxTrace.class.php',
   dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxMissing.class.php',
   dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxSession.class.php',
   dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxSysMsg.class.php',
) as $admin_report_source_file) {
   $admin_report_source = (string)file_get_contents($admin_report_source_file);
   if (strpos($admin_report_source, 'enable_delete_tab(') === false
       || strpos($admin_report_source, 'check_action_token(') !== false
       || strpos($admin_report_source, 'action_token(') !== false) {
      $fail('dbxAdmin-Report dupliziert die zentrale dbxReport-Tokenpruefung.', 29);
   }
}

$sys_msg_source = (string)file_get_contents(
   dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxSysMsg.class.php'
);
if (strpos($sys_msg_source, 'dbx_do=delete_error_log&rid=error_log') === false
    || strpos($sys_msg_source, "dbx()->action_url(") === false) {
   $fail('Das Loeschen des PHP-Error-Logs ist nicht automatisch RID-gebunden signiert.', 34);
}

require_once dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxUser.class.php';
$admin_user = new \dbx\dbxAdmin\dbxUser();
$admin_user_action = new ReflectionMethod($admin_user, 'action_url');
$admin_user_delete_url = (string)$admin_user_action->invoke(
   $admin_user,
   'list_user',
   array('dbx_do' => 'row_delete', 'rid' => 9)
);
$admin_user_delete_route = $web->parse_route_url($admin_user_delete_url);
$admin_user_delete_policy = $web->action_policy_for_url($admin_user_delete_url);
if ((string)($admin_user_delete_policy['action'] ?? '') !== 'dbxReport.row_delete'
    || !dbx()->check_action_token(
       (string)($admin_user_delete_policy['scope'] ?? ''),
       (string)($admin_user_delete_route['params']['dbx_token'] ?? '')
    )) {
   $fail('dbxAdmin-Benutzerloeschung verwendet nicht den zentralen Row-Delete-Scope.', 35);
}

$admin_verify_url9 = (string)$admin_user_action->invoke(
   $admin_user,
   'list_user',
   array('dbx_do' => 'verify', 'rid' => 9)
);
$admin_verify_url10 = (string)$admin_user_action->invoke(
   $admin_user,
   'list_user',
   array('dbx_do' => 'verify', 'rid' => 10)
);
$admin_verify_token9 = (string)(
   $web->parse_route_url($admin_verify_url9)['params']['dbx_token'] ?? ''
);
$admin_verify_token10 = (string)(
   $web->parse_route_url($admin_verify_url10)['params']['dbx_token'] ?? ''
);
if ($admin_verify_token9 === $admin_verify_token10
    || !dbx()->check_action_token('dbxAdmin.user.verify.9', $admin_verify_token9)
    || dbx()->check_action_token('dbxAdmin.user.verify.10', $admin_verify_token9)) {
   $fail('dbxAdmin-Sonderaktionen sind nicht an Aktion und RID gebunden.', 40);
}

require_once dirname(__DIR__, 2) . '/modules/dbxShop_admin/include/dbxShopAdmin.class.php';
$shop_admin = new \dbx\dbxShop_admin\dbxShopAdmin();
$shop_action = new ReflectionMethod($shop_admin, 'action_url');
$shop_delete_url = (string)$shop_action->invoke(
   $shop_admin,
   '?dbx_modul=dbxShop_admin&dbx_run1=products&dbx_do=row_delete&rid=11'
);
$shop_delete_route = $web->parse_route_url($shop_delete_url);
$shop_delete_policy = $web->action_policy_for_url($shop_delete_url);
if ((string)($shop_delete_policy['action'] ?? '') !== 'dbxReport.row_delete'
    || !dbx()->check_action_token(
       (string)($shop_delete_policy['scope'] ?? ''),
       (string)($shop_delete_route['params']['dbx_token'] ?? '')
    )) {
   $fail('dbxShop-Produktloeschung verwendet nicht den zentralen Row-Delete-Scope.', 36);
}

$report->add_action('rows_delete', 'action_button_delete', '&dbx_run2=multi_delete');
$multi_action = $report->multiAction('multi_delete');
$multi_route = $web->parse_route_url((string)($multi_action['action'] ?? ''));
$multi_policy = $web->action_policy_for_url((string)($multi_action['action'] ?? ''));
if (!dbx()->check_action_token(
   (string)($multi_policy['scope'] ?? ''),
   (string)($multi_route['params']['dbx_token'] ?? '')
)) {
   $fail('dbxReport tokenisiert Multi-Delete nicht automatisch.', 14);
}

$grid_base = '?dbx_modul=testModule&dbx_run1=grid';
$grid_urls = $report->gridUrls(array(
   'read' => $grid_base . '&dbx_run2=records_grid_read',
   'save' => $grid_base . '&dbx_run2=records_grid_save',
   'insert' => $grid_base . '&dbx_run2=records_grid_insert',
   'delete' => $grid_base . '&dbx_run2=records_grid_delete',
   'sort' => $grid_base . '&dbx_run2=records_grid_sort',
   'sync' => $grid_base . '&dbx_run2=records_grid_sync',
));
if ((string)($grid_urls['read_url'] ?? '') !== $grid_base . '&dbx_run2=records_grid_read') {
   $fail('Die lesende Grid-URL wurde unnoetig veraendert.', 32);
}
foreach (array('save', 'insert', 'delete', 'sort', 'sync') as $grid_action) {
   $grid_url = (string)($grid_urls[$grid_action . '_url'] ?? '');
   $grid_route = $web->parse_route_url($grid_url);
   $grid_policy = $web->action_policy_for_url($grid_url);
   if (isset($grid_route['params']['dbx_do'])
       || (string)($grid_policy['action'] ?? '') !== 'dbxReport.grid_' . $grid_action
       || !dbx()->check_action_token(
          (string)($grid_policy['scope'] ?? ''),
          (string)($grid_route['params']['dbx_token'] ?? '')
       )) {
      $fail('dbxReport tokenisiert Grid-' . $grid_action . ' nicht zentral.', 33);
   }
}

$unrecognized_grid_urls = $report->gridUrls(array(
   'save' => '?dbx_modul=testModule&dbx_run1=grid&dbx_run2=write',
));
if ((string)($unrecognized_grid_urls['save_url'] ?? '') !== '') {
   $fail('Eine nicht erkennbare schreibende Grid-Route wurde unsigniert ausgegeben.', 39);
}

$direct_grid_route = $web->parse_route_url(
   $grid_base . '&dbx_run2=records_grid_delete'
);
$_GET = $direct_grid_route['params'];
$_POST = array('id' => 77);
dbx()->set_system_var('dbx_modul', 'testModule');
dbx()->set_system_var('dbx_run1', 'grid');
dbx()->set_system_var('dbx_run2', 'records_grid_delete');
$direct_grid_policy = $web->current_action_policy();
if ((string)($direct_grid_policy['action'] ?? '') !== 'dbxReport.grid_delete'
    || $web->current_action_request_is_valid($direct_grid_policy)) {
   $fail('Eine direkt konstruierte Grid-Delete-Anfrage ohne Token wird nicht abgewiesen.', 37);
}
$_GET = array();
$_POST = array();

$real_grid_routes = array(
   '?dbx_modul=dbxAdmin&dbx_run1=dd&dbx_run2=data_save&rid=dbx%7CdbxUser' =>
      'dbxReport.grid_save',
   '?dbx_modul=dbxAdmin&dbx_run1=dd&dbx_run2=fields_insert&modul=dbx&dd=dbxUser' =>
      'dbxReport.grid_insert',
   '?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=content_grid_sync' =>
      'dbxReport.grid_sync',
   '?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=user_grid_delete' =>
      'dbxReport.grid_delete',
);
foreach ($real_grid_routes as $real_grid_url => $expected_grid_action) {
   $real_grid_policy = $web->action_policy_for_url($real_grid_url);
   if ((string)($real_grid_policy['action'] ?? '') !== $expected_grid_action) {
      $fail('Reale Grid-Route wird nicht erkannt: ' . $real_grid_url, 38);
   }
}

$token_before_auth_change = dbx()->action_token('auth.boundary');
dbx()->invalidate_action_tokens();
$token_after_auth_change = dbx()->action_token('auth.boundary');
if ($token_before_auth_change === $token_after_auth_change
    || dbx()->check_action_token('auth.boundary', $token_before_auth_change)
) {
   $fail('Ein Action-Token ueberlebt den Sicherheitskontextwechsel.', 15);
}

$session_source = (string)file_get_contents(
   dirname(__DIR__) . '/dbxSession.class.php'
);
if (substr_count($session_source, 'dbx()->invalidate_action_tokens();') < 2) {
   $fail('Login und Logout invalidieren Action-Tokens nicht beide.', 16);
}

$web_app_source = (string)file_get_contents(
   dirname(__DIR__) . '/dbxWebApp.class.php'
);
$run_source = substr(
   $web_app_source,
   (int)strrpos($web_app_source, 'function run()')
);
$policy_position = strpos($run_source, '$action_policy = $this->current_action_policy();');
$module_position = strpos($run_source, '$dbx_modul=dbx()->get_modul_obj($modul);');
if ($policy_position === false
    || $module_position === false
    || $policy_position >= $module_position
) {
   $fail('Die zentrale Action-Pruefung laeuft nicht vor dem Modulcode.', 17);
}

echo "OK dbx action token automation\n";
