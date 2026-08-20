(function () {


    // --------------------------------------------------

    if (!window.dbx) {
        console.error('[ace] dbx not found');
        return;
    }

    const dbx = window.dbx;

    function log(...args)   { dbx.log('[ace]', ...args); }
    function warn(...args)  { dbx.warn('[ace]', ...args); }
    function error(...args) { dbx.error('[ace]', ...args); }

    log('lib loaded');

    dbx.feature.register('ace', {

        scope: "element", // 🔥 FIX (einzige Änderung)

        prio: 'last',

        css: [
            ['css', 'design', 'c-ace.css']
        ],

        js: [
            ['js', 'lib', 'ajax.js']
        ],

        init: init
    });

    function init(el, cfg) {

        log('init START', el, cfg);

        if (el.__aceInitialized || el.__aceInitializing) {
            log('already initialized');
            return;
        }

        el.__aceInitializing = true;

        function resetInitState() {
            el.__aceInitializing = false;
            el.__aceInitialized = false;
        }

        loadAce(function (ok) {

            log('loadAce callback');

            if (!ok || !window.ace || typeof window.ace.edit !== 'function') {
                resetInitState();
                error('Ace ist nicht geladen.');
                return;
            }

            if (!el || !el.isConnected) {
                resetInitState();
                warn('init skipped: element is no longer connected');
                return;
            }

            const file = cfg.file || '';

            const container = el.closest('.c-ace');
            log('container (.c-ace):', container);

            const textarea = container
                ? container.querySelector('textarea')
                : null;

            if (!textarea) {
                resetInitState();
                error('textarea not found in container');
                return;
            }

            textarea.style.display = 'none';

            if (cfg.height) el.style.height = cfg.height;
            if (cfg.width)  el.style.width  = cfg.width;

            log('element size BEFORE init', {
                offsetHeight: el.offsetHeight,
                offsetWidth: el.offsetWidth,
                clientHeight: el.clientHeight,
                styleHeight: el.style.height
            });

            try {

                const editor = ace.edit(el);
                el.__aceEditor = editor;
                el.__aceInitialized = true;
                el.__aceInitializing = false;

                log('editor created', editor);

                // --------------------------------------------------
                // THEME
                // --------------------------------------------------

                function resolveTheme(name) {
                    if (!name) return 'monokai';
                    const t = name.toLowerCase();
                    if (t === 'dark') return 'monokai';
                    if (t === 'light') return 'github';
                    return t;
                }

                try {
                    editor.setTheme("ace/theme/" + resolveTheme(cfg.theme));
                } catch {
                    editor.setTheme("ace/theme/monokai");
                }

                editor.session.setMode(getMode(file));

                const dirtyEl = container ? container.querySelector('.editor-dirty') : null;

                function setDirty(state) {

                    log('setDirty:', state, dirtyEl);

                    if (dirtyEl) {
                        dirtyEl.dataset.state = state ? 'dirty' : '';
                    } else {
                        warn('dirtyEl not found');
                    }
                }

                // --------------------------------------------------
                // CONTENT INIT
                // --------------------------------------------------

                editor.setValue(textarea.value || '', -1);
                setDirty(false);

                log('AFTER setValue size', {
                    offsetHeight: el.offsetHeight,
                    clientHeight: el.clientHeight
                });

                // --------------------------------------------------
                // 🔥 FIX: LIVE RESIZE (OHNE DELAY)
                // --------------------------------------------------

                function doResize() {
                    editor.resize();
                }

                // initial
                doResize();

                // window resize
                window.addEventListener('resize', doResize);

                // 🔥 wichtig: window resize (drag/resize von openWin)
                const win = el.closest('.dbx-window');

                if (win && window.ResizeObserver) {

                    let raf;

                    const ro = new ResizeObserver(() => {
                        cancelAnimationFrame(raf);
                        raf = requestAnimationFrame(() => editor.resize());
                    });

                    ro.observe(win);
                }

                // fallback (falls ResizeObserver fehlt)
                else {
                    setTimeout(() => {
                        editor.resize();
                    }, 0);
                }

                // --------------------------------------------------
                // REGISTRY
                // --------------------------------------------------

                window.__dbxEditors = window.__dbxEditors || [];

                const entry = {
                    editor,
                    container,
                    textarea,
                    cfg,
                    save: null
                };

                window.__dbxEditors.push(entry);

                editor.on('focus', () => window.__dbxActiveEditor = entry);
                el.addEventListener('mousedown', () => window.__dbxActiveEditor = entry);

                // --------------------------------------------------
                // CHANGE
                // --------------------------------------------------

                editor.session.on('change', function () {

                    const val = editor.getValue();
                    textarea.value = val;

                    setDirty(true);

                });

                // SAVE BUTTON
                addSaveButton(editor, file, entry, setDirty);

            } catch (e) {
                resetInitState();
                error('editor init failed', e);
            }

        });
    }

    // --------------------------------------------------
    // ACE LOADER
    // --------------------------------------------------

    function loadAce(callback) {

        const libPath = dbx.config.libPath || '';
        const root = libPath.replace(/js\/lib\/?$/, '');
        const acePath = root + 'add_ons/ace/';

        function configureAcePaths() {
            if (window.ace && ace.config) {
                ace.config.set("basePath", acePath);
                ace.config.set("modePath", acePath);
                ace.config.set("themePath", acePath);
                ace.config.set("workerPath", acePath);
            }
        }

        if (window.ace && typeof window.ace.edit === 'function') {
            configureAcePaths();
            return callback(true);
        }

        if (window.__dbxAceQueue) {
            window.__dbxAceQueue.push(callback);
            return;
        }

        window.__dbxAceQueue = [callback];

        const script = document.createElement('script');

        script.src = acePath + 'ace.js';

        script.onload = function () {

            configureAcePaths();

            const q = window.__dbxAceQueue;
            window.__dbxAceQueue = null;
            const ok = !!(window.ace && typeof window.ace.edit === 'function');
            q.forEach(fn => fn(ok));
        };

        script.onerror = function () {
            error('FAILED:', script.src);
            const q = window.__dbxAceQueue || [];
            window.__dbxAceQueue = null;
            q.forEach(fn => fn(false));
        };

        document.head.appendChild(script);
    }

    // --------------------------------------------------
    // MODE
    // --------------------------------------------------

    function getMode(file) {

        if (!file) return "ace/mode/text";

        const ext = file.split('.').pop().toLowerCase();

        if (ext === 'css') return "ace/mode/css";
        if (ext === 'htm' || ext === 'html') return "ace/mode/html";
        if (ext === 'js') return "ace/mode/javascript";
        if (ext === 'php') return "ace/mode/php";

        return "ace/mode/text";
    }

    // --------------------------------------------------
    // SAVE BUTTON (unverändert)
    // --------------------------------------------------

    function addSaveButton(editor, file, entry, setDirty) {

        const container = editor.container.closest('.c-ace');
        log('addSaveButton container:', container);

        if (!container) {
            warn('no editor container found');
            return;
        }

        const btnSave   = container.querySelector('.editor-save');
        const btnDelete = container.querySelector('.editor-delete');
        const btnRename = container.querySelector('.editor-rename');
        const btnCopy   = container.querySelector('.editor-copy');
        const input     = container.querySelector('.editor-filename');
        const security  = container.querySelector('.dbx-editor-security');

        if (!btnSave) {
            warn('no save button found');
            return;
        }

        function showMsg(txt, type='ok') {

            const el = document.createElement('div');
            el.className = 'dbx-msg';
            el.textContent = txt;

            el.style.position = 'fixed';
            el.style.top = '20px';
            el.style.right = '20px';
            el.style.zIndex = 999999;
            el.style.padding = '6px 10px';
            el.style.borderRadius = '4px';
            el.style.background = (type === 'error') ? '#dc3545' : '#198754';
            el.style.color = '#fff';
            el.style.fontSize = '12px';
            el.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
            el.style.opacity = '0';

            document.body.appendChild(el);

            requestAnimationFrame(() => el.style.opacity = '1');

            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 300);
            }, 1200);
        }

        if (input) input.value = file;

        const confirmDeleteText = (entry?.cfg?.confirm_delete ?? 'Datei wirklich löschen?');
        const confirmRenameText = (entry?.cfg?.confirm_rename ?? 'Datei wirklich umbenennen?');
        const confirmCopyText   = (entry?.cfg?.confirm_copy   ?? 'Datei wirklich kopieren?');

        function doConfirm(text, msg) {
            if (text === '-') return true;
            return confirm(text + '\n' + msg);
        }

        function setIcon(state) {

            const icon = btnSave.querySelector('i');
            if (!icon) return;

            icon.className = 'bi';

            if (state === 'saving') icon.classList.add('bi-arrow-repeat');
            else if (state === 'saved') icon.classList.add('bi-check');
            else if (state === 'error') icon.classList.add('bi-x');
            else icon.classList.add('bi-floppy');
        }

        setIcon();

        function requestJson(url, options = {}) {
            if (!dbx.ajax || typeof dbx.ajax.request !== 'function') {
                return Promise.reject(new Error('ajax.js nicht geladen.'));
            }
            return dbx.ajax.request(Object.assign({
                url: url,
                method: 'GET',
                mode: 'json',
                timeout: 30000
            }, options));
        }

        /**
         * Sendet eine Dateimutation ausschließlich per POST und ergänzt den
         * aktuellen dbxForm-Token. Jede Antwort rotiert den Token, damit auch
         * mehrere Speichern-/Kopieren-Aktionen in einem Editorfenster möglich
         * bleiben, ohne einen bereits verbrauchten Token wiederzuverwenden.
         */
        function requestMutation(data) {
            if (!security || !security.name || !security.value) {
                return Promise.reject(new Error('dbxForm-Sicherheitstoken fehlt.'));
            }

            const action = String(data?.action || '');
            const body = new URLSearchParams();
            Object.entries(data || {}).forEach(([name, value]) => {
                if (name === 'action') return;
                body.set(name, String(value ?? ''));
            });
            body.set(security.name, security.value);

            return requestJson('?dbx_modul=dbxEditor&dbx_run1=' + encodeURIComponent(action), {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString()
            }).then(res => {
                if (res?.security?.name && res?.security?.value) {
                    security.name = res.security.name;
                    security.value = res.security.value;
                }
                return res;
            });
        }

        function doSave() {

            if (btnSave.dataset.busy === '1') return;
            btnSave.dataset.busy = '1';

            const content = editor.getValue();
            const currentFile = input ? input.value : file;

            btnSave.dataset.state = 'saving';
            setIcon('saving');

            requestMutation({
                action: 'save',
                file: currentFile,
                content: content
            })
            .then(res => {

                if (res.ok) {

                    btnSave.dataset.state = 'saved';
                    setIcon('saved');
                    setDirty(false);

                    showMsg('Datei gespeichert');

                    setTimeout(() => {
                        btnSave.dataset.state = '';
                        setIcon();
                    }, 1200);

                } else {
                    btnSave.dataset.state = 'error';
                    setIcon('error');
                    showMsg('Speichern fehlgeschlagen', 'error');
                }

                btnSave.dataset.busy = '0';
            })
            .catch(() => {
                btnSave.dataset.state = 'error';
                setIcon('error');
                showMsg('Speichern fehlgeschlagen', 'error');
                btnSave.dataset.busy = '0';
            });
        }

        btnSave.onclick = doSave;
        entry.save = doSave;

        if (btnDelete) {

            btnDelete.onclick = function () {

                const currentFile = input ? input.value : file;

                if (!doConfirm(confirmDeleteText, currentFile)) return;

                requestMutation({
                    action: 'delete',
                    file: currentFile
                })
                .then(res => {

                    if (res.ok) {

                        showMsg('Datei gelöscht');

                        const win = container.closest('.dbx-window');
                        if (win) win.remove();

                    } else {
                        showMsg('Löschen fehlgeschlagen', 'error');
                    }
                });
            };
        }

        let oldValue = file;

        if (btnRename && input) {

            btnRename.onclick = function () {

                const newFile = input.value.trim();

                if (!newFile || newFile === oldValue) {
                    showMsg('Dateiname unverändert');
                    return;
                }

                if (!doConfirm(confirmRenameText, oldValue + ' → ' + newFile)) return;

                requestMutation({
                    action: 'rename',
                    old: oldValue,
                    new: newFile
                })
                .then(res => {

                    if (res.ok) {

                        oldValue = newFile;
                        showMsg('Datei umbenannt');

                    } else {
                        showMsg('Umbenennen fehlgeschlagen', 'error');
                        input.value = oldValue;
                    }
                });
            };
        }

        if (btnCopy && input) {

            btnCopy.onclick = function () {

                const newFile = input.value.trim();

                if (!newFile || newFile === oldValue) {
                    showMsg('Dateiname unverändert');
                    return;
                }

                if (!doConfirm(confirmCopyText, oldValue + ' → ' + newFile)) return;

                requestMutation({
                    action: 'copy',
                    old: oldValue,
                    new: newFile
                })
                .then(res => {

                    if (res.ok) {

                        showMsg('Datei kopiert');

                    } else {
                        showMsg('Kopieren fehlgeschlagen', 'error');
                    }
                });
            };
        }

        log('save/delete/rename/copy wired');
    }

    if (!window.__dbxSaveHandlerInstalled) {

        window.__dbxSaveHandlerInstalled = true;

        document.addEventListener('keydown', function (e) {

            const isSave = (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's';
            if (!isSave) return;

            e.preventDefault();

            if (window.__dbxActiveEditor?.save) {
                window.__dbxActiveEditor.save();
                return;
            }

            if (window.__dbxEditors?.length) {
                window.__dbxEditors[0].save();
            }
        });
    }

})();
