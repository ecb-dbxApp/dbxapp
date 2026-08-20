/*!
 * dbxapp cms-marker.js
 * Canonical CMS marker creation and normalization.
 */
(function (window, document) {
    "use strict";

    if (!window.dbx) {
        console.error("[dbx][cms-marker] dbx core missing");
        return;
    }

    const escapeHtml = value => String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    const qsa = (root, selector) => root ? Array.from(root.querySelectorAll(selector)) : [];

    function name(marker) {
        const value = String(marker || "").replace(/^dbx:/i, "").trim().toLowerCase() || "marker";
        return ["hero_text", "hero-text", "herotext"].includes(value) ? "hero" : value;
    }

    function className(marker) {
        return name(marker).replace(/[^a-z0-9_-]+/gi, "-") || "marker";
    }

    function label(marker, customLabel) {
        const labels = {
            "dbx:hero": "Hero",
            "dbx:split": "col-2 Trenner",
            "dbx:col2": "col-2 Trenner",
            "dbx:col3a": "col-3a Trenner",
            "dbx:col3b": "col-3b Trenner",
            "dbx:header": "Header",
            "dbx:teaser": "Header",
            "dbx:footer": "Footer",
            "dbx:pagebreak": "Druck Seitenumbruch"
        };
        const canonicalMarker = "dbx:" + name(marker);
        if (canonicalMarker === "dbx:hero") return "Hero";
        return customLabel || labels[canonicalMarker] || canonicalMarker || "dbx:marker";
    }

    function html(marker, customLabel) {
        if (marker === "dbx:split") marker = "dbx:col2";
        const markerName = name(marker);
        const markerClass = className(marker);
        return `<hr class="dbx-cms-marker dbx-cms-marker-${escapeHtml(markerClass)}" contenteditable="false" draggable="false" tabindex="0" data-dbx-marker="dbx:${escapeHtml(markerName)}" data-label="${escapeHtml(label(marker, customLabel))}">`;
    }

    function element(marker, customLabel, ownerDocument) {
        const template = (ownerDocument || document).createElement("template");
        template.innerHTML = html(marker, customLabel);
        return template.content.firstElementChild;
    }

    function nameFromElement(node) {
        if (!node || node.nodeType !== 1 || !node.getAttribute) return "";
        const raw = node.getAttribute("data-dbx-marker") || node.getAttribute("data-dbx-marker-comment") || "";
        return raw ? name(raw) : "";
    }

    function isIgnorableSibling(node) {
        if (!node) return false;
        if (node.nodeType === 3) return String(node.nodeValue || "").replace(/\uFEFF/g, "").trim() === "";
        if (node.nodeType !== 1) return false;
        const tag = node.tagName || "";
        if (tag === "BR") return true;
        if (!/^(P|DIV|SPAN)$/i.test(tag)) return false;
        if (node.querySelector && node.querySelector(".dbx-cms-marker,[data-dbx-marker],img,video,iframe,table,hr")) return false;
        return String(node.textContent || "").replace(/\uFEFF/g, "").trim() === "";
    }

    function nearbySibling(node, direction) {
        let current = direction < 0 ? node?.previousSibling : node?.nextSibling;
        while (current && isIgnorableSibling(current)) {
            current = direction < 0 ? current.previousSibling : current.nextSibling;
        }
        return current && current.nodeType === 1 ? current : null;
    }

    function hasSameNeighbor(node, markerName) {
        return nameFromElement(nearbySibling(node, -1)) === markerName
            || nameFromElement(nearbySibling(node, 1)) === markerName;
    }

    function dedupeAdjacent(container) {
        if (!container) return;
        qsa(container, ".dbx-cms-marker,[data-dbx-marker]").forEach(marker => {
            if (!marker.parentNode) return;
            const markerName = nameFromElement(marker);
            if (!markerName) return;
            if (nameFromElement(nearbySibling(marker, -1)) === markerName) marker.remove();
        });
    }

    function normalizeComments(container) {
        if (!container) return;
        const ownerDocument = container.ownerDocument || document;
        const comments = [];
        (function collect(node) {
            Array.from(node && node.childNodes || []).forEach(child => {
                if (child.nodeType === 8) comments.push(child);
                else collect(child);
            });
        })(container);
        comments.forEach(node => {
            const match = String(node.nodeValue || "").trim().match(/^dbx:([a-z0-9_-]+)/i);
            if (match) {
                const markerName = name("dbx:" + match[1].toLowerCase());
                if (node.parentNode && hasSameNeighbor(node, markerName)) {
                    node.parentNode.removeChild(node);
                    return;
                }
                const marker = element("dbx:" + markerName, null, ownerDocument);
                if (marker && node.parentNode) node.parentNode.replaceChild(marker, node);
                return;
            }
            if (node.parentNode) node.parentNode.removeChild(node);
        });
        dedupeAdjacent(container);
    }

    function dedupeSingleton(container) {
        if (!container) return;
        const seen = new Set();
        qsa(container, ".dbx-cms-marker,[data-dbx-marker]").forEach(marker => {
            const markerName = nameFromElement(marker);
            if (markerName !== "hero") return;
            if (seen.has(markerName)) {
                marker.remove();
                return;
            }
            seen.add(markerName);
        });
    }

    function plainTextName(text) {
        const match = String(text || "").trim().match(/^dbx:(split|col2|col3a|col3b|header|teaser|footer|pagebreak)$/i);
        if (!match) return "";
        return match[1].toLowerCase() === "split" ? "col2" : match[1].toLowerCase();
    }

    function normalizePlainText(container) {
        if (!container) return;
        normalizeComments(container);
        const ownerDocument = container.ownerDocument || document;
        qsa(container, "p,div").forEach(node => {
            if (node.querySelector(".dbx-cms-marker,[data-dbx-marker],img,video,iframe,table,hr,ul,ol")) return;
            const markerName = plainTextName(node.textContent);
            if (!markerName) return;
            const marker = element("dbx:" + markerName, null, ownerDocument);
            if (marker) node.replaceWith(marker);
        });
        Array.from(container.childNodes || []).forEach(node => {
            if (node.nodeType !== 3) return;
            const markerName = plainTextName(node.nodeValue);
            if (!markerName) return;
            const marker = element("dbx:" + markerName, null, ownerDocument);
            if (marker) node.replaceWith(marker);
        });
    }

    function serialize(sourceHtml, cleanRuntimeNodes) {
        const box = document.createElement("div");
        box.innerHTML = sourceHtml || "";
        normalizeComments(box);
        if (typeof cleanRuntimeNodes === "function") cleanRuntimeNodes(box);
        normalizePlainText(box);
        qsa(box, ".dbx-cms-marker[data-dbx-marker-comment]").forEach(marker => {
            const markerText = marker.getAttribute("data-dbx-marker-comment") || "";
            const markerName = name(markerText);
            const horizontalRule = document.createElement("hr");
            horizontalRule.className = "dbx-cms-marker dbx-cms-marker-" + className(markerName);
            horizontalRule.setAttribute("data-dbx-marker", "dbx:" + markerName);
            horizontalRule.setAttribute("data-label", marker.textContent || markerText);
            horizontalRule.setAttribute("contenteditable", "false");
            horizontalRule.setAttribute("draggable", "false");
            horizontalRule.setAttribute("tabindex", "0");
            marker.replaceWith(horizontalRule);
        });
        qsa(box, ".dbx-cms-marker,[data-dbx-marker]").forEach(originalMarker => {
            const markerName = name(originalMarker.getAttribute("data-dbx-marker") || "");
            let marker = originalMarker;
            if (marker.tagName !== "HR") {
                const horizontalRule = document.createElement("hr");
                Array.from(marker.attributes || []).forEach(attribute => {
                    if (attribute.name !== "data-dbx-marker-comment") {
                        horizontalRule.setAttribute(attribute.name, attribute.value);
                    }
                });
                marker.replaceWith(horizontalRule);
                marker = horizontalRule;
            }
            marker.classList.remove("is-selected", "is-dragging", "is-drop-before", "is-drop-after");
            Array.from(marker.classList).forEach(cssClass => {
                if (cssClass.indexOf("dbx-cms-marker-") === 0) marker.classList.remove(cssClass);
            });
            marker.classList.add("dbx-cms-marker", "dbx-cms-marker-" + className(markerName));
            marker.removeAttribute("data-cms-drag-token");
            marker.setAttribute("data-dbx-marker", "dbx:" + markerName);
            if (markerName === "hero" || !marker.getAttribute("data-label")) {
                marker.setAttribute("data-label", label("dbx:" + markerName));
            }
            marker.setAttribute("contenteditable", "false");
            marker.setAttribute("draggable", "false");
            marker.setAttribute("tabindex", "0");
        });
        dedupeSingleton(box);
        dedupeAdjacent(box);
        return box.innerHTML;
    }

    window.dbx.cmsMarker = Object.freeze({
        name,
        className,
        label,
        html,
        element,
        nameFromElement,
        nearbySibling,
        dedupeAdjacent,
        normalizeComments,
        dedupeSingleton,
        plainTextName,
        normalizePlainText,
        serialize
    });
})(window, document);
