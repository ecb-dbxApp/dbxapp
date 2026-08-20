<?php

namespace dbx\dbxContent;

/** Interne Komponente von dbxContentRenderer. */
trait dbxContentRendererLayoutTrait {

private function detect_template_slots($template_html) {
      $template_html = (string)$template_html;
      $slots = array(
         'content' => array(),
         'media' => array(),
         'cols' => 1,
         'cms' => array(),
      );

      if (preg_match_all('/\{content:([a-z0-9_]+)\}/i', $template_html, $m)) {
         foreach ($m[1] as $slot) {
            $slots['content'][strtolower($slot)] = 1;
         }
      }
      if (preg_match_all('/\{media:([a-z0-9_]+)\}/i', $template_html, $m)) {
         foreach ($m[1] as $slot) {
            $slots['media'][strtolower($slot)] = 1;
         }
      }
      if (preg_match_all('/\{cms:([a-z0-9_]+)\}/i', $template_html, $m)) {
         foreach ($m[1] as $slot) {
            $slot = strtolower($slot);
            $slots['cms'][$slot] = 1;
            if (in_array($slot, array('hero', 'gallery'), true)) $slots['media'][$slot] = 1;
            if ($slot === 'hero') $slots['content']['hero'] = 1;
            if ($slot === 'header') $slots['content']['header'] = 1;
            if ($slot === 'teaser') $slots['content']['teaser'] = 1;
            if ($slot === 'content') $slots['content']['content'] = 1;
            if ($slot === 'footer') $slots['content']['footer'] = 1;
            if (in_array($slot, array('col1', 'col2', 'col3'), true)) $slots['content'][$slot] = 1;
         }
      }

      if (strpos($template_html, '{content}') !== false || strpos($template_html, '{content:body}') !== false) {
         $slots['content']['content'] = 1;
      }

      if (isset($slots['content']['col3']) || strpos($template_html, '{col_3}') !== false || strpos($template_html, '{cms:col3}') !== false) $slots['cols'] = 3;
      elseif (isset($slots['content']['col2']) || strpos($template_html, '{col_2}') !== false || strpos($template_html, '{cms:col2}') !== false) $slots['cols'] = 2;
      elseif (isset($slots['content']['col1']) || strpos($template_html, '{col_1}') !== false || strpos($template_html, '{cms:col1}') !== false) $slots['cols'] = 1;
      else $slots['cols'] = 1;

      return $slots;
   }

private function parse_content($html, array $slots) {
      $blocks = $this->html_blocks($html);
      $segments = $this->segment_content_blocks($blocks);

      $hero_blocks = $segments['hero'];
      $header_blocks = $segments['header'];
      $footer_blocks = $segments['footer'];
      $body_blocks = $segments['content'];

      $has_hero_slot = !empty($slots['content']['hero']) || !empty($slots['cms']['hero']);
      $has_header_slot = !empty($slots['content']['header']) || !empty($slots['content']['teaser'])
         || !empty($slots['content']['thesar']) || !empty($slots['cms']['header']);
      $has_footer_slot = !empty($slots['content']['footer']) || !empty($slots['cms']['footer']);

      if (!$has_hero_slot) {
         $body_blocks = array_merge($hero_blocks, $body_blocks);
         $hero_blocks = array();
      }
      if (!$has_header_slot) {
         $body_blocks = array_merge($header_blocks, $body_blocks);
         $header_blocks = array();
      }
      if (!$has_footer_slot) {
         $body_blocks = array_merge($body_blocks, $footer_blocks);
         $footer_blocks = array();
      }

      $cols = max(1, min(3, (int)($slots['cols'] ?? 1)));
      $col_blocks = $this->split_content_for_columns($body_blocks, $cols);
      $all_content = $this->strip_dbx_markers($this->join_blocks($blocks));
      $body_html = $this->strip_dbx_markers($this->join_blocks($body_blocks));

      return array(
         'h1' => '',
         'hero' => $this->strip_dbx_markers($this->join_blocks($hero_blocks)),
         'header' => $this->strip_dbx_markers($this->join_blocks($header_blocks)),
         'teaser' => $this->strip_dbx_markers($this->join_blocks($header_blocks)),
         'footer' => $this->strip_dbx_markers($this->join_blocks($footer_blocks)),
         'body' => $body_html,
         'content' => (isset($slots['content']['content']) || $cols === 1) ? $body_html : $all_content,
         'col1' => $cols === 1 ? $body_html : $this->strip_dbx_markers($this->join_blocks($col_blocks[0] ?? array())),
         'col2' => $this->strip_dbx_markers($this->join_blocks($col_blocks[1] ?? array())),
         'col3' => $this->strip_dbx_markers($this->join_blocks($col_blocks[2] ?? array())),
      );
   }

private function html_blocks($html) {
      $html = trim((string)$html);
      if ($html === '') return array();

      $pattern = '/(<!--\s*dbx:[^>]*?-->|<hr\b[^>]*>|<(h1|h2|h3|h4|h5|h6|p|ul|ol|blockquote|figure|table|div)\b[^>]*>.*?<\/\2>|<img\b[^>]*>)/is';
      preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE);

      $blocks = array();
      $pos = 0;
      foreach ($matches[0] as $idx => $match) {
         $before = trim(substr($html, $pos, $match[1] - $pos));
         if ($before !== '') $blocks[] = array('type' => 'html', 'html' => $before);

         $chunk = $match[0];
         $type = strtolower((string)($matches[2][$idx][0] ?? 'html'));
         if (preg_match('/^<!--\s*dbx:/i', $chunk)) $type = 'marker';
         elseif (preg_match('/^<hr/i', $chunk)) $type = $this->marker_name_from_html($chunk) !== '' ? 'marker' : 'break';
         elseif (preg_match('/^<img/i', $chunk)) $type = 'img';

         $blocks[] = array('type' => $type, 'html' => $chunk);
         $pos = $match[1] + strlen($chunk);
      }

      $rest = trim(substr($html, $pos));
      if ($rest !== '') $blocks[] = array('type' => 'html', 'html' => $rest);
      return $blocks;
   }

private function segment_content_blocks(array $blocks) {
      $hero_idx = null;
      $header_idx = null;
      $footer_idx = null;
      $footer_hr_idx = null;

      foreach ($blocks as $idx => $block) {
         if ($hero_idx === null && $this->is_dbx_marker($block, array('hero'))) {
            $hero_idx = $idx;
         }
         if ($header_idx === null && $this->is_dbx_marker($block, array('header', 'teaser', 'thesar'))) {
            $header_idx = $idx;
         }
         if ($this->is_dbx_marker($block, array('footer'))) {
            if ($footer_idx === null) $footer_idx = $idx;
            if ($footer_hr_idx === null && $this->is_hr_marker($block)) $footer_hr_idx = $idx;
         }
      }
      if ($footer_hr_idx !== null) $footer_idx = $footer_hr_idx;

      $result = array(
         'hero' => array(),
         'header' => array(),
         'content' => array(),
         'footer' => array(),
      );
      $n = count($blocks);
      $has_hero_marker = $hero_idx !== null;

      $markers = array();
      if ($hero_idx !== null) $markers[] = array('type' => 'hero', 'idx' => $hero_idx);
      if ($header_idx !== null) $markers[] = array('type' => 'header', 'idx' => $header_idx);
      if ($footer_idx !== null) $markers[] = array('type' => 'footer', 'idx' => $footer_idx);
      usort($markers, function($a, $b) {
         return (int)$a['idx'] <=> (int)$b['idx'];
      });

      $pos = 0;
      foreach ($markers as $marker) {
         $idx = (int)$marker['idx'];
         $gap = $idx > $pos ? array_slice($blocks, $pos, $idx - $pos) : array();
         if ($marker['type'] === 'hero') {
            $result['hero'] = $gap;
            $pos = $idx + 1;
            continue;
         }
         if ($marker['type'] === 'header') {
            if ($has_hero_marker && $hero_idx < $idx) {
               $result['header'] = $gap;
            } else {
               $result['content'] = array_merge($result['content'], $gap);
            }
            $pos = $idx + 1;
            continue;
         }
         if ($marker['type'] === 'footer') {
            $result['content'] = array_merge($result['content'], $gap);
            $result['footer'] = array_slice($blocks, $idx + 1);
            return $result;
         }
      }

      if ($pos < $n) {
         $result['content'] = array_merge($result['content'], array_slice($blocks, $pos));
      }
      return $result;
   }

private function split_content_for_columns(array $blocks, $cols) {
      $cols = max(1, min(3, (int)$cols));
      $result = array_fill(0, $cols, array());
      if ($cols === 1) {
         $result[0] = $blocks;
         return $result;
      }

      $marker_split = $this->split_by_column_markers($blocks, $cols);
      if ($marker_split !== null) return $marker_split;

      return $this->split_by_text_weight($blocks, $cols);
   }

private function split_by_column_markers(array $blocks, $cols) {
      $result = array_fill(0, $cols, array());
      $current = 0;
      $has = false;
      foreach ($blocks as $block) {
         if ($cols === 2 && $this->is_dbx_marker($block, array('col2', 'split'))) {
            $has = true;
            $current = 1;
            continue;
         }
         if ($cols === 3 && $this->is_dbx_marker($block, array('col2', 'col3a'))) {
            $has = true;
            $current = 1;
            continue;
         }
         if ($cols === 3 && $this->is_dbx_marker($block, array('col3', 'col3b'))) {
            $has = true;
            $current = 2;
            continue;
         }
         $result[$current][] = $block;
      }
      return $has ? $result : null;
   }

private function split_by_text_weight(array $blocks, $cols) {
      $units = $this->column_units_from_blocks($blocks, $cols);
      $result = array_fill(0, $cols, array());
      if (empty($units)) return $result;

      $parts = $this->partition_column_units($units, $cols);
      $parts = $this->keep_leading_blanks_with_previous_column($parts);
      foreach ($parts as $idx => $part) {
         if ($idx >= $cols) break;
         $result[$idx] = $this->column_units_to_blocks($part);
      }
      return $result;
   }

private function column_units_from_blocks(array $blocks, $cols) {
      $units = array();
      $list_index = 0;
      $paragraph_index = 0;

      foreach ($blocks as $block) {
         $type = strtolower((string)($block['type'] ?? 'html'));
         if ($type === 'ul' || $type === 'ol') {
            $list_units = $this->list_units_from_block($block, 'list-' . $list_index, $cols);
            $list_index++;
            if ($list_units !== null) {
               $units = array_merge($units, $list_units);
               continue;
            }
         }
         if ($type === 'p') {
            $paragraph_units = $this->paragraph_units_from_block($block, 'p-' . $paragraph_index, $cols);
            $paragraph_index++;
            if ($paragraph_units !== null) {
               $units = array_merge($units, $paragraph_units);
               continue;
            }
         }

         $units[] = array(
            'kind' => 'block',
            'block' => $block,
            'weight' => $this->estimate_block_weight($block, $cols),
         );
      }

      return $units;
   }

private function paragraph_units_from_block(array $block, $paragraph_key, $cols) {
      $html = trim((string)($block['html'] ?? ''));
      if ($html === '' || $this->is_blank_flow_html($html)) return null;
      if (preg_match('/<(img|video|table|ul|ol|figure|blockquote|div|section|article|aside|header|footer|h[1-6]|pre)\b/i', $html)) return null;
      if (!preg_match('/^<\s*p\b([^>]*)>([\s\S]*)<\/\s*p\s*>$/i', $html, $m)) return null;

      $attrs = (string)$m[1];
      $chunks = $this->inline_safe_chunks((string)$m[2], $cols);
      if (count($chunks) < 5) return null;

      $weights = array();
      $total = 0;
      foreach ($chunks as $chunk) {
         $weight = $this->estimate_inline_chunk_weight($chunk, $cols);
         $weights[] = $weight;
         $total += $weight;
      }

      $threshold = max(260, ((int)$cols === 3 ? 240 : 320));
      if ($total <= $threshold) return null;

      $units = array();
      foreach ($chunks as $idx => $chunk) {
         if ($chunk === '') continue;
         $units[] = array(
            'kind' => 'paragraph_piece',
            'paragraph_key' => $paragraph_key,
            'attrs' => $attrs,
            'html' => $chunk,
            'weight' => max(0, (int)($weights[$idx] ?? 0)),
         );
      }

      return count($units) > 1 ? $units : null;
   }

private function inline_safe_chunks($inner_html, $cols) {
      $tokens = preg_split('/(<[^>]+>)/', (string)$inner_html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
      if (!is_array($tokens) || empty($tokens)) return array();

      $chunks = array();
      $count = count($tokens);
      $chunk_limit = $this->column_chars_per_line($cols);
      for ($i = 0; $i < $count; $i++) {
         $token = $tokens[$i];
         if (preg_match('/^<[^>]+>$/', $token)) {
            $tag = $this->tag_name($token);
            if ($tag !== '' && $this->is_opening_tag($token) && !$this->is_void_tag($tag) && !preg_match('/\/\s*>$/', $token)) {
               $chunk = $token;
               $depth = 1;
               while ($depth > 0 && $i + 1 < $count) {
                  $i++;
                  $next = $tokens[$i];
                  $chunk .= $next;
                  if (!preg_match('/^<[^>]+>$/', $next)) continue;
                  $next_tag = $this->tag_name($next);
                  if ($next_tag !== $tag) continue;
                  if ($this->is_closing_tag($next)) {
                     $depth--;
                  } elseif ($this->is_opening_tag($next) && !preg_match('/\/\s*>$/', $next)) {
                     $depth++;
                  }
               }
               $chunks[] = $chunk;
               continue;
            }
            $chunks[] = $token;
            continue;
         }

         if (preg_match_all('/\S+\s*/u', $token, $words)) {
            $buffer = '';
            foreach ($words[0] as $word) {
               $candidate = $buffer . $word;
               if ($buffer !== '' && $this->text_length(strip_tags($candidate)) > $chunk_limit) {
                  $chunks[] = $buffer;
                  $buffer = $word;
               } else {
                  $buffer = $candidate;
               }
            }
            if ($buffer !== '') $chunks[] = $buffer;
         }
      }

      return $chunks;
   }

private function tag_name($tag) {
      return preg_match('/^<\s*\/?\s*([a-z0-9]+)/i', (string)$tag, $m) ? strtolower($m[1]) : '';
   }

private function is_opening_tag($tag) {
      return preg_match('/^<\s*[a-z0-9]/i', (string)$tag) === 1;
   }

private function is_closing_tag($tag) {
      return preg_match('/^<\s*\//', (string)$tag) === 1;
   }

private function is_void_tag($tag) {
      return in_array(strtolower((string)$tag), array('area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr'), true);
   }

private function estimate_inline_chunk_weight($html, $cols) {
      $html = (string)$html;
      $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
      $chars = $this->text_length($text);
      $weight = (int)ceil(($chars / max(1, $this->column_chars_per_line($cols))) * 100);
      $weight += substr_count(strtolower($html), '<br') * 100;
      return max(1, $weight);
   }

private function list_units_from_block(array $block, $list_key, $cols) {
      $html = trim((string)($block['html'] ?? ''));
      if (!preg_match('/^<\s*(ul|ol)\b([^>]*)>([\s\S]*)<\/\s*\1\s*>$/i', $html, $m)) return null;

      $tag = strtolower($m[1]);
      $attrs = (string)$m[2];
      $items = $this->extract_top_level_list_items((string)$m[3]);
      if (count($items) < 2) return null;

      $units = array();
      foreach ($items as $item_html) {
         $units[] = array(
            'kind' => 'list_item',
            'list_key' => $list_key,
            'tag' => $tag,
            'attrs' => $attrs,
            'html' => $item_html,
            'weight' => $this->estimate_list_item_weight($item_html, $cols),
         );
      }
      return $units;
   }

private function extract_top_level_list_items($inner_html) {
      $inner_html = (string)$inner_html;
      if (!preg_match_all('/<li\b[^>]*>|<\/li\s*>/i', $inner_html, $matches, PREG_OFFSET_CAPTURE)) {
         return array();
      }

      $items = array();
      $depth = 0;
      $start = null;

      foreach ($matches[0] as $match) {
         $tag = $match[0];
         $offset = $match[1];
         if (preg_match('/^<li\b/i', $tag)) {
            if ($depth === 0) $start = $offset;
            $depth++;
            continue;
         }

         if ($depth === 0) continue;
         $depth--;
         if ($depth === 0 && $start !== null) {
            $items[] = substr($inner_html, $start, $offset + strlen($tag) - $start);
            $start = null;
         }
      }

      return $depth === 0 ? $items : array();
   }

private function estimate_list_item_weight($html, $cols) {
      $nested_items = max(0, substr_count(strtolower((string)$html), '<li') - 1);
      return max(100, $this->estimate_text_weight((string)$html, $cols, 1) + ($nested_items * 120));
   }

private function partition_column_units(array $units, $cols) {
      $cols = max(1, min(3, (int)$cols));
      $n = count($units);
      if ($cols === 1 || $n === 0) return array($units);

      if ($n < $cols) {
         $parts = array_fill(0, $cols, array());
         foreach ($units as $idx => $unit) $parts[$idx][] = $unit;
         return $parts;
      }

      $prefix = array(0);
      foreach ($units as $unit) {
         $prefix[] = end($prefix) + max(0, (int)($unit['weight'] ?? 0));
      }

      $total = $prefix[$n];
      $target = $total > 0 ? $total / $cols : 1;
      $inf = PHP_INT_MAX;
      $dp = array();
      $prev = array();

      for ($c = 0; $c <= $cols; $c++) {
         $dp[$c] = array_fill(0, $n + 1, $inf);
         $prev[$c] = array_fill(0, $n + 1, -1);
      }

      for ($i = 1; $i <= $n; $i++) {
         $dp[1][$i] = $this->column_partition_cost($prefix, 0, $i, $target);
         $prev[1][$i] = 0;
      }

      for ($c = 2; $c <= $cols; $c++) {
         for ($i = $c; $i <= $n; $i++) {
            for ($j = $c - 1; $j < $i; $j++) {
               if ($dp[$c - 1][$j] === $inf) continue;
               $cost = $dp[$c - 1][$j] + $this->column_partition_cost($prefix, $j, $i, $target);
               if ($cost < $dp[$c][$i]) {
                  $dp[$c][$i] = $cost;
                  $prev[$c][$i] = $j;
               }
            }
         }
      }

      $parts = array_fill(0, $cols, array());
      $i = $n;
      for ($c = $cols; $c >= 1; $c--) {
         $j = $prev[$c][$i];
         if ($j < 0) $j = max(0, $i - 1);
         $parts[$c - 1] = array_slice($units, $j, $i - $j);
         $i = $j;
      }

      return $parts;
   }

private function keep_leading_blanks_with_previous_column(array $parts) {
      for ($i = 1; $i < count($parts); $i++) {
         while (!empty($parts[$i]) && $this->is_blank_column_unit($parts[$i][0])) {
            $parts[$i - 1][] = array_shift($parts[$i]);
         }
      }
      return $parts;
   }

private function is_blank_column_unit(array $unit) {
      if (($unit['kind'] ?? '') !== 'block' || !isset($unit['block']) || !is_array($unit['block'])) return false;
      $block = $unit['block'];
      return strtolower((string)($block['type'] ?? '')) === 'p' && $this->is_blank_flow_html((string)($block['html'] ?? ''));
   }

private function column_partition_cost(array $prefix, $start, $end, $target) {
      $sum = $prefix[$end] - $prefix[$start];
      $diff = $sum - $target;
      return $diff * $diff;
   }

private function column_units_to_blocks(array $units) {
      $blocks = array();
      $pending = null;

      foreach ($units as $unit) {
         if (($unit['kind'] ?? '') === 'list_item') {
            $key = (string)($unit['list_key'] ?? '');
            if ($pending === null || ($pending['kind'] ?? '') !== 'list' || $pending['list_key'] !== $key) {
               $this->flush_pending_column_group($pending, $blocks);
               $pending = array(
                  'kind' => 'list',
                  'list_key' => $key,
                  'tag' => (string)($unit['tag'] ?? 'ul'),
                  'attrs' => (string)($unit['attrs'] ?? ''),
                  'items' => array(),
               );
            }
            $pending['items'][] = (string)($unit['html'] ?? '');
            continue;
         }
         if (($unit['kind'] ?? '') === 'paragraph_piece') {
            $key = (string)($unit['paragraph_key'] ?? '');
            if ($pending === null || ($pending['kind'] ?? '') !== 'paragraph' || $pending['paragraph_key'] !== $key) {
               $this->flush_pending_column_group($pending, $blocks);
               $pending = array(
                  'kind' => 'paragraph',
                  'paragraph_key' => $key,
                  'attrs' => (string)($unit['attrs'] ?? ''),
                  'pieces' => array(),
               );
            }
            $pending['pieces'][] = (string)($unit['html'] ?? '');
            continue;
         }
         $this->flush_pending_column_group($pending, $blocks);
         if (isset($unit['block']) && is_array($unit['block'])) $blocks[] = $unit['block'];
      }

      $this->flush_pending_column_group($pending, $blocks);
      return $blocks;
   }

private function flush_pending_column_group(&$pending, array &$blocks) {
      if ($pending === null) return;
      $kind = (string)($pending['kind'] ?? '');
      $tag = (string)($pending['tag'] ?? '');
      if ($kind === 'list') {
         $tag = preg_match('/^(ul|ol)$/i', $tag) ? strtolower($tag) : 'ul';
         $html = '<' . $tag . (string)$pending['attrs'] . '>' . implode('', $pending['items']) . '</' . $tag . '>';
      } elseif ($kind === 'paragraph') {
         $tag = 'p';
         $html = '<p' . (string)$pending['attrs'] . '>' . implode('', $pending['pieces']) . '</p>';
      } else {
         $pending = null;
         return;
      }
      $blocks[] = array(
         'type' => $tag,
         'html' => $html,
      );
      $pending = null;
   }

private function estimate_block_weight(array $block, $cols) {
      $html = (string)($block['html'] ?? '');
      $type = strtolower((string)($block['type'] ?? 'html'));

      if ($type === 'marker') return 0;
      if ($type === 'img') return $this->estimate_media_weight($html, $cols);
      if ($type === 'figure') return $this->estimate_media_weight($html, $cols) + min(300, $this->estimate_text_weight($html, $cols, 0));
      if ($type === 'table') return 500 + substr_count(strtolower($html), '<tr') * 100;
      if ($type === 'ul' || $type === 'ol') return max(200, substr_count(strtolower($html), '<li') * 100 + $this->estimate_text_weight($html, $cols, 0));
      if ($type === 'p' && preg_match('/<(img|video)\b/i', $html)) return $this->estimate_media_weight($html, $cols) + min(300, $this->estimate_text_weight($html, $cols, 0));
      if ($type === 'p' && $this->is_blank_flow_html($html)) return 0;
      if (in_array($type, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'), true)) return max(130, $this->estimate_text_weight($html, $cols, 1) + 60);
      if ($type === 'blockquote') return max(180, $this->estimate_text_weight($html, $cols, 1) + 80);

      return max(60, $this->estimate_text_weight($html, $cols, 1));
   }

private function estimate_text_weight($html, $cols, $min_lines = 0) {
      $html = (string)$html;
      $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
      $chars = $this->text_length($text);
      $lines = $chars > 0 ? ($chars / max(1, $this->column_chars_per_line($cols))) : 0;
      $lines += substr_count(strtolower($html), '<br');
      $lines = max((float)$min_lines, $lines);
      return (int)ceil($lines * 100);
   }

private function estimate_media_weight($html, $cols) {
      if (!preg_match_all('/<(img|video)\b[^>]*>/i', (string)$html, $matches)) {
         return $this->default_media_lines($cols) * 100;
      }

      $weight = 0;
      foreach ($matches[0] as $tag) {
         $weight += $this->estimate_media_lines_from_tag($tag, $cols) * 100;
      }
      return max($this->default_media_lines($cols) * 100, $weight);
   }

private function estimate_media_lines_from_tag($tag, $cols) {
      $default = $this->default_media_lines($cols);
      $width = 0;
      $height = 0;
      if (preg_match('/\bwidth=["\']?([0-9.]+)/i', (string)$tag, $m)) $width = (float)$m[1];
      if (preg_match('/\bheight=["\']?([0-9.]+)/i', (string)$tag, $m)) $height = (float)$m[1];
      if ($width <= 0 || $height <= 0) return $default;

      $ratio = $height / $width;
      $base = ((int)$cols === 3) ? 14 : 19;
      return max(8, min(24, (int)round($ratio * $base)));
   }

private function default_media_lines($cols) {
      return ((int)$cols === 3) ? 10 : 14;
   }

private function column_chars_per_line($cols) {
      if ((int)$cols === 3) return 42;
      if ((int)$cols === 2) return 58;
      return 78;
   }

private function text_length($text) {
      $text = (string)$text;
      return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
   }

private function is_blank_flow_html($html) {
      $html = (string)$html;
      if (preg_match('/<(img|video|picture|source|iframe|embed|object|svg|canvas)\b/i', $html)) return false;
      $html = preg_replace('/<\/?p\b[^>]*>/i', '', $html);
      $html = preg_replace('/<br\s*\/?>/i', '', $html);
      $html = str_replace('&nbsp;', '', $html);
      return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) === '';
   }

private function join_blocks(array $blocks) {
      $html = '';
      foreach ($blocks as $block) {
         if ($this->is_dbx_marker($block, array('pagebreak'))) {
            $html .= '<div class="dbx-content-pagebreak" aria-hidden="true"></div>' . "\n";
            continue;
         }
         if (($block['type'] ?? '') === 'marker') continue;
         $html .= ($block['html'] ?? '') . "\n";
      }
      return trim($html);
   }

private function is_dbx_marker(array $block, array $names) {
      if (($block['type'] ?? '') !== 'marker') return false;
      $name = $this->marker_name_from_html((string)($block['html'] ?? ''));
      return $name !== '' && in_array($name, $names, true);
   }

private function is_hr_marker(array $block) {
      if (($block['type'] ?? '') !== 'marker') return false;
      return preg_match('/^\s*<hr\b/i', (string)($block['html'] ?? '')) === 1;
   }

private function marker_name_from_html($html) {
      $html = (string)$html;
      if (preg_match('/<!--\s*dbx:([a-z0-9_-]+)/i', $html, $m)) return $this->canonical_marker_name($m[1]);
      if (preg_match('/\bdata-dbx-marker=["\'](?:dbx:)?([a-z0-9_-]+)["\']/i', $html, $m)) return $this->canonical_marker_name($m[1]);
      if (preg_match('/\bclass=["\'][^"\']*\bdbx-cms-marker-([a-z0-9_-]+)\b/i', $html, $m)) return $this->canonical_marker_name($m[1]);
      if (preg_match('/\bclass=["\'][^"\']*\bdbx_marker_([a-z0-9_-]+)\b/i', $html, $m)) return $this->canonical_marker_name($m[1]);
      if (preg_match('/\bclass=["\'][^"\']*\bdbx_split\b/i', $html)) return 'col2';
      return '';
   }

private function canonical_marker_name($name) {
      $name = strtolower(trim((string)$name));
      if (in_array($name, array('hero_text', 'hero-text', 'herotext'), true)) return 'hero';
      return $name;
   }

private function convert_mod_placeholders($html) {
      $html = (string)$html;
      if ($html === '' || stripos($html, 'dbx-cms-mod-placeholder') === false) return $html;

      return preg_replace_callback(
         '/<(p|figure|div)\b([^>]*\bdbx-cms-mod-placeholder\b[^>]*)>([\s\S]*?)<\/\1>/i',
         function($m) {
            $attrs = (string)($m[2] ?? '');
            $inner = (string)($m[3] ?? '');
            $modul = '';
            $params = '';
            $data_dbx = '';
            $alt = '';

            if (preg_match('/\bdata-cms-mod-modul=["\']([^"\']*)["\']/i', $attrs, $mm)) {
               $modul = trim((string)$mm[1]);
            }
            if (preg_match('/\bdata-cms-mod-params=["\']([^"\']*)["\']/i', $attrs, $mm)) {
               $params = trim((string)$mm[1]);
            }
            if ($modul === '' && preg_match('/\bdata-cms-mod-modul=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $modul = trim((string)$mm[1]);
            }
            if ($params === '' && preg_match('/\bdata-cms-mod-params=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $params = trim((string)$mm[1]);
            }
            if ($modul === '' && preg_match('/<img\b[^>]*\bdata-cms-mod-modul=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $modul = trim((string)$mm[1]);
            }
            if ($params === '' && preg_match('/<img\b[^>]*\bdata-cms-mod-params=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $params = trim((string)$mm[1]);
            }
            if (preg_match('/<img\b[^>]*\bdata-dbx=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $data_dbx = trim((string)$mm[1]);
            }
            if (preg_match('/<img\b[^>]*\balt=["\']([^"\']*)["\']/i', $inner, $mm)) {
               $alt = trim((string)$mm[1]);
            }

            if (($modul === '' || $params === '') && preg_match('/modul_image[^"\']*file=([^"\'&]+)/i', $inner . $attrs, $file_match)) {
               $base = dirname(__DIR__, 3) . '/dbxAdmin/include/dbxModuleImages.class.php';
               if (is_file($base)) {
                  require_once $base;
                  $resolved = (new \dbx\dbxAdmin\dbxModuleImages())->resolve_from_filename(rawurldecode((string)$file_match[1]));
                  if (is_array($resolved) && $resolved) {
                     if ($modul === '') {
                        $modul = (string)($resolved['default_modul'] ?? '');
                     }
                     if ($params === '') {
                        $params = (string)($resolved['default_params'] ?? '');
                     }
                  }
               }
            }

            $modul = preg_replace('/[^A-Za-z0-9_]+/', '', $modul);
            if ($modul === '') return $m[0];

            $params = str_replace(array("\r", "\n"), '', $params);
            $marker = '[modul=' . $modul . ']' . $params . '[/modul]';
            $data_dbx = htmlspecialchars($data_dbx, ENT_QUOTES, 'UTF-8');
            $alt_attr = $alt !== '' ? ' title="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' : '';

            if ($data_dbx !== '') {
               return '<div class="dbx-cms-mod-output" data-dbx="' . $data_dbx . '"' . $alt_attr . '>' . $marker . '</div>';
            }
            return $marker;
         },
         $html
      );
   }

private function strip_dbx_markers($html) {
      $html = preg_replace('/<!--\s*dbx:[\s\S]*?-->/i', '', (string)$html);
      $html = preg_replace('/<hr\b[^>]*(?:data-dbx-marker|dbx-cms-marker|dbx_marker_|dbx_split)[^>]*>/i', '', $html);
      return trim($html);
   }

private function replace_content_markers($html, array $merge) {
      foreach ($merge as $key => $value) {
         if (strpos($key, ':') !== false) {
            $html = str_replace('{' . $key . '}', $value, $html);
         }
      }
      return $html;
   }

private function render_inline($tpl, $slot, $value, $obj_key = '') {
      if (!$this->html_has_visible_content($value)) return '';
      $file = 'i-' . $slot;
      $data_key = $obj_key !== '' ? $obj_key : $slot;
      $path = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxContent/tpl/htm/' . strtolower($file) . '.htm');
      if (!is_file($path)) return $value;
      $html = $tpl->get_tpl('dbxContent|' . $file, array('obj:' . $data_key => $value, $data_key => $value));
      if (strpos($html, 'not found') !== false || trim($html) === '') return $value;
      $html = str_replace('{obj:' . $data_key . '}', $value, $html);
      return $html;
   }

private function html_has_visible_content($html) {
      $html = $this->strip_dbx_markers((string)$html);
      if (trim($html) === '') return false;
      if (stripos($html, '[modul=') !== false) return true;
      if (preg_match('/<(img|video|iframe|source|object|embed|table|ul|ol|li|figure)\b/i', $html)) return true;
      $text = preg_replace('/<!--[\s\S]*?-->/u', '', $html);
      $text = preg_replace('/<br\s*\/?>/iu', '', $text);
      $text = strip_tags($text);
      $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $text = str_replace("\xC2\xA0", ' ', $text);
      return trim($text) !== '';
   }

private function render_title_slot($tpl, $template_html, $title) {
      $title = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
      if (preg_match('/<h[1-6]\b[^>]*>[^<]*\{cms:title\}/i', (string)$template_html)) {
         return $title;
      }
      return $this->render_inline($tpl, 'title', $title);
   }

private function cleanup_empty_sections($html) {
      $html = preg_replace('/<section\b[^>]*class="[^"]*\bcms-header\b[^"]*"[^>]*>\s*<\/section>/i', '', (string)$html);
      $html = preg_replace('/<section\b[^>]*class="[^"]*\bgallery\b[^"]*\bno-gallery\b[^"]*"[^>]*>[\s\S]*?<\/section>/i', '', (string)$html);
      $html = preg_replace('/<section\b([^>]*class="[^"]*\bdbx-content-(?:gallery-section|teaser-section|header-text)\b[^"]*"[^>]*)>\s*<\/section>/i', '', (string)$html);
      $html = preg_replace('/<footer\b([^>]*class="[^"]*\bdbx-content-footer\b[^"]*"[^>]*)>\s*<\/footer>/i', '', $html);
      $html = preg_replace('/<div\b([^>]*class="[^"]*\bdbx-content-teaser\b[^"]*"[^>]*)>\s*<\/div>/i', '', $html);
      $html = preg_replace('/<section\b([^>]*class="[^"]*\bdbx-content-hero\b[^"]*"[^>]*)>\s*<\/section>/i', '', $html);
      $html = preg_replace('/<div\b([^>]*class="[^"]*\bhero-content\b[^"]*"[^>]*)>\s*<\/div>/i', '', $html);
      return $html;
   }
}
