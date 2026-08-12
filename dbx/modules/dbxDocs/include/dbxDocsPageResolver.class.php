<?php
namespace dbx\dbxDocs;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap.php';

use dbx\dbxContent\dbxContentLng;

/**
 * Ordnet Dokumentationsrouten ihren dbx_page-Templates zu.
 *
 * Der Resolver arbeitet vor dem Design-Rendering. Dadurch werden linkes und
 * horizontales dbxMenu serverseitig aus dem passenden Seitentemplate geladen;
 * JavaScript muss keine Navigationsstruktur aus dem DOM rekonstruieren.
 */
class dbxDocsPageResolver
{
    private const AREA_PAGES = array(
        'einstieg' => 'docs-start',
        'anwender' => 'docs-start',
        'users' => 'docs-start',
        'usuarios' => 'docs-start',
        'getting started' => 'docs-start',
        'primeros pasos' => 'docs-start',
        'anwenden' => 'docs-apply',
        'tutorials' => 'docs-apply',
        'use' => 'docs-apply',
        'usar' => 'docs-apply',
        'tutoriales' => 'docs-apply',
        'praxis' => 'docs-apply',
        'practice' => 'docs-apply',
        'práctica' => 'docs-apply',
        'entwickeln' => 'docs-develop',
        'entwicklung' => 'docs-develop',
        'develop' => 'docs-develop',
        'desarrollar' => 'docs-develop',
        'entwickler' => 'docs-develop',
        'developers' => 'docs-develop',
        'desarrolladores' => 'docs-develop',
        'betrieb' => 'docs-operate',
        'betrieb & sicherheit' => 'docs-operate',
        'operate' => 'docs-operate',
        'operar' => 'docs-operate',
        'administratoren' => 'docs-operate',
        'administrators' => 'docs-operate',
        'administradores' => 'docs-operate',
    );

    public function forContent(int $cid, string $language = 'de', string $source = ''): string
    {
        if ($cid <= 0) {
            return 'default';
        }
        if (in_array($source, array('dbxHome-config', 'permalink-root', 'permalink-home'), true)) {
            return 'default';
        }

        $language = strtolower(trim($language));
        if (!in_array($language, array('de', 'en', 'es'), true)) {
            $language = 'de';
        }
        $db = dbx()->get_system_obj('dbxDB');
        $page = $db->select1(dbxContentLng::ddContent($language), $cid, 'id,folder', 0);
        $folderId = (int)($page['folder'] ?? 0);
        $visited = array();

        while ($folderId > 0 && !isset($visited[$folderId])) {
            $visited[$folderId] = true;
            $folder = $db->select1(
                dbxContentLng::ddFolder($language),
                $folderId,
                'id,name,parent_id',
                0
            );
            if (!is_array($folder)) {
                break;
            }
            $name = mb_strtolower(trim((string)($folder['name'] ?? '')));
            if (isset(self::AREA_PAGES[$name])) {
                return self::AREA_PAGES[$name];
            }
            $folderId = (int)($folder['parent_id'] ?? 0);
        }

        return 'default';
    }

    public function forModule(string $module, string $action = ''): string
    {
        $module = strtolower(trim($module));
        $action = strtolower(trim($action));
        if ($module === 'dbxdocs' && $action === 'reference') {
            return 'docs-reference';
        }
        return 'default';
    }
}
