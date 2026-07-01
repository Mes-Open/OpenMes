import { Head, useForm, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import WorkerForm from "./WorkerForm";
import { customFieldInitial, submitForm } from "../../../lib/customFieldForm";
import { __ } from "../../../lib/i18n";
function WorkerCreate() {
  const { crews = [], wageGroups = [], personnelClasses = [], skills = [], customFields = [] } = usePage().props;
  const form = useForm({
    code: "",
    name: "",
    email: "",
    phone: "",
    crew_id: "",
    wage_group_id: "",
    personnel_class_id: "",
    pay_type: "",
    pay_rate: "",
    is_active: true,
    skills: [],
    ...customFieldInitial()
  });
  const submit = (e) => {
    e.preventDefault();
    submitForm(form, "post", "/admin/workers");
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Worker") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Worker")), /* @__PURE__ */ React.createElement(WorkerForm, { form, crews, wageGroups, personnelClasses, customFields, skills, onSubmit: submit }));
}
WorkerCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkerCreate as default
};
