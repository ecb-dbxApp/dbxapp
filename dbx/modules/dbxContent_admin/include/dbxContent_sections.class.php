<?php
namespace dbx\dbxContent_admin;

dbx()->get_system_obj('dbxReport', 'use');
require_once __DIR__ . '/dbxReport_ContentSections.class.php';
require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContent_permalink;

class dbxContent_sections {

   private $content_folders = array();
   private $media_usages = array();
   private $section_texts = null;

   private function section_texts() {
      if ($this->section_texts) return $this->section_texts;
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('content-section-texts');
      $texts->set_field_definition('dbxContent_admin|rpt-content-list-selection');
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->section_texts = $texts;
      return $this->section_texts;
   }

   private function render_section($title, $subtitle, $content, $bar_actions = '') {
      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxContent_admin|content-admin-section', array(
         'title' => $title,
         'subtitle' => $subtitle,
         'content' => $content,
         'bar_actions' => $bar_actions,
      ));
   }

   private function is_ajax_request() {
      return (int)dbx()->get_system_var('dbx_ajax', 0, 'int') === 1;
   }

   private function render_section_or_ajax($title, $subtitle, $content, $bar_actions = '') {
      if ($this->is_ajax_request()) {
         return $content;
      }

      return $this->render_section($title, $subtitle, $content, $bar_actions);
   }

   private function base_url($action, $params = array()) {
      return dbx()->append_url_params(
         '?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=' . rawurlencode((string)$action),
         $params
      );
   }

   private function request_json() {
      return dbx()->get_json_request();
   }

   private function content_grid_folder_editor_values() {
      if (!$this->content_folders) {
         $db = dbx()->get_system_obj('dbxDB');
         $this->load_content_folders_map($db);
      }

      $pairs = array('0=/');
      $folder_ids = array_keys($this->content_folders);
      usort($folder_ids, function ($a, $b) {
         return strcmp($this->content_folder_path($a), $this->content_folder_path($b));
      });

      foreach ($folder_ids as $folder_id) {
         $path = $this->content_folder_path($folder_id);
         $label = str_replace(array('~', '=', ';'), array(' ', ' ', ' '), $path);
         $pairs[] = (int)$folder_id . '=' . $label;
      }

      return implode('~', $pairs);
   }

   private function content_grid_cols($texts) {
      $folder_values = $this->content_grid_folder_editor_values();

      return implode(',', array(
         'id[ID]:number:p:width=72;hozAlign=center;headerHozAlign=center',
         'title[' . $texts->get_fd_message('column_title') . ']:text::width=240',
         'permalink[' . $texts->get_fd_message('column_permalink') . ']:text::width=200',
         'folder[' . $texts->get_fd_message('column_folder') . ']:text::editor=list;values=' . $folder_values . ';width=220',
         'activ[' . $texts->get_fd_message('column_active') . ']:text::editor=list;values=0='
            . $texts->get_fd_message('status_inactive') . '~1=' . $texts->get_fd_message('status_active') . ';width=110',
         'update_date[' . $texts->get_fd_message('column_updated') . ']:text:p:width=170',
      ));
   }

   private function load_content_folders_map($db) {
      $folder_rows = $db->select(
         dbxContentLng::dd_folder(),
         '',
         'id,name,parent_id',
         'id',
         'ASC',
         '',
         0,
         0,
         0
      );
      $this->content_folders = array();
      if (is_array($folder_rows)) {
         foreach ($folder_rows as $folder_row) {
            $folder_id = (int)($folder_row['id'] ?? 0);
            if ($folder_id > 0) {
               $this->content_folders[$folder_id] = $folder_row;
            }
         }
      }
   }

   private function enrich_content_row(array $row) {
      $row['folder_path'] = $this->content_folder_path($row['folder'] ?? 0);
      if (isset($row['update_date'])) {
         $row['update_date'] = preg_replace('/\.\d+$/', '', trim((string)$row['update_date']));
      }
      $id = (int)($row['id'] ?? 0);
      if ($id > 0) {
         $row['profile_link'] = $this->base_url('edit', array('cid' => $id)) . '&dbx_window=1';
         $row['show_link'] = '?dbx_modul=dbxContent&dbx_run1=show&dbx_cid=' . $id . '&dbx_window=1';
      }
      return $row;
   }

   private function grid_sort_from_request() {
      $rsort = 'id';
      $rdesc = 'DESC';
      $allowed = array('id', 'title', 'permalink', 'activ', 'update_date', 'folder');
      $sort = dbx()->get_request_var('dbx_sorters', '', '*');
      if ($sort) {
         $sorters = json_decode($sort, true);
         if (is_array($sorters) && isset($sorters[0]['field'])) {
            $field = (string)$sorters[0]['field'];
            if (in_array($field, $allowed, true)) {
               $rsort = $field;
               $rdesc = strtolower((string)($sorters[0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            }
         }
      }
      return array($rsort, $rdesc);
   }

   private function content_grid_read() {
      $db = dbx()->get_system_obj('dbxDB');
      $dd = dbxContentLng::dd_content();
      $this->load_content_folders_map($db);

      $page = (int)dbx()->get_request_var('page', 0, 'int');
      $size = (int)dbx()->get_request_var('size', 0, 'int');

      $search = dbx()->get_request_var('dbx_search', '', 'sqlsearch|max=128');
      $where = $db->build_search_where($dd, $search, array('title', 'permalink'), array('id'), 'contains');
      list($rsort, $rdesc) = $this->grid_sort_from_request();

      $flds = array('id', 'title', 'permalink', 'folder', 'activ', 'update_date');

      if ($page > 0 && $size > 0) {
         $size = max(1, min(200, $size));
         $rpos = ($page - 1) * $size;
         $count = (int)$db->count($dd, $where);
         $rows = $db->select($dd, $where, $flds, $rsort, $rdesc, '', $size, $rpos);
      } else {
         $rows = $db->select($dd, $where, $flds, $rsort, $rdesc, '', 0, 0);
         $count = is_array($rows) ? count($rows) : 0;
      }
      if (!is_array($rows)) {
         $rows = array();
      }

      $out = array();
      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $out[] = $this->enrich_content_row($row);
      }

      $count = (int)$count;
      $last_page = ($page > 0 && $size > 0) ? max(1, (int)ceil($count / $size)) : 1;

      dbx()->json_response(array(
         'ok' => 1,
         'count' => $count,
         'last_page' => $last_page,
         'last_row' => $count,
         'rows' => $out,
         'server_time' => (new \DateTime())->format('Y-m-d H:i:s.v'),
      ), true);
   }

   private function content_grid_sync() {
      $db = dbx()->get_system_obj('dbxDB');
      $dd = dbxContentLng::dd_content();
      $this->load_content_folders_map($db);

      $last_update = trim((string)dbx()->get_request_var('last_update', ''));
      if ($last_update === '') {
         $last_update = trim((string)dbx()->get_modul_var('last_update', ''));
      }
      if ($last_update === '') {
         $last_update = '1970-01-01 00:00:00';
      }

      $where = "update_date > '" . str_replace("'", "''", $last_update) . "'";
      $flds = array('id', 'title', 'permalink', 'folder', 'activ', 'update_date');
      $rows = $db->select($dd, $where, $flds, 'update_date', 'ASC', '', 0, 0);
      if (!is_array($rows)) {
         $rows = array();
      }

      $out = array();
      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $out[] = $this->enrich_content_row($row);
      }

      dbx()->json_response(array(
         'ok' => 1,
         'count' => count($out),
         'rows' => $out,
         'server_time' => (new \DateTime())->format('Y-m-d H:i:s.v'),
      ), true);
   }

   private function content_grid_save() {
      $db = dbx()->get_system_obj('dbxDB');
      $cms = dbx()->get_include_obj('dbxContent_cms');
      if (is_object($cms) && method_exists($cms, 'ensure_schema')) {
         $cms->ensure_schema($db);
      }

      $payload = $this->request_json();
      $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : array();
      $dd = dbxContentLng::dd_content();
      $allowed = array_flip(array('title', 'permalink', 'activ', 'folder'));
      $saved = array();

      $this->load_content_folders_map($db);

      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) {
            continue;
         }

         $data = array();
         foreach ($allowed as $field => $_) {
            if (!array_key_exists($field, $row)) {
               continue;
            }
            if ($field === 'activ' || $field === 'folder') {
               $data[$field] = (int)$row[$field];
            } else {
               $data[$field] = trim((string)$row[$field]);
            }
         }
         if (!$data) {
            continue;
         }

         if (array_key_exists('folder', $data)) {
            $folder_id = (int)$data['folder'];
            if ($folder_id < 0 || ($folder_id > 0 && !isset($this->content_folders[$folder_id]))) {
               continue;
            }
         }

         if (array_key_exists('permalink', $data)) {
            if ($data['permalink'] === '') {
               $current = $db->select1($dd, $id, 'title,folder', 0);
               $page_title = (string)($data['title'] ?? ($current['title'] ?? 'Seite'));
               $page_folder = (int)($data['folder'] ?? ($current['folder'] ?? 0));
               $data['permalink'] = dbxContent_permalink::build(
                  $db,
                  dbxContentLng::dd_folder(),
                  $page_folder,
                  $page_title,
                  $id
               );
            } elseif (!dbxContent_permalink::is_valid($data['permalink'])) {
               dbx()->json_response(array(
                  'ok' => 0,
                  'success' => false,
                  'field' => 'permalink',
                  'id' => $id,
                  'msg' => 'Permalink: nur Kleinbuchstaben, Zahlen und einzelne Bindestriche sind erlaubt.',
               ), true);
            } elseif (dbxContent_permalink::exists($db, $dd, $data['permalink'], $id)) {
               dbx()->json_response(array(
                  'ok' => 0,
                  'success' => false,
                  'field' => 'permalink',
                  'id' => $id,
                  'msg' => 'Dieser Permalink wird bereits von einer anderen Seite verwendet.',
               ), true);
            }
         }

         if ($db->update($dd, $data, $id) >= 0) {
            dbxContentLngSync::after_page_save($db, $id, false);
            dbxContentPageCache::invalidate_content($id);
            dbxContentPageCache::invalidate_all_menus();
            $fresh = $db->select1($dd, $id, 'id,title,permalink,folder,activ,update_date', 0);
            if (is_array($fresh)) {
               $saved[] = $this->enrich_content_row($fresh);
            }
         }
      }

      $this->load_content_folders_map($db);

      dbx()->json_response(array(
         'ok' => 1,
         'success' => true,
         'rows' => $saved,
      ), true);
   }

   private function content_grid_delete() {
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? 0);
      if ($id <= 0) {
         dbx()->json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Seiten-ID.'), true);
      }

      $cms = dbx()->get_include_obj('dbxContent_cms');
      $result = is_object($cms) ? $cms->delete_page_record($id) : array('ok' => 0, 'errors' => array('CMS nicht verfuegbar.'));
      $errors = is_array($result['errors'] ?? null) ? $result['errors'] : array();

      dbx()->json_response(array(
         'ok' => (int)($result['ok'] ?? 0),
         'success' => (int)($result['ok'] ?? 0) === 1,
         'msg' => implode(' ', $errors),
      ), true);
   }

   private function report_content_grid() {
      $o_report = dbx()->get_system_obj('dbxReport');
      $db = dbx()->get_system_obj('dbxDB');
      $dd = dbxContentLng::dd_content();
      $all = (int)$db->count($dd);
      $active = (int)$db->count($dd, 'activ = 1');

      $o_report->init('content-admin-content-grid', 'content-admin-content-grid');
      $o_report->set_field_definition('dbxContent_admin|rpt-content-list-selection');
      $o_report->load_fd_messages();
      $o_report->add_rep('shell_panel_class', 'dbx-grid dbx-content-list-grid');
      $o_report->add_rep('frame_use_form', '0');
      $o_report->add_rep('bar_title', $o_report->get_fd_message('bar_title'));
      $o_report->add_rep('bar_subtitle', $o_report->get_fd_message('bar_subtitle'));
      $o_report->set_mode('tabulator');
      $o_report->_rrows = 600;
      $o_report->_grid_id = 'dbx_content_list_grid';
      $o_report->_grid_cols = $this->content_grid_cols($o_report);
      $o_report->_grid_layout = 'fitDataStretch';
      $o_report->_grid_read_url = $this->base_url('content_grid_read');
      $o_report->_grid_save_url = $this->base_url('content_grid_save');
      $o_report->_grid_delete_url = $this->base_url('content_grid_delete');
      $o_report->_grid_sync_url = $this->base_url('content_grid_sync');
      $o_report->_grid_synctime = '1.5';
      $o_report->add_grid_stats(array(
         array('label' => $o_report->get_fd_message('stats_pages'), 'value' => (string)$all),
         array('label' => $o_report->get_fd_message('stats_active'), 'value' => (string)$active, 'tone' => 'ok'),
         array('label' => $o_report->get_fd_message('stats_inactive'), 'value' => (string)max(0, $all - $active)),
      ), $o_report->get_fd_message('stats_title'));
      $o_report->add_obj('bar_actions', 'obj-value',
         '<a class="btn btn-outline-primary btn-sm" href="' . dbx()->esc($this->base_url('edit')) . '">' .
         '<i class="bi bi-pencil-square"></i><span>' . dbx()->esc($o_report->get_fd_message('cms_label')) . '</span></a>'
      );

      return $o_report->run();
   }

   private function report_rows($action, array $fields, array $rows, $msg_success = '', $msg_error = '') {
      $report = new dbxReport_ContentSections();
      $report->init('content-admin-report', 'content-admin-report');
      $report->set_field_definition('dbxContent_admin|rpt-content-list-selection');
      $report->load_fd_messages();
      $report->set_form_help_enabled(false);
      $report->set_action('?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=' . rawurlencode($action));
      $report->_create_sel_flds = 0;
      $report->_rflds = $fields;
      $report->set_mode('table');
      $report->_rdata = $rows;
      $report->_rcount = count($rows);
      $report->_rrows = count($rows) > 0 ? count($rows) : 1;
      $report->_rpos = 0;

      if ($msg_success !== '') {
         $report->_msg_success = $msg_success;
      }
      if ($msg_error !== '') {
         $report->_msg_error = $msg_error;
      }

      if ($action === 'list_folder') {
         $report->set_callback_owner($this);
         $report->set_callback('next_record', 'folder_next_record');
         $report->set_callback('row_action_data', 'folder_row_action_data');
         $report->set_table_actions(array('edit', 'delete'));
         $report->_msg_confirm_delete = $report->get_fd_message('confirm_folder_delete');
         $report->set_table_tpl('tpl_row_edit', 'modul|content-admin-row-edit');
      } elseif ($action === 'list_media') {
         $report->set_callback_owner($this);
         $report->set_callback('next_record', 'media_next_record');
         $report->set_callback('row_action_data', 'media_row_action_data');
         $report->set_table_actions(array('delete'));
         $report->_msg_confirm_delete = $report->get_fd_message('confirm_media_delete');
         $report->set_table_tpl('tpl_row_delete', 'modul|content-admin-row-delete-media');
      } elseif ($action === 'templates') {
         $report->set_callback_owner($this);
         $report->set_callback('row_action_data', 'template_row_action_data');
         $report->set_table_actions(array('edit', 'delete'));
         $report->_fld_id = 'name';
         $report->_msg_confirm_delete = $report->get_fd_message('confirm_template_delete');
         $report->_rpt_format['modified'] = 'php-datetime-usr';
         $report->_table_buttons = 'left';
         $report->set_table_tpl('tpl_row_edit', 'modul|content-admin-row-edit');
         $report->set_table_tpl('tpl_row_delete', 'modul|content-admin-row-delete-template');
      }

      return $report->run();
   }

   private function content_folder_path($folder_id) {
      $folder_id = (int)$folder_id;
      if ($folder_id <= 0) {
         return '/';
      }

      $parts = array();
      $seen = array();

      while ($folder_id > 0 && isset($this->content_folders[$folder_id]) && !isset($seen[$folder_id])) {
         $seen[$folder_id] = true;
         $folder = $this->content_folders[$folder_id];
         $name = trim((string)($folder['name'] ?? ''), " \t\n\r\0\x0B/");

         if ($name !== '') {
            array_unshift($parts, $name);
         }

         $parent_id = (int)($folder['parent_id'] ?? 0);
         if ($parent_id === $folder_id) {
            break;
         }
         $folder_id = $parent_id;
      }

      return '/' . implode('/', $parts);
   }

   public function content_next_record($report, $record) {
      if (!is_array($record)) {
         return $record;
      }

      $record['folder_path'] = $this->content_folder_path($record['folder'] ?? 0);
      if (isset($record['update_date'])) {
         $record['update_date'] = preg_replace('/\.\d+$/', '', trim((string)$record['update_date']));
      }
      return $record;
   }

   public function content_row_action_data($report, $data) {
      if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
         return $data;
      }

      $type = (string)($data['type'] ?? '');
      $rid = (int)($data['data']['rid'] ?? 0);

      if ($type === 'edit') {
         $url = '?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=edit&cid=' . $rid . '&dbx_window=1';
         $data['data']['action'] = $url;
         $data['data']['edit_url'] = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
         $data['data']['edit_title'] = htmlspecialchars('Content im CMS bearbeiten', ENT_QUOTES, 'UTF-8');
         $data['data']['class'] = 'openWin';
      } elseif ($type === 'delete') {
         $data['data']['action'] =
            '?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=list_content';
      }

      return $data;
   }

   public function media_next_record($report, $record) {
      if (!is_array($record)) {
         return $record;
      }

      $id = (int)($record['id'] ?? 0);
      $type = strtolower(trim((string)($record['media_type'] ?? '')));
      $provider = strtolower(trim((string)($record['provider'] ?? '')));
      $provider_id = trim((string)($record['provider_id'] ?? ''));
      $title = trim((string)($record['title'] ?? $record['file_name'] ?? 'Medium'));
      $preview = '<span class="text-muted"><i class="bi bi-file-earmark"></i></span>';

      if ($id > 0 && in_array($type, array('image', 'video', 'external_video'), true)) {
         $media_url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $id;
         $url = '?dbx_modul=dbxContent_admin&dbx_run1=media_view&rid=' . $id . '&dbx_window=1';
         $thumb_url = $media_url . '&dbx_thumb=1';
         if ($type === 'external_video' && $provider === 'youtube' && preg_match('/^[A-Za-z0-9_-]{11}$/', $provider_id)) {
            $thumb_url = 'https://img.youtube.com/vi/' . rawurlencode($provider_id) . '/hqdefault.jpg';
         }
         $preview =
            '<a class="openWin dbx-win" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" ' .
            'data-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" ' .
            'data-title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" ' .
            'title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' .
            '<img src="' . htmlspecialchars($thumb_url, ENT_QUOTES, 'UTF-8') . '" ' .
            'alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" loading="lazy" ' .
            'class="rounded border bg-light" style="width:96px;height:64px;object-fit:cover">' .
            '</a>';
      }

      $record['thumbnail'] = $preview;
      $usages = $this->media_usages[$id] ?? array();
      $usage_html = array();
      foreach ($usages as $usage) {
         $lng = strtolower(trim((string)($usage['lng'] ?? '')));
         $usage_id = (int)($usage['id'] ?? 0);
         $type = (string)($usage['type'] ?? 'page');
         $label = trim((string)($usage['title'] ?? ''));
         $url = '?dbx_modul=dbxContent_admin&dbx_run1=cms&dbx_lng=' . rawurlencode($lng) .
            ($type === 'folder' ? '&fid=' : '&cid=') . $usage_id . '&dbx_window=1';
         $usage_html[] =
            '<a class="openWin dbx-win d-block text-decoration-none mb-1" ' .
            'href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" ' .
            'data-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" ' .
            'data-title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" ' .
            'title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' .
            '<span class="badge text-bg-secondary me-1">' . htmlspecialchars(strtoupper($lng), ENT_QUOTES, 'UTF-8') . '</span>' .
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
            '</a>';
      }
      $record['usage'] = count($usage_html)
         ? implode('', $usage_html)
         : '<span class="text-muted">Nicht verwendet</span>';
      $record['_usage_count'] = count($usages);
      return $record;
   }

   public function media_row_action_data($report, $data) {
      if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
         return $data;
      }

      if ((string)($data['type'] ?? '') !== 'delete') {
         return $data;
      }

      $rid = (int)($data['data']['rid'] ?? 0);
      $used = count($this->media_usages[$rid] ?? array()) > 0;
      $data['data']['delete_url'] =
         '?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=list_media&dbx_do=row_delete&rid=' . $rid;
      $data['data']['delete_class'] = $used ? 'disabled text-muted' : 'dbxAjax dbxConfirm text-danger';
      $data['data']['delete_title'] = $used
         ? 'Medium wird verwendet und kann nicht geloescht werden'
         : 'Medium loeschen';
      $data['data']['delete_disabled'] = $used ? 'aria-disabled="true" tabindex="-1"' : '';
      $data['data']['confirm'] = $report->_msg_confirm_delete;

      return $data;
   }

   private function add_media_usage(array &$map, int $media_id, string $lng, int $id, string $title, string $type = 'page'): void {
      if ($media_id <= 0 || $id <= 0 || $lng === '') {
         return;
      }

      $key = $type . '|' . $lng . '|' . $id;
      $map[$media_id][$key] = array(
         'lng' => $lng,
         'id' => $id,
         'title' => ($type === 'folder' ? 'Ordner: ' : '') . ($title !== '' ? $title : ('#' . $id)),
         'type' => $type,
      );
   }

   private function load_media_usages($db): array {
      $map = array();
      $usage_rows = $db->select('dbxMediaUsage', 'active = 1', 'media_id,content_id,folder_id,content_lng', 'media_id,id', 'ASC', '', 0, 0, 0);
      $usage_rows = is_array($usage_rows) ? $usage_rows : array();

      foreach (dbxContentLngSync::accessible_lngs() as $lng) {
         $lng = strtolower(trim((string)$lng));
         if ($lng === '') {
            continue;
         }

         $pages = $db->select(
            dbxContentLng::dd_content($lng),
            '',
            'id,title,hero_image_id,seo_image_id,content',
            'id',
            'ASC',
            '',
            0,
            0,
            0
         );
         $folders = $db->select(
            dbxContentLng::dd_folder($lng),
            '',
            'id,name,hero_image_id',
            'id',
            'ASC',
            '',
            0,
            0,
            0
         );

         $pages_by_id = array();
         foreach (is_array($pages) ? $pages : array() as $page) {
            $page_id = (int)($page['id'] ?? 0);
            if ($page_id <= 0) {
               continue;
            }
            $pages_by_id[$page_id] = $page;

            $hero_id = (int)($page['hero_image_id'] ?? 0);
            if ($hero_id > 0) {
               $this->add_media_usage($map, $hero_id, $lng, $page_id, (string)($page['title'] ?? ''), 'page');
            }
            $seo_id = (int)($page['seo_image_id'] ?? 0);
            if ($seo_id > 0) {
               $this->add_media_usage($map, $seo_id, $lng, $page_id, (string)($page['title'] ?? ''), 'page');
            }
            $inline_ids = array();
            $content = (string)($page['content'] ?? '');
            if (preg_match_all('/data-cms-media-id=["\']?(\d+)/i', $content, $matches)) {
               foreach ($matches[1] as $media_id) $inline_ids[(int)$media_id] = (int)$media_id;
            }
            if (preg_match_all('/(?:dbx_mid|media_id)=(\d+)(?:[^0-9]|$)/i', $content, $matches)) {
               foreach ($matches[1] as $media_id) $inline_ids[(int)$media_id] = (int)$media_id;
            }
            foreach ($inline_ids as $media_id) {
               if ($media_id > 0) {
                  $this->add_media_usage($map, (int)$media_id, $lng, $page_id, (string)($page['title'] ?? ''), 'page');
               }
            }
         }

         $folders_by_id = array();
         foreach (is_array($folders) ? $folders : array() as $folder) {
            $folder_id = (int)($folder['id'] ?? 0);
            if ($folder_id <= 0) {
               continue;
            }
            $folders_by_id[$folder_id] = $folder;
            $hero_id = (int)($folder['hero_image_id'] ?? 0);
            if ($hero_id > 0) {
               $this->add_media_usage($map, $hero_id, $lng, $folder_id, (string)($folder['name'] ?? ''), 'folder');
            }
         }

         foreach ($usage_rows as $usage) {
            if (strtolower(trim((string)($usage['content_lng'] ?? 'de'))) !== $lng) continue;
            $media_id = (int)($usage['media_id'] ?? 0);
            $content_id = (int)($usage['content_id'] ?? 0);
            $folder_id = (int)($usage['folder_id'] ?? 0);
            if ($content_id > 0 && isset($pages_by_id[$content_id])) {
               $this->add_media_usage(
                  $map,
                  $media_id,
                  $lng,
                  $content_id,
                  (string)($pages_by_id[$content_id]['title'] ?? ''),
                  'page'
               );
            } elseif ($folder_id > 0 && isset($folders_by_id[$folder_id])) {
               $this->add_media_usage(
                  $map,
                  $media_id,
                  $lng,
                  $folder_id,
                  (string)($folders_by_id[$folder_id]['name'] ?? ''),
                  'folder'
               );
            }
         }
      }

      foreach ($map as $media_id => $items) {
         $map[$media_id] = array_values($items);
      }
      return $map;
   }

   public function folder_next_record($report, $record) {
      if (!is_array($record)) {
         return $record;
      }

      $record['folder_path'] = $this->content_folder_path($record['id'] ?? 0);
      return $record;
   }

   public function folder_row_action_data($report, $data) {
      if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
         return $data;
      }

      $type = (string)($data['type'] ?? '');
      $rid = (int)($data['data']['rid'] ?? 0);

      if ($type === 'edit') {
         $url = '?dbx_modul=dbxContent_admin&dbx_run1=cms&fid=' . $rid . '&dbx_window=1';
         $data['data']['action'] = $url;
         $data['data']['edit_url'] = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
         $data['data']['edit_title'] = htmlspecialchars(
            $this->section_texts()->get_fd_message('edit_folder_in_cms'),
            ENT_QUOTES,
            'UTF-8'
         );
         $data['data']['class'] = 'openWin';
      } elseif ($type === 'delete') {
         $data['data']['action'] =
            '?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=list_folder';
      }

      return $data;
   }

   private function report_content() {
      return $this->report_content_grid();
   }

   private function report_folders() {
      $db = dbx()->get_system_obj('dbxDB');
      $texts = $this->section_texts();

      $delete_id = (int)dbx()->get_modul_var('rid', 0, 'int');
      if (dbx()->get_modul_var('dbx_do', '', 'parameter') === 'row_delete' && $delete_id > 0) {
         $cms = dbx()->get_include_obj('dbxContent_cms');
         $cms->delete_folder_record($delete_id);
      }

      $fields = array(
         'id' => 'ID',
         'folder_path' => $texts->get_fd_message('column_folder'),
         'template' => $texts->get_fd_message('column_template'),
         'group_read' => $texts->get_fd_message('column_read_rights'),
      );

      $rows = $db->select(
         dbxContentLng::dd_folder(),
         '',
         'id,name,parent_id,template,group_read',
         'name',
         'ASC',
         '',
         0,
         0,
         0
      );
      if (!is_array($rows)) {
         $rows = array();
      }

      $this->content_folders = array();
      foreach ($rows as $index => $row) {
         $folder_id = (int)($row['id'] ?? 0);
         if ($folder_id > 0) {
            $this->content_folders[$folder_id] = $row;
         }
         $rows[$index]['folder_path'] = '';
      }

      return $this->report_rows('list_folder', $fields, $rows);
   }

   private function report_media() {
      $db = dbx()->get_system_obj('dbxDB');
      $texts = $this->section_texts();

      $this->media_usages = $this->load_media_usages($db);
      $delete_id = (int)dbx()->get_modul_var('rid', 0, 'int');
      if (dbx()->get_modul_var('dbx_do', '', 'parameter') === 'row_delete' && $delete_id > 0) {
         $cms = dbx()->get_include_obj('dbxContent_cms');
         $cms->delete_media_record($delete_id);
         $this->media_usages = $this->load_media_usages($db);
      }

      $fields = array(
         'thumbnail' => $texts->get_fd_message('column_preview'),
         'id' => 'ID',
         'title' => $texts->get_fd_message('column_title'),
         'media_type' => $texts->get_fd_message('column_type'),
         'media_folder' => $texts->get_fd_message('column_folder'),
         'provider' => $texts->get_fd_message('column_provider'),
         'usage' => $texts->get_fd_message('column_usage'),
         'active' => $texts->get_fd_message('column_active'),
      );

      $rows = $db->select(
         'dbxMedia',
         '',
         'id,title,media_type,file_name,media_folder,provider,provider_id,active',
         'id',
         'DESC',
         '',
         0,
         0,
         0
      );
      if (!is_array($rows)) {
         $rows = array();
      }

      foreach ($rows as $index => $row) {
         $rows[$index]['thumbnail'] = '';
         $rows[$index]['usage'] = '';
      }

      return $this->report_rows('list_media', $fields, $rows);
   }

   private function media_view() {
      $id = (int)dbx()->get_modul_var('rid', 0, 'int');
      $db = dbx()->get_system_obj('dbxDB');
      $row = $id > 0 ? $db->select1('dbxMedia', $id) : array();

      if (!is_array($row) || !count($row)) {
         return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-warning', array(
            'msg' => $this->section_texts()->get_fd_message('media_not_found'),
         ));
      }

      $type = strtolower(trim((string)($row['media_type'] ?? 'file')));
      $provider = strtolower(trim((string)($row['provider'] ?? '')));
      $provider_id = trim((string)($row['provider_id'] ?? ''));
      $title = trim((string)($row['title'] ?? $row['file_name'] ?? ('Medium #' . $id)));
      $mime = trim((string)($row['mime'] ?? ''));
      $media_url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $id;
      $media_html = '';

      if ($type === 'image') {
         $media_html =
            '<img src="' . htmlspecialchars($media_url, ENT_QUOTES, 'UTF-8') . '" ' .
            'alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" ' .
            'class="img-fluid rounded border bg-light" style="max-height:72vh;object-fit:contain">';
      } elseif ($type === 'video') {
         $poster = !empty($row['thumb_file_path'])
            ? ' poster="' . htmlspecialchars($media_url . '&dbx_thumb=1', ENT_QUOTES, 'UTF-8') . '"'
            : '';
         $media_html =
            '<video class="w-100 rounded border bg-dark" style="max-height:72vh" controls preload="metadata" playsinline' . $poster . '>' .
            '<source src="' . htmlspecialchars($media_url, ENT_QUOTES, 'UTF-8') . '"' .
            ($mime !== '' ? ' type="' . htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') . '"' : '') . '>' .
            'Ihr Browser kann dieses Video nicht wiedergeben.</video>';
      } elseif ($type === 'external_video' && $provider === 'youtube' && preg_match('/^[A-Za-z0-9_-]{11}$/', $provider_id)) {
         $embed_url = 'https://www.youtube.com/embed/' . rawurlencode($provider_id);
         $media_html =
            '<div class="ratio ratio-16x9"><iframe src="' . htmlspecialchars($embed_url, ENT_QUOTES, 'UTF-8') . '" ' .
            'title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" ' .
            'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" ' .
            'allowfullscreen></iframe></div>';
      } else {
         $media_html =
            '<div class="text-center py-5"><i class="bi bi-file-earmark fs-1 d-block mb-3"></i>' .
            '<a class="btn btn-primary" href="' . htmlspecialchars($media_url, ENT_QUOTES, 'UTF-8') . '">' .
            '<i class="bi bi-box-arrow-up-right"></i> Datei anzeigen</a></div>';
      }

      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxContent_admin|content-admin-media-view', array(
         'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
         'media' => $media_html,
         'type' => htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
         'folder' => htmlspecialchars((string)($row['media_folder'] ?? ''), ENT_QUOTES, 'UTF-8'),
         'mime' => htmlspecialchars($mime, ENT_QUOTES, 'UTF-8'),
         'id' => (string)$id,
      ));
   }

   public function template_row_action_data($report, $data) {
      if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
         return $data;
      }

      $record = is_array($data['record'] ?? null) ? $data['record'] : array();
      $type = (string)($data['type'] ?? '');
      $tpl = trim((string)($record['name'] ?? ''));

      if ($type === 'edit') {
         $modul = trim((string)($record['modul'] ?? 'dbxContent'));
         $file_type = trim((string)($record['type'] ?? 'htm'));

         $rel_path = 'dbx/modules/' . $modul . '/tpl/' . $file_type . '/' . $tpl . '.' . $file_type;
         $url = '?dbx_modul=dbxEditor&dbx_run1=edit&file=' . rawurlencode($rel_path) . '&dbx_window=1';

         $data['data']['action'] = $url;
         $data['data']['edit_url'] = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
         $data['data']['edit_title'] = htmlspecialchars($modul . '|' . $tpl, ENT_QUOTES, 'UTF-8');
         $data['data']['class'] = 'openWin';
      } elseif ($type === 'delete' && $tpl !== '') {
         $data['data']['delete_url'] =
            '?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=templates&dbx_do=row_delete&rid=' .
            rawurlencode($tpl);
         $data['data']['delete_class'] = 'dbxAjax dbxConfirm text-danger';
         $data['data']['delete_title'] = 'Template loeschen';
         $data['data']['delete_disabled'] = '';
         $data['data']['confirm'] = $report->_msg_confirm_delete;
      }

      return $data;
   }

   private function template_dir() {
      return dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxContent/tpl/htm/');
   }

   private function normalize_template_name($name) {
      $name = trim((string)$name);
      $name = preg_replace('/\.htm$/i', '', $name);

      if ($name !== '' && stripos($name, 'c-') !== 0) {
         $name = 'c-' . $name;
      }

      if ($name === '' || !preg_match('/^c-[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $name)) {
         return '';
      }

      return $name;
   }

   private function template_file_path($name) {
      $name = $this->normalize_template_name($name);
      if ($name === '') {
         return '';
      }

      $dir = rtrim($this->template_dir(), '/\\') . DIRECTORY_SEPARATOR;
      if (!is_dir($dir)) {
         return '';
      }

      return $dir . $name . '.htm';
   }

   private function default_template_markup() {
      return <<<'HTML'
<article id="dbx_target_{i}" class="c-cms default">

  <div class="title">
    <h1>{cms:title}</h1>
  </div>

  <section class="cms-hero {cms:hero_class}" style="{hero:style}">
    <div class="hero">{cms:hero}</div>
  </section>

  <section class="cms-header header">{cms:header}</section>

  <section class="gallery {cms:gallery_class}">
    <div class="gallery-list"
         style="{gallery:style}"{gallery:data_dbx}>{cms:gallery}</div>
  </section>

  <section class="cols cols-{cms:cols}">
    <div class="col col-1">{cms:col1}</div>
    <div class="col col-2">{cms:col2}</div>
    <div class="col col-3">{cms:col3}</div>
  </section>

  <footer class="footer">{cms:footer}</footer>

</article>

HTML;
   }

   private function delete_template_file($name) {
      $path = $this->template_file_path($name);
      if ($path === '' || !is_file($path)) {
         return false;
      }

      return @unlink($path);
   }

   private function create_template_file($name) {
      $texts = $this->section_texts();
      $name = $this->normalize_template_name($name);
      if ($name === '') {
         return array('ok' => false, 'msg' => $texts->get_fd_message('template_invalid_name'));
      }

      $path = $this->template_file_path($name);
      if ($path === '') {
         return array('ok' => false, 'msg' => $texts->get_fd_message('template_path_error'));
      }

      if (is_file($path)) {
         return array('ok' => false, 'msg' => $texts->format_fd_message('template_exists', array('name' => $name)));
      }

      $bytes = @file_put_contents($path, $this->default_template_markup());
      if ($bytes === false) {
         return array('ok' => false, 'msg' => $texts->get_fd_message('template_create_error'));
      }

      return array(
         'ok' => true,
         'msg' => $texts->format_fd_message('template_created', array('name' => $name)),
         'name' => $name
      );
   }

   private function templates_bar_actions() {
      $texts = $this->section_texts();
      $url = '?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=template_new&dbx_window=1';

      return '<a class="btn btn-primary btn-sm openWin dbx-win" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" ' .
         'data-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" ' .
         'data-title="' . htmlspecialchars($texts->get_fd_message('template_new_title'), ENT_QUOTES, 'UTF-8') .
         '" title="' . htmlspecialchars($texts->get_fd_message('template_new_action_title'), ENT_QUOTES, 'UTF-8') . '">' .
         '<i class="bi bi-plus-lg"></i> ' .
         htmlspecialchars($texts->get_fd_message('template_new_button'), ENT_QUOTES, 'UTF-8') . '</a>';
   }

   /**
    * Rendert und verarbeitet das Formular zum Anlegen eines c-*-Templates.
    *
    * Dateiname und Pflichtregeln werden von dbxForm geprüft. Die Datei wird
    * erst nach erfolgreicher CSRF- und Feldvalidierung angelegt.
    */
   private function template_new() {
      $action = '?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=template_new';
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('content-template-new', 'content-admin-template-new');
      $form->set_field_definition('dbxContent_admin|rpt-content-list-selection');
      $form->load_fd_messages();
      $form->set_action($action);
      // Den von init() erzeugten Security-Wert erhalten.
      $form->merge_data(array('template_name' => ''));
      $form->_msg_info = $form->get_fd_message('template_new_info');
      $form->add_fld(
         'template_name',
         'text-label',
         label: $form->get_fd_message('template_name_label'),
         rules: 'parameter|min=3|max=120',
         placeholder: 'c-mein-layout',
         errormsg: $form->get_fd_message('template_name_error')
      );

      $help = dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
      $help_button = is_object($help) && method_exists($help, 'formButton')
         ? $help->form_button('dbxContent_admin', 'content-template-new', $form->get_fd_message('template_new_title'))
         : '';
      $form->add_rep('help_button', $help_button);

      if ($form->submit()) {
         if (!$form->errors()) {
            $template_name = trim((string)$form->get_post('template_name', '', 'parameter|min=3|max=120'));
            $result = $this->create_template_file($template_name);
            if (!empty($result['ok'])) {
               return dbx()->redirect('?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=templates');
            }
            $form->add_fld_error('template_name', (string)($result['msg'] ?? $form->get_fd_message('template_create_error')));
            $form->_msg_error = (string)($result['msg'] ?? $form->get_fd_message('template_create_error'));
         } else {
            $form->_msg_error = $form->get_fd_message('template_name_validation');
         }
      }

      return $form->run();
   }

   private function report_templates() {
      $texts = $this->section_texts();
      $msg_success = '';
      $msg_error = '';
      $dbx_do = (string)dbx()->get_request_var('dbx_do', '', 'alphanum');
      $delete_name = trim((string)dbx()->get_request_var('rid', '', 'alphanum'));
      if ($dbx_do === 'row_delete' && $delete_name !== '') {
         if ($this->delete_template_file($delete_name)) {
            $msg_success = $texts->format_fd_message('template_deleted', array('name' => $delete_name));
         } else {
            $msg_error = $texts->get_fd_message('template_delete_error');
         }
      }

      $dir = $this->template_dir();
      $files = is_dir($dir) ? glob($dir . '*.htm') : array();
      $rows = array();

      if (is_array($files)) {
         sort($files, SORT_NATURAL | SORT_FLAG_CASE);
         foreach ($files as $file) {
            if (!is_file($file)) {
               continue;
            }

            $name = pathinfo($file, PATHINFO_FILENAME);
            if (stripos($name, 'c-') !== 0) {
               continue;
            }

            $rows[] = array(
               'modul' => 'dbxContent',
               'name' => $name,
               'type' => pathinfo($file, PATHINFO_EXTENSION),
               'size' => (string)filesize($file),
               'modified' => date('Y-m-d H:i:s', filemtime($file)),
            );
         }
      }

      $fields = array(
         'modul' => $texts->get_fd_message('column_module'),
         'name' => $texts->get_fd_message('column_template'),
         'type' => $texts->get_fd_message('column_type'),
         'size' => $texts->get_fd_message('column_bytes'),
         'modified' => $texts->get_fd_message('column_updated'),
      );

      return $this->report_rows('templates', $fields, $rows, $msg_success, $msg_error);
   }

   public function run($work = '') {
      switch ($work) {
         case 'content_grid_read':
            $this->content_grid_read();
            break;

         case 'content_grid_save':
            $this->content_grid_save();
            break;

         case 'content_grid_delete':
            $this->content_grid_delete();
            break;

         case 'content_grid_sync':
            $this->content_grid_sync();
            break;

         case 'list_content':
            return $this->report_content();

         case 'list_folder':
            $texts = $this->section_texts();
            return $this->render_section(
               $texts->get_fd_message('section_folders_title'),
               $texts->get_fd_message('section_folders_subtitle'),
               $this->report_folders()
            );

         case 'list_media':
            $texts = $this->section_texts();
            return $this->render_section(
               $texts->get_fd_message('section_media_title'),
               $texts->get_fd_message('section_media_subtitle'),
               $this->report_media()
            );

         case 'media_view':
            return $this->media_view();

         case 'templates':
            $texts = $this->section_texts();
            return $this->render_section_or_ajax(
               $texts->get_fd_message('section_templates_title'),
               $texts->get_fd_message('section_templates_subtitle'),
               $this->report_templates(),
               $this->templates_bar_actions()
            );

         case 'template_new':
            return $this->template_new();
      }

      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbx|alert-warning', array(
         'msg' => $this->section_texts()->format_fd_message(
            'section_undefined',
            array('section' => $work)
         ),
      ));
   }
}

?>
