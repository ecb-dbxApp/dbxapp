<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);

function module_mail_sender_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$config = array();
require $root . '/dbx/modules/dbxContact/cfg/config.php';
$contactConfig = $config;

$config = array();
require $root . '/dbx/modules/dbxShop/cfg/config.php';
$shopConfig = $config;

$contactForm = (string)file_get_contents(
    $root . '/dbx/modules/dbxContact/include/dbxContactForm.class.php'
);
$contactAdmin = (string)file_get_contents(
    $root . '/dbx/modules/dbxContact_admin/include/dbxContactAdmin.class.php'
);
$shopService = (string)file_get_contents(
    $root . '/dbx/modules/dbxShop/include/dbxShopService.class.php'
);
$shopAdmin = (string)file_get_contents(
    $root . '/dbx/modules/dbxShop_admin/include/dbxShopAdmin.class.php'
);
$installer = (string)file_get_contents(
    $root . '/dbx/modules/dbxSetup/include/dbxInstall.class.php'
);
require_once $root . '/dbx/modules/dbxSetup/include/dbxInstall.class.php';

module_mail_sender_assert(
    ($contactConfig['mail_from'] ?? '') === ''
        && ($contactConfig['mail_profile'] ?? '') === 'dbxApp',
    'Das Kontaktmodul muss den installationsbezogenen Absender in der lokalen Konfiguration erwarten.'
);
module_mail_sender_assert(
    substr_count($contactForm, "get_config('dbxContact', 'mail_from')") >= 1
        && str_contains($contactForm, '$this->mail_from_param()')
        && str_contains($contactForm, 'FILTER_VALIDATE_EMAIL')
        && str_contains($contactForm, "'reply_to'")
        && str_contains($contactAdmin, "get_config('dbxContact', 'mail_from')")
        && str_contains($contactAdmin, 'FILTER_VALIDATE_EMAIL')
        && str_contains($contactAdmin, '$fromParam'),
    'Kontaktbenachrichtigung, Bestätigung oder Supportantwort umgehen mail_from.'
);

module_mail_sender_assert(
    ($shopConfig['mail_from'] ?? '') === 'shop@dbxapp.de'
        && ($shopConfig['mail_profile'] ?? '') === 'dbxApp'
        && ($shopConfig['mail_from_name'] ?? '') === 'dbxShop',
    'Das Shopmodul besitzt nicht den erwarteten konfigurierbaren Standardabsender.'
);
module_mail_sender_assert(
    !str_contains($shopService, "\$from = 'shop@dbxapp.de'")
        && str_contains($shopService, "\$cfg['mail_from']")
        && str_contains($shopService, 'shopMailOptions')
        && str_contains($shopService, "\$extra['mail_profile'] = \$profile")
        && substr_count($shopService, '$mailOptions') >= 5,
    'Bestell- oder Widerrufsmails nutzen nicht durchgehend die Shop-Konfiguration.'
);
module_mail_sender_assert(
    str_contains($shopAdmin, "patch_local_config('dbxShop', \$mailLocal)")
        && str_contains($shopAdmin, "\$cfg['mail_from_name']")
        && str_contains($shopAdmin, "'mail_profile' => \$profile")
        && str_contains($shopAdmin, 'if (!$sent)'),
    'Shop-Einstellungen oder Statusmails verwenden die lokale Absenderkonfiguration nicht vollständig.'
);

module_mail_sender_assert(
    str_contains($installer, "patch_local_config('dbxContact'")
        && str_contains($installer, "patch_local_config('dbxShop'")
        && str_contains($installer, "moduleSenderAddress('kontakt'")
        && str_contains($installer, "moduleSenderAddress('shop'")
        && str_contains($installer, 'Kontaktanfragen')
        && str_contains($installer, 'Shop-Nachrichten'),
    'Der Installer konfiguriert die getrennten Modulabsender nicht vollständig.'
);

$installerClass = new ReflectionClass('dbx\\dbxSetup\\dbxInstall');
$installerObject = $installerClass->newInstanceWithoutConstructor();
$moduleSender = $installerClass->getMethod('moduleSenderAddress');
module_mail_sender_assert(
    $moduleSender->invoke($installerObject, 'kontakt', 'admin@kunde.example') === 'kontakt@kunde.example'
        && $moduleSender->invoke($installerObject, 'shop', 'system@kunde.example') === 'shop@kunde.example'
        && $moduleSender->invoke($installerObject, 'shop', '') === 'shop@dbxapp.de',
    'Die Modulabsender werden nicht korrekt aus der globalen Absenderdomain abgeleitet.'
);

echo "OK configurable contact and shop mail senders\n";
