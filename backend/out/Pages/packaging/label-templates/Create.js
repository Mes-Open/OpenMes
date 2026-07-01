import { Head, useForm, usePage } from "@inertiajs/react";
import AppLayout from "../../../layouts/AppLayout";
import LabelTemplateForm from "./Form";
import { __ } from "../../../lib/i18n";
function LabelTemplateCreate() {
  const { types = {}, sizes = {}, barcodeFormats = {}, availableFields = {}, defaultFields = {} } = usePage().props;
  const form = useForm({
    name: "",
    type: "work_order",
    size: "100x50",
    barcode_format: "code128",
    fields: defaultFields,
    is_default: false,
    is_active: true
  });
  const submit = (e) => {
    e.preventDefault();
    form.post("/packaging/label-templates");
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-7xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("New Label Template") }), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink mb-6" }, __("New Label Template")), /* @__PURE__ */ React.createElement(LabelTemplateForm, { form, types, sizes, barcodeFormats, availableFields, submitLabel: "Create", onSubmit: submit }));
}
LabelTemplateCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  LabelTemplateCreate as default
};
