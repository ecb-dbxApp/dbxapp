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
require_once dirname(__DIR__, 3) . '/include/tests/dbxModuleSourceBundle.php';
$dashboard = dbx_test_module_source_bundle(
   $module . '/include/dbxDashboard.class.php'
);
$controller = (string)file_get_contents(
   $module . '/include/dbxUpdate.class.php'
);
$manager = (string)file_get_contents(
   dirname(__DIR__, 3) . '/include/dbxPackageManager.class.php'
);
$hero = (string)file_get_contents(
   $module . '/tpl/htm/admin-dashboard-hero.htm'
);
$update_card = (string)file_get_contents(
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
   'public function local_status(): array',
   'Netzwerkfreier Kurzstatus',
) as $part) {
   dashboard_update_assert(
      str_contains($manager, $part),
      'Zentraler Paketmanager-Vertrag fehlt: ' . $part
   );
}

dashboard_update_assert(
   str_contains($controller, 'dbxPackageManager::configured()'),
   'Update-Controller verwendet nicht die zentrale Konfiguration.'
);
dashboard_update_assert(
   str_contains($dashboard, 'dbxPackageManager::configured()->local_status()'),
   'Dashboard liest nicht den zentralen lokalen Update-Status.'
);

$status_method_start = strpos($dashboard, 'private function update_status(): array');
$state_method_start = strpos($dashboard, 'private function update_state(array $status): array');
dashboard_update_assert(
   $status_method_start !== false && $state_method_start !== false,
   'Dashboard-Statusmethoden fehlen.'
);
$status_method = substr(
   $dashboard,
   $status_method_start,
   $state_method_start - $status_method_start
);
dashboard_update_assert(
   !str_contains($status_method, '->check(')
      && !str_contains($status_method, '->prepare(')
      && !str_contains($status_method, 'file_get_contents('),
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
      str_contains($update_card, $part),
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

$load_messages = static function (string $file): array {
   $messages = array();
   $fields = array();
   include $file;
   return $messages;
};

$message_sets = array();
foreach (array('de' => '', 'en' => '_en', 'es' => '_es') as $language => $suffix) {
   $message_sets[$language] = $load_messages(
      $module . '/fd/admin-dashboard-status' . $suffix . '.fd.php'
   );
   $dashboard_template = (string)file_get_contents(
      $module . '/tpl/htm/admin-dashboard' . $suffix . '.htm'
   );
   foreach (array(
      '{update_nav_class}',
      '{update_nav_label}',
      '{update_nav_badge}',
      'dbx_run1=update',
   ) as $part) {
      dashboard_update_assert(
         str_contains($dashboard_template, $part),
         'Update-Navigation fehlt für ' . $language . ': ' . $part
      );
   }
}

$reference_keys = array_keys($message_sets['de']);
sort($reference_keys);
foreach ($message_sets as $language => $messages) {
   $keys = array_keys($messages);
   sort($keys);
   dashboard_update_assert(
      $keys === $reference_keys,
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
