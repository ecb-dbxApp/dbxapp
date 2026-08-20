<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingPageFormServiceTrait {

   public function render_page_create_form(): string {
      $this->ensure_content_bootstrap();
      $lng = strtolower(trim((string) dbx()->get_request_var('lng', '', '*')));
      if ($lng === '') {
         $lng = strtolower(trim((string) dbx()->get_request_var('dbx_lng', dbxContentLng::current(), '*')));
      }
      $lng_options = $this->build_lng_options($lng);
      $style_options = $this->build_style_options((string) dbx()->get_request_var('writing_style', 'sachlich', '*'));
      $folder_id = (int) dbx()->get_request_var('folder_id', 0, 'int');
      $sorter_after_page_id = (int) dbx()->get_request_var('sorter_after_page_id', 0, 'int');
      if ($sorter_after_page_id > 0) {
         try {
            $after_page = $this->load_page($lng, $sorter_after_page_id);
            $folder_id = (int) ($after_page['folder'] ?? $folder_id);
         } catch (\Throwable $e) {
            $sorter_after_page_id = 0;
         }
      }

      $hero_enabled = dbx()->get_request_var('hero_enabled', '1', '*') !== '0';
      $selected_template = (string) dbx()->get_request_var('content_template', self::CONTENT_TEMPLATE_DEFAULT, '*');
      $selected_bootstrap_components = $this->selected_bootstrap_components_from_request();
      $placement_title = $folder_id > 0 ? $this->create_placement_title($lng, $folder_id, $sorter_after_page_id) : 'Zielposition waehlen';

      $data = $this->with_module_bar(array_merge(array(
         'hub_url' => $this->esc($this->module_url('briefing')),
         'export_url' => $this->esc($this->module_url('briefing_export')),
         'tree_url' => $this->esc($this->content_admin_url('cms_tree')),
         'lng' => $this->esc($lng),
         'lng_options' => $lng_options,
         'selected_folder_id' => (string) $folder_id,
         'selected_sorter_after_page_id' => (string) $sorter_after_page_id,
         'selected_placement_title' => $this->esc($placement_title),
         'current_context' => $this->render_create_placement_html($lng, $folder_id, $sorter_after_page_id),
         'style_options' => $style_options,
         'template_options' => $this->build_content_template_options($selected_template, $hero_enabled),
         'bootstrap_component_choices' => $this->build_bootstrap_component_choices($selected_bootstrap_components),
         'title' => $this->esc((string) dbx()->get_request_var('title', '', '*')),
         'permalink' => $this->esc((string) dbx()->get_request_var('permalink', '', '*')),
         'description' => $this->esc((string) dbx()->get_request_var('description', '', '*')),
         'keywords' => $this->esc((string) dbx()->get_request_var('keywords', '', '*')),
         'content_brief' => $this->esc((string) dbx()->get_request_var('content_brief', '', '*')),
         'hero_brief' => $this->esc((string) dbx()->get_request_var('hero_brief', '', '*')),
         'hero_text_brief' => $this->esc((string) dbx()->get_request_var('hero_text_brief', '', '*')),
         'custom_notes' => $this->esc((string) dbx()->get_request_var('custom_notes', '', '*')),
         'hero_checked' => $hero_enabled ? 'checked' : '',
         'activ_checked' => dbx()->get_request_var('activ', '1', '*') !== '0' ? 'checked' : '',
      ), $this->briefing_workflow_data('briefing_page_create')), 'briefing_page_create', $this->bar_back_hub());

      return $this->briefing_form(
         $this->briefing_form_id('page_create'),
         'ki-briefing-page-create',
         $this->module_url('briefing_export'),
         $data
      )->run();
   }

   public function render_page_update_form(): string {
      $this->ensure_content_bootstrap();
      $lng = dbxContentLng::current();
      $page_id = (int) dbx()->get_request_var('page_id', 0, 'int');
      $lng_options = $this->build_lng_options($lng);
      $style_options = $this->build_style_options((string) dbx()->get_request_var('writing_style', 'sachlich', '*'));
      $page = array();
      $page_title = '';
      $rendered = '<div class="dbx-cms-empty">Seite links im Content Tree waehlen.</div>';
      $context_html = '<div class="dbx-ki-context-empty">Noch keine Seite gewaehlt.</div>';
      if ($page_id > 0) {
         try {
            $page = $this->load_page($lng, $page_id);
            $page_title = (string) ($page['title'] ?? ('Seite #' . $page_id));
            $rendered = '[modul=dbxContent]dbx_run1=show&cid=' . $page_id . '[/modul]';
            $context_html = $this->render_page_context_html($lng, $page);
         } catch (\Throwable $e) {
            $page_id = 0;
         }
      }

      $data = $this->with_module_bar(array_merge(array(
         'hub_url' => $this->esc($this->module_url('briefing')),
         'export_url' => $this->esc($this->module_url('briefing_export')),
         'tree_url' => $this->esc($this->content_admin_url('cms_tree')),
         'preview_url' => $this->esc($this->module_url('briefing_page_update_preview')),
         'lng' => $this->esc($lng),
         'lng_options' => $lng_options,
         'selected_page_id' => (string) $page_id,
         'selected_page_title' => $this->esc($page_title !== '' ? $page_title : 'Keine Seite gewaehlt'),
         'current_rendered' => $rendered,
         'current_context' => $context_html,
         'style_options' => $style_options,
         'change_brief' => $this->esc((string) dbx()->get_request_var('change_brief', '', '*')),
         'custom_notes' => $this->esc((string) dbx()->get_request_var('custom_notes', '', '*')),
         'field_content_checked' => dbx()->get_request_var('change_content', '1', '*') !== '0' ? 'checked' : '',
         'field_title_checked' => dbx()->get_request_var('change_title', '', '*') === '1' ? 'checked' : '',
         'field_description_checked' => dbx()->get_request_var('change_description', '', '*') === '1' ? 'checked' : '',
         'field_hero_checked' => dbx()->get_request_var('change_hero', '', '*') === '1' ? 'checked' : '',
         'hero_mode_replace_checked' => dbx()->get_request_var('hero_mode', 'replace', '*') === 'replace' ? 'checked' : '',
         'hero_mode_create_checked' => dbx()->get_request_var('hero_mode', 'replace', '*') === 'create' ? 'checked' : '',
         'hero_brief' => $this->esc((string) dbx()->get_request_var('hero_brief', '', '*')),
         'embedded_policy_modify_checked' => dbx()->get_request_var('embedded_policy', 'preserve', '*') === 'modify' ? 'checked' : '',
         'embedded_policy_preserve_checked' => dbx()->get_request_var('embedded_policy', 'preserve', '*') === 'preserve' ? 'checked' : '',
         'embedded_policy_reorder_checked' => dbx()->get_request_var('embedded_policy', 'preserve', '*') === 'reorder' ? 'checked' : '',
         'embedded_policy_remove_checked' => dbx()->get_request_var('embedded_policy', 'preserve', '*') === 'remove' ? 'checked' : '',
         'embedded_change_notes' => $this->esc((string) dbx()->get_request_var('embedded_change_notes', '', '*')),
      ), $this->briefing_workflow_data('briefing_page_update')), 'briefing_page_update', $this->bar_back_hub());

      $form = $this->briefing_form(
         $this->briefing_form_id('page_update'),
         'ki-briefing-page-update',
         $this->module_url('briefing_export'),
         $data
      );

      // Bootstrap-Komponenten-Checkboxen als echte FD-Felder, damit die
      // Auswahl per dbxForm-UI-State-Persistenz dauerhaft im Browser
      // gemerkt wird (siehe fd/ki-briefing-page-update.fd.php, data=ui_persist=1).
      $form->set_field_definition('dbxKi|ki-briefing-page-update');
      $form->_ui_state_persist = 1;
      $form->add_flds();

      return $form->run();
   }

   public function handle_page_update_preview_json(): void {
      $this->ensure_content_bootstrap();

      $lng = strtolower(trim((string) dbx()->get_request_var('dbx_lng', dbxContentLng::current(), '*')));
      $page_id = (int) dbx()->get_request_var('id', 0, 'int');
      if ($page_id <= 0) {
         dbx()->json_response(array('ok' => 0, 'error' => 'Keine Seite gewaehlt.'), true);
      }

      try {
         $page = $this->load_page($lng, $page_id);
         $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
         $html = $this->with_content_lng($lng, function () use ($renderer, $page_id) {
            return $renderer->render($page_id);
         });
         dbx()->json_response(array(
            'ok' => 1,
            'id' => $page_id,
            'title' => (string) ($page['title'] ?? ''),
            'html' => $html,
            'context_html' => $this->render_page_context_html($lng, $page),
         ), true);
      } catch (\Throwable $e) {
         dbx()->json_response(array('ok' => 0, 'error' => $e->getMessage()), true);
      }
   }

   public function render_page_translate_form(): string {
      $this->ensure_content_bootstrap();
      $source_lng = strtolower(trim((string) dbx()->get_request_var('source_lng', '', '*')));
      if ($source_lng === '') {
         $source_lng = strtolower(trim((string) dbx()->get_request_var('dbx_lng', dbxContentLng::current(), '*')));
      }
      $source_id = (int) dbx()->get_request_var('source_id', 0, 'int');
      $target_lngs = $this->selected_target_lngs_from_request($source_lng, true);
      $current_rendered = '<div class="dbx-cms-empty">Seite links im Content Tree waehlen.</div>';
      $current_context = '<p class="dbx-ki-context-empty">Noch keine Seite gewaehlt.</p>';
      $selected_title = 'Keine Seite gewaehlt';
      if ($source_id > 0) {
         try {
            $source = $this->load_page($source_lng, $source_id);
            $selected_title = trim((string) ($source['title'] ?? '')) !== '' ? (string) $source['title'] : ('Seite #' . $source_id);
            $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
            $current_rendered = $this->with_content_lng($source_lng, function () use ($renderer, $source_id) {
               return $renderer->render($source_id);
            });
            $current_context = $this->render_page_context_html($source_lng, $source);
         } catch (\Throwable $e) {
            $current_rendered = '<div class="dbx-cms-empty">Quellseite konnte nicht geladen werden.</div>';
         }
      }

      $data = $this->with_module_bar(array_merge(array(
         'hub_url' => $this->esc($this->module_url('briefing')),
         'export_url' => $this->esc($this->module_url('briefing_export')),
         'tree_url' => $this->esc($this->content_admin_url('cms_tree')),
         'preview_url' => $this->esc($this->module_url('briefing_page_update_preview')),
         'source_lng' => $this->esc($source_lng),
         'source_lng_options' => $this->build_lng_options($source_lng),
         'target_lng_checkboxes' => $this->build_target_lng_checkboxes($source_lng, $target_lngs),
         'selected_page_id' => (string) $source_id,
         'selected_page_title' => $this->esc($selected_title),
         'style_options' => $this->build_style_options((string) dbx()->get_request_var('writing_style', 'sachlich', '*')),
         'translation_notes' => $this->esc((string) dbx()->get_request_var('translation_notes', '', '*')),
         'current_rendered' => $current_rendered,
         'current_context' => $current_context,
         'copy_media_checked' => dbx()->get_request_var('copy_media', '1', '*') !== '0' ? 'checked' : '',
      ), $this->briefing_workflow_data('briefing_page_translate')), 'briefing_page_translate', $this->bar_back_hub());

      return $this->briefing_form(
         $this->briefing_form_id('page_translate'),
         'ki-briefing-page-translation',
         $this->module_url('briefing_export'),
         $data
      )->run();
   }
}
