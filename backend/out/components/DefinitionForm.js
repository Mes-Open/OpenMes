import { Link, useForm } from "@inertiajs/react";
import { Checkbox, DatePicker, Dropdown } from "@openmes/ui";
import { __ } from "../lib/i18n";
const OPTION_TYPES = ["select", "multiselect"];
const RANGE_TYPES = ["number", "integer"];
function DefinitionForm({ action, method = "post", initial, entities = [], types = [], submitLabel }) {
  const form = useForm(initial);
  const { data, setData, errors, processing } = form;
  const submit = (e) => {
    e.preventDefault();
    form.submit(method, action);
  };
  const setConfig = (patch) => setData("config", { ...data.config ?? {}, ...patch });
  const options = data.config?.options ?? [];
  const setOption = (i, patch) => setConfig({ options: options.map((o, idx) => idx === i ? { ...o, ...patch } : o) });
  const addOption = () => setConfig({ options: [...options, { value: "", label: "" }] });
  const removeOption = (i) => setConfig({ options: options.filter((_, idx) => idx !== i) });
  const isOptioned = OPTION_TYPES.includes(data.type);
  const isRange = RANGE_TYPES.includes(data.type);
  return /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "bg-om-card rounded-om-sm shadow-sm p-6 max-w-2xl space-y-5" }, /* @__PURE__ */ React.createElement(
    SelectField,
    {
      label: __("Entity"),
      required: true,
      value: data.entity_type,
      error: errors.entity_type,
      placeholder: __("\u2014 Select entity \u2014"),
      options: entities,
      onChange: (v) => setData("entity_type", v)
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("Key"),
      required: true,
      value: data.key,
      error: errors.key,
      help: __("Machine name: lowercase letters, numbers, underscores (e.g. shelf_life_days)."),
      onChange: (v) => setData("key", v)
    }
  ), /* @__PURE__ */ React.createElement(TextField, { label: __("Label"), required: true, value: data.label, error: errors.label, onChange: (v) => setData("label", v) }), /* @__PURE__ */ React.createElement(
    SelectField,
    {
      label: __("Type"),
      required: true,
      value: data.type,
      error: errors.type,
      placeholder: __("\u2014 Select type \u2014"),
      options: types,
      onChange: (v) => setData("type", v)
    }
  ), isOptioned && /* @__PURE__ */ React.createElement(
    OptionsEditor,
    {
      options,
      setOption,
      addOption,
      removeOption,
      error: errors["config.options"]
    }
  ), isRange && /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("Min"),
      type: "number",
      value: data.config?.min ?? "",
      error: errors["config.min"],
      onChange: (v) => setConfig({ min: v })
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("Max"),
      type: "number",
      value: data.config?.max ?? "",
      error: errors["config.max"],
      onChange: (v) => setConfig({ max: v })
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex items-end gap-6" }, /* @__PURE__ */ React.createElement(CheckboxField, { label: __("Required"), checked: data.required, onChange: (v) => setData("required", v) }), /* @__PURE__ */ React.createElement(CheckboxField, { label: __("Active"), checked: data.is_active, onChange: (v) => setData("is_active", v) }), /* @__PURE__ */ React.createElement("div", { className: "w-28" }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("Position"),
      type: "number",
      value: data.position ?? 0,
      error: errors.position,
      onChange: (v) => setData("position", v)
    }
  ))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "submit",
      disabled: processing,
      className: "bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover disabled:opacity-50"
    },
    processing ? __("Saving\u2026") : submitLabel ?? __("Save")
  ), /* @__PURE__ */ React.createElement(Link, { href: "/admin/custom-fields", className: "text-om-muted hover:text-om-ink text-sm" }, __("Cancel"))));
}
function TextField({ label, value, error, onChange, required, help, type = "text" }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), type === "date" ? /* @__PURE__ */ React.createElement(DatePicker, { className: "w-full", value: value || null, onChange: (iso) => onChange(iso ?? "") }) : /* @__PURE__ */ React.createElement("input", { type, value: value ?? "", onChange: (e) => onChange(e.target.value), className: "form-input w-full" }), help && /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, help), error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
function SelectField({ label, value, error, onChange, required, placeholder, options }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      className: "w-full",
      options: options.map((o) => ({ value: String(o.value), label: o.label })),
      value: value == null ? "" : String(value),
      onChange: (v) => onChange(v),
      placeholder
    }
  ), error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
function CheckboxField({ label, checked, onChange }) {
  return /* @__PURE__ */ React.createElement("div", { className: "pb-2" }, /* @__PURE__ */ React.createElement(Checkbox, { checked: !!checked, onChange: (next) => onChange(next), label }));
}
function OptionsEditor({ options, setOption, addOption, removeOption, error }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Options"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement("div", { className: "space-y-2" }, options.length === 0 && /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-faint" }, __("No options yet.")), options.map((o, i) => /* @__PURE__ */ React.createElement("div", { key: i, className: "flex items-center gap-2" }, /* @__PURE__ */ React.createElement(
    "input",
    {
      className: "form-input flex-1",
      placeholder: __("value"),
      value: o.value ?? "",
      onChange: (e) => setOption(i, { value: e.target.value })
    }
  ), /* @__PURE__ */ React.createElement(
    "input",
    {
      className: "form-input flex-1",
      placeholder: __("label"),
      value: o.label ?? "",
      onChange: (e) => setOption(i, { label: e.target.value })
    }
  ), /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => removeOption(i),
      className: "text-om-blocked hover:text-om-blocked text-sm px-2",
      title: __("Remove")
    },
    "\u2715"
  )))), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: addOption, className: "mt-2 text-sm text-om-accent hover:text-om-accent" }, __("+ Add option")), error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
export {
  DefinitionForm as default
};
