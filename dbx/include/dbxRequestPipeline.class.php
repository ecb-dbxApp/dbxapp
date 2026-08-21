<?php

declare(strict_types=1);

/**
 * Orchestriert einen vollständigen HTTP-Request vom Sessionstart bis zur Response.
 *
 * Fachlogik verbleibt in WebApp, Session, Runtime und Content-Cache. Die Pipeline
 * beschreibt ausschließlich deren feste Reihenfolge und hält den Frontcontroller
 * sowie die öffentliche dbxApi frei von Ablaufdetails.
 */
final class dbxRequestPipeline {

   /** Führt den kompletten Frontcontroller-Ablauf aus und schreibt die Response. */
   public function run(): void {
      $api = dbx();
      $response = $this->render_web_app();
      $sync_request = (int)$api->get_request_var('dbx_sync', 1, 'int') === 1;

      if ($sync_request
         && class_exists('\\dbx\\dbxContent\\dbxContentPageCache', false)
         && \dbx\dbxContent\dbxContentPageCache::is_prepared_full_page_request()) {
         $stored = \dbx\dbxContent\dbxContentPageCache::write_full_page($response);
         $api->debug($stored
            ? '#FULL-PAGE-CACHE stored exact final guest response'
            : '#FULL-PAGE-CACHE final response not stored');
      }

      $session = $api->get_system_obj('dbxSession');
      $api->debug("call session_save($sync_request)");
      $discarded_ephemeral_session = method_exists($session, 'discard_ephemeral_anonymous_session')
         && $session->discard_ephemeral_anonymous_session(false);
      if (!$discarded_ephemeral_session) {
         $session->save_session();
         $session->clean_session();
         if (!empty($GLOBALS['dbx_session_destroy_pending'])) {
            $session->destroy_php_session();
         }
      }

      if ($sync_request) {
         $api->get_system_obj('dbxRuntime')->send_headers();
      }
      $this->emit_http_response_body($response);

      while (ob_get_level() > 0) {
         if (!@ob_end_flush()) {
            break;
         }
      }

      $runtime = $api->get_system_obj('dbxRuntime');
      $runtime->debug_timer(0);
      $runtime->store_performance_timer();
      $api->debug('#END#');
   }

   /** Rendert den eigentlichen dbXapp-Request bis zur finalen Response. */
   private function render_web_app(): string {
      $api = dbx();
      $page_content = '';
      $api->debug('#### Session #### PHP-ID=(' . session_id() . ')');

      $api->timer('system', 'full-app');
      $api->timer('system-load', 'load Kernel');
      $web_app = $api->get_system_obj('dbxWebApp');
      $session = $api->get_system_obj('dbxSession');
      $interpreter = null;
      $api->timer('system-load');

      $api->timer('session-load', 'Session load');
      $session->load_session();
      $api->timer('session-load');

      $api->timer('system-check', 'System check');
      $api->set_system_var('dbx_activ_modul', 'dbx');
      $web_app->check_request();
      $web_app->check_remember();
      $web_app->check_config();
      $web_app->check_lng();
      $sync = $api->get_request_var('dbx_sync', 1, 'int');
      $install_mode = (int)$api->get_system_var('dbx_install', 0, 'int') === 1;

      if (!$install_mode) {
         if ($web_app->apply_canonical_home_redirect()
            || $web_app->apply_content_permalink_redirect()) {
            $api->timer('system-check');
            $api->timer('system');
            return '';
         }
         if ($web_app->check_missing()) {
            exit;
         }

         $api->load_content_cache_classes();
         if (\dbx\dbxContent\dbxContentPageCache::prepare_full_page_request()) {
            $cached_page = \dbx\dbxContent\dbxContentPageCache::read_full_page();
            if ($cached_page !== null) {
               $this->serve_full_page_cache_hit($cached_page);
            }
         }
         $web_app->check_perma();
         if ($web_app->apply_canonical_content_redirect()
            || $web_app->apply_missing_permalink_redirect()) {
            $api->timer('system-check');
            $api->timer('system');
            return '';
         }
         \dbx\dbxContent\dbxContentPageCache::attach_resolved_content_route();
      } else {
         $api->set_system_var('dbx_permalink', '');
      }

      $web_app->check_design();
      $web_app->check_modul();
      $modul = $api->get_system_var('dbx_modul', 'undef');
      $api->debug(sprintf(
         '#DBX RUN Base-URL(%s) Self=(%s) Ajax=(%s) Perma (%s) User=(%s) SYS CACHE=(%s) ',
         $api->get_base_url(),
         $api->get_self_url(),
         $api->get_system_var('dbx_ajax', 0, 'int'),
         $api->get_system_var('dbx_permalink', 'undef'),
         $api->user(),
         $api->get_cfg('dbx', 'cache')
      ));
      $api->timer('system-check');

      $api->timer($modul, 'Master-Modul');
      $module_content = $web_app->run();
      $api->timer($modul);

      $api->timer('page-load', 'Page-Load');
      $api->debug("#RUN-DBXWEBAPP SYNC=$sync");
      if ($sync) {
         $page_content = $web_app->design_load($module_content);
         $interpreter ??= $api->get_system_obj('dbxInterpreter');
         $api->timer('interpreter', 'Interpreter');
         $page_content = $interpreter->run($page_content);
         $api->timer('interpreter');
         // Der Interpreter kann weitere Templates mit DBX-Laufzeitwerten
         // einsetzen. Diese müssen vor der Wiederherstellung geschützter
         // Codebeispiele aufgelöst werden, damit weder Cache-Hits noch die
         // Live-Ausgabe Platzhalter wie {dbx:design} ausliefern.
         $page_content = $api->get_system_obj('dbxTPL')->replaces_dbx($page_content);
         $page_content = $web_app->add_norep($page_content);
         $page_content = $web_app->add_editor_files_data($page_content);
         $page_content = $web_app->out_filter($page_content);
      } else {
         $api->debug('no sync no output');
         http_response_code(204);
      }
      $api->timer('page-load');
      $api->timer('system');
      return (string)$page_content;
   }

   /** Liefert eine fertige Gastseite unverändert aus und beendet den Request. */
   private function serve_full_page_cache_hit(string $html): void {
      $api = dbx();
      $session = $api->get_system_obj('dbxSession');
      $discarded = is_object($session)
         && method_exists($session, 'discard_ephemeral_anonymous_session')
         && $session->discard_ephemeral_anonymous_session(true);
      if (!$discarded && session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      if (!headers_sent()) {
         header_remove('Set-Cookie');
         header_remove('Expires');
         header_remove('Pragma');
         $ttl = max(0, min(3600, (int)$api->get_cfg('dbx', 'full_page_browser_ttl', 60)));
         header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=30');
         $etag = '"' . hash('sha256', $html) . '"';
         header('ETag: ' . $etag);
         $if_none_match = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
         if ($if_none_match !== '' && hash_equals($etag, $if_none_match)) {
            http_response_code(304);
            $api->get_system_obj('dbxRuntime')->send_headers();
            exit;
         }
      }

      $api->get_system_obj('dbxRuntime')->send_headers();
      $this->emit_http_response_body($html);
      exit;
   }

   /** Gibt bei GET den Body aus und unterdrückt ihn bei HEAD einheitlich. */
   private function emit_http_response_body(string $response): void {
      if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'HEAD') {
         echo $response;
      }
   }
}
