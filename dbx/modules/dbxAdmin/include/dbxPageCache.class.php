<?php
namespace dbx\dbxAdmin;

/**
 * Zeigt Status und Wartungsaktionen des öffentlichen Full-Page-Caches an.
 */
class dbxPageCache {

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function load_cache_classes(): void {
      dbx()->load_content_cache_classes();
   }

   public function run(): string {
      $this->load_cache_classes();

      $run2 = dbx()->get_modul_var('dbx_run2', 'show', 'parameter');
      $msg = '';

      if ($run2 === 'flush') {
         $stats = \dbx\dbxContent\dbxContentPageCache::invalidate_all();
         $msg = $this->tpl()->get_tpl('dbx|alert-success', array(
            'msg' => 'Gast-Full-Page-Cache geleert: '
               . (int)($stats['content'] ?? 0) . ' Datei(en) entfernt.'
         ));
      }

      $stats = \dbx\dbxContent\dbxContentPageCache::cache_stats();

      return $this->tpl()->get_tpl('dbxAdmin|page-cache-admin', array_merge(array(
         'msg' => $msg,
         'flush_url' => '?dbx_modul=dbxAdmin&dbx_run1=cache&dbx_run2=flush',
         'content_count' => (int)($stats['content'] ?? 0),
         'cache_dir' => (string)($stats['base_dir'] ?? ''),
      ), dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp')->vars('cache')));
   }
}
