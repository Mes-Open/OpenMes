import { Head, router, useForm, usePage } from "@inertiajs/react";
import { Badge, Button, Dropdown, InlineAlert } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import { useMemo } from "react";
import AppLayout from "../../../layouts/AppLayout";
import { __ } from "../../../lib/i18n";
const ALERT_SEVERITY = {
  danger: "error",
  warning: "warning",
  info: "info"
};
function Metric({ label, value, sub, accent }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5 text-center" }, /* @__PURE__ */ React.createElement("p", { className: `font-mono text-[27px] font-medium leading-none tracking-[-0.02em] ${accent ?? "text-om-ink"}` }, value), /* @__PURE__ */ React.createElement("p", { className: "mt-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, label), sub && /* @__PURE__ */ React.createElement("p", { className: "text-[11px] text-om-faint mt-0.5" }, sub));
}
function ShiftHandoverIndex() {
  const { lines = [], selectedLineId = null, balance, recent = [] } = usePage().props;
  const form = useForm({ line_id: selectedLineId ?? "", notes: "" });
  const onLineChange = (value) => {
    router.get("/supervisor/shift-handover", value ? { line_id: value } : {}, {
      preserveScroll: true,
      preserveState: false
    });
  };
  const submit = (e) => {
    e.preventDefault();
    if (!confirm(__("Confirm & close shift") + "?")) return;
    form.transform((data) => ({ ...data, line_id: selectedLineId ?? "" }));
    form.post("/supervisor/shift-handover", { preserveScroll: true });
  };
  const shift = balance?.shift;
  const discrepancies = balance?.discrepancies ? Object.values(balance.discrepancies) : [];
  const recentColumns = useMemo(() => [
    {
      id: "shift_start",
      accessorKey: "shift_start",
      header: __("Shift"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "whitespace-nowrap text-om-muted" }, row.original.shift_start)
    },
    {
      id: "line_name",
      accessorKey: "line_name",
      header: __("Line"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, row.original.line_name ?? "\u2014")
    },
    {
      id: "produced_qty",
      accessorKey: "produced_qty",
      header: __("Produced"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-ink" }, row.original.produced_qty)
    },
    {
      id: "good_qty",
      accessorKey: "good_qty",
      header: __("Good"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-ink" }, row.original.good_qty)
    },
    {
      id: "packed_qty",
      accessorKey: "packed_qty",
      header: __("Packed"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-ink" }, row.original.packed_qty)
    },
    {
      id: "shipped_qty",
      accessorKey: "shipped_qty",
      header: __("Shipped"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] text-om-ink" }, row.original.shipped_qty)
    },
    {
      id: "confirmed_by",
      accessorKey: "confirmed_by",
      header: __("Confirmed by"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.confirmed_by ?? "\u2014")
    },
    {
      id: "confirmed_at",
      accessorKey: "confirmed_at",
      header: __("Confirmed at"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "whitespace-nowrap text-om-faint" }, row.original.confirmed_at)
    }
  ], []);
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: "Shift Handover" }), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink" }, __("Shift Handover")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, shift ? `${shift.name} (${shift.start}\u2013${shift.end})` : __("No shift configured \u2014 using default window"), balance?.window?.business_date ? ` \xB7 ${balance.window.business_date}` : "")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1" }, __("Line")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: selectedLineId == null ? "" : String(selectedLineId),
      onChange: (v) => onLineChange(v),
      options: [
        { value: "", label: __("All lines") },
        ...lines.map((l) => ({ value: String(l.id), label: l.name }))
      ],
      className: "min-w-[180px]"
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6" }, /* @__PURE__ */ React.createElement(Metric, { label: __("Produced"), value: balance?.produced_qty ?? 0, accent: "text-om-accent" }), /* @__PURE__ */ React.createElement(Metric, { label: __("Scrap"), value: balance?.scrap_qty ?? 0, accent: "text-om-blocked" }), /* @__PURE__ */ React.createElement(Metric, { label: __("Good"), value: balance?.good_qty ?? 0, accent: "text-om-running" }), /* @__PURE__ */ React.createElement(Metric, { label: __("Packed"), value: balance?.packed_qty ?? 0, accent: "text-om-ink" }), /* @__PURE__ */ React.createElement(
    Metric,
    {
      label: __("WIP"),
      value: balance?.wip_total_qty ?? 0,
      sub: `${balance?.wip_open_pallets_qty ?? 0} ${__("Open pallets")} + ${balance?.wip_unpacked_qty ?? 0} ${__("Unpacked")}`,
      accent: "text-om-downtime"
    }
  ), /* @__PURE__ */ React.createElement(Metric, { label: __("Shipped"), value: balance?.shipped_qty ?? 0, accent: "text-om-muted" })), discrepancies.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "space-y-2 mb-6" }, discrepancies.map((d, i) => /* @__PURE__ */ React.createElement(InlineAlert, { key: i, severity: ALERT_SEVERITY[d.severity] ?? "info", title: d.label }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] font-medium text-om-ink" }, d.value)))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-b border-om-line2 flex items-center justify-between" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink" }, __("Open pallets")), /* @__PURE__ */ React.createElement(Badge, { variant: "neutral" }, balance?.wip_open_pallets_count ?? 0)), !balance?.open_pallets || balance.open_pallets.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "px-4 py-6 text-center text-om-faint text-sm" }, __("No open pallets")) : /* @__PURE__ */ React.createElement("div", { className: "max-h-64 overflow-y-auto" }, balance.open_pallets.map((p) => /* @__PURE__ */ React.createElement("div", { key: p.id, className: "px-4 py-2 flex items-center justify-between border-b border-om-line2 last:border-0 hover:bg-om-bg text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[12px] font-medium text-om-accent" }, p.pallet_no), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[12px] text-om-muted" }, p.order_no), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[13px] font-medium text-om-ink" }, p.qty, " ", __("pcs")))))), /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink mb-3" }, __("Close shift")), /* @__PURE__ */ React.createElement("label", { className: "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1.5" }, __("Notes")), /* @__PURE__ */ React.createElement(
    "textarea",
    {
      value: form.data.notes,
      onChange: (e) => form.setData("notes", e.target.value),
      rows: 4,
      className: "w-full rounded-om-sm border border-om-line bg-om-card px-3 py-2 text-sm text-om-ink placeholder:text-om-faint focus:outline-none focus:border-om-accent",
      placeholder: __("Handover notes (optional)")
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mt-2" }, __("Confirming saves an immutable audit snapshot of the figures above.")), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "submit",
      variant: "accent",
      disabled: form.processing,
      className: "mt-3 w-full"
    },
    form.processing ? __("Saving\u2026") : __("Confirm & close shift")
  ))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-b border-om-line2" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink" }, __("Recent handovers"))), /* @__PURE__ */ React.createElement("div", { className: "p-4" }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: recent,
      columns: recentColumns,
      searchable: true,
      columnToggle: true,
      paginated: true,
      searchPlaceholder: __("Search handovers\u2026"),
      emptyLabel: __("No handovers yet")
    }
  ))));
}
ShiftHandoverIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ShiftHandoverIndex as default
};
