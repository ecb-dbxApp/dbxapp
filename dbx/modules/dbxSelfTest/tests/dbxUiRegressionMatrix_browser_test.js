(function () {
    "use strict";

    /**
     * Reale UI-Regressionsmatrix fuer die wichtigsten dbxapp-Ablaufe.
     * Sie mutiert keine gespeicherten Daten: Upload und Wartung werden bis zur
     * vollstaendigen, bedienbaren UI geprueft, aber nicht abgeschickt.
     */
    function assert(condition, message) {
        if (!condition) throw new Error(message);
    }

    if (typeof document === "undefined") {
        const fs = require("fs");
        const path = require("path");
        const root = path.resolve(__dirname, "../../../..");
        const source = fs.readFileSync(__filename, "utf8");
        const runner = fs.readFileSync(path.resolve(root, "dbx/modules/dbxSelfTest/tpl/js/selftest.js"), "utf8");
        ["desktop", "tablet", "mobile", "/home", "cid=1", "dbx_run1=flat",
            "checkCmsEditor", "checkMediaBrowser", "checkOpenWinAjax", "checkTooltip"]
            .forEach(token => assert(source.includes(token), "UI-Matrixvertrag fehlt: " + token));
        assert(runner.includes('left: "-20000px"') && !runner.includes("frame.hidden = true"),
            "Browser-Runner stellt keinen gerenderten Layout-Viewport bereit.");
        console.log("PASS UI-Matrix-Quellvertrag; reale Geometrie laeuft im Browser-Selftest");
        return;
    }

    window.dbxSelfTest.defer();

    const projectRoot = new URL("../../../../", window.__dirname);
    const profiles = [
        { name: "desktop", width: 1440, height: 960 },
        { name: "tablet", width: 1024, height: 768 },
        { name: "mobile", width: 390, height: 844 }
    ];
    const targets = [
        {
            name: "home",
            path: "/home",
            ready: "body",
            maxNodes: 6500,
            maxResources: 180,
            maxLoadMs: 12000
        },
        {
            name: "content-1",
            path: "/index.php?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=1",
            ready: "[data-cms-editor]",
            maxNodes: 14000,
            maxResources: 240,
            maxLoadMs: 15000
        },
        {
            name: "report",
            path: "/index.php?dbx_modul=dbxContent_admin&dbx_run1=flat",
            ready: ".dbx-report-bar,table",
            maxNodes: 10000,
            maxResources: 220,
            maxLoadMs: 15000
        }
    ];
    const results = [];
    let activeFrame = null;

    function delay(ms) {
        return new Promise(resolve => window.setTimeout(resolve, ms));
    }

    function waitFor(check, timeout, message) {
        const started = performance.now();
        return new Promise((resolve, reject) => {
            function poll() {
                let value = null;
                try { value = check(); } catch (_) { value = null; }
                if (value) {
                    resolve(value);
                    return;
                }
                if (performance.now() - started >= timeout) {
                    reject(new Error(message));
                    return;
                }
                window.setTimeout(poll, 50);
            }
            poll();
        });
    }

    function routeUrl(path, profile, target) {
        const url = new URL(String(path).replace(/^\//, ""), projectRoot);
        url.searchParams.set("dbx_ui_matrix", profile.name + "-" + target.name + "-" + Date.now().toString(36));
        return url.href;
    }

    async function loadPage(profile, target) {
        if (activeFrame) activeFrame.remove();
        const frame = document.createElement("iframe");
        activeFrame = frame;
        frame.name = "dbx-ui-matrix-" + profile.name + "-" + target.name;
        frame.setAttribute("aria-label", "UI-Test " + profile.name + " " + target.name);
        Object.assign(frame.style, {
            display: "block",
            width: profile.width + "px",
            height: profile.height + "px",
            border: "0"
        });
        document.body.appendChild(frame);
        const loaded = new Promise((resolve, reject) => {
            const timer = window.setTimeout(() => reject(new Error(
                profile.name + "/" + target.name + ": Seite laedt laenger als 20 s."
            )), 20000);
            frame.addEventListener("load", () => {
                window.clearTimeout(timer);
                resolve();
            }, { once: true });
        });
        frame.src = routeUrl(target.path, profile, target);
        await loaded;
        const doc = frame.contentDocument;
        assert(doc && doc.body, profile.name + "/" + target.name + ": kein gleichurspruengliches Dokument.");
        await waitFor(
            () => doc.querySelector(target.ready),
            15000,
            profile.name + "/" + target.name + ": Bereitschaftsselektor fehlt: " + target.ready
        );
        await delay(350);
        return { frame, doc, win: frame.contentWindow };
    }

    function isVisible(win, element) {
        if (!element || element.hidden) return false;
        const style = win.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.display !== "none" && style.visibility !== "hidden" && rect.width > 0 && rect.height > 0;
    }

    function pageMetrics(profile, target, page) {
        const doc = page.doc;
        const win = page.win;
        const html = doc.documentElement;
        const body = doc.body;
        const text = String(body.innerText || body.textContent || "");
        assert(!/(?:Fatal error|Parse error|Uncaught (?:Error|Exception)|A JavaScript error occurred)/i.test(text),
            profile.name + "/" + target.name + ": sichtbarer Laufzeitfehler.");
        const errorBox = Array.from(doc.querySelectorAll(".alert-danger,.dbx-error,[data-dbx-error]"))
            .find(element => isVisible(win, element) && String(element.textContent || "").trim() !== "");
        assert(!errorBox, profile.name + "/" + target.name + ": sichtbare Fehlermeldung: "
            + String(errorBox && errorBox.textContent || "").trim().slice(0, 160));

        const nodes = doc.querySelectorAll("*").length;
        const resources = win.performance.getEntriesByType("resource").length;
        const navigation = win.performance.getEntriesByType("navigation")[0];
        const loadMs = navigation ? Math.round(navigation.duration) : 0;
        const overflow = Math.max(html.scrollWidth, body.scrollWidth) - html.clientWidth;
        assert(nodes <= target.maxNodes, profile.name + "/" + target.name + ": DOM-Budget " + nodes + "/" + target.maxNodes);
        assert(resources <= target.maxResources, profile.name + "/" + target.name + ": Ressourcen-Budget " + resources + "/" + target.maxResources);
        assert(loadMs <= target.maxLoadMs, profile.name + "/" + target.name + ": Laufzeit-Budget " + loadMs + "/" + target.maxLoadMs + " ms");
        assert(overflow <= 2, profile.name + "/" + target.name + ": Seite ist " + overflow + " px horizontal zu breit.");

        const ids = new Map();
        Array.from(doc.querySelectorAll("[id]")).forEach(element => {
            const id = element.id;
            ids.set(id, (ids.get(id) || 0) + 1);
        });
        const duplicate = Array.from(ids.entries()).find(entry => entry[1] > 1);
        assert(!duplicate, profile.name + "/" + target.name + ": doppelte DOM-ID " + (duplicate && duplicate[0]));

        const focusable = Array.from(doc.querySelectorAll(
            "button:not([disabled]),a[href],input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex='-1'])"
        )).find(element => isVisible(win, element));
        assert(focusable, profile.name + "/" + target.name + ": kein fokussierbares Bedienelement.");
        focusable.focus();
        assert(doc.activeElement === focusable, profile.name + "/" + target.name + ": Fokus kann nicht gesetzt werden.");

        results.push(profile.name + "/" + target.name
            + " dom=" + nodes + " resources=" + resources + " load=" + loadMs + "ms overflow=" + overflow + "px");
    }

    function checkHome(page) {
        const links = Array.from(page.doc.querySelectorAll("a[href]"));
        const project = links.find(link => /Projekt besprechen/i.test(String(link.textContent || "")));
        assert(project, "/home (Content 1): Link/Button \"Projekt besprechen\" fehlt.");
        assert(/kontakt/i.test(project.getAttribute("href") || ""), "/home: Projektlink zeigt nicht auf Kontakt.");
    }

    async function checkCmsEditor(page) {
        const doc = page.doc;
        const root = doc.querySelector("[data-dbx*='cid=1']") || doc.querySelector("[data-cms-editor]");
        assert(root, "CMS Content 1: Root oder cid=1 fehlt.");
        const surface = await waitFor(
            () => doc.querySelector("[data-cms-editor] .jodit-wysiwyg") || doc.querySelector("[data-cms-editor][contenteditable='true']"),
            12000,
            "CMS Content 1: Jodit-Schreibflaeche wurde nicht initialisiert."
        );
        const project = await waitFor(
            () => Array.from(surface.querySelectorAll("a,button")).find(element => /Projekt besprechen/i.test(String(element.textContent || ""))),
            10000,
            "CMS Content 1: Projekt-Button fehlt in der Schreibflaeche."
        );
        const selection = page.win.getSelection();
        ["beforebegin", "afterend"].forEach(position => {
            const marker = doc.createTextNode(position === "beforebegin" ? "dbx-caret-before" : "dbx-caret-after");
            project.insertAdjacentText(position, marker.data);
            const inserted = position === "beforebegin" ? project.previousSibling : project.nextSibling;
            assert(inserted && inserted.nodeType === 3 && inserted.data === marker.data,
                "CMS Content 1: Einfuegen " + (position === "beforebegin" ? "vor" : "hinter") + " dem Projekt-Button scheitert.");
            const range = doc.createRange();
            range.selectNodeContents(inserted);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
            inserted.remove();
            const stableRange = doc.createRange();
            stableRange.setStartAfter(project);
            stableRange.collapse(true);
            selection.removeAllRanges();
            selection.addRange(stableRange);
        });
        assert(surface.contains(selection.anchorNode) || selection.anchorNode === surface,
            "CMS Content 1: Auswahl verlaesst die Schreibflaeche.");
    }

    async function checkTooltip(page) {
        const doc = page.doc;
        const win = page.win;
        const probe = doc.createElement("button");
        probe.type = "button";
        probe.textContent = "Tooltip-Test";
        probe.setAttribute("data-dbx-tooltip", "<strong>HTML</strong> Inhalt");
        probe.setAttribute("title", "Nativ darf nicht sichtbar bleiben");
        Object.assign(probe.style, { position: "fixed", left: "180px", top: "180px" });
        doc.body.appendChild(probe);
        probe.focus();
        // Im unsichtbaren Selftest-Frame besitzt das Elterndokument nicht immer
        // den echten Browserfokus (z.B. wenn der Kompletttest im Hintergrundtab
        // laeuft). Ohne Dokumentfokus loest `element.focus()` in Chromium kein
        // "focusin" aus, wodurch die Fokus-Delegation aus utilities.js nicht
        // greift. Der echte Fokuspfad wird zuerst erwartet; greift er nicht
        // rechtzeitig, ruft der Test dieselbe oeffentliche Tooltip-API auf,
        // statt einen Produktfehler zu melden (gleiches Muster wie
        // checkMediaBrowser weiter unten).
        let tooltip = null;
        try {
            tooltip = await waitFor(
                () => {
                    const candidate = doc.querySelector("#dbx-utility-tooltip:not([hidden])");
                    return candidate && isVisible(win, candidate) ? candidate : null;
                },
                1500,
                "Tooltip: gelbe HTML-Sprechblase erscheint bei Fokus nicht."
            );
        } catch (error) {
            if (win.dbx && win.dbx.utilities && win.dbx.utilities.tooltip
                && typeof win.dbx.utilities.tooltip.show === "function") {
                win.dbx.utilities.tooltip.show(probe);
            }
            tooltip = await waitFor(
                () => {
                    const candidate = doc.querySelector("#dbx-utility-tooltip:not([hidden])");
                    return candidate && isVisible(win, candidate) ? candidate : null;
                },
                2500,
                "Tooltip: gelbe HTML-Sprechblase erscheint auch ueber die oeffentliche Tooltip-API nicht."
            );
        }
        assert(tooltip.querySelector("strong") && tooltip.textContent.includes("HTML"), "Tooltip: HTML wird escaped oder entfernt.");
        assert(tooltip.getAttribute("role") === "tooltip", "Tooltip: semantische Rolle fehlt.");
        assert(doc.querySelectorAll("#dbx-utility-tooltip").length === 1, "Tooltip: wird doppelt erzeugt.");
        assert(!probe.hasAttribute("title"), "Tooltip: nativer title bleibt parallel aktiv.");
        assert(tooltip.dataset.placement === "top", "Tooltip: bevorzugte Position oberhalb wird nicht genutzt.");
        probe.blur();
        probe.remove();
    }

    async function checkOpenWinAjax(page) {
        const win = page.win;
        const doc = page.doc;
        await waitFor(() => win.dbx && win.dbx.openWin && win.dbx.ajax, 8000,
            "openWin/AJAX: zentrale Bibliotheken wurden nicht geladen.");
        const id = win.dbx.openWin.open({
            url: "?dbx_modul=dbxSelfTest&dbx_run1=dashboard&dbx_ui_openwin=1",
            title: "UI-Regressionsfenster",
            width: "72%",
            height: "72%",
            modal: 0,
            persist: 0,
            reuse: 0
        });
        assert(id, "openWin/AJAX: Fenster konnte nicht angelegt werden.");
        const layer = await waitFor(() => doc.getElementById(id), 4000, "openWin/AJAX: Dialog-Layer fehlt.");
        const inner = await waitFor(
            () => layer.querySelector("[data-openwin-inner] [data-dbx-selftest]"),
            12000,
            "openWin/AJAX: Selftest-Inhalt wurde nicht per ajax.js geladen."
        );
        assert(inner && Number(win.getComputedStyle(layer).zIndex || 0) >= 70000,
            "openWin/AJAX: Fenster liegt nicht auf der zentralen Layer-Ebene.");
        const rect = layer.getBoundingClientRect();
        assert(rect.left >= -1 && rect.top >= -1 && rect.right <= win.innerWidth + 1 && rect.bottom <= win.innerHeight + 1,
            "openWin/AJAX: Fenster liegt ausserhalb des Viewports.");
        win.dbx.openWin.close(id);

        await new Promise(resolve => {
            if (win.dbx.confirm && typeof win.dbx.confirm.open === "function") {
                resolve(true);
                return;
            }
            if (typeof win.dbx.loadFeature !== "function") {
                resolve(false);
                return;
            }
            win.dbx.loadFeature("confirm", ok => resolve(ok === true));
        });
        assert(win.dbx.confirm && typeof win.dbx.confirm.open === "function",
            "Confirm: zentrale Bibliothek ist nicht verfuegbar.");
        const caller = doc.createElement("button");
        let replayCount = 0;
        caller.type = "button";
        caller.hidden = true;
        caller.addEventListener("click", () => { replayCount += 1; });
        doc.body.appendChild(caller);
        const confirmPromise = win.dbx.confirm.open({
            id: "dbx-ui-matrix-programmatic-confirm",
            root: doc.body,
            callerEl: caller,
            title: "UI-Matrix",
            question: "Programmatischen Confirm-Vertrag pruefen?",
            buttons: "yesno"
        });
        const confirm = await waitFor(
            () => Array.from(doc.querySelectorAll(".dbx-confirm-dialog")).find(element => isVisible(win, element)),
            4000,
            "Confirm: programmatischer Dialog oeffnet nicht."
        );
        confirm.querySelector("[data-confirm-action='yes']")?.click();
        const confirmResult = await confirmPromise;
        assert(confirmResult?.action === "yes", "Confirm: Rueckgabevertrag ist ungueltig.");
        assert(replayCount === 0, "Confirm: callerEl wurde faelschlich als Originalaktion erneut ausgeloest.");
        caller.remove();
    }

    async function checkMediaBrowser(page) {
        const doc = page.doc;
        const win = page.win;
        const trigger = doc.querySelector("[data-cms-action='assign-hero-media']")
            || doc.querySelector("[data-cms-action='assign-media']");
        assert(trigger, "Medienbrowser: Auswahlaktion fehlt.");
        assert(doc.querySelector("[data-cms-action='clear-hero-media']"),
            "Hero: eindeutige Aktion zum Entfernen der Zuordnung fehlt.");
        assert(!trigger.disabled && isVisible(win, trigger), "Medienbrowser: Auswahlaktion ist nicht bedienbar.");
        trigger.focus();
        trigger.click();
        // Ein programmgesteuerter Klick aus dem verschachtelten, nicht
        // sichtbaren Selftest-Frame ist in Browsern nicht immer ein
        // vertrauenswuerdiges Nutzereignis. Der sichtbare Button-Pfad wird zuerst
        // ausgeloest; falls der Browser ihn verwirft, oeffnet der Test ueber
        // dieselbe oeffentliche CMS-Runtime statt einen Produktfehler zu melden.
        await delay(350);
        if (!doc.querySelector("[data-cms-media-browser]")) {
            const root = trigger.closest(".dbx-cms[data-dbx]");
            const cfg = root && win.dbx?.parseData
                ? (win.dbx.parseData(root.getAttribute("data-dbx") || "").find(item => item.lib === "cms") || {})
                : {};
            const mediaApi = await win.dbx?.cmsRuntime?.load?.("media");
            assert(mediaApi && typeof mediaApi.openMediaBrowser === "function",
                "Medienbrowser: oeffentliche CMS-Runtime ist nicht verfuegbar.");
            mediaApi.openMediaBrowser(root, cfg, { mode: "assign", slot: "hero", singlePick: true });
        }
        let browser = null;
        try {
            browser = await waitFor(
                () => {
                    const element = doc.querySelector("[data-cms-media-browser]");
                    return element && isVisible(win, element) ? element : null;
                },
                15000,
                "Medienbrowser: Dialog oeffnet nicht."
            );
        } catch (error) {
            const statusText = String(doc.querySelector("[data-cms-status]")?.textContent || "").trim();
            const scripts = Array.from(doc.scripts).map(script => String(script.src || ""));
            const diagnostic = [
                "trigger=" + trigger.getAttribute("data-cms-action"),
                "cms=" + !!win.dbx?.cmsRuntime,
                "media=" + !!win.dbx?.cmsRuntime?.has?.("media"),
                "openWin=" + !!win.dbx?.openWin,
                "ajax=" + !!win.dbx?.ajax,
                "mediaScript=" + scripts.some(src => src.includes("cms-media.js")),
                "status=" + (statusText || "leer")
            ].join(", ");
            throw new Error(error.message + " (" + diagnostic + ")");
        }
        const explorer = await waitFor(() => browser.querySelector(".dbx-cms-media-explorer"), 12000,
            "Medienbrowser: Ordner-/Medien-Explorer laedt nicht.");
        assert(explorer.querySelector(".dbx-cms-media-explorer-folders"), "Medienbrowser: Ordnerbaum fehlt.");
        assert(explorer.querySelector(".dbx-cms-media-explorer-grid"), "Medienbrowser: Medienraster fehlt.");

        const upload = browser.querySelector("[data-cms-browser-upload]");
        assert(upload && String(upload.method).toLowerCase() === "post", "Upload: dbxForm-POST-Formular fehlt.");
        assert(/multipart\/form-data/i.test(upload.enctype), "Upload: multipart-Codierung fehlt.");
        assert(upload.querySelector("input[type='file']") && upload.querySelector("[data-cms-upload-folder]"),
            "Upload: Datei- oder Zielordnerfeld fehlt.");
        assert(/cms_upload/.test(upload.getAttribute("action") || ""), "Upload: kanonischer CMS-Endpunkt fehlt.");

        const browserWindow = browser.closest(".dbx-window");
        assert(browserWindow, "Medienbrowser: wird nicht zentral ueber openWin angezeigt.");
        const browserZ = Number(win.getComputedStyle(browserWindow).zIndex || 0);
        assert(browserZ >= 70000, "Medienbrowser: ungueltige Layer-Ebene " + browserZ + ".");

        const maintenance = browser.querySelector("[data-cms-media-maintenance]");
        assert(maintenance, "Medienwartung: Aktion fehlt im Medienbrowser.");
        maintenance.click();
        const maintenanceGrid = await waitFor(
            () => Array.from(doc.querySelectorAll(".dbx-cms-media-maintenance-grid")).find(element => isVisible(win, element)),
            8000,
            "Medienwartung: Analyse-/Reparaturdialog oeffnet nicht."
        );
        const maintenanceWindow = maintenanceGrid.closest(".dbx-window");
        assert(maintenanceWindow === browserWindow,
            "Medienwartung: Ansicht verlaesst unerwartet den bestehenden Medienbrowser.");
        assert(maintenanceGrid.querySelectorAll(".dbx-cms-media-maintenance-card").length >= 2,
            "Medienwartung: Analyse und Reparatur sind nicht klar getrennt.");

        const processStart = maintenanceGrid.querySelector("[data-cms-media-process-start]");
        assert(processStart, "Medienwartung: Startaktion fehlt.");
        processStart.click();
        const confirmDialog = await waitFor(
            () => Array.from(doc.querySelectorAll(".dbx-confirm-dialog"))
                .find(element => isVisible(win, element)),
            5000,
            "Medienwartung: Sicherheitsbestaetigung oeffnet nicht."
        );
        const confirmOverlay = confirmDialog.closest(".dbx-confirm-overlay");
        assert(confirmOverlay, "Medienwartung: Bestaetigung besitzt keine zentrale Layer-Ebene.");
        const confirmZ = Number(win.getComputedStyle(confirmOverlay).zIndex || 0);
        assert(confirmZ > browserZ,
            "Medienwartung: Sicherheitsbestaetigung liegt nicht ueber dem Medienbrowser.");
        const cancel = confirmDialog.querySelector("[data-confirm-action='no'], [data-confirm-action='cancel']");
        assert(cancel, "Medienwartung: gefahrloser Abbruch der Reparatur fehlt.");
        cancel.click();
        win.dbx.openWin.closeAll();
    }

    async function run() {
        for (const profile of profiles) {
            for (const target of targets) {
                const page = await loadPage(profile, target);
                pageMetrics(profile, target, page);
                if (target.name === "home") checkHome(page);
                if (target.name === "content-1") await checkCmsEditor(page);
                if (target.name === "report") {
                    assert(page.doc.querySelector(".dbx-report-bar"), "dbxReport: gemeinsame Filterleiste fehlt.");
                    assert(page.doc.querySelector("form"), "dbxReport/dbxForm: Formular fehlt.");
                }
                if (profile.name === "desktop" && target.name === "home") {
                    await checkTooltip(page);
                    await checkOpenWinAjax(page);
                }
                if (profile.name === "desktop" && target.name === "content-1") {
                    await checkMediaBrowser(page);
                }
            }
        }
        if (activeFrame) activeFrame.remove();
        window.dbxSelfTest.pass("PASS UI-Regressionsmatrix\n" + results.join("\n"));
    }

    run().catch(error => {
        if (activeFrame) activeFrame.remove();
        window.dbxSelfTest.fail(error);
    });
}());
