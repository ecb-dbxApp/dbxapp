(function ($) {

    if (!window.dbx) return;
    const dbx = window.dbx;

    function init(el, cfg) {

        // --------------------------------------------
        // Sprite laden (einmal global)
        // --------------------------------------------
        if (!window._dbxIconSpriteLoaded) {

            const spritePath = dbx.config.rootPath + 'design/' + dbx.config.design + '/icons/sprite.svg';

            dbx.log("icons → load sprite:", spritePath);

            if (!dbx.ajax || typeof dbx.ajax.request !== 'function') {
                dbx.error("icons → ajax.js not loaded");
                return;
            }

            dbx.ajax.request({
                url: spritePath,
                method: 'GET',
                mode: 'text',
                timeout: 30000
            })
                .then(svg => {

                    const div = document.createElement('div');
                    div.style.display = 'none';
                    div.innerHTML = svg;

                    document.body.prepend(div);

                    window._dbxIconSpriteLoaded = true;

                    dbx.log("icons → sprite loaded");

                    renderIcons(el);
                })
                .catch(err => {
                    dbx.error("icons → sprite load failed:", err);
                });

        } else {
            renderIcons(el);
        }
    }

    function renderIcons(el) {

        $(el).find('.dbx-icon').each(function () {

            const icon = this.getAttribute('data-icon');
            if (!icon) return;

            const size  = this.getAttribute('data-size');
            const color = this.getAttribute('data-color');

            // SVG erstellen
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.classList.add('dbx-icon');

            // size (optional)
            if (size) {
                svg.classList.add('dbx-icon-' + size);
            }

            // color (optional)
            if (color) {
                svg.style.color = color;
            }

            // use
            const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
            use.setAttribute('href', '#' + icon);

            svg.appendChild(use);

            // ersetzen
            this.replaceWith(svg);
        });
    }

    // --------------------------------------------
    // FEATURE REGISTER (WICHTIG!)
    // --------------------------------------------
    dbx.feature.register("icons", {

        scope: "element", // 🔥 FIX

        css: [
            ['css', 'design', 'c-icons.css']
        ],

        js: [
            ['js', 'lib', 'ajax.js']
        ],

        init(el, cfg) {
            init(el, cfg || {});
        }

    });

})(jQuery);
