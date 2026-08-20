/*!
 * dbxapp grid-state.js - layout, pagination and dirty state
 */
(function (window) {
    "use strict";

    const feature = window.dbx && window.dbx.feature && window.dbx.feature._features.grid;
    if (!feature) {
        console.error('[dbx][grid] feature missing before runtime extension');
        return;
    }

    Object.assign(feature, {
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
                    icon.setAttribute('data-dbx-tooltip', text.rowsPerPage);
                    icon.setAttribute('aria-hidden', 'true');

                    controls.insertBefore(icon, sizeSelect);
                }

                sizeSelect.setAttribute('data-dbx-tooltip', text.rowsPerPage);
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
                        btn.setAttribute('data-dbx-tooltip', label);
                        btn.setAttribute('aria-label', label);
                    }
                    return;
                }

                btn.dataset.dbxPageType = type;
                btn.innerHTML = defs[type].html;
                btn.setAttribute('data-dbx-tooltip', defs[type].label);
                btn.setAttribute('aria-label', defs[type].label);
            });
        },

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
            if (!table || table._dbxBuilt !== true || typeof table.getEditedCells !== 'function') {
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
            if (!table || table._dbxBuilt !== true || typeof table.getEditedCells !== 'function') return editedMap;
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

    });
})(window);
