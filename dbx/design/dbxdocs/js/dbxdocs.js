(function (window, document) {
    "use strict";

    var dbx = window.dbx;
    var shell = document.querySelector(".dbxdocs-app-shell");
    var toggle = document.querySelector("[data-dbxdocs-sidebar-toggle]");
    var content = document.querySelector(".dbx-content");
    var topButton = document.getElementById("dbxBackToTop");
    var sectionbar = document.querySelector("[data-dbxdocs-sectionbar]");
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

    function headingId(heading, index, used) {
        var anchor = heading.querySelector("a.anchor[id]");
        var id = anchor ? anchor.id : heading.id;
        if (!id) {
            id = (heading.textContent || "abschnitt")
                .toLocaleLowerCase(document.documentElement.lang || "de")
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-+|-+$/g, "") || "abschnitt-" + (index + 1);
        }
        var base = id;
        var suffix = 2;
        while (used[id]) {
            id = base + "-" + suffix;
            suffix += 1;
        }
        used[id] = true;
        heading.id = id;
        return id;
    }

    function buildPageToc() {
        var language = (document.documentElement.lang || "de").toLowerCase();
        var labels = {
            de: "Auf dieser Seite",
            en: "On this page",
            es: "En esta página"
        };
        document.querySelectorAll(".dbxdocs-cms-article:not([data-dbxdocs-no-toc])").forEach(function (article) {
            if (article.querySelector(":scope > .dbxdocs-page-toc")) return;
            var headings = Array.prototype.slice.call(article.querySelectorAll("h2, h3"));
            if (headings.length < 4) return;

            var used = Object.create(null);
            var details = document.createElement("details");
            details.className = "dbxdocs-page-toc";
            details.open = window.matchMedia("(min-width: 768px)").matches;
            var summary = document.createElement("summary");
            summary.innerHTML = '<i class="bi bi-list-nested" aria-hidden="true"></i><span>'
                + (labels[language] || labels.de) + "</span>";
            var list = document.createElement("ol");
            headings.forEach(function (heading, index) {
                var id = headingId(heading, index, used);
                var item = document.createElement("li");
                item.className = heading.tagName.toLowerCase() === "h3" ? "is-subsection" : "is-section";
                var link = document.createElement("a");
                var pageTarget = window.location.pathname + window.location.search + "#" + id;
                link.href = pageTarget;
                link.textContent = (heading.textContent || "").trim();
                link.addEventListener("click", function (event) {
                    event.preventDefault();
                    heading.scrollIntoView({ behavior: "smooth", block: "start" });
                    window.history.replaceState(null, "", pageTarget);
                });
                item.appendChild(link);
                list.appendChild(item);
            });
            details.appendChild(summary);
            details.appendChild(list);
            var metadata = article.querySelector(":scope > .dbx-doc-meta");
            if (metadata && metadata.nextSibling) {
                article.insertBefore(details, metadata.nextSibling);
            } else {
                article.insertBefore(details, article.firstChild);
            }
        });
    }

    function initSectionNavigation() {
        if (!sectionbar) return;
        var lists = Array.prototype.slice.call(sectionbar.querySelectorAll(".dbx-menu-list"));
        lists.forEach(function (list) {
            list.addEventListener("wheel", function (event) {
                if (Math.abs(event.deltaY) <= Math.abs(event.deltaX) || list.scrollWidth <= list.clientWidth) {
                    return;
                }
                event.preventDefault();
                list.scrollLeft += event.deltaY;
            }, { passive: false });
        });

        var active = sectionbar.querySelector(".dbx-menu-item.is-active > .dbx-menu-link")
            || sectionbar.querySelector(".dbx-menu-item.is-active-path > .dbx-menu-link");
        if (active) {
            window.requestAnimationFrame(function () {
                active.scrollIntoView({ block: "nearest", inline: "center" });
            });
        }

        sectionbar.addEventListener("keydown", function (event) {
            if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") return;
            var links = Array.prototype.slice.call(sectionbar.querySelectorAll(".dbx-menu-link"))
                .filter(function (link) { return link.offsetParent !== null; });
            var current = links.indexOf(document.activeElement);
            if (current < 0) return;
            event.preventDefault();
            var next = event.key === "ArrowRight" ? current + 1 : current - 1;
            links[(next + links.length) % links.length].focus();
        });
    }

    if ("scrollRestoration" in window.history) {
        window.history.scrollRestoration = "manual";
    }

    applyResponsiveState();
    buildPageToc();
    initSectionNavigation();
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

    var mainMenu = document.getElementById("dbx_main_menu");
    if (mainMenu) {
        mainMenu.addEventListener("click", function (event) {
            if (!desktopQuery.matches && event.target.closest("a[href]")) {
                shell.classList.remove("is-mobile-nav-open");
                updateToggle(false, "bi bi-list");
            }
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
