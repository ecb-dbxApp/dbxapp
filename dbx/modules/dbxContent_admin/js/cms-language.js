/*!
 * dbxapp cms-language.js
 * Lazy Sprachvarianten-, Synchronisations- und Loeschdialoge.
 * Das Modul wird erst bei einer Sprachaktion oder einem relevanten
 * Synchronisationsergebnis geladen.
 */
(function (window, document) {
    "use strict";

    const dbx = window.dbx;
    const runtime = dbx && dbx.cmsRuntime;
    if (!runtime || typeof runtime.register !== "function") {
        console.error("[dbx][cms-language] CMS runtime missing");
        return;
    }

    runtime.register("language", function (context) {
        const {
            apiUrl,
            cfgUrl,
            clearDirtyAfterSave,
            cmsLngParams,
            dbx,
            ensureConfirm,
            escapeHtml,
            escapeTextareaValue,
            fetchJson,
            fillLngProvisionContentPreviews,
            formatTranslateWarnings,
            getField,
            getFolderField,
            hideFolderEditor,
            isMasterLngCfg,
            loadTree,
            qs,
            qsa,
            renderMedia,
            setEditorHtml,
            setField,
            setSelectedFolder,
            setSelectedPage,
            setSelectedType,
            showTreePanel,
            state,
            status,
            suppressDirtyFor,
            updateCurrentSelectionTitle
        } = context;

        function handleLngAfterSave(root, cfg, data) {
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

        /**
         * Bringt alle Seiten- und Ordner-Loeschpfade in denselben UI-Zustand.
         * Nach erfolgreichem Loeschen ist keine entfernte Entitaet mehr ausgewaehlt
         * und der frisch geladene Content-Baum wird als naechster Arbeitsschritt
         * sichtbar angezeigt.
         */

        function finishEntityDelete(root, cfg, type, id) {
            if (type === "folder") {
                hideFolderEditor(root);
                const s = state(root);
                if (Number(s.selectedFolder || 0) === id) {
                    setSelectedFolder(root, 0);
                    setSelectedType(root, "page");
                }
            } else {
                const s = state(root);
                s.loading = true;
                s.page = null;
                s.mediaRows = [];
                s.heroPreviewRow = null;
                s.heroParentPreviewRow = null;
                s.seoPreviewRow = null;
                setSelectedPage(root, 0);
                setSelectedType(root, "page");
                ["id", "title", "menu_title", "permalink", "description", "keywords"].forEach(key => {
                    setField(root, key, "");
                });
                suppressDirtyFor(root, 300);
                setEditorHtml(root, "");
                renderMedia(root, []);
                updateCurrentSelectionTitle(root, "page", 0, "");
                s.loading = false;
                clearDirtyAfterSave(root);
            }

            return loadTree(root, cfg).then(data => {
                showTreePanel(root);
                return data;
            }, err => {
                // Auch bei einem fehlgeschlagenen Refresh muss der Tree erreichbar
                // sein; die bestehende Fehlerbehandlung meldet den Ladefehler.
                showTreePanel(root);
                throw err;
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
                        return finishEntityDelete(root, cfg, type, id);
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
                        return finishEntityDelete(root, cfg, type, id);
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
                            return finishEntityDelete(root, cfg, type, id);
                        });
                    });
                }
                showLngDeleteModal(root, cfg, type, id, label, data.preview || {});
                return null;
            }).catch(err => {
                status(root, err && err.message ? err.message : "Vorschau fehlgeschlagen.", "error");
            });
        }

        return Object.freeze({
            handleLngAfterSave,
            openLngDeleteDialog,
            openLngProvisionDialog,
            resetLngSync
        });
    });
})(window, document);
