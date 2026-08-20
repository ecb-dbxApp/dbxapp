/*!
 * dbxapp cms-editor.js
 * Editor-DOM, Caret, Markerplatzierung und persistierbarer HTML-Zustand.
 */
(function (window, document) {
    "use strict";

    const runtime = window.dbx && window.dbx.cmsRuntime;
    if (!runtime || typeof runtime.register !== "function") {
        console.error("[dbx][cms-editor] CMS runtime missing");
        return;
    }

    runtime.register("editor", function (context) {
        const {
            qs,
            qsa,
            state,
            closestElement,
            getEditorInstance,
            getField,
            setField,
            markDirty,
            bindEditorHeight,
            scheduleEditorHeight,
            bindBootstrapCardEditingGuards,
            bindEditorMarkerEventsRetry,
            normalizeBootstrapComponents,
            normalizeEditorMarkers,
            normalizeModPlaceholders,
            normalizeCommentMarkers,
            repairInlineVideoHtml,
            repairInlineVideoPlayers,
            scheduleEditorMediaRender,
            cssSizeValue,
            inlineVideoOptionsButtonHtml,
            inlineVideoOptionsFromElement,
            syncInlineVideoOptionsToMedia,
            cmsMarkerElement,
            isEmptyEditorBlock
        } = context;
    function setEditorHtml(root, html) {
        html = repairInlineVideoHtml(root, html);
        // Bereits gespeicherte, inhaltslose Editor-Absaetze werden einmalig
        // in einem abgetrennten Container entfernt. Der sichtbare Editor muss
        // dafuer weder aufgebaut, geleert noch erneut gesetzt werden.
        const incoming = document.createElement("div");
        incoming.innerHTML = html || "";
        cleanEditorRuntimeNodes(incoming);
        html = incoming.innerHTML || "";
        root.__dbxCmsApplyingEditorHtml = true;
        const instance = getEditorInstance(root);
        const editor = qs(root, "[data-cms-editor]");
        if (instance) instance.value = html || "";
        else if (editor) editor.innerHTML = html || "";
        setField(root, "content", html || "");
        bindEditorHeight(root);
        scheduleEditorHeight(root);
        window.setTimeout(() => {
            normalizeEditorMarkers(root);
            const surface = editorSurface(root);
            normalizeBootstrapComponents(surface);
            repairInlineVideoPlayers(root, surface);
            normalizeInlineMediaLayout(surface);
            normalizeModPlaceholders(surface);
            // Laufzeitanker und Cursorhilfen nur in einer Kopie entfernen.
            // Das fruehere Reinigen und erneute Setzen des Live-DOM erzeugte
            // beim Laden kurz sichtbare bzw. wechselnde Leerzeilen.
            const repairedHtml = surface ? editorHtmlSnapshot(surface) : getEditorHtml(root);
            if (/(<video\b|<iframe\b|dbx-cms-inline-video-block)/i.test(html) && !/(<video\b|<iframe\b|dbx-cms-inline-video-block)/i.test(repairedHtml || "")) {
                setField(root, "content", html || "");
            } else {
                setField(root, "content", repairedHtml || "");
            }
            bindBootstrapCardEditingGuards(root);
            bindEditorMarkerEventsRetry(root);
            scheduleEditorHeight(root);
            root.__dbxCmsApplyingEditorHtml = false;
        }, 0);
    }

    function editorHtmlSnapshot(surface) {
        if (!surface || !surface.cloneNode) return "";
        const snapshot = surface.cloneNode(true);
        cleanEditorRuntimeNodes(snapshot);
        return snapshot.innerHTML || "";
    }

    function getEditorHtml(root) {
        const instance = getEditorInstance(root);
        const surface = editorSurface(root);
        if (surface) {
            normalizeBootstrapComponents(surface);
            normalizeInlineMediaLayout(surface);
            const html = editorHtmlSnapshot(surface);
            setField(root, "content", html);
            bindBootstrapCardEditingGuards(root);
            return html;
        }
        if (instance) return instance.value || "";
        const editor = qs(root, "[data-cms-editor]");
        return editor ? editor.innerHTML : getField(root, "content");
    }

    function editorSurface(root) {
        const instance = getEditorInstance(root);
        if (instance && instance.editor) return instance.editor;
        return qs(root, ".jodit-wysiwyg") || qs(root, "[data-cms-editor]");
    }

    function nodeElement(node) {
        if (!node) return null;
        return node.nodeType === 1 ? node : node.parentElement;
    }

    function rangeInsideSurface(surface, range) {
        if (!surface || !range) return false;
        const start = nodeElement(range.startContainer);
        const end = nodeElement(range.endContainer);
        return !!(start && end && surface.contains(start) && surface.contains(end));
    }

    function nodeHasEditorContent(node) {
        if (!node) return false;
        if (node.nodeType === 3) {
            return String(node.nodeValue || "").replace(/\uFEFF/g, "").replace(/\u00a0/g, " ").trim() !== "";
        }
        if (node.nodeType !== 1 && node.nodeType !== 11) return false;
        if (node.nodeType === 1) {
            const tag = node.tagName || "";
            if (tag === "BR") return false;
            if (/^(IMG|VIDEO|AUDIO|IFRAME|OBJECT|EMBED|TABLE|HR)$/i.test(tag)) return true;
        }
        return Array.from(node.childNodes || []).some(nodeHasEditorContent);
    }

    function setEditorCaretAfterNode(root, node) {
        const surface = editorSurface(root);
        if (!surface || !node) return false;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel) return false;
        const range = doc.createRange();
        if (!node.parentNode || !surface.contains(node)) {
            node = surface.lastChild;
            if (!node) {
                range.selectNodeContents(surface);
                range.collapse(false);
                sel.removeAllRanges();
                sel.addRange(range);
                state(root).editorRange = range.cloneRange();
                return true;
            }
        }
        const next = node.nextSibling;
        if (next && next.nodeType === 1 && /^(P|DIV|H1|H2|H3|H4|H5|H6|BLOCKQUOTE|LI)$/i.test(next.tagName || "")) {
            range.setStart(next, 0);
        } else {
            range.setStartAfter(node);
        }
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
        state(root).editorRange = range.cloneRange();
        return true;
    }

    function commitEditorCaretHosts(container) {
        qsa(container, "[data-dbx-cms-caret-host]").forEach(host => {
            const layout = host.getAttribute("data-dbx-cms-caret-host");
            host.innerHTML = String(host.innerHTML || "").replace(/\u200b/g, "");
            host.removeAttribute("data-dbx-cms-caret-host");
            host.removeAttribute("contenteditable");
            host.removeAttribute("tabindex");
            if (layout === "inline") {
                const parent = host.parentNode;
                if (!parent) return;
                while (host.firstChild) parent.insertBefore(host.firstChild, host);
                host.remove();
            } else if (isEmptyEditorBlock(host)) {
                host.remove();
            }
        });
    }

    function cleanEditorRuntimeNodes(container) {
        normalizeCommentMarkers(container);
        syncInlineVideoBlockSizes(container);
        commitEditorCaretHosts(container);
        qsa(container, "[data-dbx-cms-button-caret-anchor],[data-dbx-cms-element-caret-anchor]").forEach(anchor => anchor.remove());
        qsa(container, "[data-dbx-cms-movable-block]").forEach(block => {
            block.removeAttribute("data-dbx-cms-movable-block");
            block.removeAttribute("draggable");
        });
        qsa(container, "[data-dbx-cms-movable-button]").forEach(button => {
            button.removeAttribute("data-dbx-cms-movable-button");
            if (button.getAttribute("draggable") === "true") button.removeAttribute("draggable");
        });
        qsa(container, "[data-dbx-cms-editable-badge]").forEach(badge => {
            badge.removeAttribute("data-dbx-cms-editable-badge");
            if (badge.getAttribute("contenteditable") === "true") badge.removeAttribute("contenteditable");
            if (badge.getAttribute("tabindex") === "0") badge.removeAttribute("tabindex");
        });
        qsa(container, "[data-jodit-temp], [data-jodit-selection_marker], span[id^='jodit-selection_marker_']").forEach(el => el.remove());
        qsa(container, "[data-cms-inline-video-options-open]").forEach(el => el.remove());
        qsa(container, ".dbx-cms-inline-media video, .dbx-cms-inline-media iframe, .dbx-cms-inline-media source").forEach(media => {
            const src = String(media.getAttribute("src") || "");
            const poster = String(media.getAttribute("poster") || "");
            if (/^data:/i.test(src) || /^blob:/i.test(src)) media.removeAttribute("src");
            if (/^data:/i.test(poster) || /^blob:/i.test(poster)) media.removeAttribute("poster");
            if (!media.getAttribute("data-cms-media-slot")) media.setAttribute("data-cms-media-slot", "inline");
            if (/^video$/i.test(media.tagName || "")) {
                media.setAttribute("controls", "controls");
                media.setAttribute("preload", "none");
                media.setAttribute("playsinline", "playsinline");
            }
        });
        qsa(container, ".dbx-cms-inline-media, figure.dbx-cms-inline-video-block").forEach(wrapper => {
            if (!wrapper.getAttribute("data-cms-media-slot")) wrapper.setAttribute("data-cms-media-slot", "inline");
            if (wrapper.classList.contains("dbx-cms-inline-video-block")) {
                wrapper.removeAttribute("contenteditable");
                const videoMedia = qs(wrapper, "[data-cms-media-id], img[src*='dbx_mid='], video[src*='dbx_mid='], source[src*='dbx_mid=']");
                const videoIdMatch = String(
                    wrapper.getAttribute("data-cms-media-id")
                    || videoMedia?.getAttribute("data-cms-media-id")
                    || videoMedia?.getAttribute("src")
                    || ""
                ).match(/(?:dbx_mid=)?([0-9]+)/i);
                const videoId = Number(videoIdMatch && videoIdMatch[1] || 0);
                if (videoId > 0) wrapper.setAttribute("data-cms-media-id", String(videoId));
            }
            if (wrapper.getAttribute("data-cms-media-id") && !inlineMediaWrapperHasContent(wrapper)) wrapper.remove();
            const inlineImage = qs(wrapper, "img");
            if (inlineImage && !wrapper.classList.contains("dbx-cms-inline-video-block")) {
                const srcMatch = String(inlineImage.getAttribute("src") || "").match(/dbx_mid=([0-9]+)/i);
                if (srcMatch && Number(srcMatch[1] || 0) > 0) {
                    inlineImage.setAttribute("data-cms-media-id", String(Number(srcMatch[1])));
                }
                wrapper.removeAttribute("data-cms-media-id");
            }
        });
        removeEmptyEditorParagraphs(container);
        normalizeInlineMissingMedia(container);
    }

    function removeEmptyEditorParagraphs(container) {
        if (!container) return;
        qsa(container, "p").forEach(paragraph => {
            if (paragraph.matches(".dbx-cms-inline-media, .dbx-cms-mod-placeholder, [data-cms-media-id], [data-dbx-marker]")) return;
            if (qs(paragraph, "img,video,audio,iframe,object,embed,table,hr,svg,canvas,input,textarea,select,button,[data-cms-media-id],[data-dbx-marker]")) return;

            const text = String(paragraph.textContent || "")
                .replace(/[\s\u00a0\u200b\ufeff]+/g, "");
            if (text === "") paragraph.remove();
        });
    }

    function inlineMediaWrapperHasContent(wrap) {
        if (!wrap) return false;
        return !!qs(wrap, "img, video, iframe, source, .dbx-cms-inline-video-thumb, .dbx-cms-inline-video-empty, .dbx-cms-inline-media-missing");
    }

    function inlineMissingMediaWrap(doc, id, label) {
        const p = doc.createElement("p");
        p.className = "dbx-cms-inline-media dbx-cms-inline-media-missing-wrap";
        if (id > 0) p.setAttribute("data-cms-media-id", String(id));
        p.setAttribute("data-cms-media-slot", "inline");
        p.setAttribute("contenteditable", "false");
        p.setAttribute("tabindex", "0");
        p.setAttribute("data-dbx-tooltip", "Fehlende Mediendatei auswählen, Entf zum Löschen");
        const span = doc.createElement("span");
        span.className = "dbx-cms-inline-media-missing";
        span.setAttribute("aria-hidden", "true");
        span.textContent = label || ("Mediendatei #" + id + " nicht verfuegbar");
        p.appendChild(span);
        return p;
    }

    function normalizeInlineMissingMedia(container) {
        if (!container) return;
        const doc = container.ownerDocument || document;
        qsa(container, ".dbx-cms-inline-media-missing-wrap").forEach(wrap => {
            wrap.setAttribute("contenteditable", "false");
            wrap.classList.add("dbx-cms-inline-media");
            wrap.classList.remove("dbx-cms-mod-placeholder");
            const id = Number(wrap.getAttribute("data-cms-media-id") || 0);
            if (id > 0 && !wrap.getAttribute("data-cms-media-slot")) wrap.setAttribute("data-cms-media-slot", "inline");
        });
        qsa(container, ".dbx-cms-inline-media-missing").forEach(span => {
            if (closestElement(span, ".dbx-cms-inline-media-missing-wrap")) return;
            const id = Number(span.getAttribute("data-cms-media-id") || span.parentElement?.getAttribute("data-cms-media-id") || 0);
            const label = String(span.textContent || "").trim() || ("Mediendatei #" + id + " nicht verfuegbar");
            const host = span.parentElement;
            const wrap = inlineMissingMediaWrap(doc, id, label);
            if (host && (host.classList.contains("dbx-cms-mod-placeholder") || host.classList.contains("dbx-cms-inline-media"))) {
                host.replaceWith(wrap);
            } else {
                span.replaceWith(wrap);
            }
        });
    }

    function inlineVideoMediaSize(media) {
        if (!media) return { width: "", height: "" };
        let width = media.style && media.style.width ? media.style.width : "";
        let height = media.style && media.style.height ? media.style.height : "";
        if (!width) width = media.getAttribute("width") || media.getAttribute("data-cms-video-width") || "";
        if (!height) height = media.getAttribute("height") || media.getAttribute("data-cms-video-height") || "";
        return {
            width: cssSizeValue(width),
            height: cssSizeValue(height)
        };
    }

    function persistInlineVideoRenderedSize(wrapper) {
        if (!wrapper || !wrapper.getBoundingClientRect) return false;
        const rect = wrapper.getBoundingClientRect();
        if (!rect || rect.width < 32 || rect.height < 24) return false;
        const width = Math.round(rect.width) + "px";
        const height = Math.round(rect.height) + "px";
        const beforeWidth = cssSizeValue(wrapper.getAttribute("data-cms-video-width") || wrapper.style.width || "");
        const beforeHeight = cssSizeValue(wrapper.getAttribute("data-cms-video-height") || wrapper.style.height || "");
        const changed = beforeWidth !== width || beforeHeight !== height;
        wrapper.style.width = width;
        wrapper.style.height = height;
        wrapper.setAttribute("data-cms-video-width", width);
        wrapper.setAttribute("data-cms-video-height", height);
        const options = inlineVideoOptionsFromElement(wrapper);
        options.width = width;
        options.height = height;
        syncInlineVideoOptionsToMedia(wrapper, options);
        return changed;
    }

    function beginInlineVideoResizeTrack(root, wrapper) {
        if (!root || !wrapper) return;
        const rect = wrapper.getBoundingClientRect ? wrapper.getBoundingClientRect() : null;
        state(root).inlineVideoResizeTrack = {
            wrapper,
            width: rect ? Math.round(rect.width) : 0,
            height: rect ? Math.round(rect.height) : 0
        };
    }

    function finishInlineVideoResizeTrack(root) {
        const s = state(root);
        const track = s.inlineVideoResizeTrack;
        s.inlineVideoResizeTrack = null;
        if (!track || !track.wrapper || !track.wrapper.isConnected) return false;
        const rect = track.wrapper.getBoundingClientRect ? track.wrapper.getBoundingClientRect() : null;
        if (!rect) return false;
        const width = Math.round(rect.width);
        const height = Math.round(rect.height);
        if (Math.abs(width - track.width) < 2 && Math.abs(height - track.height) < 2) return false;
        if (!persistInlineVideoRenderedSize(track.wrapper)) return false;
        syncEditorDom(root);
        return true;
    }

    function syncInlineVideoBlockSizes(container) {
        if (!container) return;
        qsa(container, ".dbx-cms-inline-video-block").forEach(wrapper => {
            if (!qs(wrapper, "[data-cms-inline-video-options-open]")) {
                wrapper.insertAdjacentHTML("beforeend", inlineVideoOptionsButtonHtml());
            }
            const media = qs(wrapper, ".dbx-cms-inline-video-thumb, img, video, iframe");
            const size = inlineVideoMediaSize(media);
            const wrapperWidth = cssSizeValue(wrapper.style.width || wrapper.getAttribute("data-cms-video-width") || "");
            const wrapperHeight = cssSizeValue(wrapper.style.height || wrapper.getAttribute("data-cms-video-height") || "");
            let width = wrapperWidth || size.width;
            let height = wrapperHeight || size.height;
            if (width) {
                wrapper.style.width = width;
                wrapper.setAttribute("data-cms-video-width", width);
            }
            if (height && height !== "auto") {
                wrapper.style.height = height;
                wrapper.setAttribute("data-cms-video-height", height);
            }
            if (media) {
                media.removeAttribute("width");
                media.removeAttribute("height");
                if (media.style) {
                    media.style.width = "";
                    media.style.height = "";
                }
            }
        });
    }

    function plainMarkerName(text) {
        return dbx.cmsMarker.plainTextName(text);
    }

    function normalizePlainTextMarkers(container) {
        dbx.cmsMarker.normalizePlainText(container);
    }

    function normalizeInlineMediaLayout(container) {
        if (!container) return;
        qsa(container, ".dbx-cms-inline-media img, .dbx-cms-inline-media video, .dbx-cms-inline-media iframe").forEach(media => {
            const wrapper = closestElement(media, ".dbx-cms-inline-media");
            if (!wrapper) return;
            const floatValue = String(media.style.float || "").toLowerCase();
            if (floatValue !== "left" && floatValue !== "right") return;

            wrapper.style.float = floatValue;
            wrapper.style.marginLeft = media.style.marginLeft || "";
            wrapper.style.marginRight = media.style.marginRight || "";
            media.style.float = "";
            media.style.marginLeft = "";
            media.style.marginRight = "";
            media.style.display = "";
        });
    }

    function topEditorChild(surface, range) {
        if (!surface || !range) return null;
        if (range.startContainer === surface) {
            const child = surface.childNodes[Math.min(range.startOffset, Math.max(surface.childNodes.length - 1, 0))];
            return child && child.nodeType === 1 ? child : null;
        }
        let el = nodeElement(range.startContainer);
        if (!el || el === surface || !surface.contains(el)) return null;
        while (el.parentElement && el.parentElement !== surface) el = el.parentElement;
        return el.parentElement === surface ? el : null;
    }

    function canSplitForMarker(block) {
        if (!block || block.nodeType !== 1) return false;
        return /^(P|DIV|H1|H2|H3|H4|H5|H6|BLOCKQUOTE|UL|OL|LI)$/i.test(block.tagName || "")
            && !/^(TABLE|FIGURE)$/i.test(block.tagName || "");
    }

    function insertFragmentAfter(parent, fragment, afterNode) {
        let last = afterNode;
        Array.from(fragment.childNodes || []).forEach(node => {
            parent.insertBefore(node, last ? last.nextSibling : null);
            last = node;
        });
        return last;
    }

    function insertEditorHrNode(root, hrNode) {
        const instance = getEditorInstance(root);
        if (instance && typeof instance.setMode === "function") {
            instance.setMode(window.Jodit && window.Jodit.MODE_WYSIWYG ? window.Jodit.MODE_WYSIWYG : 1);
        }

        restoreEditorSelection(root);

        const surface = editorSurface(root);
        if (!surface) return false;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        let range = sel && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : state(root).editorRange;
        if (!hrNode) return false;

        if (!rangeInsideSurface(surface, range)) {
            surface.appendChild(hrNode);
            setEditorCaretAfterNode(root, hrNode);
            normalizeEditorMarkers(root);
            bindEditorMarkerEventsRetry(root);
            syncEditorDom(root);
            return true;
        }

        if (!range.collapsed) {
            range.deleteContents();
            if (sel) {
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }

        const block = topEditorChild(surface, range);
        if (canSplitForMarker(block) && block.contains(nodeElement(range.startContainer))) {
            const parent = block.parentElement;
            const suffixRange = range.cloneRange();
            suffixRange.setEndAfter(block);
            const suffix = suffixRange.extractContents();
            const blockHasContent = nodeHasEditorContent(block);

            if (blockHasContent) {
                parent.insertBefore(hrNode, block.nextSibling);
            } else {
                parent.insertBefore(hrNode, block);
                block.remove();
            }

            if (nodeHasEditorContent(suffix)) {
                insertFragmentAfter(parent, suffix, hrNode);
            }
        } else if (range.startContainer === surface) {
            surface.insertBefore(hrNode, surface.childNodes[range.startOffset] || null);
        } else if (block && block.parentElement === surface) {
            surface.insertBefore(hrNode, block.nextSibling);
        } else {
            range.insertNode(hrNode);
        }

        normalizeEditorMarkers(root);
        bindEditorMarkerEventsRetry(root);
        syncEditorDom(root);
        setEditorCaretAfterNode(root, hrNode);
        return true;
    }

    function insertEditorPlainHr(root) {
        const surface = editorSurface(root);
        const doc = surface ? (surface.ownerDocument || document) : document;
        return insertEditorHrNode(root, doc.createElement("hr"));
    }

    function insertEditorMarkerElement(root, marker, label) {
        const surface = editorSurface(root);
        const doc = surface ? (surface.ownerDocument || document) : document;
        return insertEditorHrNode(root, cmsMarkerElement(marker, label, doc));
    }

    function saveEditorSelection(root) {
        const surface = editorSurface(root);
        if (!surface) return false;
        if (state(root).selectedMarker || state(root).selectedMissingMedia) {
            hideEditorCaretHint(root);
            return false;
        }
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel || !sel.rangeCount) {
            hideEditorCaretHint(root);
            return false;
        }
        const range = sel.getRangeAt(0);
        const common = range.commonAncestorContainer;
        const commonEl = nodeElement(common);
        if (common !== surface && (!commonEl || !surface.contains(commonEl))) {
            hideEditorCaretHint(root);
            return false;
        }
        state(root).editorRange = range.cloneRange();
        refreshEditorCaretHint(root);
        return true;
    }

    function refreshEditorCaretHint(root, explicitRange) {
        const surface = editorSurface(root);
        const s = state(root);
        // Immer die aktuelle Browser-Selection verwenden. Eine geklonte
        // editorRange kann Jodits Caret bereits um einen Frame verfehlen.
        // Solange der eigene Cursor sichtbar ist, blendet CSS die native Caret
        // aus; dadurch existiert auch optisch nur genau eine Position.
        const range = explicitRange || s.editorContextPasteRange || currentEditorCaretRange(surface);
        if (!range) {
            hideEditorCaretHint(root);
            return;
        }
        if (!surface || !range || !range.collapsed || !rangeInsideSurface(surface, range)) {
            hideEditorCaretHint(root);
            return;
        }
        if (s.editorCaretHintFrame) window.cancelAnimationFrame(s.editorCaretHintFrame);
        s.editorCaretHintFrame = window.requestAnimationFrame(() => {
            s.editorCaretHintFrame = null;
            const liveRange = explicitRange || s.editorContextPasteRange || currentEditorCaretRange(surface);
            if (!liveRange || !rangeInsideSurface(surface, liveRange)) return hideEditorCaretHint(root);
            const rect = editorCaretRect(liveRange);
            if (!rect) return hideEditorCaretHint(root);
            const surfaceRect = surface.getBoundingClientRect();
            if ((!rect.width && !rect.height)
                || rect.right < surfaceRect.left - 4
                || rect.left > surfaceRect.right + 4
                || rect.bottom < surfaceRect.top - 4
                || rect.top > surfaceRect.bottom + 4) {
                return hideEditorCaretHint(root);
            }
            let hint = s.editorCaretHint;
            if (!hint || !hint.parentNode) {
                hint = document.createElement("span");
                hint.className = "dbx-cms-editor-caret-hint";
                hint.setAttribute("data-cms-editor-caret-hint", "");
                hint.setAttribute("aria-hidden", "true");
                document.body.appendChild(hint);
                s.editorCaretHint = hint;
            }
            const height = Math.max(11, Math.min(15, Math.round((rect.height || 16) * .78)));
            hint.style.left = Math.round(rect.left - 1) + "px";
            hint.style.top = Math.round(rect.top + Math.max(0, ((rect.height || height) - height) / 2)) + "px";
            hint.style.height = height + "px";
            surface.classList.add("is-dbx-cms-caret-preview");
        });
    }

    function currentEditorCaretRange(surface) {
        if (!surface) return null;
        const doc = surface.ownerDocument || document;
        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel || !sel.rangeCount) return null;
        const range = sel.getRangeAt(0);
        return range.collapsed && rangeInsideSurface(surface, range) ? range : null;
    }

    function editorCaretRect(range) {
        if (!range) return null;
        const usable = rect => rect && Number.isFinite(rect.left)
            && Number.isFinite(rect.top) && ((rect.height || 0) > 0 || (rect.width || 0) > 0);
        try {
            const direct = range.getBoundingClientRect();
            if (usable(direct)) return direct;
            const clientRects = Array.from(range.getClientRects ? range.getClientRects() : []);
            const clientRect = clientRects.find(usable);
            if (clientRect) return clientRect;
        } catch (_) {}

        // Kollabierte Ranges an Text- und Elementgrenzen liefern je nach
        // Browser gelegentlich ein leeres Rechteck. Die Kante eines direkt
        // benachbarten Zeichens bzw. Elements ist stabil und benoetigt keinen
        // temporaeren Messknoten im Editor-DOM.
        const node = range.startContainer;
        const offset = Number(range.startOffset || 0);
        const doc = node && (node.ownerDocument || document);
        if (!node || !doc || !doc.createRange) return null;
        try {
            const probe = doc.createRange();
            if (node.nodeType === 3 && String(node.nodeValue || "").length) {
                const length = String(node.nodeValue || "").length;
                const before = offset > 0;
                probe.setStart(node, before ? offset - 1 : offset);
                probe.setEnd(node, before ? offset : Math.min(length, offset + 1));
                const rect = probe.getBoundingClientRect();
                if (usable(rect)) return {
                    left: before ? rect.right : rect.left,
                    right: before ? rect.right : rect.left,
                    top: rect.top,
                    bottom: rect.bottom,
                    width: 0,
                    height: rect.height
                };
            }
            if (node.nodeType === 1) {
                const sibling = offset > 0 ? node.childNodes[offset - 1] : node.childNodes[offset];
                const element = sibling && (sibling.nodeType === 1 ? sibling : sibling.parentElement);
                const rect = element && element.getBoundingClientRect ? element.getBoundingClientRect() : null;
                if (usable(rect)) return {
                    left: offset > 0 ? rect.right : rect.left,
                    right: offset > 0 ? rect.right : rect.left,
                    top: rect.top,
                    bottom: rect.bottom,
                    width: 0,
                    height: rect.height
                };
                const hostRect = node.getBoundingClientRect ? node.getBoundingClientRect() : null;
                if (usable(hostRect)) return {
                    left: hostRect.left,
                    right: hostRect.left,
                    top: hostRect.top,
                    bottom: hostRect.bottom,
                    width: 0,
                    height: hostRect.height
                };
            }
        } catch (_) {}
        return null;
    }

    function hideEditorCaretHint(root) {
        const s = state(root);
        const surface = editorSurface(root);
        if (s.editorCaretHintTimer) {
            window.clearTimeout(s.editorCaretHintTimer);
            s.editorCaretHintTimer = null;
        }
        if (s.editorCaretHintFrame) {
            window.cancelAnimationFrame(s.editorCaretHintFrame);
            s.editorCaretHintFrame = null;
        }
        const hint = s.editorCaretHint || qs(document, "[data-cms-editor-caret-hint]");
        if (hint) hint.remove();
        s.editorCaretHint = null;
        if (surface) surface.classList.remove("is-dbx-cms-caret-preview");
    }

    function restoreEditorSelection(root) {
        const surface = editorSurface(root);
        if (!surface) return false;
        const doc = surface.ownerDocument || document;
        const range = state(root).editorRange;
        if (!range || !range.startContainer || !range.endContainer) {
            if (surface.focus) surface.focus();
            return false;
        }

        const start = nodeElement(range.startContainer);
        const end = nodeElement(range.endContainer);
        if (!start || !end || !surface.contains(start) || !surface.contains(end)) {
            state(root).editorRange = null;
            if (surface.focus) surface.focus();
            return false;
        }

        const sel = doc.getSelection ? doc.getSelection() : null;
        if (!sel) return false;
        if (surface.focus) surface.focus({ preventScroll: true });
        sel.removeAllRanges();
        sel.addRange(range);
        return true;
    }

    function pushEditorHtml(root) {
        const surface = editorSurface(root);
        const html = surface ? (surface.innerHTML || "") : getEditorHtml(root);
        const instance = getEditorInstance(root);
        if (instance && instance.value !== html) instance.value = html;
        setField(root, "content", html);
        markDirty(root);
        scheduleEditorHeight(root);
    }

    function hoistEditorMarkersToSurface(surface) {
        if (!surface) return;
        qsa(surface, ".dbx-cms-marker,[data-dbx-marker]").forEach(marker => {
            if (marker.parentElement === surface) return;
            let block = marker.parentElement;
            while (block && block.parentElement && block.parentElement !== surface) {
                block = block.parentElement;
            }
            if (block && block.parentElement === surface) {
                surface.insertBefore(marker, block.nextSibling);
            } else {
                surface.appendChild(marker);
            }
        });
    }

    function surfaceEditorBlocks(surface, ignoreMarker) {
        if (!surface) return [];
        return Array.from(surface.children).filter(el => {
            return el.nodeType === 1 && el !== ignoreMarker;
        });
    }

    function markerSurfacePlacement(surface, x, y, ignoreMarker) {
        if (!surface) return null;
        const blocks = surfaceEditorBlocks(surface, ignoreMarker);
        if (!blocks.length) {
            return { ref: null, before: true, target: null };
        }

        for (let i = 0; i < blocks.length; i++) {
            const block = blocks[i];
            const rect = block.getBoundingClientRect ? block.getBoundingClientRect() : null;
            if (!rect || !rect.height) continue;
            if (y < rect.top + rect.height / 2) {
                return { ref: block, before: true, target: block };
            }
        }

        const last = blocks[blocks.length - 1];
        return { ref: last, before: false, target: last };
    }

    function syncEditorDom(root, options) {
        options = options || {};
        const surface = editorSurface(root);
        normalizeEditorMarkers(root);
        repairInlineVideoPlayers(root, surface);
        normalizeInlineMediaLayout(surface);
        normalizeBootstrapComponents(surface);
        const html = surface ? editorHtmlSnapshot(surface) : getEditorHtml(root);
        setField(root, "content", html || "");
        bindBootstrapCardEditingGuards(root);
        if (!options.silent) markDirty(root);
        scheduleEditorHeight(root);
        scheduleEditorMediaRender(root, html);
    }

        return {
            setEditorHtml,
            editorHtmlSnapshot,
            getEditorHtml,
            editorSurface,
            nodeElement,
            rangeInsideSurface,
            nodeHasEditorContent,
            setEditorCaretAfterNode,
            commitEditorCaretHosts,
            cleanEditorRuntimeNodes,
            removeEmptyEditorParagraphs,
            inlineMediaWrapperHasContent,
            inlineMissingMediaWrap,
            normalizeInlineMissingMedia,
            inlineVideoMediaSize,
            persistInlineVideoRenderedSize,
            beginInlineVideoResizeTrack,
            finishInlineVideoResizeTrack,
            syncInlineVideoBlockSizes,
            plainMarkerName,
            normalizePlainTextMarkers,
            normalizeInlineMediaLayout,
            topEditorChild,
            canSplitForMarker,
            insertFragmentAfter,
            insertEditorHrNode,
            insertEditorPlainHr,
            insertEditorMarkerElement,
            saveEditorSelection,
            refreshEditorCaretHint,
            currentEditorCaretRange,
            editorCaretRect,
            hideEditorCaretHint,
            restoreEditorSelection,
            pushEditorHtml,
            hoistEditorMarkersToSurface,
            surfaceEditorBlocks,
            markerSurfacePlacement,
            syncEditorDom
        };
    });
})(window, document);
