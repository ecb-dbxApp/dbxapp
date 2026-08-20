/*!
 * dbxapp grid-export.js - lazy export dependencies
 */
(function (window) {
    "use strict";

    const feature = window.dbx && window.dbx.feature && window.dbx.feature._features.grid;
    if (!feature) {
        console.error('[dbx][grid] feature missing before runtime extension');
        return;
    }

    Object.assign(feature, {
        _loadRootScript(file, done) {

            window.dbxGridExportDeps = window.dbxGridExportDeps || {};

            const state = window.dbxGridExportDeps[file] || { status: 'new', callbacks: [] };
            window.dbxGridExportDeps[file] = state;

            if (state.status === 'loaded') {
                done && done(true);
                return;
            }

            if (state.status === 'loading') {
                state.callbacks.push(done);
                return;
            }

            state.status = 'loading';
            state.callbacks = done ? [done] : [];

            let url = dbx.config.rootPath + file;

            const searchParams = new URLSearchParams(location.search);
            const cacheBust = searchParams.get('dbx_nocache') || searchParams.get('cachebust');
            if (cacheBust) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'dbx_nocache=' + encodeURIComponent(cacheBust);
            }

            const finish = (ok) => {

                state.status = ok ? 'loaded' : 'error';

                state.callbacks.forEach(cb => cb && cb(ok === true));
                state.callbacks = [];
            };

            if (!dbx.ajax || typeof dbx.ajax.request !== 'function') {
                dbx.error('[grid] ajax.js is required for export dependency loading', url);
                finish(false);
                return;
            }

            dbx.ajax.request({
                url: url,
                method: 'GET',
                mode: 'text',
                timeout: 30000
            }).then(source => {
                try {
                    const run = new Function(
                        'window',
                        'self',
                        'globalThis',
                        'global',
                        'exports',
                        'module',
                        'define',
                        String(source || '') + '\n//# sourceURL=' + url
                    );

                    run.call(window, window, window, window, window, undefined, undefined, undefined);
                    finish(true);
                } catch (e) {
                    dbx.error('[grid] export dependency load failed', url, e);
                    finish(false);
                }
            }).catch(error => {
                dbx.error('[grid] export dependency load failed', url, error);
                finish(false);
            });
        },

        _waitForExportDep(check, done, attempts) {

            const maxAttempts = attempts || 20;

            if (check()) {
                done && done(true);
                return;
            }

            if (maxAttempts <= 0) {
                done && done(false);
                return;
            }

            window.setTimeout(() => {
                this._waitForExportDep(check, done, maxAttempts - 1);
            }, 50);
        },

        _setTabulatorDependency(table, key, value) {

            if (!table || !table.dependencyRegistry || !value) return false;

            table.dependencyRegistry.deps = table.dependencyRegistry.deps || {};
            table.dependencyRegistry.deps[key] = value;

            return true;
        },

        _ensureExcelExportDeps(table, done) {

            if (window.XLSX) {
                done && done(this._setTabulatorDependency(table, 'XLSX', window.XLSX));
                return;
            }

            this._loadRootScript('add_ons/tabulator-deps/xlsx.full.min.js', (ok) => {
                if (ok !== true) {
                    done && done(false);
                    return;
                }

                this._waitForExportDep(
                    () => !!window.XLSX,
                    (ready) => {
                        done && done(ready === true && this._setTabulatorDependency(table, 'XLSX', window.XLSX));
                    }
                );
            });
        },

        _ensurePdfExportDeps(table, done) {

            const hasAutoTable = () => !!(
                window.jspdf &&
                window.jspdf.jsPDF &&
                window.jspdf.jsPDF.API &&
                window.jspdf.jsPDF.API.autoTable
            );

            if (hasAutoTable()) {
                done && done(this._setTabulatorDependency(table, 'jspdf', window.jspdf));
                return;
            }

            const loadAutoTable = () => {
                this._loadRootScript('add_ons/tabulator-deps/jspdf.plugin.autotable.min.js', (ok) => {
                    if (ok !== true) {
                        done && done(false);
                        return;
                    }

                    this._waitForExportDep(
                        hasAutoTable,
                        (ready) => {
                            done && done(ready === true && this._setTabulatorDependency(table, 'jspdf', window.jspdf));
                        }
                    );
                });
            };

            if (window.jspdf && window.jspdf.jsPDF) {
                loadAutoTable();
                return;
            }

            this._loadRootScript('add_ons/tabulator-deps/jspdf.umd.min.js', (ok) => {
                if (ok !== true || !(window.jspdf && window.jspdf.jsPDF)) {
                    done && done(false);
                    return;
                }

                loadAutoTable();
            });
        },

    });
})(window);
