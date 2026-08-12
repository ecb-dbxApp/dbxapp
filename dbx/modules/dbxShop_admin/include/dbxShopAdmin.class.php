<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

require_once __DIR__ . '/dbxShopAdminHelpContentService.trait.php';
require_once __DIR__ . '/dbxShopAdminMediaUsageService.trait.php';
require_once __DIR__ . '/dbxShopAdminProductReportService.trait.php';
require_once __DIR__ . '/dbxShopAdminProductFormService.trait.php';
require_once __DIR__ . '/dbxShopAdminProductMappingService.trait.php';
require_once __DIR__ . '/dbxShopAdminProductActionService.trait.php';
require_once __DIR__ . '/dbxShopAdminDashboardService.trait.php';
require_once __DIR__ . '/dbxShopAdminOrderService.trait.php';
require_once __DIR__ . '/dbxShopAdminCatalogService.trait.php';
require_once __DIR__ . '/dbxShopAdminMediaService.trait.php';
require_once __DIR__ . '/dbxShopAdminChannelService.trait.php';
require_once __DIR__ . '/dbxShopAdminContentService.trait.php';

class dbxShopAdmin {

   use dbxShopAdminHelpContentServiceTrait;
   use dbxShopAdminMediaUsageServiceTrait;
   use dbxShopAdminProductReportServiceTrait;
   use dbxShopAdminProductFormServiceTrait;
   use dbxShopAdminProductMappingServiceTrait;
   use dbxShopAdminProductActionServiceTrait;
   use dbxShopAdminDashboardServiceTrait;
   use dbxShopAdminOrderServiceTrait;
   use dbxShopAdminCatalogServiceTrait;
   use dbxShopAdminMediaServiceTrait;
   use dbxShopAdminChannelServiceTrait;
   use dbxShopAdminContentServiceTrait;

   private const ACTION_TOKEN_SCOPE = 'dbxShop_admin.actions';

   /**
    * Erlaubt Provisionierung und Reparatur nur waehrend des expliziten,
    * tokenisierten Wartungslaufs.
    */
   private bool $maintenanceMode = false;

   /**
    * Sichtbare Rückmeldung einer vor dem Seitenaufbau abgewiesenen Kartenaktion.
    *
    * Die Kartenformulare werden erst nach der Aktionsverarbeitung aufgebaut.
    * Deshalb wird die Meldung kurz zwischengespeichert und von frame() genau
    * einmal oberhalb des aktuellen Verwaltungsbereichs ausgegeben.
    */
   private string $postedFormError = '';
   private $catalogTexts = null;

   /**
    * Modulweite, sprachabhängige Texte für die Katalog-Nebenformulare.
    *
    * Das eigene dbxForm-Objekt hält den FD-Kontext stabil, während auf einer
    * Seite mehrere Kartenformulare mit unterschiedlichen Datendictionaries
    * gerendert werden.
    */
   private function catalogTexts() {
      if ($this->catalogTexts) {
         return $this->catalogTexts;
      }
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('shop-catalog-texts');
      $texts->_fd = 'dbxShop_admin|shop-catalog';
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->catalogTexts = $texts;
      return $this->catalogTexts;
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   private function h($value): string {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   /**
    * Ergaenzt den vorhandenen sessiongebundenen dbx-Aktionstoken.
    *
    * GET-Links bleiben kompatibel. Nur Links und Endpunkte, die Daten
    * veraendern, werden tokenisiert.
    */
   private function actionUrl(string $url): string {
      $securedUrl = dbx()->action_url($url);
      if ($securedUrl !== $url) {
         return $securedUrl;
      }

      $separator = strpos($url, '?') === false ? '?' : '&';
      return $url . $separator . 'dbx_token=' . rawurlencode(dbx()->action_token(self::ACTION_TOKEN_SCOPE));
   }

   /**
    * Prueft den gemeinsamen Token fuer schreibende Shop-Admin-Aktionen.
    */
   private function checkActionToken(string $action): bool {
      $token = (string)dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
      if (dbx()->check_action_token(self::ACTION_TOKEN_SCOPE, $token)) {
         return true;
      }

      $this->postedFormError = $this->catalogTexts()->get_fd_message('security_token_error');
      dbx()->sys_msg(
         'security',
         'dbxShop_admin',
         $action,
         'Shop-Admin-Aktion ohne gueltigen Token abgewiesen',
         'ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
      );
      return false;
   }

   private function openWinButton(string $url, string $title, string $content, string $class = 'btn btn-outline-primary', string $width = '88%', string $height = '88%'): string {
      $escUrl = $this->h($url);
      $escTitle = $this->h($title);
      $class = trim($class);
      if (strpos(' ' . $class . ' ', ' openWin ') === false) {
         $class .= ' openWin';
      }
      if (strpos(' ' . $class . ' ', ' dbx-win ') === false) {
         $class .= ' dbx-win';
      }
      if (strpos($content, 'bi-question-circle') !== false && strpos(' ' . $class . ' ', ' dbx-help-action ') === false) {
         $class .= ' dbx-help-action';
      }
      return '<a class="' . $this->h($class) . '" href="' . $escUrl . '" data-url="' . $escUrl . '" data-title="' . $escTitle . '" data-width="' . $this->h($width) . '" data-height="' . $this->h($height) . '" title="' . $escTitle . '" role="button">' . $content . '</a>';
   }

   private function helpButton(int $helpId, string $title, string $class = 'btn btn-outline-secondary btn-sm me-1', string $width = '72%', string $height = '82%'): string {
      if ($helpId <= 0) {
         return '';
      }
      return $this->openWinButton(
         '?dbx_modul=dbxContent&dbx_run1=content&cid=' . $helpId,
         $title,
         '<i class="bi bi-question-circle"></i><span class="visually-hidden"> Hilfe</span>',
         $class,
         $width,
         $height
      );
   }

   private function money($value): string {
      return number_format((float) $value, 2, ',', '.') . ' EUR';
   }

   private function mediaUrl(string $path): string {
      $path = trim(str_replace('\\', '/', $path));
      if ($path === '') return '';
      if (preg_match('~^https?://~i', $path) || substr($path, 0, 1) === '/') return $path;
      return dbx()->get_base_url() . ltrim($path, '/');
   }

   private function mediaItemUrl(array $image, bool $thumb = true): string {
      $mediaId = (int)($image['media_id'] ?? 0);
      if ($mediaId > 0) {
         $url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $mediaId;
         if ($thumb) {
            $url .= '&dbx_thumb=1';
         }
         return $url;
      }
      return $this->mediaUrl((string)($image['image_path'] ?? ''));
   }

   private function repo() {
      return dbx()->get_include_obj('dbxShopRepository', 'dbxShop');
   }

   private function ensureSeed(): void {
      // Normale Admin-Aufrufe duerfen weder Schema noch Demo-Daten aendern.
      // Installation und Wartung erfolgen ausschliesslich ueber run1=install.
      $this->repo()->install();
   }

   public function run(): string {
      $run = dbx()->get_modul_var('dbx_run1', 'dashboard', 'parameter');
      $run = $run === '' ? 'dashboard' : (string)$run;

      $manifest = dbx()->get_system_obj('dbxActionManifest');
      $definition = $manifest->action('dbxShop_admin', $run);
      if (!is_array($definition)) {
         return $this->placeholder('Unbekannter Shop-Aufruf', 'dbx_run1=' . $run . ' ist nicht definiert.');
      }
      $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
      if (!in_array($method, (array)$definition['methods'], true)) {
         if (!headers_sent()) http_response_code(405);
         return $this->placeholder('Methode nicht erlaubt', $method . ' ist für dbx_run1=' . $run . ' nicht erlaubt.');
      }
      $handler = (string)$definition['handler'];
      if (!method_exists($this, $handler)) {
         throw new \LogicException('Shop-Admin-Handler fehlt: ' . $run);
      }
      return $this->{$handler}();
   }
}
?>
