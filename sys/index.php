<?php
/**
 * dbXwebApp
 *
 * @package           dbXapp
 * @author            Armin Leonard Braun
 * @copyright         2021 dbXwebApp Org
 * @license           GPL-2.0-or-later
 *
 * Description:       dbXwebApp  (Content, Applications, Views, Forms, Tabels, Grids)
 * Version:           8.5.3.4.1 (dbXapp)
 * Requires PHP:      8.x 
 * Author:            Armin L. Braun
 * Author URI:        https://www.dbxwebapp.org/ArminBraun
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

ob_start("ob_gzhandler");     // Compression


session_set_cookie_params([
  'lifetime' => 0,        // Dauer des Cookies, 0 bedeutet bis der Browser geschlossen wird
  'path' => '/',          // Gültiger Pfad für das Cookie
  'domain' => '',         // Optional: Das Domain-Feld kann leer gelassen werden, wenn nicht spezifisch
  'secure' => false,      // Hier auf false setzen, um den Cookie auch bei HTTP zu übertragen
  'httponly' => false,    // Empfohlen: Verhindert Zugriff über JavaScript (nur über HTTP)
  'samesite' => 'Lax'     // Optional: Verhindert das Senden bei Cross-Site-Requests
]);
//ini_set('session.use_cookies', '0');
//ini_set('session.use_only_cookies', '0');
//ini_set('session.use_trans_sid', '1');
session_start();




define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin'  ,0);
error_reporting(E_ALL );
date_default_timezone_set('Europe/Berlin');
//error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
//ini_set('display_errors', 'Off');



function dbx_get_base_dir() { 
  $path= (str_replace('\\','/',__DIR__)).'/';
  return $path; 
};

function dbx_os_path_file($path_file) {
  // allways return unix path 
  return str_replace('\\', '/', $path_file);
  //return $path_file; 
  //return str_replace('/', DIRECTORY_SEPARATOR, $path_file);
}

function dbx_get_file_dir() {
  $path=dbx_get_base_dir().'files/';
  return $path;
}


$path_file=dbx_get_base_dir().'dbx/include/dbxKernel.php';
include_once dbx_os_path_file($path_file);



function run_dbXwebApp() {
  $session_id = session_id();
  $page_content='';
  dbx_debug("#### Session #### PHP-ID=($session_id)");




  dbx_run_time('system','full-app');
  dbx_run_time('system-load','load Kernel');

 

  $oWebApp      = dbx_get_sys_object('dbxWebApp');
  $oSession     = dbx_get_sys_object('dbxSession');
  $oInterpreter = dbx_get_sys_object('dbxInterpreter');
  dbx_run_time('system-load');

  dbx_run_time('session-load','Session load');
  $oSession->load_session();
  dbx_run_time('session-load');
  
  dbx_run_time('system-check','System check');
  dbx_set_SysVar('dbx_activ_modul','dbx');     // dbx_set_remember use dbx_activ_modul
  

  $oWebApp->check_request();  // set sysvar: base_url base_uri permalink self_url
  $oWebApp->check_remember(); // edit color design
  $oWebApp->check_lng();      // set sysvar: dbx_lng !Important for check_perma() 
  $oWebApp->check_missing();
  $oWebApp->check_perma();

  $oWebApp->check_config();
  $oWebApp->check_design();    // set design
  $oWebApp->check_modul();     // set modul


  $self  =dbx_get_self_url();
  $base  =dbx_get_base_url();
  $uid   =dbx_get_CurrentUser();

  $cache =dbx_get_cfg('dbx','cache');
  $perma =dbx_get_SysVar('dbx_permalink','undeff'); 
  $modul =dbx_get_SysVar('dbx_modul'    ,'undeff');
  $action=dbx_get_SysVar('dbx_action'   ,'undeff');
  dbx_debug("#DBX RUN Base-URL($base) Self=($self) Perma ($perma) User=($uid) SYS CACHE=($cache) "); 

  dbx_run_time('system-check'); 

  dbx_run_time('modul-run','Master Modul-run()'); 
  $modul_content=$oWebApp->run();
  dbx_run_time('modul-run');

  dbx_run_time('page-load','Page-Load'); 

  $sync=dbx_get_PostGetVar('dbx_sync',1,'parameter');
  if ($sync=='false') $sync=0;
  //dbx_debug("#RUN-DBXWEBAPP SYNC=$sync");
  if ($sync) {

    //dbx_debug("sync content");
    $page_content =$oWebApp->design_load($modul_content);

    dbx_run_time('interpreter','Interpreter');
    $page_content=$oInterpreter->run($page_content);
    dbx_run_time('interpreter');

    //dbx_debug('######TRANSLATE#######');
    //$page_content=$oWebApp->translate($page_content);

    $page_content=$oWebApp->add_norep($page_content);
    // - - - - - - - - - - - - - -
    //$page_content=$oWebApp->out_filter($page_content));
    $page_content=$oWebApp->out_filter($page_content);
  } else {
    dbx_debug("no sync no output");
    http_response_code(204); // Kein Inhalt
  }
  dbx_run_time('page-load');
  if ($sync) $oSession->save_session();
  $oSession->clean_session();  
  dbx_run_time('system');
  return $page_content;
}

$response=run_dbXwebApp();
echo $response; // the one and onley Point  
while (@ob_end_flush());

dbx_debug_run_timer(3); // wenn es länger als 3 sec dauert dann debug timer für Analyse 
dbx_debug("#ENDE");

exit;