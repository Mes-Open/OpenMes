import { Head, useForm, usePage } from "@inertiajs/react";
import { __ } from "../../../lib/i18n";
import { Button, Checkbox } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
function ProcessTemplatesEdit() {
  const { productType, processTemplate } = usePage().props;
  const form = useForm({
    name: processTemplate.name ?? "",
    is_active: !!processTemplate.is_active
  });
  const { data, setData, errors, processing } = form;
  const submit = (e) => {
    e.preventDefault();
    form.put(
      `/admin/product-types/${productType.id}/process-templates/${processTemplate.id}`
    );
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Edit Process Template") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-2xl mx-auto" }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `/admin/product-types/${productType.id}/process-templates`,
      className: "text-om-accent hover:text-om-accent flex items-center gap-2 mb-4"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    "Back to Templates"
  ), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Edit Process Template")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, productType.name, " \u2014 Version ", processTemplate.version)), /* @__PURE__ */ React.createElement("div", { className: "card" }, /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement("label", { htmlFor: "name", className: "form-label" }, "Template Name"), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      id: "name",
      value: data.name,
      onChange: (e) => setData("name", e.target.value),
      className: `form-input w-full${errors.name ? " border-om-blocked" : ""}`,
      placeholder: "e.g., Standard Assembly Process, Quality Inspection v2",
      required: true,
      autoFocus: true
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, "Descriptive name for this manufacturing process"), errors.name && /* @__PURE__ */ React.createElement("p", { className: "text-om-blocked text-sm mt-1" }, errors.name)), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: data.is_active,
      onChange: (next) => setData("is_active", next),
      label: "Active (template is ready for use in work orders)"
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex justify-end gap-3" }, /* @__PURE__ */ React.createElement(
    "a",
    {
      href: `/admin/product-types/${productType.id}/process-templates`,
      className: "btn-touch btn-secondary"
    },
    "Cancel"
  ), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: processing, disabled: processing }, processing ? "Saving\u2026" : "Update Template"))))));
}
ProcessTemplatesEdit.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  ProcessTemplatesEdit as default
};
