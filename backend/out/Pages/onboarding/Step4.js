import { Head, Link, useForm } from "@inertiajs/react";
import { Button, TextField } from "@openmes/ui";
import OnboardingLayout from "../../layouts/OnboardingLayout";
import { __ } from "../../lib/i18n";
const currentYear = (/* @__PURE__ */ new Date()).getFullYear();
const DEFAULT_ORDER_NO = `WO-${currentYear}-001`;
function Step4() {
  const form = useForm({ order_no: DEFAULT_ORDER_NO, planned_qty: "100", description: "" });
  const { data, setData, post, processing, errors } = form;
  const submit = (e) => {
    e.preventDefault();
    post("/onboarding/step/4");
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Step 4 \u2014 Work Order") }), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-2" }, "Step 4/4"), /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-semibold tracking-[-0.02em] text-om-ink mb-2" }, __("Create First Work Order")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-6" }, __("A work order represents a production batch to manufacture. Create your first one.")), /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: /* @__PURE__ */ React.createElement(React.Fragment, null, __("Order Number"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")),
      id: "order_no",
      mono: true,
      value: data.order_no,
      onChange: (v) => setData("order_no", v),
      error: errors.order_no,
      required: true
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: /* @__PURE__ */ React.createElement(React.Fragment, null, __("Planned Quantity"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")),
      id: "planned_qty",
      type: "number",
      value: data.planned_qty,
      onChange: (v) => setData("planned_qty", v),
      error: errors.planned_qty,
      required: true,
      step: "0.01",
      min: "0.01"
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("Description"),
      id: "description",
      multiline: true,
      rows: 2,
      value: data.description,
      onChange: (v) => setData("description", v),
      placeholder: __("Optional notes")
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mt-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/onboarding/step/3", className: "text-om-muted hover:text-om-ink text-[13px] transition-colors" }, "\u2190 Back"), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent", loading: processing }, processing ? __("Saving\u2026") : __("Complete Setup")))));
}
Step4.layout = (page) => /* @__PURE__ */ React.createElement(OnboardingLayout, null, page);
export {
  Step4 as default
};
