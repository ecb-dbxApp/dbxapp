<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
require_once __DIR__ . '/dbxKiWritingStyles.class.php';

class dbxKiBriefingService {

   private const BRIEFING_VERSION = '1.3';
   private const HERO_MAX_WIDTH = 1280;
   private const HERO_MAX_HEIGHT = 400;
   private const HERO_OPTIMAL_WIDTH = 1280;
   private const HERO_OPTIMAL_HEIGHT = 300;
   private const HERO_DEFAULT_HEIGHT = '300px';
   private const HERO_DEFAULT_IMAGE_WIDTH = 1280;
   private const HERO_DEFAULT_IMAGE_HEIGHT = 300;
   private const HERO_TEXT_MAX_LINES = 3;
   private const CONTENT_TEMPLATE_DEFAULT = 'c-title-hero_header-body1-footer';

   private function contentTemplateDir(): string {
      $dir = dbx()->get_system_var('dbx_dir', '') . '/modules/dbxContent/tpl/htm/';
      if (!is_dir($dir)) {
         $dir = dirname(__DIR__, 2) . '/dbxContent/tpl/htm/';
      }
      return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
   }

   private function listContentTemplates(): array {
      $files = glob($this->contentTemplateDir() . 'c-*.htm');
      $out = array();
      if (is_array($files)) {
         sort($files);
         foreach ($files as $file) {
            $out[] = basename($file, '.htm');
         }
      }
      return $out ?: array('c-content');
   }

   private function sanitizeContentTemplate(string $template, bool $heroEnabled): string {
      $template = trim($template);
      $allowed = $this->listContentTemplates();
      if ($template === '' || $template === 'parent') {
         return $heroEnabled ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      if (!in_array($template, $allowed, true)) {
         return $heroEnabled ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      if (!$heroEnabled && strpos($template, 'hero') !== false) {
         return 'parent';
      }
      return $template;
   }

   private function analyzeTemplateSlots(string $template): array {
      if ($template === '' || $template === 'parent') {
         return array(
            'hero_text' => false,
            'header' => false,
            'footer' => false,
            'cols' => 1,
            'gallery' => false,
         );
      }
      $path = $this->contentTemplateDir() . $template . '.htm';
      if (!is_file($path)) {
         return array('hero_text' => true, 'header' => true, 'footer' => true, 'cols' => 1, 'gallery' => false);
      }
      $html = (string) file_get_contents($path);
      $slots = array(
         'hero_text' => strpos($html, '{cms:hero_text}') !== false,
         'header' => strpos($html, '{cms:header}') !== false,
         'footer' => strpos($html, '{cms:footer}') !== false,
         'gallery' => strpos($html, '{cms:gallery}') !== false,
         'cols' => 1,
      );
      if (strpos($html, '{cms:col3}') !== false) {
         $slots['cols'] = 3;
      } elseif (strpos($html, '{cms:col2}') !== false) {
         $slots['cols'] = 2;
      }
      return $slots;
   }

   private function buildContentTemplateOptions(string $selected, bool $heroEnabled): string {
      $selected = $this->sanitizeContentTemplate($selected, $heroEnabled);
      $html = '';
      if (!$heroEnabled) {
         $html .= '<option value="parent"' . ($selected === 'parent' ? ' selected' : '') . '>parent — vom Ordner</option>';
      }
      foreach ($this->listContentTemplates() as $name) {
         if (!$heroEnabled && strpos($name, 'hero') !== false) {
            continue;
         }
         if ($heroEnabled && strpos($name, 'hero') === false && $name !== 'c-content') {
            continue;
         }
         $sel = ($name === $selected) ? ' selected' : '';
         $html .= '<option value="' . $this->esc($name) . '"' . $sel . '>' . $this->esc($name) . '</option>';
      }
      return $html;
   }

   private function contentTemplateForCreate(bool $heroEnabled, string $selected = ''): string {
      return $this->sanitizeContentTemplate($selected, $heroEnabled);
   }

   private function contentMarkerHr(string $name): string {
      $labels = array(
         'hero' => 'Hero-Text',
         'header' => 'Header',
         'footer' => 'Footer',
      );
      $name = strtolower(trim($name));
      $label = $labels[$name] ?? $name;
      return '<hr class="dbx-cms-marker dbx-cms-marker-' . $name
         . '" contenteditable="false" data-dbx-marker="dbx:' . $name
         . '" data-label="' . $label . '">';
   }

   private function contentMarkersMeta(array $slots): array {
      $markers = array();
      if (!empty($slots['hero_text'])) {
         $markers['hero'] = $this->contentMarkerHr('hero');
      }
      if (!empty($slots['header'])) {
         $markers['header'] = $this->contentMarkerHr('header');
      }
      if (!empty($slots['footer'])) {
         $markers['footer'] = $this->contentMarkerHr('footer');
      }
      return $markers;
   }

   private function contentExampleHtml(array $slots, string $heroTextHint = ''): string {
      $parts = array();
      if (!empty($slots['hero_text'])) {
         $lead = $heroTextHint !== '' ? $heroTextHint : 'Kurzer Hero-Text';
         $parts[] = '<p class="lead">' . $lead . '</p>';
         $parts[] = $this->contentMarkerHr('hero');
      }
      if (!empty($slots['header'])) {
         $parts[] = $this->contentMarkerHr('header');
      }
      $parts[] = '<h2>Ueberschrift</h2><p>Haupttext...</p>';
      if (!empty($slots['footer'])) {
         $parts[] = $this->contentMarkerHr('footer');
         $parts[] = '<p><small>Optionale Fusszeile</small></p>';
      }
      return implode('', $parts);
   }

   private function contentMarkersGuide(string $template, bool $withHeroImage, string $heroTextBrief = ''): string {
      $slots = $this->analyzeTemplateSlots($template);
      $markers = $this->contentMarkersMeta($slots);
      $example = $this->contentExampleHtml($slots, $heroTextBrief);

      $lines = array(
         '## Content-Template und Bereichs-Marker',
         '',
         'Content-Template: `' . $template . '`',
         '',
         'Der Inhalt in `content` wird mit **`<hr>`-Markern** getrennt (Reihenfolge von oben nach unten):',
         '',
      );

      if (!empty($slots['hero_text'])) {
         $lines[] = '1. Text **vor** Hero-Marker → Slot `{cms:hero_text}` (Text im Hero-Bereich neben/unter dem Bild)';
         $lines[] = '   Standard: maximal ' . self::HERO_TEXT_MAX_LINES . ' Zeilen Hero-Text, wenn nicht anders angegeben.';
         $lines[] = '2. `<hr data-dbx-marker="dbx:hero">`';
         if ($heroTextBrief !== '') {
            $lines[] = '   Hero-Text laut Auftrag: *' . $heroTextBrief . '*';
         }
      }
      if (!empty($slots['header'])) {
         $lines[] = '- Text zwischen Hero- und Header-Marker → `{cms:header}` (eigener Block zwischen Hero und Body)';
         $lines[] = '- `<hr data-dbx-marker="dbx:header">`';
      }
      $lines[] = '- Text bis Footer-Marker → Body (`{cms:col1}`' . ((int)($slots['cols'] ?? 1) > 1 ? ' / Spalten' : '') . ')';
      if (!empty($slots['footer'])) {
         $lines[] = '- `<hr data-dbx-marker="dbx:footer">`';
         $lines[] = '- Text danach → `{cms:footer}`';
      }
      $lines[] = '';
      $lines[] = 'Fehlende Marker: der jeweilige Bereich entfaellt, der Text gehoert zum Body.';
      $lines[] = '**Spalten-Marker (`col2`, `col3a`, `col3b`) nicht setzen** — werden manuell im CMS gesetzt.';
      $lines[] = '';
      foreach ($markers as $name => $hr) {
         $lines[] = '**' . ucfirst($name) . '-Marker:**';
         $lines[] = '```html';
         $lines[] = $hr;
         $lines[] = '```';
         $lines[] = '';
      }
      $lines[] = '**Beispiel `content` fuer dieses Template:**';
      $lines[] = '```html';
      $lines[] = $example;
      $lines[] = '```';
      if ($withHeroImage) {
         $lines[] = '';
         $lines[] = 'Hero-**Bild** kommt ueber dbxKi-Medienschritte — nicht ins HTML. Neue Hero-Bilder liegen verbindlich in `img/hero`.';
      }
      return implode("\n", $lines);
   }

   private function contentMarkersGuideShort(string $template): string {
      $slots = $this->analyzeTemplateSlots($template);
      $bits = array('Template `' . $template . '`');
      if (!empty($slots['hero_text'])) {
         $bits[] = 'Hero-/Header-/Footer-Marker per `<hr>`';
      } elseif (!empty($slots['header']) || !empty($slots['footer'])) {
         $bits[] = 'Header-/Footer-Marker per `<hr>`';
      }
      $bits[] = 'keine Spalten-Marker';
      $bits[] = 'Bootstrap-5-Content-Komponenten nur wenn im Auftrag ausgewaehlt, nur Bootstrap-Klassen, kein eigenes CSS/JS';
      return implode('; ', $bits) . '.';
   }

   private function allowedBootstrapComponents(): array {
      return array(
         'alert' => array(
            'label' => 'Hinweis',
            'classes' => 'alert alert-info / alert-warning / alert-success',
            'use' => 'Kurze Hinweis-, Info- oder Erfolgsbox.',
         ),
         'card' => array(
            'label' => 'Cards',
            'classes' => 'card, card-body, row, row-cols-*, g-*',
            'use' => 'Teaser, Leistungsboxen oder Paket-/Feature-Kacheln.',
         ),
         'list_group' => array(
            'label' => 'Listenbox',
            'classes' => 'list-group, list-group-item',
            'use' => 'Kompakte Nutzen-, Schritt- oder Funktionslisten.',
         ),
         'badges' => array(
            'label' => 'Badges',
            'classes' => 'badge text-bg-*',
            'use' => 'Status, Kategorien, kleine Hervorhebungen.',
         ),
         'buttons' => array(
            'label' => 'Buttons',
            'classes' => 'btn btn-primary / btn-outline-primary',
            'use' => 'CTA-Links ohne eigenes JavaScript.',
         ),
         'table' => array(
            'label' => 'Tabelle',
            'classes' => 'table table-striped table-hover',
            'use' => 'Vergleichs- oder Preis-/Datenuebersichten.',
         ),
         'accordion' => array(
            'label' => 'Akkordeon',
            'classes' => 'accordion, accordion-item, accordion-button',
            'use' => 'FAQ oder aufklappbare Detailbereiche.',
         ),
         'tabs' => array(
            'label' => 'Tabs',
            'classes' => 'nav nav-tabs, tab-content, tab-pane',
            'use' => 'Alternative Sichten auf denselben Inhalt.',
         ),
      );
   }

   private function selectedBootstrapComponentsFromRequest(): array {
      $raw = dbx()->get_request_var('bootstrap_components', array(), '*');
      if (!is_array($raw)) {
         $raw = $raw === '' ? array() : explode(',', (string) $raw);
      }
      $allowed = $this->allowedBootstrapComponents();
      $out = array();
      foreach ($raw as $key) {
         $key = strtolower(trim((string) $key));
         if (isset($allowed[$key]) && !in_array($key, $out, true)) {
            $out[] = $key;
         }
      }
      return $out;
   }

   private function buildBootstrapComponentChoices(array $selected): string {
      $html = '';
      foreach ($this->allowedBootstrapComponents() as $key => $meta) {
         $checked = in_array($key, $selected, true) ? ' checked' : '';
         $html .= '<label><input type="checkbox" name="bootstrap_components[]" value="' . $this->esc($key) . '"' . $checked . '>'
            . '<span><strong>' . $this->esc($meta['label'] ?? $key) . '</strong><small>' . $this->esc($meta['use'] ?? '') . '</small></span></label>';
      }
      return $html;
   }

   private function bootstrapComponentsMeta(array $selected): array {
      $allowed = $this->allowedBootstrapComponents();
      $out = array();
      foreach ($selected as $key) {
         if (isset($allowed[$key])) {
            $out[$key] = $allowed[$key];
         }
      }
      return $out;
   }

   private function bootstrapComponentsGuide(array $selected): string {
      $meta = $this->bootstrapComponentsMeta($selected);
      if (!$meta) {
         return "Keine Bootstrap-Komponenten im Content verwenden. Erlaubt sind nur normales Jodit-HTML wie h2, h3, p, ul/ol, Links und einfache Textstruktur.";
      }
      $lines = array(
         'Die KI darf im Content nur diese ausgewaehlten Bootstrap-5-Komponenten verwenden:',
         '',
      );
      foreach ($meta as $key => $row) {
         $lines[] = '- `' . $key . '` (' . ($row['label'] ?? $key) . '): ' . ($row['use'] ?? '') . ' Klassen: `' . ($row['classes'] ?? '') . '`.';
      }
      $lines[] = '';
      $lines[] = 'Nicht ausgewaehlte Bootstrap-Komponenten sind verboten. Kein eigenes CSS, kein eigenes JavaScript, keine Inline-Styles. HTML muss in Jodit bearbeitbar bleiben.';
      return implode("\n", $lines);
   }

   private function heroImageSpecText(): string {
      return 'JPG, Standard ' . self::HERO_DEFAULT_IMAGE_WIDTH . '×' . self::HERO_DEFAULT_IMAGE_HEIGHT . ' px'
         . ' (nur bei ausdruecklicher Vorgabe abweichend, maximal ' . self::HERO_MAX_WIDTH . '×' . self::HERO_MAX_HEIGHT . ' px),'
         . ' CMS-Hero-Hoehe Standard ' . self::HERO_DEFAULT_HEIGHT;
   }

   private function heroImageBriefingMeta(): array {
      return array(
         'format' => 'jpg',
         'max_width' => self::HERO_MAX_WIDTH,
         'max_height' => self::HERO_MAX_HEIGHT,
         'default_width' => self::HERO_DEFAULT_IMAGE_WIDTH,
         'default_image_height' => self::HERO_DEFAULT_IMAGE_HEIGHT,
         'default_dimensions' => self::HERO_DEFAULT_IMAGE_WIDTH . 'x' . self::HERO_DEFAULT_IMAGE_HEIGHT,
         'recommended' => self::HERO_OPTIMAL_WIDTH . 'x' . self::HERO_OPTIMAL_HEIGHT,
         'default_height' => self::HERO_DEFAULT_HEIGHT,
      );
   }

   private function ensureContentBootstrap(): void {
      if (!class_exists(dbxContentLng::class)) {
         require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
      }
   }

   private function cms(): dbxKiCmsService {
      return dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');
   }

   private function help(): dbxKiHelp {
      return dbx()->get_include_obj('dbxKiHelp', 'dbxKi');
   }

   private function bundle(): dbxKiBundleService {
      return dbx()->get_include_obj('dbxKiBundleService', 'dbxKi');
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function esc($value): string {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   private function moduleUrl(string $run1, array $params = array()): string {
      $url = '?dbx_modul=dbxKi&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         if ($value === null || $value === '') {
            continue;
         }
         $url .= '&' . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
      }
      return $url;
   }

   private function contentAdminUrl(string $run1, array $params = array()): string {
      $url = '?dbx_modul=dbxContent_admin&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         if ($value === null || $value === '') {
            continue;
         }
         $url .= '&' . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
      }
      return $url;
   }

   private function withContentLng(string $lng, callable $fn) {
      $lng = strtolower(trim($lng));
      $prev = (string) dbx()->get_system_var('dbx_lng', '');
      if ($lng !== '') {
         dbx()->set_system_var('dbx_lng', $lng);
      }
      try {
         return $fn();
      } finally {
         dbx()->set_system_var('dbx_lng', $prev);
      }
   }

   private function withModuleBar(array $data, string $screen, string $actionsHtml = ''): array {
      return array_merge($data, $this->help()->moduleBarTemplateData($screen, $actionsHtml));
   }

   /**
    * Erstellt ein dbxForm fuer eine frei gestaltete dbxKi-Briefingseite.
    *
    * Die sichtbaren Spezialfelder bleiben im jeweiligen HTML-Template, weil
    * Tree, Vorschau und Auswahlfelder von kiBriefing.js gemeinsam gesteuert
    * werden. dbxForm uebernimmt zentral Formular-ID, CSRF-Token, Submit-
    * Erkennung, Meldungen und den Template-Lauf. Das Template muss innerhalb
    * des eigentlichen Formulars einen `[dbx:form]`-Slot enthalten, damit das
    * Security-Feld nicht versehentlich in ein eingebettetes Importformular
    * eingesetzt wird.
    *
    * @param string $fid         Stabile Formular-ID fuer Token und Zustand
    * @param string $template    dbxKi-Template ohne Modul-Praefix
    * @param string $action      Ziel-URL des Formulars
    * @param array  $replacements Bereits kontextgerecht aufbereitete Templatewerte
    *
    * @return \dbxForm
    */
   private function briefingForm(string $fid, string $template, string $action, array $replacements = array()) {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init($fid, $template);
      $form->_action = $action;
      $form->_msg_info = '';

      foreach ($replacements as $key => $value) {
         $form->add_rep((string) $key, $value);
      }

      return $form;
   }

   /**
    * Liefert die stabile dbxForm-ID fuer ein exportierbares Briefing-Rezept.
    */
   private function briefingFormId(string $recipe): string {
      return 'ki-briefing-' . str_replace('_', '-', strtolower(trim($recipe)));
   }

   private function barBackHub(): string {
      return '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($this->moduleUrl('briefing')) . '" title="Zurueck zum Hub"><i class="bi bi-arrow-left"></i></a>';
   }

   private function briefingWorkflowData(string $returnRun1): array {
      return array(
         'workflow_hint' => $this->tpl()->get_tpl('dbxKi|ki-briefing-workflow-hint', array()),
         'import_panel' => $this->bundle()->renderImportPanel($returnRun1),
      );
   }

   public function recipes(): array {
      return array(
         'page_create' => array(
            'title' => 'Neue Content-Seite anlegen',
            'icon' => 'bi-file-earmark-plus',
            'subtitle' => 'Ordner, Titel, Text, Hero — KI-Auftrag erzeugen',
            'recipe' => 'page.create.v1',
            'form_url' => $this->moduleUrl('briefing_page_create'),
            'export_action' => 'briefing_export',
         ),
         'page_update' => array(
            'title' => 'Bestehende Seite aendern',
            'icon' => 'bi-pencil-square',
            'subtitle' => 'Inhalt aktualisieren, Stil anpassen, Hero tauschen',
            'recipe' => 'page.update.v1',
            'form_url' => $this->moduleUrl('briefing_page_update'),
            'export_action' => 'briefing_export',
         ),
         'page_translate' => array(
            'title' => 'Seite uebersetzen',
            'icon' => 'bi-translate',
            'subtitle' => 'Quellseite in andere Sprache uebertragen',
            'recipe' => 'translation.v1',
            'form_url' => $this->moduleUrl('briefing_page_translate'),
            'export_action' => 'briefing_export',
         ),
         'module_update' => array(
            'title' => 'Bestehendes Modul bearbeiten',
            'icon' => 'bi-box-seam',
            'subtitle' => 'Komplettes Modul als Kontext, harte dbxapp-Regeln, Antwort-ZIP',
            'recipe' => 'module.update.v1',
            'form_url' => $this->moduleUrl('briefing_module'),
            'export_action' => 'briefing_module_export',
         ),
         'design' => array(
            'title' => 'Design entwickeln',
            'icon' => 'bi-palette',
            'subtitle' => 'Aufteilung, Menue, Footer und Branding mit komplettem Designkontext',
            'recipe' => 'design.update.v1',
            'form_url' => $this->moduleUrl('briefing_design'),
            'export_action' => 'briefing_design_export',
         ),
      );
   }

   public function exportBackUrl(string $recipe): string {
      switch ($recipe) {
         case 'page_update':
            return $this->moduleUrl('briefing_page_update');
         case 'page_translate':
            return $this->moduleUrl('briefing_page_translate');
         default:
            return $this->moduleUrl('briefing_page_create');
      }
   }

   public function renderHub(): string {
      $this->ensureContentBootstrap();
      $cards = '';
      foreach ($this->recipes() as $key => $meta) {
         $cards .= '<div class="col-md-6">'
            . '<div class="card h-100">'
            . '<div class="card-body d-flex flex-column">'
            . '<h2 class="h5"><i class="bi ' . $this->esc($meta['icon'] ?? 'bi-grid') . ' me-2"></i>'
            . $this->esc($meta['title'] ?? $key) . '</h2>'
            . '<p class="small text-muted flex-grow-1">' . $this->esc($meta['subtitle'] ?? '') . '</p>'
            . '<a class="btn btn-primary" href="' . $this->esc($meta['form_url'] ?? '#') . '">Formular oeffnen</a>'
            . '</div></div></div>';
      }

      return $this->tpl()->get_tpl('dbxKi|ki-briefing-hub', $this->withModuleBar(array_merge(array(
         'recipe_cards' => $cards,
         'import_url' => $this->esc($this->moduleUrl('bundle')),
         'styles_url' => $this->esc($this->moduleUrl('briefing_styles')),
         'bundle_version' => $this->esc(self::BRIEFING_VERSION),
      ), $this->briefingWorkflowData('briefing')), 'briefing',
         '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($this->moduleUrl('briefing_styles')) . '" title="Schreibstile"><i class="bi bi-type"></i></a>'));
   }

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
      $selectedBootstrapComponents = $this->selectedBootstrapComponentsFromRequest();
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
         'bootstrap_component_choices' => $this->buildBootstrapComponentChoices($selectedBootstrapComponents),
         'change_brief' => $this->esc((string) dbx()->get_request_var('change_brief', '', '*')),
         'custom_notes' => $this->esc((string) dbx()->get_request_var('custom_notes', '', '*')),
         'field_content_checked' => dbx()->get_request_var('change_content', '1', '*') !== '0' ? 'checked' : '',
         'field_title_checked' => dbx()->get_request_var('change_title', '', '*') === '1' ? 'checked' : '',
         'field_description_checked' => dbx()->get_request_var('change_description', '', '*') === '1' ? 'checked' : '',
         'field_hero_checked' => dbx()->get_request_var('change_hero', '', '*') === '1' ? 'checked' : '',
         'hero_brief' => $this->esc((string) dbx()->get_request_var('hero_brief', '', '*')),
         'embedded_policy_preserve_checked' => dbx()->get_request_var('embedded_policy', 'preserve', '*') === 'preserve' ? 'checked' : '',
         'embedded_policy_reorder_checked' => dbx()->get_request_var('embedded_policy', 'preserve', '*') === 'reorder' ? 'checked' : '',
         'embedded_policy_remove_checked' => dbx()->get_request_var('embedded_policy', 'preserve', '*') === 'remove' ? 'checked' : '',
         'embedded_change_notes' => $this->esc((string) dbx()->get_request_var('embedded_change_notes', '', '*')),
      ), $this->briefingWorkflowData('briefing_page_update')), 'briefing_page_update', $this->barBackHub());

      return $this->briefingForm(
         $this->briefingFormId('page_update'),
         'ki-briefing-page-update',
         $this->moduleUrl('briefing_export'),
         $data
      )->run();
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

   public function renderStylesAdmin(): string {
      $rows = '';
      foreach (dbxKiWritingStyles::all() as $key => $meta) {
         $rows .= '<tr>'
            . '<td><input class="form-control form-control-sm" name="style_key[]" value="' . $this->esc($key) . '"></td>'
            . '<td><input class="form-control form-control-sm" name="style_label[]" value="' . $this->esc($meta['label'] ?? '') . '"></td>'
            . '<td><textarea class="form-control form-control-sm" name="style_prompt[]" rows="2">' . $this->esc($meta['prompt'] ?? '') . '</textarea></td>'
            . '</tr>';
      }
      $rows .= '<tr>'
         . '<td><input class="form-control form-control-sm" name="style_key[]" placeholder="neuer_stil"></td>'
         . '<td><input class="form-control form-control-sm" name="style_label[]" placeholder="Bezeichnung"></td>'
         . '<td><textarea class="form-control form-control-sm" name="style_prompt[]" rows="2" placeholder="KI-Anweisung"></textarea></td>'
         . '</tr>';

      $data = $this->withModuleBar(array(
         'hub_url' => $this->esc($this->moduleUrl('briefing')),
         'save_url' => $this->esc($this->moduleUrl('briefing_styles_save')),
         'style_rows' => $rows,
      ), 'briefing_styles', $this->barBackHub());

      return $this->briefingForm(
         'ki-briefing-styles',
         'ki-briefing-styles',
         $this->moduleUrl('briefing_styles_save'),
         $data
      )->run();
   }

   public function handleStylesSave(): string {
      try {
         $form = $this->briefingForm(
            'ki-briefing-styles',
            'ki-briefing-styles',
            $this->moduleUrl('briefing_styles_save')
         );
         if (!$form->submit()) {
            throw new \RuntimeException('Ungueltiger oder abgelaufener Formular-Token.');
         }
         if (dbx()->get_request_var('styles_action', 'save', 'parameter') === 'reset') {
            dbxKiWritingStyles::resetToDefaults();
            dbx()->sys_msg('info', 'dbxKi', 'styles', 'Schreibstile', 'Standard wiederhergestellt');
            return $this->renderStylesAdmin();
         }
         $styles = dbxKiWritingStyles::parseFormRows(
            (array) dbx()->get_request_var('style_key', array(), '*'),
            (array) dbx()->get_request_var('style_label', array(), '*'),
            (array) dbx()->get_request_var('style_prompt', array(), '*')
         );
         dbxKiWritingStyles::save($styles);
         dbx()->sys_msg('info', 'dbxKi', 'styles', 'Schreibstile gespeichert', count($styles) . ' Stile');
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxKi', 'styles', 'Speichern fehlgeschlagen', $e->getMessage());
      }
      return $this->renderStylesAdmin();
   }

   public function handleStylesReset(): string {
      // Rueckwaertskompatibler GET-Endpunkt ohne schreibende Wirkung. Das
      // Zuruecksetzen erfolgt nur noch als geschuetzter dbxForm-POST.
      dbx()->sys_msg('warning', 'dbxKi', 'styles', 'Schreibstile',
         'Standardwerte bitte ueber das geschuetzte Formular zuruecksetzen.');
      return $this->renderStylesAdmin();
   }

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
         'bootstrap_components' => $this->selectedBootstrapComponentsFromRequest(),
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
         'auto_execute' => true,
      );

      $context = array(
         'lng' => $in['lng'],
         'target_folder_id' => $in['folder_id'],
         'target_folder_label' => $folderLabel,
         'sorter_after_page_id' => $in['sorter_after_page_id'],
         'sorter_after' => $this->slimPageForUpdate($sorterAfterPage),
         'sorter' => $sorterValue,
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
         'auto_execute' => true,
      );

      $context = array(
         'lng' => $in['lng'],
         'current_page' => $this->slimPageForUpdate($page),
         'current_page_context' => $pageContext,
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
               'alt' => '___KI_FUELLEN___',
            ),
         );
      }

      $pageParams = array(
         'lng' => $in['lng'],
         'folder_id' => $in['folder_id'],
         'title' => $in['title'],
         'template' => $this->contentTemplateForCreate($in['hero_enabled'], $in['content_template'] ?? ''),
         'activ' => $in['activ'] ? 1 : 0,
         'content' => '___KI_FUELLEN___',
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
         $patch['content'] = '___KI_FUELLEN___';
      }
      if (in_array('title', $in['change_fields'], true)) {
         $patch['title'] = '___KI_FUELLEN___';
      }
      if (in_array('description', $in['change_fields'], true)) {
         $patch['description'] = '___KI_FUELLEN___';
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
            'action' => 'media.create_base64',
            'params' => array(
               'file_name' => 'hero.jpg',
               'asset_ref' => 'hero.jpg',
               'media_folder' => 'img/hero',
               'title' => (string) ($page['title'] ?? 'Hero'),
               'alt' => '___KI_FUELLEN___',
            ),
         );
         $steps[] = array(
            'id' => 'hero_assign',
            'action' => 'media.assign',
            'params' => array(
               'media_id' => '$ref:hero.media_id',
               'content_id' => (int) $in['page_id'],
               'slot' => 'hero',
               'lng' => $in['lng'],
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
                     'title' => '___KI_FUELLEN___',
                     'description' => '___KI_FUELLEN___',
                     'keywords' => '___KI_FUELLEN___',
                     'content' => '___KI_FUELLEN___',
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
                  'title' => '___KI_FUELLEN___',
                  'description' => '___KI_FUELLEN___',
                  'keywords' => '___KI_FUELLEN___',
                  'content' => '___KI_FUELLEN___',
               ),
            ),
         );
      }
      return array(
         'steps' => $steps,
      );
   }

   private function packBriefingFiles(array $in): array {
      $recipe = (string) ($in['recipe'] ?? '');
      $heroAssets = !empty($in['hero_assets']);
      return array(
         '00-START.md' => $this->buildStartMd(
            $recipe,
            (string) ($in['task_label'] ?? ''),
            $heroAssets,
            (string) ($in['context_hint'] ?? ''),
            (string) ($in['content_template'] ?? '')
         ),
         'manifest.json' => $in['manifest'],
         'briefing.json' => $in['briefing'],
         'job.vorlage.json' => $in['job_vorlage'],
         'context.json' => $in['context'],
         'KI-AUFTRAG.md' => $in['auftrag'],
         'bundle.rules.json' => $this->bundleRulesForRecipe($recipe, $heroAssets, (string) ($in['content_template'] ?? '')),
      );
   }

   private function buildStartMd(string $recipe, string $taskLabel, bool $heroAssets, string $contextHint, string $contentTemplate = ''): string {
      if ($contentTemplate === '') {
         $contentTemplate = $heroAssets ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      $zipExtra = $heroAssets
         ? "- `assets/hero.jpg` (" . $this->heroImageSpecText() . ")\n"
         : '';
      $assetsShort = $heroAssets
         ? '**Ja:** genau `assets/hero.jpg` — ' . $this->heroImageSpecText() . '. `asset_ref` = `hero.jpg` (nicht aendern).'
         : '**Nein.** Keinen `assets/` Ordner anlegen.';
      return $this->renderTemplateFile('ki-start.md', array(
         'task_label' => $taskLabel,
         'recipe' => $recipe,
         'zip_extra' => $zipExtra,
         'assets_short' => $assetsShort,
         'context_hint' => $contextHint,
         'content_layout_short' => $this->contentMarkersGuideShort($contentTemplate),
      ));
   }

   private function bundleRulesForRecipe(string $recipe, bool $withHero = false, string $contentTemplate = ''): array {
      $actions = array();
      switch ($recipe) {
         case 'page.create.v1':
            $actions = $withHero
               ? array('media.create_base64', 'page.create', 'media.assign')
               : array('page.create');
            break;
         case 'page.update.v1':
            $actions = $withHero
               ? array('page.hero_replace_image', 'page.hero_create_image', 'media.create_base64', 'page.update', 'media.assign')
               : array('page.update');
            break;
         case 'translation.v1':
            $actions = array('translation.apply', 'page.update');
            break;
      }
      return array(
         'recipe' => $recipe,
         'allowed_actions' => $actions,
         'refs' => '$ref:{step_id}.{field}',
         'asset_ref' => 'Dateiname relativ zu assets/, z.B. hero.jpg',
         'forbidden' => array('*.delete', 'data_base64'),
         'content' => $this->contentRulesForBriefing($contentTemplate, $withHero),
      );
   }

   private function contentRulesForBriefing(string $contentTemplate, bool $withHero): array {
      if ($contentTemplate === '') {
         $contentTemplate = $withHero ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      $slots = $this->analyzeTemplateSlots($contentTemplate);
      $markers = $this->contentMarkersMeta($slots);
      $rules = array(
         'format' => 'HTML in page.create/page.update content oder translation.content',
         'marker_type' => 'hr',
         'markers' => $markers,
         'marker_order' => array('hero', 'header', 'footer'),
         'marker_attributes' => 'class="dbx-cms-marker", data-dbx-marker="dbx:hero|header|footer", data-label',
         'sections' => array(
            'before_hero' => 'Hero-Text ({cms:hero_text})',
            'between_hero_and_header' => 'Header ({cms:header}) — eigener Abschnitt nach Hero, vor Body',
            'between_header_and_footer' => 'Body ({cms:col1})',
            'after_footer' => 'Footer ({cms:footer})',
         ),
         'missing_marker' => 'Bereich entfaellt, Text gehoert zum Body',
         'no_column_markers' => 'Spalten-Marker (col2/col3a/col3b) nicht von der KI setzen',
          'hero_image' => 'Hero-Bild nicht im HTML. Bestehendes Hero aendern: page.hero_replace_image. Neues Hero setzen: page.hero_create_image oder media.create_base64 + media.assign mit media_folder=img/hero.',
          'fake_hero_forbidden' => 'Ein Seitenkopf aus Inline-Bild plus position-relative/position-absolute Textebene ist verboten. Das Bild gehoert in slot=hero/hero_image_id; der Text vor den dbx:hero-Marker.',
         'hero_height_default' => self::HERO_DEFAULT_HEIGHT . ' in page.create/page.update setzen, wenn ein Hero-Bild neu angelegt wird und nichts anderes angegeben ist',
         'hero_text_default' => 'Hero-Text maximal ' . self::HERO_TEXT_MAX_LINES . ' Zeilen, wenn nicht anders angegeben',
         'inline_images' => 'CMS-Medien nie als files/media/... in img src setzen. Nach media.create_* inline_src oder inline_img verwenden: index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid={id} plus data-cms-media-id.',
         'package_pages' => 'Paket-Detailseiten (dbxapp-paket-*) per page.update mit patch.package_product_image=true auf home-package-* Produktbild umstellen. page.get liefert package_hint.',
      );
      $rules['template'] = $contentTemplate;
      $rules['template_slots'] = $slots;
      return $rules;
   }

   private function sanitizeEmbeddedPolicy(string $policy): string {
      $policy = strtolower(trim($policy));
      return in_array($policy, array('preserve', 'reorder', 'remove'), true) ? $policy : 'preserve';
   }

   private function embeddedPolicyText(string $policy, string $notes): string {
      $policy = $this->sanitizeEmbeddedPolicy($policy);
      $notes = trim($notes);
      $lines = array(
         'Standard: Bestehende eingebettete Medien, Videos und `[modul=...]...[/modul]`-Aufrufe exakt beibehalten.',
         'Keine bestehenden Medienpfade manuell umschreiben; dbxKi/CMS-Befehle loesen Speicherpfade automatisch.',
      );
      if ($policy === 'reorder') {
         $lines[] = 'Erlaubt: vorhandene Medien/Module neu anordnen, aber nur soweit es zur gewuenschten Aenderung passt.';
      } elseif ($policy === 'remove') {
         $lines[] = 'Erlaubt: vorhandene Medien/Module entfernen, aber nur wenn unten konkret beschrieben ist, was weg soll.';
      } else {
         $lines[] = 'Nicht erlaubt: Medien/Module entfernen, ersetzen oder neu anordnen.';
      }
      if ($notes !== '') {
         $lines[] = 'Konkrete Anweisung zu Medien/Modulen: ' . $notes;
      }
      return implode("\n", $lines);
   }

   private function moduleCallsFromContent(string $content): array {
      $calls = array();
      if (preg_match_all('/\[modul=([A-Za-z0-9_]+)\]([\s\S]*?)\[\/modul\]/i', $content, $m, PREG_SET_ORDER)) {
         foreach ($m as $idx => $match) {
            $calls[] = array(
               'index' => $idx + 1,
               'modul' => (string) ($match[1] ?? ''),
               'params' => trim((string) ($match[2] ?? '')),
               'marker' => (string) ($match[0] ?? ''),
            );
         }
      }
      return $calls;
   }

   private function inlineMediaIdsFromContent(string $content): array {
      $ids = array();
      if (preg_match_all('/data-cms-media-id=["\']?([0-9]+)/i', $content, $m)) {
         foreach ($m[1] as $id) {
            $ids[(int) $id] = (int) $id;
         }
      }
      if (preg_match_all('/(?:dbx_mid|media_id)=([0-9]+)/i', $content, $m)) {
         foreach ($m[1] as $id) {
            $ids[(int) $id] = (int) $id;
         }
      }
      return array_values(array_filter($ids));
   }

   private function mediaContextForPage(int $pageId, string $content): array {
      $db = dbx()->get_system_obj('dbxDB');
      $mediaIds = $this->inlineMediaIdsFromContent($content);
      $usageRows = $db->select('dbxMediaUsage', 'content_id = ' . (int) $pageId . ' AND active = 1', '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      if (!is_array($usageRows)) {
         $usageRows = array();
      }
      foreach ($usageRows as $usage) {
         $id = (int) ($usage['media_id'] ?? 0);
         if ($id > 0) {
            $mediaIds[$id] = $id;
         }
      }

      $rows = array();
      foreach (array_values(array_unique($mediaIds)) as $id) {
         $media = $db->select1('dbxMedia', (int) $id);
         if (!is_array($media)) {
            $rows[] = array('id' => (int) $id, 'missing' => 1);
            continue;
         }
         $usage = array_values(array_filter($usageRows, function ($row) use ($id) {
            return (int) ($row['media_id'] ?? 0) === (int) $id;
         }));
         $rows[] = array(
            'id' => (int) $id,
            'title' => (string) ($media['title'] ?? $media['file_name'] ?? ''),
            'file_name' => (string) ($media['file_name'] ?? ''),
            'media_type' => (string) ($media['media_type'] ?? ''),
            'mime' => (string) ($media['mime'] ?? ''),
            'slots' => array_values(array_unique(array_map(function ($row) {
               return (string) ($row['slot'] ?? '');
            }, $usage))),
            'inline_reference' => in_array((int) $id, $this->inlineMediaIdsFromContent($content), true) ? 1 : 0,
         );
      }
      return $rows;
   }

   private function renderedTextForPage(string $lng, int $pageId): string {
      try {
         $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
         $html = $this->withContentLng($lng, function () use ($renderer, $pageId) {
            return $renderer->render($pageId);
         });
         return $this->truncate(strip_tags((string) $html), 12000);
      } catch (\Throwable $e) {
         return '';
      }
   }

   private function pageContextForKi(string $lng, array $page): array {
      $pageId = (int) ($page['id'] ?? 0);
      $content = (string) ($page['content'] ?? '');
      return array(
         'render_reference' => '[modul=dbxContent]dbx_run1=show&cid=' . $pageId . '[/modul]',
         'folder_label' => $this->folderLabel($lng, (int) ($page['folder'] ?? 0)),
         'template' => (string) ($page['template'] ?? ''),
         'rendered_text_excerpt' => $this->renderedTextForPage($lng, $pageId),
         'embedded_media' => $this->mediaContextForPage($pageId, $content),
         'module_calls' => $this->moduleCallsFromContent($content),
         'rules' => array(
            'default' => 'Preserve embedded media and module calls exactly unless the briefing explicitly allows reorder/remove.',
            'media_paths' => 'Do not rewrite existing media paths manually. Keep existing HTML/media references or use dbxKi media steps for new hero assets.',
         ),
      );
   }

   private function embeddedSummaryForPrompt(array $context): string {
      $media = is_array($context['embedded_media'] ?? null) ? $context['embedded_media'] : array();
      $modules = is_array($context['module_calls'] ?? null) ? $context['module_calls'] : array();
      $lines = array();
      $lines[] = 'Eingebettete Medien: ' . count($media);
      foreach ($media as $row) {
         $label = '#' . (int) ($row['id'] ?? 0);
         $title = trim((string) ($row['title'] ?? $row['file_name'] ?? ''));
         $slots = implode(',', array_filter((array) ($row['slots'] ?? array())));
         $lines[] = '- ' . $label . ($title !== '' ? ' ' . $title : '') . ($slots !== '' ? ' [' . $slots . ']' : '');
      }
      $lines[] = 'Modul-Aufrufe: ' . count($modules);
      foreach ($modules as $call) {
         $lines[] = '- [modul=' . (string) ($call['modul'] ?? '') . ']' . (string) ($call['params'] ?? '') . '[/modul]';
      }
      return implode("\n", $lines);
   }

   private function renderPageContextHtml(string $lng, array $page): string {
      $context = $this->pageContextForKi($lng, $page);
      $media = is_array($context['embedded_media'] ?? null) ? $context['embedded_media'] : array();
      $modules = is_array($context['module_calls'] ?? null) ? $context['module_calls'] : array();
      $mediaHtml = '';
      foreach ($media as $row) {
         $title = trim((string) ($row['title'] ?? $row['file_name'] ?? ''));
         $slots = implode(', ', array_filter((array) ($row['slots'] ?? array())));
         $mediaHtml .= '<li><code>#' . (int) ($row['id'] ?? 0) . '</code> '
            . $this->esc($title !== '' ? $title : 'Medium')
            . ($slots !== '' ? ' <span class="text-muted">(' . $this->esc($slots) . ')</span>' : '')
            . '</li>';
      }
      if ($mediaHtml === '') {
         $mediaHtml = '<li class="text-muted">Keine eingebetteten Medien erkannt.</li>';
      }

      $moduleHtml = '';
      foreach ($modules as $call) {
         $moduleHtml .= '<li><code>[modul=' . $this->esc($call['modul'] ?? '') . ']</code> '
            . '<span class="text-muted">' . $this->esc($call['params'] ?? '') . '</span></li>';
      }
      if ($moduleHtml === '') {
         $moduleHtml = '<li class="text-muted">Keine Modul-Aufrufe erkannt.</li>';
      }

      return $this->tpl()->get_tpl('dbxKi|ki-briefing-page-update-context', array(
         'page_id' => (string) (int) ($page['id'] ?? 0),
         'page_title' => $this->esc((string) ($page['title'] ?? '')),
         'folder_label' => $this->esc((string) ($context['folder_label'] ?? '')),
         'template' => $this->esc((string) ($context['template'] ?? '')),
         'permalink' => $this->esc((string) ($page['permalink'] ?? '')),
         'media_count' => (string) count($media),
         'module_count' => (string) count($modules),
         'media_items' => $mediaHtml,
         'module_items' => $moduleHtml,
      ));
   }

   private function createPlacementTitle(string $lng, int $folderId, int $afterPageId): string {
      $folder = $folderId > 0 ? $this->folderLabel($lng, $folderId) : '';
      if ($afterPageId > 0) {
         try {
            $page = $this->loadPage($lng, $afterPageId);
            return 'Unter #' . $afterPageId . ' ' . (string) ($page['title'] ?? '') . ' in ' . $folder;
         } catch (\Throwable $e) {
            return $folder !== '' ? $folder : 'Zielposition waehlen';
         }
      }
      return $folder !== '' ? $folder . ' / am Ende' : 'Zielposition waehlen';
   }

   private function renderCreatePlacementHtml(string $lng, int $folderId, int $afterPageId): string {
      $folderLabel = $folderId > 0 ? $this->folderLabel($lng, $folderId) : '';
      $pageTitle = '';
      $sorter = '';
      if ($afterPageId > 0) {
         try {
            $page = $this->loadPage($lng, $afterPageId);
            $pageTitle = (string) ($page['title'] ?? '');
            $folderId = (int) ($page['folder'] ?? $folderId);
            $folderLabel = $this->folderLabel($lng, $folderId);
            $sorter = $this->sorterAfterPage($lng, $afterPageId);
         } catch (\Throwable $e) {
            $afterPageId = 0;
         }
      }
      return $this->tpl()->get_tpl('dbxKi|ki-briefing-page-create-placement', array(
         'folder_id' => $folderId > 0 ? (string) $folderId : '-',
         'folder_label' => $this->esc($folderLabel !== '' ? $folderLabel : 'Noch kein Zielordner gewaehlt'),
         'after_page_id' => $afterPageId > 0 ? (string) $afterPageId : '-',
         'after_page_title' => $this->esc($pageTitle !== '' ? $pageTitle : 'Keine Seite als Sortieranker'),
         'sorter' => $this->esc($sorter !== '' ? $sorter : 'automatisch am Ende'),
      ));
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

   private function writingStyles(): array {
      return dbxKiWritingStyles::all();
   }

   private function zipStructureCreate(bool $heroEnabled): string {
      if ($heroEnabled) {
         return "antwort.zip\n"
            . "├── manifest.json\n"
            . "├── job.json\n"
            . "├── README.md\n"
            . "└── assets/\n"
            . "    └── hero.jpg";
      }
      return "antwort.zip\n"
         . "├── manifest.json\n"
         . "├── job.json\n"
         . "└── README.md";
   }

   private function zipStructureUpdate(bool $heroChange): string {
      if ($heroChange) {
         return "antwort.zip\n"
            . "├── manifest.json\n"
            . "├── job.json\n"
            . "├── README.md\n"
            . "└── assets/\n"
            . "    └── hero.jpg";
      }
      return "antwort.zip\n"
         . "├── manifest.json\n"
         . "├── job.json\n"
         . "└── README.md";
   }

   private function assetsRulesCreate(bool $heroEnabled, string $heroBrief): string {
      if ($heroEnabled) {
         $brief = $heroBrief !== '' ? $heroBrief : 'passend zum Seitenthema';
         return "**Hero-Bild ist vorgesehen** (`briefing.hero.enabled = true`):\n\n"
            . "1. Lege **genau eine** Datei an: `assets/hero.jpg` (" . $this->heroImageSpecText() . ").\n"
            . "2. Motiv: " . $brief . "\n"
            . "3. In `job.json` den Step `hero` **nicht** aendern — `asset_ref` bleibt `hero.jpg`.\n"
            . "4. Hero-Medienordner bleibt `img/hero`.\n"
            . "5. **Kein** `data_base64` eintragen. **Keine** weiteren Dateien in `assets/`.\n"
            . "6. Alt-Text (`alt` im hero-Step) ausfuellen.";
      }
      return "**Kein Hero-Bild** (`briefing.hero.enabled = false`):\n\n"
         . "1. **Keinen** `assets/` Ordner in der Antwort-ZIP anlegen.\n"
         . "2. Keine Medien-Steps — `job.vorlage.json` enthaelt nur `page.create`.\n"
         . "3. Nicht ueber Bilder nachdenken; direkt Text/HTML in `content` schreiben.";
   }

   private function assetsRulesUpdate(bool $heroChange, string $heroBrief): string {
      if ($heroChange) {
         $brief = $heroBrief !== '' ? $heroBrief : 'passend zur Seite';
         return "**Neues Hero-Bild** (`hero` in change_fields):\n\n"
            . "1. Lege `assets/hero.jpg` an (" . $this->heroImageSpecText() . "). Motiv: " . $brief . "\n"
            . "2. Fuer ein wirklich neues Hero-Bild: neue Medienverknuepfung in `img/hero` setzen.\n"
            . "3. Fuer eine reine Aenderung des bestehenden Hero-Bildes: nur die bestehende Hero-Datei ersetzen, keine neue Verknuepfung.\n"
            . "4. `asset_ref` bleibt `hero.jpg`. Kein `data_base64`. Keine weiteren Assets.";
      }
      return "**Kein Hero-Wechsel** (kein `hero` in change_fields):\n\n"
         . "1. **Keinen** `assets/` Ordner anlegen.\n"
         . "2. Nur `page.update` in `job.json` — keine Medien-Steps.";
   }

   private function assetsReadmeHero(string $heroBrief): string {
      return "KI: Lege hier die Datei hero.jpg ab.\n\n"
         . "Pfad in der Antwort-ZIP: assets/hero.jpg\n"
         . "Groesse: " . $this->heroImageSpecText() . "\n"
         . "In job.json: asset_ref = hero.jpg (bereits in job.vorlage.json)\n"
         . "Motiv: " . ($heroBrief !== '' ? $heroBrief : 'passend zum Seitenthema') . "\n";
   }

   private function renderTemplateFile(string $basename, array $vars): string {
      $path = dirname(__DIR__) . '/tpl/briefing/' . $basename;
      if (!is_file($path)) {
         return '';
      }
      $text = file_get_contents($path);
      if (!is_string($text)) {
         return '';
      }
      foreach ($vars as $key => $value) {
         $text = str_replace('{' . $key . '}', (string) $value, $text);
      }
      return $text;
   }

   private function menschAnleitungCreate(string $title): string {
      return "# Anleitung fuer Menschen\n\n"
         . "## Schritt 1 — Formular (erledigt)\n\n"
         . "Sie haben den Auftrag **" . $title . "** spezifiziert.\n\n"
         . "## Schritt 2 — ZIP an die KI\n\n"
         . "1. Diese ZIP bei ChatGPT, DeepSeek o.ae. hochladen **oder**\n"
         . "2. Den Inhalt von `KI-AUFTRAG.md` kopieren und einfügen.\n\n"
         . "Sagen Sie der KI: *„Arbeite KI-AUFTRAG.md exakt ab. Assets-Regeln nicht abweichen. Liefere antwort.zip mit job.json.“*\n\n"
         . "## Schritt 3 — Antwort importieren\n\n"
         . "1. In dbXapp: **dbxKi → Bundle importieren**\n"
         . "2. Die ZIP der KI hochladen\n"
         . "3. Vorschau pruefen → **Ausfuehren**\n";
   }

   private function menschAnleitungUpdate(): string {
      return "# Anleitung fuer Menschen\n\n"
         . "1. ZIP an die KI geben (siehe KI-AUFTRAG.md)\n"
         . "2. Fertige ZIP mit `job.json` zurueck erhalten\n"
         . "3. Unter dbxKi → Bundle importieren und ausfuehren\n";
   }

   private function menschAnleitungTranslate(string $title, string $targetLng): string {
      return "# Anleitung fuer Menschen\n\n"
         . "## Schritt 1 — Formular (erledigt)\n\n"
         . "Uebersetzungsauftrag fuer **" . $title . "** nach **" . strtoupper($targetLng) . "**.\n\n"
         . "## Schritt 2 — ZIP an die KI\n\n"
         . "1. Diese ZIP bei ChatGPT, DeepSeek o.ae. hochladen **oder**\n"
         . "2. Den Inhalt von `KI-AUFTRAG.md` kopieren und einfügen.\n\n"
         . "Sagen Sie der KI: *„Arbeite KI-AUFTRAG.md exakt ab und liefere antwort.zip mit job.json.“*\n\n"
         . "## Schritt 3 — Antwort importieren\n\n"
         . "1. In dbXapp: **dbxKi → Bundle importieren**\n"
         . "2. Die ZIP der KI hochladen\n"
         . "3. Vorschau pruefen → **Ausfuehren**\n";
   }

   private function buildLngOptions(string $selected): string {
      $lngs = $this->availableLngs();
      $html = '';
      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '') {
            continue;
         }
         $sel = $lng === $selected ? ' selected' : '';
         $html .= '<option value="' . $this->esc($lng) . '"' . $sel . '>' . strtoupper($this->esc($lng)) . '</option>';
      }
      return $html;
   }

   private function buildTargetLngCheckboxes(string $sourceLng, array $selected): string {
      $selected = $this->normalizeTargetLngs($selected, true);
      $html = '';
      foreach ($this->availableLngs() as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '') {
            continue;
         }
         $checked = in_array($lng, $selected, true) ? ' checked' : '';
         $isSource = $lng === $sourceLng;
         $id = 'dbxKiTargetLng_' . preg_replace('/[^a-z0-9_]/', '_', $lng);
         $html .= '<label class="dbx-ki-lng-choice' . ($isSource ? ' is-source' : '') . '" data-ki-target-lng="' . $this->esc($lng) . '">'
            . '<input type="checkbox" name="target_lngs[]" id="' . $this->esc($id) . '" value="' . $this->esc($lng) . '"' . $checked . '>'
            . '<span><strong>' . strtoupper($this->esc($lng)) . '</strong><small data-ki-target-mode>'
            . ($isSource ? 'Rechtschreibung/Grammatik' : 'Uebersetzung')
            . '</small></span>'
            . '</label>';
      }
      return $html;
   }

   private function buildTargetLngOptions(string $sourceLng, string $selected): string {
      $lngs = $this->availableLngs();
      $html = '';
      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '' || $lng === $sourceLng) {
            continue;
         }
         $sel = $lng === $selected ? ' selected' : '';
         $html .= '<option value="' . $this->esc($lng) . '"' . $sel . '>' . strtoupper($this->esc($lng)) . '</option>';
      }
      return $html;
   }

   private function selectedTargetLngsFromRequest(string $sourceLng, bool $defaultAllOthers): array {
      $raw = dbx()->get_request_var('target_lngs', array(), '*');
      if (!is_array($raw)) {
         $raw = $raw !== '' ? array($raw) : array();
      }
      $fallback = strtolower(trim((string) dbx()->get_request_var('target_lng', '', '*')));
      if ($fallback !== '') {
         $raw[] = $fallback;
      }
      $selected = $this->normalizeTargetLngs($raw, true);
      if (!$selected && $defaultAllOthers) {
         foreach ($this->availableLngs() as $lng) {
            if ($lng !== $sourceLng) {
               $selected[] = $lng;
            }
         }
      }
      return array_values(array_unique($selected));
   }

   private function normalizeTargetLngs(array $lngs, bool $allowSource): array {
      $allowed = array_fill_keys($this->availableLngs(), true);
      $out = array();
      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '' || !isset($allowed[$lng])) {
            continue;
         }
         if (!$allowSource && $lng === strtolower(trim((string) dbxContentLng::current()))) {
            continue;
         }
         $out[$lng] = $lng;
      }
      return array_values($out);
   }

   private function availableLngs(): array {
      $lngs = array();
      if (class_exists(dbxContentLngSync::class)) {
         $lngs = dbxContentLngSync::accessibleLngs();
      }
      if (!is_array($lngs) || !$lngs) {
         $lngs = array(dbxContentLng::current());
      }
      $out = array();
      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng !== '') {
            $out[$lng] = $lng;
         }
      }
      return array_values($out);
   }

   private function targetInstructionLabels(string $sourceLng, array $targets): array {
      $out = array();
      foreach ($targets as $targetLng) {
         $out[] = array(
            'lng' => $targetLng,
            'mode' => $targetLng === $sourceLng ? 'proofread' : 'translate',
            'label' => $targetLng === $sourceLng
               ? strtoupper($targetLng) . ': Rechtschreib- und Grammatikpruefung der Quellsprache'
               : strtoupper($sourceLng) . ' -> ' . strtoupper($targetLng) . ': Uebersetzung',
         );
      }
      return $out;
   }

   private function targetInstructionsForPrompt(string $sourceLng, array $targets): string {
      $lines = array();
      foreach ($targets as $targetLng) {
         if ($targetLng === $sourceLng) {
            $lines[] = '- ' . strtoupper($targetLng) . ': Kein Sprachwechsel. Korrigiere Rechtschreibung, Grammatik, Zeichensetzung und offensichtliche Tippfehler. Inhalt, Sinn, HTML-Struktur, Medien und Modul-Aufrufe beibehalten. Nutze den `page.update`-Step `proofread_' . $targetLng . '`.';
         } else {
            $lines[] = '- ' . strtoupper($sourceLng) . ' -> ' . strtoupper($targetLng) . ': Vollstaendige Uebersetzung aller Felder. Nutze den `translation.apply`-Step `translation_' . $targetLng . '`.';
         }
      }
      return implode("\n", $lines);
   }

   private function buildFolderOptions(string $lng, int $selected): string {
      $labels = $this->folderLabels($lng);
      $html = '<option value="">— Ordner waehlen —</option>';
      foreach ($labels as $id => $label) {
         $sel = ((int) $id === $selected) ? ' selected' : '';
         $html .= '<option value="' . (int) $id . '"' . $sel . '>' . $this->esc($label) . ' (#' . (int) $id . ')</option>';
      }
      return $html;
   }

   private function buildPageOptions(string $lng, int $selected): string {
      $snap = $this->cms()->bundleSnapshot(array('lng' => $lng, 'limit' => 300));
      $folders = $this->folderLabels($lng);
      $rows = is_array($snap['pages']['rows'] ?? null) ? $snap['pages']['rows'] : array();
      $html = '<option value="">— Seite waehlen —</option>';
      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $id = (int) ($row['id'] ?? 0);
         if ($id <= 0) {
            continue;
         }
         $fid = (int) ($row['folder'] ?? 0);
         $folder = $folders[$fid] ?? ('Ordner #' . $fid);
         $title = trim((string) ($row['title'] ?? ''));
         $sel = ($id === $selected) ? ' selected' : '';
         $html .= '<option value="' . $id . '"' . $sel . '>' . $this->esc($title) . ' — ' . $this->esc($folder) . ' (#' . $id . ')</option>';
      }
      return $html;
   }

   private function buildStyleOptions(string $selected): string {
      $html = '';
      foreach ($this->writingStyles() as $key => $meta) {
         $sel = ($key === $selected) ? ' selected' : '';
         $html .= '<option value="' . $this->esc($key) . '"' . $sel . '>' . $this->esc($meta['label'] ?? $key) . '</option>';
      }
      return $html;
   }

   private function folderLabels(string $lng): array {
      $snap = $this->cms()->bundleSnapshot(array('lng' => $lng, 'limit' => 500));
      $rows = is_array($snap['folders']['rows'] ?? null) ? $snap['folders']['rows'] : array();
      $byId = array();
      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $byId[(int) ($row['id'] ?? 0)] = $row;
      }
      $labels = array();
      foreach ($byId as $id => $row) {
         $parts = array();
         $cur = (int) $id;
         $guard = 0;
         while ($cur > 0 && isset($byId[$cur]) && $guard++ < 25) {
            array_unshift($parts, (string) ($byId[$cur]['name'] ?? ''));
            $cur = (int) ($byId[$cur]['parent_id'] ?? 0);
         }
         $labels[$id] = implode(' / ', array_filter($parts));
      }
      asort($labels, SORT_NATURAL | SORT_FLAG_CASE);
      return $labels;
   }

   private function folderLabel(string $lng, int $folderId): string {
      $labels = $this->folderLabels($lng);
      return (string) ($labels[$folderId] ?? ('Ordner #' . $folderId));
   }

   private function sorterAfterPage(string $lng, int $pageId): string {
      $db = dbx()->get_system_obj('dbxDB');
      $dd = dbxContentLng::ddContent($lng);
      $page = $db->select1($dd, $pageId);
      if (!is_array($page)) {
         return '';
      }
      $folder = (int) ($page['folder'] ?? 0);
      $sorter = (int) ($page['sorter'] ?? 0);
      if ($folder <= 0) {
         return '';
      }
      $rows = $db->select($dd, 'folder = ' . $folder . ' AND sorter > ' . $sorter, 'sorter,id', 'sorter,id', 'ASC', '', 1, 0, 0);
      $nextSorter = is_array($rows) && isset($rows[0]) ? (int) ($rows[0]['sorter'] ?? 0) : 0;
      if ($nextSorter > ($sorter + 1)) {
         return sprintf('%04d', $sorter + 1);
      }
      return sprintf('%04d', $sorter);
   }

   private function loadPage(string $lng, int $pageId): array {
      $db = dbx()->get_system_obj('dbxDB');
      $row = $db->select1(dbxContentLng::ddContent($lng), $pageId);
      if (!is_array($row)) {
         throw new \InvalidArgumentException('Seite nicht gefunden.');
      }
      return $row;
   }

   private function pageContentExcerpt(string $lng, int $pageId): string {
      try {
         $row = $this->loadPage($lng, $pageId);
         return $this->truncate(strip_tags((string) ($row['content'] ?? '')), 2000);
      } catch (\Throwable $e) {
         return '';
      }
   }

   private function truncate(string $text, int $max): string {
      $text = trim(preg_replace('/\s+/u', ' ', $text));
      if (mb_strlen($text) <= $max) {
         return $text;
      }
      return mb_substr($text, 0, $max) . '…';
   }
}
