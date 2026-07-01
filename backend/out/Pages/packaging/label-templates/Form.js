import { Link } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
function LabelTemplateForm({ form, types, sizes, barcodeFormats, availableFields, submitLabel, onSubmit }) {
  const { data, setData, errors, processing } = form;
  const sel = (label, name, map, error) => /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label, " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: Object.entries(map).map(([v, l]) => ({ value: String(v), label: l })),
      value: data[name] == null ? "" : String(data[name]),
      onChange: (v) => setData(name, v),
      className: "w-full"
    }
  ), error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
  return /* @__PURE__ */ React.createElement("form", { onSubmit, className: "bg-om-card rounded-om-sm shadow-sm p-6 max-w-2xl space-y-5" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Name"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement("input", { type: "text", value: data.name, onChange: (e) => setData("name", e.target.value), className: "form-input w-full", autoFocus: true }), errors.name && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.name)), sel(__("Type"), "type", types, errors.type), sel(__("Label Size"), "size", sizes, errors.size), sel(__("Barcode Format"), "barcode_format", barcodeFormats, errors.barcode_format), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-2" }, __("Fields on label")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-2 border border-om-line2 rounded-om-sm p-3" }, Object.entries(availableFields).map(([key, label]) => /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      key,
      checked: !!data.fields?.[key],
      onChange: (next) => setData("fields", { ...data.fields, [key]: next }),
      label
    }
  )))), /* @__PURE__ */ React.createElement("div", { className: "flex flex-col gap-2" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: !!data.is_default,
      onChange: (next) => setData("is_default", next),
      label: __("Default template for this type")
    }
  ), /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: !!data.is_active,
      onChange: (next) => setData("is_active", next),
      label: __("Active")
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing, disabled: processing }, processing ? "Saving\u2026" : submitLabel), /* @__PURE__ */ React.createElement(Link, { href: "/packaging/label-templates", className: "text-om-muted hover:text-om-ink text-sm" }, "Cancel")));
}
export {
  LabelTemplateForm as default
};
