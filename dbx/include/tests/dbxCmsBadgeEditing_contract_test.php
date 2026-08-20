<?php

declare(strict_types=1);

$source = (string)file_get_contents(dirname(__DIR__, 2) . '/modules/dbxContent_admin/js/cms.js')
    . (string)file_get_contents(dirname(__DIR__, 2) . '/modules/dbxContent_admin/js/cms-context.js')
    . (string)file_get_contents(dirname(__DIR__, 2) . '/modules/dbxContent_admin/js/cms-components.js')
    . (string)file_get_contents(dirname(__DIR__, 2) . '/modules/dbxContent_admin/js/cms-editor.js');
$base = dirname(__DIR__, 2);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(
    str_contains($source, 'data-dbx-cms-editable-badge')
        && str_contains($source, 'badge.setAttribute("contenteditable", "true")')
        && str_contains($source, 'badge.setAttribute("tabindex", "0")')
        && str_contains($source, 'const focusEditableBadge = event =>')
        && str_contains($source, 'function openEditableBadgeTextInput(badge, surface)')
        && str_contains($source, 'input.setAttribute("aria-label", "Pill-Text bearbeiten")')
        && str_contains($source, 'finish(true)')
        && str_contains($source, 'finish(false)')
        && str_contains($source, 'doc.addEventListener("mousedown", focusEditableBadge, true)'),
    'Normale Content-Badges werden im visuellen Editor nicht ausdrücklich editierbar gemacht.'
);
$assert(
    str_contains($source, 'label: "Pills"')
        && str_contains($source, 'badge text-bg-primary">Pill 1')
        && str_contains($source, 'badge text-bg-secondary">Pill 2')
        && str_contains($source, 'badge text-bg-success">Pill 3'),
    'Pills fehlen im Menü der Bootstrap-Content-Komponenten.'
);
$assert(
    str_contains($source, 'function editorFlexAlignmentTarget(root)')
        && str_contains($source, 'justifyCenter: "justify-content-center"')
        && str_contains($source, 'applyEditorAlignment(root, item.command)'),
    'Die Textausrichtung berücksichtigt Bootstrap-Flex-Container nicht.'
);
$assert(
    str_contains($source, 'badge.removeAttribute("data-dbx-cms-editable-badge")')
        && str_contains($source, 'badge.removeAttribute("contenteditable")')
        && str_contains($source, 'badge.removeAttribute("tabindex")')
        && str_contains($source, 'function editorHtmlSnapshot(surface)')
        && str_contains($source, 'const snapshot = surface.cloneNode(true);')
        && str_contains($source, 'cleanEditorRuntimeNodes(snapshot);')
        && substr_count($source, 'bindBootstrapCardEditingGuards(root);') >= 5,
    'Editor-Runtimeattribute werden nicht ohne Mutation der sichtbaren Editorfläche serialisiert.'
);
$assert(
    str_contains($source, 'function movableEditorButtonBlock(root, target)')
        && str_contains($source, 'button.setAttribute("draggable", "true")')
        && str_contains($source, 'surface.addEventListener("dragstart"')
        && str_contains($source, 'surface.addEventListener("drop"')
        && str_contains($source, 'target.parentNode.insertBefore(drag.block')
        && str_contains($source, 'button.removeAttribute("data-dbx-cms-movable-button")'),
    'Bootstrap-Buttons können im visuellen Editor nicht als vollständige Inhaltsblöcke verschoben werden.'
);
$assert(
    str_contains($source, 'function movableEditorContextBlock(root, target)')
        && str_contains($source, 'const block = movableEditorContextBlock(root, e.target)')
        && str_contains($source, 'data-dbx-cms-movable-block')
        && str_contains($source, 'block.setAttribute("draggable", "true")')
        && str_contains($source, 'surface.contains(lockedParent)')
        && str_contains($source, 'block.removeAttribute("data-dbx-cms-movable-block")'),
    'Drag-and-drop ist nicht einheitlich für Textblöcke, Medien, Marker und Bootstrap-Komponenten aktiviert.'
);
$assert(
    str_contains($source, 'function editorContextBlockHtml(root, target)')
        && str_contains($source, 'const button = movableEditorButtonBlock(root, target);')
        && str_contains($source, 'if (button && surface.contains(button)) return button;')
        && str_contains($source, 'state(root).editorClipboardHtml = html')
        && str_contains($source, 'function insertEditorContextBlock(root, html, target)')
        && str_contains($source, 'pasteEditorContext(root, movable)')
        && str_contains($source, 'const block = movableEditorContextBlock(root, target);'),
    'Kopieren, Ausschneiden, Einfügen und Löschen verwenden nicht dieselbe allgemeine Elementauswahl.'
);
$assert(
    str_contains($source, 'function rememberEditorContextPasteRange(root, x, y)')
        && str_contains($source, 'function restoreEditorContextPasteRange(root)')
        && str_contains($source, 'editorClipboardHtmlAtCaret(root, html)')
        && str_contains($source, 'children[0].matches("a.btn")')
        && str_contains($source, 'paragraph.firstElementChild.matches("a.btn")')
        && str_contains($source, 'data-dbx-cms-button-caret-anchor')
        && str_contains($source, 'data-dbx-cms-element-caret-anchor')
        && str_contains($source, 'closestElement(activeSurface, ".dbx-cms")')
        && str_contains($source, 'activeSurface.focus({ preventScroll: true })')
        && str_contains($source, 'function setEditorCaretBesideElement(root, element, side)')
        && str_contains($source, 'range.setStartBefore(element)')
        && str_contains($source, 'range.setStartAfter(element)')
        && !str_contains($source, 'function ensureEditorCaretHost(anchor)')
        && !str_contains($source, 'function ensureLeadingEditorParagraph(surface)')
        && str_contains($source, 'function commitEditorCaretHosts(container)')
        && str_contains($source, 'setEditorCaretInCardBody(editorRoot, cardBody)')
        && str_contains($source, 'data-dbx-cms-caret-host')
        && str_contains($source, 'function normalizeEditorElementCaretAnchors(surface, doc)')
        && str_contains($source, 'function createEditorCaretAnchors(doc, element, kind, layout)')
        && str_contains($source, 'data-dbx-cms-caret-side')
        && str_contains($source, 'createEditorCaretAnchor(doc, element, kind, layout, "before")')
        && str_contains($source, 'createEditorCaretAnchor(doc, element, kind, layout, "after")')
        && str_contains($source, 'doc.createElement(layout === "block" ? "div" : "span")')
        && str_contains($source, 'qsa(surface, "a.btn").forEach(button => createEditorCaretAnchors')
        && str_contains($source, '.alert,.card,.list-group,.accordion,.table-responsive,.row,.nav-tabs,.tab-content,.badge')
        && str_contains($source, 'function alignEditorContextPasteRange(root, html)')
        && str_contains($source, 'closestElement(start, "code,kbd,samp,a,button")')
        && str_contains($source, 'function insertDraggedEditorBlockAtCaret(root, block)')
        && str_contains($source, 'function insertEditorBlockFragmentAtRange(surface, range, fragment)')
        && str_contains($source, 'if (!insertEditorBlockFragmentAtRange(surface, range, tpl.content))')
        && str_contains($source, 'const afterBlock = block.cloneNode(false);')
        && str_contains($source, 'if (qs(surface, "[data-dbx-cms-caret-host]")) return;')
        && str_contains($source, 'range.insertNode(moved)')
        && substr_count($source, 'state(root).editorContextPasteRange = null;') >= 3,
    'Einfügen und Drag-and-drop berücksichtigen nicht die genaue Caret-Position innerhalb eines Zielblocks.'
);
$assert(
    str_contains($source, 'document.body.appendChild(hint)')
        && str_contains($source, 'function editorCaretRect(range)')
        && str_contains($source, 'function currentEditorCaretRange(surface)')
        && str_contains($source, 'explicitRange || s.editorContextPasteRange || currentEditorCaretRange(surface)')
        && str_contains($source, 'range.getBoundingClientRect()')
        && str_contains($source, 'range.getClientRects ? range.getClientRects()')
        && str_contains($source, 'const hostRect = node.getBoundingClientRect')
        && str_contains($source, 'Math.max(11, Math.min(15')
        && str_contains($source, 'refreshEditorCaretHint(root, state(root).editorContextPasteRange)'),
    'Die präzise Einfügeposition wird im Editor nicht sichtbar hervorgehoben.'
);
foreach (['dbxapp', 'flowers', 'steal'] as $design) {
    $css = (string)file_get_contents($base . '/design/' . $design . '/css/c-cms.css');
    $assert(
        str_contains($css, '.is-dbx-cms-dragging')
            && str_contains($css, '.is-dbx-cms-drop-target')
            && str_contains($css, '.dbx-cms-editor-caret-hint')
            && str_contains($css, 'caret-color: #0057ff !important')
            && str_contains($css, '.jodit-wysiwyg.is-dbx-cms-caret-preview')
            && str_contains($css, 'caret-color: transparent !important')
            && str_contains($css, '[data-dbx-cms-caret-side="after"]:last-child')
            && str_contains($css, 'min-width: 2rem')
            && str_contains($css, 'width: 3px')
            && str_contains($css, '[data-dbx-cms-button-caret-anchor]')
            && str_contains($css, '[data-dbx-cms-element-caret-anchor]')
            && str_contains($css, '[data-dbx-cms-caret-layout="block"]')
            && str_contains($css, 'margin: 1px 0')
            && str_contains($css, 'z-index: 2')
            && str_contains($css, '[data-dbx-cms-caret-layout="block"]::after')
            && str_contains($css, '[data-dbx-cms-caret-host]')
            && str_contains($css, 'min-width: .65rem')
            && str_contains($css, 'z-index: 2147483646'),
        'Drag-and-drop-Rückmeldung fehlt im CMS-Stylesheet des Designs ' . $design . '.'
    );
}

echo "OK CMS badges are editable and flex alignment maps to Bootstrap classes.\n";
