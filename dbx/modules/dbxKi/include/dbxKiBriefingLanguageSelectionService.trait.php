<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingLanguageSelectionServiceTrait {

   private function buildLngOptions(string $selected): string {
      $lngs = $this->availableLngs();
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

   private function buildTargetLngCheckboxes(string $sourceLng, array $selected): string {
      $selected = $this->normalizeTargetLngs($selected, true);
      $html = '';
      foreach ($this->availableLngs() as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '') {
            continue;
         }
         $checked = in_array($lng, $selected, true) ? ' checked' : '';
         $isSource = $lng === $sourceLng;
         $id = 'dbxKiTargetLng_' . preg_replace('/[^a-z0-9_]/', '_', $lng);
         $html .= '<label class="dbx-ki-lng-choice' . ($isSource ? ' is-source' : '') . '" data-ki-target-lng="' . $this->esc($lng) . '">'
            . '<input type="checkbox" name="target_lngs[]" id="' . $this->esc($id) . '" value="' . $this->esc($lng) . '"' . $checked . '>'
            . '<span><strong>' . strtoupper($this->esc($lng)) . '</strong><small data-ki-target-mode>'
            . ($isSource ? 'Rechtschreibung/Grammatik' : 'Uebersetzung')
            . '</small></span>'
            . '</label>';
      }
      return $html;
   }


   private function selectedTargetLngsFromRequest(string $sourceLng, bool $defaultAllOthers): array {
      $raw = dbx()->get_request_var('target_lngs', array(), '*');
      if (!is_array($raw)) {
         $raw = $raw !== '' ? array($raw) : array();
      }
      $fallback = strtolower(trim((string) dbx()->get_request_var('target_lng', '', '*')));
      if ($fallback !== '') {
         $raw[] = $fallback;
      }
      $selected = $this->normalizeTargetLngs($raw, true);
      if (!$selected && $defaultAllOthers) {
         foreach ($this->availableLngs() as $lng) {
            if ($lng !== $sourceLng) {
               $selected[] = $lng;
            }
         }
      }
      return array_values(array_unique($selected));
   }

   private function normalizeTargetLngs(array $lngs, bool $allowSource): array {
      $allowed = array_fill_keys($this->availableLngs(), true);
      $out = array();
      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '' || !isset($allowed[$lng])) {
            continue;
         }
         if (!$allowSource && $lng === strtolower(trim((string) dbxContentLng::current()))) {
            continue;
         }
         $out[$lng] = $lng;
      }
      return array_values($out);
   }

   private function availableLngs(): array {
      $lngs = array();
      if (class_exists(dbxContentLngSync::class)) {
         $lngs = dbxContentLngSync::accessibleLngs();
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

   private function targetInstructionLabels(string $sourceLng, array $targets): array {
      $out = array();
      foreach ($targets as $targetLng) {
         $out[] = array(
            'lng' => $targetLng,
            'mode' => $targetLng === $sourceLng ? 'proofread' : 'translate',
            'label' => $targetLng === $sourceLng
               ? strtoupper($targetLng) . ': Rechtschreib- und Grammatikpruefung der Quellsprache'
               : strtoupper($sourceLng) . ' -> ' . strtoupper($targetLng) . ': Uebersetzung',
         );
      }
      return $out;
   }

   private function targetInstructionsForPrompt(string $sourceLng, array $targets): string {
      $lines = array();
      foreach ($targets as $targetLng) {
         if ($targetLng === $sourceLng) {
            $lines[] = '- ' . strtoupper($targetLng) . ': Kein Sprachwechsel. Korrigiere Rechtschreibung, Grammatik, Zeichensetzung und offensichtliche Tippfehler. Inhalt, Sinn, HTML-Struktur, Medien und Modul-Aufrufe beibehalten. Nutze den `page.update`-Step `proofread_' . $targetLng . '`.';
         } else {
            $lines[] = '- ' . strtoupper($sourceLng) . ' -> ' . strtoupper($targetLng) . ': Vollstaendige Uebersetzung aller Felder. Nutze den `translation.apply`-Step `translation_' . $targetLng . '`.';
         }
      }
      return implode("\n", $lines);
   }
}
