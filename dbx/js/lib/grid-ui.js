/*!
 * dbxapp grid-ui.js - toolbar, layout and column chooser
 */
(function (window) {
    "use strict";

    const feature = window.dbx && window.dbx.feature && window.dbx.feature._features.grid;
    if (!feature) {
        console.error('[dbx][grid] feature missing before runtime extension');
        return;
    }

    Object.assign(feature, {
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
    });
})(window);
