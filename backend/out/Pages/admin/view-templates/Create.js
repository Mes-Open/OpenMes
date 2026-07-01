import { Head, useForm } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ViewTemplateForm from "./Form";
import { __ } from "../../../lib/i18n";
function ViewTemplateCreate() {
  const form = useForm({ name: "", description: "", columns: [{ label: "", key: "", source: "field" }] });
  const submit = (e) => {
    e.preventDefault();
    form.post("/admin/view-templates");
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New View Template") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New View Template")), /* @__PURE__ */ React.createElement(ViewTemplateForm, { form, submitLabel: __("Create"), onSubmit: submit }));
}
ViewTemplateCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ViewTemplateCreate as default
};
