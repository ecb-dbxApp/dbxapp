<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingPageLookupServiceTrait {

   private function buildFolderOptions(string $lng, int $selected): string {
      $labels = $this->folderLabels($lng);
      $html = '<option value="">— Ordner waehlen —</option>';
      foreach ($labels as $id => $label) {
         $sel = ((int) $id === $selected) ? ' selected' : '';
         $html .= '<option value="' . (int) $id . '"' . $sel . '>' . $this->esc($label) . ' (#' . (int) $id . ')</option>';
      }
      return $html;
   }

   private function buildPageOptions(string $lng, int $selected): string {
      $snap = $this->cms()->bundleSnapshot(array('lng' => $lng, 'limit' => 300));
      $folders = $this->folderLabels($lng);
      $rows = is_array($snap['pages']['rows'] ?? null) ? $snap['pages']['rows'] : array();
      $html = '<option value="">— Seite waehlen —</option>';
      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $id = (int) ($row['id'] ?? 0);
         if ($id <= 0) {
            continue;
         }
         $fid = (int) ($row['folder'] ?? 0);
         $folder = $folders[$fid] ?? ('Ordner #' . $fid);
         $title = trim((string) ($row['title'] ?? ''));
         $sel = ($id === $selected) ? ' selected' : '';
         $html .= '<option value="' . $id . '"' . $sel . '>' . $this->esc($title) . ' — ' . $this->esc($folder) . ' (#' . $id . ')</option>';
      }
      return $html;
   }

   private function buildStyleOptions(string $selected): string {
      $html = '';
      foreach ($this->writingStyles() as $key => $meta) {
         $sel = ($key === $selected) ? ' selected' : '';
         $html .= '<option value="' . $this->esc($key) . '"' . $sel . '>' . $this->esc($meta['label'] ?? $key) . '</option>';
      }
      return $html;
   }

   private function folderLabels(string $lng): array {
      $snap = $this->cms()->bundleSnapshot(array('lng' => $lng, 'limit' => 500));
      $rows = is_array($snap['folders']['rows'] ?? null) ? $snap['folders']['rows'] : array();
      $byId = array();
      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $byId[(int) ($row['id'] ?? 0)] = $row;
      }
      $labels = array();
      foreach ($byId as $id => $row) {
         $parts = array();
         $cur = (int) $id;
         $guard = 0;
         while ($cur > 0 && isset($byId[$cur]) && $guard++ < 25) {
            array_unshift($parts, (string) ($byId[$cur]['name'] ?? ''));
            $cur = (int) ($byId[$cur]['parent_id'] ?? 0);
         }
         $labels[$id] = implode(' / ', array_filter($parts));
      }
      asort($labels, SORT_NATURAL | SORT_FLAG_CASE);
      return $labels;
   }

   private function folderLabel(string $lng, int $folderId): string {
      $labels = $this->folderLabels($lng);
      return (string) ($labels[$folderId] ?? ('Ordner #' . $folderId));
   }

   private function sorterAfterPage(string $lng, int $pageId): string {
      $db = dbx()->get_system_obj('dbxDB');
      $dd = dbxContentLng::ddContent($lng);
      $page = $db->select1($dd, $pageId);
      if (!is_array($page)) {
         return '';
      }
      $folder = (int) ($page['folder'] ?? 0);
      $sorter = (int) ($page['sorter'] ?? 0);
      if ($folder <= 0) {
         return '';
      }
      $rows = $db->select($dd, 'folder = ' . $folder . ' AND sorter > ' . $sorter, 'sorter,id', 'sorter,id', 'ASC', '', 1, 0, 0);
      $nextSorter = is_array($rows) && isset($rows[0]) ? (int) ($rows[0]['sorter'] ?? 0) : 0;
      if ($nextSorter > ($sorter + 1)) {
         return sprintf('%04d', $sorter + 1);
      }
      return sprintf('%04d', $sorter);
   }

   private function loadPage(string $lng, int $pageId): array {
      $db = dbx()->get_system_obj('dbxDB');
      $row = $db->select1(dbxContentLng::ddContent($lng), $pageId);
      if (!is_array($row)) {
         throw new \InvalidArgumentException('Seite nicht gefunden.');
      }
      return $row;
   }

   private function pageContentExcerpt(string $lng, int $pageId): string {
      try {
         $row = $this->loadPage($lng, $pageId);
         return $this->truncate(strip_tags((string) ($row['content'] ?? '')), 2000);
      } catch (\Throwable $e) {
         return '';
      }
   }
}
