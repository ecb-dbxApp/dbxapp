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
 * Gemeinsamer CMS-Kontext, Sicherheit, Cache, HTML-Normalisierung und Permalinks.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsCoreServiceTrait {


   /** Eine Persistenzinstanz pro Request und dbxDB-Verbindung. */
   private function persistence($db = null): dbxContentCmsPersistenceService {
      if (!$this->persistenceService) {
         $db ??= dbx()->get_system_obj('dbxDB');
         $this->persistenceService = new dbxContentCmsPersistenceService($db, $this->dd_media, $this->dd_media_usage);
      }
      return $this->persistenceService;
   }



   /**
    * Sprachabhängige CMS-Texte aus der zentralen FD.
    *
    * Das getrennte Objekt verhindert, dass die drei auf derselben CMS-Seite
    * gerenderten dbxForm-Instanzen sich gegenseitig den aktiven FD-Kontext
    * überschreiben.
    */
   private function cms_texts() {
      if ($this->cmsTexts) return $this->cmsTexts;
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('cms-page-texts');
      $texts->_fd = 'dbxContent_admin|cms-page';
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->cmsTexts = $texts;
      return $this->cmsTexts;
   }



   /** Gemeinsamer Optionskatalog für alle CMS-Formularbereiche. */
   private function cms_options(): dbxContentCmsOptionCatalog {
      if (!$this->cmsOptionCatalog) {
         $this->cmsOptionCatalog = new dbxContentCmsOptionCatalog($this->cms_texts());
      }
      return $this->cmsOptionCatalog;
   }



   /**
    * Verbindlicher Feldvertrag fuer alle mit dbxForm gerenderten CMS-Bereiche.
    *
    * Der Feldname bleibt ein data-Attribut fuer JavaScript. CSS erhaelt keine
    * feldspezifischen Klassen mehr; der Scope trennt gleichzeitig Seite,
    * Einstellungen, Ordner und weitere Formulare mit gleichnamigen Feldern.
    */
   private function cms_field_data(string $name, string $scope): array {
      return array(
         'cms_field' => $name,
         'cms_field_scope' => $scope,
      );
   }



   /**
    * Uebergibt ausgewaehlte FD-Texte als sichere dbxTPL-Replacements an dbxForm.
    */
   private function add_form_message_replaces($form, array $keys): void {
      foreach ($keys as $key) {
         $form->add_rep($key, dbx()->esc($form->get_fd_message($key)));
      }
   }



   private function cms_js_messages(): string {
      $texts = $this->cms_texts();
      $keys = array(
         'page_select_first', 'page_loading', 'page_load_error',
         'page_saved', 'page_save_error', 'page_deleted', 'page_delete_error',
         'page_duplicating', 'page_duplicated', 'page_duplicate_error',
         'folder_selected', 'folder_edit', 'folder_saved', 'folder_save_error',
         'folder_delete_error', 'folder_select_first', 'folder_name_required',
         'unsaved', 'saved', 'unsaved_title', 'saved_title',
         'page_save_title', 'folder_save_title', 'page_delete_title',
         'folder_delete_title', 'duplicate_title', 'duplicate_select_title',
         'selection_label', 'tree_show', 'tree_hide', 'right_show', 'right_hide',
         'editor_marker_tooltip', 'editor_media_tooltip', 'editor_module_tooltip',
         'editor_bootstrap_tooltip', 'editor_text_format', 'editor_horizontal_rule',
         'editor_columns_two', 'editor_columns_three',
         'editor_columns_first', 'editor_columns_second', 'editor_columns_third',
         'editor_columns_new', 'editor_columns_stacked', 'editor_columns_responsive',
         'editor_column_add', 'editor_columns_dissolve',
         'editor_context_menu', 'editor_context_undo', 'editor_context_redo',
          'editor_context_select_all', 'editor_context_block_up', 'editor_context_block_down',
          'editor_context_module', 'editor_context_video', 'editor_image_edit', 'editor_image_remove',
          'editor_context_copy',
         'editor_context_cut', 'editor_context_paste', 'editor_context_delete',
         'editor_save_all', 'editor_bold', 'editor_italic', 'editor_underline',
         'editor_strike', 'editor_align_left', 'editor_align_center',
         'editor_align_right', 'editor_align_justify', 'editor_print_break',
         'hero_parent_empty', 'hero_preview_empty', 'hero_preview_loading',
         'hero_preview_not_image', 'hero_image_alt',
         'content_template_edit_title', 'content_template_edit_aria',
         'content_template_select_first', 'content_template_confirm_question',
         'content_template_confirm_hint', 'content_template_confirm_yes',
         'content_template_open_error', 'cancel_label',
          'media_inline_empty', 'media_inline_focus', 'media_inline_edit',
          'media_inline_remove', 'media_inline_removed', 'media_gallery_empty', 'media_hero_empty',
         'media_area_empty', 'media_all_folders', 'media_folders_title',
         'media_no_folders', 'media_folder_empty', 'media_back',
         'media_folder_instruction', 'media_folder_label', 'media_count',
         'media_view', 'media_view_small', 'media_view_medium', 'media_view_large',
      );
      $messages = array();
      foreach ($keys as $key) $messages[$key] = $texts->get_fd_message($key);
      return json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }



   private function cms_json_response(array $data): void {
      if ($this->cms_form_security) {
         $data['form_security'] = $this->cms_form_security;
      }
      dbx()->json_response($data, true);
   }



   /**
    * Liefert die gemeinsame dbxForm-Fabrik fuer den CMS-Medienbrowser.
    */
   private function media_forms(): dbxContentMediaForms {
      return dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin');
   }



   /**
    * Prueft den dbxForm-Token eines Medienbrowser-POSTs.
    *
    * Der von dbxForm rotierte Folgetoken wird an cms_json_response()
    * weitergereicht, damit cms.js Mehrfachuploads ohne Seitenreload fortsetzen
    * kann.
    */
   private function verify_media_form(string $kind): bool {
      $action = $this->base_url($kind === 'external' ? 'cms_external_video' : 'cms_upload');
      $state = $this->media_forms()->verify($kind, $action);
      $this->cms_form_security = is_array($state['security'] ?? null) ? $state['security'] : array();
      return !empty($state['submitted']);
   }



   private function lng_provision_open_flag($db, string $entity, int $id): int {
      return $this->persistence($db)->languageProvisionOpenFlag($entity, $id);
   }



   /** Gemeinsame Sprachmetadaten fuer Seiten- und Ordner-Speicherantworten. */
   private function lng_save_response($db, string $entity, int $id, array $syncResult): array {
      return $this->persistence($db)->languageSaveResponse($entity, $id, $syncResult);
   }


   private function apply_cms_lng_context(): void {
      $lng = strtolower(trim((string) dbx()->get_request_var('dbx_lng', '')));
      if ($lng === '') {
         return;
      }

      $allowed = dbxContentLngSync::accessibleLngs();
      if (!in_array($lng, $allowed, true)) {
         return;
      }

      dbx()->set_system_var('dbx_lng', $lng);
      dbx()->set_remember_var('dbx_lng', $lng, 'dbx');
   }



   private function ini_size_bytes($value) {
      $value = trim((string)$value);
      if ($value === '') return 0;
      $unit = strtolower(substr($value, -1));
      $num = (float)$value;
      if ($unit === 'g') $num *= 1024 * 1024 * 1024;
      elseif ($unit === 'm') $num *= 1024 * 1024;
      elseif ($unit === 'k') $num *= 1024;
      return (int)$num;
   }



   private function upload_max_bytes() {
      $upload = $this->ini_size_bytes(ini_get('upload_max_filesize'));
      $post = $this->ini_size_bytes(ini_get('post_max_size'));
      if ($upload <= 0) return $post;
      if ($post <= 0) return $upload;
      return min($upload, $post);
   }



   private function base_url($action, $params = array()) {
      $url = $this->app_url() . '?dbx_modul=dbxContent_admin&dbx_run1=' . rawurlencode((string)$action);
      if ($this->tokenized_action((string)$action)) {
         $params['dbx_token'] = dbx()->action_token(self::ACTION_TOKEN_SCOPE);
      }
      return dbx()->append_url_params($url, $params);
   }



   /**
    * Aktionen, deren gerenderte Endpunkte einen Token benoetigen.
    *
    * cms_media ist grundsaetzlich lesend, kann mit sync=1 jedoch Dateien
    * einlesen und Datensaetze anlegen. Deshalb bekommt auch dieser gemischte
    * Endpunkt einen Token; serverseitig wird er nur fuer sync=1 verlangt.
    */
   private function tokenized_action(string $action): bool {
      return !empty($this->cms_actions()[$action]['token']);
   }



   /** @return array<string,array<string,mixed>> */
   private function cms_actions(): array {
      return dbx()->get_system_obj('dbxActionManifest')
         ->module('dbxContent_admin', 'cms-actions');
   }



   /** Liefert true, wenn die Aktion zum kanonischen CMS-Vertrag gehoert. */
   public function supports_action(string $action): bool {
      return $action === 'cms' || isset($this->cms_actions()[$action]);
   }



   private function action_requires_token(string $action): bool {
      if ($action === 'cms_media') {
         return (int)dbx()->get_modul_var('sync', 0, 'int') === 1;
      }
      return $this->tokenized_action($action);
   }



   private function check_action_token(string $action): bool {
      $token = (string)dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
      if (dbx()->check_action_token(self::ACTION_TOKEN_SCOPE, $token)) {
         return true;
      }

      dbx()->sys_msg(
         'security',
         'dbxContent_admin',
         $action,
         'CMS-Aktion ohne gueltigen Token abgewiesen',
         'ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
      );
      return false;
   }



   private function reject_action_token(string $action) {
      http_response_code(403);
      $message = 'Die Aktion wurde aus Sicherheitsgruenden abgewiesen. Bitte laden Sie das CMS neu.';
      if ($action === 'cms_media_process') {
         return '<div class="alert alert-danger m-3">' . dbx()->esc($message) . '</div>';
      }
      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $message));
      return '';
   }



   private function app_url(): string {
      $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
      if ($script === '') {
         return '';
      }

      $dir = str_replace('\\', '/', dirname($script));
      if ($dir === '.' || $dir === '/' || $dir === '\\') {
         return '/';
      }

      return rtrim($dir, '/') . '/';
   }






   private function flush_saved_page_cache($db, int $cid): void {
      $this->persistence($db)->flushSavedPageCache($cid);
   }






   private function copy_page_media_usage(
      $db,
      int $sourceCid,
      int $targetCid,
      int $targetFolderId,
      bool $replace = true,
      string $sourceLng = '',
      string $targetLng = ''
   ): int {
      $sourceCid = (int) $sourceCid;
      $targetCid = (int) $targetCid;
      $targetFolderId = (int) $targetFolderId;
      $sourceLng = dbxContentMediaUsageScope::language($sourceLng);
      $targetLng = dbxContentMediaUsageScope::language($targetLng);
      if ($sourceCid <= 0 || $targetCid <= 0 || !is_object($db)) {
         return 0;
      }

      $copySlots = array('hero', 'gallery', 'header', 'teaser', 'footer');
      if ($replace) {
         foreach ($copySlots as $slot) {
            $db->update(
               $this->dd_media_usage,
               array('active' => 0),
               dbxContentMediaUsageScope::withLanguage(
                  "content_id = " . $targetCid . " AND slot = '" . str_replace("'", "''", $slot) . "' AND active = 1",
                  $targetLng
               ),
               0,
               1,
               1,
               0
            );
         }
      }

      $rows = $db->select(
         $this->dd_media_usage,
         dbxContentMediaUsageScope::withLanguage(
            "content_id = " . $sourceCid . " AND active = 1 AND slot IN ('hero','gallery','header','teaser','footer')",
            $sourceLng
         ),
         '*',
         'slot,sorter,id',
         'ASC',
         '',
         0,
         0,
         0
      );
      if (!is_array($rows)) {
         return 0;
      }

      $copied = 0;
      foreach ($rows as $usage) {
         if (!is_array($usage)) {
            continue;
         }
         $mediaId = (int) ($usage['media_id'] ?? 0);
         if ($mediaId <= 0) {
            continue;
         }
         $media = $db->select1($this->dd_media, $mediaId, 'active', 0);
         if (!is_array($media) || (int) ($media['active'] ?? 0) !== 1) {
            continue;
         }

         $slot = $this->valid_media_slot($usage['slot'] ?? 'gallery');
         if ($slot === 'inline') {
            continue;
         }

         $usageId = $this->create_media_usage(
            $db,
            $mediaId,
            $targetCid,
            $targetFolderId,
            $slot,
            (string) ($usage['template'] ?? ''),
            (string) ($usage['caption'] ?? ''),
            (string) ($usage['settings'] ?? ''),
            $targetLng
         );
         if ($usageId > 0) {
            $copied++;
         }
      }

      return $copied;
   }



   private function sync_lng_page_media_from_master($db, int $masterCid, int $slaveCid, string $lng): int {
      $masterCid = (int) $masterCid;
      $slaveCid = (int) $slaveCid;
      $lng = strtolower(trim($lng));
      if ($masterCid <= 0 || $slaveCid <= 0 || $lng === '' || !is_object($db)) {
         return 0;
      }

      $slaveRow = $db->select1(dbxContentLng::ddContent($lng), $slaveCid, 'content,folder', 0);
      if (!is_array($slaveRow)) {
         return 0;
      }

      $slaveFolder = (int) ($slaveRow['folder'] ?? 0);
      $copied = $this->copy_page_media_usage(
         $db,
         $masterCid,
         $slaveCid,
         $slaveFolder,
         true,
         dbxContentLngSync::masterLng(),
         $lng
      );
      $this->sync_inline_media_usage($db, $slaveCid, (string) ($slaveRow['content'] ?? ''), $slaveFolder, null, false, $lng);
      return $copied;
   }



   private function apply_lng_sync_media($db, int $masterCid, array $syncResult): int {
      $master = dbxContentLngSync::masterLng();
      $copied = 0;
      foreach (($syncResult['updated'] ?? array()) as $item) {
         if (!is_array($item) || (($item['entity'] ?? 'page') !== 'page')) {
            continue;
         }
         $lng = strtolower(trim((string) ($item['lng'] ?? '')));
         $slaveId = (int) ($item['id'] ?? 0);
         if ($slaveId <= 0 || $lng === '' || $lng === $master) {
            continue;
         }
         $copied += $this->sync_lng_page_media_from_master($db, $masterCid, $slaveId, $lng);
      }
      return $copied;
   }



   private function apply_lng_provision_media($db, string $type, int $masterId, array $result): int {
      if ($type !== 'page' || !is_object($db)) {
         return 0;
      }

      $copied = 0;
      $items = array_merge(
         is_array($result['created'] ?? null) ? $result['created'] : array(),
         is_array($result['updated'] ?? null) ? $result['updated'] : array()
      );
      foreach ($items as $item) {
         if (!is_array($item)) {
            continue;
         }
         $lng = strtolower(trim((string) ($item['lng'] ?? '')));
         $slaveId = (int) ($item['id'] ?? 0);
         if ($slaveId <= 0 || $lng === '') {
            continue;
         }
         $copied += $this->sync_lng_page_media_from_master($db, $masterId, $slaveId, $lng);
      }
      return $copied;
   }











   private function flush_menu_cache(): void {
      $this->persistence()->flushMenuCache();
   }






   private function flush_media_cache($db, int $content_id = 0, int $folder_id = 0): void {
      $this->persistence($db)->flushMediaCache($content_id, $folder_id);
   }



   private function flush_media_by_media_id($db, int $media_id): void {
      $this->persistence($db)->flushMediaByMediaId($media_id);
   }



   private function request_json() {
      return dbx()->get_json_request(true);
   }



   private function normalize_content_media_urls($html) {
      $html = (string)$html;
      $html = preg_replace_callback('/<figure\b([^>]*dbx-cms-inline-video-block[^>]*)>[\s\S]*?<\/figure>/i', function($m) {
         $attrs = $m[1];
         if (!preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $attrs, $id_match)
            && !preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $m[0], $id_match)
            && !preg_match('/dbx_mid=([0-9]+)/i', $m[0], $id_match)) return $m[0];
         $id = (int)$id_match[1];
         $placeholder = $this->inline_media_placeholder_html($id);
         if ($placeholder === '') return $m[0];
         $attrs = preg_replace('/\scontenteditable=(["\']).*?\1/i', '', $attrs);
         $attrs = preg_replace('/\sdata-cms-media-id\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs);
         $attrs .= ' data-cms-media-id="' . $id . '"';
         if (stripos($attrs, 'data-cms-media-slot=') === false) $attrs .= ' data-cms-media-slot="inline"';
         return '<figure' . $attrs . '>' . $placeholder . '</figure>';
      }, $html);
      /*
       * Alte CMS-Seiten konnten Videos als freies <video><source ...></video>
       * enthalten. Der Editor arbeitet inzwischen mit stabilen, über die
       * Medien-ID gerenderten Platzhaltern. Die Konvertierung hält bestehende
       * Inhalte beim nächsten Speichern kompatibel.
       */
      $html = preg_replace_callback('/<video\b([^>]*)>([\s\S]*?)<\/video>/i', function($m) {
         $attrs = (string)($m[1] ?? '');
         $inner = (string)($m[2] ?? '');
         $source = $attrs . ' ' . $inner;
         $id = 0;
         if (preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $source, $id_match)
            || preg_match('/dbx_mid=([0-9]+)/i', $source, $id_match)) {
            $id = (int)($id_match[1] ?? 0);
         }
         if ($id <= 0) return $m[0];

         $placeholder = $this->inline_media_placeholder_html($id);
         if ($placeholder === '') return $m[0];

         $video_attrs = ' class="dbx-cms-inline-media dbx-cms-inline-video-block"'
            . ' data-cms-media-id="' . $id . '" data-cms-media-slot="inline"';
         foreach (array('autoplay', 'loop', 'muted') as $option) {
            $enabled = preg_match('/(?:^|\s)' . $option . '(?:\s|=|$)/i', $attrs) === 1;
            $video_attrs .= ' data-cms-video-' . $option . '="' . ($enabled ? '1' : '0') . '"';
         }
         return '<figure' . $video_attrs . '>' . $placeholder . '</figure>';
      }, $html);
      $html = preg_replace('/<(video|iframe|source)\b[^>]*data-cms-media-id=["\']?([0-9]+)[^>]*>(?:\s*<\/\1>)?/i', '', $html);
      $html = preg_replace_callback('/<(img|video|iframe|source)\b([^>]*?)>/i', function($m) {
         $tag = $m[0];
         $tag_name = strtolower((string)($m[1] ?? ''));
         $id = 0;
         if (preg_match('/dbx_mid=([0-9]+)/i', $tag, $id_match)) {
            $id = (int)$id_match[1];
            $tag = preg_replace('/\sdata-cms-media-id\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $tag);
            $tag = preg_match('/\/\s*>$/', $tag)
               ? preg_replace('/\s*\/\s*>$/', ' data-cms-media-id="' . $id . '" />', $tag)
               : preg_replace('/\s*>$/', ' data-cms-media-id="' . $id . '">', $tag);
         } elseif (preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $tag, $id_match)) {
            $id = (int)$id_match[1];
         }
         if ($tag_name === 'img' && $id > 0 && !$this->inline_media_available($id)) {
            return $this->inline_media_missing_html($id);
         }
         if (stripos($tag, 'data-cms-media-slot=') === false && $id > 0) {
            $tag = preg_match('/\/\s*>$/', $tag)
               ? preg_replace('/\s*\/\s*>$/', ' data-cms-media-slot="inline" />', $tag)
               : preg_replace('/\s*>$/', ' data-cms-media-slot="inline">', $tag);
         }
         return $tag;
      }, $html);
      $html = preg_replace_callback('/<(p|figure|div)\b([^>]*\bclass\s*=\s*(?:"[^"]*\bdbx-cms-inline-media\b[^"]*"|\'[^\']*\bdbx-cms-inline-media\b[^\']*\')[^>]*)>([\s\S]*?)<\/\1>/i', function($m) {
         $tag = (string)($m[1] ?? 'p');
         $attrs = (string)($m[2] ?? '');
         $inner = (string)($m[3] ?? '');
         if (stripos($attrs, 'dbx-cms-inline-video-block') !== false) {
            return $m[0];
         }
         if (!preg_match('/<img\b[^>]*\bsrc\s*=\s*(?:"[^"]*dbx_mid=([0-9]+)[^"]*"|\'[^\']*dbx_mid=([0-9]+)[^\']*\')/i', $inner, $id_match)) {
            return $m[0];
         }
         $id = (int)(($id_match[1] ?? 0) ?: ($id_match[2] ?? 0));
         if ($id <= 0) return $m[0];
         $attrs = preg_replace('/\sdata-cms-media-id\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs);
         if (stripos($attrs, 'data-cms-media-slot=') === false) $attrs .= ' data-cms-media-slot="inline"';
         $inner = preg_replace_callback('/<img\b([^>]*)>/i', function($img) use ($id) {
            $img_attrs = (string)($img[1] ?? '');
            if (!preg_match('/\bsrc\s*=\s*(?:"[^"]*dbx_mid=' . $id . '\b[^"]*"|\'[^\']*dbx_mid=' . $id . '\b[^\']*\')/i', $img_attrs)) {
               return $img[0];
            }
            $img_attrs = preg_replace('/\sdata-cms-media-id\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $img_attrs);
            if (stripos($img_attrs, 'data-cms-media-slot=') === false) $img_attrs .= ' data-cms-media-slot="inline"';
            return '<img' . $img_attrs . ' data-cms-media-id="' . $id . '">';
         }, $inner, 1);
         return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
      }, $html);
      $html = $this->strip_empty_inline_media_wrappers($html);
      $html = str_replace(
         array(
            '?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid=',
            '?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=',
            'index.phpindex.php?',
         ),
         array(
            'index.php?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid=',
            'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=',
            'index.php?',
         ),
         $html
      );
      return $html;
   }



   private function content_node_has_visible_value($node): bool {
      foreach ($node->childNodes as $child) {
         if ($child instanceof \DOMText) {
            $value = preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', (string)$child->nodeValue);
            if ($value !== '') return true;
            continue;
         }

         if (!($child instanceof \DOMElement)) continue;
         $tag = strtolower($child->tagName);
         if (in_array($tag, array('br', 'wbr'), true)) continue;
         if (in_array($tag, array('img', 'video', 'audio', 'iframe', 'object', 'embed', 'table', 'hr', 'svg', 'canvas', 'input', 'textarea', 'select', 'button'), true)) {
            return true;
         }
         if ($this->content_node_has_visible_value($child)) return true;
      }
      return false;
   }



   private function strip_empty_content_paragraphs_fallback(string $html): string {
      $pattern = '#<p\b[^>]*>(?:\s|&nbsp;|&#0*160;|&#x0*a0;|<br\b[^>]*>|<wbr\b[^>]*>|<!--[\s\S]*?-->)*</p>#iu';
      do {
         $clean = preg_replace($pattern, '', $html);
         if ($clean === null || $clean === $html) break;
         $html = $clean;
      } while (true);
      return $html;
   }



   private function safe_content_style(string $style, string $tag, string $className = ''): string {
      $tag = strtolower(trim($tag));
      $className = strtolower(trim($className));
      $isMediaLayout = in_array($tag, array('img', 'video', 'iframe', 'figure'), true)
         || preg_match('/(?:^|\s)dbx-cms-inline-(?:media|image|video|video-block)(?:\s|$)/', $className);
      $safe = array();

      foreach (explode(';', $style) as $declaration) {
         if (strpos($declaration, ':') === false) continue;
         list($property, $value) = array_map('trim', explode(':', $declaration, 2));
         $property = strtolower($property);
         $value = strtolower($value);
         if ($property === '' || $value === '') continue;

         if ($property === 'text-align' && in_array($value, array('left', 'right', 'center', 'justify', 'start', 'end'), true)) {
            $safe[$property] = $value;
            continue;
         }

         // Jodits Farbwerkzeug schreibt Text- und Fuellfarben als Inline-CSS.
         // Zugelassen werden nur die von der Palette erzeugten Hex-/RGB-Werte;
         // Funktions- oder URL-Ausdruecke bleiben damit ausgeschlossen.
         if (in_array($property, array('color', 'background-color'), true)
            && (preg_match('/^(?:#[0-9a-f]{3,4}|#[0-9a-f]{6}|#[0-9a-f]{8})$/', $value)
               || preg_match('/^rgba?\(\s*[0-9.%+\-\s,\/]+\)$/', $value)
               || in_array($value, array('transparent', 'currentcolor'), true))) {
            $safe[$property] = $value;
            continue;
         }

         if (!$isMediaLayout) continue;
         if ($property === 'float' && in_array($value, array('left', 'right', 'none'), true)) {
            $safe[$property] = $value;
            continue;
         }
         if ($property === 'display' && in_array($value, array('block', 'inline-block'), true)) {
            $safe[$property] = $value;
            continue;
         }
         if (in_array($property, array('width', 'height', 'max-width', 'max-height'), true)
            && preg_match('/^(?:auto|0|(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)(?:px|%|vw|vh|rem|em))$/', $value)) {
            $safe[$property] = $value;
            continue;
         }
         if (in_array($property, array('margin-left', 'margin-right'), true)
            && preg_match('/^(?:auto|0|(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)(?:px|%|rem|em))$/', $value)) {
            $safe[$property] = $value;
         }
      }

      $result = '';
      foreach ($safe as $property => $value) {
         $result .= $property . ': ' . $value . '; ';
      }
      return trim($result);
   }



   private function sanitize_content_html($html) {
      $html = (string)$html;
      if ($html === '') {
         return '';
      }

      $html = preg_replace('#<(script|style|object|embed|base|meta|link)\b[^>]*>.*?</\1>#is', '', $html);
      $html = preg_replace('#<(script|style|object|embed|base|meta|link)\b[^>]*\/?>#is', '', $html);

      if (!class_exists('\DOMDocument')) {
         $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/is', '', $html);
         $html = preg_replace('/\s+style\s*=\s*(["\']).*?\1/is', '', $html);
         $html = preg_replace('/\s+(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/is', '', $html);
         return $this->strip_empty_content_paragraphs_fallback($html);
      }

      $previous = libxml_use_internal_errors(true);
      $doc = new \DOMDocument('1.0', 'UTF-8');
      $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="dbx_cms_sanitize_root">' . $html . '</div></body></html>';
      $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
      libxml_clear_errors();
      libxml_use_internal_errors($previous);

      $root = $doc->getElementById('dbx_cms_sanitize_root');
      if (!$root) {
         return $html;
      }

       $walker = function($node) use (&$walker) {
          if ($node instanceof \DOMElement) {
             $remove = array();
             $safeStyle = '';
             foreach ($node->attributes as $attr) {
                $name = strtolower($attr->name);
                $value = trim((string)$attr->value);
                if ($name === 'style') {
                   $safeStyle = $this->safe_content_style(
                      $value,
                      (string)$node->tagName,
                      (string)$node->getAttribute('class')
                   );
                   $remove[] = $attr->name;
                   continue;
                }
               if (strpos($name, 'on') === 0) {
                  $remove[] = $attr->name;
                  continue;
               }
               if (($name === 'href' || $name === 'src' || $name === 'xlink:href') && preg_match('/^\s*javascript:/i', $value)) {
                  $remove[] = $attr->name;
                  continue;
               }
               if ($name === 'data-dbx') {
                  if (stripos($value, 'lib=openWin') === false || stripos($value, 'javascript:') !== false) {
                     $remove[] = $attr->name;
                  }
               }
            }
            foreach ($remove as $name) {
               $node->removeAttribute($name);
            }
             if ($safeStyle !== '') {
                $node->setAttribute('style', $safeStyle);
             }
          }

         foreach (iterator_to_array($node->childNodes) as $child) {
            $walker($child);
         }
      };
      $walker($root);

      $emptyParagraphs = array();
      foreach ($root->getElementsByTagName('p') as $paragraph) {
         if (!$this->content_node_has_visible_value($paragraph)) {
            $emptyParagraphs[] = $paragraph;
         }
      }
      foreach ($emptyParagraphs as $paragraph) {
         if ($paragraph->parentNode) $paragraph->parentNode->removeChild($paragraph);
      }

      $out = '';
      foreach ($root->childNodes as $child) {
         $out .= $doc->saveHTML($child);
      }
      return $out;
   }



   private function normalize_and_sanitize_content($html) {
      return $this->sanitize_content_html($this->normalize_content_media_urls($html));
   }



   private function inline_media_available($id) {
      $id = (int)$id;
      if ($id <= 0) return false;
      $db = dbx()->get_system_obj('dbxDB');
      $row = $db->select1($this->dd_media, $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) return false;
      return $this->media_file_exists($row);
   }



   private function inline_media_missing_html($id) {
      $id = (int)$id;
      if ($id <= 0) return '';
      $label = 'Mediendatei #' . $id . ' nicht verfuegbar';
      return '<p class="dbx-cms-inline-media dbx-cms-inline-media-missing-wrap" data-cms-media-id="' . $id . '" data-cms-media-slot="inline" contenteditable="false" tabindex="0" title="Fehlende Mediendatei auswählen, Entf zum Löschen"><span class="dbx-cms-inline-media-missing" aria-hidden="true">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></p>';
   }



   private function inline_media_placeholder_html($id) {
      $id = (int)$id;
      if ($id <= 0) return '';
      $db = dbx()->get_system_obj('dbxDB');
      $row = $db->select1($this->dd_media, $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) return '';
      $row = $this->normalize_media_row($row);
      $title = htmlspecialchars((string)($row['title'] ?? $row['alt'] ?? $row['file_name'] ?? 'Video'), ENT_QUOTES, 'UTF-8');
      $attr = ' data-cms-media-id="' . $id . '" data-cms-media-slot="inline"';
      if (!in_array($this->media_type($row), array('video', 'external_video'), true)) return '';
      $thumb = htmlspecialchars((string)($row['thumb_url'] ?? ''), ENT_QUOTES, 'UTF-8');
      if ($thumb !== '') return '<img class="dbx-cms-inline-video-thumb" src="' . $thumb . '" alt="' . $title . '" title="' . $title . '"' . $attr . '><span class="dbx-cms-inline-video-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>';
      return '<span class="dbx-cms-inline-video-empty"' . $attr . '>' . $title . '</span><span class="dbx-cms-inline-video-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>';
   }



   private function inline_media_embed_html(array $row) {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) return '';
      $row = $this->normalize_media_row($row);
      $attr = ' data-cms-media-id="' . $id . '" data-cms-media-slot="inline" contenteditable="false" tabindex="0"';
      if (in_array($this->media_type($row), array('video', 'external_video'), true)) {
         $inner = $this->inline_media_placeholder_html($id);
         return $inner !== '' ? '<figure class="dbx-cms-inline-media dbx-cms-inline-video-block"' . $attr . '>' . $inner . '</figure><p></p>' : '';
      }
      $url = htmlspecialchars((string)($row['url'] ?? $this->media_url($row)), ENT_QUOTES, 'UTF-8');
      if ($url === '') return '';
      $alt = htmlspecialchars((string)($row['alt'] ?? $row['title'] ?? $row['file_name'] ?? ''), ENT_QUOTES, 'UTF-8');
      $title = htmlspecialchars((string)($row['title'] ?? $row['alt'] ?? $row['file_name'] ?? ''), ENT_QUOTES, 'UTF-8');
      return '<p class="dbx-cms-inline-media"' . $attr . '><img class="dbx-cms-inline-image" src="' . $url . '" alt="' . $alt . '" title="' . $title . '"' . $attr . '></p><p></p>';
   }



   private function bool_int($value, $default = 1) {
      if ($value === '' || $value === null) return $default;
      return (int)((string)$value === '1' || $value === 1 || $value === true || $value === 'on');
   }



   private function clean_text($value, $max = 254) {
      $value = trim((string)$value);
      if ($max > 0 && strlen($value) > $max) {
         $value = substr($value, 0, $max);
      }
      return $value;
   }



   private function slug($text) {
      return dbxContent_permalink::slug($text);
   }



   private function page_permalink($db, $folder_id, $title, $permalink, int $excludeId = 0) {
      $permalink = trim($this->clean_text($permalink, 254));
      if ($permalink === '') {
         return dbxContent_permalink::build($db, dbxContentLng::ddFolder(), $folder_id, $title, $excludeId);
      }
      if (!dbxContent_permalink::isValid($permalink)) {
         throw new \InvalidArgumentException('Permalink: nur Kleinbuchstaben, Zahlen und einzelne Bindestriche sind erlaubt.');
      }
      if (dbxContent_permalink::exists($db, dbxContentLng::ddContent(), $permalink, $excludeId)) {
         throw new \InvalidArgumentException('Dieser Permalink wird bereits von einer anderen Seite verwendet.');
      }
      return $permalink;
   }



   private function page_permalink_exists($db, string $permalink, int $excludeId = 0): bool {
      $permalink = dbxContent_permalink::normalize($permalink);
      return dbxContent_permalink::exists($db, dbxContentLng::ddContent(), $permalink, $excludeId);
   }



   private function duplicate_page_permalink($db, int $folderId, string $title, string $permalink): string {
      $base = dbxContent_permalink::normalize($this->clean_text($permalink, 254));
      if ($base === '') {
         $base = dbxContent_permalink::build($db, dbxContentLng::ddFolder(), $folderId, $title);
      }
      if ($base === '') {
         $base = 'seite';
      }

      $copyNumber = 1;
      do {
         $suffix = $copyNumber === 1 ? '-kopie' : '-kopie-' . $copyNumber;
         $maxBaseLength = max(1, 254 - strlen($suffix));
         $candidateBase = rtrim(substr($base, 0, $maxBaseLength), '/-');
         if ($candidateBase === '') {
            $candidateBase = 'seite';
         }
         $candidate = dbxContent_permalink::normalize($candidateBase . $suffix);
         $copyNumber++;
      } while ($this->page_permalink_exists($db, $candidate));

      return $candidate;
   }
}
