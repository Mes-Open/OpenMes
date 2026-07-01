import { useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import { Dropdown } from "@openmes/ui";
import AppLayout from "../../layouts/AppLayout";
import { __ } from "../../lib/i18n";
function Icon({ d, className = "w-5 h-5" }) {
  return /* @__PURE__ */ React.createElement("svg", { className, fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d }));
}
function CsvImport() {
  const {
    recentImports = [],
    savedMappings = [],
    systemFields = {},
    lines = [],
    productionPeriod = "none",
    import_result: importResult = null,
    csrf_token: csrfToken
  } = usePage().props;
  const [dragging, setDragging] = useState(false);
  const [filename, setFilename] = useState("");
  const [fileInput, setFileInput] = useState(null);
  const [importStrategy, setImportStrategy] = useState("update_or_create");
  const [mappingId, setMappingId] = useState("");
  const [targetLineId, setTargetLineId] = useState("");
  const handleDrop = (e) => {
    e.preventDefault();
    setDragging(false);
    if (e.dataTransfer.files.length > 0 && fileInput) {
      try {
        fileInput.files = e.dataTransfer.files;
      } catch (_) {
      }
      setFilename(e.dataTransfer.files[0]?.name || "");
    }
  };
  const statusBadge = (status) => {
    if (status === "COMPLETED") return "bg-om-running-bg text-om-running";
    if (status === "FAILED") return "bg-om-blocked-bg text-om-blocked";
    return "bg-om-downtime-bg text-om-downtime";
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("CSV Import") }), /* @__PURE__ */ React.createElement("nav", { className: "flex items-center gap-2 text-sm text-om-muted mb-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:text-om-ink" }, __("Dashboard")), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-ink font-medium" }, __("CSV Import"))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Import")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, __("Import work orders from a CSV, XLS or XLSX file with custom column mapping")))), importResult && /* @__PURE__ */ React.createElement("div", { className: `card mb-6 border-l-4 ${importResult.failed === 0 ? "border-om-running" : "border-yellow-500"}` }, /* @__PURE__ */ React.createElement("div", { className: "flex items-start gap-4" }, /* @__PURE__ */ React.createElement("div", { className: `${importResult.failed === 0 ? "bg-om-running-bg" : "bg-om-downtime-bg"} rounded-full p-3 flex-shrink-0` }, importResult.failed === 0 ? /* @__PURE__ */ React.createElement(Icon, { d: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z", className: "w-6 h-6 text-om-running" }) : /* @__PURE__ */ React.createElement(Icon, { d: "M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z", className: "w-6 h-6 text-om-downtime" })), /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement("p", { className: "font-bold text-om-ink mb-1" }, importResult.failed === 0 ? __("Import Completed") : __("Import Completed with errors")), /* @__PURE__ */ React.createElement("div", { className: "flex gap-6 text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-running font-medium" }, "\u2713 ", importResult.success, " imported"), importResult.failed > 0 && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked font-medium" }, "\u2717 ", importResult.failed, " failed"), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, importResult.total, " total rows")), importResult.errors && importResult.errors.length > 0 && /* @__PURE__ */ React.createElement("details", { className: "mt-3" }, /* @__PURE__ */ React.createElement("summary", { className: "text-sm text-om-blocked cursor-pointer" }, "Show errors (", importResult.errors.length, ")"), /* @__PURE__ */ React.createElement("ul", { className: "mt-2 text-xs text-om-blocked space-y-1 bg-om-blocked-bg rounded p-3" }, importResult.errors.map((err, i) => /* @__PURE__ */ React.createElement("li", { key: i }, err))))))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-3 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-2 card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink mb-4" }, __("Upload File")), /* @__PURE__ */ React.createElement(
    "form",
    {
      method: "POST",
      action: "/admin/csv-import/upload",
      encType: "multipart/form-data"
    },
    /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrfToken }),
    /* @__PURE__ */ React.createElement(
      "div",
      {
        className: `border-2 border-dashed rounded-om p-8 text-center transition-colors mb-6 cursor-pointer
                                ${dragging ? "border-om-accent bg-om-chip" : "border-om-line hover:border-om-faintest"}`,
        onDragOver: (e) => {
          e.preventDefault();
          setDragging(true);
        },
        onDragLeave: (e) => {
          e.preventDefault();
          setDragging(false);
        },
        onDrop: handleDrop,
        onClick: () => fileInput && fileInput.click()
      },
      /* @__PURE__ */ React.createElement(
        Icon,
        {
          d: "M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12",
          className: "mx-auto h-12 w-12 text-om-faint mb-3"
        }
      ),
      /* @__PURE__ */ React.createElement("p", { className: "text-om-muted font-medium" }, __("Drop file here or"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, __("browse"))),
      /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint mt-1" }, "Max 32 MB \xB7 .csv, .txt, .xlsx, .xls"),
      /* @__PURE__ */ React.createElement("div", { className: "mt-3 flex items-center justify-center gap-3 text-xs" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, __("Sample files:")), /* @__PURE__ */ React.createElement(
        "a",
        {
          href: "/samples/zlecenia-import.xlsx",
          download: true,
          className: "inline-flex items-center gap-1 text-om-accent hover:text-om-accent font-medium hover:underline",
          onClick: (e) => e.stopPropagation()
        },
        /* @__PURE__ */ React.createElement(Icon, { d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4", className: "w-3.5 h-3.5" }),
        "XLSX"
      ), /* @__PURE__ */ React.createElement(
        "a",
        {
          href: "/samples/zlecenia-import.csv",
          download: true,
          className: "inline-flex items-center gap-1 text-om-accent hover:text-om-accent font-medium hover:underline",
          onClick: (e) => e.stopPropagation()
        },
        /* @__PURE__ */ React.createElement(Icon, { d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4", className: "w-3.5 h-3.5" }),
        "CSV"
      )),
      /* @__PURE__ */ React.createElement(
        "input",
        {
          type: "file",
          name: "csv_file",
          ref: setFileInput,
          accept: ".csv,.txt,.xlsx,.xls",
          className: "hidden",
          onChange: (e) => setFilename(e.target.files[0]?.name || ""),
          required: true
        }
      ),
      filename && /* @__PURE__ */ React.createElement("p", { className: "mt-2 text-sm text-om-accent font-medium" }, filename)
    ),
    /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 gap-4 mb-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Duplicate Strategy")), /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "import_strategy", value: importStrategy }), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        options: [
          { value: "update_or_create", label: __("Update if exists, create if new") },
          { value: "skip_existing", label: __("Skip existing records") },
          { value: "error_on_duplicate", label: __("Error on duplicates") }
        ],
        value: importStrategy,
        onChange: (v) => setImportStrategy(v),
        className: "w-full"
      }
    )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Load Mapping Profile (optional)")), /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "mapping_id", value: mappingId }), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        options: [
          { value: "", label: __("\u2014 Map columns manually \u2014") },
          ...savedMappings.map((m) => ({
            value: String(m.id),
            label: `${m.name}${m.is_default ? " (default)" : ""}`
          }))
        ],
        value: mappingId == null ? "" : String(mappingId),
        onChange: (v) => setMappingId(v),
        className: "w-full"
      }
    ))),
    /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Assign all rows to Production Line (optional)")), /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "target_line_id", value: targetLineId }), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        options: [
          { value: "", label: __("\u2014 Use line_code column from file \u2014") },
          ...lines.map((line) => ({ value: String(line.id), label: line.name }))
        ],
        value: targetLineId == null ? "" : String(targetLineId),
        onChange: (v) => setTargetLineId(v),
        className: "w-full"
      }
    ), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mt-1" }, __("If selected, every imported work order will be assigned to this line, overriding any line_code column in the file."))),
    productionPeriod !== "none" && /* @__PURE__ */ React.createElement("div", { className: "mb-4 p-3 bg-om-chip border border-om-line rounded-om-sm" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs font-semibold text-om-accent uppercase tracking-wide mb-2" }, __("Planning Period"), /* @__PURE__ */ React.createElement("span", { className: "font-normal normal-case" }, " ", "\u2014 ", __("system is configured for :period production split", { period: __(productionPeriod.charAt(0).toUpperCase() + productionPeriod.slice(1)) }))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-3" }, productionPeriod === "weekly" ? /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "form-label text-xs" }, __("Week Number (1\u201353)")), /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "number",
        name: "import_week",
        min: "1",
        max: "53",
        className: "form-input w-full",
        placeholder: __("e.g. current week")
      }
    )) : /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "form-label text-xs" }, __("Month Number (1\u201312)")), /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "number",
        name: "import_month",
        min: "1",
        max: "12",
        className: "form-input w-full",
        placeholder: __("e.g. current month")
      }
    )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "form-label text-xs" }, __("Year")), /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "number",
        name: "production_year",
        min: "2000",
        max: "2100",
        className: "form-input w-full",
        defaultValue: (/* @__PURE__ */ new Date()).getFullYear()
      }
    )))),
    /* @__PURE__ */ React.createElement("button", { type: "submit", className: "btn-touch btn-primary w-full" }, /* @__PURE__ */ React.createElement(Icon, { d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12", className: "w-5 h-5 inline-block mr-2" }), __("Upload & Configure Mapping"))
  ), /* @__PURE__ */ React.createElement("details", { className: "mt-6" }, /* @__PURE__ */ React.createElement("summary", { className: "text-sm font-medium text-om-muted cursor-pointer hover:text-om-ink" }, __("Available system fields reference")), /* @__PURE__ */ React.createElement("div", { className: "mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2" }, Object.entries(systemFields).map(([key, label]) => /* @__PURE__ */ React.createElement("div", { key, className: "flex items-center gap-2 text-xs bg-om-panel rounded p-2" }, /* @__PURE__ */ React.createElement("code", { className: "text-om-accent font-mono shrink-0" }, key), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, label), (key === "order_no" || key === "quantity") && /* @__PURE__ */ React.createElement("span", { className: "ml-auto text-om-blocked font-bold shrink-0" }, __("required")))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 text-xs bg-om-chip rounded p-2 sm:col-span-2" }, /* @__PURE__ */ React.createElement("code", { className: "text-purple-700 font-mono shrink-0" }, "custom:field_name"), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Any extra field \u2014 stored as JSON on the work order")))))), /* @__PURE__ */ React.createElement("div", { className: "space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink mb-3" }, __("Saved Mapping Profiles")), savedMappings.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, __("No saved profiles yet. Profiles are saved during import.")) : savedMappings.map((m) => {
    const colCount = Object.keys(m.mapping_config?.column_mappings ?? {}).length;
    return /* @__PURE__ */ React.createElement("div", { key: m.id, className: "flex items-center justify-between py-2 border-b border-om-line2 last:border-0" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-sm font-medium text-om-ink" }, m.name), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted" }, colCount, " column", colCount !== 1 ? "s" : "", " mapped")), !m.is_default && /* @__PURE__ */ React.createElement(
      "form",
      {
        method: "POST",
        action: `/admin/csv-import/mappings/${m.id}`,
        onSubmit: (e) => !window.confirm(__("Delete mapping profile?")) && e.preventDefault()
      },
      /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrfToken }),
      /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_method", value: "DELETE" }),
      /* @__PURE__ */ React.createElement("button", { type: "submit", className: "text-red-400 hover:text-om-blocked p-1" }, /* @__PURE__ */ React.createElement(Icon, { d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16", className: "w-4 h-4" }))
    ));
  })), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink mb-3" }, __("Recent Imports")), recentImports.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, __("No imports yet.")) : recentImports.map((imp) => /* @__PURE__ */ React.createElement("div", { key: imp.id, className: "py-2 border-b border-om-line2 last:border-0" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-1" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted truncate max-w-[140px]", title: imp.filename }, imp.filename), /* @__PURE__ */ React.createElement("span", { className: `text-xs px-2 py-0.5 rounded-full font-medium ${statusBadge(imp.status)}` }, __(imp.status))), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-running" }, "\u2713 ", imp.successful_rows), " /", " ", imp.total_rows, " rows", imp.failed_rows > 0 && /* @__PURE__ */ React.createElement(React.Fragment, null, " \xB7 ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "\u2717 ", imp.failed_rows)), imp.created_at_human && /* @__PURE__ */ React.createElement(React.Fragment, null, " \xB7 ", imp.created_at_human)), imp.error_log && imp.error_log.length > 0 && /* @__PURE__ */ React.createElement("details", { className: "mt-1" }, /* @__PURE__ */ React.createElement("summary", { className: "text-xs text-om-blocked cursor-pointer hover:text-om-blocked" }, "Show errors (", imp.error_log.length, ")"), /* @__PURE__ */ React.createElement("ul", { className: "mt-1 space-y-0.5 bg-om-blocked-bg rounded p-2 max-h-40 overflow-y-auto" }, imp.error_log.map((err, i) => /* @__PURE__ */ React.createElement("li", { key: i, className: "text-xs text-om-blocked font-mono break-all" }, err))))))))));
}
CsvImport.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CsvImport as default
};
