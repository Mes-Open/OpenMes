import { Head, Link, router, usePage } from "@inertiajs/react";
import { useMemo } from "react";
import { DatePicker } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { formatNumber, __ } from "../../../lib/i18n";
const KIND_BG = { blue: "bg-om-accent", amber: "bg-om-downtime", red: "bg-om-blocked" };
const KIND_TEXT = { blue: "text-om-accent", amber: "text-om-downtime", red: "text-om-blocked" };
const KIND_BADGE = { blue: "bg-om-chip text-om-accent", amber: "bg-om-downtime-bg text-om-downtime", red: "bg-om-blocked-bg text-om-blocked" };
function oeeBand(v) {
  if (v == null) return "text-om-muted";
  if (v >= 85) return "text-om-running";
  if (v >= 65) return "text-om-downtime";
  return "text-om-blocked";
}
function OeeShow() {
  const { line, records = [], downtimeByReason = [], dateFrom, dateTo } = usePage().props;
  const apply = (changes) => router.get(`/admin/oee/${line.id}`, { date_from: dateFrom, date_to: dateTo, ...changes }, { preserveState: false });
  const maxMinutes = Math.max(...downtimeByReason.map((d) => d.total_minutes ?? 0), 1);
  const columns = useMemo(() => [
    {
      id: "record_date",
      accessorKey: "record_date",
      header: "Date",
      meta: { align: "left" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.record_date)
    },
    {
      id: "shift",
      accessorFn: (r) => r.shift?.name ?? __("All"),
      header: "Shift",
      meta: { align: "left" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.shift?.name ?? __("All"))
    },
    {
      id: "planned_minutes",
      accessorKey: "planned_minutes",
      header: "Planned",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.planned_minutes, "min")
    },
    {
      id: "operating_minutes",
      accessorKey: "operating_minutes",
      header: "Operating",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.operating_minutes, "min")
    },
    {
      id: "downtime_minutes",
      accessorKey: "downtime_minutes",
      header: "Downtime",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-blocked" }, row.original.downtime_minutes, "min")
    },
    {
      id: "availability_pct",
      accessorKey: "availability_pct",
      header: "A%",
      meta: { align: "right" },
      cell: ({ row }) => row.original.availability_pct != null ? Number(row.original.availability_pct).toFixed(1) + "%" : "\u2014"
    },
    {
      id: "performance_pct",
      accessorKey: "performance_pct",
      header: "P%",
      meta: { align: "right" },
      cell: ({ row }) => row.original.performance_pct != null ? Number(row.original.performance_pct).toFixed(1) + "%" : "\u2014"
    },
    {
      id: "quality_pct",
      accessorKey: "quality_pct",
      header: "Q%",
      meta: { align: "right" },
      cell: ({ row }) => row.original.quality_pct != null ? Number(row.original.quality_pct).toFixed(1) + "%" : "\u2014"
    },
    {
      id: "oee_pct",
      accessorKey: "oee_pct",
      header: "OEE%",
      meta: { align: "right" },
      cell: ({ row }) => {
        const oeeClass = oeeBand(row.original.oee_pct != null ? Number(row.original.oee_pct) : null);
        return /* @__PURE__ */ React.createElement("span", { className: `font-bold ${oeeClass}` }, row.original.oee_pct != null ? Number(row.original.oee_pct).toFixed(1) + "%" : "\u2014");
      }
    },
    {
      id: "total_produced",
      accessorKey: "total_produced",
      header: "Produced",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, formatNumber(Number(row.original.total_produced)))
    },
    {
      id: "scrap_qty",
      accessorKey: "scrap_qty",
      header: "Scrap",
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.scrap_qty > 0 ? formatNumber(Number(row.original.scrap_qty)) : "\u2014")
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("OEE \u2014 :name", { name: line.name }) }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-start flex-wrap gap-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, line.name, " \u2014 OEE"), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 text-sm" }, dateFrom, " to ", dateTo)), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 flex-wrap" }, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `/admin/oee/print?line_id=${line.id}&date_from=${dateFrom}&date_to=${dateTo}`,
      target: "_blank",
      rel: "noreferrer",
      className: "btn-touch btn-secondary inline-flex items-center gap-2 text-sm"
    },
    /* @__PURE__ */ React.createElement("svg", { xmlns: "http://www.w3.org/2000/svg", className: "w-4 h-4", fill: "none", viewBox: "0 0 24 24", stroke: "currentColor" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" })),
    __("Print")
  ), /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `/admin/oee/print/pdf?line_id=${line.id}&date_from=${dateFrom}&date_to=${dateTo}`,
      className: "btn-touch btn-secondary inline-flex items-center gap-2 text-sm"
    },
    /* @__PURE__ */ React.createElement("svg", { xmlns: "http://www.w3.org/2000/svg", className: "w-4 h-4", fill: "none", viewBox: "0 0 24 24", stroke: "currentColor" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" })),
    __("Download PDF")
  ), /* @__PURE__ */ React.createElement(Link, { href: "/admin/oee", className: "btn-touch btn-secondary text-sm" }, __("Back to OEE")))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-4 flex flex-wrap items-end gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, __("From")), /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: dateFrom || null,
      onChange: (iso) => apply({ date_from: iso ?? "" }),
      className: "w-44"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, __("To")), /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: dateTo || null,
      onChange: (iso) => apply({ date_to: iso ?? "" }),
      className: "w-44"
    }
  ))), downtimeByReason.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink mb-4" }, __("Downtime by Reason")), /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, downtimeByReason.map((item, i) => {
    const bg = KIND_BG[item.kind_color] ?? "bg-om-blocked";
    const badge = KIND_BADGE[item.kind_color] ?? "bg-om-blocked-bg text-om-blocked";
    const pct = item.total_minutes / maxMinutes * 100;
    return /* @__PURE__ */ React.createElement("div", { key: i, className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("div", { className: "w-44 shrink-0" }, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-medium text-om-muted" }, item.reason), /* @__PURE__ */ React.createElement("span", { className: `text-xs ml-1 px-1.5 py-0.5 rounded font-medium ${badge}` }, item.kind_label)), /* @__PURE__ */ React.createElement("div", { className: "flex-1 bg-om-chip rounded-full h-5 overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: `h-full rounded-full ${bg}`, style: { width: `${pct}%` } })), /* @__PURE__ */ React.createElement("div", { className: "w-28 text-right shrink-0" }, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-mono font-bold text-om-muted" }, item.total_minutes, "min"), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint ml-1" }, "(", item.count, "\xD7)")));
  }))), records.length > 0 ? /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-5 overflow-hidden" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink mb-4" }, __("Daily Records")), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: records,
      columns,
      searchPlaceholder: "Search records\u2026",
      emptyLabel: __("No OEE records for this period.")
    }
  )) : /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-8 text-center" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("No OEE records for this period.")))));
}
OeeShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  OeeShow as default
};
