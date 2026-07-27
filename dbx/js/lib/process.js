/*!
 * dbxapp process.js
 * Prozess-UI: Fortschritt, Auto-Tick und Steuerung.
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][process] dbx core missing");
        return;
    }

    const dbx = window.dbx;
    const timers = new WeakMap();

    dbx.process = dbx.process || {};

    function readAttr(el, name, def = "") {
        if (!el || !el.getAttribute) return def;
        const value = el.getAttribute(name);
        return value == null ? def : String(value).trim();
    }

    function bool(value, def = false) {
        if (value === undefined || value === null || value === "") return def;
        if (value === true || value === 1 || value === "1" || value === "on" || value === "true") return true;
        if (value === false || value === 0 || value === "0" || value === "off" || value === "false") return false;
        return def;
    }

    function clampPercent(value) {
        const num = parseInt(value, 10);
        if (Number.isNaN(num)) return 0;
        return Math.max(0, Math.min(100, num));
    }

    function emit(name, data) {
        if (dbx.event && typeof dbx.event.emit === "function") {
            dbx.event.emit(name, data || {});
        }
    }

    function status(root) {
        return readAttr(root, "data-process-status", "running").toLowerCase();
    }

    function isWaiting(root) {
        return ["paused", "canceled", "finished", "error"].includes(status(root));
    }

    function clearTimer(root) {
        const timer = timers.get(root);
        if (timer) {
            window.clearTimeout(timer);
            timers.delete(root);
        }
    }

    function setBusy(root, busy) {
        if (!root || !root.classList) return;
        root.classList.toggle("is-loading", !!busy);
        root.setAttribute("aria-busy", busy ? "true" : "false");
    }

    function setProgress(root, name, value) {
        const percent = clampPercent(value);
        const bar = root.querySelector("[data-process-bar='" + name + "']");
        const label = root.querySelector("[data-process-percent='" + name + "']");

        if (bar) {
            bar.style.width = percent + "%";
            bar.setAttribute("aria-valuenow", String(percent));
            if (bar.parentElement && bar.parentElement.getAttribute("role") === "progressbar") {
                bar.parentElement.setAttribute("aria-valuenow", String(percent));
            }
        }

        if (label) {
            label.textContent = percent + "%";
        }
    }

    function syncUi(root) {
        setProgress(root, "overall", readAttr(root, "data-process-percent", "0"));
        setProgress(root, "step", readAttr(root, "data-process-step-percent", "0"));

        const currentStatus = status(root);
        root.querySelectorAll("[data-process-visible]").forEach(el => {
            const list = readAttr(el, "data-process-visible")
                .split(",")
                .map(item => item.trim().toLowerCase())
                .filter(Boolean);
            el.hidden = list.length ? !list.includes(currentStatus) : false;
        });
    }

    function requestHtml(url) {
        if (!url) return Promise.reject(new Error("missing_url"));

        if (dbx.ajax && typeof dbx.ajax.request === "function") {
            return dbx.ajax.request({
                url: url,
                method: "GET",
                mode: "html",
                timeout: 30000
            });
        }

        return Promise.reject(new Error("ajax.js nicht geladen."));
    }

    function findReplacement(root, html) {
        const tpl = document.createElement("template");
        tpl.innerHTML = String(html || "").trim();

        if (!tpl.content.childNodes.length) return null;

        let next = null;

        if (root.id) {
            const id = (window.CSS && CSS.escape)
                ? CSS.escape(root.id)
                : root.id.replace(/([^A-Za-z0-9_-])/g, "\\$1");
            next = tpl.content.querySelector("#" + id);
        }

        if (!next) {
            next = tpl.content.querySelector("[data-dbx-process-root='1'], .dbx-process");
        }

        if (!next) {
            next = tpl.content.firstElementChild;
        }

        return next;
    }

    function replaceRoot(root, html) {
        const next = findReplacement(root, html);
        if (!next) {
            root.innerHTML = String(html || "");
            syncUi(root);
            return root;
        }

        root.replaceWith(next);

        if (dbx.scan) {
            dbx.scan(next);
        }

        return next;
    }

    function loadIntoRoot(root, url, reason) {
        clearTimer(root);
        setBusy(root, true);

        emit("process:before", {
            root: root,
            url: url,
            reason: reason || "tick"
        });

        return requestHtml(url)
            .then(html => {
                const next = replaceRoot(root, html);
                emit("process:after", {
                    root: next,
                    url: url,
                    reason: reason || "tick"
                });
                return next;
            })
            .catch(err => {
                dbx.warn("[process] request failed", err);
                root.classList.add("has-error");
                const msg = root.querySelector("[data-process-message]");
                if (msg) msg.textContent = "Prozess-Anfrage fehlgeschlagen.";
                return root;
            })
            .finally(() => setBusy(root, false));
    }

    function actionUrl(root, action) {
        return readAttr(root, "data-process-" + action + "-url", "");
    }

    function ensureConfirm() {
        if (dbx.confirm && typeof dbx.confirm.open === "function") {
            return Promise.resolve(true);
        }

        if (typeof dbx.resolveFeature !== "function") {
            return Promise.resolve(false);
        }

        return new Promise(resolve => {
            dbx.resolveFeature("confirm", ok => {
                resolve(ok === true && dbx.confirm && typeof dbx.confirm.open === "function");
            });
        });
    }

    function confirmAction(root, action) {
        if (action !== "cancel" && action !== "restart") {
            return Promise.resolve(true);
        }

        const title = action === "cancel" ? "Prozess abbrechen" : "Prozess neu starten";
        const question = action === "cancel"
            ? "Diesen Prozess abbrechen?"
            : "Diesen Prozess neu starten?";

        return ensureConfirm().then(ok => {
            if (!ok) {
                dbx.warn("[process] confirm.js konnte nicht geladen werden; Aktion abgebrochen.");
                return false;
            }

            return dbx.confirm.open({
                id: "process-" + action + "-" + Date.now(),
                root: root,
                title: title,
                question: question,
                buttons: "yesno",
                labelyes: "<i class='bi bi-check-lg'></i> Ja",
                labelno: "<i class='bi bi-x-lg'></i> Nein"
            }).then(result => result && result.action === "yes");
        });
    }

    function runAction(root, action) {
        action = String(action || "").toLowerCase();
        if (!action || root.__dbxProcessBusy) return;

        const url = actionUrl(root, action);
        if (!url) return;

        confirmAction(root, action).then(ok => {
            if (!ok) return;

            root.__dbxProcessBusy = true;
            loadIntoRoot(root, url, action).finally(() => {
                root.__dbxProcessBusy = false;
            });
        });
    }

    function schedule(root, cfg) {
        clearTimer(root);
        if (isWaiting(root)) return;

        const auto = bool(readAttr(root, "data-process-autostart", cfg.autostart), true);
        if (!auto) return;

        const url = readAttr(root, "data-process-next-url", cfg.url || "");
        if (!url) return;

        const interval = Math.max(250, parseInt(readAttr(root, "data-process-interval", cfg.interval || "800"), 10) || 800);

        const timer = window.setTimeout(function () {
            loadIntoRoot(root, url, "tick");
        }, interval);

        timers.set(root, timer);
    }

    dbx.process.init = function (root, cfg) {
        if (!root) return;

        root.setAttribute("data-dbx-process-root", "1");
        syncUi(root);

        if (root.__dbxProcessBound !== true) {
            root.__dbxProcessBound = true;
            root.addEventListener("click", function (e) {
                const btn = e.target.closest("[data-process-action]");
                if (!btn || !root.contains(btn)) return;

                e.preventDefault();
                runAction(root, readAttr(btn, "data-process-action"));
            });
        }

        schedule(root, cfg || {});
    };

    dbx.process.refresh = function (root) {
        if (!root) return Promise.resolve(null);
        const url = readAttr(root, "data-process-next-url", "");
        return loadIntoRoot(root, url, "refresh");
    };

    dbx.feature.register("process", {
        scope: "element",
        priority: "mid",
        css: [
            ["css", "design", "c-process.css"]
        ],
        js: [
            ["js", "lib", "ajax.js"]
        ],
        init(el, cfg) {
            if (!el) return;
            dbx.process.init(el, cfg || {});
        },
        destroy(el) {
            clearTimer(el);
        }
    });

})(window, document);
