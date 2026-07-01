import { useState } from "react";
import { router, useForm } from "@inertiajs/react";
import { Dropdown } from "@openmes/ui";
import { __ } from "../../../lib/i18n";
const SIGNAL_TYPES = [
  { value: "state", label: __("State") },
  { value: "good_count", label: __("Good count") },
  { value: "reject_count", label: __("Reject count") },
  { value: "cycle_complete", label: __("Cycle complete") },
  { value: "telemetry", label: __("Telemetry") },
  { value: "alarm", label: __("Alarm") }
];
const SIGNAL_LABELS = Object.fromEntries(SIGNAL_TYPES.map((s) => [s.value, s.label]));
const DATA_TYPES = ["bool", "int16", "uint16", "int32", "uint32", "float32", "float64", "string"];
const REGISTER_TYPES = [
  { value: "coil", label: __("Coil (0x)") },
  { value: "discrete", label: __("Discrete input (1x)") },
  { value: "input", label: __("Input register (3x)") },
  { value: "holding", label: __("Holding register (4x)") }
];
function TagManager({
  connectionId,
  tags = [],
  workstations = [],
  basePath,
  showRegisterType = false,
  addressLabel = __("Address"),
  addressPlaceholder = ""
}) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mb-3" }, /* @__PURE__ */ React.createElement("h2", { className: "text-lg font-semibold text-om-ink" }, __("Tags & Signals")), /* @__PURE__ */ React.createElement("span", { className: "text-xs text-om-faint" }, tags.length, " ", tags.length === 1 ? __("tag") : __("tags"))), /* @__PURE__ */ React.createElement("div", { className: "space-y-3" }, tags.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-dashed border-om-line p-8 text-center text-om-faint" }, /* @__PURE__ */ React.createElement("p", { className: "text-sm" }, __("No tags defined yet \u2014 the poller has nothing to read."))) : /* @__PURE__ */ React.createElement("div", { className: "bg-om-card rounded-om border border-om-line2 overflow-hidden divide-y divide-om-line2" }, tags.map((tag) => /* @__PURE__ */ React.createElement(TagRow, { key: tag.id, tag, connectionId, basePath }))), /* @__PURE__ */ React.createElement(
    AddTagForm,
    {
      connectionId,
      workstations,
      basePath,
      showRegisterType,
      addressLabel,
      addressPlaceholder
    }
  )));
}
function TagRow({ tag, connectionId, basePath }) {
  const handleDelete = () => {
    if (confirm(__('Delete tag ":name"?', { name: tag.name }))) {
      router.delete(`${basePath}/${connectionId}/tags/${tag.id}`, { preserveScroll: true });
    }
  };
  const valueMap = tag.transform?.value_map;
  const scale = tag.transform?.scale;
  return /* @__PURE__ */ React.createElement("div", { className: `px-4 py-3 flex items-start gap-3 ${!tag.is_active ? "opacity-50" : ""}` }, /* @__PURE__ */ React.createElement("div", { className: "flex-1 min-w-0 space-y-1" }, /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 flex-wrap text-sm" }, /* @__PURE__ */ React.createElement("span", { className: "font-medium text-om-ink" }, tag.name), /* @__PURE__ */ React.createElement("span", { className: "font-mono text-xs text-om-muted bg-om-chip px-1.5 py-0.5 rounded" }, tag.address), /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, "\u2192"), /* @__PURE__ */ React.createElement("span", { className: "text-xs px-2 py-0.5 rounded-full bg-om-chip text-om-accent" }, SIGNAL_LABELS[tag.signal_type] ?? tag.signal_type)), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-2 flex-wrap text-xs text-om-faint" }, /* @__PURE__ */ React.createElement("span", { className: "uppercase" }, tag.data_type), tag.register_type && /* @__PURE__ */ React.createElement("span", null, "\xB7 ", tag.register_type), tag.workstation && /* @__PURE__ */ React.createElement("span", null, "\xB7 ", tag.workstation), scale != null && /* @__PURE__ */ React.createElement("span", null, "\xB7 scale \xD7", scale), valueMap && /* @__PURE__ */ React.createElement("span", { className: "font-mono" }, "\xB7 ", Object.entries(valueMap).map(([k, v]) => `${k}=${v}`).join(", ")))), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: handleDelete,
      className: "p-1.5 text-om-faint hover:text-om-blocked rounded-md hover:bg-om-chip transition-colors shrink-0",
      title: __("Delete tag")
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-4 h-4", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" }))
  ));
}
function AddTagForm({ connectionId, workstations, basePath, showRegisterType, addressLabel, addressPlaceholder }) {
  const [open, setOpen] = useState(false);
  const form = useForm({
    name: "",
    address: "",
    signal_type: "state",
    data_type: "int16",
    register_type: showRegisterType ? "holding" : "",
    workstation_id: "",
    value_map: "",
    scale: ""
  });
  const { data, setData, errors, processing } = form;
  const submit = (e) => {
    e.preventDefault();
    form.post(`${basePath}/${connectionId}/tags`, {
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
    __("Add tag")
  ), open && /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "mt-4 space-y-3" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-3" }, /* @__PURE__ */ React.createElement(MiniField, { label: __("Name *"), error: errors.name }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.name, onChange: (e) => setData("name", e.target.value), required: true, className: "form-input w-full text-sm", placeholder: __("e.g. Line 1 state") })), /* @__PURE__ */ React.createElement(MiniField, { label: `${addressLabel} *`, error: errors.address }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.address, onChange: (e) => setData("address", e.target.value), required: true, className: "form-input w-full text-sm font-mono", placeholder: addressPlaceholder })), /* @__PURE__ */ React.createElement(MiniField, { label: __("Signal type *"), error: errors.signal_type }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: SIGNAL_TYPES.map((s) => ({ value: String(s.value), label: s.label })),
      value: data.signal_type == null ? "" : String(data.signal_type),
      onChange: (v) => setData("signal_type", v),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(MiniField, { label: __("Data type *"), error: errors.data_type }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: DATA_TYPES.map((d) => ({ value: String(d), label: d })),
      value: data.data_type == null ? "" : String(data.data_type),
      onChange: (v) => setData("data_type", v),
      className: "w-full"
    }
  )), showRegisterType && /* @__PURE__ */ React.createElement(MiniField, { label: __("Register type *"), error: errors.register_type }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: REGISTER_TYPES.map((r) => ({ value: String(r.value), label: r.label })),
      value: data.register_type == null ? "" : String(data.register_type),
      onChange: (v) => setData("register_type", v),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(MiniField, { label: __("Workstation"), error: errors.workstation_id }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: workstations.map((w) => ({ value: String(w.id), label: w.line ? `${w.line} / ${w.name}` : w.name })),
      value: data.workstation_id == null ? "" : String(data.workstation_id),
      onChange: (v) => setData("workstation_id", v),
      placeholder: __("\u2014 none \u2014"),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(MiniField, { label: __("Value map \u2014 e.g. 1=RUNNING,2=IDLE,3=FAULT"), error: errors.value_map }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.value_map, onChange: (e) => setData("value_map", e.target.value), className: "form-input w-full text-sm font-mono", placeholder: "1=RUNNING,2=IDLE" })), /* @__PURE__ */ React.createElement(MiniField, { label: __("Scale \u2014 multiply raw reading"), error: errors.scale }, /* @__PURE__ */ React.createElement("input", { type: "number", step: "any", value: data.scale, onChange: (e) => setData("scale", e.target.value), className: "form-input w-full text-sm", placeholder: "1.0" }))), /* @__PURE__ */ React.createElement("div", { className: "flex gap-2" }, /* @__PURE__ */ React.createElement("button", { type: "submit", disabled: processing, className: "px-4 py-1.5 bg-om-ink text-om-on-ink text-sm rounded-om-sm hover:bg-om-ink-hover transition-colors disabled:opacity-50" }, processing ? __("Adding\u2026") : __("Add Tag")), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: () => setOpen(false), className: "px-4 py-1.5 bg-om-chip text-om-muted text-sm rounded-om-sm hover:bg-om-line2 transition-colors" }, __("Cancel")))));
}
function MiniField({ label, error, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-xs text-om-muted mb-0.5" }, label), children, error && /* @__PURE__ */ React.createElement("p", { className: "mt-0.5 text-xs text-om-blocked" }, error));
}
export {
  TagManager as default
};
