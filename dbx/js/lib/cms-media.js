/*!
 * dbxapp cms-media.js
 * Lazy Medienbrowser und Medienbearbeitung.
 * Der Editor-Kern bleibt unabhaengig davon sofort nutzbar; dieses Modul wird
 * erst durch eine Medien- oder Modulbrowser-Aktion aktiviert.
 */
(function (window, document) {
    "use strict";

    const dbx = window.dbx;
    const runtime = dbx && dbx.cmsRuntime;
    if (!runtime || typeof runtime.register !== "function") {
        console.error("[dbx][cms-media] CMS runtime missing");
        return;
    }

    runtime.register("media", function (context) {
        const {
            dbx,
            qs,
            qsa,
            cmsText,
            closestElement,
            status,
            apiUrl,
            fetchJson,
            fetchHtml,
            ensureOpenWin,
            ensureConfirm,
            extractProcessHtml,
            escapeHtml,
            escapeTooltipAttr,
            state,
            cfgUrl,
            browserCfg,
            mediaBrowserFormHtml,
            applyFormSecurity,
            clearCmsLoading,
            markDirty,
            normalizeModPlaceholders,
            modPlaceholderValues,
            insertModPlaceholder,
            mediaRowFromItem,
            currentMediaSlot,
            insertMediaRow,
            applyInlineMediaAssignment,
            mediaRowWithUsage,
            upsertLocalMediaRow,
            setLocalMediaSlot,
            selectedUploadFiles,
            setUploadFiles,
            updateUploadLabel,
            formatBytes,
            isExternalVideoRow,
            canEditImage,
            mediaOriginLabel,
            scheduleMediaLazyLoad,
            setupMediaLazyImages,
            mediaPreviewHtml,
            editorSurface,
            saveEditorSelection,
            cmsConfig,
            syncEditorAfterContextAction,
            getFolderField,
            getField,
            loadMedia,
            collectInlineMediaIdsFromEditor,
            removeInlineMediaFromEditor,
            renderMedia,
            autoRatioValue
        } = context;
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
            if (modal && modal.classList.contains("is-batch-open")) return true;
            if (modal && modal.__dbxCmsSinglePick) return false;
            // Im Editor dienen die Auswahl-Schalter dem Batch-Resize und dem
            // Verschieben mehrerer Medien. Sie muessen daher ebenfalls sammeln,
            // auch wenn die normale Kartenaktion ein einzelnes Medium einfuegt.
            return mode === "editor" || mode === "pick" || (mode === "assign" && modal.__dbxCmsAssignSlot !== "hero");
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

        function mediaSlotLabel(slot) {
            const slotLabels = { inline: "Im Text", hero: "Hero", gallery: "Galerie", shop: "Shop" };
            slot = String(slot || "").trim();
            return slotLabels[slot] || slot || "Verwendung";
        }

        function mediaUsagePages(row) {
            return Array.isArray(row && row.usage_pages) ? row.usage_pages : [];
        }

        function mediaUsageSlots(row) {
            const slots = new Set();
            mediaUsagePages(row).forEach(page => {
                (Array.isArray(page.slots) ? page.slots : []).forEach(slot => {
                    slot = String(slot || "").trim();
                    if (slot) slots.add(slot);
                });
            });
            if (!slots.size && Number(row?.current_usage_id || row?.usage_id || 0) > 0) {
                const slot = String(row?.slot || "").trim();
                if (slot) slots.add(slot);
            }
            return Array.from(slots);
        }

        function currentEditorMediaSlots(root) {
            const slotsById = new Map();
            const add = (id, slot) => {
                id = Number(id || 0);
                slot = String(slot || "").trim();
                if (!id || !slot) return;
                if (!slotsById.has(id)) slotsById.set(id, new Set());
                slotsById.get(id).add(slot);
            };

            collectInlineMediaIdsFromEditor(root).forEach(id => add(id, "inline"));
            const heroId = Number(getField(root, "hero_image_id") || 0);
            if (heroId > 0) add(heroId, "hero");
            (state(root).mediaRows || []).forEach(row => {
                const slot = String(row?.slot || "").trim();
                if (slot === "gallery" || slot === "shop") add(row.id || row.media_id, slot);
            });
            return slotsById;
        }

        function reconcileMediaBrowserUsageWithEditor(root, rows) {
            const contentId = Number(getField(root, "id") || 0);
            if (!contentId || root.classList.contains("is-folder-editing")) return rows;
            const slotsById = currentEditorMediaSlots(root);
            const title = getField(root, "title") || "";
            const folderId = Number(getField(root, "folder") || 0);

            return (rows || []).map(source => {
                const row = Object.assign({}, source || {});
                const mediaId = Number(row.id || row.media_id || 0);
                const pages = mediaUsagePages(row)
                    .filter(page => Number(page.content_id || page.id || 0) !== contentId)
                    .map(page => Object.assign({}, page, {
                        slots: Array.isArray(page.slots) ? page.slots.slice() : []
                    }));
                const currentSlots = Array.from(slotsById.get(mediaId) || []);
                if (currentSlots.length) {
                    pages.push({
                        id: contentId,
                        content_id: contentId,
                        title,
                        folder_id: folderId,
                        folder_title: "",
                        slots: currentSlots
                    });
                    row.slot = currentSlots[0];
                }
                pages.sort((a, b) => Number(a.content_id || a.id || 0) - Number(b.content_id || b.id || 0));
                row.usage_pages = pages;
                row.used_count = pages.reduce((count, page) => count + Math.max(1, (page.slots || []).length), 0);
                return row;
            });
        }

        function mediaUsageLabel(row) {
            row = row || {};
            const pages = mediaUsagePages(row);
            const shown = pages.slice(0, 3).map(page => {
                const id = Number(page.content_id || page.id || 0);
                const slots = (Array.isArray(page.slots) ? page.slots : []).map(mediaSlotLabel).filter(Boolean);
                return "#" + id + (slots.length ? " (" + slots.join(", ") + ")" : "");
            }).join(", ");
            const suffix = pages.length > 3 ? ", ..." : "";
            const count = Number(row.used_count || 0);
            if (shown) return "Verwendet: " + shown + suffix;
            if (count > 0) return count === 1 ? "Verwendet 1x" : "Verwendet " + count + "x";
            return "Nicht verwendet";
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
                <button type="button" class="dbx-cms-media-browser-pickarea" data-cms-media-browser-pick draggable="${isExternalVideoRow(row) ? "false" : "true"}" data-dbx-tooltip="${needsConfirm ? "Medium fuer Auswahl markieren" : "Medium in den Editor einfuegen"}">
                    <span>${mediaPreviewHtml(row)}</span>
                    <strong>${escapeHtml(row.title || row.file_name || "Medium")}</strong>
                    ${canEditImage(row) || needsConfirm ? '<em class="dbx-cms-media-browser-check"><i class="bi bi-check2"></i></em>' : ''}
                </button>
                <div class="dbx-cms-media-browser-actions">
                    <span class="dbx-cms-media-browser-meta">
                        <span class="dbx-cms-media-browser-origin">${escapeHtml(mediaOriginLabel(row))}</span>
                        <span class="dbx-cms-media-browser-usage" tabindex="0" data-dbx-tooltip="${escapeTooltipAttr(mediaUsageTooltipHtml(row))}">${escapeHtml(mediaUsageLabel(row))}</span>
                    </span>
                    ${canEditImage(row) ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-browser-select data-dbx-tooltip="Bild fuer Batch Resize auswaehlen"><i class="bi bi-check2-square"></i></button>' : ''}
                    ${canEditImage(row) ? '<button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-browser-edit data-dbx-tooltip="Bild zuschneiden oder resizen"><i class="bi bi-crop"></i></button>' : ''}
                    <button type="button" class="btn btn-outline-danger btn-sm" data-cms-media-browser-delete data-dbx-tooltip="Mediendatei wirklich loeschen">
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
                    <button type="button" class="dbx-cms-media-explorer-pick dbx-cms-media-browser-pickarea" data-cms-media-browser-pick draggable="${draggable}" data-dbx-tooltip="Medium auswaehlen">
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
                        ${canEditImage(row) ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-browser-select data-dbx-tooltip="Bild fuer Batch Resize auswaehlen"><i class="bi bi-check2-square"></i></button>' : ''}
                        ${canEditImage(row) ? '<button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-browser-edit data-dbx-tooltip="Bild zuschneiden oder resizen"><i class="bi bi-crop"></i></button>' : ''}
                        <button type="button" class="btn btn-outline-danger btn-sm" data-cms-media-browser-delete data-dbx-tooltip="Mediendatei wirklich loeschen"><i class="bi bi-trash"></i></button>
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
                parent.dataset.dbxTooltip = "Vorhandenen Ordner als Basis waehlen";
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
                    <select class="form-select form-select-sm" data-cms-media-browser-resize-preset data-dbx-tooltip="Resize-Groesse">
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
                    <label class="dbx-cms-media-resize-ratio" data-dbx-tooltip="Seitenverhaeltnis beim Resize behalten">
                        <input type="checkbox" data-cms-bulk-resize-ratio checked>
                        <span>Ratio</span>
                    </label>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-cms-action="bulk-resize-media" data-cms-resize-scope="selected">
                        <i class="bi bi-check2-square"></i>
                        <span>Auswahl resizen</span>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-cms-action="bulk-resize-media" data-cms-resize-scope="all">
                        <i class="bi bi-images"></i>
                        <span>Alle angezeigten resizen</span>
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
                    style="--dbx-folder-depth:${depth}" data-dbx-tooltip="${escapeHtml(folder)}">
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
                return `<button type="button" class="btn btn-outline-primary btn-sm${viewSize === size ? " active" : ""}" data-cms-media-tree-size="${size}" data-dbx-tooltip="Ansicht ${labels[size]}">
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
                        <div class="dbx-cms-media-explorer-folder-management">
                            <input type="text" class="form-control form-control-sm" data-cms-media-tree-folder-name placeholder="Unterordner" aria-label="Name des neuen Unterordners">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Ordner verwalten">
                                <button type="button" class="btn btn-outline-primary" data-cms-media-tree-folder-create data-dbx-tooltip="Unterordner im gewählten Ordner anlegen">
                                    <i class="bi bi-folder-plus"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-cms-media-tree-folder-delete data-dbx-tooltip="Gewählten leeren Ordner löschen">
                                    <i class="bi bi-folder-x"></i>
                                </button>
                            </div>
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

        function createMediaFolder(root, cfg, modal, options) {
            options = options || {};
            const url = cfgUrl(cfg, "mediafoldercreate");
            const input = options.input || qs(modal, "[data-cms-folder-name]");
            const parent = qs(modal, "[data-cms-folder-parent]");
            const uploadFolder = qs(modal, "[data-cms-upload-folder]");
            const name = String(input?.value || "").trim();
            const parentVal = String(options.parent || parent?.value || uploadFolder?.value || "").trim();
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
                        modal.__dbxCmsMediaTreeFolder = data.folder;
                        modal.__dbxCmsMediaFolder = data.folder;
                        if (filterFolder && (data.folder === "all" || (modal.__dbxCmsFolders || []).includes(data.folder))) {
                            filterFolder.value = data.folder;
                        }
                        if (uploadFolder && uploadFolders.includes(data.folder)) uploadFolder.value = data.folder;
                    }
                    syncUploadFolderSelect(modal, uploadFolders, data.folder || "");
                    renderMediaFolderTree(modal, modal.__dbxCmsFolders || []);
                });
            }).catch(err => {
                dbx.error("[cms] media folder create failed", err);
                status(root, err && err.message ? err.message : "Medienordner konnte nicht angelegt werden.", "error");
            });
        }

        function deleteSelectedMediaFolder(root, cfg, modal, selectedFolder) {
            const url = cfgUrl(cfg, "mediafolderdelete");
            const select = qs(modal, "[data-cms-upload-folder]");
            const folder = String(selectedFolder || select?.value || "");
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
                    hint: "Nur vollstaendig leere Ordner werden geloescht. Medien, Dateien und Unterordner werden niemals mitgeloescht.",
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
                modal.__dbxCmsMediaTreeFolder = mediaFolderParent(folder);
                modal.__dbxCmsMediaFolder = modal.__dbxCmsMediaTreeFolder;
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
            const sourceRows = Array.isArray(browserModal.__dbxCmsFilteredRows)
                ? browserModal.__dbxCmsFilteredRows
                : (browserModal.__dbxCmsRows || []);
            const rows = sourceRows.filter(canEditImage);
            const selected = browserModal.__dbxCmsSelectedIds || new Set();
            if (!rows.length) {
                list.innerHTML = '<div class="dbx-cms-empty">Keine bearbeitbaren Bilder im aktuellen Medienbrowser-Filter.</div>';
                return;
            }
            list.innerHTML = rows.map(row => `
                <button type="button"
                    class="dbx-cms-media-batch-item${selected.has(Number(row.id || 0)) ? " is-selected" : ""}"
                    data-cms-batch-item
                    data-media-id="${escapeHtml(row.id || "")}" data-dbx-tooltip="${escapeHtml(row.title || row.file_name || "Bild")}">
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

        function mediaProcessHeadMarkup() {
            return '<div class="dbx-cms-media-process-head"><strong><i class="bi bi-tools"></i> Medienwartung</strong><button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-process-close data-dbx-tooltip="Zurueck zum Medienbrowser"><i class="bi bi-arrow-left"></i><span>Zurueck</span></button></div>';
        }

        function mediaMaintenanceFolderOptions(modal, includeAll, preferred) {
            let folders = uploadFolderOptions(modal && modal.__dbxCmsFolders || [])
                .filter(folder => String(folder || "").indexOf("img/") === 0);
            if (!folders.length && modal) {
                folders = Array.from(qs(modal, "[data-cms-upload-folder]")?.options || [])
                    .map(option => String(option.value || ""))
                    .filter(folder => folder.indexOf("img/") === 0);
            }
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
                        <strong><i class="bi bi-arrow-repeat"></i> Medien und Nutzung pruefen</strong>
                        <p>Vergleicht Seiten, Ordner, Hero-, Inline-, Galerie- und Shop-Verwendungen mit der Datenbank. Fehlende Zuordnungen werden ergaenzt, falsche und alte Eintraege entfernt, Ordner je Parent auf 10er-Sortierwerte normalisiert und die Datenbanken anschliessend komprimiert.</p>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-process-start>
                            <i class="bi bi-play-fill"></i>
                            <span>Analyse &amp; Reparatur starten</span>
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
            ensureConfirm().then(ok => {
                if (!ok) {
                    status(root, "Confirm-Lib ist nicht geladen.", "error");
                    return null;
                }
                return dbx.confirm.open({
                    id: "cms-media-maintenance-" + Date.now(),
                    root,
                    title: '<i class="bi bi-tools"></i> Medienwartung',
                    question: "Mediennutzung jetzt vollstaendig analysieren und korrigieren?",
                    hint: "Nachweislich ungueltige und deaktivierte Datenbankeintraege werden dauerhaft entfernt. Ordner werden je Parent in der aktuellen Reihenfolge auf 0010, 0020, 0030 usw. gesetzt. Danach werden Medien- und Content-Datenbank komprimiert.",
                    buttons: "yesno",
                    labelyes: '<i class="bi bi-play-fill"></i> Analyse starten',
                    labelno: '<i class="bi bi-x-lg"></i> Abbrechen',
                    closable: true,
                    backdropclose: false,
                    escclose: true
                });
            }).then(result => {
                if (!result || result.action !== "yes") return;
                runMediaMaintenance(root, cfg, browserModal, batchPanel);
            });
        }

        function runMediaMaintenance(root, cfg, browserModal, batchPanel) {
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

                const treeFolderCreate = closestElement(e.target, "[data-cms-media-tree-folder-create]");
                if (treeFolderCreate && modal.contains(treeFolderCreate)) {
                    e.preventDefault();
                    const tree = closestElement(treeFolderCreate, "[data-cms-media-folder-tree]");
                    createMediaFolder(root, cfg, modal, {
                        parent: modal.__dbxCmsMediaTreeFolder || modal.__dbxCmsMediaFolder || "",
                        input: qs(tree, "[data-cms-media-tree-folder-name]")
                    });
                    return;
                }

                const folderDelete = closestElement(e.target, "[data-cms-folder-delete]");
                if (folderDelete && modal.contains(folderDelete)) {
                    e.preventDefault();
                    deleteSelectedMediaFolder(root, cfg, modal);
                    return;
                }

                const treeFolderDelete = closestElement(e.target, "[data-cms-media-tree-folder-delete]");
                if (treeFolderDelete && modal.contains(treeFolderDelete)) {
                    e.preventDefault();
                    deleteSelectedMediaFolder(
                        root,
                        cfg,
                        modal,
                        modal.__dbxCmsMediaTreeFolder || modal.__dbxCmsMediaFolder || ""
                    );
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
                    deleteMedia(
                        root,
                        cfg,
                        Number(item?.getAttribute("data-media-id") || 0),
                        item?.getAttribute("data-title") || "",
                        browserDelete
                    ).then(deleted => deleted ? openMediaBrowser(modal.__dbxCmsRoot || root, cfg, {
                            mode,
                            slot: modal.__dbxCmsAssignSlot || currentMediaSlot(root),
                            mediaFolder: modal.__dbxCmsMediaFolder || "",
                            formDataExtra: modal.__dbxCmsFormDataExtra || null,
                            afterAssign: modal.__dbxCmsAfterAssign
                        }) : null)
                        .catch(() => null);
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
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-browser-close data-dbx-tooltip="Schliessen"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="dbx-cms-status dbx-cms-media-browser-status" data-cms-status aria-live="polite"></div>
                    <details class="dbx-cms-media-upload-panel" data-cms-media-upload-panel>
                        <summary>
                            <span class="dbx-cms-media-upload-summary-main">
                                <i class="bi bi-chevron-right dbx-cms-toggle-icon"></i>
                                <span>Upload und YouTube</span>
                            </span>
                            <span class="dbx-cms-media-upload-summary-actions">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-batch-open data-dbx-tooltip="Batch Resize">
                                    <i class="bi bi-tools"></i>
                                    <span>Batch</span>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-cms-media-maintenance data-dbx-tooltip="Medienwartung im Medienbrowser starten">
                                    <i class="bi bi-tools"></i>
                                    <span>Wartung</span>
                                </button>
                            </span>
                        </summary>
                        ${uploadFormHtml}
                        <div class="dbx-cms-media-folderbar">
                            <span class="small text-muted dbx-cms-media-folderbar-title">Neuer Unterordner:</span>
                            <select class="form-select form-select-sm" data-cms-folder-parent data-dbx-tooltip="Vorhandenen Ordner als Basis waehlen"></select>
                            <input type="text" class="form-control form-control-sm" data-cms-folder-name placeholder="Ordnername">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-cms-folder-create data-dbx-tooltip="Ordner anlegen">
                                <i class="bi bi-folder-plus"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-cms-folder-delete data-dbx-tooltip="Ausgewaehlten Upload-Ordner loeschen">
                                <i class="bi bi-folder-x"></i>
                            </button>
                            <input type="text" class="form-control form-control-sm" data-cms-folder-rename-name placeholder="Neuer Ordnername">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-folder-rename data-dbx-tooltip="Ordner umbenennen">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                        ${externalVideoFormHtml}
                    </details>
                    <div class="dbx-cms-media-process-panel" data-cms-media-process-panel hidden></div>
                    <div class="dbx-cms-media-browser-tools">
                        <button type="button" class="btn btn-outline-primary btn-sm dbx-cms-media-folder-toggle" data-cms-media-folder-toggle data-dbx-tooltip="Medienordner anzeigen">
                            <i class="bi bi-list-ul"></i>
                        </button>
                        <input type="text" class="form-control form-control-sm" data-cms-media-browser-search placeholder="Medien suchen">
                        <select class="form-select form-select-sm" data-cms-media-browser-folder data-dbx-tooltip="Verzeichnis anzeigen">
                            <option value="all">Alle Verzeichnisse</option>
                        </select>
                        <select class="form-select form-select-sm" data-cms-media-browser-slot data-dbx-tooltip="Bereich anzeigen">
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
            let initialMediaParams = null;
            if (search) search.value = "";
            if (list) list.innerHTML = mediaBrowserSkeletonHtml(24);

            const requestedFolder = String(mediaFolder || folderSelect?.value || "all");
            const mediaParams = mediaBrowserQueryParams(requestedFolder);
            mediaParams.sync = 0;
            mediaParams.limit = 28;
            mediaParams.offset = 0;
            if (formDataExtra && formDataExtra.xmodul) mediaParams.xmodul = formDataExtra.xmodul;
            initialMediaParams = Object.assign({}, mediaParams);

            fetchJson(apiUrl(mediaUrl, mediaParams), { timeout: 30000 })
                .then(data => {
                    if (modal.__dbxCmsMediaRequest !== requestId) return;
                    if (!data || !data.ok) throw new Error("bad response");
                    const rows = reconcileMediaBrowserUsageWithEditor(root, Array.isArray(data.rows) ? data.rows : []);
                    modal.__dbxCmsRows = rows;
                    renderMediaFolderTree(modal, modal.__dbxCmsFolders || []);
                    const render = () => {
                        const term = String(search?.value || "").toLowerCase();
                        const slotFilter = String(slotSelect?.value || "all");
                        const folderFilter = String(folderSelect?.value || "all");
                        const selected = modal.__dbxCmsSelectedIds || new Set();
                        const multi = isMediaBrowserMulti(modal);
                        const needsConfirm = mediaBrowserUsesConfirmBar(modal);
                        const filterRows = row => {
                            const hay = String((row.title || "") + " " + (row.file_name || "") + " " + (row.alt || "")).toLowerCase();
                            const matchTerm = !term || hay.includes(term);
                            const matchSlot = slotFilter === "all" || mediaUsageSlots(row).includes(slotFilter);
                            const matchFolder = folderFilter === "all" || String(row.media_folder || "") === folderFilter;
                            return matchTerm && matchSlot && matchFolder;
                        };
                        const filtered = !term && slotFilter === "all" ? rows : rows.filter(filterRows);
                        modal.__dbxCmsFilteredRows = filtered;
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
                    const folderRefresh = new Promise(resolve => window.setTimeout(resolve, 250))
                        .then(() => refreshMediaFolderControls(root, cfg, modal))
                        .then(() => {
                            if (mediaFolder && folderSelect) folderSelect.value = mediaFolder;
                            const uploadFolders = modal.__dbxCmsUploadFolders || uploadFolderOptions(modal.__dbxCmsFolders || []);
                            syncUploadFolderSelect(modal, uploadFolders, mediaFolder);
                        });
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

                    let hasMore = Number(data.has_more || 0) === 1;
                    let nextOffset = Number(data.next_offset || rows.length || 0);
                    const loadRemaining = () => {
                        if (!hasMore || modal.__dbxCmsMediaRequest !== requestId || !initialMediaParams) return;
                        const params = Object.assign({}, initialMediaParams, {
                            sync: 0,
                            limit: 84,
                            offset: nextOffset
                        });
                        fetchJson(apiUrl(mediaUrl, params), { timeout: 30000 }).then(nextData => {
                            if (modal.__dbxCmsMediaRequest !== requestId || !nextData || !nextData.ok) return;
                            const incoming = reconcileMediaBrowserUsageWithEditor(root, Array.isArray(nextData.rows) ? nextData.rows : []);
                            const known = new Set(rows.map(row => Number(row.id || row.media_id || 0)));
                            incoming.forEach(row => {
                                const id = Number(row.id || row.media_id || 0);
                                if (!id || known.has(id)) return;
                                known.add(id);
                                rows.push(row);
                            });
                            modal.__dbxCmsRows = rows;
                            hasMore = Number(nextData.has_more || 0) === 1;
                            nextOffset = Number(nextData.next_offset || (nextOffset + incoming.length));

                            const hasActiveFilter = String(search?.value || "").trim() !== ""
                                || String(slotSelect?.value || "all") !== "all";
                            if (hasActiveFilter) {
                                if (!hasMore) render();
                            } else if (list && list.__dbxCmsMediaRenderScrollHandler) {
                                list.__dbxCmsMediaRenderScrollHandler();
                            }
                            if (hasMore) window.setTimeout(loadRemaining, 0);
                        }).catch(err => {
                            dbx.warn("[cms] remaining media could not be loaded", err);
                        });
                    };
                    if (hasMore) window.setTimeout(loadRemaining, 0);
                    folderRefresh.then(() => renderMediaFolderTree(modal, modal.__dbxCmsFolders || []));
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
                    <button type="button" class="dbx-cms-mod-browser-pick" data-cms-mod-browser-pick data-dbx-tooltip="In Editor einfuegen">
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
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-mod-browser-back hidden data-dbx-tooltip="Zurueck zur Modulliste">
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

        function deleteMedia(root, cfg, id, mediaTitle, source) {
            const url = cfgUrl(cfg, "deletemedia");
            if (!url || !id) return Promise.resolve();

            return ensureConfirm().then(ok => {
                if (!ok) throw new Error("Confirm-Lib ist nicht geladen.");
                const label = String(mediaTitle || "Medium #" + id);
                return dbx.confirm.open({
                    id: "cms-delete-media-" + id,
                    root,
                    callerEl: source,
                    title: '<i class="bi bi-trash"></i> Mediendatei loeschen',
                    question: "Mediendatei <strong>" + escapeHtml(label) + "</strong> wirklich dauerhaft loeschen?",
                    hint: "Das Loeschen ist nur moeglich, wenn das Medium nicht mehr verwendet wird.",
                    buttons: "yesno",
                    labelyes: '<i class="bi bi-trash"></i> Loeschen',
                    labelno: '<i class="bi bi-x-lg"></i> Abbrechen',
                    closable: true,
                    backdropclose: false,
                    escclose: true
                });
            }).then(result => {
                if (!result || result.action !== "yes") return null;
                return fetchJson(apiUrl(url, { id }), {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ id: Number(id) })
                });
            }).then(data => {
                if (!data) return false;
                if (!data || !data.ok) throw new Error(data && data.msg ? data.msg : "delete failed");
                status(root, "Mediendatei geloescht.", "success");
                return loadMedia(root, cfg).then(() => true);
            }).catch(err => {
                dbx.error("[cms] media delete failed", err);
                status(root, err && err.message ? err.message : "Mediendatei konnte nicht geloescht werden.", "error");
                throw err;
            });
        }

        function mediaEditNeedsRebuild(modal) {
            return !modal || !!qs(modal, ".dbx-cms-media-edit-actions") || !qs(modal, "[data-cms-media-edit-status]");
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
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-cms-media-edit-close data-dbx-tooltip="Schliessen"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="dbx-cms-media-edit-status" data-cms-media-edit-status aria-live="polite" hidden></div>
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

        function setMediaEditLocalStatus(modal, message, type) {
            const local = qs(modal, "[data-cms-media-edit-status]");
            if (!local) return;
            message = String(message || "");
            local.textContent = message;
            local.hidden = !message;
            local.className = "dbx-cms-media-edit-status" + (message && type ? " is-" + type : "");
        }

        function reportMediaEditStatus(root, modal, message, type) {
            status(root, message, type);
            setMediaEditLocalStatus(modal, message, type);
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
                reportMediaEditStatus(root, modal, "Kein Bild ausgewaehlt.", "error");
                return false;
            }
            if (!modal.__dbxCmsCropActive) {
                reportMediaEditStatus(root, modal, "Bitte zuerst einen Bildausschnitt waehlen.", "error");
                return false;
            }
            if (!img || !img.complete || !natural.width || !natural.height || payload.width < 1 || payload.height < 1) {
                reportMediaEditStatus(root, modal, "Bitte einen gueltigen Ausschnitt waehlen.", "error");
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
                reportMediaEditStatus(root, modal, "Vorschau konnte nicht erstellt werden.", "error");
                return false;
            }
            try {
                ctx.drawImage(img, x, y, width, height, 0, 0, width, height);
            } catch (err) {
                dbx.error("[cms] crop preview failed", err);
                reportMediaEditStatus(root, modal, "Vorschau konnte nicht erstellt werden.", "error");
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
            reportMediaEditStatus(root, modal, "Ausschnitt als Vorschau erstellt. Mit Ausschnitt uebernehmen speichern.", "info");
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
                reportMediaEditStatus(root, modal, "Kein Bild ausgewaehlt.", "error");
                return Promise.resolve(false);
            }
            if (action === "resize" && modal.__dbxCmsPendingCrop) {
                reportMediaEditStatus(root, modal, "Bitte den Ausschnitt zuerst uebernehmen oder den Dialog neu oeffnen.", "warning");
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
                reportMediaEditStatus(root, modal, "Bitte zuerst einen Bildausschnitt waehlen.", "error");
                return Promise.resolve(false);
            }
            if (action === "crop" && (payload.width < 1 || payload.height < 1)) {
                reportMediaEditStatus(root, modal, "Bitte einen gueltigen Ausschnitt waehlen.", "error");
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
                if (!data || !data.ok) {
                    setMediaEditLocalStatus(modal, "Bild konnte nicht bearbeitet werden.", "error");
                    return false;
                }
                const updated = Array.isArray(data.rows) && data.rows[0] ? data.rows[0] : null;
                if (!updated) {
                    setMediaEditLocalStatus(modal, "Die aktualisierten Bilddaten fehlen.", "error");
                    return false;
                }
                setMediaEditLocalStatus(modal, successMsg, "success");
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
            setMediaEditLocalStatus(modal, "", "");
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
                    const shown = Array.isArray(modal.__dbxCmsFilteredRows)
                        ? modal.__dbxCmsFilteredRows
                        : mediaBrowserAllRows(modal);
                    return shown.filter(canEditImage);
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

        return Object.freeze({
            confirmPickMediaBrowser,
            toggleMediaBrowserSelection,
            selectedMediaBrowserRows,
            renderMediaMaintenanceHome,
            startMediaMaintenance,
            executeUnusedMediaMaintenance,
            openMediaBrowser,
            openModBrowser,
            openModPlaceholderOptions,
            removeMedia,
            deleteMedia,
            previewMediaCrop,
            commitMediaEditAction,
            openMediaEdit,
            bulkResizeMedia,
            assignMedia,
            saveMediaOrder,
            addExternalVideo,
            uploadMedia
        });
    });

})(window, document);
