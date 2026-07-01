import { useState, useMemo } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Dropdown } from "@openmes/ui";
import { DataTable } from "@openmes/ui/table";
import AppLayout from "../../../layouts/AppLayout";
import { formatNumber, __ } from "../../../lib/i18n";
const STATUS_STYLES = {
  pending: "bg-om-downtime-bg text-om-downtime",
  processed: "bg-om-running-bg text-om-running",
  dismissed: "bg-om-chip text-om-muted",
  draft: "bg-om-chip text-om-muted"
};
function deviation(planned, actual) {
  const p = parseFloat(planned);
  if (!p) return 0;
  return (parseFloat(actual) - p) / p * 100;
}
function fmt(n, decimals = 2) {
  return formatNumber(Number(n), {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  });
}
function ProductionAnomaliesIndex() {
  const { anomalies, filters = {}, workOrders = [] } = usePage().props;
  const { data: rows = [], links = [], meta = {} } = anomalies ?? {};
  const [workOrderId, setWorkOrderId] = useState(filters.work_order_id ?? "");
  const [status, setStatus] = useState(filters.status ?? "");
  function applyFilter(e) {
    e.preventDefault();
    router.get("/admin/production-anomalies", { work_order_id: workOrderId, status }, { preserveState: true });
  }
  function resetFilter() {
    setWorkOrderId("");
    setStatus("");
    router.get("/admin/production-anomalies", {}, { preserveState: true });
  }
  function handleProcess(id) {
    router.post(`/admin/production-anomalies/${id}/process`, {}, { preserveScroll: true });
  }
  function handleDelete(id) {
    if (confirm(__("Delete this anomaly record?"))) {
      router.delete(`/admin/production-anomalies/${id}`, { preserveScroll: true });
    }
  }
  const columns = useMemo(() => [
    {
      id: "work_order",
      accessorFn: (r) => r.work_order?.order_no ?? "",
      header: __("Work Order"),
      cell: ({ row }) => {
        const anomaly = row.original;
        return anomaly.work_order ? /* @__PURE__ */ React.createElement(
          "a",
          {
            href: `/admin/work-orders/${anomaly.work_order.id}`,
            className: "inline-flex items-center font-mono text-sm font-semibold text-om-accent bg-om-chip border border-om-line rounded px-2 py-0.5 hover:bg-om-chip hover:border-om-line transition-colors"
          },
          anomaly.work_order.order_no
        ) : /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "\u2014");
      }
    },
    {
      id: "product_name",
      accessorKey: "product_name",
      header: __("Product"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-ink" }, row.original.product_name)
    },
    {
      id: "planned_qty",
      accessorKey: "planned_qty",
      header: __("Planned Qty"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, fmt(row.original.planned_qty))
    },
    {
      id: "actual_qty",
      accessorKey: "actual_qty",
      header: __("Actual Qty"),
      meta: { align: "right" },
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, fmt(row.original.actual_qty))
    },
    {
      id: "deviation",
      accessorFn: (r) => deviation(r.planned_qty, r.actual_qty),
      header: __("Deviation"),
      meta: { align: "right" },
      cell: ({ row }) => {
        const dev = deviation(row.original.planned_qty, row.original.actual_qty);
        const devPositive = dev >= 0;
        return /* @__PURE__ */ React.createElement("span", { className: `font-medium ${devPositive ? "text-om-running" : "text-om-blocked"}` }, devPositive ? "+" : "", fmt(dev, 1), "%");
      }
    },
    {
      id: "reason",
      accessorFn: (r) => r.anomaly_reason?.name ?? "\u2014",
      header: __("Reason"),
      cell: ({ row }) => /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, row.original.anomaly_reason?.name ?? "\u2014")
    },
    {
      id: "status",
      accessorKey: "status",
      header: __("Status"),
      cell: ({ row }) => {
        const anomaly = row.original;
        return /* @__PURE__ */ React.createElement("span", { className: `px-2 py-1 rounded-full text-xs font-medium ${STATUS_STYLES[anomaly.status] ?? STATUS_STYLES.dismissed}` }, anomaly.status ? anomaly.status.charAt(0).toUpperCase() + anomaly.status.slice(1) : "\u2014");
      }
    },
    {
      id: "actions",
      header: __("Actions"),
      enableSorting: false,
      meta: { align: "right" },
      cell: ({ row }) => {
        const anomaly = row.original;
        return /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-end gap-2" }, anomaly.status === "pending" && /* @__PURE__ */ React.createElement(
          "button",
          {
            onClick: () => handleProcess(anomaly.id),
            title: __("Process"),
            className: "text-om-running hover:text-om-running p-1"
          },
          /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" }))
        ), /* @__PURE__ */ React.createElement(
          "button",
          {
            onClick: () => handleDelete(anomaly.id),
            title: __("Delete"),
            className: "text-om-blocked hover:text-om-blocked p-1"
          },
          /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" }))
        ));
      }
    }
  ], []);
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Production Anomalies") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-6" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Production Anomalies")), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/admin/production-anomalies/create",
      className: "btn-touch btn-primary"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5 inline-block mr-2", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M12 4v16m8-8H4" })),
    __("Record Anomaly")
  )), /* @__PURE__ */ React.createElement("div", { className: "card mb-4" }, /* @__PURE__ */ React.createElement("form", { onSubmit: applyFilter, className: "flex flex-wrap gap-3 items-end" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Work Order")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      className: "w-full",
      options: [{ value: "", label: __("All work orders") }, ...workOrders.map((wo) => ({ value: String(wo.id), label: wo.order_no }))],
      value: workOrderId == null ? "" : String(workOrderId),
      onChange: (v) => setWorkOrderId(v)
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "form-label" }, __("Status")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      className: "w-full",
      options: [
        { value: "", label: __("All statuses") },
        { value: "pending", label: __("Pending") },
        { value: "processed", label: __("Processed") },
        { value: "dismissed", label: __("Dismissed") }
      ],
      value: status,
      onChange: (v) => setStatus(v)
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement("button", { type: "submit", className: "btn-touch btn-primary" }, __("Filter")), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: resetFilter, className: "btn-touch btn-secondary" }, __("Reset"))))), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement(
    DataTable,
    {
      data: rows,
      columns,
      searchable: true,
      columnToggle: true,
      paginated: true,
      searchPlaceholder: "Search anomalies\u2026",
      emptyLabel: __("No anomalies recorded")
    }
  ), links && links.length > 3 && /* @__PURE__ */ React.createElement("div", { className: "mt-4 px-4 flex flex-wrap gap-1" }, links.map((link, i) => link.url ? /* @__PURE__ */ React.createElement(
    "button",
    {
      key: i,
      onClick: () => router.get(link.url, {}, { preserveState: true }),
      className: `px-3 py-1 rounded text-sm border ${link.active ? "bg-om-ink text-om-on-ink border-om-accent" : "border-om-line text-om-muted hover:bg-om-bg"}`,
      dangerouslySetInnerHTML: { __html: link.label }
    }
  ) : /* @__PURE__ */ React.createElement(
    "span",
    {
      key: i,
      className: "px-3 py-1 rounded text-sm border border-om-line2 text-om-faint",
      dangerouslySetInnerHTML: { __html: link.label }
    }
  ))))));
}
ProductionAnomaliesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ProductionAnomaliesIndex as default
};
