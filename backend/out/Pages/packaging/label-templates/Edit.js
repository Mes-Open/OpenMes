import { Head, useForm, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import LabelTemplateForm from "./Form";
import { __ } from "../../../lib/i18n";
function LabelTemplateEdit() {
  const { template, types = {}, sizes = {}, barcodeFormats = {}, availableFields = {} } = usePage().props;
  const form = useForm({
    name: template.name ?? "",
    type: template.type ?? "work_order",
    size: template.size ?? "100x50",
    barcode_format: template.barcode_format ?? "code128",
    fields: template.fields_config ?? {},
    is_default: !!template.is_default,
    is_active: !!template.is_active
  });
  const submit = (e) => {
    e.preventDefault();
    form.put(`/packaging/label-templates/${template.id}`);
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Edit :name", { name: template.name }) }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("Edit Label Template")), /* @__PURE__ */ React.createElement(LabelTemplateForm, { form, types, sizes, barcodeFormats, availableFields, submitLabel: "Save Changes", onSubmit: submit }));
}
LabelTemplateEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  LabelTemplateEdit as default
};
