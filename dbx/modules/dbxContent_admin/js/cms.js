/*!
 * dbxapp cms.js
 * Content CMS coordination and feature runtime.
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
        page: [["js", "module", "dbxContent_admin/cms-page.js"]],
        tree: [["js", "module", "dbxContent_admin/cms-tree.js"]],
        media: [["js", "module", "dbxContent_admin/cms-media.js"]],
        language: [["js", "module", "dbxContent_admin/cms-language.js"]],
        joditImage: [["js", "module", "dbxContent_admin/cms-jodit-image.js"]],
        context: [["js", "module", "dbxContent_admin/cms-context.js"]],
        components: [["js", "module", "dbxContent_admin/cms-components.js"]],
        editor: [["js", "module", "dbxContent_admin/cms-editor.js"]]
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
            bindBootstrapCardEditingGuards,
            bindEditorHeight,
            bindEditorMarkerEventsRetry,
            cmsMarkerElement,
            cssSizeValue,
            inlineVideoOptionsButtonHtml,
            inlineVideoOptionsFromElement,
            normalizeCommentMarkers,
            repairInlineVideoHtml,
            repairInlineVideoPlayers,
            scheduleEditorMediaRender,
            syncInlineVideoOptionsToMedia,            commitEditorCaretHosts,
            insertEditorFragment,
            isEmptyEditorBlock,
            nodeHasEditorContent,
            normalizeEditorMarkers,
            setEditorCaretBesideElement,
            setEditorCaretInCardBody,            cleanEditorRuntimeNodes,
            editorRangeFromPoint,
            getEditorInstance,
            hideEditorCaretHint,
            inlineModTarget,
            insertEditorHtml,
            markDirty,
            nodeElement,
            normalizeBootstrapComponents,
            openCmsImageEditor,
            openModPlaceholderOptions,
            rangeInsideSurface,
            rangeIntersectsNode,
            refreshEditorCaretHint,
            removeEditorImage,
            removeEditorMarker,
            removeEditorModPlaceholder,
            restoreEditorSelection,
            scheduleEditorHeight,
            selectEditorMarker,
            selectEditorModPlaceholder,
            setEditorCaretAfterNode,
            setEditorCaretFromPoint,
            syncEditorDom,            syncEditorAfterContextAction,
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

    function callCmsModuleSync(name, method, args) {
        const api = cmsModules[name] || instantiateCmsModule(name);
        if (!api || typeof api[method] !== "function") {
            throw new Error(`CMS-Modulmethode fehlt: ${name}.${method}`);
        }
        return api[method](...(args || []));
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
        return dbx.cmsMarker.name(marker);
    }

    function cmsMarkerClassName(marker) {
        return dbx.cmsMarker.className(marker);
    }

    function cmsMarkerLabel(marker, label) {
        return dbx.cmsMarker.label(marker, label);
    }

    function cmsMarkerHtml(marker, label) {
        return dbx.cmsMarker.html(marker, label);
    }

    function cmsMarkerElement(marker, label, doc) {
        return dbx.cmsMarker.element(marker, label, doc);
    }

    function markerNameFromElement(node) {
        return dbx.cmsMarker.nameFromElement(node);
    }

    function nearbyMarkerSibling(node, dir) {
        return dbx.cmsMarker.nearbySibling(node, dir);
    }

    function dedupeAdjacentMarkers(container) {
        dbx.cmsMarker.dedupeAdjacent(container);
    }

    function normalizeCommentMarkers(container) {
        dbx.cmsMarker.normalizeComments(container);
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
        dbx.cmsMarker.dedupeSingleton(container);
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

    /* Editor-DOM, Caret und Serialisierungszustand liegen in cms-editor.js. */
    function setEditorHtml(root, html) {
        return callCmsModuleSync("editor", "setEditorHtml", Array.from(arguments));
    }

    function editorHtmlSnapshot(surface) {
        return callCmsModuleSync("editor", "editorHtmlSnapshot", Array.from(arguments));
    }

    function getEditorHtml(root) {
        return callCmsModuleSync("editor", "getEditorHtml", Array.from(arguments));
    }

    function editorSurface(root) {
        return callCmsModuleSync("editor", "editorSurface", Array.from(arguments));
    }

    function nodeElement(node) {
        return callCmsModuleSync("editor", "nodeElement", Array.from(arguments));
    }

    function rangeInsideSurface(surface, range) {
        return callCmsModuleSync("editor", "rangeInsideSurface", Array.from(arguments));
    }

    function nodeHasEditorContent(node) {
        return callCmsModuleSync("editor", "nodeHasEditorContent", Array.from(arguments));
    }

    function setEditorCaretAfterNode(root, node) {
        return callCmsModuleSync("editor", "setEditorCaretAfterNode", Array.from(arguments));
    }

    function commitEditorCaretHosts(container) {
        return callCmsModuleSync("editor", "commitEditorCaretHosts", Array.from(arguments));
    }

    function cleanEditorRuntimeNodes(container) {
        return callCmsModuleSync("editor", "cleanEditorRuntimeNodes", Array.from(arguments));
    }

    function removeEmptyEditorParagraphs(container) {
        return callCmsModuleSync("editor", "removeEmptyEditorParagraphs", Array.from(arguments));
    }

    function inlineMediaWrapperHasContent(wrap) {
        return callCmsModuleSync("editor", "inlineMediaWrapperHasContent", Array.from(arguments));
    }

    function inlineMissingMediaWrap(doc, id, label) {
        return callCmsModuleSync("editor", "inlineMissingMediaWrap", Array.from(arguments));
    }

    function normalizeInlineMissingMedia(container) {
        return callCmsModuleSync("editor", "normalizeInlineMissingMedia", Array.from(arguments));
    }

    function inlineVideoMediaSize(media) {
        return callCmsModuleSync("editor", "inlineVideoMediaSize", Array.from(arguments));
    }

    function persistInlineVideoRenderedSize(wrapper) {
        return callCmsModuleSync("editor", "persistInlineVideoRenderedSize", Array.from(arguments));
    }

    function beginInlineVideoResizeTrack(root, wrapper) {
        return callCmsModuleSync("editor", "beginInlineVideoResizeTrack", Array.from(arguments));
    }

    function finishInlineVideoResizeTrack(root) {
        return callCmsModuleSync("editor", "finishInlineVideoResizeTrack", Array.from(arguments));
    }

    function syncInlineVideoBlockSizes(container) {
        return callCmsModuleSync("editor", "syncInlineVideoBlockSizes", Array.from(arguments));
    }

    function plainMarkerName(text) {
        return callCmsModuleSync("editor", "plainMarkerName", Array.from(arguments));
    }

    function normalizePlainTextMarkers(container) {
        return callCmsModuleSync("editor", "normalizePlainTextMarkers", Array.from(arguments));
    }

    function normalizeInlineMediaLayout(container) {
        return callCmsModuleSync("editor", "normalizeInlineMediaLayout", Array.from(arguments));
    }

    function topEditorChild(surface, range) {
        return callCmsModuleSync("editor", "topEditorChild", Array.from(arguments));
    }

    function canSplitForMarker(block) {
        return callCmsModuleSync("editor", "canSplitForMarker", Array.from(arguments));
    }

    function insertFragmentAfter(parent, fragment, afterNode) {
        return callCmsModuleSync("editor", "insertFragmentAfter", Array.from(arguments));
    }

    function insertEditorHrNode(root, hrNode) {
        return callCmsModuleSync("editor", "insertEditorHrNode", Array.from(arguments));
    }

    function insertEditorPlainHr(root) {
        return callCmsModuleSync("editor", "insertEditorPlainHr", Array.from(arguments));
    }

    function insertEditorMarkerElement(root, marker, label) {
        return callCmsModuleSync("editor", "insertEditorMarkerElement", Array.from(arguments));
    }

    function saveEditorSelection(root) {
        return callCmsModuleSync("editor", "saveEditorSelection", Array.from(arguments));
    }

    function refreshEditorCaretHint(root, explicitRange) {
        return callCmsModuleSync("editor", "refreshEditorCaretHint", Array.from(arguments));
    }

    function currentEditorCaretRange(surface) {
        return callCmsModuleSync("editor", "currentEditorCaretRange", Array.from(arguments));
    }

    function editorCaretRect(range) {
        return callCmsModuleSync("editor", "editorCaretRect", Array.from(arguments));
    }

    function hideEditorCaretHint(root) {
        return callCmsModuleSync("editor", "hideEditorCaretHint", Array.from(arguments));
    }

    function restoreEditorSelection(root) {
        return callCmsModuleSync("editor", "restoreEditorSelection", Array.from(arguments));
    }

    function pushEditorHtml(root) {
        return callCmsModuleSync("editor", "pushEditorHtml", Array.from(arguments));
    }

    function hoistEditorMarkersToSurface(surface) {
        return callCmsModuleSync("editor", "hoistEditorMarkersToSurface", Array.from(arguments));
    }

    function surfaceEditorBlocks(surface, ignoreMarker) {
        return callCmsModuleSync("editor", "surfaceEditorBlocks", Array.from(arguments));
    }

    function markerSurfacePlacement(surface, x, y, ignoreMarker) {
        return callCmsModuleSync("editor", "markerSurfacePlacement", Array.from(arguments));
    }

    function syncEditorDom(root, options) {
        return callCmsModuleSync("editor", "syncEditorDom", Array.from(arguments));
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

    /* Kontextmenue und Blockaktionen liegen in cms-context.js. */
    function editorSelectionRange(root) {
        return callCmsModuleSync("context", "editorSelectionRange", Array.from(arguments));
    }

    function editorSelectionText(root) {
        return callCmsModuleSync("context", "editorSelectionText", Array.from(arguments));
    }

    function selectEditorContents(root) {
        return callCmsModuleSync("context", "selectEditorContents", Array.from(arguments));
    }

    function editorContextBlock(root, target) {
        return callCmsModuleSync("context", "editorContextBlock", Array.from(arguments));
    }

    function movableEditorContextBlock(root, target) {
        return callCmsModuleSync("context", "movableEditorContextBlock", Array.from(arguments));
    }

    function movableEditorButtonBlock(root, target) {
        return callCmsModuleSync("context", "movableEditorButtonBlock", Array.from(arguments));
    }

    function clearEditorButtonDrag(root) {
        return callCmsModuleSync("context", "clearEditorButtonDrag", Array.from(arguments));
    }

    function closestBootstrapComponent(root, target) {
        return callCmsModuleSync("context", "closestBootstrapComponent", Array.from(arguments));
    }

    function bootstrapRowColumns(row) {
        return callCmsModuleSync("context", "bootstrapRowColumns", Array.from(arguments));
    }

    function bootstrapColumnRow(root, target) {
        return callCmsModuleSync("context", "bootstrapColumnRow", Array.from(arguments));
    }

    function clearBootstrapColumnClasses(column) {
        return callCmsModuleSync("context", "clearBootstrapColumnClasses", Array.from(arguments));
    }

    function finishBootstrapColumnAction(root, focusNode) {
        return callCmsModuleSync("context", "finishBootstrapColumnAction", Array.from(arguments));
    }

    function setBootstrapColumnLayout(root, row, mode) {
        return callCmsModuleSync("context", "setBootstrapColumnLayout", Array.from(arguments));
    }

    function addBootstrapColumn(root, row) {
        return callCmsModuleSync("context", "addBootstrapColumn", Array.from(arguments));
    }

    function dissolveBootstrapColumns(root, row) {
        return callCmsModuleSync("context", "dissolveBootstrapColumns", Array.from(arguments));
    }

    function contextMissingMediaTarget(root, target) {
        return callCmsModuleSync("context", "contextMissingMediaTarget", Array.from(arguments));
    }

    function selectEditorMissingMedia(root, wrap) {
        return callCmsModuleSync("context", "selectEditorMissingMedia", Array.from(arguments));
    }

    function removeEditorMissingMedia(root, wrap) {
        return callCmsModuleSync("context", "removeEditorMissingMedia", Array.from(arguments));
    }

    function handleEditorMissingMediaClick(root, wrap, e) {
        return callCmsModuleSync("context", "handleEditorMissingMediaClick", Array.from(arguments));
    }

    function removableEditorContextTarget(root, target) {
        return callCmsModuleSync("context", "removableEditorContextTarget", Array.from(arguments));
    }

    function editorElementSibling(el, dir) {
        return callCmsModuleSync("context", "editorElementSibling", Array.from(arguments));
    }

    function moveEditorContextBlock(root, block, dir) {
        return callCmsModuleSync("context", "moveEditorContextBlock", Array.from(arguments));
    }

    function closeEditorContextMenu(root) {
        return callCmsModuleSync("context", "closeEditorContextMenu", Array.from(arguments));
    }

    function clipboardWriteText(text) {
        return callCmsModuleSync("context", "clipboardWriteText", Array.from(arguments));
    }

    function clipboardReadText() {
        return callCmsModuleSync("context", "clipboardReadText", Array.from(arguments));
    }

    function editorContextBlockHtml(root, target) {
        return callCmsModuleSync("context", "editorContextBlockHtml", Array.from(arguments));
    }

    function copyEditorContext(root, target) {
        return callCmsModuleSync("context", "copyEditorContext", Array.from(arguments));
    }

    function cutEditorContext(root, target) {
        return callCmsModuleSync("context", "cutEditorContext", Array.from(arguments));
    }

    function editorRangeIsInsertable(root, range) {
        return callCmsModuleSync("context", "editorRangeIsInsertable", Array.from(arguments));
    }

    function restoreEditorContextPasteRange(root) {
        return callCmsModuleSync("context", "restoreEditorContextPasteRange", Array.from(arguments));
    }

    function editorClipboardInlineButtonHtml(root, html) {
        return callCmsModuleSync("context", "editorClipboardInlineButtonHtml", Array.from(arguments));
    }

    function alignEditorContextPasteRange(root, html) {
        return callCmsModuleSync("context", "alignEditorContextPasteRange", Array.from(arguments));
    }

    function editorClipboardHtmlAtCaret(root, html) {
        return callCmsModuleSync("context", "editorClipboardHtmlAtCaret", Array.from(arguments));
    }

    function insertEditorContextBlock(root, html, target) {
        return callCmsModuleSync("context", "insertEditorContextBlock", Array.from(arguments));
    }

    function rememberEditorContextPasteRange(root, x, y) {
        return callCmsModuleSync("context", "rememberEditorContextPasteRange", Array.from(arguments));
    }

    function insertDraggedEditorBlockAtCaret(root, block) {
        return callCmsModuleSync("context", "insertDraggedEditorBlockAtCaret", Array.from(arguments));
    }

    function pasteEditorContext(root, target) {
        return callCmsModuleSync("context", "pasteEditorContext", Array.from(arguments));
    }

    function deleteEditorContext(root, target) {
        return callCmsModuleSync("context", "deleteEditorContext", Array.from(arguments));
    }

    function selectEditorNode(root, node) {
        return callCmsModuleSync("context", "selectEditorNode", Array.from(arguments));
    }

    function syncEditorAfterContextAction(root) {
        return callCmsModuleSync("context", "syncEditorAfterContextAction", Array.from(arguments));
    }

    function contextImageTarget(root, target) {
        return callCmsModuleSync("context", "contextImageTarget", Array.from(arguments));
    }

    function contextTableCell(root, target) {
        return callCmsModuleSync("context", "contextTableCell", Array.from(arguments));
    }

    function contextTableTarget(root, target) {
        return callCmsModuleSync("context", "contextTableTarget", Array.from(arguments));
    }

    function openEditorImageProperties(root, img) {
        return callCmsModuleSync("context", "openEditorImageProperties", Array.from(arguments));
    }

    function contextMenuButton(label, icon, action, disabled) {
        return callCmsModuleSync("context", "contextMenuButton", Array.from(arguments));
    }

    function showEditorContextMenu(root, e) {
        return callCmsModuleSync("context", "showEditorContextMenu", Array.from(arguments));
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

    /* Bootstrap-Komponenten und Editor-Runtime liegen in cms-components.js. */
    function bootstrapComponentItems(root) {
        return callCmsModuleSync("components", "bootstrapComponentItems", Array.from(arguments));
    }

    function cmsBootstrapComponentIcon() {
        return callCmsModuleSync("components", "cmsBootstrapComponentIcon", Array.from(arguments));
    }

    function insertBootstrapComponent(root, html) {
        return callCmsModuleSync("components", "insertBootstrapComponent", Array.from(arguments));
    }

    function openEditableBadgeTextInput(badge, surface) {
        return callCmsModuleSync("components", "openEditableBadgeTextInput", Array.from(arguments));
    }

    function bindEditableBadgeEditing(surface) {
        return callCmsModuleSync("components", "bindEditableBadgeEditing", Array.from(arguments));
    }

    function createEditorCaretAnchor(doc, element, kind, layout, side) {
        return callCmsModuleSync("components", "createEditorCaretAnchor", Array.from(arguments));
    }

    function createEditorCaretAnchors(doc, element, kind, layout) {
        return callCmsModuleSync("components", "createEditorCaretAnchors", Array.from(arguments));
    }

    function editorCaretAnchorLayout(element) {
        return callCmsModuleSync("components", "editorCaretAnchorLayout", Array.from(arguments));
    }

    function normalizeEditorElementCaretAnchors(surface, doc) {
        return callCmsModuleSync("components", "normalizeEditorElementCaretAnchors", Array.from(arguments));
    }

    function normalizeFlexContentAlignment(surface) {
        return callCmsModuleSync("components", "normalizeFlexContentAlignment", Array.from(arguments));
    }

    function normalizeBootstrapComponents(surface) {
        return callCmsModuleSync("components", "normalizeBootstrapComponents", Array.from(arguments));
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
        return dbx.cmsMarker.serialize(html, cleanEditorRuntimeNodes);
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
            ["js", "module", "dbxContent_admin/cms-context.js"],
            ["js", "module", "dbxContent_admin/cms-components.js"],
            ["js", "module", "dbxContent_admin/cms-editor.js"],
            ["js", "module", "dbxContent_admin/cms-marker.js"],
            ["js", "module", "dbxContent_admin/cms-page.js"]
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
