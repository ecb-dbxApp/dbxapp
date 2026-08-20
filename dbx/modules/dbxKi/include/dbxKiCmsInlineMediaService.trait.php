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

trait dbxKiCmsInlineMediaServiceTrait {

   private function inline_media_src(int $id): string {
      return 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . max(0, (int)$id);
   }

   private function package_media_file_map(): array {
      return array(
         'dbxapp-paket-demo' => 'paket-demo-360x480.webp',
         'dbxapp-paket-non-profit' => 'paket-nonprofit-360x480.webp',
         'dbxapp-paket-business' => 'paket-business-360x480.webp',
         'dbxapp-paket-intranet' => 'paket-intranet-360x480.webp',
         'dbxapp-paket-enterprise' => 'paket-enterprise-360x480.webp',
      );
   }

   private function package_media_id_for_permalink(string $permalink): int {
      $permalink = trim(strtolower($permalink));
      $map = $this->package_media_file_map();
      $file_name = (string)($map[$permalink] ?? '');
      if ($file_name === '') {
         return 0;
      }
      $where = "active = 1 AND file_name = '" . str_replace("'", "''", $file_name) . "'";
      $row = $this->db->select1('dbxMedia', $where);
      return is_array($row) ? (int)($row['id'] ?? 0) : 0;
   }

   private function package_page_hint(array $page): ?array {
      $permalink = trim((string)($page['permalink'] ?? ''));
      $media_id = $this->package_media_id_for_permalink($permalink);
      if ($media_id <= 0) {
         return null;
      }
      return array(
         'permalink' => $permalink,
         'media_id' => $media_id,
         'file_name' => (string)($this->package_media_file_map()[strtolower($permalink)] ?? ''),
         'inline_src' => $this->inline_media_src($media_id),
         'update_patch' => array('package_product_image' => true),
      );
   }

   private function apply_package_product_image(string $content, int $media_id, string $alt = ''): string {
      if ($media_id <= 0 || stripos($content, 'col-md-4') === false || stripos($content, 'card') === false) {
         return $content;
      }
      $src_esc = htmlspecialchars($this->inline_media_src($media_id), ENT_QUOTES, 'UTF-8');
      $alt_esc = htmlspecialchars($alt !== '' ? $alt : 'Paket', ENT_QUOTES, 'UTF-8');
      $img = '<img class="card-img-top" src="' . $src_esc . '" data-cms-media-id="' . $media_id . '" alt="' . $alt_esc . '">';

      $updated = preg_replace_callback(
         '/<div class="col-md-4"><div class="card shadow-sm(?:\s+position-relative)?">(?:<img[^>]*card-img-top[^>]*>)?(?:<span class="position-absolute[^>]*>[\s\S]*?<\/span>)?<div class="card-body text-center">([\s\S]*?)<\/div><\/div><\/div>/i',
         function($m) use ($img) {
            $body = (string)($m[1] ?? '');
            $badge = '';
            if (preg_match('/<span class="badge[^>]*bg-success[^>]*>([\s\S]*?)<\/span>/i', $body, $badge_match)) {
               $label = trim(strip_tags((string)($badge_match[1] ?? 'Kostenlos')));
               if ($label !== '') {
                  $badge = '<span class="position-absolute top-0 end-0 badge rounded-pill bg-success m-2">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
               }
            }
            $body = preg_replace('/<span class="badge[^>]*>[\s\S]*?<\/span>\s*(?:<br\s*\/?>)?\s*/i', '', $body, 1);
            $body = preg_replace('/<img\b[^>]*>\s*/i', '', $body, 1);
            $body = preg_replace('/\bh5 mt-3\b/', 'h5', $body, 1);
            return '<div class="col-md-4"><div class="card shadow-sm position-relative">' . $img . $badge . '<div class="card-body text-center">' . $body . '</div></div></div>';
         },
         $content,
         1
      );

      return is_string($updated) && $updated !== '' ? $updated : $content;
   }

   private function ensure_inline_media_usage(int $content_id, int $media_id, string $lng = ''): void {
      $content_id = (int)$content_id;
      $media_id = (int)$media_id;
      if ($content_id <= 0 || $media_id <= 0) {
         return;
      }
      $lng = dbxContentMediaUsageScope::language($lng);
      $where = dbxContentMediaUsageScope::with_language('content_id = ' . $content_id . ' AND media_id = ' . $media_id . " AND slot = 'inline' AND active = 1", $lng);
      if (is_array($this->db->select1('dbxMediaUsage', $where))) {
         return;
      }
      $data = array(
         'active' => 1,
         'media_id' => $media_id,
         'content_id' => $content_id,
         'folder_id' => 0,
         'content_lng' => $lng,
         'slot' => 'inline',
         'template' => '',
         'caption' => '',
         'settings' => '',
         'sorter' => $this->next_usage_sorter($content_id, 0, 'inline', $lng),
      );
      $this->db->insert('dbxMediaUsage', $data);
   }

   private function media_inline_payload(int $id): array {
      $id = max(0, (int)$id);
      if ($id <= 0) {
         return array();
      }
      $src = $this->inline_media_src($id);
      return array(
         'inline_src' => $src,
         'inline_img' => '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" data-cms-media-id="' . $id . '" alt="">',
      );
   }

   private function normalize_content_inline_media_urls(string $html): string {
      $html = (string)$html;
      if ($html === '' || stripos($html, '<img') === false) {
         return $html;
      }

      return preg_replace_callback('/<img\b([^>]*?)>/i', function($m) {
         $tag = (string)($m[0] ?? '');
         $attrs = (string)($m[1] ?? '');
         $id = 0;
         if (preg_match('/\bdata-cms-media-id=["\']?([0-9]+)/i', $attrs, $id_match)) {
            $id = (int)$id_match[1];
         } elseif (preg_match('/\bdbx_mid=([0-9]+)/i', $attrs, $id_match)) {
            $id = (int)$id_match[1];
         } elseif (preg_match('/\bsrc=(["\'])([^"\']+)\1/i', $attrs, $src_match)) {
            $id = $this->media_id_by_inline_src((string)$src_match[2]);
         }
         if ($id <= 0) {
            return $tag;
         }
         return $this->patch_img_tag_for_inline_media($tag, $id);
      }, $html);
   }

   private function media_id_by_inline_src(string $src): int {
      $src = html_entity_decode(trim($src), ENT_QUOTES, 'UTF-8');
      if ($src === '' || preg_match('#^(?:https?:)?//#i', $src) || stripos($src, 'dbx_mid=') !== false) {
         return 0;
      }

      $path = preg_replace('/[?#].*$/', '', str_replace('\\', '/', $src));
      $rel = '';
      if (preg_match('#(?:^|/)(?:files/)?media/(.+)$#i', $path, $match)) {
         $rel = 'media/' . ltrim((string)$match[1], '/');
      } else {
         return 0;
      }

      static $cache = array();
      if (isset($cache[$rel])) {
         return (int)$cache[$rel];
      }

      $where = "active = 1 AND file_path = '" . str_replace("'", "''", $rel) . "'";
      $row = $this->db->select1('dbxMedia', $where);
      if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
         return $cache[$rel] = (int)$row['id'];
      }

      $base = basename($rel);
      if ($base === '' || $base === '.' || $base === '..') {
         return $cache[$rel] = 0;
      }
      $rows = $this->db->select(
         'dbxMedia',
         "active = 1 AND file_name = '" . str_replace("'", "''", $base) . "'",
         'id,file_path',
         'id',
         'DESC',
         '',
         5,
         0,
         0
      );
      if (is_array($rows)) {
         foreach ($rows as $candidate) {
            $candidate_path = ltrim(str_replace('\\', '/', (string)($candidate['file_path'] ?? '')), '/');
            if ($candidate_path === $rel || basename($candidate_path) === $base) {
               return $cache[$rel] = (int)($candidate['id'] ?? 0);
            }
         }
      }

      return $cache[$rel] = 0;
   }

   private function patch_img_tag_for_inline_media(string $tag, int $id): string {
      $id = max(0, (int)$id);
      if ($id <= 0) {
         return $tag;
      }
      $src = $this->inline_media_src($id);
      $src_attr = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
      $tag = preg_replace('/\s*data-cms-media-id\s*=\s*["\']?[^"\'>\s]*["\']*/i', '', $tag);
      if (preg_match('/\bsrc=(["\'])([^"\']*)\1/i', $tag)) {
         $tag = preg_replace('/\bsrc=(["\'])([^"\']*)\1/i', 'src="' . $src_attr . '"', $tag, 1);
      } else {
         $tag = preg_replace('/^<img\b/i', '<img src="' . $src_attr . '"', $tag);
      }
      $tag = preg_replace('/^<img\b/i', '<img data-cms-media-id="' . $id . '"', $tag);
      return $tag;
   }
}
