/*!
 * dbxapp kiResultWindow.js
 * Oeffnet nach einem erfolgreichen dbxKi-Bundle-Import automatisch ein
 * openWin-Fenster mit der Vorschau (Uebernehmen/Verwerfen), statt das
 * Ergebnis inline auf der Seite zu ersetzen. Nutzt ausschliesslich die
 * oeffentliche dbx.openWin.open()-API - keine Aenderung an openWin.js.
 *
 * Aktivierung: <span data-dbx="lib=kiResultWindow|url=...|title=..." ...></span>
 * irgendwo im per dbxAjax zurueckgegebenen HTML-Fragment.
 *
 * Innerhalb eines so geoeffneten Fensters stehen drei deklarative
 * Klick-Aktionen zur Verfuegung (alle auf `document` delegiert, damit sie
 * auch fuer per AJAX/openWin/process.js nachgeladene Inhalte funktionieren):
 *
 * - data-dbx-ki-discard="{url}"       Job serverseitig aufraeumen (AJAX GET),
 *                                     danach das eigene Fenster schliessen.
 * - data-dbx-ki-inline-action="{url}" URL per AJAX GET laden und das eigene
 *                                     Fensterinnere durch die Antwort
 *                                     ersetzen (bleibt im Fenster, statt wie
 *                                     ein normaler <a href> die ganze Seite
 *                                     zu verlassen). Nach dem Ersetzen wird
 *                                     dbx.scan() auf den neuen Inhalt
 *                                     angewendet (z.B. fuer process.js-Polling).
 * - data-dbx-ki-run-url="{url}" +
 *   data-dbx-ki-run-frame="{iframeId}" Wechselt die src eines iframes im
 *                                     selben Fenster (Sticky-Run-Panel fuer
 *                                     Modul-Vorschauen).
 *
 * Beide AJAX-Aktionen respektieren ein optionales data-confirm="..." (nativer
 * confirm()-Dialog vor der Aktion).
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][kiResultWindow] dbx core missing");
        return;
    }

    const dbx = window.dbx;
    const LIB = "kiResultWindow";

    function closeOwnWindow(el) {
        const win = el.closest(".dbx-window");
        if (!win) return;
        const closeBtn = win.querySelector(".dbx-window-close");
        if (closeBtn) closeBtn.click();
    }

    function confirmed(el) {
        const text = el.getAttribute("data-confirm");
        return !text || window.confirm(text);
    }

    dbx.feature.register(LIB, {
        scope: "element",
        priority: "mid",
        js: [
            ["js", "lib", "ajax.js"],
            ["js", "lib", "openWin.js"]
        ],
        init(el, cfg) {
            if (!el || el.__dbxKiResultWindowReady) return;
            el.__dbxKiResultWindowReady = true;

            const url = (cfg && cfg.url) || "";
            if (!url || !dbx.openWin || typeof dbx.openWin.open !== "function") return;

            dbx.openWin.open({
                url: url,
                title: (cfg && cfg.title) || "KI-Ergebnis",
                width: (cfg && cfg.width) || "1100",
                height: (cfg && cfg.height) || "88%",
                modal: 0,
                minimizable: 1,
                maximizable: 1
            });
        }
    });

    document.addEventListener("click", e => {
        const discardBtn = e.target.closest ? e.target.closest("[data-dbx-ki-discard]") : null;
        if (discardBtn) {
            e.preventDefault();
            const url = discardBtn.getAttribute("data-dbx-ki-discard");
            if (!url || !confirmed(discardBtn)) return;
            discardBtn.setAttribute("disabled", "disabled");
            const finish = () => closeOwnWindow(discardBtn);
            dbx.ajax.request({ url: url, method: "GET", mode: "text" }).then(finish).catch(finish);
            return;
        }

        const inlineBtn = e.target.closest ? e.target.closest("[data-dbx-ki-inline-action]") : null;
        if (inlineBtn) {
            e.preventDefault();
            const url = inlineBtn.getAttribute("data-dbx-ki-inline-action");
            if (!url || !confirmed(inlineBtn)) return;
            const inner = inlineBtn.closest("[data-openwin-inner]") || inlineBtn.closest(".dbx-window-inner");
            if (!inner) {
                window.location.href = url;
                return;
            }
            inlineBtn.setAttribute("disabled", "disabled");
            if (dbx.ajax && typeof dbx.ajax.request === "function") {
                dbx.ajax.request({ url: url, method: "GET", mode: "html" }).then(html => {
                    inner.innerHTML = html;
                    dbx.scan(inner);
                }).catch(() => { inlineBtn.removeAttribute("disabled"); });
            } else {
                window.location.href = url;
            }
            return;
        }

        const runBtn = e.target.closest ? e.target.closest("[data-dbx-ki-run-url]") : null;
        if (runBtn) {
            e.preventDefault();
            const frameId = runBtn.getAttribute("data-dbx-ki-run-frame");
            const url = runBtn.getAttribute("data-dbx-ki-run-url");
            const frame = frameId ? document.getElementById(frameId) : null;
            if (frame && url) frame.src = url;
        }
    });

})(window, document);
