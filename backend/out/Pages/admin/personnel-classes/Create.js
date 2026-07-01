import { Head, useForm, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import PersonnelClassForm from "./Form";
import { __ } from "../../../lib/i18n";
function PersonnelClassCreate() {
  const { skills = [], levels = [] } = usePage().props;
  const form = useForm({
    code: "",
    name: "",
    description: "",
    required_skill_ids: [],
    default_required_cert_level: {},
    is_active: true
  });
  const submit = (e) => {
    e.preventDefault();
    form.post("/admin/personnel-classes");
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Personnel Class") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Personnel Class")), /* @__PURE__ */ React.createElement(PersonnelClassForm, { form, skills, levels, submitLabel: __("Create"), onSubmit: submit }));
}
PersonnelClassCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  PersonnelClassCreate as default
};
