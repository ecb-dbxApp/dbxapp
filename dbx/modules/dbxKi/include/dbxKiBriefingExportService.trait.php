<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingExportServiceTrait {

   public function handleExport(): void {
      $this->ensureContentBootstrap();
      if (!class_exists('ZipArchive')) {
         dbx()->json_response(array('ok' => 0, 'error' => 'ZipArchive nicht verfuegbar.'), true);
      }

      $recipe = strtolower(trim((string) dbx()->get_request_var('recipe', '', '*')));
      if (!in_array($recipe, array('page_create', 'page_update', 'page_translate'), true)) {
         dbx()->json_response(array('ok' => 0, 'error' => 'Unbekanntes Rezept: ' . $recipe), true);
         return;
      }

      $form = $this->briefingForm(
         $this->briefingFormId($recipe),
         'ki-briefing-' . str_replace('_', '-', $recipe),
         $this->moduleUrl('briefing_export')
      );
      if (!$form->submit()) {
         dbx()->json_response(array('ok' => 0, 'error' => 'Ungueltiger oder abgelaufener Formular-Token.'), true);
         return;
      }

      if ($recipe === 'page_create') {
         $package = $this->buildPageCreatePackage($this->collectPageCreateInput());
      } elseif ($recipe === 'page_update') {
         $package = $this->buildPageUpdatePackage($this->collectPageUpdateInput());
      } elseif ($recipe === 'page_translate') {
         $package = $this->buildPageTranslatePackage($this->collectPageTranslateInput());
      }

      $this->sendZipDownload($package);
   }

   private function collectPageCreateInput(): array {
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
         'bootstrap_components' => $this->selectedBootstrapComponentsFromRequest(),
         'activ' => dbx()->get_request_var('activ', '0', '*') === '1',
         'custom_notes' => trim((string) dbx()->get_request_var('custom_notes', '', '*')),
      );
   }

   private function collectPageUpdateInput(): array {
      $lng = strtolower(trim((string) dbx()->get_request_var('lng', dbxContentLng::current(), '*')));
      $changeFields = array();
      if (dbx()->get_request_var('change_content', '0', '*') === '1') {
         $changeFields[] = 'content';
      }
      if (dbx()->get_request_var('change_title', '0', '*') === '1') {
         $changeFields[] = 'title';
      }
      if (dbx()->get_request_var('change_description', '0', '*') === '1') {
         $changeFields[] = 'description';
      }
      if (dbx()->get_request_var('change_hero', '0', '*') === '1') {
         $changeFields[] = 'hero';
      }
      if (!$changeFields) {
         $changeFields = array('content');
      }

      return array(
         'recipe' => 'page_update',
         'lng' => $lng,
         'page_id' => max(0, (int) dbx()->get_request_var('page_id', 0, 'int')),
         'writing_style' => trim((string) dbx()->get_request_var('writing_style', 'sachlich', '*')),
         'change_brief' => trim((string) dbx()->get_request_var('change_brief', '', '*')),
         'change_fields' => $changeFields,
         'hero_brief' => trim((string) dbx()->get_request_var('hero_brief', '', '*')),
         'custom_notes' => trim((string) dbx()->get_request_var('custom_notes', '', '*')),
         'embedded_policy' => $this->sanitizeEmbeddedPolicy((string) dbx()->get_request_var('embedded_policy', 'preserve', '*')),
         'embedded_change_notes' => trim((string) dbx()->get_request_var('embedded_change_notes', '', '*')),
         'bootstrap_components' => $this->selectedBootstrapComponentsFromUpdateFields(),
      );
   }

   private function collectPageTranslateInput(): array {
      $sourceLng = strtolower(trim((string) dbx()->get_request_var('source_lng', dbxContentLng::current(), '*')));
      $targetLngs = $this->selectedTargetLngsFromRequest($sourceLng, false);
      return array(
         'recipe' => 'page_translate',
         'source_lng' => $sourceLng,
         'target_lng' => (string) ($targetLngs[0] ?? ''),
         'target_lngs' => $targetLngs,
         'source_id' => max(0, (int) dbx()->get_request_var('source_id', 0, 'int')),
         'writing_style' => trim((string) dbx()->get_request_var('writing_style', 'sachlich', '*')),
         'translation_notes' => trim((string) dbx()->get_request_var('translation_notes', '', '*')),
         'copy_media' => dbx()->get_request_var('copy_media', '1', '*') !== '0',
      );
   }

   private function buildPageCreatePackage(array $in): array {
      if ($in['folder_id'] <= 0) {
         throw new \InvalidArgumentException('Bitte einen Zielordner waehlen.');
      }
      if ($in['title'] === '') {
         throw new \InvalidArgumentException('Titel ist erforderlich.');
      }
      if ($in['content_brief'] === '') {
         throw new \InvalidArgumentException('Beschreiben Sie, worum es im Text gehen soll.');
      }

      $sorterAfterPage = array();
      $sorterAfterLabel = '';
      $sorterValue = '';
      if ($in['sorter_after_page_id'] > 0) {
         try {
            $sorterAfterPage = $this->loadPage($in['lng'], $in['sorter_after_page_id']);
            $in['folder_id'] = (int) ($sorterAfterPage['folder'] ?? $in['folder_id']);
            $sorterAfterLabel = '#' . (int) $in['sorter_after_page_id'] . ' ' . (string) ($sorterAfterPage['title'] ?? '');
            $sorterValue = $this->sorterAfterPage($in['lng'], $in['sorter_after_page_id']);
         } catch (\Throwable $e) {
            $in['sorter_after_page_id'] = 0;
         }
      }
      $in['sorter'] = $sorterValue;
      $folderLabel = $this->folderLabel($in['lng'], $in['folder_id']);
      $styles = $this->writingStyles();
      $styleKey = $in['writing_style'];
      if (!isset($styles[$styleKey])) {
         $styleKey = 'sachlich';
      }
      $stylePrompt = (string) ($styles[$styleKey]['prompt'] ?? '');

      $permalink = $in['permalink'];
      if ($permalink === '') {
         $permalink = '(automatisch aus Titel)';
      }

      $contentTemplate = $this->contentTemplateForCreate($in['hero_enabled'], $in['content_template'] ?? '');
      $templateSlots = $this->analyzeTemplateSlots($contentTemplate);

      $briefing = array(
         'briefing_version' => self::BRIEFING_VERSION,
         'recipe' => 'page.create.v1',
         'task' => 'page_create',
         'created_at' => date('c'),
         'lng' => $in['lng'],
         'folder_id' => $in['folder_id'],
         'folder_label' => $folderLabel,
         'sorter_after_page_id' => $in['sorter_after_page_id'],
         'sorter_after_label' => $sorterAfterLabel,
         'sorter' => $sorterValue,
         'title' => $in['title'],
         'permalink' => $in['permalink'],
         'description' => $in['description'],
         'keywords' => $in['keywords'],
         'activ' => $in['activ'],
         'writing_style' => $styleKey,
         'content' => array('brief' => $in['content_brief']),
         'hero' => array(
            'enabled' => $in['hero_enabled'],
            'brief' => $in['hero_brief'],
            'image' => $this->heroImageBriefingMeta(),
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
         'content_template' => $contentTemplate,
         'template_slots' => $templateSlots,
         'content_markers' => $this->contentMarkersMeta($templateSlots),
         'bootstrap_components' => $this->bootstrapComponentsMeta($in['bootstrap_components'] ?? array()),
         'custom_notes' => $in['custom_notes'],
      );

      $jobVorlage = $this->jobVorlagePageCreate($in);
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
         'target_folder_label' => $folderLabel,
         'sorter_after_page_id' => $in['sorter_after_page_id'],
         'sorter_after' => $this->slimPageForUpdate($sorterAfterPage),
         'sorter' => $sorterValue,
      );
      $contract = $this->contracts()->create(
         'cms',
         'page.create.v1',
         $manifest,
         $jobVorlage,
         $this->pageCreateOutputDefinitions($in),
         $this->heroAssetDefinitions($in['hero_enabled']),
         $this->folderSnapshot($in['lng'], (int)$in['folder_id'])
      );

      $auftrag = $this->renderTemplateFile('ki-page-create-auftrag.md', array(
         'writing_style_prompt' => $stylePrompt,
         'content_brief' => $in['content_brief'],
         'custom_notes' => $in['custom_notes'] !== '' ? $in['custom_notes'] : '(keine)',
         'zip_structure' => $this->zipStructureCreate($in['hero_enabled']),
         'assets_rules' => $this->assetsRulesCreate($in['hero_enabled'], $in['hero_brief']),
         'lng' => $in['lng'],
         'folder_id' => (string) $in['folder_id'],
         'folder_label' => $folderLabel,
         'sorter_after_label' => $sorterAfterLabel !== '' ? $sorterAfterLabel : '(am Ende des Ordners)',
         'sorter' => $sorterValue !== '' ? $sorterValue : '(automatisch am Ende)',
         'title' => $in['title'],
         'permalink' => $permalink,
         'description' => $in['description'] !== '' ? $in['description'] : '(optional)',
         'keywords' => $in['keywords'] !== '' ? $in['keywords'] : '(optional)',
         'activ' => $in['activ'] ? '1 (aktiv)' : '0 (inaktiv)',
         'content_template' => $contentTemplate,
         'hero_text_brief' => ($in['hero_text_brief'] ?? '') !== '' ? $in['hero_text_brief'] : '(optional — Kurztext im Hero-Bereich)',
         'content_markers_guide' => $this->contentMarkersGuide($contentTemplate, $in['hero_enabled'], $in['hero_text_brief'] ?? ''),
         'bootstrap_components_guide' => $this->bootstrapComponentsGuide($in['bootstrap_components'] ?? array()),
      ));

      $files = $this->packBriefingFiles(array(
         'recipe' => 'page.create.v1',
         'task_label' => 'Neue CMS-Seite: ' . $in['title'],
         'hero_assets' => $in['hero_enabled'],
         'content_template' => $contentTemplate,
         'context_hint' => 'Nein — Ordner und Sortierung stehen in briefing.json',
         'manifest' => $manifest,
         'briefing' => $briefing,
         'job_vorlage' => $jobVorlage,
          'context' => $context,
          'auftrag' => $auftrag,
          'contract' => $contract,
       ));
      if ($in['hero_enabled']) {
         $files['assets/README.txt'] = $this->assetsReadmeHero($in['hero_brief']);
      }

      return array(
         'filename' => 'dbxki-auftrag-neue-seite-' . preg_replace('/[^a-z0-9_-]+/i', '-', $in['title']) . '.zip',
         'files' => $files,
      );
   }

   private function buildPageUpdatePackage(array $in): array {
      if ($in['page_id'] <= 0) {
         throw new \InvalidArgumentException('Bitte eine Seite waehlen.');
      }
      if ($in['change_brief'] === '') {
         throw new \InvalidArgumentException('Beschreiben Sie die gewuenschte Aenderung.');
      }

      $page = $this->loadPage($in['lng'], $in['page_id']);
      $styles = $this->writingStyles();
      $styleKey = $in['writing_style'];
      if (!isset($styles[$styleKey])) {
         $styleKey = 'sachlich';
      }
      $pageTemplate = trim((string) ($page['template'] ?? 'parent'));
      if ($pageTemplate === '' || $pageTemplate === 'parent') {
         $pageTemplate = 'c-body1-footer';
      }
      $pageContext = $this->pageContextForKi($in['lng'], $page);
      $embeddedPolicy = $this->embeddedPolicyText($in['embedded_policy'], $in['embedded_change_notes']);

      $briefing = array(
         'briefing_version' => self::BRIEFING_VERSION,
         'recipe' => 'page.update.v1',
         'task' => 'page_update',
         'lng' => $in['lng'],
         'page_id' => $in['page_id'],
         'page_title' => (string) ($page['title'] ?? ''),
         'permalink' => (string) ($page['permalink'] ?? ''),
         'writing_style' => $styleKey,
         'change_brief' => $in['change_brief'],
         'change_fields' => $in['change_fields'],
         'content_template' => $pageTemplate,
         'bootstrap_components' => $this->bootstrapComponentsMeta($in['bootstrap_components'] ?? array()),
         'embedded_content_policy' => array(
            'mode' => $in['embedded_policy'],
            'notes' => $in['embedded_change_notes'],
            'default' => 'preserve existing embedded media and module calls exactly',
         ),
         'hero' => array(
            'brief' => $in['hero_brief'],
            'mode' => $in['hero_mode'],
            'image' => $this->heroImageBriefingMeta(),
         ),
         'custom_notes' => $in['custom_notes'],
      );

      $jobVorlage = $this->jobVorlagePageUpdate($in, $page);
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
         'current_page' => $this->slimPageForUpdate($page),
         'current_page_context' => $pageContext,
      );
      $contract = $this->contracts()->create(
         'cms',
         'page.update.v1',
         $manifest,
         $jobVorlage,
         $this->pageUpdateOutputDefinitions($in),
         $this->heroAssetDefinitions(in_array('hero', $in['change_fields'], true)),
         $this->pageSnapshot($in['lng'], $page)
      );

      $excerpt = $this->truncate((string) ($page['content'] ?? ''), 4000);

      $heroChange = in_array('hero', $in['change_fields'], true);

      $auftrag = $this->renderTemplateFile('ki-page-update-auftrag.md', array(
         'writing_style_prompt' => (string) ($styles[$styleKey]['prompt'] ?? ''),
         'change_brief' => $in['change_brief'],
         'custom_notes' => $in['custom_notes'] !== '' ? $in['custom_notes'] : '(keine)',
         'embedded_policy' => $embeddedPolicy,
         'embedded_summary' => $this->embeddedSummaryForPrompt($pageContext),
         'zip_structure' => $this->zipStructureUpdate($heroChange),
         'assets_rules' => $this->assetsRulesUpdate($heroChange, $in['hero_brief']),
         'page_id' => (string) $in['page_id'],
         'page_title' => (string) ($page['title'] ?? ''),
         'lng' => $in['lng'],
         'permalink' => (string) ($page['permalink'] ?? ''),
         'current_content_excerpt' => $excerpt,
         'content_markers_guide' => in_array('content', $in['change_fields'], true)
            ? $this->contentMarkersGuide($pageTemplate, $heroChange)
            : '(Inhalt wird nicht geaendert.)',
         'bootstrap_components_guide' => in_array('content', $in['change_fields'], true)
            ? $this->bootstrapComponentsGuide($in['bootstrap_components'] ?? array())
            : '(Inhalt wird nicht geaendert.)',
      ));

      return array(
         'filename' => 'dbxki-auftrag-update-seite-' . (int) $in['page_id'] . '.zip',
         'files' => array_merge($this->packBriefingFiles(array(
            'recipe' => 'page.update.v1',
            'task_label' => 'Seite #' . $in['page_id'] . ' aendern',
            'hero_assets' => $heroChange,
            'content_template' => $pageTemplate,
            'context_hint' => 'Ja — bisheriger Seiteninhalt',
            'manifest' => $manifest,
            'briefing' => $briefing,
            'job_vorlage' => $jobVorlage,
             'context' => $context,
             'auftrag' => $auftrag,
             'contract' => $contract,
          )), $heroChange ? array('assets/README.txt' => $this->assetsReadmeHero($in['hero_brief'])) : array()),
      );
   }

   private function buildPageTranslatePackage(array $in): array {
      if ($in['source_id'] <= 0) {
         throw new \InvalidArgumentException('Bitte eine Quellseite waehlen.');
      }
      $targets = $this->normalizeTargetLngs((array) ($in['target_lngs'] ?? array()), true);
      if (!$targets) {
         throw new \InvalidArgumentException('Bitte mindestens eine Ziel- oder Korrektursprache waehlen.');
      }
      $in['target_lngs'] = $targets;
      $in['target_lng'] = (string) ($targets[0] ?? '');

      $source = $this->loadPage($in['source_lng'], $in['source_id']);
      $sourceContext = $this->pageContextForKi($in['source_lng'], $source);
      $styles = $this->writingStyles();
      $styleKey = $in['writing_style'];
      if (!isset($styles[$styleKey])) {
         $styleKey = 'sachlich';
      }
      $targetLabels = $this->targetInstructionLabels($in['source_lng'], $targets);
      $hasCorrections = in_array($in['source_lng'], $targets, true);
      $realTargets = array_values(array_filter($targets, function ($lng) use ($in) {
         return $lng !== $in['source_lng'];
      }));

      $briefing = array(
         'briefing_version' => self::BRIEFING_VERSION,
         'recipe' => 'translation.v1',
         'task' => 'page_translate',
         'source_lng' => $in['source_lng'],
         'target_lng' => $in['target_lng'],
         'target_lngs' => $targets,
         'targets' => $targetLabels,
         'correction_mode_lngs' => $hasCorrections ? array($in['source_lng']) : array(),
         'source_id' => $in['source_id'],
         'source_title' => (string) ($source['title'] ?? ''),
         'source_permalink' => (string) ($source['permalink'] ?? ''),
         'writing_style' => $styleKey,
         'translation_notes' => $in['translation_notes'],
         'copy_media' => $in['copy_media'],
      );

      $jobVorlage = $this->jobVorlagePageTranslate($in);
      $manifest = array(
         'bundle_version' => self::BRIEFING_VERSION,
         'title' => 'Uebersetzung: ' . ($source['title'] ?? ''),
         'recipe' => 'translation.v1',
         'source_lng' => $in['source_lng'],
         'target_lng' => $in['target_lng'],
         'target_lngs' => $targets,
         'intent' => $realTargets && $hasCorrections ? 'translate_and_correct' : ($hasCorrections ? 'correct' : 'translate'),
      );

      $context = array(
         'source_lng' => $in['source_lng'],
         'target_lng' => $in['target_lng'],
         'target_lngs' => $targets,
         'targets' => $targetLabels,
         'source' => $this->slimPageForTranslation($source),
         'source_page_context' => $sourceContext,
      );
      $contract = $this->contracts()->create(
         'cms',
         'translation.v1',
         $manifest,
         $jobVorlage,
         $this->translationOutputDefinitions($in),
         array(),
         $this->pageSnapshot($in['source_lng'], $source)
      );

      $auftrag = $this->renderTemplateFile('ki-page-translation-auftrag.md', array(
         'writing_style_prompt' => (string) ($styles[$styleKey]['prompt'] ?? ''),
         'translation_notes' => $in['translation_notes'] !== '' ? $in['translation_notes'] : '(keine)',
         'source_lng' => $in['source_lng'],
         'target_lng' => $in['target_lng'],
         'target_lngs' => implode(', ', array_map('strtoupper', $targets)),
         'target_instructions' => $this->targetInstructionsForPrompt($in['source_lng'], $targets),
         'source_id' => (string) $in['source_id'],
         'source_title' => (string) ($source['title'] ?? ''),
         'source_permalink' => (string) ($source['permalink'] ?? ''),
         'render_reference' => (string) ($sourceContext['render_reference'] ?? ''),
         'embedded_summary' => $this->embeddedSummaryForPrompt($sourceContext),
      ));

      return array(
         'filename' => 'dbxki-auftrag-uebersetzung-' . (int) $in['source_id'] . '-' . implode('-', $targets) . '.zip',
         'files' => $this->packBriefingFiles(array(
            'recipe' => 'translation.v1',
            'task_label' => 'Uebersetzung/Korrektur ' . strtoupper($in['source_lng']) . ' -> ' . implode(', ', array_map('strtoupper', $targets)),
            'hero_assets' => false,
            'context_hint' => 'Ja — Quelltext in source.content',
            'content_template' => (string) ($sourceContext['template'] ?? ''),
            'manifest' => $manifest,
            'briefing' => $briefing,
            'job_vorlage' => $jobVorlage,
             'context' => $context,
             'auftrag' => $auftrag,
             'contract' => $contract,
          )),
      );
   }

   private function jobVorlagePageCreate(array $in): array {
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

      $pageParams = array(
         'lng' => $in['lng'],
         'folder_id' => $in['folder_id'],
         'title' => $in['title'],
         'template' => $this->contentTemplateForCreate($in['hero_enabled'], $in['content_template'] ?? ''),
         'activ' => $in['activ'] ? 1 : 0,
         'content' => '{{output:page.content}}',
      );
      if ($in['hero_enabled']) {
         $pageParams['hero_height'] = self::HERO_DEFAULT_HEIGHT;
      }
      if (!empty($in['sorter'])) {
         $pageParams['sorter'] = (string) $in['sorter'];
      }
      if ($in['permalink'] !== '') {
         $pageParams['permalink'] = $in['permalink'];
      }
      if ($in['description'] !== '') {
         $pageParams['description'] = $in['description'];
      }
      if ($in['keywords'] !== '') {
         $pageParams['keywords'] = $in['keywords'];
      }

      $steps[] = array(
         'id' => 'page',
         'action' => 'page.create',
         'params' => $pageParams,
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

   private function jobVorlagePageUpdate(array $in, array $page): array {
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
         $currentHeroHeight = trim((string) ($page['hero_height'] ?? ''));
         if ($currentHeroHeight === '' || $currentHeroHeight === 'parent') {
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

   private function jobVorlagePageTranslate(array $in): array {
      $steps = array();
      foreach ((array) ($in['target_lngs'] ?? array($in['target_lng'])) as $targetLng) {
         $targetLng = strtolower(trim((string) $targetLng));
         if ($targetLng === '') {
            continue;
         }
         if ($targetLng === $in['source_lng']) {
            $steps[] = array(
               'id' => 'proofread_' . $targetLng,
               'action' => 'page.update',
               'params' => array(
                  'lng' => $in['source_lng'],
                  'id' => (int) $in['source_id'],
                  'patch' => array(
                     'title' => '{{output:proofread_' . $targetLng . '.title}}',
                     'description' => '{{output:proofread_' . $targetLng . '.description}}',
                     'keywords' => '{{output:proofread_' . $targetLng . '.keywords}}',
                     'content' => '{{output:proofread_' . $targetLng . '.content}}',
                  ),
               ),
            );
            continue;
         }
         $steps[] = array(
            'id' => 'translation_' . $targetLng,
            'action' => 'translation.apply',
            'params' => array(
               'source_lng' => $in['source_lng'],
               'target_lng' => $targetLng,
               'source_id' => (int) $in['source_id'],
               'copy_media' => $in['copy_media'] ? 1 : 0,
               'translation' => array(
                  'title' => '{{output:translation_' . $targetLng . '.title}}',
                  'description' => '{{output:translation_' . $targetLng . '.description}}',
                  'keywords' => '{{output:translation_' . $targetLng . '.keywords}}',
                  'content' => '{{output:translation_' . $targetLng . '.content}}',
               ),
            ),
         );
      }
      return array(
         'steps' => $steps,
      );
   }

   private function pageCreateOutputDefinitions(array $in): array {
      $outputs = array(
         'page.content' => array('type' => 'html', 'required' => true, 'allow_empty' => false),
      );
      if (!empty($in['hero_enabled'])) {
         $outputs['hero.alt'] = array('type' => 'string', 'required' => true, 'allow_empty' => false, 'max_length' => 254);
      }
      return $outputs;
   }

   private function pageUpdateOutputDefinitions(array $in): array {
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

   private function translationOutputDefinitions(array $in): array {
      $outputs = array();
      foreach ((array)($in['target_lngs'] ?? array()) as $targetLng) {
         $targetLng = strtolower(trim((string)$targetLng));
         if ($targetLng === '') continue;
         $prefix = ($targetLng === (string)$in['source_lng'] ? 'proofread_' : 'translation_') . $targetLng;
         $outputs[$prefix . '.title'] = array('type' => 'string', 'required' => true, 'allow_empty' => false, 'max_length' => 254);
         $outputs[$prefix . '.description'] = array('type' => 'string', 'required' => true, 'allow_empty' => true, 'max_length' => 254);
         $outputs[$prefix . '.keywords'] = array('type' => 'string', 'required' => true, 'allow_empty' => true, 'max_length' => 254);
         $outputs[$prefix . '.content'] = array('type' => 'html', 'required' => true, 'allow_empty' => true);
      }
      return $outputs;
   }

   private function pageSnapshot(string $lng, array $page): array {
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

   private function folderSnapshot(string $lng, int $id): array {
      $row = dbx()->get_system_obj('dbxDB')->select1(dbxContentLng::ddFolder($lng), $id);
      if (!is_array($row)) throw new \RuntimeException('Zielordner fuer Snapshot nicht gefunden.');
      $fields = array('id', 'name', 'parent_id', 'sorter', 'template', 'activ', 'update_date');
      $values = array();
      foreach ($fields as $field) $values[$field] = $row[$field] ?? null;
      return array('type' => 'folder', 'lng' => strtolower(trim($lng)), 'id' => $id,
         'fields' => $fields, 'fingerprint' => $this->contracts()->fingerprint($values));
   }

   private function packBriefingFiles(array $in): array {
      $recipe = (string) ($in['recipe'] ?? '');
      $heroAssets = !empty($in['hero_assets']);
      $contract = is_array($in['contract'] ?? null) ? $in['contract'] : array();
      return array(
         '00-START.md' => $this->buildStartMd(
            $recipe,
            (string) ($in['task_label'] ?? ''),
            $heroAssets,
            (string) ($in['context_hint'] ?? ''),
            (string) ($in['content_template'] ?? '')
         ),
         'briefing.json' => $in['briefing'],
         'context.json' => $in['context'],
         'KI-AUFTRAG.md' => $in['auftrag'],
         'bundle.rules.json' => $this->bundleRulesForRecipe($recipe, $heroAssets, (string) ($in['content_template'] ?? '')),
         'auftrag.contract.json' => $contract,
         'answer.template.json' => $this->contracts()->answerTemplate($contract),
      );
   }

   private function slimPageForUpdate(array $page): array {
      return array(
         'id' => (int) ($page['id'] ?? 0),
         'title' => (string) ($page['title'] ?? ''),
         'permalink' => (string) ($page['permalink'] ?? ''),
         'description' => (string) ($page['description'] ?? ''),
         'keywords' => (string) ($page['keywords'] ?? ''),
         'content' => (string) ($page['content'] ?? ''),
      );
   }

   private function slimPageForTranslation(array $page): array {
      return array(
         'id' => (int) ($page['id'] ?? 0),
         'title' => (string) ($page['title'] ?? ''),
         'permalink' => (string) ($page['permalink'] ?? ''),
         'description' => (string) ($page['description'] ?? ''),
         'keywords' => (string) ($page['keywords'] ?? ''),
         'content' => (string) ($page['content'] ?? ''),
      );
   }

   private function sendZipDownload(array $package): void {
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
