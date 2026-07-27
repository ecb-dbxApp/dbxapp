/*!
 * dbxapp kiBriefing.js
 * Compact dbxKi briefing helpers.
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][kiBriefing] dbx core missing");
        return;
    }

    const dbx = window.dbx;
    const LIB = "kiBriefing";

    function qs(root, sel) {
        return root ? root.querySelector(sel) : null;
    }

    function qsa(root, sel) {
        return root ? Array.from(root.querySelectorAll(sel)) : [];
    }

    function closest(target, sel) {
        const el = target && target.nodeType === 1 ? target : target && target.parentElement;
        return el && el.closest ? el.closest(sel) : null;
    }

    function escapeHtml(value) {
        const div = document.createElement("div");
        div.textContent = value == null ? "" : String(value);
        return div.innerHTML;
    }

    function apiUrl(url, params) {
        const out = new URL(String(url || window.location.href), window.location.href);
        Object.keys(params || {}).forEach(key => out.searchParams.set(key, params[key]));
        out.searchParams.set("_", Date.now());
        return out.toString();
    }

    function fetchJson(url) {
        if (!dbx.ajax || typeof dbx.ajax.request !== "function") {
            return Promise.reject(new Error("ajax.js nicht geladen."));
        }
        return dbx.ajax.request({ url: url, method: "GET", mode: "json", timeout: 20000 });
    }

    function state(root) {
        root.__dbxKiBriefing = root.__dbxKiBriefing || {
            nodes: [],
            flat: [],
            collapsed: new Set(),
            selectedPage: 0,
            selectedFolder: 0
        };
        return root.__dbxKiBriefing;
    }

    function setStatus(root, msg, type) {
        const el = qs(root, "[data-ki-status]");
        if (!el) return;
        el.textContent = msg || "";
        el.className = type === "error" ? "text-danger" : type === "success" ? "text-success" : "";
    }

    function currentLng(root, cfg) {
        const select = qs(root, "[data-ki-lng]");
        return String((select && select.value) || (cfg && cfg.lng) || "").toLowerCase();
    }

    function mode(root, cfg) {
        return String((cfg && cfg.mode) || root.getAttribute("data-ki-mode") || "").toLowerCase();
    }

    function syncTargetLanguageModes(root, cfg) {
        const lng = currentLng(root, cfg);
        qsa(root, "[data-ki-target-lng]").forEach(label => {
            const target = String(label.getAttribute("data-ki-target-lng") || "").toLowerCase();
            const mode = qs(label, "[data-ki-target-mode]");
            const isSource = target && target === lng;
            label.classList.toggle("is-source", isSource);
            if (mode) mode.textContent = isSource ? "Rechtschreibung/Grammatik" : "Uebersetzung";
        });
    }

    function nodeMatches(node, search) {
        if (!search) return true;
        return String(node._title || "").toLowerCase().includes(search);
    }

    function childrenMatch(node, search) {
        return Array.isArray(node._children) && node._children.some(child => nodeMatches(child, search) || childrenMatch(child, search));
    }

    function renderTree(root) {
        const s = state(root);
        const box = qs(root, "[data-ki-tree]");
        if (!box) return;
        const search = String((qs(root, "[data-ki-tree-search]") || {}).value || "").toLowerCase();

        function rowHtml(node, type, active, collapsed, hasChildren) {
            const id = Number(node._id || 0);
            const toggle = type === "folder" && hasChildren
                ? `<span class="dbx-cms-tree-toggle" data-ki-tree-toggle data-id="${id}" title="Ordner ein- oder ausklappen"><i class="bi ${collapsed ? "bi-chevron-right" : "bi-chevron-down"}"></i></span>`
                : '<span class="dbx-cms-tree-toggle-spacer"></span>';
            let html = String(node._row_html || "");
            if (!html) {
                const icon = type === "folder" ? "bi-folder2-open" : "bi-file-earmark-text";
                const title = type === "page"
                    ? `<span class="dbx-cms-page-id">(${id})</span> ${escapeHtml(node._title || "")}`
                    : escapeHtml(node._title || "");
                html = `<div role="button" tabindex="0" class="dbx-cms-tree-row dbx-cms-tree-${type}" data-type="${type}" data-id="${id}" data-folder="${escapeHtml(node._parent || 0)}">${toggle}<span class="dbx-cms-tree-icons"><i class="bi ${icon}"></i></span><span class="dbx-cms-tree-label">${title}</span><span class="dbx-cms-tree-meta"></span></div>`;
            } else {
                html = html.replace(/\sdraggable="true"/g, "");
                html = html.replace(/<button[\s\S]*?data-cms-folder-edit-btn[\s\S]*?<\/button>/g, "");
                if (!html.includes("data-ki-tree-toggle") && !html.includes("dbx-cms-tree-toggle-spacer")) {
                    html = html.replace('<span class="dbx-cms-tree-icons">', toggle + '<span class="dbx-cms-tree-icons">');
                }
            }
            if (active && !html.includes(" is-active")) {
                html = html.replace("dbx-cms-tree-row", "dbx-cms-tree-row is-active");
            }
            if (type === "folder" && collapsed && !html.includes(" is-collapsed")) {
                html = html.replace("dbx-cms-tree-row", "dbx-cms-tree-row is-collapsed");
            }
            return html;
        }

        function renderNode(node) {
            if (!node || typeof node !== "object") return "";
            const children = Array.isArray(node._children) ? node._children : [];
            const type = node._type === "folder" ? "folder" : "page";
            const visible = nodeMatches(node, search) || childrenMatch(node, search);
            if (!visible) return "";
            const id = Number(node._id || 0);
            const collapsed = type === "folder" && !search && s.collapsed.has(id);
            const active = mode(root, {}) === "create"
                ? ((type === "page" && Number(s.selectedPage || 0) === id) || (type === "folder" && Number(s.selectedFolder || 0) === id && !Number(s.selectedPage || 0)))
                : (type === "page" && Number(s.selectedPage || 0) === id);
            let html = rowHtml(node, type, active, collapsed, children.length > 0);
            if (children.length) {
                html += `<div class="dbx-cms-tree-children"${collapsed ? " hidden" : ""}>`;
                children.forEach(child => { html += renderNode(child); });
                html += "</div>";
            }
            return html;
        }

        box.innerHTML = s.nodes.length
            ? s.nodes.map(renderNode).join("")
            : '<div class="dbx-cms-empty">Keine Content-Struktur vorhanden.</div>';
    }

    function loadTree(root, cfg) {
        const box = qs(root, "[data-ki-tree]");
        if (box) box.innerHTML = '<div class="dbx-cms-empty">Tree wird geladen...</div>';
        return fetchJson(apiUrl(cfg.tree, { dbx_lng: currentLng(root, cfg) }))
            .then(data => {
                if (!data || !data.ok) throw new Error("tree failed");
                const s = state(root);
                s.nodes = Array.isArray(data.nodes) ? data.nodes : [];
                s.flat = Array.isArray(data.flat) ? data.flat : [];
                renderTree(root);
            })
            .catch(err => {
                dbx.error("[kiBriefing] tree load failed", err);
                if (box) box.innerHTML = '<div class="dbx-cms-empty">Tree konnte nicht geladen werden.</div>';
            });
    }

    function rowLabel(row) {
        const label = qs(row, ".dbx-cms-tree-label");
        return String(label ? label.textContent : "").replace(/\s+/g, " ").trim();
    }

    function nodeTitle(root, type, id) {
        const s = state(root);
        const found = (s.flat || []).find(node => String(node._type || "") === type && Number(node._id || 0) === Number(id || 0));
        return found ? String(found._title || "").replace(/\s+/g, " ").trim() : "";
    }

    function updateCreateContext(root, folderId, folderLabel, pageId, pageLabel) {
        const context = qs(root, "[data-ki-context]");
        const folderHidden = qs(root, "[data-ki-folder-id]");
        const pageHidden = qs(root, "[data-ki-page-id]");
        const title = qs(root, ".dbx-ki-update-head h2");
        folderId = Number(folderId || 0);
        pageId = Number(pageId || 0);
        folderLabel = folderLabel || (folderId > 0 ? ("Ordner #" + folderId) : "Noch kein Zielordner gewaehlt");
        pageLabel = pageLabel || "";
        if (folderHidden) folderHidden.value = folderId > 0 ? String(folderId) : "";
        if (pageHidden) pageHidden.value = pageId > 0 ? String(pageId) : "";
        if (title) title.textContent = pageId > 0
            ? `Unter ${pageLabel} in ${folderLabel}`
            : (folderId > 0 ? `${folderLabel} / am Ende` : "Zielposition waehlen");
        if (context) {
            context.innerHTML = `<div class="dbx-ki-page-context dbx-ki-create-placement">
 <div class="dbx-ki-page-context-head"><strong>Zielposition</strong><span>neu</span></div>
 <dl class="dbx-ki-page-context-meta">
  <div><dt>Ordner</dt><dd>${escapeHtml(folderLabel)} <span class="text-muted">#${folderId || "-"}</span></dd></div>
  <div><dt>Unter</dt><dd>${pageId > 0 ? escapeHtml(pageLabel) : "Keine Seite als Sortieranker"} <span class="text-muted">#${pageId || "-"}</span></dd></div>
  <div><dt>Sorter</dt><dd>${pageId > 0 ? "direkt unter dieser Seite" : "automatisch am Ende"}</dd></div>
 </dl>
</div>`;
        }
    }

    function selectCreatePlacement(root, folderId, folderLabel, pageId, pageLabel) {
        const s = state(root);
        s.selectedFolder = Number(folderId || 0);
        s.selectedPage = Number(pageId || 0);
        updateCreateContext(root, s.selectedFolder, folderLabel, s.selectedPage, pageLabel);
        renderTree(root);
        setStatus(root, s.selectedFolder > 0 ? "Zielposition gewaehlt." : "Bitte Zielordner waehlen.", s.selectedFolder > 0 ? "success" : "error");
    }

    function selectPage(root, cfg, pageId) {
        pageId = Number(pageId || 0);
        if (!pageId) return Promise.resolve();
        const s = state(root);
        s.selectedPage = pageId;
        const hidden = qs(root, "[data-ki-page-id]");
        if (hidden) hidden.value = String(pageId);
        renderTree(root);
        setStatus(root, "Seite wird geladen...", "info");
        const preview = qs(root, "[data-ki-preview]");
        if (preview) preview.innerHTML = '<div class="dbx-cms-empty">Seite wird geladen...</div>';

        return fetchJson(apiUrl(cfg.preview, { id: pageId, dbx_lng: currentLng(root, cfg) }))
            .then(data => {
                if (!data || !data.ok) throw new Error((data && data.error) || "preview failed");
                const title = data.title || ("Seite #" + pageId);
                const titleEl = qs(root, "[data-ki-selected-title]");
                const headTitle = qs(root, ".dbx-ki-update-head h2");
                if (titleEl) titleEl.textContent = title;
                if (headTitle) headTitle.textContent = title;
                if (preview) {
                    preview.innerHTML = data.html || '<div class="dbx-cms-empty">Keine Vorschau vorhanden.</div>';
                    if (dbx.rescan) dbx.rescan(preview);
                }
                const context = qs(root, "[data-ki-context]");
                if (context) context.innerHTML = data.context_html || "";
                setStatus(root, "Gerenderte dbxContent-Vorschau", "success");
            })
            .catch(err => {
                dbx.error("[kiBriefing] preview load failed", err);
                if (preview) preview.innerHTML = '<div class="dbx-cms-empty">Seite konnte nicht geladen werden.</div>';
                setStatus(root, "Seite konnte nicht geladen werden.", "error");
            });
    }

    function bind(root, cfg) {
        if (cfg && cfg.mode) root.setAttribute("data-ki-mode", cfg.mode);
        const isCreate = mode(root, cfg) === "create";
        const initial = Number(cfg.pageid || 0);
        if (initial > 0) state(root).selectedPage = initial;
        const initialFolder = Number(cfg.folderid || 0);
        if (initialFolder > 0) state(root).selectedFolder = initialFolder;

        const search = qs(root, "[data-ki-tree-search]");
        if (search) search.addEventListener("input", () => renderTree(root));

        const lng = qs(root, "[data-ki-lng]");
        if (lng) {
            lng.addEventListener("change", () => {
                const selected = Number((qs(root, "[data-ki-page-id]") || {}).value || 0);
                syncTargetLanguageModes(root, cfg);
                loadTree(root, cfg).then(() => {
                    if (!isCreate && selected > 0) selectPage(root, cfg, selected);
                });
            });
        }

        root.addEventListener("click", e => {
            const toggle = closest(e.target, "[data-ki-tree-toggle]");
            if (toggle && root.contains(toggle)) {
                e.preventDefault();
                e.stopPropagation();
                const id = Number(toggle.getAttribute("data-id") || 0);
                const s = state(root);
                if (s.collapsed.has(id)) s.collapsed.delete(id);
                else s.collapsed.add(id);
                renderTree(root);
                return;
            }

            const row = closest(e.target, ".dbx-cms-tree-row");
            if (!row || !root.contains(row)) return;
            const type = row.getAttribute("data-type");
            const id = Number(row.getAttribute("data-id") || 0);
            if (type === "folder") {
                if (isCreate) {
                    selectCreatePlacement(root, id, rowLabel(row), 0, "");
                    return;
                }
                const s = state(root);
                if (s.collapsed.has(id)) s.collapsed.delete(id);
                else s.collapsed.add(id);
                renderTree(root);
                return;
            }
            if (type === "page") {
                if (isCreate) {
                    const folderId = Number(row.getAttribute("data-folder") || 0);
                    selectCreatePlacement(root, folderId, nodeTitle(root, "folder", folderId) || ("Ordner #" + folderId), id, rowLabel(row));
                    return;
                }
                selectPage(root, cfg, id);
            }
        });

        root.addEventListener("keydown", e => {
            if (e.key !== "Enter" && e.key !== " ") return;
            const row = closest(e.target, ".dbx-cms-tree-row");
            if (!row || !root.contains(row)) return;
            e.preventDefault();
            row.click();
        });

        const form = qs(root, "[data-ki-form]");
        if (form) {
            form.addEventListener("submit", e => {
                const folderField = qs(root, "[data-ki-folder-id]");
                if (folderField) {
                    const folderId = Number(folderField.value || 0);
                    if (folderId <= 0) {
                        e.preventDefault();
                        setStatus(root, "Bitte zuerst einen Zielordner im Content Tree waehlen.", "error");
                    }
                    return;
                }
                const pageId = Number((qs(root, "[data-ki-page-id]") || {}).value || 0);
                if (pageId <= 0) {
                    e.preventDefault();
                    setStatus(root, "Bitte zuerst eine Seite im Content Tree waehlen.", "error");
                    const tree = qs(root, "[data-ki-tree]");
                    if (tree) tree.focus();
                    return;
                }
                const targets = qsa(root, "input[name='target_lngs[]']");
                if (targets.length && !targets.some(input => input.checked)) {
                    e.preventDefault();
                    setStatus(root, "Bitte mindestens eine Ziel- oder Korrektursprache waehlen.", "error");
                }
            });
        }

        syncTargetLanguageModes(root, cfg);
        loadTree(root, cfg).then(() => {
            if (isCreate && initialFolder > 0) {
                updateCreateContext(root, initialFolder, "Ordner #" + initialFolder, initial, initial > 0 ? ("Seite #" + initial) : "");
            }
        });
    }

    dbx.feature.register(LIB, {
        scope: "element",
        priority: "mid",
        css: [
            ["css", "design", "c-cms.css"],
            ["css", "design", "c-form.css"]
        ],
        js: [
            ["js", "lib", "ajax.js"]
        ],
        init(el, cfg) {
            if (!el || el.__dbxKiBriefingReady) return;
            el.__dbxKiBriefingReady = true;
            bind(el, cfg || {});
        },
        rescan(root) {
            (root || document).querySelectorAll(".dbx-ki-update[data-dbx]").forEach(el => {
                if (el.__dbxKiBriefingReady) return;
                const cfgList = dbx.parseData(el.getAttribute("data-dbx"));
                const cfg = cfgList.find(item => item.lib === LIB) || {};
                this.init(el, cfg);
            });
        }
    });

})(window, document);
