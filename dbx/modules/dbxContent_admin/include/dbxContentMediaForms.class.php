<?php
namespace dbx\dbxContent_admin;

/**
 * Gemeinsame dbxForm-Definitionen fuer den JavaScript-Medienbrowser.
 *
 * cms.js wird im CMS, in der SEO-Verwaltung, im Shop und in der
 * Modulbildverwaltung wiederverwendet. Diese Klasse stellt dafuer dieselben
 * serverseitig gerenderten Formulare und dieselbe Token-Pruefung bereit.
 */
class dbxContentMediaForms {

   /**
    * Erzeugt bewusst eine eigene Formularinstanz.
    *
    * Der Medienbrowser kann in bereits laufende dbxForm-Seiten eingebettet
    * sein. Ein neues Objekt verhindert, dass init() deren Formularzustand
    * ueberschreibt.
    */
   private function form(string $fid, string $template, string $action): \dbxForm {
      dbx()->get_system_obj('dbxForm', 'use');
      $form = new \dbxForm();
      $form->init($fid, 'dbxContent_admin|' . $template);
      // Die Formulare werden auch aus Shop-, SEO- und Modulansichten
      // gerendert, ihre POST-Endpunkte laufen aber in dbxContent_admin.
      // Ein fester Sysdata-Scope hält Token-Erzeugung und Prüfung identisch.
      $form->_dbx_modul = 'dbxContent_admin';
      $form->_sys = $form->load_sysdata();
      $form->_action = $action;
      $form->_msg_info = '';
      $form->set_form_help_enabled(false);
      $form->add_rep('action', dbx()->esc($action));
      return $form;
   }

   /**
    * Rendert Upload- und optional YouTube-Formular als inerte DOM-Templates.
    */
   public function renderTemplates(
      string $uploadAction,
      string $uploadFid = 'cms-media-upload',
      string $externalAction = '',
      string $externalFid = 'cms-external-video'
   ): string {
      $upload = $this->form($uploadFid, 'cms-media-upload-form', $uploadAction)->run();
      $external = '';
      if ($externalAction !== '') {
         $external = $this->form($externalFid, 'cms-external-video-form', $externalAction)->run();
      }

      return dbx()->get_system_obj('dbxTPL')->get_tpl(
         'dbxContent_admin|cms-media-browser-form-templates',
         array(
            'upload_form' => $upload,
            'external_video_form' => $external,
         )
      );
   }

   /**
    * Prueft einen Medienbrowser-POST und liefert zugleich den Folgetoken.
    *
    * @return array{submitted:bool,security:array{name:string,value:string}}
    */
   public function verify(string $kind, string $action, string $fid = ''): array {
      $kind = strtolower(trim($kind));
      $isExternal = $kind === 'external';
      if ($fid === '') {
         $fid = $isExternal ? 'cms-external-video' : 'cms-media-upload';
      }
      $form = $this->form(
         $fid,
         $isExternal ? 'cms-external-video-form' : 'cms-media-upload-form',
         $action
      );
      $submitted = (bool)$form->submit();
      return array(
         'submitted' => $submitted,
         'security' => $form->get_security_data(),
      );
   }
}
