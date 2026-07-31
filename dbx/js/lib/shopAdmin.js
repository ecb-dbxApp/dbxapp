/*!
 * @file shopAdmin.js
 * Shop-Administration: CMS-Medienbrowser fuer Artikelbilder.
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        return;
    }

    const dbx = window.dbx;
    const LIB = "shopAdmin";
    let reloadTimer = null;

    function cfg(root) {
        root = root || document.querySelector(".dbx-shop-media-manager") || document;
        return {
            media: root.getAttribute("data-shop-media") || "",
            upload: root.getAttribute("data-shop-upload") || "",
            externalvideo: root.getAttribute("data-shop-externalvideo") || "",
            mediafolders: root.getAttribute("data-shop-mediafolders") || "",
            mediafoldercreate: root.getAttribute("data-shop-mediafoldercreate") || "",
            mediafolderdelete: root.getAttribute("data-shop-mediafolderdelete") || "",
            mediafolderrename: root.getAttribute("data-shop-mediafolderrename") || "",
            mediamove: root.getAttribute("data-shop-mediamove") || "",
            mediaunused: root.getAttribute("data-shop-mediaunused") || "",
            mediaprocess: root.getAttribute("data-shop-mediaprocess") || "",
            deletemedia: root.getAttribute("data-shop-deletemedia") || "",
            editmedia: root.getAttribute("data-shop-editmedia") || "",
            uploadmediafolder: root.getAttribute("data-shop-uploadmediafolder") || "",
            assignurl: root.getAttribute("data-shop-assignurl") || ""
        };
    }

    function loadSharedMediaBrowserAssets() {
        return new Promise(function (resolve) {
            if (!dbx.load) {
                resolve(false);
                return;
            }
            dbx.load([
                ["css", "design", "c-cms.css"],
                ["js", "lib", "ajax.js"],
                ["js", "lib", "openWin.js"],
                ["js", "lib", "cms.js"]
            ], function () {
                resolve(!!(dbx.cmsMediaBrowser && typeof dbx.cmsMediaBrowser.open === "function"));
            });
        });
    }

    function ensureCmsMediaBrowser() {
        if (dbx.cmsMediaBrowser && typeof dbx.cmsMediaBrowser.open === "function") {
            return loadSharedMediaBrowserAssets();
        }
        return loadSharedMediaBrowserAssets();
    }

    function ensureAjax() {
        return new Promise(function (resolve) {
            if (dbx.ajax && typeof dbx.ajax.request === "function") {
                resolve(true);
                return;
            }
            if (!dbx.load) {
                resolve(false);
                return;
            }
            dbx.load([["js", "lib", "ajax.js"]], function () {
                resolve(!!(dbx.ajax && typeof dbx.ajax.request === "function"));
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
            body: typeof options.body !== "undefined" ? options.body : null,
            headers: options.headers || {},
            timeout: Number(options.timeout || 20000)
        });
    }

    function scheduleReload() {
        if (reloadTimer) {
            window.clearTimeout(reloadTimer);
        }
        reloadTimer = window.setTimeout(function () {
            if (window.dbx && dbx.utilities && dbx.utilities.leaveGuard) {
                dbx.utilities.leaveGuard.allowOnce();
            }
            window.location.reload();
        }, 900);
    }

    function assignMedia(root, row) {
        const settings = cfg(root);
        if (!settings.assignurl) {
            return Promise.reject(new Error("Shop-Zuordnung ist nicht konfiguriert."));
        }
        const product = root.querySelector("[data-shop-product-select]");
        const group = root.querySelector("[data-shop-group-select]");
        const sorter = root.querySelector("[data-shop-sorter]");
        const primary = root.querySelector("[data-shop-primary]");
        const productId = product ? parseInt(product.value || "0", 10) || 0 : 0;
        const groupId = group ? parseInt(group.value || "0", 10) || 0 : 0;
        if (productId <= 0 && groupId <= 0) {
            window.alert("Bitte zuerst einen Artikel oder eine Artikelgruppe auswaehlen.");
            return Promise.resolve(false);
        }
        const payload = {
            product_id: productId,
            group_id: groupId,
            media_id: parseInt(row.id || row.media_id || "0", 10) || 0,
            title: row.title || row.label || row.file_name || "",
            alt: row.alt || row.title || row.label || "",
            sorter: sorter ? (parseInt(sorter.value || "100", 10) || 100) : 100,
            is_primary: primary && primary.checked ? 1 : 0
        };
        if (payload.media_id <= 0) {
            window.alert("Das ausgewaehlte Medium hat keine gueltige ID.");
            return Promise.resolve(false);
        }
        return fetchJson(settings.assignurl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        }).then(function (data) {
            if (!data || !data.ok) {
                window.alert((data && data.msg) ? data.msg : "Bild konnte nicht zugeordnet werden.");
                return false;
            }
            scheduleReload();
            return true;
        }).catch(function (err) {
            window.alert(err && err.message ? err.message : "Bild konnte nicht zugeordnet werden.");
            return false;
        });
    }

    function openMediaBrowser(root, button) {
        ensureCmsMediaBrowser().then(function (ok) {
            if (!ok || !dbx.cmsMediaBrowser) {
                window.alert("CMS-Medienbrowser konnte nicht geladen werden.");
                return;
            }
            const settings = cfg(root);
            dbx.cmsMediaBrowser.open(root, settings, {
                mode: "pick",
                mediaFolder: button.getAttribute("data-shop-media-folder") || settings.uploadmediafolder || "all",
                afterAssign: function (row) {
                    return assignMedia(root, row);
                }
            });
        });
    }

    function readDragPayload(event) {
        try {
            return JSON.parse(event.dataTransfer.getData("application/json") || "{}");
        } catch (err) {
            return {};
        }
    }

    function moveTreeNode(root, drop, payload) {
        const url = root.getAttribute("data-shop-tree-moveurl") || "";
        const targetGroupId = parseInt(drop.getAttribute("data-shop-tree-drop") || "0", 10) || 0;
        if (!url || !payload || !payload.type) {
            return;
        }
        if (payload.type === "product" && targetGroupId <= 0) {
            window.alert("Artikel koennen nur auf eine Artikelgruppe gezogen werden.");
            return;
        }
        const body = {
            type: payload.type,
            target_group_id: targetGroupId
        };
        if (payload.type === "product") {
            body.product_id = parseInt(payload.id || "0", 10) || 0;
        } else if (payload.type === "group") {
            body.group_id = parseInt(payload.id || "0", 10) || 0;
        }
        ensureAjax().then(function (ok) {
            if (!ok) {
                window.alert("AJAX konnte nicht geladen werden.");
                return;
            }
            return fetchJson(url, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(body)
            }).then(function (data) {
                if (!data || !data.ok) {
                    window.alert((data && data.msg) ? data.msg : "Verschieben nicht moeglich.");
                    return;
                }
                scheduleReload();
            });
        }).catch(function (err) {
            window.alert(err && err.message ? err.message : "Verschieben nicht moeglich.");
        });
    }

    function normalizeSearch(value) {
        return String(value || "")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/\s+/g, " ")
            .trim();
    }

    function setGroupCollapsed(group, collapsed) {
        if (!group) return;
        group.classList.toggle("is-collapsed", !!collapsed);
        const button = group.querySelector(":scope > .dbx-shop-tree-group-head [data-shop-tree-group-toggle]");
        if (button) {
            button.setAttribute("aria-expanded", collapsed ? "false" : "true");
            const icon = button.querySelector("i");
            if (icon) {
                icon.classList.toggle("bi-chevron-right", !!collapsed);
                icon.classList.toggle("bi-chevron-down", !collapsed);
            }
        }
    }

    function clearTreeSearchState(root) {
        root.querySelectorAll(".is-search-match,.is-search-path").forEach(function (node) {
            node.classList.remove("is-search-match", "is-search-path");
        });
        root.querySelectorAll("[data-shop-tree-hidden]").forEach(function (node) {
            node.hidden = false;
            node.removeAttribute("data-shop-tree-hidden");
        });
    }

    function applyTreeSearch(root) {
        const input = root.querySelector("[data-shop-tree-search]");
        const term = normalizeSearch(input ? input.value : "");
        root.classList.toggle("is-searching", term !== "");
        clearTreeSearchState(root);
        if (term === "") {
            return;
        }

        function nodeText(node) {
            return normalizeSearch(node.getAttribute("data-shop-tree-search-text") || node.textContent || "");
        }

        function filterProduct(product) {
            const match = nodeText(product).includes(term);
            product.hidden = !match;
            if (!match) {
                product.setAttribute("data-shop-tree-hidden", "1");
            } else {
                product.classList.add("is-search-match");
            }
            return match;
        }

        function filterProductList(list) {
            let any = false;
            list.querySelectorAll(":scope > .dbx-shop-tree-products > .dbx-shop-tree-product").forEach(function (product) {
                any = filterProduct(product) || any;
            });
            list.hidden = !any;
            if (!any) {
                list.setAttribute("data-shop-tree-hidden", "1");
            }
            return any;
        }

        function filterGroup(group) {
            const ownMatch = nodeText(group).includes(term);
            let childMatch = false;
            const children = group.querySelector(":scope > .dbx-shop-tree-children");
            if (children) {
                Array.prototype.forEach.call(children.children, function (child) {
                    if (child.matches(".dbx-shop-tree-group")) {
                        childMatch = filterGroup(child) || childMatch;
                    } else if (child.matches(".dbx-shop-tree-product-list")) {
                        childMatch = filterProductList(child) || childMatch;
                    }
                });
            }
            const visible = ownMatch || childMatch;
            group.hidden = !visible;
            if (!visible) {
                group.setAttribute("data-shop-tree-hidden", "1");
            } else if (ownMatch) {
                group.classList.add("is-search-match");
                if (children) {
                    children.querySelectorAll("[data-shop-tree-hidden]").forEach(function (node) {
                        node.hidden = false;
                        node.removeAttribute("data-shop-tree-hidden");
                    });
                }
            } else {
                group.classList.add("is-search-path");
            }
            return visible;
        }

        root.querySelectorAll(".dbx-shop-tree-list > .dbx-shop-tree-group").forEach(filterGroup);
        const ungrouped = root.querySelector(".dbx-shop-tree-ungrouped");
        if (ungrouped) {
            let anyUngrouped = false;
            ungrouped.querySelectorAll(".dbx-shop-tree-product").forEach(function (product) {
                anyUngrouped = filterProduct(product) || anyUngrouped;
            });
            ungrouped.hidden = !anyUngrouped;
            if (!anyUngrouped) {
                ungrouped.setAttribute("data-shop-tree-hidden", "1");
            }
        }
    }

    function initProductTree(root) {
        if (root.__dbxShopProductTreeBound) return;
        root.__dbxShopProductTreeBound = true;

        root.querySelectorAll("[data-shop-tree-node]").forEach(function (node) {
            node.setAttribute("draggable", "true");
        });

        root.addEventListener("dragstart", function (event) {
            if (event.target.closest("button,a,input,select,textarea")) return;
            const node = event.target.closest("[data-shop-tree-node]");
            if (!node || !root.contains(node)) return;
            const type = node.getAttribute("data-shop-tree-node");
            const id = type === "product"
                ? node.getAttribute("data-shop-tree-product")
                : node.getAttribute("data-shop-tree-group");
            event.dataTransfer.effectAllowed = "move";
            event.dataTransfer.setData("application/json", JSON.stringify({ type: type, id: id }));
            event.dataTransfer.setData("text/plain", type + ":" + id);
            node.classList.add("is-dragging");
        });

        root.addEventListener("dragend", function () {
            root.querySelectorAll(".is-dragging,.is-drop-target").forEach(function (el) {
                el.classList.remove("is-dragging", "is-drop-target");
            });
        });

        root.addEventListener("dragover", function (event) {
            const drop = event.target.closest("[data-shop-tree-drop]");
            if (!drop || !root.contains(drop)) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = "move";
            root.querySelectorAll(".is-drop-target").forEach(function (el) {
                if (el !== drop) el.classList.remove("is-drop-target");
            });
            drop.classList.add("is-drop-target");
        });

        root.addEventListener("dragleave", function (event) {
            const drop = event.target.closest("[data-shop-tree-drop]");
            if (drop) drop.classList.remove("is-drop-target");
        });

        root.addEventListener("drop", function (event) {
            const drop = event.target.closest("[data-shop-tree-drop]");
            if (!drop || !root.contains(drop)) return;
            event.preventDefault();
            drop.classList.remove("is-drop-target");
            moveTreeNode(root, drop, readDragPayload(event));
        });

        const search = root.querySelector("[data-shop-tree-search]");
        if (search) {
            search.addEventListener("input", function () {
                applyTreeSearch(root);
            });
        }
    }

    function findProductTreeShell(element, fallbackRoot) {
        if (element) {
            const direct = element.closest("[data-shop-tree-shell]");
            if (direct) return direct;
            const panel = element.closest(".dbx-panel");
            if (panel) {
                const shell = panel.querySelector("[data-shop-tree-shell]");
                if (shell) return shell;
            }
        }
        if (fallbackRoot && fallbackRoot.querySelector) {
            return fallbackRoot.querySelector("[data-shop-tree-shell]") || null;
        }
        return null;
    }

    function setProductTreeOpen(shell, open) {
        if (!shell) return;
        const container = shell.closest(".dbx-panel") || shell;
        shell.classList.toggle("is-shop-tree-collapsed", !open);
        shell.classList.toggle("is-shop-tree-open", !!open);
        container.querySelectorAll("[data-shop-tree-toggle]").forEach(function (button) {
            button.setAttribute("aria-expanded", open ? "true" : "false");
            button.setAttribute("aria-label", open ? "Artikelgruppen-Baum ausblenden" : "Artikelgruppen-Baum anzeigen");
            button.setAttribute("title", open ? "Artikelgruppen-Baum ausblenden" : "Artikelgruppen-Baum anzeigen");
            const icon = button.querySelector("i");
            if (icon) {
                icon.classList.toggle("bi-diagram-3", !open);
                icon.classList.toggle("bi-x-lg", !!open);
            }
        });
    }

    function toggleProductTree(shell) {
        setProductTreeOpen(shell, shell.classList.contains("is-shop-tree-collapsed"));
    }

    function init(root) {
        root = root || document;
        root.querySelectorAll("[data-shop-tree-moveurl]").forEach(initProductTree);
        if (root.matches && root.matches("[data-shop-tree-moveurl]")) {
            initProductTree(root);
        }
        root.addEventListener("click", function (event) {
            const stopPropagation = event.target.closest("[data-shop-stop-propagation]");
            if (stopPropagation) {
                event.stopPropagation();
            }
            const toggle = event.target.closest("[data-shop-tree-toggle]");
            if (toggle) {
                event.preventDefault();
                toggleProductTree(findProductTreeShell(toggle, root));
                return;
            }
            const close = event.target.closest("[data-shop-tree-close]");
            if (close) {
                event.preventDefault();
                setProductTreeOpen(findProductTreeShell(close, root), false);
                return;
            }
            const groupToggle = event.target.closest("[data-shop-tree-group-toggle]");
            if (groupToggle) {
                event.preventDefault();
                event.stopPropagation();
                const group = groupToggle.closest("[data-shop-tree-group-wrap]");
                setGroupCollapsed(group, !group.classList.contains("is-collapsed"));
                return;
            }
            const searchClear = event.target.closest(".dbx-clear-btn");
            if (searchClear && searchClear.closest(".dbx-shop-tree-search-wrap")) {
                window.setTimeout(function () {
                    const tree = searchClear.closest("[data-shop-tree-panel]");
                    if (tree) applyTreeSearch(tree);
                }, 0);
                return;
            }
            const button = event.target.closest(".dbx-shop-media-pick");
            if (!button) {
                return;
            }
            event.preventDefault();
            const panel = button.closest(".dbx-shop-media-manager") || root;
            openMediaBrowser(panel, button);
        });
    }

    dbx.feature.register(LIB, {
        scope: "element",
        js: [],
        init
    });
})(window, document);
