import { Head, router, usePage } from "@inertiajs/react";
import { DatePicker, Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import { useMemo, useState } from "react";
import AppLayout from "../../../layouts/AppLayout";
import { formatNumber, __ } from "../../../lib/i18n";
import OeeGauge from "../../../components/OeeGauge";
const LINE_PALETTE = ["#2563eb", "#db2777", "#0891b2", "#16a34a", "#ea580c", "#7c3aed"];
function oeeBand(v) {
  if (v == null) return { text: "text-om-muted", bg: "bg-om-faintest" };
  if (v >= 85) return { text: "text-om-running", bg: "bg-om-running" };
  if (v >= 65) return { text: "text-om-downtime", bg: "bg-om-downtime" };
  return { text: "text-om-blocked", bg: "bg-om-blocked" };
}
function fmt1(v) {
  return v != null ? Number(v).toFixed(1) + "%" : "\u2014";
}
function OeeIndex() {
  const { lines = [], lineId, dateFrom, dateTo, records = [], summary = {}, trend = [], trendByLine = [], granularity } = usePage().props;
  const [mode, setMode] = useState(lineId ? "per_line" : "combined");
  const apply = (changes) => router.get("/admin/oee", { line_id: lineId ?? "", date_from: dateFrom, date_to: dateTo, granularity, ...changes }, { preserveState: false });
  const coloredByLine = trendByLine.map((l, i) => ({ ...l, color: LINE_PALETTE[i % LINE_PALETTE.length] }));
  const maxTrend = Math.max(...trend.map((d) => d.oee ?? 0), 1);
  const recordColumns = useMemo(() => [
    {
      id: "record_date",
      accessorKey: "record_date",
      header: "Date",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.record_date)
    },
    {
      id: "line",
      accessorFn: (r) => r.line?.name,
      header: "Line",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, row.original.line?.name)
    },
    {
      id: "shift",
      accessorFn: (r) => r.shift?.name ?? __("All"),
      header: "Shift",
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.shift?.name ?? __("All"))
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
        const r = row.original;
        const band = oeeBand(r.oee_pct != null ? Number(r.oee_pct) : null);
        return /* @__PURE__ */ React.createElement("span", { className: `font-bold ${band.text}` }, r.oee_pct != null ? Number(r.oee_pct).toFixed(1) + "%" : "\u2014");
      }
    },
    {
      id: "total_produced",
      accessorKey: "total_produced",
      header: __("Produced"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, formatNumber(Number(row.original.total_produced)))
    },
    {
      id: "scrap_qty",
      accessorKey: "scrap_qty",
      header: __("Scrap"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-blocked" }, row.original.scrap_qty > 0 ? formatNumber(Number(row.original.scrap_qty)) : "\u2014")
    },
    {
      id: "downtime_minutes",
      accessorKey: "downtime_minutes",
      header: __("Downtime"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.downtime_minutes, "min")
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("OEE Report") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-start" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("OEE Report")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 text-sm" }, __("Overall Equipment Effectiveness \u2014 Availability \xD7 Performance \xD7 Quality"))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `/admin/oee/print?${new URLSearchParams(Object.fromEntries(Object.entries({ line_id: lineId, date_from: dateFrom, date_to: dateTo }).filter(([, v]) => v != null && v !== "")))}`,
      target: "_blank",
      rel: "noreferrer",
      className: "btn-touch btn-secondary inline-flex items-center gap-2 text-sm"
    },
    /* @__PURE__ */ React.createElement("svg", { xmlns: "http://www.w3.org/2000/svg", className: "w-4 h-4", fill: "none", viewBox: "0 0 24 24", stroke: "currentColor" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" })),
    __("Print")
  ), /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `/admin/oee/print/pdf?${new URLSearchParams(Object.fromEntries(Object.entries({ line_id: lineId, date_from: dateFrom, date_to: dateTo }).filter(([, v]) => v != null && v !== "")))}`,
      className: "btn-touch btn-secondary inline-flex items-center gap-2 text-sm"
    },
    /* @__PURE__ */ React.createElement("svg", { xmlns: "http://www.w3.org/2000/svg", className: "w-4 h-4", fill: "none", viewBox: "0 0 24 24", stroke: "currentColor" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: 2, d: "M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" })),
    __("Download PDF")
  ))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-4 flex flex-wrap items-end gap-4" }, /* @__PURE__ */ React.createElement(Filter, { label: "Line" }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      className: "min-w-[160px]",
      options: [{ value: "", label: __("All Lines") }, ...lines.map((l) => ({ value: String(l.id), label: l.name }))],
      value: lineId == null ? "" : String(lineId),
      onChange: (v) => apply({ line_id: v })
    }
  )), /* @__PURE__ */ React.createElement(Filter, { label: "From" }, /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: dateFrom || null,
      onChange: (iso) => apply({ date_from: iso ?? "" }),
      className: "w-44"
    }
  )), /* @__PURE__ */ React.createElement(Filter, { label: "To" }, /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: dateTo || null,
      onChange: (iso) => apply({ date_to: iso ?? "" }),
      className: "w-44"
    }
  ))), Object.keys(summary).length > 0 && /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" }, lines.map((line) => {
    const s = summary[line.id];
    if (!s) return null;
    const oee = s.avg_oee != null ? Number(s.avg_oee).toFixed(1) : null;
    return /* @__PURE__ */ React.createElement(
      "a",
      {
        key: line.id,
        href: `/admin/oee/${line.id}?date_from=${dateFrom}&date_to=${dateTo}`,
        className: "bg-om-card rounded-om-sm shadow-sm p-5 hover:shadow-md transition-shadow flex flex-col items-center text-center"
      },
      /* @__PURE__ */ React.createElement("h3", { className: "font-bold text-om-ink mb-3" }, line.name),
      /* @__PURE__ */ React.createElement(OeeGauge, { value: oee != null ? Number(oee) : null }),
      /* @__PURE__ */ React.createElement("div", { className: "w-full grid grid-cols-3 gap-2 mt-4" }, /* @__PURE__ */ React.createElement(MetricMini, { label: "Availability", value: fmt1(s.avg_availability) }), /* @__PURE__ */ React.createElement(MetricMini, { label: "Performance", value: s.avg_performance != null ? fmt1(s.avg_performance) : "N/A" }), /* @__PURE__ */ React.createElement(MetricMini, { label: "Quality", value: fmt1(s.avg_quality) })),
      /* @__PURE__ */ React.createElement("div", { className: "w-full mt-3 pt-3 border-t border-om-line2 flex justify-around text-xs text-om-muted" }, /* @__PURE__ */ React.createElement("span", null, "Produced: ", formatNumber(Number(s.total_produced))), /* @__PURE__ */ React.createElement("span", null, "Scrap: ", formatNumber(Number(s.total_scrap))), /* @__PURE__ */ React.createElement("span", null, "Downtime: ", s.total_downtime, "min"))
    );
  })), trend.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-4 flex-wrap gap-2" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink" }, __("OEE Trend")), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2 flex-wrap" }, coloredByLine.length > 1 && /* @__PURE__ */ React.createElement("div", { className: "inline-flex rounded-om-sm border border-om-line2 overflow-hidden" }, /* @__PURE__ */ React.createElement(ModeBtn, { active: mode === "combined", onClick: () => setMode("combined") }, __("Combined")), /* @__PURE__ */ React.createElement(ModeBtn, { active: mode === "per_line", onClick: () => setMode("per_line") }, __("Per line"))), /* @__PURE__ */ React.createElement("div", { className: "inline-flex rounded-om-sm border border-om-line2 overflow-hidden" }, [["day", __("Daily")], ["week", __("Weekly")], ["month", __("Monthly")]].map(([key, label]) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key,
      onClick: () => apply({ granularity: key }),
      className: `px-3 py-1 text-sm ${granularity === key ? "bg-om-ink text-om-on-ink" : "bg-om-card text-om-muted hover:bg-om-bg"}`
    },
    label
  ))))), mode === "combined" && /* @__PURE__ */ React.createElement("div", { className: "h-48 flex items-end gap-1 overflow-x-auto pb-6" }, trend.map((day, i) => {
    const band = oeeBand(day.oee);
    const height = Math.max(day.oee / 100 * 160, 2);
    return /* @__PURE__ */ React.createElement("div", { key: i, className: "flex-1 min-w-[20px] flex flex-col items-center gap-1" }, /* @__PURE__ */ React.createElement("span", { className: `text-xs font-bold ${band.text}` }, day.oee, "%"), /* @__PURE__ */ React.createElement(
      "div",
      {
        className: `w-full rounded-t transition-all ${band.bg}`,
        style: { height: `${height}px` }
      }
    ), /* @__PURE__ */ React.createElement("span", { className: `text-[10px] text-om-faint whitespace-nowrap ${granularity === "day" ? "rotate-[-45deg] origin-top-left" : ""}` }, granularity === "day" ? day.date.substring(5) : day.date));
  })), mode === "per_line" && coloredByLine.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "h-48 flex items-end gap-3 overflow-x-auto pb-6" }, (coloredByLine[0]?.points ?? []).map((bucket, b) => /* @__PURE__ */ React.createElement("div", { key: b, className: "flex-1 min-w-[40px] flex flex-col items-center gap-1" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-end gap-px h-40 w-full justify-center" }, coloredByLine.map((line) => {
    const pt = line.points[b] ?? { oee: 0 };
    const h = Math.max(pt.oee / 100 * 140, 2);
    return /* @__PURE__ */ React.createElement("div", { key: line.line_id, className: "flex flex-col items-center justify-end", style: { width: 18 }, title: `${line.line_name}: ${pt.oee}%` }, /* @__PURE__ */ React.createElement("span", { className: "text-[9px] font-semibold", style: { color: line.color } }, pt.oee, "%"), /* @__PURE__ */ React.createElement("div", { className: "rounded-t transition-all", style: { background: line.color, height: `${h}px`, width: "100%" } }));
  })), /* @__PURE__ */ React.createElement("span", { className: `text-[10px] text-om-faint whitespace-nowrap ${granularity === "day" ? "rotate-[-45deg] origin-top-left" : ""}` }, granularity === "day" ? bucket.date.substring(5) : bucket.date)))), /* @__PURE__ */ React.createElement("div", { className: "mt-2 flex items-center gap-4 text-xs text-om-muted flex-wrap" }, mode === "combined" ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("span", { className: "flex items-center gap-1" }, /* @__PURE__ */ React.createElement("span", { className: "w-3 h-3 bg-om-running rounded inline-block" }), " ", __("\u2265 85% (World-class)")), /* @__PURE__ */ React.createElement("span", { className: "flex items-center gap-1" }, /* @__PURE__ */ React.createElement("span", { className: "w-3 h-3 bg-om-downtime rounded inline-block" }), " 65\u201384%"), /* @__PURE__ */ React.createElement("span", { className: "flex items-center gap-1" }, /* @__PURE__ */ React.createElement("span", { className: "w-3 h-3 bg-om-blocked rounded inline-block" }), " < 65%")) : coloredByLine.map((l) => /* @__PURE__ */ React.createElement("span", { key: l.line_id, className: "flex items-center gap-1" }, /* @__PURE__ */ React.createElement("span", { className: "w-3 h-3 rounded inline-block", style: { background: l.color } }), l.line_name)))), records.length > 0 ? /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-5 overflow-hidden" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink mb-4" }, __("Daily Records")), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: records,
      columns: recordColumns,
      searchPlaceholder: "Search records\u2026"
    }
  )) : /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-12 text-center" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted text-lg mb-2" }, __("No OEE data available")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint" }, __("OEE data will appear once production batches are completed and downtimes are reported.")))));
}
OeeIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function Filter({ label, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, label), children);
}
function ModeBtn({ active, onClick, children }) {
  return /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick,
      className: `px-3 py-1 text-sm ${active ? "bg-om-ink text-om-on-ink" : "bg-om-card text-om-muted hover:bg-om-bg"}`
    },
    children
  );
}
function MetricMini({ label, value }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-[10px] text-om-muted uppercase tracking-wide leading-tight" }, label), /* @__PURE__ */ React.createElement("p", { className: "text-sm font-bold text-om-ink" }, value));
}
export {
  OeeIndex as default
};
