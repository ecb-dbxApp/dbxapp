<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingLanguageSelectionServiceTrait {

   private function build_lng_options(string $selected): string {
      $lngs = $this->available_lngs();
      $html = '';
      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '') {
            continue;
         }
         $sel = $lng === $selected ? ' selected' : '';
         $html .= '<option value="' . $this->esc($lng) . '"' . $sel . '>' . strtoupper($this->esc($lng)) . '</option>';
      }
      return $html;
   }

   private function build_target_lng_checkboxes(string $source_lng, array $selected): string {
      $selected = $this->normalize_target_lngs($selected, true);
      $html = '';
      foreach ($this->available_lngs() as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '') {
            continue;
         }
         $checked = in_array($lng, $selected, true) ? ' checked' : '';
         $is_source = $lng === $source_lng;
         $id = 'dbxKiTargetLng_' . preg_replace('/[^a-z0-9_]/', '_', $lng);
         $html .= '<label class="dbx-ki-lng-choice' . ($is_source ? ' is-source' : '') . '" data-ki-target-lng="' . $this->esc($lng) . '">'
            . '<input type="checkbox" name="target_lngs[]" id="' . $this->esc($id) . '" value="' . $this->esc($lng) . '"' . $checked . '>'
            . '<span><strong>' . strtoupper($this->esc($lng)) . '</strong><small data-ki-target-mode>'
            . ($is_source ? 'Rechtschreibung/Grammatik' : 'Uebersetzung')
            . '</small></span>'
            . '</label>';
      }
      return $html;
   }


   private function selected_target_lngs_from_request(string $source_lng, bool $default_all_others): array {
      $raw = dbx()->get_request_var('target_lngs', array(), '*');
      if (!is_array($raw)) {
         $raw = $raw !== '' ? array($raw) : array();
      }
      $fallback = strtolower(trim((string) dbx()->get_request_var('target_lng', '', '*')));
      if ($fallback !== '') {
         $raw[] = $fallback;
      }
      $selected = $this->normalize_target_lngs($raw, true);
      if (!$selected && $default_all_others) {
         foreach ($this->available_lngs() as $lng) {
            if ($lng !== $source_lng) {
               $selected[] = $lng;
            }
         }
      }
      return array_values(array_unique($selected));
   }

   private function normalize_target_lngs(array $lngs, bool $allow_source): array {
      $allowed = array_fill_keys($this->available_lngs(), true);
      $out = array();
      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '' || !isset($allowed[$lng])) {
            continue;
         }
         if (!$allow_source && $lng === strtolower(trim((string) dbxContentLng::current()))) {
            continue;
         }
         $out[$lng] = $lng;
      }
      return array_values($out);
   }

   private function available_lngs(): array {
      $lngs = array();
      if (class_exists(dbxContentLngSync::class)) {
         $lngs = dbxContentLngSync::accessible_lngs();
      }
      if (!is_array($lngs) || !$lngs) {
         $lngs = array(dbxContentLng::current());
      }
      $out = array();
      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng !== '') {
            $out[$lng] = $lng;
         }
      }
      return array_values($out);
   }

   private function target_instruction_labels(string $source_lng, array $targets): array {
      $out = array();
      foreach ($targets as $target_lng) {
         $out[] = array(
            'lng' => $target_lng,
            'mode' => $target_lng === $source_lng ? 'proofread' : 'translate',
            'label' => $target_lng === $source_lng
               ? strtoupper($target_lng) . ': Rechtschreib- und Grammatikpruefung der Quellsprache'
               : strtoupper($source_lng) . ' -> ' . strtoupper($target_lng) . ': Uebersetzung',
         );
      }
      return $out;
   }

   private function target_instructions_for_prompt(string $source_lng, array $targets): string {
      $lines = array();
      foreach ($targets as $target_lng) {
         if ($target_lng === $source_lng) {
            $lines[] = '- ' . strtoupper($target_lng) . ': Kein Sprachwechsel. Korrigiere Rechtschreibung, Grammatik, Zeichensetzung und offensichtliche Tippfehler. Inhalt, Sinn, HTML-Struktur, Medien und Modul-Aufrufe beibehalten. Nutze den `page.update`-Step `proofread_' . $target_lng . '`.';
         } else {
            $lines[] = '- ' . strtoupper($source_lng) . ' -> ' . strtoupper($target_lng) . ': Vollstaendige Uebersetzung aller Felder. Nutze den `translation.apply`-Step `translation_' . $target_lng . '`.';
         }
      }
      return implode("\n", $lines);
   }
}
