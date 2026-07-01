import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { Button, Dropdown, TextField } from "@openmes/ui";
import AppLayout from "../../layouts/AppLayout";
import { __ } from "../../lib/i18n";
const LABEL_CLASS = "block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]";
const INPUT_CLASS = "w-full bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)]";
function InspectionsCreate() {
  const { materials = [], plans = [] } = usePage().props;
  const { data, setData, post, processing, errors } = useForm({
    material_id: "",
    lot_number: "",
    supplier_lot_ref: "",
    quantity_received: "",
    inspection_plan_id: ""
  });
  const submit = (e) => {
    e.preventDefault();
    post("/inspections");
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Start Inspection") }), /* @__PURE__ */ React.createElement("div", { className: "max-w-2xl mx-auto" }, /* @__PURE__ */ React.createElement("h1", { className: "text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-4" }, __("Start Inspection")), /* @__PURE__ */ React.createElement("form", { onSubmit: submit, className: "bg-om-card border border-om-line rounded-om p-6 space-y-5" }, /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS }, __("Material"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.material_id == null ? "" : String(data.material_id),
      onChange: (v) => setData("material_id", v),
      placeholder: __("-- choose --"),
      options: materials.map((m) => ({
        value: String(m.id),
        label: `${m.code} \u2014 ${m.name}`
      })),
      className: "w-full"
    }
  ), errors.material_id && /* @__PURE__ */ React.createElement("p", { className: "text-[11.5px] text-om-blocked mt-1" }, errors.material_id)), /* @__PURE__ */ React.createElement("div", { className: "grid grid-cols-2 gap-3" }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: /* @__PURE__ */ React.createElement(React.Fragment, null, __("Lot number"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")),
      mono: true,
      required: true,
      maxLength: 100,
      value: data.lot_number,
      onChange: (v) => setData("lot_number", v),
      error: errors.lot_number
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("Supplier lot ref"),
      mono: true,
      maxLength: 100,
      value: data.supplier_lot_ref,
      onChange: (v) => setData("supplier_lot_ref", v),
      error: errors.supplier_lot_ref
    }
  )), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("Quantity received"),
      mono: true,
      type: "number",
      step: "0.001",
      min: "0",
      value: data.quantity_received,
      onChange: (v) => setData("quantity_received", v),
      error: errors.quantity_received
    }
  ), /* @__PURE__ */ React.createElement("div", null, /* @__PURE__ */ React.createElement("label", { className: LABEL_CLASS }, __("Inspection plan")), /* @__PURE__ */ React.createElement(
    Dropdown,
    {
      value: data.inspection_plan_id == null ? "" : String(data.inspection_plan_id),
      onChange: (v) => setData("inspection_plan_id", v),
      placeholder: __("\u2014 no plan (ad-hoc) \u2014"),
      options: plans.map((plan) => ({
        value: String(plan.id),
        label: `${plan.name}${plan.material ? ` (${plan.material.name})` : ""}${plan.material_type ? ` (${plan.material_type.name})` : ""}`
      })),
      className: "w-full"
    }
  ), /* @__PURE__ */ React.createElement("p", { className: "text-[12.5px] text-om-muted mt-1" }, __("If no plan is selected, you can still record results but no criteria will be pre-filled.")), errors.inspection_plan_id && /* @__PURE__ */ React.createElement("p", { className: "text-[11.5px] text-om-blocked mt-1" }, errors.inspection_plan_id)), /* @__PURE__ */ React.createElement("div", { className: "flex justify-end gap-2" }, /* @__PURE__ */ React.createElement(
    Link,
    {
      href: "/inspections",
      className: "inline-flex items-center justify-center rounded-om-sm border border-om-line px-4 py-[9px] text-[13px] font-semibold text-om-ink hover:bg-om-chip transition-colors"
    },
    __("Cancel")
  ), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent", loading: processing }, __("Start"))))));
}
InspectionsCreate.layout = (page) => /* @__PURE__ */ React.createElement(AppLayout, null, page);
export {
  InspectionsCreate as default
};
