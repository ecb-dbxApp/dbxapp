(function () {
    "use strict";

    function assert(condition, message) {
        if (!condition) throw new Error(message);
    }

    if (typeof document === "undefined") {
        const fs = require("fs");
        const path = require("path");
        const root = path.resolve(__dirname, "../../..");
        const jquery = fs.readFileSync(path.resolve(root, "dbx/vendor/components/jquery/jquery.js"), "utf8");
        const tree = fs.readFileSync(path.resolve(root, "dbx/add_ons/dbxJstree/dbxJstree.js"), "utf8");
        assert(jquery.includes("jQuery JavaScript Library v4.0.0"), "jQuery 4.0.0 fehlt.");
        assert(tree.includes("dbxJstree 1.0.0"), "Vollständige dbxJstree-Lib fehlt.");
        assert(tree.includes("feature.register('dbxJstree'"), "dbxJstree ist nicht im dbxapp-Lader registriert.");
        assert(tree.includes("add_ons/dbxJstree/assets"), "dbxJstree verwendet nicht seinen isolierten Asset-Pfad.");
        console.log("PASS dbxJstree static jQuery-4 contract");
        return;
    }

    window.dbxSelfTest.defer();
    const projectRoot = new URL("../../../", window.__dirname);
    const assetRevision = Date.now().toString(36);

    function load(relative) {
        return new Promise((resolve, reject) => {
            const script = document.createElement("script");
            const url = new URL(relative, projectRoot);
            url.searchParams.set("dbx_selftest", assetRevision);
            script.src = url.href;
            script.onload = resolve;
            script.onerror = () => reject(new Error("Script konnte nicht geladen werden: " + relative));
            document.head.appendChild(script);
        });
    }

    load("dbx/vendor/components/jquery/jquery.min.js")
        .then(() => load("dbx/add_ons/dbxJstree/dbxJstree.js"))
        .then(() => new Promise((resolve, reject) => {
            let runtimeError = null;
            const captureRuntimeError = event => {
                runtimeError = event.error || new Error(event.message || "Unbekannter Browserfehler");
            };
            window.addEventListener("error", captureRuntimeError);
            assert(window.jQuery.fn.jquery === "4.0.0", "Browser nutzt nicht jQuery 4.0.0.");
            assert(window.jQuery.jstree.version === "1.0.0", "Falsche dbxJstree-Version.");
            assert(window.dbxJstree.jqueryCompatibility === "4.x", "jQuery-Kompatibilität fehlt.");
            ["changed", "checkbox", "conditionalselect", "contextmenu", "dnd", "massload", "search", "sort", "state", "types", "unique", "wholerow"]
                .forEach(plugin => assert(!!window.jQuery.jstree.plugins[plugin], "Plug-in fehlt: " + plugin));

            const host = document.createElement("div");
            document.body.appendChild(host);
            const tree = window.jQuery(host);
            const timer = window.setTimeout(() => {
                window.removeEventListener("error", captureRuntimeError);
                reject(new Error("dbxJstree ready timeout"));
            }, 5000);
            tree.one("ready.jstree", () => {
                window.setTimeout(() => {
                    try {
                        const instance = tree.jstree(true);
                        assert(instance.get_node("root").text === "Wurzel", "Startknoten fehlt.");
                        const child = instance.create_node("root", { text: "Kind" });
                        assert(!!child, "Knoten konnte nicht erstellt werden.");
                        instance.rename_node(child, "Umbenannt");
                        assert(instance.get_node(child).text === "Umbenannt", "Umbenennen fehlgeschlagen.");
                        instance.select_node(child);
                        assert(instance.is_selected(child), "Auswahl fehlgeschlagen.");
                        instance.delete_node(child);
                        assert(instance.get_node(child) === false, "Löschen fehlgeschlagen.");
                        instance.destroy();
                        window.clearTimeout(timer);
                        window.setTimeout(() => {
                            window.removeEventListener("error", captureRuntimeError);
                            if (runtimeError) {
                                reject(runtimeError);
                                return;
                            }
                            resolve();
                        }, 200);
                    } catch (error) {
                        window.clearTimeout(timer);
                        window.removeEventListener("error", captureRuntimeError);
                        reject(error);
                    }
                }, 0);
            });
            tree.jstree({
                core: {
                    check_callback: true,
                    data: [{ id: "root", parent: "#", text: "Wurzel" }]
                },
                plugins: ["changed", "checkbox", "conditionalselect", "contextmenu", "dnd", "massload", "search", "sort", "state", "types", "unique", "wholerow"]
            });
        }))
        .then(() => window.dbxSelfTest.pass("PASS dbxJstree create/rename/select/delete mit jQuery 4"))
        .catch(error => window.dbxSelfTest.fail(error));
}());
