import { Head, useForm, usePage } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import AppLayout from "../../../layouts/AppLayout";
import UserForm from "./UserForm";
function UserCreate() {
  const { roles = [], workstations = [], crews = [], wageGroups = [], skills = [] } = usePage().props;
  const form = useForm({
    account_type: "user",
    name: "",
    username: "",
    email: "",
    password: "",
    password_confirmation: "",
    force_password_change: false,
    role: "",
    workstation_id: "",
    worker_code: "",
    worker_phone: "",
    worker_crew_id: "",
    worker_wage_group_id: "",
    skills: []
  });
  const submit = (e) => {
    e.preventDefault();
    form.post("/admin/users");
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Account") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Account")), /* @__PURE__ */ React.createElement(UserForm, { form, roles, workstations, crews, wageGroups, skills, onSubmit: submit }));
}
UserCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  UserCreate as default
};
