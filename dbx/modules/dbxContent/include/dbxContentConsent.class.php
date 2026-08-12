<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap.php';

class dbxContentConsent {

   public function run(): string {
      return '<div class="dbx-consent-panel card">'
         . '<div class="card-body">'
         . '<h5 class="card-title">Datenschutz-Einstellungen</h5>'
         . '<p class="text-muted mb-3">Legen Sie fest, welche Cookies auf dieser Website und welche externen Inhalte geladen werden dürfen.</p>'
         . '<div class="form-check mb-2">'
         . '<input class="form-check-input" type="checkbox" id="dbxConsentCookies" data-dbx-consent-cookies checked disabled>'
         . '<label class="form-check-label" for="dbxConsentCookies">Technisch notwendig <span class="text-muted">(eigene Website, immer aktiv)</span></label>'
         . '</div>'
         . '<div class="form-check mb-3">'
         . '<input class="form-check-input" type="checkbox" id="dbxConsentYoutube" data-dbx-consent-youtube>'
         . '<label class="form-check-label" for="dbxConsentYoutube">Externe Medien <span class="text-muted">(YouTube)</span></label>'
         . '</div>'
         . '<div class="dbx-consent-panel-actions">'
         . '<button type="button" class="btn btn-primary btn-sm" data-dbx-consent-action="accept-all">Alle akzeptieren</button>'
         . '<button type="button" class="btn btn-outline-secondary btn-sm" data-dbx-consent-action="necessary">Nur notwendige</button>'
         . '<button type="button" class="btn btn-success btn-sm" data-dbx-consent-action="save">Speichern</button>'
         . '<button type="button" class="btn btn-outline-danger btn-sm" data-dbx-consent-action="reject" data-dbx-tooltip="Alle Zustimmungen widerrufen und Hinweis erneut anzeigen">Ablehnen</button>'
         . '</div>'
         . '</div>'
         . '</div>';
   }
}

?>
