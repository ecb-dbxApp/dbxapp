/**
 * dbxapp Demo Mode UI guard.
 *
 * The server-side config and dbxDB guards are authoritative. This helper
 * makes their read-only state visible before a user tries to submit a form.
 */
(function (window, document) {
  'use strict';

  if (!document.body || document.body.dataset.dbxDemoMode !== 'read-only') {
    return;
  }

  var message = 'Demo-Modus: Änderungen sind in dieser Installation gesperrt.';

  var mutationPattern = /(?:^|[\s_-])(?:save|create|new|insert|update|delete|remove|clear|batch|upload|crop|resize|speichern|anlegen|erstellen|loeschen|löschen|leeren|entfernen|aendern|ändern|hochladen)(?:$|[\s_-])/i;

  function containsMutationMarker(value) {
    return mutationPattern.test(String(value || ''));
  }

  function isMutationLink(link) {
    var href = String(link && link.getAttribute('href') || '');
    return /[?&]dbx_token=/i.test(href)
      || /[?&](?:dbx_do|dbx_run[123])=[^&]*(?:save|insert|update|delete|remove|clear|batch|upload|crop|resize)/i.test(href);
  }

  function isMutationForm(form) {
    if (!form || String(form.getAttribute('method') || 'get').toLowerCase() === 'get') {
      return false;
    }

    if (isMutationLink({ getAttribute: function () { return form.getAttribute('action') || ''; } })) {
      return true;
    }

    return Array.prototype.some.call(
      form.querySelectorAll('input[name], input[type="submit"], input[type="image"], button'),
      function (control) {
        var name = String(control.name || '');
        var value = String(control.value || '');
        var type = String(control.type || '').toLowerCase();
        var label = [
          value,
          control.textContent,
          control.getAttribute('aria-label'),
          control.getAttribute('title')
        ].join(' ');
        return name === 'dbx_token'
          || ((/^dbx_(?:do|run[123])$/i.test(name) || /(?:action|submit|save|delete)/i.test(name))
            && containsMutationMarker(value || name))
          || (['submit', 'image'].indexOf(type) !== -1 && containsMutationMarker(label));
      }
    );
  }

  function markReadOnly(root) {
    root = root || document;

    root.querySelectorAll('form').forEach(function (form) {
      if (!isMutationForm(form)) {
        return;
      }
      form.dataset.dbxDemoReadonly = '1';
      form.querySelectorAll('button[type="submit"], input[type="submit"], input[type="image"]').forEach(function (control) {
        control.disabled = true;
        control.setAttribute('aria-disabled', 'true');
        control.setAttribute('data-dbx-tooltip', message);
      });
    });

    root.querySelectorAll('a[href]').forEach(function (link) {
      if (!isMutationLink(link)) {
        return;
      }
      link.dataset.dbxDemoReadonly = '1';
      link.setAttribute('aria-disabled', 'true');
      link.setAttribute('data-dbx-tooltip', message);
    });
  }

  document.addEventListener('submit', function (event) {
    if (!isMutationForm(event.target)) {
      return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
  }, true);

  document.addEventListener('click', function (event) {
    var link = event.target.closest && event.target.closest('a[data-dbx-demo-readonly="1"]');
    if (!link) {
      return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
  }, true);

  markReadOnly(document);
  new MutationObserver(function (records) {
    records.forEach(function (record) {
      record.addedNodes.forEach(function (node) {
        if (node.nodeType === 1) {
          markReadOnly(node);
        }
      });
    });
  }).observe(document.body, { childList: true, subtree: true });
})(window, document);
