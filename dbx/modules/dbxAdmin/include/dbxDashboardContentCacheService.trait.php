<?php
namespace dbx\dbxAdmin;

trait dbxDashboardContentCacheServiceTrait {

   private function load_content_cache_classes(): void {
      dbx()->load_content_cache_classes();
   }

   private function content_cache_language_catalog(): array {
      return array(
         'de' => array('label' => 'Deutsch',  'flag' => '🇩🇪', 'tone' => 'teal'),
         'en' => array('label' => 'English',  'flag' => '🇬🇧', 'tone' => 'navy'),
         'es' => array('label' => 'Español',  'flag' => '🇪🇸', 'tone' => 'amber'),
      );
   }

   private function content_cache_enabled_config(): bool {
      return \dbx\dbxContent\dbxContentPageCache::isConfigEnabled();
   }

   private function content_cache_files_for_lng(string $lng): int {
      $this->load_content_cache_classes();
      $lng = strtolower(trim($lng));
      if ($lng === '') {
         return 0;
      }

      $base = \dbx\dbxContent\dbxContentPageCache::baseDir() . 'content/';
      return count(glob($base . 'full-page/*_' . $lng . '_*.htm') ?: array());
   }

   private function content_cache_language_rows(): string {
      $this->load_content_cache_classes();
      $tpl = dbx()->get_system_obj('dbxTPL');
      $catalog = $this->content_cache_language_catalog();
      $lngs = dbx()->accessible_lngs();
      $tones = array('teal', 'navy', 'amber', 'cyan', 'purple', 'green');
      $rows = '';
      $toneIndex = 0;

      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '') {
            continue;
         }

         $meta = $catalog[$lng] ?? array(
            'label' => strtoupper($lng),
            'flag' => '🌐',
            'tone' => $tones[$toneIndex % count($tones)],
         );
         $toneIndex++;

         $pagesTotal = $this->safe_count(\dbx\dbxContent\dbxContentLng::ddContent($lng));
         $pagesActive = $this->safe_count(\dbx\dbxContent\dbxContentLng::ddContent($lng), 'activ = 1');
         $folders = $this->safe_count(\dbx\dbxContent\dbxContentLng::ddFolder($lng));

         $rows .= $tpl->get_tpl('dbxAdmin|admin-dashboard-content-cache-lng', array(
            'flag' => dbx()->esc($meta['flag'] ?? '🌐'),
            'label' => dbx()->esc($meta['label'] ?? strtoupper($lng)),
            'code' => dbx()->esc(strtoupper($lng)),
            'tone' => dbx()->esc($meta['tone'] ?? 'teal'),
            'pages_total' => $this->fmt($pagesTotal),
            'pages_active' => $this->fmt($pagesActive),
            'folders' => $this->fmt($folders),
            'cached' => $this->fmt($this->content_cache_files_for_lng($lng)),
         ));
      }

      if ($rows === '') {
         $rows = '<article class="dbx-admin-dashboard-cache-lng-card dbx-admin-dashboard-cache-lng-empty">'
            . '<div class="dbx-admin-dashboard-cache-lng-title"><strong>Keine Sprachen konfiguriert</strong></div>'
            . '</article>';
      }

      return $rows;
   }

   private function process_content_cache_action(): void {
      $run2 = dbx()->get_modul_var('dbx_run2', '', 'parameter');
      if ($run2 === '') {
         return;
      }

      $this->load_content_cache_classes();

      if ($run2 === 'cache_flush') {
         \dbx\dbxContent\dbxContentPageCache::invalidateAll();
         return;
      }

      if ($run2 === 'sitemap_rebuild') {
         \dbx\dbxContent\dbxContentSitemap::rebuild();
         return;
      }

      if ($run2 === 'cache_save') {
         $enabled = isset($_POST['cache_content']);
         \dbx\dbxContent\dbxContentPageCache::setConfigEnabled($enabled);
      }
   }

   private function content_cache_panel_body_data(): array {
      $this->load_content_cache_classes();
      $stats = \dbx\dbxContent\dbxContentPageCache::cacheStats();
      $sitemapStats = \dbx\dbxContent\dbxContentSitemap::stats();
      $enabled = $this->content_cache_enabled_config();

      return array(
         'cache_save_url' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=cache_save'),
         'cache_flush_url' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=cache_flush'),
         'sitemap_rebuild_url' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=sitemap_rebuild'),
         'sitemap_url' => dbx()->esc(rtrim((string) dbx()->get_base_url(), '/') . '/sitemap.xml'),
         'cache_admin_url' => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=cache'),
         'cache_enabled_checked' => $enabled ? 'checked' : '',
         'cache_status_tone' => $enabled ? 'on' : 'off',
         'cache_status_icon' => $enabled ? 'bi-check-circle-fill' : 'bi-pause-circle-fill',
         'cache_status_label' => dbx()->esc($enabled ? 'Cache-Schreiben aktiv' : 'Cache-Schreiben pausiert'),
         'cache_status_hint' => $enabled
            ? dbx()->esc('Vorhandene Gastseiten werden gelesen; Cache-Misses werden als vollstaendige Endausgabe gespeichert.')
               . '<br>'
               . dbx()->esc('Head-Metadaten, Design, Menues und Module sind fertig aufgeloest; ein HIT braucht nur den Session-Zugriff, aber keine Content-Datenbank.')
            : dbx()->esc('Vorhandene Gastseiten werden weiterhin aus dem Cache ausgegeben. Cache-Misses werden live gerendert, aber nicht neu gespeichert.'),
         'cache_content_count' => $this->fmt((int) ($stats['content'] ?? 0)),
         'sitemap_count' => $this->fmt((int) ($sitemapStats['urls'] ?? 0)),
         'sitemap_generated' => dbx()->esc((string) ($sitemapStats['generated_at'] ?? '')),
         'sitemap_state' => dbx()->esc(!empty($sitemapStats['exists']) ? 'vorhanden' : 'nicht erstellt'),
         'cache_dir' => dbx()->esc((string) ($stats['base_dir'] ?? '')),
         'lng_rows' => $this->content_cache_language_rows(),
      );
   }

   private function content_cache_panel_body_html(): string {
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-content-cache-body', 'admin-dashboard-content-cache-body');
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';

      foreach ($this->content_cache_panel_body_data() as $key => $value) {
         $oForm->add_rep($key, $value);
      }

      return $oForm->add_norep($oForm->run());
   }

   private function content_cache_bar_actions_html(): string {
      $this->load_content_cache_classes();
      $enabled = $this->content_cache_enabled_config();
      $oForm = new \dbxForm();
      $oForm->init('admin-dashboard-content-cache-actions', 'admin-dashboard-content-cache-actions');
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=cache_save';
      $oForm->_msg_info = '';
      $oForm->add_rep('cache_save_url', dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=cache_save'));
      $oForm->add_rep('cache_flush_url', dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=cache_flush'));
      $oForm->add_rep('sitemap_rebuild_url', dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=run&dbx_run2=sitemap_rebuild'));
      $oForm->add_rep('cache_enabled_checked', $enabled ? 'checked' : '');
      $oForm->add_rep('bar_extra', $this->help_action('cache'));

      return $oForm->add_norep($oForm->run());
   }

   private function content_cache_panel() {
      $oForm = new \dbxForm();
      $panelTarget = 'content-cache';
      $oForm->init('admin-dashboard-content-cache', 'admin-dashboard-content-cache');
      $oForm->_action = '?dbx_modul=dbxAdmin&dbx_run1=run';
      $oForm->_msg_info = '';
      $oForm->add_obj('cache_bar', 'dbx|component-bar', $this->card_bar_data(
         'Gast-Full-Page-Cache',
         'bi-lightning-charge-fill',
         'Komplette Endausgabe gueltiger Permalinks, ausschliesslich fuer nicht angemeldete Gaeste',
         $this->content_cache_bar_actions_html()
            . $this->card_action('?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=edit', 'CMS')
            . $this->collapse_action($panelTarget, 'Zuklappen', true)
      ));
      $oForm->add_rep('panel_target', dbx()->esc($panelTarget));
      $oForm->add_rep('cache_body', $this->content_cache_panel_body_html());

      return $oForm->run();
   }
}
