(function ($) {

    const LIB = "menu";
    const MOBILE_BREAKPOINT = 991.98;

    let _lastMobileState = null;

    /* =====================================================
     * HELPERS (UNVERÄNDERT)
     * ===================================================== */

    function parseQuery(url) {
        const query = {};
        const q = url.split('?')[1];
        if (!q) return query;

        q.split('&').forEach(part => {
            const [key, val] = part.split('=');
            if (key) query[key] = val || '';
        });

        return query;
    }

    function currentQuery() {
        return parseQuery(window.location.search);
    }

    function linkQuery(href) {
        return parseQuery(href);
    }

    function isQueryMatch(linkParams, currentParams) {
        if (!linkParams || !Object.keys(linkParams).length) {
            return false;
        }

        for (let key in linkParams) {
            if (currentParams[key] !== linkParams[key]) {
                return false;
            }
        }
        return true;
    }

    function cleanPath(pathname) {
        let path = String(pathname || '/').replace(/\/+$/, '');
        return path || '/';
    }

    function urlFromHref(href) {
        try {
            return new URL(href, window.location.href);
        } catch (e) {
            return null;
        }
    }

    function getHistory() {
        if (!window.dbx || typeof dbx.uiGet !== 'function') {
            return [];
        }

        const history = dbx.uiGet(LIB, 'history', 'items', []);
        return Array.isArray(history) ? history : [];
    }

    function setHistory(history) {
        if (!window.dbx || typeof dbx.uiSet !== 'function') {
            return;
        }

        dbx.uiSet(LIB, 'history', 'items', Array.isArray(history) ? history : []);
    }

    function linkScore(href, currentUrl, currentParams) {
        const linkUrl = urlFromHref(href);
        if (!linkUrl) return -1;

        if (linkUrl.origin !== currentUrl.origin) {
            return -1;
        }

        const linkParams = linkQuery(href);
        const hasQuery = Object.keys(linkParams).length > 0;
        const pathMatch = cleanPath(linkUrl.pathname) === cleanPath(currentUrl.pathname);

        if (hasQuery) {
            if (!pathMatch || !isQueryMatch(linkParams, currentParams)) {
                return -1;
            }

            return 1000 + Object.keys(linkParams).length;
        }

        if (pathMatch) {
            return 500;
        }

        return -1;
    }

    function isMobileViewport() {
        return window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT}px)`).matches;
    }

    function getMenuLabel(cfg, id) {

        const fallback = (typeof id === "string" && id.trim() !== "" && id !== "undef")
            ? id
            : "";

        if (!cfg || typeof cfg.label !== "string") {
            return fallback;
        }

        const label = cfg.label.trim();
        const lower = label.toLowerCase();

        if (label === "" || lower === "undefined" || lower === "null") {
            return fallback;
        }

        return label;
    }


    /* =====================================================
     * CORE LOG WRAPPER
     * ===================================================== */
    function log(...a) {
        if (window.dbx) dbx.log("[menu]", ...a);
    }

    /* =====================================================
     * MOBILE TOGGLE / RESPONSIVE STATE
     * ===================================================== */

    function ensureMobileToggle($menu, id, labelHtml) {

        const menuId = $menu.attr('id') || ('dbx-menu-' + id);
        const $parent = $menu.parent();

        let toggleClass = 'dbx-menu-toggle-main';

        if ($menu.hasClass('dbx-menu-admin')) {
            toggleClass = 'dbx-menu-toggle-admin';
        }

        $menu.attr('id', menuId);

        let $bar = $parent.children('.dbx-menu-mobile-bar').first();

        if (!$bar.length) {
            $bar = $('<div class="dbx-menu-mobile-bar"></div>');
            $parent.prepend($bar);
            log("mobile toggle bar created");
        }

        let $toggle = $bar.children('.dbx-menu-toggle[aria-controls="' + menuId + '"]').first();

        if ($toggle.length) {
            return;
        }

        const labelText = $('<div>').html(labelHtml).text().trim() || id;

        $toggle = $(`
            <button
                type="button"
                class="dbx-menu-toggle ${toggleClass}"
                aria-controls="${menuId}"
                aria-expanded="false"
                aria-label="Menü ${labelText} öffnen"
            >
                <i class="bi bi-list" aria-hidden="true"></i>
                <span class="dbx-menu-toggle-label">${labelHtml}</span>
            </button>
        `);

        $bar.append($toggle);

        log("mobile toggle created", menuId, labelText);
    }

    function closeMenu($menu) {

        if (!$menu || !$menu.length) return;

        $menu.removeClass('is-mobile-open');

        $menu.find('.dbx-menu-item.is-open')
            .removeClass('is-open');

        $menu.find('.dbx-menu-link[aria-expanded]')
            .attr('aria-expanded', 'false');

        const menuId = $menu.attr('id');
        if (!menuId) return;

        const $toggle = $('.dbx-menu-toggle[aria-controls="' + menuId + '"]');

        $toggle.attr('aria-expanded', 'false');

        $toggle.find('i.bi')
            .removeClass('bi-x')
            .addClass('bi-list');
    }

    function closeAllMenus() {
        $('.dbx-menu-root').each(function () {
            closeMenu($(this));
        });
    }

    /**
     * Oeffnet innerhalb des angegebenen Menuebereichs alle Eltern des
     * aktuell aktiven Links. Die URL-Aktivierung markiert diese Elemente mit
     * is-active-path; beim erneuten Oeffnen des Menues muss der Benutzer den
     * Pfad dadurch nicht noch einmal Ebene fuer Ebene aufklappen.
     */
    function restoreActivePath($scope) {

        if (!$scope || !$scope.length) return;

        $scope
            .find('.dbx-menu-item.has-children.is-active-path')
            .addBack('.dbx-menu-item.has-children.is-active-path')
            .each(function () {

                const $item = $(this);

                $item.addClass('is-open');
                $item.children('.dbx-menu-link[aria-expanded]')
                    .attr('aria-expanded', 'true');
            });
    }

    function syncResponsiveState(force) {

        const mobile = isMobileViewport();

        if (!force && _lastMobileState === mobile) {
            return;
        }

        _lastMobileState = mobile;

        closeAllMenus();

        log("responsive sync", mobile ? "mobile" : "desktop");
    }

    /* =====================================================
     * MENU BUILD
     * ===================================================== */

    function buildMenu($menu) {

        $menu.addClass('dbx-menu-root');

        if ($menu.is('ul')) {
            $menu.addClass('dbx-menu-list');
        }

        $menu.find('ul').addClass('dbx-menu-list');

        $menu.find('li').each(function () {

            const $li = $(this);
            const $a  = $li.children('a');

            $li.addClass('dbx-menu-item');

            if ($li.hasClass('align-right') || $li.data('align') === 'right') {
                $li.addClass('dbx-menu-right');
            }

            if ($a.length) {
                $a.addClass('dbx-menu-link');
            }

            if ($li.children('ul').length) {

                $li.addClass('has-children');

                if ($a.length) {

                    $a.attr('data-role', 'toggle');
                    $a.attr('aria-haspopup', 'true');
                    $a.attr('aria-expanded', 'false');
                    $a.attr('role', 'button');

                    if ($a.attr('href') === '#') {
                        $a.removeAttr('href');
                    }
                    if (!$a.attr('href')) {
                        $a.attr('tabindex', '0');
                    }
                }

                if ($a.length && !$a.find('.dbx-caret').length) {
                    $('<span class="dbx-caret"></span>').appendTo($a);
                }
            }
        });
    }

    /* =====================================================
     * ACTIVE STATE
     * ===================================================== */

    function activateByUrl(root) {

        const currentParams = currentQuery();
        const currentUrl = new URL(window.location.href);

        let bestMatch = null;
        let bestScore = -1;

        $(root).find('.dbx-menu-link[href]').each(function () {

            const href = $(this).attr('href');
            if (!href || href === '#') return;

            const score = linkScore(href, currentUrl, currentParams);

            if (score > bestScore) {
                bestMatch = $(this);
                bestScore = score;
            }
        });

        if (!bestMatch) return;

        const $item = bestMatch.closest('.dbx-menu-item');

        $(root).find('.dbx-menu-item')
            .removeClass('is-active is-active-path is-open');

        $(root).find('.dbx-menu-link[aria-expanded]')
            .attr('aria-expanded', 'false');

        $item.addClass('is-active is-active-path');

        $item.parents('.dbx-menu-item').each(function () {

            const $parent = $(this);

            $parent.addClass('is-active is-active-path');

            const $link = $parent.children('.dbx-menu-link');
            if ($link.length) {
                $parent.addClass('is-active');
            }
        });

        storeHistory(bestMatch);
    }

    /* =====================================================
     * HISTORY (UNVERÄNDERT)
     * ===================================================== */

    function storeHistory($link) {

        const text = $link.text().trim();
        const href = $link.attr('href');

        if (!href || href === '#') return;

        let history = getHistory();

        history = history.filter(item => item.href !== href);

        history.unshift({ text, href });

        history = history.slice(0, 20);

        setHistory(history);
    }

    function buildChronic() {

        $('.chronic[data-dbx*="menu|chronic"]').each(function () {

            const $el = $(this);

            const deepMatch = $el.attr('data-dbx').match(/deep=(\d+)/);
            const deep = deepMatch ? parseInt(deepMatch[1]) : 10;

            let history = getHistory();

            history = history.slice(0, deep);

            const html = history.map(item =>
                `<a href="${item.href}" class="chronic-item">${item.text}</a>`
            ).join(' <span class="chronic-sep">›</span> ');

            $el.html(html);
        });
    }

    /* =====================================================
     * EVENTS → CORE DELEGATION
     * ===================================================== */

    function bindEvents() {

        if (bindEvents._bound) return;
        bindEvents._bound = true;

        dbx.on(
            'click',
            '.dbx-menu-item.has-children > .dbx-menu-link',
            function (e, el) {

                e.preventDefault();
                e.stopPropagation();

                const $link       = $(el);
                const $item       = $link.parent();
                const $parentList = $item.parent();
                const willOpen    = !$item.hasClass('is-open');

                $parentList.children('.dbx-menu-item.is-open').not($item).each(function () {
                    $(this)
                        .removeClass('is-open')
                        .children('.dbx-menu-link')
                        .attr('aria-expanded', 'false');
                });

                $item.toggleClass('is-open', willOpen);
                $link.attr('aria-expanded', willOpen ? 'true' : 'false');

                if (willOpen) {
                    restoreActivePath($item);
                }

                log("toggle item", willOpen ? "open" : "close");
            }
        );

        dbx.on(
            'keydown',
            '.dbx-menu-item.has-children > .dbx-menu-link',
            function (e, el) {
                const key = e.key || e.which;
                if (key !== 'Enter' && key !== ' ' && key !== 'Spacebar' && key !== 13 && key !== 32) {
                    return;
                }

                e.preventDefault();
                el.click();
            }
        );

        dbx.on(
            'click',
            '.dbx-menu-toggle',
            function (e, el) {

                e.preventDefault();
                e.stopPropagation();

                const $toggle = $(el);
                const menuId  = $toggle.attr('aria-controls');
                const $menu   = $('#' + menuId);

                if (!$menu.length) return;

                const willOpen = !$menu.hasClass('is-mobile-open');

                if (!willOpen) {
                    closeMenu($menu);
                } else {
                    $menu.addClass('is-mobile-open');
                    $toggle.attr('aria-expanded', 'true');
                    $toggle.find('i.bi')
                        .removeClass('bi-list')
                        .addClass('bi-x');
                    restoreActivePath($menu);
                }

                log("toggle mobile", menuId, willOpen ? "open" : "close");
            }
        );

        dbx.on('click', 'body', function (e) {

            if (!e.target.closest('.dbx-menu-root') && !e.target.closest('.dbx-menu-toggle')) {
                closeAllMenus();
            }
        });

        if (!bindEvents._resizeBound) {
            bindEvents._resizeBound = true;

            window.addEventListener('resize', function () {
                syncResponsiveState(false);
            });
        }

        log("events bound");
    }

    /* =====================================================
     * FEATURE INIT
     * ===================================================== */

    dbx.feature.register(LIB, {

        scope: "element",
        priority: "veryfirst",

        css: [
            ["css", "design", "m-menu.css"],
            ["css", "design", "c-menu.css"]
        ],

        init(el, cfg) {

            const id = dbx.getLibId(cfg);
            const $el = $(el);

            if (!id || id === "undef") {
                dbx.warn("menu → missing id");
                return;
            }

            const label = getMenuLabel(cfg, id);

            el.__dbxInitialized = el.__dbxInitialized || {};
            el.__dbxInitialized[LIB] = true;

            log("init", id);

            buildMenu($el);
            ensureMobileToggle($el, id, label);

            $el.find('.dbx-menu-item').removeClass('is-open');
            $el.find('.dbx-menu-link[aria-expanded]').attr('aria-expanded', 'false');
            $el.removeClass('is-mobile-open');

            activateByUrl($el);
            buildChronic();

            bindEvents();
            syncResponsiveState(true);

            el.style.visibility = 'visible';
        }

    });

})(jQuery);
