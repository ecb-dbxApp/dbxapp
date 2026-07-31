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

$automaticDeleteUrl = '?dbx_modul=testModule&dbx_run1=delete&rid=17';
$automaticSecuredDeleteUrl = dbx()->action_url($automaticDeleteUrl);
$automaticDeleteRoute = $web->parse_route_url($automaticSecuredDeleteUrl);
$automaticDeletePolicy = $web->action_policy_for_url($automaticSecuredDeleteUrl);
$automaticDeleteToken = (string)($automaticDeleteRoute['params']['dbx_token'] ?? '');
if ((string)($automaticDeletePolicy['source'] ?? '') !== 'automatic'
    || (string)($automaticDeletePolicy['action'] ?? '') !== 'dbxAction.delete'
    || (string)($automaticDeletePolicy['bindings']['rid'] ?? '') !== '17'
    || !dbx()->check_action_token(
       (string)($automaticDeletePolicy['scope'] ?? ''),
       $automaticDeleteToken
    )) {
   $fail('delete plus rid wurde nicht automatisch tokenisiert.', 18);
}

$automaticSaveUrl = '?dbx_modul=testModule&dbx_run1=edit&dbx_run2=save&rid=23';
$automaticSecuredSaveUrl = dbx()->action_url($automaticSaveUrl);
$automaticSaveRoute = $web->parse_route_url($automaticSecuredSaveUrl);
$automaticSavePolicy = $web->action_policy_for_url($automaticSecuredSaveUrl);
if ((string)($automaticSavePolicy['source'] ?? '') !== 'automatic'
    || (string)($automaticSavePolicy['action'] ?? '') !== 'dbxAction.save'
    || !dbx()->check_action_token(
       (string)($automaticSavePolicy['scope'] ?? ''),
       (string)($automaticSaveRoute['params']['dbx_token'] ?? '')
    )) {
   $fail('save plus rid wurde nicht automatisch tokenisiert.', 19);
}

$namedDeleteUrl = '?dbx_modul=testModule&dbx_run1=invoice_delete&rid=25';
$namedDeletePolicy = $web->action_policy_for_url($namedDeleteUrl);
if ((string)($namedDeletePolicy['action'] ?? '') !== 'dbxAction.delete') {
   $fail('delete als Aktionsbestandteil wurde nicht erkannt.', 25);
}

$undeleteUrl = '?dbx_modul=testModule&dbx_run1=undelete&rid=25';
if (dbx()->action_url($undeleteUrl) !== $undeleteUrl) {
   $fail('undelete wurde faelschlich als delete erkannt.', 26);
}

$deleteWithoutRid = '?dbx_modul=testModule&dbx_run1=delete';
if (dbx()->action_url($deleteWithoutRid) !== $deleteWithoutRid) {
   $fail('delete ohne RID wurde unnoetig tokenisiert.', 20);
}

$navigationWithRid = '?dbx_modul=testModule&dbx_run1=form&rid=17';
if (dbx()->action_url($navigationWithRid) !== $navigationWithRid) {
   $fail('Normale RID-Navigation wurde tokenisiert.', 21);
}

$changedAutomaticDeletePolicy = $web->action_policy_for_url(
   '?dbx_modul=testModule&dbx_run1=delete&rid=18'
);
if (dbx()->check_action_token(
   (string)($changedAutomaticDeletePolicy['scope'] ?? ''),
   $automaticDeleteToken
)) {
   $fail('Automatisches Delete-Token ist nicht an die RID gebunden.', 22);
}

$_GET = $automaticDeleteRoute['params'];
$_POST = array();
dbx()->set_system_var('dbx_modul', 'testModule');
dbx()->set_system_var('dbx_run1', 'delete');
dbx()->set_system_var('dbx_run2', '');
dbx()->set_system_var('dbx_run3', '');
$automaticCurrentPolicy = $web->current_action_policy();
if ((string)($automaticCurrentPolicy['source'] ?? '') !== 'automatic'
    || !$web->current_action_request_is_valid($automaticCurrentPolicy)) {
   $fail('Automatischer RID-Request wird vor dem Modulstart nicht akzeptiert.', 23);
}
unset($_GET['dbx_token']);
$automaticCurrentPolicy = $web->current_action_policy();
if ($web->current_action_request_is_valid($automaticCurrentPolicy)) {
   $fail('Automatischer RID-Request ohne Token wird zentral akzeptiert.', 24);
}

$_GET = array();
dbx()->set_system_var('dbx_run1', 'list');

$deleteUrl = '?dbx_modul=testModule&dbx_run1=list&dbx_do=row_delete&rid=17';
$securedDeleteUrl = dbx()->action_url($deleteUrl);
$deleteRoute = $web->parse_route_url($securedDeleteUrl);
$deleteToken = (string)($deleteRoute['params']['dbx_token'] ?? '');
$deletePolicy = $web->action_policy_for_url($securedDeleteUrl);

if (!preg_match('/^[a-f0-9]{64}$/', $deleteToken)
    || (string)($deletePolicy['action'] ?? '') !== 'dbxReport.row_delete'
    || !dbx()->check_action_token((string)($deletePolicy['scope'] ?? ''), $deleteToken)) {
   $fail('Standard-Row-Delete wurde nicht kanonisch tokenisiert.', 2);
}

$changedRidUrl = '?dbx_modul=testModule&dbx_run1=list&dbx_do=row_delete&rid=18';
$changedRidPolicy = $web->action_policy_for_url($changedRidUrl);
if (dbx()->check_action_token((string)($changedRidPolicy['scope'] ?? ''), $deleteToken)) {
   $fail('Ein Row-Delete-Token ist nicht an die RID gebunden.', 3);
}

$securityStore = $_SESSION['dbx']['session']['dbx']['security'] ?? array();
if (!isset($securityStore['action_token_secret']) || isset($securityStore['action_tokens'])) {
   $fail('Neue Tokens verwenden weiterhin einen Sessioneintrag je Scope.', 4);
}

for ($rid = 1; $rid <= 200; $rid++) {
   dbx()->action_token('test.dynamic.' . $rid);
}
$securityStore = $_SESSION['dbx']['session']['dbx']['security'] ?? array();
if (array_key_exists('action_tokens', $securityStore)) {
   $fail('Dynamische Scopes vergroessern weiterhin die Sessionablage.', 5);
}

$_SESSION = array();
if (dbx()->check_action_token('missing.scope', '')) {
   $fail('Ein leerer Action-Token wurde akzeptiert.', 6);
}
if (isset($_SESSION['dbx']['session']['dbx']['security']['action_token_secret'])) {
   $fail('Eine leere Token-Pruefung schreibt unnoetig eine Session.', 7);
}

$unknownToken = str_repeat('a', 64);
if (dbx()->check_action_token('missing.scope', $unknownToken)) {
   $fail('Ein unbekannter Action-Token wurde akzeptiert.', 30);
}
if (isset($_SESSION['dbx']['session']['dbx']['security']['action_token_secret'])) {
   $fail('Eine ungueltige Token-Pruefung erzeugt ein neues Session-Secret.', 31);
}

$legacyToken = bin2hex(random_bytes(32));
dbx()->set_session_var(
   'action_tokens',
   array('legacy.scope' => $legacyToken),
   'security',
   'dbx'
);
if (!dbx()->check_action_token('legacy.scope', $legacyToken)) {
   $fail('Ein bereits gerenderter Legacy-Link ist nicht mehr kompatibel.', 8);
}

// Eine Modulroute wird einmal deklarativ registriert. Fuer den Test bleibt
// die Konfiguration ausschliesslich im aktuellen Session-Cache.
$dbxConfig = dbx()->get_config('dbx');
$dbxConfig['action_routes'] = array(
   'custom_delete' => array(
      'match' => array(
         'dbx_run1' => 'test_action',
         'dbx_do' => 'remove',
      ),
      'bind' => array('id'),
   ),
);
$_SESSION['dbx']['config']['dbx'] = $dbxConfig;

$customUrl = '?dbx_modul=dbx&dbx_run1=test_action&dbx_do=remove&id=44';
$customSecuredUrl = dbx()->action_url($customUrl);
$customRoute = $web->parse_route_url($customSecuredUrl);
$customPolicy = $web->action_policy_for_url($customSecuredUrl);
if ((string)($customPolicy['source'] ?? '') !== 'module'
    || (string)($customPolicy['action'] ?? '') !== 'dbx.custom_delete'
    || !dbx()->check_action_token(
       (string)($customPolicy['scope'] ?? ''),
       (string)($customRoute['params']['dbx_token'] ?? '')
    )) {
   $fail('Deklarierte Modulaktion wird nicht automatisch tokenisiert.', 9);
}

// Aktueller Request: dieselbe zentrale Policy muss vor dem Modulstart gelten.
$currentSecuredUrl = dbx()->action_url($deleteUrl);
$currentRoute = $web->parse_route_url($currentSecuredUrl);
$_GET = $currentRoute['params'];
$_POST = array();
dbx()->set_system_var('dbx_modul', 'testModule');
dbx()->set_system_var('dbx_run1', 'list');
dbx()->set_system_var('dbx_run2', '');
dbx()->set_system_var('dbx_run3', '');

$currentPolicy = $web->current_action_policy();
if (!$currentPolicy || !$web->current_action_request_is_valid($currentPolicy)) {
   $fail('Ein gueltiger aktueller Action-Request wird zentral abgewiesen.', 10);
}

$_GET['dbx_token'] = str_repeat('0', 64);
$currentPolicy = $web->current_action_policy();
if ($web->current_action_request_is_valid($currentPolicy)) {
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
$formAction = $form->mergedAction();
$formActionRoute = $web->parse_route_url($formAction);
$formActionPolicy = $web->action_policy_for_url($formAction);
if ((string)($formActionPolicy['action'] ?? '') !== 'dbxAction.save'
    || !dbx()->check_action_token(
       (string)($formActionPolicy['scope'] ?? ''),
       (string)($formActionRoute['params']['dbx_token'] ?? '')
    )) {
   $fail('dbxForm tokenisiert erkannte RID-Actions nicht automatisch.', 27);
}

$report = new dbxActionTokenAutomationReport();
$report->configure('?dbx_modul=testModule&dbx_run1=list');
$rowAction = $report->rowDeleteData(array('id' => 23));
$rowRoute = $web->parse_route_url((string)($rowAction['action'] ?? ''));
$rowPolicy = $web->action_policy_for_url((string)($rowAction['action'] ?? ''));
if ((string)($rowRoute['params']['dbx_do'] ?? '') !== 'row_delete'
    || (string)($rowRoute['params']['rid'] ?? '') !== '23'
    || !dbx()->check_action_token(
       (string)($rowPolicy['scope'] ?? ''),
       (string)($rowRoute['params']['dbx_token'] ?? '')
    )) {
   $fail('dbxReport tokenisiert die Row-Delete-URL nicht automatisch.', 12);
}

$report->enable_delete_tab('dbxMissing');
$deleteTableHtml = html_entity_decode($report->deleteTableButton(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
if (strpos($deleteTableHtml, 'dbx_do=delete_tab') === false
    || strpos($deleteTableHtml, 'dbx_token=') === false) {
   $fail('dbxReport tokenisiert delete_tab nicht automatisch.', 13);
}

$adminDeleteUrl = '?dbx_modul=dbxAdmin&dbx_run1=trace&dbx_run2=list_trace&dbx_do=delete_tab';
$adminSecuredDeleteUrl = dbx()->action_url($adminDeleteUrl);
$adminDeleteRoute = $web->parse_route_url($adminSecuredDeleteUrl);
$adminDeletePolicy = $web->action_policy_for_url($adminSecuredDeleteUrl);
if ((string)($adminDeletePolicy['action'] ?? '') !== 'dbxReport.delete_tab'
    || !dbx()->check_action_token(
       (string)($adminDeletePolicy['scope'] ?? ''),
       (string)($adminDeleteRoute['params']['dbx_token'] ?? '')
    )) {
   $fail('dbxAdmin-Reports verwenden nicht den zentralen delete_tab-Scope.', 28);
}

foreach (array(
   dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxTrace.class.php',
   dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxMissing.class.php',
   dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxSession.class.php',
   dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxSysMsg.class.php',
) as $adminReportSourceFile) {
   $adminReportSource = (string)file_get_contents($adminReportSourceFile);
   if (strpos($adminReportSource, 'enable_delete_tab(') === false
       || strpos($adminReportSource, 'check_action_token(') !== false
       || strpos($adminReportSource, 'action_token(') !== false) {
      $fail('dbxAdmin-Report dupliziert die zentrale dbxReport-Tokenpruefung.', 29);
   }
}

$sysMsgSource = (string)file_get_contents(
   dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxSysMsg.class.php'
);
if (strpos($sysMsgSource, 'dbx_do=delete_error_log&rid=error_log') === false
    || strpos($sysMsgSource, "dbx()->action_url(") === false) {
   $fail('Das Loeschen des PHP-Error-Logs ist nicht automatisch RID-gebunden signiert.', 34);
}

require_once dirname(__DIR__, 2) . '/modules/dbxAdmin/include/dbxUser.class.php';
$adminUser = new \dbx\dbxAdmin\dbxUser();
$adminUserAction = new ReflectionMethod($adminUser, 'action_url');
$adminUserDeleteUrl = (string)$adminUserAction->invoke(
   $adminUser,
   'list_user',
   array('dbx_do' => 'row_delete', 'rid' => 9)
);
$adminUserDeleteRoute = $web->parse_route_url($adminUserDeleteUrl);
$adminUserDeletePolicy = $web->action_policy_for_url($adminUserDeleteUrl);
if ((string)($adminUserDeletePolicy['action'] ?? '') !== 'dbxReport.row_delete'
    || !dbx()->check_action_token(
       (string)($adminUserDeletePolicy['scope'] ?? ''),
       (string)($adminUserDeleteRoute['params']['dbx_token'] ?? '')
    )) {
   $fail('dbxAdmin-Benutzerloeschung verwendet nicht den zentralen Row-Delete-Scope.', 35);
}

$adminVerifyUrl9 = (string)$adminUserAction->invoke(
   $adminUser,
   'list_user',
   array('dbx_do' => 'verify', 'rid' => 9)
);
$adminVerifyUrl10 = (string)$adminUserAction->invoke(
   $adminUser,
   'list_user',
   array('dbx_do' => 'verify', 'rid' => 10)
);
$adminVerifyToken9 = (string)(
   $web->parse_route_url($adminVerifyUrl9)['params']['dbx_token'] ?? ''
);
$adminVerifyToken10 = (string)(
   $web->parse_route_url($adminVerifyUrl10)['params']['dbx_token'] ?? ''
);
if ($adminVerifyToken9 === $adminVerifyToken10
    || !dbx()->check_action_token('dbxAdmin.user.verify.9', $adminVerifyToken9)
    || dbx()->check_action_token('dbxAdmin.user.verify.10', $adminVerifyToken9)) {
   $fail('dbxAdmin-Sonderaktionen sind nicht an Aktion und RID gebunden.', 40);
}

require_once dirname(__DIR__, 2) . '/modules/dbxShop_admin/include/dbxShopAdmin.class.php';
$shopAdmin = new \dbx\dbxShop_admin\dbxShopAdmin();
$shopAction = new ReflectionMethod($shopAdmin, 'actionUrl');
$shopDeleteUrl = (string)$shopAction->invoke(
   $shopAdmin,
   '?dbx_modul=dbxShop_admin&dbx_run1=products&dbx_do=row_delete&rid=11'
);
$shopDeleteRoute = $web->parse_route_url($shopDeleteUrl);
$shopDeletePolicy = $web->action_policy_for_url($shopDeleteUrl);
if ((string)($shopDeletePolicy['action'] ?? '') !== 'dbxReport.row_delete'
    || !dbx()->check_action_token(
       (string)($shopDeletePolicy['scope'] ?? ''),
       (string)($shopDeleteRoute['params']['dbx_token'] ?? '')
    )) {
   $fail('dbxShop-Produktloeschung verwendet nicht den zentralen Row-Delete-Scope.', 36);
}

$report->add_action('rows_delete', 'action_button_delete', '&dbx_run2=multi_delete');
$multiAction = $report->multiAction('multi_delete');
$multiRoute = $web->parse_route_url((string)($multiAction['action'] ?? ''));
$multiPolicy = $web->action_policy_for_url((string)($multiAction['action'] ?? ''));
if (!dbx()->check_action_token(
   (string)($multiPolicy['scope'] ?? ''),
   (string)($multiRoute['params']['dbx_token'] ?? '')
)) {
   $fail('dbxReport tokenisiert Multi-Delete nicht automatisch.', 14);
}

$gridBase = '?dbx_modul=testModule&dbx_run1=grid';
$gridUrls = $report->gridUrls(array(
   'read' => $gridBase . '&dbx_run2=records_grid_read',
   'save' => $gridBase . '&dbx_run2=records_grid_save',
   'insert' => $gridBase . '&dbx_run2=records_grid_insert',
   'delete' => $gridBase . '&dbx_run2=records_grid_delete',
   'sort' => $gridBase . '&dbx_run2=records_grid_sort',
   'sync' => $gridBase . '&dbx_run2=records_grid_sync',
));
if ((string)($gridUrls['read_url'] ?? '') !== $gridBase . '&dbx_run2=records_grid_read') {
   $fail('Die lesende Grid-URL wurde unnoetig veraendert.', 32);
}
foreach (array('save', 'insert', 'delete', 'sort', 'sync') as $gridAction) {
   $gridUrl = (string)($gridUrls[$gridAction . '_url'] ?? '');
   $gridRoute = $web->parse_route_url($gridUrl);
   $gridPolicy = $web->action_policy_for_url($gridUrl);
   if (isset($gridRoute['params']['dbx_do'])
       || (string)($gridPolicy['action'] ?? '') !== 'dbxReport.grid_' . $gridAction
       || !dbx()->check_action_token(
          (string)($gridPolicy['scope'] ?? ''),
          (string)($gridRoute['params']['dbx_token'] ?? '')
       )) {
      $fail('dbxReport tokenisiert Grid-' . $gridAction . ' nicht zentral.', 33);
   }
}

$unrecognizedGridUrls = $report->gridUrls(array(
   'save' => '?dbx_modul=testModule&dbx_run1=grid&dbx_run2=write',
));
if ((string)($unrecognizedGridUrls['save_url'] ?? '') !== '') {
   $fail('Eine nicht erkennbare schreibende Grid-Route wurde unsigniert ausgegeben.', 39);
}

$directGridRoute = $web->parse_route_url(
   $gridBase . '&dbx_run2=records_grid_delete'
);
$_GET = $directGridRoute['params'];
$_POST = array('id' => 77);
dbx()->set_system_var('dbx_modul', 'testModule');
dbx()->set_system_var('dbx_run1', 'grid');
dbx()->set_system_var('dbx_run2', 'records_grid_delete');
$directGridPolicy = $web->current_action_policy();
if ((string)($directGridPolicy['action'] ?? '') !== 'dbxReport.grid_delete'
    || $web->current_action_request_is_valid($directGridPolicy)) {
   $fail('Eine direkt konstruierte Grid-Delete-Anfrage ohne Token wird nicht abgewiesen.', 37);
}
$_GET = array();
$_POST = array();

$realGridRoutes = array(
   '?dbx_modul=dbxAdmin&dbx_run1=dd&dbx_run2=data_save&rid=dbx%7CdbxUser' =>
      'dbxReport.grid_save',
   '?dbx_modul=dbxAdmin&dbx_run1=dd&dbx_run2=fields_insert&modul=dbx&dd=dbxUser' =>
      'dbxReport.grid_insert',
   '?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=content_grid_sync' =>
      'dbxReport.grid_sync',
   '?dbx_modul=dbxUser_admin&dbx_run1=user&dbx_run2=user_grid_delete' =>
      'dbxReport.grid_delete',
);
foreach ($realGridRoutes as $realGridUrl => $expectedGridAction) {
   $realGridPolicy = $web->action_policy_for_url($realGridUrl);
   if ((string)($realGridPolicy['action'] ?? '') !== $expectedGridAction) {
      $fail('Reale Grid-Route wird nicht erkannt: ' . $realGridUrl, 38);
   }
}

$tokenBeforeAuthChange = dbx()->action_token('auth.boundary');
dbx()->invalidate_action_tokens();
$tokenAfterAuthChange = dbx()->action_token('auth.boundary');
if ($tokenBeforeAuthChange === $tokenAfterAuthChange
    || dbx()->check_action_token('auth.boundary', $tokenBeforeAuthChange)
) {
   $fail('Ein Action-Token ueberlebt den Sicherheitskontextwechsel.', 15);
}

$sessionSource = (string)file_get_contents(
   dirname(__DIR__) . '/dbxSession.class.php'
);
if (substr_count($sessionSource, 'dbx()->invalidate_action_tokens();') < 2) {
   $fail('Login und Logout invalidieren Action-Tokens nicht beide.', 16);
}

$webAppSource = (string)file_get_contents(
   dirname(__DIR__) . '/dbxWebApp.class.php'
);
$runSource = substr(
   $webAppSource,
   (int)strrpos($webAppSource, 'function run()')
);
$policyPosition = strpos($runSource, '$actionPolicy = $this->current_action_policy();');
$modulePosition = strpos($runSource, '$dbxModul=dbx()->get_modul_obj($modul);');
if ($policyPosition === false
    || $modulePosition === false
    || $policyPosition >= $modulePosition
) {
   $fail('Die zentrale Action-Pruefung laeuft nicht vor dem Modulcode.', 17);
}

echo "OK dbx action token automation\n";
