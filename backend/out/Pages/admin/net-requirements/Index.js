import { Head, router, usePage } from "@inertiajs/react";
import { DatePicker, Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
const num = (v) => Number(v ?? 0);
const fmt = (v) => num(v).toLocaleString(void 0, { maximumFractionDigits: 4 });
function NetRequirementsIndex() {
  const {
    lines = [],
    lineId,
    dateFrom,
    dateTo,
    requirements = [],
    shortages = [],
    totals = { work_orders: 0, components: 0, shortage_components: 0, total_shortfall: 0 }
  } = usePage().props;
  const apply = (changes) => router.get("/admin/net-requirements", { line_id: lineId ?? "", date_from: dateFrom, date_to: dateTo, ...changes }, { preserveState: false });
  const reqColumns = [
    { id: "code", accessorKey: "code", header: __("Code"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, row.original.code ?? "\u2014") },
    { id: "name", accessorKey: "name", header: __("Component"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, row.original.name) },
    { id: "required_qty", accessorFn: (r) => num(r.required_qty), header: __("Required Qty"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, fmt(row.original.required_qty)), meta: { align: "right" } },
    { id: "available_qty", accessorFn: (r) => num(r.available_qty), header: __("Available Qty"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, fmt(row.original.available_qty)), meta: { align: "right" } },
    { id: "net_qty", accessorFn: (r) => num(r.net_qty), header: __("Net"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: `tabular-nums font-medium ${row.original.is_short ? "text-om-blocked" : "text-om-running"}` }, fmt(row.original.net_qty)), meta: { align: "right" } },
    { id: "uom", accessorKey: "unit_of_measure", header: __("Unit"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, row.original.unit_of_measure ?? "\u2014") },
    {
      id: "status",
      accessorFn: (r) => r.is_short ? "short" : "ok",
      header: __("Status"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: `text-xs px-2 py-0.5 rounded-full font-medium ${row.original.is_short ? "bg-om-blocked-bg text-om-blocked" : "bg-om-running-bg text-om-running"}` }, row.original.is_short ? __("Short") : __("OK"))
    }
  ];
  const shortageColumns = [
    { id: "code", accessorKey: "code", header: __("Code"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, row.original.code ?? "\u2014") },
    { id: "name", accessorKey: "name", header: __("Component"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, row.original.name) },
    { id: "required_qty", accessorFn: (r) => num(r.required_qty), header: __("Required Qty"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, fmt(row.original.required_qty)), meta: { align: "right" } },
    { id: "available_qty", accessorFn: (r) => num(r.available_qty), header: __("Available Qty"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, fmt(row.original.available_qty)), meta: { align: "right" } },
    { id: "net_qty", accessorFn: (r) => num(r.net_qty), header: __("Shortfall"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums font-medium text-om-blocked" }, fmt(row.original.net_qty)), meta: { align: "right" } },
    {
      id: "related",
      accessorFn: (r) => (r.related_work_orders ?? []).join(" "),
      header: __("Driving work orders"),
      enableSorting: false,
      cell: ({ row }) => /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-1" }, (row.original.related_work_orders ?? []).map((wo) => /* @__PURE__ */ React.createElement("span", { key: wo, className: "font-mono text-[11px] bg-om-chip text-om-muted px-2 py-0.5 rounded-[5px]" }, wo)))
    }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Net requirements") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Net requirements")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 text-sm" }, __("Component requirements exploded from planned work orders, netted against on-hand stock, with a shortage list."))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om-sm p-4 flex flex-wrap items-end gap-4" }, /* @__PURE__ */ React.createElement(Filter, { label: __("Line") }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      className: "min-w-[160px]",
      options: [{ value: "", label: __("All Lines") }, ...lines.map((l) => ({ value: String(l.id), label: l.name }))],
      value: lineId == null ? "" : String(lineId),
      onChange: (v) => apply({ line_id: v })
    }
  )), /* @__PURE__ */ React.createElement(Filter, { label: __("From") }, /* @__PURE__ */ React.createElement(DatePicker, { value: dateFrom || null, onChange: (iso) => apply({ date_from: iso ?? "" }), className: "w-44" })), /* @__PURE__ */ React.createElement(Filter, { label: __("To") }, /* @__PURE__ */ React.createElement(DatePicker, { value: dateTo || null, onChange: (iso) => apply({ date_to: iso ?? "" }), className: "w-44" }))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 lg:grid-cols-4 gap-4" }, /* @__PURE__ */ React.createElement(Kpi, { label: __("Planned work orders"), value: totals.work_orders }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Components required"), value: totals.components }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Components short"), value: totals.shortage_components, tone: totals.shortage_components > 0 ? "blocked" : null }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Total shortfall"), value: fmt(totals.total_shortfall), tone: num(totals.total_shortfall) > 0 ? "blocked" : null })), /* @__PURE__ */ React.createElement(Card, { title: __("Shortages") }, shortages.length === 0 ? /* @__PURE__ */ React.createElement(Empty, null, __("No shortages \u2014 on-hand stock covers the planned work orders.")) : /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: shortages,
      columns: shortageColumns,
      searchable: true,
      paginated: true,
      searchPlaceholder: __("Search components\u2026"),
      emptyLabel: __("No shortages.")
    }
  )), /* @__PURE__ */ React.createElement(Card, { title: __("Net requirements by component") }, requirements.length === 0 ? /* @__PURE__ */ React.createElement(Empty, null, __("No planned work orders in this period.")) : /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: requirements,
      columns: reqColumns,
      searchable: true,
      columnToggle: true,
      paginated: true,
      searchPlaceholder: __("Search components\u2026"),
      emptyLabel: __("No requirements.")
    }
  ))));
}
NetRequirementsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function Filter({ label, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, label), children);
}
function Kpi({ label, value, tone }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om-sm p-5" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, label), /* @__PURE__ */ React.createElement("p", { className: `text-2xl font-bold truncate ${tone === "blocked" ? "text-om-blocked" : "text-om-ink"}`, title: String(value) }, value));
}
function Card({ title, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om-sm p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-semibold text-om-ink mb-4" }, title), children);
}
function Empty({ children }) {
  return /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-center py-8" }, children);
}
export {
  NetRequirementsIndex as default
};
