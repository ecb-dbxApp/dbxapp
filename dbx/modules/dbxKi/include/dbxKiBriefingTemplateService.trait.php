<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingTemplateServiceTrait {

   private function content_template_dir(): string {
      $dir = dbx()->get_system_var('dbx_dir', '') . '/modules/dbxContent/tpl/htm/';
      if (!is_dir($dir)) {
         $dir = dirname(__DIR__, 2) . '/dbxContent/tpl/htm/';
      }
      return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
   }

   private function list_content_templates(): array {
      $files = glob($this->content_template_dir() . 'c-*.htm');
      $out = array();
      if (is_array($files)) {
         sort($files);
         foreach ($files as $file) {
            $out[] = basename($file, '.htm');
         }
      }
      return $out ?: array('c-content');
   }

   private function sanitize_content_template(string $template, bool $hero_enabled): string {
      $template = trim($template);
      $allowed = $this->list_content_templates();
      if ($template === '' || $template === 'parent') {
         return $hero_enabled ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      if (!in_array($template, $allowed, true)) {
         return $hero_enabled ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      if (!$hero_enabled && strpos($template, 'hero') !== false) {
         return 'parent';
      }
      return $template;
   }

   private function analyze_template_slots(string $template): array {
      if ($template === '' || $template === 'parent') {
         return array(
            'hero_text' => false,
            'header' => false,
            'footer' => false,
            'cols' => 1,
            'gallery' => false,
         );
      }
      $path = $this->content_template_dir() . $template . '.htm';
      if (!is_file($path)) {
         return array('hero_text' => true, 'header' => true, 'footer' => true, 'cols' => 1, 'gallery' => false);
      }
      $html = (string) file_get_contents($path);
      $slots = array(
         'hero_text' => strpos($html, '{cms:hero_text}') !== false,
         'header' => strpos($html, '{cms:header}') !== false,
         'footer' => strpos($html, '{cms:footer}') !== false,
         'gallery' => strpos($html, '{cms:gallery}') !== false,
         'cols' => 1,
      );
      if (strpos($html, '{cms:col3}') !== false) {
         $slots['cols'] = 3;
      } elseif (strpos($html, '{cms:col2}') !== false) {
         $slots['cols'] = 2;
      }
      return $slots;
   }

   private function build_content_template_options(string $selected, bool $hero_enabled): string {
      $selected = $this->sanitize_content_template($selected, $hero_enabled);
      $html = '';
      if (!$hero_enabled) {
         $html .= '<option value="parent"' . ($selected === 'parent' ? ' selected' : '') . '>parent — vom Ordner</option>';
      }
      foreach ($this->list_content_templates() as $name) {
         if (!$hero_enabled && strpos($name, 'hero') !== false) {
            continue;
         }
         if ($hero_enabled && strpos($name, 'hero') === false && $name !== 'c-content') {
            continue;
         }
         $sel = ($name === $selected) ? ' selected' : '';
         $html .= '<option value="' . $this->esc($name) . '"' . $sel . '>' . $this->esc($name) . '</option>';
      }
      return $html;
   }

   private function content_template_for_create(bool $hero_enabled, string $selected = ''): string {
      return $this->sanitize_content_template($selected, $hero_enabled);
   }

   private function content_marker_hr(string $name): string {
      $labels = array(
         'hero' => 'Hero-Text',
         'header' => 'Header',
         'footer' => 'Footer',
      );
      $name = strtolower(trim($name));
      $label = $labels[$name] ?? $name;
      return '<hr class="dbx-cms-marker dbx-cms-marker-' . $name
         . '" contenteditable="false" data-dbx-marker="dbx:' . $name
         . '" data-label="' . $label . '">';
   }

   private function content_markers_meta(array $slots): array {
      $markers = array();
      if (!empty($slots['hero_text'])) {
         $markers['hero'] = $this->content_marker_hr('hero');
      }
      if (!empty($slots['header'])) {
         $markers['header'] = $this->content_marker_hr('header');
      }
      if (!empty($slots['footer'])) {
         $markers['footer'] = $this->content_marker_hr('footer');
      }
      return $markers;
   }

   private function content_example_html(array $slots, string $hero_text_hint = ''): string {
      $parts = array();
      if (!empty($slots['hero_text'])) {
         $lead = $hero_text_hint !== '' ? $hero_text_hint : 'Kurzer Hero-Text';
         $parts[] = '<p class="lead">' . $lead . '</p>';
         $parts[] = $this->content_marker_hr('hero');
      }
      if (!empty($slots['header'])) {
         $parts[] = $this->content_marker_hr('header');
      }
      $parts[] = '<h2>Ueberschrift</h2><p>Haupttext...</p>';
      if (!empty($slots['footer'])) {
         $parts[] = $this->content_marker_hr('footer');
         $parts[] = '<p><small>Optionale Fusszeile</small></p>';
      }
      return implode('', $parts);
   }

   private function content_markers_guide(string $template, bool $with_hero_image, string $hero_text_brief = ''): string {
      $slots = $this->analyze_template_slots($template);
      $markers = $this->content_markers_meta($slots);
      $example = $this->content_example_html($slots, $hero_text_brief);

      $lines = array(
         '## Content-Template und Bereichs-Marker',
         '',
         'Content-Template: `' . $template . '`',
         '',
         'Der Inhalt in `content` wird mit **`<hr>`-Markern** getrennt (Reihenfolge von oben nach unten):',
         '',
      );

      if (!empty($slots['hero_text'])) {
         $lines[] = '1. Text **vor** Hero-Marker → Slot `{cms:hero_text}` (Text im Hero-Bereich neben/unter dem Bild)';
         $lines[] = '   Standard: maximal ' . self::HERO_TEXT_MAX_LINES . ' Zeilen Hero-Text, wenn nicht anders angegeben.';
         $lines[] = '2. `<hr data-dbx-marker="dbx:hero">`';
         if ($hero_text_brief !== '') {
            $lines[] = '   Hero-Text laut Auftrag: *' . $hero_text_brief . '*';
         }
      }
      if (!empty($slots['header'])) {
         $lines[] = '- Text zwischen Hero- und Header-Marker → `{cms:header}` (eigener Block zwischen Hero und Body)';
         $lines[] = '- `<hr data-dbx-marker="dbx:header">`';
      }
      $lines[] = '- Text bis Footer-Marker → Body (`{cms:col1}`' . ((int)($slots['cols'] ?? 1) > 1 ? ' / Spalten' : '') . ')';
      if (!empty($slots['footer'])) {
         $lines[] = '- `<hr data-dbx-marker="dbx:footer">`';
         $lines[] = '- Text danach → `{cms:footer}`';
      }
      $lines[] = '';
      $lines[] = 'Fehlende Marker: der jeweilige Bereich entfaellt, der Text gehoert zum Body.';
      $lines[] = '**Spalten-Marker (`col2`, `col3a`, `col3b`) nicht setzen** — werden manuell im CMS gesetzt.';
      $lines[] = '';
      foreach ($markers as $name => $hr) {
         $lines[] = '**' . ucfirst($name) . '-Marker:**';
         $lines[] = '```html';
         $lines[] = $hr;
         $lines[] = '```';
         $lines[] = '';
      }
      $lines[] = '**Beispiel `content` fuer dieses Template:**';
      $lines[] = '```html';
      $lines[] = $example;
      $lines[] = '```';
      if ($with_hero_image) {
         $lines[] = '';
         $lines[] = 'Hero-**Bild** kommt ueber dbxKi-Medienschritte — nicht ins HTML. Neue Hero-Bilder liegen verbindlich in `img/hero`.';
      }
      return implode("\n", $lines);
   }

   private function content_markers_guide_short(string $template): string {
      $slots = $this->analyze_template_slots($template);
      $bits = array('Template `' . $template . '`');
      if (!empty($slots['hero_text'])) {
         $bits[] = 'Hero-/Header-/Footer-Marker per `<hr>`';
      } elseif (!empty($slots['header']) || !empty($slots['footer'])) {
         $bits[] = 'Header-/Footer-Marker per `<hr>`';
      }
      $bits[] = 'keine Spalten-Marker';
      $bits[] = 'Bootstrap-5-Content-Komponenten nur wenn im Auftrag ausgewaehlt, nur Bootstrap-Klassen, kein eigenes CSS/JS';
      return implode('; ', $bits) . '.';
   }
}
