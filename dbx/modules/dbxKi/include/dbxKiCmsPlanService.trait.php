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

trait dbxKiCmsPlanServiceTrait {

   private function plan_folder_create(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $name = $this->clean($params['name'] ?? '', 120);
      if ($name === '') throw new \InvalidArgumentException('name ist erforderlich.');
      $parent = max(0, (int)($params['parent_id'] ?? 0));
      if ($parent > 0 && !is_array($this->db->select1(dbxContentLng::dd_folder($lng), $parent))) {
         throw new \RuntimeException('Parent-Ordner nicht gefunden.');
      }
      return array(
         'operation' => 'insert',
         'entity' => 'folder',
         'lng' => $lng,
         'data' => $this->folder_data($params, $parent, $name),
      );
   }

   private function plan_folder_update(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $dd = dbxContentLng::dd_folder($lng);
      $before = $this->db->select1($dd, $id);
      if (!is_array($before)) throw new \RuntimeException('Ordner nicht gefunden.');
      $patch = $this->patch($params);
      $parent = array_key_exists('parent_id', $patch) ? max(0, (int)$patch['parent_id']) : (int)($before['parent_id'] ?? 0);
      if ($parent === $id || $this->folder_descendant($dd, $parent, $id)) {
         throw new \InvalidArgumentException('Ungültiger Parent: Schleife im Ordnerbaum.');
      }
      if ($parent > 0 && !is_array($this->db->select1($dd, $parent))) throw new \RuntimeException('Parent-Ordner nicht gefunden.');
      $allowed = array('name', 'parent_id', 'group_read', 'template', 'hero_template', 'hero_image_id', 'hero_margin_top', 'hero_height', 'hero_variant', 'hero_sticky', 'hero_scroll_layer', 'sorter');
      $data = $this->whitelist($patch, $allowed);
      if (isset($data['name'])) $data['name'] = $this->clean($data['name'], 120);
      if (!$data) throw new \InvalidArgumentException('Keine änderbaren Felder übergeben.');
      return array('operation' => 'update', 'entity' => 'folder', 'lng' => $lng, 'id' => $id, 'before' => $before, 'changes' => $data);
   }

   private function plan_folder_delete(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $row = $this->db->select1(dbxContentLng::dd_folder($lng), $id);
      if (!is_array($row)) throw new \RuntimeException('Ordner nicht gefunden.');
      $check = dbxContentLngSync::folder_deletable($this->db, $lng, $id);
      if ((int)($check['deletable'] ?? 0) !== 1) {
         throw new \RuntimeException((string)($check['reason'] ?? 'Ordner ist nicht löschbar.'));
      }
      return array('operation' => 'delete', 'entity' => 'folder', 'lng' => $lng, 'id' => $id, 'before' => $row);
   }

   private function plan_page_create(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $title = $this->clean($params['title'] ?? '', 254);
      if ($title === '') throw new \InvalidArgumentException('title ist erforderlich.');
      $folder = max(0, (int)($params['folder_id'] ?? $params['folder'] ?? 0));
      if ($folder > 0 && !is_array($this->db->select1(dbxContentLng::dd_folder($lng), $folder))) {
         throw new \RuntimeException('Zielordner nicht gefunden.');
      }
      $data = $this->page_data($params, $lng, $folder, $title);
      $this->assert_no_fake_inline_hero((string)($data['content'] ?? ''));
      return array(
         'operation' => 'insert',
         'entity' => 'page',
         'lng' => $lng,
         'data' => $data,
      );
   }

   private function plan_page_update(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $dd = dbxContentLng::dd_content($lng);
      $before = $this->db->select1($dd, $id);
      if (!is_array($before)) throw new \RuntimeException('Seite nicht gefunden.');
      $patch = $this->patch($params);
      if (array_key_exists('folder_id', $patch) && !array_key_exists('folder', $patch)) {
         $patch['folder'] = $patch['folder_id'];
      }
      $package_product_image = $this->bool_value($patch['package_product_image'] ?? false);
      $package_media_id = max(0, (int)($patch['package_media_id'] ?? 0));
      $package_image_alt = $this->clean($patch['package_image_alt'] ?? '', 254);
      unset($patch['package_product_image'], $patch['package_media_id'], $patch['package_image_alt']);
      $allowed = array(
          'activ', 'folder', 'title', 'menu_title', 'seo_title', 'permalink', 'description', 'keywords', 'group_read', 'template', 'content', 'sorter',
         'hero_template', 'hero_image_id', 'hero_margin_top', 'hero_height', 'hero_variant', 'hero_sticky',
         'hero_scroll_layer', 'gallery_template', 'gallery_visible_count', 'gallery_image_size',
         'gallery_lightbox_width', 'gallery_overflow', 'gallery_click_behavior'
      );
      $data = $this->whitelist($patch, $allowed);
      if (isset($data['title'])) $data['title'] = $this->clean($data['title'], 254);
      if (isset($data['menu_title'])) $data['menu_title'] = $this->clean($data['menu_title'], 96);
      if (isset($data['seo_title'])) $data['seo_title'] = $this->clean($data['seo_title'], 254);
      if (array_key_exists('permalink', $data)) {
         $data['permalink'] = trim($this->clean($data['permalink'], 254));
         if (!dbxContent_permalink::is_valid($data['permalink'])) {
            throw new \InvalidArgumentException('permalink darf nur Kleinbuchstaben, Zahlen und einzelne Bindestriche enthalten.');
         }
         if (dbxContent_permalink::exists($this->db, $dd, $data['permalink'], $id)) {
            throw new \InvalidArgumentException('permalink wird bereits von einer anderen Seite verwendet.');
         }
      }
      if (isset($data['folder'])) {
         $data['folder'] = max(0, (int)$data['folder']);
         if ($data['folder'] > 0 && !is_array($this->db->select1(dbxContentLng::dd_folder($lng), $data['folder']))) {
            throw new \RuntimeException('Zielordner nicht gefunden.');
         }
      }
      if (!$data && !$package_product_image && $package_media_id <= 0) {
         throw new \InvalidArgumentException('Keine änderbaren Felder übergeben.');
      }
      if (array_key_exists('content', $data)) {
         $data['content'] = $this->normalize_content_inline_media_urls((string)$data['content']);
      }
      $package_media_applied = 0;
      if ($package_product_image || $package_media_id > 0) {
         $media_id = $package_media_id > 0
            ? $package_media_id
            : $this->package_media_id_for_permalink((string)($before['permalink'] ?? ''));
         if ($media_id <= 0) {
            throw new \RuntimeException('Kein Paket-Produktbild fuer diese Seite gefunden. package_media_id angeben oder home-package-* Medium anlegen.');
         }
         $content = array_key_exists('content', $data)
            ? (string)$data['content']
            : (string)($before['content'] ?? '');
         $data['content'] = $this->normalize_content_inline_media_urls(
            $this->apply_package_product_image($content, $media_id, $package_image_alt)
         );
         $package_media_applied = $media_id;
      }
      if (array_key_exists('content', $data)) {
         $this->assert_no_fake_inline_hero((string)$data['content']);
      }
      $plan = array('operation' => 'update', 'entity' => 'page', 'lng' => $lng, 'id' => $id, 'before' => $before, 'changes' => $data);
      if ($package_media_applied > 0) {
         $plan['package_media_id_applied'] = $package_media_applied;
      }
      return $plan;
   }

   private function plan_page_hero_replace_image(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $hero = $this->hero_media_for_page($lng, $id);
      $media = $hero['media'];
      $source = $this->source_image_plan($params);
      $target = $this->media_local_file($media);
      if ($target === '') throw new \RuntimeException('Hero-Medium ist keine lokale Datei.');

      $width = max(1, (int)($params['width'] ?? $media['width'] ?? 0));
      $height = max(1, (int)($params['height'] ?? $media['height'] ?? 0));
      if ($width <= 1 || $height <= 1) {
         $width = 1280;
         $height = 300;
      }
      $mime = (string)($media['mime'] ?? '');
      if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true)) {
         $mime = $this->mime_from_file_name((string)($media['file_name'] ?? 'hero.webp'));
      }

      return array(
         'operation' => 'replace_page_hero_file',
         'entity' => 'page_hero',
         'lng' => $lng,
         'id' => $id,
         'page' => $hero['page'],
         'media' => $media,
         'usage' => $hero['usage'],
         'source' => $source,
         'target_file' => $target,
         'width' => $width,
         'height' => $height,
         'fit' => $this->image_fit($params['fit'] ?? 'cover'),
         'quality' => $this->image_quality($params['quality'] ?? 82),
         'mime' => $mime,
      );
   }

   private function plan_page_hero_create_image(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $dd = dbxContentLng::dd_content($lng);
      $page = $this->db->select1($dd, $id);
      if (!is_array($page)) throw new \RuntimeException('Seite nicht gefunden.');

      $permalink = trim((string)($page['permalink'] ?? ''));
      $base_name = $permalink !== '' ? $permalink : ('page-' . $id);
      $file_name = $this->safe_file_name($params['file_name'] ?? ($base_name . '-hero.webp'));
      if ($file_name === '') $file_name = 'page-' . $id . '-hero.webp';

      $variant = $this->plan_media_create_image_variant(array_merge($params, array(
         'file_name' => $file_name,
         'width' => max(1, (int)($params['width'] ?? 1280)),
         'height' => max(1, (int)($params['height'] ?? 300)),
         'fit' => $params['fit'] ?? 'cover',
         'quality' => $params['quality'] ?? 82,
         'media_folder' => 'img/hero',
         'title' => $params['title'] ?? ('Hero ' . ($page['title'] ?? $file_name)),
         'alt' => $params['alt'] ?? (string)($page['title'] ?? ''),
      )));

      return array(
         'operation' => 'create_page_hero_media',
         'entity' => 'page_hero',
         'lng' => $lng,
         'id' => $id,
         'page' => $page,
         'media_plan' => $variant,
      );
   }

   private function plan_page_delete(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $row = $this->db->select1(dbxContentLng::dd_content($lng), $id);
      if (!is_array($row)) throw new \RuntimeException('Seite nicht gefunden.');
      $usage = $this->db->count('dbxMediaUsage', dbxContentMediaUsageScope::with_language('content_id = ' . $id . ' AND active = 1', $lng));
      return array('operation' => 'delete', 'entity' => 'page', 'lng' => $lng, 'id' => $id, 'before' => $row, 'media_usage_to_deactivate' => $usage);
   }

   private function plan_media_create(array $params): array {
      $name = $this->safe_file_name($params['file_name'] ?? '');
      $raw = (string)($params['data_base64'] ?? '');
      if ($name === '' || trim($raw) === '') throw new \InvalidArgumentException('file_name und data_base64 sind erforderlich.');
      $decoded = $this->decode_base64($raw);
      $max = max(1024, (int)dbx()->get_cfg('dbxKi', 'max_base64_bytes', 10485760));
      if (strlen($decoded) > $max) throw new \InvalidArgumentException('Datei überschreitet das konfigurierte Größenlimit.');
      $mime = $this->detect_mime($decoded, $name);
      $allowed = array('image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'video/quicktime', 'application/pdf', 'text/plain');
      if (!in_array($mime, $allowed, true)) throw new \InvalidArgumentException('Nicht unterstützter MIME-Typ: ' . $mime);
      $type = strpos($mime, 'image/') === 0 ? 'image' : (strpos($mime, 'video/') === 0 ? 'video' : 'file');
      $default_folder = $type === 'image' ? 'img/images' : ($type === 'video' ? 'img/video' : 'file/ki');
      $folder = $this->media_folder($params['media_folder'] ?? $default_folder, $type);
      return array(
         'operation' => 'create_file_and_insert',
         'entity' => 'media',
         'file_name' => $name,
         'bytes' => strlen($decoded),
         'sha256' => hash('sha256', $decoded),
         'mime' => $mime,
         'media_type' => $type,
         'media_folder' => $folder,
         'metadata' => array(
            'title' => $this->clean($params['title'] ?? pathinfo($name, PATHINFO_FILENAME), 160),
            'alt' => $this->clean($params['alt'] ?? '', 254),
            'caption' => $this->clean($params['caption'] ?? ''),
            'tags' => $this->clean($params['tags'] ?? '', 254),
         ),
      );
   }

   private function plan_media_create_image_variant(array $params): array {
      if (!extension_loaded('gd')) {
         throw new \RuntimeException('GD ist erforderlich, um Bildvarianten zu erzeugen.');
      }

      $source = $this->resolve_local_file((string)($params['source_file'] ?? ''));
      if ($source === '' || !is_file($source) || !is_readable($source)) {
         throw new \InvalidArgumentException('source_file ist nicht lesbar.');
      }

      $name = $this->safe_file_name($params['file_name'] ?? '');
      if ($name === '') throw new \InvalidArgumentException('file_name ist erforderlich.');
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $mime_map = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp');
      if (!isset($mime_map[$ext])) {
         throw new \InvalidArgumentException('file_name muss .webp, .jpg, .jpeg oder .png verwenden.');
      }

      $info = @getimagesize($source);
      if (!is_array($info) || empty($info[0]) || empty($info[1])) {
         throw new \InvalidArgumentException('source_file ist kein lesbares Bild.');
      }
      $source_mime = (string)($info['mime'] ?? '');
      if (!in_array($source_mime, array('image/jpeg', 'image/png', 'image/webp', 'image/gif'), true)) {
         throw new \InvalidArgumentException('Nicht unterstützter Quellbildtyp: ' . $source_mime);
      }

      $source_width = (int)$info[0];
      $source_height = (int)$info[1];
      $crop = $this->image_crop_rect($params, $source_width, $source_height);
      $width = max(1, (int)($params['width'] ?? $source_width));
      $height = max(1, (int)($params['height'] ?? $source_height));
      $fit = strtolower(trim((string)($params['fit'] ?? 'cover')));
      if (!in_array($fit, array('cover', 'contain'), true)) $fit = 'cover';
      $quality = min(100, max(1, (int)($params['quality'] ?? 82)));
      $tint = $this->normalize_hex_color((string)($params['tint'] ?? ''));
      $tint_strength = max(0.0, min(1.0, (float)($params['tint_strength'] ?? 0)));
      $folder = $this->media_folder($params['media_folder'] ?? 'img/images', 'image');

      return array(
         'operation' => 'create_image_variant_and_insert',
         'entity' => 'media',
         'source_file' => $source,
         'source_sha256' => hash_file('sha256', $source),
         'source_mime' => $source_mime,
         'source_width' => $source_width,
         'source_height' => $source_height,
         'crop' => $crop,
         'file_name' => $name,
         'mime' => $mime_map[$ext],
         'media_type' => 'image',
         'media_folder' => $folder,
         'width' => $width,
         'height' => $height,
         'fit' => $fit,
         'quality' => $quality,
         'tint' => $tint,
         'tint_strength' => $tint_strength,
         'metadata' => array(
            'title' => $this->clean($params['title'] ?? pathinfo($name, PATHINFO_FILENAME), 160),
            'alt' => $this->clean($params['alt'] ?? '', 254),
            'caption' => $this->clean($params['caption'] ?? ''),
            'tags' => $this->clean($params['tags'] ?? '', 254),
         ),
      );
   }

   private function plan_media_update(array $params): array {
      $id = $this->id($params);
      $before = $this->db->select1('dbxMedia', $id);
      if (!is_array($before) || (int)($before['active'] ?? 0) !== 1) throw new \RuntimeException('Medium nicht gefunden.');
      $data = $this->whitelist($this->patch($params), array('title', 'alt', 'caption', 'tags', 'template'));
      if (!$data) throw new \InvalidArgumentException('Keine änderbaren Metadaten übergeben.');
      return array('operation' => 'update', 'entity' => 'media', 'id' => $id, 'before' => $before, 'changes' => $data);
   }

   private function plan_media_assign(array $params): array {
      $media_id = $this->id($params, 'media_id');
      $media = $this->db->select1('dbxMedia', $media_id);
      if (!is_array($media) || (int)($media['active'] ?? 0) !== 1) throw new \RuntimeException('Medium nicht gefunden.');
      $content_id = max(0, (int)($params['content_id'] ?? 0));
      $folder_id = max(0, (int)($params['folder_id'] ?? 0));
      if (($content_id > 0) === ($folder_id > 0)) throw new \InvalidArgumentException('Genau content_id oder folder_id muss gesetzt sein.');
      $lng = $this->language($params['lng'] ?? '');
      if ($content_id > 0 && !is_array($this->db->select1(dbxContentLng::dd_content($lng), $content_id))) throw new \RuntimeException('Seite nicht gefunden.');
      if ($folder_id > 0 && !is_array($this->db->select1(dbxContentLng::dd_folder($lng), $folder_id))) throw new \RuntimeException('Ordner nicht gefunden.');
      $slot = $this->slot($params['slot'] ?? 'gallery');
      return array(
         'operation' => 'insert',
         'entity' => 'media_usage',
         'lng' => $lng,
         'media' => $media,
         'data' => array(
            'active' => 1,
            'media_id' => $media_id,
            'content_id' => $content_id,
            'folder_id' => $folder_id,
            'slot' => $slot,
            'template' => $this->clean($params['template'] ?? $media['template'] ?? '', 80),
            'caption' => $this->clean($params['caption'] ?? ''),
            'settings' => is_array($params['settings'] ?? null)
               ? json_encode($params['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
               : $this->clean($params['settings'] ?? ''),
         ),
      );
   }

   private function plan_media_unassign(array $params): array {
      $id = $this->id($params, 'usage_id');
      $row = $this->db->select1('dbxMediaUsage', $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) throw new \RuntimeException('Aktive Medienzuordnung nicht gefunden.');
      return array('operation' => 'update', 'entity' => 'media_usage', 'id' => $id, 'before' => $row, 'changes' => array('active' => 0));
   }

   private function plan_media_delete(array $params): array {
      $id = $this->id($params);
      $data = $this->media_get(array('id' => $id));
      if (count($data['usage'])) throw new \RuntimeException('Medium wird noch verwendet. Zuerst media.unassign ausführen.');
      return array('operation' => 'delete', 'entity' => 'media', 'id' => $id, 'before' => $data['row']);
   }

   private function plan_translation_apply(array $params): array {
      $preview = $this->translation_preview($params);
      $translation = is_array($params['translation'] ?? null) ? $params['translation'] : array();
      foreach (array('title', 'description', 'keywords', 'content') as $field) {
         if (!array_key_exists($field, $translation)) throw new \InvalidArgumentException('translation.' . $field . ' fehlt.');
      }
      $translation = $this->whitelist($translation, array(
         'title', 'description', 'keywords', 'content', 'seo_title',
         'img_alt_1', 'img_alt_2', 'img_alt_3',
         'img_des_1', 'img_des_2', 'img_des_3'
      ));
      $translation['title'] = $this->clean($translation['title'], 254);
      $translation['description'] = $this->clean($translation['description'], 254);
      $translation['keywords'] = $this->clean($translation['keywords'], 254);
      foreach (array('seo_title', 'img_alt_1', 'img_alt_2', 'img_alt_3') as $field) {
         if (array_key_exists($field, $translation)) {
            $translation[$field] = $this->clean($translation[$field], 254);
         }
      }
      foreach (array('img_des_1', 'img_des_2', 'img_des_3') as $field) {
         if (array_key_exists($field, $translation)) {
            $translation[$field] = $this->clean($translation[$field]);
         }
      }
      if ($translation['title'] === '') throw new \InvalidArgumentException('Übersetzter Titel darf nicht leer sein.');
      $translation['content'] = $this->normalize_content_inline_media_urls((string)$translation['content']);
      return array(
         'operation' => is_array($preview['target']) ? 'update' : 'insert',
         'entity' => 'translation',
         'source_lng' => $preview['source_lng'],
         'target_lng' => $preview['target_lng'],
         'source' => $preview['source'],
         'target' => $preview['target'],
         'translation' => $translation,
         'copy_media' => !array_key_exists('copy_media', $params) || $this->bool_value($params['copy_media']),
      );
   }

   private function plan_translation_sync_all(array $params): array {
      $source_lng = $this->language($params['source_lng'] ?? '');
      $target_lngs = $this->target_languages($params, $source_lng);
      if (!count($target_lngs)) {
         throw new \InvalidArgumentException('Keine Zielsprachen gefunden.');
      }

      $root_folder_id = max(0, (int)($params['root_folder_id'] ?? $params['folder_id'] ?? 0));
      if ($root_folder_id > 0 && !is_array($this->db->select1(dbxContentLng::dd_folder($source_lng), $root_folder_id))) {
         throw new \RuntimeException('Quellordner nicht gefunden.');
      }

      $folder_ids = $this->collect_folder_ids_for_lng($source_lng, $root_folder_id);
      $page_ids = $this->collect_page_ids_for_lng($source_lng, $root_folder_id, $folder_ids);

      return array(
         'operation' => 'translation_sync_all',
         'entity' => 'content_language',
         'source_lng' => $source_lng,
         'target_lngs' => $target_lngs,
         'root_folder_id' => $root_folder_id,
         'update_existing' => !array_key_exists('update_existing', $params) || $this->bool_value($params['update_existing']),
         'skip_manual' => array_key_exists('skip_manual', $params) && $this->bool_value($params['skip_manual']),
         'copy_media' => !array_key_exists('copy_media', $params) || $this->bool_value($params['copy_media']),
         'replace_media_usage' => array_key_exists('replace_media_usage', $params) && $this->bool_value($params['replace_media_usage']),
         'provider' => dbxContentTranslate::provider(),
         'counts' => array(
            'folders' => count($folder_ids),
            'pages' => count($page_ids),
            'target_languages' => count($target_lngs),
         ),
         'source_ids' => array(
            'folders' => $folder_ids,
            'pages' => $page_ids,
         ),
      );
   }
}
