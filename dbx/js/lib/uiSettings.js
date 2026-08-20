/*!
 * @file uiSettings.js
 * Dreistufige UI-Vererbung: persoenlicher Wert, Admin-Standard, Produktwert.
 */
(function (window) {
    "use strict";

    if (!window.dbx) return;
    const dbx = window.dbx;
    dbx.uiDefaultPayload = dbx.uiDefaultPayload || { desktop: {}, mobile: {} };

    function storageKey(lib, id, key) {
        if (!lib || !id || !key || id === "undef") return "";
        return `dbx.UI.${lib}.${id}.${key}`;
    }

    function context() {
        return window.matchMedia && window.matchMedia("(max-width: 760px)").matches
            ? "mobile"
            : "desktop";
    }

    function eligible(key, targetContext) {
        key = String(key || "");
        targetContext = targetContext === "mobile" ? "mobile" : "desktop";
        if (/^dbx\.UI\.grid\.[A-Za-z0-9_.:-]{1,180}\.(PAGE\.SIZE|GRIDLINES|AUTOSAVE|COLUMNS\.ORDER|COLUMNS\.(SIZE|VISIBLE)\.[A-Za-z0-9_.:-]{1,120})$/.test(key)) return true;
        if (targetContext === "desktop" && /^dbx\.UI\.grid\.[A-Za-z0-9_.:-]{1,180}\.HEIGHT$/.test(key)) return true;
        if (/^dbx\.UI\.utilities\.global\.(mode|theme|skin(?::[a-z0-9_-]{1,80})?)$/.test(key)) return true;
        if (/^dbx\.UI\.collapse\.[A-Za-z0-9_.:-]{1,180}\.state$/.test(key)) return true;
        if (/^dbx\.UI\.menu\.[A-Za-z0-9_.:-]{1,180}\.branches$/.test(key)) return true;
        return key === "dbx.UI.adminDashboard.admin-dashboard.section";
    }

    dbx.uiStorageKey = storageKey;
    dbx.uiDefaultContext = context;
    dbx.uiIsDefaultEligible = eligible;

    dbx.uiHasPersonal = function (lib, id, key) {
        const keyName = storageKey(lib, id, key);
        if (!keyName) return false;
        try { return localStorage.getItem(keyName) !== null; } catch (e) { return false; }
    };

    dbx.uiRemove = function (lib, id, key) {
        const keyName = storageKey(lib, id, key);
        if (!keyName) return;
        try { localStorage.removeItem(keyName); } catch (e) { dbx.warn("uiRemove failed:", keyName, e); }
    };

    dbx.uiSet = function (lib, id, key, value) {
        const keyName = storageKey(lib, id, key);
        if (!keyName) return;
        try {
            if (value === null || value === undefined) {
                localStorage.removeItem(keyName);
                return;
            }
            localStorage.setItem(keyName, JSON.stringify(value));
        } catch (e) {
            dbx.warn("uiSet failed:", keyName, e);
        }
    };

    dbx.uiGet = function (lib, id, key, productDefault) {
        const keyName = storageKey(lib, id, key);
        if (!keyName) return productDefault;
        try {
            const personal = localStorage.getItem(keyName);
            if (personal !== null) return JSON.parse(personal);
        } catch (e) {
            dbx.warn("uiGet failed:", keyName, e);
        }

        const defaults = dbx.uiDefaultPayload && dbx.uiDefaultPayload[context()];
        return defaults && Object.prototype.hasOwnProperty.call(defaults, keyName)
            ? defaults[keyName]
            : productDefault;
    };

    dbx.uiCollectDefaultCandidates = function (targetContext) {
        targetContext = targetContext === "mobile" ? "mobile" : "desktop";
        const values = {};
        try {
            for (let index = 0; index < localStorage.length; index += 1) {
                const keyName = localStorage.key(index);
                if (!eligible(keyName, targetContext)) continue;
                try { values[keyName] = JSON.parse(localStorage.getItem(keyName)); } catch (e) {}
            }
        } catch (e) {
            dbx.warn("uiCollectDefaultCandidates failed:", e);
        }
        return values;
    };

    dbx.uiClearPersonalDefaults = function (targetContext) {
        const values = dbx.uiCollectDefaultCandidates(targetContext);
        Object.keys(values).forEach(keyName => {
            try { localStorage.removeItem(keyName); } catch (e) {}
        });
        return Object.keys(values).length;
    };

    dbx.uiApplySnapshot = function (snapshot, targetContext) {
        targetContext = targetContext === "mobile" ? "mobile" : "desktop";
        dbx.uiClearPersonalDefaults(targetContext);
        if (!snapshot || typeof snapshot !== "object" || Array.isArray(snapshot)) return 0;
        let applied = 0;
        Object.keys(snapshot).forEach(keyName => {
            if (!eligible(keyName, targetContext)) return;
            try {
                localStorage.setItem(keyName, JSON.stringify(snapshot[keyName]));
                applied += 1;
            } catch (e) {}
        });
        return applied;
    };
})(window);
