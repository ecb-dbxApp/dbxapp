(function () {

    if (!window.dbx) return;
    const dbx = window.dbx;

    function log(...a)   { dbx.log('[icons_editor]', '→', ...a); }
    function warn(...a)  { dbx.warn('[icons_editor]', '→', ...a); }

    const ICON_CLASS = 'dbx-editor-icon';
    const ICON_Z_INDEX_MIN = 2400;
    const ICON_Z_INDEX_OFFSET = 240;
    const ICON_STACK_STEP_X = 32;
    const ICON_STACK_STEP_Y = 28;
    const ICON_COLLISION_WIDTH = 32;
    const ICON_COLLISION_HEIGHT = 26;

    dbx.feature.register("icons_editor", {

        scope: "global", // 🔥 FIX
        priority: "last",

        // 🔥 NEU: CSS deklarativ (PREPARE übernimmt)
        css: [
            ['css', 'design', 'c-icons_editor.css']
        ],

        init(el, cfg) {

            log("init start", cfg);

            const root = document.body;
            const editorMenuFiles = window.__dbxEditorMenuFiles || new Map();
            window.__dbxEditorMenuFiles = editorMenuFiles;

            collectEditorFiles();
            renderEditorFilesMenu();
            watchEditorFilesData();

            // --------------------------------------------------
            // INIT GUARD (auf Core-Standard gebracht)
            // --------------------------------------------------

            el.__dbxInitialized = el.__dbxInitialized || {};
            if (el.__dbxInitialized["icons_editor"]) {
                log("already initialized on element, skip");
                return;
            }
            el.__dbxInitialized["icons_editor"] = true;

            // --------------------------------------------------
            // DEPENDENCY (kein dbx.load mehr!)
            // --------------------------------------------------

            log("resolve deps: openWin");

            dbx.resolveFeature("openWin", function () {

                log("deps ready");

                const items = [];

                // --------------------------------------------------
                // SCAN
                // --------------------------------------------------

                dbx.iconsEditor = dbx.iconsEditor || {};
                dbx.iconsEditor.rescan = function (scanRoot) {
                    runWhenRendered(() => scanEditorMarkers(scanRoot));
                };
                this.rescan = dbx.iconsEditor.rescan;

                runWhenRendered(() => scanEditorMarkers(root));

                function runWhenRendered(callback) {
                    const run = () => {
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                setTimeout(callback, 0);
                            });
                        });
                    };

                    if (document.readyState === 'complete') {
                        run();
                        return;
                    }

                    window.addEventListener('load', run, { once: true });
                }

                function scanEditorMarkers(scanRoot) {
                    const scope = scanRoot || document.body;

                    collectEditorFiles();
                    renderEditorFilesMenu();

                    log("scanning DOM (scope)", scope);

                    const comments = [];

                    const walker = document.createTreeWalker(
                        scope,
                        NodeFilter.SHOW_COMMENT,
                        null,
                        false
                    );

                    let node;

                    while ((node = walker.nextNode())) {
                        if (node.__dbxEditorIconCreated) {
                            continue;
                        }

                        const txt = node.nodeValue.trim();
                        const marker = parseMarker(txt);

                        if (marker) {
                            comments.push({ node, marker });
                        }
                    }

                    log("found comments:", comments.length);

                    // --------------------------------------------------
                    // CREATE ICONS
                    // --------------------------------------------------

                    const seenTplPaths = new Set();
                    const seenTplTableCols = new Set();

                    comments.forEach(item => {

                        const node = item.node;
                        const meta = item.marker;
                        const path = meta.path || '';

                        // Nur Template-HTML-Editor (🖍️ HTML bearbeiten).
                        if (meta.kind !== 'tpl') {
                            node.__dbxEditorIconCreated = true;
                            return;
                        }

                        const inTableCell = isTableCellMarker(node);

                        if (path && inTableCell) {
                            const cell = node.parentElement;
                            const colKey = path + '|' + (cell ? cell.cellIndex : 0);

                            // Pro Tabellenspalte ein Stift (erste Zeile im DOM).
                            if (seenTplTableCols.has(colKey)) {
                                node.__dbxEditorIconCreated = true;
                                return;
                            }
                            seenTplTableCols.add(colKey);
                        } else if (path) {
                            if (seenTplPaths.has(path)) {
                                node.__dbxEditorIconCreated = true;
                                return;
                            }
                            seenTplPaths.add(path);
                        }

                        const target = normalizeTarget(findTargetElement(node));

                        if (!target) {
                            warn("no target found for", path);
                            return;
                        }

                        createIcon(target, meta);
                        node.__dbxEditorIconCreated = true;
                    });
                }

                // --------------------------------------------------
                // EVENTS (GLOBAL)
                // --------------------------------------------------

                if (!window.__dbxEditorEvents) {

                    log("attach global events");

                    window.addEventListener('scroll', updateAll);
                    window.addEventListener('resize', updateAll);
                    window.addEventListener('load', updateAll);

                    window.__dbxEditorEvents = true;
                }

                log("initialized, total icons:", items.length);

                // ==================================================
                // FUNCTIONS
                // ==================================================

                function createIcon(target, meta) {

                    const url = getEditorFileUrl(meta);

                    const icon = document.createElement('a');

                    icon.href = url;
                    const title = getTemplateOverlayTitle(meta.path || '');

                    icon.innerHTML = '<span data-dbx-tooltip="' + escapeHtml(title) + '">🖍️</span>';
                    icon.className = ICON_CLASS;
                    icon.dataset.dbxTooltip = title;

                    icon.setAttribute(
                        'data-dbx',
                        'lib=openWin' +
                        '|url=' + url +
                        '|title=' + encodeDataValue(title) +
                        '|reload=1' +
                        '|width=80%' +
                        '|height=80%' +
                        '|left=center' +
                        '|top=center' +
                        '|prio=last'
                    );

                    // EVENTS
                    icon.addEventListener('mouseenter', () => highlightTarget(target, true));
                    icon.addEventListener('mouseleave', () => highlightTarget(target, false));

                    icon.style.cursor = 'pointer';
                    icon.style.textDecoration = 'none';

                    if (isTableCellTarget(target)) {
                        attachTableCellIcon(icon, target);
                        dbx.rescan(icon);
                        items.push({ icon, target, meta });
                        watchTargetLayout(icon, target, getTemplateNodes(target)[0]);
                        return icon;
                    }

                    // DOM INSERT (zuerst!)
                    const anchorNode = getTargetAnchorNode(target);
                    const el = anchorNode || document.body;
                    const container = el.closest('.dbx-window, .dbx-openwin, .dbx-modal, #dbxHeader') || document.body;

                    // STYLE
                    icon.style.position = 'absolute';
                    icon.style.zIndex = getEditorIconZIndex(container);

                    // DOM INSERT
                    container.appendChild(icon);

                    dbx.rescan(icon);

                    // 🔥🔥 FINAL FIX: echten Scroll-Container sauber bestimmen
                    let scrollEl = el;
                    while (scrollEl && scrollEl !== document.body) {
                        const style = window.getComputedStyle(scrollEl);

                        if (
                            style.overflowY === 'auto' ||
                            style.overflowY === 'scroll'
                        ) {
                            break;
                        }

                        scrollEl = scrollEl.parentElement;
                    }

                    // 👉 FALL 1: app-layout (container scroll)
                    if (scrollEl && scrollEl !== document.body) {

                        if (!scrollEl.__dbxScrollBound) {
                            scrollEl.addEventListener('scroll', updateAll);
                            scrollEl.__dbxScrollBound = true;
                        }

                    } else {

                        // 👉 FALL 2: web-layout (window scroll)
                        if (!window.__dbxEditorEvents) {
                            window.addEventListener('scroll', updateAll);
                            window.addEventListener('resize', updateAll);
                            window.__dbxEditorEvents = true;
                        }
                    }

                    items.push({ icon, target, meta });

                    watchTargetLayout(icon, target, container);
                    schedulePositionUpdates(icon, target);

                    return icon;
                }

                function getTemplateNodes(target) {

                    if (!target) return [];

                    if (Array.isArray(target.nodes) && target.nodes.length) {
                        return target.nodes;
                    }

                    if (target.type === 'element' && target.node) {
                        return [target.node];
                    }

                    if (target.type === 'text' && target.node && target.node.parentElement) {
                        return [target.node.parentElement];
                    }

                    return [];
                }

                function highlightTarget(target, state) {

                    getTemplateNodes(target).forEach(node => highlight(node, state));
                }

                function updateAll() {
                    items.forEach(({ icon, target }) => {
                        positionIcon(icon, target);
                    });
                }

                function schedulePositionUpdates(icon, target) {
                    requestAnimationFrame(() => positionIcon(icon, target));
                    [50, 150, 300, 700, 1200, 2000].forEach(delay => {
                        setTimeout(() => positionIcon(icon, target), delay);
                    });
                }

                function watchTargetLayout(icon, target, container) {

                    if (!window.ResizeObserver) return;

                    const observer = new ResizeObserver(() => {
                        requestAnimationFrame(() => positionIcon(icon, target));
                    });
                    getTemplateNodes(target).forEach(anchor => {
                        if (anchor && anchor.nodeType === 1) {
                            observer.observe(anchor);
                        }
                    });

                    if (container && container.nodeType === 1) {
                        observer.observe(container);
                    }

                    icon.__dbxResizeObserver = observer;
                }

                function positionIcon(icon, target) {

                    if (icon.dataset.dbxEditorInCell === '1') {
                        return;
                    }

                    let rect = getTargetRect(target);

                    if (target.originalNode) {
                        const originalRect = getTargetRect({
                            type: 'element',
                            node: target.originalNode
                        });

                        if (isUsableEditorRect(originalRect) && !isNonPositionableElement(target.originalNode)) {
                            target.type = 'element';
                            target.node = target.originalNode;
                            rect = originalRect;
                        } else if (!isUsableEditorRect(rect)) {
                            const anchor = isHiddenInput(target.originalNode)
                                ? findHiddenFieldAnchor(target.originalNode)
                                : findVisibleEditorAnchor(target.originalNode);

                            if (anchor) {
                                target.type = 'element';
                                target.node = anchor;
                                rect = getTargetRect(target);
                            }
                        }
                    }

                    if (!isUsableEditorRect(rect)) return;

                    const container = icon.parentElement;
                    icon.style.zIndex = getEditorIconZIndex(container);

                    const slot = getIconStackSlot(icon, target, container, rect);
                    const row = Math.floor(slot / 8);
                    const col = slot % 8;
                    const offset = row * ICON_STACK_STEP_Y;
                    const xOffset = col * ICON_STACK_STEP_X;
                    icon.dataset.dbxEditorStackSlot = String(slot);

                    // 🔥 FIX: Header NICHT bewegen
                    if (container.closest('#dbxHeader')) {

                        // einmal initial setzen reicht (kein scroll-follow)
                        icon.style.top  = (rect.top  + offset) + 'px';
                        icon.style.left = (rect.left + xOffset) + 'px';
                        return;
                    }

                    // BODY (web-layout)
                    if (container === document.body) {

                        icon.style.top  = (rect.top  + window.scrollY + offset) + 'px';
                        icon.style.left = (rect.left + window.scrollX + xOffset) + 'px';
                        return;
                    }

                    // CONTAINER (app-layout)
                    const crect = container.getBoundingClientRect();

                    icon.style.top  = (rect.top  - crect.top  + container.scrollTop  + offset) + 'px';
                    icon.style.left = (rect.left - crect.left + container.scrollLeft + xOffset) + 'px';
                }

                function getIconStackSlot(icon, target, container, rect) {

                    const anchor = getTargetAnchorNode(target);
                    if (!anchor) return 0;

                    const sameTargetItems = items.filter(item => {
                        return item.icon &&
                            item.icon.parentElement === container &&
                            (
                                getTargetAnchorNode(item.target) === anchor ||
                                targetsMayOverlap(item.target, rect)
                            );
                    });

                    const idx = sameTargetItems.findIndex(item => item.icon === icon);
                    return idx >= 0 ? idx : 0;
                }

                function targetsMayOverlap(target, rect) {

                    const otherRect = getTargetRect(target);
                    if (!otherRect) return false;

                    return Math.abs(otherRect.left - rect.left) < ICON_COLLISION_WIDTH &&
                        Math.abs(otherRect.top - rect.top) < ICON_COLLISION_HEIGHT;
                }

                function getUnionRect(nodes) {

                    let top = Infinity;
                    let left = Infinity;
                    let bottom = -Infinity;
                    let right = -Infinity;
                    let found = false;

                    (nodes || []).forEach(node => {
                        if (!node || !node.getBoundingClientRect) return;

                        const rect = node.getBoundingClientRect();
                        if (rect.width <= 0 && rect.height <= 0) return;

                        found = true;
                        top = Math.min(top, rect.top);
                        left = Math.min(left, rect.left);
                        bottom = Math.max(bottom, rect.bottom);
                        right = Math.max(right, rect.right);
                    });

                    if (!found) return null;

                    return {
                        top: top,
                        left: left,
                        bottom: bottom,
                        right: right,
                        width: right - left,
                        height: bottom - top
                    };
                }

                function getTargetRect(target) {

                    if (!target) return null;

                    const templateNodes = getTemplateNodes(target);
                    if (templateNodes.length) {
                        const union = getUnionRect(templateNodes);
                        if (union) return union;
                    }

                    if (!target.node) return null;

                    if (target.type === 'text') {
                        const range = document.createRange();
                        range.setStart(target.node, 0);
                        range.setEnd(target.node, 1);
                        return range.getBoundingClientRect();
                    }

                    if (target.node.nodeType === 1 && isHiddenInput(target.node)) {
                        const anchor = findHiddenFieldAnchor(target.node);

                        if (anchor && anchor.getBoundingClientRect) {
                            return anchor.getBoundingClientRect();
                        }
                    }

                    if (!target.node.getBoundingClientRect) return null;
                    return target.node.getBoundingClientRect();
                }

                function isUsableEditorRect(rect) {
                    return !!rect &&
                        Number.isFinite(rect.top) &&
                        Number.isFinite(rect.left) &&
                        (rect.width > 0 || rect.height > 0 || rect.top > 0 || rect.left > 0);
                }

                function getTargetAnchorNode(target) {

                    const templateNodes = getTemplateNodes(target);
                    if (templateNodes.length) {
                        return templateNodes[0];
                    }

                    if (!target || !target.node) return null;
                    return target.node.nodeType === 3
                        ? (target.node.parentElement || target.node)
                        : target.node;
                }

                function isTableCellMarker(commentNode) {

                    const parent = commentNode && commentNode.parentElement;
                    return !!(parent && parent.matches && parent.matches('td, th'));
                }

                function isTableCellTarget(target) {

                    const nodes = getTemplateNodes(target);
                    return nodes.length === 1 && nodes[0].matches && nodes[0].matches('td, th');
                }

                function attachTableCellIcon(icon, target) {

                    const cell = getTemplateNodes(target)[0];
                    if (!cell) return;

                    const computed = window.getComputedStyle(cell);
                    if (computed.position === 'static') {
                        cell.dataset.dbxEditorHadPosition = '1';
                        cell.style.position = 'relative';
                    }

                    icon.className = ICON_CLASS + ' ' + ICON_CLASS + '--cell';
                    icon.style.position = 'absolute';
                    icon.style.top = '2px';
                    icon.style.left = '2px';
                    icon.style.right = 'auto';
                    icon.style.bottom = 'auto';
                    icon.style.zIndex = '6';
                    icon.dataset.dbxEditorInCell = '1';
                    cell.appendChild(icon);
                }

                function highlight(el, state) {

                    if (!el) return;
                    el.classList.toggle('dbx-editor-highlight', !!state);
                }

                function getZIndex(el) {
                    while (el && el !== document.body) {
                        const z = window.getComputedStyle(el).zIndex;
                        if (z !== 'auto') return parseInt(z, 10) || 0;
                        el = el.parentElement;
                    }
                    return 0;
                }

                function getEditorIconZIndex(container) {
                    const zIndex = getZIndex(container) + ICON_Z_INDEX_OFFSET;
                    return Math.max(ICON_Z_INDEX_MIN, zIndex);
                }


                function findTplEndComment(startComment) {

                    let depth = 1;
                    let node = startComment.nextSibling;

                    while (node) {
                        if (node.nodeType === 8) {
                            const txt = (node.nodeValue || '').trim();

                            if (txt.startsWith('DBX-TPL-START|')) {
                                depth++;
                            } else if (txt === 'DBX-TPL-END') {
                                depth--;
                                if (depth === 0) {
                                    return node;
                                }
                            }
                        }

                        node = node.nextSibling;
                    }

                    return null;
                }

                function collectTplElements(startComment) {

                    const endComment = findTplEndComment(startComment);
                    const nodes = [];

                    if (!endComment) {
                        return nodes;
                    }

                    let node = startComment.nextSibling;

                    while (node && node !== endComment) {
                        if (node.nodeType === 1) {
                            nodes.push(node);
                        }
                        node = node.nextSibling;
                    }

                    return nodes;
                }

                function findTemplateTarget(startComment) {

                    const parent = startComment.parentElement;

                    if (parent && parent.matches && parent.matches('td, th')) {
                        return {
                            type: 'template',
                            nodes: [parent],
                            node: parent
                        };
                    }

                    const nodes = collectTplElements(startComment);

                    if (nodes.length === 1 && nodes[0].matches && nodes[0].matches('td, th')) {
                        return {
                            type: 'template',
                            nodes: nodes,
                            node: nodes[0]
                        };
                    }

                    if (nodes.length) {
                        return {
                            type: 'template',
                            nodes: nodes,
                            node: nodes[0]
                        };
                    }

                    let node = startComment.nextSibling;

                    while (node) {
                        if (node.nodeType === 8 && (node.nodeValue || '').trim() === 'DBX-TPL-END') {
                            break;
                        }

                        if (node.nodeType === 1) {
                            return {
                                type: 'template',
                                nodes: [node],
                                node: node
                            };
                        }

                        if (node.nodeType === 3 && node.textContent.trim() !== '') {
                            return { type: 'text', node: node, nodes: [] };
                        }

                        node = node.nextSibling;
                    }

                    if (startComment.parentElement) {
                        return {
                            type: 'template',
                            nodes: [startComment.parentElement],
                            node: startComment.parentElement
                        };
                    }

                    return null;
                }

                function findTargetElement(startNode) {

                    if (startNode.nodeType === 8) {
                        const txt = (startNode.nodeValue || '').trim();

                        if (txt.startsWith('DBX-TPL-START|')) {
                            return findTemplateTarget(startNode);
                        }
                    }

                    let node = startNode.nextSibling;

                    while (node) {

                        // TEXT mit Inhalt → direkt verwenden!
                        if (node.nodeType === 3 && node.textContent.trim() !== '') {
                            return { type: 'text', node: node, nodes: [] };
                        }

                        // ELEMENT
                        if (node.nodeType === 1) {
                            return { type: 'element', node: node, nodes: [node] };
                        }

                        node = node.nextSibling;
                    }

                    if (startNode.parentElement) {
                        return {
                            type: 'element',
                            node: startNode.parentElement,
                            nodes: [startNode.parentElement]
                        };
                    }

                    return null;
                }

                function normalizeTarget(target) {

                    if (!target) return null;

                    if (target.type === 'template') {
                        return target;
                    }

                    if (!target.node) return null;

                    if (target.type === 'element' && isHiddenInput(target.node)) {
                        const anchor = findHiddenFieldAnchor(target.node);

                        if (anchor) {
                            return {
                                type: 'element',
                                node: anchor,
                                originalNode: target.node
                            };
                        }
                    }

                    if (target.type === 'element' && isNonPositionableElement(target.node)) {
                        const anchor = findVisibleEditorAnchor(target.node);

                        if (anchor) {
                            return {
                                type: 'element',
                                node: anchor,
                                originalNode: target.node
                            };
                        }
                    }

                    return target;
                }

                function isHiddenInput(node) {

                    if (!node || node.nodeType !== 1) return false;

                    const tagName = node.tagName.toLowerCase();
                    const type = (node.getAttribute('type') || '').toLowerCase();

                    return tagName === 'input' && type === 'hidden';
                }

                function findHiddenFieldAnchor(hiddenInput) {

                    const id = hiddenInput.getAttribute('id');

                    if (id) {
                        const label = document.querySelector('label[for="' + CSS.escape(id) + '"]');

                        if (label && isVisibleEditorAnchor(label)) {
                            return label;
                        }
                    }

                    let prev = hiddenInput.previousElementSibling;

                    while (prev) {
                        if (isVisibleEditorAnchor(prev)) {
                            return prev;
                        }

                        prev = prev.previousElementSibling;
                    }

                    let parent = hiddenInput.parentElement;

                    while (parent && parent !== document.body) {
                        if (parent.matches('form, .dbxForm_wrapper')) {
                            parent = parent.parentElement;
                            continue;
                        }

                        if (isVisibleEditorAnchor(parent)) {
                            return parent;
                        }

                        parent = parent.parentElement;
                    }

                    return null;
                }

                function isNonPositionableElement(node) {

                    if (!node || node.nodeType !== 1) return false;

                    if (isHiddenInput(node)) return true;

                    const tagName = node.tagName.toLowerCase();
                    const type = (node.getAttribute('type') || '').toLowerCase();

                    if (tagName === 'input' && type === 'hidden') return true;
                    if (node.hidden) return true;

                    const rect = node.getBoundingClientRect ? node.getBoundingClientRect() : null;
                    if (rect && rect.width === 0 && rect.height === 0) return true;

                    const style = window.getComputedStyle(node);
                    return style.display === 'none' || style.visibility === 'hidden';
                }

                function findVisibleEditorAnchor(node) {

                    const candidates = [
                        node.closest('.dbx-window-body'),
                        node.closest('#dbxContent'),
                        node.parentElement
                    ];

                    for (const candidate of candidates) {
                        if (isVisibleEditorAnchor(candidate)) {
                            return candidate;
                        }
                    }

                    return document.body;
                }

                function isVisibleEditorAnchor(node) {

                    if (!node || node.nodeType !== 1 || !node.getBoundingClientRect) return false;

                    const rect = node.getBoundingClientRect();
                    if (rect.width <= 0 || rect.height <= 0) return false;

                    const style = window.getComputedStyle(node);
                    return style.display !== 'none' && style.visibility !== 'hidden';
                }

                function parseMarker(txt) {

                    if (txt.startsWith('DBX-TPL-START|')) {
                        return {
                            kind: 'tpl',
                            path: txt.split('|')[1] || ''
                        };
                    }

                    if (txt.startsWith('DBX-EDITOR|')) {
                        const parts = txt.split('|');
                        return {
                            kind: (parts[1] || 'file').toLowerCase(),
                            path: parts.slice(2).join('|') || ''
                        };
                    }

                    return null;
                }

            });

            // ==================================================
            // FILE MENU
            // ==================================================

            function collectEditorFiles() {

                let hasAllResourcesMode = getDbxEditMode() === 9;

                document.querySelectorAll('.dbx-editor-files-data').forEach(node => {
                    if (node.__dbxEditorFilesRead) return;
                    node.__dbxEditorFilesRead = true;

                    let payload = null;

                    try {
                        payload = JSON.parse(node.textContent || '{}');
                    } catch (e) {
                        warn('invalid editor files payload', e);
                        return;
                    }

                    if (parseInt(payload.mode, 10) === 9) {
                        hasAllResourcesMode = true;
                    }

                    const files = Array.isArray(payload.files) ? payload.files : [];

                    files.forEach(file => {
                        if (!file || !file.kind || !file.file) return;
                        const key = file.kind + '|' + file.file;
                        editorMenuFiles.set(key, {
                            kind: file.kind,
                            path: file.file
                        });
                    });
                });

                if (hasAllResourcesMode) {
                    collectLoadedCssFiles();
                    collectLoadedJsFiles();
                }
            }

            function collectLoadedCssFiles() {

                const hrefs = new Set();

                Array.from(document.styleSheets || []).forEach(sheet => {
                    if (sheet && sheet.href) {
                        hrefs.add(sheet.href);
                    }
                });

                document.querySelectorAll('link[rel~="stylesheet"][href]').forEach(link => {
                    hrefs.add(link.href);
                });

                hrefs.forEach(href => {
                    const path = stylesheetHrefToEditorPath(href);
                    if (!path) return;

                    editorMenuFiles.set('css|' + path, {
                        kind: 'css',
                        path
                    });
                });
            }

            function stylesheetHrefToEditorPath(href) {

                if (!href || href.indexOf('data:') === 0 || href.indexOf('blob:') === 0) {
                    return '';
                }

                let url;

                try {
                    url = new URL(href, window.location.href);
                } catch (e) {
                    return '';
                }

                if (url.origin !== window.location.origin) {
                    return '';
                }

                let path = url.pathname.replace(/\\/g, '/').replace(/^\/+/, '');
                const appRoot = window.location.pathname.replace(/\\/g, '/').split('/').filter(Boolean)[0] || '';

                if (appRoot && path.indexOf(appRoot + '/') === 0) {
                    path = path.substring(appRoot.length + 1);
                }

                if (!/\.css$/i.test(path)) {
                    return '';
                }

                return path;
            }

            function collectLoadedJsFiles() {

                document.querySelectorAll('script[src]').forEach(script => {
                    const path = assetHrefToEditorPath(script.src, 'js');
                    if (!path) return;

                    editorMenuFiles.set('js|' + path, {
                        kind: 'js',
                        path
                    });
                });
            }

            function assetHrefToEditorPath(href, ext) {

                if (!href || href.indexOf('data:') === 0 || href.indexOf('blob:') === 0) {
                    return '';
                }

                let url;

                try {
                    url = new URL(href, window.location.href);
                } catch (e) {
                    return '';
                }

                if (url.origin !== window.location.origin) {
                    return '';
                }

                let path = url.pathname.replace(/\\/g, '/').replace(/^\/+/, '');
                const appRoot = window.location.pathname.replace(/\\/g, '/').split('/').filter(Boolean)[0] || '';

                if (appRoot && path.indexOf(appRoot + '/') === 0) {
                    path = path.substring(appRoot.length + 1);
                }

                if (!new RegExp('\\.' + ext + '$', 'i').test(path)) {
                    return '';
                }

                return path;
            }

            function getDbxEditMode() {

                const value = new URLSearchParams(window.location.search).get('dbx_edit');
                return parseInt(value || '0', 10) || 0;
            }

            function getEditorFileUrl(file) {

                const kind = String((file && file.kind) || '').toLowerCase();
                const path = String((file && file.path) || '').replace(/\\/g, '/');
                const requestPath = window.location.origin + (window.location.pathname || '/');

                if (kind === 'dd') {
                    const match = path.match(/^dbx\/modules\/([^/]+)\/dd\/([^/]+)\.dd\.php$/);
                    if (match) {
                        return requestPath + '?dbx_modul=dbxAdmin&dbx_run1=edit_dd&modul=' +
                            encodeURIComponent(match[1]) +
                            '&dd=' + encodeURIComponent(match[2]);
                    }
                }

                if (kind === 'fd') {
                    const match = path.match(/^dbx\/modules\/([^/]+)\/fd\/([^/]+)\.fd\.php$/);
                    if (match) {
                        return requestPath + '?dbx_modul=dbxAdmin&dbx_run1=edit_fd&modul=' +
                            encodeURIComponent(match[1]) +
                            '&fd=' + encodeURIComponent(match[2]);
                    }
                }

                return requestPath + '?dbx_modul=dbxEditor&dbx_run1=edit&file=' + encodeURIComponent(path);
            }

            function watchEditorFilesData() {

                if (window.__dbxEditorFilesObserver) return;

                const observer = new MutationObserver(mutations => {
                    if (window.__dbxEditorFilesRendering) return;

                    const hasExternalEditorDataChange = mutations.some(mutation => {
                        const target = mutation.target;

                        if (target && target.closest && target.closest('#dbxEditorFilesMenu')) {
                            return false;
                        }

                        return Array.from(mutation.addedNodes || []).some(node => {
                            if (!node) return false;
                            if (node.id === 'dbxEditorFilesMenu') return false;
                            if (node.closest && node.closest('#dbxEditorFilesMenu')) return false;
                            if (node.matches && node.matches('.dbx-editor-files-data')) return true;
                            if (node.querySelector && node.querySelector('.dbx-editor-files-data')) return true;
                            return false;
                        });
                    });

                    if (!hasExternalEditorDataChange) return;

                    collectEditorFiles();
                    renderEditorFilesMenu();
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });

                window.__dbxEditorFilesObserver = observer;
            }

            function renderEditorFilesMenu() {

                let menu = document.getElementById('dbxEditorFilesMenu');

                if (!editorMenuFiles.size) {
                    if (menu) {
                        menu.remove();
                        syncEditorFilesMenuLayer(null);
                    }
                    return;
                }

                if (menu && menu.tagName.toLowerCase() !== 'li') {
                    menu.remove();
                    menu = null;
                }

                if (!menu) {
                    menu = document.createElement('li');
                    menu.id = 'dbxEditorFilesMenu';
                    menu.className = 'align-right dbx-menu-right dbx-editor-files-menu dbx-menu-item has-children';
                }
                if (!attachEditorMenu(menu)) {
                    return;
                }

                const files = sortEditorFiles(Array.from(editorMenuFiles.values()));
                const signature = JSON.stringify(files);

                if (menu.dataset.dbxEditorFilesSignature === signature) {
                    return;
                }

                const grouped = {};

                files.forEach(file => {
                    const kind = file.kind || 'file';
                    grouped[kind] = grouped[kind] || [];
                    grouped[kind].push(file);
                });

                let html = '<a class="dbx-menu-link dbx-editor-files-toggle" data-role="toggle" aria-haspopup="true" aria-expanded="false" data-dbx-tooltip="Verwendete Editor-Dateien">';
                html += '<i class="bi bi-files"></i><span>' + files.length + '</span><span class="dbx-caret"></span>';
                html += '</a>';
                html += '<ul class="dbx-menu-list dbx-editor-files-list">';

                getEditorKindOrder(grouped).forEach(kind => {
                    html += '<li class="dbx-menu-item dbx-editor-files-title">' + escapeHtml(getMarkerTitle({ kind })) + '</li>';

                    grouped[kind].forEach(file => {
                        const url = getEditorFileUrl(file);
                        html += '<li class="dbx-menu-item">';
                        html += '<a class="dbx-menu-link" href="' + url + '" data-dbx="lib=openWin|url=' + url + '|title=' + encodeDataValue(getMarkerTitle(file)) + '|reload=1|width=80%|height=80%|left=center|top=center|prio=last|minimizable=1|maximizable=1">';
                        html += '<i class="bi ' + getMarkerIcon(file) + '"></i>';
                        html += '<span>' + escapeHtml(getShortPath(file.path)) + '</span>';
                        html += '</a>';
                        html += '</li>';
                    });
                });

                html += '</ul>';
                window.__dbxEditorFilesRendering = true;

                try {
                    menu.dataset.dbxEditorFilesSignature = signature;
                    menu.innerHTML = html;

                    bindEditorMenu(menu);

                    dbx.rescan(menu);
                } finally {
                    window.__dbxEditorFilesRendering = false;
                }
            }

            function attachEditorMenu(menu) {

                const slot = getEditorMenuSlot();

                if (slot) {
                    const parent = slot.parentElement;
                    const spacer = parent && parent.querySelector(':scope > .dbx-menu-spacer');

                    if (menu.parentElement !== parent) {
                        slot.insertAdjacentElement('afterend', menu);
                    } else if (menu.previousElementSibling !== slot) {
                        slot.insertAdjacentElement('afterend', menu);
                    }

                    if (spacer && (spacer.compareDocumentPosition(menu) & Node.DOCUMENT_POSITION_PRECEDING)) {
                        slot.insertAdjacentElement('afterend', menu);
                    }

                    menu.classList.add('dbx-menu-right');
                    menu.style.display = '';
                    return true;
                }

                if (menu.parentElement) {
                    menu.remove();
                }

                return false;
            }

            function getEditorMenuSlot() {

                const slots = Array.from(document.querySelectorAll('#dbx_admin_menu .dbx-edit-menu-slot, .dbx-menu-admin .dbx-edit-menu-slot, .dbx-edit-menu-slot'));

                if (!slots.length) return null;

                return slots.find(slot => {
                    const rect = slot.getBoundingClientRect();
                    const style = window.getComputedStyle(slot);
                    return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
                }) || slots[0];
            }

            function bindEditorMenu(menu) {

                const toggle = menu.querySelector('.dbx-editor-files-toggle');

                if (toggle) {
                    toggle.setAttribute('data-role', 'toggle');
                    toggle.setAttribute('aria-haspopup', 'true');
                    toggle.setAttribute('aria-expanded', menu.classList.contains('is-open') ? 'true' : 'false');
                }

                if (!menu.__dbxEditorFilesLayerObserver && window.MutationObserver) {
                    menu.__dbxEditorFilesLayerObserver = new MutationObserver(() => syncEditorFilesMenuLayer(menu));
                    menu.__dbxEditorFilesLayerObserver.observe(menu, {
                        attributes: true,
                        attributeFilter: ['class']
                    });
                }

                syncEditorFilesMenuLayer(menu);

                if (!window.__dbxEditorMenuCloseBound) {
                    document.addEventListener('click', event => {
                        const openMenu = document.querySelector('.dbx-editor-files-menu.is-open');
                        if (!openMenu) return;
                        if (openMenu.contains(event.target)) return;
                        openMenu.classList.remove('is-open');
                        syncEditorFilesMenuLayer(openMenu);
                    });

                    window.__dbxEditorMenuCloseBound = true;
                }

                menu.onclick = event => {
                    const link = event.target.closest('a');
                    if (!link) return;
                    if (link.classList.contains('dbx-editor-files-toggle')) return;
                    menu.classList.remove('is-open');
                    syncEditorFilesMenuLayer(menu);
                };
            }

            function syncEditorFilesMenuLayer(menu) {

                const open = !!(menu && menu.classList.contains('is-open'));
                const body = document.body;
                const header = document.getElementById('dbxHeader');

                if (body) {
                    body.classList.toggle('dbx-editor-files-menu-open', open);
                }

                if (header) {
                    header.classList.toggle('dbx-editor-files-menu-open', open);
                }

                if (menu) {
                    const toggle = menu.querySelector('.dbx-editor-files-toggle');
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    }
                }
            }

            function sortEditorFiles(files) {

                const order = {
                    fd: 10,
                    dd: 20,
                    class: 30,
                    sysclass: 40,
                    config: 45,
                    css: 48,
                    js: 49,
                    tpl: 50,
                    file: 60,
                    design: 100
                };

                return files.sort((a, b) => {
                    const kindA = (a.kind || 'file').toLowerCase();
                    const kindB = (b.kind || 'file').toLowerCase();
                    const rankA = order[kindA] || 99;
                    const rankB = order[kindB] || 99;

                    if (rankA !== rankB) return rankA - rankB;

                    return getShortPath(a.path || '').localeCompare(getShortPath(b.path || ''), undefined, {
                        sensitivity: 'base'
                    });
                });
            }

            function getEditorKindOrder(grouped) {

                const preferred = ['fd', 'dd', 'class', 'sysclass', 'config', 'css', 'js', 'tpl', 'file', 'design'];
                const existing = Object.keys(grouped);
                const ordered = preferred.filter(kind => grouped[kind]);

                existing.forEach(kind => {
                    if (!ordered.includes(kind)) {
                        ordered.push(kind);
                    }
                });

                return ordered;
            }

            function getShortPath(path) {

                const parts = String(path || '').split('/');
                const normalized = parts.join('/');
                const prefix = 'dbx/modules/';

                if (normalized.indexOf(prefix) === 0) {
                    return normalized.substring(prefix.length);
                }

                if (normalized.indexOf('dbx/design/') === 0) {
                    return normalized.substring('dbx/'.length);
                }

                return normalized;
            }

            function escapeHtml(value) {

                const div = document.createElement('div');
                div.textContent = String(value || '');
                return div.innerHTML;
            }

            function encodeDataValue(value) {

                return encodeURIComponent(String(value || ''));
            }

            function getMarkerIcon(meta) {

                return getFileIcon(meta.path || '');
            }

            function getMarkerTitle(meta) {

                const kind = (meta.kind || 'tpl').toLowerCase();

                if (kind === 'fd') return 'FD Definition';
                if (kind === 'dd') return 'DD Definition';
                if (kind === 'class') return 'Modul Class';
                if (kind === 'sysclass') return 'myX System Class';
                if (kind === 'config') return meta.path ? getConfigTitle(meta.path) : 'Config';
                if (kind === 'css') return meta.path ? getCssTitle(meta.path) : 'CSS';
                if (kind === 'js') return meta.path ? getJsTitle(meta.path) : 'JS';
                if (kind === 'tpl') return meta.path ? getTemplateTitle(meta.path) : 'Template';
                if (kind === 'design') return meta.path ? getDesignTitle(meta.path) : 'Design Page';

                return meta.path ? getGenericFileTitle(meta.path) : 'Datei';
            }

            function getTemplateOverlayTitle(path) {

                const normalized = String(path || '').replace(/\\/g, '/');
                const parts = normalized.split('/').filter(Boolean);
                const tplIndex = parts.lastIndexOf('tpl');
                const modulesIndex = parts.lastIndexOf('modules');

                const modul = modulesIndex !== -1 && parts[modulesIndex + 1]
                    ? parts[modulesIndex + 1]
                    : (tplIndex > 0 ? parts[tplIndex - 1] : 'dbx');

                let templateName = 'Template';

                if (tplIndex !== -1 && parts[tplIndex + 2]) {
                    templateName = splitFileName(parts[tplIndex + 2]).name || parts[tplIndex + 2];
                } else if (parts.length) {
                    templateName = splitFileName(parts[parts.length - 1]).name || parts[parts.length - 1];
                }

                return modul + '|' + templateName;
            }

            function getTemplateTitle(path) {

                const normalized = String(path || '').replace(/\\/g, '/');
                const parts = normalized.split('/').filter(Boolean);
                const tplIndex = parts.lastIndexOf('tpl');
                const modulesIndex = parts.lastIndexOf('modules');

                if (tplIndex !== -1 && parts[tplIndex + 1] && parts[tplIndex + 2]) {
                    const modul = modulesIndex !== -1 && parts[modulesIndex + 1]
                        ? parts[modulesIndex + 1]
                        : (parts[tplIndex - 1] || 'dbx');
                    const type = parts[tplIndex + 1];
                    const base = parts[tplIndex + 2];
                    const parsed = splitFileName(base);
                    return [modul, parsed.name, (parsed.ext || type)].filter(Boolean).join(' - ');
                }

                const base = parts.length ? parts[parts.length - 1] : normalized;
                const parsed = splitFileName(base);
                return [parsed.name || 'Template', parsed.ext].filter(Boolean).join(' - ');
            }

            function getDesignTitle(path) {

                const normalized = String(path || '').replace(/\\/g, '/');
                const parts = normalized.split('/').filter(Boolean);
                const designIndex = parts.lastIndexOf('design');
                const design = designIndex !== -1 && parts[designIndex + 1]
                    ? parts[designIndex + 1]
                    : 'Design';
                const type = designIndex !== -1 && parts[designIndex + 2]
                    ? parts[designIndex + 2]
                    : '';
                const base = parts.length ? parts[parts.length - 1] : normalized;
                const parsed = splitFileName(base);

                return ['Design Page', design, parsed.name, (parsed.ext || type)].filter(Boolean).join(' - ');
            }

            function getCssTitle(path) {

                const normalized = String(path || '').replace(/\\/g, '/');
                const parts = normalized.split('/').filter(Boolean);
                const base = parts.length ? parts[parts.length - 1] : normalized;
                const parsed = splitFileName(base);

                return ['CSS', parsed.name, parsed.ext].filter(Boolean).join(' - ');
            }

            function getJsTitle(path) {

                const normalized = String(path || '').replace(/\\/g, '/');
                const parts = normalized.split('/').filter(Boolean);
                const base = parts.length ? parts[parts.length - 1] : normalized;
                const parsed = splitFileName(base);

                return ['JS', parsed.name, parsed.ext].filter(Boolean).join(' - ');
            }

            function getConfigTitle(path) {

                const normalized = String(path || '').replace(/\\/g, '/');
                const parts = normalized.split('/').filter(Boolean);
                const modulesIndex = parts.lastIndexOf('modules');
                const cfgIndex = parts.lastIndexOf('cfg');
                const base = parts.length ? parts[parts.length - 1] : normalized;
                const parsed = splitFileName(base);

                const modul = modulesIndex !== -1 && parts[modulesIndex + 1]
                    ? parts[modulesIndex + 1]
                    : (cfgIndex > 0 ? parts[cfgIndex - 1] : '');

                const name = parsed.name === 'config' ? 'config' : (parsed.name || 'config');
                return [modul, name, parsed.ext].filter(Boolean).join(' - ');
            }

            function getGenericFileTitle(path) {

                const normalized = String(path || '').replace(/\\/g, '/');
                const parts = normalized.split('/').filter(Boolean);
                const base = parts.length ? parts[parts.length - 1] : normalized;
                const parsed = splitFileName(base);

                return [parsed.name || 'Datei', parsed.ext].filter(Boolean).join(' - ');
            }

            function splitFileName(fileName) {

                const base = String(fileName || '');
                const dot = base.lastIndexOf('.');

                if (dot > 0 && dot < base.length - 1) {
                    return {
                        name: base.substring(0, dot),
                        ext: base.substring(dot + 1)
                    };
                }

                return {
                    name: base,
                    ext: ''
                };
            }


            function getFileIcon(path) {

                const ext = (path.split('.').pop() || '').toLowerCase();

                if (ext === 'php') return 'bi-filetype-php';
                if (ext === 'js')  return 'bi-filetype-js';
                if (ext === 'css') return 'bi-filetype-css';
                if (ext === 'html' || ext === 'htm') return 'bi-filetype-html';

                return 'bi-file-earmark';
            }

        }

    });

})();
