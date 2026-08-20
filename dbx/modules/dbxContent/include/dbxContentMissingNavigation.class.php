<?php

declare(strict_types=1);

namespace dbx\dbxContent;

/** Zentrale sprachabhängige Hinweisseite für unbekannte Permalinks. */
final class dbxContentMissingNavigation
{
    /** @var array<string,string> */
    private const ROUTES = array(
        'de' => 'seite-nicht-gefunden',
        'en' => 'page-not-found',
        'es' => 'pagina-no-encontrada',
    );

    public function language_for_route(string $permalink): string
    {
        $route = strtolower(trim(str_replace('\\', '/', $permalink), '/'));
        foreach (self::ROUTES as $language => $candidate) {
            if ($route === $candidate) {
                return $language;
            }
        }
        return '';
    }

    public function route_for_language(string $language): string
    {
        $language = strtolower(trim($language));
        return self::ROUTES[$language] ?? self::ROUTES['de'];
    }

    public function redirect_target(string $permalink, string $language): string
    {
        $permalink = trim(str_replace('\\', '/', $permalink), '/');
        if ($permalink === '') {
            return '';
        }

        $language = strtolower(trim($language));
        if (!array_key_exists($language, self::ROUTES)) {
            $language = 'de';
        }

        $base = rtrim((string)dbx()->get_base_url(), '/') . '/';
        $default_language = strtolower(trim((string)dbx()->get_cfg(
            'dbx',
            'default_lng',
            'de'
        )));
        $language_prefix = (int)dbx()->get_cfg(
            'dbx',
            'language_path_prefix',
            0
        ) === 1 && $language !== $default_language
            ? rawurlencode($language) . '/'
            : '';

        return $base
            . $language_prefix
            . $this->route_for_language($language)
            . '?from=' . rawurlencode(substr($permalink, 0, 254))
            . '&dbx_lng=' . rawurlencode($language);
    }

    public function render(): string
    {
        $language = strtolower(trim((string)dbx()->get_system_var(
            'dbx_lng',
            'de'
        )));
        if (!array_key_exists($language, self::ROUTES)) {
            $language = 'de';
        }

        $requested = trim((string)dbx()->get_request_var(
            'from',
            '',
            'parameter|max=254'
        ), '/');
        $tpl = dbx()->get_system_obj('dbxTPL');
        $pages = dbxContentSitemap::navigation_pages($language);
        $rows = '';
        foreach ($pages as $page) {
            $rows .= $tpl->get_tpl('dbxContent|missing-navigation-row', array(
                'url' => dbx()->esc((string)($page['url'] ?? '')),
                'title' => dbx()->esc((string)($page['title'] ?? '')),
                'permalink' => dbx()->esc((string)($page['permalink'] ?? '')),
            ));
        }

        $titles = array(
            'de' => 'Seite nicht gefunden',
            'en' => 'Page not found',
            'es' => 'Página no encontrada',
        );
        dbx()->set_system_var('dbx_title', $titles[$language]);
        dbx()->set_system_var('dbx_robots', 'noindex,follow');

        return $tpl->get_tpl('dbxContent|missing-navigation', array(
            'requested_permalink' => dbx()->esc(
                $requested !== '' ? '/' . $requested : '/'
            ),
            'home_url' => dbx()->esc(rtrim((string)dbx()->get_base_url(), '/') . '/'),
            'sitemap_url' => dbx()->esc(
                rtrim((string)dbx()->get_base_url(), '/') . '/sitemap.xml'
            ),
            'page_rows' => $rows,
            'page_count' => count($pages),
        ));
    }
}
