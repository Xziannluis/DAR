"use strict";

const themeToggleButtons = document.querySelectorAll("[data-theme-toggle]");
let savedTheme = "light";
try {
  savedTheme = localStorage.getItem("dar-rms-theme") || "light";
} catch {
  savedTheme = "light";
}
if (savedTheme === "dark") document.documentElement.dataset.theme = "dark";

const updateThemeToggleState = () => {
  const isDark = document.documentElement.dataset.theme === "dark";
  themeToggleButtons.forEach((button) => button.setAttribute("aria-pressed", String(isDark)));
};
updateThemeToggleState();
themeToggleButtons.forEach((button) => {
  button.addEventListener("click", () => {
    const isDark = document.documentElement.dataset.theme !== "dark";
    if (isDark) {
      document.documentElement.dataset.theme = "dark";
    } else {
      delete document.documentElement.dataset.theme;
    }
    try {
      localStorage.setItem("dar-rms-theme", isDark ? "dark" : "light");
    } catch {
      // Keep the in-page theme toggle working if storage is unavailable.
    }
    updateThemeToggleState();
  });
});

document.querySelectorAll("[data-logbook-filters]").forEach((form) => {
  const searchInput = form.querySelector('input[name="search"]');
  let searchTimer;

  form.querySelectorAll("select").forEach((select) => {
    select.addEventListener("change", () => {
      window.clearTimeout(searchTimer);
      form.requestSubmit();
    });
  });

  searchInput?.addEventListener("input", () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => form.requestSubmit(), 350);
  });
});

document.querySelectorAll("[data-reset-dialog]").forEach((button) => {
  button.addEventListener("click", () => {
    const dialog = document.getElementById(button.dataset.resetDialog);
    if (!(dialog instanceof HTMLDialogElement)) return;
    dialog.showModal();
    dialog.querySelector('input[name="new_password"]')?.focus();
  });
});

document.querySelectorAll("[data-close-dialog]").forEach((button) => {
  button.addEventListener("click", () => {
    const dialog = button.closest("dialog");
    if (dialog instanceof HTMLDialogElement) dialog.close();
  });
});

document.querySelectorAll("[data-password-toggle]").forEach((toggle) => {
  toggle.addEventListener("click", () => {
    const input = document.getElementById(toggle.dataset.passwordToggle);
    if (!(input instanceof HTMLInputElement)) return;

    const showPassword = input.type === "password";
    input.type = showPassword ? "text" : "password";
    toggle.setAttribute("aria-pressed", String(showPassword));
    toggle.setAttribute(
      "aria-label",
      showPassword ? "Hide password" : "Show password",
    );
  });
});

const deliveryLabels = {
  handcarry: "Handcarry",
  email: "Email",
  jrs: "JRS",
  philpost: "PhilPost",
  odts: "ODTS",
};

document.querySelectorAll("[data-service-picker]").forEach((picker) => {
  let initialMethods = [];
  let initialDetails = {};
  try {
    initialMethods = JSON.parse(picker.dataset.initialMethods || "[]");
    initialDetails = JSON.parse(picker.dataset.initialDetails || "{}");
  } catch {
    initialMethods = [];
    initialDetails = {};
  }

  const syncRows = () => {
    const rows = Array.from(picker.querySelectorAll(".service-picker-row"));
    const selectedMethods = rows.map((row) => row.querySelector("select")?.value).filter(Boolean);
    rows.forEach((row) => {
      const select = row.querySelector("select");
      const selected = select.value;
      const removeButton = row.querySelector(".remove-service-button");
      if (removeButton) removeButton.hidden = rows.length < 2;
      select.querySelectorAll("option").forEach((option) => {
        option.disabled = option.value !== "" && selectedMethods.includes(option.value) && option.value !== selected;
      });
    });
    const addButton = document.querySelector(`[data-add-service="${picker.id}"]`);
    if (addButton) addButton.hidden = selectedMethods.length === 0;
  };

  const addRow = (selectedMethod = "") => {
    const row = document.createElement("div");
    row.className = "service-picker-row";
    const serviceSelect = document.createElement("select");
    serviceSelect.name = "delivery_methods[]";
    serviceSelect.required = true;
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = "Choose a messengerial service";
    placeholder.disabled = true;
    placeholder.selected = selectedMethod === "";
    serviceSelect.append(placeholder);
    const detailFields = [];
    Object.entries(deliveryLabels).forEach(([method, labelText]) => {
      const option = document.createElement("option");
      option.value = method;
      option.textContent = labelText;
      option.selected = method === selectedMethod;
      serviceSelect.append(option);

      const details = document.createElement("div");
      details.className = "service-detail-field";
      details.dataset.method = method;
      details.hidden = method !== selectedMethod;
      const detailInputs = method === "handcarry"
        ? [
            ["handcarry_name", "Handcarry name"],
            ["receiver_name", "Name of receiver"],
          ]
        : method === "jrs"
          ? [["reference", "JRS reference"], ["receiver_name", "Name of receiver"]]
          : method === "philpost"
            ? [["reference", "PhilPost reference"], ["receiver_name", "Name of receiver"]]
            : [["", `${labelText} details`]];
      detailInputs.forEach(([fieldName, fieldLabel]) => {
        const detailsLabel = document.createElement("label");
        const input = document.createElement("input");
        input.type = "text";
        input.name = fieldName
          ? `delivery_details[${method}][${fieldName}]`
          : `delivery_details[${method}]`;
        input.maxLength = 200;
        input.required = method === selectedMethod;
        input.disabled = method !== selectedMethod;
        const storedDetail = initialDetails[method];
        input.value = fieldName
          ? (typeof storedDetail === "object" && storedDetail !== null ? storedDetail[fieldName] || "" : ((fieldName === "handcarry_name" || fieldName === "reference") && typeof storedDetail === "string" ? storedDetail : ""))
          : (typeof storedDetail === "string" ? storedDetail : "");
        input.placeholder = fieldLabel;
        input.id = `${picker.id}-${rowsafe(method)}-${fieldName || "details"}-${Math.random().toString(36).slice(2, 7)}`;
        detailsLabel.htmlFor = input.id;
        detailsLabel.textContent = fieldLabel;
        details.append(detailsLabel, input);
      });
      detailFields.push(details);
    });
    serviceSelect.addEventListener("change", () => {
      row.querySelectorAll(".service-detail-field").forEach((field) => {
        const active = field.dataset.method === serviceSelect.value;
        field.hidden = !active;
        field.querySelectorAll("input").forEach((detailInput) => {
          detailInput.disabled = !active;
          detailInput.required = active;
        });
      });
      syncRows();
    });
    row.append(serviceSelect, ...detailFields);

    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "remove-service-button";
    remove.textContent = "Remove service";
    remove.addEventListener("click", () => {
      row.remove();
      syncRows();
    });
    row.append(remove);
    picker.append(row);
    picker.querySelector(".service-picker-fallback")?.remove();
    syncRows();
  };

  const rowsafe = (value) => value.replace(/[^a-z0-9_-]/gi, "-");
  if (Array.isArray(initialMethods) && initialMethods.length) {
    initialMethods.forEach((method) => addRow(method));
  } else {
    addRow();
  }
  document.querySelector(`[data-add-service="${picker.id}"]`)?.addEventListener("click", () => addRow());
});

document.addEventListener("click", (event) => {
  if (!(event.target instanceof Element)) return;

  if (event.target.closest("[data-print-logbook]")) {
    window.print();
    return;
  }

  const button = event.target.closest("[data-print-record]");
  if (!(button instanceof HTMLButtonElement)) return;

  const record = button.closest(".outgoing-record");
  if (!(record instanceof HTMLElement)) return;

  const finishPrinting = () => {
    document.body.classList.remove("print-record-mode");
    record.classList.remove("print-target");
  };
  document.body.classList.add("print-record-mode");
  record.classList.add("print-target");
  window.addEventListener("afterprint", finishPrinting, { once: true });
  window.print();
});

document.querySelectorAll("form").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!form.checkValidity()) return;

    const confirmation = form.dataset.confirm;
    if (confirmation && !window.confirm(confirmation)) {
      event.preventDefault();
      return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    if (!(submitButton instanceof HTMLButtonElement)) return;

    submitButton.disabled = true;
    submitButton.setAttribute("aria-busy", "true");
    const label = submitButton.querySelector("span");
    if (label) {
      label.textContent =
        form.querySelector('[name="action"]')?.value === "login"
          ? "Signing in…"
          : "Please wait…";
    }
  });
});
