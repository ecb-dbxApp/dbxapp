/**
 * @file form.js
 * Clientseitige Formular-Erweiterungen fuer dbXapp.
 *
 * Diese Lib ist bewusst UI-nah und kapselt wiederverwendbare Form-Features:
 * - Multi-Select-Darstellung fuer `select[multiple]`
 * - Select1-UI fuer kompakte Auswahlfelder
 * - Passwortanzeigen
 *
 * Beispiel:
 * ```html
 * <form data-dbx="lib=form">
 *    <select multiple class="dbxMultiSelect2" name="groups[]">...</select>
 * </form>
 * ```
 */
(function (window, document) {
    "use strict";

    function uiText() {
        const language = String(document.documentElement.lang || "de")
            .toLowerCase()
            .slice(0, 2);
        const translations = {
            de: {
                selected: "Ausgewählt",
                available: "Verfügbar",
                emptySelected: "Keine Auswahl",
                emptyAvailable: "Keine weiteren Werte",
                emptyValues: "Keine Werte"
            },
            en: {
                selected: "Selected",
                available: "Available",
                emptySelected: "Nothing selected",
                emptyAvailable: "No more values",
                emptyValues: "No values"
            },
            es: {
                selected: "Seleccionados",
                available: "Disponibles",
                emptySelected: "Ninguna selección",
                emptyAvailable: "No hay más valores",
                emptyValues: "No hay valores"
            }
        };
        return translations[language] || translations.de;
    }

    function optionText(option) {
        return (option.textContent || option.innerText || option.value || "").trim();
    }

    function setSelected(select, value, selected) {
        Array.from(select.options).forEach(option => {
            if (option.value === value) {
                option.selected = selected;
            }
        });

        select.dispatchEvent(new Event("change", { bubbles: true }));
        select.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function createItem(option, side, move) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "dbx-ms2-item";
        btn.dataset.value = option.value;
        btn.dataset.side = side;
        btn.textContent = optionText(option);
        btn.dataset.dbxTooltip = optionText(option);
        btn.addEventListener("click", () => move(option.value, side));
        return btn;
    }

    function buildMultiSelect(select) {
        if (!select || select.dataset.dbxMultiselectReady === "1") return;
        const text = uiText();

        select.dataset.dbxMultiselectReady = "1";
        select.classList.add("dbx-ms2-source");

        const wrapper = document.createElement("div");
        wrapper.className = "dbx-ms2";
        wrapper.dataset.source = select.id || select.name || "";

        const selectedCol = document.createElement("div");
        selectedCol.className = "dbx-ms2-col";

        const availableCol = document.createElement("div");
        availableCol.className = "dbx-ms2-col";

        const selectedTitle = document.createElement("div");
        selectedTitle.className = "dbx-ms2-title";
        selectedTitle.textContent = text.selected;

        const availableTitle = document.createElement("div");
        availableTitle.className = "dbx-ms2-title";
        availableTitle.textContent = text.available;

        const selectedList = document.createElement("div");
        selectedList.className = "dbx-ms2-list";

        const availableList = document.createElement("div");
        availableList.className = "dbx-ms2-list";

        selectedCol.appendChild(selectedTitle);
        selectedCol.appendChild(selectedList);
        availableCol.appendChild(availableTitle);
        availableCol.appendChild(availableList);

        wrapper.appendChild(selectedCol);
        wrapper.appendChild(availableCol);

        const render = () => {
            selectedList.innerHTML = "";
            availableList.innerHTML = "";

            const selected = [];
            const available = [];

            Array.from(select.options).forEach(option => {
                if (option.disabled) return;
                if (select.multiple && (option.value === "" || (option.value === "0" && optionText(option).toLowerCase().indexOf("bitte") === 0))) return;
                if (option.selected) {
                    selected.push(option);
                } else {
                    available.push(option);
                }
            });

            selected.forEach(option => selectedList.appendChild(createItem(option, "selected", move)));
            available.forEach(option => availableList.appendChild(createItem(option, "available", move)));

            if (!selected.length) {
                const empty = document.createElement("div");
                empty.className = "dbx-ms2-empty";
                empty.textContent = text.emptySelected;
                selectedList.appendChild(empty);
            }

            if (!available.length) {
                const empty = document.createElement("div");
                empty.className = "dbx-ms2-empty";
                empty.textContent = text.emptyAvailable;
                availableList.appendChild(empty);
            }
        };

        function move(value, side) {
            setSelected(select, value, side === "available");
            render();
        }

        select.addEventListener("change", render);
        select.insertAdjacentElement("afterend", wrapper);
        render();
    }

    function findMultiSelectTargets(target, root) {
        const key = String(target || "").trim();
        const ctx = root || document;
        const nodes = Array.from(ctx.querySelectorAll([
            "select[multiple]",
            "select.dbxMultiSelect2",
            "select[data-dbx-multiselect2]",
            "select[data-dbx-multiselect]"
        ].join(","))).filter(select => {
            return !select.classList.contains("bsMultiSelect") &&
                !select.classList.contains("sel-multiple-line") &&
                !select.classList.contains("dbxSelect1") &&
                !select.hasAttribute("data-dbx-select1");
        });

        if (!key) return nodes;

        return nodes.filter(select => {
            return select.id === key ||
                select.id.indexOf(key + "_") === 0 ||
                select.name === key ||
                select.name === key + "[]";
        });
    }

    function initMultiSelects(root, target) {
        findMultiSelectTargets(target, root).forEach(buildMultiSelect);
    }

    window.dbxFormMultiselect = function(target) {
        initMultiSelects(document, target);
    };

    window.multiselect2 = window.dbxFormMultiselect;
    window.multiselect = window.dbxFormMultiselect;

    function selectedValues(select) {
        return Array.from(select.options)
            .filter(option => option.selected && !option.disabled)
            .map(option => option.value);
    }

    function syncSelect1Hidden(select, input) {
        input.value = "";
    }

    function emitSelect1Change(select) {
        select.dispatchEvent(new Event("change", { bubbles: true }));
        select.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function toggleSelect1Value(select, value, selected) {
        Array.from(select.options).forEach(option => {
            if (option.value === value) option.selected = selected;
        });
    }

    function buildSelect1(select) {
        if (!select || select.dataset.dbxSelect1Ready === "1") return;

        select.dataset.dbxSelect1Ready = "1";
        select.classList.add("dbx-select1-source");

        const wrapper = document.createElement("div");
        wrapper.className = "dbx-select1";
        wrapper.dataset.source = select.id || select.name || "";

        const control = document.createElement("div");
        control.className = "dbx-select1-control";

        const chips = document.createElement("div");
        chips.className = "dbx-select1-chips";

        const input = document.createElement("input");
        input.type = "text";
        input.className = "dbx-select1-input";
        input.autocomplete = "off";
        input.placeholder = select.getAttribute("placeholder") || select.dataset.prompt || "Auswahl...";

        const prompt = document.createElement("div");
        prompt.className = "dbx-select1-prompt";
        prompt.hidden = true;

        control.appendChild(chips);
        control.appendChild(input);
        wrapper.appendChild(control);
        wrapper.appendChild(prompt);

        function options() {
            return Array.from(select.options).filter(option => {
                if (option.disabled) return false;
                if (option.value === "" || option.value === "0") {
                    const text = optionText(option).toLowerCase();
                    return text && text.indexOf("bitte") !== 0 && text.indexOf("auswahl") !== 0;
                }
                return true;
            });
        }

        function render() {
            const filter = input.value.trim().toLowerCase();
            chips.innerHTML = "";
            prompt.innerHTML = "";

            const selected = selectedValues(select);
            selected.forEach(value => {
                const option = options().find(opt => opt.value === value);
                if (!option) return;

                const chip = document.createElement("button");
                chip.type = "button";
                chip.className = "dbx-select1-chip";
                chip.dataset.value = value;
                chip.dataset.dbxTooltip = "Abwählen";
                chip.innerHTML = `<span>${optionText(option)}</span><i class="bi bi-x-lg" aria-hidden="true"></i>`;
                chip.addEventListener("click", () => {
                    toggleSelect1Value(select, value, false);
                    syncSelect1Hidden(select, input);
                    emitSelect1Change(select);
                    render();
                    input.focus();
                });
                chips.appendChild(chip);
            });

            const matches = options().filter(option => {
                const text = optionText(option).toLowerCase();
                const value = String(option.value || "").toLowerCase();
                return !filter || text.includes(filter) || value.includes(filter);
            });

            matches.forEach(option => {
                const row = document.createElement("button");
                row.type = "button";
                row.className = "dbx-select1-option";
                if (option.selected) row.classList.add("is-selected");
                row.dataset.value = option.value;
                row.innerHTML = `<i class="bi ${option.selected ? "bi-check2-square" : "bi-square"}" aria-hidden="true"></i><span>${optionText(option)}</span>`;
                row.addEventListener("click", () => {
                    toggleSelect1Value(select, option.value, !option.selected);
                    input.value = "";
                    syncSelect1Hidden(select, input);
                    emitSelect1Change(select);
                    render();
                    input.focus();
                    prompt.hidden = false;
                });
                prompt.appendChild(row);
            });

            if (!matches.length) {
                const empty = document.createElement("div");
                empty.className = "dbx-select1-empty";
                empty.textContent = uiText().emptyValues;
                prompt.appendChild(empty);
            }

            syncSelect1Hidden(select, input);
        }

        let closeOnControlClick = false;

        input.addEventListener("focus", () => {
            prompt.hidden = false;
            render();
        });
        input.addEventListener("input", () => {
            prompt.hidden = false;
            render();
        });
        input.addEventListener("keydown", event => {
            if (event.key === "Escape") {
                prompt.hidden = true;
                input.blur();
            }
        });
        control.addEventListener("pointerdown", event => {
            closeOnControlClick = !prompt.hidden && !event.target.closest(".dbx-select1-chip");
        });
        control.addEventListener("click", event => {
            if (event.target.closest(".dbx-select1-chip")) return;

            if (closeOnControlClick) {
                prompt.hidden = true;
                input.blur();
                closeOnControlClick = false;
                return;
            }

            input.focus();
            prompt.hidden = false;
        });
        select.addEventListener("change", render);
        document.addEventListener("click", event => {
            if (!wrapper.contains(event.target) && event.target !== select) {
                prompt.hidden = true;
            }
        });

        select.insertAdjacentElement("afterend", wrapper);
        render();
    }

    function findSelect1Targets(root) {
        const ctx = root || document;
        const nodes = [];
        if (ctx.matches && ctx.matches("select.dbxSelect1,select[data-dbx-select1]")) {
            nodes.push(ctx);
        }
        ctx.querySelectorAll("select.dbxSelect1,select[data-dbx-select1]").forEach(select => nodes.push(select));
        return nodes;
    }

    function initSelect1(root) {
        findSelect1Targets(root || document).forEach(buildSelect1);
    }

    function initPasswordToggles(root) {
        const ctx = root || document;
        const buttons = [];

        if (ctx.matches && ctx.matches("[data-dbx-password-toggle]")) {
            buttons.push(ctx);
        }

        ctx.querySelectorAll("[data-dbx-password-toggle]").forEach(button => buttons.push(button));
        buttons.forEach(button => {
            if (button.dataset.dbxPasswordToggleReady === "1") return;

            const input = document.getElementById(button.dataset.dbxPasswordToggle || "");
            if (!input) return;

            button.dataset.dbxPasswordToggleReady = "1";
            button.addEventListener("click", () => {
                const show = input.type === "password";
                const icon = button.querySelector("i");

                input.type = show ? "text" : "password";
                input.dataset.dbxPasswordVisible = show ? "1" : "0";
                button.setAttribute("aria-pressed", show ? "true" : "false");
                button.setAttribute("aria-label", show ? "Passwort verbergen" : "Passwort anzeigen");
                button.setAttribute("data-dbx-tooltip", show ? "Passwort verbergen" : "Passwort anzeigen");

                if (icon) {
                    icon.classList.toggle("bi-eye", !show);
                    icon.classList.toggle("bi-eye-slash", show);
                }

                input.focus();
            });
        });
    }

    function initRoot(root) {
        initPasswordToggles(root || document);
        initSelect1(root || document);
        initMultiSelects(root || document);
    }

    function registerFormFeature() {
        if (!window.dbx || !window.dbx.feature || !window.dbx.feature.register) {
            return false;
        }

        if (window.dbx.feature.has && window.dbx.feature.has("form")) {
            return true;
        }

        window.dbx.feature.register("form", {

            scope: "element", // 🔥 FIX

            // 🔥 CSS über Core PREPARE
            css: [
                ['css', 'design', 'c-form.css']
            ],

            // 🔥 Prio
            priority: "mid",

            init(el, config) {

                if (!el) return;

                // --------------------------------------------------
                // INIT GUARD (pro Element)
                // --------------------------------------------------
                el.__dbxInitialized = el.__dbxInitialized || {};
                if (el.__dbxInitialized["form"]) return;
                el.__dbxInitialized["form"] = true;

                initRoot(el);

                // --------------------------------------------------
                // GLOBAL EVENT (einmalig!)
                // --------------------------------------------------
                if (document.__dbxFormInit) return;
                document.__dbxFormInit = true;

                window.dbx.log("[form] init global handlers");

                window.dbx.on("click", ".dbx-input-btn", function (e, btn) {

                    const wrap = btn.closest(".dbx-input");
                    if (!wrap) return;

                    const input = wrap.querySelector(".dbx-input-field");
                    if (!input) return;

                    const action = btn.dataset.action;

                    switch (action) {

                        case "clear":
                            input.value = "";
                            input.dispatchEvent(new Event("input", { bubbles: true }));
                            input.focus();
                            break;

                        case "calendar":
                            // später
                            break;

                        case "lookup":
                            // später
                            break;
                    }

                });

                window.dbx.on("change", "select.dbxMultiSelect2", function () {
                    initMultiSelects(document);
                });

                window.dbx.on("change", ".dbxForm_wrapper select[multiple]", function () {
                    initMultiSelects(document);
                });

            }

        });

        return true;
    }

    if (!registerFormFeature()) {
        let tries = 0;
        const timer = window.setInterval(function () {
            tries++;
            if (registerFormFeature() || tries > 100) {
                window.clearInterval(timer);
            }
        }, 25);
    }

})(window, document);
