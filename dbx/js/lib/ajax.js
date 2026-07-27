/*!
 * @file ajax.js
 * =========================================================
 * DBX AJAX SYSTEM (ajax.js)
 * =========================================================
 *
 * Zweck
 * -----
 * ajax.js ist die universelle, scoped AJAX-Infrastruktur von DBX.
 *
 * Die Lib verarbeitet innerhalb ihres eigenen Root-Elements:
 * - Form-Submits
 * - Link-Klicks
 * - Button-/Action-Klicks
 *
 * Standardverhalten
 * -----------------
 * Der DBX-Standard ist weiterhin:
 *
 *   HTML laden → Target ersetzen
 *
 * Für UI-nahe Spezialfälle unterstützt ajax.js zusätzlich:
 *
 *   JSON laden → Antwort an aufrufende Logik zurückgeben
 *
 *
 * Architekturprinzip
 * ------------------
 * ajax.js ist bewusst NUR Transport-/Response-Infrastruktur.
 * Die Lib enthält keine Fachlogik für Reports, Grid, Formflows usw.
 *
 * Zuständigkeiten:
 * - ajax.js   → URL/Target/Mode/Request/Response
 * - form.js   → Form-/Report-UI-Logik
 * - grid.js   → Grid-Logik / Sort / Sync / Save / Restore
 * - confirm.js→ Bestätigungsdialoge vor Aktionen
 *
 *
 * Scope-Regel (verbindlich)
 * -------------------------
 * ajax.js arbeitet immer mit:
 *
 *   scope: "element"
 *
 * Das bedeutet:
 * - eine ajax-Instanz gilt nur für ihr Root-Element
 * - plus dessen Children
 * - niemals global
 *
 * Mehrere lib=ajax Instanzen auf derselben Seite sind ausdrücklich erlaubt.
 * Der jeweils nächste passende AJAX-Root im DOM ist zuständig.
 *
 *
 * =========================================================
 * KONFIGURATION AM ROOT (data-dbx)
 * =========================================================
 *
 * Beispiel:
 *
 *   <div id="dbx_target_7"
 *        data-dbx="lib=ajax|class=dbxAjax|mode=html|url=form|target=dbx_target_7">
 *     ...
 *   </div>
 *
 *
 * Unterstützte Parameter am AJAX-Root
 * -----------------------------------
 *
 * lib=ajax
 *   Pflicht. Registriert die AJAX-Lib auf diesem Root.
 *
 * class=dbxAjax
 *   Match-Class. Nur Elemente mit dieser Klasse werden
 *   von dieser AJAX-Instanz verarbeitet.
 *
 *   Beispiele:
 *   - class=dbxAjax
 *   - class=dbxUiAjax
 *   - class=*            → alle passenden Elemente im Scope
 *
 * mode=html|json|text
 *   Default-Response-Typ dieser AJAX-Instanz.
 *
 *   html:
 *   - Standard in DBX
 *   - Response wird als HTML interpretiert
 *   - Target wird ersetzt
 *
 *   json:
 *   - Response wird als JSON interpretiert
 *   - standardmäßig KEIN automatischer Replace
 *   - die aufrufende Lib verarbeitet die Antwort selbst
 *
 *   text:
 *   - Response wird als Text gelesen
 *   - kein automatischer Replace, außer explizit aktiviert
 *
 * bind=form,link,button
 *   Welche Elementtypen diese AJAX-Instanz behandeln darf.
 *
 *   Erlaubt:
 *   - bind=form
 *   - bind=link
 *   - bind=button
 *   - bind=form,link,button
 *   - bind=* / bind=all
 *
 *   Wenn nicht angegeben, ist Standard:
 *   - form,link,button
 *
 * url=form|lib|<volle URL>|&...
 *   Default-URL-Auflösung.
 *
 *   Erlaubt:
 *
 *   url=form
 *     → benutze die action der umgebenden Form
 *
 *   url=lib
 *     → benutze die URL aus der AJAX-Lib selbst
 *       (nur sinnvoll, wenn dort wirklich eine echte URL definiert ist)
 *
 *   url=?dbx_modul=...
 *     → vollständige feste URL
 *
 *   url=&dbx_run3=delete
 *     → hänge Parameter an die Basis-URL an
 *
 *       Basis-URL für "&..." ist:
 *       1. Form-URL
 *       2. sonst Lib-URL
 *
 * target=dbx_target_7
 *   Default-Target für Replace-Fälle.
 *
 *   Wichtig:
 *   - vor allem relevant bei mode=html
 *   - bei json standardmäßig nicht nötig
 *   - kann aber für explizite JSON-Replace-Fälle verwendet werden
 *
 * replace=target|inner
 *   Steuert, wie HTML in das Target übernommen wird.
 *
 *   target:
 *   - Standard in DBX
 *   - das Target-Element wird durch die Response ersetzt
 *
 *   inner:
 *   - nur der Inhalt des Target-Elements wird ersetzt
 *   - das Target-Element selbst bleibt im DOM bestehen
 *   - sinnvoll für AJAX-Menüs, Panels oder Bereiche, deren Root erhalten bleiben muss
 *
 * method=get|post|put|patch|delete
 *   Default-HTTP-Methode.
 *
 *   Wenn nicht angegeben:
 *   - Links    → GET
 *   - Form/Button → POST
 *
 * params=a=1&b=2
 *   Default-Zusatzparameter dieser AJAX-Instanz.
 *   Diese werden mit Form-/Element-Parametern zusammengeführt.
 *
 * jsonreplace=1
 *   Nur für mode=json relevant.
 *
 *   Standard:
 *   - jsonreplace=0
 *
 *   Wenn aktiviert:
 *   - ajax.js erwartet HTML innerhalb des JSON-Responses
 *   - dieser HTML-Teil wird in target ersetzt
 *
 * jsonkey=html
 *   Nur für mode=json + jsonreplace=1 relevant.
 *
 *   Gibt an, unter welchem Key im JSON der HTML-String erwartet wird.
 *
 *   Beispiel:
 *   Response:
 *     { "ok": 1, "html": "<div>...</div>" }
 *
 *   Dann:
 *     jsonkey=html
 *
 * textreplace=1
 *   Nur für mode=text relevant.
 *   Wenn aktiv, wird der Text direkt in das Target ersetzt.
 *
 *
 * =========================================================
 * ERLAUBTE ELEMENTE IM SCOPE
 * =========================================================
 *
 * ajax.js reagiert innerhalb des eigenen Roots auf:
 *
 * - <form>
 * - <a>
 * - <button>
 * - <input type="button">
 * - <input type="submit">
 * - <input type="image">
 *
 * Voraussetzung:
 * - Element liegt innerhalb des AJAX-Roots
 * - Elementtyp passt zu bind
 * - Element besitzt die passende class
 *
 *
 * =========================================================
 * VERERBUNG / OVERRIDE-PRINZIP
 * =========================================================
 *
 * Ziel
 * ----
 * Möglichst wenig Angaben in Templates.
 * Standardwerte kommen vom AJAX-Root oder von der Form.
 * Links/Buttons/Formulare dürfen bei Bedarf gezielt abweichen.
 *
 *
 * 1) URL-Auflösung
 * ----------------
 * Reihenfolge:
 *
 * a) explizite Element-URL
 * b) Form-URL
 * c) Lib-URL
 *
 * Erlaubte URL-Varianten am Element/Form:
 *
 * - volle URL
 * - url=form
 * - url=lib
 * - url=&...
 *
 *
 * Bedeutung von url=&...
 * ----------------------
 * Beginnt eine URL mit "&", dann wird diese NICHT als vollständige URL
 * behandelt, sondern als Erweiterung der Basis-URL.
 *
 * Basis-URL ist:
 * 1. Form-URL
 * 2. sonst Lib-URL
 *
 * Beispiel:
 *   Form action:
 *     ?dbx_modul=test&dbx_run1=list
 *
 *   Button:
 *     data-ajax-url="&dbx_run3=row_delete&rid=15"
 *
 *   Ergebnis:
 *     ?dbx_modul=test&dbx_run1=list&dbx_run3=row_delete&rid=15
 *
 *
 * 2) Target-Auflösung
 * -------------------
 * Reihenfolge:
 *
 * a) explizites Element-Target
 * b) Form-Target
 * c) Lib-Target
 *
 *
 * 3) Mode-Auflösung
 * -----------------
 * Reihenfolge:
 *
 * a) expliziter Element-Mode
 * b) Form-Mode
 * c) Lib-Mode
 *
 *
 * 4) Method-Auflösung
 * -------------------
 * Reihenfolge:
 *
 * a) explizite Element-Method
 * b) Form-Method
 * c) Lib-Method
 * d) Default nach Elementtyp
 *
 *
 * 5) Zusatzparameter
 * ------------------
 * Reihenfolge / Zusammenführung:
 *
 * a) params aus der Lib
 * b) params aus der Form
 * c) params aus dem Element
 *
 * Alle Parameter werden zusammengeführt.
 * Spätere Ebenen dürfen frühere Werte überschreiben.
 *
 *
 * =========================================================
 * DATENQUELLEN FÜR OVERRIDES
 * =========================================================
 *
 * Element/Form-spezifische Overrides können z. B. über:
 *
 * - data-ajax-url
 * - data-ajax-target
 * - data-ajax-replace
 * - data-ajax-mode
 * - data-ajax-method
 * - data-ajax-params
 * - data-ajax-jsonreplace
 * - data-ajax-jsonkey
 * - data-ajax-textreplace
 *
 * erfolgen.
 *
 * Zusätzlich werden weiterhin typische HTML-Quellen berücksichtigt:
 *
 * - form action
 * - form method
 * - button formaction
 * - button formmethod
 * - link href
 *
 * Historische Unterstützung:
 * - data-dbx_form
 * - data-dbx_target
 * - .dbxAjaxFormAction
 *
 *
 * =========================================================
 * RESPONSE-VERHALTEN
 * =========================================================
 *
 * mode=html
 * ---------
 * Standardfall in DBX.
 *
 * Ablauf:
 * - Response als HTML lesen
 * - Target ersetzen oder bei replace=inner nur Target-Inhalt ersetzen
 * - <script>-Tags ausführen
 * - dbx.scan(newElement) auf neuem Inhalt ausführen
 *
 *
 * mode=json
 * ---------
 * Standardverhalten:
 * - Response als JSON lesen
 * - KEIN automatischer DOM-Replace
 * - Ergebnis an aufrufende Logik zurückgeben
 *
 * Optional:
 * - mit jsonreplace=1 kann HTML aus einem JSON-Key gelesen
 *   und in target ersetzt werden
 *
 *
 * mode=text
 * ---------
 * Standardverhalten:
 * - Response als Text lesen
 * - kein automatischer Replace
 *
 * Optional:
 * - mit textreplace=1 wird Text in target ersetzt
 *
 *
 * =========================================================
 * EVENTS
 * =========================================================
 *
 * ajax.js sendet DBX-Events über dbx.event.emit():
 *
 * ajax:before
 *   Vor dem Request
 *
 * ajax:after
 *   Nach erfolgreichem Request / ggf. nach Replace
 *
 * ajax:error
 *   Bei Request- oder Parse-Fehlern
 *
 * Typische Daten:
 * - id
 * - root
 * - source
 * - form
 * - type
 * - mode
 * - method
 * - url
 * - target
 * - params
 * - response / error
 *
 *
 * =========================================================
 * LOCK / DOUBLE SUBMIT PROTECTION
 * =========================================================
 *
 * ajax.js verhindert parallele doppelte Ausführungen auf derselben Quelle
 * (Form oder auslösendes Element), solange ein Request läuft.
 *
 * Zusätzlich erhält das aktive Target während des Requests:
 *
 *   class="dbx-ajax-loading"
 *
 *
 * =========================================================
 * VERWENDUNGSBEISPIELE
 * =========================================================
 *
 * 1) Klassischer Report mit HTML-Replace
 * --------------------------------------
 *
 *   <div id="dbx_target_7"
 *        data-dbx="lib=ajax|class=dbxAjax|mode=html|url=form|target=dbx_target_7">
 *
 *     <form action="?dbx_modul=test&dbx_run1=list"
 *           class="dbxAjax">
 *       ...
 *     </form>
 *   </div>
 *
 * Verhalten:
 * - Submit lädt HTML
 * - dbx_target_7 wird ersetzt
 *
 *
 * 2) Pagination-Link im selben Report
 * -----------------------------------
 *
 *   <a href="?dbx_modul=test&dbx_run1=list&dbx_rpos=20"
 *      class="dbxAjax">Weiter</a>
 *
 * Verhalten:
 * - Link läuft über dieselbe ajax-Instanz
 * - Target wird ersetzt
 *
 *
 * 3) Button ergänzt nur Parameter an Form-URL
 * -------------------------------------------
 *
 *   <button class="dbxAjax"
 *           data-ajax-url="&dbx_run3=row_delete&rid=15">
 *     Löschen
 *   </button>
 *
 * Verhalten:
 * - Basis-URL kommt von der Form
 * - Parameter werden angehängt
 *
 *
 * 4) UI-AJAX mit JSON
 * -------------------
 *
 *   <div data-dbx="lib=ajax|class=dbxUiAjax|mode=json|bind=button,link">
 *     <button class="dbxUiAjax"
 *             data-ajax-url="&dbx_mode=report_select&dbx_select_action=row">
 *       Select
 *     </button>
 *   </div>
 *
 * Verhalten:
 * - Response wird als JSON verarbeitet
 * - kein automatischer Replace
 *
 *
 * 5) JSON mit explizitem Replace
 * ------------------------------
 *
 *   <div id="panel"
 *        data-dbx="lib=ajax|class=dbxUiAjax|mode=json|target=panel|jsonreplace=1|jsonkey=html">
 *     ...
 *   </div>
 *
 * Erwartete JSON-Antwort:
 *   {
 *     "ok": 1,
 *     "html": "<div>Neuer Inhalt</div>"
 *   }
 *
 *
 * =========================================================
 * WICHTIGE REGELN
 * =========================================================
 *
 * - ajax.js ist immer scoped, nie global
 * - HTML-Replace bleibt DBX-Standard
 * - JSON ist für UI-/Spezialfälle gedacht
 * - confirm gehört NICHT in ajax.js
 * - confirm ist eigene Fachlib (confirm.js)
 * - Fachlogik gehört nicht in ajax.js
 * - URL/Target/Mode sollen möglichst geerbt werden
 * - nur Abweichungen werden lokal angegeben
 *
 * =========================================================
 */




(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][ajax] dbx core missing");
        return;
    }

    const dbx = window.dbx;

    dbx.ajax = dbx.ajax || {};


    /* =========================================================
     * HELPERS
     * ========================================================= */

    function bool(v, def = false) {

        if (v === undefined || v === null || v === "") return def;
        if (v === true || v === 1 || v === "1" || v === "on" || v === "true") return true;
        if (v === false || v === 0 || v === "0" || v === "off" || v === "false") return false;

        return def;
    }


    function normalizeMode(v) {

        const mode = String(v || "html").toLowerCase().trim();

        if (mode === "json") return "json";
        if (mode === "text") return "text";

        return "html";
    }


    function normalizeReplaceMode(v) {

        const mode = String(v || "target").toLowerCase().trim();

        if (mode === "inner" || mode === "innerhtml" || mode === "html" || mode === "content") {
            return "inner";
        }

        return "target";
    }


    function normalizeMethod(v, def = "POST") {

        const method = String(v || def || "POST").toUpperCase().trim();

        if (method === "GET") return "GET";
        if (method === "POST") return "POST";
        if (method === "PUT") return "PUT";
        if (method === "PATCH") return "PATCH";
        if (method === "DELETE") return "DELETE";

        return String(def || "POST").toUpperCase();
    }


    function isPlainObject(value) {
        return value && Object.prototype.toString.call(value) === "[object Object]";
    }


    function appendDataToUrl(url, data) {
        if (!data || !isPlainObject(data)) return url;

        const out = new URL(String(url), window.location.href);
        Object.keys(data).forEach(key => {
            const value = data[key];
            if (value === undefined || value === null) return;
            if (Array.isArray(value)) {
                value.forEach(item => out.searchParams.append(key, item));
            } else {
                out.searchParams.set(key, value);
            }
        });
        return out.toString();
    }


    function bodyFromData(data) {
        if (!data) return null;
        if (window.FormData && data instanceof FormData) return data;
        if (window.URLSearchParams && data instanceof URLSearchParams) return data;
        if (typeof Blob !== "undefined" && data instanceof Blob) return data;
        if (typeof data === "string") return data;
        if (!isPlainObject(data)) return data;

        const body = new URLSearchParams();
        Object.keys(data).forEach(key => {
            const value = data[key];
            if (value === undefined || value === null) return;
            if (Array.isArray(value)) {
                value.forEach(item => body.append(key, item));
            } else {
                body.set(key, value);
            }
        });
        return body;
    }


    function normalizeBind(v) {

        if (v === undefined || v === null || v === "") {
            return ["form", "link", "button"];
        }

        const raw = String(v).toLowerCase().trim();

        if (raw === "*" || raw === "all") {
            return ["form", "link", "button"];
        }

        return raw
            .split(",")
            .map(s => s.trim())
            .filter(Boolean);
    }


    function normalizeClassFilter(v) {

        if (v === undefined || v === null || v === "") {
            return ["dbxAjax"];
        }

        const raw = String(v).trim();

        if (raw === "*") {
            return ["*"];
        }

        return raw
            .split(",")
            .map(s => s.trim())
            .filter(Boolean);
    }


    function elementType(el) {

        if (!el || !el.tagName) return "";

        const tag = el.tagName.toLowerCase();

        if (tag === "form") return "form";
        if (tag === "a") return "link";

        if (tag === "button") return "button";

        if (tag === "input") {
            const type = String(el.getAttribute("type") || "text").toLowerCase();
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

        if (!Array.isArray(classList) || !classList.length) {
            return false;
        }

        if (classList.includes("*")) {
            return true;
        }

        for (let i = 0; i < classList.length; i++) {
            if (el.classList.contains(classList[i])) {
                return true;
            }
        }

        return false;
    }


    function readAttr(el, name) {

        if (!el || !el.getAttribute) return "";

        const v = el.getAttribute(name);
        return v == null ? "" : String(v).trim();
    }


    function readDataAjax(el, key) {

        if (!el) return "";

        return readAttr(el, "data-ajax-" + key);
    }


    function readLegacyData(el, key) {

        if (!el) return "";

        return readAttr(el, "data-" + key);
    }


    function parseParamString(str) {

        const out = [];

        if (str === undefined || str === null) {
            return out;
        }

        let raw = String(str).trim();

        if (!raw) {
            return out;
        }

        raw = raw.replace(/^[?&]+/, "");

        if (!raw) {
            return out;
        }

        const usp = new URLSearchParams(raw);

        usp.forEach((value, key) => {
            out.push([key, value]);
        });

        return out;
    }


    function appendToUrl(baseUrl, extra) {

        let base = String(baseUrl || "").trim();
        let add  = String(extra || "").trim();

        if (!add) return base;
        if (!base) return "";

        add = add.replace(/^[?&]+/, "");

        if (!add) return base;

        const hashPos = base.indexOf("#");
        let hash = "";

        if (hashPos !== -1) {
            hash = base.substring(hashPos);
            base = base.substring(0, hashPos);
        }

        const sep = base.includes("?") ? "&" : "?";

        return base + sep + add + hash;
    }


    function ensureAjaxFlag(url) {

        let finalUrl = String(url || "").trim();

        if (!finalUrl) return finalUrl;

        if (finalUrl.indexOf("dbx_ajax=") === -1) {
            finalUrl = appendToUrl(finalUrl, "dbx_ajax=1");
        }

        return finalUrl;
    }

    // Zentrale Stelle fuer das AJAX-Kennzeichen. Andere Module liefern
    // grundsaetzlich normale, auch direkt aufrufbare URLs.
    dbx.ajax.url = ensureAjaxFlag;

    function isPrivateNetworkHost(hostname) {
        const host = String(hostname || "").trim().toLowerCase().replace(/^\[|\]$/g, "");

        if (!host) return false;
        if (host === "localhost" || host === "localhost.localdomain" || host.endsWith(".localhost")) return true;
        if (host === "::1") return true;
        if (host.startsWith("fe80:") || host.startsWith("fc") || host.startsWith("fd")) return true;

        const m = host.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
        if (!m) return false;

        const a = Number(m[1]);
        const b = Number(m[2]);

        if (a === 10 || a === 127) return true;
        if (a === 169 && b === 254) return true;
        if (a === 172 && b >= 16 && b <= 31) return true;
        if (a === 192 && b === 168) return true;

        return false;
    }

    function shouldBlockLocalNetworkUrl(parsed) {
        if (!parsed || (parsed.protocol !== "http:" && parsed.protocol !== "https:")) return false;
        if (parsed.origin === window.location.origin) return false;
        if (isPrivateNetworkHost(window.location.hostname)) return false;
        return isPrivateNetworkHost(parsed.hostname);
    }


    function normalizeRequestUrl(url) {

        const raw = String(url || "").trim();

        if (!raw) return raw;

        try {
            const parsed = new URL(raw, window.location.href);

            if (shouldBlockLocalNetworkUrl(parsed)) {
                throw new Error("blocked_local_network_request");
            }

            if (
                (parsed.protocol === "http:" || parsed.protocol === "https:") &&
                parsed.hostname === window.location.hostname &&
                parsed.port === window.location.port &&
                parsed.protocol !== window.location.protocol
            ) {
                parsed.protocol = window.location.protocol;
            }

            if (parsed.origin === window.location.origin) {
                return parsed.pathname + parsed.search + parsed.hash;
            }

            return parsed.toString();
        } catch (e) {
            if (e && e.message === "blocked_local_network_request") {
                throw e;
            }
            return raw;
        }
    }


    function resolveElementByTarget(target) {

        const raw = String(target || "").trim();

        if (!raw) return null;

        let el = document.getElementById(raw);
        if (el) return el;

        try {
            el = document.querySelector(raw);
            if (el) return el;
        } catch (e) {
            dbx.warn("[dbx.ajax] invalid target selector:", raw);
        }

        return null;
    }


    function getClosestForm(source, root) {

        if (!source) return null;

        if (source.tagName && source.tagName.toLowerCase() === "form") {
            return source;
        }

        const dataFormId = readLegacyData(source, "dbx_form");
        if (dataFormId) {
            const formByDataId = document.getElementById(dataFormId);
            if (formByDataId) {
                return formByDataId;
            }
        }

        const formAttr = readAttr(source, "form");
        if (formAttr) {
            const formByAttr = document.getElementById(formAttr);
            if (formByAttr) {
                return formByAttr;
            }
        }

        const form = source.closest("form");

        if (!form) {
            return null;
        }

        if (root && !root.contains(form)) {
            return null;
        }

        return form;
    }


    function getRootConfigs(root) {

        if (!root) return [];

        if (Array.isArray(root._dbxAjaxConfigs)) {
            return root._dbxAjaxConfigs;
        }

        let out = [];

        if (dbx.declare && dbx.declare.schemas && dbx.declare.schemas.ajax) {
            out = dbx.declare.resolve("ajax", root);
        } else {
            const attr = readAttr(root, "data-dbx");
            const list = dbx.parseData(attr).filter(cfg => cfg.lib === "ajax");

            out = list.map((cfg, index) => {
                return {
                    _index: index,
                    lib: "ajax",
                    class: normalizeClassFilter(cfg.class),
                    mode: normalizeMode(cfg.mode || "html"),
                    url: String(cfg.url || "form").trim(),
                    target: String(cfg.target || "").trim(),
                    replace: normalizeReplaceMode(cfg.replace || "target"),
                    bind: normalizeBind(cfg.bind),
                    method: normalizeMethod(cfg.method || ""),
                    params: String(cfg.params || "").trim(),
                    jsonreplace: bool(cfg.jsonreplace, false),
                    jsonkey: String(cfg.jsonkey || "html").trim(),
                    textreplace: bool(cfg.textreplace, false)
                };
            });

            if (!out.length) {
                out.push({
                    _index: 0,
                    lib: "ajax",
                    class: normalizeClassFilter("dbxAjax"),
                    mode: "html",
                    url: "form",
                    target: "",
                    replace: "target",
                    bind: ["form", "link", "button"],
                    method: "",
                    params: "",
                    jsonreplace: false,
                    jsonkey: "html",
                    textreplace: false
                });
            }
        }

        root._dbxAjaxConfigs = out;

        return out;
    }


    function findMatchingConfig(root, source, type, overrides) {

        if (overrides && overrides.config) {
            return overrides.config;
        }

        const configs = getRootConfigs(root);

        for (let i = 0; i < configs.length; i++) {

            const cfg = configs[i];

            if (!bindMatches(type, cfg.bind)) {
                continue;
            }

            if (!classMatches(source, cfg.class)) {
                continue;
            }

            return cfg;
        }

        return null;
    }


    function getFormDefaultUrl(form) {

        if (!form) return "";

        const action = readAttr(form, "action");

        if (action) {
            return action;
        }

        return window.location.href;
    }


    function getLibDefaultUrl(cfg, form) {

        if (!cfg) return "";

        const url = String(cfg.url || "").trim();

        if (!url) return "";

        if (url === "form") {
            return getFormDefaultUrl(form);
        }

        if (url === "lib") {
            return "";
        }

        if (url.charAt(0) === "&") {
            const base = getFormDefaultUrl(form);
            return base ? appendToUrl(base, url) : "";
        }

        return url;
    }


    function resolveUrl(root, source, form, cfg, overrides) {

        const libUrl = getLibDefaultUrl(cfg, form);

        const formUrlOverride = readDataAjax(form, "url");
        const sourceUrlOverride =
            (overrides && overrides.url) ||
            readDataAjax(source, "url") ||
            readAttr(source, "formaction") ||
            ((elementType(source) === "link") ? readAttr(source, "href") : "");

        const formBase = formUrlOverride
            ? resolveUrlToken(formUrlOverride, getFormDefaultUrl(form), libUrl)
            : getFormDefaultUrl(form);

        if (sourceUrlOverride) {
            return resolveUrlToken(sourceUrlOverride, formBase, libUrl);
        }

        if (elementType(source) === "form") {
            return formBase || libUrl;
        }

        if (form) {
            return formBase || libUrl;
        }

        return libUrl;
    }


    function resolveUrlToken(token, formUrl, libUrl) {

        const raw = String(token || "").trim();

        if (!raw) {
            return "";
        }

        if (raw === "form") {
            return formUrl || libUrl || "";
        }

        if (raw === "lib") {
            return libUrl || formUrl || "";
        }

        if (raw.charAt(0) === "&") {
            const base = formUrl || libUrl || "";
            if (!base) {
                return "";
            }
            return appendToUrl(base, raw);
        }

        return raw;
    }


    function resolveTarget(root, source, form, cfg, overrides) {

        const sourceTarget =
            (overrides && overrides.target) ||
            readDataAjax(source, "target") ||
            readLegacyData(source, "dbx_target");

        if (sourceTarget) {
            return sourceTarget;
        }

        const formTarget = readDataAjax(form, "target");
        if (formTarget) {
            return formTarget;
        }

        if (cfg && cfg.target) {
            return cfg.target;
        }

        return "";
    }


    function resolveReplaceMode(source, form, cfg, overrides) {

        const sourceReplace =
            (overrides && overrides.replace) ||
            readDataAjax(source, "replace");

        if (sourceReplace) {
            return normalizeReplaceMode(sourceReplace);
        }

        const formReplace = readDataAjax(form, "replace");
        if (formReplace) {
            return normalizeReplaceMode(formReplace);
        }

        return normalizeReplaceMode((cfg && cfg.replace) || "target");
    }


    function resolveMode(source, form, cfg, overrides) {

        const sourceMode =
            (overrides && overrides.mode) ||
            readDataAjax(source, "mode");

        if (sourceMode) {
            return normalizeMode(sourceMode);
        }

        const formMode = readDataAjax(form, "mode");
        if (formMode) {
            return normalizeMode(formMode);
        }

        return normalizeMode((cfg && cfg.mode) || "html");
    }


    function resolveMethod(source, form, type, cfg, overrides) {

        const sourceMethod =
            (overrides && overrides.method) ||
            readDataAjax(source, "method") ||
            readAttr(source, "formmethod");

        if (sourceMethod) {
            return normalizeMethod(sourceMethod, type === "link" ? "GET" : "POST");
        }

        const formMethod =
            readDataAjax(form, "method") ||
            readAttr(form, "method");

        if (formMethod) {
            return normalizeMethod(formMethod, type === "link" ? "GET" : "POST");
        }

        if (cfg && cfg.method) {
            return normalizeMethod(cfg.method, type === "link" ? "GET" : "POST");
        }

        return normalizeMethod(type === "link" ? "GET" : "POST");
    }


    function resolveJsonReplace(source, form, cfg, overrides) {

        if (overrides && typeof overrides.jsonreplace !== "undefined") {
            return bool(overrides.jsonreplace, false);
        }

        const sourceVal = readDataAjax(source, "jsonreplace");
        if (sourceVal !== "") {
            return bool(sourceVal, false);
        }

        const formVal = readDataAjax(form, "jsonreplace");
        if (formVal !== "") {
            return bool(formVal, false);
        }

        return bool(cfg && cfg.jsonreplace, false);
    }


    function resolveJsonKey(source, form, cfg, overrides) {

        if (overrides && overrides.jsonkey) {
            return String(overrides.jsonkey).trim();
        }

        const sourceVal = readDataAjax(source, "jsonkey");
        if (sourceVal) {
            return String(sourceVal).trim();
        }

        const formVal = readDataAjax(form, "jsonkey");
        if (formVal) {
            return String(formVal).trim();
        }

        return String((cfg && cfg.jsonkey) || "html").trim();
    }


    function resolveTextReplace(source, form, cfg, overrides) {

        if (overrides && typeof overrides.textreplace !== "undefined") {
            return bool(overrides.textreplace, false);
        }

        const sourceVal = readDataAjax(source, "textreplace");
        if (sourceVal !== "") {
            return bool(sourceVal, false);
        }

        const formVal = readDataAjax(form, "textreplace");
        if (formVal !== "") {
            return bool(formVal, false);
        }

        return bool(cfg && cfg.textreplace, false);
    }


    function collectExtraParams(source, form, cfg, overrides) {

        const out = [];

        if (cfg && cfg.params) {
            out.push(...parseParamString(cfg.params));
        }

        const formParams = readDataAjax(form, "params");
        if (formParams) {
            out.push(...parseParamString(formParams));
        }

        const sourceParams =
            (overrides && overrides.params) ||
            readDataAjax(source, "params");

        if (sourceParams) {
            out.push(...parseParamString(sourceParams));
        }

        return out;
    }


    function applyParamsToFormData(fd, params) {

        if (!fd || !params || !params.length) return;

        params.forEach(([key, value]) => {
            try {
                fd.set(key, value);
            } catch (e) {
                fd.append(key, value);
            }
        });
    }


    function applyParamsToUrl(url, params) {

        let finalUrl = String(url || "").trim();

        if (!finalUrl) {
            return finalUrl;
        }

        if (!params || !params.length) {
            return finalUrl;
        }

        const hashPos = finalUrl.indexOf("#");
        let hash = "";

        if (hashPos !== -1) {
            hash = finalUrl.substring(hashPos);
            finalUrl = finalUrl.substring(0, hashPos);
        }

        let qs = "";

        params.forEach(([key, value], idx) => {
            const pair = encodeURIComponent(key) + "=" + encodeURIComponent(value);
            qs += (idx === 0 ? "" : "&") + pair;
        });

        if (qs) {
            finalUrl = appendToUrl(finalUrl, qs);
        }

        return finalUrl + hash;
    }


    function buildBodyAndUrl(ctx) {

        let url = String(ctx.url || "").trim();
        let body = null;

        const params = Array.isArray(ctx.params) ? ctx.params.slice() : [];

        if (ctx.method === "GET") {

            if (ctx.form) {
                const fd = new FormData(ctx.form);
                fd.forEach((value, key) => {
                    params.push([key, value]);
                });
            }

            if (ctx.submitName) {
                params.push([ctx.submitName, ctx.submitValue]);
            }

            url = applyParamsToUrl(url, params);

            return {
                url: url,
                body: null
            };
        }

        if (ctx.form) {
            body = new FormData(ctx.form);
        } else {
            body = new FormData();
        }

        if (ctx.submitName) {
            body.set(ctx.submitName, ctx.submitValue);
        }

        applyParamsToFormData(body, params);

        return {
            url: url,
            body: body
        };
    }


    function emit(name, data) {

        if (dbx.event && typeof dbx.event.emit === "function") {
            dbx.event.emit(name, data);
        }
    }


    function executeScripts(scripts) {

        if (!scripts || !scripts.length) {
            return Promise.resolve();
        }

        let chain = Promise.resolve();

        scripts.forEach(srcNode => {

            chain = chain.then(() => {
                return new Promise((resolve) => {

                    const script = document.createElement("script");

                    for (let i = 0; i < srcNode.attributes.length; i++) {
                        const attr = srcNode.attributes[i];
                        if (attr.name === "src") continue;
                        script.setAttribute(attr.name, attr.value);
                    }

                    if (srcNode.src) {
                        script.src = srcNode.src;
                        script.async = false;
                        script.onload = () => resolve();
                        script.onerror = () => {
                            dbx.error("[dbx.ajax] script load failed:", srcNode.src);
                            resolve();
                        };
                        document.body.appendChild(script);
                    } else {
                        script.text = srcNode.textContent || "";
                        document.body.appendChild(script);
                        resolve();
                    }
                });
            });
        });

        return chain;
    }


    function replaceTarget(targetEl, html, replaceMode = "target") {

        if (!targetEl) {
            return Promise.resolve(targetEl);
        }

        if (html === undefined || html === null) {
            return Promise.resolve(targetEl);
        }

        const markup = String(html).trim();

        if (!markup) {
            return Promise.resolve(targetEl);
        }

        const temp = document.createElement("div");
        temp.innerHTML = markup;

        const scripts = Array.from(temp.querySelectorAll("script"));
        scripts.forEach(s => s.remove());

        let newElement = targetEl;
        const mode = normalizeReplaceMode(replaceMode);

        if (mode === "inner") {

            targetEl.innerHTML = temp.innerHTML;
            newElement = targetEl;

        } else if (temp.children.length === 1) {

            const candidate = temp.firstElementChild;

            if (candidate && targetEl.parentNode) {
                targetEl.parentNode.replaceChild(candidate, targetEl);
                newElement = candidate;
            } else {
                targetEl.innerHTML = temp.innerHTML;
                newElement = targetEl;
            }

        } else {

            targetEl.innerHTML = temp.innerHTML;
            newElement = targetEl;
        }

        return executeScripts(scripts).then(() => {

            if (newElement && typeof dbx.scan === "function") {
                dbx.scan(newElement);
            }

            return newElement;
        });
    }


    function requestNow() {
        return window.performance && typeof window.performance.now === "function"
            ? window.performance.now()
            : Date.now();
    }


    function requestElapsedSeconds(startedAt) {
        return Math.max(0, (requestNow() - startedAt) / 1000);
    }


    function parseRuntimeSeconds(value) {
        const number = Number(value);
        return Number.isFinite(number) && number >= 0 ? number : null;
    }


    function phpRuntimeFromResponse(response, data) {
        if (response && response.headers && typeof response.headers.get === "function") {
            const headerValue = parseRuntimeSeconds(response.headers.get("X-DBX-PHP-Runtime"));
            if (headerValue !== null) {
                return headerValue;
            }
        }

        if (data && typeof data === "object") {
            const directValue = parseRuntimeSeconds(data.dbx_php_runtime || data.php_runtime);
            if (directValue !== null) {
                return directValue;
            }

            if (data._dbx_runtime && typeof data._dbx_runtime === "object") {
                const nestedValue = parseRuntimeSeconds(data._dbx_runtime.php);
                if (nestedValue !== null) {
                    return nestedValue;
                }
            }
        }

        return null;
    }


    function shouldTrackRuntime(options, url, body) {
        if (dbx.footerStatus && typeof dbx.footerStatus.shouldTrackAjaxRuntime === "function") {
            return dbx.footerStatus.shouldTrackAjaxRuntime(url, body, options);
        }

        if (options && (
            options.skipRuntime === true ||
            options.footerRuntime === 'hidden' ||
            options.footerRuntime === 'skip' ||
            options.footerRuntime === '0'
        )) {
            return false;
        }

        if (options && String(options.mode || "").toLowerCase() === "json") {
            return false;
        }

        const targetUrl = String(url || '');
        if (/[?&]dbx_sync=0(?:&|$)/.test(targetUrl)) {
            return false;
        }

        if (body instanceof URLSearchParams && body.get('dbx_sync') === '0') {
            return false;
        }

        if (typeof body === 'string' && /(?:^|&)dbx_sync=0(?:&|$)/.test(body)) {
            return false;
        }

        return true;
    }


    function updateFooterRuntime(startedAt, response, data) {
        if (!dbx.footerStatus || typeof dbx.footerStatus.updateAjax !== "function") {
            return;
        }

        dbx.footerStatus.updateAjax(
            requestElapsedSeconds(startedAt),
            phpRuntimeFromResponse(response, data)
        );
    }


    /* =========================================================
     * PUBLIC CORE REQUEST
     * ========================================================= */

    dbx.ajax.request = function (options) {

        const method = normalizeMethod(options && options.method, "POST");
        const mode   = normalizeMode(options && options.mode);
        let url       = String((options && options.url) || "").trim();
        let body      = (options && typeof options.body !== "undefined") ? options.body : null;
        const headers = (options && options.headers) ? options.headers : {};
        const data    = (options && typeof options.data !== "undefined") ? options.data : null;
        const timeout = Math.max(0, Number((options && options.timeout) || 0));
        const keepalive = bool(options && options.keepalive, false);
        const controller = timeout > 0 && window.AbortController ? new AbortController() : null;
        let timer = null;
        let runtimeUpdated = false;
        const startedAt = requestNow();

        if (!url) {
            return Promise.reject(new Error("missing_url"));
        }

        if ((method === "GET" || method === "HEAD") && data) {
            url = appendDataToUrl(url, data);
        } else if (body === null && data) {
            body = bodyFromData(data);
        }

        url = ensureAjaxFlag(url);

        try {
            url = normalizeRequestUrl(url);
        } catch (err) {
            dbx.warn("[dbx.ajax] local network request blocked", url);
            return Promise.reject(err);
        }

        const trackRuntime = shouldTrackRuntime(options, url, body);

        function markRuntime(response, responseData) {
            if (!trackRuntime) return;
            runtimeUpdated = true;
            updateFooterRuntime(startedAt, response, responseData);
        }

        dbx.log("[dbx.ajax] request start", {
            method: method,
            mode: mode,
            url: url
        });

        if (controller) {
            timer = window.setTimeout(() => controller.abort(), timeout);
        }

        return fetch(url, {
            method: method,
            body: body,
            headers: headers,
            credentials: "same-origin",
            keepalive: keepalive,
            signal: controller ? controller.signal : undefined
        })
        .then(response => {

            if (!response.ok) {
                markRuntime(response, null);
                throw new Error("HTTP " + response.status);
            }

            if (mode === "json") {
                return response.text().then(txt => {
                    if (!txt) {
                        const emptyData = {};
                        markRuntime(response, emptyData);
                        return emptyData;
                    }

                    try {
                        const data = JSON.parse(txt);
                        markRuntime(response, data);
                        return data;
                    } catch (err) {
                        const clean = String(txt || "")
                            .replace(/<script[\s\S]*?<\/script>/gi, "")
                            .replace(/<style[\s\S]*?<\/style>/gi, "")
                            .replace(/<[^>]+>/g, " ")
                            .replace(/\s+/g, " ")
                            .trim();
                        markRuntime(response, null);
                        throw new Error(clean || "Ungueltige Serverantwort");
                    }
                });
            }

            return response.text().then(txt => {
                markRuntime(response, txt);
                return txt;
            });
        })
        .catch(error => {
            if (!runtimeUpdated && trackRuntime) {
                updateFooterRuntime(startedAt, null, null);
            }

            throw error;
        })
        .finally(() => {
            if (timer) window.clearTimeout(timer);
        });
    };


    /* =========================================================
     * INTERNAL EXECUTE
     * ========================================================= */

    function executeAjax(root, source, overrides = {}, event) {

        if (!root) {
            dbx.warn("[dbx.ajax] missing root");
            return Promise.resolve(null);
        }

        const type = elementType(source);

        if (!type) {
            dbx.warn("[dbx.ajax] unsupported source:", source);
            return Promise.resolve(null);
        }

        const cfg = findMatchingConfig(root, source, type, overrides);

        if (!cfg && !overrides.url) {
            dbx.log("[dbx.ajax] no matching config", {
                type: type,
                source: source
            });
            return Promise.resolve(null);
        }

        const form = (overrides && overrides.form) || getClosestForm(source, root);

        /*
         * Bei einem nativen Submit ist die eigentliche Schaltfläche über
         * SubmitEvent.submitter verfügbar. Sie muss separat erhalten bleiben,
         * weil new FormData(form) ihren name/value-Wert nicht übernimmt.
         */
        const eventSubmitter = event && event.submitter ? event.submitter : null;
        const overrideSubmitter = overrides && overrides.submitSource
            ? overrides.submitSource
            : null;
        const submitSource = overrideSubmitter || eventSubmitter || ((type === "button") ? source : null);
        const submitName = String(
            (overrides && overrides.submitName) ||
            readAttr(submitSource, "name") ||
            ""
        );
        const submitValueOverride = overrides && Object.prototype.hasOwnProperty.call(overrides, "submitValue")
            ? overrides.submitValue
            : null;
        const submitValue = submitValueOverride !== null
            ? String(submitValueOverride)
            : String(readAttr(submitSource, "value") || "");

        const ctx = {
            root: root,
            source: source,
            type: type,
            form: form,
            cfg: cfg || {},
            submitSource: submitSource,
            submitName: submitName,
            submitValue: submitValue
        };

        ctx.mode        = resolveMode(source, form, cfg, overrides);
        ctx.method      = resolveMethod(source, form, type, cfg, overrides);
        ctx.url         = resolveUrl(root, source, form, cfg, overrides);
        ctx.target      = resolveTarget(root, source, form, cfg, overrides);
        ctx.replace     = resolveReplaceMode(source, form, cfg, overrides);
        ctx.params      = collectExtraParams(source, form, cfg, overrides);
        ctx.jsonreplace = resolveJsonReplace(source, form, cfg, overrides);
        ctx.jsonkey     = resolveJsonKey(source, form, cfg, overrides);
        ctx.textreplace = resolveTextReplace(source, form, cfg, overrides);

        if (event && typeof event.preventDefault === "function") {
            event.preventDefault();
        }

        if (!ctx.url) {
            dbx.warn("[dbx.ajax] missing resolved url", {
                type: ctx.type,
                source: ctx.source
            });
            return Promise.resolve(null);
        }

        const lockEl = ctx.form || ctx.source;

        if (lockEl && lockEl._dbxAjaxRunning === true) {
            dbx.log("[dbx.ajax] request skipped (already running)");
            return Promise.resolve(null);
        }

        if (lockEl) {
            lockEl._dbxAjaxRunning = true;
        }

        const targetEl = resolveElementByTarget(ctx.target);

        if (ctx.mode === "html" && !targetEl) {
            dbx.warn("[dbx.ajax] target not found for html mode:", ctx.target);
            if (lockEl) {
                lockEl._dbxAjaxRunning = false;
            }
            return Promise.resolve(null);
        }

        let loadingTimer = null;
        if (targetEl) {
            targetEl.classList.add("dbx-ajax-loading");
            loadingTimer = window.setTimeout(() => {
                targetEl.classList.remove("dbx-ajax-loading", "is-loading");
            }, 20000);
        }

        const built = buildBodyAndUrl(ctx);

        ctx.url  = built.url;
        ctx.body = built.body;

        const eventData = {
            id: root.id || "",
            root: root,
            source: source,
            form: form,
            type: ctx.type,
            mode: ctx.mode,
            method: ctx.method,
            url: ctx.url,
            target: ctx.target,
            replace: ctx.replace,
            params: ctx.params
        };

        emit("ajax:before", eventData);

        return dbx.ajax.request({
            url: ctx.url,
            method: ctx.method,
            mode: ctx.mode,
            body: ctx.body
        })
        .then(responseData => {

            if (ctx.mode === "html") {

                return replaceTarget(targetEl, responseData, ctx.replace).then(newTarget => {

                    emit("ajax:after", {
                        ...eventData,
                        response: responseData,
                        targetElement: newTarget
                    });

                    return {
                        ok: true,
                        mode: ctx.mode,
                        response: responseData,
                        targetElement: newTarget
                    };
                });
            }

            if (ctx.mode === "json") {

                if (ctx.jsonreplace === true && targetEl) {

                    let html = "";

                    if (responseData && typeof responseData === "object") {
                        html = responseData[ctx.jsonkey] || "";
                    }

                    if (typeof html === "string" && html !== "") {
                        return replaceTarget(targetEl, html, ctx.replace).then(newTarget => {

                            emit("ajax:after", {
                                ...eventData,
                                response: responseData,
                                targetElement: newTarget
                            });

                            return {
                                ok: true,
                                mode: ctx.mode,
                                response: responseData,
                                targetElement: newTarget
                            };
                        });
                    }
                }

                emit("ajax:after", {
                    ...eventData,
                    response: responseData,
                    targetElement: targetEl
                });

                return {
                    ok: true,
                    mode: ctx.mode,
                    response: responseData,
                    targetElement: targetEl
                };
            }

            if (ctx.mode === "text") {

                if (ctx.textreplace === true && targetEl) {
                    return replaceTarget(targetEl, responseData, ctx.replace).then(newTarget => {

                        emit("ajax:after", {
                            ...eventData,
                            response: responseData,
                            targetElement: newTarget
                        });

                        return {
                            ok: true,
                            mode: ctx.mode,
                            response: responseData,
                            targetElement: newTarget
                        };
                    });
                }

                emit("ajax:after", {
                    ...eventData,
                    response: responseData,
                    targetElement: targetEl
                });

                return {
                    ok: true,
                    mode: ctx.mode,
                    response: responseData,
                    targetElement: targetEl
                };
            }

            emit("ajax:after", {
                ...eventData,
                response: responseData,
                targetElement: targetEl
            });

            return {
                ok: true,
                mode: ctx.mode,
                response: responseData,
                targetElement: targetEl
            };
        })
        .catch(error => {

            dbx.error("[dbx.ajax] error:", error);

            emit("ajax:error", {
                ...eventData,
                error: error
            });

            throw error;
        })
        .finally(() => {

            if (lockEl) {
                lockEl._dbxAjaxRunning = false;
            }

            if (loadingTimer) {
                window.clearTimeout(loadingTimer);
            }

            if (targetEl) {
                targetEl.classList.remove("dbx-ajax-loading", "is-loading");
            }

            const finalTargetEl = resolveElementByTarget(ctx.target);
            if (finalTargetEl) {
                finalTargetEl.classList.remove("dbx-ajax-loading", "is-loading");
            }
        });
    }


    /* =========================================================
     * PUBLIC EXECUTE
     * ========================================================= */

    dbx.ajax.run = function (root, source, overrides = {}, event) {
        return executeAjax(root, source, overrides, event);
    };


    /* =========================================================
     * DECLARE SCHEMA (Defaults + data-* Aliase)
     * ========================================================= */

    if (dbx.declare && typeof dbx.declare.registerSchema === "function") {

        dbx.declare.registerSchema("ajax", {
            fields: {
                class: {
                    default: "dbxAjax"
                },
                mode: {
                    default: "html",
                    aliases: ["data-ajax-mode"]
                },
                url: {
                    default: "form",
                    aliases: ["data-ajax-url", "data-url"]
                },
                target: {
                    default: "",
                    aliases: ["data-ajax-target", "data-dbx_target", "data-target"],
                    infer: function (el, ctx) {
                        return dbx.declare.infer.ajaxTarget(ctx.root || el);
                    }
                },
                replace: {
                    default: "target",
                    aliases: ["data-ajax-replace"]
                },
                bind: {
                    default: "form,link,button",
                    aliases: ["data-ajax-bind"]
                },
                method: {
                    default: "",
                    aliases: ["data-ajax-method"]
                },
                params: {
                    default: "",
                    aliases: ["data-ajax-params"]
                },
                jsonreplace: {
                    default: "0",
                    aliases: ["data-ajax-jsonreplace"]
                },
                jsonkey: {
                    default: "html",
                    aliases: ["data-ajax-jsonkey"]
                },
                textreplace: {
                    default: "0",
                    aliases: ["data-ajax-textreplace"]
                }
            }
        });

        dbx.declare.transforms.ajax = function (raw, root) {
            return {
                _index: raw._index,
                lib: "ajax",
                class: normalizeClassFilter(raw.class),
                mode: normalizeMode(raw.mode),
                url: String(raw.url || "form").trim(),
                target: String(raw.target || "").trim(),
                replace: normalizeReplaceMode(raw.replace),
                bind: normalizeBind(raw.bind),
                method: normalizeMethod(raw.method, ""),
                params: String(raw.params || "").trim(),
                jsonreplace: bool(raw.jsonreplace, false),
                jsonkey: String(raw.jsonkey || "html").trim(),
                textreplace: bool(raw.textreplace, false)
            };
        };
    }


    /* =========================================================
     * FEATURE
     * ========================================================= */

    dbx.feature.register("ajax", {

        scope: "element",

        priority: "mid",

        css: [
            ["css", "design", "c-ajax.css"]
        ],


        init(el, cfg) {

            if (!el) return;

            el.__dbxInitialized = el.__dbxInitialized || {};

            if (el.__dbxInitialized["ajax"]) {
                return;
            }

            el.__dbxInitialized["ajax"] = true;
            el.setAttribute("data-dbx-ajax-root", "1");

            getRootConfigs(el);

            dbx.log("[dbx.ajax] init", {
                rootId: el.id || "",
                configs: el._dbxAjaxConfigs
            });

            /* -------------------------------------------------
            * SUBMIT
            * ------------------------------------------------- */
            el.addEventListener("submit", function (e) {

                if (e.defaultPrevented) return;

                const form = e.target.closest("form");
                if (!form) return;
                if (!el.contains(form)) return;

                const nearestAjaxRoot = form.closest("[data-dbx-ajax-root='1']");
                if (nearestAjaxRoot !== el) return;

                executeAjax(el, form, {
                    submitSource: e.submitter || null,
                    submitName: e.submitter ? readAttr(e.submitter, "name") : "",
                    submitValue: e.submitter ? readAttr(e.submitter, "value") : ""
                }, e).catch(() => {});
            });

            /* -------------------------------------------------
            * CLICK
            * ------------------------------------------------- */
            el.addEventListener("click", function (e) {

                if (e.defaultPrevented) return;

                const source = e.target.closest("a, button, input[type='button'], input[type='submit'], input[type='image']");
                if (!source) return;
                if (!el.contains(source)) return;

                const nearestAjaxRoot = source.closest("[data-dbx-ajax-root='1']");
                if (nearestAjaxRoot !== el) return;

                const type = elementType(source);

                if (type === "link") {

                    executeAjax(el, source, {}, e).catch(() => {});
                    return;
                }

                if (type === "button") {

                    const tag = source.tagName.toLowerCase();
                    const btnType = (tag === "button")
                        ? String(source.getAttribute("type") || "submit").toLowerCase()
                        : String(source.getAttribute("type") || "").toLowerCase();

                    const isFormAction = source.classList.contains("dbxAjaxFormAction");

                    if (isFormAction) {

                        const formId = readLegacyData(source, "dbx_form");
                        const target = readLegacyData(source, "dbx_target");
                        const form = formId ? document.getElementById(formId) : getClosestForm(source, el);

                        executeAjax(el, source, {
                            form: form,
                            target: target || "",
                            url: readAttr(source, "href") || readDataAjax(source, "url") || ""
                        }, e).catch(() => {});

                        return;
                    }

                    /* submit-button in form:
                    normales AJAX läuft über submit-event.
                    nur wenn expliziter Override vorhanden ist,
                    wird direkt auf Button-Ebene ausgeführt. */
                    const hasButtonOverride =
                        !!readDataAjax(source, "url") ||
                        !!readDataAjax(source, "target") ||
                        !!readDataAjax(source, "mode") ||
                        !!readDataAjax(source, "method") ||
                        !!readDataAjax(source, "params") ||
                        !!readAttr(source, "formaction") ||
                        !!readAttr(source, "formmethod");

                    if ((btnType === "submit" || btnType === "image") && !hasButtonOverride) {
                        return;
                    }

                    executeAjax(el, source, {}, e).catch(() => {});
                }
            });
        },


        destroy(el, cfg) {

            if (!el) return;

            delete el._dbxAjaxConfigs;

            if (el.__dbxInitialized && el.__dbxInitialized["ajax"]) {
                delete el.__dbxInitialized["ajax"];
            }

            el.removeAttribute("data-dbx-ajax-root");

            dbx.log("[dbx.ajax] destroy", {
                rootId: el.id || ""
            });
        }

    });

})(window, document);
