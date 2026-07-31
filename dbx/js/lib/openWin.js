/**
 * openWin.js - Fenster-Manager Bibliothek
 * Version: 2.1.0
 * Core-unabhängiges CSS-Loading
 */

(function (window, document, $) {
    "use strict";

    if (!window.dbx || !$) {
        console.warn("openWin: dbx oder jQuery nicht verfügbar");
        return;
    }

    const dbx = window.dbx;
    const language = String(document.documentElement.lang || "de")
        .toLowerCase()
        .split("-")[0];
    const UI_TEXTS = {
        de: {
            window: "Fenster",
            windowBar: "Fensterleiste",
            minimizedWindows: "Minimierte Fenster",
            closeAll: "Alle Fenster schließen",
            noWindows: "Keine Fenster geöffnet",
            openWindow: "Fenster öffnen: {title}",
            reloadWindow: "Fenster neu laden",
            loading: "Laden...",
            loadError: "Fehler beim Laden",
            unknownError: "Unbekannter Fehler",
            ajaxMissing: "ajax.js wurde nicht geladen.",
            systemInfo: "Systeminformationen",
            runtime: "Laufzeit",
            runtimeTitle: "Laufzeit bis die Seite geladen ist",
            privacy: "Datenschutz",
            privacySlug: "datenschutz"
        },
        en: {
            window: "Window",
            windowBar: "Window bar",
            minimizedWindows: "Minimized windows",
            closeAll: "Close all windows",
            noWindows: "No windows open",
            openWindow: "Open window: {title}",
            reloadWindow: "Reload window",
            loading: "Loading...",
            loadError: "Error while loading",
            unknownError: "Unknown error",
            ajaxMissing: "ajax.js was not loaded.",
            systemInfo: "System information",
            runtime: "Runtime",
            runtimeTitle: "Time until the page was loaded",
            privacy: "Data protection",
            privacySlug: "data-protection"
        },
        es: {
            window: "Ventana",
            windowBar: "Barra de ventanas",
            minimizedWindows: "Ventanas minimizadas",
            closeAll: "Cerrar todas las ventanas",
            noWindows: "No hay ventanas abiertas",
            openWindow: "Abrir ventana: {title}",
            reloadWindow: "Volver a cargar la ventana",
            loading: "Cargando...",
            loadError: "Error al cargar",
            unknownError: "Error desconocido",
            ajaxMissing: "No se ha cargado ajax.js.",
            systemInfo: "Información del sistema",
            runtime: "Tiempo de ejecución",
            runtimeTitle: "Tiempo transcurrido hasta cargar la página",
            privacy: "Protección de datos",
            privacySlug: "protecci-on-de-datos"
        }
    };
    const ui = UI_TEXTS[language] || UI_TEXTS.de;
    const uiFormat = (text, values) => Object.keys(values || {}).reduce(
        (result, key) => result.replaceAll("{" + key + "}", String(values[key])),
        String(text || "")
    );


    // --------------------------------------------------
    // KONSTANTEN
    // --------------------------------------------------

    const CONFIG = {
        // Z-Index Basiswerte: normale openWin-Fenster bleiben hoch,
        // echte Werkzeug-/Optionsdialoge bekommen bei Bedarf die Top-Ebene.
        BASE_Z_DRAGGABLE: 70000,
        BASE_Z_MODAL: 80000,
        BASE_Z_TOP: 230000,
        Z_INCREMENT: 20,
        ANIMATION_DURATION: 200,
        MAX_WINDOWS: 15,
        DEFAULT_WIDTH: 0.7,
        DEFAULT_HEIGHT: 0.6,
        MIN_WIDTH: 200,
        MIN_HEIGHT: 150,
        RESIZE_HANDLE_SIZE: 12,
        DRAG_OFFSET: 30,
        DEFAULT_POPUP_WIDTH: 800,
        DEFAULT_POPUP_HEIGHT: 600,
        STATE_VERSION: 1,
        STATE_KEY: "dbx.openWin.state",
        STATE_SAVE_DELAY: 120
    };

    // --------------------------------------------------
    // WINDOW MANAGER KLASSE
    // --------------------------------------------------

    class WindowManager {

        constructor() {
            this.windows = new Map();
            this.stack = [];
            this.activeDrag = null;
            this.activeResize = null;
            this.counter = 0;
            this.cssLoaded = false;
            this.stateSaveTimer = null;
            this.restoringState = false;
            this.stateRestored = false;

            this.localizeChrome();
            this.initEventSystem();
            this.initGlobalDelegation();

        }

        // --------------------------------------------------
        // EVENT SYSTEM (ZENTRAL)
        // --------------------------------------------------

        initEventSystem() {
            $(document).on('mousemove.openwin', (e) => this.handleMouseMove(e));
            $(document).on('mouseup.openwin', (e) => this.handleMouseUp(e));
            $(document).on('touchmove.openwin', (e) => this.handleMouseMove(e));
            $(document).on('touchend.openwin touchcancel.openwin', (e) => this.handleMouseUp(e));
            $(document).on('keydown.openwin', (e) => this.handleKeyDown(e));
            $(window).on('beforeunload.openWinState', () => {
                this.saveState(true);
            });
        }

        initGlobalDelegation() {
            dbx.on("click", ".dbx-win, [data-dbx*='lib=openWin']", (e, el) => {
                this.handleWinClick(e, el);
            });

            // 🔥 NEU: Window Actions
            dbx.on("click", ".dbx-window [data-action]", (e, el) => {
                this.handleWindowAction(e, el);
            });

            $(document).on("click.openWinDock", "#windrop .dbx-window-drop", (e) => {
                e.preventDefault();
                const windowId = $(e.currentTarget).attr("data-window-id");
                this.restoreWindow(windowId);
            });

            $(document).on("click.openWinCloseAll", "#dbxWindowCloseAll", (e) => {
                e.preventDefault();
                this.closeAllWindows();
            });
        }

        handleWindowAction(e, el) {
            const action = el.dataset.action;
            if (!action) return;

            const $win = $(el).closest(".dbx-window");
            if (!$win.length) return;

            const windowId = $win.attr("id");
            const windowData = this.windows.get(windowId);
            if (!windowData) return;

            this.safeLog("action", action, windowId);

            switch(action) {

                case "close":
                    this.closeWindow(windowId);
                    break;

                case "reload":
                    this.reloadWindow(windowId);
                    break;

                case "fullscreen":
                    $win.find(".dbx-window-maximize").trigger("click");
                    break;

                case "minimize":
                    if (!this.isMobileViewport()) {
                        $win.find(".dbx-window-minimize").trigger("click");
                    }
                    break;

                case "front":
                    this.bringToFront(windowId);
                    break;

                case "select":

                    let value = el.dataset.value;
                    let label = el.dataset.label;

                    if (value === undefined) {
                        value = $(el).text().trim();
                    }

                    if (label === undefined) {
                        label = value;
                    }

                    const item = { value, label };

                    // 🔥 MULTI MODE
                    if (windowData.cfg.multi == 1) {

                        const idx = windowData.selectedValues.findIndex(v => v.value == value);

                        if (idx !== -1) {
                            windowData.selectedValues.splice(idx, 1);
                            $(el).removeClass("dbx-selected");
                        } else {
                            windowData.selectedValues.push(item);
                            $(el).addClass("dbx-selected");
                        }

                        // 🔥 keepOpen entscheidet ob direkt resolved wird
                        if (windowData.cfg.keepOpen != 1) {
                            this.resolve(windowId, windowData.selectedValues);
                        }

                    } else {
                        this.resolve(windowId, item);
                    }

                    break;
            }

            e.preventDefault();
        }

        readWinConfig(el) {

            if (!el) return null;

            if (dbx.declare && dbx.declare.schemas && dbx.declare.schemas.openWin) {
                const list = dbx.declare.resolve("openWin", el);
                if (list && list.length) return list[0];
            }

            const raw = el.getAttribute("data-dbx");
            if (!raw) return null;

            const cfgList = dbx.parseData(raw);
            return cfgList.find(c => c.lib == "openWin") || null;
        }

        handleWinClick(e, el) {

            const cfg = this.readWinConfig(el);
            if (!cfg) return;

            e.preventDefault();

            if (el.__dbxOpening) return;
            el.__dbxOpening = true;
            setTimeout(() => el.__dbxOpening = false, 300);

            this.openWindow(el, cfg);
        }

        // --------------------------------------------------
        // MOUSE EVENT HANDLER
        // --------------------------------------------------

        handleMouseMove(e) {
            if (this.activeDrag) {
                this.updateDragPosition(e);
            }
            if (this.activeResize) {
                this.updateResizeSize(e);
            }
        }

        handleMouseUp(e) {

            // 🔥 NEU: dragging / resizing cleanup
            const changedWindowId = this.activeDrag?.windowId || this.activeResize?.windowId || null;

            if (this.activeDrag) {
                const w = this.windows.get(this.activeDrag.windowId);
                if (w) w.element.removeClass("dbx-window-dragging");
            }

            if (this.activeResize) {
                const w = this.windows.get(this.activeResize.windowId);
                if (w) w.element.removeClass("dbx-window-resizing");
            }

            this.activeDrag = null;
            this.activeResize = null;

            if (changedWindowId) {
                this.scheduleSaveState();
            }
        }

        handleKeyDown(e) {

            // 🔥 PROMPT KEYBOARD + TYPEAHEAD
            if (this.stack.length > 0) {

                const activeWin = this.stack[this.stack.length - 1];
                if (activeWin && activeWin.cfg.mode === "prompt") {

                    const $win = activeWin.element;
                    const $allItems = $win.find("[data-action='select']"); // 🔥 FIX
                    const $items = $allItems.filter(":visible"); // 🔥 FIX

                    if ($items.length) {

                        // 🔥 CONFIG
                        const filterMode = activeWin.cfg.filter || "highlight";

                        // 🔥 INIT TYPE BUFFER
                        if (!activeWin._typeBuffer) activeWin._typeBuffer = "";
                        if (!activeWin._typeTimer) activeWin._typeTimer = null;

                        let $current = $items.filter(".dbx-active").first();

                        if (!$current.length) {
                            $items.removeClass("dbx-active"); // 🔥 FIX
                            $current = $items.first().addClass("dbx-active");
                        }

                        let idx = $items.index($current);

                        if (idx === -1) idx = 0; // 🔥 FIX

                        // --------------------------------------------------
                        // 🔥 TYPEAHEAD
                        // --------------------------------------------------
                        if (e.key.length === 1 && !e.ctrlKey && !e.metaKey) {

                            activeWin._typeBuffer += e.key.toLowerCase();

                            clearTimeout(activeWin._typeTimer);
                            activeWin._typeTimer = setTimeout(() => {

                                activeWin._typeBuffer = "";

                                // 🔥 RESET FILTER (ALLE ITEMS!)
                                $allItems.each(function() { // 🔥 FIX
                                    const $el = $(this);
                                    $el.show();
                                    $el.removeClass("dbx-match");
                                });

                            }, 500);

                            const search = activeWin._typeBuffer;

                            let foundIndex = -1;

                            $allItems.each(function(i) { // 🔥 FIX
                                const $el = $(this);

                                const label = ($el.data("label") || $el.text()).toString().toLowerCase();

                                const match = label.includes(search);

                                // 🔥 FILTER MODES
                                if (filterMode === "hide") {
                                    $el.toggle(match);
                                } else {
                                    $el.show();
                                }

                                if (filterMode === "highlight") {
                                    $el.toggleClass("dbx-match", match);
                                } else {
                                    $el.removeClass("dbx-match");
                                }

                                if (match && foundIndex === -1) {
                                    foundIndex = i;
                                }
                            });

                            const $visible = $allItems.filter(":visible"); // 🔥 FIX

                            if (foundIndex !== -1 && $visible.length) {

                                $allItems.removeClass("dbx-active"); // 🔥 FIX

                                const $target = $visible.first(); // 🔥 FIX (stabil)

                                $target.addClass("dbx-active");

                                const el = $target[0];
                                if (el && el.scrollIntoView) {
                                    el.scrollIntoView({ block: "nearest" });
                                }
                            }

                            e.preventDefault();
                            return;
                        }

                        // --------------------------------------------------
                        // 🔥 ARROWS
                        // --------------------------------------------------
                        if (e.key === "ArrowDown") {
                            idx = Math.min(idx + 1, $items.length - 1);
                            $items.removeClass("dbx-active"); // 🔥 FIX
                            $items.eq(idx).addClass("dbx-active");
                            e.preventDefault();
                            return;
                        }

                        if (e.key === "ArrowUp") {
                            idx = Math.max(idx - 1, 0);
                            $items.removeClass("dbx-active"); // 🔥 FIX
                            $items.eq(idx).addClass("dbx-active");
                            e.preventDefault();
                            return;
                        }

                        // --------------------------------------------------
                        // 🔥 ENTER
                        // --------------------------------------------------
                        if (e.key === "Enter") {
                            $items.eq(idx).trigger("click");
                            e.preventDefault();
                            return;
                        }
                    }
                }
            }

            // --------------------------------------------------
            // BESTEHENDES VERHALTEN (UNVERÄNDERT)
            // --------------------------------------------------

            if (e.key == 'Escape' && this.stack.length > 0) {

                const activeWin = this.stack[this.stack.length - 1];
                if (!activeWin) return;

                if (activeWin.fullscreen && !this.isMobileViewport()) {

                    const $win = activeWin.element;

                    if (activeWin.originalSize) {
                        $win.css({
                            width: activeWin.originalSize.width,
                            height: activeWin.originalSize.height,
                            left: activeWin.originalPosition.left,
                            top: activeWin.originalPosition.top
                        });
                    }

                    activeWin.fullscreen = false;
                    $win.removeClass("dbx-window-fullscreen");

                    $win.find(".dbx-window-maximize")
                        .html('<i class="bi bi-square"></i>');

                    e.preventDefault();
                    return;
                }

                if (activeWin.cfg.closable != 0) {
                    this.closeWindow(activeWin.id);
                    e.preventDefault();
                }
            }

            const activeElement = document.activeElement;
            if (activeElement && activeElement.closest) {
                if (this.isKeyboardEditingElement(activeElement)) return;
                const windowEl = activeElement.closest('.dbx-window');
                if (windowEl) {
                    this.moveWindowWithKeys(windowEl.id, e);
                }
            }
        }

        localizeChrome() {
            $(".dbx-footer-dockbar").attr("aria-label", ui.windowBar);
            $("#windrop").attr("aria-label", ui.minimizedWindows);
            $("#dbxWindowCloseAll")
                .attr("title", ui.closeAll)
                .attr("aria-label", ui.closeAll);
            $(".dbx-footer-info").attr("aria-label", ui.systemInfo);
            $(".dbx-footer-info-item")
                .attr("title", ui.runtimeTitle)
                .each(function () {
                    $(this).find("span").first().text(ui.runtime);
                });
            $(".dbx-footer-consent-link").each(function () {
                const $link = $(this);
                const dataDbx = String($link.attr("data-dbx") || "")
                    .replace(/url=datenschutz(?=\||$)/, "url=" + ui.privacySlug)
                    .replace(/title=Datenschutz(?=\||$)/, "title=" + ui.privacy);
                $link
                    .attr("href", ui.privacySlug)
                    .attr("data-dbx", dataDbx)
                    .text(ui.privacy);
            });
        }

        isKeyboardEditingElement(el) {
            if (!el || !el.closest) return false;
            const target = el.closest('input, textarea, select, [contenteditable="true"], [contenteditable=""], [role="textbox"], .jodit-ui-input, .jodit-ui-text_area');
            if (!target) return false;
            if (target.matches && target.matches('input')) {
                const type = String(target.getAttribute('type') || 'text').toLowerCase();
                return !['button', 'checkbox', 'color', 'file', 'image', 'radio', 'range', 'reset', 'submit'].includes(type);
            }
            return true;
        }

        moveWindowWithKeys(windowId, e) {
            const windowData = this.windows.get(windowId);
            if (!windowData || windowData.cfg.draggable == 0) return;
            if (this.isMobileViewport()) return;

            const step = e.shiftKey ? 10 : 1;
            const currentPos = windowData.element.position();
            let newLeft = currentPos.left;
            let newTop = currentPos.top;

            switch(e.key) {
                case 'ArrowLeft': newLeft -= step; break;
                case 'ArrowRight': newLeft += step; break;
                case 'ArrowUp': newTop -= step; break;
                case 'ArrowDown': newTop += step; break;
                default: return;
            }

            const bounds = this.getViewportPageBounds();
            newLeft = Math.min(
                Math.max(newLeft, bounds.left),
                Math.max(bounds.left, bounds.right - windowData.element.outerWidth())
            );
            newTop = Math.min(
                Math.max(newTop, bounds.top),
                Math.max(bounds.top, bounds.bottom - windowData.element.outerHeight())
            );

            windowData.element.css({ left: newLeft + 'px', top: newTop + 'px' });
            e.preventDefault();
        }

        // --------------------------------------------------
        // Z-INDEX MANAGEMENT
        // --------------------------------------------------

        isTopZWindow(windowDataOrCfg) {
            const cfg = windowDataOrCfg && windowDataOrCfg.cfg ? windowDataOrCfg.cfg : (windowDataOrCfg || {});
            const priority = String(cfg.priority || cfg.zPriority || "").toLowerCase();
            return cfg.topZ == 1 || cfg.modalTop == 1 || priority === "top" || priority === "modal-top";
        }

        windowBaseZ(windowDataOrCfg, isModal = false) {
            const cfg = windowDataOrCfg && windowDataOrCfg.cfg ? windowDataOrCfg.cfg : (windowDataOrCfg || {});
            if (this.isTopZWindow(windowDataOrCfg)) return CONFIG.BASE_Z_TOP;
            return (isModal || cfg.modal == 1 || cfg.mode == "modal-fullscreen") ? CONFIG.BASE_Z_MODAL : CONFIG.BASE_Z_DRAGGABLE;
        }

        domElement(el) {
            if (!el) return null;
            if (el.jquery && el[0]) return el[0];
            if (el.nodeType === 1) return el;
            return null;
        }

        numericZIndex(el) {
            el = this.domElement(el);
            if (!el) return 0;
            const z = parseInt(window.getComputedStyle(el).zIndex, 10);
            return Number.isFinite(z) ? z : 0;
        }

        maxElementZIndex(el) {
            let max = 0;
            let cur = this.domElement(el);
            while (cur && cur !== document.documentElement) {
                max = Math.max(max, this.numericZIndex(cur));
                cur = cur.parentElement;
            }
            return max;
        }

        callerZIndexFloor(callerEl) {
            return this.maxElementZIndex(callerEl);
        }

        nextZAboveCaller(zIndex, callerEl) {
            const callerZ = this.callerZIndexFloor(callerEl);
            if (callerZ > 0) {
                zIndex = Math.max(zIndex, callerZ + CONFIG.Z_INCREMENT);
            }
            return Math.min(2147483647, zIndex);
        }

        getNextZIndex(isModal = false, cfg = null, callerEl = null) {
            const baseZ = this.windowBaseZ(cfg || {}, isModal);
            return this.nextZAboveCaller(baseZ + (this.stack.length * CONFIG.Z_INCREMENT), callerEl);
        }

        bringToFront(windowId) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return;

            const idx = this.stack.findIndex(w => w.id == windowId);
            if (idx != -1) {
                const [win] = this.stack.splice(idx, 1);
                this.stack.push(win);
            }

            this.windows.forEach(w => {
                w.element.removeClass("dbx-window-active");
            });

            windowData.element.addClass("dbx-window-active");
            this.updateAllZIndices();

            const newZIndex = parseInt(windowData.element.css('z-index'), 10) || 0;
            this.safeLog("bringToFront", windowId, "z-index:", newZIndex);
            this.scheduleSaveState();
        }

        activateTopVisibleWindow(excludeWindowId = null) {
            for (let i = this.stack.length - 1; i >= 0; i--) {
                const windowData = this.stack[i];
                if (!windowData || windowData.id == excludeWindowId) continue;
                if (windowData.closing || windowData.minimized) continue;
                if (!windowData.element || !windowData.element.is(":visible")) continue;

                this.bringToFront(windowData.id);
                return;
            }

            this.windows.forEach(w => {
                w.element.removeClass("dbx-window-active");
            });
            this.updateAllZIndices();
        }

        focusWindow(windowId) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return;

            const $win = windowData.element;
            const $active = $win.find("input,textarea,select,button,a,[tabindex]").filter(":visible").first();

            if ($active.length) {
                $active.focus();
            } else {
                $win.focus();
            }
        }

        restoreWindow(windowId) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return;

            if (this.isMobileViewport()) {
                this.applyMobileWindowMode(windowData);
                this.bringToFront(windowId);
                this.focusWindow(windowId);
                return;
            }

            const $win = windowData.element;

            if (windowData.minimized || !$win.is(":visible")) {
                const $dockItem = this.getDockItem(windowId);
                const fromRect = this.getElementRect($dockItem);

                if (windowData.maximized && windowData.originalSize && windowData.originalPosition) {
                    $win.css({
                        width: windowData.originalSize.width,
                        height: windowData.originalSize.height,
                        left: windowData.originalPosition.left,
                        top: windowData.originalPosition.top
                    });
                    windowData.maximized = false;
                    $win.find(".dbx-window-maximize").html('<i class="bi bi-square"></i>');
                }

                if (windowData.overlay) {
                    windowData.overlay.show();
                }

                $win.stop(true, true).css("display", "flex");
                $win.css("visibility", "hidden");
                const toRect = this.getElementRect($win);

                windowData.minimized = false;
                $win.removeClass("dbx-window-is-minimized");
                $win.find(".dbx-window-minimize").html('<i class="bi bi-dash"></i>');

                this.animateDockTransfer($win, fromRect, toRect, windowData.cfg, "restore", () => {
                    this.removeDockItem(windowId);
                    this.focusWindow(windowId);
                    this.scheduleSaveState();
                });
            }

            this.bringToFront(windowId);
            this.focusWindow(windowId);
        }

        getDock() {
            let $dock = $("#windrop");

            if (!$dock.length) {
                $dock = $('<div id="windrop"></div>');

                const $footer = $("#dbxFooter");
                if ($footer.length) {
                    $footer.prepend($dock);
                } else {
                    $("body").append($dock.addClass("dbx-window-dock-floating"));
                }
            }

            if (!$dock.attr("aria-label")) {
                $dock.attr("aria-label", ui.minimizedWindows);
            }

            return $dock.addClass("dbx-window-dock");
        }

        updateDockUi() {
            const $footer = $("#dbxFooter");
            if (!$footer.length) return;

            const windowCount = this.windows.size;
            const dockCount = $("#windrop .dbx-window-drop").length;
            const $closeAll = $("#dbxWindowCloseAll");

            $footer
                .toggleClass("has-open-windows", windowCount > 0)
                .toggleClass("has-docked-windows", dockCount > 0);

            if ($closeAll.length) {
                $closeAll
                    .prop("disabled", windowCount < 1)
                    .attr("title", windowCount > 0 ? ui.closeAll + " (" + windowCount + ")" : ui.noWindows)
                    .attr("aria-label", windowCount > 0 ? ui.closeAll : ui.noWindows)
                    .attr("aria-hidden", windowCount > 0 ? "false" : "true");
            }
        }

        getDockItem(windowId) {
            if (!windowId) return $();

            return this.getDock()
                .children(".dbx-window-drop")
                .filter((idx, el) => el.getAttribute("data-window-id") == windowId)
                .first();
        }

        createDockItem(windowData) {
            const title = this.getDockTitle(windowData);
            let $item = this.getDockItem(windowData.id);

            if (!$item.length) {
                $item = $(`
                    <button type="button" class="dbx-window-drop">
                        <i class="bi bi-window"></i>
                        <span class="dbx-window-drop-title"></span>
                    </button>
                `);

                this.getDock().append($item);
            }

            $item
                .attr("data-window-id", windowData.id)
                .attr("title", title)
                .attr("aria-label", uiFormat(ui.openWindow, { title: title }))
                .removeClass("is-restoring")
                .addClass("is-minimized");

            $item.find(".dbx-window-drop-title").text(title);
            this.updateDockUi();

            return $item;
        }

        getDockTitle(windowData) {
            if (!windowData) return ui.window;

            const cfgTitle = windowData.cfg && windowData.cfg.title;
            const domTitle = windowData.element ? windowData.element.find(".dbx-window-title").text() : "";
            return (cfgTitle || domTitle || ui.window).toString();
        }

        removeDockItem(windowId) {
            this.getDockItem(windowId).remove();
            this.updateDockUi();
        }

        getElementRect($el) {
            if (!$el || !$el.length || !$el[0].getBoundingClientRect) return null;

            const rect = $el[0].getBoundingClientRect();
            if (!rect.width || !rect.height) return null;

            return {
                left: rect.left,
                top: rect.top,
                width: rect.width,
                height: rect.height
            };
        }

        getDockAnimationDuration(cfg) {
            cfg = cfg || {};

            const duration = parseInt(
                cfg.dockAnimDuration ||
                cfg.dockAnimationDuration ||
                cfg.windropAnimDuration ||
                cfg.windropAnimationDuration,
                10
            );

            if (!isNaN(duration)) return Math.max(320, duration);

            return Math.max(680, this.getAnimationDuration(cfg));
        }

        animateDockTransfer($win, fromRect, toRect, cfg, mode, done) {
            const duration = this.getDockAnimationDuration(cfg);

            if (!$win || !$win.length || !fromRect || !toRect || duration <= 0 || !window.requestAnimationFrame) {
                if ($win && $win.length) {
                    $win.css("visibility", "");
                }
                if (done) done();
                return;
            }

            const startRect = mode == "restore" ? toRect : fromRect;
            const endRect = mode == "restore" ? fromRect : toRect;
            const scaleX = Math.max(0.04, endRect.width / startRect.width);
            const scaleY = Math.max(0.04, endRect.height / startRect.height);
            const translateX = endRect.left - startRect.left;
            const translateY = endRect.top - startRect.top;
            const startTransform = mode == "restore"
                ? `translate(${translateX}px, ${translateY}px) scale(${scaleX}, ${scaleY})`
                : "translate(0, 0) scale(1, 1)";
            const endTransform = mode == "restore"
                ? "translate(0, 0) scale(1, 1)"
                : `translate(${translateX}px, ${translateY}px) scale(${scaleX}, ${scaleY})`;
            const el = $win[0];
            const originalTransition = el.style.transition;
            const originalTransform = el.style.transform;
            const originalTransformOrigin = el.style.transformOrigin;
            const originalPointerEvents = el.style.pointerEvents;
            let finished = false;

            const cleanup = () => {
                $win.removeClass("dbx-window-docking");
                el.style.transition = originalTransition;
                el.style.transform = originalTransform;
                el.style.transformOrigin = originalTransformOrigin;
                el.style.pointerEvents = originalPointerEvents;
            };

            const finish = () => {
                if (finished) return;
                finished = true;
                clearTimeout(timer);
                $win.off("transitionend.openWinDock");

                if (mode == "minimize") {
                    if (done) done();
                    cleanup();
                    return;
                }

                cleanup();
                if (done) done();
            };

            $win
                .addClass("dbx-window-docking")
                .css({
                    visibility: "",
                    transition: "none",
                    transformOrigin: "left top",
                    transform: startTransform,
                    pointerEvents: "none"
                });

            el.offsetHeight;

            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    $win.css({
                        transition: `transform ${duration}ms cubic-bezier(0.18, 0.86, 0.24, 1)`,
                        transform: endTransform
                    });
                });
            });

            const timer = setTimeout(finish, duration + 80);
            $win.on("transitionend.openWinDock", (e) => {
                const event = e.originalEvent || e;
                if (e.target !== el || event.propertyName !== "transform") return;
                finish();
            });
        }

        updateAllZIndices() {
            this.stack.forEach((windowData, idx) => {
                const baseZ = this.windowBaseZ(windowData, windowData.isModal);

                const zIndex = baseZ + (idx * CONFIG.Z_INCREMENT);
                windowData.element.css('z-index', zIndex);
                if (windowData.overlay) {
                    windowData.overlay.css('z-index', zIndex - 1);
                }
            });

            for (let pass = 0; pass < 2; pass++) {
                this.stack.forEach((windowData) => {
                    const currentZ = parseInt(windowData.element.css('z-index'), 10) || 0;
                    const zIndex = this.nextZAboveCaller(currentZ, windowData.callerEl);
                    if (zIndex === currentZ) return;
                    windowData.element.css('z-index', zIndex);
                    if (windowData.overlay) {
                        windowData.overlay.css('z-index', zIndex - 1);
                    }
                });
            }
        }

        reloadWindow(windowId) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return;

            let url = windowData.url;
            if (!url) return;

            // 🔥 cache bust (optional, aber sinnvoll)
            const sep = url.includes("?") ? "&" : "?";
            url = url + sep + "_=" + Date.now();

            this.safeLog("reload", windowId, url);

            this.loadContent(windowId, url, windowData.cfg);
        }       

        // --------------------------------------------------
        // HILFSFUNKTIONEN
        // --------------------------------------------------

        safeLog(...args) {
            if (dbx.log && typeof dbx.log == 'function') {
                dbx.log("openWin →", ...args);
            } else if (console && console.log) {
                console.log("[openWin]", ...args);
            }
        }

        safeWarn(...args) {
            if (dbx.warn && typeof dbx.warn == 'function') {
                dbx.warn("openWin →", ...args);
            } else if (console && console.warn) {
                console.warn("[openWin]", ...args);
            }
        }

        parseSize(value, base, defaultValue = null) {
            if (value == undefined || value == null) return defaultValue;

            const strValue = value.toString().trim().toLowerCase();

            if (strValue == "auto") return "auto";

            if (strValue.endsWith("%")) {
                const p = parseFloat(strValue);
                if (!isNaN(p)) return Math.round(base * p / 100);
            }

            let numericValue = parseInt(strValue);
            if (strValue.endsWith("px")) {
                numericValue = parseInt(strValue);
            }

            if (!isNaN(numericValue)) return numericValue;

            return defaultValue;
        }

        optionEnabled(value, defaultValue = true) {
            if (value == undefined || value == null || value === "") return defaultValue;
            if (value === false) return false;

            const strValue = value.toString().trim().toLowerCase();
            return !["0", "false", "off", "no", "nein"].includes(strValue);
        }

        optionUnset(value) {
            return value == undefined || value == null || value === "";
        }

        getViewportPageBounds() {
            const root = document.documentElement;
            const fallbackLeft = window.pageXOffset || root.scrollLeft || 0;
            const fallbackTop = window.pageYOffset || root.scrollTop || 0;
            const visual = window.visualViewport;
            const width = visual && Number.isFinite(visual.width)
                ? visual.width
                : (window.innerWidth || root.clientWidth);
            const height = visual && Number.isFinite(visual.height)
                ? visual.height
                : (window.innerHeight || root.clientHeight);
            const left = visual && Number.isFinite(visual.pageLeft)
                ? visual.pageLeft
                : fallbackLeft;
            const top = visual && Number.isFinite(visual.pageTop)
                ? visual.pageTop
                : fallbackTop;

            return {
                left: left,
                top: top,
                width: width,
                height: height,
                right: left + width,
                bottom: top + height
            };
        }

        clampWindowToViewport(windowData) {
            if (!windowData || this.isMobileViewport()) return;

            const $win = windowData.element;
            if (!$win || !$win.length || !$win.is(":visible")) return;

            const bounds = this.getViewportPageBounds();
            const width = $win.outerWidth();
            const height = $win.outerHeight();
            const currentLeft = parseFloat($win.css("left"));
            const currentTop = parseFloat($win.css("top"));
            const minLeft = bounds.left;
            const minTop = bounds.top;
            const maxLeft = Math.max(minLeft, bounds.right - width);
            const maxTop = Math.max(minTop, bounds.bottom - height);

            $win.css({
                left: Math.min(Math.max(
                    Number.isFinite(currentLeft) ? currentLeft : minLeft,
                    minLeft
                ), maxLeft) + "px",
                top: Math.min(Math.max(
                    Number.isFinite(currentTop) ? currentTop : minTop,
                    minTop
                ), maxTop) + "px"
            });
        }

        isMobileViewport() {
            const maxWidth = 768;

            if (window.matchMedia) {
                return window.matchMedia("(max-width: " + maxWidth + "px)").matches;
            }

            return window.innerWidth <= maxWidth;
        }

        applyMobileWindowMode(windowData) {
            if (!windowData || !this.isMobileViewport()) return;

            const $win = windowData.element;
            if (!$win || !$win.length) return;

            const el = $win[0];
            const heightValue = (
                window.CSS &&
                CSS.supports &&
                CSS.supports("height", "100dvh")
            ) ? "100dvh" : window.innerHeight + "px";

            windowData.minimized = false;
            windowData.fullscreen = true;
            windowData.maximized = true;

            this.removeDockItem(windowData.id);

            $win
                .removeClass("dbx-window-is-minimized")
                .addClass("dbx-window-fullscreen dbx-window-mobile-fullscreen")
                .css("display", "flex");

            el.style.setProperty("position", "fixed", "important");
            el.style.setProperty("left", "0", "important");
            el.style.setProperty("top", "0", "important");
            el.style.setProperty("width", "100vw", "important");
            el.style.setProperty("height", heightValue, "important");
            el.style.setProperty("max-width", "100vw", "important");
            el.style.setProperty("max-height", heightValue, "important");
            el.style.setProperty("min-width", "0", "important");
            el.style.setProperty("min-height", "0", "important");

            $win.find(".dbx-window-minimize").hide();
            $win.find(".dbx-window-maximize").hide();
            $win.find(".dbx-window-resize").hide();

            if (windowData.overlay) {
                windowData.overlay.show();
            }
        }

        normalizeConfig(cfg) {
            cfg = cfg || {};

            if (this.optionUnset(cfg.scroll)) {
                cfg.scroll = 1;
            }

            if (this.optionUnset(cfg.reloadable) && this.optionUnset(cfg.reload)) {
                cfg.reload = 1;
            }

            return cfg;
        }

        getStateStorageKey() {
            const path = (window.location && window.location.pathname) ? window.location.pathname : "default";
            return CONFIG.STATE_KEY + ":" + path;
        }

        readState() {
            try {
                if (!dbx.uiGet) return null;

                const data = dbx.uiGet("openWin", this.getStateStorageKey(), "state", null);
                if (!data || data.version !== CONFIG.STATE_VERSION || !Array.isArray(data.windows)) {
                    return null;
                }

                return data;
            } catch (e) {
                this.safeWarn("state read failed", e);
                return null;
            }
        }

        writeState(data) {
            try {
                if (!dbx.uiSet) return false;
                dbx.uiSet("openWin", this.getStateStorageKey(), "state", data && data.windows && data.windows.length ? data : null);
                return true;
            } catch (e) {
                this.safeWarn("state write failed", e);
                return false;
            }
        }

        clearState() {
            try {
                if (!dbx.uiSet) return false;
                dbx.uiSet("openWin", this.getStateStorageKey(), "state", null);
                return true;
            } catch (e) {
                this.safeWarn("state clear failed", e);
                return false;
            }
        }

        getRestoreMode(options = {}) {
            const configMode = dbx.config && (
                dbx.config.openWinRestoreMode ||
                (dbx.config.openWin && dbx.config.openWin.restoreMode)
            );
            const mode = (options.mode || options.restoreMode || configMode || "minimized").toString().toLowerCase();

            return mode == "minimized" || mode == "minimize" || mode == "windrop" ? "minimized" : "state";
        }

        isPersistableWindow(windowData) {
            if (!windowData || !windowData.url) return false;
            if (windowData.closing) return false;

            const cfg = windowData.cfg || {};
            const mode = (cfg.mode || "panel").toString().toLowerCase();
            const method = (cfg.method || "GET").toString().toUpperCase();

            if (!this.optionEnabled(cfg.persist ?? cfg.restoreOnReload ?? cfg.saveState, true)) return false;
            if (cfg.content !== undefined || cfg.html !== undefined) return false;
            if (["modal-fullscreen", "prompt", "popup", "confirm"].includes(mode)) return false;
            if (method !== "GET") return false;

            return true;
        }

        sanitizeStateConfig(cfg) {
            const skipKeys = [
                "restore", "__restore", "__restoreState", "__restoreMinimized",
                "callerEl", "onBeforeClose", "onOpen", "onClose", "onLoad",
                "onError", "onReuse", "onMaxReached"
            ];

            try {
                return JSON.parse(JSON.stringify(cfg || {}, (key, value) => {
                    if (!key) return value;
                    if (skipKeys.includes(key) || key.startsWith("__") || /^on[A-Z]/.test(key)) return undefined;
                    if (typeof value == "function" || typeof value == "undefined") return undefined;
                    if (value && (value.nodeType || value === window || value === document || value.jquery)) return undefined;
                    return value;
                }));
            } catch (e) {
                const clean = {};
                Object.keys(cfg || {}).forEach(key => {
                    const value = cfg[key];
                    if (skipKeys.includes(key) || key.startsWith("__") || /^on[A-Z]/.test(key)) return;
                    if (typeof value == "function" || typeof value == "undefined") return;
                    if (value && typeof value == "object") return;
                    clean[key] = value;
                });
                return clean;
            }
        }

        getWindowState(windowData, order) {
            if (!this.isPersistableWindow(windowData)) return null;

            const $win = windowData.element;
            const $body = $win.find(".dbx-window-body");

            return {
                url: windowData.url,
                cfg: this.sanitizeStateConfig(windowData.cfg),
                fingerprint: windowData.fingerprint,
                order: order,
                title: this.getDockTitle(windowData),
                state: {
                    left: $win.css("left"),
                    top: $win.css("top"),
                    width: $win.css("width"),
                    height: $win.css("height"),
                    zIndex: parseInt($win.css("z-index"), 10) || null,
                    minimized: !!windowData.minimized,
                    fullscreen: !!windowData.fullscreen,
                    originalSize: windowData.originalSize || null,
                    originalPosition: windowData.originalPosition || null,
                    scrollTop: $body.length ? $body.scrollTop() : 0
                }
            };
        }

        saveState(force = false) {
            if (this.restoringState && !force) return false;

            clearTimeout(this.stateSaveTimer);
            this.stateSaveTimer = null;

            const windows = this.stack
                .map((windowData, order) => this.getWindowState(windowData, order))
                .filter(Boolean);

            return this.writeState({
                version: CONFIG.STATE_VERSION,
                savedAt: Date.now(),
                path: window.location ? window.location.pathname : "",
                windows: windows
            });
        }

        scheduleSaveState() {
            if (this.restoringState) return;

            clearTimeout(this.stateSaveTimer);
            this.stateSaveTimer = setTimeout(() => this.saveState(), CONFIG.STATE_SAVE_DELAY);
        }

        applySavedWindowState(windowData, state) {
            if (!windowData || !state) return;

            const $win = windowData.element;
            const css = {};

            ["left", "top", "width", "height"].forEach(key => {
                if (state[key] !== undefined && state[key] !== null && state[key] !== "") {
                    css[key] = state[key];
                }
            });

            if (Object.keys(css).length) {
                $win.css(css);
            }

            windowData.originalSize = state.originalSize || null;
            windowData.originalPosition = state.originalPosition || null;

            if (state.fullscreen) {
                $win.css({
                    width: "100%",
                    height: "100%",
                    left: "0",
                    top: "0"
                });

                windowData.fullscreen = true;
                $win.addClass("dbx-window-fullscreen");
                $win.find(".dbx-window-maximize").html('<i class="bi bi-arrows-angle-contract"></i>');
            }
        }

        restoreState(options = {}) {
            if (this.stateRestored && !options.force) return [];

            const data = this.readState();
            this.stateRestored = true;

            if (!data || !Array.isArray(data.windows) || !data.windows.length) {
                return [];
            }

            const mode = this.getRestoreMode(options);
            const restored = [];

            this.restoringState = true;

            data.windows
                .slice()
                .sort((a, b) => (a.order || 0) - (b.order || 0))
                .forEach(item => {
                    if (!item || !item.url) return;

                    const cfg = $.extend(true, {}, item.cfg || {});
                    const isModal = (cfg.modal == 1 || cfg.mode == "modal-fullscreen");
                    if (!this.isPersistableWindow({ url: item.url, cfg: cfg, isModal: isModal })) return;

                    const fingerprint = this.getWindowFingerprint(cfg, item.url);

                    if (this.findReusableWindow(fingerprint)) return;

                    cfg.__restore = 1;
                    cfg.__restoreState = item.state || {};
                    cfg.__restoreMinimized = (mode == "minimized" || (item.state && item.state.minimized)) ? 1 : 0;

                    const windowId = this.createWindow(cfg, item.url, null);
                    if (windowId) restored.push(windowId);
                });

            this.restoringState = false;
            this.saveState(true);

            this.safeLog("state restored", restored.length, "mode:", mode);
            return restored;
        }

        normalizeWindowUrl(url) {
            url = String(url || '').replace(/&amp;/g, '&');

            try {
                const parsed = new URL(url, window.location.href);
                const entries = Array.from(parsed.searchParams.entries())
                    .filter(([key]) => key !== '_')
                    .sort((a, b) => {
                        const keyCompare = a[0].localeCompare(b[0]);
                        return keyCompare || String(a[1]).localeCompare(String(b[1]));
                    });

                parsed.search = '';
                entries.forEach(([key, value]) => parsed.searchParams.append(key, value));

                return parsed.href;
            } catch (e) {
                return url;
            }
        }

        getWindowFingerprint(cfg, url) {
            const uniqueKeys = [
                'mode', 'modal', 'title', 'width', 'height', 'position',
                'left', 'top', 'sizeX', 'sizeY', 'scroll'
            ];
            const data = {
                url: this.normalizeWindowUrl(url)
            };

            uniqueKeys.forEach(key => {
                if (cfg[key] !== undefined && cfg[key] !== null) {
                    data[key] = cfg[key];
                }
            });

            return JSON.stringify(data);
        }

        findReusableWindow(fingerprint) {
            if (!fingerprint) return null;

            for (const windowData of this.windows.values()) {
                if (windowData.fingerprint === fingerprint) {
                    return windowData;
                }
            }

            return null;
        }

        escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        decodeConfigText(value) {
            if (value === undefined || value === null) return '';

            const text = value.toString();
            if (text.indexOf('%') === -1) return text;

            try {
                return decodeURIComponent(text);
            } catch (e) {
                return text;
            }
        }

        getAnimationName(cfg, type) {
            cfg = cfg || {};

            if (type == "open") {
                return cfg.animOpen || cfg.openAnimation || cfg.animationOpen || "fade-scale-in";
            }

            if (type == "minimize") {
                return cfg.animMin || cfg.minAnimation || cfg.animationMin || cfg.minimizeAnimation || "fade-out";
            }

            if (type == "close") {
                return cfg.animClose || cfg.closeAnimation || cfg.animationClose || "fade-out";
            }

            return "none";
        }

        getAnimationDuration(cfg) {
            const duration = parseInt((cfg || {}).animDuration || (cfg || {}).animationDuration || CONFIG.ANIMATION_DURATION, 10);
            return isNaN(duration) ? CONFIG.ANIMATION_DURATION : duration;
        }

        normalizeAnimationName(name) {
            name = (name || "none").toString().trim().toLowerCase();

            const aliases = {
                "fade": "fade-in",
                "fadein": "fade-in",
                "fade-in": "fade-in",
                "fadeout": "fade-out",
                "fade-out": "fade-out",
                "scale": "fade-scale-in",
                "fade-scale": "fade-scale-in",
                "fade-scale-in": "fade-scale-in",
                "scale-out": "fade-scale-out",
                "fade-scale-out": "fade-scale-out",
                "slide": "slide-up-in",
                "slide-up": "slide-up-in",
                "slide-up-in": "slide-up-in",
                "slide-down-out": "slide-down-out",
                "minimize": "minimize-out",
                "minimize-out": "minimize-out",
                "none": "none",
                "0": "none"
            };

            return aliases[name] || name.replace(/[^a-z0-9_-]/g, "");
        }

        animateElement($el, animationName, cfg, done) {
            const name = this.normalizeAnimationName(animationName);
            const duration = this.getAnimationDuration(cfg);

            if (!$el || !$el.length || name == "none" || duration <= 0) {
                if (done) done();
                return;
            }

            const className = "dbx-ow-anim-" + name;
            let finished = false;

            const finish = () => {
                if (finished) return;
                finished = true;
                clearTimeout(timer);
                $el.off("animationend.openWinAnimation");
                $el.removeClass(className + " dbx-ow-anim-running");
                $el[0].style.removeProperty("--dbx-openwin-animation-duration");
                if (done) done();
            };

            $el.removeClass((index, classList) => {
                return (classList.match(/(^|\s)dbx-ow-anim-\S+/g) || []).join(" ");
            });

            $el[0].style.setProperty("--dbx-openwin-animation-duration", duration + "ms");
            $el.addClass("dbx-ow-anim-running " + className);

            const timer = setTimeout(finish, duration + 80);
            $el.off("animationend.openWinAnimation").on("animationend.openWinAnimation", finish);
        }

        triggerCallback(callback, ...args) {
            if (callback && typeof callback == 'function') {
                callback(...args);
            } else if (callback && typeof callback == 'string' && window[callback]) {
                window[callback](...args);
            }
        }

        triggerCallbackWithReturn(callback, ...args) {
            if (callback && typeof callback == 'function') {
                return callback(...args);
            } else if (callback && typeof callback == 'string' && window[callback]) {
                return window[callback](...args);
            }
            return undefined;
        }

        // --------------------------------------------------
        // DRAG & RESIZE
        // --------------------------------------------------

        initDrag(windowId) {
            const windowData = this.windows.get(windowId);
            if (!windowData || windowData.cfg.draggable == 0) return;

            if (this.isMobileViewport()) return;

            const $win = windowData.element;
            const $header = $win.find(".dbx-window-header");

            $header.css("cursor", "move");

            $header.off('mousedown.drag').on('mousedown.drag', (e) => {
                if (!$(e.target).closest('.dbx-window-header').length) return;

                if ($(e.target).closest(".dbx-window-controls, .dbx-window-close, .dbx-window-minimize, .dbx-window-maximize, .dbx-window-reload, button, input, textarea, select, a, [data-action]").length) {
                    return;
                }

                this.bringToFront(windowId);

                // 🔥 NEU: dragging class
                $win.addClass("dbx-window-dragging");

                this.activeDrag = {
                    windowId: windowId,
                    startX: e.clientX,
                    startY: e.clientY,
                    startLeft: parseFloat($win.css('left')),
                    startTop: parseFloat($win.css('top'))
                };

                e.preventDefault();
            });

            $header.off('touchstart.drag').on('touchstart.drag', (e) => {
                if (this.isMobileViewport()) return;

                if ($(e.target).closest(".dbx-window-controls, .dbx-window-close, .dbx-window-minimize, .dbx-window-maximize, .dbx-window-reload, button, input, textarea, select, a, [data-action]").length) {
                    return;
                }

                const touch = e.originalEvent?.touches?.[0];
                if (!touch) return;

                this.bringToFront(windowId);

                // 🔥 NEU
                $win.addClass("dbx-window-dragging");

                this.activeDrag = {
                    windowId: windowId,
                    startX: touch.clientX,
                    startY: touch.clientY,
                    startLeft: parseFloat($win.css('left')),
                    startTop: parseFloat($win.css('top'))
                };

                e.preventDefault();
            });
        }

        updateDragPosition(e) {
            if (!this.activeDrag) return;

            const windowData = this.windows.get(this.activeDrag.windowId);
            if (!windowData) return;

            const clientX = e.clientX || (e.originalEvent?.touches?.[0]?.clientX);
            const clientY = e.clientY || (e.originalEvent?.touches?.[0]?.clientY);
            if (clientX == undefined) return;

            const dx = clientX - this.activeDrag.startX;
            const dy = clientY - this.activeDrag.startY;

            let newLeft = this.activeDrag.startLeft + dx;
            let newTop = this.activeDrag.startTop + dy;

            const bounds = this.getViewportPageBounds();
            newLeft = Math.min(
                Math.max(newLeft, bounds.left),
                Math.max(bounds.left, bounds.right - windowData.element.outerWidth())
            );
            newTop = Math.min(
                Math.max(newTop, bounds.top),
                Math.max(bounds.top, bounds.bottom - windowData.element.outerHeight())
            );

            windowData.element.css({
                left: newLeft + 'px',
                top: newTop + 'px'
            });
        }

        initResize(windowId) {
            const windowData = this.windows.get(windowId);
            if (!windowData || windowData.cfg.resizable == 0) return;

            if (this.isMobileViewport()) return;

            const $win = windowData.element;
            const $resize = $win.find(".dbx-window-resize");

            $resize.off('mousedown.resize').on('mousedown.resize', (e) => {
                this.bringToFront(windowId);

                // 🔥 NEU
                $win.addClass("dbx-window-resizing");

                this.activeResize = {
                    windowId: windowId,
                    startX: e.clientX,
                    startY: e.clientY,
                    startWidth: $win.outerWidth(),
                    startHeight: $win.outerHeight()
                };

                e.preventDefault();
                e.stopPropagation();
            });

            $resize.off('touchstart.resize').on('touchstart.resize', (e) => {
                if (this.isMobileViewport()) return;

                const touch = e.originalEvent?.touches?.[0];
                if (!touch) return;

                this.bringToFront(windowId);

                // 🔥 NEU
                $win.addClass("dbx-window-resizing");

                this.activeResize = {
                    windowId: windowId,
                    startX: touch.clientX,
                    startY: touch.clientY,
                    startWidth: $win.outerWidth(),
                    startHeight: $win.outerHeight()
                };

                e.preventDefault();
                e.stopPropagation();
            });
        }

        updateResizeSize(e) {
            if (!this.activeResize) return;

            const windowData = this.windows.get(this.activeResize.windowId);
            if (!windowData) return;

            const clientX = e.clientX || (e.originalEvent?.touches?.[0]?.clientX);
            const clientY = e.clientY || (e.originalEvent?.touches?.[0]?.clientY);
            if (clientX == undefined) return;

            const dx = clientX - this.activeResize.startX;
            const dy = clientY - this.activeResize.startY;

            let newWidth = this.activeResize.startWidth + dx;
            let newHeight = this.activeResize.startHeight + dy;

            const bounds = this.getViewportPageBounds();
            const viewportWidth = bounds.width;
            const viewportHeight = bounds.height;

            const minWidth = this.parseSize(windowData.cfg.minWidth, viewportWidth, CONFIG.MIN_WIDTH);
            const maxWidth = this.parseSize(windowData.cfg.maxWidth, viewportWidth, viewportWidth);
            const minHeight = this.parseSize(windowData.cfg.minHeight, viewportHeight, CONFIG.MIN_HEIGHT);
            const maxHeight = this.parseSize(windowData.cfg.maxHeight, viewportHeight, viewportHeight);

            newWidth = Math.min(Math.max(newWidth, minWidth), maxWidth);
            newHeight = Math.min(Math.max(newHeight, minHeight), maxHeight);

            windowData.element.css({
                width: newWidth + 'px',
                height: newHeight + 'px'
            });
        }

        // --------------------------------------------------
        // MINIMIZE / MAXIMIZE
        // --------------------------------------------------

        initMinimize(windowId) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return;
            if (this.isMobileViewport()) return;

            const $win = windowData.element;
            const $minimizeBtn = $win.find(".dbx-window-minimize");

            if (!$minimizeBtn.length) return;

            $minimizeBtn.off('click.minimize').on('click.minimize', () => {
                if (windowData.minimized) {
                    this.restoreWindow(windowId);
                } else {
                    this.minimizeWindow(windowId);
                }
            });
        }

        minimizeWindow(windowId) {
            const windowData = this.windows.get(windowId);
            if (!windowData || windowData.minimized) return;
            if (this.isMobileViewport()) return;

            const $win = windowData.element;
            const $minimizeBtn = $win.find(".dbx-window-minimize");
            const $dockItem = this.createDockItem(windowData);
            const fromRect = this.getElementRect($win);
            const toRect = this.getElementRect($dockItem);

            this.bringToFront(windowId);
            $dockItem.addClass("is-minimizing");

            this.animateDockTransfer($win, fromRect, toRect, windowData.cfg, "minimize", () => {
                $win.hide().css("visibility", "").addClass("dbx-window-is-minimized");
                windowData.minimized = true;
                $win.removeClass("dbx-window-active");
                $minimizeBtn.html('<i class="bi bi-plus"></i>');
                $dockItem.removeClass("is-minimizing").addClass("is-minimized");

                if (windowData.overlay) {
                    windowData.overlay.hide();
                }

                this.activateTopVisibleWindow(windowId);
                this.scheduleSaveState();
            });
        }

        initMaximize(windowId) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return;
            if (this.isMobileViewport()) return;

            const $win = windowData.element;
            const $maximizeBtn = $win.find(".dbx-window-maximize");

            if (!$maximizeBtn.length) return;

            $maximizeBtn.off('click.maximize').on('click.maximize', () => {

                // 🔥 FULLSCREEN MODE
                if (windowData.fullscreen) {

                    if (windowData.originalSize) {
                        $win.css({
                            width: windowData.originalSize.width,
                            height: windowData.originalSize.height,
                            left: windowData.originalPosition.left,
                            top: windowData.originalPosition.top
                        });
                    }

                    windowData.fullscreen = false;
                    $win.removeClass("dbx-window-fullscreen");

                    $maximizeBtn.html('<i class="bi bi-square"></i>');

                } else {

                    windowData.originalSize = {
                        width: $win.css('width'),
                        height: $win.css('height')
                    };

                    windowData.originalPosition = {
                        left: $win.css('left'),
                        top: $win.css('top')
                    };

                    $win.css({
                        width: '100%',
                        height: '100%',
                        left: '0',
                        top: '0'
                    });

                    windowData.fullscreen = true;
                    $win.addClass("dbx-window-fullscreen");

                    $maximizeBtn.html('<i class="bi bi-arrows-angle-contract"></i>');
                }

                this.scheduleSaveState();
            });
        }

        // --------------------------------------------------
        // CONTENT LOADING
        // --------------------------------------------------

        loadContent(windowId, url, cfg) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return;

            const $body = windowData.element.find(".dbx-window-body");
            const $target = this.getWindowInnerTarget(windowData);
            this.syncContentFlags(windowData);

            $target.html(`
                <div class="dbx-window-loading">
                    ${ui.loading}
                </div>
            `);

            if (!dbx.ajax || typeof dbx.ajax.request !== "function") {
                const errorMsg = cfg.errorMessage || `
                    <div class='dbx-window-error text-danger p-3'>
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>${ui.loadError}</strong><br>
                        <small>${ui.ajaxMissing}</small>
                    </div>`;
                $target.html(errorMsg);
                this.triggerCallback(cfg.onError, windowData.element[0], ui.ajaxMissing);
                return;
            }

            const requestConfig = {
                url: url,
                method: cfg.method || "GET",
                mode: "html",
                data: cfg.data || {},
                timeout: cfg.timeout || 30000
            };

            if (cfg.headers && typeof cfg.headers == 'object') {
                requestConfig.headers = cfg.headers;
            }

            dbx.ajax.request(requestConfig)
                .then(response => {
                    this.safeLog("loaded", url);
                    windowData.url = url;
                    windowData.cfg = $.extend(true, {}, windowData.cfg || {}, cfg || {});
                    $target.html(response);
                    this.syncContentFlags(windowData);

                    this.rescanContent($target[0]);
                    if (windowData.restoreState && windowData.restoreState.scrollTop) {
                        $body.scrollTop(windowData.restoreState.scrollTop);
                    }
                    this.triggerCallback(cfg.onLoad, windowData.element[0]);
                })
                .catch(error => {
                    const msg = error && error.message ? error.message : ui.unknownError;
                    this.safeWarn("load failed", url, msg);
                    const errorMsg = cfg.errorMessage || `
                        <div class='dbx-window-error text-danger p-3'>
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>${ui.loadError}</strong><br>
                            <small>${msg}</small>
                        </div>`;
                    $target.html(errorMsg);
                    this.triggerCallback(cfg.onError, windowData.element[0], error);
                });
        }

        getWindowInnerTarget(windowData) {
            const $body = windowData.element.find(".dbx-window-body");
            let $target = $body.children("[data-openwin-inner]").first();
            if (!$target.length) {
                $target = $(`<div class="dbx-window-inner" data-openwin-inner id="inner_${windowData.id}"></div>`);
                const existing = $body.contents().detach();
                $body.empty().append($target);
                if (existing.length) $target.append(existing);
            }
            return $target;
        }

        syncContentFlags(windowData) {
            if (!windowData || !windowData.element) return;
            const hasAce = windowData.element.find(".c-ace, [data-dbx*='lib=ace']").length > 0;
            windowData.element.toggleClass("dbx-window-has-ace", hasAce);
        }

        setWindowContent(windowId, cfg) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return null;
            cfg = this.normalizeConfig(cfg || {});
            const $target = this.getWindowInnerTarget(windowData);
            if (cfg.title !== undefined && cfg.title !== null) {
                const title = this.decodeConfigText(cfg.title);
                cfg.title = title;
                windowData.element.find(".dbx-window-title").html(title);
            }
            if (cfg.content !== undefined) {
                $target.empty().append(cfg.content);
            } else if (cfg.html !== undefined) {
                $target.html(cfg.html);
            } else {
                return null;
            }
            this.syncContentFlags(windowData);
            windowData.cfg = $.extend(true, {}, windowData.cfg || {}, cfg || {});
            this.rescanContent($target[0]);
            this.triggerCallback(cfg.onLoad, windowData.element[0]);
            this.bringToFront(windowId);
            return windowId;
        }

        replaceSelfWindowContent(el, cfg, url) {
            const $win = $(el).closest(".dbx-window");
            if (!$win.length) return null;
            const windowId = $win.attr("id");
            const windowData = this.windows.get(windowId);
            if (!windowData) return null;

            if (cfg.content !== undefined || cfg.html !== undefined) {
                return this.setWindowContent(windowId, cfg);
            }

            if (!url) return null;
            this.loadContent(windowId, url, cfg);
            this.bringToFront(windowId);
            return windowId;
        }

        rescanContent(element) {
            // dbx.rescan existiert?
            if (dbx.rescan && typeof dbx.rescan == 'function') {
                dbx.rescan(element);
            } else if (dbx.scan && typeof dbx.scan == 'function') {
                dbx.scan(element);
            }

            if (dbx.iconsEditor && typeof dbx.iconsEditor.rescan == 'function') {
                dbx.iconsEditor.rescan(element);
            }

            // Sonst nichts tun
        }

        // --------------------------------------------------
        // OVERLAY
        // --------------------------------------------------

        createOverlay(windowId) {
            const overlayId = `dbx_overlay_${windowId}`;

            const windowData = this.windows.get(windowId);
            if (!windowData) return;

            const winZ = parseInt(windowData.element.css('z-index'), 10) || CONFIG.BASE_Z_MODAL;
            const zIndex = winZ - 1;

            const $overlay = $(`
                <div id="${overlayId}" class="dbx-window-overlay" 
                    style="position:fixed; top:0; left:0; width:100%; height:100%; 
                            z-index:${zIndex};
                            display:none;">
                </div>
            `);

            $("body").append($overlay);
            $overlay.fadeIn(CONFIG.ANIMATION_DURATION);

            if (windowData.cfg.clickToClose == 1) {
                $overlay.on('click', () => {
                    this.closeWindow(windowId);
                });
            }

            windowData.overlay = $overlay;
        }

        // --------------------------------------------------
        // FENSTER ERSTELLEN
        // --------------------------------------------------

        createWindow(cfg, url, callerEl = null) {
            cfg = $.extend(true, {}, cfg || {});
            cfg = this.normalizeConfig(cfg);

            const restoreState = cfg.__restoreState || null;
            const isRestore = cfg.__restore == 1 || cfg.restore == 1;
            const restoreMinimized = cfg.__restoreMinimized == 1;

            delete cfg.__restoreState;
            delete cfg.__restore;
            delete cfg.__restoreMinimized;
            delete cfg.restore;

            if (this.stack.length >= CONFIG.MAX_WINDOWS) {
                this.safeWarn("maximum windows reached", CONFIG.MAX_WINDOWS);
                this.triggerCallback(cfg.onMaxReached);
                return null;
            }

            this.counter++;
            const windowId = `dbxWindow_${this.counter}_${Date.now()}`;

            const isModal = (cfg.modal == 1 || cfg.mode == "modal-fullscreen");
            const zIndex = this.getNextZIndex(isModal, cfg, callerEl);

            const title = this.decodeConfigText(cfg.title || ui.window);
            cfg.title = title;
            const footer = cfg.footer || '';
            const isMobileViewport = this.isMobileViewport();

            const showClose    = cfg.closable != 0;
            const showReload   = isMobileViewport ? false : this.optionEnabled(cfg.reloadable ?? cfg.reload, false);
            const showMinimize = isMobileViewport ? false : this.optionEnabled(cfg.minimizable ?? cfg.minimize, true);
            const showMaximize = isMobileViewport ? false : this.optionEnabled(cfg.maximizable ?? cfg.maximize, true);

            const scrollClass = (cfg.scroll == 0) ? 'dbx-window-no-scroll' : 'dbx-window-scroll';

            const customTools = isMobileViewport ? '' : (cfg.tools || '');

            const html = `
                <div class="dbx-window ${isModal ? 'dbx-window-modal' : 'dbx-window-draggable'} ${scrollClass}" 
                    id="${windowId}" 
                    style="z-index:${zIndex}; position:absolute; display:none;"
                    tabindex="-1"
                    role="dialog"
                    aria-label="">

                    <div class="dbx-window-header">
                        <span class="dbx-window-title">${title}</span>

                        <div class="dbx-window-controls">

                            ${customTools}

                            ${showReload ? '<span class="dbx-window-reload" data-action="reload" title="' + ui.reloadWindow + '" aria-label="' + ui.reloadWindow + '"><i class="bi bi-arrow-clockwise"></i></span>' : ''}
                            ${showMinimize ? '<span class="dbx-window-minimize"><i class="bi bi-dash"></i></span>' : ''}
                            ${showMaximize ? '<span class="dbx-window-maximize"><i class="bi bi-square"></i></span>' : ''}
                            ${showClose ? '<span class="dbx-window-close"><i class="bi bi-x"></i></span>' : ''}
                        </div>
                    </div>

                    <div class="dbx-window-body"><div class="dbx-window-inner" data-openwin-inner id="inner_${windowId}"></div></div>

                    ${footer ? `<div class="dbx-window-footer">${footer}</div>` : ''}

                    ${!isMobileViewport && cfg.resizable != 0 ? '<div class="dbx-window-resize"></div>' : ''}
                </div>
            `;

            $("body").append(html);
            const $win = $(`#${windowId}`);

            const windowData = {
                id: windowId,
                element: $win,
                cfg: cfg,
                url: url,
                fingerprint: this.getWindowFingerprint(cfg, url),
                callerEl: callerEl,
                isModal: isModal,
                minimized: false,
                maximized: false,
                fullscreen: false,
                originalSize: null,
                originalPosition: null,
                overlay: null,
                restoreState: restoreState,
                selectedValues: []
            };

            this.windows.set(windowId, windowData);
            this.stack.push(windowData);

            this.calculateWindowSize(windowData, cfg);
            if (restoreState) {
                this.applySavedWindowState(windowData, restoreState);
            }

            if (!isRestore && cfg.mode === "prompt" && callerEl && !isMobileViewport) {

                const rect = callerEl.getBoundingClientRect();
                const bounds = this.getViewportPageBounds();
                const viewportWidth = bounds.width;

                let left = rect.left + bounds.left;
                let top  = rect.bottom + bounds.top;

                let width  = $win.outerWidth();
                let height = $win.outerHeight();

                if (!cfg.width) {
                    width = Math.min(300, viewportWidth - 20);
                    $win.css("width", width + "px");
                }

                if (!cfg.height) {
                    $win.css("height", "auto");
                    height = $win.outerHeight();
                }

                if (left + width > bounds.right) {
                    left = bounds.right - width - 10;
                }

                if (top + height > bounds.bottom) {
                    top = rect.top + bounds.top - height;
                }

                left = Math.max(bounds.left + 10, left);
                top  = Math.max(bounds.top + 10, top);

                $win.css({
                    left: left,
                    top: top
                });
            }

            // 🔥 PROMPT: OUTSIDE CLICK CLOSE
            if (!isRestore && cfg.mode === "prompt") {

                setTimeout(() => {
                    $(document).on(`mousedown.${windowId}`, (ev) => {

                        const $target = $(ev.target);

                        if (
                            !$target.closest(`#${windowId}`).length &&
                            (!callerEl || (!$target.is(callerEl) && !$target.closest(callerEl).length))
                        ) {

                            this.closeWindow(windowId);
                        }

                    });
                }, 0);
            }

            if (
                !restoreState &&
                (
                    cfg.mode == "fullscreen" ||
                    cfg.mode == "modal-fullscreen" ||
                    cfg.position == "fullscreen"
                )
            ) {
                $win.css({
                    width: '100%',
                    height: '100%',
                    left: '0',
                    top: '0'
                });

                windowData.fullscreen = true;
                $win.addClass("dbx-window-fullscreen");

                $win.find(".dbx-window-maximize")
                    .html('<i class="bi bi-arrows-angle-contract"></i>');
            }

            if (isMobileViewport) {
                this.applyMobileWindowMode(windowData);
            }

            this.initWindowEvents(windowId, cfg);

            if (!isMobileViewport && cfg.draggable != 0) this.initDrag(windowId);
            if (!isMobileViewport && cfg.resizable != 0) this.initResize(windowId);
            if (!isMobileViewport && showMinimize) this.initMinimize(windowId);
            if (!isMobileViewport && showMaximize) this.initMaximize(windowId);
            if (isModal) this.createOverlay(windowId);

            if (cfg.content !== undefined || cfg.html !== undefined) {
                const $body = this.getWindowInnerTarget(windowData);
                if (cfg.content !== undefined) {
                    $body.empty().append(cfg.content);
                } else {
                    $body.html(cfg.html);
                }
                this.rescanContent($body[0]);
                this.triggerCallback(cfg.onLoad, $win[0]);
            } else {
                this.loadContent(windowId, url, cfg);
            }

            if (!isMobileViewport && restoreMinimized) {
                windowData.minimized = true;
                $win.hide().addClass("dbx-window-is-minimized");
                $win.find(".dbx-window-minimize").html('<i class="bi bi-plus"></i>');
                this.createDockItem(windowData);
                if (windowData.overlay) {
                    windowData.overlay.stop(true, true).hide();
                }
            } else {
                $win.css("display", "flex");
                if (isMobileViewport) {
                    this.applyMobileWindowMode(windowData);
                }
                this.bringToFront(windowId);
                this.clampWindowToViewport(windowData);

                if (!isRestore) {
                    this.animateElement($win, this.getAnimationName(cfg, "open"), cfg);

                    const $firstInput = $win.find("input,textarea,select").first();
                    if ($firstInput.length) {
                        $firstInput.focus();
                    } else {
                        $win.focus();
                    }
                }
            }

            if (!isRestore) {
                this.triggerCallback(cfg.onOpen, $win[0]);
                this.scheduleSaveState();
            }

            this.safeLog("window created", windowId, "modal:", isModal, "z-index:", zIndex);
            this.updateDockUi();

            return windowId;
        }

        calculateWindowSize(windowData, cfg) {
            const $win = windowData.element;
            const el = $win[0];

            const bounds = this.getViewportPageBounds();
            const viewportWidth = bounds.width;
            const viewportHeight = bounds.height;

            let width = this.parseSize(cfg.width, viewportWidth);
            let height = this.parseSize(cfg.height, viewportHeight);

            if (width == null || width == "auto") {
                width = Math.round(viewportWidth * CONFIG.DEFAULT_WIDTH);
            }
            if (height == null || height == "auto") {
                height = Math.round(viewportHeight * CONFIG.DEFAULT_HEIGHT);
            }

            const minWidth = this.parseSize(cfg.minWidth, viewportWidth, CONFIG.MIN_WIDTH);
            const maxWidth = this.parseSize(cfg.maxWidth, viewportWidth, viewportWidth);

            // 🔥 FIX: minHeight prüfen
            let minHeight;
            if (cfg.minHeight == undefined || cfg.minHeight == null) {
                minHeight = 200;
            } else {
                minHeight = this.parseSize(cfg.minHeight, viewportHeight, CONFIG.MIN_HEIGHT);
            }

            const maxHeight = this.parseSize(cfg.maxHeight, viewportHeight, viewportHeight);

            width = Math.min(Math.max(width, minWidth), maxWidth);
            height = Math.min(Math.max(height, minHeight), maxHeight);

            let left, top;

            if (cfg.position == 'center-top') {
                left = bounds.left + (viewportWidth - width) / 2;
                top = bounds.top + Math.max(24, Math.round(viewportHeight * 0.04));
            } else if (cfg.position == 'center') {
                left = bounds.left + (viewportWidth - width) / 2;
                top = bounds.top + (viewportHeight - height) / 2;
            } else if (cfg.position && typeof cfg.position == 'object') {
                left = bounds.left + this.parseSize(cfg.position.x, viewportWidth, 100);
                top = bounds.top + this.parseSize(cfg.position.y, viewportHeight, 100);
            } else {
                const offset = Math.min(this.stack.length * CONFIG.DRAG_OFFSET, 200);
                left = bounds.left + 100 + offset;
                top = bounds.top + 100 + offset;
            }

            left = Math.min(
                Math.max(left, bounds.left),
                Math.max(bounds.left, bounds.right - width)
            );
            top = Math.min(
                Math.max(top, bounds.top),
                Math.max(bounds.top, bounds.bottom - height)
            );

            $win.css({
                width: width + 'px',
                height: height + 'px',
                left: left + 'px',
                top: top + 'px'
            });

            // 🔥 WICHTIG: !important setzen
            if (cfg.minHeight == undefined || cfg.minHeight == null) {
                el.style.setProperty('min-height', '200px', 'important');
            }

            if (this.isMobileViewport()) {
                this.applyMobileWindowMode(windowData);
            }
        }

        initWindowEvents(windowId, cfg) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return;

            const $win = windowData.element;

            $win.off('mousedown.front').on('mousedown.front', () => {
                this.bringToFront(windowId);
            });

            $win.find(".dbx-window-close")
                .off('click.close touchend.close')
                .on('click.close touchend.close', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeWindow(windowId);
                });

            $win.attr('tabindex', '-1');
        }

        // --------------------------------------------------
        // FENSTER SCHLIESSEN
        // --------------------------------------------------

        closeWindow(windowId) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return;

            const $win = windowData.element;
            const cfg = windowData.cfg;

            this.safeLog("close", windowId);

            let shouldClose = true;
            if (cfg.onBeforeClose) {
                const result = this.triggerCallbackWithReturn(cfg.onBeforeClose, $win[0]);
                if (result == false) shouldClose = false;
            }

            if (!shouldClose) return;

            windowData.closing = true;
            this.removeDockItem(windowId);

            if (windowData.overlay) {
                windowData.overlay.fadeOut(CONFIG.ANIMATION_DURATION, () => {
                    windowData.overlay.remove();
                });
            }

            this.animateElement($win, this.getAnimationName(cfg, "close"), cfg, () => {
                $win.hide();
                $(document).off(`.${windowId}`);
                $win.off();
                $win.remove();

                const idx = this.stack.findIndex(w => w.id == windowId);
                if (idx != -1) this.stack.splice(idx, 1);

                this.windows.delete(windowId);
                this.updateAllZIndices();
                this.updateDockUi();
                this.scheduleSaveState();

                // --------------------------------------------------
                // 🔥 NEU: AFTER CLOSE ACTIONS
                // --------------------------------------------------

                if (cfg.afterClose) {

                    this.safeLog("afterClose", cfg.afterClose);

                    // reload dieses fensters (falls es noch existiert → skip)
                    if (cfg.afterClose === "reload") {
                        if (windowData.id && this.windows.has(windowData.id)) {
                            this.reloadWindow(windowData.id);
                        }
                    }

                    // reload alle fenster
                    if (cfg.afterClose === "reloadAll") {
                        this.getAllWindows().forEach(w => {
                            if (w && w.id) this.reloadWindow(w.id);
                        });
                    }

                    // custom callback
                    if (typeof cfg.afterClose === "string" && window[cfg.afterClose]) {
                        window[cfg.afterClose]();
                    }

                    if (typeof cfg.afterClose === "function") {
                        cfg.afterClose();
                    }
                }

                this.triggerCallback(cfg.onClose);
                this.safeLog("closed", windowId);
            });
        }

        resolve(windowId, value) {
            const windowData = this.windows.get(windowId);
            if (!windowData) return;

            const cfg = windowData.cfg;
            const callerEl = windowData.callerEl;

            this.safeLog("resolve", windowId, value);

            // 🔥 NORMALIZE
            let outValue = value;

            if (Array.isArray(value)) {
                outValue = value.map(v => v.value).join(",");
            } else if (value && typeof value === "object") {
                outValue = value.value;
            }

            // 🔥 PROMPT MODE
            if (cfg.mode === "prompt") {
                if (callerEl) {
                    if ("value" in callerEl) {
                        callerEl.value = outValue;
                    }
                    $(callerEl).trigger("change");
                }

                if (cfg.keepOpen != 1) {
                    this.closeWindow(windowId);
                }

                return;
            }

            // 🔥 EVENT SYSTEM
            if (cfg.event) {

                let name = cfg.event;

                if (cfg.target && cfg.target !== "broadcast") {
                    name = cfg.target + ":" + cfg.event;
                }

                dbx.event.emit(name, value);
            }
        }

        // --------------------------------------------------
        // ÖFFENTLICHE API
        // --------------------------------------------------

        openWindow(el, cfg) {
            el = this.domElement(el);
            cfg = this.normalizeConfig(cfg || {});
            if (cfg.title !== undefined && cfg.title !== null) {
                cfg.title = this.decodeConfigText(cfg.title);
            }

            let url = cfg.url || (el ? el.getAttribute("href") : null);
            if (!url && (cfg.content !== undefined || cfg.html !== undefined)) {
                url = "about:blank";
                cfg.ajax = 0;
                cfg.persist = 0;
                cfg.reloadable = 0;
                cfg.reload = 0;
            }
            if (!url) {
                this.safeWarn("no url");
                return null;
            }

            const mode = (cfg.mode || "panel").toLowerCase();

            if (String(cfg.target || "").toLowerCase() == "self") {
                const replaced = this.replaceSelfWindowContent(el, cfg, url);
                if (replaced) return replaced;
            }

            if (mode == "popup") {
                return this.openPopup(url, cfg);
            }

            if (cfg.reuse != 0 && cfg.allowDuplicate != 1) {
                const fingerprint = this.getWindowFingerprint(cfg, url);
                const existing = this.findReusableWindow(fingerprint);

                if (existing) {
                    this.safeLog("reuse window", existing.id, url);
                    this.restoreWindow(existing.id);
                    this.triggerCallback(cfg.onReuse, existing.element[0]);
                    return existing.id;
                }
            }

            return this.createWindow(cfg, url, el); // 🔥 NEU
        }

        openPopup(url, cfg) {
            const width = this.parseSize(cfg.width, screen.width, CONFIG.DEFAULT_POPUP_WIDTH);
            const height = this.parseSize(cfg.height, screen.height, CONFIG.DEFAULT_POPUP_HEIGHT);
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;

            const features = [
                `width=${width}`, `height=${height}`,
                `left=${left}`, `top=${top}`,
                cfg.resizable != 0 ? 'resizable=yes' : 'resizable=no',
                cfg.scrollbars != 0 ? 'scrollbars=yes' : 'scrollbars=no',
                'toolbar=no', 'menubar=no', 'location=no', 'status=no'
            ].join(',');

            const winName = cfg.windowName || `dbx_popup_${Date.now()}`;
            const win = window.open(url, winName, features);

            if (!win && cfg.fallback != 0) {
                if (cfg.fallback == 'redirect') {
                    if (window.dbx && dbx.utilities && dbx.utilities.leaveGuard) {
                        dbx.utilities.leaveGuard.allowIfInternal(url);
                    }
                    window.location.href = url;
                } else {
                    window.open(url, '_blank');
                }
            }

            win && win.focus();
            return win;
        }

        // Öffentliche Methoden
        getWindow(windowId) { return this.windows.get(windowId); }
        getAllWindows() { return Array.from(this.windows.values()); }
        closeAllWindows() { Array.from(this.windows.keys()).forEach(id => this.closeWindow(id)); }
        getWindowCount() { return this.windows.size; }

    }

    // --------------------------------------------------
    // GLOBALE INITIALISIERUNG
    // --------------------------------------------------

    function registerOpenWinFeature(windowManager) {
        if (!windowManager || !dbx.feature || !dbx.feature.register) return;
        if (dbx.feature.has && dbx.feature.has("openWin")) return;

        dbx.feature.register("openWin", {
            version: "2.1.0",

            scope: "global",

            css: [
                ['css', 'design', 'c-openWin.css']
            ],

            js: [
                ['js', 'lib', 'ajax.js']
            ],

            init: (el, cfg) => {

                el.__dbxInitialized = el.__dbxInitialized || {};
                if (el.__dbxInitialized["openWin"]) return;

                windowManager.restoreState();
            }
        });
    }

    if (!dbx.__openWinManager) {

        const windowManager = new WindowManager();
        dbx.__openWinManagerInstance = windowManager;

        // Öffentliche API
        dbx.openWin = {
            open: (cfg, el) => windowManager.openWindow(el || null, cfg),
            close: (windowId) => windowManager.closeWindow(windowId),
            closeAll: () => windowManager.closeAllWindows(),
            get: (windowId) => windowManager.getWindow(windowId),
            getAll: () => windowManager.getAllWindows(),
            getCount: () => windowManager.getWindowCount(),
            bringToFront: (windowId) => windowManager.bringToFront(windowId),
            popup: (url, cfg) => windowManager.openPopup(url, cfg || {}),
            saveState: () => windowManager.saveState(true),
            restoreState: (options) => windowManager.restoreState(options || {}),
            clearState: () => windowManager.clearState(),
            getState: () => windowManager.readState(),
            resolve: (windowId, value) => windowManager.resolve(windowId, value) // 🔥 NEU
        };

        // Feature registrieren
        registerOpenWinFeature(windowManager);

        setTimeout(() => windowManager.restoreState(), 120);

        dbx.__openWinManager = true;

        windowManager.safeLog("ready - modal z-index:", CONFIG.BASE_Z_MODAL, 
                             "dragable z-index:", CONFIG.BASE_Z_DRAGGABLE);
    }

    registerOpenWinFeature(dbx.__openWinManagerInstance);
    setTimeout(() => registerOpenWinFeature(dbx.__openWinManagerInstance), 0);
    setTimeout(() => registerOpenWinFeature(dbx.__openWinManagerInstance), 80);

    if (dbx.declare && typeof dbx.declare.registerSchema === "function") {

        dbx.declare.registerSchema("openWin", {
            fields: {
                url: {
                    default: "",
                    aliases: ["data-url"],
                    infer: function (el) {
                        return el && el.getAttribute ? (el.getAttribute("href") || "") : "";
                    }
                },
                title: {
                    default: ui.window,
                    aliases: ["data-title"],
                    infer: function (el) {
                        return el && el.getAttribute ? (el.getAttribute("title") || "") : "";
                    }
                },
                height: {
                    default: "760",
                    aliases: ["data-height", "data-size-y"]
                },
                width: {
                    default: "1280",
                    aliases: ["data-width", "data-size-x"]
                },
                sizeX: {
                    default: "",
                    aliases: ["data-size-x"]
                },
                sizeY: {
                    default: "",
                    aliases: ["data-size-y"]
                },
                modal: {
                    default: "0",
                    aliases: ["data-modal"]
                },
                scroll: {
                    default: "1",
                    aliases: ["data-scroll"]
                },
                position: {
                    default: "center",
                    aliases: ["data-position"]
                },
                minimizable: {
                    default: "1",
                    aliases: ["data-minimizable"]
                },
                maximizable: {
                    default: "1",
                    aliases: ["data-maximizable"]
                },
                resizable: {
                    default: "1",
                    aliases: ["data-resizable"]
                }
            }
        });

        dbx.declare.transforms.openWin = function (raw) {
            const width = raw.width || raw.sizeX || "1280";
            const height = raw.height || raw.sizeY || "760";
            return {
                lib: "openWin",
                url: String(raw.url || "").trim(),
                title: String(raw.title || ui.window).trim(),
                height: height,
                width: width,
                sizeX: raw.sizeX || width,
                sizeY: raw.sizeY || height,
                modal: raw.modal,
                scroll: raw.scroll,
                position: String(raw.position || "center").trim(),
                left: raw.left,
                top: raw.top,
                resizable: raw.resizable,
                minimizable: raw.minimizable,
                maximizable: raw.maximizable
            };
        };
    }

})(window, document, jQuery);
