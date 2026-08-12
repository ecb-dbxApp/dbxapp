(function () {
  "use strict";

  let previousStorageMode = null;

  function syncStorageMode() {
    const active = document.querySelector("input[name=storage_mode]:checked");
    const mode = active ? active.value : "";
    const fields = document.querySelector("[data-install-db-fields]");
    const confirmation = document.querySelector("[data-install-storage-confirm]");
    const confirmationInput = confirmation
      ? confirmation.querySelector("input[name=storage_advanced_confirm]")
      : null;

    if (fields) {
      fields.hidden = mode !== "mysql";
    }

    document.querySelectorAll("[data-install-storage-note]").forEach(function (note) {
      note.hidden = note.dataset.installStorageNote !== mode;
    });

    if (confirmation) {
      confirmation.hidden = mode === "sqlite";
    }

    if (confirmationInput && (
      mode === "sqlite"
      || (previousStorageMode !== null && previousStorageMode !== mode)
    )) {
      confirmationInput.checked = false;
    }

    previousStorageMode = mode;
  }

  function markCheckedValuesChanged(event) {
    const form = event.target.closest(".dbx-install-form");
    const status = form ? form.querySelector("[data-install-check-status][data-install-checked='1']") : null;
    if (!status || event.target.closest(".dbx-install-actions")) {
      return;
    }

    status.dataset.installChecked = "changed";
    status.classList.remove("alert-success");
    status.classList.add("alert-warning");
    const icon = status.querySelector(":scope > i");
    const title = status.querySelector("strong");
    const detail = status.querySelector("span");
    if (icon) {
      icon.className = "bi bi-exclamation-triangle-fill";
    }
    if (title) {
      title.textContent = "Werte seit der letzten Prüfung geändert";
    }
    if (detail) {
      detail.textContent = "Bitte prüfen Sie die geänderten Angaben erneut oder verwenden Sie „Weiter“, um sie beim Fortsetzen nochmals zu validieren.";
    }
  }

  function initInstallPasswordRules() {
    const rules = document.querySelector("[data-install-password-rules]");
    const password = document.querySelector("input[name=admin_password]");
    const repeat = document.querySelector("input[name=admin_password_repeat]");
    const minimum = document.querySelector("input[name=password_min_length]");
    if (!rules || !password || !repeat || !minimum) {
      return;
    }

    function setRule(name, valid, active) {
      const item = rules.querySelector("[data-password-rule='" + name + "']");
      if (!item) {
        return;
      }
      const icon = item.querySelector("i");
      item.classList.toggle("is-valid", active && valid);
      item.classList.toggle("is-missing", active && !valid);
      if (icon) {
        icon.className = "bi " + (!active
          ? "bi-circle"
          : (valid ? "bi-check-circle-fill" : "bi-x-circle-fill"));
      }
    }

    function updateRules() {
      const value = password.value || "";
      const repeated = repeat.value || "";
      const active = value !== "" || repeated !== "";
      const minimumLength = Math.max(6, Math.min(128, Number(minimum.value) || 6));
      const label = rules.querySelector("[data-password-min-label]");
      if (label) {
        label.textContent = String(minimumLength);
      }
      setRule("length", Array.from(value).length >= minimumLength, active);
      setRule("letters", /[A-Z]/.test(value) && /[a-z]/.test(value), active);
      setRule("number", /[0-9]/.test(value), active);
      setRule("special", /[^A-Za-z0-9]/.test(value), active);
      setRule("match", value !== "" && value === repeated, active);
    }

    password.addEventListener("input", updateRules);
    repeat.addEventListener("input", updateRules);
    minimum.addEventListener("input", updateRules);
    updateRules();
  }

  document.addEventListener("click", function (event) {
    const button = event.target.closest("[data-dbx-password-toggle]");
    if (!button) {
      return;
    }

    const input = document.getElementById(button.dataset.dbxPasswordToggle || "");
    if (!input) {
      return;
    }

    const show = input.type === "password";
    const icon = button.querySelector("i");
    input.type = show ? "text" : "password";
    input.dataset.dbxPasswordVisible = show ? "1" : "0";
    button.setAttribute("aria-pressed", show ? "true" : "false");
    button.setAttribute("aria-label", show ? "Passwort verbergen" : "Passwort anzeigen");
    button.setAttribute("data-dbx-tooltip", show ? "Passwort verbergen" : "Passwort anzeigen");

    if (icon) {
      icon.classList.toggle("bi-eye", !show);
      icon.classList.toggle("bi-eye-slash", show);
    }

    input.focus();
  });

  document.addEventListener("change", function (event) {
    markCheckedValuesChanged(event);
    if (event.target.matches("input[name=storage_mode]")) {
      syncStorageMode();
    }
  });

  document.addEventListener("input", markCheckedValuesChanged);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      syncStorageMode();
      initInstallPasswordRules();
    });
  } else {
    syncStorageMode();
    initInstallPasswordRules();
  }
}());
