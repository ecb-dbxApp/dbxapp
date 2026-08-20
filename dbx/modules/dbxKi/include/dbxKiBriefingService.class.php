<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
require_once __DIR__ . '/dbxKiWritingStyles.class.php';
require_once __DIR__ . '/dbxKiContractService.class.php';
require_once __DIR__ . '/dbxKiValue.class.php';

require_once __DIR__ . '/dbxKiBriefingCoreService.trait.php';
require_once __DIR__ . '/dbxKiBriefingTemplateService.trait.php';
require_once __DIR__ . '/dbxKiBriefingBootstrapComponentService.trait.php';
require_once __DIR__ . '/dbxKiBriefingHeroImageService.trait.php';
require_once __DIR__ . '/dbxKiBriefingHubRenderService.trait.php';
require_once __DIR__ . '/dbxKiBriefingPageFormService.trait.php';
require_once __DIR__ . '/dbxKiBriefingStylesAdminService.trait.php';
require_once __DIR__ . '/dbxKiBriefingExportService.trait.php';
require_once __DIR__ . '/dbxKiBriefingPromptBuildingService.trait.php';
require_once __DIR__ . '/dbxKiBriefingLanguageSelectionService.trait.php';
require_once __DIR__ . '/dbxKiBriefingPageLookupService.trait.php';

class dbxKiBriefingService {

   private const BRIEFING_VERSION = '2.0';
   private const HERO_MAX_WIDTH = 1280;
   private const HERO_MAX_HEIGHT = 400;
   private const HERO_OPTIMAL_WIDTH = 1280;
   private const HERO_OPTIMAL_HEIGHT = 300;
   private const HERO_DEFAULT_HEIGHT = '300px';
   private const HERO_DEFAULT_IMAGE_WIDTH = 1280;
   private const HERO_DEFAULT_IMAGE_HEIGHT = 300;
   private const HERO_TEXT_MAX_LINES = 3;
   private const CONTENT_TEMPLATE_DEFAULT = 'c-title-hero_header-body1-footer';

   use dbxKiBriefingCoreServiceTrait;
   use dbxKiBriefingTemplateServiceTrait;
   use dbxKiBriefingBootstrapComponentServiceTrait;
   use dbxKiBriefingHeroImageServiceTrait;
   use dbxKiBriefingHubRenderServiceTrait;
   use dbxKiBriefingPageFormServiceTrait;
   use dbxKiBriefingStylesAdminServiceTrait;
   use dbxKiBriefingExportServiceTrait;
   use dbxKiBriefingPromptBuildingServiceTrait;
   use dbxKiBriefingLanguageSelectionServiceTrait;
   use dbxKiBriefingPageLookupServiceTrait;
}
