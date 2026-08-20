/*!
 * dbxapp cms-components.js
 * Bootstrap-Komponenten, Badges und Caret-Anker im CMS-Editor.
 */
(function (window, document) {
    "use strict";

    const runtime = window.dbx && window.dbx.cmsRuntime;
    if (!runtime || typeof runtime.register !== "function") {
        console.error("[dbx][cms-components] CMS runtime missing");
        return;
    }

    runtime.register("components", function (context) {
        const {
            qs,
            qsa,
            closestElement,
            cmsText,
            commitEditorCaretHosts,
            editorSurface,
            escapeHtml,
            insertEditorFragment,
            isEmptyEditorBlock,
            markDirty,
            nodeHasEditorContent,
            normalizeEditorMarkers,
            refreshEditorCaretHint,
            scheduleEditorHeight,
            setEditorCaretBesideElement,
            setEditorCaretInCardBody,
            syncEditorDom
        } = context;
    function bootstrapComponentItems(root) {
        const openWinData = "lib=openWin|title=Information|width=900|height=80%|position=center-top|reload=1|minimizable=1|maximizable=1";
        const firstColumn = escapeHtml(cmsText(root, "editor_columns_first", "Inhalt der ersten Spalte."));
        const secondColumn = escapeHtml(cmsText(root, "editor_columns_second", "Inhalt der zweiten Spalte."));
        const thirdColumn = escapeHtml(cmsText(root, "editor_columns_third", "Inhalt der dritten Spalte."));
        return [
            {
                label: "Hinweis / Alert",
                html: '<div class="alert alert-info" role="alert"><h4 class="alert-heading">Hinweis</h4><p>Kurzer Hinweistext fuer den Inhalt.</p></div><p></p>'
            },
            {
                label: "Card",
                html: '<div class="card"><div class="card-body"><h3 class="card-title">Karten-Titel</h3><p class="card-text">Kurzer Text fuer diese Karte.</p><a class="btn btn-primary" href="#">Mehr erfahren</a></div></div><p></p>'
            },
            {
                label: "3 Karten",
                html: '<div class="row row-cols-1 row-cols-md-3 g-3"><div class="col"><div class="card h-100"><div class="card-body"><h3 class="card-title">Erste Karte</h3><p class="card-text">Kurzer Text.</p></div></div></div><div class="col"><div class="card h-100"><div class="card-body"><h3 class="card-title">Zweite Karte</h3><p class="card-text">Kurzer Text.</p></div></div></div><div class="col"><div class="card h-100"><div class="card-body"><h3 class="card-title">Dritte Karte</h3><p class="card-text">Kurzer Text.</p></div></div></div></div><p></p>'
            },
            {
                label: cmsText(root, "editor_columns_two", "2 Spalten"),
                html: `<div class="row g-3"><div class="col-12 col-md"><p>${firstColumn}</p></div><div class="col-12 col-md"><p>${secondColumn}</p></div></div><p></p>`
            },
            {
                label: cmsText(root, "editor_columns_three", "3 Spalten"),
                html: `<div class="row g-3"><div class="col-12 col-md"><p>${firstColumn}</p></div><div class="col-12 col-md"><p>${secondColumn}</p></div><div class="col-12 col-md"><p>${thirdColumn}</p></div></div><p></p>`
            },
            {
                label: "List Group",
                html: '<div class="list-group"><a class="list-group-item list-group-item-action active" href="#">Aktiver Punkt</a><a class="list-group-item list-group-item-action" href="#">Weiterer Punkt</a><a class="list-group-item list-group-item-action" href="#">Dritter Punkt</a></div><p></p>'
            },
            {
                label: "CTA Button",
                html: '<p><a class="btn btn-primary" href="#">Informationen anfragen</a> <a class="btn btn-outline-primary" href="#">Mehr erfahren</a></p><p></p>'
            },
            {
                label: "Pills",
                html: '<div class="d-flex flex-wrap gap-2"><span class="badge text-bg-primary">Pill 1</span><span class="badge text-bg-secondary">Pill 2</span><span class="badge text-bg-success">Pill 3</span></div><p></p>'
            },
            {
                label: "openWin Link",
                html: '<p><a class="btn btn-outline-primary dbx-win" href="kontakt" data-dbx="' + openWinData + '|url=kontakt">Im Fenster oeffnen</a></p><p></p>'
            },
            {
                label: "Accordion",
                html: '<div class="accordion" id="dbxCmsAccordion"><div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#dbxCmsAccordionOne" aria-expanded="true" aria-controls="dbxCmsAccordionOne">Erster Bereich</button></h3><div id="dbxCmsAccordionOne" class="accordion-collapse collapse show" data-bs-parent="#dbxCmsAccordion"><div class="accordion-body">Inhalt des ersten Bereichs.</div></div></div><div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dbxCmsAccordionTwo" aria-expanded="false" aria-controls="dbxCmsAccordionTwo">Zweiter Bereich</button></h3><div id="dbxCmsAccordionTwo" class="accordion-collapse collapse" data-bs-parent="#dbxCmsAccordion"><div class="accordion-body">Inhalt des zweiten Bereichs.</div></div></div></div><p></p>'
            },
            {
                label: "Tabs",
                html: '<div><ul class="nav nav-tabs" role="tablist"><li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#dbxCmsTabOne" role="tab">Tab 1</button></li><li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#dbxCmsTabTwo" role="tab">Tab 2</button></li></ul><div class="tab-content border border-top-0 p-3"><div class="tab-pane fade show active" id="dbxCmsTabOne" role="tabpanel">Inhalt Tab 1.</div><div class="tab-pane fade" id="dbxCmsTabTwo" role="tabpanel">Inhalt Tab 2.</div></div></div><p></p>'
            },
            {
                label: "Tabelle",
                html: '<div class="table-responsive"><table class="table table-striped table-hover align-middle"><thead><tr><th>Spalte 1</th><th>Spalte 2</th><th>Spalte 3</th></tr></thead><tbody><tr><td>Wert</td><td>Wert</td><td>Wert</td></tr><tr><td>Wert</td><td>Wert</td><td>Wert</td></tr></tbody></table></div><p></p>'
            }
        ];
    }

    function cmsBootstrapComponentIcon() {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M7 8h10M7 12h4M13 12h4M7 16h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
    }

    function insertBootstrapComponent(root, html) {
        const uid = "dbxCms" + Date.now().toString(36) + Math.floor(Math.random() * 1000).toString(36);
        html = String(html || "")
            .replaceAll("dbxCmsAccordion", uid + "Accordion")
            .replaceAll("dbxCmsAccordionOne", uid + "AccordionOne")
            .replaceAll("dbxCmsAccordionTwo", uid + "AccordionTwo")
            .replaceAll("dbxCmsTabOne", uid + "TabOne")
            .replaceAll("dbxCmsTabTwo", uid + "TabTwo");
        insertEditorFragment(root, html);
        normalizeEditorMarkers(root);
        normalizeBootstrapComponents(editorSurface(root));
        syncEditorDom(root);
        markDirty(root);
        scheduleEditorHeight(root);
    }

    function openEditableBadgeTextInput(badge, surface) {
        if (!badge || !surface || !badge.isConnected) return;
        const doc = surface.ownerDocument || document;
        const previous = doc.__dbxCmsBadgeTextInput;
        if (previous && previous.isConnected) previous.remove();

        const initialText = String(badge.textContent || "").trim();
        const input = doc.createElement("input");
        input.type = "text";
        input.className = "form-control form-control-sm dbx-cms-badge-text-input";
        input.value = initialText;
        input.setAttribute("aria-label", "Pill-Text bearbeiten");
        input.dataset.dbxTooltip = "Enter: übernehmen · Escape: verwerfen";

        const rect = badge.getBoundingClientRect();
        input.style.position = "fixed";
        input.style.zIndex = "100000";
        input.style.left = Math.max(8, Math.min(rect.left, doc.documentElement.clientWidth - 168)) + "px";
        input.style.top = Math.max(8, Math.min(rect.top, doc.documentElement.clientHeight - 42)) + "px";
        input.style.width = Math.max(160, Math.min(320, rect.width + 80)) + "px";
        doc.body.appendChild(input);
        doc.__dbxCmsBadgeTextInput = input;

        let finished = false;
        const finish = commit => {
            if (finished) return;
            finished = true;
            const nextText = String(input.value || "").trim();
            input.remove();
            if (doc.__dbxCmsBadgeTextInput === input) doc.__dbxCmsBadgeTextInput = null;
            if (!commit || nextText === "" || nextText === initialText || !badge.isConnected) return;

            badge.textContent = nextText;
            const editorRoot = closestElement(surface, ".dbx-cms");
            if (editorRoot) syncEditorDom(editorRoot);
        };

        input.addEventListener("keydown", event => {
            if (event.key === "Enter") {
                event.preventDefault();
                finish(true);
            } else if (event.key === "Escape") {
                event.preventDefault();
                finish(false);
            }
        });
        input.addEventListener("blur", () => finish(true));
        (doc.defaultView || window).setTimeout(() => {
            input.focus();
            input.select();
        }, 0);
    }

    function bindEditableBadgeEditing(surface) {
        if (!surface) return;
        const doc = surface.ownerDocument || document;
        if (doc.__dbxCmsEditableBadgeGuardsBound) return;
        doc.__dbxCmsEditableBadgeGuardsBound = true;

        const focusEditableBadge = event => {
            const badge = closestElement(event.target, ".badge[data-dbx-cms-editable-badge]");
            const activeSurface = closestElement(badge, ".jodit-wysiwyg, [data-cms-editor]");
            if (!badge || !activeSurface) return;
            if (badge.classList.contains("position-absolute") || closestElement(badge, ".position-absolute")) return;

            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === "function") event.stopImmediatePropagation();
            (doc.defaultView || window).setTimeout(() => openEditableBadgeTextInput(badge, activeSurface), 0);
        };

        const focusAdjacentElement = event => {
            const anchor = closestElement(event.target, "[data-dbx-cms-button-caret-anchor],[data-dbx-cms-element-caret-anchor]");
            const activeSurface = closestElement(anchor, ".jodit-wysiwyg, [data-cms-editor]");
            const editorRoot = closestElement(activeSurface, ".dbx-cms");
            const side = anchor?.getAttribute("data-dbx-cms-caret-side") === "before" ? "before" : "after";
            const element = anchor && (side === "before" ? anchor.nextElementSibling : anchor.previousElementSibling);
            if (!anchor || !activeSurface || !editorRoot || !element) return;
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === "function") event.stopImmediatePropagation();
            (doc.defaultView || window).setTimeout(() => {
                if (typeof activeSurface.focus === "function") activeSurface.focus({ preventScroll: true });
                if (anchor.hasAttribute("data-dbx-cms-element-caret-anchor")) {
                    const card = element.matches("img.card-img-top,img.card-img,img.card-img-bottom") ? closestElement(element, ".card") : null;
                    const cardBody = card ? qs(card, ".card-body") : null;
                    if (cardBody) {
                        setEditorCaretInCardBody(editorRoot, cardBody);
                    } else {
                        setEditorCaretBesideElement(editorRoot, element, side);
                    }
                } else {
                    setEditorCaretBesideElement(editorRoot, element, side);
                }
                refreshEditorCaretHint(editorRoot);
            }, 0);
        };

        // Capture before Jodit handles the inline node; the compact text input
        // updates only the label and therefore preserves the complete badge.
        doc.addEventListener("mousedown", focusEditableBadge, true);
        doc.addEventListener("mousedown", focusAdjacentElement, true);
    }

    function createEditorCaretAnchor(doc, element, kind, layout, side) {
        if (!doc || !element || !element.parentNode) return null;
        side = side === "before" ? "before" : "after";
        const anchor = doc.createElement(layout === "block" ? "div" : "span");
        anchor.setAttribute(kind === "button" ? "data-dbx-cms-button-caret-anchor" : "data-dbx-cms-element-caret-anchor", "1");
        anchor.setAttribute("data-dbx-cms-caret-layout", layout === "block" ? "block" : "inline");
        anchor.setAttribute("data-dbx-cms-caret-side", side);
        anchor.setAttribute("contenteditable", "false");
        anchor.setAttribute("aria-label", side === "before" ? "Cursorposition vor Element" : "Cursorposition hinter Element");
        anchor.textContent = "\u200b";
        element.parentNode.insertBefore(anchor, side === "before" ? element : element.nextSibling);
        return anchor;
    }

    function createEditorCaretAnchors(doc, element, kind, layout) {
        createEditorCaretAnchor(doc, element, kind, layout, "before");
        createEditorCaretAnchor(doc, element, kind, layout, "after");
    }

    function editorCaretAnchorLayout(element) {
        if (!element || !element.matches) return "inline";
        return element.matches("figure,table,video,hr,img.card-img-top,img.card-img,img.card-img-bottom,.dbx-cms-marker,.dbx-cms-inline-media,.dbx-cms-inline-media-missing-wrap,.alert,.card,.list-group,.accordion,.table-responsive,.row,.nav-tabs,.tab-content")
            ? "block"
            : "inline";
    }

    function normalizeEditorElementCaretAnchors(surface, doc) {
        const selector = ".dbx-cms-marker,.dbx-cms-inline-media,.dbx-cms-inline-media-missing-wrap,figure,table,img,video,hr,.alert,.card,.list-group,.accordion,.table-responsive,.row,.nav-tabs,.tab-content,.badge";
        qsa(surface, selector).forEach(element => {
            if (element.matches("img,video") && closestElement(element, "figure,.dbx-cms-inline-media")) return;
            if (element.matches("table") && closestElement(element, ".table-responsive")) return;
            if (element.matches(".badge") && (closestElement(element, "a.btn") || element.classList.contains("position-absolute") || closestElement(element, ".position-absolute"))) return;
            const lockedParent = element.closest("[contenteditable='false']");
            if (lockedParent && surface.contains(lockedParent) && lockedParent !== element) return;
            createEditorCaretAnchors(doc, element, "element", editorCaretAnchorLayout(element));
        });
    }

    function normalizeFlexContentAlignment(surface) {
        if (!surface) return;
        const runtimeAnchor = "[data-dbx-cms-button-caret-anchor],[data-dbx-cms-element-caret-anchor]";
        qsa(surface, ".d-flex,.d-inline-flex").forEach(flex => {
            // Automatisch wird nur die eindeutige Pill-/Button-Zeile
            // migriert. Bei beliebigen Layout-Flexboxen koennte text-align
            // absichtlich fuer den Text innerhalb der Spalten gesetzt sein.
            const items = Array.from(flex.children || []).filter(child => !child.matches(runtimeAnchor));
            if (!items.length || !items.every(child => child.matches(".badge,.btn,a.btn,button.btn"))) return;

            let alignment = String(flex.style.textAlign || "").toLowerCase();
            if (!alignment && flex.classList.contains("text-center")) alignment = "center";
            if (!alignment && flex.classList.contains("text-end")) alignment = "end";
            if (!alignment && flex.classList.contains("text-start")) alignment = "start";
            const flexClass = {
                left: "justify-content-start",
                start: "justify-content-start",
                center: "justify-content-center",
                right: "justify-content-end",
                end: "justify-content-end"
            }[alignment];
            if (!flexClass) return;

            [
                "justify-content-start", "justify-content-center", "justify-content-end",
                "justify-content-between", "justify-content-around", "justify-content-evenly"
            ].forEach(className => flex.classList.remove(className));
            flex.classList.add(flexClass);
            flex.classList.remove("text-start", "text-center", "text-end");
            flex.style.removeProperty("text-align");
            if (!String(flex.getAttribute("style") || "").trim()) flex.removeAttribute("style");
        });
    }

    function normalizeBootstrapComponents(surface) {
        if (!surface) return;
        const doc = surface.ownerDocument || document;
        bindEditableBadgeEditing(surface);
        commitEditorCaretHosts(surface);
        normalizeFlexContentAlignment(surface);
        // Laufzeitanker immer deterministisch neu aufbauen. Wird direkt vor
        // einem vorhandenen Anker Text eingegeben, läge dieser sonst nicht
        // mehr unmittelbar neben dem Button und ein zweiter Anker entstünde.
        qsa(surface, "[data-dbx-cms-button-caret-anchor],[data-dbx-cms-element-caret-anchor]").forEach(anchor => anchor.remove());
        qsa(surface, "a.btn").forEach(button => {
            button.setAttribute("draggable", "true");
            button.setAttribute("data-dbx-cms-movable-button", "1");
        });
        // Nur Laufzeitattribute: Beim Speichern entfernt cleanEditorRuntimeNodes
        // diese Kennzeichnung wieder. So bleibt der gespeicherte Content sauber.
        qsa(surface, ".dbx-cms-marker,.dbx-cms-inline-media,.dbx-cms-inline-media-missing-wrap,figure,table,img,video,hr,p,h1,h2,h3,h4,h5,h6,ul,ol,dl,pre,blockquote,section,article,.alert,.card,.list-group,.accordion,.table-responsive,.row").forEach(block => {
            const lockedParent = block.closest("[contenteditable='false']");
            if (lockedParent && surface.contains(lockedParent) && lockedParent !== block) return;
            block.setAttribute("draggable", "true");
            block.setAttribute("data-dbx-cms-movable-block", "1");
        });
        qsa(surface, ".badge").forEach(badge => {
            const absoluteHost = closestElement(badge, ".position-absolute");
            if (badge.classList.contains("position-absolute") || absoluteHost) return;
            // Normale Content-Badges sind Textinhalt. Die explizite Runtime-
            // Freigabe macht sie auch innerhalb komplexer Bootstrap-Strukturen
            // direkt per Caret bearbeitbar; beim Serialisieren wird sie entfernt.
            badge.setAttribute("contenteditable", "true");
            badge.setAttribute("tabindex", "0");
            badge.setAttribute("data-dbx-cms-editable-badge", "1");
        });
        qsa(surface, ".card").forEach(card => {
            let body = Array.from(card.children || []).find(child => child.classList && child.classList.contains("card-body"));
            if (!body) {
                body = doc.createElement("div");
                body.className = "card-body";
                const insertAfter = Array.from(card.children || []).reverse().find(child => {
                    if (!child.classList) return false;
                    return child.classList.contains("card-img-top")
                        || child.classList.contains("card-img")
                        || child.classList.contains("card-header")
                        || child.classList.contains("badge")
                        || child.classList.contains("position-absolute");
                });
                card.insertBefore(body, insertAfter ? insertAfter.nextSibling : card.firstChild);
            }

            Array.from(card.childNodes || []).forEach(node => {
                if (node === body) return;
                if (node.nodeType === 3) {
                    if (String(node.nodeValue || "").trim() === "") return;
                    body.appendChild(node);
                    return;
                }
                if (node.nodeType !== 1) return;
                if (node.classList && (
                    node.classList.contains("card-body")
                    || node.classList.contains("card-img-top")
                    || node.classList.contains("card-img")
                    || node.classList.contains("card-header")
                    || node.classList.contains("card-footer")
                    || node.classList.contains("list-group")
                    || node.classList.contains("dbx-cms-inline-media")
                    || node.classList.contains("dbx-media")
                    || node.classList.contains("position-absolute")
                )) return;
                if (isEmptyEditorBlock(node)) {
                    node.remove();
                    return;
                }
                if (/^(H[1-6]|P|A|BUTTON|UL|OL|DIV|SPAN|SMALL|STRONG|EM)$/i.test(node.tagName || "")) {
                    body.appendChild(node);
                }
            });

            qsa(card, ".position-absolute.badge, .position-absolute .badge, .badge.position-absolute").forEach(badge => {
                if (!card.contains(badge)) return;
                badge.setAttribute("contenteditable", "false");
                Array.from(badge.children || []).forEach(child => {
                    if (/^(H[1-6]|P|DIV|A|BUTTON|UL|OL)$/i.test(child.tagName || "")) body.appendChild(child);
                });
            });

            qsa(card, "img.card-img-top,img.card-img,img.card-img-bottom").forEach(img => {
                img.setAttribute("contenteditable", "false");
                img.setAttribute("draggable", "false");
            });

            qsa(body, "p,div").forEach(block => {
                if (block === body) return;
                if (!isEmptyEditorBlock(block)) return;
                block.remove();
            });

            if (!nodeHasEditorContent(body)) {
                const p = doc.createElement("p");
                p.innerHTML = "<br>";
                body.appendChild(p);
            }
        });
        // Erst nach allen strukturellen Bootstrap-Korrekturen einfügen. Sonst
        // könnte z. B. die Karten-Normalisierung einen Bildanker in card-body
        // verschieben und die anklickbare Position vom Bild trennen.
        qsa(surface, "a.btn").forEach(button => createEditorCaretAnchors(doc, button, "button", "inline"));
        normalizeEditorElementCaretAnchors(surface, doc);
    }

        return {
            bootstrapComponentItems,
            cmsBootstrapComponentIcon,
            insertBootstrapComponent,
            openEditableBadgeTextInput,
            bindEditableBadgeEditing,
            createEditorCaretAnchor,
            createEditorCaretAnchors,
            editorCaretAnchorLayout,
            normalizeEditorElementCaretAnchors,
            normalizeFlexContentAlignment,
            normalizeBootstrapComponents
        };
    });
})(window, document);
