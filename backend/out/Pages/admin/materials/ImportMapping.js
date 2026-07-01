import { useState, useEffect } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import { Dropdown } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
const AUTO_MAP_RULES = {
  symbol: "external_code",
  kod: "external_code",
  code: "code",
  indeks: "external_code",
  nazwa: "name",
  name: "name",
  opis: "description",
  description: "description",
  jm: "unit_of_measure",
  "j.m.": "unit_of_measure",
  unit: "unit_of_measure",
  jednostka: "unit_of_measure",
  ean: "ean",
  "kod kreskowy": "ean",
  barcode: "ean",
  stan: "stock_quantity",
  ilosc: "stock_quantity",
  "ilo\u015B\u0107": "stock_quantity",
  stock: "stock_quantity",
  quantity: "stock_quantity",
  "stan magazynowy": "stock_quantity",
  cena: "unit_price",
  "cena netto": "unit_price",
  price: "unit_price",
  dostawca: "supplier_name",
  supplier: "supplier_name",
  kontrahent: "supplier_name",
  typ: "material_type",
  type: "material_type",
  kategoria: "material_type",
  category: "material_type",
  grupa: "material_type",
  waluta: "price_currency",
  currency: "price_currency",
  min: "min_stock_level",
  minimum: "min_stock_level",
  "min stock": "min_stock_level"
};
function Icon({ d, className = "w-5 h-5" }) {
  return /* @__PURE__ */ React.createElement("svg", { className, fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d }));
}
function autoDetect(headers) {
  const result = {};
  for (const h of headers) {
    const norm = h.toLowerCase().trim();
    for (const [pattern, target] of Object.entries(AUTO_MAP_RULES)) {
      if (norm === pattern || norm.includes(pattern)) {
        result[h] = target;
        break;
      }
    }
    if (!result[h]) result[h] = "_ignore";
  }
  return result;
}
function MaterialsImportMapping() {
  const {
    headers = [],
    previewRows = [],
    totalRows = 0,
    path = "",
    systemFields = {},
    importStrategy = "update_or_create",
    externalSystem = "",
    csrf_token: csrfToken
  } = usePage().props;
  const [mappings, setMappings] = useState(() => autoDetect(headers));
  useEffect(() => {
    setMappings(autoDetect(headers));
  }, [headers.join(",")]);
  const setMapping = (header, value) => {
    setMappings((prev) => ({ ...prev, [header]: value }));
  };
  const handleAutoMap = () => setMappings(autoDetect(headers));
  const handleClearAll = () => {
    const cleared = {};
    for (const h of headers) cleared[h] = "_ignore";
    setMappings(cleared);
  };
  const strategyDescription = {
    update_or_create: "Create & Update \u2014 new materials will be created, existing ones updated with new data.",
    create_only: "Create Only \u2014 existing materials will be skipped.",
    skip_existing: "Update Only \u2014 only existing materials will be updated, new ones skipped."
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "Map Material Columns" }), /* @__PURE__ */ React.createElement("nav", { className: "flex items-center gap-2 text-sm text-om-muted mb-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:text-om-ink" }, "Dashboard"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/materials", className: "hover:text-om-ink" }, "Materials"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/materials/import", className: "hover:text-om-ink" }, "Import"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-ink font-medium" }, "Map Columns")), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, "Map Columns"), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, "Assign each column to a material field.", " ", /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-accent" }, totalRows, " rows"), " to import", externalSystem && /* @__PURE__ */ React.createElement(React.Fragment, null, " \xB7 Source: ", /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, externalSystem)))), /* @__PURE__ */ React.createElement(Link, { href: "/admin/materials/import", className: "btn-touch btn-secondary text-sm" }, "Back")), /* @__PURE__ */ React.createElement("form", { method: "POST", action: "/admin/materials/import/process" }, /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "_token", value: csrfToken }), /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "file_path", value: path }), /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "import_strategy", value: importStrategy }), /* @__PURE__ */ React.createElement("input", { type: "hidden", name: "external_system", value: externalSystem }), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-3 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-2 space-y-4" }, /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-4" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink" }, "Column Mapping"), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: handleAutoMap,
      className: "text-xs text-om-accent hover:text-om-accent underline"
    },
    "Auto-detect"
  ), /* @__PURE__ */ React.createElement("span", { className: "text-om-faintest" }, "|"), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: handleClearAll,
      className: "text-xs text-om-blocked hover:text-om-blocked underline"
    },
    "Clear all"
  ))), /* @__PURE__ */ React.createElement("div", { className: "space-y-3" }, headers.map((h) => {
    const sampleVal = previewRows[0]?.[h] ?? "";
    return /* @__PURE__ */ React.createElement("div", { key: h, className: "flex items-center gap-3 p-3 bg-om-panel rounded-om-sm" }, /* @__PURE__ */ React.createElement("div", { className: "w-1/3 min-w-0" }, /* @__PURE__ */ React.createElement(
      "span",
      {
        className: "text-sm font-mono font-medium text-om-ink truncate block",
        title: h
      },
      h
    ), sampleVal && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint truncate block" }, "e.g. ", sampleVal.length > 40 ? sampleVal.slice(0, 40) + "\u2026" : sampleVal)), /* @__PURE__ */ React.createElement(
      Icon,
      {
        d: "M14 5l7 7m0 0l-7 7m7-7H3",
        className: "w-5 h-5 text-om-faint shrink-0"
      }
    ), /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "hidden",
        name: `mapping[${h}]`,
        value: mappings[h] ?? "_ignore"
      }
    ), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        className: "w-full",
        options: Object.entries(systemFields).map(([val, label]) => ({ value: String(val), label })),
        value: mappings[h] == null ? "_ignore" : String(mappings[h]),
        onChange: (v) => setMapping(h, v)
      }
    )));
  }))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-semibold text-om-ink mb-3" }, "Data Preview (first ", previewRows.length, " rows)"), /* @__PURE__ */ React.createElement("div", { className: "overflow-x-auto" }, /* @__PURE__ */ React.createElement("table", { className: "min-w-full text-sm" }, /* @__PURE__ */ React.createElement("thead", null, /* @__PURE__ */ React.createElement("tr", null, headers.map((h) => /* @__PURE__ */ React.createElement(
    "th",
    {
      key: h,
      className: "px-3 py-2 text-left text-xs font-medium text-om-muted uppercase bg-om-panel whitespace-nowrap"
    },
    h
  )))), /* @__PURE__ */ React.createElement("tbody", { className: "divide-y divide-om-line2" }, previewRows.map((row, ri) => /* @__PURE__ */ React.createElement("tr", { key: ri }, headers.map((h) => /* @__PURE__ */ React.createElement(
    "td",
    {
      key: h,
      className: "px-3 py-2 whitespace-nowrap text-om-muted max-w-[200px] truncate"
    },
    row[h] ?? ""
  ))))))))), /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-1 space-y-4" }, /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-sm font-semibold text-om-ink mb-3" }, "Required Fields"), /* @__PURE__ */ React.createElement("ul", { className: "text-sm text-om-muted space-y-1" }, /* @__PURE__ */ React.createElement("li", null, /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*"), " ", /* @__PURE__ */ React.createElement("strong", null, "Name"), " \u2014 material name"), /* @__PURE__ */ React.createElement("li", null, /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*"), " ", /* @__PURE__ */ React.createElement("strong", null, "Code"), " or ", /* @__PURE__ */ React.createElement("strong", null, "External Code"), " \u2014 for identification"))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-sm font-semibold text-om-ink mb-3" }, "Strategy"), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, strategyDescription[importStrategy] ?? importStrategy)), /* @__PURE__ */ React.createElement("div", { className: "sticky top-4" }, /* @__PURE__ */ React.createElement("button", { type: "submit", className: "btn-touch btn-primary w-full text-center" }, /* @__PURE__ */ React.createElement(Icon, { d: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12", className: "w-5 h-5 inline-block mr-2" }), "Import ", totalRows, " Materials"))))));
}
MaterialsImportMapping.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  MaterialsImportMapping as default
};
