(function (window, document) {
    "use strict";

    const dbx = window.dbx;
    const shell = document.querySelector(".fleurop-app-shell");
    const toggle = document.querySelector("[data-fleurop-sidebar-toggle]");
    const content = document.querySelector(".dbx-content");
    const topButton = document.getElementById("dbxBackToTop");

    if (!shell) return;

    function readCollapsed() {
        if (dbx && typeof dbx.uiGet === "function") {
            const current = dbx.uiGet("design", "flowers", "sidebarCollapsed", null);
            if (current === true || current === false) {
                return current;
            }
            return dbx.uiGet("design", "fleurop", "sidebarCollapsed", false) === true;
        }
        return false;
    }

    function writeCollapsed(value) {
        if (dbx && typeof dbx.uiSet === "function") {
            dbx.uiSet("design", "flowers", "sidebarCollapsed", value);
        }
    }

    function setCollapsed(value) {
        const collapsed = window.matchMedia("(min-width: 992px)").matches && value === true;
        shell.classList.toggle("is-sidebar-collapsed", collapsed);
        if (toggle) {
            toggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
            const icon = toggle.querySelector("i");
            if (icon) icon.className = collapsed ? "bi bi-layout-sidebar-inset" : "bi bi-list";
        }
    }

    setCollapsed(readCollapsed());

    if (toggle) {
        toggle.addEventListener("click", function () {
            const next = !shell.classList.contains("is-sidebar-collapsed");
            setCollapsed(next);
            writeCollapsed(next);
        });
    }

    window.addEventListener("resize", function () {
        setCollapsed(readCollapsed());
    });

    if (content && topButton) {
        content.addEventListener("scroll", function () {
            topButton.classList.toggle("show", content.scrollTop > 360);
        }, { passive: true });

        topButton.addEventListener("click", function () {
            content.scrollTo({ top: 0, behavior: "smooth" });
        });
    }
})(window, document);
