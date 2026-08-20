<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingBootstrapComponentServiceTrait {

   private function allowed_bootstrap_components(): array {
      return array(
         'alert' => array(
            'label' => 'Hinweis',
            'classes' => 'alert alert-info / alert-warning / alert-success',
            'use' => 'Kurze Hinweis-, Info- oder Erfolgsbox.',
         ),
         'card' => array(
            'label' => 'Cards',
            'classes' => 'card, card-body, row, row-cols-*, g-*',
            'use' => 'Teaser, Leistungsboxen oder Paket-/Feature-Kacheln.',
         ),
         'list_group' => array(
            'label' => 'Listenbox',
            'classes' => 'list-group, list-group-item',
            'use' => 'Kompakte Nutzen-, Schritt- oder Funktionslisten.',
         ),
         'badges' => array(
            'label' => 'Badges',
            'classes' => 'badge text-bg-*',
            'use' => 'Status, Kategorien, kleine Hervorhebungen.',
         ),
         'buttons' => array(
            'label' => 'Buttons',
            'classes' => 'btn btn-primary / btn-outline-primary',
            'use' => 'CTA-Links ohne eigenes JavaScript.',
         ),
         'table' => array(
            'label' => 'Tabelle',
            'classes' => 'table table-striped table-hover',
            'use' => 'Vergleichs- oder Preis-/Datenuebersichten.',
         ),
         'accordion' => array(
            'label' => 'Akkordeon',
            'classes' => 'accordion, accordion-item, accordion-button',
            'use' => 'FAQ oder aufklappbare Detailbereiche.',
         ),
         'tabs' => array(
            'label' => 'Tabs',
            'classes' => 'nav nav-tabs, tab-content, tab-pane',
            'use' => 'Alternative Sichten auf denselben Inhalt.',
         ),
      );
   }

   private function selected_bootstrap_components_from_request(): array {
      $raw = dbx()->get_request_var('bootstrap_components', array(), '*');
      if (!is_array($raw)) {
         $raw = $raw === '' ? array() : explode(',', (string) $raw);
      }
      $allowed = $this->allowed_bootstrap_components();
      $out = array();
      foreach ($raw as $key) {
         $key = strtolower(trim((string) $key));
         if (isset($allowed[$key]) && !in_array($key, $out, true)) {
            $out[] = $key;
         }
      }
      return $out;
   }

   /**
    * Seite-aendern-Formular: Bootstrap-Komponenten sind hier echte
    * FD-Felder `comp_<key>` (siehe fd/ki-briefing-page-update.fd.php),
    * damit dbxForm ihre Auswahl per UI-State dauerhaft merken kann.
    */
   private function selected_bootstrap_components_from_update_fields(): array {
      $out = array();
      foreach ($this->allowed_bootstrap_components() as $key => $meta) {
         if (dbx()->get_request_var('comp_' . $key, '0', '*') === '1') {
            $out[] = $key;
         }
      }
      return $out;
   }

   private function build_bootstrap_component_choices(array $selected): string {
      $html = '';
      foreach ($this->allowed_bootstrap_components() as $key => $meta) {
         $checked = in_array($key, $selected, true) ? ' checked' : '';
         $html .= '<label><input type="checkbox" name="bootstrap_components[]" value="' . $this->esc($key) . '"' . $checked . '>'
            . '<span><strong>' . $this->esc($meta['label'] ?? $key) . '</strong><small>' . $this->esc($meta['use'] ?? '') . '</small></span></label>';
      }
      return $html;
   }

   private function bootstrap_components_meta(array $selected): array {
      $allowed = $this->allowed_bootstrap_components();
      $out = array();
      foreach ($selected as $key) {
         if (isset($allowed[$key])) {
            $out[$key] = $allowed[$key];
         }
      }
      return $out;
   }

   private function bootstrap_components_guide(array $selected): string {
      $meta = $this->bootstrap_components_meta($selected);
      if (!$meta) {
         return "Keine Bootstrap-Komponenten im Content verwenden. Erlaubt sind nur normales Jodit-HTML wie h2, h3, p, ul/ol, Links und einfache Textstruktur.";
      }
      $lines = array(
         'Die KI darf im Content nur diese ausgewaehlten Bootstrap-5-Komponenten verwenden:',
         '',
      );
      foreach ($meta as $key => $row) {
         $lines[] = '- `' . $key . '` (' . ($row['label'] ?? $key) . '): ' . ($row['use'] ?? '') . ' Klassen: `' . ($row['classes'] ?? '') . '`.';
      }
      $lines[] = '';
      $lines[] = 'Nicht ausgewaehlte Bootstrap-Komponenten sind verboten. Kein eigenes CSS, kein eigenes JavaScript, keine Inline-Styles. HTML muss in Jodit bearbeitbar bleiben.';
      return implode("\n", $lines);
   }
}
