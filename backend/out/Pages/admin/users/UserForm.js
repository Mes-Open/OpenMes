import { Link } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import { Button, Checkbox, Dropdown, RadioGroup } from "@openmes/ui";
import { useState } from "react";
function UserForm({ form, roles, workstations, crews, wageGroups, skills, isEdit, onSubmit }) {
  const { data, setData, errors, processing } = form;
  const isUser = data.account_type === "user";
  const [showWorker, setShowWorker] = useState(() => isEdit && !!data.worker_code);
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
  return /* @__PURE__ */ React.createElement("form", { onSubmit, className: "bg-om-card rounded-om-sm shadow-sm p-6 max-w-3xl space-y-5" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Account Type")), /* @__PURE__ */ React.createElement(
    RadioGroup,
    {
      options: [
        { value: "user", label: __("Personal user") },
        { value: "workstation", label: __("Workstation") }
      ],
      value: data.account_type,
      onChange: (v) => setData("account_type", v)
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: __("Name"), error: errors.name, required: true }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.name, onChange: (e) => setData("name", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Username"), error: errors.username, required: true }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.username, onChange: (e) => setData("username", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: __("Email"), error: errors.email, required: true }, /* @__PURE__ */ React.createElement("input", { type: "email", value: data.email, onChange: (e) => setData("email", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement(Field, { label: isEdit ? __("Password (blank = keep)") : __("Password"), error: errors.password, required: !isEdit }, /* @__PURE__ */ React.createElement("input", { type: "password", value: data.password, onChange: (e) => setData("password", e.target.value), className: "form-input w-full", autoComplete: "new-password" })), /* @__PURE__ */ React.createElement(Field, { label: __("Confirm Password") }, /* @__PURE__ */ React.createElement("input", { type: "password", value: data.password_confirmation, onChange: (e) => setData("password_confirmation", e.target.value), className: "form-input w-full", autoComplete: "new-password" }))), /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: !!data.force_password_change,
      onChange: (next) => setData("force_password_change", next),
      label: __("Require password change at next login")
    }
  ), isUser ? /* @__PURE__ */ React.createElement(Field, { label: __("Role"), error: errors.role, required: true }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: roles.map((r) => ({ value: String(r), label: r })),
      value: data.role == null ? "" : String(data.role),
      onChange: (v) => setData("role", v),
      placeholder: __("\u2014 Select role \u2014"),
      className: "w-full"
    }
  )) : /* @__PURE__ */ React.createElement(Field, { label: __("Workstation"), error: errors.workstation_id, required: true }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: workstations.map((w) => ({ value: String(w.id), label: w.name })),
      value: data.workstation_id == null ? "" : String(data.workstation_id),
      onChange: (v) => setData("workstation_id", v),
      placeholder: __("\u2014 Select workstation \u2014"),
      className: "w-full"
    }
  )), isUser && /* @__PURE__ */ React.createElement("div", { className: "border border-om-line2 rounded-om-sm" }, /* @__PURE__ */ React.createElement(
    "button",
    {
      type: "button",
      onClick: () => setShowWorker((v) => !v),
      className: "w-full flex items-center justify-between px-4 py-3 text-sm font-medium text-om-muted hover:bg-om-bg rounded-om-sm"
    },
    /* @__PURE__ */ React.createElement("span", null, "Worker profile", " ", /* @__PURE__ */ React.createElement("span", { className: "font-normal text-om-faint" }, "\u2014 optional, only for shop-floor staff")),
    /* @__PURE__ */ React.createElement("span", { className: "text-om-faint" }, showWorker ? "\u25B2" : "\u25BC")
  ), showWorker && /* @__PURE__ */ React.createElement("div", { className: "border-t border-om-line2 p-4 space-y-4" }, /* @__PURE__ */ React.createElement("p", { className: "text-xs text-om-faint" }, "Fill in a worker code to link/create a shop-floor worker profile for this account. Leave this collapsed for office/admin accounts."), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-4" }, /* @__PURE__ */ React.createElement(Field, { label: "Worker Code", error: errors.worker_code }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.worker_code, onChange: (e) => setData("worker_code", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: "Phone", error: errors.worker_phone }, /* @__PURE__ */ React.createElement("input", { type: "text", value: data.worker_phone, onChange: (e) => setData("worker_phone", e.target.value), className: "form-input w-full" })), /* @__PURE__ */ React.createElement(Field, { label: "Crew" }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [{ value: "", label: "\u2014 None \u2014" }, ...crews.map((c) => ({ value: String(c.id), label: c.name }))],
      value: data.worker_crew_id == null ? "" : String(data.worker_crew_id),
      onChange: (v) => setData("worker_crew_id", v),
      className: "w-full"
    }
  )), /* @__PURE__ */ React.createElement(Field, { label: "Wage Group" }, /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      options: [{ value: "", label: "\u2014 None \u2014" }, ...wageGroups.map((g) => ({ value: String(g.id), label: g.name }))],
      value: data.worker_wage_group_id == null ? "" : String(data.worker_wage_group_id),
      onChange: (v) => setData("worker_wage_group_id", v),
      className: "w-full"
    }
  ))), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-2" }, "Skills & level (1\u20135)"), /* @__PURE__ */ React.createElement("div", { className: "border border-om-line2 rounded divide-y" }, skills.length === 0 && /* @__PURE__ */ React.createElement("p", { className: "px-3 py-2 text-sm text-om-faint" }, "No skills defined."), skills.map((skill) => {
    const id = String(skill.id);
    const on = selectedSkills.has(id);
    return /* @__PURE__ */ React.createElement("div", { key: skill.id, className: "flex items-center gap-3 px-3 py-2" }, /* @__PURE__ */ React.createElement(
      Checkbox,
      {
        checked: on,
        onChange: (next) => toggleSkill(skill.id, next),
        label: skill.name,
        className: "flex-1"
      }
    ), on && /* @__PURE__ */ React.createElement(
      Dropdown,
      {
        options: [1, 2, 3, 4, 5].map((l) => ({ value: String(l), label: String(l) })),
        value: String(selectedSkills.get(id)),
        onChange: (v) => setSkillLevel(skill.id, v),
        className: "min-w-[64px]"
      }
    ));
  }))))), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing, disabled: processing }, processing ? "Saving\u2026" : isEdit ? "Save Changes" : "Create Account"), /* @__PURE__ */ React.createElement(Link, { href: "/admin/users", className: "text-om-muted hover:text-om-ink text-sm" }, "Cancel")));
}
function Field({ label, error, required, children }) {
  return /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, label, " ", required && /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), children, error && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, error));
}
export {
  UserForm as default
};
