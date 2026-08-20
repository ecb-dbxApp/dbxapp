<?php
namespace dbx\dbxContent_admin;

use dbx\dbxContent\dbxContentHome;
use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;
use dbx\dbxContent\dbxContentRenderer;
use dbx\dbxContent\dbxContentTranslate;
use dbx\dbxContent\dbxContent_permalink;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
require_once __DIR__ . '/dbxContentMediaUsageMaintenance.class.php';
require_once __DIR__ . '/dbxContentCmsOptionCatalog.class.php';
require_once __DIR__ . '/dbxContentTreeOrder.class.php';
require_once __DIR__ . '/dbxContentCmsPersistenceService.class.php';
require_once __DIR__ . '/dbxContentAdminSessionState.class.php';

require_once __DIR__ . '/dbxContentCmsCoreService.trait.php';
require_once __DIR__ . '/dbxContentCmsTreeService.trait.php';
require_once __DIR__ . '/dbxContentCmsMediaFileService.trait.php';
require_once __DIR__ . '/dbxContentCmsMediaUsageService.trait.php';
require_once __DIR__ . '/dbxContentCmsMediaProcessService.trait.php';
require_once __DIR__ . '/dbxContentCmsMediaImageService.trait.php';
require_once __DIR__ . '/dbxContentCmsPageShellService.trait.php';
require_once __DIR__ . '/dbxContentCmsPageActionService.trait.php';
require_once __DIR__ . '/dbxContentCmsMediaBrowserService.trait.php';
require_once __DIR__ . '/dbxContentCmsMediaAssignmentService.trait.php';
require_once __DIR__ . '/dbxContentCmsMediaFolderService.trait.php';
require_once __DIR__ . '/dbxContentCmsFormService.trait.php';

class dbxContent_cms extends \dbxObj {

   use dbxContentCmsCoreServiceTrait;
   use dbxContentCmsTreeServiceTrait;
   use dbxContentCmsMediaFileServiceTrait;
   use dbxContentCmsMediaUsageServiceTrait;
   use dbxContentCmsMediaProcessServiceTrait;
   use dbxContentCmsMediaImageServiceTrait;
   use dbxContentCmsPageShellServiceTrait;
   use dbxContentCmsPageActionServiceTrait;
   use dbxContentCmsMediaBrowserServiceTrait;
   use dbxContentCmsMediaAssignmentServiceTrait;
   use dbxContentCmsMediaFolderServiceTrait;
   use dbxContentCmsFormServiceTrait;

   private const ACTION_TOKEN_SCOPE = 'dbxContent_admin.actions';
   /** CMS-Aktionsverträge liegen zentral in cfg/cms-actions.php. */

   private $dd_media   = 'dbxMedia';
   private $dd_media_usage = 'dbxMediaUsage';
   private $cms_form_security = array();
   private $cms_texts = null;
   private ?dbxContentCmsOptionCatalog $cms_option_catalog = null;
   private ?dbxContentCmsPersistenceService $persistence_service = null;

   public function run($action = 'cms') {
      $this->apply_cms_lng_context();
      $action = (string)$action;
      if ($this->action_requires_token($action) && !$this->check_action_token($action)) {
         return $this->reject_action_token($action);
      }
      if ($action === 'cms') {
         return $this->render_cms();
      }

      $definition = $this->cms_actions()[$action] ?? null;
      if (!is_array($definition)) {
         return $this->render_cms();
      }

      // Demo-Benutzer dürfen jeden lesenden CMS-Endpunkt erkunden. Schreibende
      // Endpunkte enden dagegen vor der ersten DB-Operation kontrolliert hier.
      if (!empty($definition['mutation']) && dbx()->is_demo_mode()) {
         return $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'demo_readonly' => true,
            'msg' => 'Demo-Modus: Änderungen sind gesperrt.',
         ));
      }

      $handler = (string)($definition['handler'] ?? '');
      if ($handler === '' || !method_exists($this, $handler)) {
         throw new \LogicException('CMS-Handler fehlt: ' . $action);
      }
      return $this->{$handler}();
   }
}

?>
