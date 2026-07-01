import { Head, Link, useForm } from "@inertiajs/react";
import { Button, TextField } from "@openmes/ui";
import OnboardingLayout from "../../layouts/OnboardingLayout";
import { __ } from "../../lib/i18n";
function Step2() {
  const form = useForm({ name: "", code: "", unit_of_measure: "pcs" });
  const { data, setData, post, processing, errors } = form;
  const submit = (e) => {
    e.preventDefault();
    post("/onboarding/step/2");
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Step 2 \u2014 Product Type") }), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-2" }, "Step 2/4"), /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-semibold tracking-[-0.02em] text-om-ink mb-2" }, __("Add a Product Type")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-6" }, __("What product does this line produce? Define the product type.")), /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: /* @__PURE__ */ React.createElement(React.Fragment, null, __("Product Name"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")),
      id: "name",
      value: data.name,
      onChange: (v) => setData("name", v),
      error: errors.name,
      required: true,
      placeholder: __("e.g. Air Filter")
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: /* @__PURE__ */ React.createElement(React.Fragment, null, __("Code"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")),
      id: "code",
      value: data.code,
      onChange: (v) => setData("code", v),
      error: errors.code,
      required: true,
      placeholder: __("e.g. FILTER")
    }
  ), /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: __("Unit of Measure"),
      id: "unit_of_measure",
      value: data.unit_of_measure,
      onChange: (v) => setData("unit_of_measure", v),
      placeholder: __("pcs, kg, m...")
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex items-center justify-between mt-6" }, /* @__PURE__ */ React.createElement(Link, { href: "/onboarding/step/1", className: "text-om-muted hover:text-om-ink text-[13px] transition-colors" }, "\u2190 Back"), /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent", loading: processing }, processing ? __("Saving\u2026") : __("Next: Process Template \u2192")))));
}
Step2.layout = (page) => /* @__PURE__ */ React.createElement(OnboardingLayout, null, page);
export {
  Step2 as default
};
