<?php
/**
 * Vertrag: c-*-Content-Layouts sind sprachunabhängig.
 */

$module_dir = dirname(__DIR__);
$catalog_file = $module_dir . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxContentCmsOptionCatalog.class.php';
$catalog_class = is_file($catalog_file) ? (string)file_get_contents($catalog_file) : '';
$template_dir = $module_dir . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm';

foreach (array(
    'private ?array $content_template_names = null',
    'private function content_template_names(): array',
    "dbx/modules/dbxContent/tpl/htm/",
    'is_array($this->content_template_names)',
    'foreach ($this->content_template_names() as $name)',
) as $part) {
    if (strpos($catalog_class, $part) === false) {
        fwrite(STDERR, "Sprachunabhängiger Content-Template-Vertrag fehlt: {$part}\n");
        exit(1);
    }
}

$method_start = strpos($catalog_class, 'private function content_template_names(): array');
$method_end = strpos($catalog_class, 'private function options_html(', $method_start);
$method_source = substr($catalog_class, $method_start, $method_end - $method_start);
foreach (array('dbx_lng_resolve_file', 'dbx_lng_name', 'dbx_lng') as $forbidden) {
    if (strpos($method_source, $forbidden) !== false) {
        fwrite(STDERR, "c-*-Layouts dürfen nicht sprachabhängig aufgelöst werden: {$forbidden}\n");
        exit(1);
    }
}

foreach (array(
    'cms-field-content-template-select',
    'cms-field-folder-content-template-select',
) as $name) {
    $file = $template_dir . DIRECTORY_SEPARATOR . $name . '.htm';
    $source = is_file($file) ? (string)file_get_contents($file) : '';

    if (substr_count($source, '{{name}_options}') !== 1) {
        fwrite(STDERR, "Options-Platzhalter ist in {$name} unvollständig oder mehrfach vorhanden.\n");
        exit(1);
    }
    if (strpos($source, '{{name}_options</select>') !== false) {
        fwrite(STDERR, "Defekter Options-Platzhalter in {$name}.\n");
        exit(1);
    }
    foreach (array('_en', '_es') as $suffix) {
        if (is_file($template_dir . DIRECTORY_SEPARATOR . $name . $suffix . '.htm')) {
            fwrite(STDERR, "Sprachabhängiges Strukturduplikat ist noch vorhanden: {$name}{$suffix}.htm\n");
            exit(1);
        }
    }
}

$content_template_dir = dirname($module_dir) . DIRECTORY_SEPARATOR
    . 'dbxContent' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm';
$files = glob($content_template_dir . DIRECTORY_SEPARATOR . 'c-*.htm');
if (!is_array($files) || count($files) < 2) {
    fwrite(STDERR, "Keine gemeinsame c-*-Templatebasis gefunden.\n");
    exit(1);
}

echo "OK: c-*-Content-Templates sind für de/en/es gemeinsam und auswählbar\n";
