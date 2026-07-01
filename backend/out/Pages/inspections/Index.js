import { useMemo } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Dropdown, StatusPill } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../layouts/AppLayout";
import { formatNumber, __ } from "../../lib/i18n";
const DISPOSITION_LABELS = {
  pending: __("Pending"),
  accept: "Accept",
  accept_with_deviation: "Accept with deviation",
  rework: "Rework",
  quarantine: "Quarantine",
  scrap: "Scrap",
  reject: "Reject",
  return_to_supplier: "Return to supplier"
};
const DISPOSITION_OPTIONS = [
  "pending",
  "accept",
  "accept_with_deviation",
  "rework",
  "quarantine",
  "scrap",
  "reject",
  "return_to_supplier"
];
function statusPill(status) {
  const map = {
    pass: "running",
    conditional_pass: "downtime",
    fail: "blocked",
    pending: "pending"
  };
  return map[status] ?? "pending";
}
function dispositionPill(disposition) {
  const map = {
    accept: "running",
    accept_with_deviation: "running",
    rework: "downtime",
    quarantine: "pending",
    scrap: "blocked",
    reject: "blocked",
    return_to_supplier: "downtime",
    pending: "pending"
  };
  return map[disposition] ?? "pending";
}
const TH_CLASS = "px-3 py-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint";
function fmtNum(n) {
  if (n == null) return "\u2014";
  return formatNumber(Number(n), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function InspectionsIndex() {
  const { inspections = [], tab = "pending", stats = {}, selectedDisposition = "" } = usePage().props;
  const tabs = [
    { key: "pending", label: __("Pending") },
    { key: "recent", label: __("Recent") },
    { key: "failed", label: __("Failed") }
  ];
  const tabHref = (key) => {
    const params = new URLSearchParams({ tab: key });
    if (selectedDisposition) params.set("disposition", selectedDisposition);
    return `/inspections?${params.toString()}`;
  };
  const dispHref = (d) => {
    const params = new URLSearchParams({ tab });
    if (d) params.set("disposition", d);
    return `/inspections?${params.toString()}`;
  };
  const columns = useMemo(() => [
    {
      id: "started",
      accessorFn: (r) => r.started_at_formatted ?? "",
      header: __("Started"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[12px] text-om-muted" }, row.original.started_at_formatted ?? "\u2014")
    },
    {
      id: "material",
      accessorFn: (r) => r.material?.name ?? "",
      header: __("Material"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, row.original.material?.name ?? "\u2014")
    },
    {
      id: "lot",
      accessorKey: "lot_number",
      header: __("Lot"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-ink" }, row.original.lot_number)
    },
    {
      id: "qty",
      accessorKey: "quantity_received",
      header: __("Qty"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-ink" }, row.original.quantity_received != null ? fmtNum(row.original.quantity_received) : "\u2014")
    },
    {
      id: "inspector",
      accessorFn: (r) => r.inspector?.name ?? "",
      header: __("Inspector"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.inspector?.name ?? "\u2014")
    },
    {
      id: "status",
      accessorKey: "status",
      header: __("Status"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(
        StatusPill,
        {
          status: statusPill(row.original.status),
          pulse: false,
          label: (row.original.status ?? "").replace(/_/g, " ")
        }
      ), row.original.issue_id && /* @__PURE__ */ React.createElement("span", { className: "block font-mono text-[11px] text-om-blocked mt-1" }, "NC #", row.original.issue_id))
    },
    {
      id: "disposition",
      accessorKey: "disposition",
      header: __("Disposition"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement(
        StatusPill,
        {
          status: dispositionPill(row.original.disposition ?? "pending"),
          pulse: false,
          label: (row.original.disposition ?? "pending").replace(/_/g, " ")
        }
      )
    },
    {
      id: "actions",
      header: __("Actions"),
      enableSorting: false,
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement(
        Link,
        {
          href: `/inspections/${row.original.id}`,
          className: "text-om-accent hover:underline"
        },
        row.original.status === "pending" ? __("Perform") : __("Open")
      )
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Inbound Inspections") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Inbound Inspections")), /* @__PURE__ */ React.createElement("p", { className: "text-[13px] text-om-muted mt-1" }, __("Receive material lots and verify them against an inspection plan."))), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/inspections/create",
      className: "inline-flex items-center justify-center gap-2 rounded-om-sm bg-om-accent px-4 py-2.5 text-[13px] font-semibold text-white transition-colors hover:brightness-95"
    },
    "+ Start inspection"
  )), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-4 text-center" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Pending")), /* @__PURE__ */ React.createElement("div", { className: `mt-1 font-mono text-2xl font-semibold ${(stats.pending ?? 0) > 0 ? "text-om-downtime" : "text-om-faint"}` }, stats.pending ?? 0)), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-4 text-center" }, /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Failed (30d)")), /* @__PURE__ */ React.createElement("div", { className: `mt-1 font-mono text-2xl font-semibold ${(stats.recent_fail ?? 0) > 0 ? "text-om-blocked" : "text-om-running"}` }, stats.recent_fail ?? 0))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-1 mb-3 border-b border-om-line" }, tabs.map(({ key, label }) => /* @__PURE__ */ React.createElement(
    "a",
    {
      key,
      href: tabHref(key),
      className: `px-4 py-2 text-[13px] font-medium border-b-2 -mb-px transition-colors ${tab === key ? "border-om-accent text-om-ink" : "border-transparent text-om-muted hover:text-om-ink"}`
    },
    label
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 mb-3" }, /* @__PURE__ */ React.createElement("label", { htmlFor: "disposition", className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Disposition:")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: selectedDisposition == null ? "" : String(selectedDisposition),
      onChange: (v) => router.visit(dispHref(v), { preserveScroll: true }),
      options: [
        { value: "", label: __("All") },
        ...DISPOSITION_OPTIONS.map((d) => ({ value: String(d), label: DISPOSITION_LABELS[d] ?? d }))
      ],
      className: "w-48"
    }
  ), selectedDisposition && /* @__PURE__ */ React.createElement("a", { href: `/inspections?tab=${tab}`, className: "text-[11.5px] text-om-muted hover:text-om-ink" }, __("Clear"))), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: inspections,
      columns,
      searchable: true,
      columnToggle: true,
      paginated: true,
      searchPlaceholder: "Search inspections\u2026",
      emptyLabel: __("No inspections in this tab.")
    }
  )));
}
InspectionsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  InspectionsIndex as default
};
