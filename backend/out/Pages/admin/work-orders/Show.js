import { useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import CustomFieldsDisplay from "../../../components/CustomFieldsDisplay";
import { WO_STATUS_STYLES } from "./fields";
import { formatDate, formatNumber, timeAgo, __ } from "../../../lib/i18n";
const TERMINAL = ["DONE", "REJECTED", "CANCELLED"];
const BATCH_STATUS_STYLES = {
  PENDING: "bg-om-chip text-om-muted",
  IN_PROGRESS: "bg-om-chip text-om-accent",
  DONE: "bg-om-running-bg text-om-running"
};
const STEP_STATUS_STYLES = {
  DONE: "bg-om-running-bg text-om-running",
  IN_PROGRESS: "bg-om-chip text-om-accent"
};
const ISSUE_STATUS_STYLES = {
  OPEN: "bg-om-blocked-bg text-om-blocked",
  ACKNOWLEDGED: "bg-om-downtime-bg text-om-downtime",
  RESOLVED: "bg-om-running-bg text-om-running"
};
function fmtQty(n) {
  return formatNumber(Number(n ?? 0), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtDate(d) {
  if (!d) return null;
  const dt = new Date(d);
  if (Number.isNaN(dt.getTime())) return d;
  return formatDate(dt, { day: "2-digit", month: "short", year: "numeric" });
}
function BatchRow({ batch }) {
  const [open, setOpen] = useState(batch.is_first ?? false);
  const batchStyle = BATCH_STATUS_STYLES[batch.status] ?? "bg-om-chip text-om-faint";
  return /* @__PURE__ */ React.createElement("div", { className: "border border-om-line2 rounded-om-sm p-3" }, /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "flex items-center justify-between cursor-pointer",
      onClick: () => setOpen((o) => !o)
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("span", { className: "font-semibold text-om-muted" }, "Batch #", batch.batch_number), /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded text-xs font-medium ${batchStyle}` }, __(batch.status)), /* @__PURE__ */ React.createElement("span", { className: "text-sm text-om-muted" }, fmtQty(batch.produced_qty), " / ", fmtQty(batch.target_qty))),
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2", onClick: (e) => e.stopPropagation() }, /* @__PURE__ */ React.createElement(
      "svg",
      {
        className: `w-4 h-4 text-om-faint transition-transform ${open ? "rotate-180" : ""}`,
        fill: "none",
        stroke: "currentColor",
        viewBox: "0 0 24 24"
      },
      /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 9l-7 7-7-7" })
    ))
  ), open && /* @__PURE__ */ React.createElement("div", { className: "mt-3 space-y-1" }, (batch.steps ?? []).map((step) => {
    const stepStyle = STEP_STATUS_STYLES[step.status] ?? "bg-om-chip text-om-muted";
    const estimated = step.estimated_duration_minutes ?? null;
    const overTime = estimated && step.duration_minutes != null && step.duration_minutes > estimated;
    return /* @__PURE__ */ React.createElement("div", { key: step.id, className: "flex items-center gap-3 py-1 px-2 rounded text-sm" }, /* @__PURE__ */ React.createElement("span", { className: `w-5 h-5 rounded-full flex items-center justify-center text-xs flex-shrink-0 ${stepStyle}` }, step.step_number), /* @__PURE__ */ React.createElement("span", { className: "flex-1 text-om-muted" }, step.name), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint" }, step.status.replace("_", " ")), step.duration_minutes != null ? /* @__PURE__ */ React.createElement("span", { className: `text-xs font-medium ${overTime ? "text-om-blocked" : "text-om-running"}` }, step.duration_minutes, "min", estimated ? ` / est. ${estimated}min` : "") : estimated ? /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint" }, "est. ", estimated, "min") : null);
  }), batch.started_at && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint pt-1" }, "Started: ", fmtDate(batch.started_at), batch.completed_at ? ` \xB7 Completed: ${fmtDate(batch.completed_at)}` : "")));
}
function DoneModal({ workOrder, onClose }) {
  const [qty, setQty] = useState(String(workOrder.planned_qty ?? ""));
  function handleSubmit(e) {
    e.preventDefault();
    router.post(`/admin/work-orders/${workOrder.id}/complete`, { produced_qty: qty }, { preserveScroll: true });
    onClose();
  }
  return /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 z-50 flex items-center justify-center bg-black/50" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-xl p-6 w-full max-w-md mx-4" }, /* @__PURE__ */ React.createElement("h3", { className: "text-lg font-bold text-om-ink mb-4" }, __("Complete Work Order")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-4" }, "Enter the produced quantity for ", /* @__PURE__ */ React.createElement("strong", null, workOrder.order_no), "."), /* @__PURE__ */ React.createElement("form", { onSubmit: handleSubmit }, /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Produced Quantity")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      step: "0.01",
      min: "0.01",
      max: workOrder.planned_qty * 2,
      value: qty,
      onChange: (e) => setQty(e.target.value),
      className: "w-full border border-om-line rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-om-accent",
      required: true
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-1" }, "Planned: ", fmtQty(workOrder.planned_qty))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-end gap-2" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: onClose,
      className: "px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
    },
    "Cancel"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "submit",
      className: "px-4 py-2 text-sm font-medium text-white bg-om-running border border-transparent rounded-md hover:brightness-95"
    },
    "Mark as Done"
  )))));
}
function AdminWorkOrderShow() {
  const { workOrder, customFields = [] } = usePage().props;
  const [showDoneModal, setShowDoneModal] = useState(false);
  const post = (verb) => router.post(`/admin/work-orders/${workOrder.id}/${verb}`, {}, { preserveScroll: true });
  const status = workOrder.status;
  const isTerminal = TERMINAL.includes(status);
  const pct = workOrder.planned_qty > 0 ? Math.min(workOrder.produced_qty / workOrder.planned_qty * 100, 100) : 0;
  const isDuePast = workOrder.due_date && new Date(workOrder.due_date) < /* @__PURE__ */ new Date() && status !== "DONE";
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Work Order :no", { no: workOrder.order_no }) }), /* @__PURE__ */ React.createElement("nav", { className: "flex items-center gap-2 text-sm text-om-muted mb-4" }, /* @__PURE__ */ React.createElement(Link, { href: "/admin/dashboard", className: "hover:text-om-ink" }, __("Dashboard")), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/work-orders", className: "hover:text-om-ink" }, __("Work Orders")), /* @__PURE__ */ React.createElement("span", null, "/"), /* @__PURE__ */ React.createElement("span", { className: "text-om-muted font-medium" }, "#", workOrder.order_no)), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 flex-wrap" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink font-mono" }, workOrder.order_no), /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded text-xs font-semibold ${WO_STATUS_STYLES[status] ?? "bg-om-chip text-om-muted"}` }, status)), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, "Created ", timeAgo(workOrder.created_at), workOrder.product_type_name ? ` \xB7 ${workOrder.product_type_name}` : "")), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-2" }, status === "PENDING" && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => post("accept"),
      className: "px-4 py-2 text-sm font-medium text-om-on-ink bg-om-ink rounded-md hover:bg-om-ink-hover"
    },
    "Accept"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => {
        if (confirm("Reject this work order?")) post("reject");
      },
      className: "px-4 py-2 text-sm font-medium text-om-blocked bg-om-card border border-om-line rounded-md hover:bg-om-bg"
    },
    "Reject"
  )), status === "ACCEPTED" && /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => {
        if (confirm("Reject this work order?")) post("reject");
      },
      className: "px-4 py-2 text-sm font-medium text-om-blocked bg-om-card border border-om-line rounded-md hover:bg-om-bg"
    },
    "Reject"
  ), status === "IN_PROGRESS" && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => post("pause"),
      className: "px-4 py-2 text-sm font-medium text-om-downtime bg-om-card border border-om-line rounded-md hover:bg-om-bg"
    },
    "Pause"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => setShowDoneModal(true),
      className: "px-4 py-2 text-sm font-medium text-white bg-om-running rounded-md hover:brightness-95"
    },
    "Done"
  )), status === "PAUSED" && /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => post("resume"),
      className: "px-4 py-2 text-sm font-medium text-om-on-ink bg-om-ink rounded-md hover:bg-om-ink-hover"
    },
    "Resume"
  ), isTerminal ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => {
        if (confirm("Reopen this work order?")) post("reopen");
      },
      className: "px-4 py-2 text-sm font-medium text-om-on-ink bg-om-ink rounded-md hover:bg-om-ink-hover"
    },
    "Reopen"
  ), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/work-orders/${workOrder.id}/edit`,
      className: "px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
    },
    "Edit"
  )) : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/work-orders/${workOrder.id}/edit`,
      className: "px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
    },
    "Edit"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => {
        if (confirm("Cancel this work order?")) post("cancel");
      },
      className: "px-4 py-2 text-sm font-medium text-om-accent bg-om-card border border-om-line rounded-md hover:bg-om-bg"
    },
    "Cancel"
  )), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/admin/work-orders",
      className: "px-4 py-2 text-sm font-medium text-om-muted bg-om-card border border-om-line rounded-md hover:bg-om-bg"
    },
    "\u2190 Back"
  ))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-3 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-2 space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink mb-4" }, __("Details")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 md:grid-cols-3 gap-4 text-sm" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("Order Number")), /* @__PURE__ */ React.createElement("p", { className: "font-mono font-semibold text-om-ink" }, workOrder.order_no)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("Line")), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.line_name ?? "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("Product Type")), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.product_type_name ?? "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("Planned Qty")), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, fmtQty(workOrder.planned_qty))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("Produced Qty")), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, fmtQty(workOrder.produced_qty))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("Priority")), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.priority ?? "\u2014")), workOrder.due_date && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("Due Date")), /* @__PURE__ */ React.createElement("p", { className: `font-medium ${isDuePast ? "text-om-blocked" : "text-om-ink"}` }, fmtDate(workOrder.due_date))), workOrder.description && /* @__PURE__ */ React.createElement("div", { className: "col-span-2 md:col-span-3" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, __("Description")), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.description)), workOrder.extra_data && Object.keys(workOrder.extra_data).length > 0 && /* @__PURE__ */ React.createElement("div", { className: "col-span-2 md:col-span-3" }, /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mb-1" }, __("Extra Data")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-2" }, Object.entries(workOrder.extra_data).map(([k, v]) => /* @__PURE__ */ React.createElement("div", { key: k, className: "bg-om-panel rounded px-2 py-1" }, /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint" }, k), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted font-medium" }, String(v)))))))), /* @__PURE__ */ React.createElement(CustomFieldsDisplay, { definitions: customFields, values: workOrder.custom_fields ?? {} }), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-bold text-om-ink mb-4" }, "Batches", " ", /* @__PURE__ */ React.createElement("span", { className: "text-sm font-normal text-om-faint" }, "(", workOrder.batches.length, ")")), workOrder.batches.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint py-4 text-center" }, __("No batches yet.")) : /* @__PURE__ */ React.createElement("div", { className: "space-y-3" }, workOrder.batches.map((batch, i) => /* @__PURE__ */ React.createElement(BatchRow, { key: batch.id, batch: { ...batch, is_first: i === 0 } }))))), /* @__PURE__ */ React.createElement("div", { className: "space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5" }, /* @__PURE__ */ React.createElement("h3", { className: "text-base font-bold text-om-ink mb-3" }, __("Progress")), /* @__PURE__ */ React.createElement("div", { className: "mb-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between text-sm text-om-muted mb-1" }, /* @__PURE__ */ React.createElement("span", null, __("Completion")), /* @__PURE__ */ React.createElement("span", null, pct.toFixed(1), "%")), /* @__PURE__ */ React.createElement("div", { className: "w-full bg-om-line2 rounded-full h-3" }, /* @__PURE__ */ React.createElement(
    "div",
    {
      className: `h-3 rounded-full ${pct >= 100 ? "bg-om-running" : "bg-om-ink"}`,
      style: { width: `${pct}%` }
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "space-y-1 text-sm" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Planned:")), /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, fmtQty(workOrder.planned_qty))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Produced:")), /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, fmtQty(workOrder.produced_qty))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Batches:")), /* @__PURE__ */ React.createElement("span", { className: "font-medium" }, workOrder.batches.length)))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm border border-om-line2 p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-3" }, /* @__PURE__ */ React.createElement("h3", { className: "text-base font-bold text-om-ink" }, __("Issues")), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/issues?search=${encodeURIComponent(workOrder.order_no)}`,
      className: "text-xs text-om-accent hover:underline"
    },
    __("Manage \u2192")
  )), workOrder.issues.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint text-center py-3" }, __("No issues.")) : /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, workOrder.issues.map((issue) => {
    const isBlocking = ["OPEN", "ACKNOWLEDGED"].includes(issue.status) && issue.is_blocking;
    const issueStatusStyle = ISSUE_STATUS_STYLES[issue.status] ?? "bg-om-chip text-om-muted";
    return /* @__PURE__ */ React.createElement(
      Link,
      {
        key: issue.id,
        href: `/admin/issues?search=${encodeURIComponent(workOrder.order_no)}`,
        className: `block p-2 rounded-om-sm text-xs transition hover:ring-1 hover:ring-blue-300 ${isBlocking ? "bg-om-blocked-bg" : "bg-om-panel"}`
      },
      /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, issue.issue_type_name), /* @__PURE__ */ React.createElement("span", { className: `px-1.5 py-0.5 rounded text-xs ${issueStatusStyle}` }, __(issue.status))),
      /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 truncate" }, issue.title)
    );
  })))))), showDoneModal && /* @__PURE__ */ React.createElement(DoneModal, { workOrder, onClose: () => setShowDoneModal(false) }));
}
AdminWorkOrderShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  AdminWorkOrderShow as default
};
