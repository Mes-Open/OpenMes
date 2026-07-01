import { useMemo, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { DatePicker, Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { __, formatDateTime, formatNumber } from "../../../lib/i18n";
const STATUS_BADGE = {
  DONE: "bg-om-running-bg text-om-running",
  CANCELLED: "bg-om-chip text-om-muted",
  REJECTED: "bg-om-blocked-bg text-om-blocked"
};
const PRESET_LABELS = {
  today: "Today",
  yesterday: "Yesterday",
  last7: "Last 7 days",
  last30: "Last 30 days",
  this_month: "This month",
  last_month: "Last month",
  custom: "Custom",
  all: "All time"
};
function fmtDuration(min) {
  if (min == null) return "\u2014";
  const total = Math.round(min);
  const h = Math.floor(total / 60);
  const m = total % 60;
  return h > 0 ? `${h}h ${m}m` : `${m}m`;
}
function ReportsIndex() {
  const { orders, summary = {}, filters = {}, lines = [], productTypes = [], statusOptions = [], presets = [] } = usePage().props;
  const [form, setForm] = useState({
    status: filters.status ?? "",
    line_id: filters.line_id ?? "",
    product_type_id: filters.product_type_id ?? "",
    preset: filters.preset ?? "last30",
    from: filters.from ?? "",
    to: filters.to ?? "",
    search: filters.search ?? ""
  });
  const apply = (overrides = {}) => {
    const params = { ...form, ...overrides };
    Object.keys(params).forEach((k) => {
      if (params[k] === "" || params[k] == null) delete params[k];
    });
    router.get("/admin/reports", params, { preserveState: false, preserveScroll: true });
  };
  const setPreset = (preset) => {
    setForm((f) => ({ ...f, preset }));
    apply({ preset });
  };
  const clear = () => router.get("/admin/reports", {}, { preserveState: false });
  const exportUrl = () => {
    const p = new URLSearchParams();
    Object.entries(form).forEach(([k, v]) => {
      if (v) p.set(k, v);
    });
    const qs = p.toString();
    return `/admin/reports/export${qs ? "?" + qs : ""}`;
  };
  const goPage = (page) => apply({ page });
  const rows = orders?.data ?? [];
  const links = orders?.links ?? [];
  const lastPage = orders?.last_page ?? 1;
  const columns = useMemo(
    () => [
      {
        id: "order_no",
        accessorKey: "order_no",
        header: __("Order"),
        cell: ({ row }) => {
          const r = row.original;
          return /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-accent" }, /* @__PURE__ */ React.createElement(Link, { href: `/admin/reports/${r.id}`, onClick: (e) => e.stopPropagation() }, r.order_no));
        }
      },
      {
        id: "product",
        accessorFn: (r) => r.product_name ?? "",
        header: __("Product"),
        cell: ({ row }) => {
          const r = row.original;
          return /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, r.product_name ?? "\u2014", r.product_code && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint font-mono ml-1" }, r.product_code));
        }
      },
      {
        id: "line",
        accessorFn: (r) => r.line_name ?? "",
        header: __("Line"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.line_name ?? "\u2014")
      },
      {
        id: "status",
        accessorKey: "status",
        header: __("Status"),
        cell: ({ row }) => {
          const r = row.original;
          return /* @__PURE__ */ React.createElement(
            "span",
            {
              className: `px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[r.status] ?? "bg-om-chip text-om-muted"}`
            },
            __(r.status)
          );
        }
      },
      {
        id: "completed",
        accessorKey: "completed_at",
        header: __("Completed"),
        cell: ({ row }) => {
          const r = row.original;
          return /* @__PURE__ */ React.createElement("span", { className: "text-om-muted whitespace-nowrap" }, r.completed_at ? formatDateTime(r.completed_at) : "\u2014");
        }
      },
      {
        id: "produced_planned",
        accessorFn: (r) => r.produced_qty,
        header: `${__("Produced")} / ${__("Planned")}`,
        meta: { align: "right" },
        cell: ({ row }) => {
          const r = row.original;
          return /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, formatNumber(r.produced_qty), " / ", formatNumber(r.planned_qty));
        }
      },
      {
        id: "execution",
        accessorKey: "execution_minutes",
        header: __("Execution"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted whitespace-nowrap" }, fmtDuration(row.original.execution_minutes))
      },
      {
        id: "lots",
        accessorFn: (r) => r.lots ? r.lots.join(", ") : "",
        header: __("LOTs"),
        cell: ({ row }) => {
          const r = row.original;
          return /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs text-om-muted" }, r.lots.length ? r.lots.slice(0, 2).join(", ") : "\u2014", r.lots.length > 2 && /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, " +", r.lots.length - 2));
        }
      },
      {
        id: "issues",
        accessorKey: "issues_count",
        header: __("Issues"),
        meta: { align: "right" },
        cell: ({ row }) => {
          const r = row.original;
          return r.issues_count > 0 ? /* @__PURE__ */ React.createElement("span", { className: "px-2 py-0.5 rounded-full text-xs bg-om-downtime-bg text-om-downtime" }, r.issues_count) : /* @__PURE__ */ React.createElement("span", { className: "text-om-faintest" }, "0");
        }
      }
    ],
    []
  );
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Work Order History") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Work Order History")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, __("Completed, cancelled and rejected orders \u2014 full execution record."))), /* @__PURE__ */ React.createElement("a", { href: exportUrl(), className: "btn-touch btn-secondary whitespace-nowrap" }, __("Export CSV"))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 md:grid-cols-5 gap-3" }, /* @__PURE__ */ React.createElement(SummaryCard, { label: __("Orders"), value: formatNumber(summary.orders ?? 0) }), /* @__PURE__ */ React.createElement(SummaryCard, { label: __("Produced"), value: formatNumber(summary.produced ?? 0) }), /* @__PURE__ */ React.createElement(SummaryCard, { label: __("Planned"), value: formatNumber(summary.planned ?? 0) }), /* @__PURE__ */ React.createElement(SummaryCard, { label: __("Avg execution"), value: fmtDuration(summary.avg_execution_minutes) }), /* @__PURE__ */ React.createElement(
    SummaryCard,
    {
      label: __("On-time"),
      value: summary.on_time_pct == null ? "\u2014" : `${summary.on_time_pct}%`
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-1.5" }, presets.map((p) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key: p,
      type: "button",
      onClick: () => setPreset(p),
      className: `px-3 py-1.5 rounded-om-sm text-sm font-medium border ${form.preset === p ? "bg-om-ink text-om-on-ink border-om-accent" : "bg-om-card text-om-muted border-om-line2 hover:bg-om-bg"}`
    },
    __(PRESET_LABELS[p] ?? p)
  ))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-4 flex flex-wrap items-end gap-3" }, form.preset === "custom" && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Field, { label: __("From") }, /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: form.from || null,
      onChange: (iso) => setForm((f) => ({ ...f, from: iso ?? "" })),
      className: "w-44"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: __("To") }, /* @__PURE__ */ React.createElement(
    DatePicker,
    {
      value: form.to || null,
      onChange: (iso) => setForm((f) => ({ ...f, to: iso ?? "" })),
      className: "w-44"
    }
  ))), /* @__PURE__ */ React.createElement(Field, { label: __("Status") }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.status == null ? "" : String(form.status),
      onChange: (v) => setForm((f) => ({ ...f, status: v })),
      options: [
        { value: "", label: __("All") },
        ...statusOptions.map((s) => ({ value: String(s), label: __(s) }))
      ],
      className: "w-44"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: __("Line") }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.line_id == null ? "" : String(form.line_id),
      onChange: (v) => setForm((f) => ({ ...f, line_id: v })),
      options: [
        { value: "", label: __("All") },
        ...lines.map((l) => ({ value: String(l.id), label: l.name }))
      ],
      className: "w-44"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: __("Product Type") }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.product_type_id == null ? "" : String(form.product_type_id),
      onChange: (v) => setForm((f) => ({ ...f, product_type_id: v })),
      options: [
        { value: "", label: __("All") },
        ...productTypes.map((p) => ({ value: String(p.id), label: p.name }))
      ],
      className: "w-44"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: __("Search") }, /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.search,
      onChange: (e) => setForm((f) => ({ ...f, search: e.target.value })),
      onKeyDown: (e) => e.key === "Enter" && apply(),
      placeholder: __("Order no. or LOT"),
      className: "form-input py-1.5 text-sm"
    }
  )), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: () => apply(), className: "btn-touch btn-primary" }, __("Apply")), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: clear, className: "btn-touch btn-secondary" }, __("Clear"))), /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: rows,
      columns,
      paginated: false,
      searchPlaceholder: __("Order no. or LOT"),
      emptyLabel: __("No orders match the current filters."),
      onRowClick: (r) => router.visit(`/admin/reports/${r.id}`)
    }
  ), lastPage > 1 && /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1 flex-wrap justify-center" }, links.map((link, i) => /* @__PURE__ */ React.createElement(
    "button",
    {
      key: i,
      type: "button",
      disabled: !link.url,
      onClick: () => link.url && goPage(new URL(link.url).searchParams.get("page")),
      className: `px-3 py-1 text-sm rounded border ${link.active ? "bg-om-ink text-om-on-ink border-om-accent" : link.url ? "border-om-line text-om-muted hover:bg-om-bg" : "border-om-line2 text-om-faint cursor-default"}`,
      dangerouslySetInnerHTML: { __html: link.label }
    }
  )))));
}
function SummaryCard({ label, value }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-4" }, /* @__PURE__ */ React.createElement("div", { className: "text-xs text-om-muted uppercase" }, label), /* @__PURE__ */ React.createElement("div", { className: "text-2xl font-bold text-om-ink mt-1" }, value));
}
function Field({ label, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, label), children);
}
ReportsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ReportsIndex as default
};
