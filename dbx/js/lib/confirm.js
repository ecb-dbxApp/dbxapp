/*!
 * =========================================================
 * DBX CONFIRM SYSTEM (confirm.js)
 * =========================================================
 *
 * Zweck
 * -----
 * confirm.js ist die universelle Bestätigungs-Lib von DBX.
 *
 * Die Lib ist bewusst getrennt von:
 * - ajax.js   → Transport / Response
 * - form.js   → Formular-/UI-Logik
 *
 * confirm.js kümmert sich ausschließlich um:
 * - Bestätigungsdialoge
 * - Button-Sets
 * - frei definierbare Beschriftungen
 * - HTML-Inhalte
 * - optionalen Hinweistext
 *
 *
 * Scope-Regel
 * -----------
 * confirm.js arbeitet als DBX-Feature mit:
 *
 *   scope: "element"
 *
 * Das bedeutet:
 * - eine confirm-Instanz gilt nur für ihr Root-Element
 * - plus dessen Children
 * - niemals global als Event-Fänger
 *
 *
 * Architektur
 * -----------
 * confirm.js bietet zwei Nutzungsarten:
 *
 * 1. deklarativ über data-dbx + Klassen + data-confirm-*
 * 2. programmatisch über dbx.confirm.open(...)
 *
 *
 * =========================================================
 * ROOT-KONFIGURATION (data-dbx)
 * =========================================================
 *
 * Beispiel:
 *
 *   <div data-dbx="lib=confirm|class=dbxConfirm|bind=link,button,form">
 *     ...
 *   </div>
 *
 *
 * Unterstützte Root-Parameter
 * ---------------------------
 *
 * lib=confirm
 *   Pflicht. Registriert confirm.js auf diesem Root.
 *
 * class=dbxConfirm
 *   Match-Class. Nur Elemente mit dieser Klasse werden
 *   von dieser confirm-Instanz behandelt.
 *
 *   Erlaubt:
 *   - class=dbxConfirm
 *   - class=dbxDeleteConfirm
 *   - class=*
 *
 * bind=link,button,form
 *   Welche Elementtypen bestätigt werden dürfen.
 *
 *   Erlaubt:
 *   - bind=link
 *   - bind=button
 *   - bind=form
 *   - bind=link,button,form
 *   - bind=* / bind=all
 *
 * title=...
 *   Default-Titel des Dialogs.
 *
 * question=...
 *   Default-Fragetext des Dialogs.
 *
 * hint=...
 *   Optionaler Hinweistext unter der Frage.
 *   Wird nur angezeigt, wenn gesetzt und nicht leer.
 *
 * buttons=yesno|yesnocancel|cancel|cancelonly
 *   Default-Button-Set.
 *
 *   yesno:
 *   - Ja / Nein
 *
 *   yesnocancel:
 *   - Ja / Nein / Abbruch
 *
 *   cancel / cancelonly:
 *   - nur Abbruch
 *
 * titlehtml=0|1
 * questionhtml=0|1
 * hinthtml=0|1
 * labelhtml=0|1
 *   Steuert, ob die jeweiligen Inhalte als HTML interpretiert werden.
 *
 *   Standard:
 *   - titlehtml=1
 *   - questionhtml=1
 *   - hinthtml=1
 *   - labelhtml=1
 *
 * labelyes=...
 * labelno=...
 * labelcancel=...
 *   Frei definierbare Beschriftungen.
 *   HTML ist erlaubt, wenn labelhtml=1.
 *
 * closable=0|1
 *   Darf der Dialog über "X" geschlossen werden?
 *
 * backdropclose=0|1
 *   Darf Klick auf Backdrop den Dialog schließen?
 *
 * escclose=0|1
 *   Darf Escape den Dialog schließen?
 *
 *
 * =========================================================
 * ELEMENT-OVERRIDES (data-confirm-*)
 * =========================================================
 *
 * Ein Link, Button oder ein Form kann alle relevanten
 * Einstellungen lokal überschreiben.
 *
 * Unterstützte data-confirm-* Parameter
 * -------------------------------------
 *
 * data-confirm-title="..."
 * data-confirm-question="..."
 * data-confirm-hint="..."
 * data-confirm-buttons="yesno|yesnocancel|cancel"
 *
 * data-confirm-titlehtml="0|1"
 * data-confirm-questionhtml="0|1"
 * data-confirm-hinthtml="0|1"
 * data-confirm-labelhtml="0|1"
 *
 * data-confirm-labelyes="..."
 * data-confirm-labelno="..."
 * data-confirm-labelcancel="..."
 *
 * data-confirm-closable="0|1"
 * data-confirm-backdropclose="0|1"
 * data-confirm-escclose="0|1"
 *
 *
 * Kurzform
 * --------
 * Zusätzlich wird akzeptiert:
 *
 *   data-confirm="..."
 *
 * Das wird als question behandelt, falls data-confirm-question
 * nicht gesetzt ist.
 *
 *
 * =========================================================
 * PROGRAMMATISCHE NUTZUNG
 * =========================================================
 *
 *   dbx.confirm.open({
 *     id: "delete-15",
 *     root: el,
 *     title: "<i class='bi bi-trash'></i> Löschen",
 *     question: "Datensatz wirklich löschen?",
 *     hint: "<small>Dieser Vorgang kann nicht rückgängig gemacht werden.</small>",
 *     buttons: "yesnocancel",
 *     labelyes: "<i class='bi bi-check-lg'></i> Ja",
 *     labelno: "<i class='bi bi-x-lg'></i> Nein",
 *     labelcancel: "<i class='bi bi-slash-circle'></i> Abbruch"
 *   }).then(result => {
 *     if (result.action === "yes") { ... }
 *   });
 *
 *
 * Rückgabe von open(...)
 * ---------------------
 * Promise → resolve({
 *   id,
 *   action,   // yes | no | cancel | close
 *   source,
 *   root,
 *   dialog
 * })
 *
 *
 * =========================================================
 * BUTTON-SETS
 * =========================================================
 *
 * buttons=yesno
 *   - yes
 *   - no
 *
 * buttons=yesnocancel
 *   - yes
 *   - no
 *   - cancel
 *
 * buttons=cancel / cancelonly
 *   - cancel
 *
 *
 * =========================================================
 * AUTOMATISCHES FORTSETZEN DER AUSLÖSUNG
 * =========================================================
 *
 * Wenn confirm.js deklarativ auf einem:
 * - Link
 * - Button
 * - Form
 * sitzt,
 * dann wird die ursprüngliche Aktion nach erfolgreichem "yes"
 * automatisch fortgesetzt.
 *
 * Reihenfolge:
 * 1. Falls möglich direkt über ajax.js
 * 2. sonst normaler Browser-Default
 *
 * confirm.js selbst macht keine Fachlogik.
 * Es reicht die Auslösung an ajax.js oder den Browser weiter.
 *
 *
 * =========================================================
 * EVENTS
 * =========================================================
 *
 * confirm.js nutzt dbx.event.emit():
 *
 * confirm:open
 * confirm:result
 *
 * Wenn ein id gesetzt ist, funktionieren zusätzlich die
 * id-scoped Events von core.js:
 *
 *   confirm:result:<id>
 *
 * =========================================================
 */

(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][confirm] dbx core missing");
        return;
    }

    const dbx = window.dbx;

    dbx.confirm = dbx.confirm || {};

    const _dialogs = {};
    let _uid = 0;


    /* =========================================================
     * HELPERS
     * ========================================================= */

    function bool(v, def = false) {
        if (v === undefined || v === null || v === "") return def;
        if (v === true || v === 1 || v === "1" || v === "on" || v === "true") return true;
        if (v === false || v === 0 || v === "0" || v === "off" || v === "false") return false;
        return def;
    }

    function str(v, def = "") {
        if (v === undefined || v === null) return def;
        return String(v);
    }

    function defaultLabels() {
        return {
            yes: '<i class="bi bi-check-lg"></i> ' + dbx.translate({
                de: "Ja",
                en: "Yes",
                es: "Sí"
            }),
            no: '<i class="bi bi-x-lg"></i> ' + dbx.translate({
                de: "Nein",
                en: "No",
                es: "No"
            }),
            cancel: '<i class="bi bi-slash-circle"></i> ' + dbx.translate({
                de: "Abbrechen",
                en: "Cancel",
                es: "Cancelar"
            })
        };
    }

    function normalizeBind(v) {
        if (v === undefined || v === null || v === "") return ["link", "button", "form"];

        const raw = String(v).toLowerCase().trim();

        if (raw === "*" || raw === "all") {
            return ["link", "button", "form"];
        }

        return raw.split(",").map(s => s.trim()).filter(Boolean);
    }

    function normalizeClassFilter(v) {
        if (v === undefined || v === null || v === "") return ["dbxConfirm"];

        const raw = String(v).trim();
        if (raw === "*") return ["*"];

        return raw.split(",").map(s => s.trim()).filter(Boolean);
    }

    function normalizeButtons(v) {
        const raw = String(v || "yesno").toLowerCase().trim();

        if (raw === "yesnocancel") return "yesnocancel";
        if (raw === "cancel" || raw === "cancelonly") return "cancel";

        return "yesno";
    }

    function elementType(el) {
        if (!el || !el.tagName) return "";

        const tag = el.tagName.toLowerCase();

        if (tag === "form") return "form";
        if (tag === "a") return "link";
        if (tag === "button") return "button";

        if (tag === "input") {
            const type = str(el.getAttribute("type"), "").toLowerCase();
            if (type === "button" || type === "submit" || type === "image") {
                return "button";
            }
        }

        return "";
    }

    function bindMatches(type, bindList) {
        if (!type) return false;
        if (!Array.isArray(bindList) || !bindList.length) return false;
        return bindList.includes(type);
    }

    function classMatches(el, classList) {
        if (!el) return false;
        if (!Array.isArray(classList) || !classList.length) return false;
        if (classList.includes("*")) return true;

        for (let i = 0; i < classList.length; i++) {
            if (el.classList.contains(classList[i])) return true;
        }

        return false;
    }

    function readAttr(el, name) {
        if (!el || !el.getAttribute) return "";
        const v = el.getAttribute(name);
        return v == null ? "" : String(v).trim();
    }

    function readConfirm(el, key) {
        return readAttr(el, "data-confirm-" + key) || readAttr(el, "data-confirm_" + key);
    }

    function emit(name, data) {
        if (dbx.event && typeof dbx.event.emit === "function") {
            dbx.event.emit(name, data);
        }
    }

    function htmlOrText(el, value, allowHtml) {
        if (!el) return;

        if (allowHtml) {
            el.innerHTML = value || "";
        } else {
            el.textContent = value || "";
        }
    }

    function iconFromTitle(title) {
        const raw = String(title || "").toLowerCase();
        if (raw.includes("trash") || raw.includes("loesch") || raw.includes("lösch")) {
            return "bi-trash";
        }
        if (raw.includes("copy") || raw.includes("kopier")) {
            return "bi-copy";
        }
        if (raw.includes("warn") || raw.includes("achtung")) {
            return "bi-exclamation-triangle";
        }
        if (raw.includes("mail") || raw.includes("e-mail") || raw.includes("email")) {
            return "bi-envelope-check";
        }
        return "bi-question-circle";
    }

    function ensureRoot(el) {
        return el || document.body;
    }

    function getRootConfigs(root) {

        if (!root) return [];

        if (Array.isArray(root._dbxConfirmConfigs)) {
            return root._dbxConfirmConfigs;
        }

        let out = [];

        if (dbx.declare && dbx.declare.schemas && dbx.declare.schemas.confirm) {
            out = dbx.declare.resolve("confirm", root);
        } else {
            const attr = readAttr(root, "data-dbx");
            const list = dbx.parseData(attr).filter(cfg => cfg.lib === "confirm");
            const labels = defaultLabels();

            out = list.map((cfg, index) => {
                return {
                    _index: index,
                    class: normalizeClassFilter(cfg.class),
                    bind: normalizeBind(cfg.bind),
                    title: str(cfg.title, ""),
                    question: str(cfg.question, ""),
                    hint: str(cfg.hint, ""),
                    buttons: normalizeButtons(cfg.buttons),
                    titlehtml: bool(cfg.titlehtml, true),
                    questionhtml: bool(cfg.questionhtml, true),
                    hinthtml: bool(cfg.hinthtml, true),
                    labelhtml: bool(cfg.labelhtml, true),
                    labelyes: str(cfg.labelyes, labels.yes),
                    labelno: str(cfg.labelno, labels.no),
                    labelcancel: str(cfg.labelcancel, labels.cancel),
                    closable: bool(cfg.closable, true),
                    backdropclose: bool(cfg.backdropclose, false),
                    escclose: bool(cfg.escclose, true)
                };
            });

            if (!out.length) {
                out.push({
                    _index: 0,
                    class: normalizeClassFilter("dbxConfirm"),
                    bind: normalizeBind("link,button,form"),
                    title: "",
                    question: "",
                    hint: "",
                    buttons: "yesno",
                    titlehtml: true,
                    questionhtml: true,
                    hinthtml: true,
                    labelhtml: true,
                    labelyes: labels.yes,
                    labelno: labels.no,
                    labelcancel: labels.cancel,
                    closable: true,
                    backdropclose: false,
                    escclose: true
                });
            }
        }

        root._dbxConfirmConfigs = out;

        return out;
    }

    function findMatchingConfig(root, source, type) {

        const configs = getRootConfigs(root);

        for (let i = 0; i < configs.length; i++) {
            const cfg = configs[i];

            if (!bindMatches(type, cfg.bind)) continue;
            if (!classMatches(source, cfg.class)) continue;

            return cfg;
        }

        return null;
    }

    function readOptionsFromElement(source, cfg) {

        const options = {
            id: readConfirm(source, "id") || "",
            title: readConfirm(source, "title") || cfg.title,
            question: readConfirm(source, "question") || readAttr(source, "data-confirm") || cfg.question,
            hint: readConfirm(source, "hint") || cfg.hint,
            buttons: normalizeButtons(readConfirm(source, "buttons") || cfg.buttons),

            titlehtml: bool(readConfirm(source, "titlehtml"), cfg.titlehtml),
            questionhtml: bool(readConfirm(source, "questionhtml"), cfg.questionhtml),
            hinthtml: bool(readConfirm(source, "hinthtml"), cfg.hinthtml),
            labelhtml: bool(readConfirm(source, "labelhtml"), cfg.labelhtml),

            labelyes: readConfirm(source, "labelyes") || cfg.labelyes,
            labelno: readConfirm(source, "labelno") || cfg.labelno,
            labelcancel: readConfirm(source, "labelcancel") || cfg.labelcancel,

            closable: bool(readConfirm(source, "closable"), cfg.closable),
            backdropclose: bool(readConfirm(source, "backdropclose"), cfg.backdropclose),
            escclose: bool(readConfirm(source, "escclose"), cfg.escclose),

            root: null,
            source: source
        };

        return options;
    }

    function buildButtonsMarkup(opts) {

        const actions = [];

        if (opts.buttons === "yesno") {
            actions.push("yes", "no");
        }

        if (opts.buttons === "yesnocancel") {
            actions.push("yes", "no", "cancel");
        }

        if (opts.buttons === "cancel") {
            actions.push("cancel");
        }

        return actions;
    }

    function getMountEl(root, opts) {

        if (opts && opts.mountEl) {
            return opts.mountEl;
        }

        return document.body;
    }

    function numericZIndex(el) {
        if (!el || el.nodeType !== 1) return 0;
        const z = parseInt(window.getComputedStyle(el).zIndex, 10);
        return Number.isFinite(z) ? z : 0;
    }

    function maxAncestorZIndex(el) {
        let max = 0;
        let cur = el && el.nodeType === 1 ? el : null;
        while (cur && cur !== document.documentElement) {
            max = Math.max(max, numericZIndex(cur));
            cur = cur.parentElement;
        }
        return max;
    }

    function autoConfirmZIndex(root, opts) {
        if (opts && opts.zIndex > 0) return opts.zIndex;

        if (dbx.uiLayer && typeof dbx.uiLayer.next === "function") {
            return dbx.uiLayer.next({
                floor: 5000,
                step: 20,
                elements: [
                    opts && opts.source,
                    opts && opts.callerEl,
                    root,
                    opts && opts.mountEl
                ]
            });
        }

        let max = 5000;
        max = Math.max(max, maxAncestorZIndex(opts && opts.source));
        max = Math.max(max, maxAncestorZIndex(opts && opts.callerEl));
        max = Math.max(max, maxAncestorZIndex(root));
        max = Math.max(max, maxAncestorZIndex(opts && opts.mountEl));
        document.querySelectorAll(".dbx-window, .dbx-window-overlay, .dbx-confirm-overlay, .dbx-confirm-dialog").forEach(el => {
            const style = window.getComputedStyle(el);
            if (el.isConnected && style.display !== "none" && style.visibility !== "hidden" && el.getClientRects().length > 0) {
                max = Math.max(max, numericZIndex(el));
            }
        });
        return Math.min(2147483647, max + 20);
    }

    function createDialogElements(root, opts) {

        const mountEl = getMountEl(root, opts);

        const overlay = document.createElement("div");
        overlay.className = "dbx-confirm-overlay";
        overlay.setAttribute("data-dbx-layer", "confirm-overlay");
        overlay.setAttribute("data-dbx-escape-owner", "confirm");
        overlay.style.position = "fixed";
        overlay.style.inset = "0";
        overlay.style.zIndex = String(autoConfirmZIndex(root, opts));
        overlay.style.background = "rgba(0,0,0,0.35)";
        overlay.style.backdropFilter = "blur(2px)";
        overlay.style.display = "flex";
        overlay.style.alignItems = "center";
        overlay.style.justifyContent = "center";
        overlay.style.padding = "1rem";

        const dialog = document.createElement("div");
        dialog.className = "dbx-confirm-dialog card shadow-lg";
        dialog.setAttribute("data-dbx-layer", "confirm-dialog");
        dialog.setAttribute("role", "alertdialog");
        dialog.setAttribute("aria-modal", "true");
        dialog.setAttribute("tabindex", "-1");
        dialog.style.width = "100%";
        dialog.style.maxWidth = "640px";
        dialog.style.border = "1px solid rgba(62, 129, 218, 0.28)";
        dialog.style.borderRadius = "10px";
        dialog.style.overflow = "hidden";
        dialog.style.boxShadow = "0 22px 70px rgba(28, 57, 99, 0.28)";

        const header = document.createElement("div");
        header.className = "card-header dbx-confirm-header d-flex align-items-center justify-content-between gap-3";
        header.style.background = "linear-gradient(180deg, #d8ebff 0%, #a9cffc 100%)";
        header.style.borderBottom = "1px solid rgba(58, 123, 208, 0.34)";
        header.style.color = "#132033";
        header.style.minHeight = "70px";
        header.style.padding = "12px 16px";

        const titleWrap = document.createElement("div");
        titleWrap.className = "dbx-confirm-titlewrap d-flex align-items-center gap-3";
        titleWrap.style.minWidth = "0";

        const titleIcon = document.createElement("span");
        titleIcon.className = "dbx-confirm-title-icon";
        titleIcon.innerHTML = "<i class=\"bi bi-question-circle\"></i>";
        titleIcon.style.alignItems = "center";
        titleIcon.style.background = "rgba(255,255,255,0.58)";
        titleIcon.style.border = "1px solid rgba(35, 103, 194, 0.28)";
        titleIcon.style.borderRadius = "8px";
        titleIcon.style.color = "#0d6efd";
        titleIcon.style.display = "inline-flex";
        titleIcon.style.flex = "0 0 46px";
        titleIcon.style.fontSize = "1.35rem";
        titleIcon.style.height = "46px";
        titleIcon.style.justifyContent = "center";
        titleIcon.style.width = "46px";

        const title = document.createElement("div");
        title.className = "dbx-confirm-title fw-semibold";
        title.style.fontSize = "1.08rem";
        title.style.lineHeight = "1.25";
        title.style.minWidth = "0";

        const ariaId = String(opts.id || "dbx-confirm").replace(/[^a-zA-Z0-9_-]/g, "-");
        title.id = ariaId + "-title";
        dialog.setAttribute("aria-labelledby", title.id);

        const btnClose = document.createElement("button");
        btnClose.type = "button";
        btnClose.className = "btn btn-sm btn-outline-primary";
        btnClose.innerHTML = "<i class=\"bi bi-x-lg\"></i>";
        btnClose.style.background = "rgba(255,255,255,0.52)";
        btnClose.style.borderColor = "rgba(35, 103, 194, 0.34)";
        btnClose.style.flex = "0 0 auto";

        const body = document.createElement("div");
        body.className = "card-body dbx-confirm-body";
        body.style.background = "linear-gradient(180deg, #ffffff 0%, #f6f9fd 100%)";
        body.style.padding = "18px 20px";

        const question = document.createElement("div");
        question.className = "dbx-confirm-question mb-2";
        question.style.color = "#182231";
        question.style.fontSize = "1rem";
        question.style.lineHeight = "1.45";

        const hint = document.createElement("div");
        hint.className = "dbx-confirm-hint text-muted small mb-3";
        hint.style.background = "#f8fafc";
        hint.style.border = "1px solid #e2e8f0";
        hint.style.borderRadius = "8px";
        hint.style.padding = "10px 12px";

        const footer = document.createElement("div");
        footer.className = "card-footer d-flex justify-content-end gap-2 flex-wrap";
        footer.style.background = "#f8fbff";
        footer.style.borderTop = "1px solid #dce8f7";
        footer.style.padding = "12px 16px";

        titleWrap.appendChild(titleIcon);
        titleWrap.appendChild(title);
        header.appendChild(titleWrap);
        header.appendChild(btnClose);

        body.appendChild(question);
        body.appendChild(hint);

        dialog.appendChild(header);
        dialog.appendChild(body);
        dialog.appendChild(footer);

        overlay.appendChild(dialog);
        mountEl.appendChild(overlay);

        return {
            mountEl,
            overlay,
            dialog,
            header,
            titleIcon,
            title,
            btnClose,
            body,
            question,
            hint,
            footer
        };
    }

    function applyDialogState(entry) {

        const opts = entry.options;
        const ui = entry.ui;

        htmlOrText(ui.title, opts.title, opts.titlehtml);
        htmlOrText(ui.question, opts.question, opts.questionhtml);
        ui.titleIcon.innerHTML = "<i class=\"bi " + iconFromTitle(opts.title) + "\"></i>";

        if (opts.hint) {
            ui.hint.style.display = "";
            htmlOrText(ui.hint, opts.hint, opts.hinthtml);
        } else {
            ui.hint.style.display = "none";
            ui.hint.innerHTML = "";
        }

        ui.btnClose.style.display = opts.closable ? "" : "none";

        ui.footer.innerHTML = "";

        const actions = buildButtonsMarkup(opts);

        actions.forEach(action => {

            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "btn";

            if (action === "yes") {
                btn.className += " btn-primary";
                btn.setAttribute("data-confirm-action", "yes");
                htmlOrText(btn, opts.labelyes, opts.labelhtml);
            }

            if (action === "no") {
                btn.className += " btn-outline-secondary";
                btn.setAttribute("data-confirm-action", "no");
                htmlOrText(btn, opts.labelno, opts.labelhtml);
            }

            if (action === "cancel") {
                btn.className += " btn-outline-danger";
                btn.setAttribute("data-confirm-action", "cancel");
                htmlOrText(btn, opts.labelcancel, opts.labelhtml);
            }

            ui.footer.appendChild(btn);
        });
    }

    function closeDialog(entry, result) {

        if (!entry || entry.closed) return;

        entry.closed = true;

        const id = entry.id;
        const ui = entry.ui;

        if (ui && ui.overlay && ui.overlay.parentNode) {
            ui.overlay.parentNode.removeChild(ui.overlay);
        }

        delete _dialogs[id];

        const previousFocus = entry.previousFocus;
        if (previousFocus && previousFocus.isConnected && typeof previousFocus.focus === "function") {
            try {
                previousFocus.focus({ preventScroll: true });
            } catch (_) {
                previousFocus.focus();
            }
        }

        emit("confirm:result", {
            id: id,
            action: result.action,
            source: entry.options.source || null,
            root: entry.options.root || null,
            dialog: null
        });

        entry.resolve(result);
    }

    function topDialogEntry() {
        let top = null;
        let topZ = -1;
        Object.keys(_dialogs).forEach(id => {
            const entry = _dialogs[id];
            const overlay = entry && entry.ui ? entry.ui.overlay : null;
            if (!overlay || !overlay.isConnected) return;
            const zIndex = dbx.uiLayer && typeof dbx.uiLayer.ancestorZIndex === "function"
                ? dbx.uiLayer.ancestorZIndex(overlay)
                : numericZIndex(overlay);
            if (zIndex >= topZ) {
                top = entry;
                topZ = zIndex;
            }
        });
        return top;
    }

    function dialogFocusableElements(dialog) {
        if (!dialog) return [];
        const selector = "button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex='-1'])";
        return Array.from(dialog.querySelectorAll(selector)).filter(el => {
            const style = window.getComputedStyle(el);
            return style.display !== "none" && style.visibility !== "hidden" && el.getClientRects().length > 0;
        });
    }

    function focusDialog(entry) {
        if (!entry || entry.closed || topDialogEntry() !== entry) return;
        const ui = entry.ui;
        const target = ui.footer.querySelector("[data-confirm-action]")
            || (entry.options.closable ? ui.btnClose : null)
            || ui.dialog;
        if (!target || typeof target.focus !== "function") return;
        try {
            target.focus({ preventScroll: true });
        } catch (_) {
            target.focus();
        }
    }

    function continueOriginalAction(entry) {

        const source = entry.options.source;
        if (!source) return;

        const type = elementType(source);
        if (!type) return;

        if (type === "link") {
            source.__dbxConfirmBypass = true;
            source.click();
            return;
        }

        if (type === "button") {

            const btnType = str(source.getAttribute("type"), "submit").toLowerCase();
            const form = source.form || source.closest("form");

            if ((btnType === "submit" || btnType === "image") && form) {
                /*
                 * FormData(form) enthält Submit-Buttons nicht. Zusätzlich
                 * liefert requestSubmit() in älteren WebViews nicht immer ein
                 * SubmitEvent.submitter. Ein kurzlebiges Hidden-Feld erhält
                 * deshalb name/value, während der normale Submit-Pfad weiter
                 * selbst zwischen AJAX und Browsernavigation entscheidet.
                 */
                const submitName = readAttr(source, "name");
                let submitProxy = null;

                if (submitName) {
                    submitProxy = document.createElement("input");
                    submitProxy.type = "hidden";
                    submitProxy.name = submitName;
                    submitProxy.value = readAttr(source, "value");
                    submitProxy.setAttribute("data-dbx-confirm-submitter", "1");
                    form.appendChild(submitProxy);
                }

                source.__dbxConfirmBypass = true;
                form.__dbxConfirmBypass = true;

                try {
                    if (typeof form.requestSubmit === "function") {
                        form.requestSubmit(source);
                    } else {
                        form.submit();
                    }
                } finally {
                    if (submitProxy && submitProxy.parentNode) {
                        submitProxy.parentNode.removeChild(submitProxy);
                    }
                }

                return;
            }

            source.__dbxConfirmBypass = true;
            source.click();
            return;
        }

        if (type === "form") {
            source.__dbxConfirmBypass = true;

            if (typeof source.requestSubmit === "function") {
                source.requestSubmit();
            } else {
                source.submit();
            }
        }
    }

    function openDialog(rawOptions) {
        const labels = defaultLabels();

        const opts = {
            id: str(rawOptions.id, "dbx-confirm-" + (++_uid)),
            root: ensureRoot(rawOptions.root),
            // Nur ein deklarativ erkannter `source` darf nach "yes" seine
            // ursprüngliche Aktion fortsetzen. `callerEl` dient bei
            // programmatischen Dialogen ausschließlich Layering und Fokus.
            source: rawOptions.source || null,
            callerEl: rawOptions.callerEl || rawOptions.caller || rawOptions.source || null,
            mountEl: rawOptions.mountEl || null,
            zIndex: parseInt(rawOptions.zIndex, 10) || 0,

            title: str(rawOptions.title, ""),
            question: str(rawOptions.question, ""),
            hint: str(rawOptions.hint, ""),
            buttons: normalizeButtons(rawOptions.buttons),

            titlehtml: bool(rawOptions.titlehtml, true),
            questionhtml: bool(rawOptions.questionhtml, true),
            hinthtml: bool(rawOptions.hinthtml, true),
            labelhtml: bool(rawOptions.labelhtml, true),

            labelyes: str(rawOptions.labelyes, labels.yes),
            labelno: str(rawOptions.labelno, labels.no),
            labelcancel: str(rawOptions.labelcancel, labels.cancel),

            closable: bool(rawOptions.closable, true),
            backdropclose: bool(rawOptions.backdropclose, false),
            escclose: bool(rawOptions.escclose, true),

            onyes: (typeof rawOptions.onyes === "function") ? rawOptions.onyes : null,
            onno: (typeof rawOptions.onno === "function") ? rawOptions.onno : null,
            oncancel: (typeof rawOptions.oncancel === "function") ? rawOptions.oncancel : null
        };

        if (_dialogs[opts.id]) {
            dbx.confirm.close(opts.id, { action: "close" });
        }

        return new Promise((resolve) => {

            const ui = createDialogElements(opts.root, opts);

            const entry = {
                id: opts.id,
                options: opts,
                ui: ui,
                resolve: resolve,
                closed: false,
                keyHandler: null,
                previousFocus: document.activeElement
            };

            _dialogs[opts.id] = entry;

            applyDialogState(entry);

            emit("confirm:open", {
                id: opts.id,
                source: opts.source || null,
                root: opts.root || null,
                dialog: ui.dialog
            });

            ui.footer.addEventListener("click", function (e) {

                const btn = e.target.closest("[data-confirm-action]");
                if (!btn) return;

                const action = btn.getAttribute("data-confirm-action");

                if (action === "yes" && opts.onyes) {
                    try {
                        opts.onyes(entry);
                    } catch (err) {
                        dbx.error("[dbx.confirm] onyes failed", err);
                    }
                }

                if (action === "no" && opts.onno) {
                    try {
                        opts.onno(entry);
                    } catch (err) {
                        dbx.error("[dbx.confirm] onno failed", err);
                    }
                }

                if (action === "cancel" && opts.oncancel) {
                    try {
                        opts.oncancel(entry);
                    } catch (err) {
                        dbx.error("[dbx.confirm] oncancel failed", err);
                    }
                }

                closeDialog(entry, {
                    id: opts.id,
                    action: action,
                    source: opts.source || null,
                    root: opts.root || null,
                    dialog: null
                });

                if (action === "yes" && opts.source) {
                    continueOriginalAction(entry);
                }
            });

            ui.btnClose.addEventListener("click", function () {

                if (!opts.closable) return;

                closeDialog(entry, {
                    id: opts.id,
                    action: "close",
                    source: opts.source || null,
                    root: opts.root || null,
                    dialog: null
                });
            });

            ui.overlay.addEventListener("click", function (e) {

                if (e.target !== ui.overlay) return;
                if (!opts.backdropclose) return;

                closeDialog(entry, {
                    id: opts.id,
                    action: "close",
                    source: opts.source || null,
                    root: opts.root || null,
                    dialog: null
                });
            });

            entry.keyHandler = function (e) {

                if (topDialogEntry() !== entry) return;

                if (e.key === "Tab") {
                    const focusable = dialogFocusableElements(ui.dialog);
                    if (!focusable.length) {
                        e.preventDefault();
                        ui.dialog.focus();
                        return;
                    }
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                    return;
                }

                if (e.key !== "Escape") return;

                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
                if (!opts.escclose) return;

                closeDialog(entry, {
                    id: opts.id,
                    action: "close",
                    source: opts.source || null,
                    root: opts.root || null,
                    dialog: null
                });
            };

            document.addEventListener("keydown", entry.keyHandler, true);

            const oldResolve = entry.resolve;

            entry.resolve = function (result) {
                document.removeEventListener("keydown", entry.keyHandler, true);
                oldResolve(result);
            };

            focusDialog(entry);
        });
    }


    /* =========================================================
     * PUBLIC API
     * ========================================================= */

    dbx.confirm.open = function (options) {
        return openDialog(options || {});
    };

    dbx.confirm.update = function (id, patch) {

        const entry = _dialogs[id];
        if (!entry) return false;

        const opts = entry.options;
        const data = patch || {};

        if ("title" in data) opts.title = str(data.title, "");
        if ("question" in data) opts.question = str(data.question, "");
        if ("hint" in data) opts.hint = str(data.hint, "");

        applyDialogState(entry);
        return true;
    };

    dbx.confirm.close = function (id, result) {

        const entry = _dialogs[id];
        if (!entry) return false;

        closeDialog(entry, {
            id: id,
            action: (result && result.action) ? result.action : "close",
            source: entry.options.source || null,
            root: entry.options.root || null,
            dialog: null
        });

        return true;
    };

    dbx.confirm.get = function (id) {
        return _dialogs[id] || null;
    };


    /* =========================================================
     * DECLARE SCHEMA (Defaults + data-* Aliase)
     * ========================================================= */

    if (dbx.declare && typeof dbx.declare.registerSchema === "function") {
        const schemaLabels = defaultLabels();

        dbx.declare.registerSchema("confirm", {
            fields: {
                class: {
                    default: "dbxConfirm"
                },
                bind: {
                    default: "link,button,form",
                    aliases: ["data-confirm-bind"]
                },
                title: {
                    default: "",
                    aliases: ["data-confirm-title", "data-title"]
                },
                question: {
                    default: "",
                    aliases: ["data-confirm-question", "data-confirm"]
                },
                hint: {
                    default: "",
                    aliases: ["data-confirm-hint"]
                },
                buttons: {
                    default: "yesno",
                    aliases: ["data-confirm-buttons"]
                },
                titlehtml: {
                    default: "1",
                    aliases: ["data-confirm-titlehtml"]
                },
                questionhtml: {
                    default: "1",
                    aliases: ["data-confirm-questionhtml"]
                },
                hinthtml: {
                    default: "1",
                    aliases: ["data-confirm-hinthtml"]
                },
                labelhtml: {
                    default: "1",
                    aliases: ["data-confirm-labelhtml"]
                },
                labelyes: {
                    default: schemaLabels.yes,
                    aliases: ["data-confirm-labelyes"]
                },
                labelno: {
                    default: schemaLabels.no,
                    aliases: ["data-confirm-labelno"]
                },
                labelcancel: {
                    default: schemaLabels.cancel,
                    aliases: ["data-confirm-labelcancel"]
                },
                closable: {
                    default: "1",
                    aliases: ["data-confirm-closable"]
                },
                backdropclose: {
                    default: "0",
                    aliases: ["data-confirm-backdropclose"]
                },
                escclose: {
                    default: "1",
                    aliases: ["data-confirm-escclose"]
                }
            }
        });

        dbx.declare.transforms.confirm = function (raw) {
            return {
                _index: raw._index,
                class: normalizeClassFilter(raw.class),
                bind: normalizeBind(raw.bind),
                title: str(raw.title, ""),
                question: str(raw.question, ""),
                hint: str(raw.hint, ""),
                buttons: normalizeButtons(raw.buttons),
                titlehtml: bool(raw.titlehtml, true),
                questionhtml: bool(raw.questionhtml, true),
                hinthtml: bool(raw.hinthtml, true),
                labelhtml: bool(raw.labelhtml, true),
                labelyes: str(raw.labelyes, schemaLabels.yes),
                labelno: str(raw.labelno, schemaLabels.no),
                labelcancel: str(raw.labelcancel, schemaLabels.cancel),
                closable: bool(raw.closable, true),
                backdropclose: bool(raw.backdropclose, false),
                escclose: bool(raw.escclose, true)
            };
        };
    }


    /* =========================================================
     * FEATURE
     * ========================================================= */

    dbx.feature.register("confirm", {

        scope: "element",

        priority: "mid",

        init(el, cfg) {

            if (!el) return;

            el.__dbxInitialized = el.__dbxInitialized || {};
            if (el.__dbxInitialized["confirm"]) return;
            el.__dbxInitialized["confirm"] = true;

            el.setAttribute("data-dbx-confirm-root", "1");
            getRootConfigs(el);

            dbx.log("[dbx.confirm] init", {
                rootId: el.id || "",
                configs: el._dbxConfirmConfigs
            });

            el.addEventListener("click", function (e) {

                const source = e.target.closest("a, button, input[type='button'], input[type='submit'], input[type='image']");
                if (!source) return;
                if (!el.contains(source)) return;

                if (source.__dbxConfirmBypass === true) {
                    delete source.__dbxConfirmBypass;
                    return;
                }

                const nearestRoot = source.closest("[data-dbx-confirm-root='1']");
                if (nearestRoot !== el) return;

                const type = elementType(source);
                const cfgMatch = findMatchingConfig(el, source, type);

                if (!cfgMatch) return;

                e.preventDefault();

                const options = readOptionsFromElement(source, cfgMatch);
                options.root = el;

                openDialog(options).catch(err => {
                    dbx.error("[dbx.confirm] open failed", err);
                });
            }, true);

            el.addEventListener("submit", function (e) {

                const form = e.target.closest("form");
                if (!form) return;
                if (!el.contains(form)) return;

                if (form.__dbxConfirmBypass === true) {
                    delete form.__dbxConfirmBypass;
                    return;
                }

                const nearestRoot = form.closest("[data-dbx-confirm-root='1']");
                if (nearestRoot !== el) return;

                const type = elementType(form);
                const cfgMatch = findMatchingConfig(el, form, type);

                if (!cfgMatch) return;

                e.preventDefault();

                const options = readOptionsFromElement(form, cfgMatch);
                options.root = el;

                openDialog(options).catch(err => {
                    dbx.error("[dbx.confirm] open failed", err);
                });
            }, true);
        },

        destroy(el, cfg) {

            if (!el) return;

            if (el.__dbxInitialized && el.__dbxInitialized["confirm"]) {
                delete el.__dbxInitialized["confirm"];
            }

            delete el._dbxConfirmConfigs;
            el.removeAttribute("data-dbx-confirm-root");

            dbx.log("[dbx.confirm] destroy", {
                rootId: el.id || ""
            });
        }

    });

})(window, document);
