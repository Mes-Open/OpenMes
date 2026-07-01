import { useState, useEffect, useRef } from "react";
import { Head, router, usePage, useForm } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
import AppLayout from "../../../../layouts/AppLayout";
import { formatNumber, formatTime } from "../../../../lib/i18n";
const STATUS_DOT = {
  green: "bg-om-running",
  yellow: "bg-om-downtime",
  red: "bg-om-blocked",
  slate: "bg-slate-400"
};
const ACTION_COLORS = {
  update_batch_step: "bg-om-chip text-purple-700",
  update_work_order_qty: "bg-om-chip text-om-accent",
  create_issue: "bg-om-blocked-bg text-om-blocked",
  update_line_status: "bg-om-downtime-bg text-orange-700",
  set_work_order_status: "bg-om-chip text-indigo-700",
  log_event: "bg-om-chip text-om-muted",
  webhook_forward: "bg-teal-100 text-teal-700"
};
const ACTION_LABELS = {
  update_batch_step: "Update Batch Step",
  update_work_order_qty: "Update Work Order Qty",
  create_issue: "Create Issue",
  update_line_status: "Update Line Status",
  set_work_order_status: "Set Work Order Status",
  log_event: "Log Event",
  webhook_forward: "Webhook Forward"
};
function MqttShow() {
  const { connection, recentMessages = [], messagesUrl } = usePage().props;
  const mqtt = connection.mqtt;
  const dot = STATUS_DOT[connection.status_color] ?? "bg-slate-400";
  const handleToggle = () => {
    router.post(`/admin/connectivity/mqtt/${connection.id}/toggle-active`, {}, { preserveScroll: true });
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: `${connection.name} \u2014 MQTT` }), /* @__PURE__ */ React.createElement("div", { className: "p-6 space-y-6" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-start justify-between gap-4" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: "/admin/connectivity/mqtt",
      className: "text-sm text-om-muted hover:text-om-ink flex items-center gap-1 mb-2"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    "MQTT Connections"
  ), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("span", { className: `w-3 h-3 rounded-full ${dot} ${connection.status === "connected" ? "animate-pulse" : ""}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-2xl font-bold text-om-ink" }, connection.name), !connection.is_active && /* @__PURE__ */ React.createElement("span", { className: "text-xs px-2 py-0.5 bg-om-chip text-om-muted rounded-full" }, "Inactive")), mqtt && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-sm text-om-muted font-mono" }, mqtt.broker_host, ":", mqtt.broker_port, mqtt.use_tls && /* @__PURE__ */ React.createElement("span", { className: "ml-2 text-om-running" }, "TLS"), " \xB7 ", "QoS ", mqtt.qos_default)), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `/admin/connectivity/mqtt/${connection.id}/edit`,
      className: "px-4 py-2 text-sm font-medium bg-om-chip text-om-muted rounded-om-sm hover:bg-om-line2 transition-colors"
    },
    "Edit"
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: handleToggle,
      className: `px-4 py-2 text-sm font-medium rounded-om-sm transition-colors ${connection.is_active ? "bg-om-downtime-bg text-om-downtime hover:bg-om-downtime-bg" : "bg-om-running-bg text-om-running hover:bg-om-running-bg"}`
    },
    connection.is_active ? "Disable" : "Enable"
  ))), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-3 gap-4" }, /* @__PURE__ */ React.createElement(StatCard, { value: connection.topics.length, label: "Topics" }), /* @__PURE__ */ React.createElement(StatCard, { value: formatNumber(Number(connection.messages_received)), label: "Messages received" }), /* @__PURE__ */ React.createElement(StatCard, { value: connection.status, label: connection.last_connected_at ?? "Never", capitalize: true })), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-3" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-semibold text-om-ink" }, "Topics & Mappings")), /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, connection.topics.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-dashed border-om-line p-8 text-center text-om-faint" }, /* @__PURE__ */ React.createElement("svg", { className: "w-8 h-8 mx-auto mb-2 opacity-40", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "1.5", d: "M7 20l4-16m2 16l4-16M6 9h14M4 15h14" })), /* @__PURE__ */ React.createElement("p", { className: "text-sm" }, "No topics subscribed yet.")) : connection.topics.map((topic) => /* @__PURE__ */ React.createElement(
    TopicCard,
    {
      key: topic.id,
      topic,
      connectionId: connection.id
    }
  )), /* @__PURE__ */ React.createElement(AddTopicForm, { connectionId: connection.id }))), /* @__PURE__ */ React.createElement(
    LiveMessageLog,
    {
      initialMessages: recentMessages,
      initialLastId: recentMessages.length > 0 ? Math.max(...recentMessages.map((m) => m.id)) : 0,
      messagesUrl
    }
  )));
}
MqttShow.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
function StatCard({ value, label, capitalize }) {
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-om-line2 p-4 text-center" }, /* @__PURE__ */ React.createElement("p", { className: `text-2xl font-bold text-om-ink ${capitalize ? "capitalize" : ""}` }, value), /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-muted mt-1" }, label));
}
function TopicCard({ topic, connectionId }) {
  const [editOpen, setEditOpen] = useState(false);
  const [addMappingOpen, setAddMappingOpen] = useState(false);
  const handleDeleteTopic = () => {
    if (confirm("Delete this topic and all its mappings?")) {
      router.delete(`/admin/connectivity/mqtt/${connectionId}/topics/${topic.id}`, { preserveScroll: true });
    }
  };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-om-line2 overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 px-4 py-3 bg-om-panel border-b border-om-line2" }, /* @__PURE__ */ React.createElement("span", { className: `w-2 h-2 rounded-full shrink-0 ${topic.is_active ? "bg-om-running" : "bg-slate-400"}` }), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-sm font-medium text-om-ink flex-1" }, topic.topic_pattern), /* @__PURE__ */ React.createElement("span", { className: "text-xs px-2 py-0.5 bg-om-line2 text-om-muted rounded-full uppercase" }, topic.payload_format), topic.description && /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint max-w-xs truncate" }, topic.description), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1 shrink-0" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setEditOpen((o) => !o),
      className: "p-1.5 text-om-faint hover:text-om-ink rounded-md hover:bg-om-chip transition-colors"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-3.5 h-3.5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" }))
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: handleDeleteTopic,
      className: "p-1.5 text-om-faint hover:text-om-blocked rounded-md hover:bg-om-chip transition-colors"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-3.5 h-3.5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" }))
  ))), editOpen && /* @__PURE__ */ React.createElement(
    EditTopicForm,
    {
      topic,
      connectionId,
      onClose: () => setEditOpen(false)
    }
  ), /* @__PURE__ */ React.createElement("div", { className: "divide-y divide-om-line2" }, topic.mappings.length === 0 ? /* @__PURE__ */ React.createElement("p", { className: "px-4 py-3 text-xs text-om-faint italic" }, "No mappings defined \u2014 messages will be logged only.") : topic.mappings.map((mapping) => /* @__PURE__ */ React.createElement(
    MappingRow,
    {
      key: mapping.id,
      mapping,
      topic,
      connectionId
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-t border-om-line2" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setAddMappingOpen((o) => !o),
      className: "flex items-center gap-1.5 text-xs font-medium text-om-accent hover:text-om-accent"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-3.5 h-3.5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M12 4v16m8-8H4" })),
    "Add mapping rule"
  ), addMappingOpen && /* @__PURE__ */ React.createElement(
    AddMappingForm,
    {
      connectionId,
      topicId: topic.id,
      onClose: () => setAddMappingOpen(false)
    }
  )));
}
function EditTopicForm({ topic, connectionId, onClose }) {
  const form = useForm({
    topic_pattern: topic.topic_pattern,
    payload_format: topic.payload_format,
    description: topic.description ?? ""
  });
  const submit = (e) => {
    e.preventDefault();
    form.put(`/admin/connectivity/mqtt/${connectionId}/topics/${topic.id}`, {
      preserveScroll: true,
      onSuccess: onClose
    });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "px-4 py-3 border-b border-om-line2 bg-om-chip/40" }, /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "flex gap-3 items-end flex-wrap" }, /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-48" }, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Pattern"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.topic_pattern,
      onChange: (e) => form.setData("topic_pattern", e.target.value),
      required: true,
      className: "w-full px-2 py-1.5 text-sm font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Format"), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.data.payload_format,
      onChange: (v) => form.setData("payload_format", v),
      options: ["json", "plain", "csv", "hex"].map((f) => ({ value: f, label: f.toUpperCase() }))
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-36" }, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Description"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.description,
      onChange: (e) => form.setData("description", e.target.value),
      className: "w-full px-2 py-1.5 text-sm border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(Button, { variant: "primary", type: "submit", loading: form.processing }, "Save"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", type: "button", onClick: onClose }, "Cancel"))));
}
function MappingRow({ mapping, topic, connectionId }) {
  const [editOpen, setEditOpen] = useState(false);
  const color = ACTION_COLORS[mapping.action_type] ?? "bg-om-chip text-om-muted";
  const label = ACTION_LABELS[mapping.action_type] ?? mapping.action_type;
  const priority = String(mapping.priority).padStart(3, "0");
  const handleDelete = () => {
    if (confirm("Delete this mapping?")) {
      router.delete(
        `/admin/connectivity/mqtt/${connectionId}/topics/${topic.id}/mappings/${mapping.id}`,
        { preserveScroll: true }
      );
    }
  };
  return /* @__PURE__ */ React.createElement("div", { className: `px-4 py-3 flex items-start gap-3 text-xs ${!mapping.is_active ? "opacity-50" : ""}` }, /* @__PURE__ */ React.createElement("span", { className: "shrink-0 text-om-faint tabular-nums mt-0.5" }, priority), /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-0 space-y-1" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 flex-wrap" }, mapping.field_path && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-muted bg-om-chip px-1.5 py-0.5 rounded" }, mapping.field_path), /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "\u2192")), /* @__PURE__ */ React.createElement("span", { className: `px-2 py-0.5 rounded-full text-xs font-medium ${color}` }, label), mapping.condition_expr && /* @__PURE__ */ React.createElement("span", { className: "font-mono text-om-faint text-xs" }, "if: ", mapping.condition_expr)), mapping.description && /* @__PURE__ */ React.createElement("p", { className: "text-om-faint" }, mapping.description), mapping.action_params && /* @__PURE__ */ React.createElement("p", { className: "font-mono text-om-faint break-all" }, JSON.stringify(mapping.action_params).substring(0, 120)), editOpen && /* @__PURE__ */ React.createElement(
    EditMappingForm,
    {
      mapping,
      topic,
      connectionId,
      onClose: () => setEditOpen(false)
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1 shrink-0" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setEditOpen((o) => !o),
      className: "p-1 text-om-faint hover:text-om-ink rounded hover:bg-om-chip transition-colors"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-3 h-3", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" }))
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: handleDelete,
      className: "p-1 text-om-faint hover:text-om-blocked rounded hover:bg-om-chip transition-colors"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-3 h-3", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M6 18L18 6M6 6l12 12" }))
  )));
}
function EditMappingForm({ mapping, topic, connectionId, onClose }) {
  const form = useForm({
    field_path: mapping.field_path ?? "",
    action_type: mapping.action_type,
    condition_expr: mapping.condition_expr ?? "",
    priority: String(mapping.priority),
    action_params: mapping.action_params ? JSON.stringify(mapping.action_params, null, 2) : "",
    description: mapping.description ?? ""
  });
  const submit = (e) => {
    e.preventDefault();
    form.put(`/admin/connectivity/mqtt/${connectionId}/topics/${topic.id}/mappings/${mapping.id}`, {
      preserveScroll: true,
      onSuccess: onClose
    });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "mt-2 pt-2 border-t border-om-line2" }, /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "space-y-2" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-2" }, /* @__PURE__ */ React.createElement(MiniField, { label: "Field path" }, /* @__PURE__ */ React.createElement("input", { type: "text", value: form.data.field_path, onChange: (e) => form.setData("field_path", e.target.value), className: "w-full px-2 py-1 text-xs font-mono border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent" })), /* @__PURE__ */ React.createElement(MiniField, { label: "Action type" }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.data.action_type,
      onChange: (v) => form.setData("action_type", v),
      options: Object.entries(ACTION_LABELS).map(([val, lbl]) => ({ value: val, label: lbl })),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(MiniField, { label: "Condition" }, /* @__PURE__ */ React.createElement("input", { type: "text", value: form.data.condition_expr, onChange: (e) => form.setData("condition_expr", e.target.value), className: "w-full px-2 py-1 text-xs font-mono border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent" })), /* @__PURE__ */ React.createElement(MiniField, { label: "Priority" }, /* @__PURE__ */ React.createElement("input", { type: "number", value: form.data.priority, onChange: (e) => form.setData("priority", e.target.value), min: "1", max: "9999", className: "w-full px-2 py-1 text-xs border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent" }))), /* @__PURE__ */ React.createElement(MiniField, { label: "Action params (JSON)" }, /* @__PURE__ */ React.createElement("textarea", { value: form.data.action_params, onChange: (e) => form.setData("action_params", e.target.value), rows: 2, className: "w-full px-2 py-1 text-xs font-mono border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent" })), /* @__PURE__ */ React.createElement(MiniField, { label: "Description" }, /* @__PURE__ */ React.createElement("input", { type: "text", value: form.data.description, onChange: (e) => form.setData("description", e.target.value), className: "w-full px-2 py-1 text-xs border border-om-line rounded bg-om-card text-om-ink focus:ring-1 focus:ring-om-accent" })), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(Button, { variant: "primary", type: "submit", loading: form.processing }, "Save"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", type: "button", onClick: onClose }, "Cancel"))));
}
function AddTopicForm({ connectionId }) {
  const [open, setOpen] = useState(false);
  const form = useForm({ topic_pattern: "", payload_format: "json", description: "" });
  const submit = (e) => {
    e.preventDefault();
    form.post(`/admin/connectivity/mqtt/${connectionId}/topics`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        setOpen(false);
      }
    });
  };
  return /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-om-line2 p-4" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setOpen((o) => !o),
      className: "flex items-center gap-2 text-sm font-medium text-om-accent hover:text-om-accent"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M12 4v16m8-8H4" })),
    "Add topic"
  ), open && /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "mt-4 space-y-3" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-3 gap-3" }, /* @__PURE__ */ React.createElement("div", { className: "col-span-2" }, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Topic pattern ", /* @__PURE__ */ React.createElement("span", { className: "text-om-faint font-normal" }, "(supports + and # wildcards)")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.topic_pattern,
      onChange: (e) => form.setData("topic_pattern", e.target.value),
      placeholder: "factory/line1/+/status",
      required: true,
      className: "w-full px-3 py-2 text-sm font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent"
    }
  )), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Payload format"), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.data.payload_format,
      onChange: (v) => form.setData("payload_format", v),
      options: [
        { value: "json", label: "JSON" },
        { value: "plain", label: "Plain text" },
        { value: "csv", label: "CSV" },
        { value: "hex", label: "Hex" }
      ],
      className: "w-full"
    }
  ))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs font-medium text-om-muted mb-1" }, "Description (optional)"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.description,
      onChange: (e) => form.setData("description", e.target.value),
      placeholder: "e.g. Production count from Line 1",
      className: "w-full px-3 py-2 text-sm border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(Button, { variant: "primary", type: "submit", loading: form.processing }, "Add Topic"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", type: "button", onClick: () => setOpen(false) }, "Cancel"))));
}
function AddMappingForm({ connectionId, topicId, onClose }) {
  const form = useForm({
    field_path: "",
    action_type: Object.keys(ACTION_LABELS)[0],
    condition_expr: "",
    priority: "100",
    action_params: "",
    description: ""
  });
  const submit = (e) => {
    e.preventDefault();
    form.post(`/admin/connectivity/mqtt/${connectionId}/topics/${topicId}/mappings`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        onClose();
      }
    });
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "mt-3 space-y-3" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-3" }, /* @__PURE__ */ React.createElement(MiniField, { label: "Field path \u2014 e.g. $.qty or $.data.value" }, /* @__PURE__ */ React.createElement("input", { type: "text", value: form.data.field_path, onChange: (e) => form.setData("field_path", e.target.value), placeholder: "$.value", className: "w-full px-2 py-1.5 text-xs font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent" })), /* @__PURE__ */ React.createElement(MiniField, { label: "Action type *" }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: form.data.action_type,
      onChange: (v) => form.setData("action_type", v),
      options: Object.entries(ACTION_LABELS).map(([val, lbl]) => ({ value: val, label: lbl })),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(MiniField, { label: "Condition \u2014 e.g. value > 0" }, /* @__PURE__ */ React.createElement("input", { type: "text", value: form.data.condition_expr, onChange: (e) => form.setData("condition_expr", e.target.value), placeholder: "value > 0", className: "w-full px-2 py-1.5 text-xs font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent" })), /* @__PURE__ */ React.createElement(MiniField, { label: "Priority" }, /* @__PURE__ */ React.createElement("input", { type: "number", value: form.data.priority, onChange: (e) => form.setData("priority", e.target.value), min: "1", max: "9999", className: "w-full px-2 py-1.5 text-xs border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent" }))), /* @__PURE__ */ React.createElement(MiniField, { label: 'Action params (JSON) \u2014 e.g. {"order_no_path":"$.order_no"}' }, /* @__PURE__ */ React.createElement("textarea", { value: form.data.action_params, onChange: (e) => form.setData("action_params", e.target.value), rows: 3, placeholder: '{"order_no_path": "$.order_no", "qty_path": "$.qty"}', className: "w-full px-2 py-1.5 text-xs font-mono border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent" })), /* @__PURE__ */ React.createElement(MiniField, { label: "Description" }, /* @__PURE__ */ React.createElement("input", { type: "text", value: form.data.description, onChange: (e) => form.setData("description", e.target.value), placeholder: "e.g. Update produced qty from machine counter", className: "w-full px-2 py-1.5 text-xs border border-om-line rounded-om-sm bg-om-card text-om-ink focus:ring-2 focus:ring-om-accent focus:border-transparent" })), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement(Button, { variant: "primary", type: "submit", loading: form.processing }, "Add Mapping"), /* @__PURE__ */ React.createElement(Button, { variant: "secondary", type: "button", onClick: onClose }, "Cancel")));
}
function MiniField({ label, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs text-om-muted mb-0.5" }, label), children);
}
function LiveMessageLog({ initialMessages, initialLastId, messagesUrl }) {
  const [messages, setMessages] = useState([]);
  const [autoScroll, setAutoScroll] = useState(true);
  const lastIdRef = useRef(initialLastId);
  const logRef = useRef(null);
  useEffect(() => {
    const interval = setInterval(async () => {
      try {
        const res = await fetch(`${messagesUrl}?after_id=${lastIdRef.current}`);
        if (!res.ok) return;
        const data = await res.json();
        if (data.length === 0) return;
        const sorted = [...data].reverse();
        setMessages((prev) => {
          const next = [...prev, ...sorted];
          return next.length > 500 ? next.slice(next.length - 500) : next;
        });
        lastIdRef.current = Math.max(...data.map((m) => m.id ?? 0), lastIdRef.current);
      } catch (_) {
      }
    }, 3e3);
    return () => clearInterval(interval);
  }, [messagesUrl]);
  useEffect(() => {
    if (autoScroll && logRef.current) {
      logRef.current.scrollTop = logRef.current.scrollHeight;
    }
  }, [messages, autoScroll]);
  const formatTime2 = (iso) => {
    if (!iso) return "";
    try {
      return formatTime2(new Date(iso), { hour12: false });
    } catch {
      return iso;
    }
  };
  const statusDot = (status) => {
    if (status === "ok") return "bg-om-running";
    if (status === "error") return "bg-om-blocked";
    return "bg-om-downtime";
  };
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-semibold text-om-ink mb-3" }, "Live Message Log"), /* @__PURE__ */ React.createElement("div", { className: "bg-om-ink rounded-om border border-gray-700 overflow-hidden" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between px-4 py-2.5 border-b border-gray-700 bg-om-ink/60" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-1.5" }, /* @__PURE__ */ React.createElement("span", { className: "w-2 h-2 rounded-full bg-om-running animate-pulse" }), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint" }, "Live (polling)")), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-muted" }, messages.length, " new messages")), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: autoScroll,
      onChange: (next) => setAutoScroll(next),
      label: "Auto-scroll"
    }
  ), /* @__PURE__ */ React.createElement("button", { onClick: () => setMessages([]), className: "text-xs text-om-muted hover:text-om-faintest transition-colors" }, "Clear"))), /* @__PURE__ */ React.createElement("div", { ref: logRef, className: "h-96 overflow-y-auto font-mono text-xs p-4 space-y-2" }, [...initialMessages].reverse().map((msg) => /* @__PURE__ */ React.createElement("div", { key: `init-${msg.id}`, className: "flex gap-3 items-start opacity-60" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted shrink-0 tabular-nums" }, formatTime2(msg.received_at)), /* @__PURE__ */ React.createElement("span", { className: `w-1.5 h-1.5 rounded-full mt-1 shrink-0 ${statusDot(msg.processing_status)}` }), /* @__PURE__ */ React.createElement("span", { className: "text-blue-300 shrink-0 max-w-xs truncate" }, msg.topic), /* @__PURE__ */ React.createElement("span", { className: "text-om-faintest break-all" }, String(msg.raw_payload ?? "").substring(0, 200)))), messages.map((msg) => /* @__PURE__ */ React.createElement("div", { key: msg.id, className: "flex gap-3 items-start" }, /* @__PURE__ */ React.createElement("span", { className: "text-om-muted shrink-0 tabular-nums" }, formatTime2(msg.received_at)), /* @__PURE__ */ React.createElement("span", { className: `w-1.5 h-1.5 rounded-full mt-1 shrink-0 ${statusDot(msg.processing_status)}` }), /* @__PURE__ */ React.createElement("span", { className: "text-blue-300 shrink-0 max-w-xs truncate" }, msg.topic), /* @__PURE__ */ React.createElement("span", { className: "text-om-faintest break-all" }, String(msg.raw_payload ?? "").substring(0, 200)), msg.processing_error && /* @__PURE__ */ React.createElement("span", { className: "text-red-400 ml-1" }, "\u26A0 ", msg.processing_error))), initialMessages.length === 0 && messages.length === 0 && /* @__PURE__ */ React.createElement("div", { className: "text-om-muted text-center py-8" }, "Waiting for messages..."))));
}
export {
  MqttShow as default
};
