<?php

declare(strict_types=1);

/**
 * Prüft den ausgelagerten, einheitlichen Optionskatalog aller CMS-Formulare.
 */

require_once dirname(__DIR__) . '/include/dbxContentCmsOptionCatalog.class.php';
require_once dirname(__DIR__, 3) . '/include/tests/dbxModuleSourceBundle.php';

use dbx\dbxContent_admin\dbxContentCmsOptionCatalog;

$texts = new class {
    public function get_fd_message(string $key): string { return 'msg:' . $key; }
};
$catalog = new dbxContentCmsOptionCatalog($texts);

$hero = $catalog->hero_template_values();
$variants = $catalog->hero_variant_values();
$sizes = $catalog->gallery_image_size_values();
$clicks = $catalog->gallery_click_values();

$failures = array();
if (($hero['parent'] ?? '') !== 'msg:option_parent' || ($hero['none'] ?? '') !== 'msg:option_no_hero') {
    $failures[] = 'Sprachtexte der Hero-Auswahl fehlen.';
}
if (!isset($variants['original'], $variants['dark'], $variants['monochrome'])) {
    $failures[] = 'Hero-Varianten sind unvollständig.';
}
if (count($sizes) !== 13 || !isset($sizes['1920x1080'], $sizes['1080x1350'])) {
    $failures[] = 'Bildgrößen sind unvollständig.';
}
if (!isset($clicks['lightbox'], $clicks['photoswipe'], $clicks['none'])) {
    $failures[] = 'Galerieaktionen sind unvollständig.';
}

$controller = dbx_test_module_source_bundle(dirname(__DIR__) . '/include/dbxContent_cms.class.php');
if (!str_contains($controller, '$this->cms_options()->hero_template_values()')
    || str_contains($controller, '$this->content_template_values()')
    || str_contains($controller, 'private function hero_template_values(')
    || str_contains($controller, 'private function gallery_click_values(')
) {
    $failures[] = 'Der CMS-Controller enthält weiterhin eigene Optionskataloge.';
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK einheitlicher CMS-Optionskatalog für Hero, Galerie, Templates und Rechte.\n";
