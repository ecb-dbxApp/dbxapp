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
                'window' => 'In dbx-Fenster öffnen',
                'tab' => 'In neuem Tab öffnen',
                'back' => 'Zur Portalansicht',
                'actions' => 'Doxygen-Darstellung öffnen',
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
                'window' => 'Open in dbx window',
                'tab' => 'Open in new tab',
                'back' => 'Back to portal view',
                'actions' => 'Open Doxygen view',
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
                'window' => 'Abrir en ventana dbx',
                'tab' => 'Abrir en una pestaña nueva',
                'back' => 'Volver a la vista del portal',
                'actions' => 'Abrir la vista de Doxygen',
                'fallback' => 'La referencia no se puede mostrar dentro de esta página.',
            ),
        );

        $languageLabels = $labels[$this->language()];
        return array(
            'title' => (string)($languageLabels[$section] ?? $languageLabels['page']),
            'subtitle' => (string)$languageLabels['subtitle'],
            'window' => (string)$languageLabels['window'],
            'tab' => (string)$languageLabels['tab'],
            'back' => (string)$languageLabels['back'],
            'actions' => (string)$languageLabels['actions'],
            'fallback' => (string)$languageLabels['fallback'],
        );
    }

    private function requestedDocument(string $section): string
    {
        if (isset(self::REFERENCE_PAGES[$section])) {
            return self::REFERENCE_PAGES[$section];
        }

        if (!in_array($section, array('page', 'window'), true)) {
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

    private function search(): string
    {
        $query = trim((string)dbx()->get_modul_var('q', '', 'text|max=120'));
        $labels = array(
            'de' => array(
                'title' => 'Dokumentation durchsuchen',
                'subtitle' => 'Handbücher, Tutorials und technische Einstiege aus einer Suche.',
                'placeholder' => 'Aufgabe, Modul oder Fehlermeldung suchen …',
                'button' => 'Suchen',
                'empty' => 'Geben Sie mindestens zwei Zeichen ein.',
                'none' => 'Keine passende Seite gefunden.',
                'results' => 'Treffer',
                'reference' => 'Zusätzlich in der API-Referenz suchen',
            ),
            'en' => array(
                'title' => 'Search documentation',
                'subtitle' => 'Manuals, tutorials and technical entry points in one search.',
                'placeholder' => 'Search for a task, module or error message …',
                'button' => 'Search',
                'empty' => 'Enter at least two characters.',
                'none' => 'No matching page found.',
                'results' => 'Results',
                'reference' => 'Also search the API reference',
            ),
            'es' => array(
                'title' => 'Buscar en la documentación',
                'subtitle' => 'Manuales, tutoriales y entradas técnicas en una sola búsqueda.',
                'placeholder' => 'Buscar tarea, módulo o mensaje de error …',
                'button' => 'Buscar',
                'empty' => 'Introduzca al menos dos caracteres.',
                'none' => 'No se encontró ninguna página adecuada.',
                'results' => 'Resultados',
                'reference' => 'Buscar también en la referencia API',
            ),
        );
        $text = $labels[$this->language()];
        // Redaktionelle Hilfen beantworten in der Regel die eigentliche Aufgabe.
        // Die technische Referenz ergaenzt sie, darf sie aber nicht aus den
        // sichtbaren Treffern verdraengen (z. B. bei "dbxForm").
        $pageResults = mb_strlen($query) >= 2 ? $this->searchPages($query) : array();
        $apiResults = mb_strlen($query) >= 2 ? $this->searchApiPages($query) : array();
        $results = array_merge(
            array_slice($pageResults, 0, 24),
            array_slice($apiResults, 0, 16)
        );
        $items = '';
        foreach ($results as $result) {
            $items .= dbx()->get_system_obj('dbxTPL')->get_tpl('dbxDocs|search-result', array(
                'result_url' => dbx()->esc((string)$result['permalink']),
                'result_title' => dbx()->esc((string)$result['title']),
                'result_type' => dbx()->esc((string)$result['type']),
                'result_excerpt' => dbx()->esc((string)$result['excerpt']),
            ));
        }
        if ($items === '') {
            $message = mb_strlen($query) < 2 ? $text['empty'] : $text['none'];
            $items = '<p class="dbxdocs-search-empty"><i class="bi bi-search" aria-hidden="true"></i> '
                . dbx()->esc($message) . '</p>';
        }

        dbx()->set_system_var('dbx_title', $text['title']);
        return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxDocs|search', array(
            'search_title' => $text['title'],
            'search_subtitle' => $text['subtitle'],
            'search_placeholder' => $text['placeholder'],
            'search_button' => $text['button'],
            'search_query' => dbx()->esc($query),
            'search_result_label' => $text['results'],
            'search_result_count' => (string)count($results),
            'search_results' => $items,
            'search_reference_label' => $text['reference'],
        ));
    }

    private function searchPages(string $query): array
    {
        $language = $this->language();
        $dd = 'dbx|content_' . $language;
        $rows = dbx()->get_system_obj('dbxDB')->select(
            $dd,
            '',
            'id,title,menu_title,seo_title,permalink,description,keywords,content,activ,group_read',
            'id',
            'ASC',
            '',
            0,
            0,
            0
        );
        $needle = mb_strtolower($query);
        $results = array();
        foreach (is_array($rows) ? $rows : array() as $row) {
            $permalink = trim((string)($row['permalink'] ?? ''));
            if (
                (int)($row['activ'] ?? 0) !== 1
                || trim((string)($row['group_read'] ?? '*')) !== '*'
                || (!str_starts_with($permalink, 'dokumentation-') && !str_starts_with($permalink, 'tutorial-'))
            ) {
                continue;
            }
            $title = trim((string)($row['seo_title'] ?? ''));
            if ($title === '') {
                $title = trim((string)($row['title'] ?? $row['menu_title'] ?? $permalink));
            }
            $description = trim((string)($row['description'] ?? ''));
            $plainContent = html_entity_decode(strip_tags((string)($row['content'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plainContent = trim((string)preg_replace('/\s+/u', ' ', $plainContent));
            $fields = array(
                12 => $title,
                8 => (string)($row['menu_title'] ?? ''),
                6 => $description,
                4 => (string)($row['keywords'] ?? ''),
                1 => $plainContent,
            );
            $score = 0;
            foreach ($fields as $weight => $value) {
                if (mb_stripos((string)$value, $needle) !== false) {
                    $score += (int)$weight;
                }
            }
            if ($score <= 0) {
                continue;
            }
            $excerpt = $description !== '' ? $description : $plainContent;
            if (mb_strlen($excerpt) > 230) {
                $position = mb_stripos($excerpt, $needle);
                $start = max(0, (int)$position - 70);
                $excerpt = ($start > 0 ? '…' : '') . mb_substr($excerpt, $start, 220) . '…';
            }
            $results[] = array(
                'score' => $score,
                'title' => $title,
                'permalink' => $permalink,
                'type' => str_starts_with($permalink, 'tutorial-') ? 'Tutorial' : 'Handbuch',
                'excerpt' => $excerpt,
            );
        }
        usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($results, 0, 40);
    }

    /** Ergänzt die CMS-Treffer um Klassen, Methoden und Dateien aus Doxygen. */
    private function searchApiPages(string $query): array
    {
        $path = __DIR__ . '/assets/api-search-index.json';
        $raw = is_file($path) ? file_get_contents($path) : false;
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        $entries = is_array($payload) && is_array($payload['entries'] ?? null)
            ? $payload['entries']
            : array();
        $tokens = preg_split('/[^\p{L}\p{N}_-]+/u', mb_strtolower($query)) ?: array();
        $tokens = array_values(array_filter($tokens, static fn(string $token): bool => mb_strlen($token) >= 2));
        $results = array();
        foreach ($entries as $entry) {
            $title = trim((string)($entry['title'] ?? ''));
            $context = trim((string)($entry['context'] ?? ''));
            $url = trim((string)($entry['url'] ?? ''));
            if ($title === '' || $url === '') {
                continue;
            }
            $haystack = mb_strtolower($title . ' ' . $context . ' ' . (string)($entry['kind_label'] ?? ''));
            $matches = true;
            foreach ($tokens as $token) {
                if (!str_contains($haystack, $token)) {
                    $matches = false;
                    break;
                }
            }
            if (!$matches) {
                continue;
            }
            $score = (int)($entry['weight'] ?? 0)
                + (str_contains(mb_strtolower($title), mb_strtolower($query)) ? 80 : 0);
            $results[] = array(
                'score' => $score,
                'title' => $title,
                'permalink' => dbx()->get_base_url() . 'reference/current/' . $url,
                'type' => trim((string)($entry['kind_label'] ?? 'API')) ?: 'API',
                'excerpt' => $context !== '' ? $context : 'Technische Referenz aus dem aktuellen dbxapp-Quellcode.',
            );
        }
        return array_slice($results, 0, 40);
    }

    private function notFound(): string
    {
        $resource = trim((string)dbx()->get_modul_var('resource', '', 'text|max=180'));
        $titles = array(
            'de' => 'Dokument nicht gefunden',
            'en' => 'Document not found',
            'es' => 'Documento no encontrado',
        );
        http_response_code(404);
        dbx()->set_system_var('dbx_title', $titles[$this->language()]);
        return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxDocs|not-found', array(
            'missing_resource' => dbx()->esc($resource),
        ));
    }

    public function run($action = ''): string
    {
        $action = $action !== ''
            ? (string)$action
            : (string)dbx()->get_modul_var('dbx_run1', 'reference', 'parameter');
        if ($action === 'search') {
            return $this->search();
        }
        if ($action === 'not_found') {
            return $this->notFound();
        }
        if ($action !== 'reference') {
            return '';
        }

        $section = strtolower(trim((string)dbx()->get_modul_var('dbx_run2', 'overview', 'parameter')));
        if (!isset(self::REFERENCE_PAGES[$section]) && !in_array($section, array('page', 'window'), true)) {
            $section = 'overview';
        }

        $document = $this->requestedDocument($section);
        $labelSection = array_search($document, self::REFERENCE_PAGES, true);
        $labels = $this->labels(is_string($labelSection) ? $labelSection : 'page');
        $referenceUrl = dbx()->get_base_url() . 'reference/current/' . rawurlencode($document);
        $windowRoute = '?dbx_modul=dbxDocs&dbx_run1=reference&dbx_run2=window&doc=' . rawurlencode($document);
        $dialogRoute = $windowRoute . '&dbx_window=1';
        $portalRoute = '?dbx_modul=dbxDocs&dbx_run1=reference&dbx_run2=overview';

        dbx()->set_system_var('dbx_title', $labels['title']);
        $template = $section === 'window' ? 'dbxDocs|reference-window' : 'dbxDocs|reference';
        return dbx()->get_system_obj('dbxTPL')->get_tpl($template, array(
            'reference_title' => $labels['title'],
            'reference_subtitle' => $labels['subtitle'],
            'reference_url' => dbx()->esc($referenceUrl),
            'reference_window_url' => dbx()->esc($dialogRoute),
            'reference_tab_url' => dbx()->esc($windowRoute),
            'reference_portal_url' => dbx()->esc($portalRoute),
            'reference_window_label' => $labels['window'],
            'reference_tab_label' => $labels['tab'],
            'reference_back_label' => $labels['back'],
            'reference_actions_label' => $labels['actions'],
            'reference_fallback' => $labels['fallback'],
        ));
    }
}
