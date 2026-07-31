<?php
namespace dbx\dbxDocs;

/**
 * Bindet die ausschließlich von Doxygen erzeugte technische Referenz in das
 * dbxapp-Dokumentationsportal ein.
 *
 * Redaktionelle Handbücher und Tutorials liegen im dbxContent-CMS. Dieses
 * Modul akzeptiert nur HTML-Dateien aus dem festen Doxygen-Ausgabeverzeichnis.
 */
class dbxDocs
{
    private const REFERENCE_PAGES = array(
        'overview' => 'index.html',
        'classes' => 'annotated.html',
        'namespaces' => 'namespaces.html',
        'files' => 'files.html',
        'examples' => 'examples.html',
    );

    private function language(): string
    {
        $language = strtolower(trim((string)dbx()->get_system_var('dbx_lng', 'de')));
        return in_array($language, array('de', 'en', 'es'), true) ? $language : 'de';
    }

    private function labels(string $section): array
    {
        $labels = array(
            'de' => array(
                'overview' => 'Technische Referenz',
                'classes' => 'Klassen',
                'namespaces' => 'Namespaces',
                'files' => 'Dateien',
                'examples' => 'Beispiele',
                'page' => 'Technische Referenz',
                'subtitle' => 'Automatisch aus dem dbxapp-Quellcode erzeugt.',
                'open' => 'Referenz in einem neuen Fenster öffnen',
                'fallback' => 'Die Referenz kann nicht eingebettet angezeigt werden.',
            ),
            'en' => array(
                'overview' => 'Technical reference',
                'classes' => 'Classes',
                'namespaces' => 'Namespaces',
                'files' => 'Files',
                'examples' => 'Examples',
                'page' => 'Technical reference',
                'subtitle' => 'Generated automatically from the dbxapp source code.',
                'open' => 'Open reference in a new window',
                'fallback' => 'The reference cannot be displayed within this page.',
            ),
            'es' => array(
                'overview' => 'Referencia técnica',
                'classes' => 'Clases',
                'namespaces' => 'Espacios de nombres',
                'files' => 'Archivos',
                'examples' => 'Ejemplos',
                'page' => 'Referencia técnica',
                'subtitle' => 'Generada automáticamente a partir del código fuente de dbxapp.',
                'open' => 'Abrir la referencia en una ventana nueva',
                'fallback' => 'La referencia no se puede mostrar dentro de esta página.',
            ),
        );

        $languageLabels = $labels[$this->language()];
        return array(
            'title' => (string)($languageLabels[$section] ?? $languageLabels['page']),
            'subtitle' => (string)$languageLabels['subtitle'],
            'open' => (string)$languageLabels['open'],
            'fallback' => (string)$languageLabels['fallback'],
        );
    }

    private function requestedDocument(string $section): string
    {
        if (isset(self::REFERENCE_PAGES[$section])) {
            return self::REFERENCE_PAGES[$section];
        }

        if ($section !== 'page') {
            return self::REFERENCE_PAGES['overview'];
        }

        $document = trim((string)dbx()->get_modul_var('doc', '', 'parameter'));
        if (preg_match('/^[A-Za-z0-9_.-]+\.html$/', $document) !== 1) {
            return self::REFERENCE_PAGES['overview'];
        }

        $root = realpath(dbx()->os_path(dbx()->get_base_dir() . 'reference/current'));
        $file = realpath(dbx()->os_path(dbx()->get_base_dir() . 'reference/current/' . $document));
        if ($root === false || $file === false || !is_file($file)) {
            return self::REFERENCE_PAGES['overview'];
        }

        $rootPrefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $normalizedFile = str_replace('\\', '/', $file);
        return str_starts_with($normalizedFile, $rootPrefix)
            ? basename($normalizedFile)
            : self::REFERENCE_PAGES['overview'];
    }

    public function run($action = ''): string
    {
        $action = $action !== ''
            ? (string)$action
            : (string)dbx()->get_modul_var('dbx_run1', 'reference', 'parameter');
        if ($action !== 'reference') {
            return '';
        }

        $section = strtolower(trim((string)dbx()->get_modul_var('dbx_run2', 'overview', 'parameter')));
        if (!isset(self::REFERENCE_PAGES[$section]) && $section !== 'page') {
            $section = 'overview';
        }

        $document = $this->requestedDocument($section);
        $labels = $this->labels($section);
        $referenceUrl = dbx()->get_base_url() . 'reference/current/' . rawurlencode($document);

        dbx()->set_system_var('dbx_title', $labels['title']);
        return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxDocs|reference', array(
            'reference_title' => $labels['title'],
            'reference_subtitle' => $labels['subtitle'],
            'reference_url' => dbx()->esc($referenceUrl),
            'reference_open_label' => $labels['open'],
            'reference_fallback' => $labels['fallback'],
        ));
    }
}
