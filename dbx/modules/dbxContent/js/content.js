/*!
 * dbxapp content.js
 * Lightweight frontend runtime for rendered CMS pages (no tree, no editor).
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][content] dbx core missing");
        return;
    }

    const dbx = window.dbx;
    const LIB = "content";

    function hasVisibleHeroContent(el) {
        if (!el) return false;
        if (el.querySelector("img,video,iframe,object,embed,table,ul,ol,li,figure,[data-dbx]")) return true;
        return String(el.textContent || "").replace(/\u00a0/g, " ").trim() !== "";
    }

    function cleanupEmptyHeroContent(root) {
        (root || document).querySelectorAll(".c-cms .cms-hero.has-hero .hero-content").forEach(el => {
            if (!hasVisibleHeroContent(el)) el.remove();
        });
    }

    function init(el, cfg) {
        if (!el || el.__dbxContentReady) return;
        el.__dbxContentReady = true;
        cleanupEmptyHeroContent(el);
        if (window.dbx && typeof dbx.log === "function") {
            dbx.log("[content] init", cfg && cfg.id ? cfg.id : "");
        }
    }

    dbx.content = {
        init,
        rescan(ctx) {
            const root = ctx || document;
            cleanupEmptyHeroContent(root);
            root.querySelectorAll("[data-dbx]").forEach(el => {
                if (el.__dbxContentReady) return;
                const cfgList = dbx.parseData(el.getAttribute("data-dbx"));
                const cfg = cfgList.find(item => item.lib === LIB);
                if (cfg) init(el, cfg);
            });
        }
    };

    dbx.feature.register(LIB, {
        scope: "element",
        priority: "last",
        css: [
            ["css", "design", "c-content-frame.css"],
            ["css", "design", "c-content.css"]
        ],
        js: [],
        init,
        rescan(ctx) {
            dbx.content.rescan(ctx);
        }
    });

})(window, document);
