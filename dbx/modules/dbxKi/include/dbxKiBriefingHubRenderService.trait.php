<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingHubRenderServiceTrait {

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
      return '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($this->moduleUrl('briefing_content')) . '" title="Zurück zur Content-Übersicht"><i class="bi bi-arrow-left"></i></a>';
   }

   private function briefingWorkflowData(string $returnRun1): array {
      return array(
         'workflow_hint' => $this->tpl()->get_tpl('dbxKi|ki-briefing-workflow-hint', array()),
         'import_panel' => $this->bundle()->renderImportPanel($returnRun1),
      );
   }

   private function workflowHintOnly(): array {
      return array(
         'workflow_hint' => $this->tpl()->get_tpl('dbxKi|ki-briefing-workflow-hint', array()),
      );
   }

   private function areaIntro(string $icon, string $title, string $text): string {
      dbx()->add_css('dbxKi', 'dbxKi.css');
      return $this->tpl()->get_tpl('dbxKi|ki-briefing-area-intro', array(
         'area_icon' => $this->esc($icon),
         'area_title' => $this->esc($title),
         'area_text' => $this->esc($text),
      ));
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
      $tpl = $this->tpl();

      $groups = $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-content', array(
            'page_create_url' => $this->esc($this->moduleUrl('briefing_page_create')),
            'page_update_url' => $this->esc($this->moduleUrl('briefing_page_update')),
            'page_translate_url' => $this->esc($this->moduleUrl('briefing_page_translate')),
            'pages_translate_url' => $this->esc($this->moduleUrl('translation_sync_all')),
         ))
         . $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-design', array(
            'design_create_url' => $this->esc($this->moduleUrl('briefing_design_edit', array('mode' => 'create'))),
            'design_update_url' => $this->esc($this->moduleUrl('briefing_design_edit', array('mode' => 'update'))),
         ))
         . $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-module', array(
            'module_new_url' => $this->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_new'),
            'module_edit_url' => $this->esc($this->moduleUrl('briefing_module_edit')),
         ));

      return $tpl->get_tpl('dbxKi|ki-briefing-hub', $this->withModuleBar(array_merge(array(
         'area_intro' => '',
         'recipe_groups' => $groups,
         'import_url' => $this->esc($this->moduleUrl('bundle')),
         'styles_url' => $this->esc($this->moduleUrl('briefing_styles')),
         'bundle_version' => $this->esc(self::BRIEFING_VERSION),
      ), $this->briefingWorkflowData('briefing')), 'briefing',
         '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($this->moduleUrl('briefing_styles')) . '" title="Schreibstile"><i class="bi bi-type"></i></a>'));
   }

   public function renderContentHub(): string {
      $this->ensureContentBootstrap();
      $tpl = $this->tpl();

      $groups = $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-content', array(
         'page_create_url' => $this->esc($this->moduleUrl('briefing_page_create')),
         'page_update_url' => $this->esc($this->moduleUrl('briefing_page_update')),
         'page_translate_url' => $this->esc($this->moduleUrl('briefing_page_translate')),
         'pages_translate_url' => $this->esc($this->moduleUrl('translation_sync_all')),
      ));

      return $tpl->get_tpl('dbxKi|ki-briefing-hub', $this->withModuleBar(array_merge(array(
         'area_intro' => $this->areaIntro(
            'dbx/modules/dbxKi/tpl/img/area-content.svg',
            'Content-KI',
            'Neue Seiten anlegen, bestehende überarbeiten oder in weitere Sprachen übertragen lassen — einzeln oder für einen ganzen Ordner. Jede Änderung durchläuft Briefing, ZIP-Auftrag und eine geprüfte Vorschau, bevor sie übernommen wird.'
         ),
         'recipe_groups' => $groups,
         'import_url' => $this->esc($this->moduleUrl('bundle')),
         'styles_url' => $this->esc($this->moduleUrl('briefing_styles')),
         'bundle_version' => $this->esc(self::BRIEFING_VERSION),
      ), $this->briefingWorkflowData('briefing_content')), 'briefing_content',
         '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($this->moduleUrl('briefing_styles')) . '" title="Schreibstile"><i class="bi bi-type"></i></a>'));
   }

   public function renderModuleHub(): string {
      $this->ensureContentBootstrap();
      $tpl = $this->tpl();

      $groups = $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-module', array(
         'module_new_url' => $this->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_new'),
         'module_edit_url' => $this->esc($this->moduleUrl('briefing_module_edit')),
      ));

      return $tpl->get_tpl('dbxKi|ki-briefing-area-hub', $this->withModuleBar(array_merge(array(
         'area_intro' => $this->areaIntro(
            'dbx/modules/dbxKi/tpl/img/area-module.svg',
            'Modul-KI',
            'Ein bestehendes Modul gezielt erweitern, anpassen oder reparieren lassen — mit vollständigem Modulkontext, harten dbxapp-Regeln und einem Backup vor jeder Übernahme. Neue Module entstehen zuerst über den Modul-Wizard.'
         ),
         'recipe_groups' => $groups,
      ), $this->workflowHintOnly()), 'briefing_module'));
   }

   public function renderDesignHub(): string {
      $this->ensureContentBootstrap();
      $tpl = $this->tpl();

      $groups = $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-design', array(
         'design_create_url' => $this->esc($this->moduleUrl('briefing_design_edit', array('mode' => 'create'))),
         'design_update_url' => $this->esc($this->moduleUrl('briefing_design_edit', array('mode' => 'update'))),
      ));

      return $tpl->get_tpl('dbxKi|ki-briefing-area-hub', $this->withModuleBar(array_merge(array(
         'area_intro' => $this->areaIntro(
            'dbx/modules/dbxKi/tpl/img/area-design.svg',
            'Design-KI',
            'Ein neues Design auf Basis eines Ausgangsdesigns entwickeln oder ein bestehendes in Aufteilung, Menü, Branding und Footer anpassen lassen — als vollständiges, geprüftes Antwortpaket mit Vorschau vor der Aktivierung.'
         ),
         'recipe_groups' => $groups,
      ), $this->workflowHintOnly()), 'briefing_design'));
   }
}
