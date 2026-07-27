(function () {
  "use strict";

  var TEXT = {};
  var KIND_META = {};
  var VALIDATION_META = {};
  var ACTION_META = {};
  var PRESETS = {};

  function t(key, fallback) {
    return Object.prototype.hasOwnProperty.call(TEXT, key) ? String(TEXT[key]) : String(fallback || key);
  }

  function tf(key, replacements, fallback) {
    var message = t(key, fallback);
    Object.keys(replacements || {}).forEach(function (name) {
      message = message.split("{" + name + "}").join(String(replacements[name]));
    });
    return message;
  }

  function applyMessages(root) {
    try {
      TEXT = JSON.parse(root.getAttribute("data-workflow-messages") || "{}");
    } catch (ignore) {
      TEXT = {};
    }

    KIND_META = {
      input: { label: t("kind_input", "Eingabe"), icon: "bi-input-cursor-text", action: "form", defaultLabel: t("js_new_input", "Neue Eingabe"), defaultEvent: t("js_input_complete", "Eingabe vollständig") },
      action: { label: t("kind_action", "Aktion"), icon: "bi-lightning-charge", action: "module", defaultLabel: t("js_new_action", "Neue Aktion"), defaultEvent: t("js_action_complete", "Aktion ausgeführt") },
      check: { label: t("kind_check", "Prüfung"), icon: "bi-shield-check", action: "select", defaultLabel: t("js_new_check", "Neue Prüfung"), defaultEvent: t("js_check_complete", "Prüfung abgeschlossen") },
      decision: { label: t("kind_decision", "Entscheidung"), icon: "bi-signpost-split", action: "select", defaultLabel: t("js_new_decision", "Neue Entscheidung"), defaultEvent: t("js_decision_complete", "Entscheidung getroffen") }
    };

    VALIDATION_META = {
      exactly_one: t("validation_exactly_one", "Genau ein Wert"),
      at_least_one: t("validation_at_least_one", "Mindestens ein Wert"),
      not_empty: t("validation_not_empty", "Nicht leer"),
      positive_integer: t("validation_positive_integer", "Ganzzahl größer 0"),
      confirmed: t("validation_confirmed", "Ausdrücklich bestätigt")
    };

    ACTION_META = {
      form: t("resolver_form", "Eingabeformular öffnen"),
      select: t("resolver_select", "Auswahlformular öffnen"),
      create: t("resolver_create", "Erfassungsformular öffnen"),
      module: t("resolver_module", "Modulformular öffnen")
    };

    PRESETS = {
      payment_terms: {
        kind: "input",
        label: t("js_payment_label", "Zahlungsbedingung"),
        key: "payment_terms",
        event: t("js_payment_event", "Zahlungsbedingung ausgewählt"),
        action: "select",
        mode: "single",
        validation: "exactly_one",
        question: t("js_payment_question", "Welche Zahlungsbedingung ist ausgewählt?"),
        missing: t("js_payment_missing", "Keine Zahlungsbedingung ausgewählt – der Workflow ist unvollständig."),
        resolver: t("js_payment_resolver", "Zahlungsbedingung auswählen"),
        hint: t("js_payment_hint", "Wähle die Zahlungsfrist, die für diesen Vorgang gelten soll."),
        options: t("js_payment_options", "sofort=Sofort fällig")
      }
    };
  }

  function visibleRows(root) {
    return Array.prototype.filter.call(root.querySelectorAll("[data-workflow-step-row]"), function (row) {
      return !row.classList.contains("dbx-workflow-step-row-empty") && row.style.display !== "none";
    });
  }

  function field(row, namePart) {
    return row.querySelector('[name^="' + namePart + '["]');
  }

  function value(row, namePart) {
    var el = field(row, namePart);
    return el ? String(el.value || "").trim() : "";
  }

  function actionChoices(row) {
    return Array.prototype.slice.call(row.querySelectorAll("[data-workflow-action-choice]"));
  }

  function selectedActions(row) {
    return actionChoices(row).filter(function (choice) { return choice.checked; }).map(function (choice) { return choice.value; });
  }

  function setActionSelected(row, action, checked) {
    actionChoices(row).forEach(function (choice) {
      if (choice.value === action) choice.checked = checked;
    });
  }

  function syncActionControls(row, requestedPreferred) {
    var preferred = field(row, "workflow_step_action");
    if (requestedPreferred) setActionSelected(row, requestedPreferred, true);
    var actions = selectedActions(row);
    if (!actions.length) {
      var fallback = requestedPreferred || (preferred && preferred.value) || "form";
      setActionSelected(row, fallback, true);
      actions = selectedActions(row);
    }
    if (preferred) {
      var preferredValue = requestedPreferred || preferred.value;
      if (actions.indexOf(preferredValue) < 0) preferredValue = actions[0];
      Array.prototype.forEach.call(preferred.options, function (option) {
        option.disabled = actions.indexOf(option.value) < 0;
      });
      preferred.value = preferredValue;
    }
    var moduleSetting = row.querySelector("[data-workflow-module-setting]");
    if (moduleSetting) moduleSetting.hidden = actions.indexOf("module") < 0;
  }

  function slug(text) {
    return String(text || "")
      .toLowerCase()
      .replace(/[ä]/g, "ae")
      .replace(/[ö]/g, "oe")
      .replace(/[ü]/g, "ue")
      .replace(/[ß]/g, "ss")
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "");
  }

  function updateRowSummary(row) {
    var kind = value(row, "workflow_step_kind") || "input";
    var meta = KIND_META[kind] || KIND_META.input;
    var label = value(row, "workflow_step_label") || t("new_task", "Neue Aufgabe");
    var keyInput = field(row, "workflow_step_key");
    if (keyInput && !keyInput.value.trim()) keyInput.value = slug(label);
    if (!row.dataset.workflowLastKey && keyInput && keyInput.value.trim()) row.dataset.workflowLastKey = keyInput.value.trim();

    row.dataset.stepKind = kind;
    var icon = row.querySelector("[data-workflow-kind-icon]");
    var kindLabel = row.querySelector("[data-workflow-kind-label]");
    var title = row.querySelector("[data-workflow-step-title]");
    var result = row.querySelector("[data-workflow-step-result]");
    var automation = value(row, "workflow_step_automation");
    var autoBadge = row.querySelector("[data-workflow-auto-badge]");
    var question = field(row, "workflow_step_question");
    var missing = field(row, "workflow_step_missing_message");
    if (question && row.dataset.questionAuto === "1") question.value = tf("default_question", { label: label }, "Ist „{label}“ vollständig und richtig?");
    if (missing && row.dataset.missingAuto === "1") missing.value = tf("default_missing_message", { label: label }, "{label} fehlt oder ist nicht vollständig – der Workflow ist unvollständig.");
    if (icon) icon.className = "bi " + meta.icon;
    if (kindLabel) kindLabel.textContent = meta.label;
    if (title) title.textContent = label;
    if (result) result.textContent = value(row, "workflow_step_event") || t("no_result", "Noch kein Ergebnistext");
    if (autoBadge) autoBadge.hidden = automation !== "observe";
  }

  function renderChecks(root) {
    var rows = visibleRows(root);
    var list = root.querySelector("[data-workflow-check-list]");
    var count = root.querySelector("[data-workflow-check-count]");
    if (count) count.textContent = rows.length + " " + (rows.length === 1 ? t("js_requirement_singular", "Anforderung") : t("js_requirement_plural", "Anforderungen"));
    if (!list) return;
    list.innerHTML = rows.map(function (row) {
      var label = value(row, "workflow_step_label") || value(row, "workflow_step_key") || t("new_task", "Neue Aufgabe");
      var question = value(row, "workflow_step_question") || tf("default_question", { label: label }, "Ist „{label}“ vollständig und richtig?");
      var validation = value(row, "workflow_step_validation") || "exactly_one";
      var required = value(row, "workflow_step_required") !== "0";
      var action = value(row, "workflow_step_action") || "form";
      var resolver = value(row, "workflow_step_resolver_label") || ACTION_META[action] || ACTION_META.form;
      return '<article class="dbx-workflow-check-preview-item">' +
        '<i class="bi bi-shield-check"></i><div><strong>' + escapeHtml(question) + '</strong><small>' + escapeHtml(label) + '</small></div>' +
        '<span class="dbx-workflow-check-rule">' + escapeHtml(VALIDATION_META[validation] || validation) + '</span>' +
        '<span class="dbx-workflow-check-required">' + (required ? t("preview_required", "Pflicht") : t("preview_optional", "Optional")) + '</span>' +
        '<span class="dbx-workflow-check-resolver"><i class="bi bi-box-arrow-in-right"></i> ' + escapeHtml(resolver) + '</span></article>';
    }).join("");
  }

  function updateDependencyOptions(root) {
    var rows = visibleRows(root);
    rows.forEach(function (row) {
      var select = field(row, "workflow_step_depends_on");
      if (!select) return;
      var selected = select.dataset.workflowPendingDependency || select.value;
      var ownKey = value(row, "workflow_step_key");
      var html = '<option value="">' + escapeHtml(t("js_direct_after_previous", "Direkt nach vorherigem Schritt")) + '</option>';
      rows.forEach(function (candidate) {
        var key = value(candidate, "workflow_step_key");
        if (!key || key === ownKey) return;
        var label = value(candidate, "workflow_step_label") || key;
        html += '<option value="' + escapeHtml(key) + '">' + escapeHtml(label) + "</option>";
      });
      select.innerHTML = html;
      select.value = selected;
      if (selected && select.value !== selected) select.dataset.workflowPendingDependency = selected;
      else delete select.dataset.workflowPendingDependency;
    });
  }

  function migrateDependencyKey(root, row) {
    var oldKey = row.dataset.workflowLastKey || "";
    var newKey = value(row, "workflow_step_key");
    if (oldKey && newKey && oldKey !== newKey) {
      visibleRows(root).forEach(function (candidate) {
        var select = field(candidate, "workflow_step_depends_on");
        if (!select) return;
        var dependency = select.dataset.workflowPendingDependency || select.value;
        if (dependency === oldKey) select.dataset.workflowPendingDependency = newKey;
      });
    }
    if (newKey) row.dataset.workflowLastKey = newKey;
  }

  function escapeHtml(text) {
    var div = document.createElement("div");
    div.textContent = String(text || "");
    return div.innerHTML;
  }

  function renumber(root) {
    visibleRows(root).forEach(function (row, index) {
      var number = row.querySelector("[data-workflow-step-number]");
      if (number) number.textContent = String(index + 1).padStart(2, "0");
      updateRowSummary(row);
    });
    updateDependencyOptions(root);
    renderChecks(root);
    validate(root);
    draw(root);
  }

  function setStatus(root, type, title, items) {
    var box = root.querySelector("[data-workflow-validation]");
    if (!box) return;
    box.className = "dbx-workflow-validation is-" + type;
    var icon = type === "error" ? "bi-x-octagon" : (type === "warning" ? "bi-exclamation-triangle" : "bi-check2-circle");
    box.innerHTML = '<i class="bi ' + icon + '"></i><div><strong>' + escapeHtml(title) + "</strong>" +
      (items.length ? "<ul><li>" + items.map(escapeHtml).join("</li><li>") + "</li></ul>" : "") + "</div>";
  }

  function validate(root) {
    var rows = visibleRows(root);
    var errors = [];
    var warnings = [];
    var byKey = {};
    var positions = {};

    rows.forEach(function (row, index) {
      var label = value(row, "workflow_step_label");
      var key = value(row, "workflow_step_key");
      if (!label) errors.push(tf("js_error_label_missing", { step: index + 1 }, "Schritt {step} braucht eine Bezeichnung."));
      if (!key) errors.push(tf("js_error_key_missing", { step: index + 1 }, "Schritt {step} braucht einen internen Schlüssel."));
      if (key && byKey[key]) errors.push(tf("js_error_key_duplicate", { key: key }, "Der Schlüssel „{key}“ wird mehrfach verwendet."));
      if (key) {
        byKey[key] = row;
        positions[key] = index;
      }
      var kind = value(row, "workflow_step_kind");
      var question = value(row, "workflow_step_question");
      if (!question) errors.push(tf("js_error_question_missing", { label: label || key }, "Für „{label}“ fehlt die automatisch verwendete Prüffrage."));
      var options = value(row, "workflow_step_options").split(/\r?\n|,/).filter(function (item) { return item.trim(); });
      if (kind === "decision" && options.length < 2) {
        errors.push(tf("js_error_decision_options", { label: label || key }, "Die Entscheidung „{label}“ braucht mindestens zwei Ergebnisse."));
      }
      if (value(row, "workflow_step_automation") === "observe" && kind !== "check" && kind !== "decision") {
        warnings.push(tf("js_warning_automation", { label: label || key }, "Automatik bei „{label}“ ist nur sinnvoll als Prüfung oder Entscheidung."));
      }
      var actions = selectedActions(row);
      if (actions.indexOf("select") >= 0 && !options.length && !value(row, "workflow_step_source")) {
        warnings.push(tf("js_warning_select_source", { label: label || key }, "Das Auswahlformular für „{label}“ hat noch keine Auswahlwerte oder Datenquelle."));
      }
      if (actions.indexOf("module") >= 0 && !value(row, "workflow_step_module_links")) {
        warnings.push(tf("js_warning_module_link", { label: label || key }, "Für „{label}“ ist noch kein Modulformular hinterlegt."));
      }
    });

    rows.forEach(function (row) {
      var key = value(row, "workflow_step_key");
      var depends = value(row, "workflow_step_depends_on");
      if (!depends) return;
      if (!byKey[depends]) errors.push(tf("js_error_dependency_missing", { dependency: depends, key: key }, "Die Abhängigkeit „{dependency}“ von „{key}“ existiert nicht."));
      else if (positions[depends] >= positions[key]) errors.push(tf("js_error_dependency_order", { dependency: depends, key: key }, "„{key}“ muss hinter seiner Voraussetzung „{dependency}“ liegen."));
    });

    if (!rows.length) errors.push(t("validation_minimum_step", "Mindestens ein Schritt ist erforderlich."));
    if (errors.length) setStatus(root, "error", t("js_status_invalid", "Workflow noch nicht speicherbar"), errors);
    else if (warnings.length) setStatus(root, "warning", t("js_status_warning", "Workflow ist speicherbar – Hinweise prüfen"), warnings);
    else setStatus(root, "ok", t("js_status_valid", "Prüfung wurde automatisch abgeleitet"), [
      tf("js_status_valid_detail", { steps: rows.length, requirements: rows.length }, "{steps} Schritte liefern {requirements} Prüfanforderungen samt passendem Bearbeitungsweg.")
    ]);

    var form = root.closest("form");
    if (form) form.dataset.workflowValid = errors.length ? "0" : "1";
    return errors.length === 0;
  }

  function point(canvas, element, side) {
    var base = canvas.getBoundingClientRect();
    var rect = element.getBoundingClientRect();
    return {
      x: rect.left - base.left + rect.width / 2,
      y: (side === "top" ? rect.top : rect.bottom) - base.top
    };
  }

  function pathBetween(a, b) {
    var bend = Math.max(28, Math.abs(b.y - a.y) * 0.42);
    return "M " + a.x + " " + a.y + " C " + a.x + " " + (a.y + bend) + ", " + b.x + " " + (b.y - bend) + ", " + b.x + " " + b.y;
  }

  function draw(root) {
    window.requestAnimationFrame(function () {
      var canvas = root.querySelector("[data-workflow-canvas]");
      var svg = root.querySelector("[data-workflow-lines]");
      var start = root.querySelector("[data-workflow-start]");
      var finish = root.querySelector("[data-workflow-finish]");
      var rows = visibleRows(root);
      if (!canvas || !svg || !start || !finish) return;

      var width = Math.max(1, canvas.clientWidth);
      var height = Math.max(1, canvas.scrollHeight);
      svg.setAttribute("viewBox", "0 0 " + width + " " + height);
      svg.setAttribute("width", width);
      svg.setAttribute("height", height);

      var byKey = {};
      rows.forEach(function (row) { byKey[value(row, "workflow_step_key")] = row; });
      var markup = '<defs><marker id="dbxWorkflowArrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z"></path></marker></defs>';
      rows.forEach(function (row, index) {
        var depends = value(row, "workflow_step_depends_on");
        var source = (depends && byKey[depends]) ? byKey[depends] : (index ? rows[index - 1] : start);
        var a = point(canvas, source, "bottom");
        var b = point(canvas, row, "top");
        var branch = value(row, "workflow_step_depends_value");
        var sourceKind = source.dataset ? source.dataset.stepKind : "start";
        markup += '<path class="dbx-workflow-flow-line is-' + escapeHtml(sourceKind || "input") + '" d="' + pathBetween(a, b) + '" marker-end="url(#dbxWorkflowArrow)"></path>';
        if (branch) {
          markup += '<text class="dbx-workflow-flow-label" x="' + ((a.x + b.x) / 2 + 9) + '" y="' + ((a.y + b.y) / 2) + '">' + escapeHtml(branch) + "</text>";
        }
      });
      var last = rows.length ? rows[rows.length - 1] : start;
      markup += '<path class="dbx-workflow-flow-line is-finish" d="' + pathBetween(point(canvas, last, "bottom"), point(canvas, finish, "top")) + '" marker-end="url(#dbxWorkflowArrow)"></path>';
      svg.innerHTML = markup;
    });
  }

  function setField(row, namePart, fieldValue) {
    var el = field(row, namePart);
    if (el) el.value = fieldValue;
  }

  function resetRow(row, kind, presetName) {
    Array.prototype.forEach.call(row.querySelectorAll("input, textarea, select"), function (el) {
      if (el.name && (el.name.indexOf("workflow_step_present") >= 0 || el.name.indexOf("workflow_step_action_contract") >= 0)) return;
      if (el.type === "checkbox") el.checked = el.name.indexOf("workflow_step_active") >= 0;
      else el.value = "";
    });
    var kindField = field(row, "workflow_step_kind");
    var actionField = field(row, "workflow_step_action");
    var requiredField = field(row, "workflow_step_required");
    var modeField = field(row, "workflow_step_mode");
    var automationField = field(row, "workflow_step_automation");
    var labelField = field(row, "workflow_step_label");
    var eventField = field(row, "workflow_step_event");
    var optionsField = field(row, "workflow_step_options");
    var meta = KIND_META[kind] || KIND_META.input;
    if (kindField) kindField.value = kind;
    if (actionField) actionField.value = meta.action;
    syncActionControls(row, meta.action);
    if (requiredField) requiredField.value = "1";
    if (modeField) modeField.value = "single";
    if (automationField) automationField.value = (kind === "check") ? "observe" : "manual";
    if (labelField) labelField.value = meta.defaultLabel;
    if (eventField) eventField.value = meta.defaultEvent;
      if (optionsField && kind === "decision") optionsField.value = "yes=" + t("js_yes", "Ja") + "\nno=" + t("js_no", "Nein");
    setField(row, "workflow_step_validation", "exactly_one");
    setField(row, "workflow_step_question", tf("default_question", { label: meta.defaultLabel }, "Ist „{label}“ vollständig und richtig?"));
    setField(row, "workflow_step_missing_message", tf("default_missing_message", { label: meta.defaultLabel }, "{label} fehlt oder ist nicht vollständig – der Workflow ist unvollständig."));
    setField(row, "workflow_step_resolver_label", ACTION_META[meta.action] || ACTION_META.form);
    row.dataset.questionAuto = "1";
    row.dataset.missingAuto = "1";
    delete row.dataset.workflowLastKey;

    var preset = PRESETS[presetName] || null;
    if (preset) {
      kind = preset.kind || kind;
      actionChoices(row).forEach(function (choice) { choice.checked = false; });
      setField(row, "workflow_step_kind", kind);
      setField(row, "workflow_step_label", preset.label || meta.defaultLabel);
      setField(row, "workflow_step_key", preset.key || slug(preset.label));
      setField(row, "workflow_step_event", preset.event || t("js_default_event", "Angabe vollständig"));
      setField(row, "workflow_step_action", preset.action || meta.action);
      syncActionControls(row, preset.action || meta.action);
      setField(row, "workflow_step_mode", preset.mode || "single");
      setField(row, "workflow_step_validation", preset.validation || "exactly_one");
      setField(row, "workflow_step_question", preset.question || "");
      setField(row, "workflow_step_missing_message", preset.missing || "");
      setField(row, "workflow_step_resolver_label", preset.resolver || ACTION_META[preset.action] || ACTION_META.form);
      setField(row, "workflow_step_hint", preset.hint || "");
      setField(row, "workflow_step_options", preset.options || "");
      row.dataset.questionAuto = "0";
      row.dataset.missingAuto = "0";
    }
    row.classList.remove("dbx-workflow-step-row-empty");
    row.style.display = "";
    row.setAttribute("draggable", "true");
    var details = row.querySelector("details");
    if (details) details.open = true;
  }

  function addRow(root, kind, beforeRow, presetName) {
    var row = root.querySelector(".dbx-workflow-step-row-empty");
    if (!row) {
      setStatus(root, "error", t("js_no_free_step", "Kein freier Schritt mehr"), [
        t("js_no_free_step_detail", "Workflow speichern und erneut öffnen, um weitere Schritte anzulegen.")
      ]);
      return null;
    }
    resetRow(row, kind || "input", presetName || "");
    if (beforeRow && beforeRow.parentNode === row.parentNode) row.parentNode.insertBefore(row, beforeRow);
    renumber(root);
    var label = field(row, "workflow_step_label");
    if (label) label.focus();
    return row;
  }

  function removeRow(root, row) {
    var active = field(row, "workflow_step_active");
    var label = field(row, "workflow_step_label");
    var key = field(row, "workflow_step_key");
    if (active) active.checked = false;
    if (label) label.value = "";
    if (key) key.value = "";
    row.classList.add("dbx-workflow-step-row-empty");
    row.style.display = "none";
    var list = root.querySelector("[data-workflow-step-list]");
    if (list) list.appendChild(row);
    renumber(root);
  }

  function nearestRow(list, y) {
    var rows = Array.prototype.filter.call(list.querySelectorAll("[data-workflow-step-row]"), function (row) {
      return !row.classList.contains("dbx-workflow-step-row-empty") && !row.classList.contains("is-dragging");
    });
    var closest = { offset: Number.NEGATIVE_INFINITY, row: null };
    rows.forEach(function (row) {
      var box = row.getBoundingClientRect();
      var offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) closest = { offset: offset, row: row };
    });
    return closest.row;
  }

  function init(root) {
    if (!root || root.dataset.workflowDesignerReady === "1") return;
    applyMessages(root);
    root.dataset.workflowDesignerReady = "1";
    var list = root.querySelector("[data-workflow-step-list]");
    if (!list) return;

    root.addEventListener("input", function (event) {
      var row = event.target.closest("[data-workflow-step-row]");
      if (row) {
        if (event.target === field(row, "workflow_step_key")) migrateDependencyKey(root, row);
        if (event.target.name && event.target.name.indexOf("workflow_step_question") >= 0) row.dataset.questionAuto = "0";
        if (event.target.name && event.target.name.indexOf("workflow_step_missing_message") >= 0) row.dataset.missingAuto = "0";
        updateRowSummary(row);
      }
      updateDependencyOptions(root);
      renderChecks(root);
      validate(root);
      draw(root);
    });

    root.addEventListener("change", function (event) {
      var row = event.target.closest("[data-workflow-step-row]");
      if (!row) return;
      if (event.target === field(row, "workflow_step_depends_on")) delete event.target.dataset.workflowPendingDependency;
      if (event.target.matches("[data-workflow-action-choice]")) {
        syncActionControls(row, "");
      }
      if (event.target.name && event.target.name.indexOf("workflow_step_kind") >= 0) {
        var meta = KIND_META[event.target.value] || KIND_META.input;
        var action = field(row, "workflow_step_action");
        if (action) action.value = meta.action;
        syncActionControls(row, meta.action);
      } else if (event.target === field(row, "workflow_step_action")) {
        syncActionControls(row, event.target.value);
      }
      updateRowSummary(row);
      renumber(root);
    });

    root.addEventListener("click", function (event) {
      var add = event.target.closest("[data-workflow-add-kind]");
      if (add) {
        event.preventDefault();
        addRow(root, add.dataset.workflowAddKind || "input", null, add.dataset.workflowAddPreset || "");
        return;
      }
      var remove = event.target.closest("[data-workflow-remove-step]");
      if (remove) {
        event.preventDefault();
        removeRow(root, remove.closest("[data-workflow-step-row]"));
      }
    });

    root.addEventListener("pointerdown", function (event) {
      var handle = event.target.closest("[data-workflow-drag-handle]");
      if (handle) {
        var row = handle.closest("[data-workflow-step-row]");
        if (row) row.dataset.dragReady = "1";
      }
    });

    root.addEventListener("pointerup", function () {
      visibleRows(root).forEach(function (row) { delete row.dataset.dragReady; });
    });

    root.addEventListener("dragstart", function (event) {
      var palette = event.target.closest("[data-workflow-add-kind]");
      if (palette) {
        event.dataTransfer.setData("text/x-dbx-workflow-kind", palette.dataset.workflowAddKind || "input");
        event.dataTransfer.setData("text/x-dbx-workflow-preset", palette.dataset.workflowAddPreset || "");
        event.dataTransfer.effectAllowed = "copy";
        return;
      }
      var row = event.target.closest("[data-workflow-step-row]");
      var handle = event.target.closest("[data-workflow-drag-handle]");
      if (!row || (!handle && row.dataset.dragReady !== "1")) {
        event.preventDefault();
        return;
      }
      row.classList.add("is-dragging");
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/x-dbx-workflow-row", "1");
    });

    list.addEventListener("dragover", function (event) {
      event.preventDefault();
      var dragging = root.querySelector(".dbx-workflow-step-row.is-dragging");
      if (dragging) {
        var before = nearestRow(list, event.clientY);
        if (before) list.insertBefore(dragging, before);
        else {
          var firstEmpty = list.querySelector(".dbx-workflow-step-row-empty");
          list.insertBefore(dragging, firstEmpty || null);
        }
        draw(root);
      }
      event.dataTransfer.dropEffect = dragging ? "move" : "copy";
    });

    list.addEventListener("drop", function (event) {
      event.preventDefault();
      var kind = event.dataTransfer.getData("text/x-dbx-workflow-kind");
      var preset = event.dataTransfer.getData("text/x-dbx-workflow-preset");
      if (kind) addRow(root, kind, nearestRow(list, event.clientY), preset);
      var dragging = root.querySelector(".dbx-workflow-step-row.is-dragging");
      if (dragging) dragging.classList.remove("is-dragging");
      renumber(root);
    });

    root.addEventListener("dragend", function () {
      visibleRows(root).forEach(function (row) {
        row.classList.remove("is-dragging");
        delete row.dataset.dragReady;
      });
      renumber(root);
    });

    var form = root.closest("form");
    if (form) {
      form.addEventListener("submit", function (event) {
        if (!validate(root)) {
          event.preventDefault();
          event.stopImmediatePropagation();
          var box = root.querySelector("[data-workflow-validation]");
          if (box) box.scrollIntoView({ behavior: "smooth", block: "center" });
        }
      }, true);
    }

    if (window.ResizeObserver) {
      var observer = new ResizeObserver(function () { draw(root); });
      observer.observe(root.querySelector("[data-workflow-canvas]"));
    } else {
      window.addEventListener("resize", function () { draw(root); });
    }
    visibleRows(root).forEach(function (row) {
      row.dataset.workflowLastKey = value(row, "workflow_step_key");
      syncActionControls(row, "");
    });
    renumber(root);
  }

  function initAll() {
    Array.prototype.forEach.call(document.querySelectorAll("[data-workflow-designer]"), init);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initAll);
  else initAll();

  document.addEventListener("dbx:content:loaded", initAll);
  window.setTimeout(initAll, 0);
}());
