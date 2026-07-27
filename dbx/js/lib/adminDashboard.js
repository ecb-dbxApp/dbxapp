/*!
 * @file adminDashboard.js
 * Dashboard-Visualisierung fuer das dbxAdmin-Modul.
 *
 * Die Lib rendert bewusst ohne Fremdchart-Library:
 * - Counter-Animationen
 * - Health-Ring
 * - Sparklines
 * - Speedometer fuer Request-/DB-Timer
 *
 * Beispiel:
 * ```html
 * <section data-dbx="lib=adminDashboard">
 *    <canvas data-admin-gauge="0.110"></canvas>
 * </section>
 * ```
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][adminDashboard] dbx core missing");
        return;
    }

    const dbx = window.dbx;
    const colors = {
        teal: "#0f9f9a",
        cyan: "#2878b8",
        green: "#2d9b65",
        amber: "#c9841b",
        navy: "#14213d",
        red: "#c94f4f",
        purple: "#7b5bb7",
        slate: "#58677a",
        grid: "rgba(42,59,84,.12)",
        muted: "#637083"
    };

    function attr(el, name, def = "") {
        if (!el || !el.getAttribute) return def;
        const value = el.getAttribute(name);
        return value == null ? def : String(value).trim();
    }

    function number(value, def = 0) {
        const raw = String(value == null ? "" : value).replace(/\./g, "").replace(",", ".");
        const num = parseFloat(raw);
        return Number.isFinite(num) ? num : def;
    }

    function format(num) {
        return Math.round(num).toLocaleString("de-DE");
    }

    function animateValue(el) {
        const target = number(attr(el, "data-admin-value", el.textContent), 0);
        const suffix = String(el.textContent || "").includes("%") ? "%" : "";
        const start = performance.now();
        const duration = 760;

        function step(now) {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            const value = target * eased;
            el.textContent = format(value) + suffix;
            if (t < 1) window.requestAnimationFrame(step);
        }

        window.requestAnimationFrame(step);
    }

    function bindSysmsgControls(root) {
        if (!root || root.__dbxSysmsgControlsBound) return;
        root.__dbxSysmsgControlsBound = true;
        root.addEventListener("change", event => {
            const select = event.target && event.target.closest
                ? event.target.closest("[data-sysmsg-level-select]")
                : null;
            if (!select || !root.contains(select)) return;

            if (attr(select, "data-sysmsg-level-select", "") === "form") {
                const form = select.closest("form");
                if (form) {
                    if (typeof form.requestSubmit === "function") form.requestSubmit();
                    else form.submit();
                }
                return;
            }

            const control = select.closest(".dbx-admin-dashboard-sysmsg-level-control");
            const save = control ? control.querySelector("a[data-sysmsg-level-save]") : null;
            if (!save) return;
            save.href = attr(save, "data-save-base", "") + "&sys_msg_level=" + encodeURIComponent(select.value);
            save.click();
        });
    }

   function canvasSize(canvas) {
       const rect = canvas.getBoundingClientRect();
       const ratio = window.devicePixelRatio || 1;
        const baseWidth = Math.max(1, number(canvas.getAttribute("width"), 1));
        const baseHeight = Math.max(1, number(canvas.getAttribute("height"), 1));
        const cssWidth = rect.width > 2 ? rect.width : baseWidth;
        const cssHeight = rect.height > 2 ? rect.height : Math.max(1, cssWidth * (baseHeight / baseWidth));
        const width = Math.max(1, Math.round(cssWidth * ratio));
        const height = Math.max(1, Math.round(cssHeight * ratio));

        if (canvas.width !== width || canvas.height !== height) {
            canvas.width = width;
            canvas.height = height;
        }

        return { width, height, ratio };
    }

    function drawSpark(canvas) {
        const values = attr(canvas, "data-admin-spark", "")
            .split(",")
            .map(v => number(v, 0))
            .filter(v => Number.isFinite(v));

        if (!values.length) return;

        const ctx = canvas.getContext("2d");
        const size = canvasSize(canvas);
        const w = size.width;
        const h = size.height;
        const min = Math.min(...values);
        const max = Math.max(...values);
        const range = Math.max(1, max - min);
        const pad = 8 * size.ratio;

        ctx.clearRect(0, 0, w, h);
        ctx.lineWidth = 2 * size.ratio;
        ctx.strokeStyle = colors.teal;
        ctx.beginPath();

        values.forEach((value, index) => {
            const x = pad + ((w - pad * 2) / Math.max(1, values.length - 1)) * index;
            const y = h - pad - ((value - min) / range) * (h - pad * 2);
            if (index === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });

        ctx.stroke();

        const gradient = ctx.createLinearGradient(0, 0, 0, h);
        gradient.addColorStop(0, "rgba(15,159,154,.22)");
        gradient.addColorStop(1, "rgba(15,159,154,0)");

        ctx.lineTo(w - pad, h - pad);
        ctx.lineTo(pad, h - pad);
        ctx.closePath();
        ctx.fillStyle = gradient;
        ctx.fill();
    }

    function drawRing(root) {
        const holder = root.querySelector(".dbx-admin-dashboard-health");
        if (!holder) return;

        const canvas = holder.querySelector("canvas");
        if (!canvas) return;

        const value = Math.max(0, Math.min(100, number(attr(holder, "data-admin-ring", "0"), 0)));
        const ctx = canvas.getContext("2d");
        const size = canvasSize(canvas);
        const w = size.width;
        const h = size.height;
        const cx = w / 2;
        const cy = h / 2;
        const radius = Math.min(w, h) * .38;

        ctx.clearRect(0, 0, w, h);
        ctx.lineWidth = 10 * size.ratio;
        ctx.lineCap = "round";

        ctx.strokeStyle = "rgba(42,59,84,.10)";
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.stroke();

        ctx.strokeStyle = value >= 75 ? colors.green : (value >= 45 ? colors.amber : colors.red);
        ctx.beginPath();
        ctx.arc(cx, cy, radius, -Math.PI / 2, -Math.PI / 2 + (Math.PI * 2 * value / 100));
        ctx.stroke();
    }

    function drawGauge(canvas) {
        const value = Math.max(0, number(attr(canvas, "data-admin-gauge-value", "0"), 0));
        const max = Math.max(1, number(attr(canvas, "data-admin-gauge-max", "1"), 1));
        const ctx = canvas.getContext("2d");
        const size = canvasSize(canvas);
        const w = size.width;
        const h = size.height;
        const ratio = size.ratio;
        const cx = w / 2;
        const cy = h * .86;
        const radius = Math.min(w * .42, h * .64);
        const start = Math.PI;
        const end = Math.PI * 2;
        const pct = Math.max(0, Math.min(1, value / max));
        const displayPct = 1 - pct;
        const angle = start + (end - start) * displayPct;
        const pointerColor = displayPct < .34 ? colors.red : (displayPct < .68 ? colors.amber : colors.green);

        function arcFor(ms) {
            return start + (end - start) * (1 - Math.max(0, Math.min(1, ms / max)));
        }

        function drawArc(fromMs, toMs, color, alpha) {
            ctx.save();
            ctx.globalAlpha = alpha;
            ctx.strokeStyle = color;
            ctx.lineWidth = 13 * ratio;
            ctx.lineCap = "butt";
            ctx.beginPath();
            ctx.arc(cx, cy, radius, arcFor(fromMs), arcFor(toMs));
            ctx.stroke();
            ctx.restore();
        }

        ctx.clearRect(0, 0, w, h);
        ctx.lineWidth = 13 * ratio;
        ctx.lineCap = "round";

        ctx.strokeStyle = "rgba(42,59,84,.12)";
        ctx.beginPath();
        ctx.arc(cx, cy, radius, start, end);
        ctx.stroke();

        drawArc(6000, 4000, colors.red, .35);
        drawArc(4000, 2000, colors.amber, .38);
        drawArc(2000, 0, colors.green, .38);

        const gradient = ctx.createLinearGradient(cx - radius, 0, cx + radius, 0);
        gradient.addColorStop(0, colors.red);
        gradient.addColorStop(.34, colors.red);
        gradient.addColorStop(.35, colors.amber);
        gradient.addColorStop(.67, colors.amber);
        gradient.addColorStop(.68, colors.green);
        gradient.addColorStop(1, colors.green);
        ctx.strokeStyle = gradient;
        ctx.beginPath();
        ctx.arc(cx, cy, radius, start, angle);
        ctx.stroke();

        [6000, 4000, 2000, 0].forEach(ms => {
            const tick = arcFor(ms);
            const inner = radius - 11 * ratio;
            const outer = radius + 4 * ratio;
            ctx.strokeStyle = "rgba(20,33,61,.38)";
            ctx.lineWidth = 1.5 * ratio;
            ctx.beginPath();
            ctx.moveTo(cx + Math.cos(tick) * inner, cy + Math.sin(tick) * inner);
            ctx.lineTo(cx + Math.cos(tick) * outer, cy + Math.sin(tick) * outer);
            ctx.stroke();

            ctx.fillStyle = colors.muted;
            ctx.font = `${9.5 * ratio}px system-ui, -apple-system, Segoe UI, sans-serif`;
            ctx.textAlign = ms === 6000 ? "left" : (ms === 0 ? "right" : "center");
            ctx.fillText(ms === 0 ? "0" : `${Math.round(ms / 1000)}s`, cx + Math.cos(tick) * (radius + 14 * ratio), cy + Math.sin(tick) * (radius + 14 * ratio) + 4 * ratio);
        });

        ctx.strokeStyle = pointerColor;
        ctx.lineWidth = 3 * ratio;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(cx + Math.cos(angle) * (radius - 8 * ratio), cy + Math.sin(angle) * (radius - 8 * ratio));
        ctx.stroke();

        ctx.fillStyle = "#fff";
        ctx.beginPath();
        ctx.arc(cx, cy, 8 * ratio, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = pointerColor;
        ctx.lineWidth = 2 * ratio;
        ctx.stroke();
    }

    function parseBars(canvas) {
        try {
            const data = JSON.parse(attr(canvas, "data-admin-bars", "[]"));
            return Array.isArray(data) ? data : [];
        } catch (err) {
            dbx.warn("[adminDashboard] bar data invalid", err);
            return [];
        }
    }

    function drawBars(canvas) {
        const rows = parseBars(canvas);
        if (!rows.length) return;

        const ctx = canvas.getContext("2d");
        const size = canvasSize(canvas);
        const w = size.width;
        const h = size.height;
        const ratio = size.ratio;
        const padX = 42 * ratio;
        const padY = 30 * ratio;
        const chartW = w - padX * 2;
        const chartH = h - padY * 2;
        const max = Math.max(1, ...rows.map(row => number(row.value, 0)));
        const gap = 16 * ratio;
        const barW = (chartW - gap * (rows.length - 1)) / rows.length;

        ctx.clearRect(0, 0, w, h);
        ctx.font = `${12 * ratio}px system-ui, -apple-system, Segoe UI, sans-serif`;
        ctx.textBaseline = "middle";

        for (let i = 0; i <= 4; i++) {
            const y = padY + chartH - (chartH / 4) * i;
            ctx.strokeStyle = colors.grid;
            ctx.lineWidth = 1 * ratio;
            ctx.beginPath();
            ctx.moveTo(padX, y);
            ctx.lineTo(w - padX, y);
            ctx.stroke();
        }

        rows.forEach((row, index) => {
            const value = number(row.value, 0);
            const barH = Math.max(3 * ratio, (value / max) * chartH);
            const x = padX + index * (barW + gap);
            const y = padY + chartH - barH;
            const tone = colors[row.tone] || colors.teal;

            const gradient = ctx.createLinearGradient(0, y, 0, y + barH);
            gradient.addColorStop(0, tone);
            gradient.addColorStop(1, "rgba(40,120,184,.50)");

            ctx.fillStyle = gradient;
            roundedRect(ctx, x, y, barW, barH, 8 * ratio);
            ctx.fill();

            ctx.fillStyle = colors.navy;
            ctx.textAlign = "center";
            ctx.font = `${13 * ratio}px system-ui, -apple-system, Segoe UI, sans-serif`;
            ctx.fillText(format(value), x + barW / 2, Math.max(16 * ratio, y - 12 * ratio));

            ctx.fillStyle = colors.muted;
            ctx.font = `${12 * ratio}px system-ui, -apple-system, Segoe UI, sans-serif`;
            ctx.fillText(String(row.label || ""), x + barW / 2, h - 14 * ratio);
        });
    }

    function roundedRect(ctx, x, y, width, height, radius) {
        const r = Math.min(radius, width / 2, height / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + width - r, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + r);
        ctx.lineTo(x + width, y + height);
        ctx.lineTo(x, y + height);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    function redraw(root) {
        root.querySelectorAll("canvas[data-admin-spark]").forEach(drawSpark);
        root.querySelectorAll("canvas[data-admin-gauge]").forEach(drawGauge);
        root.querySelectorAll("canvas[data-admin-bars]").forEach(drawBars);
        drawRing(root);
    }

    function dashboardRoot(ctx) {
        if (ctx && ctx.matches && ctx.matches("[data-admin-dashboard='1']")) {
            return ctx;
        }
        if (ctx && ctx.querySelector) {
            return ctx.querySelector("[data-admin-dashboard='1']");
        }
        return document.querySelector("[data-admin-dashboard='1']");
    }

    function dashboardWork(root) {
        return root ? root.querySelector("#dbx_admin_dashboard_work") : null;
    }

    function navSectionFromLink(link) {
        return attr(link, "data-admin-nav", "");
    }

    const UI_LIB = "adminDashboard";
    const UI_ID = "admin-dashboard";
    const UI_KEY_SECTION = "section";
    const DEFAULT_SECTION = "hero";

    function sectionFromUrl(url) {
        if (!url) return "";
        try {
            const parsed = new URL(String(url), window.location.href);
            return String(parsed.searchParams.get("dbx_run2") || "").trim();
        } catch (err) {
            return "";
        }
    }

    function dashboardRootFromEvent(data) {
        const direct = dashboardRoot(data && data.root);
        if (direct) return direct;

        const target = data && data.targetElement;
        if (target && target.closest) {
            return target.closest("[data-admin-dashboard='1']");
        }

        const source = data && data.source;
        if (source && source.closest) {
            return source.closest("[data-admin-dashboard='1']");
        }

        return null;
    }

    function getUiSection() {
        if (!dbx.uiGet) return DEFAULT_SECTION;

        const section = String(dbx.uiGet(UI_LIB, UI_ID, UI_KEY_SECTION, "") || "").trim();
        return section || DEFAULT_SECTION;
    }

    function setUiSection(section) {
        if (!section || !dbx.uiSet) return;
        dbx.uiSet(UI_LIB, UI_ID, UI_KEY_SECTION, section);
    }

    function navLink(root, section) {
        if (!root || !section) return null;
        return root.querySelector(`[data-admin-nav="${section}"].list-group-item`);
    }

    function workSection(root) {
        const work = dashboardWork(root);
        return work ? String(work.getAttribute("data-admin-section") || DEFAULT_SECTION) : DEFAULT_SECTION;
    }

    function setWorkSection(root, section) {
        const work = dashboardWork(root);
        if (work && section) {
            work.setAttribute("data-admin-section", section);
        }
    }

    function setActiveNav(root, link) {
        if (!root || !link) return;

        root.querySelectorAll("[data-admin-nav].list-group-item").forEach(row => {
            row.classList.toggle("active", row === link);
            row.setAttribute("aria-current", row === link ? "page" : "false");
        });

        const section = navSectionFromLink(link);
        if (section) {
            setUiSection(section);
            setWorkSection(root, section);
        }
    }

    function setActiveNavBySection(root, section) {
        const link = navLink(root, section);
        if (link) setActiveNav(root, link);
    }

    function ajaxRootForLink(link) {
        if (!link || !link.closest) return null;
        return link.closest("[data-dbx-ajax-root='1']");
    }

   function syncDashboardSection(root) {
       const section = getUiSection();
       const link = navLink(root, section);
        if (!link) {
            setUiSection(DEFAULT_SECTION);
            const fallbackLink = navLink(root, DEFAULT_SECTION);
            if (fallbackLink) setActiveNav(root, fallbackLink);
            scheduleRedraw(root);
            return;
        }

       const currentSection = workSection(root);
       setActiveNav(root, link);

        if (currentSection !== section && dbx.ajax && typeof dbx.ajax.run === "function") {
            const ajaxRoot = ajaxRootForLink(link);
            if (ajaxRoot) {
                dbx.ajax.run(ajaxRoot, link);
            } else {
                link.click();
            }
        } else {
            scheduleRedraw(root);
        }
    }

    function resolveNavSection(data, root) {
        const source = data && data.source;
        if (source && source.getAttribute && source.hasAttribute("data-admin-nav") && root.contains(source)) {
            return navSectionFromLink(source);
        }

        const fromUrl = sectionFromUrl(data && data.url);
        if (fromUrl) return fromUrl;

        return "";
    }

    function handleDashboardAjaxBefore(data) {
        const root = dashboardRootFromEvent(data);
        if (!root) return;

        const source = data && data.source;
        if (source && source.getAttribute && source.hasAttribute("data-admin-nav") && root.contains(source)) {
            setActiveNav(root, source);
        }
    }

    function handleDashboardAjaxAfter(data) {
        const root = dashboardRootFromEvent(data);
        if (!root) return;

        const section = resolveNavSection(data, root);
        if (section) {
            setActiveNavBySection(root, section);
        }

        scheduleRedraw(root);
    }

    function scheduleRedraw(root) {
        if (!root) return;

        window.requestAnimationFrame(() => {
            refreshContent(root);
            window.setTimeout(() => refreshContent(root), 60);
            window.setTimeout(() => refreshContent(root), 220);
        });
    }

    function refreshContent(root) {
        if (!root) return;

        const work = dashboardWork(root);
        const scope = work || root;

        scope.querySelectorAll("[data-admin-value]").forEach(animateValue);
        redraw(root);
    }

    dbx.adminDashboard = dbx.adminDashboard || {
        init(root) {
            if (!root) return;

            bindSysmsgControls(root);
            syncDashboardSection(root);

            if (!root.__dbxAdminDashboardNavBound) {
                root.__dbxAdminDashboardNavBound = true;
                root.addEventListener("click", event => {
                    const link = event.target.closest("[data-admin-nav].list-group-item.dbxAjax");
                    if (!link || !root.contains(link)) return;
                    setActiveNav(root, link);
                });
            }

            window.setTimeout(() => {
                root.classList.add("is-ready");
                scheduleRedraw(root);
            }, 30);

            if (!root.__dbxAdminDashboardResize) {
                root.__dbxAdminDashboardResize = true;
                window.addEventListener("resize", () => redraw(root), { passive: true });
            }
        },

        rescan(ctx) {
            const root = dashboardRoot(ctx);
            if (root) scheduleRedraw(root);
        }
    };

    if (!dbx.adminDashboard.__ajaxBeforeBound && dbx.event && typeof dbx.event.on === "function") {
        dbx.adminDashboard.__ajaxBeforeBound = true;
        dbx.event.on("ajax:before", data => {
            handleDashboardAjaxBefore(data);
        });
    }

    if (!dbx.adminDashboard.__ajaxAfterBound && dbx.event && typeof dbx.event.on === "function") {
        dbx.adminDashboard.__ajaxAfterBound = true;
        dbx.event.on("ajax:after", data => {
            handleDashboardAjaxAfter(data);
        });
    }

    if (!dbx.adminDashboard.__uiCollapseBound && dbx.event && typeof dbx.event.on === "function") {
        dbx.adminDashboard.__uiCollapseBound = true;
        dbx.event.on("ui:collapse", data => {
            const panel = data && data.panel;
            const root = panel && panel.closest ? panel.closest("[data-admin-dashboard='1']") : null;
            if (root) window.setTimeout(() => redraw(root), 20);
        });
    }

    dbx.feature.register("adminDashboard", {
        scope: "element",
        priority: "last",
        css: [
            ["css", "root", "vendor/twbs/bootstrap-icons/font/bootstrap-icons.css"],
            ["css", "design", "c-form.css"],
            ["css", "design", "c-admin.css"],
            ["css", "root", "modules/dbxAdmin/tpl/css/admin-dashboard.css"]
        ],
        js: [
            ["js", "lib", "ajax.js"]
        ],
        init: function (el) {
            dbx.adminDashboard.init(el);
        },
        rescan: function (ctx) {
            dbx.adminDashboard.rescan(ctx || document);
        }
    });

})(window, document);
