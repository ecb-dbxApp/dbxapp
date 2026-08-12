<?php
namespace dbx\dbxAdmin;

trait dbxDashboardUpdateStatusServiceTrait {

   /**
    * Reads the local updater state once per dashboard request.
    *
    * dbxUpdateService::status() only reads files from files/update. A normal
    * dashboard request therefore never waits for GitHub or another network
    * service. The explicit update page remains responsible for checking and
    * downloading releases.
    *
    * @return array<string,mixed>
    */
   private function update_status(): array {
      if (is_array($this->updateStatusCache)) {
         return $this->updateStatusCache;
      }

      try {
         dbx()->get_include_obj('dbxUpdateService', 'dbxAdmin');
         $this->updateStatusCache = dbxUpdateService::configured()->status();
         $this->updateStatusCache['status_available'] = true;
      } catch (\Throwable $exception) {
         $this->updateStatusCache = array(
            'current_version' => '',
            'available_version' => '',
            'update_available' => false,
            'checked_at' => '',
            'staged_version' => '',
            'stop_available' => false,
            'status_available' => false,
         );
      }

      return $this->updateStatusCache;
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
      $ready = $staged !== ''
         && ($current === '' || version_compare($staged, $current, '>'));

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
         $statusText = $texts->format_fd_message(
            $state['status_message'],
            array('version' => $staged)
         );
      } elseif ($state['class'] === 'available') {
         $statusText = $texts->format_fd_message(
            $state['status_message'],
            array('version' => $available)
         );
      } else {
         $statusText = $texts->get_fd_message($state['status_message']);
      }

      $versionText = $available !== ''
         ? $texts->format_fd_message(
            'update_versions',
            array('current' => $current, 'available' => $available)
         )
         : $texts->format_fd_message(
            'update_version_current',
            array('current' => $current !== '' ? $current : '–')
         );
      $checkedAt = strtotime((string)($status['checked_at'] ?? ''));
      $checkedText = $checkedAt
         ? $texts->format_fd_message(
            'update_checked',
            array('date' => date('d.m.Y H:i', $checkedAt))
         )
         : $texts->get_fd_message('update_not_checked');

      return dbx()->get_system_obj('dbxTPL')->get_tpl(
         'dbxAdmin|admin-dashboard-update-status',
         array(
            'update_title' => dbx()->esc($texts->get_fd_message('update_title')),
            'state_class' => dbx()->esc($state['class']),
            'state_icon' => dbx()->esc($state['icon']),
            'status_text' => dbx()->esc($statusText),
            'version_text' => dbx()->esc($versionText),
            'checked_text' => dbx()->esc($checkedText),
            'action_class' => dbx()->esc($state['action_class']),
            'action_label' => dbx()->esc(
               $texts->get_fd_message($state['action_message'])
            ),
         )
      );
   }
}
