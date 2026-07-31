/*!
 * dbxapp viewport.js
 * Admin preview for common desktop, tablet and mobile viewport sizes.
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][viewport] dbx core missing");
        return;
    }

    const dbx = window.dbx;
    const LIB = "viewport";
    const PREVIEW_PARAM = "dbx_viewport_preview";
    const PRESETS = {
        "desktop-4k":       { label: "Desktop Wide 4K",  width: 3840, height: 2160, icon: "bi-display" },
        "desktop-full-hd":  { label: "Desktop Full HD",  width: 1920, height: 1080, icon: "bi-display" },
        "desktop-hd":       { label: "Desktop HD",       width: 1366, height: 768,  icon: "bi-display" },
        "tablet-portrait":  { label: "Tablet hoch",      width: 820,  height: 1180, icon: "bi-tablet" },
        "tablet-landscape": { label: "Tablet quer",      width: 1180, height: 820,  icon: "bi-tablet-landscape" },
        "mobile-portrait":  { label: "Mobile hoch",      width: 390,  height: 844,  icon: "bi-phone" },
        "mobile-landscape": { label: "Mobile quer",      width: 844,  height: 390,  icon: "bi-phone-landscape" }
    };

    let lab = null;
    let currentKey = "desktop-full-hd";
    let resizeFrame = 0;

    function isPreviewPage() {
        return new URLSearchParams(window.location.search).get(PREVIEW_PARAM) === "1";
    }

    function previewUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set(PREVIEW_PARAM, "1");
        url.searchParams.delete("dbx_ajax");
        url.searchParams.delete("dbx_sync");
        url.searchParams.delete("dbx_nocache");
        url.searchParams.delete("cachebust");
        return url.href;
    }

    function optionMarkup() {
        return Object.keys(PRESETS).map(key => {
            const preset = PRESETS[key];
            return `<option value="${key}">${preset.label} · ${preset.width} × ${preset.height}</option>`;
        }).join("");
    }

    function buildLab() {
        if (lab && lab.isConnected) return lab;

        lab = document.createElement("section");
        lab.className = "dbx-viewport-lab";
        lab.hidden = true;
        lab.setAttribute("aria-label", "Viewport-Vorschau");
        lab.innerHTML = `
            <header class="dbx-viewport-toolbar">
                <div class="dbx-viewport-toolbar-brand">
                    <i class="bi bi-bounding-box-circles" aria-hidden="true"></i>
                    <span>Viewport-Test</span>
                </div>
                <label class="dbx-viewport-profile-select">
                    <span class="visually-hidden">Auflösung</span>
                    <select data-dbx-viewport-select>${optionMarkup()}</select>
                </label>
                <span class="dbx-viewport-dimensions" data-dbx-viewport-dimensions></span>
                <span class="dbx-viewport-scale" data-dbx-viewport-scale></span>
                <div class="dbx-viewport-toolbar-actions">
                    <button type="button" data-dbx-viewport-rotate title="Hoch- und Querformat tauschen" aria-label="Hoch- und Querformat tauschen">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <button type="button" data-dbx-viewport-reload title="Vorschau neu laden" aria-label="Vorschau neu laden">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <button type="button" data-dbx-viewport-open title="Vorschau in neuem Tab öffnen" aria-label="Vorschau in neuem Tab öffnen">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </button>
                    <button type="button" class="dbx-viewport-close" data-dbx-viewport-close title="Viewport-Test schließen" aria-label="Viewport-Test schließen">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </header>
            <div class="dbx-viewport-stage" data-dbx-viewport-stage>
                <div class="dbx-viewport-device" data-dbx-viewport-device>
                    <iframe data-dbx-viewport-frame scrolling="yes" title="Responsive Vorschau der aktuellen Seite"></iframe>
                </div>
            </div>`;

        document.body.appendChild(lab);
        bindLabEvents(lab);
        return lab;
    }

    function findPreset(width, height) {
        return Object.keys(PRESETS).find(key => {
            const preset = PRESETS[key];
            return preset.width === width && preset.height === height;
        }) || "";
    }

    function activeDimensions() {
        const frame = lab && lab.querySelector("[data-dbx-viewport-frame]");
        return {
            width: Number(frame && frame.dataset.viewportWidth) || PRESETS[currentKey].width,
            height: Number(frame && frame.dataset.viewportHeight) || PRESETS[currentKey].height
        };
    }

    function fitLab() {
        if (!lab || lab.hidden) return;

        const stage = lab.querySelector("[data-dbx-viewport-stage]");
        const device = lab.querySelector("[data-dbx-viewport-device]");
        const frame = lab.querySelector("[data-dbx-viewport-frame]");
        const scaleLabel = lab.querySelector("[data-dbx-viewport-scale]");
        const dimensions = activeDimensions();
        const rect = stage.getBoundingClientRect();
        const availableWidth = Math.max(240, rect.width - 42);
        const availableHeight = Math.max(180, rect.height - 42);
        const scale = Math.min(1, availableWidth / dimensions.width, availableHeight / dimensions.height);

        device.style.width = Math.max(1, Math.round(dimensions.width * scale)) + "px";
        device.style.height = Math.max(1, Math.round(dimensions.height * scale)) + "px";
        frame.style.width = dimensions.width + "px";
        frame.style.height = dimensions.height + "px";
        frame.style.transform = `scale(${scale})`;
        scaleLabel.textContent = Math.round(scale * 100) + "%";
    }

    function scheduleFit() {
        if (resizeFrame) window.cancelAnimationFrame(resizeFrame);
        resizeFrame = window.requestAnimationFrame(function () {
            resizeFrame = 0;
            fitLab();
        });
    }

    function syncMenuState(key) {
        document.querySelectorAll("[data-dbx-viewport-preset]").forEach(link => {
            const active = link.getAttribute("data-dbx-viewport-preset") === key;
            link.classList.toggle("is-active", active);
            link.setAttribute("aria-current", active ? "true" : "false");
        });
    }

    function applyDimensions(width, height, key) {
        const frame = lab.querySelector("[data-dbx-viewport-frame]");
        const label = lab.querySelector("[data-dbx-viewport-dimensions]");
        const select = lab.querySelector("[data-dbx-viewport-select]");

        frame.dataset.viewportWidth = String(width);
        frame.dataset.viewportHeight = String(height);
        label.textContent = width + " × " + height + " px";

        if (key && PRESETS[key]) {
            currentKey = key;
            select.value = key;
            syncMenuState(key);
        }

        scheduleFit();
    }

    function applyPreset(key) {
        const preset = PRESETS[key];
        if (!preset) return;
        applyDimensions(preset.width, preset.height, key);
    }

    function openLab(key) {
        const root = buildLab();
        const frame = root.querySelector("[data-dbx-viewport-frame]");
        root.hidden = false;
        document.documentElement.classList.add("dbx-viewport-lab-open");

        applyPreset(PRESETS[key] ? key : currentKey);
        if (!frame.getAttribute("src")) frame.setAttribute("src", previewUrl());
        scheduleFit();
    }

    function closeLab() {
        if (!lab) return;
        lab.hidden = true;
        document.documentElement.classList.remove("dbx-viewport-lab-open");
    }

    function rotateLab() {
        const dimensions = activeDimensions();
        const rotatedKey = findPreset(dimensions.height, dimensions.width);
        applyDimensions(dimensions.height, dimensions.width, rotatedKey);
    }

    function reloadLab() {
        const frame = lab && lab.querySelector("[data-dbx-viewport-frame]");
        if (!frame) return;
        try {
            frame.contentWindow.location.reload();
        } catch (e) {
            frame.setAttribute("src", previewUrl());
        }
    }

    function bindLabEvents(root) {
        root.querySelector("[data-dbx-viewport-select]").addEventListener("change", function () {
            applyPreset(this.value);
        });
        root.querySelector("[data-dbx-viewport-rotate]").addEventListener("click", rotateLab);
        root.querySelector("[data-dbx-viewport-reload]").addEventListener("click", reloadLab);
        root.querySelector("[data-dbx-viewport-open]").addEventListener("click", function () {
            const frame = root.querySelector("[data-dbx-viewport-frame]");
            const link = document.createElement("a");
            link.href = frame.getAttribute("src") || previewUrl();
            link.target = "_blank";
            link.rel = "noopener";
            link.hidden = true;
            document.body.appendChild(link);
            link.click();
            link.remove();
        });
        root.querySelector("[data-dbx-viewport-close]").addEventListener("click", closeLab);
    }

    function bindMenu(root) {
        root.querySelectorAll("[data-dbx-viewport-preset]").forEach(link => {
            link.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                const menuItem = link.closest(".dbx-menu-item.is-open");
                if (menuItem) {
                    menuItem.classList.remove("is-open");
                    const toggle = menuItem.querySelector(":scope > .dbx-menu-link");
                    if (toggle) toggle.setAttribute("aria-expanded", "false");
                }
                openLab(link.getAttribute("data-dbx-viewport-preset"));
            });
        });
    }

    function init(root) {
        if (isPreviewPage()) {
            document.documentElement.classList.add("dbx-viewport-preview-page");
            return;
        }
        bindMenu(root);
        window.addEventListener("resize", scheduleFit, { passive: true });
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && lab && !lab.hidden) closeLab();
        });
    }

    dbx.viewport = {
        presets: PRESETS,
        open: openLab,
        close: closeLab,
        apply: applyPreset
    };

    dbx.feature.register(LIB, {
        scope: "global",
        priority: "last",
        css: [
            ["css", "root", "design/dbxapp/css/c-viewport.css"]
        ],
        js: [],
        init
    });

})(window, document);
