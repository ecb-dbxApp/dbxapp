<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingHubRenderServiceTrait {

   private function with_module_bar(array $data, string $screen, string $actions_html = ''): array {
      return array_merge($data, $this->help()->module_bar_template_data($screen, $actions_html));
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
   private function briefing_form(string $fid, string $template, string $action, array $replacements = array()) {
      return dbxKiValue::form($fid, $template, $action, $replacements);
   }

   /**
    * Liefert die stabile dbxForm-ID fuer ein exportierbares Briefing-Rezept.
    */
   private function briefing_form_id(string $recipe): string {
      return 'ki-briefing-' . str_replace('_', '-', strtolower(trim($recipe)));
   }

   private function bar_back_hub(): string {
      return '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($this->module_url('briefing_content')) . '" title="Zurück zur Content-Übersicht"><i class="bi bi-arrow-left"></i></a>';
   }

   private function briefing_workflow_data(string $return_run1): array {
      return array(
         'workflow_hint' => $this->tpl()->get_tpl('dbxKi|ki-briefing-workflow-hint', array()),
         'import_panel' => $this->bundle()->render_import_panel($return_run1),
      );
   }

   private function workflow_hint_only(): array {
      return array(
         'workflow_hint' => $this->tpl()->get_tpl('dbxKi|ki-briefing-workflow-hint', array()),
      );
   }

   private function area_intro(string $icon, string $title, string $text): string {
      dbx()->get_system_obj('dbxAssetRegistry')->add_css('dbxKi', 'dbxKi.css');
      return $this->tpl()->get_tpl('dbxKi|ki-briefing-area-intro', array(
         'area_icon' => $this->esc($icon),
         'area_title' => $this->esc($title),
         'area_text' => $this->esc($text),
      ));
   }

   public function export_back_url(string $recipe): string {
      switch ($recipe) {
         case 'page_update':
            return $this->module_url('briefing_page_update');
         case 'page_translate':
            return $this->module_url('briefing_page_translate');
         default:
            return $this->module_url('briefing_page_create');
      }
   }

   public function render_hub(): string {
      $this->ensure_content_bootstrap();
      $tpl = $this->tpl();

      $groups = $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-content', array(
            'page_create_url' => $this->esc($this->module_url('briefing_page_create')),
            'page_update_url' => $this->esc($this->module_url('briefing_page_update')),
            'page_translate_url' => $this->esc($this->module_url('briefing_page_translate')),
            'pages_translate_url' => $this->esc($this->module_url('translation_sync_all')),
         ))
         . $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-design', array(
            'design_create_url' => $this->esc($this->module_url('briefing_design_edit', array('mode' => 'create'))),
            'design_update_url' => $this->esc($this->module_url('briefing_design_edit', array('mode' => 'update'))),
         ))
         . $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-module', array(
            'module_new_url' => $this->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_new'),
            'module_edit_url' => $this->esc($this->module_url('briefing_module_edit')),
         ));

      return $tpl->get_tpl('dbxKi|ki-briefing-hub', $this->with_module_bar(array_merge(array(
         'area_intro' => '',
         'recipe_groups' => $groups,
         'import_url' => $this->esc($this->module_url('bundle')),
         'styles_url' => $this->esc($this->module_url('briefing_styles')),
         'bundle_version' => $this->esc(self::BRIEFING_VERSION),
      ), $this->briefing_workflow_data('briefing')), 'briefing',
         '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($this->module_url('briefing_styles')) . '" title="Schreibstile"><i class="bi bi-type"></i></a>'));
   }

   public function render_content_hub(): string {
      $this->ensure_content_bootstrap();
      $tpl = $this->tpl();

      $groups = $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-content', array(
         'page_create_url' => $this->esc($this->module_url('briefing_page_create')),
         'page_update_url' => $this->esc($this->module_url('briefing_page_update')),
         'page_translate_url' => $this->esc($this->module_url('briefing_page_translate')),
         'pages_translate_url' => $this->esc($this->module_url('translation_sync_all')),
      ));

      return $tpl->get_tpl('dbxKi|ki-briefing-hub', $this->with_module_bar(array_merge(array(
         'area_intro' => $this->area_intro(
            'dbx/modules/dbxKi/tpl/img/area-content.svg',
            'Content-KI',
            'Neue Seiten anlegen, bestehende überarbeiten oder in weitere Sprachen übertragen lassen — einzeln oder für einen ganzen Ordner. Jede Änderung durchläuft Briefing, ZIP-Auftrag und eine geprüfte Vorschau, bevor sie übernommen wird.'
         ),
         'recipe_groups' => $groups,
         'import_url' => $this->esc($this->module_url('bundle')),
         'styles_url' => $this->esc($this->module_url('briefing_styles')),
         'bundle_version' => $this->esc(self::BRIEFING_VERSION),
      ), $this->briefing_workflow_data('briefing_content')), 'briefing_content',
         '<a class="btn btn-outline-secondary btn-sm" href="' . $this->esc($this->module_url('briefing_styles')) . '" title="Schreibstile"><i class="bi bi-type"></i></a>'));
   }

   public function render_module_hub(): string {
      $this->ensure_content_bootstrap();
      $tpl = $this->tpl();

      $groups = $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-module', array(
         'module_new_url' => $this->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_new'),
         'module_edit_url' => $this->esc($this->module_url('briefing_module_edit')),
      ));

      return $tpl->get_tpl('dbxKi|ki-briefing-area-hub', $this->with_module_bar(array_merge(array(
         'area_intro' => $this->area_intro(
            'dbx/modules/dbxKi/tpl/img/area-module.svg',
            'Modul-KI',
            'Ein bestehendes Modul gezielt erweitern, anpassen oder reparieren lassen — mit vollständigem Modulkontext, harten dbxapp-Regeln und einem Backup vor jeder Übernahme. Neue Module entstehen zuerst über den Modul-Wizard.'
         ),
         'recipe_groups' => $groups,
      ), $this->workflow_hint_only()), 'briefing_module'));
   }

   public function render_design_hub(): string {
      $this->ensure_content_bootstrap();
      $tpl = $this->tpl();

      $groups = $tpl->get_tpl('dbxKi|ki-briefing-recipe-group-design', array(
         'design_create_url' => $this->esc($this->module_url('briefing_design_edit', array('mode' => 'create'))),
         'design_update_url' => $this->esc($this->module_url('briefing_design_edit', array('mode' => 'update'))),
      ));

      return $tpl->get_tpl('dbxKi|ki-briefing-area-hub', $this->with_module_bar(array_merge(array(
         'area_intro' => $this->area_intro(
            'dbx/modules/dbxKi/tpl/img/area-design.svg',
            'Design-KI',
            'Ein neues Design auf Basis eines Ausgangsdesigns entwickeln oder ein bestehendes in Aufteilung, Menü, Branding und Footer anpassen lassen — als vollständiges, geprüftes Antwortpaket mit Vorschau vor der Aktivierung.'
         ),
         'recipe_groups' => $groups,
      ), $this->workflow_hint_only()), 'briefing_design'));
   }
}
