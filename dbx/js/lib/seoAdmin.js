/*!
 * dbxapp seoAdmin.js — SEO settings per content page
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][seoAdmin] dbx core missing");
        return;
    }

    const dbx = window.dbx;
    const LIB = "seoAdmin";

    function text(root, key, fallback) {
        if (!root.__dbxSeoMessages) {
            try {
                root.__dbxSeoMessages = JSON.parse(root.getAttribute("data-seo-messages") || "{}");
            } catch (err) {
                root.__dbxSeoMessages = {};
            }
        }
        const value = root.__dbxSeoMessages[key];
        return typeof value === "string" && value !== "" ? value : fallback;
    }

    function qs(root, sel) {
        return root ? root.querySelector(sel) : null;
    }

    function setStatus(root, msg, type) {
        const el = qs(root, "[data-seo-status]");
        if (!el) return;
        el.textContent = msg || "";
        el.className = "dbx-seo-status small" + (type === "error" ? " text-danger" : type === "success" ? " text-success" : " text-muted");
    }

    function apiUrl(url, params) {
        const out = new URL(String(url || window.location.href), window.location.href);
        Object.keys(params || {}).forEach(key => out.searchParams.set(key, params[key]));
        out.searchParams.set("_", Date.now());
        return out.toString();
    }

    function fetchJson(url, opt) {
        opt = opt || {};
        return dbx.ajax.request({
            url: url,
            method: opt.method || "GET",
            mode: "json",
            body: (typeof opt.body !== "undefined") ? opt.body : null,
            headers: opt.headers || {},
            timeout: 20000
        });
    }

    function getField(root, name) {
        const el = root.querySelector('[data-cms-field="' + name + '"]');
        if (!el) return "";
        return el.value;
    }

    function setField(root, name, value) {
        const el = root.querySelector('[data-cms-field="' + name + '"]');
        if (!el) return;
        el.value = value == null ? "" : String(value);
    }

    function state(root) {
        if (!root.__dbxSeoState) {
            root.__dbxSeoState = { previewRow: null, loading: false };
        }
        return root.__dbxSeoState;
    }

    function renderPreview(root) {
        const preview = qs(root, "[data-seo-preview]");
        if (!preview) return;
        const id = Number(getField(root, "seo_image_id") || 0);
        const s = state(root);
        const row = (s.previewRow && Number(s.previewRow.id || 0) === id) ? s.previewRow : null;
        if (id <= 0) {
            preview.innerHTML = '<div class="dbx-cms-seo-preview-empty">' + text(root, "og_empty", "No OG image selected.") + '</div>';
            return;
        }
        if (!row) {
            preview.innerHTML = '<div class="dbx-cms-seo-preview-empty">' + text(root, "og_loading", "Loading OG image.") + '</div>';
            return;
        }
        const url = row.preview_url || row.thumb_url || "";
        if (!url || String(row.mime || "").indexOf("image/") !== 0 && row.media_type !== "image") {
            preview.innerHTML = '<div class="dbx-cms-seo-preview-empty">' + text(root, "og_not_image", "The selected medium is not an image.") + '</div>';
            return;
        }
        const title = row.title || "OG-Bild";
        preview.innerHTML = '<img src="' + url + '" alt="' + title.replace(/"/g, "") + '" class="img-fluid rounded border">';
    }

    function loadPage(root, cfg, cid) {
        const pageId = Number(cid || 0);
        if (!cfg.page || pageId <= 0) return Promise.resolve();
        const s = state(root);
        if (s.loading) return Promise.resolve();
        s.loading = true;
        setStatus(root, text(root, "load_start", "Loading SEO data..."), "info");

        return fetchJson(apiUrl(cfg.page, { id: pageId }))
            .then(data => {
                s.loading = false;
                if (!data || !data.ok) throw new Error("load failed");
                const row = data.row || {};
                setField(root, "id", row.id || pageId);
                setField(root, "keywords", row.keywords || "");
                setField(root, "meta_robots", row.meta_robots || "index,follow");
                setField(root, "seo_title", row.seo_title || "");
                setField(root, "seo_image_id", row.seo_image_id || 0);
                s.previewRow = data.seo_preview_media || null;
                renderPreview(root);
                setStatus(root, "", "info");
            })
            .catch(err => {
                s.loading = false;
                dbx.error("[seoAdmin] load failed", err);
                setStatus(root, text(root, "load_error", "SEO data could not be loaded."), "error");
            });
    }

    function savePage(root, cfg) {
        if (!cfg.save) return Promise.resolve();
        const payload = {
            id: Number(getField(root, "id") || 0),
            keywords: getField(root, "keywords"),
            meta_robots: getField(root, "meta_robots") || "index,follow",
            seo_title: getField(root, "seo_title"),
            seo_image_id: Number(getField(root, "seo_image_id") || 0)
        };
        if (payload.id <= 0) {
            setStatus(root, text(root, "no_page_selected", "No page selected."), "error");
            return Promise.resolve();
        }

        setStatus(root, text(root, "save_start", "Saving SEO data..."), "info");
        return fetchJson(cfg.save, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        }).then(data => {
            if (!data || !data.ok) throw new Error("save failed");
            state(root).previewRow = data.seo_preview_media || null;
            renderPreview(root);
            setStatus(root, text(root, "seo_saved", "SEO saved."), "success");
        }).catch(err => {
            dbx.error("[seoAdmin] save failed", err);
            setStatus(root, text(root, "save_error", "Data could not be saved."), "error");
        });
    }

    function openSimpleMediaPicker(root, cfg, onPick) {
        if (!cfg.media) return Promise.resolve();
        return fetchJson(apiUrl(cfg.media, { images: 1, sync: 1, media_type: "image" }))
            .then(data => {
                const rows = (data && data.rows) ? data.rows : [];
                const images = rows.filter(row => row && row.id && (row.media_type === "image" || String(row.mime || "").indexOf("image/") === 0));
                if (!images.length) {
                    setStatus(root, text(root, "no_media_images", "There are no images in the media library."), "error");
                    return;
                }
                let modal = document.getElementById("dbxSeoMediaPicker");
                if (!modal) {
                    modal = document.createElement("div");
                    modal.id = "dbxSeoMediaPicker";
                    modal.className = "dbx-seo-media-picker modal fade";
                    modal.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">' + text(root, "picker_title", "Select OG image") + '</h5><button type="button" class="btn-close" data-seo-media-close></button></div><div class="modal-body"><div class="row g-2" data-seo-media-grid></div></div></div></div>';
                    document.body.appendChild(modal);
                }
                const grid = modal.querySelector("[data-seo-media-grid]");
                grid.innerHTML = images.map(row => {
                    const url = row.preview_url || row.thumb_url || ("index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=" + row.id);
                    const title = String(row.title || row.file_name || ("#" + row.id)).replace(/"/g, "");
                    return '<div class="col-6 col-md-4 col-lg-3"><button type="button" class="btn btn-light border w-100 p-2 text-start" data-seo-media-pick="' + row.id + '"><img src="' + url + '" alt="" class="img-fluid rounded mb-2"><span class="small d-block text-truncate">' + title + "</span></button></div>";
                }).join("");
                const close = () => {
                    modal.classList.remove("show");
                    modal.style.display = "none";
                    document.body.classList.remove("modal-open");
                };
                modal.querySelector("[data-seo-media-close]").onclick = close;
                modal.onclick = e => { if (e.target === modal) close(); };
                grid.onclick = e => {
                    const btn = e.target.closest("[data-seo-media-pick]");
                    if (!btn) return;
                    const id = Number(btn.getAttribute("data-seo-media-pick") || 0);
                    const row = images.find(item => Number(item.id) === id);
                    if (row && typeof onPick === "function") onPick(row);
                    close();
                };
                modal.classList.add("show");
                modal.style.display = "block";
                document.body.classList.add("modal-open");
            });
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

    function bind(root, cfg) {
        const select = qs(root, "[data-seo-page-select]");
        if (select) {
            select.addEventListener("change", () => {
                const cid = Number(select.value || 0);
                if (cid > 0) loadPage(root, cfg, cid);
            });
        }

        root.addEventListener("click", e => {
            const btn = e.target.closest("[data-seo-action]");
            if (!btn || !root.contains(btn)) return;
            const action = btn.getAttribute("data-seo-action");

            if (action === "save") {
                e.preventDefault();
                savePage(root, cfg);
                return;
            }

            if (action === "assign-media") {
                e.preventDefault();
                ensureCmsMediaBrowser().then(function (ok) {
                    if (!ok || !dbx.cmsMediaBrowser) {
                        if (!cfg.media) {
                            setStatus(root, text(root, "media_api_missing", "The media API is not configured."), "error");
                            return;
                        }
                        openSimpleMediaPicker(root, cfg, row => {
                            state(root).previewRow = row;
                            setField(root, "seo_image_id", row.id);
                            renderPreview(root);
                            setStatus(root, text(root, "og_selected_pending", "OG image selected (save still required)."), "success");
                        }).catch(err => {
                            dbx.error("[seoAdmin] media list failed", err);
                            setStatus(root, text(root, "media_load_error", "Media could not be loaded."), "error");
                        });
                        return;
                    }
                    dbx.cmsMediaBrowser.open(root, cfg, {
                        mode: "assign",
                        slot: "seo",
                        afterAssign(row) {
                            if (!row || !row.id) return;
                            state(root).previewRow = row;
                            setField(root, "seo_image_id", row.id);
                            renderPreview(root);
                            setStatus(root, text(root, "og_selected_pending", "OG image selected (save still required)."), "success");
                        }
                    });
                });
                return;
            }

            if (action === "clear-media") {
                e.preventDefault();
                state(root).previewRow = null;
                setField(root, "seo_image_id", 0);
                renderPreview(root);
                setStatus(root, text(root, "og_removed_pending", "OG image removed (save still required)."), "info");
            }
        });
    }

    const seoFeature = {
        scope: "element",
        priority: "mid",
        css: [
            ["css", "design", "c-cms.css"]
        ],
        js: [
            ["js", "lib", "ajax.js"]
        ],

        init(el, cfg) {
            if (!el || el.__dbxSeoReady) return;
            el.__dbxSeoReady = true;
            cfg = cfg || {};
            bind(el, cfg);
            const select = qs(el, "[data-seo-page-select]");
            const cid = Number((select && select.value) || cfg.cid || 0);
            if (cid > 0) loadPage(el, cfg, cid);
        },

        rescan(root) {
            (root || document).querySelectorAll(".dbx-seo-admin[data-dbx]").forEach(el => {
                if (el.__dbxSeoReady) return;
                const cfgList = dbx.parseData(el.getAttribute("data-dbx"));
                const cfg = cfgList.find(item => item.lib === LIB) || {};
                this.init(el, cfg);
            });
        }
    };

    dbx.feature.register(LIB, seoFeature);

})(window, document);
