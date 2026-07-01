import { useMemo, useState } from "react";
import { Head, usePage } from "@inertiajs/react";
import { Badge, Dropdown, StatusPill } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import { useDashboardShapes } from "../../lib/useDashboardShapes";
import { useHotShapes } from "../../components/LiveShapesProvider";
import OeeGauge, { oeeColor } from "../../components/OeeGauge";
import AppLayout from "../../layouts/AppLayout";
import { __ } from "../../lib/i18n";
import { formatDateTime } from "../../lib/i18n";
const WO_TERMINAL = ["DONE", "CANCELLED", "REJECTED"];
function AdminDashboard(props) {
  const hot = useHotShapes();
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Admin Dashboard") }), /* @__PURE__ */ React.createElement(DashboardBody, { hot, ...props }));
}
AdminDashboard.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function DashboardBody({
  hot,
  enabledWidgets = [],
  widgetOrder = [],
  inboundQcStats,
  materialsStats,
  scrapStats,
  nonConformanceStats
}) {
  const { workOrders, lines, issues, issueTypes, oeeRecords, isLoading, error } = useDashboardShapes(hot);
  const [selectedLineId, setSelectedLineId] = useState("");
  const scopedWorkOrders = useMemo(
    () => filterByLine(workOrders, selectedLineId),
    [workOrders, selectedLineId]
  );
  const scopedIssues = useMemo(() => {
    if (!selectedLineId) return issues;
    const lineWoIds = new Set(
      workOrders.filter((wo) => String(wo.line_id) === String(selectedLineId)).map((wo) => String(wo.id))
    );
    return issues.filter((i) => lineWoIds.has(String(i.work_order_id)));
  }, [issues, workOrders, selectedLineId]);
  const stats = useMemo(
    () => computeStats(scopedWorkOrders, scopedIssues, issueTypes, lines),
    [scopedWorkOrders, scopedIssues, issueTypes, lines]
  );
  const recentWorkOrders = useMemo(
    () => [...scopedWorkOrders].sort((a, b) => byDateDesc(a.created_at, b.created_at)).slice(0, 10),
    [scopedWorkOrders]
  );
  const recentIssues = useMemo(
    () => [...scopedIssues].sort((a, b) => byDateDesc(a.created_at, b.created_at)).slice(0, 5),
    [scopedIssues]
  );
  const showWidget = (id) => enabledWidgets.length === 0 || enabledWidgets.includes(id);
  const order = useMemo(() => buildOrder(widgetOrder), [widgetOrder]);
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(
    Header,
    {
      selectedLineId,
      onLineChange: setSelectedLineId,
      lines
    }
  ), error && /* @__PURE__ */ React.createElement("pre", { className: "bg-om-blocked-bg text-om-blocked p-3 rounded-om text-xs mb-4" }, String(error)), isLoading && /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-sm mb-4" }, __("Connecting to live sync\u2026")), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-6" }, showWidget("kpi_cards") && /* @__PURE__ */ React.createElement(Section, { order: order.kpi_cards }, /* @__PURE__ */ React.createElement(KpiCards, { stats, selectedLineId })), showWidget("oee_overview") && oeeRecords.length > 0 && /* @__PURE__ */ React.createElement(Section, { order: order.oee_overview }, /* @__PURE__ */ React.createElement(OeeOverview, { records: oeeRecords, lines })), showWidget("inbound_qc_overview") && inboundQcStats && /* @__PURE__ */ React.createElement(Section, { order: order.inbound_qc_overview }, /* @__PURE__ */ React.createElement(InboundQcOverview, { stats: inboundQcStats })), showWidget("materials_overview") && materialsStats && /* @__PURE__ */ React.createElement(Section, { order: order.materials_overview }, /* @__PURE__ */ React.createElement(MaterialsOverview, { stats: materialsStats })), showWidget("scrap_overview") && scrapStats && /* @__PURE__ */ React.createElement(Section, { order: order.scrap_overview }, /* @__PURE__ */ React.createElement(ScrapOverview, { stats: scrapStats })), showWidget("non_conformance_overview") && nonConformanceStats && /* @__PURE__ */ React.createElement(Section, { order: order.non_conformance_overview }, /* @__PURE__ */ React.createElement(NonConformanceOverview, { stats: nonConformanceStats })), showWidget("recent_work_orders") && /* @__PURE__ */ React.createElement(Section, { order: order.recent_work_orders }, /* @__PURE__ */ React.createElement(RecentWorkOrders, { rows: recentWorkOrders, lines })), showWidget("open_issues") && /* @__PURE__ */ React.createElement(Section, { order: order.open_issues }, /* @__PURE__ */ React.createElement(RecentIssues, { rows: recentIssues, issueTypes }))));
}
function Header({ selectedLineId, onLineChange, lines }) {
  const { locale } = usePage().props;
  const selectedLine = lines.find((l) => String(l.id) === String(selectedLineId));
  const dateStr = (/* @__PURE__ */ new Date()).toLocaleString(locale, {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit"
  });
  return /* @__PURE__ */ React.createElement("div", { className: "mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Admin Dashboard")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 text-sm" }, formatDateTime(/* @__PURE__ */ new Date()), " ", selectedLine ? /* @__PURE__ */ React.createElement(React.Fragment, null, "\u2014 ", /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-accent" }, selectedLine.name)) : __("\u2014 all lines"))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: selectedLineId == null ? "" : String(selectedLineId),
      onChange: (v) => onLineChange(v),
      placeholder: __("All lines"),
      className: "min-w-[180px]",
      options: [
        { value: "", label: __("All lines") },
        ...lines.slice().sort((a, b) => a.name.localeCompare(b.name)).map((line) => ({ value: String(line.id), label: line.name }))
      ]
    }
  ), selectedLineId && /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => onLineChange(""),
      className: "text-xs text-om-faint hover:text-om-ink cursor-pointer"
    },
    "\u2715 ",
    __("Clear")
  )));
}
function KpiCards({ stats, selectedLineId }) {
  const lineQs = selectedLineId ? `?line_id=${selectedLineId}` : "";
  return /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4" }, /* @__PURE__ */ React.createElement(
    KpiCard,
    {
      href: `/admin/work-orders${lineQs}`,
      label: __("Total Work Orders"),
      value: stats.total_work_orders
    }
  ), /* @__PURE__ */ React.createElement(
    KpiCard,
    {
      href: `/admin/work-orders?status=IN_PROGRESS${lineQs ? "&" + lineQs.slice(1) : ""}`,
      label: __("In Progress"),
      value: stats.in_progress,
      accent: "blue",
      hint: __("incl. accepted")
    }
  ), /* @__PURE__ */ React.createElement(
    KpiCard,
    {
      href: `/admin/work-orders?status=PENDING${lineQs ? "&" + lineQs.slice(1) : ""}`,
      label: __("Pending"),
      value: stats.pending,
      accent: "gray"
    }
  ), /* @__PURE__ */ React.createElement(
    KpiCard,
    {
      href: `/admin/work-orders?status=BLOCKED${lineQs ? "&" + lineQs.slice(1) : ""}`,
      label: __("Blocked"),
      value: stats.blocked,
      accent: "red"
    }
  ), /* @__PURE__ */ React.createElement(
    KpiCard,
    {
      label: __("Active Today"),
      value: stats.active_today,
      accent: "green",
      hint: __("started or updated")
    }
  ), /* @__PURE__ */ React.createElement(
    KpiCard,
    {
      href: "/admin/issues",
      label: __("Open Issues"),
      value: stats.open_issues,
      accent: "yellow"
    }
  ), /* @__PURE__ */ React.createElement(
    KpiCard,
    {
      href: "/admin/issues?blocking=1",
      label: __("Blocking Issues"),
      value: stats.blocking_issues,
      accent: "red"
    }
  ), /* @__PURE__ */ React.createElement(KpiCard, { href: "/admin/lines", label: __("Active Lines"), value: stats.active_lines }));
}
function KpiCard({ href, label, value, accent, hint }) {
  const accentClasses = {
    blue: "text-om-accent",
    gray: "text-om-muted",
    red: "text-om-blocked",
    green: "text-om-running",
    yellow: "text-om-downtime"
  };
  const valueClass = accentClasses[accent] ?? "text-om-ink";
  const Wrapper = href ? "a" : "div";
  return /* @__PURE__ */ React.createElement(
    Wrapper,
    {
      href,
      className: `block bg-om-card border border-om-line rounded-om p-5 transition-colors ${href ? "hover:border-om-faintest" : ""}`
    },
    /* @__PURE__ */ React.createElement("p", { className: "mb-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, label),
    /* @__PURE__ */ React.createElement("p", { className: `font-mono text-[27px] font-medium leading-none tracking-[-0.02em] ${valueClass}` }, value),
    hint && /* @__PURE__ */ React.createElement("p", { className: "text-[11px] text-om-faint mt-1.5" }, hint)
  );
}
function OeeOverview({ records, lines }) {
  const todayStr = (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
  const byLineToday = useMemo(
    () => new Map(records.filter((r) => r.record_date === todayStr).map((r) => [String(r.line_id), r])),
    [records, todayStr]
  );
  const sortedLines = useMemo(
    () => [...lines].sort((a, b) => String(a.name ?? "").localeCompare(String(b.name ?? ""))),
    [lines]
  );
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-3" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink" }, __("OEE Overview")), /* @__PURE__ */ React.createElement("a", { href: "/admin/oee", className: "text-[12.5px] text-om-accent hover:underline" }, __("Full report"), " \u2192")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3" }, sortedLines.map((line) => {
    const r = byLineToday.get(String(line.id));
    const value = r?.oee_pct != null ? Number(r.oee_pct) : null;
    return /* @__PURE__ */ React.createElement(
      "div",
      {
        key: line.id,
        className: `p-3 rounded-om-sm border flex flex-col items-center ${oeeCardClass(value)}`
      },
      /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] font-medium text-om-ink text-center mb-2 truncate w-full" }, line.name ?? __("Line :id", { id: line.id })),
      /* @__PURE__ */ React.createElement(OeeGauge, { value }),
      r ? /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 font-mono text-[10px] text-om-muted mt-2" }, /* @__PURE__ */ React.createElement("span", null, "A: ", pct(r.availability_pct)), /* @__PURE__ */ React.createElement("span", null, "P: ", r.performance_pct != null ? pct(r.performance_pct) : "-"), /* @__PURE__ */ React.createElement("span", null, "Q: ", pct(r.quality_pct))) : /* @__PURE__ */ React.createElement("div", { className: "text-xs text-om-faint mt-2" }, "\xA0")
    );
  })));
}
function oeeCardClass(value) {
  return {
    green: "border-om-line bg-om-running-bg",
    yellow: "border-om-line bg-om-downtime-bg",
    red: "border-om-line bg-om-blocked-bg",
    gray: "border-om-line bg-om-panel"
  }[oeeColor(value)];
}
function InboundQcOverview({ stats }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-3" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink" }, __("Inbound QC (30 days)")), /* @__PURE__ */ React.createElement("a", { href: "/inspections", className: "text-[12.5px] text-om-accent hover:underline" }, __("View all"), " \u2192")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 sm:grid-cols-5 gap-4 text-sm" }, /* @__PURE__ */ React.createElement(Stat, { label: __("Pending"), value: stats.pending }), /* @__PURE__ */ React.createElement(Stat, { label: __("Completed"), value: stats.completed_30d }), /* @__PURE__ */ React.createElement(Stat, { label: __("Failed"), value: stats.failed_30d, color: "red" }), /* @__PURE__ */ React.createElement(Stat, { label: __("Conditional"), value: stats.conditional_30d, color: "yellow" }), /* @__PURE__ */ React.createElement(
    Stat,
    {
      label: __("Pass rate"),
      value: stats.pass_rate_30d != null ? `${stats.pass_rate_30d}%` : "\u2014",
      color: "green"
    }
  )));
}
function MaterialsOverview({ stats }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink mb-3" }, __("Materials")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 sm:grid-cols-5 gap-4 text-sm" }, /* @__PURE__ */ React.createElement(Stat, { label: __("Low stock"), value: stats.low_stock_count, color: "red" }), /* @__PURE__ */ React.createElement(Stat, { label: __("Expiring 30d"), value: stats.expiring_count, color: "yellow" }), /* @__PURE__ */ React.createElement(Stat, { label: __("Lots released"), value: stats.lots_total }), /* @__PURE__ */ React.createElement(Stat, { label: __("Quarantined"), value: stats.quarantined_count, color: "red" }), /* @__PURE__ */ React.createElement(Stat, { label: __("Reserved qty"), value: Number(stats.reserved_total).toFixed(0) })));
}
function ScrapOverview({ stats }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-3" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink" }, __("Scrap (30 days)")), /* @__PURE__ */ React.createElement("a", { href: "/admin/scrap-reports", className: "text-[12.5px] text-om-accent hover:underline" }, __("Full report"), " \u2192")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm" }, /* @__PURE__ */ React.createElement(Stat, { label: __("Total scrap"), value: Number(stats.total_qty_30d ?? 0).toFixed(0), color: "red" }), /* @__PURE__ */ React.createElement(Stat, { label: __("Scrap entries"), value: stats.entries_30d ?? 0 }), /* @__PURE__ */ React.createElement(
    Stat,
    {
      label: __("Top reason"),
      value: stats.top_reason ? `${stats.top_reason} (${Number(stats.top_reason_qty ?? 0).toFixed(0)})` : "\u2014",
      color: "yellow"
    }
  )));
}
function NonConformanceOverview({ stats }) {
  const DISPOSITION_LABELS = {
    pending: __("Pending"),
    scrap: __("Scrap"),
    rework: __("Rework"),
    return_to_supplier: __("Return to supplier"),
    use_as_is: __("Use as is")
  };
  const byType = stats.open_by_type ?? [];
  const maxCount = Math.max(...byType.map((t) => Number(t.count ?? 0)), 1);
  const summary = stats.disposition_summary ?? {};
  const overdue = Number(stats.overdue_actions ?? 0);
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-3" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink" }, __("Non-conformances")), /* @__PURE__ */ React.createElement("a", { href: "/admin/non-conformance-reports", className: "text-[12.5px] text-om-accent hover:underline" }, __("Full report"), " \u2192")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm mb-4" }, /* @__PURE__ */ React.createElement(Stat, { label: __("Open non-conformances"), value: stats.open_total ?? 0 }), /* @__PURE__ */ React.createElement(
    Stat,
    {
      label: __("Overdue actions"),
      value: overdue,
      color: overdue > 0 ? "red" : "green"
    }
  ), /* @__PURE__ */ React.createElement(Stat, { label: __("Scrap"), value: Number(summary.scrap ?? 0), color: "yellow" })), byType.length > 0 ? /* @__PURE__ */ React.createElement("div", { className: "space-y-1.5" }, /* @__PURE__ */ React.createElement("p", { className: "text-[11.5px] uppercase tracking-[0.06em] text-om-faint mb-1" }, __("Open by type")), byType.map((t) => /* @__PURE__ */ React.createElement("div", { key: t.name, className: "flex items-center gap-3 text-[12.5px]" }, /* @__PURE__ */ React.createElement("span", { className: "w-36 shrink-0 truncate text-om-muted", title: t.name }, t.name), /* @__PURE__ */ React.createElement("div", { className: "flex-1 h-3.5 bg-om-chip rounded" }, /* @__PURE__ */ React.createElement("div", { className: "h-3.5 bg-om-blocked rounded", style: { width: `${Number(t.count) / maxCount * 100}%` } })), /* @__PURE__ */ React.createElement("span", { className: "w-8 shrink-0 text-right tabular-nums text-om-muted" }, t.count)))) : /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-faint" }, __("No open non-conformances.")));
}
function Stat({ label, value, color }) {
  const tile = {
    red: "bg-om-blocked-bg",
    yellow: "bg-om-downtime-bg",
    green: "bg-om-running-bg",
    blue: "bg-om-chip",
    purple: "bg-om-chip"
  };
  const colors = {
    red: "text-om-blocked",
    yellow: "text-om-downtime",
    green: "text-om-running",
    blue: "text-om-accent",
    purple: "text-om-accent"
  };
  return /* @__PURE__ */ React.createElement("div", { className: `p-3 rounded-om-sm border border-om-line ${tile[color] ?? "bg-om-panel"}` }, /* @__PURE__ */ React.createElement("p", { className: "mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, label), /* @__PURE__ */ React.createElement("p", { className: `font-mono text-[22px] font-medium tracking-[-0.02em] ${colors[color] ?? "text-om-ink"}` }, value));
}
function RecentWorkOrders({ rows, lines }) {
  const lineById = useMemo(() => new Map(lines.map((l) => [String(l.id), l])), [lines]);
  const columns = useMemo(
    () => [
      {
        id: "order",
        accessorKey: "order_no",
        header: __("Order"),
        cell: ({ row }) => {
          const wo = row.original;
          return /* @__PURE__ */ React.createElement(
            "a",
            {
              href: `/admin/work-orders/${wo.id}`,
              className: "font-mono text-[12px] font-medium text-om-accent hover:underline"
            },
            wo.order_no
          );
        }
      },
      {
        id: "line",
        accessorFn: (wo) => lineById.get(String(wo.line_id))?.name ?? "\u2014",
        header: __("Line"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, lineById.get(String(row.original.line_id))?.name ?? "\u2014")
      },
      {
        id: "status",
        accessorKey: "status",
        header: __("Status"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement(StatusBadge, { status: row.original.status })
      },
      {
        id: "produced_planned",
        accessorFn: (wo) => Number(wo.produced_qty),
        header: __("Produced / planned"),
        meta: { align: "right" },
        cell: ({ row }) => {
          const wo = row.original;
          return /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-muted" }, Number(wo.produced_qty).toFixed(0), " /", " ", Number(wo.planned_qty).toFixed(0));
        }
      }
    ],
    [lineById]
  );
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-3" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink" }, __("Recent work orders")), /* @__PURE__ */ React.createElement("a", { href: "/admin/work-orders", className: "text-[12.5px] text-om-accent hover:underline" }, __("View all"), " \u2192")), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: rows,
      columns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: __("No active work orders.")
    }
  ));
}
function RecentIssues({ rows, issueTypes }) {
  const typeById = useMemo(
    () => new Map(issueTypes.map((t) => [String(t.id), t])),
    [issueTypes]
  );
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-3" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink" }, __("Open issues")), /* @__PURE__ */ React.createElement("a", { href: "/admin/issues", className: "text-[12.5px] text-om-accent hover:underline" }, __("View all"), " \u2192")), rows.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-sm" }, __("No open issues."), " \u{1F389}") : /* @__PURE__ */ React.createElement("ul", { className: "divide-y divide-om-line2" }, rows.map((issue) => {
    const type = typeById.get(String(issue.issue_type_id));
    return /* @__PURE__ */ React.createElement("li", { key: issue.id, className: "py-2 flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-sm font-medium text-om-ink" }, issue.title), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint" }, type?.name ?? "\u2014", " \xB7", " ", issue.work_order_id ? __("WO #:id", { id: issue.work_order_id }) : __("no WO"))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 items-center" }, type?.is_blocking && /* @__PURE__ */ React.createElement(Badge, { variant: "danger" }, __("blocking")), /* @__PURE__ */ React.createElement(StatusBadge, { status: issue.status })));
  })));
}
const PILL_STATUS = {
  PENDING: "pending",
  ACCEPTED: "pending",
  IN_PROGRESS: "running",
  BLOCKED: "blocked",
  PAUSED: "downtime",
  OPEN: "blocked",
  ACKNOWLEDGED: "downtime",
  RESOLVED: "running",
  DONE: "done",
  CLOSED: "done"
};
function StatusBadge({ status }) {
  return /* @__PURE__ */ React.createElement(StatusPill, { status: PILL_STATUS[status] ?? "pending", label: __(status) });
}
function Section({ order, children }) {
  return /* @__PURE__ */ React.createElement("div", { style: { order: order * 10 } }, children);
}
function computeStats(workOrders, issues, issueTypes, lines) {
  const counts = workOrders.reduce(
    (acc, wo) => {
      acc.total++;
      if (wo.status === "PENDING") acc.pending++;
      if (wo.status === "ACCEPTED" || wo.status === "IN_PROGRESS") acc.in_progress++;
      if (wo.status === "BLOCKED") acc.blocked++;
      return acc;
    },
    { total: 0, pending: 0, in_progress: 0, blocked: 0 }
  );
  const today = (/* @__PURE__ */ new Date()).toISOString().slice(0, 10);
  const active_today = workOrders.filter(
    (wo) => (wo.updated_at ?? "").slice(0, 10) === today
  ).length;
  const blockingTypeIds = new Set(
    issueTypes.filter((t) => t.is_blocking).map((t) => String(t.id))
  );
  const blocking_issues = issues.filter(
    (i) => blockingTypeIds.has(String(i.issue_type_id))
  ).length;
  return {
    total_work_orders: counts.total,
    pending: counts.pending,
    in_progress: counts.in_progress,
    blocked: counts.blocked,
    active_today,
    open_issues: issues.length,
    blocking_issues,
    active_lines: lines.filter((l) => l.is_active).length
  };
}
function filterByLine(rows, lineId) {
  if (!lineId) return rows;
  return rows.filter((row) => String(row.line_id) === String(lineId));
}
function byDateDesc(a, b) {
  return (b ?? "").localeCompare(a ?? "");
}
function buildOrder(widgetOrder) {
  const defaults = [
    "kpi_cards",
    "oee_overview",
    "inbound_qc_overview",
    "materials_overview",
    "scrap_overview",
    "non_conformance_overview",
    "recent_work_orders",
    "open_issues"
  ];
  const indexOf = (id) => {
    const i = widgetOrder.indexOf(id);
    return i === -1 ? defaults.indexOf(id) + 100 : i;
  };
  return Object.fromEntries(defaults.map((id) => [id, indexOf(id)]));
}
function pct(v) {
  if (v == null) return "\u2014";
  return `${Number(v).toFixed(0)}%`;
}
export {
  AdminDashboard as default
};
