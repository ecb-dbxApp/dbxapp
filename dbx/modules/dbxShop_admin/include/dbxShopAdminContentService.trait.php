<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Rechtstexte als CMS-Inhalt statt duplizierter Shop-Ausgabe.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminContentServiceTrait {


   private function legal_page(): string {
      return $this->shop_legal_cms_page(
         'legal',
         'Rechtstexte',
         'Diese Seite kommt aus dem CMS über den stabilen Permalink /shop-rechtstexte. Inhalte wie Anbieterkennzeichnung, AGB, Zahlung, Versand und Datenschutz-Hinweise werden dort gepflegt.',
         'legal'
      );
   }
}
