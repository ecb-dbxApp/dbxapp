<?php

declare(strict_types=1);

/**
 * Architekturvertrag für den zentralen Update-Status im Admin-Dashboard.
 */

function dashboard_update_assert(bool $condition, string $message): void
{
   if (!$condition) {
      throw new RuntimeException($message);
   }
}

$module = dirname(__DIR__);
$dashboard = (string)file_get_contents(
   $module . '/include/dbxDashboard.class.php'
);
$controller = (string)file_get_contents(
   $module . '/include/dbxUpdate.class.php'
);
$service = (string)file_get_contents(
   $module . '/include/dbxUpdateService.class.php'
);
$hero = (string)file_get_contents(
   $module . '/tpl/htm/admin-dashboard-hero.htm'
);
$updateCard = (string)file_get_contents(
   $module . '/tpl/htm/admin-dashboard-update-status.htm'
);
$actions = (string)file_get_contents(
   $module . '/tpl/htm/admin-dashboard-actions.htm'
);
$css = (string)file_get_contents(
   $module . '/tpl/css/admin-dashboard.css'
);

foreach (array(
   'public static function configured(): self',
   'public function status(): array',
   'This method performs file reads only.',
) as $part) {
   dashboard_update_assert(
      str_contains($service, $part),
      'Zentraler Update-Servicevertrag fehlt: ' . $part
   );
}

dashboard_update_assert(
   str_contains($controller, 'dbxUpdateService::configured()'),
   'Update-Controller verwendet nicht die zentrale Konfiguration.'
);
dashboard_update_assert(
   str_contains($dashboard, 'dbxUpdateService::configured()->status()'),
   'Dashboard liest nicht den zentralen lokalen Update-Status.'
);

$statusMethodStart = strpos($dashboard, 'private function update_status(): array');
$stateMethodStart = strpos($dashboard, 'private function update_state(array $status): array');
dashboard_update_assert(
   $statusMethodStart !== false && $stateMethodStart !== false,
   'Dashboard-Statusmethoden fehlen.'
);
$statusMethod = substr(
   $dashboard,
   $statusMethodStart,
   $stateMethodStart - $statusMethodStart
);
dashboard_update_assert(
   !str_contains($statusMethod, '->check(')
      && !str_contains($statusMethod, '->prepare(')
      && !str_contains($statusMethod, 'file_get_contents('),
   'Dashboard darf weder Netzwerkprüfung noch eigene Statusdateien verwenden.'
);

foreach (array(
   "'ready'",
   "'available'",
   "'current'",
   "'unknown'",
   "'update_status'",
   "'update_nav_badge'",
) as $part) {
   dashboard_update_assert(
      str_contains($dashboard, $part),
      'Dashboard-Darstellungsvertrag fehlt: ' . $part
   );
}

dashboard_update_assert(
   str_contains($hero, '{obj:update_status}'),
   'Update-Status fehlt im Bereich Status & Health.'
);
dashboard_update_assert(
   str_contains($actions, '{obj:action_update}'),
   'System-Update fehlt im Schnellzugriff.'
);
foreach (array(
   'dbx-admin-dashboard-update-{state_class}',
   '{status_text}',
   '{version_text}',
   '{checked_text}',
   'dbx_run1=update',
) as $part) {
   dashboard_update_assert(
      str_contains($updateCard, $part),
      'Update-Status-Templatevertrag fehlt: ' . $part
   );
}
foreach (array(
   '.dbx-admin-dashboard-update-nav',
   '.dbx-admin-dashboard-update-badge',
   '.dbx-admin-dashboard-update-ready',
   '.dbx-admin-dashboard-update-available',
) as $part) {
   dashboard_update_assert(
      str_contains($css, $part),
      'Update-Status-CSS fehlt: ' . $part
   );
}

$loadMessages = static function (string $file): array {
   $messages = array();
   $fields = array();
   include $file;
   return $messages;
};

$messageSets = array();
foreach (array('de' => '', 'en' => '_en', 'es' => '_es') as $language => $suffix) {
   $messageSets[$language] = $loadMessages(
      $module . '/fd/admin-dashboard-status' . $suffix . '.fd.php'
   );
   $dashboardTemplate = (string)file_get_contents(
      $module . '/tpl/htm/admin-dashboard' . $suffix . '.htm'
   );
   foreach (array(
      '{update_nav_class}',
      '{update_nav_label}',
      '{update_nav_badge}',
      'dbx_run1=update',
   ) as $part) {
      dashboard_update_assert(
         str_contains($dashboardTemplate, $part),
         'Update-Navigation fehlt für ' . $language . ': ' . $part
      );
   }
}

$referenceKeys = array_keys($messageSets['de']);
sort($referenceKeys);
foreach ($messageSets as $language => $messages) {
   $keys = array_keys($messages);
   sort($keys);
   dashboard_update_assert(
      $keys === $referenceKeys,
      'Dashboard-FD-Schlüssel weichen für ' . $language . ' ab.'
   );
   foreach (array(
      'update_title',
      'update_status_ready',
      'update_status_available',
      'update_status_current',
      'update_status_unknown',
      'update_action_ready',
      'update_nav_available',
   ) as $key) {
      dashboard_update_assert(
         trim((string)($messages[$key] ?? '')) !== '',
         'Dashboard-Update-Meldung fehlt für ' . $language . ': ' . $key
      );
   }
}

echo "dbxDashboardUpdateStatus: Menü, Status & Health, Cache-only und DE/EN/ES vollständig.\n";
