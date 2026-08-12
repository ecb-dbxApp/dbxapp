<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingTemplateServiceTrait {

   private function contentTemplateDir(): string {
      $dir = dbx()->get_system_var('dbx_dir', '') . '/modules/dbxContent/tpl/htm/';
      if (!is_dir($dir)) {
         $dir = dirname(__DIR__, 2) . '/dbxContent/tpl/htm/';
      }
      return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
   }

   private function listContentTemplates(): array {
      $files = glob($this->contentTemplateDir() . 'c-*.htm');
      $out = array();
      if (is_array($files)) {
         sort($files);
         foreach ($files as $file) {
            $out[] = basename($file, '.htm');
         }
      }
      return $out ?: array('c-content');
   }

   private function sanitizeContentTemplate(string $template, bool $heroEnabled): string {
      $template = trim($template);
      $allowed = $this->listContentTemplates();
      if ($template === '' || $template === 'parent') {
         return $heroEnabled ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      if (!in_array($template, $allowed, true)) {
         return $heroEnabled ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      if (!$heroEnabled && strpos($template, 'hero') !== false) {
         return 'parent';
      }
      return $template;
   }

   private function analyzeTemplateSlots(string $template): array {
      if ($template === '' || $template === 'parent') {
         return array(
            'hero_text' => false,
            'header' => false,
            'footer' => false,
            'cols' => 1,
            'gallery' => false,
         );
      }
      $path = $this->contentTemplateDir() . $template . '.htm';
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

   private function buildContentTemplateOptions(string $selected, bool $heroEnabled): string {
      $selected = $this->sanitizeContentTemplate($selected, $heroEnabled);
      $html = '';
      if (!$heroEnabled) {
         $html .= '<option value="parent"' . ($selected === 'parent' ? ' selected' : '') . '>parent — vom Ordner</option>';
      }
      foreach ($this->listContentTemplates() as $name) {
         if (!$heroEnabled && strpos($name, 'hero') !== false) {
            continue;
         }
         if ($heroEnabled && strpos($name, 'hero') === false && $name !== 'c-content') {
            continue;
         }
         $sel = ($name === $selected) ? ' selected' : '';
         $html .= '<option value="' . $this->esc($name) . '"' . $sel . '>' . $this->esc($name) . '</option>';
      }
      return $html;
   }

   private function contentTemplateForCreate(bool $heroEnabled, string $selected = ''): string {
      return $this->sanitizeContentTemplate($selected, $heroEnabled);
   }

   private function contentMarkerHr(string $name): string {
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

   private function contentMarkersMeta(array $slots): array {
      $markers = array();
      if (!empty($slots['hero_text'])) {
         $markers['hero'] = $this->contentMarkerHr('hero');
      }
      if (!empty($slots['header'])) {
         $markers['header'] = $this->contentMarkerHr('header');
      }
      if (!empty($slots['footer'])) {
         $markers['footer'] = $this->contentMarkerHr('footer');
      }
      return $markers;
   }

   private function contentExampleHtml(array $slots, string $heroTextHint = ''): string {
      $parts = array();
      if (!empty($slots['hero_text'])) {
         $lead = $heroTextHint !== '' ? $heroTextHint : 'Kurzer Hero-Text';
         $parts[] = '<p class="lead">' . $lead . '</p>';
         $parts[] = $this->contentMarkerHr('hero');
      }
      if (!empty($slots['header'])) {
         $parts[] = $this->contentMarkerHr('header');
      }
      $parts[] = '<h2>Ueberschrift</h2><p>Haupttext...</p>';
      if (!empty($slots['footer'])) {
         $parts[] = $this->contentMarkerHr('footer');
         $parts[] = '<p><small>Optionale Fusszeile</small></p>';
      }
      return implode('', $parts);
   }

   private function contentMarkersGuide(string $template, bool $withHeroImage, string $heroTextBrief = ''): string {
      $slots = $this->analyzeTemplateSlots($template);
      $markers = $this->contentMarkersMeta($slots);
      $example = $this->contentExampleHtml($slots, $heroTextBrief);

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
         if ($heroTextBrief !== '') {
            $lines[] = '   Hero-Text laut Auftrag: *' . $heroTextBrief . '*';
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
      if ($withHeroImage) {
         $lines[] = '';
         $lines[] = 'Hero-**Bild** kommt ueber dbxKi-Medienschritte — nicht ins HTML. Neue Hero-Bilder liegen verbindlich in `img/hero`.';
      }
      return implode("\n", $lines);
   }

   private function contentMarkersGuideShort(string $template): string {
      $slots = $this->analyzeTemplateSlots($template);
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
