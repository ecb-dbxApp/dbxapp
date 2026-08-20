<?php
namespace dbx\dbxAdmin;

trait dbxSchemaTransferServiceTrait {



   /**
    * Führt den Tabellen-Transferprozess aus.
    *
    * @return string
    */
   private function run_transfer() {
      $texts = $this->schema_texts();
      $source_server = dbx()->get_modul_var('source_server', '', 'parameter');
      $source_table  = dbx()->get_modul_var('source_table', '', 'parameter');
      $start        = dbx()->get_modul_var('start', 0, 'int');

      if (!$source_server || !$source_table) {
         return '<div class="alert alert-danger">'
            . $this->esc($texts->get_fd_message('missing_source'))
            . '</div>';
      }

      if (!$start) {
         return $this->form_transfer($source_server, $source_table);
      }

      $target_server = dbx()->get_modul_var('target_server', '', 'parameter');
      $target_table  = dbx()->get_modul_var('target_table', $source_table, 'parameter');
      $create_target = dbx()->get_modul_var('create_target', 1, 'int');
      $truncate     = dbx()->get_modul_var('truncate_target', 1, 'int');
      $reset        = dbx()->get_modul_var('reset', 0, 'int');
      $cmd          = dbx()->get_modul_var('proc_cmd', '', 'parameter');
      $o_dd          = dbx()->get_system_obj('dbxDD');

      if ($reset) {
         $o_dd->transfer_table($source_server, $source_table, $target_server, $target_table, 'reset', $create_target, $truncate);
      }

      $next_url = $this->build_url('db', 'transfer', array(
         'source_server'   => $source_server,
         'source_table'    => $source_table,
         'target_server'   => $target_server,
         'target_table'    => $target_table,
         'create_target'   => $create_target,
         'truncate_target' => $truncate,
         'start'           => 1,
      ));

      if ($cmd && in_array($cmd, array('pause', 'resume', 'continue', 'cancel'), true)) {
         $state = $o_dd->transfer_table($source_server, $source_table, $target_server, $target_table, $cmd, $create_target, $truncate);
         return $this->render_process('Transfer: ' . $source_server . '|' . $source_table . ' -> ' . $target_server . '|' . $target_table, $state, $next_url, $this->build_url('db', 'list_db'));
      }

      $state = $o_dd->transfer_table($source_server, $source_table, $target_server, $target_table, 'step', $create_target, $truncate);

      return $this->render_process('Transfer: ' . $source_server . '|' . $source_table . ' -> ' . $target_server . '|' . $target_table, $state, $next_url, $this->build_url('db', 'list_db'));
   }



   /**
    * Rendert das Formular fuer Tabellen-Transfer.
    *
    * @param string $sourceServer Eingabeparameter fuer diese Methode.
    * @param string $sourceTable Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function form_transfer($source_server, $source_table) {
      $o_form = dbx()->get_system_obj('dbxForm');
      $servers = $this->get_server_options();

      $default_target = '';
      foreach ($servers as $server => $label) {
         if ($server != $source_server) {
            $default_target = $server;
            break;
         }
      }
      if (!$default_target) {
         $default_target = $source_server;
      }

      $o_form->init('form-schema-action', 'form-schema-action');
      $o_form->set_field_definition('dbxAdmin|schema-report');
      $o_form->load_fd_messages();
      $o_form->set_data(array(
         'source_server'   => $source_server,
         'source_table'    => $source_table,
         'target_server'   => $default_target,
         'target_table'    => $source_table,
         'create_target'   => 1,
         'truncate_target' => 1,
      ));
      $o_form->set_action($this->build_url('db', 'transfer', array(
         'source_server' => $source_server,
         'source_table'  => $source_table,
      )));
      $o_form->_msg_info = $o_form->get_fd_message('transfer_info');

      $yes_no = array(
         1 => $o_form->get_fd_message('yes'),
         0 => $o_form->get_fd_message('no'),
      );
      $o_form->add_fld('source_server', 'text-label', label: $o_form->get_fd_message('label_source_server'), rules: 'parameter');
      $o_form->add_fld('source_table', 'text-label', label: $o_form->get_fd_message('label_source_table'), rules: 'parameter');
      $o_form->add_fld('target_server', 'select-single-label', label: $o_form->get_fd_message('label_target_server'), rules: 'parameter', options: $servers);
      $o_form->add_fld('target_table', 'text-label', label: $o_form->get_fd_message('label_target_table'), rules: 'parameter|min=1');
      $o_form->add_fld('create_target', 'select-single-label', label: $o_form->get_fd_message('label_create_target'), rules: 'int', options: $yes_no);
      $o_form->add_fld('truncate_target', 'select-single-label', label: $o_form->get_fd_message('label_truncate_target'), rules: 'int', options: $yes_no);

      if ($o_form->submit() && !$o_form->errors()) {
         dbx()->set_modul_var('target_server', $o_form->get_post('target_server', $default_target, 'parameter'));
         dbx()->set_modul_var('target_table', $o_form->get_post('target_table', $source_table, 'parameter'));
         dbx()->set_modul_var('create_target', $o_form->get_post('create_target', 1, 'int'));
         dbx()->set_modul_var('truncate_target', $o_form->get_post('truncate_target', 1, 'int'));
         dbx()->set_modul_var('start', 1);
         dbx()->set_modul_var('reset', 1);

         return $this->run_transfer();
      }

      return $o_form->run();
   }
}
