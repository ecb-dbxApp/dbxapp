<?php
namespace dbx\dbxAdmin;

trait dbxDashboardUpdateStatusServiceTrait {

   /**
    * Reads the local updater state once per dashboard request.
    *
    * dbxPackageManager::local_status() reads only local manifests, receipts and
    * the accepted catalog cache. Dashboard requests never access the network.
    *
    * @return array<string,mixed>
    */
   private function update_status(): array {
      if (is_array($this->update_status_cache)) {
         return $this->update_status_cache;
      }

      try {
         dbx()->get_system_obj('dbxPackageManager', 'use');
         $this->update_status_cache = \dbxPackageManager::configured()->local_status();
         $this->update_status_cache['status_available'] = true;
      } catch (\Throwable $exception) {
         $this->update_status_cache = array(
            'current_version' => '',
            'available_version' => '',
            'update_available' => false,
            'checked_at' => '',
            'staged_version' => '',
            'stop_available' => false,
            'status_available' => false,
         );
      }

      return $this->update_status_cache;
   }

   /**
    * Normalizes the cached updater state for dashboard and navigation output.
    *
    * @param array<string,mixed> $status
    * @return array<string,string>
    */
   private function update_state(array $status): array {
      $current = (string)($status['current_version'] ?? '');
      $staged = (string)($status['staged_version'] ?? '');
      $ready = !empty($status['stop_available']);

      if ($ready) {
         return array(
            'class' => 'ready',
            'icon' => 'bi-shield-check',
            'status_message' => 'update_status_ready',
            'action_message' => 'update_action_ready',
            'nav_message' => 'update_nav_ready',
            'action_class' => 'btn-primary',
         );
      }

      if (!empty($status['update_available'])) {
         return array(
            'class' => 'available',
            'icon' => 'bi-cloud-arrow-down-fill',
            'status_message' => 'update_status_available',
            'action_message' => 'update_action_available',
            'nav_message' => 'update_nav_available',
            'action_class' => 'btn-warning',
         );
      }

      if (!empty($status['status_available'])
         && (string)($status['checked_at'] ?? '') !== '') {
         return array(
            'class' => 'current',
            'icon' => 'bi-check-circle-fill',
            'status_message' => 'update_status_current',
            'action_message' => 'update_action_current',
            'nav_message' => 'update_nav_current',
            'action_class' => 'btn-outline-primary',
         );
      }

      return array(
         'class' => 'unknown',
         'icon' => 'bi-question-circle-fill',
         'status_message' => 'update_status_unknown',
         'action_message' => 'update_action_unknown',
         'nav_message' => 'update_nav_unknown',
         'action_class' => 'btn-outline-primary',
      );
   }

   /**
    * Renders the local update state below Status & Health.
    */
   private function update_status_panel(\dbxForm $texts): string {
      $status = $this->update_status();
      $state = $this->update_state($status);
      $current = trim((string)($status['current_version'] ?? ''));
      $available = trim((string)($status['available_version'] ?? ''));
      $staged = trim((string)($status['staged_version'] ?? ''));

      if ($state['class'] === 'ready') {
         $status_text = $texts->format_fd_message(
            $state['status_message'],
            array('version' => $staged)
         );
      } elseif ($state['class'] === 'available') {
         $status_text = $texts->format_fd_message(
            $state['status_message'],
            array('version' => $available)
         );
      } else {
         $status_text = $texts->get_fd_message($state['status_message']);
      }

      $version_text = $available !== ''
         ? $texts->format_fd_message(
            'update_versions',
            array('current' => $current, 'available' => $available)
         )
         : $texts->format_fd_message(
            'update_version_current',
            array('current' => $current !== '' ? $current : '–')
         );
      $checked_at = strtotime((string)($status['checked_at'] ?? ''));
      $checked_text = $checked_at
         ? $texts->format_fd_message(
            'update_checked',
            array('date' => date('d.m.Y H:i', $checked_at))
         )
         : $texts->get_fd_message('update_not_checked');

      return dbx()->get_system_obj('dbxTPL')->get_tpl(
         'dbxAdmin|admin-dashboard-update-status',
         array(
            'update_title' => dbx()->esc($texts->get_fd_message('update_title')),
            'state_class' => dbx()->esc($state['class']),
            'state_icon' => dbx()->esc($state['icon']),
            'status_text' => dbx()->esc($status_text),
            'version_text' => dbx()->esc($version_text),
            'checked_text' => dbx()->esc($checked_text),
            'action_class' => dbx()->esc($state['action_class']),
            'action_label' => dbx()->esc(
               $texts->get_fd_message($state['action_message'])
            ),
         )
      );
   }
}
