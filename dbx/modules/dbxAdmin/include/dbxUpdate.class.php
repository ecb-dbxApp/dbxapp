<?php

declare(strict_types=1);

namespace dbx\dbxAdmin;

/**
 * dbxForm/dbxTPL controller for the admin-only system updater.
 */
class dbxUpdate
{
   public function run(): string
   {
      dbx()->get_include_obj('dbxUpdateService', 'dbxAdmin');
      $config = dbx()->get_config('dbxAdmin');
      $manifestUrl = is_array($config)
         ? (string)($config['update_manifest_url'] ?? '')
         : '';
      $cacheTtl = is_array($config)
         ? (int)($config['update_cache_ttl'] ?? 21600)
         : 21600;
      $service = new dbxUpdateService('', $manifestUrl, $cacheTtl);

      $form = dbx()->get_system_obj('dbxForm');
      $form->init('admin-system-update', 'admin-update');
      $form->_fd = 'dbxAdmin|admin-update';
      $form->load_fd_messages();
      $form->set_form_help_enabled(false);
      $form->_action = '?dbx_modul=dbxAdmin&dbx_run1=update';
      $form->_msg_info = '';
      $form->_msg_success = '';
      $form->_msg_error = '';
      $form->_msg_warning = '';
      $form->_data['update_request'] = 1;
      $form->add_fld(
         'update_request',
         'hidden',
         rules: 'int',
         dd: ''
      );

      if ($form->submit()) {
         $operation = (string)dbx()->get_modul_var(
            'update_operation',
            '',
            'parameter'
         );
         try {
            switch ($operation) {
               case 'check':
                  $manifest = $service->check(true);
                  $form->set_msg_ok($form->format_fd_message(
                     'check_success',
                     array('version' => $manifest['version'])
                  ));
                  break;

               case 'stage':
                  $staged = $service->stage();
                  $form->set_msg_ok($form->format_fd_message(
                     'stage_success',
                     array('version' => $staged['manifest']['version'])
                  ));
                  break;

               case 'install':
                  $installed = $service->install();
                  $form->set_msg_ok($form->format_fd_message(
                     'install_success',
                     array('version' => $installed['to_version'])
                  ));
                  break;

               case 'rollback':
                  $rollback = $service->rollback();
                  $form->set_msg_ok($form->format_fd_message(
                     'rollback_success',
                     array('version' => $rollback['from_version'])
                  ));
                  break;

               default:
                  $form->set_msg_error($form->get_fd_message('operation_invalid'));
            }
         } catch (\Throwable $exception) {
            $form->set_msg_error($exception->getMessage());
         }
      }

      $status = $service->status();
      $available = $status['available_version'] !== ''
         ? (string)$status['available_version']
         : $form->get_fd_message('not_checked');
      $staged = $status['staged_version'] !== ''
         ? (string)$status['staged_version']
         : $form->get_fd_message('not_staged');
      $checked = $status['checked_at'] !== ''
         ? (string)$status['checked_at']
         : $form->get_fd_message('never');
      $releaseLink = '';
      if ($status['release_url'] !== '') {
         $releaseLink = '<a class="btn btn-outline-secondary btn-sm"'
            . ' href="' . dbx()->esc((string)$status['release_url']) . '"'
            . ' target="_blank" rel="noopener noreferrer">'
            . '<i class="bi bi-box-arrow-up-right"></i> '
            . dbx()->esc($form->get_fd_message('release_notes'))
            . '</a>';
      }

      $replacements = array(
         'bar_title' => $form->get_fd_message('bar_title'),
         'bar_subtitle' => $form->get_fd_message('bar_subtitle'),
         'bar_icon' => 'bi-arrow-repeat',
         'bar_actions' => $releaseLink,
         'bar_class' => 'dbx-module-bar',
         'bar_title_class' => 'dbx-module-bar-titleblock',
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_middle' => '',
         'bar_extra' => '',
         'bar_actions_class' => 'dbx-module-bar-actions',
         'intro' => $form->get_fd_message('intro'),
         'current_label' => $form->get_fd_message('current_label'),
         'available_label' => $form->get_fd_message('available_label'),
         'staged_label' => $form->get_fd_message('staged_label'),
         'checked_label' => $form->get_fd_message('checked_label'),
         'current_version' => dbx()->esc((string)$status['current_version']),
         'available_version' => dbx()->esc($available),
         'staged_version' => dbx()->esc($staged),
         'checked_at' => dbx()->esc($checked),
         'status_class' => !empty($status['update_available']) ? 'is-update' : 'is-current',
         'status_text' => !empty($status['update_available'])
            ? $form->get_fd_message('update_available')
            : $form->get_fd_message('up_to_date'),
         'check_label' => $form->get_fd_message('check_label'),
         'stage_label' => $form->get_fd_message('stage_label'),
         'install_label' => $form->get_fd_message('install_label'),
         'rollback_label' => $form->get_fd_message('rollback_label'),
         'stage_disabled' => !empty($status['update_available']) ? '' : 'disabled',
         'install_disabled' => $status['staged_version'] !== '' ? '' : 'disabled',
         'rollback_disabled' => !empty($status['rollback_available']) ? '' : 'disabled',
         'install_confirm_title' => $form->get_fd_message('install_confirm_title'),
         'install_confirm' => $form->get_fd_message('install_confirm'),
         'install_confirm_hint' => $form->get_fd_message('install_confirm_hint'),
         'rollback_confirm_title' => $form->get_fd_message('rollback_confirm_title'),
         'rollback_confirm' => $form->get_fd_message('rollback_confirm'),
         'rollback_confirm_hint' => $form->get_fd_message('rollback_confirm_hint'),
         'security_title' => $form->get_fd_message('security_title'),
         'security_text' => $form->get_fd_message('security_text'),
         'database_title' => $form->get_fd_message('database_title'),
         'database_text' => $form->get_fd_message('database_text'),
      );
      foreach ($replacements as $key => $value) {
         $form->add_rep($key, $value);
      }

      return $form->run();
   }
}
