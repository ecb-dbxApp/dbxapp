/*!
 * dbxapp runtime.js
 * Laufzeitdiagnose, Events und Geräteabstraktion des Kernels.
 */
(function (window, document) {
    "use strict";
    const dbx = window.dbx;
    if (!dbx) throw new Error("dbx core missing before runtime.js");
    if (dbx.__runtimeLoaded === true) return;
    dbx.__runtimeLoaded = true;

    /* =====================================================
     * FOOTER STATUS
     * ===================================================== */
    dbx.footerStatus = dbx.footerStatus || (function () {

        function formatSeconds(seconds) {
            seconds = Math.max(0, Number(seconds) || 0);
            return seconds.toFixed(3);
        }

        function formatPhpSeconds(seconds) {
            seconds = Math.max(0, Number(seconds) || 0);
            return Math.max(0.001, seconds).toFixed(3);
        }

        function runtimeElement() {
            return document.querySelector("[data-dbx-runtime]");
        }

        function responseRuntimeSeconds() {
            const perf = window.performance;
            const nav = perf && perf.getEntriesByType && perf.getEntriesByType("navigation")[0];
            const domReadyEnd = nav ? Number(nav.domContentLoadedEventEnd) : NaN;

            // navigation.duration endet erst nach dem load-Event. Langsame Bilder,
            // Fonts oder andere Subressourcen ließen die Anzeige deshalb noch
            // weiterlaufen, obwohl DOM und JavaScript bereits einsatzbereit waren.
            if (Number.isFinite(domReadyEnd) && domReadyEnd > 0) {
                return domReadyEnd / 1000;
            }

            return perf && perf.now ? perf.now() / 1000 : 0;
        }

        function navigationPhpRuntimeSeconds() {
            const perf = window.performance;
            const nav = perf && perf.getEntriesByType && perf.getEntriesByType("navigation")[0];
            const entries = nav && nav.serverTiming ? Array.from(nav.serverTiming) : [];
            const timer = entries.find(item => item && item.name === "dbxphp");
            const duration = timer ? Number(timer.duration) : NaN;

            return Number.isFinite(duration) && duration >= 0 ? duration / 1000 : null;
        }

        function phpRuntimeSeconds(el) {
            const headerRuntime = navigationPhpRuntimeSeconds();
            if (headerRuntime !== null) {
                return headerRuntime;
            }

            const raw = el ? el.getAttribute("data-dbx-php-runtime") : "";
            const value = Number(raw);

            return Number.isFinite(value) && value >= 0 ? value : null;
        }

        function write(responseSeconds, phpSeconds, label) {
            const el = runtimeElement();
            if (!el) return;

            const responseLabel = formatSeconds(responseSeconds);
            const hasPhp = phpSeconds != null && phpSeconds !== ""
                && Number.isFinite(Number(phpSeconds)) && Number(phpSeconds) >= 0;
            const phpValue = hasPhp ? Number(phpSeconds) : null;
            const phpLabel = hasPhp ? "/" + formatPhpSeconds(phpValue) : "";
            const text = responseLabel + phpLabel + " sec";

            el.textContent = text;

            if (hasPhp) {
                el.setAttribute("data-dbx-php-runtime", formatPhpSeconds(phpValue));
            }

            const title = hasPhp
                ? label + ": " + responseLabel + " / " + formatPhpSeconds(phpValue) + " sec"
                : label + ": " + responseLabel + " sec";

            el.setAttribute("data-dbx-tooltip", title);
            el.setAttribute("data-dbx-page-runtime-title", title);
        }

        function init() {
            const el = runtimeElement();
            if (!el) return;

            const update = function () {
                write(
                    responseRuntimeSeconds(),
                    phpRuntimeSeconds(el),
                    dbx.translate({
                        de: "DOM- und JavaScript-Bereitschaft / PHP-Laufzeit",
                        en: "DOM and JavaScript readiness / PHP runtime",
                        es: "Disponibilidad de DOM y JavaScript / tiempo de ejecución de PHP"
                    })
                );
            };

            if (document.readyState !== "loading") {
                update();
            } else {
                // Genau an derselben Grenze messen, die auch angezeigt wird.
                // Ein früher setTimeout-Wert und ein später load-Wert ließen die
                // Zahl sichtbar springen und rechneten langsame Bilder hinein.
                document.addEventListener("DOMContentLoaded", update, { once: true });
            }
        }


        function shouldTrackAjaxRuntime(url, body, options) {
            if (options && (
                options.skipRuntime === true ||
                options.footerRuntime === "hidden" ||
                options.footerRuntime === "skip" ||
                options.footerRuntime === "0"
            )) {
                return false;
            }

            if (options && String(options.mode || "").toLowerCase() === "json") {
                return false;
            }

            const targetUrl = String(url || "");
            if (/[?&]dbx_sync=0(?:&|$)/.test(targetUrl)) {
                return false;
            }

            if (body instanceof URLSearchParams && body.get("dbx_sync") === "0") {
                return false;
            }

            if (body instanceof FormData && body.get("dbx_sync") === "0") {
                return false;
            }

            if (typeof body === "string" && /(?:^|&)dbx_sync=0(?:&|$)/.test(body)) {
                return false;
            }

            return true;
        }

        return {
            init,
            formatSeconds,
            shouldTrackAjaxRuntime,
            updateAjax(responseSeconds, phpSeconds) {
                write(
                    responseSeconds,
                    phpSeconds,
                    dbx.translate({
                        de: "Letzte AJAX-Anfrage / PHP-Laufzeit",
                        en: "Latest AJAX request / PHP runtime",
                        es: "Última solicitud AJAX / tiempo de ejecución de PHP"
                    })
                );
            }
        };

    })();

    /* =====================================================
     * 🔥 EVENT DELEGATION HELPER
     * ===================================================== */
    dbx.on = function (event, selector, handler) {

        document.addEventListener(event, function (e) {

            const el = e.target.closest(selector);
            if (!el) return;

            handler.call(el, e, el);

        }, true); // 🔥 WICHTIG: CAPTURE PHASE

        dbx.log("delegation registered:", event, selector);
    };


    /* =====================================================
    * 🔥 EVENT SYSTEM (CUSTOM)
    * ===================================================== */

    dbx.event = dbx.event || {

        _events: {},

        on(name, handler) {
            if (!name || typeof handler !== "function") return;

            this._events[name] = this._events[name] || [];
            this._events[name].push(handler);

            dbx.log("event:on →", name);
        },

        emit(name, data) {
            if (!name) return;

            const list = this._events[name];
            if (!list || !list.length) return;

            dbx.log("event:emit →", name, data);

            list.forEach(fn => {
                try {
                    fn(data);
                } catch (e) {
                    dbx.error("event handler failed:", name, e);
                }
            });

            // 🔥 NEU: scoped event
            if (data && data.id) {
                const scoped = name + ":" + data.id;
                const list2 = this._events[scoped];

                if (list2) {
                    list2.forEach(fn => {
                        try {
                            fn(data);
                        } catch (e) {
                            dbx.error("event handler failed:", scoped, e);
                        }
                    });
                }
            }
        }

    };

   /* =====================================================
    * DEVICE (STATE-BASED ABSTRACTION)
    * ===================================================== */
    dbx.device = (function(){

        const state = {
            visible: true,
            online: true,
            active: true,
            lastActivity: Date.now()
        };

        const IDLE_MS = 30000; // 30s

        /* =====================================================
        * INTERNAL UPDATES
        * ===================================================== */

        function updateVisibility(){
            state.visible = !document.hidden;
        }

        function updateOnline(){
            state.online = navigator.onLine !== false;
        }

        function markActive(){
            state.lastActivity = Date.now();
            state.active = true;
        }

        function checkIdle(){
            const now = Date.now();
            state.active = (now - state.lastActivity) < IDLE_MS;
        }

        /* =====================================================
        * BIND EVENTS (BROWSER)
        * ===================================================== */

        document.addEventListener('visibilitychange', updateVisibility, true);
        window.addEventListener('online', updateOnline, true);
        window.addEventListener('offline', updateOnline, true);

        document.addEventListener('mousemove', markActive, true);
        document.addEventListener('keydown', markActive, true);
        document.addEventListener('click', markActive, true);

        // idle checker (leichtgewichtig)
        setInterval(checkIdle, 5000);

        // init
        updateVisibility();
        updateOnline();

        /* =====================================================
        * PUBLIC API
        * ===================================================== */

        return {

            /* ===== READ ===== */

            isVisible(){
                return state.visible === true;
            },

            isOnline(){
                return state.online === true;
            },

            isActive(){
                return state.active === true;
            },

            getState(){
                return Object.assign({}, state);
            },

            /* =====================================================
            * OPTIONAL: EXTERNAL OVERRIDE (FUTURE: APP / PWA)
            * ===================================================== */

            _set(partial){

                if(!partial || typeof partial !== 'object') return;

                if(typeof partial.visible === 'boolean'){
                    state.visible = partial.visible;
                }

                if(typeof partial.online === 'boolean'){
                    state.online = partial.online;
                }

                if(typeof partial.active === 'boolean'){
                    state.active = partial.active;
                }

                if(typeof partial.lastActivity === 'number'){
                    state.lastActivity = partial.lastActivity;
                }

                dbx.log('[device] override', partial);
            }

        };

    })();

    /* =====================================================
    * DEVICE CAPABILITIES (BRIDGE)
    * ===================================================== */

    dbx.device.camera = {

        async open(opts){

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                dbx.error('[device] camera not supported');
                throw new Error('camera_not_supported');
            }

            const constraints = opts?.constraints || { video: true };

            try {

                const stream = await navigator.mediaDevices.getUserMedia(constraints);

                return {
                    stream,
                    stop(){
                        stream.getTracks().forEach(t => t.stop());
                    }
                };

            } catch (e) {

                dbx.error('[device] camera error', e);
                throw e;
            }
        }

    };


    dbx.device.file = {

        async pick(opts){

            return new Promise((resolve, reject) => {

                const input = document.createElement('input');
                input.type = 'file';

                if (opts?.accept) input.accept = opts.accept;
                if (opts?.multiple) input.multiple = true;

                input.onchange = () => {

                    if(!input.files || !input.files.length){
                        resolve(null); // 🔥 FIX
                    } else {
                        resolve(input.files);
                    }
                };

                input.onerror = reject;

                input.click();
            });
        }

    };


    dbx.device.clipboard = {

        async write(text){

            if (!navigator.clipboard) {
                dbx.error('[device] clipboard not supported');
                throw new Error('clipboard_not_supported');
            }

            return navigator.clipboard.writeText(text);
        },

        async read(){

            if (!navigator.clipboard) {
                dbx.error('[device] clipboard not supported');
                throw new Error('clipboard_not_supported');
            }

            return navigator.clipboard.readText();
        }

    };


    dbx.device.share = {

        async open(data){

            if (!navigator.share) {
                dbx.error('[device] share not supported');
                throw new Error('share_not_supported');
            }

            return navigator.share(data);
        }

    };



})(window, document);
