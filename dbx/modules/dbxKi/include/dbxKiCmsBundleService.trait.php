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

   public function bundleActionCatalog(): array {
      return $this->catalog();
   }

   public function bundleIsAllowedInPackage(string $action): bool {
      $catalog = $this->catalog();
      if (!isset($catalog[$action]) || !($catalog[$action]['write'] ?? false)) {
         return false;
      }
      if (!empty($catalog[$action]['destructive'])) {
         return false;
      }
      return true;
   }

   public function bundleBuildPlan(string $action, array $params): array {
      if (!$this->bundleIsAllowedInPackage($action)) {
         throw new \InvalidArgumentException('Aktion im Bundle nicht erlaubt: ' . $action);
      }
      return $this->build_plan($action, $params);
   }

   public function bundleExecutePlan(string $action, array $params, array $plan): array {
      if (!$this->bundleIsAllowedInPackage($action)) {
         throw new \InvalidArgumentException('Aktion im Bundle nicht erlaubt: ' . $action);
      }
      return $this->execute_action($action, $params, $plan);
   }

   public function bundleExecuteToken(): string {
      return dbx()->action_token(self::TOKEN_SCOPE);
   }

   public function bundleCheckExecuteToken(string $token): bool {
      return dbx()->check_action_token(self::TOKEN_SCOPE, $token);
   }

   public function bundleSnapshot(array $params = array()): array {
      return $this->snapshot($params);
   }

   public function bundleRead(string $action, array $params = array()): array {
      if (!in_array($action, array('folder.get', 'page.get'), true)) {
         throw new \InvalidArgumentException('Leseaktion fuer Bundle-Snapshot nicht erlaubt.');
      }
      $result = $this->read_action($action, $params);
      return is_array($result) ? $result : array();
   }

   public function bundleSystemDescribe(): array {
      return $this->describe();
   }
}
