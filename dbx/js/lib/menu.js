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

    /**
     * Linke/vertikale Menues sind dauerhafte Seitennavigationen. Neben den
     * semantischen aside-/Sidebar-Markierungen dient die tatsaechliche
     * Geometrie als Fallback fuer Designs mit einem normalen header-Element.
     */
    function isPersistentSideMenu($menu) {

        if (!$menu || !$menu.length) return false;

        const configured = $menu.attr('data-dbx-menu-active-open');
        if (configured === '1') return true;
        if (configured === '0') return false;

        const element = $menu[0];
        if (element.closest('aside, [data-dbx-menu-position="left"], .dbx-sidebar, .fleurop-sidebar, .dbxdocs-sidebar')) {
            return true;
        }

        const container = element.closest('#dbxHeader');
        if (!container || !window.innerWidth || !window.innerHeight) return false;

        const rect = container.getBoundingClientRect();
        const leftAligned = rect.left <= Math.max(8, window.innerWidth * 0.02);
        const narrow = rect.width > 0 && rect.width <= Math.min(440, window.innerWidth * 0.45);
        const tall = rect.height >= Math.min(480, window.innerHeight * 0.6)
            && rect.height > rect.width;

        return leftAligned && narrow && tall;
    }

    function sideMenuStateId($menu) {
        const menuId = String($menu.attr('id') || 'menu').replace(/[^a-z0-9_-]/gi, '');
        const design = String(document.body && document.body.getAttribute('data-dbx-design') || 'default')
            .replace(/[^a-z0-9_-]/gi, '');
        const language = String(document.documentElement.getAttribute('lang') || 'default')
            .replace(/[^a-z0-9_-]/gi, '');

        return ['side-open', menuId || 'menu', design || 'default', language || 'default'].join(':');
    }

    function branchPath($item, $menu) {
        const parts = [];
        let item = $item && $item[0];
        const root = $menu && $menu[0];

        while (item && root && item !== root) {
            const list = item.parentElement;
            if (!list) break;
            const siblings = Array.from(list.children).filter(child => child.classList.contains('dbx-menu-item'));
            const index = siblings.indexOf(item);
            if (index < 0) break;
            parts.unshift(index);
            item = list.closest('.dbx-menu-item');
        }

        return parts.join('.');
    }

    function branchByPath($menu, path) {
        const indices = String(path || '').split('.').map(value => parseInt(value, 10));
        if (!indices.length || indices.some(index => !Number.isInteger(index) || index < 0)) {
            return $();
        }

        let list = $menu[0];
        if (list && !list.classList.contains('dbx-menu-list')) {
            list = Array.from(list.children).find(child => child.classList.contains('dbx-menu-list')) || null;
        }
        if (!list) return $();
        let item = null;
        for (let depth = 0; depth < indices.length; depth += 1) {
            const items = Array.from(list.children).filter(child => child.classList.contains('dbx-menu-item'));
            item = items[indices[depth]] || null;
            if (!item) return $();
            if (depth < indices.length - 1) {
                list = Array.from(item.children).find(child => child.classList.contains('dbx-menu-list')) || null;
                if (!list) return $();
            }
        }

        return item ? $(item) : $();
    }

    function storeOpenBranches($menu) {
        if (!isPersistentSideMenu($menu) || !window.dbx || typeof dbx.uiSet !== 'function') return;

        const paths = [];
        $menu.find('.dbx-menu-item.has-children.is-open').each(function () {
            const path = branchPath($(this), $menu);
            if (path !== '' && paths.indexOf(path) === -1) paths.push(path);
        });

        dbx.uiSet(LIB, sideMenuStateId($menu), 'branches', paths);
    }

    function restoreStoredOpenBranches($menu) {
        if (!isPersistentSideMenu($menu) || !window.dbx || typeof dbx.uiGet !== 'function') return;

        const paths = dbx.uiGet(LIB, sideMenuStateId($menu), 'branches', []);
        if (!Array.isArray(paths)) return;

        paths.forEach(function (path) {
            const $item = branchByPath($menu, path);
            if (!$item.length || !$item.hasClass('has-children')) return;
            $item.addClass('is-open');
            $item.children('.dbx-menu-link[aria-expanded]').attr('aria-expanded', 'true');
        });
    }

    function closeAllMenus(preservePersistent) {
        $('.dbx-menu-root').each(function () {
            const $menu = $(this);

            if (preservePersistent === true && isPersistentSideMenu($menu)) {
                return;
            }

            closeMenu($menu);
        });
    }

    /**
     * Schliesst einen kompletten Menuezweig. Auch verdeckte Unterebenen
     * verlieren ihren Oeffnungszustand, damit sie spaeter nicht unerwartet
     * wieder erscheinen.
     */
    function closeBranch($item) {

        if (!$item || !$item.length) return;

        const $branchItems = $item.add($item.find('.dbx-menu-item'));

        $branchItems.removeClass('is-open');
        $branchItems
            .children('.dbx-menu-link[aria-expanded]')
            .attr('aria-expanded', 'false');
    }

    /**
     * Linke Navigationen arbeiten als Akkordeon: Beim Oeffnen eines Ordners
     * bleiben nur dieser Ordner und seine erforderlichen Eltern geoeffnet.
     */
    function closeOtherSideBranches($menu, $item) {

        const keep = new Set([$item[0]]);
        $item.parents('.dbx-menu-item.has-children').each(function () {
            if ($menu[0].contains(this)) keep.add(this);
        });

        $menu.find('.dbx-menu-item.has-children.is-open').each(function () {
            if (!keep.has(this)) closeBranch($(this));
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

        $('.dbx-menu-root').each(function () {
            const $menu = $(this);
            if (!isPersistentSideMenu($menu)) return;
            restoreActivePath($menu);
            restoreStoredOpenBranches($menu);
        });

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

            const $link = $(this);
            const href = $link.attr('href');
            if (!href || href === '#') return;

            /*
             * Sprach- und Designoptionen ändern nur die Darstellung der
             * aktuellen Route. Sie dürfen deshalb nicht den eigentlichen
             * Seitenlink als aktiven Menüpunkt verdrängen.
             */
            if ($link.is('.dbxLngOpt, .dbx-design-opt, .dbx-design-skin-opt, .dbx-skin-opt')) {
                return;
            }

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

            /*
             * Nur die aufgerufene Seite ist aktiv. Eltern markieren lediglich
             * den Pfad und bleiben dadurch von der aktiven Seite unterscheidbar.
             */
            $parent.addClass('is-active-path');
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
                const $menu       = $link.closest('.dbx-menu-root');
                const willOpen    = !$item.hasClass('is-open');

                if (willOpen) {
                    if (isPersistentSideMenu($menu)) {
                        closeOtherSideBranches($menu, $item);
                    } else {
                        $parentList.children('.dbx-menu-item.is-open').not($item).each(function () {
                            closeBranch($(this));
                        });
                    }
                    $item.addClass('is-open');
                    $link.attr('aria-expanded', 'true');
                    restoreActivePath($item);
                } else {
                    closeBranch($item);
                }

                storeOpenBranches($menu);

                log("toggle item", willOpen ? "open" : "close");
            }
        );

        dbx.on(
            'click',
            '.dbx-menu-item:not(.has-children) > .dbx-menu-link[href]',
            function (e, el) {
                storeOpenBranches($(el).closest('.dbx-menu-root'));
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
                closeAllMenus(true);
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
            if (isPersistentSideMenu($el)) {
                $el.attr('data-dbx-menu-persist-open', '1');
                restoreActivePath($el);
                restoreStoredOpenBranches($el);
            }

            el.style.visibility = 'visible';
        }

    });

})(jQuery);
