<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;
use dbx\dbxContent\dbxContentHome;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;
use dbx\dbxContent\dbxContentRenderer;
use dbx\dbxContent\dbxContentTranslate;
use dbx\dbxContent\dbxContent_permalink;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
require_once __DIR__ . '/dbxKiValue.class.php';

require_once __DIR__ . '/dbxKiCmsCoreService.trait.php';
require_once __DIR__ . '/dbxKiCmsDescribeService.trait.php';
require_once __DIR__ . '/dbxKiCmsReadService.trait.php';
require_once __DIR__ . '/dbxKiCmsPlanService.trait.php';
require_once __DIR__ . '/dbxKiCmsExecuteService.trait.php';
require_once __DIR__ . '/dbxKiCmsTranslationService.trait.php';
require_once __DIR__ . '/dbxKiCmsDataService.trait.php';
require_once __DIR__ . '/dbxKiCmsImageService.trait.php';
require_once __DIR__ . '/dbxKiCmsCacheService.trait.php';
require_once __DIR__ . '/dbxKiCmsInlineMediaService.trait.php';
require_once __DIR__ . '/dbxKiCmsBundleService.trait.php';

class dbxKiCmsService {

   private const TOKEN_SCOPE = 'dbxKi.cms.execute';
   private const API_VERSION = '0.1';

   use dbxKiCmsCoreServiceTrait;
   use dbxKiCmsDescribeServiceTrait;
   use dbxKiCmsReadServiceTrait;
   use dbxKiCmsPlanServiceTrait;
   use dbxKiCmsExecuteServiceTrait;
   use dbxKiCmsTranslationServiceTrait;
   use dbxKiCmsDataServiceTrait;
   use dbxKiCmsImageServiceTrait;
   use dbxKiCmsCacheServiceTrait;
   use dbxKiCmsInlineMediaServiceTrait;
   use dbxKiCmsBundleServiceTrait;

   private $db;
}
