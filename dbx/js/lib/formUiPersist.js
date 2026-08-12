/*!
 * dbxapp formUiPersist.js
 * Merkt sich den Wert einzelner dbxForm-Felder dauerhaft im Browser
 * (dbx.uiGet/uiSet) und stellt ihn beim naechsten Laden automatisch
 * wieder her. Wird von dbxForm::create_fld() ueber
 * data-dbx="lib=formUiPersist|form={fid}|key={fieldname}" aktiviert,
 * siehe dbxForm::$_ui_state_persist und das FD-Feld-Flag
 * `data=ui_persist=1`.
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][formUiPersist] dbx core missing");
        return;
    }

    const dbx = window.dbx;
    const LIB = "formUiPersist";

    dbx.feature.register(LIB, {
        scope: "element",
        priority: "low",
        init(el, cfg) {
            if (!el || el.__dbxFormUiPersistReady) return;
            el.__dbxFormUiPersistReady = true;

            const form = String((cfg && cfg.form) || "form");
            const key = String((cfg && cfg.key) || el.name || el.id || "");
            if (!key) return;

            const isCheckbox = el.type === "checkbox";

            // Der gemerkte Browser-Zustand ist fuer diese Felder massgeblich,
            // genau wie z.B. bei der Grid-Seitengroesse (dbx.uiGet/uiSet) -
            // die Checkbox-Auswahl wird nicht pro Datensatz gespeichert,
            // sondern ist eine reine Bedienpraeferenz des Anwenders.
            const stored = dbx.uiGet(LIB, form, key, null);
            if (stored !== null) {
                if (isCheckbox) el.checked = !!stored;
                else el.value = String(stored);
            }

            el.addEventListener("change", () => {
                dbx.uiSet(LIB, form, key, isCheckbox ? el.checked : el.value);
            });
        },
        rescan(root) {
            (root || document).querySelectorAll('[data-dbx*="lib=' + LIB + '"]').forEach(el => {
                if (el.__dbxFormUiPersistReady) return;
                const cfgList = dbx.parseData(el.getAttribute("data-dbx"));
                const cfg = cfgList.find(item => item.lib === LIB) || {};
                this.init(el, cfg);
            });
        }
    });

})(window, document);
