/*!
 * dbxapp cms-context.js
 * Editor-Kontextmenue, Zwischenablage und Blockaktionen.
 */
(function (window, document) {
    "use strict";

    const runtime = window.dbx && window.dbx.cmsRuntime;
    if (!runtime || typeof runtime.register !== "function") {
        console.error("[dbx][cms-context] CMS runtime missing");
        return;
    }

    runtime.register("context", function (context) {
        const {
            dbx,
            qs,
            qsa,
            state,
            cmsText,
            cmsConfig,
            closestElement,
            editorSurface,
            rangeInsideSurface,
            rangeIntersectsNode,
            nodeElement,
            normalizeBootstrapComponents,
            scheduleEditorHeight,
            syncEditorDom,
            markDirty,
            selectEditorMarker,
            removeEditorMarker,
            inlineModTarget,
            selectEditorModPlaceholder,
            removeEditorModPlaceholder,
            inlineVideoEventTarget,
            openInlineVideoOptions,
            openModPlaceholderOptions,
            cleanEditorRuntimeNodes,
            editorRangeFromPoint,
            setEditorCaretFromPoint,
            insertEditorHtml,
            escapeHtml,
            getEditorInstance,
            openCmsImageEditor,
            removeEditorImage,
            refreshEditorCaretHint,
            hideEditorCaretHint,
            restoreEditorSelection,
            setEditorCaretAfterNode,
            execEditorCommand
        } = context;
    function editorSelectionRange(root) {
        const surface = editorSurface(root);
        if (!surface) return null;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel || !sel.rangeCount) return null;
        const range = sel.getRangeAt(0);
        return rangeInsideSurface(surface, range) ? range : null;
    }

    function editorSelectionText(root) {
        const range = editorSelectionRange(root);
        return range && !range.collapsed ? String(range.cloneContents().textContent || "") : "";
    }

    function selectEditorContents(root) {
        const surface = editorSurface(root);
        if (!surface) return false;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel) return false;
        const range = doc.createRange();
        range.selectNodeContents(surface);
        if (surface.focus) surface.focus({ preventScroll: true });
        sel.removeAllRanges();
        sel.addRange(range);
        state(root).editorRange = range.cloneRange();
        return true;
    }

    function editorContextBlock(root, target) {
        const surface = editorSurface(root);
        if (!surface) return null;
        const el = closestBootstrapComponent(root, target) || closestElement(
            target,
            ".dbx-cms-marker,.dbx-cms-inline-media,.dbx-cms-inline-media-missing-wrap,figure,table,img,video,hr,p,h1,h2,h3,h4,h5,h6,ul,ol,dl,pre,blockquote,section,article"
        );
        return el && el !== surface && surface.contains(el) ? el : null;
    }

    /**
     * Kontextmenü und Drag-and-drop verwenden bewusst dieselbe Blockauflösung.
     * Dadurch sind Textblöcke, Medien, Marker und Bootstrap-Komponenten mit
     * allen angebotenen Bearbeitungswegen konsistent erreichbar.
     */
    function movableEditorContextBlock(root, target) {
        return removableEditorContextTarget(root, target);
    }

    function movableEditorButtonBlock(root, target) {
        const surface = editorSurface(root);
        const button = closestElement(target, "a.btn[data-dbx-cms-movable-button]");
        if (!surface || !button || !surface.contains(button)) return null;
        const paragraph = closestElement(button, "p");
        if (paragraph && surface.contains(paragraph)
            && paragraph.children.length === 1
            && paragraph.firstElementChild === button) {
            return paragraph;
        }
        return button;
    }

    function clearEditorButtonDrag(root) {
        const s = state(root);
        const drag = s.editorButtonDrag;
        if (drag && drag.block) drag.block.classList.remove("is-dbx-cms-dragging");
        const surface = editorSurface(root);
        qsa(surface, ".is-dbx-cms-drop-target").forEach(el => el.classList.remove("is-dbx-cms-drop-target"));
        s.editorButtonDrag = null;
    }

    function closestBootstrapComponent(root, target) {
        const surface = editorSurface(root);
        if (!surface || !target) return null;
        const row = closestElement(target, ".row");
        if (row && surface.contains(row) && (bootstrapRowColumns(row).length || qs(row, ".card"))) {
            return row;
        }
        const tabsPart = closestElement(target, ".nav-tabs,.tab-content");
        if (tabsPart && surface.contains(tabsPart)) {
            const tabsWrap = tabsPart.parentElement;
            const tabsChildren = tabsWrap ? Array.from(tabsWrap.children || []) : [];
            if (tabsWrap && surface.contains(tabsWrap)
                && tabsChildren.some(child => child.classList?.contains("nav-tabs"))
                && tabsChildren.some(child => child.classList?.contains("tab-content"))) {
                return tabsWrap;
            }
            return tabsPart;
        }
        const el = closestElement(target, ".alert,.card,.list-group,.accordion,.table-responsive");
        if (!el || !surface.contains(el)) return null;
        return el;
    }

    /**
     * CMS-Spaltenboxen verwenden Bootstrap-rows mit direkten col-Kindern.
     * Die Layoutaktionen ändern ausschließlich die Spaltenklassen; Inhalte,
     * Medien und Module bleiben erhalten. Beim Auflösen werden die Inhalte
     * in ihrer bisherigen Reihenfolge aus den Spalten herausgehoben.
     */
    function bootstrapRowColumns(row) {
        if (!row || !row.classList || !row.classList.contains("row")) return [];
        return Array.from(row.children || []).filter(column => {
            return column.classList && Array.from(column.classList).some(name => /^col(?:$|-)/.test(name));
        });
    }

    function bootstrapColumnRow(root, target) {
        const surface = editorSurface(root);
        const row = closestElement(target, ".row");
        return row && surface && surface.contains(row) && bootstrapRowColumns(row).length ? row : null;
    }

    function clearBootstrapColumnClasses(column) {
        if (!column || !column.classList) return;
        Array.from(column.classList).forEach(name => {
            if (/^col(?:$|-)/.test(name)) column.classList.remove(name);
        });
    }

    function finishBootstrapColumnAction(root, focusNode) {
        normalizeBootstrapComponents(editorSurface(root));
        syncEditorDom(root);
        markDirty(root);
        scheduleEditorHeight(root);
        if (focusNode && focusNode.parentNode) selectEditorNode(root, focusNode);
        return true;
    }

    function setBootstrapColumnLayout(root, row, mode) {
        const columns = bootstrapRowColumns(row);
        if (!columns.length) return false;
        columns.forEach(column => {
            clearBootstrapColumnClasses(column);
            column.classList.add("col-12");
            if (mode === "responsive") column.classList.add("col-md");
        });
        return finishBootstrapColumnAction(root, row);
    }

    function addBootstrapColumn(root, row) {
        const columns = bootstrapRowColumns(row);
        if (!row || !columns.length) return false;
        const doc = row.ownerDocument || document;
        const column = doc.createElement("div");
        const responsive = columns.some(existing => {
            return Array.from(existing.classList || []).some(name => /^col-(?:sm|md|lg|xl|xxl)(?:-|$)/.test(name));
        });
        column.className = responsive ? "col-12 col-md" : "col-12";
        const paragraph = doc.createElement("p");
        paragraph.textContent = cmsText(root, "editor_columns_new", "Neue Spalte");
        column.appendChild(paragraph);
        row.appendChild(column);
        return finishBootstrapColumnAction(root, column);
    }

    function dissolveBootstrapColumns(root, row) {
        const columns = bootstrapRowColumns(row);
        if (!row || !row.parentNode || !columns.length) return false;
        const doc = row.ownerDocument || document;
        const fragment = doc.createDocumentFragment();
        let firstMoved = null;
        Array.from(row.childNodes || []).forEach(node => {
            if (node.nodeType === 1 && columns.includes(node)) {
                while (node.firstChild) {
                    const child = node.firstChild;
                    if (!firstMoved && child.nodeType === 1) firstMoved = child;
                    fragment.appendChild(child);
                }
                return;
            }
            if (!firstMoved && node.nodeType === 1) firstMoved = node;
            fragment.appendChild(node);
        });
        row.parentNode.insertBefore(fragment, row);
        row.remove();
        return finishBootstrapColumnAction(root, firstMoved);
    }

    function contextMissingMediaTarget(root, target) {
        const surface = editorSurface(root);
        const wrap = closestElement(target, ".dbx-cms-inline-media-missing-wrap");
        return wrap && surface && surface.contains(wrap) ? wrap : null;
    }

    function selectEditorMissingMedia(root, wrap) {
        const surface = editorSurface(root);
        qsa(surface, ".dbx-cms-inline-media-missing-wrap.is-selected").forEach(el => {
            el.classList.remove("is-selected");
            el.removeAttribute("aria-selected");
        });
        if (wrap && surface && surface.contains(wrap)) {
            selectEditorMarker(root, null);
            wrap.classList.add("is-selected");
            wrap.setAttribute("aria-selected", "true");
            state(root).selectedMissingMedia = wrap;
            hideEditorCaretHint(root);
            const doc = surface.ownerDocument || document;
            const sel = doc.getSelection ? doc.getSelection() : null;
            if (sel) sel.removeAllRanges();
            if (wrap.focus) wrap.focus({ preventScroll: true });
        } else {
            state(root).selectedMissingMedia = null;
            qsa(surface, ".dbx-cms-inline-media-missing-wrap[aria-selected]").forEach(el => el.removeAttribute("aria-selected"));
        }
    }

    function removeEditorMissingMedia(root, wrap) {
        wrap = wrap || state(root).selectedMissingMedia;
        if (!wrap || !wrap.parentNode) return false;
        selectEditorMissingMedia(root, null);
        wrap.remove();
        syncEditorDom(root);
        markDirty(root);
        return true;
    }

    function handleEditorMissingMediaClick(root, wrap, e) {
        if (!wrap) return false;
        selectEditorMissingMedia(root, wrap);
        if (e) {
            if (typeof e.preventDefault === "function") e.preventDefault();
            if (typeof e.stopPropagation === "function") e.stopPropagation();
            if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();
        }
        return true;
    }

    function removableEditorContextTarget(root, target) {
        const surface = editorSurface(root);
        if (!surface) return null;
        // Verschachtelte Buttons sind eigenständige CMS-Elemente. Sie müssen
        // vor ihrer umgebenden Bootstrap-Komponente aufgelöst werden, damit
        // Kopieren, Ausschneiden und Drag-and-drop nicht z. B. die komplette
        // Alert-Box statt nur des Buttons erfassen.
        const button = movableEditorButtonBlock(root, target);
        if (button && surface.contains(button)) return button;
        const component = closestBootstrapComponent(root, target);
        if (component && surface.contains(component)) return component;
        const mod = inlineModTarget(root, target);
        if (mod && surface.contains(mod)) return mod;
        const missing = closestElement(target, ".dbx-cms-inline-media-missing-wrap");
        if (missing && surface.contains(missing)) return missing;
        const inlineMedia = closestElement(target, ".dbx-cms-inline-media");
        if (inlineMedia && surface.contains(inlineMedia)) return inlineMedia;
        const el = editorContextBlock(root, target);
        if (!el || !surface.contains(el)) return null;
        if ((el.tagName === "IMG" || el.tagName === "VIDEO") && el.parentElement && el.parentElement.tagName === "FIGURE") {
            return el.parentElement;
        }
        return el;
    }

    function editorElementSibling(el, dir) {
        let node = dir < 0 ? el?.previousSibling : el?.nextSibling;
        while (node && node.nodeType !== 1) node = dir < 0 ? node.previousSibling : node.nextSibling;
        return node || null;
    }

    function moveEditorContextBlock(root, block, dir) {
        const surface = editorSurface(root);
        if (!block || !surface || !surface.contains(block) || block === surface) return false;
        const sibling = editorElementSibling(block, dir);
        if (!sibling || !block.parentElement) return false;
        if (dir < 0) block.parentElement.insertBefore(block, sibling);
        else block.parentElement.insertBefore(sibling, block);
        if (block.matches && block.matches(".dbx-cms-marker")) selectEditorMarker(root, block);
        syncEditorDom(root);
        return true;
    }

    function closeEditorContextMenu(root) {
        const s = state(root);
        if (typeof s.editorContextCleanup === "function") s.editorContextCleanup();
        if (s.editorContextMenu && s.editorContextMenu.parentNode) s.editorContextMenu.parentNode.removeChild(s.editorContextMenu);
        s.editorContextCleanup = null;
        s.editorContextMenu = null;
    }

    function clipboardWriteText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(() => false);
        }
        return Promise.resolve(false);
    }

    function clipboardReadText() {
        if (navigator.clipboard && navigator.clipboard.readText) {
            return navigator.clipboard.readText().catch(() => "");
        }
        return Promise.resolve("");
    }

    function editorContextBlockHtml(root, target) {
        const block = movableEditorContextBlock(root, target);
        const surface = editorSurface(root);
        if (!block || !surface || !surface.contains(block)) return "";
        const container = (surface.ownerDocument || document).createElement("div");
        container.appendChild(block.cloneNode(true));
        cleanEditorRuntimeNodes(container);
        return container.innerHTML;
    }

    function copyEditorContext(root, target) {
        const html = editorContextBlockHtml(root, target);
        if (html) {
            state(root).editorClipboardHtml = html;
            return clipboardWriteText(html).then(() => true);
        }
        restoreEditorSelection(root);
        const text = editorSelectionText(root);
        const copied = document.execCommand && document.execCommand("copy");
        if (!copied && text) return clipboardWriteText(text);
        return Promise.resolve(copied);
    }

    function cutEditorContext(root, target) {
        const block = movableEditorContextBlock(root, target);
        const html = editorContextBlockHtml(root, block);
        if (block && html) {
            state(root).editorClipboardHtml = html;
            return clipboardWriteText(html).finally(() => {
                if (block.matches && block.matches(".dbx-cms-marker")) {
                    removeEditorMarker(root, block);
                    return;
                }
                block.remove();
                syncEditorDom(root);
                markDirty(root);
            });
        }
        restoreEditorSelection(root);
        const text = editorSelectionText(root);
        const cut = document.execCommand && document.execCommand("cut");
        if (cut) {
            syncEditorDom(root);
            return Promise.resolve(true);
        }
        if (text) {
            return clipboardWriteText(text).finally(() => {
                execEditorCommand(root, "delete");
                syncEditorDom(root);
            });
        }
        return Promise.resolve(false);
    }

    function editorRangeIsInsertable(root, range) {
        const surface = editorSurface(root);
        if (!surface || !range || !rangeInsideSurface(surface, range)) return false;
        const start = nodeElement(range.startContainer);
        const locked = start ? closestElement(start, "[contenteditable='false']") : null;
        return !(locked && surface.contains(locked));
    }

    function restoreEditorContextPasteRange(root) {
        const s = state(root);
        const range = s.editorContextPasteRange;
        if (!editorRangeIsInsertable(root, range)) return false;
        s.editorRange = range.cloneRange();
        return restoreEditorSelection(root);
    }

    function editorClipboardInlineButtonHtml(root, html) {
        const surface = editorSurface(root);
        const doc = surface ? (surface.ownerDocument || document) : document;
        const template = doc.createElement("template");
        template.innerHTML = String(html || "");
        const children = Array.from(template.content.children || []);
        if (children.length === 1 && children[0].matches("a.btn")) {
            return children[0].outerHTML;
        }
        // Ein alleinstehender Bootstrap-Button liegt im Content üblicherweise
        // in einem Absatz. An einer Text-Caret-Position wird nur der Link
        // eingefügt, damit er z. B. direkt in einer Alert-Box stehen kann.
        if (children.length === 1 && children[0].tagName === "P") {
            const paragraph = children[0];
            if (paragraph.children.length === 1
                && paragraph.firstElementChild.matches("a.btn")
                && String(paragraph.textContent || "").trim() === String(paragraph.firstElementChild.textContent || "").trim()) {
                return paragraph.innerHTML;
            }
        }
        return "";
    }

    function alignEditorContextPasteRange(root, html) {
        if (!editorClipboardInlineButtonHtml(root, html)) return;
        const surface = editorSurface(root);
        const s = state(root);
        const range = s.editorContextPasteRange;
        if (!surface || !editorRangeIsInsertable(root, range)) return;
        const start = nodeElement(range.startContainer);
        const closedInline = start ? closestElement(start, "code,kbd,samp,a,button") : null;
        if (!closedInline || !surface.contains(closedInline) || !closedInline.parentNode) return;
        const doc = surface.ownerDocument || document;
        const aligned = doc.createRange();
        aligned.setStartAfter(closedInline);
        aligned.collapse(true);
        s.editorContextPasteRange = aligned;
    }

    function editorClipboardHtmlAtCaret(root, html) {
        return editorClipboardInlineButtonHtml(root, html) || String(html || "");
    }

    function insertEditorContextBlock(root, html, target) {
        const surface = editorSurface(root);
        const destination = movableEditorContextBlock(root, target);
        if (!surface || !html) return false;
        alignEditorContextPasteRange(root, html);
        if (restoreEditorContextPasteRange(root)) {
            state(root).editorContextPasteRange = null;
            insertEditorHtml(root, editorClipboardHtmlAtCaret(root, html));
            markDirty(root);
            refreshEditorCaretHint(root);
            return true;
        }
        state(root).editorContextPasteRange = null;
        if (!destination || !surface.contains(destination) || !destination.parentNode) {
            insertEditorHtml(root, html);
            return true;
        }
        const doc = surface.ownerDocument || document;
        const template = doc.createElement("template");
        template.innerHTML = html;
        const first = template.content.firstElementChild;
        destination.parentNode.insertBefore(template.content, destination.nextSibling);
        normalizeBootstrapComponents(surface);
        syncEditorDom(root);
        markDirty(root);
        if (first && first.parentNode) selectEditorNode(root, first);
        return true;
    }

    function rememberEditorContextPasteRange(root, x, y) {
        const surface = editorSurface(root);
        const range = editorRangeFromPoint(surface, x, y);
        const s = state(root);
        s.editorContextPasteRange = editorRangeIsInsertable(root, range) ? range.cloneRange() : null;
        if (s.editorContextPasteRange) refreshEditorCaretHint(root, s.editorContextPasteRange);
        return s.editorContextPasteRange;
    }

    function insertDraggedEditorBlockAtCaret(root, block) {
        const range = state(root).editorContextPasteRange;
        if (!block || !block.parentNode || !editorRangeIsInsertable(root, range)) return null;
        if (rangeIntersectsNode(range, block)) return null;

        let moved = block;
        if (block.tagName === "P"
            && block.children.length === 1
            && block.firstElementChild.matches("a.btn")
            && String(block.textContent || "").trim() === String(block.firstElementChild.textContent || "").trim()) {
            moved = block.firstElementChild;
        }
        const oldWrapper = moved === block ? null : block;
        if (moved.matches && moved.matches("a.btn")) {
            const start = nodeElement(range.startContainer);
            const closedInline = start ? closestElement(start, "code,kbd,samp,a,button") : null;
            if (closedInline && editorSurface(root)?.contains(closedInline) && closedInline.parentNode) {
                range.setStartAfter(closedInline);
            }
        }
        range.collapse(true);
        range.insertNode(moved);
        state(root).editorContextPasteRange = null;
        if (oldWrapper && !String(oldWrapper.textContent || "").trim() && !oldWrapper.children.length) oldWrapper.remove();
        setEditorCaretAfterNode(root, moved);
        return moved;
    }

    function pasteEditorContext(root, target) {
        const internalHtml = state(root).editorClipboardHtml || "";
        if (internalHtml) return Promise.resolve(insertEditorContextBlock(root, internalHtml, target));
        if (restoreEditorContextPasteRange(root)) {
            state(root).editorContextPasteRange = null;
        } else {
            restoreEditorSelection(root);
        }
        return clipboardReadText().then(text => {
            if (text) {
                if (document.execCommand && document.execCommand("insertText", false, text)) {
                    syncEditorDom(root);
                    return true;
                }
                insertEditorHtml(root, escapeHtml(text));
                return true;
            }
            if (document.execCommand && document.execCommand("paste")) {
                syncEditorDom(root);
                return true;
            }
            return false;
        });
    }

    function deleteEditorContext(root, target) {
        const marker = closestElement(target, ".dbx-cms-marker") || state(root).selectedMarker;
        if (marker && editorSurface(root)?.contains(marker)) return removeEditorMarker(root, marker);
        const missing = contextMissingMediaTarget(root, target) || state(root).selectedMissingMedia;
        if (missing && editorSurface(root)?.contains(missing)) return removeEditorMissingMedia(root, missing);
        const mod = inlineModTarget(root, target) || state(root).selectedModPlaceholder;
        if (mod && editorSurface(root)?.contains(mod)) return removeEditorModPlaceholder(root, mod);
        const removable = removableEditorContextTarget(root, target);
        if (removable) {
            removable.remove();
            syncEditorDom(root);
            return true;
        }
        restoreEditorSelection(root);
        execEditorCommand(root, "delete");
        syncEditorDom(root);
        return true;
    }

    function selectEditorNode(root, node) {
        const surface = editorSurface(root);
        if (!surface || !node || !surface.contains(node)) return false;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel) return false;
        const range = doc.createRange();
        range.selectNode(node);
        if (surface.focus) surface.focus({ preventScroll: true });
        sel.removeAllRanges();
        sel.addRange(range);
        state(root).editorRange = range.cloneRange();
        return true;
    }

    function syncEditorAfterContextAction(root) {
        const instance = getEditorInstance(root);
        if (instance && instance.synchronizeValues) instance.synchronizeValues();
        syncEditorDom(root);
    }

    function contextImageTarget(root, target) {
        if (inlineModTarget(root, target)) return null;
        const surface = editorSurface(root);
        const img = closestElement(target, "img");
        if (img && surface && surface.contains(img)) return img;

        // Alte Inhalte können eine absolut positionierte Textebene über einem
        // Bild enthalten. Die Textebene darf die Bildaktionen nicht blockieren.
        const overlay = closestElement(target, ".position-absolute,[style*='position:absolute'],[style*='position: absolute']");
        const host = overlay?.parentElement || null;
        if (!host || !surface || !surface.contains(host)) return null;
        const isRelative = host.classList?.contains("position-relative")
            || /position\s*:\s*relative/i.test(host.getAttribute("style") || "");
        if (!isRelative) return null;
        return Array.from(host.children || []).find(child => child.tagName === "IMG") || null;
    }


    function contextTableCell(root, target) {
        const surface = editorSurface(root);
        const cell = closestElement(target, "td,th");
        return cell && surface && surface.contains(cell) ? cell : null;
    }

    function contextTableTarget(root, target) {
        const surface = editorSurface(root);
        const table = closestElement(target, "table");
        return table && surface && surface.contains(table) ? table : null;
    }

    function openEditorImageProperties(root, img) {
        const instance = getEditorInstance(root);
        if (!instance || !img) return false;
        selectEditorNode(root, img);
        if (instance.e && instance.e.fire) {
            instance.e.fire("openImageProperties", img);
            return true;
        }
        return false;
    }




    function contextMenuButton(label, icon, action, disabled) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "dbx-cms-context-menu-item";
        btn.disabled = !!disabled;
        if (icon) {
            const iconEl = document.createElement("i");
            iconEl.className = "bi " + icon;
            iconEl.setAttribute("aria-hidden", "true");
            btn.appendChild(iconEl);
        }
        const labelEl = document.createElement("span");
        labelEl.textContent = label;
        btn.appendChild(labelEl);
        btn.addEventListener("mousedown", e => e.preventDefault());
        btn.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();
            if (btn.disabled) return;
            const root = btn.__dbxCmsRoot;
            closeEditorContextMenu(root);
            Promise.resolve(action()).catch(err => dbx.warn("[cms] context action failed", err));
        });
        return btn;
    }

    function showEditorContextMenu(root, e) {
        const surface = editorSurface(root);
        if (!surface) return;
        closeEditorContextMenu(root);

        const target = e.target;
        rememberEditorContextPasteRange(root, e.clientX, e.clientY);
        const marker = closestElement(target, ".dbx-cms-marker");
        const missingMedia = contextMissingMediaTarget(root, target);
        const hasSelection = !!editorSelectionText(root);
        const img = contextImageTarget(root, target);
        const videoMedia = inlineVideoEventTarget(root, e);
        const modPlaceholder = inlineModTarget(root, target);
        const component = closestBootstrapComponent(root, target);
        const columnRow = bootstrapColumnRow(root, target);
        const cell = contextTableCell(root, target);
        const table = contextTableTarget(root, target);

        if (marker && surface.contains(marker)) {
            selectEditorMarker(root, marker);
        } else if (missingMedia) {
            selectEditorMissingMedia(root, missingMedia);
        } else if (cell) {
            selectEditorNode(root, cell);
        } else if (modPlaceholder) {
            selectEditorModPlaceholder(root, modPlaceholder);
        } else if (component && surface.contains(component)) {
            selectEditorNode(root, component);
        } else if (videoMedia) {
            selectEditorNode(root, videoMedia);
        } else if (img) {
            selectEditorNode(root, img);
        } else if (!hasSelection) {
            setEditorCaretFromPoint(root, e.clientX, e.clientY);
            selectEditorMarker(root, null);
        }
        if (state(root).editorContextPasteRange) {
            refreshEditorCaretHint(root, state(root).editorContextPasteRange);
        }

        const menu = document.createElement("div");
        menu.className = "dbx-cms-context-menu";
        menu.setAttribute("role", "menu");
        menu.setAttribute("aria-label", cmsText(root, "editor_context_menu", "Editor-Kontextmenü"));

        const movable = movableEditorContextBlock(root, target);
        state(root).editorContextTarget = movable;
        const hasContextTarget = !!(marker || missingMedia || modPlaceholder || component || videoMedia || img || table || movable);
        const items = [
            [cmsText(root, "editor_context_undo", "Rückgängig"), "bi-arrow-counterclockwise", () => execEditorCommand(root, "undo"), false],
            [cmsText(root, "editor_context_redo", "Wiederholen"), "bi-arrow-clockwise", () => execEditorCommand(root, "redo"), false],
            [cmsText(root, "editor_context_select_all", "Alles markieren"), "bi-check2-square", () => selectEditorContents(root), false],
            [cmsText(root, "editor_context_block_up", "Block nach oben"), "bi-arrow-up", () => moveEditorContextBlock(root, movable, -1), !movable],
            [cmsText(root, "editor_context_block_down", "Block nach unten"), "bi-arrow-down", () => moveEditorContextBlock(root, movable, 1), !movable],
            [cmsText(root, "editor_context_module", "Modul-Platzhalter"), "bi-puzzle", () => openModPlaceholderOptions(root, modPlaceholder, cmsConfig(root) || {}), !modPlaceholder],
            [cmsText(root, "editor_context_video", "Video-Optionen"), "bi-camera-video", () => openInlineVideoOptions(root, videoMedia), !videoMedia],
            [cmsText(root, "editor_image_edit", "Bild bearbeiten"), "bi-pencil-square", () => openCmsImageEditor(root, img), !img],
            [cmsText(root, "editor_image_remove", "Bild aus Inhalt entfernen"), "bi-image-alt", () => removeEditorImage(root, img), !img],
            [cmsText(root, "editor_context_copy", "Kopieren"), "bi-clipboard", () => copyEditorContext(root, target), !hasSelection && !hasContextTarget],
            [cmsText(root, "editor_context_cut", "Ausschneiden"), "bi-scissors", () => cutEditorContext(root, target), !hasSelection && !hasContextTarget],
            [cmsText(root, "editor_context_paste", "Einfügen"), "bi-clipboard-plus", () => pasteEditorContext(root, movable), false],
            [cmsText(root, "editor_context_delete", "Löschen"), "bi-trash", () => deleteEditorContext(root, target), !hasSelection && !hasContextTarget]
        ];

        if (columnRow) {
            items.splice(
                5,
                0,
                [cmsText(root, "editor_columns_stacked", "Spalten untereinander"), "bi-layout-three-columns", () => setBootstrapColumnLayout(root, columnRow, "stacked"), false],
                [cmsText(root, "editor_columns_responsive", "Spalten nebeneinander"), "bi-layout-split", () => setBootstrapColumnLayout(root, columnRow, "responsive"), false],
                [cmsText(root, "editor_column_add", "Spalte hinzufügen"), "bi-plus-square", () => addBootstrapColumn(root, columnRow), false],
                [cmsText(root, "editor_columns_dissolve", "Spalten auflösen"), "bi-x-diamond", () => dissolveBootstrapColumns(root, columnRow), false]
            );
        }

        items.forEach(item => {
            const btn = contextMenuButton(item[0], item[1], item[2], item[3]);
            btn.__dbxCmsRoot = root;
            menu.appendChild(btn);
        });

        document.body.appendChild(menu);
        const vw = window.innerWidth || document.documentElement.clientWidth || 0;
        const vh = window.innerHeight || document.documentElement.clientHeight || 0;
        const rect = menu.getBoundingClientRect();
        const left = Math.max(8, Math.min(e.clientX, vw - rect.width - 8));
        const top = Math.max(8, Math.min(e.clientY, vh - rect.height - 8));
        menu.style.left = left + "px";
        menu.style.top = top + "px";

        const close = evt => {
            if (evt && menu.contains(evt.target)) return;
            closeEditorContextMenu(root);
        };
        const closeOnKey = evt => {
            if (evt.key === "Escape") closeEditorContextMenu(root);
        };
        const win = window;
        window.setTimeout(() => {
            document.addEventListener("mousedown", close, true);
            document.addEventListener("keydown", closeOnKey, true);
            win.addEventListener("scroll", close, true);
            win.addEventListener("resize", close, true);
        }, 0);
        state(root).editorContextMenu = menu;
        state(root).editorContextCleanup = () => {
            document.removeEventListener("mousedown", close, true);
            document.removeEventListener("keydown", closeOnKey, true);
            win.removeEventListener("scroll", close, true);
            win.removeEventListener("resize", close, true);
        };
    }

        return {
            editorSelectionRange,
            editorSelectionText,
            selectEditorContents,
            editorContextBlock,
            movableEditorContextBlock,
            movableEditorButtonBlock,
            clearEditorButtonDrag,
            closestBootstrapComponent,
            bootstrapRowColumns,
            bootstrapColumnRow,
            clearBootstrapColumnClasses,
            finishBootstrapColumnAction,
            setBootstrapColumnLayout,
            addBootstrapColumn,
            dissolveBootstrapColumns,
            contextMissingMediaTarget,
            selectEditorMissingMedia,
            removeEditorMissingMedia,
            handleEditorMissingMediaClick,
            removableEditorContextTarget,
            editorElementSibling,
            moveEditorContextBlock,
            closeEditorContextMenu,
            clipboardWriteText,
            clipboardReadText,
            editorContextBlockHtml,
            copyEditorContext,
            cutEditorContext,
            editorRangeIsInsertable,
            restoreEditorContextPasteRange,
            editorClipboardInlineButtonHtml,
            alignEditorContextPasteRange,
            editorClipboardHtmlAtCaret,
            insertEditorContextBlock,
            rememberEditorContextPasteRange,
            insertDraggedEditorBlockAtCaret,
            pasteEditorContext,
            deleteEditorContext,
            selectEditorNode,
            syncEditorAfterContextAction,
            contextImageTarget,
            contextTableCell,
            contextTableTarget,
            openEditorImageProperties,
            contextMenuButton,
            showEditorContextMenu
        };
    });
})(window, document);
