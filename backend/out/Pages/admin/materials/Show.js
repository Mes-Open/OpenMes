import { useMemo } from "react";
import { Head, Link } from "@inertiajs/react";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import CustomFieldsDisplay from "../../../components/CustomFieldsDisplay";
const MOVEMENT_TYPE_COLORS = {
  receipt: "text-om-running",
  return: "text-om-accent",
  allocation: "text-om-downtime",
  consume: "text-om-muted",
  scrap: "text-om-blocked",
  adjustment: "text-purple-700"
};
const LOT_STATUS_COLORS = {
  released: "bg-om-running-bg text-om-running",
  quarantine: "bg-om-blocked-bg text-om-blocked",
  expired: "bg-om-downtime-bg text-om-downtime"
};
function fmt(val, decimals = 3) {
  return Number(val ?? 0).toFixed(decimals);
}
function MaterialShow({ material, lots = [], recentMovements = [], customFields = [] }) {
  const available = material.available_quantity ?? 0;
  const minStock = material.min_stock_level ?? 0;
  const stockCardBorder = available < minStock ? "border-red-400" : "border-blue-400";
  const lotColumns = useMemo(() => [
    {
      id: "lot_number",
      accessorKey: "lot_number",
      header: "Lot",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.lot_number)
    },
    {
      id: "supplier_lot_no",
      accessorKey: "supplier_lot_no",
      header: "Supplier ref",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted font-mono text-xs" }, row.original.supplier_lot_no ?? "\u2014")
    },
    {
      id: "quantity_received",
      accessorKey: "quantity_received",
      header: "Received",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, fmt(row.original.quantity_received))
    },
    {
      id: "quantity_available",
      accessorKey: "quantity_available",
      header: "Available",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: `font-mono ${row.original.quantity_available <= 0 ? "text-om-faint" : "font-bold"}` }, fmt(row.original.quantity_available))
    },
    {
      id: "expiry_date",
      accessorKey: "expiry_date",
      header: "Expiry",
      cell: ({ row }) => {
        const lot = row.original;
        const expiringSoon = lot.expiry_date && isExpiringSoon(lot.expiry_date);
        return /* @__PURE__ */ React.createElement("span", { className: `text-xs ${expiringSoon ? "text-om-downtime font-semibold" : "text-om-muted"}` }, lot.expiry_date ? lot.expiry_date.substring(0, 10) : "\u2014", expiringSoon && /* @__PURE__ */ React.createElement("span", { className: "ml-1" }, "\u23F0"));
      }
    },
    {
      id: "status",
      accessorKey: "status",
      header: "Status",
      cell: ({ row }) => {
        const badge = LOT_STATUS_COLORS[row.original.status] ?? "bg-om-chip text-om-muted";
        return /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded-full text-xs font-medium ${badge}` }, ucFirst(row.original.status));
      }
    }
  ], []);
  const movementColumns = useMemo(() => [
    {
      id: "performed_at",
      accessorKey: "performed_at",
      header: "When",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs font-mono text-om-muted" }, row.original.performed_at ? row.original.performed_at.substring(0, 16).replace("T", " ") : "\u2014")
    },
    {
      id: "movement_type",
      accessorKey: "movement_type",
      header: "Type",
      cell: ({ row }) => {
        const typeColor = MOVEMENT_TYPE_COLORS[row.original.movement_type] ?? "text-om-muted";
        return /* @__PURE__ */ React.createElement("span", { className: `font-medium ${typeColor}` }, row.original.movement_type);
      }
    },
    {
      id: "delta",
      accessorKey: "quantity",
      header: "Delta",
      meta: { align: "right" },
      cell: ({ row }) => {
        const qty = Number(row.original.quantity ?? 0);
        const qtyColor = qty > 0 ? "text-om-running" : qty < 0 ? "text-om-blocked" : "text-om-muted";
        return /* @__PURE__ */ React.createElement("span", { className: `font-mono ${qtyColor}` }, qty > 0 ? "+" : "", fmt(qty));
      }
    },
    {
      id: "balance_after",
      accessorKey: "balance_after",
      header: "Balance",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, fmt(row.original.balance_after))
    },
    {
      id: "source",
      accessorFn: (r) => r.source_type ? `${r.source_type} #${r.source_id}` : "\u2014",
      header: "Source",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, row.original.source_type ? `${row.original.source_type} #${row.original.source_id}` : "\u2014")
    },
    {
      id: "reason",
      accessorKey: "reason",
      header: "Reason",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted truncate max-w-xs block", title: row.original.reason ?? "" }, (row.original.reason ?? "").substring(0, 60))
    },
    {
      id: "performed_by",
      accessorFn: (r) => r.performed_by?.name ?? "\u2014",
      header: "By",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, row.original.performed_by?.name ?? "\u2014")
    }
  ], []);
  const bomColumns = useMemo(() => [
    {
      id: "template",
      accessorFn: (r) => r.process_template?.name ?? "\u2014",
      header: "Template",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-sm" }, row.original.process_template?.name ?? "\u2014")
    },
    {
      id: "product",
      accessorFn: (r) => r.process_template?.product_type?.name ?? "-",
      header: "Product",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-sm" }, row.original.process_template?.product_type?.name ?? "-")
    },
    {
      id: "quantity_per_unit",
      accessorKey: "quantity_per_unit",
      header: "Qty/Unit",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-sm" }, row.original.quantity_per_unit)
    },
    {
      id: "scrap_percentage",
      accessorKey: "scrap_percentage",
      header: "Scrap %",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-sm" }, row.original.scrap_percentage, "%")
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `Material \u2014 ${material.name}` }), /* @__PURE__ */ React.createElement("nav", { className: "text-sm text-om-muted mb-4 flex items-center gap-1" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:underline" }, "Dashboard"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/materials", className: "hover:underline" }, "Materials"), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, material.name)), /* @__PURE__ */ React.createElement("div", { className: "max-w-5xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, material.name), material.is_active ? /* @__PURE__ */ React.createElement("span", { className: "px-3 py-1 bg-om-running-bg text-om-running rounded-full text-sm font-medium" }, "Active") : /* @__PURE__ */ React.createElement("span", { className: "px-3 py-1 bg-om-chip text-om-muted rounded-full text-sm font-medium" }, "Inactive")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1 font-mono" }, material.code)), /* @__PURE__ */ React.createElement(Link, { href: `/admin/materials/${material.id}/edit`, className: "btn-touch btn-secondary" }, "Edit")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 gap-6 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-lg font-semibold mb-4" }, "Details"), /* @__PURE__ */ React.createElement("dl", { className: "space-y-3" }, /* @__PURE__ */ React.createElement(Row, { label: "Type", value: material.material_type?.name ?? "\u2014" }), /* @__PURE__ */ React.createElement(Row, { label: "Unit", value: material.unit_of_measure ?? "\u2014" }), /* @__PURE__ */ React.createElement(Row, { label: "Tracking", value: ucFirst(material.tracking_type) }), /* @__PURE__ */ React.createElement(Row, { label: "Default Scrap %", value: `${material.default_scrap_percentage}%` }))), /* @__PURE__ */ React.createElement("div", { className: `card border-l-4 ${stockCardBorder}` }, /* @__PURE__ */ React.createElement("h3", { className: "text-lg font-semibold mb-4" }, "Stock breakdown"), /* @__PURE__ */ React.createElement("dl", { className: "space-y-2" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between text-sm" }, /* @__PURE__ */ React.createElement("dt", { className: "text-om-muted" }, "On hand"), /* @__PURE__ */ React.createElement("dd", { className: "font-mono" }, fmt(material.stock_quantity), " ", material.unit_of_measure)), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between text-sm" }, /* @__PURE__ */ React.createElement("dt", { className: "text-om-muted" }, "Reserved by active batches"), /* @__PURE__ */ React.createElement("dd", { className: "font-mono text-om-downtime" }, fmt(material.reserved_quantity), " ", material.unit_of_measure)), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between text-sm pt-2 border-t border-om-line2" }, /* @__PURE__ */ React.createElement("dt", { className: "font-medium text-om-muted" }, "Available"), /* @__PURE__ */ React.createElement("dd", { className: `font-mono font-bold ${available <= 0 ? "text-om-blocked" : "text-om-running"}` }, fmt(available), " ", material.unit_of_measure)), material.min_stock_level != null && /* @__PURE__ */ React.createElement("div", { className: "flex justify-between text-xs text-om-faint" }, /* @__PURE__ */ React.createElement("dt", null, "Min stock level"), /* @__PURE__ */ React.createElement("dd", { className: "font-mono" }, fmt(material.min_stock_level), " ", material.unit_of_measure)), material.unit_price != null && /* @__PURE__ */ React.createElement("div", { className: "flex justify-between text-xs text-om-faint" }, /* @__PURE__ */ React.createElement("dt", null, "Stock value"), /* @__PURE__ */ React.createElement("dd", { className: "font-mono" }, Number(material.stock_quantity * material.unit_price).toFixed(2), " ", material.price_currency)))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-lg font-semibold mb-4" }, "External System"), material.external_code ? /* @__PURE__ */ React.createElement("dl", { className: "space-y-3" }, /* @__PURE__ */ React.createElement(Row, { label: "System", value: material.external_system }), /* @__PURE__ */ React.createElement(Row, { label: "External Code", value: /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, material.external_code) })) : /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, "No external system linked."), material.sources && material.sources.length > 0 && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("h4", { className: "text-sm font-semibold mt-4 mb-2" }, "Additional Sources"), material.sources.map((src) => /* @__PURE__ */ React.createElement("div", { key: src.id, className: "p-2 bg-om-panel rounded mb-2 text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, src.integration_config?.system_name ?? "Unknown"), ":", " ", /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, src.external_code)))))), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(CustomFieldsDisplay, { definitions: customFields, values: material.custom_fields ?? {} })), lots.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "card mb-6" }, /* @__PURE__ */ React.createElement("h3", { className: "text-lg font-semibold mb-4" }, "Lots ", /* @__PURE__ */ React.createElement("span", { className: "text-sm font-normal text-om-faint" }, "(", lots.length, ")")), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: lots,
      columns: lotColumns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  )), recentMovements.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "card mb-6" }, /* @__PURE__ */ React.createElement("h3", { className: "text-lg font-semibold mb-4" }, "Recent stock movements"), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: recentMovements,
      columns: movementColumns,
      searchPlaceholder: "Search movements\u2026"
    }
  )), material.bom_items && material.bom_items.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("h3", { className: "text-lg font-semibold mb-4" }, "Used in BOM (", material.bom_items.length, " templates)"), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: material.bom_items,
      columns: bomColumns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  ))));
}
MaterialShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function ucFirst(str) {
  if (!str) return "\u2014";
  return str.charAt(0).toUpperCase() + str.slice(1);
}
function isExpiringSoon(dateStr) {
  const d = new Date(dateStr);
  const now = /* @__PURE__ */ new Date();
  const diff = (d - now) / (1e3 * 60 * 60 * 24);
  return diff >= 0 && diff <= 30;
}
function Row({ label, value }) {
  return /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("dt", { className: "text-sm text-om-muted" }, label), /* @__PURE__ */ React.createElement("dd", { className: "text-sm font-medium" }, value));
}
export {
  MaterialShow as default
};
