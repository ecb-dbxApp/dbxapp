<?php

declare(strict_types=1);

$source = (string)file_get_contents(
    dirname(__DIR__) . '/include/dbxDashboardContentCacheService.trait.php'
);

$start = strpos($source, 'private function content_cache_bar_actions_html()');
$end = strpos($source, 'private function content_cache_panel()', $start === false ? 0 : $start);
$method = ($start !== false && $end !== false)
    ? substr($source, $start, $end - $start)
    : '';

if ($method === ''
    || !str_contains($method, 'set_form_help_enabled(false)')
    || !str_contains($method, "add_rep('bar_extra', \$this->help_action('cache'))")) {
    fwrite(STDERR, "Die Content-Cache-Aktionen duerfen keine verschachtelte Formular-Bar erzeugen.\n");
    exit(1);
}

echo "OK Content-Cache-Aktionen bleiben in der Dashboard-Bar.\n";
