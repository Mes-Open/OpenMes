import { Head, Link } from "@inertiajs/react";
import { useMemo } from "react";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
const STATUS_COLORS = {
  received: "bg-om-chip text-om-accent",
  quarantine: "bg-om-downtime-bg text-om-downtime",
  released: "bg-om-running-bg text-om-running",
  consumed: "bg-om-chip text-om-muted",
  expired: "bg-om-blocked-bg text-om-blocked",
  rejected: "bg-om-blocked-bg text-om-blocked"
};
function ucFirst(str) {
  if (!str) return "\u2014";
  return str.charAt(0).toUpperCase() + str.slice(1);
}
function trimQty(val) {
  if (val == null) return "\u2014";
  return parseFloat(Number(val).toFixed(4)).toString();
}
function fmtDate(str) {
  if (!str) return null;
  return str.substring(0, 10);
}
function fmtDateTime(str) {
  if (!str) return "\u2014";
  return str.substring(0, 16).replace("T", " ");
}
function isExpired(dateStr) {
  if (!dateStr) return false;
  return new Date(dateStr) < /* @__PURE__ */ new Date();
}
function MaterialLotShow({ lot }) {
  const statusColor = STATUS_COLORS[lot.status] ?? "bg-om-chip text-om-muted";
  const totalConsumed = (lot.consumptions ?? []).reduce((sum, c) => sum + Number(c.quantity_consumed ?? 0), 0);
  const expiryPast = lot.expiry_date && isExpired(lot.expiry_date);
  const sourceBatchId = lot.extra_data?.source_batch_id;
  const sublotColumns = useMemo(() => [
    {
      id: "sublot",
      accessorKey: "sublot_number",
      header: "Sublot",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.sublot_number)
    },
    {
      id: "quantity",
      accessorFn: (r) => Number(r.quantity ?? 0),
      header: "Quantity",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, trimQty(row.original.quantity), " ", /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, row.original.unit_of_measure))
    },
    {
      id: "status",
      accessorKey: "status",
      header: "Status",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 rounded text-xs bg-om-chip" }, ucFirst(row.original.status))
    },
    {
      id: "notes",
      accessorKey: "notes",
      header: "Notes",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.notes ?? "\u2014")
    }
  ], []);
  const consumptionColumns = useMemo(() => [
    {
      id: "when",
      accessorFn: (r) => r.consumed_at,
      header: "When",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, fmtDateTime(row.original.consumed_at))
    },
    {
      id: "work_order",
      accessorFn: (r) => {
        const wo = r.batch_step?.batch?.work_order;
        return wo ? wo.lot_number ?? `#${wo.id}` : "\u2014";
      },
      header: "Work order",
      cell: ({ row }) => {
        const wo = row.original.batch_step?.batch?.work_order;
        return /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs" }, wo ? wo.lot_number ?? `#${wo.id}` : "\u2014");
      }
    },
    {
      id: "batch",
      accessorFn: (r) => {
        const batch = r.batch_step?.batch;
        return batch ? batch.lot_number ?? `#${batch.id}` : "\u2014";
      },
      header: "Batch",
      cell: ({ row }) => {
        const batch = row.original.batch_step?.batch;
        return /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs" }, batch ? batch.lot_number ?? `#${batch.id}` : "\u2014");
      }
    },
    {
      id: "step",
      accessorFn: (r) => r.batch_step?.name ?? "\u2014",
      header: "Step",
      cell: ({ row }) => row.original.batch_step?.name ?? "\u2014"
    },
    {
      id: "quantity",
      accessorFn: (r) => Number(r.quantity_consumed ?? 0),
      header: "Quantity",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, trimQty(row.original.quantity_consumed), " ", /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, lot.unit_of_measure))
    },
    {
      id: "by",
      accessorFn: (r) => r.recorded_by?.name ?? "\u2014",
      header: "By",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.recorded_by?.name ?? "\u2014")
    }
  ], [lot.unit_of_measure]);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `Material Lot \u2014 ${lot.lot_number}` }), /* @__PURE__ */ React.createElement("nav", { className: "text-sm text-om-muted mb-4 flex items-center gap-1" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:underline" }, "Dashboard"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/material-lots", className: "hover:underline" }, "Material Lots"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, lot.lot_number)), /* @__PURE__ */ React.createElement("div", { className: "max-w-5xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink font-mono" }, lot.lot_number), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-sm mt-1" }, "Received ", fmtDateTime(lot.received_at), lot.material && /* @__PURE__ */ React.createElement(React.Fragment, null, " \u2014 ", /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, lot.material.name)))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement("span", { className: `px-3 py-1 rounded-full text-sm font-medium ${statusColor}` }, ucFirst(lot.status)), /* @__PURE__ */ React.createElement(Link, { href: `/admin/material-lots/${lot.id}/edit`, className: "btn-touch btn-secondary" }, "Edit"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/material-lots", className: "btn-touch btn-ghost" }, "\u2190 Back"))), /* @__PURE__ */ React.createElement("div", { className: "card mb-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide mb-4" }, "Info"), /* @__PURE__ */ React.createElement("dl", { className: "grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm" }, /* @__PURE__ */ React.createElement(InfoCell, { label: "Material" }, lot.material ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, lot.material.name), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted block font-mono" }, lot.material.code)) : "\u2014"), /* @__PURE__ */ React.createElement(InfoCell, { label: "Quantity" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, trimQty(lot.quantity_available), " / ", trimQty(lot.quantity_received), " ", /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, lot.unit_of_measure))), /* @__PURE__ */ React.createElement(InfoCell, { label: "Expiry" }, lot.expiry_date ? /* @__PURE__ */ React.createElement("span", { className: expiryPast ? "text-om-blocked font-semibold" : "text-om-ink" }, fmtDate(lot.expiry_date)) : /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "\u2014")), /* @__PURE__ */ React.createElement(InfoCell, { label: "Manufacturing date" }, fmtDate(lot.manufacturing_date) ?? "\u2014"), /* @__PURE__ */ React.createElement(InfoCell, { label: "Supplier lot" }, lot.supplier_lot_no ?? "\u2014"), /* @__PURE__ */ React.createElement(InfoCell, { label: "Supplier reference" }, lot.supplier_reference ?? "\u2014"), /* @__PURE__ */ React.createElement(InfoCell, { label: "Inspection" }, lot.inspection ? /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/inspections/${lot.inspection.id}`,
      className: "text-om-accent hover:underline"
    },
    "#",
    lot.inspection.id,
    " (",
    lot.inspection.status,
    ")"
  ) : /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "Not linked")), /* @__PURE__ */ React.createElement(InfoCell, { label: "Source" }, lot.source?.external_name ?? "\u2014"), /* @__PURE__ */ React.createElement(InfoCell, { label: "Created by" }, lot.created_by?.name ?? "\u2014"))), lot.sublots && lot.sublots.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "card mb-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide mb-4" }, "Sublots (", lot.sublots.length, ")"), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: lot.sublots,
      columns: sublotColumns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-4" }, /* @__PURE__ */ React.createElement("h2", { className: "text-sm font-semibold text-om-muted uppercase tracking-wide" }, "Genealogy"), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, "Total consumed:", " ", /* @__PURE__ */ React.createElement("span", { className: "font-mono font-medium" }, trimQty(totalConsumed), " ", lot.unit_of_measure))), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement("h3", { className: "text-xs font-semibold text-om-muted uppercase mb-2" }, "Forward \u2014 consumed by"), !lot.consumptions || lot.consumptions.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted italic" }, "No consumption recorded yet.") : /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: lot.consumptions,
      columns: consumptionColumns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h3", { className: "text-xs font-semibold text-om-muted uppercase mb-2" }, "Backward \u2014 sourced from"), /* @__PURE__ */ React.createElement("dl", { className: "grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted" }, "Inspection"), /* @__PURE__ */ React.createElement("dd", { className: "mt-1" }, lot.inspection ? /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/inspections/${lot.inspection.id}`,
      className: "text-om-accent hover:underline"
    },
    "#",
    lot.inspection.id,
    " \u2014 ",
    ucFirst(lot.inspection.status)
  ) : /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "No inbound inspection"))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted" }, "Supplier reference"), /* @__PURE__ */ React.createElement("dd", { className: "mt-1 text-om-ink" }, lot.supplier_reference ?? lot.supplier_lot_no ?? "\u2014"))), sourceBatchId && /* @__PURE__ */ React.createElement("p", { className: "mt-3 text-xs text-om-muted" }, "Upstream source batch:", " ", /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, "#", sourceBatchId), " ", "\u2014 see backward genealogy API for full chain.")))));
}
MaterialLotShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function InfoCell({ label, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("dt", { className: "text-xs text-om-muted uppercase" }, label), /* @__PURE__ */ React.createElement("dd", { className: "mt-1 text-om-ink" }, children));
}
export {
  MaterialLotShow as default
};
