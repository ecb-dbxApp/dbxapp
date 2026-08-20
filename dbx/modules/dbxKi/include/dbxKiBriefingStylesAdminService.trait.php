<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingStylesAdminServiceTrait {

   public function render_styles_admin(): string {
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

      $data = $this->with_module_bar(array(
         'hub_url' => $this->esc($this->module_url('briefing')),
         'save_url' => $this->esc($this->module_url('briefing_styles_save')),
         'style_rows' => $rows,
      ), 'briefing_styles', $this->bar_back_hub());

      return $this->briefing_form(
         'ki-briefing-styles',
         'ki-briefing-styles',
         $this->module_url('briefing_styles_save'),
         $data
      )->run();
   }

   public function handle_styles_save(): string {
      try {
         $form = $this->briefing_form(
            'ki-briefing-styles',
            'ki-briefing-styles',
            $this->module_url('briefing_styles_save')
         );
         if (!$form->submit()) {
            throw new \RuntimeException('Ungueltiger oder abgelaufener Formular-Token.');
         }
         if (dbx()->get_request_var('styles_action', 'save', 'parameter') === 'reset') {
            dbxKiWritingStyles::reset_to_defaults();
            dbx()->sys_msg('info', 'dbxKi', 'styles', 'Schreibstile', 'Standard wiederhergestellt');
            return $this->render_styles_admin();
         }
         $styles = dbxKiWritingStyles::parse_form_rows(
            (array) dbx()->get_request_var('style_key', array(), '*'),
            (array) dbx()->get_request_var('style_label', array(), '*'),
            (array) dbx()->get_request_var('style_prompt', array(), '*')
         );
         dbxKiWritingStyles::save($styles);
         dbx()->sys_msg('info', 'dbxKi', 'styles', 'Schreibstile gespeichert', count($styles) . ' Stile');
      } catch (\Throwable $e) {
         dbx()->sys_msg('error', 'dbxKi', 'styles', 'Speichern fehlgeschlagen', $e->getMessage());
      }
      return $this->render_styles_admin();
   }

   public function handle_styles_reset(): string {
      // Rueckwaertskompatibler GET-Endpunkt ohne schreibende Wirkung. Das
      // Zuruecksetzen erfolgt nur noch als geschuetzter dbxForm-POST.
      dbx()->sys_msg('warning', 'dbxKi', 'styles', 'Schreibstile',
         'Standardwerte bitte ueber das geschuetzte Formular zuruecksetzen.');
      return $this->render_styles_admin();
   }
}
