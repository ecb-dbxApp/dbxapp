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

trait dbxKiCmsBundleServiceTrait {

   public function bundle_action_catalog(): array {
      return $this->catalog();
   }

   public function bundle_is_allowed_in_package(string $action): bool {
      $catalog = $this->catalog();
      if (!isset($catalog[$action]) || !($catalog[$action]['write'] ?? false)) {
         return false;
      }
      if (!empty($catalog[$action]['destructive'])) {
         return false;
      }
      return true;
   }

   public function bundle_build_plan(string $action, array $params): array {
      if (!$this->bundle_is_allowed_in_package($action)) {
         throw new \InvalidArgumentException('Aktion im Bundle nicht erlaubt: ' . $action);
      }
      return $this->build_plan($action, $params);
   }

   public function bundle_execute_plan(string $action, array $params, array $plan): array {
      if (!$this->bundle_is_allowed_in_package($action)) {
         throw new \InvalidArgumentException('Aktion im Bundle nicht erlaubt: ' . $action);
      }
      return $this->execute_action($action, $params, $plan);
   }

   public function bundle_execute_token(): string {
      return dbx()->action_token(self::TOKEN_SCOPE);
   }

   public function bundle_check_execute_token(string $token): bool {
      return dbx()->check_action_token(self::TOKEN_SCOPE, $token);
   }

   public function bundle_snapshot(array $params = array()): array {
      return $this->snapshot($params);
   }

   public function bundle_read(string $action, array $params = array()): array {
      if (!in_array($action, array('folder.get', 'page.get'), true)) {
         throw new \InvalidArgumentException('Leseaktion fuer Bundle-Snapshot nicht erlaubt.');
      }
      $result = $this->read_action($action, $params);
      return is_array($result) ? $result : array();
   }

   public function bundle_system_describe(): array {
      return $this->describe();
   }
}
