import { Head, router, usePage } from "@inertiajs/react";
import { useMemo } from "react";
import { Dropdown, StatusPill } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../layouts/AppLayout";
import { __ } from "../../lib/i18n";
const ISSUE_PILL_STATUS = {
  OPEN: "blocked",
  ACKNOWLEDGED: "downtime",
  RESOLVED: "running",
  CLOSED: "done"
};
function SupervisorDashboard() {
  const { lines = [], selectedLineId, stats = {}, throughput = {}, issueStats = {}, recentIssues = [] } = usePage().props;
  const changeLine = (id) => router.get("/supervisor/dashboard", id ? { line_id: id } : {}, { preserveState: false });
  const issueColumns = useMemo(() => [
    {
      id: "title",
      accessorKey: "title",
      header: __("Issue"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, row.original.title)
    },
    {
      id: "type",
      accessorFn: (r) => r.type ?? "\u2014",
      header: __("Type"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.type ?? "\u2014")
    },
    {
      id: "work_order",
      accessorFn: (r) => r.work_order ?? "\u2014",
      header: __("WO"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[12px] text-om-muted" }, row.original.work_order ?? "\u2014")
    },
    {
      id: "reported_at",
      accessorFn: (r) => r.reported_at ?? "\u2014",
      header: __("Reported"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, row.original.reported_at ?? "\u2014")
    },
    {
      id: "status",
      accessorKey: "status",
      header: __("Status"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement(StatusPill, { status: ISSUE_PILL_STATUS[row.original.status] ?? "pending", label: __(row.original.status) })
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Supervisor Dashboard") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Supervisor Dashboard")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: lines.map((l) => ({ value: String(l.id), label: l.name })),
      value: selectedLineId == null ? "" : String(selectedLineId),
      onChange: (v) => changeLine(v),
      className: "min-w-[180px]"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4" }, /* @__PURE__ */ React.createElement(Kpi, { label: __("Total WOs"), value: stats.total_work_orders }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Active"), value: stats.active_work_orders, accent: "blue" }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Completed"), value: stats.completed_work_orders, accent: "green" }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Blocked"), value: stats.blocked_work_orders, accent: "red" }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Open Issues"), value: stats.open_issues, accent: "yellow" }), /* @__PURE__ */ React.createElement(Kpi, { label: __("Blocking"), value: stats.blocking_issues, accent: "red" })), /* @__PURE__ */ React.createElement("div", { className: "grid lg:grid-cols-2 gap-6" }, /* @__PURE__ */ React.createElement(Card, { title: __("Throughput (30 days) \xB7 avg :avg", { avg: throughput.average ?? 0 }) }, /* @__PURE__ */ React.createElement(BarList, { labels: throughput.labels, values: throughput.values, unit: "" })), /* @__PURE__ */ React.createElement(Card, { title: __("Issues by type (30 days)") }, /* @__PURE__ */ React.createElement(BarList, { labels: issueStats.by_type?.labels, values: issueStats.by_type?.values, unit: "", color: "bg-om-downtime" }))), /* @__PURE__ */ React.createElement(Card, { title: __("Recent issues") }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: recentIssues,
      columns: issueColumns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: `${__("No issues.")} \u{1F389}`
    }
  ))));
}
SupervisorDashboard.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function Kpi({ label, value, accent }) {
  const colors = { blue: "text-om-accent", green: "text-om-running", red: "text-om-blocked", yellow: "text-om-downtime" };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("p", { className: "mb-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, label), /* @__PURE__ */ React.createElement("p", { className: `font-mono text-[27px] font-medium leading-none tracking-[-0.02em] ${colors[accent] ?? "text-om-ink"}` }, value ?? 0));
}
function Card({ title, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink mb-3" }, title), children);
}
function BarList({ labels = [], values = [], unit = "", color = "bg-om-accent" }) {
  if (!labels?.length) return /* @__PURE__ */ React.createElement("p", { className: "text-om-faint text-sm" }, __("No data."));
  const max = Math.max(...values, 1);
  return /* @__PURE__ */ React.createElement("div", { className: "space-y-[15px]" }, labels.map((label, i) => /* @__PURE__ */ React.createElement("div", { key: i, className: "flex items-center gap-2 text-xs" }, /* @__PURE__ */ React.createElement("span", { className: "w-20 shrink-0 text-[12.5px] text-om-muted truncate text-right" }, label), /* @__PURE__ */ React.createElement("div", { className: "flex-1 bg-om-chip rounded-[20px] h-[7px] overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: `${color} h-[7px] rounded-[20px]`, style: { width: `${values[i] / max * 100}%` } })), /* @__PURE__ */ React.createElement("span", { className: "w-12 shrink-0 font-mono text-[12px] text-om-muted" }, values[i], unit))));
}
export {
  SupervisorDashboard as default
};
