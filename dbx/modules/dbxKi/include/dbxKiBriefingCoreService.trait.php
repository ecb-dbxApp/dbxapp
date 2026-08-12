<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingCoreServiceTrait {

   private function contracts(): dbxKiContractService {
      return dbx()->get_include_obj('dbxKiContractService', 'dbxKi');
   }

   private function ensureContentBootstrap(): void {
      if (!class_exists(dbxContentLng::class)) {
         require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
      }
   }

   private function cms(): dbxKiCmsService {
      return dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');
   }

   private function help(): dbxKiHelp {
      return dbx()->get_include_obj('dbxKiHelp', 'dbxKi');
   }

   private function bundle(): dbxKiBundleService {
      return dbx()->get_include_obj('dbxKiBundleService', 'dbxKi');
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function esc($value): string {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   private function moduleUrl(string $run1, array $params = array()): string {
      $url = '?dbx_modul=dbxKi&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         if ($value === null || $value === '') {
            continue;
         }
         $url .= '&' . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
      }
      return $url;
   }

   private function contentAdminUrl(string $run1, array $params = array()): string {
      $url = '?dbx_modul=dbxContent_admin&dbx_run1=' . rawurlencode($run1);
      foreach ($params as $key => $value) {
         if ($value === null || $value === '') {
            continue;
         }
         $url .= '&' . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
      }
      return $url;
   }

   private function withContentLng(string $lng, callable $fn) {
      $lng = strtolower(trim($lng));
      $prev = (string) dbx()->get_system_var('dbx_lng', '');
      if ($lng !== '') {
         dbx()->set_system_var('dbx_lng', $lng);
      }
      try {
         return $fn();
      } finally {
         dbx()->set_system_var('dbx_lng', $prev);
      }
   }

   private function writingStyles(): array {
      return dbxKiWritingStyles::all();
   }

   private function truncate(string $text, int $max): string {
      $text = trim(preg_replace('/\s+/u', ' ', $text));
      if (mb_strlen($text) <= $max) {
         return $text;
      }
      return mb_substr($text, 0, $max) . '…';
   }
}
