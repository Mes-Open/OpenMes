import { Link } from "@inertiajs/react";
import { Button, Checkbox, Dropdown } from "@openmes/ui";
import { __ } from "../../../lib/i18n";
import CustomFields from "../../../components/CustomFields";
import { customFieldProps } from "../../../lib/customFieldForm";
function WorkerForm({ form, crews, wageGroups, personnelClasses, skills, customFields = [], isEdit, onSubmit }) {
  const { data, setData, errors, processing } = form;
  const selectedSkills = new Map((data.skills ?? []).map((s) => [String(s.id), s.level ?? 1]));
  const toggleSkill = (id, on) => {
    const next = new Map(selectedSkills);
    if (on) next.set(String(id), 1);
    else next.delete(String(id));
    setData("skills", [...next].map(([sid, level]) => ({ id: Number(sid), level })));
  };
  const setSkillLevel = (id, level) => {
    const next = new Map(selectedSkills);
    next.set(String(id), Number(level));
    setData("skills", [...next].map(([sid, lvl]) => ({ id: Number(sid), level: lvl })));
  };
  return /* @__PURE__ */ React.createElement("form", { onSubmit, className: "bg-om-card rounded-om-sm shadow-sm p-6 max-w-3xl space-y-5" }, /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement(Field, { label: __("Code"), error: errors.code, required: true }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.code, onChange: (e) => setData("code", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Name"), error: errors.name, required: true }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.name, onChange: (e) => setData("name", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Email"), error: errors.email }, /* @__PURE__ */ React.createElement("input", { type: "email", value: data.email, onChange: (e) => setData("email", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Phone"), error: errors.phone }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.phone, onChange: (e) => setData("phone", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Crew"), error: errors.crew_id }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [{ value: "", label: __("\u2014 None \u2014") }, ...crews.map((c) => ({ value: String(c.id), label: c.name }))],
      value: data.crew_id == null ? "" : String(data.crew_id),
      onChange: (v) => setData("crew_id", v),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: __("Wage Group"), error: errors.wage_group_id }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [{ value: "", label: __("\u2014 None \u2014") }, ...wageGroups.map((g) => ({ value: String(g.id), label: g.name }))],
      value: data.wage_group_id == null ? "" : String(data.wage_group_id),
      onChange: (v) => setData("wage_group_id", v),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: __("Personnel Class"), error: errors.personnel_class_id }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [{ value: "", label: __("\u2014 None \u2014") }, ...personnelClasses.map((p) => ({ value: String(p.id), label: p.name }))],
      value: data.personnel_class_id == null ? "" : String(data.personnel_class_id),
      onChange: (v) => setData("personnel_class_id", v),
      className: "w-full"
    }
  ))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("h3", { className: "text-sm font-semibold text-om-muted mb-2" }, __("Compensation")), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement(Field, { label: __("Pay type"), error: errors.pay_type }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [
        { value: "", label: __("Use system default") },
        { value: "hourly", label: __("Hourly") },
        { value: "weekly", label: __("Weekly") },
        { value: "piece_rate", label: __("Piece rate") }
      ],
      value: data.pay_type == null ? "" : String(data.pay_type),
      onChange: (v) => setData("pay_type", v),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: __("Pay rate"), error: errors.pay_rate }, /* @__PURE__ */ React.createElement("input", { type: "number", step: "0.0001", min: "0", value: data.pay_rate ?? "", onChange: (e) => setData("pay_rate", e.target.value), className: "form-input w-full" }))), /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-faint" }, __("Hourly/weekly: rate per hour/week. Piece rate: amount per produced piece. Currency is set system-wide in Settings."))), /* @__PURE__ */ React.createElement(Checkbox, { checked: !!data.is_active, onChange: (next) => setData("is_active", next), label: __("Active") }), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-2" }, __("Skills & level (1-5)")), /* @__PURE__ */ React.createElement("div", { className: "border border-om-line2 rounded divide-y" }, skills.length === 0 && /* @__PURE__ */ React.createElement("p", { className: "px-3 py-2 text-sm text-om-faint" }, __("No skills defined.")), skills.map((skill) => {
    const id = String(skill.id);
    const on = selectedSkills.has(id);
    return /* @__PURE__ */ React.createElement("div", { key: skill.id, className: "flex items-center gap-3 px-3 py-2" }, /* @__PURE__ */ React.createElement(Checkbox, { className: "flex-1", checked: on, onChange: (next) => toggleSkill(skill.id, next), label: skill.name }), on && /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        options: [1, 2, 3, 4, 5].map((l) => ({ value: String(l), label: String(l) })),
        value: String(selectedSkills.get(id)),
        onChange: (v) => setSkillLevel(skill.id, v),
        className: "min-w-[64px]"
      }
    ));
  }))), customFields.length > 0 && /* @__PURE__ */ React.createElement(CustomFields, { ...customFieldProps(form, customFields) }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing, disabled: processing }, processing ? __("Saving\u2026") : isEdit ? __("Save Changes") : __("Create Worker")), /* @__PURE__ */ React.createElement(Link, { href: "/admin/workers", className: "text-om-muted hover:text-om-ink text-sm" }, __("Cancel"))));
}
function Field({ label, error, required, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), children, error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
export {
  WorkerForm as default
};
