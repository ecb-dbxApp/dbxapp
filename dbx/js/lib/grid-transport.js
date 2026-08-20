/*!
 * dbxapp grid-transport.js - remote requests and server sorting
 */
(function (window) {
    "use strict";

    const feature = window.dbx && window.dbx.feature && window.dbx.feature._features.grid;
    if (!feature) {
        console.error('[dbx][grid] feature missing before runtime extension');
        return;
    }

    Object.assign(feature, {
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


    });
})(window);
