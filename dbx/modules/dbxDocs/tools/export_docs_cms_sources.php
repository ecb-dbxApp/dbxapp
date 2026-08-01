<?php
declare(strict_types=1);

/**
 * Exportiert ausschließlich redaktionelle CMS-Seiten als reproduzierbare
 * Migrationsquellen. Datenzugriffe erfolgen über dbxDB, niemals über SQL.
 */

$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';

$map = array(
    'dbxapp_user_docs.html' => 'dokumentation-anwender',
    'dbxapp_user_start.html' => 'dokumentation-erste-schritte',
    'dbxapp_user_admin.html' => 'dokumentation-administration',
    'dbxapp_user_content.html' => 'dokumentation-cms-medien',
    'dbxapp_user_shop.html' => 'dokumentation-shop-bedienung',
    'dbxapp_user_workflows.html' => 'dokumentation-workflow-bedienung',
    'dbxapp_user_design.html' => 'dokumentation-design-tutorial',
    'dbxapp_user_ki_content.html' => 'dokumentation-ki-content',
    'dbxapp_user_ki_design.html' => 'dokumentation-ki-design',
    'dbxapp_user_ki_modules.html' => 'dokumentation-ki-module',
    'dbxapp_developer_docs.html' => 'dokumentation-entwickler',
    'dbxapp_dev_orientation.html' => 'dokumentation-architektur-laufzeit',
    'dbxapp_dev_modules.html' => 'dokumentation-modulentwicklung',
    'dbxapp_dev_pipelines.html' => 'dokumentation-kernpipelines',
    'dbxapp_dev_content_design.html' => 'dokumentation-content-fachmodule',
    'dbxapp_dev_operations.html' => 'dokumentation-betrieb-entwickler',
    'dbxapp_user_system_update.html' => 'dokumentation-system-update',
    'dbxapp_module_quickstart.html' => 'dokumentation-modul-schnellstart',
    'md_files_2doku_2doku-dbxapp-libs-attribute.html' => 'dokumentation-library-attribute',
);

$output = $base . '/dbx/modules/dbxDocs/content/generated';
if (!is_dir($output) && !mkdir($output, 0775, true) && !is_dir($output)) {
    throw new RuntimeException('Ausgabeverzeichnis konnte nicht angelegt werden.');
}

$db = dbx()->get_system_obj('dbxDB');
$written = array();
$missing = array();
foreach ($map as $file => $permalink) {
    $row = $db->select1('dbx|content_de', array('permalink' => $permalink), 'id,content', 0);
    $content = trim((string)($row['content'] ?? ''));
    if ((int)($row['id'] ?? 0) <= 0 || $content === '') {
        $missing[] = $permalink;
        continue;
    }
    $content = preg_replace(
        '#\s*<!--\s*dbxdocs-meta:start\s*-->.*?<!--\s*dbxdocs-meta:end\s*-->\s*#is',
        "\n",
        $content
    );
    $document = '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"></head><body>'
        . '<div class="contents">' . trim((string)$content) . '</div>'
        . '</body></html>';
    $target = $output . '/' . basename($file);
    if (file_put_contents($target, $document) === false) {
        throw new RuntimeException('Export konnte nicht geschrieben werden: ' . basename($file));
    }
    $written[] = basename($file);
}

echo json_encode(array(
    'ok' => $missing === array(),
    'written' => $written,
    'missing' => $missing,
    'output' => $output,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($missing === array() ? 0 : 1);
