import { Head, Link, usePage } from "@inertiajs/react";
import { StatusPill } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../layouts/AppLayout";
import { formatNumber, __ } from "../../lib/i18n";
function ProgressBar({ pct, done }) {
  const color = done ? "bg-om-running" : pct >= 50 ? "bg-om-downtime" : "bg-om-accent";
  return /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1 bg-om-line rounded-full h-1.5" }, /* @__PURE__ */ React.createElement("div", { className: `h-1.5 rounded-full ${color}`, style: { width: `${pct}%` } })), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[11px] text-om-faint w-8 text-right" }, pct, "%"));
}
function StatusBadge({ item }) {
  if (item.done) {
    return /* @__PURE__ */ React.createElement(StatusPill, { status: "done", label: __("Packed") });
  }
  if (item.status === "DONE") {
    return /* @__PURE__ */ React.createElement(StatusPill, { status: "running", label: __("In Progress") });
  }
  return /* @__PURE__ */ React.createElement(StatusPill, { status: "pending", label: item.status });
}
const itemColumns = [
  {
    id: "order_no",
    accessorKey: "order_no",
    header: __("Order"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono font-semibold text-om-ink" }, row.original.order_no)
  },
  {
    id: "product",
    accessorKey: "product",
    header: __("Product"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, row.original.product)
  },
  {
    id: "line",
    accessorFn: (r) => r.line ?? "\u2014",
    header: __("Line"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.line ?? "\u2014")
  },
  {
    id: "eans",
    accessorFn: (r) => (r.eans ?? []).join(" "),
    header: __("EAN"),
    enableSorting: false,
    cell: ({ row }) => (row.original.eans ?? []).map((ean) => /* @__PURE__ */ React.createElement("span", { key: ean, className: "inline-block font-mono text-[11px] bg-om-chip text-om-muted px-2 py-0.5 rounded-[5px] mr-1" }, ean))
  },
  {
    id: "packed_qty",
    accessorKey: "packed_qty",
    header: __("Packed"),
    meta: { align: "right" },
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono font-semibold text-om-ink" }, row.original.packed_qty)
  },
  {
    id: "planned_qty",
    accessorKey: "planned_qty",
    header: __("Plan"),
    meta: { align: "right" },
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, row.original.planned_qty)
  },
  {
    id: "progress",
    accessorKey: "progress",
    header: __("Progress"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement(ProgressBar, { pct: row.original.progress, done: row.original.done })
  },
  {
    id: "status",
    accessorFn: (r) => r.done ? __("Packed") : r.status === "DONE" ? __("In Progress") : r.status,
    header: __("Status"),
    cell: ({ row }) => /* @__PURE__ */ React.createElement(StatusBadge, { item: row.original })
  }
];
function Admin() {
  const { items = [], stats = {} } = usePage().props;
  const totalPacked = stats.total_packed ?? 0;
  const plan = stats.plan ?? 0;
  const realizacja = plan > 0 ? Math.min(100, Math.round(totalPacked / plan * 100)) : 0;
  const now = /* @__PURE__ */ new Date();
  const hour = now.getHours();
  const shiftLabel = hour >= 6 && hour < 18 ? "06:00 \u2013 18:00" : "18:00 \u2013 06:00";
  const realizacjaColor = realizacja >= 100 ? "text-om-running" : realizacja >= 50 ? "text-om-downtime" : "text-om-blocked";
  const realizacjaBar = realizacja >= 100 ? "bg-om-running" : realizacja >= 50 ? "bg-om-downtime" : "bg-om-blocked";
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Packaging \u2014 Overview") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("nav", { className: "flex items-center gap-1 text-[13px] text-om-muted mb-4" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:text-om-ink hover:underline" }, __("Dashboard")), /* @__PURE__ */ React.createElement("span", { className: "mx-1" }, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, __("Packaging"))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-2xl font-semibold tracking-[-0.02em] text-om-ink" }, __("Packaging \u2014 Overview")), /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted mt-1" }, __("Current shift: :shift", { shift: shiftLabel }))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/packaging/station",
      className: "inline-flex items-center justify-center rounded-om-sm bg-om-ink px-4 py-2.5 text-[13px] font-semibold text-om-on-ink hover:bg-om-ink-hover transition-colors"
    },
    __("Open station")
  ), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/packaging/eans",
      className: "inline-flex items-center justify-center rounded-om-sm bg-om-chip px-4 py-2.5 text-[13px] font-semibold text-om-ink hover:bg-om-line2 transition-colors"
    },
    __("Manage EANs")
  ))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-4 text-center" }, /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] text-om-ink" }, formatNumber(stats.today_packed ?? 0)), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2" }, __("Packed (shift)"))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-4 text-center" }, /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] text-om-muted" }, formatNumber(plan)), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2" }, __("Total plan"))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-4 text-center" }, /* @__PURE__ */ React.createElement("p", { className: `font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] ${(stats.backlog ?? 0) > 0 ? "text-om-blocked" : "text-om-running"}` }, formatNumber(stats.backlog ?? 0)), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2" }, __("Backlog"))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-4 text-center" }, /* @__PURE__ */ React.createElement("p", { className: `font-mono text-[40px] leading-none font-semibold tracking-[-0.02em] ${realizacjaColor}` }, realizacja, "%"), /* @__PURE__ */ React.createElement("p", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mt-2" }, __("Completion")), /* @__PURE__ */ React.createElement("div", { className: "w-full bg-om-line rounded-full h-1.5 mt-2" }, /* @__PURE__ */ React.createElement("div", { className: `h-1.5 rounded-full ${realizacjaBar}`, style: { width: `${realizacja}%` } })))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-b border-om-line" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[15px] font-semibold tracking-[-0.01em] text-om-ink" }, __("Work orders to pack"), " (", items.length, ")")), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: items,
      columns: itemColumns,
      searchable: true,
      columnToggle: true,
      paginated: true,
      searchPlaceholder: __("Search orders\u2026"),
      emptyLabel: __("No work orders with assigned EAN codes")
    }
  ))));
}
Admin.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  Admin as default
};
