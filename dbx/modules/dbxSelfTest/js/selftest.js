(function () {
    "use strict";

    const roots = Array.from(document.querySelectorAll("[data-dbx-selftest]"));
    roots.forEach(init);

    function init(root) {
        if (root.__dbxSelfTestBound) return;
        root.__dbxSelfTestBound = true;

        const state = {
            busy: false,
            stop: false,
            activeRun: null
        };
        const q = selector => root.querySelector(selector);
        const qa = selector => Array.from(root.querySelectorAll(selector));
        const urls = {
            start: root.dataset.startUrl,
            execute: root.dataset.executeUrl,
            finish: root.dataset.finishUrl,
            browserResult: root.dataset.browserResultUrl
        };

        async function request(url, payload) {
            const options = payload === undefined ? {} : {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            };
            const response = await fetch(url, options);
            const raw = await response.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch (_) {
                const detail = raw
                    .replace(/<[^>]*>/g, " ")
                    .replace(/&nbsp;/gi, " ")
                    .replace(/\s+/g, " ")
                    .trim()
                    .slice(0, 500);
                data = {
                    ok: 0,
                    error: "Serverantwort ist kein JSON" + (detail ? ": " + detail : " (HTTP " + response.status + ").")
                };
            }
            if (!response.ok || !data || !data.ok) {
                throw new Error((data && (data.error || data.msg)) || ("HTTP " + response.status));
            }
            return data;
        }


        function escapeHtml(value) {
            const node = document.createElement("span");
            node.textContent = String(value == null ? "" : value);
            return node.innerHTML;
        }

        function formatDuration(milliseconds) {
            const value = Number(milliseconds || 0);
            return value >= 1000 ? (value / 1000).toFixed(2) + " s" : value + " ms";
        }

        function statusBadgeHtml(status) {
            const labels = {
                pending: "Offen",
                running: "Läuft",
                passed: "Bestanden",
                failed: "Fehlgeschlagen",
                skipped: "Übersprungen",
                aborted: "Abgebrochen"
            };
            const classes = {
                pending: "text-bg-secondary",
                running: "text-bg-primary",
                passed: "text-bg-success",
                failed: "text-bg-danger",
                skipped: "text-bg-secondary",
                aborted: "text-bg-secondary"
            };
            return '<span class="badge ' + (classes[status] || classes.pending) + '">'
                + escapeHtml(labels[status] || status) + "</span>";
        }

        function testMeta(id) {
            const statusHost = q('[data-selftest-status-for="' + CSS.escape(id) + '"]');
            const nameEl = statusHost && statusHost.closest("tr")?.querySelector(".dbx-selftest-testname");
            if (!nameEl) return null;
            return {
                id: id,
                name: nameEl.textContent || id,
                execution: nameEl.dataset.testExecution || "server",
                relativePath: nameEl.dataset.testPath || "",
                timeout: Number(nameEl.dataset.testTimeout || 30)
            };
        }

        function updateRowStatus(id, status, durationMs) {
            const statusHost = q('[data-selftest-status-for="' + CSS.escape(id) + '"]');
            if (statusHost) statusHost.innerHTML = statusBadgeHtml(status);
            const durationHost = q('[data-selftest-duration-for="' + CSS.escape(id) + '"]');
            if (durationHost && durationMs !== undefined) durationHost.textContent = formatDuration(durationMs);
            const row = statusHost && statusHost.closest("tr");
            if (row) row.className = row.className.replace(/\bis-\S+/g, "").trim() + " is-" + status;
        }

        function updateProgress(run, currentName) {
            const box = q("[data-selftest-progress]");
            if (!box || !run) return;
            box.hidden = false;
            const totals = run.totals || {};
            const total = Number(totals.total || (run.test_ids || []).length || 0);
            const completed = Number(totals.completed || 0);
            const percent = total ? Math.round((completed / total) * 100) : 0;
            q("[data-selftest-progress-count]").textContent = completed + " / " + total;
            q("[data-selftest-progress-bar]").style.width = percent + "%";
            q("[data-selftest-current]").textContent = currentName || (run.status === "running" ? "Nächster Test wird vorbereitet …" : runStatusLabel(run.status));
            q("[data-selftest-progress-title]").textContent = run.profile === "quick" ? "Schnelltest" : "Kompletttest";
        }

        function runStatusLabel(status) {
            return ({ passed: "Alle Tests bestanden.", failed: "Testlauf mit Fehlern abgeschlossen.", aborted: "Testlauf abgebrochen.", running: "Testlauf läuft." })[status] || status;
        }

        function setBusy(busy) {
            state.busy = busy;
            qa("[data-selftest-run]").forEach(button => button.disabled = busy);
            const stop = q("[data-selftest-stop]");
            if (stop) {
                stop.hidden = !busy;
                stop.disabled = false;
            }
        }

        function renderRunSummary(run) {
            const host = q("[data-selftest-run-summary]");
            const text = q("[data-selftest-run-summary-text]");
            if (!host || !text || !run) return;
            const totals = run.totals || {};
            const passed = Number(totals.passed || 0);
            const failed = Number(totals.failed || 0);
            const skipped = Number(totals.skipped || 0);
            const dateText = new Date(run.finished_at || run.started_at).toLocaleString();
            let line = "Letzter Test am " + dateText + " — " + passed + " bestanden / " + failed + " nicht bestanden";
            if (skipped) line += " / " + skipped + " übersprungen";
            host.className = "dbx-selftest-summary-bar alert " + (failed > 0 ? "alert-danger" : "alert-success")
                + " d-flex align-items-center justify-content-between flex-wrap gap-2";
            text.innerHTML = '<i class="bi ' + (failed > 0 ? "bi-exclamation-triangle" : "bi-check-circle") + '" aria-hidden="true"></i> '
                + escapeHtml(line);
        }

        function executeBrowserTest(test) {
            return new Promise(resolve => {
                const started = performance.now();
                const startedAt = new Date().toISOString();
                const output = [];
                const frame = document.createElement("iframe");
                // Browser-Tests, die reale Geometrie, Fokus und Layer pruefen,
                // brauchen eine gerenderte Viewport-Flaeche. Das Testdokument
                // bleibt ausserhalb des sichtbaren Bereichs, nimmt aber normal
                // am Layout teil; `hidden` wuerde alle Rechtecke auf 0 setzen.
                frame.className = "dbx-selftest-browser-stage";
                frame.setAttribute("aria-hidden", "true");
                frame.tabIndex = -1;
                Object.assign(frame.style, {
                    position: "fixed",
                    left: "-20000px",
                    top: "0",
                    width: "1440px",
                    height: "1000px",
                    border: "0",
                    opacity: "0",
                    pointerEvents: "none"
                });
                document.body.append(frame);
                const win = frame.contentWindow;
                let done = false;
                let deferred = false;
                const timeoutMs = Math.min(240000, Math.max(5000, Number(test.timeout || 30) * 1000));

                function serialize(value) {
                    if (typeof value === "string") return value;
                    try { return JSON.stringify(value); } catch (_) { return String(value); }
                }

                function finish(status, message, timedOut) {
                    if (done) return;
                    done = true;
                    clearTimeout(timer);
                    if (message) output.push(String(message));
                    frame.remove();
                    resolve({
                        status: status,
                        output: output.join("\n") || (status === "passed" ? "PASS Browser-JavaScript-Test" : "FAIL Browser-JavaScript-Test"),
                        duration_ms: Math.round(performance.now() - started),
                        started_at: startedAt,
                        timed_out: timedOut ? 1 : 0
                    });
                }

                const timer = setTimeout(() => finish("failed", "Zeitlimit des Browser-Tests überschritten.", true), timeoutMs);
                const testUrl = new URL(test.relativePath, document.baseURI);
                testUrl.searchParams.set("dbx_selftest", Date.now().toString(36));
                const directoryUrl = new URL("./", testUrl);

                win.__dirname = directoryUrl.href;
                win.require = function (name) {
                    if (name === "path") {
                        return {
                            resolve: function () {
                                const parts = Array.from(arguments);
                                const base = parts.shift() || directoryUrl.href;
                                return new URL(parts.join("/"), String(base).replace(/\/?$/, "/")).href;
                            }
                        };
                    }
                    if (name === "fs") {
                        return {
                            readFileSync: function (url) {
                                const xhr = new XMLHttpRequest();
                                xhr.open("GET", String(url), false);
                                xhr.send(null);
                                if (xhr.status < 200 || xhr.status >= 300) {
                                    throw new Error("Testquelle konnte nicht geladen werden: " + url + " (HTTP " + xhr.status + ")");
                                }
                                return xhr.responseText;
                            }
                        };
                    }
                    throw new Error("Browser-Test unterstützt require(\"" + name + "\") nicht.");
                };
                win.console = {
                    log: function () { output.push(Array.from(arguments).map(serialize).join(" ")); },
                    info: function () { output.push(Array.from(arguments).map(serialize).join(" ")); },
                    warn: function () { output.push("WARN: " + Array.from(arguments).map(serialize).join(" ")); },
                    error: function () { output.push("ERROR: " + Array.from(arguments).map(serialize).join(" ")); }
                };
                function describeError(error) {
                    if (!error) return "FAIL";
                    const message = error.message || String(error);
                    return error.stack ? message + "\n" + error.stack : message;
                }
                win.dbxSelfTest = {
                    defer: function () { deferred = true; },
                    pass: function (message) { finish("passed", message || "PASS"); },
                    fail: function (error) { finish("failed", describeError(error)); }
                };
                win.__dbxSelfTestReport = function (result) {
                    finish(result && result.status === "passed" ? "passed" : "failed", result && result.output, result && result.timed_out);
                };
                win.onerror = function (message, source, line, column, error) {
                    finish("failed", error ? describeError(error) : String(message) + " (" + line + ":" + column + ")");
                    return true;
                };

                const script = frame.contentDocument.createElement("script");
                script.src = testUrl.href;
                script.onload = function () {
                    setTimeout(() => { if (!deferred) finish("passed"); }, 0);
                };
                script.onerror = function () {
                    finish("failed", "JavaScript-Test konnte nicht geladen oder geparst werden: " + test.relativePath);
                };
                frame.contentDocument.head.append(script);
            });
        }

        async function executeRun(run) {
            state.activeRun = run;
            state.stop = false;
            setBusy(true);
            const completed = new Set((run.results || []).map(result => result.test_id));
            try {
                for (const id of run.test_ids || []) {
                    if (completed.has(id)) continue;
                    if (state.stop) break;
                    const meta = testMeta(id);
                    const testName = meta ? meta.name : id;
                    const testStarted = Date.now();
                    updateRowStatus(id, "running");
                    updateProgress(run, "Läuft: " + testName + " (0 s)");
                    const activityPulse = window.setInterval(() => {
                        const seconds = Math.max(0, Math.floor((Date.now() - testStarted) / 1000));
                        const suffix = seconds >= 15 ? " · umfangreiche Prüfung" : "";
                        const current = q("[data-selftest-current]");
                        if (current) current.textContent = "Läuft: " + testName + " (" + seconds + " s)" + suffix;
                    }, 1000);
                    let data;
                    try {
                        if (meta && meta.execution === "browser") {
                            const browserResult = await executeBrowserTest(meta);
                            data = await request(urls.browserResult, {
                                run_id: run.id,
                                test_id: id,
                                result: browserResult
                            });
                        } else {
                            data = await request(urls.execute, { run_id: run.id, test_id: id });
                        }
                    } finally {
                        window.clearInterval(activityPulse);
                    }
                    run = data.run;
                    state.activeRun = run;
                    updateRowStatus(id, data.result.status, data.result.duration_ms);
                    updateProgress(run);
                }
                const finished = await request(urls.finish, { run_id: run.id, aborted: state.stop ? 1 : 0 });
                state.activeRun = finished.run;
                updateProgress(finished.run);
                renderRunSummary(finished.run);
            } catch (error) {
                const current = q("[data-selftest-current]");
                if (current) current.textContent = "Lauf unterbrochen: " + error.message;
                window.alert(error.message);
            } finally {
                setBusy(false);
            }
        }

        async function startRun(profile, ids) {
            if (state.busy) return;
            // Bereits vor dem ersten Netzwerkzugriff sperren. Andernfalls
            // koennen Doppelklicks mehrere identische Laeufe anlegen.
            setBusy(true);
            try {
                const data = await request(urls.start, { profile: profile, test_ids: ids || [] });
                await executeRun(data.run);
            } catch (error) {
                window.alert(error.message);
                setBusy(false);
            }
        }

        root.addEventListener("click", event => {
            const runButton = event.target.closest("[data-selftest-run]");
            if (runButton) {
                const mode = runButton.dataset.selftestRun;
                if (mode === "selected") {
                    const ids = qa(".cb-row-select:checked").map(input => input.dataset.reportRid);
                    if (!ids.length) return window.alert("Bitte mindestens einen Test auswählen.");
                    startRun("full", ids);
                } else {
                    startRun(mode === "quick" ? "quick" : "full", []);
                }
                return;
            }
            const stop = event.target.closest("[data-selftest-stop]");
            if (stop) {
                state.stop = true;
                stop.disabled = true;
                q("[data-selftest-current]").textContent = "Lauf wird nach dem aktuellen Test beendet …";
                return;
            }
        });
    }
})();
