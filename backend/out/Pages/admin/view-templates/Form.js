import { Link } from "@inertiajs/react";
import RepeatableRows from "../../../components/RepeatableRows";
import { __ } from "../../../lib/i18n";
const COLUMN_FIELDS = [
  { name: "label", label: "Label", placeholder: "Quantity" },
  { name: "key", label: "Key", placeholder: "planned_qty" },
  {
    name: "source",
    label: "Source",
    type: "select",
    width: "w-40",
    options: [{ value: "field", label: "Field" }, { value: "extra_data", label: "Extra data" }]
  }
];
function ViewTemplateForm({ form, submitLabel, onSubmit }) {
  const { data, setData, errors, processing } = form;
  const translatedFields = COLUMN_FIELDS.map((f) => ({
    ...f,
    label: __(f.label),
    placeholder: f.placeholder ? __(f.placeholder) : void 0,
    options: f.options ? f.options.map((o) => ({ ...o, label: __(o.label) })) : void 0
  }));
  return /* @__PURE__ */ React.createElement("form", { onSubmit, className: "bg-om-card rounded-om-sm shadow-sm p-6 max-w-3xl space-y-5" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Name"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement("input", { type: "text", value: data.name, onChange: (e) => setData("name", e.target.value), className: "form-input w-full", autoFocus: true }), errors.name && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.name)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Description")), /* @__PURE__ */ React.createElement("textarea", { value: data.description ?? "", onChange: (e) => setData("description", e.target.value), rows: 2, className: "form-input w-full" }), errors.description && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.description)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-2" }, __("Columns"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    RepeatableRows,
    {
      value: data.columns,
      onChange: (rows) => setData("columns", rows),
      fields: translatedFields,
      addLabel: __("+ Add column"),
      newRow: () => ({ label: "", key: "", source: "field" })
    }
  ), errors.columns && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.columns)), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement("button", { type: "submit", disabled: processing, className: "bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover disabled:opacity-50" }, processing ? __("Saving\u2026") : submitLabel), /* @__PURE__ */ React.createElement(Link, { href: "/admin/view-templates", className: "text-om-muted hover:text-om-ink text-sm" }, __("Cancel"))));
}
export {
  ViewTemplateForm as default
};
