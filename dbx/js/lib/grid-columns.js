/*!
 * dbxapp grid-columns.js - columns, editors and row actions
 */
(function (window) {
    "use strict";

    function dbxExtractLabel(token) {
        if (!token) return { key: '', label: '' };
        const match = token.match(/^([^\[]+)\[(.+)\]$/);
        if (!match) return { key: token.trim(), label: token.trim() };
        return { key: match[1].trim(), label: match[2].trim() };
    }

    function dbxGridParseColumnOptions(raw) {
        const options = {};
        String(raw || '').split(';').forEach(part => {
            part = part.trim();
            if (!part) return;
            const separator = part.indexOf('=');
            if (separator === -1) {
                options[part] = '1';
                return;
            }
            const key = part.substring(0, separator).trim();
            if (key) options[key] = part.substring(separator + 1).trim();
        });
        return options;
    }

    function dbxGridParseEditorValues(raw) {
        const values = {};
        String(raw || '').split('~').forEach(part => {
            const separator = part.indexOf('=');
            const value = separator === -1 ? part : part.substring(0, separator);
            values[value] = separator === -1 ? part : part.substring(separator + 1);
        });
        return values;
    }

    const feature = window.dbx && window.dbx.feature && window.dbx.feature._features.grid;
    if (!feature) {
        console.error('[dbx][grid] feature missing before runtime extension');
        return;
    }

    Object.assign(feature, {
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
                    // callerEl statt source: nur fuer Z-Index/Fokus-Layering.
                    // "source" wuerde confirm.js veranlassen, nach "Ja" die
                    // urspruengliche Aktion fortzusetzen (source.click()) -
                    // das wuerde den Delete-Button erneut ausloesen und den
                    // Bestaetigungsdialog sofort ein zweites Mal oeffnen.
                    callerEl: source,
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
                        btnShow.dataset.dbxTooltip = 'Anzeigen';
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
                        btnEdit.dataset.dbxTooltip = dbx.translate({
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
                            div.dataset.dbxTooltip = text;
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
    });
})(window);
