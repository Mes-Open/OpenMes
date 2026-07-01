import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { Button, Checkbox } from "@openmes/ui";
import AppLayout from "../../../layouts/AppLayout";
import CustomFields from "../../../components/CustomFields";
import { customFieldInitial, customFieldProps, submitForm } from "../../../lib/customFieldForm";
import { __ } from "../../../lib/i18n";
function WorkstationCreate() {
  const { line, customFields = [] } = usePage().props;
  const form = useForm({
    code: "",
    name: "",
    workstation_type: "",
    is_active: true,
    ...customFieldInitial()
  });
  const submit = (e) => {
    e.preventDefault();
    submitForm(form, "post", `/admin/lines/${line.id}/workstations`);
  };
  return /* @__PURE__ */ React.createElement("div", { className: "max-w-2xl mx-auto" }, /* @__PURE__ */ React.createElement(Head, { title: __("Create Workstation") }), /* @__PURE__ */ React.createElement("div", { className: "mb-6" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}/workstations`,
      className: "text-om-accent hover:text-om-accent flex items-center gap-2 mb-4 text-sm"
    },
    /* @__PURE__ */ React.createElement("svg", { className: "w-5 h-5", fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" }, /* @__PURE__ */ React.createElement("path", { strokeLinecap: "round", strokeLinejoin: "round", strokeWidth: "2", d: "M15 19l-7-7 7-7" })),
    __("Back to Workstations")
  ), /* @__PURE__ */ React.createElement("h1", { className: "text-3xl font-bold text-om-ink" }, __("Create Workstation")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, line.name)), /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "bg-om-card rounded-om-sm shadow-sm p-6 space-y-5" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Workstation Code"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      name: "code",
      value: form.data.code,
      onChange: (e) => form.setData("code", e.target.value),
      placeholder: __("e.g., WS-A01, ASSEMBLY-1"),
      className: "form-input w-full",
      required: true,
      autoFocus: true
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Unique identifier for this workstation")), form.errors.code && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, form.errors.code)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Workstation Name"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-blocked" }, "*")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      name: "name",
      value: form.data.name,
      onChange: (e) => form.setData("name", e.target.value),
      placeholder: __("e.g., Assembly Station 1, Quality Check Point"),
      className: "form-input w-full",
      required: true
    }
  ), form.errors.name && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, form.errors.name)), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: "block text-sm font-medium text-om-muted mb-1" }, __("Workstation Type")), /* @__PURE__ */ React.createElement(
    "input",
    {
      type: "text",
      value: form.data.workstation_type,
      onChange: (e) => form.setData("workstation_type", e.target.value),
      placeholder: __("e.g., Assembly, Quality Control, Packaging (optional)"),
      className: "form-input w-full"
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mt-1" }, __("Optional classification for this workstation")), form.errors.workstation_type && /* @__PURE__ */ React.createElement("p", { className: "mt-1 text-xs text-om-blocked" }, form.errors.workstation_type)), /* @__PURE__ */ React.createElement(
    Checkbox,
    {
      checked: form.data.is_active,
      onChange: (next) => form.setData("is_active", next),
      label: __("Active (workstation is ready for use)")
    }
  ), customFields.length > 0 && /* @__PURE__ */ React.createElement(CustomFields, { ...customFieldProps(form, customFields) }), /* @__PURE__ */ React.createElement("div", { className: "flex items-center gap-3 pt-2" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "primary", loading: form.processing, disabled: form.processing }, form.processing ? __("Creating\u2026") : __("Create Workstation")), /* @__PURE__ */ React.createElement(
    Link,
    {
      href: `/admin/lines/${line.id}/workstations`,
      className: "text-om-muted hover:text-om-ink text-sm"
    },
    __("Cancel")
  ))));
}
WorkstationCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  WorkstationCreate as default
};
