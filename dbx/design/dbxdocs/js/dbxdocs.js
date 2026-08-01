(function (window, document) {
    "use strict";

    var dbx = window.dbx;
    var shell = document.querySelector(".dbxdocs-app-shell");
    var toggle = document.querySelector("[data-dbxdocs-sidebar-toggle]");
    var content = document.querySelector(".dbx-content");
    var topButton = document.getElementById("dbxBackToTop");
    var desktopQuery = window.matchMedia("(min-width: 992px)");

    if (!shell) return;

    function readCollapsed() {
        if (dbx && typeof dbx.uiGet === "function") {
            return dbx.uiGet("design", "dbxdocs", "sidebarCollapsed", false) === true;
        }
        return false;
    }

    function writeCollapsed(value) {
        if (dbx && typeof dbx.uiSet === "function") {
            dbx.uiSet("design", "dbxdocs", "sidebarCollapsed", value);
        }
    }

    function updateToggle(expanded, iconClass) {
        if (!toggle) return;
        toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
        var icon = toggle.querySelector("i");
        if (icon) icon.className = iconClass;
    }

    function applyDesktopState() {
        var collapsed = readCollapsed();
        shell.classList.remove("is-mobile-nav-open");
        shell.classList.toggle("is-sidebar-collapsed", collapsed);
        updateToggle(!collapsed, collapsed ? "bi bi-layout-sidebar-inset" : "bi bi-list");
    }

    function applyMobileState() {
        shell.classList.remove("is-sidebar-collapsed");
        shell.classList.remove("is-mobile-nav-open");
        updateToggle(false, "bi bi-list");
    }

    function applyResponsiveState() {
        if (desktopQuery.matches) {
            applyDesktopState();
        } else {
            applyMobileState();
        }
    }

    function resetInitialContentScroll() {
        if (!content || window.location.hash) return;
        content.scrollTop = 0;
    }

    if ("scrollRestoration" in window.history) {
        window.history.scrollRestoration = "manual";
    }

    applyResponsiveState();
    resetInitialContentScroll();
    window.addEventListener("pageshow", function () {
        resetInitialContentScroll();
        window.requestAnimationFrame(resetInitialContentScroll);
    });
    window.addEventListener("load", function () {
        window.requestAnimationFrame(resetInitialContentScroll);
    });

    if (toggle) {
        toggle.addEventListener("click", function () {
            if (desktopQuery.matches) {
                var collapsed = !shell.classList.contains("is-sidebar-collapsed");
                shell.classList.toggle("is-sidebar-collapsed", collapsed);
                writeCollapsed(collapsed);
                updateToggle(!collapsed, collapsed ? "bi bi-layout-sidebar-inset" : "bi bi-list");
                return;
            }

            var open = !shell.classList.contains("is-mobile-nav-open");
            shell.classList.toggle("is-mobile-nav-open", open);
            updateToggle(open, open ? "bi bi-x-lg" : "bi bi-list");
        });
    }

    if (typeof desktopQuery.addEventListener === "function") {
        desktopQuery.addEventListener("change", applyResponsiveState);
    } else if (typeof desktopQuery.addListener === "function") {
        desktopQuery.addListener(applyResponsiveState);
    }

    if (content && topButton) {
        content.addEventListener("scroll", function () {
            topButton.classList.toggle("show", content.scrollTop > 360);
        }, { passive: true });

        topButton.addEventListener("click", function () {
            content.scrollTo({ top: 0, behavior: "smooth" });
        });
    }
})(window, document);
