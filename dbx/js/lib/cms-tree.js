/*!
 * dbxapp cms-tree.js
 * Lazy Content-Baum.
 * Registrierung allein hat keine Seiteneffekte; geladen wird dieses Modul
 * erst beim Oeffnen, Suchen oder expliziten Ansteuern eines Ordners.
 */
(function (window, document) {
    "use strict";

    const dbx = window.dbx;
    const runtime = dbx && dbx.cmsRuntime;
    if (!runtime || typeof runtime.register !== "function") {
        console.error("[dbx][cms-tree] CMS runtime missing");
        return;
    }

    runtime.register("tree", function (context) {
        const {
            dbx,
            qs,
            status,
            apiUrl,
            fetchJson,
            escapeHtml,
            state,
            cfgUrl,
            isViewMode,
            setFolderCollapsed,
            isFolderCollapsed,
            setSelectedPage,
            setSelectedType,
            revealTreeSelection,
            cmsLngParams,
            updateViewPageTitle,
            cmsConfig,
            findNode,
            buildPageFolderOptions,
            showFolderEditor,
            loadViewPage,
            loadPage,
            loadMedia
        } = context;
        function folderTreeEditButton(id) {
            return `<button type="button" class="dbx-cms-tree-edit" data-cms-folder-edit-btn data-id="${escapeHtml(id)}" data-dbx-tooltip="Ordner bearbeiten" aria-label="Ordner bearbeiten"><i class="bi bi-pencil-square"></i></button>`;
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
                    ? `<span class="dbx-cms-tree-toggle" data-cms-tree-toggle data-id="${escapeHtml(node._id)}" data-dbx-tooltip="Ordner ein- oder ausklappen"><i class="bi ${toggleClass}"></i></span>`
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
                const rights = type === "folder" && node._rights ? `<span class="dbx-cms-rights" data-cms-folder-edit data-dbx-tooltip="Ordnerrechte bearbeiten">${escapeHtml(node._rights)}</span>` : "";
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
            if (s.treeLoading && s.treePromise) return s.treePromise;
            s.treeLoading = true;
            if (box) box.innerHTML = '<div class="dbx-cms-empty">Tree wird geladen...</div>';

            s.treePromise = fetchJson(apiUrl(url, cmsLngParams(root)))
                .then(data => {
                    s.tree = Array.isArray(data.nodes) ? data.nodes : [];
                    s.flat = Array.isArray(data.flat) ? data.flat : [];
                    s.treeLoaded = true;
                    buildPageFolderOptions(root);
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
                    revealTreeSelection(root);
                })
                .catch(err => {
                    s.treeLoaded = false;
                    dbx.error("[cms] tree load failed", err);
                    if (box) {
                        box.innerHTML = '<div class="dbx-cms-empty">Tree konnte nicht geladen werden.</div>';
                    }
                    status(root, "Tree konnte nicht geladen werden.", "error");
                })
                .finally(() => {
                    s.treeLoading = false;
                    s.treePromise = null;
                });
            return s.treePromise;
        }

        function ensureTreeLoaded(root, cfg) {
            const s = state(root);
            if (s.treeLoaded) return Promise.resolve(s.tree);
            return loadTree(root, cfg || cmsConfig(root));
        }

        return Object.freeze({
            renderTree,
            toggleTreeFolder,
            loadTree,
            ensureTreeLoaded
        });
    });

})(window, document);
