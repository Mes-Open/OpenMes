import { Head, Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import { Dropdown } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
function Icon({ d, className = "w-5 h-5" }) {
  return /* @__PURE__ */ React.createElement("svg", { className, fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d }));
}
function MaterialsImport() {
  const {
    import_result: importResult = null,
    flash = {},
    csrf_token: csrfToken
  } = usePage().props;
  const [importStrategy, setImportStrategy] = useState("update_or_create");
  const [externalSystem, setExternalSystem] = useState("");
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-5xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "Import Materials" }), /* @__PURE__ */ React.createElement("nav", { className: "flex items-center gap-2 text-sm text-om-muted mb-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:text-om-ink" }, "Dashboard"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/materials", className: "hover:text-om-ink" }, "Materials"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-ink font-medium" }, "Import")), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, "Import Materials"), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, "Import materials from CSV, XLS or XLSX file (e.g. Subiekt GT export)")), /* @__PURE__ */ React.createElement(Link, { href: "/admin/materials", className: "btn-touch btn-secondary" }, "Back to Materials")), importResult && /* @__PURE__ */ React.createElement("div", { className: `card mb-6 border-l-4 ${!importResult.errors?.length ? "border-om-running" : "border-yellow-500"}` }, /* @__PURE__ */ React.createElement("div", { className: "flex items-start gap-4" }, /* @__PURE__ */ React.createElement("div", { className: `${!importResult.errors?.length ? "bg-om-running-bg" : "bg-om-downtime-bg"} rounded-full p-3 flex-shrink-0` }, !importResult.errors?.length ? /* @__PURE__ */ React.createElement(Icon, { d: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z", className: "w-6 h-6 text-om-running" }) : /* @__PURE__ */ React.createElement(Icon, { d: "M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z", className: "w-6 h-6 text-om-downtime" })), /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement("p", { className: "font-bold text-om-ink mb-1" }, "Import ", !importResult.errors?.length ? "Completed" : "Completed with errors"), /* @__PURE__ */ React.createElement("div", { className: "flex gap-6 text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-running font-medium" }, importResult.created, " created"), /* @__PURE__ */ React.createElement("span", { className: "text-om-accent font-medium" }, importResult.updated, " updated"), importResult.skipped > 0 && /* @__PURE__ */ React.createElement("span", { className: "text-om-muted font-medium" }, importResult.skipped, " skipped"), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, importResult.total, " total rows")), importResult.errors && importResult.errors.length > 0 && /* @__PURE__ */ React.createElement("details", { className: "mt-3" }, /* @__PURE__ */ React.createElement("summary", { className: "text-sm text-om-blocked cursor-pointer" }, "Show errors (", importResult.errors.length, ")"), /* @__PURE__ */ React.createElement("ul", { className: "mt-2 text-xs text-om-blocked space-y-1 bg-om-blocked-bg rounded p-3" }, importResult.errors.map((err, i) => /* @__PURE__ */ React.createElement("li", { key: i }, err))))))), flash.error && /* @__PURE__ */ React.createElement("div", { className: "card mb-6 border-l-4 border-om-blocked" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-blocked font-medium" }, flash.error)), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-3 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-2" }, /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-semibold text-om-ink mb-4" }, "Upload File"), /* @__PURE__ */ React.createElement(
    "form",
    {
      method: "POST",
      action: "/admin/materials/import/upload",
      encType: "multipart/form-data",
      className: "space-y-4"
    },
    /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrfToken }),
    /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "File (CSV, XLS, XLSX)"), /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "file",
        name: "import_file",
        accept: ".csv,.xls,.xlsx,.txt",
        required: true,
        className: "w-full rounded-om-sm border border-om-line bg-om-card px-3 py-2 text-om-ink file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-om-chip file:text-om-accent hover:file:bg-om-chip"
      }
    )),
    /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Import Strategy"), /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "import_strategy", value: importStrategy }), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        options: [
          { value: "update_or_create", label: "Create new & update existing" },
          { value: "create_only", label: "Create new only (skip existing)" },
          { value: "skip_existing", label: "Update existing only (skip new)" }
        ],
        value: importStrategy,
        onChange: (v) => setImportStrategy(v),
        className: "w-full"
      }
    )),
    /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Source System ", /* @__PURE__ */ React.createElement("span", { className: "text-om-faint font-normal" }, "(optional)")), /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "external_system", value: externalSystem }), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        options: [
          { value: "", label: "-- None --" },
          { value: "subiekt_gt", label: "Subiekt GT" },
          { value: "subiekt_nexo", label: "Subiekt nexo" },
          { value: "optima", label: "Comarch Optima" },
          { value: "wf_mag", label: "WF-Mag" },
          { value: "enova", label: "Enova365" },
          { value: "sap", label: "SAP" },
          { value: "custom", label: "Other (custom)" }
        ],
        value: externalSystem,
        onChange: (v) => setExternalSystem(v),
        placeholder: "-- None --",
        className: "w-full"
      }
    )),
    /* @__PURE__ */ React.createElement("div", { className: "pt-2" }, /* @__PURE__ */ React.createElement("button", { type: "submit", className: "btn-touch btn-primary w-full sm:w-auto" }, /* @__PURE__ */ React.createElement(Icon, { d: "M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12", className: "w-5 h-5 inline-block mr-2" }), "Upload & Map Columns"))
  ))), /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-1 space-y-4" }, /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-sm font-semibold text-om-ink mb-3" }, "Supported Formats"), /* @__PURE__ */ React.createElement("ul", { className: "text-sm text-om-muted space-y-2" }, /* @__PURE__ */ React.createElement("li", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "w-2 h-2 bg-om-running rounded-full" }), "CSV (comma or semicolon separated)"), /* @__PURE__ */ React.createElement("li", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "w-2 h-2 bg-om-running rounded-full" }), "XLS (Excel 97-2003)"), /* @__PURE__ */ React.createElement("li", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "w-2 h-2 bg-om-running rounded-full" }), "XLSX (Excel 2007+)"))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-sm font-semibold text-om-ink mb-3" }, "Subiekt GT Export"), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-2" }, "To export materials from Subiekt GT:"), /* @__PURE__ */ React.createElement("ol", { className: "text-sm text-om-muted space-y-1 list-decimal list-inside" }, /* @__PURE__ */ React.createElement("li", null, "Go to Towary > Lista towarow"), /* @__PURE__ */ React.createElement("li", null, "Select all or filter"), /* @__PURE__ */ React.createElement("li", null, "Click Export > Excel/CSV"), /* @__PURE__ */ React.createElement("li", null, "Include columns: Symbol, Nazwa, JM, Cena, EAN, Stan"))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-sm font-semibold text-om-ink mb-3" }, "Matching Logic"), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, "Existing materials are matched by:"), /* @__PURE__ */ React.createElement("ol", { className: "text-sm text-om-muted space-y-1 list-decimal list-inside mt-1" }, /* @__PURE__ */ React.createElement("li", null, "External Code + Source System"), /* @__PURE__ */ React.createElement("li", null, "EAN / Barcode"), /* @__PURE__ */ React.createElement("li", null, "Internal Code"))))));
}
MaterialsImport.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaterialsImport as default
};
