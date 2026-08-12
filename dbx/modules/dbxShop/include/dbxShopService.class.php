<?php
namespace dbx\dbxShop;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

require_once __DIR__ . '/dbxShopServiceCoreService.trait.php';
require_once __DIR__ . '/dbxShopServiceContentPageService.trait.php';
require_once __DIR__ . '/dbxShopServiceProductDisplayService.trait.php';
require_once __DIR__ . '/dbxShopServiceCatalogService.trait.php';
require_once __DIR__ . '/dbxShopServiceCartService.trait.php';
require_once __DIR__ . '/dbxShopServiceCheckoutService.trait.php';
require_once __DIR__ . '/dbxShopServiceMailService.trait.php';
require_once __DIR__ . '/dbxShopServiceOrderPageService.trait.php';
require_once __DIR__ . '/dbxShopServiceRouteService.trait.php';

class dbxShopService {

   use dbxShopServiceCoreServiceTrait;
   use dbxShopServiceContentPageServiceTrait;
   use dbxShopServiceProductDisplayServiceTrait;
   use dbxShopServiceCatalogServiceTrait;
   use dbxShopServiceCartServiceTrait;
   use dbxShopServiceCheckoutServiceTrait;
   use dbxShopServiceMailServiceTrait;
   use dbxShopServiceOrderPageServiceTrait;
   use dbxShopServiceRouteServiceTrait;

   private array $textForms = array();
}
