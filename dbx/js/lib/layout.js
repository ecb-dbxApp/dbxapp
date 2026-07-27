(function (window) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        return;
    }

    window.dbx.feature.register("layout", {
        scope: "element",
        priority: "mid",
        init() {
            // Layout-Lib ist aktuell ein reservierter Erweiterungspunkt.
        }
    });

})(window);
