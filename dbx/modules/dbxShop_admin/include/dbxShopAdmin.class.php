<?php
namespace dbx\dbxShop_admin;

require_once dirname(__DIR__, 2) . '/dbxShop/include/dbxShopMediaUrl.class.php';
require_once dirname(__DIR__, 2) . '/dbxShop/include/dbxShopValue.class.php';

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
   private bool $maintenance_mode = false;

   /**
    * Sichtbare Rückmeldung einer vor dem Seitenaufbau abgewiesenen Kartenaktion.
    *
    * Die Kartenformulare werden erst nach der Aktionsverarbeitung aufgebaut.
    * Deshalb wird die Meldung kurz zwischengespeichert und von frame() genau
    * einmal oberhalb des aktuellen Verwaltungsbereichs ausgegeben.
    */
   private string $posted_form_error = '';
   private $catalog_texts = null;

   /**
    * Modulweite, sprachabhängige Texte für die Katalog-Nebenformulare.
    *
    * Das eigene dbxForm-Objekt hält den FD-Kontext stabil, während auf einer
    * Seite mehrere Kartenformulare mit unterschiedlichen Datendictionaries
    * gerendert werden.
    */
   private function catalog_texts() {
      if ($this->catalog_texts) {
         return $this->catalog_texts;
      }
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('shop-catalog-texts');
      $texts->set_field_definition('dbxShop_admin|shop-catalog');
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->catalog_texts = $texts;
      return $this->catalog_texts;
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
    * Ergänzt den vorhandenen sessiongebundenen dbx-Aktionstoken.
    *
    * GET-Links bleiben kompatibel. Nur Links und Endpunkte, die Daten
    * veraendern, werden tokenisiert.
    */
   private function action_url(string $url): string {
      $secured_url = dbx()->action_url($url);
      if ($secured_url !== $url) {
         return $secured_url;
      }

      $separator = strpos($url, '?') === false ? '?' : '&';
      return $url . $separator . 'dbx_token=' . rawurlencode(dbx()->action_token(self::ACTION_TOKEN_SCOPE));
   }

   /**
    * Prüft den gemeinsamen Token fuer schreibende Shop-Admin-Aktionen.
    */
   private function check_action_token(string $action): bool {
      $token = (string)dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
      if (dbx()->check_action_token(self::ACTION_TOKEN_SCOPE, $token)) {
         return true;
      }

      $this->posted_form_error = $this->catalog_texts()->get_fd_message('security_token_error');
      dbx()->sys_msg(
         'security',
         'dbxShop_admin',
         $action,
         'Shop-Admin-Aktion ohne gueltigen Token abgewiesen',
         'ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
      );
      return false;
   }

   private function open_win_button(string $url, string $title, string $content, string $class = 'btn btn-outline-primary', string $width = '88%', string $height = '88%'): string {
      $esc_url = $this->h($url);
      $esc_title = $this->h($title);
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
      return '<a class="' . $this->h($class) . '" href="' . $esc_url . '" data-url="' . $esc_url . '" data-title="' . $esc_title . '" data-width="' . $this->h($width) . '" data-height="' . $this->h($height) . '" title="' . $esc_title . '" role="button">' . $content . '</a>';
   }

   private function help_button(string $context, string $title, string $class = 'btn btn-outline-secondary btn-sm me-1', string $width = '72%', string $height = '82%'): string {
      if ($context === '') {
         return '';
      }
      $parts = explode('--', $context, 2);
      $help = dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
      $url = is_object($help)
         ? $help->url('dbxShop_admin', $parts[0], $parts[1] ?? '', $title)
         : '';
      if ($url === '') return '';
      return $this->open_win_button(
         $url,
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



   private function repo() {
      return dbx()->get_include_obj('dbxShopRepository', 'dbxShop');
   }

   private function ensure_seed(): void {
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
