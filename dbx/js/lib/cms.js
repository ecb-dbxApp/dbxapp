/*!
 * dbxapp cms.js
 * Content CMS runtime and stable editor core.
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

    const CMS_MODULE_ASSETS = Object.freeze({
        page: [["js", "lib", "cms-page.js"]],
        tree: [["js", "lib", "cms-tree.js"]],
        media: [["js", "lib", "cms-media.js"]],
        language: [["js", "lib", "cms-language.js"]],
        joditImage: [["js", "lib", "cms-jodit-image.js"]]
    });
    const cmsModuleFactories = Object.create(null);
    const cmsModules = Object.create(null);
    const cmsModulePromises = Object.create(null);

    function cmsRuntimeContext() {
        return Object.freeze({
            dbx,
            LIB,
            TREE_UI_ID,
            PANEL_UI_ID,
            addExternalVideo,
            apiUrl,
            applyFormSecurity,
            applyHeroTemplateChoice,
            applyInlineMediaAssignment,
            applyInlineVideoOptions,
            applySaveSuccessStatus,
            applyTreePanelState,
            assignMedia,
            autoRatioValue,
            bindAdminTreeOutsideClose,
            bindCmsKeyboardShortcuts,
            bindTreeSearchClear,
            bindViewTreeHover,
            browserCfg,
            buildPageFolderOptions,
            bulkResizeMedia,
            canEditImage,
            cfgUrl,
            clearCmsLoading,
            clearDirtyAfterSave,
            clearEditorDropMarks,
            closeInlineVideoOptionsWindow,
            closeTreePanel,
            closestElement,
            cmsConfig,
            cmsLngParams,
            cmsText,
            collectInlineMediaIdsFromEditor,
            commitMediaEditAction,
            confirmPickMediaBrowser,
            currentMediaSlot,
            deleteCurrentCms,
            deleteMedia,
            duplicateCurrentPage,
            editorSurface,
            ensureAjax,
            ensureConfirm,
            ensureOpenWin,
            ensureTreeLoaded,
            escapeHtml,
            escapeTextareaValue,
            escapeTooltipAttr,
            execEditorCommand,
            executeUnusedMediaMaintenance,
            extractProcessHtml,
            fetchHtml,
            fetchJson,
            fillLngProvisionContentPreviews,
            findNode,
            focusInlineMediaInEditor,
            forceCollapseTreePanel,
            formatBytes,
            formatTranslateWarnings,
            getEditorHtml,
            getField,
            getFolderField,
            handleLngAfterSave,
            hideFolderEditor,
            inlineVideoEventTarget,
            inlineVideoTarget,
            insertMarker,
            insertMediaRow,
            insertModPlaceholder,
            isExternalVideoRow,
            isFolderCollapsed,
            isImageRow,
            isMasterLngCfg,
            isInlineVideoResizeHandleEvent,
            isViewMode,
            loadMedia,
            loadPage,
            loadTree,
            loadViewPage,
            markDirty,
            mediaBrowserFormHtml,
            mediaOriginLabel,
            mediaPreviewHtml,
            mediaRowFromItem,
            mediaRowWithUsage,
            modPlaceholderValues,
            normalizeModPlaceholders,
            openContentAdmin,
            openInlineVideoOptions,
            openLngDeleteDialog,
            openLngProvisionDialog,
            openMediaBrowser,
            openMediaEdit,
            openPreview,
            playInlineVideoBlock,
            previewMediaCrop,
            qs,
            qsa,
            removeInlineMediaFromEditor,
            removeMedia,
            renderHeroPreview,
            renderMedia,
            renderMediaMaintenanceHome,
            renderSeoPreview,
            renderTree,
            resetLngSync,
            revealTreeSelection,
            saveCurrentCms,
            saveEditorSelection,
            saveMediaOrder,
            scheduleMediaLazyLoad,
            selectedMediaBrowserRows,
            selectedUploadFiles,
            serializeCmsMarkers,
            setDirty,
            setEditorHtml,
            setField,
            setFolderCollapsed,
            setLocalMediaSlot,
            setSaving,
            setSelectedFolder,
            setSelectedPage,
            setSelectedType,
            setUploadFiles,
            setupMediaLazyImages,
            showFolderEditor,
            showTreePanel,
            startMediaMaintenance,
            state,
            status,
            suppressDirtyFor,
            syncEditorAfterContextAction,
            syncUploadSlot,
            toggleMediaBrowserSelection,
            toggleRightPanel,
            toggleTreeFolder,
            toggleTreePanel,
            updateCurrentSelectionTitle,
            updateHeaderActionTooltips,
            updateUploadLabel,
            updateViewPageTitle,
            uploadMedia,
            upsertLocalMediaRow
        });
    }

    function registerCmsModule(name, factory) {
        if (!name || typeof factory !== "function") return;
        cmsModuleFactories[name] = factory;
    }

    function instantiateCmsModule(name) {
        if (cmsModules[name]) return cmsModules[name];
        const factory = cmsModuleFactories[name];
        if (typeof factory !== "function") {
            throw new Error(`CMS-Modul nicht registriert: ${name}`);
        }
        const api = factory(cmsRuntimeContext());
        if (!api || typeof api !== "object") {
            throw new Error(`CMS-Modul ohne API: ${name}`);
        }
        cmsModules[name] = api;
        return api;
    }

    function ensureCmsModule(name) {
        if (cmsModules[name]) return Promise.resolve(cmsModules[name]);
        if (cmsModulePromises[name]) return cmsModulePromises[name];
        if (cmsModuleFactories[name]) {
            try {
                return Promise.resolve(instantiateCmsModule(name));
            } catch (err) {
                return Promise.reject(err);
            }
        }
        const assets = CMS_MODULE_ASSETS[name];
        if (!assets) return Promise.reject(new Error(`Unbekanntes CMS-Modul: ${name}`));
        cmsModulePromises[name] = loadAssets(assets)
            .then(() => instantiateCmsModule(name))
            .catch(err => {
                delete cmsModulePromises[name];
                throw err;
            });
        return cmsModulePromises[name];
    }

    function callCmsModule(name, method, args) {
        const api = cmsModules[name];
        if (api && typeof api[method] === "function") {
            return api[method](...(args || []));
        }
        return ensureCmsModule(name).then(
            loaded => {
                if (typeof loaded[method] !== "function") {
                    throw new Error(`CMS-Modulmethode fehlt: ${name}.${method}`);
                }
                return loaded[method](...(args || []));
            },
            err => {
                dbx.error(`[cms] ${name} module load failed`, err);
                const root = args && args[0] && args[0].nodeType === 1 ? args[0] : null;
                if (root) status(root, `CMS-Modul ${name} konnte nicht geladen werden.`, "error");
                return false;
            }
        );
    }

    dbx.cmsRuntime = Object.freeze({
        register: registerCmsModule,
        load: ensureCmsModule,
        has: name => !!cmsModules[name]
    });

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
        select.dataset.dbxTooltip = option ? String(option.text || "") : "";
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
            treeLoaded: false,
            treeLoading: false,
            treePromise: null,
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

    function confirmPickMediaBrowser(...args) {
        return callCmsModule("media", "confirmPickMediaBrowser", args);
    }

    function isViewMode(cfg) {
        return String((cfg && cfg.mode) || "").toLowerCase() === "view";
    }

    function syncStickyHeaderOffset(root) {
        // Ein CMS in einem dbx-Fenster scrollt in dessen eigenem Body. Der
        // App-Header liegt ausserhalb dieses Scrollcontainers und darf deshalb
        // nicht als sticky Abstand eingerechnet werden. Andernfalls entsteht
        // oberhalb der CMS-Kopfleiste eine sichtbare, menuehohe Leerstelle.
        const inWindow = !!closestElement(root, ".dbx-window-body");
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
        const height = inWindow
            ? 0
            : (header ? Math.ceil(Math.min(headerBottom || fallbackBottom || 0, 120)) : 0);
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
        if (window.ResizeObserver) {
            root.__dbxCmsStickyResizeObserver = new ResizeObserver(() => syncStickyHeaderOffset(root));
            const cmsHead = qs(root, ".dbx-cms-head");
            if (cmsHead) root.__dbxCmsStickyResizeObserver.observe(cmsHead);
            const header = document.getElementById("dbxHeader");
            if (header && !closestElement(root, ".dbx-window-body")) {
                root.__dbxCmsStickyResizeObserver.observe(header);
            }
        }
        window.addEventListener("resize", () => syncStickyHeaderOffset(root), { passive: true });
    }

    function settleStickyHeaderOffset(root) {
        if (root.__dbxCmsStickySettleTimer) {
            window.clearTimeout(root.__dbxCmsStickySettleTimer);
        }
        let attempts = 0;
        const check = () => {
            root.__dbxCmsStickySettleTimer = null;
            if (!root.isConnected) return;
            syncStickyHeaderOffset(root);
            attempts += 1;
            // dbx-Fenster koennen ihre Endgroesse nach dem AJAX-Inhalt noch
            // animiert annehmen. Der begrenzte Settle-Lauf folgt dieser einen
            // Initialisierung und erzeugt keine Timer bei Editor-Eingaben.
            if (attempts < 10) {
                root.__dbxCmsStickySettleTimer = window.setTimeout(check, 120);
            }
        };
        window.requestAnimationFrame(check);
    }

    function waitForCmsCriticalStyles(root, done) {
        const styleId = root.getAttribute("data-cms-critical-style");
        const selector = styleId
            ? `link[data-dbx-cms-critical="${String(styleId).replace(/"/g, "\\\"")}"]`
            : "link[data-dbx-cms-critical]";
        const links = qsa(document, selector);
        const pending = links.filter(link => !link.sheet);
        if (!pending.length) {
            root.classList.remove("dbx-cms-booting");
            done();
            return;
        }

        let remaining = pending.length;
        let finished = false;
        const finish = () => {
            if (finished) return;
            finished = true;
            window.clearTimeout(timer);
            root.classList.remove("dbx-cms-booting");
            if (root.isConnected) done();
        };
        const settled = () => {
            remaining -= 1;
            if (remaining <= 0) finish();
        };
        pending.forEach(link => {
            link.addEventListener("load", settled, { once: true });
            link.addEventListener("error", settled, { once: true });
        });
        const timer = window.setTimeout(finish, 2000);
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
            btn.setAttribute("data-dbx-tooltip", collapsed
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
        if (expanded) ensureTreeLoaded(root, cmsConfig(root));
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

    function initTreePanelState(root) {
        // Der Content-Baum ist eine teure Sekundaeransicht. Jede neue CMS-
        // Instanz startet geschlossen; Daten und Zeilen werden erst nach einer
        // bewussten Benutzeraktion geladen.
        applyTreePanelState(root, true);
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
            btn.setAttribute("data-dbx-tooltip", collapsed
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

    function toggleTreePanel(root, cfg) {
        const collapsed = !root.classList.contains("is-tree-collapsed");
        clearTreeHoverTimers(root);
        setTreeHoverExpanded(root, false);
        applyTreePanelState(root, collapsed);
        if (dbx.uiSet) dbx.uiSet(LIB, PANEL_UI_ID, "treeCollapsed", collapsed);
        if (!collapsed) ensureTreeLoaded(root, cfg || cmsConfig(root));
    }

    function closeTreePanel(root) {
        if (!root || root.classList.contains("is-tree-collapsed")) return;
        clearTreeHoverTimers(root);
        setTreeHoverExpanded(root, false);
        applyTreePanelState(root, true);
        if (dbx.uiSet) dbx.uiSet(LIB, PANEL_UI_ID, "treeCollapsed", true);
    }

    function showTreePanel(root) {
        if (!root) return;
        clearTreeHoverTimers(root);
        setTreeHoverExpanded(root, false);
        applyTreePanelState(root, false);
        if (dbx.uiSet) dbx.uiSet(LIB, PANEL_UI_ID, "treeCollapsed", false);
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
        // Die aktive Seite ist auch bei geschlossenem Baum bekannt. Erst ein
        // bereits geladener Baum muss neu gerendert und aufgeklappt werden.
        // So bleibt der Tree-Code bis zur ersten bewussten Tree-Aktion lazy.
        if (!s.treeLoaded) return;
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
        const workplace = qs(container, ".jodit-workplace") || qs(root, ".jodit-workplace");
        const source = qs(container, ".jodit-source textarea, .jodit-source") || qs(root, ".jodit-source textarea, .jodit-source");
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

        const base = 430;
        // Die bisherige Messung setzte Container, Workplace und Editor vor
        // jedem Lesen kurz auf min-height:0. Jodit kann diese Aenderung bei
        // einer spaeten Selection-/Change-Meldung sichtbar zeichnen; der
        // Inhalt sprang dann nach dem Setzen des Cursors geringfuegig. Die
        // natuerliche Inhaltshoehe laesst sich ohne jede Layout-Mutation aus
        // den sichtbaren Kindgrenzen bestimmen.
        const surfaceRect = surface.getBoundingClientRect ? surface.getBoundingClientRect() : null;
        const surfaceStyle = window.getComputedStyle ? window.getComputedStyle(surface) : null;
        const paddingBottom = surfaceStyle ? (parseFloat(surfaceStyle.paddingBottom) || 0) : 0;
        let contentBottom = surfaceRect ? surfaceRect.top : 0;
        Array.from(surface.children || []).forEach(child => {
            if (!child.getBoundingClientRect) return;
            const rect = child.getBoundingClientRect();
            if (rect && Number.isFinite(rect.bottom)) contentBottom = Math.max(contentBottom, rect.bottom);
        });
        let contentHeight = surfaceRect
            ? Math.ceil(Math.max(0, contentBottom - surfaceRect.top + paddingBottom))
            : Math.ceil(surface.scrollHeight || 0);
        if (source && source.offsetParent !== null) contentHeight = Math.max(contentHeight, Math.ceil(source.scrollHeight || 0));
        const nextHeight = Math.max(base, contentHeight);
        const value = nextHeight + "px";

        group.style.setProperty("--dbx-cms-editor-min-height", value);
        sized.forEach(el => {
            if (!el.style) return;
            el.style.height = "auto";
            el.style.maxHeight = "none";
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
    }

    function bindEditorHeight(root) {
        if (!root || root.__dbxCmsEditorHeightBound) return;
        const surface = editorSurface(root);
        if (!surface) return;
        const instance = getEditorInstance(root);
        const eventRoot = instance && instance.container ? instance.container : surface;
        root.__dbxCmsEditorHeightBound = true;

        const resizeHandler = () => scheduleEditorHeight(root);
        const inputHandler = e => {
            // Präsentationswechsel von Jodit (z. B. Vollbild) erzeugen
            // synthetische input/change-Ereignisse. Nur eine echte Eingabe des
            // Anwenders darf den schnellen Dirty-Pfad aktivieren.
            if (e.isTrusted === false) return;
            const source = closestElement(e.target, ".jodit-source");
            if (e.target !== surface && !surface.contains(e.target) && !source) return;
            root.__dbxCmsUserEditPending = true;
            scheduleEditorHeight(root);
        };
        const loadHandler = e => {
            if (e.target && (e.target === surface || surface.contains(e.target))) scheduleEditorHeight(root);
        };
        const ownerWindow = root.closest ? root.closest(".dbx-window") : null;
        const closeHandler = e => {
            if (!ownerWindow || !e.detail || e.detail.element !== ownerWindow) return;
            window.removeEventListener("resize", resizeHandler);
            eventRoot.removeEventListener("input", inputHandler, true);
            eventRoot.removeEventListener("load", loadHandler, true);
            document.removeEventListener("dbx:openwin-before-close", closeHandler);
        };

        root.__dbxCmsEditorHeightHandlers = { resizeHandler, inputHandler, loadHandler, closeHandler };
        window.addEventListener("resize", resizeHandler, { passive: true });
        eventRoot.addEventListener("input", inputHandler, true);
        eventRoot.addEventListener("load", loadHandler, true);
        document.addEventListener("dbx:openwin-before-close", closeHandler);
    }

    function restoreJoditFullscreenLayer(root) {
        const layer = root && root.__dbxCmsJoditFullscreenLayer;
        if (!layer || !layer.active) return;

        layer.active = false;
        document.removeEventListener("keydown", layer.keyHandler, true);
        if (layer.placeholder && layer.placeholder.parentNode) {
            layer.placeholder.parentNode.insertBefore(layer.container, layer.placeholder);
            layer.placeholder.parentNode.removeChild(layer.placeholder);
        }
        layer.container.removeAttribute("data-dbx-layer");
        layer.container.removeAttribute("data-dbx-escape-owner");
        layer.container.removeAttribute("data-dbx-cms-fullsize-layer");
        layer.container.style.removeProperty("z-index");
        if (layer.portal && layer.portal.parentNode) layer.portal.parentNode.removeChild(layer.portal);
        layer.portal = null;
        layer.placeholder = null;
        root.__dbxCmsUserEditPending = false;
        scheduleEditorHeight(root);
    }

    function enterJoditFullscreenLayer(root) {
        const layer = root && root.__dbxCmsJoditFullscreenLayer;
        if (!layer || layer.active || !layer.container.parentNode) return;

        layer.active = true;
        layer.placeholder = document.createComment("dbx-cms-jodit-fullscreen");
        layer.container.parentNode.insertBefore(layer.placeholder, layer.container);

        layer.portal = document.createElement("div");
        layer.portal.className = "dbx-cms-editor-group dbx-cms-editor-fullsize-portal";
        layer.portal.setAttribute("data-dbx-cms-fullsize-portal", "1");
        document.body.appendChild(layer.portal);
        layer.portal.appendChild(layer.container);

        layer.container.setAttribute("data-dbx-layer", "editor-fullscreen");
        layer.container.setAttribute("data-dbx-escape-owner", "editor-fullscreen");
        layer.container.setAttribute("data-dbx-cms-fullsize-layer", "1");
        const zIndex = dbx.uiLayer.next({ floor: 5000, step: 20, exclude: [layer.container] });
        layer.container.style.setProperty("z-index", String(zIndex), "important");
        root.__dbxCmsUserEditPending = false;
        document.addEventListener("keydown", layer.keyHandler, true);
    }

    function syncJoditFullscreenLayer(root) {
        const layer = root && root.__dbxCmsJoditFullscreenLayer;
        if (!layer) return;
        if (layer.container.classList.contains("jodit_fullsize")) enterJoditFullscreenLayer(root);
        else restoreJoditFullscreenLayer(root);
    }

    function bindJoditFullscreenLayer(root) {
        if (!root || root.__dbxCmsJoditFullscreenLayer) return;
        const instance = getEditorInstance(root);
        const container = instance && instance.container ? instance.container : null;
        if (!container) return;

        const ownerWindow = root.closest ? root.closest(".dbx-window") : null;
        const layer = {
            container,
            ownerWindow,
            active: false,
            placeholder: null,
            portal: null,
            observer: null,
            keyHandler: null,
            closeHandler: null
        };
        root.__dbxCmsJoditFullscreenLayer = layer;

        layer.keyHandler = e => {
            if (e.key !== "Escape" || !layer.active) return;
            if (dbx.uiLayer && typeof dbx.uiLayer.top === "function") {
                const owner = dbx.uiLayer.top({ selector: "[data-dbx-escape-owner]" });
                if (owner && owner !== container) return;
            }
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
            const button = qs(container, ".jodit-toolbar-button_fullsize button");
            if (button) button.click();
        };
        layer.closeHandler = e => {
            if (!ownerWindow || !e.detail || e.detail.element !== ownerWindow) return;
            restoreJoditFullscreenLayer(root);
            layer.observer.disconnect();
            document.removeEventListener("keydown", layer.keyHandler, true);
            document.removeEventListener("dbx:openwin-before-close", layer.closeHandler);
        };
        layer.observer = new MutationObserver(() => syncJoditFullscreenLayer(root));
        layer.observer.observe(container, { attributes: true, attributeFilter: ["class"] });
        document.addEventListener("dbx:openwin-before-close", layer.closeHandler);
        syncJoditFullscreenLayer(root);
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
        el.setAttribute("data-dbx-tooltip", s.dirty
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
            saveBtn.setAttribute("data-dbx-tooltip", saveTitle);
            saveBtn.setAttribute("aria-label", saveTitle);
        }
        if (deleteBtn) {
            deleteBtn.setAttribute("data-dbx-tooltip", deleteTitle);
            deleteBtn.setAttribute("aria-label", deleteTitle);
        }
        if (duplicateBtn) {
            const canDuplicate = !isFolder && state(root).selectedType === "page" && !state(root).duplicating;
            const duplicateTooltip = canDuplicate
                ? cmsText(root, "duplicate_title", "Ausgewählte Seite duplizieren")
                : cmsText(root, "duplicate_select_title", "Zum Duplizieren zuerst eine Seite auswählen");
            duplicateBtn.disabled = !canDuplicate;
            duplicateBtn.setAttribute("data-dbx-tooltip", duplicateTooltip);
            duplicateBtn.setAttribute("aria-label", duplicateTooltip);
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

    function handleLngAfterSave(root, cfg, data) {
        if (!data || !isMasterLngCfg(cfg, root)) return Promise.resolve(false);
        const targets = Array.isArray(data.lng_sync_targets) ? data.lng_sync_targets : [];
        if (Number(data.open_lng_provision) !== 1 && targets.length === 0) {
            return Promise.resolve(false);
        }
        return callCmsModule("language", "handleLngAfterSave", [root, cfg, data]);
    }

    function openLngProvisionDialog(...args) {
        return callCmsModule("language", "openLngProvisionDialog", args);
    }

    function resetLngSync(...args) {
        return callCmsModule("language", "resetLngSync", args);
    }

    function openLngDeleteDialog(...args) {
        return callCmsModule("language", "openLngDeleteDialog", args);
    }

    function applySaveSuccessStatus(root, data, message) {
        const saveStatus = formatLngSaveStatus(message, data || {});
        status(root, saveStatus.text, saveStatus.type);
        clearDirtyAfterSave(root);
        return saveStatus;
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
            if (dbx.utilities && dbx.utilities.leaveGuard) {
                dbx.utilities.leaveGuard.allowIfInternal(url);
            }
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
            ? ` data-cms-video-url="${escapeHtml(videoUrl)}" data-cms-video-type="${escapeHtml(row.media_type || "")}" data-cms-video-mime="${escapeHtml(row.mime || "")}" data-cms-video-muted="0" data-cms-video-align="left"`
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
        const name = String(marker || "").replace(/^dbx:/i, "").trim().toLowerCase() || "marker";
        return ["hero_text", "hero-text", "herotext"].includes(name) ? "hero" : name;
    }

    function cmsMarkerClassName(marker) {
        return cmsMarkerName(marker).replace(/[^a-z0-9_-]+/gi, "-") || "marker";
    }

    function cmsMarkerLabel(marker, label) {
        const labels = {
            "dbx:hero": "Hero",
            "dbx:split": "col-2 Trenner",
            "dbx:col2": "col-2 Trenner",
            "dbx:col3a": "col-3a Trenner",
            "dbx:col3b": "col-3b Trenner",
            "dbx:header": "Header",
            "dbx:teaser": "Header",
            "dbx:footer": "Footer",
            "dbx:pagebreak": "Druck Seitenumbruch"
        };
        const canonicalMarker = "dbx:" + cmsMarkerName(marker);
        if (canonicalMarker === "dbx:hero") return "Hero";
        return label || labels[canonicalMarker] || canonicalMarker || "dbx:marker";
    }

    function cmsMarkerHtml(marker, label) {
        if (marker === "dbx:split") marker = "dbx:col2";
        const name = cmsMarkerName(marker);
        marker = "dbx:" + name;
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

    function dedupeSingletonMarkers(container) {
        if (!container) return;
        const seen = new Set();
        qsa(container, ".dbx-cms-marker,[data-dbx-marker]").forEach(marker => {
            const name = markerNameFromElement(marker);
            if (name !== "hero") return;
            if (seen.has(name)) {
                marker.remove();
                return;
            }
            seen.add(name);
        });
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
            `data-cms-video-muted="${options.muted ? "1" : "0"}"`,
            `data-cms-video-align="${inlineVideoAlignValue(options.align)}"`
        ];
        if (options.width) attrs.push(`data-cms-video-width="${escapeHtml(options.width)}"`);
        if (options.height) attrs.push(`data-cms-video-height="${escapeHtml(options.height)}"`);
        return " " + attrs.join(" ");
    }

    function inlineVideoOptionsFromElement(el) {
        const media = el ? qs(el, ".dbx-cms-inline-video-thumb,.dbx-cms-inline-video-empty,video,iframe,img") : null;
        const attr = name => String((el && el.getAttribute && el.getAttribute(name)) || (media && media.getAttribute && media.getAttribute(name)) || "");
        const marginLeft = String(el?.style?.marginLeft || "").toLowerCase();
        const marginRight = String(el?.style?.marginRight || "").toLowerCase();
        let align = inlineVideoAlignValue(attr("data-cms-video-align"), "");
        if (!align && marginLeft === "auto" && marginRight === "auto") align = "center";
        if (!align && marginLeft === "auto") align = "right";
        return {
            width: cssSizeValue(attr("data-cms-video-width") || el?.style?.width || media?.style?.width || ""),
            height: cssSizeValue(attr("data-cms-video-height") || el?.style?.height || media?.style?.height || ""),
            autoplay: attr("data-cms-video-autoplay") === "1",
            loop: attr("data-cms-video-loop") === "1",
            muted: attr("data-cms-video-muted") === "1",
            align: align || "left"
        };
    }

    function inlineVideoAlignValue(value, fallback) {
        value = String(value || "").trim().toLowerCase();
        if (value === "center" || value === "zentriert") return "center";
        if (value === "right" || value === "rechts") return "right";
        if (value === "left" || value === "links") return "left";
        return fallback === undefined ? "left" : fallback;
    }

    function applyInlineVideoAlignment(wrapper, align) {
        if (!wrapper) return;
        align = inlineVideoAlignValue(align);
        wrapper.setAttribute("data-cms-video-align", align);
        wrapper.style.float = "";
        if (align === "center") {
            wrapper.style.marginLeft = "auto";
            wrapper.style.marginRight = "auto";
            return;
        }
        if (align === "right") {
            wrapper.style.marginLeft = "auto";
            wrapper.style.marginRight = "0px";
            return;
        }
        wrapper.style.marginLeft = "";
        wrapper.style.marginRight = "";
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
            el.setAttribute("data-cms-video-align", inlineVideoAlignValue(options.align));
        });
    }

    function inlineVideoOptionsButtonHtml() {
        return '<button type="button" class="dbx-cms-inline-video-options-btn" data-cms-inline-video-options-open contenteditable="false" tabindex="-1" data-dbx-tooltip="Video Optionen" aria-label="Video Optionen"><i class="bi bi-sliders"></i></button>';
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
        const align = qs(modal, "[data-cms-video-options-align]");
        const autoplay = qs(modal, "[data-cms-video-options-autoplay]");
        const loop = qs(modal, "[data-cms-video-options-loop]");
        const muted = qs(modal, "[data-cms-video-options-muted]");
        if (width) width.value = options.width || "";
        if (height) height.value = options.height || "";
        if (align) align.value = options.align || "left";
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
        const align = inlineVideoAlignValue(qs(modal, "[data-cms-video-options-align]")?.value || "left");
        const autoplay = qs(modal, "[data-cms-video-options-autoplay]")?.checked;
        const loop = qs(modal, "[data-cms-video-options-loop]")?.checked;
        let muted = qs(modal, "[data-cms-video-options-muted]")?.value === "1";
        if (autoplay) muted = true;
        const options = {
            width,
            height,
            autoplay: !!autoplay,
            loop: !!loop,
            muted: !!muted,
            align
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
        applyInlineVideoAlignment(media, align);
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
            if (!qs(modal, "[data-cms-video-options-align]")) {
                const heightLabel = qs(modal, "[data-cms-video-options-height]")?.closest("label");
                if (heightLabel && heightLabel.parentNode) {
                    const alignLabel = document.createElement("label");
                    alignLabel.innerHTML = 'Ausrichtung <select class="form-select form-select-sm" data-cms-video-options-align><option value="left">Links</option><option value="center">Horizontal zentriert</option><option value="right">Rechts</option></select>';
                    heightLabel.insertAdjacentElement("afterend", alignLabel);
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
                <label>Ausrichtung <select class="form-select form-select-sm" data-cms-video-options-align><option value="left">Links</option><option value="center">Horizontal zentriert</option><option value="right">Rechts</option></select></label>
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
        qsa(wrap, "video").forEach(video => {
            if (closestElement(video, ".dbx-cms-inline-video-block")) return;

            const source = qs(video, "source[src]");
            const url = String(video.getAttribute("src") || source?.getAttribute("src") || "");
            const idMatch = String(
                video.getAttribute("data-cms-media-id") || source?.getAttribute("data-cms-media-id") || url
            ).match(/(?:dbx_mid=)?([0-9]+)/i);
            const id = Number(idMatch && idMatch[1] || 0);
            if (id <= 0) return;

            const options = {
                width: cssSizeValue(video.getAttribute("width") || video.style?.width || ""),
                height: cssSizeValue(video.getAttribute("height") || video.style?.height || ""),
                autoplay: video.hasAttribute("autoplay"),
                loop: video.hasAttribute("loop"),
                muted: video.hasAttribute("muted")
            };
            const row = mediaRowById(root, id) || {
                id,
                url,
                mime: source?.getAttribute("type") || "video/mp4",
                media_type: "video",
                thumb_url: video.getAttribute("poster") || "",
                title: video.getAttribute("aria-label") || video.getAttribute("title") || "Video"
            };
            const holder = document.createElement("div");
            holder.innerHTML = `<figure class="dbx-cms-inline-media dbx-cms-inline-video-block"${inlineVideoDataAttributes(row, id, options)}>${mediaPlayerInnerHtml(row, id, options)}</figure>`;
            const figure = holder.firstElementChild;
            if (figure) video.replaceWith(figure);
        });
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
        const html = inlineVideoEditorPlayerHtml(row, id, inlineVideoOptionsFromElement(wrapper));
        if (!html) return false;
        wrapper.innerHTML = html;
        wrapper.classList.add("is-playing");
        syncEditorDom(root);
        return true;
    }

    function renderHeroPreview(root) {
        const preview = qs(root, "[data-cms-hero-preview]");
        if (!preview) return;

        const idValue = String(getField(root, "hero_image_id") || "");
        const clearButton = qs(root, "[data-cms-action='clear-hero-media']");
        if (clearButton) {
            const canClear = Number(idValue) > 0;
            clearButton.disabled = !canClear;
            clearButton.setAttribute("aria-disabled", canClear ? "false" : "true");
        }

        const templateValue = String(getField(root, "hero_template") || "").toLowerCase();
        if (templateValue === "none" || templateValue === "no-hero") {
            preview.innerHTML = '<div class="dbx-cms-hero-preview-empty">'
                + escapeHtml(cmsText(root, "hero_preview_empty", "Kein Hero-Bild ausgewählt."))
                + '</div>';
            return;
        }

        const id = Number(idValue);
        const s = state(root);
        const rows = s.mediaRows || [];
        const row = rows.find(item => Number(item.id || 0) === id)
            || (s.heroPreviewRow && Number(s.heroPreviewRow.id || 0) === id ? s.heroPreviewRow : null);

        if (idValue === "parent") {
            const parentRow = s.heroParentPreviewRow || null;
            if (parentRow && parentRow.url) {
                const folderName = parentRow.parent_folder_name || parentRow.folder_name || "Parent";
                preview.innerHTML = `<img src="${escapeHtml(parentRow.thumb_url || parentRow.url)}" alt="${escapeHtml(parentRow.alt || parentRow.title || cmsText(root, "hero_image_alt", "Hero-Bild"))}"><figcaption class="dbx-cms-hero-preview-origin">${escapeHtml(folderName)}</figcaption>`;
                return;
            }
            preview.innerHTML = '<div class="dbx-cms-hero-preview-empty">'
                + escapeHtml(cmsText(root, "hero_parent_empty", "Kein Hero im übergeordneten Ordner."))
                + '</div>';
            return;
        }

        if (!id) {
            preview.innerHTML = '<div class="dbx-cms-hero-preview-empty">'
                + escapeHtml(cmsText(root, "hero_preview_empty", "Kein Hero-Bild ausgewählt."))
                + '</div>';
            return;
        }

        if (!row || !row.url) {
            preview.innerHTML = '<div class="dbx-cms-hero-preview-empty">'
                + escapeHtml(cmsText(root, "hero_preview_loading", "Hero-Bild wird geladen."))
                + '</div>';
            return;
        }

        const isImage = String(row.mime || "").startsWith("image/") || /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(row.file_name || row.url || "");
        if (!isImage) {
            preview.innerHTML = '<div class="dbx-cms-hero-preview-empty">'
                + escapeHtml(cmsText(root, "hero_preview_not_image", "Das gewählte Medium ist kein Bild."))
                + '</div>';
            return;
        }

        preview.innerHTML = `<img src="${escapeHtml(row.thumb_url || row.url)}" alt="${escapeHtml(row.alt || row.title || cmsText(root, "hero_image_alt", "Hero-Bild"))}">`;
    }

    function applyHeroTemplateChoice(root, source) {
        const isFolder = source && source.getAttribute && source.getAttribute("data-cms-field-scope") === "folder";
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

    function toggleMediaBrowserSelection(...args) {
        return callCmsModule("media", "toggleMediaBrowserSelection", args);
    }

    function selectedMediaBrowserRows(...args) {
        return callCmsModule("media", "selectedMediaBrowserRows", args);
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

    function renderMediaMaintenanceHome(...args) {
        return callCmsModule("media", "renderMediaMaintenanceHome", args);
    }

    function startMediaMaintenance(...args) {
        return callCmsModule("media", "startMediaMaintenance", args);
    }

    function executeUnusedMediaMaintenance(...args) {
        return callCmsModule("media", "executeUnusedMediaMaintenance", args);
    }

    function openMediaBrowser(...args) {
        return callCmsModule("media", "openMediaBrowser", args);
    }

    function openModBrowser(...args) {
        return callCmsModule("media", "openModBrowser", args);
    }

    function openModPlaceholderOptions(...args) {
        return callCmsModule("media", "openModPlaceholderOptions", args);
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
        let loading = false;

        const activate = event => {
            const panel = closestElement(event.target, ".jodit-dialog__panel, .jodit-dialog");
            if (!panel || !/Bildeigenschaften|Image properties|Bild/i.test(String(panel.textContent || ""))) return;
            if (loading) return;
            loading = true;
            ensureCmsModule("joditImage").then(api => {
                document.removeEventListener("focusin", activate, true);
                document.removeEventListener("pointerover", activate, true);
                api.bind(root, cfg || {});
            }).catch(err => {
                loading = false;
                dbx.error("[cms] Jodit image module load failed", err);
                status(root, "Bilddialog konnte nicht initialisiert werden.", "error");
            });
        };

        document.addEventListener("focusin", activate, true);
        document.addEventListener("pointerover", activate, true);
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
            { label: "Hero", marker: "dbx:hero" },
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
            popup: function (jodit, current, close) {
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
            popup: function (jodit, current, close) {
                const box = document.createElement("div");
                box.className = "dbx-cms-marker-menu";
                bootstrapComponentItems(root).forEach(item => {
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
            }
        };
        window.Jodit.defaultOptions.controls.dbxTextStyle = {
            tooltip: cmsText(root, "editor_text_format", "Textformatierung"),
            icon: cmsTextStyleIcon(),
            text: "",
            popup: function (jodit, current, close) {
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
                        applyEditorAlignment(root, item.command);
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
            sourceEditor: "area",
            beautifyHTML: false,
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
                    const surface = editorSurface(root);
                    // Jodit liefert hier bereits den aktuellen HTML-Wert. Die
                    // vollständige DOM-Normalisierung erfolgt bei strukturellen
                    // Aktionen und vor dem Speichern, nicht bei jedem Zeichen.
                    const html = String(this.value || (surface ? surface.innerHTML : "") || "");
                    if (root.__dbxCmsApplyingEditorHtml) return;
                    // Nur ein echtes Browser-Eingabeereignis darf diesen
                    // schnellen Pfad aktivieren. Runtime-Mutationen von Jodit
                    // werden ignoriert; eine Vollkopie des grossen Editor-DOM
                    // bei jedem change waere deutlich zu langsam.
                    if (!root.__dbxCmsUserEditPending) return;
                    root.__dbxCmsUserEditPending = false;
                    setField(root, "content", html);
                    markDirty(root);
                    // Echte Texteingaben werden bereits vom input-Listener
                    // der Hoehenlogik erfasst. Jodit meldet jedoch auch beim
                    // blossen Fokussieren nachtraeglich ein change-Event; eine
                    // erneute Hoehenmessung hier verursachte den sichtbaren
                    // Sprung nach dem Setzen des Cursors.
                    scheduleEditorMediaRender(root, html);
                },
                focus: function () {
                    window.requestAnimationFrame(() => saveEditorSelection(root));
                },
                afterSelectionChange: function () {
                    window.requestAnimationFrame(() => saveEditorSelection(root));
                }
            }
        });
        bindJoditFullscreenLayer(root);
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
        button.setAttribute("data-dbx-tooltip", cmsText(root, "editor_save_all", "Alles speichern"));
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
        // Bereits gespeicherte, inhaltslose Editor-Absaetze werden einmalig
        // in einem abgetrennten Container entfernt. Der sichtbare Editor muss
        // dafuer weder aufgebaut, geleert noch erneut gesetzt werden.
        const incoming = document.createElement("div");
        incoming.innerHTML = html || "";
        cleanEditorRuntimeNodes(incoming);
        html = incoming.innerHTML || "";
        root.__dbxCmsApplyingEditorHtml = true;
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
            // Laufzeitanker und Cursorhilfen nur in einer Kopie entfernen.
            // Das fruehere Reinigen und erneute Setzen des Live-DOM erzeugte
            // beim Laden kurz sichtbare bzw. wechselnde Leerzeilen.
            const repairedHtml = surface ? editorHtmlSnapshot(surface) : getEditorHtml(root);
            if (/(<video\b|<iframe\b|dbx-cms-inline-video-block)/i.test(html) && !/(<video\b|<iframe\b|dbx-cms-inline-video-block)/i.test(repairedHtml || "")) {
                setField(root, "content", html || "");
            } else {
                setField(root, "content", repairedHtml || "");
            }
            bindBootstrapCardEditingGuards(root);
            bindEditorMarkerEventsRetry(root);
            scheduleEditorHeight(root);
            root.__dbxCmsApplyingEditorHtml = false;
        }, 0);
    }

    function editorHtmlSnapshot(surface) {
        if (!surface || !surface.cloneNode) return "";
        const snapshot = surface.cloneNode(true);
        cleanEditorRuntimeNodes(snapshot);
        return snapshot.innerHTML || "";
    }

    function getEditorHtml(root) {
        const instance = getEditorInstance(root);
        const surface = editorSurface(root);
        if (surface) {
            normalizeBootstrapComponents(surface);
            normalizeInlineMediaLayout(surface);
            const html = editorHtmlSnapshot(surface);
            setField(root, "content", html);
            bindBootstrapCardEditingGuards(root);
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
        if (!node.parentNode || !surface.contains(node)) {
            node = surface.lastChild;
            if (!node) {
                range.selectNodeContents(surface);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
                state(root).editorRange = range.cloneRange();
                return true;
            }
        }
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

    function commitEditorCaretHosts(container) {
        qsa(container, "[data-dbx-cms-caret-host]").forEach(host => {
            const layout = host.getAttribute("data-dbx-cms-caret-host");
            host.innerHTML = String(host.innerHTML || "").replace(/\u200b/g, "");
            host.removeAttribute("data-dbx-cms-caret-host");
            host.removeAttribute("contenteditable");
            host.removeAttribute("tabindex");
            if (layout === "inline") {
                const parent = host.parentNode;
                if (!parent) return;
                while (host.firstChild) parent.insertBefore(host.firstChild, host);
                host.remove();
            } else if (isEmptyEditorBlock(host)) {
                host.remove();
            }
        });
    }

    function cleanEditorRuntimeNodes(container) {
        normalizeCommentMarkers(container);
        syncInlineVideoBlockSizes(container);
        commitEditorCaretHosts(container);
        qsa(container, "[data-dbx-cms-button-caret-anchor],[data-dbx-cms-element-caret-anchor]").forEach(anchor => anchor.remove());
        qsa(container, "[data-dbx-cms-movable-block]").forEach(block => {
            block.removeAttribute("data-dbx-cms-movable-block");
            block.removeAttribute("draggable");
        });
        qsa(container, "[data-dbx-cms-movable-button]").forEach(button => {
            button.removeAttribute("data-dbx-cms-movable-button");
            if (button.getAttribute("draggable") === "true") button.removeAttribute("draggable");
        });
        qsa(container, "[data-dbx-cms-editable-badge]").forEach(badge => {
            badge.removeAttribute("data-dbx-cms-editable-badge");
            if (badge.getAttribute("contenteditable") === "true") badge.removeAttribute("contenteditable");
            if (badge.getAttribute("tabindex") === "0") badge.removeAttribute("tabindex");
        });
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
            if (wrapper.classList.contains("dbx-cms-inline-video-block")) {
                wrapper.removeAttribute("contenteditable");
                const videoMedia = qs(wrapper, "[data-cms-media-id], img[src*='dbx_mid='], video[src*='dbx_mid='], source[src*='dbx_mid=']");
                const videoIdMatch = String(
                    wrapper.getAttribute("data-cms-media-id")
                    || videoMedia?.getAttribute("data-cms-media-id")
                    || videoMedia?.getAttribute("src")
                    || ""
                ).match(/(?:dbx_mid=)?([0-9]+)/i);
                const videoId = Number(videoIdMatch && videoIdMatch[1] || 0);
                if (videoId > 0) wrapper.setAttribute("data-cms-media-id", String(videoId));
            }
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
        p.setAttribute("data-dbx-tooltip", "Fehlende Mediendatei auswählen, Entf zum Löschen");
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
            const wrapperWidth = cssSizeValue(wrapper.style.width || wrapper.getAttribute("data-cms-video-width") || "");
            const wrapperHeight = cssSizeValue(wrapper.style.height || wrapper.getAttribute("data-cms-video-height") || "");
            let width = wrapperWidth || size.width;
            let height = wrapperHeight || size.height;
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

    function refreshEditorCaretHint(root, explicitRange) {
        const surface = editorSurface(root);
        const s = state(root);
        // Immer die aktuelle Browser-Selection verwenden. Eine geklonte
        // editorRange kann Jodits Caret bereits um einen Frame verfehlen.
        // Solange der eigene Cursor sichtbar ist, blendet CSS die native Caret
        // aus; dadurch existiert auch optisch nur genau eine Position.
        const range = explicitRange || s.editorContextPasteRange || currentEditorCaretRange(surface);
        if (!range) {
            hideEditorCaretHint(root);
            return;
        }
        if (!surface || !range || !range.collapsed || !rangeInsideSurface(surface, range)) {
            hideEditorCaretHint(root);
            return;
        }
        if (s.editorCaretHintFrame) window.cancelAnimationFrame(s.editorCaretHintFrame);
        s.editorCaretHintFrame = window.requestAnimationFrame(() => {
            s.editorCaretHintFrame = null;
            const liveRange = explicitRange || s.editorContextPasteRange || currentEditorCaretRange(surface);
            if (!liveRange || !rangeInsideSurface(surface, liveRange)) return hideEditorCaretHint(root);
            const rect = editorCaretRect(liveRange);
            if (!rect) return hideEditorCaretHint(root);
            const surfaceRect = surface.getBoundingClientRect();
            if ((!rect.width && !rect.height)
                || rect.right < surfaceRect.left - 4
                || rect.left > surfaceRect.right + 4
                || rect.bottom < surfaceRect.top - 4
                || rect.top > surfaceRect.bottom + 4) {
                return hideEditorCaretHint(root);
            }
            let hint = s.editorCaretHint;
            if (!hint || !hint.parentNode) {
                hint = document.createElement("span");
                hint.className = "dbx-cms-editor-caret-hint";
                hint.setAttribute("data-cms-editor-caret-hint", "");
                hint.setAttribute("aria-hidden", "true");
                document.body.appendChild(hint);
                s.editorCaretHint = hint;
            }
            const height = Math.max(11, Math.min(15, Math.round((rect.height || 16) * .78)));
            hint.style.left = Math.round(rect.left - 1) + "px";
            hint.style.top = Math.round(rect.top + Math.max(0, ((rect.height || height) - height) / 2)) + "px";
            hint.style.height = height + "px";
            surface.classList.add("is-dbx-cms-caret-preview");
        });
    }

    function currentEditorCaretRange(surface) {
        if (!surface) return null;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel || !sel.rangeCount) return null;
        const range = sel.getRangeAt(0);
        return range.collapsed && rangeInsideSurface(surface, range) ? range : null;
    }

    function editorCaretRect(range) {
        if (!range) return null;
        const usable = rect => rect && Number.isFinite(rect.left)
            && Number.isFinite(rect.top) && ((rect.height || 0) > 0 || (rect.width || 0) > 0);
        try {
            const direct = range.getBoundingClientRect();
            if (usable(direct)) return direct;
            const clientRects = Array.from(range.getClientRects ? range.getClientRects() : []);
            const clientRect = clientRects.find(usable);
            if (clientRect) return clientRect;
        } catch (_) {}

        // Kollabierte Ranges an Text- und Elementgrenzen liefern je nach
        // Browser gelegentlich ein leeres Rechteck. Die Kante eines direkt
        // benachbarten Zeichens bzw. Elements ist stabil und benoetigt keinen
        // temporaeren Messknoten im Editor-DOM.
        const node = range.startContainer;
        const offset = Number(range.startOffset || 0);
        const doc = node && (node.ownerDocument || document);
        if (!node || !doc || !doc.createRange) return null;
        try {
            const probe = doc.createRange();
            if (node.nodeType === 3 && String(node.nodeValue || "").length) {
                const length = String(node.nodeValue || "").length;
                const before = offset > 0;
                probe.setStart(node, before ? offset - 1 : offset);
                probe.setEnd(node, before ? offset : Math.min(length, offset + 1));
                const rect = probe.getBoundingClientRect();
                if (usable(rect)) return {
                    left: before ? rect.right : rect.left,
                    right: before ? rect.right : rect.left,
                    top: rect.top,
                    bottom: rect.bottom,
                    width: 0,
                    height: rect.height
                };
            }
            if (node.nodeType === 1) {
                const sibling = offset > 0 ? node.childNodes[offset - 1] : node.childNodes[offset];
                const element = sibling && (sibling.nodeType === 1 ? sibling : sibling.parentElement);
                const rect = element && element.getBoundingClientRect ? element.getBoundingClientRect() : null;
                if (usable(rect)) return {
                    left: offset > 0 ? rect.right : rect.left,
                    right: offset > 0 ? rect.right : rect.left,
                    top: rect.top,
                    bottom: rect.bottom,
                    width: 0,
                    height: rect.height
                };
                const hostRect = node.getBoundingClientRect ? node.getBoundingClientRect() : null;
                if (usable(hostRect)) return {
                    left: hostRect.left,
                    right: hostRect.left,
                    top: hostRect.top,
                    bottom: hostRect.bottom,
                    width: 0,
                    height: hostRect.height
                };
            }
        } catch (_) {}
        return null;
    }

    function hideEditorCaretHint(root) {
        const s = state(root);
        const surface = editorSurface(root);
        if (s.editorCaretHintTimer) {
            window.clearTimeout(s.editorCaretHintTimer);
            s.editorCaretHintTimer = null;
        }
        if (s.editorCaretHintFrame) {
            window.cancelAnimationFrame(s.editorCaretHintFrame);
            s.editorCaretHintFrame = null;
        }
        const hint = s.editorCaretHint || qs(document, "[data-cms-editor-caret-hint]");
        if (hint) hint.remove();
        s.editorCaretHint = null;
        if (surface) surface.classList.remove("is-dbx-cms-caret-preview");
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
        normalizeBootstrapComponents(surface);
        const html = surface ? editorHtmlSnapshot(surface) : getEditorHtml(root);
        setField(root, "content", html || "");
        bindBootstrapCardEditingGuards(root);
        if (!options.silent) markDirty(root);
        scheduleEditorHeight(root);
        scheduleEditorMediaRender(root, html);
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
                    handleLngAfterSave(root, cfg, data);
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

    function setEditorCaretBesideElement(root, element, side) {
        const surface = editorSurface(root);
        if (!surface || !element || !element.parentNode || !surface.contains(element)) return false;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel) return false;
        const range = doc.createRange();
        if (side === "before") range.setStartBefore(element);
        else range.setStartAfter(element);
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
        selectEditorMarker(root, null);
        return setEditorCaretBesideElement(root, marker, "before");
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
            Array.from(marker.classList).forEach(className => {
                if (className.indexOf("dbx-cms-marker-") === 0) marker.classList.remove(className);
            });
            marker.classList.add("dbx-cms-marker", "dbx-cms-marker-" + cmsMarkerClassName(name));
            marker.setAttribute("data-dbx-marker", "dbx:" + name);
            if (name === "hero" || !marker.getAttribute("data-label")) marker.setAttribute("data-label", cmsMarkerLabel("dbx:" + name));
            marker.setAttribute("contenteditable", "false");
            marker.setAttribute("draggable", "false");
            marker.setAttribute("tabindex", "0");
            marker.setAttribute("role", "button");
            marker.removeAttribute("title");
            marker.setAttribute("data-dbx-tooltip", "Marker auswählen, ziehen zum Verschieben, Entf zum Löschen");
        });
        hoistEditorMarkersToSurface(surface);
        dedupeSingletonMarkers(surface);
        dedupeAdjacentMarkers(surface);
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
        refreshEditorCaretHint(root, range);
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
        const el = closestBootstrapComponent(root, target) || closestElement(
            target,
            ".dbx-cms-marker,.dbx-cms-inline-media,.dbx-cms-inline-media-missing-wrap,figure,table,img,video,hr,p,h1,h2,h3,h4,h5,h6,ul,ol,dl,pre,blockquote,section,article"
        );
        return el && el !== surface && surface.contains(el) ? el : null;
    }

    /**
     * Kontextmenü und Drag-and-drop verwenden bewusst dieselbe Blockauflösung.
     * Dadurch sind Textblöcke, Medien, Marker und Bootstrap-Komponenten mit
     * allen angebotenen Bearbeitungswegen konsistent erreichbar.
     */
    function movableEditorContextBlock(root, target) {
        return removableEditorContextTarget(root, target);
    }

    function movableEditorButtonBlock(root, target) {
        const surface = editorSurface(root);
        const button = closestElement(target, "a.btn[data-dbx-cms-movable-button]");
        if (!surface || !button || !surface.contains(button)) return null;
        const paragraph = closestElement(button, "p");
        if (paragraph && surface.contains(paragraph)
            && paragraph.children.length === 1
            && paragraph.firstElementChild === button) {
            return paragraph;
        }
        return button;
    }

    function clearEditorButtonDrag(root) {
        const s = state(root);
        const drag = s.editorButtonDrag;
        if (drag && drag.block) drag.block.classList.remove("is-dbx-cms-dragging");
        const surface = editorSurface(root);
        qsa(surface, ".is-dbx-cms-drop-target").forEach(el => el.classList.remove("is-dbx-cms-drop-target"));
        s.editorButtonDrag = null;
    }

    function closestBootstrapComponent(root, target) {
        const surface = editorSurface(root);
        if (!surface || !target) return null;
        const row = closestElement(target, ".row");
        if (row && surface.contains(row) && (bootstrapRowColumns(row).length || qs(row, ".card"))) {
            return row;
        }
        const tabsPart = closestElement(target, ".nav-tabs,.tab-content");
        if (tabsPart && surface.contains(tabsPart)) {
            const tabsWrap = tabsPart.parentElement;
            const tabsChildren = tabsWrap ? Array.from(tabsWrap.children || []) : [];
            if (tabsWrap && surface.contains(tabsWrap)
                && tabsChildren.some(child => child.classList?.contains("nav-tabs"))
                && tabsChildren.some(child => child.classList?.contains("tab-content"))) {
                return tabsWrap;
            }
            return tabsPart;
        }
        const el = closestElement(target, ".alert,.card,.list-group,.accordion,.table-responsive");
        if (!el || !surface.contains(el)) return null;
        return el;
    }

    /**
     * CMS-Spaltenboxen verwenden Bootstrap-rows mit direkten col-Kindern.
     * Die Layoutaktionen ändern ausschließlich die Spaltenklassen; Inhalte,
     * Medien und Module bleiben erhalten. Beim Auflösen werden die Inhalte
     * in ihrer bisherigen Reihenfolge aus den Spalten herausgehoben.
     */
    function bootstrapRowColumns(row) {
        if (!row || !row.classList || !row.classList.contains("row")) return [];
        return Array.from(row.children || []).filter(column => {
            return column.classList && Array.from(column.classList).some(name => /^col(?:$|-)/.test(name));
        });
    }

    function bootstrapColumnRow(root, target) {
        const surface = editorSurface(root);
        const row = closestElement(target, ".row");
        return row && surface && surface.contains(row) && bootstrapRowColumns(row).length ? row : null;
    }

    function clearBootstrapColumnClasses(column) {
        if (!column || !column.classList) return;
        Array.from(column.classList).forEach(name => {
            if (/^col(?:$|-)/.test(name)) column.classList.remove(name);
        });
    }

    function finishBootstrapColumnAction(root, focusNode) {
        normalizeBootstrapComponents(editorSurface(root));
        syncEditorDom(root);
        markDirty(root);
        scheduleEditorHeight(root);
        if (focusNode && focusNode.parentNode) selectEditorNode(root, focusNode);
        return true;
    }

    function setBootstrapColumnLayout(root, row, mode) {
        const columns = bootstrapRowColumns(row);
        if (!columns.length) return false;
        columns.forEach(column => {
            clearBootstrapColumnClasses(column);
            column.classList.add("col-12");
            if (mode === "responsive") column.classList.add("col-md");
        });
        return finishBootstrapColumnAction(root, row);
    }

    function addBootstrapColumn(root, row) {
        const columns = bootstrapRowColumns(row);
        if (!row || !columns.length) return false;
        const doc = row.ownerDocument || document;
        const column = doc.createElement("div");
        const responsive = columns.some(existing => {
            return Array.from(existing.classList || []).some(name => /^col-(?:sm|md|lg|xl|xxl)(?:-|$)/.test(name));
        });
        column.className = responsive ? "col-12 col-md" : "col-12";
        const paragraph = doc.createElement("p");
        paragraph.textContent = cmsText(root, "editor_columns_new", "Neue Spalte");
        column.appendChild(paragraph);
        row.appendChild(column);
        return finishBootstrapColumnAction(root, column);
    }

    function dissolveBootstrapColumns(root, row) {
        const columns = bootstrapRowColumns(row);
        if (!row || !row.parentNode || !columns.length) return false;
        const doc = row.ownerDocument || document;
        const fragment = doc.createDocumentFragment();
        let firstMoved = null;
        Array.from(row.childNodes || []).forEach(node => {
            if (node.nodeType === 1 && columns.includes(node)) {
                while (node.firstChild) {
                    const child = node.firstChild;
                    if (!firstMoved && child.nodeType === 1) firstMoved = child;
                    fragment.appendChild(child);
                }
                return;
            }
            if (!firstMoved && node.nodeType === 1) firstMoved = node;
            fragment.appendChild(node);
        });
        row.parentNode.insertBefore(fragment, row);
        row.remove();
        return finishBootstrapColumnAction(root, firstMoved);
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
        // Verschachtelte Buttons sind eigenständige CMS-Elemente. Sie müssen
        // vor ihrer umgebenden Bootstrap-Komponente aufgelöst werden, damit
        // Kopieren, Ausschneiden und Drag-and-drop nicht z. B. die komplette
        // Alert-Box statt nur des Buttons erfassen.
        const button = movableEditorButtonBlock(root, target);
        if (button && surface.contains(button)) return button;
        const component = closestBootstrapComponent(root, target);
        if (component && surface.contains(component)) return component;
        const mod = inlineModTarget(root, target);
        if (mod && surface.contains(mod)) return mod;
        const missing = closestElement(target, ".dbx-cms-inline-media-missing-wrap");
        if (missing && surface.contains(missing)) return missing;
        const inlineMedia = closestElement(target, ".dbx-cms-inline-media");
        if (inlineMedia && surface.contains(inlineMedia)) return inlineMedia;
        const el = editorContextBlock(root, target);
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

    function editorContextBlockHtml(root, target) {
        const block = movableEditorContextBlock(root, target);
        const surface = editorSurface(root);
        if (!block || !surface || !surface.contains(block)) return "";
        const container = (surface.ownerDocument || document).createElement("div");
        container.appendChild(block.cloneNode(true));
        cleanEditorRuntimeNodes(container);
        return container.innerHTML;
    }

    function copyEditorContext(root, target) {
        const html = editorContextBlockHtml(root, target);
        if (html) {
            state(root).editorClipboardHtml = html;
            return clipboardWriteText(html).then(() => true);
        }
        restoreEditorSelection(root);
        const text = editorSelectionText(root);
        const copied = document.execCommand && document.execCommand("copy");
        if (!copied && text) return clipboardWriteText(text);
        return Promise.resolve(copied);
    }

    function cutEditorContext(root, target) {
        const block = movableEditorContextBlock(root, target);
        const html = editorContextBlockHtml(root, block);
        if (block && html) {
            state(root).editorClipboardHtml = html;
            return clipboardWriteText(html).finally(() => {
                if (block.matches && block.matches(".dbx-cms-marker")) {
                    removeEditorMarker(root, block);
                    return;
                }
                block.remove();
                syncEditorDom(root);
                markDirty(root);
            });
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

    function editorRangeIsInsertable(root, range) {
        const surface = editorSurface(root);
        if (!surface || !range || !rangeInsideSurface(surface, range)) return false;
        const start = nodeElement(range.startContainer);
        const locked = start ? closestElement(start, "[contenteditable='false']") : null;
        return !(locked && surface.contains(locked));
    }

    function restoreEditorContextPasteRange(root) {
        const s = state(root);
        const range = s.editorContextPasteRange;
        if (!editorRangeIsInsertable(root, range)) return false;
        s.editorRange = range.cloneRange();
        return restoreEditorSelection(root);
    }

    function editorClipboardInlineButtonHtml(root, html) {
        const surface = editorSurface(root);
        const doc = surface ? (surface.ownerDocument || document) : document;
        const template = doc.createElement("template");
        template.innerHTML = String(html || "");
        const children = Array.from(template.content.children || []);
        if (children.length === 1 && children[0].matches("a.btn")) {
            return children[0].outerHTML;
        }
        // Ein alleinstehender Bootstrap-Button liegt im Content üblicherweise
        // in einem Absatz. An einer Text-Caret-Position wird nur der Link
        // eingefügt, damit er z. B. direkt in einer Alert-Box stehen kann.
        if (children.length === 1 && children[0].tagName === "P") {
            const paragraph = children[0];
            if (paragraph.children.length === 1
                && paragraph.firstElementChild.matches("a.btn")
                && String(paragraph.textContent || "").trim() === String(paragraph.firstElementChild.textContent || "").trim()) {
                return paragraph.innerHTML;
            }
        }
        return "";
    }

    function alignEditorContextPasteRange(root, html) {
        if (!editorClipboardInlineButtonHtml(root, html)) return;
        const surface = editorSurface(root);
        const s = state(root);
        const range = s.editorContextPasteRange;
        if (!surface || !editorRangeIsInsertable(root, range)) return;
        const start = nodeElement(range.startContainer);
        const closedInline = start ? closestElement(start, "code,kbd,samp,a,button") : null;
        if (!closedInline || !surface.contains(closedInline) || !closedInline.parentNode) return;
        const doc = surface.ownerDocument || document;
        const aligned = doc.createRange();
        aligned.setStartAfter(closedInline);
        aligned.collapse(true);
        s.editorContextPasteRange = aligned;
    }

    function editorClipboardHtmlAtCaret(root, html) {
        return editorClipboardInlineButtonHtml(root, html) || String(html || "");
    }

    function insertEditorContextBlock(root, html, target) {
        const surface = editorSurface(root);
        const destination = movableEditorContextBlock(root, target);
        if (!surface || !html) return false;
        alignEditorContextPasteRange(root, html);
        if (restoreEditorContextPasteRange(root)) {
            state(root).editorContextPasteRange = null;
            insertEditorHtml(root, editorClipboardHtmlAtCaret(root, html));
            markDirty(root);
            refreshEditorCaretHint(root);
            return true;
        }
        state(root).editorContextPasteRange = null;
        if (!destination || !surface.contains(destination) || !destination.parentNode) {
            insertEditorHtml(root, html);
            return true;
        }
        const doc = surface.ownerDocument || document;
        const template = doc.createElement("template");
        template.innerHTML = html;
        const first = template.content.firstElementChild;
        destination.parentNode.insertBefore(template.content, destination.nextSibling);
        normalizeBootstrapComponents(surface);
        syncEditorDom(root);
        markDirty(root);
        if (first && first.parentNode) selectEditorNode(root, first);
        return true;
    }

    function rememberEditorContextPasteRange(root, x, y) {
        const surface = editorSurface(root);
        const range = editorRangeFromPoint(surface, x, y);
        const s = state(root);
        s.editorContextPasteRange = editorRangeIsInsertable(root, range) ? range.cloneRange() : null;
        if (s.editorContextPasteRange) refreshEditorCaretHint(root, s.editorContextPasteRange);
        return s.editorContextPasteRange;
    }

    function insertDraggedEditorBlockAtCaret(root, block) {
        const range = state(root).editorContextPasteRange;
        if (!block || !block.parentNode || !editorRangeIsInsertable(root, range)) return null;
        if (rangeIntersectsNode(range, block)) return null;

        let moved = block;
        if (block.tagName === "P"
            && block.children.length === 1
            && block.firstElementChild.matches("a.btn")
            && String(block.textContent || "").trim() === String(block.firstElementChild.textContent || "").trim()) {
            moved = block.firstElementChild;
        }
        const oldWrapper = moved === block ? null : block;
        if (moved.matches && moved.matches("a.btn")) {
            const start = nodeElement(range.startContainer);
            const closedInline = start ? closestElement(start, "code,kbd,samp,a,button") : null;
            if (closedInline && editorSurface(root)?.contains(closedInline) && closedInline.parentNode) {
                range.setStartAfter(closedInline);
            }
        }
        range.collapse(true);
        range.insertNode(moved);
        state(root).editorContextPasteRange = null;
        if (oldWrapper && !String(oldWrapper.textContent || "").trim() && !oldWrapper.children.length) oldWrapper.remove();
        setEditorCaretAfterNode(root, moved);
        return moved;
    }

    function pasteEditorContext(root, target) {
        const internalHtml = state(root).editorClipboardHtml || "";
        if (internalHtml) return Promise.resolve(insertEditorContextBlock(root, internalHtml, target));
        if (restoreEditorContextPasteRange(root)) {
            state(root).editorContextPasteRange = null;
        } else {
            restoreEditorSelection(root);
        }
        return clipboardReadText().then(text => {
            if (text) {
                if (document.execCommand && document.execCommand("insertText", false, text)) {
                    syncEditorDom(root);
                    return true;
                }
                insertEditorHtml(root, escapeHtml(text));
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
        if (img && surface && surface.contains(img)) return img;

        // Alte Inhalte können eine absolut positionierte Textebene über einem
        // Bild enthalten. Die Textebene darf die Bildaktionen nicht blockieren.
        const overlay = closestElement(target, ".position-absolute,[style*='position:absolute'],[style*='position: absolute']");
        const host = overlay?.parentElement || null;
        if (!host || !surface || !surface.contains(host)) return null;
        const isRelative = host.classList?.contains("position-relative")
            || /position\s*:\s*relative/i.test(host.getAttribute("style") || "");
        if (!isRelative) return null;
        return Array.from(host.children || []).find(child => child.tagName === "IMG") || null;
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
        rememberEditorContextPasteRange(root, e.clientX, e.clientY);
        const marker = closestElement(target, ".dbx-cms-marker");
        const missingMedia = contextMissingMediaTarget(root, target);
        const hasSelection = !!editorSelectionText(root);
        const img = contextImageTarget(root, target);
        const videoMedia = inlineVideoEventTarget(root, e);
        const modPlaceholder = inlineModTarget(root, target);
        const component = closestBootstrapComponent(root, target);
        const columnRow = bootstrapColumnRow(root, target);
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
        if (state(root).editorContextPasteRange) {
            refreshEditorCaretHint(root, state(root).editorContextPasteRange);
        }

        const menu = document.createElement("div");
        menu.className = "dbx-cms-context-menu";
        menu.setAttribute("role", "menu");
        menu.setAttribute("aria-label", cmsText(root, "editor_context_menu", "Editor-Kontextmenü"));

        const movable = movableEditorContextBlock(root, target);
        state(root).editorContextTarget = movable;
        const hasContextTarget = !!(marker || missingMedia || modPlaceholder || component || videoMedia || img || table || movable);
        const items = [
            [cmsText(root, "editor_context_undo", "Rückgängig"), "bi-arrow-counterclockwise", () => execEditorCommand(root, "undo"), false],
            [cmsText(root, "editor_context_redo", "Wiederholen"), "bi-arrow-clockwise", () => execEditorCommand(root, "redo"), false],
            [cmsText(root, "editor_context_select_all", "Alles markieren"), "bi-check2-square", () => selectEditorContents(root), false],
            [cmsText(root, "editor_context_block_up", "Block nach oben"), "bi-arrow-up", () => moveEditorContextBlock(root, movable, -1), !movable],
            [cmsText(root, "editor_context_block_down", "Block nach unten"), "bi-arrow-down", () => moveEditorContextBlock(root, movable, 1), !movable],
            [cmsText(root, "editor_context_module", "Modul-Platzhalter"), "bi-puzzle", () => openModPlaceholderOptions(root, modPlaceholder, cmsConfig(root) || {}), !modPlaceholder],
            [cmsText(root, "editor_context_video", "Video-Optionen"), "bi-camera-video", () => openInlineVideoOptions(root, videoMedia), !videoMedia],
            [cmsText(root, "editor_image_edit", "Bild bearbeiten"), "bi-pencil-square", () => openCmsImageEditor(root, img), !img],
            [cmsText(root, "editor_image_remove", "Bild aus Inhalt entfernen"), "bi-image-alt", () => removeEditorImage(root, img), !img],
            [cmsText(root, "editor_context_copy", "Kopieren"), "bi-clipboard", () => copyEditorContext(root, target), !hasSelection && !hasContextTarget],
            [cmsText(root, "editor_context_cut", "Ausschneiden"), "bi-scissors", () => cutEditorContext(root, target), !hasSelection && !hasContextTarget],
            [cmsText(root, "editor_context_paste", "Einfügen"), "bi-clipboard-plus", () => pasteEditorContext(root, movable), false],
            [cmsText(root, "editor_context_delete", "Löschen"), "bi-trash", () => deleteEditorContext(root, target), !hasSelection && !hasContextTarget]
        ];

        if (columnRow) {
            items.splice(
                5,
                0,
                [cmsText(root, "editor_columns_stacked", "Spalten untereinander"), "bi-layout-three-columns", () => setBootstrapColumnLayout(root, columnRow, "stacked"), false],
                [cmsText(root, "editor_columns_responsive", "Spalten nebeneinander"), "bi-layout-split", () => setBootstrapColumnLayout(root, columnRow, "responsive"), false],
                [cmsText(root, "editor_column_add", "Spalte hinzufügen"), "bi-plus-square", () => addBootstrapColumn(root, columnRow), false],
                [cmsText(root, "editor_columns_dissolve", "Spalten auflösen"), "bi-x-diamond", () => dissolveBootstrapColumns(root, columnRow), false]
            );
        }

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

        surface.addEventListener("dragstart", e => {
            const block = movableEditorContextBlock(root, e.target);
            if (!block) return;
            state(root).editorButtonDrag = { block };
            block.classList.add("is-dbx-cms-dragging");
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = "move";
                e.dataTransfer.setData("text/plain", "dbx-cms-button");
            }
        }, true);

        surface.addEventListener("dragover", e => {
            const drag = state(root).editorButtonDrag;
            if (!drag || !drag.block) return;
            const target = editorContextBlock(root, e.target);
            if (!target || target === drag.block || drag.block.contains(target)) return;
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = "move";
            rememberEditorContextPasteRange(root, e.clientX, e.clientY);
            qsa(surface, ".is-dbx-cms-drop-target").forEach(el => el.classList.remove("is-dbx-cms-drop-target"));
            target.classList.add("is-dbx-cms-drop-target");
        }, true);

        surface.addEventListener("drop", e => {
            const drag = state(root).editorButtonDrag;
            if (!drag || !drag.block || !drag.block.parentNode) return;
            const target = editorContextBlock(root, e.target);
            if (!target || target === drag.block || drag.block.contains(target) || !target.parentNode) {
                clearEditorButtonDrag(root);
                return;
            }
            e.preventDefault();
            rememberEditorContextPasteRange(root, e.clientX, e.clientY);
            let moved = insertDraggedEditorBlockAtCaret(root, drag.block);
            const insertedAtCaret = !!moved;
            if (!moved) {
                const rect = target.getBoundingClientRect();
                const after = Number(e.clientY || 0) >= rect.top + rect.height / 2;
                target.parentNode.insertBefore(drag.block, after ? target.nextSibling : target);
                moved = drag.block;
            }
            state(root).editorContextPasteRange = null;
            clearEditorButtonDrag(root);
            normalizeBootstrapComponents(surface);
            syncEditorDom(root);
            markDirty(root);
            if (insertedAtCaret) refreshEditorCaretHint(root);
            else selectEditorNode(root, moved);
        }, true);

        surface.addEventListener("dragend", () => clearEditorButtonDrag(root), true);
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

    function bootstrapComponentItems(root) {
        const openWinData = "lib=openWin|title=Information|width=900|height=80%|position=center-top|reload=1|minimizable=1|maximizable=1";
        const firstColumn = escapeHtml(cmsText(root, "editor_columns_first", "Inhalt der ersten Spalte."));
        const secondColumn = escapeHtml(cmsText(root, "editor_columns_second", "Inhalt der zweiten Spalte."));
        const thirdColumn = escapeHtml(cmsText(root, "editor_columns_third", "Inhalt der dritten Spalte."));
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
                label: cmsText(root, "editor_columns_two", "2 Spalten"),
                html: `<div class="row g-3"><div class="col-12 col-md"><p>${firstColumn}</p></div><div class="col-12 col-md"><p>${secondColumn}</p></div></div><p></p>`
            },
            {
                label: cmsText(root, "editor_columns_three", "3 Spalten"),
                html: `<div class="row g-3"><div class="col-12 col-md"><p>${firstColumn}</p></div><div class="col-12 col-md"><p>${secondColumn}</p></div><div class="col-12 col-md"><p>${thirdColumn}</p></div></div><p></p>`
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
                label: "Pills",
                html: '<div class="d-flex flex-wrap gap-2"><span class="badge text-bg-primary">Pill 1</span><span class="badge text-bg-secondary">Pill 2</span><span class="badge text-bg-success">Pill 3</span></div><p></p>'
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

    function openEditableBadgeTextInput(badge, surface) {
        if (!badge || !surface || !badge.isConnected) return;
        const doc = surface.ownerDocument || document;
        const previous = doc.__dbxCmsBadgeTextInput;
        if (previous && previous.isConnected) previous.remove();

        const initialText = String(badge.textContent || "").trim();
        const input = doc.createElement("input");
        input.type = "text";
        input.className = "form-control form-control-sm dbx-cms-badge-text-input";
        input.value = initialText;
        input.setAttribute("aria-label", "Pill-Text bearbeiten");
        input.dataset.dbxTooltip = "Enter: übernehmen · Escape: verwerfen";

        const rect = badge.getBoundingClientRect();
        input.style.position = "fixed";
        input.style.zIndex = "100000";
        input.style.left = Math.max(8, Math.min(rect.left, doc.documentElement.clientWidth - 168)) + "px";
        input.style.top = Math.max(8, Math.min(rect.top, doc.documentElement.clientHeight - 42)) + "px";
        input.style.width = Math.max(160, Math.min(320, rect.width + 80)) + "px";
        doc.body.appendChild(input);
        doc.__dbxCmsBadgeTextInput = input;

        let finished = false;
        const finish = commit => {
            if (finished) return;
            finished = true;
            const nextText = String(input.value || "").trim();
            input.remove();
            if (doc.__dbxCmsBadgeTextInput === input) doc.__dbxCmsBadgeTextInput = null;
            if (!commit || nextText === "" || nextText === initialText || !badge.isConnected) return;

            badge.textContent = nextText;
            const editorRoot = closestElement(surface, ".dbx-cms");
            if (editorRoot) syncEditorDom(editorRoot);
        };

        input.addEventListener("keydown", event => {
            if (event.key === "Enter") {
                event.preventDefault();
                finish(true);
            } else if (event.key === "Escape") {
                event.preventDefault();
                finish(false);
            }
        });
        input.addEventListener("blur", () => finish(true));
        (doc.defaultView || window).setTimeout(() => {
            input.focus();
            input.select();
        }, 0);
    }

    function bindEditableBadgeEditing(surface) {
        if (!surface) return;
        const doc = surface.ownerDocument || document;
        if (doc.__dbxCmsEditableBadgeGuardsBound) return;
        doc.__dbxCmsEditableBadgeGuardsBound = true;

        const focusEditableBadge = event => {
            const badge = closestElement(event.target, ".badge[data-dbx-cms-editable-badge]");
            const activeSurface = closestElement(badge, ".jodit-wysiwyg, [data-cms-editor]");
            if (!badge || !activeSurface) return;
            if (badge.classList.contains("position-absolute") || closestElement(badge, ".position-absolute")) return;

            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === "function") event.stopImmediatePropagation();
            (doc.defaultView || window).setTimeout(() => openEditableBadgeTextInput(badge, activeSurface), 0);
        };

        const focusAdjacentElement = event => {
            const anchor = closestElement(event.target, "[data-dbx-cms-button-caret-anchor],[data-dbx-cms-element-caret-anchor]");
            const activeSurface = closestElement(anchor, ".jodit-wysiwyg, [data-cms-editor]");
            const editorRoot = closestElement(activeSurface, ".dbx-cms");
            const side = anchor?.getAttribute("data-dbx-cms-caret-side") === "before" ? "before" : "after";
            const element = anchor && (side === "before" ? anchor.nextElementSibling : anchor.previousElementSibling);
            if (!anchor || !activeSurface || !editorRoot || !element) return;
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === "function") event.stopImmediatePropagation();
            (doc.defaultView || window).setTimeout(() => {
                if (typeof activeSurface.focus === "function") activeSurface.focus({ preventScroll: true });
                if (anchor.hasAttribute("data-dbx-cms-element-caret-anchor")) {
                    const card = element.matches("img.card-img-top,img.card-img,img.card-img-bottom") ? closestElement(element, ".card") : null;
                    const cardBody = card ? qs(card, ".card-body") : null;
                    if (cardBody) {
                        setEditorCaretInCardBody(editorRoot, cardBody);
                    } else {
                        setEditorCaretBesideElement(editorRoot, element, side);
                    }
                } else {
                    setEditorCaretBesideElement(editorRoot, element, side);
                }
                refreshEditorCaretHint(editorRoot);
            }, 0);
        };

        // Capture before Jodit handles the inline node; the compact text input
        // updates only the label and therefore preserves the complete badge.
        doc.addEventListener("mousedown", focusEditableBadge, true);
        doc.addEventListener("mousedown", focusAdjacentElement, true);
    }

    function createEditorCaretAnchor(doc, element, kind, layout, side) {
        if (!doc || !element || !element.parentNode) return null;
        side = side === "before" ? "before" : "after";
        const anchor = doc.createElement(layout === "block" ? "div" : "span");
        anchor.setAttribute(kind === "button" ? "data-dbx-cms-button-caret-anchor" : "data-dbx-cms-element-caret-anchor", "1");
        anchor.setAttribute("data-dbx-cms-caret-layout", layout === "block" ? "block" : "inline");
        anchor.setAttribute("data-dbx-cms-caret-side", side);
        anchor.setAttribute("contenteditable", "false");
        anchor.setAttribute("aria-label", side === "before" ? "Cursorposition vor Element" : "Cursorposition hinter Element");
        anchor.textContent = "\u200b";
        element.parentNode.insertBefore(anchor, side === "before" ? element : element.nextSibling);
        return anchor;
    }

    function createEditorCaretAnchors(doc, element, kind, layout) {
        createEditorCaretAnchor(doc, element, kind, layout, "before");
        createEditorCaretAnchor(doc, element, kind, layout, "after");
    }

    function editorCaretAnchorLayout(element) {
        if (!element || !element.matches) return "inline";
        return element.matches("figure,table,video,hr,img.card-img-top,img.card-img,img.card-img-bottom,.dbx-cms-marker,.dbx-cms-inline-media,.dbx-cms-inline-media-missing-wrap,.alert,.card,.list-group,.accordion,.table-responsive,.row,.nav-tabs,.tab-content")
            ? "block"
            : "inline";
    }

    function normalizeEditorElementCaretAnchors(surface, doc) {
        const selector = ".dbx-cms-marker,.dbx-cms-inline-media,.dbx-cms-inline-media-missing-wrap,figure,table,img,video,hr,.alert,.card,.list-group,.accordion,.table-responsive,.row,.nav-tabs,.tab-content,.badge";
        qsa(surface, selector).forEach(element => {
            if (element.matches("img,video") && closestElement(element, "figure,.dbx-cms-inline-media")) return;
            if (element.matches("table") && closestElement(element, ".table-responsive")) return;
            if (element.matches(".badge") && (closestElement(element, "a.btn") || element.classList.contains("position-absolute") || closestElement(element, ".position-absolute"))) return;
            const lockedParent = element.closest("[contenteditable='false']");
            if (lockedParent && surface.contains(lockedParent) && lockedParent !== element) return;
            createEditorCaretAnchors(doc, element, "element", editorCaretAnchorLayout(element));
        });
    }

    function normalizeFlexContentAlignment(surface) {
        if (!surface) return;
        const runtimeAnchor = "[data-dbx-cms-button-caret-anchor],[data-dbx-cms-element-caret-anchor]";
        qsa(surface, ".d-flex,.d-inline-flex").forEach(flex => {
            // Automatisch wird nur die eindeutige Pill-/Button-Zeile
            // migriert. Bei beliebigen Layout-Flexboxen koennte text-align
            // absichtlich fuer den Text innerhalb der Spalten gesetzt sein.
            const items = Array.from(flex.children || []).filter(child => !child.matches(runtimeAnchor));
            if (!items.length || !items.every(child => child.matches(".badge,.btn,a.btn,button.btn"))) return;

            let alignment = String(flex.style.textAlign || "").toLowerCase();
            if (!alignment && flex.classList.contains("text-center")) alignment = "center";
            if (!alignment && flex.classList.contains("text-end")) alignment = "end";
            if (!alignment && flex.classList.contains("text-start")) alignment = "start";
            const flexClass = {
                left: "justify-content-start",
                start: "justify-content-start",
                center: "justify-content-center",
                right: "justify-content-end",
                end: "justify-content-end"
            }[alignment];
            if (!flexClass) return;

            [
                "justify-content-start", "justify-content-center", "justify-content-end",
                "justify-content-between", "justify-content-around", "justify-content-evenly"
            ].forEach(className => flex.classList.remove(className));
            flex.classList.add(flexClass);
            flex.classList.remove("text-start", "text-center", "text-end");
            flex.style.removeProperty("text-align");
            if (!String(flex.getAttribute("style") || "").trim()) flex.removeAttribute("style");
        });
    }

    function normalizeBootstrapComponents(surface) {
        if (!surface) return;
        const doc = surface.ownerDocument || document;
        bindEditableBadgeEditing(surface);
        commitEditorCaretHosts(surface);
        normalizeFlexContentAlignment(surface);
        // Laufzeitanker immer deterministisch neu aufbauen. Wird direkt vor
        // einem vorhandenen Anker Text eingegeben, läge dieser sonst nicht
        // mehr unmittelbar neben dem Button und ein zweiter Anker entstünde.
        qsa(surface, "[data-dbx-cms-button-caret-anchor],[data-dbx-cms-element-caret-anchor]").forEach(anchor => anchor.remove());
        qsa(surface, "a.btn").forEach(button => {
            button.setAttribute("draggable", "true");
            button.setAttribute("data-dbx-cms-movable-button", "1");
        });
        // Nur Laufzeitattribute: Beim Speichern entfernt cleanEditorRuntimeNodes
        // diese Kennzeichnung wieder. So bleibt der gespeicherte Content sauber.
        qsa(surface, ".dbx-cms-marker,.dbx-cms-inline-media,.dbx-cms-inline-media-missing-wrap,figure,table,img,video,hr,p,h1,h2,h3,h4,h5,h6,ul,ol,dl,pre,blockquote,section,article,.alert,.card,.list-group,.accordion,.table-responsive,.row").forEach(block => {
            const lockedParent = block.closest("[contenteditable='false']");
            if (lockedParent && surface.contains(lockedParent) && lockedParent !== block) return;
            block.setAttribute("draggable", "true");
            block.setAttribute("data-dbx-cms-movable-block", "1");
        });
        qsa(surface, ".badge").forEach(badge => {
            const absoluteHost = closestElement(badge, ".position-absolute");
            if (badge.classList.contains("position-absolute") || absoluteHost) return;
            // Normale Content-Badges sind Textinhalt. Die explizite Runtime-
            // Freigabe macht sie auch innerhalb komplexer Bootstrap-Strukturen
            // direkt per Caret bearbeitbar; beim Serialisieren wird sie entfernt.
            badge.setAttribute("contenteditable", "true");
            badge.setAttribute("tabindex", "0");
            badge.setAttribute("data-dbx-cms-editable-badge", "1");
        });
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
        // Erst nach allen strukturellen Bootstrap-Korrekturen einfügen. Sonst
        // könnte z. B. die Karten-Normalisierung einen Bildanker in card-body
        // verschieben und die anklickbare Position vom Bild trennen.
        qsa(surface, "a.btn").forEach(button => createEditorCaretAnchors(doc, button, "button", "inline"));
        normalizeEditorElementCaretAnchors(surface, doc);
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

    function editorMediaId(node) {
        if (!node || !node.getAttribute) return 0;
        const ownId = Number(node.getAttribute("data-cms-media-id") || 0);
        if (ownId > 0) return ownId;
        const src = String(node.getAttribute("src") || "");
        const match = src.match(/dbx_mid=([0-9]+)/i);
        return Number(match && match[1] || 0);
    }

    function editorMediaNodeById(root, id) {
        id = Number(id || 0);
        const surface = editorSurface(root);
        if (!surface || !id) return null;
        return qsa(surface, "[data-cms-media-id],img[src*='dbx_mid='],video[src*='dbx_mid='],iframe[src*='dbx_mid=']")
            .find(node => editorMediaId(node) === id) || null;
    }

    function editorImageRow(root, img) {
        const id = editorMediaId(img);
        const stored = mediaRowById(root, id);
        if (stored) return stored;
        const src = String(img?.getAttribute("src") || "");
        return {
            id,
            url: src,
            thumb_url: src,
            mime: "image/*",
            media_type: "image",
            file_name: "",
            title: img?.getAttribute("title") || img?.getAttribute("alt") || "Bild",
            alt: img?.getAttribute("alt") || "",
            width: img?.naturalWidth || img?.getAttribute("width") || "",
            height: img?.naturalHeight || img?.getAttribute("height") || ""
        };
    }

    function openCmsImageEditor(root, img) {
        if (!img) return false;
        const row = editorImageRow(root, img);
        if (Number(row.id || 0) > 0) {
            openMediaEdit(root, cmsConfig(root) || {}, row);
            return true;
        }
        return openEditorImageProperties(root, img);
    }

    function removeEditorImage(root, img) {
        if (!img || !img.parentNode) return false;
        const wrapper = closestElement(img, ".dbx-cms-inline-media,figure");
        const target = wrapper && editorSurface(root)?.contains(wrapper) ? wrapper : img;
        target.remove();
        syncEditorDom(root);
        return true;
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
            window.clearTimeout(surface.__dbxCmsBootstrapNormalizeTimer);
            surface.__dbxCmsBootstrapNormalizeTimer = window.setTimeout(() => {
                surface.__dbxCmsBootstrapNormalizeTimer = null;
                // Der temporaere Host ist waehrend der Eingabe die reale
                // Selection. Ein Neuaufbau der Runtime-Anker wuerde ihn nach
                // dem ersten Zeichen committen und Jodit in den naechsten
                // Textblock springen lassen.
                if (qs(surface, "[data-dbx-cms-caret-host]")) return;
                normalizeBootstrapComponents(surface);
            }, 180);
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
        if (!insertEditorBlockFragmentAtRange(surface, range, tpl.content)) {
            range.insertNode(tpl.content);
        }

        normalizeEditorMarkers(root);
        normalizeBootstrapComponents(surface);
        bindEditorMarkerEventsRetry(root);
        syncEditorDom(root);
        const caretNode = nodes.slice().reverse().find(node => node.parentNode && surface.contains(node)) || surface.lastChild;
        setEditorCaretAfterNode(root, caretNode);
        saveEditorSelection(root);
        return true;
    }

    function execEditorCommand(root, command) {
        restoreEditorSelection(root);
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

    function editorFlexAlignmentTarget(root) {
        const surface = editorSurface(root);
        if (!surface) return null;
        restoreEditorSelection(root);
        const range = editorSelectionRange(root) || state(root).editorRange;
        if (!range || !range.startContainer) return null;
        const candidates = [
            nodeElement(range.startContainer),
            nodeElement(range.endContainer),
            nodeElement(range.commonAncestorContainer),
            topEditorChild(surface, range)
        ].filter(Boolean);
        for (const candidate of candidates) {
            const flex = candidate.matches && candidate.matches(".d-flex,.d-inline-flex")
                ? candidate
                : closestElement(candidate, ".d-flex,.d-inline-flex");
            if (flex && surface.contains(flex)) return flex;
        }
        return null;
    }

    function applyEditorAlignment(root, command) {
        const flex = editorFlexAlignmentTarget(root);
        const flexClass = {
            justifyLeft: "justify-content-start",
            justifyCenter: "justify-content-center",
            justifyRight: "justify-content-end"
        }[command];
        if (!flex || !flexClass) {
            execEditorCommand(root, command);
            return;
        }
        [
            "justify-content-start", "justify-content-center", "justify-content-end",
            "justify-content-between", "justify-content-around", "justify-content-evenly"
        ].forEach(className => flex.classList.remove(className));
        flex.classList.add(flexClass);
        syncEditorDom(root);
        setEditorCaretInElement(root, flex, 0);
        saveEditorSelection(root);
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
            Array.from(marker.classList).forEach(className => {
                if (className.indexOf("dbx-cms-marker-") === 0) marker.classList.remove(className);
            });
            marker.classList.add("dbx-cms-marker", "dbx-cms-marker-" + cmsMarkerClassName(name));
            marker.removeAttribute("data-cms-drag-token");
            marker.setAttribute("data-dbx-marker", "dbx:" + name);
            if (name === "hero" || !marker.getAttribute("data-label")) marker.setAttribute("data-label", cmsMarkerLabel("dbx:" + name));
            marker.setAttribute("contenteditable", "false");
            marker.setAttribute("draggable", "false");
            marker.setAttribute("tabindex", "0");
        });
        dedupeSingletonMarkers(box);
        dedupeAdjacentMarkers(box);
        return box.innerHTML;
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
            btn.setAttribute("data-dbx-tooltip", "Suche zurücksetzen");
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

    function renderTree(...args) {
        return callCmsModule("tree", "renderTree", args);
    }

    function toggleTreeFolder(...args) {
        return callCmsModule("tree", "toggleTreeFolder", args);
    }

    function loadTree(...args) {
        return callCmsModule("tree", "loadTree", args);
    }

    function ensureTreeLoaded(...args) {
        return callCmsModule("tree", "ensureTreeLoaded", args);
    }

    function loadInitialSelection(...args) {
        return callCmsModule("page", "loadInitialSelection", args);
    }

    function setFolderField(...args) {
        return callCmsModule("page", "setFolderField", args);
    }

    function getFolderField(...args) {
        return callCmsModule("page", "getFolderField", args);
    }

    function findNode(...args) {
        return callCmsModule("page", "findNode", args);
    }

    function buildPageFolderOptions(...args) {
        return callCmsModule("page", "buildPageFolderOptions", args);
    }

    function showFolderEditor(...args) {
        return callCmsModule("page", "showFolderEditor", args);
    }

    function hideFolderEditor(...args) {
        return callCmsModule("page", "hideFolderEditor", args);
    }

    function saveFolder(...args) {
        return callCmsModule("page", "saveFolder", args);
    }

    function deleteFolder(...args) {
        return callCmsModule("page", "deleteFolder", args);
    }

    function setField(...args) {
        return callCmsModule("page", "setField", args);
    }

    function getField(...args) {
        return callCmsModule("page", "getField", args);
    }

    function loadViewPage(...args) {
        return callCmsModule("page", "loadViewPage", args);
    }

    function loadPage(...args) {
        return callCmsModule("page", "loadPage", args);
    }

    function loadMedia(...args) {
        return callCmsModule("page", "loadMedia", args);
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
            const sourceNode = node.matches?.(".dbx-cms-inline-media")
                ? (qs(node, "img[src*='dbx_mid='],video[src*='dbx_mid='],iframe[src*='dbx_mid='],source[src*='dbx_mid=']")
                    || qs(node, "img[data-cms-media-id],video[data-cms-media-id],iframe[data-cms-media-id],source[data-cms-media-id]"))
                : node;
            const src = String(sourceNode?.getAttribute?.("src") || "");
            const match = src.match(/dbx_mid=([0-9]+)/i);
            if (match) {
                registerId(match[1]);
                return;
            }
            registerId(node.getAttribute("data-cms-media-id") || sourceNode?.getAttribute?.("data-cms-media-id"));
        };

        if (surface) {
            const selector = ".dbx-cms-inline-media,img[data-cms-media-id],video[data-cms-media-id],iframe[data-cms-media-id],source[data-cms-media-id],img[src*='dbx_mid='],video[src*='dbx_mid='],iframe[src*='dbx_mid='],source[src*='dbx_mid=']";
            qsa(surface, selector).forEach(node => {
                const wrapper = closestElement(node, ".dbx-cms-inline-media");
                if (wrapper && wrapper !== node) return;
                if (node.matches?.(".dbx-cms-inline-media") && !inlineMediaWrapperHasContent(node)) return;
                registerNode(node);
            });
        }

        return Array.from(ids).filter(Boolean);
    }

    function inlineMediaRowsFromEditor(root, rows) {
        const ids = collectInlineMediaIdsFromEditor(root);
        return ids.map(id => {
            const stored = (rows || []).find(row => Number(row.id || row.media_id || 0) === id)
                || mediaRowById(root, id);
            if (stored) {
                return Object.assign({}, stored, {
                    id,
                    slot: "inline",
                    usage: "inline",
                    usage_id: String(stored.slot || "") === "inline" ? stored.usage_id : ""
                });
            }

            const node = editorMediaNodeById(root, id);
            const image = node?.tagName === "IMG" ? node : qs(node, "img");
            const src = String(image?.getAttribute("src") || node?.getAttribute("src") || "");
            return {
                id,
                slot: "inline",
                usage: "inline",
                url: src,
                thumb_url: image?.getAttribute("src") || src,
                // Getrennte Fragmente verhindern, dass Dokumentationsparser die
                // MIME-Wildcards als Beginn verschachtelter Kommentare lesen.
                mime: node?.tagName === "VIDEO" ? "video/" + "*" : "image/" + "*",
                media_type: node?.tagName === "VIDEO" || closestElement(node, ".dbx-cms-inline-video-block") ? "video" : "image",
                title: image?.getAttribute("title") || image?.getAttribute("alt") || node?.getAttribute("title") || ("Medium #" + id),
                alt: image?.getAttribute("alt") || "",
                width: image?.naturalWidth || image?.getAttribute("width") || "",
                height: image?.naturalHeight || image?.getAttribute("height") || ""
            };
        });
    }

    function editorInlineMediaSignature(root, html) {
        const ids = typeof html === "string"
            ? inlineMediaIds(html)
            : collectInlineMediaIdsFromEditor(root);
        return ids
            .map(id => Number(id || 0))
            .filter(Boolean)
            .join(",");
    }

    function scheduleEditorMediaRender(root, html) {
        if (!root) return;
        const signature = editorInlineMediaSignature(root, html);
        if (signature === root.__dbxCmsMediaRenderSignature) return;
        root.__dbxCmsMediaRenderSignature = signature;
        window.clearTimeout(root.__dbxCmsMediaRenderTimer);
        root.__dbxCmsMediaRenderTimer = window.setTimeout(() => {
            root.__dbxCmsMediaRenderTimer = null;
            renderMedia(root);
        }, 90);
    }

    function focusInlineMediaInEditor(root, id) {
        const node = editorMediaNodeById(root, id);
        const surface = editorSurface(root);
        if (!node || !surface) return false;
        qsa(surface, ".is-dbx-cms-selected").forEach(selected => selected.classList.remove("is-dbx-cms-selected"));
        const target = closestElement(node, ".dbx-cms-inline-media") || node;
        target.classList.add("is-dbx-cms-selected");
        selectEditorNode(root, target);
        if (target.scrollIntoView) target.scrollIntoView({ behavior: "smooth", block: "center" });
        return true;
    }

    function insertEditorBlockFragmentAtRange(surface, range, fragment) {
        if (!surface || !range || !fragment) return false;
        const hasBlockContent = Array.from(fragment.children || []).some(element => element.matches(
            "div,p,section,article,aside,header,footer,figure,table,ul,ol,blockquote,hr"
        ));
        if (!hasBlockContent) return false;

        const start = nodeElement(range.startContainer);
        const block = start ? closestElement(start, "p,h1,h2,h3,h4,h5,h6,blockquote") : null;
        if (!block || !surface.contains(block) || !block.parentNode) return false;
        const locked = closestElement(block, "[contenteditable='false']");
        if (locked && surface.contains(locked)) return false;

        // Block-Komponenten duerfen nie in einen Absatz geschrieben werden.
        // Browser reparieren ein verschachteltes <p><div>...</div></p>
        // unterschiedlich und versetzen dabei die Selection nach dem ersten
        // Zeichen. Wir teilen den aktuellen Textblock stattdessen an der Caret
        // und setzen die Komponente als echtes Geschwisterelement dazwischen.
        const doc = surface.ownerDocument || document;
        const suffixRange = doc.createRange();
        try {
            suffixRange.setStart(range.startContainer, range.startOffset);
            suffixRange.setEnd(block, block.childNodes.length);
        } catch (_) {
            return false;
        }
        const suffix = suffixRange.extractContents();
        const parent = block.parentNode;
        const insertionRef = block.nextSibling;
        parent.insertBefore(fragment, insertionRef);

        if (nodeHasEditorContent(suffix)) {
            const afterBlock = block.cloneNode(false);
            afterBlock.appendChild(suffix);
            parent.insertBefore(afterBlock, insertionRef);
        }
        if (!nodeHasEditorContent(block)) block.remove();
        return true;
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
        scheduleEditorMediaRender(root);
        return true;
    }

    function savePage(...args) {
        return callCmsModule("page", "savePage", args);
    }

    function deletePage(...args) {
        return callCmsModule("page", "deletePage", args);
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
        root.__dbxCmsMediaRenderSignature = editorInlineMediaSignature(root);
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
            const visible = boxSlot === "inline"
                ? inlineMediaRowsFromEditor(root, rows)
                : (Array.isArray(rows) ? rows : []).filter(row => {
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
                const actions = boxSlot === "inline"
                    ? `<button type="button" class="btn btn-outline-primary btn-sm" data-cms-inline-focus data-dbx-tooltip="${escapeHtml(cmsText(root, "media_inline_focus", "Im Editor auswählen"))}"><i class="bi bi-crosshair"></i></button>
                       ${canEditImage(row) ? `<button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-edit-one data-dbx-tooltip="${escapeHtml(cmsText(root, "media_inline_edit", "Bild bearbeiten"))}"><i class="bi bi-pencil-square"></i></button>` : ""}
                       <button type="button" class="btn btn-outline-primary btn-sm" data-cms-inline-remove data-dbx-tooltip="${escapeHtml(cmsText(root, "media_inline_remove", "Aus Inhalt entfernen"))}"><i class="bi bi-trash"></i></button>`
                    : `${canEmbed ? '<button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-embed data-dbx-tooltip="Medium direkt in den Editor einfuegen"><i class="bi bi-image"></i></button>' : ''}
                       <button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-remove data-dbx-tooltip="Zuordnung aus dieser Seite entfernen"><i class="bi bi-trash"></i></button>`;
                return `<div class="dbx-cms-media-item" draggable="true" data-media-id="${escapeHtml(row.id || "")}" data-usage-id="${escapeHtml(row.usage_id || row.current_usage_id || "")}" data-media-slot="${escapeHtml(slot)}" data-media-folder="${escapeHtml(row.media_folder || "")}" data-url="${escapeHtml(row.url || "")}" data-thumb-url="${escapeHtml(row.thumb_url || "")}" data-mime="${escapeHtml(row.mime || "")}" data-media-type="${escapeHtml(row.media_type || "")}" data-file-name="${escapeHtml(row.file_name || "")}" data-file-path="${escapeHtml(row.file_path || "")}" data-title="${escapeHtml(row.title || "")}" data-alt="${escapeHtml(row.alt || "")}" data-width="${escapeHtml(row.width || "")}" data-height="${escapeHtml(row.height || "")}">
                <span class="dbx-cms-media-preview"><span class="dbx-cms-media-badge">${escapeHtml(badge)}</span>${preview}</span>
                <span class="dbx-cms-media-meta">
                    <span class="dbx-cms-media-name">${escapeHtml(row.title || row.file_name || "Medium")}</span>
                    <span class="dbx-cms-media-slot">${escapeHtml(originLabel)}</span>
                </span>
                <span class="dbx-cms-media-actions">
                    ${actions}
                </span>
            </div>`;
            }).join("");
            setupMediaLazyImages(box);
        });
    }

    function removeMedia(...args) {
        return callCmsModule("media", "removeMedia", args);
    }

    function deleteMedia(...args) {
        return callCmsModule("media", "deleteMedia", args);
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

    function previewMediaCrop(...args) {
        return callCmsModule("media", "previewMediaCrop", args);
    }

    function commitMediaEditAction(...args) {
        return callCmsModule("media", "commitMediaEditAction", args);
    }

    function openMediaEdit(...args) {
        return callCmsModule("media", "openMediaEdit", args);
    }

    function bulkResizeMedia(...args) {
        return callCmsModule("media", "bulkResizeMedia", args);
    }

    function assignMedia(...args) {
        return callCmsModule("media", "assignMedia", args);
    }

    function saveMediaOrder(...args) {
        return callCmsModule("media", "saveMediaOrder", args);
    }

    function addExternalVideo(...args) {
        return callCmsModule("media", "addExternalVideo", args);
    }

    function uploadMedia(...args) {
        return callCmsModule("media", "uploadMedia", args);
    }

    function insertMarker(root, marker, label) {
        const editor = qs(root, "[data-cms-editor]");
        if (!editor) return;
        if (marker === "dbx:split") marker = "dbx:col2";
        const name = cmsMarkerName(marker);
        if (name === "hero") {
            const existing = qsa(editorSurface(root), ".dbx-cms-marker,[data-dbx-marker]")
                .find(item => markerNameFromElement(item) === name);
            if (existing) {
                insertEditorHrNode(root, existing);
                return;
            }
        }
        insertEditorMarkerElement(root, marker, label);
    }

    function bind(...args) {
        return callCmsModule("page", "bind", args);
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
            ["js", "lib", "form.js"],
            ["js", "lib", "cms-page.js"]
        ],

        init(el, cfg) {
            if (!el || el.__dbxCmsReady) return;
            el.__dbxCmsReady = true;
            // Der Editor wird auf jeder administrativen CMS-Seite gebraucht.
            // Download und Ausfuehrung duerfen deshalb parallel zu den
            // kritischen Styles laufen; die DOM-Initialisierung wartet weiter.
            const editorAssetReady = isViewMode(cfg || {})
                ? Promise.resolve(false)
                : ensureJodit();
            const pageModuleReady = ensureCmsModule("page");
            waitForCmsCriticalStyles(el, () => {
                pageModuleReady.then(() => {
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
                    initTreePanelState(el);
                    initRightPanelState(el);
                    bindTreeRuntimeEnhancements(el);
                    if (!isViewMode(cfg || {})) {
                        editorAssetReady.then(ok => {
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
                    Promise.resolve(loadInitialSelection(el, cfg || {})).finally(() => {
                        // Die Seitenkopfdaten und Aktionsleisten koennen durch den
                        // initialen AJAX-Ladevorgang hoeher werden. Erst danach ist
                        // der endgueltige Sticky-Abstand messbar.
                        syncStickyHeaderOffset(el);
                        settleStickyHeaderOffset(el);
                    });
                }).catch(err => {
                    dbx.error("[cms] page module init failed", err);
                    status(el, "CMS-Aktionsmodul konnte nicht geladen werden.", "error");
                });
            });
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
