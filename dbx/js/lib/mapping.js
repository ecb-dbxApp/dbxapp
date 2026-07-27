/*!
 * dbxapp mapping.js
 * Schema mapping UI: drag/drop field assignment and visual links.
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][mapping] dbx core missing");
        return;
    }

    const dbx = window.dbx;

    function readAttr(el, name, def = "") {
        if (!el || !el.getAttribute) return def;
        const value = el.getAttribute(name);
        return value == null ? def : String(value).trim();
    }

    function findRoots(el) {
        if (!el || !el.querySelectorAll) return [];
        if (el.matches && el.matches(".dbx-schema-mapping, [data-mapping-root='1']")) return [el];
        return Array.from(el.querySelectorAll(".dbx-schema-mapping, [data-mapping-root='1']"));
    }

    function esc(value) {
        value = String(value || "");
        if (window.CSS && typeof window.CSS.escape === "function") {
            return window.CSS.escape(value);
        }
        return value.replace(/([^A-Za-z0-9_-])/g, "\\$1");
    }

    function sourceItems(root) {
        return Array.from(root.querySelectorAll("[data-mapping-source]"));
    }

    function selects(root) {
        return Array.from(root.querySelectorAll("[data-mapping-select]"));
    }

    function targetRow(select) {
        return select && select.closest ? select.closest("[data-mapping-target]") : null;
    }

    function findSource(root, name) {
        if (!name) return null;
        return sourceItems(root).find(item => readAttr(item, "data-mapping-source") === name) || null;
    }

    function isCenterVisibleIn(item, container) {
        if (!item || !container) return false;

        const rect = item.getBoundingClientRect();
        const box = container.getBoundingClientRect();
        const x = rect.left + (rect.width / 2);
        const y = rect.top + (rect.height / 2);

        return x >= box.left
            && x <= box.right
            && y >= box.top
            && y <= box.bottom;
    }

    function setCount(root) {
        const all = selects(root);
        const mapped = all.filter(sel => sel.value).length;
        root.querySelectorAll("[data-mapping-count='mapped']").forEach(el => {
            el.textContent = mapped + " / " + all.length;
        });
    }

    function enforceUnique(root, changed) {
        const value = changed && changed.value ? changed.value : "";
        if (!value) return;

        selects(root).forEach(sel => {
            if (sel !== changed && sel.value === value) {
                sel.value = "";
            }
        });
    }

    function drawLines(root) {
        const svg = root.querySelector("[data-mapping-lines]");
        const workbench = root.querySelector(".dbx-mapping-workbench");
        const sourceList = root.querySelector(".dbx-mapping-source-list");
        const targetList = root.querySelector(".dbx-mapping-target-list");
        if (!svg || !workbench) return;

        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }

        const box = workbench.getBoundingClientRect();
        svg.setAttribute("viewBox", "0 0 " + Math.max(1, box.width) + " " + Math.max(1, box.height));

        selects(root).forEach(sel => {
            const source = findSource(root, sel.value);
            const row = targetRow(sel);
            if (!source || !row) return;
            if (!isCenterVisibleIn(source, sourceList) || !isCenterVisibleIn(row, targetList)) return;

            const s = source.getBoundingClientRect();
            const t = row.getBoundingClientRect();
            const x1 = s.right - box.left;
            const y1 = s.top + (s.height / 2) - box.top;
            const x2 = t.left - box.left;
            const y2 = t.top + (t.height / 2) - box.top;
            const dx = Math.max(50, Math.abs(x2 - x1) * 0.45);

            const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
            path.setAttribute("d", "M " + x1 + " " + y1 + " C " + (x1 + dx) + " " + y1 + ", " + (x2 - dx) + " " + y2 + ", " + x2 + " " + y2);
            path.setAttribute("class", "dbx-mapping-line");
            svg.appendChild(path);
        });
    }

    function sync(root) {
        const used = new Set();

        selects(root).forEach(sel => {
            const row = targetRow(sel);
            if (!row) return;

            const hasValue = !!sel.value;
            row.classList.toggle("is-mapped", hasValue);
            row.classList.toggle("is-empty", !hasValue);
            row.setAttribute("data-selected-source", sel.value || "");
            if (hasValue) used.add(sel.value);
        });

        sourceItems(root).forEach(item => {
            const name = readAttr(item, "data-mapping-source");
            item.classList.toggle("is-used", used.has(name));
        });

        setCount(root);
        window.requestAnimationFrame(() => drawLines(root));
    }

    function setTarget(root, target, source) {
        const sel = selects(root).find(item => readAttr(item, "data-target") === target);
        if (!sel) return;
        sel.value = source || "";
        enforceUnique(root, sel);
        sync(root);
    }

    function clear(root) {
        selects(root).forEach(sel => {
            sel.value = "";
        });
        sync(root);
    }

    function auto(root) {
        selects(root).forEach(sel => {
            sel.value = readAttr(sel, "data-auto-source");
        });
        sync(root);
    }

    function bind(root) {
        if (root.__dbxMappingBound) return;
        root.__dbxMappingBound = true;

        root.addEventListener("dragstart", e => {
            const item = e.target.closest("[data-mapping-source]");
            if (!item || !root.contains(item)) return;

            const name = readAttr(item, "data-mapping-source");
            e.dataTransfer.effectAllowed = "copy";
            e.dataTransfer.setData("text/plain", name);
            root.classList.add("is-dragging");
            item.classList.add("is-drag-source");
        });

        root.addEventListener("dragend", e => {
            root.classList.remove("is-dragging");
            const item = e.target.closest("[data-mapping-source]");
            if (item) item.classList.remove("is-drag-source");
        });

        root.addEventListener("dragover", e => {
            const drop = e.target.closest("[data-mapping-drop]");
            if (!drop || !root.contains(drop)) return;
            e.preventDefault();
            drop.classList.add("is-over");
        });

        root.addEventListener("dragleave", e => {
            const drop = e.target.closest("[data-mapping-drop]");
            if (drop) drop.classList.remove("is-over");
        });

        root.addEventListener("drop", e => {
            const drop = e.target.closest("[data-mapping-drop]");
            if (!drop || !root.contains(drop)) return;

            e.preventDefault();
            drop.classList.remove("is-over");
            setTarget(root, readAttr(drop, "data-mapping-drop"), e.dataTransfer.getData("text/plain"));
        });

        root.addEventListener("change", e => {
            const sel = e.target.closest("[data-mapping-select]");
            if (!sel || !root.contains(sel)) return;
            enforceUnique(root, sel);
            sync(root);
        });

        root.addEventListener("click", e => {
            const action = e.target.closest("[data-mapping-action]");
            if (action && root.contains(action)) {
                e.preventDefault();
                if (readAttr(action, "data-mapping-action") === "clear") clear(root);
                if (readAttr(action, "data-mapping-action") === "auto") auto(root);
                return;
            }

            const clearBtn = e.target.closest("[data-mapping-clear-row]");
            if (clearBtn && root.contains(clearBtn)) {
                e.preventDefault();
                const row = clearBtn.closest("[data-mapping-target]");
                const sel = row ? row.querySelector("[data-mapping-select]") : null;
                if (sel) sel.value = "";
                sync(root);
            }
        });

        window.addEventListener("resize", () => sync(root), { passive: true });
        root.addEventListener("scroll", () => sync(root), true);
    }

    dbx.mapping = dbx.mapping || {};
    dbx.mapping.init = function (el) {
        findRoots(el).forEach(root => {
            bind(root);
            sync(root);
        });
    };

    dbx.feature.register("mapping", {
        scope: "element",
        priority: "mid",
        css: [
            ["css", "design", "c-schema-mapping.css"]
        ],
        init(el) {
            dbx.mapping.init(el);
        }
    });

})(window, document);
