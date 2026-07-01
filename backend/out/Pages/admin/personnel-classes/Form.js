import { Link } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
import { __ } from "../../../lib/i18n";
function PersonnelClassForm({ form, skills, levels, submitLabel, onSubmit }) {
  const { data, setData, errors, processing } = form;
  const selected = new Set((data.required_skill_ids ?? []).map((id) => String(id)));
  const toggleSkill = (skillId, checked) => {
    const id = String(skillId);
    const next = new Set(selected);
    const levelMap = { ...data.default_required_cert_level ?? {} };
    if (checked) {
      next.add(id);
      if (!levelMap[id]) levelMap[id] = levels[0];
    } else {
      next.delete(id);
      delete levelMap[id];
    }
    setData("required_skill_ids", [...next]);
    setData("default_required_cert_level", levelMap);
  };
  const setLevel = (skillId, level) => {
    setData("default_required_cert_level", {
      ...data.default_required_cert_level ?? {},
      [String(skillId)]: level
    });
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit, className: "bg-om-card rounded-om-sm shadow-sm p-6 max-w-3xl space-y-5" }, /* @__PURE__ */ React.createElement(Field, { label: __("Code"), error: errors.code, required: true }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.code, onChange: (e) => setData("code", e.target.value), className: "form-input w-full", autoFocus: true })), /* @__PURE__ */ React.createElement(Field, { label: __("Name"), error: errors.name, required: true }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.name, onChange: (e) => setData("name", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Description"), error: errors.description }, /* @__PURE__ */ React.createElement("textarea", { value: data.description ?? "", onChange: (e) => setData("description", e.target.value), rows: 3, className: "form-input w-full" })), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-2" }, __("Required skills & minimum level")), /* @__PURE__ */ React.createElement("div", { className: "border border-om-line2 rounded-om-sm divide-y" }, skills.length === 0 && /* @__PURE__ */ React.createElement("p", { className: "px-3 py-3 text-sm text-om-faint" }, __("No skills defined.")), skills.map((skill) => {
    const id = String(skill.id);
    const isOn = selected.has(id);
    return /* @__PURE__ */ React.createElement("div", { key: skill.id, className: "flex items-center gap-3 px-3 py-2" }, /* @__PURE__ */ React.createElement(Checkbox, { checked: isOn, onChange: (next) => toggleSkill(skill.id, next), label: skill.name, className: "flex-1" }), isOn && /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        options: levels.map((lvl) => ({ value: String(lvl), label: lvl })),
        value: (data.default_required_cert_level ?? {})[id] == null ? String(levels[0]) : String((data.default_required_cert_level ?? {})[id]),
        onChange: (v) => setLevel(skill.id, v)
      }
    ));
  })), errors.required_skill_ids && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, errors.required_skill_ids)), /* @__PURE__ */ React.createElement(Checkbox, { checked: !!data.is_active, onChange: (next) => setData("is_active", next), label: __("Active") }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing }, processing ? __("Saving\u2026") : submitLabel), /* @__PURE__ */ React.createElement(Link, { href: "/admin/personnel-classes", className: "text-om-muted hover:text-om-ink text-sm" }, __("Cancel"))));
}
function Field({ label, error, required, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), children, error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
export {
  PersonnelClassForm as default
};
