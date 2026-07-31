/**
 * ============================================================
 * DBX GRID – INVARIANTEN (UNVERLETZBAR)
 * ============================================================
 *
 * Diese Regeln definieren das unveränderliche Verhalten des Grids.
 * Sie gelten IMMER – unabhängig von Features, Bugfixes oder Refactorings.
 *
 * ------------------------------------------------------------
 * INVARIANTE 1: EDIT ≠ SORT
 * ------------------------------------------------------------
 * - Eine Datenänderung (cellEdited, Save, Autosave)
 *   darf NIEMALS eine Sortierung auslösen oder verändern.
 * - Sortierung ändert sich ausschließlich durch:
 *   - explizite User-Aktion (Header-Klick)
 *   - expliziten Restore beim Reload
 *   - explizite Remote-Neulieferung durch Server
 * - Kein implizites Re-Sort durch row.update(), reactiveData o.ä.
 *
 * ------------------------------------------------------------
 * INVARIANTE 2: RELOAD DARF KEINE DATEN VERLIEREN
 * ------------------------------------------------------------
 * - Nach Reload dürfen keine Zeilen verschwinden.
 * - Auch nicht bei:
 *   - veraltetem Sort-State
 *   - geänderten Spalten / Schema
 *   - kaputtem Layout-State
 * - Im Zweifel:
 *   - Sort verwerfen
 *   - Layout best-effort anwenden
 *   - Default anzeigen
 *
 * ------------------------------------------------------------
 * INVARIANTE 3: PERSISTENTER STATE IST OPTIONAL
 * ------------------------------------------------------------
 * - Gespeicherter State (Layout, Sort, Height, Pagination)
 *   ist immer hilfreich,
 *   aber niemals verpflichtend.
 * - Ungültiger oder inkompatibler State wird ignoriert oder bereinigt.
 * - Persistenz darf UX niemals verschlechtern.
 *
 * ------------------------------------------------------------
 * INVARIANTE 4: SYSTEM-SPALTEN SIND HEILIG
 * ------------------------------------------------------------
 * - System-Spalten (z.B. _actions, _rownum, _*)
 *   sind NICHT Teil des User-Layouts.
 * - Sie dürfen:
 *   - nicht gespeichert
 *   - nicht sortiert
 *   - nicht verschoben
 *   - nicht ausgeblendet
 *   werden.
 * - User-State darf System-Spalten niemals beeinflussen.
 *
 * ------------------------------------------------------------
 * INVARIANTE 5: NUR USER-AKTIONEN SIND SICHTBAR
 * ------------------------------------------------------------
 * - Systeminterne Aktionen (Save, Autosave, Restore, Sync)
 *   dürfen keine sichtbaren Layout-, Sort- oder UI-Sprünge erzeugen.
 * - Wenn der User nichts geklickt hat,
 *   darf sich visuell nichts „magisch“ verändern.
 *
 * ------------------------------------------------------------
 * INVARIANTE 6: DEFENSIVES RESTORE
 * ------------------------------------------------------------
 * - Restore ist immer best-effort.
 * - Unbekannte Spalten, Sort-Felder oder States
 *   werden verworfen oder auf Default gesetzt.
 * - Der User darf jederzeit sauber neu sortieren oder anordnen.
 *
 * ============================================================
 * MERKSATZ:
 * Das Grid darf NIE überraschen.
 * Vorhersehbares Verhalten ist wichtiger als Feature-Vollständigkeit.
 * ============================================================
 */


/**
 * dbx grid feature (Tabulator)
 * -------------------------------------------------
 * requires: core.js (dbx namespace + loader)
 * -------------------------------------------------
 */

(function() {
    window.dbxGrid = window.dbxGrid || {};

    if (!window.dbx || !dbx.feature) {
        console.error('[dbx][grid] dbx core missing');
        return;
    }

    dbx.feature.register('grid', {

        prio: 'mid',

        css: [
            ['css','root','add_ons/tabulator/dist/css/tabulator.min.css'],
            ['css','design','c-grid.css']
        ],

        js: [
            ['js','lib','ajax.js'],
            ['js','root','add_ons/tabulator/dist/js/tabulator.min.js']
        ],

        scope: 'element',


        /* =========================================================
         * SCHEMA AUTOLOAD (design/js/<schema>.js)
         * ========================================================= */
        loadSchema(schemaName, done) {

            if (
                window.dbxGridSchema &&
                window.dbxGridSchema[schemaName]
            ) {
                done();
                return;
            }

            const url =
                dbx.config.rootPath +
                'design/' +
                dbx.getDesign() +
                '/js/' +
                schemaName +
                '.js';

            dbx.log('[grid][schema] load', url);

            dbx.loader.js(url, () => {
                if (
                    window.dbxGridSchema &&
                    window.dbxGridSchema[schemaName]
                ) {
                    done();
                } else {
                    dbx.error('[grid][schema] loaded but not registered:', schemaName);
                }
            });
        },


        /* =========================================================
         * INIT
         * ========================================================= */
        init(el, cfg) {

            if (typeof window.Tabulator === "undefined") {
                alert(
                    "[DBX ERROR]\n" +
                    "Missing dependency: Tabulator\n\n" +
                    "lib=grid\n" +
                    "id=" + (cfg.id || "undef") + "\n\n" +
                    "Check PREPARE js loading."
                );
                dbx.error("Tabulator missing");
                return;
            }

            const heightRaw = String(cfg.height ?? '').trim().toLowerCase();
            const autoHeight = (heightRaw === '' || heightRaw === 'auto' || heightRaw === 'content');
            const height = autoHeight ? false : (parseInt(cfg.height, 10) || 400);
            const minHeight = cfg.minheight ? parseInt(cfg.minheight, 10) : false;
            const maxHeight = cfg.maxheight ? parseInt(cfg.maxheight, 10) : false;

            const colsDef = cfg.cols || '';

            const allowDelete = ((cfg.allowdelete ?? cfg.allowDelete ?? '1') == '1') && !!cfg.delete;
            const allowEdit   = ((cfg.allowedit   ?? cfg.allowEdit   ?? '1') == '1') && !!cfg.save;
            const allowInsert = ((cfg.allowinsert ?? cfg.allowInsert ?? '1') == '1') && !!cfg.insert;

            const headerFilter = this._bool(cfg.headerfilter ?? cfg.headerFilter ?? 1, true);
            const headerSort   = this._bool(cfg.headersort ?? cfg.headerSort ?? 1, true);

            const headerFilterLiveFilter = this._bool(cfg.headerfilterlivefilter ?? cfg.headerFilterLiveFilter ?? 1, true);
            const headerFilterPlaceholder = String(cfg.headerfilterplaceholder ?? cfg.headerFilterPlaceholder ?? '');

            const pagination      = this._bool(cfg.pagination ?? 0, false);
            const paginationMode  = String(cfg.paginationmode || cfg.paginationMode || 'local').toLowerCase();
            const progressiveLoad = String(cfg.progressiveload || cfg.progressiveLoad || '').toLowerCase();
            const pageSize        = parseInt(cfg.pagesize ?? cfg.paginationSize ?? 15, 10) || 15;

            const paginationSizeSelector = this._parsePaginationSizeSelector(
                cfg.pagesizeselector ?? cfg.paginationSizeSelector ?? false
            );

            const paginationButtonCount = this._int(
                cfg.paginationbuttoncount ?? cfg.paginationButtonCount ?? 5,
                5
            );

            const paginationCounter = this._normalizePaginationCounter(
                cfg.paginationcounter ?? cfg.paginationCounter ?? false
            );

            const paginationAddRow = String(
                cfg.paginationaddrow ?? cfg.paginationAddRow ?? 'page'
            ).toLowerCase() === 'table' ? 'table' : 'page';

            const paginationOutOfRange = this._normalizePaginationOutOfRange(
                cfg.paginationoutofrange ?? cfg.paginationOutOfRange ?? false
            );

            const paginationControls = this._bool(
                cfg.paginationcontrols ?? cfg.paginationControls ?? 1,
                true
            );

            const syncMode        = String(cfg.syncmode || 'delta').toLowerCase();
            const searchMode      = String(cfg.searchmode || 'local').toLowerCase();
            const syncRun         = this._bool(cfg.sync_run ?? cfg.syncrun ?? 1, true);
            const syncLed         = this._bool(cfg.sync_led ?? cfg.syncled ?? 1, true);

            const responsiveLayoutRaw = String(cfg.responsivelayout ?? cfg.responsiveLayout ?? '').toLowerCase().trim();
            const responsiveLayout =
                (!responsiveLayoutRaw || responsiveLayoutRaw === '0' || responsiveLayoutRaw === 'false' || responsiveLayoutRaw === 'off')
                    ? false
                    : responsiveLayoutRaw;

            const movableColumns = this._bool(cfg.movablecolumns ?? cfg.movableColumns ?? 1, true);
            const resizableColumns = this._bool(cfg.resizablecolumns ?? cfg.resizableColumns ?? 1, true);

            const headerSortStart = this._normalizeHeaderSortStart(
                cfg.headersortstart ?? cfg.headerSortStart ?? 'asc'
            );

            const headerSortTristate = this._bool(
                cfg.headersorttristate ?? cfg.headerSortTristate ?? 0,
                false
            );

            const searchPlaceholder = String(cfg.searchplaceholder ?? '🔍');
            const searchWidth = this._int(cfg.searchwidth ?? 220, 220);

            const heightMin = this._int(cfg.heightmin ?? 320, 320);
            const heightMax = this._int(cfg.heightmax ?? 960, 960);
            const heightStep = this._int(cfg.heightstep ?? 40, 40);

            const showSearch       = this._bool(cfg.showsearch ?? 1, true);
            const showAutosave     = this._bool(cfg.showautosave ?? 1, true);
            const showGridLines    = this._bool(cfg.showgridlines ?? 1, true);
            const showHeight       = this._bool(cfg.showheight ?? 1, true);
            const showReload       = this._bool(cfg.showreload ?? 1, true);
            const showReset        = this._bool(cfg.showreset ?? 1, true);
            const showSave         = this._bool(cfg.showsave ?? 1, true);
            const showInsert       = this._bool(cfg.showinsert ?? cfg.showInsert ?? 1, true);
            const showColumns      = this._bool(cfg.showcolumns ?? 1, true);
            const showSyncStatus   = this._bool(cfg.showsyncstatus ?? 1, true);
            const showExportExcel  = this._bool(cfg.showexportexcel ?? 0, false);
            const showExportPdf    = this._bool(cfg.showexportpdf ?? 0, false);

            const exportFileName = String(cfg.exportfilename ?? (cfg.id || 'grid'));
            const exportSheetName = String(cfg.exportsheetname ?? (cfg.id || 'grid'));
            const pdfOrientation = String(cfg.pdforientation ?? 'landscape').toLowerCase() === 'portrait' ? 'portrait' : 'landscape';
            const pdfTitle = String(cfg.pdftitle ?? document.title ?? 'Export');

            let sortUrl = null;

            if (cfg.sort && cfg.sort !== '0') {
                sortUrl = cfg.sort;
            }

            const urls = {
                read: cfg.read || null,
                save: cfg.save || null,
                delete: cfg.delete || null,
                insert: cfg.insert || null,
                sync: cfg.sync || null,
                sort: sortUrl
            };

            dbx.log('[grid] init', {
                id: cfg.id || 'undef',
                pagination,
                paginationMode,
                pageSize,
                paginationSizeSelector,
                paginationButtonCount,
                paginationCounter,
                paginationControls,
                progressiveLoad,
                searchMode,
                syncMode,
                syncRun,
                syncLed,
                headerSort,
                headerSortStart,
                headerSortTristate,
                read: urls.read,
                save: urls.save,
                sync: urls.sync,
                sort: urls.sort
            });

            this.createTable(el, {
                height,
                minHeight,
                maxHeight,
                colsDef,
                urls,
                allowDelete,
                allowEdit,
                allowInsert,
                deleteConfirmTitle: String(cfg.deleteconfirmtitle || dbx.translate({
                    de: '<i class="bi bi-trash"></i> Datensatz löschen',
                    en: '<i class="bi bi-trash"></i> Delete record',
                    es: '<i class="bi bi-trash"></i> Eliminar registro'
                })),
                deleteConfirmQuestion: String(cfg.deleteconfirmquestion || dbx.translate({
                    de: 'Diesen Datensatz wirklich löschen?',
                    en: 'Do you really want to delete this record?',
                    es: '¿Desea eliminar este registro?'
                })),
                deleteConfirmHint: String(cfg.deleteconfirmhint || dbx.translate({
                    de: '<small>Dieser Vorgang kann nicht rückgängig gemacht werden.</small>',
                    en: '<small>This action cannot be undone.</small>',
                    es: '<small>Esta acción no se puede deshacer.</small>'
                })),
                headerFilter,
                headerSort,
                headerFilterLiveFilter,
                headerFilterPlaceholder,
                headerSortStart,
                headerSortTristate,
                pagination,
                paginationMode,
                pageSize,
                paginationSizeSelector,
                paginationButtonCount,
                paginationCounter,
                paginationAddRow,
                paginationOutOfRange,
                paginationControls,
                progressiveLoad,
                syncMode,
                searchMode,
                syncRun,
                syncLed,
                responsiveLayout,
                movableColumns,
                resizableColumns,
                searchPlaceholder,
                searchWidth,
                heightMin,
                heightMax,
                heightStep,
                showSearch,
                showAutosave,
                showGridLines,
                showHeight,
                showReload,
                showReset,
                showSave,
                showInsert,
                showColumns,
                showSyncStatus,
                showExportExcel,
                showExportPdf,
                exportFileName,
                exportSheetName,
                pdfOrientation,
                pdfTitle,
                cfg
            });
        },


        /* =========================================================
         * DESTROY
         * ========================================================= */
        destroy(el, cfg) {

            const table = el && el._dbxTable ? el._dbxTable : null;

            try {
                if (table) {
                    table._dbxDestroyed = true;
                }
            } catch (e) {}

            try {
                if (table && table._dbxLoopId) {
                    dbx.loop.hint(table._dbxLoopId, 'pause');
                }
            } catch (e) {
                dbx.warn('[grid] destroy loop pause failed', e);
            }

            try {
                if (table && table._dbxAutoTimer) {
                    clearTimeout(table._dbxAutoTimer);
                    table._dbxAutoTimer = null;
                }
            } catch (e) {
                dbx.warn('[grid] destroy auto timer clear failed', e);
            }

            try {
                if (table && table._dbxLayoutTimer) {
                    clearTimeout(table._dbxLayoutTimer);
                    table._dbxLayoutTimer = null;
                }
            } catch (e) {
                dbx.warn('[grid] destroy layout timer clear failed', e);
            }

            try {
                if (table && table._dbxPageLayoutTimer) {
                    clearTimeout(table._dbxPageLayoutTimer);
                    table._dbxPageLayoutTimer = null;
                }
            } catch (e) {
                dbx.warn('[grid] destroy page layout timer clear failed', e);
            }

            try {
                if (table && table._dbxChooserTimer) {
                    clearTimeout(table._dbxChooserTimer);
                    table._dbxChooserTimer = null;
                }
            } catch (e) {
                dbx.warn('[grid] destroy chooser timer clear failed', e);
            }

            try {
                if (table && typeof table.destroy === 'function') {
                    table.destroy();
                }
            } catch (e) {
                dbx.warn('[grid] destroy table failed', e);
            }

            if (el) {
                delete el._dbxGridInitialized;
                delete el._dbxTable;
                delete el._dbxFeature;
                delete el._dbxOpt;
                delete el._dbxSchemaParsed;
                delete el._dbxApplyGridLines;
            }
        },


        /* =========================================================
         * DBX AJAX URL HELPER
         * ========================================================= */
        _dbxAjaxUrl(url, opts) {

            opts = opts || {};

            if (!url) return url;

            let finalUrl = (dbx.ajax && typeof dbx.ajax.url === 'function')
                ? dbx.ajax.url(url)
                : url;

            if (opts.background === true && finalUrl.indexOf('dbx_sync=') === -1) {
                finalUrl += (finalUrl.indexOf('?') === -1 ? '?' : '&') + 'dbx_sync=0';
            }

            return finalUrl;
        },


        _getAjaxSorters(table) {

            if (!table || !table.element) return [];

            const opt = table.element._dbxOpt || {};

            if (opt.headerSort !== true) {
                return [];
            }

            if (table._dbxBuilt !== true) {
                return [];
            }

            if (table._dbxIsRemotePagination === true) {
                const sorters = table.getSorters ? table.getSorters() : [];
                return Array.isArray(sorters) ? sorters : [];
            }

            if (opt.urls && opt.urls.sort) {
                const s = table._dbxServerSort;

                if (s && s.field && s.dir) {
                    return [{
                        field: s.field,
                        dir: s.dir
                    }];
                }
            }

            const sorters = table.getSorters ? table.getSorters() : [];
            return Array.isArray(sorters) ? sorters : [];
        },

        _applyServerSortIndicators(table) {

            if (!table || !table.element) return;

            const opt = table.element._dbxOpt || {};

            if (!opt.urls || !opt.urls.sort) return;
            if (table._dbxIsRemotePagination === true) return;

            const active = table._dbxServerSort || null;
            const cols   = this._getLeafColumns(table);

            cols.forEach(col => {

                const field = col.getField ? col.getField() : null;
                if (!field || field.startsWith('_')) return;

                const el = col.getElement ? col.getElement() : null;
                if (!el) return;

                let aria = 'none';

                if (active && active.field === field) {
                    aria = (active.dir === 'asc') ? 'ascending' : 'descending';
                }

                el.setAttribute('aria-sort', aria);
            });
        },



        _dbxRequest(url, options = {}) {

            if (!url) {
                return Promise.reject(new Error('Missing URL'));
            }

            if (!dbx.ajax || typeof dbx.ajax.request !== 'function') {
                return Promise.reject(new Error('ajax.js nicht geladen.'));
            }

            const method       = String(options.method || 'GET').toUpperCase();
            const headers      = options.headers || {};
            const body         = (typeof options.body === 'undefined') ? null : options.body;
            const responseType = options.responseType || 'json';
            const startedAt    = Date.now();
            const skipRuntime  = options.skipRuntime === true
                || /[?&]dbx_sync=0(?:&|$)/.test(String(url || ''));

            dbx.log('[grid][ajax] start', {
                method: method,
                url: url,
                responseType: responseType
            });

            return dbx.ajax.request({
                url: url,
                method: method,
                mode: responseType === 'json' ? 'json' : 'text',
                body: body,
                headers: headers,
                timeout: options.timeout || 30000,
                skipRuntime: skipRuntime
            }).then(out => {
                dbx.log('[grid][ajax] success', {
                    method: method,
                    url: url,
                    duration_ms: Date.now() - startedAt
                });
                return out;
            }).catch(error => {
                dbx.error('[grid][ajax] error', {
                    method: method,
                    url: url,
                    duration_ms: Date.now() - startedAt,
                    error: error
                });
                throw error;
            });
        },


        _parsePaginationSizeSelector(v) {

            if (v === undefined || v === null || v === '' || v === false || v === 0 || v === '0' || v === 'off' || v === 'false') {
                return false;
            }

            if (v === true || v === 1 || v === '1' || v === 'on' || v === 'true' || v === 'auto') {
                return true;
            }

            const normalizeValue = (item) => {
                if (item === true) return 99999;

                const txt = String(item).trim().toLowerCase();

                if (!txt) return null;
                if (txt === 'true')  return 99999;
                if (txt === 'all')   return 99999;
                if (txt === '*')     return 99999;
                if (txt === '__all') return 99999;

                const n = parseInt(txt, 10);
                if (!isNaN(n) && n > 0) return n;

                return null;
            };

            if (Array.isArray(v)) {
                const out = v.map(normalizeValue).filter(x => x !== null);
                return out.length ? this._normalizePaginationSizeSelectorOrder(out) : false;
            }

            const out = String(v)
                .split(',')
                .map(normalizeValue)
                .filter(x => x !== null);

            return out.length ? this._normalizePaginationSizeSelectorOrder(out) : false;
        },

        _normalizePaginationSizeSelectorOrder(values) {

            return [15, 5, 25, 50, 100, 99999];
        },

        _pageSizeSelectOptions() {

            return [
                { value: '1', text: '1' },
                { value: '5', text: '5' },
                { value: '15', text: '15' },
                { value: '25', text: '25' },
                { value: '50', text: '50' },
                { value: '100', text: '100' },
                { value: '99999', text: '*' }
            ];
        },

        _normalizePageSizeValue(v, def = 15, selector = false) {

            if (v === true) return 99999;

            const txt = String(v ?? '').toLowerCase().trim();
            if (txt === 'true' || txt === 'all' || txt === '*' || txt === '__all') return 99999;

            const n = parseInt(txt, 10);
            if (!isNaN(n) && n > 0) {
                return n;
            }

            const defTxt = String(def ?? '').toLowerCase().trim();
            if (def === true || defTxt === 'true' || defTxt === 'all' || defTxt === '*' || defTxt === '__all') {
                return 99999;
            }

            const defNum = parseInt(def, 10);
            if (!isNaN(defNum) && defNum > 0) {
                return defNum;
            }

            if (Array.isArray(selector) && selector.length) {
                return selector[0] === true ? 99999 : (parseInt(selector[0], 10) || 15);
            }

            return 15;
        },

        _storePageSizeState(gridId, value, def = 15, selector = false) {

            const normalized = this._normalizePageSizeValue(value, def, selector);
            dbx.uiSet('grid', gridId, 'PAGE.SIZE', String(normalized));
            return normalized;
        },

        _getPageSizeState(gridId, def = 15, selector = false) {

            const defaultSize = this._normalizePageSizeValue(def, 15, selector);
            return this._normalizePageSizeValue(
                dbx.uiGet('grid', gridId, 'PAGE.SIZE', String(defaultSize)),
                defaultSize,
                selector
            );
        },

        _changePageSize(table, value, opt = {}) {

            if (!table || !table.element) return;

            const gridId = table.element.id || 'grid';
            const pageSize = this._storePageSizeState(gridId, value, opt.pageSize || 15, opt.paginationSizeSelector);

            table._dbxPageSizeState = pageSize;
            opt.pageSize = pageSize;

            try {
                table._dbxPageSizeChanging = true;
                if (typeof table.setPageSize === 'function') {
                    table.setPageSize(pageSize);
                }
                if (typeof table.setPage === 'function') {
                    table.setPage(1);
                }
            } catch (err) {
                dbx.warn('[grid] page size change failed', err);
            } finally {
                table._dbxPageSizeChanging = false;
            }

            this.reloadTable(table, opt, { resetPage: true });
            window.setTimeout(() => this._applyPaginationButtonLabels(table), 0);
        },

        _normalizePaginationCounter(v) {

            if (v === undefined || v === null || v === '' || v === false || v === 0 || v === '0' || v === 'off' || v === 'false') {
                return false;
            }

            const txt = String(v).toLowerCase().trim();

            if (txt === '1' || txt === 'on' || txt === 'true') return 'rows';
            if (txt === 'rows')  return 'rows';
            if (txt === 'pages') return 'pages';

            return false;
        },

        _normalizeHeaderSortStart(v) {

            const txt = String(v || 'asc').toLowerCase().trim();
            return (txt === 'desc') ? 'desc' : 'asc';
        },

        _normalizePaginationOutOfRange(v) {

            if (v === undefined || v === null || v === '') return false;

            const txt = String(v).toLowerCase().trim();

            if (txt === 'false' || txt === 'off' || txt === '0') return false;
            if (txt === 'first' || txt === 'last' || txt === 'reset') return txt;

            const n = parseInt(txt, 10);
            if (!isNaN(n)) return n;

            return false;
        },

        _getPaginationUiEls(el) {

            const root = this._getRoot(el);

            return {
                root: root,
                bar: root ? root.querySelector('[data-dbx-role="pagination-bar"]') : null,
                controls: root ? root.querySelector('[data-dbx-role="pagination-controls"]') : null,
                counter: root ? root.querySelector('[data-dbx-role="pagination-counter"]') : null
            };
        },

        _setRoleVisible(root, role, show) {

            if (!root) return;

            const el = root.querySelector('[data-dbx-role="' + role + '"]');
            if (!el) return;

            el.style.display = show ? '' : 'none';
        },

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

            const xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);

            xhr.onload = () => {
                if (xhr.status < 200 || xhr.status >= 300) {
                    dbx.error('[grid] export dependency load failed', url, 'HTTP ' + xhr.status);
                    finish(false);
                    return;
                }

                try {
                    const run = new Function(
                        'window',
                        'self',
                        'globalThis',
                        'global',
                        'exports',
                        'module',
                        'define',
                        xhr.responseText + '\n//# sourceURL=' + url
                    );

                    run.call(window, window, window, window, window, undefined, undefined, undefined);
                    finish(true);
                } catch (e) {
                    dbx.error('[grid] export dependency load failed', url, e);
                    finish(false);
                }
            };

            xhr.onerror = () => {
                dbx.error('[grid] export dependency load failed', url);
                finish(false);
            };

            xhr.send();
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

        _applyPaginationButtonLabels(table) {

            if (!table || !table.element) return;

            const language = String(document.documentElement.lang || 'de')
                .toLowerCase()
                .slice(0, 2);
            const translations = {
                de: {
                    rowsPerPage: 'Zeilen pro Seite',
                    allRows: 'Alle Zeilen',
                    rows: 'Zeilen',
                    first: 'Erste Seite',
                    prev: 'Vorherige Seite',
                    next: 'Nächste Seite',
                    last: 'Letzte Seite',
                    showPage: 'Seite {page} anzeigen'
                },
                en: {
                    rowsPerPage: 'Rows per page',
                    allRows: 'All rows',
                    rows: 'rows',
                    first: 'First page',
                    prev: 'Previous page',
                    next: 'Next page',
                    last: 'Last page',
                    showPage: 'Show page {page}'
                },
                es: {
                    rowsPerPage: 'Filas por página',
                    allRows: 'Todas las filas',
                    rows: 'filas',
                    first: 'Primera página',
                    prev: 'Página anterior',
                    next: 'Página siguiente',
                    last: 'Última página',
                    showPage: 'Mostrar página {page}'
                }
            };
            const text = translations[language] || translations.de;

            const ui = this._getPaginationUiEls(table.element);
            const controls = ui && ui.controls ? ui.controls : null;
            if (!controls) return;

            const sizeSelect = controls.querySelector('.tabulator-page-size');
            if (sizeSelect) {
                let icon = controls.querySelector('.dbx-grid-page-size-icon');

                controls.querySelectorAll('label').forEach(label => {
                    if (String(label.textContent || '').trim().toLowerCase() === 'page size') {
                        label.remove();
                    }
                });

                if (!icon) {
                    icon = document.createElement('span');
                    icon.className = 'dbx-grid-page-size-icon';
                    icon.innerHTML = '<i class="bi bi-list-ol"></i>';
                    icon.setAttribute('title', text.rowsPerPage);
                    icon.setAttribute('aria-hidden', 'true');

                    controls.insertBefore(icon, sizeSelect);
                }

                sizeSelect.setAttribute('title', text.rowsPerPage);
                sizeSelect.setAttribute('aria-label', text.rowsPerPage);

                const currentPageSize = table._dbxPageSizeState || (table.getPageSize ? table.getPageSize() : '');
                const currentValue = String(currentPageSize || sizeSelect.value || '15');

                sizeSelect.innerHTML = '';

                this._pageSizeSelectOptions().forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.text;
                    option.setAttribute('title', item.value === '99999' ? text.allRows : item.text + ' ' + text.rows);
                    sizeSelect.appendChild(option);
                });

                if (currentValue && sizeSelect.querySelector('option[value="' + currentValue + '"]')) {
                    sizeSelect.value = currentValue;
                }

            }

            if (controls._dbxPageSizeStateBound !== true) {
                controls._dbxPageSizeStateBound = true;
                controls.addEventListener('change', (e) => {
                    const select = e.target && e.target.closest ? e.target.closest('.tabulator-page-size') : null;
                    if (!select) return;

                    e.preventDefault();
                    e.stopImmediatePropagation();

                    const opt = table.element && table.element._dbxOpt ? table.element._dbxOpt : {};
                    const value = this._normalizePageSizeValue(select.value, opt.pageSize || 15, opt.paginationSizeSelector);
                    this._queueTableTimer(table, '_dbxPageSizeChangeTimer', () => {
                        this._changePageSize(table, value, opt);
                    }, 0);
                }, true);
            }

            const buttons = controls.querySelectorAll('.tabulator-page');
            if (!buttons || !buttons.length) return;

            const detectType = (btn) => {

                const values = [
                    String(btn.getAttribute('data-page') || '').toLowerCase().trim(),
                    String(btn.getAttribute('aria-label') || '').toLowerCase().trim(),
                    String(btn.getAttribute('title') || '').toLowerCase().trim(),
                    String(btn.textContent || '').toLowerCase().trim()
                ];

                const has = (needle) => values.some(v => v === needle || v.indexOf(needle) !== -1);

                if (has('first')) return 'first';
                if (has('previous') || has('prev')) return 'prev';
                if (has('next')) return 'next';
                if (has('last')) return 'last';

                return null;
            };

            const defs = {
                first: {
                    html: '<i class="bi bi-chevron-bar-left"></i>',
                    label: text.first
                },
                prev: {
                    html: '<i class="bi bi-chevron-left"></i>',
                    label: text.prev
                },
                next: {
                    html: '<i class="bi bi-chevron-right"></i>',
                    label: text.next
                },
                last: {
                    html: '<i class="bi bi-chevron-bar-right"></i>',
                    label: text.last
                }
            };

            buttons.forEach(btn => {

                const type = detectType(btn);
                if (!type || !defs[type]) {
                    const page = String(btn.textContent || '').trim();
                    if (/^\d+$/.test(page)) {
                        const label = text.showPage.replace('{page}', page);
                        btn.setAttribute('title', label);
                        btn.setAttribute('aria-label', label);
                    }
                    return;
                }

                btn.dataset.dbxPageType = type;
                btn.innerHTML = defs[type].html;
                btn.setAttribute('title', defs[type].label);
                btn.setAttribute('aria-label', defs[type].label);
            });
        },

        _ensureSortIcons(table) {

            if (!table || !table.element) return;
            if (table._dbxBuilt !== true) return;

            const opt = table.element._dbxOpt || {};
            const cols = this._getLeafColumns(table);

            cols.forEach(col => {

                const field = col.getField ? col.getField() : null;
                if (!field || field.startsWith('_')) return;

                const def = col.getDefinition ? col.getDefinition() : {};
                const sortable =
                    (opt.headerSort === true) &&
                    (
                        (def && def.headerSort === true) ||
                        (def && typeof def.headerClick === 'function')
                    );

                const headerEl = col.getElement ? col.getElement() : null;
                if (!headerEl) return;

                const titleEl =
                    headerEl.querySelector('.tabulator-col-title') ||
                    headerEl.querySelector('.tabulator-col-content') ||
                    headerEl;

                let iconEl = headerEl.querySelector('.dbx-grid-sort-icon');

                if (!sortable) {
                    if (iconEl) iconEl.remove();
                    headerEl.classList.remove('dbx-grid-sortable');
                    headerEl.classList.remove('dbx-grid-sort-asc');
                    headerEl.classList.remove('dbx-grid-sort-desc');
                    headerEl.classList.remove('dbx-grid-sort-none');
                    headerEl.setAttribute('aria-sort', 'none');
                    return;
                }

                headerEl.classList.add('dbx-grid-sortable');

                if (!iconEl) {
                    iconEl = document.createElement('span');
                    iconEl.className = 'dbx-grid-sort-icon';
                    iconEl.innerHTML = '<i class="bi bi-arrow-down-up"></i>';
                    titleEl.appendChild(iconEl);
                }
            });
        },

        _applySortIndicators(table) {

            if (!table || !table.element) return;
            if (table._dbxBuilt !== true) return;

            const opt = table.element._dbxOpt || {};
            const cols = this._getLeafColumns(table);

            this._ensureSortIcons(table);

            let activeMap = {};

            if (opt.headerSort === true) {

                const sorters = this._getAjaxSorters(table);

                if (Array.isArray(sorters)) {
                    sorters.forEach(s => {
                        if (!s || !s.field) return;
                        activeMap[s.field] = s.dir || 'asc';
                    });
                }
            }

            cols.forEach(col => {

                const field = col.getField ? col.getField() : null;
                if (!field || field.startsWith('_')) return;

                const def = col.getDefinition ? col.getDefinition() : {};
                const sortable =
                    (opt.headerSort === true) &&
                    (
                        (def && def.headerSort === true) ||
                        (def && typeof def.headerClick === 'function')
                    );

                const headerEl = col.getElement ? col.getElement() : null;
                if (!headerEl) return;

                const iconEl = headerEl.querySelector('.dbx-grid-sort-icon');

                headerEl.classList.remove('dbx-grid-sort-asc');
                headerEl.classList.remove('dbx-grid-sort-desc');
                headerEl.classList.remove('dbx-grid-sort-none');

                if (!sortable) {
                    if (iconEl) iconEl.remove();
                    headerEl.setAttribute('aria-sort', 'none');
                    return;
                }

                const dir = activeMap[field] || null;

                if (!iconEl) return;

                if (dir === 'asc') {
                    iconEl.innerHTML = '<i class="bi bi-caret-up-fill"></i>';
                    headerEl.classList.add('dbx-grid-sort-asc');
                    headerEl.setAttribute('aria-sort', 'ascending');
                } else if (dir === 'desc') {
                    iconEl.innerHTML = '<i class="bi bi-caret-down-fill"></i>';
                    headerEl.classList.add('dbx-grid-sort-desc');
                    headerEl.setAttribute('aria-sort', 'descending');
                } else {
                    iconEl.innerHTML = '<i class="bi bi-arrow-down-up"></i>';
                    headerEl.classList.add('dbx-grid-sort-none');
                    headerEl.setAttribute('aria-sort', 'none');
                }
            });
        },

        /* =========================================================
         * HELPERS
         * ========================================================= */
        _bool(v, def = false) {
            if (v === undefined || v === null || v === '') return def;
            if (v === true || v === 1 || v === '1' || v === 'on' || v === 'true') return true;
            if (v === false || v === 0 || v === '0' || v === 'off' || v === 'false') return false;
            return def;
        },

        _int(v, def = 0) {
            const n = parseInt(v, 10);
            return isNaN(n) ? def : n;
        },

        _isTableAlive(table) {
            return !!(
                table &&
                table._dbxDestroyed !== true &&
                table.element &&
                table.element.isConnected === true
            );
        },

        _isTableLayoutReady(table) {

            if (!this._isTableAlive(table)) return false;

            const root = table.element;
            if (!root) return false;

            return !!(
                root.querySelector('.tabulator-header') ||
                root.querySelector('.tabulator-tableholder')
            );
        },

        _queueTableTimer(table, key, fn, delay = 0) {

            if (!table || !key || typeof fn !== 'function') return;

            if (table[key]) {
                clearTimeout(table[key]);
                table[key] = null;
            }

            table[key] = setTimeout(() => {
                table[key] = null;

                if (!this._isTableAlive(table)) return;

                fn();
            }, delay);
        },

        _getLeafColumns(table) {

            const out = [];
            if (!table || typeof table.getColumns !== 'function') return out;

            const walk = (cols) => {
                cols.forEach(col => {
                    const field = col.getField && col.getField();
                    if (field && !field.startsWith('_')) {
                        out.push(col);
                    }
                    if (col.getSubColumns) {
                        const subs = col.getSubColumns();
                        if (subs && subs.length) {
                            walk(subs);
                        }
                    }
                });
            };

            walk(table.getColumns());
            return out;
        },

        _restoreStoredColumnWidths(table, gridId) {

            if (!this._isTableLayoutReady(table)) return;

            const uiGet = (k, def) => dbx.uiGet('grid', gridId, k, def);
            const cols = this._getLeafColumns(table);

            cols.forEach(col => {
                const field = col.getField();
                if (!field || field.startsWith('_')) return;

                const w = uiGet('COLUMNS.SIZE.' + field, null);
                if (w === null) return;

                const width = parseInt(w, 10);
                if (isNaN(width) || width <= 0) return;

                const currentWidth = col.getWidth();
                if (typeof currentWidth === 'number' && Math.abs(currentWidth - width) <= 1) {
                    return;
                }

                try {
                    col.setWidth(width);
                } catch (e) {
                    dbx.warn('[grid] restore width failed', field, width, e);
                }
            });
        },

        _restoreStoredColumnVisibility(table, gridId) {

            if (!this._isTableLayoutReady(table)) return;

            const uiGet = (k, def) => dbx.uiGet('grid', gridId, k, def);
            const cols = this._getLeafColumns(table);

            cols.forEach(col => {
                const field = col.getField();
                if (!field || field.startsWith('_')) return;

                const vis = uiGet('COLUMNS.VISIBLE.' + field, null);
                if (vis === null) return;

                try {
                    if (vis === '0' && col.isVisible()) col.hide();
                    if (vis === '1' && !col.isVisible()) col.show();
                } catch (e) {
                    dbx.warn('[grid] restore visibility failed', field, vis, e);
                }
            });
        },

        _applyShiftGroupLabels(table) {

            if (!this._isTableLayoutReady(table)) return;

            const root = table.element;
            if (!root || !root.innerHTML.includes('~~')) return;

            root.querySelectorAll('.tabulator-col-group').forEach(groupEl => {

                const titleEl = groupEl.querySelector('.tabulator-col-title');
                if (!titleEl) return;

                if (titleEl.querySelector('.dbx-shift-label')) return;

                const raw = titleEl.textContent;
                if (!raw || !raw.includes('~~')) return;

                const parts = raw.split('~~');
                if (parts.length !== 2) return;

                const left  = parts[0].trim();
                const right = parts[1].trim();

                titleEl.innerHTML =
                    '<div class="dbx-shift-label">' +
                        '<span class="left">' + left + '</span>' +
                        '<span class="right">' + right + '</span>' +
                    '</div>';
            });
        },

        _applyInitialLayoutState(table, gridId) {

            if (!this._isTableLayoutReady(table)) return false;

            try {
                table.blockRedraw();

                this._restoreStoredColumnWidths(table, gridId);
                this._restoreStoredColumnVisibility(table, gridId);
                this._applyShiftGroupLabels(table);

            } finally {
                try {
                    table.restoreRedraw(true);
                } catch (e) {
                    dbx.warn('[grid] restoreRedraw failed', e);
                }
            }

            return true;
        },

        _getRoot(el) {
            return el.closest('.dbx-grid');
        },

        _findSaveButton(el) {
            const root = this._getRoot(el);
            let btn = root ? root.querySelector('[data-dbx="grid-save"]') : null;
            if (!btn) {
                const panel = el.closest('.dbx-panel');
                btn = panel ? panel.querySelector('[data-dbx="grid-save"]') : null;
            }
            return btn;
        },

        _tableHasPendingEdits(table) {
            if (!table || typeof table.getEditedCells !== 'function') {
                return false;
            }
            try {
                const edited = table.getEditedCells();
                return Array.isArray(edited) && edited.length > 0;
            } catch (e) {
                return false;
            }
        },

        _syncDirtyState(table) {
            const hasEdits = this._tableHasPendingEdits(table);
            if (hasEdits) {
                table._dbxDirty = true;
            }
            return table._dbxDirty === true || hasEdits;
        },

        _markTableDirty(table, el) {
            table._dbxDirty = true;
            this.updateSaveButton(el, table);
        },

        _getSyncEls(el) {
            const root = this._getRoot(el);
            return {
                root,
                led: root ? root.querySelector('.dbx-grid-sync-led') : null,
                count: root ? root.querySelector('.dbx-grid-sync-count') : null
            };
        },

        _setLedState(led, state) {

            if (!led) return;

            if (led._dbxSyncLedEnabled === false) {
                if (led.style.display !== 'none') {
                    led.style.display = 'none';
                }
                return;
            }

            if (led.style.display === 'none') {
                led.style.display = 'inline-block';
            }

            if (led._dbxState === state) return;
            led._dbxState = state;

            let color = '#bbb';

            if (state === 'loading') color = '#0d6efd';
            if (state === 'ok')      color = '#198754';
            if (state === 'idle')    color = '#bbb';
            if (state === 'error')   color = '#dc3545';

            if (led._dbxLastColor !== color) {
                led.style.backgroundColor = color;
                led._dbxLastColor = color;
            }
        },

        _setSyncCount(countEl, value) {

            if (!countEl) return;

            const txt = String(value ?? '');
            if (countEl.textContent !== txt) {
                countEl.textContent = txt;
            }
        },

        _clearConflictFlags(table) {
            if (!table || !table.element) return;
            table.element.querySelectorAll('.dbx-cell-conflict').forEach(el => {
                el.classList.remove('dbx-cell-conflict');
            });
        },

        _rowIdField(table) {
            return (table && table.options && table.options.index) ? table.options.index : 'id';
        },

        _collectEditedMap(table) {

            const editedMap = {};
            const editedCells = table.getEditedCells();

            if (!editedCells || !editedCells.length) {
                return editedMap;
            }

            for (let i = 0; i < editedCells.length; i++) {
                const c = editedCells[i];
                const r = c.getRow();
                if (!r) continue;

                const id = r.getData()?.[this._rowIdField(table)];
                const f  = c.getField();

                if (id == null || !f) continue;

                if (!editedMap[id]) editedMap[id] = {};
                editedMap[id][f] = true;
            }

            return editedMap;
        },

        _applySchemaCellStyle(cell, rowData) {

            if (!cell) return;

            const table  = cell.getTable();
            const schema = table?.element?._dbxSchemaParsed;
            if (!schema || !schema.columns) return;

            const field = cell.getField();
            const colSchema = schema.columns[field];
            if (!colSchema) return;

            const style = dbxGrid.evalCell(colSchema, cell.getValue(), rowData || cell.getRow().getData());
            if (style) {
                dbxGridApplyCellStyle(cell, style);
            }
        },

        _ajaxResponse(table, url, params, response) {

            if (response && typeof response === 'object') {
                if (typeof response.server_time !== 'undefined') {
                    table._dbxServerTime = response.server_time || null;
                }

                if (typeof response.count !== 'undefined') {
                    table._dbxSyncCount = response.count || 0;
                }
            }

            if (table._dbxIsRemotePagination === true || table._dbxIsProgressive === true) {

                if (response && Array.isArray(response.data)) {
                    return {
                        last_page: response.last_page || 1,
                        last_row: response.last_row,
                        data: response.data
                    };
                }

                if (response && Array.isArray(response.rows)) {
                    return {
                        last_page: response.last_page || 1,
                        last_row: response.last_row,
                        data: response.rows
                    };
                }

                if (Array.isArray(response)) {
                    return {
                        last_page: 1,
                        data: response
                    };
                }

                dbx.error('[grid] invalid paginated response', response);
                return {
                    last_page: 1,
                    data: []
                };
            }

            if (response && Array.isArray(response.rows)) {
                return response.rows;
            }

            if (Array.isArray(response)) {
                return response;
            }

            dbx.error('[grid] invalid response', response);
            return [];
        },


        /* =========================================================
         * BUILD COLUMNS
         * ========================================================= */
        _escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        _deleteRecordLabel(data, idField) {
            if (!data || typeof data !== 'object') return '';

            const parts = [];
            const name = data.display_name || data.name2 || data.name || data.uname || data.title || data.label || '';
            const email = data.email || '';
            const id = data[idField] ?? data.id ?? '';

            if (name) parts.push(String(name));
            if (email) parts.push(String(email));
            if (id !== '') parts.push('ID ' + String(id));

            return parts.join(' - ');
        },

        _confirmDelete(data, idField, opt, source) {
            const feature = this;
            const label = feature._deleteRecordLabel(data, idField);
            const labelHtml = label
                ? '<div class="mt-2"><strong>' + feature._escapeHtml(label) + '</strong></div>'
                : '';

            const openConfirm = function() {
                if (!window.dbx || !dbx.confirm || typeof dbx.confirm.open !== 'function') {
                    (window.dbx && dbx.error ? dbx.error : console.error)('[grid] confirm feature missing');
                    return Promise.resolve(false);
                }

                return dbx.confirm.open({
                    id: 'grid-delete-' + String((data && (data[idField] ?? data.id)) || Date.now()),
                    root: source ? source.closest('[data-dbx]') : document.body,
                    source,
                    title: opt.deleteConfirmTitle,
                    question: opt.deleteConfirmQuestion + labelHtml,
                    hint: opt.deleteConfirmHint,
                    buttons: 'yesno',
                    labelyes: '<i class="bi bi-trash"></i> ' + dbx.translate({
                        de: 'Löschen',
                        en: 'Delete',
                        es: 'Eliminar'
                    }),
                    labelno: '<i class="bi bi-x-lg"></i> ' + dbx.translate({
                        de: 'Abbrechen',
                        en: 'Cancel',
                        es: 'Cancelar'
                    }),
                    closable: true,
                    backdropclose: false,
                    escclose: true
                }).then(result => result && result.action === 'yes');
            };

            if (window.dbx && typeof dbx.loadFeature === 'function' && (!dbx.confirm || typeof dbx.confirm.open !== 'function')) {
                return new Promise(resolve => {
                    dbx.loadFeature('confirm', function() {
                        openConfirm().then(resolve).catch(() => resolve(false));
                    });
                });
            }

            return openConfirm().catch(() => false);
        },

        buildColumns(opt) {

            const colsDef = opt.colsDef;
            const gridId  = opt._gridId;

            const uiGet = (k, def) => dbx.uiGet('grid', gridId, k, def);

            const cols = [];
            const groups = {};
            const ungrouped = [];
            let hasGroups = false;

            const hasActions = !!(opt.allowDelete);

            const orderRaw = uiGet('COLUMNS.ORDER', null);
            const orderList = orderRaw
                ? orderRaw.split('|').filter(f => f && !f.startsWith('_'))
                : null;

            const sortEnabled = (opt.headerSort === true);
            const useDedicatedServerSort = sortEnabled && !!(opt.urls.sort && opt._dbxIsRemotePagination !== true);
            const useTabulatorSort = sortEnabled && !useDedicatedServerSort;

            const actionsCol = {
                title: '<i class="bi bi-gear"></i>',
                headerHozAlign: 'center',
                field: '_actions',
                width: 124,
                minWidth: 124,
                maxWidth: 124,
                hozAlign: 'center',
                headerHozAlign: 'center',
                frozen: true,
                headerSort: false,
                headerFilter: false,
                resizable: false,
                cssClass: 'dbx-col-actions',
                formatter: function(cell) {
                    const wrap = document.createElement('div');
                    wrap.style.display = 'flex';
                    wrap.style.gap = '6px';
                    wrap.style.justifyContent = 'center';
                    wrap.style.alignItems = 'center';

                    const row = cell.getRow();
                    const table = row.getTable();
                    const data = row.getData();

                    if (data && data.show_link) {
                        const btnShow = document.createElement('button');
                        btnShow.className = 'btn btn-sm btn-outline-primary';
                        btnShow.style.minWidth = '28px';
                        btnShow.style.height = '25px';
                        btnShow.style.padding = '2px 6px';
                        btnShow.style.lineHeight = '1';
                        btnShow.title = 'Anzeigen';
                        btnShow.innerHTML = '<i class="bi bi-eye"></i>';

                        btnShow.addEventListener('click', function(e) {
                            e.stopPropagation();
                            const url = String(data.show_link || '');
                            if (!url) return;

                            if (window.dbx && dbx.openWin && typeof dbx.openWin.open === 'function') {
                                dbx.openWin.open({
                                    url: url,
                                    title: dbx.translate({
                                        de: 'Vorschau',
                                        en: 'Preview',
                                        es: 'Vista previa'
                                    }) + ': ' + String(data.title || 'Content'),
                                    width: 1280,
                                    height: 820,
                                    modal: 0,
                                    ajax: 1,
                                    scroll: 1,
                                    position: 'center',
                                    reloadable: 1,
                                    reuse: 1,
                                    allowDuplicate: 0
                                }, btnShow);
                            } else {
                                if (window.dbx && dbx.utilities && dbx.utilities.leaveGuard) {
                                    dbx.utilities.leaveGuard.allowIfInternal(url);
                                }
                                window.location.href = url;
                            }
                        });

                        wrap.appendChild(btnShow);
                    }

                    if (data && data.profile_link) {
                        const btnEdit = document.createElement('button');
                        btnEdit.className = 'btn btn-sm btn-outline-primary';
                        btnEdit.style.minWidth = '28px';
                        btnEdit.style.height = '25px';
                        btnEdit.style.padding = '2px 6px';
                        btnEdit.style.lineHeight = '1';
                        btnEdit.title = dbx.translate({
                            de: 'Bearbeiten',
                            en: 'Edit',
                            es: 'Editar'
                        });
                        btnEdit.innerHTML = '<i class="bi bi-pencil-square"></i>';

                        btnEdit.addEventListener('click', function(e) {
                            e.stopPropagation();
                            const url = String(data.profile_link || '');
                            if (!url) return;

                            if (window.dbx && dbx.openWin && typeof dbx.openWin.open === 'function') {
                                dbx.openWin.open({
                                    url: url,
                                    title: dbx.translate({
                                        de: 'Benutzer',
                                        en: 'User',
                                        es: 'Usuario'
                                    }),
                                    height: 760,
                                    width: 1280,
                                    modal: 1,
                                    scroll: 1,
                                    position: 'center'
                                }, btnEdit);
                            } else {
                                if (window.dbx && dbx.utilities && dbx.utilities.leaveGuard) {
                                    dbx.utilities.leaveGuard.allowIfInternal(url);
                                }
                                window.location.href = url;
                            }
                        });

                        wrap.appendChild(btnEdit);
                    }

                    if (opt.allowDelete) {
                        const btnDel = document.createElement('button');
                        btnDel.className = 'btn btn-sm btn-danger';
                        btnDel.style.minWidth = '28px';
                        btnDel.style.height = '25px';
                        btnDel.style.padding = '2px 6px';
                        btnDel.style.lineHeight = '1';
                        btnDel.innerHTML = '<i class="bi bi-trash"></i>';

                        btnDel.addEventListener('click', function(e) {
                            e.stopPropagation();
                            const idField = table.element._dbxFeature._rowIdField(table);
                            if (!data || typeof data[idField] === 'undefined') return;

                            table.element._dbxFeature._confirmDelete(data, idField, opt, btnDel)
                                .then(confirmed => {
                                    if (!confirmed) return;

                                    return table.element._dbxFeature._dbxRequest(
                                        table.element._dbxFeature._dbxAjaxUrl(opt.urls.delete || ''),
                                        {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ id: data[idField] }),
                                            responseType: 'json'
                                        }
                                    );
                                })
                                .then(res => {
                                    if (!res) return;
                                    if (res && (res.ok || res.success)) {
                                        row.delete();
                                    } else {
                                        dbx.error('[grid] delete failed', res);
                                    }
                                })
                                .catch(err => {
                                    dbx.error('[grid] delete error', err);
                                });
                        });

                        wrap.appendChild(btnDel);
                    }

                    return wrap;
                }
            };

            const fieldDefinitions = colsDef.split(',');
            const colMap = {};

            fieldDefinitions.forEach(def => {

                let groupName = null;

                if (def.includes('@')) {
                    const tmp = def.split('@');
                    def = tmp[0].trim();
                    groupName = tmp[1].trim();
                    hasGroups = true;
                }

                const parts = def.split(':').map(s => s.trim());

                const fieldInfo = dbxExtractLabel(parts[0]);
                const field = fieldInfo.key;
                const title = fieldInfo.label;

                const gridType = String(parts[1] || '').toLowerCase();
                let flag = parts[2] || null;
                let optionRaw = parts.slice(3).join(':');

                if (flag && flag.indexOf('=') !== -1) {
                    optionRaw = parts.slice(2).join(':');
                    flag = null;
                }

                const colOptions = dbxGridParseColumnOptions(optionRaw);

                if (!field || field.startsWith('_') || flag === '!v') return;

                const visState = uiGet('COLUMNS.VISIBLE.' + field, '1');

                const col = {
                    title: title,
                    field,
                    visible: (visState !== '0'),
                    headerSort: useTabulatorSort,
                    headerSortStartingDir: opt.headerSortStart || 'asc',
                    headerSortTristate: useTabulatorSort ? !!opt.headerSortTristate : false,
                    sorter: colOptions.sorter || (gridType === 'number' ? 'number' : (gridType === 'date' ? 'date' : 'string')),
                    headerFilter: opt.headerFilter ? 'input' : false,
                    headerFilterLiveFilter: !!opt.headerFilterLiveFilter,
                    headerFilterPlaceholder: opt.headerFilterPlaceholder || undefined,
                    editor: (opt.allowEdit && flag !== 'p') ? 'input' : false,
                    editable: (opt.allowEdit && flag !== 'p'),
                    resizable: true,

                    formatter: (cell) => {

                        const value = cell.getValue();

                        if (gridType === 'image') {
                            if (!value) return '';
                            const img = document.createElement('img');
                            img.src = String(value);
                            img.alt = '';
                            img.loading = 'lazy';
                            img.style.width = colOptions.imgWidth || '38px';
                            img.style.height = colOptions.imgHeight || '38px';
                            img.style.objectFit = 'cover';
                            img.style.borderRadius = colOptions.radius || '50%';
                            img.style.display = 'block';
                            img.style.margin = '0 auto';
                            return img;
                        }

                        if (colOptions.formatter === 'truncate' || colOptions.truncate === '1') {
                            const text = (value === null || value === undefined) ? '' : String(value);
                            const maxChars = parseInt(colOptions.maxChars || colOptions.maxchars || 180, 10) || 180;
                            const shortText = text.length > maxChars ? text.substring(0, maxChars) + '...' : text;
                            const div = document.createElement('div');
                            div.className = 'dbx-grid-cell-truncate';
                            div.textContent = shortText.replace(/\s+/g, ' ').trim();
                            div.title = text;
                            return div;
                        }

                        const table  = cell.getTable();
                        const schema = table?.element?._dbxSchemaParsed;
                        if (!schema || !schema.columns) return value;

                        const field = cell.getField();
                        const colSchema = schema.columns[field];
                        if (!colSchema) return value;

                        const rowData = cell.getRow().getData();
                        const style = dbxGrid.evalCell(colSchema, value, rowData);

                        if (style) {
                            dbxGridApplyCellStyle(cell, style);
                        }

                        return value;
                    },
                };

                if (colOptions.width) col.width = parseInt(colOptions.width, 10) || col.width;
                if (colOptions.minWidth) col.minWidth = parseInt(colOptions.minWidth, 10) || col.minWidth;
                if (colOptions.maxWidth) col.maxWidth = parseInt(colOptions.maxWidth, 10) || col.maxWidth;
                if (colOptions.hozAlign) col.hozAlign = colOptions.hozAlign;
                if (colOptions.headerHozAlign) col.headerHozAlign = colOptions.headerHozAlign;
                if (colOptions.bigEditor === '1' || colOptions.bigeditor === '1') {
                    col.cssClass = [col.cssClass, 'dbx-grid-cell-big-editor'].filter(Boolean).join(' ');
                }

                if (gridType === 'image') {
                    col.editor = false;
                    col.editable = false;
                    col.headerFilter = false;
                    col.headerSort = false;
                }

                if (opt.allowEdit && flag !== 'p' && colOptions.editor) {
                    if (colOptions.editor === 'list' || colOptions.editor === 'select') {
                        const lookupValues = dbxGridParseEditorValues(colOptions.values || '');
                        col.editor = 'list';
                        col.editorParams = {
                            values: lookupValues
                        };
                        const baseFormatter = col.formatter;
                        col.formatter = (cell) => {
                            const value = cell.getValue();
                            const key = (value === null || value === undefined) ? '' : String(value);
                            const display = Object.prototype.hasOwnProperty.call(lookupValues, key)
                                ? lookupValues[key]
                                : value;

                            const table = cell.getTable();
                            const schema = table?.element?._dbxSchemaParsed;
                            if (schema && schema.columns) {
                                const field = cell.getField();
                                const colSchema = schema.columns[field];
                                if (colSchema) {
                                    const rowData = cell.getRow().getData();
                                    const style = dbxGrid.evalCell(colSchema, value, rowData);
                                    if (style) {
                                        dbxGridApplyCellStyle(cell, style);
                                    }
                                }
                            }

                            if (!Object.prototype.hasOwnProperty.call(lookupValues, key) && typeof baseFormatter === 'function') {
                                return baseFormatter(cell);
                            }

                            return display;
                        };
                    } else if (colOptions.editor === 'textarea') {
                        col.editor = 'textarea';
                    } else if (colOptions.editor === 'input') {
                        col.editor = 'input';
                    }
                }

                if (useDedicatedServerSort) {
                    col.headerClick = (e, column) => {

                        const table = column.getTable();
                        const field = column.getField();
                        if (!field || field.startsWith('_')) return;

                        const current = table._dbxServerSort || null;
                        const startDir = opt.headerSortStart || 'asc';
                        const otherDir = (startDir === 'asc') ? 'desc' : 'asc';

                        let nextSort = null;

                        if (!current || current.field !== field) {
                            nextSort = { field: field, dir: startDir };
                        } else if (current.dir === startDir) {
                            nextSort = { field: field, dir: otherDir };
                        } else if (current.dir === otherDir && opt.headerSortTristate === true) {
                            nextSort = null;
                        } else {
                            nextSort = { field: field, dir: startDir };
                        }

                        table._dbxServerSort = nextSort;

                        table.element._dbxFeature._applySortIndicators(table);

                        if (!nextSort) {
                            dbx.log('[grid] server sort cleared', {
                                id: gridId,
                                field: field
                            });

                            table.setData(table.element._dbxFeature._dbxAjaxUrl(opt.urls.read));
                            return;
                        }

                        const url =
                            table.element._dbxFeature._dbxAjaxUrl(
                                opt.urls.sort +
                                '&field=' + encodeURIComponent(nextSort.field) +
                                '&dir=' + encodeURIComponent(nextSort.dir)
                            );

                        dbx.log('[grid] server sort click', {
                            id: gridId,
                            field: nextSort.field,
                            dir: nextSort.dir,
                            url: url
                        });

                        table.setData(url);
                    };
                }

                colMap[field] = col;

                if (groupName) {
                    if (!groups[groupName]) groups[groupName] = [];
                    groups[groupName].push(col);
                } else {
                    ungrouped.push(col);
                }
            });

            if (orderList && !hasGroups) {

                const ordered = [];
                const used = {};

                orderList.forEach(f => {
                    if (colMap[f]) {
                        ordered.push(colMap[f]);
                        used[f] = true;
                    }
                });

                Object.keys(colMap).forEach(f => {
                    if (!used[f]) {
                        ordered.push(colMap[f]);
                    }
                });

                if (hasActions) {
                    ordered.unshift(actionsCol);
                }

                return ordered;
            }

            if (hasGroups) {

                let idx = 0;

                if (hasActions) {
                    cols.unshift(actionsCol);
                }

                if (ungrouped.length) {
                    ungrouped.forEach(col => cols.push(col));
                }

                Object.keys(groups).forEach(groupName => {

                    if (idx > 0 || ungrouped.length > 0) {
                        cols.push({
                            title: '',
                            field: `_sep_${idx}`,
                            width: 6,
                            minWidth: 6,
                            maxWidth: 6,
                            headerSort: false,
                            headerFilter: false,
                            resizable: false,
                            cssClass: 'dbx-col-separator',
                            formatter: () => '',
                            print: false,
                            download: false
                        });
                    }

                    cols.push({
                        title: groupName,
                        columns: groups[groupName]
                    });

                    idx++;
                });

                return cols;
            }

            if (hasActions) {
                cols.push(actionsCol);
            }

            return cols.concat(ungrouped);
        },


        /* =========================================================
         * SAVE BUTTON UI
         * ========================================================= */
        updateSaveButton(el, table) {

            const btn = this._findSaveButton(el);
            if (!btn) return;

            const isDirty = this._syncDirtyState(table);

            if (btn._dbxDirtyState === isDirty) return;

            btn._dbxDirtyState = isDirty;

            if (isDirty) {
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-primary');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            }
        },


        /* =========================================================
         * LAYOUT STATE
         * ========================================================= */
        bindLayoutState(el, table) {

            let saveTimeout = null;
            let lastResizedField = null;

            const gridId = el.id || 'grid';
            const uiSet = (k, v) => dbx.uiSet('grid', gridId, k, v);

            const getLeafColumns = () => this._getLeafColumns(table);

            function saveLayout(type) {

                if (saveTimeout) clearTimeout(saveTimeout);

                saveTimeout = setTimeout(() => {

                    if (type === 'order') {
                        const cols = getLeafColumns();
                        const order = cols.map(c => c.getField()).join('|');
                        uiSet('COLUMNS.ORDER', order);
                        return;
                    }

                    if (type === 'width') {
                        if (!lastResizedField) return;

                        const col = table.getColumn(lastResizedField);
                        if (!col) return;

                        const w = col.getWidth();

                        if (typeof w === 'number' && w > 0) {
                            uiSet('COLUMNS.SIZE.' + lastResizedField, String(w));
                        }
                        return;
                    }

                }, 300);
            }

            table.on('columnResized', col => {
                const f = col.getField();
                if (!f || f.startsWith('_')) return;

                lastResizedField = f;
                saveLayout('width');
            });

            table.on('columnMoved', col => {
                const f = col.getField();
                if (!f || f.startsWith('_')) return;
                saveLayout('order');
            });

            table.on('columnVisibilityChanged', (col, visible) => {
                const f = col.getField();
                if (!f || f.startsWith('_')) return;
                uiSet('COLUMNS.VISIBLE.' + f, visible ? '1' : '0');
            });

            table.on('pageSizeChanged', (pageSize) => {
                if (table._dbxPageSizeChanging === true) {
                    table._dbxPageSizeState = this._normalizePageSizeValue(
                        pageSize,
                        opt.pageSize || 15,
                        opt.paginationSizeSelector
                    );
                }

                if (table._dbxIsRemotePagination === true) {
                    try {
                        table.setPage(1);
                    } catch (e) {
                        dbx.warn('[grid] setPage failed after pageSizeChanged', e);
                    }
                    table.replaceData();
                }
            });
        },


        /* =========================================================
         * TOOLBAR
         * ========================================================= */
        bindToolbar(el, table, opt, uiState, root) {

            const gridId = el.id || 'grid';

            const uiGet = (k, def) => dbx.uiGet('grid', gridId, k, def);
            const uiSet = (k, v)   => dbx.uiSet('grid', gridId, k, v);

            el._dbxApplyGridLines = function(force) {

                const on = uiGet('GRIDLINES', '1') == '1';
                const tabRoot = table.element;
                if (!tabRoot) return;

                if (on) {
                    tabRoot.classList.add('dbx-grid-lines');
                } else {
                    tabRoot.classList.remove('dbx-grid-lines');
                }
            };

            uiState.gridLines = uiGet('GRIDLINES', '1') == '1';
            uiState.autosave  = uiGet('AUTOSAVE', '1') == '1';

            const heightStored = uiGet('HEIGHT', null);

            const autosave     = root ? root.querySelector('[data-dbx="grid-autosave"]') : null;
            const gridLinesCb  = root ? root.querySelector('[data-dbx="grid-lines"]') : null;
            const saveBtn      = root ? root.querySelector('[data-dbx="grid-save"]') : null;
            const insertBtn    = root ? root.querySelector('[data-dbx="grid-insert"]') : null;
            const reloadBtn    = root ? root.querySelector('[data-dbx="grid-reload"]') : null;
            const resetBtn     = root ? root.querySelector('[data-dbx="grid-reset"]') : null;
            const colBtn       = root ? root.querySelector('[data-dbx="grid-columns"]') : null;
            const excelBtn     = root ? root.querySelector('[data-dbx="grid-export-excel"]') : null;
            const pdfBtn       = root ? root.querySelector('[data-dbx="grid-export-pdf"]') : null;
            const heightSlider = root ? root.querySelector('[data-dbx="grid-height"]') : null;
            const searchInput  = root ? root.querySelector('[data-dbx="grid-search"]') : null;

            this._setRoleVisible(root, 'search', opt.showSearch === true);
            this._setRoleVisible(root, 'autosave', opt.showAutosave === true && opt.allowEdit === true);
            this._setRoleVisible(root, 'gridlines', opt.showGridLines === true);
            this._setRoleVisible(root, 'height', opt.showHeight === true);
            this._setRoleVisible(root, 'reload', opt.showReload === true);
            this._setRoleVisible(root, 'reset', opt.showReset === true);
            this._setRoleVisible(root, 'save', opt.showSave === true && opt.allowEdit === true);
            this._setRoleVisible(root, 'insert', opt.showInsert === true && opt.allowInsert === true);
            this._setRoleVisible(root, 'columns', opt.showColumns === true);
            this._setRoleVisible(root, 'syncstatus', opt.showSyncStatus === true && opt.syncLed !== false);
            this._setRoleVisible(root, 'export-excel', opt.showExportExcel === true);
            this._setRoleVisible(root, 'export-pdf', opt.showExportPdf === true);

            this._setRoleVisible(
                root,
                'pagination-bar',
                opt.pagination === true && (
                    (opt.paginationControls === true) ||
                    (opt.paginationCounter !== false)
                )
            );

            this._setRoleVisible(
                root,
                'pagination-controls',
                opt.pagination === true && opt.paginationControls === true
            );

            this._setRoleVisible(
                root,
                'pagination-counter',
                opt.pagination === true && opt.paginationCounter !== false
            );

            if (searchInput) {
                searchInput.placeholder = opt.searchPlaceholder || '🔍';
                if (opt.searchWidth > 0) {
                    searchInput.style.width = opt.searchWidth + 'px';
                }
            }

            if (autosave) {
                autosave.checked = uiState.autosave;
                autosave.addEventListener('change', () => {
                    uiSet('AUTOSAVE', autosave.checked ? '1' : '0');
                });
            }

            if (gridLinesCb) {
                gridLinesCb.checked = uiState.gridLines;
                gridLinesCb.addEventListener('change', () => {
                    uiSet('GRIDLINES', gridLinesCb.checked ? '1' : '0');
                    el._dbxApplyGridLines(false);
                });
            }

            if (heightSlider) {

                const heightMin = Math.max(120, parseInt(opt.heightMin, 10) || 320);
                const heightMax = Math.max(heightMin, parseInt(opt.heightMax, 10) || 960);
                const heightStep = Math.max(10, parseInt(opt.heightStep, 10) || 40);

                heightSlider.min = String(heightMin);
                heightSlider.max = String(heightMax);
                heightSlider.step = String(heightStep);

                if (heightStored !== null) {
                    heightSlider.value = heightStored;
                }

                const sliderHeight = parseInt(heightSlider.value, 10);
                if (!isNaN(sliderHeight)) {
                    heightSlider.value = String(Math.min(heightMax, Math.max(heightMin, sliderHeight)));
                }

                heightSlider.addEventListener('input', () => {
                    const h = parseInt(heightSlider.value, 10);
                    if (isNaN(h)) return;

                    uiSet('HEIGHT', String(h));
                    table.setHeight(h);
                });
            }

            if (saveBtn) {
                saveBtn.addEventListener('click', () => {
                    if (table._dbxDirty === true) {
                        this.saveTable(table, opt);
                    }
                });
            }

            if (insertBtn) {
                insertBtn.addEventListener('click', () => {
                    this.insertRow(table, opt);
                });
            }

            if (reloadBtn) {
                reloadBtn.addEventListener('click', () => {
                    if (table._dbxSaving === true) return;
                    this.reloadTable(table, opt);
                });
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', () => {

                    const keys = [
                        'GRIDLINES',
                        'AUTOSAVE',
                        'HEIGHT',
                        'COLUMNS.ORDER',
                        'PAGE.SIZE',
                        'PAGE.NO'
                    ];

                    keys.forEach(k => uiSet(k, null));

                    const cols = table.getColumns();
                    const fields = [];

                    (function walk(cols){
                        cols.forEach(c => {
                            const f = c.getField && c.getField();
                            if (f && !f.startsWith('_')) fields.push(f);
                            if (c.getSubColumns) {
                                const sub = c.getSubColumns();
                                if (sub && sub.length) walk(sub);
                            }
                        });
                    })(cols);

                    fields.forEach(f => {
                        uiSet('COLUMNS.SIZE.' + f, null);
                        uiSet('COLUMNS.VISIBLE.' + f, null);
                    });

                    uiSet('GRIDLINES', '1');
                    uiSet('AUTOSAVE', '1');

                    if (window.dbx && dbx.utilities && dbx.utilities.leaveGuard) {
                        dbx.utilities.leaveGuard.allowOnce();
                    }
                    location.reload();
                });
            }

            if (colBtn) {
                colBtn.addEventListener('click', () => {
                    this.openColumnChooser(colBtn, table);
                });
            }

            if (excelBtn) {
                excelBtn.addEventListener('click', () => {
                    this._ensureExcelExportDeps(table, (ok) => {
                        if (ok !== true) {
                            dbx.error('[grid] excel export dependencies missing');
                            return;
                        }

                        try {
                            table.download('xlsx', (opt.exportFileName || gridId) + '.xlsx', {
                                sheetName: opt.exportSheetName || gridId
                            });
                        } catch (e) {
                            dbx.error('[grid] excel export failed', e);
                        }
                    });
                });
            }

            if (pdfBtn) {
                pdfBtn.addEventListener('click', () => {
                    this._ensurePdfExportDeps(table, (ok) => {
                        if (ok !== true) {
                            dbx.error('[grid] pdf export dependencies missing');
                            return;
                        }

                        try {
                            table.download('pdf', (opt.exportFileName || gridId) + '.pdf', {
                                orientation: opt.pdfOrientation || 'landscape',
                                title: opt.pdfTitle || document.title || 'Export'
                            });
                        } catch (e) {
                            dbx.error('[grid] pdf export failed', e);
                        }
                    });
                });
            }

            if (searchInput) {

                if (!table._dbxGlobalSearchFilter) {
                    table._dbxGlobalSearchFilter = function (data, filterParams) {
                        const val = String((filterParams && filterParams.value) || '').toLowerCase();
                        if (!val) {
                            return true;
                        }
                        const fields = (filterParams && filterParams.fields) || [];
                        for (let i = 0; i < fields.length; i++) {
                            const v = data[fields[i]];
                            if (v != null && String(v).toLowerCase().indexOf(val) !== -1) {
                                return true;
                            }
                        }
                        return false;
                    };
                }

                const getFields = () => {
                    const out = [];
                    (function walk(cols){
                        cols.forEach(c => {
                            const f = c.getField && c.getField();
                            if (f && !f.startsWith('_')) out.push(f);
                            if (c.getSubColumns) {
                                const sub = c.getSubColumns();
                                if (sub && sub.length) walk(sub);
                            }
                        });
                    })(table.getColumns());
                    return out;
                };

                let timer = null;

                const applyLocalSearch = () => {
                    const val = searchInput.value.trim();

                    if (opt.searchMode === 'remote') {
                        table._dbxSearchValue = val.toLowerCase();
                        this.reloadTable(table, opt, { resetPage: true });
                        return;
                    }

                    if (!val) {
                        table.clearFilter();
                        return;
                    }

                    table.setFilter(table._dbxGlobalSearchFilter, {
                        value: val,
                        fields: getFields()
                    });
                };

                searchInput.addEventListener('input', () => {

                    if (timer) clearTimeout(timer);

                    if (opt.searchMode === 'remote') {
                        timer = setTimeout(applyLocalSearch, 250);
                        return;
                    }

                    applyLocalSearch();
                });

                table.on('dataLoaded', () => {
                    if (opt.searchMode === 'remote') {
                        return;
                    }
                    if (!searchInput.value.trim()) {
                        return;
                    }
                    applyLocalSearch();
                });
            }

            table.on('tableBuilt', () => {
                table._dbxBuilt = true;
                if (table._dbxPageSizeState && table.getPageSize && table.getPageSize() !== table._dbxPageSizeState) {
                    try {
                        table._dbxPageSizeChanging = true;
                        table.setPageSize(table._dbxPageSizeState);
                    } catch (e) {
                        dbx.warn('[grid] restore page size failed', e);
                    } finally {
                        table._dbxPageSizeChanging = false;
                    }
                }
                el._dbxApplyGridLines(false);
                this._applySortIndicators(table);
            });
        },

        /* =========================================================
         * COLUMN CHOOSER
         * ========================================================= */
        openColumnChooser(btn, table) {

            const el     = table.element;
            const gridId = el.id || 'grid';

            const uiGet = (k, def) => dbx.uiGet('grid', gridId, k, def);
            const uiSet = (k, v)   => dbx.uiSet('grid', gridId, k, v);

            const old = document.querySelector(`.dbx-col-chooser[data-grid-id="${gridId}"]`);
            if (old) old.remove();

            const box = document.createElement('div');
            box.className = 'dbx-col-chooser shadow p-2 bg-white border rounded';
            box.dataset.gridId = gridId;

            box.style.position   = 'fixed';
            box.style.zIndex     = 9999;
            box.style.minWidth   = '260px';
            box.style.maxHeight  = '70vh';
            box.style.overflowY  = 'auto';

            const rect = btn.getBoundingClientRect();
            box.style.left = rect.left + 'px';
            box.style.top  = (rect.bottom + 4) + 'px';

            const groupMap = {};
            const allCols  = [];

            this._getLeafColumns(table).forEach(col => {

                const field = col.getField();
                if (!field || field.startsWith('_')) return;

                allCols.push(col);

                let groupTitle = '-';
                const parent = col.getParentColumn();
                if (parent) {
                    const def = parent.getDefinition();
                    if (def?.title?.trim()) groupTitle = def.title.trim();
                }

                if (!groupMap[groupTitle]) groupMap[groupTitle] = [];
                groupMap[groupTitle].push(col);
            });

            const queueWidthRestore = () => {
                this._queueTableTimer(table, '_dbxChooserTimer', () => {
                    if (!this._isTableLayoutReady(table)) return;
                    this._restoreStoredColumnWidths(table, gridId);
                }, 0);
            };

            const saveVisibility = () => {
                allCols.forEach(c => {
                    const field = c.getField();
                    uiSet('COLUMNS.VISIBLE.' + field, c.isVisible() ? '1' : '0');
                });
            };

            Object.keys(groupMap).forEach(groupTitle => {

                const cols = groupMap[groupTitle];

                const groupLabel = document.createElement('label');
                groupLabel.className = 'fw-bold d-flex align-items-center gap-2 mb-1';

                const groupCb = document.createElement('input');
                groupCb.type = 'checkbox';

                const updateGroupState = () => {
                    const visibleCount = cols.filter(c => c.isVisible()).length;
                    groupCb.checked = (visibleCount === cols.length);
                    groupCb.indeterminate = (visibleCount > 0 && visibleCount < cols.length);
                };

                updateGroupState();

                groupCb.addEventListener('change', () => {

                    if (!this._isTableLayoutReady(table)) return;

                    table.blockRedraw();

                    cols.forEach(c => {
                        const f = c.getField();
                        if (!f || f.startsWith('_')) return;

                        groupCb.checked
                            ? table.showColumn(f)
                            : table.hideColumn(f);
                    });

                    table.restoreRedraw(true);

                    saveVisibility();
                    updateGroupState();
                    queueWidthRestore();
                });

                groupLabel.appendChild(groupCb);
                groupLabel.appendChild(document.createTextNode(groupTitle));
                box.appendChild(groupLabel);

                cols.forEach(col => {

                    const field = col.getField();
                    const def   = col.getDefinition();
                    const labelText = def?.title ? def.title : field;

                    const label = document.createElement('label');
                    label.className = 'd-flex align-items-center gap-2 small ms-3';

                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.checked = col.isVisible();

                    cb.addEventListener('change', () => {

                        if (!this._isTableLayoutReady(table)) return;

                        table.blockRedraw();

                        cb.checked
                            ? table.showColumn(field)
                            : table.hideColumn(field);

                        table.restoreRedraw(true);

                        saveVisibility();
                        updateGroupState();
                        queueWidthRestore();
                    });

                    label.appendChild(cb);
                    label.appendChild(document.createTextNode(labelText));
                    box.appendChild(label);
                });

                box.appendChild(document.createElement('hr'));
            });

            document.body.appendChild(box);

            setTimeout(() => {
                const close = (e) => {
                    if (!box.contains(e.target) && e.target !== btn) {
                        box.remove();
                        document.removeEventListener('click', close);
                    }
                };
                document.addEventListener('click', close);
            }, 0);
        },


        /* =========================================================
         * PARAMS / REMOTE STATE
         * ========================================================= */
        buildAjaxParams(table, params, optFallback) {

            const out = Object.assign({}, params || {});
            const gridEl = table && table.element ? table.element : null;
            const opt = (gridEl && gridEl._dbxOpt) ? gridEl._dbxOpt : (optFallback || {});
            const gridId = (gridEl && gridEl.id) ? gridEl.id : (opt._gridId || 'grid');
            const uiSet = (k, v) => dbx.uiSet('grid', gridId, k, v);

            delete out.sorters;
            delete out.filter;
            delete out.filters;

            if (table && table._dbxSearchValue) {
                out.dbx_search = table._dbxSearchValue;
            }

            const isRemote = table && table._dbxIsRemotePagination === true;

            if (isRemote) {
                const page = parseInt(out.page, 10) || (table.getPage ? table.getPage() : 1) || 1;
                const rawSize = out.size ?? (table.getPageSize ? table.getPageSize() : null) ?? opt.pageSize ?? 50;
                const normalizedSize = this._normalizePageSizeValue(rawSize, opt.pageSize || 15, opt.paginationSizeSelector);
                const size = normalizedSize;

                out.page = page;
                out.size = size;

                uiSet('PAGE.NO', page);
                this._storePageSizeState(gridId, normalizedSize, opt.pageSize || 15, opt.paginationSizeSelector);
            }

            if (!table || typeof table.getHeaderFilters !== 'function') {
                return out;
            }

            const sorters = this._getAjaxSorters(table);
            if (Array.isArray(sorters) && sorters.length) {
                out.dbx_sorters = JSON.stringify(sorters);
            }

            const headerFilters = table.getHeaderFilters ? table.getHeaderFilters() : [];
            if (Array.isArray(headerFilters) && headerFilters.length) {
                out.dbx_filters = JSON.stringify(headerFilters);
            }

            return out;
        },


        /* =========================================================
         * RELOAD
         * ========================================================= */
        reloadTable(table, opt, cfg = {}) {

            const resetPage = !!cfg.resetPage;

            if (table._dbxSaving === true) return;

            if (table._dbxIsRemotePagination) {
                if (resetPage) {
                    try {
                        table.setPage(1);
                    } catch (e) {
                        dbx.warn('[grid] remote reset page failed', e);
                    }
                }
                table.replaceData();
                return;
            }

            if (table._dbxIsProgressive) {
                table.setData(this._dbxAjaxUrl(opt.urls.read));
                return;
            }

            table.replaceData(this._dbxAjaxUrl(opt.urls.read));
        },

        insertRow(table, opt) {

            if (!table || !opt || !opt.urls || !opt.urls.insert) return;
            if (table._dbxSaving === true) return;

            const url = this._dbxAjaxUrl(opt.urls.insert);
            table._dbxSaving = true;

            this._dbxRequest(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({}),
                responseType: 'json'
            })
            .then(resp => {
                table._dbxSaving = false;

                if (!resp || !(resp.ok || resp.success)) {
                    dbx.error('[grid] insert failed', resp);
                    return;
                }

                const row = resp.row || (Array.isArray(resp.rows) ? resp.rows[0] : null);
                if (row) {
                    table.addData([row], false);
                } else {
                    this.reloadTable(table, opt);
                }
            })
            .catch(err => {
                table._dbxSaving = false;
                dbx.error('[grid] insert error', err);
            });
        },


        /* =========================================================
         * SYNC
         * ========================================================= */
        bindSyncLoop(el, table, opt) {

            const syncUrl = opt.urls.sync;
            if (!syncUrl) return;

            const syncEls = this._getSyncEls(el);

            if (syncEls.led) {
                syncEls.led._dbxSyncLedEnabled = (opt.syncLed !== false);

                if (opt.syncLed === false) {
                    syncEls.led.style.display = 'none';
                } else {
                    syncEls.led.style.display = 'inline-block';
                }
            }

            if (opt.syncRun === false) {
                dbx.log('[grid][sync] disabled by sync_run=0', {
                    id: el.id || 'grid'
                });
                return;
            }

            let synctime = parseFloat(opt.cfg.synctime || 2);
            if (isNaN(synctime)) synctime = 2;

            if (synctime === 0) return;
            if (synctime < 0.5) synctime = 0.5;
            if (synctime > 60) synctime = 60;

            const interval = Math.round(synctime * 1000);

            const loopId = 'grid-sync-' + (el.id || 'grid') + '-' + Date.now();
            table._dbxLoopId = loopId;
            table._dbxSyncRunning = false;
            table._dbxSyncMode = opt.syncMode || 'delta';

            dbx.log('[grid][sync] bind', {
                id: el.id || 'grid',
                synctime: synctime,
                interval: interval,
                mode: table._dbxSyncMode,
                remotePagination: table._dbxIsRemotePagination === true
            });

            dbx.loop.add({
                id: loopId,
                timing: {
                    base: interval,
                    idle: Math.max(interval * 2, interval + 1000),
                    hidden: Math.max(interval * 3, interval + 2000),
                    min: 500,
                    max: 60000
                },
                onRun: () => {

                    if (table._dbxSyncRunning === true) return;
                    if (table._dbxSaving === true) return;
                    if (!table._dbxServerTime) return;
                    if (!dbx.device.isVisible()) return;
                    if (!this._isTableAlive(table)) return;

                    table._dbxSyncRunning = true;

                    const startedAt = Date.now();

                    dbx.log('[grid][sync] request start', {
                        id: el.id || 'grid',
                        last_update: table._dbxServerTime,
                        remotePagination: table._dbxIsRemotePagination === true,
                        page: table._dbxIsRemotePagination ? (table.getPage() || 1) : null,
                        size: table._dbxIsRemotePagination ? (table.getPageSize() || opt.pageSize || 50) : null
                    });

                    let loadingTimer = setTimeout(() => {
                        if (table._dbxSyncRunning === true) {
                            dbx.log('[grid][sync] loader threshold reached', {
                                id: el.id || 'grid'
                            });
                            this._setLedState(syncEls.led, 'loading');
                        }
                    }, 120);

                    const editedMap = this._collectEditedMap(table);

                    let url = this._dbxAjaxUrl(syncUrl, { background: true }) +
                        '&last_update=' + encodeURIComponent(table._dbxServerTime);

                    if (table._dbxIsRemotePagination) {
                        url += '&dbx_page=' + encodeURIComponent(table.getPage() || 1);
                        url += '&dbx_size=' + encodeURIComponent(table.getPageSize() || opt.pageSize || 50);
                    }

                    return this._dbxRequest(url, {
                        method: 'GET',
                        responseType: 'json'
                    })
                        .then(res => {

                            const rows = Array.isArray(res?.rows) ? res.rows : [];

                            dbx.log('[grid][sync] response', {
                                id: el.id || 'grid',
                                ok: res?.ok,
                                rows: rows.length,
                                count: (typeof res?.count !== 'undefined') ? res.count : null
                            });

                            if (!res || res.ok !== 1) {
                                this._setLedState(syncEls.led, 'idle');
                                return;
                            }

                            if (typeof res.server_time !== 'undefined' && res.server_time) {
                                table._dbxServerTime = res.server_time;
                            }

                            if (typeof res.count !== 'undefined') {
                                this._setSyncCount(syncEls.count, res.count || '');
                            }

                            if (!rows.length) {
                                this._setLedState(syncEls.led, 'idle');
                                return;
                            }

                            if (table._dbxIsRemotePagination) {

                                dbx.log('[grid][sync] remote reload triggered', {
                                    id: el.id || 'grid',
                                    rows: rows.length
                                });

                                this.reloadTable(table, opt, {
                                    reason: 'sync-remote-delta'
                                });

                                this._setLedState(syncEls.led, 'ok');
                                return;
                            }

                            let changed = 0;

                            for (let i = 0; i < rows.length; i++) {

                                const r = rows[i];
                                if (!r || typeof r.id === 'undefined') continue;

                                const row = table.getRow(r.id);

                                if (!row) {
                                    table.addData([r], false);
                                    changed++;
                                    continue;
                                }

                                const data = row.getData();
                                const editedFields = editedMap[r.id] || null;
                                const patch = {};

                                for (const k in r) {

                                    if (k === 'id') continue;

                                    const newVal = r[k];
                                    const oldVal = data[k];

                                    if (
                                        newVal === oldVal ||
                                        String(newVal) === String(oldVal)
                                    ) {
                                        continue;
                                    }

                                    if (editedFields && editedFields[k] === true) {

                                        const cell = row.getCell(k);
                                        if (cell && newVal !== oldVal) {
                                            const cellEl = cell.getElement();
                                            if (cellEl) {
                                                cellEl.classList.add('dbx-cell-conflict');
                                            }
                                        }

                                        continue;
                                    }

                                    patch[k] = newVal;
                                }

                                const keys = Object.keys(patch);
                                if (!keys.length) continue;

                                row.update(patch);
                                changed++;

                                keys.forEach(k => {
                                    const cell = row.getCell(k);
                                    if (cell) {
                                        this._applySchemaCellStyle(cell, row.getData());
                                    }
                                });
                            }

                            dbx.log('[grid][sync] local apply done', {
                                id: el.id || 'grid',
                                incoming: rows.length,
                                changed: changed
                            });

                            this._setLedState(syncEls.led, changed > 0 ? 'ok' : 'idle');
                        })
                        .catch(err => {
                            dbx.error('[grid][sync] error', err);
                            this._setLedState(syncEls.led, 'error');
                        })
                        .finally(() => {
                            clearTimeout(loadingTimer);
                            loadingTimer = null;
                            table._dbxSyncRunning = false;

                            dbx.log('[grid][sync] request end', {
                                id: el.id || 'grid',
                                duration_ms: (Date.now() - startedAt)
                            });
                        });
                }
            });
        },


        /* =========================================================
         * CREATE TABLE
         * ========================================================= */
        createTable(el, opt) {

            if (el._dbxGridInitialized) return;
            el._dbxGridInitialized = true;

            const gridId = el.id || 'grid';
            opt._gridId = gridId;

            const uiGet = (k, def) => dbx.uiGet('grid', gridId, k, def);
            const uiSet = (k, v) => dbx.uiSet('grid', gridId, k, v);

            const schemaName =
                opt.cfg && typeof opt.cfg.schema === 'string'
                    ? opt.cfg.schema.trim()
                    : '';

            const buildGrid = () => {

                const isRemotePagination =
                    opt.pagination === true &&
                    (opt.paginationMode === 'remote');

                const isProgressive =
                    (opt.progressiveLoad === 'scroll' || opt.progressiveLoad === 'load');

                opt._dbxIsRemotePagination = isRemotePagination;
                opt._dbxIsProgressive = isProgressive;

                const columns = this.buildColumns(opt);

                const pageSizeStored = this._getPageSizeState(
                    gridId,
                    opt.pageSize || 15,
                    opt.paginationSizeSelector
                );
                const pageSizeInitial = pageSizeStored === 1 ? 15 : pageSizeStored;
                opt.pageSize = pageSizeInitial;
                const pageNoStored   = this._int(uiGet('PAGE.NO', 1), 1);

                const heightStoredRaw = opt.height === false ? null : uiGet('HEIGHT', null);
                const heightStoredInt = heightStoredRaw !== null ? parseInt(heightStoredRaw, 10) : NaN;
                const initialHeightRaw = opt.height === false ? false : (!isNaN(heightStoredInt) ? heightStoredInt : opt.height);
                const heightMinBound = Math.max(120, parseInt(opt.heightMin, 10) || 320);
                const heightMaxBound = Math.max(heightMinBound, parseInt(opt.heightMax, 10) || 960);
                const initialHeight = initialHeightRaw === false
                    ? false
                    : Math.min(heightMaxBound, Math.max(heightMinBound, initialHeightRaw));

                const paginationUi = this._getPaginationUiEls(el);

                dbx.log('[grid] createTable', {
                    id: gridId,
                    remotePagination: isRemotePagination,
                    progressive: isProgressive,
                    pageSizeStored: pageSizeStored,
                    pageNoStored: pageNoStored,
                    initialHeight: initialHeight,
                    searchMode: opt.searchMode,
                    syncRun: opt.syncRun,
                    syncLed: opt.syncLed,
                    dedicatedServerSort: !!(opt.headerSort === true && opt.urls.sort && !isRemotePagination),
                    paginationCounter: opt.paginationCounter,
                    paginationSizeSelector: opt.paginationSizeSelector
                });

                let table = null;

                const ajaxURLGenerator = (url, config, params) => {

                    let finalUrl = this._dbxAjaxUrl(url);
                    const merged = this.buildAjaxParams(table, params || {}, opt);

                    const usp = new URLSearchParams();

                    Object.keys(merged).forEach(key => {
                        const val = merged[key];
                        if (val === undefined || val === null || val === '') return;
                        usp.append(key, val);
                    });

                    if (String(finalUrl).includes('?')) {
                        finalUrl += '&' + usp.toString();
                    } else {
                        finalUrl += '?' + usp.toString();
                    }

                    dbx.log('[grid][ajaxURL]', {
                        id: gridId,
                        url: finalUrl,
                        params: merged
                    });

                    return finalUrl;
                };

                const ajaxRequestFunc = (url, config, params) => {

                    const method =
                        (typeof config === 'string')
                            ? config
                            : ((config && config.method) ? config.method : 'GET');

                    dbx.log('[grid][ajaxRequestFunc]', {
                        id: gridId,
                        method: method,
                        url: url,
                        params: params || {}
                    });

                    return this._dbxRequest(url, {
                        method: method,
                        responseType: 'json'
                    });
                };

                const tabulatorOptions = {

                    height: initialHeight,
                    minHeight: opt.minHeight || false,
                    maxHeight: opt.maxHeight || false,
                    layout: (opt.cfg && opt.cfg.layout) ? opt.cfg.layout : 'fitColumns',
                    responsiveLayout: opt.responsiveLayout || false,
                    placeholder: String(opt.cfg.placeholder ?? ''),

                    dataLoader: false,

                    sortMode: isRemotePagination ? 'remote' : 'local',

                    filterMode: opt.searchMode === 'remote' ? 'remote' : 'local',

                    ajaxURL: this._dbxAjaxUrl(opt.urls.read),
                    ajaxConfig: 'GET',
                    ajaxContentType: 'json',
                    ajaxURLGenerator: ajaxURLGenerator,
                    ajaxRequestFunc: ajaxRequestFunc,
                    ajaxResponse: (url, params, response) => this._ajaxResponse(table, url, params, response),

                    pagination: opt.pagination === true,
                    paginationMode: isRemotePagination ? 'remote' : 'local',
                    paginationSize: pageSizeInitial,
                    paginationInitialPage: pageNoStored,
                    paginationAddRow: opt.paginationAddRow || 'page',
                    paginationButtonCount: opt.paginationButtonCount || 5,
                    progressiveLoad: isProgressive ? opt.progressiveLoad : false,

                    index: String(opt.cfg.index || 'id'),
                    columns: columns,

                    reactiveData: false,
                    movableColumns: opt.movableColumns !== false,
                    resizableColumns: opt.resizableColumns !== false,

                    rowFormatter: function(row) {

                        const rowEl = row.getElement();
                        if (!rowEl) return;

                        const schema = row.getTable().element._dbxSchemaParsed;
                        if (!schema || !Array.isArray(schema.rows)) return;

                        const data = row.getData();

                        rowEl.style.removeProperty('background-color');
                        rowEl.style.removeProperty('color');

                        for (let i = 0; i < schema.rows.length; i++) {
                            const rule = schema.rows[i];
                            if (!dbxGrid.evalRule(rule, null, data)) continue;

                            if (rule.style?.bg) {
                                rowEl.style.setProperty('background-color', rule.style.bg, 'important');
                            }
                            if (rule.style?.color) {
                                rowEl.style.setProperty('color', rule.style.color, 'important');
                            }
                            break;
                        }
                    }
                };

                if (opt.pagination === true && opt.paginationControls === true && paginationUi.controls) {
                    tabulatorOptions.paginationElement = paginationUi.controls;
                }

                if (opt.pagination === true && opt.paginationCounter !== false) {
                    tabulatorOptions.paginationCounter = opt.paginationCounter;

                    if (paginationUi.counter) {
                        tabulatorOptions.paginationCounterElement = paginationUi.counter;
                    }
                }

                if (opt.pagination === true && opt.paginationSizeSelector !== false) {
                    tabulatorOptions.paginationSizeSelector = opt.paginationSizeSelector;
                }

                if (opt.pagination === true && opt.paginationOutOfRange !== false) {
                    tabulatorOptions.paginationOutOfRange = opt.paginationOutOfRange;
                }

                table = new Tabulator(el, tabulatorOptions);

                table._dbxIsRemotePagination = isRemotePagination;
                table._dbxIsProgressive = isProgressive;
                table._dbxSortRestored = false;
                table._dbxDirty = false;
                table._dbxSaving = false;
                table._dbxAutoTimer = null;
                table._dbxSearchValue = '';
                table._dbxLayoutRestored = false;
                table._dbxServerSort = null;
                table._dbxPageLayoutTimer = null;
                table._dbxBuilt = false;
                table._dbxPageSizeState = pageSizeStored;

                const syncEls = this._getSyncEls(el);

                if (syncEls.led) {
                    syncEls.led._dbxSyncLedEnabled = (opt.syncLed !== false);

                    if (opt.syncLed === false) {
                        syncEls.led.style.display = 'none';
                    } else {
                        syncEls.led.style.display = 'inline-block';
                    }
                }

                const queueLocalPageStabilize = (reason) => {

                    if (table._dbxIsRemotePagination === true) return;
                    if (table._dbxLayoutRestored !== true) return;

                    this._queueTableTimer(table, '_dbxPageLayoutTimer', () => {

                        if (!this._isTableLayoutReady(table) || table._dbxBuilt !== true) {
                            this._queueTableTimer(table, '_dbxPageLayoutTimer', () => {
                                queueLocalPageStabilize(reason);
                            }, 30);
                            return;
                        }

                        dbx.log('[grid] local page stabilize start', {
                            id: gridId,
                            reason: reason,
                            page: table.getPage ? table.getPage() : null
                        });

                        try {
                            table.redraw(true);
                        } catch (e) {
                            dbx.warn('[grid] local page redraw failed', e);
                        }

                        this._applySortIndicators(table);

                        dbx.log('[grid] local page stabilize done', {
                            id: gridId,
                            reason: reason,
                            page: table.getPage ? table.getPage() : null
                        });

                    }, 0);
                };

                table.on('pageLoaded', (pageno) => {
                    uiSet('PAGE.NO', pageno);
                    this._storePageSizeState(
                        gridId,
                        table._dbxPageSizeState || (table.getPageSize ? table.getPageSize() : opt.pageSize),
                        opt.pageSize,
                        opt.paginationSizeSelector
                    );

                    dbx.log('[grid] pageLoaded', {
                        id: gridId,
                        page: pageno,
                        pageSize: table.getPageSize()
                    });

                    this._applyPaginationButtonLabels(table);

                    if (table._dbxIsRemotePagination !== true) {
                        queueLocalPageStabilize('pageLoaded');
                    }
                });

                table.on('cellEdited', (cell) => {

                    if (opt.allowEdit === false) return;

                    this._markTableDirty(table, el);

                    dbx.log('[grid] cellEdited', {
                        id: gridId,
                        rowId: cell?.getRow?.()?.getData?.()?.id,
                        field: cell?.getField?.()
                    });

                    const autosave = uiGet('AUTOSAVE', '1') == '1';
                    if (!autosave) return;

                    if (table._dbxAutoTimer) {
                        clearTimeout(table._dbxAutoTimer);
                    }

                    table._dbxAutoTimer = setTimeout(() => {

                        if (table._dbxSaving === true) return;
                        if (this._syncDirtyState(table) !== true) return;

                        dbx.log('[grid] autosave trigger', {
                            id: gridId
                        });

                        this.saveTable(table, opt);

                    }, 300);
                });

                table.on('renderComplete', () => {
                    if (opt.allowEdit !== false) {
                        this.updateSaveButton(el, table);
                    }
                });

                table.on('dataLoaded', (data) => {

                    const syncEls = this._getSyncEls(el);

                    if (typeof table._dbxSyncCount !== 'undefined') {
                        this._setSyncCount(syncEls.count, table._dbxSyncCount || '');
                    }

                    if (table._dbxPageSizeState === 1 && table._dbxPageSizeOneApplied !== true) {
                        table._dbxPageSizeOneApplied = true;

                        try {
                            table._dbxPageSizeChanging = true;
                            table.setPageSize(1);
                            if (typeof table.setPage === 'function') {
                                table.setPage(1);
                            }
                            table.redraw(true);
                        } catch (e) {
                            dbx.warn('[grid] restore page size 1 failed', e);
                        } finally {
                            table._dbxPageSizeChanging = false;
                        }
                    }

                    this._applySortIndicators(table);
                    this._applyPaginationButtonLabels(table);

                    dbx.log('[grid] dataLoaded', {
                        id: gridId,
                        rows: Array.isArray(data) ? data.length : null,
                        sortRestored: table._dbxSortRestored === true,
                        remotePagination: table._dbxIsRemotePagination === true,
                        progressive: table._dbxIsProgressive === true
                    });

                    if (table._dbxIsRemotePagination !== true) {
                        queueLocalPageStabilize('dataLoaded');
                    }

                    if (!table._dbxSortRestored) {
                        table._dbxSortRestored = true;
                        this.bindSyncLoop(el, table, opt);
                    }
                });

                table.on('dataSorted', () => {
                    this._applySortIndicators(table);
                });

                table.on('renderComplete', () => {
                    this._applySortIndicators(table);
                    this._applyPaginationButtonLabels(table);
                });

                table.on('columnsLoaded', () => {

                    if (table._dbxLayoutRestored === true) {
                        dbx.log('[grid] columnsLoaded skipped (already restored)', {
                            id: gridId
                        });
                        return;
                    }

                    dbx.log('[grid] columnsLoaded -> restore layout start', {
                        id: gridId
                    });

                    const tryRestore = () => {

                        if (table._dbxLayoutRestored === true) return;

                        const applied = this._applyInitialLayoutState(table, gridId);

                        if (applied !== true) {
                            this._queueTableTimer(table, '_dbxLayoutTimer', tryRestore, 30);
                            return;
                        }

                        table._dbxLayoutRestored = true;
                        this._applySortIndicators(table);

                        dbx.log('[grid] columnsLoaded -> restore layout done', {
                            id: gridId
                        });
                    };

                    this._queueTableTimer(table, '_dbxLayoutTimer', tryRestore, 0);
                });

                this.bindLayoutState(el, table);

                el._dbxTable   = table;
                el._dbxFeature = this;
                el._dbxOpt     = opt;

                this.bindToolbar(
                    el,
                    table,
                    opt,
                    opt._uiState || (opt._uiState = {}),
                    el.closest('.dbx-grid')
                );

                this.updateSaveButton(el, table);
            };

            if (schemaName) {
                this.loadSchema(schemaName, () => {
                    el._dbxSchemaParsed = dbxGridParseSchema(window.dbxGridSchema[schemaName]);
                    buildGrid();
                });
            } else {
                buildGrid();
            }
        },

        /* =========================================================
         * SAVE
         * ========================================================= */
        saveTable(table, opt) {

            if (!opt || !opt.urls || !opt.urls.save) {
                table._dbxSaving = false;
                dbx.error('[grid] saveTable → missing save URL', {
                    id: table.element?.id || 'undef'
                });
                return;
            }

            if (table._dbxSaving === true) {
                return;
            }

            if (table._dbxDirty !== true && this._syncDirtyState(table) !== true) {
                table._dbxSaving = false;
                return;
            }

            const editedCells = table.getEditedCells();

            if (!editedCells || editedCells.length === 0) {

                table._dbxSaving = false;
                table._dbxDirty = false;

                if (table.element && table.element._dbxFeature) {
                    table.element._dbxFeature.updateSaveButton(table.element, table);
                }

                return;
            }

            const rowsMap = {};

            editedCells.forEach(cell => {

                const row = cell.getRow();
                if (!row) return;

                const data = row.getData();
                const idField = this._rowIdField(table);
                if (!data || typeof data[idField] === 'undefined') return;

                if (!rowsMap[data[idField]]) {
                    rowsMap[data[idField]] = Object.assign({}, data);
                }
            });

            const rows = Object.values(rowsMap);

            if (!rows.length) {

                table._dbxSaving = false;
                table._dbxDirty = false;

                if (table.element && table.element._dbxFeature) {
                    table.element._dbxFeature.updateSaveButton(table.element, table);
                }

                return;
            }

            const url = this._dbxAjaxUrl(opt.urls.save);

            table._dbxSaving = true;

            this._dbxRequest(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ rows: rows }),
                responseType: 'json'
            })
            .then(resp => {

                table._dbxSaving = false;
                table._dbxDirty  = false;

                table.getEditedCells().forEach(cell => {
                    try {
                        cell.clearEdited();
                    } catch (e) {}
                });

                this._clearConflictFlags(table);

                if (table.element && table.element._dbxFeature) {
                    table.element._dbxFeature.updateSaveButton(table.element, table);
                }

            })
            .catch(err => {
                table._dbxSaving = false;
                this._syncDirtyState(table);
                if (table.element && table.element._dbxFeature) {
                    table.element._dbxFeature.updateSaveButton(table.element, table);
                }
                dbx.error('[grid] SAVE failed', {
                    error: err,
                    url: url
                });
            });
        }

    });


    function dbxExtractLabel(token) {
        if (!token) return { key:'', label:'' };
        const m = token.match(/^([^\[]+)\[(.+)\]$/);
        if (!m) return { key: token.trim(), label: token.trim() };
        return { key: m[1].trim(), label: m[2].trim() };
    }

    function dbxGridParseColumnOptions(raw) {
        const out = {};
        String(raw || '').split(';').forEach(part => {
            part = part.trim();
            if (!part) return;

            const pos = part.indexOf('=');
            if (pos === -1) {
                out[part] = '1';
                return;
            }

            const key = part.substring(0, pos).trim();
            const value = part.substring(pos + 1).trim();
            if (key) out[key] = value;
        });

        return out;
    }

    function dbxGridParseEditorValues(raw) {
        const values = {};

        String(raw || '').split('~').forEach(part => {
            const pos = part.indexOf('=');
            let value = part;
            let label = part;

            if (pos !== -1) {
                value = part.substring(0, pos);
                label = part.substring(pos + 1);
            }

            values[value] = label;
        });

        return values;
    }


    /* =================================================
     * [dbx][grid][schema][step2]
     * schema parser & normalizer
     * ================================================= */
    (function() {

        if (!window.dbx) return;

        window.dbxGridParseSchema = function(rawSchema) {

            const out = {
                meta: rawSchema.meta || {},
                conditions: rawSchema.conditions || {},
                rows: [],
                columns: {}
            };

            if (Array.isArray(rawSchema.rows)) {
                rawSchema.rows.forEach(rowRule => {

                    const norm = dbxGridNormalizeRule(
                        rowRule,
                        out.conditions,
                        null
                    );

                    if (!norm) return;

                    norm.style = {
                        bg: rowRule.bg || null,
                        color: rowRule.color || null,
                        cls: rowRule.cls || null
                    };

                    out.rows.push(norm);
                });
            }

            if (!rawSchema.columns || typeof rawSchema.columns !== 'object') {
                return out;
            }

            Object.keys(rawSchema.columns).forEach(colName => {

                const colDef = rawSchema.columns[colName];
                if (!colDef || !Array.isArray(colDef.rules)) return;

                out.columns[colName] = { rules: [] };

                colDef.rules.forEach(rule => {

                    const norm = dbxGridNormalizeRule(
                        Object.assign({}, rule, { col: colName }),
                        out.conditions,
                        colName
                    );

                    if (!norm) return;

                    out.columns[colName].rules.push(norm);
                });
            });

            return out;
        };

        function dbxGridNormalizeRule(rule, conditions, currentCol) {

            if (!rule || typeof rule !== 'object') return null;

            const resolveCondition = (c) => {
                if (typeof c === 'string') {
                    if (!conditions[c]) {
                        console.warn('[normalize] unknown condition', c);
                        return null;
                    }
                    return Object.assign({}, conditions[c]);
                }
                return Object.assign({}, c);
            };

            if (Array.isArray(rule.all)) {

                if (rule.all.length === 0) {
                    return {
                        all: [],
                        style: {
                            bg: rule.bg || null,
                            color: rule.color || rule.text || null,
                            cls: rule.cls || null
                        }
                    };
                }

                const subs = rule.all
                    .map(resolveCondition)
                    .map(r => dbxGridNormalizeRule(r, conditions, currentCol))
                    .filter(Boolean);

                if (!subs.length) return null;

                return {
                    all: subs,
                    style: {
                        bg: rule.bg || null,
                        color: rule.color || rule.text || null,
                        cls: rule.cls || null
                    }
                };
            }

            if (Array.isArray(rule.any)) {

                const subs = rule.any
                    .map(resolveCondition)
                    .map(r => dbxGridNormalizeRule(r, conditions, currentCol))
                    .filter(Boolean);

                if (!subs.length) return null;

                return {
                    any: subs,
                    style: {
                        bg: rule.bg || null,
                        color: rule.color || rule.text || null,
                        cls: rule.cls || null
                    }
                };
            }

            const col = rule.col || currentCol;

            if (!col) {
                console.warn('[normalize] rule dropped (no col)', rule);
                return null;
            }

            return {
                col,
                if: rule.if,
                normalize: rule.normalize,
                value: rule.value,
                isReserved: rule.isReserved || false,
                style: {
                    bg: rule.bg || null,
                    color: rule.color || rule.text || null,
                    cls: rule.cls || null
                }
            };
        }

    })();


    /* =================================================
     * [dbx][grid][schema][step3]
     * rule evaluator
     * ================================================= */
    (function() {

        window.dbxGrid.evalCell = function(colRules, cellValue, rowData) {

            if (!colRules || !Array.isArray(colRules.rules)) {
                return null;
            }

            let finalStyle = null;
            let matched = false;

            for (let i = 0; i < colRules.rules.length; i++) {

                const rule = colRules.rules[i];
                const ok = window.dbxGrid.evalRule(rule, cellValue, rowData);

                if (!ok) continue;

                matched = true;

                if (rule.style) {
                    finalStyle = Object.assign({}, finalStyle || {}, rule.style);
                }
            }

            if (matched) {
                return finalStyle || {};
            }

            return null;
        };

        window.dbxGrid.evalRule = function(rule, cellValue, rowData) {

            if (!rule) return false;

            if (Array.isArray(rule.all)) {
                return rule.all.every(r =>
                    window.dbxGrid.evalRule(r, cellValue, rowData)
                );
            }

            if (Array.isArray(rule.any)) {
                return rule.any.some(r =>
                    window.dbxGrid.evalRule(r, cellValue, rowData)
                );
            }

            let left;

            if (rule.col === '$cell') {
                left = cellValue;
            } else if (rule.col) {
                left = rowData[rule.col];
            } else {
                left = cellValue;
            }

            if (typeof left === 'string' && rule.normalize === 'trim') {
                left = left.trim();
            }

            const right = dbxResolveCompareValue(
                rule.value,
                rule.isReserved || false,
                rowData
            );

            return dbxCompare(left, rule.if, right);
        };

        function dbxResolveCompareValue(value, isReserved, rowData) {

            if (isReserved) {
                if (value === 'today') {
                    const d = new Date();
                    d.setHours(0, 0, 0, 0);
                    return d;
                }

                if (typeof value === 'string' && /^[+-]\d+(day|month|year)s?$/.test(value)) {
                    return dbxShiftDate(new Date(), value);
                }

                return value;
            }

            if (typeof value === 'string' && value.charAt(0) === '$') {
                return rowData[value.substring(1)];
            }

            return value;
        }

        function dbxShiftDate(base, expr) {
            const d = new Date(base);
            const n = parseInt(expr, 10);

            if (expr.includes('day')) d.setDate(d.getDate() + n);
            if (expr.includes('month')) d.setMonth(d.getMonth() + n);
            if (expr.includes('year')) d.setFullYear(d.getFullYear() + n);

            return d;
        }

        function dbxCompare(left, op, right) {

            if (left === null || left === undefined) left = '';
            if (right === null || right === undefined) right = '';

            if (op === 'empty') return left === '';
            if (op === 'notEmpty') return left !== '';
            if (op === 'startsWith') return String(left).startsWith(String(right));
            if (op === 'contains') return String(left).includes(String(right));
            if (op === '==') return left == right;
            if (op === '!=') return left != right;

            const l = dbxToComparable(left);
            const r = dbxToComparable(right);

            if (l === null || r === null) return false;

            if (op === '<')  return l < r;
            if (op === '<=') return l <= r;
            if (op === '>')  return l > r;
            if (op === '>=') return l >= r;

            return false;
        }

        function dbxToComparable(v) {

            if (v instanceof Date) return v.getTime();

            if (typeof v === 'string') {
                const d = dbxParseDate(v);
                if (d) return d.getTime();
            }

            if (!isNaN(v)) return Number(v);

            return null;
        }

        window.dbxParseDate = function(v) {

            if (!v) return null;

            if (/^\d{4}-\d{2}-\d{2}$/.test(v)) {
                const d = new Date(v);
                return isNaN(d) ? null : d;
            }

            if (/^\d{2}\.\d{2}\.\d{4}$/.test(v)) {
                const [d, m, y] = v.split('.');
                const dt = new Date(`${y}-${m}-${d}`);
                return isNaN(dt) ? null : dt;
            }

            return null;
        };

    })();


    /* =================================================
     * [dbx][grid][schema][step4]
     * apply style helper
     * ================================================= */
    window.dbxGridApplyCellStyle = function(cell, style) {

        const el = cell.getElement();
        if (!el || !style) return;

        if (style.bg) {
            el.style.removeProperty('background-color');
            el.style.setProperty('background-color', style.bg, 'important');
        }

        if (style.color) {
            el.style.removeProperty('color');
            el.style.setProperty('color', style.color, 'important');
        }

        if (style.cls) {
            el.classList.add(style.cls);
        }
    };

})();
