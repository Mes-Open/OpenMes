import { Link } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
const cap = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
function ProcessSegmentForm({ form, workstationTypes, skills, segmentTypes, submitLabel, onSubmit }) {
  const { data, setData, errors, processing } = form;
  const selected = new Set((data.required_skill_ids ?? []).map((id) => Number(id)));
  const toggleSkill = (skillId, checked) => {
    const id = Number(skillId);
    const next = new Set(selected);
    if (checked) next.add(id);
    else next.delete(id);
    setData("required_skill_ids", [...next]);
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit, className: "bg-om-card rounded-om-sm shadow-sm p-6 max-w-3xl space-y-5" }, /* @__PURE__ */ React.createElement(Field, { label: "Code", error: errors.code, required: true }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.code, onChange: (e) => setData("code", e.target.value), className: "form-input w-full", autoFocus: true })), /* @__PURE__ */ React.createElement(Field, { label: "Name", error: errors.name, required: true }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.name, onChange: (e) => setData("name", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: "Description", error: errors.description }, /* @__PURE__ */ React.createElement("textarea", { value: data.description ?? "", onChange: (e) => setData("description", e.target.value), rows: 3, className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: "Segment Type", error: errors.segment_type, required: true }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: segmentTypes.map((t) => ({ value: String(t), label: cap(t) })),
      value: data.segment_type == null ? "" : String(data.segment_type),
      onChange: (v) => setData("segment_type", v),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: "Workstation Type", error: errors.workstation_type_id }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: workstationTypes.map((w) => ({ value: String(w.id), label: w.name })),
      value: data.workstation_type_id == null ? "" : String(data.workstation_type_id),
      onChange: (v) => setData("workstation_type_id", v),
      placeholder: "\u2014 None \u2014",
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: "Estimated Duration (minutes)", error: errors.estimated_duration_minutes }, /* @__PURE__ */ React.createElement("input", { type: "number", value: data.estimated_duration_minutes ?? "", onChange: (e) => setData("estimated_duration_minutes", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: "Required Operators", error: errors.required_operators, required: true }, /* @__PURE__ */ React.createElement("input", { type: "number", value: data.required_operators ?? "", onChange: (e) => setData("required_operators", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: "Standard Instruction", error: errors.standard_instruction }, /* @__PURE__ */ React.createElement("textarea", { value: data.standard_instruction ?? "", onChange: (e) => setData("standard_instruction", e.target.value), rows: 3, className: "form-input w-full" })), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-2" }, "Required skills"), /* @__PURE__ */ React.createElement("div", { className: "border border-om-line2 rounded-om-sm divide-y" }, skills.length === 0 && /* @__PURE__ */ React.createElement("p", { className: "px-3 py-3 text-sm text-om-faint" }, "No skills defined."), skills.map((skill) => /* @__PURE__ */ React.createElement("div", { key: skill.id, className: "flex items-center gap-2 px-3 py-2 text-sm text-om-muted" }, /* @__PURE__ */ React.createElement(Checkbox, { checked: selected.has(Number(skill.id)), onChange: (next) => toggleSkill(skill.id, next), label: skill.name })))), errors.required_skill_ids && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.required_skill_ids)), /* @__PURE__ */ React.createElement(Field, { label: "Parameters (JSON)", error: errors.parameters_raw }, /* @__PURE__ */ React.createElement(
    "textarea",
    {
      value: data.parameters_raw ?? "",
      onChange: (e) => setData("parameters_raw", e.target.value),
      rows: 6,
      className: "form-input w-full font-mono text-sm"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing, disabled: processing }, processing ? "Saving\u2026" : submitLabel), /* @__PURE__ */ React.createElement(Link, { href: "/admin/process-segments", className: "text-om-muted hover:text-om-ink text-sm" }, "Cancel")));
}
function Field({ label, error, required, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), children, error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
export {
  ProcessSegmentForm as default
};
