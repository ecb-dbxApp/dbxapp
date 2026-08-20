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
      $form->set_action($action);
      $form->set_field_definition('dbxContent_admin|cms-page');
      $form->load_fd_messages();
      $form->_msg_info = '';
      $form->set_form_help_enabled(false);
      $form->add_rep('action', dbx()->esc($action));
      foreach (array(
         'upload_drop_label',
         'upload_folder_label',
         'upload_folder_title',
         'upload_submit_title',
         'upload_label',
         'external_video_placeholder',
         'external_video_submit_title',
      ) as $key) {
         $form->add_rep($key, dbx()->esc($form->get_fd_message($key)));
      }
      return $form;
   }

   /**
    * Rendert Upload- und optional YouTube-Formular als inerte DOM-Templates.
    */
   public function render_templates(
      string $upload_action,
      string $upload_fid = 'cms-media-upload',
      string $external_action = '',
      string $external_fid = 'cms-external-video'
   ): string {
      $upload = $this->form($upload_fid, 'cms-media-upload-form', $upload_action)->run();
      $external = '';
      if ($external_action !== '') {
         $external = $this->form($external_fid, 'cms-external-video-form', $external_action)->run();
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
    * Prüft einen Medienbrowser-POST und liefert zugleich den Folgetoken.
    *
    * @return array{submitted:bool,security:array{name:string,value:string}}
    */
   public function verify(string $kind, string $action, string $fid = ''): array {
      $kind = strtolower(trim($kind));
      $is_external = $kind === 'external';
      if ($fid === '') {
         $fid = $is_external ? 'cms-external-video' : 'cms-media-upload';
      }
      $form = $this->form(
         $fid,
         $is_external ? 'cms-external-video-form' : 'cms-media-upload-form',
         $action
      );
      $submitted = (bool)$form->submit();
      return array(
         'submitted' => $submitted,
         'security' => $form->get_security_data(),
      );
   }
}
