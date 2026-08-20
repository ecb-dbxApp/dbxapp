<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/dbxContentMissingNavigation.class.php';

$navigation = new \dbx\dbxContent\dbxContentMissingNavigation();
$routes = array(
    'de' => 'seite-nicht-gefunden',
    'en' => 'page-not-found',
    'es' => 'pagina-no-encontrada',
);
foreach ($routes as $language => $route) {
    if ($navigation->route_for_language($language) !== $route
        || $navigation->language_for_route($route) !== $language) {
        throw new RuntimeException('Missing-Navigation route is invalid: ' . $language);
    }
    $template = dirname(__DIR__) . '/tpl/htm/missing-navigation'
        . ($language === 'de' ? '' : '_' . $language) . '.htm';
    $source = (string)file_get_contents($template);
    foreach (array('{requested_permalink}', '{page_rows}', '{page_count}', '{sitemap_url}') as $slot) {
        if (!str_contains($source, $slot)) {
            throw new RuntimeException(basename($template) . ' is missing slot ' . $slot);
        }
    }
}

$pipeline = (string)file_get_contents(dirname(__DIR__, 3) . '/include/dbxRequestPipeline.class.php');
$redirect = (string)file_get_contents(dirname(__DIR__, 3) . '/include/dbxWebAppRedirect.trait.php');
if (!str_contains($pipeline, 'apply_missing_permalink_redirect()')
    || !str_contains($redirect, "header('Location: ' . \$target, true, 302);")) {
    throw new RuntimeException('Unknown public permalinks must use the central 302 redirect.');
}

echo "OK unbekannte Permalinks führen per 302 zu einer sprachabhängigen Sitemap-Seite.\n";
