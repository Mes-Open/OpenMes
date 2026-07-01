import { Link } from "@inertiajs/react";
import { Dropdown, RadioGroup } from "@openmes/ui";
import RepeatableRows from "../../../components/RepeatableRows";
const CRITERIA_FIELDS = [
  { name: "name", label: "Characteristic", placeholder: "Surface finish" },
  {
    name: "type",
    label: "Type",
    type: "select",
    width: "w-36",
    options: [
      { value: "visual", label: "Visual" },
      { value: "measurement", label: "Measurement" },
      { value: "functional", label: "Functional" },
      { value: "pass_fail", label: "Pass/Fail" }
    ]
  },
  { name: "required", label: "Req.", type: "checkbox", width: "w-12" },
  { name: "unit", label: "Unit", width: "w-20" },
  { name: "spec_min", label: "Min", type: "number", width: "w-20" },
  { name: "spec_max", label: "Max", type: "number", width: "w-20" }
];
function InspectionPlanForm({ form, materials, materialTypes, submitLabel, onSubmit }) {
  const { data, setData, errors, processing } = form;
  return /* @__PURE__ */ React.createElement("form", { onSubmit, className: "bg-om-card rounded-om-sm shadow-sm p-6 max-w-4xl space-y-5" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Name ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement("input", { type: "text", value: data.name, onChange: (e) => setData("name", e.target.value), className: "form-input w-full", autoFocus: true }), errors.name && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.name)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Description"), /* @__PURE__ */ React.createElement("textarea", { value: data.description ?? "", onChange: (e) => setData("description", e.target.value), rows: 2, className: "form-input w-full" })), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Scope ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    RadioGroup,
    {
      options: [
        { value: "material", label: "Specific material" },
        { value: "material_type", label: "Material type" },
        { value: "generic", label: "Generic" }
      ],
      value: data.scope,
      onChange: (v) => setData("scope", v)
    }
  )), data.scope === "material" && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Material ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.material_id == null ? "" : String(data.material_id),
      onChange: (v) => setData("material_id", v),
      options: materials.map((m) => ({ value: String(m.id), label: m.name })),
      placeholder: "\u2014 Select material \u2014",
      className: "w-full"
    }
  ), errors.material_id && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.material_id)), data.scope === "material_type" && /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, "Material Type ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.material_type_id == null ? "" : String(data.material_type_id),
      onChange: (v) => setData("material_type_id", v),
      options: materialTypes.map((t) => ({ value: String(t.id), label: t.name })),
      placeholder: "\u2014 Select type \u2014",
      className: "w-full"
    }
  ), errors.material_type_id && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.material_type_id)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-2" }, "Criteria ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    RepeatableRows,
    {
      value: data.criteria,
      onChange: (rows) => setData("criteria", rows),
      fields: CRITERIA_FIELDS,
      addLabel: "+ Add criterion",
      newRow: () => ({ name: "", type: "visual", required: true, unit: "", spec_min: "", spec_max: "" })
    }
  ), errors.criteria && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.criteria)), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement("button", { type: "submit", disabled: processing, className: "bg-om-ink text-om-on-ink px-4 py-2 rounded-om-sm text-sm font-medium hover:bg-om-ink-hover disabled:opacity-50" }, processing ? "Saving\u2026" : submitLabel), /* @__PURE__ */ React.createElement(Link, { href: "/admin/inspection-plans", className: "text-om-muted hover:text-om-ink text-sm" }, "Cancel")));
}
export {
  InspectionPlanForm as default
};
