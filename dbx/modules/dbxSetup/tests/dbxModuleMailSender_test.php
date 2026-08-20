<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
require_once $root . '/dbx/include/tests/dbxModuleSourceBundle.php';

function module_mail_sender_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$config = array();
require $root . '/dbx/modules/dbxContact/cfg/config.php';
$contact_config = $config;

$config = array();
require $root . '/dbx/modules/dbxShop/cfg/config.php';
$shop_config = $config;

$contact_form = (string)file_get_contents(
    $root . '/dbx/modules/dbxContact/include/dbxContactForm.class.php'
);
$contact_admin = (string)file_get_contents(
    $root . '/dbx/modules/dbxContact_admin/include/dbxContactAdmin.class.php'
);
$shop_service = dbx_test_module_source_bundle(
    $root . '/dbx/modules/dbxShop/include/dbxShopService.class.php'
);
$shop_admin = dbx_test_module_source_bundle(
    $root . '/dbx/modules/dbxShop_admin/include/dbxShopAdmin.class.php'
);
$installer = (string)file_get_contents(
    $root . '/dbx/modules/dbxSetup/include/dbxInstall.class.php'
);
require_once $root . '/dbx/modules/dbxSetup/include/dbxInstall.class.php';

module_mail_sender_assert(
    ($contact_config['mail_from'] ?? '') === ''
        && ($contact_config['mail_profile'] ?? '') === 'dbxApp',
    'Das Kontaktmodul muss den installationsbezogenen Absender in der lokalen Konfiguration erwarten.'
);
module_mail_sender_assert(
    substr_count($contact_form, "get_cfg('dbxContact', 'mail_from')") >= 1
        && str_contains($contact_form, '$this->mail_from_param()')
        && str_contains($contact_form, 'FILTER_VALIDATE_EMAIL')
        && str_contains($contact_form, "'reply_to'")
        && str_contains($contact_admin, "get_cfg('dbxContact', 'mail_from')")
        && str_contains($contact_admin, 'FILTER_VALIDATE_EMAIL')
        && str_contains($contact_admin, '$from_param'),
    'Kontaktbenachrichtigung, Bestätigung oder Supportantwort umgehen mail_from.'
);

module_mail_sender_assert(
    ($shop_config['mail_from'] ?? '') === 'shop@dbxapp.de'
        && ($shop_config['mail_profile'] ?? '') === 'dbxApp'
        && ($shop_config['mail_from_name'] ?? '') === 'dbxShop',
    'Das Shopmodul besitzt nicht den erwarteten konfigurierbaren Standardabsender.'
);
module_mail_sender_assert(
    !str_contains($shop_service, "\$from = 'shop@dbxapp.de'")
        && str_contains($shop_service, "\$cfg['mail_from']")
        && str_contains($shop_service, 'shop_mail_options')
        && str_contains($shop_service, "\$extra['mail_profile'] = \$profile")
        && substr_count($shop_service, '$mail_options') >= 5,
    'Bestell- oder Widerrufsmails nutzen nicht durchgehend die Shop-Konfiguration.'
);
module_mail_sender_assert(
    str_contains($shop_admin, "patch_local_config('dbxShop', \$mail_local)")
        && str_contains($shop_admin, "\$cfg['mail_from_name']")
        && str_contains($shop_admin, "'mail_profile' => \$profile")
        && str_contains($shop_admin, 'if (!$sent)'),
    'Shop-Einstellungen oder Statusmails verwenden die lokale Absenderkonfiguration nicht vollständig.'
);

module_mail_sender_assert(
    str_contains($installer, "patch_local_config('dbxContact'")
        && str_contains($installer, "patch_local_config('dbxShop'")
        && str_contains($installer, "module_sender_address('kontakt'")
        && str_contains($installer, "module_sender_address('shop'")
        && str_contains($installer, 'Kontaktanfragen')
        && str_contains($installer, 'Shop-Nachrichten'),
    'Der Installer konfiguriert die getrennten Modulabsender nicht vollständig.'
);

$installer_class = new ReflectionClass('dbx\\dbxSetup\\dbxInstall');
$installer_object = $installer_class->newInstanceWithoutConstructor();
$module_sender = $installer_class->getMethod('module_sender_address');
module_mail_sender_assert(
    $module_sender->invoke($installer_object, 'kontakt', 'admin@kunde.example') === 'kontakt@kunde.example'
        && $module_sender->invoke($installer_object, 'shop', 'system@kunde.example') === 'shop@kunde.example'
        && $module_sender->invoke($installer_object, 'shop', '') === 'shop@dbxapp.de',
    'Die Modulabsender werden nicht korrekt aus der globalen Absenderdomain abgeleitet.'
);

echo "OK configurable contact and shop mail senders\n";
