/*!
 * dbxapp utilities.js
 * Global helpers: clearable inputs, back-to-top, app/web mode, skin/theme.
 */
(function (window, document) {
    "use strict";

    const LIB = "utilities";
    const ID = "global";
    const MODE_KEY = "mode";
    const THEME_KEY = "theme";
    const SKIN_KEY = "skin";
    const COLLAPSE_LIB = "collapse";
    const CONSENT_KEY = "consent";
    const CONSENT_PRIVACY_URL = "datenschutz";
    const CONSENT_IMPRESSUM_URL = "impressum";
    const DEFAULT_CONSENT = { cookies: true, youtube: false, decided: false, ts: 0 };
    let leaveGuardEnabled = true;
    let leaveGuardAllowUntil = 0;

    function hasDbx() {
        return !!(window.dbx && window.dbx.feature);
    }

    function onReady(fn) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fn, { once: true });
        } else {
            fn();
        }
    }

    function allowLeaveGuardNavigation(durationMs = 15000) {
        leaveGuardAllowUntil = Date.now() + Math.max(250, Number(durationMs) || 15000);
    }

    function isSameWebsiteNavigation(url) {
        try {
            const target = new URL(url, window.location.href);
            return target.origin === window.location.origin;
        } catch (_) {
            return false;
        }
    }

    function allowIfInternalNavigation(url, durationMs = 15000) {
        if (!isSameWebsiteNavigation(url)) {
            return false;
        }
        allowLeaveGuardNavigation(durationMs);
        return true;
    }

    function initLeaveGuard() {
        if (document.__dbxUtilitiesLeaveGuardBound) {
            return;
        }
        document.__dbxUtilitiesLeaveGuardBound = true;

        document.addEventListener("click", function (event) {
            const explicit = event.target && event.target.closest
                ? event.target.closest("[data-dbx-leave-allow]")
                : null;
            if (explicit) {
                allowLeaveGuardNavigation();
                return;
            }

            const link = event.target && event.target.closest
                ? event.target.closest("a[href]")
                : null;
            if (!link) {
                return;
            }
            const target = String(link.getAttribute("target") || "").toLowerCase();
            if (target && target !== "_self") {
                return;
            }
            // Ein normaler Link ist eine bewusste Navigation und kein
            // Schliessen des Tabs/Fensters. Das gilt auch fuer externe Ziele.
            allowLeaveGuardNavigation();
        }, true);

        document.addEventListener("submit", function (event) {
            const form = event.target;
            if (!form) {
                return;
            }
            const target = String(form.getAttribute("target") || "").toLowerCase();
            if (target && target !== "_self") {
                return;
            }
            allowLeaveGuardNavigation();
        }, true);

        // Ein Reload verlaesst fachlich weder Datensatz noch Anwendung. Die
        // native Browserwarnung ist dabei nur stoerend. Tastatur-Reloads
        // werden auch in Browsern ohne Navigation API erkannt; Chromium und
        // andere moderne Browser melden zusaetzlich Toolbar-/API-Reloads hier.
        document.addEventListener("keydown", function (event) {
            const key = String(event.key || "").toLowerCase();
            if (key === "f5" || ((event.ctrlKey || event.metaKey) && key === "r")) {
                allowLeaveGuardNavigation();
            }
        }, true);

        if (window.navigation && typeof window.navigation.addEventListener === "function") {
            window.navigation.addEventListener("navigate", function (event) {
                // Der Navigation-Event wird fuer Reload, History-Navigation
                // und programmatische Seitenwechsel ausgeloest, nicht jedoch
                // fuer das reine Schliessen eines Tabs oder Fensters.
                if (event) {
                    allowLeaveGuardNavigation();
                }
            });
        }

        window.addEventListener("beforeunload", function (event) {
            if (!leaveGuardEnabled || Date.now() <= leaveGuardAllowUntil) {
                return;
            }
            event.preventDefault();
            event.returnValue = "";
        });
    }

    function storeGet(key, def) {
        return hasDbx() && typeof dbx.uiGet === "function" ? dbx.uiGet(LIB, ID, key, def) : def;
    }

    function storeSet(key, value) {
        if (hasDbx() && typeof dbx.uiSet === "function") {
            dbx.uiSet(LIB, ID, key, value);
        }
    }

    function currentMode() {
        return document.body.classList.contains("dbx-web") ? "dbx-web" : "dbx-app";
    }

    function currentTheme() {
        return document.body.classList.contains("theme-dark") || document.body.classList.contains("dark")
            ? "dark"
            : "light";
    }

    function skinIdsForDesign() {
        const design = String(getDesign() || "").toLowerCase();
        const found = [];

        document.querySelectorAll(".dbx-design-skin-opt[data-design][data-skin]").forEach(opt => {
            if (String(opt.getAttribute("data-design") || "").toLowerCase() !== design) {
                return;
            }
            const skin = String(opt.getAttribute("data-skin") || "").toLowerCase();
            if (/^[a-z0-9][a-z0-9_-]*$/.test(skin) && found.indexOf(skin) === -1) {
                found.push(skin);
            }
        });

        if (!found.length) {
            const serverSkin = String(getServerSkin() || "").toLowerCase();
            if (/^[a-z0-9][a-z0-9_-]*$/.test(serverSkin)) {
                found.push(serverSkin);
            }
        }

        return found;
    }

    function defaultSkinForDesign() {
        const skins = skinIdsForDesign();
        const serverSkin = String(getServerSkin() || "").toLowerCase();
        if (skins.indexOf(serverSkin) !== -1) {
            return serverSkin;
        }
        if (skins.indexOf("blau") !== -1) {
            return "blau";
        }
        if (skins.indexOf("hell") !== -1) {
            return "hell";
        }
        return skins[0] || "blau";
    }

    function isValidSkin(skin) {
        return skinIdsForDesign().indexOf(String(skin || "")) !== -1;
    }

    function getDesign() {
        if (document.body && document.body.getAttribute("data-dbx-design")) {
            return document.body.getAttribute("data-dbx-design");
        }
        if (window.dbx && dbx.config && dbx.config.design) {
            return dbx.config.design;
        }
        return "dbxapp";
    }

    function getServerSkin() {
        if (document.body && document.body.getAttribute("data-dbx-skin")) {
            return document.body.getAttribute("data-dbx-skin");
        }
        return "blau";
    }

    function skinStoreKey() {
        const design = String(getDesign() || "dbxapp").toLowerCase().replace(/[^a-z0-9_-]/g, "");
        return SKIN_KEY + ":" + (design || "dbxapp");
    }

    function requestDefinesSkin() {
        try {
            return new URL(window.location.href).searchParams.has("dbx_color");
        } catch (_) {
            return false;
        }
    }

    function parseSkinFromBody() {
        if (!document.body || !document.body.className) {
            return getServerSkin();
        }

        const match = document.body.className.match(/\bskin-([a-z0-9][a-z0-9_-]*)\b/);
        return match ? match[1] : getServerSkin();
    }

    function skinCssHref(skin) {
        const design = getDesign();
        const root = (window.dbx && dbx.config && dbx.config.rootPath) ? dbx.config.rootPath : "";
        return root + "design/" + design + "/css/skin-" + skin + ".css";
    }

    function findSkinStylesheet() {
        return document.querySelector('link[rel="stylesheet"][href*="skin-"]');
    }

    function updateModeIcons(mode) {
        document.querySelectorAll(".dbxModeIcon").forEach(icon => {
            icon.classList.toggle("bi-window", mode === "dbx-app");
            icon.classList.toggle("bi-globe", mode !== "dbx-app");
        });
    }

    function updateThemeIcons(theme) {
        document.querySelectorAll(".dbxThemeIcon").forEach(icon => {
            icon.classList.toggle("bi-moon", theme === "dark");
            icon.classList.toggle("bi-sun", theme !== "dark");
        });
    }

    function updateSkinMenuState(skin) {
        document.querySelectorAll(".dbx-skin-opt").forEach(opt => {
            const item = opt.closest("li");
            const active = opt.getAttribute("data-skin") === skin;
            opt.classList.toggle("is-active", active);
            if (item) {
                item.classList.toggle("is-active", active);
            }

            let mark = opt.querySelector(".dbx-skin-check");
            if (active) {
                if (!mark) {
                    mark = document.createElement("i");
                    mark.className = "bi bi-check2 dbx-skin-check";
                    opt.appendChild(mark);
                }
            } else if (mark) {
                mark.remove();
            }
        });
    }

    function attr(el, name, def = "") {
        if (!el || !el.getAttribute) return def;
        const value = el.getAttribute(name);
        return value == null ? def : String(value).trim();
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === "function") {
            return window.CSS.escape(value);
        }

        return String(value || "").replace(/["\\]/g, "\\$&");
    }

    function escapeHtml(value) {
        const div = document.createElement("div");
        div.textContent = value == null ? "" : String(value);
        return div.innerHTML;
    }

    let tooltipEl = null;
    let tooltipTarget = null;
    let tooltipBound = false;
    let tooltipStyleRequested = false;
    let tooltipPointerTarget = null;
    let tooltipFocusTarget = null;
    let tooltipAttributeObserver = null;
    let tooltipPositionFrame = 0;
    let tooltipPositionDeadline = 0;
    let tooltipPositionRect = "";
    let tooltipStableFrames = 0;
    let tooltipSuppressedTitles = [];
    let tooltipAriaTarget = null;
    let tooltipAriaDescription = null;
    const TOOLTIP_ID = "dbx-utility-tooltip";
    const TOOLTIP_SELECTOR = "[data-dbx-tooltip],[data-dbx-errormsg]";

    function ensureTooltipStyle() {
        if (tooltipStyleRequested) return;
        tooltipStyleRequested = true;
        if (window.dbx && typeof window.dbx.add_css === "function") {
            window.dbx.add_css("design", "c-tooltip.css");
        }
    }

    function closestTooltipTarget(node) {
        const target = node && node.closest ? node.closest(TOOLTIP_SELECTOR) : null;
        if (!target || target === tooltipEl) return null;
        return target;
    }

    function suppressNativeTitles(target) {
        tooltipSuppressedTitles = [];
        let current = target;
        while (current && current !== document.body && current !== document.documentElement) {
            if (current.hasAttribute && current.hasAttribute("title")) {
                tooltipSuppressedTitles.push({
                    element: current,
                    title: current.getAttribute("title") || ""
                });
                current.removeAttribute("title");
            }
            current = current.parentElement;
        }
    }

    function restoreNativeTitles() {
        tooltipSuppressedTitles.forEach(item => {
            if (item.element && item.element.isConnected && !item.element.hasAttribute("title")) {
                item.element.setAttribute("title", item.title);
            }
        });
        tooltipSuppressedTitles = [];
    }

    function bindTooltipDescription(target) {
        tooltipAriaTarget = target;
        tooltipAriaDescription = target.getAttribute("aria-describedby");
        const ids = String(tooltipAriaDescription || "").split(/\s+/).filter(Boolean);
        if (!ids.includes(TOOLTIP_ID)) ids.push(TOOLTIP_ID);
        target.setAttribute("aria-describedby", ids.join(" "));
    }

    function restoreTooltipDescription() {
        if (!tooltipAriaTarget) return;
        if (tooltipAriaDescription == null || tooltipAriaDescription === "") {
            tooltipAriaTarget.removeAttribute("aria-describedby");
        } else {
            tooltipAriaTarget.setAttribute("aria-describedby", tooltipAriaDescription);
        }
        tooltipAriaTarget = null;
        tooltipAriaDescription = null;
    }

    function sanitizeTooltipHtml(value) {
        const allowed = new Set([
            "B", "STRONG", "EM", "I", "U", "S", "SMALL", "MARK", "BR",
            "SPAN", "DIV", "P", "H1", "H2", "H3", "H4", "H5", "H6",
            "BLOCKQUOTE", "PRE", "HR", "UL", "OL", "LI", "DL", "DT", "DD",
            "CODE", "KBD", "SAMP", "SUB", "SUP", "TABLE", "THEAD",
            "TBODY", "TFOOT", "TR", "TH", "TD"
        ]);
        const removeEntirely = new Set([
            "SCRIPT", "STYLE", "TEMPLATE", "IFRAME", "OBJECT", "EMBED",
            "LINK", "META", "BASE", "FORM", "INPUT", "BUTTON", "TEXTAREA",
            "SELECT", "OPTION", "SVG", "MATH", "IMG", "VIDEO", "AUDIO",
            "SOURCE", "CANVAS"
        ]);
        const tpl = document.createElement("template");
        tpl.innerHTML = String(value == null ? "" : value);

        Array.from(tpl.content.querySelectorAll("*")).forEach(element => {
            if (removeEntirely.has(element.tagName)) {
                element.remove();
                return;
            }
            if (!allowed.has(element.tagName)) {
                element.replaceWith(...Array.from(element.childNodes));
                return;
            }
            Array.from(element.attributes).forEach(attribute => {
                if (attribute.name !== "class" && attribute.name !== "aria-hidden") {
                    element.removeAttribute(attribute.name);
                }
            });
        });

        return tpl.innerHTML;
    }

    function normalizeTooltipHtml(value, asHtml) {
        let html = String(value == null ? "" : value).trim();
        if (!html || html === "{tooltip}" || html === "#tooltip#") return "";
        if (/^(?:&nbsp;|\u00a0|\s)+$/i.test(html)) return "";
        html = asHtml ? sanitizeTooltipHtml(html) : escapeHtml(html);

        const tpl = document.createElement("template");
        tpl.innerHTML = html;
        const text = (tpl.content.textContent || "").replace(/\u00a0/g, " ").trim();
        const hasVisualNode = !!tpl.content.querySelector("table,ul,ol,li,dl,br,hr");
        if (!text && !hasVisualNode) return "";
        return html;
    }

    function tooltipHtmlList(rows, options) {
        options = options || {};
        rows = Array.isArray(rows) ? rows : [];
        if (!rows.length) {
            return `<div class="dbx-utility-tooltip-empty">${escapeHtml(options.empty || "Keine Verwendung")}</div>`;
        }
        const title = options.title
            ? `<span class="dbx-utility-tooltip-title">${escapeHtml(options.title)}</span>`
            : "";
        return title + `<div class="dbx-utility-tooltip-list">` + rows.map(row => {
            const id = row && row.id != null ? row.id : "";
            const folder = row && row.folder != null ? row.folder : "";
            const text = row && row.title != null ? row.title : "";
            return `<div class="dbx-utility-tooltip-row">`
                + `<span class="dbx-utility-tooltip-id">${escapeHtml(id)}</span>`
                + `<span class="dbx-utility-tooltip-folder">${escapeHtml(folder)}</span>`
                + `<span class="dbx-utility-tooltip-title-text">${escapeHtml(text)}</span>`
                + `</div>`;
        }).join("") + `</div>`;
    }

    function tooltipData(target) {
        if (!target) return "";
        const errorText = target.getAttribute("data-dbx-errormsg") || "";
        const normalizedErrorText = normalizeTooltipHtml(errorText, true);
        if (normalizedErrorText) {
            return {
                html: normalizedErrorText,
                kind: "error"
            };
        }
        const text = target.getAttribute("data-dbx-tooltip") || "";
        const normalizedText = normalizeTooltipHtml(text, true);
        if (normalizedText) {
            return {
                html: normalizedText,
                kind: "tooltip"
            };
        }
        return null;
    }

    function positionTooltip(target) {
        if (!tooltipEl || !target || !target.getBoundingClientRect) return;
        const rect = target.getBoundingClientRect();
        const gap = 10;
        const margin = 8;
        const width = tooltipEl.offsetWidth || 0;
        const height = tooltipEl.offsetHeight || 0;
        const center = rect.left + (rect.width / 2);
        const spaceAbove = rect.top - margin - gap;
        const spaceBelow = window.innerHeight - rect.bottom - margin - gap;
        const placement = (height <= spaceAbove || spaceAbove >= spaceBelow)
            ? "top"
            : "bottom";
        let top = placement === "top"
            ? rect.top - height - gap
            : rect.bottom + gap;
        let left = center - (width / 2);

        top = Math.max(margin, Math.min(top, window.innerHeight - height - margin));
        left = Math.max(margin, Math.min(left, window.innerWidth - width - margin));
        const arrowLeft = Math.max(14, Math.min(center - left, width - 14));

        tooltipEl.dataset.placement = placement;
        tooltipEl.style.left = `${Math.round(left)}px`;
        tooltipEl.style.top = `${Math.round(top)}px`;
        tooltipEl.style.setProperty("--dbx-tooltip-arrow-left", `${Math.round(arrowLeft)}px`);
    }

    function stopTooltipPositionTracking() {
        if (tooltipPositionFrame) window.cancelAnimationFrame(tooltipPositionFrame);
        tooltipPositionFrame = 0;
        tooltipPositionDeadline = 0;
        tooltipPositionRect = "";
        tooltipStableFrames = 0;
    }

    function trackTooltipPosition(duration) {
        if (!tooltipTarget || !tooltipEl || tooltipEl.hidden) return;
        tooltipPositionDeadline = Math.max(tooltipPositionDeadline, performance.now() + (duration || 1200));
        if (tooltipPositionFrame) return;

        const update = () => {
            tooltipPositionFrame = 0;
            if (!tooltipTarget || !tooltipEl || tooltipEl.hidden || !tooltipTarget.isConnected) return;

            const rect = tooltipTarget.getBoundingClientRect();
            const rectKey = [rect.left, rect.top, rect.width, rect.height]
                .map(value => Math.round(value * 10) / 10)
                .join(":");
            if (rectKey !== tooltipPositionRect) {
                tooltipPositionRect = rectKey;
                tooltipStableFrames = 0;
                positionTooltip(tooltipTarget);
            } else {
                tooltipStableFrames++;
            }

            if (performance.now() < tooltipPositionDeadline && tooltipStableFrames < 12) {
                tooltipPositionFrame = window.requestAnimationFrame(update);
            }
        };

        tooltipPositionFrame = window.requestAnimationFrame(update);
    }

    function showTooltip(target) {
        const data = tooltipData(target);
        if (!data || !data.html) return;
        if (tooltipTarget && tooltipTarget !== target) {
            hideTooltip();
        }
        ensureTooltipStyle();
        if (!tooltipEl) {
            tooltipEl = document.createElement("div");
            tooltipEl.id = TOOLTIP_ID;
            tooltipEl.className = "dbx-utility-tooltip";
            tooltipEl.setAttribute("role", "tooltip");
            tooltipEl.setAttribute("data-dbx-layer", "tooltip");
            tooltipEl.setAttribute("aria-hidden", "true");
            document.body.appendChild(tooltipEl);
        }
        if (tooltipTarget !== target) {
            suppressNativeTitles(target);
            bindTooltipDescription(target);
        }
        tooltipTarget = target;
        tooltipEl.dataset.kind = data.kind || "tooltip";
        tooltipEl.innerHTML = data.html;
        tooltipEl.style.left = "-9999px";
        tooltipEl.style.top = "-9999px";
        if (window.dbx && dbx.uiLayer && typeof dbx.uiLayer.next === "function") {
            tooltipEl.style.zIndex = String(dbx.uiLayer.next({
                floor: 5000,
                step: 20,
                exclude: [tooltipEl]
            }));
        }
        tooltipEl.hidden = false;
        tooltipEl.setAttribute("aria-hidden", "false");
        positionTooltip(target);
        trackTooltipPosition(1200);
    }

    function hideTooltip(target) {
        if (target && tooltipTarget && target !== tooltipTarget) return;
        restoreNativeTitles();
        restoreTooltipDescription();
        stopTooltipPositionTracking();
        tooltipTarget = null;
        if (tooltipEl) {
            tooltipEl.hidden = true;
            tooltipEl.setAttribute("aria-hidden", "true");
        }
    }

    function initHtmlTooltips(root) {
        ensureTooltipStyle();
        if (tooltipBound) return;
        tooltipBound = true;

        tooltipAttributeObserver = new MutationObserver(mutations => {
            if (!tooltipTarget) return;
            if (!mutations.some(mutation => mutation.target === tooltipTarget)) return;
            if (!tooltipTarget.matches(TOOLTIP_SELECTOR) || !tooltipData(tooltipTarget)) {
                hideTooltip(tooltipTarget);
                return;
            }
            showTooltip(tooltipTarget);
        });
        tooltipAttributeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ["data-dbx-tooltip", "data-dbx-errormsg"],
            subtree: true
        });

        document.addEventListener("mouseover", function (e) {
            const target = closestTooltipTarget(e.target);
            if (!target || target.contains(e.relatedTarget)) return;
            tooltipPointerTarget = target;
            showTooltip(target);
        }, true);

        document.addEventListener("mouseout", function (e) {
            let target = closestTooltipTarget(e.target);
            if (!target && tooltipTarget && (e.target === tooltipTarget || (tooltipTarget.contains && tooltipTarget.contains(e.target)))) {
                target = tooltipTarget;
            }
            if (!target || target.contains(e.relatedTarget)) return;
            if (tooltipPointerTarget === target) tooltipPointerTarget = null;
            if (tooltipFocusTarget === target) return;
            hideTooltip(target);
        }, true);

        document.addEventListener("focusin", function (e) {
            const target = closestTooltipTarget(e.target);
            if (!target) return;
            tooltipFocusTarget = target;
            showTooltip(target);
        }, true);

        document.addEventListener("focusout", function (e) {
            let target = closestTooltipTarget(e.target);
            if (!target && tooltipTarget && (e.target === tooltipTarget || (tooltipTarget.contains && tooltipTarget.contains(e.target)))) {
                target = tooltipTarget;
            }
            if (!target) return;
            if (tooltipFocusTarget === target) tooltipFocusTarget = null;
            if (tooltipPointerTarget === target) return;
            hideTooltip(target);
        }, true);

        document.addEventListener("scroll", function () {
            tooltipPointerTarget = null;
            tooltipFocusTarget = null;
            hideTooltip();
        }, true);
        window.addEventListener("resize", function () {
            tooltipPointerTarget = null;
            tooltipFocusTarget = null;
            hideTooltip();
        });
    }

    function initPasswordCriteria(root) {
        root = root || document;
        const rulesList = [];
        if (root.matches && root.matches("[data-dbx-password-rules]")) {
            rulesList.push(root);
        }
        if (root.querySelectorAll) {
            root.querySelectorAll("[data-dbx-password-rules]").forEach(
                rules => rulesList.push(rules)
            );
        }

        rulesList.forEach(function (rules) {
            if (rules.dataset.dbxPasswordRulesBound === "1") {
                return;
            }
            const form = rules.closest("form") || document;
            const passwordName = rules.dataset.passwordInput || "";
            const repeatName = rules.dataset.passwordRepeat || "";
            const password = passwordName && form.elements
                ? form.elements.namedItem(passwordName)
                : null;
            const repeat = repeatName && form.elements
                ? form.elements.namedItem(repeatName)
                : null;
            if (!password || !repeat) {
                return;
            }
            rules.dataset.dbxPasswordRulesBound = "1";

            function setRule(name, valid, active) {
                const item = rules.querySelector(
                    "[data-password-rule='" + name + "']"
                );
                if (!item) return;
                const icon = item.querySelector("i");
                item.classList.toggle("is-valid", active && valid);
                item.classList.toggle("is-missing", active && !valid);
                if (icon) {
                    icon.className = "bi " + (!active
                        ? "bi-circle"
                        : (valid
                            ? "bi-check-circle-fill"
                            : "bi-x-circle-fill"));
                }
            }

            function updateRules() {
                const value = password.value || "";
                const repeated = repeat.value || "";
                const active = value !== "" || repeated !== "";
                const minimumLength = Math.max(
                    6,
                    Math.min(128, Number(rules.dataset.passwordMin) || 6)
                );
                const minimumLabel = rules.querySelector(
                    "[data-password-min-label]"
                );
                if (minimumLabel) {
                    minimumLabel.textContent = String(minimumLength);
                }
                setRule(
                    "length",
                    Array.from(value).length >= minimumLength,
                    active
                );
                setRule(
                    "letters",
                    /[A-Z]/.test(value) && /[a-z]/.test(value),
                    active
                );
                setRule("number", /[0-9]/.test(value), active);
                setRule("special", /[^A-Za-z0-9]/.test(value), active);
                setRule(
                    "match",
                    value !== "" && value === repeated,
                    active
                );
            }

            password.addEventListener("input", updateRules);
            repeat.addEventListener("input", updateRules);
            updateRules();
        });
    }

    function getCollapseState(key) {
        if (!key || !window.dbx || typeof dbx.uiGet !== "function") return "";
        return dbx.uiGet(COLLAPSE_LIB, key, "state", "");
    }

    function setCollapseState(key, collapsed) {
        if (!key || !window.dbx || typeof dbx.uiSet !== "function") return;
        dbx.uiSet(COLLAPSE_LIB, key, "state", collapsed ? "collapsed" : "open");
    }

    function collapseStateKey(button, panel, target) {
        return attr(button, "data-collapse-state-key",
            attr(panel, "data-collapse-state-key",
                attr(button, "data-ui-state-key",
                    attr(panel, "data-ui-state-key", target)
                )
            )
        );
    }

    function setCollapseUi(button, panel, collapsed) {
        panel.classList.toggle("is-collapsed", collapsed);
        button.setAttribute("aria-expanded", collapsed ? "false" : "true");

        const label = button.querySelector("[data-collapse-label]") || button.querySelector("span");
        if (label) {
            label.textContent = collapsed ? "Aufklappen" : "Zuklappen";
        }
    }

    function initCollapsible(root) {
        const ctx = root && root.querySelectorAll ? root : document;
        const buttons = [];

        if (ctx.nodeType === 1 && ctx.matches && ctx.matches("[data-collapse-toggle],[data-admin-collapse-toggle]")) {
            buttons.push(ctx);
        }

        ctx.querySelectorAll("[data-collapse-toggle],[data-admin-collapse-toggle]").forEach(button => {
            buttons.push(button);
        });

        buttons.forEach(button => {
            if (button.__dbxUtilityCollapse) return;
            button.__dbxUtilityCollapse = true;

            const target = attr(button, "data-collapse-toggle", attr(button, "data-admin-collapse-toggle", ""));
            if (!target) return;

            const safeTarget = cssEscape(target);
            const selector = [
                `[data-collapse-panel="${safeTarget}"]`,
                `[data-admin-collapsible-panel="${safeTarget}"]`
            ].join(",");
            const panel = button.closest(selector) || ctx.querySelector(selector);
            if (!panel) return;

            const stateKey = collapseStateKey(button, panel, target);
            const storedState = getCollapseState(stateKey);
            if (storedState === "collapsed" || storedState === "open") {
                setCollapseUi(button, panel, storedState === "collapsed");
            }

            button.addEventListener("click", () => {
                const collapsed = !panel.classList.contains("is-collapsed");
                setCollapseUi(button, panel, collapsed);
                setCollapseState(stateKey, collapsed);

                if (window.dbx && dbx.event && typeof dbx.event.emit === "function") {
                    dbx.event.emit("ui:collapse", {
                        key: stateKey,
                        target,
                        collapsed,
                        panel,
                        button
                    });
                }
            });
        });
    }

    function syncSkinToServer(skin) {
        try {
            const url = new URL(window.location.href);
            url.searchParams.set("dbx_color", skin);
            window.history.replaceState({}, "", url.pathname + url.search + url.hash);
        } catch (_) {}

        try {
            if (dbx.ajax && typeof dbx.ajax.request === "function") {
                const syncUrl = new URL(window.location.href);
                syncUrl.searchParams.set("dbx_color", skin);
                dbx.ajax.request({
                    url: syncUrl.toString(),
                    method: "GET",
                    mode: "html"
                }).catch(function () {});
            }
        } catch (_) {}
    }

    function applySkin(skin, persist) {
        if (!isValidSkin(skin)) {
            skin = defaultSkinForDesign();
        }

        const link = findSkinStylesheet();
        if (link) {
            link.href = skinCssHref(skin);
        }

        Array.from(document.body.classList).forEach(function (name) {
            if (/^skin-[a-z0-9][a-z0-9_-]*$/.test(name)) {
                document.body.classList.remove(name);
            }
        });
        document.body.classList.add("skin-" + skin);
        document.body.setAttribute("data-dbx-skin", skin);

        document.body.classList.remove("light", "dark", "theme-light", "theme-dark");
        if (skin === "dunkel") {
            document.body.classList.add("theme-dark");
        } else {
            document.body.classList.add("theme-light");
        }

        updateSkinMenuState(skin);
        updateThemeIcons(skin === "dunkel" ? "dark" : "light");

        if (persist) {
            storeSet(skinStoreKey(), skin);
            storeSet(THEME_KEY, skin === "dunkel" ? "dark" : "light");
            syncSkinToServer(skin);
        }

        if (window.dbx && dbx.event && typeof dbx.event.emit === "function") {
            dbx.event.emit("utilities:skin", { id: ID, skin: skin });
        }
    }

    function applyMode(mode, persist) {
        if (mode !== "dbx-web" && mode !== "dbx-app") {
            mode = currentMode();
        }

        document.body.classList.remove("dbx-web", "dbx-app");
        document.body.classList.add(mode);
        updateModeIcons(mode);

        if (persist) {
            storeSet(MODE_KEY, mode);
        }

        if (window.dbx && dbx.event && typeof dbx.event.emit === "function") {
            dbx.event.emit("utilities:mode", { id: ID, mode: mode });
        }

        updateBackToTopState();
    }

    function applyTheme(theme, persist) {
        if (theme !== "light" && theme !== "dark") {
            theme = currentTheme();
        }

        document.body.classList.remove("light", "dark", "theme-light", "theme-dark");
        document.body.classList.add(theme === "dark" ? "theme-dark" : "theme-light");
        updateThemeIcons(theme);

        if (persist) {
            storeSet(THEME_KEY, theme);
        }

        if (window.dbx && dbx.event && typeof dbx.event.emit === "function") {
            dbx.event.emit("utilities:theme", { id: ID, theme: theme });
        }
    }

    function appScrollElement() {
        return document.querySelector(".dbx-content") || document.scrollingElement || document.documentElement;
    }

    function currentScrollTop() {
        if (document.body.classList.contains("dbx-app")) {
            const el = appScrollElement();
            return el ? el.scrollTop : 0;
        }

        return window.scrollY || document.documentElement.scrollTop || 0;
    }

    function updateBackToTopState() {
        const btn = document.getElementById("dbxBackToTop");
        if (!btn) return;
        btn.classList.toggle("show", currentScrollTop() > 200);
    }

    function scrollBackToTop() {
        if (document.body.classList.contains("dbx-app")) {
            const el = appScrollElement();
            if (el && typeof el.scrollTo === "function") {
                el.scrollTo({ top: 0, behavior: "smooth" });
            }
            return;
        }

        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function initClearableInputs() {
        if (document.__dbxUtilitiesClearableBound) {
            document.querySelectorAll(".dbx-clearable").forEach(bindClearableInput);
            return;
        }
        document.__dbxUtilitiesClearableBound = true;

        document.addEventListener("input", function (e) {
            const input = e.target;
            if (!input || !input.classList || !input.classList.contains("dbx-clearable")) return;
            bindClearableInput(input);
        }, true);

        document.addEventListener("click", function (e) {
            const btn = e.target && e.target.closest ? e.target.closest(".dbx-clear-btn") : null;
            if (!btn) return;
            const wrap = btn.closest(".dbx-input-clearable");
            const input = wrap ? wrap.querySelector(".dbx-clearable") : null;
            if (!input) return;
            e.preventDefault();
            input.value = "";
            input.dispatchEvent(new Event("input", { bubbles: true }));
            input.dispatchEvent(new Event("change", { bubbles: true }));
            input.focus();
            syncClearableButton(input, btn);
            if (input.hasAttribute("data-dbx-clear-submit") && input.form) {
                if (typeof input.form.requestSubmit === "function") {
                    input.form.requestSubmit();
                } else {
                    input.form.submit();
                }
            }
        }, true);

        document.querySelectorAll(".dbx-clearable").forEach(bindClearableInput);
    }

    function syncClearableButton(input, btn) {
        if (!input || !btn) return;
        if (String(input.value || "").length > 0) {
            btn.removeAttribute("hidden");
        } else {
            btn.setAttribute("hidden", "");
        }
    }

    function bindClearableInput(input) {
        if (!input || !input.classList || !input.classList.contains("dbx-clearable")) return;

        const wrap = input.closest(".dbx-input-clearable");
        if (!wrap) return;

        let btn = wrap.querySelector(".dbx-clear-btn");
        if (!btn) {
            btn = document.createElement("button");
            btn.type = "button";
            btn.className = "dbx-clear-btn";
            btn.setAttribute("aria-label", "Suche zurücksetzen");
            btn.setAttribute("data-dbx-tooltip", "Suche zurücksetzen");
            btn.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
            wrap.appendChild(btn);
        }

        syncClearableButton(input, btn);
    }

    function initBackToTop() {
        const btn = document.getElementById("dbxBackToTop");
        if (!btn) return;

        if (!document.__dbxUtilitiesBackToTopBound) {
            document.__dbxUtilitiesBackToTopBound = true;
            window.addEventListener("scroll", updateBackToTopState, { passive: true });
            document.addEventListener("scroll", function (e) {
                if (e.target && e.target.classList && e.target.classList.contains("dbx-content")) {
                    updateBackToTopState();
                }
            }, true);
        }

        if (!btn.__dbxUtilitiesBackToTopBound) {
            btn.__dbxUtilitiesBackToTopBound = true;
            btn.addEventListener("click", function (e) {
                e.preventDefault();
                scrollBackToTop();
            });
        }

        updateBackToTopState();
    }

    function initSkin() {
        let storedSkin = storeGet(skinStoreKey(), null);
        if (storedSkin === null && String(getDesign() || "").toLowerCase() === "dbxapp") {
            storedSkin = storeGet(SKIN_KEY, null);
        }
        const bodySkin = parseSkinFromBody();
        const skin = requestDefinesSkin() && isValidSkin(bodySkin)
            ? bodySkin
            : (isValidSkin(storedSkin)
            ? storedSkin
            : (isValidSkin(bodySkin) ? bodySkin : defaultSkinForDesign()));

        applySkin(skin, false);

        if (storedSkin !== skin) {
            storeSet(skinStoreKey(), skin);
        }

        if (bodySkin !== skin) {
            syncSkinToServer(skin);
        }
    }

    function initModeTheme() {
        const storedMode = storeGet(MODE_KEY, null);
        applyMode(storedMode === "dbx-web" || storedMode === "dbx-app" ? storedMode : currentMode(), false);

        initSkin();

        if (document.__dbxUtilitiesModeThemeBound) return;
        document.__dbxUtilitiesModeThemeBound = true;

        document.addEventListener("click", function (e) {
            const modeBtn = e.target && e.target.closest ? e.target.closest(".dbxModeToggle") : null;
            if (modeBtn) {
                e.preventDefault();
                applyMode(currentMode() === "dbx-app" ? "dbx-web" : "dbx-app", true);
                return;
            }

            const skinOpt = e.target && e.target.closest ? e.target.closest(".dbx-skin-opt") : null;
            if (skinOpt) {
                const href = String(skinOpt.getAttribute("href") || "").trim();
                if (href && href !== "#") {
                    return;
                }
                e.preventDefault();
                const skin = skinOpt.getAttribute("data-skin");
                if (skin) {
                    applySkin(skin, true);
                }
            }
        }, true);
    }

    function getConsent() {
        const stored = storeGet(CONSENT_KEY, null);
        if (stored && typeof stored === "object") {
            return Object.assign({}, DEFAULT_CONSENT, stored);
        }
        return Object.assign({}, DEFAULT_CONSENT);
    }

    function setConsent(patch) {
        const next = Object.assign({}, getConsent(), patch || {}, { ts: Date.now() });
        storeSet(CONSENT_KEY, next);
        return next;
    }

    function dispatchConsentChanged(consent) {
        document.dispatchEvent(new CustomEvent("dbx:consent-changed", { detail: consent }));
        if (window.dbx && dbx.event && typeof dbx.event.emit === "function") {
            dbx.event.emit("utilities:consent", consent);
        }
    }

    function isAdminEditorContext() {
        try {
            const url = new URL(window.location.href);
            if (url.searchParams.get("dbx_modul") === "dbxContent_admin") {
                return true;
            }
        } catch (_) {}
        if (document.body && document.body.classList.contains("dbx-cms-admin")) {
            return true;
        }
        return !!document.querySelector(".dbx-cms-editor, .cms-admin, [data-dbx-cms-editor]");
    }

    function openConsentHelpWindow(url, title) {
        const cfg = {
            url: url,
            title: title,
            width: "900",
            height: "85%",
            position: "center-top",
            reload: "1",
            minimizable: "1",
            maximizable: "1"
        };
        if (window.dbx && dbx.openWin && typeof dbx.openWin.open === "function") {
            dbx.openWin.open(cfg);
            return;
        }
        allowIfInternalNavigation(url);
        window.location.href = url;
    }

    function openConsentSettings() {
        openConsentHelpWindow(CONSENT_PRIVACY_URL, "Datenschutz");
    }

    function openImpressum() {
        openConsentHelpWindow(CONSENT_IMPRESSUM_URL, "Impressum");
    }

    function hideConsentBanner() {
        const banner = document.getElementById("dbxConsentBanner");
        if (banner) {
            banner.remove();
        }
        document.body.classList.remove("dbx-consent-open");
    }

    function showConsentBanner() {
        if (document.getElementById("dbxConsentBanner")) {
            return;
        }
        if (isAdminEditorContext()) {
            return;
        }
        if (getConsent().decided) {
            return;
        }

        const banner = document.createElement("div");
        banner.id = "dbxConsentBanner";
        banner.className = "dbx-consent-overlay";
        banner.setAttribute("role", "dialog");
        banner.setAttribute("aria-modal", "true");
        banner.setAttribute("aria-label", "Datenschutz und Cookies");
        banner.innerHTML = ''
            + '<div class="dbx-consent-backdrop" aria-hidden="true"></div>'
            + '<div class="dbx-consent-modal card shadow">'
            + '<div class="card-body dbx-consent-modal-body">'
            + '<h5 class="dbx-consent-modal-title">Datenschutz &amp; Cookies</h5>'
            + '<p class="dbx-consent-modal-text">'
            + 'Auf dieser Website setzen wir technisch notwendige Cookies ein, damit dbXapp '
            + 'funktioniert (z.&nbsp;B. Anmeldung, Sprache, Ihre Einstellungen). '
            + 'Externe Medien wie YouTube-Videos laden wir erst nach Ihrer Zustimmung.'
            + '</p>'
            + '<p class="dbx-consent-modal-links">'
            + '<button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-dbx-consent-link="privacy">Datenschutz</button>'
            + '<span class="dbx-consent-modal-links-sep" aria-hidden="true">·</span>'
            + '<button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-dbx-consent-link="impressum">Impressum</button>'
            + '</p>'
            + '<div class="dbx-consent-banner-actions">'
            + '<button type="button" class="btn btn-outline-secondary btn-sm" data-dbx-consent-action="settings">Einstellungen</button>'
            + '<button type="button" class="btn btn-outline-secondary btn-sm" data-dbx-consent-action="necessary">Nur notwendige</button>'
            + '<button type="button" class="btn btn-primary btn-sm" data-dbx-consent-action="accept-all">Alle akzeptieren</button>'
            + '</div>'
            + '</div>'
            + '</div>';

        document.body.classList.add("dbx-consent-open");
        document.body.appendChild(banner);
    }

    function syncConsentPanel(panel) {
        if (!panel) {
            return;
        }
        const consent = getConsent();
        const youtube = panel.querySelector("[data-dbx-consent-youtube]");
        if (youtube) {
            youtube.checked = !!consent.youtube;
        }
        const cookies = panel.querySelector("[data-dbx-consent-cookies]");
        if (cookies) {
            cookies.checked = true;
            cookies.disabled = true;
        }
    }

    function initConsentPanels(root) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll(".dbx-consent-panel").forEach(syncConsentPanel);
    }

    function youtubeVideoIdFromUrl(url) {
        const value = String(url || "");
        const patterns = [
            /(?:embed\/|v=|youtu\.be\/)([A-Za-z0-9_-]{11})/,
            /[?&]v=([A-Za-z0-9_-]{11})/
        ];
        for (let i = 0; i < patterns.length; i++) {
            const match = value.match(patterns[i]);
            if (match && match[1]) {
                return match[1];
            }
        }
        return "";
    }

    function buildYoutubeConsentPlaceholder(el) {
        const url = el.getAttribute("data-youtube-embed-url") || "";
        const videoId = youtubeVideoIdFromUrl(url);
        const thumb = videoId
            ? '<img class="dbx-youtube-consent-thumb" src="https://img.youtube.com/vi/'
                + videoId + '/hqdefault.jpg" alt="" loading="lazy">'
            : "";
        return thumb
            + '<button type="button" class="dbx-youtube-consent-play" aria-label="Video abspielen">'
            + '<i class="bi bi-play-fill"></i></button>';
    }

    function deactivateYoutubeEmbeds(root) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll("[data-youtube-embed-url].dbx-youtube-activated").forEach(function (el) {
            el.classList.remove("dbx-youtube-activated");
            el.innerHTML = buildYoutubeConsentPlaceholder(el);
        });
    }

    function activateYoutubeEmbed(el) {
        if (!el || !el.getAttribute || el.classList.contains("dbx-youtube-activated")) {
            return;
        }
        if (!getConsent().youtube) {
            return;
        }

        const url = el.getAttribute("data-youtube-embed-url");
        if (!url) {
            return;
        }

        const iframe = document.createElement("iframe");
        iframe.className = "dbx-content-video-player";
        iframe.src = url;
        iframe.title = el.getAttribute("data-youtube-title") || "YouTube Video";
        iframe.loading = el.getAttribute("data-youtube-loading") || "lazy";
        iframe.setAttribute("allowfullscreen", "");
        iframe.allowFullscreen = true;

        el.classList.add("dbx-youtube-activated");
        el.innerHTML = "";
        el.appendChild(iframe);
    }

    function activateYoutubeEmbeds(root) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll("[data-youtube-embed-url]:not(.dbx-youtube-activated)").forEach(activateYoutubeEmbed);
    }

    function closeConsentHostWindow(fromEl) {
        if (!fromEl || typeof fromEl.closest !== "function") {
            return;
        }
        const winEl = fromEl.closest(".dbx-window");
        if (!winEl || !winEl.id) {
            return;
        }
        if (window.dbx && dbx.openWin && typeof dbx.openWin.close === "function") {
            dbx.openWin.close(winEl.id);
        }
    }

    function acceptAllConsent(fromEl) {
        const consent = setConsent({ cookies: true, youtube: true, decided: true });
        hideConsentBanner();
        dispatchConsentChanged(consent);
        activateYoutubeEmbeds(document);
        closeConsentHostWindow(fromEl);
    }

    function acceptNecessaryConsent(fromEl) {
        const consent = setConsent({ cookies: true, youtube: false, decided: true });
        hideConsentBanner();
        dispatchConsentChanged(consent);
        closeConsentHostWindow(fromEl);
    }

    function rejectAllConsent(fromEl) {
        const consent = setConsent(Object.assign({}, DEFAULT_CONSENT, { ts: Date.now() }));
        deactivateYoutubeEmbeds(document);
        initConsentPanels(document);
        dispatchConsentChanged(consent);
        closeConsentHostWindow(fromEl);
        showConsentBanner();
    }

    function saveConsentFromPanel(panel, fromEl) {
        const scope = panel && panel.querySelector ? panel : document;
        const youtube = scope.querySelector("[data-dbx-consent-youtube]");
        const consent = setConsent({
            cookies: true,
            youtube: youtube ? !!youtube.checked : false,
            decided: true
        });
        hideConsentBanner();
        dispatchConsentChanged(consent);
        activateYoutubeEmbeds(document);
        closeConsentHostWindow(fromEl || panel);
    }

    function initConsentDelegation() {
        if (document.__dbxUtilitiesConsentBound) {
            return;
        }
        document.__dbxUtilitiesConsentBound = true;

        document.addEventListener("click", function (e) {
            const linkBtn = e.target && e.target.closest ? e.target.closest("[data-dbx-consent-link]") : null;
            if (linkBtn) {
                e.preventDefault();
                const link = linkBtn.getAttribute("data-dbx-consent-link");
                if (link === "privacy") {
                    openConsentSettings();
                } else if (link === "impressum") {
                    openImpressum();
                }
                return;
            }

            const actionBtn = e.target && e.target.closest ? e.target.closest("[data-dbx-consent-action]") : null;
            if (actionBtn) {
                const action = actionBtn.getAttribute("data-dbx-consent-action");
                const panel = actionBtn.closest(".dbx-consent-panel");
                if (action === "accept-all") {
                    e.preventDefault();
                    acceptAllConsent(actionBtn);
                } else if (action === "necessary" || action === "accept-necessary") {
                    e.preventDefault();
                    acceptNecessaryConsent(actionBtn);
                } else if (action === "save") {
                    e.preventDefault();
                    saveConsentFromPanel(panel, actionBtn);
                } else if (action === "reject") {
                    e.preventDefault();
                    rejectAllConsent(actionBtn);
                } else if (action === "settings") {
                    e.preventDefault();
                    openConsentSettings();
                }
                return;
            }

            const playBtn = e.target && e.target.closest ? e.target.closest(".dbx-youtube-consent-play") : null;
            if (!playBtn) {
                return;
            }
            const placeholder = playBtn.closest("[data-youtube-embed-url]");
            if (!placeholder || placeholder.classList.contains("dbx-youtube-activated")) {
                return;
            }
            e.preventDefault();
            if (!getConsent().youtube) {
                openConsentSettings();
                return;
            }
            activateYoutubeEmbed(placeholder);
        }, true);

        document.addEventListener("change", function (e) {
            const input = e.target;
            if (!input || !input.matches || !input.matches("[data-dbx-consent-youtube]")) {
                return;
            }
            const panel = input.closest(".dbx-consent-panel");
            if (panel) {
                syncConsentPanel(panel);
            }
        }, true);

        document.addEventListener("dbx:consent-changed", function (ev) {
            if (ev.detail && ev.detail.youtube) {
                activateYoutubeEmbeds(document);
            } else if (ev.detail && !ev.detail.youtube) {
                deactivateYoutubeEmbeds(document);
            }
        });
    }

    function initConsent(root) {
        initConsentDelegation();
        initConsentPanels(root || document);
        const consent = getConsent();
        if (!consent.decided && (!root || root === document)) {
            showConsentBanner();
        }
        if (consent.youtube) {
            activateYoutubeEmbeds(root || document);
        }
    }

    function init(root) {
        if (!document.body) return;

        initClearableInputs();
        initBackToTop();
        initLeaveGuard();
        initModeTheme();
        initConsent(root);
        initCollapsible(root);
        initHtmlTooltips(root);
        initPasswordCriteria(root);

        if (window.dbx && typeof dbx.log === "function") {
            dbx.log("[utilities] init");
        }
    }

    const consentApi = {
        get: getConsent,
        set: setConsent,
        acceptAll: acceptAllConsent,
        acceptNecessary: acceptNecessaryConsent,
        rejectAll: rejectAllConsent,
        savePanel: saveConsentFromPanel,
        openSettings: openConsentSettings,
        openImpressum: openImpressum,
        activateYoutube: activateYoutubeEmbeds,
        syncPanels: initConsentPanels,
        showBanner: showConsentBanner,
        hideBanner: hideConsentBanner
    };

    const api = {
        init,
        rescan: init,
        applyMode,
        applyTheme,
        applySkin,
        currentMode,
        currentTheme,
        currentSkin: parseSkinFromBody,
        skins: skinIdsForDesign(),
        designSkins: skinIdsForDesign(),
        collapsible: {
            init: initCollapsible
        },
        tooltip: {
            init: initHtmlTooltips,
            htmlList: tooltipHtmlList,
            show: showTooltip,
            hide: hideTooltip
        },
        passwordRules: {
            init: initPasswordCriteria
        },
        leaveGuard: {
            allowOnce: allowLeaveGuardNavigation,
            allowIfInternal: allowIfInternalNavigation,
            enable: function () {
                leaveGuardEnabled = true;
            },
            disable: function () {
                leaveGuardEnabled = false;
            },
            enabled: function () {
                return leaveGuardEnabled;
            }
        },
        consent: consentApi
    };

    if (window.dbx) {
        dbx.utilities = api;

        if (dbx.event && typeof dbx.event.on === "function" && !dbx.utilities.__collapseAjaxAfterBound) {
            dbx.utilities.__collapseAjaxAfterBound = true;
            dbx.event.on("ajax:after", data => {
                const ajaxRoot = data && (data.targetElement || data.root) ? (data.targetElement || data.root) : document;
                initCollapsible(ajaxRoot);
                initHtmlTooltips(ajaxRoot);
                initPasswordCriteria(ajaxRoot);
            });
        }

        if (dbx.feature && typeof dbx.feature.register === "function") {
            dbx.feature.register(LIB, {
                scope: "global",
                priority: "verylast",
                init,
                rescan: init
            });
        }
    }

    onReady(init);

})(window, document);
