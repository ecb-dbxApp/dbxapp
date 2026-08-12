/**
 * @file core.js
 * Clientseitiger dbXapp-Kernel.
 *
 * Sinn:
 * - stellt den globalen Namespace `window.dbx` bereit
 * - stellt `dbx.feature.register()` fuer selbstregistrierende Feature-Libs bereit
 * - scannt `data-dbx`-Marker im DOM
 * - stellt Events, Logging, Runtime-Footer und Task-Phasen bereit
 *
 * Feature-Beispiel:
 * ```js
 * dbx.feature.register("beispiel", {
 *     selector: ".dbxBeispiel",
 *     scope: "element",
 *     init: function (el, cfg) {
 *         el.textContent = cfg.label || "bereit";
 *     }
 * });
 * ```
 *
 * Template-Beispiel:
 * ```html
 * <div data-dbx="lib=beispiel|label=Hallo"></div>
 * ```
 */

(function (window, document) {
    "use strict";


    /* =====================================================
     * Namespace
     * ===================================================== */
    if (!window.dbx) window.dbx = {};
    const dbx = window.dbx;

    dbx._tasks = [];
    dbx.assetVersion = "";

    /**
     * Liefert einen Framework-Text in der aktiven Dokumentensprache.
     *
     * Fachliche Formular- und Reportmeldungen kommen weiterhin aus der
     * sprachabhängigen FD. Diese kleine Funktion ist ausschließlich für
     * generische JavaScript-Chrome wie Laufzeit, Grid oder Sortierstatus.
     */
    dbx.translate = function (translations, fallbackLanguage) {
        const language = String(document.documentElement.lang || "de")
            .toLowerCase()
            .slice(0, 2);
        const fallback = String(fallbackLanguage || "de")
            .toLowerCase()
            .slice(0, 2);
        const values = translations && typeof translations === "object"
            ? translations
            : {};

        return values[language] ?? values[fallback] ?? "";
    };

    try {
        const currentScript = document.currentScript;
        if (currentScript && currentScript.src) {
            dbx.assetVersion = new URL(currentScript.src, location.href).searchParams.get("v") || "";
        }
    } catch (e) {}


    /* =====================================================
    * WINDOW HELPER (GLOBAL BRIDGE)
    * ===================================================== */
    dbx.win = {

        getCurrentWindowEl() {
            const script = document.currentScript;
            if (script) {
                return script.closest?.('.dbx-window') || null;
            }

            const el = document.activeElement;
            return el?.closest?.('.dbx-window') || null;
        },

        getCurrentWindowId() {
            const win = this.getCurrentWindowEl();
            return win ? win.id : null;
        },

        close(target) {
            if (target === 'all' || target === '*') {
                if (dbx.openWin && dbx.openWin.closeAll) {
                    dbx.openWin.closeAll();
                }
                return;
            }

            const id = this.getCurrentWindowId();

            if (id && dbx.openWin && dbx.openWin.close) {
                dbx.openWin.close(id);
            }
        },

        reload(target) {
            if (target === 'all' || target === '*') {

                if (!dbx.openWin || !dbx.openWin.getAll) return;

                dbx.openWin.getAll().forEach(w => {
                    if (w && w.id) {
                        this._reloadSingle(w.id);
                    }
                });

                return;
            }

            const id = this.getCurrentWindowId();
            if (id) {
                this._reloadSingle(id);
            }
        },

        _reloadSingle(windowId) {

            if (!dbx.openWin || !dbx.openWin.get) return;

            const w = dbx.openWin.get(windowId);
            if (!w || !w.url) return;

            let url = w.url;

            const sep = url.includes("?") ? "&" : "?";
            url = url + sep + "_=" + Date.now();

            if (dbx.openWin && dbx.openWin.open) {
                dbx.openWin.open(w.cfg);
            }
        }

    };

    /* =====================================================
     * UI LAYER SYSTEM
     * ===================================================== */
    const DBX_UI_LAYER_SELECTOR = [
        "[data-dbx-layer]",
        ".dbx-window",
        ".dbx-window-overlay",
        ".dbx-confirm-overlay",
        ".dbx-confirm-dialog",
        ".jodit-container.jodit_fullsize"
    ].join(",");

    function uiLayerElement(value) {
        if (!value) return null;
        if (value.jquery && value[0]) return value[0];
        return value.nodeType === 1 ? value : null;
    }

    function uiLayerElements(value) {
        if (!value) return [];
        if (typeof value === "string") return Array.from(document.querySelectorAll(value));
        if (value.jquery) return value.toArray();
        if (value.nodeType === 1) return [value];
        if (typeof value[Symbol.iterator] === "function") return Array.from(value);
        return [];
    }

    function uiLayerZIndex(value) {
        const el = uiLayerElement(value);
        if (!el) return 0;
        const zIndex = parseInt(window.getComputedStyle(el).zIndex, 10);
        return Number.isFinite(zIndex) ? zIndex : 0;
    }

    function uiLayerAncestorZIndex(value) {
        let el = uiLayerElement(value);
        let max = 0;
        while (el && el !== document.documentElement) {
            max = Math.max(max, uiLayerZIndex(el));
            el = el.parentElement;
        }
        return max;
    }

    function uiLayerIsRendered(value) {
        const el = uiLayerElement(value);
        if (!el || !el.isConnected) return false;
        const style = window.getComputedStyle(el);
        if (style.display === "none" || style.visibility === "hidden" || style.visibility === "collapse") {
            return false;
        }
        return el.getClientRects().length > 0;
    }

    function uiLayerCandidates(options) {
        options = options || {};
        const selector = options.selector === undefined ? DBX_UI_LAYER_SELECTOR : options.selector;
        const candidates = new Set(selector ? uiLayerElements(selector) : []);
        uiLayerElements(options.elements).forEach(el => candidates.add(el));
        uiLayerElements(options.include).forEach(el => candidates.add(el));
        const excluded = new Set(uiLayerElements(options.exclude).map(uiLayerElement).filter(Boolean));
        const renderedOnly = options.renderedOnly !== false;

        return Array.from(candidates)
            .map(uiLayerElement)
            .filter(el => el && !excluded.has(el) && (!renderedOnly || uiLayerIsRendered(el)));
    }

    dbx.uiLayer = {
        selector: DBX_UI_LAYER_SELECTOR,
        element: uiLayerElement,
        isRendered: uiLayerIsRendered,
        zIndex: uiLayerZIndex,
        ancestorZIndex: uiLayerAncestorZIndex,

        max(options) {
            options = options || {};
            let max = Number(options.floor) || 0;
            uiLayerCandidates(options).forEach(el => {
                max = Math.max(max, uiLayerAncestorZIndex(el));
            });
            return max;
        },

        next(options) {
            options = options || {};
            const step = Math.max(1, Number(options.step) || 20);
            const ceiling = Math.max(1, Number(options.ceiling) || 2147483647);
            return Math.min(ceiling, this.max(options) + step);
        },

        top(options) {
            let top = null;
            let topZ = -1;
            uiLayerCandidates(options || {}).forEach(el => {
                const zIndex = uiLayerAncestorZIndex(el);
                if (zIndex >= topZ) {
                    top = el;
                    topZ = zIndex;
                }
            });
            return top;
        }
    };



    /* =====================================================
     * LOG SYSTEM
     * ===================================================== */
    dbx.LOG = false;

    dbx.log = (...a) => { 
        if (dbx.LOG) console.log("[dbx " + dbx._ts() + "]", ...a); 
    };

    dbx.warn = function (...a) {
        console.warn("[dbx " + dbx._ts() + "]", ...a);
        if (a.length >= 3 && typeof a[0] === "string" && typeof a[1] === "string") {
            dbx.diag("warn", a[0], a[1], String(a[2] || ""), a[3] || {});
        }
    };

    dbx.isIgnorableBrowserError = function (msg) {
        const text = String(msg || "");

        return (
            text.includes("ResizeObserver loop completed with undelivered notifications") ||
            text.includes("ResizeObserver loop limit exceeded")
        );
    };

    dbx.error = (...a) => {

    const msg = "[dbx " + dbx._ts() + "] " + a.join(" ");

    // -------------------------------------------------
    // 1. console (immer)
    // -------------------------------------------------
    console.error(msg);

    // -------------------------------------------------
    // 2. intern speichern
    // -------------------------------------------------
    dbx._errors = dbx._errors || [];
    dbx._errors.push({
        time: Date.now(),
        message: msg
    });

    dbx._hasCriticalError = true;

    if (dbx.diag && dbx.diag.queueReport) {
        const diagEntry = {
            time: Date.now(),
            level: "error",
            lib: "core",
            code: "JS_ERROR",
            message: msg,
            ctx: { args: a },
            element: ""
        };
        dbx._diagnostics.push(diagEntry);
        dbx.diag.queueReport(diagEntry);
    }

    // -------------------------------------------------
    // 3. DEV MODE → optional alert
    // -------------------------------------------------
    if (dbx.LOG) {
        // ⚠️ kein Spam – nur erste Meldung als Alert
        if (!dbx.__errorAlertShown) {
            alert(
                "[DBX ERROR]\n\n" +
                msg
            );
            dbx.__errorAlertShown = true;
        }
    }

    // -------------------------------------------------
    // 4. LIVE MODE → visueller Indikator (minimal)
    // -------------------------------------------------
    if (!dbx.LOG) {

        if (!dbx.__errorIndicator) {

            const el = document.createElement("div");

            el.className = "dbx-error-indicator";
            el.style.position = "fixed";
            el.style.top = "10px";
            el.style.right = "10px";
            el.style.width = "12px";
            el.style.height = "12px";
            el.style.background = "#dc3545";
            el.style.borderRadius = "50%";
            el.style.zIndex = "99999";
            el.style.boxShadow = "0 0 6px rgba(0,0,0,0.3)";
            el.dataset.dbxTooltip = dbx.translate({
                de: "DBX-Fehler aufgetreten",
                en: "A DBX error occurred",
                es: "Se ha producido un error de DBX"
            });

            document.body.appendChild(el);

            dbx.__errorIndicator = el;
        }
    }
    };

    dbx._ts = function () {
        const d = new Date();
        const t = d.toISOString().split("T")[1];
        return t.replace("Z", "");
    };


    /* =====================================================
     * DIAGNOSTICS + DECLARE (Defaults, Aliase, Autoload)
     * ===================================================== */
    dbx._diagnostics = dbx._diagnostics || [];

    dbx.diag = function (level, lib, code, message, ctx) {

        const entry = {
            time: Date.now(),
            level: String(level || "info"),
            lib: String(lib || "core"),
            code: String(code || "DIAG"),
            message: String(message || ""),
            ctx: ctx || {},
            element: dbx.diag.elementHint(ctx && ctx.el)
        };

        dbx._diagnostics.push(entry);

        if (entry.level === "info" && !dbx.LOG) {
            return entry;
        }

        const line = dbx.diag.format(entry);

        if (entry.level === "error") {
            console.error(line);
        } else if (entry.level === "warn") {
            console.warn(line);
        } else if (dbx.LOG) {
            console.log(line);
        }

        if (entry.level === "warn" || entry.level === "error") {
            dbx.diag.queueReport(entry);
        }

        return entry;
    };

    dbx.diag.elementHint = function (el) {

        if (!el || el.nodeType !== 1) return "";

        const parts = [];

        if (el.id) {
            parts.push("#" + el.id);
        } else if (el.classList && el.classList.length) {
            parts.push("." + Array.from(el.classList).slice(0, 3).join("."));
        } else {
            parts.push(el.tagName.toLowerCase());
        }

        if (el.getAttribute && el.getAttribute("data-dbx")) {
            const raw = el.getAttribute("data-dbx");
            const m = String(raw).match(/lib=([^|]+)/);
            if (m) parts.push("lib=" + m[1]);
        }

        return parts.join("");
    };

    dbx.diag.format = function (entry) {

        const el = entry.element ? " @ " + entry.element : "";
        const field = entry.ctx && entry.ctx.field ? " field=" + entry.ctx.field : "";
        const src = entry.ctx && entry.ctx.source ? " source=" + entry.ctx.source : "";
        const val = entry.ctx && entry.ctx.value !== undefined ? " value=" + JSON.stringify(entry.ctx.value) : "";

        return "[dbx][" + entry.lib + "][" + entry.code + "] " + entry.message + el + field + src + val;
    };

    dbx.diag.queueReport = function (entry) {

        dbx.diag._reportQueue = dbx.diag._reportQueue || [];
        dbx.diag._reportQueue.push(entry);

        if (dbx.diag._reportTimer) return;

        dbx.diag._reportTimer = setTimeout(function () {
            dbx.diag._reportTimer = null;
            dbx.diag.flushReports();
        }, 1500);
    };

    dbx.diag.reportUrl = function () {
        const root = (dbx.config && dbx.config.rootPath) ? dbx.config.rootPath : "/";
        return root + "?dbx_modul=dbxAdmin&dbx_run1=sysmsg&dbx_run2=client_diag";
    };

    dbx.diag.flushReports = function () {

        const queue = (dbx.diag._reportQueue || []).slice();
        dbx.diag._reportQueue = [];

        if (!queue.length) return;

        const payload = {
            entries: queue.map(function (e) {
                return {
                    level: e.level,
                    lib: e.lib,
                    code: e.code,
                    message: e.message,
                    element: e.element,
                    ctx: e.ctx
                };
            })
        };

        if (!dbx.ajax || typeof dbx.ajax.request !== "function") return;

        dbx.ajax.request({
            url: dbx.diag.reportUrl(),
            method: "POST",
            mode: "json",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
            keepalive: true,
            skipRuntime: true
        }).catch(function (err) {
            dbx.log("diag report failed:", err);
        });
    };

    dbx.declare = dbx.declare || {};

    dbx.declare.schemas = dbx.declare.schemas || {};
    dbx.declare.transforms = dbx.declare.transforms || {};
    dbx.declare.infer = dbx.declare.infer || {};

    dbx.declare.registerSchema = function (lib, schema) {
        if (!lib || !schema) return;
        dbx.declare.schemas[lib] = schema;
    };

    dbx.declare.readAttr = function (el, name) {
        if (!el || !el.getAttribute) return null;
        const v = el.getAttribute(name);
        if (v === null) return null;
        return String(v);
    };

    dbx.declare.readField = function (el, lib, field, spec, ctx) {

        const aliases = [spec.attr].concat(spec.aliases || []).filter(Boolean);
        let source = "missing";
        let value;

        if (ctx && ctx.base && ctx.base[field] !== undefined && String(ctx.base[field]).trim() !== "") {
            return {
                value: String(ctx.base[field]).trim(),
                source: "data-dbx",
                field: field
            };
        }

        for (let i = 0; i < aliases.length; i++) {
            const raw = dbx.declare.readAttr(el, aliases[i]);
            if (raw !== null && String(raw).trim() !== "") {
                source = aliases[i];
                value = String(raw).trim();
                break;
            }
        }

        if (value === undefined && typeof spec.infer === "function") {
            const inferred = spec.infer(el, ctx || {});
            if (inferred !== undefined && inferred !== null && String(inferred).trim() !== "") {
                source = "infer";
                value = String(inferred).trim();
            }
        }

        if (value === undefined) {
            if (spec.default !== undefined) {
                value = spec.default;
                source = "default";
                dbx.diag("info", lib, field.toUpperCase() + "_DEFAULT",
                    "Attribut nicht gesetzt, Standard verwendet",
                    { el: el, field: field, source: source, value: value });
            } else if (spec.required) {
                dbx.diag("warn", lib, field.toUpperCase() + "_MISSING",
                    "Pflicht-Attribut fehlt",
                    { el: el, field: field, source: source });
                value = "";
            } else {
                value = "";
            }
        }

        if (typeof spec.coerce === "function") {
            value = spec.coerce(value, el, ctx || {});
        }

        return { value: value, source: source, field: field };
    };

    dbx.declare.infer.ajaxTarget = function (root) {

        if (!root || root.nodeType !== 1) return "";

        const id = String(root.id || "").trim();
        if (/^dbx_target_\d+$/i.test(id)) return id;

        const form = root.tagName === "FORM"
            ? root
            : root.querySelector("form.dbxAjax, form[id^='dbx_form_']");

        if (form && form.id) {
            const m = form.id.match(/dbx_form_(\d+)/i);
            if (m) return "dbx_target_" + m[1];
        }

        const target = root.querySelector("[id^='dbx_target_']");
        if (target && target.id) return target.id;

        return "";
    };

    dbx.declare.infer.confirmRoot = function (el) {

        if (!el || el.nodeType !== 1) return null;

        const form = el.closest("form.dbxAjax");
        if (form) return form;

        const panel = el.closest(".dbx-confirm-root, [data-dbx*='lib=confirm']");
        if (panel) return panel;

        return el.parentElement || document.body;
    };

    dbx.declare.buildFromSchema = function (lib, root, baseCfg, index) {

        const schema = dbx.declare.schemas[lib];
        if (!schema || !schema.fields) return null;

        const ctx = { root: root, index: index, base: baseCfg || {} };
        const raw = { _index: index, lib: lib };

        Object.keys(schema.fields).forEach(function (field) {
            const spec = schema.fields[field];
            const res = dbx.declare.readField(root, lib, field, spec, ctx);
            raw[field] = res.value;
            raw["_" + field + "Source"] = res.source;
        });

        if (typeof dbx.declare.transforms[lib] === "function") {
            return dbx.declare.transforms[lib](raw, root);
        }

        return raw;
    };

    dbx.declare.resolve = function (lib, root) {

        if (!root) return [];

        const cacheKey = "_dbx" + lib.charAt(0).toUpperCase() + lib.slice(1) + "Configs";

        if (Array.isArray(root[cacheKey])) {
            return root[cacheKey];
        }

        const attr = dbx.declare.readAttr(root, "data-dbx") || "";
        const parsed = dbx.parseData(attr).filter(function (cfg) {
            return cfg.lib === lib;
        });

        let out = [];

        if (parsed.length) {
            out = parsed.map(function (cfg, index) {
                return dbx.declare.buildFromSchema(lib, root, cfg, index) || cfg;
            });
        } else if (dbx.declare.schemas[lib]) {
            out = [dbx.declare.buildFromSchema(lib, root, {}, 0)];
        }

        if (!out.length && dbx.declare.schemas[lib]) {
            out = [dbx.declare.buildFromSchema(lib, root, {}, 0)];
        }

        root[cacheKey] = out;

        return out;
    };

    dbx.declare.hasLibInDataDbx = function (el, lib) {
        const attr = dbx.declare.readAttr(el, "data-dbx") || "";
        return dbx.parseData(attr).some(function (cfg) {
            return cfg.lib === lib;
        });
    };

    dbx.declare.markAutoload = function (el, lib) {
        el.__dbxAutoload = el.__dbxAutoload || {};
        if (el.__dbxAutoload[lib]) return false;
        el.__dbxAutoload[lib] = true;
        return true;
    };

    dbx.ensureElementScopeStores = function (el) {

        if (!el || el.nodeType !== 1) return;

        el.__dbxInitialized = el.__dbxInitialized || {};
        el.__dbxState = el.__dbxState || {};
        el.__dbxError = el.__dbxError || {};
    };

    dbx.declare.queueFeature = function (lib, el, scopeType, cfg) {

        if (!dbx.declare.markAutoload(el, lib)) return;

        dbx.ensureElementScopeStores(el);

        const f = dbx.feature._features[lib];
        let scope = scopeType || "element";

        if (f && f.scope) {
            scope = f.scope;
        }

        const id = (cfg && cfg.id) ? cfg.id : (el.id || "undef");
        const keyScoped = scope + ":" + lib + ":" + id;

        dbx._tasks.push({
            lib: lib,
            el: el,
            cfg: Object.assign({ lib: lib, id: id }, cfg || {}),
            _key: keyScoped,
            _scopeType: scope
        });
    };

    dbx.declare.scanAutoload = function (ctx) {

        const root = ctx || document;

        root.querySelectorAll("form.dbxAjax").forEach(function (form) {
            if (!dbx.declare.hasLibInDataDbx(form, "ajax")) {
                dbx.declare.queueFeature("ajax", form, "element", { id: form.id || "undef" });
            }
            if (!dbx.declare.hasLibInDataDbx(form, "form")) {
                const wrap = form.closest(".dbxForm_wrapper") || form;
                dbx.declare.queueFeature("form", wrap, "element", { id: wrap.id || form.id || "undef" });
            }
            if (form.querySelector(".dbxConfirm, [data-confirm]") && !dbx.declare.hasLibInDataDbx(form, "confirm")) {
                dbx.declare.queueFeature("confirm", form, "element", { id: form.id || "undef" });
            }
        });

        root.querySelectorAll(".dbx-ajax-root").forEach(function (el) {
            if (!dbx.declare.hasLibInDataDbx(el, "ajax")) {
                dbx.declare.queueFeature("ajax", el, "element", { id: el.id || "undef" });
            }
        });

        root.querySelectorAll(".dbxConfirm:not(form), [data-confirm]:not(form)").forEach(function (el) {
            const confirmRoot = dbx.declare.infer.confirmRoot(el);
            if (confirmRoot && !dbx.declare.hasLibInDataDbx(confirmRoot, "confirm")) {
                dbx.declare.queueFeature("confirm", confirmRoot, "element", { id: confirmRoot.id || "undef" });
            }
        });

        root.querySelectorAll(".dbx-win, .dbx-win-preload").forEach(function (el) {
            if (!dbx.declare.hasLibInDataDbx(el, "openWin")) {
                dbx.declare.queueFeature("openWin", el, "global", { id: el.id || "undef" });
            }
        });

        dbx.log("declare.scanAutoload → tasks:", dbx._tasks.length);
    };


    /* =====================================================
    * GLOBAL ERROR SHIELD (FAIL-SAFE)
    * ===================================================== */

    if (!dbx.__errorShield) {

        window.onerror = function (msg, src, line, col, err) {

            if (dbx.isIgnorableBrowserError(msg)) {
                dbx.log("IGNORED BROWSER ERROR:", msg);
                return true;
            }

            dbx.error(
                "GLOBAL ERROR:",
                msg,
                "at", src + ":" + line + ":" + col
            );

            // DEV → Fehler NICHT unterdrücken (Browser zeigt Stacktrace)
            if (dbx.LOG) return false;

            // LIVE → Fehler unterdrücken (silent)
            return true;
        };

        window.onunhandledrejection = function (e) {

            const reason = e && e.reason ? e.reason : "";
            const msg = reason && reason.message ? reason.message : reason;
            if (dbx.isIgnorableBrowserError(msg)) {
                dbx.log("IGNORED PROMISE ERROR:", msg);
                return true;
            }

            dbx.error(
                "PROMISE ERROR:",
                reason
            );
        };

        dbx.__errorShield = true;
    }




    /* =====================================================
     * CONFIG
     * ===================================================== */
    dbx.config = dbx.config || (function () {

        let scriptPath = "/";
        let design = "default";
        let rootPath = "/";
        let log = false;

        const scripts = document.getElementsByTagName("script");

        for (let s of scripts) {

            if (!s.src || !s.src.includes("core.js")) continue;

            const url = new URL(s.src, window.location.origin);

            scriptPath = url.pathname.substring(0, url.pathname.lastIndexOf("/") + 1);

            const d = url.searchParams.get("design");
            if (d) design = d;

            const l = url.searchParams.get("log");
            if (l && l.toLowerCase() === "on") log = true;

            if (scriptPath.includes("/js/lib/")) {
                rootPath = scriptPath.split("/js/lib/")[0] + "/";
            }

            break;
        }

        return {
            libPath: scriptPath,
            rootPath,
            design,
            log
        };

    })();

    dbx.LOG = dbx.config.log;
    dbx.log("core init → config:", dbx.config);

    /* =====================================================
     * UI STATE
     * ===================================================== */
    dbx.uiSet = function (lib, id, key, val) {
        if (!lib || !id || !key || id === "undef") return;
        const k = `dbx.UI.${lib}.${id}.${key}`;
        try {
            localStorage.setItem(k, JSON.stringify(val));
            dbx.log("uiSet:", k, val);
        } catch (e) {
            dbx.warn("uiSet failed:", k, e);
        }
    };

    dbx.uiGet = function (lib, id, key, def) {
        if (!lib || !id || !key || id === "undef") return def;
        const k = `dbx.UI.${lib}.${id}.${key}`;
        try {
            const v = localStorage.getItem(k);
            if (v === null) return def;
            const parsed = JSON.parse(v);
            dbx.log("uiGet:", k, parsed);
            return parsed;
        } catch (e) {
            dbx.warn("uiGet failed:", k, e);
            return def;
        }
    };

    dbx.getDesign = () => dbx.config.design;
    dbx.getLibId  = cfg => (cfg && cfg.id) ? cfg.id : "undef";

    /* =====================================================
     * FOOTER STATUS
     * ===================================================== */
    dbx.footerStatus = dbx.footerStatus || (function () {

        function formatSeconds(seconds) {
            seconds = Math.max(0, Number(seconds) || 0);
            return seconds.toFixed(3);
        }

        function formatPhpSeconds(seconds) {
            seconds = Math.max(0, Number(seconds) || 0);
            return Math.max(0.001, seconds).toFixed(3);
        }

        function runtimeElement() {
            return document.querySelector("[data-dbx-runtime]");
        }

        function responseRuntimeSeconds() {
            const perf = window.performance;
            const nav = perf && perf.getEntriesByType && perf.getEntriesByType("navigation")[0];
            const domReadyEnd = nav ? Number(nav.domContentLoadedEventEnd) : NaN;

            // navigation.duration endet erst nach dem load-Event. Langsame Bilder,
            // Fonts oder andere Subressourcen ließen die Anzeige deshalb noch
            // weiterlaufen, obwohl DOM und JavaScript bereits einsatzbereit waren.
            if (Number.isFinite(domReadyEnd) && domReadyEnd > 0) {
                return domReadyEnd / 1000;
            }

            return perf && perf.now ? perf.now() / 1000 : 0;
        }

        function navigationPhpRuntimeSeconds() {
            const perf = window.performance;
            const nav = perf && perf.getEntriesByType && perf.getEntriesByType("navigation")[0];
            const entries = nav && nav.serverTiming ? Array.from(nav.serverTiming) : [];
            const timer = entries.find(item => item && item.name === "dbxphp");
            const duration = timer ? Number(timer.duration) : NaN;

            return Number.isFinite(duration) && duration >= 0 ? duration / 1000 : null;
        }

        function phpRuntimeSeconds(el) {
            const headerRuntime = navigationPhpRuntimeSeconds();
            if (headerRuntime !== null) {
                return headerRuntime;
            }

            const raw = el ? el.getAttribute("data-dbx-php-runtime") : "";
            const value = Number(raw);

            return Number.isFinite(value) && value >= 0 ? value : null;
        }

        function write(responseSeconds, phpSeconds, label) {
            const el = runtimeElement();
            if (!el) return;

            const responseLabel = formatSeconds(responseSeconds);
            const hasPhp = phpSeconds != null && phpSeconds !== ""
                && Number.isFinite(Number(phpSeconds)) && Number(phpSeconds) >= 0;
            const phpValue = hasPhp ? Number(phpSeconds) : null;
            const phpLabel = hasPhp ? "/" + formatPhpSeconds(phpValue) : "";
            const text = responseLabel + phpLabel + " sec";

            el.textContent = text;

            if (hasPhp) {
                el.setAttribute("data-dbx-php-runtime", formatPhpSeconds(phpValue));
            }

            const title = hasPhp
                ? label + ": " + responseLabel + " / " + formatPhpSeconds(phpValue) + " sec"
                : label + ": " + responseLabel + " sec";

            el.setAttribute("data-dbx-tooltip", title);
            el.setAttribute("data-dbx-page-runtime-title", title);
        }

        function init() {
            const el = runtimeElement();
            if (!el) return;

            const update = function () {
                write(
                    responseRuntimeSeconds(),
                    phpRuntimeSeconds(el),
                    dbx.translate({
                        de: "DOM- und JavaScript-Bereitschaft / PHP-Laufzeit",
                        en: "DOM and JavaScript readiness / PHP runtime",
                        es: "Disponibilidad de DOM y JavaScript / tiempo de ejecución de PHP"
                    })
                );
            };

            if (document.readyState !== "loading") {
                update();
            } else {
                // Genau an derselben Grenze messen, die auch angezeigt wird.
                // Ein früher setTimeout-Wert und ein später load-Wert ließen die
                // Zahl sichtbar springen und rechneten langsame Bilder hinein.
                document.addEventListener("DOMContentLoaded", update, { once: true });
            }
        }


        function shouldTrackAjaxRuntime(url, body, options) {
            if (options && (
                options.skipRuntime === true ||
                options.footerRuntime === "hidden" ||
                options.footerRuntime === "skip" ||
                options.footerRuntime === "0"
            )) {
                return false;
            }

            if (options && String(options.mode || "").toLowerCase() === "json") {
                return false;
            }

            const targetUrl = String(url || "");
            if (/[?&]dbx_sync=0(?:&|$)/.test(targetUrl)) {
                return false;
            }

            if (body instanceof URLSearchParams && body.get("dbx_sync") === "0") {
                return false;
            }

            if (body instanceof FormData && body.get("dbx_sync") === "0") {
                return false;
            }

            if (typeof body === "string" && /(?:^|&)dbx_sync=0(?:&|$)/.test(body)) {
                return false;
            }

            return true;
        }

        return {
            init,
            formatSeconds,
            shouldTrackAjaxRuntime,
            updateAjax(responseSeconds, phpSeconds) {
                write(
                    responseSeconds,
                    phpSeconds,
                    dbx.translate({
                        de: "Letzte AJAX-Anfrage / PHP-Laufzeit",
                        en: "Latest AJAX request / PHP runtime",
                        es: "Última solicitud AJAX / tiempo de ejecución de PHP"
                    })
                );
            }
        };

    })();

    /* =====================================================
     * LOADER
     * ===================================================== */
    dbx.loader = {

        _loaded: {},
        _callbacks: {},

        _cacheBustUrl(url) {
            const searchParams = new URLSearchParams(location.search);
            const cacheBust = searchParams.get("dbx_nocache") || searchParams.get("cachebust") || dbx.assetVersion;

            if (!cacheBust) return url;

            try {
                const parsed = new URL(url, location.origin);
                if (parsed.searchParams.has("v")) return url;
            } catch(e) {}

            return url + (url.includes("?") ? "&" : "?") + "v=" + encodeURIComponent(cacheBust);
        },

        css(url, cb) {

            if (!url) {
                cb && cb({ ok: false });
                return;
            }

            url = this._cacheBustUrl(url);

            if (this._loaded[url] === "loaded") {
                dbx.log("CSS already loaded:", url);
                return cb && cb({ ok: true });
            }

            if (this._loaded[url] === "loading") {
                dbx.log("CSS already loading:", url);
                (this._callbacks[url] ||= []).push(cb);
                return;
            }

            // 🔥 FIX: sauberes URL Matching
            const requested = new URL(url, location.origin);
            const target = requested.pathname;
            const targetSearch = requested.search;

            const links = document.querySelectorAll('link[rel="stylesheet"]');
            for (let l of links) {
                try {
                    const current = new URL(l.href);
                    if (current.pathname === target && (!targetSearch || current.search === targetSearch)) {
                        this._loaded[url] = "loaded";
                        dbx.log("CSS already in DOM:", url);
                        return cb && cb({ ok: true });
                    }
                } catch(e){}
            }

            dbx.log("CSS load start:", url);

            this._loaded[url] = "loading";
            this._callbacks[url] = cb ? [cb] : [];

            const link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = url;

            let finished = false;

            function done(self, status) {
                if (finished) return;
                finished = true;

                self._callbacks[url].forEach(f => f && f(status));
                self._callbacks[url] = [];
            }

            link.onload = () => {
                this._loaded[url] = "loaded";
                dbx.log("CSS loaded:", url);
                done(this, { ok: true });
            };

            link.onerror = () => {
                this._loaded[url] = "error";
                dbx.error("CSS load failed:", url);
                done(this, { ok: false });
            };

            document.head.appendChild(link);
        },

        js(url, cb) {

            if (!url) {
                cb && cb({ ok: false });
                return;
            }

            url = this._cacheBustUrl(url);

            if (this._loaded[url] === "loaded") {
                dbx.log("JS already loaded:", url);
                return cb && cb({ ok: true });
            }

            if (this._loaded[url] === "loading") {
                dbx.log("JS already loading:", url);
                (this._callbacks[url] ||= []).push(cb);
                return;
            }

            // 🔥 FIX: sauberes URL Matching
            const requested = new URL(url, location.origin);
            const target = requested.pathname;
            const targetSearch = requested.search;

            const scripts = document.querySelectorAll('script[src]');
            for (let s of scripts) {
                try {
                    const current = new URL(s.src);
                    if (current.pathname === target && (!targetSearch || current.search === targetSearch)) {
                        this._loaded[url] = "loaded";
                        dbx.log("JS already in DOM:", url);
                        return cb && cb({ ok: true });
                    }
                } catch(e){}
            }

            dbx.log("JS load start:", url);

            this._loaded[url] = "loading";
            this._callbacks[url] = cb ? [cb] : [];

            const s = document.createElement("script");
            s.src = url;
            s.async = false;

            let finished = false;

            function done(self, status) {
                if (finished) return;
                finished = true;

                self._callbacks[url].forEach(f => f && f(status));
                self._callbacks[url] = [];
            }

            s.onload = () => {
                this._loaded[url] = "loaded";
                dbx.log("JS loaded:", url);
                done(this, { ok: true });
            };

            s.onerror = () => {
                this._loaded[url] = "error";
                dbx.error("JS load failed:", url);
                done(this, { ok: false });
            };

            document.body.appendChild(s);
        }

    };

    dbx.add_css = function (type, file, cb) {

        if (!type || !file) {
            cb && cb({ ok: false }); // 🔥 FIX
            return;
        }

        let url = "";

        if (type === "lib") {
            url = dbx.config.libPath + file;
        }

        if (type === "design") {
            url = dbx.config.rootPath + "design/" + dbx.config.design + "/css/" + file;
        }

        if (type === "root") {
            url = dbx.config.rootPath + file;
        }

        const searchParams = new URLSearchParams(location.search);
        const cacheBust = searchParams.get("dbx_nocache") || searchParams.get("cachebust");
        if (cacheBust) {
            url += (url.includes("?") ? "&" : "?") + "dbx_nocache=" + encodeURIComponent(cacheBust);
        }

        if (!url) {
            dbx.warn("add_css invalid type:", type);

            alert(
                "[DBX ERROR]\n" +
                "Invalid CSS scope\n\n" +
                "type=" + type + "\n" +
                "file=" + file
            );

            cb && cb({ ok: false }); // 🔥 FIX
            return;
        }

        dbx.log("add_css:", type, "→", url);
        dbx.loader.css(url, cb);
    };

    dbx.add_js = function (type, file, cb) {

        if (!type || !file) {
            cb && cb({ ok: false }); // 🔥 FIX
            return;
        }

        let url = "";

        if (type === "lib") {
            url = dbx.config.libPath + file;
        }

        if (type === "design") {
            url = dbx.config.rootPath + "design/" + dbx.config.design + "/js/" + file;
        }

        if (type === "root") {
            url = dbx.config.rootPath + file;
        }

        const searchParams = new URLSearchParams(location.search);
        const cacheBust = searchParams.get("dbx_nocache") || searchParams.get("cachebust");
        if (cacheBust) {
            url += (url.includes("?") ? "&" : "?") + "dbx_nocache=" + encodeURIComponent(cacheBust);
        }

        if (!url) {
            dbx.warn("add_js invalid type:", type);

            alert(
                "[DBX ERROR]\n" +
                "Invalid JS scope\n\n" +
                "type=" + type + "\n" +
                "file=" + file
            );

            cb && cb({ ok: false }); // 🔥 FIX
            return;
        }

        dbx.log("add_js:", type, "→", url);
        dbx.loader.js(url, cb);
    };

    /* =====================================================
     * DEPENDENCY CHAIN
     * ===================================================== */
    dbx.load = function (list, done) {

        let i = 0;

        function next() {

            if (i >= list.length) {
                dbx.log("dbx.load → complete");
                return done && done();
            }

            const [type, scope, file] = list[i++];

            dbx.log("dbx.load → step:", type, scope, file);

            let finished = false;

            function safeNext(res) {

                if (finished) return;
                finished = true;

                if (!res || res.ok !== true) {
                    dbx.warn("dbx.load → asset failed:", type, file);
                }

                next();
            }

            // 🔥 FAILSAFE TIMEOUT (z.B. 5s)
            const timer = setTimeout(() => {

                dbx.error("dbx.load timeout:", type, file);

                safeNext({ ok: false });

            }, 5000);

            function wrappedNext(res) {
                clearTimeout(timer);
                safeNext(res);
            }

            if (type === "js") {
                dbx.add_js(scope, file, wrappedNext);
            } else if (type === "css") {
                dbx.add_css(scope, file, wrappedNext);
            } else {
                wrappedNext({ ok: true });
            }
        }

        next();
    };

    /* =====================================================
     * FEATURE REGISTRY
     * ===================================================== */
    dbx.feature = {

        _features: {},

        register(name, obj) {
            this._features[name] = obj;
            dbx.log("feature registered:", name);
        },

        has(name) {
            return Object.prototype.hasOwnProperty.call(this._features, name);
        },

        init(name, el, cfg) {

            dbx.log("init feature:", name, "id:", cfg.id);

            if (this.has(name)) {
                try {
                    this._features[name].init(el, cfg);
                } catch (e) {
                    dbx.error("Feature init failed:", name, e);
                }
                return;
            }

            dbx.error("Feature not registered:", name);
        }
    };

    dbx.loadUtilities = function () {
        const existing = document.querySelector('script[src*="/utilities.js"],script[src$="utilities.js"]');
        if (existing) {
            return;
        }

        dbx.add_js("lib", "utilities.js");
    };

    if (document.body) {
        dbx.loadUtilities();
    } else {
        document.addEventListener("DOMContentLoaded", dbx.loadUtilities, { once: true });
    }

    /* =====================================================
     * PARSER
     * ===================================================== */
    dbx.parseData = function (str) {

        const result = [];
        if (!str) return result;

        const blocks = str.split("||");

        function normalize(val) {

            if (val === undefined || val === null) return val;

            const raw = val.toString().trim();
            const v   = raw.toLowerCase();

            if (v === "on"    || v === "1") return 1;
            if (v === "off"   || v === "0") return 0;
            if (v === "true"  || v === "1") return 1;
            if (v === "false" || v === "0") return 0;            

            // 🔥 JSON AUTO PARSE
            if (
                (raw.startsWith("{") && raw.endsWith("}")) ||
                (raw.startsWith("[") && raw.endsWith("]"))
            ) {
                try {
                    return JSON.parse(raw);
                } catch (e) {
                    dbx.warn("parseData JSON failed:", raw);
                }
            }

            return raw;
        }

        blocks.forEach(block => {

            const cfg = {};
            if (!block) return;

            block.split("|").forEach(part => {

                if (!part) return;

                const idx = part.indexOf("=");
                if (idx === -1) return;

                // 🔥 FIX 1: key lowercase
                const key = part.substring(0, idx).trim().toLowerCase();

                // 🔥 FIX 2: kein trim hier (macht normalize)
                let val = part.substring(idx + 1);

                val = normalize(val);

                cfg[key] = val;
            });

            if (cfg.lib) result.push(cfg);
        });

        return result;
    };

    /* =====================================================
     * RESOLVER (EXACT + FALLBACK BEHALTEN!)
     * ===================================================== */
    function waitForFeatureRegistration(libName, callback, attempts) {

        if (dbx.feature.has(libName)) {
            callback(true);
            return;
        }

        attempts = attempts === undefined ? 8 : attempts;

        if (attempts <= 0) {
            callback(false);
            return;
        }

        window.setTimeout(function () {
            waitForFeatureRegistration(libName, callback, attempts - 1);
        }, 25);
    }

    function reloadFeatureScript(libName, callback) {
        const url = dbx.config.libPath + libName + ".js";
        const target = new URL(url, location.origin).pathname;

        document.querySelectorAll("script[src]").forEach(script => {
            try {
                if (new URL(script.src).pathname === target) {
                    script.parentNode && script.parentNode.removeChild(script);
                }
            } catch (e) {}
        });

        const reloadUrl = url + (url.includes("?") ? "&" : "?") + "dbx_reload=" + Date.now();
        dbx.loader._loaded[url] = null;
        dbx.loader._loaded[reloadUrl] = null;

        dbx.log("resolveFeature → reload script:", libName);
        dbx.loader.js(reloadUrl, function (res) {
            if (!res || res.ok !== true) {
                callback(false);
                return;
            }
            waitForFeatureRegistration(libName, callback, 8);
        });
    }

    dbx.resolveFeature = function (libName, callback) {

        if (!libName) {
            callback && callback(false);
            return;
        }

        if (dbx.feature.has(libName)) {
            dbx.log("resolveFeature → already loaded:", libName);
            return callback && callback(true);
        }

        const exactUrl = dbx.config.libPath + libName + ".js";

        dbx.log("resolveFeature → try exact:", exactUrl);

        dbx.loader.js(exactUrl, function (res) {

            // 🔥 NEW: loader status prüfen
            if (!res || res.ok !== true) {
                dbx.error("resolveFeature → load failed:", libName);
                return callback && callback(false);
            }

            waitForFeatureRegistration(libName, function (registered) {

                if (registered) {
                    dbx.log("resolveFeature → loaded exact:", libName);
                    return callback && callback(true);
                }

                dbx.warn("resolveFeature → script loaded but feature NOT registered:", libName);

                // 🔥 FALLBACK bleibt
                if (libName.includes("-")) {

                    const base = libName.split("-")[0];

                    if (dbx.feature.has(base)) {
                        dbx.log("resolveFeature → base already loaded:", base);
                        return callback && callback(true);
                    }

                    const baseUrl = dbx.config.libPath + base + ".js";

                    dbx.log("resolveFeature → fallback base:", baseUrl);

                    dbx.loader.js(baseUrl, function (res2) {

                        // 🔥 loader fail
                        if (!res2 || res2.ok !== true) {
                            dbx.error("resolveFeature → fallback load failed:", base);
                            return callback && callback(false);
                        }

                        waitForFeatureRegistration(base, function (baseRegistered) {

                            if (baseRegistered) {
                                dbx.log("resolveFeature → loaded base:", base);
                                return callback && callback(true);
                            }

                            dbx.error("Feature not registered after fallback:", libName);
                            return callback && callback(false);
                        });
                    });

                } else {

                    reloadFeatureScript(libName, function (reloaded) {
                        if (reloaded) {
                            dbx.log("resolveFeature → loaded after reload:", libName);
                            return callback && callback(true);
                        }
                        dbx.error("Feature not registered:", libName);
                        return callback && callback(false);
                    });
                }
            });
        });
    };

    /* =====================================================
     * SCAN
     * ===================================================== */
    dbx.scan = function (root) {

        const ctx = root || document;

        if (dbx.declare && typeof dbx.declare.scanAutoload === "function") {
            dbx.declare.scanAutoload(ctx);
        }

        // 🔥 FIX: root selbst + children
        const nodes = [];

        if (ctx.nodeType === 1 && ctx.hasAttribute && ctx.hasAttribute("data-dbx")) {
            nodes.push(ctx);
        }

        ctx.querySelectorAll("[data-dbx]").forEach(n => nodes.push(n));

        // 🔥 NEW: global scoped state store
        dbx._scopeState = dbx._scopeState || {};
        dbx._scopeError = dbx._scopeError || {};
        dbx._scopeInit  = dbx._scopeInit  || {};

        nodes.forEach(el => {

            const cfgList = dbx.parseData(el.getAttribute("data-dbx"));
            if (!cfgList.length) return;

            el.__dbxInitialized = el.__dbxInitialized || {};
            el.__dbxState = el.__dbxState || {};
            el.__dbxError = el.__dbxError || {};

            cfgList.forEach(cfg => {

                if (!cfg.id) cfg.id = "undef";

                const f = dbx.feature._features[cfg.lib];

                if (!f) {
                    dbx.log("scan → feature not yet loaded:", cfg.lib);
                }

                let scopeType = "element";

                if (f && f.scope) {

                    if (!["element","group","global"].includes(f.scope)) {
                        dbx.error("Invalid scope:", cfg.lib, f.scope);
                        return;
                    }

                    scopeType = f.scope;

                } else {
                    dbx.log("scan → fallback scope=element:", cfg.lib);
                }

                let scopeKey;

                if (scopeType === "global") {

                    scopeKey = "global";

                } else if (scopeType === "group") {

                    scopeKey = cfg.group || cfg.id || "group";

                } else {

                    if (!el.__dbxScopeId) {
                        dbx._scopeUid = (dbx._scopeUid || 0) + 1;
                        el.__dbxScopeId = dbx._scopeUid;
                    }

                    scopeKey = el.__dbxScopeId;
                }

                const keyScoped = cfg.lib + "::" + scopeKey;

                const stateStore = (scopeType === "element") ? el.__dbxState : dbx._scopeState;
                const errorStore = (scopeType === "element") ? el.__dbxError : dbx._scopeError;
                const initStore  = (scopeType === "element") ? el.__dbxInitialized : dbx._scopeInit;

                const state = stateStore[keyScoped];

                if (state === "pending") {
                    // 🔥 FIX: pending darf nicht blockieren wenn kein task mehr existiert
                    if (!dbx._taskMap || !dbx._taskMap[keyScoped]) {
                        stateStore[keyScoped] = undefined;
                    } else {
                        return;
                    }
                }
                if (state === "done") return;

                let allowRetry = false;

                if (state === "error") {

                    const err = errorStore[keyScoped] || {};

                    const count = err.count || 1;
                    const last  = err.last || 0;

                    const delay = Math.min(10000, count * 2000);

                    if (count >= 5) {
                        dbx.warn("retry limit reached:", keyScoped);
                        return;
                    }

                    if (Date.now() - last < delay) {
                        return;
                    }

                    dbx.log("retry allowed:", keyScoped, "attempt:", count + 1);
                    allowRetry = true;
                }

                if (initStore[keyScoped] && stateStore[keyScoped] === "done") return;

                const key = keyScoped;

                dbx._taskMap = dbx._taskMap || {};

                //if (!allowRetry && dbx._taskMap[key]) return;

                stateStore[keyScoped] = "pending";

                dbx._taskMap[key] = true;

                dbx._tasks.push({
                    el: el,
                    lib: cfg.lib,
                    cfg: cfg,
                    _key: keyScoped,
                    _scopeType: scopeType
                });

            });
        });

        dbx.runTasks();
    };

   /* =====================================================
     * runTasks
     * ===================================================== */
    dbx.runTasks = function () {

        if (!dbx._tasks.length) return;

        if (dbx._running) {
            dbx.log("runTasks → already running, skip");
            return;
        }

        dbx._running = true;

        const tasks = dbx._tasks.slice();
        dbx._tasks = [];

        dbx.log("runTasks → start", tasks.length);

        const phases = {
            veryfirst: [],
            first: [],
            mid: [],
            last: [],
            verylast: []
        };

        // -------------------------------------------------
        // 🔥 HELPER: richtiger Store je Scope
        // -------------------------------------------------
        function getStores(t) {

            let scopeType = t._scopeType;

            if (!scopeType) {
                const f = dbx.feature._features[t.lib];
                scopeType = (f && f.scope) ? f.scope : "element";
                t._scopeType = scopeType;
            }

            if (scopeType === "element") {

                if (!t.el || t.el.nodeType !== 1) {
                    dbx._scopeState = dbx._scopeState || {};
                    dbx._scopeError = dbx._scopeError || {};
                    dbx._scopeInit  = dbx._scopeInit  || {};
                    return {
                        state: dbx._scopeState,
                        error: dbx._scopeError,
                        init:  dbx._scopeInit
                    };
                }

                dbx.ensureElementScopeStores(t.el);

                return {
                    state: t.el.__dbxState,
                    error: t.el.__dbxError,
                    init:  t.el.__dbxInitialized
                };
            }

            dbx._scopeState = dbx._scopeState || {};
            dbx._scopeError = dbx._scopeError || {};
            dbx._scopeInit  = dbx._scopeInit  || {};

            return {
                state: dbx._scopeState,
                error: dbx._scopeError,
                init:  dbx._scopeInit
            };
        }

        function requeueTask(t, store, key, reason) {

            const err = (store.error && store.error[key]) || { count: 0 };
            const attempt = err.count + 1;
            const maxAttempts = 3;

            if (store.error) {
                store.error[key] = { count: attempt, last: Date.now() };
            }

            if (attempt < maxAttempts) {
                dbx.log("runTasks → requeue (" + attempt + "/" + maxAttempts + "):", t.lib, reason || "");
                if (store.state) delete store.state[key];
                if (dbx._taskMap) delete dbx._taskMap[key];
                dbx._tasks.push(t);
                return true;
            }

            return false;
        }

        function getScopeType(t) {
            const f = dbx.feature._features[t.lib];
            const scopeType = (f && f.scope) ? f.scope : "element";

            if (!["element","group","global"].includes(scopeType)) {
                dbx.error("Invalid scope:", t.lib, scopeType);
                return "element";
            }

            return scopeType;
        }

        function makeTaskKey(t, scopeType) {
            if (scopeType === "global") {
                return t.lib + "::global";
            }

            if (scopeType === "group") {
                return t.lib + "::" + (t.cfg.group || t.cfg.id || "group");
            }

            if (!t.el.__dbxScopeId) {
                dbx._scopeUid = (dbx._scopeUid || 0) + 1;
                t.el.__dbxScopeId = dbx._scopeUid;
            }

            return t.lib + "::" + t.el.__dbxScopeId;
        }

        function storeForScope(t, scopeType) {
            if (scopeType === "element") {
                if (t.el && t.el.nodeType === 1) {
                    dbx.ensureElementScopeStores(t.el);
                }
                return {
                    state: t.el.__dbxState,
                    error: t.el.__dbxError,
                    init:  t.el.__dbxInitialized
                };
            }

            dbx._scopeState = dbx._scopeState || {};
            dbx._scopeError = dbx._scopeError || {};
            dbx._scopeInit  = dbx._scopeInit  || {};

            return {
                state: dbx._scopeState,
                error: dbx._scopeError,
                init:  dbx._scopeInit
            };
        }

        function normalizeTaskScope(t) {
            const oldKey = t._key;
            const oldScopeType = t._scopeType || "element";
            const newScopeType = getScopeType(t);
            const newKey = makeTaskKey(t, newScopeType);

            if (oldKey && oldKey !== newKey) {
                const oldStore = storeForScope(t, oldScopeType);

                if (oldStore.state && oldStore.state[oldKey] === "pending") {
                    delete oldStore.state[oldKey];
                }

                if (oldStore.error && oldStore.error[oldKey]) {
                    delete oldStore.error[oldKey];
                }

                if (dbx._taskMap) {
                    delete dbx._taskMap[oldKey];
                }
            }

            t._scopeType = newScopeType;
            t._key = newKey;

            return t;
        }

        function assignPhases() {
            const seen = {};

            tasks.forEach(t => {

                normalizeTaskScope(t);

                const store = getStores(t);

                if (store.init[t._key] || store.state[t._key] === "done") {
                    if (dbx._taskMap) delete dbx._taskMap[t._key];
                    return;
                }

                if (seen[t._key]) {
                    return;
                }

                seen[t._key] = true;
                store.state[t._key] = "pending";
                dbx._taskMap = dbx._taskMap || {};
                dbx._taskMap[t._key] = true;

                const f = dbx.feature._features[t.lib];
                const libPrio = f && f.priority ? f.priority : "mid";
                const cfgPrio = t.cfg.prio;
                const prio = cfgPrio || libPrio || "mid";

                if (!phases[prio]) phases.mid.push(t);
                else phases[prio].push(t);
            });
        }

        // =================================================
        // VERYFIRST
        // =================================================
        function runVeryFirstImmediate(done) {

            const list = phases.veryfirst;
            if (!list.length) return done();

            let i = 0;

            function next() {

                if (i >= list.length) return done();

                const t = list[i++];
                const key = t._key;

                const store = getStores(t);

                if (store.state[key] !== "pending") {
                    store.state[key] = "pending";
                }

                dbx.resolveFeature(t.lib, function (ok) {

                    if (ok !== true) {
                        if (requeueTask(t, store, key, "resolveFeature failed")) {
                            return next();
                        }
                        dbx.error("runTasks → lib load failed:", t.lib);
                        store.state[key] = "error";
                        return next();
                    }

                    const f = dbx.feature._features[t.lib];

                    const assets = [];

                    if (f && Array.isArray(f.css)) {
                        assets.push(...f.css);
                    }

                    if (f && Array.isArray(f.js)) {
                        f.js.forEach(entry => {
                            if (Array.isArray(entry)) {
                                assets.push(entry);
                            } else {
                                assets.push(['js', 'lib', entry]);
                            }
                        });
                    }

                    if (assets.length) {
                        dbx.load(assets, runInit);
                    } else {
                        runInit();
                    }

                    function runInit() {

                        try {

                            const feature = dbx.feature._features[t.lib];

                            if (!feature || !feature.init) {
                                if (requeueTask(t, store, key, "init missing")) {
                                    return next();
                                }
                                dbx.error("runTasks → no init for lib:", t.lib);
                                store.state[key] = "error";
                                return next();
                            }

                            feature.init.call(feature, t.el, t.cfg);

                            store.init[key] = true;
                            store.state[key] = "done";

                            delete store.error[key];

                            if (dbx._taskMap) delete dbx._taskMap[key];

                        } catch (e) {

                            if (requeueTask(t, store, key, "init error: " + e.message)) {
                                return next();
                            }

                            dbx.error("runTasks → INIT ERROR:", t.lib, e);
                            store.state[key] = "error";

                            if (dbx._taskMap) delete dbx._taskMap[key];
                        }

                        next();
                    }
                });
            }

            next();
        }

        // -------------------------------------------------
        // PREPARE (unverändert)
        // -------------------------------------------------
        function runPrepare(done) {

            let i = 0;
            const assetSet = new Set();

            function next() {

                if (i >= tasks.length) {

                    const list = Array.from(assetSet).map(s => JSON.parse(s));

                    if (!list.length) return done();

                    return dbx.load(list, done);
                }

                const t = tasks[i++];

                dbx.resolveFeature(t.lib, function (ok) {

                    if (ok !== true) return next();

                    const f = dbx.feature._features[t.lib];

                    if (f && Array.isArray(f.css)) {
                        f.css.forEach(entry => assetSet.add(JSON.stringify(entry)));
                    }

                    if (f && Array.isArray(f.js)) {
                        f.js.forEach(entry => {
                            if (Array.isArray(entry)) {
                                assetSet.add(JSON.stringify(entry));
                            } else {
                                assetSet.add(JSON.stringify(['js', 'lib', entry]));
                            }
                        });
                    }

                    next();
                });
            }

            next();
        }

        // -------------------------------------------------
        // RUNNER (FIXED)
        // -------------------------------------------------
        function runPhase(name, list, done) {

            dbx.log("runPhase →", name, "tasks:", list.length);

            let i = 0;

            function next() {

                if (i >= list.length) {
                    return done && done();
                }

                const t = list[i++];

                dbx.log("runPhase → task:", name, t.lib, t._key);

                if (name === "veryfirst") return next();

                const key = t._key;
                const store = getStores(t);

                if (store.state[key] !== "pending") {
                    store.state[key] = "pending";
                }

                new Promise((resolve) => {

                    function runInit() {

                        try {

                            const f = dbx.feature._features[t.lib];

                            if (!f || !f.init) {

                                if (requeueTask(t, store, key, "init missing")) {
                                    return resolve();
                                }

                                dbx.error("runPhase → NO INIT:", t.lib);
                                store.state[key] = "error";

                                if (dbx._taskMap) delete dbx._taskMap[key];

                                return resolve();
                            }

                            dbx.log("runPhase → INIT:", t.lib);

                            // 🔥 FIX: korrektes this wiederherstellen (einzige Änderung)
                            const res = f.init.call(f, t.el, t.cfg);

                            if (res && typeof res.then === "function") {
                                res.finally(() => {
                                    store.init[key] = true;
                                    store.state[key] = "done";
                                    delete store.error[key];

                                    if (dbx._taskMap) delete dbx._taskMap[key];

                                    resolve();
                                });
                            } else {
                                store.init[key] = true;
                                store.state[key] = "done";
                                delete store.error[key];

                                if (dbx._taskMap) delete dbx._taskMap[key];

                                resolve();
                            }

                        } catch (e) {

                            if (requeueTask(t, store, key, "init error: " + e.message)) {
                                return resolve();
                            }

                            dbx.error("runPhase → INIT ERROR:", t.lib, e);
                            store.state[key] = "error";

                            if (dbx._taskMap) delete dbx._taskMap[key];

                            resolve();
                        }
                    }

                    const libName = t.lib || t.cfg?.lib;

                    dbx.resolveFeature(libName, (ok) => {

                        if (ok !== true) {
                            if (requeueTask(t, store, key, "resolveFeature failed")) {
                                return resolve();
                            }
                            dbx.error("runPhase → lib load failed:", libName);
                            store.state[key] = "error";
                            if (dbx._taskMap) delete dbx._taskMap[key];
                            return resolve();
                        }

                        const f = dbx.feature._features[libName];

                        const assets = [];

                        if (f && Array.isArray(f.css)) {
                            assets.push(...f.css);
                        }

                        if (f && Array.isArray(f.js)) {
                            f.js.forEach(entry => {
                                if (Array.isArray(entry)) {
                                    assets.push(entry);
                                } else {
                                    assets.push(['js', 'lib', entry]);
                                }
                            });
                        }

                        if (assets.length) {
                            dbx.load(assets, runInit);
                        } else {
                            runInit();
                        }

                    });

                }).then(next);
            }

            next();
        }

        // =================================================
        // EXECUTION
        // =================================================
        runPrepare(function () {

            assignPhases();

            runVeryFirstImmediate(function () {

                runPhase("first", phases.first, function () {

                    runPhase("mid", phases.mid, function () {

                        runPhase("last", phases.last, function () {

                            runPhase("verylast", phases.verylast, function () {

                                dbx._taskMap = {};
                                dbx._running = false;

                                if (dbx._tasks.length) {
                                    setTimeout(dbx.runTasks, 0);
                                }

                            });
                        });
                    });
                });
            });
        });
    };


    /* =====================================================
     * 🔥 MUTATION OBSERVER (ADD ONLY)
     * ===================================================== */
    if (!dbx.__observerInit) {

        dbx.log("observer → init");

        const observer = new MutationObserver(function (mutations) {

            mutations.forEach(m => {

                // =================================================
                // 🔥 ADD (bestehend)
                // =================================================
                m.addedNodes.forEach(node => {

                    if (node.nodeType !== 1) return;

                    if (node.hasAttribute && node.hasAttribute("data-dbx")) {
                        dbx.log("observer → node");
                        dbx.scan(node);
                    }

                    if (node.querySelectorAll) {
                        const found = node.querySelectorAll("[data-dbx]");
                        if (found.length) {
                            dbx.log("observer → subtree:", found.length);
                            dbx.scan(node);
                        }
                    }
                });

                // =================================================
                // 🔥 NEW: REMOVE → CLEANUP + DESTROY
                // =================================================
                m.removedNodes.forEach(node => {

                    if (node.nodeType !== 1) return;

                    const list = [];

                    // selbst prüfen
                    if (node.hasAttribute && node.hasAttribute("data-dbx")) {
                        list.push(node);
                    }

                    // subtree prüfen
                    if (node.querySelectorAll) {
                        node.querySelectorAll("[data-dbx]").forEach(n => list.push(n));
                    }

                    if (!list.length) return;

                    list.forEach(el => {

                        const cfgList = dbx.parseData(el.getAttribute("data-dbx"));
                        if (!cfgList.length) return;

                        cfgList.forEach(cfg => {

                            if (!cfg.id) cfg.id = "undef";

                            const f = dbx.feature._features[cfg.lib];
                            if (!f || !f.scope) return;

                            const scopeType = f.scope;

                            let scopeKey;

                            if (scopeType === "global") {
                                // global → NICHT löschen
                                return;
                            }

                            if (scopeType === "group") {
                                scopeKey = cfg.group || cfg.id || "group";
                            } else {
                                scopeKey = el.__dbxScopeId;
                            }

                            if (!scopeKey) return;

                            const keyScoped = cfg.lib + "::" + scopeKey;

                            const storeState = (scopeType === "element") ? el.__dbxState : dbx._scopeState;
                            const storeError = (scopeType === "element") ? el.__dbxError : dbx._scopeError;
                            const storeInit  = (scopeType === "element") ? el.__dbxInitialized : dbx._scopeInit;

                            // -------------------------------------------------
                            // 🔥 DESTROY (optional)
                            // -------------------------------------------------
                            try {
                                if (f.destroy && storeInit && storeInit[keyScoped]) {
                                    f.destroy(el, cfg);
                                }
                            } catch (e) {
                                dbx.error("destroy failed:", cfg.lib, e);
                            }

                            // -------------------------------------------------
                            // 🔥 CLEANUP
                            // -------------------------------------------------
                            if (storeState && keyScoped in storeState) {
                                delete storeState[keyScoped];
                            }

                            if (storeError && keyScoped in storeError) {
                                delete storeError[keyScoped];
                            }

                            if (storeInit && keyScoped in storeInit) {
                                delete storeInit[keyScoped];
                            }

                            dbx.log("GC cleanup:", keyScoped);
                        });
                    });
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        dbx.__observerInit = true;
    }

    /* =====================================================
     * 🔥 EVENT DELEGATION HELPER
     * ===================================================== */
    dbx.on = function (event, selector, handler) {

        document.addEventListener(event, function (e) {

            const el = e.target.closest(selector);
            if (!el) return;

            handler.call(el, e, el);

        }, true); // 🔥 WICHTIG: CAPTURE PHASE

        dbx.log("delegation registered:", event, selector);
    };


    /* =====================================================
    * 🔥 EVENT SYSTEM (CUSTOM)
    * ===================================================== */

    dbx.event = dbx.event || {

        _events: {},

        on(name, handler) {
            if (!name || typeof handler !== "function") return;

            this._events[name] = this._events[name] || [];
            this._events[name].push(handler);

            dbx.log("event:on →", name);
        },

        emit(name, data) {
            if (!name) return;

            const list = this._events[name];
            if (!list || !list.length) return;

            dbx.log("event:emit →", name, data);

            list.forEach(fn => {
                try {
                    fn(data);
                } catch (e) {
                    dbx.error("event handler failed:", name, e);
                }
            });

            // 🔥 NEU: scoped event
            if (data && data.id) {
                const scoped = name + ":" + data.id;
                const list2 = this._events[scoped];

                if (list2) {
                    list2.forEach(fn => {
                        try {
                            fn(data);
                        } catch (e) {
                            dbx.error("event handler failed:", scoped, e);
                        }
                    });
                }
            }
        }

    };

   /* =====================================================
    * DEVICE (STATE-BASED ABSTRACTION)
    * ===================================================== */
    dbx.device = (function(){

        const state = {
            visible: true,
            online: true,
            active: true,
            lastActivity: Date.now()
        };

        const IDLE_MS = 30000; // 30s

        /* =====================================================
        * INTERNAL UPDATES
        * ===================================================== */

        function updateVisibility(){
            state.visible = !document.hidden;
        }

        function updateOnline(){
            state.online = navigator.onLine !== false;
        }

        function markActive(){
            state.lastActivity = Date.now();
            state.active = true;
        }

        function checkIdle(){
            const now = Date.now();
            state.active = (now - state.lastActivity) < IDLE_MS;
        }

        /* =====================================================
        * BIND EVENTS (BROWSER)
        * ===================================================== */

        document.addEventListener('visibilitychange', updateVisibility, true);
        window.addEventListener('online', updateOnline, true);
        window.addEventListener('offline', updateOnline, true);

        document.addEventListener('mousemove', markActive, true);
        document.addEventListener('keydown', markActive, true);
        document.addEventListener('click', markActive, true);

        // idle checker (leichtgewichtig)
        setInterval(checkIdle, 5000);

        // init
        updateVisibility();
        updateOnline();

        /* =====================================================
        * PUBLIC API
        * ===================================================== */

        return {

            /* ===== READ ===== */

            isVisible(){
                return state.visible === true;
            },

            isOnline(){
                return state.online === true;
            },

            isActive(){
                return state.active === true;
            },

            getState(){
                return Object.assign({}, state);
            },

            /* =====================================================
            * OPTIONAL: EXTERNAL OVERRIDE (FUTURE: APP / PWA)
            * ===================================================== */

            _set(partial){

                if(!partial || typeof partial !== 'object') return;

                if(typeof partial.visible === 'boolean'){
                    state.visible = partial.visible;
                }

                if(typeof partial.online === 'boolean'){
                    state.online = partial.online;
                }

                if(typeof partial.active === 'boolean'){
                    state.active = partial.active;
                }

                if(typeof partial.lastActivity === 'number'){
                    state.lastActivity = partial.lastActivity;
                }

                dbx.log('[device] override', partial);
            }

        };

    })();

    /* =====================================================
    * LOOP (SMART POLLING CORE)
    * ===================================================== */
    dbx.loop = (function(){

        const tasks = {};

        function clamp(v, min, max){
            if(min != null && v < min) return min;
            if(max != null && v > max) return max;
            return v;
        }

        function resolveInterval(task){

            const t = task.timing || {};

            let interval;

            if(task.paused){
                return null;
            }

            // hint
            if(task.hint){

                switch(task.hint){

                    case 'fast':   interval = t.min || t.base; break;
                    case 'slow':   interval = Math.max((t.base||2000)*2, t.idle||3000); break;
                    case 'idle':   interval = t.idle || t.base; break;
                    case 'boost':  interval = (t.base||2000) / 2; break;
                    default:       interval = t.base || 2000;
                }

            } else {

                if(!dbx.device.isVisible()){
                    interval = t.hidden || (t.base||2000)*3;
                }
                else if(!dbx.device.isActive()){
                    interval = t.idle || (t.base||2000)*2;
                }
                else{
                    interval = t.base || 2000;
                }
            }

            return clamp(
                interval,
                t.min || 500,
                t.max || 30000
            );
        }

        function schedule(task){

            if(task.paused) return;

            if(task.timer) {
                clearTimeout(task.timer); // 🔥 FIX
                task.timer = null;        // 🔥 FIX
            }

            const interval = resolveInterval(task);
            if(interval == null) return;

            task.timer = setTimeout(() => run(task), interval);
        }

        function run(task){

            if(task.running) return;

            task.running = true;

            let res;

            try{
                res = task.onRun({
                    id: task.id,
                    hint: task.hint
                });
            }
            catch(e){
                dbx.error('[loop] run error', task.id, e);
                finish();
                return;
            }

            Promise.resolve(res)
                .catch(err => {
                    dbx.error('[loop] async error', task.id, err);
                })
                .finally(() => finish());

            function finish(){

                task.running = false;
                task.lastRun = Date.now();

                task.timer = null; // 🔥 FIX

                if(task.hintUntil && Date.now() > task.hintUntil){
                    task.hint = null;
                    task.hintUntil = 0;
                }

                schedule(task);
            }
        }

        return {

            add(cfg){

                if(!cfg || !cfg.id || !cfg.onRun){
                    dbx.error('[loop] invalid task', cfg);
                    return;
                }

                if(tasks[cfg.id]){
                    dbx.warn('[loop] already exists', cfg.id);
                    return;
                }

                tasks[cfg.id] = {
                    id: cfg.id,
                    onRun: cfg.onRun,
                    timing: cfg.timing || {},
                    running: false,
                    paused: false,
                    hint: null,
                    hintUntil: 0,
                    timer: null,
                    lastRun: 0
                };

                schedule(tasks[cfg.id]);

                dbx.log('[loop] add', cfg.id);
            },

            hint(id, mode, opts){

                const t = tasks[id];
                if(!t) return;

                if(mode === 'pause'){
                    t.paused = true;
                    if(t.timer){
                        clearTimeout(t.timer);
                        t.timer = null;
                    }
                    return;
                }

                if(mode === 'resume'){
                    t.paused = false;
                    if(t.timer) clearTimeout(t.timer);
                    schedule(t);
                    return;
                }

                if(mode === 'none'){
                    t.hint = null;
                    t.hintUntil = 0;
                    return;
                }

                t.hint = mode;

                if(opts && opts.duration){
                    t.hintUntil = Date.now() + opts.duration;
                }

                if(mode === 'boost'){

                    // 🔥 FIX: niemals während running starten
                    if(t.running){
                        return; // einfach nächsten Zyklus beschleunigen
                    }

                    if(t.timer){
                        clearTimeout(t.timer);
                        t.timer = null;
                    }

                    run(t);
                }
            },

            debug(){
                const out = [];

                Object.values(tasks).forEach(t => {
                    out.push({
                        id: t.id,
                        running: t.running,
                        hint: t.hint,
                        lastRun: t.lastRun,
                        timer: !!t.timer
                    });
                });

                return out;
            }

        };

    })();

 

    /* =====================================================
    * DEVICE CAPABILITIES (BRIDGE)
    * ===================================================== */

    dbx.device.camera = {

        async open(opts){

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                dbx.error('[device] camera not supported');
                throw new Error('camera_not_supported');
            }

            const constraints = opts?.constraints || { video: true };

            try {

                const stream = await navigator.mediaDevices.getUserMedia(constraints);

                return {
                    stream,
                    stop(){
                        stream.getTracks().forEach(t => t.stop());
                    }
                };

            } catch (e) {

                dbx.error('[device] camera error', e);
                throw e;
            }
        }

    };


    dbx.device.file = {

        async pick(opts){

            return new Promise((resolve, reject) => {

                const input = document.createElement('input');
                input.type = 'file';

                if (opts?.accept) input.accept = opts.accept;
                if (opts?.multiple) input.multiple = true;

                input.onchange = () => {

                    if(!input.files || !input.files.length){
                        resolve(null); // 🔥 FIX
                    } else {
                        resolve(input.files);
                    }
                };

                input.onerror = reject;

                input.click();
            });
        }

    };


    dbx.device.clipboard = {

        async write(text){

            if (!navigator.clipboard) {
                dbx.error('[device] clipboard not supported');
                throw new Error('clipboard_not_supported');
            }

            return navigator.clipboard.writeText(text);
        },

        async read(){

            if (!navigator.clipboard) {
                dbx.error('[device] clipboard not supported');
                throw new Error('clipboard_not_supported');
            }

            return navigator.clipboard.readText();
        }

    };


    dbx.device.share = {

        async open(data){

            if (!navigator.share) {
                dbx.error('[device] share not supported');
                throw new Error('share_not_supported');
            }

            return navigator.share(data);
        }

    };


    /* =====================================================
     * DOM READY
     * ===================================================== */
    function domReady() {
        dbx.log("DOM ready → scan");
        dbx.scan(document);
        dbx.footerStatus.init();
    }
    if (document.readyState !== "loading") {
        domReady();
    } else {
        document.addEventListener("DOMContentLoaded", domReady, { once: true });
    }

    /* =====================================================
     * API
     * ===================================================== */
    dbx.rescan = function (root) {
        dbx.log("rescan");
        const ctx = root || document;
        dbx.scan(ctx);

        Object.keys(dbx.feature._features || {}).forEach(function (name) {
            const f = dbx.feature._features[name];
            if (!f || typeof f.rescan !== "function") return;

            try {
                f.rescan(ctx);
            } catch (e) {
                dbx.error("rescan → feature error:", name, e);
            }
        });
    };

    dbx.loadFeature = function (name, cb) {
        dbx.resolveFeature(name, cb || function () {});
    };

})(window, document);
