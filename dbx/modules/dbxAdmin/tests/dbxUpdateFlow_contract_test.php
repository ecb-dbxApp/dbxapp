<?php

declare(strict_types=1);

function update_flow_assert(bool $condition, string $message): void
{
   if (!$condition) {
      throw new RuntimeException($message);
   }
}

$module = dirname(__DIR__);
$controller = (string)file_get_contents(
   $module . '/include/dbxUpdate.class.php'
);
$service = (string)file_get_contents(
   $module . '/include/dbxUpdateService.class.php'
);

foreach (array("case 'start':", "case 'stop':") as $contract) {
   update_flow_assert(
      str_contains($controller, $contract),
      'Controller-Vertrag fehlt: ' . $contract
   );
}
foreach (array(
   'public function prepare(): array',
   'public function cancel(): array',
   'private function synchronized(callable $operation): mixed',
) as $contract) {
   update_flow_assert(
      str_contains($service, $contract),
      'Service-Vertrag fehlt: ' . $contract
   );
}

foreach (array('', '_en', '_es') as $language) {
   $fd = (string)file_get_contents(
      $module . '/fd/admin-update' . $language . '.fd.php'
   );
   foreach (array(
      "\$messages['start_label']",
      "\$messages['prepare_success']",
      "\$messages['stop_label']",
      "\$messages['stop_success']",
      "\$messages['ready_to_install']",
   ) as $message) {
      update_flow_assert(
         str_contains($fd, $message),
         'Sprachmeldung fehlt in admin-update' . $language . '.fd.php: '
            . $message
      );
   }

   $template = (string)file_get_contents(
      $module . '/tpl/htm/admin-update' . $language . '.htm'
   );
   update_flow_assert(
      str_contains($template, 'value="start"')
         && str_contains($template, 'value="install"')
         && str_contains($template, 'value="stop"'),
      'Start-/Install-/Stop-Ablauf fehlt in admin-update'
         . $language . '.htm.'
   );
   update_flow_assert(
      !str_contains($template, 'value="check"')
         && !str_contains($template, 'value="stage"'),
      'Der alte mehrstufige Ablauf ist noch sichtbar in admin-update'
         . $language . '.htm.'
   );
}

echo "dbxUpdateFlow: automatisches Vorbereiten und sicherer Stop vollständig.\n";
