<?php

declare(strict_types=1);

$shopRoot = dirname(__DIR__);
$projectModules = dirname(__DIR__, 2);
$service = (string)file_get_contents($shopRoot . '/include/dbxShopService.class.php');
$config = (string)file_get_contents($shopRoot . '/cfg/config.php');
$admin = (string)file_get_contents($projectModules . '/dbxShop_admin/include/dbxShopAdmin.class.php');

$fail = static function(string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

if (!str_contains($config, "\$config['demo_notice_enabled'] = true;")) {
   $fail('Der kompatible Standardwert für den Demo-Hinweis fehlt.', 1);
}
if (!str_contains($service, "\$this->settingsBool(\$this->shopConfig(), 'demo_notice_enabled', true)")) {
   $fail('Das Shop-Frontend wertet den Demo-Hinweis-Schalter nicht zentral aus.', 2);
}
if (substr_count($admin, "'demo_notice_enabled'") < 3
   || !str_contains($admin, 'dbxContentPageCache::invalidateAllFullPages()')) {
   $fail('Speichern, Formulardaten oder Cache-Invalidierung der Shop-Einstellung fehlen.', 3);
}

foreach (array('', '_en', '_es') as $suffix) {
   $fd = (string)file_get_contents(
      $projectModules . '/dbxShop_admin/fd/shop-settings' . $suffix . '.fd.php'
   );
   $template = (string)file_get_contents(
      $projectModules . '/dbxShop_admin/tpl/htm/shop-settings-form' . $suffix . '.htm'
   );
   if (!str_contains($fd, "'demo_notice_enabled'")) {
      $fail('Sprach-FD enthält den Demo-Hinweis-Schalter nicht: ' . ($suffix ?: '_de'), 4);
   }
   if (!str_contains($template, '{obj:demo_notice_enabled}')
      || !str_contains($template, 'id="{frame_id}"')
      || str_contains($template, 'id="dbxForm_{i}"')) {
      $fail('Sprach-Template zeigt den Demo-Hinweis-Schalter nicht: ' . ($suffix ?: '_de'), 5);
   }
}

echo "OK shop demo notice configuration\n";
