/*!
 * dbxapp scheduler.js
 * Sichtbarkeitsabhängige, einmalig registrierte Hintergrundaufgaben.
 */
(function (window) {
    "use strict";
    const dbx = window.dbx;
    if (!dbx || !dbx.device) throw new Error("dbx runtime missing before scheduler.js");
    if (dbx.__schedulerLoaded === true) return;
    dbx.__schedulerLoaded = true;

    /* =====================================================
    * LOOP (SMART POLLING CORE)
    * ===================================================== */
    dbx.loop = (function(){

        const tasks = {};

        function clamp(v, min, max){
            if(min != null && v < min) return min;
            if(max != null && v > max) return max;
            return v;
        }

        function resolveInterval(task){

            const t = task.timing || {};

            let interval;

            if(task.paused){
                return null;
            }

            // hint
            if(task.hint){

                switch(task.hint){

                    case 'fast':   interval = t.min || t.base; break;
                    case 'slow':   interval = Math.max((t.base||2000)*2, t.idle||3000); break;
                    case 'idle':   interval = t.idle || t.base; break;
                    case 'boost':  interval = (t.base||2000) / 2; break;
                    default:       interval = t.base || 2000;
                }

            } else {

                if(!dbx.device.isVisible()){
                    interval = t.hidden || (t.base||2000)*3;
                }
                else if(!dbx.device.isActive()){
                    interval = t.idle || (t.base||2000)*2;
                }
                else{
                    interval = t.base || 2000;
                }
            }

            return clamp(
                interval,
                t.min || 500,
                t.max || 30000
            );
        }

        function schedule(task){

            if(task.paused) return;

            if(task.timer) {
                clearTimeout(task.timer); // 🔥 FIX
                task.timer = null;        // 🔥 FIX
            }

            const interval = resolveInterval(task);
            if(interval == null) return;

            task.timer = setTimeout(() => run(task), interval);
        }

        function run(task){

            if(task.running) return;

            task.running = true;

            let res;

            try{
                res = task.onRun({
                    id: task.id,
                    hint: task.hint
                });
            }
            catch(e){
                dbx.error('[loop] run error', task.id, e);
                finish();
                return;
            }

            Promise.resolve(res)
                .catch(err => {
                    dbx.error('[loop] async error', task.id, err);
                })
                .finally(() => finish());

            function finish(){

                task.running = false;
                task.lastRun = Date.now();

                task.timer = null; // 🔥 FIX

                if(task.hintUntil && Date.now() > task.hintUntil){
                    task.hint = null;
                    task.hintUntil = 0;
                }

                schedule(task);
            }
        }

        return {

            add(cfg){

                if(!cfg || !cfg.id || !cfg.onRun){
                    dbx.error('[loop] invalid task', cfg);
                    return;
                }

                if(tasks[cfg.id]){
                    dbx.warn('[loop] already exists', cfg.id);
                    return;
                }

                tasks[cfg.id] = {
                    id: cfg.id,
                    onRun: cfg.onRun,
                    timing: cfg.timing || {},
                    running: false,
                    paused: false,
                    hint: null,
                    hintUntil: 0,
                    timer: null,
                    lastRun: 0
                };

                schedule(tasks[cfg.id]);

                dbx.log('[loop] add', cfg.id);
            },

            hint(id, mode, opts){

                const t = tasks[id];
                if(!t) return;

                if(mode === 'pause'){
                    t.paused = true;
                    if(t.timer){
                        clearTimeout(t.timer);
                        t.timer = null;
                    }
                    return;
                }

                if(mode === 'resume'){
                    t.paused = false;
                    if(t.timer) clearTimeout(t.timer);
                    schedule(t);
                    return;
                }

                if(mode === 'none'){
                    t.hint = null;
                    t.hintUntil = 0;
                    return;
                }

                t.hint = mode;

                if(opts && opts.duration){
                    t.hintUntil = Date.now() + opts.duration;
                }

                if(mode === 'boost'){

                    // 🔥 FIX: niemals während running starten
                    if(t.running){
                        return; // einfach nächsten Zyklus beschleunigen
                    }

                    if(t.timer){
                        clearTimeout(t.timer);
                        t.timer = null;
                    }

                    run(t);
                }
            },

            debug(){
                const out = [];

                Object.values(tasks).forEach(t => {
                    out.push({
                        id: t.id,
                        running: t.running,
                        hint: t.hint,
                        lastRun: t.lastRun,
                        timer: !!t.timer
                    });
                });

                return out;
            }

        };

    })();

 


})(window);
