<?php
namespace dbx\dbxDocs;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;

/**
 * Einmalige, wiederholbare Übernahme der redaktionellen Dokumentation in
 * dbxContent.
 *
 * Die Migration überschreibt vorhandene CMS-Inhalte standardmäßig nicht.
 * Nach der Erstübernahme ist das CMS die redaktionelle Quelle; Doxygen bleibt
 * ausschließlich für automatisch generierte Quellcode-Referenzen zuständig.
 */
class dbxDocsContentProvision
{
    private object $db;
    private string $baseDir;
    private string $referenceDir;
    private bool $force;
    private array $folderIds = array();
    private array $htmlToPermalink = array();
    private array $result = array(
        'folders_created' => 0,
        'folders_updated' => 0,
        'pages_created' => 0,
        'pages_updated' => 0,
        'pages_preserved' => 0,
        'links_updated' => 0,
        'errors' => array(),
    );

    public function __construct(object $db, string $baseDir, bool $force = false)
    {
        $this->db = $db;
        $this->baseDir = rtrim(str_replace('\\', '/', $baseDir), '/');
        $this->referenceDir = $this->baseDir . '/reference/current';
        $this->force = $force;
    }

    public function run(): array
    {
        $this->prepareHtmlMap();
        $this->prepareFolderTree();
        $this->moveTutorialFolders();
        $this->provisionAreaLandingPages();
        $this->shortenTutorialTitles();
        $this->provisionDocumentationHome();
        $this->provisionGermanDocumentation();
        $this->provisionLegacyDocumentation();
        $this->provisionServicePages();
        $this->provisionCuratedDocumentationUpdates();
        $this->refineLongFormNavigation();
        $this->synchronizeDocumentationMetadata();
        $this->repairDocumentationLinks();
        dbxContentPageCache::invalidateAll();
        return $this->result + array('ok' => $this->result['errors'] === array());
    }

    private function folderPlan(): array
    {
        return array(
            'de' => array(
                array('key' => 'root', 'name' => 'Dokumentation', 'parent' => '', 'sorter' => '0010'),
                array('key' => 'start', 'name' => 'Einstieg', 'parent' => 'root', 'sorter' => '0010'),
                array('key' => 'tutorials', 'name' => 'Anwenden', 'legacy_names' => array('Tutorials'), 'parent' => 'root', 'sorter' => '0020'),
                array('key' => 'content', 'name' => 'CMS & KI', 'legacy_names' => array('Content & KI'), 'parent' => 'tutorials', 'sorter' => '0010'),
                array('key' => 'design', 'name' => 'Design', 'parent' => 'tutorials', 'sorter' => '0020'),
                array('key' => 'shop', 'name' => 'Shop', 'parent' => 'tutorials', 'sorter' => '0030'),
                array('key' => 'workflow', 'name' => 'Workflows', 'parent' => 'tutorials', 'sorter' => '0040'),
                array('key' => 'development', 'name' => 'Entwickeln', 'legacy_names' => array('Entwicklung'), 'parent' => 'root', 'sorter' => '0030'),
                array('key' => 'architecture', 'name' => 'Architektur', 'parent' => 'development', 'sorter' => '0010'),
                array('key' => 'libraries', 'name' => 'Kernbibliotheken', 'parent' => 'development', 'sorter' => '0020'),
                array('key' => 'modules', 'name' => 'Module & JavaScript', 'parent' => 'development', 'sorter' => '0030'),
                array('key' => 'deep', 'name' => 'Analysen & Konzepte', 'parent' => 'development', 'sorter' => '0040'),
                array('key' => 'ai-instructions', 'name' => 'KI-Arbeitsanweisungen', 'parent' => 'development', 'sorter' => '0050'),
                array('key' => 'operations', 'name' => 'Betrieb', 'legacy_names' => array('Betrieb & Sicherheit'), 'parent' => 'root', 'sorter' => '0040'),
                array('key' => 'service', 'name' => 'Service', 'parent' => 'operations', 'sorter' => '0090'),
            ),
            'en' => array(
                array('key' => 'root', 'name' => 'Documentation', 'parent' => '', 'sorter' => '0010'),
                array('key' => 'start', 'name' => 'Getting started', 'parent' => 'root', 'sorter' => '0010'),
                array('key' => 'tutorials', 'name' => 'Use', 'legacy_names' => array('Tutorials'), 'parent' => 'root', 'sorter' => '0020'),
                array('key' => 'content', 'name' => 'CMS & AI', 'parent' => 'tutorials', 'sorter' => '0010'),
                array('key' => 'design', 'name' => 'Design', 'parent' => 'tutorials', 'sorter' => '0020'),
                array('key' => 'shop', 'name' => 'Shop', 'parent' => 'tutorials', 'sorter' => '0030'),
                array('key' => 'workflow', 'name' => 'Workflows', 'parent' => 'tutorials', 'sorter' => '0040'),
                array('key' => 'development', 'name' => 'Develop', 'parent' => 'root', 'sorter' => '0030'),
                array('key' => 'architecture', 'name' => 'Architecture', 'parent' => 'development', 'sorter' => '0010'),
                array('key' => 'libraries', 'name' => 'Core libraries', 'parent' => 'development', 'sorter' => '0020'),
                array('key' => 'modules', 'name' => 'Modules & JavaScript', 'parent' => 'development', 'sorter' => '0030'),
                array('key' => 'deep', 'name' => 'Analyses & concepts', 'parent' => 'development', 'sorter' => '0040'),
                array('key' => 'ai-instructions', 'name' => 'AI instructions', 'parent' => 'development', 'sorter' => '0050'),
                array('key' => 'operations', 'name' => 'Operate', 'parent' => 'root', 'sorter' => '0040'),
                array('key' => 'service', 'name' => 'Service', 'parent' => 'operations', 'sorter' => '0090'),
            ),
            'es' => array(
                array('key' => 'root', 'name' => 'Documentación', 'parent' => '', 'sorter' => '0010'),
                array('key' => 'start', 'name' => 'Primeros pasos', 'parent' => 'root', 'sorter' => '0010'),
                array('key' => 'tutorials', 'name' => 'Usar', 'legacy_names' => array('Tutoriales'), 'parent' => 'root', 'sorter' => '0020'),
                array('key' => 'content', 'name' => 'CMS e IA', 'parent' => 'tutorials', 'sorter' => '0010'),
                array('key' => 'design', 'name' => 'Diseño', 'parent' => 'tutorials', 'sorter' => '0020'),
                array('key' => 'shop', 'name' => 'Tienda', 'parent' => 'tutorials', 'sorter' => '0030'),
                array('key' => 'workflow', 'name' => 'Flujos de trabajo', 'parent' => 'tutorials', 'sorter' => '0040'),
                array('key' => 'development', 'name' => 'Desarrollar', 'parent' => 'root', 'sorter' => '0030'),
                array('key' => 'architecture', 'name' => 'Arquitectura', 'parent' => 'development', 'sorter' => '0010'),
                array('key' => 'libraries', 'name' => 'Bibliotecas base', 'parent' => 'development', 'sorter' => '0020'),
                array('key' => 'modules', 'name' => 'Módulos y JavaScript', 'parent' => 'development', 'sorter' => '0030'),
                array('key' => 'deep', 'name' => 'Análisis y conceptos', 'parent' => 'development', 'sorter' => '0040'),
                array('key' => 'ai-instructions', 'name' => 'Instrucciones de IA', 'parent' => 'development', 'sorter' => '0050'),
                array('key' => 'operations', 'name' => 'Operar', 'parent' => 'root', 'sorter' => '0040'),
                array('key' => 'service', 'name' => 'Servicio', 'parent' => 'operations', 'sorter' => '0090'),
            ),
        );
    }

    private function pagePlan(): array
    {
        return array(
            array('html' => 'dbxapp_user_docs.html', 'folder' => 'start', 'title' => 'Anwenderdokumentation', 'menu' => 'Anwenderhandbuch', 'permalink' => 'dokumentation-anwender'),
            array('html' => 'dbxapp_user_start.html', 'folder' => 'start', 'title' => 'Einstieg und Bedienung', 'menu' => 'Erste Schritte', 'permalink' => 'dokumentation-erste-schritte'),
            array('html' => 'dbxapp_user_admin.html', 'folder' => 'operations', 'title' => 'Administration und Systemstatus', 'menu' => 'Administration', 'permalink' => 'dokumentation-administration'),
            array('html' => 'dbxapp_user_content.html', 'folder' => 'content', 'title' => 'Inhalte, CMS und Medien', 'menu' => 'CMS & Medien', 'permalink' => 'dokumentation-cms-medien'),
            array('html' => 'dbxapp_user_shop.html', 'folder' => 'shop', 'title' => 'Shop bedienen und administrieren', 'menu' => 'Shop bedienen', 'permalink' => 'dokumentation-shop-bedienung'),
            array('html' => 'dbxapp_user_workflows.html', 'folder' => 'workflow', 'title' => 'Workflows erstellen und benutzen', 'menu' => 'Workflows bedienen', 'permalink' => 'dokumentation-workflow-bedienung'),
            array('html' => 'dbxapp_user_design.html', 'folder' => 'design', 'title' => 'Designs manuell und mit KI erstellen', 'menu' => 'Design-Tutorial', 'permalink' => 'dokumentation-design-tutorial'),
            array('html' => 'dbxapp_user_ki_content.html', 'folder' => 'content', 'title' => 'KI-Bereich Content', 'menu' => 'KI: Content', 'permalink' => 'dokumentation-ki-content'),
            array('html' => 'dbxapp_user_ki_design.html', 'folder' => 'design', 'title' => 'KI-Bereich Design', 'menu' => 'KI: Design', 'permalink' => 'dokumentation-ki-design'),
            array('html' => 'dbxapp_user_ki_modules.html', 'folder' => 'modules', 'title' => 'KI-Bereich Module', 'menu' => 'KI: Module', 'permalink' => 'dokumentation-ki-module'),
            array('html' => 'dbxapp_developer_docs.html', 'folder' => 'architecture', 'title' => 'Entwicklerdokumentation', 'menu' => 'Entwicklerhandbuch', 'permalink' => 'dokumentation-entwickler'),
            array('html' => 'dbxapp_dev_orientation.html', 'folder' => 'architecture', 'title' => 'Architektur und Laufzeit', 'menu' => 'Architektur & Laufzeit', 'permalink' => 'dokumentation-architektur-laufzeit'),
            array('html' => 'dbxapp_dev_modules.html', 'folder' => 'modules', 'title' => 'Verbindliche Modulentwicklung', 'menu' => 'Modulentwicklung', 'permalink' => 'dokumentation-modulentwicklung'),
            array('html' => 'dbxapp_dev_pipelines.html', 'folder' => 'libraries', 'title' => 'Kernpipelines und Bibliotheken', 'menu' => 'Kernpipelines', 'permalink' => 'dokumentation-kernpipelines'),
            array('html' => 'dbxapp_dev_content_design.html', 'folder' => 'architecture', 'title' => 'CMS, KI, Design und Fachmodule', 'menu' => 'Content & Fachmodule', 'permalink' => 'dokumentation-content-fachmodule'),
            array('html' => 'dbxapp_dev_operations.html', 'folder' => 'operations', 'title' => 'Installation, Updates und Betrieb', 'menu' => 'Betrieb für Entwickler', 'permalink' => 'dokumentation-betrieb-entwickler'),
            array('html' => 'dbxapp_system_overview.html', 'folder' => 'start', 'title' => 'Systemüberblick', 'menu' => 'Systemüberblick', 'permalink' => 'dokumentation-systemueberblick'),
            array('html' => 'dbxapp_routing_templates.html', 'folder' => 'architecture', 'title' => 'Routing, Templates und Modul-Inclusion', 'menu' => 'Routing & Content', 'permalink' => 'dokumentation-routing-content'),
            array('html' => 'dbxapp_dbxtpl.html', 'folder' => 'libraries', 'title' => 'dbxTPL-Leitfaden', 'menu' => 'dbxTPL', 'permalink' => 'dokumentation-dbxtpl'),
            array('html' => 'dbxapp_dbxdb_dd_fd.html', 'folder' => 'libraries', 'title' => 'dbxDB, DD und FD', 'menu' => 'dbxDB, DD & FD', 'permalink' => 'dokumentation-dbxdb-dd-fd'),
            array('html' => 'dbxapp_dbxform.html', 'folder' => 'libraries', 'title' => 'dbxForm-Leitfaden', 'menu' => 'dbxForm', 'permalink' => 'dokumentation-dbxform'),
            array('html' => 'dbxapp_dbxreport.html', 'folder' => 'libraries', 'title' => 'dbxReport-Leitfaden', 'menu' => 'dbxReport', 'permalink' => 'dokumentation-dbxreport'),
            array('html' => 'dbxapp_module_patterns.html', 'folder' => 'modules', 'title' => 'Module entwickeln', 'menu' => 'Modul-Patterns', 'permalink' => 'dokumentation-module-entwickeln'),
            array('html' => 'dbxapp_module_quickstart.html', 'folder' => 'modules', 'title' => 'Modul in 30 Minuten', 'menu' => 'Modul-Schnellstart', 'permalink' => 'dokumentation-modul-schnellstart'),
            array('html' => 'dbxapp_javascript_libs.html', 'folder' => 'modules', 'title' => 'JavaScript-Systembibliotheken', 'menu' => 'JavaScript', 'permalink' => 'dokumentation-javascript'),
            array('html' => 'dbxapp_cms_dbxki.html', 'folder' => 'content', 'title' => 'CMS und dbxKi', 'menu' => 'CMS & dbxKi', 'permalink' => 'dokumentation-cms-dbxki'),
            array('html' => 'dbxapp_core_classes.html', 'folder' => 'libraries', 'title' => 'Core-Klassen', 'menu' => 'Core-Klassen', 'permalink' => 'dokumentation-core-klassen'),
            array('html' => 'dbxapp_ai_rules.html', 'folder' => 'modules', 'title' => 'Verbindliche Regeln für KI-Agenten', 'menu' => 'KI-Regeln', 'permalink' => 'dokumentation-ki-regeln'),
            array('html' => 'dbxapp_credits.html', 'folder' => 'service', 'title' => 'Bibliotheken, Credits und Lizenzen', 'menu' => 'Lizenzen', 'permalink' => 'dokumentation-lizenzen'),
            array('html' => 'dbxapp_dbxinterpreter.html', 'folder' => 'libraries', 'title' => 'dbxInterpreter-Leitfaden', 'menu' => 'dbxInterpreter', 'permalink' => 'dokumentation-dbxinterpreter'),
            array('html' => 'dbxapp_design_themes_skins.html', 'folder' => 'design', 'title' => 'Designs, Themes und Skins', 'menu' => 'Design-Grundlagen', 'permalink' => 'dokumentation-designs-skins'),
            array('html' => 'dbxapp_design_ai_reference.html', 'folder' => 'design', 'title' => 'Design-KI-Referenz', 'menu' => 'Design per KI', 'permalink' => 'dokumentation-design-ki'),
            array('html' => 'dbxapp_shop_guide.html', 'folder' => 'shop', 'title' => 'Shop-Leitfaden', 'menu' => 'Shop-Grundlagen', 'permalink' => 'dokumentation-shop'),
            array('html' => 'dbxapp_shop_ai_reference.html', 'folder' => 'shop', 'title' => 'Shop-KI-Referenz', 'menu' => 'Shop per KI', 'permalink' => 'dokumentation-shop-ki'),
            array('html' => 'dbxapp_workflow_guide.html', 'folder' => 'workflow', 'title' => 'dbxWorkflow-Leitfaden', 'menu' => 'Workflow-Grundlagen', 'permalink' => 'dokumentation-workflows'),
            array('html' => 'dbxapp_workflow_create.html', 'folder' => 'workflow', 'title' => 'Workflow erstellen', 'menu' => 'Workflow erstellen', 'permalink' => 'dokumentation-workflow-erstellen'),
            array('html' => 'dbxapp_workflow_use.html', 'folder' => 'workflow', 'title' => 'Workflow nutzen', 'menu' => 'Workflow nutzen', 'permalink' => 'dokumentation-workflow-nutzen'),
            array('html' => 'dbxapp_current_operations.html', 'folder' => 'operations', 'title' => 'Aktueller Stand, Betrieb und Qualität', 'menu' => 'Betriebsstand', 'permalink' => 'dokumentation-betriebsstand'),
            array('html' => 'dbxapp_security_integrity_performance.html', 'folder' => 'operations', 'title' => 'Sicherheit, Integrität und Performance', 'menu' => 'Sicherheit & Performance', 'permalink' => 'dokumentation-sicherheit-performance'),
            array('html' => 'dbxapp_db_roundtrip.html', 'folder' => 'operations', 'title' => 'DB3–MySQL–DB3-Roundtrip', 'menu' => 'DB3 ↔ MySQL', 'permalink' => 'dokumentation-db3-mysql'),
            array('html' => 'dbxapp_module_reference.html', 'folder' => 'modules', 'title' => 'Verbindliches Modulhandbuch', 'menu' => 'Modulhandbuch', 'permalink' => 'dokumentation-modulhandbuch'),
            array('html' => 'dbxapp_design_studio_ki.html', 'folder' => 'design', 'title' => 'Design Studio und KI-Wizard', 'menu' => 'Design Studio', 'permalink' => 'dokumentation-design-studio'),
            array('html' => 'dbxapp_install_update_dd_bindings.html', 'folder' => 'operations', 'title' => 'Installation, Updates und DD-Serverbindungen', 'menu' => 'Installation & Updates', 'permalink' => 'dokumentation-installation-updates'),
            array('html' => 'dbxapp_ki_areas.html', 'folder' => 'content', 'title' => 'Die drei KI-Bereiche in dbxapp', 'menu' => 'KI-Bereiche', 'permalink' => 'dokumentation-ki-bereiche'),
            array('html' => 'dbxapp_documentation_portal.html', 'folder' => 'operations', 'title' => 'Dokumentationsportal betreiben und veröffentlichen', 'menu' => 'Dokumentationsportal', 'permalink' => 'dokumentation-portal-betrieb'),
            array('html' => 'dbxapp_user_system_update.html', 'folder' => 'operations', 'title' => 'System-Updates sicher durchführen', 'menu' => 'System-Update', 'permalink' => 'dokumentation-system-update'),
        );
    }

    private function legacyPlan(): array
    {
        return array(
            array('file' => 'files/doku/doku-anwendung.html', 'folder' => 'deep', 'title' => 'Anwendungsentwicklung mit dbxapp', 'menu' => 'Anwendungsentwicklung', 'permalink' => 'dokumentation-anwendungsentwicklung'),
            array('file' => 'files/doku/doku-dbxapp-form-report-lifecycle-analyse.html', 'folder' => 'deep', 'title' => 'Form- und Report-Lifecycle', 'menu' => 'Form & Report Lifecycle', 'permalink' => 'dokumentation-form-report-lifecycle'),
            array('file' => 'files/doku/doku-dbxapp-komponenten-stecknorm.html', 'folder' => 'deep', 'title' => 'Komponenten-Stecknorm', 'menu' => 'Komponenten-Stecknorm', 'permalink' => 'dokumentation-komponenten-stecknorm'),
            array('file' => 'files/doku/doku-dbxapp-lego-rad-roadmap.html', 'folder' => 'deep', 'title' => 'LEGO-RAD-Roadmap', 'menu' => 'RAD-Roadmap', 'permalink' => 'dokumentation-rad-roadmap'),
            array('file' => 'files/doku/doku-dbxapp-rad-konzept.html', 'folder' => 'deep', 'title' => 'RAD-Konzept', 'menu' => 'RAD-Konzept', 'permalink' => 'dokumentation-rad-konzept'),
            array('file' => 'files/doku/doku-modulentwickler.html', 'folder' => 'deep', 'title' => 'Leitfaden für Modulentwickler', 'menu' => 'Modulentwickler', 'permalink' => 'dokumentation-modulentwickler'),
            array('file' => 'files/doku/doku-system.html', 'folder' => 'deep', 'title' => 'Technische Systemdokumentation', 'menu' => 'Systemdokumentation', 'permalink' => 'dokumentation-system'),
            array('file' => 'files/doku/md_files_2doku_2doku-dbxapp-libs-attribute.html', 'folder' => 'deep', 'title' => 'dbxapp-Library-Attribute', 'menu' => 'Library-Attribute', 'permalink' => 'dokumentation-library-attribute', 'reference' => 'md_files_2doku_2doku-dbxapp-libs-attribute.html'),
            array('file' => 'files/doku/doku-codex-arbeitsanweisung-dbxapp.html', 'folder' => 'ai-instructions', 'title' => 'Codex-Arbeitsanweisung für dbxapp', 'menu' => 'Codex-Arbeitsanweisung', 'permalink' => 'dokumentation-codex-anweisung'),
            array('file' => 'files/doku/doku-dbxapp-codex-arbeitsleitfaden.html', 'folder' => 'ai-instructions', 'title' => 'Codex-Arbeitsleitfaden', 'menu' => 'Codex-Leitfaden', 'permalink' => 'dokumentation-codex-leitfaden'),
            array('file' => 'files/doku/chat-gpt-prompt-analyse.txt', 'folder' => 'ai-instructions', 'title' => 'KI-Prompt für Analysen', 'menu' => 'Prompt: Analyse', 'permalink' => 'dokumentation-prompt-analyse'),
            array('file' => 'files/doku/chat-gpt-prompt-codex.txt', 'folder' => 'ai-instructions', 'title' => 'KI-Prompt für Codex', 'menu' => 'Prompt: Codex', 'permalink' => 'dokumentation-prompt-codex'),
            array('file' => 'files/doku/chat-gpt-prompt-entwicklung.txt', 'folder' => 'ai-instructions', 'title' => 'KI-Prompt für Entwicklung', 'menu' => 'Prompt: Entwicklung', 'permalink' => 'dokumentation-prompt-entwicklung'),
        );
    }

    private function prepareHtmlMap(): void
    {
        foreach ($this->pagePlan() as $page) {
            $this->htmlToPermalink[strtolower((string)$page['html'])] = (string)$page['permalink'];
        }
    }

    private function prepareFolderTree(): void
    {
        foreach ($this->folderPlan() as $language => $folders) {
            foreach ($folders as $folder) {
                $parentKey = (string)$folder['parent'];
                $parentId = $parentKey === '' ? 0 : (int)($this->folderIds[$language][$parentKey] ?? 0);
                $id = $this->ensureFolder(
                    $language,
                    (string)$folder['name'],
                    $parentId,
                    (string)$folder['sorter'],
                    is_array($folder['legacy_names'] ?? null) ? $folder['legacy_names'] : array()
                );
                $this->folderIds[$language][(string)$folder['key']] = $id;
            }
        }
    }

    private function ensureFolder(
        string $language,
        string $name,
        int $parentId,
        string $sorter,
        array $legacyNames = array()
    ): int
    {
        $dd = dbxContentLng::ddFolder($language);
        $row = $this->db->select1($dd, array('parent_id' => $parentId, 'name' => $name), 'id,name,parent_id,sorter', 0);
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            if ((string)($row['sorter'] ?? '') !== $sorter) {
                $this->db->update($dd, array('sorter' => $sorter), $id, 0, 1, 0, 1);
                $this->result['folders_updated']++;
            }
            return $id;
        }

        foreach (array_values(array_unique(array_merge(array($name), $legacyNames))) as $candidateName) {
            $candidateName = trim((string)$candidateName);
            if ($candidateName === '') {
                continue;
            }
            $candidate = $this->db->select1($dd, array('name' => $candidateName), 'id,name,parent_id,sorter', 0);
            $candidateId = (int)($candidate['id'] ?? 0);
            if ($candidateId <= 0) {
                continue;
            }
            if ($this->db->update($dd, array(
                'name' => $name,
                'parent_id' => $parentId,
                'sorter' => $sorter,
            ), $candidateId, 0, 1, 0, 1) !== 1) {
                throw new RuntimeException('Ordner konnte nicht verschoben werden: ' . $language . '/' . $candidateName);
            }
            $this->result['folders_updated']++;
            return $candidateId;
        }

        $created = $this->db->insert($dd, array(
            'name' => $name,
            'parent_id' => $parentId,
            'group_read' => '*',
            'sorter' => $sorter,
        ), 0, 1, 0, 1);
        if ($created !== 1) {
            throw new RuntimeException('Ordner konnte nicht angelegt werden: ' . $language . '/' . $name);
        }
        $this->result['folders_created']++;
        return (int)$this->db->get_insert_id();
    }

    private function moveTutorialFolders(): void
    {
        foreach (array('de', 'en', 'es') as $language) {
            $dd = dbxContentLng::ddFolder($language);
            $rows = $this->db->select($dd, '', 'id,name,parent_id,sorter', 'id', 'ASC', '', 0, 0, 0);
            $targetId = (int)($this->folderIds[$language]['tutorials'] ?? 0);
            $rootId = (int)($this->folderIds[$language]['root'] ?? 0);

            foreach (is_array($rows) ? $rows : array() as $row) {
                $id = (int)($row['id'] ?? 0);
                $name = trim((string)($row['name'] ?? ''));
                if ($id <= 0 || $id === $targetId || stripos($name, 'tutor') === false) {
                    continue;
                }

                $pages = $this->db->select(
                    dbxContentLng::ddContent($language),
                    array('folder' => $id),
                    'id',
                    'id',
                    'ASC',
                    '',
                    1,
                    0,
                    0
                );
                if (!is_array($pages) || $pages === array()) {
                    continue;
                }

                if ($this->folderIsEmpty($language, $targetId)) {
                    $this->db->delete($dd, $targetId, 0, 1);
                    $this->db->update($dd, array(
                        'name' => array('de' => 'Anwenden', 'en' => 'Use', 'es' => 'Usar')[$language],
                        'parent_id' => $rootId,
                        'sorter' => '0020',
                    ), $id, 0, 1, 0, 1);
                    $this->folderIds[$language]['tutorials'] = $id;
                    $this->result['folders_updated']++;
                }
                break;
            }
        }
    }

    /**
     * Legt für jeden Hauptbereich eine echte CMS-Einstiegsseite an.
     *
     * Die Designschale wird später anhand des Ordnerpfads als eigenes
     * dbx_page gewählt. Die Einstiegsseiten bleiben reguläre dbxContent-
     * Datensätze und sind deshalb suchbar, editierbar und cachefähig.
     */
    private function provisionAreaLandingPages(): void
    {
        $areas = array(
            'de' => array(
                'start' => array(
                    'title' => 'Einstieg', 'permalink' => 'dokumentation-einstieg',
                    'lead' => 'Orientierung, erste Schritte und der schnellste Weg zur passenden Anleitung.',
                    'links' => array(
                        array('dokumentation-erste-schritte', 'Erste Schritte', 'Anmelden, navigieren und sicher beginnen.'),
                        array('dokumentation-systemueberblick', 'Systemüberblick', 'Bereiche und Zusammenspiel von dbxapp verstehen.'),
                        array('tutorial-installation', 'Installation als Tutorial', 'Eine neue Installation kontrolliert einrichten.'),
                    ),
                ),
                'tutorials' => array(
                    'title' => 'Anwenden', 'permalink' => 'dokumentation-anwenden',
                    'lead' => 'Aufgaben im CMS, Design, Shop und Workflow Schritt für Schritt erledigen.',
                    'links' => array(
                        array('dokumentation-cms-medien', 'CMS & Medien', 'Inhalte, Bilder und Videos zuverlässig pflegen.'),
                        array('dokumentation-design-tutorial', 'Design', 'Oberflächen manuell oder mit KI gestalten.'),
                        array('dokumentation-shop-bedienung', 'Shop', 'Produkte, Kanäle und Bestellungen bearbeiten.'),
                        array('dokumentation-workflow-bedienung', 'Workflows', 'Abläufe erstellen, starten und kontrollieren.'),
                    ),
                ),
                'development' => array(
                    'title' => 'Entwickeln', 'permalink' => 'dokumentation-entwickeln',
                    'lead' => 'Architektur, Templates, Kernbibliotheken, Module und JavaScript nach dem dbxapp-Weg.',
                    'links' => array(
                        array('dokumentation-architektur-laufzeit', 'Architektur', 'Request, Runtime und Modulgrenzen verstehen.'),
                        array('dokumentation-modul-schnellstart', 'Modul-Schnellstart', 'Ein dbxapp-Modul in einem Arbeitsgang aufbauen.'),
                        array('dokumentation-dbxtpl', 'Templates', 'dbxTPL und Modul-Inclusion korrekt einsetzen.'),
                        array('dokumentation-javascript', 'JavaScript', 'Systembibliotheken und Lebenszyklus sicher nutzen.'),
                    ),
                ),
                'operations' => array(
                    'title' => 'Betrieb', 'permalink' => 'dokumentation-betrieb',
                    'lead' => 'Installation, Updates, Selbsttests, Sicherheit und laufenden Betrieb beherrschen.',
                    'links' => array(
                        array('dokumentation-installation', 'Installation', 'Voraussetzungen und produktionsreife Einrichtung.'),
                        array('dokumentation-selbsttest', 'System-Selbsttest', 'PHP- und JavaScript-Prüfungen ausführen und auswerten.'),
                        array('dokumentation-system-update', 'Updates', 'Versionen kontrolliert und rückrollbar aktualisieren.'),
                        array('dokumentation-sicherheit-performance', 'Sicherheit & Performance', 'Integrität prüfen und Laufzeit gezielt optimieren.'),
                    ),
                ),
            ),
            'en' => array(
                'start' => array('title' => 'Getting started', 'permalink' => 'documentation-getting-started', 'lead' => 'Orientation and the shortest route to the right dbxapp guide.'),
                'tutorials' => array('title' => 'Use', 'permalink' => 'documentation-use', 'lead' => 'Complete day-to-day tasks in CMS, design, shop and workflows.'),
                'development' => array('title' => 'Develop', 'permalink' => 'documentation-develop', 'lead' => 'Build modules, templates and JavaScript using dbxapp conventions.'),
                'operations' => array('title' => 'Operate', 'permalink' => 'documentation-operate', 'lead' => 'Install, update, test and operate dbxapp reliably.'),
            ),
            'es' => array(
                'start' => array('title' => 'Primeros pasos', 'permalink' => 'documentacion-primeros-pasos', 'lead' => 'Orientación y el camino más corto hacia la guía adecuada de dbxapp.'),
                'tutorials' => array('title' => 'Usar', 'permalink' => 'documentacion-usar', 'lead' => 'Realice tareas en CMS, diseño, tienda y flujos de trabajo.'),
                'development' => array('title' => 'Desarrollar', 'permalink' => 'documentacion-desarrollar', 'lead' => 'Cree módulos, plantillas y JavaScript según las convenciones de dbxapp.'),
                'operations' => array('title' => 'Operar', 'permalink' => 'documentacion-operar', 'lead' => 'Instale, actualice, pruebe y opere dbxapp de forma fiable.'),
            ),
        );

        foreach ($areas as $language => $languageAreas) {
            foreach ($languageAreas as $folderKey => $area) {
                $links = is_array($area['links'] ?? null) ? $area['links'] : array(
                    array('?dbx_modul=dbxDocs&dbx_run1=search', 'Search', 'Search all available manuals and references.'),
                    array('?dbx_modul=dbxDocs&dbx_run1=reference&dbx_run2=overview', 'API reference', 'Open the generated technical reference.'),
                );
                $cards = '';
                foreach ($links as $link) {
                    $cards .= '<a class="dbxdocs-path-card" href="' . dbx()->esc((string)$link[0]) . '">'
                        . '<i class="bi bi-arrow-right-circle" aria-hidden="true"></i>'
                        . '<span><strong>' . dbx()->esc((string)$link[1]) . '</strong><small>'
                        . dbx()->esc((string)$link[2]) . '</small></span></a>';
                }
                $revision = '2026-08-01-area-pages-v1';
                $content = '<article class="dbxdocs-cms-article dbxdocs-area-landing" data-dbx-doc-revision="' . $revision . '">'
                    . '<header class="dbxdocs-area-hero"><span class="dbxdocs-kicker">dbxapp Wissen</span>'
                    . '<h2>' . dbx()->esc((string)$area['title']) . '</h2><p class="lead">'
                    . dbx()->esc((string)$area['lead']) . '</p></header>'
                    . '<div class="dbxdocs-path-grid">' . $cards . '</div></article>';
                $page = array(
                    'activ' => 1,
                    'folder' => (int)($this->folderIds[$language][$folderKey] ?? 0),
                    'title' => (string)$area['title'],
                    'menu_title' => (string)$area['title'],
                    'seo_title' => (string)$area['title'] . ' – dbxapp Dokumentation',
                    'permalink' => (string)$area['permalink'],
                    'description' => (string)$area['lead'],
                    'keywords' => 'dbxapp, documentation, area-page',
                    'group_read' => '*',
                    'sorter' => '0001',
                    'template' => 'parent',
                    'content' => $content,
                );
                $dd = dbxContentLng::ddContent($language);
                $existing = $this->db->select1($dd, array('permalink' => $page['permalink']), 'id,content', 0);
                $id = (int)($existing['id'] ?? 0);
                if ($id > 0 && !str_contains((string)($existing['content'] ?? ''), 'data-dbx-doc-revision="' . $revision . '"')) {
                    if ($this->db->update($dd, $page, $id, 0, 1, 0, 1) === 1) {
                        $this->result['pages_updated']++;
                    }
                    continue;
                }
                $this->upsertPage($language, $page);
            }
        }
    }

    private function folderIsEmpty(string $language, int $folderId): bool
    {
        if ($folderId <= 0) {
            return true;
        }
        $pages = $this->db->select(dbxContentLng::ddContent($language), array('folder' => $folderId), 'id', 'id', 'ASC', '', 1, 0, 0);
        $folders = $this->db->select(dbxContentLng::ddFolder($language), array('parent_id' => $folderId), 'id', 'id', 'ASC', '', 1, 0, 0);
        return (!is_array($pages) || $pages === array()) && (!is_array($folders) || $folders === array());
    }

    private function shortenTutorialTitles(): void
    {
        foreach (array('de', 'en', 'es') as $language) {
            $folderId = (int)($this->folderIds[$language]['tutorials'] ?? 0);
            if ($folderId <= 0) {
                continue;
            }
            $dd = dbxContentLng::ddContent($language);
            $rows = $this->db->select(
                $dd,
                array('folder' => $folderId),
                'id,title,menu_title,seo_title',
                'sorter,title,id',
                'ASC',
                '',
                0,
                0,
                0
            );
            foreach (is_array($rows) ? $rows : array() as $row) {
                $id = (int)($row['id'] ?? 0);
                $fullTitle = trim((string)($row['seo_title'] ?? ''));
                if ($fullTitle === '') {
                    $fullTitle = trim((string)($row['title'] ?? ''));
                }
                $menuTitle = $this->shortTutorialTitle((string)($row['title'] ?? ''));
                if ($id <= 0 || $menuTitle === '') {
                    continue;
                }
                if (!$this->force && trim((string)($row['menu_title'] ?? '')) !== '') {
                    continue;
                }
                if ($this->db->update($dd, array(
                    'title' => $menuTitle,
                    'menu_title' => $menuTitle,
                    'seo_title' => $fullTitle,
                ), $id, 0, 1, 0, 1) === 1) {
                    $this->result['pages_updated']++;
                }
            }
        }
    }

    private function shortTutorialTitle(string $title): string
    {
        $title = trim((string)preg_replace('/^Tutorial\s*:\s*/iu', '', $title));
        $replacements = array(
            'Login, Profil und Passwort' => 'Login, Profil & Passwort',
            'Designs manuell und mit dbxKi erstellen' => 'Design manuell & per KI',
            'CMS Felder, Kopfleiste und Einstellungen' => 'CMS Felder & Einstellungen',
            'CMS Medienverwendung' => 'CMS Medien',
            'Shop im Frontend benutzen' => 'Shop-Frontend',
            'Shop Dashboard' => 'Shop-Dashboard',
            'Admin Dashboard' => 'Admin-Dashboard',
            'Frontpage direkt bearbeiten' => 'Frontpage bearbeiten',
        );
        $title = $replacements[$title] ?? $title;
        if (mb_strlen($title) <= 48) {
            return $title;
        }
        $short = mb_substr($title, 0, 47);
        $space = mb_strrpos($short, ' ');
        if ($space !== false && $space > 28) {
            $short = mb_substr($short, 0, $space);
        }
        return rtrim($short, " \t\n\r\0\x0B,.;:-") . '…';
    }

    private function provisionGermanDocumentation(): void
    {
        $sorterByFolder = array();
        foreach ($this->pagePlan() as $page) {
            $folderKey = (string)$page['folder'];
            $sorterByFolder[$folderKey] = ($sorterByFolder[$folderKey] ?? 0) + 10;
            try {
                $content = $this->extractDoxygenContents((string)$page['html']);
                $this->upsertPage('de', array(
                    'activ' => 1,
                    'folder' => (int)($this->folderIds['de'][$folderKey] ?? 0),
                    'title' => (string)$page['menu'],
                    'menu_title' => (string)$page['menu'],
                    'seo_title' => (string)$page['title'],
                    'permalink' => (string)$page['permalink'],
                    'description' => 'dbxapp-Dokumentation: ' . (string)$page['title'],
                    'keywords' => 'dbxapp, Dokumentation, dbxdocs-migration',
                    'group_read' => '*',
                    'sorter' => sprintf('%04d', $sorterByFolder[$folderKey]),
                    'template' => 'parent',
                    'content' => $content,
                ));
            } catch (\Throwable $exception) {
                $existing = $this->db->select1(
                    dbxContentLng::ddContent('de'),
                    array('permalink' => (string)$page['permalink']),
                    'id',
                    0
                );
                if ((int)($existing['id'] ?? 0) > 0) {
                    $this->result['pages_preserved']++;
                    continue;
                }
                $this->result['errors'][] = (string)$page['html'] . ': ' . $exception->getMessage();
            }
        }
    }

    /**
     * Hält die öffentliche Startseite bewusst kurz und reproduzierbar.
     *
     * Der Revisionsmarker schützt spätere redaktionelle Änderungen. Eine neue
     * gebündelte Revision ersetzt den vorherigen Portalblock vollständig und
     * entfernt damit auch historische Animationen oder Tutorial-Gesamtlisten.
     */
    private function provisionDocumentationHome(): void
    {
        $file = dirname(__DIR__) . '/content/dbxapp_home.html';
        $content = is_file($file) ? trim((string)file_get_contents($file)) : '';
        $revision = '2026-08-01-portal-v5';
        if ($content === '' || !str_contains($content, 'data-dbx-doc-revision="' . $revision . '"')) {
            $this->result['errors'][] = 'dbxapp_home.html: Gebündelte Startseite fehlt oder ist ungültig.';
            return;
        }
        $content = $this->lowerHtmlHeadingLevels($content);

        $dd = dbxContentLng::ddContent('de');
        $row = $this->db->select1(
            $dd,
            array('permalink' => 'tutorials-dbxapp'),
            'id,content',
            0
        );
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            $this->result['errors'][] = 'Dokumentations-Startseite tutorials-dbxapp wurde nicht gefunden.';
            return;
        }
        if (str_contains((string)($row['content'] ?? ''), 'data-dbx-doc-revision="' . $revision . '"')) {
            return;
        }
        if ($this->db->update($dd, array('content' => $content), $id, 0, 1, 0, 1) === 1) {
            $this->result['pages_updated']++;
        }
    }

    private function provisionLegacyDocumentation(): void
    {
        $sorterByFolder = array();
        foreach ($this->legacyPlan() as $page) {
            $folderKey = (string)$page['folder'];
            $sorterByFolder[$folderKey] = ($sorterByFolder[$folderKey] ?? 0) + 10;
            try {
                $content = !empty($page['reference'])
                    ? $this->extractDoxygenContents((string)$page['reference'])
                    : $this->extractLegacyContents((string)$page['file']);
                $this->upsertPage('de', array(
                    'activ' => 1,
                    'folder' => (int)($this->folderIds['de'][$folderKey] ?? 0),
                    'title' => (string)$page['menu'],
                    'menu_title' => (string)$page['menu'],
                    'seo_title' => (string)$page['title'],
                    'permalink' => (string)$page['permalink'],
                    'description' => 'Vertiefende dbxapp-Dokumentation: ' . (string)$page['title'],
                    'keywords' => 'dbxapp, Dokumentation, Vertiefung, dbxdocs-migration',
                    'group_read' => '*',
                    'sorter' => sprintf('%04d', $sorterByFolder[$folderKey]),
                    'template' => 'parent',
                    'content' => $content,
                ));
            } catch (\Throwable $exception) {
                $this->result['errors'][] = (string)$page['file'] . ': ' . $exception->getMessage();
            }
        }
    }

    private function provisionServicePages(): void
    {
        $imprintDe = $this->db->select1(
            dbxContentLng::ddContent('de'),
            array('permalink' => 'impressum'),
            'id,lng_uid',
            0
        );
        $imprintUid = trim((string)($imprintDe['lng_uid'] ?? ''));
        $this->movePageToService('de', (int)($imprintDe['id'] ?? 0), 'Impressum', '0080');

        if ($imprintUid !== '') {
            foreach (array('en' => 'Imprint', 'es' => 'Aviso legal') as $language => $menuTitle) {
                $row = $this->db->select1(
                    dbxContentLng::ddContent($language),
                    array('lng_uid' => $imprintUid),
                    'id',
                    0
                );
                $this->movePageToService($language, (int)($row['id'] ?? 0), $menuTitle, '0080');
            }
        }

        $contactPages = array(
            'de' => array('title' => 'Kontakt', 'description' => 'Kontaktieren Sie das dbxapp-Team.', 'menu' => 'Kontakt'),
            'en' => array('title' => 'Contact', 'description' => 'Contact the dbxapp team.', 'menu' => 'Contact'),
            'es' => array('title' => 'Contacto', 'description' => 'Póngase en contacto con el equipo de dbxapp.', 'menu' => 'Contacto'),
        );
        foreach ($contactPages as $language => $labels) {
            $this->upsertPage($language, array(
                'activ' => 1,
                'folder' => (int)($this->folderIds[$language]['service'] ?? 0),
                'title' => $labels['title'],
                'menu_title' => $labels['menu'],
                'permalink' => 'dokumentation-kontakt-' . $language,
                'description' => $labels['description'],
                'keywords' => 'dbxapp, contact, dbxdocs-migration',
                'group_read' => '*',
                'sorter' => '0090',
                'template' => 'parent',
                'content' => '<p class="lead">' . dbx()->esc($labels['description']) . '</p>'
                    . '[modul=dbxContact]dbx_run1=form[/modul]',
            ));
        }
    }

    private function movePageToService(string $language, int $id, string $menuTitle, string $sorter): void
    {
        if ($id <= 0) {
            return;
        }
        $dd = dbxContentLng::ddContent($language);
        $targetFolder = (int)($this->folderIds[$language]['service'] ?? 0);
        $existing = $this->db->select1($dd, array('id' => $id), 'id,folder,menu_title,sorter', 0);
        if (
            (int)($existing['folder'] ?? 0) === $targetFolder
            && (string)($existing['menu_title'] ?? '') === $menuTitle
            && (string)($existing['sorter'] ?? '') === $sorter
        ) {
            return;
        }
        $updated = $this->db->update($dd, array(
            'folder' => $targetFolder,
            'menu_title' => $menuTitle,
            'sorter' => $sorter,
        ), $id, 0, 1, 0, 1);
        if ($updated === 1) {
            $this->result['pages_updated']++;
        }
    }

    /**
     * Spielt gezielte redaktionelle Verbesserungen genau einmal ein.
     *
     * Der Revisionsmarker bleibt im CMS-Inhalt erhalten. Spätere redaktionelle
     * Änderungen werden dadurch nicht bei jedem Provision-Lauf überschrieben.
     */
    private function provisionCuratedDocumentationUpdates(): void
    {
        $updates = array(
            'dokumentation-installation' => array(
                'revision' => '2026-08-01-installation-3',
                'file' => dirname(__DIR__) . '/content/dbxapp_user_installation.html',
                'page' => array(
                    'activ' => 1,
                    'folder_key' => 'operations',
                    'title' => 'dbxapp installieren',
                    'menu_title' => 'Installation',
                    'seo_title' => 'dbxapp installieren – Voraussetzungen und sieben Schritte',
                    'permalink' => 'dokumentation-installation',
                    'description' => 'dbxapp sicher installieren: Voraussetzungen, DB3 oder PDO, persönlicher Administrator und Abnahme.',
                    'keywords' => 'dbxapp, Installation, Setup, DB3, PDO, Administrator, dbxdocs-curated',
                    'group_read' => '*',
                    'sorter' => '0005',
                    'template' => 'parent',
                ),
            ),
            'tutorial-installation' => array(
                'revision' => '2026-08-01-tutorial-installation-3',
                'file' => dirname(__DIR__) . '/content/tutorial_installation.html',
                'page' => array(
                    'activ' => 1,
                    'folder_key' => 'tutorials',
                    'title' => 'Tutorial: dbxapp installieren',
                    'menu_title' => 'Installation',
                    'seo_title' => 'Tutorial: dbxapp Schritt für Schritt installieren',
                    'permalink' => 'tutorial-installation',
                    'description' => 'Geführte dbxapp-Installation mit Voraussetzungen, sieben Assistentenschritten, Kontrollpunkten, SelfTest und Fehlersuche.',
                    'keywords' => 'dbxapp, Tutorial, Installation, Setup, Administrator, SelfTest, dbxdocs-curated',
                    'group_read' => '*',
                    'sorter' => '0005',
                    'template' => 'parent',
                ),
            ),
            'dokumentation-selbsttest' => array(
                'revision' => '2026-08-01-selftest-3',
                'file' => dirname(__DIR__) . '/content/dbxapp_user_selftest.html',
                'page' => array(
                    'activ' => 1,
                    'folder_key' => 'operations',
                    'title' => 'System-Selbsttest',
                    'menu_title' => 'System-Selbsttest',
                    'seo_title' => 'dbxSelfTest – vollständige Systemtests und JavaScript-Prüfungen',
                    'permalink' => 'dokumentation-selbsttest',
                    'description' => 'dbxSelfTest vollständig erklärt: Schnell-, Komplett-, Auswahl- und Einzeltests, Browser-JavaScript, Protokolle und CLI.',
                    'keywords' => 'dbxapp, dbxSelfTest, Selbsttest, JavaScript, PHP, Qualitätssicherung, dbxdocs-curated',
                    'group_read' => '*',
                    'sorter' => '0015',
                    'template' => 'parent',
                ),
            ),
            'dokumentation-ki-design' => array(
                'revision' => '2026-08-01-ki-design-4',
                'file' => dirname(__DIR__) . '/content/dbxapp_user_ki_design.html',
            ),
            'dokumentation-dbxform-schnellstart' => array(
                'revision' => '2026-08-01-dbxform-quickstart-1',
                'file' => dirname(__DIR__) . '/content/dbxapp_dbxform_quickstart.html',
                'page' => array(
                    'activ' => 1,
                    'folder_key' => 'libraries',
                    'title' => 'dbxForm in einem Arbeitsgang',
                    'menu_title' => 'dbxForm · Schnellstart',
                    'seo_title' => 'dbxForm-Schnellstart – sicher vom DD/FD-Vertrag zum Speichern',
                    'permalink' => 'dokumentation-dbxform-schnellstart',
                    'description' => 'Kompakter dbxForm-Schnellstart mit verbindlichem Ablauf, Beispiel und Abnahme.',
                    'keywords' => 'dbxapp, dbxForm, Schnellstart, Formular, DD, FD, dbxdocs-curated',
                    'group_read' => '*',
                    'sorter' => '0025',
                    'template' => 'parent',
                ),
            ),
            'dokumentation-dbxreport-schnellstart' => array(
                'revision' => '2026-08-01-dbxreport-quickstart-1',
                'file' => dirname(__DIR__) . '/content/dbxapp_dbxreport_quickstart.html',
                'page' => array(
                    'activ' => 1,
                    'folder_key' => 'libraries',
                    'title' => 'dbxReport klar und sicher aufbauen',
                    'menu_title' => 'dbxReport · Schnellstart',
                    'seo_title' => 'dbxReport-Schnellstart – Filter, Pagination und Aktionen',
                    'permalink' => 'dokumentation-dbxreport-schnellstart',
                    'description' => 'Kompakter dbxReport-Schnellstart für Filter, Sortierung, Pagination, Aktionen und Abnahme.',
                    'keywords' => 'dbxapp, dbxReport, Schnellstart, Report, Pagination, Aktionen, dbxdocs-curated',
                    'group_read' => '*',
                    'sorter' => '0035',
                    'template' => 'parent',
                ),
            ),
        );

        $dd = dbxContentLng::ddContent('de');
        foreach ($updates as $permalink => $update) {
            $file = (string)$update['file'];
            $content = is_file($file) ? file_get_contents($file) : false;
            if (!is_string($content) || trim($content) === '') {
                $this->result['errors'][] = basename($file) . ': Kuratierter CMS-Inhalt fehlt.';
                continue;
            }
            $content = $this->lowerHtmlHeadingLevels(trim($content));
            $row = $this->db->select1(
                $dd,
                array('permalink' => $permalink),
                'id,content',
                0
            );
            $id = (int)($row['id'] ?? 0);
            $revision = (string)$update['revision'];
            $current = (string)($row['content'] ?? '');
            if ($id > 0 && str_contains($current, 'data-dbx-doc-revision="' . $revision . '"')) {
                continue;
            }

            $page = is_array($update['page'] ?? null) ? $update['page'] : array();
            if ($id <= 0) {
                if ($page === array()) {
                    $this->result['errors'][] = $permalink . ': Metadaten für die neue CMS-Seite fehlen.';
                    continue;
                }
                $folderKey = (string)($page['folder_key'] ?? '');
                unset($page['folder_key']);
                $page['folder'] = (int)($this->folderIds['de'][$folderKey] ?? 0);
                $page['content'] = $content;
                try {
                    $this->upsertPage('de', $page);
                } catch (\Throwable $exception) {
                    $this->result['errors'][] = $permalink . ': ' . $exception->getMessage();
                }
                continue;
            }

            $changes = array('content' => $content);
            if ($page !== array()) {
                $folderKey = (string)($page['folder_key'] ?? '');
                unset($page['folder_key']);
                $page['folder'] = (int)($this->folderIds['de'][$folderKey] ?? 0);
                $changes = array_merge($page, $changes);
            }
            if ($this->db->update($dd, $changes, $id, 0, 1, 0, 1) === 1) {
                $this->result['pages_updated']++;
                dbxContentPermalinkIndex::upsertPage(
                    $id,
                    $permalink,
                    (string)($changes['group_read'] ?? '*'),
                    (int)($changes['activ'] ?? 1),
                    'de'
                );
            }
        }
    }

    /** Kennzeichnet die langen Leitfäden als Vertiefung statt als Einstieg. */
    private function refineLongFormNavigation(): void
    {
        $dd = dbxContentLng::ddContent('de');
        $changes = array(
            'dokumentation-dbxform' => array('menu_title' => 'dbxForm · Gesamtdokument', 'sorter' => '0030'),
            'dokumentation-dbxreport' => array('menu_title' => 'dbxReport · Gesamtdokument', 'sorter' => '0040'),
        );
        foreach ($changes as $permalink => $navigation) {
            $row = $this->db->select1($dd, array('permalink' => $permalink), 'id,menu_title,sorter', 0);
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (
                (string)($row['menu_title'] ?? '') === $navigation['menu_title']
                && (string)($row['sorter'] ?? '') === $navigation['sorter']
            ) {
                continue;
            }
            if ($this->db->update($dd, $navigation, $id, 0, 1, 0, 1) === 1) {
                $this->result['pages_updated']++;
            }
        }
    }

    /**
     * Ergänzt alle redaktionellen Seiten um dieselbe sichtbare Qualitätszeile.
     * Der eigentliche CMS-Inhalt bleibt dabei unangetastet.
     */
    private function synchronizeDocumentationMetadata(): void
    {
        $dd = dbxContentLng::ddContent('de');
        $rows = $this->db->select(
            $dd,
            '',
            'id,permalink,content',
            'id',
            'ASC',
            '',
            0,
            0,
            0
        );
        foreach (is_array($rows) ? $rows : array() as $row) {
            $id = (int)($row['id'] ?? 0);
            $permalink = trim((string)($row['permalink'] ?? ''));
            $content = (string)($row['content'] ?? '');
            if (
                $id <= 0
                || $content === ''
                || $permalink === 'tutorials-dbxapp'
                || (!str_starts_with($permalink, 'dokumentation-') && !str_starts_with($permalink, 'tutorial-'))
            ) {
                continue;
            }

            $metadata = $this->documentationMetadataHtml($permalink);
            $normalizedContent = str_replace(array('dbXapp', 'DBXApp'), 'dbxapp', $content);
            $updated = preg_replace(
                '#\s*<!--\s*dbxdocs-meta:start\s*-->.*?<!--\s*dbxdocs-meta:end\s*-->\s*#is',
                "\n",
                $normalizedContent
            );
            $updated = trim(is_string($updated) ? $updated : $content);
            if (preg_match("/<(?:div|article)\\b[^>]*class=([\"'])[^\"']*\\bdbxdocs-cms-article\\b[^\"']*\\1[^>]*>/i", $updated, $match, PREG_OFFSET_CAPTURE) === 1) {
                $tag = (string)$match[0][0];
                $position = (int)$match[0][1] + strlen($tag);
                $updated = substr($updated, 0, $position)
                    . "\n" . $metadata . "\n"
                    . ltrim(substr($updated, $position));
            } else {
                $updated = $metadata . "\n" . $updated;
            }
            if ($updated === $content) {
                continue;
            }
            if ($this->db->update($dd, array('content' => $updated), $id, 0, 1, 0, 1) === 1) {
                $this->result['pages_updated']++;
            }
        }
    }

    private function documentationMetadataHtml(string $permalink): string
    {
        $type = 'Handbuch';
        $audience = 'Anwender';
        if (str_starts_with($permalink, 'tutorial-')) {
            $type = 'Tutorial';
        } elseif (preg_match('/(?:installation|selbsttest|betrieb|sicherheit|db3-mysql|system-update)/', $permalink) === 1) {
            $type = 'Betriebshandbuch';
            $audience = 'Administration';
        } elseif (preg_match('/(?:ki-agenten|ki-regeln|codex|prompt)/', $permalink) === 1) {
            $type = 'Arbeitsanweisung';
            $audience = 'KI-Agenten';
        } elseif (preg_match('/(?:entwickler|entwickeln|architektur|runtime|routing|dbxtpl|dbxdb|dbxform|dbxreport|modul|javascript|core|interpreter|rad|lifecycle|stecknorm)/', $permalink) === 1) {
            $type = str_contains($permalink, 'schnellstart') ? 'Schnellstart' : 'Entwicklerhandbuch';
            $audience = 'Entwickler';
        }

        $versionFile = $this->baseDir . '/VERSION';
        $version = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '4.1.3';
        $canonical = htmlspecialchars($permalink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<!-- dbxdocs-meta:start -->'
            . '<aside class="dbx-doc-meta" aria-label="Dokumentstatus">'
            . '<span><small>Seitentyp</small><strong>' . $type . '</strong></span>'
            . '<span><small>Zielgruppe</small><strong>' . $audience . '</strong></span>'
            . '<span><small>Gültig für</small><strong>dbxapp ' . htmlspecialchars($version, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></span>'
            . '<span><small>Stand</small><strong>1. August 2026</strong></span>'
            . '<a href="' . $canonical . '" rel="canonical"><small>Quelle</small><strong>Kanonische Seite</strong></a>'
            . '</aside>'
            . '<!-- dbxdocs-meta:end -->';
    }

    /** Der CMS-Seitentitel ist H1; redaktionelle Überschriften beginnen bei H2. */
    private function lowerHtmlHeadingLevels(string $html): string
    {
        if (preg_match('/<h1\b/i', $html) !== 1) {
            return $html;
        }
        $updated = preg_replace_callback(
            '/<(\/?)h([1-5])(\b[^>]*)>/i',
            static function (array $match): string {
                return '<' . (string)$match[1] . 'h' . (((int)$match[2]) + 1) . (string)$match[3] . '>';
            },
            $html
        );
        return is_string($updated) ? $updated : $html;
    }

    /**
     * Repariert alte Doxygen-Ziele in bereits übernommenen CMS-Seiten.
     *
     * Nur bekannte redaktionelle Dokumente und Tutorials werden umgeschrieben.
     * Echte Quellcode-Referenzen bleiben weiterhin im Doxygen-Bereich.
     */
    private function repairDocumentationLinks(): void
    {
        $dd = dbxContentLng::ddContent('de');
        $rows = $this->db->select(
            $dd,
            '',
            'id,permalink,content',
            'id',
            'ASC',
            '',
            0,
            0,
            0
        );

        foreach (is_array($rows) ? $rows : array() as $row) {
            $id = (int)($row['id'] ?? 0);
            $permalink = trim((string)($row['permalink'] ?? ''));
            $content = (string)($row['content'] ?? '');
            if (
                $id <= 0
                || $content === ''
                || (!str_starts_with($permalink, 'dokumentation-') && !str_starts_with($permalink, 'tutorial-'))
            ) {
                continue;
            }

            $updated = preg_replace_callback(
                '/\bhref=(["\'])(.*?)\1/isu',
                function (array $match): string {
                    $href = html_entity_decode((string)$match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $mapped = $this->knownCmsHref($href);
                    if ($mapped === '') {
                        return (string)$match[0];
                    }
                    return 'href=' . (string)$match[1]
                        . htmlspecialchars($mapped, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . (string)$match[1];
                },
                $content
            );

            if (!is_string($updated) || $updated === $content) {
                continue;
            }
            if ($this->db->update($dd, array('content' => $updated), $id, 0, 1, 0, 1) === 1) {
                $this->result['links_updated']++;
            }
        }
    }

    private function knownCmsHref(string $href): string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return '';
        }

        $fragment = '';
        if (str_contains($href, '#')) {
            [$href, $fragmentValue] = explode('#', $href, 2);
            $fragment = $fragmentValue !== '' ? '#' . $fragmentValue : '';
        }

        $query = parse_url($href, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
            $document = basename((string)($params['doc'] ?? ''));
            $mapped = $this->referenceDocumentPermalink($document);
            return $mapped !== '' ? $mapped . $fragment : '';
        }

        if (preg_match('#^https?://#i', $href)) {
            return '';
        }
        $path = parse_url($href, PHP_URL_PATH);
        $document = basename(is_string($path) ? $path : $href);
        $mapped = $this->referenceDocumentPermalink($document);
        return $mapped !== '' ? $mapped . $fragment : '';
    }

    private function upsertPage(string $language, array $data): int
    {
        $dd = dbxContentLng::ddContent($language);
        $existing = $this->db->select1($dd, array('permalink' => (string)$data['permalink']), '*', 0);
        $id = (int)($existing['id'] ?? 0);

        if ($id > 0) {
            $isImported = str_contains((string)($existing['keywords'] ?? ''), 'dbxdocs-migration');
            if (!$this->force && $isImported) {
                $this->result['pages_preserved']++;
                return $id;
            }
            if (!$this->force && !$isImported) {
                $navigation = array_intersect_key($data, array_flip(array('folder', 'menu_title', 'sorter')));
                $navigation = array_filter(
                    $navigation,
                    static function (mixed $value, string $field) use ($existing): bool {
                        if ($field === 'folder') {
                            return (int)($existing[$field] ?? 0) !== (int)$value;
                        }
                        return (string)($existing[$field] ?? '') !== (string)$value;
                    },
                    ARRAY_FILTER_USE_BOTH
                );
                if ($navigation !== array() && $this->db->update($dd, $navigation, $id, 0, 1, 0, 1) === 1) {
                    $this->result['pages_updated']++;
                }
                return $id;
            }
            if ($this->db->update($dd, $data, $id, 0, 1, 0, 1) !== 1) {
                throw new RuntimeException('CMS-Seite konnte nicht aktualisiert werden: ' . (string)$data['permalink']);
            }
            $this->result['pages_updated']++;
        } else {
            if ($this->db->insert($dd, $data, 0, 1, 0, 1) !== 1) {
                throw new RuntimeException('CMS-Seite konnte nicht angelegt werden: ' . (string)$data['permalink']);
            }
            $id = (int)$this->db->get_insert_id();
            $this->result['pages_created']++;
        }

        dbxContentPermalinkIndex::upsertPage(
            $id,
            (string)$data['permalink'],
            (string)($data['group_read'] ?? '*'),
            (int)($data['activ'] ?? 1),
            $language
        );
        return $id;
    }

    private function extractDoxygenContents(string $document): string
    {
        $file = $this->doxygenMigrationFile($document);
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Doxygen-Migrationsquelle fehlt.');
        }

        $html = file_get_contents($file);
        if (!is_string($html) || $html === '') {
            throw new RuntimeException('Doxygen-Migrationsquelle ist leer.');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " contents ")]');
        $contents = $nodes !== false ? $nodes->item(0) : null;
        if (!$contents instanceof DOMElement) {
            throw new RuntimeException('Doxygen-Inhaltsbereich wurde nicht gefunden.');
        }

        foreach ($xpath->query('.//script|.//form', $contents) ?: array() as $unsafe) {
            $unsafe->parentNode?->removeChild($unsafe);
        }
        foreach ($xpath->query('.//a[@href]', $contents) ?: array() as $link) {
            if ($link instanceof DOMElement) {
                $link->setAttribute('href', $this->rewriteReferenceLink($link->getAttribute('href')));
            }
        }
        foreach ($xpath->query('.//img[@src]', $contents) ?: array() as $image) {
            if (!$image instanceof DOMElement) {
                continue;
            }
            $src = trim($image->getAttribute('src'));
            if ($src !== '' && !preg_match('#^(?:https?:|data:|/)#i', $src)) {
                $image->setAttribute('src', 'docs/assets/' . basename(parse_url($src, PHP_URL_PATH) ?: $src));
            }
            $image->setAttribute('loading', 'lazy');
        }
        $this->normalizeEditorialHeadings($dom, $xpath, $contents);
        $this->neutralizeInterpreterMarkers($dom, $xpath, $contents);

        $out = '';
        foreach ($contents->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return '<div class="dbxdocs-cms-article">' . trim($out) . '</div>';
    }

    private function doxygenMigrationFile(string $document): string
    {
        $document = basename($document);
        $generated = $this->baseDir . '/dbx/modules/dbxDocs/content/generated/' . $document;
        if (is_file($generated)) {
            return $generated;
        }
        $current = $this->referenceDir . '/' . $document;
        if (is_file($current)) {
            return $current;
        }

        $archives = glob($this->baseDir . '/reference/archive/manual-content-migration-*', GLOB_ONLYDIR) ?: array();
        rsort($archives, SORT_NATURAL);
        foreach ($archives as $archive) {
            $candidate = rtrim(str_replace('\\', '/', $archive), '/') . '/' . $document;
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return $current;
    }

    private function extractLegacyContents(string $relativeFile): string
    {
        $file = $this->baseDir . '/' . ltrim(str_replace('\\', '/', $relativeFile), '/');
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Dokumentationsquelle fehlt.');
        }
        $extension = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
        $source = file_get_contents($file);
        if (!is_string($source) || $source === '') {
            throw new RuntimeException('Dokumentationsquelle ist leer.');
        }
        if ($extension === 'txt') {
            return '<div class="dbxdocs-cms-article"><pre class="fragment">'
                . htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre></div>';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $source, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        $body = $xpath->query('//body')->item(0);
        if (!$body instanceof DOMElement) {
            throw new RuntimeException('HTML-Inhaltsbereich wurde nicht gefunden.');
        }
        foreach ($xpath->query('.//script|.//style|.//form', $body) ?: array() as $unsafe) {
            $unsafe->parentNode?->removeChild($unsafe);
        }
        $this->normalizeEditorialHeadings($dom, $xpath, $body);
        $this->neutralizeInterpreterMarkers($dom, $xpath, $body);
        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return '<div class="dbxdocs-cms-article">' . trim($out) . '</div>';
    }

    /**
     * Der CMS-Seitentitel ist die einzige H1. Doxygen beginnt Markdown-
     * Unterkapitel technisch erneut bei H1; beim Import werden sie deshalb
     * um genau eine Ebene abgesenkt.
     */
    private function normalizeEditorialHeadings(
        DOMDocument $dom,
        DOMXPath $xpath,
        DOMElement $root
    ): void {
        $headings = array();
        foreach ($xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5', $root) ?: array() as $heading) {
            if ($heading instanceof DOMElement) {
                $headings[] = $heading;
            }
        }
        foreach ($headings as $heading) {
            $level = min(6, ((int)substr(strtolower($heading->tagName), 1)) + 1);
            $replacement = $dom->createElement('h' . $level);
            foreach ($heading->attributes as $attribute) {
                $replacement->setAttribute($attribute->nodeName, $attribute->nodeValue ?? '');
            }
            while ($heading->firstChild !== null) {
                $replacement->appendChild($heading->firstChild);
            }
            $heading->parentNode?->replaceChild($replacement, $heading);
        }
    }

    /**
     * Codebeispiele müssen im CMS inert bleiben. Ein sichtbares Zeichen wird
     * durch ein neutrales Span getrennt, damit `[modul]`, `[dbx:*]` und
     * Platzhalter nicht vom dbxInterpreter ausgeführt werden.
     */
    private function neutralizeInterpreterMarkers(
        DOMDocument $dom,
        DOMXPath $xpath,
        DOMElement $root
    ): void {
        $query = './/*[contains(concat(" ", normalize-space(@class), " "), " fragment ")'
            . ' or self::pre or self::code'
            . ' or contains(concat(" ", normalize-space(@class), " "), " tt ")]//text()';
        $nodes = $xpath->query($query, $root);
        if ($nodes === false) {
            return;
        }

        $textNodes = array();
        foreach ($nodes as $node) {
            $textNodes[] = $node;
        }
        foreach ($textNodes as $textNode) {
            $text = (string)$textNode->nodeValue;
            if (!str_contains($text, '[') && !str_contains($text, '{')) {
                continue;
            }
            $parts = preg_split('/([\[\{])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($parts === false ? array($text) : $parts as $part) {
                if ($part === '[' || $part === '{') {
                    $marker = $dom->createElement('span');
                    $marker->setAttribute('class', 'dbxdocs-code-punctuation');
                    $marker->appendChild($dom->createTextNode($part));
                    $textNode->parentNode?->insertBefore($marker, $textNode);
                } elseif ($part !== '') {
                    $textNode->parentNode?->insertBefore($dom->createTextNode($part), $textNode);
                }
            }
            $textNode->parentNode?->removeChild($textNode);
        }
    }

    private function rewriteReferenceLink(string $href): string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(?:https?:|mailto:|tel:|/)#i', $href)) {
            return $href;
        }

        $fragment = '';
        if (str_contains($href, '#')) {
            [$href, $fragment] = explode('#', $href, 2);
            $fragment = $fragment !== '' ? '#' . $fragment : '';
        }
        $path = parse_url($href, PHP_URL_PATH);
        $document = basename(is_string($path) ? $path : $href);
        if (!str_ends_with(strtolower($document), '.html')) {
            return $href . $fragment;
        }

        $mapped = $this->referenceDocumentPermalink($document);
        if ($mapped !== '') {
            return ltrim($mapped, '/') . $fragment;
        }

        return '?dbx_modul=dbxDocs&dbx_run1=reference&dbx_run2=page&doc='
            . rawurlencode($document)
            . $fragment;
    }

    private function referenceDocumentPermalink(string $document): string
    {
        $document = strtolower(basename(trim($document)));
        if ($document === '') {
            return '';
        }

        $mapped = $this->htmlToPermalink[$document] ?? '';
        if ($mapped !== '') {
            return $mapped;
        }

        if (preg_match('/^dbxcontent_tutorial_(.+)\.html$/', $document, $match) === 1) {
            $slug = str_replace('_', '-', (string)$match[1]);
            return str_starts_with($slug, 'tutorial-') || str_starts_with($slug, 'tutorials-')
                ? $slug
                : '';
        }
        return '';
    }
}
