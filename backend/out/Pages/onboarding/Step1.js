import { Head, useForm } from "@inertiajs/react";
import { Button, TextField } from "@openmes/ui";
import OnboardingLayout from "../../layouts/OnboardingLayout";
import { __ } from "../../lib/i18n";
function Step1() {
  const form = useForm({ name: "", code: "", description: "" });
  const { data, setData, post, processing, errors } = form;
  const submit = (e) => {
    e.preventDefault();
    post("/onboarding/step/1");
  };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Head, { title: __("Step 1 \u2014 Production Line") }), /* @__PURE__ */ React.createElement("div", { className: "font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-2" }, "Step 1/4"), /* @__PURE__ */ React.createElement("h2", { className: "text-xl font-semibold tracking-[-0.02em] text-om-ink mb-2" }, __("Create a Production Line")), /* @__PURE__ */ React.createElement("p", { className: "text-sm text-om-muted mb-6" }, __("A production line is a physical area where manufacturing happens. Start by creating your first one.")), /* @__PURE__ */ React.createElement("form", { onSubmit: submit }, /* @__PURE__ */ React.createElement("div", { className: "space-y-4" }, /* @__PURE__ */ React.createElement(
    TextField,
    {
      label: /* @__PURE__ */ React.createElement(React.Fragment, null, __("Line Name"), " ", /* @__PURE__ */ React.createElement("span", { className: "text-om-accent" }, "*")),
      id: "name",
      value: data.name,
      onChange: (v) => setData("name", v),
      error: errors.name,
      required: true,
      placeholder: __("e.g. Injection Line 1")
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
      placeholder: __("e.g. INJ-01")
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
      placeholder: __("Optional description")
    }
  )), /* @__PURE__ */ React.createElement("div", { className: "flex justify-end mt-6" }, /* @__PURE__ */ React.createElement(Button, { type: "submit", variant: "accent", loading: processing }, processing ? __("Saving\u2026") : __("Next: Product Type \u2192")))));
}
Step1.layout = (page) => /* @__PURE__ */ React.createElement(OnboardingLayout, null, page);
export {
  Step1 as default
};
