/*!
 * dbxapp cms-jodit-image.js
 * Lazy Erweiterung fuer den Jodit-Bilddialog und den zentralen Medienbrowser.
 */
(function (window, document) {
    "use strict";

    const dbx = window.dbx;
    const runtime = dbx && dbx.cmsRuntime;
    if (!runtime || typeof runtime.register !== "function") {
        console.error("[dbx][cms-jodit-image] CMS runtime missing");
        return;
    }

    runtime.register("joditImage", function (context) {
        const {
            closestElement,
            cmsText,
            escapeHtml,
            isImageRow,
            openMediaBrowser,
            qs,
            qsa,
            status
        } = context;

        function visibleJoditDialogInputs(panel) {
            return qsa(panel, "input, textarea").filter(input => {
                const type = String(input.getAttribute("type") || "text").toLowerCase();
                if (["hidden", "file", "button", "submit", "reset", "checkbox", "radio"].includes(type)) return false;
                if (input.disabled || input.readOnly) return false;
                return !!input.getClientRects().length;
            });
        }

        function joditDialogValueInputs(panel) {
            return qsa(panel, "input, textarea").filter(input => {
                const type = String(input.getAttribute("type") || "text").toLowerCase();
                if (["hidden", "file", "button", "submit", "reset", "checkbox", "radio"].includes(type)) return false;
                return !input.disabled && !input.readOnly;
            });
        }

        function findJoditImageDialogPathInput(panel) {
            if (panel && panel.__dbxCmsJoditPathInput && panel.contains(panel.__dbxCmsJoditPathInput)) {
                return panel.__dbxCmsJoditPathInput;
            }
            const visibleInputs = visibleJoditDialogInputs(panel);
            const inputs = visibleInputs.length ? visibleInputs : joditDialogValueInputs(panel);
            return inputs.find(input => /src|url|path|image|bild/i.test(String(input.name || input.id || input.getAttribute("placeholder") || "")))
                || inputs.find(input => String(input.value || "").indexOf("index.php") >= 0 || /\.(jpg|jpeg|png|gif|webp|svg)(\?|#|$)/i.test(input.value || ""))
                || inputs[0]
                || null;
        }

        function joditImageDialogPanel(target) {
            const panel = closestElement(target, ".jodit-dialog__panel, .jodit-dialog");
            if (!panel) return null;
            const text = String(panel.textContent || "");
            if (!/Bildeigenschaften|Image properties|Bild/i.test(text)) return null;
            return findJoditImageDialogPathInput(panel) ? panel : null;
        }

        function hideJoditUploadControl(el) {
            if (!el) return;
            const node = closestElement(el, ".jodit-drag-and-drop__file-box, .jodit-form__group, .jodit-ui-block, .jodit-ui-group, .jodit-upload, .jodit-uploader, label, div") || el;
            node.classList.add("dbx-cms-jodit-upload-hidden");
            node.hidden = true;
        }

        function compactJoditImageDialog(root, panel) {
            if (!panel) return;
            panel.classList.add("dbx-cms-jodit-image-dialog");
            qsa(panel, "input[type='file'], .jodit-drag-and-drop__file-box, .jodit_uploadfile_button, .jodit-uploader, .jodit-upload").forEach(hideJoditUploadControl);
            qsa(panel, "button, a, [role='button']").forEach(btn => {
                const label = String((btn.textContent || "") + " " + (btn.title || "") + " " + (btn.getAttribute("aria-label") || ""));
                if (/Hochladen|Upload/i.test(label)) {
                    if (isJoditImageDialogMediaTrigger(panel, btn)) {
                        btn.dataset.dbxTooltip = "Bild aus Medienbrowser auswaehlen";
                        return;
                    }
                    btn.classList.add("dbx-cms-jodit-upload-hidden");
                    btn.hidden = true;
                }
            });
            ensureJoditImageDialogMediaButton(root, panel);
            hideJoditImageDialogPathField(panel);
        }

        function joditImageDialogPathContainer(pathInput) {
            if (!pathInput) return null;
            const dialog = closestElement(pathInput, ".jodit-dialog__panel, .jodit-dialog");
            let node = pathInput.parentElement;
            for (let i = 0; node && node !== dialog && i < 5; i++) {
                const inputs = joditDialogValueInputs(node);
                const text = String(node.textContent || "");
                if (inputs.length === 1 && inputs[0] === pathInput && /Pfad|URL|Src|Quelle|Source/i.test(text)) return node;
                node = node.parentElement;
            }
            return pathInput;
        }

        function joditImagePreviewHost(panel) {
            const img = qsa(panel, "img").find(item => {
                if (item.closest && item.closest(".jodit-dialog__header")) return false;
                return !!item.getClientRects().length;
            });
            if (!img) return null;
            const dialog = closestElement(img, ".jodit-dialog__panel, .jodit-dialog");
            let host = img.parentElement;
            for (let i = 0; host && host !== dialog && i < 4; i++) {
                if (host.children.length <= 4) break;
                host = host.parentElement;
            }
            if (!host || host === dialog) host = img.parentElement;
            if (host) host.classList.add("dbx-cms-jodit-preview-picker");
            return host;
        }

        function ensureJoditImageDialogMediaButton(root, panel) {
            const pathInput = findJoditImageDialogPathInput(panel);
            if (!pathInput || qs(panel, "[data-cms-jodit-media-select]")) return;
            panel.__dbxCmsJoditPathInput = pathInput;
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "btn btn-outline-primary btn-sm dbx-cms-hero-pick dbx-cms-jodit-media-select";
            btn.setAttribute("data-cms-jodit-media-select", "1");
            btn.dataset.dbxTooltip = "Bild aus Medienbrowser auswaehlen";
            btn.innerHTML = '<i class="bi bi-image"></i><i class="bi bi-camera-video"></i><i class="bi bi-upload"></i><span>'
                + escapeHtml(cmsText(root, "selection_label", "Auswahl")) + '</span>';
            const preview = joditImagePreviewHost(panel);
            if (preview) {
                preview.insertAdjacentElement("beforebegin", btn);
                return;
            }
            const container = joditImageDialogPathContainer(pathInput);
            if (container && container.parentElement) {
                container.insertAdjacentElement("beforebegin", btn);
                return;
            }
            pathInput.insertAdjacentElement("beforebegin", btn);
        }

        function hideJoditImageDialogPathField(panel) {
            const pathInput = findJoditImageDialogPathInput(panel);
            if (!pathInput) return;
            panel.__dbxCmsJoditPathInput = pathInput;
            const container = joditImageDialogPathContainer(pathInput);
            if (container && container !== pathInput && joditDialogValueInputs(container).length === 1) {
                container.classList.add("dbx-cms-jodit-path-hidden");
                container.hidden = true;
                return;
            }
            pathInput.classList.add("dbx-cms-jodit-path-hidden");
            pathInput.hidden = true;
            pathInput.setAttribute("aria-hidden", "true");
        }

        function isJoditImageDialogMediaTrigger(panel, target) {
            const trigger = closestElement(target, "button, a, [role='button'], .jodit-ui-button, .jodit-button, .jodit-input__icon, .jodit-icon");
            if (!panel || !trigger || !panel.contains(trigger)) return false;
            if (trigger.hasAttribute("data-cms-jodit-media-select")) return true;
            if (closestElement(trigger, ".jodit-dialog__header, .jodit-dialog__footer")) return false;
            const label = String((trigger.textContent || "") + " " + (trigger.title || "") + " " + (trigger.getAttribute("aria-label") || ""));
            if (/Abbrechen|Anwenden|Loeschen|L\u00f6schen|Schliessen|Schlie\u00dfen|Fortgeschritten/i.test(label)) return false;

            const pathInput = findJoditImageDialogPathInput(panel);
            if (!pathInput) return false;
            const inputRect = pathInput.getBoundingClientRect();
            const triggerRect = trigger.getBoundingClientRect();
            if (!inputRect.width || !triggerRect.width) return false;
            return triggerRect.left >= inputRect.right - 12
                && triggerRect.top <= inputRect.bottom + 8
                && triggerRect.bottom >= inputRect.top - 8;
        }

        function setJoditDialogFieldValue(field, value, overwrite) {
            if (!field || value == null) return;
            if (!overwrite && String(field.value || "").trim()) return;
            field.value = String(value || "");
            field.dispatchEvent(new Event("input", { bubbles: true }));
            field.dispatchEvent(new Event("change", { bubbles: true }));
        }

        function applyMediaToJoditImageDialog(root, panel, row) {
            if (!row || !row.url) return false;
            if (!isImageRow(row)) {
                status(root, "Bitte ein Bild auswaehlen.", "error");
                return false;
            }
            const inputs = visibleJoditDialogInputs(panel);
            const pathInput = findJoditImageDialogPathInput(panel);
            setJoditDialogFieldValue(pathInput, row.url, true);

            const afterPath = inputs.slice(Math.max(0, inputs.indexOf(pathInput) + 1))
                .filter(input => String(input.getAttribute("type") || "text").toLowerCase() !== "number");
            const titleInput = afterPath.find(input => /title|titel/i.test(String(input.name || input.id || input.getAttribute("placeholder") || ""))) || afterPath[0] || null;
            const altInput = afterPath.find(input => /alt|alternative/i.test(String(input.name || input.id || input.getAttribute("placeholder") || ""))) || afterPath.find(input => input !== titleInput) || null;
            const label = row.title || row.alt || row.file_name || "";
            setJoditDialogFieldValue(titleInput, row.title || label, false);
            setJoditDialogFieldValue(altInput, row.alt || label, false);
            qsa(panel, "img").forEach(img => {
                if (img.closest && img.closest(".jodit-dialog__header")) return;
                img.src = row.thumb_url || row.url;
                img.alt = row.alt || label;
            });
            status(root, "Bild aus Medienbrowser uebernommen.", "success");
            return true;
        }

        function openJoditImageDialogMediaBrowser(root, cfg, panel) {
            compactJoditImageDialog(root, panel);
            openMediaBrowser(root, cfg || {}, {
                mode: "pick",
                slot: "inline",
                singlePick: true,
                afterAssign(row) {
                    return applyMediaToJoditImageDialog(root, panel, row);
                }
            });
        }

        function bind(root, cfg) {
            if (root.__dbxCmsJoditImageModuleBound) return;
            root.__dbxCmsJoditImageModuleBound = true;

            const handle = event => {
                const panel = joditImageDialogPanel(event.target);
                if (!panel) return;
                compactJoditImageDialog(root, panel);
                if (event.type !== "click" || !isJoditImageDialogMediaTrigger(panel, event.target)) return;
                event.preventDefault();
                event.stopPropagation();
                if (event.stopImmediatePropagation) event.stopImmediatePropagation();
                openJoditImageDialogMediaBrowser(root, cfg || {}, panel);
            };

            document.addEventListener("click", handle, true);
            document.addEventListener("focusin", handle, true);
            qsa(document, ".jodit-dialog__panel, .jodit-dialog").forEach(panel => {
                if (joditImageDialogPanel(panel)) compactJoditImageDialog(root, panel);
            });
        }

        return Object.freeze({ bind });
    });
})(window, document);
