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
            ['js','lib','grid-state.js'],
            ['js','lib','grid-export.js'],
            ['js','lib','grid-transport.js'],
            ['js','lib','grid-columns.js'],
            ['js','lib','grid-ui.js'],
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
