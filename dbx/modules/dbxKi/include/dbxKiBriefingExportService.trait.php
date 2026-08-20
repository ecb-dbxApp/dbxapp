<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingExportServiceTrait {

   public function handle_export(): void {
      $this->ensure_content_bootstrap();
      if (!class_exists('ZipArchive')) {
         dbx()->json_response(array('ok' => 0, 'error' => 'ZipArchive nicht verfuegbar.'), true);
      }

      $recipe = strtolower(trim((string) dbx()->get_request_var('recipe', '', '*')));
      if (!in_array($recipe, array('page_create', 'page_update', 'page_translate'), true)) {
         dbx()->json_response(array('ok' => 0, 'error' => 'Unbekanntes Rezept: ' . $recipe), true);
         return;
      }

      $form = $this->briefing_form(
         $this->briefing_form_id($recipe),
         'ki-briefing-' . str_replace('_', '-', $recipe),
         $this->module_url('briefing_export')
      );
      if (!$form->submit()) {
         dbx()->json_response(array('ok' => 0, 'error' => 'Ungueltiger oder abgelaufener Formular-Token.'), true);
         return;
      }

      if ($recipe === 'page_create') {
         $package = $this->build_page_create_package($this->collect_page_create_input());
      } elseif ($recipe === 'page_update') {
         $package = $this->build_page_update_package($this->collect_page_update_input());
      } elseif ($recipe === 'page_translate') {
         $package = $this->build_page_translate_package($this->collect_page_translate_input());
      }

      $this->send_zip_download($package);
   }

   private function collect_page_create_input(): array {
      $lng = strtolower(trim((string) dbx()->get_request_var('lng', dbxContentLng::current(), '*')));
      return array(
         'recipe' => 'page_create',
         'lng' => $lng,
         'folder_id' => max(0, (int) dbx()->get_request_var('folder_id', 0, 'int')),
         'sorter_after_page_id' => max(0, (int) dbx()->get_request_var('sorter_after_page_id', 0, 'int')),
         'title' => trim((string) dbx()->get_request_var('title', '', '*')),
         'permalink' => trim((string) dbx()->get_request_var('permalink', '', '*')),
         'description' => trim((string) dbx()->get_request_var('description', '', '*')),
         'keywords' => trim((string) dbx()->get_request_var('keywords', '', '*')),
         'writing_style' => trim((string) dbx()->get_request_var('writing_style', 'sachlich', '*')),
         'content_brief' => trim((string) dbx()->get_request_var('content_brief', '', '*')),
         'hero_enabled' => dbx()->get_request_var('hero_enabled', '0', '*') === '1',
         'hero_brief' => trim((string) dbx()->get_request_var('hero_brief', '', '*')),
         'hero_mode' => dbx()->get_request_var('hero_mode', 'replace', '*') === 'create' ? 'create' : 'replace',
         'hero_text_brief' => trim((string) dbx()->get_request_var('hero_text_brief', '', '*')),
         'content_template' => trim((string) dbx()->get_request_var('content_template', '', '*')),
         'bootstrap_components' => $this->selected_bootstrap_components_from_request(),
         'activ' => dbx()->get_request_var('activ', '0', '*') === '1',
         'custom_notes' => trim((string) dbx()->get_request_var('custom_notes', '', '*')),
      );
   }

   private function collect_page_update_input(): array {
      $lng = strtolower(trim((string) dbx()->get_request_var('lng', dbxContentLng::current(), '*')));
      $change_fields = array();
      if (dbx()->get_request_var('change_content', '0', '*') === '1') {
         $change_fields[] = 'content';
      }
      if (dbx()->get_request_var('change_title', '0', '*') === '1') {
         $change_fields[] = 'title';
      }
      if (dbx()->get_request_var('change_description', '0', '*') === '1') {
         $change_fields[] = 'description';
      }
      if (dbx()->get_request_var('change_hero', '0', '*') === '1') {
         $change_fields[] = 'hero';
      }
      if (!$change_fields) {
         $change_fields = array('content');
      }

      return array(
         'recipe' => 'page_update',
         'lng' => $lng,
         'page_id' => max(0, (int) dbx()->get_request_var('page_id', 0, 'int')),
         'writing_style' => trim((string) dbx()->get_request_var('writing_style', 'sachlich', '*')),
         'change_brief' => trim((string) dbx()->get_request_var('change_brief', '', '*')),
         'change_fields' => $change_fields,
         'hero_brief' => trim((string) dbx()->get_request_var('hero_brief', '', '*')),
         'custom_notes' => trim((string) dbx()->get_request_var('custom_notes', '', '*')),
         'embedded_policy' => $this->sanitize_embedded_policy((string) dbx()->get_request_var('embedded_policy', 'preserve', '*')),
         'embedded_change_notes' => trim((string) dbx()->get_request_var('embedded_change_notes', '', '*')),
         'bootstrap_components' => $this->selected_bootstrap_components_from_update_fields(),
      );
   }

   private function collect_page_translate_input(): array {
      $source_lng = strtolower(trim((string) dbx()->get_request_var('source_lng', dbxContentLng::current(), '*')));
      $target_lngs = $this->selected_target_lngs_from_request($source_lng, false);
      return array(
         'recipe' => 'page_translate',
         'source_lng' => $source_lng,
         'target_lng' => (string) ($target_lngs[0] ?? ''),
         'target_lngs' => $target_lngs,
         'source_id' => max(0, (int) dbx()->get_request_var('source_id', 0, 'int')),
         'writing_style' => trim((string) dbx()->get_request_var('writing_style', 'sachlich', '*')),
         'translation_notes' => trim((string) dbx()->get_request_var('translation_notes', '', '*')),
         'copy_media' => dbx()->get_request_var('copy_media', '1', '*') !== '0',
      );
   }

   private function build_page_create_package(array $in): array {
      if ($in['folder_id'] <= 0) {
         throw new \InvalidArgumentException('Bitte einen Zielordner waehlen.');
      }
      if ($in['title'] === '') {
         throw new \InvalidArgumentException('Titel ist erforderlich.');
      }
      if ($in['content_brief'] === '') {
         throw new \InvalidArgumentException('Beschreiben Sie, worum es im Text gehen soll.');
      }

      $sorter_after_page = array();
      $sorter_after_label = '';
      $sorter_value = '';
      if ($in['sorter_after_page_id'] > 0) {
         try {
            $sorter_after_page = $this->load_page($in['lng'], $in['sorter_after_page_id']);
            $in['folder_id'] = (int) ($sorter_after_page['folder'] ?? $in['folder_id']);
            $sorter_after_label = '#' . (int) $in['sorter_after_page_id'] . ' ' . (string) ($sorter_after_page['title'] ?? '');
            $sorter_value = $this->sorter_after_page($in['lng'], $in['sorter_after_page_id']);
         } catch (\Throwable $e) {
            $in['sorter_after_page_id'] = 0;
         }
      }
      $in['sorter'] = $sorter_value;
      $folder_label = $this->folder_label($in['lng'], $in['folder_id']);
      $styles = $this->writing_styles();
      $style_key = $in['writing_style'];
      if (!isset($styles[$style_key])) {
         $style_key = 'sachlich';
      }
      $style_prompt = (string) ($styles[$style_key]['prompt'] ?? '');

      $permalink = $in['permalink'];
      if ($permalink === '') {
         $permalink = '(automatisch aus Titel)';
      }

      $content_template = $this->content_template_for_create($in['hero_enabled'], $in['content_template'] ?? '');
      $template_slots = $this->analyze_template_slots($content_template);

      $briefing = array(
         'briefing_version' => self::BRIEFING_VERSION,
         'recipe' => 'page.create.v1',
         'task' => 'page_create',
         'created_at' => date('c'),
         'lng' => $in['lng'],
         'folder_id' => $in['folder_id'],
         'folder_label' => $folder_label,
         'sorter_after_page_id' => $in['sorter_after_page_id'],
         'sorter_after_label' => $sorter_after_label,
         'sorter' => $sorter_value,
         'title' => $in['title'],
         'permalink' => $in['permalink'],
         'description' => $in['description'],
         'keywords' => $in['keywords'],
         'activ' => $in['activ'],
         'writing_style' => $style_key,
         'content' => array('brief' => $in['content_brief']),
         'hero' => array(
            'enabled' => $in['hero_enabled'],
            'brief' => $in['hero_brief'],
            'image' => $this->hero_image_briefing_meta(),
            'height' => array(
               'default' => self::HERO_DEFAULT_HEIGHT,
               'rule' => 'In page.create hero_height=' . self::HERO_DEFAULT_HEIGHT . ' setzen, wenn nichts anderes angegeben ist.',
            ),
         ),
         'hero_text' => array(
            'brief' => $in['hero_text_brief'] ?? '',
            'max_lines' => self::HERO_TEXT_MAX_LINES,
            'rule' => 'Wenn nicht anders angegeben, maximal ' . self::HERO_TEXT_MAX_LINES . ' Zeilen.',
         ),
         'content_template' => $content_template,
         'template_slots' => $template_slots,
         'content_markers' => $this->content_markers_meta($template_slots),
         'bootstrap_components' => $this->bootstrap_components_meta($in['bootstrap_components'] ?? array()),
         'custom_notes' => $in['custom_notes'],
      );

      $job_vorlage = $this->job_vorlage_page_create($in);
      $manifest = array(
         'bundle_version' => self::BRIEFING_VERSION,
         'title' => $in['title'],
         'recipe' => 'page.create.v1',
         'lng' => $in['lng'],
         'intent' => 'create',
         'area' => 'cms',
         'auto_execute' => false,
      );

      $context = array(
         'lng' => $in['lng'],
         'target_folder_id' => $in['folder_id'],
         'target_folder_label' => $folder_label,
         'sorter_after_page_id' => $in['sorter_after_page_id'],
         'sorter_after' => dbxKiValue::slim_page($sorter_after_page),
         'sorter' => $sorter_value,
      );
      $contract = $this->contracts()->create(
         'cms',
         'page.create.v1',
         $manifest,
         $job_vorlage,
         $this->page_create_output_definitions($in),
         $this->hero_asset_definitions($in['hero_enabled']),
         $this->folder_snapshot($in['lng'], (int)$in['folder_id'])
      );

      $auftrag = $this->render_template_file('ki-page-create-auftrag.md', array(
         'writing_style_prompt' => $style_prompt,
         'content_brief' => $in['content_brief'],
         'custom_notes' => $in['custom_notes'] !== '' ? $in['custom_notes'] : '(keine)',
         'zip_structure' => $this->zip_structure_create($in['hero_enabled']),
         'assets_rules' => $this->assets_rules_create($in['hero_enabled'], $in['hero_brief']),
         'lng' => $in['lng'],
         'folder_id' => (string) $in['folder_id'],
         'folder_label' => $folder_label,
         'sorter_after_label' => $sorter_after_label !== '' ? $sorter_after_label : '(am Ende des Ordners)',
         'sorter' => $sorter_value !== '' ? $sorter_value : '(automatisch am Ende)',
         'title' => $in['title'],
         'permalink' => $permalink,
         'description' => $in['description'] !== '' ? $in['description'] : '(optional)',
         'keywords' => $in['keywords'] !== '' ? $in['keywords'] : '(optional)',
         'activ' => $in['activ'] ? '1 (aktiv)' : '0 (inaktiv)',
         'content_template' => $content_template,
         'hero_text_brief' => ($in['hero_text_brief'] ?? '') !== '' ? $in['hero_text_brief'] : '(optional — Kurztext im Hero-Bereich)',
         'content_markers_guide' => $this->content_markers_guide($content_template, $in['hero_enabled'], $in['hero_text_brief'] ?? ''),
         'bootstrap_components_guide' => $this->bootstrap_components_guide($in['bootstrap_components'] ?? array()),
      ));

      $files = $this->pack_briefing_files(array(
         'recipe' => 'page.create.v1',
         'task_label' => 'Neue CMS-Seite: ' . $in['title'],
         'hero_assets' => $in['hero_enabled'],
         'content_template' => $content_template,
         'context_hint' => 'Nein — Ordner und Sortierung stehen in briefing.json',
         'manifest' => $manifest,
         'briefing' => $briefing,
         'job_vorlage' => $job_vorlage,
          'context' => $context,
          'auftrag' => $auftrag,
          'contract' => $contract,
       ));
      if ($in['hero_enabled']) {
         $files['assets/README.txt'] = $this->assets_readme_hero($in['hero_brief']);
      }

      return array(
         'filename' => 'dbxki-auftrag-neue-seite-' . preg_replace('/[^a-z0-9_-]+/i', '-', $in['title']) . '.zip',
         'files' => $files,
      );
   }

   private function build_page_update_package(array $in): array {
      if ($in['page_id'] <= 0) {
         throw new \InvalidArgumentException('Bitte eine Seite waehlen.');
      }
      if ($in['change_brief'] === '') {
         throw new \InvalidArgumentException('Beschreiben Sie die gewuenschte Aenderung.');
      }

      $page = $this->load_page($in['lng'], $in['page_id']);
      $styles = $this->writing_styles();
      $style_key = $in['writing_style'];
      if (!isset($styles[$style_key])) {
         $style_key = 'sachlich';
      }
      $page_template = trim((string) ($page['template'] ?? 'parent'));
      if ($page_template === '' || $page_template === 'parent') {
         $page_template = 'c-body1-footer';
      }
      $page_context = $this->page_context_for_ki($in['lng'], $page);
      $embedded_policy = $this->embedded_policy_text($in['embedded_policy'], $in['embedded_change_notes']);

      $briefing = array(
         'briefing_version' => self::BRIEFING_VERSION,
         'recipe' => 'page.update.v1',
         'task' => 'page_update',
         'lng' => $in['lng'],
         'page_id' => $in['page_id'],
         'page_title' => (string) ($page['title'] ?? ''),
         'permalink' => (string) ($page['permalink'] ?? ''),
         'writing_style' => $style_key,
         'change_brief' => $in['change_brief'],
         'change_fields' => $in['change_fields'],
         'content_template' => $page_template,
         'bootstrap_components' => $this->bootstrap_components_meta($in['bootstrap_components'] ?? array()),
         'embedded_content_policy' => array(
            'mode' => $in['embedded_policy'],
            'notes' => $in['embedded_change_notes'],
            'default' => 'preserve existing embedded media and module calls exactly',
         ),
         'hero' => array(
            'brief' => $in['hero_brief'],
            'mode' => $in['hero_mode'],
            'image' => $this->hero_image_briefing_meta(),
         ),
         'custom_notes' => $in['custom_notes'],
      );

      $job_vorlage = $this->job_vorlage_page_update($in, $page);
      $manifest = array(
         'bundle_version' => self::BRIEFING_VERSION,
         'title' => 'Update: ' . ($page['title'] ?? ''),
         'recipe' => 'page.update.v1',
         'lng' => $in['lng'],
         'intent' => 'update',
         'area' => 'cms',
         'auto_execute' => false,
      );

      $context = array(
         'lng' => $in['lng'],
         'current_page' => dbxKiValue::slim_page($page),
         'current_page_context' => $page_context,
      );
      $contract = $this->contracts()->create(
         'cms',
         'page.update.v1',
         $manifest,
         $job_vorlage,
         $this->page_update_output_definitions($in),
         $this->hero_asset_definitions(in_array('hero', $in['change_fields'], true)),
         $this->page_snapshot($in['lng'], $page)
      );

      $excerpt = $this->truncate((string) ($page['content'] ?? ''), 4000);

      $hero_change = in_array('hero', $in['change_fields'], true);

      $auftrag = $this->render_template_file('ki-page-update-auftrag.md', array(
         'writing_style_prompt' => (string) ($styles[$style_key]['prompt'] ?? ''),
         'change_brief' => $in['change_brief'],
         'custom_notes' => $in['custom_notes'] !== '' ? $in['custom_notes'] : '(keine)',
         'embedded_policy' => $embedded_policy,
         'embedded_summary' => $this->embedded_summary_for_prompt($page_context),
         'zip_structure' => $this->zip_structure_update($hero_change),
         'assets_rules' => $this->assets_rules_update($hero_change, $in['hero_brief']),
         'page_id' => (string) $in['page_id'],
         'page_title' => (string) ($page['title'] ?? ''),
         'lng' => $in['lng'],
         'permalink' => (string) ($page['permalink'] ?? ''),
         'current_content_excerpt' => $excerpt,
         'content_markers_guide' => in_array('content', $in['change_fields'], true)
            ? $this->content_markers_guide($page_template, $hero_change)
            : '(Inhalt wird nicht geaendert.)',
         'bootstrap_components_guide' => in_array('content', $in['change_fields'], true)
            ? $this->bootstrap_components_guide($in['bootstrap_components'] ?? array())
            : '(Inhalt wird nicht geaendert.)',
      ));

      return array(
         'filename' => 'dbxki-auftrag-update-seite-' . (int) $in['page_id'] . '.zip',
         'files' => array_merge($this->pack_briefing_files(array(
            'recipe' => 'page.update.v1',
            'task_label' => 'Seite #' . $in['page_id'] . ' aendern',
            'hero_assets' => $hero_change,
            'content_template' => $page_template,
            'context_hint' => 'Ja — bisheriger Seiteninhalt',
            'manifest' => $manifest,
            'briefing' => $briefing,
            'job_vorlage' => $job_vorlage,
             'context' => $context,
             'auftrag' => $auftrag,
             'contract' => $contract,
          )), $hero_change ? array('assets/README.txt' => $this->assets_readme_hero($in['hero_brief'])) : array()),
      );
   }

   private function build_page_translate_package(array $in): array {
      if ($in['source_id'] <= 0) {
         throw new \InvalidArgumentException('Bitte eine Quellseite waehlen.');
      }
      $targets = $this->normalize_target_lngs((array) ($in['target_lngs'] ?? array()), true);
      if (!$targets) {
         throw new \InvalidArgumentException('Bitte mindestens eine Ziel- oder Korrektursprache waehlen.');
      }
      $in['target_lngs'] = $targets;
      $in['target_lng'] = (string) ($targets[0] ?? '');

      $source = $this->load_page($in['source_lng'], $in['source_id']);
      $source_context = $this->page_context_for_ki($in['source_lng'], $source);
      $styles = $this->writing_styles();
      $style_key = $in['writing_style'];
      if (!isset($styles[$style_key])) {
         $style_key = 'sachlich';
      }
      $target_labels = $this->target_instruction_labels($in['source_lng'], $targets);
      $has_corrections = in_array($in['source_lng'], $targets, true);
      $real_targets = array_values(array_filter($targets, function ($lng) use ($in) {
         return $lng !== $in['source_lng'];
      }));

      $briefing = array(
         'briefing_version' => self::BRIEFING_VERSION,
         'recipe' => 'translation.v1',
         'task' => 'page_translate',
         'source_lng' => $in['source_lng'],
         'target_lng' => $in['target_lng'],
         'target_lngs' => $targets,
         'targets' => $target_labels,
         'correction_mode_lngs' => $has_corrections ? array($in['source_lng']) : array(),
         'source_id' => $in['source_id'],
         'source_title' => (string) ($source['title'] ?? ''),
         'source_permalink' => (string) ($source['permalink'] ?? ''),
         'writing_style' => $style_key,
         'translation_notes' => $in['translation_notes'],
         'copy_media' => $in['copy_media'],
      );

      $job_vorlage = $this->job_vorlage_page_translate($in);
      $manifest = array(
         'bundle_version' => self::BRIEFING_VERSION,
         'title' => 'Uebersetzung: ' . ($source['title'] ?? ''),
         'recipe' => 'translation.v1',
         'source_lng' => $in['source_lng'],
         'target_lng' => $in['target_lng'],
         'target_lngs' => $targets,
         'intent' => $real_targets && $has_corrections ? 'translate_and_correct' : ($has_corrections ? 'correct' : 'translate'),
      );

      $context = array(
         'source_lng' => $in['source_lng'],
         'target_lng' => $in['target_lng'],
         'target_lngs' => $targets,
         'targets' => $target_labels,
         'source' => dbxKiValue::slim_page($source),
         'source_page_context' => $source_context,
      );
      $contract = $this->contracts()->create(
         'cms',
         'translation.v1',
         $manifest,
         $job_vorlage,
         $this->translation_output_definitions($in),
         array(),
         $this->page_snapshot($in['source_lng'], $source)
      );

      $auftrag = $this->render_template_file('ki-page-translation-auftrag.md', array(
         'writing_style_prompt' => (string) ($styles[$style_key]['prompt'] ?? ''),
         'translation_notes' => $in['translation_notes'] !== '' ? $in['translation_notes'] : '(keine)',
         'source_lng' => $in['source_lng'],
         'target_lng' => $in['target_lng'],
         'target_lngs' => implode(', ', array_map('strtoupper', $targets)),
         'target_instructions' => $this->target_instructions_for_prompt($in['source_lng'], $targets),
         'source_id' => (string) $in['source_id'],
         'source_title' => (string) ($source['title'] ?? ''),
         'source_permalink' => (string) ($source['permalink'] ?? ''),
         'render_reference' => (string) ($source_context['render_reference'] ?? ''),
         'embedded_summary' => $this->embedded_summary_for_prompt($source_context),
      ));

      return array(
         'filename' => 'dbxki-auftrag-uebersetzung-' . (int) $in['source_id'] . '-' . implode('-', $targets) . '.zip',
         'files' => $this->pack_briefing_files(array(
            'recipe' => 'translation.v1',
            'task_label' => 'Uebersetzung/Korrektur ' . strtoupper($in['source_lng']) . ' -> ' . implode(', ', array_map('strtoupper', $targets)),
            'hero_assets' => false,
            'context_hint' => 'Ja — Quelltext in source.content',
            'content_template' => (string) ($source_context['template'] ?? ''),
            'manifest' => $manifest,
            'briefing' => $briefing,
            'job_vorlage' => $job_vorlage,
             'context' => $context,
             'auftrag' => $auftrag,
             'contract' => $contract,
          )),
      );
   }

   private function job_vorlage_page_create(array $in): array {
      $steps = array();
      if ($in['hero_enabled']) {
         $steps[] = array(
            'id' => 'hero',
            'action' => 'media.create_base64',
            'params' => array(
               'file_name' => 'hero.jpg',
               'asset_ref' => 'hero.jpg',
               'media_folder' => 'img/hero',
               'title' => $in['title'],
               'alt' => '{{output:hero.alt}}',
            ),
         );
      }

      $page_params = array(
         'lng' => $in['lng'],
         'folder_id' => $in['folder_id'],
         'title' => $in['title'],
         'template' => $this->content_template_for_create($in['hero_enabled'], $in['content_template'] ?? ''),
         'activ' => $in['activ'] ? 1 : 0,
         'content' => '{{output:page.content}}',
      );
      if ($in['hero_enabled']) {
         $page_params['hero_height'] = self::HERO_DEFAULT_HEIGHT;
      }
      if (!empty($in['sorter'])) {
         $page_params['sorter'] = (string) $in['sorter'];
      }
      if ($in['permalink'] !== '') {
         $page_params['permalink'] = $in['permalink'];
      }
      if ($in['description'] !== '') {
         $page_params['description'] = $in['description'];
      }
      if ($in['keywords'] !== '') {
         $page_params['keywords'] = $in['keywords'];
      }

      $steps[] = array(
         'id' => 'page',
         'action' => 'page.create',
         'params' => $page_params,
      );

      if ($in['hero_enabled']) {
         $steps[] = array(
            'id' => 'hero_assign',
            'action' => 'media.assign',
            'params' => array(
               'media_id' => '$ref:hero.media_id',
               'content_id' => '$ref:page.page_id',
               'slot' => 'hero',
               'lng' => $in['lng'],
            ),
         );
      }

      return array('steps' => $steps);
   }

   private function job_vorlage_page_update(array $in, array $page): array {
      $patch = array();
      if (in_array('content', $in['change_fields'], true)) {
         $patch['content'] = '{{output:page.content}}';
      }
      if (in_array('title', $in['change_fields'], true)) {
         $patch['title'] = '{{output:page.title}}';
      }
      if (in_array('description', $in['change_fields'], true)) {
         $patch['description'] = '{{output:page.description}}';
      }
      if (in_array('hero', $in['change_fields'], true)) {
         $current_hero_height = trim((string) ($page['hero_height'] ?? ''));
         if ($current_hero_height === '' || $current_hero_height === 'parent') {
            $patch['hero_height'] = self::HERO_DEFAULT_HEIGHT;
         }
      }

      $steps = array();
      if (in_array('hero', $in['change_fields'], true)) {
         $steps[] = array(
            'id' => 'hero',
            'action' => $in['hero_mode'] === 'create' ? 'page.hero_create_image' : 'page.hero_replace_image',
            'params' => array(
               'lng' => $in['lng'],
               'id' => (int)$in['page_id'],
               'file_name' => 'hero.jpg',
               'asset_ref' => 'hero.jpg',
               'width' => self::HERO_DEFAULT_IMAGE_WIDTH,
               'height' => self::HERO_DEFAULT_IMAGE_HEIGHT,
               'fit' => 'cover',
               'title' => (string) ($page['title'] ?? 'Hero'),
               'alt' => '{{output:hero.alt}}',
            ),
         );
      }

      $steps[] = array(
         'id' => 'page',
         'action' => 'page.update',
         'params' => array(
            'lng' => $in['lng'],
            'id' => (int) $in['page_id'],
            'patch' => $patch,
         ),
      );

      return array('steps' => $steps);
   }

   private function job_vorlage_page_translate(array $in): array {
      $steps = array();
      foreach ((array) ($in['target_lngs'] ?? array($in['target_lng'])) as $target_lng) {
         $target_lng = strtolower(trim((string) $target_lng));
         if ($target_lng === '') {
            continue;
         }
         if ($target_lng === $in['source_lng']) {
            $steps[] = array(
               'id' => 'proofread_' . $target_lng,
               'action' => 'page.update',
               'params' => array(
                  'lng' => $in['source_lng'],
                  'id' => (int) $in['source_id'],
                  'patch' => array(
                     'title' => '{{output:proofread_' . $target_lng . '.title}}',
                     'description' => '{{output:proofread_' . $target_lng . '.description}}',
                     'keywords' => '{{output:proofread_' . $target_lng . '.keywords}}',
                     'content' => '{{output:proofread_' . $target_lng . '.content}}',
                  ),
               ),
            );
            continue;
         }
         $steps[] = array(
            'id' => 'translation_' . $target_lng,
            'action' => 'translation.apply',
            'params' => array(
               'source_lng' => $in['source_lng'],
               'target_lng' => $target_lng,
               'source_id' => (int) $in['source_id'],
               'copy_media' => $in['copy_media'] ? 1 : 0,
               'translation' => array(
                  'title' => '{{output:translation_' . $target_lng . '.title}}',
                  'description' => '{{output:translation_' . $target_lng . '.description}}',
                  'keywords' => '{{output:translation_' . $target_lng . '.keywords}}',
                  'content' => '{{output:translation_' . $target_lng . '.content}}',
               ),
            ),
         );
      }
      return array(
         'steps' => $steps,
      );
   }

   private function page_create_output_definitions(array $in): array {
      $outputs = array(
         'page.content' => array('type' => 'html', 'required' => true, 'allow_empty' => false),
      );
      if (!empty($in['hero_enabled'])) {
         $outputs['hero.alt'] = array('type' => 'string', 'required' => true, 'allow_empty' => false, 'max_length' => 254);
      }
      return $outputs;
   }

   private function page_update_output_definitions(array $in): array {
      $outputs = array();
      foreach ((array)($in['change_fields'] ?? array()) as $field) {
         if ($field === 'content') {
            $outputs['page.content'] = array('type' => 'html', 'required' => true, 'allow_empty' => false);
         } elseif ($field === 'title') {
            $outputs['page.title'] = array('type' => 'string', 'required' => true, 'allow_empty' => false, 'max_length' => 254);
         } elseif ($field === 'description') {
            $outputs['page.description'] = array('type' => 'string', 'required' => true, 'allow_empty' => true, 'max_length' => 254);
         } elseif ($field === 'hero') {
            $outputs['hero.alt'] = array('type' => 'string', 'required' => true, 'allow_empty' => false, 'max_length' => 254);
         }
      }
      return $outputs;
   }

   private function translation_output_definitions(array $in): array {
      $outputs = array();
      foreach ((array)($in['target_lngs'] ?? array()) as $target_lng) {
         $target_lng = strtolower(trim((string)$target_lng));
         if ($target_lng === '') continue;
         $prefix = ($target_lng === (string)$in['source_lng'] ? 'proofread_' : 'translation_') . $target_lng;
         $outputs[$prefix . '.title'] = array('type' => 'string', 'required' => true, 'allow_empty' => false, 'max_length' => 254);
         $outputs[$prefix . '.description'] = array('type' => 'string', 'required' => true, 'allow_empty' => true, 'max_length' => 254);
         $outputs[$prefix . '.keywords'] = array('type' => 'string', 'required' => true, 'allow_empty' => true, 'max_length' => 254);
         $outputs[$prefix . '.content'] = array('type' => 'html', 'required' => true, 'allow_empty' => true);
      }
      return $outputs;
   }

   private function page_snapshot(string $lng, array $page): array {
      $fields = array('id', 'title', 'permalink', 'description', 'keywords', 'content', 'folder', 'template', 'hero_height', 'lng_rev', 'update_date');
      $values = array();
      foreach ($fields as $field) {
         $values[$field] = $page[$field] ?? null;
      }
      return array(
         'type' => 'page',
         'lng' => strtolower(trim($lng)),
         'id' => (int)($page['id'] ?? 0),
         'fields' => $fields,
         'fingerprint' => $this->contracts()->fingerprint($values),
      );
   }

   private function folder_snapshot(string $lng, int $id): array {
      $row = dbx()->get_system_obj('dbxDB')->select1(dbxContentLng::dd_folder($lng), $id);
      if (!is_array($row)) throw new \RuntimeException('Zielordner fuer Snapshot nicht gefunden.');
      $fields = array('id', 'name', 'parent_id', 'sorter', 'template', 'activ', 'update_date');
      $values = array();
      foreach ($fields as $field) $values[$field] = $row[$field] ?? null;
      return array('type' => 'folder', 'lng' => strtolower(trim($lng)), 'id' => $id,
         'fields' => $fields, 'fingerprint' => $this->contracts()->fingerprint($values));
   }

   private function pack_briefing_files(array $in): array {
      $recipe = (string) ($in['recipe'] ?? '');
      $hero_assets = !empty($in['hero_assets']);
      $contract = is_array($in['contract'] ?? null) ? $in['contract'] : array();
      return array(
         '00-START.md' => $this->build_start_md(
            $recipe,
            (string) ($in['task_label'] ?? ''),
            $hero_assets,
            (string) ($in['context_hint'] ?? ''),
            (string) ($in['content_template'] ?? '')
         ),
         'briefing.json' => $in['briefing'],
         'context.json' => $in['context'],
         'KI-AUFTRAG.md' => $in['auftrag'],
         'bundle.rules.json' => $this->bundle_rules_for_recipe($recipe, $hero_assets, (string) ($in['content_template'] ?? '')),
         'auftrag.contract.json' => $contract,
         'answer.template.json' => $this->contracts()->answer_template($contract),
      );
   }

   private function send_zip_download(array $package): void {
      $tmp = tempnam(sys_get_temp_dir(), 'dbxki_brief');
      $zip = new \ZipArchive();
      if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
         @unlink($tmp);
         throw new \RuntimeException('ZIP konnte nicht erstellt werden.');
      }

      foreach ($package['files'] as $path => $content) {
         if (is_array($content)) {
            $content = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
         }
         $zip->addFromString($path, (string) $content);
      }
      $zip->close();

      $name = (string) ($package['filename'] ?? 'dbxki-auftrag.zip');
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
      header('Content-Length: ' . filesize($tmp));
      readfile($tmp);
      @unlink($tmp);
      exit;
   }
}
