<?php

namespace dbx\dbxContent;

/** Interne Komponente von dbxContentRenderer. */
trait dbxContentRendererMediaTrait {

private function render_inline_media_placeholders($db, array $parsed) {
      foreach ($parsed as $key => $html) {
         if (!is_string($html)) continue;
         if (stripos($html, 'dbx-cms-inline-video-block') !== false) {
            $html = $this->render_inline_video_placeholders($db, $html);
         }
         if (stripos($html, 'dbx_mid=') !== false || stripos($html, 'data-cms-media-id') !== false) {
            $html = $this->render_inline_image_sanitize($db, $html);
         }
         $parsed[$key] = $html;
      }
      return $parsed;
   }

private function render_inline_image_sanitize($db, $html) {
      return preg_replace_callback('/<(img)\b([^>]*?)>/i', function($m) use ($db) {
         $attrs = (string)($m[2] ?? '');
         $id = 0;
         if (preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $attrs, $id_match)) {
            $id = (int)$id_match[1];
         } elseif (preg_match('/dbx_mid=([0-9]+)/i', $attrs, $id_match)) {
            $id = (int)$id_match[1];
         }
         if ($id <= 0) return $m[0];
         $row = $db->select1($this->dd_media, $id);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1 || !$this->media_file_exists($row)) return '';
         $tag = (string)$m[0];
         $width = max(0, (int)($row['width'] ?? 0));
         $height = max(0, (int)($row['height'] ?? 0));
         if ($width > 0 && $height > 0) {
            $tag = self::with_html_image_attr($tag, 'width', (string)$width);
            $tag = self::with_html_image_attr($tag, 'height', (string)$height);
         }
         return $tag;
      }, (string)$html);
   }

private function render_inline_video_placeholders($db, $html) {
      return preg_replace_callback('/<figure\b([^>]*\bdbx-cms-inline-video-block\b[^>]*)>[\s\S]*?<\/figure>/i', function($m) use ($db) {
         $attrs = $m[1];
         if (!preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $attrs, $id_match)) return $m[0];
         $id = (int)$id_match[1];
         $row = $db->select1($this->dd_media, $id);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1 || !$this->media_file_exists($row)) return '';
         return $this->render_inline_video_player($row, $attrs, $m[0]);
      }, (string)$html);
   }

private function inline_media_type(array $row) {
      if ((string)($row['media_type'] ?? '') === 'external_video') return 'external_video';
      $mime = (string)($row['mime'] ?? '');
      if (strpos($mime, 'video/') === 0 || preg_match('/\.(mp4|webm|ogv|ogg|mov|m4v)$/i', (string)($row['file_name'] ?? $row['file_path'] ?? ''))) return 'video';
      if (strtolower((string)($row['storage_type'] ?? '')) === 'external') return 'external_video';
      return 'file';
   }

private function render_inline_video_player(array $row, $source_attrs = '', $source_html = '') {
      $id = (int)($row['id'] ?? 0);
      $type = $this->inline_media_type($row);
      if ($id <= 0 || !in_array($type, array('video', 'external_video'), true)) return '';

      $source_attrs = (string)$source_attrs;
      $source_html = (string)$source_html;
      $video_options = $this->inline_video_options_from_html($source_attrs, $source_html);
      $style = $video_options['style'] !== '' ? ' style="' . htmlspecialchars($video_options['style'], ENT_QUOTES, 'UTF-8') . '"' : '';
      $autoplay = !empty($video_options['autoplay']);
      $loop = !empty($video_options['loop']);
      $muted = !empty($video_options['muted']);
      $align = (string)($video_options['align'] ?? 'left');
      if ($autoplay) {
         $muted = true;
      }

      $title = htmlspecialchars((string)($row['title'] ?? $row['alt'] ?? $row['file_name'] ?? 'Video'), ENT_QUOTES, 'UTF-8');
      $caption = trim((string)($row['caption'] ?? ''));
      $caption_html = $caption !== '' ? '<figcaption>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</figcaption>' : '';
      $figure_attr = ' class="dbx-content-inline-media dbx-content-inline-video" data-media-id="' . $id . '" data-video-align="' . htmlspecialchars($align, ENT_QUOTES, 'UTF-8') . '"' . $style;

      if ($type === 'external_video') {
         $url = (string)($row['embed_url'] ?? $row['external_url'] ?? '');
         if ($url === '') return '';
         $url = $this->external_video_option_url($url, $autoplay, $muted, $loop);
         $loading = $autoplay ? 'eager' : 'lazy';
         if ($this->is_youtube_media($row, $url)) {
            return '<figure' . $figure_attr . '>' . $this->render_youtube_consent_block($url, (string)($row['title'] ?? $row['alt'] ?? $row['file_name'] ?? 'Video'), $loading) . $caption_html . '</figure>';
         }
         return '<figure' . $figure_attr . '><iframe class="dbx-content-video-player" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" title="' . $title . '" loading="' . $loading . '" allowfullscreen></iframe>' . $caption_html . '</figure>';
      }

      $url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $id;
      $poster = !empty($row['thumb_file_path']) ? $url . '&dbx_thumb=1' : '';
      $poster_attr = $poster !== '' ? ' poster="' . htmlspecialchars($poster, ENT_QUOTES, 'UTF-8') . '"' : '';
      $mime_attr = trim((string)($row['mime'] ?? '')) !== '' ? ' type="' . htmlspecialchars((string)$row['mime'], ENT_QUOTES, 'UTF-8') . '"' : '';
      $autoplay_attr = $autoplay ? ' autoplay' : '';
      $loop_attr = $loop ? ' loop' : '';
      $muted_attr = $muted ? ' muted' : '';
      return '<figure' . $figure_attr . '><video class="dbx-content-video-player" controls preload="metadata" playsinline' . $autoplay_attr . $loop_attr . $muted_attr . $poster_attr . '><source src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $mime_attr . '></video>' . $caption_html . '</figure>';
   }

private function inline_video_options_from_html($source_attrs, $source_html) {
      $style = '';
      if (preg_match('/\sstyle=(["\'])(.*?)\1/i', (string)$source_attrs, $m)) {
         $style = $this->safe_inline_video_style($m[2]);
      }

      $width = $this->attr_value_from_html($source_attrs, 'data-cms-video-width');
      $height = $this->attr_value_from_html($source_attrs, 'data-cms-video-height');
      $align_raw = $this->attr_value_from_html($source_attrs, 'data-cms-video-align');
      if ($width === '') $width = $this->attr_value_from_html($source_attrs, 'width');
      if ($height === '') $height = $this->attr_value_from_html($source_attrs, 'height');

      if ((string)$source_html !== '' && preg_match('/<(?:img|video|iframe)\b([^>]*)>/i', (string)$source_html, $media_match)) {
         $media_attrs = $media_match[1];
         if ($width === '') $width = $this->attr_value_from_html($media_attrs, 'data-cms-video-width');
         if ($height === '') $height = $this->attr_value_from_html($media_attrs, 'data-cms-video-height');
         if ($width === '') $width = $this->attr_value_from_html($media_attrs, 'width');
         if ($height === '') $height = $this->attr_value_from_html($media_attrs, 'height');
         if ($align_raw === '') $align_raw = $this->attr_value_from_html($media_attrs, 'data-cms-video-align');
         if ($style === '' && preg_match('/\sstyle=(["\'])(.*?)\1/i', $media_attrs, $style_match)) {
            $style = $this->safe_inline_video_style($style_match[2]);
         }
      }

      $width = $this->css_size_value($width);
      $height = $this->css_size_value($height);
      $style_parts = $this->style_to_map($style);
      if ($width !== '') $style_parts['width'] = $width;
      if ($height !== '') $style_parts['height'] = $height;
      $align = $this->inline_video_align_value($align_raw, $style_parts);
      if ($align === 'center') {
         unset($style_parts['float']);
         $style_parts['margin-left'] = 'auto';
         $style_parts['margin-right'] = 'auto';
      } elseif ($align === 'right') {
         unset($style_parts['float']);
         $style_parts['margin-left'] = 'auto';
         $style_parts['margin-right'] = '0px';
      } elseif ($align_raw !== '') {
         unset($style_parts['float'], $style_parts['margin-left'], $style_parts['margin-right']);
      }

      $autoplay_raw = $this->attr_value_from_html($source_attrs, 'data-cms-video-autoplay');
      if ($autoplay_raw === '' && (string)$source_html !== '' && preg_match('/<(?:img|video|iframe)\b([^>]*)>/i', (string)$source_html, $media_match)) {
         $autoplay_raw = $this->attr_value_from_html($media_match[1], 'data-cms-video-autoplay');
      }
      $muted_raw = $this->attr_value_from_html($source_attrs, 'data-cms-video-muted');
      if ($muted_raw === '' && (string)$source_html !== '' && preg_match('/<(?:img|video|iframe)\b([^>]*)>/i', (string)$source_html, $media_match)) {
         $muted_raw = $this->attr_value_from_html($media_match[1], 'data-cms-video-muted');
      }

      $loop_raw = $this->attr_value_from_html($source_attrs, 'data-cms-video-loop');
      if ($loop_raw === '' && (string)$source_html !== '' && preg_match('/<(?:img|video|iframe)\b([^>]*)>/i', (string)$source_html, $media_match)) {
         $loop_raw = $this->attr_value_from_html($media_match[1], 'data-cms-video-loop');
      }

      return array(
         'style' => $this->style_map_to_string($style_parts),
         'autoplay' => preg_match('/^(1|true|yes|ja|on)$/i', trim((string)$autoplay_raw)) === 1,
         'loop' => preg_match('/^(1|true|yes|ja|on)$/i', trim((string)$loop_raw)) === 1,
         'muted' => preg_match('/^(1|true|yes|ja|on)$/i', trim((string)$muted_raw)) === 1,
         'align' => $align,
      );
   }

private function inline_video_align_value($value, array $style = array()) {
      $value = strtolower(trim((string)$value));
      if (in_array($value, array('left', 'center', 'right'), true)) return $value;
      $margin_left = strtolower(trim((string)($style['margin-left'] ?? '')));
      $margin_right = strtolower(trim((string)($style['margin-right'] ?? '')));
      if ($margin_left === 'auto' && $margin_right === 'auto') return 'center';
      if ($margin_left === 'auto') return 'right';
      return 'left';
   }

private function attr_value_from_html($attrs, $name) {
      $name = preg_quote((string)$name, '/');
      if (preg_match('/\s' . $name . '\s*=\s*(["\'])(.*?)\1/i', (string)$attrs, $m)) return trim((string)$m[2]);
      if (preg_match('/\s' . $name . '\s*=\s*([^\s>]+)/i', (string)$attrs, $m)) return trim((string)$m[1], "\"' ");
      return '';
   }

private function css_size_value($value) {
      $value = trim((string)$value);
      if ($value === '') return '';
      if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $value)) return $value . 'px';
      if (preg_match('/^[0-9]+(?:\.[0-9]+)?(?:px|%|vw|vh|rem|em)$/i', $value)) return $value;
      if (strtolower($value) === 'auto') return 'auto';
      return '';
   }

private function safe_inline_video_style($style) {
      $out = array();
      foreach ($this->style_to_map($style) as $key => $value) {
         if (!in_array($key, array('width', 'height', 'max-width', 'float', 'margin-left', 'margin-right', 'display'), true)) continue;
         if (in_array($key, array('width', 'height', 'max-width', 'margin-left', 'margin-right'), true)) {
            $value = $this->css_size_value($value);
            if ($value === '') continue;
         } elseif ($key === 'float') {
            if (!in_array(strtolower($value), array('left', 'right', 'none'), true)) continue;
            $value = strtolower($value);
         } elseif ($key === 'display') {
            if (!in_array(strtolower($value), array('block', 'inline-block'), true)) continue;
            $value = strtolower($value);
         }
         $out[$key] = $value;
      }
      return $this->style_map_to_string($out);
   }

private function style_to_map($style) {
      $out = array();
      foreach (explode(';', (string)$style) as $part) {
         if (strpos($part, ':') === false) continue;
         list($key, $value) = array_map('trim', explode(':', $part, 2));
         $key = strtolower($key);
         if ($key === '' || $value === '') continue;
         $out[$key] = $value;
      }
      return $out;
   }

private function style_map_to_string(array $style) {
      $out = array();
      foreach ($style as $key => $value) {
         if ((string)$value === '') continue;
         $out[] = $key . ': ' . $value;
      }
      return implode('; ', $out);
   }

private function external_video_option_url($url, $autoplay = false, $muted = false, $loop = false) {
      $url = trim((string)$url);
      if ($url === '') return '';
      $join = (strpos($url, '?') === false) ? '?' : '&';
      $params = array('playsinline' => '1', 'rel' => '0');
      if ($autoplay) $params['autoplay'] = '1';
      $params['mute'] = $muted ? '1' : '0';
      if ($loop) {
         $params['loop'] = '1';
         $video_id = $this->youtube_video_id_from_url($url);
         if ($video_id !== '') $params['playlist'] = $video_id;
      }
      foreach ($params as $key => $value) {
         if (preg_match('/(?:[?&])' . preg_quote($key, '/') . '=/i', $url)) continue;
         $url .= $join . rawurlencode($key) . '=' . rawurlencode($value);
         $join = '&';
      }
      return $url;
   }

private function youtube_video_id_from_url($url) {
      $url = trim((string)$url);
      if ($url === '') return '';
      if (preg_match('~(?:embed/|v=|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m)) {
         return (string)$m[1];
      }
      return '';
   }

private function is_youtube_media(array $row, $url) {
      $provider = strtolower(trim((string)($row['provider'] ?? '')));
      if ($provider === 'youtube') return true;
      $url = trim((string)$url);
      if ($url !== '' && preg_match('~(?:youtube\.com|youtu\.be)~i', $url)) return true;
      $embed = trim((string)($row['embed_url'] ?? $row['external_url'] ?? ''));
      if ($embed !== '' && preg_match('~(?:youtube\.com|youtu\.be)~i', $embed)) return true;
      $provider_id = trim((string)($row['provider_id'] ?? ''));
      return $provider_id !== '' && preg_match('~^[A-Za-z0-9_-]{11}$~', $provider_id) && $provider !== 'vimeo';
   }

private function render_youtube_consent_block($embed_url, $title, $loading = 'lazy') {
      $embed_url = trim((string)$embed_url);
      if ($embed_url === '') return '';
      $title = htmlspecialchars(trim((string)$title), ENT_QUOTES, 'UTF-8');
      $loading = ($loading === 'eager') ? 'eager' : 'lazy';
      $video_id = $this->youtube_video_id_from_url($embed_url);
      $thumb = $video_id !== '' ? 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg' : '';
      $thumb_html = $thumb !== ''
         ? '<img class="dbx-youtube-consent-thumb" src="' . htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy">'
         : '';
      return '<div class="dbx-youtube-consent-placeholder"'
         . ' data-youtube-embed-url="' . htmlspecialchars($embed_url, ENT_QUOTES, 'UTF-8') . '"'
         . ' data-youtube-title="' . $title . '"'
         . ' data-youtube-loading="' . $loading . '">'
         . $thumb_html
         . '<button type="button" class="dbx-youtube-consent-play" aria-label="Video abspielen">'
         . '<i class="bi bi-play-fill"></i></button>'
         . '</div>';
   }

private function render_youtube_hero_figure(array $row, $url, array $settings) {
      $url = $this->external_video_option_url($url, false, false, false);
      $title = (string)($row['title'] ?? $row['alt'] ?? $row['file_name'] ?? 'Video');
      $caption = trim((string)($row['caption'] ?? ''));
      $caption_html = $caption !== '' ? '<figcaption>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</figcaption>' : '';
      $hero_variant = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)($settings['hero_variant'] ?? 'original')));
      $hero_scroll = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)($settings['hero_scroll_layer'] ?? 'under')));
      $hero_classes = array(
         'dbx-media',
         'dbx-media-video',
         'dbx-media-external-video',
         'dbx-media-hero',
         'dbx-media-hero-' . ($hero_variant !== '' ? $hero_variant : 'original'),
         $this->is_enabled_value($settings['hero_sticky'] ?? '0') ? 'dbx-media-hero-sticky' : '',
         'dbx-media-hero-scroll-' . ($hero_scroll !== '' ? $hero_scroll : 'under'),
      );
      $style = $this->css_custom_properties(array(
         'cms-hero-margin-top' => $this->css_length_value($settings['hero_margin_top'] ?? '0', '0'),
         'cms-hero-height' => $this->css_length_value($settings['hero_height'] ?? 'auto', 'auto'),
      ));
      return '<figure class="' . htmlspecialchars(implode(' ', array_filter($hero_classes)), ENT_QUOTES, 'UTF-8') . '"'
         . ($style !== '' ? ' style="' . $style . '"' : '')
         . '>' . $this->render_youtube_consent_block($url, $title, 'lazy') . $caption_html . '</figure>';
   }

private function render_media_merge($db, $tpl, $cid, array $slots, array $rec, array $settings = array()) {
      $merge = array(
         'media:hero' => '',
         'media:gallery' => '',
         'media:header' => '',
         'media:teaser' => '',
         'media:footer' => '',
         'media_hero' => '',
         'media_gallery' => '',
         'media_header' => '',
         'media_teaser' => '',
         'media_footer' => '',
      );

      $usage_rows = $db->select(
         $this->dd_media_usage,
         dbxContentMediaUsageScope::with_language('content_id = ' . (int)$cid . ' AND active = 1'),
         '*',
         'slot,sorter,id',
         'ASC',
         '',
         0,
         0,
         0
      );
      $rows = array();
      if (is_array($usage_rows) && !empty($usage_rows)) {
         $media_ids = array_values(array_filter(array_unique(array_map(
            static fn($usage) => (int)($usage['media_id'] ?? 0),
            $usage_rows
         ))));
         $media_by_id = array();
         if ($media_ids) {
            $media_rows = $db->select(
               $this->dd_media,
               'id IN (' . implode(',', $media_ids) . ') AND active = 1',
               '*',
               '',
               'ASC',
               '',
               0,
               0,
               0
            );
            foreach ((is_array($media_rows) ? $media_rows : array()) as $media_row) {
               $media_by_id[(int)($media_row['id'] ?? 0)] = $media_row;
            }
         }
         foreach ($usage_rows as $usage) {
            if (!is_array($usage)) continue;
            $media_id = (int)($usage['media_id'] ?? 0);
            if ($media_id <= 0) continue;
            $row = $media_by_id[$media_id] ?? array();
            if (!$row) continue;
            $row['slot'] = (string)($usage['slot'] ?? 'gallery');
            $row['sorter'] = (string)($usage['sorter'] ?? '');
            if (!empty($usage['template'])) $row['template'] = $usage['template'];
            if (!empty($usage['caption'])) $row['caption'] = $usage['caption'];
            $row['usage_id'] = (int)($usage['id'] ?? 0);
            $rows[] = $row;
         }
      } else {
         $rows = $db->select($this->dd_media, 'content_id = ' . (int)$cid . ' AND active = 1', '*', 'slot,sorter,title,id', 'ASC', '', 0, 0, 0);
      }
      if (!is_array($rows)) return $merge;

      $by_slot = array();
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         if (!$this->media_file_exists($row)) continue;
         $slot = trim((string)($row['slot'] ?? 'gallery')) ?: 'gallery';
         if (!isset($by_slot[$slot])) $by_slot[$slot] = array();
         $by_slot[$slot][] = $row;
      }

      if (!$settings) $settings = $this->content_settings($db, $rec);
      $needed = array_keys($slots['media'] ?? array());
      foreach (array('hero', 'gallery', 'header', 'teaser', 'footer') as $slot) {
         if (!in_array($slot, $needed, true)) continue;
         $rows_for_slot = $by_slot[$slot] ?? array();
         if ($slot === 'hero') {
            if (dbxContentRuntime::is_no_hero($settings['hero_template'] ?? '') || dbxContentRuntime::is_no_hero($settings['hero_image_id'] ?? '')) continue;
            $rows_for_slot = $this->one_hero_row($db, $rows_for_slot, $settings['hero_image_id']);
         }
         if (empty($rows_for_slot)) continue;
         $html = $this->render_media_slot($tpl, $rows_for_slot, $slot, $settings);
         $merge['media:' . $slot] = $html;
         $merge['media_' . $slot] = $html;
      }
      return $merge;
   }

private function one_hero_row($db, array $rows, $hero_id) {
      $hero_id = (int)$hero_id;
      if ($hero_id > 0) {
         $row = $db->select1($this->dd_media, $hero_id);
         if (is_array($row) && (int)($row['active'] ?? 0) === 1 && $this->media_file_exists($row)) return array($row);
      }
      return isset($rows[0]) ? array($rows[0]) : array();
   }

private function render_media_slot($tpl, array $rows, $slot, array $settings) {
      if (!$rows) return '';
      $html = '';
      foreach ($rows as $row) {
         $mime = (string)($row['mime'] ?? '');
         $type = ((string)($row['media_type'] ?? '') === 'external_video')
            ? 'external_video'
            : ((strpos($mime, 'video/') === 0) ? 'video' : ((strpos($mime, 'image/') === 0 || preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', (string)($row['file_name'] ?? ''))) ? 'image' : 'file'));
         $template = trim((string)($row['template'] ?? ''));
         if ($slot === 'hero' && !empty($settings['hero_template'])) $template = $settings['hero_template'];
         if ($slot === 'gallery' && !empty($settings['gallery_template'])) {
            $template = $settings['gallery_template'];
            if ($type === 'image' && $template === 'video-gallery') $template = 'image-gallery';
            if (($type === 'video' || $type === 'external_video') && in_array($template, array('image-gallery', 'carousel3', 'cols3'), true)) $template = $type . '-gallery';
            if ($type === 'file' && in_array($template, array('image-gallery', 'video-gallery', 'carousel3', 'cols3'), true)) $template = 'file-gallery';
         }
         if ($template === '' || strtolower($template) === 'parent') $template = $type . '-' . ($slot === 'gallery' ? 'gallery' : $slot);
         if (!$this->media_template_exists($template)) $template = $type . '-' . ($slot === 'gallery' ? 'gallery' : $slot);
         if (!$this->media_template_exists($template)) $template = 'file-' . ($slot === 'gallery' ? 'gallery' : $slot);

         $url = $type === 'external_video'
            ? (string)($row['embed_url'] ?? $row['external_url'] ?? '')
            : 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . (int)($row['id'] ?? 0);
         if ($type === 'external_video' && $slot === 'hero' && $this->is_youtube_media($row, $url)) {
            $html .= $this->render_youtube_hero_figure($row, $url, $settings);
            continue;
         }
         if ($type === 'external_video') {
            $thumb_url = dbxContentMediaUrl::external_video_thumbnail($row);
            $poster_url = $thumb_url;
         } else {
            $thumb_url = !empty($row['thumb_file_path']) ? $url . '&dbx_thumb=1' : $url;
            $poster_url = !empty($row['thumb_file_path']) ? $url . '&dbx_thumb=1' : '';
            if ($slot === 'gallery'
               && $type === 'image'
               && strtolower(trim((string)($settings['gallery_overflow'] ?? ''))) === 'tutorial') {
               $thumb_url = $url;
            }
         }
         $data = array(
            'id' => (string)($row['id'] ?? ''),
            'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            'thumb_url' => htmlspecialchars($thumb_url, ENT_QUOTES, 'UTF-8'),
            'poster_url' => htmlspecialchars($poster_url, ENT_QUOTES, 'UTF-8'),
            'media_type' => htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
            'title' => htmlspecialchars((string)($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'alt' => htmlspecialchars((string)($row['alt'] ?? $row['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'caption' => htmlspecialchars((string)($row['caption'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'slot' => htmlspecialchars($slot, ENT_QUOTES, 'UTF-8'),
            'mime' => htmlspecialchars($mime, ENT_QUOTES, 'UTF-8'),
            'hero_margin_top' => htmlspecialchars($this->css_length_value($settings['hero_margin_top'] ?? '0', '0'), ENT_QUOTES, 'UTF-8'),
            'hero_height' => htmlspecialchars($this->css_length_value($settings['hero_height'] ?? 'auto', 'auto'), ENT_QUOTES, 'UTF-8'),
            'hero_variant' => htmlspecialchars((string)($settings['hero_variant'] ?? 'original'), ENT_QUOTES, 'UTF-8'),
            'hero_sticky_class' => $this->is_enabled_value($settings['hero_sticky'] ?? '0') ? 'dbx-media-hero-sticky' : '',
            'hero_scroll_class' => 'dbx-media-hero-scroll-' . htmlspecialchars((string)($settings['hero_scroll_layer'] ?? 'under'), ENT_QUOTES, 'UTF-8'),
         );
         $width = max(0, (int)($row['width'] ?? 0));
         $height = max(0, (int)($row['height'] ?? 0));
         $thumb_width = max(0, (int)($row['thumb_width'] ?? 0));
         $thumb_height = max(0, (int)($row['thumb_height'] ?? 0));
         if ($thumb_width <= 0 || $thumb_height <= 0) {
            $thumb_width = $width;
            $thumb_height = $height;
         }
         $visible_count = max(1, min(12, (int)($settings['gallery_visible_count'] ?? 3)));
         $data['image_dimensions'] = ($width > 0 && $height > 0)
            ? ' width="' . $width . '" height="' . $height . '"'
            : '';
         $data['thumb_dimensions'] = ($thumb_width > 0 && $thumb_height > 0)
            ? ' width="' . $thumb_width . '" height="' . $thumb_height . '"'
            : '';
         $data['image_sizes'] = $slot === 'gallery'
            ? '(max-width: 767px) 100vw, ' . (int)ceil(100 / $visible_count) . 'vw'
            : '100vw';
         $html .= $tpl->get_tpl('dbxContent|media-' . $template, $data);
      }
      return $html;
   }

private function media_template_exists($template) {
      $template = preg_replace('/[^a-z0-9_-]+/i', '', (string)$template);
      if ($template === '') return false;
      $path = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxContent/tpl/htm/media-' . strtolower($template) . '.htm');
      return is_file($path);
   }

private function media_file_exists(array $row) {
      if (strtolower((string)($row['storage_type'] ?? '')) === 'external') {
         return trim((string)($row['embed_url'] ?? $row['external_url'] ?? '')) !== '';
      }
      $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return false;

      $root = dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/');
      $file = dbx()->os_path($root . $rel);

      $base = realpath($root);
      $real = realpath($file);
      return $base && $real && strpos($real, $base) === 0 && is_file($real) && is_readable($real);
   }

private function css_class_token($prefix, $value) {
      $prefix = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)$prefix));
      $value = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)$value));
      $value = trim($value, '-');
      if ($value === '') $value = 'default';
      return trim($prefix . '-' . $value, '-');
   }

private function css_style_value($value, $fallback = '') {
      $value = trim((string)$value);
      if ($value === '' || strtolower($value) === 'parent') $value = (string)$fallback;
      $value = preg_replace('/[;"<>]+/', '', $value);
      $value = preg_replace('/[^a-z0-9\s#.,%()+\-\/_]+/i', '', $value);
      return trim($value);
   }

private function css_length_value($value, $fallback = '') {
      $value = $this->css_style_value($value, $fallback);
      if ($value === '') return '';
      if (preg_match('/^-?(?:[0-9]+|[0-9]*\.[0-9]+)$/', $value)) {
         return $value === '0' ? '0' : $value . 'px';
      }
      return $value;
   }

private function hero_content_custom_properties($height) {
      $scale = $this->hero_content_scale($height);
      return array(
         'cms-hero-content-scale' => $this->css_number($scale),
         'cms-hero-content-size' => $this->css_px(18 * $scale),
         'cms-hero-title-size' => $this->css_px(46 * $scale),
         'cms-hero-subtitle-size' => $this->css_px(30 * $scale),
         'cms-hero-content-padding' => $this->css_px(10 * $scale),
         'cms-hero-content-offset' => $this->css_px(34 * $scale),
      );
   }

private function hero_content_scale($height) {
      $px = $this->css_length_to_px($height);
      if ($px <= 0) return 1.0;

      $scale = $px / 420.0;
      if ($scale < 0.72) $scale = 0.72;
      if ($scale > 1.85) $scale = 1.85;
      return $scale;
   }

private function css_length_to_px($value) {
      $value = strtolower(trim($this->css_length_value($value, 'auto')));
      if ($value === '' || $value === 'auto' || $value === 'parent') return 0.0;

      if (preg_match('/^([0-9]+(?:\.[0-9]+)?)px$/', $value, $m)) return (float)$m[1];
      if (preg_match('/^([0-9]+(?:\.[0-9]+)?)$/', $value, $m)) return (float)$m[1];
      if (preg_match('/^([0-9]+(?:\.[0-9]+)?)(rem|em)$/', $value, $m)) return (float)$m[1] * 16.0;
      if (preg_match('/^([0-9]+(?:\.[0-9]+)?)(vh|svh|lvh|dvh)$/', $value, $m)) return (float)$m[1] * 7.2;
      if (preg_match('/^([0-9]+(?:\.[0-9]+)?)vw$/', $value, $m)) return (float)$m[1] * 12.8;

      return 0.0;
   }

private function css_px($value) {
      return $this->css_number($value) . 'px';
   }

private function css_number($value) {
      $value = (float)$value;
      $out = number_format($value, 3, '.', '');
      return rtrim(rtrim($out, '0'), '.');
   }

private function css_custom_properties(array $props) {
      $out = array();
      foreach ($props as $name => $value) {
         $name = preg_replace('/[^a-z0-9_-]+/i', '-', (string)$name);
         $value = trim((string)$value);
         if ($name === '' || $value === '') continue;
         $out[] = '--' . strtolower($name) . ':' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
      }
      return implode(';', $out);
   }

private function gallery_aspect_ratio($size) {
      if (preg_match('/^\s*([0-9]{1,5})\s*x\s*([0-9]{1,5})\s*$/i', (string)$size, $m)) {
         $w = max(1, (int)$m[1]);
         $h = max(1, (int)$m[2]);
         return $w . ' / ' . $h;
      }
      return '4 / 3';
   }

private function gallery_data_dbx_attrs(array $settings, bool $has_slot, bool $has_media): string {
      if (!$has_slot || !$has_media) {
         return '';
      }

      $parts = array(
         'lib=gallery',
         'module=dbxContent',
         'img-count=' . max(1, min(12, (int)($settings['gallery_visible_count'] ?? 3))),
         'img-size=' . trim((string)($settings['gallery_image_size'] ?? 'original')),
         'lightbox-width=' . $this->css_length_value($settings['gallery_lightbox_width'] ?? '100vw', '100vw'),
         'overflow=' . trim((string)($settings['gallery_overflow'] ?? 'grid')),
         'click=' . trim((string)($settings['gallery_click_behavior'] ?? 'lightbox')),
      );

      return ' data-dbx="' . htmlspecialchars(implode('|', $parts), ENT_QUOTES, 'UTF-8') . '"';
   }
}
