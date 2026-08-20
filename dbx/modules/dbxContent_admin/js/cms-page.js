/*!
 * dbxapp cms-page.js
 * Seiten-, Formular- und Aktionssteuerung.
 * Wird zusammen mit dem CMS-Kern geladen, weil diese Funktionen fuer jede
 * administrative CMS-Ansicht benoetigt werden.
 */
(function (window, document) {
    "use strict";

    const dbx = window.dbx;
    const runtime = dbx && dbx.cmsRuntime;
    if (!runtime || typeof runtime.register !== "function") {
        console.error("[dbx][cms-page] CMS runtime missing");
        return;
    }

    runtime.register("page", function (context) {
        const {
            dbx,
            LIB,
            PANEL_UI_ID,
            qs,
            qsa,
            cmsText,
            closestElement,
            status,
            apiUrl,
            fetchJson,
            ensureOpenWin,
            ensureConfirm,
            ensureAjax,
            escapeHtml,
            state,
            cfgUrl,
            confirmPickMediaBrowser,
            isViewMode,
            applyTreePanelState,
            forceCollapseTreePanel,
            toggleRightPanel,
            toggleTreePanel,
            closeTreePanel,
            bindAdminTreeOutsideClose,
            bindViewTreeHover,
            setSelectedFolder,
            setSelectedPage,
            setSelectedType,
            revealTreeSelection,
            openPreview,
            setDirty,
            setSaving,
            updateHeaderActionTooltips,
            cmsLngParams,
            handleLngAfterSave,
            applySaveSuccessStatus,
            openLngProvisionDialog,
            resetLngSync,
            openLngDeleteDialog,
            clearCmsLoading,
            updateCurrentSelectionTitle,
            updateViewPageTitle,
            openContentAdmin,
            markDirty,
            clearDirtyAfterSave,
            suppressDirtyFor,
            mediaRowFromItem,
            currentMediaSlot,
            syncUploadSlot,
            insertMediaRow,
            setLocalMediaSlot,
            inlineVideoTarget,
            inlineVideoEventTarget,
            isInlineVideoResizeHandleEvent,
            openInlineVideoOptions,
            applyInlineVideoOptions,
            closeInlineVideoOptionsWindow,
            playInlineVideoBlock,
            renderHeroPreview,
            applyHeroTemplateChoice,
            renderSeoPreview,
            toggleMediaBrowserSelection,
            selectedMediaBrowserRows,
            setUploadFiles,
            updateUploadLabel,
            renderMediaMaintenanceHome,
            startMediaMaintenance,
            executeUnusedMediaMaintenance,
            openMediaBrowser,
            setEditorHtml,
            getEditorHtml,
            editorSurface,
            saveCurrentCms,
            duplicateCurrentPage,
            deleteCurrentCms,
            bindCmsKeyboardShortcuts,
            clearEditorDropMarks,
            execEditorCommand,
            serializeCmsMarkers,
            bindTreeSearchClear,
            renderTree,
            toggleTreeFolder,
            loadTree,
            ensureTreeLoaded,
            collectInlineMediaIdsFromEditor,
            focusInlineMediaInEditor,
            removeInlineMediaFromEditor,
            renderMedia,
            removeMedia,
            deleteMedia,
            autoRatioValue,
            previewMediaCrop,
            commitMediaEditAction,
            openMediaEdit,
            bulkResizeMedia,
            assignMedia,
            saveMediaOrder,
            addExternalVideo,
            uploadMedia,
            insertMarker
        } = context;
        function loadInitialSelection(root, cfg) {
            const s = state(root);
            if (s.selectionRestored) return Promise.resolve();
            s.selectionRestored = true;

            const requestedPage = Number(cfg && cfg.cid ? cfg.cid : 0) || 0;
            const requestedFolder = Number(cfg && cfg.fid ? cfg.fid : 0) || 0;

            if (isViewMode(cfg)) {
                const pageId = requestedPage || Number(s.selectedPage || 0);
                if (pageId > 0) {
                    setSelectedPage(root, pageId);
                    setSelectedType(root, "page");
                    if (root.getAttribute("data-cms-initial-page-loaded") === "1") {
                        s.page = { id: pageId, title: "" };
                        root.removeAttribute("data-cms-initial-page-loaded");
                        return Promise.resolve();
                    }
                    return loadViewPage(root, cfg, pageId);
                }
                return Promise.resolve();
            }

            if (requestedFolder > 0) {
                const folderId = requestedFolder;
                return ensureTreeLoaded(root, cfg).then(() => {
                    const folder = findNode(root, "folder", folderId);
                    if (!folder) return;
                    setSelectedFolder(root, folderId);
                    setSelectedType(root, "folder");
                    showFolderEditor(root, folder);
                    return loadMedia(root, cfg);
                });
            }

            const pageId = requestedPage || Number(s.selectedPage || 0);
            if (pageId > 0) {
                setSelectedPage(root, pageId);
                setSelectedType(root, "page");
                return loadPage(root, cfg, pageId);
            }

            return Promise.resolve();
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

        function cmsFieldSelector(name, scope) {
            const safeName = String(name || "").replace(/(["\\])/g, "\\$1");
            const base = `[data-cms-field="${safeName}"]`;
            return scope
                ? `${base}[data-cms-field-scope="${scope}"]`
                : `${base}:not([data-cms-field-scope="folder"])`;
        }

        function setFolderField(root, name, value) {
            const el = qs(root, cmsFieldSelector(name, "folder"));
            if (!el) return;
            el.value = value == null ? "" : String(value);
            syncCmsSelect(el);
            if (name === "hero_image_id") renderHeroPreview(root);
            if (name === "seo_image_id") renderSeoPreview(root);
        }

        function getFolderField(root, name) {
            const el = qs(root, cmsFieldSelector(name, "folder"));
            return el ? el.value : "";
        }

        function findNode(root, type, id) {
            const list = state(root).flat || [];
            return list.find(node => node._type === type && Number(node._id) === Number(id)) || null;
        }

        function buildPageFolderOptions(root) {
            const select = qs(root, cmsFieldSelector("folder", "page"));
            if (!select) return;

            const selectedFolder = String(select.value || "0");
            const rootLabel = select.options.length
                ? String(select.options[0].textContent || "Root / erste Ebene")
                : "Root / erste Ebene";
            let html = `<option value="0">${escapeHtml(rootLabel)}</option>`;

            function walk(nodes, depth) {
                (nodes || []).forEach(node => {
                    if (node._type !== "folder") return;
                    const id = Number(node._id || 0);
                    if (id <= 0) return;
                    const prefix = depth > 0 ? Array(depth + 1).join("-- ") : "";
                    html += `<option value="${escapeHtml(id)}">${escapeHtml(prefix + (node._title || "Ordner " + id))}</option>`;
                    walk(node._children || [], depth + 1);
                });
            }

            walk(state(root).tree || [], 0);
            select.innerHTML = html;
            select.value = Array.from(select.options).some(option => option.value === selectedFolder)
                ? selectedFolder
                : "0";
            syncCmsSelect(select);
        }

        function buildParentOptions(root, currentId, selectedParent) {
            const s = state(root);
            const select = qs(root, cmsFieldSelector("parent_id", "folder"));
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

            const first = qs(panel, cmsFieldSelector("name", "folder"));
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
                if (!data || !data.ok) {
                    const err = new Error(data && data.msg ? data.msg : "folder save failed");
                    err.demoReadonly = !!(data && data.demo_readonly);
                    throw err;
                }
                applySaveSuccessStatus(root, data, cmsText(root, "folder_saved", "Ordner gespeichert."));
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
                    handleLngAfterSave(root, cfg, data);
                });
            }).catch(err => {
                if (err && err.demoReadonly) {
                    status(root, err.message, "info");
                    return;
                }
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
            el.dataset.dbxTooltip = selected ? selected.textContent.trim() : "";
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
            const field = select.getAttribute("data-cms-field") || "";
            if (field !== "template") return;

            const wrapper = closestElement(select, "[data-cms-field-wrap]");
            const link = qs(wrapper, "[data-cms-content-template-edit]");
            if (!link) return;

            const template = String(select.value || "").trim();
            const url = contentTemplateEditorUrl(template);
            const enabled = url !== "";

            link.setAttribute("href", enabled ? url : "#");
            link.setAttribute("data-url", enabled ? url : "");
            const editTitle = cmsText(root, "content_template_edit_title", "Content-Template bearbeiten");
            link.setAttribute("data-title", enabled ? editTitle + ": " + template : editTitle);
            link.setAttribute("aria-disabled", enabled ? "false" : "true");
            link.setAttribute("tabindex", enabled ? "0" : "-1");
            link.setAttribute("data-dbx-tooltip", enabled
                ? cmsText(root, "content_template_edit_aria", "Ausgewähltes Content-Template im ACE-Editor bearbeiten") + ": " + template
                : cmsText(root, "content_template_select_first", "Zuerst ein c-* Content-Template auswählen"));
            link.classList.toggle("disabled", !enabled);
        }

        function openContentTemplateEditor(root, link) {
            const wrapper = closestElement(link, "[data-cms-field-wrap]");
            const select = qs(wrapper, 'select[data-cms-field="template"]');
            const template = String(select && select.value || "").trim();
            const url = contentTemplateEditorUrl(template);

            if (!select || !url) {
                if (select) syncContentTemplateEditLink(root, select);
                status(root, cmsText(root, "content_template_select_first", "Zuerst ein c-* Content-Template auswählen"), "error");
                return;
            }

            if (link.__dbxCmsTemplateConfirming) return;
            link.__dbxCmsTemplateConfirming = true;

            ensureConfirm().then(ok => {
                if (!ok) throw new Error("confirm.js nicht geladen.");

                return dbx.confirm.open({
                    id: "dbx-cms-content-template-edit",
                    root: root,
                    title: '<i class="bi bi-pencil-square"></i> ' + escapeHtml(cmsText(root, "content_template_edit_title", "Content-Template bearbeiten")),
                    question: "Content-Template <strong>" + escapeHtml(template) + "</strong>: "
                        + escapeHtml(cmsText(root, "content_template_confirm_question", "wirklich bearbeiten?")),
                    hint: escapeHtml(cmsText(root, "content_template_confirm_hint", "Achtung: Eine Änderung betrifft jede Seite, die dieses Content-Template verwendet.")),
                    buttons: "yesno",
                    labelyes: '<i class="bi bi-pencil-square"></i> ' + escapeHtml(cmsText(root, "content_template_confirm_yes", "Ja, bearbeiten")),
                    labelno: cmsText(root, "cancel_label", "Abbrechen")
                });
            }).then(result => {
                if (!result || result.action !== "yes") return null;

                return Promise.all([ensureAjax(), ensureOpenWin()]).then(ready => {
                    if (!ready[0]) throw new Error("ajax.js nicht geladen.");
                    if (!ready[1]) throw new Error("openWin.js nicht geladen.");

                    return dbx.openWin.open({
                        url: url,
                        title: '<i class="bi bi-pencil-square"></i> '
                            + escapeHtml(cmsText(root, "content_template_edit_title", "Content-Template bearbeiten"))
                            + ': ' + escapeHtml(template),
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
                status(root, err && err.message ? err.message : cmsText(root, "content_template_open_error", "Content-Template-Editor konnte nicht geöffnet werden."), "error");
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
                button.dataset.dbxTooltip = text;
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
            qsa(root, "select[data-cms-field]:not([multiple])").forEach(select => {
                buildCmsSelect(root, select);
                syncContentTemplateEditLink(root, select);
            });
        }

        function cmsFieldValue(value) {
            return value == null ? "" : value;
        }

        function setField(root, name, value) {
            const el = qs(root, cmsFieldSelector(name));
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
            const el = qs(root, cmsFieldSelector(name));
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

                    ["id", "folder", "title", "menu_title", "permalink", "description", "keywords", "template", "activ", "hero_template", "hero_image_id", "hero_margin_top", "hero_height", "hero_variant", "hero_sticky", "hero_scroll_layer", "gallery_template", "gallery_visible_count", "gallery_image_size", "gallery_lightbox_width", "gallery_overflow", "gallery_click_behavior"].forEach(key => {
                        setField(root, key, cmsFieldValue(row[key]));
                    });

                    try {
                        suppressDirtyFor(root, 300);
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
                menu_title: getField(root, "menu_title"),
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
                if (!data || !data.ok) {
                    const err = new Error(data && data.msg ? data.msg : "save failed");
                    err.demoReadonly = !!(data && data.demo_readonly);
                    throw err;
                }
                saveCommitted = true;
                const row = data.row || {};
                if (row && row.id) {
                    s.loading = true;
                    suppressDirtyFor(root, 250);
                    ["id", "folder", "title", "menu_title", "permalink", "description", "keywords", "template", "activ", "hero_template", "hero_image_id", "hero_margin_top", "hero_height", "hero_variant", "hero_sticky", "hero_scroll_layer", "gallery_template", "gallery_visible_count", "gallery_image_size", "gallery_lightbox_width", "gallery_overflow", "gallery_click_behavior"].forEach(key => {
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
                const saveStatus = applySaveSuccessStatus(root, data, cmsText(root, "page_saved", "Seite gespeichert."));
                committedStatus = saveStatus.text;
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
                        handleLngAfterSave(root, cfg, data);
                        return data;
                    });
            }).catch(err => {
                s.loading = false;
                if (saveCommitted) {
                    dbx.warn("[cms] post-save refresh failed", err);
                    status(root, committedStatus + " Ansicht konnte nicht vollstaendig aktualisiert werden.", "success");
                    clearDirtyAfterSave(root);
                } else {
                    if (err && err.demoReadonly) {
                        status(root, err.message, "info");
                        return;
                    }
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

        function handleCmsAction(root, cfg, action, event) {
            if (!root || !action || !root.contains(action)) return false;
            if (event) {
                event.preventDefault();
                event.stopPropagation();
                if (event.stopImmediatePropagation) event.stopImmediatePropagation();
            }

            const name = action.getAttribute("data-cms-action");
            if (name === "toggle-tree-panel") toggleTreePanel(root, cfg);
            if (name === "toggle-right-panel") toggleRightPanel(root, action);
            if (name === "save" || name === "save-settings") saveCurrentCms(root, cfg);
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
                            handleLngAfterSave(root, cfg, data);
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
            if (name === "preview") openPreview(root);
            if (name === "lng-provision") openLngProvisionDialog(root, cfg);
            if (name === "lng-reset-sync") resetLngSync(root, cfg);
            if (name === "assign-media") {
                openMediaBrowser(root, cfg, {
                    mode: "assign",
                    slot: action.getAttribute("data-cms-slot") || currentMediaSlot(root)
                });
            }
            if (name === "clear-hero-media") {
                state(root).heroPreviewRow = null;
                setField(root, "hero_image_id", "0");
                setField(root, "hero_template", "none");
                if (root.classList.contains("is-folder-editing")) {
                    setFolderField(root, "hero_image_id", "0");
                    setFolderField(root, "hero_template", "none");
                }
                markDirty(root);
                renderHeroPreview(root);
                renderMedia(root);
                status(root, "Hero-Bild entfernt. Zum Uebernehmen speichern.", "success");
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
            return true;
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
                    handleCmsAction(root, cfg, action, e);
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

                const inlineFocus = closestElement(e.target, "[data-cms-inline-focus]");
                if (inlineFocus && root.contains(inlineFocus)) {
                    const item = closestElement(inlineFocus, ".dbx-cms-media-item");
                    focusInlineMediaInEditor(root, Number(item?.getAttribute("data-media-id") || 0));
                    return;
                }

                const inlineRemove = closestElement(e.target, "[data-cms-inline-remove]");
                if (inlineRemove && root.contains(inlineRemove)) {
                    const item = closestElement(inlineRemove, ".dbx-cms-media-item");
                    const id = Number(item?.getAttribute("data-media-id") || 0);
                    if (removeInlineMediaFromEditor(root, id)) {
                        renderMedia(root);
                        status(root, cmsText(root, "media_inline_removed", "Medium wurde aus dem Inhalt entfernt."), "success");
                    }
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
                const contentTemplate = closestElement(e.target, '[data-cms-field="template"]');
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
                const heroTemplate = closestElement(e.target, '[data-cms-field="hero_template"]');
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

            qsa(root, '[data-cms-field]:not([data-cms-field-scope="folder"])').forEach(el => {
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

            // Bei kompakten Tree-Zeilen (~32-42px) ergaben reine 28%-Raender
            // nur ca. 10px hohe Trefferzonen fuer "davor/danach einsortieren" -
            // in der Praxis kaum treffbar, weshalb fast jeder Drop stattdessen
            // als "in den Ordner verschieben" (die grosse mittlere Zone) landete.
            // Einsortieren per Drag ist der haeufige Fall, Verschachteln der
            // seltene (und ueber das Ordner-Dropdown im Formular ohnehin
            // praeziser moeglich) - die Zonen sind deshalb bewusst zugunsten
            // des Einsortierens verschoben statt nur symmetrisch vergroessert.
            const folderDropZone = (rect, clientY) => {
                const edge = Math.max(rect.height * 0.4, 14);
                const offset = clientY - rect.top;
                if (offset < edge) return "before";
                if (rect.height - offset < edge) return "after";
                return "into";
            };

            // Dediziertes ~12px hohes Einfuege-Band statt einer duennen
            // Randlinie an der Zeile selbst - erscheint dynamisch genau in
            // der Luecke zwischen zwei Zeilen (Ordner oder Seiten), abhaengig
            // von der aktuellen Zieh-Position. Bewusst NICHT position:fixed -
            // stattdessen position:absolute innerhalb des scrollenden
            // Tree-Containers, aus der Differenz zweier getBoundingClientRect()
            // Aufrufe berechnet. Das ist unabhaengig von jeglicher Coordinate-
            // Space-Eigenheit rund um fixed-positionierte Elemente in
            // verschachtelten/gescrollten Containern.
            const getDropLineContainer = (row) => {
                return (row && row.closest(".dbx-cms-tree")) || root;
            };

            const getDropLine = (container) => {
                let line = container.__dbxCmsTreeDropLine;
                if (!line || !container.contains(line)) {
                    line = document.createElement("div");
                    line.className = "dbx-cms-tree-dropline";
                    if (getComputedStyle(container).position === "static") {
                        container.style.position = "relative";
                    }
                    container.appendChild(line);
                    container.__dbxCmsTreeDropLine = line;
                }
                return line;
            };

            const showDropLine = (row, where) => {
                const container = getDropLineContainer(row);
                const line = getDropLine(container);
                const rect = row.getBoundingClientRect();
                const containerRect = container.getBoundingClientRect();
                const edgeY = where === "before" ? rect.top : rect.bottom;
                line.style.left = (rect.left - containerRect.left) + "px";
                line.style.width = rect.width + "px";
                line.style.top = (edgeY - containerRect.top + container.scrollTop - 6) + "px";
                line.style.display = "block";
            };

            const hideDropLine = () => {
                qsa(root, ".dbx-cms-tree-dropline").forEach(line => { line.style.display = "none"; });
            };

            root.addEventListener("dragstart", e => {
                const media = closestElement(e.target, ".dbx-cms-media-item");
                if (media && root.contains(media)) {
                    e.dataTransfer.effectAllowed = "move";
                    e.dataTransfer.setData("application/x-dbx-cms-media", media.getAttribute("data-media-id") || "");
                    media.classList.add("is-dragging");
                    return;
                }

                const treePress = root.__dbxCmsTreePress;
                const eventRow = closestElement(e.target, ".dbx-cms-tree-row");
                const row = treePress && treePress.row && root.contains(treePress.row) ? treePress.row : eventRow;
                if (!row || !root.contains(row)) return;
                const nodeData = {
                    type: row.getAttribute("data-type"),
                    id: Number(row.getAttribute("data-id") || 0)
                };
                root.__dbxCmsTreeDrag = {
                    row,
                    x: treePress && treePress.row === row ? treePress.x : Number(e.clientX || 0),
                    y: treePress && treePress.row === row ? treePress.y : Number(e.clientY || 0),
                    dragIntent: !!(treePress && treePress.row === row && treePress.dragIntent),
                    dropped: false,
                    data: nodeData
                };
                e.dataTransfer.effectAllowed = "move";
                e.dataTransfer.setData("application/x-dbx-cms-node", JSON.stringify(nodeData));
                row.classList.add("is-dragging");
            });

            root.addEventListener("dragend", e => {
                clearEditorDropMarks(root);
                state(root).dragMarker = null;
                state(root).pointerDragMarker = null;

                const media = closestElement(e.target, ".dbx-cms-media-item");
                if (media) media.classList.remove("is-dragging");
                qsa(root, ".dbx-cms-media-item.is-drop-before,.dbx-cms-media-item.is-drop-after").forEach(el => el.classList.remove("is-drop-before", "is-drop-after"));

                const treeDrag = root.__dbxCmsTreeDrag;
                const row = treeDrag && treeDrag.row ? treeDrag.row : closestElement(e.target, ".dbx-cms-tree-row");
                if (row) row.classList.remove("is-dragging");
                qsa(root, ".dbx-cms-tree-row.is-drop-target").forEach(el => el.classList.remove("is-drop-target"));
                hideDropLine();

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
                if (!row || !root.contains(row)) {
                    hideDropLine();
                    return;
                }
                const type = row.getAttribute("data-type");
                const runtimeData = root.__dbxCmsTreeDrag && root.__dbxCmsTreeDrag.data
                    ? root.__dbxCmsTreeDrag.data
                    : null;
                const hasData = !!runtimeData || Array.from(e.dataTransfer.types || []).includes("application/x-dbx-cms-node");
                if (!hasData) return;
                let data = runtimeData;
                try {
                    if (!data) data = JSON.parse(e.dataTransfer.getData("application/x-dbx-cms-node") || "{}");
                } catch (_) {}
                if (!data || !data.type || !data.id) return;
                if (root.__dbxCmsTreeDrag) root.__dbxCmsTreeDrag.dragIntent = true;
                if (data.type === "folder" && type !== "folder") {
                    hideDropLine();
                    return;
                }

                e.preventDefault();
                e.dataTransfer.dropEffect = "move";
                qsa(root, ".dbx-cms-tree-row.is-drop-target").forEach(el => {
                    if (el !== row) el.classList.remove("is-drop-target");
                });

                const rect = row.getBoundingClientRect();
                if (type === "page") {
                    const before = rect.height ? (e.clientY - rect.top) / rect.height < 0.5 : true;
                    row.classList.remove("is-drop-target");
                    showDropLine(row, before ? "before" : "after");
                } else if (data.type === "folder") {
                    const zone = folderDropZone(rect, e.clientY);
                    if (zone === "into") {
                        hideDropLine();
                        row.classList.add("is-drop-target");
                    } else {
                        row.classList.remove("is-drop-target");
                        showDropLine(row, zone);
                    }
                } else {
                    hideDropLine();
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
                if (row && !row.contains(e.relatedTarget)) {
                    row.classList.remove("is-drop-target");
                    if (!e.relatedTarget || !closestElement(e.relatedTarget, ".dbx-cms-tree-row")) hideDropLine();
                }
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
                row.classList.remove("is-drop-target");
                hideDropLine();

                let data = root.__dbxCmsTreeDrag && root.__dbxCmsTreeDrag.data
                    ? root.__dbxCmsTreeDrag.data
                    : null;
                try {
                    if (!data) data = JSON.parse(e.dataTransfer.getData("application/x-dbx-cms-node") || "{}");
                } catch (_) {}
                if (!data || !data.type || !data.id) return;

                const targetType = row.getAttribute("data-type");
                const targetId = Number(row.getAttribute("data-id") || 0);
                let targetFolder = targetType === "folder" ? targetId : Number(row.getAttribute("data-folder") || 0);
                const position = {};

                if (targetType === "folder" && data.type === "folder") {
                    const rect = row.getBoundingClientRect();
                    const zone = folderDropZone(rect, e.clientY);
                    if (zone !== "into") {
                        targetFolder = Number(row.getAttribute("data-folder") || 0);
                        if (zone === "before") position.before_id = targetId;
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

        return Object.freeze({
            loadInitialSelection,
            setFolderField,
            getFolderField,
            findNode,
            buildPageFolderOptions,
            showFolderEditor,
            hideFolderEditor,
            saveFolder,
            deleteFolder,
            setField,
            getField,
            loadViewPage,
            loadPage,
            loadMedia,
            savePage,
            deletePage,
            handleCmsAction,
            bind
        });
    });

})(window, document);
