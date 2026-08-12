<?php
/**
 * Vertrag: c-*-Content-Layouts sind sprachunabhängig.
 */

$moduleDir = dirname(__DIR__);
$catalogFile = $moduleDir . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxContentCmsOptionCatalog.class.php';
$catalogClass = is_file($catalogFile) ? (string)file_get_contents($catalogFile) : '';
$templateDir = $moduleDir . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm';

foreach (array(
    'private ?array $contentTemplateNames = null',
    'private function content_template_names(): array',
    "dbx/modules/dbxContent/tpl/htm/",
    'is_array($this->contentTemplateNames)',
    'foreach ($this->content_template_names() as $name)',
) as $part) {
    if (strpos($catalogClass, $part) === false) {
        fwrite(STDERR, "Sprachunabhängiger Content-Template-Vertrag fehlt: {$part}\n");
        exit(1);
    }
}

$methodStart = strpos($catalogClass, 'private function content_template_names(): array');
$methodEnd = strpos($catalogClass, 'private function options_html(', $methodStart);
$methodSource = substr($catalogClass, $methodStart, $methodEnd - $methodStart);
foreach (array('dbx_lng_resolve_file', 'dbx_lng_name', 'dbx_lng') as $forbidden) {
    if (strpos($methodSource, $forbidden) !== false) {
        fwrite(STDERR, "c-*-Layouts dürfen nicht sprachabhängig aufgelöst werden: {$forbidden}\n");
        exit(1);
    }
}

foreach (array(
    'cms-field-content-template-select',
    'cms-field-folder-content-template-select',
) as $name) {
    $file = $templateDir . DIRECTORY_SEPARATOR . $name . '.htm';
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
        if (is_file($templateDir . DIRECTORY_SEPARATOR . $name . $suffix . '.htm')) {
            fwrite(STDERR, "Sprachabhängiges Strukturduplikat ist noch vorhanden: {$name}{$suffix}.htm\n");
            exit(1);
        }
    }
}

$contentTemplateDir = dirname($moduleDir) . DIRECTORY_SEPARATOR
    . 'dbxContent' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm';
$files = glob($contentTemplateDir . DIRECTORY_SEPARATOR . 'c-*.htm');
if (!is_array($files) || count($files) < 2) {
    fwrite(STDERR, "Keine gemeinsame c-*-Templatebasis gefunden.\n");
    exit(1);
}

echo "OK: c-*-Content-Templates sind für de/en/es gemeinsam und auswählbar\n";
