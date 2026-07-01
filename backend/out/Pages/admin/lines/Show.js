import { useState } from "react";
import { __ } from "../../../lib/i18n";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
import CustomFieldsDisplay from "../../../components/CustomFieldsDisplay";
const WORK_ORDER_STATUS_LABELS = {
  PENDING: "Pending",
  ACCEPTED: "Accepted",
  IN_PROGRESS: "In Progress",
  BLOCKED: "Blocked",
  PAUSED: "Paused",
  DONE: "Done",
  REJECTED: "Rejected",
  CANCELLED: "Cancelled"
};
const WORK_ORDER_STATUS_CLASSES = {
  PENDING: "bg-om-downtime-bg text-om-downtime",
  IN_PROGRESS: "bg-om-chip text-om-accent",
  COMPLETED: "bg-om-running-bg text-om-running",
  BLOCKED: "bg-om-blocked-bg text-om-blocked"
};
function Icon({ d, className = "w-5 h-5" }) {
  return /* @__PURE__ */ React.createElement("svg", { className, fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d }));
}
function LineStatusesCard({ line, lineStatuses }) {
  const form = useForm({ color: "#F59E0B", name: "", sort_order: 10 });
  const submit = (e) => {
    e.preventDefault();
    form.post(`/admin/lines/${line.id}/statuses`, { preserveScroll: true, onSuccess: () => form.reset() });
  };
  const deleteStatus = (statusId) => {
    if (confirm("Delete this status?")) {
      router.delete(`/admin/line-statuses/${statusId}`, { preserveScroll: true });
    }
  };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-6 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink" }, "Line Statuses"), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-0.5" }, "Kanban statuses available for work orders on this line. Global statuses are shown in gray.")), /* @__PURE__ */ React.createElement(Link, { href: "/admin/line-statuses", className: "text-sm text-om-accent hover:underline" }, "Manage global statuses \u2192")), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-2 mb-4" }, lineStatuses.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, "No statuses yet. Add one below or", " ", /* @__PURE__ */ React.createElement(Link, { href: "/admin/line-statuses", className: "text-om-accent hover:underline" }, "manage global statuses"), ".") : lineStatuses.map((status) => /* @__PURE__ */ React.createElement(
    "div",
    {
      key: status.id,
      className: "flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-white",
      style: { backgroundColor: status.color }
    },
    status.name,
    status.is_default && /* @__PURE__ */ React.createElement("span", { className: "text-xs opacity-75" }, "(default)"),
    status.line_id === null ? /* @__PURE__ */ React.createElement("span", { className: "text-xs opacity-60" }, "global") : /* @__PURE__ */ React.createElement(
      "button",
      {
        onClick: () => deleteStatus(status.id),
        className: "ml-1 opacity-75 hover:opacity-100",
        title: "Delete"
      },
      /* @__PURE__ */ React.createElement(Icon, { d: "M6 18L18 6M6 6l12 12", className: "w-3.5 h-3.5" })
    )
  ))), /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "border-t border-om-line2 pt-4 flex items-end gap-3 flex-wrap" }, /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-1" }, /* @__PURE__ */ React.createElement("label", { className: "text-xs text-om-muted" }, "Color"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "color",
      value: form.data.color,
      onChange: (e) => form.setData("color", e.target.value),
      className: "w-10 h-10 rounded cursor-pointer border border-om-line p-0.5",
      required: true
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-1 flex-1 min-w-[160px]" }, /* @__PURE__ */ React.createElement("label", { className: "text-xs text-om-muted" }, "Status name (line-specific)"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.name,
      onChange: (e) => form.setData("name", e.target.value),
      placeholder: "e.g. Waiting for parts",
      className: "form-input py-1.5 text-sm",
      maxLength: 100,
      required: true
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-1 w-20" }, /* @__PURE__ */ React.createElement("label", { className: "text-xs text-om-muted" }, "Order"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "number",
      value: form.data.sort_order,
      onChange: (e) => form.setData("sort_order", e.target.value),
      min: 0,
      className: "form-input py-1.5 text-sm"
    }
  )), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "ghost", loading: form.processing }, /* @__PURE__ */ React.createElement(Icon, { d: "M12 4v16m8-8H4", className: "w-4 h-4 inline-block mr-1" }), "Add to this line")));
}
function ProductTypesCard({ line, allProductTypes, assignedTypeIds: initialAssigned }) {
  const [open, setOpen] = useState(false);
  const form = useForm({ product_type_ids: initialAssigned });
  const toggleType = (id) => {
    const current = form.data.product_type_ids;
    const next = current.includes(id) ? current.filter((x) => x !== id) : [...current, id];
    form.setData("product_type_ids", next);
  };
  const submit = (e) => {
    e.preventDefault();
    form.post(`/admin/lines/${line.id}/product-types/sync`, { preserveScroll: true });
  };
  const assignedSet = new Set(initialAssigned);
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-6 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink" }, __("Assigned Product Types")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-0.5" }, __("Product types that can be produced on this line.")))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-2 mb-4" }, line.product_types.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint" }, __("No product types assigned \u2014 all types are allowed.")) : line.product_types.map((pt) => /* @__PURE__ */ React.createElement(
    "span",
    {
      key: pt.id,
      className: "inline-flex items-center gap-1.5 px-3 py-1.5 bg-om-chip border border-om-line text-om-accent text-sm font-medium rounded-om-sm"
    },
    /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs text-om-accent" }, pt.code),
    pt.name
  ))), /* @__PURE__ */ React.createElement("div", { className: "border-t border-om-line2 pt-4" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setOpen((o) => !o),
      className: "text-sm text-om-accent hover:text-om-accent font-medium flex items-center gap-1"
    },
    /* @__PURE__ */ React.createElement(Icon, { d: "M12 4v16m8-8H4", className: "w-4 h-4" }),
    /* @__PURE__ */ React.createElement("span", null, open ? "Hide selector" : "Change assignment")
  ), open && /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "mt-3" }, allProductTypes.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, __("No active product types defined yet.")) : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mb-3" }, allProductTypes.map((pt) => {
    const checked = form.data.product_type_ids.includes(pt.id);
    return /* @__PURE__ */ React.createElement(
      "label",
      {
        key: pt.id,
        className: `flex items-center gap-2 p-2.5 rounded-om-sm border cursor-pointer transition-colors ${checked ? "border-blue-400 bg-om-chip" : "border-om-line2 hover:border-om-line"}`
      },
      /* @__PURE__ */ React.createElement(
        Checkbox,
        {
          checked,
          onChange: () => toggleType(pt.id)
        }
      ),
      /* @__PURE__ */ React.createElement("div", { className: "min-w-0" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm font-medium text-om-ink truncate" }, pt.name), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint font-mono" }, pt.code))
    );
  })), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mb-3" }, "Leave all unchecked to allow all product types on this line."), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: form.processing }, "Save Assignment")))));
}
function WorkstationsCard({ line, effectiveWorkstations }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-6 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink" }, __("Workstations")), effectiveWorkstations.length === 0 || effectiveWorkstations.length === 1 && effectiveWorkstations[0].is_line_itself ? /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-downtime mt-0.5 font-medium" }, "No workstations configured \u2014 line itself acts as a single workstation.") : /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-0.5" }, line.workstations_count, " workstation(s) on this line.")), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}/workstations`,
      className: "px-3 py-2 border border-om-line rounded-om-sm text-sm font-medium text-om-muted hover:bg-om-bg"
    },
    "Manage"
  )), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" }, effectiveWorkstations.map((ws) => /* @__PURE__ */ React.createElement(
    "div",
    {
      key: ws.id,
      className: `flex items-center gap-3 p-3 rounded-om-sm border ${ws.is_line_itself ? "border-om-line bg-om-downtime-bg" : "border-om-line2 bg-om-panel"}`
    },
    /* @__PURE__ */ React.createElement("div", { className: `${ws.is_line_itself ? "bg-om-downtime-bg" : "bg-om-running-bg"} rounded-full p-2 flex-shrink-0` }, /* @__PURE__ */ React.createElement(
      Icon,
      {
        d: "M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4",
        className: `w-5 h-5 ${ws.is_line_itself ? "text-om-downtime" : "text-om-running"}`
      }
    )),
    /* @__PURE__ */ React.createElement("div", { className: "min-w-0" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm font-semibold text-om-ink truncate" }, ws.name), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint font-mono" }, ws.code), ws.is_line_itself && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-downtime" }, __("virtual (line = workstation)")))
  ))));
}
function OperatorsCard({ line, availableOperators }) {
  const form = useForm({ user_id: "" });
  const submit = (e) => {
    e.preventDefault();
    form.post(`/admin/lines/${line.id}/assign-operator`, {
      preserveScroll: true,
      onSuccess: () => form.reset()
    });
  };
  const unassign = (userId, userName) => {
    if (confirm(`Remove ${userName} from this line?`)) {
      router.delete(`/admin/lines/${line.id}/operators/${userId}`, { preserveScroll: true });
    }
  };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-4" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink" }, "Assigned Operators")), line.users.length > 0 ? /* @__PURE__ */ React.createElement("div", { className: "space-y-2 mb-4" }, line.users.map((user) => /* @__PURE__ */ React.createElement("div", { key: user.id, className: "flex items-center justify-between p-3 bg-om-panel rounded-om-sm" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("div", { className: "w-10 h-10 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-bold" }, user.name.charAt(0).toUpperCase()), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, user.name), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, user.username))), /* @__PURE__ */ React.createElement(
    "button",
    {
      onClick: () => unassign(user.id, user.name),
      className: "text-om-blocked hover:text-om-blocked p-2",
      title: "Remove operator"
    },
    /* @__PURE__ */ React.createElement(Icon, { d: "M6 18L18 6M6 6l12 12" })
  )))) : /* @__PURE__ */ React.createElement("div", { className: "text-center py-8 bg-om-panel rounded-om-sm mb-4" }, /* @__PURE__ */ React.createElement(
    Icon,
    {
      d: "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
      className: "mx-auto h-12 w-12 text-om-faint mb-2"
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, "No operators assigned yet")), availableOperators.length > 0 ? /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "border-t border-om-line2 pt-4" }, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Assign New Operator"), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: availableOperators.map((op) => ({ value: String(op.id), label: `${op.name} (${op.username})` })),
      value: form.data.user_id == null ? "" : String(form.data.user_id),
      onChange: (v) => form.setData("user_id", v),
      placeholder: "Select an operator...",
      className: "flex-1"
    }
  ), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: form.processing }, /* @__PURE__ */ React.createElement(Icon, { d: "M12 4v16m8-8H4" }), "Assign")), form.errors.user_id && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, form.errors.user_id)) : /* @__PURE__ */ React.createElement("div", { className: "border-t border-om-line2 pt-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted text-center" }, "All available operators are already assigned to this line.")));
}
function WorkOrdersCard({ line, workOrders }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink mb-4" }, "Recent Work Orders"), workOrders.length > 0 ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, workOrders.map((wo) => /* @__PURE__ */ React.createElement("div", { key: wo.id, className: "p-3 bg-om-panel rounded-om-sm" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement("p", { className: "font-medium text-om-ink" }, wo.work_order_number), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, wo.product_name), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-1" }, "Quantity: ", wo.planned_qty, " | Created: ", wo.created_at?.slice(0, 16).replace("T", " "))), /* @__PURE__ */ React.createElement(
    "span",
    {
      className: `px-2 py-1 text-xs font-medium rounded-full ${WORK_ORDER_STATUS_CLASSES[wo.status] ?? "bg-om-chip text-om-ink"}`
    },
    WORK_ORDER_STATUS_LABELS[wo.status] ?? wo.status
  ))))), line.work_orders_count > 10 && /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted text-center mt-4" }, "Showing 10 most recent of ", line.work_orders_count, " total work orders")) : /* @__PURE__ */ React.createElement("div", { className: "text-center py-8 bg-om-panel rounded-om-sm" }, /* @__PURE__ */ React.createElement(
    Icon,
    {
      d: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01",
      className: "mx-auto h-12 w-12 text-om-faint mb-2"
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-om-muted" }, "No work orders yet")));
}
function DefaultViewCard({ line }) {
  const handleChange = (value) => {
    router.post(
      `/admin/lines/${line.id}/default-view`,
      { default_operator_view: value },
      { preserveScroll: true }
    );
  };
  const current = line.default_operator_view ?? "queue";
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-6 mt-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink mb-1" }, "Default Operator View"), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-4" }, "Choose which view operators see by default when they select this line."), /* @__PURE__ */ React.createElement("div", { className: "flex gap-3" }, [
    {
      value: "queue",
      label: "Queue",
      desc: "Standard work order list with status, batches, priority and actions."
    },
    {
      value: "workstation",
      label: "Workstation",
      desc: "Flat production table with quantities, Z1/Z2 shift inputs and inline entry."
    }
  ].map((opt) => /* @__PURE__ */ React.createElement(
    "label",
    {
      key: opt.value,
      className: `flex-1 flex items-center gap-3 p-4 rounded-om-sm border-2 cursor-pointer transition-colors ${current === opt.value ? "border-om-accent bg-om-chip" : "border-om-line2 hover:border-om-line"}`
    },
    /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "radio",
        name: "default_operator_view",
        value: opt.value,
        checked: current === opt.value,
        onChange: () => handleChange(opt.value),
        className: "sr-only"
      }
    ),
    /* @__PURE__ */ React.createElement(
      "span",
      {
        "aria-hidden": true,
        className: `flex size-[18px] shrink-0 items-center justify-center rounded-full border-2 ${current === opt.value ? "border-om-accent" : "border-om-faintest"}`
      },
      current === opt.value && /* @__PURE__ */ React.createElement("span", { className: "size-2 rounded-full bg-om-accent" })
    ),
    /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("span", { className: "text-sm font-semibold text-om-ink" }, opt.label), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-0.5" }, opt.desc))
  ))));
}
function ViewTemplateCard({ line, allViewTemplates }) {
  const form = useForm({ view_template_id: line.view_template_id != null ? String(line.view_template_id) : "" });
  const submit = (e) => {
    e.preventDefault();
    form.post(`/admin/lines/${line.id}/view-template`, { preserveScroll: true });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-6 mt-6" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink mb-1" }, __("Workstation View")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-4" }, "Select a view template that defines which columns operators see in the Workstation view for this line."), /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "flex items-end gap-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1" }, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("View Template")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [
        { value: "", label: "\u2014 Default (no custom columns) \u2014" },
        ...allViewTemplates.map((tpl) => ({ value: String(tpl.id), label: `${tpl.name} (${tpl.columns_count} columns)` }))
      ],
      value: form.data.view_template_id == null ? "" : String(form.data.view_template_id),
      onChange: (v) => form.setData("view_template_id", v),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: form.processing }, "Save")), allViewTemplates.length === 0 && /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint mt-3" }, "No templates created yet.", " ", /* @__PURE__ */ React.createElement(Link, { href: "/admin/view-templates/create", className: "text-om-accent hover:underline" }, "Create one"), "."));
}
function ViewColumnsCard({ line, viewColumns: initialColumns }) {
  const [columns, setColumns] = useState(
    initialColumns.map((c) => ({ label: c.label, key: c.key, source: c.source }))
  );
  const [newLabel, setNewLabel] = useState("");
  const [newKey, setNewKey] = useState("");
  const [newSource, setNewSource] = useState("extra_data");
  const [processing, setProcessing] = useState(false);
  const add = () => {
    if (!newLabel || !newKey) return;
    setColumns((cols) => [...cols, { label: newLabel, key: newKey, source: newSource }]);
    setNewLabel("");
    setNewKey("");
    setNewSource("extra_data");
  };
  const remove = (i) => setColumns((cols) => cols.filter((_, idx) => idx !== i));
  const moveUp = (i) => {
    if (i === 0) return;
    setColumns((cols) => {
      const next = [...cols];
      [next[i - 1], next[i]] = [next[i], next[i - 1]];
      return next;
    });
  };
  const moveDown = (i) => {
    setColumns((cols) => {
      if (i >= cols.length - 1) return cols;
      const next = [...cols];
      [next[i], next[i + 1]] = [next[i + 1], next[i]];
      return next;
    });
  };
  const submit = (e) => {
    e.preventDefault();
    setProcessing(true);
    router.post(
      `/admin/lines/${line.id}/view-columns`,
      { columns },
      {
        preserveScroll: true,
        onFinish: () => setProcessing(false)
      }
    );
  };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-6 mt-4" }, /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-bold text-om-ink mb-1" }, __("Workstation View Columns")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-4" }, "Configure which columns operators see in the Workstation view for this line.", " ", "Columns with source ", /* @__PURE__ */ React.createElement("strong", null, "extra_data"), " pull values from the work order's imported data.", " ", "Columns with source ", /* @__PURE__ */ React.createElement("strong", null, "field"), " pull from work order fields (order_no, description, due_date, priority)."), /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "space-y-2 mb-4" }, columns.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "text-sm text-om-faint text-center py-4" }, "No custom columns configured. Default view will be shown.") : columns.map((col, i) => /* @__PURE__ */ React.createElement("div", { key: i, className: "flex items-center gap-2 bg-om-panel rounded-om-sm px-3 py-2" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-faint text-sm font-mono w-6 text-center" }, i + 1), /* @__PURE__ */ React.createElement("span", { className: "flex-1 text-sm font-medium text-om-ink" }, col.label), /* @__PURE__ */ React.createElement("code", { className: "text-xs text-om-muted bg-om-line2 px-1.5 py-0.5 rounded" }, col.source, ".", col.key), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => moveUp(i),
      className: "p-1 text-om-faint hover:text-om-ink",
      title: "Move up"
    },
    /* @__PURE__ */ React.createElement(Icon, { d: "M5 15l7-7 7 7", className: "w-4 h-4" })
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => moveDown(i),
      className: "p-1 text-om-faint hover:text-om-ink",
      title: "Move down"
    },
    /* @__PURE__ */ React.createElement(Icon, { d: "M19 9l-7 7-7-7", className: "w-4 h-4" })
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => remove(i),
      className: "p-1 text-red-400 hover:text-om-blocked",
      title: "Remove"
    },
    /* @__PURE__ */ React.createElement(Icon, { d: "M6 18L18 6M6 6l12 12", className: "w-4 h-4" })
  )))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-wrap gap-2 items-end border-t border-om-line2 pt-4 mb-4" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-[140px]" }, /* @__PURE__ */ React.createElement("label", { className: "text-xs text-om-muted block mb-1" }, __("Column Label")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: newLabel,
      onChange: (e) => setNewLabel(e.target.value),
      placeholder: __("e.g. Material"),
      className: "form-input w-full text-sm"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-[140px]" }, /* @__PURE__ */ React.createElement("label", { className: "text-xs text-om-muted block mb-1" }, __("Data Key")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: newKey,
      onChange: (e) => setNewKey(e.target.value),
      placeholder: __("e.g. material"),
      className: "form-input w-full text-sm"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "w-36" }, /* @__PURE__ */ React.createElement("label", { className: "text-xs text-om-muted block mb-1" }, __("Source")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [
        { value: "extra_data", label: "extra_data" },
        { value: "field", label: "field" }
      ],
      value: newSource == null ? "" : String(newSource),
      onChange: (v) => setNewSource(v),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "button",
      variant: "ghost",
      onClick: add,
      disabled: !newLabel || !newKey
    },
    "+ Add"
  )), /* @__PURE__ */ React.createElement("div", { className: "flex justify-end" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing }, "Save View Columns"))));
}
function LineShow() {
  const {
    line,
    workOrders = [],
    availableOperators = [],
    lineStatuses = [],
    allProductTypes = [],
    assignedTypeIds = [],
    viewColumns = [],
    allViewTemplates = [],
    effectiveWorkstations = [],
    customFields = []
  } = usePage().props;
  const handleToggleActive = () => {
    router.post(`/admin/lines/${line.id}/toggle-active`, {}, { preserveScroll: true });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `${line.name} \u2014 Configure` }), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/admin/lines",
      className: "text-om-accent hover:text-om-accent flex items-center gap-2 mb-4 text-sm"
    },
    /* @__PURE__ */ React.createElement(Icon, { d: "M15 19l-7-7 7-7" }),
    "Back to Production Lines"
  ), /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-4" }, /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, line.name), line.is_active ? /* @__PURE__ */ React.createElement("span", { className: "px-3 py-1 bg-om-running-bg text-om-running rounded-full text-sm font-medium" }, "Active") : /* @__PURE__ */ React.createElement("span", { className: "px-3 py-1 bg-om-chip text-om-muted rounded-full text-sm font-medium" }, "Inactive")), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}/edit`,
      className: "px-4 py-2 border border-om-line rounded-om-sm text-sm font-medium text-om-muted hover:bg-om-bg flex items-center gap-2"
    },
    /* @__PURE__ */ React.createElement(Icon, { d: "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" }),
    "Edit Line"
  ), /* @__PURE__ */ React.createElement(
    Button,
    {
      type: "button",
      variant: line.is_active ? "ghost" : "primary",
      onClick: handleToggleActive
    },
    line.is_active ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Icon, { d: "M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" }), "Deactivate") : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Icon, { d: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" }), "Activate")
  ))), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted font-mono mt-1" }, line.code), line.description && /* @__PURE__ */ React.createElement("p", { className: "text-om-muted mt-2" }, line.description)), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 md:grid-cols-3 gap-6 mb-6" }, /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, "Work Orders"), /* @__PURE__ */ React.createElement("p", { className: "text-3xl font-bold text-om-accent" }, line.work_orders_count)), /* @__PURE__ */ React.createElement("div", { className: "bg-om-chip rounded-full p-3" }, /* @__PURE__ */ React.createElement(
    Icon,
    {
      d: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2",
      className: "w-8 h-8 text-om-accent"
    }
  )))), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}/workstations`,
      className: "bg-om-card rounded-om-sm shadow-sm p-5 hover:shadow-md transition-shadow block"
    },
    /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, __("Workstations")), /* @__PURE__ */ React.createElement("p", { className: "text-3xl font-bold text-om-running" }, line.workstations_count)), /* @__PURE__ */ React.createElement("div", { className: "bg-om-running-bg rounded-full p-3" }, /* @__PURE__ */ React.createElement(
      Icon,
      {
        d: "M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4",
        className: "w-8 h-8 text-om-running"
      }
    )))
  ), /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om-sm shadow-sm p-5" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted" }, "Assigned Operators"), /* @__PURE__ */ React.createElement("p", { className: "text-3xl font-bold text-om-ink" }, line.users_count)), /* @__PURE__ */ React.createElement("div", { className: "bg-om-chip rounded-full p-3" }, /* @__PURE__ */ React.createElement(
    Icon,
    {
      d: "M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z",
      className: "w-8 h-8 text-om-ink"
    }
  ))))), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(CustomFieldsDisplay, { definitions: customFields, values: line.custom_fields ?? {} })), /* @__PURE__ */ React.createElement(LineStatusesCard, { line, lineStatuses }), /* @__PURE__ */ React.createElement(ProductTypesCard, { line, allProductTypes, assignedTypeIds }), /* @__PURE__ */ React.createElement(WorkstationsCard, { line, effectiveWorkstations }), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-1 lg:grid-cols-2 gap-6" }, /* @__PURE__ */ React.createElement(OperatorsCard, { line, availableOperators }), /* @__PURE__ */ React.createElement(WorkOrdersCard, { line, workOrders })), /* @__PURE__ */ React.createElement(DefaultViewCard, { line }), /* @__PURE__ */ React.createElement(ViewTemplateCard, { line, allViewTemplates }), /* @__PURE__ */ React.createElement(ViewColumnsCard, { line, viewColumns }));
}
LineShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  LineShow as default
};
