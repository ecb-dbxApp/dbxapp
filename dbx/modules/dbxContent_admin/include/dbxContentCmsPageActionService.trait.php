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

/**
 * Seiten- und Ordneraktionen, Sortierung und Transaktionen ueber den Persistenzservice.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsPageActionServiceTrait {


   private function delete_folder_in_lngs($db, int $id, array $delete_lngs): array {
      return $this->persistence($db)->delete_folder($id, $delete_lngs);
   }



   private function page_json() {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      if ($id <= 0) {
         $id = $this->resolve_cms_page_id();
      }
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $row = $id > 0 ? $db->select1(dbxContentLng::dd_content(), $id) : $db->empty_record(dbxContentLng::dd_content())[0];
      if (is_array($row) && isset($row['content'])) {
         $row['content'] = $this->normalize_content_media_urls($row['content']);
      }
      if (is_array($row)) {
         $row = $this->normalize_gallery_row($row);
      }
      $media = $id > 0 ? $this->media_usage_rows_for_context($db, $id, 0) : array();
      $this->cms_json_response(array(
         'ok' => 1,
         'row' => $row,
         'media' => $media,
         'hero_preview_media' => $this->hero_preview_media($db, $row),
         'hero_parent_preview_media' => $this->inherited_hero_preview_media($db, (int)($row['folder'] ?? 0)),
         'seo_preview_media' => $this->seo_preview_media($db, $row),
      ));
   }



   private function hero_preview_media($db, $row) {
      if (!is_array($row)) return array();
      $hero_id = (int)($row['hero_image_id'] ?? 0);
      if ($hero_id <= 0) return array();
      $hero = $db->select1($this->dd_media, $hero_id);
      if (!is_array($hero) || (int)($hero['active'] ?? 0) !== 1) return array();
      if (!$this->media_file_exists($hero)) return array();
      return $this->normalize_media_row($hero);
   }



   private function inherited_hero_preview_media($db, $folder_id) {
      $folder_id = (int)$folder_id;
      $seen = array();
      while ($folder_id > 0 && !isset($seen[$folder_id])) {
         $seen[$folder_id] = 1;
         $folder = $db->select1(dbxContentLng::dd_folder(), $folder_id, '*', 0);
         if (!is_array($folder)) return array();
         $hero_template = trim((string)($folder['hero_template'] ?? 'parent'));
         if ($this->is_no_hero_template($hero_template)) return array();
         $hero_value = trim((string)($folder['hero_image_id'] ?? 'parent'));
         $hero_id = (int)$hero_value;
         if ($hero_id > 0) {
            $hero = $db->select1($this->dd_media, $hero_id);
            if (!is_array($hero) || (int)($hero['active'] ?? 0) !== 1 || !$this->media_file_exists($hero)) return array();
            $hero = $this->normalize_media_row($hero);
            $hero['parent_folder_id'] = $folder_id;
            $hero['parent_folder_name'] = (string)($folder['name'] ?? '');
            return $hero;
         }
         if ($hero_value === '0' || strtolower($hero_value) === 'none') return array();
         $folder_id = (int)($folder['parent_id'] ?? 0);
      }
      return array();
   }



   private function seo_preview_media($db, $row) {
      if (!is_array($row)) return array();
      $seo_id = (int)($row['seo_image_id'] ?? 0);
      if ($seo_id <= 0) return array();
      $media = $db->select1($this->dd_media, $seo_id);
      if (!is_array($media) || (int)($media['active'] ?? 0) !== 1) return array();
      if (!$this->media_file_exists($media)) return array();
      return $this->normalize_media_row($media);
   }



   private function save_json() {
      $texts = $this->cms_texts();
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? 0);
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      $title = $this->clean_text($payload['title'] ?? '', 254);
      $folder = (int)($payload['folder'] ?? 0);
      $keywords_provided = array_key_exists('keywords', $payload);
      try {
         $permalink = $this->page_permalink($db, $folder, $title, $payload['permalink'] ?? '', $id);
      } catch (\InvalidArgumentException $e) {
         $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'field' => 'permalink',
            'msg' => $e->getMessage(),
         ));
      }
      $data = array(
         'activ' => $this->bool_int($payload['activ'] ?? 1, 1),
         'folder' => $folder,
         'title' => $title,
         'menu_title' => $this->clean_text($payload['menu_title'] ?? '', 96),
         'permalink' => $permalink,
         'description' => $this->clean_text($payload['description'] ?? '', 254),
         'template' => $this->clean_text($payload['template'] ?? 'parent', 254),
         'hero_template' => $this->clean_text($payload['hero_template'] ?? 'parent', 80),
         'hero_image_id' => $this->clean_text($payload['hero_image_id'] ?? 'parent', 32),
         'hero_margin_top' => $this->clean_text($payload['hero_margin_top'] ?? 'parent', 32),
         'hero_height' => $this->clean_text($payload['hero_height'] ?? 'parent', 32),
         'hero_variant' => $this->clean_text($payload['hero_variant'] ?? 'parent', 32),
         'hero_sticky' => $this->clean_text($payload['hero_sticky'] ?? 'parent', 32),
         'hero_scroll_layer' => $this->clean_text($payload['hero_scroll_layer'] ?? 'parent', 32),
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => $this->clean_text($payload['gallery_image_size'] ?? 'original', 32),
         'gallery_lightbox_width' => $this->clean_text($payload['gallery_lightbox_width'] ?? '100vw', 32),
         'gallery_overflow' => $this->clean_text($payload['gallery_overflow'] ?? 'grid', 32),
         'gallery_click_behavior' => $this->clean_text($payload['gallery_click_behavior'] ?? 'lightbox', 32),
         'content' => $this->normalize_and_sanitize_content($payload['content'] ?? ''),
      );
      $data = $this->normalize_hero_payload($data);

      if ($id > 0) {
         $existing_seo = $db->select1(dbxContentLng::dd_content(), $id, 'keywords,meta_robots,seo_title,seo_image_id', 0);
         $data['keywords'] = $keywords_provided
            ? $this->clean_text($payload['keywords'] ?? '', 254)
            : (is_array($existing_seo) ? (string)($existing_seo['keywords'] ?? '') : '');
         $data['meta_robots'] = is_array($existing_seo) ? (string)($existing_seo['meta_robots'] ?? 'index,follow') : 'index,follow';
         $data['seo_title'] = is_array($existing_seo) ? (string)($existing_seo['seo_title'] ?? '') : '';
         $data['seo_image_id'] = is_array($existing_seo) ? max(0, (int)($existing_seo['seo_image_id'] ?? 0)) : 0;
      } else {
         $data['keywords'] = $keywords_provided ? $this->clean_text($payload['keywords'] ?? '', 254) : '';
         $data['meta_robots'] = 'index,follow';
         $data['seo_title'] = '';
         $data['seo_image_id'] = 0;
      }

      try {
         $stored = $this->persistence($db)->save_page(
            $data,
            $id,
            $payload,
            fn($store, $content_id, $folder_id, $hero_id) => $this->sync_saved_hero_media_usage($store, $content_id, $folder_id, $hero_id),
            fn($store, $content_id, $html, $folder_id, $client_ids, $provided) => $this->sync_inline_media_usage($store, $content_id, $html, $folder_id, $client_ids, $provided),
            fn($store, $master_id, $sync) => $this->apply_lng_sync_media($store, $master_id, $sync)
         );
         $saved_id = (int)$stored['saved_id'];
         $sync_result = (array)$stored['sync_result'];
         $inline_sync = (array)$stored['inline_sync'];
      } catch (\Throwable $e) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_save_error')));
      }
      $media = $this->media_usage_rows_for_context($db, $saved_id, 0);
               $saved_row = $db->select1(dbxContentLng::dd_content(), $saved_id);
               if (is_array($saved_row)) {
                  if (isset($saved_row['content'])) {
                     $saved_row['content'] = $this->normalize_content_media_urls($saved_row['content']);
                  }
                  $saved_row = $this->normalize_gallery_row($saved_row);
               }
               $this->cms_json_response(array_merge(array(
                  'ok' => 1,
                  'success' => true,
                  'id' => $saved_id,
                  'row' => $saved_row,
                  'media' => $media,
                  'inline_media_sync' => $inline_sync,
                  'hero_preview_media' => $this->hero_preview_media($db, $saved_row),
                  'hero_parent_preview_media' => $this->inherited_hero_preview_media($db, (int)($saved_row['folder'] ?? 0)),
                  'seo_preview_media' => $this->seo_preview_media($db, $saved_row),
               ), $this->lng_save_response($db, 'page', $saved_id, $sync_result)));
   }



   private function new_page_json() {
      $texts = $this->cms_texts();
      $folder = (int)dbx()->get_modul_var('folder', 0, 'int');
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $content_dd = dbxContentLng::dd_content();
      $title = $texts->format_fd_message('new_page_title', array('time' => date('H:i')));
      $sorter = $this->next_content_sorter($db, $folder);
      try {
         $id = $this->persistence($db)->create_page(array(
            'activ' => 1,
            'folder' => $folder,
            'title' => $title,
            'menu_title' => '',
            'permalink' => dbxContent_permalink::build($db, dbxContentLng::dd_folder(), $folder, $title),
            'template' => 'parent',
            'hero_template' => 'parent',
            'hero_image_id' => 'parent',
            'hero_margin_top' => 'parent',
            'hero_height' => 'parent',
            'hero_variant' => 'parent',
            'hero_sticky' => 'parent',
            'hero_scroll_layer' => 'parent',
            'gallery_template' => 'image-gallery',
            'gallery_visible_count' => '3',
            'gallery_image_size' => 'original',
            'gallery_lightbox_width' => '100vw',
            'gallery_overflow' => 'grid',
            'gallery_click_behavior' => 'lightbox',
            'description' => '',
            'keywords' => '',
            'meta_robots' => 'index,follow',
            'seo_title' => '',
            'seo_image_id' => 0,
            'sorter' => $sorter,
            'content' => '<p>' . $texts->get_fd_message('new_page_content') . '</p>',
         ));
      } catch (\Throwable $e) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_create_error')));
      }
      $this->cms_json_response(array(
         'ok' => 1,
         'success' => true,
         'id' => $id,
         'row' => $db->select1($content_dd, $id),
         'open_lng_provision' => $this->lng_provision_open_flag($db, 'page', $id),
      ));
   }


   private function duplicate_page_json() {
      $texts = $this->cms_texts();
      $payload = $this->request_json();
      $source_id = (int)($payload['id'] ?? dbx()->get_modul_var('id', 0, 'int'));
      if ($source_id <= 0) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_select_first')));
      }
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $content_dd = dbxContentLng::dd_content();
      $source = $db->select1($content_dd, $source_id, '*', 0);
      if (!is_array($source) || (int)($source['id'] ?? 0) !== $source_id) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_not_found')));
      }
      $copy = $source;
      foreach (array(
         'id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner',
         'hits', 'xvote', 'vote', 'vote1', 'vote2', 'vote3', 'vote4', 'vote5', 'lastuservote'
      ) as $field) {
         unset($copy[$field]);
      }

      $folder_id = (int)($source['folder'] ?? 0);
      $title = (string)($source['title'] ?? 'Unbenannte Seite');
      $copy['sorter'] = $this->next_content_sorter($db, $folder_id);
      $copy['permalink'] = $this->duplicate_page_permalink(
         $db,
         $folder_id,
         $title,
         (string)($source['permalink'] ?? '')
      );
      $copy['lng_uid'] = '';
      $copy['lng_sync'] = dbxContentLngSync::is_master_lng() ? 'auto' : 'manual';
      $copy['lng_rev'] = 1;
      $copy['lng_synced_rev'] = 0;
      try {
         $stored = $this->persistence($db)->duplicate_page(
            $copy,
            fn($store, $new_id) => $this->copy_page_media_usage(
               $store, $source_id, $new_id, $folder_id, true, dbxContentLng::current(), dbxContentLng::current()
            ),
            fn($store, $new_id) => $this->sync_inline_media_usage(
               $store, $new_id, (string)($source['content'] ?? ''), $folder_id
            )
         );
         $new_id = (int)$stored['new_id'];
         $media_copied = (int)$stored['media_copied'];
         $inline_sync = (array)$stored['inline_sync'];
      } catch (\Throwable $e) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_duplicate_error')));
      }
      $row = $db->select1($content_dd, $new_id);
      if (is_array($row) && isset($row['content'])) {
         $row['content'] = $this->normalize_content_media_urls($row['content']);
         $row = $this->normalize_gallery_row($row);
      }
      $this->cms_json_response(array(
         'ok' => 1,
         'success' => true,
         'id' => $new_id,
         'source_id' => $source_id,
         'row' => $row,
         'permalink' => (string)($row['permalink'] ?? $copy['permalink']),
         'media_copied' => $media_copied,
         'inline_media_sync' => $inline_sync,
         'open_lng_provision' => $this->lng_provision_open_flag($db, 'page', $new_id),
         'msg' => $texts->get_fd_message('page_duplicated'),
      ));
   }


   private function next_content_sorter($db, $folder_id) {
      $folder_id = (int)$folder_id;
      $rows = $db->select(dbxContentLng::dd_content(), 'folder = ' . $folder_id, '*', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int)($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }



   private function new_folder_json() {
      $texts = $this->cms_texts();
      $parent = (int)dbx()->get_modul_var('parent', 0, 'int');
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $folder_dd = dbxContentLng::dd_folder();
      $sorter = $this->next_folder_sorter($db, $parent);
      try {
         $id = $this->persistence($db)->create_folder(array(
            'name' => $texts->format_fd_message('new_folder_title', array('time' => date('H:i'))),
            'parent_id' => $parent,
            'sorter' => $sorter,
            'group_read' => $parent > 0 ? 'parent' : '*',
            'template' => $parent > 0 ? 'parent' : 'c-content',
            'hero_template' => $parent > 0 ? 'parent' : 'image-hero',
            'hero_image_id' => 'parent',
            'hero_margin_top' => 'parent',
            'hero_height' => 'parent',
            'hero_variant' => 'parent',
            'hero_sticky' => 'parent',
            'hero_scroll_layer' => 'parent',
         ), $parent);
      } catch (\Throwable $e) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_create_error')));
      }
      $this->cms_json_response(array(
         'ok' => 1,
         'success' => true,
         'id' => $id,
         'row' => $db->select1($folder_dd, $id),
         'open_lng_provision' => $this->lng_provision_open_flag($db, 'folder', $id),
      ));
   }



   private function save_folder_json() {
      $texts = $this->cms_texts();
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? 0);
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      $name = $this->clean_text($payload['name'] ?? '', 120);
      $parent_id = (int)($payload['parent_id'] ?? 0);
      if ($name === '') {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_name_required')));
      }
      if ($parent_id < 0) $parent_id = 0;
      if ($id > 0 && $parent_id === $id) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_self_parent')));
      }
      if ($id > 0 && $this->folder_is_descendant($db, $parent_id, $id)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_descendant_parent')));
      }

      $rights = $this->clean_text($payload['group_read'] ?? '', 512);
      if ($rights === '') $rights = $parent_id > 0 ? 'parent' : '*';

      $old = $id > 0 ? $db->select1(dbxContentLng::dd_folder(), $id, '*', 0) : array();
      $data = array(
         'name' => $name,
         'parent_id' => $parent_id,
         'group_read' => $rights,
         'template' => $this->clean_text($payload['template'] ?? 'parent', 254),
         'hero_template' => $this->clean_text($payload['hero_template'] ?? 'parent', 80),
         'hero_image_id' => $this->clean_text($payload['hero_image_id'] ?? 'parent', 32),
         'hero_margin_top' => $this->clean_text($payload['hero_margin_top'] ?? 'parent', 32),
         'hero_height' => $this->clean_text($payload['hero_height'] ?? 'parent', 32),
         'hero_variant' => $this->clean_text($payload['hero_variant'] ?? 'parent', 32),
         'hero_sticky' => $this->clean_text($payload['hero_sticky'] ?? 'parent', 32),
         'hero_scroll_layer' => $this->clean_text($payload['hero_scroll_layer'] ?? 'parent', 32),
      );
      $data = $this->normalize_hero_payload($data);
      if ($id <= 0 || (is_array($old) && (int)($old['parent_id'] ?? 0) !== $parent_id)) {
         $data['sorter'] = $this->next_folder_sorter($db, $parent_id);
      }

      $folder_dd = dbxContentLng::dd_folder();
      try {
         $stored = $this->persistence($db)->save_folder(
            $data,
            $id,
            fn($store, $content_id, $folder_id, $hero_id) => $this->sync_saved_hero_media_usage($store, $content_id, $folder_id, $hero_id)
         );
         $saved_id = (int)$stored['saved_id'];
         $sync_result = (array)$stored['sync_result'];
      } catch (\Throwable $e) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_save_error')));
      }

      $old_parent = is_array($old) ? (int)($old['parent_id'] ?? $parent_id) : $parent_id;
      $this->persistence($db)->flush_saved_folder_cache($saved_id, $parent_id, $old_parent);
      $this->cms_json_response(array_merge(array(
         'ok' => 1,
         'success' => true,
         'id' => $saved_id,
         'row' => $db->select1($folder_dd, $saved_id),
      ), $this->lng_save_response($db, 'folder', $saved_id, $sync_result)));
   }



   private function delete_folder_json() {
      $texts = $this->cms_texts();
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? 0);
      if ($id <= 0) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_none_selected')));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $delete_lngs = $this->normalize_delete_lngs($payload);
      $result = $this->delete_folder_in_lngs($db, $id, $delete_lngs);

      if ((int)($result['ok'] ?? 0) === 1) {
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'id' => $id,
            'deleted' => $result['deleted'] ?? array(),
            'warnings' => $result['errors'] ?? array(),
         ));
      }

      $msg = count($result['errors'] ?? array()) ? implode(' ', $result['errors']) : $texts->get_fd_message('folder_delete_error');
      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $msg));
   }



   private function delete_page_json() {
      $texts = $this->cms_texts();
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? dbx()->get_modul_var('id', 0, 'int'));
      if ($id <= 0) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_none_selected')));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $delete_lngs = $this->normalize_delete_lngs($payload);
      $result = $this->delete_page_in_lngs($db, $id, $delete_lngs);

      if ((int)($result['ok'] ?? 0) === 1) {
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'id' => $id,
            'deleted' => $result['deleted'] ?? array(),
            'warnings' => $result['errors'] ?? array(),
         ));
      }

      $msg = count($result['errors'] ?? array()) ? implode(' ', $result['errors']) : $texts->get_fd_message('page_delete_error');
      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $msg));
   }



   private function move_node_json() {
      $payload = $this->request_json();
      $type = $this->clean_text($payload['type'] ?? '', 16);
      $id = (int)($payload['id'] ?? 0);
      $target = (int)($payload['target_folder'] ?? 0);
      $before_id = (int)($payload['before_id'] ?? 0);
      $after_id = (int)($payload['after_id'] ?? 0);

      if ($id <= 0 || $target < 0 || !in_array($type, array('folder', 'page'), true)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ungueltige Tree-Verschiebung.'));
      }

      if ($type === 'folder' && $id === $target) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner kann nicht in sich selbst verschoben werden.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      try {
         $moved = $this->persistence($db)->move_node($type, $id, $target, $before_id, $after_id);
      } catch (\Throwable $e) {
         $message = $e instanceof \InvalidArgumentException
            ? $e->getMessage()
            : 'Tree-Eintrag konnte nicht atomar verschoben werden.';
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $message));
      }
      $this->cms_json_response(array_merge(array('ok' => 1, 'success' => true), $moved));
   }



   private function folder_is_descendant($db, $folder_id, $ancestor_id) {
      $folder_id = (int)$folder_id;
      $ancestor_id = (int)$ancestor_id;
      $guard = 0;
      while ($folder_id > 0 && $guard < 100) {
         if ($folder_id === $ancestor_id) return true;
         $row = $db->select1(dbxContentLng::dd_folder(), $folder_id);
         if (!is_array($row)) return false;
         $folder_id = (int)($row['parent_id'] ?? 0);
         $guard++;
      }
      return false;
   }



   private function next_folder_sorter($db, $parent_id) {
      $parent_id = (int)$parent_id;
      $rows = $db->select(dbxContentLng::dd_folder(), 'parent_id = ' . $parent_id, '*', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int)($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }



   /**
    * Laedt eine ID-Menge in einer Abfrage und bildet sie nach Primaerschluessel ab.
    * Ungueltige und doppelte IDs werden vor dem SQL entfernt.
    */
   private function rows_by_ids($db, $dd, $ids, $fields = '*') {
      $ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : array()), function($id) {
         return $id > 0;
      })));
      if (!$ids) return array();

      $rows = $db->select($dd, 'id IN (' . implode(',', $ids) . ')', $fields, 'id', 'ASC', '', 0, 0, 0);
      if (!is_array($rows)) return array();

      $map = array();
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         if ($id > 0) $map[$id] = $row;
      }
      return $map;
   }



   private function media_usage_page_map($db, $usage_rows) {
      $map = array();
      if (!is_array($usage_rows)) return $map;

      $page_cache = array();
      $folder_cache = array();

      $page_ids_by_language = array();
      foreach ($usage_rows as $usage) {
         if (!is_array($usage)) continue;
         $content_id = (int)($usage['content_id'] ?? 0);
         if ($content_id <= 0) continue;
         $content_lng = dbxContentMediaUsageScope::language((string)($usage['content_lng'] ?? ''));
         $page_ids_by_language[$content_lng][$content_id] = $content_id;
      }
      foreach ($page_ids_by_language as $content_lng => $page_ids) {
         foreach ($this->rows_by_ids($db, dbxContentLng::dd_content($content_lng), array_values($page_ids), 'id,folder,title') as $id => $page) {
            $page_cache[$content_lng . ':' . $id] = $page;
         }
      }

      $folder_ids_by_language = array();
      foreach ($usage_rows as $usage) {
         if (!is_array($usage)) continue;
         $content_id = (int)($usage['content_id'] ?? 0);
         $content_lng = dbxContentMediaUsageScope::language((string)($usage['content_lng'] ?? ''));
         $page = $page_cache[$content_lng . ':' . $content_id] ?? array();
         $folder_id = (int)($page['folder'] ?? ($usage['folder_id'] ?? 0));
         if ($folder_id > 0) $folder_ids_by_language[$content_lng][$folder_id] = $folder_id;
      }
      foreach ($folder_ids_by_language as $content_lng => $folder_ids) {
         foreach ($this->rows_by_ids($db, dbxContentLng::dd_folder($content_lng), array_values($folder_ids), 'id,name,title') as $id => $folder) {
            $folder_cache[$content_lng . ':' . $id] = $folder;
         }
      }

      foreach ($usage_rows as $usage) {
         if (!is_array($usage)) continue;
         $media_id = (int)($usage['media_id'] ?? 0);
         $content_id = (int)($usage['content_id'] ?? 0);
         $content_lng = dbxContentMediaUsageScope::language((string)($usage['content_lng'] ?? ''));
         if ($media_id <= 0 || $content_id <= 0) continue;

         $page_key = $content_lng . ':' . $content_id;
         $page = is_array($page_cache[$page_key] ?? null) ? $page_cache[$page_key] : array();
         $folder_id = (int)($page['folder'] ?? ($usage['folder_id'] ?? 0));

         $folder_key = $content_lng . ':' . $folder_id;
         $folder = ($folder_id > 0 && is_array($folder_cache[$folder_key] ?? null)) ? $folder_cache[$folder_key] : array();

         if (!isset($map[$media_id])) $map[$media_id] = array();
         if (!isset($map[$media_id][$page_key])) {
            $map[$media_id][$page_key] = array(
               'id' => $content_id,
               'content_id' => $content_id,
               'content_lng' => $content_lng,
               'title' => (string)($page['title'] ?? ''),
               'folder_id' => $folder_id,
               'folder_title' => (string)($folder['name'] ?? $folder['title'] ?? ''),
               'slots' => array(),
            );
         }

         $slot = trim((string)($usage['slot'] ?? ''));
         if ($slot !== '' && !in_array($slot, $map[$media_id][$page_key]['slots'], true)) {
            $map[$media_id][$page_key]['slots'][] = $slot;
         }
      }

      foreach ($map as $media_id => $items) {
         ksort($items, SORT_NUMERIC);
         $map[$media_id] = array_values($items);
      }

      return $map;
   }
}
