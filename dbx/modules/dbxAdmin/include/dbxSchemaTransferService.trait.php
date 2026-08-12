<?php
namespace dbx\dbxAdmin;

trait dbxSchemaTransferServiceTrait {



   /**
    * Fuehrt den Tabellen-Transferprozess aus.
    *
    * @return string
    */
   private function run_transfer() {
      $texts = $this->schema_texts();
      $sourceServer = dbx()->get_modul_var('source_server', '', 'parameter');
      $sourceTable  = dbx()->get_modul_var('source_table', '', 'parameter');
      $start        = dbx()->get_modul_var('start', 0, 'int');

      if (!$sourceServer || !$sourceTable) {
         return '<div class="alert alert-danger">'
            . $this->esc($texts->get_fd_message('missing_source'))
            . '</div>';
      }

      if (!$start) {
         return $this->form_transfer($sourceServer, $sourceTable);
      }

      $targetServer = dbx()->get_modul_var('target_server', '', 'parameter');
      $targetTable  = dbx()->get_modul_var('target_table', $sourceTable, 'parameter');
      $createTarget = dbx()->get_modul_var('create_target', 1, 'int');
      $truncate     = dbx()->get_modul_var('truncate_target', 1, 'int');
      $reset        = dbx()->get_modul_var('reset', 0, 'int');
      $cmd          = dbx()->get_modul_var('proc_cmd', '', 'parameter');
      $oDD          = dbx()->get_system_obj('dbxDD');

      if ($reset) {
         $oDD->transfer_table($sourceServer, $sourceTable, $targetServer, $targetTable, 'reset', $createTarget, $truncate);
      }

      $nextUrl = $this->build_url('db', 'transfer', array(
         'source_server'   => $sourceServer,
         'source_table'    => $sourceTable,
         'target_server'   => $targetServer,
         'target_table'    => $targetTable,
         'create_target'   => $createTarget,
         'truncate_target' => $truncate,
         'start'           => 1,
      ));

      if ($cmd && in_array($cmd, array('pause', 'resume', 'continue', 'cancel'), true)) {
         $state = $oDD->transfer_table($sourceServer, $sourceTable, $targetServer, $targetTable, $cmd, $createTarget, $truncate);
         return $this->render_process('Transfer: ' . $sourceServer . '|' . $sourceTable . ' -> ' . $targetServer . '|' . $targetTable, $state, $nextUrl, $this->build_url('db', 'list_db'));
      }

      $state = $oDD->transfer_table($sourceServer, $sourceTable, $targetServer, $targetTable, 'step', $createTarget, $truncate);

      return $this->render_process('Transfer: ' . $sourceServer . '|' . $sourceTable . ' -> ' . $targetServer . '|' . $targetTable, $state, $nextUrl, $this->build_url('db', 'list_db'));
   }



   /**
    * Rendert das Formular fuer Tabellen-Transfer.
    *
    * @param string $sourceServer Eingabeparameter fuer diese Methode.
    * @param string $sourceTable Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function form_transfer($sourceServer, $sourceTable) {
      $oForm = dbx()->get_system_obj('dbxForm');
      $servers = $this->get_server_options();

      $defaultTarget = '';
      foreach ($servers as $server => $label) {
         if ($server != $sourceServer) {
            $defaultTarget = $server;
            break;
         }
      }
      if (!$defaultTarget) {
         $defaultTarget = $sourceServer;
      }

      $oForm->init('form-schema-action');
      $oForm->_fd = 'dbxAdmin|schema-report';
      $oForm->load_fd_messages();
      $oForm->_data = array(
         'source_server'   => $sourceServer,
         'source_table'    => $sourceTable,
         'target_server'   => $defaultTarget,
         'target_table'    => $sourceTable,
         'create_target'   => 1,
         'truncate_target' => 1,
      );
      $oForm->_action = $this->build_url('db', 'transfer', array(
         'source_server' => $sourceServer,
         'source_table'  => $sourceTable,
      ));
      $oForm->_msg_info = $oForm->get_fd_message('transfer_info');

      $yesNo = array(
         1 => $oForm->get_fd_message('yes'),
         0 => $oForm->get_fd_message('no'),
      );
      $oForm->add_fld('source_server', 'text-label', label: $oForm->get_fd_message('label_source_server'), rules: 'parameter');
      $oForm->add_fld('source_table', 'text-label', label: $oForm->get_fd_message('label_source_table'), rules: 'parameter');
      $oForm->add_fld('target_server', 'select-single-label', label: $oForm->get_fd_message('label_target_server'), rules: 'parameter', options: $servers);
      $oForm->add_fld('target_table', 'text-label', label: $oForm->get_fd_message('label_target_table'), rules: 'parameter|min=1');
      $oForm->add_fld('create_target', 'select-single-label', label: $oForm->get_fd_message('label_create_target'), rules: 'int', options: $yesNo);
      $oForm->add_fld('truncate_target', 'select-single-label', label: $oForm->get_fd_message('label_truncate_target'), rules: 'int', options: $yesNo);

      if ($oForm->submit() && !$oForm->errors()) {
         dbx()->set_modul_var('target_server', $oForm->get_post('target_server', $defaultTarget, 'parameter'));
         dbx()->set_modul_var('target_table', $oForm->get_post('target_table', $sourceTable, 'parameter'));
         dbx()->set_modul_var('create_target', $oForm->get_post('create_target', 1, 'int'));
         dbx()->set_modul_var('truncate_target', $oForm->get_post('truncate_target', 1, 'int'));
         dbx()->set_modul_var('start', 1);
         dbx()->set_modul_var('reset', 1);

         return $this->run_transfer();
      }

      return $oForm->run();
   }
}
