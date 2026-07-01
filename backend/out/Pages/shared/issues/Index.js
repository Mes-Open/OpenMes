import { useEffect, useState } from "react";
import { Head, router, useForm, usePage } from "@inertiajs/react";
import { Button, IconButton, Dropdown, DatePicker, TextField, StatusPill, Modal, InlineAlert, ConfirmDialog } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
import ResourceTable from "../../../components/ResourceTable";
import { __ } from "../../../lib/i18n";
const STATUS_STYLES = {
  OPEN: "bg-om-blocked-bg text-om-blocked",
  ACKNOWLEDGED: "bg-om-downtime-bg text-om-downtime",
  RESOLVED: "bg-om-running-bg text-om-running",
  CLOSED: "bg-om-line2 text-om-muted"
};
const DISPOSITION_STYLES = {
  pending: "bg-om-downtime-bg text-om-downtime",
  scrap: "bg-om-blocked-bg text-om-blocked",
  rework: "bg-om-chip text-om-accent",
  return_to_supplier: "bg-om-chip text-om-accent",
  use_as_is: "bg-om-running-bg text-om-running"
};
const DISPOSITION_LABELS = {
  pending: "Pending",
  scrap: "Scrap",
  rework: "Rework",
  return_to_supplier: "Return to supplier",
  use_as_is: "Use as is"
};
const NC_SOURCE_LABELS = { internal: "Internal", external: "External", supplier: "Supplier" };
const ACTION_STATUS = {
  open: { tone: "pending", label: __("Open") },
  in_progress: { tone: "downtime", label: __("In progress") },
  done: { tone: "running", label: __("Done") },
  verified: { tone: "done", label: __("Verified") }
};
const ACTION_TYPE_LABELS = { corrective: "Corrective", preventive: "Preventive", containment: "Containment" };
function IssuesIndex() {
  const {
    issueTypeNames = {},
    lineNames = {},
    reporterNames = {},
    workOrderNos = {},
    csrf_token: csrf
  } = usePage().props;
  const base = typeof window !== "undefined" && window.location.pathname.startsWith("/admin") ? "/admin" : "/supervisor";
  const post = (id, verb, data = {}) => router.post(`${base}/issues/${id}/${verb}`, data, { preserveScroll: true });
  const [actionsFor, setActionsFor] = useState(null);
  const [dispositionFor, setDispositionFor] = useState(null);
  const columns = [
    { key: "title", label: __("Issue"), className: "font-medium text-om-ink" },
    { key: "type", label: __("Type"), className: "text-om-muted", render: (r) => issueTypeNames[r.issue_type_id] ?? "\u2014" },
    { key: "wo", label: __("Work Order"), className: "text-om-muted", render: (r) => workOrderNos[r.work_order_id] ?? "\u2014" },
    { key: "reporter", label: __("Reported by"), className: "text-om-muted", render: (r) => reporterNames[r.reported_by_id] ?? "\u2014" },
    { key: "reported_at", label: __("Reported"), className: "text-om-muted", render: (r) => r.reported_at ? r.reported_at.slice(0, 16).replace("T", " ") : "\u2014" },
    {
      key: "status",
      label: __("Status"),
      render: (r) => /* @__PURE__ */ React.createElement("span", { className: `text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? "bg-om-chip text-om-muted"}` }, __(r.status))
    },
    {
      key: "disposition",
      label: __("Disposition"),
      filter: true,
      allLabel: __("All dispositions"),
      options: Object.keys(DISPOSITION_LABELS).map((value) => ({ value, label: __(DISPOSITION_LABELS[value]) })),
      render: (r) => /* @__PURE__ */ React.createElement("span", { className: `text-xs px-2 py-0.5 rounded-full font-medium ${DISPOSITION_STYLES[r.disposition] ?? "bg-om-chip text-om-muted"}` }, __(DISPOSITION_LABELS[r.disposition] ?? r.disposition ?? "Pending"))
    }
  ];
  const resolveAction = (r) => ({
    label: __("Resolve"),
    onClick: () => {
      const notes = prompt(__("Resolution notes:"));
      if (notes !== null) post(r.id, "resolve", { resolution_notes: notes });
    }
  });
  const actions = (r) => {
    const list = [
      { label: __("Disposition"), onClick: () => setDispositionFor(r) },
      { label: __("Actions"), onClick: () => setActionsFor(r) }
    ];
    const s = r.status;
    if (s === "OPEN") {
      list.push({ label: __("Acknowledge"), onClick: () => post(r.id, "acknowledge") }, resolveAction(r));
    } else if (s === "ACKNOWLEDGED") {
      list.push(resolveAction(r));
    } else if (s === "RESOLVED") {
      list.push({ label: __("Close"), onClick: () => post(r.id, "close") });
    }
    return list;
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Issues") }), /* @__PURE__ */ React.createElement(
    ResourceTable,
    {
      shape: "issues_all",
      title: __("Issues"),
      columns,
      orderBy: "reported_at",
      orderDir: "desc",
      actions,
      emptyText: __("No issues.")
    }
  ), dispositionFor && /* @__PURE__ */ React.createElement(
    DispositionModal,
    {
      issue: dispositionFor,
      base,
      onClose: () => setDispositionFor(null)
    }
  ), actionsFor && /* @__PURE__ */ React.createElement(
    ActionsModal,
    {
      issue: actionsFor,
      base,
      csrf,
      users: reporterNames,
      onClose: () => setActionsFor(null)
    }
  ));
}
IssuesIndex.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function DispositionModal({ issue, base, onClose }) {
  const { data, setData, post, processing, errors } = useForm({
    disposition: issue.disposition ?? "pending",
    non_conforming_qty: issue.non_conforming_qty ?? "",
    nc_source: issue.nc_source ?? "",
    root_cause: issue.root_cause ?? "",
    containment_action: issue.containment_action ?? ""
  });
  const submit = () => {
    post(`${base}/issues/${issue.id}/disposition`, {
      preserveScroll: true,
      onSuccess: onClose
    });
  };
  const dispositionOptions = Object.keys(DISPOSITION_LABELS).map((value) => ({ value, label: __(DISPOSITION_LABELS[value]) }));
  const sourceOptions = [
    { value: "", label: __("\u2014 Source \u2014") },
    ...Object.keys(NC_SOURCE_LABELS).map((value) => ({ value, label: __(NC_SOURCE_LABELS[value]) }))
  ];
  return /* @__PURE__ */ React.createElement(
    Modal,
    {
      open: true,
      onClose,
      title: __("Disposition"),
      subtitle: issue.title,
      closeLabel: __("Close"),
      className: "max-w-[520px]",
      footer: /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Button, { variant: "secondary", onClick: onClose }, __("Cancel")), /* @__PURE__ */ React.createElement(Button, { variant: "primary", onClick: submit, disabled: processing }, __("Record disposition")))
    },
    /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "mb-1 block text-[12.5px] font-medium text-om-ink" }, __("Disposition")), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        className: "w-full",
        options: dispositionOptions,
        value: data.disposition,
        onChange: (v) => setData("disposition", v)
      }
    ), errors.disposition && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[11.5px] text-om-blocked" }, errors.disposition)), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-3" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "mb-1 block text-[12.5px] font-medium text-om-ink" }, __("Non-conforming quantity")), /* @__PURE__ */ React.createElement(
      TextField,
      {
        inputMode: "decimal",
        value: String(data.non_conforming_qty ?? ""),
        onChange: (v) => setData("non_conforming_qty", v),
        placeholder: "0",
        error: errors.non_conforming_qty
      }
    )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "mb-1 block text-[12.5px] font-medium text-om-ink" }, __("Source")), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        className: "w-full",
        options: sourceOptions,
        value: data.nc_source,
        onChange: (v) => setData("nc_source", v)
      }
    ), errors.nc_source && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[11.5px] text-om-blocked" }, errors.nc_source))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "mb-1 block text-[12.5px] font-medium text-om-ink" }, __("Root cause")), /* @__PURE__ */ React.createElement(
      TextField,
      {
        multiline: true,
        value: data.root_cause ?? "",
        onChange: (v) => setData("root_cause", v),
        placeholder: __("Optional"),
        error: errors.root_cause
      }
    )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "mb-1 block text-[12.5px] font-medium text-om-ink" }, __("Containment action")), /* @__PURE__ */ React.createElement(
      TextField,
      {
        multiline: true,
        value: data.containment_action ?? "",
        onChange: (v) => setData("containment_action", v),
        placeholder: __("Optional"),
        error: errors.containment_action
      }
    )))
  );
}
function ActionsModal({ issue, base, csrf, users, onClose }) {
  const [actions, setActions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [form, setForm] = useState({ type: "corrective", title: "", assigned_to_id: "", due_date: "" });
  const [confirmDelete, setConfirmDelete] = useState(null);
  const api = async (url, method = "GET", body) => {
    const res = await fetch(url, {
      method,
      headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
      credentials: "same-origin",
      body: body ? JSON.stringify(body) : void 0
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.message || __("Request failed"));
    return json;
  };
  const load = () => {
    setLoading(true);
    api(`${base}/issues/${issue.id}/actions`).then((d) => setActions(d.actions ?? [])).catch((e) => setError(e.message)).finally(() => setLoading(false));
  };
  useEffect(load, []);
  const run = async (fn) => {
    setError(null);
    try {
      const d = await fn();
      setActions(d.actions ?? []);
    } catch (e) {
      setError(e.message);
    }
  };
  const add = (e) => {
    e.preventDefault();
    if (!form.title.trim()) return;
    run(() => api(`${base}/issues/${issue.id}/actions`, "POST", {
      type: form.type,
      title: form.title.trim(),
      assigned_to_id: form.assigned_to_id || null,
      due_date: form.due_date || null
    })).then(() => setForm({ type: "corrective", title: "", assigned_to_id: "", due_date: "" }));
  };
  const start = (a) => run(() => api(`${base}/issues/actions/${a.id}/start`, "POST"));
  const complete = (a) => {
    const notes = prompt(__("Completion notes (optional):")) ?? void 0;
    run(() => api(`${base}/issues/actions/${a.id}/complete`, "POST", { notes }));
  };
  const verify = (a) => run(() => api(`${base}/issues/actions/${a.id}/verify`, "POST"));
  const remove = (a) => run(() => api(`${base}/issues/actions/${a.id}`, "DELETE"));
  const typeOptions = [
    { value: "corrective", label: __("Corrective") },
    { value: "preventive", label: __("Preventive") },
    { value: "containment", label: __("Containment") }
  ];
  const assigneeOptions = [
    { value: "", label: __("\u2014 Assignee \u2014") },
    ...Object.entries(users).map(([id, name]) => ({ value: id, label: name }))
  ];
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(
    Modal,
    {
      open: true,
      onClose,
      title: __("Corrective / preventive actions"),
      subtitle: issue.title,
      closeLabel: __("Close"),
      className: "max-w-[640px]"
    },
    /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, error && /* @__PURE__ */ React.createElement(InlineAlert, { severity: "error", title: __("Something went wrong") }, error), loading ? /* @__PURE__ */ React.createElement("p", { className: "py-4 text-[12.5px] text-om-faint" }, __("Loading\u2026")) : actions.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "py-3 text-[12.5px] text-om-faint" }, __("No actions yet. The issue can only be closed once all actions are verified.")) : /* @__PURE__ */ React.createElement("ul", { className: "max-h-72 space-y-2 overflow-y-auto" }, actions.map((a) => {
      const st = ACTION_STATUS[a.status] ?? { tone: "pending", label: a.status };
      return /* @__PURE__ */ React.createElement(
        "li",
        {
          key: a.id,
          className: "flex items-center gap-3 rounded-om border border-om-line bg-om-bg px-3 py-3"
        },
        /* @__PURE__ */ React.createElement("span", { className: "font-mono text-[9.5px] uppercase tracking-[0.06em] rounded-[20px] bg-om-chip px-[10px] py-1 text-om-muted" }, __(ACTION_TYPE_LABELS[a.type] ?? a.type)),
        /* @__PURE__ */ React.createElement("span", { className: "flex-1 text-[13px] font-medium text-om-ink" }, a.title, a.assigned_to && /* @__PURE__ */ React.createElement("span", { className: "ml-2 text-[11.5px] text-om-faint" }, "\u2192 ", a.assigned_to), a.due_date && /* @__PURE__ */ React.createElement("span", { className: `ml-2 text-[11.5px] ${a.is_overdue ? "font-semibold text-om-blocked" : "text-om-faint"}` }, __("due"), " ", a.due_date, a.is_overdue ? ` \xB7 ${__("Overdue")}` : "")),
        /* @__PURE__ */ React.createElement(StatusPill, { status: st.tone, label: st.label, pulse: false }),
        a.status === "open" && /* @__PURE__ */ React.createElement(Button, { variant: "secondary", className: "px-3 py-1.5 text-[12px]", onClick: () => start(a) }, __("Start")),
        (a.status === "open" || a.status === "in_progress") && /* @__PURE__ */ React.createElement(Button, { variant: "primary", className: "px-3 py-1.5 text-[12px]", onClick: () => complete(a) }, __("Complete")),
        a.status === "done" && /* @__PURE__ */ React.createElement(Button, { variant: "accent", className: "px-3 py-1.5 text-[12px]", onClick: () => verify(a) }, __("Verify")),
        /* @__PURE__ */ React.createElement(IconButton, { variant: "danger", "aria-label": __("Delete"), onClick: () => setConfirmDelete(a) }, "\xD7")
      );
    })), /* @__PURE__ */ React.createElement("form", { onSubmit: add, className: "flex flex-wrap items-end gap-2 border-t border-om-line2 pt-4" }, /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        className: "min-w-[9rem]",
        options: typeOptions,
        value: form.type,
        onChange: (v) => setForm({ ...form, type: v })
      }
    ), /* @__PURE__ */ React.createElement(
      TextField,
      {
        className: "min-w-[12rem] flex-1",
        value: form.title,
        onChange: (v) => setForm({ ...form, title: v }),
        placeholder: __("Action title"),
        maxLength: 255,
        required: true
      }
    ), /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        className: "min-w-[9rem]",
        options: assigneeOptions,
        value: form.assigned_to_id,
        onChange: (v) => setForm({ ...form, assigned_to_id: v })
      }
    ), /* @__PURE__ */ React.createElement(
      DatePicker,
      {
        className: "min-w-[10rem]",
        value: form.due_date,
        onChange: (v) => setForm({ ...form, due_date: v || "" }),
        placeholder: __("Due date")
      }
    ), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary" }, __("Add"))))
  ), /* @__PURE__ */ React.createElement(
    ConfirmDialog,
    {
      open: !!confirmDelete,
      onClose: () => setConfirmDelete(null),
      onConfirm: () => {
        const a = confirmDelete;
        setConfirmDelete(null);
        if (a) remove(a);
      },
      title: __("Delete this action?"),
      confirmLabel: __("Delete"),
      cancelLabel: __("Cancel")
    },
    confirmDelete?.title
  ));
}
export {
  IssuesIndex as default
};
