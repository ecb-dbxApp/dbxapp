<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingPageFormServiceTrait {

   public function renderPageCreateForm(): string {
      $this->ensureContentBootstrap();
      $lng = strtolower(trim((string) dbx()->get_request_var('lng', '', '*')));
      if ($lng === '') {
         $lng = strtolower(trim((string) dbx()->get_request_var('dbx_lng', dbxContentLng::current(), '*')));
      }
      $lngOptions = $this->buildLngOptions($lng);
      $styleOptions = $this->buildStyleOptions((string) dbx()->get_request_var('writing_style', 'sachlich', '*'));
      $folderId = (int) dbx()->get_request_var('folder_id', 0, 'int');
      $sorterAfterPageId = (int) dbx()->get_request_var('sorter_after_page_id', 0, 'int');
      if ($sorterAfterPageId > 0) {
         try {
            $afterPage = $this->loadPage($lng, $sorterAfterPageId);
            $folderId = (int) ($afterPage['folder'] ?? $folderId);
         } catch (\Throwable $e) {
            $sorterAfterPageId = 0;
         }
      }

      $heroEnabled = dbx()->get_request_var('hero_enabled', '1', '*') !== '0';
      $selectedTemplate = (string) dbx()->get_request_var('content_template', self::CONTENT_TEMPLATE_DEFAULT, '*');
      $selectedBootstrapComponents = $this->selectedBootstrapComponentsFromRequest();
      $placementTitle = $folderId > 0 ? $this->createPlacementTitle($lng, $folderId, $sorterAfterPageId) : 'Zielposition waehlen';

      $data = $this->withModuleBar(array_merge(array(
         'hub_url' => $this->esc($this->moduleUrl('briefing')),
         'export_url' => $this->esc($this->moduleUrl('briefing_export')),
         'tree_url' => $this->esc($this->contentAdminUrl('cms_tree')),
         'lng' => $this->esc($lng),
         'lng_options' => $lngOptions,
         'selected_folder_id' => (string) $folderId,
         'selected_sorter_after_page_id' => (string) $sorterAfterPageId,
         'selected_placement_title' => $this->esc($placementTitle),
         'current_context' => $this->renderCreatePlacementHtml($lng, $folderId, $sorterAfterPageId),
         'style_options' => $styleOptions,
         'template_options' => $this->buildContentTemplateOptions($selectedTemplate, $heroEnabled),
         'bootstrap_component_choices' => $this->buildBootstrapComponentChoices($selectedBootstrapComponents),
         'title' => $this->esc((string) dbx()->get_request_var('title', '', '*')),
         'permalink' => $this->esc((string) dbx()->get_request_var('permalink', '', '*')),
         'description' => $this->esc((string) dbx()->get_request_var('description', '', '*')),
         'keywords' => $this->esc((string) dbx()->get_request_var('keywords', '', '*')),
         'content_brief' => $this->esc((string) dbx()->get_request_var('content_brief', '', '*')),
         'hero_brief' => $this->esc((string) dbx()->get_request_var('hero_brief', '', '*')),
         'hero_text_brief' => $this->esc((string) dbx()->get_request_var('hero_text_brief', '', '*')),
         'custom_notes' => $this->esc((string) dbx()->get_request_var('custom_notes', '', '*')),
         'hero_checked' => $heroEnabled ? 'checked' : '',
         'activ_checked' => dbx()->get_request_var('activ', '1', '*') !== '0' ? 'checked' : '',
      ), $this->briefingWorkflowData('briefing_page_create')), 'briefing_page_create', $this->barBackHub());

      return $this->briefingForm(
         $this->briefingFormId('page_create'),
         'ki-briefing-page-create',
         $this->moduleUrl('briefing_export'),
         $data
      )->run();
   }

   public function renderPageUpdateForm(): string {
      $this->ensureContentBootstrap();
      $lng = dbxContentLng::current();
      $pageId = (int) dbx()->get_request_var('page_id', 0, 'int');
      $lngOptions = $this->buildLngOptions($lng);
      $styleOptions = $this->buildStyleOptions((string) dbx()->get_request_var('writing_style', 'sachlich', '*'));
      $page = array();
      $pageTitle = '';
      $rendered = '<div class="dbx-cms-empty">Seite links im Content Tree waehlen.</div>';
      $contextHtml = '<div class="dbx-ki-context-empty">Noch keine Seite gewaehlt.</div>';
      if ($pageId > 0) {
         try {
            $page = $this->loadPage($lng, $pageId);
            $pageTitle = (string) ($page['title'] ?? ('Seite #' . $pageId));
            $rendered = '[modul=dbxContent]dbx_run1=show&cid=' . $pageId . '[/modul]';
            $contextHtml = $this->renderPageContextHtml($lng, $page);
         } catch (\Throwable $e) {
            $pageId = 0;
         }
      }

      $data = $this->withModuleBar(array_merge(array(
         'hub_url' => $this->esc($this->moduleUrl('briefing')),
         'export_url' => $this->esc($this->moduleUrl('briefing_export')),
         'tree_url' => $this->esc($this->contentAdminUrl('cms_tree')),
         'preview_url' => $this->esc($this->moduleUrl('briefing_page_update_preview')),
         'lng' => $this->esc($lng),
         'lng_options' => $lngOptions,
         'selected_page_id' => (string) $pageId,
         'selected_page_title' => $this->esc($pageTitle !== '' ? $pageTitle : 'Keine Seite gewaehlt'),
         'current_rendered' => $rendered,
         'current_context' => $contextHtml,
         'style_options' => $styleOptions,
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
      ), $this->briefingWorkflowData('briefing_page_update')), 'briefing_page_update', $this->barBackHub());

      $form = $this->briefingForm(
         $this->briefingFormId('page_update'),
         'ki-briefing-page-update',
         $this->moduleUrl('briefing_export'),
         $data
      );

      // Bootstrap-Komponenten-Checkboxen als echte FD-Felder, damit die
      // Auswahl per dbxForm-UI-State-Persistenz dauerhaft im Browser
      // gemerkt wird (siehe fd/ki-briefing-page-update.fd.php, data=ui_persist=1).
      $form->_fd = 'dbxKi|ki-briefing-page-update';
      $form->_ui_state_persist = 1;
      $form->add_flds();

      return $form->run();
   }

   public function handlePageUpdatePreviewJson(): void {
      $this->ensureContentBootstrap();

      $lng = strtolower(trim((string) dbx()->get_request_var('dbx_lng', dbxContentLng::current(), '*')));
      $pageId = (int) dbx()->get_request_var('id', 0, 'int');
      if ($pageId <= 0) {
         dbx()->json_response(array('ok' => 0, 'error' => 'Keine Seite gewaehlt.'), true);
      }

      try {
         $page = $this->loadPage($lng, $pageId);
         $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
         $html = $this->withContentLng($lng, function () use ($renderer, $pageId) {
            return $renderer->render($pageId);
         });
         dbx()->json_response(array(
            'ok' => 1,
            'id' => $pageId,
            'title' => (string) ($page['title'] ?? ''),
            'html' => $html,
            'context_html' => $this->renderPageContextHtml($lng, $page),
         ), true);
      } catch (\Throwable $e) {
         dbx()->json_response(array('ok' => 0, 'error' => $e->getMessage()), true);
      }
   }

   public function renderPageTranslateForm(): string {
      $this->ensureContentBootstrap();
      $sourceLng = strtolower(trim((string) dbx()->get_request_var('source_lng', '', '*')));
      if ($sourceLng === '') {
         $sourceLng = strtolower(trim((string) dbx()->get_request_var('dbx_lng', dbxContentLng::current(), '*')));
      }
      $sourceId = (int) dbx()->get_request_var('source_id', 0, 'int');
      $targetLngs = $this->selectedTargetLngsFromRequest($sourceLng, true);
      $currentRendered = '<div class="dbx-cms-empty">Seite links im Content Tree waehlen.</div>';
      $currentContext = '<p class="dbx-ki-context-empty">Noch keine Seite gewaehlt.</p>';
      $selectedTitle = 'Keine Seite gewaehlt';
      if ($sourceId > 0) {
         try {
            $source = $this->loadPage($sourceLng, $sourceId);
            $selectedTitle = trim((string) ($source['title'] ?? '')) !== '' ? (string) $source['title'] : ('Seite #' . $sourceId);
            $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
            $currentRendered = $this->withContentLng($sourceLng, function () use ($renderer, $sourceId) {
               return $renderer->render($sourceId);
            });
            $currentContext = $this->renderPageContextHtml($sourceLng, $source);
         } catch (\Throwable $e) {
            $currentRendered = '<div class="dbx-cms-empty">Quellseite konnte nicht geladen werden.</div>';
         }
      }

      $data = $this->withModuleBar(array_merge(array(
         'hub_url' => $this->esc($this->moduleUrl('briefing')),
         'export_url' => $this->esc($this->moduleUrl('briefing_export')),
         'tree_url' => $this->esc($this->contentAdminUrl('cms_tree')),
         'preview_url' => $this->esc($this->moduleUrl('briefing_page_update_preview')),
         'source_lng' => $this->esc($sourceLng),
         'source_lng_options' => $this->buildLngOptions($sourceLng),
         'target_lng_checkboxes' => $this->buildTargetLngCheckboxes($sourceLng, $targetLngs),
         'selected_page_id' => (string) $sourceId,
         'selected_page_title' => $this->esc($selectedTitle),
         'style_options' => $this->buildStyleOptions((string) dbx()->get_request_var('writing_style', 'sachlich', '*')),
         'translation_notes' => $this->esc((string) dbx()->get_request_var('translation_notes', '', '*')),
         'current_rendered' => $currentRendered,
         'current_context' => $currentContext,
         'copy_media_checked' => dbx()->get_request_var('copy_media', '1', '*') !== '0' ? 'checked' : '',
      ), $this->briefingWorkflowData('briefing_page_translate')), 'briefing_page_translate', $this->barBackHub());

      return $this->briefingForm(
         $this->briefingFormId('page_translate'),
         'ki-briefing-page-translation',
         $this->moduleUrl('briefing_export'),
         $data
      )->run();
   }
}
