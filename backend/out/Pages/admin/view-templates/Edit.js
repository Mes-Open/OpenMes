import { Head, useForm } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import ViewTemplateForm from "./Form";
import { __ } from "../../../lib/i18n";
function ViewTemplateEdit({ viewTemplate }) {
  const form = useForm({
    name: viewTemplate.name ?? "",
    description: viewTemplate.description ?? "",
    columns: viewTemplate.columns ?? []
  });
  const submit = (e) => {
    e.preventDefault();
    form.put(`/admin/view-templates/${viewTemplate.id}`);
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit :name", { name: viewTemplate.name }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit View Template")), /* @__PURE__ */ React.createElement(ViewTemplateForm, { form, submitLabel: __("Save Changes"), onSubmit: submit }));
}
ViewTemplateEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ViewTemplateEdit as default
};
