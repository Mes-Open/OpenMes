import { Head, router, usePage } from "@inertiajs/react";
import { DatePicker } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
const DISPOSITION_LABELS = {
  pending: "Pending",
  scrap: "Scrap",
  rework: "Rework",
  return_to_supplier: "Return to supplier",
  use_as_is: "Use as is"
};
const DISPOSITION_BAR = {
  pending: "bg-om-downtime",
  scrap: "bg-om-blocked",
  rework: "bg-om-accent",
  return_to_supplier: "bg-om-accent",
  use_as_is: "bg-om-running"
};
const num = (v) => Number(v ?? 0);
const fmt = (v) => num(v).toLocaleString(void 0, { maximumFractionDigits: 2 });
function NonConformanceReportIndex() {
  const {
    dateFrom,
    dateTo,
    pareto = { total_count: 0, total_nc_qty: 0, types: [] },
    dispositionSummary = {},
    overdueActions = 0
  } = usePage().props;
  const types = pareto.types ?? [];
  const topType = types[0] ?? null;
  const maxCount = Math.max(...types.map((t) => num(t.count)), 1);
  const dispositionTotal = Object.values(dispositionSummary).reduce((a, b) => a + num(b), 0);
  const apply = (changes) => router.get("/admin/non-conformance-reports", { date_from: dateFrom, date_to: dateTo, ...changes }, { preserveState: false });
  const paretoColumns = [
    { id: "name", accessorKey: "name", header: __("Issue type"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, row.original.name) },
    { id: "count", accessorFn: (r) => num(r.count), header: __("Count"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, row.original.count), meta: { align: "right" } },
    { id: "nc_qty", accessorFn: (r) => num(r.nc_qty), header: __("Non-conforming quantity"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, fmt(row.original.nc_qty)), meta: { align: "right" } },
    { id: "pct", accessorFn: (r) => num(r.pct), header: __("% of Total"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, num(row.original.pct).toFixed(1), "%"), meta: { align: "right" } },
    { id: "cumulative_pct", accessorFn: (r) => num(r.cumulative_pct), header: __("Cumulative %"), cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "tabular-nums" }, num(row.original.cumulative_pct).toFixed(1), "%"), meta: { align: "right" } }
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Non-conformance report") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Non-conformance report")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 text-sm" }, __("Which issue types drive the most non-conformances (Pareto), and how they are dispositioned."))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om-sm p-4 flex flex-wrap items-end gap-4" }, /* @__PURE__ */ React.createElement(Filter, { label: __("From") }, /* @__PURE__ */ React.createElement(DatePicker, { value: dateFrom || null, onChange: (iso) => apply({ date_from: iso ?? "" }), className: "w-44" })), /* @__PURE__ */ React.createElement(Filter, { label: __("To") }, /* @__PURE__ */ React.createElement(DatePicker, { value: dateTo || null, onChange: (iso) => apply({ date_to: iso ?? "" }), className: "w-44" }))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 lg:grid-cols-4 gap-4" }, /* @__PURE__ */ React.createElement(Kpi, { label: __("Total non-conformances"), value: pareto.total_count }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Non-conforming quantity"), value: fmt(pareto.total_nc_qty) }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Distinct types"), value: types.length }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Overdue actions"), value: overdueActions, tone: overdueActions > 0 ? "blocked" : null })), /* @__PURE__ */ React.createElement(Card, { title: __("Non-conformance Pareto by type") }, types.length === 0 ? /* @__PURE__ */ React.createElement(Empty, null, __("No non-conformances in this period.")) : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "space-y-2 mb-6" }, types.map((t) => /* @__PURE__ */ React.createElement("div", { key: t.issue_type_id ?? t.name, className: "flex items-center gap-3 text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "w-44 shrink-0 truncate text-om-muted", title: t.name }, t.name), /* @__PURE__ */ React.createElement("div", { className: "flex-1 h-5 bg-om-chip rounded" }, /* @__PURE__ */ React.createElement("div", { className: "h-5 bg-om-blocked rounded", style: { width: `${num(t.count) / maxCount * 100}%` } })), /* @__PURE__ */ React.createElement("span", { className: "w-28 shrink-0 text-right tabular-nums text-om-muted" }, t.count, " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "(", num(t.pct).toFixed(1), "%)"))))), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: types,
      columns: paretoColumns,
      searchable: true,
      columnToggle: true,
      paginated: true,
      searchPlaceholder: __("Search types\u2026"),
      emptyLabel: __("No non-conformances in this period.")
    }
  ))), /* @__PURE__ */ React.createElement(Card, { title: __("Disposition summary") }, dispositionTotal === 0 ? /* @__PURE__ */ React.createElement(Empty, null, __("No non-conformances in this period.")) : /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, Object.keys(DISPOSITION_LABELS).map((key) => {
    const v = num(dispositionSummary[key]);
    const pct = dispositionTotal > 0 ? v / dispositionTotal * 100 : 0;
    return /* @__PURE__ */ React.createElement("div", { key, className: "flex items-center gap-3 text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "w-44 shrink-0 truncate text-om-muted" }, __(DISPOSITION_LABELS[key])), /* @__PURE__ */ React.createElement("div", { className: "flex-1 h-5 bg-om-chip rounded" }, /* @__PURE__ */ React.createElement("div", { className: `h-5 rounded ${DISPOSITION_BAR[key]}`, style: { width: `${pct}%` } })), /* @__PURE__ */ React.createElement("span", { className: "w-28 shrink-0 text-right tabular-nums text-om-muted" }, v, " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "(", pct.toFixed(1), "%)")));
  })))));
}
NonConformanceReportIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function Filter({ label, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, label), children);
}
function Kpi({ label, value, sub, tone }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om-sm p-5" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, label), /* @__PURE__ */ React.createElement("p", { className: `text-2xl font-bold truncate ${tone === "blocked" ? "text-om-blocked" : "text-om-ink"}`, title: String(value) }, value), sub && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-0.5" }, sub));
}
function Card({ title, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om-sm p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-semibold text-om-ink mb-4" }, title), children);
}
function Empty({ children }) {
  return /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-center py-8" }, children);
}
export {
  NonConformanceReportIndex as default
};
