tinymce.init({
    selector:   "textarea",
    width:      '100%',
    height:     270,
    plugins:    "link",
    statusbar:  false,
    toolbar:    "link"
});

// Prevent bootstrap dialog from blocking focusin
document.addEventListener('focusin', function(e) {
    if (e.target.closest(".tox-tinymce-aux, .moxman-window, .tam-assetmanager-root") !== null) {
		e.stopImmediatePropagation();
	}
});
