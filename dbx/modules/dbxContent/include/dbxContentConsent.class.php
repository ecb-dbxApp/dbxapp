<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap.php';

class dbxContentConsent {

   private function texts(): \dbxForm {
      dbx()->get_system_obj('dbxForm', 'use');
      $form = new \dbxForm();
      $form->set_form_help_enabled(false);
      $form->set_field_definition('dbxContent|consent');
      $form->load_fd_messages();
      return $form;
   }

   private function h($value): string {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   public function run(): string {
      $t = $this->texts();
      return '<div class="dbx-consent-panel card">'
         . '<div class="card-body">'
         . '<h5 class="card-title">' . $this->h($t->get_fd_message('panel_title')) . '</h5>'
         . '<p class="text-muted mb-3">' . $this->h($t->get_fd_message('panel_text')) . '</p>'
         . '<div class="form-check mb-2">'
         . '<input class="form-check-input" type="checkbox" id="dbxConsentCookies" data-dbx-consent-cookies checked disabled>'
         . '<label class="form-check-label" for="dbxConsentCookies">' . $this->h($t->get_fd_message('necessary_label')) . ' <span class="text-muted">' . $this->h($t->get_fd_message('necessary_hint')) . '</span></label>'
         . '</div>'
         . '<div class="form-check mb-3">'
         . '<input class="form-check-input" type="checkbox" id="dbxConsentYoutube" data-dbx-consent-youtube>'
         . '<label class="form-check-label" for="dbxConsentYoutube">' . $this->h($t->get_fd_message('external_label')) . ' <span class="text-muted">' . $this->h($t->get_fd_message('external_hint')) . '</span></label>'
         . '</div>'
         . '<div class="dbx-consent-panel-actions">'
         . '<button type="button" class="btn btn-primary btn-sm" data-dbx-consent-action="accept-all">' . $this->h($t->get_fd_message('accept_all')) . '</button>'
         . '<button type="button" class="btn btn-outline-secondary btn-sm" data-dbx-consent-action="necessary">' . $this->h($t->get_fd_message('necessary_only')) . '</button>'
         . '<button type="button" class="btn btn-success btn-sm" data-dbx-consent-action="save">' . $this->h($t->get_fd_message('save')) . '</button>'
         . '<button type="button" class="btn btn-outline-danger btn-sm" data-dbx-consent-action="reject" data-dbx-tooltip="' . $this->h($t->get_fd_message('reject_tooltip')) . '">' . $this->h($t->get_fd_message('reject')) . '</button>'
         . '</div>'
         . '</div>'
         . '</div>';
   }
}

?>
