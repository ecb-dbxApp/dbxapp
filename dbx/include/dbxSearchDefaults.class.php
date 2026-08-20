<?php

declare(strict_types=1);

/** Erstellt die einheitliche Konfiguration fuer das gemeinsame Suchfeld. */
final class dbxSearchDefaults {

   /** Verbindet die Standardwerte mit den Angaben des jeweiligen Aufrufers. */
   public function build(array $overrides = array()): array {
      $defaults = array(
         'name'        => '',
         'value'       => '',
         'placeholder' => '🔍',
         'title'       => 'Suchen',
         'tooltip'     => '',
         'errormsg'    => '',
         'class'       => '',
         'input_class' => 'form-control-sm dbx-grid-search',
         'data_role'   => 'search',
         'wrap_class'  => '',
         'wrap_style'  => '',
         'label'       => '',
         'style'       => '',
         'extra_attrs' => '',
         'i'           => 0,
      );

      if (array_key_exists('placeholder', $overrides)
          && trim((string)$overrides['placeholder']) === '') {
         unset($overrides['placeholder']);
      }

      return array_merge($defaults, $overrides);
   }
}
