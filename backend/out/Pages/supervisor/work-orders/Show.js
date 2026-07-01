import { useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Button, StatusPill } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
import { formatDate, formatNumber } from "../../../lib/i18n";
const TERMINAL = ["DONE", "REJECTED", "CANCELLED"];
const WO_PILL_STATUS = {
  PENDING: "pending",
  ACCEPTED: "pending",
  IN_PROGRESS: "running",
  PAUSED: "downtime",
  BLOCKED: "blocked",
  DONE: "done",
  REJECTED: "blocked",
  CANCELLED: "done"
};
const BATCH_PILL_STATUS = {
  PENDING: "pending",
  IN_PROGRESS: "running",
  DONE: "done"
};
const STEP_STATUS_STYLES = {
  DONE: "bg-om-running-bg text-om-running",
  IN_PROGRESS: "bg-om-selected text-om-accent"
};
const ISSUE_PILL_STATUS = {
  OPEN: "blocked",
  ACKNOWLEDGED: "downtime",
  RESOLVED: "running"
};
const LINK_GHOST = "inline-flex items-center justify-center gap-2 text-[13px] font-semibold rounded-om-sm border border-om-line px-4 py-[9px] text-om-ink hover:bg-om-chip transition-colors";
function fmtQty(n) {
  return formatNumber(Number(n ?? 0), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtDate(d) {
  if (!d) return null;
  const dt = new Date(d);
  if (Number.isNaN(dt.getTime())) return d;
  return formatDate(dt, { day: "2-digit", month: "short", year: "numeric" });
}
function fmtDateTime(d) {
  if (!d) return null;
  const dt = new Date(d);
  if (Number.isNaN(dt.getTime())) return d;
  return formatDate(dt, { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
}
function BatchRow({ batch, processSnapshot }) {
  const [open, setOpen] = useState(batch.is_first ?? false);
  const snapshotSteps = {};
  if (processSnapshot && Array.isArray(processSnapshot.steps)) {
    processSnapshot.steps.forEach((s) => {
      snapshotSteps[s.step_number] = s.estimated_duration_minutes ?? null;
    });
  }
  return /* @__PURE__ */ React.createElement("div", { className: "border border-om-line rounded-om p-3" }, /* @__PURE__ */ React.createElement(
    "div",
    {
      className: "flex items-center justify-between cursor-pointer",
      onClick: () => setOpen((o) => !o)
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("span", { className: "font-semibold text-om-ink" }, "Batch #", batch.batch_number), /* @__PURE__ */ React.createElement(
      StatusPill,
      {
        status: BATCH_PILL_STATUS[batch.status] ?? "pending",
        label: batch.status.replace("_", " ")
      }
    ), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[12px] text-om-muted" }, fmtQty(batch.produced_qty), " / ", fmtQty(batch.target_qty))),
    /* @__PURE__ */ React.createElement(
      "svg",
      {
        className: `w-4 h-4 text-om-faint transition-transform ${open ? "rotate-180" : ""}`,
        fill: "none",
        stroke: "currentColor",
        viewBox: "0 0 24 24"
      },
      /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 9l-7 7-7-7" })
    )
  ), open && /* @__PURE__ */ React.createElement("div", { className: "mt-3 space-y-1" }, (batch.steps ?? []).map((step) => {
    const stepStyle = STEP_STATUS_STYLES[step.status] ?? "bg-om-chip text-om-faint";
    const estimated = snapshotSteps[step.step_number] ?? null;
    const overTime = estimated && step.duration_minutes != null && step.duration_minutes > estimated;
    return /* @__PURE__ */ React.createElement("div", { key: step.id, className: "flex items-center gap-3 py-1.5 px-2 rounded-om-sm text-sm hover:bg-om-bg" }, /* @__PURE__ */ React.createElement("span", { className: `w-5 h-5 rounded-full flex items-center justify-center font-mono text-xs flex-shrink-0 ${stepStyle}` }, step.step_number), /* @__PURE__ */ React.createElement("span", { className: "flex-1 text-om-ink" }, step.name), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, step.status.replace("_", " ")), step.duration_minutes != null ? /* @__PURE__ */ React.createElement("span", { className: `font-mono text-xs font-medium ${overTime ? "text-om-blocked" : "text-om-running"}` }, step.duration_minutes, "min", estimated ? ` / est. ${estimated}min` : "") : estimated ? /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs text-om-faint" }, "est. ", estimated, "min") : null);
  }), batch.started_at && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint pt-1" }, "Started: ", fmtDateTime(batch.started_at), batch.completed_at ? ` \xB7 Completed: ${fmtDateTime(batch.completed_at)}` : "")));
}
function DoneModal({ workOrder, onClose }) {
  const [qty, setQty] = useState(String(workOrder.planned_qty ?? ""));
  function handleSubmit(e) {
    e.preventDefault();
    router.post(`/supervisor/work-orders/${workOrder.id}/complete`, { produced_qty: qty }, { preserveScroll: true });
    onClose();
  }
  return /* @__PURE__ */ React.createElement("div", { className: "fixed inset-0 z-50 flex items-center justify-center bg-black/50" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om shadow-xl p-6 w-full max-w-md mx-4" }, /* @__PURE__ */ React.createElement("h3", { className: "text-[16px] font-semibold text-om-ink mb-4" }, __("Complete Work Order")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-4" }, "Enter the produced quantity for ", /* @__PURE__ */ React.createElement("strong", { className: "font-mono text-om-ink" }, workOrder.order_no), "."), /* @__PURE__ */ React.createElement("form", { onSubmit: handleSubmit }, /* @__PURE__ */ React.createElement("div", { className: "mb-4" }, /* @__PURE__ */ React.createElement("label", { className: "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1.5" }, __("Produced Quantity")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      step: "0.01",
      min: "0.01",
      max: workOrder.planned_qty * 2,
      value: qty,
      onChange: (e) => setQty(e.target.value),
      className: "w-full rounded-om-sm border border-om-line bg-om-card px-3 py-2 font-mono text-sm text-om-ink focus:outline-none focus:border-om-accent",
      required: true
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mt-1.5" }, __("Planned:"), " ", /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, fmtQty(workOrder.planned_qty)))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-end gap-2" }, /* @__PURE__ */ React.createElement(Button, { type: "button", variant: "secondary", onClick: onClose }, "Cancel"), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent" }, "Mark as Done")))));
}
function SupervisorWorkOrderShow() {
  const { workOrder } = usePage().props;
  const [showDoneModal, setShowDoneModal] = useState(false);
  const post = (verb) => router.post(`/supervisor/work-orders/${workOrder.id}/${verb}`, {}, { preserveScroll: true });
  const status = workOrder.status;
  const isTerminal = TERMINAL.includes(status);
  const pct = workOrder.planned_qty > 0 ? Math.min(workOrder.produced_qty / workOrder.planned_qty * 100, 100) : 0;
  const isDuePast = workOrder.due_date && new Date(workOrder.due_date) < /* @__PURE__ */ new Date() && status !== "DONE";
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Work Order :no", { no: workOrder.order_no }) }), /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("h1", { className: "font-mono text-[26px] font-medium tracking-[-0.02em] text-om-ink" }, workOrder.order_no), /* @__PURE__ */ React.createElement(StatusPill, { status: WO_PILL_STATUS[status] ?? "pending", label: status })), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1" }, "Created ", timeAgo(workOrder.created_at), workOrder.product_type_name ? ` \xB7 ${workOrder.product_type_name}` : "")), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-2" }, status === "PENDING" && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Button, { variant: "primary", onClick: () => post("accept") }, "Accept"), /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "danger",
      onClick: () => {
        if (confirm("Reject this work order?")) post("reject");
      }
    },
    "Reject"
  )), status === "ACCEPTED" && /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "danger",
      onClick: () => {
        if (confirm("Reject this work order?")) post("reject");
      }
    },
    "Reject"
  ), status === "IN_PROGRESS" && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: () => post("pause") }, "Pause"), /* @__PURE__ */ React.createElement(Button, { variant: "accent", onClick: () => setShowDoneModal(true) }, "Done")), status === "PAUSED" && /* @__PURE__ */ React.createElement(Button, { variant: "primary", onClick: () => post("resume") }, "Resume"), isTerminal ? /* @__PURE__ */ React.createElement(
    Button,
    {
      variant: "primary",
      onClick: () => {
        if (confirm("Reopen this work order?")) post("reopen");
      }
    },
    "Reopen"
  ) : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/supervisor/work-orders/${workOrder.id}/edit`,
      className: LINK_GHOST
    },
    "Edit"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => {
        if (confirm("Cancel this work order?")) post("cancel");
      },
      className: "inline-flex items-center justify-center gap-2 text-[13px] font-semibold rounded-om-sm border border-om-line px-4 py-[9px] text-om-accent hover:bg-om-chip transition-colors cursor-pointer"
    },
    "Cancel"
  )), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/supervisor/work-orders",
      className: LINK_GHOST
    },
    "\u2190 Back"
  ))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-3 gap-6" }, /* @__PURE__ */ React.createElement("div", { className: "lg:col-span-2 space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink mb-4" }, __("Details")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 md:grid-cols-3 gap-4 text-sm" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Order Number")), /* @__PURE__ */ React.createElement("p", { className: "font-mono font-medium text-om-ink" }, workOrder.order_no)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Line")), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.line_name ?? "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Product Type")), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.product_type_name ?? "\u2014")), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Planned Qty")), /* @__PURE__ */ React.createElement("p", { className: "font-mono font-medium text-om-ink" }, fmtQty(workOrder.planned_qty))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Produced Qty")), /* @__PURE__ */ React.createElement("p", { className: "font-mono font-medium text-om-ink" }, fmtQty(workOrder.produced_qty))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Priority")), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.priority ?? "\u2014")), workOrder.due_date && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Due Date")), /* @__PURE__ */ React.createElement("p", { className: `font-medium ${isDuePast ? "text-om-blocked" : "text-om-ink"}` }, fmtDate(workOrder.due_date))), workOrder.description && /* @__PURE__ */ React.createElement("div", { className: "col-span-2 md:col-span-3" }, /* @__PURE__ */ React.createElement("p", { className: "mb-1 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Description")), /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, workOrder.description)))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("h2", { className: "text-[14px] font-semibold text-om-ink mb-4" }, "Batches", " ", /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[12px] font-normal text-om-faint" }, "(", workOrder.batches.length, ")")), workOrder.batches.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint py-4 text-center" }, __("No batches yet.")) : /* @__PURE__ */ React.createElement("div", { className: "space-y-3" }, workOrder.batches.map((batch, i) => /* @__PURE__ */ React.createElement(
    BatchRow,
    {
      key: batch.id,
      batch: { ...batch, is_first: i === 0 },
      processSnapshot: workOrder.process_snapshot
    }
  ))))), /* @__PURE__ */ React.createElement("div", { className: "space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("h3", { className: "text-[14px] font-semibold text-om-ink mb-3" }, __("Progress")), /* @__PURE__ */ React.createElement("div", { className: "mb-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-baseline text-sm mb-1.5" }, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint" }, __("Completion")), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[12px] text-om-ink" }, pct.toFixed(1), "%")), /* @__PURE__ */ React.createElement("div", { className: "w-full bg-om-chip rounded-[20px] h-[7px] overflow-hidden" }, /* @__PURE__ */ React.createElement(
    "div",
    {
      className: `h-[7px] rounded-[20px] ${pct >= 100 ? "bg-om-running" : "bg-om-accent"}`,
      style: { width: `${pct}%` }
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "space-y-1 text-sm" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Planned:")), /* @__PURE__ */ React.createElement("span", { className: "font-mono font-medium text-om-ink" }, fmtQty(workOrder.planned_qty))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Produced:")), /* @__PURE__ */ React.createElement("span", { className: "font-mono font-medium text-om-ink" }, fmtQty(workOrder.produced_qty))), /* @__PURE__ */ React.createElement("div", { className: "flex justify-between" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted" }, __("Batches:")), /* @__PURE__ */ React.createElement("span", { className: "font-mono font-medium text-om-ink" }, workOrder.batches.length)))), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card border border-om-line rounded-om p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center mb-3" }, /* @__PURE__ */ React.createElement("h3", { className: "text-[14px] font-semibold text-om-ink" }, __("Issues")), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/supervisor/issues",
      className: "text-xs text-om-accent hover:underline"
    },
    __("Manage \u2192")
  )), workOrder.issues.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint text-center py-3" }, __("No issues.")) : /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, workOrder.issues.map((issue) => {
    const isBlocking = ["OPEN", "ACKNOWLEDGED"].includes(issue.status) && issue.is_blocking;
    return /* @__PURE__ */ React.createElement(
      "div",
      {
        key: issue.id,
        className: `p-2.5 rounded-om-sm text-xs ${isBlocking ? "bg-om-blocked-bg" : "bg-om-panel"}`
      },
      /* @__PURE__ */ React.createElement("div", { className: "flex justify-between items-center gap-2" }, /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, issue.issue_type_name), /* @__PURE__ */ React.createElement(
        StatusPill,
        {
          status: ISSUE_PILL_STATUS[issue.status] ?? "pending",
          label: issue.status
        }
      )),
      /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-1 truncate" }, issue.title)
    );
  })))))), showDoneModal && /* @__PURE__ */ React.createElement(DoneModal, { workOrder, onClose: () => setShowDoneModal(false) }));
}
SupervisorWorkOrderShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  SupervisorWorkOrderShow as default
};
