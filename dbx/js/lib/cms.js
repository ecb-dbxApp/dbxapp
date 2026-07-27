/*!
 * dbxapp cms.js
 * Content CMS: tree, page editor, media upload.
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][cms] dbx core missing");
        return;
    }

    const dbx = window.dbx;
    const LIB = "cms";
    const TREE_UI_ID = "dbxContent_admin.contentTree";
    const PANEL_UI_ID = "dbxContent_admin.panels";

    function qs(root, sel) {
        return root ? root.querySelector(sel) : null;
    }

    function qsa(root, sel) {
        return root ? Array.from(root.querySelectorAll(sel)) : [];
    }

    function cmsMessages(root) {
        if (!root) return {};
        if (root._dbxCmsMessages) return root._dbxCmsMessages;
        try {
            root._dbxCmsMessages = JSON.parse(root.getAttribute("data-cms-messages") || "{}");
        } catch (ignore) {
            root._dbxCmsMessages = {};
        }
        return root._dbxCmsMessages;
    }

    function cmsText(root, key, fallback) {
        const messages = cmsMessages(root);
        return Object.prototype.hasOwnProperty.call(messages, key)
            ? String(messages[key])
            : String(fallback || key);
    }

    function cmsLanguage(root) {
        const language = String(root && root.getAttribute("data-cms-language") || "de").toLowerCase();
        return ["de", "en", "es"].includes(language) ? language : "de";
    }

    function closestElement(target, selector) {
        if (!target) return null;
        const el = target.nodeType === 1 ? target : target.parentElement;
        return el && el.closest ? el.closest(selector) : null;
    }

    function status(root, msg, type = "info") {
        const modal = state(root).mediaBrowser;
        const el = qs(root, "[data-cms-status]") || (modal ? qs(modal, "[data-cms-status]") : null);
        if (!el) {
            if (msg && type === "error") {
                dbx.warn("[cms]", msg);
            }
            return;
        }
        el.className = "dbx-cms-status dbx-cms-status-" + type;
        el.textContent = msg || "";
        if (msg) window.setTimeout(() => { el.textContent = ""; }, 3500);
    }

    function apiUrl(url, params) {
        const out = new URL(String(url || window.location.href), window.location.href);
        Object.keys(params || {}).forEach(key => out.searchParams.set(key, params[key]));
        out.searchParams.set("_", Date.now());
        return out.toString();
    }

    function fetchJson(url, opt) {
        opt = opt || {};
        if (!dbx.ajax || typeof dbx.ajax.request !== "function") {
            return Promise.reject(new Error("ajax.js nicht geladen."));
        }

        return dbx.ajax.request({
            url: url,
            method: opt.method || "GET",
            mode: "json",
            body: (typeof opt.body !== "undefined") ? opt.body : null,
            data: (typeof opt.data !== "undefined") ? opt.data : null,
            headers: opt.headers || {},
            timeout: Number(opt.timeout || 20000),
            footerRuntime: opt.footerRuntime || ""
        }).catch(err => {
            if (err && err.name === "AbortError") throw new Error("Serverantwort dauert zu lange.");
            throw err;
        });
    }

    function fetchHtml(url, opt) {
        opt = opt || {};
        if (!dbx.ajax || typeof dbx.ajax.request !== "function") {
            return Promise.reject(new Error("ajax.js nicht geladen."));
        }

        return dbx.ajax.request({
                url: url,
                method: opt.method || "GET",
                mode: "html",
                body: (typeof opt.body !== "undefined") ? opt.body : null,
                data: (typeof opt.data !== "undefined") ? opt.data : null,
                headers: opt.headers || {},
                timeout: Number(opt.timeout || 20000),
                footerRuntime: opt.footerRuntime || ""
            })
            .catch(err => {
                if (err && err.name === "AbortError") throw new Error("Serverantwort dauert zu lange.");
                throw err;
            });
    }

    function ensureFeature(name) {
        if (!name) return Promise.resolve(false);
        if (dbx.feature && dbx.feature.has && dbx.feature.has(name)) {
            return Promise.resolve(true);
        }

        return new Promise(resolve => {
            if (!dbx.resolveFeature) {
                resolve(false);
                return;
            }

            dbx.resolveFeature(name, ok => resolve(ok === true));
        });
    }

    function loadAssets(list) {
        return new Promise(resolve => {
            if (!Array.isArray(list) || !list.length) {
                resolve(true);
                return;
            }

            if (!dbx.load) {
                resolve(false);
                return;
            }

            dbx.load(list, () => resolve(true));
        });
    }

    function ensureOpenWin() {
        return ensureFeature("openWin").then(ok => {
            return ok && dbx.openWin && typeof dbx.openWin.open === "function";
        });
    }

    function ensureConfirm() {
        return ensureFeature("confirm").then(ok => {
            return ok && dbx.confirm && typeof dbx.confirm.open === "function";
        });
    }

    function syncCmsSelectTitle(target) {
        const select = closestElement(target, "select[data-cms-select-title]");
        if (!select) return;
        const option = select.options && select.selectedIndex >= 0
            ? select.options[select.selectedIndex]
            : null;
        select.title = option ? String(option.text || "") : "";
    }

    function ensureAjax() {
        return ensureFeature("ajax").then(ok => {
            return ok && dbx.ajax && typeof dbx.ajax.request === "function";
        });
    }

    function ensureJodit() {
        if (window.Jodit && window.Jodit.make) {
            return Promise.resolve(true);
        }

        return loadAssets([
            ["css", "root", "vendor/jodit/jodit.fat.min.css"],
            ["js", "root", "vendor/jodit/jodit.fat.min.js"]
        ]).then(() => !!(window.Jodit && window.Jodit.make));
    }

    function extractProcessHtml(html) {
        const tpl = document.createElement("template");
        tpl.innerHTML = String(html || "").trim();
        const proc = tpl.content.querySelector("[data-dbx-process-root='1'], .dbx-process");
        return proc ? proc.outerHTML : String(html || "");
    }

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text == null ? "" : String(text);
        return div.innerHTML;
    }

    function escapeTooltipAttr(text) {
        return escapeHtml(String(text == null ? "" : text).replace(/"/g, "'"));
    }

    function escapeTextareaValue(text) {
        return String(text == null ? "" : text).replace(/<\/textarea/gi, "&lt;/textarea");
    }

    function fillLngProvisionContentPreviews(modal, items) {
        if (!modal || !Array.isArray(items)) return;
        items.forEach((item, index) => {
            const preview = qs(modal, `[data-lng-content-preview="${index}"]`);
            if (!preview) return;
            const html = String(item && item.content != null ? item.content : "").trim();
            preview.innerHTML = html;
            if (!html) {
                preview.classList.add("is-empty");
            }
        });
    }

    function state(root) {
        root.__dbxCms = root.__dbxCms || {
            tree: [],
            flat: [],
            selectedFolder: Number(dbx.uiGet ? dbx.uiGet(LIB, TREE_UI_ID, "selectedFolder", 0) : 0) || 0,
            folder: null,
            selectedPage: Number(dbx.uiGet ? dbx.uiGet(LIB, TREE_UI_ID, "selectedPage", 0) : 0) || 0,
            selectedType: dbx.uiGet ? String(dbx.uiGet(LIB, TREE_UI_ID, "selectedType", "page") || "page") : "page",
            page: null,
            mediaRows: [],
            mediaFilter: "all",
            collapsedFolders: null,
            selectionRestored: false,
            dirty: false,
            loading: false,
            saving: false,
            duplicating: false,
            pageLoadSeq: 0
        };
        return root.__dbxCms;
    }

    function cfgUrl(cfg, key) {
        return cfg[key] || "";
    }

    function browserCfg(modal) {
        return modal && modal.__dbxCmsCfg ? modal.__dbxCmsCfg : {};
    }

    /**
     * Liest ein serverseitig durch dbxForm gerendertes Medienformular.
     * Der Medienbrowser erzeugt dadurch weder Form-Struktur noch Security-
     * Felder selbst.
     */
    function mediaBrowserFormHtml(root, selector) {
        const template = qs(root, selector);
        return template ? String(template.innerHTML || "").trim() : "";
    }

    /**
     * Uebernimmt den nach jedem JSON-Submit rotierten dbxForm-Token.
     */
    function applyFormSecurity(form, data) {
        const security = data && data.form_security;
        if (!form || !security || !security.name || !security.value) return;
        const input = qs(form, `input[name="${String(security.name).replace(/"/g, '\\"')}"]`);
        if (input) {
            input.value = String(security.value);
            // form.reset() muss ebenfalls auf den rotierten Token und nicht
            // auf den beim ersten Rendern gesetzten Wert zurueckgehen.
            input.defaultValue = String(security.value);
        }
    }

    function mediaBrowserProfile(cfg) {
        const media = String(cfgUrl(cfg, "media") || "");
        if (media.indexOf("modul_images_media") >= 0) return "mod";
        return "cms";
    }

    function applyMediaBrowserProfile(modal, cfg) {
        const isMod = mediaBrowserProfile(cfg) === "mod";
        modal.dataset.dbxMediaProfile = isMod ? "mod" : "cms";
        const folderBar = qs(modal, ".dbx-cms-media-folderbar");
        const externalVideo = qs(modal, "[data-cms-browser-external-video]");
        const slotSelect = qs(modal, "[data-cms-media-browser-slot]");
        const uploadFolder = qs(modal, "[data-cms-upload-folder]");
        const folderSelect = qs(modal, "[data-cms-media-browser-folder]");
        const folderTree = qs(modal, "[data-cms-media-folder-tree]");
        const batchBtn = qs(modal, "[data-cms-media-batch-open]");
        const rootHint = qs(modal, "[data-cms-media-root-hint]");
        if (rootHint) {
            rootHint.textContent = isMod ? "Speicherort: files/mod/" : "Speicherort: files/media/";
        }
        if (folderBar) folderBar.hidden = isMod;
        if (externalVideo) externalVideo.hidden = isMod;
        if (slotSelect) slotSelect.hidden = isMod;
        if (uploadFolder) uploadFolder.hidden = isMod;
        if (folderSelect) folderSelect.hidden = isMod;
        if (folderTree) folderTree.hidden = isMod || !modal.classList.contains("is-folder-tree-open");
        if (isMod) modal.classList.remove("is-folder-tree-open");
        if (batchBtn) batchBtn.hidden = isMod;
    }

    function isMediaBrowserMulti(modal) {
        const mode = modal && modal.__dbxCmsMediaMode ? modal.__dbxCmsMediaMode : "";
        if (modal && modal.__dbxCmsSinglePick) return false;
        return mode === "pick" || (mode === "assign" && modal.__dbxCmsAssignSlot !== "hero");
    }

    function mediaBrowserUsesConfirmBar(modal) {
        const mode = modal && modal.__dbxCmsMediaMode ? modal.__dbxCmsMediaMode : "";
        return mode === "pick" || mode === "assign";
    }

    function confirmPickMediaBrowser(root, modal) {
        const rows = selectedMediaBrowserRows(modal);
        if (!rows.length) {
            status(root, "Bitte mindestens ein Bild auswaehlen.", "error");
            return Promise.resolve();
        }

        let chain = Promise.resolve();
        let keepOpen = false;
        rows.forEach(row => {
            chain = chain.then(() => {
                if (typeof modal.__dbxCmsAfterAssign === "function") {
                    return Promise.resolve(modal.__dbxCmsAfterAssign(row)).then(result => {
                        if (result === false) keepOpen = true;
                    });
                }
            });
        });
        return chain.then(() => {
            if (keepOpen) {
                clearCmsLoading(root);
                return;
            }
            modal.hidden = true;
            if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.close === "function") {
                dbx.openWin.close(modal.__dbxCmsWindowId);
            }
            clearCmsLoading(root);
            status(root, "Auswahl uebernommen.", "success");
        });
    }

    function isViewMode(cfg) {
        return String((cfg && cfg.mode) || "").toLowerCase() === "view";
    }

    function syncStickyHeaderOffset(root) {
        const header = document.getElementById("dbxHeader");
        const headerMenus = header ? qsa(header, ".dbx-menu").filter(el => el.parentElement === header) : [];
        const visibleBottom = headerMenus.reduce((bottom, el) => {
            const style = window.getComputedStyle ? window.getComputedStyle(el) : null;
            if (style && (style.display === "none" || style.visibility === "hidden")) return bottom;
            const rect = el.getBoundingClientRect();
            if (!rect.height || rect.bottom <= 0) return bottom;
            return Math.max(bottom, rect.bottom);
        }, 0);
        const headerBottom = header ? Math.max(0, header.getBoundingClientRect().bottom || 0) : 0;
        const fallbackBottom = Math.min(visibleBottom || 0, 160);
        const height = header ? Math.ceil(Math.min(headerBottom || fallbackBottom || 0, 120)) : 0;
        root.style.setProperty("--dbx-cms-sticky-top", height + "px");

        const cmsHead = qs(root, ".dbx-cms-head");
        const cmsHeadStyle = cmsHead && window.getComputedStyle ? window.getComputedStyle(cmsHead) : null;
        let cmsHeadTop = cmsHeadStyle ? parseFloat(cmsHeadStyle.top || "0") : 0;
        if (!Number.isFinite(cmsHeadTop)) cmsHeadTop = 0;
        const cmsHeadHeight = cmsHead ? Math.ceil(cmsHead.getBoundingClientRect().height || cmsHead.offsetHeight || 0) : 0;
        const editorTop = cmsHead ? Math.ceil(cmsHeadTop + cmsHeadHeight) : height;
        root.style.setProperty("--dbx-cms-editor-toolbar-top", editorTop + "px");
        const instance = getEditorInstance(root);
        if (instance && instance.o) instance.o.toolbarStickyOffset = editorTop;
    }

    function bindStickyHeaderOffset(root) {
        if (root.__dbxCmsStickyOffsetBound) return;
        root.__dbxCmsStickyOffsetBound = true;
        syncStickyHeaderOffset(root);
        window.setTimeout(() => syncStickyHeaderOffset(root), 50);
        window.setTimeout(() => syncStickyHeaderOffset(root), 300);
        window.addEventListener("resize", () => syncStickyHeaderOffset(root), { passive: true });
    }

    function syncTreeFlyoutPosition(root) {
        if (!root || root.classList.contains("is-tree-collapsed")) return;
        const btn = qs(root, ".dbx-cms-head [data-cms-action='toggle-tree-panel']");
        const panel = isViewMode(cmsConfig(root)) ? qs(root, ".dbx-cms-view-tree-panel") : qs(root, ".dbx-cms-tree-panel");
        if (!btn || !panel) return;

        const rect = btn.getBoundingClientRect();
        const gap = 6;
        const minEdge = 12;
        const maxWidth = isViewMode(cmsConfig(root)) ? 720 : 600;
        const left = Math.max(minEdge, Math.min(rect.left, window.innerWidth - minEdge - maxWidth));
        const width = Math.min(maxWidth, window.innerWidth - left - minEdge);
        const top = Math.min(rect.bottom + gap, window.innerHeight - 80);

        root.style.setProperty("--dbx-cms-tree-left", Math.round(left) + "px");
        root.style.setProperty("--dbx-cms-tree-top", Math.round(top) + "px");
        root.style.setProperty("--dbx-cms-tree-viewport-top", Math.round(top) + "px");
        root.style.setProperty("--dbx-cms-tree-width", Math.round(width) + "px");

        /*
         * backdrop-filter/transform an einem umgebenden App-Container kann
         * fuer position:fixed einen eigenen Bezugspunkt erzeugen. rect ist
         * dagegen immer viewportbezogen. Nach dem ersten Layout gleichen wir
         * genau diesen Versatz aus, damit der Tree unter seinem Schalter sitzt.
         */
        const placed = panel.getBoundingClientRect();
        const correctedLeft = left + (left - placed.left);
        const correctedTop = top + (top - placed.top);
        if (Number.isFinite(correctedLeft) && Math.abs(placed.left - left) > 0.5) {
            root.style.setProperty("--dbx-cms-tree-left", Math.round(correctedLeft) + "px");
        }
        if (Number.isFinite(correctedTop) && Math.abs(placed.top - top) > 0.5) {
            root.style.setProperty("--dbx-cms-tree-top", Math.round(correctedTop) + "px");
        }
    }

    function bindTreeFlyoutPosition(root) {
        if (root.__dbxCmsTreeFlyoutBound) return;
        root.__dbxCmsTreeFlyoutBound = true;

        const update = () => syncTreeFlyoutPosition(root);
        window.addEventListener("resize", update, { passive: true });
        window.addEventListener("scroll", update, { passive: true });

        const scroller = closestElement(root, ".dbx-content");
        if (scroller) scroller.addEventListener("scroll", update, { passive: true });
    }

    function applyTreePanelState(root, collapsed) {
        root.classList.toggle("is-tree-collapsed", !!collapsed);
        root.classList.remove("is-tree-peek");
        if (!collapsed) clearTreeForceCollapse(root);
        qsa(root, "[data-cms-action='toggle-tree-panel']").forEach(btn => {
            btn.setAttribute("aria-expanded", collapsed ? "false" : "true");
            btn.setAttribute("aria-label", collapsed
                ? cmsText(root, "tree_show", "Content-Baum anzeigen")
                : cmsText(root, "tree_hide", "Content-Baum ausblenden"));
            btn.setAttribute("title", collapsed
                ? cmsText(root, "tree_show", "Content-Baum anzeigen")
                : cmsText(root, "tree_hide", "Content-Baum ausblenden"));
            const icon = qs(btn, "i");
            if (icon) {
                icon.classList.toggle("bi-list", collapsed);
                icon.classList.toggle("bi-x-lg", !collapsed);
                icon.classList.toggle("bi-layout-sidebar-inset", false);
            }
        });
        if (!collapsed) {
            syncTreeFlyoutPosition(root);
            window.requestAnimationFrame(() => syncTreeFlyoutPosition(root));
            window.setTimeout(() => syncTreeFlyoutPosition(root), 0);
        }
    }

    function clearTreeForceCollapse(root) {
        if (root.__dbxCmsTreeForceTimer) {
            window.clearTimeout(root.__dbxCmsTreeForceTimer);
            root.__dbxCmsTreeForceTimer = null;
        }
        root.classList.remove("is-tree-force-collapsed");
    }

    function forceCollapseTreePanel(root) {
        root.classList.add("is-tree-force-collapsed");
        if (root.__dbxCmsTreeForceTimer) window.clearTimeout(root.__dbxCmsTreeForceTimer);
        root.__dbxCmsTreeForceTimer = window.setTimeout(() => {
            root.__dbxCmsTreeForceTimer = null;
            if (root.classList.contains("is-tree-collapsed")) {
                root.classList.remove("is-tree-force-collapsed");
            }
        }, 220);
    }

    function clearTreeHoverTimers(root) {
        if (root.__dbxCmsTreeHoverEnterTimer) {
            window.clearTimeout(root.__dbxCmsTreeHoverEnterTimer);
            root.__dbxCmsTreeHoverEnterTimer = null;
        }
        if (root.__dbxCmsTreeHoverLeaveTimer) {
            window.clearTimeout(root.__dbxCmsTreeHoverLeaveTimer);
            root.__dbxCmsTreeHoverLeaveTimer = null;
        }
    }

    function setTreeHoverExpanded(root, expanded) {
        if (!root) return;
        root.classList.toggle("is-tree-panel-hover", !!expanded);
        if (isViewMode(cmsConfig(root)) && root.classList.contains("is-tree-collapsed")) {
            if (expanded && !root.classList.contains("is-tree-force-collapsed")) {
                root.classList.add("is-tree-peek");
            } else {
                root.classList.remove("is-tree-peek");
            }
        }
    }

    function scheduleTreeHover(root, expanded) {
        if (!root) return;
        const delay = 700;
        if (expanded) {
            if (root.__dbxCmsTreeHoverLeaveTimer) {
                window.clearTimeout(root.__dbxCmsTreeHoverLeaveTimer);
                root.__dbxCmsTreeHoverLeaveTimer = null;
            }
            if (root.classList.contains("is-tree-panel-hover")) return;
            if (root.__dbxCmsTreeHoverEnterTimer) window.clearTimeout(root.__dbxCmsTreeHoverEnterTimer);
            root.__dbxCmsTreeHoverEnterTimer = window.setTimeout(() => {
                root.__dbxCmsTreeHoverEnterTimer = null;
                setTreeHoverExpanded(root, true);
            }, delay);
            return;
        }

        if (root.__dbxCmsTreeHoverEnterTimer) {
            window.clearTimeout(root.__dbxCmsTreeHoverEnterTimer);
            root.__dbxCmsTreeHoverEnterTimer = null;
        }
        if (!root.classList.contains("is-tree-panel-hover") && !root.classList.contains("is-tree-peek")) return;
        if (root.__dbxCmsTreeHoverLeaveTimer) window.clearTimeout(root.__dbxCmsTreeHoverLeaveTimer);
        root.__dbxCmsTreeHoverLeaveTimer = window.setTimeout(() => {
            root.__dbxCmsTreeHoverLeaveTimer = null;
            setTreeHoverExpanded(root, false);
        }, delay);
    }

    function initTreePanelState(root, cfg) {
        const defaultCollapsed = isViewMode(cfg || {});
        const collapsed = !!(dbx.uiGet ? dbx.uiGet(LIB, PANEL_UI_ID, "treeCollapsed", defaultCollapsed) : defaultCollapsed);
        applyTreePanelState(root, collapsed);
    }

    function applyRightPanelState(root, collapsed) {
        const panel = qs(root, "[data-cms-right-panel]");
        if (!panel) return;

        root.classList.toggle("is-right-panel-collapsed", !!collapsed);
        qsa(root, "[data-cms-action='toggle-right-panel']").forEach(btn => {
            btn.setAttribute("aria-expanded", collapsed ? "false" : "true");
            btn.setAttribute("aria-label", collapsed
                ? cmsText(root, "right_show", "Rechte Spalte einblenden")
                : cmsText(root, "right_hide", "Rechte Spalte ausblenden"));
            btn.setAttribute("title", collapsed
                ? cmsText(root, "right_show", "Rechte Spalte einblenden")
                : cmsText(root, "right_hide", "Rechte Spalte ausblenden"));
            const icon = qs(btn, "i");
            if (icon) {
                icon.classList.toggle("bi-list", !!collapsed);
                icon.classList.toggle("bi-chevron-bar-right", !collapsed);
            }
        });
    }

    function initRightPanelState(root) {
        if (!qs(root, "[data-cms-right-panel]")) return;
        const collapsed = !!(dbx.uiGet ? dbx.uiGet(LIB, PANEL_UI_ID, "rightPanelCollapsed", false) : false);
        applyRightPanelState(root, collapsed);
    }

    function scrollCmsToTop(root) {
        const windowBody = closestElement(root, ".dbx-window-body");
        const appContent = closestElement(root, ".dbx-content");
        const scrollHost = windowBody || appContent;

        if (scrollHost && typeof scrollHost.scrollTo === "function") {
            scrollHost.scrollTo({ top: 0, behavior: "smooth" });
            return;
        }
        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function toggleRightPanel(root, trigger) {
        const collapsed = !root.classList.contains("is-right-panel-collapsed");
        applyRightPanelState(root, collapsed);
        if (dbx.uiSet) dbx.uiSet(LIB, PANEL_UI_ID, "rightPanelCollapsed", collapsed);
        if (!collapsed && trigger && trigger.classList.contains("dbx-cms-right-panel-toggle-bottom")) {
            window.requestAnimationFrame(() => scrollCmsToTop(root));
        }
    }

    function toggleTreePanel(root) {
        const collapsed = !root.classList.contains("is-tree-collapsed");
        clearTreeHoverTimers(root);
        setTreeHoverExpanded(root, false);
        applyTreePanelState(root, collapsed);
        if (dbx.uiSet) dbx.uiSet(LIB, PANEL_UI_ID, "treeCollapsed", collapsed);
    }

    function closeTreePanel(root) {
        if (!root || root.classList.contains("is-tree-collapsed")) return;
        clearTreeHoverTimers(root);
        setTreeHoverExpanded(root, false);
        applyTreePanelState(root, true);
        if (dbx.uiSet) dbx.uiSet(LIB, PANEL_UI_ID, "treeCollapsed", true);
    }

    function bindAdminTreeOutsideClose(root, cfg) {
        if (!root || isViewMode(cfg || {}) || root.__dbxCmsAdminTreeOutsideCloseBound) return;
        root.__dbxCmsAdminTreeOutsideCloseBound = true;

        document.addEventListener("click", e => {
            if (root.classList.contains("is-tree-collapsed")) return;
            const target = e.target;
            const panel = qs(root, ".dbx-cms-tree-panel");
            const toggle = closestElement(target, "[data-cms-action='toggle-tree-panel']");
            if (toggle && root.contains(toggle)) return;
            if (panel && panel.contains(target)) return;
            closeTreePanel(root);
        }, true);
    }

    function bindViewTreeHover(root, cfg) {
        if (!isViewMode(cfg) || root.__dbxCmsViewTreeHoverBound) return;
        root.__dbxCmsViewTreeHoverBound = true;

        const treePanel = qs(root, ".dbx-cms-view-tree-panel");
        const viewPanel = qs(root, ".dbx-cms-view-panel");

        if (treePanel) {
            treePanel.addEventListener("mouseenter", () => {
                if (root.classList.contains("is-tree-collapsed") && !root.classList.contains("is-tree-force-collapsed")) scheduleTreeHover(root, true);
            });
            treePanel.addEventListener("mouseleave", () => {
                scheduleTreeHover(root, false);
            });
        }

        if (viewPanel) {
            viewPanel.addEventListener("mouseenter", () => {
                clearTreeHoverTimers(root);
                setTreeHoverExpanded(root, false);
                applyTreePanelState(root, true);
                clearTreeForceCollapse(root);
                if (dbx.uiSet) dbx.uiSet(LIB, PANEL_UI_ID, "treeCollapsed", true);
            });
        }

        document.addEventListener("pointermove", e => {
            if (!root.classList.contains("is-tree-force-collapsed")) return;
            if (treePanel && treePanel.contains(e.target)) return;
            clearTreeForceCollapse(root);
        }, true);

        root.addEventListener("mouseover", e => {
            if (!root.classList.contains("is-tree-collapsed")) return;
            if (treePanel && treePanel.contains(e.target)) {
                if (root.classList.contains("is-tree-force-collapsed")) return;
                scheduleTreeHover(root, true);
                return;
            }
            if (viewPanel && viewPanel.contains(e.target)) {
                clearTreeHoverTimers(root);
                setTreeHoverExpanded(root, false);
                applyTreePanelState(root, true);
                clearTreeForceCollapse(root);
                if (dbx.uiSet) dbx.uiSet(LIB, PANEL_UI_ID, "treeCollapsed", true);
            }
        });
    }

    function getCollapsedFolders(root) {
        const s = state(root);
        if (s.collapsedFolders && typeof s.collapsedFolders === "object") {
            return s.collapsedFolders;
        }
        const saved = dbx.uiGet ? dbx.uiGet(LIB, TREE_UI_ID, "collapsedFolders", {}) : {};
        s.collapsedFolders = (saved && typeof saved === "object" && !Array.isArray(saved)) ? saved : {};
        return s.collapsedFolders;
    }

    function setFolderCollapsed(root, id, collapsed) {
        const folders = getCollapsedFolders(root);
        const key = String(id || "");
        if (!key) return;
        if (collapsed) {
            folders[key] = true;
        } else {
            delete folders[key];
        }
        if (dbx.uiSet) dbx.uiSet(LIB, TREE_UI_ID, "collapsedFolders", folders);
    }

    function isFolderCollapsed(root, id, search) {
        if (search) return false;
        const folders = getCollapsedFolders(root);
        return folders[String(id || "")] === true;
    }

    function setSelectedFolder(root, id) {
        const s = state(root);
        s.selectedFolder = Number(id || 0);
        if (dbx.uiSet) dbx.uiSet(LIB, TREE_UI_ID, "selectedFolder", s.selectedFolder);
    }

    function setSelectedPage(root, id) {
        const s = state(root);
        s.selectedPage = Number(id || 0);
        if (dbx.uiSet) dbx.uiSet(LIB, TREE_UI_ID, "selectedPage", s.selectedPage);
    }

    function setSelectedType(root, type) {
        const s = state(root);
        s.selectedType = type === "folder" ? "folder" : "page";
        if (dbx.uiSet) dbx.uiSet(LIB, TREE_UI_ID, "selectedType", s.selectedType);
        updateHeaderActionTooltips(root);
    }

    function revealTreeSelection(root) {
        const s = state(root);
        const type = s.selectedType === "folder" ? "folder" : "page";
        const id = type === "folder" ? s.selectedFolder : s.selectedPage;
        if (!id) {
            renderTree(root);
            return;
        }

        let node = findNode(root, type, id);
        let parent = Number(node && node._parent || 0);
        const seen = new Set();
        while (parent > 0 && !seen.has(parent)) {
            seen.add(parent);
            setFolderCollapsed(root, parent, false);
            node = findNode(root, "folder", parent);
            parent = Number(node && node._parent || 0);
        }

        renderTree(root);
        window.requestAnimationFrame(() => {
            const row = qs(root, ".dbx-cms-tree-row.is-active");
            if (row && row.scrollIntoView) {
                row.scrollIntoView({ block: "nearest", inline: "nearest" });
            }
        });
    }

    function openPreview(root) {
        const id = Number(getField(root, "id") || 0);
        if (!id) {
            status(root, "Bitte zuerst eine Seite auswaehlen.", "error");
            return;
        }

        const title = getField(root, "title") || "Content Vorschau";
        const url = "?dbx_modul=dbxContent&dbx_run1=show&dbx_cid=" + encodeURIComponent(id) + "&dbx_window=1";
        const cfg = {
            url: url,
            title: "Vorschau: " + title,
            width: 1280,
            height: 820,
            modal: 0,
            ajax: 1,
            scroll: 1,
            position: "center",
            reloadable: 1,
            reuse: 1,
            allowDuplicate: 0
        };

        ensureOpenWin().then(ok => {
            if (ok) {
                dbx.openWin.open(cfg, root);
            } else {
                status(root, "openWin.js nicht geladen.", "error");
            }
        });
    }

    function getEditorInstance(root) {
        return root && root.__dbxCmsJodit ? root.__dbxCmsJodit : null;
    }

    function editorHeightGroup(root) {
        return qs(root, ".dbx-cms-editor-group");
    }

    function syncEditorHeight(root) {
        const group = editorHeightGroup(root);
        const surface = editorSurface(root);
        if (!group || !surface) return;

        const instance = getEditorInstance(root);
        const container = instance && instance.container ? instance.container : qs(root, ".jodit-container");
        const workplace = qs(root, ".jodit-workplace");
        const source = qs(root, ".jodit-source textarea, .jodit-source");
        const sized = [container, workplace, surface, source].filter(Boolean);

        if (container && container.classList && container.classList.contains("jodit_fullsize")) {
            sized.forEach(el => {
                if (!el.style) return;
                el.style.removeProperty("height");
                el.style.removeProperty("max-height");
                el.style.removeProperty("min-height");
            });
            return;
        }

        sized.forEach(el => {
            if (!el.style) return;
            el.style.height = "auto";
            el.style.maxHeight = "none";
            el.style.minHeight = "0px";
        });

        const base = 430;
        const contentHeight = Math.ceil(Math.max(
            surface.scrollHeight || 0,
            source ? (source.scrollHeight || 0) : 0
        ));
        const nextHeight = Math.max(base, contentHeight);
        const value = nextHeight + "px";

        group.style.setProperty("--dbx-cms-editor-min-height", value);
        sized.forEach(el => {
            if (!el.style) return;
            el.style.minHeight = value;
        });
    }

    function scheduleEditorHeight(root) {
        if (!root) return;
        if (root.__dbxCmsEditorHeightFrame) {
            window.cancelAnimationFrame(root.__dbxCmsEditorHeightFrame);
        }
        root.__dbxCmsEditorHeightFrame = window.requestAnimationFrame(() => {
            root.__dbxCmsEditorHeightFrame = null;
            syncEditorHeight(root);
        });
        [80, 250, 800].forEach(delay => window.setTimeout(() => syncEditorHeight(root), delay));
    }

    function bindEditorHeight(root) {
        if (!root || root.__dbxCmsEditorHeightBound) return;
        root.__dbxCmsEditorHeightBound = true;

        window.addEventListener("resize", () => scheduleEditorHeight(root), { passive: true });
        root.addEventListener("input", e => {
            const surface = editorSurface(root);
            if (surface && (e.target === surface || surface.contains(e.target))) {
                scheduleEditorHeight(root);
            }
        }, true);
        root.addEventListener("load", e => {
            const surface = editorSurface(root);
            if (surface && e.target && surface.contains(e.target)) {
                scheduleEditorHeight(root);
            }
        }, true);
    }

    function setDirty(root, dirty) {
        const s = state(root);
        s.dirty = !!dirty;
        root.classList.toggle("is-dirty", s.dirty);

        const el = qs(root, "[data-cms-dirty-state]");
        if (!el) return;

        const text = el.querySelector("span");
        if (text) text.textContent = s.dirty
            ? cmsText(root, "unsaved", "Ungespeichert")
            : cmsText(root, "saved", "Gespeichert");
        el.setAttribute("title", s.dirty
            ? cmsText(root, "unsaved_title", "Ungespeicherte Änderungen")
            : cmsText(root, "saved_title", "Alle Änderungen gespeichert"));
    }

    function setSaving(root, saving) {
        const s = state(root);
        s.saving = !!saving;
        root.classList.toggle("is-saving", s.saving);
        root.setAttribute("aria-busy", s.saving ? "true" : "false");

        qsa(root, "[data-cms-action='save'], [data-cms-action='save-settings'], [data-cms-action='save-folder']").forEach(btn => {
            btn.disabled = s.saving;
            btn.classList.toggle("is-saving", s.saving);
        });
    }

    function updateHeaderActionTooltips(root) {
        if (!root) return;
        const isFolder = root.classList.contains("is-folder-editing");
        const saveTitle = isFolder
            ? cmsText(root, "folder_save_title", "Ordner speichern")
            : cmsText(root, "page_save_title", "Seite speichern");
        const deleteTitle = isFolder
            ? cmsText(root, "folder_delete_title", "Ordner löschen")
            : cmsText(root, "page_delete_title", "Seite löschen");
        const saveBtn = qs(root, ".dbx-cms-head [data-cms-action='save']");
        const deleteBtn = qs(root, ".dbx-cms-head [data-cms-action='delete']");
        const duplicateBtn = qs(root, ".dbx-cms-head [data-cms-action='duplicate-page']");
        const lngBtn = qs(root, ".dbx-cms-head [data-cms-action='lng-provision']");
        const lngResetBtn = qs(root, ".dbx-cms-head [data-cms-action='lng-reset-sync']");

        if (saveBtn) {
            saveBtn.setAttribute("title", saveTitle);
            saveBtn.setAttribute("aria-label", saveTitle);
        }
        if (deleteBtn) {
            deleteBtn.setAttribute("title", deleteTitle);
            deleteBtn.setAttribute("aria-label", deleteTitle);
        }
        if (duplicateBtn) {
            const canDuplicate = !isFolder && state(root).selectedType === "page" && !state(root).duplicating;
            duplicateBtn.disabled = !canDuplicate;
            duplicateBtn.setAttribute("title", canDuplicate
                ? cmsText(root, "duplicate_title", "Ausgewählte Seite duplizieren")
                : cmsText(root, "duplicate_select_title", "Zum Duplizieren zuerst eine Seite auswählen"));
            duplicateBtn.setAttribute("aria-label", duplicateBtn.getAttribute("title"));
        }
        if (lngBtn) {
            const cfg = cmsConfig(root);
            const show = isMasterLngCfg(cfg, root);
            lngBtn.hidden = !show;
            lngBtn.style.display = show ? "" : "none";
        }
        if (lngResetBtn) {
            const cfg = cmsConfig(root);
            const show = isMasterLngCfg(cfg, root);
            lngResetBtn.hidden = !show;
            lngResetBtn.style.display = show ? "" : "none";
        }
    }

    function currentCmsLng(root) {
        const tab = qs(root, ".dbx-cms-lng-tab.is-active");
        return tab ? String(tab.getAttribute("data-cms-lng") || "").toLowerCase() : "";
    }

    function cmsLngParams(root) {
        const lng = currentCmsLng(root);
        return lng ? { dbx_lng: lng } : {};
    }

    function isMasterLngCfg(cfg, root) {
        const activeTab = qs(root, ".dbx-cms-lng-tab.is-active");
        if (activeTab && activeTab.classList.contains("is-master")) {
            return true;
        }

        let master = String((cfg && (cfg.masterlng || cfg.master_lng)) || "").toLowerCase().trim();
        if (!/^[a-z]{2,3}$/.test(master)) {
            const masterTab = qs(root, ".dbx-cms-lng-tab.is-master");
            master = masterTab ? String(masterTab.getAttribute("data-cms-lng") || "de").toLowerCase() : "de";
        }

        const current = currentCmsLng(root);
        return !current || current === master;
    }

    function maybeOpenLngProvisionAfterCreate(root, cfg, data) {
        if (!data) {
            return;
        }
        if (!isMasterLngCfg(cfg, root)) {
            dbx.warn("[cms] provision dialog skipped: not master language tab");
            return;
        }

        if (Number(data.open_lng_provision) === 1) {
            window.setTimeout(() => openLngProvisionDialog(root, cfg), 0);
            return;
        }

        const targets = Array.isArray(data.lng_sync_targets) ? data.lng_sync_targets : [];
        if (targets.length) {
            window.setTimeout(() => showLngSyncResultModal(root, data), 0);
        }
    }

    function showLngSyncResultModal(root, data) {
        const old = document.querySelector("[data-cms-lng-dialog]");
        if (old) old.remove();

        const targets = Array.isArray(data.lng_sync_targets)
            ? data.lng_sync_targets.map(lng => String(lng || "").toLowerCase()).filter(Boolean)
            : [];
        if (!targets.length) return;

        const updated = Array.isArray(data.lng_sync_updated) ? data.lng_sync_updated : [];
        const skipped = Array.isArray(data.lng_sync_skipped) ? data.lng_sync_skipped : [];
        const errors = Array.isArray(data.lng_sync_errors) ? data.lng_sync_errors.filter(Boolean) : [];
        const provider = String(data.lng_translate_provider || "copy").toLowerCase();
        const providerLabel = (!provider || provider === "undef") ? "copy" : provider;

        const updatedLngs = new Set(updated.map(item => String(item && item.lng || "").toLowerCase()).filter(Boolean));
        const skippedByLng = new Map();
        skipped.forEach(item => {
            const lng = String(item && item.lng || "").toLowerCase();
            if (lng) skippedByLng.set(lng, String(item && item.reason || ""));
        });

        const reasonLabels = {
            manual: ["Manuell", "warning"],
            missing: ["Sprachversion fehlt", "warning"],
            not_found: ["Nicht gefunden", "danger"],
            folder_missing: ["Zielordner fehlt", "warning"],
            up_to_date: ["Bereits aktuell", "secondary"]
        };
        const rows = targets.map(lng => {
            let label = "Keine Aenderung";
            let tone = "secondary";
            if (updatedLngs.has(lng)) {
                label = "Synchronisiert";
                tone = "success";
            } else if (skippedByLng.has(lng)) {
                const mapped = reasonLabels[skippedByLng.get(lng)] || ["Uebersprungen", "secondary"];
                label = mapped[0];
                tone = mapped[1];
            }
            return `<div class="dbx-cms-lng-row dbx-cms-lng-result-row">
                <strong>${escapeHtml(lng.toUpperCase())}</strong>
                <span class="badge text-bg-${escapeHtml(tone)}">${escapeHtml(label)}</span>
            </div>`;
        }).join("");

        const providerHint = providerLabel === "copy"
            ? "Die Inhalte wurden aus der Master-Sprache uebernommen. Eine automatische Uebersetzung ist nicht konfiguriert."
            : `Uebersetzungsdienst: ${escapeHtml(providerLabel)}`;
        const warningText = formatTranslateWarnings(data.translate_warnings);
        const warningsHtml = [warningText, ...errors.map(String)]
            .filter(Boolean)
            .map(message => `<div class="dbx-cms-lng-warn">${escapeHtml(message)}</div>`)
            .join("");
        const mediaCopied = Number(data.lng_media_copied || 0);
        const mediaHtml = mediaCopied > 0
            ? `<div class="text-muted small">${mediaCopied} Medien-Verknuepfung(en) wurden uebernommen.</div>`
            : "";

        const resultHtml = `<div class="d-grid gap-2">${rows}${mediaHtml}${warningsHtml}</div>`;
        ensureConfirm().then(ok => {
            if (!ok) {
                showLngSyncResultFallback(root, providerHint, resultHtml, providerLabel);
                return;
            }
            const oldDialog = document.querySelector("[data-cms-lng-dialog]");
            if (oldDialog) oldDialog.remove();
            return dbx.confirm.open({
                id: "cms-lng-sync-result",
                root,
                title: '<i class="bi bi-translate"></i> Sprachsynchronisierung',
                question: resultHtml,
                hint: providerHint,
                buttons: "cancel",
                labelcancel: '<i class="bi bi-check-lg"></i> Schliessen',
                closable: true,
                backdropclose: false,
                escclose: true
            });
        }).catch(err => {
            dbx.warn("[cms] sync result dialog fallback", err);
            showLngSyncResultFallback(root, providerHint, resultHtml, providerLabel);
        });
    }

    function cmsLanguageDialogZIndex() {
        let max = 260000;
        document.querySelectorAll(".dbx-window, .dbx-window-overlay, .dbx-confirm-overlay, .dbx-confirm-dialog").forEach(el => {
            const value = parseInt(window.getComputedStyle(el).zIndex, 10);
            if (Number.isFinite(value)) max = Math.max(max, value + 20);
        });
        return Math.min(2147483646, max);
    }

    function showLngSyncResultFallback(root, providerHint, resultHtml, providerLabel) {
        const old = document.querySelector("[data-cms-lng-dialog]");
        if (old) old.remove();

        const modal = document.createElement("div");
        modal.className = "dbx-cms-lng-dialog dbx-cms-lng-result-dialog";
        modal.setAttribute("data-cms-lng-dialog", "1");
        modal.setAttribute("data-cms-lng-result-dialog", "1");
        modal.style.zIndex = String(cmsLanguageDialogZIndex());
        modal.innerHTML = `
            <div class="dbx-cms-lng-dialog-backdrop" data-cms-lng-close></div>
            <div class="dbx-cms-lng-dialog-panel" role="dialog" aria-modal="true" aria-label="Sprachsynchronisierung">
                <div class="dbx-cms-lng-dialog-head">
                    <strong><i class="bi bi-translate me-2" aria-hidden="true"></i>Sprachsynchronisierung</strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cms-lng-close aria-label="Schliessen">&times;</button>
                </div>
                <div class="dbx-cms-lng-dialog-body">
                    <div class="alert alert-info py-2 mb-0">${providerHint}</div>
                    ${resultHtml}
                </div>
                <div class="dbx-cms-lng-dialog-foot">
                    <span class="text-muted small">Provider: ${escapeHtml(providerLabel)}</span>
                    <button type="button" class="btn btn-primary btn-sm" data-cms-lng-close>Schliessen</button>
                </div>
            </div>`;

        document.body.appendChild(modal);
        const close = () => {
            document.removeEventListener("keydown", onKeyDown);
            modal.remove();
        };
        const onKeyDown = event => {
            if (event.key === "Escape") close();
        };
        qsa(modal, "[data-cms-lng-close]").forEach(btn => btn.addEventListener("click", close));
        document.addEventListener("keydown", onKeyDown);
    }

    function openLngProvisionDialog(root, cfg) {
        const s = state(root);
        const type = root.classList.contains("is-folder-editing") || s.selectedType === "folder" ? "folder" : "page";
        const id = type === "folder"
            ? Number(s.selectedFolder || getField(root, "folder") || 0)
            : Number(s.selectedPage || getField(root, "id") || 0);

        if (!id) {
            status(root, "Bitte zuerst eine Seite oder einen Ordner waehlen.", "error");
            return;
        }
        if (!isMasterLngCfg(cfg, root)) {
            status(root, "Uebertragung nur in der Master-Sprache moeglich.", "error");
            return;
        }

        const url = cfgUrl(cfg, "lngpreview");
        if (!url) {
            status(root, "Lng-Preview-URL fehlt.", "error");
            return;
        }

        fetchJson(apiUrl(url, cmsLngParams(root)), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ type, id })
        }).then(data => {
            if (!data || !data.ok) {
                throw new Error(data && data.msg ? data.msg : "Vorschau fehlgeschlagen");
            }
            showLngProvisionModal(root, cfg, type, id, data.preview || {}, data.provider || "", data.translate_warnings || []);
            const warn = formatTranslateWarnings(data.translate_warnings);
            if (warn) status(root, warn, "warning");
        }).catch(err => {
            status(root, err && err.message ? err.message : "Vorschau fehlgeschlagen.", "error");
        });
    }

    function showLngProvisionModal(root, cfg, type, id, preview, provider, translateWarnings) {
        const old = document.querySelector("[data-cms-lng-dialog]");
        if (old) old.remove();

        const prov = String(provider || "").toLowerCase();
        const provLabel = (!prov || prov === "undef") ? "copy" : prov;
        const items = Array.isArray(preview.items) ? preview.items : [];
        const warnGlobal = formatTranslateWarnings(translateWarnings);
        const warnGlobalHtml = warnGlobal ? `<div class="dbx-cms-lng-warn">${escapeHtml(warnGlobal)}</div>` : "";
        const treeOptionHtml = type === "folder"
            ? `<label class="dbx-cms-lng-check mb-2"><input type="checkbox" data-lng-provision-tree> Gesamten Unterbaum inkl. Seiten uebertragen</label>`
            : "";
        const rows = items.map((item, index) => {
            const lng = String(item.lng || "").toUpperCase();
            const exists = Number(item.exists || 0) === 1;
            const warnings = Array.isArray(item.warnings) ? item.warnings.join(" ") : "";
            const warnHtml = warnings ? `<div class="dbx-cms-lng-warn">${escapeHtml(warnings)}</div>` : "";
            const existsLabel = exists ? " (vorhanden)" : " (neu)";

            if (type === "folder") {
                return `<div class="dbx-cms-lng-row" data-lng-row="${index}">
                    <label class="dbx-cms-lng-check"><input type="checkbox" data-lng-enabled checked> <strong>${escapeHtml(lng)}</strong>${escapeHtml(existsLabel)}</label>
                    <label>Name<input class="form-control form-control-sm" data-lng-name value="${escapeHtml(item.name || "")}"></label>
                    ${warnHtml}
                    <input type="hidden" data-lng-code value="${escapeHtml(item.lng || "")}">
                </div>`;
            }

            return `<div class="dbx-cms-lng-row" data-lng-row="${index}">
                <label class="dbx-cms-lng-check"><input type="checkbox" data-lng-enabled checked> <strong>${escapeHtml(lng)}</strong>${escapeHtml(existsLabel)}</label>
                <label>Titel<input class="form-control form-control-sm" data-lng-title value="${escapeHtml(item.title || "")}"></label>
                <label>Permalink<input class="form-control form-control-sm" data-lng-permalink value="${escapeHtml(item.permalink || "")}"></label>
                <label>Beschreibung<input class="form-control form-control-sm" data-lng-description value="${escapeHtml(item.description || "")}"></label>
                <input type="hidden" data-lng-folder value="${Number(item.folder || 0)}">
                <input type="hidden" data-lng-code value="${escapeHtml(item.lng || "")}">
                <details class="dbx-cms-lng-content-details"><summary>Inhalt (Vorschau)</summary>
                    <div class="dbx-cms-lng-content-preview dbx-content-page" data-lng-content-preview="${index}"></div>
                </details>
                <textarea class="d-none" data-lng-content aria-hidden="true">${escapeTextareaValue(item.content || "")}</textarea>
                ${warnHtml}
            </div>`;
        }).join("");

        const modal = document.createElement("div");
        modal.className = "dbx-cms-lng-dialog";
        modal.setAttribute("data-cms-lng-dialog", "1");
        modal.style.zIndex = String(cmsLanguageDialogZIndex());
        modal.innerHTML = `
            <div class="dbx-cms-lng-dialog-backdrop" data-cms-lng-close></div>
            <div class="dbx-cms-lng-dialog-panel" role="dialog" aria-modal="true" aria-label="Uebersetzungen pruefen">
                <div class="dbx-cms-lng-dialog-head">
                    <strong>In andere Sprachen uebertragen</strong>
                    <span class="text-muted">Provider: ${escapeHtml(provLabel)}</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cms-lng-close>&times;</button>
                </div>
                <div class="dbx-cms-lng-dialog-body">
                    ${warnGlobalHtml}
                    ${treeOptionHtml}
                    ${rows || '<p class="text-muted">Keine Zielsprachen konfiguriert.</p>'}
                </div>
                <div class="dbx-cms-lng-dialog-foot">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-lng-close>Abbrechen</button>
                    <button type="button" class="btn btn-primary btn-sm" data-cms-lng-submit>Uebernehmen</button>
                </div>
            </div>`;

        document.body.appendChild(modal);
        fillLngProvisionContentPreviews(modal, items);

        qsa(modal, "[data-cms-lng-close]").forEach(btn => {
            btn.addEventListener("click", () => modal.remove());
        });

        const submit = qs(modal, "[data-cms-lng-submit]");
        if (submit) {
            submit.addEventListener("click", () => {
                const payloadItems = qsa(modal, "[data-lng-row]").map(row => {
                    const lng = qs(row, "[data-lng-code]");
                    const enabled = qs(row, "[data-lng-enabled]");
                    const out = {
                        lng: lng ? lng.value : "",
                        enabled: enabled && enabled.checked ? 1 : 0
                    };
                    if (type === "folder") {
                        const name = qs(row, "[data-lng-name]");
                        out.name = name ? name.value : "";
                    } else {
                        const title = qs(row, "[data-lng-title]");
                        const permalink = qs(row, "[data-lng-permalink]");
                        const description = qs(row, "[data-lng-description]");
                        const content = qs(row, "[data-lng-content]");
                        const folder = qs(row, "[data-lng-folder]");
                        out.title = title ? title.value : "";
                        out.permalink = permalink ? permalink.value : "";
                        out.description = description ? description.value : "";
                        out.content = content ? content.value : "";
                        out.folder = folder ? Number(folder.value || 0) : 0;
                    }
                    return out;
                });

                const treeMode = type === "folder" && !!(qs(modal, "[data-lng-provision-tree]") && qs(modal, "[data-lng-provision-tree]").checked);
                const provUrl = cfgUrl(cfg, treeMode ? "lngprovisiontree" : "lngprovision");
                const payload = treeMode
                    ? { id, lngs: payloadItems.filter(item => Number(item.enabled) === 1).map(item => item.lng) }
                    : { type, id, items: payloadItems };

                fetchJson(apiUrl(provUrl, cmsLngParams(root)), {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload)
                }).then(data => {
                    if (!data || !data.ok) {
                        const errs = data && data.result && Array.isArray(data.result.errors) ? data.result.errors.join(" ") : "";
                        const transWarn = formatTranslateWarnings(data && data.result ? data.result.translate_warnings : null);
                        throw new Error([data && data.msg, errs, transWarn].filter(Boolean).join(" ") || "Uebertragung fehlgeschlagen");
                    }
                    modal.remove();
                    let msg = treeMode ? "Unterbaum in andere Sprachen uebernommen." : "Sprachvarianten uebernommen.";
                    const mediaCopied = Number((data.result && data.result.media_copied) || 0);
                    if (mediaCopied > 0) msg += " " + mediaCopied + " Medien-Verknuepfung(en) kopiert.";
                    const transWarn = formatTranslateWarnings(data.result && data.result.translate_warnings);
                    if (transWarn) msg += " " + transWarn;
                    status(root, msg, transWarn ? "warning" : "success");
                    return loadTree(root, cfg);
                }).catch(err => {
                    status(root, err && err.message ? err.message : "Uebertragung fehlgeschlagen.", "error");
                });
            });
        }
    }

    function formatTranslateWarnings(warnings) {
        if (!Array.isArray(warnings) || !warnings.length) return "";
        const msgs = warnings.map(w => w && w.message ? String(w.message) : "").filter(Boolean);
        if (!msgs.length) return "";
        return "Uebersetzung: " + [...new Set(msgs)].join(" ");
    }

    function formatLngSaveStatus(base, data) {
        const parts = [base];
        const synced = Number(data.lng_synced || 0);
        const provider = String(data.lng_translate_provider || "").toLowerCase();
        if (synced > 0) {
            parts.push(synced + " Sprachversion(en) synchronisiert" + (provider === "copy" ? " (Text kopiert)." : "."));
        }
        const media = Number(data.lng_media_copied || 0);
        if (media > 0) parts.push(media + " Medien-Verknuepfung(en) uebernommen.");
        const inlineAdded = Number(data.inline_media_sync?.added || 0);
        const inlineRemoved = Number(data.inline_media_sync?.removed || 0);
        if (inlineAdded > 0) parts.push(inlineAdded + " Inline-Medium/Medien verknuepft.");
        if (inlineRemoved > 0) parts.push(inlineRemoved + " Inline-Medium/Medien aus der Liste entfernt.");
        if (Number(data.lng_forked || 0) === 1) parts.push("Sprachverknuepfung getrennt (manuell).");
        if (Number(data.open_lng_provision || 0) === 1) parts.push("Fehlende Sprachen — Dialog folgt.");
        const warn = formatTranslateWarnings(data.translate_warnings);
        if (warn) parts.push(warn);
        const syncErrors = Array.isArray(data.lng_sync_errors) ? data.lng_sync_errors.filter(Boolean) : [];
        if (syncErrors.length) parts.push("Sprachsync: " + [...new Set(syncErrors.map(String))].join(" "));
        const text = parts.join(" ");
        const hasWarn = Number(data.lng_forked || 0) === 1 || !!warn || syncErrors.length > 0;
        return { text, type: hasWarn ? "warning" : "success" };
    }

    function resetLngSync(root, cfg) {
        const s = state(root);
        const type = root.classList.contains("is-folder-editing") || s.selectedType === "folder" ? "folder" : "page";
        const id = type === "folder"
            ? Number(s.selectedFolder || getFolderField(root, "id") || 0)
            : Number(s.selectedPage || getField(root, "id") || 0);
        const url = cfgUrl(cfg, "lngresetsync");

        if (!id || !url) {
            status(root, "Bitte zuerst eine Seite oder einen Ordner waehlen.", "error");
            return Promise.resolve();
        }
        if (!isMasterLngCfg(cfg, root)) {
            status(root, "Auto-Sync nur in der Master-Sprache setzbar.", "error");
            return Promise.resolve();
        }

        return ensureConfirm().then(ok => {
            if (!ok) return null;
            return dbx.confirm.open({
                id: "cms-lng-reset-sync-" + id,
                root,
                title: "<i class=\"bi bi-link-45deg\"></i> Auto-Sync aktivieren",
                question: "Verknuepfte Sprachversionen wieder auf <strong>Auto-Sync</strong> stellen?",
                hint: "Manuelle Aenderungen in Slave-Sprachen bleiben erhalten, werden aber kuenftig wieder vom Master ueberschrieben.",
                buttons: "yesno",
                labelyes: "Ja, Auto-Sync",
                labelno: "Abbrechen",
                closable: true,
                backdropclose: false,
                escclose: true
            });
        }).then(result => {
            if (!result || result.action !== "yes") return null;
            return fetchJson(apiUrl(url), {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ type, id })
            });
        }).then(data => {
            if (!data) return;
            if (!data.ok) throw new Error(data && data.msg ? data.msg : "Auto-Sync konnte nicht gesetzt werden.");
            const count = Array.isArray(data.result && data.result.updated) ? data.result.updated.length : 0;
            status(root, count > 0 ? "Auto-Sync fuer " + count + " Sprache(n) aktiviert." : "Keine Sprachversion zum Aktualisieren gefunden.", count > 0 ? "success" : "warning");
            return loadTree(root, cfg);
        }).catch(err => {
            status(root, err && err.message ? err.message : "Auto-Sync konnte nicht gesetzt werden.", "error");
        });
    }

    function syncStatusLabel(sync, isMaster) {
        if (Number(isMaster) === 1) return "Master";
        const s = String(sync || "auto").toLowerCase();
        return s === "manual" ? "manuell" : "auto";
    }

    function runSimpleDeleteConfirm(root, title, question, hint) {
        return ensureConfirm().then(ok => {
            if (!ok) {
                status(root, "Confirm-Lib ist nicht geladen.", "error");
                return null;
            }
            return dbx.confirm.open({
                id: "cms-delete-simple-" + Date.now(),
                root,
                title,
                question,
                hint,
                buttons: "yesno",
                labelyes: "<i class=\"bi bi-trash\"></i> Loeschen",
                labelno: "<i class=\"bi bi-x-lg\"></i> Abbrechen",
                closable: true,
                backdropclose: false,
                escclose: true
            });
        });
    }

    function executeLngDelete(root, cfg, type, id, deleteLngs, deleteUrl) {
        return fetchJson(apiUrl(deleteUrl), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id, delete_lngs: deleteLngs })
        }).then(data => {
            if (!data || !data.ok) {
                throw new Error(data && data.msg ? data.msg : "delete failed");
            }
            const count = Array.isArray(data.deleted) ? data.deleted.length : 0;
            const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
            let msg = count > 1
                ? (type === "folder" ? "Ordner" : "Seite") + " in " + count + " Sprachen geloescht."
                : (type === "folder" ? "Ordner geloescht." : "Seite geloescht.");
            if (warnings.length) {
                msg += " Hinweis: " + warnings.join(" ");
            }
            status(root, msg, warnings.length ? "warning" : "success");
            return data;
        });
    }

    function showLngDeleteModal(root, cfg, type, id, label, preview) {
        const old = qs(root, "[data-cms-lng-delete-dialog]");
        if (old) old.remove();

        const items = Array.isArray(preview.items) ? preview.items : [];
        const rows = items.map((item, index) => {
            const lng = String(item.lng || "").toUpperCase();
            const checked = Number(item.checked || 0) === 1;
            const deletable = Number(item.deletable || 0) === 1;
            const syncLabel = syncStatusLabel(item.lng_sync, item.is_master);
            const syncClass = Number(item.is_master) === 1 ? "master" : String(item.lng_sync || "auto").toLowerCase();
            const blockReason = String(item.block_reason || "").trim();
            const disabled = deletable ? "" : " disabled";
            const checkedAttr = checked && deletable ? " checked" : "";
            const blockHtml = blockReason
                ? `<div class="dbx-cms-lng-warn">${escapeHtml(blockReason)}</div>`
                : "";

            return `<div class="dbx-cms-lng-row dbx-cms-lng-delete-row" data-lng-delete-row="${index}">
                <label class="dbx-cms-lng-check">
                    <input type="checkbox" data-lng-delete-enabled${disabled}${checkedAttr}>
                    <strong>${escapeHtml(lng)}</strong>
                    <span class="dbx-cms-lng-badge is-${escapeHtml(syncClass)}">${escapeHtml(syncLabel)}</span>
                    <span class="text-muted">${escapeHtml(item.label || "")}</span>
                </label>
                ${blockHtml}
                <input type="hidden" data-lng-delete-code value="${escapeHtml(item.lng || "")}">
            </div>`;
        }).join("");

        const entityLabel = type === "folder" ? "Ordner" : "Seite";
        const modal = document.createElement("div");
        modal.className = "dbx-cms-lng-dialog";
        modal.setAttribute("data-cms-lng-delete-dialog", "1");
        modal.innerHTML = `
            <div class="dbx-cms-lng-dialog-backdrop" data-cms-lng-delete-close></div>
            <div class="dbx-cms-lng-dialog-panel" role="dialog" aria-modal="true" aria-label="Sprachversionen loeschen">
                <div class="dbx-cms-lng-dialog-head">
                    <strong>${escapeHtml(entityLabel)} in mehreren Sprachen loeschen</strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cms-lng-delete-close>&times;</button>
                </div>
                <div class="dbx-cms-lng-dialog-body">
                    <p class="mb-2">Welche Sprachversionen von <strong>${escapeHtml(label || entityLabel)}</strong> sollen geloescht werden?</p>
                    <p class="text-muted small mb-3">Auto-Sync-Versionen sind vorausgewaehlt. Manuelle Versionen nur bei Bedarf aktivieren.</p>
                    ${rows || '<p class="text-muted">Keine verknuepften Sprachversionen gefunden.</p>'}
                </div>
                <div class="dbx-cms-lng-dialog-foot">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-lng-delete-close>Abbrechen</button>
                    <button type="button" class="btn btn-danger btn-sm" data-cms-lng-delete-submit><i class="bi bi-trash"></i> Loeschen</button>
                </div>
            </div>`;

        root.appendChild(modal);

        qsa(modal, "[data-cms-lng-delete-close]").forEach(btn => {
            btn.addEventListener("click", () => modal.remove());
        });

        const submit = qs(modal, "[data-cms-lng-delete-submit]");
        if (submit) {
            submit.addEventListener("click", () => {
                const deleteLngs = qsa(modal, "[data-lng-delete-row]").map(row => {
                    const enabled = qs(row, "[data-lng-delete-enabled]");
                    const code = qs(row, "[data-lng-delete-code]");
                    if (!enabled || !enabled.checked || enabled.disabled) return "";
                    return code ? code.value : "";
                }).filter(Boolean);

                if (!deleteLngs.length) {
                    status(root, "Bitte mindestens eine loeschbare Sprachversion auswaehlen.", "error");
                    return;
                }

                const deleteUrl = cfgUrl(cfg, type === "folder" ? "deletefolder" : "deletepage");
                executeLngDelete(root, cfg, type, id, deleteLngs, deleteUrl).then(data => {
                    modal.remove();
                    if (type === "folder") {
                        hideFolderEditor(root);
                        const s = state(root);
                        if (Number(s.selectedFolder || 0) === id) {
                            setSelectedFolder(root, 0);
                            setSelectedType(root, "page");
                        }
                    } else {
                        setSelectedPage(root, 0);
                        setSelectedType(root, "page");
                        setEditorHtml(root, "");
                        renderMedia(root, []);
                    }
                    return loadTree(root, cfg);
                }).catch(err => {
                    status(root, err && err.message ? err.message : "Loeschen fehlgeschlagen.", "error");
                });
            });
        }
    }

    function openLngDeleteDialog(root, cfg, type, id, label) {
        const previewUrl = cfgUrl(cfg, "lngdeletepreview");
        const deleteUrl = cfgUrl(cfg, type === "folder" ? "deletefolder" : "deletepage");
        const entityLabel = type === "folder" ? "Ordner" : "Seite";
        const title = `<i class="bi bi-trash"></i> ${entityLabel} loeschen`;
        const question = `${entityLabel} <strong>${escapeHtml(label || entityLabel)}</strong> wirklich loeschen?`;
        const hint = type === "folder"
            ? "Der Ordner wird nur geloescht, wenn keine Seiten und keine Unterordner enthalten sind."
            : "Die Medien werden aus der Seite geloest, Dateien bleiben im Medienbestand.";

        if (!isMasterLngCfg(cfg, root) || !previewUrl || !deleteUrl) {
            return runSimpleDeleteConfirm(root, title, question, hint).then(result => {
                if (!result || result.action !== "yes") return null;
                return executeLngDelete(root, cfg, type, id, [], deleteUrl).then(data => {
                    if (type === "folder") {
                        hideFolderEditor(root);
                        const s = state(root);
                        if (Number(s.selectedFolder || 0) === id) {
                            setSelectedFolder(root, 0);
                            setSelectedType(root, "page");
                        }
                    } else {
                        setSelectedPage(root, 0);
                        setSelectedType(root, "page");
                        setEditorHtml(root, "");
                        renderMedia(root, []);
                    }
                    return loadTree(root, cfg);
                });
            });
        }

        return fetchJson(apiUrl(previewUrl), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ type, id })
        }).then(data => {
            if (!data || !data.ok) {
                throw new Error(data && data.msg ? data.msg : "Vorschau fehlgeschlagen");
            }
            const items = Array.isArray(data.preview && data.preview.items) ? data.preview.items : [];
            if (items.length <= 1) {
                return runSimpleDeleteConfirm(root, title, question, hint).then(result => {
                    if (!result || result.action !== "yes") return null;
                    return executeLngDelete(root, cfg, type, id, [], deleteUrl).then(data => {
                        if (type === "folder") {
                            hideFolderEditor(root);
                            const s = state(root);
                            if (Number(s.selectedFolder || 0) === id) {
                                setSelectedFolder(root, 0);
                                setSelectedType(root, "page");
                            }
                        } else {
                            setSelectedPage(root, 0);
                            setSelectedType(root, "page");
                            setEditorHtml(root, "");
                            renderMedia(root, []);
                        }
                        return loadTree(root, cfg);
                    });
                });
            }
            showLngDeleteModal(root, cfg, type, id, label, data.preview || {});
            return null;
        }).catch(err => {
            status(root, err && err.message ? err.message : "Vorschau fehlgeschlagen.", "error");
        });
    }

    function clearCmsLoading(root) {
        const anchors = [root, document.body, document.documentElement].filter(Boolean);
        anchors.forEach(anchor => {
            anchor.classList.remove("dbx-ajax-loading", "is-loading");
            anchor.removeAttribute("aria-busy");
            anchor.style.removeProperty("filter");
            anchor.style.removeProperty("opacity");
        });

        let el = root;
        while (el) {
            el.classList.remove("dbx-ajax-loading", "is-loading");
            if (el === document.body) break;
            el = el.parentElement;
        }

        qsa(document, ".dbx-ajax-loading, .is-loading, [data-cms-action]").forEach(el => {
            el.classList.remove("dbx-ajax-loading", "is-loading");
        });

        qsa(document, ".jodit-dialog__overlay, .jodit-dialog_overlay, .jodit-ui-modal-overlay, .jodit-popup__overlay, .jodit-icon_loader, .jodit-icon-loader, .jodit-loader, .jodit-ui-loader, .jodit_spinner, .jodit-wait").forEach(el => {
            el.style.display = "none";
            el.style.background = "transparent";
            el.style.boxShadow = "none";
            el.style.backdropFilter = "none";
        });

        qsa(document, ".dbx-loader, .dbx-loading, .dbx-wait, .dbx-backdrop, .modal-backdrop").forEach(el => {
            el.classList.remove("show", "active", "dbx-ajax-loading", "is-loading");
            el.style.display = "none";
            el.style.background = "transparent";
            el.style.boxShadow = "none";
            el.style.backdropFilter = "none";
        });
    }

    function currentSelectionLabel(id, title, fallback) {
        const numericId = Number(id || 0);
        const text = String(title || fallback || "").trim();
        if (numericId > 0 && text) return "(" + numericId + ") " + text;
        if (numericId > 0) return "(" + numericId + ")";
        return text || fallback || "";
    }

    function updateCurrentSelectionTitle(root, type, id, title) {
        const el = qs(root, "[data-cms-current-title]");
        if (!el) return;
        const fallback = type === "folder" ? "Neuer Ordner" : "Keine Seite ausgewaehlt";
        el.textContent = currentSelectionLabel(id, title, fallback);
    }

    function updateViewPageTitle(root, title) {
        const el = qs(root, "[data-cms-page-title]");
        if (!el) return;
        const text = String(title || "").trim();
        el.textContent = text !== "" ? text : "Keine Seite ausgewaehlt";
    }

    function openContentAdmin(root) {
        const s = state(root);
        const pageId = Number((s.page && (s.page.id || s.page)) || s.selectedPage || 0);
        if (!pageId) {
            status(root, "Bitte zuerst eine Seite waehlen.", "info");
            return;
        }

        const btn = qs(root, "[data-cms-action='open-admin']");
        const base = btn ? String(btn.getAttribute("data-cms-admin-base") || "").trim() : "";
        const url = (base || "?dbx_modul=dbxContent_admin&dbx_run1=cms") + "&cid=" + encodeURIComponent(pageId);
        const winCfg = {
            url: url,
            title: "Content bearbeiten",
            width: "1320",
            height: "860",
            modal: "1",
            scroll: "1"
        };

        ensureFeature("openWin").then(ok => {
            if (ok && dbx.openWin && typeof dbx.openWin.open === "function") {
                dbx.openWin.open(winCfg);
                return;
            }
            dbx.warn("[cms] openWin.js konnte nicht geladen werden; CMS wird im aktuellen Fenster geoeffnet.");
            window.location.assign(url);
        });
    }

    function markDirty(root) {
        const s = state(root);
        if (s.loading || s.saving || s.suppressDirty) return;
        s.dirtyVersion = (Number(s.dirtyVersion || 0) + 1);
        setDirty(root, true);

        if (root.classList.contains("is-folder-editing")) {
            updateCurrentSelectionTitle(root, "folder", getFolderField(root, "id"), getFolderField(root, "name"));
            return;
        }
        updateCurrentSelectionTitle(root, "page", getField(root, "id"), getField(root, "title"));
    }

    function clearDirtyAfterSave(root) {
        const s = state(root);
        const version = Number(s.dirtyVersion || 0);
        setDirty(root, false);
        [80, 300].forEach(delay => {
            window.setTimeout(() => {
                const current = state(root);
                if (!current.loading && !current.saving && Number(current.dirtyVersion || 0) === version) {
                    setDirty(root, false);
                }
            }, delay);
        });
    }

    function suppressDirtyFor(root, delay) {
        const s = state(root);
        const token = Date.now() + Math.random();
        s.suppressDirty = true;
        s.suppressDirtyToken = token;
        window.setTimeout(() => {
            const current = state(root);
            if (current.suppressDirtyToken === token) {
                current.suppressDirty = false;
                current.suppressDirtyToken = null;
            }
        }, Number(delay || 0));
    }

    function mediaHtml(row) {
        row = row || {};
        const url = row.url || "";
        if (!url) return "";

        const mime = String(row.mime || "");
        const fileName = String(row.file_name || url);
        const title = escapeHtml(row.title || row.alt || fileName || "Medium");
        const alt = escapeHtml(row.alt || row.title || fileName || "");

        const id = Number(row.id || row.media_id || 0);
        const videoUrl = row.embed_url || row.external_url || row.url || "";
        const videoDataAttr = isVideoRow(row)
            ? ` data-cms-video-url="${escapeHtml(videoUrl)}" data-cms-video-type="${escapeHtml(row.media_type || "")}" data-cms-video-mime="${escapeHtml(row.mime || "")}" data-cms-video-muted="0"`
            : "";
        const mediaAttr = id > 0 ? ` data-cms-media-id="${id}" data-cms-media-slot="inline"${videoDataAttr}` : ` data-cms-media-slot="inline"${videoDataAttr}`;

        if (mime.startsWith("image/") || /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(fileName)) {
            return `<p class="dbx-cms-inline-media" data-cms-media-slot="inline"><img class="dbx-cms-inline-image" src="${escapeHtml(url)}" alt="${alt}" title="${title}"${mediaAttr}></p><p></p>`;
        }

        if (isExternalVideoRow(row)) {
            return `<figure class="dbx-cms-inline-media dbx-cms-inline-video-block"${mediaAttr}>${mediaPlayerInnerHtml(row, id)}</figure><p></p>`;
        }

        if (mime.startsWith("video/") || /\.(mp4|webm|ogv|ogg|mov|m4v)$/i.test(fileName)) {
            return `<figure class="dbx-cms-inline-media dbx-cms-inline-video-block"${mediaAttr}>${mediaPlayerInnerHtml(row, id)}</figure><p></p>`;
        }

        return `<p><a href="${escapeHtml(url)}" target="_blank" rel="noopener">${title}</a></p>`;
    }

    function cmsMarkerName(marker) {
        return String(marker || "").replace(/^dbx:/, "") || "marker";
    }

    function cmsMarkerClassName(marker) {
        return cmsMarkerName(marker).replace(/[^a-z0-9_-]+/gi, "-") || "marker";
    }

    function cmsMarkerLabel(marker, label) {
        const labels = {
            "dbx:hero": "Hero-Text",
            "dbx:split": "col-2 Trenner",
            "dbx:col2": "col-2 Trenner",
            "dbx:col3a": "col-3a Trenner",
            "dbx:col3b": "col-3b Trenner",
            "dbx:header": "Header",
            "dbx:teaser": "Header",
            "dbx:footer": "Footer",
            "dbx:pagebreak": "Druck Seitenumbruch"
        };
        return label || labels[marker] || marker || "dbx:marker";
    }

    function cmsMarkerHtml(marker, label) {
        if (marker === "dbx:split") marker = "dbx:col2";
        const name = cmsMarkerName(marker);
        const className = cmsMarkerClassName(marker);
        return `<hr class="dbx-cms-marker dbx-cms-marker-${escapeHtml(className)}" contenteditable="false" draggable="false" tabindex="0" data-dbx-marker="dbx:${escapeHtml(name)}" data-label="${escapeHtml(cmsMarkerLabel(marker, label))}">`;
    }

    function cmsMarkerElement(marker, label, doc) {
        const tpl = (doc || document).createElement("template");
        tpl.innerHTML = cmsMarkerHtml(marker, label);
        return tpl.content.firstElementChild;
    }

    function markerNameFromElement(node) {
        if (!node || node.nodeType !== 1 || !node.getAttribute) return "";
        const raw = node.getAttribute("data-dbx-marker") || node.getAttribute("data-dbx-marker-comment") || "";
        return raw ? cmsMarkerName(raw) : "";
    }

    function ignorableMarkerSibling(node) {
        if (!node) return false;
        if (node.nodeType === 3) return String(node.nodeValue || "").replace(/\uFEFF/g, "").trim() === "";
        if (node.nodeType !== 1) return false;
        const tag = node.tagName || "";
        if (tag === "BR") return true;
        if (!/^(P|DIV|SPAN)$/i.test(tag)) return false;
        if (node.querySelector && node.querySelector(".dbx-cms-marker,[data-dbx-marker],img,video,iframe,table,hr")) return false;
        return String(node.textContent || "").replace(/\uFEFF/g, "").trim() === "";
    }

    function nearbyMarkerSibling(node, dir) {
        let cur = dir < 0 ? node?.previousSibling : node?.nextSibling;
        while (cur && ignorableMarkerSibling(cur)) cur = dir < 0 ? cur.previousSibling : cur.nextSibling;
        return cur && cur.nodeType === 1 ? cur : null;
    }

    function hasSameMarkerNeighbor(node, name) {
        return markerNameFromElement(nearbyMarkerSibling(node, -1)) === name ||
            markerNameFromElement(nearbyMarkerSibling(node, 1)) === name;
    }

    function dedupeAdjacentMarkers(container) {
        if (!container) return;
        qsa(container, ".dbx-cms-marker,[data-dbx-marker]").forEach(marker => {
            if (!marker.parentNode) return;
            const name = markerNameFromElement(marker);
            if (!name) return;
            const prev = nearbyMarkerSibling(marker, -1);
            if (markerNameFromElement(prev) === name) marker.remove();
        });
    }

    function normalizeCommentMarkers(container) {
        if (!container) return;
        const doc = container.ownerDocument || document;
        const comments = [];
        (function collect(node) {
            Array.from(node && node.childNodes || []).forEach(child => {
                if (child.nodeType === 8) comments.push(child);
                else collect(child);
            });
        })(container);
        comments.forEach(node => {
            const text = String(node.nodeValue || "").trim();
            const match = text.match(/^dbx:([a-z0-9_-]+)/i);
            if (match) {
                const name = cmsMarkerName("dbx:" + match[1].toLowerCase());
                if (node.parentNode && hasSameMarkerNeighbor(node, name)) {
                    node.parentNode.removeChild(node);
                    return;
                }
                const marker = cmsMarkerElement("dbx:" + name, null, doc);
                if (marker && node.parentNode) node.parentNode.replaceChild(marker, node);
                return;
            }
            if (node.parentNode) node.parentNode.removeChild(node);
        });
        dedupeAdjacentMarkers(container);
    }

    function cmsMarkerMenuIcon() {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M4 4h12.5a1 1 0 0 1 .8 1.6L14.9 9l2.4 3.4a1 1 0 0 1-.8 1.6H6v6H4V4Zm2 2v6h8.55l-1.7-2.4a1 1 0 0 1 0-1.16L14.55 6H6Z"/></svg>';
    }

    function cmsHrIcon() {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M4 11h16v2H4v-2Zm2-5h12v2H6V6Zm0 10h12v2H6v-2Z"/></svg>';
    }

    function cmsSaveIcon() {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 3h13l1 1v17H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 2v14h12V5h-2v5H7V5H5Zm4 0v3h4V5H9Zm-1 8h8v2H8v-2Zm0 3h8v2H8v-2Z"/></svg>';
    }

    function cmsTextStyleIcon() {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 4h7.2c2.7 0 4.5 1.5 4.5 3.8 0 1.25-.57 2.25-1.57 2.9 1.42.57 2.27 1.83 2.27 3.55 0 2.55-1.95 4.25-4.9 4.25H5V4Zm3 2.6v3.1h3.8c1.18 0 1.85-.57 1.85-1.55 0-1-.67-1.55-1.85-1.55H8Zm0 5.55v3.75h4.22c1.35 0 2.1-.68 2.1-1.88 0-1.18-.75-1.87-2.1-1.87H8Z"/><path d="M19 5h2v14h-2V5Z"/></svg>';
    }

    function cmsModBrowserIcon() {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="4" width="8" height="6" rx="1" fill="none" stroke="currentColor" stroke-width="1.6"/><rect x="13" y="4" width="8" height="6" rx="1" fill="none" stroke="currentColor" stroke-width="1.6"/><rect x="3" y="14" width="18" height="6" rx="1" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M6 7h2M15 7h2M6 17h12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>';
    }

    function modPlaceholderFromItem(item) {
        return {
            id: item?.id || "",
            url: item?.url || "",
            label: item?.label || "",
            default_modul: item?.default_modul || "",
            default_params: item?.default_params || "",
            default_alt: item?.default_alt || item?.label || "Modul"
        };
    }

    function modPlaceholderHtml(item, insertId) {
        const row = modPlaceholderFromItem(item);
        const url = row.url || "";
        const alt = row.default_alt || "Modul";
        const modul = row.default_modul || "";
        const params = row.default_params || "";
        const modAttr = modul ? ` data-cms-mod-modul="${escapeHtml(modul)}"` : "";
        const parAttr = params ? ` data-cms-mod-params="${escapeHtml(params)}"` : "";
        const insertAttr = insertId ? ` data-cms-mod-insert-id="${escapeHtml(insertId)}"` : "";
        return `<p class="dbx-cms-mod-placeholder dbx-cms-inline-media" contenteditable="false" draggable="true"${insertAttr}${modAttr}${parAttr}><img class="dbx-cms-mod-image" src="${escapeHtml(url)}" alt="${escapeHtml(alt)}"${modAttr}${parAttr} style="width:100%;max-width:100%;height:auto;display:block;" contenteditable="false"></p><p></p>`;
    }

    function inlineModTarget(root, target) {
        const surface = editorSurface(root);
        const mod = closestElement(target, ".dbx-cms-mod-placeholder");
        return mod && surface && surface.contains(mod) ? mod : null;
    }

    function normalizeModPlaceholders(container) {
        if (!container) return;
        qsa(container, ".dbx-cms-mod-placeholder").forEach(wrap => {
            wrap.setAttribute("contenteditable", "false");
            wrap.classList.add("dbx-cms-inline-media");
            const img = qs(wrap, "img") || qs(wrap, ".dbx-cms-mod-image");
            if (!img) return;
            img.classList.add("dbx-cms-mod-image");
            img.style.width = "100%";
            img.style.maxWidth = "100%";
            img.style.height = "auto";
            img.style.display = "block";
            img.setAttribute("contenteditable", "false");
            img.removeAttribute("data-dbx");
            const modul = wrap.getAttribute("data-cms-mod-modul") || "";
            const params = wrap.getAttribute("data-cms-mod-params") || "";
            if (modul) {
                img.setAttribute("data-cms-mod-modul", modul);
                wrap.setAttribute("data-cms-mod-modul", modul);
            }
            if (params) {
                img.setAttribute("data-cms-mod-params", params);
                wrap.setAttribute("data-cms-mod-params", params);
            }
            const alt = img.getAttribute("alt") || "";
            if (alt) wrap.setAttribute("title", alt);
        });
    }

    function modPlaceholderValues(wrapper) {
        const img = wrapper ? (qs(wrapper, "img") || qs(wrapper, ".dbx-cms-mod-image")) : null;
        return {
            modul: wrapper?.getAttribute("data-cms-mod-modul") || img?.getAttribute("data-cms-mod-modul") || "",
            params: wrapper?.getAttribute("data-cms-mod-params") || img?.getAttribute("data-cms-mod-params") || "",
            alt: img?.getAttribute("alt") || wrapper?.getAttribute("title") || ""
        };
    }

    function selectEditorModPlaceholder(root, wrap) {
        const surface = editorSurface(root);
        qsa(surface, ".dbx-cms-mod-placeholder.is-selected").forEach(el => {
            el.classList.remove("is-selected");
            el.removeAttribute("aria-selected");
        });
        if (wrap && surface && surface.contains(wrap)) {
            selectEditorMarker(root, null);
            selectEditorMissingMedia(root, null);
            wrap.classList.add("is-selected");
            wrap.setAttribute("aria-selected", "true");
            state(root).selectedModPlaceholder = wrap;
            hideEditorCaretHint(root);
            selectEditorNode(root, wrap);
        } else {
            state(root).selectedModPlaceholder = null;
        }
    }

    function isEmptyEditorParagraph(el) {
        return !!(el && el.nodeType === 1 && /^P$/i.test(el.tagName || "") && !nodeHasEditorContent(el));
    }

    function removeEditorModPlaceholder(root, wrap) {
        wrap = wrap || state(root).selectedModPlaceholder;
        const surface = editorSurface(root);
        if (!wrap || !wrap.parentNode || !surface || !surface.contains(wrap)) return false;
        const next = wrap.nextElementSibling;
        selectEditorModPlaceholder(root, null);
        wrap.remove();
        if (isEmptyEditorParagraph(next)) next.remove();
        syncEditorDom(root);
        markDirty(root);
        return true;
    }

    function insertModPlaceholder(root, item, cfg) {
        const insertId = "mod_" + Date.now().toString(36) + "_" + Math.random().toString(36).slice(2, 8);
        const html = modPlaceholderHtml(item, insertId);
        if (!html) return false;
        insertEditorHtml(root, html);
        const surface = editorSurface(root);
        normalizeModPlaceholders(surface);
        setField(root, "content", getEditorHtml(root));
        markDirty(root);
        scheduleEditorHeight(root);
        const inserted = qs(surface, `.dbx-cms-mod-placeholder[data-cms-mod-insert-id="${insertId}"]`);
        if (inserted) {
            inserted.removeAttribute("data-cms-mod-insert-id");
            selectEditorModPlaceholder(root, inserted);
            if (inserted.scrollIntoView) inserted.scrollIntoView({ block: "center", inline: "nearest" });
            window.setTimeout(() => openModPlaceholderOptions(root, inserted, cfg || cmsConfig(root) || {}), 0);
        }
        return true;
    }

    function slashCommandHit(root, token) {
        const surface = editorSurface(root);
        const doc = surface ? (surface.ownerDocument || document) : document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!surface || !sel || !sel.rangeCount || !sel.isCollapsed) return null;
        const range = sel.getRangeAt(0);
        if (!rangeInsideSurface(surface, range)) return null;
        const node = range.startContainer;
        const offset = range.startOffset || 0;
        if (!node || node.nodeType !== 3 || offset < token.length) return null;
        const before = String(node.nodeValue || "").slice(offset - token.length, offset);
        if (before.toLowerCase() !== token.toLowerCase()) return null;
        return { node, start: offset - token.length, end: offset };
    }

    function removeSlashCommandHit(root, hit) {
        if (!hit || !hit.node) return false;
        const text = String(hit.node.nodeValue || "");
        hit.node.nodeValue = text.slice(0, hit.start) + text.slice(hit.end);
        const surface = editorSurface(root);
        const doc = surface ? (surface.ownerDocument || document) : document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (sel && doc.createRange) {
            const range = doc.createRange();
            range.setStart(hit.node, hit.start);
            range.collapse(true);
            sel.removeAllRanges();
            sel.addRange(range);
            state(root).editorRange = range.cloneRange();
        }
        syncEditorAfterContextAction(root);
        setField(root, "content", getEditorHtml(root));
        return true;
    }

    function handleEditorSlashCommands(root, cfg) {
        if (!root || root.__dbxCmsSlashModOpening) return;
        const hit = slashCommandHit(root, "/mod");
        if (!hit) return;
        root.__dbxCmsSlashModOpening = true;
        removeSlashCommandHit(root, hit);
        saveEditorSelection(root);
        openModBrowser(root, cfg || cmsConfig(root) || {});
        window.setTimeout(() => {
            root.__dbxCmsSlashModOpening = false;
        }, 500);
    }

    function bindEditorSlashCommands(root, cfg) {
        const surface = editorSurface(root);
        if (!surface || surface.__dbxCmsSlashCommandsBound) return;
        surface.__dbxCmsSlashCommandsBound = true;
        const run = () => window.setTimeout(() => handleEditorSlashCommands(root, cfg || {}), 0);
        surface.addEventListener("input", run);
        surface.addEventListener("keyup", e => {
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            if (e.key === "d" || e.key === "D" || e.key === "Enter" || e.key === " ") run();
        });
    }

    function mediaRowFromItem(item) {
        return {
            url: item?.getAttribute("data-url") || "",
            mime: item?.getAttribute("data-mime") || "",
            file_name: item?.getAttribute("data-file-name") || "",
            file_path: item?.getAttribute("data-file-path") || "",
            thumb_url: item?.getAttribute("data-thumb-url") || "",
            media_type: item?.getAttribute("data-media-type") || "",
            width: item?.getAttribute("data-width") || "",
            height: item?.getAttribute("data-height") || "",
            title: item?.getAttribute("data-title") || "",
            alt: item?.getAttribute("data-alt") || "",
            slot: item?.getAttribute("data-slot") || "",
            media_folder: item?.getAttribute("data-media-folder") || "",
            id: item?.getAttribute("data-media-id") || ""
        };
    }

    function currentMediaSlot(root) {
        const filter = qs(root, "[data-cms-media-filter]");
        const value = filter ? filter.value : state(root).mediaFilter;
        return value && value !== "all" ? value : "gallery";
    }

    function syncUploadSlot(root) {
        const input = qs(root, "[data-cms-upload-slot]");
        if (input) input.value = currentMediaSlot(root);
        const externalInput = qs(root, "[data-cms-external-video-slot]");
        if (externalInput) externalInput.value = currentMediaSlot(root);
    }

    function insertMediaRow(root, row) {
        const html = mediaHtml(row);
        if (!html) return;
        leaveInlineMediaCaption(root);
        if (!insertEditorFragment(root, html)) insertEditorHtml(root, html);
    }

    function applyInlineMediaAssignment(root, row) {
        if (!row || !row.url) return;
        insertMediaRow(root, row);
        setLocalMediaSlot(root, row.id, "inline");
        markDirty(root);
    }

    function mediaRowWithUsage(row, usage, fallbackSlot) {
        const out = Object.assign({}, row || {});
        usage = usage || {};
        if (usage.id) out.usage_id = usage.id;
        if (usage.id) out.current_usage_id = usage.id;
        out.slot = usage.slot || out.slot || fallbackSlot || "gallery";
        if (usage.sorter) out.sorter = usage.sorter;
        if (usage.template) out.template = usage.template;
        if (usage.caption) out.caption = usage.caption;
        return out;
    }

    function upsertLocalMediaRow(root, row) {
        const id = Number(row && row.id || 0);
        if (!id) return;
        const s = state(root);
        const rows = Array.isArray(s.mediaRows) ? s.mediaRows.slice() : [];
        const pos = rows.findIndex(item => Number(item.id || 0) === id);
        if (pos >= 0) rows[pos] = Object.assign({}, rows[pos], row);
        else rows.push(row);
        renderMedia(root, rows);
    }

    function setLocalMediaSlot(root, id, slot) {
        id = Number(id || 0);
        if (!id) return;
        const s = state(root);
        const row = (s.mediaRows || []).find(item => Number(item.id || 0) === id);
        if (!row) return;
        row.slot = slot;
        row.usage = slot;
        renderMedia(root);
    }

    function inlineVideoDataAttributes(row, id, options) {
        row = row || {};
        options = options || {};
        const videoUrl = row.embed_url || row.external_url || row.url || "";
        const attrs = [
            `data-cms-media-id="${id}"`,
            'data-cms-media-slot="inline"',
            `data-cms-video-url="${escapeHtml(videoUrl)}"`,
            `data-cms-video-type="${escapeHtml(row.media_type || "")}"`,
            `data-cms-video-mime="${escapeHtml(row.mime || "")}"`,
            `data-cms-video-autoplay="${options.autoplay ? "1" : "0"}"`,
            `data-cms-video-loop="${options.loop ? "1" : "0"}"`,
            `data-cms-video-muted="${options.muted ? "1" : "0"}"`
        ];
        if (options.width) attrs.push(`data-cms-video-width="${escapeHtml(options.width)}"`);
        if (options.height) attrs.push(`data-cms-video-height="${escapeHtml(options.height)}"`);
        return " " + attrs.join(" ");
    }

    function inlineVideoOptionsFromElement(el) {
        const media = el ? qs(el, ".dbx-cms-inline-video-thumb,.dbx-cms-inline-video-empty,video,iframe,img") : null;
        const attr = name => String((el && el.getAttribute && el.getAttribute(name)) || (media && media.getAttribute && media.getAttribute(name)) || "");
        return {
            width: cssSizeValue(attr("data-cms-video-width") || el?.style?.width || media?.style?.width || ""),
            height: cssSizeValue(attr("data-cms-video-height") || el?.style?.height || media?.style?.height || ""),
            autoplay: attr("data-cms-video-autoplay") === "1",
            loop: attr("data-cms-video-loop") === "1",
            muted: attr("data-cms-video-muted") === "1"
        };
    }

    function syncInlineVideoOptionsToMedia(wrapper, options) {
        if (!wrapper) return;
        options = options || {};
        const targets = [wrapper].concat(qsa(wrapper, ".dbx-cms-inline-video-thumb,.dbx-cms-inline-video-empty,.dbx-cms-inline-video-player,video,iframe,img"));
        targets.forEach(el => {
            if (!el || !el.setAttribute) return;
            if (options.width) el.setAttribute("data-cms-video-width", options.width);
            else el.removeAttribute("data-cms-video-width");
            if (options.height) el.setAttribute("data-cms-video-height", options.height);
            else el.removeAttribute("data-cms-video-height");
            el.setAttribute("data-cms-video-autoplay", options.autoplay ? "1" : "0");
            el.setAttribute("data-cms-video-loop", options.loop ? "1" : "0");
            el.setAttribute("data-cms-video-muted", options.muted ? "1" : "0");
        });
    }

    function inlineVideoOptionsButtonHtml() {
        return '<button type="button" class="dbx-cms-inline-video-options-btn" data-cms-inline-video-options-open contenteditable="false" tabindex="-1" title="Video Optionen" aria-label="Video Optionen"><i class="bi bi-sliders"></i></button>';
    }

    function mediaPlayerInnerHtml(row, id, options) {
        row = row || {};
        id = Number(id || row.id || row.media_id || 0);
        if (id <= 0) return "";
        const title = escapeHtml(row.title || row.alt || row.file_name || "Video");
        const thumb = row.thumb_url || "";
        const mediaAttr = inlineVideoDataAttributes(row, id, options || {});
        const img = thumb
            ? `<img class="dbx-cms-inline-video-thumb" src="${escapeHtml(thumb)}" alt="${title}" title="${title}"${mediaAttr}>`
            : `<span class="dbx-cms-inline-video-empty"${mediaAttr}>${title}</span>`;
        return `${img}<span class="dbx-cms-inline-video-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>${inlineVideoOptionsButtonHtml()}`;
    }

    function inlineVideoTarget(root, target) {
        const surface = editorSurface(root);
        if (!surface || !target) return null;
        const direct = closestElement(target, ".dbx-cms-inline-video-block");
        if (direct && surface.contains(direct)) return direct;
        const media = closestElement(target, ".dbx-cms-inline-video-player,iframe,video");
        return media && surface && surface.contains(media) ? media : null;
    }

    function inlineVideoTargetAtPoint(root, e) {
        const surface = editorSurface(root);
        if (!surface || !e || !Number.isFinite(Number(e.clientX)) || !Number.isFinite(Number(e.clientY))) return null;
        const x = Number(e.clientX);
        const y = Number(e.clientY);
        const selectors = ".dbx-cms-inline-video-block,.dbx-cms-inline-video-player,iframe,video";
        return qsa(surface, selectors).find(el => {
            const target = closestElement(el, ".dbx-cms-inline-video-block") || el;
            const rect = target.getBoundingClientRect ? target.getBoundingClientRect() : null;
            return rect && x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
        }) || null;
    }

    function inlineVideoEventTarget(root, e) {
        return inlineVideoTarget(root, e && e.target) || inlineVideoTargetAtPoint(root, e);
    }

    function cmsRootFromEditorElement(el) {
        if (!el) return null;
        const direct = closestElement(el, ".dbx-cms");
        if (direct) return direct;
        return qsa(document, ".dbx-cms[data-dbx]").find(root => {
            const surface = editorSurface(root);
            return root.contains(el) || (surface && surface.contains(el));
        }) || null;
    }

    function isInlineVideoResizeHandleEvent(wrapper, e) {
        if (!wrapper || !e || !wrapper.getBoundingClientRect) return false;
        const rect = wrapper.getBoundingClientRect();
        const grip = 22;
        return e.clientX >= rect.right - grip && e.clientY >= rect.bottom - grip;
    }

    function cssSizeValue(value) {
        value = String(value || "").trim();
        if (!value) return "";
        if (/^\d+(\.\d+)?$/.test(value)) return value + "px";
        if (/^\d+(\.\d+)?(px|%|vw|vh|rem|em)$/i.test(value)) return value;
        if (/^auto$/i.test(value)) return "auto";
        return "";
    }

    function openInlineVideoOptions(root, media) {
        if (!media) return false;
        const modal = ensureInlineVideoOptionsDialog(root);
        modal.__dbxCmsInlineVideo = media;
        const options = inlineVideoOptionsFromElement(media);
        const width = qs(modal, "[data-cms-video-options-width]");
        const height = qs(modal, "[data-cms-video-options-height]");
        const autoplay = qs(modal, "[data-cms-video-options-autoplay]");
        const loop = qs(modal, "[data-cms-video-options-loop]");
        const muted = qs(modal, "[data-cms-video-options-muted]");
        if (width) width.value = options.width || "";
        if (height) height.value = options.height || "";
        if (autoplay) autoplay.checked = !!options.autoplay;
        if (loop) loop.checked = !!options.loop;
        if (muted) muted.value = options.muted ? "1" : "0";
        modal.hidden = false;
        openInlineVideoOptionsWindow(root, modal);
        return true;
    }

    function applyInlineVideoOptions(root, modal) {
        const media = modal && modal.__dbxCmsInlineVideo;
        if (!media) return false;
        const width = cssSizeValue(qs(modal, "[data-cms-video-options-width]")?.value || "");
        const height = cssSizeValue(qs(modal, "[data-cms-video-options-height]")?.value || "");
        const autoplay = qs(modal, "[data-cms-video-options-autoplay]")?.checked;
        const loop = qs(modal, "[data-cms-video-options-loop]")?.checked;
        let muted = qs(modal, "[data-cms-video-options-muted]")?.value === "1";
        if (autoplay) muted = true;
        const options = {
            width,
            height,
            autoplay: !!autoplay,
            loop: !!loop,
            muted: !!muted
        };
        if (width) {
            media.style.width = width;
            media.setAttribute("data-cms-video-width", width);
        } else {
            media.style.width = "";
            media.removeAttribute("data-cms-video-width");
        }
        if (height) {
            media.style.height = height;
            media.setAttribute("data-cms-video-height", height);
        } else {
            media.style.height = "";
            media.removeAttribute("data-cms-video-height");
        }
        media.setAttribute("data-cms-video-autoplay", autoplay ? "1" : "0");
        media.setAttribute("data-cms-video-loop", loop ? "1" : "0");
        media.setAttribute("data-cms-video-muted", muted ? "1" : "0");
        syncInlineVideoOptionsToMedia(media, options);
        syncEditorAfterContextAction(root);
        markDirty(root);
        closeInlineVideoOptionsWindow(modal);
        return true;
    }

    function closeInlineVideoOptionsWindow(modal) {
        if (!modal) return;
        const winId = modal.__dbxCmsWindowId || "";
        modal.__dbxCmsInlineVideo = null;
        modal.hidden = true;
        modal.classList.remove("is-open");
        modal.__dbxCmsWindowId = null;
        modal.removeAttribute("role");
        modal.removeAttribute("aria-modal");
        modal.removeAttribute("aria-label");
        if (winId && dbx.openWin && typeof dbx.openWin.close === "function") {
            dbx.openWin.close(winId);
            return;
        }
    }

    function ensureInlineVideoOptionsDialog(root) {
        const s = state(root);
        let modal = s.videoOptionsModal || qs(root, "[data-cms-video-options]");
        if (modal) {
            modal.__dbxCmsRoot = root;
            s.videoOptionsModal = modal;
            if (!qs(modal, "[data-cms-video-options-loop]")) {
                const autoplayLabel = qs(modal, "[data-cms-video-options-autoplay]")?.closest("label");
                if (autoplayLabel && autoplayLabel.parentNode) {
                    const loopLabel = document.createElement("label");
                    loopLabel.className = "dbx-cms-video-options-check";
                    loopLabel.innerHTML = '<input type="checkbox" data-cms-video-options-loop> Auto Loop';
                    autoplayLabel.insertAdjacentElement("afterend", loopLabel);
                }
            }
            return modal;
        }
        modal = document.createElement("div");
        modal.className = "dbx-cms-video-options";
        modal.setAttribute("data-cms-video-options", "1");
        modal.__dbxCmsRoot = root;
        modal.hidden = true;
        modal.innerHTML = `
            <div class="dbx-cms-video-options-body">
                <label>Breite <input type="text" class="form-control form-control-sm" data-cms-video-options-width placeholder="z.B. 640px, 80%, leer = Standard"></label>
                <label>Hoehe <input type="text" class="form-control form-control-sm" data-cms-video-options-height placeholder="z.B. 240px, leer = 16:9"></label>
                <label class="dbx-cms-video-options-check"><input type="checkbox" data-cms-video-options-autoplay> Autoplay</label>
                <label class="dbx-cms-video-options-check"><input type="checkbox" data-cms-video-options-loop> Auto Loop</label>
                <label>Ton <select class="form-select form-select-sm" data-cms-video-options-muted><option value="0">Ton an</option><option value="1">Ton aus</option></select></label>
            </div>
            <div class="dbx-cms-video-options-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-video-options-close><i class="bi bi-x-lg"></i><span>Abbrechen</span></button>
                <button type="button" class="btn btn-primary btn-sm" data-cms-video-options-apply><i class="bi bi-check2"></i><span>Uebernehmen</span></button>
            </div>`;
        modal.addEventListener("click", e => {
            if (closestElement(e.target, "[data-cms-video-options-close]")) {
                e.preventDefault();
                e.stopPropagation();
                closeInlineVideoOptionsWindow(modal);
                return;
            }
            if (closestElement(e.target, "[data-cms-video-options-apply]")) {
                e.preventDefault();
                e.stopPropagation();
                applyInlineVideoOptions(root, modal);
            }
        });
        root.appendChild(modal);
        s.videoOptionsModal = modal;
        return modal;
    }

    function bindInlineVideoOptionsDocumentEvents() {
        if (document.__dbxCmsVideoOptionsEventsBound) return;
        document.__dbxCmsVideoOptionsEventsBound = true;
        const openOptionsFromButton = e => {
            if (e.button !== undefined && e.button !== 0) return false;
            const openBtn = closestElement(e.target, "[data-cms-inline-video-options-open]");
            if (!openBtn) return false;
            const root = cmsRootFromEditorElement(openBtn);
            const video = closestElement(openBtn, ".dbx-cms-inline-video-block");
            const surface = root ? editorSurface(root) : null;
            if (!root || !video || (surface && !surface.contains(video))) return false;
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
            const now = Date.now();
            if (!openBtn.__dbxCmsVideoOptionsOpenedAt || now - openBtn.__dbxCmsVideoOptionsOpenedAt > 250) {
                openBtn.__dbxCmsVideoOptionsOpenedAt = now;
                openInlineVideoOptions(root, video);
            }
            return true;
        };
        document.addEventListener("pointerdown", openOptionsFromButton, true);
        document.addEventListener("mousedown", openOptionsFromButton, true);
        document.addEventListener("click", e => {
            if (openOptionsFromButton(e)) return;
            const closeBtn = closestElement(e.target, "[data-cms-video-options-close]");
            const applyBtn = closestElement(e.target, "[data-cms-video-options-apply]");
            if (!closeBtn && !applyBtn) return;
            const modal = closestElement(e.target, "[data-cms-video-options]");
            if (!modal) return;
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
            if (closeBtn) {
                closeInlineVideoOptionsWindow(modal);
                return;
            }
            const root = modal.__dbxCmsRoot || closestElement(modal, ".dbx-cms");
            if (root) applyInlineVideoOptions(root, modal);
            else closeInlineVideoOptionsWindow(modal);
        }, true);
    }

    function openInlineVideoOptionsWindow(root, modal) {
        if (!modal) return false;
        const currentWindow = closestElement(modal, ".dbx-window");
        const oldOptionsWindowId = modal.__dbxCmsWindowId || "";
        if (currentWindow && currentWindow.parentNode) {
            document.body.appendChild(modal);
            if (oldOptionsWindowId && currentWindow.id === oldOptionsWindowId && dbx.openWin && typeof dbx.openWin.close === "function") {
                dbx.openWin.close(oldOptionsWindowId);
            }
        } else if (modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }
        modal.__dbxCmsRoot = root;
        modal.__dbxCmsWindowId = null;
        modal.hidden = false;
        modal.classList.add("is-open");
        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");
        modal.setAttribute("aria-label", "Video Optionen");
        const first = qs(modal, "[data-cms-video-options-width]") || qs(modal, "input,select,button");
        if (first && first.focus) window.setTimeout(() => first.focus({ preventScroll: true }), 0);
        return true;
    }

    function repairInlineVideoPlayers(root, container) {
        if (!container) return;
        const rows = state(root).mediaRows || [];
        qsa(container, ".dbx-cms-inline-media[data-cms-media-id]").forEach(wrapper => {
            const id = Number(wrapper.getAttribute("data-cms-media-id") || 0);
            if (!id) return;
            const row = rows.find(item => Number(item.id || item.media_id || 0) === id) || null;
            const needsVideo = wrapper.classList.contains("dbx-cms-inline-video-block") || (row && isVideoRow(row));
            if (!needsVideo || wrapper.querySelector(".dbx-cms-inline-video-thumb,.dbx-cms-inline-video-empty")) return;
            wrapper.classList.add("dbx-cms-inline-video-block");
            wrapper.removeAttribute("contenteditable");
            const options = inlineVideoOptionsFromElement(wrapper);
            wrapper.innerHTML = mediaPlayerInnerHtml(row, id, options);
            syncInlineVideoOptionsToMedia(wrapper, options);
        });
    }

    function repairInlineVideoHtml(root, html) {
        const wrap = document.createElement("div");
        wrap.innerHTML = String(html || "");
        repairInlineVideoPlayers(root, wrap);
        return wrap.innerHTML;
    }

    function mediaRowById(root, id) {
        id = Number(id || 0);
        if (!id) return null;
        return (state(root).mediaRows || []).find(row => Number(row.id || row.media_id || 0) === id) || null;
    }

    function youtubeEmbedFromThumb(src) {
        const match = String(src || "").match(/img\.youtube\.com\/vi\/([^/]+)/i);
        return match ? "https://www.youtube.com/embed/" + encodeURIComponent(match[1]) : "";
    }

    function inlineVideoRowFromWrapper(root, wrapper, id) {
        const row = mediaRowById(root, id);
        if (row) return row;
        const media = qs(wrapper, "[data-cms-media-id]") || wrapper;
        const thumb = qs(wrapper, ".dbx-cms-inline-video-thumb, img");
        const youtubeUrl = youtubeEmbedFromThumb(thumb?.getAttribute("src") || "");
        const url = wrapper.getAttribute("data-cms-video-url") || media?.getAttribute("data-cms-video-url") || youtubeUrl || (id ? `index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=${id}` : "");
        if (!url) return null;
        return {
            id,
            media_type: wrapper.getAttribute("data-cms-video-type") || media?.getAttribute("data-cms-video-type") || (youtubeUrl ? "external_video" : "video"),
            mime: wrapper.getAttribute("data-cms-video-mime") || media?.getAttribute("data-cms-video-mime") || "",
            url,
            embed_url: youtubeUrl ? url : "",
            thumb_url: thumb?.getAttribute("src") || "",
            title: thumb?.getAttribute("title") || thumb?.getAttribute("alt") || "Video"
        };
    }

    function urlWithParams(url, params) {
        try {
            const out = new URL(String(url || ""), window.location.href);
            Object.keys(params || {}).forEach(key => out.searchParams.set(key, params[key]));
            return out.toString();
        } catch (e) {
            return String(url || "");
        }
    }

    function youtubeVideoIdFromUrl(url) {
        const match = String(url || "").match(/(?:embed\/|v=|youtu\.be\/)([A-Za-z0-9_-]{11})/i);
        return match ? match[1] : "";
    }

    function externalVideoPlayerParams(url, options) {
        options = options || {};
        const params = { playsinline: "1", rel: "0" };
        if (options.autoplay) params.autoplay = "1";
        params.mute = options.muted ? "1" : "0";
        if (options.loop) {
            params.loop = "1";
            const videoId = youtubeVideoIdFromUrl(url);
            if (videoId) params.playlist = videoId;
        }
        return params;
    }

    function inlineVideoEditorPlayerHtml(row, id, options) {
        row = row || {};
        options = options || {};
        id = Number(id || row.id || row.media_id || 0);
        const title = escapeHtml(row.title || row.alt || row.file_name || "Video");
        const mediaAttr = inlineVideoDataAttributes(row, id, options);
        if (isExternalVideoRow(row)) {
            let url = row.embed_url || row.external_url || row.url || "";
            if (!url) return "";
            url = urlWithParams(url, externalVideoPlayerParams(url, options));
            return `<iframe class="dbx-cms-inline-video-player" src="${escapeHtml(url)}" title="${title}" allowfullscreen${mediaAttr}></iframe>${inlineVideoOptionsButtonHtml()}`;
        }
        const url = row.url || (id ? `index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=${id}` : "");
        if (!url) return "";
        const mime = row.mime ? ` type="${escapeHtml(row.mime)}"` : "";
        const poster = row.thumb_url ? ` poster="${escapeHtml(row.thumb_url)}"` : "";
        const autoplayAttr = options.autoplay ? " autoplay" : "";
        const loopAttr = options.loop ? " loop" : "";
        const mutedAttr = options.muted ? " muted" : "";
        return `<video class="dbx-cms-inline-video-player" controls${autoplayAttr}${loopAttr}${mutedAttr} playsinline preload="metadata"${poster}${mediaAttr}><source src="${escapeHtml(url)}"${mime}></video>${inlineVideoOptionsButtonHtml()}`;
    }

    function playInlineVideoBlock(root, wrapper) {
        const surface = editorSurface(root);
        if (!wrapper || !surface || !surface.contains(wrapper)) return false;
        if (wrapper.querySelector(".dbx-cms-inline-video-player, iframe, video")) return false;
        const id = Number(wrapper.getAttribute("data-cms-media-id") || qs(wrapper, "[data-cms-media-id]")?.getAttribute("data-cms-media-id") || 0);
        const row = inlineVideoRowFromWrapper(root, wrapper, id);
        if (!row || !isVideoRow(row)) {
            status(root, "Video konnte im Editor nicht geladen werden.", "error");
            return false;
        }
        syncInlineVideoBlockSizes(surface);
        const html = inlineVideoEditorPlayerHtml(row, id, {
            autoplay: wrapper.getAttribute("data-cms-video-autoplay") === "1",
            loop: wrapper.getAttribute("data-cms-video-loop") === "1",
            muted: wrapper.getAttribute("data-cms-video-muted") === "1"
        });
        if (!html) return false;
        wrapper.innerHTML = html;
        wrapper.classList.add("is-playing");
        syncEditorDom(root);
        return true;
    }

    function renderHeroPreview(root) {
        const preview = qs(root, "[data-cms-hero-preview]");
        if (!preview) return;

        const templateValue = String(getField(root, "hero_template") || "").toLowerCase();
        if (templateValue === "none" || templateValue === "no-hero") {
            preview.innerHTML = '<div class="dbx-cms-hero-preview-empty">Kein Hero ausgewaehlt.</div>';
            return;
        }

        const idValue = String(getField(root, "hero_image_id") || "");
        const id = Number(idValue);
        const s = state(root);
        const rows = s.mediaRows || [];
        const row = rows.find(item => Number(item.id || 0) === id)
            || (s.heroPreviewRow && Number(s.heroPreviewRow.id || 0) === id ? s.heroPreviewRow : null);

        if (idValue === "parent") {
            const parentRow = s.heroParentPreviewRow || null;
            if (parentRow && parentRow.url) {
                const folderName = parentRow.parent_folder_name || parentRow.folder_name || "Parent";
                preview.innerHTML = `<img src="${escapeHtml(parentRow.thumb_url || parentRow.url)}" alt="${escapeHtml(parentRow.alt || parentRow.title || "Hero-Bild")}"><figcaption class="dbx-cms-hero-preview-origin">${escapeHtml(folderName)}</figcaption>`;
                return;
            }
            preview.innerHTML = '<div class="dbx-cms-hero-preview-empty">'
                + escapeHtml(cmsText(root, "hero_parent_empty", "Kein Hero im übergeordneten Ordner."))
                + '</div>';
            return;
        }

        if (!id) {
            preview.innerHTML = '<div class="dbx-cms-hero-preview-empty">Kein Hero-Bild ausgewaehlt.</div>';
            return;
        }

        if (!row || !row.url) {
            preview.innerHTML = '<div class="dbx-cms-hero-preview-empty">Hero-Bild wird geladen.</div>';
            return;
        }

        const isImage = String(row.mime || "").startsWith("image/") || /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(row.file_name || row.url || "");
        if (!isImage) {
            preview.innerHTML = '<div class="dbx-cms-hero-preview-empty">Das gewaehlte Medium ist kein Bild.</div>';
            return;
        }

        preview.innerHTML = `<img src="${escapeHtml(row.thumb_url || row.url)}" alt="${escapeHtml(row.alt || row.title || "Hero-Bild")}">`;
    }

    function applyHeroTemplateChoice(root, source) {
        const isFolder = source && source.hasAttribute && source.hasAttribute("data-cms-folder-field");
        const value = String(isFolder ? getFolderField(root, "hero_template") : getField(root, "hero_template")).toLowerCase();
        const folderPanel = qs(root, "[data-cms-folder-editor]");
        if (value) {
            setField(root, "hero_template", value);
            if (folderPanel && !folderPanel.hidden) setFolderField(root, "hero_template", value);
        }
        if (value === "parent") {
            setField(root, "hero_image_id", "parent");
            if (folderPanel && !folderPanel.hidden) setFolderField(root, "hero_image_id", "parent");
            renderHeroPreview(root);
            return;
        }
        if (value !== "none" && value !== "no-hero") {
            renderHeroPreview(root);
            return;
        }
        state(root).heroPreviewRow = null;
        setField(root, "hero_template", "none");
        setField(root, "hero_image_id", "0");
        if (folderPanel && !folderPanel.hidden) {
            setFolderField(root, "hero_template", "none");
            setFolderField(root, "hero_image_id", "0");
        }
        renderHeroPreview(root);
        renderMedia(root);
    }

    function renderSeoPreview(root) {
        const preview = qs(root, "[data-cms-seo-preview]");
        if (!preview) return;

        const id = Number(getField(root, "seo_image_id") || 0);
        const s = state(root);
        const rows = s.mediaRows || [];
        const row = rows.find(item => Number(item.id || 0) === id)
            || (s.seoPreviewRow && Number(s.seoPreviewRow.id || 0) === id ? s.seoPreviewRow : null);

        if (!id) {
            preview.innerHTML = '<div class="dbx-cms-seo-preview-empty">Kein OG-Bild ausgewaehlt (Hero-Fallback).</div>';
            return;
        }

        if (!row || !row.url) {
            preview.innerHTML = '<div class="dbx-cms-seo-preview-empty">OG-Bild wird geladen.</div>';
            return;
        }

        const isImage = String(row.mime || "").startsWith("image/") || /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(row.file_name || row.url || "");
        if (!isImage) {
            preview.innerHTML = '<div class="dbx-cms-seo-preview-empty">Das gewaehlte Medium ist kein Bild.</div>';
            return;
        }

        preview.innerHTML = `<img src="${escapeHtml(row.thumb_url || row.url)}" alt="${escapeHtml(row.alt || row.title || "OG-Bild")}">`;
    }

    function mediaBrowserRows(modal) {
        return qsa(modal, "[data-cms-media-browser-item]").map(mediaRowFromItem);
    }

    function updateMediaBrowserConfirm(modal) {
        const bar = qs(modal, "[data-cms-media-browser-confirmbar]");
        const count = qs(modal, "[data-cms-media-browser-count]");
        const selected = modal.__dbxCmsSelectedIds || new Set();
        const needsConfirm = mediaBrowserUsesConfirmBar(modal);
        if (bar) bar.hidden = !needsConfirm;
        if (count) count.textContent = String(selected.size);
        updateMediaBrowserBatchUi(modal);
    }

    function selectedMediaBrowserImageRows(modal) {
        const selected = modal && modal.__dbxCmsSelectedIds ? modal.__dbxCmsSelectedIds : new Set();
        return mediaBrowserAllRows(modal).filter(row => selected.has(Number(row.id || 0)) && canEditImage(row));
    }

    function mediaBrowserAllRows(modal) {
        return Array.isArray(modal && modal.__dbxCmsRows) ? modal.__dbxCmsRows : mediaBrowserRows(modal);
    }

    function batchControlHost(browserModal) {
        if (!browserModal) return null;
        return browserModal.__dbxCmsBatchPanel || browserModal;
    }

    function updateMediaBrowserBatchUi(modal) {
        const selectedImages = selectedMediaBrowserImageRows(modal).length;
        [modal, modal && modal.__dbxCmsBatchPanel].forEach(host => {
            if (!host) return;
            const count = qs(host, "[data-cms-media-browser-resize-count]");
            if (count) count.textContent = String(selectedImages);
        });
    }

    function firstMediaFolderOption(select, prefix) {
        prefix = String(prefix || "");
        return Array.from(select?.options || []).find(option => {
            const value = String(option.value || "");
            return value && value !== "all" && (!prefix || value.indexOf(prefix) === 0);
        })?.value || "";
    }

    function mediaFolderLabel(folder) {
        return String(folder || "").trim();
    }

    function mediaOriginLabel(row) {
        row = row || {};
        let folder = String(row.media_folder || "").trim().replace(/\\/g, "/");
        if (!folder) {
            let path = String(row.file_path || row.path || "").trim().replace(/\\/g, "/");
            path = path.replace(/^files\/media\//i, "").replace(/^media\//i, "");
            const slash = path.lastIndexOf("/");
            folder = slash >= 0 ? path.slice(0, slash) : "";
        }
        folder = folder.replace(/^files\/media\//i, "").replace(/^media\//i, "").replace(/^\/+|\/+$/g, "");
        return folder || "Root";
    }

    function mediaSlotLabel(slot) {
        const slotLabels = { inline: "Im Text", hero: "Hero", gallery: "Galerie", shop: "Shop" };
        slot = String(slot || "").trim();
        return slotLabels[slot] || slot || "Verwendung";
    }

    function mediaUsagePages(row) {
        return Array.isArray(row && row.usage_pages) ? row.usage_pages : [];
    }

    function mediaUsageLabel(row) {
        row = row || {};
        const slot = String(row.slot || "").trim();
        const pages = mediaUsagePages(row);
        const ids = pages.map(page => Number(page.content_id || page.id || 0)).filter(Boolean);
        const shownIds = ids.slice(0, 3).map(id => "#" + id).join(", ");
        const suffix = ids.length > 3 ? ", ..." : "";
        const currentUsageId = Number(row.current_usage_id || row.usage_id || 0);
        if (currentUsageId > 0 && slot) {
            return mediaSlotLabel(slot) + (shownIds ? ": " + shownIds + suffix : "");
        }
        const count = Number(row.used_count || 0);
        if (ids.length > 0) return "Verwendet: " + shownIds + suffix;
        if (count > 0) return count === 1 ? "Verwendet 1x" : "Verwendet " + count + "x";
        return "Nicht verwendet";
    }

    function mediaUsageTooltip(row) {
        row = row || {};
        const pages = mediaUsagePages(row);
        if (pages.length) {
            return pages.map(page => {
                const id = Number(page.content_id || page.id || 0);
                const folderId = Number(page.folder_id || 0);
                const folder = String(page.folder_title || "").trim();
                const title = String(page.title || "").trim();
                const slots = Array.isArray(page.slots) ? page.slots.map(mediaSlotLabel).filter(Boolean).join(", ") : mediaSlotLabel(row.slot || "");
                return "#" + id
                    + (folderId > 0 || folder ? " | Ordner: " + (folder || ("#" + folderId)) : "")
                    + (title ? " | " + title : "")
                    + (slots ? " | " + slots : "");
            }).join("\n");
        }
        return mediaUsageLabel(row);
    }

    function mediaUsageTooltipRows(row) {
        row = row || {};
        const pages = mediaUsagePages(row);
        return pages.map(page => {
            const id = Number(page.content_id || page.id || 0);
            const folderId = Number(page.folder_id || 0);
            const folder = String(page.folder_title || "").trim();
            const title = String(page.title || "").trim();
            return {
                id: id > 0 ? "#" + id : "",
                folder: folder || (folderId > 0 ? "#" + folderId : "-"),
                title: title || "-"
            };
        });
    }

    function mediaUsageTooltipHtml(row) {
        const rows = mediaUsageTooltipRows(row);
        const tooltip = window.dbx && dbx.utilities && dbx.utilities.tooltip;
        if (tooltip && typeof tooltip.htmlList === "function") {
            return tooltip.htmlList(rows, {
                title: rows.length ? "Verwendet von" : "",
                empty: mediaUsageLabel(row)
            });
        }
        if (!rows.length) {
            return `<div>${escapeHtml(mediaUsageLabel(row))}</div>`;
        }
        return `<strong>Verwendet von</strong><br>` + rows.map(item => {
            return `${escapeHtml(item.id)} ${escapeHtml(item.folder)} ${escapeHtml(item.title)}`;
        }).join("<br>");
    }

    function mediaBrowserItemHtml(row, selected, needsConfirm) {
        row = row || {};
        selected = selected || new Set();
        return `<div class="dbx-cms-media-browser-item${selected.has(Number(row.id || 0)) ? " is-selected" : ""}"
            data-cms-media-browser-item
            data-media-id="${escapeHtml(row.id || "")}"
            data-url="${escapeHtml(row.url || "")}"
            data-thumb-url="${escapeHtml(row.thumb_url || "")}"
            data-mime="${escapeHtml(row.mime || "")}"
            data-media-type="${escapeHtml(row.media_type || "")}"
            data-width="${escapeHtml(row.width || "")}"
            data-height="${escapeHtml(row.height || "")}"
            data-file-name="${escapeHtml(row.file_name || "")}"
            data-file-path="${escapeHtml(row.file_path || "")}"
            data-title="${escapeHtml(row.title || "")}"
            data-alt="${escapeHtml(row.alt || "")}"
            data-media-folder="${escapeHtml(row.media_folder || "")}"
            data-slot="${escapeHtml(row.slot || "")}"
            draggable="${isExternalVideoRow(row) ? "false" : "true"}">
            <button type="button" class="dbx-cms-media-browser-pickarea" data-cms-media-browser-pick draggable="${isExternalVideoRow(row) ? "false" : "true"}" title="${needsConfirm ? "Medium fuer Auswahl markieren" : "Medium in den Editor einfuegen"}">
                <span>${mediaPreviewHtml(row)}</span>
                <strong>${escapeHtml(row.title || row.file_name || "Medium")}</strong>
                ${canEditImage(row) || needsConfirm ? '<em class="dbx-cms-media-browser-check"><i class="bi bi-check2"></i></em>' : ''}
            </button>
            <div class="dbx-cms-media-browser-actions">
                <span class="dbx-cms-media-browser-meta">
                    <span class="dbx-cms-media-browser-origin">${escapeHtml(mediaOriginLabel(row))}</span>
                    <span class="dbx-cms-media-browser-usage" tabindex="0" data-dbx-tooltip="${escapeTooltipAttr(mediaUsageTooltipHtml(row))}">${escapeHtml(mediaUsageLabel(row))}</span>
                </span>
                ${canEditImage(row) ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-browser-select title="Bild fuer Batch Resize auswaehlen"><i class="bi bi-check2-square"></i></button>' : ''}
                ${canEditImage(row) ? '<button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-browser-edit title="Bild zuschneiden oder resizen"><i class="bi bi-crop"></i></button>' : ''}
                <button type="button" class="btn btn-outline-danger btn-sm" data-cms-media-browser-delete title="Mediendatei wirklich loeschen">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>`;
    }

    function mediaBrowserExplorerItemHtml(row, selected) {
        row = row || {};
        selected = selected || new Set();
        const id = Number(row.id || 0);
        const title = row.title || row.file_name || "Medium";
        const draggable = isExternalVideoRow(row) ? "false" : "true";
        const originLabel = mediaOriginLabel(row);
        const usageLabel = mediaUsageLabel(row);
        const usageTooltip = mediaUsageTooltipHtml(row);
        return `<div class="dbx-cms-media-explorer-item dbx-cms-media-browser-item${selected.has(id) ? " is-selected" : ""}"
            data-cms-media-browser-item
            data-cms-media-tree-media
            data-media-id="${escapeHtml(row.id || "")}"
            data-url="${escapeHtml(row.url || "")}"
            data-thumb-url="${escapeHtml(row.thumb_url || "")}"
            data-mime="${escapeHtml(row.mime || "")}"
            data-media-type="${escapeHtml(row.media_type || "")}"
            data-width="${escapeHtml(row.width || "")}"
            data-height="${escapeHtml(row.height || "")}"
            data-file-name="${escapeHtml(row.file_name || "")}"
            data-file-path="${escapeHtml(row.file_path || "")}"
            data-title="${escapeHtml(row.title || "")}"
            data-alt="${escapeHtml(row.alt || "")}"
            data-media-folder="${escapeHtml(row.media_folder || "")}"
            data-slot="${escapeHtml(row.slot || "")}"
            draggable="${draggable}">
                <button type="button" class="dbx-cms-media-explorer-pick dbx-cms-media-browser-pickarea" data-cms-media-browser-pick draggable="${draggable}" title="Medium auswaehlen">
                    <span class="dbx-cms-media-explorer-thumb">${mediaPreviewHtml(row)}</span>
                    <strong>${escapeHtml(title)}</strong>
                    <small>${escapeHtml(row.file_name || "")}</small>
                    <em class="dbx-cms-media-browser-check"><i class="bi bi-check2"></i></em>
                </button>
                <div class="dbx-cms-media-explorer-actions">
                    <span class="dbx-cms-media-browser-meta">
                        <span class="dbx-cms-media-browser-origin">${escapeHtml(originLabel)}</span>
                        <span class="dbx-cms-media-browser-usage" tabindex="0" data-dbx-tooltip="${escapeTooltipAttr(usageTooltip)}">${escapeHtml(usageLabel)}</span>
                    </span>
                    ${canEditImage(row) ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-browser-select title="Bild fuer Batch Resize auswaehlen"><i class="bi bi-check2-square"></i></button>' : ''}
                    ${canEditImage(row) ? '<button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-browser-edit title="Bild zuschneiden oder resizen"><i class="bi bi-crop"></i></button>' : ''}
                    <button type="button" class="btn btn-outline-danger btn-sm" data-cms-media-browser-delete title="Mediendatei wirklich loeschen"><i class="bi bi-trash"></i></button>
                </div>
            </div>`;
    }

    function renderMediaRowsChunked(host, rows, rowHtml, afterDone) {
        if (!host) return;
        rows = Array.isArray(rows) ? rows : [];
        const token = ((host.__dbxMediaRenderToken || 0) + 1);
        host.__dbxMediaRenderToken = token;
        if (host.__dbxMediaRenderScrollHandler) {
            host.removeEventListener("scroll", host.__dbxMediaRenderScrollHandler);
            host.__dbxMediaRenderScrollHandler = null;
        }
        host.innerHTML = "";
        if (!rows.length) {
            if (afterDone) afterDone();
            return;
        }
        const batchSize = 18;
        let offset = 0;
        let rendering = false;
        const appendBatch = () => {
            if (host.__dbxMediaRenderToken !== token || rendering) return;
            rendering = true;
            const html = rows.slice(offset, offset + batchSize).map(rowHtml).join("");
            if (html) {
                host.insertAdjacentHTML("beforeend", html);
                setupMediaLazyImages(host);
            }
            offset += batchSize;
            rendering = false;
            if (afterDone) {
                afterDone();
            }
        };

        const appendWhenNeeded = () => {
            if (host.__dbxMediaRenderToken !== token || offset >= rows.length) return;
            const distance = host.scrollHeight - host.scrollTop - host.clientHeight;
            if (distance < 220) appendBatch();
            if (host.scrollHeight <= host.clientHeight + 40 && offset < rows.length) {
                window.requestAnimationFrame(appendWhenNeeded);
            }
        };

        host.__dbxMediaRenderScrollHandler = () => {
            window.requestAnimationFrame(appendWhenNeeded);
            scheduleMediaLazyLoad(host);
        };
        host.addEventListener("scroll", host.__dbxMediaRenderScrollHandler, { passive: true });
        appendBatch();
        window.requestAnimationFrame(appendWhenNeeded);
    }

    function mediaBrowserSkeletonHtml(count) {
        count = Math.max(6, Math.min(36, Number(count || 18)));
        let html = "";
        for (let i = 0; i < count; i++) {
            html += `<div class="dbx-cms-media-browser-item dbx-cms-media-browser-skeleton" aria-hidden="true">
                <span class="dbx-cms-media-browser-skeleton-thumb"></span>
                <span class="dbx-cms-media-browser-skeleton-line"></span>
                <span class="dbx-cms-media-browser-skeleton-meta"></span>
            </div>`;
        }
        return html;
    }

    function mediaFolderDiskPath(folder) {
        folder = String(folder || "").trim();
        if (!folder) return "";
        if (folder === "mod") return "files/mod/";
        return "files/media/" + folder.replace(/\\/g, "/") + "/";
    }

    function mediaBrowserQueryParams(folder) {
        const params = { sync: 0 };
        folder = String(folder || "all");
        if (folder === "all") {
            params.media_folder = "all";
        } else {
            params.media_folder = folder;
            if (folder === "youtube" || folder.indexOf("youtube/") === 0) {
                params.media_type = "external_video";
        } else if (folder.indexOf("videos/") === 0 || folder === "videos" || folder.indexOf("video/") === 0 || folder === "video" || folder === "img/video" || folder.indexOf("img/video/") === 0) {
            params.media_type = "video";
            } else if (folder.indexOf("file/") === 0 || folder === "file") {
                params.media_type = "file";
            } else if (folder === "module" || folder === "mod") {
                params.images = 1;
                params.media_type = "image";
            } else {
                params.images = 1;
                params.media_type = "image";
            }
        }
        return params;
    }

    function mediaTypeFromFolder(folder) {
        folder = String(folder || "");
        if (folder.indexOf("videos/") === 0 || folder === "videos" || folder.indexOf("video/") === 0 || folder === "video") return "video";
        if (folder.indexOf("file/") === 0 || folder === "file") return "file";
        return "image";
    }

    function buildNewMediaFolderPath(parentFolder, name) {
        parentFolder = String(parentFolder || "").trim();
        name = String(name || "").trim().replace(/\\/g, "/");
        if (!name) return "";
        if (name.indexOf("/") >= 0) return name;
        if (!parentFolder) return "img/" + name;
        const base = parentFolder.split("/")[0] || "img";
        if (base === "img" || base === "video" || base === "file" || base === "module") {
            return base + "/" + name;
        }
        return parentFolder + "/" + name;
    }

    function ensureMediaBrowserFolderUi(modal) {
        if (!modal) return;
        const uploadForm = qs(modal, "[data-cms-browser-upload]");
        const uploadFolder = qs(modal, "[data-cms-upload-folder]");
        if (uploadForm && uploadFolder && !uploadFolder.closest(".dbx-cms-upload-folder-label")) {
            const label = document.createElement("label");
            label.className = "dbx-cms-upload-folder-label";
            label.innerHTML = '<span class="form-label small mb-1">Zielordner</span>';
            uploadFolder.parentNode.insertBefore(label, uploadFolder);
            label.appendChild(uploadFolder);
        }
        const typeSelect = qs(modal, "[data-cms-folder-type]");
        if (typeSelect) {
            const folderBar = typeSelect.closest(".dbx-cms-media-folderbar");
            const parent = document.createElement("select");
            parent.className = typeSelect.className;
            parent.setAttribute("data-cms-folder-parent", "1");
            parent.title = "Vorhandenen Ordner als Basis waehlen";
            typeSelect.replaceWith(parent);
            if (folderBar && !qs(folderBar, ".dbx-cms-media-folderbar-title")) {
                const title = document.createElement("span");
                title.className = "small text-muted dbx-cms-media-folderbar-title";
                title.textContent = "Neuer Unterordner:";
                folderBar.insertBefore(title, parent);
            }
        }
        if (!modal.__dbxCmsUploadFolderSyncBound) {
            modal.__dbxCmsUploadFolderSyncBound = true;
            modal.addEventListener("change", e => {
                const uploadSelect = closestElement(e.target, "[data-cms-upload-folder]");
                if (!uploadSelect || !modal.contains(uploadSelect)) return;
                uploadSelect.__dbxCmsTouched = true;
                const parentSelect = qs(modal, "[data-cms-folder-parent]");
                if (parentSelect && Array.from(parentSelect.options).some(option => option.value === uploadSelect.value)) {
                    parentSelect.value = uploadSelect.value;
                }
            });
        }
    }

    function disposeMediaBrowserModal(root, modal) {
        if (!modal) return;
        if (modal.__dbxCmsBatchWindowId && dbx.openWin && typeof dbx.openWin.close === "function") {
            dbx.openWin.close(modal.__dbxCmsBatchWindowId);
        }
        if (modal.__dbxCmsBatchPanel) {
            modal.__dbxCmsBatchPanel.remove();
            modal.__dbxCmsBatchPanel = null;
        }
        if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.close === "function") {
            dbx.openWin.close(modal.__dbxCmsWindowId);
        }
        modal.remove();
        if (state(root).mediaBrowser === modal) state(root).mediaBrowser = null;
    }

    function mediaBatchWindowMarkup() {
        return `
            <div class="dbx-cms-media-batch-toolbar">
                <span class="dbx-cms-media-browser-resize-count"><strong data-cms-media-browser-resize-count>0</strong> Bilder ausgewaehlt</span>
                <select class="form-select form-select-sm" data-cms-media-browser-resize-preset title="Resize-Groesse">
                    <option value="">Groesse waehlen</option>
                    <option value="800x600">800 x 600</option>
                    <option value="1024x768">1024 x 768</option>
                    <option value="1280x720">1280 x 720 HD</option>
                    <option value="1600x900">1600 x 900</option>
                    <option value="1920x1080">1920 x 1080 Full HD</option>
                    <option value="2560x1440">2560 x 1440 QHD</option>
                </select>
                <input type="number" class="form-control form-control-sm" data-cms-bulk-resize-width placeholder="Breite">
                <input type="number" class="form-control form-control-sm" data-cms-bulk-resize-height placeholder="Hoehe">
                <label class="dbx-cms-media-resize-ratio" title="Seitenverhaeltnis beim Resize behalten">
                    <input type="checkbox" data-cms-bulk-resize-ratio checked>
                    <span>Ratio</span>
                </label>
                <button type="button" class="btn btn-outline-primary btn-sm" data-cms-action="bulk-resize-media" data-cms-resize-scope="selected">
                    <i class="bi bi-check2-square"></i>
                    <span>Auswahl resizen</span>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" data-cms-action="bulk-resize-media" data-cms-resize-scope="all">
                    <i class="bi bi-images"></i>
                    <span>Alle resizen</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-batch-close>
                    <i class="bi bi-arrow-left"></i>
                    <span>Zurueck</span>
                </button>
            </div>
            <div class="dbx-cms-media-batch-list" data-cms-media-batch-list>
                <div class="dbx-cms-empty">Bilder zum Batch-Resize auswaehlen.</div>
            </div>`;
    }

    function bindMediaBatchWindowEvents(root, cfg, browserModal, batchPanel) {
        if (!batchPanel || batchPanel.__dbxCmsBatchEventsBound) return;
        batchPanel.__dbxCmsBatchEventsBound = true;
        batchPanel.__dbxCmsBrowserModal = browserModal;

        batchPanel.addEventListener("click", e => {
            e.stopPropagation();
            const cfg = browserCfg(browserModal);

            const batchClose = closestElement(e.target, "[data-cms-media-batch-close]");
            if (batchClose && batchPanel.contains(batchClose)) {
                e.preventDefault();
                batchPanel.hidden = true;
                if (browserModal) browserModal.classList.remove("is-batch-open");
                return;
            }

            const batchItem = closestElement(e.target, "[data-cms-batch-item]");
            if (batchItem && batchPanel.contains(batchItem)) {
                e.preventDefault();
                toggleMediaBrowserSelection(browserModal, batchItem);
                return;
            }

            const bulkResize = closestElement(e.target, "[data-cms-action='bulk-resize-media']");
            if (bulkResize && batchPanel.contains(bulkResize)) {
                e.preventDefault();
                bulkResizeMedia(root, cfg, bulkResize.getAttribute("data-cms-resize-scope") || "selected", browserModal);
                return;
            }

            const processClose = closestElement(e.target, "[data-cms-media-process-close]");
            if (processClose && batchPanel.contains(processClose)) {
                e.preventDefault();
                const panel = closestElement(processClose, "[data-cms-media-process-panel]");
                if (panel) {
                    panel.hidden = true;
                    panel.innerHTML = "";
                }
                if (browserModal) browserModal.classList.remove("is-process-open");
                clearCmsLoading(root);
            }
        });

        batchPanel.addEventListener("change", e => {
            e.stopPropagation();
            const preset = closestElement(e.target, "[data-cms-media-browser-resize-preset]");
            if (preset && batchPanel.contains(preset)) {
                mediaBrowserPreset(batchPanel, preset.value);
                return;
            }
            const ratioInput = closestElement(e.target, "[data-cms-bulk-resize-ratio]");
            if (ratioInput && batchPanel.contains(ratioInput)) {
                status(root, ratioInput.checked ? "Resize behaelt das Seitenverhaeltnis." : "Resize nutzt exakte Breite und Hoehe.", "info");
            }
        });
    }

    function mediaBatchWindowIsOpen(browserModal) {
        const panel = browserModal && browserModal.__dbxCmsBatchPanel;
        if (!panel || !browserModal.__dbxCmsBatchWindowId) return false;
        return document.documentElement.contains(panel) && !!closestElement(panel, ".dbx-window");
    }

    function elevateMediaBatchWindow(browserModal) {
        const panel = browserModal && browserModal.__dbxCmsBatchPanel;
        const win = (browserModal && browserModal.__dbxCmsBatchWindowId ? document.getElementById(browserModal.__dbxCmsBatchWindowId) : null)
            || (panel ? closestElement(panel, ".dbx-window") : null);
        if (!win) return;
        win.classList.add("dbx-cms-media-batch-window-frame");
    }

    function openMediaBatchWindow(root, cfg, browserModal) {
        if (!browserModal) return;
        let batchPanel = browserModal.__dbxCmsBatchPanel;
        if (!batchPanel || !document.documentElement.contains(batchPanel) || !qs(batchPanel, "[data-cms-media-batch-list]")) {
            if (batchPanel) batchPanel.remove();
            batchPanel = document.createElement("div");
            batchPanel.className = "dbx-cms-media-batch-window";
            batchPanel.setAttribute("data-cms-media-batch-window", "1");
            batchPanel.innerHTML = mediaBatchWindowMarkup();
            const processPanel = qs(browserModal, "[data-cms-media-process-panel]");
            if (processPanel && processPanel.parentNode) {
                processPanel.parentNode.insertBefore(batchPanel, processPanel);
            } else {
                browserModal.appendChild(batchPanel);
            }
            browserModal.__dbxCmsBatchPanel = batchPanel;
            bindMediaBatchWindowEvents(root, cfg, browserModal, batchPanel);
        }
        browserModal.__dbxCmsBatchWindowId = null;
        browserModal.classList.remove("is-folder-tree-open", "is-process-open");
        browserModal.classList.add("is-batch-open");
        const processPanel = qs(browserModal, "[data-cms-media-process-panel]");
        if (processPanel) {
            processPanel.hidden = true;
            processPanel.innerHTML = "";
        }
        batchPanel.hidden = false;
        batchPanel.__dbxCmsBrowserModal = browserModal;
        batchPanel.__dbxCmsCfg = cfg || browserCfg(browserModal);
        updateMediaBrowserBatchUi(browserModal);
        renderMediaBatchList(browserModal);
    }

    function mediaBrowserNeedsRebuild(modal) {
        if (!modal) return false;
        return !!qs(modal, "[data-cms-folder-type]")
            || !qs(modal, ".dbx-cms-upload-target")
            || !qs(modal, "[data-cms-media-folder-toggle]")
            || !qs(modal, "[data-cms-media-folder-tree]")
            || !qs(modal, "[data-cms-media-process-panel]")
            || !qs(modal, "[data-cms-folder-rename]")
            || !!qs(modal, "[data-cms-media-browser-slot] option[value='header']")
            || !!qs(modal, "[data-cms-media-tools-toggle]")
            || !qs(modal, "[data-cms-media-batch-open]");
    }

    function uploadFolderOptions(folders) {
        return (folders || []).filter(folder => {
            const value = String(folder || "");
            if (!value || value === "all") return false;
            if (value === "youtube" || value.indexOf("youtube/") === 0) return false;
            if (value === "module" || value.indexOf("module/") === 0) return false;
            if (value === "img/video" || value.indexOf("img/video/") === 0) return false;
            return true;
        });
    }

    function syncUploadFolderSelect(modal, uploadFolders, preferred) {
        const uploadFolder = qs(modal, "[data-cms-upload-folder]");
        if (!uploadFolder || uploadFolder.__dbxCmsTouched) return;
        const filterFolder = qs(modal, "[data-cms-media-browser-folder]");
        const browse = String(filterFolder?.value || "");
        const candidates = [
            String(preferred || ""),
            browse !== "all" ? browse : "",
            String(modal.__dbxCmsMediaFolder || "")
        ];
        for (let i = 0; i < candidates.length; i++) {
            const cand = candidates[i];
            if (cand && uploadFolders.includes(cand)) {
                uploadFolder.value = cand;
                return;
            }
        }
        const imageFolder = uploadFolders.find(folder => folder.indexOf("img/") === 0);
        if (imageFolder) uploadFolder.value = imageFolder;
        else if (uploadFolders.length) uploadFolder.value = uploadFolders[0];
    }

    function setSelectOptions(select, folders, includeAll) {
        if (!select) return;
        const current = select.value || "";
        const options = [];
        if (includeAll) options.push(`<option value="all">${escapeHtml(cmsText(select.closest(".dbx-cms"), "media_all_folders", "Alle Verzeichnisse"))}</option>`);
        (folders || []).forEach(folder => {
            const label = mediaFolderLabel(folder);
            const hint = mediaFolderDiskPath(folder);
            options.push(`<option value="${escapeHtml(folder)}" title="${escapeHtml(hint)}">${escapeHtml(label)}</option>`);
        });
        select.innerHTML = options.join("");
        if (current && Array.from(select.options).some(option => option.value === current)) {
            select.value = current;
        }
    }

    function mediaFolderParent(folder) {
        folder = String(folder || "").replace(/\/+$/g, "");
        const idx = folder.lastIndexOf("/");
        return idx > 0 ? folder.slice(0, idx) : "";
    }

    function mediaFolderName(folder) {
        folder = String(folder || "").replace(/\/+$/g, "");
        return folder.split("/").pop() || folder;
    }

    function mediaFolderDepth(folder) {
        folder = String(folder || "").replace(/^\/+|\/+$/g, "");
        return folder ? Math.max(0, folder.split("/").length - 1) : 0;
    }

    function mediaFolderTreeViewSize(modal) {
        const value = String(modal && modal.__dbxCmsMediaTreeSize || "medium");
        return ["small", "medium", "large"].includes(value) ? value : "medium";
    }

    function renderMediaFolderTree(modal, folders) {
        const tree = qs(modal, "[data-cms-media-folder-tree]");
        if (!tree) return;
        const uploadFolders = uploadFolderOptions(folders || modal.__dbxCmsFolders || []);
        const rows = Array.isArray(modal.__dbxCmsRows) ? modal.__dbxCmsRows : [];
        const selected = modal.__dbxCmsSelectedIds || new Set();
        const counts = rows.reduce((out, row) => {
            const folder = String(row.media_folder || "");
            if (folder) out[folder] = (out[folder] || 0) + 1;
            return out;
        }, {});
        if (!uploadFolders.length) {
            tree.innerHTML = `
                <div class="dbx-cms-media-folder-tree-head">
                    <div>
                        <strong><i class="bi bi-folder2-open"></i> ${escapeHtml(cmsText(modal.closest(".dbx-cms"), "media_folders_title", "Medienordner"))}</strong>
                        <span>${escapeHtml(cmsText(modal.closest(".dbx-cms"), "media_no_folders", "Keine Medienordner vorhanden."))}</span>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-folder-tree-close>
                        <i class="bi bi-arrow-left"></i>
                        <span>${escapeHtml(cmsText(modal.closest(".dbx-cms"), "media_back", "Zurück"))}</span>
                    </button>
                </div>`;
            return;
        }
        const folderSet = new Set(uploadFolders);
        let currentFolder = String(modal.__dbxCmsMediaTreeFolder || modal.__dbxCmsMediaFolder || "").trim();
        const selectFilter = String(qs(modal, "[data-cms-media-browser-folder]")?.value || "");
        if ((!currentFolder || !folderSet.has(currentFolder)) && selectFilter && selectFilter !== "all" && folderSet.has(selectFilter)) {
            currentFolder = selectFilter;
        }
        if (!currentFolder || !folderSet.has(currentFolder)) {
            currentFolder = uploadFolders.find(folder => (counts[folder] || 0) > 0) || uploadFolders[0];
        }
        modal.__dbxCmsMediaTreeFolder = currentFolder;
        const viewSize = mediaFolderTreeViewSize(modal);
        const folderRows = rows.filter(row => String(row.media_folder || "") === currentFolder);
        const folderOptions = uploadFolders.map(folder => {
            const depth = mediaFolderDepth(folder);
            const label = mediaFolderName(folder);
            const count = counts[folder] || 0;
            const active = folder === currentFolder;
            return `<button type="button"
                class="dbx-cms-media-explorer-folder${active ? " is-active" : ""}"
                data-cms-media-folder-drop
                data-cms-media-tree-folder-select
                data-folder="${escapeHtml(folder)}"
                draggable="true"
                style="--dbx-folder-depth:${depth}"
                title="${escapeHtml(folder)}">
                    <span class="dbx-cms-media-explorer-indent"></span>
                    <i class="bi ${active ? "bi-folder2-open" : "bi-folder"}"></i>
                    <span class="dbx-cms-media-explorer-folder-text">
                        <strong>${escapeHtml(label)}</strong>
                        <small>${escapeHtml(folder)}</small>
                    </span>
                    <em>${count}</em>
                </button>`;
        }).join("");
        const sizeButtons = ["small", "medium", "large"].map(size => {
            const labels = {
                small: cmsText(modal.closest(".dbx-cms"), "media_view_small", "Klein"),
                medium: cmsText(modal.closest(".dbx-cms"), "media_view_medium", "Mittel"),
                large: cmsText(modal.closest(".dbx-cms"), "media_view_large", "Groß")
            };
            const icons = { small: "bi-grid-3x3-gap", medium: "bi-grid", large: "bi-grid-1x2" };
            return `<button type="button" class="btn btn-outline-primary btn-sm${viewSize === size ? " active" : ""}" data-cms-media-tree-size="${size}" title="Ansicht ${labels[size]}">
                <i class="bi ${icons[size]}"></i>
                <span>${labels[size]}</span>
            </button>`;
        }).join("");
        tree.innerHTML = `
            <div class="dbx-cms-media-folder-tree-head">
                <div>
                        <strong><i class="bi bi-folder2-open"></i> ${escapeHtml(cmsText(modal.closest(".dbx-cms"), "media_folders_title", "Medienordner"))}</strong>
                        <span>${escapeHtml(cmsText(modal.closest(".dbx-cms"), "media_folder_instruction", "Ordner links wählen, Medien rechts markieren oder per Drag verschieben."))}</span>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-folder-tree-close>
                    <i class="bi bi-arrow-left"></i>
                        <span>${escapeHtml(cmsText(modal.closest(".dbx-cms"), "media_back", "Zurück"))}</span>
                </button>
            </div>
            <div class="dbx-cms-media-explorer" data-cms-media-tree data-view-size="${escapeHtml(viewSize)}">
                <div class="dbx-cms-media-explorer-sidebar">
                    <div class="dbx-cms-media-explorer-sidebar-title">
                        <i class="bi bi-folder2"></i>
                        <span>${escapeHtml(cmsText(modal.closest(".dbx-cms"), "media_folder_label", "Ordner"))}</span>
                    </div>
                    <div class="dbx-cms-media-explorer-folders">
                        ${folderOptions}
                    </div>
                </div>
                <div class="dbx-cms-media-explorer-main">
                    <div class="dbx-cms-media-explorer-toolbar">
                        <div>
                            <strong>${escapeHtml(currentFolder)}</strong>
                            <span>${escapeHtml(cmsText(modal.closest(".dbx-cms"), "media_count", "{count} Medien").replace("{count}", String(folderRows.length)))}</span>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="${escapeHtml(cmsText(modal.closest(".dbx-cms"), "media_view", "Ansicht"))}">
                            ${sizeButtons}
                        </div>
                    </div>
                    <div class="dbx-cms-media-explorer-grid">
                        ${folderRows.length ? '' : '<div class="dbx-cms-media-tree-empty">' + escapeHtml(cmsText(modal.closest(".dbx-cms"), "media_folder_empty", "Keine Medien in diesem Ordner.")) + '</div>'}
                    </div>
                </div>
            </div>`;
        const grid = qs(tree, ".dbx-cms-media-explorer-grid");
        if (folderRows.length && grid) {
            renderMediaRowsChunked(
                grid,
                folderRows,
                row => mediaBrowserExplorerItemHtml(row, selected),
                () => updateMediaBrowserBatchUi(modal)
            );
        }
    }

    function setMediaBrowserFolderTreeMode(modal, open) {
        if (!modal) return;
        const tree = qs(modal, "[data-cms-media-folder-tree]");
        const toggle = qs(modal, "[data-cms-media-folder-toggle]");
        if (!tree) return;
        open = !!open;
        modal.classList.toggle("is-folder-tree-open", open);
        if (open) {
            modal.classList.remove("is-batch-open", "is-process-open");
            const batchPanel = modal.__dbxCmsBatchPanel || null;
            if (batchPanel) batchPanel.hidden = true;
            const processPanel = qs(modal, "[data-cms-media-process-panel]");
            if (processPanel) {
                processPanel.hidden = true;
                processPanel.innerHTML = "";
            }
        }
        tree.classList.toggle("is-open", open);
        tree.hidden = !open;
        if (toggle) {
            toggle.classList.toggle("is-active", open);
            toggle.setAttribute("aria-pressed", open ? "true" : "false");
        }
        if (open) renderMediaFolderTree(modal, modal.__dbxCmsFolders || []);
    }

    function refreshMediaFolderControls(root, cfg, modal) {
        const url = cfgUrl(cfg, "mediafolders");
        if (!url || !modal) return Promise.resolve([]);
        const profile = mediaBrowserProfile(cfg);
        return fetchJson(apiUrl(url), { timeout: 20000 })
            .then(data => {
                let folders = Array.isArray(data && data.folders) ? data.folders : [];
                if (profile === "mod") {
                    folders = folders.filter(folder => String(folder || "") === "mod");
                    if (!folders.length) folders = ["mod"];
                } else {
                    folders = folders.filter(folder => {
                        const value = String(folder || "");
                        return value && value !== "module" && value.indexOf("module/") !== 0;
                    });
                }
                const uploadFolders = uploadFolderOptions(folders);
                modal.__dbxCmsFolders = folders;
                modal.__dbxCmsUploadFolders = uploadFolders;
                if (data && data.root) {
                    modal.dataset.dbxMediaRoot = String(data.root);
                    const rootHint = qs(modal, "[data-cms-media-root-hint]");
                    if (rootHint) rootHint.textContent = "Speicherort: " + String(data.root);
                }
                setSelectOptions(qs(modal, "[data-cms-media-browser-folder]"), folders, true);
                setSelectOptions(qs(modal, "[data-cms-upload-folder]"), uploadFolders, false);
                renderMediaFolderTree(modal, folders);
                const parentSelect = qs(modal, "[data-cms-folder-parent]");
                if (parentSelect) {
                    setSelectOptions(parentSelect, uploadFolders, false);
                    const uploadFolder = qs(modal, "[data-cms-upload-folder]");
                    if (uploadFolder && uploadFolder.value && uploadFolders.includes(uploadFolder.value)) {
                        parentSelect.value = uploadFolder.value;
                    }
                }
                syncUploadFolderSelect(modal, uploadFolders, modal.__dbxCmsMediaFolder || "");
                return folders;
            })
            .catch(err => {
                dbx.warn("[cms] media folders load failed", err);
                return modal.__dbxCmsFolders || [];
            });
    }

    function createMediaFolder(root, cfg, modal) {
        const url = cfgUrl(cfg, "mediafoldercreate");
        const input = qs(modal, "[data-cms-folder-name]");
        const parent = qs(modal, "[data-cms-folder-parent]");
        const uploadFolder = qs(modal, "[data-cms-upload-folder]");
        const name = String(input?.value || "").trim();
        const parentVal = String(parent?.value || uploadFolder?.value || "").trim();
        const folderPath = buildNewMediaFolderPath(parentVal, name);
        if (!url || !name || !folderPath) {
            status(root, "Bitte Basis-Ordner und Ordnernamen eintragen.", "error");
            return Promise.resolve();
        }
        return fetchJson(apiUrl(url), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ media_type: mediaTypeFromFolder(folderPath), media_folder: folderPath })
        }).then(data => {
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "folder create failed");
            if (input) input.value = "";
            status(root, "Medienordner angelegt.", "success");
            return refreshMediaFolderControls(root, cfg, modal).then(() => {
                const uploadFolder = qs(modal, "[data-cms-upload-folder]");
                const filterFolder = qs(modal, "[data-cms-media-browser-folder]");
                const uploadFolders = modal.__dbxCmsUploadFolders || uploadFolderOptions(modal.__dbxCmsFolders || []);
                if (data.folder) {
                    if (filterFolder && (data.folder === "all" || (modal.__dbxCmsFolders || []).includes(data.folder))) {
                        filterFolder.value = data.folder;
                    }
                    if (uploadFolder && uploadFolders.includes(data.folder)) uploadFolder.value = data.folder;
                }
                syncUploadFolderSelect(modal, uploadFolders, data.folder || "");
            });
        }).catch(err => {
            dbx.error("[cms] media folder create failed", err);
            status(root, err && err.message ? err.message : "Medienordner konnte nicht angelegt werden.", "error");
        });
    }

    function deleteSelectedMediaFolder(root, cfg, modal) {
        const url = cfgUrl(cfg, "mediafolderdelete");
        const select = qs(modal, "[data-cms-upload-folder]");
        const folder = String(select?.value || "");
        if (!url || !folder) return Promise.resolve();

        return ensureConfirm().then(ok => {
            if (!ok) {
                status(root, "Confirm-Lib ist nicht geladen.", "error");
                return null;
            }

            return dbx.confirm.open({
                id: "cms-delete-media-folder-" + folder,
                root,
                title: "<i class=\"bi bi-trash\"></i> Medienordner loeschen",
                question: "Medienordner <strong>" + escapeHtml(folder) + "</strong> wirklich loeschen?",
                hint: "Der Ordner kann nur geloescht werden, wenn er leer ist.",
                buttons: "yesno",
                labelyes: "<i class=\"bi bi-trash\"></i> Loeschen",
                labelno: "<i class=\"bi bi-x-lg\"></i> Abbrechen",
                closable: true,
                backdropclose: false,
                escclose: true
            });
        }).then(result => {
            if (!result || result.action !== "yes") return null;

            return fetchJson(apiUrl(url), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ media_folder: folder })
            });
        }).then(data => {
            if (!data) return null;
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "folder delete failed");
            status(root, "Medienordner geloescht.", "success");
            return refreshMediaFolderControls(root, cfg, modal);
        }).catch(err => {
            dbx.error("[cms] media folder delete failed", err);
            status(root, err && err.message ? err.message : "Medienordner konnte nicht geloescht werden.", "error");
        });
    }

    function renameSelectedMediaFolder(root, cfg, modal) {
        const url = cfgUrl(cfg, "mediafolderrename");
        const select = qs(modal, "[data-cms-upload-folder]");
        const input = qs(modal, "[data-cms-folder-rename-name]");
        const from = String(select?.value || "").trim();
        const newName = String(input?.value || "").trim();
        if (!url || !from || !newName) {
            status(root, "Bitte Ordner und neuen Namen eintragen.", "error");
            return Promise.resolve();
        }
        const segments = from.split("/");
        const toFolder = newName.indexOf("/") >= 0
            ? newName
            : (segments.length > 1 ? segments.slice(0, -1).concat(newName) : [segments[0] || "img", newName]).join("/");
        if (toFolder === from) {
            status(root, "Der neue Name ist identisch.", "error");
            return Promise.resolve();
        }
        return fetchJson(apiUrl(url), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ from_folder: from, to_folder: toFolder })
        }).then(data => {
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "folder rename failed");
            if (input) input.value = "";
            status(root, data.msg || "Medienordner umbenannt.", "success");
            return refreshMediaFolderControls(root, cfg, modal).then(() => {
                const uploadFolder = qs(modal, "[data-cms-upload-folder]");
                const filterFolder = qs(modal, "[data-cms-media-browser-folder]");
                const uploadFolders = modal.__dbxCmsUploadFolders || uploadFolderOptions(modal.__dbxCmsFolders || []);
                if (data.to_folder) {
                    if (filterFolder && (modal.__dbxCmsFolders || []).includes(data.to_folder)) {
                        filterFolder.value = data.to_folder;
                        modal.__dbxCmsMediaFolder = data.to_folder;
                    }
                    if (uploadFolder && uploadFolders.includes(data.to_folder)) uploadFolder.value = data.to_folder;
                }
                syncUploadFolderSelect(modal, uploadFolders, data.to_folder || "");
                return openMediaBrowser(modal.__dbxCmsRoot || root, cfg, {
                    mode: modal.__dbxCmsMediaMode || "editor",
                    slot: modal.__dbxCmsAssignSlot || currentMediaSlot(root),
                    mediaFolder: data.to_folder || modal.__dbxCmsMediaFolder || "",
                    formDataExtra: modal.__dbxCmsFormDataExtra || null,
                    afterAssign: modal.__dbxCmsAfterAssign
                });
            });
        }).catch(err => {
            dbx.error("[cms] media folder rename failed", err);
            status(root, err && err.message ? err.message : "Medienordner konnte nicht umbenannt werden.", "error");
        });
    }

    function moveMediaFolderToFolder(root, cfg, modal, source, target) {
        const url = cfgUrl(cfg, "mediafolderrename");
        source = String(source || "").trim();
        target = String(target || "").trim();
        if (!url) {
            status(root, "Ordner verschieben ist nicht konfiguriert.", "error");
            return Promise.resolve();
        }
        if (!source || !target) {
            status(root, "Quell- und Zielordner fehlen.", "error");
            return Promise.resolve();
        }
        if (source === target || target.indexOf(source + "/") === 0) {
            status(root, "Ordner kann nicht in sich selbst verschoben werden.", "error");
            return Promise.resolve();
        }
        const currentParent = mediaFolderParent(source);
        if (currentParent === target) {
            status(root, "Ordner liegt bereits dort.", "warning");
            return Promise.resolve();
        }
        const toFolder = target.replace(/\/+$/g, "") + "/" + mediaFolderName(source);
        if ((modal.__dbxCmsFolders || []).includes(toFolder)) {
            status(root, "Zielordner existiert bereits.", "error");
            return Promise.resolve();
        }
        return fetchJson(apiUrl(url), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ from_folder: source, to_folder: toFolder })
        }).then(data => {
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "folder move failed");
            modal.__dbxCmsMediaTreeFolder = data.to_folder || toFolder;
            status(root, data.msg || "Medienordner verschoben.", "success");
            return openMediaBrowser(modal.__dbxCmsRoot || root, cfg, {
                mode: modal.__dbxCmsMediaMode || "editor",
                slot: modal.__dbxCmsAssignSlot || currentMediaSlot(root),
                mediaFolder: data.to_folder || toFolder,
                formDataExtra: modal.__dbxCmsFormDataExtra || null,
                afterAssign: modal.__dbxCmsAfterAssign
            });
        }).catch(err => {
            dbx.error("[cms] media folder move failed", err);
            status(root, err && err.message ? err.message : "Medienordner konnte nicht verschoben werden.", "error");
        });
    }

    function moveSelectedMedia(root, cfg, modal) {
        const url = cfgUrl(cfg, "mediamove");
        const targetSelect = qs(modal, "[data-cms-media-move-folder]");
        const target = String(targetSelect?.value || "").trim();
        const rows = selectedMediaBrowserRows(modal);
        if (!url) {
            status(root, "Verschieben ist nicht konfiguriert.", "error");
            return Promise.resolve();
        }
        if (!target) {
            status(root, "Bitte Zielordner waehlen.", "error");
            return Promise.resolve();
        }
        if (!rows.length) {
            status(root, "Bitte mindestens ein Medium auswaehlen.", "error");
            return Promise.resolve();
        }
        const movable = rows.filter(row => !isExternalVideoRow(row));
        if (!movable.length) {
            status(root, "YouTube-Eintraege koennen nicht verschoben werden.", "error");
            return Promise.resolve();
        }
        let chain = Promise.resolve();
        let moved = 0;
        movable.forEach(row => {
            chain = chain.then(() => fetchJson(apiUrl(url), {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ media_id: row.id, media_folder: target })
            }).then(data => {
                if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "media move failed");
                moved++;
            }));
        });
        return chain.then(() => {
            modal.__dbxCmsSelectedIds = new Set();
            status(root, moved === 1 ? "Medium verschoben." : moved + " Medien verschoben.", "success");
            return openMediaBrowser(modal.__dbxCmsRoot || root, cfg, {
                mode: modal.__dbxCmsMediaMode || "editor",
                slot: modal.__dbxCmsAssignSlot || currentMediaSlot(root),
                mediaFolder: modal.__dbxCmsMediaFolder || "",
                formDataExtra: modal.__dbxCmsFormDataExtra || null,
                afterAssign: modal.__dbxCmsAfterAssign
            });
        }).catch(err => {
            dbx.error("[cms] media move failed", err);
            status(root, err && err.message ? err.message : "Medium konnte nicht verschoben werden.", "error");
        });
    }

    function draggedMediaRows(modal, mediaId) {
        mediaId = Number(mediaId || 0);
        const selected = modal.__dbxCmsSelectedIds || new Set();
        if (mediaId > 0 && selected.has(mediaId)) {
            const rows = selectedMediaBrowserRows(modal);
            if (rows.length) return rows;
        }
        const item = mediaId > 0 ? qs(modal, `[data-cms-media-browser-item][data-media-id="${mediaId}"]`) : null;
        return item ? [mediaRowFromItem(item)] : [];
    }

    function mediaFolderAcceptsRow(folder, row) {
        folder = String(folder || "");
        const type = String(row && row.media_type || "").toLowerCase();
        const mime = String(row && row.mime || "").toLowerCase();
        if (type === "video" || mime.indexOf("video/") === 0) return folder === "videos" || folder.indexOf("videos/") === 0 || folder === "video" || folder.indexOf("video/") === 0;
        if (type === "file") return folder === "file" || folder.indexOf("file/") === 0;
        return folder.indexOf("img/") === 0 && folder !== "img/video" && folder.indexOf("img/video/") !== 0;
    }

    function moveMediaRowsToFolder(root, cfg, modal, rows, target) {
        const url = cfgUrl(cfg, "mediamove");
        target = String(target || "").trim();
        if (!url) {
            status(root, "Verschieben ist nicht konfiguriert.", "error");
            return Promise.resolve();
        }
        if (!target) {
            status(root, "Bitte Zielordner waehlen.", "error");
            return Promise.resolve();
        }
        const incompatible = (rows || []).filter(row => row && !isExternalVideoRow(row) && !mediaFolderAcceptsRow(target, row));
        if (incompatible.length) {
            status(root, "Zielordner passt nicht zum Medientyp.", "error");
            return Promise.resolve();
        }
        const movable = (rows || []).filter(row => row && !isExternalVideoRow(row) && String(row.media_folder || "") !== target);
        if (!movable.length) {
            status(root, "Keine verschiebbaren Medien fuer diesen Ordner.", "warning");
            return Promise.resolve();
        }
        let moved = 0;
        return movable.reduce((chain, row) => {
            return chain.then(() => fetchJson(apiUrl(url), {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ media_id: row.id, media_folder: target })
            }).then(data => {
                if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "media move failed");
                moved++;
                if (data.row) patchMediaBrowserRow(modal, data.row);
            }));
        }, Promise.resolve()).then(() => {
            modal.__dbxCmsSelectedIds = new Set();
            modal.__dbxCmsMediaTreeFolder = target;
            status(root, moved === 1 ? "Medium verschoben." : moved + " Medien verschoben.", "success");
            return openMediaBrowser(modal.__dbxCmsRoot || root, cfg, {
                mode: modal.__dbxCmsMediaMode || "editor",
                slot: modal.__dbxCmsAssignSlot || currentMediaSlot(root),
                mediaFolder: modal.__dbxCmsMediaFolder || "",
                formDataExtra: modal.__dbxCmsFormDataExtra || null,
                afterAssign: modal.__dbxCmsAfterAssign
            });
        }).catch(err => {
            dbx.error("[cms] media tree move failed", err);
            status(root, err && err.message ? err.message : "Medium konnte nicht verschoben werden.", "error");
        });
    }

    function setMediaBrowserSelection(modal, mediaId, selected) {
        mediaId = Number(mediaId || 0);
        if (!modal || mediaId <= 0) return;
        if (!modal.__dbxCmsSelectedIds) modal.__dbxCmsSelectedIds = new Set();
        if (selected && !isMediaBrowserMulti(modal)) {
            modal.__dbxCmsSelectedIds.clear();
            qsa(modal, "[data-cms-media-browser-item].is-selected").forEach(el => {
                el.classList.remove("is-selected");
            });
            const batchPanel = modal.__dbxCmsBatchPanel;
            if (batchPanel) {
                qsa(batchPanel, "[data-cms-batch-item].is-selected").forEach(el => {
                    el.classList.remove("is-selected");
                });
            }
        }
        if (selected) modal.__dbxCmsSelectedIds.add(mediaId);
        else modal.__dbxCmsSelectedIds.delete(mediaId);
        qsa(modal, `[data-cms-media-browser-item][data-media-id="${mediaId}"]`).forEach(el => {
            el.classList.toggle("is-selected", selected);
        });
        const batchPanel = modal.__dbxCmsBatchPanel;
        if (batchPanel) {
            qsa(batchPanel, `[data-cms-batch-item][data-media-id="${mediaId}"]`).forEach(el => {
                el.classList.toggle("is-selected", selected);
            });
        }
        updateMediaBrowserConfirm(modal);
        updateMediaBrowserBatchUi(modal);
    }

    function toggleMediaBrowserSelection(modal, item) {
        const id = Number(item?.getAttribute("data-media-id") || 0);
        if (!id) return;
        const selected = !(modal.__dbxCmsSelectedIds || new Set()).has(id);
        setMediaBrowserSelection(modal, id, selected);
    }

    function renderMediaBatchList(browserModal) {
        const batchPanel = browserModal && browserModal.__dbxCmsBatchPanel;
        const list = batchPanel && qs(batchPanel, "[data-cms-media-batch-list]");
        if (!list || !browserModal) return;
        const rows = (browserModal.__dbxCmsRows || []).filter(canEditImage);
        const selected = browserModal.__dbxCmsSelectedIds || new Set();
        if (!rows.length) {
            list.innerHTML = '<div class="dbx-cms-empty">Keine bearbeitbaren Bilder im aktuellen Medienbrowser-Filter.</div>';
            return;
        }
        list.innerHTML = rows.map(row => `
            <button type="button"
                class="dbx-cms-media-batch-item${selected.has(Number(row.id || 0)) ? " is-selected" : ""}"
                data-cms-batch-item
                data-media-id="${escapeHtml(row.id || "")}"
                title="${escapeHtml(row.title || row.file_name || "Bild")}">
                <span class="dbx-cms-media-batch-thumb">${mediaPreviewHtml(row)}</span>
                <span class="dbx-cms-media-batch-name">${escapeHtml(row.title || row.file_name || "Bild")}</span>
                <em class="dbx-cms-media-browser-check"><i class="bi bi-check2"></i></em>
            </button>`).join("");
        setupMediaLazyImages(list);
    }

    function patchMediaBrowserRow(browserModal, row) {
        if (!browserModal || !row) return;
        const id = Number(row.id || 0);
        if (id <= 0) return;
        const rows = browserModal.__dbxCmsRows;
        if (Array.isArray(rows)) {
            const idx = rows.findIndex(item => Number(item.id || 0) === id);
            if (idx >= 0) rows[idx] = Object.assign({}, rows[idx], row);
            else rows.push(row);
        }
        const thumb = row.thumb_url || row.url || "";
        qsa(browserModal, `[data-cms-media-browser-item][data-media-id="${id}"]`).forEach(el => {
            if (row.url) el.setAttribute("data-url", row.url);
            if (row.thumb_url) el.setAttribute("data-thumb-url", row.thumb_url);
            if (row.width !== undefined && row.width !== null) el.setAttribute("data-width", row.width);
            if (row.height !== undefined && row.height !== null) el.setAttribute("data-height", row.height);
            if (row.file_name) el.setAttribute("data-file-name", row.file_name);
            if (row.file_path) el.setAttribute("data-file-path", row.file_path);
            if (row.title) el.setAttribute("data-title", row.title);
            if (row.alt) el.setAttribute("data-alt", row.alt);
            if (row.media_folder) el.setAttribute("data-media-folder", row.media_folder);
            const img = qs(el, ".dbx-cms-media-browser-pickarea img");
            if (img && thumb) {
                img.src = thumb;
                img.removeAttribute("data-dbx-media-src");
            }
        });
        renderMediaBatchList(browserModal);
    }

    function selectedMediaBrowserRows(modal) {
        const selected = modal.__dbxCmsSelectedIds || new Set();
        return mediaBrowserAllRows(modal).filter(row => selected.has(Number(row.id || 0)));
    }

    function closeMediaBrowserModal(root, modal) {
        if (!modal) return;
        modal.hidden = true;
        if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.close === "function") {
            dbx.openWin.close(modal.__dbxCmsWindowId);
        }
        clearCmsLoading(root);
    }

    function leaveInlineMediaCaption(root) {
        const sel = window.getSelection ? window.getSelection() : null;
        if (!sel || !sel.rangeCount) return;
        let node = sel.anchorNode;
        if (node && node.nodeType !== 1) node = node.parentElement;
        const figure = node && node.closest ? node.closest("figure, .dbx-cms-inline-media, .dbx-cms-inline-image, .dbx-cms-inline-video") : null;
        const editor = qs(root, "[data-cms-editor]");
        if (!figure || !editor || !editor.contains(figure)) return;
        const range = document.createRange();
        range.setStartAfter(figure);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
    }

    function selectedUploadFiles(form) {
        const input = qs(form, 'input[type="file"][name="file"]');
        return input && input.files && input.files.length ? Array.from(input.files) : [];
    }

    function selectedUploadFile(form) {
        const files = selectedUploadFiles(form);
        return files.length ? files[0] : null;
    }

    function setUploadFiles(form, files) {
        const input = qs(form, 'input[type="file"][name="file"]');
        files = Array.from(files || []).filter(Boolean);
        if (!input || !files.length || !window.DataTransfer) return false;
        const dt = new DataTransfer();
        files.forEach(file => dt.items.add(file));
        input.files = dt.files;
        updateUploadLabel(form);
        return true;
    }

    function setUploadFile(form, file) {
        return setUploadFiles(form, file ? [file] : []);
    }

    function updateUploadLabel(form) {
        const label = qs(form, "[data-cms-upload-label]");
        const files = selectedUploadFiles(form);
        if (label) {
            if (files.length > 1) {
                label.textContent = files.length + " Dateien ausgewaehlt";
            } else {
                label.textContent = files.length ? files[0].name : "Datei auswaehlen oder hier ablegen";
            }
        }
        form.classList.toggle("has-file", files.length > 0);
    }

    function formatBytes(bytes) {
        bytes = Number(bytes || 0);
        if (bytes >= 1024 * 1024 * 1024) return (bytes / 1024 / 1024 / 1024).toFixed(1).replace(/\.0$/, "") + " GB";
        if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1).replace(/\.0$/, "") + " MB";
        if (bytes >= 1024) return (bytes / 1024).toFixed(1).replace(/\.0$/, "") + " KB";
        return bytes + " B";
    }

    function isExternalVideoRow(row) {
        const type = String(row && row.media_type || "").toLowerCase();
        const storageType = String(row && row.storage_type || "").toLowerCase();
        return type === "external_video" || (type === "video" && storageType === "external");
    }

    function isVideoRow(row) {
        return isExternalVideoRow(row) || String(row.media_type || "").toLowerCase() === "video" || String(row.mime || "").startsWith("video/") || /\.(mp4|webm|ogv|ogg|mov|m4v)$/i.test(row.file_name || row.url || "");
    }

    function isImageRow(row) {
        return String(row.media_type || "").toLowerCase() === "image" || String(row.mime || "").startsWith("image/") || /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(row.file_name || row.url || "");
    }

    function canEditImage(row) {
        return isImageRow(row) && !/\.svg$/i.test(row.file_name || row.url || "");
    }

    const mediaLazyPlaceholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='320' height='220' viewBox='0 0 320 220'%3E%3Crect width='320' height='220' rx='12' fill='%23dbeafe'/%3E%3Crect x='24' y='24' width='272' height='172' rx='10' fill='%23eff6ff' stroke='%2393c5fd' stroke-width='3'/%3E%3Ccircle cx='118' cy='90' r='22' fill='%23bfdbfe'/%3E%3Cpath d='M64 166l62-54 42 34 28-24 60 44z' fill='%2360a5fa' opacity='.55'/%3E%3C/svg%3E";

    function mediaLazyImageHtml(src, label, extraHtml) {
        src = String(src || "");
        if (!src) return "";
        return `<img class="dbx-cms-media-lazy-image" src="${mediaLazyPlaceholder}" data-dbx-media-src="${escapeHtml(src)}" alt="${label}" loading="lazy" decoding="async">${extraHtml || ""}`;
    }

    function mediaLazyLoadVisible(scope) {
        if (!scope) return;
        const rootRect = scope.getBoundingClientRect();
        const margin = 48;
        qsa(scope, "img[data-dbx-media-src]").forEach(img => {
            const rect = img.getBoundingClientRect();
            if (
                rect.bottom < rootRect.top - margin ||
                rect.top > rootRect.bottom + margin ||
                rect.right < rootRect.left - margin ||
                rect.left > rootRect.right + margin
            ) {
                return;
            }
            const src = img.getAttribute("data-dbx-media-src");
            if (!src || img.__dbxMediaLazyLoading) return;
            img.__dbxMediaLazyLoading = true;
            const finish = () => {
                img.removeEventListener("load", finish);
                img.removeEventListener("error", finish);
                img.removeAttribute("data-dbx-media-src");
                img.__dbxMediaLazyLoading = false;
            };
            img.addEventListener("load", finish);
            img.addEventListener("error", finish);
            img.src = src;
        });
    }

    function scheduleMediaLazyLoad(scope) {
        if (!scope) return;
        if (scope.__dbxMediaLazyFrame) return;
        scope.__dbxMediaLazyFrame = window.requestAnimationFrame(() => {
            scope.__dbxMediaLazyFrame = 0;
            mediaLazyLoadVisible(scope);
        });
    }

    function setupMediaLazyImages(scope) {
        if (!scope) return;
        const images = qsa(scope, "img[data-dbx-media-src]");
        if (!images.length) return;
        if (!scope.__dbxMediaLazyBound) {
            scope.__dbxMediaLazyBound = true;
            scope.addEventListener("scroll", () => scheduleMediaLazyLoad(scope), { passive: true });
            window.addEventListener("resize", () => scheduleMediaLazyLoad(scope), { passive: true });
        }
        scheduleMediaLazyLoad(scope);
    }

    function mediaPreviewHtml(row) {
        const src = row.thumb_url || row.url || "";
        const label = escapeHtml(row.alt || row.title || "");
        if (isImageRow(row) && src) return mediaLazyImageHtml(src, label);
        if (isExternalVideoRow(row)) {
            return row.thumb_url
                ? mediaLazyImageHtml(row.thumb_url, label, '<i class="bi bi-play-fill dbx-cms-media-video-icon"></i>')
                : '<i class="bi bi-camera-video"></i>';
        }
        if (isVideoRow(row)) {
            return row.thumb_url
                ? mediaLazyImageHtml(row.thumb_url, label, '<i class="bi bi-play-fill dbx-cms-media-video-icon"></i>')
                : '<i class="bi bi-camera-video"></i>';
        }
        return '<i class="bi bi-file-earmark"></i>';
    }

    function mediaProcessHeadMarkup() {
        return '<div class="dbx-cms-media-process-head"><strong><i class="bi bi-tools"></i> Medienwartung</strong><button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-process-close title="Zurueck zum Medienbrowser"><i class="bi bi-arrow-left"></i><span>Zurueck</span></button></div>';
    }

    function mediaMaintenanceFolderOptions(modal, includeAll, preferred) {
        const folders = uploadFolderOptions(modal && modal.__dbxCmsFolders || [])
            .filter(folder => String(folder || "").indexOf("img/") === 0);
        const current = String(preferred || "");
        const options = [];
        if (includeAll) options.push('<option value="all">Alle Ordner</option>');
        folders.forEach(folder => {
            options.push(`<option value="${escapeHtml(folder)}"${folder === current ? " selected" : ""}>${escapeHtml(mediaFolderLabel(folder))}</option>`);
        });
        return options.join("");
    }

    function renderMediaMaintenanceHome(root, cfg, browserModal, batchPanel) {
        const panel = (browserModal ? qs(browserModal, "[data-cms-media-process-panel]") : null)
            || (batchPanel ? qs(batchPanel, "[data-cms-media-process-panel]") : null);
        if (!panel) return;
        if (browserModal) {
            browserModal.classList.remove("is-folder-tree-open", "is-batch-open");
            browserModal.classList.add("is-process-open");
            const batch = browserModal.__dbxCmsBatchPanel || null;
            if (batch) batch.hidden = true;
        }
        const folderSelect = browserModal ? qs(browserModal, "[data-cms-media-browser-folder]") : null;
        const selectedFolder = String(folderSelect?.value || browserModal?.__dbxCmsMediaFolder || "");
        const preferredSource = selectedFolder && selectedFolder !== "all" ? selectedFolder : "all";
        panel.hidden = false;
        panel.innerHTML = mediaProcessHeadMarkup() + `
            <div class="dbx-cms-media-maintenance-grid">
                <section class="dbx-cms-media-maintenance-card">
                    <strong><i class="bi bi-arrow-repeat"></i> Medien pruefen</strong>
                    <p>Synchronisiert Mediendateien und Vorschaubilder mit der Datenbank.</p>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-process-start>
                        <i class="bi bi-play-fill"></i>
                        <span>Wartung starten</span>
                    </button>
                </section>
                <section class="dbx-cms-media-maintenance-card">
                    <strong><i class="bi bi-image"></i> Nicht verwendete Bilder</strong>
                    <label>
                        <span>Quelle</span>
                        <select class="form-select form-select-sm" data-cms-media-unused-source>
                            ${mediaMaintenanceFolderOptions(browserModal, true, preferredSource)}
                        </select>
                    </label>
                    <label>
                        <span>Zielordner fuer Verschieben</span>
                        <select class="form-select form-select-sm" data-cms-media-unused-target>
                            ${mediaMaintenanceFolderOptions(browserModal, false, "")}
                        </select>
                    </label>
                    <div class="dbx-cms-media-maintenance-actions">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-cms-media-unused-action="delete">
                            <i class="bi bi-trash"></i>
                            <span>Unbenutzte loeschen</span>
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-unused-action="move">
                            <i class="bi bi-folder-symlink"></i>
                            <span>Unbenutzte verschieben</span>
                        </button>
                    </div>
                    <p class="dbx-cms-media-maintenance-note" data-cms-media-unused-result>Es werden nur Bilder ohne erkannte Verwendung verarbeitet.</p>
                </section>
            </div>`;
        if (dbx.scan) dbx.scan(panel);
    }

    function startMediaMaintenance(root, cfg, browserModal, batchPanel) {
        const url = cfgUrl(cfg || {}, "mediaprocess");
        const panel = (browserModal ? qs(browserModal, "[data-cms-media-process-panel]") : null)
            || (batchPanel ? qs(batchPanel, "[data-cms-media-process-panel]") : null);
        if (!url || !panel) {
            status(root, "Medienwartung ist nicht konfiguriert.", "error");
            return;
        }
        if (browserModal) {
            browserModal.classList.remove("is-folder-tree-open", "is-batch-open");
            browserModal.classList.add("is-process-open");
            const batch = browserModal.__dbxCmsBatchPanel || null;
            if (batch) batch.hidden = true;
        }

        const token = "media-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 8);
        panel.hidden = false;
        const processHead = mediaProcessHeadMarkup();
        panel.innerHTML = processHead + '<div class="dbx-cms-empty">Medienwartung wird vorbereitet...</div>';

        fetchHtml(apiUrl(url, { reset: 1, proc_key: token }), { timeout: 20000 })
            .then(html => {
                panel.innerHTML = processHead + (html ? extractProcessHtml(html) : '<div class="dbx-cms-empty">Medienwartung konnte nicht gestartet werden.</div>');
                if (dbx.scan) dbx.scan(panel);
                const proc = qs(panel, ".dbx-process");
                if (proc && proc.getAttribute("data-process-status") === "finished" && browserModal) {
                    status(root, "Medienwartung abgeschlossen.", "success");
                    openMediaBrowser(root, cfg, {
                        mode: browserModal.__dbxCmsMediaMode || "editor",
                        slot: browserModal.__dbxCmsAssignSlot || currentMediaSlot(root),
                        mediaFolder: browserModal.__dbxCmsMediaFolder || "",
                        formDataExtra: browserModal.__dbxCmsFormDataExtra || null,
                        afterAssign: browserModal.__dbxCmsAfterAssign
                    });
                }
            })
            .catch(err => {
                dbx.error("[cms] media maintenance failed", err);
                panel.innerHTML = '<div class="dbx-cms-empty">Medienwartung konnte nicht gestartet werden.</div>';
                status(root, err && err.message ? err.message : "Medienwartung konnte nicht gestartet werden.", "error");
            })
            .finally(() => clearCmsLoading(root));
    }

    function renderUnusedMediaProcess(panel, state) {
        state = state || {};
        const percent = Math.max(0, Math.min(100, Number(state.percent || 0)));
        const title = state.title || "Unbenutzte Bilder";
        const message = state.message || "Wartung wird vorbereitet...";
        const detail = state.detail || "";
        const done = state.done === true;
        panel.innerHTML = mediaProcessHeadMarkup() + `
            <div class="dbx-cms-media-unused-process" data-cms-media-unused-process>
                <div class="dbx-cms-media-unused-process-title">
                    <strong><i class="bi ${done ? "bi-check2-circle" : "bi-arrow-repeat"}"></i> ${escapeHtml(title)}</strong>
                    <span>${percent}%</span>
                </div>
                <div class="dbx-cms-media-unused-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percent}">
                    <span style="width:${percent}%"></span>
                </div>
                <p>${escapeHtml(message)}</p>
                ${detail ? `<pre>${escapeHtml(detail)}</pre>` : ""}
            </div>`;
        if (dbx.scan) dbx.scan(panel);
    }

    function executeUnusedMediaMaintenance(root, cfg, browserModal, action) {
        const url = cfgUrl(cfg || {}, "mediaunused");
        const panel = browserModal ? qs(browserModal, "[data-cms-media-process-panel]") : null;
        if (!url || !panel) {
            status(root, "Wartung fuer unbenutzte Bilder ist nicht konfiguriert.", "error");
            return;
        }
        action = action === "move" ? "move" : "delete";
        const source = String(qs(panel, "[data-cms-media-unused-source]")?.value || "all");
        const target = String(qs(panel, "[data-cms-media-unused-target]")?.value || "");
        if (action === "move" && !target) {
            status(root, "Bitte Zielordner waehlen.", "error");
            return;
        }
        const sourceLabel = source === "all" ? "allen Ordnern" : "Ordner " + source;
        const question = action === "delete"
            ? "Alle nicht verwendeten Bilder aus " + sourceLabel + " wirklich loeschen?"
            : "Alle nicht verwendeten Bilder aus " + sourceLabel + " nach " + target + " verschieben?";
        ensureConfirm().then(ok => {
            if (!ok) {
                status(root, "Confirm-Lib ist nicht geladen.", "error");
                return null;
            }
            return dbx.confirm.open({
                id: "cms-media-unused-" + action + "-" + Date.now(),
                root,
                title: "<i class=\"bi bi-tools\"></i> Medienwartung",
                question: escapeHtml(question),
                hint: "Verwendete Bilder werden serverseitig erneut geprueft und uebersprungen.",
                buttons: "yesno",
                labelyes: action === "delete" ? "<i class=\"bi bi-trash\"></i> Loeschen" : "<i class=\"bi bi-folder-symlink\"></i> Verschieben",
                labelno: "<i class=\"bi bi-x-lg\"></i> Abbrechen",
                closable: true,
                backdropclose: false,
                escclose: true
            });
        }).then(result => {
            if (!result || result.action !== "yes") return null;
            if (browserModal) browserModal.classList.add("is-process-open");
            panel.hidden = false;
            renderUnusedMediaProcess(panel, {
                percent: 15,
                title: action === "delete" ? "Unbenutzte Bilder loeschen" : "Unbenutzte Bilder verschieben",
                message: "Server prueft Verwendungen und verarbeitet die Bilder..."
            });
            return fetchJson(apiUrl(url), {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action, source_folder: source, target_folder: target }),
                timeout: 120000
            });
        }).then(data => {
            if (!data) return;
            if (!data.ok) throw new Error(data && data.msg ? data.msg : "Wartung konnte nicht ausgefuehrt werden.");
            const detail = "Geprueft: " + Number(data.checked || 0)
                + "\nVerarbeitet: " + Number(data.affected || 0)
                + "\nUebersprungen, weil verwendet: " + Number(data.skipped_used || 0)
                + (Array.isArray(data.errors) && data.errors.length ? "\nFehler:\n" + data.errors.join("\n") : "");
            renderUnusedMediaProcess(panel, {
                percent: 100,
                done: true,
                title: action === "delete" ? "Loeschen abgeschlossen" : "Verschieben abgeschlossen",
                message: data.msg || "Wartung abgeschlossen.",
                detail
            });
            status(root, data.msg || "Wartung abgeschlossen.", Number(data.affected || 0) > 0 ? "success" : "warning");
            if (browserModal) {
                window.setTimeout(() => {
                    browserModal.classList.remove("is-process-open");
                    openMediaBrowser(browserModal.__dbxCmsRoot || root, browserCfg(browserModal), {
                        mode: browserModal.__dbxCmsMediaMode || "editor",
                        slot: browserModal.__dbxCmsAssignSlot || currentMediaSlot(root),
                        mediaFolder: source !== "all" ? source : (browserModal.__dbxCmsMediaFolder || ""),
                        formDataExtra: browserModal.__dbxCmsFormDataExtra || null,
                        afterAssign: browserModal.__dbxCmsAfterAssign
                    });
                }, 900);
            }
        }).catch(err => {
            dbx.error("[cms] unused media maintenance failed", err);
            renderUnusedMediaProcess(panel, {
                percent: 100,
                done: true,
                title: "Wartung fehlgeschlagen",
                message: err && err.message ? err.message : "Wartung konnte nicht ausgefuehrt werden."
            });
            status(root, err && err.message ? err.message : "Wartung konnte nicht ausgefuehrt werden.", "error");
        });
    }

    function openMediaBrowserWindow(root, modal) {
        if (!modal) return false;
        modal.__dbxCmsCallerRoot = root || null;
        if (!dbx.openWin || typeof dbx.openWin.open !== "function") {
            ensureOpenWin().then(ok => {
                if (ok) openMediaBrowserWindow(root, modal);
                else status(root, "openWin.js nicht geladen.", "error");
            });
            return false;
        }
        const currentWindow = closestElement(modal, ".dbx-window");
        const currentIsBrowserWindow = currentWindow && currentWindow.classList.contains("dbx-cms-media-browser-window");
        if (currentIsBrowserWindow) {
            modal.hidden = false;
            raiseMediaBrowserWindow(currentWindow, modal);
            if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.bringToFront === "function") {
                dbx.openWin.bringToFront(modal.__dbxCmsWindowId);
            }
            raiseMediaBrowserWindow(currentWindow, modal);
            return true;
        }
        const id = dbx.openWin.open({
            title: '<i class="bi bi-images"></i> Medienbrowser',
            content: modal,
            width: "1024",
            height: "82%",
            minWidth: "860",
            minHeight: "460",
            maxWidth: "96%",
            maxHeight: "94%",
            position: "center",
            scroll: 0,
            resizable: 1,
            minimizable: 1,
            maximizable: 1,
            reloadable: 0,
            persist: 0,
            reuse: 0
        }, root);
        if (id) {
            modal.__dbxCmsWindowId = id;
            modal.hidden = false;
            const win = document.getElementById(id) || closestElement(modal, ".dbx-window");
            raiseMediaBrowserWindow(win, modal);
        }
        return !!id;
    }

    function raiseMediaBrowserWindow(win, modal) {
        win = win || closestElement(modal, ".dbx-window");
        if (!win && modal && modal.__dbxCmsWindowId) win = document.getElementById(modal.__dbxCmsWindowId);
        if (!win) return;
        const apply = () => {
            win.classList.add("dbx-cms-media-browser-window");
            if (dbx.openWin && typeof dbx.openWin.bringToFront === "function" && win.id) {
                dbx.openWin.bringToFront(win.id);
            }
        };
        apply();
        window.requestAnimationFrame(apply);
        window.setTimeout(apply, 80);
    }

    function mediaBrowserPreset(modal, value) {
        if (!modal || !value) return;
        const width = qs(modal, "[data-cms-bulk-resize-width]");
        const height = qs(modal, "[data-cms-bulk-resize-height]");
        if (!width || !height) return;
        const parts = String(value).toLowerCase().split("x");
        width.value = Number(parts[0] || 0) || "";
        height.value = Number(parts[1] || 0) || "";
    }

    function bindMediaBrowserEvents(root, cfg, modal) {
        if (!modal || modal.__dbxCmsEventsBound) return;
        modal.__dbxCmsEventsBound = true;

        const batchBtn = qs(modal, "[data-cms-media-batch-open]");
        if (batchBtn && !batchBtn.__dbxCmsBatchBound) {
            batchBtn.__dbxCmsBatchBound = true;
            batchBtn.addEventListener("mousedown", e => e.stopPropagation());
            batchBtn.addEventListener("click", e => {
                e.preventDefault();
                e.stopPropagation();
                openMediaBatchWindow(modal.__dbxCmsRoot || root, browserCfg(modal), modal);
            });
        }

        modal.addEventListener("click", e => {
            e.stopPropagation();
            const cfg = browserCfg(modal);

            const browserClose = closestElement(e.target, "[data-cms-media-browser-close]");
            if (browserClose && modal.contains(browserClose)) {
                e.preventDefault();
                if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.close === "function") {
                    dbx.openWin.close(modal.__dbxCmsWindowId);
                } else {
                    modal.hidden = true;
                }
                clearCmsLoading(root);
                return;
            }

            const batchOpen = closestElement(e.target, "[data-cms-media-batch-open]");
            if (batchOpen && modal.contains(batchOpen)) {
                e.preventDefault();
                e.stopPropagation();
                openMediaBatchWindow(root, cfg, modal);
                return;
            }

            const browserMaintenance = closestElement(e.target, "[data-cms-media-maintenance]");
            if (browserMaintenance && modal.contains(browserMaintenance)) {
                e.preventDefault();
                e.stopPropagation();
                const batchPanel = modal.__dbxCmsBatchPanel || null;
                if (batchPanel) batchPanel.hidden = true;
                renderMediaMaintenanceHome(root, cfg, modal, null);
                return;
            }

            const processStart = closestElement(e.target, "[data-cms-media-process-start]");
            if (processStart && modal.contains(processStart)) {
                e.preventDefault();
                e.stopPropagation();
                startMediaMaintenance(root, cfg, modal, null);
                return;
            }

            const unusedAction = closestElement(e.target, "[data-cms-media-unused-action]");
            if (unusedAction && modal.contains(unusedAction)) {
                e.preventDefault();
                e.stopPropagation();
                executeUnusedMediaMaintenance(root, cfg, modal, unusedAction.getAttribute("data-cms-media-unused-action"));
                return;
            }

            const processClose = closestElement(e.target, "[data-cms-media-process-close]");
            if (processClose && modal.contains(processClose)) {
                e.preventDefault();
                e.stopPropagation();
                const panel = closestElement(processClose, "[data-cms-media-process-panel]");
                if (panel) {
                    panel.hidden = true;
                    panel.innerHTML = "";
                }
                modal.classList.remove("is-process-open");
                clearCmsLoading(root);
                return;
            }

            const folderCreate = closestElement(e.target, "[data-cms-folder-create]");
            if (folderCreate && modal.contains(folderCreate)) {
                e.preventDefault();
                createMediaFolder(root, cfg, modal);
                return;
            }

            const folderDelete = closestElement(e.target, "[data-cms-folder-delete]");
            if (folderDelete && modal.contains(folderDelete)) {
                e.preventDefault();
                deleteSelectedMediaFolder(root, cfg, modal);
                return;
            }

            const folderRename = closestElement(e.target, "[data-cms-folder-rename]");
            if (folderRename && modal.contains(folderRename)) {
                e.preventDefault();
                renameSelectedMediaFolder(root, cfg, modal);
                return;
            }

            const folderToggle = closestElement(e.target, "[data-cms-media-folder-toggle]");
            if (folderToggle && modal.contains(folderToggle)) {
                e.preventDefault();
                const tree = qs(modal, "[data-cms-media-folder-tree]");
                if (tree) {
                    setMediaBrowserFolderTreeMode(modal, !modal.classList.contains("is-folder-tree-open"));
                }
                return;
            }

            const folderTreeClose = closestElement(e.target, "[data-cms-media-folder-tree-close]");
            if (folderTreeClose && modal.contains(folderTreeClose)) {
                e.preventDefault();
                e.stopPropagation();
                setMediaBrowserFolderTreeMode(modal, false);
                return;
            }

            const treeSize = closestElement(e.target, "[data-cms-media-tree-size]");
            if (treeSize && modal.contains(treeSize)) {
                e.preventDefault();
                e.stopPropagation();
                modal.__dbxCmsMediaTreeSize = String(treeSize.getAttribute("data-cms-media-tree-size") || "medium");
                renderMediaFolderTree(modal, modal.__dbxCmsFolders || []);
                return;
            }

            const treeFolderSelect = closestElement(e.target, "[data-cms-media-tree-folder-select]");
            if (treeFolderSelect && modal.contains(treeFolderSelect)) {
                e.preventDefault();
                e.stopPropagation();
                modal.__dbxCmsMediaTreeFolder = String(treeFolderSelect.getAttribute("data-folder") || "");
                renderMediaFolderTree(modal, modal.__dbxCmsFolders || []);
                return;
            }

            const folderDropClick = closestElement(e.target, "[data-cms-media-folder-drop]");
            if (folderDropClick && modal.contains(folderDropClick)) {
                e.preventDefault();
                e.stopPropagation();
                const rows = selectedMediaBrowserRows(modal);
                if (!rows.length) {
                    status(root, "Medien markieren oder per Drag auf den Zielordner ziehen.", "info");
                    return;
                }
                moveMediaRowsToFolder(root, cfg, modal, rows, folderDropClick.getAttribute("data-folder") || "");
                return;
            }

            const selectBtn = closestElement(e.target, "[data-cms-media-browser-select]");
            if (selectBtn && modal.contains(selectBtn)) {
                e.preventDefault();
                e.stopPropagation();
                const item = closestElement(selectBtn, "[data-cms-media-browser-item]");
                if (item) toggleMediaBrowserSelection(modal, item);
                return;
            }

            const browserConfirm = closestElement(e.target, "[data-cms-media-browser-confirm]");
            if (browserConfirm && modal.contains(browserConfirm)) {
                e.preventDefault();
                if (modal.__dbxCmsMediaMode === "pick") {
                    confirmPickMediaBrowser(root, modal);
                    return;
                }
                const slot = modal.__dbxCmsAssignSlot || currentMediaSlot(root);
                const rows = selectedMediaBrowserRows(modal);
                if (!rows.length) {
                    status(root, "Bitte mindestens ein Bild auswaehlen.", "error");
                    return;
                }

                let chain = Promise.resolve();
                rows.forEach(row => {
                    chain = chain.then(() => assignMedia(root, cfg, row, slot).then(assignedRow => {
                        if (!assignedRow) return;
                        if (slot === "inline") {
                            insertMediaRow(root, assignedRow);
                            setLocalMediaSlot(root, assignedRow.id, "inline");
                        }
                        if (typeof modal.__dbxCmsAfterAssign === "function") {
                            modal.__dbxCmsAfterAssign(assignedRow);
                        }
                    }));
                });
                chain.then(() => {
                    modal.hidden = true;
                    if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.close === "function") dbx.openWin.close(modal.__dbxCmsWindowId);
                    clearCmsLoading(root);
                    status(root, "Auswahl uebernommen.", "success");
                });
                return;
            }

            const browserDelete = closestElement(e.target, "[data-cms-media-browser-delete]");
            if (browserDelete && modal.contains(browserDelete)) {
                e.preventDefault();
                const item = closestElement(browserDelete, "[data-cms-media-browser-item]");
                const mode = modal.__dbxCmsMediaMode === "assign" ? "assign" : "editor";
                deleteMedia(root, cfg, Number(item?.getAttribute("data-media-id") || 0))
                    .then(() => openMediaBrowser(modal.__dbxCmsRoot || root, cfg, {
                        mode,
                        slot: modal.__dbxCmsAssignSlot || currentMediaSlot(root),
                        mediaFolder: modal.__dbxCmsMediaFolder || "",
                        formDataExtra: modal.__dbxCmsFormDataExtra || null,
                        afterAssign: modal.__dbxCmsAfterAssign
                    }));
                return;
            }

            const browserEdit = closestElement(e.target, "[data-cms-media-browser-edit]");
            if (browserEdit && modal.contains(browserEdit)) {
                e.preventDefault();
                const item = closestElement(browserEdit, "[data-cms-media-browser-item]");
                openMediaEdit(root, cfg, mediaRowFromItem(item));
                return;
            }

            const browserPick = closestElement(e.target, "[data-cms-media-browser-pick]");
            if (browserPick && modal.contains(browserPick)) {
                e.preventDefault();
                const item = closestElement(browserPick, "[data-cms-media-browser-item]") || browserPick;
                const mediaRow = mediaRowFromItem(item);
                const mode = modal.__dbxCmsMediaMode || "editor";
                if (mode === "pick" || mode === "assign") {
                    toggleMediaBrowserSelection(modal, item);
                    return;
                }
                assignMedia(root, cfg, mediaRow, "inline").then(assignedRow => {
                    if (!assignedRow) return;
                    insertMediaRow(root, assignedRow);
                    setLocalMediaSlot(root, assignedRow.id, "inline");
                    modal.hidden = true;
                    if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.close === "function") dbx.openWin.close(modal.__dbxCmsWindowId);
                    clearCmsLoading(root);
                });
            }
        });

        modal.addEventListener("change", e => {
            e.stopPropagation();
            const uploadForm = closestElement(e.target, "[data-cms-browser-upload]");
            if (uploadForm && modal.contains(uploadForm)) updateUploadLabel(uploadForm);
            const uploadFolderSelect = closestElement(e.target, "[data-cms-upload-folder]");
            if (uploadFolderSelect && modal.contains(uploadFolderSelect)) uploadFolderSelect.__dbxCmsTouched = true;
        });

        modal.addEventListener("dragstart", e => {
            const item = closestElement(e.target, "[data-cms-media-browser-item]");
            if (item && modal.contains(item)) {
                const id = Number(item.getAttribute("data-media-id") || 0);
                if (!id || isExternalVideoRow(mediaRowFromItem(item))) {
                    e.preventDefault();
                    return;
                }
                modal.__dbxCmsDraggedMediaId = id;
                modal.__dbxCmsDraggedFolder = "";
                item.classList.add("is-dragging");
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = "move";
                    e.dataTransfer.setData("text/plain", String(id));
                    e.dataTransfer.setData("application/x-dbx-media-id", String(id));
                }
                return;
            }

            const folder = closestElement(e.target, "[data-cms-media-tree-folder-select]");
            if (!folder || !modal.contains(folder)) return;
            const folderPath = String(folder.getAttribute("data-folder") || "");
            if (!folderPath) {
                e.preventDefault();
                return;
            }
            modal.__dbxCmsDraggedFolder = folderPath;
            modal.__dbxCmsDraggedMediaId = 0;
            folder.classList.add("is-dragging");
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = "move";
                e.dataTransfer.setData("text/plain", folderPath);
                e.dataTransfer.setData("application/x-dbx-media-folder", folderPath);
            }
        });

        modal.addEventListener("dragend", e => {
            const item = closestElement(e.target, "[data-cms-media-browser-item]");
            if (item) item.classList.remove("is-dragging");
            const folder = closestElement(e.target, "[data-cms-media-tree-folder-select]");
            if (folder) folder.classList.remove("is-dragging");
            qsa(modal, "[data-cms-media-folder-drop].is-dragover").forEach(el => el.classList.remove("is-dragover"));
            modal.__dbxCmsDraggedMediaId = 0;
            modal.__dbxCmsDraggedFolder = "";
        });

        modal.addEventListener("dragover", e => {
            e.stopPropagation();
            const folderDrop = closestElement(e.target, "[data-cms-media-folder-drop]");
            if (folderDrop && modal.contains(folderDrop)) {
                e.preventDefault();
                folderDrop.classList.add("is-dragover");
                if (e.dataTransfer) e.dataTransfer.dropEffect = "move";
                return;
            }
            const dropzone = closestElement(e.target, "[data-cms-dropzone]");
            if (!dropzone || !modal.contains(dropzone)) return;
            e.preventDefault();
            dropzone.classList.add("is-dragover");
        });

        modal.addEventListener("dragleave", e => {
            e.stopPropagation();
            const folderDrop = closestElement(e.target, "[data-cms-media-folder-drop]");
            if (folderDrop && modal.contains(folderDrop)) {
                folderDrop.classList.remove("is-dragover");
                return;
            }
            const dropzone = closestElement(e.target, "[data-cms-dropzone]");
            if (!dropzone || !modal.contains(dropzone)) return;
            dropzone.classList.remove("is-dragover");
        });

        modal.addEventListener("drop", e => {
            e.stopPropagation();
            const folderDrop = closestElement(e.target, "[data-cms-media-folder-drop]");
            if (folderDrop && modal.contains(folderDrop)) {
                e.preventDefault();
                folderDrop.classList.remove("is-dragover");
                const target = folderDrop.getAttribute("data-folder") || "";
                const draggedFolder = String((e.dataTransfer && e.dataTransfer.getData("application/x-dbx-media-folder")) || modal.__dbxCmsDraggedFolder || "");
                if (draggedFolder) {
                    moveMediaFolderToFolder(root, browserCfg(modal), modal, draggedFolder, target);
                    return;
                }
                const id = Number((e.dataTransfer && (e.dataTransfer.getData("application/x-dbx-media-id") || e.dataTransfer.getData("text/plain"))) || modal.__dbxCmsDraggedMediaId || 0);
                moveMediaRowsToFolder(root, browserCfg(modal), modal, draggedMediaRows(modal, id), target);
                return;
            }
            const dropzone = closestElement(e.target, "[data-cms-dropzone]");
            if (!dropzone || !modal.contains(dropzone)) return;
            e.preventDefault();
            dropzone.classList.remove("is-dragover");
            const form = closestElement(dropzone, "form");
            const files = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length ? e.dataTransfer.files : null;
            if (!form || !files || !files.length) return;
            if (!setUploadFiles(form, files)) {
                status(root, "Datei bitte ueber die Dateiauswahl waehlen.", "error");
            }
        });
    }

    function openMediaBrowser(root, cfg, options) {
        options = options || {};
        cfg = Object.assign({}, cfg || {});
        const mode = options.mode === "assign" ? "assign" : (options.mode === "pick" ? "pick" : "editor");
        const assignSlot = options.slot || (mode === "assign" ? currentMediaSlot(root) : "inline");
        let mediaFolder = String(options.mediaFolder || options.media_folder || "").trim();
        if (!mediaFolder && mediaBrowserProfile(cfg) === "mod") mediaFolder = "mod";
        const formDataExtra = options.formDataExtra && typeof options.formDataExtra === "object" ? options.formDataExtra : null;
        const afterAssign = typeof options.afterAssign === "function" ? options.afterAssign : null;
        clearCmsLoading(root);
        const mediaUrl = cfgUrl(cfg || {}, "media");
        if (!mediaUrl) {
            status(root, "Medienbrowser ist nicht konfiguriert.", "error");
            return;
        }

        let modal = state(root).mediaBrowser;
        if (!modal || !document.documentElement.contains(modal)) {
            modal = qs(root, "[data-cms-media-browser]");
        }
        if (mediaBrowserNeedsRebuild(modal)) {
            disposeMediaBrowserModal(root, modal);
            modal = null;
        }
        if (!modal) {
            const uploadFormHtml = mediaBrowserFormHtml(root, "[data-cms-media-upload-template]");
            const externalVideoFormHtml = mediaBrowserFormHtml(root, "[data-cms-external-video-template]");
            if (!uploadFormHtml) {
                status(root, "Das dbxForm-Uploadformular fuer den Medienbrowser fehlt.", "error");
                return;
            }
            modal = document.createElement("div");
            modal.className = "dbx-cms-media-browser";
            modal.setAttribute("data-cms-media-browser", "1");
            modal.innerHTML = `
                <div class="dbx-cms-media-browser-head">
                    <div>
                        <strong><i class="bi bi-images"></i> Medienbrowser</strong>
                        <div class="small text-muted" data-cms-media-root-hint></div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-browser-close title="Schliessen"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="dbx-cms-status dbx-cms-media-browser-status" data-cms-status aria-live="polite"></div>
                <details class="dbx-cms-media-upload-panel" data-cms-media-upload-panel>
                    <summary>
                        <span class="dbx-cms-media-upload-summary-main">
                            <i class="bi bi-chevron-right dbx-cms-toggle-icon"></i>
                            <span>Upload und YouTube</span>
                        </span>
                        <span class="dbx-cms-media-upload-summary-actions">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-batch-open title="Batch Resize">
                                <i class="bi bi-tools"></i>
                                <span>Batch</span>
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-maintenance title="Medienwartung im Medienbrowser starten">
                                <i class="bi bi-tools"></i>
                                <span>Wartung</span>
                            </button>
                        </span>
                    </summary>
                    ${uploadFormHtml}
                    <div class="dbx-cms-media-folderbar">
                        <span class="small text-muted dbx-cms-media-folderbar-title">Neuer Unterordner:</span>
                        <select class="form-select form-select-sm" data-cms-folder-parent title="Vorhandenen Ordner als Basis waehlen"></select>
                        <input type="text" class="form-control form-control-sm" data-cms-folder-name placeholder="Ordnername">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-cms-folder-create title="Ordner anlegen">
                            <i class="bi bi-folder-plus"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-cms-folder-delete title="Ausgewaehlten Upload-Ordner loeschen">
                            <i class="bi bi-folder-x"></i>
                        </button>
                        <input type="text" class="form-control form-control-sm" data-cms-folder-rename-name placeholder="Neuer Ordnername">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-folder-rename title="Ordner umbenennen">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </div>
                    ${externalVideoFormHtml}
                </details>
                <div class="dbx-cms-media-process-panel" data-cms-media-process-panel hidden></div>
                <div class="dbx-cms-media-browser-tools">
                    <button type="button" class="btn btn-outline-primary btn-sm dbx-cms-media-folder-toggle" data-cms-media-folder-toggle title="Medienordner anzeigen">
                        <i class="bi bi-list-ul"></i>
                    </button>
                    <input type="text" class="form-control form-control-sm" data-cms-media-browser-search placeholder="Medien suchen">
                    <select class="form-select form-select-sm" data-cms-media-browser-folder title="Verzeichnis anzeigen">
                        <option value="all">Alle Verzeichnisse</option>
                    </select>
                    <select class="form-select form-select-sm" data-cms-media-browser-slot title="Bereich anzeigen">
                        <option value="all">Alle</option>
                        <option value="gallery">Galerie</option>
                        <option value="hero">Hero</option>
                        <option value="inline">Im Text</option>
                        <option value="shop">Shop</option>
                    </select>
                </div>
                <div class="dbx-cms-media-folder-tree" data-cms-media-folder-tree hidden></div>
                <div class="dbx-cms-media-browser-list" data-cms-media-browser-list>
                    ${mediaBrowserSkeletonHtml(24)}
                </div>
                <div class="dbx-cms-media-browser-confirm" data-cms-media-browser-confirmbar hidden>
                    <span><strong data-cms-media-browser-count>0</strong> Medien ausgewaehlt</span>
                    <button type="button" class="btn btn-primary btn-sm" data-cms-media-browser-confirm>
                        <i class="bi bi-check2"></i>
                        <span>Auswahl uebernehmen</span>
                    </button>
                </div>`;
            // Medienmanager koennen selbst innerhalb eines grossen
            // dbxForm-Formulars liegen (z. B. Artikel bearbeiten). Das Modal
            // wird dann als Geschwisterelement angehaengt, damit seine beiden
            // Formulare niemals in ein anderes <form> verschachtelt werden.
            const ownerForm = closestElement(root, "form");
            const modalHost = ownerForm && ownerForm.parentElement ? ownerForm.parentElement : root;
            modalHost.appendChild(modal);

            const browserUpload = qs(modal, "[data-cms-browser-upload]");
            if (browserUpload) {
                browserUpload.addEventListener("submit", e => {
                    e.preventDefault();
                    const currentMode = modal.__dbxCmsMediaMode || "editor";
                    const currentSlot = modal.__dbxCmsAssignSlot || (currentMode === "assign" ? currentMediaSlot(root) : "inline");
                    uploadMedia(root, browserCfg(modal), browserUpload, {
                        pickMode: currentMode === "pick",
                        insertUploaded: currentMode === "editor",
                        formDataExtra: modal.__dbxCmsFormDataExtra || null,
                        afterUpload: data => {
                            const rows = data && Array.isArray(data.rows) ? data.rows : (data && data.row ? [data.row] : []);
                            const responses = data && Array.isArray(data.responses) ? data.responses : [];
                            const moduleResp = responses.find(item => item && Array.isArray(item.items)) || null;
                            if (currentMode === "pick" && data && data.ok && moduleResp && Array.isArray(moduleResp.items)) {
                                if (typeof modal.__dbxCmsAfterAssign === "function") {
                                    modal.__dbxCmsAfterAssign({ items: moduleResp.items, filename: moduleResp.filename || "" });
                                }
                                modal.hidden = true;
                                if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.close === "function") {
                                    dbx.openWin.close(modal.__dbxCmsWindowId);
                                }
                                clearCmsLoading(root);
                                return;
                            }
                            if (currentMode === "pick" && rows.length) {
                                let chain = Promise.resolve();
                                rows.forEach(row => {
                                    chain = chain.then(() => {
                                        if (typeof modal.__dbxCmsAfterAssign === "function") {
                                            return Promise.resolve(modal.__dbxCmsAfterAssign(row));
                                        }
                                    });
                                });
                                chain.then(() => {
                                    modal.hidden = true;
                                    if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.close === "function") {
                                        dbx.openWin.close(modal.__dbxCmsWindowId);
                                    }
                                    clearCmsLoading(root);
                                });
                                return;
                            }
                            if (currentMode === "assign" && rows.length) {
                                rows.forEach(row => {
                                    if (currentSlot === "inline") applyInlineMediaAssignment(root, row);
                                    else setLocalMediaSlot(root, row.id, currentSlot);
                                    if (typeof modal.__dbxCmsAfterAssign === "function") modal.__dbxCmsAfterAssign(row);
                                });
                            }
                            const refreshFolder = data.uploadFolder
                                || (rows[0] && rows[0].media_folder)
                                || qs(modal, "[data-cms-upload-folder]")?.value
                                || modal.__dbxCmsMediaFolder
                                || "";
                            openMediaBrowser(root, browserCfg(modal), {
                                mode: currentMode,
                                slot: currentSlot,
                                mediaFolder: refreshFolder,
                                formDataExtra: modal.__dbxCmsFormDataExtra || null,
                                afterAssign: modal.__dbxCmsAfterAssign
                            });
                        }
                    });
                });
            }
            const browserExternalVideo = qs(modal, "[data-cms-browser-external-video]");
            if (browserExternalVideo) {
                browserExternalVideo.addEventListener("submit", e => {
                    e.preventDefault();
                    const currentMode = modal.__dbxCmsMediaMode || "editor";
                    const currentSlot = modal.__dbxCmsAssignSlot || (currentMode === "assign" ? currentMediaSlot(root) : "inline");
                    addExternalVideo(root, browserCfg(modal), browserExternalVideo, {
                        insertExternal: currentMode === "editor",
                        slot: currentSlot,
                        afterExternal: data => {
                            const row = data && data.row ? data.row : null;
                            if (currentMode === "assign" && row) {
                                if (currentSlot === "inline") applyInlineMediaAssignment(root, row);
                                else setLocalMediaSlot(root, row.id, currentSlot);
                                if (typeof modal.__dbxCmsAfterAssign === "function") modal.__dbxCmsAfterAssign(row);
                            }
                            const refreshFolder = (row && row.media_folder)
                                || qs(modal, "[data-cms-upload-folder]")?.value
                                || modal.__dbxCmsMediaFolder
                                || "";
                            openMediaBrowser(root, browserCfg(modal), {
                                mode: currentMode,
                                slot: currentSlot,
                                mediaFolder: refreshFolder,
                                formDataExtra: modal.__dbxCmsFormDataExtra || null,
                                afterAssign: modal.__dbxCmsAfterAssign
                            });
                        }
                    });
                });
            }
            state(root).mediaBrowser = modal;
        }
        state(root).mediaBrowser = modal;
        modal.__dbxCmsCfg = cfg;
        modal.__dbxCmsRoot = root;
        ensureMediaBrowserFolderUi(modal);
        applyMediaBrowserProfile(modal, cfg);
        bindMediaBrowserEvents(root, cfg, modal);

        const list = qs(modal, "[data-cms-media-browser-list]");
        const search = qs(modal, "[data-cms-media-browser-search]");
        const slotSelect = qs(modal, "[data-cms-media-browser-slot]");
        const folderSelect = qs(modal, "[data-cms-media-browser-folder]");
        const uploadFolder = qs(modal, "[data-cms-upload-folder]");
        const uploadSlot = qs(modal, 'input[name="slot"]');
        const uploadTemplate = qs(modal, 'input[name="template"]');
        const externalVideoSlot = qs(modal, '[data-cms-browser-external-video] input[name="slot"]');
        modal.__dbxCmsMediaMode = mode;
        modal.__dbxCmsAssignSlot = assignSlot;
        modal.__dbxCmsAfterAssign = afterAssign;
        modal.__dbxCmsMediaFolder = mediaFolder;
        modal.__dbxCmsFormDataExtra = formDataExtra;
        modal.__dbxCmsSinglePick = options.singlePick === true;
        modal.__dbxCmsSelectedIds = new Set();
        updateMediaBrowserConfirm(modal);
        if (slotSelect) slotSelect.value = "all";
        if (folderSelect) folderSelect.value = mediaFolder || folderSelect.value || "all";
        if (uploadSlot) uploadSlot.value = assignSlot;
        if (uploadTemplate) uploadTemplate.value = mode === "assign" ? "" : "image-inline";
        if (externalVideoSlot) externalVideoSlot.value = assignSlot;
        clearCmsLoading(root);
        modal.hidden = false;
        openMediaBrowserWindow(root, modal);
        modal.classList.add("is-loading");
        modal.setAttribute("aria-busy", "true");
        const requestId = Date.now() + "-" + Math.random();
        modal.__dbxCmsMediaRequest = requestId;
        if (search) search.value = "";
        if (list) list.innerHTML = mediaBrowserSkeletonHtml(24);

        refreshMediaFolderControls(root, cfg, modal).then(() => {
            if (mediaFolder && folderSelect) {
                folderSelect.value = mediaFolder;
            }
            const uploadFolders = modal.__dbxCmsUploadFolders || uploadFolderOptions(modal.__dbxCmsFolders || []);
            syncUploadFolderSelect(modal, uploadFolders, mediaFolder);
            const mediaParams = mediaBrowserQueryParams(String(folderSelect?.value || mediaFolder || "all"));
            if (!modal.__dbxCmsMediaSynced) mediaParams.sync = 1;
            if (formDataExtra && formDataExtra.xmodul) mediaParams.xmodul = formDataExtra.xmodul;
            return fetchJson(apiUrl(mediaUrl, mediaParams), { timeout: 30000 });
        })
            .then(data => {
                if (modal.__dbxCmsMediaRequest !== requestId) return;
                if (!data || !data.ok) throw new Error("bad response");
                const rows = Array.isArray(data.rows) ? data.rows : [];
                modal.__dbxCmsMediaSynced = true;
                modal.__dbxCmsRows = rows;
                renderMediaFolderTree(modal, modal.__dbxCmsFolders || []);
                const render = () => {
                    const term = String(search?.value || "").toLowerCase();
                    const slotFilter = String(slotSelect?.value || "all");
                    const folderFilter = String(folderSelect?.value || "all");
                    const selected = modal.__dbxCmsSelectedIds || new Set();
                    const multi = isMediaBrowserMulti(modal);
                    const needsConfirm = mediaBrowserUsesConfirmBar(modal);
                    const filtered = rows.filter(row => {
                        const hay = String((row.title || "") + " " + (row.file_name || "") + " " + (row.alt || "")).toLowerCase();
                        const matchTerm = !term || hay.includes(term);
                        const matchSlot = slotFilter === "all" || String(row.slot || "").trim() === slotFilter;
                        const matchFolder = folderFilter === "all" || String(row.media_folder || "") === folderFilter;
                        return matchTerm && matchSlot && matchFolder;
                    });
                    if (!filtered.length) {
                        list.innerHTML = '<div class="dbx-cms-empty">Keine passenden Medien gefunden.</div>';
                        updateMediaBrowserBatchUi(modal);
                        return;
                    }
                    renderMediaRowsChunked(
                        list,
                        filtered,
                        row => mediaBrowserItemHtml(row, selected, needsConfirm),
                        () => {
                            updateMediaBrowserConfirm(modal);
                            updateMediaBrowserBatchUi(modal);
                            renderMediaBatchList(modal);
                        }
                    );
                };
                render();
                if (search && !search.__dbxCmsMediaBrowserBound) {
                    search.__dbxCmsMediaBrowserBound = true;
                    search.addEventListener("input", render);
                }
                if (slotSelect && !slotSelect.__dbxCmsMediaBrowserBound) {
                    slotSelect.__dbxCmsMediaBrowserBound = true;
                    slotSelect.addEventListener("change", render);
                }
                if (folderSelect && !folderSelect.__dbxCmsMediaBrowserBound) {
                    folderSelect.__dbxCmsMediaBrowserBound = true;
                    folderSelect.addEventListener("change", () => {
                        const uploadFolders = modal.__dbxCmsUploadFolders || uploadFolderOptions(modal.__dbxCmsFolders || []);
                        syncUploadFolderSelect(modal, uploadFolders, folderSelect.value);
                        openMediaBrowser(modal.__dbxCmsRoot || root, browserCfg(modal), {
                            mode,
                            slot: assignSlot,
                            mediaFolder: folderSelect.value,
                            formDataExtra: modal.__dbxCmsFormDataExtra || null,
                            afterAssign: modal.__dbxCmsAfterAssign
                        });
                    });
                }
            })
            .catch(err => {
                if (modal.__dbxCmsMediaRequest !== requestId) return;
                dbx.error("[cms] media browser failed", err);
                if (list) list.innerHTML = '<div class="dbx-cms-empty">Medien konnten nicht geladen werden.</div>';
            })
            .finally(() => {
                if (modal.__dbxCmsMediaRequest === requestId) {
                    modal.classList.remove("is-loading");
                    modal.removeAttribute("aria-busy");
                    clearCmsLoading(root);
                    window.setTimeout(() => clearCmsLoading(root), 50);
                }
            });
    }

    function openModBrowserWindow(root, modal) {
        if (!modal) return false;
        if (!dbx.openWin || typeof dbx.openWin.open !== "function") {
            ensureOpenWin().then(ok => {
                if (ok) openModBrowserWindow(root, modal);
                else status(root, "openWin.js nicht geladen.", "error");
            });
            return false;
        }
        const currentWindow = closestElement(modal, ".dbx-window");
        const ownWindowId = modal.__dbxCmsWindowId || "";
        if (currentWindow && ownWindowId && currentWindow.id === ownWindowId) {
            modal.hidden = false;
            if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.bringToFront === "function") {
                dbx.openWin.bringToFront(modal.__dbxCmsWindowId);
            }
            return true;
        }
        if (currentWindow && (!ownWindowId || currentWindow.id !== ownWindowId)) {
            document.body.appendChild(modal);
        }
        const id = dbx.openWin.open({
            title: '<i class="bi bi-puzzle"></i> Modul Aufruf',
            content: modal,
            width: "760",
            height: "520",
            minWidth: "560",
            minHeight: "360",
            position: "center",
            scroll: 0,
            topZ: 1,
            priority: "top",
            resizable: 1,
            minimizable: 1,
            maximizable: 1,
            reloadable: 0,
            persist: 0,
            reuse: 0
        }, root);
        if (id) {
            modal.__dbxCmsWindowId = id;
            modal.hidden = false;
            if (dbx.openWin && typeof dbx.openWin.bringToFront === "function") {
                dbx.openWin.bringToFront(id);
            }
        }
        return !!id;
    }

    function closeModBrowserWindow(modal) {
        if (!modal) return;
        const winId = modal.__dbxCmsWindowId || "";
        modal.__dbxCmsWindowId = null;
        if (winId && dbx.openWin && typeof dbx.openWin.close === "function") {
            dbx.openWin.close(winId);
            return;
        }
        modal.hidden = true;
    }

    function renderModBrowserImages(list, items) {
        if (!list) return;
        if (!items.length) {
            list.innerHTML = '<div class="dbx-cms-empty">Keine Modul-Bilder in files/mod gefunden.</div>';
            return;
        }
        list.innerHTML = items.map(item => `
            <div class="dbx-cms-mod-browser-item" data-cms-mod-browser-item
                 data-url="${escapeHtml(item.url || "")}"
                 data-label="${escapeHtml(item.label || "")}"
                 data-modul="${escapeHtml(item.default_modul || "")}"
                 data-params="${escapeHtml(item.default_params || "")}"
                 data-alt="${escapeHtml(item.default_alt || item.label || "")}">
                <button type="button" class="dbx-cms-mod-browser-pick" data-cms-mod-browser-pick title="In Editor einfuegen">
                    <img src="${escapeHtml(item.url || "")}" alt="${escapeHtml(item.label || "")}" loading="lazy">
                    <span class="dbx-cms-mod-browser-label">${escapeHtml(item.label || "")}</span>
                    <small class="dbx-cms-mod-browser-params">${escapeHtml(item.description || item.default_params || "")}</small>
                </button>
            </div>`).join("");
    }

    function renderModBrowserModules(list, items) {
        if (!list) return;
        if (!items.length) {
            list.innerHTML = '<div class="dbx-cms-empty">Keine Module gefunden.</div>';
            return;
        }
        list.innerHTML = items.filter(item => Number(item.image_count || 0) > 0).map(item => {
            const count = Number(item.image_count || 0);
            const runs = Array.isArray(item.run1_actions) ? item.run1_actions.slice(0, 4).join(", ") : "";
            const hint = count > 0
                ? (count + " Bild" + (count === 1 ? "" : "er"))
                : "keine Bilder";
            return `
            <div class="dbx-cms-mod-browser-module" data-cms-mod-browser-module data-modul="${escapeHtml(item.id || "")}">
                <button type="button" class="dbx-cms-mod-browser-module-pick" data-cms-mod-browser-module-pick>
                    <span class="dbx-cms-mod-browser-module-name"><i class="bi bi-puzzle"></i> ${escapeHtml(item.label || item.id || "")}</span>
                    <span class="dbx-cms-mod-browser-module-meta">${escapeHtml(hint)}</span>
                    ${runs ? `<small class="dbx-cms-mod-browser-module-runs">${escapeHtml(runs)}</small>` : ""}
                </button>
            </div>`;
        }).join("");
        if (!list.innerHTML.trim()) {
            list.innerHTML = '<div class="dbx-cms-empty">Keine Module mit Bildern in files/mod gefunden.</div>';
        }
    }

    function setModBrowserStep(modal, step, modul) {
        if (!modal) return;
        modal.__dbxCmsModStep = step || "modules";
        modal.__dbxCmsModSelected = modul || "";
        modal.classList.toggle("is-image-step", step === "images");
        const title = qs(modal, "[data-cms-mod-browser-title]");
        const back = qs(modal, "[data-cms-mod-browser-back]");
        if (title) {
            title.textContent = step === "images"
                ? ("Modul: " + (modul || ""))
                : "";
        }
        if (back) back.hidden = step !== "images";
    }

    function loadModBrowserModules(root, cfg, modal) {
        const modulesUrl = cfgUrl(cfg, "modmodules");
        const list = qs(modal, "[data-cms-mod-browser-list]");
        if (!modulesUrl) {
            if (list) list.innerHTML = '<div class="dbx-cms-empty">Modul-Liste URL fehlt.</div>';
            return Promise.resolve();
        }
        if (list) list.innerHTML = '<div class="dbx-cms-empty">Lade Module...</div>';
        setModBrowserStep(modal, "modules", "");
        return fetchJson(apiUrl(modulesUrl)).then(data => {
            const items = data && Array.isArray(data.items) ? data.items : [];
            modal.__dbxCmsModModules = items;
            renderModBrowserModules(list, items);
        }).catch(err => {
            dbx.error("[cms] mod modules failed", err);
            if (list) list.innerHTML = '<div class="dbx-cms-empty">Module konnten nicht geladen werden.</div>';
        });
    }

    function loadModBrowserCatalog(root, cfg, modal, modul) {
        const catalogUrl = cfgUrl(cfg, "modcatalog");
        const list = qs(modal, "[data-cms-mod-browser-list]");
        if (!catalogUrl || !modul) {
            if (list) list.innerHTML = '<div class="dbx-cms-empty">Modul-Katalog URL fehlt.</div>';
            return Promise.resolve();
        }
        if (list) list.innerHTML = '<div class="dbx-cms-empty">Lade Modul-Bilder...</div>';
        setModBrowserStep(modal, "images", modul);
        return fetchJson(apiUrl(catalogUrl, { modul: modul })).then(data => {
            const items = data && Array.isArray(data.items) ? data.items : [];
            renderModBrowserImages(list, items);
        }).catch(err => {
            dbx.error("[cms] mod catalog failed", err);
            if (list) list.innerHTML = '<div class="dbx-cms-empty">Modul-Bilder konnten nicht geladen werden.</div>';
        });
    }

    function bindModBrowserEvents(root, cfg, modal) {
        if (!modal || modal.__dbxCmsModEventsBound) return;
        modal.__dbxCmsModEventsBound = true;

        modal.addEventListener("click", e => {
            e.stopPropagation();

            const backBtn = closestElement(e.target, "[data-cms-mod-browser-back]");
            if (backBtn && modal.contains(backBtn)) {
                e.preventDefault();
                loadModBrowserModules(root, cfg, modal);
                return;
            }

            const modulePick = closestElement(e.target, "[data-cms-mod-browser-module-pick]");
            if (modulePick && modal.contains(modulePick)) {
                e.preventDefault();
                const row = closestElement(modulePick, "[data-cms-mod-browser-module]");
                const modul = row ? (row.getAttribute("data-modul") || "") : "";
                if (!modul) return;
                loadModBrowserCatalog(root, cfg, modal, modul);
                return;
            }

            const pickBtn = closestElement(e.target, "[data-cms-mod-browser-pick]");
            if (pickBtn && modal.contains(pickBtn)) {
                e.preventDefault();
                const item = closestElement(pickBtn, "[data-cms-mod-browser-item]");
                if (!item) return;
                const row = {
                    url: item.getAttribute("data-url") || "",
                    label: item.getAttribute("data-label") || "",
                    default_modul: item.getAttribute("data-modul") || "",
                    default_params: item.getAttribute("data-params") || "",
                    default_alt: item.getAttribute("data-alt") || ""
                };
                insertModPlaceholder(root, row, cfg);
                closeModBrowserWindow(modal);
            }
        });
    }

    function openModBrowser(root, cfg) {
        cfg = cfg || cmsConfig(root) || {};
        saveEditorSelection(root);
        if (!cfgUrl(cfg, "modmodules") || !cfgUrl(cfg, "modcatalog")) {
            status(root, "Modul-Browser URLs fehlen.", "error");
            return;
        }
        let modal = state(root).modBrowser;
        if (!modal || !document.documentElement.contains(modal)) {
            modal = qs(root, "[data-cms-mod-browser]");
        }
        if (!modal) {
            modal = document.createElement("div");
            modal.className = "dbx-cms-mod-browser";
            modal.setAttribute("data-cms-mod-browser", "1");
            modal.innerHTML = `
                <div class="dbx-cms-mod-browser-head">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-mod-browser-back hidden title="Zurueck zur Modulliste">
                        <i class="bi bi-arrow-left"></i>
                    </button>
                    <span data-cms-mod-browser-title></span>
                </div>
                <div class="dbx-cms-mod-browser-list" data-cms-mod-browser-list>
                    <div class="dbx-cms-empty">Lade Module...</div>
                </div>`;
            root.appendChild(modal);
            state(root).modBrowser = modal;
        }
        state(root).modBrowser = modal;
        modal.__dbxCmsRoot = root;
        bindModBrowserEvents(root, cfg, modal);
        modal.hidden = false;
        openModBrowserWindow(root, modal);
        loadModBrowserModules(root, cfg, modal);
    }

    function modOptionsNeedsRebuild(modal) {
        return !!qs(modal, "[data-cms-mod-options-dbx]") || !!qs(modal, "select[data-cms-mod-options-modul]");
    }

    function ensureModPlaceholderOptionsDialog(root) {
        const s = state(root);
        let modal = s.modOptionsModal || qs(root, "[data-cms-mod-options]");
        if (modal && modOptionsNeedsRebuild(modal)) {
            modal.remove();
            modal = null;
            s.modOptionsModal = null;
        }
        if (modal) {
            modal.__dbxCmsRoot = root;
            s.modOptionsModal = modal;
            return modal;
        }
        modal = document.createElement("div");
        modal.className = "dbx-cms-mod-options";
        modal.setAttribute("data-cms-mod-options", "1");
        modal.__dbxCmsRoot = root;
        modal.hidden = true;
        modal.innerHTML = `
            <div class="dbx-cms-mod-options-body">
                <label>Alt-Text (img alt)
                    <input type="text" class="form-control form-control-sm" data-cms-mod-options-alt placeholder="Alternativtext des Pseudo-Bildes">
                </label>
                <div class="dbx-cms-mod-options-module">
                    <span class="dbx-cms-mod-options-module-label">Modul</span>
                    <strong class="dbx-cms-mod-options-module-value" data-cms-mod-options-modul></strong>
                </div>
                <label>Parameter
                    <input type="text" class="form-control form-control-sm" data-cms-mod-options-params placeholder="z.B. dbx_run1=show&root=0">
                    <small class="text-muted">Query-String fuer [modul=...]parameter[/modul]</small>
                </label>
            </div>
            <div class="dbx-cms-mod-options-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-mod-options-close><i class="bi bi-x-lg"></i><span>Abbrechen</span></button>
                <button type="button" class="btn btn-primary btn-sm" data-cms-mod-options-apply><i class="bi bi-check2"></i><span>Uebernehmen</span></button>
            </div>`;
        modal.addEventListener("click", e => {
            if (closestElement(e.target, "[data-cms-mod-options-close]")) {
                e.preventDefault();
                closeModPlaceholderOptionsWindow(modal);
                return;
            }
            if (closestElement(e.target, "[data-cms-mod-options-apply]")) {
                e.preventDefault();
                applyModPlaceholderOptions(root, modal);
            }
        });
        root.appendChild(modal);
        s.modOptionsModal = modal;
        return modal;
    }

    function closeModPlaceholderOptionsWindow(modal) {
        if (!modal) return;
        modal.__dbxCmsModPlaceholder = null;
        modal.hidden = true;
        const winId = modal.__dbxCmsWindowId || "";
        if (winId && dbx.openWin && typeof dbx.openWin.close === "function") {
            dbx.openWin.close(winId);
            modal.__dbxCmsWindowId = null;
        }
    }

    function openModPlaceholderOptions(root, wrapper, cfg) {
        if (!wrapper) return false;
        cfg = cfg || cmsConfig(root) || {};
        const modal = ensureModPlaceholderOptionsDialog(root);
        modal.__dbxCmsModPlaceholder = wrapper;
        modal.__dbxCmsCfg = cfg;
        const values = modPlaceholderValues(wrapper);
        const params = qs(modal, "[data-cms-mod-options-params]");
        const alt = qs(modal, "[data-cms-mod-options-alt]");
        const modulEl = qs(modal, "[data-cms-mod-options-modul]");
        if (params) params.value = values.params || "";
        if (alt) alt.value = values.alt || "";
        if (modulEl) modulEl.textContent = values.modul || "–";
        modal.hidden = false;
        if (!dbx.openWin || typeof dbx.openWin.open !== "function") {
            ensureOpenWin().then(ok => {
                if (ok) openModPlaceholderOptionsWindow(root, modal);
            });
            return true;
        }
        openModPlaceholderOptionsWindow(root, modal);
        return true;
    }

    function openModPlaceholderOptionsWindow(root, modal) {
        if (!modal) return false;
        if (!dbx.openWin || typeof dbx.openWin.open !== "function") {
            ensureOpenWin().then(ok => {
                if (ok) openModPlaceholderOptionsWindow(root, modal);
                else status(root, "openWin.js nicht geladen.", "error");
            });
            return false;
        }
        const currentWindow = closestElement(modal, ".dbx-window");
        const ownWindowId = modal.__dbxCmsWindowId || "";
        if (currentWindow && currentWindow.isConnected && ownWindowId && currentWindow.id === ownWindowId) {
            modal.hidden = false;
            if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.bringToFront === "function") {
                dbx.openWin.bringToFront(modal.__dbxCmsWindowId);
            }
            return true;
        }
        if (currentWindow && currentWindow.isConnected && (!ownWindowId || currentWindow.id !== ownWindowId)) {
            document.body.appendChild(modal);
        } else if (currentWindow && !currentWindow.isConnected) {
            modal.__dbxCmsWindowId = null;
            if (modal.parentNode) modal.parentNode.removeChild(modal);
        }
        const id = dbx.openWin.open({
            title: '<i class="bi bi-puzzle"></i> Modul-Platzhalter',
            content: modal,
            width: "520",
            height: "260",
            minWidth: "420",
            minHeight: "300",
            position: "center",
            scroll: 0,
            topZ: 1,
            priority: "top",
            resizable: 1,
            minimizable: 0,
            maximizable: 0,
            reloadable: 0,
            persist: 0,
            reuse: 0
        }, root);
        if (id) {
            modal.__dbxCmsWindowId = id;
            modal.hidden = false;
            if (dbx.openWin && typeof dbx.openWin.bringToFront === "function") {
                dbx.openWin.bringToFront(id);
            }
        }
        return !!id;
    }

    function applyModPlaceholderOptions(root, modal) {
        const wrapper = modal && modal.__dbxCmsModPlaceholder;
        if (!wrapper) return false;
        const values = modPlaceholderValues(wrapper);
        const modul = values.modul || "";
        const params = qs(modal, "[data-cms-mod-options-params]")?.value || "";
        const alt = qs(modal, "[data-cms-mod-options-alt]")?.value || "";
        const img = qs(wrapper, "img") || qs(wrapper, ".dbx-cms-mod-image");
        if (!modul) {
            status(root, "Modul konnte am Platzhalter nicht erkannt werden.", "warning");
            return false;
        }
        wrapper.setAttribute("data-cms-mod-params", params);
        wrapper.setAttribute("title", alt);
        if (img) {
            img.setAttribute("alt", alt);
            img.setAttribute("title", alt);
            img.removeAttribute("data-dbx");
            img.setAttribute("data-cms-mod-params", params);
        }
        normalizeModPlaceholders(editorSurface(root));
        syncEditorAfterContextAction(root);
        markDirty(root);
        closeModPlaceholderOptionsWindow(modal);
        return true;
    }

    function bindModPlaceholderEvents(root) {
        if (root.__dbxCmsModPlaceholderBound) return;
        root.__dbxCmsModPlaceholderBound = true;
        root.addEventListener("dblclick", e => {
            const video = inlineVideoEventTarget(root, e);
            if (video) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
                openInlineVideoOptions(root, video);
                return;
            }
            const mod = inlineModTarget(root, e.target);
            if (!mod) return;
            e.preventDefault();
            e.stopPropagation();
            openModPlaceholderOptions(root, mod, cmsConfig(root) || {});
        }, true);
    }

    function visibleJoditDialogInputs(panel) {
        return qsa(panel, "input, textarea").filter(input => {
            const type = String(input.getAttribute("type") || "text").toLowerCase();
            if (["hidden", "file", "button", "submit", "reset", "checkbox", "radio"].includes(type)) return false;
            if (input.disabled || input.readOnly) return false;
            return !!input.getClientRects().length;
        });
    }

    function joditDialogValueInputs(panel) {
        return qsa(panel, "input, textarea").filter(input => {
            const type = String(input.getAttribute("type") || "text").toLowerCase();
            if (["hidden", "file", "button", "submit", "reset", "checkbox", "radio"].includes(type)) return false;
            return !input.disabled && !input.readOnly;
        });
    }

    function findJoditImageDialogPathInput(panel) {
        if (panel && panel.__dbxCmsJoditPathInput && panel.contains(panel.__dbxCmsJoditPathInput)) {
            return panel.__dbxCmsJoditPathInput;
        }
        const visibleInputs = visibleJoditDialogInputs(panel);
        const inputs = visibleInputs.length ? visibleInputs : joditDialogValueInputs(panel);
        return inputs.find(input => /src|url|path|image|bild/i.test(String(input.name || input.id || input.getAttribute("placeholder") || "")))
            || inputs.find(input => String(input.value || "").indexOf("index.php") >= 0 || /\.(jpg|jpeg|png|gif|webp|svg)(\?|#|$)/i.test(input.value || ""))
            || inputs[0]
            || null;
    }

    function joditImageDialogPanel(target) {
        const panel = closestElement(target, ".jodit-dialog__panel, .jodit-dialog");
        if (!panel) return null;
        const text = String(panel.textContent || "");
        if (!/Bildeigenschaften|Image properties|Bild/i.test(text)) return null;
        return findJoditImageDialogPathInput(panel) ? panel : null;
    }

    function hideJoditUploadControl(el) {
        if (!el) return;
        const node = closestElement(el, ".jodit-drag-and-drop__file-box, .jodit-form__group, .jodit-ui-block, .jodit-ui-group, .jodit-upload, .jodit-uploader, label, div") || el;
        node.classList.add("dbx-cms-jodit-upload-hidden");
        node.hidden = true;
    }

    function compactJoditImageDialog(panel) {
        if (!panel) return;
        panel.classList.add("dbx-cms-jodit-image-dialog");
        qsa(panel, "input[type='file'], .jodit-drag-and-drop__file-box, .jodit_uploadfile_button, .jodit-uploader, .jodit-upload").forEach(hideJoditUploadControl);
        qsa(panel, "button, a, [role='button']").forEach(btn => {
            const label = String((btn.textContent || "") + " " + (btn.title || "") + " " + (btn.getAttribute("aria-label") || ""));
            if (/Hochladen|Upload/i.test(label)) {
                if (isJoditImageDialogMediaTrigger(panel, btn)) {
                    btn.title = "Bild aus Medienbrowser auswaehlen";
                    return;
                }
                btn.classList.add("dbx-cms-jodit-upload-hidden");
                btn.hidden = true;
            }
        });
        ensureJoditImageDialogMediaButton(panel);
        hideJoditImageDialogPathField(panel);
    }

    function joditImageDialogPathContainer(pathInput) {
        if (!pathInput) return null;
        const dialog = closestElement(pathInput, ".jodit-dialog__panel, .jodit-dialog");
        let node = pathInput.parentElement;
        for (let i = 0; node && node !== dialog && i < 5; i++) {
            const inputs = joditDialogValueInputs(node);
            const text = String(node.textContent || "");
            if (inputs.length === 1 && inputs[0] === pathInput && /Pfad|URL|Src|Quelle|Source/i.test(text)) return node;
            node = node.parentElement;
        }
        return pathInput;
    }

    function joditImagePreviewHost(panel) {
        const img = qsa(panel, "img").find(item => {
            if (item.closest && item.closest(".jodit-dialog__header")) return false;
            return !!item.getClientRects().length;
        });
        if (!img) return null;
        const dialog = closestElement(img, ".jodit-dialog__panel, .jodit-dialog");
        let host = img.parentElement;
        for (let i = 0; host && host !== dialog && i < 4; i++) {
            if (host.children.length <= 4) break;
            host = host.parentElement;
        }
        if (!host || host === dialog) host = img.parentElement;
        if (host) host.classList.add("dbx-cms-jodit-preview-picker");
        return host;
    }

    function joditSingleInputBox(input) {
        if (!input) return null;
        const dialog = closestElement(input, ".jodit-dialog__panel, .jodit-dialog");
        let node = closestElement(input, ".jodit-ui-input, .jodit-form__group, .jodit-ui-block, div") || input.parentElement;
        for (let i = 0; node && node !== dialog && i < 5; i++) {
            if (joditDialogValueInputs(node).length === 1) return node;
            node = node.parentElement;
        }
        return input.parentElement || input;
    }

    function arrangeJoditImageSizeControls(panel) {
        const pathInput = findJoditImageDialogPathInput(panel);
        const inputs = joditDialogValueInputs(panel).filter(input => input !== pathInput);
        const pathIndex = joditDialogValueInputs(panel).indexOf(pathInput);
        const beforePath = pathIndex > 0 ? joditDialogValueInputs(panel).slice(0, pathIndex) : inputs;
        const sizeInputs = beforePath
            .filter(input => input !== pathInput && !input.closest(".dbx-cms-jodit-openwin"))
            .filter(input => /^\s*\d+(\.\d+)?\s*$/.test(String(input.value || "")) || String(input.getAttribute("type") || "").toLowerCase() === "number")
            .slice(0, 2);
        if (sizeInputs.length < 2) return;

        let row = qs(panel, ".dbx-cms-jodit-size-row");
        if (!row) {
            row = document.createElement("div");
            row.className = "dbx-cms-jodit-size-row";
        }

        const firstBox = joditSingleInputBox(sizeInputs[0]);
        const secondBox = joditSingleInputBox(sizeInputs[1]);
        const common = firstBox && secondBox ? (firstBox.parentElement === secondBox.parentElement ? firstBox.parentElement : null) : null;
        const lockButton = common ? qsa(common, "button, .jodit-ui-button, [role='button']").find(btn => {
            const text = String((btn.textContent || "") + " " + (btn.title || "") + " " + (btn.getAttribute("aria-label") || "") + " " + (btn.className || ""));
            return /lock|proportion|ratio|verhaeltnis|verh\u00e4ltnis/i.test(text) || qsa(btn, "svg,.jodit-icon").length;
        }) : null;

        const preview = joditImagePreviewHost(panel);
        if (preview && row.parentElement !== preview.parentElement) {
            preview.insertAdjacentElement("afterend", row);
        } else if (!row.parentElement && firstBox && firstBox.parentElement) {
            firstBox.insertAdjacentElement("beforebegin", row);
        }

        if (firstBox) row.appendChild(firstBox);
        if (lockButton) row.appendChild(lockButton);
        if (secondBox) row.appendChild(secondBox);
    }

    function ensureJoditImageDialogMediaButton(panel) {
        const pathInput = findJoditImageDialogPathInput(panel);
        if (!pathInput || qs(panel, "[data-cms-jodit-media-select]")) return;
        panel.__dbxCmsJoditPathInput = pathInput;
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "btn btn-outline-primary btn-sm dbx-cms-hero-pick dbx-cms-jodit-media-select";
        btn.setAttribute("data-cms-jodit-media-select", "1");
        btn.title = "Bild aus Medienbrowser auswaehlen";
        btn.innerHTML = '<i class="bi bi-image"></i><i class="bi bi-camera-video"></i><i class="bi bi-upload"></i><span>'
            + escapeHtml(cmsText(root, "selection_label", "Auswahl")) + '</span>';
        const preview = joditImagePreviewHost(panel);
        if (preview) {
            preview.insertAdjacentElement("beforebegin", btn);
            return;
        }
        const container = joditImageDialogPathContainer(pathInput);
        if (container && container.parentElement) {
            container.insertAdjacentElement("beforebegin", btn);
            return;
        }
        pathInput.insertAdjacentElement("beforebegin", btn);
    }

    function hideJoditImageDialogPathField(panel) {
        const pathInput = findJoditImageDialogPathInput(panel);
        if (!pathInput) return;
        panel.__dbxCmsJoditPathInput = pathInput;
        const container = joditImageDialogPathContainer(pathInput);
        if (container && container !== pathInput && joditDialogValueInputs(container).length === 1) {
            container.classList.add("dbx-cms-jodit-path-hidden");
            container.hidden = true;
            return;
        }
        pathInput.classList.add("dbx-cms-jodit-path-hidden");
        pathInput.hidden = true;
        pathInput.setAttribute("aria-hidden", "true");
    }

    function findJoditDialogLinkInput(panel) {
        const pathInput = findJoditImageDialogPathInput(panel);
        const inputs = joditDialogValueInputs(panel).filter(input => input !== pathInput);
        return inputs.find(input => /link|href|url/i.test(String(input.name || input.id || input.getAttribute("placeholder") || "")))
            || inputs[inputs.length - 1]
            || null;
    }

    function ensureJoditImageOpenWinControls(panel) {
        if (qs(panel, "[data-cms-jodit-openwin]")) return;
        const linkInput = findJoditDialogLinkInput(panel);
        if (!linkInput) return;
        const host = document.createElement("div");
        host.className = "dbx-cms-jodit-openwin";
        host.setAttribute("data-cms-jodit-openwin", "1");
        host.innerHTML = `
            <label class="dbx-cms-jodit-openwin-field">
                <span>Link oeffnen</span>
                <select class="form-select form-select-sm" data-cms-jodit-link-mode>
                    <option value="">Normal</option>
                    <option value="blank">Neuer Tab</option>
                    <option value="openwin">openWin</option>
                </select>
            </label>
            <div class="dbx-cms-jodit-openwin-options" data-cms-jodit-openwin-options hidden>
                <label><span>Groesse</span><select class="form-select form-select-sm" data-cms-jodit-openwin-size>
                    <option value="900x620">900 x 620</option>
                    <option value="1024x760">1024 x 760</option>
                    <option value="80%x80%">80% x 80%</option>
                    <option value="1320x860">1320 x 860</option>
                </select></label>
                <label><span>Position</span><select class="form-select form-select-sm" data-cms-jodit-openwin-position>
                    <option value="center">Zentriert</option>
                    <option value="center-top">Zentriert oben</option>
                    <option value="cascade">Versetzt</option>
                    <option value="fullscreen">Vollbild</option>
                </select></label>
            </div>`;

        const linkBox = joditSingleInputBox(linkInput);
        if (linkBox && linkBox.parentElement) linkBox.insertAdjacentElement("afterend", host);
        else linkInput.insertAdjacentElement("afterend", host);

        const newTab = qsa(panel, "input[type='checkbox']").find(input => /tab|target|blank|neu/i.test(String(input.name || input.id || input.closest("label")?.textContent || "")));
        const mode = qs(host, "[data-cms-jodit-link-mode]");
        const options = qs(host, "[data-cms-jodit-openwin-options]");
        if (newTab && newTab.checked) mode.value = "blank";
        mode.addEventListener("change", () => {
            if (options) options.hidden = mode.value !== "openwin";
            if (newTab) newTab.checked = mode.value === "blank";
        });
    }

    function readJoditOpenWinSettings(panel) {
        const mode = qs(panel, "[data-cms-jodit-link-mode]");
        const size = qs(panel, "[data-cms-jodit-openwin-size]");
        const position = qs(panel, "[data-cms-jodit-openwin-position]");
        const value = String(mode?.value || "");
        const parts = String(size?.value || "900x620").split("x");
        return {
            mode: value,
            width: parts[0] || "900",
            height: parts[1] || "620",
            position: String(position?.value || "center")
        };
    }

    function findImageByDialogPath(root, path) {
        const surface = editorSurface(root);
        if (!surface) return null;
        const clean = String(path || "").split("#")[0];
        return qsa(surface, "img").find(img => {
            const src = String(img.getAttribute("src") || img.src || "").split("#")[0];
            return src === clean || src.endsWith(clean) || clean.endsWith(src);
        }) || null;
    }

    function applyJoditOpenWinSettings(root, panel, settings) {
        if (!settings || !settings.mode) return;
        const linkInput = findJoditDialogLinkInput(panel);
        const href = String(linkInput?.value || "").trim();
        if (!href) return;
        const pathInput = findJoditImageDialogPathInput(panel);
        const img = findImageByDialogPath(root, pathInput ? pathInput.value : "");
        const link = img ? closestElement(img, "a") : null;
        if (!link) return;
        if (settings.mode === "blank") {
            link.setAttribute("target", "_blank");
            link.setAttribute("rel", "noopener");
            link.classList.remove("dbx-win");
            link.removeAttribute("data-dbx");
            link.removeAttribute("data-url");
            return;
        }
        if (settings.mode === "openwin") {
            const title = img.getAttribute("alt") || img.getAttribute("title") || "Information";
            const dbxData = "lib=openWin|url=" + href + "|title=" + title + "|width=" + settings.width + "|height=" + settings.height + "|position=" + settings.position + "|reload=1|minimizable=1|maximizable=1";
            link.classList.add("dbx-win");
            link.removeAttribute("target");
            link.setAttribute("href", href);
            link.setAttribute("data-url", href);
            link.setAttribute("data-title", title);
            link.setAttribute("data-width", settings.width);
            link.setAttribute("data-height", settings.height);
            link.setAttribute("data-position", settings.position);
            link.setAttribute("data-dbx", dbxData);
            syncEditorDom(root);
            markDirty(root);
        }
    }

    function isJoditImageDialogMediaTrigger(panel, target) {
        const trigger = closestElement(target, "button, a, [role='button'], .jodit-ui-button, .jodit-button, .jodit-input__icon, .jodit-icon");
        if (!panel || !trigger || !panel.contains(trigger)) return false;
        if (trigger.hasAttribute("data-cms-jodit-media-select")) return true;
        if (closestElement(trigger, ".jodit-dialog__header, .jodit-dialog__footer")) return false;
        const label = String((trigger.textContent || "") + " " + (trigger.title || "") + " " + (trigger.getAttribute("aria-label") || ""));
        if (/Abbrechen|Anwenden|Loeschen|L\u00f6schen|Schliessen|Schlie\u00dfen|Fortgeschritten/i.test(label)) return false;

        const pathInput = findJoditImageDialogPathInput(panel);
        if (!pathInput) return false;
        const inputRect = pathInput.getBoundingClientRect();
        const triggerRect = trigger.getBoundingClientRect();
        if (!inputRect.width || !triggerRect.width) return false;
        return triggerRect.left >= inputRect.right - 12
            && triggerRect.top <= inputRect.bottom + 8
            && triggerRect.bottom >= inputRect.top - 8;
    }

    function setJoditDialogFieldValue(field, value, overwrite) {
        if (!field || value == null) return;
        if (!overwrite && String(field.value || "").trim()) return;
        field.value = String(value || "");
        field.dispatchEvent(new Event("input", { bubbles: true }));
        field.dispatchEvent(new Event("change", { bubbles: true }));
    }

    function applyMediaToJoditImageDialog(root, panel, row) {
        if (!row || !row.url) return false;
        if (!isImageRow(row)) {
            status(root, "Bitte ein Bild auswaehlen.", "error");
            return false;
        }
        const inputs = visibleJoditDialogInputs(panel);
        const pathInput = findJoditImageDialogPathInput(panel);
        setJoditDialogFieldValue(pathInput, row.url, true);

        const afterPath = inputs.slice(Math.max(0, inputs.indexOf(pathInput) + 1))
            .filter(input => String(input.getAttribute("type") || "text").toLowerCase() !== "number");
        const titleInput = afterPath.find(input => /title|titel/i.test(String(input.name || input.id || input.getAttribute("placeholder") || ""))) || afterPath[0] || null;
        const altInput = afterPath.find(input => /alt|alternative/i.test(String(input.name || input.id || input.getAttribute("placeholder") || ""))) || afterPath.find(input => input !== titleInput) || null;
        const label = row.title || row.alt || row.file_name || "";
        setJoditDialogFieldValue(titleInput, row.title || label, false);
        setJoditDialogFieldValue(altInput, row.alt || label, false);
        qsa(panel, "img").forEach(img => {
            if (img.closest && img.closest(".jodit-dialog__header")) return;
            img.src = row.thumb_url || row.url;
            img.alt = row.alt || label;
        });
        status(root, "Bild aus Medienbrowser uebernommen.", "success");
        return true;
    }

    function openJoditImageDialogMediaBrowser(root, cfg, panel) {
        compactJoditImageDialog(panel);
        openMediaBrowser(root, cfg || {}, {
            mode: "pick",
            slot: "inline",
            singlePick: true,
            afterAssign(row) {
                return applyMediaToJoditImageDialog(root, panel, row);
            }
        });
    }

    function openEditorMediaBrowser(root, cfg) {
        openMediaBrowser(root, cfg || {}, {
            mode: "pick",
            slot: "inline",
            singlePick: true,
            afterAssign(row) {
                return assignMedia(root, cfg || {}, row, "inline").then(assignedRow => {
                    if (!assignedRow) return false;
                    insertMediaRow(root, assignedRow);
                    setLocalMediaSlot(root, assignedRow.id, "inline");
                    return true;
                });
            }
        });
    }

    function bindJoditImageDialogMediaPicker(root, cfg) {
        if (root.__dbxCmsJoditImagePickerBound) return;
        root.__dbxCmsJoditImagePickerBound = true;

        document.addEventListener("click", e => {
            const panel = joditImageDialogPanel(e.target);
            if (!panel) return;
            compactJoditImageDialog(panel);
            if (!isJoditImageDialogMediaTrigger(panel, e.target)) return;
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            openJoditImageDialogMediaBrowser(root, cfg || {}, panel);
        }, true);

        document.addEventListener("focusin", e => {
            const panel = joditImageDialogPanel(e.target);
            if (panel) compactJoditImageDialog(panel);
        }, true);

        // Kein MutationObserver hier: Jodit baut den Dialog dynamisch um und Firefox
        // kann sonst bei DOM-Korrekturen in eine dauernde Mutation-Schleife laufen.
    }

    function initEditor(root, cfg) {
        const editor = qs(root, "[data-cms-editor]");
        if (!editor || editor.__dbxJoditReady) return;

        if (!window.Jodit || !window.Jodit.make) {
            dbx.warn("[cms] Jodit not loaded, fallback contenteditable active");
            return;
        }

        editor.__dbxJoditReady = true;
        bindModPlaceholderEvents(root);
        const cmsMarkers = [
            { label: "Hero-Text", marker: "dbx:hero" },
            { label: "Header", marker: "dbx:header" },
            { label: "Footer", marker: "dbx:footer" },
            { label: "col-2 Trenner", marker: "dbx:col2" },
            { label: "col-3a Trenner", marker: "dbx:col3a" },
            { label: "col-3b Trenner", marker: "dbx:col3b" },
            { label: cmsText(root, "editor_print_break", "Druck-Seitenumbruch"), marker: "dbx:pagebreak" }
        ];

        window.Jodit.defaultOptions.controls.dbxMarkerMenu = {
            tooltip: cmsText(root, "editor_marker_tooltip", "Trenner für Content-Bereiche einfügen"),
            icon: cmsMarkerMenuIcon(),
            text: "",
            popup: function (jodit, current, control, close) {
                const box = document.createElement("div");
                box.className = "dbx-cms-marker-menu";
                cmsMarkers.forEach(item => {
                    const btn = document.createElement("button");
                    btn.type = "button";
                    btn.className = "dbx-cms-marker-menu-item";
                    btn.textContent = item.label;
                    btn.addEventListener("mousedown", e => {
                        e.preventDefault();
                    });
                    btn.addEventListener("click", e => {
                        e.preventDefault();
                        insertMarker(root, item.marker, item.label);
                        if (typeof close === "function") close();
                    });
                    box.appendChild(btn);
                });
                return box;
            },
            exec: function (jodit) {
                if (jodit && jodit.e && jodit.e.fire) jodit.e.fire("togglePopup", "dbxMarkerMenu");
            }
        };
        window.Jodit.defaultOptions.controls.dbxImageBrowser = {
            tooltip: cmsText(root, "editor_media_tooltip", "Medium aus dem Medienbrowser einfügen"),
            icon: "image",
            exec: function () {
                openEditorMediaBrowser(root, cfg || {});
            }
        };
        window.Jodit.defaultOptions.controls.dbxModBrowser = {
            tooltip: cmsText(root, "editor_module_tooltip", "Modulaufruf einfügen"),
            icon: cmsModBrowserIcon(),
            text: "",
            exec: function () {
                saveEditorSelection(root);
                openModBrowser(root, cfg || {});
            }
        };
        window.Jodit.defaultOptions.controls.dbxBootstrapComponents = {
            tooltip: cmsText(root, "editor_bootstrap_tooltip", "Bootstrap-Content-Komponente einfügen"),
            icon: cmsBootstrapComponentIcon(),
            text: "",
            popup: function (jodit, current, control, close) {
                const box = document.createElement("div");
                box.className = "dbx-cms-marker-menu";
                bootstrapComponentItems().forEach(item => {
                    const btn = document.createElement("button");
                    btn.type = "button";
                    btn.className = "dbx-cms-marker-menu-item";
                    btn.textContent = item.label;
                    btn.addEventListener("mousedown", e => e.preventDefault());
                    btn.addEventListener("click", e => {
                        e.preventDefault();
                        insertBootstrapComponent(root, item.html);
                        if (typeof close === "function") close();
                    });
                    box.appendChild(btn);
                });
                return box;
            },
            exec: function (jodit) {
                if (jodit && jodit.e && jodit.e.fire) jodit.e.fire("togglePopup", "dbxBootstrapComponents");
            }
        };
        window.Jodit.defaultOptions.controls.dbxTextStyle = {
            tooltip: cmsText(root, "editor_text_format", "Textformatierung"),
            icon: cmsTextStyleIcon(),
            text: "",
            popup: function (jodit, current, control, close) {
                const box = document.createElement("div");
                box.className = "dbx-cms-marker-menu dbx-cms-text-style-menu";
                const styles = [
                    { label: cmsText(root, "editor_bold", "Fett"), mark: "B", command: "bold", className: "is-bold" },
                    { label: cmsText(root, "editor_italic", "Kursiv"), mark: "I", command: "italic", className: "is-italic" },
                    { label: cmsText(root, "editor_underline", "Unterstrichen"), mark: "U", command: "underline", className: "is-underlined" },
                    { label: cmsText(root, "editor_strike", "Durchgestrichen"), mark: "S", command: "strikethrough", className: "is-struck" }
                ];
                const alignments = [
                    { label: cmsText(root, "editor_align_left", "Linksbündig"), icon: "bi-text-left", command: "justifyLeft" },
                    { label: cmsText(root, "editor_align_center", "Zentriert"), icon: "bi-text-center", command: "justifyCenter" },
                    { label: cmsText(root, "editor_align_right", "Rechtsbündig"), icon: "bi-text-right", command: "justifyRight" },
                    { label: cmsText(root, "editor_align_justify", "Blocksatz"), icon: "bi-justify", command: "justifyFull" }
                ];
                const appendCommand = item => {
                    const btn = document.createElement("button");
                    btn.type = "button";
                    btn.className = "dbx-cms-marker-menu-item dbx-cms-text-style-menu-item";
                    const mark = item.icon
                        ? '<i class="bi ' + item.icon + ' dbx-cms-text-style-menu-icon" aria-hidden="true"></i>'
                        : '<span class="dbx-cms-text-style-menu-mark ' + item.className + '">' + item.mark + '</span>';
                    btn.innerHTML = mark + '<span>' + item.label + '</span>';
                    btn.addEventListener("mousedown", e => e.preventDefault());
                    btn.addEventListener("click", e => {
                        e.preventDefault();
                        execEditorCommand(root, item.command);
                        if (typeof close === "function") close();
                    });
                    box.appendChild(btn);
                };
                styles.forEach(appendCommand);
                const divider = document.createElement("span");
                divider.className = "dbx-cms-text-style-menu-divider";
                divider.setAttribute("aria-hidden", "true");
                box.appendChild(divider);
                alignments.forEach(appendCommand);
                return box;
            },
            exec: function (jodit) {
                if (jodit && jodit.e && jodit.e.fire) jodit.e.fire("togglePopup", "dbxTextStyle");
            }
        };
        window.Jodit.defaultOptions.controls.dbxHr = {
            tooltip: cmsText(root, "editor_horizontal_rule", "Horizontale Linie an der Cursorposition einfügen"),
            icon: cmsHrIcon(),
            text: "",
            exec: function () {
                insertEditorPlainHr(root);
            }
        };
        window.Jodit.defaultOptions.controls.dbxSave = {
            tooltip: cmsText(root, "editor_save_all", "Alles speichern"),
            icon: cmsSaveIcon(),
            text: "",
            exec: function () {
                saveCurrentCms(root, cfg || {});
            }
        };

        bindJoditImageDialogMediaPicker(root, cfg || {});

        root.__dbxCmsJodit = window.Jodit.make(editor, {
            language: cmsLanguage(root),
            popupRoot: document.body,
            height: 430,
            minHeight: 320,
            toolbarSticky: false,
            autofocus: false,
            enter: "p",
            defaultMode: "1",
            askBeforePasteHTML: false,
            askBeforePasteFromWord: false,
            cleanHTML: {
                removeEmptyElements: false
            },
            toolbarAdaptive: false,
            showCharsCounter: false,
            showWordsCounter: false,
            showXPathInStatusbar: false,
            imageDefaultWidth: 720,
            uploader: false,
            buttons: [
                "source", "dbxSave", "|",
                "dbxTextStyle", "|",
                "ul", "ol", "outdent", "indent", "|",
                "paragraph", "brush", "|",
                "link", "dbxImageBrowser", "dbxModBrowser", "dbxBootstrapComponents", "video", "dbxHr", "|",
                "dbxMarkerMenu", "|",
                "undo", "redo", "|",
                "fullsize"
            ],
            events: {
                change: function () {
                    normalizeEditorMarkers(root);
                    const surface = editorSurface(root);
                    normalizeBootstrapComponents(surface);
                    normalizeInlineMediaLayout(surface);
                    normalizeModPlaceholders(surface);
                    const html = surface ? (surface.innerHTML || "") : (this.value || "");
                    if (html !== (this.value || "")) this.value = html;
                    setField(root, "content", html);
                    markDirty(root);
                    scheduleEditorHeight(root);
                },
                focus: function () {
                    window.requestAnimationFrame(() => saveEditorSelection(root));
                },
                afterSelectionChange: function () {
                    window.requestAnimationFrame(() => saveEditorSelection(root));
                }
            }
        });
        bindEditorSlashCommands(root, cfg || {});
        bindBootstrapCardEditingGuards(root);
        bindEditorHeight(root);
        window.setTimeout(() => {
            normalizeEditorMarkers(root);
            const surface = editorSurface(root);
            normalizeBootstrapComponents(surface);
            bindBootstrapCardEditingGuards(root);
            bindEditorSlashCommands(root, cfg || {});
            normalizeModPlaceholders(surface);
            bindEditorMarkerEventsRetry(root);
            bindEditorSaveButton(root, cfg || {}, 0);
            scheduleEditorHeight(root);
        }, 0);
    }

    function bindEditorSaveButton(root, cfg, attempt) {
        attempt = Number(attempt || 0);
        const instance = getEditorInstance(root);
        const container = instance && instance.container ? instance.container : root;
        let buttons = qsa(container, ".jodit-toolbar-button");
        if (!buttons.length) buttons = qsa(root, ".jodit-toolbar-button");
        const saveButton = buttons.find(btn => /dbxsave/i.test(btn.className || "")) || buttons[1] || null;
        const button = saveButton ? qs(saveButton, "button") : null;
        if (!button) {
            if (attempt < 20) window.setTimeout(() => bindEditorSaveButton(root, cfg || {}, attempt + 1), 100);
            return;
        }
        if (button.__dbxCmsSaveBound) return;
        button.__dbxCmsSaveBound = true;
        button.setAttribute("title", cmsText(root, "editor_save_all", "Alles speichern"));
        button.setAttribute("aria-label", cmsText(root, "editor_save_all", "Alles speichern"));
        button.addEventListener("click", event => {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === "function") event.stopImmediatePropagation();
            saveCurrentCms(root, cfg || {});
        }, true);
    }

    function setEditorHtml(root, html) {
        html = repairInlineVideoHtml(root, html);
        const instance = getEditorInstance(root);
        const editor = qs(root, "[data-cms-editor]");
        if (instance) instance.value = html || "";
        else if (editor) editor.innerHTML = html || "";
        setField(root, "content", html || "");
        bindEditorHeight(root);
        scheduleEditorHeight(root);
        window.setTimeout(() => {
            normalizeEditorMarkers(root);
            const surface = editorSurface(root);
            normalizeBootstrapComponents(surface);
            repairInlineVideoPlayers(root, surface);
            normalizeInlineMediaLayout(surface);
            normalizeModPlaceholders(surface);
            if (surface) cleanEditorRuntimeNodes(surface);
            const repairedHtml = surface ? surface.innerHTML : getEditorHtml(root);
            const current = getEditorInstance(root);
            if (current) current.value = repairedHtml || "";
            if (/(<video\b|<iframe\b|dbx-cms-inline-video-block)/i.test(html) && !/(<video\b|<iframe\b|dbx-cms-inline-video-block)/i.test(repairedHtml || "")) {
                setField(root, "content", html || "");
            } else {
                setField(root, "content", repairedHtml || "");
            }
            bindEditorMarkerEventsRetry(root);
            scheduleEditorHeight(root);
        }, 0);
    }

    function getEditorHtml(root) {
        const instance = getEditorInstance(root);
        const surface = editorSurface(root);
        if (surface) {
            normalizeBootstrapComponents(surface);
            normalizeInlineMediaLayout(surface);
            cleanEditorRuntimeNodes(surface);
            const html = surface.innerHTML || "";
            if (instance) instance.value = html;
            setField(root, "content", html);
            return html;
        }
        if (instance) return instance.value || "";
        const editor = qs(root, "[data-cms-editor]");
        return editor ? editor.innerHTML : getField(root, "content");
    }

    function editorSurface(root) {
        const instance = getEditorInstance(root);
        if (instance && instance.editor) return instance.editor;
        return qs(root, ".jodit-wysiwyg") || qs(root, "[data-cms-editor]");
    }

    function nodeElement(node) {
        if (!node) return null;
        return node.nodeType === 1 ? node : node.parentElement;
    }

    function rangeInsideSurface(surface, range) {
        if (!surface || !range) return false;
        const start = nodeElement(range.startContainer);
        const end = nodeElement(range.endContainer);
        return !!(start && end && surface.contains(start) && surface.contains(end));
    }

    function nodeHasEditorContent(node) {
        if (!node) return false;
        if (node.nodeType === 3) {
            return String(node.nodeValue || "").replace(/\uFEFF/g, "").replace(/\u00a0/g, " ").trim() !== "";
        }
        if (node.nodeType !== 1 && node.nodeType !== 11) return false;
        if (node.nodeType === 1) {
            const tag = node.tagName || "";
            if (tag === "BR") return false;
            if (/^(IMG|VIDEO|AUDIO|IFRAME|OBJECT|EMBED|TABLE|HR)$/i.test(tag)) return true;
        }
        return Array.from(node.childNodes || []).some(nodeHasEditorContent);
    }

    function setEditorCaretAfterNode(root, node) {
        const surface = editorSurface(root);
        if (!surface || !node) return false;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel) return false;
        const range = doc.createRange();
        const next = node.nextSibling;
        if (next && next.nodeType === 1 && /^(P|DIV|H1|H2|H3|H4|H5|H6|BLOCKQUOTE|LI)$/i.test(next.tagName || "")) {
            range.setStart(next, 0);
        } else {
            range.setStartAfter(node);
        }
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
        state(root).editorRange = range.cloneRange();
        return true;
    }

    function cleanEditorRuntimeNodes(container) {
        normalizeCommentMarkers(container);
        syncInlineVideoBlockSizes(container);
        qsa(container, "[data-jodit-temp], [data-jodit-selection_marker], span[id^='jodit-selection_marker_']").forEach(el => el.remove());
        qsa(container, "[data-cms-inline-video-options-open]").forEach(el => el.remove());
        qsa(container, ".dbx-cms-inline-media video, .dbx-cms-inline-media iframe, .dbx-cms-inline-media source").forEach(media => {
            const src = String(media.getAttribute("src") || "");
            const poster = String(media.getAttribute("poster") || "");
            if (/^data:/i.test(src) || /^blob:/i.test(src)) media.removeAttribute("src");
            if (/^data:/i.test(poster) || /^blob:/i.test(poster)) media.removeAttribute("poster");
            if (!media.getAttribute("data-cms-media-slot")) media.setAttribute("data-cms-media-slot", "inline");
            if (/^video$/i.test(media.tagName || "")) {
                media.setAttribute("controls", "controls");
                media.setAttribute("preload", "none");
                media.setAttribute("playsinline", "playsinline");
            }
        });
        qsa(container, ".dbx-cms-inline-media, figure.dbx-cms-inline-video-block").forEach(wrapper => {
            if (!wrapper.getAttribute("data-cms-media-slot")) wrapper.setAttribute("data-cms-media-slot", "inline");
            if (wrapper.classList.contains("dbx-cms-inline-video-block")) wrapper.removeAttribute("contenteditable");
            if (wrapper.getAttribute("data-cms-media-id") && !inlineMediaWrapperHasContent(wrapper)) wrapper.remove();
            const inlineImage = qs(wrapper, "img");
            if (inlineImage && !wrapper.classList.contains("dbx-cms-inline-video-block")) {
                const srcMatch = String(inlineImage.getAttribute("src") || "").match(/dbx_mid=([0-9]+)/i);
                if (srcMatch && Number(srcMatch[1] || 0) > 0) {
                    inlineImage.setAttribute("data-cms-media-id", String(Number(srcMatch[1])));
                }
                wrapper.removeAttribute("data-cms-media-id");
            }
        });
        removeEmptyEditorParagraphs(container);
        normalizeInlineMissingMedia(container);
    }

    function removeEmptyEditorParagraphs(container) {
        if (!container) return;
        qsa(container, "p").forEach(paragraph => {
            if (paragraph.matches(".dbx-cms-inline-media, .dbx-cms-mod-placeholder, [data-cms-media-id], [data-dbx-marker]")) return;
            if (qs(paragraph, "img,video,audio,iframe,object,embed,table,hr,svg,canvas,input,textarea,select,button,[data-cms-media-id],[data-dbx-marker]")) return;

            const text = String(paragraph.textContent || "")
                .replace(/[\s\u00a0\u200b\ufeff]+/g, "");
            if (text === "") paragraph.remove();
        });
    }

    function inlineMediaWrapperHasContent(wrap) {
        if (!wrap) return false;
        return !!qs(wrap, "img, video, iframe, source, .dbx-cms-inline-video-thumb, .dbx-cms-inline-video-empty, .dbx-cms-inline-media-missing");
    }

    function inlineMissingMediaWrap(doc, id, label) {
        const p = doc.createElement("p");
        p.className = "dbx-cms-inline-media dbx-cms-inline-media-missing-wrap";
        if (id > 0) p.setAttribute("data-cms-media-id", String(id));
        p.setAttribute("data-cms-media-slot", "inline");
        p.setAttribute("contenteditable", "false");
        p.setAttribute("tabindex", "0");
        p.setAttribute("title", "Fehlende Mediendatei auswaehlen, Entf zum Loeschen");
        const span = doc.createElement("span");
        span.className = "dbx-cms-inline-media-missing";
        span.setAttribute("aria-hidden", "true");
        span.textContent = label || ("Mediendatei #" + id + " nicht verfuegbar");
        p.appendChild(span);
        return p;
    }

    function normalizeInlineMissingMedia(container) {
        if (!container) return;
        const doc = container.ownerDocument || document;
        qsa(container, ".dbx-cms-inline-media-missing-wrap").forEach(wrap => {
            wrap.setAttribute("contenteditable", "false");
            wrap.classList.add("dbx-cms-inline-media");
            wrap.classList.remove("dbx-cms-mod-placeholder");
            const id = Number(wrap.getAttribute("data-cms-media-id") || 0);
            if (id > 0 && !wrap.getAttribute("data-cms-media-slot")) wrap.setAttribute("data-cms-media-slot", "inline");
        });
        qsa(container, ".dbx-cms-inline-media-missing").forEach(span => {
            if (closestElement(span, ".dbx-cms-inline-media-missing-wrap")) return;
            const id = Number(span.getAttribute("data-cms-media-id") || span.parentElement?.getAttribute("data-cms-media-id") || 0);
            const label = String(span.textContent || "").trim() || ("Mediendatei #" + id + " nicht verfuegbar");
            const host = span.parentElement;
            const wrap = inlineMissingMediaWrap(doc, id, label);
            if (host && (host.classList.contains("dbx-cms-mod-placeholder") || host.classList.contains("dbx-cms-inline-media"))) {
                host.replaceWith(wrap);
            } else {
                span.replaceWith(wrap);
            }
        });
    }

    function inlineVideoMediaSize(media) {
        if (!media) return { width: "", height: "" };
        let width = media.style && media.style.width ? media.style.width : "";
        let height = media.style && media.style.height ? media.style.height : "";
        if (!width) width = media.getAttribute("width") || media.getAttribute("data-cms-video-width") || "";
        if (!height) height = media.getAttribute("height") || media.getAttribute("data-cms-video-height") || "";
        return {
            width: cssSizeValue(width),
            height: cssSizeValue(height)
        };
    }

    function persistInlineVideoRenderedSize(wrapper) {
        if (!wrapper || !wrapper.getBoundingClientRect) return false;
        const rect = wrapper.getBoundingClientRect();
        if (!rect || rect.width < 32 || rect.height < 24) return false;
        const width = Math.round(rect.width) + "px";
        const height = Math.round(rect.height) + "px";
        const beforeWidth = cssSizeValue(wrapper.getAttribute("data-cms-video-width") || wrapper.style.width || "");
        const beforeHeight = cssSizeValue(wrapper.getAttribute("data-cms-video-height") || wrapper.style.height || "");
        const changed = beforeWidth !== width || beforeHeight !== height;
        wrapper.style.width = width;
        wrapper.style.height = height;
        wrapper.setAttribute("data-cms-video-width", width);
        wrapper.setAttribute("data-cms-video-height", height);
        const options = inlineVideoOptionsFromElement(wrapper);
        options.width = width;
        options.height = height;
        syncInlineVideoOptionsToMedia(wrapper, options);
        return changed;
    }

    function beginInlineVideoResizeTrack(root, wrapper) {
        if (!root || !wrapper) return;
        const rect = wrapper.getBoundingClientRect ? wrapper.getBoundingClientRect() : null;
        state(root).inlineVideoResizeTrack = {
            wrapper,
            width: rect ? Math.round(rect.width) : 0,
            height: rect ? Math.round(rect.height) : 0
        };
    }

    function finishInlineVideoResizeTrack(root) {
        const s = state(root);
        const track = s.inlineVideoResizeTrack;
        s.inlineVideoResizeTrack = null;
        if (!track || !track.wrapper || !track.wrapper.isConnected) return false;
        const rect = track.wrapper.getBoundingClientRect ? track.wrapper.getBoundingClientRect() : null;
        if (!rect) return false;
        const width = Math.round(rect.width);
        const height = Math.round(rect.height);
        if (Math.abs(width - track.width) < 2 && Math.abs(height - track.height) < 2) return false;
        if (!persistInlineVideoRenderedSize(track.wrapper)) return false;
        syncEditorDom(root);
        return true;
    }

    function syncInlineVideoBlockSizes(container) {
        if (!container) return;
        qsa(container, ".dbx-cms-inline-video-block").forEach(wrapper => {
            if (!qs(wrapper, "[data-cms-inline-video-options-open]")) {
                wrapper.insertAdjacentHTML("beforeend", inlineVideoOptionsButtonHtml());
            }
            const media = qs(wrapper, ".dbx-cms-inline-video-thumb, img, video, iframe");
            const size = inlineVideoMediaSize(media);
            let width = size.width || cssSizeValue(wrapper.style.width || wrapper.getAttribute("data-cms-video-width") || "");
            let height = size.height || cssSizeValue(wrapper.style.height || wrapper.getAttribute("data-cms-video-height") || "");
            const rect = wrapper.getBoundingClientRect ? wrapper.getBoundingClientRect() : null;
            const defaultWidth = Math.min((container.getBoundingClientRect ? container.getBoundingClientRect().width : 720) || 720, 720);
            if (!width && rect && rect.width > 0 && Math.abs(rect.width - defaultWidth) > 2) width = Math.round(rect.width) + "px";
            if (!height && rect && rect.height > 0) {
                const ratioHeight = rect.width > 0 ? rect.width * 9 / 16 : 0;
                if (!ratioHeight || Math.abs(rect.height - ratioHeight) > 2) height = Math.round(rect.height) + "px";
            }
            if (width) {
                wrapper.style.width = width;
                wrapper.setAttribute("data-cms-video-width", width);
            }
            if (height && height !== "auto") {
                wrapper.style.height = height;
                wrapper.setAttribute("data-cms-video-height", height);
            }
            if (media) {
                media.removeAttribute("width");
                media.removeAttribute("height");
                if (media.style) {
                    media.style.width = "";
                    media.style.height = "";
                }
            }
        });
    }

    function plainMarkerName(text) {
        const match = String(text || "").trim().match(/^dbx:(split|col2|col3a|col3b|header|teaser|footer|pagebreak)$/i);
        if (!match) return "";
        return match[1].toLowerCase() === "split" ? "col2" : match[1].toLowerCase();
    }

    function normalizePlainTextMarkers(container) {
        if (!container) return;
        normalizeCommentMarkers(container);
        const doc = container.ownerDocument || document;
        qsa(container, "p,div").forEach(el => {
            if (el.querySelector(".dbx-cms-marker,[data-dbx-marker],img,video,iframe,table,hr,ul,ol")) return;
            const name = plainMarkerName(el.textContent);
            if (!name) return;
            const hr = cmsMarkerElement("dbx:" + name, null, doc);
            if (hr) el.replaceWith(hr);
        });
        Array.from(container.childNodes || []).forEach(node => {
            if (node.nodeType !== 3) return;
            const name = plainMarkerName(node.nodeValue);
            if (!name) return;
            const hr = cmsMarkerElement("dbx:" + name, null, doc);
            if (hr) node.replaceWith(hr);
        });
    }

    function normalizeInlineMediaLayout(container) {
        if (!container) return;
        qsa(container, ".dbx-cms-inline-media img, .dbx-cms-inline-media video, .dbx-cms-inline-media iframe").forEach(media => {
            const wrapper = closestElement(media, ".dbx-cms-inline-media");
            if (!wrapper) return;
            const floatValue = String(media.style.float || "").toLowerCase();
            if (floatValue !== "left" && floatValue !== "right") return;

            wrapper.style.float = floatValue;
            wrapper.style.marginLeft = media.style.marginLeft || "";
            wrapper.style.marginRight = media.style.marginRight || "";
            media.style.float = "";
            media.style.marginLeft = "";
            media.style.marginRight = "";
            media.style.display = "";
        });
    }

    function topEditorChild(surface, range) {
        if (!surface || !range) return null;
        if (range.startContainer === surface) {
            const child = surface.childNodes[Math.min(range.startOffset, Math.max(surface.childNodes.length - 1, 0))];
            return child && child.nodeType === 1 ? child : null;
        }
        let el = nodeElement(range.startContainer);
        if (!el || el === surface || !surface.contains(el)) return null;
        while (el.parentElement && el.parentElement !== surface) el = el.parentElement;
        return el.parentElement === surface ? el : null;
    }

    function canSplitForMarker(block) {
        if (!block || block.nodeType !== 1) return false;
        return /^(P|DIV|H1|H2|H3|H4|H5|H6|BLOCKQUOTE|UL|OL|LI)$/i.test(block.tagName || "")
            && !/^(TABLE|FIGURE)$/i.test(block.tagName || "");
    }

    function insertFragmentAfter(parent, fragment, afterNode) {
        let last = afterNode;
        Array.from(fragment.childNodes || []).forEach(node => {
            parent.insertBefore(node, last ? last.nextSibling : null);
            last = node;
        });
        return last;
    }

    function insertEditorHrNode(root, hrNode) {
        const instance = getEditorInstance(root);
        if (instance && typeof instance.setMode === "function") {
            instance.setMode(window.Jodit && window.Jodit.MODE_WYSIWYG ? window.Jodit.MODE_WYSIWYG : 1);
        }

        restoreEditorSelection(root);

        const surface = editorSurface(root);
        if (!surface) return false;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        let range = sel && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : state(root).editorRange;
        if (!hrNode) return false;

        if (!rangeInsideSurface(surface, range)) {
            surface.appendChild(hrNode);
            setEditorCaretAfterNode(root, hrNode);
            normalizeEditorMarkers(root);
            bindEditorMarkerEventsRetry(root);
            syncEditorDom(root);
            return true;
        }

        if (!range.collapsed) {
            range.deleteContents();
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }

        const block = topEditorChild(surface, range);
        if (canSplitForMarker(block) && block.contains(nodeElement(range.startContainer))) {
            const parent = block.parentElement;
            const suffixRange = range.cloneRange();
            suffixRange.setEndAfter(block);
            const suffix = suffixRange.extractContents();
            const blockHasContent = nodeHasEditorContent(block);

            if (blockHasContent) {
                parent.insertBefore(hrNode, block.nextSibling);
            } else {
                parent.insertBefore(hrNode, block);
                block.remove();
            }

            if (nodeHasEditorContent(suffix)) {
                insertFragmentAfter(parent, suffix, hrNode);
            }
        } else if (range.startContainer === surface) {
            surface.insertBefore(hrNode, surface.childNodes[range.startOffset] || null);
        } else if (block && block.parentElement === surface) {
            surface.insertBefore(hrNode, block.nextSibling);
        } else {
            range.insertNode(hrNode);
        }

        normalizeEditorMarkers(root);
        bindEditorMarkerEventsRetry(root);
        syncEditorDom(root);
        setEditorCaretAfterNode(root, hrNode);
        return true;
    }

    function insertEditorPlainHr(root) {
        const surface = editorSurface(root);
        const doc = surface ? (surface.ownerDocument || document) : document;
        return insertEditorHrNode(root, doc.createElement("hr"));
    }

    function insertEditorMarkerElement(root, marker, label) {
        const surface = editorSurface(root);
        const doc = surface ? (surface.ownerDocument || document) : document;
        return insertEditorHrNode(root, cmsMarkerElement(marker, label, doc));
    }

    function saveEditorSelection(root) {
        const surface = editorSurface(root);
        if (!surface) return false;
        if (state(root).selectedMarker || state(root).selectedMissingMedia) {
            hideEditorCaretHint(root);
            return false;
        }
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel || !sel.rangeCount) {
            hideEditorCaretHint(root);
            return false;
        }
        const range = sel.getRangeAt(0);
        const common = range.commonAncestorContainer;
        const commonEl = nodeElement(common);
        if (common !== surface && (!commonEl || !surface.contains(commonEl))) {
            hideEditorCaretHint(root);
            return false;
        }
        state(root).editorRange = range.cloneRange();
        refreshEditorCaretHint(root);
        return true;
    }

    function refreshEditorCaretHint(root) {
        const surface = editorSurface(root);
        if (!surface || state(root).selectedMarker || state(root).selectedMissingMedia) {
            hideEditorCaretHint(root);
            return;
        }
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel || !sel.rangeCount) {
            hideEditorCaretHint(root);
            return;
        }
        const range = sel.getRangeAt(0);
        if (!range.collapsed) {
            hideEditorCaretHint(root);
            return;
        }
        showEditorCaretHint(root, range);
    }

    function hideEditorCaretHint(root) {
        const s = state(root);
        if (s.editorCaretHintTimer) {
            window.clearTimeout(s.editorCaretHintTimer);
            s.editorCaretHintTimer = null;
        }
        const hint = qs(root, "[data-cms-editor-caret-hint]");
        if (hint) hint.hidden = true;
    }

    function editorCaretBlock(surface, range) {
        if (!surface || !range) return null;
        let el = nodeElement(range.startContainer);
        if (!el || el === surface) {
            const child = surface.childNodes[Math.max(0, Math.min(range.startOffset || 0, surface.childNodes.length - 1))];
            el = nodeElement(child) || surface;
        }
        while (el && el !== surface) {
            if (/^(P|DIV|H1|H2|H3|H4|H5|H6|BLOCKQUOTE|LI|TD|TH)$/i.test(el.tagName || "")) return el;
            el = el.parentElement;
        }
        return null;
    }

    function editorCaretBlockRect(surface, range) {
        const block = editorCaretBlock(surface, range);
        if (!block || !block.getBoundingClientRect) return null;
        const blockRect = block.getBoundingClientRect();
        if (!blockRect || !blockRect.width || !blockRect.height) return null;
        const style = window.getComputedStyle ? window.getComputedStyle(block) : null;
        const paddingLeft = Number.parseFloat(style?.paddingLeft || "0") || 0;
        const paddingTop = Number.parseFloat(style?.paddingTop || "0") || 0;
        let lineHeight = Number.parseFloat(style?.lineHeight || "0") || 0;
        if (!lineHeight) {
            const fontSize = Number.parseFloat(style?.fontSize || "16") || 16;
            lineHeight = fontSize * 1.55;
        }
        return {
            left: blockRect.left + paddingLeft,
            top: blockRect.top + paddingTop,
            height: Math.max(18, Math.min(blockRect.height, lineHeight || 24)),
            blockRect
        };
    }

    function caretRectLooksValid(rect, surface, range) {
        if (!rect || !rect.height) return false;
        const surfaceRect = surface.getBoundingClientRect ? surface.getBoundingClientRect() : null;
        if (surfaceRect && (rect.top < surfaceRect.top - 4 || rect.top > surfaceRect.bottom + 4)) return false;
        const block = editorCaretBlock(surface, range);
        if (!block || !block.getBoundingClientRect) return true;
        const blockRect = block.getBoundingClientRect();
        return rect.top >= blockRect.top - 4 && rect.top <= blockRect.bottom + 4;
    }

    function editorCaretLineHeight(surface, range) {
        const block = editorCaretBlock(surface, range);
        if (!block) return 18;
        const style = window.getComputedStyle ? window.getComputedStyle(block) : null;
        let lineHeight = Number.parseFloat(style?.lineHeight || "0") || 0;
        if (!lineHeight || style?.lineHeight === "normal") {
            lineHeight = (Number.parseFloat(style?.fontSize || "16") || 16) * 1.45;
        }
        return Math.max(14, Math.min(lineHeight, 22));
    }

    function showEditorCaretHint(root, range) {
        const surface = editorSurface(root);
        if (!surface || !range || !range.collapsed) {
            hideEditorCaretHint(root);
            return;
        }

        let rect = null;
        const rects = range.getClientRects ? Array.from(range.getClientRects()) : [];
        rect = rects.find(item => item && item.height > 0 && item.height <= 36) || null;
        if (!rect && range.startContainer && range.startContainer.nodeType === 3) {
            const probe = range.cloneRange();
            const text = range.startContainer.nodeValue || "";
            if (range.startOffset < text.length) probe.setEnd(range.startContainer, range.startOffset + 1);
            else if (range.startOffset > 0) probe.setStart(range.startContainer, range.startOffset - 1);
            rect = Array.from(probe.getClientRects ? probe.getClientRects() : []).find(item => item && item.height > 0 && item.height <= 36) || null;
        }
        if (!caretRectLooksValid(rect, surface, range)) {
            hideEditorCaretHint(root);
            return;
        }

        const lineHeight = editorCaretLineHeight(surface, range);
        let hint = qs(root, "[data-cms-editor-caret-hint]");
        if (!hint) {
            hint = document.createElement("span");
            hint.className = "dbx-cms-editor-caret-hint";
            hint.setAttribute("data-cms-editor-caret-hint", "1");
            hint.setAttribute("aria-hidden", "true");
            root.appendChild(hint);
        }

        hint.hidden = false;
        hint.style.left = Math.round(rect.left) + "px";
        hint.style.top = Math.round(rect.top + Math.max(0, (rect.height - lineHeight) / 2)) + "px";
        hint.style.height = Math.round(lineHeight) + "px";

        const s = state(root);
        if (s.editorCaretHintTimer) {
            window.clearTimeout(s.editorCaretHintTimer);
            s.editorCaretHintTimer = null;
        }
    }

    function restoreEditorSelection(root) {
        const surface = editorSurface(root);
        if (!surface) return false;
        const doc = surface.ownerDocument || document;
        const range = state(root).editorRange;
        if (!range || !range.startContainer || !range.endContainer) {
            if (surface.focus) surface.focus();
            return false;
        }

        const start = nodeElement(range.startContainer);
        const end = nodeElement(range.endContainer);
        if (!start || !end || !surface.contains(start) || !surface.contains(end)) {
            state(root).editorRange = null;
            if (surface.focus) surface.focus();
            return false;
        }

        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel) return false;
        if (surface.focus) surface.focus({ preventScroll: true });
        sel.removeAllRanges();
        sel.addRange(range);
        return true;
    }

    function pushEditorHtml(root) {
        const surface = editorSurface(root);
        const html = surface ? (surface.innerHTML || "") : getEditorHtml(root);
        const instance = getEditorInstance(root);
        if (instance && instance.value !== html) instance.value = html;
        setField(root, "content", html);
        markDirty(root);
        scheduleEditorHeight(root);
    }

    function hoistEditorMarkersToSurface(surface) {
        if (!surface) return;
        qsa(surface, ".dbx-cms-marker,[data-dbx-marker]").forEach(marker => {
            if (marker.parentElement === surface) return;
            let block = marker.parentElement;
            while (block && block.parentElement && block.parentElement !== surface) {
                block = block.parentElement;
            }
            if (block && block.parentElement === surface) {
                surface.insertBefore(marker, block.nextSibling);
            } else {
                surface.appendChild(marker);
            }
        });
    }

    function surfaceEditorBlocks(surface, ignoreMarker) {
        if (!surface) return [];
        return Array.from(surface.children).filter(el => {
            return el.nodeType === 1 && el !== ignoreMarker;
        });
    }

    function markerSurfacePlacement(surface, x, y, ignoreMarker) {
        if (!surface) return null;
        const blocks = surfaceEditorBlocks(surface, ignoreMarker);
        if (!blocks.length) {
            return { ref: null, before: true, target: null };
        }

        for (let i = 0; i < blocks.length; i++) {
            const block = blocks[i];
            const rect = block.getBoundingClientRect ? block.getBoundingClientRect() : null;
            if (!rect || !rect.height) continue;
            if (y < rect.top + rect.height / 2) {
                return { ref: block, before: true, target: block };
            }
        }

        const last = blocks[blocks.length - 1];
        return { ref: last, before: false, target: last };
    }

    function syncEditorDom(root, options) {
        options = options || {};
        const surface = editorSurface(root);
        normalizeEditorMarkers(root);
        repairInlineVideoPlayers(root, surface);
        normalizeInlineMediaLayout(surface);
        if (surface) cleanEditorRuntimeNodes(surface);
        const html = surface ? surface.innerHTML : getEditorHtml(root);
        const instance = getEditorInstance(root);
        if (instance) instance.value = html || "";
        setField(root, "content", html || "");
        if (!options.silent) markDirty(root);
        scheduleEditorHeight(root);
    }

    function saveCurrentCms(root, cfg) {
        if (root.classList.contains("is-folder-editing")) return saveFolder(root, cfg || {});
        syncEditorDom(root, { silent: true });
        return savePage(root, cfg || {});
    }

    function duplicateCurrentPage(root, cfg) {
        const s = state(root);
        const id = Number(getField(root, "id") || 0);
        const url = cfgUrl(cfg, "duplicatepage");
        if (root.classList.contains("is-folder-editing") || s.selectedType !== "page" || !id || !url) {
            status(root, cmsText(root, "page_select_first", "Bitte zuerst eine Seite auswählen."), "error");
            return Promise.resolve();
        }
        if (s.dirty) {
            status(root, "Bitte ungespeicherte Aenderungen vor dem Duplizieren speichern.", "warning");
            return Promise.resolve();
        }
        if (s.saving || s.duplicating) return Promise.resolve();

        s.duplicating = true;
        updateHeaderActionTooltips(root);
        status(root, cmsText(root, "page_duplicating", "Seite wird dupliziert..."), "info");

        return fetchJson(apiUrl(url, cmsLngParams(root)), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id })
        }).then(data => {
            if (!data || !data.ok || !data.id) {
                throw new Error(data && data.msg ? data.msg : "duplicate failed");
            }
            return loadTree(root, cfg)
                .then(() => loadPage(root, cfg, data.id))
                .then(() => {
                    status(root, data.msg || cmsText(root, "page_duplicated", "Seite wurde dupliziert."), "success");
                    maybeOpenLngProvisionAfterCreate(root, cfg, data);
                    return data;
                });
        }).catch(err => {
            dbx.error("[cms] duplicate page failed", err);
            status(root, err && err.message ? err.message : cmsText(root, "page_duplicate_error", "Seite konnte nicht dupliziert werden."), "error");
        }).finally(() => {
            s.duplicating = false;
            updateHeaderActionTooltips(root);
        });
    }

    function deleteCurrentCms(root, cfg) {
        if (root.classList.contains("is-folder-editing")) return deleteFolder(root, cfg || {});
        return deletePage(root, cfg || {});
    }

    function bindCmsKeyboardShortcuts(root, cfg) {
        if (!root || root.__dbxCmsKeyboardShortcutsBound) return;
        root.__dbxCmsKeyboardShortcutsBound = true;
        root.addEventListener("keydown", e => {
            const key = String(e.key || "").toLowerCase();
            if (!(e.ctrlKey || e.metaKey) || key !== "s") return;
            if (!root.contains(e.target)) return;
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
            saveCurrentCms(root, cfg || {});
        }, true);
    }

    function cmsConfig(root) {
        if (!root || !root.getAttribute || !dbx.parseData) return {};
        const cfgList = dbx.parseData(root.getAttribute("data-dbx"));
        return cfgList.find(item => item.lib === LIB) || {};
    }

    function editorDropTarget(root, target) {
        const surface = editorSurface(root);
        if (!surface) return null;
        let el = target && target.nodeType === 1 ? target : target?.parentElement;
        if (!el || !surface.contains(el)) return surface;
        const selector = ".dbx-cms-marker,p,h1,h2,h3,h4,ul,ol,blockquote,figure,table,img,hr";
        const block = el.closest ? el.closest(selector) : null;
        return block && surface.contains(block) ? block : surface;
    }

    function clearEditorDropMarks(root) {
        const surface = editorSurface(root);
        if (!surface) return;
        qsa(surface, ".is-drop-before,.is-drop-after").forEach(el => el.classList.remove("is-drop-before", "is-drop-after"));
    }

    function ensureLeadingEditorParagraph(surface) {
        if (!surface) return null;
        const first = surface.firstElementChild;
        if (!first || !first.classList.contains("dbx-cms-marker")) return null;
        const doc = surface.ownerDocument || document;
        const p = doc.createElement("p");
        p.innerHTML = "<br>";
        surface.insertBefore(p, first);
        return p;
    }

    function setEditorCaretInElement(root, el, offset) {
        const surface = editorSurface(root);
        if (!surface || !el) return false;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel) return false;
        const range = doc.createRange();
        offset = Number(offset || 0);
        if (el.firstChild) {
            range.setStart(el.firstChild, Math.min(offset, el.firstChild.length || 0));
        } else {
            range.setStart(el, 0);
        }
        range.collapse(true);
        if (surface.focus) surface.focus({ preventScroll: true });
        sel.removeAllRanges();
        sel.addRange(range);
        state(root).editorRange = range.cloneRange();
        refreshEditorCaretHint(root);
        return true;
    }

    function focusEditorAboveMarker(root, marker) {
        const surface = editorSurface(root);
        if (!surface || !marker) return false;
        let lead = marker.previousElementSibling;
        if (!lead || !/^(P|DIV|H[1-6]|BLOCKQUOTE|UL|OL)$/i.test(lead.tagName)) {
            if (marker === surface.firstElementChild) {
                lead = ensureLeadingEditorParagraph(surface);
            }
            if (!lead) {
                const doc = surface.ownerDocument || document;
                lead = doc.createElement("p");
                lead.innerHTML = "<br>";
                surface.insertBefore(lead, marker);
            }
        }
        if (!lead) return false;
        selectEditorMarker(root, null);
        return setEditorCaretInElement(root, lead, 0);
    }

    function createMarkerDragGhost(marker, rect) {
        const doc = marker.ownerDocument || document;
        const ghost = doc.createElement("div");
        ghost.className = marker.className;
        ghost.classList.remove("is-selected", "is-hovered", "is-dragging", "is-dragging-source");
        ghost.classList.add("dbx-cms-marker-drag-ghost");
        ghost.setAttribute("data-label", marker.getAttribute("data-label") || "");
        ghost.setAttribute("aria-hidden", "true");
        ghost.style.position = "fixed";
        ghost.style.zIndex = "2147483647";
        ghost.style.pointerEvents = "none";
        ghost.style.width = Math.max(rect.width, 120) + "px";
        ghost.style.height = Math.max(rect.height, 34) + "px";
        ghost.style.left = rect.left + "px";
        ghost.style.top = rect.top + "px";
        (doc.body || document.body).appendChild(ghost);
        return ghost;
    }

    function updateMarkerDragGhost(drag, clientX, clientY) {
        if (!drag || !drag.ghost) return;
        drag.ghost.style.left = (clientX - drag.ghostOffsetX) + "px";
        drag.ghost.style.top = (clientY - drag.ghostOffsetY) + "px";
    }

    function removeMarkerDragGhost(ghost) {
        if (ghost && ghost.parentNode) ghost.parentNode.removeChild(ghost);
    }

    function clearMarkerPointerDrag(root) {
        const drag = state(root).pointerDragMarker;
        if (!drag) return;
        removeMarkerDragGhost(drag.ghost);
        if (drag.marker) drag.marker.classList.remove("is-dragging", "is-dragging-source");
        clearEditorDropMarks(root);
        state(root).pointerDragMarker = null;
    }

    function normalizeEditorMarkers(root) {
        const surface = editorSurface(root);
        if (!surface) return;
        const selectedName = markerNameFromElement(state(root).selectedMarker);
        normalizeCommentMarkers(surface);
        normalizePlainTextMarkers(surface);
        qsa(surface, ".dbx-cms-marker,[data-dbx-marker]").forEach(marker => {
            const raw = marker.getAttribute("data-dbx-marker") || marker.getAttribute("data-dbx-marker-comment") || "";
            const name = cmsMarkerName(raw);
            if (marker.tagName !== "HR") {
                const hr = document.createElement("hr");
                Array.from(marker.attributes || []).forEach(attr => {
                    if (attr.name === "data-dbx-marker-comment") return;
                    hr.setAttribute(attr.name, attr.value);
                });
                marker.replaceWith(hr);
                marker = hr;
            }
            marker.classList.add("dbx-cms-marker", "dbx-cms-marker-" + cmsMarkerClassName(name));
            marker.setAttribute("data-dbx-marker", "dbx:" + name);
            if (!marker.getAttribute("data-label")) marker.setAttribute("data-label", cmsMarkerLabel("dbx:" + name));
            marker.setAttribute("contenteditable", "false");
            marker.setAttribute("draggable", "false");
            marker.setAttribute("tabindex", "0");
            marker.setAttribute("role", "button");
            marker.setAttribute("title", "Marker auswaehlen, ziehen zum Verschieben, Entf zum Loeschen");
        });
        hoistEditorMarkersToSurface(surface);
        dedupeAdjacentMarkers(surface);
        ensureLeadingEditorParagraph(surface);
        if (selectedName) {
            const restored = qsa(surface, ".dbx-cms-marker").find(item => markerNameFromElement(item) === selectedName);
            if (restored) selectEditorMarker(root, restored);
        }
    }

    function clearEditorMarkerHover(surface, except) {
        if (!surface) return;
        qsa(surface, ".dbx-cms-marker.is-hovered").forEach(el => {
            if (el !== except) el.classList.remove("is-hovered");
        });
    }

    function selectEditorMarker(root, marker) {
        const surface = editorSurface(root);
        selectEditorMissingMedia(root, null);
        qsa(surface, ".dbx-cms-marker.is-selected").forEach(el => {
            if (el !== marker) {
                el.classList.remove("is-selected");
                el.removeAttribute("aria-selected");
            }
        });
        const s = state(root);
        if (marker && surface && surface.contains(marker)) {
            marker.classList.add("is-selected");
            marker.setAttribute("aria-selected", "true");
            s.selectedMarker = marker;
            clearEditorMarkerHover(surface, marker);
            hideEditorCaretHint(root);
            const doc = surface.ownerDocument || document;
            const sel = doc.getSelection ? doc.getSelection() : null;
            if (sel) sel.removeAllRanges();
            if (marker.focus) marker.focus({ preventScroll: true });
        } else {
            s.selectedMarker = null;
            qsa(surface, ".dbx-cms-marker[aria-selected]").forEach(el => el.removeAttribute("aria-selected"));
        }
    }

    function handleEditorMarkerClick(root, marker, e) {
        if (!marker) return false;
        selectEditorMarker(root, marker);
        if (e) {
            if (typeof e.preventDefault === "function") e.preventDefault();
            if (typeof e.stopPropagation === "function") e.stopPropagation();
            if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
        }
        return true;
    }

    function emptyEditorParagraph(el) {
        if (!el || el.nodeType !== 1 || el.tagName !== "P") return false;
        if (el.querySelector("img,video,table,hr,.dbx-cms-marker")) return false;
        return String(el.textContent || "").trim() === "" && !el.querySelector("br");
    }

    function removeEditorMarker(root, marker) {
        const surface = editorSurface(root);
        if (!marker || !surface || !surface.contains(marker)) return false;
        const next = marker.nextElementSibling;
        marker.remove();
        if (emptyEditorParagraph(next)) next.remove();
        state(root).selectedMarker = null;
        clearEditorDropMarks(root);
        syncEditorDom(root);
        return true;
    }

    function rangeIntersectsNode(range, node) {
        if (!range || !node) return false;
        if (range.intersectsNode) {
            try {
                return range.intersectsNode(node);
            } catch (e) {
                return false;
            }
        }
        return false;
    }

    function editorRangeFromPoint(surface, x, y) {
        const doc = surface ? (surface.ownerDocument || document) : document;
        if (!surface || !doc) return null;
        let range = null;
        if (doc.caretRangeFromPoint) {
            range = doc.caretRangeFromPoint(x, y);
        } else if (doc.caretPositionFromPoint) {
            const pos = doc.caretPositionFromPoint(x, y);
            if (pos) {
                range = doc.createRange();
                range.setStart(pos.offsetNode, pos.offset);
                range.collapse(true);
            }
        }
        if (!range) return null;
        const start = nodeElement(range.startContainer);
        return start && surface.contains(start) ? range : null;
    }

    function setEditorCaretFromPoint(root, x, y) {
        const surface = editorSurface(root);
        const doc = surface ? (surface.ownerDocument || document) : document;
        const range = editorRangeFromPoint(surface, x, y);
        const sel = doc && doc.getSelection ? doc.getSelection() : null;
        if (!range || !sel) return false;
        if (surface.focus) surface.focus({ preventScroll: true });
        sel.removeAllRanges();
        sel.addRange(range);
        state(root).editorRange = range.cloneRange();
        return true;
    }

    function showEditorMarkerDropHint(root, x, y, ignoreMarker) {
        const surface = editorSurface(root);
        clearEditorDropMarks(root);
        if (!surface) return;
        const placement = markerSurfacePlacement(surface, x, y, ignoreMarker);
        const target = placement && placement.target ? placement.target : null;
        if (!target) return;
        target.classList.toggle("is-drop-before", !!placement.before);
        target.classList.toggle("is-drop-after", !placement.before);
    }

    function finishEditorMarkerDrop(root, marker) {
        if (!marker) return;
        marker.classList.remove("is-dragging", "is-dragging-source");
        marker.removeAttribute("data-cms-drag-token");
        const drag = state(root).pointerDragMarker;
        if (drag && drag.ghost) removeMarkerDragGhost(drag.ghost);
        clearEditorDropMarks(root);
        state(root).dragMarker = null;
        state(root).pointerDragMarker = null;
        const surface = editorSurface(root);
        hoistEditorMarkersToSurface(surface);
        normalizeEditorMarkers(root);
        selectEditorMarker(root, marker);
        pushEditorHtml(root);
    }

    function moveEditorMarkerToPoint(root, marker, x, y) {
        const surface = editorSurface(root);
        if (!marker || !surface || !surface.contains(marker)) return false;
        const placement = markerSurfacePlacement(surface, x, y, marker);
        if (!placement) return false;

        if (marker.parentElement) marker.parentElement.removeChild(marker);

        if (placement.ref && placement.before) {
            surface.insertBefore(marker, placement.ref);
        } else if (placement.ref) {
            surface.insertBefore(marker, placement.ref.nextSibling);
        } else {
            surface.appendChild(marker);
        }

        finishEditorMarkerDrop(root, marker);
        return true;
    }

    function editorSelectionRange(root) {
        const surface = editorSurface(root);
        if (!surface) return null;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel || !sel.rangeCount) return null;
        const range = sel.getRangeAt(0);
        return rangeInsideSurface(surface, range) ? range : null;
    }

    function editorSelectionText(root) {
        const range = editorSelectionRange(root);
        return range && !range.collapsed ? String(range.cloneContents().textContent || "") : "";
    }

    function selectEditorContents(root) {
        const surface = editorSurface(root);
        if (!surface) return false;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel) return false;
        const range = doc.createRange();
        range.selectNodeContents(surface);
        if (surface.focus) surface.focus({ preventScroll: true });
        sel.removeAllRanges();
        sel.addRange(range);
        state(root).editorRange = range.cloneRange();
        return true;
    }

    function editorContextBlock(root, target) {
        const surface = editorSurface(root);
        if (!surface) return null;
        const el = closestBootstrapComponent(root, target) || closestElement(target, ".dbx-cms-marker,figure,table,img,video,hr,p,h1,h2,h3,h4,ul,ol,blockquote");
        return el && surface.contains(el) ? el : null;
    }

    function closestBootstrapComponent(root, target) {
        const surface = editorSurface(root);
        if (!surface || !target) return null;
        const el = closestElement(target, ".row,.alert,.card,.list-group,.accordion,.table-responsive,.nav-tabs,.tab-content");
        if (!el || !surface.contains(el)) return null;
        if (el.classList.contains("row") && !qs(el, ".col,.card")) return null;
        return el;
    }

    function contextMissingMediaTarget(root, target) {
        const surface = editorSurface(root);
        const wrap = closestElement(target, ".dbx-cms-inline-media-missing-wrap");
        return wrap && surface && surface.contains(wrap) ? wrap : null;
    }

    function selectEditorMissingMedia(root, wrap) {
        const surface = editorSurface(root);
        qsa(surface, ".dbx-cms-inline-media-missing-wrap.is-selected").forEach(el => {
            el.classList.remove("is-selected");
            el.removeAttribute("aria-selected");
        });
        if (wrap && surface && surface.contains(wrap)) {
            selectEditorMarker(root, null);
            wrap.classList.add("is-selected");
            wrap.setAttribute("aria-selected", "true");
            state(root).selectedMissingMedia = wrap;
            hideEditorCaretHint(root);
            const doc = surface.ownerDocument || document;
            const sel = doc.getSelection ? doc.getSelection() : null;
            if (sel) sel.removeAllRanges();
            if (wrap.focus) wrap.focus({ preventScroll: true });
        } else {
            state(root).selectedMissingMedia = null;
            qsa(surface, ".dbx-cms-inline-media-missing-wrap[aria-selected]").forEach(el => el.removeAttribute("aria-selected"));
        }
    }

    function removeEditorMissingMedia(root, wrap) {
        wrap = wrap || state(root).selectedMissingMedia;
        if (!wrap || !wrap.parentNode) return false;
        selectEditorMissingMedia(root, null);
        wrap.remove();
        syncEditorDom(root);
        markDirty(root);
        return true;
    }

    function handleEditorMissingMediaClick(root, wrap, e) {
        if (!wrap) return false;
        selectEditorMissingMedia(root, wrap);
        if (e) {
            if (typeof e.preventDefault === "function") e.preventDefault();
            if (typeof e.stopPropagation === "function") e.stopPropagation();
            if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
        }
        return true;
    }

    function removableEditorContextTarget(root, target) {
        const surface = editorSurface(root);
        if (!surface) return null;
        const component = closestBootstrapComponent(root, target);
        if (component && surface.contains(component)) return component;
        const mod = inlineModTarget(root, target);
        if (mod && surface.contains(mod)) return mod;
        const missing = closestElement(target, ".dbx-cms-inline-media-missing-wrap");
        if (missing && surface.contains(missing)) return missing;
        const el = closestElement(target, ".dbx-cms-marker,figure,table,img,video,hr");
        if (!el || !surface.contains(el)) return null;
        if ((el.tagName === "IMG" || el.tagName === "VIDEO") && el.parentElement && el.parentElement.tagName === "FIGURE") {
            return el.parentElement;
        }
        return el;
    }

    function editorElementSibling(el, dir) {
        let node = dir < 0 ? el?.previousSibling : el?.nextSibling;
        while (node && node.nodeType !== 1) node = dir < 0 ? node.previousSibling : node.nextSibling;
        return node || null;
    }

    function moveEditorContextBlock(root, block, dir) {
        const surface = editorSurface(root);
        if (!block || !surface || !surface.contains(block) || block === surface) return false;
        const sibling = editorElementSibling(block, dir);
        if (!sibling || !block.parentElement) return false;
        if (dir < 0) block.parentElement.insertBefore(block, sibling);
        else block.parentElement.insertBefore(sibling, block);
        if (block.matches && block.matches(".dbx-cms-marker")) selectEditorMarker(root, block);
        syncEditorDom(root);
        return true;
    }

    function closeEditorContextMenu(root) {
        const s = state(root);
        if (typeof s.editorContextCleanup === "function") s.editorContextCleanup();
        if (s.editorContextMenu && s.editorContextMenu.parentNode) s.editorContextMenu.parentNode.removeChild(s.editorContextMenu);
        s.editorContextCleanup = null;
        s.editorContextMenu = null;
    }

    function clipboardWriteText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(() => false);
        }
        return Promise.resolve(false);
    }

    function clipboardReadText() {
        if (navigator.clipboard && navigator.clipboard.readText) {
            return navigator.clipboard.readText().catch(() => "");
        }
        return Promise.resolve("");
    }

    function copyEditorContext(root, target) {
        const marker = closestElement(target, ".dbx-cms-marker");
        if (marker && editorSurface(root)?.contains(marker)) {
            state(root).editorClipboardHtml = marker.outerHTML;
            return clipboardWriteText(marker.outerHTML);
        }
        restoreEditorSelection(root);
        const text = editorSelectionText(root);
        const copied = document.execCommand && document.execCommand("copy");
        if (!copied && text) return clipboardWriteText(text);
        return Promise.resolve(copied);
    }

    function cutEditorContext(root, target) {
        const marker = closestElement(target, ".dbx-cms-marker");
        if (marker && editorSurface(root)?.contains(marker)) {
            state(root).editorClipboardHtml = marker.outerHTML;
            return clipboardWriteText(marker.outerHTML).finally(() => removeEditorMarker(root, marker));
        }
        restoreEditorSelection(root);
        const text = editorSelectionText(root);
        const cut = document.execCommand && document.execCommand("cut");
        if (cut) {
            syncEditorDom(root);
            return Promise.resolve(true);
        }
        if (text) {
            return clipboardWriteText(text).finally(() => {
                execEditorCommand(root, "delete");
                syncEditorDom(root);
            });
        }
        return Promise.resolve(false);
    }

    function pasteEditorContext(root) {
        restoreEditorSelection(root);
        return clipboardReadText().then(text => {
            if (text) {
                if (document.execCommand && document.execCommand("insertText", false, text)) {
                    syncEditorDom(root);
                    return true;
                }
                insertEditorHtml(root, escapeHtml(text));
                return true;
            }
            const html = state(root).editorClipboardHtml || "";
            if (html) {
                insertEditorHtml(root, html);
                return true;
            }
            if (document.execCommand && document.execCommand("paste")) {
                syncEditorDom(root);
                return true;
            }
            return false;
        });
    }

    function deleteEditorContext(root, target) {
        const marker = closestElement(target, ".dbx-cms-marker") || state(root).selectedMarker;
        if (marker && editorSurface(root)?.contains(marker)) return removeEditorMarker(root, marker);
        const missing = contextMissingMediaTarget(root, target) || state(root).selectedMissingMedia;
        if (missing && editorSurface(root)?.contains(missing)) return removeEditorMissingMedia(root, missing);
        const mod = inlineModTarget(root, target) || state(root).selectedModPlaceholder;
        if (mod && editorSurface(root)?.contains(mod)) return removeEditorModPlaceholder(root, mod);
        const removable = removableEditorContextTarget(root, target);
        if (removable) {
            removable.remove();
            syncEditorDom(root);
            return true;
        }
        restoreEditorSelection(root);
        execEditorCommand(root, "delete");
        syncEditorDom(root);
        return true;
    }

    function selectEditorNode(root, node) {
        const surface = editorSurface(root);
        if (!surface || !node || !surface.contains(node)) return false;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel) return false;
        const range = doc.createRange();
        range.selectNode(node);
        if (surface.focus) surface.focus({ preventScroll: true });
        sel.removeAllRanges();
        sel.addRange(range);
        state(root).editorRange = range.cloneRange();
        return true;
    }

    function syncEditorAfterContextAction(root) {
        const instance = getEditorInstance(root);
        if (instance && instance.synchronizeValues) instance.synchronizeValues();
        syncEditorDom(root);
    }

    function contextImageTarget(root, target) {
        if (inlineModTarget(root, target)) return null;
        const surface = editorSurface(root);
        const img = closestElement(target, "img");
        return img && surface && surface.contains(img) ? img : null;
    }

    function contextLinkTarget(root, target) {
        const surface = editorSurface(root);
        const link = closestElement(target, "a");
        return link && surface && surface.contains(link) ? link : null;
    }

    function contextTableCell(root, target) {
        const surface = editorSurface(root);
        const cell = closestElement(target, "td,th");
        return cell && surface && surface.contains(cell) ? cell : null;
    }

    function contextTableTarget(root, target) {
        const surface = editorSurface(root);
        const table = closestElement(target, "table");
        return table && surface && surface.contains(table) ? table : null;
    }

    function openEditorImageProperties(root, img) {
        const instance = getEditorInstance(root);
        if (!instance || !img) return false;
        selectEditorNode(root, img);
        if (instance.e && instance.e.fire) {
            instance.e.fire("openImageProperties", img);
            return true;
        }
        return false;
    }

    function alignContextImage(root, img, mode) {
        if (!img) return false;
        const media = closestElement(img, ".dbx-cms-inline-media");
        const target = media && editorSurface(root)?.contains(media) ? media : img;
        [img, target].forEach(el => {
            if (!el) return;
            el.style.float = "";
            el.style.marginLeft = "";
            el.style.marginRight = "";
            el.style.display = "";
        });
        if (mode === "left") {
            target.style.float = "left";
            target.style.marginRight = "1.5rem";
        } else if (mode === "right") {
            target.style.float = "right";
            target.style.marginLeft = "1.5rem";
        } else if (mode === "center") {
            target.style.display = "block";
            target.style.marginLeft = "auto";
            target.style.marginRight = "auto";
        }
        syncEditorAfterContextAction(root);
        return true;
    }

    function openEditorLinkDialog(root, targetNode) {
        const instance = getEditorInstance(root);
        if (!instance) return false;
        if (targetNode) selectEditorNode(root, targetNode);
        if (instance.execCommand) {
            instance.execCommand("openLinkDialog");
            return true;
        }
        return false;
    }

    function removeContextLink(root, link) {
        if (!link || !link.parentNode) return false;
        while (link.firstChild) link.parentNode.insertBefore(link.firstChild, link);
        link.remove();
        syncEditorAfterContextAction(root);
        return true;
    }

    function tableApi(root) {
        const instance = getEditorInstance(root);
        if (!instance || !instance.getInstance) return null;
        try {
            return instance.getInstance("Table", instance.o);
        } catch (e) {
            return null;
        }
    }

    function runTableContextAction(root, cell, action) {
        const table = contextTableTarget(root, cell);
        const api = tableApi(root);
        if (!table || !cell || !api) return false;
        const row = cell.parentElement;
        switch (action) {
            case "row-before":
                api.appendRow(table, row, false);
                break;
            case "row-after":
                api.appendRow(table, row, true);
                break;
            case "row-delete":
                api.removeRow(table, row.rowIndex);
                break;
            case "column-before":
                api.appendColumn(table, cell, false);
                break;
            case "column-after":
                api.appendColumn(table, cell, true);
                break;
            case "column-delete":
                api.removeColumn(table, cell.cellIndex);
                break;
            case "cell-empty":
                cell.innerHTML = "<br>";
                break;
            case "table-delete":
                table.remove();
                break;
            default:
                return false;
        }
        syncEditorAfterContextAction(root);
        return true;
    }

    function contextMenuButton(label, icon, action, disabled) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "dbx-cms-context-menu-item";
        btn.disabled = !!disabled;
        if (icon) {
            const iconEl = document.createElement("i");
            iconEl.className = "bi " + icon;
            iconEl.setAttribute("aria-hidden", "true");
            btn.appendChild(iconEl);
        }
        const labelEl = document.createElement("span");
        labelEl.textContent = label;
        btn.appendChild(labelEl);
        btn.addEventListener("mousedown", e => e.preventDefault());
        btn.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();
            if (btn.disabled) return;
            const root = btn.__dbxCmsRoot;
            closeEditorContextMenu(root);
            Promise.resolve(action()).catch(err => dbx.warn("[cms] context action failed", err));
        });
        return btn;
    }

    function showEditorContextMenu(root, e) {
        const surface = editorSurface(root);
        if (!surface) return;
        closeEditorContextMenu(root);

        const target = e.target;
        const marker = closestElement(target, ".dbx-cms-marker");
        const missingMedia = contextMissingMediaTarget(root, target);
        const hasSelection = !!editorSelectionText(root);
        const img = contextImageTarget(root, target);
        const videoMedia = inlineVideoEventTarget(root, e);
        const modPlaceholder = inlineModTarget(root, target);
        const component = closestBootstrapComponent(root, target);
        const cell = contextTableCell(root, target);
        const table = contextTableTarget(root, target);

        if (marker && surface.contains(marker)) {
            selectEditorMarker(root, marker);
        } else if (missingMedia) {
            selectEditorMissingMedia(root, missingMedia);
        } else if (cell) {
            selectEditorNode(root, cell);
        } else if (modPlaceholder) {
            selectEditorModPlaceholder(root, modPlaceholder);
        } else if (component && surface.contains(component)) {
            selectEditorNode(root, component);
        } else if (videoMedia) {
            selectEditorNode(root, videoMedia);
        } else if (img) {
            selectEditorNode(root, img);
        } else if (!hasSelection) {
            setEditorCaretFromPoint(root, e.clientX, e.clientY);
            selectEditorMarker(root, null);
        }

        const menu = document.createElement("div");
        menu.className = "dbx-cms-context-menu";
        menu.setAttribute("role", "menu");
        menu.setAttribute("aria-label", "Editor Kontextmenue");

        const movable = removableEditorContextTarget(root, target);
        const hasContextTarget = !!(marker || missingMedia || modPlaceholder || component || videoMedia || img || table || movable);
        const items = [
            ["Rueckgaengig", "bi-arrow-counterclockwise", () => execEditorCommand(root, "undo"), false],
            ["Wiederholen", "bi-arrow-clockwise", () => execEditorCommand(root, "redo"), false],
            ["Alles markieren", "bi-check2-square", () => selectEditorContents(root), false],
            ["Block nach oben", "bi-arrow-up", () => moveEditorContextBlock(root, movable, -1), !movable],
            ["Block nach unten", "bi-arrow-down", () => moveEditorContextBlock(root, movable, 1), !movable],
            ["Modul Platzhalter", "bi-puzzle", () => openModPlaceholderOptions(root, modPlaceholder, cmsConfig(root) || {}), !modPlaceholder],
            ["Video Optionen", "bi-camera-video", () => openInlineVideoOptions(root, videoMedia), !videoMedia],
            ["Kopieren", "bi-clipboard", () => copyEditorContext(root, target), !hasSelection && !hasContextTarget],
            ["Ausschneiden", "bi-scissors", () => cutEditorContext(root, target), !hasSelection && !hasContextTarget],
            ["Einfuegen", "bi-clipboard-plus", () => pasteEditorContext(root), false],
            ["Loeschen", "bi-trash", () => deleteEditorContext(root, target), !hasSelection && !hasContextTarget]
        ];

        items.forEach(item => {
            const btn = contextMenuButton(item[0], item[1], item[2], item[3]);
            btn.__dbxCmsRoot = root;
            menu.appendChild(btn);
        });

        document.body.appendChild(menu);
        const vw = window.innerWidth || document.documentElement.clientWidth || 0;
        const vh = window.innerHeight || document.documentElement.clientHeight || 0;
        const rect = menu.getBoundingClientRect();
        const left = Math.max(8, Math.min(e.clientX, vw - rect.width - 8));
        const top = Math.max(8, Math.min(e.clientY, vh - rect.height - 8));
        menu.style.left = left + "px";
        menu.style.top = top + "px";

        const close = evt => {
            if (evt && menu.contains(evt.target)) return;
            closeEditorContextMenu(root);
        };
        const closeOnKey = evt => {
            if (evt.key === "Escape") closeEditorContextMenu(root);
        };
        const win = window;
        window.setTimeout(() => {
            document.addEventListener("mousedown", close, true);
            document.addEventListener("keydown", closeOnKey, true);
            win.addEventListener("scroll", close, true);
            win.addEventListener("resize", close, true);
        }, 0);
        state(root).editorContextMenu = menu;
        state(root).editorContextCleanup = () => {
            document.removeEventListener("mousedown", close, true);
            document.removeEventListener("keydown", closeOnKey, true);
            win.removeEventListener("scroll", close, true);
            win.removeEventListener("resize", close, true);
        };
    }

    function editorMarkerFromEvent(root, e) {
        const surface = editorSurface(root);
        const marker = closestElement(e.target, ".dbx-cms-marker");
        return marker && surface && surface.contains(marker) ? marker : null;
    }

    function editorRootFromMarker(marker) {
        if (!marker) return null;
        const surface = closestElement(marker, ".jodit-wysiwyg, [data-cms-editor]");
        const root = closestElement(marker, ".dbx-cms");
        return root && surface && root.contains(surface) ? root : null;
    }

    function editorMarkerAtPoint(x, y) {
        if (!Number.isFinite(Number(x)) || !Number.isFinite(Number(y))) return null;
        const direct = document.elementFromPoint ? document.elementFromPoint(x, y) : null;
        const directMarker = closestElement(direct, ".dbx-cms-marker");
        if (directMarker) return directMarker;
        return qsa(document, ".jodit-wysiwyg .dbx-cms-marker, [data-cms-editor] .dbx-cms-marker").find(marker => {
            const rect = marker.getBoundingClientRect ? marker.getBoundingClientRect() : null;
            return rect && x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
        }) || null;
    }

    function editorMarkerFromPointerEvent(e) {
        return closestElement(e.target, ".dbx-cms-marker") || editorMarkerAtPoint(e.clientX, e.clientY);
    }

    function bindEditorMarkerEvents(root) {
        const surface = editorSurface(root);
        if (!surface) return;

        ["mouseup", "keyup", "input", "touchend"].forEach(type => {
            surface.addEventListener(type, () => saveEditorSelection(root), true);
        });
        const doc = surface.ownerDocument || document;
        if (!root.__dbxCmsSelectionBound && doc && doc.addEventListener) {
            root.__dbxCmsSelectionBound = true;
            doc.addEventListener("selectionchange", () => saveEditorSelection(root));
        }
        if (!root.__dbxCmsMarkerKeyBound) {
            root.__dbxCmsMarkerKeyBound = true;
            root.addEventListener("keydown", e => {
                if (e.key !== "Delete" && e.key !== "Backspace") return;
                const marker = state(root).selectedMarker;
                const mod = state(root).selectedModPlaceholder;
                const currentSurface = editorSurface(root);
                if (marker && currentSurface && currentSurface.contains(marker)) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeEditorMarker(root, marker);
                    return;
                }
                if (mod && currentSurface && currentSurface.contains(mod)) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeEditorModPlaceholder(root, mod);
                }
            }, true);
        }
        if (!root.__dbxCmsInlineVideoResizeBound) {
            root.__dbxCmsInlineVideoResizeBound = true;
            const finish = () => finishInlineVideoResizeTrack(root);
            document.addEventListener("mouseup", finish, true);
            document.addEventListener("touchend", finish, true);
            document.addEventListener("pointerup", finish, true);
        }
        if (!root.__dbxCmsMarkerContainerBound) {
            root.__dbxCmsMarkerContainerBound = true;
            root.addEventListener("mousedown", e => {
                const marker = editorMarkerFromEvent(root, e) || editorMarkerFromPointerEvent(e);
                if (marker && editorRootFromMarker(marker) !== root) return;
                if (!marker) return;
                selectEditorMarker(root, marker);
                e.stopPropagation();
            }, true);
            root.addEventListener("click", e => {
                const marker = editorMarkerFromEvent(root, e) || editorMarkerFromPointerEvent(e);
                if (marker && editorRootFromMarker(marker) !== root) return;
                if (!marker) return;
                handleEditorMarkerClick(root, marker, e);
            }, true);
        }

        if (surface.__dbxCmsMarkerEventsBound) return;
        surface.__dbxCmsMarkerEventsBound = true;

        surface.addEventListener("mousedown", e => {
            const marker = closestElement(e.target, ".dbx-cms-marker");
            if (marker && surface.contains(marker)) {
                selectEditorMarker(root, marker);
                e.stopPropagation();
                return;
            }
            const missingMedia = contextMissingMediaTarget(root, e.target);
            if (missingMedia) {
                handleEditorMissingMediaClick(root, missingMedia, e);
                return;
            }
            const modPlaceholder = inlineModTarget(root, e.target);
            if (modPlaceholder) {
                selectEditorModPlaceholder(root, modPlaceholder);
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
                return;
            }
            const videoBlock = inlineVideoEventTarget(root, e);
            if (videoBlock) {
                beginInlineVideoResizeTrack(root, videoBlock);
                if (e.button !== undefined && e.button !== 0) return;
                if (!isInlineVideoResizeHandleEvent(videoBlock, e)) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
                    openInlineVideoOptions(root, videoBlock);
                }
                return;
            }
            const first = surface.firstElementChild;
            if (first && first.classList.contains("dbx-cms-marker")) {
                const rect = first.getBoundingClientRect();
                if (e.clientY < rect.top + 8) {
                    focusEditorAboveMarker(root, first);
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            }
            selectEditorMarker(root, null);
            selectEditorMissingMedia(root, null);
            selectEditorModPlaceholder(root, null);
        }, true);

        surface.addEventListener("mouseover", e => {
            const marker = closestElement(e.target, ".dbx-cms-marker");
            clearEditorMarkerHover(surface, marker);
            if (marker && surface.contains(marker)) marker.classList.add("is-hovered");
        }, true);

        surface.addEventListener("mouseout", e => {
            const marker = closestElement(e.target, ".dbx-cms-marker");
            if (!marker || !surface.contains(marker)) return;
            const related = e.relatedTarget;
            if (!related || !marker.contains(related)) marker.classList.remove("is-hovered");
        }, true);

        surface.addEventListener("focusin", () => {
            if (!state(root).selectedMarker && !state(root).selectedMissingMedia) refreshEditorCaretHint(root);
        }, true);

        surface.addEventListener("focusout", e => {
            const next = e.relatedTarget;
            if (next && surface.contains(next)) return;
            if (next && closestElement(next, ".dbx-cms-marker")) return;
            hideEditorCaretHint(root);
        }, true);

        surface.addEventListener("click", e => {
            const marker = closestElement(e.target, ".dbx-cms-marker");
            if (marker && surface.contains(marker)) {
                handleEditorMarkerClick(root, marker, e);
                return;
            }
            const missingMedia = contextMissingMediaTarget(root, e.target);
            if (missingMedia) {
                handleEditorMissingMediaClick(root, missingMedia, e);
                return;
            }
            const modPlaceholder = inlineModTarget(root, e.target);
            if (modPlaceholder) {
                selectEditorModPlaceholder(root, modPlaceholder);
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
                return;
            }
            const videoBlock = inlineVideoEventTarget(root, e);
            if (videoBlock) {
                if (e.button !== undefined && e.button !== 0) return;
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
                openInlineVideoOptions(root, videoBlock);
                return;
            }
            saveEditorSelection(root);
            selectEditorMarker(root, null);
            selectEditorMissingMedia(root, null);
        }, true);

        surface.addEventListener("keydown", e => {
            if (e.key !== "Delete" && e.key !== "Backspace") return;
            const marker = closestElement(e.target, ".dbx-cms-marker") || state(root).selectedMarker;
            if (marker && surface.contains(marker)) {
                e.preventDefault();
                e.stopPropagation();
                removeEditorMarker(root, marker);
                return;
            }
            const missingMedia = contextMissingMediaTarget(root, e.target) || state(root).selectedMissingMedia;
            if (missingMedia && surface.contains(missingMedia)) {
                e.preventDefault();
                e.stopPropagation();
                removeEditorMissingMedia(root, missingMedia);
                return;
            }
            const mod = inlineModTarget(root, e.target) || state(root).selectedModPlaceholder;
            if (mod && surface.contains(mod)) {
                e.preventDefault();
                e.stopPropagation();
                removeEditorModPlaceholder(root, mod);
            }
        }, true);

        surface.addEventListener("contextmenu", e => {
            if (!surface.contains(e.target)) return;
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
            showEditorContextMenu(root, e);
        }, true);
    }

    function bindEditorMarkerEventsRetry(root, attempt) {
        attempt = Number(attempt || 0);
        bindEditorMarkerEvents(root);
        const surface = editorSurface(root);
        if (surface && surface.__dbxCmsMarkerEventsBound) return;
        if (attempt < 20) window.setTimeout(() => bindEditorMarkerEventsRetry(root, attempt + 1), 100);
    }

    function bindGlobalCmsEditorEvents() {
        if (document.__dbxCmsEditorGlobalEventsBound) return;
        document.__dbxCmsEditorGlobalEventsBound = true;
        if (document.documentElement) document.documentElement.setAttribute("data-dbx-cms-global-events", "1");
        document.addEventListener("pointerdown", e => {
            if (e.button !== 0) return;
            const marker = editorMarkerFromPointerEvent(e);
            const root = editorRootFromMarker(marker);
            if (!root) return;
            selectEditorMarker(root, marker);
            state(root).pointerDragMarker = {
                marker,
                startX: e.clientX,
                startY: e.clientY,
                dragging: false
            };
            e.stopPropagation();
        }, true);
        document.addEventListener("pointermove", e => {
            qsa(document, ".dbx-cms").forEach(currentRoot => {
                const drag = state(currentRoot).pointerDragMarker;
                if (!drag || !drag.marker) return;
                const dx = Math.abs(e.clientX - drag.startX);
                const dy = Math.abs(e.clientY - drag.startY);
                if (!drag.dragging && dx < 6 && dy < 6) return;
                if (!drag.dragging) {
                    drag.dragging = true;
                    const rect = drag.marker.getBoundingClientRect();
                    drag.ghostOffsetX = drag.startX - rect.left;
                    drag.ghostOffsetY = drag.startY - rect.top;
                    drag.ghost = createMarkerDragGhost(drag.marker, rect);
                    drag.marker.classList.add("is-dragging-source");
                }
                e.preventDefault();
                updateMarkerDragGhost(drag, e.clientX, e.clientY);
                showEditorMarkerDropHint(currentRoot, e.clientX, e.clientY, drag.marker);
            });
        }, true);
        document.addEventListener("pointerup", e => {
            qsa(document, ".dbx-cms").forEach(root => {
                const drag = state(root).pointerDragMarker;
                if (!drag || !drag.marker) return;
                if (drag.dragging) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!moveEditorMarkerToPoint(root, drag.marker, e.clientX, e.clientY)) {
                        clearMarkerPointerDrag(root);
                    }
                } else {
                    selectEditorMarker(root, drag.marker);
                    clearMarkerPointerDrag(root);
                }
            });
        }, true);
        document.addEventListener("pointercancel", () => {
            qsa(document, ".dbx-cms").forEach(root => clearMarkerPointerDrag(root));
        }, true);
        document.addEventListener("keydown", e => {
            const key = String(e.key || "").toLowerCase();
            const targetRoot = closestElement(e.target, ".dbx-cms");
            if ((e.ctrlKey || e.metaKey) && key === "s" && targetRoot) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
                saveCurrentCms(targetRoot, cmsConfig(targetRoot));
                return;
            }
            if (e.key !== "Delete" && e.key !== "Backspace") return;
            const root = targetRoot || editorRootFromMarker(closestElement(e.target, ".dbx-cms-marker"));
            const marker = root ? state(root).selectedMarker : null;
            const missingMedia = root ? state(root).selectedMissingMedia : null;
            const mod = root ? state(root).selectedModPlaceholder : null;
            const surface = root ? editorSurface(root) : null;
            if (marker && surface && surface.contains(marker)) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
                removeEditorMarker(root, marker);
                return;
            }
            if (missingMedia && surface && surface.contains(missingMedia)) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
                removeEditorMissingMedia(root, missingMedia);
                return;
            }
            if (mod && surface && surface.contains(mod)) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
                removeEditorModPlaceholder(root, mod);
            }
        }, true);
    }

    function insertEditorHtml(root, html) {
        const instance = getEditorInstance(root);
        if (instance && typeof instance.setMode === "function") {
            instance.setMode(window.Jodit && window.Jodit.MODE_WYSIWYG ? window.Jodit.MODE_WYSIWYG : 1);
        }
        hideEditorCaretHint(root);
        restoreEditorSelection(root);
        if (instance && instance.s && instance.s.insertHTML) {
            instance.s.insertHTML(html);
            normalizeEditorMarkers(root);
            normalizeBootstrapComponents(editorSurface(root));
            bindEditorMarkerEventsRetry(root);
            syncEditorDom(root);
            saveEditorSelection(root);
            return;
        }
        document.execCommand("insertHTML", false, html);
        normalizeEditorMarkers(root);
        normalizeBootstrapComponents(editorSurface(root));
        bindEditorMarkerEventsRetry(root);
        syncEditorDom(root);
        saveEditorSelection(root);
    }

    function bootstrapComponentItems() {
        const openWinData = "lib=openWin|title=Information|width=900|height=80%|position=center-top|reload=1|minimizable=1|maximizable=1";
        return [
            {
                label: "Hinweis / Alert",
                html: '<div class="alert alert-info" role="alert"><h4 class="alert-heading">Hinweis</h4><p>Kurzer Hinweistext fuer den Inhalt.</p></div><p></p>'
            },
            {
                label: "Card",
                html: '<div class="card"><div class="card-body"><h3 class="card-title">Karten-Titel</h3><p class="card-text">Kurzer Text fuer diese Karte.</p><a class="btn btn-primary" href="#">Mehr erfahren</a></div></div><p></p>'
            },
            {
                label: "3 Karten",
                html: '<div class="row row-cols-1 row-cols-md-3 g-3"><div class="col"><div class="card h-100"><div class="card-body"><h3 class="card-title">Erste Karte</h3><p class="card-text">Kurzer Text.</p></div></div></div><div class="col"><div class="card h-100"><div class="card-body"><h3 class="card-title">Zweite Karte</h3><p class="card-text">Kurzer Text.</p></div></div></div><div class="col"><div class="card h-100"><div class="card-body"><h3 class="card-title">Dritte Karte</h3><p class="card-text">Kurzer Text.</p></div></div></div></div><p></p>'
            },
            {
                label: "List Group",
                html: '<div class="list-group"><a class="list-group-item list-group-item-action active" href="#">Aktiver Punkt</a><a class="list-group-item list-group-item-action" href="#">Weiterer Punkt</a><a class="list-group-item list-group-item-action" href="#">Dritter Punkt</a></div><p></p>'
            },
            {
                label: "CTA Button",
                html: '<p><a class="btn btn-primary" href="#">Informationen anfragen</a> <a class="btn btn-outline-primary" href="#">Mehr erfahren</a></p><p></p>'
            },
            {
                label: "openWin Link",
                html: '<p><a class="btn btn-outline-primary dbx-win" href="kontakt" data-dbx="' + openWinData + '|url=kontakt">Im Fenster oeffnen</a></p><p></p>'
            },
            {
                label: "Accordion",
                html: '<div class="accordion" id="dbxCmsAccordion"><div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#dbxCmsAccordionOne" aria-expanded="true" aria-controls="dbxCmsAccordionOne">Erster Bereich</button></h3><div id="dbxCmsAccordionOne" class="accordion-collapse collapse show" data-bs-parent="#dbxCmsAccordion"><div class="accordion-body">Inhalt des ersten Bereichs.</div></div></div><div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dbxCmsAccordionTwo" aria-expanded="false" aria-controls="dbxCmsAccordionTwo">Zweiter Bereich</button></h3><div id="dbxCmsAccordionTwo" class="accordion-collapse collapse" data-bs-parent="#dbxCmsAccordion"><div class="accordion-body">Inhalt des zweiten Bereichs.</div></div></div></div><p></p>'
            },
            {
                label: "Tabs",
                html: '<div><ul class="nav nav-tabs" role="tablist"><li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#dbxCmsTabOne" role="tab">Tab 1</button></li><li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#dbxCmsTabTwo" role="tab">Tab 2</button></li></ul><div class="tab-content border border-top-0 p-3"><div class="tab-pane fade show active" id="dbxCmsTabOne" role="tabpanel">Inhalt Tab 1.</div><div class="tab-pane fade" id="dbxCmsTabTwo" role="tabpanel">Inhalt Tab 2.</div></div></div><p></p>'
            },
            {
                label: "Tabelle",
                html: '<div class="table-responsive"><table class="table table-striped table-hover align-middle"><thead><tr><th>Spalte 1</th><th>Spalte 2</th><th>Spalte 3</th></tr></thead><tbody><tr><td>Wert</td><td>Wert</td><td>Wert</td></tr><tr><td>Wert</td><td>Wert</td><td>Wert</td></tr></tbody></table></div><p></p>'
            }
        ];
    }

    function cmsBootstrapComponentIcon() {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M7 8h10M7 12h4M13 12h4M7 16h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    }

    function insertBootstrapComponent(root, html) {
        const uid = "dbxCms" + Date.now().toString(36) + Math.floor(Math.random() * 1000).toString(36);
        html = String(html || "")
            .replaceAll("dbxCmsAccordion", uid + "Accordion")
            .replaceAll("dbxCmsAccordionOne", uid + "AccordionOne")
            .replaceAll("dbxCmsAccordionTwo", uid + "AccordionTwo")
            .replaceAll("dbxCmsTabOne", uid + "TabOne")
            .replaceAll("dbxCmsTabTwo", uid + "TabTwo");
        insertEditorFragment(root, html);
        normalizeEditorMarkers(root);
        normalizeBootstrapComponents(editorSurface(root));
        syncEditorDom(root);
        markDirty(root);
        scheduleEditorHeight(root);
    }

    function normalizeBootstrapComponents(surface) {
        if (!surface) return;
        const doc = surface.ownerDocument || document;
        qsa(surface, ".card").forEach(card => {
            let body = Array.from(card.children || []).find(child => child.classList && child.classList.contains("card-body"));
            if (!body) {
                body = doc.createElement("div");
                body.className = "card-body";
                const insertAfter = Array.from(card.children || []).reverse().find(child => {
                    if (!child.classList) return false;
                    return child.classList.contains("card-img-top")
                        || child.classList.contains("card-img")
                        || child.classList.contains("card-header")
                        || child.classList.contains("badge")
                        || child.classList.contains("position-absolute");
                });
                card.insertBefore(body, insertAfter ? insertAfter.nextSibling : card.firstChild);
            }

            Array.from(card.childNodes || []).forEach(node => {
                if (node === body) return;
                if (node.nodeType === 3) {
                    if (String(node.nodeValue || "").trim() === "") return;
                    body.appendChild(node);
                    return;
                }
                if (node.nodeType !== 1) return;
                if (node.classList && (
                    node.classList.contains("card-body")
                    || node.classList.contains("card-img-top")
                    || node.classList.contains("card-img")
                    || node.classList.contains("card-header")
                    || node.classList.contains("card-footer")
                    || node.classList.contains("list-group")
                    || node.classList.contains("dbx-cms-inline-media")
                    || node.classList.contains("dbx-media")
                    || node.classList.contains("position-absolute")
                )) return;
                if (isEmptyEditorBlock(node)) {
                    node.remove();
                    return;
                }
                if (/^(H[1-6]|P|A|BUTTON|UL|OL|DIV|SPAN|SMALL|STRONG|EM)$/i.test(node.tagName || "")) {
                    body.appendChild(node);
                }
            });

            qsa(card, ".position-absolute.badge, .position-absolute .badge, .badge.position-absolute").forEach(badge => {
                if (!card.contains(badge)) return;
                badge.setAttribute("contenteditable", "false");
                Array.from(badge.children || []).forEach(child => {
                    if (/^(H[1-6]|P|DIV|A|BUTTON|UL|OL)$/i.test(child.tagName || "")) body.appendChild(child);
                });
            });

            qsa(card, "img.card-img-top,img.card-img,img.card-img-bottom").forEach(img => {
                img.setAttribute("contenteditable", "false");
                img.setAttribute("draggable", "false");
            });

            qsa(body, "p,div").forEach(block => {
                if (block === body) return;
                if (!isEmptyEditorBlock(block)) return;
                block.remove();
            });

            if (!nodeHasEditorContent(body)) {
                const p = doc.createElement("p");
                p.innerHTML = "<br>";
                body.appendChild(p);
            }
        });
    }

    function isEmptyEditorBlock(block) {
        if (!block || block.nodeType !== 1) return false;
        if (!/^(P|DIV)$/i.test(block.tagName || "")) return false;
        if (block.querySelector("img,video,iframe,object,embed,table,hr,ul,ol,.dbx-cms-marker,.dbx-cms-inline-media")) return false;
        return String(block.textContent || "").replace(/\u00a0/g, " ").replace(/\uFEFF/g, "").trim() === "";
    }

    function editableCardBlockFromSelection(surface) {
        if (!surface) return null;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel || !sel.rangeCount) return null;
        const range = sel.getRangeAt(0);
        if (!range.collapsed || !surface.contains(range.commonAncestorContainer)) return null;
        const el = nodeElement(range.startContainer);
        const body = closestElement(el, ".card-body");
        if (!body || !surface.contains(body)) return null;
        const block = closestElement(el, "p,div,h1,h2,h3,h4,h5,h6,li") || body;
        if (!body.contains(block)) return null;
        return { body, block, range };
    }

    function rangeIsAtElementBoundary(range, element, boundary) {
        if (!range || !element) return false;
        const probe = range.cloneRange();
        if (boundary === "start") {
            probe.selectNodeContents(element);
            probe.setEnd(range.startContainer, range.startOffset);
        } else {
            probe.selectNodeContents(element);
            probe.setStart(range.startContainer, range.startOffset);
        }
        return String(probe.toString() || "").replace(/\u00a0/g, " ").replace(/\uFEFF/g, "").trim() === "";
    }

    function firstEditableCardBodyBlock(body) {
        return Array.from(body.children || []).find(child => {
            if (!/^(P|DIV|H1|H2|H3|H4|H5|H6|UL|OL|LI|A|BUTTON)$/i.test(child.tagName || "")) return false;
            return nodeHasEditorContent(child);
        }) || null;
    }

    function lastEditableCardBodyBlock(body) {
        return Array.from(body.children || []).reverse().find(child => {
            if (!/^(P|DIV|H1|H2|H3|H4|H5|H6|UL|OL|LI|A|BUTTON)$/i.test(child.tagName || "")) return false;
            return nodeHasEditorContent(child);
        }) || null;
    }

    function setEditorCaretInCardBody(root, body) {
        const first = firstEditableCardBodyBlock(body) || body;
        return setEditorCaretInElement(root, first, 0);
    }

    function handleBootstrapCardDeleteKey(root, event) {
        const surface = editorSurface(root);
        const ctx = editableCardBlockFromSelection(surface);
        if (!ctx) return false;
        const key = String(event.key || "");
        if (key !== "Backspace" && key !== "Delete") return false;
        const { body, block, range } = ctx;

        if (block !== body && isEmptyEditorBlock(block)) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === "function") event.stopImmediatePropagation();
            const next = block.nextElementSibling;
            const prev = block.previousElementSibling;
            block.remove();
            const target = key === "Delete" ? (next || prev || body) : (prev || next || body);
            setEditorCaretInElement(root, target, 0);
            normalizeBootstrapComponents(surface);
            syncEditorDom(root);
            return true;
        }

        const first = firstEditableCardBodyBlock(body);
        if (key === "Backspace" && first && (block === first || first.contains(block)) && rangeIsAtElementBoundary(range, block, "start")) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === "function") event.stopImmediatePropagation();
            setEditorCaretInCardBody(root, body);
            return true;
        }

        const last = lastEditableCardBodyBlock(body);
        if (key === "Delete" && last && (block === last || last.contains(block)) && rangeIsAtElementBoundary(range, block, "end")) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === "function") event.stopImmediatePropagation();
            return true;
        }
        return false;
    }

    function bindBootstrapCardEditingGuards(root) {
        if (!root) return;
        const surface = editorSurface(root);
        if (!surface || surface.__dbxCmsBootstrapCardGuardsBound) return;
        surface.__dbxCmsBootstrapCardGuardsBound = true;
        surface.addEventListener("keydown", event => {
            handleBootstrapCardDeleteKey(root, event);
        }, true);
        surface.addEventListener("input", () => {
            window.requestAnimationFrame(() => {
                normalizeBootstrapComponents(surface);
            });
        }, true);
    }

    function insertEditorFragment(root, html) {
        const surface = editorSurface(root);
        if (!surface) return false;
        const doc = surface.ownerDocument || document;
        const tpl = doc.createElement("template");
        tpl.innerHTML = String(html || "");
        if (!tpl.content || !tpl.content.childNodes.length) return false;

        const instance = getEditorInstance(root);
        if (instance && typeof instance.setMode === "function") {
            instance.setMode(window.Jodit && window.Jodit.MODE_WYSIWYG ? window.Jodit.MODE_WYSIWYG : 1);
        }
        hideEditorCaretHint(root);
        restoreEditorSelection(root);
        if (surface.focus) surface.focus({ preventScroll: true });

        const sel = doc.getSelection ? doc.getSelection() : null;
        let range = sel && sel.rangeCount ? sel.getRangeAt(0) : null;
        if (!range || !surface.contains(range.commonAncestorContainer)) {
            range = doc.createRange();
            range.selectNodeContents(surface);
            range.collapse(false);
        }

        const nodes = Array.from(tpl.content.childNodes);
        range.deleteContents();
        range.insertNode(tpl.content);

        normalizeEditorMarkers(root);
        normalizeBootstrapComponents(surface);
        bindEditorMarkerEventsRetry(root);
        syncEditorDom(root);
        setEditorCaretAfterNode(root, nodes[nodes.length - 1]);
        saveEditorSelection(root);
        return true;
    }

    function execEditorCommand(root, command) {
        const instance = getEditorInstance(root);
        if (instance && instance.execCommand) {
            instance.execCommand(command);
            setField(root, "content", instance.value || "");
            markDirty(root);
            scheduleEditorHeight(root);
            return;
        }
        document.execCommand(command, false, null);
        markDirty(root);
        scheduleEditorHeight(root);
    }

    function serializeCmsMarkers(html) {
        const box = document.createElement("div");
        box.innerHTML = html || "";
        normalizeCommentMarkers(box);
        cleanEditorRuntimeNodes(box);
        normalizePlainTextMarkers(box);
        qsa(box, ".dbx-cms-marker[data-dbx-marker-comment]").forEach(marker => {
            const markerText = marker.getAttribute("data-dbx-marker-comment") || "";
            const name = cmsMarkerName(markerText);
            const hr = document.createElement("hr");
            hr.className = "dbx-cms-marker dbx-cms-marker-" + cmsMarkerClassName(name);
            hr.setAttribute("data-dbx-marker", "dbx:" + name);
            hr.setAttribute("data-label", marker.textContent || markerText);
            hr.setAttribute("contenteditable", "false");
            hr.setAttribute("draggable", "false");
            hr.setAttribute("tabindex", "0");
            marker.replaceWith(hr);
        });
        qsa(box, ".dbx-cms-marker,[data-dbx-marker]").forEach(marker => {
            const name = cmsMarkerName(marker.getAttribute("data-dbx-marker") || "");
            if (marker.tagName !== "HR") {
                const hr = document.createElement("hr");
                Array.from(marker.attributes || []).forEach(attr => {
                    if (attr.name === "data-dbx-marker-comment") return;
                    hr.setAttribute(attr.name, attr.value);
                });
                marker.replaceWith(hr);
                marker = hr;
            }
            marker.classList.remove("is-selected", "is-dragging", "is-drop-before", "is-drop-after");
            marker.classList.add("dbx-cms-marker", "dbx-cms-marker-" + cmsMarkerClassName(name));
            marker.removeAttribute("data-cms-drag-token");
            marker.setAttribute("data-dbx-marker", "dbx:" + name);
            if (!marker.getAttribute("data-label")) marker.setAttribute("data-label", cmsMarkerLabel("dbx:" + name));
            marker.setAttribute("contenteditable", "false");
            marker.setAttribute("draggable", "false");
            marker.setAttribute("tabindex", "0");
        });
        dedupeAdjacentMarkers(box);
        return box.innerHTML;
    }

    function folderTreeEditButton(id) {
        return `<button type="button" class="dbx-cms-tree-edit" data-cms-folder-edit-btn data-id="${escapeHtml(id)}" title="Ordner bearbeiten" aria-label="Ordner bearbeiten"><i class="bi bi-pencil-square"></i></button>`;
    }

    function bindTreeSearchClear(root, input) {
        if (!input || input.__dbxCmsTreeSearchClearBound) return;
        const wrap = input.closest(".dbx-input-clearable");
        if (!wrap) return;

        input.__dbxCmsTreeSearchClearBound = true;
        wrap.classList.add("dbx-cms-tree-search-wrap");

        let btn = wrap.querySelector(".dbx-clear-btn");
        if (!btn) {
            btn = document.createElement("button");
            btn.type = "button";
            btn.className = "dbx-clear-btn";
            btn.setAttribute("aria-label", "Suche zurücksetzen");
            btn.setAttribute("title", "Suche zurücksetzen");
            btn.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
            wrap.appendChild(btn);
        }
        btn.classList.add("dbx-cms-tree-search-clear");

        const sync = () => {
            if (String(input.value || "").length > 0) {
                btn.removeAttribute("hidden");
            } else {
                btn.setAttribute("hidden", "");
            }
        };

        input.addEventListener("input", sync);
        btn.addEventListener("click", e => {
            e.preventDefault();
            input.value = "";
            input.dispatchEvent(new Event("input", { bubbles: true }));
            input.dispatchEvent(new Event("change", { bubbles: true }));
            input.focus();
            renderTree(root);
            sync();
        });
        sync();
    }

    function renderTree(root) {
        const s = state(root);
        const box = qs(root, "[data-cms-tree]");
        const searchRaw = String(qs(root, "[data-cms-search]")?.value || "").trim();
        const search = searchRaw.toLowerCase();
        const searchIsNumeric = /^\d+$/.test(searchRaw);
        if (!box) return;

        function treeSearchText(node) {
            const rights = root.classList.contains("dbx-cms-view") ? "" : String(node._rights || "");
            return [
                String(node._id || ""),
                String(node._title || ""),
                rights
            ].join(" ").toLowerCase();
        }

        function nodeMatches(node) {
            if (!search) return true;
            if (searchIsNumeric && String(node._id || "") === searchRaw) return true;
            return treeSearchText(node).includes(search);
        }

        function hasSearchMatch(node) {
            if (!search) return true;
            if (nodeMatches(node)) return true;
            const children = Array.isArray(node._children) ? node._children : [];
            return children.some(hasSearchMatch);
        }

        function searchClass(node, children) {
            if (!search) return "";
            const exactId = searchIsNumeric && String(node._id || "") === searchRaw;
            if (exactId) return " is-search-match is-search-id-match";
            if (nodeMatches(node)) return " is-search-match";
            if (children.some(hasSearchMatch)) return " is-search-path";
            return "";
        }

        function renderNode(node) {
            const children = Array.isArray(node._children) ? node._children : [];
            if (search && !hasSearchMatch(node)) return "";
            const type = node._type === "folder" ? "folder" : "page";
            const icon = type === "folder" ? "bi-folder2-open" : "bi-file-earmark-text";
            const active =
                (type === "page" && s.selectedType !== "folder" && Number(node._id) === Number(s.selectedPage)) ||
                (type === "folder" && s.selectedType === "folder" && Number(node._id) === Number(s.selectedFolder))
                    ? " is-active"
                    : "";
            const matchedClass = searchClass(node, children);
            const collapsed = type === "folder" && children.length ? isFolderCollapsed(root, node._id, search) : false;
            const expandedAttr = collapsed ? "false" : "true";
            const toggleClass = collapsed ? "bi-chevron-right" : "bi-chevron-down";
            const collapsedClass = collapsed ? " is-collapsed" : "";
            const editBtn = type === "folder" ? folderTreeEditButton(node._id) : "";
            const toggle = type === "folder" && children.length
                ? `<span class="dbx-cms-tree-toggle" data-cms-tree-toggle data-id="${escapeHtml(node._id)}" title="Ordner ein- oder ausklappen"><i class="bi ${toggleClass}"></i></span>`
                : `<span class="dbx-cms-tree-toggle-spacer"></span>`;
            if (node._row_html) {
                let html = String(node._row_html);
                if (active && !html.includes(" is-active")) {
                    html = html.replace("dbx-cms-tree-row", "dbx-cms-tree-row is-active");
                }
                if (matchedClass) {
                    html = html.replace(/\bdbx-cms-tree-row\b/, "dbx-cms-tree-row" + matchedClass);
                }
                if (!html.includes("data-cms-tree-toggle") && !html.includes("dbx-cms-tree-toggle-spacer")) {
                    html = html.replace('<span class="dbx-cms-tree-icons">', toggle + '<span class="dbx-cms-tree-icons">');
                }
                if (type === "folder") {
                    html = html.replace(/\bdbx-cms-tree-row\b/, "dbx-cms-tree-row" + collapsedClass);
                    const rowAria = /(<div role="button"[^>]*?)\saria-expanded="[^"]*"/;
                    if (rowAria.test(html)) {
                        html = html.replace(rowAria, '$1 aria-expanded="' + expandedAttr + '"');
                    } else {
                        html = html.replace('<div role="button"', '<div role="button" aria-expanded="' + expandedAttr + '"');
                    }
                    if (!html.includes("data-cms-folder-edit-btn")) {
                        if (html.includes('class="dbx-cms-lng-badges"')) {
                            html = html.replace(/<span class="dbx-cms-lng-badges"/, editBtn + '<span class="dbx-cms-lng-badges"');
                        } else {
                            html = html.replace(/(<span class="dbx-cms-tree-meta">)([\s\S]*?)(<\/span>)/, '$1$2' + editBtn + '$3');
                        }
                    }
                }
                if (children.length) {
                    html += `<div class="dbx-cms-tree-children"${collapsed ? " hidden" : ""}>`;
                    children.forEach(child => {
                        if (!search || hasSearchMatch(child)) html += renderNode(child);
                    });
                    html += `</div>`;
                }
                return html;
            }
            const rights = type === "folder" && node._rights ? `<span class="dbx-cms-rights" data-cms-folder-edit title="Ordnerrechte bearbeiten">${escapeHtml(node._rights)}</span>` : "";
            const lngBadges = node._lng_badges ? String(node._lng_badges) : "";
            const meta = `${rights}${editBtn}${lngBadges}`;
            const title = type === "page"
                ? `<span class="dbx-cms-page-id">(${escapeHtml(node._id)})</span> ${escapeHtml(node._title)}`
                : escapeHtml(node._title);

            let html = "";
            if (!search || hasSearchMatch(node)) {
                html += `<div role="button" tabindex="0" draggable="true" class="dbx-cms-tree-row dbx-cms-tree-${type}${active}${matchedClass}${collapsedClass}" data-type="${type}" data-id="${escapeHtml(node._id)}" data-folder="${escapeHtml(node._parent || node._id)}"${type === "folder" ? ` aria-expanded="${expandedAttr}"` : ""}>`;
                html += toggle;
                html += `<span class="dbx-cms-tree-icons"><i class="bi ${icon}"></i>`;
                html += `</span><span class="dbx-cms-tree-label">${title}</span><span class="dbx-cms-tree-meta">${meta}</span>`;
                html += `</div>`;
            }

            if (children.length) {
                html += `<div class="dbx-cms-tree-children"${collapsed ? " hidden" : ""}>`;
                children.forEach(child => {
                    if (!search || hasSearchMatch(child)) html += renderNode(child);
                });
                html += `</div>`;
            }

            return html;
        }

        if (!s.tree.length) {
            box.innerHTML = '<div class="dbx-cms-empty">Keine Content-Struktur vorhanden.</div>';
            return;
        }

        box.innerHTML = s.tree.map(renderNode).join("");
        if (search) {
            const firstMatch = qs(box, ".dbx-cms-tree-row.is-search-id-match") || qs(box, ".dbx-cms-tree-row.is-search-match");
            if (firstMatch) {
                requestAnimationFrame(() => {
                    firstMatch.scrollIntoView({ block: "center", inline: "nearest" });
                });
            }
        }
    }

    function toggleTreeFolder(root, row, forceCollapsed) {
        if (!row) return;
        const id = Number(row.getAttribute("data-id") || 0);
        if (!id || row.getAttribute("data-type") !== "folder") return;
        const collapsed = typeof forceCollapsed === "boolean"
            ? forceCollapsed
            : !isFolderCollapsed(root, id, false);
        setFolderCollapsed(root, id, collapsed);
        renderTree(root);
    }

    function loadTree(root, cfg) {
        const s = state(root);
        const url = cfgUrl(cfg, "tree");
        const box = qs(root, "[data-cms-tree]");
        if (!url) {
            if (box) box.innerHTML = '<div class="dbx-cms-empty">Tree-URL fehlt.</div>';
            return Promise.resolve();
        }
        if (box) box.innerHTML = '<div class="dbx-cms-empty">Tree wird geladen...</div>';

        return fetchJson(apiUrl(url, cmsLngParams(root)))
            .then(data => {
                s.tree = Array.isArray(data.nodes) ? data.nodes : [];
                s.flat = Array.isArray(data.flat) ? data.flat : [];
                renderTree(root);

                if (!s.selectionRestored) {
                    s.selectionRestored = true;
                    if (isViewMode(cfg)) {
                        const requestedPage = Number(cfg && cfg.cid ? cfg.cid : 0) || 0;
                        if (requestedPage > 0) {
                            if (root.getAttribute("data-cms-initial-page-loaded") === "1") {
                                const node = s.flat.find(n => n._type === "page" && Number(n._id) === requestedPage) || {};
                                s.page = { id: requestedPage, title: node._title || "" };
                                setSelectedPage(root, requestedPage);
                                setSelectedType(root, "page");
                                updateViewPageTitle(root, node._title || "");
                                root.removeAttribute("data-cms-initial-page-loaded");
                                revealTreeSelection(root);
                                return;
                            }
                            return loadViewPage(root, cfg, requestedPage);
                        }
                    if (s.selectedPage > 0) {
                        return loadViewPage(root, cfg, s.selectedPage);
                    }
                        const firstViewPage = s.flat.find(n => n._type === "page");
                        if (firstViewPage) return loadViewPage(root, cfg, firstViewPage._id);
                        return;
                    }
                    const requestedEditPage = Number(cfg && cfg.cid ? cfg.cid : 0) || 0;
                    if (requestedEditPage > 0) {
                        s.selectedPage = requestedEditPage;
                        s.selectedType = "page";
                        setSelectedPage(root, requestedEditPage);
                        setSelectedType(root, "page");
                        return loadPage(root, cfg, requestedEditPage).then(() => revealTreeSelection(root));
                    }
                    if (s.selectedType === "folder" && s.selectedFolder) {
                        const folder = findNode(root, "folder", s.selectedFolder);
                        if (folder) {
                            showFolderEditor(root, folder);
                            loadMedia(root, cfg);
                            revealTreeSelection(root);
                            return;
                        }
                    }
                    if (s.selectedPage > 0) {
                        return loadPage(root, cfg, s.selectedPage);
                    }
                    const firstPage = s.flat.find(n => n._type === "page");
                    if (firstPage) return loadPage(root, cfg, firstPage._id);
                }
            })
            .catch(err => {
                dbx.error("[cms] tree load failed", err);
                if (box) {
                    box.innerHTML = '<div class="dbx-cms-empty">Tree konnte nicht geladen werden.</div>';
                }
                status(root, "Tree konnte nicht geladen werden.", "error");
            });
    }

    function setSelectValues(select, value) {
        if (!select) return;
        const values = String(value || "").split(",").map(v => v.trim()).filter(Boolean);
        Array.from(select.options).forEach(option => { option.selected = values.includes(option.value); });
        syncCmsSelect(select);
        select.dispatchEvent(new Event("change", { bubbles: true }));
    }

    function getSelectValues(select) {
        if (!select) return "";
        return Array.from(select.selectedOptions).map(option => option.value).join(",");
    }

    function setFolderField(root, name, value) {
        const el = qs(root, `[data-cms-folder-field="${name}"]`);
        if (!el) return;
        el.value = value == null ? "" : String(value);
        syncCmsSelect(el);
        if (name === "hero_image_id") renderHeroPreview(root);
        if (name === "seo_image_id") renderSeoPreview(root);
    }

    function getFolderField(root, name) {
        const el = qs(root, `[data-cms-folder-field="${name}"]`);
        return el ? el.value : "";
    }

    function findNode(root, type, id) {
        const list = state(root).flat || [];
        return list.find(node => node._type === type && Number(node._id) === Number(id)) || null;
    }

    function buildParentOptions(root, currentId, selectedParent) {
        const s = state(root);
        const select = qs(root, '[data-cms-folder-field="parent_id"]');
        if (!select) return;

        const forbidden = new Set();
        function markForbidden(node) {
            if (!node || node._type !== "folder") return;
            forbidden.add(Number(node._id));
            (node._children || []).forEach(markForbidden);
        }
        if (currentId > 0) markForbidden(findNode(root, "folder", currentId));

        let html = '<option value="0">Root / erste Ebene</option>';
        function walk(nodes, depth) {
            (nodes || []).forEach(node => {
                if (node._type !== "folder") return;
                const id = Number(node._id || 0);
                if (!forbidden.has(id)) {
                    const pad = depth > 0 ? Array(depth + 1).join("-- ") : "";
                    html += `<option value="${escapeHtml(id)}">${escapeHtml(pad + (node._title || "Ordner " + id))}</option>`;
                }
                walk(node._children || [], depth + 1);
            });
        }
        walk(s.tree || [], 0);
        select.innerHTML = html;
        select.value = String(selectedParent || 0);
        syncCmsSelect(select);
    }

    function showFolderEditor(root, folder, anchor) {
        const panel = qs(root, "[data-cms-folder-editor]");
        const title = qs(root, "[data-cms-folder-title]");
        const rights = qs(root, "[data-cms-folder-rights]");
        const s = state(root);

        s.folder = folder || null;
        if (!panel || !folder) return;
        s.mediaRows = [];
        s.heroPreviewRow = null;
        root.classList.add("is-folder-editing");
        updateHeaderActionTooltips(root);

        const id = Number(folder._id || folder.id || 0);
        const parentId = Number(folder._parent ?? folder.parent_id ?? s.selectedFolder ?? 0);
        const name = folder._title || folder.name || "";
        const template = folder._template || folder.template || "";
        const folderRights = folder._rights || folder.group_read || (parentId > 0 ? "parent" : "*");
        const settingFields = ["hero_template", "hero_image_id", "hero_margin_top", "hero_height", "hero_variant", "hero_sticky", "hero_scroll_layer"];
        updateCurrentSelectionTitle(root, "folder", id, name);

        panel.hidden = false;
        panel.classList.toggle("is-new", id <= 0);
        if (title) title.textContent = id > 0 ? "Ordner bearbeiten" : "Neuen Ordner anlegen";
        setFolderField(root, "id", id);
        setFolderField(root, "name", name);
        buildParentOptions(root, id, parentId);
        setFolderField(root, "template", template);
        setSelectValues(rights, folderRights);
        settingFields.forEach(key => {
            const value = folder["_" + key] || folder[key] || "parent";
            setFolderField(root, key, value);
            setField(root, key, value);
        });
        renderHeroPreview(root);
        const deleteButton = qs(panel, "[data-cms-folder-delete]");
        if (deleteButton) deleteButton.hidden = id <= 0;

        panel.style.removeProperty("left");
        panel.style.removeProperty("top");

        if (typeof window.multiselect2 === "function") {
            window.multiselect2(rights && (rights.id || rights.name) ? (rights.id || rights.name) : "group_read");
        }

        const first = qs(panel, '[data-cms-folder-field="name"]');
        if (first) window.setTimeout(() => first.focus(), 20);
    }

    function hideFolderEditor(root) {
        const panel = qs(root, "[data-cms-folder-editor]");
        if (panel) panel.hidden = true;
        root.classList.remove("is-folder-editing");
        updateHeaderActionTooltips(root);
        const pageTitle = getField(root, "title");
        updateCurrentSelectionTitle(root, "page", getField(root, "id"), pageTitle);
    }

    function saveFolder(root, cfg) {
        const s = state(root);
        const folder = s.folder;
        const url = cfgUrl(cfg, "savefolder");
        if (s.saving) return Promise.resolve();
        if (!folder || !url) {
            status(root, cmsText(root, "folder_select_first", "Bitte zuerst einen Ordner im Baum wählen."), "error");
            return;
        }

        const id = Number(getFolderField(root, "id") || 0);
        const name = getFolderField(root, "name").trim();
        if (!name) {
            status(root, cmsText(root, "folder_name_required", "Bitte eine Ordner-Bezeichnung eintragen."), "error");
            return;
        }

        const rights = getSelectValues(qs(root, "[data-cms-folder-rights]"));
        setSaving(root, true);
        return fetchJson(apiUrl(url, cmsLngParams(root)), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id,
                name,
                parent_id: Number(getFolderField(root, "parent_id") || 0),
                template: getFolderField(root, "template"),
                group_read: rights,
                hero_template: getField(root, "hero_template") || getFolderField(root, "hero_template") || "parent",
                hero_image_id: getField(root, "hero_image_id") || getFolderField(root, "hero_image_id") || "parent",
                hero_margin_top: getField(root, "hero_margin_top") || getFolderField(root, "hero_margin_top") || "parent",
                hero_height: getField(root, "hero_height") || getFolderField(root, "hero_height") || "parent",
                hero_variant: getField(root, "hero_variant") || getFolderField(root, "hero_variant") || "parent",
                hero_sticky: getField(root, "hero_sticky") || getFolderField(root, "hero_sticky") || "parent",
                hero_scroll_layer: getField(root, "hero_scroll_layer") || getFolderField(root, "hero_scroll_layer") || "parent",
            })
        }).then(data => {
            if (!data || !data.ok) throw new Error("folder save failed");
            const synced = Number(data.lng_synced || 0);
            const saveStatus = formatLngSaveStatus(
                cmsText(root, "folder_saved", "Ordner gespeichert."),
                data
            );
            status(root, saveStatus.text, saveStatus.type);
            clearDirtyAfterSave(root);
            setSelectedFolder(root, data.id || id || 0);
            setSelectedType(root, "folder");
            return loadTree(root, cfg).then(() => {
                const fresh = findNode(root, "folder", data.id || id);
                if (fresh) {
                    const mediaRows = (s.mediaRows || []).slice();
                    const heroPreviewRow = s.heroPreviewRow || null;
                    showFolderEditor(root, fresh);
                    s.mediaRows = mediaRows;
                    s.heroPreviewRow = heroPreviewRow;
                    renderHeroPreview(root);
                    renderMedia(root);
                    revealTreeSelection(root);
                }
                maybeOpenLngProvisionAfterCreate(root, cfg, data);
            });
        }).catch(err => {
            dbx.error("[cms] folder save failed", err);
            status(root, cmsText(root, "folder_save_error", "Ordner konnte nicht gespeichert werden."), "error");
        }).finally(() => {
            setSaving(root, false);
            clearCmsLoading(root);
        });
    }

    function deleteFolder(root, cfg) {
        const id = Number(getFolderField(root, "id") || 0);
        const name = getFolderField(root, "name") || "Ordner";
        const url = cfgUrl(cfg, "deletefolder");
        if (!id || !url) {
            status(root, "Nur gespeicherte Ordner koennen geloescht werden.", "error");
            return;
        }

        return openLngDeleteDialog(root, cfg, "folder", id, name).catch(err => {
            const msg = err && err.message ? err.message : cmsText(root, "folder_delete_error", "Ordner konnte nicht gelöscht werden.");
            dbx.error("[cms] folder delete failed", err);
            status(root, msg, "error");
        });
    }

    function moveNode(root, cfg, type, id, targetFolder, position) {
        const url = cfgUrl(cfg, "movenode");
        if (!url || !type || !id || targetFolder < 0) return Promise.resolve();

        const payload = {
            type,
            id,
            target_folder: targetFolder
        };
        if (position && position.before_id) payload.before_id = position.before_id;
        if (position && position.after_id) payload.after_id = position.after_id;

        return fetchJson(apiUrl(url), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        }).then(data => {
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "move failed");
            status(root, "Tree-Eintrag verschoben.", "success");
            return loadTree(root, cfg);
        }).catch(err => {
            dbx.error("[cms] move node failed", err);
            status(root, err && err.message ? err.message : "Tree-Eintrag konnte nicht verschoben werden.", "error");
        });
    }

    function syncSelectTitle(el) {
        if (!el || el.tagName !== "SELECT") return;
        const selected = el.selectedOptions && el.selectedOptions.length ? el.selectedOptions[0] : null;
        el.title = selected ? selected.textContent.trim() : "";
    }

    function selectedOptionText(select) {
        const selected = select && select.selectedOptions && select.selectedOptions.length ? select.selectedOptions[0] : null;
        return selected ? selected.textContent.trim() : "";
    }

    function syncCmsSelect(select) {
        syncSelectTitle(select);
        if (select && typeof select.__dbxCmsSelectRender === "function") {
            select.__dbxCmsSelectRender();
        }
        const root = closestElement(select, ".dbx-cms");
        if (root) syncContentTemplateEditLink(root, select);
    }

    function contentTemplateEditorUrl(template) {
        template = String(template || "").trim();
        if (!/^c-[A-Za-z0-9][A-Za-z0-9_-]*$/.test(template)) return "";

        const file = "dbx/modules/dbxContent/tpl/htm/" + template + ".htm";
        return "?dbx_modul=dbxEditor&dbx_run1=edit&file=" + encodeURIComponent(file) + "&dbx_window=1";
    }

    function syncContentTemplateEditLink(root, select) {
        if (!root || !select) return;
        const field = select.getAttribute("data-cms-field") || select.getAttribute("data-cms-folder-field") || "";
        if (field !== "template") return;

        const wrapper = closestElement(select, ".dbx-cms-field-template");
        const link = qs(wrapper, "[data-cms-content-template-edit]");
        if (!link) return;

        const template = String(select.value || "").trim();
        const url = contentTemplateEditorUrl(template);
        const enabled = url !== "";

        link.setAttribute("href", enabled ? url : "#");
        link.setAttribute("data-url", enabled ? url : "");
        link.setAttribute("data-title", enabled ? "Content-Template bearbeiten: " + template : "Content-Template bearbeiten");
        link.setAttribute("aria-disabled", enabled ? "false" : "true");
        link.setAttribute("tabindex", enabled ? "0" : "-1");
        link.setAttribute("title", enabled
            ? template + " im ACE-Template-Editor bearbeiten"
            : "Zuerst ein c-* Content-Template auswaehlen");
        link.classList.toggle("disabled", !enabled);
    }

    function openContentTemplateEditor(root, link) {
        const wrapper = closestElement(link, ".dbx-cms-field-template");
        const select = qs(wrapper, 'select[data-cms-field="template"], select[data-cms-folder-field="template"]');
        const template = String(select && select.value || "").trim();
        const url = contentTemplateEditorUrl(template);

        if (!select || !url) {
            if (select) syncContentTemplateEditLink(root, select);
            status(root, "Bitte zuerst ein c-* Content-Template auswaehlen.", "error");
            return;
        }

        if (link.__dbxCmsTemplateConfirming) return;
        link.__dbxCmsTemplateConfirming = true;

        ensureConfirm().then(ok => {
            if (!ok) throw new Error("confirm.js nicht geladen.");

            return dbx.confirm.open({
                id: "dbx-cms-content-template-edit",
                root: root,
                title: '<i class="bi bi-pencil-square"></i> Content-Template bearbeiten',
                question: "Das Content-Template <strong>" + escapeHtml(template) + "</strong> wirklich bearbeiten?",
                hint: "<strong>Achtung:</strong> Eine Aenderung betrifft jede Seite, die dieses Content-Template verwendet.",
                buttons: "yesno",
                labelyes: '<i class="bi bi-pencil-square"></i> Ja, bearbeiten',
                labelno: "Abbrechen"
            });
        }).then(result => {
            if (!result || result.action !== "yes") return null;

            return Promise.all([ensureAjax(), ensureOpenWin()]).then(ready => {
                if (!ready[0]) throw new Error("ajax.js nicht geladen.");
                if (!ready[1]) throw new Error("openWin.js nicht geladen.");

                return dbx.openWin.open({
                    url: url,
                    title: '<i class="bi bi-pencil-square"></i> Content-Template bearbeiten: ' + escapeHtml(template),
                    width: "90%",
                    height: "88%",
                    position: "center",
                    scroll: 1,
                    resizable: 1,
                    minimizable: 1,
                    maximizable: 1,
                    reloadable: 1,
                    reload: 1,
                    persist: 0,
                    reuse: 1
                }, link);
            });
        }).catch(err => {
            dbx.error("[cms] content template editor failed", err);
            status(root, err && err.message ? err.message : "Content-Template-Editor konnte nicht geoeffnet werden.", "error");
        }).finally(() => {
            link.__dbxCmsTemplateConfirming = false;
        });
    }

    function syncDetailsToggleIcon(details) {
        const icon = qs(details, ".dbx-cms-toggle-icon:not(.dbx-cms-toggle-icon-open):not(.dbx-cms-toggle-icon-closed)");
        if (icon) {
            icon.classList.toggle("bi-chevron-down", !!details.open);
            icon.classList.toggle("bi-chevron-right", !details.open);
        }
        const openIcon = qs(details, ".dbx-cms-toggle-icon-open");
        const closedIcon = qs(details, ".dbx-cms-toggle-icon-closed");
        if (openIcon) openIcon.style.display = details.open ? "inline-block" : "none";
        if (closedIcon) closedIcon.style.display = details.open ? "none" : "inline-block";
    }

    function initDetailsToggles(root) {
        const saved = dbx.uiGet ? dbx.uiGet(LIB, PANEL_UI_ID, "openPanels", {}) : {};
        const openPanels = (saved && typeof saved === "object" && !Array.isArray(saved)) ? saved : {};
        qsa(root, ".dbx-cms-page-panel, .dbx-cms-settings-panel").forEach((details, index) => {
            const key = details.getAttribute("data-cms-ui-state") || ("panel-" + index);
            details.setAttribute("data-cms-ui-state", key);
            if (Object.prototype.hasOwnProperty.call(openPanels, key)) {
                details.open = !!openPanels[key];
            }
            syncDetailsToggleIcon(details);
            if (details.__dbxCmsToggleReady) return;
            details.__dbxCmsToggleReady = true;
            details.addEventListener("toggle", () => {
                syncDetailsToggleIcon(details);
                const state = dbx.uiGet ? dbx.uiGet(LIB, PANEL_UI_ID, "openPanels", {}) : {};
                const next = (state && typeof state === "object" && !Array.isArray(state)) ? state : {};
                next[key] = !!details.open;
                if (dbx.uiSet) dbx.uiSet(LIB, PANEL_UI_ID, "openPanels", next);
            });
        });
    }

    function closeCmsSelects(root, except) {
        qsa(root, ".dbx-cms-select.is-open").forEach(wrapper => {
            if (wrapper === except) return;
            wrapper.classList.remove("is-open");
            const button = qs(wrapper, ".dbx-cms-select-control");
            const menu = qs(wrapper, ".dbx-cms-select-menu");
            if (button) button.setAttribute("aria-expanded", "false");
            if (menu) menu.hidden = true;
        });
    }

    function buildCmsSelect(root, select) {
        if (!select || select.multiple || select.dataset.dbxCmsSelectReady === "1") return;

        select.dataset.dbxCmsSelectReady = "1";
        select.classList.add("dbx-cms-select-source");

        const wrapper = document.createElement("div");
        wrapper.className = "dbx-cms-select";

        const button = document.createElement("button");
        button.type = "button";
        button.className = "dbx-cms-select-control";
        button.setAttribute("aria-haspopup", "listbox");
        button.setAttribute("aria-expanded", "false");

        const value = document.createElement("span");
        value.className = "dbx-cms-select-value";

        const icon = document.createElement("i");
        icon.className = "bi bi-chevron-down";
        icon.setAttribute("aria-hidden", "true");

        const menu = document.createElement("div");
        menu.className = "dbx-cms-select-menu";
        menu.setAttribute("role", "listbox");
        menu.hidden = true;

        button.appendChild(value);
        button.appendChild(icon);
        wrapper.appendChild(button);
        wrapper.appendChild(menu);
        select.insertAdjacentElement("afterend", wrapper);

        function open() {
            closeCmsSelects(root, wrapper);
            wrapper.classList.add("is-open");
            button.setAttribute("aria-expanded", "true");
            menu.hidden = false;
            render();
        }

        function close() {
            wrapper.classList.remove("is-open");
            button.setAttribute("aria-expanded", "false");
            menu.hidden = true;
        }

        function render() {
            const text = selectedOptionText(select) || cmsText(root, "selection_label", "Auswahl");
            value.textContent = text;
            button.title = text;
            button.disabled = select.disabled;
            menu.innerHTML = "";

            Array.from(select.options).forEach(option => {
                if (option.disabled) return;
                const row = document.createElement("button");
                row.type = "button";
                row.className = "dbx-cms-select-option";
                row.dataset.value = option.value;
                row.setAttribute("role", "option");
                row.setAttribute("aria-selected", option.selected ? "true" : "false");
                if (option.selected) row.classList.add("is-selected");

                const label = document.createElement("span");
                label.textContent = option.textContent.trim();
                const check = document.createElement("i");
                check.className = option.selected ? "bi bi-check2" : "bi";
                check.setAttribute("aria-hidden", "true");
                row.appendChild(label);
                row.appendChild(check);

                row.addEventListener("click", event => {
                    event.preventDefault();
                    event.stopPropagation();
                    select.value = option.value;
                    syncSelectTitle(select);
                    select.dispatchEvent(new Event("input", { bubbles: true }));
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                    render();
                    close();
                    button.focus();
                });

                menu.appendChild(row);
            });

            syncSelectTitle(select);
        }

        button.addEventListener("click", event => {
            event.preventDefault();
            event.stopPropagation();
            if (wrapper.classList.contains("is-open")) close();
            else open();
        });
        button.addEventListener("keydown", event => {
            if (event.key === "Escape") {
                close();
                return;
            }
            if (event.key === "Enter" || event.key === " " || event.key === "ArrowDown") {
                event.preventDefault();
                open();
            }
        });
        select.addEventListener("change", render);
        select.__dbxCmsSelectRender = render;
        render();
    }

    function initCmsSelects(root) {
        qsa(root, "select[data-cms-field]:not([multiple]), select[data-cms-folder-field]:not([multiple])").forEach(select => {
            buildCmsSelect(root, select);
            syncContentTemplateEditLink(root, select);
        });
    }

    function cmsFieldValue(value) {
        return value == null ? "" : value;
    }

    function setField(root, name, value) {
        const el = qs(root, `[data-cms-field="${name}"]`);
        if (!el) return;
        if (el.multiple) {
            const values = String(value == null ? "" : value).split(",").map(v => v.trim()).filter(Boolean);
            Array.from(el.options).forEach(opt => { opt.selected = values.includes(opt.value); });
            syncCmsSelect(el);
            return;
        }
        el.value = value == null ? "" : String(value);
        syncCmsSelect(el);
    }

    function getField(root, name) {
        const el = qs(root, `[data-cms-field="${name}"]`);
        if (!el) return "";
        if (el.multiple) {
            return Array.from(el.selectedOptions).map(opt => opt.value).join(",");
        }
        return el.value;
    }

    function loadViewPage(root, cfg, id, opt) {
        opt = opt || {};
        const s = state(root);
        const url = cfgUrl(cfg, "viewpage");
        const box = qs(root, "[data-cms-content-view]");
        if (!url || !id) return Promise.resolve();
        if (box) box.innerHTML = '<div class="dbx-cms-empty">' + escapeHtml(cmsText(root, "page_loading", "Seite wird geladen...")) + '</div>';

        return fetchJson(apiUrl(url, { id }), { footerRuntime: opt.footerRuntime || "" })
            .then(data => {
                if (!data || !data.ok) throw new Error("bad response");
                const pageId = Number(data.id || id);
                s.page = { id: pageId, title: data.title || "" };
                setSelectedPage(root, pageId);
                setSelectedType(root, "page");
                if (box) box.innerHTML = data.html || '<div class="dbx-cms-empty">Keine Ansicht vorhanden.</div>';
                updateViewPageTitle(root, data.title || "");
                revealTreeSelection(root);
            })
            .catch(err => {
                dbx.error("[cms] content view load failed", err);
                if (box) box.innerHTML = '<div class="dbx-cms-empty">' + escapeHtml(cmsText(root, "page_load_error", "Seite konnte nicht geladen werden.")) + '</div>';
                status(root, cmsText(root, "page_load_error", "Seite konnte nicht geladen werden."), "error");
            });
    }

    function loadPage(root, cfg, id) {
        const s = state(root);
        const url = cfgUrl(cfg, "page");
        const pageId = Number(id || 0);
        if (!url || pageId <= 0) return Promise.resolve();

        const loadSeq = Number(s.pageLoadSeq || 0) + 1;
        s.pageLoadSeq = loadSeq;
        s.loading = true;

        return fetchJson(apiUrl(url, Object.assign({ id: pageId }, cmsLngParams(root))))
            .then(data => {
                if (loadSeq !== s.pageLoadSeq) return;
                if (!data || !data.ok) throw new Error("bad response");
                const row = data.row || {};
                s.page = row;
                hideFolderEditor(root);
                s.mediaRows = [];
                s.heroPreviewRow = data.hero_preview_media || null;
                s.heroParentPreviewRow = data.hero_parent_preview_media || null;
                s.seoPreviewRow = data.seo_preview_media || null;
                setSelectedPage(root, row.id || pageId);
                setSelectedFolder(root, row.folder || 0);
                setSelectedType(root, "page");

                ["id", "folder", "title", "permalink", "description", "keywords", "template", "activ", "hero_template", "hero_image_id", "hero_margin_top", "hero_height", "hero_variant", "hero_sticky", "hero_scroll_layer", "gallery_template", "gallery_visible_count", "gallery_image_size", "gallery_lightbox_width", "gallery_overflow", "gallery_click_behavior"].forEach(key => {
                    setField(root, key, cmsFieldValue(row[key]));
                });

                try {
                    setEditorHtml(root, row.content || "");
                } catch (err) {
                    dbx.error("[cms] editor update failed", err);
                }

                updateCurrentSelectionTitle(root, "page", row.id || pageId, row.title || "Unbenannte Seite");

                revealTreeSelection(root);
                renderMedia(root, data.media || []);
                renderSeoPreview(root);
                setDirty(root, false);
                s.loading = false;
            })
            .catch(err => {
                if (loadSeq !== s.pageLoadSeq) return;
                s.loading = false;
                dbx.error("[cms] page load failed", err);
                status(root, cmsText(root, "page_load_error", "Seite konnte nicht geladen werden."), "error");
            });
    }

    function selectTreePage(root, cfg, id) {
        const pageId = Number(id || 0);
        if (!pageId) return Promise.resolve();
        status(root, cmsText(root, "page_loading", "Seite wird geladen..."), "info");
        closeTreePanel(root);
        return loadPage(root, cfg, pageId);
    }

    function selectTreeFolder(root, cfg, row, options) {
        if (!row || !root.contains(row) || row.getAttribute("data-type") !== "folder") return;
        const id = Number(row.getAttribute("data-id") || 0);
        if (!id) return;
        setSelectedFolder(root, id);
        setSelectedType(root, "folder");
        renderTree(root);
        if (!options || !options.silent) {
            status(root, cmsText(root, "folder_selected", "Ordner gewählt."), "info");
        }
    }

    function openFolderEditorFromRow(root, cfg, row) {
        if (!row || !root.contains(row) || row.getAttribute("data-type") !== "folder") return;
        const id = Number(row.getAttribute("data-id") || 0);
        const folder = findNode(root, "folder", id);
        if (!folder) return;
        setSelectedFolder(root, id);
        setSelectedType(root, "folder");
        showFolderEditor(root, folder, row);
        loadMedia(root, cfg);
        revealTreeSelection(root);
        status(root, cmsText(root, "folder_edit", "Ordner bearbeiten."), "info");
        closeTreePanel(root);
    }

    function activateTreeRow(root, cfg, row) {
        if (!row || !root.contains(row)) return;
        const type = row.getAttribute("data-type");
        const id = Number(row.getAttribute("data-id") || 0);
        const stamp = String(type || "") + ":" + String(id || 0);
        const s = state(root);
        const now = Date.now();
        if (s.lastTreeActivate === stamp && now - Number(s.lastTreeActivateAt || 0) < 350) {
            return;
        }
        s.lastTreeActivate = stamp;
        s.lastTreeActivateAt = now;

        if (isViewMode(cfg)) {
            if (type === "page") {
                loadViewPage(root, cfg, id, { footerRuntime: "visible" }).finally(() => {
                    applyTreePanelState(root, true);
                    forceCollapseTreePanel(root);
                    if (dbx.uiSet) dbx.uiSet(LIB, PANEL_UI_ID, "treeCollapsed", true);
                });
            }
            return;
        }
        if (type === "folder") {
            selectTreeFolder(root, cfg, row, { silent: true });
            return;
        }
        if (type === "page") {
            selectTreePage(root, cfg, id);
        }
    }

    function loadMedia(root, cfg) {
        const url = cfgUrl(cfg, "media");
        const pageId = Number(getField(root, "id") || 0);
        const folderId = root.classList.contains("is-folder-editing") ? Number(getFolderField(root, "id") || 0) : 0;
        if (!url || (!pageId && !folderId)) return Promise.resolve();

        const params = folderId > 0 ? { folder_id: folderId, usage: 1 } : { content_id: pageId, usage: 1 };
        return fetchJson(apiUrl(url, params))
            .then(data => {
                if (!data || !data.ok) throw new Error("bad response");
                renderMedia(root, data.rows || []);
            })
            .catch(err => {
                dbx.warn("[cms] media refresh failed", err);
            });
    }

    function reloadCms(root, cfg) {
        const s = state(root);
        const wasDirty = !!s.dirty;
        const selectedType = root.classList.contains("is-folder-editing") || s.selectedType === "folder"
            ? "folder"
            : "page";
        const folderId = selectedType === "folder"
            ? Number(getFolderField(root, "id") || s.selectedFolder || 0)
            : 0;
        const pageId = selectedType === "page"
            ? Number(getField(root, "id") || s.selectedPage || 0)
            : 0;
        const finish = () => {
            setDirty(root, false);
            status(
                root,
                wasDirty
                    ? "Neu geladen. Nicht gespeicherte Änderungen wurden verworfen."
                    : "CMS neu geladen.",
                "success"
            );
        };

        status(root, "CMS wird neu geladen...", "info");
        s.selectionRestored = true;

        return loadTree(root, cfg).then(() => {
            if (selectedType === "folder" && folderId > 0) {
                const folder = findNode(root, "folder", folderId);
                if (folder) {
                    setSelectedFolder(root, folderId);
                    setSelectedType(root, "folder");
                    showFolderEditor(root, folder);
                    revealTreeSelection(root);
                    return loadMedia(root, cfg).then(finish);
                }
            }

            const currentPageId = pageId > 0 && s.flat.some(node => (
                node._type === "page" && Number(node._id) === pageId
            )) ? pageId : 0;
            const firstPage = currentPageId ? null : s.flat.find(node => node._type === "page");
            const reloadPageId = currentPageId || Number(firstPage && firstPage._id || 0);

            if (reloadPageId > 0) {
                return loadPage(root, cfg, reloadPageId).then(finish);
            }

            hideFolderEditor(root);
            renderMedia(root, []);
            finish();
        }).catch(err => {
            dbx.error("[cms] reload failed", err);
            status(root, "CMS konnte nicht neu geladen werden.", "error");
        });
    }

    function collectPage(root) {
        const html = serializeCmsMarkers(getEditorHtml(root));
        setField(root, "content", html);

        return {
            id: Number(getField(root, "id") || 0),
            folder: Number(getField(root, "folder") || 0),
            title: getField(root, "title"),
            permalink: getField(root, "permalink"),
            description: getField(root, "description"),
            keywords: getField(root, "keywords"),
            template: getField(root, "template"),
            hero_template: getField(root, "hero_template") || "parent",
            hero_image_id: getField(root, "hero_image_id") || "parent",
            hero_margin_top: getField(root, "hero_margin_top") || "parent",
            hero_height: getField(root, "hero_height") || "parent",
            hero_variant: getField(root, "hero_variant") || "parent",
            hero_sticky: getField(root, "hero_sticky") || "parent",
            hero_scroll_layer: getField(root, "hero_scroll_layer") || "parent",
            gallery_template: "image-gallery",
            gallery_visible_count: "3",
            gallery_image_size: getField(root, "gallery_image_size") || "original",
            gallery_lightbox_width: getField(root, "gallery_lightbox_width") || "100vw",
            gallery_overflow: getField(root, "gallery_overflow") || "grid",
            gallery_click_behavior: getField(root, "gallery_click_behavior") || "lightbox",
            activ: getField(root, "activ"),
            content: html,
            inline_media_ids: collectInlineMediaIdsFromEditor(root)
        };
    }

    function collectInlineMediaIdsFromEditor(root) {
        const surface = editorSurface(root);
        const ids = new Set();
        const registerId = (value) => {
            const id = Number(value || 0);
            if (id > 0) ids.add(id);
        };
        const registerNode = (node) => {
            if (!node || !node.getAttribute) return;
            registerId(node.getAttribute("data-cms-media-id"));
            const src = String(node.getAttribute("src") || "");
            const match = src.match(/dbx_mid=([0-9]+)/i);
            if (match) registerId(match[1]);
        };

        if (surface) {
            qsa(surface, ".dbx-cms-inline-media[data-cms-media-id], figure.dbx-cms-inline-video-block[data-cms-media-id]").forEach(wrap => {
                if (!inlineMediaWrapperHasContent(wrap)) return;
                if (qs(wrap, "img[src*='dbx_mid=']")) return;
                registerNode(wrap);
            });
            qsa(surface, "img[data-cms-media-id], video[data-cms-media-id], iframe[data-cms-media-id]").forEach(registerNode);
            qsa(surface, "img[src*='dbx_mid=']").forEach(registerNode);
        }

        return Array.from(ids).filter(Boolean);
    }

    function inlineMediaIds(html) {
        const ids = new Set();
        String(html || "").replace(/dbx_mid=([0-9]+)/gi, (_, id) => {
            ids.add(Number(id));
            return "";
        });
        String(html || "").replace(/data-cms-media-id=["']?([0-9]+)/gi, (_, id) => {
            ids.add(Number(id));
            return "";
        });
        return Array.from(ids).filter(Boolean);
    }

    function removeInlineMediaFromEditor(root, id) {
        id = Number(id || 0);
        if (!id) return false;

        const html = getEditorHtml(root);
        const doc = new DOMParser().parseFromString('<div data-root>' + String(html || "") + '</div>', "text/html");
        const wrap = doc.querySelector("[data-root]");
        if (!wrap) return false;

        let changed = false;
        const mediaNodes = Array.from(wrap.querySelectorAll("[data-cms-media-id], img, video, iframe, source"));
        mediaNodes.forEach(node => {
            if (!node || !node.getAttribute) return;

            const dataId = Number(node.getAttribute("data-cms-media-id") || 0);
            const src = node.getAttribute("src") || node.getAttribute("href") || "";
            const srcMatch = new RegExp("(?:dbx_mid=|data-cms-media-id=)" + id + "(?:[^0-9]|$)", "i").test(src);
            if (dataId !== id && !srcMatch) return;

            const target = node.closest(".dbx-cms-inline-media, .dbx-cms-inline-media-missing-wrap, figure") || node;
            if (target && target.parentNode) {
                target.parentNode.removeChild(target);
                changed = true;
            }
        });

        if (!changed) return false;

        setEditorHtml(root, wrap.innerHTML);
        markDirty(root);
        return true;
    }

    function savePage(root, cfg) {
        const url = cfgUrl(cfg, "save");
        const s = state(root);
        if (!url || s.saving) return Promise.resolve();
        let saveCommitted = false;
        let committedStatus = cmsText(root, "page_saved", "Seite gespeichert.");

        setSaving(root, true);

        return fetchJson(apiUrl(url, cmsLngParams(root)), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(collectPage(root)),
            timeout: 60000
        }).then(data => {
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "save failed");
            saveCommitted = true;
            const row = data.row || {};
            if (row && row.id) {
                s.loading = true;
                suppressDirtyFor(root, 250);
                ["id", "folder", "title", "permalink", "description", "keywords", "template", "activ", "hero_template", "hero_image_id", "hero_margin_top", "hero_height", "hero_variant", "hero_sticky", "hero_scroll_layer", "gallery_template", "gallery_visible_count", "gallery_image_size", "gallery_lightbox_width", "gallery_overflow", "gallery_click_behavior"].forEach(key => {
                    if (row[key] !== undefined) setField(root, key, cmsFieldValue(row[key]));
                });
                setSelectedPage(root, row.id || data.id || 0);
                setSelectedFolder(root, row.folder || 0);
                setSelectedType(root, "page");
                s.page = row;
                updateCurrentSelectionTitle(root, "page", row.id || data.id || 0, row.title || "Unbenannte Seite");
                if (row.content !== undefined) {
                    try {
                        setEditorHtml(root, row.content || "");
                    } catch (err) {
                        dbx.warn("[cms] editor sync after save failed", err);
                    }
                }
                s.loading = false;
            }
            s.heroPreviewRow = data.hero_preview_media || s.heroPreviewRow || null;
            s.heroParentPreviewRow = data.hero_parent_preview_media || s.heroParentPreviewRow || null;
            s.seoPreviewRow = data.seo_preview_media || s.seoPreviewRow || null;
            if (Array.isArray(data.media)) renderMedia(root, data.media);
            renderHeroPreview(root);
            renderSeoPreview(root);
            const saveStatus = formatLngSaveStatus(cmsText(root, "page_saved", "Seite gespeichert."), data);
            committedStatus = saveStatus.text;
            status(root, saveStatus.text, saveStatus.type);
            clearDirtyAfterSave(root);
            clearCmsLoading(root);
            return loadMedia(root, cfg)
                .catch(err => {
                    dbx.warn("[cms] media refresh after save failed", err);
                })
                .then(() => loadTree(root, cfg))
                .catch(err => {
                    dbx.warn("[cms] refresh after save failed", err);
                    status(root, "Seite gespeichert. Ansicht konnte nicht aktualisiert werden.", "success");
                })
                .then(() => {
                    revealTreeSelection(root);
                    maybeOpenLngProvisionAfterCreate(root, cfg, data);
                    return data;
                });
        }).catch(err => {
            s.loading = false;
            if (saveCommitted) {
                dbx.warn("[cms] post-save refresh failed", err);
                status(root, committedStatus + " Ansicht konnte nicht vollstaendig aktualisiert werden.", "success");
                clearDirtyAfterSave(root);
            } else {
                dbx.error("[cms] save failed", err);
                status(root, err && err.message ? err.message : cmsText(root, "page_save_error", "Seite konnte nicht gespeichert werden."), "error");
            }
        }).finally(() => {
            setSaving(root, false);
            clearCmsLoading(root);
        });
    }

    function deletePage(root, cfg) {
        const id = Number(getField(root, "id") || 0);
        const title = getField(root, "title") || "Seite";
        const url = cfgUrl(cfg, "deletepage");
        if (!id || !url) {
            status(root, cmsText(root, "page_select_first", "Bitte zuerst eine Seite auswählen."), "error");
            return Promise.resolve();
        }

        return openLngDeleteDialog(root, cfg, "page", id, title).catch(err => {
            dbx.error("[cms] page delete failed", err);
            status(root, err && err.message ? err.message : cmsText(root, "page_delete_error", "Seite konnte nicht gelöscht werden."), "error");
        });
    }

    function mediaSlotMatchesBox(boxSlot, slot, mediaFilter) {
        if (boxSlot === "gallery") {
            // Shop media belongs to one concrete CMS document and is managed
            // alongside its regular gallery media in that document's sidebar.
            return slot === "gallery" || slot === "shop";
        }
        if (boxSlot === "inline" || boxSlot === "shop") return slot === boxSlot;
        if (boxSlot === "all" && mediaFilter !== "all" && slot !== mediaFilter) return false;
        if (slot === "hero" && boxSlot !== "all") return false;
        return true;
    }

    function renderMedia(root, rows) {
        const boxes = qsa(root, "[data-cms-media]");
        if (!boxes.length) return;
        const s = state(root);
        if (Array.isArray(rows)) s.mediaRows = rows.slice();
        rows = s.mediaRows || [];
        renderHeroPreview(root);
        renderSeoPreview(root);

        syncUploadSlot(root);
        const prefixes = { hero: "h", gallery: "g", inline: "i", shop: "s" };
        const mediaFilter = String(qs(root, "[data-cms-media-filter]")?.value || state(root).mediaFilter || "all");

        boxes.forEach(box => {
            const boxSlot = String(box.getAttribute("data-cms-media") || "all");
            const visible = (Array.isArray(rows) ? rows : []).filter(row => {
                const slot = String(row.slot || "").trim();
                return mediaSlotMatchesBox(boxSlot, slot, mediaFilter);
            });

            if (!visible.length) {
                const empty = boxSlot === "gallery"
                    ? cmsText(root, "media_gallery_empty", "Keine Medien in der Galerie.")
                    : (boxSlot === "inline"
                        ? cmsText(root, "media_inline_empty", "Keine Medien im Text.")
                        : (mediaFilter === "hero"
                            ? cmsText(root, "media_hero_empty", "Kein Hero-Medium zugeordnet.")
                            : cmsText(root, "media_area_empty", "Keine Medien in diesem Bereich.")));
                box.innerHTML = `<div class="dbx-cms-empty">${empty}</div>`;
                return;
            }

            const counters = {};
            box.innerHTML = visible.map(row => {
                const preview = mediaPreviewHtml(row);
                const canEmbed = !!row.url;
                const slot = String(row.slot || "inline");
                counters[slot] = (counters[slot] || 0) + 1;
                const badge = (prefixes[slot] || "m") + counters[slot];
                const originLabel = mediaOriginLabel(row);
                return `<div class="dbx-cms-media-item" draggable="true" data-media-id="${escapeHtml(row.id || "")}" data-usage-id="${escapeHtml(row.usage_id || row.current_usage_id || "")}" data-media-slot="${escapeHtml(slot)}" data-media-folder="${escapeHtml(row.media_folder || "")}" data-url="${escapeHtml(row.url || "")}" data-thumb-url="${escapeHtml(row.thumb_url || "")}" data-mime="${escapeHtml(row.mime || "")}" data-media-type="${escapeHtml(row.media_type || "")}" data-file-name="${escapeHtml(row.file_name || "")}" data-file-path="${escapeHtml(row.file_path || "")}" data-title="${escapeHtml(row.title || "")}" data-alt="${escapeHtml(row.alt || "")}" data-width="${escapeHtml(row.width || "")}" data-height="${escapeHtml(row.height || "")}">
                <span class="dbx-cms-media-preview"><span class="dbx-cms-media-badge">${escapeHtml(badge)}</span>${preview}</span>
                <span class="dbx-cms-media-meta">
                    <span class="dbx-cms-media-name">${escapeHtml(row.title || row.file_name || "Medium")}</span>
                    <span class="dbx-cms-media-slot">${escapeHtml(originLabel)}</span>
                </span>
                <span class="dbx-cms-media-actions">
                    ${canEmbed ? '<button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-embed title="Medium direkt in den Editor einfuegen"><i class="bi bi-image"></i></button>' : ''}
                    <button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-remove title="Zuordnung aus dieser Seite entfernen"><i class="bi bi-trash"></i></button>
                </span>
            </div>`;
            }).join("");
            setupMediaLazyImages(box);
        });
    }

    function removeMedia(root, cfg, id, usageId, slot) {
        const url = cfgUrl(cfg, "removemedia");
        if (!url || (!id && !usageId)) return Promise.resolve();
        const pageId = Number(getField(root, "id") || 0);
        const folderId = root.classList.contains("is-folder-editing")
            ? Number(getFolderField(root, "id") || 0)
            : Number(getField(root, "folder") || 0);
        const payload = {
            id: Number(id || 0),
            usage_id: Number(usageId || 0),
            content_id: pageId,
            folder_id: folderId || 0,
            slot: slot || ""
        };
        return fetchJson(apiUrl(url), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
            .then(data => {
                if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "remove failed");
                if (slot === "inline") removeInlineMediaFromEditor(root, id);
                if (Array.isArray(data.media || data.rows)) renderMedia(root, data.media || data.rows);
                status(root, "Medien-Zuordnung entfernt.", "success");
                return Array.isArray(data.media || data.rows) ? Promise.resolve(data) : loadMedia(root, cfg);
            })
            .catch(err => {
                dbx.error("[cms] media remove failed", err);
                status(root, "Medien-Zuordnung konnte nicht entfernt werden.", "error");
            });
    }

    function deleteMedia(root, cfg, id) {
        const url = cfgUrl(cfg, "deletemedia");
        if (!url || !id) return Promise.resolve();

        return fetchJson(apiUrl(url, { id }), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: Number(id) })
        }).then(data => {
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "delete failed");
            status(root, "Mediendatei geloescht.", "success");
            return loadMedia(root, cfg);
        }).catch(err => {
            dbx.error("[cms] media delete failed", err);
            status(root, "Mediendatei konnte nicht geloescht werden.", "error");
        });
    }

    function autoRatioValue(changed, other, originalWidth, originalHeight) {
        const value = Number(changed?.value || 0);
        if (!changed || !other || !value || !originalWidth || !originalHeight) return;
        if (changed.hasAttribute("data-cms-media-edit-width")) {
            other.value = Math.max(1, Math.round(value * originalHeight / originalWidth));
        } else {
            other.value = Math.max(1, Math.round(value * originalWidth / originalHeight));
        }
    }

    function mediaEditNeedsRebuild(modal) {
        return !modal || !!qs(modal, ".dbx-cms-media-edit-actions");
    }

    function fitMediaEditDialog(modal) {
        const maxW = Math.min(1380, Math.floor(window.innerWidth * 0.96));
        const maxH = Math.min(1010, Math.floor(window.innerHeight * 0.94));
        const width = Math.min(760, maxW);
        const height = Math.min(640, maxH);
        modal.style.width = width + "px";
        modal.style.height = height + "px";
        modal.style.maxWidth = maxW + "px";
        modal.style.maxHeight = maxH + "px";
    }

    function ensureMediaEditDialog(root) {
        const s = state(root);
        let modal = s.mediaEditDialog || qs(root, "[data-cms-media-edit]");
        if (modal && !document.documentElement.contains(modal)) {
            modal = null;
            s.mediaEditDialog = null;
        }
        if (mediaEditNeedsRebuild(modal)) {
            if (modal) modal.remove();
            modal = null;
            s.mediaEditDialog = null;
        }
        if (modal) {
            if (modal.parentNode !== document.body) document.body.appendChild(modal);
            modal.__dbxCmsRoot = root;
            s.mediaEditDialog = modal;
            return modal;
        }
        modal = document.createElement("div");
        modal.className = "dbx-cms-media-edit";
        modal.setAttribute("data-cms-media-edit", "1");
        modal.__dbxCmsRoot = root;
        modal.hidden = true;
        modal.innerHTML = `
            <div class="dbx-cms-media-edit-head">
                <strong><i class="bi bi-crop"></i> Bild bearbeiten</strong>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-edit-close title="Schliessen"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="dbx-cms-media-edit-body">
                <div class="dbx-cms-media-edit-preview" data-cms-media-edit-preview>
                    <div class="dbx-cms-media-edit-stage" data-cms-media-edit-stage>
                        <img src="" alt="" draggable="false" data-cms-media-edit-image>
                        <span class="dbx-cms-media-edit-selection" data-cms-media-edit-selection hidden>
                            <span class="dbx-cms-media-edit-selection-size" data-cms-media-edit-selection-size></span>
                        </span>
                    </div>
                    <span class="dbx-cms-media-edit-info" data-cms-media-edit-info>X 0 px · Y 0 px · 0 x 0 px</span>
                </div>
                <div class="dbx-cms-media-edit-grid">
                    <label>X <input type="number" class="form-control form-control-sm" data-cms-media-edit-x value="0"></label>
                    <label>Y <input type="number" class="form-control form-control-sm" data-cms-media-edit-y value="0"></label>
                    <label>Breite <input type="number" class="form-control form-control-sm" data-cms-media-edit-width></label>
                    <label>Hoehe <input type="number" class="form-control form-control-sm" data-cms-media-edit-height></label>
                    <label class="dbx-cms-media-edit-ratio"><input type="checkbox" data-cms-media-edit-ratio checked> Ratio Resize</label>
                    <div class="dbx-cms-media-edit-side-actions">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-edit-resize><i class="bi bi-arrows-angle-contract"></i><span>Resize</span></button>
                        <button type="button" class="btn btn-primary btn-sm" data-cms-media-edit-crop><i class="bi bi-crop"></i><span>Zuschneiden</span></button>
                        <span class="dbx-cms-media-edit-side-separator" aria-hidden="true"></span>
                        <span class="dbx-cms-media-edit-hint">
                            <span>Mit der Maus den gewuenschten Bildausschnitt waehlen.</span>
                            <button type="button" class="btn btn-danger btn-sm" data-cms-media-edit-crop-apply>
                                <i class="bi bi-crop"></i><span>Ausschnitt uebernehmen</span>
                            </button>
                        </span>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modal);
        s.mediaEditDialog = modal;
        return modal;
    }

    function mediaEditImageSize(modal) {
        const img = qs(modal, "[data-cms-media-edit-image]");
        const row = modal.__dbxCmsEditRow || {};
        return {
            width: Number(img?.naturalWidth || row.width || 0),
            height: Number(img?.naturalHeight || row.height || 0)
        };
    }

    function clampMediaCrop(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function renderMediaCropSelection(modal) {
        const preview = qs(modal, "[data-cms-media-edit-preview]");
        const stage = qs(modal, "[data-cms-media-edit-stage]");
        const img = qs(modal, "[data-cms-media-edit-image]");
        const selection = qs(modal, "[data-cms-media-edit-selection]");
        const info = qs(modal, "[data-cms-media-edit-info]");
        if (!preview || !stage || !img || !selection || !img.complete) {
            if (info) info.textContent = "X 0 px · Y 0 px · 0 x 0 px";
            return;
        }

        const natural = mediaEditImageSize(modal);
        const imageRect = img.getBoundingClientRect();
        const stageRect = stage.getBoundingClientRect();
        if (!natural.width || !natural.height || !imageRect.width || !imageRect.height) return;

        const x = clampMediaCrop(Number(qs(modal, "[data-cms-media-edit-x]")?.value || 0), 0, natural.width - 1);
        const y = clampMediaCrop(Number(qs(modal, "[data-cms-media-edit-y]")?.value || 0), 0, natural.height - 1);
        const width = clampMediaCrop(Number(qs(modal, "[data-cms-media-edit-width]")?.value || natural.width), 1, natural.width - x);
        const height = clampMediaCrop(Number(qs(modal, "[data-cms-media-edit-height]")?.value || natural.height), 1, natural.height - y);
        const scaleX = imageRect.width / natural.width;
        const scaleY = imageRect.height / natural.height;
        const infoText = "X " + Math.round(x) + " px · Y " + Math.round(y) + " px · "
            + Math.round(width) + " x " + Math.round(height) + " px";
        if (info) info.textContent = infoText;

        selection.style.left = (imageRect.left - stageRect.left + x * scaleX) + "px";
        selection.style.top = (imageRect.top - stageRect.top + y * scaleY) + "px";
        selection.style.width = (width * scaleX) + "px";
        selection.style.height = (height * scaleY) + "px";

        const size = qs(selection, "[data-cms-media-edit-selection-size]");
        const isFullFrame = x === 0 && y === 0 && width >= natural.width && height >= natural.height;
        if (!modal.__dbxCmsCropActive && isFullFrame) {
            selection.hidden = true;
            if (size) size.textContent = "";
            return;
        }
        selection.hidden = false;

        if (size) size.textContent = Math.round(width) + " × " + Math.round(height);
    }

    function setMediaCropFields(modal, crop) {
        modal.__dbxCmsCropActive = true;
        const natural = mediaEditImageSize(modal);
        if (!natural.width || !natural.height) return;
        const x = clampMediaCrop(Math.round(Math.min(crop.x1, crop.x2)), 0, natural.width - 1);
        const y = clampMediaCrop(Math.round(Math.min(crop.y1, crop.y2)), 0, natural.height - 1);
        const right = clampMediaCrop(Math.round(Math.max(crop.x1, crop.x2)), x + 1, natural.width);
        const bottom = clampMediaCrop(Math.round(Math.max(crop.y1, crop.y2)), y + 1, natural.height);
        const width = qs(modal, "[data-cms-media-edit-width]");
        const height = qs(modal, "[data-cms-media-edit-height]");
        const inputX = qs(modal, "[data-cms-media-edit-x]");
        const inputY = qs(modal, "[data-cms-media-edit-y]");
        if (inputX) inputX.value = x;
        if (inputY) inputY.value = y;
        if (width) width.value = right - x;
        if (height) height.value = bottom - y;
        renderMediaCropSelection(modal);
    }

    function mediaCropPoint(modal, event) {
        const img = qs(modal, "[data-cms-media-edit-image]");
        const natural = mediaEditImageSize(modal);
        if (!img || !natural.width || !natural.height) return null;
        const rect = img.getBoundingClientRect();
        if (!rect.width || !rect.height) return null;
        return {
            x: clampMediaCrop((event.clientX - rect.left) * natural.width / rect.width, 0, natural.width),
            y: clampMediaCrop((event.clientY - rect.top) * natural.height / rect.height, 0, natural.height),
            inside: event.clientX >= rect.left && event.clientX <= rect.right
                && event.clientY >= rect.top && event.clientY <= rect.bottom
        };
    }

    function mediaEditPayload(modal, action) {
        const row = modal.__dbxCmsEditRow || {};
        return {
            action: action,
            id: Number(row.id || 0),
            width: Number(qs(modal, "[data-cms-media-edit-width]")?.value || 0),
            height: Number(qs(modal, "[data-cms-media-edit-height]")?.value || 0),
            ratio: qs(modal, "[data-cms-media-edit-ratio]")?.checked ? 1 : 0,
            x: Number(qs(modal, "[data-cms-media-edit-x]")?.value || 0),
            y: Number(qs(modal, "[data-cms-media-edit-y]")?.value || 0)
        };
    }

    function resetMediaEditSelection(modal) {
        const x = qs(modal, "[data-cms-media-edit-x]");
        const y = qs(modal, "[data-cms-media-edit-y]");
        const w = qs(modal, "[data-cms-media-edit-width]");
        const h = qs(modal, "[data-cms-media-edit-height]");
        const selection = qs(modal, "[data-cms-media-edit-selection]");
        const natural = mediaEditImageSize(modal);
        modal.__dbxCmsCropActive = false;
        if (x) x.value = "0";
        if (y) y.value = "0";
        if (w && natural.width) w.value = natural.width;
        if (h && natural.height) h.value = natural.height;
        if (selection) selection.hidden = true;
        renderMediaCropSelection(modal);
    }

    function previewMediaCrop(root, modal) {
        const img = qs(modal, "[data-cms-media-edit-image]");
        const selection = qs(modal, "[data-cms-media-edit-selection]");
        const payload = mediaEditPayload(modal, "crop");
        const natural = mediaEditImageSize(modal);
        if (!payload.id) {
            status(root, "Kein Bild ausgewaehlt.", "error");
            return false;
        }
        if (!modal.__dbxCmsCropActive) {
            status(root, "Bitte zuerst einen Bildausschnitt waehlen.", "error");
            return false;
        }
        if (!img || !img.complete || !natural.width || !natural.height || payload.width < 1 || payload.height < 1) {
            status(root, "Bitte einen gueltigen Ausschnitt waehlen.", "error");
            return false;
        }
        const x = clampMediaCrop(Math.round(payload.x), 0, natural.width - 1);
        const y = clampMediaCrop(Math.round(payload.y), 0, natural.height - 1);
        const width = clampMediaCrop(Math.round(payload.width), 1, natural.width - x);
        const height = clampMediaCrop(Math.round(payload.height), 1, natural.height - y);
        const canvas = document.createElement("canvas");
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext("2d");
        if (!ctx) {
            status(root, "Vorschau konnte nicht erstellt werden.", "error");
            return false;
        }
        try {
            ctx.drawImage(img, x, y, width, height, 0, 0, width, height);
        } catch (err) {
            dbx.error("[cms] crop preview failed", err);
            status(root, "Vorschau konnte nicht erstellt werden.", "error");
            return false;
        }
        const previous = modal.__dbxCmsPendingCrop || { x: 0, y: 0 };
        modal.__dbxCmsPendingCrop = {
            id: payload.id,
            x: Math.round(Number(previous.x || 0) + x),
            y: Math.round(Number(previous.y || 0) + y),
            width,
            height
        };
        img.onload = () => {
            resetMediaEditSelection(modal);
            fitMediaEditDialog(modal);
        };
        img.src = canvas.toDataURL("image/png");
        if (selection) selection.hidden = true;
        status(root, "Ausschnitt als Vorschau erstellt. Mit Ausschnitt uebernehmen speichern.", "info");
        return true;
    }

    function refreshMediaEditPreview(modal, row) {
        if (!modal || !row) return;
        const img = qs(modal, "[data-cms-media-edit-image]");
        const w = qs(modal, "[data-cms-media-edit-width]");
        const h = qs(modal, "[data-cms-media-edit-height]");
        const x = qs(modal, "[data-cms-media-edit-x]");
        const y = qs(modal, "[data-cms-media-edit-y]");
        const selection = qs(modal, "[data-cms-media-edit-selection]");
        modal.__dbxCmsEditRow = row;
        modal.__dbxCmsPendingCrop = null;
        modal.__dbxCmsCropActive = false;
        if (x) x.value = "0";
        if (y) y.value = "0";
        if (selection) selection.hidden = true;
        const baseUrl = row.url || row.thumb_url || "";
        const url = baseUrl ? apiUrl(baseUrl) : "";
        if (w) w.value = row.width || "";
        if (h) h.value = row.height || "";
        if (!img || !url) return;
        img.onload = () => {
            const natural = mediaEditImageSize(modal);
            if (w && natural.width) w.value = natural.width;
            if (h && natural.height) h.value = natural.height;
            if (selection) selection.hidden = true;
            fitMediaEditDialog(modal);
            renderMediaCropSelection(modal);
        };
        img.src = url;
    }

    function reopenMediaBrowserAfterEdit(root, browser) {
        if (!browser || browser.hidden) return;
        openMediaBrowser(browser.__dbxCmsRoot || root, browserCfg(browser), {
            mode: browser.__dbxCmsMediaMode || "editor",
            slot: browser.__dbxCmsAssignSlot || currentMediaSlot(root),
            mediaFolder: browser.__dbxCmsMediaFolder || "",
            formDataExtra: browser.__dbxCmsFormDataExtra || null,
            afterAssign: browser.__dbxCmsAfterAssign
        });
    }

    function commitMediaEditAction(root, cfg, modal, action, options) {
        options = options || {};
        let payload = mediaEditPayload(modal, action);
        if (!payload.id) {
            status(root, "Kein Bild ausgewaehlt.", "error");
            return Promise.resolve(false);
        }
        if (action === "resize" && modal.__dbxCmsPendingCrop) {
            status(root, "Bitte den Ausschnitt zuerst uebernehmen oder den Dialog neu oeffnen.", "warning");
            return Promise.resolve(false);
        }
        if (action === "crop" && modal.__dbxCmsPendingCrop) {
            payload = Object.assign({}, payload, {
                x: Number(modal.__dbxCmsPendingCrop.x || 0),
                y: Number(modal.__dbxCmsPendingCrop.y || 0),
                width: Number(modal.__dbxCmsPendingCrop.width || 0),
                height: Number(modal.__dbxCmsPendingCrop.height || 0)
            });
        }
        if (action === "crop" && !modal.__dbxCmsPendingCrop && !modal.__dbxCmsCropActive) {
            status(root, "Bitte zuerst einen Bildausschnitt waehlen.", "error");
            return Promise.resolve(false);
        }
        if (action === "crop" && (payload.width < 1 || payload.height < 1)) {
            status(root, "Bitte einen gueltigen Ausschnitt waehlen.", "error");
            return Promise.resolve(false);
        }
        const successMsg = action === "crop"
            ? "Bild zugeschnitten."
            : "Bild bearbeitet.";
        return editMedia(root, cfg, payload, {
            reload: false,
            silent: false,
            successMsg
        }).then(data => {
            if (!data || !data.ok) return false;
            const updated = Array.isArray(data.rows) && data.rows[0] ? data.rows[0] : null;
            if (!updated) return false;
            const browserModal = qs(document, "[data-cms-media-browser]");
            if (browserModal) patchMediaBrowserRow(browserModal, updated);
            loadMedia(root, cfg);
            if (options.closeAfter) {
                modal.hidden = true;
                reopenMediaBrowserAfterEdit(root, qs(document, "[data-cms-media-browser]"));
                return true;
            }
            refreshMediaEditPreview(modal, updated);
            return true;
        });
    }

    function bindMediaEditEvents(root, modal) {
        if (!modal || modal.__dbxCmsEventsBound) return;
        modal.__dbxCmsEventsBound = true;

        // Direkt am Dialog binden. Der Medienbrowser lebt in einem openWin-Fenster
        // und weitere UI-Layer koennen die delegierten Root-Events abfangen.
        modal.addEventListener("click", e => {
            const close = closestElement(e.target, "[data-cms-media-edit-close]");
            if (close && modal.contains(close)) {
                e.preventDefault();
                e.stopPropagation();
                modal.hidden = true;
                return;
            }

            const resize = closestElement(e.target, "[data-cms-media-edit-resize]");
            const cropApply = closestElement(e.target, "[data-cms-media-edit-crop-apply]");
            const cropSave = closestElement(e.target, "[data-cms-media-edit-crop]");
            const cfg = modal.__dbxCmsCfg || {};

            if (cropApply && modal.contains(cropApply)) {
                e.preventDefault();
                e.stopPropagation();
                commitMediaEditAction(root, cfg, modal, "crop", { closeAfter: false });
                return;
            }

            if (cropSave && modal.contains(cropSave)) {
                e.preventDefault();
                e.stopPropagation();
                previewMediaCrop(root, modal);
                return;
            }

            if (resize && modal.contains(resize)) {
                e.preventDefault();
                e.stopPropagation();
                commitMediaEditAction(root, cfg, modal, "resize", { closeAfter: false });
            }
        });

        modal.addEventListener("input", e => {
            const editInput = closestElement(e.target, "[data-cms-media-edit-x], [data-cms-media-edit-y], [data-cms-media-edit-width], [data-cms-media-edit-height]");
            if (!editInput || !modal.contains(editInput)) return;
            modal.__dbxCmsCropActive = true;
            if (editInput.hasAttribute("data-cms-media-edit-x") || editInput.hasAttribute("data-cms-media-edit-y")) {
                renderMediaCropSelection(modal);
                return;
            }
            const ratio = qs(modal, "[data-cms-media-edit-ratio]");
            if (ratio && ratio.checked && !modal.__dbxCmsCropSelecting) {
                const row = modal.__dbxCmsEditRow || {};
                const w = qs(modal, "[data-cms-media-edit-width]");
                const h = qs(modal, "[data-cms-media-edit-height]");
                autoRatioValue(editInput, editInput === w ? h : w, Number(row.width || 0), Number(row.height || 0));
            }
            renderMediaCropSelection(modal);
        });

        const preview = qs(modal, "[data-cms-media-edit-preview]");
        if (preview) {
            preview.addEventListener("pointerdown", e => {
                if (e.button !== undefined && e.button !== 0) return;
                const point = mediaCropPoint(modal, e);
                if (!point || !point.inside) return;
                e.preventDefault();
                e.stopPropagation();
                modal.__dbxCmsCropSelecting = {
                    pointerId: e.pointerId,
                    x1: point.x,
                    y1: point.y,
                    x2: point.x,
                    y2: point.y
                };
                if (preview.setPointerCapture && e.pointerId !== undefined) {
                    preview.setPointerCapture(e.pointerId);
                }
                setMediaCropFields(modal, modal.__dbxCmsCropSelecting);
            });

            preview.addEventListener("pointermove", e => {
                const crop = modal.__dbxCmsCropSelecting;
                if (!crop || (e.pointerId !== undefined && crop.pointerId !== e.pointerId)) return;
                const point = mediaCropPoint(modal, e);
                if (!point) return;
                e.preventDefault();
                crop.x2 = point.x;
                crop.y2 = point.y;
                setMediaCropFields(modal, crop);
            });

            const finishSelection = e => {
                const crop = modal.__dbxCmsCropSelecting;
                if (!crop || (e.pointerId !== undefined && crop.pointerId !== e.pointerId)) return;
                const point = mediaCropPoint(modal, e);
                if (point) {
                    crop.x2 = point.x;
                    crop.y2 = point.y;
                    setMediaCropFields(modal, crop);
                }
                if (preview.releasePointerCapture && e.pointerId !== undefined && preview.hasPointerCapture?.(e.pointerId)) {
                    preview.releasePointerCapture(e.pointerId);
                }
                modal.__dbxCmsCropSelecting = null;
            };
            preview.addEventListener("pointerup", finishSelection);
            preview.addEventListener("pointercancel", finishSelection);
        }

        window.addEventListener("resize", () => {
            if (!modal.hidden) {
                fitMediaEditDialog(modal);
                renderMediaCropSelection(modal);
            }
        });
    }

    function openMediaEdit(root, cfg, row) {
        if (!row || !canEditImage(row)) {
            status(root, "Nur Rasterbilder koennen bearbeitet werden.", "error");
            return;
        }
        const modal = ensureMediaEditDialog(root);
        bindMediaEditEvents(root, modal);
        // Der Medienbrowser kann aus einem CMS-openWin heraus ein weiteres openWin oeffnen.
        // Der Bildeditor liegt deshalb als Body-Layer ausserhalb des CMS-Fenster-Stacking-Contexts.
        const windowLevels = qsa(document, ".dbx-window")
            .map(win => Number(window.getComputedStyle(win).zIndex || 0))
            .filter(Number.isFinite);
        modal.style.zIndex = String(Math.max(3200, ...windowLevels) + 10);
        modal.__dbxCmsCfg = cfg || {};
        modal.__dbxCmsEditRow = row;
        modal.__dbxCmsPendingCrop = null;
        modal.__dbxCmsCropActive = false;
        const img = qs(modal, "[data-cms-media-edit-image]");
        const w = qs(modal, "[data-cms-media-edit-width]");
        const h = qs(modal, "[data-cms-media-edit-height]");
        const x = qs(modal, "[data-cms-media-edit-x]");
        const y = qs(modal, "[data-cms-media-edit-y]");
        const ratio = qs(modal, "[data-cms-media-edit-ratio]");
        if (img) {
            img.onload = () => {
                const natural = mediaEditImageSize(modal);
                if (w && natural.width) w.value = natural.width;
                if (h && natural.height) h.value = natural.height;
                fitMediaEditDialog(modal);
                renderMediaCropSelection(modal);
            };
            img.src = apiUrl(row.url || row.thumb_url || "");
        }
        if (w) w.value = row.width || "";
        if (h) h.value = row.height || "";
        if (x) x.value = "0";
        if (y) y.value = "0";
        if (ratio) ratio.checked = true;
        modal.hidden = false;
        if (img && img.complete) {
            fitMediaEditDialog(modal);
            renderMediaCropSelection(modal);
        }
    }

    function editMedia(root, cfg, payload, options) {
        options = options || {};
        const url = cfgUrl(cfg, "editmedia");
        if (!url) {
            status(root, "Medienbearbeitung ist nicht konfiguriert.", "error");
            return Promise.resolve();
        }
        return fetchJson(apiUrl(url), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload || {})
        }).then(data => {
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "edit failed");
            if (options.silent !== true) status(root, options.successMsg || "Bild bearbeitet.", "success");
            if (options.reload === false) return data;
            return loadMedia(root, cfg).then(() => data);
        }).catch(err => {
            dbx.error("[cms] media edit failed", err);
            if (options.silent !== true) status(root, err && err.message ? err.message : "Bild konnte nicht bearbeitet werden.", "error");
            return false;
        });
    }

    function mediaRowsForResize(root, scope, modal) {
        if (modal) {
            if (scope === "all") {
                return mediaBrowserAllRows(modal).filter(canEditImage);
            }
            if (scope === "visible") {
                return mediaBrowserRows(modal).filter(canEditImage);
            }
            return selectedMediaBrowserImageRows(modal);
        }

        const rows = state(root).mediaRows || [];
        if (scope === "all") {
            return rows.filter(canEditImage);
        }
        if (scope === "visible") {
            const visibleIds = qsa(root, ".dbx-cms-media-item").map(el => Number(el.getAttribute("data-media-id") || 0)).filter(Boolean);
            return rows.filter(row => visibleIds.includes(Number(row.id || 0)) && canEditImage(row));
        }
        const ids = qsa(root, "[data-cms-media-select]:checked").map(el => Number(el.value || 0)).filter(Boolean);
        return rows.filter(row => ids.includes(Number(row.id || 0)) && canEditImage(row));
    }

    function bulkResizeMedia(root, cfg, scope, modal) {
        scope = scope || "selected";
        const rows = mediaRowsForResize(root, scope, modal);
        const ids = rows.map(row => Number(row.id || 0)).filter(Boolean);
        const host = batchControlHost(modal) || modal || root;
        const width = Number(qs(host, "[data-cms-bulk-resize-width]")?.value || 0);
        const height = Number(qs(host, "[data-cms-bulk-resize-height]")?.value || 0);
        const ratio = qs(host, "[data-cms-bulk-resize-ratio]")?.checked !== false;
        if (!ids.length) {
            status(root, scope === "selected" ? "Bitte erst Bilder auswaehlen." : "Keine bearbeitbaren Bilder gefunden.", "error");
            return;
        }
        if (!width && !height) {
            status(root, "Bitte Breite oder Hoehe fuer Resize eintragen.", "error");
            return;
        }
        let chain = Promise.resolve({ ok: 0, failed: 0 });
        ids.forEach(id => {
            chain = chain.then(result => editMedia(root, cfg, {
                action: "resize",
                id,
                width,
                height,
                ratio: ratio ? 1 : 0
            }, {
                reload: false,
                silent: true
            }).then(ok => {
                if (ok) result.ok++;
                else result.failed++;
                status(root, "Resize laeuft: " + result.ok + " von " + ids.length + " Bildern bearbeitet.", "info");
                return result;
            }));
        });

        chain.then(result => {
            if (!result.ok) {
                status(root, "Keine Bilder konnten resized werden.", "error");
                return;
            }
            status(root, result.failed ? (result.ok + " Bilder resized, " + result.failed + " fehlgeschlagen.") : (result.ok + " Bilder resized."), result.failed ? "error" : "success");
            loadMedia(root, cfg);
            if (!modal || modal.hidden) return;
            openMediaBrowser(modal.__dbxCmsRoot || root, browserCfg(modal), {
                mode: modal.__dbxCmsMediaMode || "editor",
                slot: modal.__dbxCmsAssignSlot || currentMediaSlot(root),
                mediaFolder: modal.__dbxCmsMediaFolder || "",
                formDataExtra: modal.__dbxCmsFormDataExtra || null,
                afterAssign: modal.__dbxCmsAfterAssign
            });
        });
    }

    function assignMedia(root, cfg, row, slot) {
        const url = cfgUrl(cfg, "assignmedia");
        const isFolderEditing = root.classList.contains("is-folder-editing");
        let pageId = Number(getField(root, "id") || 0);
        const folderId = isFolderEditing
            ? Number(getFolderField(root, "id") || 0)
            : Number(getField(root, "folder") || 0);
        const mediaId = Number(row && row.id || 0);
        const targetSlot = slot || currentMediaSlot(root);
        if (isFolderEditing && targetSlot === "hero") pageId = 0;
        if (!url || !mediaId || (!pageId && !(folderId && targetSlot === "hero"))) {
            status(root, "Bitte erst eine Seite und ein Bild waehlen.", "error");
            return Promise.resolve();
        }

        return fetchJson(apiUrl(url), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id: mediaId,
                content_id: pageId,
                folder_id: folderId,
                slot: targetSlot
            })
        }).then(data => {
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "assign failed");
            status(root, "Bild der Seite zugeordnet.", "success");
            const assignedRow = mediaRowWithUsage(data.row || row, data.usage || {}, targetSlot);
            return loadMedia(root, cfg).then(() => {
                upsertLocalMediaRow(root, assignedRow);
                return assignedRow;
            });
        }).catch(err => {
            dbx.error("[cms] media assign failed", err);
            status(root, "Bild konnte nicht zugeordnet werden.", "error");
            return null;
        });
    }

    function saveMediaOrder(root, cfg, list) {
        const url = cfgUrl(cfg, "sortmedia");
        const pageId = Number(getField(root, "id") || 0);
        if (!url || !pageId) return Promise.resolve();
        const scope = list || root;
        const ids = qsa(scope, ".dbx-cms-media-item").map(item => Number(item.getAttribute("data-usage-id") || item.getAttribute("data-media-id") || 0)).filter(Boolean);
        if (!ids.length) return Promise.resolve();
        const slot = String((list && list.getAttribute("data-cms-media")) || "");

        return fetchJson(apiUrl(url), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                content_id: pageId,
                slot: slot || "all",
                ids
            })
        }).then(data => {
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "sort failed");
            status(root, "Medien sortiert.", "success");
            return loadMedia(root, cfg);
        }).catch(err => {
            dbx.error("[cms] media sort failed", err);
            status(root, "Medien konnten nicht sortiert werden.", "error");
        });
    }

    function setMediaSlot(root, cfg, id, slot) {
        const url = cfgUrl(cfg, "setmediaslot");
        const pageId = Number(getField(root, "id") || 0);
        if (!url || !id || !slot) return Promise.resolve();
        const item = qs(root, `.dbx-cms-media-item[data-media-id="${String(id).replace(/"/g, '\\"')}"]`);
        const usageId = Number(item?.getAttribute("data-usage-id") || 0);

        return fetchJson(apiUrl(url), {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id: Number(id),
                usage_id: usageId,
                content_id: pageId,
                slot
            })
        }).then(data => {
            if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "slot failed");
            status(root, "Medium zugeordnet.", "success");
            const updatedRow = data.row ? mediaRowWithUsage(data.row, data.usage || {}, slot) : null;
            return loadMedia(root, cfg).then(() => {
                if (updatedRow) upsertLocalMediaRow(root, updatedRow);
                return data;
            });
        }).catch(err => {
            dbx.error("[cms] media slot failed", err);
            status(root, "Medium konnte nicht zugeordnet werden.", "error");
            return loadMedia(root, cfg).then(() => null);
        }).finally(() => {
            clearCmsLoading(root);
            window.setTimeout(() => clearCmsLoading(root), 50);
        });
    }

    function addExternalVideo(root, cfg, form, options) {
        options = options || {};
        const url = cfgUrl(cfg, "externalvideo");
        const externalUrl = String(qs(form, 'input[name="url"]')?.value || "").trim();
        const isFolderEditing = !!closestElement(form, "[data-cms-folder-panel]");
        const pageId = isFolderEditing ? 0 : Number(getField(root, "id") || 0);
        const folderId = isFolderEditing
            ? Number(getFolderField(root, "id") || 0)
            : Number(getField(root, "folder") || 0);
        const slotInput = qs(form, 'input[name="slot"]');
        const slot = options.slot || (slotInput && slotInput.value ? slotInput.value : currentMediaSlot(root));
        if (!url) {
            status(root, "Externe Videos sind nicht konfiguriert.", "error");
            return Promise.resolve(null);
        }
        if (!externalUrl) {
            status(root, "Bitte eine YouTube-URL eintragen.", "error");
            return Promise.resolve(null);
        }
        if (!pageId && !(folderId && slot === "hero")) {
            status(root, "Bitte erst eine Seite speichern/auswaehlen.", "error");
            return Promise.resolve(null);
        }

        const body = new FormData(form);
        body.set("provider", "youtube");
        body.set("external_url", externalUrl);
        body.set("content_id", pageId);
        body.set("folder_id", folderId || "0");
        body.set("slot", slot);
        body.set("media_folder", "youtube");

        return fetchJson(apiUrl(url), {
            method: "POST",
            body: body
        }).then(data => {
            applyFormSecurity(form, data);
            if (!data || !data.ok || !data.row) throw new Error(data && data.msg ? data.msg : "external video failed");
            status(root, "Externes Video hinzugefuegt.", "success");
            const assignedRow = mediaRowWithUsage(data.row, data.usage || {}, slot);
            if (options.insertExternal) insertMediaRow(root, assignedRow);
            form.reset();
            return loadMedia(root, cfg).then(() => {
                upsertLocalMediaRow(root, assignedRow);
                if (typeof options.afterExternal === "function") options.afterExternal(Object.assign({}, data, { row: assignedRow }));
                return Object.assign({}, data, { row: assignedRow });
            });
        }).catch(err => {
            dbx.error("[cms] external video failed", err);
            status(root, err && err.message ? err.message : "Externes Video konnte nicht hinzugefuegt werden.", "error");
            return null;
        });
    }

    function uploadMedia(root, cfg, form, options) {
        options = options || {};
        const url = cfgUrl(cfg, "upload");
        const moduleUploadUrl = String(url || "").indexOf("modul_images_upload") >= 0;
        options.moduleUpload = moduleUploadUrl;
        const isFolderEditing = root.classList.contains("is-folder-editing");
        let pageId = Number(getField(root, "id") || 0);
        const folderId = isFolderEditing
            ? Number(getFolderField(root, "id") || 0)
            : Number(getField(root, "folder") || 0);
        const slotInput = qs(form, 'input[name="slot"]');
        const slot = slotInput && slotInput.value ? slotInput.value : currentMediaSlot(root);
        if (isFolderEditing && slot === "hero") pageId = 0;
        const browserModal = state(root).mediaBrowser;
        const browserOpen = browserModal && !browserModal.hidden;
        if (!url) {
            status(root, "Upload ist nicht konfiguriert.", "error");
            return;
        }
        if (!options.pickMode && !options.moduleUpload && !browserOpen && !pageId && !(folderId && slot === "hero")) {
            status(root, "Bitte erst eine Seite speichern/auswaehlen.", "error");
            return;
        }
        const files = selectedUploadFiles(form);
        if (!files.length) {
            status(root, "Bitte zuerst eine Datei auswaehlen oder in die Upload-Zone ziehen.", "error");
            updateUploadLabel(form);
            return;
        }
        const selectedUploadFolder = qs(form, "[data-cms-upload-folder]")?.value || "";
        const maxUpload = Number(cfg && cfg.uploadmax || 0);
        if (maxUpload > 0) {
            const tooLarge = files.find(file => Number(file.size || 0) > maxUpload);
            if (tooLarge) {
                status(root, "Datei ist zu gross: " + tooLarge.name + " (" + formatBytes(tooLarge.size) + "). Erlaubt sind maximal " + formatBytes(maxUpload) + ".", "error");
                return;
            }
        }

        const uploadOne = function (file) {
            const body = new FormData(form);
            body.delete("file");
            body.append("file", file, file.name);
            if (!options.moduleUpload) {
                body.set("content_id", pageId);
                body.set("folder_id", folderId || "0");
            }
            const uploadFolderSelect = qs(form, "[data-cms-upload-folder]");
            if (!options.moduleUpload) {
                if (String(file.type || "").startsWith("video/")) {
                    const videoFolder = firstMediaFolderOption(uploadFolderSelect, "videos") || firstMediaFolderOption(uploadFolderSelect, "video");
                    if (videoFolder && !/^(videos|video)(\/|$)/.test(String(body.get("media_folder") || ""))) {
                        body.set("media_folder", videoFolder);
                    }
                } else if (String(file.type || "").startsWith("image/")) {
                    const imageFolder = firstMediaFolderOption(uploadFolderSelect, "img/");
                    if (imageFolder && String(body.get("media_folder") || "").indexOf("img/") !== 0) {
                        body.set("media_folder", imageFolder);
                    }
                }
            }
            if (form.hasAttribute("data-cms-upload")) {
                body.set("slot", slot);
            }
            Object.keys(options.formDataExtra || {}).forEach(key => {
                if (options.formDataExtra[key] != null) {
                    body.set(key, options.formDataExtra[key]);
                }
            });

            return fetchJson(apiUrl(url), {
                method: "POST",
                body: body,
                timeout: 60000
            }).then(data => {
                applyFormSecurity(form, data);
                if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "upload failed");
                data.upload_folder = String(body.get("media_folder") || "");
                return data;
            });
        };

        files.reduce((chain, file) => {
            return chain.then(results => uploadOne(file).then(data => {
                results.push(data);
                return results;
            }));
        }, Promise.resolve([]))
            .then(data => {
                const rows = data.map(item => item && item.row ? mediaRowWithUsage(item.row, item.usage || {}, slot) : null).filter(Boolean);
                const moduleUpload = data.some(item => item && Array.isArray(item.items));
                const uploadMsg = rows.length > 1
                    ? rows.length + " Medien hochgeladen."
                    : (moduleUpload ? "Modulbild gespeichert." : "Medium hochgeladen.");
                status(root, uploadMsg, "success");
                if (options.insertUploaded) rows.forEach(row => insertMediaRow(root, row));
                form.reset();
                const uploadFolderAfterReset = qs(form, "[data-cms-upload-folder]");
                const refreshUploadFolder = data.find(item => item && item.upload_folder)?.upload_folder
                    || (rows[0] && rows[0].media_folder)
                    || selectedUploadFolder
                    || "";
                if (uploadFolderAfterReset && refreshUploadFolder && Array.from(uploadFolderAfterReset.options).some(option => option.value === refreshUploadFolder)) {
                    uploadFolderAfterReset.value = refreshUploadFolder;
                }
                updateUploadLabel(form);
                const modal = state(root).mediaBrowser;
                const browserOpen = modal && !modal.hidden;
                let reload;
                if (moduleUpload && options.pickMode) {
                    reload = Promise.resolve();
                } else if (browserOpen) {
                    reload = Promise.resolve();
                } else {
                    reload = loadMedia(root, cfg);
                }
                reload.then(() => rows.forEach(row => upsertLocalMediaRow(root, row)));
                if (typeof options.afterUpload === "function") {
                    reload.then(() => options.afterUpload({
                        ok: 1,
                        success: true,
                        row: rows[0] || null,
                        rows: rows,
                        responses: data,
                        uploadFolder: refreshUploadFolder
                    }));
                }
                return reload;
            })
            .catch(err => {
                dbx.error("[cms] upload failed", err);
                const message = err && err.message ? String(err.message) : "";
                const uploadErr = message === "Decoding failed"
                    ? "Upload fehlgeschlagen. Die Serverantwort konnte nicht gelesen werden; bitte Upload-Limit und Dateigroesse pruefen."
                    : (message || "Upload fehlgeschlagen.");
                status(root, uploadErr, "error");
            })
            .finally(() => {
                clearCmsLoading(root);
            });
    }

    function insertMarker(root, marker, label) {
        const editor = qs(root, "[data-cms-editor]");
        if (!editor) return;
        if (marker === "dbx:split") marker = "dbx:col2";
        insertEditorMarkerElement(root, marker, label);
    }

    function bind(root, cfg) {
        initDetailsToggles(root);
        initCmsSelects(root);
        bindAdminTreeOutsideClose(root, cfg || {});
        bindViewTreeHover(root, cfg || {});
        bindCmsKeyboardShortcuts(root, cfg || {});
        updateHeaderActionTooltips(root);

        if (!root.__dbxCmsMediaProcessEvents && dbx.event && typeof dbx.event.on === "function") {
            root.__dbxCmsMediaProcessEvents = true;
            dbx.event.on("process:after", data => {
                const proc = data && data.root;
                if (!proc || !proc.getAttribute) return;
                const modal = closestElement(proc, "[data-cms-media-browser]");
                if (!modal || !root.contains(modal)) return;
                if (proc.getAttribute("data-process-status") !== "finished" || proc.__dbxCmsFinishedHandled) return;
                proc.__dbxCmsFinishedHandled = true;
                status(root, "Medienwartung abgeschlossen.", "success");
                openMediaBrowser(root, cfg, {
                    mode: modal.__dbxCmsMediaMode || "editor",
                    slot: modal.__dbxCmsAssignSlot || currentMediaSlot(root),
                    mediaFolder: modal.__dbxCmsMediaFolder || "",
                    formDataExtra: modal.__dbxCmsFormDataExtra || null,
                    afterAssign: modal.__dbxCmsAfterAssign
                });
            });
        }

        if (!root.__dbxCmsTreePointerBound) {
            root.__dbxCmsTreePointerBound = true;
            let treePress = null;
            root.addEventListener("pointerdown", e => {
                if (e.button !== 0) return;
                const row = closestElement(e.target, ".dbx-cms-tree-row");
                if (!row || !root.contains(row) || closestElement(e.target, "[data-cms-tree-toggle]") || closestElement(e.target, "[data-cms-folder-edit-btn]")) return;
                treePress = { row, x: e.clientX, y: e.clientY, pointerId: e.pointerId, dragIntent: false };
                root.__dbxCmsTreePress = treePress;
            }, true);
            root.addEventListener("pointermove", e => {
                if (!treePress || treePress.pointerId !== e.pointerId) return;
                if (Math.abs(e.clientX - treePress.x) > 8 || Math.abs(e.clientY - treePress.y) > 8) {
                    treePress.dragIntent = true;
                }
            }, true);
            root.addEventListener("pointerup", e => {
                if (e.button !== 0 || !treePress || treePress.pointerId !== e.pointerId) return;
                const start = treePress;
                treePress = null;
                root.__dbxCmsTreePress = null;
                const row = closestElement(e.target, ".dbx-cms-tree-row");
                if (!row || row !== start.row || row.classList.contains("is-dragging")) return;
                if (Math.abs(e.clientX - start.x) > 8 || Math.abs(e.clientY - start.y) > 8) return;
                activateTreeRow(root, cfg, row);
            }, true);
            root.addEventListener("pointercancel", e => {
                if (!treePress || treePress.pointerId !== e.pointerId) return;
                treePress = null;
                root.__dbxCmsTreePress = null;
            }, true);
        }

        root.addEventListener("click", e => {
            if (!closestElement(e.target, ".dbx-cms-select")) {
                closeCmsSelects(root);
            }

            const contentTemplateEdit = closestElement(e.target, "[data-cms-content-template-edit]");
            if (contentTemplateEdit && root.contains(contentTemplateEdit)) {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                openContentTemplateEditor(root, contentTemplateEdit);
                return;
            }

            const row = closestElement(e.target, ".dbx-cms-tree-row");
            if (row && root.contains(row)) {
                const editBtn = closestElement(e.target, "[data-cms-folder-edit-btn]");
                if (editBtn && root.contains(editBtn)) {
                    e.preventDefault();
                    e.stopPropagation();
                    openFolderEditorFromRow(root, cfg, row);
                    return;
                }
                const rightsEdit = closestElement(e.target, "[data-cms-folder-edit]");
                if (rightsEdit && root.contains(rightsEdit) && row.getAttribute("data-type") === "folder") {
                    e.preventDefault();
                    e.stopPropagation();
                    openFolderEditorFromRow(root, cfg, row);
                    return;
                }
                const toggle = closestElement(e.target, "[data-cms-tree-toggle]");
                if (toggle && root.contains(toggle)) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleTreeFolder(root, row);
                    return;
                }
                if (row.getAttribute("data-type") === "folder") {
                    selectTreeFolder(root, cfg, row);
                    return;
                }
                activateTreeRow(root, cfg, row);
                return;
            }

            const folderClose = closestElement(e.target, "[data-cms-folder-close]");
            if (folderClose && root.contains(folderClose)) {
                hideFolderEditor(root);
                return;
            }

            const action = closestElement(e.target, "[data-cms-action]");
            if (action && root.contains(action)) {
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();

                const name = action.getAttribute("data-cms-action");
                if (name === "toggle-tree-panel") toggleTreePanel(root);
                if (name === "toggle-right-panel") toggleRightPanel(root, action);
                if (name === "save") saveCurrentCms(root, cfg);
                if (name === "save-settings") saveCurrentCms(root, cfg);
                if (name === "delete") deleteCurrentCms(root, cfg);
                if (name === "delete-page") deletePage(root, cfg);
                if (name === "save-folder") saveFolder(root, cfg);
                if (name === "delete-folder") deleteFolder(root, cfg);
                if (name === "reload") reloadCms(root, cfg);
                if (name === "open-admin") openContentAdmin(root);
                if (name === "duplicate-page") duplicateCurrentPage(root, cfg);
                if (name === "new-page") {
                    const folder = state(root).selectedFolder || Number(getField(root, "folder") || 0);
                    fetchJson(apiUrl(cfgUrl(cfg, "newpage"), { folder })).then(data => {
                        if (data && data.ok) {
                            return loadTree(root, cfg).then(() => loadPage(root, cfg, data.id)).then(() => {
                                maybeOpenLngProvisionAfterCreate(root, cfg, data);
                            });
                        }
                        throw new Error(data && data.msg ? data.msg : "new page failed");
                    }).catch(err => {
                        dbx.error("[cms] new page failed", err);
                        status(root, err && err.message ? err.message : "Seite konnte nicht angelegt werden.", "error");
                    });
                }
                if (name === "new-folder") {
                    const parent = state(root).selectedFolder || Number(getField(root, "folder") || 0) || 0;
                    status(root, "Bezeichnung fuer neuen Ordner eintragen und speichern.", "info");
                    showFolderEditor(root, {
                        _id: 0,
                        _title: "",
                        _parent: parent,
                        _rights: parent > 0 ? "parent" : "*",
                        _template: ""
                    });
                }
                if (name === "preview") {
                    openPreview(root);
                }
                if (name === "lng-provision") {
                    openLngProvisionDialog(root, cfg);
                }
                if (name === "lng-reset-sync") {
                    resetLngSync(root, cfg);
                }
                if (name === "assign-media") {
                    openMediaBrowser(root, cfg, { mode: "assign", slot: action.getAttribute("data-cms-slot") || currentMediaSlot(root) });
                }
                if (name === "bulk-resize-media") {
                    bulkResizeMedia(root, cfg, action.getAttribute("data-cms-resize-scope") || "selected");
                }
                if (name === "assign-hero-media") {
                    openMediaBrowser(root, cfg, {
                        mode: "assign",
                        slot: "hero",
                        singlePick: true,
                        afterAssign(row) {
                            if (!row || !row.id) return;
                            state(root).heroPreviewRow = row;
                            setField(root, "hero_image_id", row.id);
                            if (!getField(root, "hero_template") || getField(root, "hero_template") === "parent" || getField(root, "hero_template") === "none") {
                                setField(root, "hero_template", "image-hero");
                            }
                            markDirty(root);
                            renderHeroPreview(root);
                            renderMedia(root);
                            status(root, "Hero-Bild ausgewaehlt.", "success");
                        }
                    });
                }
                if (name === "assign-seo-media") {
                    openMediaBrowser(root, cfg, {
                        mode: "assign",
                        slot: "seo",
                        afterAssign(row) {
                            if (!row || !row.id) return;
                            state(root).seoPreviewRow = row;
                            setField(root, "seo_image_id", row.id);
                            markDirty(root);
                            renderSeoPreview(root);
                            status(root, "OG-Bild ausgewaehlt.", "success");
                        }
                    });
                }
                if (name === "clear-seo-media") {
                    state(root).seoPreviewRow = null;
                    setField(root, "seo_image_id", 0);
                    markDirty(root);
                    renderSeoPreview(root);
                    status(root, "OG-Bild entfernt.", "success");
                }
                return;
            }

            const browserClose = closestElement(e.target, "[data-cms-media-browser-close]");
            if (browserClose && root.contains(browserClose)) {
                const modal = closestElement(browserClose, "[data-cms-media-browser]");
                if (modal) modal.hidden = true;
                clearCmsLoading(root);
                return;
            }

            const browserMaintenance = closestElement(e.target, "[data-cms-media-maintenance]");
            if (browserMaintenance && root.contains(browserMaintenance)) {
                const batchPanel = closestElement(browserMaintenance, "[data-cms-media-batch-window]");
                const browserModal = batchPanel && batchPanel.__dbxCmsBrowserModal
                    ? batchPanel.__dbxCmsBrowserModal
                    : closestElement(browserMaintenance, "[data-cms-media-browser]");
                renderMediaMaintenanceHome(root, cfg, browserModal, batchPanel || null);
                return;
            }

            const processStart = closestElement(e.target, "[data-cms-media-process-start]");
            if (processStart && root.contains(processStart)) {
                const batchPanel = closestElement(processStart, "[data-cms-media-batch-window]");
                const browserModal = batchPanel && batchPanel.__dbxCmsBrowserModal
                    ? batchPanel.__dbxCmsBrowserModal
                    : closestElement(processStart, "[data-cms-media-browser]");
                startMediaMaintenance(root, cfg, browserModal, batchPanel || null);
                return;
            }

            const unusedAction = closestElement(e.target, "[data-cms-media-unused-action]");
            if (unusedAction && root.contains(unusedAction)) {
                const browserModal = closestElement(unusedAction, "[data-cms-media-browser]");
                executeUnusedMediaMaintenance(root, cfg, browserModal, unusedAction.getAttribute("data-cms-media-unused-action"));
                return;
            }

            const processClose = closestElement(e.target, "[data-cms-media-process-close]");
            if (processClose && root.contains(processClose)) {
                const panel = closestElement(processClose, "[data-cms-media-process-panel]");
                if (panel) {
                    panel.hidden = true;
                    panel.innerHTML = "";
                }
                const modal = closestElement(processClose, "[data-cms-media-browser]");
                if (modal) modal.classList.remove("is-process-open");
                clearCmsLoading(root);
                return;
            }

            const browserConfirm = closestElement(e.target, "[data-cms-media-browser-confirm]");
            if (browserConfirm && root.contains(browserConfirm)) {
                const modal = closestElement(browserConfirm, "[data-cms-media-browser]");
                if (modal && modal.__dbxCmsMediaMode === "pick") {
                    confirmPickMediaBrowser(root, modal);
                    return;
                }
                const slot = modal && modal.__dbxCmsAssignSlot || currentMediaSlot(root);
                const rows = modal ? selectedMediaBrowserRows(modal) : [];
                if (!rows.length) {
                    status(root, "Bitte mindestens ein Bild auswaehlen.", "error");
                    return;
                }

                let chain = Promise.resolve();
                rows.forEach(row => {
                    chain = chain.then(() => assignMedia(root, cfg, row, slot).then(assignedRow => {
                        if (!assignedRow) return;
                        if (slot === "inline") {
                            insertMediaRow(root, assignedRow);
                            setLocalMediaSlot(root, assignedRow.id, "inline");
                        }
                        if (modal && typeof modal.__dbxCmsAfterAssign === "function") {
                            modal.__dbxCmsAfterAssign(assignedRow);
                        }
                    }));
                });
                chain.then(() => {
                    if (modal) {
                        modal.hidden = true;
                        if (modal.__dbxCmsWindowId && dbx.openWin && typeof dbx.openWin.close === "function") {
                            dbx.openWin.close(modal.__dbxCmsWindowId);
                        }
                    }
                    clearCmsLoading(root);
                    status(root, "Auswahl uebernommen.", "success");
                });
                return;
            }

            const browserDelete = closestElement(e.target, "[data-cms-media-browser-delete]");
            if (browserDelete && root.contains(browserDelete)) {
                const item = closestElement(browserDelete, "[data-cms-media-browser-item]");
                const modal = closestElement(browserDelete, "[data-cms-media-browser]");
                const mode = modal && modal.__dbxCmsMediaMode === "assign" ? "assign" : "editor";
                deleteMedia(root, cfg, Number(item?.getAttribute("data-media-id") || 0))
                    .then(() => openMediaBrowser(root, cfg, { mode }));
                return;
            }

            const browserEdit = closestElement(e.target, "[data-cms-media-browser-edit]");
            if (browserEdit && root.contains(browserEdit)) {
                const item = closestElement(browserEdit, "[data-cms-media-browser-item]");
                openMediaEdit(root, cfg, mediaRowFromItem(item));
                return;
            }

            const browserPick = closestElement(e.target, "[data-cms-media-browser-pick]");
            if (browserPick && root.contains(browserPick)) {
                const item = closestElement(browserPick, "[data-cms-media-browser-item]") || browserPick;
                const mediaRow = mediaRowFromItem(item);
                const modal = closestElement(browserPick, "[data-cms-media-browser]");
                const mode = modal && modal.__dbxCmsMediaMode ? modal.__dbxCmsMediaMode : "editor";
                if (mode === "pick" || mode === "assign") {
                    if (modal) toggleMediaBrowserSelection(modal, item);
                    return;
                }
                assignMedia(root, cfg, mediaRow, "inline").then(assignedRow => {
                    if (!assignedRow) return;
                    insertMediaRow(root, assignedRow);
                    setLocalMediaSlot(root, assignedRow.id, "inline");
                    if (modal) modal.hidden = true;
                    clearCmsLoading(root);
                });
                return;
            }

            const cmd = closestElement(e.target, "[data-cms-cmd]");
            if (cmd && root.contains(cmd)) {
                execEditorCommand(root, cmd.getAttribute("data-cms-cmd"));
                return;
            }

            const marker = closestElement(e.target, "[data-cms-marker]");
            if (marker && root.contains(marker)) {
                insertMarker(root, marker.getAttribute("data-cms-marker"));
                return;
            }

            const mediaRemove = closestElement(e.target, "[data-cms-media-remove]");
            if (mediaRemove && root.contains(mediaRemove)) {
                const item = closestElement(mediaRemove, ".dbx-cms-media-item");
                const id = Number(item?.getAttribute("data-media-id") || 0);
                const usageId = Number(item?.getAttribute("data-usage-id") || 0);
                const slot = String(item?.getAttribute("data-media-slot") || "");
                removeMedia(root, cfg, id, usageId, slot);
                return;
            }

            const mediaEdit = closestElement(e.target, "[data-cms-media-edit-one]");
            if (mediaEdit && root.contains(mediaEdit)) {
                const item = closestElement(mediaEdit, ".dbx-cms-media-item");
                openMediaEdit(root, cfg, mediaRowFromItem(item));
                return;
            }

            const toolsToggle = closestElement(e.target, "[data-cms-media-tools-toggle]");
            if (toolsToggle && root.contains(toolsToggle)) {
                const menu = qs(root, "[data-cms-media-tools-menu]");
                if (menu) menu.hidden = !menu.hidden;
                return;
            }

            if (!closestElement(e.target, ".dbx-cms-media-tools")) {
                const menu = qs(root, "[data-cms-media-tools-menu]");
                if (menu) menu.hidden = true;
            }

            const bulkResize = closestElement(e.target, "[data-cms-action='bulk-resize-media']");
            if (bulkResize && root.contains(bulkResize)) {
                bulkResizeMedia(root, cfg, bulkResize.getAttribute("data-cms-resize-scope") || "selected");
                return;
            }

            const editModal = closestElement(e.target, "[data-cms-media-edit]");
            if (editModal && root.contains(editModal)) {
                if (closestElement(e.target, "[data-cms-media-edit-close]")) {
                    editModal.hidden = true;
                    return;
                }
                const resize = closestElement(e.target, "[data-cms-media-edit-resize]");
                const cropApply = closestElement(e.target, "[data-cms-media-edit-crop-apply]");
                const cropSave = closestElement(e.target, "[data-cms-media-edit-crop]");
                if (cropApply) {
                    commitMediaEditAction(root, cfg, editModal, "crop", { closeAfter: false });
                    return;
                }
                if (cropSave) {
                    previewMediaCrop(root, editModal);
                    return;
                }
                if (resize) {
                    commitMediaEditAction(root, cfg, editModal, "resize", { closeAfter: false });
                    return;
                }
            }

            const videoOptionsModal = closestElement(e.target, "[data-cms-video-options]");
            if (videoOptionsModal && root.contains(videoOptionsModal)) {
                if (closestElement(e.target, "[data-cms-video-options-close]")) {
                    closeInlineVideoOptionsWindow(videoOptionsModal);
                    return;
                }
                if (closestElement(e.target, "[data-cms-video-options-apply]")) {
                    applyInlineVideoOptions(root, videoOptionsModal);
                    return;
                }
            }

            const inlineVideoOptionsOpen = closestElement(e.target, "[data-cms-inline-video-options-open]");
            if (inlineVideoOptionsOpen && root.contains(inlineVideoOptionsOpen)) {
                const video = inlineVideoTarget(root, inlineVideoOptionsOpen);
                if (video) {
                    e.preventDefault();
                    e.stopPropagation();
                    openInlineVideoOptions(root, video);
                    return;
                }
            }

            const inlineVideoPlay = inlineVideoEventTarget(root, e);
            if (inlineVideoPlay && root.contains(inlineVideoPlay) && !closestElement(e.target, ".dbx-cms-context-menu")) {
                if (!closestElement(e.target, "[data-cms-inline-video-options-open]")
                    && (closestElement(e.target, ".dbx-cms-inline-video-play") || !inlineVideoPlay.querySelector(".dbx-cms-inline-video-player, iframe, video"))) {
                    e.preventDefault();
                    e.stopPropagation();
                    playInlineVideoBlock(root, inlineVideoPlay);
                    return;
                }
                if (!closestElement(e.target, "[data-cms-inline-video-options-open]") && !isInlineVideoResizeHandleEvent(inlineVideoPlay, e)) {
                    e.preventDefault();
                    e.stopPropagation();
                    openInlineVideoOptions(root, inlineVideoPlay);
                    return;
                }
            }

            const mediaInsert = closestElement(e.target, "[data-cms-media-insert]");
            if (mediaInsert && root.contains(mediaInsert)) {
                const item = closestElement(mediaInsert, ".dbx-cms-media-item");
                const url = item?.getAttribute("data-url") || "";
                if (url) insertMarker(root, "media:" + url);
            }

            const mediaEmbed = closestElement(e.target, "[data-cms-media-embed]");
            if (mediaEmbed && root.contains(mediaEmbed)) {
                const item = closestElement(mediaEmbed, ".dbx-cms-media-item");
                const mediaRow = mediaRowFromItem(item);
                insertMediaRow(root, mediaRow);
                setLocalMediaSlot(root, mediaRow.id, "inline");
                return;
            }
        });

        const search = qs(root, "[data-cms-search]");
        if (search) {
            bindTreeSearchClear(root, search);
            search.addEventListener("input", () => renderTree(root));
        }

        qsa(root, "[data-cms-upload], [data-cms-hero-upload]").forEach(upload => {
            if (upload.__dbxCmsUploadBound) return;
            upload.__dbxCmsUploadBound = true;
            upload.addEventListener("submit", e => {
                e.preventDefault();
                if (upload.hasAttribute("data-cms-hero-upload")) {
                    uploadMedia(root, cfg, upload, {
                        afterUpload(data) {
                            if (!data || !data.row || !data.row.id) return;
                            state(root).heroPreviewRow = data.row;
                            setField(root, "hero_image_id", data.row.id);
                            if (!getField(root, "hero_template") || getField(root, "hero_template") === "parent" || getField(root, "hero_template") === "none") {
                                setField(root, "hero_template", "image-hero");
                            }
                            markDirty(root);
                            renderHeroPreview(root);
                            status(root, "Hero-Bild hochgeladen.", "success");
                        }
                    });
                    return;
                }
                uploadMedia(root, cfg, upload);
            });
            upload.addEventListener("change", () => updateUploadLabel(upload));
        });

        qsa(root, "[data-cms-external-video]").forEach(form => {
            if (form.__dbxCmsExternalVideoBound) return;
            form.__dbxCmsExternalVideoBound = true;
            form.addEventListener("submit", e => {
                e.preventDefault();
                addExternalVideo(root, cfg, form);
            });
        });

        root.addEventListener("keydown", e => {
            const row = closestElement(e.target, ".dbx-cms-tree-row");
            if (!row || !root.contains(row) || row.getAttribute("data-type") !== "folder") return;
            if (e.key === "ArrowLeft") {
                e.preventDefault();
                toggleTreeFolder(root, row, true);
            } else if (e.key === "ArrowRight") {
                e.preventDefault();
                toggleTreeFolder(root, row, false);
            }
        });

        root.addEventListener("change", e => {
            const contentTemplate = closestElement(e.target, '[data-cms-field="template"], [data-cms-folder-field="template"]');
            if (contentTemplate && root.contains(contentTemplate)) {
                syncContentTemplateEditLink(root, contentTemplate);
            }
            const uploadForm = closestElement(e.target, "[data-cms-upload], [data-cms-hero-upload], [data-cms-browser-upload]");
            if (uploadForm && root.contains(uploadForm)) updateUploadLabel(uploadForm);
            const mediaFilter = closestElement(e.target, "[data-cms-media-filter]");
            if (mediaFilter && root.contains(mediaFilter)) {
                state(root).mediaFilter = mediaFilter.value || "all";
                syncUploadSlot(root);
                renderMedia(root);
            }
            const ratioInput = closestElement(e.target, "[data-cms-bulk-resize-ratio]");
            if (ratioInput && root.contains(ratioInput)) {
                status(root, ratioInput.checked ? "Resize behaelt das Seitenverhaeltnis." : "Resize nutzt exakte Breite und Hoehe.", "info");
            }
            const heroTemplate = closestElement(e.target, '[data-cms-field="hero_template"], [data-cms-folder-field="hero_template"]');
            if (heroTemplate && root.contains(heroTemplate)) {
                applyHeroTemplateChoice(root, heroTemplate);
            }
        });

        root.addEventListener("input", e => {
            const editInput = closestElement(e.target, "[data-cms-media-edit-width], [data-cms-media-edit-height]");
            if (editInput && root.contains(editInput)) {
                const modal = closestElement(editInput, "[data-cms-media-edit]");
                const ratio = qs(modal, "[data-cms-media-edit-ratio]");
                if (!modal || !ratio || !ratio.checked) return;
                const row = modal.__dbxCmsEditRow || {};
                const w = qs(modal, "[data-cms-media-edit-width]");
                const h = qs(modal, "[data-cms-media-edit-height]");
                autoRatioValue(editInput, editInput === w ? h : w, Number(row.width || 0), Number(row.height || 0));
            }
        });

        root.addEventListener("dragover", e => {
            const dropzone = closestElement(e.target, "[data-cms-dropzone]");
            if (!dropzone || !root.contains(dropzone)) return;
            e.preventDefault();
            dropzone.classList.add("is-dragover");
        });

        root.addEventListener("dragleave", e => {
            const dropzone = closestElement(e.target, "[data-cms-dropzone]");
            if (!dropzone || !root.contains(dropzone)) return;
            dropzone.classList.remove("is-dragover");
        });

        root.addEventListener("drop", e => {
            const dropzone = closestElement(e.target, "[data-cms-dropzone]");
            if (!dropzone || !root.contains(dropzone)) return;
            e.preventDefault();
            dropzone.classList.remove("is-dragover");
            const form = closestElement(dropzone, "form");
            const files = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length ? e.dataTransfer.files : null;
            if (!form || !files || !files.length) return;
            if (!setUploadFiles(form, files)) {
                status(root, "Datei bitte ueber die Dateiauswahl waehlen.", "error");
            }
        });

        qsa(root, "[data-cms-field]").forEach(el => {
            if (el.__dbxCmsDirtyBound) return;
            el.__dbxCmsDirtyBound = true;
            syncCmsSelect(el);
            el.addEventListener("input", () => markDirty(root));
            el.addEventListener("change", () => {
                syncCmsSelect(el);
                if (el.getAttribute("data-cms-field") === "hero_template") applyHeroTemplateChoice(root, el);
                markDirty(root);
            });
        });

        root.addEventListener("dragstart", e => {
            const media = closestElement(e.target, ".dbx-cms-media-item");
            if (media && root.contains(media)) {
                e.dataTransfer.effectAllowed = "move";
                e.dataTransfer.setData("application/x-dbx-cms-media", media.getAttribute("data-media-id") || "");
                media.classList.add("is-dragging");
                return;
            }

            const row = closestElement(e.target, ".dbx-cms-tree-row");
            if (!row || !root.contains(row)) return;
            const treePress = root.__dbxCmsTreePress;
            root.__dbxCmsTreeDrag = treePress && treePress.row === row ? {
                row,
                x: treePress.x,
                y: treePress.y,
                dragIntent: treePress.dragIntent,
                dropped: false
            } : null;
            e.dataTransfer.effectAllowed = "move";
            e.dataTransfer.setData("application/x-dbx-cms-node", JSON.stringify({
                type: row.getAttribute("data-type"),
                id: Number(row.getAttribute("data-id") || 0)
            }));
            row.classList.add("is-dragging");
        });

        root.addEventListener("dragend", e => {
            clearEditorDropMarks(root);
            state(root).dragMarker = null;
            state(root).pointerDragMarker = null;

            const media = closestElement(e.target, ".dbx-cms-media-item");
            if (media) media.classList.remove("is-dragging");
            qsa(root, ".dbx-cms-media-item.is-drop-before,.dbx-cms-media-item.is-drop-after").forEach(el => el.classList.remove("is-drop-before", "is-drop-after"));

            const row = closestElement(e.target, ".dbx-cms-tree-row");
            if (row) row.classList.remove("is-dragging");
            qsa(root, ".dbx-cms-tree-row.is-drop-target,.dbx-cms-tree-row.is-drop-before,.dbx-cms-tree-row.is-drop-after").forEach(el => {
                el.classList.remove("is-drop-target", "is-drop-before", "is-drop-after");
            });

            const treeDrag = root.__dbxCmsTreeDrag;
            root.__dbxCmsTreeDrag = null;
            if (row && treeDrag && treeDrag.row === row && !treeDrag.dropped && !treeDrag.dragIntent) {
                const moved = Math.abs((e.clientX || treeDrag.x) - treeDrag.x) > 8 || Math.abs((e.clientY || treeDrag.y) - treeDrag.y) > 8;
                if (!moved) activateTreeRow(root, cfg, row);
            }
        });

        root.addEventListener("dragover", e => {
            const media = closestElement(e.target, ".dbx-cms-media-item");
            if (media && root.contains(media) && Array.from(e.dataTransfer.types || []).includes("application/x-dbx-cms-media")) {
                e.preventDefault();
                e.dataTransfer.dropEffect = "move";
                qsa(root, ".dbx-cms-media-item.is-drop-before,.dbx-cms-media-item.is-drop-after").forEach(el => {
                    if (el !== media) el.classList.remove("is-drop-before", "is-drop-after");
                });
                const rect = media.getBoundingClientRect();
                const before = e.clientY < rect.top + rect.height / 2;
                media.classList.toggle("is-drop-before", before);
                media.classList.toggle("is-drop-after", !before);
                return;
            }

            const row = closestElement(e.target, ".dbx-cms-tree-row");
            if (!row || !root.contains(row)) return;
            const type = row.getAttribute("data-type");
            const hasData = Array.from(e.dataTransfer.types || []).includes("application/x-dbx-cms-node");
            if (!hasData) return;
            let data = null;
            try {
                data = JSON.parse(e.dataTransfer.getData("application/x-dbx-cms-node") || "{}");
            } catch (_) {}
            if (!data || !data.type || !data.id) return;
            if (root.__dbxCmsTreeDrag) root.__dbxCmsTreeDrag.dragIntent = true;
            if (data.type === "folder" && type !== "folder") return;

            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            qsa(root, ".dbx-cms-tree-row.is-drop-target,.dbx-cms-tree-row.is-drop-before,.dbx-cms-tree-row.is-drop-after").forEach(el => {
                if (el !== row) el.classList.remove("is-drop-target", "is-drop-before", "is-drop-after");
            });

            const rect = row.getBoundingClientRect();
            const y = rect.height ? (e.clientY - rect.top) / rect.height : 0.5;
            if (type === "page") {
                const before = y < 0.5;
                row.classList.toggle("is-drop-before", before);
                row.classList.toggle("is-drop-after", !before);
            } else if (data.type === "folder") {
                const before = y < 0.28;
                const after = y > 0.72;
                row.classList.toggle("is-drop-before", before);
                row.classList.toggle("is-drop-after", after);
                row.classList.toggle("is-drop-target", !before && !after);
            } else {
                row.classList.add("is-drop-target");
            }
        });

        root.addEventListener("dragleave", e => {
            const surface = editorSurface(root);
            if (surface && !surface.contains(e.relatedTarget)) {
                clearEditorDropMarks(root);
            }

            const media = closestElement(e.target, ".dbx-cms-media-item");
            if (media && !media.contains(e.relatedTarget)) media.classList.remove("is-drop-before", "is-drop-after");

            const row = closestElement(e.target, ".dbx-cms-tree-row");
            if (row && !row.contains(e.relatedTarget)) row.classList.remove("is-drop-target", "is-drop-before", "is-drop-after");
        });

        root.addEventListener("drop", e => {
            const media = closestElement(e.target, ".dbx-cms-media-item");
            if (media && root.contains(media) && Array.from(e.dataTransfer.types || []).includes("application/x-dbx-cms-media")) {
                e.preventDefault();
                const fromId = e.dataTransfer.getData("application/x-dbx-cms-media");
                const from = qsa(root, ".dbx-cms-media-item").find(item => item.getAttribute("data-media-id") === fromId);
                if (!from || from === media) return;
                if (from.parentElement !== media.parentElement) return;
                const rect = media.getBoundingClientRect();
                const before = e.clientY < rect.top + rect.height / 2;
                media.classList.remove("is-drop-before", "is-drop-after");
                if (before) media.parentElement.insertBefore(from, media);
                else media.parentElement.insertBefore(from, media.nextSibling);
                saveMediaOrder(root, cfg, media.parentElement);
                return;
            }

            const row = closestElement(e.target, ".dbx-cms-tree-row");
            if (!row || !root.contains(row)) return;
            e.preventDefault();
            if (root.__dbxCmsTreeDrag) {
                root.__dbxCmsTreeDrag.dropped = true;
                root.__dbxCmsTreeDrag.dragIntent = true;
            }
            row.classList.remove("is-drop-target", "is-drop-before", "is-drop-after");

            let data = null;
            try {
                data = JSON.parse(e.dataTransfer.getData("application/x-dbx-cms-node") || "{}");
            } catch (_) {}
            if (!data || !data.type || !data.id) return;

            const targetType = row.getAttribute("data-type");
            const targetId = Number(row.getAttribute("data-id") || 0);
            let targetFolder = targetType === "folder" ? targetId : Number(row.getAttribute("data-folder") || 0);
            const position = {};

            if (targetType === "folder" && data.type === "folder") {
                const rect = row.getBoundingClientRect();
                const y = rect.height ? (e.clientY - rect.top) / rect.height : 0.5;
                if (y < 0.28 || y > 0.72) {
                    targetFolder = Number(row.getAttribute("data-folder") || 0);
                    if (y < 0.28) position.before_id = targetId;
                    else position.after_id = targetId;
                }
            } else if (targetType === "page" && data.type === "page") {
                const rect = row.getBoundingClientRect();
                if (e.clientY < rect.top + rect.height / 2) position.before_id = targetId;
                else position.after_id = targetId;
            } else if (targetType !== "folder") {
                return;
            }

            if (Number(data.id) === targetId && (position.before_id || position.after_id)) {
                return;
            }

            if (data.type === "folder" && Number(data.id) === targetFolder) {
                status(root, "Ordner kann nicht in sich selbst verschoben werden.", "error");
                return;
            }

            moveNode(root, cfg, data.type, Number(data.id), targetFolder, position);
        });
    }

    bindGlobalCmsEditorEvents();
    bindInlineVideoOptionsDocumentEvents();

    function bindTreeRuntimeEnhancements(root) {
        qsa(root || document, ".dbx-cms[data-dbx]").forEach(el => {
            const cfg = cmsConfig(el);
            bindAdminTreeOutsideClose(el, cfg || {});
            bindViewTreeHover(el, cfg || {});
        });
    }

    const cmsFeature = {
        scope: "element",
        priority: "mid",
        css: [
            ["css", "design", "c-cms.css"],
            ["css", "design", "c-form.css"],
            ["css", "design", "c-grid.css"]
        ],
        js: [
            ["js", "lib", "ajax.js"],
            ["js", "lib", "form.js"]
        ],

        init(el, cfg) {
            if (!el || el.__dbxCmsReady) return;
            el.__dbxCmsReady = true;
            qsa(el, "select[data-cms-select-title]").forEach(syncCmsSelectTitle);
            el.addEventListener("focusin", event => syncCmsSelectTitle(event.target));
            el.addEventListener("change", event => syncCmsSelectTitle(event.target));
            const initialCid = Number(cfg && cfg.cid ? cfg.cid : 0) || 0;
            const initialFid = Number(cfg && cfg.fid ? cfg.fid : 0) || 0;
            if (initialFid > 0) {
                state(el).selectedFolder = initialFid;
                state(el).selectedPage = 0;
                state(el).selectedType = "folder";
            } else if (initialCid > 0) {
                state(el).selectedPage = initialCid;
                state(el).selectedType = "page";
            }
            bindStickyHeaderOffset(el);
            bindTreeFlyoutPosition(el);
            initTreePanelState(el, cfg || {});
            initRightPanelState(el);
            bindTreeRuntimeEnhancements(el);
            if (!isViewMode(cfg || {})) {
                ensureJodit().then(ok => {
                    if (ok) {
                        initEditor(el, cfg || {});
                    } else {
                        dbx.warn("[cms] Jodit not loaded, fallback contenteditable active");
                    }
                });
            }
            try {
                bind(el, cfg || {});
            } catch (err) {
                dbx.error("[cms] bind failed", err);
            }
            loadTree(el, cfg || {});
        },

        rescan(root) {
            qsa(root || document, ".dbx-cms[data-dbx]").forEach(el => {
                if (el.__dbxCmsReady) return;
                const cfgList = dbx.parseData(el.getAttribute("data-dbx"));
                const cfg = cfgList.find(item => item.lib === LIB) || {};
                this.init(el, cfg);
            });
        }
    };

    dbx.cmsMediaBrowser = {
        open: openMediaBrowser
    };

    dbx.feature.register(LIB, cmsFeature);

})(window, document);
