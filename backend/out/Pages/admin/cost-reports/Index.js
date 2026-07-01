import { useMemo, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { DatePicker, Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { __, formatNumber } from "../../../lib/i18n";
import CostMethodology from "./CostMethodology";
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
function money(value, currency) {
  if (value == null) return "\u2014";
  return `${formatNumber(value)} ${currency}`;
}
function CostReportsIndex() {
  const { orders, summary = {}, filters = {}, lines = [], productTypes = [], presets = [], currency = "PLN" } = usePage().props;
  const [form, setForm] = useState({
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
    router.get("/admin/cost-reports", params, { preserveState: false, preserveScroll: true });
  };
  const setPreset = (preset) => {
    setForm((f) => ({ ...f, preset }));
    apply({ preset });
  };
  const clear = () => router.get("/admin/cost-reports", {}, { preserveState: false });
  const exportUrl = () => {
    const p = new URLSearchParams();
    Object.entries(form).forEach(([k, v]) => {
      if (v) p.set(k, v);
    });
    const qs = p.toString();
    return `/admin/cost-reports/export${qs ? "?" + qs : ""}`;
  };
  const goPage = (page) => apply({ page });
  const rows = orders?.data ?? [];
  const links = orders?.links ?? [];
  const lastPage = orders?.last_page ?? 1;
  const columns = useMemo(
    () => [
      {
        id: "order",
        accessorKey: "order_no",
        header: __("Order"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement(
          Link,
          {
            href: `/admin/cost-reports/${row.original.id}`,
            onClick: (e) => e.stopPropagation(),
            className: "font-medium text-om-accent"
          },
          row.original.order_no
        )
      },
      {
        id: "product",
        accessorKey: "product_name",
        header: __("Product"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.product_name ?? "\u2014")
      },
      {
        id: "line",
        accessorKey: "line_name",
        header: __("Line"),
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.line_name ?? "\u2014")
      },
      {
        id: "produced",
        accessorKey: "produced_qty",
        header: __("Produced"),
        meta: { align: "right" },
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, formatNumber(row.original.produced_qty))
      },
      {
        id: "material_cost",
        accessorKey: "material_cost",
        header: __("Material cost"),
        meta: { align: "right" },
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, money(row.original.material_cost, row.original.currency))
      },
      {
        id: "labor_cost",
        accessorKey: "labor_cost",
        header: __("Labor cost"),
        meta: { align: "right" },
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, money(row.original.labor_cost, row.original.currency))
      },
      {
        id: "additional_cost",
        accessorKey: "additional_cost",
        header: __("Additional costs"),
        meta: { align: "right" },
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, money(row.original.additional_cost, row.original.currency))
      },
      {
        id: "total_cost",
        accessorKey: "total_cost",
        header: __("Total cost"),
        meta: { align: "right" },
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono font-semibold text-om-ink" }, money(row.original.total_cost, row.original.currency))
      },
      {
        id: "cost_per_unit",
        accessorKey: "cost_per_unit",
        header: __("Cost per unit"),
        meta: { align: "right" },
        cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, row.original.cost_per_unit == null ? "\u2014" : money(row.original.cost_per_unit, row.original.currency))
      }
    ],
    []
  );
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Production Cost Report") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Production Cost Report")), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, __("Material, labor and additional cost per finished work order."))), /* @__PURE__ */ React.createElement("a", { href: exportUrl(), className: "btn-touch btn-secondary whitespace-nowrap" }, __("Export CSV"))), summary.mixed_currency && /* @__PURE__ */ React.createElement("div", { className: "rounded-om-sm bg-om-downtime-bg border border-om-line text-om-downtime text-sm px-4 py-2" }, __("Mixed currencies - totals are summed without conversion.")), summary.limited && /* @__PURE__ */ React.createElement("div", { className: "rounded-om-sm bg-om-downtime-bg border border-om-line text-om-downtime text-sm px-4 py-2" }, __("Large result set: summary totals cover the first 10000 orders. Narrow the filters for an exact total.")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 md:grid-cols-5 gap-3" }, /* @__PURE__ */ React.createElement(SummaryCard, { label: __("Total cost"), value: money(summary.total_cost ?? 0, currency) }), /* @__PURE__ */ React.createElement(SummaryCard, { label: __("Material cost"), value: money(summary.material_cost ?? 0, currency) }), /* @__PURE__ */ React.createElement(SummaryCard, { label: __("Labor cost"), value: money(summary.labor_cost ?? 0, currency) }), /* @__PURE__ */ React.createElement(SummaryCard, { label: __("Additional costs"), value: money(summary.additional_cost ?? 0, currency) }), /* @__PURE__ */ React.createElement(
    SummaryCard,
    {
      label: __("Avg cost per unit"),
      value: summary.avg_cost_per_unit == null ? "\u2014" : money(summary.avg_cost_per_unit, currency)
    }
  )), /* @__PURE__ */ React.createElement(CostMethodology, null), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-1.5" }, presets.map((p) => /* @__PURE__ */ React.createElement(
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
  ))), /* @__PURE__ */ React.createElement(Field, { label: __("Line") }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.line_id == null ? "" : String(form.line_id),
      onChange: (v) => setForm((f) => ({ ...f, line_id: v })),
      options: [
        { value: "", label: __("All") },
        ...lines.map((l) => ({ value: String(l.id), label: l.name }))
      ],
      className: "w-full"
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
      className: "w-full"
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
      searchable: false,
      paginated: false,
      emptyLabel: __("No orders match the current filters."),
      onRowClick: (r) => router.visit(`/admin/cost-reports/${r.id}`)
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
CostReportsIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  CostReportsIndex as default
};
