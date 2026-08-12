<?php
namespace dbx\dbxShop;

require_once __DIR__ . '/dbxShopRepositoryCoreService.trait.php';
require_once __DIR__ . '/dbxShopRepositoryInstallSyncService.trait.php';
require_once __DIR__ . '/dbxShopRepositoryAttributeService.trait.php';
require_once __DIR__ . '/dbxShopRepositoryCatalogService.trait.php';
require_once __DIR__ . '/dbxShopRepositoryProductGroupService.trait.php';
require_once __DIR__ . '/dbxShopRepositoryChannelService.trait.php';
require_once __DIR__ . '/dbxShopRepositoryImageService.trait.php';
require_once __DIR__ . '/dbxShopRepositoryOrderService.trait.php';
require_once __DIR__ . '/dbxShopRepositoryInvoiceService.trait.php';
require_once __DIR__ . '/dbxShopRepositoryWithdrawalService.trait.php';

class dbxShopRepository {
   use dbxShopRepositoryCoreServiceTrait;
   use dbxShopRepositoryInstallSyncServiceTrait;
   use dbxShopRepositoryAttributeServiceTrait;
   use dbxShopRepositoryCatalogServiceTrait;
   use dbxShopRepositoryProductGroupServiceTrait;
   use dbxShopRepositoryChannelServiceTrait;
   use dbxShopRepositoryImageServiceTrait;
   use dbxShopRepositoryOrderServiceTrait;
   use dbxShopRepositoryInvoiceServiceTrait;
   use dbxShopRepositoryWithdrawalServiceTrait;

   /**
    * Request-lokaler Cache fuer kleine, oft wiederverwendete Referenzlisten.
    *
    * Ergaenzt den zentralen requestlokalen dbxDB-select1-Cache um fachliche
    * Referenzlisten, die nicht als identische Einzelsatzzugriffe entstehen.
    */
   private array $requestCache = array();
}
