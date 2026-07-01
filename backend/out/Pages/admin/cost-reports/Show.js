import { Head, Link, usePage } from "@inertiajs/react";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { __, formatNumber, formatDateTime } from "../../../lib/i18n";
import CostMethodology from "./CostMethodology";
const PAY_TYPE_LABELS = {
  hourly: "Hourly",
  weekly: "Weekly",
  piece_rate: "Piece rate"
};
function money(value, currency) {
  if (value == null) return "\u2014";
  return `${formatNumber(value)} ${currency}`;
}
const MATERIAL_COLUMNS = [
  {
    id: "material",
    accessorFn: (r) => r.material_name,
    header: __("Material"),
    cell: ({ row }) => {
      const it = row.original;
      return /* @__PURE__ */ React.createElement(React.Fragment, null, it.material_name ?? "\u2014", it.material_code && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint font-mono ml-1" }, it.material_code));
    }
  },
  {
    id: "source",
    accessorFn: (r) => r.source,
    header: __("Source"),
    cell: ({ row }) => {
      const it = row.original;
      return /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded-full text-xs font-medium ${it.source === "actual" ? "bg-om-running-bg text-om-running" : "bg-om-chip text-om-accent"}` }, it.source === "actual" ? __("Actual consumption") : __("BOM estimate"));
    }
  },
  {
    id: "qty",
    accessorFn: (r) => r.qty,
    header: __("Qty"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, formatNumber(row.original.qty)),
    meta: { align: "right" }
  },
  {
    id: "unit_price",
    accessorFn: (r) => r.unit_price,
    header: __("Unit price"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, money(row.original.unit_price, row.original.currency)),
    meta: { align: "right" }
  },
  {
    id: "line_total",
    accessorFn: (r) => r.line_total,
    header: __("Line total"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, money(row.original.line_total, row.original.currency)),
    meta: { align: "right" }
  }
];
const LABOR_COLUMNS = [
  {
    id: "worker",
    accessorFn: (r) => r.worker_name,
    header: __("Worker"),
    cell: ({ row }) => {
      const it = row.original;
      return /* @__PURE__ */ React.createElement(React.Fragment, null, it.worker_name ?? "\u2014", it.worker_code && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint font-mono ml-1" }, it.worker_code));
    }
  },
  {
    id: "pay_type",
    accessorFn: (r) => __(PAY_TYPE_LABELS[r.pay_type] ?? r.pay_type),
    header: __("Pay type"),
    cell: ({ row }) => __(PAY_TYPE_LABELS[row.original.pay_type] ?? row.original.pay_type)
  },
  {
    id: "basis",
    accessorFn: (r) => r.basis,
    header: __("Basis"),
    cell: ({ row }) => {
      const it = row.original;
      return /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, formatNumber(it.basis), " ", it.basis_unit === "pcs" ? __("Pieces") : __("Hours"));
    },
    meta: { align: "right" }
  },
  {
    id: "rate",
    accessorFn: (r) => r.rate,
    header: __("Rate"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, money(row.original.rate, row.original.currency)),
    meta: { align: "right" }
  },
  {
    id: "line_total",
    accessorFn: (r) => r.line_total,
    header: __("Line total"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, money(row.original.line_total, row.original.currency)),
    meta: { align: "right" }
  }
];
const ADDITIONAL_COLUMNS = [
  {
    id: "description",
    accessorFn: (r) => r.description,
    header: __("Description"),
    cell: ({ row }) => row.original.description ?? "\u2014"
  },
  {
    id: "line_total",
    accessorFn: (r) => r.line_total,
    header: __("Line total"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, money(row.original.line_total, row.original.currency)),
    meta: { align: "right" }
  }
];
function CostReportShow() {
  const { breakdown, meta = {} } = usePage().props;
  const b = breakdown;
  const currency = b.currency;
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `${__("Production Cost")} \xB7 ${b.order_no}` }), /* @__PURE__ */ React.createElement("div", { className: "max-w-5xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(Link, { href: "/admin/cost-reports", className: "text-sm text-om-accent hover:underline" }, "\u2190 ", __("Production Cost Report")), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mt-1" }, b.order_no), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, meta.product_name ?? "\u2014", " \xB7 ", meta.line_name ?? "\u2014", meta.completed_at && /* @__PURE__ */ React.createElement(React.Fragment, null, " \xB7 ", formatDateTime(meta.completed_at)))), b.mixed_currency && /* @__PURE__ */ React.createElement("div", { className: "rounded-om-sm bg-om-downtime-bg border border-om-line text-om-downtime text-sm px-4 py-2" }, __("Mixed currencies - totals are summed without conversion.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 md:grid-cols-4 gap-3" }, /* @__PURE__ */ React.createElement(Card, { label: __("Total cost"), value: money(b.total_cost, currency), strong: true }), /* @__PURE__ */ React.createElement(Card, { label: __("Cost per unit"), value: b.cost_per_unit == null ? "\u2014" : money(b.cost_per_unit, currency) }), /* @__PURE__ */ React.createElement(Card, { label: __("Produced"), value: formatNumber(b.produced_qty) }), /* @__PURE__ */ React.createElement(
    Card,
    {
      label: `${__("Materials")} / ${__("Labor")}`,
      value: `${money(b.materials.total, currency)} / ${money(b.labor.total, currency)}`
    }
  )), /* @__PURE__ */ React.createElement(CostMethodology, null), /* @__PURE__ */ React.createElement(Section, { title: __("Materials"), total: money(b.materials.total, currency) }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: b.materials.items,
      columns: MATERIAL_COLUMNS,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: __("No cost data for this work order.")
    }
  )), /* @__PURE__ */ React.createElement(Section, { title: __("Labor"), total: money(b.labor.total, currency) }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: b.labor.items,
      columns: LABOR_COLUMNS,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: __("No cost data for this work order.")
    }
  )), /* @__PURE__ */ React.createElement(Section, { title: __("Additional costs"), total: money(b.additional.total, currency) }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: b.additional.items,
      columns: ADDITIONAL_COLUMNS,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: __("No cost data for this work order.")
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "bg-om-ink text-om-on-ink rounded-om-sm p-4 flex items-center justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-lg font-medium" }, __("Total cost")), /* @__PURE__ */ React.createElement("span", { className: "text-2xl font-bold font-mono" }, money(b.total_cost, currency)))));
}
function Section({ title, total, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm overflow-x-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between px-4 py-3 border-b border-om-line2" }, /* @__PURE__ */ React.createElement("h2", { className: "font-semibold text-om-ink" }, title), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, total)), children);
}
function Card({ label, value, strong }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-4" }, /* @__PURE__ */ React.createElement("div", { className: "text-xs text-om-muted uppercase" }, label), /* @__PURE__ */ React.createElement("div", { className: `mt-1 ${strong ? "text-2xl font-bold" : "text-lg font-semibold"} text-om-ink` }, value));
}
CostReportShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CostReportShow as default
};
