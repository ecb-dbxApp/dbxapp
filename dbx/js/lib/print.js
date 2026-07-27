(function (window, document) {
    "use strict";

    if (!window.dbx) window.dbx = {};

    /* =========================================================
       CORE PRINT FUNCTION
    ========================================================= */

    dbx.print = function (configString) {

        if (dbx._printing === true) {
            dbx.log("warn", "[dbx.print] already printing");
            return;
        }

        if (!configString || typeof configString !== "string") {
            dbx.log("warn", "[dbx.print] Missing selector");
            return;
        }

        const parsed = parseConfig(configString);

        const original = document.querySelector(parsed.selector);
        if (!original) {
            dbx.log("warn", "[dbx.print] Selector not found:", parsed.selector);
            return;
        }

        dbx._printing = true;

        const clone = original.cloneNode(true);
        clone.querySelectorAll(".noPrint").forEach(el => el.remove());

        removeIfExists("dbx-print-root");
        removeIfExists("dbx-print-style");

        const root = document.createElement("div");
        root.id = "dbx-print-root";

        root.style.fontFamily = parsed.font;
        root.style.fontSize   = parsed.fontsize;
        root.style.lineHeight = parsed.lineheight;

        root.appendChild(clone);
        document.body.appendChild(root);

        document.body.classList.add("dbx-printing");

        applyTableCompaction(root, parsed);

        /* 🔥 APPLY DYNAMIC PAGE PADDING (PRINT) */
        const pages = root.querySelectorAll(".print-page");

        pages.forEach((page, index) => {

            const parts = parsed.margin.split(" ");

            let pt = parts[0] || "0";
            let pr = parts[1] || parts[0] || "0";
            let pb = parts[2] || parts[0] || "0";
            let pl = parts[3] || parts[1] || parts[0] || "0";

            if (index === 0) {
                pt = "0mm";
            }

            page.style.paddingTop    = pt;
            page.style.paddingRight  = pr;
            page.style.paddingBottom = pb;
            page.style.paddingLeft   = pl;
        });

        const style = document.createElement("style");
        style.id = "dbx-print-style";
        style.media = "print";

        style.innerHTML = `
            @page {
                size: ${parsed.format} ${parsed.orientation};
                margin: 0;
            }
        `;

        document.head.appendChild(style);

        setTimeout(() => window.print(), 60);

        const cleanup = function () {

            document.body.classList.remove("dbx-printing");

            removeIfExists("dbx-print-root");
            removeIfExists("dbx-print-style");

            dbx._printing = false;

            window.removeEventListener("afterprint", cleanup);
        };

        window.addEventListener("afterprint", cleanup);
    };


    /* =========================================================
       SCREEN MODE
    ========================================================= */

    function applyScreenMode(parsed) {

        const target = document.querySelector(parsed.selector);
        if (!target) return;

        target.classList.add("dbx-screen-preview");

        target.style.fontFamily = parsed.font;
        target.style.fontSize   = parsed.fontsize;
        target.style.lineHeight = parsed.lineheight;

        applyTableCompaction(target, parsed);

        target.querySelectorAll(".print-page").forEach(page => {

            const parts = parsed.margin.split(" ");

            page.style.paddingTop    = parts[0] || "0";
            page.style.paddingRight  = parts[1] || parts[0] || "0";
            page.style.paddingBottom = parts[2] || parts[0] || "0";
            page.style.paddingLeft   = parts[3] || parts[1] || parts[0] || "0";

            page.classList.remove("a4","a5","portrait","landscape");

            page.classList.add(parsed.format.toLowerCase());
            page.classList.add(parsed.orientation.toLowerCase());
        });
    }


    /* =========================================================
       TABLE COMPACTION ENGINE
    ========================================================= */

    function applyTableCompaction(container, parsed) {

        let fontSizePx = parseFloat(parsed.fontsize) || 10;

        let padding = parsed.padding;
        let rowheight = parsed.rowheight;
        let lineheight = parsed.lineheight;

        if (padding !== null) {
            if (!String(padding).includes("px")) {
                padding = padding + "px";
            }
        }

        if (rowheight !== null && rowheight !== "auto") {
            if (!String(rowheight).includes("px")) {
                rowheight = rowheight + "px";
            }
        }

        if (parsed.compact === true) {

            padding = Math.max(0, Math.floor(fontSizePx * 0.15)) + "px";
            lineheight = "1.0";
            rowheight = Math.floor(fontSizePx * 1.2) + "px";
        }

        if (rowheight === "auto") {

            let lh = parseFloat(lineheight) || 1.2;
            let pad = parseFloat(padding) || 0;

            let calc = Math.ceil(fontSizePx * lh + (pad * 2));

            rowheight = calc + "px";
        }

        if (rowheight) {
            container.querySelectorAll("tr").forEach(tr => {
                tr.style.height = rowheight;
            });
        }

        container.querySelectorAll("td, th").forEach(cell => {

            if (padding !== null) {
                cell.style.padding = padding;
            }

            if (lineheight) {
                cell.style.lineHeight = lineheight;
            }
        });
    }


    /* =========================================================
       CONFIG PARSER
    ========================================================= */

    function parseConfig(configString) {

        const parts = configString.split("|").map(p => p.trim());
        const selector = parts[0];

        const options = {
            format: "A4",
            orientation: "portrait",
            margin: "12mm",
            margin_top: null,
            margin_right: null,
            margin_bottom: null,
            margin_left: null,
            font: "Arial",
            fontsize: "10px",
            lineheight: "1.3",
            rowheight: null,
            padding: null,
            compact: false,
            screen: false
        };

        for (let i = 1; i < parts.length; i++) {

            const [key, value] = parts[i].split("=").map(p => p.trim());
            if (!key) continue;

            switch (key.toLowerCase()) {

                case "format":
                    options.format = value.toUpperCase();
                    break;

                case "orientation":
                    options.orientation = value.toLowerCase();
                    break;

                case "margin":
                    options.margin = value || "10mm";
                    break;

                case "margin_top":
                    options.margin_top = value;
                    break;

                case "margin_right":
                    options.margin_right = value;
                    break;

                case "margin_bottom":
                    options.margin_bottom = value;
                    break;

                case "margin_left":
                    options.margin_left = value;
                    break;

                case "font":
                    options.font = (value || "").toLowerCase() === "ariel" ? "Arial" : value;
                    break;

                case "fontsize":
                    options.fontsize = value;
                    break;

                case "lineheight":
                    options.lineheight = value;
                    break;

                case "rowheight":
                    options.rowheight = value;
                    break;

                case "padding":
                    options.padding = value;
                    break;

                case "compact":
                    options.compact = value === "1" || value === "true";
                    break;

                case "screen":
                    options.screen = value === "1" || value === "true";
                    break;
            }
        }

        let marginFinal = options.margin;

        if (
            options.margin_top !== null ||
            options.margin_right !== null ||
            options.margin_bottom !== null ||
            options.margin_left !== null
        ) {
            const mt = options.margin_top    || options.margin || "10mm";
            const mr = options.margin_right  || options.margin || "10mm";
            const mb = options.margin_bottom || options.margin || "10mm";
            const ml = options.margin_left   || options.margin || "10mm";

            marginFinal = `${mt} ${mr} ${mb} ${ml}`;
        }

        return {
            selector,
            format: options.format,
            orientation: options.orientation,
            margin: marginFinal,
            font: options.font,
            fontsize: options.fontsize,
            lineheight: options.lineheight,
            rowheight: options.rowheight,
            padding: options.padding,
            compact: options.compact,
            screen: options.screen
        };
    }

    function removeIfExists(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }


    /* =========================================================
       DBX FEATURE WRAPPER
    ========================================================= */

    if (window.dbx && dbx.feature) {

        dbx.feature.register("print", {

            scope: "element", // 🔥 FIX

            // 🔥 CSS über PREPARE
            css: [
                ['css', 'design', 'c-print.css']
            ],

            priority: "mid",

            init(el, config) {

                if (!el) return;

                // 🔥 INIT GUARD
                el.__dbxInitialized = el.__dbxInitialized || {};
                if (el.__dbxInitialized["print"]) return;
                el.__dbxInitialized["print"] = true;

                let target = config.target || config.selector;

                if (!target) {
                    const container = el.closest("[id]");
                    if (container) {
                        target = "#" + container.id;
                    }
                }

                if (!target) {
                    dbx.warn("[dbx.print] No target defined.");
                    return;
                }

                let configString = target;

                Object.keys(config).forEach(function (key) {

                    if (key === "target" || key === "selector" || key === "id")
                        return;

                    configString += "|" + key + "=" + config[key];
                });

                const parsed = parseConfig(configString);

                if (parsed.screen === true) {
                    applyScreenMode(parsed);
                }

                el.addEventListener("click", function (e) {
                    e.preventDefault();
                    dbx.print(configString);
                });
            }
        });
    }

})(window, document);