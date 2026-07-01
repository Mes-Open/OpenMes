import { Head, useForm, usePage } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import AppLayout from "../../../layouts/AppLayout";
import UserForm from "./UserForm";
function UserEdit() {
  const { user, roles = [], workstations = [], crews = [], wageGroups = [], skills = [] } = usePage().props;
  const w = user.worker;
  const form = useForm({
    account_type: user.account_type ?? "user",
    name: user.name ?? "",
    username: user.username ?? "",
    email: user.email ?? "",
    password: "",
    password_confirmation: "",
    force_password_change: !!user.force_password_change,
    role: user.role ?? "",
    workstation_id: user.workstation_id != null ? String(user.workstation_id) : "",
    worker_code: w?.code ?? "",
    worker_phone: w?.phone ?? "",
    worker_crew_id: w?.crew_id != null ? String(w.crew_id) : "",
    worker_wage_group_id: w?.wage_group_id != null ? String(w.wage_group_id) : "",
    skills: w?.skills ?? []
  });
  const submit = (e) => {
    e.preventDefault();
    form.put(`/admin/users/${user.id}`);
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: `Edit ${user.name}` }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Account")), /* @__PURE__ */ React.createElement(UserForm, { form, roles, workstations, crews, wageGroups, skills, isEdit: true, onSubmit: submit }));
}
UserEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  UserEdit as default
};
