(function () {
    "use strict";

    const roots = Array.from(document.querySelectorAll("[data-dbx-selftest]"));
    roots.forEach(init);

    function init(root) {
        if (root.__dbxSelfTestBound) return;
        root.__dbxSelfTestBound = true;

        const state = {
            tests: [],
            testById: new Map(),
            results: new Map(),
            history: [],
            selected: new Set(),
            busy: false,
            stop: false,
            activeRun: null,
            runningTestId: null
        };
        const q = selector => root.querySelector(selector);
        const qa = selector => Array.from(root.querySelectorAll(selector));
        const urls = {
            catalog: root.dataset.catalogUrl,
            start: root.dataset.startUrl,
            execute: root.dataset.executeUrl,
            finish: root.dataset.finishUrl,
            browserResult: root.dataset.browserResultUrl,
            run: root.dataset.runUrl,
            download: root.dataset.downloadUrl
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

        function element(tag, className, text) {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = String(text);
            return node;
        }

        function filteredTests() {
            const term = String(q("[data-selftest-search]")?.value || "").trim().toLowerCase();
            const category = String(q("[data-selftest-category]")?.value || "");
            return state.tests.filter(test => {
                const hay = [test.name, test.category, test.relative_path, test.description].join(" ").toLowerCase();
                return (!term || hay.includes(term)) && (!category || test.category === category);
            });
        }

        function statusBadge(status) {
            const labels = {
                pending: "Offen",
                running: "Läuft",
                passed: "Bestanden",
                failed: "Fehler",
                skipped: "Übersprungen",
                aborted: "Abgebrochen",
                interrupted: "Unterbrochen"
            };
            const classes = {
                pending: "text-bg-secondary",
                running: "text-bg-primary",
                passed: "text-bg-success",
                failed: "text-bg-danger",
                skipped: "text-bg-warning",
                aborted: "text-bg-secondary",
                interrupted: "text-bg-warning"
            };
            return element("span", "badge dbx-selftest-status " + (classes[status] || classes.pending), labels[status] || status);
        }

        function renderCatalog() {
            const host = q("[data-selftest-list]");
            if (!host) return;
            host.replaceChildren();
            const tests = filteredTests();
            if (!tests.length) {
                host.append(element("div", "dbx-selftest-empty", "Keine passenden Tests gefunden."));
                return;
            }

            const table = element("table", "table table-sm table-hover align-middle dbx-selftest-table");
            const thead = document.createElement("thead");
            const headRow = document.createElement("tr");
            ["", "Test", "Bereich", "Profil", "Status", ""].forEach(label => headRow.append(element("th", "", label)));
            thead.append(headRow);
            const tbody = document.createElement("tbody");

            tests.forEach(test => {
                const result = state.results.get(test.id);
                const status = result ? result.status : (state.runningTestId === test.id ? "running" : "pending");
                const row = element("tr", "dbx-selftest-row is-" + status);
                row.dataset.testId = test.id;

                const selectCell = document.createElement("td");
                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.className = "form-check-input";
                checkbox.checked = state.selected.has(test.id);
                checkbox.dataset.selftestSelect = test.id;
                checkbox.setAttribute("aria-label", test.name + " auswählen");
                selectCell.append(checkbox);

                const nameCell = document.createElement("td");
                nameCell.append(element("span", "dbx-selftest-testname", test.name));
                nameCell.append(element("span", "dbx-selftest-path", test.relative_path || test.description));
                const categoryCell = element("td", "", test.category);
                const tierCell = document.createElement("td");
                tierCell.append(element("span", "badge " + (test.tier === "quick" ? "text-bg-info" : "text-bg-light"), test.tier === "quick" ? "Schnell" : "Komplett"));
                const statusCell = document.createElement("td");
                statusCell.append(statusBadge(status));
                const actionCell = document.createElement("td");
                const button = element("button", "btn btn-outline-primary btn-sm", "Einzeltest");
                button.type = "button";
                button.dataset.selftestSingle = test.id;
                button.disabled = state.busy;
                actionCell.append(button);

                [selectCell, nameCell, categoryCell, tierCell, statusCell, actionCell].forEach(cell => row.append(cell));
                tbody.append(row);
            });
            table.append(thead, tbody);
            host.append(table);
        }

        function renderCategories() {
            const select = q("[data-selftest-category]");
            if (!select) return;
            const selected = select.value;
            select.replaceChildren(new Option("Alle Bereiche", ""));
            Array.from(new Set(state.tests.map(test => test.category))).sort().forEach(category => {
                select.append(new Option(category, category));
            });
            select.value = selected;
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
            qa("[data-selftest-single]").forEach(button => button.disabled = busy);
            const stop = q("[data-selftest-stop]");
            if (stop) {
                stop.hidden = !busy;
                stop.disabled = false;
            }
        }

        function appendResult(result) {
            state.results.set(result.test_id, result);
            const wrap = q("[data-selftest-results-wrap]");
            const host = q("[data-selftest-results]");
            if (!wrap || !host) return;
            wrap.hidden = false;
            const details = element("details", "dbx-selftest-result");
            if (result.status === "failed") details.open = true;
            const summary = document.createElement("summary");
            summary.append(statusBadge(result.status));
            summary.append(element("strong", "", result.name));
            summary.append(element("span", "text-muted ms-auto", formatDuration(result.duration_ms)));
            details.append(summary);
            details.append(element("div", "px-3 pb-2 small", result.summary || ""));
            details.append(element("pre", "", result.output || "Keine Ausgabe."));
            host.append(details);
            renderCatalog();
        }

        function renderRunResults(run) {
            state.results.clear();
            const host = q("[data-selftest-results]");
            if (host) host.replaceChildren();
            (run.results || []).forEach(appendResult);
            updateProgress(run);
        }

        function formatDuration(milliseconds) {
            const value = Number(milliseconds || 0);
            return value >= 1000 ? (value / 1000).toFixed(2) + " s" : value + " ms";
        }

        function renderHistory() {
            const host = q("[data-selftest-history]");
            if (!host) return;
            host.replaceChildren();
            if (!state.history.length) {
                host.append(element("div", "dbx-selftest-empty", "Noch keine Testprotokolle vorhanden."));
                return;
            }
            state.history.forEach(run => {
                const totals = run.totals || {};
                const item = element("div", "dbx-selftest-history-item");
                const info = document.createElement("div");
                info.append(element("strong", "", run.profile === "quick" ? "Schnelltest" : "Kompletttest"));
                info.append(element("div", "small text-muted", new Date(run.started_at).toLocaleString() + " · " + formatDuration(run.duration_ms)));
                item.append(info);
                item.append(statusBadge(run.display_status || run.status));
                item.append(element("span", "small", Number(totals.passed || 0) + " bestanden / " + Number(totals.failed || 0) + " Fehler"));
                const actions = element("div", "d-flex gap-1");
                const view = element("button", "btn btn-outline-secondary btn-sm", "Details");
                view.type = "button";
                view.dataset.selftestViewRun = run.id;
                actions.append(view);
                if (run.status === "running") {
                    const resume = element("button", "btn btn-outline-primary btn-sm", "Fortsetzen");
                    resume.type = "button";
                    resume.dataset.selftestResumeRun = run.id;
                    actions.append(resume);
                }
                const download = element("a", "btn btn-outline-secondary btn-sm", "JSON");
                download.href = urls.download + "&run_id=" + encodeURIComponent(run.id);
                actions.append(download);
                item.append(actions);
                host.append(item);
            });
        }

        function executeBrowserTest(test) {
            return new Promise(resolve => {
                const started = performance.now();
                const startedAt = new Date().toISOString();
                const output = [];
                const frame = document.createElement("iframe");
                frame.hidden = true;
                frame.setAttribute("aria-hidden", "true");
                document.body.append(frame);
                const win = frame.contentWindow;
                let done = false;
                let deferred = false;
                const timeoutMs = Math.min(120000, Math.max(5000, Number(test.timeout || 30) * 1000));

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
                const testUrl = new URL(test.relative_path, document.baseURI);
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
                win.dbxSelfTest = {
                    defer: function () { deferred = true; },
                    pass: function (message) { finish("passed", message || "PASS"); },
                    fail: function (error) { finish("failed", error && (error.stack || error.message) || error || "FAIL"); }
                };
                win.__dbxSelfTestReport = function (result) {
                    finish(result && result.status === "passed" ? "passed" : "failed", result && result.output, result && result.timed_out);
                };
                win.onerror = function (message, source, line, column, error) {
                    finish("failed", error && error.stack ? error.stack : String(message) + " (" + line + ":" + column + ")");
                    return true;
                };

                const script = frame.contentDocument.createElement("script");
                script.src = testUrl.href;
                script.onload = function () {
                    setTimeout(() => { if (!deferred) finish("passed"); }, 0);
                };
                script.onerror = function () {
                    finish("failed", "JavaScript-Test konnte nicht geladen oder geparst werden: " + test.relative_path);
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
                    const test = state.testById.get(id);
                    const testName = test ? test.name : "Test";
                    const testStarted = Date.now();
                    state.runningTestId = id;
                    renderCatalog();
                    updateProgress(run, "Läuft: " + testName + " (0 s)");
                    const activityPulse = window.setInterval(() => {
                        const seconds = Math.max(0, Math.floor((Date.now() - testStarted) / 1000));
                        const suffix = seconds >= 15 ? " · umfangreiche Prüfung" : "";
                        const current = q("[data-selftest-current]");
                        if (current) current.textContent = "Läuft: " + testName + " (" + seconds + " s)" + suffix;
                    }, 1000);
                    let data;
                    try {
                        if (test && test.type === "js" && test.execution === "browser") {
                            const browserResult = await executeBrowserTest(test);
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
                        state.runningTestId = null;
                    }
                    run = data.run;
                    state.activeRun = run;
                    appendResult(data.result);
                    updateProgress(run);
                }
                const finished = await request(urls.finish, { run_id: run.id, aborted: state.stop ? 1 : 0 });
                state.activeRun = finished.run;
                state.history = finished.history || state.history;
                updateProgress(finished.run);
                renderHistory();
            } catch (error) {
                const host = q("[data-selftest-results]");
                if (host) host.prepend(element("div", "alert alert-danger", error.message));
                const current = q("[data-selftest-current]");
                if (current) current.textContent = "Lauf unterbrochen: " + error.message;
                renderCatalog();
            } finally {
                setBusy(false);
            }
        }

        async function startRun(profile, ids) {
            if (state.busy) return;
            // Bereits vor dem ersten Netzwerkzugriff sperren. Andernfalls
            // koennen Doppelklicks mehrere identische Laeufe anlegen.
            setBusy(true);
            state.results.clear();
            const host = q("[data-selftest-results]");
            if (host) host.replaceChildren();
            const wrap = q("[data-selftest-results-wrap]");
            if (wrap) wrap.hidden = false;
            try {
                const data = await request(urls.start, { profile: profile, test_ids: ids || [] });
                await executeRun(data.run);
            } catch (error) {
                if (host) host.append(element("div", "alert alert-danger", error.message));
                setBusy(false);
            }
        }

        async function loadRun(id, resume) {
            try {
                const data = await request(urls.run + "&run_id=" + encodeURIComponent(id));
                renderRunResults(data.run);
                if (resume && data.run.status === "running") await executeRun(data.run);
            } catch (error) {
                window.alert(error.message);
            }
        }

        root.addEventListener("click", event => {
            const runButton = event.target.closest("[data-selftest-run]");
            if (runButton) {
                const mode = runButton.dataset.selftestRun;
                if (mode === "selected") {
                    const ids = qa("[data-selftest-select]:checked").map(input => input.dataset.selftestSelect);
                    if (!ids.length) return window.alert("Bitte mindestens einen Test auswählen.");
                    startRun("full", ids);
                } else {
                    startRun(mode === "quick" ? "quick" : "full", []);
                }
                return;
            }
            const single = event.target.closest("[data-selftest-single]");
            if (single) {
                startRun("full", [single.dataset.selftestSingle]);
                return;
            }
            const stop = event.target.closest("[data-selftest-stop]");
            if (stop) {
                state.stop = true;
                stop.disabled = true;
                q("[data-selftest-current]").textContent = "Lauf wird nach dem aktuellen Test beendet …";
                return;
            }
            const view = event.target.closest("[data-selftest-view-run]");
            if (view) loadRun(view.dataset.selftestViewRun, false);
            const resume = event.target.closest("[data-selftest-resume-run]");
            if (resume) loadRun(resume.dataset.selftestResumeRun, true);
        });

        q("[data-selftest-search]")?.addEventListener("input", renderCatalog);
        q("[data-selftest-category]")?.addEventListener("change", renderCatalog);
        q("[data-selftest-select-all]")?.addEventListener("change", event => {
            qa("[data-selftest-select]").forEach(input => {
                input.checked = event.target.checked;
                if (input.checked) state.selected.add(input.dataset.selftestSelect);
                else state.selected.delete(input.dataset.selftestSelect);
            });
        });
        root.addEventListener("change", event => {
            const input = event.target.closest("[data-selftest-select]");
            if (!input) return;
            if (input.checked) state.selected.add(input.dataset.selftestSelect);
            else state.selected.delete(input.dataset.selftestSelect);
        });

        request(urls.catalog).then(data => {
            state.tests = data.tests || [];
            state.testById = new Map(state.tests.map(test => [test.id, test]));
            state.selected = new Set(state.tests.map(test => test.id));
            state.history = data.history || [];
            renderCategories();
            renderCatalog();
            renderHistory();
        }).catch(error => {
            q("[data-selftest-list]").replaceChildren(element("div", "alert alert-danger m-3", error.message));
        });
    }
})();
