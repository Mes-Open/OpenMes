import { Head, router, usePage } from "@inertiajs/react";
import { DatePicker, Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
const categoryLabels = () => ({
  material: __("Material"),
  machine: __("Machine"),
  method: __("Method"),
  man: __("Man"),
  environment: __("Environment"),
  unknown: __("Unknown")
});
const num = (v) => Number(v ?? 0);
const fmt = (v) => num(v).toLocaleString(void 0, { maximumFractionDigits: 2 });
function ScrapReportsIndex() {
  const {
    lines = [],
    lineId,
    dateFrom,
    dateTo,
    pareto = { total_qty: 0, total_entries: 0, reasons: [] },
    ratePerLine = []
  } = usePage().props;
  const categories = categoryLabels();
  const reasons = pareto.reasons ?? [];
  const topReason = reasons[0] ?? null;
  const maxQty = Math.max(...reasons.map((r) => num(r.qty)), 1);
  const apply = (changes) => router.get("/admin/scrap-reports", { line_id: lineId ?? "", date_from: dateFrom, date_to: dateTo, ...changes }, { preserveState: false });
  const paretoColumns = [
    { id: "code", accessorKey: "code", header: __("Code"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, row.original.code) },
    { id: "name", accessorKey: "name", header: __("Reason"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, row.original.name) },
    { id: "category", accessorFn: (r) => categories[r.category] ?? r.category, header: __("Category"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, categories[row.original.category] ?? row.original.category) },
    { id: "qty", accessorFn: (r) => num(r.qty), header: __("Quantity"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, fmt(row.original.qty)), meta: { align: "right" } },
    { id: "pct", accessorFn: (r) => num(r.pct), header: __("% of Total"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, num(row.original.pct).toFixed(1), "%"), meta: { align: "right" } },
    { id: "cumulative_pct", accessorFn: (r) => num(r.cumulative_pct), header: __("Cumulative %"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, num(row.original.cumulative_pct).toFixed(1), "%"), meta: { align: "right" } }
  ];
  const rateColumns = [
    { id: "line_name", accessorKey: "line_name", header: __("Line"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, row.original.line_name) },
    { id: "scrap_qty", accessorFn: (r) => num(r.scrap_qty), header: __("Scrap"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, fmt(row.original.scrap_qty)), meta: { align: "right" } },
    { id: "produced_qty", accessorFn: (r) => num(r.produced_qty), header: __("Produced"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, fmt(row.original.produced_qty)), meta: { align: "right" } },
    { id: "scrap_rate_pct", accessorFn: (r) => num(r.scrap_rate_pct), header: __("Scrap rate"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums font-medium" }, row.original.scrap_rate_pct != null ? num(row.original.scrap_rate_pct).toFixed(2) + "%" : "\u2014"), meta: { align: "right" } }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Scrap Reports") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Scrap Reports")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 text-sm" }, __("Which reasons cause the most scrap (Pareto), and scrap rate per line."))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om-sm p-4 flex flex-wrap items-end gap-4" }, /* @__PURE__ */ React.createElement(Filter, { label: __("Line") }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      className: "min-w-[160px]",
      options: [{ value: "", label: __("All Lines") }, ...lines.map((l) => ({ value: String(l.id), label: l.name }))],
      value: lineId == null ? "" : String(lineId),
      onChange: (v) => apply({ line_id: v })
    }
  )), /* @__PURE__ */ React.createElement(Filter, { label: __("From") }, /* @__PURE__ */ React.createElement(DatePicker, { value: dateFrom || null, onChange: (iso) => apply({ date_from: iso ?? "" }), className: "w-44" })), /* @__PURE__ */ React.createElement(Filter, { label: __("To") }, /* @__PURE__ */ React.createElement(DatePicker, { value: dateTo || null, onChange: (iso) => apply({ date_to: iso ?? "" }), className: "w-44" }))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 lg:grid-cols-4 gap-4" }, /* @__PURE__ */ React.createElement(Kpi, { label: __("Total scrap quantity"), value: fmt(pareto.total_qty) }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Scrap entries"), value: fmt(pareto.total_entries) }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Distinct reasons"), value: reasons.length }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Top reason"), value: topReason?.name ?? "\u2014", sub: topReason ? __(":pct% of total", { pct: num(topReason.pct).toFixed(1) }) : null })), /* @__PURE__ */ React.createElement(Card, { title: __("Scrap Pareto by reason") }, reasons.length === 0 ? /* @__PURE__ */ React.createElement(Empty, null, __("No scrap reported in this period.")) : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "space-y-2 mb-6" }, reasons.map((r) => /* @__PURE__ */ React.createElement("div", { key: r.scrap_reason_id, className: "flex items-center gap-3 text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "w-44 shrink-0 truncate text-om-muted", title: r.name }, r.name), /* @__PURE__ */ React.createElement("div", { className: "flex-1 h-5 bg-om-chip rounded" }, /* @__PURE__ */ React.createElement("div", { className: "h-5 bg-om-blocked rounded", style: { width: `${num(r.qty) / maxQty * 100}%` } })), /* @__PURE__ */ React.createElement("span", { className: "w-28 shrink-0 text-right tabular-nums text-om-muted" }, fmt(r.qty), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "(", num(r.pct).toFixed(1), "%)"))))), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: reasons,
      columns: paretoColumns,
      searchable: true,
      columnToggle: true,
      paginated: true,
      searchPlaceholder: __("Search reasons\u2026"),
      emptyLabel: __("No scrap reported in this period.")
    }
  ))), /* @__PURE__ */ React.createElement(Card, { title: __("Scrap rate per line") }, ratePerLine.length === 0 ? /* @__PURE__ */ React.createElement(Empty, null, __("No data.")) : /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: ratePerLine,
      columns: rateColumns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: __("No data.")
    }
  ))));
}
ScrapReportsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function Filter({ label, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, label), children);
}
function Kpi({ label, value, sub }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om-sm p-5" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, label), /* @__PURE__ */ React.createElement("p", { className: "text-2xl font-bold text-om-ink truncate", title: String(value) }, value), sub && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-0.5" }, sub));
}
function Card({ title, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om-sm p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-semibold text-om-ink mb-4" }, title), children);
}
function Empty({ children }) {
  return /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-center py-8" }, children);
}
export {
  ScrapReportsIndex as default
};
