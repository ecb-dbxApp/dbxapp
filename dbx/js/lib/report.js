/*!
 * @file report.js
 * Clientseitige Report-Funktionen fuer dbXapp.
 *
 * Sinn:
 * - reportbezogene Checkbox-Auswahl synchronisieren
 * - sichtbare Zeilen und gespeicherte Auswahl getrennt behandeln
 * - Sortier-/Reihenfolge-Aktionen fuer Reportlisten kapseln
 * - Ajax-Anfragen an dbxReport-Endpunkte standardisieren
 *
 * Beispiel:
 * ```html
 * <div class="dbxReport" data-dbx="lib=report">
 *    <input type="checkbox" data-report-action="rows-select">
 * </div>
 * ```
 */
(function (window, document) {
    "use strict";

    function bootReportLib() {
        if (!window.dbx || !window.dbx.feature) {
            return false;
        }
        if (window.dbx.feature.has("report")) {
            return true;
        }

    const dbx = window.dbx;
    const LIB = "report";
    dbx.report = dbx.report || {};

    function reportText(key) {
        const texts = {
            sortDirty: {
                de: "Reihenfolge geändert. Bitte speichern.",
                en: "Order changed. Please save.",
                es: "El orden ha cambiado. Guárdelo."
            },
            sortUrlMissing: {
                de: "Sortier-URL fehlt.",
                en: "The sort URL is missing.",
                es: "Falta la URL de ordenación."
            },
            sortEmpty: {
                de: "Keine Reihenfolge zum Speichern.",
                en: "There is no order to save.",
                es: "No hay ningún orden que guardar."
            },
            ajaxMissing: {
                de: "ajax.js ist nicht geladen.",
                en: "ajax.js is not loaded.",
                es: "ajax.js no está cargado."
            },
            sortSaving: {
                de: "Reihenfolge wird gespeichert …",
                en: "Saving order …",
                es: "Guardando el orden …"
            },
            sortSaved: {
                de: "Reihenfolge wurde gespeichert.",
                en: "The order was saved.",
                es: "El orden se ha guardado."
            },
            sortSaveError: {
                de: "Reihenfolge konnte nicht gespeichert werden.",
                en: "The order could not be saved.",
                es: "No se ha podido guardar el orden."
            }
        };

        return dbx.translate(texts[key] || {});
    }

    function readAttr(el, name, def = "") {
        if (!el || !el.getAttribute) return def;
        const value = el.getAttribute(name);
        return value == null ? def : String(value).trim();
    }

    function readReportAttr(el, key, def = "") {
        return readAttr(el, "data-report-" + key, def);
    }

    function bool(value, def = false) {
        if (value === undefined || value === null || value === "") return def;
        if (value === true || value === 1 || value === "1" || value === "on" || value === "true") return true;
        if (value === false || value === 0 || value === "0" || value === "off" || value === "false") return false;
        return def;
    }

    function bindFilterAutoSubmit(root) {

        const form = getForm(root);
        if (!form || form.__dbxFilterAutoBound) {
            return;
        }

        form.__dbxFilterAutoBound = true;

        form.addEventListener("change", function (e) {

            const field = e.target && e.target.closest
                ? e.target.closest("[name='dbx_rrows']")
                : null;

            if (!field || !form.contains(field)) {
                return;
            }

            const rpos = form.querySelector("[name='dbx_rpos']");
            if (rpos) {
                rpos.value = "0";
            }

            if (typeof form.requestSubmit === "function") {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
            }
        });
    }

    function emit(name, data) {
        if (dbx.event && typeof dbx.event.emit === "function") {
            dbx.event.emit(name, data || {});
        }
    }

    function reportRoots(root) {
        if (!root || !root.querySelectorAll) return [];
        if (root.matches && (root.matches(".dbxReport") || root.matches(".dbx-report"))) return [root];
        return Array.from(root.querySelectorAll(".dbxReport, .dbx-report"));
    }

    function findRoot(el) {
        return el && el.closest ? el.closest(".dbxReport, .dbx-report, [data-dbx-report-root='1']") : null;
    }

    function ensureTableScrollWrap(root) {
        if (!root || !root.querySelectorAll) return;

        root.querySelectorAll("form > table.table, form > table[data-toggle='table']").forEach(table => {
            const parent = table.parentElement;
            if (!parent) return;
            if (parent.classList.contains("table-responsive") || parent.classList.contains("dbx-report-table-scroll")) {
                return;
            }

            const wrap = document.createElement("div");
            wrap.className = "table-responsive dbx-report-table-scroll";
            parent.insertBefore(wrap, table);
            wrap.appendChild(table);
        });
    }

    function getForm(root) {
        if (!root || !root.querySelector) return null;
        return root.querySelector("form") || (root.closest ? root.closest("form") : null);
    }

    function getActionUrl(root) {
        const form = getForm(root);
        return (form && form.getAttribute("action")) || window.location.href;
    }

    function getUrlParam(url, name) {
        try {
            return new URL(url || "", window.location.href).searchParams.get(name) || "";
        } catch (err) {
            return "";
        }
    }

    function appendValue(body, name, value) {
        body.set(name, value == null ? "" : String(value));
    }

    function getRowCheckboxes(root) {
        if (!root || !root.querySelectorAll) return [];

        return Array.from(root.querySelectorAll(
            ".cb-row-select, .form-check-input-multi, [data-report-action='row-select']"
        )).filter(input => {
            return input && input.type === "checkbox" && !input.disabled;
        });
    }

    function isHeaderCheckbox(input) {
        return input && input.matches && input.matches(".cb-col-select, [data-report-action='rows-select']");
    }

    function isRowCheckbox(input) {
        return input && input.matches && input.matches(".cb-row-select, .form-check-input-multi, [data-report-action='row-select']");
    }

    function isReportCheckbox(input) {
        return isHeaderCheckbox(input) || isRowCheckbox(input);
    }

    function stopReportSelectionEvent(e) {
        if (!e) return;
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === "function") {
            e.stopImmediatePropagation();
        }
    }

    function getHeaderCheckboxes(root) {
        if (!root || !root.querySelectorAll) return [];

        return Array.from(root.querySelectorAll(
            ".cb-col-select, [data-report-action='rows-select']"
        )).filter(input => {
            return input && input.type === "checkbox" && !input.disabled;
        });
    }

    function getRowId(input) {
        return readReportAttr(input, "rid") || readAttr(input, "data-rid") || input.value || "";
    }

    function getVisibleIds(root) {
        return getRowCheckboxes(root)
            .map(getRowId)
            .filter(Boolean);
    }

    function updateHeaderState(root, state) {
        const rows = getRowCheckboxes(root);
        const headers = getHeaderCheckboxes(root);

        if (!headers.length) return;

        let headerState = state || "";

        if (!headerState) {
            const total = rows.length;
            const checked = rows.filter(input => input.checked).length;
            headerState = (total > 0 && checked === total) ? "all" : (checked > 0 ? "partial" : "none");
        }

        headers.forEach(header => {
            header.checked = headerState === "all";
            header.indeterminate = headerState === "partial";
            header.setAttribute("aria-checked", header.indeterminate ? "mixed" : (header.checked ? "true" : "false"));
            header.setAttribute("data-report-header-state", headerState);
        });
    }

    function updateCountTargets(root, response) {
        if (!root || !root.querySelectorAll) return;

        const checkedVisible = getRowCheckboxes(root).filter(input => input.checked).length;
        const hasTotal = response && typeof response.count_selects !== "undefined";

        root.querySelectorAll("[data-report-count='selected-visible']").forEach(el => {
            el.textContent = String(checkedVisible);
        });

        if (hasTotal) {
            root.querySelectorAll("[data-report-count='selected-total']").forEach(el => {
                el.textContent = String(response.count_selects);
            });
        }
    }

    function sync(root, response) {
        updateHeaderState(root);
        updateCountTargets(root, response || null);
    }

    function buildBody(root, params) {
        const body = new URLSearchParams();

        Object.keys(params || {}).forEach(key => appendValue(body, key, params[key]));
        if (!body.has("dbx_sync")) appendValue(body, "dbx_sync", "0");
        if (!body.has("dbx_select_quick")) appendValue(body, "dbx_select_quick", "0");

        return body;
    }

    function request(root, params) {
        const url = getActionUrl(root);
        const body = buildBody(root, params);

        if (!dbx.ajax || typeof dbx.ajax.request !== "function") {
            return Promise.reject(new Error("ajax.js nicht geladen."));
        }

        const send = keepalive => dbx.ajax.request({
            url: url,
            method: "POST",
            mode: "json",
            body: body,
            keepalive: keepalive,
            headers: {
                "X-DBX-UI-State": "report-select"
            }
        });

        return send(true).catch(err => {
            if (err && String(err.message || err).toLowerCase().indexOf("keepalive") !== -1) {
                return send(false);
            }

            dbx.warn("[report] select request failed", err);
            return null;
        }).then(response => {
            if (response && Number(response.ok || 0) === 1) {
                applyResponse(root, response);
            }
            return response;
        });
    }

    function flushSelectionSnapshot(root) {
        return Promise.resolve(null);
    }

    function applyResponse(root, response) {
        const selected = new Set((response.selected_ids_visible || []).map(String));
        const visible = new Set((response.visible_ids || []).map(String));

        if (visible.size) {
            getRowCheckboxes(root).forEach(input => {
                const rid = String(getRowId(input));
                if (visible.has(rid)) {
                    input.checked = selected.has(rid);
                }
            });
        }

        sync(root, response);

        emit("report:select-response", {
            id: root.id || "",
            root: root,
            response: response
        });
    }

    function selectRows(root, checked) {
        const ids = getVisibleIds(root);

        getRowCheckboxes(root).forEach(input => {
            input.checked = checked;
        });
        sync(root);

        emit("report:rows-select", {
            id: root.id || "",
            root: root,
            checked: checked ? 1 : 0,
            visibleIds: ids
        });

        return request(root, {
            dbx_do: "rows_select",
            dbx_select_state: checked ? 1 : 0,
            dbx_select_ids: JSON.stringify(ids),
            dbx_select_visible_ids: JSON.stringify(ids)
        });
    }

    function selectRow(root, input) {
        const rid = getRowId(input);
        const visibleIds = getVisibleIds(root);

        sync(root);

        emit("report:row-select", {
            id: root.id || "",
            root: root,
            input: input,
            rid: rid,
            checked: input.checked ? 1 : 0,
            visibleIds: visibleIds
        });

        return request(root, {
            dbx_do: "row_select",
            dbx_select_id: rid,
            dbx_select_state: input.checked ? 1 : 0,
            dbx_select_visible_ids: JSON.stringify(visibleIds)
        });
    }

    function clearRows(root) {
        getRowCheckboxes(root).forEach(input => {
            input.checked = false;
        });
        sync(root);

        emit("report:clear-selects", {
            id: root.id || "",
            root: root
        });

        return request(root, {
            dbx_do: "clear_selects",
            dbx_select_visible_ids: JSON.stringify(getVisibleIds(root))
        });
    }

    function sortListFrom(el) {
        return el && el.closest ? el.closest("[data-report-sort-list]") : null;
    }

    function sortRows(list) {
        if (!list || !list.children) return [];

        return Array.from(list.children).filter(el => {
            return el && el.matches && el.matches("[data-report-sort-row]");
        });
    }

    function sortRowValue(row) {
        return readAttr(row, "data-sort-value");
    }

    function sortStatus(list, msg, type) {
        const root = findRoot(list) || list;
        const el = root && root.querySelector ? root.querySelector("[data-report-sort-status]") : null;
        if (!el) return;

        el.textContent = msg || "";
        el.classList.toggle("text-danger", type === "error");
        el.classList.toggle("text-success", type === "success");
        el.classList.toggle("text-muted", !type || type === "info");
    }

    function renumberSortRows(list) {
        sortRows(list).forEach((row, idx) => {
            const no = row.querySelector("[data-report-sort-no], .dbx-ddedit-order-no");
            if (no) no.textContent = String(idx + 1);
        });
    }

    function markSortDirty(list) {
        if (!list) return;
        list.classList.add("is-dirty");
        const root = findRoot(list) || list;
        if (root) root.classList.add("is-sort-dirty");
        sortStatus(list, reportText("sortDirty"), "info");
    }

    function clearSortDirty(list) {
        if (!list) return;
        list.classList.remove("is-dirty");
        const root = findRoot(list) || list;
        if (root) root.classList.remove("is-sort-dirty");
    }

    function normalizeSortValues(list) {
        sortRows(list).forEach((row, idx) => {
            row.setAttribute("data-sort-value", String(idx));
        });
    }

    function reloadSortContext(list) {
        if (!bool(readAttr(list, "data-sort-reload"), false)) return;

        const ddRoot = list.closest ? list.closest("[data-ddedit-root='1']") : null;
        const reload = ddRoot && ddRoot.querySelector ? ddRoot.querySelector(".dbx-ddedit-head-actions a.dbxAjax") : null;
        if (reload && typeof reload.click === "function") {
            window.setTimeout(() => reload.click(), 250);
        }
    }

    function moveSortRow(list, from, to, clientY) {
        if (!list || !from || !to || from === to) return false;

        const rect = to.getBoundingClientRect();
        const before = clientY < rect.top + (rect.height / 2);
        list.insertBefore(from, before ? to : to.nextSibling);
        renumberSortRows(list);
        markSortDirty(list);
        return true;
    }

    function saveSortList(list) {
        const url = readAttr(list, "data-sort-url");
        const order = sortRows(list).map(sortRowValue).filter(value => value !== "");

        if (!url) {
            sortStatus(list, reportText("sortUrlMissing"), "error");
            return Promise.resolve(null);
        }

        if (!order.length) {
            sortStatus(list, reportText("sortEmpty"), "error");
            return Promise.resolve(null);
        }

        if (!dbx.ajax || typeof dbx.ajax.request !== "function") {
            sortStatus(list, reportText("ajaxMissing"), "error");
            return Promise.resolve(null);
        }

        const body = new URLSearchParams();
        body.set("order", JSON.stringify(order));

        list.classList.add("is-saving");
        sortStatus(list, reportText("sortSaving"), "info");

        return dbx.ajax.request({
            url: url,
            method: "POST",
            mode: "json",
            body: body,
            headers: {
                "X-DBX-UI-State": "report-sort"
            }
        }).then(response => {
            if (!response || Number(response.ok || 0) !== 1) {
                throw new Error((response && response.msg) || "Reihenfolge konnte nicht gespeichert werden.");
            }

            clearSortDirty(list);
            normalizeSortValues(list);
            sortStatus(list, response.msg || reportText("sortSaved"), "success");
            reloadSortContext(list);
            return response;
        }).catch(err => {
            dbx.warn("[report] sort save failed", err);
            sortStatus(list, (err && err.message) || reportText("sortSaveError"), "error");
            return null;
        }).finally(() => {
            list.classList.remove("is-saving");
        });
    }


    function runFooterAction(root, picker) {
        if (!root || !picker || !picker.value) {
            return;
        }

        const dbxDo = String(picker.value || "").trim();

        if (!dbxDo) {
            return;
        }

        const actionNode = root.querySelector("[data-report-footer-action='" + dbxDo.replace(/'/g, "\\'") + "']");
        const actionLink = actionNode && actionNode.matches && actionNode.matches("a,button,input")
            ? actionNode
            : (actionNode && actionNode.querySelector ? actionNode.querySelector("a,button,input") : null);

        if (!actionLink) {
            return;
        }

        flushSelectionSnapshot(root).finally(() => actionLink.click());
    }

    function bindSort(root) {
        if (!root || root.__dbxReportSortBound) return;
        root.__dbxReportSortBound = true;

        root.addEventListener("dragstart", e => {
            const row = e.target && e.target.closest ? e.target.closest("[data-report-sort-row]") : null;
            if (!row || !root.contains(row)) return;

            const list = sortListFrom(row);
            if (!list || !root.contains(list)) return;

            const handle = row.querySelector("[data-report-sort-handle]");
            if (handle && !e.target.closest("[data-report-sort-handle]")) {
                e.preventDefault();
                return;
            }

            root._dbxReportSortDragRow = row;
            row.classList.add("is-dragging");
            list.classList.add("is-dragging");
            e.dataTransfer.effectAllowed = "move";
            e.dataTransfer.setData("text/plain", sortRowValue(row));
        });

        root.addEventListener("dragend", e => {
            const row = root._dbxReportSortDragRow || (e.target && e.target.closest ? e.target.closest("[data-report-sort-row]") : null);
            const list = row ? sortListFrom(row) : null;
            if (row) row.classList.remove("is-dragging");
            if (list) list.classList.remove("is-dragging");
            root.querySelectorAll("[data-report-sort-row].is-drop-before,[data-report-sort-row].is-drop-after").forEach(el => {
                el.classList.remove("is-drop-before", "is-drop-after");
            });
            root._dbxReportSortDragRow = null;
        });

        root.addEventListener("dragover", e => {
            const row = e.target && e.target.closest ? e.target.closest("[data-report-sort-row]") : null;
            const from = root._dbxReportSortDragRow;
            if (!row || !from || !root.contains(row)) return;

            const list = sortListFrom(row);
            if (!list || sortListFrom(from) !== list) return;

            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            root.querySelectorAll("[data-report-sort-row].is-drop-before,[data-report-sort-row].is-drop-after").forEach(el => {
                if (el !== row) el.classList.remove("is-drop-before", "is-drop-after");
            });

            const rect = row.getBoundingClientRect();
            const before = e.clientY < rect.top + (rect.height / 2);
            row.classList.toggle("is-drop-before", before);
            row.classList.toggle("is-drop-after", !before);
        });

        root.addEventListener("dragleave", e => {
            const row = e.target && e.target.closest ? e.target.closest("[data-report-sort-row]") : null;
            if (row && !row.contains(e.relatedTarget)) {
                row.classList.remove("is-drop-before", "is-drop-after");
            }
        });

        root.addEventListener("drop", e => {
            const to = e.target && e.target.closest ? e.target.closest("[data-report-sort-row]") : null;
            const from = root._dbxReportSortDragRow;
            if (!to || !from || !root.contains(to)) return;

            const list = sortListFrom(to);
            if (!list || sortListFrom(from) !== list) return;

            e.preventDefault();
            to.classList.remove("is-drop-before", "is-drop-after");
            moveSortRow(list, from, to, e.clientY);
        });

        root.addEventListener("click", e => {
            const btn = e.target && e.target.closest ? e.target.closest("[data-report-sort-save]") : null;
            if (!btn || !root.contains(btn)) return;

            const name = readAttr(btn, "data-report-sort-save");
            const list = root.querySelector('[data-report-sort-list="' + name.replace(/"/g, '\\"') + '"]') || root.querySelector("[data-report-sort-list]");
            if (!list) return;

            e.preventDefault();
            saveSortList(list);
        });
    }

    dbx.report.sync = sync;
    dbx.report.getVisibleIds = getVisibleIds;
    dbx.report.request = request;
    dbx.report.saveSortList = saveSortList;
    dbx.report.ensureTableScrollWrap = ensureTableScrollWrap;

    if (!dbx.report.__tableScrollAjaxBound && dbx.event && typeof dbx.event.on === "function") {
        dbx.report.__tableScrollAjaxBound = true;
        dbx.event.on("ajax:after", data => {
            const el = data && data.targetElement;
            if (!el) return;

            const root = findRoot(el);
            if (root) {
                ensureTableScrollWrap(root);
                return;
            }

            reportRoots(el).forEach(ensureTableScrollWrap);
        });
    }

    dbx.feature.register(LIB, {
        scope: "element",
        priority: "last",

        css: [
            ["css", "design", "c-form.css"],
            ["css", "design", "c-report.css"]
        ],

        js: [
            ["js", "lib", "ajax.js"],
            ["js", "lib", "form.js"]
        ],

        init(el, cfg) {
            if (!el) return;

            reportRoots(el).forEach(root => {
                ensureTableScrollWrap(root);

                root.__dbxInitialized = root.__dbxInitialized || {};
                if (root.__dbxInitialized[LIB]) return;
                root.__dbxInitialized[LIB] = true;

                root.setAttribute("data-dbx-report-root", "1");
                root._dbxReportConfig = cfg || {};
                bindSort(root);

                root.addEventListener("click", function (e) {
                    const input = e.target && e.target.closest ? e.target.closest("input[type='checkbox']") : null;
                    if (!input || !root.contains(input) || !isReportCheckbox(input)) return;
                    e.stopPropagation();
                });

                root.addEventListener("change", function (e) {
                    const input = e.target && e.target.closest ? e.target.closest("input[type='checkbox']") : null;
                    if (!input || !root.contains(input)) return;

                    if (isHeaderCheckbox(input)) {
                        stopReportSelectionEvent(e);
                        selectRows(root, input.checked === true);
                        return;
                    }

                    if (isRowCheckbox(input)) {
                        stopReportSelectionEvent(e);
                        selectRow(root, input);
                    }
                }, true);

                root.addEventListener("click", function (e) {
                    const actionEl = e.target && e.target.closest ? e.target.closest("[data-report-action]") : null;

                    if (actionEl && root.contains(actionEl)) {
                        const action = readReportAttr(actionEl, "action");

                        if (action === "clear-selects") {
                            e.preventDefault();
                            clearRows(root);
                        }

                        if (action === "run") {
                            e.preventDefault();
                            const picker = root.querySelector("[data-report-action='picker']");
                            runFooterAction(root, picker);
                        }

                        return;
                    }

                    const formAction = e.target && e.target.closest ? e.target.closest("a.dbxAjaxFormAction") : null;
                    if (!formAction || !root.contains(formAction)) return;

                    const dbxDo = getUrlParam(formAction.getAttribute("href"), "dbx_do");

                    if (dbxDo === "multi_select" || dbxDo === "multi_deselect") {
                        e.preventDefault();
                        selectRows(root, dbxDo === "multi_select");
                    }
                }, true);

                if (bool(cfg && cfg.form, true) && dbx.feature && dbx.feature.has && dbx.feature.has("form")) {
                    try {
                        dbx.feature.init("form", root, Object.assign({}, cfg || {}, { lib: "form" }));
                    } catch (err) {
                        dbx.warn("[report] form init failed", err);
                    }
                }

                bindFilterAutoSubmit(root);

                root._dbxReportBeforeUnload = () => {
                    flushSelectionSnapshot(root);
                };
                window.addEventListener("beforeunload", root._dbxReportBeforeUnload);

                sync(root);

                dbx.log("[report] init", {
                    id: root.id || "",
                    rows: getRowCheckboxes(root).length
                });
            });
        },

        destroy(el) {
            reportRoots(el).forEach(root => {
                flushSelectionSnapshot(root);

                if (root._dbxReportBeforeUnload) {
                    window.removeEventListener("beforeunload", root._dbxReportBeforeUnload);
                    delete root._dbxReportBeforeUnload;
                }

                delete root._dbxReportConfig;

                if (root.__dbxInitialized && root.__dbxInitialized[LIB]) {
                    delete root.__dbxInitialized[LIB];
                }

                root.removeAttribute("data-dbx-report-root");

                dbx.log("[report] destroy", {
                    id: root.id || ""
                });
            });
        }
    });

        return true;
    }

    if (!bootReportLib()) {
        let attempts = 0;
        const wait = window.setInterval(function () {
            if (bootReportLib() || ++attempts > 80) {
                window.clearInterval(wait);
            }
        }, 50);
    }

})(window, document);
