import { Checkbox, DatePicker, Dropdown } from "@openmes/ui";
import { __ } from "../lib/i18n";
const LABEL_CLASS = "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]";
const INPUT_CLASS = "w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]";
function CustomFields({
  definitions = [],
  values = {},
  onChange,
  files = {},
  onFileChange,
  removed = [],
  onRemovedChange,
  errors = {}
}) {
  if (!definitions.length) return null;
  const set = (key, val) => onChange({ ...values, [key]: val });
  const toggleRemove = (key) => onRemovedChange(removed.includes(key) ? removed.filter((k) => k !== key) : [...removed, key]);
  return /* @__PURE__ */ React.createElement("fieldset", { className: "space-y-5 border-t border-om-line pt-5" }, /* @__PURE__ */ React.createElement("legend", { className: "font-mono text-[10px] uppercase tracking-[0.12em] text-om-faint" }, __("Custom fields")), definitions.map((def) => /* @__PURE__ */ React.createElement(
    CustomFieldInput,
    {
      key: def.key,
      def,
      value: values?.[def.key],
      error: errors[`custom_fields.${def.key}`] ?? errors[`custom_field_files.${def.key}`],
      onChange: (v) => set(def.key, v),
      file: files?.[def.key] ?? null,
      onFileChange: (f) => onFileChange(def.key, f),
      removed: removed.includes(def.key),
      onToggleRemove: () => toggleRemove(def.key)
    }
  )));
}
function fileUrl(meta) {
  return meta?.path ? `/admin/custom-field-files/${meta.path.split("/").pop()}` : null;
}
function CustomFieldInput({ def, value, error, onChange, file, onFileChange, removed, onToggleRemove }) {
  const { label, type, required, config = {} } = def;
  const options = config.options ?? [];
  if (type === "file" || type === "image") {
    const meta = value && typeof value === "object" ? value : null;
    const showExisting = meta && !removed && !file;
    const url = fileUrl(meta);
    return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")), showExisting && /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 mb-2" }, type === "image" ? /* @__PURE__ */ React.createElement("img", { src: url, alt: meta.name, className: "h-20 w-20 rounded-om-sm border border-om-line object-cover" }) : /* @__PURE__ */ React.createElement("a", { href: url, target: "_blank", rel: "noreferrer", className: "text-[13px] text-om-accent hover:underline" }, meta.name), /* @__PURE__ */ React.createElement("button", { type: "button", onClick: onToggleRemove, className: "text-[11.5px] text-om-blocked hover:underline" }, __("Remove"))), removed && /* @__PURE__ */ React.createElement("p", { className: "text-[11.5px] text-om-muted mb-1" }, __("Will be removed on save.")), /* @__PURE__ */ React.createElement(
      "input",
      {
        type: "file",
        accept: type === "image" ? "image/*" : void 0,
        onChange: (e) => onFileChange(e.target.files?.[0] ?? null),
        className: "block w-full text-[13px] text-om-muted file:mr-3 file:rounded-om-sm file:border-0 file:bg-om-chip file:px-3 file:py-1.5 file:text-[13px] file:font-semibold file:text-om-ink hover:file:bg-om-line2"
      }
    ), file && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[12px] text-om-muted" }, __("Selected"), ": ", file.name), error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[11.5px] text-om-blocked" }, error));
  }
  if (type === "boolean") {
    return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement(Checkbox, { checked: !!value, onChange: (next) => onChange(next), label }), error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[11.5px] text-om-blocked" }, error));
  }
  let control;
  if (type === "textarea") {
    control = /* @__PURE__ */ React.createElement("textarea", { value: value ?? "", onChange: (e) => onChange(e.target.value), rows: 3, className: INPUT_CLASS });
  } else if (type === "select") {
    control = /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        className: "w-full",
        options: options.map((o) => ({ value: String(o.value), label: o.label })),
        value: value == null ? "" : String(value),
        onChange: (v) => onChange(v),
        placeholder: __("\u2014 Select \u2014")
      }
    );
  } else if (type === "multiselect") {
    const selected = Array.isArray(value) ? value : [];
    const toggle = (val) => onChange(selected.includes(val) ? selected.filter((v) => v !== val) : [...selected, val]);
    control = /* @__PURE__ */ React.createElement("div", { className: "space-y-1" }, options.map((o) => /* @__PURE__ */ React.createElement(
      Checkbox,
      {
        key: o.value,
        checked: selected.includes(o.value),
        onChange: () => toggle(o.value),
        label: o.label
      }
    )));
  } else if (type === "date") {
    control = /* @__PURE__ */ React.createElement(
      DatePicker,
      {
        className: "w-full",
        value: value || null,
        onChange: (iso) => onChange(iso ?? "")
      }
    );
  } else {
    const inputType = { number: "number", integer: "number", datetime: "datetime-local" }[type] ?? "text";
    control = /* @__PURE__ */ React.createElement(
      "input",
      {
        type: inputType,
        step: type === "integer" ? "1" : void 0,
        min: config.min,
        max: config.max,
        value: value ?? "",
        onChange: (e) => onChange(e.target.value),
        className: INPUT_CLASS
      }
    );
  }
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")), control, error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-[11.5px] text-om-blocked" }, error));
}
export {
  CustomFields as default
};
