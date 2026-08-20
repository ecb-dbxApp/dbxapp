/*!
 * dbxapp gallery.js
 * Lightweight image gallery and lightbox for rendered CMS content.
 */
(function (window, document) {
    "use strict";

    if (!window.dbx || !window.dbx.feature) {
        console.error("[dbx][gallery] dbx core missing");
        return;
    }

    const dbx = window.dbx;
    const LIB = "gallery";
    let lightbox = null;

    const VENDOR_PACKS = {
        swiper: [
            ["css", "root", "vendor/npm/node_modules/swiper/swiper-bundle.min.css"],
            ["js", "root", "vendor/npm/node_modules/swiper/swiper-bundle.min.js"]
        ],
        viewerjs: [
            ["css", "root", "vendor/npm/node_modules/viewerjs/dist/viewer.min.css"],
            ["js", "root", "vendor/npm/node_modules/viewerjs/dist/viewer.min.js"]
        ],
        blueimp: [
            ["css", "root", "vendor/npm/node_modules/blueimp-gallery/css/blueimp-gallery.min.css"],
            ["js", "root", "vendor/npm/node_modules/blueimp-gallery/js/blueimp-gallery.min.js"],
            ["js", "root", "vendor/npm/node_modules/blueimp-gallery/js/blueimp-gallery-video.js"]
        ],
        photoswipe: [
            ["css", "root", "vendor/npm/node_modules/photoswipe/dist/photoswipe.css"],
            ["js", "root", "vendor/npm/node_modules/photoswipe/dist/umd/photoswipe.umd.min.js"]
        ],
        openseadragon: [
            ["js", "root", "vendor/npm/node_modules/openseadragon/build/openseadragon/openseadragon.min.js"]
        ]
    };

    const vendorWaiters = {};

    function vendorKeyForMode(mode) {
        const value = String(mode || "").toLowerCase();
        if (value.indexOf("swiper-") === 0) return "swiper";
        if (value === "viewerjs") return "viewerjs";
        if (value === "blueimp") return "blueimp";
        if (value === "photoswipe") return "photoswipe";
        if (value === "deepzoom") return "openseadragon";
        return "";
    }

    function ensureVendor(mode, done) {
        const key = vendorKeyForMode(mode);
        if (!key || !VENDOR_PACKS[key]) {
            if (done) done();
            return;
        }

        if (vendorWaiters[key]) {
            if (done) vendorWaiters[key].push(done);
            return;
        }

        const queue = done ? [done] : [];
        vendorWaiters[key] = queue;

        dbx.load(VENDOR_PACKS[key], function () {
            const callbacks = vendorWaiters[key] || [];
            delete vendorWaiters[key];
            callbacks.forEach(fn => fn && fn());
        });
    }

    function qsa(root, sel) {
        return root ? Array.from(root.querySelectorAll(sel)) : [];
    }

    function closestElement(target, selector) {
        if (!target) return null;
        const el = target.nodeType === 1 ? target : target.parentElement;
        return el && el.closest ? el.closest(selector) : null;
    }

    function ensureLightbox() {
        if (lightbox && lightbox.isConnected) return lightbox;
        let box = document.querySelector("[data-dbx-gallery-lightbox]");
        if (box) {
            lightbox = box;
            normalizeLightboxMarkup(box);
            cacheLightboxRefs(box);
            return box;
        }

        box = document.createElement("div");
        box.className = "dbx-gallery-lightbox";
        box.setAttribute("data-dbx-gallery-lightbox", "1");
        box.hidden = true;
        box.innerHTML = `
            <figure class="dbx-gallery-lightbox-figure">
                <div class="dbx-gallery-lightbox-stage">
                    <button type="button" class="dbx-gallery-lightbox-nav dbx-gallery-lightbox-prev" data-dbx-gallery-prev data-dbx-tooltip="Vorheriges Bild"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="dbx-gallery-lightbox-close" data-dbx-gallery-close data-dbx-tooltip="Schliessen"><i class="bi bi-x-lg"></i></button>
                    <img src="" alt="" data-dbx-gallery-image>
                    <video controls playsinline preload="metadata" data-dbx-gallery-video hidden></video>
                    <iframe src="" title="" loading="lazy" allowfullscreen data-dbx-gallery-frame hidden></iframe>
                    <button type="button" class="dbx-gallery-lightbox-nav dbx-gallery-lightbox-next" data-dbx-gallery-next data-dbx-tooltip="Naechstes Bild"><i class="bi bi-chevron-right"></i></button>
                </div>
                <figcaption data-dbx-gallery-caption></figcaption>
            </figure>`;
        document.body.appendChild(box);
        normalizeLightboxMarkup(box);
        cacheLightboxRefs(box);
        bindLightboxGestures(box);
        lightbox = box;
        return box;
    }

    function normalizeLightboxMarkup(box) {
        if (!box) return;
        const figure = box.querySelector(".dbx-gallery-lightbox-figure");
        if (!figure) return;
        let stage = figure.querySelector(".dbx-gallery-lightbox-stage");
        if (!stage) {
            stage = document.createElement("div");
            stage.className = "dbx-gallery-lightbox-stage";
            const caption = figure.querySelector("[data-dbx-gallery-caption]");
            figure.insertBefore(stage, caption || figure.firstChild);
        }
        qsa(box, "[data-dbx-gallery-prev], [data-dbx-gallery-next], [data-dbx-gallery-close], [data-dbx-gallery-image], [data-dbx-gallery-video], [data-dbx-gallery-frame]").forEach(el => {
            if (el.parentElement !== stage) stage.appendChild(el);
        });
    }

    function cacheLightboxRefs(box) {
        if (!box || box.__dbxGalleryRefs) return;
        box.__dbxGalleryRefs = {
            stage: box.querySelector(".dbx-gallery-lightbox-stage"),
            image: box.querySelector("[data-dbx-gallery-image]"),
            video: box.querySelector("[data-dbx-gallery-video]"),
            frame: box.querySelector("[data-dbx-gallery-frame]"),
            caption: box.querySelector("[data-dbx-gallery-caption]")
        };
    }

    function resetZoom(box) {
        if (!box) return;
        box.__dbxGalleryZoom = 1;
        box.__dbxGalleryPanX = 0;
        box.__dbxGalleryPanY = 0;
        applyZoom(box);
    }

    function activeMedia(box) {
        const refs = box && box.__dbxGalleryRefs || {};
        return [refs.image, refs.video].find(el => el && !el.hidden) || null;
    }

    function applyZoom(box) {
        const media = activeMedia(box);
        if (!media) return;
        const zoom = Number(box.__dbxGalleryZoom || 1);
        const panX = Number(box.__dbxGalleryPanX || 0);
        const panY = Number(box.__dbxGalleryPanY || 0);
        media.style.transform = zoom > 1 ? `translate3d(${panX}px, ${panY}px, 0) scale(${zoom})` : "";
        media.style.cursor = zoom > 1 ? "grab" : "";
    }

    function setZoom(box, zoom, origin) {
        if (!box) return;
        const previous = Number(box.__dbxGalleryZoom || 1);
        const next = Math.max(1, Math.min(6, zoom));
        box.__dbxGalleryZoom = next;
        if (next === 1) {
            box.__dbxGalleryPanX = 0;
            box.__dbxGalleryPanY = 0;
        } else if (origin && previous > 0) {
            box.__dbxGalleryPanX = Number(box.__dbxGalleryPanX || 0) * (next / previous);
            box.__dbxGalleryPanY = Number(box.__dbxGalleryPanY || 0) * (next / previous);
        }
        applyZoom(box);
    }

    function overlayMode(settings) {
        return cleanToken(settings && settings.click || "lightbox", "lightbox");
    }

    function bindLightboxGestures(box) {
        if (!box || box.__dbxGalleryGesturesBound) return;
        box.__dbxGalleryGesturesBound = true;
        let startX = 0;
        let startY = 0;
        let lastX = 0;
        let lastY = 0;
        let tracking = false;
        let panning = false;
        let pinchStartDistance = 0;
        let pinchStartZoom = 1;
        const pointers = new Map();

        box.addEventListener("pointerdown", e => {
            if (closestElement(e.target, "button")) return;
            pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (pointers.size === 2) {
                const pts = Array.from(pointers.values());
                pinchStartDistance = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
                pinchStartZoom = Number(box.__dbxGalleryZoom || 1);
                tracking = false;
                return;
            }
            tracking = true;
            panning = Number(box.__dbxGalleryZoom || 1) > 1;
            startX = e.clientX;
            startY = e.clientY;
            lastX = e.clientX;
            lastY = e.clientY;
        }, { passive: true });

        box.addEventListener("pointermove", e => {
            if (pointers.has(e.pointerId)) pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (pointers.size === 2 && pinchStartDistance > 0) {
                const pts = Array.from(pointers.values());
                const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
                setZoom(box, pinchStartZoom * (dist / pinchStartDistance), true);
                return;
            }
            if (!panning) return;
            box.__dbxGalleryPanX = Number(box.__dbxGalleryPanX || 0) + (e.clientX - lastX);
            box.__dbxGalleryPanY = Number(box.__dbxGalleryPanY || 0) + (e.clientY - lastY);
            lastX = e.clientX;
            lastY = e.clientY;
            applyZoom(box);
        }, { passive: true });

        box.addEventListener("pointerup", e => {
            pointers.delete(e.pointerId);
            if (panning) {
                panning = false;
                tracking = false;
                return;
            }
            if (!tracking) return;
            tracking = false;
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            if (Math.abs(dx) < 48 || Math.abs(dx) < Math.abs(dy) * 1.35) return;
            move(dx < 0 ? 1 : -1);
        }, { passive: true });

        box.addEventListener("pointercancel", e => {
            pointers.delete(e.pointerId);
            tracking = false;
            panning = false;
        }, { passive: true });

        box.addEventListener("wheel", e => {
            const media = activeMedia(box);
            if (!media || (media.tagName || "").toLowerCase() === "video") return;
            e.preventDefault();
            const current = Number(box.__dbxGalleryZoom || 1);
            const factor = e.deltaY < 0 ? 1.16 : 0.86;
            setZoom(box, current * factor, true);
        }, { passive: false });

        box.addEventListener("dblclick", e => {
            if (closestElement(e.target, "button")) return;
            e.preventDefault();
            setZoom(box, Number(box.__dbxGalleryZoom || 1) > 1 ? 1 : 2.4, true);
        });
    }

    function itemData(item) {
        if (item.__dbxGalleryData) return item.__dbxGalleryData;
        const img = item.querySelector("img");
        const video = item.querySelector("video");
        const frame = item.querySelector("iframe");
        const type = item.getAttribute("data-media-type") || (frame ? "external_video" : (video ? "video" : "image"));
        item.__dbxGalleryData = {
            href: item.getAttribute("href") || (frame ? frame.getAttribute("src") : "") || (video ? video.getAttribute("src") : "") || (img ? (img.getAttribute("data-full-src") || img.getAttribute("src")) : ""),
            poster: item.getAttribute("data-poster") || (img ? img.getAttribute("src") : "") || (video ? video.getAttribute("poster") : ""),
            type,
            title: item.getAttribute("data-title") || (img ? img.getAttribute("alt") : ""),
            caption: item.getAttribute("data-caption") || "",
            width: Number(item.getAttribute("data-width") || (img ? (img.naturalWidth || img.getAttribute("width")) : 0) || 0),
            height: Number(item.getAttribute("data-height") || (img ? (img.naturalHeight || img.getAttribute("height")) : 0) || 0)
        };
        return item.__dbxGalleryData;
    }

    function collectItems(root) {
        return qsa(root, "[data-dbx-gallery-item]").filter(item => item.getAttribute("href") || item.querySelector("img,video,iframe"));
    }

    function itemsFor(root) {
        if (!root) return [];
        if (!root.__dbxGalleryItems) root.__dbxGalleryItems = collectItems(root);
        return root.__dbxGalleryItems;
    }

    function preloadNext(root, index) {
        const items = itemsFor(root);
        if (items.length < 2 || !window.Image) return;
        const next = items[(index + 1) % items.length];
        const data = itemData(next);
        if (!data.href || data.type === "video") return;
        const img = new Image();
        img.decoding = "async";
        img.src = data.href;
    }

    function cleanToken(value, fallback) {
        const token = String(value || fallback || "")
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, "-")
            .replace(/^-+|-+$/g, "");
        return token || fallback || "default";
    }

    function cssLength(value, fallback) {
        const raw = String(value || fallback || "").trim();
        if (!raw || raw.toLowerCase() === "parent") return fallback;
        if (/^[0-9]+(?:\.[0-9]+)?$/.test(raw)) return raw === "0" ? "0" : raw + "px";
        if (/^[a-z0-9\s#.,%()+\-\/_]+$/i.test(raw) && !/[;"<>]/.test(raw)) return raw;
        return fallback;
    }

    function optionToken(value, fallback, allowed) {
        const token = cleanToken(value || fallback, fallback);
        return allowed.includes(token) ? token : fallback;
    }

    function imageSizeToken(value) {
        const token = cleanToken(value || "800x600", "800x600");
        if (/^[0-9]+x[0-9]+$/.test(token) || token === "square" || token === "original") {
            return token;
        }
        return "800x600";
    }


    function galleryConfig(cfg) {
        cfg = cfg || {};
        const visible = Math.max(1, Math.min(12, parseInt(cfg["img-count"] || cfg.img_count || 3, 10) || 3));
        const overflow = optionToken(cfg.overflow, "grid", ["grid", "scroll", "slider", "laufband", "tutorial"]);
        const click = optionToken(cfg.click, "lightbox", ["lightbox", "link", "newtab", "none", "swiper-coverflow", "swiper-cube", "swiper-cards", "swiper-3d", "viewerjs", "blueimp", "photoswipe", "deepzoom"]);
        const imgSize = imageSizeToken(cfg["img-size"] || cfg.img_size);
        const lightboxWidth = cssLength(cfg["lightbox-width"] || cfg.lightbox_width || "100vw", "100vw");
        return { visible, overflow, click, imgSize, lightboxWidth };
    }

    function applyConfig(root, cfg) {
        const settings = galleryConfig(cfg);
        root.classList.add("dbx-gallery");
        root.classList.add("dbx-gallery-overflow-" + settings.overflow);
        root.classList.add("dbx-gallery-click-" + settings.click);
        root.classList.add("dbx-gallery-size-" + settings.imgSize);
        root.style.setProperty("--dbx-gallery-visible-count", String(settings.visible));
        root.style.setProperty("--dbx-gallery-item-width", `calc((100% - ${(settings.visible - 1) * 16}px) / ${settings.visible})`);
        root.style.setProperty("--dbx-gallery-lightbox-width", settings.lightboxWidth);

        const size = /^([0-9]+)x([0-9]+)$/i.exec(settings.imgSize);
        if (size) {
            root.style.setProperty("--dbx-gallery-aspect-ratio", `${size[1]} / ${size[2]}`);
        }
        return settings;
    }

    function clearOverflowBehavior(root) {
        if (!root) return;
        if (root.__dbxGalleryFrame) {
            window.cancelAnimationFrame(root.__dbxGalleryFrame);
            root.__dbxGalleryFrame = null;
        }
        if (root.__dbxGalleryTimer) {
            window.clearInterval(root.__dbxGalleryTimer);
            root.__dbxGalleryTimer = null;
        }
        qsa(root, "[data-dbx-gallery-clone]").forEach(clone => clone.remove());
        if (root.__dbxGalleryTutorial) {
            if (root.__dbxGalleryTutorial.onKeydown) {
                root.removeEventListener("keydown", root.__dbxGalleryTutorial.onKeydown);
            }
            if (root.__dbxGalleryTutorial.ui) root.__dbxGalleryTutorial.ui.remove();
            root.__dbxGalleryTutorial = null;
        }
        qsa(root, ".dbx-media-gallery-item").forEach(item => {
            item.hidden = false;
            item.classList.remove("is-active");
            item.removeAttribute("aria-hidden");
        });
        root.classList.remove("dbx-gallery-tutorial-ready");
        root.scrollLeft = 0;
    }

    function bindOverflowPause(root) {
        if (!root || root.__dbxGalleryPauseBound) return;
        root.__dbxGalleryPauseBound = true;
        root.__dbxGalleryPaused = false;
        root.addEventListener("pointerenter", () => { root.__dbxGalleryPaused = true; });
        root.addEventListener("pointerleave", () => { root.__dbxGalleryPaused = false; });
        root.addEventListener("focusin", () => { root.__dbxGalleryPaused = true; });
        root.addEventListener("focusout", () => { root.__dbxGalleryPaused = false; });
    }

    function itemLeft(root, item) {
        const rootRect = root.getBoundingClientRect();
        const itemRect = item.getBoundingClientRect();
        return root.scrollLeft + itemRect.left - rootRect.left;
    }

    function startSlider(root) {
        const items = itemsFor(root);
        if (!items.length || root.scrollWidth <= root.clientWidth) return;
        bindOverflowPause(root);
        let index = 0;
        root.__dbxGalleryTimer = window.setInterval(() => {
            if (root.__dbxGalleryPaused) return;
            const currentItems = itemsFor(root);
            if (!currentItems.length) return;
            index = (index + 1) % currentItems.length;
            root.scrollTo({ left: itemLeft(root, currentItems[index]), behavior: "smooth" });
        }, 4200);
    }

    function cloneForMarquee(item) {
        const clone = item.cloneNode(true);
        clone.setAttribute("data-dbx-gallery-clone", "1");
        clone.setAttribute("aria-hidden", "true");
        qsa(clone, "[data-dbx-gallery-item]").forEach(el => {
            el.removeAttribute("data-dbx-gallery-item");
            el.removeAttribute("href");
            el.setAttribute("tabindex", "-1");
        });
        return clone;
    }

    function startMarquee(root) {
        const items = itemsFor(root);
        if (items.length < 2) return;
        items.forEach(item => root.appendChild(cloneForMarquee(item.closest(".dbx-media-gallery-item") || item)));
        if (root.scrollWidth <= root.clientWidth) {
            qsa(root, "[data-dbx-gallery-clone]").forEach(clone => clone.remove());
            return;
        }
        bindOverflowPause(root);
        const step = function () {
            if (!root.isConnected) return;
            if (!root.__dbxGalleryPaused) {
                const resetAt = Math.max(1, (root.scrollWidth - root.clientWidth) / 2);
                root.scrollLeft += 0.45;
                if (root.scrollLeft >= resetAt) root.scrollLeft = 0;
            }
            root.__dbxGalleryFrame = window.requestAnimationFrame(step);
        };
        root.__dbxGalleryFrame = window.requestAnimationFrame(step);
    }

    function tutorialFigures(root) {
        return itemsFor(root)
            .map(item => item.closest(".dbx-media-gallery-item"))
            .filter((item, index, list) => item && list.indexOf(item) === index);
    }

    function prepareTutorialCaption(figure) {
        const link = figure.querySelector("[data-dbx-gallery-item]");
        const data = itemData(link || figure);
        const title = String(data.title || "").trim();
        let text = String((data.caption || "")).trim();
        let caption = figure.querySelector("figcaption");
        if (!caption) {
            caption = document.createElement("figcaption");
            figure.appendChild(caption);
        }
        if (!text) text = String(caption.textContent || "").trim();
        if (!title && !text) return;
        caption.textContent = "";
        if (title && title !== text) {
            const headline = document.createElement("strong");
            headline.className = "dbx-gallery-tutorial-title";
            headline.textContent = title;
            caption.appendChild(headline);
        }
        if (text) {
            const body = document.createElement("span");
            body.className = "dbx-gallery-tutorial-text";
            body.textContent = text;
            caption.appendChild(body);
        } else if (title) {
            caption.textContent = title;
        }
    }

    function setTutorialSlide(root, index) {
        const state = root.__dbxGalleryTutorial;
        if (!state || !state.figures.length) return;
        const count = state.figures.length;
        state.index = (index + count) % count;
        state.figures.forEach((figure, itemIndex) => {
            const active = itemIndex === state.index;
            figure.hidden = !active;
            figure.classList.toggle("is-active", active);
            figure.setAttribute("aria-hidden", active ? "false" : "true");
        });
        if (state.copy) {
            const caption = state.figures[state.index].querySelector("figcaption");
            state.copy.innerHTML = caption ? caption.innerHTML : "";
            state.copy.hidden = !state.copy.innerHTML.trim();
        }
        if (state.status) state.status.textContent = `${state.index + 1} / ${count}`;
        state.dots.forEach((dot, dotIndex) => {
            const active = dotIndex === state.index;
            dot.classList.toggle("is-active", active);
            dot.setAttribute("aria-current", active ? "true" : "false");
        });
    }

    function startTutorialSlideshow(root) {
        const figures = tutorialFigures(root);
        if (!figures.length) return;
        figures.forEach(figure => {
            const image = figure.querySelector("img[data-full-src]");
            if (image && image.dataset.fullSrc) {
                image.src = image.dataset.fullSrc;
            }
        });
        figures.forEach(prepareTutorialCaption);

        root.classList.add("dbx-gallery-tutorial-ready");
        root.setAttribute("role", "region");
        root.setAttribute("aria-roledescription", "carousel");
        root.setAttribute("aria-label", root.getAttribute("aria-label") || "Tutorial Slideshow");
        if (!root.hasAttribute("tabindex")) root.setAttribute("tabindex", "0");

        const ui = document.createElement("div");
        ui.className = "dbx-gallery-tutorial-ui";
        ui.setAttribute("data-dbx-gallery-tutorial-ui", "1");

        const copy = document.createElement("div");
        copy.className = "dbx-gallery-tutorial-copy";

        const prev = document.createElement("button");
        prev.type = "button";
        prev.className = "dbx-gallery-tutorial-prev";
        prev.setAttribute("aria-label", "Vorherige Folie");
        prev.innerHTML = '<i class="bi bi-chevron-left"></i><span>Zurück</span>';

        const status = document.createElement("div");
        status.className = "dbx-gallery-tutorial-status";
        status.setAttribute("aria-live", "polite");

        const next = document.createElement("button");
        next.type = "button";
        next.className = "dbx-gallery-tutorial-next";
        next.setAttribute("aria-label", "Naechste Folie");
        next.innerHTML = '<span>Weiter</span><i class="bi bi-chevron-right"></i>';

        const controls = document.createElement("div");
        controls.className = "dbx-gallery-tutorial-controls";

        const dotsWrap = document.createElement("div");
        dotsWrap.className = "dbx-gallery-tutorial-dots";
        const dots = figures.map((figure, index) => {
            const data = itemData(figure.querySelector("[data-dbx-gallery-item]") || figure);
            const dot = document.createElement("button");
            dot.type = "button";
            dot.className = "dbx-gallery-tutorial-dot";
            dot.setAttribute("aria-label", data.title ? `Folie ${index + 1}: ${data.title}` : `Folie ${index + 1}`);
            dot.textContent = String(index + 1);
            dot.addEventListener("click", () => setTutorialSlide(root, index));
            dotsWrap.appendChild(dot);
            return dot;
        });

        prev.addEventListener("click", () => setTutorialSlide(root, (root.__dbxGalleryTutorial.index || 0) - 1));
        next.addEventListener("click", () => setTutorialSlide(root, (root.__dbxGalleryTutorial.index || 0) + 1));

        controls.appendChild(prev);
        controls.appendChild(status);
        controls.appendChild(next);
        ui.appendChild(copy);
        ui.appendChild(controls);
        ui.appendChild(dotsWrap);
        root.appendChild(ui);

        const onKeydown = function (e) {
            if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
            if (closestElement(e.target, "input, textarea, select")) return;
            e.preventDefault();
            setTutorialSlide(root, (root.__dbxGalleryTutorial.index || 0) + (e.key === "ArrowRight" ? 1 : -1));
        };
        root.addEventListener("keydown", onKeydown);
        root.__dbxGalleryTutorial = { figures, ui, copy, status, dots, index: 0, onKeydown };
        setTutorialSlide(root, 0);
    }

    function startOverflowBehavior(root, settings) {
        clearOverflowBehavior(root);
        if (settings.overflow === "slider") {
            startSlider(root);
        } else if (settings.overflow === "laufband") {
            startMarquee(root);
        } else if (settings.overflow === "tutorial") {
            startTutorialSlideshow(root);
        }
    }

    function cleanupOverlay(box) {
        if (!box) return;
        if (box.__dbxGalleryViewer && typeof box.__dbxGalleryViewer.destroy === "function") {
            box.__dbxGalleryViewer.destroy();
            box.__dbxGalleryViewer = null;
        }
        if (box.__dbxGallerySwiper && typeof box.__dbxGallerySwiper.destroy === "function") {
            box.__dbxGallerySwiper.destroy(true, true);
            box.__dbxGallerySwiper = null;
        }
        if (box.__dbxGalleryOpenSeadragon && typeof box.__dbxGalleryOpenSeadragon.destroy === "function") {
            box.__dbxGalleryOpenSeadragon.destroy();
            box.__dbxGalleryOpenSeadragon = null;
        }
        qsa(box, "[data-dbx-gallery-thirdparty]").forEach(el => el.remove());
    }

    function showThirdPartyOverlay(box, root, index, mode) {
        const items = itemsFor(root);
        const links = items.map(itemData);
        const current = links[index] || {};
        if (!current.href) return false;

        if (mode === "viewerjs" && window.Viewer && current.type === "image") {
            const host = document.createElement("div");
            host.hidden = true;
            host.setAttribute("data-dbx-gallery-thirdparty", "viewerjs");
            host.innerHTML = links.filter(item => item.type === "image").map(item => `<img src="${String(item.href).replace(/"/g, "&quot;")}" alt="${String(item.title || "").replace(/"/g, "&quot;")}">`).join("");
            document.body.appendChild(host);
            const imageIndex = links.slice(0, index + 1).filter(item => item.type === "image").length - 1;
            box.__dbxGalleryViewer = new window.Viewer(host, {
                initialViewIndex: Math.max(0, imageIndex),
                inline: false,
                navbar: true,
                toolbar: true,
                title: true,
                hidden() {
                    if (box.__dbxGalleryViewer) box.__dbxGalleryViewer.destroy();
                    box.__dbxGalleryViewer = null;
                    host.remove();
                }
            });
            box.__dbxGalleryViewer.show();
            return true;
        }

        if (mode === "blueimp" && window.blueimp && typeof window.blueimp.Gallery === "function") {
            window.blueimp.Gallery(links.map(item => ({
                href: item.href,
                title: item.caption || item.title || "",
                type: item.type === "video" ? (item.mime || "video/mp4") : undefined,
                poster: item.poster || undefined
            })), {
                index,
                fullscreen: false,
                stretchImages: false,
                toggleControlsOnSlideClick: true,
                closeOnEscape: true,
                closeOnSlideClick: true
            });
            return true;
        }

        if (mode === "photoswipe" && window.PhotoSwipe && current.type === "image") {
            const dataSource = links.filter(item => item.type === "image").map(item => ({
                src: item.href,
                width: item.width || 1600,
                height: item.height || 1000,
                alt: item.title || ""
            }));
            const imageIndex = links.slice(0, index + 1).filter(item => item.type === "image").length - 1;
            const pswp = new window.PhotoSwipe({
                dataSource,
                index: Math.max(0, imageIndex),
                bgOpacity: 0.92,
                showHideAnimationType: "zoom"
            });
            pswp.init();
            return true;
        }

        return false;
    }

    function openSwiperOverlay(box, root, index, mode) {
        if (!window.Swiper) return false;
        const refs = box.__dbxGalleryRefs || {};
        const stage = refs.stage;
        if (!stage) return false;
        const items = itemsFor(root).map(itemData);
        if (!items.length) return false;

        cleanupOverlay(box);
        [refs.image, refs.video, refs.frame].forEach(el => {
            if (!el) return;
            el.hidden = true;
            if (el.tagName === "VIDEO") {
                el.pause();
                el.removeAttribute("src");
                el.load();
            } else {
                el.removeAttribute("src");
            }
        });

        const swiper = document.createElement("div");
        swiper.className = "swiper dbx-gallery-swiper";
        swiper.setAttribute("data-dbx-gallery-thirdparty", "swiper");
        swiper.setAttribute("data-dbx-gallery-swiper-mode", mode);
        swiper.innerHTML = '<div class="swiper-wrapper"></div><div class="swiper-pagination"></div>';
        const wrapper = swiper.querySelector(".swiper-wrapper");
        items.forEach(item => {
            const slide = document.createElement("div");
            slide.className = "swiper-slide";
            if (item.type === "video") {
                slide.innerHTML = `<video src="${String(item.href).replace(/"/g, "&quot;")}" poster="${String(item.poster || "").replace(/"/g, "&quot;")}" controls playsinline preload="metadata"></video>`;
            } else if (item.type === "external_video") {
                slide.innerHTML = `<iframe src="${String(item.href).replace(/"/g, "&quot;")}" title="${String(item.title || "").replace(/"/g, "&quot;")}" allowfullscreen></iframe>`;
            } else {
                slide.innerHTML = `<img src="${String(item.href).replace(/"/g, "&quot;")}" alt="${String(item.title || "").replace(/"/g, "&quot;")}">`;
            }
            wrapper.appendChild(slide);
        });
        stage.appendChild(swiper);

        const effect = mode === "swiper-cube" ? "cube" : (mode === "swiper-cards" ? "cards" : "coverflow");
        const isCube = mode === "swiper-cube";
        box.__dbxGallerySwiper = new window.Swiper(swiper, {
            effect,
            initialSlide: index,
            grabCursor: true,
            loop: !isCube && items.length > 2,
            centeredSlides: !isCube,
            slidesPerView: isCube ? 1 : (mode === "swiper-3d" || mode === "swiper-coverflow" ? "auto" : 1),
            watchSlidesProgress: true,
            pagination: { el: swiper.querySelector(".swiper-pagination"), clickable: true },
            cubeEffect: { shadow: true, slideShadows: true, shadowOffset: 24, shadowScale: 0.82 },
            cardsEffect: { perSlideOffset: 10, perSlideRotate: 2 },
            coverflowEffect: { rotate: 42, stretch: 0, depth: 180, modifier: 1, slideShadows: true },
            on: {
                slideChange(sw) {
                    box.__dbxGalleryIndex = sw.realIndex;
                    const data = items[sw.realIndex] || {};
                    if (refs.caption) refs.caption.textContent = data.caption || data.title || "";
                }
            }
        });
        return true;
    }

    function openDeepZoomOverlay(box, data) {
        if (!window.OpenSeadragon || data.type !== "image") return false;
        const refs = box.__dbxGalleryRefs || {};
        const stage = refs.stage;
        if (!stage) return false;
        cleanupOverlay(box);
        [refs.image, refs.video, refs.frame].forEach(el => {
            if (!el) return;
            el.hidden = true;
            el.removeAttribute("src");
        });
        const viewer = document.createElement("div");
        viewer.className = "dbx-gallery-deepzoom";
        viewer.setAttribute("data-dbx-gallery-thirdparty", "openseadragon");
        stage.appendChild(viewer);
        box.__dbxGalleryOpenSeadragon = window.OpenSeadragon({
            element: viewer,
            prefixUrl: "dbx/vendor/npm/node_modules/openseadragon/build/openseadragon/images/",
            tileSources: { type: "image", url: data.href },
            showNavigator: true,
            gestureSettingsMouse: { clickToZoom: true, dblClickToZoom: true, scrollToZoom: true },
            gestureSettingsTouch: { pinchToZoom: true, flickEnabled: true }
        });
        return true;
    }

    function open(root, index) {
        const items = itemsFor(root);
        if (!items.length) return;
        index = Math.max(0, Math.min(items.length - 1, Number(index || 0)));
        const settings = root.__dbxGalleryCfg || {};
        const mode = overlayMode(settings);

        ensureVendor(mode, function () {
            openWithMode(root, index, mode);
        });
    }

    function openWithMode(root, index, mode) {
        const items = itemsFor(root);
        if (!items.length) return;

        const box = ensureLightbox();
        const data = itemData(items[index]);
        cleanupOverlay(box);
        if (showThirdPartyOverlay(box, root, index, mode)) return;
        box.setAttribute("data-dbx-gallery-mode", mode);
        box.style.setProperty("--dbx-gallery-lightbox-width", "calc(100vw - (var(--dbx-gallery-lightbox-margin) * 2))");
        resetZoom(box);
        const refs = box.__dbxGalleryRefs || {};
        const image = refs.image;
        const video = refs.video;
        const frame = refs.frame;
        const caption = refs.caption;
        if (video) {
            video.pause();
            video.removeAttribute("src");
            video.removeAttribute("poster");
            video.load();
            video.hidden = true;
        }
        if (frame) {
            frame.hidden = true;
            frame.removeAttribute("src");
            frame.title = "";
        }
        if (image) {
            image.style.transform = "";
            image.style.cursor = "";
            image.alt = data.title || "";
            if (data.type === "video" || data.type === "external_video") {
                image.hidden = true;
                image.removeAttribute("src");
            } else {
                image.hidden = false;
                if (image.getAttribute("src") !== data.href) image.src = data.href;
                if (image.decode) image.decode().catch(() => {});
            }
        }
        if (data.type === "video" && video) {
            video.style.transform = "";
            video.style.cursor = "";
            video.src = data.href;
            if (data.poster) video.poster = data.poster;
            video.hidden = false;
        }
        if (data.type === "external_video" && frame) {
            frame.src = data.href;
            frame.title = data.title || "";
            frame.hidden = false;
        }
        if (caption) caption.textContent = data.caption || data.title || "";
        box.__dbxGalleryRoot = root;
        box.__dbxGalleryIndex = index;
        box.hidden = false;
        document.documentElement.classList.add("dbx-gallery-open");
        if (mode.indexOf("swiper-") === 0) openSwiperOverlay(box, root, index, mode);
        if (mode === "deepzoom") openDeepZoomOverlay(box, data);
        preloadNext(root, index);
    }

    function move(delta) {
        const box = ensureLightbox();
        const root = box.__dbxGalleryRoot;
        if (box.__dbxGallerySwiper) {
            delta > 0 ? box.__dbxGallerySwiper.slideNext() : box.__dbxGallerySwiper.slidePrev();
            return;
        }
        const items = itemsFor(root);
        if (!root || !items.length) return;
        const next = (Number(box.__dbxGalleryIndex || 0) + delta + items.length) % items.length;
        open(root, next);
    }

    function close() {
        const box = ensureLightbox();
        const refs = box.__dbxGalleryRefs || {};
        const video = refs.video;
        const image = refs.image;
        const frame = refs.frame;
        cleanupOverlay(box);
        if (video) {
            video.pause();
            video.removeAttribute("src");
            video.load();
        }
        if (image) image.removeAttribute("src");
        if (frame) frame.removeAttribute("src");
        box.hidden = true;
        box.removeAttribute("data-dbx-gallery-mode");
        document.documentElement.classList.remove("dbx-gallery-open");
    }

    function init(root, cfg) {
        if (!root || root.__dbxGalleryReady) return;
        root.__dbxGalleryReady = true;
        root.__dbxGalleryCfg = applyConfig(root, cfg);
        root.__dbxGalleryItems = collectItems(root);
        if (root.__dbxGalleryCfg.click === "newtab") {
            root.__dbxGalleryItems.forEach(item => {
                item.setAttribute("target", "_blank");
                item.setAttribute("rel", "noopener");
            });
        }
        startOverflowBehavior(root, root.__dbxGalleryCfg);

        root.addEventListener("click", e => {
            const item = closestElement(e.target, "[data-dbx-gallery-item]");
            if (!item || !root.contains(item)) return;
            if ((root.__dbxGalleryCfg || {}).click === "none") {
                e.preventDefault();
                return;
            }
            if ((root.__dbxGalleryCfg || {}).click === "link" || (root.__dbxGalleryCfg || {}).click === "newtab") {
                return;
            }
            e.preventDefault();
            const items = itemsFor(root);
            open(root, items.indexOf(item));
        });
    }

    document.addEventListener("click", e => {
        if (closestElement(e.target, "[data-dbx-gallery-close]")) {
            e.preventDefault();
            close();
            return;
        }
        if (closestElement(e.target, "[data-dbx-gallery-prev]")) {
            e.preventDefault();
            move(-1);
            return;
        }
        if (closestElement(e.target, "[data-dbx-gallery-next]")) {
            e.preventDefault();
            move(1);
        }
    });

    document.addEventListener("keydown", e => {
        const box = lightbox || document.querySelector("[data-dbx-gallery-lightbox]");
        if (!box || box.hidden) return;
        if (e.key === "Escape") close();
        if (e.key === "ArrowLeft") move(-1);
        if (e.key === "ArrowRight") move(1);
    });

    dbx.gallery = {
        init,
        rescan(ctx) {
            qsa(ctx || document, "[data-dbx]").forEach(el => {
                if (el.__dbxGalleryReady) return;
                const cfgList = dbx.parseData(el.getAttribute("data-dbx"));
                const cfg = cfgList.find(item => item.lib === LIB);
                if (cfg) init(el, cfg);
            });
        }
    };

    dbx.feature.register(LIB, {
        scope: "element",
        priority: "last",
        css: [
            ["css", "design", "c-content.css"]
        ],
        js: [],
        init,
        rescan(ctx) {
            dbx.gallery.rescan(ctx);
        }
    });

})(window, document);
