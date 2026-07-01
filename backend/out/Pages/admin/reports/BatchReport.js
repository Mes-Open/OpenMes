import { Head, usePage } from "@inertiajs/react";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
const bomColumns = [
  { id: "material_name", accessorKey: "material_name", header: "Material", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, row.original.material_name) },
  { id: "material_code", accessorKey: "material_code", header: "Code", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, row.original.material_code) },
  { id: "material_type", accessorKey: "material_type", header: "Type", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.material_type?.replace(/_/g, " ")) },
  { id: "quantity_per_unit", accessorKey: "quantity_per_unit", header: "Qty/Unit", meta: { align: "right" }, cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.quantity_per_unit) },
  {
    id: "total_qty",
    accessorKey: "total_qty",
    header: "Total",
    meta: { align: "right" },
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono font-bold" }, row.original.total_qty, row.original.scrap_percentage > 0 && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint ml-1" }, "(+", row.original.scrap_percentage, "%)"))
  },
  { id: "unit_of_measure", accessorKey: "unit_of_measure", header: "Unit", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.unit_of_measure) },
  { id: "external_code", accessorKey: "external_code", header: "Supplier LOT", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-faint font-mono" }, row.original.external_code ?? "\u2014") }
];
const stepColumns = [
  { id: "step_number", accessorKey: "step_number", header: "#", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted" }, row.original.step_number) },
  { id: "name", accessorKey: "name", header: "Step", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, row.original.name) },
  { id: "started_at", accessorKey: "started_at", header: "Started", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted font-mono text-xs" }, row.original.started_at ?? "\u2014") },
  { id: "started_by", accessorFn: (r) => r.started_by?.name ?? "\u2014", header: "Started By", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.started_by?.name ?? "\u2014") },
  { id: "completed_at", accessorKey: "completed_at", header: "Completed", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted font-mono text-xs" }, row.original.completed_at ?? "\u2014") },
  { id: "completed_by", accessorFn: (r) => r.completed_by?.name ?? "\u2014", header: "Completed By", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.completed_by?.name ?? "\u2014") },
  { id: "duration_minutes", accessorKey: "duration_minutes", header: "Duration", meta: { align: "right" }, cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.duration_minutes ? `${row.original.duration_minutes} min` : "\u2014") },
  { id: "status", accessorKey: "status", header: "Status", cell: ({ row }) => /* @__PURE__ */ React.createElement(StatusBadge, { status: row.original.status }) }
];
const confirmationColumns = [
  { id: "confirmed_at", accessorKey: "confirmed_at", header: "Date & Time", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs" }, row.original.confirmed_at) },
  { id: "confirmation_type", accessorKey: "confirmation_type", header: "Type", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "capitalize" }, row.original.confirmation_type) },
  { id: "value", accessorKey: "value", header: "Value", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.value ?? "\u2014") },
  { id: "confirmed_by", accessorFn: (r) => r.confirmed_by?.name ?? "\u2014", header: "Confirmed By", cell: ({ row }) => row.original.confirmed_by?.name ?? "\u2014" },
  { id: "notes", accessorKey: "notes", header: "Notes", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.notes ?? "\u2014") }
];
const sampleColumns = [
  { id: "sample_number", accessorKey: "sample_number", header: "Sample #", cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.sample_number) },
  { id: "parameter_name", accessorKey: "parameter_name", header: "Parameter", cell: ({ row }) => row.original.parameter_name },
  {
    id: "value",
    header: "Value",
    accessorFn: (s) => s.parameter_type === "measurement" ? s.value_numeric : s.value_boolean ? "Yes" : "No",
    cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.parameter_type === "measurement" ? row.original.value_numeric : row.original.value_boolean ? "Yes" : "No")
  },
  { id: "result", accessorKey: "is_passed", header: "Result", cell: ({ row }) => /* @__PURE__ */ React.createElement(PassBadge, { pass: row.original.is_passed }) }
];
function PassBadge({ pass }) {
  return pass ? /* @__PURE__ */ React.createElement("span", { className: "inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-running-bg text-om-running" }, "PASS") : /* @__PURE__ */ React.createElement("span", { className: "inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-blocked-bg text-om-blocked" }, "FAIL");
}
function StatusBadge({ status }) {
  const styles = {
    DONE: "bg-om-running-bg text-om-running",
    IN_PROGRESS: "bg-om-chip text-om-accent"
  };
  return /* @__PURE__ */ React.createElement("span", { className: `inline-block px-2 py-0.5 rounded text-xs font-bold ${styles[status] ?? "bg-om-chip text-om-muted"}` }, status);
}
function BatchReport() {
  const { batch, workOrder, bom = [], steps = [], confirmations = [], qualityChecks = [], checklist } = usePage().props;
  const title = batch.lot_number ?? `Batch #${batch.batch_number}`;
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `Series Report \u2014 ${title}` }), /* @__PURE__ */ React.createElement("div", { className: "max-w-4xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center flex-wrap gap-3 print:hidden" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => window.history.back(),
      className: "inline-flex items-center gap-2 text-om-accent hover:text-om-accent text-sm font-medium"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M15 19l-7-7 7-7" })),
    "Back"
  ), /* @__PURE__ */ React.createElement("div", { className: "flex gap-3" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => window.print(),
      className: "btn-touch btn-secondary text-sm"
    },
    "Print"
  ), /* @__PURE__ */ React.createElement("a", { href: `/admin/batches/${batch.id}/report/pdf`, className: "btn-touch btn-primary text-sm" }, "Download PDF"))), /* @__PURE__ */ React.createElement(Section, { title: "General Information" }, /* @__PURE__ */ React.createElement(InfoTable, { rows: [
    ["Work Order", workOrder?.order_no],
    ["Product", workOrder?.product_type?.name ?? "\u2014"],
    ["Line", workOrder?.line?.name ?? "\u2014"],
    ["Workstation", batch.workstation?.name ?? "\u2014"],
    ["LOT Number", /* @__PURE__ */ React.createElement("strong", { key: "lot" }, batch.lot_number ?? "Not assigned")],
    ["Planned Quantity", `${Number(batch.target_qty).toFixed(2)} pcs`],
    ["Produced Quantity", `${Number(batch.produced_qty).toFixed(2)} pcs`],
    ...batch.scrap_qty ? [["Scrap", `${Number(batch.scrap_qty).toFixed(2)} pcs`]] : [],
    ["Started", batch.started_at ?? "\u2014"],
    ["Completed", batch.completed_at ?? "\u2014"],
    ...batch.released_at ? [
      ["Released", `${batch.released_at} (${batch.release_type === "for_sale" ? "For Sale" : "For Production"})`],
      ["Released By", batch.released_by?.name ?? "\u2014"]
    ] : [],
    ...batch.expiry_date ? [["Expiry Date", batch.expiry_date]] : []
  ] })), bom.length > 0 && /* @__PURE__ */ React.createElement(Section, { title: "Materials (BOM)" }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: bom,
      columns: bomColumns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  )), /* @__PURE__ */ React.createElement(Section, { title: "Production Steps" }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: steps,
      columns: stepColumns,
      searchable: false,
      columnToggle: false,
      paginated: false,
      emptyLabel: "No steps recorded."
    }
  )), confirmations.length > 0 && /* @__PURE__ */ React.createElement(Section, { title: "Process Confirmations" }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: confirmations,
      columns: confirmationColumns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  )), qualityChecks.length > 0 && /* @__PURE__ */ React.createElement(Section, { title: `Quality Checks (${qualityChecks.length})` }, /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, qualityChecks.map((qc, qi) => /* @__PURE__ */ React.createElement("div", { key: qc.id ?? qi, className: "border border-om-line2 rounded-om-sm overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-panel px-4 py-2 flex flex-wrap gap-3 items-center text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "font-bold" }, "Check #", qi + 1), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted font-mono text-xs" }, qc.checked_at), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "By: ", qc.checked_by?.name ?? "\u2014"), qc.production_quantity != null && /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, "Production: ", Number(qc.production_quantity).toFixed(0), " pcs"), /* @__PURE__ */ React.createElement(PassBadge, { pass: qc.all_passed })), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: qc.samples ?? [],
      columns: sampleColumns,
      searchable: false,
      columnToggle: false,
      paginated: false
    }
  ))))), checklist && /* @__PURE__ */ React.createElement(Section, { title: "Packaging Checklist" }, /* @__PURE__ */ React.createElement(InfoTable, { rows: [
    ["UDI code readable", /* @__PURE__ */ React.createElement(PassBadge, { key: "udi", pass: checklist.udi_readable })],
    ["Packaging in good condition", /* @__PURE__ */ React.createElement(PassBadge, { key: "pkg", pass: checklist.packaging_condition })],
    ["Labels readable", /* @__PURE__ */ React.createElement(PassBadge, { key: "lbl", pass: checklist.labels_readable })],
    ["Label matches product", /* @__PURE__ */ React.createElement(PassBadge, { key: "match", pass: checklist.label_matches_product })],
    [
      "Overall",
      checklist.all_passed ? /* @__PURE__ */ React.createElement("span", { key: "overall", className: "inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-running-bg text-om-running" }, "ALL PASS") : /* @__PURE__ */ React.createElement("span", { key: "overall", className: "inline-block px-2 py-0.5 rounded text-xs font-bold bg-om-blocked-bg text-om-blocked" }, "FAILED")
    ]
  ] }), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mt-2" }, "Checked by: ", checklist.checked_by?.name ?? "\u2014", " | ", checklist.checked_at ?? "\u2014"))));
}
BatchReport.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function Section({ title, children }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: "px-5 py-3 border-b border-om-line2" }, /* @__PURE__ */ React.createElement("h2", { className: "text-base font-bold text-om-ink" }, title)), /* @__PURE__ */ React.createElement("div", { className: "p-5" }, children));
}
function InfoTable({ rows }) {
  return /* @__PURE__ */ React.createElement("table", { className: "w-full text-sm" }, /* @__PURE__ */ React.createElement("tbody", null, rows.map(([label, value], i) => /* @__PURE__ */ React.createElement("tr", { key: i, className: "border-b border-om-line2 last:border-0" }, /* @__PURE__ */ React.createElement("td", { className: "py-2 pr-4 text-om-muted font-medium w-2/5" }, label), /* @__PURE__ */ React.createElement("td", { className: "py-2 text-om-ink" }, value)))));
}
export {
  BatchReport as default
};
