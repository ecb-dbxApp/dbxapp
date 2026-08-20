<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingPageLookupServiceTrait {



   private function build_style_options(string $selected): string {
      $html = '';
      foreach ($this->writing_styles() as $key => $meta) {
         $sel = ($key === $selected) ? ' selected' : '';
         $html .= '<option value="' . $this->esc($key) . '"' . $sel . '>' . $this->esc($meta['label'] ?? $key) . '</option>';
      }
      return $html;
   }

   private function folder_labels(string $lng): array {
      $snap = $this->cms()->bundle_snapshot(array('lng' => $lng, 'limit' => 500));
      $rows = is_array($snap['folders']['rows'] ?? null) ? $snap['folders']['rows'] : array();
      $by_id = array();
      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $by_id[(int) ($row['id'] ?? 0)] = $row;
      }
      $labels = array();
      foreach ($by_id as $id => $row) {
         $parts = array();
         $cur = (int) $id;
         $guard = 0;
         while ($cur > 0 && isset($by_id[$cur]) && $guard++ < 25) {
            array_unshift($parts, (string) ($by_id[$cur]['name'] ?? ''));
            $cur = (int) ($by_id[$cur]['parent_id'] ?? 0);
         }
         $labels[$id] = implode(' / ', array_filter($parts));
      }
      asort($labels, SORT_NATURAL | SORT_FLAG_CASE);
      return $labels;
   }

   private function folder_label(string $lng, int $folder_id): string {
      $labels = $this->folder_labels($lng);
      return (string) ($labels[$folder_id] ?? ('Ordner #' . $folder_id));
   }

   private function sorter_after_page(string $lng, int $page_id): string {
      $db = dbx()->get_system_obj('dbxDB');
      $dd = dbxContentLng::dd_content($lng);
      $page = $db->select1($dd, $page_id);
      if (!is_array($page)) {
         return '';
      }
      $folder = (int) ($page['folder'] ?? 0);
      $sorter = (int) ($page['sorter'] ?? 0);
      if ($folder <= 0) {
         return '';
      }
      $rows = $db->select($dd, 'folder = ' . $folder . ' AND sorter > ' . $sorter, 'sorter,id', 'sorter,id', 'ASC', '', 1, 0, 0);
      $next_sorter = is_array($rows) && isset($rows[0]) ? (int) ($rows[0]['sorter'] ?? 0) : 0;
      if ($next_sorter > ($sorter + 1)) {
         return sprintf('%04d', $sorter + 1);
      }
      return sprintf('%04d', $sorter);
   }

   private function load_page(string $lng, int $page_id): array {
      $db = dbx()->get_system_obj('dbxDB');
      $row = $db->select1(dbxContentLng::dd_content($lng), $page_id);
      if (!is_array($row)) {
         throw new \InvalidArgumentException('Seite nicht gefunden.');
      }
      return $row;
   }

}
