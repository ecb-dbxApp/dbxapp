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
      $form->_data['update_operation'] = '';
      $form->add_fld(
         'update_request',
         'hidden',
         rules: 'int',
         dd: ''
      );
      $form->add_fld(
         'update_operation',
         'hidden',
         rules: 'parameter',
         dd: ''
      );

      if ($form->submit()) {
         $operation = (string)$form->get_fld_value(
            'update_operation',
            '',
            'parameter'
         );
         try {
            switch ($operation) {
               case 'start':
                  $prepared = $service->prepare();
                  if (!empty($prepared['staged'])) {
                     $form->set_msg_ok($form->format_fd_message(
                        'prepare_success',
                        array('version' => $prepared['manifest']['version'])
                     ));
                  } else {
                     $form->set_msg_ok($form->format_fd_message(
                        'check_success',
                        array('version' => $prepared['manifest']['version'])
                     ));
                  }
                  break;

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

               case 'stop':
                  $stopped = $service->cancel();
                  $form->set_msg_ok($form->format_fd_message(
                     'stop_success',
                     array('version' => $stopped['version'])
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
                  $form->set_error($form->get_fd_message('operation_invalid'));
            }
         } catch (\Throwable $exception) {
            $message = (int)$exception->getCode() === 404
               ? $form->get_fd_message('manifest_unavailable')
               : $exception->getMessage();
            $form->set_error($message);
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

      $hasStagedUpdate = $status['staged_version'] !== '';
      $canInstall = $hasStagedUpdate && !empty($status['update_available']);
      if ($canInstall) {
         $statusClass = 'is-ready';
         $statusText = $form->format_fd_message(
            'ready_to_install',
            array('version' => $status['staged_version'])
         );
      } elseif (!empty($status['update_available'])) {
         $statusClass = 'is-update';
         $statusText = $form->get_fd_message('update_available');
      } else {
         $statusClass = 'is-current';
         $statusText = $form->get_fd_message('up_to_date');
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
         'status_class' => $statusClass,
         'status_text' => $statusText,
         'start_label' => $form->get_fd_message('start_label'),
         'check_label' => $form->get_fd_message('check_label'),
         'stage_label' => $form->get_fd_message('stage_label'),
         'install_label' => $form->get_fd_message('install_label'),
         'stop_label' => $form->get_fd_message('stop_label'),
         'rollback_label' => $form->get_fd_message('rollback_label'),
         'start_class' => $hasStagedUpdate ? 'd-none' : '',
         'decision_class' => $hasStagedUpdate ? '' : 'd-none',
         'stage_disabled' => !empty($status['update_available']) ? '' : 'disabled',
         'install_disabled' => $canInstall ? '' : 'disabled',
         'stop_disabled' => !empty($status['stop_available']) ? '' : 'disabled',
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
         'msg_class' => '',
      );
      foreach ($replacements as $key => $value) {
         $form->add_rep($key, $value);
      }

      return $form->run();
   }
}
