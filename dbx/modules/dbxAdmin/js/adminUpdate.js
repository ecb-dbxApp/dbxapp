/**
 * @file adminUpdate.js
 * Lazy-Loader fuer die getrennten Paketbereiche der Update-Verwaltung.
 */
(function (window, document) {
    "use strict";

    const dbx = window.dbx;
    if (!dbx || !dbx.feature) return;

    let requestQueue = Promise.resolve();

    function load(section) {
        if (!section || section.dataset.dbxPackageState) return;
        const link = section.querySelector(".dbx-package-loader");
        if (!link) return;
        section.dataset.dbxPackageState = "queued";

        requestQueue = requestQueue.then(() => {
            if (!section.isConnected) return;
            section.dataset.dbxPackageState = "loading";
            return dbx.ajax.request({
                url: link.href,
                method: "GET",
                mode: "html",
                timeout: 30000
            }).then(html => {
                if (!section.isConnected) return;
                section.innerHTML = String(html || "");
                section.dataset.dbxPackageState = "loaded";
                if (typeof dbx.scan === "function") dbx.scan(section);
            }).catch(() => {
                if (!section.isConnected) return;
                section.dataset.dbxPackageState = "";
                link.classList.add("is-error");
                link.innerHTML = '<i class="bi bi-arrow-clockwise" aria-hidden="true"></i> '
                    + (link.dataset.errorLabel || "Retry");
            });
        });
    }

    function observe(root) {
        const sections = Array.from(root.querySelectorAll("[data-dbx-package-lazy]"));
        if (!sections.length) return;

        if (!("IntersectionObserver" in window)) {
            sections.forEach(load);
            return;
        }

        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                observer.unobserve(entry.target);
                load(entry.target);
            });
        }, { rootMargin: "320px 0px", threshold: 0.01 });
        sections.forEach(section => observer.observe(section));
        root.__dbxPackageObserver = observer;
    }

    function init(root) {
        if (!root || root.__dbxAdminUpdateReady) return;
        root.__dbxAdminUpdateReady = true;

        root.addEventListener("click", event => {
            const loader = event.target.closest(".dbx-package-loader");
            if (loader && root.contains(loader)) {
                event.preventDefault();
                const section = loader.closest("[data-dbx-package-lazy]");
                if (section) {
                    if (section.dataset.dbxPackageState === "queued"
                        || section.dataset.dbxPackageState === "loading") return;
                    section.dataset.dbxPackageState = "";
                    load(section);
                }
                return;
            }

            const nav = event.target.closest(".dbx-package-nav a[href*='#package-']");
            if (!nav || !root.contains(nav)) return;
            const hash = new URL(nav.href, window.location.href).hash;
            const target = hash ? root.querySelector(hash) : null;
            if (target) load(target.querySelector("[data-dbx-package-lazy]"));
        });

        observe(root);
    }

    dbx.feature.register("adminUpdate", {
        scope: "element",
        priority: "last",
        js: [["js", "lib", "ajax.js"]],
        init: init,
        rescan: function (ctx) {
            const root = ctx && ctx.matches && ctx.matches("[data-dbx*='lib=adminUpdate']")
                ? ctx
                : document.querySelector("[data-dbx*='lib=adminUpdate']");
            if (root) init(root);
        }
    });
})(window, document);
