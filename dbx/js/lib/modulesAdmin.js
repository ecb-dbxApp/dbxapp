/*!
 * @file modulesAdmin.js
 * Modul-Verwaltung: DD-Gallery, Modul-Bilder (CMS-Medienbrowser), openWin
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        return;
    }

    const dbx = window.dbx;
    const LIB = "modulesAdmin";
    const PANEL_UI_ID = "modules-admin";

    function qs(el, sel) {
        return el ? el.querySelector(sel) : null;
    }

    function qsa(el, sel) {
        return el ? Array.prototype.slice.call(el.querySelectorAll(sel)) : [];
    }

    function moduleMessageRoot(node) {
        if (node && node.nodeType === 1 && node.hasAttribute("data-module-messages")) {
            return node;
        }
        return node && node.closest
            ? (node.closest(".dbx-modules-admin") || node.closest(".dbx-modimg-manager"))
            : document.querySelector(".dbx-modules-admin, .dbx-modimg-manager");
    }

    function moduleMessages(node) {
        const root = moduleMessageRoot(node);
        if (!root) {
            return {};
        }
        if (root.__dbxModuleMessages) {
            return root.__dbxModuleMessages;
        }
        try {
            root.__dbxModuleMessages = JSON.parse(root.getAttribute("data-module-messages") || "{}");
        } catch (ignore) {
            root.__dbxModuleMessages = {};
        }
        return root.__dbxModuleMessages;
    }

    function moduleMsg(node, key, fallback, replacements) {
        let value = String(moduleMessages(node)[key] || fallback || "");
        Object.keys(replacements || {}).forEach(function (name) {
            value = value.split("{" + name + "}").join(String(replacements[name]));
        });
        return value;
    }

    function modimgMediaCfg(root) {
        root = root || document.querySelector(".dbx-modules-admin") || document;
        return {
            media: root.getAttribute("data-modimg-media") || "",
            upload: root.getAttribute("data-modimg-upload") || "",
            mediafolders: root.getAttribute("data-modimg-mediafolders") || "",
            mediafoldercreate: root.getAttribute("data-modimg-mediafoldercreate") || "",
            mediafolderdelete: root.getAttribute("data-modimg-mediafolderdelete") || "",
            deletemedia: root.getAttribute("data-modimg-deletemedia") || "",
            uploadmax: root.getAttribute("data-modimg-uploadmax") || ""
        };
    }

    function panelMediaCfg(panel) {
        const cfg = modimgMediaCfg(panel.closest(".dbx-modules-admin") || panel.closest(".dbx-modimg-manager") || document);
        if (panel.getAttribute("data-modimg-media")) {
            cfg.media = panel.getAttribute("data-modimg-media");
        }
        if (panel.getAttribute("data-modimg-upload")) {
            cfg.upload = panel.getAttribute("data-modimg-upload");
        }
        if (panel.getAttribute("data-modimg-mediafolders")) {
            cfg.mediafolders = panel.getAttribute("data-modimg-mediafolders");
        }
        if (panel.getAttribute("data-modimg-mediafoldercreate")) {
            cfg.mediafoldercreate = panel.getAttribute("data-modimg-mediafoldercreate");
        }
        if (panel.getAttribute("data-modimg-mediafolderdelete")) {
            cfg.mediafolderdelete = panel.getAttribute("data-modimg-mediafolderdelete");
        }
        if (panel.getAttribute("data-modimg-deletemedia")) {
            cfg.deletemedia = panel.getAttribute("data-modimg-deletemedia");
        }
        if (panel.getAttribute("data-modimg-uploadmax")) {
            cfg.uploadmax = panel.getAttribute("data-modimg-uploadmax");
        }
        return cfg;
    }

    function ensureCmsMediaBrowser() {
        if (dbx.cmsMediaBrowser && typeof dbx.cmsMediaBrowser.open === "function") {
            return Promise.resolve(true);
        }
        return new Promise(function (resolve) {
            if (!dbx.load) {
                resolve(false);
                return;
            }
            dbx.load([
                ["js", "lib", "ajax.js"],
                ["js", "lib", "openWin.js"],
                ["js", "lib", "cms.js"]
            ], function () {
                resolve(!!(dbx.cmsMediaBrowser && typeof dbx.cmsMediaBrowser.open === "function"));
            });
        });
    }

    function getActiveDdItem(galleryId) {
        const gallery = document.getElementById(galleryId);
        if (!gallery) {
            return null;
        }
        return gallery.querySelector(".dbx-module-dd-item.is-active") || gallery.querySelector(".dbx-module-dd-item");
    }

    function openDdItem(item) {
        if (!item || !dbx.openWin) {
            return;
        }
        const url = item.getAttribute("data-edit-url") || "";
        const title = item.getAttribute("data-edit-title") || "DD";
        if (!url) {
            return;
        }
        dbx.openWin.open({
            url: url,
            title: title,
            width: "960",
            height: "92%",
            position: "center-top",
            minimizable: 1,
            maximizable: 1
        });
    }

    function bindModuleDdGallery(root) {
        const scope = root || document;

        scope.querySelectorAll(".dbx-module-dd-gallery").forEach(function (gallery) {
            if (gallery.dataset.ddBound === "1") {
                return;
            }
            gallery.dataset.ddBound = "1";

            gallery.querySelectorAll(".dbx-module-dd-item").forEach(function (item) {
                item.addEventListener("click", function () {
                    gallery.querySelectorAll(".dbx-module-dd-item").forEach(function (node) {
                        node.classList.remove("is-active");
                        node.setAttribute("aria-selected", "false");
                    });
                    item.classList.add("is-active");
                    item.setAttribute("aria-selected", "true");
                    const panel = gallery.closest(".dbx-module-dd-gallery-panel");
                    const btn = panel ? panel.querySelector(".dbx-module-dd-open-btn") : null;
                    if (btn) {
                        btn.setAttribute("data-dd-active", item.getAttribute("data-edit-url") || "");
                    }
                });
                item.addEventListener("dblclick", function () {
                    openDdItem(item);
                });
            });
        });

        scope.querySelectorAll(".dbx-module-dd-open-btn").forEach(function (btn) {
            if (btn.dataset.ddBound === "1") {
                return;
            }
            btn.dataset.ddBound = "1";
            const galleryId = btn.getAttribute("data-dd-select") || "";
            btn.addEventListener("click", function () {
                const item = getActiveDdItem(galleryId);
                openDdItem(item);
            });
        });
    }

    function fetchJson(url, options) {
        options = options || {};
        if (!dbx.ajax || typeof dbx.ajax.request !== "function") {
            return Promise.reject(new Error("ajax.js nicht geladen."));
        }

        return dbx.ajax.request({
            url: url,
            method: options.method || "GET",
            mode: "json",
            body: (typeof options.body !== "undefined") ? options.body : null,
            headers: options.headers || {},
            timeout: Number(options.timeout || 20000)
        });
    }

    function updatePanelPreview(panel, items) {
        const previewCol = qs(panel, "[data-module-images-preview]");
        if (!previewCol) {
            return;
        }
        const modul = panel.getAttribute("data-modul") || "";
        const placeholder = panel.getAttribute("data-placeholder-url") || "";
        const placeholderAlt = panel.getAttribute("data-placeholder-alt") || modul;
        let url = "";
        let label = modul;
        let isPlaceholder = true;
        if (items && items.length) {
            url = items[0].url || "";
            label = items[0].label || items[0].file || modul;
            isPlaceholder = false;
        }
        if (!url) {
            url = placeholder;
        }
        if (url) {
            previewCol.innerHTML = '<img src="' + escapeAttr(url) + '" alt="' + escapeAttr(isPlaceholder ? placeholderAlt : label) + '" class="dbx-module-images-preview-img' + (isPlaceholder ? " is-placeholder" : "") + '" loading="lazy">';
            return;
        }
        previewCol.innerHTML = '<span class="dbx-module-images-placeholder-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>';
    }

    function updateSymbolPreview(panel, symbol) {
        const previewCol = qs(panel, "[data-module-symbol-preview]");
        if (!previewCol) {
            return;
        }
        const modul = panel.getAttribute("data-modul") || "";
        const placeholder = panel.getAttribute("data-placeholder-url") || "";
        const placeholderAlt = panel.getAttribute("data-placeholder-alt") || modul;
        symbol = symbol || {};
        const url = symbol.url || placeholder;
        const alt = symbol.alt || (symbol.url ? modul : placeholderAlt);
        if (url) {
            previewCol.innerHTML = '<img src="' + escapeAttr(url) + '" alt="' + escapeAttr(alt) + '" class="dbx-module-symbol-img' + (symbol.url ? "" : " is-placeholder") + '" loading="lazy">';
            return;
        }
        previewCol.innerHTML = '<span class="dbx-module-images-placeholder-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>';
    }

    function renderModuleImagesEmptyListHtml(node) {
        return '<div class="dbx-module-images-empty-list text-muted small">'
            + escapeHtml(moduleMsg(node, "no_module_images", "No module images"))
            + '</div>';
    }

    const MOD_FILENAME_SEP = "__";

    function stemForRuns(modul, run1, run2) {
        let name = String(modul || "").trim();
        run1 = String(run1 || "").trim();
        run2 = String(run2 || "").trim();
        if (!name || !run1) {
            return name;
        }
        name += MOD_FILENAME_SEP + run1;
        if (run2) {
            name += MOD_FILENAME_SEP + run2;
        }
        return name;
    }

    function buildModuleImageCall(modul, params) {
        const call = "dbx_modul=" + encodeURIComponent(modul || "");
        if (params) {
            return call + "&" + params;
        }
        return call;
    }

    function buildModulPreviewUrl(modul, run1, run2, params, panel) {
        run1 = String(run1 || "").trim();
        run2 = String(run2 || "").trim();
        params = String(params || "");
        if ((!run1 || !run2) && params) {
            const m1 = params.match(/(?:^|&)dbx_run1=([^&]+)/);
            const m2 = params.match(/(?:^|&)dbx_run2=([^&]+)/);
            if (!run1 && m1) {
                run1 = decodeURIComponent(m1[1].replace(/\+/g, " "));
            }
            if (!run2 && m2) {
                run2 = decodeURIComponent(m2[1].replace(/\+/g, " "));
            }
        }
        if (!run1 && panel) {
            run1 = String(panel.getAttribute("data-default-run1") || "").trim();
        }
        if (!run2 && panel) {
            run2 = String(panel.getAttribute("data-default-run2") || "").trim();
        }
        let url = "?dbx_modul=" + encodeURIComponent(modul || "");
        if (run1) {
            url += "&dbx_run1=" + encodeURIComponent(run1);
        }
        if (run2) {
            url += "&dbx_run2=" + encodeURIComponent(run2);
        }
        return { url: url, run1: run1, run2: run2 };
    }

    function setActiveGalleryItem(panel, item) {
        const list = qs(panel, "[data-module-images-list]");
        if (!list) {
            return;
        }
        qsa(list, ".dbx-module-images-item").forEach(function (el) {
            el.classList.toggle("is-active", item && el === item);
        });
    }

    function escapeHtml(text) {
        return String(text || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function escapeAttr(text) {
        return escapeHtml(text);
    }

    function renderModuleImagesList(container, items) {
        if (!container) {
            return;
        }
        if (!items || !items.length) {
            container.innerHTML = renderModuleImagesEmptyListHtml(container);
            return;
        }
        container.innerHTML = items.map(function (item, index) {
            const file = item.file || "";
            const url = item.url || "";
            const label = item.label || file;
            const params = item.default_params || "";
            const panel = container.closest(".dbx-module-images-panel");
            const modul = panel ? (panel.getAttribute("data-modul") || "") : "";
            const preview = buildModulPreviewUrl(modul, item.run1 || "", item.run2 || "", params, panel);
            const run1 = preview.run1;
            const run2 = preview.run2;
            const call = buildModuleImageCall(modul, params);
            const previewTitle = moduleMsg(panel, "module_preview", "Preview: {module}", { module: modul || "" });
            const active = index === 0 ? " is-active" : "";
            const previewBtn = (run1 || params)
                ? '<a class="btn btn-outline-secondary btn-sm dbx-win dbx-module-images-preview" href="' + escapeAttr(preview.url) + '" data-url="' + escapeAttr(preview.url) + '" data-title="' + escapeAttr(previewTitle) + '" data-width="88%" data-height="88%" data-dbx-tooltip="' + escapeAttr(moduleMsg(panel, "open_module", "Open module")) + '"><i class="bi bi-box-arrow-up-right"></i></a>'
                : "";
            return '<div class="dbx-module-images-item' + active + '" data-file="' + escapeAttr(file) + '" data-params="' + escapeAttr(params) + '" data-url="' + escapeAttr(url) + '" data-run1="' + escapeAttr(run1) + '" data-run2="' + escapeAttr(run2) + '">'
                + '<span class="dbx-module-images-thumb"><img src="' + escapeAttr(url) + '" alt="' + escapeAttr(label) + '" loading="lazy"></span>'
                + '<span class="dbx-module-images-meta"><code class="dbx-module-images-call">' + escapeHtml(call) + '</code>'
                + '<span class="dbx-module-images-file">' + escapeHtml(file) + '</span></span>'
                + '<span class="dbx-module-images-actions">'
                + previewBtn
                + '<button type="button" class="btn btn-outline-danger btn-sm dbx-module-images-remove" data-dbx-tooltip="' + escapeAttr(moduleMsg(panel, "remove_image", "Remove image")) + '"><i class="bi bi-trash"></i></button>'
                + '</span></div>';
        }).join("");
    }

    function getPanelRunTarget(panel) {
        const run1El = qs(panel, ".dbx-module-images-run1");
        const run2El = qs(panel, ".dbx-module-images-run2");
        let run1 = run1El ? String(run1El.value || "").trim() : "";
        let run2 = run2El ? String(run2El.value || "").trim() : "";
        if (!run1) {
            run1 = String(panel.getAttribute("data-active-run1") || panel.getAttribute("data-default-run1") || "").trim();
        }
        if (!run2) {
            run2 = String(panel.getAttribute("data-active-run2") || panel.getAttribute("data-default-run2") || "").trim();
        }
        return { run1: run1, run2: run2 };
    }

    function setPanelRunTarget(panel, run1, run2) {
        const run1El = qs(panel, ".dbx-module-images-run1");
        const run2El = qs(panel, ".dbx-module-images-run2");
        if (run1El) {
            run1El.value = run1 || "";
        }
        if (run2El) {
            run2El.value = run2 || "";
        }
        panel.setAttribute("data-active-run1", run1 || "");
        panel.setAttribute("data-active-run2", run2 || "");
        updateFilenamePreview(panel);
    }

    function updateFilenamePreview(panel) {
        const preview = qs(panel, ".dbx-module-images-filename-preview");
        if (!preview) {
            return;
        }
        const modul = preview.getAttribute("data-modul") || panel.getAttribute("data-modul") || "";
        const target = getPanelRunTarget(panel);
        if (!modul || !target.run1) {
            preview.textContent = modul ? modul + "__run1.*" : "modul__run1.*";
            return;
        }
        preview.textContent = stemForRuns(modul, target.run1, target.run2) + ".*";
    }

    function applyRunTargetToPanel(panel, run1, run2) {
        setPanelRunTarget(panel, run1, run2);
    }

    function selectGalleryItem(panel, item) {
        if (!panel || !item) {
            return;
        }
        setActiveGalleryItem(panel, item);
        setPanelRunTarget(panel, item.getAttribute("data-run1") || "", item.getAttribute("data-run2") || "");
        const url = item.getAttribute("data-url") || "";
        const label = qs(item, ".dbx-module-images-file") ? qs(item, ".dbx-module-images-file").textContent : "";
        if (url) {
            updatePanelPreview(panel, [{ url: url, label: label }]);
        }
    }

    function bindModuleImagesPanel(panel) {
        if (!panel || panel.dataset.modimgTargetBound === "1") {
            return;
        }
        panel.dataset.modimgTargetBound = "1";
        panel.addEventListener("input", function (e) {
            if (e.target.closest(".dbx-module-images-run1, .dbx-module-images-run2")) {
                const run1 = String((qs(panel, ".dbx-module-images-run1") || {}).value || "").trim();
                const run2 = String((qs(panel, ".dbx-module-images-run2") || {}).value || "").trim();
                panel.setAttribute("data-active-run1", run1);
                panel.setAttribute("data-active-run2", run2);
                updateFilenamePreview(panel);
            }
        });
        updateFilenamePreview(panel);
        const list = qs(panel, "[data-module-images-list]");
        if (list) {
            const items = [];
            qsa(list, ".dbx-module-images-item").forEach(function (item) {
                items.push({
                    file: item.getAttribute("data-file") || "",
                    url: item.getAttribute("data-url") || "",
                    label: item.getAttribute("data-file") || "",
                    default_params: item.getAttribute("data-params") || "",
                    run1: item.getAttribute("data-run1") || "",
                    run2: item.getAttribute("data-run2") || ""
                });
            });
            if (items.length) {
                updatePanelPreview(panel, items);
                const first = qs(list, ".dbx-module-images-item");
                if (first) {
                    selectGalleryItem(panel, first);
                }
            }
        }
    }

    function bindModuleImagesPanels(scope) {
        qsa(scope || document, ".dbx-module-images-panel").forEach(bindModuleImagesPanel);
    }

    function moduleImagesPanelFromTarget(target) {
        if (!target || !target.closest) {
            return null;
        }
        const panel = target.closest(".dbx-module-images-panel");
        if (panel) {
            return panel;
        }
        const modul = target.getAttribute("data-modul") || "";
        if (!modul) {
            return null;
        }
        return document.querySelector('.dbx-module-images-panel[data-modul="' + modul.replace(/"/g, '\\"') + '"]');
    }

    function applyModuleImageItems(panel, items) {
        const list = qs(panel, "[data-module-images-list]");
        const galleryHead = qs(panel, ".dbx-module-images-gallery-head .dbx-module-images-count");
        if (list && Array.isArray(items)) {
            renderModuleImagesList(list, items);
        }
        if (galleryHead) {
            galleryHead.textContent = String((items && items.length) || 0);
        }
        if (items && items.length) {
            updatePanelPreview(panel, items);
            const first = list ? qs(list, ".dbx-module-images-item") : null;
            if (first) {
                selectGalleryItem(panel, first);
            }
        } else {
            updatePanelPreview(panel, []);
            setActiveGalleryItem(panel, null);
        }
    }

    function addModuleImage(panel, row) {
        if (row && Array.isArray(row.items)) {
            applyModuleImageItems(panel, row.items);
            return Promise.resolve({ ok: 1, items: row.items });
        }

        const modul = panel.getAttribute("data-modul") || "";
        const addUrl = panel.getAttribute("data-add-url") || "";
        const list = qs(panel, "[data-module-images-list]");
        const target = getPanelRunTarget(panel);
        if (!modul || !addUrl) {
            return Promise.resolve(null);
        }
        if (!target.run1) {
            window.alert(moduleMsg(panel, "run1_required", "Please enter dbx_run1."));
            return Promise.resolve(null);
        }
        const mediaId = parseInt(row && row.id ? row.id : 0, 10);
        const filePath = row && (row.file_path || row.filePath) ? (row.file_path || row.filePath) : "";
        const payload = mediaId > 0 ? { media_id: mediaId } : { file_path: filePath };
        if (!payload.media_id && !payload.file_path) {
            return Promise.resolve(null);
        }
        payload.xmodul = modul;
        payload.run1 = target.run1;
        payload.run2 = target.run2;
        return fetchJson(addUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        }).then(function (data) {
            if (data && data.ok && data.items) {
                applyModuleImageItems(panel, data.items);
            } else if (data && data.msg) {
                window.alert(data.msg);
            }
            return data;
        }).catch(function (err) {
            window.alert(err && err.message ? err.message : moduleMsg(panel, "image_import_error", "The image could not be imported."));
            throw err;
        });
    }

    function addModuleSymbol(panel, row) {
        const modul = panel.getAttribute("data-modul") || "";
        const addUrl = panel.getAttribute("data-symbol-add-url") || "";
        if (!modul || !addUrl) {
            return Promise.resolve(null);
        }
        const mediaId = parseInt(row && row.id ? row.id : 0, 10);
        const filePath = row && (row.file_path || row.filePath) ? (row.file_path || row.filePath) : "";
        const payload = mediaId > 0 ? { media_id: mediaId } : { file_path: filePath };
        if (!payload.media_id && !payload.file_path) {
            return Promise.resolve(null);
        }
        payload.xmodul = modul;

        return fetchJson(addUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        }).then(function (data) {
            if (data && data.ok && data.symbol) {
                updateSymbolPreview(panel, data.symbol);
            } else if (data && data.msg) {
                window.alert(data.msg);
            }
            return data;
        }).catch(function (err) {
            window.alert(err && err.message ? err.message : moduleMsg(panel, "symbol_import_error", "The symbol image could not be imported."));
            throw err;
        });
    }

    function openModuleSymbolBrowser(panel) {
        if (!panel) {
            return;
        }
        const root = panel.closest(".dbx-modules-admin") || document.body;
        const cfg = Object.assign({}, panelMediaCfg(panel));
        cfg.media = panel.getAttribute("data-symbol-media-url") || cfg.media;
        cfg.upload = panel.getAttribute("data-symbol-upload-url") || cfg.upload;
        cfg.mediafolders = panel.getAttribute("data-symbol-mediafolders-url") || cfg.mediafolders;
        cfg.mediafoldercreate = panel.getAttribute("data-symbol-mediafoldercreate-url") || cfg.mediafoldercreate;
        cfg.mediafolderdelete = panel.getAttribute("data-symbol-mediafolderdelete-url") || cfg.mediafolderdelete;
        cfg.deletemedia = panel.getAttribute("data-symbol-deletemedia-url") || cfg.deletemedia;

        ensureCmsMediaBrowser().then(function (ok) {
            if (!ok || !dbx.cmsMediaBrowser) {
                return;
            }
            dbx.cmsMediaBrowser.open(root, cfg, {
                mode: "pick",
                mediaFolder: "img",
                afterAssign: function (row) {
                    return addModuleSymbol(panel, row);
                }
            });
        });
    }

    function openModuleImagesBrowser(panel) {
        if (!panel) {
            return;
        }
        const target = getPanelRunTarget(panel);
        if (!target.run1) {
            window.alert(moduleMsg(panel, "run1_required", "Please enter dbx_run1 for the image file name."));
            return;
        }
        const modul = panel.getAttribute("data-modul") || "";
        const root = panel.closest(".dbx-modules-admin") || panel.closest(".dbx-modimg-manager") || document.body;
        const cfg = Object.assign({}, panelMediaCfg(panel));
        const moduleUploadUrl = panel.getAttribute("data-module-upload-url") || "";
        if (moduleUploadUrl) {
            cfg.upload = moduleUploadUrl;
        }

        ensureCmsMediaBrowser().then(function (ok) {
            if (!ok || !dbx.cmsMediaBrowser) {
                return;
            }
            dbx.cmsMediaBrowser.open(root, cfg, {
                mode: "pick",
                mediaFolder: "mod",
                formDataExtra: {
                    xmodul: modul,
                    run1: target.run1,
                    run2: target.run2
                },
                afterAssign: function (row) {
                    return addModuleImage(panel, row);
                }
            });
        });
    }

    function bindModuleImages(scope) {
        scope = scope || document;

        scope.addEventListener("click", function (e) {
            const symbolBtn = e.target.closest(".dbx-module-symbol-pick");
            if (symbolBtn) {
                e.preventDefault();
                const panel = moduleImagesPanelFromTarget(symbolBtn);
                if (panel) {
                    openModuleSymbolBrowser(panel);
                }
                return;
            }

            const pickBtn = e.target.closest(".dbx-module-images-pick, .dbx-module-images-pick-btn");
            if (pickBtn) {
                e.preventDefault();
                const panel = moduleImagesPanelFromTarget(pickBtn);
                if (panel) {
                    openModuleImagesBrowser(panel);
                }
                return;
            }

            const emptyPick = e.target.closest(".dbx-module-images-empty");
            if (emptyPick && emptyPick.classList.contains("dbx-module-images-pick")) {
                e.preventDefault();
                const panel = emptyPick.closest(".dbx-module-images-panel");
                if (panel) {
                    openModuleImagesBrowser(panel);
                }
                return;
            }

            const removeBtn = e.target.closest(".dbx-module-images-remove");
            if (removeBtn) {
                e.preventDefault();
                e.stopPropagation();
                const item = removeBtn.closest(".dbx-module-images-item");
                const panel = removeBtn.closest(".dbx-module-images-panel");
                if (!item || !panel) {
                    return;
                }
                const modul = panel.getAttribute("data-modul") || "";
                const removeUrl = panel.getAttribute("data-remove-url") || "";
                const file = item.getAttribute("data-file") || "";
                if (!modul || !removeUrl || !file) {
                    return;
                }
                const panelRoot = panel.closest(".dbx-modules-admin") || panel.closest(".dbx-modimg-manager") || document.body;
                removeBtn.disabled = true;
                ensureConfirm().then(function (ok) {
                    if (!ok) {
                        window.alert(moduleMsg(panel, "confirm_unavailable", "The confirmation dialog could not be loaded."));
                        return null;
                    }
                    return dbx.confirm.open({
                        id: "module-image-remove-" + modul + "-" + Date.now(),
                        root: panelRoot,
                        source: removeBtn,
                        title: moduleMsg(panel, "image_delete_title", "Delete module call image"),
                        question: moduleMsg(panel, "image_delete_question", "Really delete the module call image {file}?", { file: "<strong>" + escapeHtml(file) + "</strong>" }),
                        hint: moduleMsg(panel, "image_delete_hint", "The image will be removed from the module image directory."),
                        buttons: "yesno",
                        labelyes: "<i class=\"bi bi-trash\"></i> " + escapeHtml(moduleMsg(panel, "delete_button", "Delete")),
                        labelno: "<i class=\"bi bi-x-lg\"></i> " + escapeHtml(moduleMsg(panel, "cancel_button", "Cancel")),
                        closable: true,
                        backdropclose: false,
                        escclose: true
                    });
                }).then(function (result) {
                    if (!result || result.action !== "yes") {
                        return null;
                    }
                    return fetchJson(removeUrl, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ xmodul: modul, file: file, delete_file: 1 })
                    });
                }).then(function (data) {
                    if (data && data.ok && data.items) {
                        applyModuleImageItems(panel, data.items);
                    } else if (data && data.msg) {
                        window.alert(data.msg);
                    }
                }).catch(function (err) {
                    if (err && err.message) {
                        window.alert(err.message);
                    }
                }).finally(function () {
                    removeBtn.disabled = false;
                });
                return;
            }

            const galleryItem = e.target.closest(".dbx-module-images-item");
            if (galleryItem && !e.target.closest(".dbx-module-images-remove, .dbx-module-images-preview")) {
                const panel = galleryItem.closest(".dbx-module-images-panel");
                if (panel) {
                    selectGalleryItem(panel, galleryItem);
                }
            }
        });

        scope.addEventListener("focusin", function (e) {
            const panel = e.target.closest && e.target.closest(".dbx-module-images-panel");
            if (panel && !panel.dataset.modimgTargetBound) {
                bindModuleImagesPanel(panel);
            }
        });
    }

    function syncAccessGroupScroll(target) {
        const wraps = [];
        if (target && target.classList && target.classList.contains("dbx-module-access-groups-scroll")) {
            wraps.push(target);
        } else if (target && target.querySelectorAll) {
            qsa(target, ".dbx-module-access-groups-scroll").forEach(function (wrap) {
                wraps.push(wrap);
            });
        } else {
            qsa(document, ".dbx-module-access-groups-scroll").forEach(function (wrap) {
                wraps.push(wrap);
            });
        }

        wraps.forEach(function (wrap) {
            const select = qs(wrap, ".dbx-module-access-groups");
            if (!select) {
                return;
            }
            const h = wrap.clientHeight;
            if (h < 1) {
                return;
            }
            const style = window.getComputedStyle(select);
            const rowH = parseFloat(style.lineHeight) || (parseFloat(style.fontSize) * 1.45) || 20;
            const padding = (parseFloat(style.paddingTop) || 0) + (parseFloat(style.paddingBottom) || 0);
            const visible = Math.max(2, Math.floor((h - padding) / rowH));
            select.size = visible;
        });
    }

    function bindAccessGroupScroll(scope) {
        scope = scope || document;
        qsa(scope, ".dbx-module-access-groups-scroll").forEach(function (wrap) {
            if (wrap.__dbxAccessScrollBound === "1") {
                return;
            }
            wrap.__dbxAccessScrollBound = "1";
            const sync = function () {
                syncAccessGroupScroll(wrap);
            };
            sync();
            if (typeof ResizeObserver !== "undefined") {
                const ro = new ResizeObserver(sync);
                ro.observe(wrap);
                wrap.__dbxAccessScrollRo = ro;
            }
        });
    }

    function bindModuleAccess(scope) {
        scope = scope || document;
        qsa(scope, ".dbx-module-access-save").forEach(function (btn) {
            if (btn.dataset.accessBound === "1") {
                return;
            }
            btn.dataset.accessBound = "1";
            btn.addEventListener("click", function () {
                const panel = btn.closest(".dbx-module-access-panel");
                if (!panel) {
                    return;
                }
                const modul = panel.getAttribute("data-modul") || "";
                const saveUrl = btn.getAttribute("data-save-url") || "";
                const select = qs(panel, ".dbx-module-access-groups");
                const status = qs(panel, ".dbx-module-access-status");
                if (!modul || !saveUrl || !select) {
                    return;
                }
                const groups = Array.prototype.slice.call(select.selectedOptions).map(function (opt) {
                    return opt.value;
                });
                btn.disabled = true;
                if (status) {
                    status.textContent = moduleMsg(panel, "saving", "Saving …");
                    status.className = "dbx-module-access-status small text-muted";
                }
                fetchJson(saveUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ xmodul: modul, groups: groups })
                }).then(function (data) {
                    if (status) {
                        if (data && data.ok) {
                            status.textContent = data.msg || moduleMsg(panel, "saved", "Saved");
                            status.className = "dbx-module-access-status small is-ok";
                        } else {
                            status.textContent = (data && data.msg) ? data.msg : moduleMsg(panel, "save_failed", "Saving failed");
                            status.className = "dbx-module-access-status small is-error";
                        }
                    } else if (data && data.msg && !data.ok) {
                        window.alert(data.msg);
                    }
                }).catch(function (err) {
                    if (status) {
                        status.textContent = err && err.message ? err.message : moduleMsg(panel, "save_failed", "Saving failed");
                        status.className = "dbx-module-access-status small is-error";
                    }
                }).finally(function () {
                    btn.disabled = false;
                });
            });
        });
    }

    function syncModulePanelToggleIcon(panel) {
        const icon = qs(panel, ".dbx-module-toggle-icon");
        const btn = qs(panel, ".dbx-module-panel-toggle-btn");
        const isOpen = panel.classList.contains("is-open");
        if (icon) {
            icon.classList.toggle("bi-chevron-down", isOpen);
            icon.classList.toggle("bi-chevron-right", !isOpen);
        }
        if (btn) {
            btn.setAttribute("aria-expanded", isOpen ? "true" : "false");
        }
    }

    function setModulePanelOpen(panel, open) {
        panel.classList.toggle("is-open", !!open);
        syncModulePanelToggleIcon(panel);
        if (open) {
            window.requestAnimationFrame(function () {
                syncAccessGroupScroll(panel);
            });
        }
    }

    function bindModulePanels(root) {
        root = root || document;
        const saved = dbx.uiGet ? dbx.uiGet(LIB, PANEL_UI_ID, "openPanels", {}) : {};
        const openPanels = (saved && typeof saved === "object" && !Array.isArray(saved)) ? saved : {};

        qsa(root, ".dbx-module-panel-wrap").forEach(function (panel, index) {
            const key = panel.getAttribute("data-module-ui-state") || ("module-" + index);
            panel.setAttribute("data-module-ui-state", key);

            if (Object.prototype.hasOwnProperty.call(openPanels, key)) {
                setModulePanelOpen(panel, !!openPanels[key]);
            } else {
                syncModulePanelToggleIcon(panel);
            }

            if (panel.__dbxModuleToggleReady) {
                return;
            }
            panel.__dbxModuleToggleReady = true;

            function togglePanel() {
                const nextOpen = !panel.classList.contains("is-open");
                setModulePanelOpen(panel, nextOpen);
                const state = dbx.uiGet ? dbx.uiGet(LIB, PANEL_UI_ID, "openPanels", {}) : {};
                const next = (state && typeof state === "object" && !Array.isArray(state)) ? Object.assign({}, state) : {};
                next[key] = nextOpen;
                if (dbx.uiSet) {
                    dbx.uiSet(LIB, PANEL_UI_ID, "openPanels", next);
                }
            }

            const toggleBtn = qs(panel, ".dbx-module-panel-toggle-btn");
            if (toggleBtn) {
                toggleBtn.addEventListener("click", function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    togglePanel();
                });
            }

            const summaryMain = qs(panel, ".dbx-module-summary-main");
            if (summaryMain) {
                summaryMain.addEventListener("click", function (e) {
                    if (e.target.closest("input, label, .dbx-module-row-select")) {
                        return;
                    }
                    e.preventDefault();
                    togglePanel();
                });
            }
        });
    }

    if (!document.__dbxModulePanelsBound) {
        document.__dbxModulePanelsBound = true;
        bindModulePanels(document);
    }

    function ensureConfirm() {
        if (dbx.confirm && typeof dbx.confirm.open === "function") {
            return Promise.resolve(true);
        }
        return new Promise(function (resolve) {
            if (!dbx.loadFeature) {
                resolve(false);
                return;
            }
            dbx.loadFeature("confirm", function () {
                resolve(!!(dbx.confirm && typeof dbx.confirm.open === "function"));
            });
        });
    }

    function updateActiveToggleButton(btn, active, responseLabel) {
        const isActive = String(active) === "1";
        btn.setAttribute("data-active", isActive ? "1" : "0");
        btn.classList.toggle("btn-success", isActive);
        btn.classList.toggle("btn-outline-secondary", !isActive);
        btn.innerHTML = '<i class="bi ' + (isActive ? "bi-toggle-on" : "bi-toggle-off") + '"></i> '
            + escapeHtml(responseLabel || moduleMsg(btn, isActive ? "active" : "inactive", isActive ? "Active" : "Inactive"));
    }

    function bindModuleBarActions(root) {
        root = root || document;

        qsa(root, ".dbx-module-active-toggle").forEach(function (btn) {
            if (btn.dataset.modActiveBound === "1") {
                return;
            }
            btn.dataset.modActiveBound = "1";
            btn.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                const url = btn.getAttribute("data-toggle-url") || "";
                const modul = btn.getAttribute("data-modul") || "";
                if (!url || !modul) {
                    return;
                }
                btn.disabled = true;
                fetchJson(url, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ xmodul: modul })
                }).then(function (data) {
                    if (data && data.ok) {
                        updateActiveToggleButton(btn, data.active, data.active_label);
                        const panel = btn.closest(".dbx-module-panel-wrap");
                        const row = btn.closest(".dbx-module-row");
                        if (panel) {
                            qsa(panel, "[data-module-status-badge]").forEach(function (badge) {
                                const isActive = String(data.active) === "1";
                                badge.textContent = data.active_label || moduleMsg(badge, isActive ? "active" : "inactive", isActive ? "Active" : "Inactive");
                                badge.classList.toggle("text-bg-success", isActive);
                                badge.classList.toggle("text-bg-secondary", !isActive);
                            });
                        }
                        if (row) {
                            row.classList.toggle("dbx-module-row-inactive", String(data.active) !== "1");
                        }
                    } else if (data && data.msg) {
                        window.alert(data.msg);
                    }
                }).catch(function (err) {
                    window.alert(err && err.message ? err.message : moduleMsg(btn, "status_save_error", "Status could not be saved."));
                }).finally(function () {
                    btn.disabled = false;
                });
            });
        });

        qsa(root, ".dbx-module-delete-btn").forEach(function (btn) {
            if (btn.dataset.modDeleteBound === "1") {
                return;
            }
            btn.dataset.modDeleteBound = "1";
            btn.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                const url = btn.getAttribute("data-delete-url") || "";
                const modul = btn.getAttribute("data-modul") || "";
                const title = btn.getAttribute("data-title") || modul;
                const panelRoot = btn.closest(".dbx-modules-admin") || document.body;
                if (!url || !modul) {
                    return;
                }

                ensureConfirm().then(function (ok) {
                    if (!ok) {
                        dbx.warn("[modulesAdmin] " + moduleMsg(btn, "confirm_unavailable", "The confirmation dialog could not be loaded."));
                        return null;
                    }
                    return dbx.confirm.open({
                        id: "module-delete-" + modul + "-" + Date.now(),
                        root: panelRoot,
                        source: btn,
                        title: moduleMsg(btn, "module_delete_title", "Delete module"),
                        question: moduleMsg(btn, "module_delete_question", "Delete module {module} completely?", { module: "<strong>" + escapeHtml(title) + "</strong>" }),
                        hint: moduleMsg(btn, "module_delete_hint", "The module directory and all its files will be removed permanently."),
                        buttons: "yesno",
                        labelyes: "<i class=\"bi bi-trash\"></i> " + escapeHtml(moduleMsg(btn, "delete_button", "Delete")),
                        labelno: "<i class=\"bi bi-x-lg\"></i> " + escapeHtml(moduleMsg(btn, "cancel_button", "Cancel")),
                        closable: true,
                        backdropclose: false,
                        escclose: true
                    });
                }).then(function (result) {
                    if (!result || result.action !== "yes") {
                        return null;
                    }
                    return fetchJson(url, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ xmodul: modul })
                    });
                }).then(function (data) {
                    if (!data) {
                        return;
                    }
                    if (data.ok) {
                        const row = btn.closest(".dbx-module-row");
                        if (row) {
                            row.remove();
                        }
                    } else if (data.msg) {
                        window.alert(data.msg);
                    }
                }).catch(function (err) {
                    if (err && err.message) {
                        window.alert(err.message);
                    }
                });
            });
        });
    }

    function bindModimgManager(root) {
        const manager = qs(root, ".dbx-modimg-manager");
        if (!manager || manager.dataset.modimgBound === "1") {
            return;
        }
        manager.dataset.modimgBound = "1";
        manager.classList.add("dbx-module-images-panel");
        if (!manager.getAttribute("data-module-images-list")) {
            const gallery = qs(manager, "[data-module-images-list]");
            if (gallery) {
                manager.setAttribute("data-module-images-list", "1");
            }
        }
    }

    function initModulesAdmin(root) {
        bindModuleDdGallery(root || document);
        bindModimgManager(root || document);
        bindModuleImagesPanels(root || document);
        bindModuleAccess(root || document);
        bindAccessGroupScroll(root || document);
        bindModulePanels(root || document);
        bindModuleBarActions(root || document);
        syncAccessGroupScroll(root || document);
    }

    if (!document.__dbxModuleImagesBound) {
        document.__dbxModuleImagesBound = true;
        bindModuleImages(document);
    }

    dbx.modulesAdmin = {
        init: initModulesAdmin,
        openImagesBrowser: openModuleImagesBrowser,
        rescan: function (ctx) {
            const root = ctx && ctx.root ? ctx.root : (ctx || document);
            initModulesAdmin(root);
        }
    };

    dbx.feature.register("modulesAdmin", {
        scope: "element",
        js: [
            ["js", "lib", "openWin.js"],
            ["js", "lib", "confirm.js"]
        ],
        css: [
            ["css", "root", "modules/dbxAdmin/tpl/css/modules-admin.css"],
            ["css", "design", "c-cms.css"]
        ],
        init: function (el) {
            dbx.modulesAdmin.init(el);
        },
        rescan: function (ctx) {
            dbx.modulesAdmin.rescan(ctx);
        }
    });
})(window, document);
