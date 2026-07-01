import { useState, useEffect, useRef } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
import AppLayout from "../../layouts/AppLayout";
import { __ } from "../../lib/i18n";
const AUTO_DETECT_MAP = {
  order_no: ["order_no", "order no", "orderno", "order number", "order_number", "wo_no", "work_order", "wo no"],
  product_name: ["product_name", "product name", "productname", "product", "item", "item name", "description product"],
  quantity: ["quantity", "qty", "planned_qty", "planned qty", "amount"],
  line_code: ["line_code", "line code", "linecode", "line", "production_line"],
  product_type_code: ["product_type_code", "product type code", "product_type", "product type", "type code", "type"],
  priority: ["priority", "prio"],
  due_date: ["due_date", "due date", "duedate", "deadline", "target date", "delivery_date"],
  description: ["description", "desc", "notes", "comment", "remarks"]
};
function Icon({ d, className = "w-5 h-5" }) {
  return /* @__PURE__ */ React.createElement("svg", { className, fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d }));
}
function buildInitialMappings(headers, prevMapping) {
  const result = {};
  for (const h of headers) {
    const raw = prevMapping?.[h] ?? "_ignore";
    const isCustom = raw.startsWith("custom:");
    result[h] = {
      select: isCustom ? "__custom__" : raw,
      customKey: isCustom ? raw.slice(7) : ""
    };
  }
  return result;
}
function CsvImportMapping() {
  const {
    headers = [],
    previewRows = [],
    totalRows = 0,
    path = "",
    savedMappings = [],
    systemFields = {},
    existingMapping = null,
    importStrategy = "update_or_create",
    targetLineId = "",
    importWeek = "",
    importMonth = "",
    productionYear = (/* @__PURE__ */ new Date()).getFullYear(),
    prevMapping = null,
    mappingError = null,
    csrf_token: csrfToken
  } = usePage().props;
  const initialPrev = prevMapping ?? existingMapping?.mapping_config?.column_mappings ?? null;
  const [mappings, setMappings] = useState(() => buildInitialMappings(headers, initialPrev));
  const [saveMappingEnabled, setSaveMappingEnabled] = useState(false);
  const formRef = useRef(null);
  const mappedCount = Object.values(mappings).filter(
    (m) => m.select && m.select !== "_ignore"
  ).length;
  const setField = (header, field, value) => {
    setMappings((prev) => ({
      ...prev,
      [header]: { ...prev[header], [field]: value }
    }));
  };
  const autoMap = () => {
    setMappings((prev) => {
      const next = { ...prev };
      for (const h of headers) {
        const norm = h.toLowerCase().trim();
        for (const [field, aliases] of Object.entries(AUTO_DETECT_MAP)) {
          if (aliases.includes(norm)) {
            next[h] = { select: field, customKey: "" };
            break;
          }
        }
      }
      return next;
    });
  };
  const clearAll = () => {
    setMappings(() => buildInitialMappings(headers, null));
  };
  const loadProfile = (columnMappings) => {
    setMappings(() => buildInitialMappings(headers, columnMappings));
  };
  const handleSubmit = (e) => {
    const form = e.target;
    form.querySelectorAll("input[data-custom-resolved]").forEach((el) => el.remove());
    for (const h of headers) {
      const m = mappings[h];
      if (m.select === "__custom__") {
        const key = (m.customKey || "").trim();
        const hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = `mapping[${h}]`;
        hidden.value = key ? `custom:${key}` : "_ignore";
        hidden.dataset.customResolved = "1";
        form.appendChild(hidden);
      }
    }
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Map Columns") }), /* @__PURE__ */ React.createElement("nav", { className: "flex items-center gap-2 text-sm text-om-muted mb-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:text-om-ink" }, __("Dashboard")), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/csv-import", className: "hover:text-om-ink" }, __("CSV Import")), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-ink font-medium" }, __("Map Columns"))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Map Columns")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, "Assign each CSV column to a system field or a custom key.", " ", /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-accent" }, totalRows, " rows"), " to import \xB7 Strategy: ", /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, importStrategy.replace(/_/g, " ")))), /* @__PURE__ */ React.createElement(Link, { href: "/admin/csv-import", className: "btn-touch btn-secondary text-sm" }, "\u2190 ", __("Back"))), mappingError && /* @__PURE__ */ React.createElement("div", { className: "mb-4 p-4 bg-om-blocked-bg border border-om-line rounded-om-sm flex items-start gap-3" }, /* @__PURE__ */ React.createElement(Icon, { d: "M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z", className: "w-5 h-5 text-om-blocked shrink-0 mt-0.5" }), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-blocked" }, mappingError)), /* @__PURE__ */ React.createElement(
    "form",
    {
      method: "POST",
      action: "/admin/csv-import/process",
      ref: formRef,
      onSubmit: handleSubmit
    },
    /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrfToken }),
    /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "file_path", value: path }),
    /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "import_strategy", value: importStrategy }),
    /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "target_line_id", value: targetLineId ?? "" }),
    /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "import_week", value: importWeek ?? "" }),
    /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "import_month", value: importMonth ?? "" }),
    /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "production_year", value: productionYear ?? (/* @__PURE__ */ new Date()).getFullYear() }),
    /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-3 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-2 space-y-4" }, /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-4" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink" }, __("Column Mapping")), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, __("Quick-fill:")), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: autoMap, className: "text-xs text-om-accent hover:text-om-accent underline" }, __("Auto-detect")), /* @__PURE__ */ React.createElement("span", { className: "text-om-faintest" }, "|"), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: clearAll, className: "text-xs text-om-blocked hover:text-om-blocked underline" }, __("Clear all")))), /* @__PURE__ */ React.createElement("div", { className: "space-y-3" }, headers.map((h) => {
      const m = mappings[h] || { select: "_ignore", customKey: "" };
      const sampleVal = previewRows[0]?.[h] ?? "\u2014";
      const isRequired = m.select === "order_no" || m.select === "quantity";
      return /* @__PURE__ */ React.createElement("div", { key: h, className: "flex items-start gap-3 p-3 bg-om-panel rounded-om-sm" }, /* @__PURE__ */ React.createElement("div", { className: "flex-shrink-0 w-40" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm font-mono font-medium text-om-ink truncate", title: h }, h), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint" }, __("CSV column"))), /* @__PURE__ */ React.createElement("div", { className: "flex-shrink-0 pt-2 text-om-faint" }, "\u2192"), /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-0" }, m.select !== "__custom__" && /* @__PURE__ */ React.createElement("input", { type: "hidden", name: `mapping[${h}]`, value: m.select }), /* @__PURE__ */ React.createElement(
        Dropdown,
        {
          className: "w-full text-sm",
          value: m.select == null ? "" : String(m.select),
          onChange: (v) => setField(h, "select", v),
          options: [
            { value: "_ignore", label: __("\u2014 Ignore this column \u2014") },
            ...Object.entries(systemFields).map(([key, label]) => ({
              value: String(key),
              label: `System Fields: ${label}${key === "order_no" || key === "quantity" ? " (required)" : ""}`
            })),
            { value: "__custom__", label: "Custom Field: Custom key\u2026" }
          ]
        }
      ), m.select === "__custom__" && /* @__PURE__ */ React.createElement("div", { className: "mt-2" }, /* @__PURE__ */ React.createElement(
        "input",
        {
          type: "text",
          className: "form-input w-full text-sm",
          placeholder: __("e.g. batch_code, color, weight_kg"),
          value: m.customKey,
          onChange: (e) => setField(h, "customKey", e.target.value)
        }
      ), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mt-1" }, __("Stored as"), " ", /* @__PURE__ */ React.createElement("code", { className: "text-purple-700" }, "custom:your_key"))), isRequired && /* @__PURE__ */ React.createElement("div", { className: "mt-1" }, /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-blocked font-medium" }, __("required field")))), /* @__PURE__ */ React.createElement("div", { className: "flex-shrink-0 w-32 hidden md:block" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mb-1" }, __("Sample")), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted font-mono truncate", title: sampleVal }, sampleVal)));
    }))), /* @__PURE__ */ React.createElement("div", { className: "card overflow-hidden" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink mb-3" }, "Data Preview", " ", /* @__PURE__ */ React.createElement("span", { className: "text-sm font-normal text-om-muted" }, "(first ", previewRows.length, " rows)")), /* @__PURE__ */ React.createElement("div", { className: "overflow-x-auto" }, /* @__PURE__ */ React.createElement("table", { className: "min-w-full text-xs" }, /* @__PURE__ */ React.createElement("thead", null, /* @__PURE__ */ React.createElement("tr", { className: "bg-om-chip" }, headers.map((h) => /* @__PURE__ */ React.createElement("th", { key: h, className: "px-3 py-2 text-left font-mono text-om-muted whitespace-nowrap" }, h)))), /* @__PURE__ */ React.createElement("tbody", { className: "divide-y divide-om-line2" }, previewRows.map((row, ri) => /* @__PURE__ */ React.createElement("tr", { key: ri, className: "hover:bg-om-bg" }, headers.map((h) => /* @__PURE__ */ React.createElement("td", { key: h, className: "px-3 py-2 text-om-muted whitespace-nowrap max-w-[140px] truncate", title: row[h] ?? "" }, row[h] ?? ""))))))))), /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, savedMappings.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-base font-bold text-om-ink mb-3" }, __("Load Saved Profile")), /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, savedMappings.map((m) => {
      const cols = Object.keys(m.mapping_config?.column_mappings ?? {}).length;
      return /* @__PURE__ */ React.createElement(
        "button",
        {
          key: m.id,
          type: "button",
          onClick: () => loadProfile(m.mapping_config?.column_mappings ?? {}),
          className: "w-full text-left px-3 py-2 rounded-om-sm border border-om-line2 hover:border-blue-400 hover:bg-om-chip transition-colors"
        },
        /* @__PURE__ */ React.createElement("p", { className: "text-sm font-medium text-om-ink" }, m.name, m.is_default ? " \u2713" : ""),
        /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted" }, cols, " ", cols === 1 ? "column" : "columns", " mapped")
      );
    }))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-base font-bold text-om-ink mb-3" }, __("Save Mapping Profile")), /* @__PURE__ */ React.createElement(
      Checkbox,
      {
        className: "mb-3",
        checked: saveMappingEnabled,
        onChange: (next) => setSaveMappingEnabled(next),
        label: __("Save this mapping for later")
      }
    ), saveMappingEnabled && /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "text",
        name: "save_mapping_name",
        className: "form-input w-full text-sm",
        placeholder: __("Profile name (e.g. ERP Export)"),
        maxLength: 100
      }
    )), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-base font-bold text-om-ink mb-3" }, __("Import Summary")), /* @__PURE__ */ React.createElement("div", { className: "space-y-2 text-sm" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Total rows:")), /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, totalRows)), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Strategy:")), /* @__PURE__ */ React.createElement("span", { className: "font-medium capitalize" }, importStrategy.replace(/_/g, " "))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Columns:")), /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, headers.length)), /* @__PURE__ */ React.createElement("div", { className: "border-t pt-2 flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Mapped:")), /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, mappedCount)))), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", className: "w-full" }, /* @__PURE__ */ React.createElement(Icon, { d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4", className: "w-5 h-5 inline-block mr-2" }), "Run Import (", totalRows, " rows)")))
  ));
}
CsvImportMapping.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CsvImportMapping as default
};
