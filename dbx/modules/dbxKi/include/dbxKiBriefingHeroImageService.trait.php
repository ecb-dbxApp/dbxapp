<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingHeroImageServiceTrait {

   private function heroImageSpecText(): string {
      return 'JPG, Standard ' . self::HERO_DEFAULT_IMAGE_WIDTH . '×' . self::HERO_DEFAULT_IMAGE_HEIGHT . ' px'
         . ' (nur bei ausdruecklicher Vorgabe abweichend, maximal ' . self::HERO_MAX_WIDTH . '×' . self::HERO_MAX_HEIGHT . ' px),'
         . ' CMS-Hero-Hoehe Standard ' . self::HERO_DEFAULT_HEIGHT;
   }

   private function heroImageBriefingMeta(): array {
      return array(
         'format' => 'jpg',
         'max_width' => self::HERO_MAX_WIDTH,
         'max_height' => self::HERO_MAX_HEIGHT,
         'default_width' => self::HERO_DEFAULT_IMAGE_WIDTH,
         'default_image_height' => self::HERO_DEFAULT_IMAGE_HEIGHT,
         'default_dimensions' => self::HERO_DEFAULT_IMAGE_WIDTH . 'x' . self::HERO_DEFAULT_IMAGE_HEIGHT,
         'recommended' => self::HERO_OPTIMAL_WIDTH . 'x' . self::HERO_OPTIMAL_HEIGHT,
         'default_height' => self::HERO_DEFAULT_HEIGHT,
      );
   }

   private function heroAssetDefinitions(bool $enabled): array {
      if (!$enabled) return array();
      return array(
         'hero.jpg' => array(
            'required' => true,
            'extensions' => array('jpg', 'jpeg'),
            'max_bytes' => 10485760,
            'width' => self::HERO_DEFAULT_IMAGE_WIDTH,
            'height' => self::HERO_DEFAULT_IMAGE_HEIGHT,
         ),
      );
   }
}
