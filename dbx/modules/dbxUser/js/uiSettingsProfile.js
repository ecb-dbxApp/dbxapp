/*!
 * @file uiSettingsProfile.js
 * Speichert und aktiviert sessionuebergreifende Benutzer-UI-Profile.
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) return;
    const dbx = window.dbx;

    function readProfile(root, context) {
        const source = root.querySelector(`[data-ui-profile-json="${context}"]`);
        if (!source) return {};
        try {
            const value = JSON.parse(source.value || "{}");
            return value && typeof value === "object" && !Array.isArray(value) ? value : {};
        } catch (error) {
            return {};
        }
    }

    function renderCurrent(root, context) {
        const values = dbx.uiCollectDefaultCandidates ? dbx.uiCollectDefaultCandidates(context) : {};
        const keys = Object.keys(values).sort((a, b) => a.localeCompare(b, "de"));
        const count = root.querySelector("[data-ui-profile-current-count]");
        const preview = root.querySelector("[data-ui-profile-current]");
        if (count) count.textContent = String(keys.length);
        if (!preview) return values;
        preview.replaceChildren();
        if (!keys.length) {
            const empty = document.createElement("div");
            empty.className = "dbx-user-ui-empty";
            empty.textContent = "Noch keine persönlichen UI-Werte vorhanden.";
            preview.appendChild(empty);
            return values;
        }
        keys.forEach(keyName => {
            const row = document.createElement("div");
            row.className = "dbx-user-ui-row";
            const code = document.createElement("code");
            code.textContent = keyName.replace(/^dbx\.UI\./, "");
            const value = document.createElement("span");
            value.textContent = JSON.stringify(values[keyName]);
            row.append(code, value);
            preview.appendChild(row);
        });
        return values;
    }

    function init(root) {
        if (!root || root.__dbxUiProfileBound) return;
        root.__dbxUiProfileBound = true;
        const context = dbx.uiDefaultContext && dbx.uiDefaultContext() === "mobile" ? "mobile" : "desktop";
        root.querySelectorAll("[data-ui-profile-context-input]").forEach(input => { input.value = context; });
        root.querySelectorAll("[data-ui-profile-context-label]").forEach(label => {
            label.textContent = context === "mobile" ? "Mobile" : "Desktop";
        });
        renderCurrent(root, context);

        const saveForm = root.querySelector("[data-ui-profile-form='save']");
        if (saveForm) {
            saveForm.addEventListener("submit", event => {
                const values = dbx.uiCollectDefaultCandidates ? dbx.uiCollectDefaultCandidates(context) : {};
                if (!Object.keys(values).length) {
                    event.preventDefault();
                    return;
                }
                const json = saveForm.querySelector("[data-ui-profile-current-json]");
                if (json) json.value = JSON.stringify(values);
            });
        }

        root.querySelectorAll("[data-ui-profile-load]").forEach(button => {
            const buttonContext = button.getAttribute("data-ui-profile-load") === "mobile" ? "mobile" : "desktop";
            if (buttonContext !== context) {
                button.disabled = true;
                button.title = "Dieses Profil kann im passenden Darstellungskontext aktiviert werden.";
            }
            button.addEventListener("click", () => {
                const target = button.getAttribute("data-ui-profile-load") === "mobile" ? "mobile" : "desktop";
                const profile = readProfile(root, target);
                if (!Object.keys(profile).length || !dbx.uiApplySnapshot) return;
                dbx.uiApplySnapshot(profile, target);
                window.location.reload();
            });
        });

        const clearForm = root.querySelector("[data-ui-profile-form='clear']");
        if (clearForm) {
            clearForm.addEventListener("submit", () => {
                if (dbx.uiClearPersonalDefaults) dbx.uiClearPersonalDefaults(context);
            });
        }
    }

    dbx.feature.register("uiSettingsProfile", {
        scope: "element",
        css: [["css", "root", "modules/dbxUser/tpl/css/ui-settings.css"]],
        init: init,
        rescan: function (ctx) {
            const root = ctx && ctx.matches && ctx.matches("[data-user-ui-settings='1']")
                ? ctx
                : (ctx && ctx.querySelector ? ctx.querySelector("[data-user-ui-settings='1']") : null);
            if (root) init(root);
        }
    });
})(window, document);
